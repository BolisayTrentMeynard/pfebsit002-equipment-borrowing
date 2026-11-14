# Readiness Recommendation (Week 16, AC-52)

Written by Claude with the developer on 2026-11-15 from the measurements in this folder. Claude ran nothing. This is an opinion about a local prototype, not an approval and not a guarantee.

## Recommendation

**Conditional: the prototype may continue to Week 17 (controlled user acceptance testing) on local staging with a small group of fake or consenting test users at roughly the ordinary workload (about 5 users with think time), under the conditions below. It must NOT be described as production ready, and it must not be used for real institutional data or for more than about the ordinary workload.**

## Why this much, and no more
- **Works at the ordinary workload on this PC.** After OPcache was enabled, 5 users for 10 minutes gave 340 requests, 0 errors, reads p95 388.6 ms and writes p95 267.6 ms; all thresholds passed and the data stayed consistent.
- **Does not meet the peak target.** 25 users gave 0 errors but all p95 2,047.5 ms against a 2,000 ms target (missed by 47.5 ms after the change; 20.8 s before it). The cause is a single-threaded server that answers one request at a time, so every request waits in one queue. Before the change, 40 users in a spike pushed a probe to 34 s (no errors, no timeouts, recovered in about 34 s).
- **A MySQL outage is detected and survived, but slowly and by hand.** Readiness turned 503, the API answered a generic 500 with no internals, no partial write was found, and the system recovered 3.96 s after MySQL was started, with no restart of php or Caddy. Detection took 13 s with no load and 99 s under load, and nothing alerts a person.
- **A rollback to `week-15` works for users** (six checks passed) but reads as "down" to a readiness monitor, and the Week 15 collection was not run on it.

## Conditions that must hold for the Week 17 use
1. OPcache stays enabled in `C:\xampp\php\php.ini` (backup `php.ini.bak-before-opcache`); check with `php -m` before a session. Without it the same workload failed its targets.
2. Staging is reset with `deploy\staging-release.ps1` before each session; `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info` (the script refuses anything else).
3. The developer watches `/up`, readiness and `ops:status` during a session; the owner is the developer and there is no on-call.
4. A backup is taken (`deploy\backup-restore.ps1`) before UAT data is entered; the dump stays outside the repo.
5. `reminders:run` is still run by hand (nothing runs `schedule:run`) and `queue:work` is started by hand; UAT scenarios must say so.
6. Only fake or consented test data; no secrets in screenshots; the Week 14 and 15 scans are rerun after Week 17 changes.

## Conditions that would have to be met before any real deployment (none are met now)
| Condition | Status |
|-----------|--------|
| A server that runs several workers (for example PHP-FPM behind nginx or Apache) and a repeat of the peak and spike runs on it | Not built; the peak target is missed on the single-threaded server |
| A database account with limited rights and a password from a secret store (now: XAMPP root, no password) | Not built |
| A publicly trusted certificate with renewal (now: Caddy's local CA) | Not built (R1) |
| An external readiness probe with a long timeout and an alert to a named owner | Not built |
| The scheduler run by the operating system and a supervised queue worker | Not built |
| Log rotation and a retention rule (now: one `laravel.log`, one outage wrote 606.6 KB) | Not built |
| Backups on a schedule, a copy off the machine, and a restore tested on realistic data | Not built; one restore shown on an almost empty database |
| Lists with a limit and a test at volume (P1) and a check of `last_used_at` (P2) | Not tested; the volume run was cut |
| An OPcache setting kept in version control or a build image | Not built; it lives in an unversioned `php.ini` |
| A rollback that keeps the readiness route and is run with the Week 15 collection | Not done |
| Review of the manual-handling recipient fields (R14) and the CORS allow-list (R15) | Not done |

## Limits of the measurements
One Windows PC; k6, the sampler, MySQL, php and Caddy share its CPU; one IP address for all fake users; assumed traffic, not real users; closed-loop virtual users (a faster server receives more requests); runs of 8 to 11 minutes; the peak was run once after the change and the spike was not repeated; the sampler CSVs of the "after" runs were not read; many latency predictions were wrong, and they are kept in the record. The numbers describe this setup only.

## Tabletop review of an incomplete release package (Activity 2, paper exercise)
Imagine a release package that contains only: the repository at a tag, the Flutter release build, `backend\.env.staging.example`, `deploy\staging-release.ps1` and `deploy\Caddyfile.staging`. Someone else must run it on Monday. Nothing below was run; it is a walk-through from the files.

| What is missing from the package | What would happen | Who would notice and how | What the package should add |
|----------------------------------|-------------------|--------------------------|-----------------------------|
| The OPcache setting (outside the repo) | The same release runs 4 to 5 times slower per request; ordinary load misses its targets | Users complain of 2 s pages; nobody has a baseline | A PHP configuration note and a `php -m` check in the checklist |
| The real `.env.staging` and two new keys | The release script stops at its checks, or the notifier runs unsigned or not at all | The script prints a clear STOP | Keep the instruction that says to generate keys; never ship the file |
| A fresh backup before the release | A bad release cannot be compared with the data before it | Nobody, until data is wrong | Run the backup script first and note the file |
| MySQL running | Readiness 503, API 500s; `/up` still 200 | Someone reading readiness or `ops:status`; a probe on `/up` alone sees nothing | Check MySQL before the release; probe readiness, not `/up` |
| A scheduler and a queue worker | No reminders are sent and nothing is retried; the app still looks fine | Nobody (no alert on failed jobs or on jobs waiting) | Name them as conditions; add the OS scheduler and a supervised worker |
| An owner and a contact | An outage waits until someone happens to look | Nobody | A named owner and a response time in the package |
| A rollback instruction | The operator improvises; a migration rollback is never rehearsed | The operator, too late | `rollback-and-recovery-plan.md`, with the readiness 404 caveat |
| Log rotation | The log grows (one outage wrote 606.6 KB) | `ops:status` shows the size if someone runs it | Rotation and a size alert |
| A statement of the load it was tested for | Someone invites 25 users | Users, as slow pages (20 s before the change, about 2 s after it) | State "about 5 users" in the package |

Result of the review: the release script and the checks cover the repeatable part. The missing parts are mostly about people, configuration outside the repo and monitoring, and they are listed as conditions above rather than fixed here.
