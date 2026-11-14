# Interpretation and Maintainability Note (Week 16)

Project: #5 Equipment Borrowing Reminder System. Date: 2026-11-15. Written by Claude from the code, the developer's results and screenshots; Claude ran no code. All numbers are for one Windows PC with the load tool on it.

## What the results show

- **The system works at the ordinary workload on this PC, once OPcache is on.** 5 users for 10 minutes: 340 requests, 0 errors, reads p95 388.6 ms, writes p95 267.6 ms, data consistent. Before the change the same run failed both latency targets (about 2 s).
- **One configuration setting explained most of the per-request cost.** Single-user times fell from about 450 to 740 ms to about 50 to 180 ms. This is consistent with OPcache being off, but it is two single-user runs plus two workload runs, not a controlled experiment. The change was made outside the repo.
- **The capacity limit is a single-threaded server.** At peak the php process sat at about 92% of one core (before the change), mysqld and caddy stayed near idle, and every endpoint waited the same time, `/up` included. After the change the peak threshold was still missed by 47.5 ms. This was not fixed because `php artisan serve` cannot use several workers.
- **A MySQL outage is survivable and visible, but not alarming.** Readiness answered 503, the API returned generic 500s with no internals, no partial write was found, and recovery took 3.96 s after the start key with no restart of php or Caddy. A person had to be looking: detection was 13 s with no load and 99 s under load.
- **The log leaked a token hash on the error path, and a fix removed it.** The normal request line was clean. The outage exposed 22 and 4 exception lines holding a token HASH in the SQL text. The chunk 5 change logs error codes and the place in the code instead, and the new region of the log shows none.
- **Predictions were often wrong and that is useful.** All baseline latency predictions missed; detection time, 5xx latency and peak throughput missed. They are kept in `week16-failure-predictions.txt`. Claude's own mistakes (baseline sizing, a PowerShell redirect, a test string, a missing seeding instruction, a wrong first guess about a stalled run) are listed in the lab log.

## What the results do not show

- **They do not show real-server behavior.** No multi-worker server, no real network, no public certificate, and the load generator shares the CPU.
- **They do not show the system at volume or over time.** The 5,000-request volume run, the 20 minute run and the queue-worker-stopped test were cut. P1 (unbounded lists) and P2 (`last_used_at` per call) were never shown to matter, and never tested at volume.
- **They do not show a crash.** The outage was a normal Stop. In chunk 4b MySQL crashed twice after the Start click (cause not established), so a crash-recovery measurement does not exist.
- **The peak result after the change is one run.** The spike was not repeated, the php CPU of the after runs was not read, and the peak-before integrity output was never captured.
- **The log fix is proven by PHPUnit, not by a live outage**, and the recovery poll sent no authenticated call.
- **The rollback was checked by six requests, not by the Week 15 collection**, and the restore was shown on an almost empty database on the same server.
- **No alert exists.** Detection is a person reading a terminal.

## Maintenance notes

- **Check OPcache before measuring or demonstrating:** `php -m | findstr /i opcache`. The setting is in `C:\xampp\php\php.ini` (backup `php.ini.bak-before-opcache`). `enable_cli` is on, so `artisan` and PHPUnit use it too.
- **Set `LOAD_TEST_PASSWORD` in the window you run k6, the seeder or the rollback script from.** Two false starts and one skipped check came from a window without it. The password never goes into a file or a screenshot.
- **Reset, then seed, then run.** `staging-release.ps1` resets `equipment_staging` and removes the load-test accounts; run `LoadTestSeeder` before k6 or the rollback login check.
- **Write the predictions file and commit it alone before any counted run**, then add actual results underneath.
- **The scan script needs the log size.** Note the byte count of `laravel.log` before a series of runs and pass it as `-StartBytes`; old regions of the log still hold the 26 token-hash lines from chunks 4 and 4b and the seeder's guard text.
- **Keep the dump, `.env`, `.env.staging` and the load-test password out of every frame and every zip.** The dump is in `C:\dev\pfebsit002-backups\` and holds fake password and token hashes. `make-clean-zip.bat` skips `.env*`, SQLite files and the logs folder.
- **`php artisan serve` is one process.** Anything slow blocks everything behind it. Do not read a long k6 queue as a bug in a single endpoint.
- **A failing database call takes about 8 to 9 s here.** A raw refused connection took about 2 s and `ops:status` against a closed port took 9.12 s; the repeats were not isolated. `DB_CONNECT_TIMEOUT` exists but does not shorten a refused connection and was not tested against a database that does not answer.
- **When the API changes, keep the Postman collection, the k6 scripts and the traceability matrix in step.**
- **Flutter stays pinned at 3.47.8** (newer is available; not upgraded on purpose). The release build is served by Caddy on the same origin; its Roboto font comes from Google's CDN.
