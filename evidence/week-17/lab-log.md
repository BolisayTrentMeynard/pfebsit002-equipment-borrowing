# Week 17 Laboratory Log

| Item | Value |
|------|-------|
| Date started | 2026-11-15 (chunk 0: design documents) |
| Branch | `week-17-uat` |
| Project | #5 Equipment Borrowing Reminder System (registered, see Week 13 log) |
| Backend | Laravel 12, PHP 8.2.12 (XAMPP), MySQL (XAMPP, MariaDB) |
| Frontend | Flutter Web (3.47.8 stable, Dart 3.13.7), Chrome 157.0.8103.51 |
| HTTPS | Caddy: dev `https://localhost:8443`, staging `https://localhost:9443` |
| Machine | 13th Gen Intel Core i5-13420H, 15.7 GB RAM, Windows 11 25H2 (from Week 16) |
| Approved scope | See `scope.md`. No new features. Scope must not change without written approval |

## Entries
- 2026-11-15: Plan approved by the developer (second, stress-tested version; D1 to D13). It was first asked as "week 16", but Week 16 was already done, so Week 17 was planned. Nothing built.
- 2026-11-15: Chunk 0 files written by Claude from the module, the project guide, the log and the code in the 2026-11-15 clean zip (nothing run): predictions, scope, UAT plan, task cards, observation sheet, acceptance criteria AC-54 to AC-68 (thresholds frozen on commit), traceability matrix v1, DR-007, DR-008 skeleton (weights only, no scores), test data plan, this log, INDEX.
- Predictions commit: `a67e08b`, 2026-11-15 13:10:36 +0800 (`week17-predictions.txt` alone, 1 file, 32 insertions). Chunk 0 documents followed in `6c3849b`.
- Tester arrangements, session date and sponsor: not decided yet (not recorded here by design; no names in the repo).
- 2026-11-15: Chunk 1 committed and pushed (`7c3b435`, `ef569a2`, `33d12a0`) and tested by the developer on MySQL (120 passed, 750 assertions), as the developer reported in the progress notes.
- 2026-11-15: Chunk 2 (2a, 2b, 2c) written by Claude in one go at the developer's request, although the plan said one segment per chat: DR-008 scores and sensitivity test (weights unchanged from `6c3849b`), `dr008-sensitivity.py` and its output, `diagrams/final-architecture.mmd` and `.png`, `evaluation-matrix.md` (skeleton), `maintenance-plan.md`, `change-control-notes.md`, `handover-readme-draft.md`, and updates to this log and to `INDEX.md`. **Built, untested, not committed.** What Claude ran in its sandbox: the Python script (Python 3, standard library), `mmdc` to render the diagram (with the sandbox's Chromium), a link check and a claims check on the new files. Redocly, PHPUnit, MySQL, Flutter and Windows were not used for this chunk. DR-008 result: A 3.70, B 3.71, C 2.53; the order of A and B flips under many weight changes, so the record recommends by purpose, not by the 0.01.

## Threshold changes after freezing
None yet. Any change is added here with its date, reason and the old value.
