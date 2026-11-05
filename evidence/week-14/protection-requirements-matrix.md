# Protection Requirements Matrix (draft, 2026-11-05)

For each piece of data: what must be protected (confidentiality C, integrity I, authenticity A, availability V), the threat, the control, and where the control is.

| Data | Where it moves or rests | Needs | Threat | Control | Where |
|------|--------------------------|-------|--------|---------|-------|
| Login password | Flutter to API | C | Read on the network | HTTPS | Caddy, `AppConfig.apiBaseUrl` |
| Login password | Database | C | Database leak | bcrypt hash (never reversible) | `users.password`, `Hash::check` |
| API token | Flutter to API, database | C, A | Stolen or guessed token | HTTPS, 60-char random token, only SHA-256 hash stored, expires in 8 h, in memory only in the app | `ApiToken`, `ApiTokenAuth` |
| Borrow requests and statuses | Flutter to API | I, A | Someone changes a request or acts without the right role | Server-side validation, role middleware, audit log | `BorrowWorkflow`, `RequireRole` |
| Notifier message (ids and type) | Worker to receiver | I, A | Altered, forged, expired or replayed message triggers a wrong or duplicate notification | HMAC-SHA256 signature, 300 s window, one-time id, HTTPS | `MessageSigner`, `NotifierReceiverController` |
| Notifier message content | Worker to receiver | C (low) | Read on the network | HTTPS only. Content is ids, no personal data, so no separate encryption | decision record 003 |
| Signing key | `.env` | C | Key in the repo, a log or a screenshot | `.env` git-ignored, `.env.example` empty, key never logged, minimum length 32, rotation with a previous key | `config/notifier.php` |
| Log files | `storage/logs/laravel.log` | C | Secrets or personal data written to logs | Log ids and reason codes only. Checked by search (no `Bearer`, token, password, key, signature) | AC-20 |
| Availability of notifications | Worker to receiver | V | Receiver down or slow | 5 s timeout, 3 tries with 5 s and 15 s waits, then FAILED_NEEDS_HUMAN | Week 13 retry policy |

Reading the table: the only item that needs integrity and authenticity without needing secrecy is the notifier message. That is why a MAC fits better than authenticated encryption here.
