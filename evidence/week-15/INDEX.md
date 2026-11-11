# Week 15 Evidence Index: Integration Testing and Security Hardening

Status (2026-11-11): Done. Chunks 0 to 3 and 5 are committed on `week-15-hardening` (chunk 4, the optional browser adverse tests A to C, was not run; the test matrix says so). Results: baseline 149 Postman tests, 137 passed, 12 failed; after the fixes 149 passed, 0 failed; `php artisan test` 47 to 61 passed; `flutter test` 5 to 15 passed; nine defects (DEF-01 to DEF-09) fixed; 13 criteria Pass and 1 Partial (AC-34). Raw captures (screenshots, terminal output) are on Google Drive in the `Week 15` folder; the link is added below once uploaded.

Drive folder link: _not added yet_

## Design (Part 1)
| File | What it is |
|------|------------|
| [`lab-log.md`](lab-log.md) | Date, branch, environment and tool versions, entries |
| [`scope.md`](scope.md) | Bounded test workflow, interfaces under test, non-goals |
| [`diagrams/test-architecture.png`](diagrams/test-architecture.png) (source `test-architecture.mmd`) | Test layers, trust zones, where each test attaches, human approval point |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-25 to AC-38 |
| [`traceability-matrix.md`](traceability-matrix.md) | Requirement to test to evidence to limitation (v3: Part F adds the final results) |
| [`decision-record-005-test-and-hardening-approach.md`](decision-record-005-test-and-hardening-approach.md) | Postman + PHPUnit + widget tests vs alternatives, ranked weaknesses, updates after the baseline and after the fixes |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, resets, Postman file hygiene, capture hygiene |
| [`week15-failure-predictions.txt`](week15-failure-predictions.txt) | Predictions committed before each run, with actual results added underneath |

## Tests and baseline
| File | What it is |
|------|------------|
| [`postman/equipment-borrowing-week15.postman_collection.json`](postman/equipment-borrowing-week15.postman_collection.json) | 73 requests (S-1 to S-6 and P-01 to P-67) in 12 folders, expected result in each description |
| [`postman/equipment-borrowing-local.postman_environment.json`](postman/equipment-borrowing-local.postman_environment.json) | Placeholders only; no token, key or password |
| [`postman/README.md`](postman/README.md) | How to run it, manual steps, hygiene |
| [`baseline-results.md`](baseline-results.md) | Expected vs actual per request for the counted baseline run, run attempts, manual checks, first seven defects |

## Results and interpretation
| File | What it shows |
|------|---------------|
| [`test-matrix.md`](test-matrix.md) | Expected vs actual for AC-25 to AC-38 (13 Pass, 1 Partial), deviations from the predictions, runs that do not count, what was not run |
| [`defect-register.md`](defect-register.md) | DEF-01 to DEF-09: cause, priority, fix, retest, regression |
| [`hardening-report.md`](hardening-report.md) | Before and after table, why these fixes, what it does not show |
| [`interpretation-and-maintainability-note.md`](interpretation-and-maintainability-note.md) | What the results show and do not show, maintenance notes |
| [`residual-risk-register.md`](residual-risk-register.md) | Current register: R7, R8, R10 and R12 updated, R13 to R19 added |

## Code changed in Week 15
Backend: `backend/app/Http/Middleware/SecurityHeaders.php`, `backend/app/Http/Controllers/Api/AuthController.php` (per-email login limit), `backend/app/Http/Controllers/Api/BorrowRequestController.php` (audit fields, denial log), `backend/bootstrap/app.php` (headers middleware, generic 404), `backend/routes/api.php` (digit-only ids), tests in `backend/tests/Feature/HardeningTest.php` (14 tests). App: `app/lib/screens/home_screen.dart`, `app/lib/widgets/remote_list.dart`, `app/lib/screens/request_detail_screen.dart`, `app/lib/screens/new_request_screen.dart`, tests in `app/test/states_test.dart` (10 tests).

## Commits (as reported by the developer from the terminal)
| Commit | What |
|--------|------|
| `fbf4159`, `c23f8a2`, `9fc31cf` | Chunk 0: predictions alone, design documents, tool versions |
| `fbb8504`, `d482e1d` | Chunk 1: predictions addendum alone (15:34:02 +0800), then the Postman baseline and results |
| `b9c9f77`, `941a336`, `a17a13e` | Chunk 2: predictions addendum alone (17:01:16), tests (nine failing), fixes (61 passing) |
| `b2758f8`, `1a3c001` | Chunk 3: predictions addendum alone (17:18:45), Flutter tests and fixes (15 passing) |
| `03a633f` | Chunk 5: predictions addendum alone, before the rerun (2026-11-11) |

## Raw captures (on Drive, not in the repo)
Planned names are `week15-<chunk>-<what>.png`. The groups are:

- **Chunk 0:** predictions commit, tool versions (Flutter 3.47.8, Dart 3.13.7, Postman 12.30.4), documents commit and push.
- **Chunk 1:** predictions-first commit and time; setup (HTTPS `/up`, environment placeholders, S-1 login pass); runs (run history, the counted run's summary and failed tab, rate-limit result); manual checks (expired token, audit rows seen by a borrower, W6 log searches); `flutter test` +5; secret scan before commit.
- **Chunk 2:** predictions commit; tests-first failures (AC-28/29, AC-31, AC-32, AC-33) and the 9 failed / 52 passed summary and test list; after fix 61 passed; header check by curl (including the failed first tries); fix commit.
- **Chunk 3:** predictions commit; first `flutter test` (13 passed, 2 failed), the 401 and narrow-overflow failures; after fix +15; commit; desktop (about 1280 px) and narrow (360 px) "My requests".
- **Chunk 5:** predictions commit `03a633f`; `migrate:fresh --seed`; the not-counted 10:21 run; the counted run (149 passed); run history; `flutter test` +15; `php artisan test` 61 passed; the AC-20 and AC-24 scan output.
- **Kept out on purpose:** three screenshots that showed a live dev token, an API key or a phone number. Keep `.env` closed and Postman login response bodies closed in anything uploaded.

## Known gaps
- Test A (expired token with the app open), test B (Caddy stopped during a collection run) and test C (lockout in the app) were not run; see `test-matrix.md` for what covers them.
- AC-34 is Partial: the 5 s notifier timeout has no test of its own; the Caddy-stopped runs are from Week 14.
- No desktop or narrow screenshot of the new request form (widget test only).
- Recipient fields on the manual-handling list not reviewed (R14).
- One rerun attempt (10:21 on 2026-11-11) failed for an unknown reason; the next run was clean.
- The Drive link above is not filled in yet. The Chrome version line in `lab-log.md` is still the placeholder.
