# Bounded Workflow and Scope (Week 13)

## Actors
| Actor | What they can do |
|-------|------------------|
| Borrower | Log in, submit a borrow request, see own requests |
| Approver | Approve or reject requests, receive overdue escalations, handle failed notifications (manual handling list) |
| Staff | Release an approved item with a due date, record the return and inspection result, close the request |
| Scheduler (system) | Runs the due-date check automatically; has no login |

## Trigger
- User actions: request, approve/reject, release, return/inspect.
- Automation: a scheduled job (Laravel scheduler, once per day, also runnable by hand with an artisan command for testing) checks released items.

## Trusted inputs
- Authenticated user identity and role (from the server, never from the request body).
- Equipment catalog and users (seeded fake data).
- Server clock for due-date checks.
- Anything from the Flutter app is untrusted and validated again on the server.

## Validation (server side)
- Required fields, equipment must exist and be available, requested dates must be in the future and within a maximum loan length (e.g. 7 days), reason text length limit.
- State changes only follow the allowed order: PENDING, then APPROVED or REJECTED, then RELEASED, then RETURNED, then CLOSED.

## Processing steps
1. Request saved with status PENDING.
2. Approver decision recorded.
3. Staff release sets `due_at`.
4. Daily job selects RELEASED items that are due within 1 day (reminder) or past due (overdue escalation to the approver).
5. Job checks the per-item notification limit (max 3 reminders and 2 escalations) and skips if reached.
6. Job calls the notifier with a timeout and correlation id. Retry up to 3 times with backoff on transient errors.
7. Outcome saved: SENT, SENT after retry, or FAILED_NEEDS_HUMAN.
8. Staff record return and inspection, request becomes CLOSED.
9. Daily custody summary lists items currently RELEASED.

## Outputs
- Updated request status, audit log entries, notification records, structured log lines with a correlation id, daily custody summary, manual-handling list.

## External dependencies
- Notifier: a fake local notification service (log or fake HTTP endpoint) that can be forced to fail. No real email, SMS or personal data.

## Explicit non-goals
- No real email or SMS sending, no real people or institutional data.
- No payments, fines, or penalties.
- No inventory purchasing or reorder logic.
- No mobile app and no public self-registration.
- No production deployment in Week 13.

## Week 13 required demonstrations
1. One success (reminder sent on first try).
2. One transient failure recovered by retry.
3. One permanent failure routed to a human (manual handling list).
