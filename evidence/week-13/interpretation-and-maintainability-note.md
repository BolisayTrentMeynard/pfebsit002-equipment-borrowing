# Interpretation and Maintainability Note (Week 13)

Project: #5 Equipment Borrowing Reminder System. Date: 2026-10-29.

## What the results show

- **The core workflow works end to end.** A borrower requests an item, an approver approves, staff release it with a due date, and staff record the return and inspection, which closes the request. Each step is role-checked on the server and written to the audit log (AC-01 to AC-08).
- **The three required automation outcomes happened as predicted.** In the demo run (predictions written at 8:47 AM, before the run), one reminder was SENT on the first attempt, one was SENT_AFTER_RETRY on attempt 3, and one stopped after exactly 3 attempts as FAILED_NEEDS_HUMAN. The failing one did not stop the other two. It reached the approver's manual-handling list and was closed with a note (AC-03, AC-09, AC-10, AC-13).
- **Retries are bounded.** The job tries 3 times (waits of 5 s and 15 s) and then stops. The failed job count in `failed_jobs` was 1, as expected.
- **The Flutter screens work against the real API** at desktop and narrow width, with validation, empty, network-error and not-allowed behavior shown.

## What the results do not show

- The notifier is a stand-in (`FakeNotifier`). It writes a log line. Nothing proves that a real email or SMS service would behave the same way.
- Timeouts, rate limits and partial failures of a real service are simulated by two switches, not observed.
- Two Flutter states (unauthorized and server error) are not fully evidenced yet (see `test-matrix.md`, gaps). AC-12 was closed for logs on 2026-10-30 (no matches for Bearer, token or password in `laravel.log`); response bodies were not searched systematically.
- All data is fake and the load is tiny (a handful of items). Nothing here says how the system behaves with hundreds of items.

## Maintainability notes

- **Limits are settings, not code.** The loan limit (7 days), the maximum of 3 reminders and 2 escalations, and the 20-hour no-repeat rule are in `config/reminders.php`. The tests and the written predictions depend on these numbers, so change them only with a reason.
- **One job per notification.** A failure stays local and the retry state sits in the `reminder_notifications` table, so a person can see attempts and the last error.
- **The worker must be running.** If it stops, jobs wait in the `jobs` table. A production setup would need the worker supervised and restarted.
- **Failure switches live in `.env`** (`NOTIFIER_TRANSIENT_TAGS`, `NOTIFIER_ALWAYS_FAIL_TAGS`) and are meant for demos only. They must be empty outside a demo.
- **Auth is a custom hashed-token table, not Sanctum** (`decision-record-002-api-auth.md`). It is small and tested, but it is code the developer owns and must maintain. The Flutter app keeps the token in memory only, so a page reload logs the user out.
- **Escalations go to every approver.** That is simple, but it would be noisy with many approvers.
- **Tests:** backend feature tests cover the AC list; the Flutter widget tests cover the main screens. The next weeks add security and integration tests on top.
