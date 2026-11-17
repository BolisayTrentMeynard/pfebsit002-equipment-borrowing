# Acceptance Criteria (Week 17)

Numbering continues from Week 16 (AC-39 to AC-53). **Thresholds are frozen when this file is committed in chunk 0.** Any change afterwards is written in `lab-log.md` with the date and the reason, and the old value stays visible. Actual results go in the test matrix in the evidence round.

"Attempt" means one tester doing one scenario once (planned: about 11 attempts, see `uat-plan.md`). The thresholds count attempts, not people. With 3 testers they show that those 3 people could do those tasks; they do not show acceptance by an office.

"Help" means any hint beyond reading the task card again. Reading the card again is not help. Every help is written on the observation sheet.

## Criteria
| ID | Category | Given / When | Expected result (frozen) | Decision maker | Checked by |
|----|----------|--------------|--------------------------|----------------|------------|
| AC-54 | UAT plan | Before any session | Scope, roles, scenarios, task cards, data, environment, entry and exit rules, severity scale, stop rule and these thresholds are written and committed; predictions are committed alone | Developer | `uat-plan.md`, commit order |
| AC-55 | Completion | UAT-01 to UAT-10 on staging | At least 80% of attempts (9 of 11; if the count differs, 80% rounded up) are completed without help | Tester of that role | Observation sheets |
| AC-56 | Time on task | First-time testers, stopwatch from the end of reading the card | New request 3 min or less; approve or reject 2 min or less; release 2 min or less; return and inspection 2 min or less. A miss is a finding, not hidden | Tester of that role | Observation sheets |
| AC-57 | Error recovery | UAT-06, UAT-07, UAT-08, UAT-09 | In each, the tester says in their own words what went wrong, and recovers without help (corrects the form, signs in again, finds the failed notification and handles it, presses Try again) | Tester of that role | Observation sheets |
| AC-58 | Findings register | After the session | Every finding has an ID (FIND-01 and up), severity, type, scenario, evidence, disposition and decision maker. No finding is deleted | Developer | `findings-register.md` (chunk 5) |
| AC-59 | Exit rule | After fixes and retests | No Critical or High finding is open. Every Medium and Low has a disposition (fix, defer with reason, change request, won't fix, not a defect) | Sponsor if available, else developer (stated) | Findings register |
| AC-60 | Retest and regression | After the approved fixes | Each fix is retested with its original scenario. `php artisan test`, `flutter test` and the Postman collection (149) pass on staging; `scan-secrets.ps1` is rerun | Developer | Terminal output, Postman Runner |
| AC-61 | Acknowledgement | End of the session | Each tester role records accept, accept with conditions or reject, with initials and date; the sponsor does the same or the record says "pending". The sheet stays private; the repo holds only the decisions | Each role; sponsor | Acknowledgement record |
| AC-62 | OpenAPI | Chunk 1 and after any API fix | `backend/openapi.yaml` is valid OpenAPI 3.1.x and lint-clean; every `api/*` route is documented and no documented path is missing in the code (drift test); auth, roles and the error answers (401, 403, 404, 409, 422, 429, 500, 503) are documented; examples are fake | Developer | Redocly lint, `OpenApiDriftTest` |
| AC-63 | Evaluation matrix | Chunk 6 | Each quality attribute (interoperability, access control, secure exchange, reliability, performance, usability, maintainability, operational readiness) has evidence links and stated gaps; every link resolves; an absence of observed failure is not written as proof of safety | Developer | `evaluation-matrix.md`, link check |
| AC-64 | Architecture alternatives | Chunk 2 | Three options are scored against weights committed before any score; a sensitivity test shifts the weights; the recommendation names its disadvantages and what would change it | Developer | DR-008, commit order |
| AC-65 | Handover package | Chunk 6 | A new maintainer can set up, run, test, monitor and roll back from the README and the documents; the maintenance plan has task, frequency, how to verify and owner by role; change-control notes and known limits are present; contacts are roles only | Developer | Handover checklist |
| AC-66 | Handover statement | Chunk 6 | The statement is conditional, never says "production ready", and separates what was demonstrated from what is future operational work | Developer (sponsor acknowledges if available) | `handover-statement.md` |
| AC-67 | Secrets and personal data | After Week 17 changes | The Week 14, 15 and 16 scans are rerun; no tester name, e-mail, face or voice is in the repo, screenshots or recordings; every session record has a run header | Developer | Scan output, review of the files |
| AC-68 | Usability basics (not UAT) | Chunk 3, developer only | Principal screens at 1280 px and 360 px; a keyboard-only walk of sign-in and New request; 200% zoom on the principal flows; form rules and server rules agree (the same input is rejected by both, with understandable messages) | Developer | Screenshots, notes |

## Activity 1 in practice: vague statement to measurable criterion
| Vague statement | Measurable version | Where |
|-----------------|--------------------|-------|
| "The app should be easy to use." | At least 80% of attempts completed without help (AC-55), with times within the limits in AC-56 | AC-55, AC-56 |
| "Errors should be clear." | In each adverse scenario the tester states the cause in their own words and recovers without help | AC-57 |
| "The system should be reliable." | A permanently failed reminder reaches Manual handling and is resolved by the approver (UAT-08); a stopped API gives a message and recovery by Try again (UAT-09); integrity checks are clean after the session | AC-57, test matrix |
| "The API should be documented." | Valid OpenAPI 3.1.x, lint clean, no route missing or extra (drift test), error answers documented | AC-62 |
| "It should be ready for the office." | A conditional statement with named conditions; never "production ready"; the sponsor acknowledges or "pending" is written | AC-61, AC-66 |
| "Testing should be thorough." | Every finding has a disposition; no Critical or High open; every fix retested with its scenario and the regression set passes | AC-58, AC-59, AC-60 |

## Who decides what
The tester of a role decides whether that role's tasks are acceptable (accept, accept with conditions, reject). The sponsor (an instructor or lab custodian, if one is available) decides the overall exit (AC-59) and acknowledges the handover statement (AC-66). If no sponsor is available the record says "pending" and the developer's decisions are marked as the developer's, not the office's.
