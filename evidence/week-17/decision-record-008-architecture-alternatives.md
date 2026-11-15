# Decision Record 008: Architecture alternatives and recommendation (Week 17)

Date: 2026-11-15. Status: **SKELETON.** Options, criteria and weights are fixed here in chunk 0 and committed BEFORE any score is written. Scores, the sensitivity test and the recommendation are added in chunk 2 (a later commit). The commit order is the evidence.

## Context
The system is a Flutter Web client and a Laravel API with MySQL, a database-backed queue, a hand-run scheduler and a signed notifier, served from one PC. Week 16 measured its limits (single-threaded server: peak p95 2,047.5 ms against a 2,000 ms target; no automatic alert; scheduler and worker by hand). Week 17 asks for at least two feasible architectures, scored against agreed criteria, with the sensitivity of the recommendation tested (Activity 2).

## Decision question
Which architecture should the final integration recommend for the partner's bounded context (a small office, about 5 users, borrowing and reminders), and what would change that answer?

## Options
| Option | Description |
|--------|-------------|
| **A. As built** | Laravel monolith on `php artisan serve` (single-threaded) behind Caddy; MySQL; database queue; `reminders:run` and `queue:work` started by hand; Flutter Web static build |
| **B. Hardened monolith** | The same code on a multi-worker server (PHP-FPM with nginx, Apache or Caddy); scheduler run by the operating system; supervised queue worker; log rotation; a limited database account; same Flutter build |
| **C. Split notification service** | API as in B, plus a separate notification service fed through a message broker (for example Redis or RabbitMQ), with its own deployment and monitoring |

Rejected before scoring (one line each): a backend-as-a-service (the course requires our own backend and server-side rules); server-rendered pages instead of Flutter Web (the course requires a Flutter Web client).

## Criteria and weights (fixed now; total 100)
| Criterion | Weight | What a 5 means | What a 1 means |
|-----------|--------|----------------|----------------|
| Reliability | 20 | Survives a worker or process failure and recovers without a person | Fails or stalls under ordinary faults |
| Security | 15 | Least privilege, secrets in a store, certificates trusted by default | Shared admin account, local-only trust |
| Supportability | 15 | One person can run, monitor and fix it with the documents | Needs specialist skills or many moving parts |
| Complexity | 12 | Few components, simplest to understand (a high score means simple) | Many components and interfaces |
| Change impact | 10 | A new rule or screen touches few places | A change touches several services |
| Skills | 10 | Within what the developer and a maintainer already know | Needs new skills |
| Cost | 10 | Free or already owned | Paid services or extra machines |
| Data ownership | 8 | One database, one owner, simple backups and audit | Data split across services with sync problems |

Scoring scale: 1 to 5 per criterion (5 best, as defined above). Weighted score = sum of weight x score / 100.

## Sensitivity method (fixed now)
1. Baseline weights above.
2. Equal weights (12.5 each).
3. Reliability-heavy (reliability 35, the other weights scaled down in proportion).
4. Security-heavy (security 30, the others scaled down).
5. Cost-and-skills-heavy (cost 20 and skills 20, the others scaled down).
6. Each criterion's weight moved by plus and minus 10 points one at a time, the others scaled to keep the total at 100.
Report: the ranking under each case, whether the top option ever changes, and the weight change at which it flips.

## Honest limits (fixed now)
The scores are judgments, not measurements. B and C are not built or measured; the limits of A come from Week 16 results. A close result means the decision depends on the weights, and the record says so.

## Scores, sensitivity, recommendation, consequences, review condition
Added in chunk 2.
