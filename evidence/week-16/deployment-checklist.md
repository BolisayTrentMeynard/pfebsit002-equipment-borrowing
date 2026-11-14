# Deployment Checklist: local staging (Week 16, AC-47)

Written 2026-11-14 by Claude from the code (chunk 2). Not run by Claude: no PHP, Flutter, Caddy or Windows in its sandbox. Every "Expected" below is a prediction (see the chunk 2 addendum in `week16-failure-predictions.txt`) until the developer's captures show the real result.

## A. One-time setup
| # | Step | Expected |
|---|------|----------|
| A1 | Start MySQL in the XAMPP Control Panel | Port 3306 answers |
| A2 | From the repo root: `copy backend\.env.staging.example backend\.env.staging` | File exists (git-ignored) |
| A3 | In `backend\`: `php artisan key:generate --show`. Paste the output after `APP_KEY=` in `.env.staging`. Do it off-screen; never screenshot it | `APP_KEY` set |
| A4 | In `backend\`: `php artisan notifier:generate-key`. Paste the output after `NOTIFIER_SIGNING_KEY=`. This MUST be a new key, not the dev one | Key set (64 characters) |
| A5 | Optional now, needed only for a queue worker: set `NOTIFIER_CA_BUNDLE='<path to Caddy root.crt>'` (single quotes, same file as dev) | Set |
| A6 | Check that each name appears once in `.env.staging` (the first line wins when repeated) | No duplicates |
| A7 | `cd app` then `flutter pub get` (already done for dev) | No error |

The database `equipment_staging` is created by the release script if `mysql.exe` is found (XAMPP default path). If root has a password, create it in phpMyAdmin first.

## B. Release (repeatable, one command)
From a STANDALONE PowerShell window (not the VS Code tab) in the repo root:

```
powershell -NoProfile -ExecutionPolicy Bypass -File deploy\staging-release.ps1
```

Useful variants: `-SkipBuild` (reuse the last Flutter build, still resets the database), `-KeepData` (no reset), `-LocalResources` (build without Google's CDN for CanvasKit).

| # | What the script does | Expected on screen |
|---|----------------------|--------------------|
| B1 | Finds `php`, `flutter`, `caddy`, `curl.exe`; MySQL answers | `ok` lines |
| B2 | Reads `.env.staging` without printing values: `APP_ENV=staging`, `APP_DEBUG=false`, `DB_DATABASE=equipment_staging`, key lengths | `ok` lines with lengths only |
| B3 | Sets `APP_ENV=staging` in its own process, asks Laravel (`config:show`) for env, debug and database name | `Laravel reports app.env = staging, app.debug = false, database = equipment_staging`. If not: STOP before any reset |
| B4 | Creates the database if missing, runs `migrate:fresh --seed` | Migrations listed, seeding done |
| B5 | `flutter build web --release --dart-define=API_BASE_URL=https://localhost:9443/api` | Build time, folder size, `main.dart.js` size |
| B6 | Opens two windows: `STAGING API 8001` and `STAGING CADDY 9443` | Both windows stay open |
| B7 | Polls `/up`, then checks six URLs and the readiness body | All `ok`; readiness body `{"status":"ok"}` |
| B8 | `php artisan ops:status` with staging settings | Environment staging, Debug mode off, Log level info, Database reachable, exit code 0 |

The script ends with `RESULT: staging is up in N s` or `RESULT: N check(s) failed`. It never prints a key or a password.

## C. Checks after the release (by the developer)
| # | Check | Expected |
|---|-------|----------|
| C1 | In Chrome open `https://localhost:9443/` and log in as `borrower1@example.test` (dev-only seed password) | Login screen, then the borrower list; padlock shows a secure connection; address bar says `localhost:9443` |
| C2 | DevTools > Network, filter Fetch/XHR, after login | Requests go to `https://localhost:9443/api/...` |
| C3 | New request form at desktop width (about 1280 px) and narrow width (360 px, DevTools device toolbar) | No overflow; all fields reachable (screenshot both, closes the Week 15 gap on this form) |
| C4 | New PowerShell window (check `echo $env:APP_ENV` prints nothing): `cd backend` then `php artisan test` | 82 passed (chunk 2 adds no PHP) |
| C5 | In `app\`: `flutter test` | 15 passed |
| C6 | Postman run on staging: see `postman/README.md` | 149 tests, 149 passed |
| C7 | Optional demo of why the guard exists: in a plain window without `APP_ENV`, `cd backend` and `php artisan config:show database.connections.mysql.database` | Shows `equipment_borrowing`. The same command with `$env:APP_ENV='staging'` shows `equipment_staging` |

## D. Stop and reset
- Stop: press Ctrl+C in the two STAGING windows, then close them.
- Reset to clean data: run the release script again (`-SkipBuild` is enough when the app did not change). The reset also empties the cache table, so the login limit counters start from zero.
- Rolling back to the `week-15` tag and restoring a dump is rehearsed in chunk 5, not here.

## E. Troubleshooting
| Symptom | Likely cause and action |
|---------|-------------------------|
| `STOP: caddy is not found` | The script was started from the VS Code tab. Use a standalone PowerShell window |
| `STOP: Laravel does not report ...` | `.env.staging` is missing or not read. Nothing was reset. Read the output the script printed |
| `STOP: ... is missing or shorter than 32 characters` | Step A4 not done, or the key was pasted with spaces |
| Port 8001 or 9443 "already answers" | An earlier staging window is still open. That is fine for a new build; close the window and re-run after changing `.env.staging` |
| Chrome shows a certificate warning | Caddy's CA is trusted in Windows from Week 14; restart Chrome fully once |
| Old screen after a new build | Hard reload (Ctrl+Shift+R) or clear site data for `localhost:9443` |
| Postman gets 401 on everything | The wrong environment is selected (dev points at 8443) or the data was not reset first |

## F. What this checklist does not do
It does not start the scheduler or a queue worker, run any load, or take a backup. It does not make staging a separate machine. Those limits are in `environment-comparison.md`.
