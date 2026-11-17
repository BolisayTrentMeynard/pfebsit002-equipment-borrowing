# Week 17 Evidence Index: Final Integration UAT and Evaluation

Status (2026-11-17): **Complete on `week-17-uat`.** All chunks committed and pushed through `4fc272c`. Remaining: merge `week-17-uat` into `main` and tag `week-17` (developer's decision only).

Drive folder link: to be added.

Scope of every result: one Windows PC, local staging, fake data, three testers. Nothing here says "production ready".

## Design (chunk 0)
| File | What it is |
|------|------------|
| [`lab-log.md`](lab-log.md) | Date, branch, environment, entries, threshold changes |
| [`scope.md`](scope.md) | Bounded workflow, the only allowed code additions, interfaces, non-goals, limits |
| [`uat-plan.md`](uat-plan.md) | Roles and decision authority, environment, data, entry and exit rules, facilitator rules, severity scale, stop rule |
| [`task-cards.md`](task-cards.md) | UAT-01 to UAT-10 cards and the five post-session questions |
| [`observation-sheet.md`](observation-sheet.md) | Run header, attempts table, ratings, acknowledgement and consent template (blank) |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-54 to AC-68 with frozen thresholds; Activity 1 (vague to measurable) |
| [`traceability-matrix.md`](traceability-matrix.md) | Requirement to check to evidence to limit (v1, plan) |
| [`decision-record-007-uat-approach.md`](decision-record-007-uat-approach.md) | Why a moderated session with three testers; rejected options |
| [`decision-record-008-architecture-alternatives.md`](decision-record-008-architecture-alternatives.md) | Options A, B, C, criteria and weights (scores come in chunk 2) |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, seeder guard, resets, backups, capture hygiene |
| [`week17-predictions.txt`](week17-predictions.txt) | Predictions for chunks 3 and 4, committed alone before the runs (Addendum 1 for chunk 3 is committed alone first) |

## Tester-independent documents (chunk 2, committed)
| File | What it is |
|------|------------|
| [`decision-record-008-architecture-alternatives.md`](decision-record-008-architecture-alternatives.md) | Now scored: A 3.70, B 3.71, C 2.53 (A and B tie at baseline), the sensitivity test, a conditional recommendation, disadvantages and what would change it (2a) |
| [`dr008-sensitivity.py`](dr008-sensitivity.py) and [`dr008-sensitivity-output.txt`](dr008-sensitivity-output.txt) | The script that computes every score and sensitivity figure, and its saved output (run by Claude; rerun by the developer on the PC, identical output) (2a) |
| [`diagrams/final-architecture.png`](diagrams/final-architecture.png) (source `final-architecture.mmd`) | Final architecture as built (Option A): people, Caddy, Flutter build, Laravel, MySQL, reminder path, Manual handling, operator tools (2b) |
| [`evaluation-matrix.md`](evaluation-matrix.md) | Skeleton of the final technical evaluation matrix: eight attributes, evidence that exists, evidence still to come, stated gaps; assessments empty until chunk 6a (2b) |
| [`maintenance-plan.md`](maintenance-plan.md) | Draft: roles, 14 routine tasks (frequency, how to verify, owner by role), contacts by role only, backlog B1 to B14 (2c) |
| [`change-control-notes.md`](change-control-notes.md) | Draft: kinds of change, rules for a fix and for a change request, commit habit, deciders, change log (2c) |
| [`handover-readme-draft.md`](handover-readme-draft.md) | Draft README for a new maintainer; replaces the root README only in chunk 6d (2c) |

## Pre-UAT readiness (chunk 3)
| File | What it is |
|------|------------|
| [`pre-uat-readiness.md`](pre-uat-readiness.md) | Filled-in record for 3a, 3b and 3c: expected values from Addendum 1, actual results (output or developer report), deviations D1 to D10, gate statement for the developer |
| [`test-data-plan.md`](test-data-plan.md) | Updated: the two account sets, the UAT item table, the card-to-item mapping, the UAT-08 mismatch (settled 2026-11-15: card and plan changed, not the seeder) |

## Decisions after chunk 3 (2026-11-15)
UAT-08 expects 3 items listed and 2 remaining (card and `uat-plan.md` section 5 changed; seeder unchanged); regression baseline is 120 / 15 / 149; operator notes added to `uat-plan.md` section 11; the integrity check runs before any reset in chunk 4. See `lab-log.md`.

## UAT session (chunk 4)
| File | What it is |
|------|------------|
| [`uat-session-record.md`](uat-session-record.md) | Session record, filled from the developer's report after the session: run header, entry checks, 12 planned attempts with their items, ratings, acknowledgements (sponsor pending), raw findings, the integrity check before any reset, gate. Empty cells are filled during the session; T1 to T3 only |

## Findings (chunk 5)
| File | What it is |
|------|------------|
| [`findings-register.md`](findings-register.md) | FIND-01 to FIND-06 with severity, type, scenario, evidence, proposed disposition and decision maker (AC-58); exit-rule and retest status (AC-59, AC-60); a decisions table for the developer to fill in |
| [`task-cards.md`](task-cards.md) | Revision section appended for UAT-05, UAT-06 and UAT-10 (FIND-04); the text used in the session is unchanged |

## Final assembly (chunk 6a and 6b)
| File | What it is |
|------|------------|
| [`evaluation-matrix.md`](evaluation-matrix.md) | Filled: eight attributes with assessments (2 Demonstrated, 6 Partly demonstrated), gaps and risks; test numbers are the 2026-11-15 results until the 6c rerun |
| [`residual-risk-register.md`](residual-risk-register.md) | The ONE merged register: R1 to R42 with status |
| [`handover-statement.md`](handover-statement.md) | Conditional handover statement (AC-66): what was shown, what was not, conditions; sponsor pending |
| [`contribution-record.md`](contribution-record.md) | Who did what; AI assistance; developer's own "hardest" line added (`4fc272c`) |
| [`evidence-map.md`](evidence-map.md) | AC-54 to AC-68 to files and results |
| [`maintenance-plan.md`](maintenance-plan.md) | Backlog now B1 to B16 (B15 and B16 from the findings) |

## Evidence round (chunk 6c)
| File | What it is |
|------|------------|
| [`test-matrix.md`](test-matrix.md) | AC-54 to AC-68 with expected result, actual, method, evidence and limit; the 2026-11-17 regression round (G1 to G7: backend 120 and Flutter 15 on the developer's word, Postman 149 of 149 in 1 m 34 s, three secret scans); checks run by Claude; predictions compared; runs that do not count; what was not run |
| [`interpretation-and-maintainability-note.md`](interpretation-and-maintainability-note.md) | What the results show and do not show; maintenance notes (including: run the scan with `-StartBytes`) |
| [`traceability-matrix.md`](traceability-matrix.md) | Results filled in (Part A and the new Part D) |
| [`evidence-map.md`](evidence-map.md), [`findings-register.md`](findings-register.md), [`residual-risk-register.md`](residual-risk-register.md), [`evaluation-matrix.md`](evaluation-matrix.md) | Updated with the rerun (no finding or assessment changed; R12 and R26 notes) |

## Final package (chunk 6d)
| File | What it is |
|------|------------|
| [`../../README.md`](../../README.md) | The root README, replaced from the handover draft: status banner, set-up, configuration, data model, API, reminders, tests, staging, limits, support by role, where things are, AI assistance |
| [`../INDEX.md`](../INDEX.md) | Evidence index, Week 17 row updated |
| [`handover-readme-draft.md`](handover-readme-draft.md) | The draft, now marked superseded and kept as history |
| [`contribution-record.md`](contribution-record.md) | Developer's own "hardest" line added; developer's review section removed by the developer |

## Chunk status
| Chunk | Status |
|-------|--------|
| 0 Design documents | Committed (`6c3849b`) |
| 1 OpenAPI, drift test, UAT seed | Committed and pushed (`7c3b435`, `ef569a2`, `33d12a0`); `php artisan test` 120 passed on the developer's MySQL |
| 2 Tester-independent documents (DR-008 scores, final diagram, evaluation matrix skeleton, maintenance plan, change control, handover README draft) | Committed and pushed (`bfdfa34` 2a, `32840ab` 2b, `07e1f6f` 2c); docs only, no test run |
| 3 Pre-UAT readiness (baseline, reminder path on staging, relay dry run) | Run 2026-11-15; record filled in; addendum committed alone (`06f72bd`); other files committed in `8228cf9` |
| 4 UAT session | Committed and pushed (`d0a2765`, `6163514`). Recording reviewed by the developer later the same day (AC-55 12 of 12, AC-56 within limits, AC-57 partly; his reading, not seen by Claude). Session run 2026-11-17 (about 12:46 to 13:16); `uat-session-record.md` filled from the developer's report (per-card notes not kept, recording is the source); integrity checks clean; FIND-01 explained (not a defect); all three testers accept; no sponsor yet |
| 5 Findings, fixes, retest, regression | Committed and pushed (`7180daa`, `cbd98d1`). Triage 2026-11-17: 6 findings, none Critical or High, one Medium proposed (records gap), no code fix proposed; dispositions approved 2026-11-17; regression not rerun (no code change), planned for chunk 6 |
| 6 Final assembly, evidence round, tag | 6a and 6b pushed (`8eb40a6`); 6c pushed (`bbca42c`, regression rerun 2026-11-17: backend 120 and Flutter 15 on the developer's word, Postman 149 of 149, scans explained); 6d pushed (`4fc272c`, root README replaced, contribution record complete). **Clean-clone check: not done** (developer's choice; AC-65 and R42 remain partly met). Open: sponsor (R37), merge to `main` and tag `week-17` (developer's decision) |
