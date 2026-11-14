# Load tests (Week 16, chunk 3)

k6 scripts and helpers for the Week 16 runs. Fake accounts only. These run against **local staging only** (`https://localhost:9443`); the scripts stop if the address is anything else. Raw output goes to `loadtest\results\` (git-ignored). Nothing here holds a password or token.

| File | What it is |
|------|-----------|
| `k6\smoke.js` | 15 s plumbing check, read-only, not a measurement |
| `k6\baseline.js` | 1 user, 30 repeats per endpoint, then 30 paced logins (about 8 min in total) |
| `k6\ordinary.js` | 5 users, think time 5 to 15 s, 1 min warm-up + 5 min measured |
| `k6\peak.js` | 25 users, think time 2 to 5 s, 1 min warm-up + 5 min measured |
| `k6\spike.js` | 0 to 40 users in 30 s, 1 min hold, 30 s down, observation to 4 min |
| `k6\common.js` | shared code: logins in `setup()`, the six journeys, metrics, summary |
| `sample-resources.ps1` | CPU and memory of php, mysqld, caddy and k6 every 5 s into a CSV |
| `integrity-check.sql` | read-only data checks after a counted run |
| `k6\failure.js` | chunk 4: the ordinary workload while MySQL is stopped and started by hand (cues, per-phase tables) |
| `scan-outage-log.ps1` | chunk 4: read-only scan of the new part of `laravel.log` after the outage run (counts, masked samples) |
| `integrity-check-extra.sql` | chunk 4: three extra read-only checks for a partial write |

## Before every counted run (the order matters)

Use **standalone PowerShell windows** (not the VS Code tab), repo root `C:\dev\pfebsit002-equipment-borrowing`. MySQL must be running in XAMPP. Close the dev API (port 8000), Postman and the Flutter dev server, so the numbers are not disturbed.

**1. Reset staging to the same data** (also empties the login-limit counters):
```
powershell -NoProfile -ExecutionPolicy Bypass -File deploy\staging-release.ps1 -SkipBuild
```
It leaves the two STAGING windows (API 8001, Caddy 9443) running. Use the same two windows for all runs.

**2. Seed the load-test accounts and items** (fake data; password typed hidden, kept only in this window's environment):
```
$sec = Read-Host 'Load-test password (12+ characters)' -AsSecureString
$env:LOAD_TEST_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
cd backend
$env:APP_ENV = 'staging'
php artisan config:show database.connections.mysql.database
```
The last line MUST print `equipment_staging`. If it prints anything else, stop and do not seed. Then:
```
php artisan db:seed --class=LoadTestSeeder
Remove-Item Env:APP_ENV
cd ..
```
(`Remove-Item Env:APP_ENV` matters: with it left set, `php artisan test` would not use the test database.) Keep this window for k6: `LOAD_TEST_PASSWORD` stays set in it. Never type the password in a command line and never screenshot it.

## Smoke test (once, before the first real run; no reset needed afterwards)
```
k6 run loadtest\k6\smoke.js
```
Expected: about 15 to 25 s, a small table, `Thresholds: all passed`, error rate 0 %. If it says `x509: certificate signed by unknown authority`, tell Claude. Do NOT use `--insecure-skip-tls-verify`. The fallback is `k6 run -e BASE_URL=http://127.0.0.1:8001 loadtest\k6\smoke.js` (loopback HTTP, skips Caddy; the evidence will say so).

## A counted run

**a. Commit the predictions alone first** (once, before the first run of chunk 3): the file `evidence\week-16\week16-failure-predictions.txt` with the chunk 3 addendum, as the only file in that commit. Note the commit time (`git log -1 --format=%ci`).

**b. Reset and seed** (steps 1 and 2 above).

**c. Start the sampler** in a second window and leave it:
```
powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\sample-resources.ps1 -Label ordinary
```

**d. Run k6** in the window that has the password set (use the script of the run: baseline, ordinary, peak):
```
k6 run loadtest\k6\ordinary.js
```
For the spike also send the probe lines to a file:
```
k6 run --console-output loadtest\results\spike-console.txt loadtest\k6\spike.js
```
Wait for the end-of-run table. The first 4 minutes are the logins in `setup()` (the progress line shows `setup`); nothing is wrong. Do not touch the app or the PC during the run.

**e. Stop the sampler** with Ctrl+C right after k6 ends. It prints the `laravel.log` growth.

**f. Integrity check** (read-only, staging database only):
```
& 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t -e "source loadtest/integrity-check.sql"
```
A check that finds nothing prints nothing (no table at all); only the database name, the two totals, the action counts and the status counts print. A table under checks 1, 2, 2b or 5 is a problem: send it. If root has a password, paste the file into phpMyAdmin with `equipment_staging` selected.

**g. Results.** `loadtest\results\<run>-summary.md` is the table to send. Keep `-summary.json`, the CSV and the console file on your PC.

## What to send to Claude after each run
The `<run>-summary.md` text (or a screenshot of the end-of-run table), the sampler's last lines (log growth) or the CSV, and the integrity output. Save raw screenshots unsorted in `Downloads\FLUTTER\Week 16`.

## Chunk 4: failure test, MySQL stopped during the ordinary workload (AC-48)

No PHP or Dart file changed in this chunk, so `php artisan test` and `flutter test` do not need to run again. `k6\common.js` got one additive hook (`hooks.onResponse`, unset in every earlier run). The run takes about 12 to 13 minutes: about 4 min 30 s of logins in `setup()`, then 7 min 30 s of load. You click Stop and Start on MySQL yourself, when the terminal tells you to.

**Before the run (once): commit the predictions alone.** After extracting the zip, commit ONLY `evidence\week-16\week16-failure-predictions.txt` (the chunk 4 addendum is at the bottom), then run `git log -1 --format=%ci` and keep the line:
```
git add evidence/week-16/week16-failure-predictions.txt
git commit -m "Week 16: add chunk 4 predictions addendum (before the MySQL-stopped run)"
git log -1 --format=%ci
```
Do not run anything of chunk 4 before this commit. The other files stay uncommitted for now.

**Windows you need:** (1) the two STAGING windows from the release script (API 8001 and Caddy 9443), (2) the k6 window with `LOAD_TEST_PASSWORD` set, (3) the sampler window, (4) a window for the manual checks (repo root), (5) the XAMPP Control Panel with the MySQL row visible. Close the dev API (port 8000), Postman and the Flutter dev server.

**1. Reset and seed** (exactly as in "Before every counted run", steps 1 and 2 above). MySQL must be running.

**2. Note the log position** (the number you give to the scan later):
```
(Get-Item backend\storage\logs\laravel.log).Length
```

**3. Optional, for the app screenshots (do it NOW, before k6 starts, because k6's logins start right away):** open `https://localhost:9443`, sign in as `loadborrower01@example.test` with the load-test password (type it; never screenshot it) and leave the tab open on "My requests".

**4. Start the sampler** (window 3), then **k6** (window 2). Do NOT add `--console-output`: the cues must appear in the terminal.
```
powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\sample-resources.ps1 -Label outage
k6 run loadtest\k6\failure.js
```
The first 4 min 30 s are the logins (nothing is wrong). Then you will see lines starting with `CUE` (what to do and when) and `MONITOR` (readiness or `/up` changed).

**5. At the line `STOP MYSQL NOW`:** click Stop on the MySQL row in the XAMPP Control Panel and write down the time. (If MySQL runs as a Windows service on your PC, stop that service instead and tell Claude.)

**6. During the outage (between the cue `Outage window` and the cue `IN 15 s: START MySQL`), in window 4, run M1 to M6 one after the other** and screenshot each output (they can be slow while the server is busy: wait for each one):
```
curl.exe --ssl-no-revoke -s -i -m 60 -w '\ntime_total=%{time_total}s\n' https://localhost:9443/up
curl.exe --ssl-no-revoke -s -i -m 60 -w '\ntime_total=%{time_total}s\n' https://localhost:9443/api/health/ready
curl.exe --ssl-no-revoke -s -i -m 60 -w '\ntime_total=%{time_total}s\n' -H 'Accept: application/json' https://localhost:9443/api/me
curl.exe --ssl-no-revoke -s -i -m 60 -w '\ntime_total=%{time_total}s\n' -H 'Accept: application/json' -H 'Authorization: Bearer outage-demo-not-a-real-token' https://localhost:9443/api/me
cd backend; $env:APP_ENV = 'staging'; php artisan ops:status; "exit code: $LASTEXITCODE"; Remove-Item Env:APP_ENV; cd ..
Get-Process mysqld
```
(M1 `/up`, M2 readiness, M3 no token, M4 made-up token, M5 `ops:status`, M6 `Get-Process mysqld`, which should print an error because the process is gone.) Include the clock in the screenshots. In the app tab (step 3), press Refresh or switch tab once during the outage and screenshot it.

**7. At the line `START MYSQL NOW`:** click Start on the MySQL row, write down the time, wait until the row shows MySQL running. Do not restart php or Caddy. In the app tab press "Try again" once and screenshot the result.

**8. Wait for the end-of-run k6 table** (the per-phase tables are at the bottom; `loadtest\results\failure-summary.md` has the same text). Then stop the sampler with Ctrl+C right away.

**9. Checks after the run** (staging database only, before any reset):
```
& 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t -e "source loadtest/integrity-check.sql"
& 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t -e "source loadtest/integrity-check-extra.sql"
powershell -NoProfile -ExecutionPolicy Bypass -File loadtest\scan-outage-log.ps1 -StartBytes <the number from step 2>
```
Checks 1, 2, 2b, 5 (first file) and 6, 7, 8 (second file) print nothing when all is well; a table under one of them is a finding: send it. The scan prints counts only and masks every 64-character hex string; if its last section says some connection frames do not show `SensitiveParameterValue`, look at those lines yourself with the screen not shared and tell Claude only whether a password is visible.

**What to keep (outside the repo, unsorted in `Downloads\FLUTTER\Week 16`):** the predictions commit line with its time, the k6 terminal with the CUE and MONITOR lines and the end tables, the six manual checks with the clock, XAMPP Control Panel showing MySQL stopped and started, the integrity outputs, the scan output, the app screens (optional), the sampler CSV `outage-resources.csv` (it stays on your PC, git-ignored), and your written click times. Keep the Authorization header, `.env`, `.env.staging` and the load-test password out of every screenshot. The made-up token in M4 is not a real token.

**If something goes wrong:** a run that was disturbed (you stopped MySQL at the wrong time, closed a window, Postman was open) is written down and NOT counted: reset, seed and run again, and say so. k6 exit code 99 means a threshold missed (a result, not a script error): in this run it would mean a 5xx before the stop or after the guard window. Never use `--http-debug`.

## Notes
- k6 exit code 99 means a threshold was missed. That is a result, not a script error. Record it; do not rerun to "fix" it.
- A run that failed because of setup (wrong password, no reset, API window closed) is written down and not counted; reset and run again.
- A login 429 in `setup()` means the login limit was hit by something else in the same minute (for example Postman). Wait a minute and rerun.
- Never use `--http-debug` or `--out` options that print requests: they can echo the Authorization header.
