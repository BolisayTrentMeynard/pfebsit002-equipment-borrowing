# Rollback and Recovery Plan (Week 16, AC-51)

Written by Claude on 2026-11-15 from the scripts in `deploy/` and `loadtest/` and from the developer's terminal output and screenshots. Claude ran none of it. Everything below is for the local staging setup (one PC); a real deployment would need a different plan (see `environment-comparison.md`).

## 1. When to use which response

| Situation | Response | Do not |
|-----------|----------|--------|
| MySQL stopped or crashed (readiness 503, `ops:status` says Database NOT REACHABLE) | Start MySQL in the XAMPP Control Panel, wait for readiness 200, run `ops:status`, read `mysql_error.log` and the `staging.ERROR` lines | Restart php or Caddy; reset the database |
| A new release breaks a route (S3 shows many 500s while readiness is 200) | Roll the CODE back to the `week-15` tag (section 3), then investigate | Roll back if a migration changed (the script refuses) |
| Staging data is wrong or lost | Restore from the last dump into a scratch database first, compare, then decide (section 2) | Restore over the live database without a scratch check |
| Slow after a PHP change (for example OPcache) | Put back `php.ini.bak-before-opcache` and restart the API window | Edit `php.ini` again without a backup |

## 2. Backup and restore (`deploy\backup-restore.ps1`)

- Run from a standalone PowerShell window in the repo root. It only READS the source database (`equipment_staging` by default), dumps it with `mysqldump` to `C:\dev\pfebsit002-backups\` (outside the repo), prints the size and SHA-256, restores it into the scratch database `equipment_restore_check`, compares every table (row count and `CHECKSUM TABLE`), then drops the scratch database.
- Rehearsed once (2026-11-14 23:55, MariaDB 10.4.32): dump 16,165 bytes, 14 of 14 tables matched, scratch dropped. The first try failed only because the window was in the wrong folder.
- The dump holds fake users with password hashes and token hashes. It never goes into the repo, a zip, Drive or a screenshot. `make-clean-zip.bat` skips SQL files; the scan script checks that no dump file is tracked.
- **What this does not show:** the database held almost no rows (6 items, 4 users, 9 migrations), the scratch copy is on the same MySQL server, no restore over a live database was done, and there is no schedule and no copy off the machine.

## 3. Roll the code back to `week-15` (`deploy\rollback-rehearsal.ps1`)

Preconditions the script checks itself: the tag `week-15` exists; no migration file and no `backend\composer.lock` differs between `week-15` and HEAD (otherwise it stops and changes nothing); port 8001 is free; Caddy is running on 9443; no leftover rollback folder.

Steps used in the counted rehearsal:
1. Press Ctrl+C in the STAGING API window (port 8001). Leave Caddy running.
2. `powershell -NoProfile -ExecutionPolicy Bypass -File deploy\rollback-rehearsal.ps1 -Action Start` in a window where `LOAD_TEST_PASSWORD` is set. Before this step run `LoadTestSeeder` if the database was reset, because the login check uses a load-test account that a reset removes (this was missed once, see below).
3. The script checks the `week-15` code out into a separate folder (`C:\dev\pfebsit002-rollback-week15`) with `git worktree`, links the existing `backend\vendor`, copies `.env.staging`, and starts the old code on 8001 against the SAME database.
4. Read the six checks, then Ctrl+C in the ROLLBACK API window, then `-Action Cleanup`.
5. Roll forward: `deploy\staging-release.ps1 -SkipBuild -KeepData`.

Counted result: `/up` 200, `/api/me` without a token 401, `/api/health/ready` 404, login 200, equipment 200, requests 200. Cleanup removed the worktree; `backend\vendor` stayed intact (37 folders). Roll-forward readiness 200 `{"status":"ok"}`. By design the script leaves the working folder, branch and uncommitted files alone (it uses a separate worktree); the developer did not report a problem. The time the whole rollback took was not recorded.

**Finding for the plan: the rollback looks like an outage to a readiness monitor.** The Week 15 code has no `/api/health/ready` route, so a probe on that path reads 404 (not 200) while users are served normally. While rolled back, watch `/up` and the app, and treat readiness 404 as expected. A real deployment would keep the readiness route in the rolled-back version or have the probe accept this case. Rolling forward while the Week 15 API was still on port 8001 printed 2 FAILs and "Staging is NOT ready": expected, not a fault.

Not counted, with causes: first Start (database reset removed the load-test account, so the login check failed; Claude had not said to run `LoadTestSeeder` first), a Start without `LOAD_TEST_PASSWORD` (login, equipment and requests checks skipped), two Starts stopped by the safety checks (Caddy not running; leftover folder). The first Cleanup runs left the folder in place (the old API window was probably still open; not confirmed); a later Cleanup removed it.

**What this does not show:** the Week 15 Postman collection was not run against the rolled-back code; only six checks were; no migration was involved, so a schema rollback was not tested (and none exists this week by design); the Flutter build was not rolled back (the script reports changed Dart files but the same release build was served).

## 4. Recovery from a MySQL outage (measured, no load)

`loadtest\recovery-poll.ps1 -Label recovery1` polled readiness about once per second (2026-11-15 about 01:00). 77 polls, 10 not-200, all readiness 503 with each failing poll taking about 8.3 to 8.6 s. Stop key 01:00:56.444 (XAMPP: stopped 1:01:00); first 503 recorded 01:01:09.494, 13.05 s after the key; start key 01:02:24.977 (XAMPP: running 1:02:28); first 200 at 01:02:28.937, 3.96 s after the key. `/up` stayed 200 in 0.05 to 0.27 s. php and Caddy were not restarted. No "MySQL shutdown unexpectedly" line, so this recovery counts as clean and replaces the unclean one from chunk 4b.

Limits: the keys were pressed 2 to 4 s before the XAMPP clicks; XAMPP's log has one-second resolution; a failing poll blocks about 8.4 s, so the recovery cannot be resolved finer than that; the poll is itself about one request per second. This was a normal Stop and Start. After a crash, MySQL needed longer: in chunk 4b it crashed twice after the Start click and ran again about 3 min 30 s later; `mysql_error.log` showed InnoDB crash recovery messages. That cause is not established.

If MySQL does not come back: read the last lines of `mysql_error.log`, do not delete data files, and use the latest dump in `C:\dev\pfebsit002-backups\` only after the scratch restore check passes.

## 5. Not rehearsed

A migration rollback or a destructive migration; restore over the live database; restore on another machine or from an off-machine copy; a rollback timed end to end; rollback of the Flutter build; recovery after a disk-full or a corrupted log; a second operator taking over.
