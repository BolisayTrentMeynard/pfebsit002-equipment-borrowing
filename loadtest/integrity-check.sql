-- Week 16, chunk 3: data integrity check after a counted load run. READ ONLY (SELECT statements only).
-- Run against the STAGING database only. In phpMyAdmin pick the database equipment_staging first, or from PowerShell:
--   & 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t < loadtest\integrity-check.sql
-- Fake data only. Nothing here prints a name, an email or a token.
-- Expected: checks 1, 2, 2b and 5 return ZERO rows; check 3 and 4 are comparisons you do with the k6 summary
-- (see the lines at the bottom of loadtest\results\<run>-summary.md).

SELECT DATABASE() AS database_in_use;   -- must say equipment_staging

-- 1. No borrower has two open requests (PENDING, APPROVED, RELEASED) for the same item. Expect 0 rows.
SELECT user_id, equipment_id, COUNT(*) AS open_requests
FROM borrow_requests
WHERE status IN ('PENDING', 'APPROVED', 'RELEASED')
GROUP BY user_id, equipment_id
HAVING COUNT(*) > 1;

-- 2. No item has two RELEASED requests at once. Expect 0 rows.
SELECT equipment_id, COUNT(*) AS released_now
FROM borrow_requests
WHERE status = 'RELEASED'
GROUP BY equipment_id
HAVING COUNT(*) > 1;

-- 2b. Every item with a RELEASED request is not 'available'. Expect 0 rows.
SELECT e.id AS equipment_id, e.status
FROM equipment e
JOIN borrow_requests r ON r.equipment_id = e.id AND r.status = 'RELEASED'
WHERE e.status = 'available';

-- 3. Rows added by the run. Compare with "201 answers to POST /requests" in the k6 summary (they must be equal
--    when the table was empty before the run; the baseline's 30 requests count if the baseline ran on the same data).
SELECT COUNT(*) AS borrow_requests_total FROM borrow_requests;

-- 4. Audit rows. Each successful write adds one audit row, a create adds one, a return adds TWO (return and close).
--    So audit_logs_total must be at least "successful writes" in the k6 summary.
SELECT COUNT(*) AS audit_logs_total FROM audit_logs;
SELECT action, COUNT(*) AS n FROM audit_logs GROUP BY action ORDER BY action;

-- 5. No request in a state the workflow cannot reach. Expect 0 rows.
--    RETURNED without a release, CLOSED without a return, RELEASED/RETURNED/CLOSED without an approval, REJECTED without a decision.
SELECT id, status
FROM borrow_requests
WHERE (status IN ('RELEASED', 'RETURNED', 'CLOSED') AND (released_at IS NULL OR decided_at IS NULL))
   OR (status IN ('RETURNED', 'CLOSED') AND returned_at IS NULL)
   OR (status = 'CLOSED' AND closed_at IS NULL)
   OR (status = 'REJECTED' AND decided_at IS NULL);

-- Context for the evidence: how the requests ended up.
SELECT status, COUNT(*) AS n FROM borrow_requests GROUP BY status ORDER BY status;
SELECT status, COUNT(*) AS n FROM equipment GROUP BY status ORDER BY status;
