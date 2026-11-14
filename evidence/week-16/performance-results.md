# Week 16 Performance Results (template, filled in after each counted run)

Status: **Baseline filled in (2026-11-14); ordinary, peak and spike still planned.** Claude fills this sheet from the developer's screenshots and the k6 summary files; it never invents a number. Every run starts from a fresh reset (`deploy\staging-release.ps1 -SkipBuild`, then `LoadTestSeeder`). Runs that fail for a setup reason are listed and not counted.

Environment (fill in once): one Windows PC, XAMPP MySQL (MariaDB), `php artisan serve` (single-threaded) on 8001 behind Caddy on 9443, k6 v2.4.0 on the same PC, `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info`. CPU model and number of logical cores: _developer: from the sampler's first line_.

Limits (they stay next to every number): assumed traffic, not real users; the load generator and the system share one PC; one IP address for all fake users; a single-threaded server; times include k6's own CPU use.

## Run log
| Run | Date and start time | Counted? | Reason if not | Files kept (outside the repo) |
|-----|---------------------|----------|---------------|-------------------------------|
| Smoke | 2026-11-14 18:08 +0800 (10:08:25 UTC) | No (plumbing check) | | |
| Baseline, attempt 1 | 2026-11-14, about 18:1x +0800 | No | Stopped by the developer after about 1 minute: `maxDuration` of the endpoints scenario (3m30s) was too short for the 0.9 s per request seen. Claude's sizing mistake, from the smoke test. Durations changed to 5m and 5m15s | none |
| Baseline, attempt 2 | ended 2026-11-14 18:26 +0800 (10:26:23 UTC), 9 min 53 s | **Yes** | | summary printed in the terminal (per developer's screenshot); sampler CSV `baseline-resources.csv` on the developer's PC |
| Ordinary | | | | |
| Peak | | | | |
| Spike | | | | |

## Baseline (1 user, 30 repeats)
| Endpoint | p50 ms | p95 ms | p99 ms | max ms | Predicted p95 | Result vs prediction |
|----------|--------|--------|--------|--------|---------------|----------------------|
| up (liveness) | 629.6 | 886.2 | 911.6 | 914.7 | 20 to 120 | 886 ms is about 7 times the top of the range: MISSED |
| ready | 775.6 | 1,045.8 | 1,100.8 | 1,109.9 | 30 to 150 | about 7 times the top: MISSED |
| login (30 paced) | 1,624 | 1,965.2 | 2,233.5 | 2,324.3 | 250 to 800 | about 2.5 times the top: MISSED |
| equipment | 866.8 | 1,195.7 | 1,198.9 | 1,200.1 | 60 to 250 | about 4.8 times the top: MISSED |
| requests_list | 869.4 | 1,261.9 | 1,322.6 | 1,323.9 | 60 to 250 | about 5 times the top: MISSED |
| create | 948.5 | 1,192.5 | 1,212.4 | 1,215.3 | 100 to 400 | about 3 times the top: MISSED |
| request_detail | 798 | 1,061.1 | 1,181.1 | 1,216.7 | 60 to 250 | about 4.2 times the top: MISSED |
| custody | 854.6 | 1,147.1 | 1,185.5 | 1,194.2 | 60 to 250 | about 4.6 times the top: MISSED |
| manual | 826.9 | 1,032.6 | 1,075.5 | 1,090.8 | 60 to 250 | about 4.1 times the top: MISSED |
| requests_pending | 890.6 | 1,239 | 1,257.5 | 1,263.6 | 60 to 300 | about 4.1 times the top: MISSED |

Counted baseline run (per the developer's screenshot of the k6 table): 300 requests in the measured phase (30 per endpoint), 305 in the whole run, 0 errors, no 5xx, no 429, no business rejections, 30 answers of 201 to `POST /requests`, `Thresholds: all passed` (only `err_all` under 1%), data sent 27.8 KB and received 716.2 KB. Every p95 is above the top of the range predicted in the chunk 3 section; the pattern is a steady cost of about 600 to 650 ms on every request (see Diagnostics).

### Integrity after the baseline (per the developer's screenshot, read-only SQL)
Database `equipment_staging`. Checks 1, 2, 2b and 5 printed nothing (no rows). `borrow_requests_total` 30 (equal to the 30 answers of 201), `audit_logs_total` 30 (30 `submit` rows), all 30 requests PENDING, 56 items `available` (50 LT items and the 6 seed items). As predicted.

### Diagnostics after the baseline (read-only, per the developer's screenshot)
- Laravel's own `http.request` lines for `GET api/equipment` (the last 8, 10:19:37 to 10:20:27 UTC): 269.4, 288.4, 362.2, 133.0, 279.0, 170.7, 252.3 and 260.7 ms, median about 265 ms. k6 measured a median of 866.8 ms for the same endpoint. About 600 ms per request is therefore outside the handler's own timing. `/up` (no database) also took about 630 ms in k6.
- `php -m | findstr /i opcache` printed nothing, so OPcache is probably not loaded (not yet proven by a PHP setting check). Nothing was changed: this is candidate P6 for chunk 5.

## Ordinary, peak, spike
| Item | Ordinary (5 users) | Peak (25 users) | Spike (0 to 40) |
|------|--------------------|-----------------|-----------------|
| Measured requests (steady phase) and per second | | | |
| p95 reads / writes / all, ms | | | |
| Thresholds (D5) | reads under 800, writes under 1500, errors under 1% | all under 2000, errors under 2% | errors under 5% (prediction, not a D5 value) |
| Threshold result | | | |
| Errors (5xx, 429, other) | | | |
| Business rejections (409, 422) | | | |
| php / mysqld / caddy / k6 CPU (% of one core, typical and highest) | | | |
| Memory (MB, start and end) | | | |
| `laravel.log` growth | | | |
| Integrity checks 1, 2, 2b, 5 (rows) | | | |
| `created_201` vs rows added; `audit_logs_total` vs `writes_ok` | | | |
| Prediction matched? | | | |

## Spike probe (recovery)
Probe lines (`probe,t=..s,status=..,ms=..`) from `spike-console.txt`: _to be summarised by Claude._

## Bottleneck candidates (confirmed or disproved by these runs)
| ID | Candidate | Evidence | Verdict |
|----|-----------|----------|---------|
| P1 | Unbounded lists | | |
| P2 | `last_used_at` update on each call | | |
| P3 | Database cache and queue, one log file | | |
| P4 | Single-threaded server | | |
| P5 | Flutter release size, no compression | | |
| P6 | A fixed cost of about 600 ms on every request (OPcache probably not loaded; not proven) | Baseline: `/up` about 630 ms in k6; handler time about 265 ms in the Laravel log; `php -m` shows no OPcache | Suspected, to be tested in chunk 5 with a before and after run |
