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
- 2026-11-15: Chunk 2 (2a, 2b, 2c) written by Claude in one go at the developer's request, although the plan said one segment per chat: DR-008 scores and sensitivity test (weights unchanged from `6c3849b`), `dr008-sensitivity.py` and its output, `diagrams/final-architecture.mmd` and `.png`, `evaluation-matrix.md` (skeleton), `maintenance-plan.md`, `change-control-notes.md`, `handover-readme-draft.md`, and updates to this log and to `INDEX.md`. **Built, untested by Claude on the developer's stack.** What Claude ran in its sandbox: the Python script (Python 3, standard library), `mmdc` to render the diagram (with the sandbox's Chromium), a link check and a claims check on the new files. Redocly, PHPUnit, MySQL, Flutter and Windows were not used for this chunk. DR-008 result: A 3.70, B 3.71, C 2.53; the order of A and B flips under many weight changes, so the record recommends by purpose, not by the 0.01.
- 2026-11-15: Chunk 2 decisions 1 to 6 (DR-008 scores, recommendation, maintenance frequencies, contacts by role only, keep the Python script as evidence, handover README stays a draft until chunk 6d) approved by the developer in chat. No score was changed.
- 2026-11-15: Chunk 2 committed and pushed (`bfdfa34` 2a, `32840ab` 2b, `07e1f6f` 2c; push `33d12a0..07e1f6f`). Docs only, so no test run; the chunk 1 result (120 passed on MySQL) is unchanged. The developer reran `dr008-sensitivity.py` on the PC: `Compare-Object` against the saved output printed nothing (identical), totals A 370, B 371, C 253 (3.70, 3.71, 2.53). `git status --short` printed nothing after the commits.
- 2026-11-15: Chunk 2 follow-up committed and pushed: `4218418` (`lab-log.md`, `INDEX.md` and DR-008 updated with the three hashes and the approvals; push `07e1f6f..4218418`).
- 2026-11-15: Chunk 3 (3a, 3b, 3c) written by Claude in one go at the developer's request ("build all chunk 3, no a b c"), although the plan says one segment per chat. Files: `week17-predictions.txt` (Addendum 1 appended, original text unchanged; to be committed ALONE first), `pre-uat-readiness.md` (blank record), `test-data-plan.md` (UatSeeder facts, proposed card-to-item mapping, open mismatch), this log and `INDEX.md`. **Docs only. Built, untested.** Claude ran nothing in the project and has no PowerShell or Windows; the developer runs every step. Claude found by reading (not running) that `UatSeeder` pre-makes one failed notification while `uat-plan.md` section 5 and the UAT-08 card assume none, and that the seeder makes one pending request where the plan said two; both are recorded in `test-data-plan.md` for the developer's decision. The baseline in Addendum 1 is 120 passed, not the 86 still written in D11.
- 2026-11-15: Chunk 3 run by the developer on the PC. Predictions addendum committed alone as `06f72bd` first. 3a: 120 passed (750 assertions), 15 Flutter tests passed, Postman 149/149 (reported), secrets scan exit 0, backup restore 14 tables same. 3b: reminder path worked on staging at the first try (SENT with certificate; FAILED_NEEDS_HUMAN, attempts 3, with a closed-port URL; Manual handling backlog 3, failed jobs 2). 3c: relay run recorded alone (6:04), all ten cards reported passed, keyboard, 200% zoom and 1280/360 px screenshots reported done. Deviations D1 to D10 are in `pre-uat-readiness.md` (largest: integrity check not run before the final reset; UAT-08 mismatch confirmed at 3 items). Claude saw only the pasted terminal output; the recording, screenshots and Postman Runner are the developer's report. Committed and pushed by the developer as `8228cf9` (4 files, 130 insertions, 13 deletions; push `06f72bd..8228cf9`).

## Threshold changes after freezing
None yet. Any change is added here with its date, reason and the old value.
