# Change-Control Notes (Week 17, AC-65): DRAFT

Written 2026-11-15 in chunk 2c by Claude from the Week 17 plan (decisions D6, D7, D11, D13), the repository scripts and the earlier evidence. These are working rules for one person plus a sponsor. They were not tried on a real change yet; the Week 17 fixes (chunk 5) are the first use, and this file is then updated with what happened.

## What counts as a change
| Kind | Examples | Route |
|------|----------|-------|
| **Fix** | A defect or a usability problem in something already in the registered scope | Finding, decision, test first, fix, retest, regression |
| **Change request** | A new feature, screen, route, role or channel (for example real e-mail or SMS) | Written, not built; needs the sponsor's written approval before any work |
| **Configuration change** | An `.env` value, a port, a PHP setting, a certificate, a key | Recorded; keys and passwords never in the repo |
| **Documentation change** | Any `evidence/` file, README, `openapi.yaml` text | Same branch rules; links and claims re-checked |
| **Emergency change** | Something broken during a demo or session | Smallest safe action first (restart, reset, roll back); the change is written up afterwards |

## Rules for a fix
1. **A finding first.** Every fix traces to a finding (FIND-nn) or a risk (R-nn) with severity, type, evidence and the decision maker.
2. **Priority.** Critical and High are always fixed. Medium and Low only if small. At most 5 fixes in Week 17 (D7). Everything else goes to the backlog in [`maintenance-plan.md`](maintenance-plan.md).
3. **Tests first.** Write the failing test, show it failing, then the fix (the Week 15 and 16 pattern). A fix with no test says why.
4. **Keep rollback safe.** Avoid a migration or a `composer.lock` change. `deploy\rollback-rehearsal.ps1` refuses to roll back when either differs from the tag.
5. **Keep the contract true.** If a route, a role rule or a response changes, update `backend/openapi.yaml` in the same commit. `OpenApiDriftTest` fails when they disagree.
6. **Regression before merge.** `php artisan test`, `flutter test`, the Postman collection on staging and `deploy\scan-secrets.ps1`. k6 is rerun only if the fix touches the request path (middleware, queries, caching); otherwise the record says it was not rerun.
7. **Retest the original scenario.** The UAT scenario that exposed the problem is run again and the result is written next to the first result. The first result is not overwritten.

## Rules for a change request
- It is written down with who asked, why, and what it would touch. It is **not built** under Week 17.
- The registered scope changes only with written approval from the sponsor (or, if none is named, the course instructor). Until then the answer is "not in scope".
- Approved requests become a new finding or backlog item with an owner role and go through the fix rules above.

## Commit and release habit
- One branch per week or purpose (`week-17-uat`); small commits with a clear message that names the finding or criterion.
- Predictions and thresholds are committed **alone, before** the run they predict. A threshold changed after freezing is logged with its date, reason and old value in the lab log.
- A tag marks a finished week (`week-17` only after the developer says it is done). An existing tag is not moved without a written reason.
- The release is `deploy\staging-release.ps1`; the previous state is a database backup (M8) and the previous tag.
- A commit is logged as "committed" only after the person who ran the commands says so.

## Who decides what
| Decision | Decider |
|----------|---------|
| Severity and disposition of a finding | Maintainer; the sponsor for the exit rule (AC-59) if one is named, else the Maintainer, stated as such |
| Accept, accept with conditions or reject for a role's tasks | The tester of that role |
| A change of scope, data or users | Sponsor, in writing |
| A change to a score in DR-008 | Maintainer, logged with date and old value |
| A change to a frozen threshold | Maintainer, logged in `lab-log.md` with date and old value |

## Change log
| Date | Change | Kind | Decided by | Tests | Result |
|------|--------|------|------------|-------|--------|
| (none yet) | | | | | |
