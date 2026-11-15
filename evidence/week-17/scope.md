# Week 17 Scope: UAT, Documentation and Final Evaluation (draft, 2026-11-15)

Same project, same registered scope (#5 Equipment Borrowing Reminder System). Week 17 adds no feature. It puts the system in front of people in the three roles, records what happens, fixes what the developer accepts, documents the API and the architecture decision, and ends with a conditional handover statement. Scope must not change without written approval.

## Bounded workflow of this week
| Part | Content |
|------|---------|
| Actors | Three testers (borrower, approver, staff), the developer as facilitator and operator, an optional sponsor (instructor or lab custodian) |
| Trigger | The developer starts a pre-UAT check, a UAT session, a fix or a documentation chunk |
| Trusted inputs | Fake data from `DatabaseSeeder` and the guarded `UatSeeder`; task cards and thresholds written and committed before the session |
| Validation | Predictions committed first; actual results written underneath; every help counted; integrity checks after the session |
| Steps | Design documents, OpenAPI and drift test, tester-independent documents, pre-UAT readiness, the session, findings and fixes, final assembly |
| Outputs | UAT plan and cards, observation records, findings register, OpenAPI file, DR-007 and DR-008, evaluation matrix, maintenance plan, handover package and statement, merged risk register, test matrix |
| External dependencies | MySQL (XAMPP), Caddy, the receiver endpoint used by the notifier, the testers' time |
| Non-goals | New features or screens, real e-mail or SMS, real data, production hosting, multi-user load tests, a real office acceptance, calling the prototype production ready |

## The only code additions allowed (decision D7)
1. `backend/openapi.yaml` and a PHPUnit drift test (no application code change).
2. A guarded `UatSeeder` (fake data; refuses outside local, staging and testing).
3. Fixes for findings the developer accepts: Critical and High always, Medium and Low only if small, at most 5 in total, tests first. No migration and no `composer.lock` change if it can be avoided.
Anything else is recorded as a change request or backlog.

## Interfaces under review
| ID | Interface | Layer used |
|----|-----------|------------|
| I1 | Flutter Web on staging (`https://localhost:9443`) | UAT session, keyboard, zoom and narrow checks |
| I2 | REST API `/api/*` | OpenAPI, drift test, Postman rerun |
| I3 | Reminder path: scheduler command, queue worker, signed notifier, Manual handling | Chunk 3 proof, UAT-08 |
| I4 | Release, backup and rollback scripts | Week 16 scripts, used before the session |

## Stated limits (said again in the evidence)
One Windows PC; testers use the developer's browser; three testers; fake data; `php artisan serve` is single-threaded; reminders and the queue worker are started by hand. The results describe this setup and these people only.
