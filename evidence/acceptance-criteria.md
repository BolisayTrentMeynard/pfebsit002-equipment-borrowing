# Acceptance Criteria (Week 13)

Each criterion is measurable. Expected results are written before testing. Actual results go in the test matrix later (evidence round).

| ID | Category | Given / When | Expected result |
|----|----------|--------------|-----------------|
| AC-01 | Normal | Valid borrower submits a request for an available item | HTTP 201, status PENDING, audit entry written |
| AC-02 | Normal | Approver approves, staff release with due date | Status APPROVED then RELEASED, `due_at` stored, 2 audit entries |
| AC-03 | Normal | Scheduler runs with one item due in under 1 day | One reminder SENT on first attempt, 1 notification record, log line with correlation id |
| AC-04 | Normal | Staff record return and inspection | Status RETURNED then CLOSED, item available again |
| AC-05 | Invalid input | Request with missing item, past date, or loan longer than the maximum | HTTP 422 with field errors, nothing saved |
| AC-06 | Invalid input | Invalid status jump (e.g. release a PENDING request) | HTTP 422/409, status unchanged |
| AC-07 | Unauthorized | Request without a token | HTTP 401, no data returned |
| AC-08 | Forbidden | Borrower tries to approve or release | HTTP 403, status unchanged, denial logged |
| AC-09 | Dependency failure | Notifier fails 2 times then succeeds | Exactly 3 attempts, final status SENT after retry, each attempt logged |
| AC-10 | Permanent failure | Notifier fails every time | Stops after 3 attempts (no endless retry), status FAILED_NEEDS_HUMAN, item appears in approver's manual-handling list |
| AC-11 | Limit | Item already has the maximum reminders | Job skips it, no new notification, skip is logged |
| AC-12 | Safety | Any log or response | No password, token, secret, or real personal data |
| AC-13 | Recovery | After AC-10, approver marks the item handled | Status changes to HANDLED_MANUALLY, audit entry written |
| AC-14 | UI | Flutter screens | Loading, empty, validation, unauthorized and server-error states each visible, works at desktop and narrow width |
