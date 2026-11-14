<#
Week 16, chunk 5 (AC-53): repeat the Week 14 and 15 secret scans in one script. Read-only. Windows PowerShell 5.1+.
Run from a standalone window in the repo root:
  powershell -NoProfile -ExecutionPolicy Bypass -File deploy\scan-secrets.ps1 -StartBytes <number>
  (omit -StartBytes to scan the whole backend\storage\logs\laravel.log)

It prints COUNTS and file names only. It never prints a key, a token, a password or a log line.
  A  key values: length of each signing key, then how many commits (git log --all -S) contain the key or APP_KEY value
     (dev .env and staging .env.staging), plus a POSITIVE CONTROL: the text NOTIFIER_SIGNING_KEY must be found, which
     proves the search works (the same control as in Week 14)
  B  tracked files: .env files (other than examples), .sqlite, .pem, backup or dump files, private-key or cloud-key patterns
  C  laravel.log from byte -StartBytes: Bearer, password, X-Message-Signature, the key values, e-mail-like text,
     64-hex strings (token hashes), the text token_hash, and the number of db.query_failed lines
Exit code 0 when every "must be zero" count is zero and the positive control is above zero; 1 otherwise.
Limits: it scans the repo history and the one log file. It does NOT scan the evidence zip, screenshots, the Drive folder
or Postman. A clean result means "not found by these searches", not "proved absent".
#>
param([long]$StartBytes = 0)

$ErrorActionPreference = 'Continue'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root
$bad = 0
function Head($t) { Write-Host ''; Write-Host "== $t" -ForegroundColor Cyan }
function Zero($label, $count) {
    if ($count -eq 0) { Write-Host ("  ok    {0,-58} {1}" -f $label, $count) -ForegroundColor Green }
    else { Write-Host ("  FOUND {0,-58} {1}" -f $label, $count) -ForegroundColor Red; $script:bad++ }
}
function Env-Value($file, $key) {
    if (-not (Test-Path $file)) { return $null }
    foreach ($line in Get-Content $file) {
        if ($line -match "^\s*$key\s*=\s*(.*)$") { return $Matches[1].Trim().Trim('"').Trim("'") }
    }
    return $null
}

Head 'A. Key values (lengths, then commits that contain them)'
$secrets = @()
foreach ($f in @(@('dev', 'backend\.env'), @('staging', 'backend\.env.staging'))) {
    foreach ($k in 'NOTIFIER_SIGNING_KEY', 'NOTIFIER_SIGNING_KEY_PREVIOUS', 'APP_KEY') {
        $v = Env-Value (Join-Path $Root $f[1]) $k
        if ($v -and $v.Length -ge 20) {
            $secrets += @{ label = "$($f[0]) $k"; value = $v }
            Write-Host ("  info  {0,-40} length {1}" -f "$($f[0]) $k", $v.Length)
        }
    }
}
if ($secrets.Count -eq 0) { Write-Host '  note  no key values found in backend\.env or backend\.env.staging (nothing to search for)' -ForegroundColor Yellow }
foreach ($s in $secrets) {
    $hits = @(& git log --all --oneline "-S$($s.value)" 2>$null).Count
    Zero ("commits containing: " + $s.label) $hits
}
$control = @(& git log --all --oneline '-SNOTIFIER_SIGNING_KEY' 2>$null).Count
if ($control -gt 0) { Write-Host ("  ok    positive control (text NOTIFIER_SIGNING_KEY found in {0} commits: the search works)" -f $control) -ForegroundColor Green }
else { Write-Host '  FAIL  positive control found nothing: the history search is not working' -ForegroundColor Red; $bad++ }

Head 'B. Tracked files'
$tracked = @(& git ls-files)
$envTracked = @($tracked | Where-Object { $_ -match '(^|/)\.env(\.[^/]*)?$' -and $_ -notmatch '\.example$' })
Zero 'tracked .env files (other than .example)' $envTracked.Count
$envTracked | ForEach-Object { Write-Host "        $_" -ForegroundColor Red }
$sqliteTracked = @($tracked | Where-Object { $_ -match '\.sqlite$' })
$pemTracked = @($tracked | Where-Object { $_ -match '\.pem$' })
Zero 'tracked .sqlite files' $sqliteTracked.Count
Zero 'tracked .pem files' $pemTracked.Count
$dumps = @($tracked | Where-Object { $_ -match '(backup|dump)' -and $_ -match '\.(sql|gz|zip)$' })
Zero 'tracked backup or dump files' $dumps.Count
$dumps | ForEach-Object { Write-Host "        $_" -ForegroundColor Red }
$pk = @(& git grep -l -I -E 'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|AKIA[0-9A-Z]{16}' 2>$null)
Zero 'tracked files with a private-key or cloud-key pattern' $pk.Count
$pk | ForEach-Object { Write-Host "        $_" -ForegroundColor Red }
$ak = @(& git grep -l -I -E '^APP_KEY=base64:[A-Za-z0-9+/=]{20,}' 2>$null)
Zero 'tracked files with a filled-in APP_KEY' $ak.Count
$ak | ForEach-Object { Write-Host "        $_" -ForegroundColor Red }

Head ("C. laravel.log from byte {0}" -f $StartBytes)
$log = Join-Path $Root 'backend\storage\logs\laravel.log'
if (-not (Test-Path $log)) { Write-Host '  note  no laravel.log found' -ForegroundColor Yellow }
else {
    $fs = [IO.File]::Open($log, 'Open', 'Read', 'ReadWrite')
    try {
        $size = $fs.Length
        if ($StartBytes -gt $size) { $StartBytes = 0; Write-Host '  note  -StartBytes is larger than the file: scanning the whole file' -ForegroundColor Yellow }
        $fs.Seek($StartBytes, 'Begin') | Out-Null
        $buf = New-Object byte[] ($size - $StartBytes)
        $read = 0
        while ($read -lt $buf.Length) { $n = $fs.Read($buf, $read, $buf.Length - $read); if ($n -le 0) { break }; $read += $n }
        $text = [Text.Encoding]::UTF8.GetString($buf, 0, $read)
    } finally { $fs.Close() }
    Write-Host ("  info  scanned {0:N0} bytes (file is {1:N0} bytes)" -f $read, $size)
    function Count-Re($pattern, $opts) { return ([regex]::Matches($text, $pattern, $opts)).Count }
    $ci = [Text.RegularExpressions.RegexOptions]::IgnoreCase
    $none = [Text.RegularExpressions.RegexOptions]::None
    Zero 'Bearer' (Count-Re 'Bearer' $none)
    Zero 'password (any case)' (Count-Re 'password' $ci)
    Zero 'X-Message-Signature' (Count-Re 'X-Message-Signature' $ci)
    foreach ($s in $secrets) { Zero ('key value in log: ' + $s.label) (([regex]::Matches($text, [regex]::Escape($s.value))).Count) }
    Zero 'e-mail-like text' (Count-Re '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' $none)
    Zero '64-hex strings (token hashes)' (Count-Re '\b[0-9a-f]{64}\b' $ci)
    Zero 'the text token_hash (SQL from a failed query)' (Count-Re 'token_hash' $none)
    Write-Host ("  info  db.query_failed lines (the sanitized entry; not an error): {0}" -f (Count-Re 'db\.query_failed' $none))
}

Write-Host ''
if ($bad -gt 0) { Write-Host "RESULT: $bad check(s) FOUND something or failed. Do not publish until each is understood." -ForegroundColor Red; exit 1 }
Write-Host 'RESULT: every "must be zero" count is zero and the positive control found the search text.' -ForegroundColor Green
exit 0
