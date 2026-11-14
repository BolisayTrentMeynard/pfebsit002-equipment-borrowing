# Week 16 Scope: Performance, Reliability and Deployment (draft, 2026-11-14)

Same project, same registered scope (#5 Equipment Borrowing Reminder System). Week 16 adds no feature for users. It measures how the system behaves under stated workloads, shows what happens when a dependency fails, prepares a repeatable local staging release with a rollback, and ends with a conditional readiness recommendation. Scope must not change without written approval.

## Bounded workflow of this week
| Part | Content |
|------|---------|
| Actors | Developer (operator and tester), fake borrowers, approver and staff accounts, k6 as the load generator, a PowerShell sampling script, Caddy, Laravel API, MySQL |
| Trigger | The developer starts a staging release, a k6 run, a failure test, a backup or a rollback |
| Trusted inputs | Fake data from `DatabaseSeeder` and the guarded `LoadTestSeeder`; workload models in `workload-models.md`; thresholds in `acceptance-criteria.md` (written before any run) |
| Validation | Each run has its workload, thresholds and prediction written first; actual results are recorded next to them; the data is checked for corruption after each run |
| Steps | Design documents, small code additions with tests, staging release, baseline and load runs, failure test, fixes with before and after, backup and rollback, evidence round |
| Outputs | k6 scripts, resource sampling script, results sheet, monitoring map, environment comparison, deployment checklist, rollback plan, readiness recommendation, screenshots on Drive |
| External dependencies | MySQL (XAMPP), Caddy (HTTPS and static files), the receiver endpoint used by the notifier |
| Non-goals | New user features or screens, real hosting or a cloud account, real institutional data, auto-scaling, a multi-server setup, a real endurance or soak test, an OpenAPI document (Week 17), full user acceptance testing (Week 17) |

## The only code additions allowed (decision D7)
1. `GET /api/health/ready`: checks the database and the queue table. Body holds only ok or fail. 200 when ready, 503 when not. Liveness is the existing Laravel `/up`, not a new route.
2. One `http.request` log line per API call: route name, status, milliseconds, correlation id. No email, name, token, body or query string.
3. Artisan command `ops:status`: a short text summary for the operator (database reachable, jobs waiting, failed jobs, items waiting for manual handling).
4. `LoadTestSeeder`: refuses to run unless the environment is local, staging or testing and a password is supplied from the environment.
5. A PHPUnit test for the notifier timeout (closes the Week 15 gap AC-34).
6. Bottleneck fixes only where measurements confirm them (D9), tests first, and the Flutter app and the Week 15 collection must keep working unchanged (C4).
Anything else is out of scope and is recorded as backlog.

## Interfaces under test
| ID | Interface | Layer used |
|----|-----------|------------|
| I1 | REST API `/api/*` under load | k6 scripts |
| I2 | Health endpoints `/up` and `/api/health/ready` | k6 monitor scenario, PHPUnit, manual during the failure test |
| I3 | Flutter Web release build served by Caddy on the same origin | Browser checks at desktop and narrow width |
| I4 | Queue worker to receiver (timeout and failure) | PHPUnit with `Http::fake`; optional manual worker-stopped run |
| I5 | Release, backup, restore and rollback scripts | Developer runs and captures |

## Stated limits of this setup (said again in the evidence)
One Windows PC runs the load generator and the system under test, so they compete for the same CPU. The API runs on `php artisan serve`, which is single-threaded; real servers run several workers. Cache, queue and sessions use the database. All numbers describe this setup only. A pass here is not a promise for a real deployment.
