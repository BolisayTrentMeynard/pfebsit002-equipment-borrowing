# Evidence Index: Week 13

Project: #5 Equipment Borrowing Reminder System. Topic: Advanced Automation and Scripting. Git tag: `week-13`.

Large files (screenshots, recordings) are on Google Drive: [3A final Submission, Week 13](https://drive.google.com/drive/folders/1Ng35RfI04woQm5cDN4HQqQACc6_zvmDm?usp=drive_link).


## 1. Design and planning (written before building)

| Item | File | What it shows | Date |
|------|------|---------------|------|
| Lab log | `evidence/week-13/lab-log.md` | Environment, versions, approved scope | 2026-10-29 |
| Scope | `evidence/week-13/scope.md` | Actors, trigger, inputs, steps, non-goals | 2026-10-29 |
| Workflow overview diagram | `evidence/week-13/diagrams/workflow-overview.png` | Borrowing workflow, automation flow, trust boundary, retry paths, human handling | 2026-10-29 |
| Earlier sequence diagram (kept) | `evidence/week-13/diagrams/workflow-sequence.png` (source `.mmd`) | First version of the diagram, replaced because it was hard to read | 2026-10-29 |
| Acceptance criteria | `evidence/week-13/acceptance-criteria.md` | Measurable criteria AC-01 to AC-14 | 2026-10-29 |
| Test data plan | `evidence/week-13/test-data-plan.md` | Fake data and environment check | 2026-10-29 |
| Screen flow | `evidence/week-13/flutter-screen-flow.md` | Flutter screens, widgets and states (Rounds A and B were built from this) | 2026-10-29 |

## 2. Decisions

| Item | File | What it shows | Date |
|------|------|---------------|------|
| Decision record 001 | `evidence/week-13/decision-record.md` | Queue jobs vs a single loop | 2026-10-29 |
| Decision record 002 | `evidence/week-13/decision-record-002-api-auth.md` | Custom hashed-token auth instead of Sanctum | 2026-10-29 |
| Activity 1 | `evidence/week-13/activity-1-architecture-decisions.md` | Triggers and patterns for 3 office workflows | 2026-10-29 |

## Contract and policy

| Item | File | What it shows | Date |
|------|------|---------------|------|
| API contract | `evidence/week-13/api-contract.md` | Endpoints, roles, inputs, outputs, error codes, status flows | 2026-10-30 |
| Retry and recovery policy | `evidence/week-13/retry-and-recovery-policy.md` | Trigger, limits, retries, recovery paths, logging, known gaps | 2026-10-30 |

## 3. Build and testing notes

| Item | File | What it shows | Date |
|------|------|---------------|------|
| Chunk 1 notes | `evidence/week-13/chunk-1-backend-testing.md` | Backend foundation: what was added, run steps | 2026-10-29 |
| Chunk 2 notes | `evidence/week-13/chunk-2-testing.md` | Reminder automation: what was added, run steps, limits | 2026-10-29 |
| Failure predictions and results | `evidence/week-13/week13-failure-predictions.txt` | Predictions written before the run (8:47 AM), then the actual results | 2026-10-29 |
| Activity 2 | `evidence/week-13/activity-2-failure-injection.md` | Failure injection results and controls | 2026-10-29 |

## 4. Results and interpretation

| Item | File | What it shows | Date |
|------|------|---------------|------|
| Test matrix | `evidence/week-13/test-matrix.md` | Expected vs actual for AC-01 to AC-14 (13 Pass, 1 Partial; AC-12 is Pass for logs only), failures kept in the record, open gaps | 2026-10-29 |
| Interpretation and maintainability note | `evidence/week-13/interpretation-and-maintainability-note.md` | What the results show and do not show, maintenance notes | 2026-10-29 |

## 5. Screenshots and recordings (on Google Drive, not in the repo)

| Item | Drive file | What it shows | Date |
|------|------------|---------------|------|
| Setup check | `week13-setup-laravel-flutter-running.png` | Laravel and Flutter running | 2026-10-28 |
| Registry proof | `week13-project-registry-proof.png` | Teacher confirmation of the registry letter | 2026-10-29 |
| Round A captures | Week 13 folder, file names as saved | Login, validation, wrong password, borrower desktop and narrow, approver manual handling, staff custody, empty state, network error | 2026-10-29 |
| Round B: tests | `week13-chunk3b-flutter-test.png` | `flutter test` passing | 2026-10-29 |
| Round B: validation | `week13-chunk3b-new-request-validation.png` | New request form validation | 2026-10-29 |
| Round B: full workflow | `week13-chunk3b-request-closed-history.png` | Closed request with full history | 2026-10-29 |
| Round B: narrow layout | `week13-chunk3b-narrow-detail.png` | Request detail at narrow width | 2026-10-29 |
| Round B: manual handling | `week13-chunk3b-manual-handling-resolve.png` | Failed item marked as handled | 2026-10-29 |
| AC-12 log search | `week13-ac12-log-search.png` | Search of `laravel.log` for Bearer, token and password: no matches | 2026-10-30 |

## Known gaps (details in the test matrix)

- AC-12 (no secrets in logs) was closed for logs on 2026-10-30: a search of `laravel.log` for `Bearer`, `token` and `password` found no matches. Response bodies were not searched systematically.
- AC-14 (Flutter states and widths) is Partial: the unauthorized, server-error and loading states are built but not captured.
- The notifier is a stand-in, so real email or SMS behavior is not shown.
