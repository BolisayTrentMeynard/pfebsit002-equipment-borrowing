# Week 16 Performance Results (template, filled in after each counted run)

Status: **Baseline, ordinary, peak and spike filled in (2026-11-14).** Optional runs (volume, 20-minute, queue-worker-stopped) not done; the developer decides whether to cut them under C3. Claude fills this sheet from the developer's screenshots and the k6 summary files; it never invents a number. Every run starts from a fresh reset (`deploy\staging-release.ps1 -SkipBuild`, then `LoadTestSeeder`). Runs that fail for a setup reason are listed and not counted.

Environment (fill in once): one Windows PC, XAMPP MySQL (MariaDB), `php artisan serve` (single-threaded) on 8001 behind Caddy on 9443, k6 v2.4.0 on the same PC, `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info`. Logical cores: 12 (sampler's first line). CPU model: _developer: still to be added_.

Limits (they stay next to every number): assumed traffic, not real users; the load generator and the system share one PC; one IP address for all fake users; a single-threaded server; times include k6's own CPU use.

## Run log
| Run | Date and start time | Counted? | Reason if not | Files kept (outside the repo) |
|-----|---------------------|----------|---------------|-------------------------------|
| Smoke | 2026-11-14 18:08 +0800 (10:08:25 UTC) | No (plumbing check) | | |
| Baseline, attempt 1 | 2026-11-14, about 18:1x +0800 | No | Stopped by the developer after about 1 minute: `maxDuration` of the endpoints scenario (3m30s) was too short for the 0.9 s per request seen. Claude's sizing mistake, from the smoke test. Durations changed to 5m and 5m15s | none |
| Baseline, attempt 2 | ended 2026-11-14 18:26 +0800 (10:26:23 UTC), 9 min 53 s | **Yes** | | summary printed in the terminal (per developer's screenshot); sampler CSV `baseline-resources.csv` on the developer's PC |
| Ordinary | ended 2026-11-14 18:49 +0800 (10:49:12 UTC), 10 min 35.7 s | **Yes** | | summary in the developer's screenshot; `ordinary-resources.csv` |
| Peak | ended 2026-11-14 19:03 +0800 (11:03:26 UTC), 10 min 54.4 s | **Yes** | | summary in the developer's screenshot; `peak-resources.csv` |
| Spike | ended 2026-11-14 19:13 +0800 (11:13:37 UTC), 8 min 26 s | **Yes** | | summary in the developer's screenshot; `spike-resources.csv`; `spike-console.txt` |

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
All values are from the developer's screenshots of the k6 end table, the sampler CSVs and the integrity output. CPU figures are % of one core; the load window is from 4 min 30 s after the sampler started (end of `setup()`) to the end of the k6 run; samples every 5 s.

| Item | Ordinary (5 users) | Peak (25 users) | Spike (0 to 40) |
|------|--------------------|-----------------|-----------------|
| Measured requests (steady phase) and per second | 294 (about 1.0 per s); whole run 350 | 405 (1.35 per s); whole run 544 | 215; whole run 249 (0.5 per s); 16 interrupted iterations |
| p50 / p95 reads, ms | 771.6 / 1,999.5 | 18,024.9 / 20,824.4 | 27,355.5 / 33,767.5 |
| p50 / p95 writes, ms | 1,062.6 / 1,960.1 | 17,745.8 / 20,963.8 | 28,409.9 / 34,090.4 |
| p50 / p95 / max all, ms | 778 / 1,987.7 / 4,421.4 | 17,958.8 / 20,830 / 22,574.8 | 24,632 / 33,790.2 / 34,761.2 |
| Thresholds (D5) | reads under 800, writes under 1500, errors under 1% | all under 2000, errors under 2% | errors under 5% (prediction, not a D5 value) |
| Threshold result | **FAILED**: lat_reads and lat_writes (errors passed) | **FAILED**: lat_all (errors passed) | all passed |
| Errors (5xx, 429, other) | 0, 0, 0 | 0, 0, 0 | 0, 0, 0 |
| Business rejections (409, 422) | 1 | 34 | 13 |
| php CPU, load window | mean 47, highest 95 | mean 92, highest 98 (71 of 77 samples at 90 or more) | mean 67, highest 97 (28 of 46 samples at 90 or more) |
| mysqld / caddy / k6 CPU, highest | 3.6 / 1.8 / 7.5 | 5.8 / 2.4 / 3.3 | 5.2 / 1.8 / 4.0 |
| php memory (MB) | 92 to 110, flat | 91 to 96, flat | 91 to 97, flat |
| `laravel.log` growth | 65,973 bytes | 118,040 bytes | 57,035 bytes |
| Integrity checks 1, 2, 2b, 5 (rows) | 0 | **not captured** (output not shown; the spike reset overwrote the data) | 0 |
| `created_201` vs rows added; `audit_logs_total` vs `writes_ok` | 9 = 9; 35 = 29 writes + 6 `close` rows | k6 counted 20 and 62; not compared | 13 = 13; **audit 33 = 30 writes + 3 `close`, but k6 counted 24 successful writes (6 fewer)** |
| Prediction matched? | See scoring below | See scoring below | See scoring below |

Note on the spike write mismatch (unexplained, hypothesis only): the database holds 6 more write actions than k6 counted as successful. Status counts and audit actions are consistent with each other (APPROVED 5 + CLOSED 3 = 8 approve rows; REJECTED 3 = 3 reject rows; 3 release, 3 return, 3 close = 3 CLOSED), and checks 1, 2, 2b and 5 are clean, so there is no sign of corrupt data. The most likely cause is that k6 stopped about 16 in-flight journeys at the end while the single-threaded server still had their requests queued and completed them afterwards. This was not tested. The ordinary run matched exactly (29 and 29).

## Scoring against predictions addendum 2 (committed 2cde512 at 2026-11-14 18:35:55 +0800, before the ordinary run)
The predictions file itself is not edited; this table is the scoring.

| Prediction | Actual | Verdict |
|------------|--------|---------|
| C3b-2 offered load about 1.0 req/s | 294 in 300 s | Hit |
| C3b-2 reads p95 under 800 ms missed | 1,999.5 ms | Hit |
| C3b-2 writes p95 under 1500 ms about 55% missed | 1,960.1 ms | Hit |
| C3b-2 p95 over all 1.5 to 3.5 s | 1,987.7 ms | Hit |
| C3b-2 median over all 0.9 to 1.5 s | 778 ms | **Missed (low)** |
| C3b-2 errors 0 to 1%, business rejections 0 to 5, no 429 | 0%, 1, none | Hit |
| C3b-2 php 60 to 95% of one core; mysqld under 15%; caddy under 5%; k6 under 15%; memory flat | php mean 47 (highest 95); the others far under; flat | php mean **missed (low)**; rest hit |
| C3b-2 p95 within 1.5 times the baseline | about 1.7 to 1.9 times | Missed (as already stated) |
| C3b-3 throughput 1.0 to 1.3 req/s | 1.35 | **Missed (slightly high)** |
| C3b-3 average answer about 18 s (12 to 25 s); p95 over all 15 to 30 s | median 18.0 s, average 17.0 s; p95 20.8 s | Hit |
| C3b-3 D5 p95 under 2000 ms missed (about 99%) | 20,830 ms | Hit |
| C3b-3 no timeouts (about 85%), error rate under 2% | max 22.6 s, 0% | Hit |
| C3b-3 business rejections 5 to 40 | 34 | Hit |
| C3b-3 php close to 100% of one core, others low | mean 92, 71 of 77 samples at 90 or more; mysqld highest 5.8 | Hit |
| C3b-4 average answer about 30 s in the hold (20 to 45 s) | probe peak 34.2 s; all-request median 24.6 s | Hit |
| C3b-4 at least one timeout (about 30%) | none (max 34.8 s of 60 s) | Did not occur (a 70% outcome) |
| C3b-4 error rate under 5%; no 429 | 0%; none | Hit |
| C3b-4 `/up` and readiness answer 200, only slowly | 200 throughout; `/up` p99 28.3 s | Hit |
| C3b-4 queue drains in 30 to 40 s after the users drop | probe 8.1 s at 21 s after the drop, 0.76 s at 34 s | Hit |
| C3b-4 probe back under about 1.3 s only 60 to 90 s after the drop, not earlier | first under 1.3 s at 34 s after the drop | **Missed (faster than predicted)** |
| C3b-5 integrity checks clean | ordinary and spike clean; peak not captured | Hit where captured |
| C3b-6 laravel.log: ordinary 150 to 400 KB; peak and spike 100 to 400 KB | 64 KB; 115 KB; 56 KB | Ordinary and spike **missed (low)**; peak hit |
| C3b-8 run times about 11 to 12 min (ordinary, peak), about 9 min (spike) | 10 min 36 s; 10 min 54 s; 8 min 26 s | Peak and ordinary slightly short; spike hit |

## Spike probe (recovery)
From `spike-console.txt` (22 probe lines, all status 200). `t` is seconds since the probe scenario started; I assume it started together with the spike scenario, so the users reached 40 at t=30 s, held to t=90 s and were down to 0 at t=120 s. That alignment is an assumption from the script design, not a measurement.

| t (s) | 0 | 6 | 16 | 36 | 69 | 108 | 141 | 154 | 160 | 166 to 235 |
|-------|---|---|----|----|----|-----|-----|-----|-----|------------|
| probe ms | 725 | 5,216 | 15,214 | 27,815 | **34,180** | 27,703 | 8,098 | **755** | 1,029 | 538 to 994 |

- Before the spike the probe answered in 725 ms (the same fixed cost as the baseline). At the top of the hold it took 34.2 s, close to 40 users times about 0.85 s (the queue-length estimate).
- After the users dropped to 0, the probe was still at 8.1 s at t=141 s (21 s after the drop) and first back under 1.3 s at t=154 s (about 34 s after the drop). Every later probe was between 538 and 1,029 ms, so there was no lasting damage and no second slowdown.
- No timeouts and no errors in the probe or anywhere in the run.

## Bottleneck candidates (confirmed or disproved by these runs)
| ID | Candidate | Evidence | Verdict |
|----|-----------|----------|---------|
| P1 | Unbounded lists | Reads were no slower than writes in any run; at peak and spike all endpoints waited the same time (one shared queue); the database was not busy (mysqld highest 5.8%) | Not the limiting factor in chunk 3 (data set is small: 56 items, at most 30 requests) |
| P2 | `last_used_at` update on each call | No separate signal; mysqld under 6% in every run | Not shown by these runs |
| P3 | Database cache and queue, one log file | `laravel.log` grew only 57 to 118 KB per run; mysqld under 6% | Not shown by these runs |
| P4 | Single-threaded server | Peak: php at about 92% of one core (71 of 77 samples at 90% or more), mysqld/caddy/k6 near idle; throughput 1.35 req/s at 25 users; every endpoint, including `/up`, waited 16 to 20 s; spike probe 34 s at 40 users, about users times service time | **Confirmed** as the capacity limit |
| P5 | Flutter release size, no compression | Not tested: the k6 scripts call the API only, not the Flutter files | Not measured in chunk 3 |
| P6 | A fixed cost of about 600 ms on every request (OPcache probably not loaded; not proven) | Baseline: `/up` about 630 ms in k6; handler time about 265 ms in the Laravel log; `php -m` shows no OPcache | Suspected, to be tested in chunk 5 with a before and after run. New evidence: at peak php is CPU-bound (about 92% of a core), so the fixed cost is PHP work, not waiting on MySQL or Caddy. That fits OPcache being off but does not prove it |
