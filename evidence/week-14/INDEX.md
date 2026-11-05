# Week 14 Evidence Index: Secure Data Exchange and Encryption

Status (2026-11-05): design, code, manual tests and evidence round done. Committed state to be confirmed by the developer. All ten criteria are Pass in the test matrix. Raw captures (screenshots, terminal output) are kept on Google Drive and listed below.

## Design (Part 1)
| File | What it is |
|------|------------|
| [`scope.md`](scope.md) | Which exchanges are protected, chosen controls, out of scope |
| [`diagrams/data-flow.png`](diagrams/data-flow.png) (source `data-flow.mmd`) | Data-flow diagram with trust zones and numbered flows F1 to F8 |
| [`protection-requirements-matrix.md`](protection-requirements-matrix.md) | Data, protection need, threat, control |
| [`decision-record-003-message-protection.md`](decision-record-003-message-protection.md) | HTTPS only vs HMAC vs authenticated encryption |
| [`key-lifecycle-plan.md`](key-lifecycle-plan.md) | Generate, store, use, rotate, revoke |
| [`acceptance-criteria.md`](acceptance-criteria.md) | AC-15 to AC-24 |
| [`test-data-plan.md`](test-data-plan.md) | Fake data, keys and capture hygiene |
| [`week14-failure-predictions.txt`](week14-failure-predictions.txt) | Predictions to commit before running the failure tests |
| [`residual-risk-register.md`](residual-risk-register.md) | Known limits and decisions |

## Code
`backend/app/Services/MessageSigner.php`, `SignedHttpNotifier.php`, `backend/app/Http/Controllers/Api/NotifierReceiverController.php`, `backend/config/notifier.php`, `backend/app/Console/Commands/NotifierTamperDemo.php` and `GenerateNotifierKey.php`, tests in `backend/tests/Feature/SignedMessageTest.php`. Flutter default API address: `app/lib/config.dart`.

## Results and interpretation
| File | What it shows |
|------|---------------|
| [`test-matrix.md`](test-matrix.md) | Expected vs actual for AC-15 to AC-24 (10 Pass), deviations, failures and surprises, open gaps |
| [`interpretation-and-maintainability-note.md`](interpretation-and-maintainability-note.md) | What the results show and do not show, maintenance notes |
| [`week14-failure-predictions.txt`](week14-failure-predictions.txt) | Predictions committed at 4:37 PM, with actual results added underneath |

## Screenshots and captures (on Google Drive, folder `Week 14`, not in the repo)
File names are the ones to use when saving to Drive. Sorting and renaming is done by the developer from the `Week 14` PC folder.

| Name to use | What it shows | Criteria |
|-------------|---------------|----------|
| `week14-caddy-running-log.png` | Caddy log with the local CA installed | AC-21 |
| `week14-up-page-secure.png` | `https://localhost:8443/up` with the "Connection is secure" popup | AC-21 |
| `week14-app-borrower-https.png` | App logged in as a borrower over HTTPS (empty list) | AC-21 |
| `week14-ac21-devtools-login-request.png` | DevTools Headers: Request URL `https://localhost:8443/api/login`, `Via: 1.1 Caddy` | AC-21 |
| `week14-network-page-load-1.png`, `-2.png` | Dev-server page load, app logged out. Supporting only: does not prove API calls | none |
| `week14-tests-47-passed.png` | `php artisan test`, 47 passed | AC-15 to AC-20 |
| `week14-testA-tamper-table.png` | `notifier:tamper-demo` table | AC-15 to AC-18 |
| `week14-testA-rejected-log-lines.png` | `notifier.message_rejected` lines (ids and reasons only) | AC-16 to AC-18, AC-20 |
| `week14-testB-worker-and-log.png` | Worker output and `sent_signed` / `delivered_signed` lines | AC-23 |
| `week14-testC-worker-and-log.png`, `week14-testC-manual-handling.png` | Caddy stopped: attempts and Manual handling list | AC-22 |
| `week14-testD-login-variant.png`, `week14-testD-overdue-error.png`, `week14-testD-after-recovery.png` | Network-error states and recovery | AC-22 |
| `week14-testE-worker-and-log.png` | Short key: permanent failure and needs_human lines | AC-19 |
| `week14-ac20-log-search.png` | Key length and key search in `laravel.log` (`False`), search for `X-Message-Signature`, `Bearer`, `password` (`False`) | AC-20 |
| `week14-ac24-secret-scan.png` | `git ls-files` check (nothing tracked) and history counts for the key and `APP_KEY` (0 and 0) | AC-24 |

Check before uploading or keeping a capture: no `.env` content, no key, no `Authorization` value, no token. The first key was visible in one screenshot and was regenerated; do not keep that screenshot in the shared folder.

## Known gaps (details in the test matrix)
- AC-20 and AC-24 are Pass. The scans cover this repository's history and the log as of 2026-11-05; rerun them after Week 15.
- AC-22: pressing "Try again" after recovery was not shown (recovery was shown by a tab switch).
- AC-19: the app card text "Signing is not configured correctly." was not captured.
- Local development certificate only (R1); sender and receiver run on the same machine.
- Week 14 Activities 1 and 2 are not written. The instructor said on 2026-10-29 that the module's Activities are not required.
