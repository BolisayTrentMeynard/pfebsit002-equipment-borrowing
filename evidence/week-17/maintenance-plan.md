# Maintenance Plan (Week 17, AC-65): DRAFT

Written 2026-11-15 in chunk 2c by Claude from the repository files and the Week 14 to 16 evidence. **Nothing in this plan has been run as a routine.** Frequencies are proposals for a small office of about 5 users; the developer confirms or changes them in chunk 6. The plan covers the local staging prototype only. It is not an operations contract and it does not make the system production ready (see the conditions in [`../week-16/readiness-recommendation.md`](../week-16/readiness-recommendation.md) and DR-008).

Owners are **roles**, never people. Today the Maintainer role is held by the developer, who is also the only operator.

## Roles
| Role | What it covers |
|------|----------------|
| Maintainer | Runs, monitors, fixes, releases and rolls back the system; keeps these documents true. Held by the developer |
| Approver (office) | Resolves Manual handling items with a note; approves or rejects requests |
| Staff (office) | Releases items and records returns and inspections |
| Sponsor | Course instructor or lab custodian, if available: acknowledges scope changes and the handover statement. "Pending" until one is named |

## Routine tasks
| # | Task | How often (proposed) | How to verify it was done | Owner | Source |
|---|------|----------------------|---------------------------|-------|--------|
| M1 | Start the reminder run (`php artisan reminders:run` in `backend\`) | Every working day at about 08:00 (nothing runs `schedule:run`, R32) | A line per run in `laravel.log`; the `reminder_notifications` table gains rows; `ops:status` shows Jobs waiting then falling | Maintainer | `backend/routes/console.php`, [`README`](handover-readme-draft.md) |
| M2 | Keep a queue worker running (`php artisan queue:work`, never `--stop-when-empty`) | While the office uses the system | `ops:status` Jobs waiting returns to 0 within 10 minutes (S7); failed jobs stay 0 (S5) | Maintainer | [`../week-16/monitoring-map.md`](../week-16/monitoring-map.md) |
| M3 | Run the custody summary (`php artisan custody:summary`) | Every working day at about 17:00, by hand | Output lists the items in custody; overdue ones are named | Maintainer | `backend/routes/console.php` |
| M4 | Read readiness and `ops:status` | Start of each working day and before any demo | `/api/health/ready` answers 200 `{"status":"ok"}`; `ops:status` exit code 0; Environment, Debug mode and Log level as expected (S10) | Maintainer | monitoring map S2, S5 to S10 |
| M5 | Resolve Manual handling items | Within one working day of an item appearing (S6) | The Manual handling tab is empty or every item has a note and status HANDLED_MANUALLY | Approver | [`../week-13/retry-and-recovery-policy.md`](../week-13/retry-and-recovery-policy.md) |
| M6 | Check the log size and clear or rotate it by hand | Weekly | `ops:status` Log file size below 50 MB (S8); a copy of the old file kept outside the repo if needed | Maintainer | monitoring map S8 |
| M7 | Check that OPcache is on (`php -m`) | Before each UAT session or demo, and after any PHP or XAMPP change | The OPcache line appears; without it the Week 16 targets fail | Maintainer | readiness recommendation, condition 1; R21 |
| M8 | Back up the database (`deploy\backup-restore.ps1`) and check the scratch restore | Before each release or UAT data entry, and monthly | The script prints size and SHA-256 and "14 of 14 tables matched" (or the current count); the dump stays outside the repo | Maintainer | [`../week-16/rollback-and-recovery-plan.md`](../week-16/rollback-and-recovery-plan.md) |
| M9 | Run the regression set | Before every release and after every fix | `php artisan test`, `flutter test`, the Postman collection on staging (149 tests) and `deploy\scan-secrets.ps1` all pass; the drift test passes | Maintainer | change-control notes, D11 |
| M10 | Review dependencies for updates and known vulnerabilities (`composer outdated`, `composer audit`, `flutter pub outdated`) | Monthly, and before any real deployment | A dated note of what was found and what was changed; any change goes through change control | Maintainer | not rehearsed in this project |
| M11 | Rotate `NOTIFIER_SIGNING_KEY` | Every 6 months (proposed), and at once after a suspected leak | The steps in [`../week-14/key-lifecycle-plan.md`](../week-14/key-lifecycle-plan.md); `notifier:tamper-demo` still shows the right answers; no `notifier.message_rejected` lines for valid messages afterwards | Maintainer | key lifecycle plan |
| M12 | Review the risk register and this backlog | Monthly, and after any incident | A dated line in the register for each risk reviewed | Maintainer | merged register (chunk 6a) |
| M13 | Reset staging for a session (`deploy\staging-release.ps1 -SkipBuild`), then reseed the UAT data | Before each UAT session or demo | Release script ends with `RESULT: staging is up`; seeder prints the counts it made | Maintainer | [`test-data-plan.md`](test-data-plan.md), `UatSeeder` |
| M14 | Check that the openapi file still matches the routes | After any route or response change | `OpenApiDriftTest` passes and Redocly lint is clean | Maintainer | `backend/openapi.yaml` |

## Support contacts (by role only)
No name, e-mail address or phone number is recorded in this repository.

| Need | Role | How reached |
|------|------|-------------|
| The system is down or wrong | Maintainer | Channel agreed with the sponsor before any real use; "to be agreed" today |
| A request is stuck or a notification failed | Approver | In the app (Manual handling tab) |
| An item was released or returned wrongly | Staff, then Maintainer | In the app, then the audit entries |
| A change to scope, data or users | Sponsor | Written approval (see [`change-control-notes.md`](change-control-notes.md)); "pending" until a sponsor is named |

Response time: the Week 16 evidence states there is no on-call. The Maintainer looks at the signals during lab sessions and before a demo. That stays true until a named owner and a response time are agreed.

## Incident quick reference
Use the decision table in the monitoring map, section 2, and the rollback plan. In short: database down means start MySQL and do not restart php or Caddy first; a bad release means roll the code back with `deploy\rollback-rehearsal.ps1` (it refuses when a migration or `composer.lock` differs); wrong data means restore into a scratch database first and compare.

## Backlog: not built (conditions before any real deployment)
Each line comes from the Week 16 readiness conditions, the risk register or DR-008. None is started. The owner is the role that would decide or do it.

| # | Item | Source | Owner | Note |
|---|------|--------|-------|------|
| B1 | A multi-worker web server (for example PHP-FPM behind nginx, Apache or Caddy) and a repeat of the peak and spike runs | R20, DR-008 option B | Maintainer | Add a concurrency test first: the code takes row locks, but nothing has sent two requests at the same moment |
| B2 | A scheduler run by the operating system and a supervised queue worker | R32, DR-008 | Maintainer | Removes M1 and M2 as by-hand steps |
| B3 | A database account with limited rights and a password from a secret store | R34 | Maintainer | Now: XAMPP `root`, no password |
| B4 | A publicly trusted certificate with renewal | R1 | Maintainer | Now: Caddy's local certificate authority |
| B5 | An external readiness probe with a timeout of at least 60 s and an alert to a named owner | R22, R23 | Maintainer, Sponsor | A readiness answer can take 8 to 53 s |
| B6 | Log rotation and a retention rule | R25 | Maintainer | One `laravel.log` today |
| B7 | Scheduled backups, a copy off the machine and a restore tested on realistic data | R28 | Maintainer | One restore on an almost empty database |
| B8 | A cap on list sizes (keeping the `data` key) and a test at volume | R30 (P1, P2) | Maintainer | The volume run was cut in Week 16 |
| B9 | The OPcache setting in version control or a build image | R21 | Maintainer | Today in an unversioned `php.ini` |
| B10 | Keep the readiness route in a rolled-back version | R27 | Maintainer | Rollback to `week-15` answers 404 on readiness |
| B11 | Review the manual-handling recipient fields and set an exact CORS origin | R14, R15 | Maintainer | Open since Week 15 |
| B12 | Bundle the Roboto font so staging does not depend on Google's CDN | R33 | Maintainer | Low priority |
| B13 | A fail-fast database check so an outage answers in under 8 to 9 s | R24 | Maintainer | Not built |
| B14 | Name an owner, a contact channel and a response time | R22 | Sponsor, Maintainer | Needed before any real use |
| B15 | In `deploy\staging-release.ps1`, give the readiness check its own timeout of 60 s with up to 3 tries 5 s apart, so a slow first answer is not reported as a failure | R41, FIND-02 | Maintainer | Deferred in Week 17 (approved 2026-11-17). Cause is a hypothesis (a 10 s curl timeout). Test by running the script twice and reading the readiness line |
| B16 | Make UAT runs cleaner: re-run `UatSeeder` before each run; name the item and the number of days on every task card (revised cards for UAT-05, UAT-06, UAT-10 are in `task-cards.md`); optionally hide the demo items EQ-001 to EQ-006 from testers by an operator step | R39, FIND-03, FIND-04, FIND-05 | Maintainer | Revised cards not yet run with testers; no code change proposed |

## What this plan does not cover
Real data, real users, hosting, data-protection duties, accessibility audit, load above about 5 users, and any change to the registered scope. Those need the sponsor's written decision and, for hosting and load, the backlog items above.
