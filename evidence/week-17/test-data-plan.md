# Test Data Plan (Week 17)

Fake data only: made-up names, `@example.test` e-mails, sample equipment. No real person, institution or production record.

## Accounts
The four fake accounts from `DatabaseSeeder` (borrowers, approver, staff) are used. Each tester gets a login card. The card holds the fake dev seed password, is printed or on screen only, and is not committed or photographed. Testers are T1, T2 and T3 in every record.

## Data waiting for each role (made by the guarded `UatSeeder`, chunk 1; exact tags added then)
| For | Waiting | Notes |
|-----|---------|-------|
| T1 borrower | Available items; no open request for the item used in UAT-01 | |
| T2 approver | Two pending requests from another fake borrower; one released item past its due date | The overdue item must appear on the Overdue tab |
| T3 staff | One approved request ready to release; one released item ready to return | |
| Operator | Reminder failure made by starting the worker with a wrong `NOTIFIER_URL` in its own window | UAT-08 |

The seeder refuses to run unless `APP_ENV` is local, staging or testing. It never touches `equipment_borrowing` unless that is the active dev database in a local environment, and it prints what it made.

## Resets and backups
Reset: `deploy\staging-release.ps1 -SkipBuild` (about 13 s) before the session and whenever a session needs a clean start. Backup: `deploy\backup-restore.ps1` before UAT data is entered; the dump stays outside the repo (`C:\dev\pfebsit002-backups\`) because it holds fake password hashes.

## Capture hygiene
Screen-only recordings or screenshots, no audio, no faces. Keep out of every frame: the dev seed password, `.env`, `.env.staging`, the Authorization header, tester names. Drop captures unsorted into `Downloads\FLUTTER\Week 17`.

## Integrity checks after the session
The Week 16 SQL checks (no double open request per borrower and item, no item released twice, audit rows add up), run by the developer on `equipment_staging`.
