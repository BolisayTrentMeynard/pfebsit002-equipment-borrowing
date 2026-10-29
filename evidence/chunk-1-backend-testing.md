# Chunk 1: Backend foundation (database, login, roles, borrow workflow)

Status: built and tested by Claude in its own sandbox (16 automated tests passed, plus a manual login and CORS check on PHP 8.3). **Not yet tested on your PC.**

## What was added (and why)
| Part | Files | Why |
|------|-------|-----|
| Tables | `database/migrations/2026_10_29_*` | users get a role; equipment, borrow requests, audit log, api tokens |
| Auth | `ApiToken` model, `ApiTokenAuth`, `AuthController` | login with hashed, expiring tokens; same error for unknown user and wrong password |
| Roles | `RequireRole` middleware, `routes/api.php` | the server checks the stored role, never anything from the request body |
| State rules | `Services/BorrowWorkflow.php` | one place enforces PENDING, APPROVED/REJECTED, RELEASED, RETURNED, CLOSED, writes the audit entry and log line |
| Correlation id | `CorrelationId` middleware | each request gets an id in the logs, audit entries and `X-Correlation-ID` header |
| CORS | `config/cors.php` | lets Flutter Web on `localhost:<any port>` call the API |
| Fake data | `DatabaseSeeder` | 4 users and 6 equipment items (see `test-data-plan.md`) |
| Tests | `tests/Feature/BorrowWorkflowTest.php` | covers AC-01, 02, 04, 05, 06, 07, 08 and more |

Not in this chunk: the scheduler, notifications, retries and the Flutter screens (next chunks).

## Run steps (from `C:\dev\pfebsit002-week-13\backend`)
1. Extract the zip over the repo folder.
2. `php artisan migrate:fresh --seed` (this wipes the local dev database, which is fine here).
3. `php artisan test` (expect 16 passed).
4. `php artisan serve` and leave it running.

Dev logins (fake, dev only): `borrower1@example.test`, `borrower2@example.test`, `approver@example.test`, `staff@example.test`, all with password `DevPass123!`.

## Quick manual check (PowerShell, second terminal; not tested on Windows by Claude)
```
$r = Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8000/api/login -ContentType "application/json" -Body '{"email":"borrower1@example.test","password":"DevPass123!"}'
$h = @{ Authorization = "Bearer $($r.token)"; Accept = "application/json" }
Invoke-RestMethod -Uri http://127.0.0.1:8000/api/equipment -Headers $h
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8000/api/requests -Headers $h -ContentType "application/json" -Body '{"equipment_id":1,"reason":"Class presentation","requested_days":2}'
```
Without the `Authorization` header the same calls must return 401.

## Common errors and fixes
| Error | Fix |
|-------|-----|
| `could not find driver` | enable `extension=pdo_sqlite` and `extension=sqlite3` in `C:\xampp\php\php.ini`, then reopen the terminal |
| `Unable to open database file` or no such table | make sure `backend\database\database.sqlite` exists (create an empty file), then rerun `php artisan migrate:fresh --seed` |
| `Class "App\Http\Middleware\..." not found` | run `composer dump-autoload` |
| Routes return 404 for `/api/...` | check that `bootstrap/app.php` was replaced and has the `api:` line, then `php artisan route:list --path=api` |
| 401 on every call | the token expired (8 hours) or the header is missing; log in again |
| Port 8000 in use | `php artisan serve --port=8001` |
| Browser CORS error later in Flutter | the Flutter port must be on `localhost` or `127.0.0.1`; restart `php artisan serve` after changing `config/cors.php` |

## Capture while testing
1. Terminal: `php artisan migrate:fresh --seed` finishing without errors.
2. Terminal: `php artisan test` showing 16 passed.
3. PowerShell: login response (hide the token), then the 401 without a token.
4. Optional: `php artisan route:list --path=api`.
Check screenshots for tokens and real data before saving.
