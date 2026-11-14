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

## Notes
- k6 exit code 99 means a threshold was missed. That is a result, not a script error. Record it; do not rerun to "fix" it.
- A run that failed because of setup (wrong password, no reset, API window closed) is written down and not counted; reset and run again.
- A login 429 in `setup()` means the login limit was hit by something else in the same minute (for example Postman). Wait a minute and rerun.
- Never use `--http-debug` or `--out` options that print requests: they can echo the Authorization header.
