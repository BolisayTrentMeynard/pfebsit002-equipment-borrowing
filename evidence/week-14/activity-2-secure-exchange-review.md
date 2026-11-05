# Week 14, Activity 2: Secure-exchange review of the laboratory build

Project: #5 Equipment Borrowing Reminder System. Data: fake test data only. Role that accepts the result: Approver (office supervisor). Review date: 2026-11-05. Written by Claude from the code and the developer's test results; Claude did not run any code.

## Task
Review the secure exchange (E2, the signed notifier message, and E1, the app calls over HTTPS) by source inspection, configuration review, transport verification, tamper testing, secret scanning and residual-risk documentation.

## Assumptions and constraints
Same as Activity 1. Out of scope: real email or SMS, public certificates, a key management service.

## Review steps and findings
| Step | What was checked | Finding | Evidence |
|------|------------------|---------|----------|
| Source inspection | `MessageSigner`, `SignedHttpNotifier`, `NotifierReceiverController` | Signature covers `v1.<id>.<timestamp>.<raw body>` and the receiver verifies the raw bytes. Order is format, signature, time window, replay. The replay id is stored only after a good signature. `hash_equals` is used. A missing or short key throws, with no unsigned fallback. Retries use a new id. **Finding F1:** the rejection log writes the `X-Message-Id` header exactly as the caller sent it, even when it is malformed, so a stranger can put an arbitrary string into the log. Laravel encodes it, so it cannot add log lines, but it can add junk. Fix later: log the id only if it has the UUID format | `MessageSigner.php`, `SignedMessageTest.php` |
| Configuration review | `config/notifier.php`, `.env.example`, `phpunit.xml` | Key, previous key, URL, window and CA bundle come from `.env`. The default driver is `fake`. Tests force the fake driver and empty keys. **Finding F2:** `.env.example` has `APP_DEBUG=true`, right for local work and wrong for deployment (Week 16). **Finding F3:** the first `.env.example` and README showed an unquoted Windows path that breaks parsing. Fixed (single quotes, one line per key) | `.env.example`, README |
| Transport verification | App to API, worker to receiver | `login` request URL is `https://localhost:8443/api/login`, answered through Caddy. The sender verifies the certificate against Caddy's CA file and never switches verification off. **Finding F4:** the response header `X-Powered-By: PHP/8.2.12` shows the PHP version (R10). The Caddy to Laravel hop is plain HTTP on the same machine (R6) | `week14-ac21-devtools-login-request.png` |
| Tamper testing | `notifier:tamper-demo` and 16 new tests | 202, 401 (altered body), 401 (expired), 409 (replay), 401 (wrong key), 400 (no headers), each matching the prediction. Caddy stopped: 3 attempts then FAILED_NEEDS_HUMAN. Short key: stops after 1 attempt | `test-matrix.md` |
| Secret scanning | Log, tracked files, git history, clean zip | No signing key, `Bearer`, `password` or `X-Message-Signature` in `laravel.log`; no tracked `.env`, SQLite or `.pem`; signing key and `APP_KEY` in 0 commits; no secrets in the clean zip | `week14-ac20-log-search.png`, `week14-ac24-secret-scan.png` |
| Residual risk | Register | R1 to R12 with decisions | `residual-risk-register.md` |

## Two approaches compared for the review itself
| | Review code and tests only | Code, tests and hand-run failure tests (selected) |
|---|----------------------------|------------------------------------------------|
| Finds logic mistakes | Yes | Yes |
| Finds setup mistakes (quoting, duplicate `.env` line, forgotten restart) | No | Yes, three were found this way |
| Cost | Low | Needs Caddy and the worker running |

## Adverse cases and safe behaviour
Altered, expired, replayed and unsigned messages are rejected and logged with an id and a reason only. A stopped receiver or a missing key ends with a person seeing the item in Manual handling, never with an endless retry.

## Limitations and next improvements
- One machine, development certificate, shared secret, cache-based replay store.
- Next: fix F1 and F4, make login throttling per account as well as per IP (R8), add an expired-token test, rerun the scans after the Week 15 changes.
- Not production ready.

## Facts, conclusions and recommendations
- **Facts:** the results above, from the test matrix and the developer's screenshots.
- **Conclusions:** the exchange rejects the tampering cases tested and fails safe. The weak points are configuration and operations, not the signing logic.
- **Recommendations:** fix F1 to F4 in Week 15, keep `.env` closed in captures, and rerun the scans.
