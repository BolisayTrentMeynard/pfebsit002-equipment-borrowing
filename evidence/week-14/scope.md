# Week 14 Scope: Secure Data Exchange (draft, 2026-11-05)

Same project, same registered scope (#5 Equipment Borrowing Reminder System). Week 14 adds protection to two exchanges that already exist. It does not add features or change the workflow.

## Exchanges that are protected
| # | Exchange | Sender | Receiver | Protection added |
|---|----------|--------|----------|------------------|
| E1 | Flutter app calls the API (login, requests, approvals) | Flutter Web app | Laravel API | HTTPS (Caddy, local certificate). Already in Week 13: hashed tokens with expiry, roles, validation |
| E2 | Reminder or escalation goes to the notifier | Queue worker (`SignedHttpNotifier`) | Receiver endpoint `POST /api/notifier/receive` | HTTPS plus a signed message (HMAC-SHA256, message id, timestamp). Receiver rejects altered, expired and replayed messages |

## What is in the message
Only ids and a type: `notification_id`, `type`, `request_id`, `recipient_id`, `attempt`. No names, emails or message text (data minimization). All project data is fake test data.

## Chosen controls and why
| Need | Control | Library |
|------|---------|---------|
| Nobody reads traffic on the network | HTTPS (TLS) | Caddy |
| Message cannot be changed or forged | MAC: HMAC-SHA256 | PHP `hash_hmac` |
| Old message cannot be sent again | Timestamp window (300 s) plus one-time message id | Laravel cache (atomic `add`) |
| Passwords and tokens not readable if the database leaks | Hashing (bcrypt for passwords, SHA-256 for tokens) | Laravel `Hash`, PHP `hash` |
| Key not in code | `NOTIFIER_SIGNING_KEY` in `.env` (git-ignored) | Laravel config |

Encryption of the message body is **not** added on purpose: the body has no secret content, and HTTPS already hides it in transit. See `decision-record-003-message-protection.md`.

## Out of scope (stays out, registered scope must not change)
- Real email or SMS, real users, real certificates from a public CA, user-to-user message encryption, a key management service.

## Environment
Windows PC, XAMPP MySQL, `php artisan serve` on 127.0.0.1:8000, Caddy on https://localhost:8443, Flutter Web in Chrome on localhost:9595.
