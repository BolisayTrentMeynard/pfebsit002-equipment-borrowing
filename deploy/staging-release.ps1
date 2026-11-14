<#
Week 16 local staging release (Windows PowerShell 5.1 or newer). Repeatable: run it again for a clean reset.

Run from a STANDALONE PowerShell window (not the VS Code tab: it does not see `caddy`), in the repo root:
  powershell -NoProfile -ExecutionPolicy Bypass -File deploy\staging-release.ps1

Options:
  -SkipBuild        reuse app\build\web (no Flutter build); still resets the database
  -KeepData         do NOT reset the database (no migrate:fresh)
  -LocalResources   build Flutter with --no-web-resources-cdn (CanvasKit files served by Caddy, not by Google's CDN)

What it does: checks tools and MySQL, checks backend\.env.staging, makes Laravel PROVE which database it will
use BEFORE anything is reset, creates and resets equipment_staging, builds the Flutter release build, starts the
API (port 8001) and Caddy (port 9443) in their own windows, then runs the health checks.

Safety: APP_ENV=staging is set only inside this script's process and the two windows it opens. Your own shell
is not changed. It never prints a key or a password (only whether a value is set and how long it is).
The development database `equipment_borrowing` is never touched: the script stops if Laravel does not report
`equipment_staging`.
#>
param(
    [switch]$SkipBuild,
    [switch]$KeepData,
    [switch]$LocalResources
)

# Continue, not Stop: Windows PowerShell 5.1 turns native-command stderr text into errors under Stop.
# Every native command below is checked through $LASTEXITCODE instead.
$ErrorActionPreference = 'Continue'

$StagingDb = 'equipment_staging'
$ApiPort   = 8001
$WebPort   = 9443
$Base      = "https://localhost:$WebPort"
$Root      = Split-Path -Parent $PSScriptRoot
$Backend   = Join-Path $Root 'backend'
$App       = Join-Path $Root 'app'

function Step($n, $text) { Write-Host ''; Write-Host "== Step ${n}: $text" -ForegroundColor Cyan }
function Fail($text)     { Write-Host ''; Write-Host "STOP: $text" -ForegroundColor Red; exit 1 }
function Ok($text)       { Write-Host "  ok    $text" -ForegroundColor Green }
function Warn($text)     { Write-Host "  note  $text" -ForegroundColor Yellow }

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

function Get-Code($url) {
    $code = & curl.exe --ssl-no-revoke -s -o NUL -m 10 -w '%{http_code}' $url
    return "$code".Trim()
}

$clock = [Diagnostics.Stopwatch]::StartNew()

# ---------------------------------------------------------------------------------------------
Step 1 'Tools and MySQL'
foreach ($tool in 'php', 'flutter', 'caddy', 'curl.exe') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Fail "$tool is not found on PATH. Run this script from a standalone PowerShell window (VS Code's tab may not see caddy)."
    }
    Ok "$tool found"
}
if (-not (Test-Port 3306)) { Fail 'MySQL does not answer on 127.0.0.1:3306. Start MySQL in the XAMPP Control Panel.' }
Ok 'MySQL answers on 127.0.0.1:3306'

# ---------------------------------------------------------------------------------------------
Step 2 'backend\.env.staging (values are never printed)'
$envFile = Join-Path $Backend '.env.staging'
if (-not (Test-Path $envFile)) {
    Fail 'backend\.env.staging is missing. Copy backend\.env.staging.example to backend\.env.staging and fill in APP_KEY and NOTIFIER_SIGNING_KEY (see evidence\week-16\deployment-checklist.md). Nothing was changed.'
}
$vals = @{}
foreach ($line in Get-Content $envFile) {
    if ($line -match '^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$') {
        $k = $Matches[1]
        $v = $Matches[2].Trim().Trim('"').Trim("'")
        if (-not $vals.ContainsKey($k)) { $vals[$k] = $v }   # a repeated key: the FIRST line wins, as in Laravel
    }
}
function Need($key, $expected) {
    if ($vals[$key] -ne $expected) { Fail "In .env.staging $key must be '$expected' (found a different value)." }
    Ok "$key = $expected"
}
Need 'APP_ENV' 'staging'
Need 'APP_DEBUG' 'false'
Need 'DB_DATABASE' $StagingDb
if (($vals['APP_KEY'] + '').Length -lt 20) { Fail 'APP_KEY is empty. Run `php artisan key:generate --show` in backend\ and paste the result into .env.staging (do not show it on screen).' }
Ok ('APP_KEY is set (length ' + $vals['APP_KEY'].Length + ')')
if (($vals['NOTIFIER_SIGNING_KEY'] + '').Length -lt 32) { Fail 'NOTIFIER_SIGNING_KEY is missing or shorter than 32 characters. Run `php artisan notifier:generate-key` and paste a NEW key into .env.staging (never reuse the dev key).' }
Ok ('NOTIFIER_SIGNING_KEY is set (length ' + $vals['NOTIFIER_SIGNING_KEY'].Length + ')')
if (($vals['NOTIFIER_URL'] + '') -notmatch ":$WebPort/") { Warn "NOTIFIER_URL does not use port $WebPort. Only matters when a queue worker runs on staging." }
if (($vals['NOTIFIER_CA_BUNDLE'] + '') -eq '') { Warn 'NOTIFIER_CA_BUNDLE is empty. Only matters when a queue worker runs on staging.' }

# ---------------------------------------------------------------------------------------------
Step 3 'Ask Laravel itself which settings it will use (guard: must say staging and equipment_staging)'
$env:APP_ENV = 'staging'          # selects backend\.env.staging; inherited by the two windows started below
Set-Location $Backend
$effEnv   = (& php artisan config:show app.env 2>&1 | Out-String)
$effDebug = (& php artisan config:show app.debug 2>&1 | Out-String)
$effDb    = (& php artisan config:show database.connections.mysql.database 2>&1 | Out-String)
if ($effEnv -notmatch '\bstaging\b')                 { Fail "Laravel does not report app.env = staging. Nothing was reset. Output was: $effEnv" }
if ($effDebug -notmatch '\bfalse\b')                 { Fail "Laravel does not report app.debug = false. Nothing was reset. Output was: $effDebug" }
if ($effDb -notmatch "\b$StagingDb\b" -or $effDb -match 'equipment_borrowing') {
    Fail "Laravel does not report database = $StagingDb. Nothing was reset. Output was: $effDb"
}
Ok 'Laravel reports app.env = staging, app.debug = false, database = equipment_staging'

# ---------------------------------------------------------------------------------------------
Step 4 "Create $StagingDb if it does not exist, then reset it"
$mysql = $null
$cmd = Get-Command mysql.exe -ErrorAction SilentlyContinue
if ($cmd) { $mysql = $cmd.Source } elseif (Test-Path 'C:\xampp\mysql\bin\mysql.exe') { $mysql = 'C:\xampp\mysql\bin\mysql.exe' }
if ($mysql) {
    & $mysql -u root -e "CREATE DATABASE IF NOT EXISTS equipment_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { Ok 'database equipment_staging exists' }
    else { Warn 'Could not create the database with mysql.exe (root may have a password). Create equipment_staging in phpMyAdmin if the next step fails.' }
} else {
    Warn 'mysql.exe not found. Create equipment_staging in phpMyAdmin if the next step fails.'
}
if ($KeepData) {
    Warn '-KeepData: database NOT reset.'
} else {
    Write-Host "  resetting database: $StagingDb (confirmed by Laravel in step 3)"
    & php artisan migrate:fresh --seed
    if ($LASTEXITCODE -ne 0) { Fail 'migrate:fresh --seed failed. Read the error above.' }
    Ok 'migrate:fresh --seed finished (4 fake users, 6 fake items)'
}

# ---------------------------------------------------------------------------------------------
Step 5 'Flutter release build (same origin: the app calls https://localhost:9443/api)'
if ($SkipBuild) {
    if (-not (Test-Path (Join-Path $App 'build\web\index.html'))) { Fail '-SkipBuild was given but app\build\web does not exist. Run without -SkipBuild once.' }
    Warn '-SkipBuild: reusing the existing build.'
} else {
    Set-Location $App
    $buildArgs = @('build', 'web', '--release', "--dart-define=API_BASE_URL=$Base/api")
    if ($LocalResources) { $buildArgs += '--no-web-resources-cdn' }
    $sw = [Diagnostics.Stopwatch]::StartNew()
    & flutter @buildArgs
    if ($LASTEXITCODE -ne 0) { Fail 'flutter build web failed. Read the error above.' }
    $sw.Stop()
    Ok ('build finished in {0:N0} s' -f $sw.Elapsed.TotalSeconds)
}
$web = Join-Path $App 'build\web'
$total = (Get-ChildItem $web -Recurse -File | Measure-Object Length -Sum).Sum
Write-Host ('  build folder size: {0:N1} MB' -f ($total / 1MB))
$js = Join-Path $web 'main.dart.js'
if (Test-Path $js) { Write-Host ('  main.dart.js size: {0:N0} KB' -f ((Get-Item $js).Length / 1KB)) } else { Warn 'main.dart.js not found in the build folder.' }

# ---------------------------------------------------------------------------------------------
Step 6 'Start the API (8001) and Caddy (9443), each in its own window'
if (Test-Port $ApiPort) {
    Warn "Port $ApiPort already answers: leaving the running API as it is. If you changed .env.staging, close its window and run this script again."
} else {
    $apiCmd = "`$Host.UI.RawUI.WindowTitle = 'STAGING API $ApiPort'; php artisan serve --host=127.0.0.1 --port=$ApiPort"
    Start-Process powershell -WorkingDirectory $Backend -ArgumentList @('-NoExit', '-Command', $apiCmd)
    Ok "API window started on port $ApiPort"
}
if (Test-Port $WebPort) {
    Warn "Port $WebPort already answers: leaving the running Caddy as it is (it serves the files from app\build\web, so a new build is picked up)."
} else {
    $caddyCmd = "`$Host.UI.RawUI.WindowTitle = 'STAGING CADDY $WebPort'; caddy run --config deploy\Caddyfile.staging --adapter caddyfile"
    Start-Process powershell -WorkingDirectory $Root -ArgumentList @('-NoExit', '-Command', $caddyCmd)
    Ok "Caddy window started on port $WebPort"
}

# ---------------------------------------------------------------------------------------------
Step 7 "Wait for $Base/up, then run the health checks"
$up = $false
for ($i = 1; $i -le 40; $i++) {
    if ((Get-Code "$Base/up") -eq '200') { $up = $true; break }
    Start-Sleep -Seconds 1
}
if (-not $up) { Fail "$Base/up did not answer 200 within 40 s. Look at the two STAGING windows for errors." }

$failed = 0
function Check($label, $url, $want) {
    $got = Get-Code $url
    if ($got -eq $want) { Ok "$label  ->  $got" }
    else { Write-Host "  FAIL  $label  ->  $got (wanted $want)" -ForegroundColor Red; $script:failed++ }
}
Check 'GET /up (liveness, no database)'                   "$Base/up"                 '200'
Check 'GET /api/health/ready (readiness)'                 "$Base/api/health/ready"   '200'
Check 'GET /api/nope (must reach Laravel, JSON 404)'      "$Base/api/nope"           '404'
Check 'GET / (Flutter index.html)'                        "$Base/"                   '200'
Check 'GET /main.dart.js (Flutter build file)'            "$Base/main.dart.js"       '200'
Check 'GET /some/page (SPA fallback to index.html)'       "$Base/some/page"          '200'
$ready = (& curl.exe --ssl-no-revoke -s -m 10 "$Base/api/health/ready") -join ''
Write-Host "  readiness body: $ready"
if ($ready -ne '{"status":"ok"}') { Write-Host '  FAIL  readiness body is not {"status":"ok"}' -ForegroundColor Red; $failed++ }

Step 8 'Operator summary (php artisan ops:status, staging settings)'
Set-Location $Backend
& php artisan ops:status
$opsExit = $LASTEXITCODE
Write-Host "  ops:status exit code: $opsExit"
if ($opsExit -ne 0) { $failed++ }

$clock.Stop()
Write-Host ''
if ($failed -gt 0) { Write-Host "RESULT: $failed check(s) failed. Staging is NOT ready." -ForegroundColor Red; exit 1 }
Write-Host ('RESULT: staging is up in {0:N0} s. Open {1}/ in Chrome.' -f $clock.Elapsed.TotalSeconds, $Base) -ForegroundColor Green
Write-Host 'Next: run `php artisan test` in a NEW window (APP_ENV must NOT be set there), then the Postman run on staging.'
exit 0
