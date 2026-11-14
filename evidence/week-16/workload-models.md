# Workload Models (Week 16, Activity 1)

Written 2026-11-14 before any measurement. Activity 1 asks for exactly two models: **ordinary** and **peak**. The spike, the volume run and the short endurance run are supporting stress runs (listed at the end) and are not workload models.

## Where the numbers come from
There is no real usage data. The figures are assumptions for a small school equipment office with about 30 regular borrowers, one approver and one staff member. They were chosen so that the ordinary model is light and the peak model is close to what a single-threaded server can take. They are not measured from any institution, and the evidence must not present them as real traffic. If the assumptions turn out wrong, the model can be changed in writing before the next run, and the change is recorded.

## Common rules
- Fake accounts only. `setup()` in k6 logs in about 32 accounts once (30 borrowers, 1 approver, 1 staff), one about every 7 seconds, so the per-IP login limit (10 per minute) stays ON. That takes about 4 minutes (estimate, not measured). Tokens are kept in memory only and are never written to disk, logged or printed. The password comes from an environment variable set in the PowerShell session; it is not typed in a command line or saved in a file.
- A virtual user picks a journey at the start of each iteration, using the weights below, then waits its think time.
- The first minute of each model is a ramp-up and warm-up. It is tagged `phase=warmup` and left out of the thresholds. The next 5 minutes are `phase=steady` and are the measured part.
- Expected statuses are written per request. 200 and 201 are success. 422 on a duplicate open request and 409 on an action that is no longer possible are **business rejections**: counted apart, not errors. 5xx, timeouts and anything else unexpected are **errors**. 429 on an API call is recorded and explained.
- The monitor scenario (1 request every 10 s to `/up` and `/api/health/ready`) runs beside every run and is not part of the user count.
- Run order: single-user baseline, ordinary, peak, spike, then the optional runs. The data is reset to the same seed before each counted run.

## Journeys
| ID | Role | Requests | Write? |
|----|------|----------|--------|
| B1 Browse | Borrower | `GET /equipment`, `GET /requests` | No |
| B2 Detail | Borrower | `GET /requests`, `GET /requests/{id}` | No |
| B3 New request | Borrower | `GET /equipment`, `POST /requests` (an item this borrower has no open request for) | Yes |
| A1 Decide | Approver | `GET /requests?status=PENDING`, then `POST approve` or `reject` for one; if none is pending the journey ends after the read | Yes, when something is pending |
| A2 Manual handling | Approver | `GET /manual-handling` | No |
| S1 Custody | Staff | `GET /custody`, then `POST release` for one approved request, or `POST return` for one released item; if none, the journey ends after the read | Yes, when something is available |

## Model 1: Ordinary
| Item | Value |
|------|-------|
| Meaning | A normal working hour: a handful of people use the app, most of them look, a few act |
| Virtual users | 5 |
| Think time | 5 to 15 s (uniform) |
| Duration | 1 min ramp-up and warm-up, then 5 min steady |
| Journey weights | B1 35%, B2 15%, B3 10%, A1 20%, A2 5%, S1 15% |
| Expected offered load | about 0.5 to 1 request per second |
| Data | Seed from `LoadTestSeeder`: 30 borrowers, 50 items, an empty request table |
| What it should show | The system is comfortable at the load it was built for, and what normal latency looks like |
| Thresholds (D5) | Reads p95 under 800 ms; writes p95 under 1500 ms; errors under 1%; no corrupted data |

## Model 2: Peak
| Item | Value |
|------|-------|
| Meaning | A busy moment, for example the start of a lab week when many people borrow and return at once |
| Virtual users | 25 |
| Think time | 2 to 5 s (uniform) |
| Duration | 1 min ramp-up and warm-up, then 5 min steady |
| Journey weights | Same as ordinary |
| Expected offered load | about 7 to 10 requests per second |
| Data | Same seed |
| What it should show | Where the system starts to queue, and which resource runs out first |
| Thresholds (D5) | p95 under 2000 ms; errors under 2%; no corrupted data |

## Supporting stress runs (not workload models)
| Run | Definition | Status in the cut order (C3) |
|-----|------------|------------------------------|
| Single-user baseline | 1 user, one request at a time, 30 repeats of each endpoint, login timed separately. No think time | Core |
| Spike | 0 to 40 users in 30 s, hold 1 min, down in 30 s, then 2 min of observation. Peak journey weights. Borrowers 31 to 40 reuse tokens of borrowers 1 to 10, so duplicate-request answers (422) are expected and counted apart | Core |
| Volume | 5,000 request rows in the database, then the ordinary workload again. Shows how list size affects the lists (P1) | Optional, dropped first |
| Short endurance | 20 min at the ordinary level. Labelled **not** a real endurance test: it cannot show slow leaks or long-term growth | Optional, dropped first |

## What is measured
Per endpoint and per phase: request count, p50, p95 and p99 response time, throughput (requests per second), error count and rate, business rejection count. During each run a PowerShell script samples CPU and memory of `php`, `mysqld` and `caddy` every 5 seconds into a CSV (no secrets in it), and the size of `laravel.log` before and after.

## Data integrity check after every counted run (read-only SQL, run by the developer)
1. No borrower has two open requests (PENDING, APPROVED or RELEASED) for the same item.
2. No item has two RELEASED requests at once, and every item with a RELEASED request has status not available.
3. The number of 201 answers to `POST /requests` in k6 equals the rows added to `borrow_requests`.
4. Every status change has an audit row (`audit_logs` count at least the number of successful writes).
5. No request has a status that the workflow cannot reach (for example RETURNED without a release).
The query text is written in chunk 3 and shown in the evidence.

## Limits
One PC for load generator and system; a single-threaded server; assumed traffic; fake accounts share one IP address, so per-IP limits behave differently from real users; response times in k6 include k6's own CPU use. The evidence says so next to every result.
