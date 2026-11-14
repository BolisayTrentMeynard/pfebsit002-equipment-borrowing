# Week 16 Performance Results (template, filled in after each counted run)

Status: **Planned.** Nothing here is a result yet. Claude fills this sheet from the developer's screenshots and the k6 summary files; it never invents a number. Every run starts from a fresh reset (`deploy\staging-release.ps1 -SkipBuild`, then `LoadTestSeeder`). Runs that fail for a setup reason are listed and not counted.

Environment (fill in once): one Windows PC, XAMPP MySQL (MariaDB), `php artisan serve` (single-threaded) on 8001 behind Caddy on 9443, k6 v2.4.0 on the same PC, `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info`. CPU model and number of logical cores: _developer: from the sampler's first line_.

Limits (they stay next to every number): assumed traffic, not real users; the load generator and the system share one PC; one IP address for all fake users; a single-threaded server; times include k6's own CPU use.

## Run log
| Run | Date and start time | Counted? | Reason if not | Files kept (outside the repo) |
|-----|---------------------|----------|---------------|-------------------------------|
| Smoke | | No (plumbing check) | | |
| Baseline | | | | |
| Ordinary | | | | |
| Peak | | | | |
| Spike | | | | |

## Baseline (1 user, 30 repeats)
| Endpoint | p50 ms | p95 ms | p99 ms | max ms | Predicted p95 | Result vs prediction |
|----------|--------|--------|--------|--------|---------------|----------------------|
| up (liveness) | | | | | 20 to 120 | |
| ready | | | | | 30 to 150 | |
| login (30 paced) | | | | | 250 to 800 | |
| equipment | | | | | 60 to 250 | |
| requests_list | | | | | 60 to 250 | |
| create | | | | | 100 to 400 | |
| request_detail | | | | | 60 to 250 | |
| custody | | | | | 60 to 250 | |
| manual | | | | | 60 to 250 | |
| requests_pending | | | | | 60 to 300 | |

## Ordinary, peak, spike
| Item | Ordinary (5 users) | Peak (25 users) | Spike (0 to 40) |
|------|--------------------|-----------------|-----------------|
| Measured requests (steady phase) and per second | | | |
| p95 reads / writes / all, ms | | | |
| Thresholds (D5) | reads under 800, writes under 1500, errors under 1% | all under 2000, errors under 2% | errors under 5% (prediction, not a D5 value) |
| Threshold result | | | |
| Errors (5xx, 429, other) | | | |
| Business rejections (409, 422) | | | |
| php / mysqld / caddy / k6 CPU (% of one core, typical and highest) | | | |
| Memory (MB, start and end) | | | |
| `laravel.log` growth | | | |
| Integrity checks 1, 2, 2b, 5 (rows) | | | |
| `created_201` vs rows added; `audit_logs_total` vs `writes_ok` | | | |
| Prediction matched? | | | |

## Spike probe (recovery)
Probe lines (`probe,t=..s,status=..,ms=..`) from `spike-console.txt`: _to be summarised by Claude._

## Bottleneck candidates (confirmed or disproved by these runs)
| ID | Candidate | Evidence | Verdict |
|----|-----------|----------|---------|
| P1 | Unbounded lists | | |
| P2 | `last_used_at` update on each call | | |
| P3 | Database cache and queue, one log file | | |
| P4 | Single-threaded server | | |
| P5 | Flutter release size, no compression | | |
