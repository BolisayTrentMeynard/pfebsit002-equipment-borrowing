<#
Week 16, chunk 5 (AC-51): rollback rehearsal. Run the week-15 CODE behind the same staging front door, check it answers,
then go back. Windows PowerShell 5.1 or newer, standalone window, repo root.

  1) In the STAGING API window (port 8001) press Ctrl+C so port 8001 is free. Leave the Caddy window (9443) running.
  2) powershell -NoProfile -ExecutionPolicy Bypass -File deploy\rollback-rehearsal.ps1 -Action Start
  3) look at the output, take your screenshots, then press Ctrl+C in the ROLLBACK API window the script opened
  4) powershell -NoProfile -ExecutionPolicy Bypass -File deploy\rollback-rehearsal.ps1 -Action Cleanup
  5) start the normal staging API again (command in the chunk 5 hand-over, or run deploy\staging-release.ps1 -SkipBuild -KeepData)

How it works: `git worktree add` checks the tag `week-15` out into a SEPARATE folder next to the repo
(pfebsit002-rollback-week15). Your working folder, branch and uncommitted files are not touched. The folder gets a
junction to the existing backend\vendor (no composer install) and a copy of backend\.env.staging (never printed). The
week-15 API then runs on port 8001 against the SAME database equipment_staging.
It refuses to start when the git history shows a migration or a composer.lock change between week-15 and HEAD: then the
database or the libraries would not match and a rollback would NOT be code-only. (Week 16 so far has neither.)
Expected on the week-15 code: /up 200, no token 401, login 200, equipment 200, requests 200, and
/api/health/ready 404 (that route was added in Week 16): a monitor that polls readiness would read the rollback as
"down". That is a finding for the rollback plan, not a failure of the rehearsal.
No key, token or password is printed. The password (LOAD_TEST_PASSWORD) is only used for the login check and goes
through a temp file. If it is not set, the login and list checks are skipped and the output says so.
#>
param(
    [Parameter(Mandatory = $true)][ValidateSet('Start', 'Cleanup')][string]$Action
)

$ErrorActionPreference = 'Continue'
$Root    = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root 'backend'
$WtPath  = Join-Path (Split-Path -Parent $Root) 'pfebsit002-rollback-week15'
$Base    = 'https://localhost:9443'
$ApiPort = 8001
function Ok($t)   { Write-Host "  ok    $t" -ForegroundColor Green }
function Warn($t) { Write-Host "  note  $t" -ForegroundColor Yellow }
function Fail($t) { Write-Host ''; Write-Host "STOP: $t" -ForegroundColor Red; exit 1 }
function Step($n, $t) { Write-Host ''; Write-Host "== Step ${n}: $t" -ForegroundColor Cyan }
function Test-Port($port) {
    try {
        $c = New-Object System.Net.Sockets.TcpClient
        $iar = $c.BeginConnect('127.0.0.1', $port, $null, $null)
        $done = $iar.AsyncWaitHandle.WaitOne(800)
        $up = $done -and $c.Connected
        $c.Close()
        return $up
    } catch { return $false }
}
function Code($url, $headerFile) {
    $a = @('--ssl-no-revoke', '-s', '-o', 'NUL', '-m', '20', '-w', '%{http_code}', '-H', 'Accept: application/json')
    if ($headerFile) { $a += @('-H', "@$headerFile") }
    $a += $url
    return ((& curl.exe @a) -join '').Trim()
}

Set-Location $Root
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Fail 'git not found.' }

# ------------------------------------------------------------------------------------------------
if ($Action -eq 'Cleanup') {
    Step 1 'Clean up the rollback folder'
    if (Test-Port $ApiPort) { Fail "Port $ApiPort still answers. Press Ctrl+C in the ROLLBACK API window first, then run Cleanup again." }
    $junction = Join-Path $WtPath 'backend\vendor'
    if (Test-Path $junction) {
        & cmd /c rmdir "$junction"      # removes ONLY the junction, never the files it points to
        if (Test-Path $junction) { Fail "Could not remove the vendor junction at $junction. Nothing else was deleted." }
        Ok 'vendor junction removed (the real backend\vendor is untouched)'
    }
    if (Test-Path $WtPath) {
        & git worktree remove --force "$WtPath" 2>&1 | Out-Null
        if (Test-Path $WtPath) { Warn "Folder $WtPath is still there. Close anything using it and delete it by hand." }
        else { Ok 'worktree removed' }
    } else { Ok 'no rollback folder to remove' }
    & git worktree prune
    $vendorFiles = (Get-ChildItem (Join-Path $Backend 'vendor') -File -ErrorAction SilentlyContinue | Measure-Object).Count
    $vendorDirs  = (Get-ChildItem (Join-Path $Backend 'vendor') -Directory -ErrorAction SilentlyContinue | Measure-Object).Count
    if ($vendorDirs -gt 5) { Ok "backend\vendor is intact ($vendorDirs folders)" } else { Write-Host "  CHECK backend\vendor looks small ($vendorDirs folders, $vendorFiles files): run composer install in backend if the app fails." -ForegroundColor Red }
    Write-Host ''
    Write-Host 'RESULT: cleaned up. Now start the normal staging API again.' -ForegroundColor Green
    exit 0
}

# ------------------------------------------------------------------------------------------------
Step 1 'Safety checks'
if (-not (Test-Path $Backend)) { Fail 'Run this from the repo (deploy folder not found where expected).' }
& git rev-parse -q --verify refs/tags/week-15 | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'The tag week-15 does not exist in this clone (run: git fetch --tags).' }
$head = (& git rev-parse --short HEAD).Trim(); $tag = (& git rev-parse --short week-15).Trim()
Ok "HEAD is $head, week-15 is $tag"
$changed = @(& git diff --name-only week-15 HEAD -- backend/database/migrations backend/composer.lock)
if ($changed.Count -gt 0) {
    $changed | ForEach-Object { Write-Host "  differs: $_" -ForegroundColor Red }
    Fail 'A migration or composer.lock differs between week-15 and HEAD. Rolling back the code alone is not safe. Nothing was changed.'
}
Ok 'no migration and no composer.lock change between week-15 and HEAD (the rollback is code-only)'
$appChanged = @(& git diff --name-only week-15 HEAD -- app/lib)
Write-Host ("  info  Dart files changed since week-15: {0}" -f $appChanged.Count)
if (-not (Test-Port 9443)) { Fail 'Caddy (9443) is not running. Start it first (command in the hand-over).' }
Ok 'Caddy answers on 9443'
if (Test-Port $ApiPort) { Fail "Port $ApiPort is in use. Press Ctrl+C in the STAGING API window first." }
Ok "port $ApiPort is free"
if (Test-Path $WtPath) { Fail "$WtPath already exists. Run -Action Cleanup first." }
if (-not (Test-Path (Join-Path $Backend '.env.staging'))) { Fail 'backend\.env.staging is missing.' }
if (-not (Test-Path (Join-Path $Backend 'vendor\autoload.php'))) { Fail 'backend\vendor is missing (run composer install in backend).' }

Step 2 'Check the week-15 code out into a separate folder'
& git worktree add --detach "$WtPath" week-15 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0 -or -not (Test-Path (Join-Path $WtPath 'backend\artisan'))) { Fail 'git worktree add failed.' }
Ok "week-15 checked out at $WtPath"
$wtBackend = Join-Path $WtPath 'backend'
& cmd /c mklink /J "$wtBackend\vendor" "$Backend\vendor" | Out-Null
if (-not (Test-Path (Join-Path $wtBackend 'vendor\autoload.php'))) { Fail 'Could not link vendor. Run -Action Cleanup, then try again.' }
Ok 'vendor linked (junction, no copy)'
Copy-Item (Join-Path $Backend '.env.staging') (Join-Path $wtBackend '.env.staging') -Force
foreach ($d in 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs', 'bootstrap\cache') {
    New-Item -ItemType Directory -Force -Path (Join-Path $wtBackend $d) | Out-Null
}
Ok '.env.staging copied (not printed) and storage folders ready'

Step 3 'Start the week-15 API on port 8001 in its own window'
$cmd = "`$Host.UI.RawUI.WindowTitle = 'ROLLBACK API $ApiPort (week-15 code)'; `$env:APP_ENV = 'staging'; php artisan serve --host=127.0.0.1 --port=$ApiPort"
Start-Process powershell -WorkingDirectory $wtBackend -ArgumentList @('-NoExit', '-Command', $cmd)
$up = $false
for ($i = 1; $i -le 40; $i++) { if ((Code "$Base/up" $null) -eq '200') { $up = $true; break }; Start-Sleep -Seconds 1 }
if (-not $up) { Fail "$Base/up did not answer 200 within 40 s. Look at the ROLLBACK API window." }
Ok "$Base/up answers 200 through Caddy"

Step 4 'Checks on the week-15 code (through the normal front door)'
$failed = 0
function Check($label, $got, $want) {
    if ($got -eq $want) { Ok "$label -> $got" } else { Write-Host "  FAIL  $label -> $got (wanted $want)" -ForegroundColor Red; $script:failed++ }
}
Check 'GET /up (liveness)'                         (Code "$Base/up" $null) '200'
Check 'GET /api/me without a token'                (Code "$Base/api/me" $null) '401'
$ready = Code "$Base/api/health/ready" $null
Check 'GET /api/health/ready (not in week-15: 404 is expected)' $ready '404'
$tmp = Join-Path $env:TEMP ('rb-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tmp | Out-Null
try {
    if ($env:LOAD_TEST_PASSWORD -and $env:LOAD_TEST_PASSWORD.Length -ge 12) {
        $bodyFile = Join-Path $tmp 'login.json'; $hdr = Join-Path $tmp 'auth.txt'
        $json = '{"email":"loadborrower01@example.test","password":' + (ConvertTo-Json -InputObject $env:LOAD_TEST_PASSWORD) + '}'
        [IO.File]::WriteAllText($bodyFile, $json, (New-Object Text.UTF8Encoding($false)))
        $resp = (& curl.exe --ssl-no-revoke -s -m 30 -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary "@$bodyFile" "$Base/api/login") -join ''
        Remove-Item $bodyFile -Force
        $token = $null; try { $token = ($resp | ConvertFrom-Json).token } catch { }
        if ($token) {
            Ok 'POST /api/login -> 200 (token received, not printed)'
            [IO.File]::WriteAllText($hdr, "Authorization: Bearer $token", (New-Object Text.ASCIIEncoding))
            Check 'GET /api/equipment (with token)' (Code "$Base/api/equipment" $hdr) '200'
            Check 'GET /api/requests (with token)'  (Code "$Base/api/requests" $hdr) '200'
        } else {
            Write-Host '  FAIL  login gave no token (was staging seeded with LoadTestSeeder, same password?)' -ForegroundColor Red; $failed++
        }
    } else { Warn 'LOAD_TEST_PASSWORD not set in this window: login, equipment and requests checks SKIPPED.' }
} finally { Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue }

Write-Host ''
if ($failed -gt 0) { Write-Host "RESULT: $failed check(s) failed on the week-15 code." -ForegroundColor Red } else { Write-Host 'RESULT: the week-15 code answers behind the same front door on the same database.' -ForegroundColor Green }
Write-Host 'Next: screenshot this output, optionally open https://localhost:9443 and sign in (screenshot), then press Ctrl+C in the ROLLBACK API window and run -Action Cleanup.'
exit 0
