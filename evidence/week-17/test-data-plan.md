# Test Data Plan (Week 17)

Fake data only: made-up names, `@example.test` e-mails, sample equipment. No real person, institution or production record.

## Accounts
Two sets of fake accounts exist on staging after the steps below. Testers use only the UAT set.

| Set | Made by | Password | Used for |
|-----|---------|----------|----------|
| `borrower1`, `borrower2`, `approver`, `staff` (`@example.test`) | `DatabaseSeeder` (run by `staging-release.ps1`) | the dev seed password | Postman, regression, the escalation recipient of `approver@example.test` |
| `uatborrower1`, `uatborrower2`, `uatapprover`, `uatstaff` (`@example.test`) | `UatSeeder` (chunk 1) | one password given in the shell as `UAT_PASSWORD` (12+ characters, fake, never written to a file) | the three testers and the facilitator |

Each tester gets a login card with a UAT account. The card is printed or on screen only, and is not committed or photographed. Testers are T1, T2 and T3 in every record.

## Data waiting for each role (made by the guarded `UatSeeder`; read from the seeder code in chunk 3, then confirmed by the dry run)
| Tag | Item | State after seeding | Who has work |
|-----|------|---------------------|--------------|
| UAT-001 | Projector Alpha | available, no request | borrower (UAT-01) |
| UAT-002 | Laptop Cart Beta | available, one closed request in history | borrower (UAT-10) |
| UAT-003 | Camera Kit Gamma | PENDING request of `uatborrower2` | approver |
| UAT-004 | Speaker Delta | APPROVED request of `uatborrower1` (item still shows available until released) | staff (release) |
| UAT-005 | Extension Cord Epsilon | RELEASED to `uatborrower1`, due in 20 hours | staff (return) |
| UAT-006 | Projector Zeta | RELEASED to `uatborrower2`, OVERDUE by 2 days; a sent reminder and one FAILED_NEEDS_HUMAN overdue notification for `uatapprover` | approver (Overdue tab, Manual handling) |

Seeder run three times gave the counts `[4,6,11]` (accounts, UAT items, requests), per the developer's chunk 1 run.

## Proposed card-to-item mapping (a proposal from the seeder code; the dry run in 3c confirms or corrects it)
| Card | Tester | Item or step |
|------|--------|--------------|
| UAT-01 | T1 | request UAT-001 Projector Alpha, 3 days (becomes the second pending request for the approver) |
| UAT-02 | T2 | approve the new request for UAT-001 (item A); reject UAT-003 Camera Kit Gamma (item B) with a note |
| UAT-03 | T3 | release UAT-004 Speaker Delta |
| UAT-04 | T2 | the Overdue tab shows UAT-006 Projector Zeta and its borrower (`uatborrower2`) |
| UAT-05 | T3 | return UAT-005 Extension Cord Epsilon with result good (`uatborrower1` then sees it closed) |
| UAT-06 | T1 | short reason; 0 or 8 days; UAT-001 again while the approved request on it is still open (needs UAT-02 to have approved it, not rejected it) |
| UAT-07 | any | wrong password, right password, sign out |
| UAT-08 | T2 | Manual handling lists 3 (1 seeded, 2 from the operator run); one marked handled, 2 remain (see "Settled mismatch" below) |
| UAT-09 | T1 | the borrower's request list while the operator stops the API |
| UAT-10 | T1 | request UAT-002 Laptop Cart Beta at about 360 px |

Dependencies this creates: UAT-02 needs the request made in UAT-01 (the seeder makes only one pending request, not two as the plan said, so the relay order matters); UAT-06's third case needs the UAT-001 request to be approved and not yet released. Whether this holds is confirmed or corrected in `pre-uat-readiness.md` (3c).

## Settled mismatch (found in chunk 3, decided by the developer on 2026-11-15)
`uat-plan.md` section 5 said the failed reminder is not pre-made and the UAT-08 card expected it "listed once" and "empty afterwards". `UatSeeder` pre-makes one FAILED_NEEDS_HUMAN notification for `uatapprover`, and Manual handling lists every failed notification whatever the recipient. Run result (2026-11-15): 3 items listed after the wrong-URL worker, 2 remaining after one is handled (developer report for the browser list; `ops:status` backlog 3). **Decision: change the card wording and the plan, not the seeder** (no code change, no migration, rollback to `week-16` stays safe). The card and `uat-plan.md` section 5 now say 3 listed and 2 remaining. Details: `pre-uat-readiness.md` (D1, D10) and `lab-log.md`.

## Resets and backups
Reset: `deploy\staging-release.ps1 -SkipBuild` (about 13 s) before the session and whenever a session needs a clean start; it re-seeds only `DatabaseSeeder`, so run `UatSeeder` again afterwards (running `UatSeeder` alone also resets the UAT data between tester runs). Backup: `deploy\backup-restore.ps1` before UAT data is entered; the dump stays outside the repo (`C:\dev\pfebsit002-backups\`) because it holds fake password hashes.

## Capture hygiene
Screen-only recordings or screenshots, no audio, no faces. Keep out of every frame: the dev seed password, `.env`, `.env.staging`, the Authorization header, tester names. Drop captures unsorted into `Downloads\FLUTTER\Week 17`.

## Integrity checks after the session
The Week 16 SQL checks (no double open request per borrower and item, no item released twice, audit rows add up), run by the developer on `equipment_staging`.
