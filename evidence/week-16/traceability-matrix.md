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
