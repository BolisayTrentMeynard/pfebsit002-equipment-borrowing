# Decision Record 006: Performance, deployment and rollback approach (Week 16)

Date: 2026-11-14. Status: approved by the developer on 2026-11-11 (D1 to D13) and re-confirmed on 2026-11-14 with changes C1 to C4, before any code change.

## Context
The system works functionally (Weeks 13 to 15). Week 16 must show how it behaves under stated load, what happens when MySQL fails, how a release is repeated and reversed, and whether it can honestly be called ready. The developer works alone on one Windows PC with XAMPP, Caddy and k6 installed. No cloud account or server is available, and real institutional data is not allowed.

## Decision questions
1. Which tool generates load and measures latency?
2. Where does "staging" run, and how is it different from development?
3. How do we run a web server that can use several workers?
4. How do we log in many fake users without breaking the login limit?
5. How do we make a rollback that is safe and testable?

## Options and choices
| Question | Chosen | Rejected and why |
|----------|--------|------------------|
| 1 Load tool | **k6** (v2.4.0): scripts are code, scenarios, thresholds, percentiles built in, runs on Windows | Apache Bench from XAMPP (kept only as a fallback: no scenarios, no think time, no per-endpoint tags); Postman performance runs (weak percentiles and no real concurrency in the desktop app, tokens sync to a cloud workspace, R17); JMeter (heavy setup for a short project) |
| 2 Staging | **Local staging**: own database `equipment_staging`, `APP_ENV=staging`, `APP_DEBUG=false`, own `.env`, Flutter release build served by Caddy on the same origin as the API | Testing on the dev database (shared data, debug on, not a separate environment); a cloud host (no account, cost, real-data and credential risk, out of scope) |
| 3 Server | Keep **`php artisan serve`** (single-threaded) and state it as a limit; Caddy still gives HTTPS and static files | Apache from XAMPP with several workers (closer to production, but a new configuration to document and secure, and it would hide the bottleneck the week is meant to measure); PHP-FPM on Windows (not available in the XAMPP setup used) |
| 4 Login for load | **k6 `setup()`** logs in about 32 accounts once, paced under 10 per minute; tokens only in memory; login limit stays ON (C1) | Raising or removing the limit for tests (would test a different system and weaken the control added in Week 15); seeding token rows directly (skips the real login path) |
| 5 Rollback | **Back to the `week-15` tag** plus a `mysqldump` restore into a scratch database; no destructive migration this week; any added index is an additive migration that can be rolled back first (C4) | Rolling forward only (no proof of recovery); dropping tables to rehearse (risk to data, not needed) |

## Other decisions carried from the plan
- Liveness is Laravel's built-in `/up`. Only `GET /api/health/ready` is added (C2).
- Small additions only: readiness, one timing log line, `ops:status`, a guarded `LoadTestSeeder` (D7).
- Workloads and thresholds are fixed in writing before any run (D4, D5).
- Failure test: MySQL stopped during the ordinary workload, prediction committed first (D8).
- Fix 2 to 3 bottlenecks only if the measurements confirm them, tests first (D9). If the list fix is needed it keeps the `data` key and adds a default cap (C4).
- Week 15 gaps closed cheaply: the notifier timeout test and new-request form screenshots on the staged build (D12).
- Cut order if time runs short (C3): the 20 min endurance run, the 5,000-request volume run and the worker-stopped test are dropped first, and the evidence says so openly.

## Bottleneck candidates (ranked, unconfirmed)
| ID | Candidate | Why it might matter | Plan |
|----|-----------|---------------------|------|
| P1 | Lists return every row (`/requests`, `/custody`, `/equipment`) | Response time and size grow with data | Measure at volume; if confirmed, default cap |
| P4 | Single-threaded server | Throughput ceiling at peak | State as a limit; not fixed |
| P2 | `last_used_at` UPDATE on every authenticated call | One extra write per request | Measure; change only if the gain is real |
| P3 | Database cache, queue, sessions; debug log; one file | Contention and log growth | Observe; staging example sets sensible values |
| P5 | Flutter release size, no compression at Caddy | First-load time | Measure file size; enable compression in Caddy if it matters |

## Consequences and limits
- All numbers describe one PC in which the load generator and the system compete for CPU. They are not predictions for a real server.
- Rejecting Apache means the evidence cannot show multi-worker behavior. The readiness recommendation says so.
- A passed threshold here is not a promise. The recommendation is conditional and never says "production ready".

## Review
Revisit after the baseline (chunk 3) and after the fixes (chunk 5). Updates are added to this file with a date, as DR-005 did.
