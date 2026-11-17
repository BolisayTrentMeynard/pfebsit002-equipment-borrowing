# UAT Session Record (Week 17, chunk 4)

Status: **session run 2026-11-17 (staging, three testers). Filled by Claude from the developer's report in chat and from his screenshots of the terminal; Claude did not see the screen recording, the observation notes or the paper sheet.** The developer kept no per-card notes during the run, so several fields below say "not recorded" and the recording is the only source for them. Nothing was guessed. After the session the developer also ran a read-only query on `borrow_requests` (screenshot, section "Request rows after the session"), which explains the integrity counts. Testers are T1, T2 and T3 only; the real names are in a private restricted Drive folder, never in this repo. All data is fake.

## Run header (`uat-plan.md` section 8)
| Item | Value |
|------|-------|
| Planned date and time | 2026-11-16, 08:30 (first plan). A first session on 2026-11-16 (09:15 to 09:35, UAT-01 and UAT-02, different tester assignment) was **not recorded and is not counted**; the data aged overnight, so staging was reset and the run restarted on 2026-11-17 (see Session notes) |
| Actual date and time | 2026-11-17, cards from about 12:46 to 13:16, including pauses (developer's times) |
| Git commit (`git rev-parse --short HEAD`) | `d0a2765` (branch `week-17-uat`) |
| `APP_ENV`, `APP_DEBUG`, `LOG_LEVEL`, database | staging, off, info, `equipment_staging` (from `ops:status`) |
| Seed used | `staging-release.ps1 -SkipBuild` (reset), then `UatSeeder` with a new `UAT_PASSWORD` typed in the shell (reset and seed about 12:23 to 12:29) |
| `php -m` OPcache line | `Zend OPcache` seen on 2026-11-16; today's check not shown to Claude |
| `ops:status` at the start | Not shown in full. The top of a 12:57 screenshot shows Manual handling backlog 1 before the UAT-08 operator step (the one seeded failed notice); log file 5,190 KB |
| Backup before UAT data (`backup-restore.ps1`) | Dump `equipment_staging-20261117-122956.sql` in `C:\dev\pfebsit002-backups` (outside the repo); table comparison result not shown to Claude |
| Browser and window size | Chrome (Incognito per plan); window size not recorded |
| Testers | T1 borrower, T2 approver, T3 staff |
| Facilitator and operator | Developer |
| Sponsor | None yet (see Acknowledgement) |
| Consent read aloud and given | T1 yes, T2 yes, T3 yes (developer's report). Text used: names kept in a private folder for the teacher, not in the public project files; no face or voice recording |
| Recording | Screen recording made (developer: the app messages are "in the video"); mic off per plan; kept in the private Drive folder, not in this repo |

## Before the session: entry checks (`uat-plan.md` section 6)
| # | Check | Done (time) | Result or deviation |
|---|-------|-------------|---------------------|
| E1 | XAMPP MySQL running; OPcache | about 12:20 | Not shown today; seen 2026-11-16 |
| E2 | Backup | 12:29:56 | Dump written (file name above). Taken after reset and seed |
| E3 | Reset (`staging-release.ps1 -SkipBuild`) | about 12:23 | Done; output not shown to Claude today |
| E4 | `UatSeeder` with `UAT_PASSWORD` | about 12:25 | Done. First sign-in at the browser failed ("Email or password is wrong.") because the password typed there was the one from the previous day; the new one was used and sign-in worked. Operator slip, not UAT-07 |
| E5 | API, Caddy, readiness | before 12:46 | Not shown to Claude |
| E6 | `ops:status` pasted | | Partly: see Run header |
| E7 | Worker window ready | | Done (wrong-URL worker used at UAT-08) |
| E8 | Login cards, task cards, stopwatch | | Cards used; no stopwatch times kept |
| E9 | Consent read | before 12:46 | Yes for all three |

## Attempts (one row per tester per scenario; order from `uat-plan.md` section 11)
**What was kept:** the developer did not write per-card notes. His estimate for every card is "about 30 seconds to 1 minute", from memory, not from a stopwatch. Help counts, exact words, messages and tester comments are not recorded; they can only be read from the screen recording. The developer reported that nothing went wrong in any card. Planned attempts: 12; AC-55 threshold 10 of 12 completed without help. **AC-55 and AC-56 cannot be confirmed from these notes (see "Recording review" below: the developer's later reading of the recording)**: the developer's report says all cards were completed and fast, but "without help" and the times are not evidenced.

| Order | Scenario | Tester | Item or step | Start | Stopwatch | Help (count and exact words) | Completed | What the tester said or did | Finding ID |
|-------|----------|--------|--------------|-------|-----------|------------------------------|-----------|-----------------------------|------------|
| 1 | UAT-01 | T1 | Request UAT-001 Projector Alpha, 3 days, with a reason | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 2 | UAT-02 | T2 | Approve the new UAT-001 request; reject UAT-003 Camera Kit Gamma with a note | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 3 | UAT-03 | T3 | Release UAT-004 Speaker Delta | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 4 | UAT-04 | T2 | Name the overdue item and borrower (UAT-006 Projector Zeta, `uatborrower2`) | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 5 | Operator action, then UAT-08 | T2 | Operator: `reminders:run`, wrong-URL worker. Manual handling lists 3; one marked handled with a note; 2 remain | 12:57 (screenshot time of the operator `ops:status`) | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report); `ops:status` 13:15 shows backlog 2 | note text not recorded | |
| 6 | UAT-05 | T3 | Return UAT-005 Extension Cord Epsilon, result good | not recorded | about 0:30 to 1:00 (estimate) | not recorded | a return was completed, but **not of UAT-005**: the rows show UAT-004 Speaker Delta returned good and UAT-006 Projector Zeta returned with missing parts; UAT-005 is still RELEASED | in recording | FIND-01 |
| 7 | UAT-06 | T1 | Short reason; loan length too long; UAT-001 again while its approved request is open | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report); a request for EQ-006 with 7 days was accepted (7 is the maximum allowed); see FIND-01 | messages in recording | FIND-01 |
| 8 | UAT-07 | T1 | Wrong password, right password, sign out | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 9 | UAT-07 | T2 | Same | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 10 | UAT-07 | T3 | Same | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | in recording | |
| 11 | UAT-09 | T1 | Borrower's list while the operator stops the API, then restarts it | not recorded | about 0:30 to 1:00 (estimate) | not recorded | yes (developer report) | message in recording | |
| 12 | UAT-10 | T1 | Request an item at about 360 px (the card names no item; the plan expected UAT-002 Laptop Cart Beta) | not recorded (request row created 13:14:01) | about 0:30 to 1:00 (estimate) | not recorded | yes: a request for EQ-005, 1 day, was created | in recording | FIND-01 |

### Prediction check (predictions are in `week17-predictions.txt`; wrong predictions stay)
| Prediction | What happened |
|------------|---------------|
| U4: generic error message on UAT-09; "Try again" understood | Not recorded. From the app code the list should show "Cannot reach the server" with a Try again button; the exact words seen are in the recording |
| U5: T2 finds Mark handled quickly | Not recorded (developer: nothing went wrong) |
| UAT-08 count: 3 listed, 2 remain | Matches in `ops:status`: backlog 1 at the start, 3 after `reminders:run` and the wrong-URL worker (screenshot 12:57:41; run said queued 2, skipped sent recently 1), 2 after one was handled (screenshot 13:15). Failed jobs 2 in both |
| API stop: exact message shown | In recording |
| Time from restart to the list loading | Not recorded |

Observed wrong-password message (screenshot taken before the session, at the sign-in screen, for `uatborrower1`): **"Email or password is wrong."** It does not say whether the e-mail exists.

## Recording review (developer's reading, reported in chat 2026-11-17)
**Source and limits.** The developer watched the screen recording and answered a private form (`recording-notes.txt`, kept outside the repo) in which every attempt had an expected line and he wrote only differences. He reported in chat: all attempts matched the expected lines, no visible hesitation, "all perfect". **Claude did not see the recording.** There are no timestamps, no stopwatch times and no observation sheets, and the only viewer was the facilitator himself, so this is one person's reading, not an independent check. He did not say on which date he watched or whether he watched all of it. The cells above that say "not recorded" are left as written; this section adds what the review reported.

| Attempt | Scenario | Tester | Developer's reading of the recording |
|---------|----------|--------|--------------------------------------|
| 1 | UAT-01 | T1 | Matched expected: no help, completed, under 3:00 |
| 2 | UAT-02 | T2 | Matched expected: no help, completed, under 2:00, note written on the reject |
| 3 | UAT-03 | T3 | Matched expected: no help, completed, under 2:00 |
| 4 | UAT-04 | T2 | Matched expected: no help, T2 named the overdue item and its borrower |
| 5 | UAT-08 | T2 | Matched expected: no help, 3 items found, one marked handled with a note, 2 remain (also shown by `ops:status` at 13:15) |
| 6 | UAT-05 | T3 | Matched expected: no help, under 2:00 (a return was recorded; the items are in the request rows, FIND-01) |
| 7 | UAT-06 | T1 | Case 1 (short reason) and case 3 (open request on the same item) matched expected. **Case 2 (loan too long): see the exception below** |
| 8 to 10 | UAT-07 | T1, T2, T3 | Matched expected: no help, wrong password refused, sign-in worked, sign-out worked |
| 11 | UAT-09 | T1 | Matched expected: no help, "Cannot reach the server" with Try again, list loaded after the restart |
| 12 | UAT-10 | T1 | Matched expected: no help, nothing cut off, a request created |

**Exception (UAT-06, case 2).** Asked what number T1 typed for "too long", the developer answered "not too long". Claude reads this as: the number was **not above 7**, which agrees with the 7-day request for EQ-006 in the request rows (7 is allowed). If so, the refusal "Enter 1 to 7 days." was **not shown** and case 2 was **not exercised**. This is Claude's reading of a short answer; the developer can correct it.

**Still "not recorded" (the developer chose to leave them so):** the note text T2 typed at UAT-08 and T2's own words on why the reminders failed; the message line under "Cannot reach the server" at UAT-09, the seconds from restart to the list loading, and what T1 said they would do; exact times; the viewing date and how much of the recording was watched.

**What this means for the criteria (stated with the limits above)**
| Criterion | Result | Basis |
|-----------|--------|-------|
| AC-55 (10 of 12 attempts without help) | **12 of 12 completed without help, per the developer's reading** | His reading of the recording; no observation sheets exist. In attempt 7 case 2 was not exercised |
| AC-56 (UAT-01 3 min; UAT-02, 03, 05 2 min each) | **No card over its limit, per the developer's reading** | No stopwatch and no exact times; he judged each against the limit |
| AC-57 (tester states the cause and recovers without help) | **Partly confirmed** | Recovered without help: UAT-07 (three testers), UAT-08, UAT-09, UAT-06 cases 1 and 3. **Not exercised:** UAT-06 case 2. The testers' own words are not recorded |

The ratings (all 5, free text "good") remain illustrations. The "loan too long" rule is covered by the form and server checks reported in chunk 3 (AC-68) and by the Week 15 tests, not by this session.

## Post-session ratings (1 = strongly disagree, 5 = strongly agree; question 5 is free text)
| Question | T1 | T2 | T3 |
|----------|----|----|----|
| 1 I could do my tasks without help | 5 | 5 | 5 |
| 2 The messages told me what to do next | 5 | 5 | 5 |
| 3 I always knew where I was in the app | 5 | 5 | 5 |
| 4 I would be comfortable using this for real equipment, with real training | 5 | 5 | 5 |
| 5 Wanted but missing (free text) | "good" (developer's summary; testers' words not kept) | same | same |

With three testers these are illustrations, not statistics. All fives with no comment is also a reason to read the recording for hesitation.

## Acknowledgement (AC-61; the signed paper stays private, the repo holds only the decisions)
| Role code | Initials | Date | Decision (accept, accept with conditions, reject) | Conditions or comment |
|-----------|----------|------|---------------------------------------------------|-----------------------|
| T1 borrower | on the private sheet | 2026-11-17 | **Accept** (developer's report: all three accept; the sheet was not seen by Claude) | none stated |
| T2 approver | on the private sheet | 2026-11-17 | **Accept** (developer's report) | none stated |
| T3 staff | on the private sheet | 2026-11-17 | **Accept** (developer's report) | none stated |
| Sponsor | | | | **Sponsor acknowledgement pending.** No sponsor is available yet; the developer's decisions are the developer's, not the office's. |

## Findings noted during or after the session (raw; triage and dispositions are chunk 5)
| Finding ID | Scenario | Tester | What happened | Severity (proposed) | Type (proposed) | Evidence |
|------------|----------|--------|---------------|---------------------|-----------------|----------|
| FIND-01 | UAT-05, UAT-06 | T3, T1 | The integrity output after the run shows one more request, one more return and one more close than the planned relay predicts (see Integrity). Arithmetic from the seeder header: planned relay gives 7 requests (RELEASED 2, CLOSED 2, PENDING 1); observed 8 (RELEASED 1, CLOSED 3, PENDING 2), and equipment `needs_check` 1 where the plan expects 0. It looks like a second item was returned with a result other than good, and one extra request was accepted. **Cause not yet known.** If an UAT-06 case that should have been refused (nothing is created) created a request, that is a defect; if testers explored, it is not. Check the recording or the `borrow_requests` rows | Low if exploration; High if an invalid request was accepted | to be decided (not a defect, or defect) | Integrity screenshot; recording; `borrow_requests` query |
| FIND-02 | Setup (E3) | Operator | On 2026-11-16 `staging-release.ps1` printed `FAIL GET /api/health/ready -> 000` and "Staging is NOT ready" although its own body line and the browser showed `{"status":"ok"}`. Probably the first request ran before the stack was ready. Today's output was not shown | Low | tooling defect (false alarm) | 2026-11-16 terminal output |
| FIND-03 | Setup, UAT-08 | Operator | `UatSeeder` times are relative to the seeding moment (UAT-005 due in 20 hours, reminders skipped for 20 hours). After an overnight pause the seeded state no longer matched the cards, so the run had to restart from a fresh seed | Low | known limit of the test data (documented in `uat-plan.md` section 11) | Lab log |

Add rows as needed. No finding is deleted.

**Update (chunk 5, 2026-11-17):** the cause of FIND-01 is found (testers chose other items; not a defect), and FIND-04 to FIND-06 were added. Triage, proposed dispositions and the exit-rule status are in [`findings-register.md`](findings-register.md). The rows above are the raw notes and are left as written. Chunk 5 also repaired the attempts table: row 6 (UAT-05) had a copy of the FIND-01 findings row pasted after its last cell; the extra cells were removed, and the same text is in the register and in the findings table above.

## After the last card: integrity check BEFORE any reset
Command, from the repo root: `& 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t -e "source loadtest/integrity-check.sql"`. Run after the session, before today's session data was reset (output screenshotted by the developer).

| Check | Expected | Actual |
|-------|----------|--------|
| `database_in_use` | `equipment_staging` | `equipment_staging` |
| 1 Two open requests for the same borrower and item | 0 rows | 0 rows (no result set printed) |
| 2 Item released twice | 0 rows | 0 rows (no result set printed) |
| 2b Released item still shown available | 0 rows | 0 rows (no result set printed) |
| 5 Request in an unreachable state | 0 rows | 0 rows (no result set printed) |
| 3 `borrow_requests_total` | the seeded 5 plus what testers made | 8 (the plan predicted 7; explained by FIND-01: three new requests, UAT-001, EQ-006 and EQ-005) |
| 4 `audit_logs_total` and rows per action | create adds one audit row, a return adds two | total 25: approve 5, close 3, notification_handled 1, reject 1, release 4, return 3, submit 8 |
| Requests by status | | APPROVED 1, CLOSED 3, PENDING 2, REJECTED 1, RELEASED 1 |
| Equipment by status | | available 10, needs_check 1, on_loan 1 |

`ops:status` after the session (13:15 screenshot): staging, debug off, log info, database reachable, queue database, jobs waiting 0, failed jobs 2, reminders PENDING 0, Manual handling backlog 2, log 5,258 KB. The API log was not reviewed for unexpected errors (not recorded).

The integrity check before the reset of 2026-11-17 (on the data of the 2026-11-16 partial run): not shown to Claude, so not evidenced (carry-over D8 stays open).

## Request rows after the session (read-only query on `equipment_staging`, developer's screenshot; `created_at` is UTC, add 8 hours for Philippine time)
| id | asset_tag | status | requested_days | inspection_result | created_at (UTC) | Origin |
|----|-----------|--------|----------------|-------------------|------------------|--------|
| 1 | UAT-002 | CLOSED | 2 | good | 2026-11-17 04:29:11 | seeded |
| 2 | UAT-003 | REJECTED | 3 | | 04:29:11 | seeded, rejected at UAT-02 |
| 3 | UAT-004 | CLOSED | 2 | good | 04:29:11 | seeded, released at UAT-03, then returned good |
| 4 | UAT-005 | RELEASED | 2 | | 04:29:11 | seeded, **not returned** |
| 5 | UAT-006 | CLOSED | 3 | missing_parts | 04:29:11 | seeded, returned with missing parts |
| 6 | UAT-001 | APPROVED | 3 | | 04:46:57 (12:46:57) | UAT-01, approved at UAT-02 |
| 7 | EQ-006 | PENDING | 7 | | 05:06:40 (13:06:40) | UAT-06 |
| 8 | EQ-005 | PENDING | 1 | | 05:14:01 (13:14:01) | UAT-10 |

The seed times (12:29:11) agree with the backup at 12:29:56, and the first request (12:46:57) agrees with the developer's start time. Audit counts agree with these rows (submit 8, approve 5, release 4, return 3, close 3, reject 1).

## Session notes
- **2026-11-16 (not recorded, not counted):** UAT-01 and UAT-02 were run from 09:15 to 09:35 with a different assignment of people to roles, with no notes and no times. The session was paused for a class. Because the seed data is relative to the seeding time, it was reseeded on 2026-11-17 and the run restarted from UAT-01.
- **2026-11-17:** cards from about 12:46 to 13:16 with pauses; all three testers took part and gave consent; screen recording on; no per-card notes were kept, so times are the developer's estimate.
- The tester names and the signed sheet are in a private restricted Drive folder shared with the teacher. This differs from `uat-plan.md` section 12 and the old consent text; the repo still holds no names.
- Open (optional, from the recording): help counts and exact words, the exact messages at UAT-06, UAT-07, UAT-09 and the API restart time, the note text at UAT-08, run-header gaps (browser and window size, today's OPcache, reset and readiness output), and the pre-reset integrity check of 2026-11-16 (not evidenced). Closed: acknowledgement decisions (all accept) and the cause of FIND-01.

## Gate for chunk 4
Met, with stated gaps: all cards were run; integrity checks 1, 2, 2b and 5 are clean and the counts are explained by the request rows; the three testers accepted; the sponsor line says "pending"; FIND-01 is explained (not a defect). Gaps stated above: per-card times and help counts were not kept (the recording is the source), and the pre-reset check of 2026-11-16 is not evidenced. Developer's statement and date:
