# Chunk 2: Reminder automation (scheduler, queue, retries, human handling)

Status: built and tested by Claude in its own sandbox: 29 automated tests passed (16 old + 13 new), and a real end-to-end run with the queue worker and the real 5 s and 15 s retry waits gave the expected three outcomes. **Tested later on the developer's PC (31 tests passed; the demo matched the written predictions, see `week13-failure-predictions.txt`).**

## What was added (and why)
| Part | Files | Why |
|------|-------|-----|
| Notification table | `..._create_reminder_notifications_table.php`, `ReminderNotification` | keeps status, attempt count and last error for every notification (auditable) |
| Planner | `Services/ReminderPlanner.php` | picks RELEASED items due within 24 h (reminder to borrower) or past due (escalation to approver), applies limits (3 reminders, 2 escalations) and a 20-hour no-repeat rule |
| Command | `reminders:run` | the trigger; scheduled daily at 08:00 in `routes/console.php`, and runnable by hand for demos |
| Job | `Jobs/SendReminderJob.php` | one job per notification, 3 tries, wait 5 s then 15 s; after the last failure the status becomes FAILED_NEEDS_HUMAN |
| Notifier | `Notifier` interface, `FakeNotifier` | the external service stand-in. It only writes a log line and can be told to fail |
| Human handling | `GET /api/manual-handling`, `POST /api/manual-handling/{id}/resolve` | approver sees failed notifications and records how it was handled (note required, audit entry) |
| Custody summary | `custody:summary` command (daily 17:00) and `GET /api/custody?overdue=1` | the daily "items in custody" summary |
| Demo data | `DemoScenarioSeeder` | 3 released items so you can show the 3 outcomes quickly |
| Tests | `tests/Feature/ReminderAutomationTest.php` | AC-03, 09, 10, 11, 13 and more |

Why one job per notification: one failing notification cannot block the others (there is a test for it). This follows decision record 001.

## IMPORTANT: write your predictions BEFORE you run the failure demo
The module says to predict the result first. Write these (or your own version) in a note with the date and time before step 6 below:
- EQ-001 (success): status SENT, attempts 1.
- EQ-002 (transient failure): fails twice, works on try 3, status SENT_AFTER_RETRY, attempts 3, takes about 20 seconds.
- EQ-003 (permanent failure): fails 3 times, then stops, status FAILED_NEEDS_HUMAN, attempts 3, appears in the manual-handling list.

## Run steps (from `C:\dev\pfebsit002-week-13\backend`)
1. Extract the zip over the repo folder.
2. Open `backend\.env` (NOT `.env.example`) and add these two lines at the bottom, then save:
   ```
   NOTIFIER_TRANSIENT_TAGS=EQ-002
   NOTIFIER_ALWAYS_FAIL_TAGS=EQ-003
   ```
3. `php artisan config:clear`
4. `php artisan migrate:fresh --seed`
5. `php artisan test` (expect 29 passed)
6. Demo (after writing your predictions):
   - `php artisan db:seed --class=DemoScenarioSeeder`
   - `php artisan reminders:run` (expect: queued 3)
   - In a **second terminal** in `backend`: `php artisan queue:work` and leave it running for about 30 seconds. Do not use `--stop-when-empty`: it would quit during the retry waits.
   - Watch the worker lines: DONE, FAIL, FAIL ... and later DONE for the retry item. Press `Ctrl+C` to stop it when it goes quiet.
7. Check the results: `php artisan tinker --execute="echo App\Models\ReminderNotification::all(['id','borrow_request_id','status','attempts','last_error'])->toJson(JSON_PRETTY_PRINT);"`
8. Check the log lines: open `backend\storage\logs\laravel.log` and search for `reminder.` (queued, attempt_failed, sent, needs_human). Each line carries the same `correlation_id` for the run.
9. Human handling (with `php artisan serve` running): log in as `approver@example.test` (password `DevPass123!`), call `GET /api/manual-handling` with the token, then `POST /api/manual-handling/<id>/resolve` with body `{"note":"Called the borrower in person"}`. The list should then be empty.
10. Optional: `php artisan custody:summary` prints the table of items in custody.

To repeat the demo: step 6 again (the seeder resets the three items).

## Common errors and fixes
| Problem | Fix |
|---------|-----|
| `reminders:run` says queued 0 | the demo seeder was not run, or you already ran it less than 20 hours ago (the no-repeat rule). Run the seeder again, it resets the items |
| Worker shows nothing | check `QUEUE_CONNECTION=database` in `.env`, then `php artisan config:clear`; the `jobs` table exists after `migrate` |
| EQ-002 and EQ-003 both succeed | the two NOTIFIER lines are missing from `.env` (not `.env.example`); add them, run `php artisan config:clear`, rerun the seeder and the steps |
| Retry item stays PENDING | the worker was stopped too early; leave it running about 30 seconds |
| `Class ... not found` | `composer dump-autoload` |
| Old failed jobs in the way | `php artisan queue:clear` then `php artisan migrate:fresh --seed` |

## Capture while testing
1. Your written predictions with date and time (before the run).
2. `php artisan test` showing 29 passed.
3. The `reminders:run` output (queued 3) and the worker terminal showing DONE/FAIL lines.
4. The tinker output showing SENT, SENT_AFTER_RETRY (attempts 3) and FAILED_NEEDS_HUMAN (attempts 3).
5. The `reminder.` log lines from `laravel.log` (success, attempt_failed, needs_human) with the same correlation id.
6. The manual-handling list response and the list after resolving it (token hidden).
Check every capture for tokens or real data before saving.

## Known limits (be ready to explain)
- The notifier is fake: delivery is a log line. A real service would also need its own credentials (kept in `.env`, never in the repo).
- The worker must be running for notifications to send. If it is stopped, jobs wait in the `jobs` table.
- The no-repeat rule (20 hours) and the limits are settings in `config/reminders.php`.
- Escalations go to every approver in the system.
