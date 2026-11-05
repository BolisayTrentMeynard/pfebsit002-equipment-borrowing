# Interpretation and Maintainability Note (Week 14)

Project: #5 Equipment Borrowing Reminder System. Date: 2026-11-05.

## What the results show

- **The receiver rejects what it should and accepts what it should.** A correctly signed, fresh message got 202. An altered body, a message signed with another key, a message signed 10 minutes ago and a replayed message got 401, 401, 401 and 409, and a message without signature headers got 400. Every row matched the prediction written before the run (AC-15 to AC-18, test A and 47 passing tests).
- **The protection works inside the real workflow, not only in a demo.** With `NOTIFIER_DRIVER=signed_http`, the three demo reminders went through the queue worker, the signed request and the receiver, and ended SENT on attempt 1. The sender log line and the receiver log line carry the same `message_id` (AC-23).
- **Failure still goes to a human.** With Caddy stopped, each reminder made exactly 3 attempts and then reached the approver's Manual handling list with "Receiver not reachable." (AC-22). A key that is too short stops the sender after 1 attempt without sending anything, so there is no unsigned fallback (AC-19).
- **The app uses HTTPS by default.** The login request goes to `https://localhost:8443/api/login` through Caddy (AC-21). With Caddy stopped, the app shows a network-error message, not a blank page.
- **The control matches the need.** The message holds only ids, so the goal is integrity and authenticity, not secrecy. A MAC (HMAC-SHA256) with a timestamp and a one-time id covers altered, forged, old and repeated messages. HTTPS covers reading the traffic. Body encryption was left out on purpose (`decision-record-003-message-protection.md`).

## What the results do not show

- **Sender and receiver are the same Laravel app on one PC.** The test proves the mechanism and the failure handling, not a separate service on another machine. Clock differences between two machines (R4) were not tested.
- **The HTTPS certificate comes from Caddy's local development authority.** It does not show how a public certificate authority, certificate renewal or a certificate error in production would behave (R1).
- **The shared secret is only as safe as the `.env` file.** Anyone with the key can forge messages (R2). The key was shown once in a screenshot and was regenerated. The rotation steps are planned (`key-lifecycle-plan.md`) and covered by an automated test for the previous key, but a full rotation on the running system was not demonstrated.
- **Log and repo safety are shown for what was searched.** An automated test, the developer's reading of the rejection lines and a search of `laravel.log` (key value, `X-Message-Signature`, `Bearer`, `password`: no matches) show ids and reason codes only (AC-20). A scan of tracked files and git history found no `.env`, key file, signing key or `APP_KEY` value (AC-24). Responses were checked by test, not by searching every response, and the scans must be run again after Week 15 changes.
- **Replay protection depends on a cache.** Flushing the cache inside the 600 s window would allow one replay (R3). If a response is lost after the receiver accepted, the retry has a new message id, so one duplicate reminder is possible (R5).
- **Not tested:** load, a real email or SMS service, other browsers, and the Caddy-to-Laravel hop, which is plain HTTP on the same machine (R6).

## Maintainability notes

- **Settings, not code.** Driver, URL, key, previous key, 300 s window and CA bundle are in `config/notifier.php` and `.env`. The default driver is `fake`, so Week 13 behaviour and its tests are unchanged.
- **`.env` mistakes are the most likely failures.** In this week they were a double-quoted Windows path, a duplicate key line (the first line wins) and a forgotten restart of `php artisan serve` after a change. The README now shows the single-quote form and the restart step.
- **Check keys by length and by a printed True/False or count, never by showing them.** An empty result proves nothing: print a number. Keep `.env` closed in any screenshot.
- **Tests do not depend on the real key.** `phpunit.xml` forces the fake driver and empty keys, and the test keys are made-up strings marked `ONLY`.
- **Failure switches for the Week 13 demo do not apply to `signed_http`.** To make a failure with the signed notifier, stop Caddy or use a short key.
- **Caddy must be started in its own PowerShell window** before the app, the worker or the tamper demo is used.
- **Next (Week 15 hardening list):** remove the `X-Powered-By` header, make login throttling per account as well as per IP, add an expired-token test, review which fields the API returns, and run the log scan again after the changes.
