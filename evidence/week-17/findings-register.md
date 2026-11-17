# Findings Register (Week 17, AC-58 and AC-59)

Written 2026-11-17 in chunk 5 by Claude from `uat-session-record.md`, the developer's report and screenshots, `uat-plan.md` section 9 (severity scale) and the code. **Docs only. Built, untested.** Claude did not see the screen recording, the observation notes or the paper sheet. Every disposition below was **proposed by Claude and approved by the developer in chat on 2026-11-17** (decisions table at the end, written by Claude from the developer's statement; the developer did not edit the file). No finding is deleted. Testers are T1 to T3 only.

Decision maker for every row: the Maintainer (the developer). **Sponsor: none yet, so the exit decision (AC-59) is the developer's, not the office's.**

## Register (AC-58 columns)
| ID | Scenario | Severity | Type | Evidence | Proposed disposition | Decision maker |
|----|----------|----------|------|----------|----------------------|----------------|
| FIND-01 | UAT-05, UAT-06, UAT-10 | Low | Not a defect | `uat-session-record.md` (integrity, request rows); `borrow_requests` query screenshot | **Not a defect** (cause found; two follow-ups registered as FIND-04 and FIND-05) | Developer |
| FIND-02 | Setup (E3) | Low | Defect (tooling, false alarm) | 2026-11-16 terminal output of `staging-release.ps1` (not in the repo) | **Defer** to backlog B15, with the fix written below if the developer prefers to fix now | Developer |
| FIND-03 | Setup, UAT-08 | Low | Known limit of the test data | `lab-log.md`; `uat-plan.md` section 11 | **Won't fix** (documented limit with an operator note) | Developer |
| FIND-04 | UAT-05, UAT-06, UAT-10 | Low | Usability issue (of the test material) | Request rows 7 and 8 and the returns of UAT-004 and UAT-006 | **Fix now, docs only**: revised wording appended to `task-cards.md` | Developer |
| FIND-05 | UAT-05, UAT-06, UAT-10 | Low | Change request (test data) | Request rows 7 and 8 (EQ-006 and EQ-005 are `DatabaseSeeder` items) | **Defer** with a reason; the wording fix in FIND-04 covers the session need | Developer |
| FIND-06 | All cards; AC-55, AC-56, AC-57 | Medium (proposed; the scale has no row for a gap in the records) | Process gap (evidence) | `uat-session-record.md` ("not recorded" cells); developer's statement that no per-card notes were kept | **Fix by reading the recording** (developer action, chunk 6) **or state the result as "not confirmed"** | Developer |

## Detail

### FIND-01: testers chose other items than the plan expected
- **What happened.** After the run the integrity counts were one request, one return and one close higher than the planned relay predicts. The read-only `borrow_requests` query explains all of it: T3 returned UAT-004 Speaker Delta (good) and UAT-006 Projector Zeta (missing parts) instead of UAT-005 Extension Cord Epsilon (still RELEASED); T1's UAT-06 request for EQ-006 asked for 7 days; T1's UAT-10 request was for EQ-005, 1 day, not UAT-002 Laptop Cart Beta.
- **Why it is not a defect.** `requested_days` is validated `between:1,7` (`BorrowRequestController`, `BorrowRequest::MAX_DAYS = 7`), so 7 days is allowed and the server was right to accept it. The short reason and the duplicate request on UAT-001 were not created. Integrity checks 1, 2, 2b and 5 returned no rows, and the audit counts match the rows.
- **What it did show.** Two follow-ups: the cards name no item or day count (FIND-04) and the demo items sit beside the UAT items (FIND-05). It also means the UAT-06 "loan length too long" refusal was **not observed in the session** (see FIND-06).
- **Original severity note kept:** the first record said "High if an invalid request was accepted". That did not happen.

### FIND-02: `staging-release.ps1` printed a false FAIL on 2026-11-16
- **What happened.** `FAIL GET /api/health/ready -> 000` and "Staging is NOT ready", while its own body line and the browser showed `{"status":"ok"}`. Today's output (2026-11-17) was not shown to Claude.
- **Likely cause (read from the script, not reproduced).** `Get-Code` uses `curl.exe -m 10`. A code of `000` means no answer within 10 s. `maintenance-plan.md` (B5) records that a readiness answer can take 8 to 53 s right after a start, so the first readiness call can time out while the second (the body line) succeeds. **This is a hypothesis.**
- **Why defer.** It cannot be made to happen on demand, the script has no test harness, Claude has no PowerShell, and the 2026-11-17 reset and session were not affected. Nothing a tester sees changes.
- **If the developer prefers to fix now (small, at most 5 fixes in total, D7):** in Step 7 give the readiness check its own call with `-m 60` and up to 3 tries 5 s apart; the other checks keep `-m 10`. No test can be written first for a PowerShell script, so the retest is: run `deploy\staging-release.ps1 -SkipBuild` twice and read the readiness line. Claude would write it in a later chunk, built and untested.

### FIND-03: the seed data is relative to the seeding moment
- UAT-005 is due in 20 hours and reminders are skipped for 20 hours from the seed. After an overnight pause the state no longer matched the cards, so the 2026-11-16 partial run was dropped and 2026-11-17 started from a fresh seed. The limit is already written in `uat-plan.md` section 11 ("re-run `UatSeeder` before each proof or each tester run"). Fixing it means changing the seeder, which needs code, tests and a new run, for a limit that is documented and was handled.

### FIND-04: three task cards name no item and no number
- UAT-05 ("a borrower has brought an item back"), UAT-06 ("a loan length that is too long") and UAT-10 ("do the same as the first card") left the tester free to pick. The proposed wording is appended to `task-cards.md` under "Revision after the 2026-11-17 session". The text used in the session is left unchanged above it.
- **Retest.** The three revised cards were **not** run with testers. The honest retest is a developer dry run of those three cards on a reseeded staging (the same way as the chunk 3 relay), which is optional and would be recorded as the developer alone, not as UAT.

### FIND-05: demo items are visible to testers
- `DatabaseSeeder` items EQ-001 to EQ-006 stay in the equipment list next to UAT-001 to UAT-006, and two requests were made for EQ-005 and EQ-006. Hiding them would need a change to the seeding steps (code and a test). Deferred: with FIND-04 the cards name the item, and the session's data was correct either way. If a later round wants a clean list, the cheapest route is an operator step, not a code change.

### FIND-06: per-card records were not kept
- **What is missing.** Per-card times, help counts, exact words, the exact app messages at UAT-06 (three cases), UAT-07 and UAT-09, the UAT-08 note text, and the pre-reset integrity check of 2026-11-16. The developer's report says every card was completed and nothing went wrong, and his estimate is about 30 s to 1 min per card. **None of that is evidenced.**
- **What this means for the criteria.** AC-55 (10 of 12 attempts without help), AC-56 (times) and AC-57 (the tester states the cause and recovers without help) **cannot be confirmed from the records.** For UAT-06 the "too long" case was not exercised at all (7 days is allowed), so AC-57 is at best partly observed there. Ratings of 5 from everyone, with the free text "good", are illustrations, not evidence of ease.
- **Options (developer's choice).** (a) Watch the recording and write what it shows (help counts, times, messages, hesitation) into `uat-session-record.md` marked "from the recording"; Claude adds them in chunk 6 from what the developer reads out. (b) Do not, and the evaluation matrix says "not confirmed" for AC-55 to AC-57. Either is honest; writing "met" without (a) is not.
- **Update 2026-11-17 (after the developer reviewed the recording).** The developer chose option (a) after first choosing (b). His reading: all 12 attempts matched the expected lines, no help, no visible hesitation. Written into `uat-session-record.md` ("Recording review"). Result: **AC-55 12 of 12 without help (threshold 10), AC-56 no card over its limit, AC-57 partly confirmed.** Limits: it is the facilitator's own reading, no timestamps, no stopwatch, no sheets, Claude did not see the recording; the UAT-06 "too long" case was not exercised (the developer's answer "not too long" is read as a number not above 7); the UAT-08 note text, the UAT-09 message line and restart time, exact times and the testers' own words stay "not recorded" by the developer's choice. Status of FIND-06: **partly resolved** (help and limits answered; the details above stay open and are stated in the matrix).
- **Privacy.** The recording stays in the private Drive folder. What goes in the repo are counts, times and message text, never names, faces or voices (AC-67).

## Counts
| Item | Value |
|------|-------|
| Findings | 6 (FIND-01 to FIND-06); none deleted |
| Critical | 0 |
| High | 0 |
| Medium | 1 (FIND-06, proposed) |
| Low | 5 |
| Code fixes proposed now | 0 (FIND-02 is optional) of the 5 allowed (D7) |
| Docs fixes proposed now | 1 (FIND-04) |
| Migrations or `composer.lock` changes | none, rollback to `week-16` stays safe |

## Exit rule (AC-59) and retest (AC-60): status on 2026-11-17
- **No Critical or High finding is open** (none was raised). Every finding has a proposed disposition; all six are approved (decisions table). The decision is the developer's, because no sponsor is named.
- **AC-60.** No code, route, response or contract change is proposed, so nothing in the application needs a retest yet. `php artisan test` (120), `flutter test` (15), the Postman collection (149) and `scan-secrets.ps1` are **not rerun in this chunk**; the last results are the chunk 3 baseline. They are rerun in the chunk 6 evidence round, and also right after any code fix the developer approves (for example the FIND-02 script change). The original scenario for FIND-04 is the dry run described above.
- **Update 2026-11-17 (chunk 6c).** The regression set was rerun: `php artisan test` 120 and `flutter test` 15 (developer's word), Postman 149 of 149 (screenshot seen), `scan-secrets.ps1` explained in [`test-matrix.md`](test-matrix.md). No finding changed. The FIND-02 text did not appear in that one reset, which does not change its disposition.

## Decisions (approved by the developer in chat, 2026-11-17; recorded here by Claude)
| ID | Developer's decision (approve, change, reject) | Date |
|----|-----------------------------------------------|------|
| FIND-01 | Approved as proposed: not a defect | 2026-11-17 |
| FIND-02 | Approved as proposed: defer to backlog B15; no script change in Week 17 | 2026-11-17 |
| FIND-03 | Approved as proposed: won't fix (documented limit) | 2026-11-17 |
| FIND-04 | Approved as proposed: docs-only fix (revised wording in `task-cards.md`); optional developer dry run not yet done | 2026-11-17 |
| FIND-05 | Approved as proposed: defer (covered by the FIND-04 wording) | 2026-11-17 |
| FIND-06 | Approved as proposed: option (a), read the recording and send counts, times and messages in chunk 6. **If the recording is not reviewed, the result for AC-55 to AC-57 is written as "not confirmed"** **Update 2026-11-17:** the developer reviewed the recording after all; the "not confirmed" fallback is replaced by the readings above (AC-55 12 of 12, AC-56 no card over limit, AC-57 partly); other details stay "not recorded". | 2026-11-17 |

## Backlog entry proposed (for `maintenance-plan.md`, added in chunk 6 if FIND-02 is deferred)
B15: In `deploy\staging-release.ps1`, give the readiness check a timeout of 60 s with up to 3 tries, so a slow first answer is not reported as a failure. Source: FIND-02. Owner: Maintainer.
