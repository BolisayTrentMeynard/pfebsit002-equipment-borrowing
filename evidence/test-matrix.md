# Test Matrix (Week 13)

Expected results come from `acceptance-criteria.md`, written before testing. Failure demo predictions are in `week13-failure-predictions.txt`, written before the run (2026-10-29, 8:47 AM). Raw screenshots are on Google Drive (see `INDEX.md`). Test date: 2026-10-29. Tester: the developer.

Method key: **Auto** = automated test (`php artisan test` or `flutter test`, all passing). **Demo** = hand-run demo with the worker. **UI** = checked by hand in Chrome.

| ID | Case | Expected | Actual | Method and evidence | Result |
|----|------|----------|--------|---------------------|--------|
| AC-01 | Valid borrower request | 201, PENDING, audit entry | As expected | Auto: `test_ac01_borrower_submits_request`. UI: new request form submitted | Pass |
| AC-02 | Approve then release with due date | APPROVED then RELEASED, `due_at` stored | As expected | Auto: `test_ac02_ac04_full_lifecycle`. UI: full cycle by hand | Pass |
| AC-03 | Reminder on first attempt | One SENT, 1 notification, log line with correlation id | SENT, attempts 1 | Auto: `test_ac03_...`. Demo: Projector A (EQ-001) | Pass |
| AC-04 | Return and inspection (good) | RETURNED then CLOSED, item available | As expected | Auto: `test_ac02_ac04_full_lifecycle`. UI: closed request history, `week13-chunk3b-request-closed-history.png` | Pass |
| AC-05 | Invalid input | 422 with field errors, nothing saved | As expected | Auto: `test_ac05_invalid_input_is_422_and_nothing_saved`. UI: `week13-chunk3b-new-request-validation.png` | Pass |
| AC-06 | Invalid status jump | 409, status unchanged | As expected | Auto: `test_ac06_invalid_status_jump_is_409` | Pass |
| AC-07 | No token | 401, no data | As expected | Auto: `test_ac07_no_token_is_401`. Chunk 1: 401 checked by hand | Pass |
| AC-08 | Borrower tries to approve or release | 403, status unchanged | As expected | Auto: `test_ac08_borrower_cannot_approve_or_release`. UI: borrower has no approve or release buttons | Pass |
| AC-09 | Notifier fails twice, then works | 3 attempts, SENT_AFTER_RETRY | SENT_AFTER_RETRY, attempts 3, about 22 s total (predicted about 20 s) | Auto: `test_ac09_...`. Demo: Projector B (EQ-002), worker output captured | Pass |
| AC-10 | Notifier always fails | Stops at 3 attempts, FAILED_NEEDS_HUMAN, in manual-handling list | FAILED_NEEDS_HUMAN, attempts 3, "Simulated notifier timeout.", 1 row in `failed_jobs` | Auto: `test_ac10_...`. Demo: Laptop Cart 1 (EQ-003); also shown again in the app on 2026-10-29 (Round B) | Pass |
| AC-11 | Item at reminder limit | Skipped, no new notification | As expected | Auto: `test_ac11_limit_reached_means_no_new_notification` | Pass |
| AC-12 | No secrets in logs or responses | None present | Not fully checked: reminder log lines and the token-hidden screenshots were reviewed by the developer; a full log search for tokens is not recorded | Auto: `test_token_is_stored_hashed` (token hashed at rest) | Partial |
| AC-13 | Approver marks failed item handled | HANDLED_MANUALLY, audit entry | As expected | Auto: `test_ac13_...`. Demo: list empty after resolve. UI: "Mark as handled" removed the card, `week13-chunk3b-manual-handling-resolve.png` | Pass |
| AC-14 | Flutter states and widths | Loading, empty, validation, unauthorized, server-error visible; desktop and narrow | Validation, wrong password, empty, network error, narrow layout shown. Unauthorized, server-error and loading states are built but not captured | Auto: 5 widget tests. UI: Round A and B screenshots (desktop and narrow) | Partial |

## Extra checks (beyond the criteria)

| Case | Result | Evidence |
|------|--------|----------|
| One failing notification does not block the others | Pass | Auto: `test_one_failing_item_does_not_block_the_others`; demo: EQ-001 and EQ-002 sent although EQ-003 failed |
| Running `reminders:run` twice does not spam | Pass | Auto: `test_running_twice_in_a_day_does_not_spam` |
| Manual handling is approver-only | Pass | Auto: `test_manual_handling_is_approver_only` |
| Borrower sees only own requests | Pass | Auto: `test_borrower_sees_only_own_requests` |
| Damaged return keeps item out of circulation | Pass | Auto: `test_damaged_return_keeps_item_out_of_circulation` |

## Failures and surprises (kept in the record)

1. **Compile error in Round A and again in Round B (`const` before a widget with `Semantics`).** `flutter test` stopped with 2 errors in `new_request_screen.dart` and `request_detail_screen.dart`. Cause: the same mistake as Round A (`const Center(` around a loading spinner). Fix: removed `const`. After the fix: 5 tests passed, 0 errors.
2. **Demo worker stopped too early.** On the repeat demo run, `queue:work --stop-when-empty` quit after the first attempts, because the retries were waiting 5 s and 15 s. The manual-handling list was still empty. Cause: the flag treats waiting jobs as "empty". Fix: run `queue:work` without the flag and wait about 40 s. Afterwards the failed item appeared in the list.
3. **First log screenshot showed the wrong lines.** It showed log lines from the automated tests, not the demo run. Retaken from the demo run.
4. **Retry timing.** The retries came about 7 s and 22 s after the first attempt, not exactly 5 s and 20 s as predicted. The queue adds a short polling delay. Not a defect.

## Gaps still open

- AC-12: search `laravel.log` for `Bearer`, `token` and `password` and note the result.
- AC-14: capture the unauthorized (expired session) and server-error states, or state that they are covered only by code.
