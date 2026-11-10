# Week 15 Evidence Index: Integration Testing and Security Hardening

Status (2026-11-10): In progress. Chunk 0 (planning and design documents) written. No test has been run and no code has changed yet. Every result below is "Planned" until the evidence round. Raw captures (screenshots, terminal output) are kept on Google Drive in the `Week 15` folder; the link is added when the week is done.

## Design (Part 1)
| File | What it is |
|------|------------|
| [`lab-log.md`](lab-log.md) | Date, branch, environment and tool versions, entries |
| [`scope.md`](scope.md) | Bounded test workflow, interfaces under test, non-goals |
| [`diagrams/test-architecture.png`](diagrams/test-architecture.png) (source `test-architecture.mmd`) | Test layers, trust zones, where each test attaches, human approval point |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-25 to AC-38 |
| [`traceability-matrix.md`](traceability-matrix.md) | Requirement to test to evidence to limitation (v1, all planned) |
| [`decision-record-005-test-and-hardening-approach.md`](decision-record-005-test-and-hardening-approach.md) | Postman + PHPUnit + widget tests vs alternatives, and ranked weakness candidates W1 to W6 |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, resets, Postman file hygiene, capture hygiene |
| [`week15-failure-predictions.txt`](week15-failure-predictions.txt) | Predictions to commit before running (baseline, tests-first, adverse tests) |

## To be added in later chunks
Postman collection and environment file (`postman/`), defect register, hardening report, test matrix with expected vs actual, interpretation and maintainability note, updated residual-risk register, screenshot list with file names.
