# UAT Session Record (Week 17, chunk 4)

Status: **prepared before the session, 2026-11-16. Built by Claude, not run. Every empty cell is filled by the developer during or right after the session; nothing is deleted afterwards** (`uat-plan.md` section 7, rule 7). Testers are T1, T2 and T3 only. The mapping from code to person is kept on paper or in a private folder, never in the repo. All data is fake.

If a session cannot be held with all three testers, say so in the first line of "Session notes" and mark the affected scenarios "not run" (stop rule, `uat-plan.md` section 7, rule 6).

## Run header (`uat-plan.md` section 8)
| Item | Value |
|------|-------|
| Planned date and time | 2026-11-16, 08:30 (planned; may move with the testers' free time; the actual start goes in the next row) |
| Actual date and time | |
| Git commit (`git rev-parse --short HEAD`) | |
| `APP_ENV`, `APP_DEBUG`, `LOG_LEVEL`, database | |
| Seed used | `UatSeeder` after `staging-release.ps1 -SkipBuild` |
| `php -m` OPcache line | |
| `ops:status` output at the start (paste) | |
| Backup before UAT data (`backup-restore.ps1`: tables same? time) | |
| Browser and window size | |
| Testers | T1 borrower, T2 approver, T3 staff |
| Facilitator and operator | Developer |
| Sponsor | None yet (see Acknowledgement) |
| Consent read aloud and given (yes or no, per tester) | T1: , T2: , T3: |
| Recording | Screen only, no audio, no faces, if consent is given: yes or no |

## Before the session: entry checks (`uat-plan.md` section 6)
Tick when done and write the time. A check that cannot be met is written down as a deviation, not skipped.

| # | Check | Done (time) | Result or deviation |
|---|-------|-------------|---------------------|
| E1 | XAMPP MySQL running; `php -m` shows OPcache | | |
| E2 | `deploy\backup-restore.ps1` run first (dump stays outside the repo) | | |
| E3 | `deploy\staging-release.ps1 -SkipBuild` (reset) | | |
| E4 | `UatSeeder` run with `UAT_PASSWORD` typed in the shell (fake, 12+ characters, not a placeholder; never written to a file) | | |
| E5 | API on 127.0.0.1:8001 and Caddy on 9443 running; `https://localhost:9443` loads; readiness answers `{"status":"ok"}` | | |
| E6 | `ops:status` read and pasted into the run header | | |
| E7 | Queue worker window ready (the normal worker; the wrong-URL worker is started later, only for UAT-08) | | |
| E8 | Login cards (UAT accounts), task cards, this record, stopwatch ready; login cards stay out of every frame | | |
| E9 | Consent text read to each tester | | |

Consent text (read aloud): "This is a test of the app, not of you. All data is fake. I will time the tasks and write notes using your code (T1, T2 or T3), not your name. I will not record your face or voice. You can stop at any time. Do you agree?"

## Attempts (one row per tester per scenario; order from `uat-plan.md` section 11)
Rule: stopwatch from the end of reading the card to "I'm done" or the success screen. Help = anything beyond "What would you do?" or "Read the card again": count it and write the exact words.

Planned attempts: **12** (UAT-07 is done by all three testers). AC-55 says 80% rounded up when the count is not 11, so the threshold is **10 of 12 completed without help**. Time limits from AC-56: UAT-01 3 min; UAT-02, 03, 05 2 min each. A miss is a finding.

| Order | Scenario | Tester | Item or step (from `test-data-plan.md`) | Start | Stopwatch | Help (count and exact words) | Completed (yes, with help, no) | What the tester said or did | Finding ID |
|-------|----------|--------|------------------------------------------|-------|-----------|------------------------------|-------------------------------|-----------------------------|------------|
| 1 | UAT-01 | T1 | Request UAT-001 Projector Alpha, 3 days, with a reason | | | | | | |
| 2 | UAT-02 | T2 | Approve the new request for UAT-001; reject UAT-003 Camera Kit Gamma with a note | | | | | | |
| 3 | UAT-03 | T3 | Release UAT-004 Speaker Delta | | | | | | |
| 4 | UAT-04 | T2 | Name the overdue item and who has it (UAT-006 Projector Zeta, `uatborrower2`) | | | | | | |
| 5 | Operator action, then UAT-08 | T2 | Operator: `reminders:run`, then the wrong-URL worker until both new reminders fail. Expect Manual handling lists 3; one marked handled with a note; 2 remain | | | | | | |
| 6 | UAT-05 | T3 | Return UAT-005 Extension Cord Epsilon, result good; `uatborrower1` then sees it closed | | | | | | |
| 7 | UAT-06 | T1 | Short reason; a loan length that is too long; UAT-001 again while its approved request is still open | | | | | | |
| 8 | UAT-07 | T1 | Wrong password, right password, sign out | | | | | | |
| 9 | UAT-07 | T2 | Same | | | | | | |
| 10 | UAT-07 | T3 | Same | | | | | | |
| 11 | UAT-09 | T1 | Borrower's request list while the operator stops the API (Ctrl+C), then restarts it after the tester reacts | | | | | | |
| 12 | UAT-10 | T1 | Request UAT-002 Laptop Cart Beta at about 360 px wide | | | | | | |

### Prediction check for UAT-09 and UAT-08 (predictions are in `week17-predictions.txt`; wrong predictions stay)
| Prediction | What happened |
|------------|---------------|
| U4: generic error message on UAT-09; "Try again" understood by the tester | |
| U5: T2 finds Mark handled quickly; the note is the hesitation | |
| UAT-08 count: 3 listed, 2 remain | |
| API stop: exact message shown (write the words) | |
| Time from restart to the list loading | |

## Post-session ratings (1 = strongly disagree, 5 = strongly agree; question 5 is free text)
| Question | T1 | T2 | T3 |
|----------|----|----|----|
| 1 I could do my tasks without help | | | |
| 2 The messages told me what to do next | | | |
| 3 I always knew where I was in the app | | | |
| 4 I would be comfortable using this for real equipment, with real training | | | |
| 5 Wanted but missing (free text; possible change requests) | | | |

With three testers these are illustrations, not statistics.

## Acknowledgement (AC-61; the signed paper stays private, the repo holds only the decision)
| Role code | Initials | Date | Decision (accept, accept with conditions, reject) | Conditions or comment |
|-----------|----------|------|---------------------------------------------------|-----------------------|
| T1 borrower | | | | |
| T2 approver | | | | |
| T3 staff | | | | |
| Sponsor | | | | **Sponsor acknowledgement pending.** (No sponsor is available yet; the developer's decisions are the developer's, not the office's.) |

## Findings noted during the session (raw; triage and dispositions are chunk 5)
Write each as it happens. Severity: Critical, High, Medium, Low (`uat-plan.md` section 9). Type: defect, usability issue, change request, not a defect. Do not fix anything during the session.

| Finding ID | Scenario | Tester | What happened (exact) | Severity (proposed) | Type (proposed) | Evidence (screenshot or time in the recording) |
|------------|----------|--------|-----------------------|---------------------|-----------------|-----------------------------------------------|
| FIND-01 | | | | | | |
| FIND-02 | | | | | | |
| FIND-03 | | | | | | |
| FIND-04 | | | | | | |
| FIND-05 | | | | | | |

Add rows as needed. A session with no finding is possible but unlikely; write "none" and why you think so if it happens.

## After the last card: integrity check BEFORE any reset (carried from chunk 3, deviation D8)
Do not reset, re-seed or run `staging-release.ps1` until this is done.

Command (from the repo root, PowerShell), as written at the top of `loadtest/integrity-check.sql`:
`& 'C:\xampp\mysql\bin\mysql.exe' -u root equipment_staging -t -e "source loadtest/integrity-check.sql"`

| Check | Expected | Actual |
|-------|----------|--------|
| `database_in_use` | `equipment_staging` | |
| 1 Two open requests for the same borrower and item | 0 rows | |
| 2 Item released twice | 0 rows | |
| 2b Released item still shown available | 0 rows | |
| 5 Request in an unreachable state | 0 rows | |
| 3 `borrow_requests_total` | write the number; compare with the requests the testers made plus the seeded ones | |
| 4 `audit_logs_total` and the rows per action | write the numbers; a create adds one audit row, a return adds two | |
| Requests by status | write the rows | |
| Equipment by status | write the rows | |

Also after the session: `ops:status` output (paste), and a look at the API log for anything other than the expected 401 (wrong password), 422 (invalid requests) and the error during the UAT-09 API stop (prediction U9).

## Session notes
Write here: who was late or missing, what went wrong with the setup, resets done during the session (time and why), recording on or off, anything that changes how to read the results.

## Gate for chunk 4
Every attempt recorded, failures included; acknowledgements collected or "pending" stated; integrity check run before any reset (or the deviation written down). Developer's statement and date:
