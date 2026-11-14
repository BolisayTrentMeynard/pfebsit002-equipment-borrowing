<#
Week 16, chunk 5: single-user latency check (Windows PowerShell 5.1 or newer). No k6. About 1 to 2 minutes.
Used for the OPcache BEFORE and AFTER comparison (candidate P6) and as a quick re-check any time.

Run it in a standalone PowerShell window, repo root, with the load-test password set in THAT window:
  $sec = Read-Host 'Load-test password' -AsSecureString
  $env:LOAD_TEST_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
  powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\measure-latency.ps1 -Label before-opcache

What it does: ONE login as loadborrower01 (the login limit is 10 per minute, this uses 1), then -Repeats calls (default 30),
one at a time, to each of: /up, /api/health/ready, GET /api/equipment, GET /api/requests. It prints p50, p95 and max in
milliseconds (as curl measures them, like k6 does) and writes loadtest\results\latency-<Label>.csv (git-ignored).
Read-only except the login (one token row). Staging only: the script stops if the address is not https://localhost:9443.
The password and the token are never printed and never put on a command line: they go through short-lived temp files
that are deleted at the end. Do not run it while k6, Postman or another test is running.
#>
param(
    [Parameter(Mandatory = $true)][string]$Label,
    [int]$Repeats = 30,
    [string]$Base = 'https://localhost:9443'
)

$ErrorActionPreference = 'Continue'
if ($Base -ne 'https://localhost:9443') { Write-Host 'STOP: this script only runs against local staging (https://localhost:9443).' -ForegroundColor Red; exit 1 }
if (-not $env:LOAD_TEST_PASSWORD -or $env:LOAD_TEST_PASSWORD.Length -lt 12) { Write-Host 'STOP: set LOAD_TEST_PASSWORD (12+ characters) in this window first. See the comment at the top of this script.' -ForegroundColor Red; exit 1 }
if (-not (Get-Command curl.exe -ErrorAction SilentlyContinue)) { Write-Host 'STOP: curl.exe not found.' -ForegroundColor Red; exit 1 }

$tmp = Join-Path $env:TEMP ('lat-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tmp | Out-Null
$bodyFile = Join-Path $tmp 'login.json'
$hdrFile  = Join-Path $tmp 'auth.txt'

function Timed($url, $useAuth) {
    $args2 = @('--ssl-no-revoke', '-s', '-o', 'NUL', '-m', '60', '-w', '%{http_code} %{time_total}')
    if ($useAuth) { $args2 += @('-H', "@$hdrFile") }
    $args2 += @('-H', 'Accept: application/json', $url)
    $out = (& curl.exe @args2) -join ''
    $parts = $out.Trim().Split(' ')
    if ($parts.Count -ne 2) { return @{ code = '000'; ms = 0.0 } }
    return @{ code = $parts[0]; ms = ([double]::Parse($parts[1], [Globalization.CultureInfo]::InvariantCulture) * 1000.0) }
}

function Pct($sorted, $p) {
    if ($sorted.Count -eq 0) { return 0 }
    $i = [math]::Ceiling($p / 100.0 * $sorted.Count) - 1
    if ($i -lt 0) { $i = 0 }
    return $sorted[$i]
}

try {
    # --- one login (password goes through a temp file, never the command line) ---
    $json = '{"email":"loadborrower01@example.test","password":' + (ConvertTo-Json -InputObject $env:LOAD_TEST_PASSWORD) + '}'
    [IO.File]::WriteAllText($bodyFile, $json, (New-Object Text.UTF8Encoding($false)))
    $resp = (& curl.exe --ssl-no-revoke -s -m 60 -H 'Content-Type: application/json' -H 'Accept: application/json' --data-binary "@$bodyFile" "$Base/api/login") -join ''
    Remove-Item $bodyFile -Force -ErrorAction SilentlyContinue
    $token = $null
    try { $token = ($resp | ConvertFrom-Json).token } catch { }
    if (-not $token) { Write-Host 'STOP: login failed (no token). Is staging running, reset and seeded with LoadTestSeeder, and is the password the same one used for seeding?' -ForegroundColor Red; exit 1 }
    [IO.File]::WriteAllText($hdrFile, "Authorization: Bearer $token", (New-Object Text.ASCIIEncoding))
    Write-Host 'login ok (token kept in a temp file for this run only)'

    $targets = @(
        @{ name = 'up';       url = "$Base/up";                  auth = $false },
        @{ name = 'ready';    url = "$Base/api/health/ready";    auth = $false },
        @{ name = 'equipment'; url = "$Base/api/equipment";      auth = $true },
        @{ name = 'requests'; url = "$Base/api/requests";        auth = $true }
    )

    $outDir = Join-Path (Get-Location) 'loadtest\results'
    if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
    $csv = Join-Path $outDir "latency-$Label.csv"
    'endpoint,n,non200,p50_ms,p95_ms,max_ms' | Set-Content -Path $csv -Encoding ascii

    Write-Host ''
    Write-Host ("Label: {0}   repeats per endpoint: {1}   start: {2}" -f $Label, $Repeats, (Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))
    '{0,-10} {1,4} {2,7} {3,9} {4,9} {5,9}' -f 'endpoint', 'n', 'non200', 'p50 ms', 'p95 ms', 'max ms' | Write-Host

    foreach ($t in $targets) {
        $null = Timed $t.url $t.auth   # one unmeasured warm-up call per endpoint
        $times = New-Object System.Collections.ArrayList
        $bad = 0
        for ($i = 0; $i -lt $Repeats; $i++) {
            $r = Timed $t.url $t.auth
            if ($r.code -ne '200') { $bad++ }
            [void]$times.Add([double]$r.ms)
        }
        $sorted = @($times | Sort-Object)
        $p50 = Pct $sorted 50; $p95 = Pct $sorted 95; $mx = $sorted[$sorted.Count - 1]
        '{0,-10} {1,4} {2,7} {3,9:N0} {4,9:N0} {5,9:N0}' -f $t.name, $Repeats, $bad, $p50, $p95, $mx | Write-Host
        ('{0},{1},{2},{3:N1},{4:N1},{5:N1}' -f $t.name, $Repeats, $bad, $p50, $p95, $mx) | Add-Content -Path $csv -Encoding ascii
    }
    Write-Host ''
    Write-Host "Saved: $csv   (warm-up call per endpoint not counted)" -ForegroundColor Green
}
finally {
    Remove-Item $tmp -Recurse -Force -ErrorAction SilentlyContinue
}
