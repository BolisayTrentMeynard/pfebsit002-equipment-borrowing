# Fictional Test Data and Environment Check

All data is made up. No real names, emails, phone numbers or school records.

## Users (seeded)
| Name | Role | Email (fake) |
|------|------|--------------|
| Test Borrower One | borrower | borrower1@example.test |
| Test Borrower Two | borrower | borrower2@example.test |
| Test Approver | approver | approver@example.test |
| Test Staff | staff | staff@example.test |

Passwords: simple dev-only passwords set in the seeder, never real ones, and never reused anywhere else.

## Equipment (seeded)
Projector A, Projector B, Laptop Cart 1, Extension Cord Set, Digital Camera 1, Portable Speaker.

## Scenario items for demos
- Item due tomorrow (success demo).
- Item due tomorrow with notifier forced to fail twice (retry demo).
- Item overdue with notifier forced to fail always (permanent failure demo).

## Environment file check
- `backend/.env.example` holds only non-secret placeholders (SQLite, `MAIL_MAILER=log`).
- Real `.env` is ignored by git. Verify with `git status` and `git ls-files | findstr .env` (should only show `.env.example`).
- The notifier URL (if used) points to `127.0.0.1` only. No production endpoint.
- Before any screenshot: check the address bar, tokens and console output.
