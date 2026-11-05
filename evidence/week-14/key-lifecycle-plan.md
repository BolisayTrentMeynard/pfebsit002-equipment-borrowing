# Key Lifecycle Plan: `NOTIFIER_SIGNING_KEY` (draft, 2026-11-05)

| Stage | Plan |
|-------|------|
| Generate | `php artisan notifier:generate-key` prints 64 random hex characters (32 random bytes from `random_bytes`). Never typed by hand, never reused from another system |
| Store | Only in `backend/.env` (git-ignored). `.env.example` has the name with an empty value. The key is never in code, tests, README, logs, screenshots or the evidence zip. Tests use made-up keys that protect nothing |
| Use | Read through `config('notifier.signing_key')`. Messages are refused if the key is missing or shorter than 32 characters (no unsigned fallback) |
| Rotate | 1) generate a new key, 2) put the OLD key in `NOTIFIER_SIGNING_KEY_PREVIOUS` and the NEW key in `NOTIFIER_SIGNING_KEY`, 3) `php artisan config:clear` and restart the worker, 4) after the 300 s window (messages in flight) empty `NOTIFIER_SIGNING_KEY_PREVIOUS`. The receiver accepts both during the overlap, the sender signs only with the new key |
| Revoke or suspected leak | Generate a new key, leave `NOTIFIER_SIGNING_KEY_PREVIOUS` empty (so the leaked key stops working at once), restart worker and server, check `laravel.log` for `notifier.message_rejected` |
| Retire | Remove old keys from `.env`; delete any copy |
| Who can read it | Only the developer on the development PC. In a real deployment: the server account that runs the app |

Not covered: a hardware key store or secret manager (out of scope for a local course project). Recorded in the residual-risk register.
