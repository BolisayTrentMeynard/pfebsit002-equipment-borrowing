-- Week 16, chunk 4: extra data checks after the MySQL-stopped run. READ ONLY (SELECT only). Staging database only.
-- Run it AFTER the standard loadtest\integrity-check.sql (which must also print nothing under checks 1, 2, 2b and 5):
--   & 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t -e "source loadtest/integrity-check-extra.sql"
-- Aim: find a PARTIAL write, which would mean a request was changed without its audit row, or an item moved without its request.
-- Every state change runs in one transaction, so checks 6, 7 and 8 should print NOTHING (no table at all).
-- Valid only on a database reset WITHOUT the optional volume rows (those CLOSED rows have no audit rows).
-- Fake data only. Nothing here prints a name, an email or a token.

SELECT DATABASE() AS database_in_use;   -- must say equipment_staging

-- 6. A request with no 'submit' audit row (the request and its audit row are written in one transaction). Expect 0 rows.
SELECT r.id AS request_id, r.status
FROM borrow_requests r
LEFT JOIN audit_logs a ON a.borrow_request_id = r.id AND a.action = 'submit'
WHERE a.id IS NULL;

-- 7. A request whose current status has no audit row that ended in that status. Expect 0 rows.
SELECT r.id AS request_id, r.status
FROM borrow_requests r
WHERE NOT EXISTS (
  SELECT 1 FROM audit_logs a WHERE a.borrow_request_id = r.id AND a.to_status = r.status
);

-- 8. An item 'on_loan' with no RELEASED request (the reverse of check 2b). Expect 0 rows.
SELECT e.id AS equipment_id, e.status
FROM equipment e
WHERE e.status = 'on_loan'
  AND NOT EXISTS (SELECT 1 FROM borrow_requests r WHERE r.equipment_id = e.id AND r.status = 'RELEASED');

-- Context: how many 'submit' audit rows there are, to compare with created_201 in the k6 summary.
SELECT COUNT(*) AS submit_audit_rows FROM audit_logs WHERE action = 'submit';
