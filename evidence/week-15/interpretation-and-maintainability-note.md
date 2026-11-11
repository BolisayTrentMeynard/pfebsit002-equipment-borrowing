# Interpretation and Maintainability Note (Week 15)

Project: #5 Equipment Borrowing Reminder System. Date: 2026-11-11. Written by Claude from the code, the developer's results and screenshots; Claude did not run any code.

## What the results show

- **The baseline found real weaknesses, not only paper ones.** The unmodified code failed 12 of 149 Postman checks: no per-email login limit, a PHP version header, missing security headers, audit ids shown to a borrower, an internal class name in a 404, an id with letters accepted, and an object-level denial that left no log line. Each failure was predicted or at least explained before it was fixed.
- **The fixes work and did not break anything.** After the fixes the same collection passed 149 of 149 on fresh data, `php artisan test` went from 47 to 61 passed, and `flutter test` from 5 to 15 passed. The old tests were untouched.
- **Different layers found different problems.** Postman found the seven backend defects because it sees real responses and headers over HTTPS. The Flutter tests found two defects (an unhandled error on a 401 and an overflow at phone width) that Postman could not see, because they live in the app. This is the reason for the three-layer approach in DR-005.
- **Tests-first showed that the tests were meaningful.** Nine of the 14 new PHPUnit tests failed on the unmodified code, and the five that passed were the ones that guard behavior that already worked.
- **The secret scans are still clean** after a week of new code and about 1.6 MB of logs: no key, token or password in `laravel.log`, no tracked `.env`, `.sqlite` or `.pem`, and the signing key and `APP_KEY` value in no commit.

## What the results do not show

- **They do not show behavior under load or in deployment.** Week 16 covers that. Headers or limits could differ behind a real web server or proxy.
- **They do not show resistance to a real attacker.** The tests check the cases in the criteria. The per-email limit also has a side effect (a one-minute lockout someone can cause on purpose).
- **Two optional browser checks were not run**: the expired token with the app open, and the lockout shown in the app. They are covered at the API and unit level, not by a live browser run.
- **The dependency-failure criterion (AC-34) is Partial.** The 15 s app timeout and the sender's handling of connection errors are tested; the 5 s notifier timeout has no test of its own, and the Caddy-stopped runs are from Week 14.
- **One run of the rerun series failed for an unknown reason** (10:21, every request after the setup got 401). The next run was clean, but the cause was not investigated, so a flaw in the collection's setup cannot be ruled out. The collection already has a data check (S-6) and the clean run passed it.
- **Data minimization was only reviewed for audit rows.** The recipient fields on the manual-handling list were not reviewed.

## Maintenance notes

- **Reset data before every Postman run** (`php artisan migrate:fresh --seed`). The collection expects fresh data, and S-6 fails when it is not.
- **Passwords and tokens stay in Postman's Current value** (secret type, Initial value empty). Do not export run results, and keep the login response body closed in screenshots.
- **The login limit is a constant** (`MAX_FAILED_LOGINS = 5`) in `AuthController`. The per-IP limit is on the route (`throttle:10,1`). Keep both in mind when changing either.
- **The security headers are in one middleware** (`SecurityHeaders`), registered for every response. If a route must be cached, change `Cache-Control` there.
- **Route ids are digits only** (`Route::pattern` in `routes/api.php`). A new route that uses a different parameter name needs its own pattern.
- **The 404 message is set in `bootstrap/app.php`.** Other error messages still come from Laravel's defaults.
- **When an API response changes, change the collection and the traceability matrix with it.** The collection can drift from the API if this is forgotten.
- **Flutter is pinned at 3.47.8.** A newer version is available; it was not upgraded during the week on purpose.
- **The small user-name trade-off:** on narrow screens the app bar shows the role only. If the name is needed on phones, put it in a menu or a second line and keep the narrow test.
