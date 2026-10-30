# Retry and Recovery Policy (Week 13)

Written from `backend/config/reminders.php`, `SendReminderJob.php`, `ReminderPlanner.php`, `FakeNotifier.php` and `routes/console.php`. These are the rules the automation follows. Results of running them are in `test-matrix.md` (AC-03, AC-09, AC-10, AC-11, AC-13) and `week13-failure-predictions.txt`.

## Trigger

- `reminders:run` is scheduled daily at 08:00 (`withoutOverlapping`). `custody:summary` is scheduled daily at 17:00.
- Known limit: the schedule is defined, but nothing on the demo PC runs `schedule:run` automatically. In the demo, `reminders:run` is started by hand. A real deployment would add one cron entry or a service that calls `php artisan schedule:run` every minute.

## What gets queued

| Situation | Notification | Recipient | Limit |
|-----------|--------------|-----------|-------|
| Released item due within 24 hours | reminder | the borrower | max 3 per request |
| Released item past its due date | overdue escalation | every approver | max 2 per request and approver |

- At most one notification of the same kind per request and recipient every 20 hours, so running the command twice in a day does not spam.
- Once the limit is reached, the item is skipped and a `reminder.skipped_limit` log line is written.

## Retry policy (per notification)

| Item | Value |
|------|-------|
| Maximum attempts | 3 |
| Wait before attempt 2 | 5 seconds |
| Wait before attempt 3 | 15 seconds |
| What is retried | transient errors only (for example a timeout or a 503 from the notifier) |
| What is not retried | permanent errors (for example the recipient no longer exists); they go straight to a human |
| Retry forever? | No. After attempt 3 the job stops. |

Measured in the demo: retries came about 7 s and 22 s after the first attempt, because the queue polls a little late. This is not a defect.

## Recovery paths

1. **Success on first try:** status `SENT`, one `reminder.sent` log line.
2. **Transient failure, then success:** each failed attempt logs `reminder.attempt_failed`; the later success sets `SENT_AFTER_RETRY` and clears the last error.
3. **Permanent failure or attempts used up:** the job calls `failed()`, the status becomes `FAILED_NEEDS_HUMAN`, the last error is saved, `reminder.needs_human` is logged, and the job appears once in `failed_jobs`.
4. **Human handling:** an approver sees the item in the manual handling screen (`GET /api/manual-handling`) and marks it handled with a note (`POST /api/manual-handling/{id}/resolve`). Status becomes `HANDLED_MANUALLY`, and an audit entry records who did it.
5. **Safe to re-run:** a job that is already finished or taken over by a human does nothing if it runs again. One failing notification does not block the others.

## Logging (structured, no secrets)

Log lines carry a correlation id and ids only (notification id, request id, recipient id, attempt number). They contain no names, emails, message text, passwords or tokens. Event names: `reminders.run_finished`, `reminder.queued`, `reminder.skipped_limit`, `reminder.skipped_recent`, `notifier.delivered`, `reminder.attempt_failed`, `reminder.sent`, `reminder.permanent_failure`, `reminder.needs_human`, `reminder.handled_manually`.

Open gap: a full search of `laravel.log` for `Bearer`, `token` and `password` is not yet recorded (AC-12 stays Partial until it is).

## Failure injection (demo and tests only)

In `backend/.env`: `NOTIFIER_TRANSIENT_TAGS` (asset tags that fail the first 2 attempts, then work) and `NOTIFIER_ALWAYS_FAIL_TAGS` (asset tags that fail every attempt). Leave both empty outside demos.
