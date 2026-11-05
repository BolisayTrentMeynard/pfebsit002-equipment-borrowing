# Residual Risk Register (updated 2026-11-05 after the manual tests; R10 to R12 added)

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
| R10 | Response header `X-Powered-By: PHP/8.2.12` shows the PHP version (seen in the DevTools Headers capture) | Certain / low | None yet | To fix in Week 15 hardening (turn off `expose_php`, or remove the header at Caddy) |
| R11 | `.env` mistakes: double-quoted Windows path breaks parsing, a repeated key is ignored (first line wins), `artisan serve` not restarted after a change | Happened in testing / low (caused one non-result in test E) | README and `.env.example` now show single quotes, one line per key and the restart step; length check instead of showing the key | Accepted; operational habit |
| R12 | A key or `APP_KEY` can leak through a screenshot of `.env` | Happened once / medium | The first signing key was regenerated; `.env` stays closed in kept captures; scan for the key value in the repo and logs (AC-20, AC-24) | Scans run 2026-11-05: key and `APP_KEY` value not found in `laravel.log` or in git history. Accepted; keep `.env` closed in captures and rerun the scans after Week 15 |
