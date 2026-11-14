# Traceability Matrix v1 (Week 16; written 2026-11-14 before any run)

Every requirement or risk is linked to the check that tests it. Every Result column says "Planned". Nothing has been run or measured.

Layers: **K** = k6 load script, **U** = PHPUnit (`php artisan test`), **P** = Postman collection (Week 15, rerun on staging), **F** = Flutter widget test, **M** = manual check or script run by the developer.

## Part A: Earlier criteria kept in the regression set
| Requirement | Existing checks | Week 16 role | Result |
|-------------|-----------------|--------------|--------|
| AC-01 to AC-24 (Weeks 13 and 14) | `php artisan test` (61 tests), developer captures | U regression after every code change | Planned |
| AC-25 to AC-38 (Week 15) | Postman 149 tests, `php artisan test`, `flutter test` (15) | P rerun on staging (AC-47); U and F rerun after fixes (AC-50) | Planned |
| AC-34 Partial (notifier timeout) | Week 14 Caddy-stopped runs | Closed by AC-49 | Planned |

## Part B: New Week 16 criteria
| Criterion | Requirement or risk | Planned test | Layer | Rejected alternative | Evidence to keep | Limitation |
|-----------|---------------------|--------------|-------|----------------------|------------------|------------|
| AC-39 | Defensible workload assumptions | Workload models written and committed before any run | M (commit order) | Choosing load after seeing results | `workload-models.md`, commit hashes | Assumed, not measured traffic |
| AC-40 | Know normal latency | Single-user baseline, 30 repeats per endpoint | K | Reading latency from one browser load | k6 summary, results sheet | k6 shares the PC |
| AC-41 | Ordinary workload meets targets | Ordinary model, 5 users, 5 min steady | K, M (sampling script) | Only a single-user test | k6 summary, sampling CSV | Assumed mix; one IP for all users |
| AC-42 | Behavior at peak and spike | Peak, spike, optional short endurance, integrity SQL after each | K, M | Only an average-load test | k6 summary, SQL output | Single-threaded server; 20 min is not endurance |
| AC-43 | Liveness and readiness | PHPUnit for 200 and 503; manual check during MySQL stop | U, M | One health endpoint for both | Test output, poll capture | Checks the database and queue table only, not the notifier receiver |
| AC-44 | Operable, safe logs | PHPUnit asserts line fields; log search for personal data | U, M | Logging full request bodies | Test output, search capture | One log file, debug level on dev |
| AC-45 | Monitoring and ownership | Monitoring map; incident walkthrough using only those signals | M | Alerts with no owner | `monitoring-map.md`, walkthrough capture | No external monitoring tool; detection is by the developer polling |
| AC-46 | Environment separation | Environment comparison; staging uses its own database and settings | M | Testing on the dev database | `environment-comparison.md`, `.env.staging.example` | Staging is local, not a separate host; production column is on paper |
| AC-47 | Repeatable release | Release script, health checks, Postman and PHPUnit on staging, form screenshots at two widths | M, P, U | Hand-run steps from memory | Script output, Postman summary | Windows PC only; same-origin Flutter build served by Caddy |
| AC-48 | Dependency failure | MySQL stopped during ordinary load; prediction first | K, M | Stopping the app only | k6 output, health poll, log lines | Local MySQL stop, not a network failure |
| AC-49 | Notifier timeout | PHPUnit with `Http::fake` for timeout, retries, FAILED_NEEDS_HUMAN | U | Waiting 5 s in a real run | Test output | Fake HTTP, not a real slow receiver |
| AC-50 | Justified performance fixes | Tests first; before and after on the same workload | U, K | Optimising before measuring | Before/after table | Gains apply to this setup |
| AC-51 | Recovery | `mysqldump`, restore to scratch DB, rollback to `week-15` | M, P | Assuming a backup works | Row counts, rollback capture | Small database; no destructive migration tested |
| AC-52 | Honest readiness decision | Conditional recommendation, tabletop review | M | Declaring "production ready" | `readiness-recommendation.md` | Opinion of the developer, written with Claude |
| AC-53 | No secrets leaked | Repeat the AC-20 and AC-24 scans including new log line, scripts and dumps | M | Trusting that nothing changed | Scan output | Searches are only as good as the strings searched |

## Part C: Performance candidates to risk to check
| Candidate | Risk | Checked in | Expected from code reading | Result |
|-----------|------|------------|----------------------------|--------|
| P1 unbounded lists | Slow responses and large payloads as data grows | Volume run, AC-42; fix in AC-50 | Confirmed at volume | Planned |
| P2 `last_used_at` write per call | Extra database write on every request | Peak run profile, AC-50 | Small effect | Planned |
| P3 database cache, queue, sessions; debug log | Contention and log growth | Sampling, log size, AC-41 and AC-42 | Little effect on reads | Planned |
| P4 single-threaded server | Throughput ceiling | Peak and spike, AC-42 | Main limit at peak; stated, not fixed | Planned |
| P5 Flutter release size, no compression | Slow first load | Release build size, browser load, AC-47 | Visible on first load only | Planned |

## Part D: Tests added in chunk 1 (written 2026-11-14; run by Claude in its sandbox on SQLite only, so the real result column stays "Planned" until the developer runs them on MySQL)
| Criterion | Test (file) | What it checks |
|-----------|-------------|----------------|
| AC-43 | `OperationsTest`: ready 200 ok without login; ready checks the queue table (503 when the table is missing); liveness is `/up` | Body is exactly ok or fail, no login needed |
| AC-43 | `OperationsDownTest`: ready 503 with no detail; `/up` still 200; `ops:status` exits 1 | Database unreachable (connection pointed at nothing, not a real MySQL stop) |
| AC-44 | `OperationsTest`: log line fields; no token, query string, email or body; route pattern hides ids and unknown paths are `unmatched`; non-API calls are not logged by this line | The `http.request` line |
| AC-45 | `OperationsTest`: `ops:status` counts and exit 0; prints no secret | Operator summary |
| AC-49 | `NotifierTimeoutTest`: timeout is 5 s in config; a timeout is transient with a safe message; 3 attempts then FAILED_NEEDS_HUMAN with 3 HTTP calls; second try succeeds as SENT_AFTER_RETRY | Faked HTTP, no real wait |
| AC-39, AC-41 (support) | `OperationsTest`: seeder refuses in production and without a 12+ character password; defaults 30 and 50; creates accounts and items, safe to run twice, login works; volume rows are CLOSED and capped at 5000 | Load-test data |

## Part E: Chunk 2 status (written 2026-11-14; nothing run by Claude)
| Criterion | Built in chunk 2 | Still needs the developer's run | Result |
|-----------|------------------|---------------------------------|--------|
| AC-46 | `environment-comparison.md`; `.env.staging.example` notes; `deploy/staging-release.ps1` refuses anything but `APP_ENV=staging`, `APP_DEBUG=false`, `equipment_staging` | Capture showing the guard passing; optional demo of the dev database name without `APP_ENV` | Planned |
| AC-47 | `deploy/Caddyfile.staging`, `deploy/staging-release.ps1`, `deployment-checklist.md`, `postman/` staging environment | Release run output, 82 and 15 tests, Postman 149 on staging, new-request form at desktop and narrow width | Planned |
| P5 (support) | Compression left off in the Caddyfile on purpose; build and `main.dart.js` size printed by the script | Measurement in chunk 3 | Planned |
