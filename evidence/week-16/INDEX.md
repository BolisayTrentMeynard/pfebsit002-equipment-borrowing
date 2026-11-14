# Week 16 Evidence Index: Performance, Reliability and Deployment

Status (2026-11-14): In progress. Chunk 0 (design documents) is committed and pushed. Chunk 1 (readiness, request log, `ops:status`, load-test seeder, notifier timeout test, staging env example) is written and waits for the developer's run. Nothing has been measured yet; every result column in the matrices says "Planned". Raw captures (screenshots, terminal output, k6 summaries) will be on Google Drive in the `Week 16` folder; the link is added below once uploaded.

Drive folder link: _not added yet_

## Design (Part 1)
| File | What it is |
|------|------------|
| [`lab-log.md`](lab-log.md) | Date, branch, environment and tool versions, entries |
| [`scope.md`](scope.md) | Bounded workflow, the only allowed code additions, interfaces, non-goals, stated limits |
| [`diagrams/deployment-and-monitoring.png`](diagrams/deployment-and-monitoring.png) (source `deployment-and-monitoring.mmd`) | Staging layout, test tools, monitoring signals, owner, rollback path |
| [`workload-models.md`](workload-models.md) | Ordinary and peak models (Activity 1), journeys, thresholds, supporting runs, integrity checks, limits |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-39 to AC-53 and the thresholds table |
| [`traceability-matrix.md`](traceability-matrix.md) | Requirement to test to evidence to limitation (v1, all "Planned") |
| [`decision-record-006-performance-and-deployment-approach.md`](decision-record-006-performance-and-deployment-approach.md) | k6, local staging, single-threaded server, paced login, rollback; rejected alternatives; ranked bottleneck candidates |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, seeder guard, resets, backups, capture hygiene |
| [`week16-failure-predictions.txt`](week16-failure-predictions.txt) | Predictions for chunks 3 to 5, committed before each run, with actual results added underneath later |

## Code changed in Week 16 (chunk 1, built, not yet run by the developer)
Backend: `backend/app/Http/Controllers/Api/HealthController.php`, `backend/app/Http/Middleware/LogApiRequest.php`, `backend/app/Console/Commands/OpsStatus.php`, `backend/config/loadtest.php`, `backend/database/seeders/LoadTestSeeder.php`, `backend/bootstrap/app.php` (log middleware), `backend/routes/api.php` (readiness route), `backend/.env.staging.example`, `backend/.gitignore` and `make-clean-zip.bat` (`.env.staging` kept out). Tests: `backend/tests/Feature/OperationsTest.php`, `OperationsDownTest.php`, `NotifierTimeoutTest.php` (21 tests).

## Still to come (added as the chunks are done)
Staging release, environment comparison, deployment checklist (chunk 2); k6 scripts, sampling script and results sheet (chunk 3); failure test and incident walkthrough (chunk 4); fixes, regression, restore and rollback (chunk 5); test matrix, monitoring map, rollback plan, readiness recommendation, interpretation note, updated risk register (chunk 6).

## Commits (as reported by the developer from the terminal)
_None yet._

## Raw captures (on Drive, not in the repo)
Planned names are `week16-<chunk>-<what>.png`. Chunk 0: predictions commit (with time), tool versions (k6, Flutter, PHP, MySQL, Caddy), documents commit and push.
