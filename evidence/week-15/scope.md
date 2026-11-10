# Week 15 Scope: Integration Testing and Hardening (draft, 2026-11-10)

Same project, same registered scope (#5 Equipment Borrowing Reminder System). Week 15 adds no feature and no route. It tests what exists, records what fails, fixes the most important weaknesses and shows that nothing else broke.

## Bounded workflow of the tests
| Part | Content |
|------|---------|
| Actors | Developer (tester), borrower, approver and staff accounts (fake seed data), the Postman collection, the PHPUnit suite, the Flutter widget tests |
| Trigger | The developer runs a collection, a test suite or a manual adverse test |
| Trusted inputs | Fake seed data (`DatabaseSeeder`, `DemoScenarioSeeder`), the test cases written in this folder |
| Validation | Every request has its expected status and body written before it is run; actual results are recorded next to it |
| Steps | Baseline run, defect record, hardening, rerun and regression, evidence |
| Outputs | Collection JSON, defect register, hardening report, test matrix, screenshots on Drive |
| External dependencies | MySQL, Caddy (HTTPS), the receiver endpoint used by the notifier |
| Non-goals | New features, performance or load testing (Week 16), deployment (Week 16), OpenAPI document (Week 17), automated vulnerability scanners, penetration of anything outside the developer's own PC |

## Interfaces under test
| ID | Interface | Layer used |
|----|-----------|------------|
| I1 | REST API `/api/*` (login, requests, custody, manual handling, equipment) | Postman and PHPUnit |
| I2 | Notifier receiver `POST /api/notifier/receive` | Postman (cross-reference) and `SignedMessageTest` |
| I3 | Queue worker to receiver over HTTPS (timeout, dependency failure) | PHPUnit (`Http::fake`) and a manual Caddy-stopped run |
| I4 | Flutter Web screens and their API states | `flutter test` and manual browser checks at desktop and narrow width |

## Out of scope for fixing (backlog, documented only)
CORS accepts any localhost port (development setting), `$guarded = []` on three internal models, a race on double submit (see `decision-record-005-test-and-hardening-approach.md`).
