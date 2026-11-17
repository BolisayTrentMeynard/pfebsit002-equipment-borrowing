# Traceability Matrix (Week 17, v1 written 2026-11-15 before any run)

Every requirement is linked to the check that tests it. Result columns said "Planned" until the evidence round; Part A and the new Part D were filled on 2026-11-17 in chunk 6c (details in [`test-matrix.md`](test-matrix.md)). Layers: **S** = UAT scenario, **U** = PHPUnit, **P** = Postman (Week 15 collection, 149 tests), **F** = Flutter widget test, **L** = lint or script run by Claude, **M** = manual check by the developer, **D** = document review.

## Part A: Earlier criteria kept in the regression set
| Requirement | Existing checks | Week 17 role | Result |
|-------------|-----------------|--------------|--------|
| AC-01 to AC-24 (Weeks 13 and 14) | `php artisan test` | U after every code change | Passed: 120 backend tests on 2026-11-17 (developer's word; summary line not sent) |
| AC-25 to AC-38 (Week 15) | Postman 149, `php artisan test`, `flutter test` (15) | P on staging before and after fixes | Passed: Postman 149 of 149, 1 m 34 s (Runner screenshot seen); `flutter test` 15 and `php artisan test` 120 (developer's word) |
| AC-39 to AC-53 (Week 16) | `php artisan test` (120 passed, 750 assertions; was 86 before chunk 1), staging scripts, scans | U and scans after fixes; k6 only if a fix touches the request path | Passed: 120 backend tests; scans: new log region clean (exit 0), whole file exit 1 for known old entries (G5 to G7); k6 not rerun |

## Part B: Week 17 criteria
| Requirement (source) | AC | Check | Layer | Planned evidence | Known limit |
|----------------------|----|-------|-------|------------------|-------------|
| Approved UAT plan and scenarios (module Topic 1) | AC-54 | Files and commit order | D | `uat-plan.md`, `task-cards.md`, predictions commit | None |
| Representative scenarios, borrower to closure (module, guide #5) | AC-55, AC-56 | UAT-01 to UAT-05 | S | Observation sheets | 3 testers, one PC |
| Invalid, unauthorized, failure and recovery paths (guide #5) | AC-57 | UAT-06 to UAT-09 | S, U | Sheets, logs | Unauthorized access is tested technically in Week 15, not by testers |
| Findings and dispositions (Topic 2) | AC-58, AC-59 | Register | D | `findings-register.md` | Depends on what testers find |
| Retest of approved fixes; regression (module Part 3) | AC-60 | Original scenario plus regression set | S, U, P, F | Terminal output, Runner | k6 not rerun unless needed |
| Signed or acknowledged result record (Topic 2) | AC-61 | Acknowledgement sheet | D | Decisions in the repo; sheet private | Sponsor may be pending |
| OpenAPI documentation (Topic 5, guide) | AC-62 | Lint, drift test, Postman | L, U, P | `backend/openapi.yaml` | Hand-written; the drift test checks routes and about 6 response shapes, not every field |
| Final technical evaluation matrix (Topic 3) | AC-63 | Link check | D, L | `evaluation-matrix.md` | Gaps are listed, not hidden |
| Architecture alternatives and recommendation (Topic 4, Activity 2) | AC-64 | DR-008 | D | Weights committed before scores | Paper comparison |
| Documentation, handover, maintenance (Topic 5) | AC-65 | Handover checklist | D | README, maintenance plan | Written by the author; checked by a fresh reviewer |
| Conditional handover statement | AC-66 | Wording check | D | `handover-statement.md` | None |
| No secrets or personal data (module quality checks) | AC-67 | Scans and review | M, D | Scan output | Faces and voices avoided by design |
| Desktop and narrow checks, keyboard use, validation agreement (module Part 3) | AC-68 | Screenshots and walk | M | Screens, notes | Flutter draws on a canvas; automated accessibility tools see little |

## Part C: Carried risks that Week 17 touches
| Risk | Week 17 handling |
|------|------------------|
| R32 Nothing runs `schedule:run`; worker by hand | UAT scenarios say so; chunk 3 proves the reminder path on staging; B in DR-008 |
| R20 Single-threaded server | Not changed; DR-008 compares A, B, C |
| R15 CORS regex | Backlog unless a finding asks for it |
| R14 Manual-handling recipient fields | Looked at when OpenAPI documents the answer; any change is a finding with a disposition |
| R18 Week 15 tests A, B, C not run | UAT-07 and UAT-09 cover wrong password and API stopped in the app |

## Part D: Week 17 results (chunk 6c, 2026-11-17)
| AC | Result | Where |
|----|--------|-------|
| AC-54 | Met | [`test-matrix.md`](test-matrix.md) |
| AC-55 | Met on the developer's reading of the recording (12 of 12) | same |
| AC-56 | Not measured (no stopwatch); reported within limits | same |
| AC-57 | Partly met (UAT-06 case 2 not exercised) | same |
| AC-58, AC-59 | Met; the decider is the developer | same |
| AC-60 | Met: no fix to retest; regression passed; scan explained | same |
| AC-61 | Met as worded; sponsor pending | same |
| AC-62, AC-63, AC-64, AC-66 | Met | same |
| AC-65 | Partly met (no fresh reviewer, no clean-clone run) | same |
| AC-67 | Met within the scan limits | same |
| AC-68 | Done per the developer's report; not verified by Claude | same |
