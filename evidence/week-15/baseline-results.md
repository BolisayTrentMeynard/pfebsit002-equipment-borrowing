# Week 15 Baseline Results (chunk 1)

Expected results are copied from the collection (written before the run). Actual results come from the developer's Postman Runner screenshots of the counted run (2026-11-10, 15:58:25), seen by Claude in chat; Claude ran nothing. Failures stay in the record: a FAIL here is a finding, not a mistake. A failed [W..] check on the unmodified code is the baseline and becomes a defect (DEF-01 and up).

**How the Actual column was filled in:** the developer's screenshots show the Failed tab (12 failures, listed below) and the totals (149 tests, 137 passed, 12 failed, 0 errors). Every request that is not in the Failed tab had all its checks pass, so its Actual says "as expected". The per-request status codes of those passing requests were not read one by one from screenshots; they are known only through their "status is ..." check having passed. This is a limit of the evidence and is listed in the interpretation note later.

## Run attempts (Postman run history, per developer's screenshot)

| Run | Start (Manila) | Tests / passed / failed | Duration | Counted? |
|-----|----------------|-------------------------|----------|----------|
| 1 | 15:50:26 | 127 / 117 / 10 | 1m 52s | NOT counted. The run was stopped (Postman shows a stop icon). Reason not recorded by the developer. |
| 2 | 15:52:58 | 10 / 10 / 0 | 10s | NOT counted. Partial run (setup only or similar). Reason not recorded. |
| 3 | 15:54:13 | 44 / 41 / 3 | 25s | NOT counted. Stopped at P-17 with a script error (data was not fresh: 6 leftover requests; collection bug where an id was stored outside `pm.test`). Fixed, S-6 added. |
| 4 | 15:58:25 | 149 / 137 / 12 | 2m 21s | **COUNTED baseline.** Fresh data, 0 errors. |

Failures stay in the record: runs 1 to 3 are listed so nobody has to wonder where they went. The first two rows are not explained in the log; the developer can add one line each.

## Run record

| Item | Value |
|------|-------|
| Date and time of the run | 2026-11-10, 15:58:25 (Manila; the log lines of this run are at about 07:58 to 07:59 UTC), 2m 21s including the 61 s wait before the rate-limit folder |
| Branch and commit | `week-15-hardening`, HEAD `fbb8504` at the time (the predictions addendum, committed alone before any run; the developer is to confirm its commit time is earlier than 15:50:26 with `git log -1 --format=%ci fbb8504`). No application code was changed. |
| Data reset first (`migrate:fresh --seed`) | yes (S-6 passed: empty list) |
| `APP_DEBUG` in the private `.env` | false (developer's word; same value must be used for the rerun) |
| Base URL | `https://localhost:8443/api` (certificate check ON) |
| Runner summary (passed / failed / total tests) | 137 passed / 12 failed / 149 total, 0 errors, average response time 1047 ms |
| `php artisan test` | 47 passed, 127 assertions, 91.36 s (MySQL test database) |
| `flutter test` | +5, all tests passed (Flutter 3.47.8) |
| Not part of the full run | folder `99 Manual` (P-66, P-67) is run by hand, see below |

## Per request

| Folder | ID | Request | Criterion | Expected | Actual (status, failed checks) | Result | Defect |
|---|---|---|---|---|---|---|---|
| 00 Setup | S-1 | POST /login as borrower1 | Setup (not a numbered test) | 200, a token, role borrower. Needs the environment variable `password` (current value only). | As expected, setup checks passed (not in the Failed tab). | Pass |  |
| 00 Setup | S-2 | POST /login as borrower2 | Setup (not a numbered test) | 200, a token, role borrower. Needs the environment variable `password` (current value only). | As expected, setup checks passed (not in the Failed tab). | Pass |  |
| 00 Setup | S-3 | POST /login as approver | Setup (not a numbered test) | 200, a token, role approver. Needs the environment variable `password` (current value only). | As expected, setup checks passed (not in the Failed tab). | Pass |  |
| 00 Setup | S-4 | POST /login as staff | Setup (not a numbered test) | 200, a token, role staff. Needs the environment variable `password` (current value only). | As expected, setup checks passed (not in the Failed tab). | Pass |  |
| 00 Setup | S-5 | GET /equipment (staff): learn the item ids | Setup (not a numbered test) | 200 and six available items. Item ids are stored in eq1 to eq6. | As expected, setup checks passed (not in the Failed tab). | Pass |  |
| 00 Setup | S-6 | GET /requests (staff): the data must be fresh | Setup (not a numbered test) | 200 and an empty list. If not, the data is left over from an earlier run: reset and start again. | As expected, setup checks passed (not in the Failed tab). | Pass |  |
| 01 Normal | P-01 | POST /requests as borrower1 (EQ-001, 3 days) | AC-01, AC-25 | 201, status PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-02 | GET /requests as borrower1 | AC-01, AC-25 | 200; contains reqMain; every row has the same borrower. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-03 | POST /requests/{{reqMain}}/approve as approver | AC-02, AC-25 | 200, status APPROVED. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-04 | POST /requests/{{reqMain}}/release as staff | AC-03, AC-25 | 200, RELEASED, due_at set. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-05 | GET /custody as staff | AC-03, AC-25 | 200; reqMain listed, overdue false. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-06 | POST /requests/{{reqMain}}/return as staff (good) | AC-04, AC-25 | 200, CLOSED, inspection good. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-07 | GET /equipment as staff (EQ-001 back) | AC-04, AC-25 | 200; EQ-001 available. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 01 Normal | P-08 | GET /me as borrower1 | AC-25 | 200, keys id, name, role. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-09 | POST /requests with an empty body | AC-26 | 422 with three field errors, nothing saved. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-10 | POST /requests with wrong types | AC-26 | 422 with three field errors. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-11 | POST /requests with an unknown equipment_id | AC-26 | 422 on equipment_id. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-12 | GET /requests?status=BOGUS | AC-26 | 422 on status. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-13 | POST /requests/{{reqMain}}/return with a bad inspection_result | AC-26 | 422 on inspection_result (validation runs before the status check). | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-14 | POST /login with an invalid email | AC-26 | 422 on email. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 02 Invalid input | P-15 | GET /requests as borrower1: nothing was saved | AC-26 | 200 and 1 row: the invalid requests saved nothing. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-16 | days = 0 (below minimum) | AC-26 | 422 on requested_days, nothing saved. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-17 | days = 1 (minimum, EQ-002) | AC-26 | 201, saved as PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-18 | days = 7 (maximum, EQ-003) | AC-26 | 201, saved as PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-19 | days = 8 (above maximum) | AC-26 | 422 on requested_days, nothing saved. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-20 | reason = 2 characters | AC-26 | 422 on reason, nothing saved. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-21 | reason = 3 characters (EQ-004) | AC-26 | 201, saved as PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-22 | reason = 255 characters (EQ-005) | AC-26 | 201, saved as PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-23 | reason = 256 characters | AC-26 | 422 on reason, nothing saved. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 03 Boundary | P-24 | GET /requests as borrower1: only the accepted ones were saved | AC-26 | 200 and 5 rows. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 04 Unauthorized | P-25 | GET /requests with no token | AC-27 | 401 {"message":"Unauthenticated."} | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 04 Unauthorized | P-26 | GET /requests with a made-up token | AC-27 | 401, same message. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 04 Unauthorized | P-27 | POST /requests with no token | AC-27 | 401, same message. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 04 Unauthorized | P-28 | POST /login with a wrong password | AC-27 | 401 Invalid credentials. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 04 Unauthorized | P-29 | POST /login with an unknown email | AC-27 | 401, identical message to the previous request. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 05 Forbidden | P-30 | POST /requests/{{reqDays1}}/approve as borrower1 | AC-28 | 403, status stays PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 05 Forbidden | P-31 | GET /manual-handling as borrower1 | AC-28 | 403. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 05 Forbidden | P-32 | POST /requests/{{reqDays1}}/approve as staff | AC-28 | 403. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 05 Forbidden | P-33 | POST /requests as staff (only borrowers submit) | AC-28 | 403, nothing saved. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 05 Forbidden | P-34 | GET /custody as borrower1 | AC-28 | 403. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 05 Forbidden | P-35 | GET /requests/{{reqMain}} as borrower2 (another borrower's request) | AC-28, W6 | 403. W6 prediction: no log line exists for this correlation id (checked by hand, see RUN-GUIDE). | 403. All checks passed. The log check shows no trace line (see Manual checks): W6. | Pass (status); W6 confirmed by log search | DEF-07 |
| 05 Forbidden | P-36 | GET /requests/{{reqDays1}} as approver: denied actions changed nothing | AC-28 | 200, status PENDING. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 06 Not found | P-37 | GET /requests/99999 as approver | AC-29 | 404 JSON, no stack trace or file path. W7 candidate: the message may name `App\Models\BorrowRequest` (not in the committed predictions). | 404 JSON; message names the internal class (`No query results for model [App\Models\BorrowRequest...`). Failed 1: W7 message check. | FAIL (W7) | DEF-05 |
| 06 Not found | P-38 | GET /requests/abc as approver | AC-29 | 404 JSON, no stack trace or file path. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 06 Not found | P-39 | GET /nope (unknown route) | AC-29 | 404 JSON, no stack trace or file path. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 06 Not found | P-40 | POST /requests/99999/approve as approver | AC-29 | 404 JSON, no stack trace or file path. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 06 Not found | P-41 | GET /requests/{{reqMain}}abc as approver (numeric prefix) | AC-29, W8 | 404. W8 candidate: MySQL may read `1abc` as 1 and return request 1 with 200 (not in the committed predictions). | 200 with request 1 instead of 404. Failed 1: W8 status check. | FAIL (W8) | DEF-06 |
| 07 Duplicate and conflict | P-42 | POST /requests as borrower2 (EQ-006) | AC-30 | 201. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 07 Duplicate and conflict | P-43 | POST /requests as borrower2 again (same item) | AC-30 | 422 "You already have an open request for this equipment." | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 07 Duplicate and conflict | P-44 | POST /requests/{{reqDup}}/approve as approver | AC-30 | 200. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 07 Duplicate and conflict | P-45 | POST /requests/{{reqDup}}/approve again | AC-30 | 409 "Cannot approve a request that is APPROVED." | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 07 Duplicate and conflict | P-46 | POST /requests/{{reqDays1}}/release as staff (skips approval) | AC-06, AC-30 | 409 "Cannot release a request that is PENDING." | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 08 Security checks | P-47 | GET /me: headers on an authenticated response | AC-32 | 200. Baseline prediction: the four [W2]/[W3] checks FAIL (weakness confirmed), the correlation id check passes. | 200. Failed 4: `X-Powered-By: PHP/8.2.12` present; no `X-Content-Type-Options`; no `Referrer-Policy`; `Cache-Control: no-cache, private` (no `no-store`). | FAIL (W2, W3) | DEF-02, DEF-03 |
| 08 Security checks | P-48 | GET /requests with no token: headers on an error response | AC-32 | 401. Baseline prediction: both checks FAIL. | 401. Failed 2: `X-Powered-By` present; no `nosniff`. | FAIL (W2, W3) | DEF-02, DEF-03 |
| 08 Security checks | P-49 | GET /requests/{{reqMain}} as borrower1: field review | AC-33 | 200. Baseline prediction: both [W4] checks FAIL. The developer also saves the response body (no token in it) for the field table. Whether it is a defect is decided from the fields the Flutter screen needs. | 200, 2.204 KB. Failed 2: `audit_logs` rows carry `correlation_id` and `user_id` to a borrower (body captured, no token in it). | FAIL (W4) | DEF-04 |
| 08 Security checks | P-50 | GET /manual-handling as approver: field review | AC-33 | 200 and an empty array on fresh data. The recipient fields need demo failures (optional step in RUN-GUIDE). | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 08 Security checks | P-51 | POST /notifier/receive with no signature headers | AC-30, AC-17 cross-reference | 400, reason malformed. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 08 Security checks | P-52 | POST /notifier/receive with a wrong signature | AC-30, AC-17 cross-reference | 401, reason bad_signature. (Needs NOTIFIER_SIGNING_KEY set in the private .env; with no key the receiver would answer 500.) | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 09 Logout | P-53 | POST /logout as borrower2 | AC-27 | 200 Logged out. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 09 Logout | P-54 | GET /me with the logged-out token | AC-27 | 401: the token no longer works. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-55 | failed login 1, same email (waits 61 s first) | AC-31 | 401. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-56 | failed login 2, same email | AC-31 | 401. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-57 | failed login 3, same email | AC-31 | 401. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-58 | failed login 4, same email | AC-31 | 401. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-59 | failed login 5, same email | AC-31 | 401. | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-60 | failed login 6, same email | AC-31 | AC-31 target: 429 with Retry-After. W1 baseline prediction: 401 (no per-account limit), so this FAILS and confirms W1. | 401. Failed 2: expected 429 and a `Retry-After` header; got 401 and none. | FAIL (W1) | DEF-01 |
| 10 Rate limit | P-61 | failed login 7, a different email | AC-31 | 401, not 429 (another email is not locked). | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-62 | failed login 8, another email | AC-31 | 401 (still inside the per-IP limit of 10). | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-63 | failed login 9, another email | AC-31 | 401 (still inside the per-IP limit of 10). | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-64 | failed login 10, another email | AC-31 | 401 (still inside the per-IP limit of 10). | As expected; all checks passed (not in the Failed tab of the counted run). | Pass |  |
| 10 Rate limit | P-65 | failed login 11 from this PC | AC-31 | 429 with Retry-After: the per-IP limit of 10 per minute (predicted to hold before and after the fix). Also clears the tokens from the environment. | 429 with `Retry-After` (per-IP limit of 10 per minute holds). All checks passed. | Pass | - |
| 99 Manual | P-66 | M-1  POST /login as borrower1 (token to be expired) | AC-27 (manual step) | 200. Then run the tinker line from RUN-GUIDE to put this token's expiry in the past. | 200, 1/1 check passed (separate manual run, about 16:2x Manila time, after the counted run). | Pass |  |
| 99 Manual | P-67 | M-2  GET /me with the expired token | AC-27 (manual step) | 401 {"message":"Unauthenticated."}; the log has `auth.rejected` for `api/me` without the token. | 401 `{"message":"Unauthenticated."}`, 2/2 checks passed; `auth.rejected` for `api/me` logged at 08:27:05 UTC without the token. | Pass |  |

## Manual checks that Postman cannot do

| Check | How | Expected | Actual | Result | Defect |
|-------|-----|----------|--------|--------|--------|
| W6 log line for the denied object access | Two PowerShell searches of `backend/storage/logs/laravel.log`: (1) the P-35 correlation id (`corrObjectDenied`); (2) borrower `auth.forbidden` lines whose path is `api/requests/<number>`; plus a control search for `auth.forbidden` | No line (W6 predicted) | (1) no output; (2) no output; control: `auth.forbidden` lines exist (for example 07:59:08 and 07:59:09 UTC for `api/requests/2/approve`, `api/requests` and `api/custody`). The log ids are full UUIDs (a cropped screenshot first looked shorter; Claude's early "format mismatch" remark was wrong). | W6 confirmed | DEF-07 |
| W2 header seen in a browser (optional) | DevTools Network, response headers of one API call | `X-Powered-By: PHP/8.2.12` present | Not done separately; the Postman P-47 and P-48 checks already show it. | Covered by P-47 | DEF-02 |
| Expired token (P-66, P-67) | M-1 (P-66), tinker line, M-2 (P-67) | 401 and an `auth.rejected` log line without the token | P-66 200; tinker `update(...)` printed `true`; P-67 401 `Unauthenticated.` (2/2 checks); log line at 08:27:05 UTC: `auth.rejected` with `correlation_id` and `path: api/me` only | Pass | |
| W4 field table | Response body of P-49 as borrower1 (no token inside) | Fields listed per role | Borrower sees: request fields, `equipment` (id, name, asset_tag), `borrower` (id, name ...), and `audit_logs` rows with `id`, `borrow_request_id`, `user_id`, `action`, `from_status`, `to_status`, `correlation_id`, `created_at`, `updated_at`. The Flutter history list uses only `created_at`, `action`, `from_status`, `to_status`. | W4 confirmed | DEF-04 |

## Defects found (all found by the counted baseline run, unmodified code; nothing fixed yet)

Priority follows DR-005 (likelihood, impact, exposure). "Fix planned" is a proposal until the developer approves chunk 2.

| ID | Found by | What is wrong | Cause (from the code) | Priority | Fix planned |
|----|----------|---------------|-----------------------|----------|-------------|
| DEF-01 (W1) | P-60 | The 6th wrong password for the same email still gets 401; no 429, no `Retry-After`. Only the per-IP limit (11th attempt, P-65) stops it. | `throttle:10,1` on the login route is per IP only; there is no per-email limit (R8). | High | Chunk 2, tests first: 5 failed logins per email per minute, then 429 with `Retry-After`; other emails unaffected (AC-31) |
| DEF-02 (W2) | P-47, P-48 | Every response carries `X-Powered-By: PHP/8.2.12`. | PHP's `expose_php` setting and Laravel send no header removal (R10). | Low impact, trivial effort | Chunk 2: remove the header in middleware; verified by developer capture (AC-32) |
| DEF-03 (W3) | P-47, P-48 | No `X-Content-Type-Options: nosniff`, no `Referrer-Policy`; authenticated JSON has `Cache-Control: no-cache, private`, not `no-store`. | No security-header middleware on the API group. | Medium | Chunk 2, tests first: add the headers (AC-32) |
| DEF-04 (W4) | P-49, body capture | A borrower's `GET /requests/{id}` returns audit rows with `user_id` (staff or approver ids) and `correlation_id`. No Flutter screen uses them. | `show()` loads `auditLogs` whole: `$borrowRequest->load([... 'auditLogs'])`. | Medium (internal ids and log correlation values exposed to a borrower) | Chunk 2: return only `action`, `from_status`, `to_status`, `created_at` (AC-33), keep the history screen working |
| DEF-05 (W7) | P-37 | The 404 message for a missing request names the internal class `App\Models\BorrowRequest`. | Laravel's default model-not-found message is passed to the JSON response. | Low | Optional in chunk 2: generic message "Not found." |
| DEF-06 (W8) | P-41 | `GET /requests/1abc` returns request 1 (200) instead of 404. | Route model binding does not limit `{borrowRequest}` to digits; MySQL reads `1abc` as 1. | Low (token and role rules still apply) | Optional in chunk 2: `whereNumber` on the route parameter |
| DEF-07 (W6) | Manual log search | A borrower opening another borrower's request gets 403 but nothing is logged (the role check logs `auth.forbidden`; `authorizeView` only aborts). | `authorizeView` has no log line. | Low to medium | Optional in chunk 2: log `auth.forbidden` with ids only, no personal data |

Not defects (documented, not fixed): W5 double-submit race (backlog, see DR-005); CORS regex accepts any localhost port; `$guarded = []` on three internal models.

**Weaknesses fixed in Week 15 (proposal):** at least three are required; DEF-01, DEF-02 and DEF-03 are the core set. DEF-04 is the clearest data-minimization case. DEF-05 to DEF-07 are cheap and small; the developer decides.
