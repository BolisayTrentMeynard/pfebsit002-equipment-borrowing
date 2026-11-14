# Postman on staging (Week 16, chunk 2)

The collection is NOT copied here. Use the Week 15 file `evidence/week-15/postman/equipment-borrowing-week15.postman_collection.json` (73 requests, 149 tests, unchanged). Only the environment differs: `equipment-borrowing-staging.postman_environment.json` (placeholders only; `baseUrl` is `https://localhost:9443/api`).

## Before the run
1. The predictions addendum for chunk 2 is committed alone and nothing has been run (see `../week16-failure-predictions.txt`).
2. Reset the staging data: `powershell -NoProfile -ExecutionPolicy Bypass -File deploy\staging-release.ps1 -SkipBuild`. **Do this before EVERY run**, including a rerun after an error. A run leaves requests behind and setup request S-6 fails on dirty data (Week 15 rule; that run is not counted).
3. Postman: Import the staging environment file. Select **Equipment Borrowing (staging HTTPS)** (check the top right: the dev environment points at port 8443, the dev API and the dev database).
4. Variable `password`: type the dev-only seed password into **Current value** only; leave Initial value empty.
5. Keep certificate verification ON. Caddy's local CA is the same one used for port 8443, so the setting from Week 15 still works. Never switch verification off.

## Run
Collection > Run, every folder EXCEPT `99 Manual`, iterations 1, delay 0, **Save responses** off. The first request of folder 10 waits 61 seconds on purpose. Screenshot the Runner summary (it shows names and checks, not tokens). Do not export run results.

## Compare with Week 15
Week 15 counted rerun: 149 tests, 149 passed, 0 failed (10:23:50, 2 min 26 s). Write the staging numbers next to them in the evidence round. A difference is a finding, even a small one (for example a different run time).

## Hygiene
No `.env.staging` open in a screenshot. No Authorization header or login response body on screen. Raw captures go to `Downloads\FLUTTER\Week 16`, not into the repo.
