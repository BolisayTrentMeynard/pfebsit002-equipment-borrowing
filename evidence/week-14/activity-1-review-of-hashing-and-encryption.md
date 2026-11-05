# Week 14, Activity 1: Review of five uses of hashing or encryption

Project: #5 Equipment Borrowing Reminder System (scope in `scope.md`, boundaries unchanged). Data: fake test data only. Role that accepts the result: Approver (office supervisor).

## Task
Review five uses of hashing or encryption, identify the security objective in each, correct inappropriate choices, and explain the operational assumptions required for the corrected design.

## Assumptions and constraints
- Small office, one developer, one PC (PHP 8.2, Laravel 12, MySQL, Caddy for local HTTPS).
- The Flutter app is untrusted. Notifications carry only ids, never names or message text.
- Standard libraries only (Laravel `Hash`, PHP `hash`, `hash_hmac`, `hash_equals`, TLS in Caddy). Nothing home-made.
- Decision criteria: does the control match the security objective, is the key or secret kept out of code, and can it be tested.

## The five uses
| # | Use | Objective | Verdict | Corrected design or reason to keep |
|---|-----|-----------|---------|------------------------------------|
| 1 | Passwords stored with bcrypt (Laravel `Hash`, cost 12) | Passwords unreadable if the database leaks | Appropriate | A slow, salted password hash. Assumption: the cost is raised over time. Rejected alternative: plain SHA-256, which is fast to guess |
| 2 | API tokens stored as SHA-256 of a random 60-character string (`api_tokens.token_hash`) | A leaked database does not give working tokens | Appropriate | The token is long and random, so a fast hash is enough (a slow one is not needed). The plain token is shown once at login and kept in app memory only. Expiry is 8 hours. Assumption: the CSPRNG is sound |
| 3 | Notifier messages protected with HMAC-SHA256 plus timestamp and message id | Integrity and authenticity (no change, no forgery), no replay | Appropriate | Message holds only ids, so secrecy is not the goal. Constant-time compare with `hash_equals`. Key at least 32 characters, only in `.env`. See `decision-record-003-message-protection.md` |
| 4 | Traffic encrypted with TLS (Caddy on `https://localhost:8443`) | Nobody on the network reads tokens or messages | Appropriate for development only | Certificate comes from Caddy's local CA, so no production validation (R1). A real deployment needs a public CA certificate |
| 5 | Proposal: encrypt the notifier body with AES as well | Secrecy of the message body | Inappropriate, corrected | The body has no secret content, TLS already hides it in transit, and encryption alone does not stop replay or forgery. It would add key handling and no protection. Kept as MAC plus TLS |

Other proposals considered and rejected: a plain SHA-256 checksum of the body (anyone can recompute it, so it proves nothing), and Base64 of a token in the database (encoding is not protection).

## Comparison of two approaches for use 3
| | HTTPS only | HTTPS plus HMAC (selected) |
|---|-----------|----------------------------|
| Stops reading in transit | Yes | Yes |
| Stops a caller who can reach the URL from forging a message | No | Yes |
| Stops replay of a captured message | No | Yes (300 s window, one-time id) |
| Cost | None | One shared key to protect and rotate; clock must be close |
Risks of the selected option: the shared secret means anyone with the key can forge messages (R2), and the replay store is a cache (R3).

## Adverse case and safe behaviour
A message with a changed body, an old timestamp, a repeated id or a wrong key must be rejected with 401 or 409 and nothing delivered. Observed in the tamper demo: 202, 401, 401, 409, 401, 400 (`test-matrix.md`, AC-15 to AC-18).

## Limitations and next improvement
- Sender and receiver are the same app on one PC, and the certificate is a development one.
- Next improvement: add a production certificate and a separate receiver (Week 16), and consider asymmetric signatures so the receiver does not hold a key that can also sign.
- This is a course prototype, not production ready.

## Facts, conclusions and recommendations
- **Facts (observed):** 47 automated tests pass on MySQL; the tamper demo matched the written predictions; `login` goes to `https://localhost:8443/api/login`; the log and git history contain no signing key (`test-matrix.md`).
- **Conclusions (reasoned):** each of uses 1 to 4 matches its objective; use 5 would add cost without benefit for this data.
- **Recommendations:** keep MAC plus TLS, rotate the key with the documented steps, and revisit encryption only if message content ever includes personal data.
