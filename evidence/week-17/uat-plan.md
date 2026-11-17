# UAT Plan (Week 17, chunk 0)

Status: **draft for the developer's approval, 2026-11-15.** Frozen when committed (see `acceptance-criteria.md`). Nothing in this file has been run.

## 1. Purpose and limits
Check whether people in the three roles can complete the registered borrowing workflow in the Flutter Web app on local staging, and record what goes wrong. UAT here is separate from technical verification (`php artisan test`, `flutter test`, Postman, k6), which stays in the regression set.

What it does not show: acceptance by a real office, behavior with real users or data, performance beyond a few users, or production readiness. Three testers show that those three could do those tasks on this PC.

## 2. Scope
In: the registered scope of #5 (borrow request, approval, release, due-date tracking, return, inspection, closure, reminders and escalation, manual handling, custody and overdue list). Out: any new feature, real e-mail or SMS, real data, production infrastructure, multi-user load. A tester's wish for something outside the scope is recorded as a **change request** and is not built (a scope change needs the instructor's written approval).

## 3. Roles and decision authority
| Role | Who | Does | Decides |
|------|-----|------|---------|
| Tester T1 (borrower) | A real person, initials only in the repo | UAT-01, 06, 07, 09, 10 | Accept, accept with conditions or reject the borrower tasks |
| Tester T2 (approver) | A real person | UAT-02, 04, 07, 08 | The same, for the approver tasks |
| Tester T3 (staff) | A real person | UAT-03, 05, 07 | The same, for the staff tasks |
| Facilitator and operator | The developer | Reads the cards word for word, times tasks, records help, makes the two operator actions (UAT-08, UAT-09), resets staging. Does not coach | Triage of findings (with the sponsor if available) |
| Sponsor (optional) | An instructor or lab custodian | Acknowledges the result and the handover statement | Overall exit (AC-59) |

If only 2 testers are found they swap roles. If none: every record says "developer walkthrough, not stakeholder UAT" (DR-007).

## 4. Environment
Local staging on the developer's PC: `https://localhost:9443` (Caddy), API on `127.0.0.1:8001`, database `equipment_staging`, `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info`. Testers use the developer's browser on the developer's PC, at the keyboard (Caddy's local certificate is trusted only there). Each tester gets a login card with a fake account; the card is not committed. A reload signs the app out (the token is in memory only; the token lives 8 hours, so no expiry test).

## 5. Test data (planned; the guarded `UatSeeder` in chunk 1 makes it)
Fake data only. Each role has work waiting so no tester depends on another's speed:
| For | Waiting |
|-----|---------|
| T1 borrower | Several available items; no open request for the item used in UAT-01 |
| T2 approver | Two pending requests from another fake borrower (one to approve, one to reject); one released item whose due date has passed (the overdue item); one failed reminder IS pre-made by `UatSeeder` (decision 2026-11-15, see `lab-log.md`); the operator adds two more before UAT-08 (wrong-URL worker), so Manual handling lists 3 and 2 remain after one is handled |
| T3 staff | One approved request ready to release; one released item ready to return |
Exact names and tags are written in `test-data-plan.md` after chunk 1.

## 6. Entry criteria (all must be true, or the deviation is written down)
Week 16's conditions: OPcache on (`php -m`); staging reset with `deploy\staging-release.ps1`; a backup taken before UAT data is entered; the developer watches `/up`, readiness and `ops:status`; reminders and the queue worker are started by hand; fake data only. Plus: the reminder path is proven on staging (chunk 3), the regression baseline is green, predictions are committed, the cards and sheets are printed or open, consent is given.

## 7. Facilitator rules (validity)
1. Read each task card word for word. Do not explain screens, buttons or rules.
2. Answer only "What would you do?" or "Read the card again". Anything more is **help**: count it and write what was said.
3. Time with a stopwatch from the end of reading the card to the tester saying they are done (or the success screen).
4. Do not fix anything during a session. Note it, reset staging if needed (`staging-release.ps1 -SkipBuild`, about 13 s) and continue.
5. Testers may think aloud. No audio or face recording. A screen-only recording or screenshots are allowed with consent.
6. **Stop rule:** if a problem blocks every remaining scenario (the app or API cannot be used), stop, log the finding, and either reschedule or record the remaining scenarios as "not run". Do not skip silently.
7. Record failures and confusion exactly as they happened. Nothing is deleted afterwards.

## 8. Run header (top of every session record)
Date and time; git commit (`git rev-parse --short HEAD`); environment values (`APP_ENV`, `APP_DEBUG`, `LOG_LEVEL`, database name); seed used; `php -m` OPcache line; `ops:status` output; browser and window size; tester codes and roles; facilitator. No tester names.

## 9. Severity, type and disposition
| Severity | Meaning |
|----------|---------|
| Critical | Data loss or corruption, a secret or personal data exposed, or a role can do what it must not |
| High | A required task cannot be completed, or a tester cannot recover from an error without help |
| Medium | The task is completed but with confusion, a wrong or misleading message, or help needed |
| Low | Cosmetic, wording or a preference |

Type: defect, usability issue, change request (outside the registered scope), or not a defect. Disposition: fix now, defer (backlog with a reason), won't fix, or not a defect. IDs FIND-01 and up. Fix policy (D7): Critical and High always; Medium and Low only if small; at most 5 fixes; tests first.

## 10. Exit criteria
No Critical or High finding is open; every finding has a disposition; every fix is retested with its original scenario; the regression set passes (AC-59, AC-60); acknowledgements are recorded or "pending" is stated (AC-61).

## 11. Session outline (about 90 minutes planned; the predictions guess longer)
Welcome and consent (5 min) → UAT-01 → UAT-02 → UAT-03 (relay, in one room) → UAT-04 → operator action and UAT-08 → UAT-05 → UAT-06, UAT-07 → UAT-09 (operator stops the API) → UAT-10 → five-question rating and acknowledgements (10 min). Fallback if testers cannot meet together: each role on its own, using the seeded data.

**Operator notes for the session (added 2026-11-15 after the chunk 3 run):**
- Run the integrity check (`loadtest/integrity-check.sql`) BEFORE any reset; a reset first makes the check meaningless (chunk 3 deviation D8).
- UAT-02 needs the request made in UAT-01 (the seeder makes one pending request), so keep the card order.
- `reminders:run` skips the same kind of notification for 20 hours. Re-run `UatSeeder` before each proof or each tester run.
- Seeded data also holds one overdue notice for `approver@example.test` after `reminders:run`; it appears in Manual handling when the wrong-URL worker fails it. Do not read it as a tester error.
- Regression baseline is 120 backend tests (750 assertions), 15 Flutter tests, Postman 149/149 (decision D11 as updated on 2026-11-15).

## 12. Privacy
The repo and its screenshots hold only T1, T2, T3, the role and initials. No names, e-mails, faces or voices. Consent and the signed sheet stay on paper or a private folder. The login card and the dev seed password stay out of the repo and out of every frame.
