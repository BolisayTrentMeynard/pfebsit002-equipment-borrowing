# Decision Record 005: Test approach and hardening priorities (Week 15)

Date: 2026-11-10. Status: approved by the developer before any code change.

## Context
The API and the Flutter app work for the normal path and for the failure cases tested in Weeks 13 and 14. Week 15 must show that the integration holds up beyond one demonstration: invalid input, boundaries, wrong roles, expired tokens, dependency failure, information leakage, and that fixes do not break working behavior. The developer works alone, on one Windows PC, with Postman installed.

## Decision question
How do we get a repeatable test baseline and justified hardening evidence for the API and the Flutter client, with the least new tooling?

## Options
| | A. Postman collection + PHPUnit + Flutter widget tests (chosen) | B. PHPUnit and widget tests only | C. curl or PowerShell scripts | D. Newman in CI or an automated scanner (for example OWASP ZAP) |
|---|---|---|---|---|
| Fit to the module (collection with named case types) | Direct | Does not give an API collection | Possible, but not a collection | Over the requirement |
| Evidence quality | Runner screenshots plus a collection another person can import | Test output only | Terminal text, hard to review | Strong, but setup-heavy |
| Interoperability (real HTTP, real HTTPS, real headers) | Yes, through Caddy | No, in-process only | Yes | Yes |
| Effort and skills | Low, the tool is installed | Lowest | Medium, Windows quoting problems | High, new tool and setup |
| Verification by another person | Import file, set one variable, run | Run one command | Run scripts | Needs the tool and its configuration |
| Secret risk | Token in a variable only; no run export | None | Tokens in shell history | Tokens in tool config |

## Decision
Option A. Postman checks what a client sees over HTTP and HTTPS (status, body, headers). PHPUnit checks logic and timing in-process, including cases Postman cannot reach (notifier timeout, expired token, throttling). `flutter test` checks the app's states with a fake HTTP client. The three layers are linked by the traceability matrix.

Rejected: B (no real HTTP, so no header or transport evidence), C (weaker evidence, more error-prone on Windows), D (setup effort is not justified for a bounded course project; kept as a future step).

## Hardening priorities
Ranked by likelihood, impact and exposure. These are candidates read from the code and are confirmed or disproved by the baseline run.

| ID | Weakness | Priority | Plan |
|----|----------|----------|------|
| W1 | Login throttle per IP only (R8) | High: login is the main entry point and the classic target | Fix, with the test written first |
| W3 | No security headers on API responses | Medium: cheap, broad effect | Fix, with the test written first |
| W2 | `X-Powered-By: PHP/8.2.12` exposed (R10) | Low impact, trivial effort, already registered | Fix; verified by developer capture |
| W4 | Audit rows and recipient details in responses | Depends on the real JSON | Decide after the baseline field review |
| W6 | A borrower opening another borrower's request gets 403, but `authorizeView` only aborts and writes no log line (the role check does log `auth.forbidden`) | Low to medium: a denied object-level access should leave a trace | Fix if the baseline confirms it: add a log line without personal data |
| W5 | Double-submit race in `store()` | Low: worst case is a second PENDING request. `release` locks the item row and returns 409 when it is not available, so no item can be loaned twice | Backlog, documented. A single-threaded `php artisan serve` cannot honestly reproduce a race |

At least three of W1, W2, W3, W4 and W6 are fixed. Defects found by the baseline that are not in this table are added to the register and ranked the same way.

## Consequences
- Disadvantages: Postman is a desktop tool and needs a manual run; results do not run on their own. The collection can drift from the API if routes change. Tests-first costs one extra round per fix. Per-account throttling lets someone lock a victim's login for a minute by failing on purpose (a small denial-of-service risk, accepted and written in the risk register).
- New responsibilities: keep the collection and the matrix updated after each API change; rerun the secret scans after Week 15; keep tokens out of captures.
- Not proven by Week 15: behavior under load (Week 16), behavior on a deployed server (Week 16), concurrency (W5), resistance to a real attacker.

## Evidence
`traceability-matrix.md`, the Postman collection and runner screenshots, PHPUnit and `flutter test` summaries, `week15-failure-predictions.txt`, and the defect and hardening reports written in the evidence round.

## Review condition
Revisit if the API routes change, if the project moves to a deployed server (Week 16), or if a fix breaks a baseline case.

## Update after the baseline (2026-11-10, chunk 1)
The decision above is not changed. The baseline run (`baseline-results.md`) confirmed W1, W2, W3, W4 and W6, and added W7 (404 message names an internal class) and W8 (`1abc` accepted as id 1), both found by reading the code while writing the collection. Defects are DEF-01 to DEF-07. W4 is justified: the Flutter history list uses only `created_at`, `action`, `from_status` and `to_status`, so `user_id` and `correlation_id` are not needed by any screen. W5 stays as backlog. Fix order for chunk 2 (proposal, developer approves first): DEF-01, DEF-03 (tests first), DEF-02, DEF-04, then DEF-07, DEF-05 and DEF-06 if time allows; no new routes or features.

## Update after the fixes (2026-11-11, chunks 2, 3 and 5)
The decision (Postman + PHPUnit + widget tests) is kept. What happened: W1, W2, W3, W4, W6, W7 and W8 were fixed as DEF-01 to DEF-07 in chunk 2, with the tests written first for the cases PHPUnit can see (nine failed, 52 passed; then 61 passed). W5 stayed in the backlog as planned. The new Flutter tests found two defects the baseline could not see (DEF-08 an unhandled error on a 401, DEF-09 an overflow at 360 px), which supports the three-layer choice. After the fixes the Postman collection passed 149 of 149 on fresh data. The "Review condition" is still open for Week 16 (deployment): headers and limits need checking behind a real server. Not done: browser tests A and C and the Caddy-stopped collection run (test B); AC-34 is Partial. Details are in `test-matrix.md` and `defect-register.md`.
