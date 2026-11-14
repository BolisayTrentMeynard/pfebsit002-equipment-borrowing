<#
Week 16, chunk 5: clean recovery measurement (Windows PowerShell 5.1 or newer). No k6, no load, no login.
It polls GET /api/health/ready about once per second and records every answer with a timestamp, so the time to
DETECT an outage and the time to RECOVER are measured, not guessed (the chunk 4 recovery number was not clean).

Run it in its OWN standalone PowerShell window, repo root, with staging running (API 8001, Caddy 9443):
  powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\recovery-poll.ps1 -Label recovery1

While it runs, use the XAMPP Control Panel and press a key IN THIS WINDOW at the same moment you click:
  S   = I just clicked STOP on the MySQL row
  G   = I just clicked START on the MySQL row
  Q   = quit and print the summary (Ctrl+C also works, but then there is no summary)
The script never touches MySQL itself. It also checks /up (liveness) every -UpEvery seconds (default 10; 0 = never).
Staging only. Output: loadtest\results\<Label>-poll.csv (git-ignored). It prints no key, token or password: it sends no
login and no Authorization header. The poll is one request per second on a single-threaded server, so while MySQL is
down each poll can take several seconds (the single-threaded server answers one call at a time): the CSV records how long.

Limits (state them next to the numbers): the poll itself is a load of about one request per second; times are as curl
measures them; the click times are the moments you pressed the key, not the moments XAMPP finished.
#>
param(
    [Parameter(Mandatory = $true)][string]$Label,
    [int]$UpEvery = 10,
    [int]$MaxMinutes = 20,
    [string]$Base = 'https://localhost:9443'
)

$ErrorActionPreference = 'Continue'
if ($Base -ne 'https://localhost:9443') { Write-Host 'STOP: this script only runs against local staging (https://localhost:9443).' -ForegroundColor Red; exit 1 }
if (-not (Get-Command curl.exe -ErrorAction SilentlyContinue)) { Write-Host 'STOP: curl.exe not found.' -ForegroundColor Red; exit 1 }

$outDir = Join-Path (Get-Location) 'loadtest\results'
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }
$csv = Join-Path $outDir "$Label-poll.csv"
'time_local,kind,http_code,seconds,note' | Set-Content -Path $csv -Encoding ascii

# Marker keys are read WHILE curl runs (a failing poll can take many seconds), so the time stamped is the key press.
$script:stopAt = $null; $script:goAt = $null; $script:quit = $false
function Read-Keys {
    try {
        while ([Console]::KeyAvailable) {
            $k = [Console]::ReadKey($true).Key.ToString().ToUpper()
            if ($k -eq 'S') { $script:stopAt = Stamp; Write-Host "  >>> STOP clicked at $($script:stopAt)" -ForegroundColor Magenta; Add-Content $csv "$($script:stopAt),marker,,,STOP clicked" -Encoding ascii }
            elseif ($k -eq 'G') { $script:goAt = Stamp; Write-Host "  >>> START clicked at $($script:goAt)" -ForegroundColor Magenta; Add-Content $csv "$($script:goAt),marker,,,START clicked" -Encoding ascii }
            elseif ($k -eq 'Q') { $script:quit = $true }
        }
    } catch { }   # no real console (for example an editor terminal): markers are not available
}
function Call($path) {
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = 'curl.exe'
    $psi.Arguments = '--ssl-no-revoke -s -o NUL -m 40 -w "%{http_code} %{time_total}" "' + $Base + $path + '"'
    $psi.UseShellExecute = $false
    $psi.RedirectStandardOutput = $true
    $psi.CreateNoWindow = $true
    $proc = [System.Diagnostics.Process]::Start($psi)
    while (-not $proc.HasExited) { Read-Keys; Start-Sleep -Milliseconds 50 }
    $out = $proc.StandardOutput.ReadToEnd()
    $p = $out.Trim().Split(' ')
    if ($p.Count -ne 2) { return @{ code = '000'; sec = 0.0 } }
    return @{ code = $p[0]; sec = [double]::Parse($p[1], [Globalization.CultureInfo]::InvariantCulture) }
}
function Stamp { (Get-Date).ToString('HH:mm:ss.fff') }

$firstFailAfterStop = $null; $firstOkAfterGo = $null
$last = $null; $n = 0; $fails = 0; $maxFailSec = 0.0
$clock = [Diagnostics.Stopwatch]::StartNew()
$nextUp = 0

Write-Host ("Polling {0}/api/health/ready about once per second for up to {1} min." -f $Base, $MaxMinutes)
Write-Host 'Keys: S = I clicked STOP,  G = I clicked START,  Q = quit and summarize.' -ForegroundColor Yellow

try {
    while ($clock.Elapsed.TotalMinutes -lt $MaxMinutes) {
        $loopStart = Get-Date
        Read-Keys
        if ($script:quit) { break }

        $r = Call '/api/health/ready'
        $n++
        $t = Stamp
        $line = '{0}  ready  {1}  {2,6:N2} s' -f $t, $r.code, $r.sec
        $color = 'Green'
        if ($r.code -ne '200') { $color = 'Red'; $fails++; if ($r.sec -gt $maxFailSec) { $maxFailSec = $r.sec } }
        if ($last -ne $null -and $last -ne $r.code) {
            $line += "   <<< CHANGE $last -> $($r.code)"
            if ($script:stopAt -and -not $script:goAt -and $r.code -ne '200' -and -not $firstFailAfterStop) { $firstFailAfterStop = $t }
            if ($script:goAt -and $r.code -eq '200' -and -not $firstOkAfterGo) { $firstOkAfterGo = $t }
        }
        # a first failure/success can also be the very first poll after a marker
        if ($script:stopAt -and -not $script:goAt -and $r.code -ne '200' -and -not $firstFailAfterStop) { $firstFailAfterStop = $t }
        if ($script:goAt -and $r.code -eq '200' -and -not $firstOkAfterGo) { $firstOkAfterGo = $t }
        $last = $r.code
        Write-Host $line -ForegroundColor $color
        Add-Content $csv ('{0},ready,{1},{2:N3},' -f $t, $r.code, $r.sec) -Encoding ascii

        if ($UpEvery -gt 0 -and $clock.Elapsed.TotalSeconds -ge $nextUp) {
            $u = Call '/up'
            $tu = Stamp
            Write-Host ('{0}  up     {1}  {2,6:N2} s' -f $tu, $u.code, $u.sec) -ForegroundColor DarkGray
            Add-Content $csv ('{0},up,{1},{2:N3},' -f $tu, $u.code, $u.sec) -Encoding ascii
            $nextUp = $clock.Elapsed.TotalSeconds + $UpEvery
        }

        while (((Get-Date) - $loopStart).TotalMilliseconds -lt 1000) { Read-Keys; Start-Sleep -Milliseconds 50 }
    }
}
finally {
    Write-Host ''
    Write-Host '=== Summary (copy or screenshot this) ===' -ForegroundColor Cyan
    Write-Host ("polls: {0}   not-200 answers: {1}   slowest not-200 answer: {2:N2} s" -f $n, $fails, $maxFailSec)
    Write-Host ("STOP clicked (your key):  {0}" -f $(if ($script:stopAt) { $script:stopAt } else { 'not marked' }))
    Write-Host ("first not-200 after STOP: {0}" -f $(if ($firstFailAfterStop) { $firstFailAfterStop } else { 'none seen' }))
    Write-Host ("START clicked (your key): {0}" -f $(if ($script:goAt) { $script:goAt } else { 'not marked' }))
    Write-Host ("first 200 after START:    {0}" -f $(if ($firstOkAfterGo) { $firstOkAfterGo } else { 'none seen' }))
    Write-Host "CSV: $csv"
}
