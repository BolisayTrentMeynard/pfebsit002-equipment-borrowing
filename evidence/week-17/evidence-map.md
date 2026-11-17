# Evidence Map (Week 17, chunk 6b; Week 18 reuses it)

Written 2026-11-17 by Claude. **Docs only. Built, untested.** "Result" says what the record shows today, with its limit. The chunk 6c results (rerun on 2026-11-17) are in this version; the full table is in [`test-matrix.md`](test-matrix.md). Earlier weeks are in their own indexes.

## Week 17 criteria
| AC | What | Where | Result on 2026-11-17 |
|----|------|-------|----------------------|
| AC-54 | UAT plan, cards, data, thresholds, predictions committed first | [`uat-plan.md`](uat-plan.md), [`task-cards.md`](task-cards.md), [`acceptance-criteria.md`](acceptance-criteria.md), [`week17-predictions.txt`](week17-predictions.txt) | Met: predictions alone in `a67e08b`, documents in `6c3849b` |
| AC-55 | 80% of attempts without help | [`uat-session-record.md`](uat-session-record.md) | 12 of 12 per the developer's reading of the recording (threshold 10); no sheets |
| AC-56 | Time on task | same | Not measured (no stopwatch); reported within limits by the developer |
| AC-57 | Error recovery | same | Partly: UAT-07, 08, 09 and UAT-06 cases 1 and 3; case 2 not exercised |
| AC-58 | Findings register | [`findings-register.md`](findings-register.md) | FIND-01 to FIND-06, all with dispositions, none deleted |
| AC-59 | Exit rule | same | No Critical or High; Medium and Low have dispositions; the decider is the developer (no sponsor) |
| AC-60 | Retest and regression | [`test-matrix.md`](test-matrix.md) (G1 to G7) | No code fix, so no retest. Regression rerun 2026-11-17: 120 and 15 (developer's word), Postman 149 of 149 in 1 m 34 s (screenshot seen), scans explained |
| AC-61 | Acknowledgement | session record | Three testers accept (developer's report); sponsor pending |
| AC-62 | OpenAPI | [`../../backend/openapi.yaml`](../../backend/openapi.yaml), `OpenApiDriftTest` | Met per the developer's runs; the drift test is inside the 120 that passed on 2026-11-17; Redocly not rerun |
| AC-63 | Evaluation matrix | [`evaluation-matrix.md`](evaluation-matrix.md) | Met: 8 attributes, links checked by Claude (0 broken) |
| AC-64 | Architecture alternatives | [`decision-record-008-architecture-alternatives.md`](decision-record-008-architecture-alternatives.md) | Met: weights before scores, sensitivity test, conditional recommendation |
| AC-65 | Handover package | [`../../README.md`](../../README.md) (replaced 2026-11-17 from [`handover-readme-draft.md`](handover-readme-draft.md)), [`maintenance-plan.md`](maintenance-plan.md), [`change-control-notes.md`](change-control-notes.md) | Partly met: documents exist; no clean-clone run and no fresh reviewer yet |
| AC-66 | Handover statement | [`handover-statement.md`](handover-statement.md) | Written; conditional; sponsor pending |
| AC-67 | Secrets and personal data | [`test-matrix.md`](test-matrix.md) (G5 to G7) | Met within the scan limits; whole-file scan exit 1 for known old entries; screenshots and the recording not checked by Claude |
| AC-68 | Usability basics (developer only) | [`pre-uat-readiness.md`](pre-uat-readiness.md) | Done per the developer's reports; screenshots not seen by Claude |

## Other Week 17 files
| File | What it is |
|------|------------|
| [`scope.md`](scope.md), [`test-data-plan.md`](test-data-plan.md), [`observation-sheet.md`](observation-sheet.md), [`traceability-matrix.md`](traceability-matrix.md) | Plan, data, blank sheet, requirement-to-check map (results added in 6c) |
| [`decision-record-007-uat-approach.md`](decision-record-007-uat-approach.md) | Why a moderated session with three testers |
| [`residual-risk-register.md`](residual-risk-register.md) | The ONE merged register, R1 to R42 |
| [`contribution-record.md`](contribution-record.md) | Who did what; AI assistance |
| [`diagrams/final-architecture.png`](diagrams/final-architecture.png) | Final architecture as built (Option A) |
| [`test-matrix.md`](test-matrix.md), [`interpretation-and-maintainability-note.md`](interpretation-and-maintainability-note.md) | Results of AC-54 to AC-68 and the regression round; what the results show and do not show |
| [`lab-log.md`](lab-log.md), [`INDEX.md`](INDEX.md) | Dated log with every commit; file index |

## Not in the repo (private, on Drive or the developer's PC)
The screen recording, the signed acknowledgement sheet, the private script with names, and the raw screenshots. The repo holds only T1 to T3 and the decisions.
