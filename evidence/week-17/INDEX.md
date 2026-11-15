# Week 17 Evidence Index: Final Integration UAT and Evaluation

Status (2026-11-15): **In progress.** Chunk 0 (design documents) is committed (`6c3849b`; predictions alone in `a67e08b`). Chunk 1 (OpenAPI, drift test, UAT seeder) is committed and pushed (`7c3b435`, `ef569a2`, `33d12a0`, per the developer). Chunk 2 (the tester-independent documents below) is committed and pushed (`bfdfa34`, `32840ab`, `07e1f6f`); docs only, the developer reran the DR-008 script and the output matched. Nothing here has been run with testers. Chunks 3 to 6 are not started.

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
| [`week17-predictions.txt`](week17-predictions.txt) | Predictions for chunks 3 and 4, committed alone before the runs |

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

## Chunk status
| Chunk | Status |
|-------|--------|
| 0 Design documents | Committed (`6c3849b`) |
| 1 OpenAPI, drift test, UAT seed | Committed and pushed (`7c3b435`, `ef569a2`, `33d12a0`); `php artisan test` 120 passed on the developer's MySQL |
| 2 Tester-independent documents (DR-008 scores, final diagram, evaluation matrix skeleton, maintenance plan, change control, handover README draft) | Committed and pushed (`bfdfa34` 2a, `32840ab` 2b, `07e1f6f` 2c); docs only, no test run |
| 3 Pre-UAT readiness | Not started |
| 4 UAT session | Not started |
| 5 Findings, fixes, retest, regression | Not started |
| 6 Final assembly, evidence round, tag | Not started |
