# Test Matrix (Week 14)

Expected results come from `acceptance-criteria.md`, written before testing. Failure-test predictions are in `week14-failure-predictions.txt`, committed at 4:37 PM on 2026-11-05, before the manual runs (4:45 PM onward, per the developer's screenshots). Raw screenshots are on Google Drive (see `INDEX.md`). Test date: 2026-11-05. Tester: the developer. The matrix and notes were written by Claude from the code, the developer's results and screenshots; Claude did not run any code itself.

Method key: **Auto** = automated test (`php artisan test`, 47 passed, 127 assertions, on MySQL). **Demo** = `notifier:tamper-demo` (test A). **Run** = hand-run end-to-end test with the worker (tests B to E in the predictions file). **UI** = checked by hand in Chrome.

| ID | Case | Expected | Actual | Method and evidence | Result |
|----|------|----------|--------|---------------------|--------|
| AC-15 | Valid signed, fresh message | 202 `accepted`, log line `notifier.delivered_signed` | 202. In test B all 3 reminders were SENT on attempt 1 with `notifier.sent_signed` and `notifier.delivered_signed` | Auto: `test_ac15_valid_message_is_accepted`. Demo case 1. Run B | Pass |
| AC-16 | Altered body, altered timestamp, or another key | 401 `bad_signature`, nothing delivered | 401 for altered body (case 2) and wrong key (case 5), both logged as `bad_signature` | Auto: three `test_ac16_...` tests. Demo cases 2 and 5 | Pass |
| AC-17 | Expired (and future) message with a good signature | 401 `expired` | 401 for the message signed 10 minutes ago, logged as `expired` | Auto: `test_ac17_...` (two tests, 301 s past and future). Demo case 3 | Pass |
| AC-18 | Same message sent twice | First 202, second 409 `replayed` | First 202, second 409, logged as `replayed` | Auto: `test_ac18_...`, `test_a_bad_signature_does_not_use_up_the_message_id`. Demo case 4 | Pass |
| AC-19 | Key missing or shorter than 32 characters | Nothing signed or accepted; no unsigned fallback | Run E (second attempt, key set to `short`): no request left the worker; 3 `reminder.permanent_failure` (attempt 1) and 3 `reminder.needs_human` (attempts 1). The receiver side (answers 500) is covered by the automated test only | Auto: `test_ac19_missing_or_short_key_is_refused_not_accepted`. Run E | Pass (sender side run by hand, receiver side by test). The app card text "Signing is not configured correctly." was not captured |
| AC-20 | No key, signature, token, password or body in logs or responses | Only ids and reason codes | Log search run by the developer on 2026-11-05 over `backend/storage/logs/laravel.log`: the signing key value (length 64, never shown) was not found (`False`); `X-Message-Signature`, `Bearer` and `password` were not found (`False`). The 5 rejection lines from the tamper demo were read earlier: ids and reason codes only. Receiver responses carry only a status and a reason code, which is covered by the automated test, not by a search of every response | Auto: `test_ac20_rejection_log_has_no_signature_key_or_body`. Manual: log search screenshots (`week14-ac20-log-search.png`) | Pass (logs searched; responses by test only) |
| AC-21 | App uses HTTPS by default | API calls go to `https://localhost:8443/api`, secure connection shown | DevTools Headers for `login`: Request URL `https://localhost:8443/api/login`, POST, 200, `Via: 1.1 Caddy`. `https://localhost:8443/up` shows "Application up" with the "Connection is secure" popup. Default in `app/lib/config.dart` is the HTTPS address | UI: DevTools Headers capture, popup capture, Caddy running log | Pass (development certificate only, see R1) |
| AC-22 | Caddy stopped | App shows its network-error state; worker retries 3 times, then FAILED_NEEDS_HUMAN | Worker (run C): 9 `reminder.attempt_failed` (attempts 1 to 3 for each of 3 items), then 3 `reminder.needs_human`; the Manual handling list showed the 3 items with "Attempts: 3" and "Last error: Receiver not reachable." App (run D): see the deviation below | Run C, Run D | Pass, with a recorded deviation in the app part |
| AC-23 | End to end with `signed_http` | Items end SENT; `notifier.sent_signed` and `notifier.delivered_signed` share the `message_id` | `migrate:fresh --seed`, DemoScenarioSeeder, `reminders:run` queued 3, `queue:work`: 3 jobs done once each, same `message_id` in both log lines, `reminder.sent` attempts 1 SENT | Run B | Pass |
| AC-24 | Secret scan of repo and evidence zip | No key, `.env`, token or password (dev-only seed passwords and test keys excluded and named) | Developer's scan on 2026-11-05: `git ls-files` shows no tracked `.env`, `.sqlite` or `.pem` file; `git log --all -S` finds the signing key in 0 commits and the `APP_KEY` value in 0 commits. Claude also scanned the clean zip: no `.env`, SQLite, key or certificate files; no `APP_KEY` value; no long key or token strings; the only `Bearer` text is the fake `not-a-real-token` in a test. Dev-only password `DevPass123!` appears in `DatabaseSeeder.php`, two backend/Flutter tests and two Week 13 evidence files (known, fake). Automated test keys are made-up strings marked `ONLY`. The first signing key (regenerated after it appeared in a screenshot) was not searched for; it no longer works | Developer's scan (`week14-ac24-secret-scan.png`) and Claude's zip scan | Pass |

Result: 10 Pass, 0 Partial. AC-20 is Pass for logs only (responses by automated test), and AC-19 and AC-22 carry the recorded notes above.

## Extra checks (beyond the criteria)

| Case | Result | Evidence |
|------|--------|----------|
| Week 13 behaviour unchanged (default driver `fake`) | Pass | Auto: the 31 Week 13 tests still pass inside the 47 |
| Retry after an accepted-but-lost response uses a new message id, so it is not treated as a replay | Pass | Auto: `test_sender_to_receiver_end_to_end_and_retry_is_not_a_replay` |
| Sender maps receiver answers to retry or human (4xx permanent, network error, 5xx and 429 transient) | Pass | Auto: `test_sender_maps_failures_to_retry_or_human` |
| Previous key accepted during rotation only | Pass | Auto: `test_previous_key_is_accepted_during_rotation_only` |
| Message carries only ids and a type | Pass | Auto: `test_sender_posts_signed_ids_only_over_the_configured_url` |

## Deviations from the predictions (kept in the record)

1. **Test D, first run.** Predicted: network-error message with a "Try again" button. Actual: the app showed the login screen ("Could not reach the server...", two `login` requests failed with `ERR_CONNECTION_REFUSED`). Cause: the browser was reloaded, which drops the in-memory token, so the app went back to login. This is a valid variant of the network-error state, not the predicted screen.
2. **Test D, second run.** The Overdue tab showed "Cannot reach the server" with a "Try again" button, as predicted. After Caddy was started again, the Manual handling list loaded, but recovery was shown by switching tabs, not by pressing "Try again". Pressing "Try again" after recovery was therefore not shown.
3. **Test E, first attempt.** The `.env` edit was not saved, so the real key was still used and all 3 reminders were SENT. This is recorded as a non-result, not a pass. The second attempt gave the predicted result.
4. **Network screenshots, first two.** They show the Flutter dev-server page load (script files on `localhost:9595`) with the app logged out, not API calls, so they do not prove AC-21. The `login` Headers capture does.

## Failures and surprises (kept in the record)

1. **Signing key shown in a screenshot.** The first key appeared in a screenshot, so it was regenerated. Rule kept from then on: `.env` stays closed in captured screenshots (`APP_KEY` is also visible there).
2. **`.env` quoting.** In a `.env`, double quotes treat `\U` as an escape and break parsing of a Windows path. `NOTIFIER_CA_BUNDLE` needs single quotes, and the path contains a space. `.env.example` and the README were fixed in this round.
3. **Duplicate key in `.env`.** The first line wins, so a second `NOTIFIER_SIGNING_KEY` line was ignored. This caused the Test E non-result. Fix: comment out the old line and check with a length check, never by showing the key.
4. **`php artisan serve` needs a restart after `.env` changes** (together with `config:clear`).
5. **Caddy from VS Code.** VS Code does not see `caddy` until it is fully restarted, so Caddy runs in a standalone PowerShell window. Chrome also had to be fully restarted before it trusted the Caddy certificate.
6. **`X-Powered-By: PHP/8.2.12` response header** reveals the PHP version. Not fixed in Week 14; it is on the Week 15 hardening list (`residual-risk-register.md`, R10).

## Gaps still open

- Optional: capture the Manual handling card text "Signing is not configured correctly." for Run E, show "Try again" recovering the list, and add a Fetch/XHR-filtered Network capture of a list request after login.
- The scans cover this repository's history only, and the log file as it was on 2026-11-05. Run them again after the Week 15 changes.
- Not covered by any test: clocks that differ by more than 300 s (same machine here), production certificates, a receiver on a separate server.
