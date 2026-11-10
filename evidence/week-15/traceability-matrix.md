# Traceability Matrix v1 (Week 15, written 2026-11-10 before any test run)

Every requirement or control is linked to the tests that check it. Nothing here is a result: the Result column says "Planned" or points to the earlier week's matrix. Test IDs `P-nn` (Postman) are assigned when the collection is written in chunk 1; until then the column names the folder and the planned case.

Layers: **P** = Postman collection, **U** = PHPUnit (`php artisan test`), **F** = Flutter widget test (`flutter test`), **M** = manual browser or terminal check.

## Part A: Earlier criteria, kept in the regression set
| Requirement | Existing checks | Week 15 role | Result |
|-------------|-----------------|--------------|--------|
| AC-01 to AC-04 normal lifecycle | `BorrowWorkflowTest` (submit, lifecycle, damaged return) | P Normal folder repeats it over HTTPS | Week 13 matrix: Pass. Week 15: Planned |
| AC-05, AC-06 invalid input and status jump | `BorrowWorkflowTest` | P Invalid and Boundary folders extend it | Week 13: Pass. Week 15: Planned |
| AC-07, AC-08 unauthorized and forbidden | `BorrowWorkflowTest` | P Unauthorized and Forbidden folders | Week 13: Pass. Week 15: Planned |
| AC-03, AC-09 to AC-11, AC-13 reminders, retry, manual handling | `ReminderAutomationTest` | U regression only; P covers manual-handling roles | Week 13: Pass. Week 15: Planned |
| AC-12, AC-20 safe logs | log searches by the developer | AC-38 repeats the scans | Pass for logs (Week 13 and 14). Week 15: Planned |
| AC-14 Flutter states | 5 widget tests, manual screenshots | AC-35 and AC-36 add state and layout tests | Week 13: Partial. Week 15: Planned |
| AC-15 to AC-19, AC-23 signed messages | `SignedMessageTest`, tamper demo | U regression; P replay case (cross-reference) | Week 14: Pass. Week 15: Planned |
| AC-21, AC-22, AC-24 HTTPS, resilience, secrets | developer captures | P runs over HTTPS; M Caddy-stopped run; AC-38 scan | Week 14: Pass. Week 15: Planned |

## Part B: New Week 15 criteria
| Criterion | Requirement or risk | Planned test | Layer | Rejected alternative | Evidence to keep | Limitation |
|-----------|---------------------|--------------|-------|----------------------|------------------|------------|
| AC-25 | Defensible baseline | Whole collection (about 33 requests) before any fix | P | Running only the happy path | Runner summary, expected vs actual table | Manual run; one environment (local) |
| AC-26 | Input handling, boundaries | Invalid folder (6), Boundary folder (4 pairs: days 0, 1, 7, 8; reason 2, 3, 255, 256) | P, U | Testing only valid input | Runner results, `php artisan test` count | Only the fields the API has |
| AC-27 | Authentication, token expiry | Unauthorized folder (no token, made-up token, expired token); new PHPUnit expired-token test | P, U | Trusting that `findValid` works without a test | Runner results, test output | The expired case needs the developer to set `expires_at` in the past for the Postman run |
| AC-28 | Function and object authorization | Forbidden folder (4: borrower approve, borrower manual-handling, staff approve, borrower views another's request) | P, U | Hiding the UI button only | Runner results, log lines | Roles are fixed to three |
| AC-29 | Error handling, information leakage | Not-found folder (unknown id, `abc`, unknown route) | P | Letting the framework page show | Runner results, body check | Debug mode must be off in `.env` to match deployment |
| AC-30 | Duplicate and idempotency | Duplicate folder (second open request, approve twice, replayed signed message) | P, U | Relying on the UI to prevent it | Runner results | Real concurrency not tested (W5) |
| AC-31 | Brute force (W1, R8) | Rate-limit folder; new PHPUnit throttle test written first | P, U | Raising only the per-IP limit | Failing-then-passing test output, 429 capture | Per-account lock can be abused for a one-minute lockout |
| AC-32 | Security headers, version exposure (W2, W3, R10) | PHPUnit header test written first; curl or DevTools capture | U, M | A web-server setting only (not testable here) | Test output, header capture | `X-Powered-By` cannot be asserted in PHPUnit |
| AC-33 | Data minimization (W4, conditional) | Field-by-field review of each role's responses | P, U | Returning whole models | Field table, test | Decided after the baseline |
| AC-34 | Timeout and dependency failure | `Http::fake` timeout test; widget test with a 16 s wait; Caddy-stopped run | U, F, M | Only stopping Caddy by hand | Test output, worker log, screenshot | A slow dependency is faked, not real |
| AC-35 | Client states | Widget tests for 403, 401, 422, 429, 500, unreachable | F | Manual screenshots only | `flutter test` output | Fake HTTP client, not the real API |
| AC-36 | Responsive layout, validation agreement | Narrow-width test; limits compared in code; desktop and narrow screenshots | F, M | Desktop only | Screenshots, limits table | Only Chrome tested |
| AC-37 | Regression safety | Baseline collection rerun and diff; both suites rerun | P, U, F | Rerunning only the failed cases | Before and after counts | Same data and machine |
| AC-38 | Secret safety | Repeat the AC-20 and AC-24 scans | M | Assuming nothing changed | Scan screenshots | Search terms only find known strings |

## Part C: Weakness candidates to test
W1 to W6 are listed with priorities in `decision-record-005-test-and-hardening-approach.md`. They are confirmed or disproved by the baseline run, and each confirmed one becomes a defect (DEF-nn) with a cause, a fix, a retest and a regression result.
