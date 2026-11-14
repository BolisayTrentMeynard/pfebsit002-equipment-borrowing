<#
Week 16, chunk 4: read-only scan of the NEW part of laravel.log after the MySQL-stopped run (Windows PowerShell 5.1 or newer).
Run it from the repo root, in a standalone PowerShell window:

  powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\scan-outage-log.ps1 -StartBytes <number>

<number> is the size of backend\storage\logs\laravel.log in bytes that you wrote down BEFORE starting k6
(`(Get-Item backend\storage\logs\laravel.log).Length`). Only the text after that position is read.

It prints counts only, plus two sample lines in which every 64-character hex string is replaced by <64-hex>.
It never prints a token, a key, an email address or a password. It changes nothing.
Limits: the search is only as good as its patterns (Bearer, password, an email shape, a 64-character hex string);
a secret in another shape would not be found.
#>
param(
    [Parameter(Mandatory = $true)][long]$StartBytes,
    [string]$LogPath = 'backend\storage\logs\laravel.log'
)

if (-not (Test-Path $LogPath)) { Write-Host "STOP: $LogPath not found. Run this from the repo root."; exit 1 }
$len = (Get-Item $LogPath).Length
if ($StartBytes -gt $len) { Write-Host "STOP: StartBytes ($StartBytes) is larger than the file ($len). Was the log cleared or rotated?"; exit 1 }

$fs = [System.IO.File]::Open($LogPath, [System.IO.FileMode]::Open, [System.IO.FileAccess]::Read, [System.IO.FileShare]::ReadWrite)
try {
    $null = $fs.Seek($StartBytes, [System.IO.SeekOrigin]::Begin)
    $sr = New-Object System.IO.StreamReader($fs, [System.Text.Encoding]::UTF8)
    $text = $sr.ReadToEnd()
}
finally { $fs.Dispose() }

function Mask([string]$s, [int]$max) {
    $m = [regex]::Replace($s, '[0-9a-fA-F]{64}', '<64-hex>')
    if ($m.Length -gt $max) { $m = $m.Substring(0, $max) + ' ...' }
    return $m
}

$lines = $text -split "`r?`n"
$entryRx = '^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \w+\.(\w+): '

Write-Host ''
Write-Host "== Log growth: start $StartBytes bytes, end $len bytes, new text $($len - $StartBytes) bytes ($([math]::Round(($len - $StartBytes) / 1KB, 1)) KB), $($lines.Count) lines"

Write-Host ''
Write-Host '== Entries by level (a stack trace belongs to the ERROR entry above it)'
$levels = @{}
foreach ($l in $lines) {
    $m = [regex]::Match($l, $entryRx)
    if ($m.Success) { $k = $m.Groups[1].Value; if ($levels.ContainsKey($k)) { $levels[$k]++ } else { $levels[$k] = 1 } }
}
$levels.GetEnumerator() | Sort-Object Name | ForEach-Object { Write-Host ("  {0,-10} {1}" -f $_.Name, $_.Value) }

Write-Host ''
Write-Host '== http.request lines by status'
$byStatus = @{}
$httpLines = 0
foreach ($l in $lines) {
    if ($l -match 'http\.request \{') {
        $httpLines++
        $m = [regex]::Match($l, '"status":(\d+)')
        if ($m.Success) { $k = $m.Groups[1].Value; if ($byStatus.ContainsKey($k)) { $byStatus[$k]++ } else { $byStatus[$k] = 1 } }
    }
}
Write-Host "  total http.request lines: $httpLines"
$byStatus.GetEnumerator() | Sort-Object Name | ForEach-Object { Write-Host ("  status {0}: {1}" -f $_.Name, $_.Value) }

Write-Host ''
Write-Host '== Readiness failures logged (health.ready_failed)'
$rf = @($lines | Where-Object { $_ -match 'health\.ready_failed' })
Write-Host "  lines: $($rf.Count)"
if ($rf.Count -gt 0) { Write-Host ('  first : ' + (Mask $rf[0] 200)) }

Write-Host ''
Write-Host '== Exceptions (ERROR entries)'
$errs = @($lines | Where-Object { $_ -match $entryRx -and $_ -match '\.ERROR: ' })
$sql = @($errs | Where-Object { $_ -match 'SQLSTATE' })
Write-Host "  ERROR entries: $($errs.Count), of them with SQLSTATE in the first line: $($sql.Count)"
$groups = $errs | ForEach-Object { Mask (($_ -replace $entryRx, '')) 90 } | Group-Object | Sort-Object Count -Descending | Select-Object -First 3
foreach ($g in $groups) { Write-Host ("  {0,4} x  {1}" -f $g.Count, $g.Name) }
$withSqlText = @($sql | Where-Object { $_ -match 'SQL: ' })
Write-Host "  first lines that also hold the SQL text ('SQL: ...', which can include bound values): $($withSqlText.Count)"
if ($withSqlText.Count -gt 0) { Write-Host ('  sample (hex masked): ' + (Mask ($withSqlText[0] -replace $entryRx, '') 260)) }

Write-Host ''
Write-Host '== Secret-shape searches in the new text (counts of LINES; a count above 0 is a finding to read, not hide)'
$bearer = @($lines | Where-Object { $_ -match 'Bearer' }).Count
$pass = @($lines | Where-Object { $_ -match '(?i)password' }).Count
$mail = @($lines | Where-Object { $_ -match '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' }).Count
$hex = @($lines | Where-Object { $_ -match '\b[0-9a-fA-F]{64}\b' }).Count
$sig = @($lines | Where-Object { $_ -match 'X-Message-Signature' }).Count
Write-Host "  Bearer .................... $bearer"
Write-Host "  password (any case) ....... $pass"
Write-Host "  email-shaped text ......... $mail"
Write-Host "  64-character hex string ... $hex   (a stored token HASH has this shape; the plain token never appears)"
Write-Host "  X-Message-Signature ....... $sig"

Write-Host ''
Write-Host '== Database connection frames in stack traces (do the arguments hide the password?)'
$frames = @($lines | Where-Object { $_ -match 'createPdoConnection|PDO->__construct' })
$hidden = @($frames | Where-Object { $_ -match 'SensitiveParameterValue' })
Write-Host "  frames: $($frames.Count), of them showing SensitiveParameterValue (password hidden): $($hidden.Count)"
if ($frames.Count -gt $hidden.Count) {
    Write-Host '  NOTE: some frames do not show SensitiveParameterValue. Look at them yourself, with the screen not shared, and tell Claude only whether a password is visible.'
}
Write-Host ''
Write-Host 'Done. Nothing was changed.'
