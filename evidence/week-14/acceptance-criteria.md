# Acceptance Criteria (Week 14)

Numbering continues from Week 13 (AC-01 to AC-14). Expected results are written before testing. Actual results go in the test matrix in the evidence round.

| ID | Category | Given / When | Expected result | Checked by |
|----|----------|--------------|-----------------|------------|
| AC-15 | Normal | Sender posts a correctly signed, fresh message | HTTP 202 `accepted`, log line `notifier.delivered_signed` | `SignedMessageTest`, tamper demo case 1 |
| AC-16 | Tamper | Body changed after signing, timestamp changed, or signed with another key | HTTP 401 `bad_signature`, nothing delivered | tests, tamper demo cases 2 and 5 |
| AC-17 | Expired | Correctly signed message older (or newer) than 300 s | HTTP 401 `expired`, nothing delivered | tests, tamper demo case 3 |
| AC-18 | Replay | The same message is sent twice | First 202, second HTTP 409 `replayed` | tests, tamper demo case 4 |
| AC-19 | Config | Key missing or shorter than 32 characters | Nothing is signed or accepted (sender fails to FAILED_NEEDS_HUMAN, receiver answers 500); no unsigned fallback | tests |
| AC-20 | Safety | Any log line or response from the secure exchange | No key, signature, token, password or message body; only ids and reason codes | tests (rejection log) and a log search by the developer |
| AC-21 | Transport | Flutter app with default settings, Caddy running | API calls go to `https://localhost:8443/api`, browser shows a secure connection | developer capture (Network tab, Fetch/XHR) |
| AC-22 | Resilience | Caddy stopped while the app is open, or while the worker sends | App shows its network-error state; the worker retries 3 times and the item goes to FAILED_NEEDS_HUMAN | developer capture, prediction written first |
| AC-23 | End to end | `NOTIFIER_DRIVER=signed_http`, demo reminders run | Items end as SENT; `notifier.sent_signed` and `notifier.delivered_signed` show the same `message_id` | developer capture |
| AC-24 | Secrets | Secret scan of the repo and the evidence zip | No signing key, `.env`, token or password found (dev-only seed passwords and test keys excluded and named) | developer scan |
