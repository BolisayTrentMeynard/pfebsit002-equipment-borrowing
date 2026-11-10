# Week 15 Evidence Index: Integration Testing and Security Hardening

Status (2026-11-10): In progress. Chunk 0 (planning and design documents) committed. Chunk 1 (Postman baseline) run: 149 tests, 137 passed, 12 failed; seven defects recorded (DEF-01 to DEF-07), none fixed yet. No application code has changed. Results for later chunks are "Planned" until the evidence round. Raw captures (screenshots, terminal output) are kept on Google Drive in the `Week 15` folder; the link is added when the week is done.

## Design (Part 1)
| File | What it is |
|------|------------|
| [`lab-log.md`](lab-log.md) | Date, branch, environment and tool versions, entries |
| [`scope.md`](scope.md) | Bounded test workflow, interfaces under test, non-goals |
| [`diagrams/test-architecture.png`](diagrams/test-architecture.png) (source `test-architecture.mmd`) | Test layers, trust zones, where each test attaches, human approval point |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-25 to AC-38 |
| [`traceability-matrix.md`](traceability-matrix.md) | Requirement to test to evidence to limitation (v2: Part E adds the baseline result) |
| [`decision-record-005-test-and-hardening-approach.md`](decision-record-005-test-and-hardening-approach.md) | Postman + PHPUnit + widget tests vs alternatives, and ranked weakness candidates W1 to W6 |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, resets, Postman file hygiene, capture hygiene |
| [`week15-failure-predictions.txt`](week15-failure-predictions.txt) | Predictions to commit before running (baseline, tests-first, adverse tests) |

## Chunk 1: Postman baseline (run, results recorded)
| File | What it is |
|------|------------|
| [`postman/equipment-borrowing-week15.postman_collection.json`](postman/equipment-borrowing-week15.postman_collection.json) | 73 requests (S-1 to S-6 and P-01 to P-67) in 12 folders, expected result in each description |
| [`postman/equipment-borrowing-local.postman_environment.json`](postman/equipment-borrowing-local.postman_environment.json) | Placeholders only; no token, key or password |
| [`postman/README.md`](postman/README.md) | How to run it, manual steps, hygiene |
| [`baseline-results.md`](baseline-results.md) | Expected vs actual per request for the counted run, run attempts, manual checks, defect list DEF-01 to DEF-07 |

## To be added in later chunks
Defect register, hardening report, test matrix with expected vs actual, interpretation and maintainability note, updated residual-risk register, screenshot list with file names.
