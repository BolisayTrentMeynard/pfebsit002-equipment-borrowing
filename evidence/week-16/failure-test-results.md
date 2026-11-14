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
Checked by the developer on 2026-11-14 (night): `outage-resources.csv` has 589 lines (a header and 588 rows, four processes per sample, about one sample every 5 s). First row 12:36:02 UTC (20:36:02 PHT), last row 12:48:37 UTC (20:48:37 PHT) with k6 at 0 processes, so the sampler was stopped about 12 s after k6 ended (k6 summary written 20:48:25 PHT). Claude has not read the rows and they are not used in this sheet. The CSV is git-ignored.

## Flutter app during the outage (optional)
Not done. No claim is made about what the app shows when the database is down.

## Findings and follow-up
1. **A database outage makes every call hang for about 40 to 55 s** (user p50 42.8 s during the outage; `/up` took 39.7 s and readiness 40.7 s although `/up` uses no database, because they queue behind the failing calls on the single-threaded server). I predicted about 3 s (H2) or under 1 s (H1); both were wrong, and I did not measure the cause. Candidate fix for chunk 5 (tests first): a short database connect timeout so a failing call returns in a few seconds. Not applied.
2. **The readiness poll itself hung 53 s**, so detection took 99 s, not 10 to 25 s. The monitor's "detect within one poll" claim in `monitoring-map.md` is wrong for this setup and is corrected there.
3. **AC-44 limit confirmed:** exception text in `laravel.log` holds the SQL with a token hash (22 lines). Candidate fix: report database connection errors without bindings. Not applied; keep as a recorded limit if not fixed.
4. **No partial write** after the outage (12 requests, 12 submit rows, audit arithmetic consistent).
5. **Recovery was fast once MySQL ran again:** first readiness 200 six seconds after the Start click, with no restart of php or Caddy.
6. **Not done in this run, stated openly:** M3 to M6, the app check, `ops:status` during the outage. They were done in the second, short outage (chunk 4b, below). A clean rerun of the full k6 outage with the Start on time was not done (developer's decision).
7. Misses kept: detection time (99 s vs 10 to 25 s), 5xx-answer latency (both hypotheses), answer count during the outage (10 vs 30 to 100), ERROR count (22 vs 30 to 100), recovery time (6 s vs 10 to 30 s, faster), "before" p95 (2.8 s vs 1.5 to 2.5 s, slightly high).

---

# Chunk 4b: second short outage (no k6, no sampler, no reset of the run)

Status: **outage part counted; recovery part disturbed and NOT counted as a recovery measurement.** Written by Claude from the developer's screenshots (terminal, XAMPP Control Panel, app). Claude ran nothing. Predictions: `week16-failure-predictions.txt`, chunk 4b addendum (C4b-1 to C4b-10), committed alone as `74e1025` at 2026-11-14 21:02:00 +0800 (before the outage) and pushed (`f1f8940..74e1025`).

What it was: MySQL stopped by hand in the XAMPP Control Panel (MySQL row only, Apache untouched), one person, one request at a time, no load. Staging was reset (`staging-release.ps1 -SkipBuild`) and `LoadTestSeeder` was run before the counted attempt. Purpose: do what chunk 4 could not (M3 to M6, `ops:status`, the app during an outage) and test H3 (one failing call alone takes about 6 to 9 s; the 40 s answers in chunk 4 were queueing).

## Run log
| Attempt | Time (PHT, XAMPP log) | Counted? | What happened |
|---------|-----------------------|----------|---------------|
| First attempt | Stop 21:04:22, accidental Start 21:04:26, Stop again 21:04:29, Start 21:06:46 | No | Only M6 was captured (`Get-Process mysqld`: no process, as predicted). The accidental Start click broke the outage into two parts. Not mixed into the counted run. |
| Unexplained stop | Stop 21:11:47, Start 21:11:53 | No | The reason was not given by the developer. Recorded as unexplained. |
| Counted attempt | Stop 21:17:42 | Outage: **Yes**. Recovery: **No** | Setup was clean (readiness 200 at 21:17:07). MySQL was started at 21:20:36 but then crashed twice (see Incident). |

## Timeline (counted attempt)
| Event | Time (PHT) | Source |
|-------|------------|--------|
| Readiness 200 before the stop (0.57 s) | 21:17:07 | curl Date header 13:17:07 GMT |
| MySQL stopped | 21:17:42 | XAMPP log |
| M3 `/api/me`, no token | 21:18:07 | Date 13:18:07 GMT |
| M1 `/up` | 21:18:25 | Date 13:18:25 GMT |
| M2 readiness | 21:18:48 | Date 13:18:48 GMT (log line 13:18:47) |
| M4 `/api/me`, made-up token | 21:19:15 | Date 13:19:15 GMT |
| M5 `ops:status`, M6 `Get-Process mysqld` | between M4 and 21:20:36 | screenshots (no clock visible) |
| App list during the outage | 21:20 | taskbar clock |
| Start click | 21:20:36 | XAMPP log (running 21:20:37) |
| **Crash 1: "MySQL shutdown unexpectedly"** | 21:20:46 | XAMPP log; running again 21:20:50 |
| Readiness 200 | 21:21:25 | Date 13:21:25 GMT |
| **Crash 2: "MySQL shutdown unexpectedly"** | 21:21:49 | XAMPP log |
| Readiness 503 (8.95 s) and the app showing "Cannot reach the server" | 21:22:33 and 21:22 | Date 13:22:33 GMT, taskbar clock |
| MySQL started (running) | 21:24:05 | XAMPP log and `mysql_error.log` |
| Readiness 200 (0.92 s) and again (0.58 s) | 21:28:34 and 21:29:25 | Date headers |
| App list loaded, "No requests yet" | 21:30 | taskbar clock |

The planned outage lasted about 2 min 54 s (21:17:42 to the Start click at 21:20:36).

## Manual checks during the planned outage
| Check | Predicted | Actual | Result |
|-------|-----------|--------|--------|
| M3 `/api/me`, no token (C4b-3) | 401 `{"message":"Unauthenticated."}` in under 2 s (about 80%) | **401**, same body, `time_total=0.75 s` | Hit |
| M1 `/up` (C4b-1) | 200 in 0.4 to 1.5 s (about 85%) | **200**, `Application up`, `time_total=0.64 s` | Hit |
| M2 readiness (C4b-2) | 503 `{"status":"fail"}`, no-store, 4 to 12 s (H3, about 55%) | **503**, body exactly `{"status":"fail"}`, `Cache-Control: no-store, private`, `nosniff`, `time_total=8.87 s` | Hit |
| M4 made-up token (C4b-4) | 500 `{"message":"Server Error"}`, no SQL, security headers, 4 to 12 s | **500**, body `{"message": "Server Error"}`, `no-store`, `nosniff`, `Referrer-Policy`, `time_total=8.90 s` | Hit |
| M5 `ops:status` (C4b-5) | Database NOT REACHABLE, counts "unknown (database down)", debug off, log level info, exit code 1, no stack trace | Exactly that: Environment staging, Debug off, Log level info, Database NOT REACHABLE, Queue driver database, the four counts `unknown (database down)`, Log file 3,072 KB, **exit code 1** | Hit (the 3 to 20 s time was not measured) |
| M6 `Get-Process mysqld` (C4b-6) | "Cannot find a process", instantly | Error `NoProcessFoundForGivenName` (only the last line of this run's output is visible in the screenshot; the full message is in the first attempt's screenshot) | Hit |
| App tab, Refresh (C4b-7) | "Something went wrong" with a plain message and Try again, not the login screen | **"Something went wrong. The server had a problem. Try again in a moment."** with a Try again button; still signed in as Load Borrower 01 | Hit |

H3: one failing call on its own took about 9 s three times (M2 8.87 s, M4 8.90 s, and 8.95 s for readiness at 21:22:33). `/up` alone took 0.64 s, against 39.7 s and 37.8 s in chunk 4. That is consistent with the chunk 4 delays being queueing on the single-threaded server. It is NOT a measurement of queueing: this run had one request at a time, and the cause of the 9 s per failing call is not measured.

## Data and log checks
| Check | Predicted (C4b-9) | Actual | Result |
|-------|-------------------|--------|--------|
| `borrow_requests_total` | unchanged | **0** | Hit, with a limit: the number before the outage was not captured; 0 is expected after the staging reset and `LoadTestSeeder`, and nothing was written during the run |
| `audit_logs_total` | unchanged | **0** | Hit (same limit) |
| `submit_audit_rows` (extra file) | 0 | **0** | Hit |
| Equipment status | all available | `available` 56 | Hit |

Log scan (from byte 3,118,270 to 3,224,330 = 106,060 bytes, 520 lines): 4 ERROR entries, all `SQLSTATE[HY000] [2002]` connection refused; 15 `http.request` lines (200 x8, 401 x1, 500 x4, 503 x2); 2 `health.ready_failed` lines (the first at 13:18:47 GMT, matching M2); Bearer 0, password 0, email shape 0, `X-Message-Signature` 0; 4 lines with a 64-character hex string (the token hash in the exception SQL, the same AC-44 limit as chunk 4); 8 of 8 connection frames hide the password. This window also holds the two crashes, so the 500 and 503 counts cannot be split between the planned outage and the crashes.

## Incident: MySQL crashed twice after the Start click
XAMPP logged "MySQL shutdown unexpectedly" at 21:20:46 and 21:21:49. `mysql_error.log` at the restart (21:24:05) shows InnoDB crash recovery, many "log sequence number is in the future" errors, and tablespace files that could not be opened for databases of other, unrelated projects in the same MySQL instance. The cause was not established; the developer reported that MySQL runs normally afterwards (readiness 200 twice, integrity checks ran). MySQL had been stopped by hand four times earlier that evening; that may or may not be related, and this is a guess, not a finding. Because of this, the recovery timing is NOT counted.

- **C4b-8** (first readiness 200 within 1 to 10 s of the Start click; Try again loads the list without signing in): **not scored.** There was no readiness check between the Start click (21:20:36) and 21:21:25, and MySQL crashed twice. After "Try again" was pressed once (21:30), the app showed the list again while still signed in, which fits the prediction, but that was after the second restart.
- **C4b-7 observation, not scored:** at 21:22 (during the second, unplanned outage) the app showed "Cannot reach the server: the server took too long to answer", the wording of the 25% alternative. The state was different from the planned outage, so it is recorded here only.
- **C4b-10** said a MySQL crash was not tested here. An unplanned crash happened anyway; nothing was predicted about it and nothing is claimed.

## Score against the predictions
C4b-1, 2, 3, 4, 5, 6, 7 and 9: hit. C4b-8: not scored. C4b-10: not applicable. No misses in this addendum. Limits: the predictions were written after chunk 4, so they were informed by it; one outage by one person on one PC is an observation, not a rate; the 3 to 20 s time for `ops:status` was not measured.

## Findings from chunk 4b
1. A failing call alone takes about 9 s on this setup (H3 supported for the single call). The 40 s answers in chunk 4 are consistent with queueing. Why one refused connection takes about 9 s is not measured. The candidate fix stays the same for chunk 5 (tests first): a short database connect timeout.
2. The no-token request (M3) answered in 0.75 s during the outage because it needs no database.
3. A made-up token gives a plain 500 `Server Error` with no SQL, host or path in the response. The SQL with a token hash is only in `laravel.log` (AC-44 limit, 4 more lines).
4. `ops:status` reports an outage correctly (exit code 1, no stack trace) on a real outage. This closes the gap from chunk 4 (M5).
5. The Flutter app shows a plain error with Try again during an outage and does not log the user out.
6. Recovery behaviour after a clean restart was already shown in chunk 4 (6 s). This chunk adds no clean recovery measurement because of the unplanned crashes.
