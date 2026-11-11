# Hardening Report (Week 15): before and after

Project: #5 Equipment Borrowing Reminder System. Branch `week-15-hardening`. Dates: 2026-11-10 (baseline and fixes), 2026-11-11 (rerun). Written by Claude from the code, the developer's results and screenshots; Claude did not run any code. No new routes or features were added (scope unchanged).

## What was done

1. A baseline was recorded on the unmodified code with a Postman collection (73 requests, 149 tests) plus the existing suites (`php artisan test` 47 passed, `flutter test` 5 passed).
2. Nine weaknesses were recorded as defects (`defect-register.md`) and ranked.
3. Tests for the weaknesses were written before the fixes where the weakness could be tested in PHPUnit (nine failed, 52 passed), then the fixes were made (61 passed).
4. New Flutter state tests found two more defects, which were fixed (15 passed).
5. Everything was rerun on fresh data, and the secret scans were repeated.

## Before and after

| Check | Before (baseline) | After (rerun 2026-11-11) |
|-------|-------------------|--------------------------|
| Postman collection | 149 tests, 137 passed, 12 failed | 149 tests, 149 passed, 0 failed |
| `php artisan test` | 47 passed (127 assertions) | 61 passed (195 assertions) |
| `flutter test` | 5 passed | 15 passed |
| 6th wrong password, same email | 401, no `Retry-After` (P-60) | 429 with `Retry-After`; other emails unaffected |
| Response headers | `X-Powered-By: PHP/8.2.12`; no `nosniff`, no `Referrer-Policy`; `Cache-Control: no-cache, private` | no `X-Powered-By`; `nosniff`; `Referrer-Policy: no-referrer`; `Cache-Control: no-store, private` on API responses |
| Audit rows seen by a borrower | `user_id` and `correlation_id` included | only `action`, `from_status`, `to_status`, `created_at` |
| Missing request (404) | message named `App\Models\BorrowRequest` | "Not found." |
| `/requests/1abc` | 200 (request 1) | 404 |
| Borrower opens another's request | 403, no log line | 403, `auth.forbidden` logged with ids and path |
| App on a 401 | unhandled `ApiException` in the console | handled, same screen and banner |
| App bar at 360 px | overflow of 103 px | no overflow (role only) |
| Secret scans (AC-38) | Week 14 scans clean | repeated, clean |

## Why these were fixed and others were not

Priority was likelihood, impact and exposure (`decision-record-005-test-and-hardening-approach.md`). W1 (login brute force) was the first priority because login is the main entry point. W2 and W3 are low effort with broad effect. W4 is data minimization: no screen uses the removed fields. DEF-05 to DEF-07 were small and cheap, approved by the developer as extras. W5 (race on submit) was left in the backlog because it cannot be reproduced honestly on a single-threaded server and its worst case is one extra PENDING request.

## Regression safety

No old test failed after any fix. The 47 earlier PHPUnit tests, the 5 earlier widget tests and every Postman request that passed at baseline still pass. The P-65 case (11th login from the same PC) still returns 429, so the per-IP limit survived the new per-email limit.

## What this does not show

- Behavior under load or on a deployed server (Week 16), and real concurrency (W5).
- Resistance to a real attacker. The checks are the ones written in the criteria.
- The browser-level expired-token case and the lockout seen in the app (tests A and C) were not run. The API results and unit tests cover them.
- Postman and the PHP tests run against the local setup (Caddy with a local certificate). A deployed server may add or remove headers.
- The `X-Powered-By` removal was verified by the developer's curl capture of two URLs, not by an automated test.
- The new request form has no desktop or narrow screenshot (widget test only).
- The AC-34 notifier timeout (5 s) was not simulated by a test of its own.

## Limits to know when reading the results

- The per-email lock can be used against a person: someone who fails on purpose can lock a user's login for a minute (accepted, R8).
- The lock counter lives in the cache; flushing the cache resets it.
- The narrow app bar no longer shows the user's name (the trade-off of DEF-09).
