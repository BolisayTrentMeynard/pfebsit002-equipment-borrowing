# Equipment Borrowing Reminder System (PFEBSIT 002 finals project)

Individual finals project for PFEBSIT 002 (Weeks 13-18): a Flutter Web app with a Laravel backend. The course module is used as a guide for what each week adds. The whole project lives in this one repository, and each week is marked with a git tag.

- **Student:** Bolisay Trent Meynard, BSIT 3A (solo)
- **Selected project:** #5 Equipment Borrowing Reminder System (backups: #25 Inventory Reorder Alert System, #21 Certificate Generation Queue)
- **Backend:** Laravel 12 (PHP 8.2), MySQL (MariaDB from XAMPP)
- **Frontend:** Flutter Web

## What it does

This project automates the borrowing cycle for shared school equipment in a small, controlled office setting. A borrower submits a borrow request, an approver accepts or rejects it, and staff release the item with a recorded due date. A scheduled job then checks due dates, sends reminders before the deadline, and escalates overdue items to the approver, with a limit on repeated notifications so nobody is spammed. When the item comes back, staff record the return and inspection result, and the request is closed. A daily summary lists the items currently in borrowers' custody. The backend validates every request, requires login, restricts actions by role (borrower, approver, staff) and logs every state change. Notifications use bounded retries, and a notification that keeps failing goes to a human for manual handling instead of retrying forever. All data is fake test data (sample equipment and made-up names), with no real personal information.

Limits used in the demo: loan of 7 days, at most 3 reminders and 2 escalations per item, 3 attempts per notification (waits of 5 s and 15 s).

## Weeks and tags

| Week | Tag | What was added | Status |
|------|-----|----------------|--------|
| 13 | `week-13` | Backend API with roles and audit log, reminder automation (scheduler, queue, bounded retries, manual handling), Flutter screens for all roles | Done |
| 14 | `week-14` | HTTPS in front of the API, signed (HMAC-SHA256) notifier messages that reject altered, expired and replayed messages, keys kept in `.env`, MySQL instead of SQLite | Done |
| 15 | `week-15` | Integration testing and security hardening (Postman collection, traceability, nine fixes, regression evidence) | Done |
| 16 | `week-16` | Performance, reliability and deployment | Not started |
| 17 | `week-17` | Final integration, UAT and evaluation | Not started |
| 18 | `week-18` | Final review, demo and technical defense | Not started |

To see the project as it was in a given week, open the tag on GitHub, or run `git checkout week-13`.

## Repository layout

```
app/        Flutter Web app
backend/    Laravel API
evidence/   Evidence per week (evidence/INDEX.md, then evidence/week-13/ and so on)
README.md
```

## How to run

You need Flutter (with Chrome), PHP 8.2 or newer, Composer, Git and MySQL (the XAMPP one works; start MySQL in the XAMPP Control Panel). The steps below worked on the developer's Windows PC with the project already set up; they have not been tested from a fresh clone.

Create two empty databases once (phpMyAdmin at http://localhost/phpmyadmin, or the `mysql` client): `equipment_borrowing` for the app and `equipment_borrowing_test` for `php artisan test`.

Backend (from `backend/`):

```
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The API runs at http://127.0.0.1:8000 (and, with Caddy running, at https://localhost:8443; see "HTTPS and signed messages" below). The demo accounts (borrower, approver, staff) are fake and are created by `backend/database/seeders/DatabaseSeeder.php`.

Flutter Web app (from `app/`, in a second terminal). Since Week 14 the app talks to `https://localhost:8443/api` by default, so start Caddy first (next section). To use the plain server instead:

```
flutter pub get
flutter run -d chrome --web-port 9595
flutter run -d chrome --web-port 9595 --dart-define=API_BASE_URL=http://127.0.0.1:8000/api
```

The first command uses the HTTPS default, the second one the plain HTTP address.

### HTTPS and signed messages (Week 14)

HTTPS locally: install Caddy (`winget install CaddyServer.Caddy`), then in its own PowerShell window run

```
caddy reverse-proxy --from localhost:8443 --to 127.0.0.1:8000 --disable-redirects
```

Caddy makes its own local certificate authority and adds it to Windows trust the first time. This is a development certificate, not a production one. Restart Chrome once after the first start.

Signed notifier messages are off by default (`NOTIFIER_DRIVER=fake`, the Week 13 behaviour). To turn them on, in `backend/.env`:

```
php artisan notifier:generate-key          (prints a random key; paste it, never commit it)
NOTIFIER_DRIVER=signed_http
NOTIFIER_SIGNING_KEY=<the key>
NOTIFIER_URL=https://localhost:8443/api/notifier/receive
NOTIFIER_CA_BUNDLE='<path to the root.crt file Caddy made>'   (single quotes, see .env.example)
```

Each name must appear only once in `.env` (if repeated, the first line wins). Then run `php artisan config:clear`, restart `php artisan serve`, keep Caddy running, and try `php artisan notifier:tamper-demo` (sends valid, altered, expired, replayed and wrongly signed messages and shows how the receiver answers). Design and evidence notes: `evidence/week-14/`.

Reminder automation demo (from `backend/`, in a third terminal):

```
php artisan db:seed --class=DemoScenarioSeeder
php artisan reminders:run
php artisan queue:work
```

Leave `queue:work` running for about 40 seconds so the retries can finish, then stop it with Ctrl+C. Do not use `--stop-when-empty`, because it quits while retries are waiting. The failure switches (`NOTIFIER_TRANSIENT_TAGS`, `NOTIFIER_ALWAYS_FAIL_TAGS`) in `backend/.env` are for demos only.

Tests:

```
cd backend && php artisan test
cd app && flutter test
```

## Configuration and secrets

The real `.env` file is never committed. Use `backend/.env.example` as the template. No API keys, passwords, tokens or real personal data belong in this repository, including screenshots and logs. The app keeps the login token in memory only.

## Evidence

Evidence is listed in `evidence/INDEX.md`. Large files (screenshots, recordings) are kept on Google Drive and linked from the index.

## AI assistance and third-party code

I used Claude (Anthropic) as an AI assistant for planning, setup guidance, drafting code, tests and documentation. I ran, tested and committed the work myself, and I can explain and defend my own contribution.

Third-party code: the default Flutter and Laravel scaffolds, the Laravel framework and its default packages (via Composer), and the Flutter `http` package. The authentication is a small custom hashed-token table (see `evidence/week-13/decision-record-002-api-auth.md`).
