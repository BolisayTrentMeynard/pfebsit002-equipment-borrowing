<#
Week 16, chunk 5 (AC-51): backup with mysqldump, restore into a SCRATCH database, prove the copy matches.
Windows PowerShell 5.1 or newer. Run from a standalone window in the repo root:
  powershell -NoProfile -ExecutionPolicy Bypass -File deploy\backup-restore.ps1

What it does (the source database is only READ; nothing in equipment_staging or equipment_borrowing changes):
  1 finds mysql.exe and mysqldump.exe (PATH or C:\xampp\mysql\bin) and checks MySQL answers
  2 dumps the source database to a .sql file OUTSIDE the repo (folder pfebsit002-backups next to the repo folder)
  3 prints the file size and its SHA-256
  4 creates a scratch database named equipment_restore_check (it only ever drops databases named equipment_restore_*)
  5 restores the dump into the scratch database
  6 compares every table: row count and CHECKSUM TABLE, source against scratch
  7 drops the scratch database (unless -KeepScratch)
Options: -Source equipment_staging (default)   -KeepScratch

SECURITY: the dump holds fake users with password hashes and token hashes. It stays outside the repo. Do not open it
on screen, do not upload it, do not screenshot its contents (screenshot this script's output instead).
Limits: a logical dump of a tiny local database; restore time and size here say nothing about production; the
scratch database is on the same MySQL server (a real rehearsal would use another machine). Root without a password is
assumed (the XAMPP default); if root has a password the script stops and says so.
#>
param(
    [string]$Source = 'equipment_staging',
    [switch]$KeepScratch
)

$ErrorActionPreference = 'Continue'
$Scratch = 'equipment_restore_check'
$Root = Split-Path -Parent $PSScriptRoot
function Ok($t)   { Write-Host "  ok    $t" -ForegroundColor Green }
function Fail($t) { Write-Host ''; Write-Host "STOP: $t" -ForegroundColor Red; exit 1 }
function Step($n, $t) { Write-Host ''; Write-Host "== Step ${n}: $t" -ForegroundColor Cyan }

if ($Source -notmatch '^equipment_[a-z0-9_]+$' -or $Source -match '^equipment_restore_') { Fail "Source '$Source' is not an allowed name (expected equipment_staging or equipment_borrowing)." }
if ($Scratch -notmatch '^equipment_restore_[a-z0-9_]+$') { Fail 'Internal guard: the scratch name must start with equipment_restore_.' }

Step 1 'Tools and MySQL'
function Find-Tool($name) {
    $c = Get-Command $name -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    $p = Join-Path 'C:\xampp\mysql\bin' $name
    if (Test-Path $p) { return $p }
    return $null
}
$mysql = Find-Tool 'mysql.exe'; $dump = Find-Tool 'mysqldump.exe'
if (-not $mysql -or -not $dump) { Fail 'mysql.exe or mysqldump.exe not found (looked on PATH and in C:\xampp\mysql\bin).' }
Ok 'mysql.exe and mysqldump.exe found'
$ver = (& $mysql -u root -N -B -e 'SELECT VERSION()' 2>&1 | Out-String).Trim()
if ($LASTEXITCODE -ne 0) { Fail "MySQL did not answer (is it running in XAMPP? does root have a password?). Output: $ver" }
Ok "MySQL answers, server version $ver"
$exists = (& $mysql -u root -N -B -e "SHOW DATABASES LIKE '$Source'" 2>&1 | Out-String).Trim()
if ($exists -ne $Source) { Fail "Database $Source does not exist." }
Ok "source database $Source exists"

Step 2 'Dump the source database (outside the repo)'
$dir = Join-Path (Split-Path -Parent $Root) 'pfebsit002-backups'
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir | Out-Null }
$file = Join-Path $dir ("{0}-{1}.sql" -f $Source, (Get-Date).ToString('yyyyMMdd-HHmmss'))
$sw = [Diagnostics.Stopwatch]::StartNew()
& $dump -u root --single-transaction --default-character-set=utf8mb4 --skip-comments "--result-file=$file" $Source 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $file)) { Fail 'mysqldump failed.' }
$sw.Stop()
Ok ('dump written in {0:N1} s' -f $sw.Elapsed.TotalSeconds)
Write-Host "  folder: $dir"

Step 3 'Size and SHA-256 of the dump'
$len = (Get-Item $file).Length
if ($len -lt 1000) { Fail "The dump is only $len bytes. Something is wrong." }
Write-Host ('  size:   {0:N0} bytes ({1:N1} KB)' -f $len, ($len / 1KB))
Write-Host ('  sha256: {0}' -f (Get-FileHash -Algorithm SHA256 -Path $file).Hash)

Step 4 "Create the scratch database $Scratch"
& $mysql -u root -e "DROP DATABASE IF EXISTS $Scratch; CREATE DATABASE $Scratch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'Could not create the scratch database.' }
Ok "scratch database $Scratch created (empty)"

Step 5 'Restore the dump into the scratch database'
$sw.Restart()
$src = $file.Replace('\', '/')
& $mysql -u root --default-character-set=utf8mb4 $Scratch -e "source $src" 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'The restore failed.' }
$sw.Stop()
Ok ('restore finished in {0:N1} s' -f $sw.Elapsed.TotalSeconds)

Step 6 'Compare every table: row count and CHECKSUM TABLE (source against scratch)'
$tables = @(& $mysql -u root -N -B $Source -e 'SHOW TABLES' 2>&1) | Where-Object { $_ -and $_ -notmatch '^ERROR' }
$scratchTables = @(& $mysql -u root -N -B $Scratch -e 'SHOW TABLES' 2>&1) | Where-Object { $_ -and $_ -notmatch '^ERROR' }
$bad = 0
'{0,-26} {1,10} {2,10}  {3}' -f 'table', 'rows src', 'rows copy', 'checksum' | Write-Host
foreach ($t in $tables) {
    $a = (& $mysql -u root -N -B $Source  -e "SELECT COUNT(*) FROM ``$t``" 2>&1 | Out-String).Trim()
    $b = (& $mysql -u root -N -B $Scratch -e "SELECT COUNT(*) FROM ``$t``" 2>&1 | Out-String).Trim()
    $ca = ((& $mysql -u root -N -B -e "CHECKSUM TABLE $Source.``$t``" 2>&1 | Out-String).Trim() -split "\s+")[-1]
    $cb = ((& $mysql -u root -N -B -e "CHECKSUM TABLE $Scratch.``$t``" 2>&1 | Out-String).Trim() -split "\s+")[-1]
    $same = ($a -eq $b) -and ($ca -eq $cb)
    $mark = if ($same) { 'same' } else { 'DIFFERENT' }
    if (-not $same) { $bad++ }
    $color = if ($same) { 'Green' } else { 'Red' }
    Write-Host ('{0,-26} {1,10} {2,10}  {3}' -f $t, $a, $b, $mark) -ForegroundColor $color
}
if ($tables.Count -ne $scratchTables.Count) { Write-Host ("  DIFFERENT number of tables: source {0}, copy {1}" -f $tables.Count, $scratchTables.Count) -ForegroundColor Red; $bad++ }
Write-Host ("  tables compared: {0}" -f $tables.Count)

Step 7 'Clean up the scratch database'
if ($KeepScratch) { Write-Host "  -KeepScratch: $Scratch left in place (drop it yourself when done)." -ForegroundColor Yellow }
else {
    & $mysql -u root -e "DROP DATABASE IF EXISTS $Scratch" 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { Ok "$Scratch dropped" } else { Write-Host "  note  could not drop $Scratch" -ForegroundColor Yellow }
}

Write-Host ''
if ($bad -gt 0 -or $tables.Count -eq 0) { Write-Host "RESULT: $bad difference(s). The backup is NOT proven good." -ForegroundColor Red; exit 1 }
Write-Host ("RESULT: backup restored into {0}; all {1} tables match (rows and checksum). Dump kept outside the repo: {2}" -f $Scratch, $tables.Count, $file) -ForegroundColor Green
exit 0
