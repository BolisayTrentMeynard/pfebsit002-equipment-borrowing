# Decision Record 008: Architecture alternatives and recommendation (Week 17)

Date: 2026-11-15. Status: **SCORED (chunk 2a), awaiting the developer's review.** Options, criteria, weights and the sensitivity method were fixed and committed in chunk 0 (`6c3849b`) before any score existed; they are unchanged below. The scores, the sensitivity test, the recommendation and the rest were added in chunk 2, after that commit, by Claude from the files in the repository. Nothing was built or measured for B or C.

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

## Scores (added in chunk 2)
Scale and weights are the ones above. Each reason points at evidence that exists; where the evidence is only a design (B and C), the score is a prediction and says so.

| Criterion (weight) | A | B | C | Why (A / B / C) |
|--------------------|---|---|---|-----------------|
| Reliability (20) | 2 | 4 | 4 | **A:** no errors at the ordinary and peak runs and the MySQL outage recovered 3.96 s after the start, but the single-threaded server missed the peak target (p95 2,047.5 ms against 2,000 ms), nothing restarts the worker, nothing runs the scheduler and nothing alerts a person (R20, R22, R32; `../week-16/readiness-recommendation.md`). **B:** several workers remove the one queue that caused the peak miss; an operating-system scheduler and a supervised worker replace the by-hand steps; one host and one database remain. A prediction, not measured. **C:** a failing notification service would not stall the API and a broker keeps messages, but the broker is one more thing that can fail. A prediction, not measured |
| Security (15) | 2 | 4 | 3 | **A:** signed messages, hashed tokens and the Week 15 fixes are in place, but the database account is XAMPP `root` with no password (R34), the certificate comes from a local CA (R1) and secrets live in `.env` files (R12). **B:** the same code with a limited database account, a public certificate and platform-held secrets, all listed as conditions in the Week 16 readiness recommendation; none built. **C:** as B, plus broker credentials and a service-to-service interface to protect, so a larger surface |
| Supportability (15) | 4 | 3 | 2 | **A:** one person can set up, run, monitor and roll back from existing documents (`../week-16/deployment-checklist.md`, `monitoring-map.md`, `rollback-and-recovery-plan.md`, `ops:status`), though detection means reading a terminal. **B:** more parts to run (web server, PHP-FPM, scheduler, supervisor, log rotation) and no documents for them yet. **C:** two deployments and a broker to monitor |
| Complexity (12), high = simple | 5 | 3 | 2 | **A:** Caddy, one PHP process, MySQL, a by-hand worker and scheduler command, and a static Flutter build. **B:** adds a multi-worker server, an OS scheduler and a supervisor. **C:** B plus a broker, a second service and a message contract |
| Change impact (10) | 4 | 4 | 2 | **A and B:** one repository; a new rule touches the model, service, controller, Flutter screen, `openapi.yaml` and tests, and the drift test keeps the API file in step. **C:** a notification rule can touch two services and the message format |
| Skills (10) | 5 | 3 | 1 | **A:** the developer built and ran every part. **B:** PHP-FPM, an OS scheduler and a supervisor have not been configured by the developer; learnable. **C:** broker and service-split skills are new |
| Cost (10) | 5 | 4 | 2 | **A:** free tools on a PC already owned. **B:** free software; a real deployment would need a host that is not owned. **C:** a host or managed broker for each extra part |
| Data ownership (8) | 5 | 5 | 3 | **A and B:** one MySQL database holds requests, audit rows and notification state; one dump restored with 14 of 14 tables matching in the Week 16 rehearsal. **C:** notification state would live in two places and need to be kept in step |

Weighted score (sum of weight x score, divided by 100): **A 3.70, B 3.71, C 2.53.** The arithmetic and every figure in the next section come from `dr008-sensitivity.py`; its output is saved in `dr008-sensitivity-output.txt` (run by Claude in its sandbox with Python 3; the developer has not rerun it).

## Sensitivity test (method as committed above)
| Case | A | B | C | Top option |
|------|---|---|---|------------|
| 1 Baseline | 3.70 | 3.71 | 2.53 | B, by 0.01: a tie |
| 2 Equal weights (12.5 each) | 4.00 | 3.75 | 2.38 | A |
| 3 Reliability-heavy (35) | 3.38 | 3.76 | 2.81 | B |
| 4 Security-heavy (30) | 3.40 | 3.76 | 2.61 | B |
| 5 Cost and skills heavy (20 and 20) | 4.03 | 3.66 | 2.27 | A |

Step 6 (each weight moved by plus and minus 10 points, the others scaled): 16 further cases. Data ownership cannot fall by 10 (its weight is 8), so it was set to 0, a change of minus 8. The top option is **A** when the weight on Reliability or Security is lowered, and when the weight on Supportability, Complexity, Skills or Cost is raised. It is **B** when the weight on Reliability or Security is raised, and when the weight on Supportability, Complexity, Skills or Cost is lowered. Change impact and Data ownership never separate A and B, because both score the same on them; the gap stays at 0.01 for B.

Results over all 21 cases (5 profiles and 16 single-weight moves): **A is on top in 8, B in 13, C in none.** The top option does flip between A and B. At baseline the weights are almost exactly at the flip point: A and B are level at a Reliability weight of 19.6 (baseline 20), Security 14.6 (15), Supportability 15.8 (15), Complexity 12.4 (12), Skills 10.4 (10) and Cost 10.9 (10). A change of about half a point in a weight, or of one point in any single score of A or B, is enough to separate them (all 27 such score changes were tried: 12 put A ahead, 15 leave B ahead, none leaves a tie). **C is last in all 21 cases**, at least 0.57 below the lower of A and B (the closest case is the Reliability-heavy profile); `dr008-sensitivity-output.txt` has every figure.

## Recommendation (conditional)
1. **The weighted score does not choose between A and B.** 3.70 against 3.71 is a tie, and the ranking flips with small changes. The record does not claim B is better on the strength of 0.01.
2. **What decides it is the purpose, which is what the weights stand for.** If cost, skills and simplicity count most (equal weights; a one-person course project), A comes first. If reliability and security count most (any use with real people depending on it), B comes first. C is not recommended at about 5 users in any case tried.
3. **Recommendation for the final integration:** keep **A** as the architecture for the Week 17 UAT and the course demonstration at about 5 users, with the six conditions of the Week 16 readiness recommendation; name **B** as the target architecture for any real deployment, and treat the Week 16 "before any real deployment" conditions as B's checklist. Of those eleven conditions, five describe B itself (a multi-worker server, a limited database account, a scheduler and supervised worker, log rotation, a publicly trusted certificate); six apply to A or B alike (an external probe with an alert, scheduled and off-machine backups, volume tests of the lists, OPcache kept in version control, a rollback that keeps the readiness route, review of R14 and R15).
4. **C** (a split notification service) is rejected for this context and kept only as a later option (see "What would change this").

## Disadvantages of this recommendation, and what it does not show
- **A is the option with measured weaknesses:** the peak target missed by 47.5 ms, no automatic alert, scheduler and worker by hand. Using A for UAT accepts those, and the Week 16 conditions say so.
- **B is a design, not a result.** Nobody has built it, run the peak and spike tests on it or documented it. Its reliability and security scores are predictions from how such servers are normally set up.
- **B may expose a problem that a single-threaded server hides.** The code takes row locks (`lockForUpdate` in `BorrowWorkflow` and `ManualHandlingController`) and checks replayed messages with an atomic `Cache::add`, but no test has sent two requests at the same moment. Moving to several workers needs a concurrency test first.
- **The scores are judgments.** They were written by Claude after the weights were committed, and the plan already expected "A for UAT and the course, B before any real deployment" (D9). That expectation may have shaped the scores. The tie at baseline and the 27 one-point changes above are the check: the result does not rest on one score. The developer is the decision maker and may change any score; each change is logged below with its date and the old value, and the script is rerun.
- **The context is small and fictional:** one office of about 5 users, fake data, one PC. Nothing here says what a real office would need.

## What would change this answer
| If | Then |
|----|------|
| Reminders must be sent without a person starting the worker (R32), even for a pilot | B (or at least an OS scheduler and a supervised worker) before the pilot |
| More than about 5 users, or the peak target (p95 under 2,000 ms) must be met | B, and repeat the peak and spike runs on it |
| Real personal data or an institution's own network | B plus the security conditions; A is not acceptable |
| Other systems must consume the notifications, or notifications need their own scaling or channels | Revisit C |
| The maintainer cannot run PHP-FPM, a scheduler and a supervisor (Skills for B drops to 1 or 2) | A stays only as a controlled demonstration; no real deployment is recommended until B can be run |
| The UAT session or a fix shows a reliability or security weakness in A | Re-score A, record the change, rerun the script |

## Consequences
- The handover statement (chunk 6) names B's conditions as future operational work and does not call A production ready.
- The maintenance plan (`maintenance-plan.md`) lists the B conditions in its backlog, each with a role as owner.
- The final architecture diagram (`diagrams/final-architecture.png`) shows A as built; B's differences are the text above.
- Nothing in the code changes for this record.

## Review condition
Review this record after the UAT session and its fixes (chunk 5), and before the final assembly (chunk 6). Re-read the scores for Reliability and Security against anything UAT-08, UAT-09 or a finding showed. A change to a score is added here as a dated line (date, criterion, option, old, new, reason); the table above is not overwritten without that line.

## Score change log
None yet.
