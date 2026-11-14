# Environment Comparison (Week 16, AC-46)

Written 2026-11-14 (chunk 2) from the files in the repository: `backend/.env.example`, `backend/.env.staging.example`, `deploy/Caddyfile.staging`, `deploy/staging-release.ps1`. The staging column says what those files set. Whether staging really behaved that way is shown by the developer's captures in the evidence round, not by this table.

The **production target** column is on paper only. Nothing in it is built, bought or tested. It exists so the readiness recommendation can say plainly how far the prototype is from a real deployment.

| Item | Development | Local staging (built in chunk 2) | Production target (on paper, NOT built) |
|------|-------------|----------------------------------|------------------------------------------|
| Purpose | Writing and trying code | Repeatable release, measurement, failure test, rollback rehearsal | Real users |
| Database | `equipment_borrowing` (PHPUnit uses `equipment_borrowing_test`) | `equipment_staging` (own database, reset by the release script) | Own database on a managed or hardened server |
| Database account | XAMPP `root`, empty password | Same account (stated limit: local only) | Limited account, password from a secret store, port not open to the network |
| `APP_ENV` | `local` | `staging` (selects `backend/.env.staging`) | `production` |
| `APP_DEBUG` | `.env.example` says `true`; the developer's own `.env` is `false` (seen in `ops:status`, chunk 1) | `false`; the release script refuses anything else | `false` |
| `LOG_LEVEL` | `debug` (example file) | `info` | `info` or `warning`, with rotation |
| Log file | One `laravel.log` | One `laravel.log` (stated limit: log size is watched, see P3) | Daily rotation, retention rule, central collection |
| `APP_KEY` and signing key | Own keys in the private `.env` | Their own keys, different from development, in `.env.staging` | From a secret store, rotation plan (Week 14 key lifecycle) |
| Secrets live in | `backend/.env` (git-ignored) | `backend/.env.staging` (git-ignored, skipped by `make-clean-zip.bat`) | Platform secrets, not a file in the app folder |
| Web server | `php artisan serve` on 8000 | `php artisan serve` on 8001, single-threaded, behind Caddy | nginx or Apache with PHP-FPM and several workers |
| HTTPS | Caddy on 8443, Caddy's local CA | Caddy on 9443, Caddy's local CA (`tls internal`) | Publicly trusted certificate, automatic renewal, HSTS |
| Flutter app | Debug dev server (`flutter run -d chrome`, port 9595) | Release build (`flutter build web --release`) in `app/build/web`, served by Caddy on the same origin as the API | Release build on a web server or CDN, same origin or an exact CORS allow-list |
| CORS | Regex accepts any localhost port (Week 15 backlog item) | Not used by the app (same origin); config unchanged | Exact origin allow-list |
| Notifier | `NOTIFIER_DRIVER=fake` by default | `signed_http` to `https://localhost:9443/api/notifier/receive`, own signing key | Real receiver, key rotation, monitored failures |
| Queue | `database`; `queue:work` started by hand | `database`; worker started by hand only when a test needs it | Supervised worker service, alert on failed jobs |
| Scheduler | `routes/console.php` defines the schedule; nothing runs `schedule:run`; `reminders:run` is run by hand | Same | OS scheduler runs `schedule:run` every minute |
| Cache and sessions | `database` | `database` (candidate P3, measured in chunk 3) | To be decided from measurements; not chosen here |
| Data | Fake seed data | Fake seed data, reset on every release; `LoadTestSeeder` only here and only in chunk 3 | Real data is out of scope for this course project |
| Compression | None | None on purpose (candidate P5 must be measured first) | Enabled |
| Backups | None | `mysqldump` and restore rehearsed in chunk 5 | Scheduled, a copy kept off the machine, restore tested |
| Monitoring | Developer looks at the terminal | `/up`, `/api/health/ready`, `ops:status`, `http.request` log line, read by the developer | External probe with an alert to a named owner (see `monitoring-map.md`, chunk 6) |
| Rollback | `git` | `git checkout week-15` and a database restore, rehearsed in chunk 5 | Versioned releases, reversible migrations, a written rollback plan |
| Ports | 8000 API, 8443 HTTPS, 9595 Flutter dev server | 8001 API, 9443 HTTPS and static files | 443 only, API not exposed directly |

## Why staging uses the same machine
No server, cloud account or budget is available, and real hosting is outside the registered scope. Dev and staging can run at the same time because they use different ports, databases and Caddy instances (the staging Caddyfile turns Caddy's admin port off so it does not clash with the dev instance).

## What this comparison does not prove
- Staging shares one PC, one CPU and one MySQL server with development and with the load generator. A result here is not a statement about a real server.
- The certificate is from a development CA. It shows that HTTPS works, not how a public certificate is obtained and renewed.
- Staging uses the same database account as development. A production account with limited rights is a target, not something tested.
- Flutter's release build may load CanvasKit files from Google's CDN unless it is built with `-LocalResources`. If the developer's capture shows that, staging is not fully offline; the lab log records which variant was used.
