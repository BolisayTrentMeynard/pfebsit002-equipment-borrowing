# Week 13, Activity 2: Verification and Improvement Activity

Task: inject controlled failures and use logs and records to show that duplicate processing, endless retries and silent data loss are prevented.

Build: branch `week-13-automation`, backend commit `f6eb5e5` plus predictions follow-up `d25810a`.
Environment: Windows PC, PHP 8.2.12, Laravel 12, SQLite, database queue.
Test data: fake only (DemoScenarioSeeder: 3 released items). Role that accepts the result: Approver.

## Method
1. Write predictions with date and time BEFORE running (`week13-failure-predictions.txt`, 2026-10-29 8:47 AM Asia/Manila).
2. Inject failures with two settings in `backend/.env`: `NOTIFIER_TRANSIENT_TAGS=EQ-002` (fails twice, then works) and `NOTIFIER_ALWAYS_FAIL_TAGS=EQ-003` (fails every time).
3. Run `reminders:run`, run `queue:work`, then read the `reminder_notifications` table, `failed_jobs` count and `reminder.*` log lines.
4. Run the automated tests as the regression set.

## Failure injection results (reported by the developer)

| Case | Injected failure | Predicted | Actual | Match |
|------|------------------|-----------|--------|-------|
| EQ-001 | none | SENT, 1 attempt | SENT, 1 attempt | yes |
| EQ-002 | fails attempts 1 and 2 | SENT_AFTER_RETRY, 3 attempts, about 20 s | SENT_AFTER_RETRY, 3 attempts, about 22 s | yes (timing close) |
| EQ-003 | fails every attempt | FAILED_NEEDS_HUMAN, 3 attempts, in manual-handling list, 1 failed_jobs row | same; last_error "Simulated notifier timeout."; failed_jobs count 1 | yes |
| Isolation | EQ-003 failing | EQ-001 and EQ-002 still sent | still sent | yes |
| Recovery | approver resolves item with a note | HANDLED_MANUALLY, list empty | same | yes |

Automated regression set: 31 tests passed (developer's run).

## How each risk is prevented

| Risk | Control in the code | Evidence |
|------|--------------------|----------|
| Endless retries | `max_attempts = 3`, backoff 5 s and 15 s, then `failed()` sets FAILED_NEEDS_HUMAN | EQ-003 stopped at 3 attempts; test `test_ac10_permanent_failure_stops_after_3_attempts_and_goes_to_human` |
| Duplicate processing | job only works on PENDING notifications; max 3 reminders and 2 escalations per request and recipient; 20-hour no-repeat rule | tests `test_ac11_limit_reached_means_no_new_notification`, `test_running_twice_in_a_day_does_not_spam` |
| Silent data loss | every notification has a row with status, attempts and last_error; failures are logged and listed for a human; audit entry when resolved | tinker output, `reminder.*` log lines with one correlation id, manual-handling list |
| One failure blocking others | one job per notification | test `test_one_failing_item_does_not_block_the_others`; Case 1 and 2 sent while Case 3 failed |

## Improvement applied and rerun
- Found during the run: the first log screenshot only showed lines from the automated tests, not the demo. Fix: retook the screenshot from the demo run so the evidence matches the run (recorded in the predictions file, not hidden).
- Small timing difference: retries came about 7 s and 22 s after the first attempt instead of exactly 5 s and 20 s, because the worker polls the queue. Documented as expected behavior, no code change.

## Limitations
- The notifier is fake, so real network timeouts are not tested.
- The no-repeat rule uses the server clock; clock or timezone errors are a risk (logs and the database are in UTC, 8 hours behind Manila).
- The worker must be running; a stopped worker leaves jobs waiting (not yet alerted).
- Prototype, not production ready.

## Observed facts, conclusions, recommendation
- Observed: all five cases matched the written predictions; 31 tests passed.
- Conclusion: for this small workflow, the three risks are controlled by bounded retries, status checks and limits, and per-item records.
- Recommendation: add a worker health check and a manual-handling count on the approver screen; repeat the test matrix after the Flutter screens are done.
