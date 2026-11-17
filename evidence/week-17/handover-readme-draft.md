# Handover README (DRAFT, Week 17 chunk 2c)

> **Superseded on 2026-11-17 (chunk 6d):** the root `README.md` now holds this text with root-relative links, the 2026-11-17 test results and the AI-assistance statement. This draft is kept as history and is no longer updated.

> **Status: draft for a new maintainer.** Written 2026-11-15 by Claude from the repository files and the Week 13 to 17 evidence. It does **not** replace the root `README.md` yet; that happens in chunk 6d, after the findings and fixes, when the links below are changed to root-relative ones. **Not tested from a fresh clone.** The setup steps are the developer's own steps from the root README, reorganised. Where a number comes from the developer's run, it says so. This draft never calls the system production ready.

# Equipment Borrowing Reminder System

A small borrowing workflow for shared school equipment, built as a student project (PFEBSIT 002, project #5): a Laravel API and a Flutter Web app. A borrower asks for an item, an approver accepts or rejects, staff release it with a due date, reminders go out before the due date, overdue items go to the approver, and staff record the return. A notification that keeps failing goes to a person (Manual handling) instead of retrying forever.

## 1. What this is, and what it is not
- **Is:** a working prototype for about 5 users on one PC, with fake data, tested by the developer and (Week 17) by three testers.
- **Is not:** a production system. Peak load misses its target, there is no alert, the scheduler and the queue worker are started by hand, the database account has no password and the certificate is a local development one. The full list is in section 11 and in [`maintenance-plan.md`](maintenance-plan.md) (backlog B1 to B14).
- **Data:** fake only. No real person, institution or record belongs in this repository, screenshots or logs.

## 2. Architecture in one picture
See [`diagrams/final-architecture.png`](diagrams/final-architecture.png) (source `final-architecture.mmd`). In words: the browser talks HTTPS to Caddy; Caddy serves the Flutter build and proxies `/api` and `/up` to Laravel; Laravel reads and writes MySQL; a by-hand scheduler command queues reminder jobs; a by-hand queue worker sends each reminder as a signed message back through Caddy to Laravel's receiver and records the result. Why this shape, and what a hardened shape would be: [`decision-record-008-architecture-alternatives.md`](decision-record-008-architecture-alternatives.md).

## 3. What you need
Windows PC (the developer's), PHP 8.2 or newer with the usual Laravel extensions, Composer, MySQL or MariaDB (XAMPP works), Git, Flutter with Chrome, Caddy (for HTTPS). Versions the developer used: PHP 8.2.12, Composer 2.10.2, MariaDB 10.4.32 (XAMPP), Flutter 3.47.8 with Dart 3.13.7, Caddy 2.11.8. k6 (2.4.0) is needed only for the load tests in `loadtest/`.

## 4. Set up for development
Create two empty databases once: `equipment_borrowing` (the app) and `equipment_borrowing_test` (`php artisan test`).

From `backend/`:
```
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```
API: `http://127.0.0.1:8000`. From `app/`: `flutter pub get`, then `flutter run -d chrome --web-port 9595` (the app calls `https://localhost:8443/api` by default, so run Caddy first):
```
caddy reverse-proxy --from localhost:8443 --to 127.0.0.1:8000 --disable-redirects
```
To use plain HTTP instead: `flutter run -d chrome --web-port 9595 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api`. Demo accounts (borrower, approver, staff) are fake and come from `backend/database/seeders/DatabaseSeeder.php`.

## 5. Configuration
Copy `backend/.env.example` to `.env` (never commit it). Each name must appear once; if repeated, the first line wins. After a change run `php artisan config:clear` and restart the server and the worker.

| Name | Meaning | Note |
|------|---------|------|
| `APP_ENV` | `local`, `staging` or `testing` | Staging is selected by setting `APP_ENV=staging` in the shell; Laravel then reads `backend/.env.staging` instead of `.env` |
| `APP_DEBUG` | Debug pages | `false` on staging (the release script refuses anything else) |
| `LOG_LEVEL` | Log detail | `info` on staging |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection | Dev `equipment_borrowing`, staging `equipment_staging`; XAMPP `root` with no password is a stated weakness |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `database` for all three | No Redis |
| `NOTIFIER_DRIVER` | `fake` (default) or `signed_http` | `signed_http` sends signed messages to `NOTIFIER_URL` |
| `NOTIFIER_URL` | Receiver address | Dev `https://localhost:8443/api/notifier/receive`; staging `https://localhost:9443/api/notifier/receive` |
| `NOTIFIER_SIGNING_KEY`, `NOTIFIER_SIGNING_KEY_PREVIOUS` | HMAC key (32+ characters) and the old key during rotation | Create with `php artisan notifier:generate-key`; staging needs its own key; never commit or screenshot |
| `NOTIFIER_TOLERANCE_SECONDS` | Accepted age of a message | 300 |
| `NOTIFIER_CA_BUNDLE` | Path to Caddy's `root.crt` | Needed by a queue worker; single quotes around the path |
| `NOTIFIER_TRANSIENT_TAGS`, `NOTIFIER_ALWAYS_FAIL_TAGS` | Demo failure switches | Demos only; leave empty otherwise |
| `UAT_PASSWORD` | Password of the four fake UAT accounts | Set in the shell only (12+ characters); read by `config/uat.php` |
| `LOAD_TEST_PASSWORD` | Same idea for the load-test accounts | Shell only |

Reminder rules (in `backend/config/reminders.php`): remind when due within 24 hours; at most 3 reminders and 2 escalations per request and recipient; no second notice of the same kind within 20 hours; 3 attempts per notification with waits of 5 s and 15 s; maximum loan 7 days.

## 6. Data model
| Table | Holds | Key columns |
|-------|-------|-------------|
| `users` | Accounts | `role`: `borrower`, `approver` or `staff` |
| `api_tokens` | Login tokens | Only the SHA-256 hash is stored; `expires_at` (8 hours), `last_used_at` |
| `equipment` | Items | `asset_tag` unique; `status`: `available`, `on_loan`, `needs_check` |
| `borrow_requests` | The loan | `status`: `PENDING`, `APPROVED`, `REJECTED`, `RELEASED`, `RETURNED`, `CLOSED`; `requested_days` 1 to 7; `due_at`; `inspection_result` |
| `audit_logs` | Every state change | `action`, `from_status`, `to_status`, `correlation_id`, `user_id` (null = system) |
| `reminder_notifications` | Reminders and escalations | `type`: `reminder` or `overdue`; `status`: `PENDING`, `SENT`, `SENT_AFTER_RETRY`, `FAILED_NEEDS_HUMAN`, `HANDLED_MANUALLY`; `attempts`, `last_error` |

Framework tables (`jobs`, `failed_jobs`, `cache`, `sessions`) come with Laravel. Request flow: PENDING, then APPROVED or REJECTED; APPROVED, then RELEASED by staff (sets the due date); RELEASED, then RETURNED and CLOSED when staff record the return and inspection.

## 7. The API
The contract is [`backend/openapi.yaml`](../../backend/openapi.yaml): OpenAPI 3.1.1, 16 operations, bearer-token scheme, the real error answers (401, 403, 404, 409, 422, 429 with `Retry-After`, 500, 503) and fake examples. `OpenApiDriftTest` fails if a route and the file disagree. Lint it with the Redocly CLI from `backend/`, where its ignore file `.redocly.lint-ignore.yaml` lives (one accepted problem: `GET /health/ready` has no 4xx answer to document). The developer's chunk 1 run used Redocly 1.34.5; the exact command was not recorded in the files Claude read, so confirm it before relying on this line.

| Access | Operations |
|--------|------------|
| Public | `GET /api/health/ready`, `POST /api/login` (10 per minute per IP and 5 failed per email per minute) |
| Signature, not a user | `POST /api/notifier/receive` (headers `X-Message-Id`, `X-Message-Timestamp`, `X-Message-Signature`) |
| Any signed-in role | `GET /api/me`, `POST /api/logout`, `GET /api/equipment`, `GET /api/requests`, `GET /api/requests/{id}` (a borrower sees only their own) |
| Borrower | `POST /api/requests` |
| Approver | `POST /api/requests/{id}/approve`, `/reject`; `GET /api/manual-handling`; `POST /api/manual-handling/{id}/resolve` |
| Staff | `POST /api/requests/{id}/release`, `/return` |
| Approver and staff | `GET /api/custody` |

Ids are digits only (`/requests/1abc` is 404). Every `api/*` answer carries `Cache-Control: no-store, private`, `X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer`, and no `X-Powered-By`. Known gap: unmatched `/api/*` paths have no `X-Correlation-Id`.

## 8. Run the reminders
From `backend/`, in separate windows, once the signed notifier is configured:
```
php artisan db:seed --class=DemoScenarioSeeder
php artisan reminders:run
php artisan queue:work
```
Leave `queue:work` running about 40 seconds so retries can finish; stop it with Ctrl+C; do not use `--stop-when-empty`. Nothing runs `schedule:run`, so `reminders:run` (daily 08:00 in `routes/console.php`) and `custody:summary` (17:00) are started by hand. On staging the worker needs `NOTIFIER_CA_BUNDLE`; the staging reminder path is proven in chunk 3 of Week 17, not before.

## 9. Test
```
cd backend && php artisan test      (uses equipment_borrowing_test)
cd app && flutter test
```
Last results reported by the developer: `php artisan test` 120 passed (750 assertions) on 2026-11-15 after chunk 1; `flutter test` 15 passed; the Postman collection 149 of 149 on staging in Week 16 (not rerun since). Postman: import `evidence/week-15/postman/equipment-borrowing-week15.postman_collection.json` and the staging environment `evidence/week-16/postman/equipment-borrowing-staging.postman_environment.json`; steps in [`../week-16/postman/README.md`](../week-16/postman/README.md). Reset staging before every run. The secret scan is `deploy\scan-secrets.ps1`. Load tests (k6) are in `loadtest/README.md` and are only rerun when a change touches the request path.

## 10. Staging, monitoring, backup and rollback
Local staging is a second environment on the same PC: database `equipment_staging`, `APP_ENV=staging`, Flutter release build and Caddy on `https://localhost:9443`, API on `127.0.0.1:8001`.

- **Set up and release:** [`../week-16/deployment-checklist.md`](../week-16/deployment-checklist.md). One command from a standalone PowerShell window in the repo root: `powershell -NoProfile -ExecutionPolicy Bypass -File deploy\staging-release.ps1` (`-SkipBuild` reuses the last build; `-KeepData` skips the reset).
- **UAT data (fake):** from `backend\`, `$env:APP_ENV='staging'`, `$env:UAT_PASSWORD='<12+ characters>'`, `php artisan db:seed --class=UatSeeder`, then remove both variables. The seeder refuses outside local, staging and testing, and running it again resets the UAT items. The pattern follows the load-test seeder in the load-test README; see [`test-data-plan.md`](test-data-plan.md).
- **Watch:** `GET /up` (liveness, no database), `GET /api/health/ready` (database and queue table; a failing answer can take 8 to 53 s), `php artisan ops:status` (needs `APP_ENV=staging` in the shell), the `http.request` lines in `backend/storage/logs/laravel.log`. Thresholds and what to do: [`../week-16/monitoring-map.md`](../week-16/monitoring-map.md).
- **Back up:** `deploy\backup-restore.ps1` (dump outside the repo, scratch restore, table comparison).
- **Roll back:** `deploy\rollback-rehearsal.ps1` to the `week-15` tag (it refuses if a migration or `composer.lock` differs; readiness answers 404 while rolled back). See [`../week-16/rollback-and-recovery-plan.md`](../week-16/rollback-and-recovery-plan.md).
- **Routine care and who does it:** [`maintenance-plan.md`](maintenance-plan.md). **How a change is made:** [`change-control-notes.md`](change-control-notes.md).

## 11. Known limits (do not remove)
- One Windows PC; `php artisan serve` is single-threaded; peak (25 users) p95 was 2,047.5 ms against a 2,000 ms target (Week 16, after OPcache); the spike was not repeated after OPcache. Use about 5 users.
- OPcache is switched on in XAMPP's `php.ini`, outside the repo; without it the same load fails its targets (check `php -m`).
- Scheduler and queue worker are started by hand; no alert reaches a person (R22, R32).
- Database account is XAMPP `root` with no password; the certificate is Caddy's local authority; plain HTTP between Caddy and Laravel on loopback (R34, R1, R6).
- Backups: one restore shown on an almost empty database; no schedule and no copy off the machine (R28).
- The app keeps the login token in memory only (8-hour lifetime), so a page reload signs the user out.
- Tested by one developer and three testers on one PC. This says those people could do those tasks there. It says nothing about a real office.
- Full register: [`../week-16/residual-risk-register.md`](../week-16/residual-risk-register.md) (merged with the new risks in chunk 6a).

## 12. Support
By role only: Maintainer (runs and fixes), Approver (Manual handling), Staff (release and return), Sponsor (scope and acknowledgement, pending). No names, e-mails or phone numbers are kept in the repository. See the table in [`maintenance-plan.md`](maintenance-plan.md).

## 13. Where things are
| Need | Go to |
|------|-------|
| Evidence for each week | `evidence/INDEX.md` |
| Week 17 plan, UAT cards and criteria | [`INDEX.md`](INDEX.md) |
| Architecture decision and scores | [`decision-record-008-architecture-alternatives.md`](decision-record-008-architecture-alternatives.md) |
| Quality evaluation | [`evaluation-matrix.md`](evaluation-matrix.md) (skeleton until chunk 6a) |
| Auth design | `evidence/week-13/decision-record-002-api-auth.md` |
| Signed messages and keys | `evidence/week-14/decision-record-003-message-protection.md`, `key-lifecycle-plan.md` |

## 14. Third-party code and AI assistance
Default Flutter and Laravel scaffolds, the Laravel framework and its Composer packages, the Flutter `http` package, Caddy, k6. Claude (Anthropic) assisted with planning, drafting code, tests and documents; the developer ran, tested and committed the work and defends it.
