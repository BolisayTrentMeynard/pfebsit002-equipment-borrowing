# Residual Risk Register (draft, 2026-11-05; status to be completed after testing)

| ID | Risk | Likelihood / impact | Control now | Residual risk and decision |
|----|------|---------------------|-------------|----------------------------|
| R1 | Local development CA is not a public certificate authority, so this does not show production certificate validation | Certain / low (dev only) | Caddy local CA, browser shows secure | Accepted for the course. A real deployment needs a certificate from a public CA (Week 16 plan) |
| R2 | Shared secret: anyone with the key can forge messages | Low / medium | Key only in `.env`, 32+ chars, rotation steps, never logged | Accepted. Asymmetric signatures would remove the shared secret |
| R3 | Replay store is a cache; flushing the cache inside the 600 s window allows one replay | Low / low (a duplicate reminder) | Timestamp window still rejects old messages | Accepted |
| R4 | Clock difference over 300 s makes valid messages look expired | Low / low | Failure goes to a human after 3 tries, not silently lost | Accepted; same machine in this project |
| R5 | Response lost after the receiver accepted: the retry sends a new id, so one duplicate reminder is possible | Low / low | Per-item limits (3 reminders, 2 escalations) and 20 h minimum gap | Accepted |
| R6 | Traffic between Caddy and Laravel is plain HTTP (same machine) | Low / low | Loopback only | Accepted for local; use TLS or a socket in deployment |
| R7 | Token is in memory only, so a reload logs the user out; token is sent in a header, so XSS would expose it | Low / medium | No `innerHTML`, tokens expire in 8 h | Accepted; revisit in Week 15 |
| R8 | Login throttling exists (10 per minute) but is per IP only | Low / low | `throttle:10,1` | To review in Week 15 hardening |
| R9 | Dev-only seed passwords appear in Week 13 evidence | Certain / low (fake data) | Fake accounts, local only | Optional cleanup |
