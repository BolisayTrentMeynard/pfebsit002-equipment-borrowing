# Week 16 Laboratory Log

| Item | Value |
|------|-------|
| Date started | 2026-11-14 (chunk 0: design documents) |
| Branch | `week-16-deployment` |
| Project | #5 Equipment Borrowing Reminder System (registered, see Week 13 log) |
| Backend | Laravel 12, PHP 8.2.12 (XAMPP), Composer 2.10.2, MySQL (XAMPP, MariaDB) |
| Frontend | Flutter Web, run in Chrome |
| HTTPS | Caddy v2.11.8 (dev: `https://localhost:8443` in front of `http://127.0.0.1:8000`; staging: `https://localhost:9443`, `deploy/Caddyfile.staging`, API on `http://127.0.0.1:8001`) |
| Load tool | k6 v2.4.0 (per developer's `k6 version` screenshot, 2026-11-14) |
| Flutter version | Flutter 3.47.8 (stable), Dart 3.13.7 (as in Week 15; not upgraded on purpose) |
| Chrome version | _developer: chrome://version_ |
| Git | 2.55.0.windows.3 |
| OS | Windows (developer PC) |
| Approved scope | See `scope.md`. Only the small code additions listed there. Scope must not change without written approval. |

Note: the registry approval covers the project title only. It does not allow real institutional data, production credentials or production infrastructure.

## Entries

- 2026-11-11: Plan approved by the developer (D1 to D13). Nothing built.
- 2026-11-14: Plan re-confirmed after Claude rechecked it against the code. Four changes approved (C1 login for load tests, C2 liveness is `/up`, C3 cut order, C4 list fix keeps the `data` key). k6 installed.
- 2026-11-14: Chunk 0 files written by Claude from the plan and the code in the 2026-11-11 clean zip: predictions, scope, workload models, acceptance criteria AC-39 to AC-53, traceability matrix v1, DR-006, test data plan, deployment and monitoring diagram, this log, the INDEX. Nothing was built, run or measured. Every result column says "Planned".
- 2026-11-14: Chunk 1 code written by Claude (not yet run by the developer): `HealthController` (`GET /api/health/ready`), `LogApiRequest` middleware (`http.request` line, global, API paths only), `ops:status` command, `config/loadtest.php` and `LoadTestSeeder`, `.env.staging.example`, 21 new tests in `OperationsTest.php`, `OperationsDownTest.php` and `NotifierTimeoutTest.php`. Claude ran the whole suite in its own sandbox with SQLite (82 passed) and smoke-tested the endpoints and the log line with `php artisan serve` on a SQLite file; it did not use MySQL, Caddy or Windows. Chunk 1 predictions addendum drafted (to be committed alone before the developer's run).
- 2026-11-14: Found while writing `.env.staging.example`: `make-clean-zip.bat` only skipped a file named exactly `.env`, so a real `.env.staging` would have gone into the clean zip. The script now also skips `.env.staging` and `.env.production`, and `backend/.gitignore` ignores `.env.staging`.
- 2026-11-14: Chunk 2 files written by Claude (not run, no PHP, Flutter, Caddy or PowerShell in its sandbox): `deploy/Caddyfile.staging`, `deploy/staging-release.ps1`, `evidence/week-16/environment-comparison.md`, `deployment-checklist.md`, `postman/` (staging environment file and run guide), staging notes in `backend/.env.staging.example`, README section. Staging port fixed at 9443 (API 8001). The release script asks Laravel for the effective environment and database name before any reset; this was checked against the Laravel 12 source (`APP_ENV` selects `.env.staging` and passes through `artisan serve`; `config:show` prints one value). Chunk 2 predictions addendum drafted (to be committed alone before the developer's run). Compression left off on purpose so P5 can be measured in chunk 3.
- Predictions commit: _hash and time to be added by the developer after committing the predictions file alone_.
- Chrome version line above is still the placeholder.
