# Interpretation and Maintainability Note (Week 17)

Project: #5 Equipment Borrowing Reminder System. Date: 2026-11-17. Written by Claude from the repository records, the developer's reports, pasted output and screenshots; Claude ran no application code. **Docs only. Built, untested.** Everything is for one Windows PC, local staging, fake data and three testers.

## What the results show
- **The registered workflow was completed by three people in one session.** 12 of 12 attempts were completed without help on the developer's reading of the recording (threshold 10), and all three testers accepted for their own tasks. This is a small, friendly sample: the viewer was also the facilitator, nothing was timed, and no sheets were kept (R35).
- **The regression set still passes after Week 17.** `php artisan test` 120 and `flutter test` 15 (developer's word), Postman 149 of 149 in 1 m 34 s (Runner screenshot seen). No application code changed in Week 17 after the seeder, the OpenAPI file and the drift test.
- **The secret scan is clean for the log written since Week 16 chunk 5, apart from two known words.** From byte 3,654,570 the only match is the word `password` twice (the seeder's guard text naming a variable). From byte 5,163,800 nothing matches and the exit code is 0.
- **The reminder failure path works as designed on staging.** A permanently failed reminder reached Manual handling and one item was marked handled by an approver (backlog 3, then 2).
- **The documents are conditional on purpose.** The handover statement allows continued evaluation and small controlled trials, and says plainly that the system is not ready for production use.

## What the results do not show
- **They do not show that the system is easy to use for other people.** No stopwatch times exist (AC-56 is "not measured"), the "loan too long" refusal was never seen by a tester (AC-57 partly), and the all-5 ratings with the free text "good" carry little information.
- **They do not show that the whole log is free of token hashes.** The whole-file scan finds 52 token-hash and 52 hex matches before byte 3,654,570. They fit the 26 old outage lines recorded in Week 16 (R26), but the lines were not read to confirm it.
- **They do not show anything about load, a crash, a real server or real data.** k6 was not rerun; the peak p95 target was missed in Week 16 by 47.5 ms.
- **They do not show that someone else can set the system up.** No fresh clone and no fresh reviewer yet (R42).
- **They do not show that an office accepts it.** No sponsor acknowledged anything (R37); every decision is the developer's.

## Maintenance notes
- **Run the scan with `-StartBytes`.** For a routine check use `deploy\scan-secrets.ps1 -StartBytes 5163800` (or the log size at the start of your work). A run on the whole file reports the old Week 16 entries and exits 1 even when nothing is new. The expected whole-file figures today are `password` 2, 64-hex strings 52 and `token_hash` 52.
- **Reset before Postman.** The collection starts with "00 Setup (run first, fresh data)"; run `deploy\staging-release.ps1 -SkipBuild`, check the readiness body, then run the Runner. If the first request says "Request could not be sent", the staging stack is not running.
- **Check the readiness body, not only the FAIL line** of `staging-release.ps1` (FIND-02, B15).
- **Keep the log fix in mind.** The error-path fix is proven by PHPUnit, not by a live outage (R26).
- **Open work before any real use** is listed in [`handover-statement.md`](handover-statement.md) and [`maintenance-plan.md`](maintenance-plan.md) (B1 to B16).
