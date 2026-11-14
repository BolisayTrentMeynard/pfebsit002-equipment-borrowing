# Week 16 Evidence Index: Performance, Reliability and Deployment

Status (2026-11-15): **Done** (chunks 0 to 6). Chunks 0 to 5 are committed and pushed on `week-16-deployment`. This evidence round (chunk 6) was written by Claude from the files in the clean zip, the developer's terminal output and screenshots and the progress notes; Claude ran no code. Raw captures (screenshots, terminal output, k6 summaries) will be on Google Drive in the `Week 16` folder; the link is added below once uploaded.

Drive folder link: _not added yet_

Scope of every number: one Windows PC, `php artisan serve` (single-threaded) behind Caddy, k6 and the sampler on the same PC. The recommendation is conditional and never says "production ready".

## Start here
| File | What it is |
|------|------------|
| [`test-matrix.md`](test-matrix.md) | AC-39 to AC-53: expected, actual, method, result (12 Pass, 3 Partial), deviations, runs that do not count, what was not run |
| [`readiness-recommendation.md`](readiness-recommendation.md) | Conditional recommendation, conditions, limits and the tabletop review of an incomplete release package (AC-52, Activity 2) |
| [`performance-results.md`](performance-results.md) | Baseline, ordinary, peak and spike (OPcache off), then the before and after rerun (OPcache on) |
| [`interpretation-and-maintainability-note.md`](interpretation-and-maintainability-note.md) | What the results show and do not show; maintenance notes |
| [`residual-risk-register.md`](residual-risk-register.md) | Current register: updated rows and R20 to R34 |

## Design (Part 1)
| File | What it is |
|------|------------|
| [`lab-log.md`](lab-log.md) | Date, branch, environment and tool versions, entries |
| [`scope.md`](scope.md) | Bounded workflow, the only allowed code additions, interfaces, non-goals, stated limits |
| [`diagrams/deployment-and-monitoring.png`](diagrams/deployment-and-monitoring.png) (source `deployment-and-monitoring.mmd`) | Staging layout, test tools, monitoring signals, owner, rollback path |
| [`workload-models.md`](workload-models.md) | Ordinary and peak models (Activity 1), journeys, thresholds, supporting runs, integrity checks, limits |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-39 to AC-53 and the thresholds table |
| [`traceability-matrix.md`](traceability-matrix.md) | Requirement to test to evidence to limitation (plan, chunk status, final results in Part G) |
| [`decision-record-006-performance-and-deployment-approach.md`](decision-record-006-performance-and-deployment-approach.md) | k6, local staging, single-threaded server, paced login, rollback; rejected alternatives |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, seeder guard, resets, backups, capture hygiene |
| [`week16-failure-predictions.txt`](week16-failure-predictions.txt) | Predictions for chunks 1 to 5, committed before each run, with actual results underneath |

## Deployment and operations
| File | What it is |
|------|------------|
| [`environment-comparison.md`](environment-comparison.md) | Dev, local staging and a production target on paper, plus findings from the runs (AC-46) |
| [`deployment-checklist.md`](deployment-checklist.md) | Setup, one-command release, checks after release, stop and reset (AC-47) |
| [`monitoring-map.md`](monitoring-map.md) | Signals, thresholds, owner, decision table, MySQL-outage walkthrough with actuals, no-load detection and recovery (AC-45) |
| [`rollback-and-recovery-plan.md`](rollback-and-recovery-plan.md) | Backup and restore, rollback to `week-15`, MySQL recovery, what was not rehearsed (AC-51) |
| [`postman/README.md`](postman/README.md) and `postman/equipment-borrowing-staging.postman_environment.json` | How to run the unchanged Week 15 collection against staging (placeholders only) |

## Failure test
| File | What it is |
|------|------------|
| [`failure-test-results.md`](failure-test-results.md) | MySQL stopped during the ordinary workload (chunk 4) and a second outage with no load (chunk 4b): timelines, tables, manual checks, data, log scan, misses (AC-48) |

## Code and scripts added in Week 16
- **Backend (chunks 1 and 5):** `HealthController`, `LogApiRequest` middleware, `OpsStatus` command, `config/loadtest.php`, `LoadTestSeeder`, `bootstrap/app.php` (request log and the failed-query log fix), `config/database.php` (optional `DB_CONNECT_TIMEOUT`), `routes/api.php` (readiness route), `backend/.env.staging.example`. Tests: `OperationsTest`, `OperationsDownTest`, `NotifierTimeoutTest`, `Week16Chunk5Test` (86 tests in the full suite).
- **Deploy (chunks 2 and 5):** `deploy/Caddyfile.staging`, `staging-release.ps1`, `backup-restore.ps1`, `rollback-rehearsal.ps1`, `scan-secrets.ps1`.
- **Load and recovery (chunks 3 to 5):** `loadtest/` (`k6/*.js`, `sample-resources.ps1`, `integrity-check.sql`, `integrity-check-extra.sql`, `scan-outage-log.ps1`, `db-fail-timing.php`, `measure-latency.ps1`, `recovery-poll.ps1`, `README.md`).
- **Not in the repo:** the OPcache setting (`C:\xampp\php\php.ini`), the real `.env` and `.env.staging`, the dump in `C:\dev\pfebsit002-backups\`, the k6 and sampler result files in `loadtest\results\` (git-ignored).

## Commits (as reported by the developer from the terminal; branch `week-16-deployment`)
| Chunk | Commits |
|-------|---------|
| 0 | `690b3f6` (predictions, 2026-11-14 14:46:59 +0800), `3bf8143` (12 files) |
| 1 | `8b2c87e` (predictions, 15:04:24), `39cbf47` (17 files) |
| 2 | `921aa1a` (11 files; the predictions addendum commit was not shown to Claude) |
| 3 | `80d7792`, `62c7b5d`, `2cde512` (addendum 2, 18:35:55), `860c2a9`, `51762b3` |
| 4 and 4b | `c25e6ec` (19:43:49), `f1f8940`, `74e1025` (21:02:00), `26771c1`, `b75c011`, `8a41e6e` |
| 5 | `8a02169` (2026-11-14 23:53:06), `792d9b6` (2026-11-15 01:34:18), `d6fdf1c` (01:37:24) |
| 6 | _to be added by the developer after committing this evidence round_ |

## Known gaps (said openly)
- AC-42 Partial: the peak target is missed before and after the change; endurance not run.
- AC-50 Partial: one performance fix (OPcache); P1, P2, P3 not shown, P5 not measured.
- AC-51 Partial: the Week 15 Postman collection was not run on the rolled-back code; restore shown on an almost empty database; rollback time not recorded.
- The volume, 20 minute and queue-worker-stopped runs were cut (C3); the spike was not repeated after the change.
- The log fix is proven by PHPUnit, not by a live outage.
- The php CPU of the "after" runs was not compared (CSVs on the PC, not read by Claude).
- Unexplained: Apache and MySQL stop and start events in chunks 4 and 4b, and the two MySQL crashes after the 4b Start.
- Some captures were taken but not shown to Claude (first release run with the Flutter build, new-request screenshots, the Postman Runner summary, the `login` Request URL).
- CPU model and Chrome version lines are still placeholders.

## Raw captures (on Drive, not in the repo)
Planned names are `week16-<chunk>-<what>.png`, filed by Claude when the developer sends the sorted folder. Keep the Authorization header, `.env`, `.env.staging`, the load-test password, the dev seed password and the dump out of every frame.
