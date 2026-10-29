# Week 13, Activity 1: Architecture and Decision Activity

Project: #5 Equipment Borrowing Reminder System (scope in `scope.md`, boundaries unchanged).
Data: fake test data only. Role that accepts the result: Approver (office supervisor).

## Task
Choose a trigger and execution pattern for three contrasting office workflows and defend each choice against latency, volume, reliability, security and staffing.

## Assumptions and constraints
- Small office: about 50 equipment items, a few borrowers per day, one approver, one or two staff.
- One developer, one laptop (PHP 8.2, SQLite, Laravel scheduler and database queue).
- Notifier is a fake local service; a real one would be an outside dependency that can fail.
- Anything from the Flutter app is untrusted; the server checks role and input.
- Human approval stays for exceptions (failed notifications, approval of requests).

## Decision criteria
Latency (how soon must it happen), volume, reliability on failure, security and privacy, staffing (who looks at it), effort to build and test.

## The three workflows

| # | Workflow | Selected trigger and pattern | Status in this project |
|---|----------|------------------------------|------------------------|
| W1 | Due-date reminders and overdue escalation | Time-based schedule (daily 08:00) that dispatches one queued job per notification | Implemented (`reminders:run`, `SendReminderJob`) |
| W2 | Daily custody summary for staff and approver | Time-based schedule (daily 17:00), single batch, no queue | Implemented (`custody:summary`, `GET /api/custody`) |
| W3 | Permanent notification failure | Event-driven: the failed job (event) puts the item on a human work list; no automatic retry | Implemented (`failed()` handler, `/api/manual-handling`) |

### W1: Due-date reminders and overdue escalation
- Question: how should the system notice items due soon or overdue and notify the right person?
- Option A (selected): daily schedule finds due items and queues one job per notification, with bounded retries (3 tries, 5 s and 15 s waits).
- Option B (rejected): one scheduled loop that sends and retries inside the loop.
- Why A: latency is not critical (a daily reminder is enough for 7-day loans), volume is small, and failure of one notification must not block the others. Retry and failed-job handling are built in and testable.
- Risks of A: a queue worker must be running; if it stops, jobs wait in the `jobs` table; one more part to explain. Escalations go to every approver.
- Security: recipient comes from the database, not the request; log lines carry a correlation id and no tokens or personal data.
- Staffing: nobody has to run it; the approver only sees the manual-handling list.
- Adverse case: notifier fails every time. Expected safe behavior: stop after 3 attempts, status FAILED_NEEDS_HUMAN, item shown to the approver (AC-10).

### W2: Daily custody summary
- Question: how do staff get a list of items currently out?
- Option A (selected): scheduled command at 17:00 prints or serves a table of RELEASED items (also on demand through `GET /api/custody?overdue=1`).
- Option B (rejected): update a summary every time an item changes (event-driven).
- Why A: the summary is only read once a day; a batch read is simple, always rebuilt from the source table, so it cannot drift out of date. Event-driven updates add more code and a way for the summary to be wrong after a missed event.
- Risks of A: the list is only as fresh as the last run (the API endpoint covers on-demand needs); it is read-only, so it sends nothing.
- Security: only Approver and Staff roles can call the endpoint (AC-08 style role check).
- Adverse case: borrower calls the endpoint. Expected safe behavior: HTTP 403, no data.

### W3: Permanent failure goes to a human
- Question: what should happen when a notification cannot be delivered?
- Option A (selected): the failure itself triggers a status change to FAILED_NEEDS_HUMAN. The approver sees it, acts outside the system (for example calls the borrower) and records a note, which sets HANDLED_MANUALLY with an audit entry (AC-13).
- Option B (rejected): keep retrying until it works.
- Why A: endless retries can hide a real problem and spam a dependency. A person can decide things the system cannot.
- Risks of A: depends on the approver checking the list; there is no alert yet if the list grows. Next improvement: a count on the approver's home screen (Flutter chunk 3).
- Security and staffing: approver-only endpoint (HTTP 403 for others), a note is required.
- Adverse case: the same item fails again later. Expected safe behavior: limits and the 20-hour no-repeat rule still apply.

## Comparison summary

| Criterion | W1 schedule + queue | W2 scheduled batch | W3 event to human |
|-----------|--------------------|--------------------|-------------------|
| Latency | up to 24 h (acceptable) | up to 24 h, or on demand by API | immediate when the last try fails |
| Volume fit | small, per item | small, one query | rare |
| Reliability | isolated per item, bounded retry | rebuilt each run, nothing to lose | depends on a person acting |
| Security | server-side recipient, no secrets in logs | role-restricted | role-restricted, note required |
| Staffing | none routinely | read only | approver time |

## Limitations and next improvement
- Prototype, not production ready. The notifier is fake, the worker is started by hand in demos, and one approver is assumed.
- Next improvement: show the manual-handling count in the Flutter app and add a health check for the queue worker (Week 16 topic).

## Observed facts, conclusions, recommendation
- Observed (developer's runs, see `activity-2-failure-injection.md` and `week13-failure-predictions.txt`): W1 and W3 behaved as predicted in the demo run.
- Conclusion: the schedule plus queue plus human hand-off pattern fits this small, low-latency-tolerance workflow.
- Recommendation: keep the pattern; revisit it if volume grows or the worker proves unreliable at deployment (see decision record 001, review condition).
