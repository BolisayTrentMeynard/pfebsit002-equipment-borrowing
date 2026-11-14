# Monitoring Map and Incident Walkthrough (Week 16, AC-45)

Status (2026-11-15, final for Week 16): **designed in chunk 4, run in chunks 4 and 4b, completed in the evidence round.** The Actual column is filled from the developer's screenshots and terminal output (Claude ran nothing). The thresholds are PROPOSED and were checked against the MySQL-stopped runs; one threshold is CORRECTED: under load detection took 99 s, not one poll (see IW-3 and section 5). Steps first skipped in chunk 4 (IW-5 to IW-7) were done in chunk 4b.

Scope and honesty: this is a map for a local prototype, not a monitoring product. There is no external monitoring tool, no pager and no dashboard. Detection is by the developer reading a terminal, a log file and a command's output. The map says what to look at, what "bad" looks like, who acts, and what the first action is.

## 1. Signals, where they are read, thresholds, owner

Owner of every signal: **the developer** (the only operator of this prototype). Response time in the course context: the developer looks at the signals during a lab session and when a test or demo is about to start. There is no 24 hour on-call, and the evidence does not pretend there is.

| # | Signal | Where it is read | "Bad" looks like | Proposed threshold | First action | Known weakness |
|---|--------|------------------|------------------|--------------------|--------------|----------------|
| S1 | Liveness | `GET /up` (Laravel built-in, no database) | no answer, or not 200 | no 200 within 10 s on 2 polls in a row | Check that the `php artisan serve` window and the Caddy window are still open; restart the one that is gone | Says only that PHP answers. It stays 200 while the database is down. On this single-threaded server it can be slow while 200 (queue behind other calls), so slow is not dead |
| S2 | Readiness | `GET /api/health/ready` (200 `{"status":"ok"}` or 503 `{"status":"fail"}`) | 503, or no answer | 503 on 2 polls in a row. CORRECTED: a failing poll can take 8 to 53 s (about 8.4 s with no load, 40 to 53 s under load), so "2 polls" is about 20 to 25 s with no load and up to about 100 s under load; give the probe a timeout of at least 60 s or it will report "no answer" | Run `php artisan ops:status`; if it says Database NOT REACHABLE, start MySQL. Do not restart php or Caddy first | Checks the database and the queue table only. It does not check the notifier receiver, disk space or the Flutter files |
| S3 | Server errors | `http.request` lines in `backend\storage\logs\laravel.log` (status field) | many `"status":500` lines | 5 or more 500 lines in one minute, or more than 2% of the lines in 5 minutes | Look at the `staging.ERROR` entries just above (exception and SQLSTATE) | One log file, read by hand. Exception text can hold SQL and bound values (see section 4) |
| S4 | Latency | `http.request` `ms` field (the handler's own time) | the handler's time rises | proposed: p95 of `ms` above 1,000 ms over 5 minutes (the baseline handler times were 130 to 360 ms) | Check the sampler or Task Manager for php at about 100% of one core (saturation, P4); check for a heavy list (P1) | NOT validated: the k6 times include about 600 ms outside the handler, and the D5 targets (800 and 1,500 ms) are already missed in this setup, so they cannot be used as an alert |
| S5 | Failed jobs | `php artisan ops:status` row "Failed jobs" | above 0 | any value above 0 | Read the `failed_jobs` rows and the `reminder.*` log lines | Needs a person to run the command |
| S6 | Manual-handling backlog | `ops:status` row "Manual handling backlog"; the app's Manual handling tab (approver) | above 0 | any value above 0 for more than 1 working day | The approver resolves the item (this is the designed human step) | A backlog is normal after a permanent notifier failure; it is a work item, not an outage |
| S7 | Jobs waiting | `ops:status` row "Jobs waiting" | grows and does not fall | above 0 for more than 10 minutes while a worker should be running | Check that `queue:work` is running | The worker is not part of the staging run by default (see the scope) |
| S8 | Log size | `ops:status` row "Log file size" | grows fast | above 50 MB, or more than 10 MB in one hour | Find the repeating line; clear or rotate the file | `LOG_STACK=single`, no rotation; one outage wrote traces for every failed call (section 4) |
| S9 | Process present | `Get-Process mysqld`, `php`, `caddy` (the sampler also records the process count) | a process missing | any of the three missing | Start it; for mysqld use the XAMPP Control Panel | Manual |
| S10 | Release state | `ops:status` rows Environment, Debug mode, Log level | staging not `staging`, or Debug mode ON | any difference from `staging`, off, info | Stop; fix `.env.staging`; reset with the release script | Only if `ops:status` is run with `APP_ENV=staging` |

Not monitored (stated openly): certificate expiry (the Caddy certificate is from a local development CA), disk space, backups (chunk 5 rehearses one restore), the notifier receiver, the Flutter client's own errors, anything outside the one PC.

## 2. Decision table (symptom to first action, and what not to do)

| What the signals show | Most likely cause | Do | Do NOT |
|-----------------------|-------------------|----|--------|
| S1 fails, S2 fails | PHP server or Caddy is gone | Restart the window that is gone, then run the health checks of the release script | Reset the database |
| S1 200, S2 503, `ops:status` says Database NOT REACHABLE, `mysqld` missing | MySQL is stopped or crashed | Start MySQL; wait for S2 to turn 200; run `ops:status`; check the log for the crash reason | Restart php or Caddy (it would not help and would hide the evidence); reset the database |
| S1 200, S2 200, S3 many 500 | A code or data fault in one route | Read the ERROR entries; roll back to the `week-15` tag if a new release caused it | Ignore it because readiness is green |
| S1 slow, S2 slow, S4 high, php near 100% of one core | Saturation of the single-threaded server | Wait for the queue to drain; reduce the load | Restart (the queue is lost and the cause stays unknown) |
| S5 or S6 above 0 only | Notifier failures handled by the retry policy | Approver resolves the manual-handling items | Treat it as an outage |

## 3. Incident walkthrough: MySQL stopped during the ordinary workload

Purpose: show that the outage is **detected from the signals above alone**, located, and answered with the right first action. The same run is the failure test of AC-48. The predictions are in `week16-failure-predictions.txt` (chunk 4 addendum, committed before the run). Result column: filled after the run of 2026-11-14 (counted with a documented deviation, see `failure-test-results.md`).

| Step | Time | What the operator does | Signal read | Predicted | Actual |
|------|------|------------------------|-------------|-----------|--------|
| IW-1 | before | Start k6, wait for the load; look at the monitor lines | S1, S2 | `/up` 200 and readiness 200 on every poll | Done: readiness 200 and /up 200 on all 10 polls in "before". |
| IW-2 | cue 180 s | Click Stop on MySQL (XAMPP); write down the time | none | n/a | Done: XAMPP log shows MySQL stopped 20:43:36 PHT (cue 20:43:38). |
| IW-3 | 10 to 25 s later | Read the monitor line "readiness changed 200 -> 503" | S2 | First 503 within one poll | **Miss.** First 503 line at 20:45:15, 99 s after the stop, because that poll took 53.4 s to answer. The threshold "detected within one poll" is wrong here: detection time = poll gap + the time a poll can hang. |
| IW-4 | right after | Run M1 and M2 by hand (`/up`, readiness) | S1, S2 | `/up` 200, readiness 503 `{"status":"fail"}` | Done: M1 200 (39.7 s), M2 503 `{"status":"fail"}` no-store (40.7 s). |
| IW-5 | right after | Run `ops:status` and read the exit code | S10, S5 to S8 | Database NOT REACHABLE, counts "unknown (database down)", exit code 1 | Not done in chunk 4 (server too slow). **Done in chunk 4b (2026-11-14, no load):** Database NOT REACHABLE, counts "unknown (database down)", exit code 1. Chunk 5: against a closed port `ops:status` took 9.12 s in total (about 1 s start-up plus about 8 s, consistent with repeated connection attempts; not isolated). |
| IW-6 | right after | Check `Get-Process mysqld` | S9 | no such process | Not done in chunk 4. **Done in chunk 4b:** no mysqld process (M6). |
| IW-7 | right after | Run M3 and M4 (no token, made-up token) | S3 | 401 without a token, 500 with one | Not done in chunk 4. **Done in chunk 4b:** M3 401 in 0.75 s; M4 500 `Server Error` in 8.90 s with no SQL in the body. |
| IW-8 | during | Decide from section 2 | all | "MySQL is down: start MySQL, do not restart php or Caddy" | Decision by the developer after the run: the signals point to the database (readiness 503, log ERROR with SQLSTATE 2002). Start MySQL; php and Caddy were not restarted. Latency also rose, which alone would not point to the database. |
| IW-9 | cue 300 s | Click Start on MySQL; write down the time | S2 | first readiness 200 within 10 to 30 s | Start click 20:47:24 (late, cue was 20:45:38). First readiness 200 at 20:47:30, 6 s after the click (predicted 10 to 30 s). |
| IW-10 | after | Scan the new log text (`scan-outage-log.ps1`), run the integrity checks | S3, S8 | ERROR entries with SQLSTATE; no partial write | Done: 22 ERROR entries with SQLSTATE 2002, 22 http.request 500 lines, no partial write (12 requests, 12 submit rows). |

Time to detect (click to first 503 seen) and time to recover (click to first 200 seen) are filled in from the k6 MONITOR lines and the CUE lines. They include the poll gap of 10 s, so they are upper bounds.

## 4. What the walkthrough can and cannot show
- It shows detection by a person reading a terminal, not an automatic alert. A real alert (an email, a pager) is not built, and the evidence says so.
- The expected finding about the log (section S3 and S8): exception text can contain the SQL with bound values, so a token hash can appear. If the run shows it, it is recorded in the results and in the risk register, and a fix is a candidate for chunk 5.
- The poll gap (10 s) limits how exact the detection time is. The manual checks give finer times.
- The outage is a normal Stop of MySQL on the same PC. A crash, a slow database or a network fault would look different (see the "Not tested" list in the predictions).

## 5. Detection and recovery with no load (chunk 5, `recovery-poll.ps1`, 2026-11-15)
Readiness was polled about once per second with no load. 77 polls, 10 not-200, all 503 `{"status":"fail"}`, each taking about 8.3 to 8.6 s. First 503 recorded 13.05 s after the stop key (about 1 s after XAMPP reported the stop, plus the 8.3 s the failing answer took). First 200 recorded 3.96 s after the start key (about 1 to 2 s after XAMPP reported MySQL running). `/up` stayed 200 in 0.05 to 0.27 s. No restart of php or Caddy, and no "MySQL shutdown unexpectedly" line, so this recovery counts as clean. Limits: key presses 2 to 4 s before the XAMPP clicks; one-second log resolution; a failing poll blocks about 8.4 s; the poll is itself a load of about 1 request per second.

What this changes in the map: detection time is the poll gap plus the time a failing answer takes, so with a 10 s poll it is about 20 s with no load and much longer under load; and `/up` is the wrong signal for a database outage (200 throughout, only slow under load).

## 6. Owner, response time and what is not built
The owner of every signal is the developer. There is no alert: nothing reaches a person unless the developer is looking at a terminal. A real deployment would add an external probe on readiness with a long timeout and an alert to a named owner, log rotation, and an alert on failed jobs and the manual-handling backlog. During a rollback to `week-15`, readiness answers 404 on purpose (see `rollback-and-recovery-plan.md`): the probe must not treat that as an outage of the whole service.
