# Postman baseline: how to run it (Week 15, chunk 1)

Files in this folder: `equipment-borrowing-week15.postman_collection.json` (73 requests: S-1 to S-6 and P-01 to P-67), `equipment-borrowing-local.postman_environment.json` (placeholders only). Expected results are written in each request's description. The tests assert the DESIRED behavior, so the weaknesses (W1 to W4, and the W7/W8 candidates) show up as failed checks on the unmodified code. That is the baseline. Nothing here was run by the author of the file (Claude); it was checked only as valid JSON and valid JavaScript.

## Before the run
1. Commit `week15-failure-predictions.txt` ALONE first (it now has an addendum for this chunk). Run nothing until that commit exists.
2. Start MySQL (XAMPP), then in `backend/`: `php artisan migrate:fresh --seed`. **Do this before EVERY run, including reruns and restarts after an error.** A run leaves requests behind (open requests block the next run). Setup request S-6 fails when the data is not fresh; if it fails, reset and start again, and do not count that run.
3. In the private `.env` set `APP_DEBUG=false` (Not found tests check for leaked traces; with `true` Laravel adds them). Restart `php artisan serve`. Write the value you used in `baseline-results.md`; use the same value for the rerun.
4. Start Caddy in its own PowerShell window: `caddy reverse-proxy --from localhost:8443 --to 127.0.0.1:8000 --disable-redirects`. `NOTIFIER_SIGNING_KEY` must be set in `.env` (P-52 reaches the signature check).
5. Postman: Import both files (import the collection as a NEW collection, do not merge into your old ones). Select the environment `Equipment Borrowing (local HTTPS)`.
6. Environment variable `password`: type the dev seed password into **Current value only**. Leave Initial value empty (Initial values sync to the cloud). Never paste it into a request, a screenshot or a file.
7. HTTPS: Keep certificate verification ON. If requests fail with a certificate error, Postman > Settings > Certificates > CA certificates: turn on and choose Caddy's `root.crt` (the same file used for `NOTIFIER_CA_BUNDLE`). If that fails, change `baseUrl` to `http://127.0.0.1:8000/api` and write that in the results. Never switch verification off.

## Run
1. Collection > Run. Select every folder EXCEPT `99 Manual`. Iterations 1, delay 0, turn **Save responses** off. Order matters (later folders use ids saved by earlier ones).
2. The first request of folder 10 waits 61 seconds on purpose (the per-IP login counter must reset). The run looks frozen for a minute: that is expected.
3. When it ends, screenshot the Runner summary (it shows request names and check results, not tokens). Do not export run results.
4. Fill in `../baseline-results.md` from the Runner (actual status and failed checks per request). Failures stay in the record.
5. The last request clears the token variables from the environment. If the run stopped early, clear them by hand (environment > clear current values of the `token...` rows) before any screenshot.

## Manual steps (after the full run, after waiting a minute for the login limit)
**Expired token (P-66, P-67).** Run `99 Manual` request M-1 on its own. Then in `backend/`:
```
php artisan tinker
>>> App\Models\ApiToken::latest('id')->first()->update(['expires_at' => now()->subMinute()]);
```
Then run M-2 (expected 401). Search `backend/storage/logs/laravel.log` for `auth.rejected` and `api/me`: the line must not contain the token.

**W6 log check (P-35).** The Postman console (View > Show Postman Console) prints `W6 check: ... <correlation id>` for P-35. In PowerShell from the repo root:
```
Select-String -Path backend\storage\logs\laravel.log -Pattern "<that correlation id>"
```
Prediction: no line (denied object-level access leaves no trace). A line means W6 is disproved. Record either result.

**W4 field table.** Save the response body of P-49 (no token inside) and list its fields per role in `baseline-results.md`. P-50 is an empty list on fresh data; to review recipient fields you would need failed notifications (the Week 14 Test C steps). Optional.

## Also record (baselines for AC-37)
In `backend/`: `php artisan test`. In `app/`: `flutter test`. Write both counts in `baseline-results.md` (the log says 47 and 5 earlier; record what you actually get).

## Hygiene
No `.env` open in a screenshot. No Authorization header or login response body in a screenshot (the collection never prints the token). Raw captures go to `Downloads\FLUTTER\Week 15`, not into the repo.
