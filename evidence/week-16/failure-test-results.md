# Week 16 Failure Test Results: MySQL Stopped During the Ordinary Workload (AC-48)

Status: **run on 2026-11-14 (evening), counted as a documented deviation** (developer approved: the Start click was about 1 min 46 s late, see the timeline). Written by Claude from the developer's screenshots (terminal, k6 end tables, XAMPP Control Panel, integrity and scan outputs). Claude ran nothing. Predictions: `week16-failure-predictions.txt`, chunk 4 addendum (C4-1 to C4-10), committed alone as `c25e6ec` at 2026-11-14 19:43:49 +0800, before any run.

Environment: local staging, one Windows PC, XAMPP MySQL (MariaDB) stopped and started by hand in the XAMPP Control Panel (MySQL is not a Windows service on this PC: `Get-Service` found nothing), `php artisan serve` on 8001 behind Caddy on 9443, k6 v2.4.0 on the same PC, `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info`. Limits: a normal Stop, not a crash; the load generator shares the PC; stop and start done by a person.

## Run log
| Run | Start (PHT) | Counted? | Reason if not |
|-----|-------------|----------|---------------|
| Predictions addendum commit `c25e6ec` | 19:43:49 | n/a | |
| Attempt 1 | about 20:14 | No | k6 was started in a window without `LOAD_TEST_PASSWORD`; it aborted before any request (0 requests). Sampler was in the wrong window. |
| Attempt 2 (control run) | 20:15 to 20:27 (k6 clock 12:20 UTC) | No | MySQL was never stopped (the developer had not stopped it; the cue was misunderstood). Every phase was normal: 0 answers of 5xx, readiness 200 on all polls. Kept as a control: the same workload with no outage gave 0 errors. Files renamed `*-nostart`, `*-control`, `failure-summary-notcounted.*`. |
| Counted run | 20:40:38 | **Yes, with a deviation** | The Stop click matched the cue. The Start click was late (see timeline), so MySQL was down for 3 min 48 s instead of 2 min and the "after" phase starts while it was still down. The two "after" thresholds therefore failed. The phase tables of "after" are NOT a recovery measurement; the timestamps are. |

A garbled paste (two curl commands on one line) produced no result and is not counted; the clean M2 run after it is the one recorded.

## Timeline
| Event | Clock time (PHT) | Seconds from load start |
|-------|------------------|-------------------------|
| Load started | 20:40:38 | 0 |
| Stop cue | 20:43:38 | 180 |
| MySQL stopped (XAMPP log) | 20:43:36 | about 178 |
| First readiness 503 (MONITOR line, the poll itself took 53.4 s) | 20:45:15 | 277 |
| Start cue | 20:45:38 | 300 |
| MySQL started (XAMPP log: Start attempt 20:47:24, running 20:47:25) | 20:47:24 | about 406 |
| First readiness 200 after the start (MONITOR line, 2,825 ms) | 20:47:30 | 413 |
| Detect: stop to first 503 | 99 s (predicted 10 to 25 s) | |
| Recover: start click to first 200 | 6 s (predicted 10 to 30 s) | |
| Outage length | 3 min 48 s (planned 2 min) | |

The XAMPP log also shows Apache stopped at 20:43:35 and started at 20:47:24 (same moments). Apache is not part of the staging path (Caddy and `artisan serve`); the developer was asked whether it was on purpose and has not said. Recorded, not explained.

## Per phase (k6 tables)
| Phase | Window (s) | n | ok | 5xx | other | 5xx share | p50 ms | p95 ms | max ms | Predicted |
|-------|-----------|---|----|-----|-------|-----------|--------|--------|--------|-----------|
| before | 60 to 180 | 97 | 97 | 0 | 0 | 0 % | 1,160 | 2,804 | 3,562 | 0 errors, p95 1.5 to 2.5 s, 80 to 110 requests |
| stopping | 180 to 200 | 2 | 0 | 2 | 0 | 100 % | 13,636 | 17,163 | 17,555 | not judged |
| during | 200 to 300 | 10 | 0 | 10 | 0 | 100 % | 42,829 | 49,682 | 52,688 | at least 95% 500; 30 to 38 user answers (H2) or about 100 (H1) |
| starting | 300 to 330 | 2 | 0 | 2 | 0 | 100 % | 45,374 | 45,436 | 45,443 | not judged |
| after | 330 to 450 | 46 | 38 | 8 | 0 | 17.4 % | 1,791 | 51,057 | 55,024 | 0 5xx (invalid: MySQL came back at about t=406 s) |

| Phase | /up n | /up 200 | /up p50 | /up max | ready n | ready 200 | ready 503 | ready p50 | ready max |
|-------|-------|---------|---------|---------|---------|-----------|-----------|-----------|-----------|
| before | 10 | 10 | 1,117 | 2,424 | 10 | 10 | 0 | 993 | 2,699 |
| stopping | 0 | 0 | - | - | 0 | 0 | 0 | - | - |
| during | 1 | 1 | 37,809 | 37,809 | 1 | 0 | 1 | 53,393 | 53,393 |
| starting | 0 | 0 | - | - | 0 | 0 | 0 | - | - |
| after | 4 | 4 | 9,305 | 43,729 | 4 | 3 | 1 | 2,576 | 53,693 |

Whole run: 255 requests, 0.3 per second, 24 answers of 5xx (22 user answers plus 2 readiness 503), 0 of 429, 0 other unexpected, 12 answers of 201 to POST /requests, 37 successful writes. **k6 thresholds: FAILED** (`fo_after_ready_503`, `fo_after_user_5xx`; exit code 99), because the "after" window began while MySQL was still down. No threshold failed before the stop.

Note on counts: a request counts in the phase where its ANSWER arrived. Because each failing call took 40 to 55 s, only 10 user answers arrived in the 100 s "during" window, and the ones that failed are counted in the phase where they ended, which is why "after" holds 8 5xx answers from requests started during the outage.

## Manual checks during the outage
| Check | Predicted | Actual |
|-------|-----------|--------|
| M1 `/up` | 200 | **200** `Application up`, `Via: 1.1 Caddy`, `time_total=39.74 s` (Date header 12:44:39 GMT = 20:44:39 PHT). Liveness stayed 200 but was slow. |
| M2 readiness | 503 `{"status":"fail"}`, no-store | **503**, body exactly `{"status":"fail"}`, `Cache-Control: no-store, private`, `nosniff`, `X-Correlation-Id` present, `time_total=40.75 s` (Date 12:45:59 GMT = 20:45:59 PHT) |
| M3 no token | 401 | **Not done** (server too slow, the developer chose M2 first) |
| M4 made-up token | 500 `Server Error` | **Not done** |
| M5 `ops:status` | Database NOT REACHABLE, exit 1 | **Not done**. The "database down" branch of `ops:status` is therefore untested on a real outage (it has PHPUnit tests, `OperationsDownTest`). |
| M6 `Get-Process mysqld` | no process | **Not done in time.** The XAMPP log shows MySQL "stopped" at 20:43:36 and the readiness 503 confirms the database was unreachable; this is indirect evidence only. |

## Data after the run (staging database)
| Check | Predicted | Actual |
|-------|-----------|--------|
| Standard checks 1, 2, 2b, 5 | no rows | No rows printed under these checks in the screenshot (the output shows only the summary tables) |
| Extra checks 6, 7, 8 | no rows | No rows printed (the extra file printed only `submit_audit_rows` = 12) |
| `borrow_requests_total` vs `created_201` | equal, or at most +2 | **12 and 12, equal** |
| `submit_audit_rows` vs `borrow_requests_total` | equal | **12 and 12, equal** |
| Audit rows vs k6 successful writes | audit at least writes | 42 audit rows; k6 counted 37 writes. The code shows a return writes TWO audit rows (return and close, `BorrowWorkflow::returnAndClose`): 12 submit + 9 approve + 3 reject + 8 release + 5 return = 37, plus 5 close = 42. Consistent. |
| Equipment: 3 released requests | 3 items on loan | 3 on loan, 53 available |

No partial write was found. This was not a test of a crash, only of a normal stop.

## Log scan (`scan-outage-log.ps1`, from byte 2,496,017)
| Item | Predicted | Actual |
|------|-----------|--------|
| New text | 0.3 to 1.5 MB (H2) | **606.6 KB**, 3,072 lines (hit) |
| ERROR entries, all with SQLSTATE | 30 to 40 (H2) or about 100 (H1) | **22**, all `SQLSTATE[HY000] [2002]` connection refused (miss: fewer calls got through at 40 s each) |
| `http.request` lines with status 500 vs k6's user 5xx | within 2 | **22 and 22** (hit) |
| `health.ready_failed` lines | k6's 503 count plus M2 | **3** = 2 (k6) + 1 (M2) (hit) |
| Lines with a 64-character hex string | at least 1 (about 75%) | **22**. The exception text holds `select * from api_tokens where token_hash = <hex>`. It is a token HASH, not a token. |
| Bearer / email shape / password / X-Message-Signature | 0 / 0 / 0 / 0 | **0 / 0 / 0 / 0** (hit) |
| Connection frames hiding the password | all `SensitiveParameterValue` | **44 of 44** (hit) |

Other counts in the new text: 199 `http.request` lines with 200, 12 with 201, 1 each with 401, 404, 409 x2, and 3 with 503.

## Sampler (`outage-resources.csv`)
Not seen by Claude. The developer has the CSV on the PC (git-ignored). Whether the sampler was stopped with Ctrl+C is not confirmed. Not used in this sheet.

## Flutter app during the outage (optional)
Not done. No claim is made about what the app shows when the database is down.

## Findings and follow-up
1. **A database outage makes every call hang for about 40 to 55 s** (user p50 42.8 s during the outage; `/up` took 39.7 s and readiness 40.7 s although `/up` uses no database, because they queue behind the failing calls on the single-threaded server). I predicted about 3 s (H2) or under 1 s (H1); both were wrong, and I did not measure the cause. Candidate fix for chunk 5 (tests first): a short database connect timeout so a failing call returns in a few seconds. Not applied.
2. **The readiness poll itself hung 53 s**, so detection took 99 s, not 10 to 25 s. The monitor's "detect within one poll" claim in `monitoring-map.md` is wrong for this setup and is corrected there.
3. **AC-44 limit confirmed:** exception text in `laravel.log` holds the SQL with a token hash (22 lines). Candidate fix: report database connection errors without bindings. Not applied; keep as a recorded limit if not fixed.
4. **No partial write** after the outage (12 requests, 12 submit rows, audit arithmetic consistent).
5. **Recovery was fast once MySQL ran again:** first readiness 200 six seconds after the Start click, with no restart of php or Caddy.
6. **Not done, stated openly:** M3 to M6, the app check, `ops:status` during the outage. A clean rerun with the Start on time was not done (developer's decision).
7. Misses kept: detection time (99 s vs 10 to 25 s), 5xx-answer latency (both hypotheses), answer count during the outage (10 vs 30 to 100), ERROR count (22 vs 30 to 100), recovery time (6 s vs 10 to 30 s, faster), "before" p95 (2.8 s vs 1.5 to 2.5 s, slightly high).
