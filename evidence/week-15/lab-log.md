# Week 15 Laboratory Log

| Item | Value |
|------|-------|
| Date started | 2026-11-10 (chunk 0: planning and design documents) |
| Branch | `week-15-hardening` |
| Project | #5 Equipment Borrowing Reminder System (registered, see Week 13 log) |
| Backend | Laravel 12, PHP 8.2.12 (XAMPP), Composer 2.10.2, MySQL (XAMPP, MariaDB) |
| Frontend | Flutter Web, run in Chrome |
| HTTPS | Caddy v2.11.8, `https://localhost:8443` in front of `http://127.0.0.1:8000` |
| API test tool | Postman (desktop app, installed by the developer) |
| Flutter version | Flutter 3.47.8 (stable), Dart 3.13.7, DevTools 2.60.0 (per developer's `flutter --version` screenshot) |
| Postman version | 12.30.4 (per the release-notes tab in the developer's screenshot) |
| Chrome version | _developer: chrome://version_ |
| Git | 2.55.0.windows.3 |
| OS | Windows (developer PC) |
| Approved scope | See `scope.md`. No new features or routes in Week 15. Scope must not change without written approval. |

Note: the registry approval covers the project title only. It does not allow real institutional data, production credentials or production infrastructure.

## Entries

- 2026-11-10: Plan approved by the developer. Part 1 documents written (scope, diagram, acceptance criteria AC-25 to AC-38, traceability matrix v1, decision record DR-005, test data plan, failure predictions). Nothing built or run yet. Every result column in the matrix says "Planned".
- 2026-11-10: Branch `week-15-hardening` created. Failure predictions committed alone as `fbf4159` at 15:09:32 +0800 (per developer's terminal output), before any test run.
- 2026-11-10: Chunk 1 files written by Claude from the code (not run, not tested): Postman collection (67 requests) and environment (placeholders only), `postman/README.md`, `baseline-results.md`, predictions addendum, traceability Part D. The baseline run, `php artisan test` and `flutter test` counts are done by the developer and recorded in `baseline-results.md`.
- 2026-11-10: First full-run attempt NOT counted. Postman stopped at P-17 with a script error. Causes: borrower1 already had 6 requests from an earlier partial run (P-15 saw 6, P-17 got 422 for a duplicate open request), and the collection stored an id outside `pm.test` so a failed response crashed the script (collection bug, fixed by Claude; precondition S-6 added). The data must be reset before every run.
- 2026-11-10: Predictions addendum for chunk 1 committed alone as `fbb8504` (per developer's terminal output) before the baseline run.
- 2026-11-10: Baseline runs in Postman (per developer's screenshots): 15:50:26 (127 tests, stopped), 15:52:58 (10 tests), 15:54:13 (44 tests, script error), then the counted run at 15:58:25: 149 tests, 137 passed, 12 failed, 0 errors, 2m 21s. Only the last run counts. The collection has 73 requests (S-1 to S-6, P-01 to P-67); the earlier entry that says 67 counted only the tests.
- 2026-11-10: Result of the counted run: weaknesses W1, W2, W3, W4, W7 and W8 confirmed by failed checks; W6 confirmed by two log searches that found no trace line (a control search found the other `auth.forbidden` lines). Expired-token steps matched the prediction: M-1 200, expiry set in the past with tinker (`true`), M-2 401, `auth.rejected` log line with no token. `php artisan test` 47 passed; `flutter test` 5 passed; `APP_DEBUG=false`. Defects DEF-01 to DEF-07 recorded in `baseline-results.md`; nothing fixed yet.
- 2026-11-10: Claude wrote the filled-in `baseline-results.md` from the developer's screenshots (not from a run). Claude first misread the log's correlation ids as a different format because a terminal screenshot was cropped; corrected after the P-49 body and the next log lines showed full UUIDs.
- 2026-11-10: Flutter reports a newer version is available; not upgraded on purpose (the evidence records Flutter 3.47.8).
- Chrome version line above is still the placeholder (`chrome://version`).
