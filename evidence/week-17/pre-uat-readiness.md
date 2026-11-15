# Pre-UAT Readiness Record (Week 17, chunk 3: sections 3a, 3b, 3c)

Status: **run on 2026-11-15, filled in from the developer's pasted terminal output and the developer's own report.** Source of each cell: "output" means the developer pasted it into the chat; "reported" means the developer said so and Claude did not see the evidence (screen recording, screenshots, Postman Runner). Wrong predictions stay (see `week17-predictions.txt`, Addendum 1). Claude did not run any of it.

Scope of every result: one Windows PC, local staging (`https://localhost:9443`), fake data only, no testers. Nothing here says "production ready".

## Run header
| Item | Value |
|------|-------|
| Date and time | 2026-11-15, about 15:17 (A1) to evening (C10 reset) |
| Git commit | `06f72bd` on `week-17-uat` (the addendum commit; the four other chunk 3 files were uncommitted during the run) |
| Environment (`ops:status`) | `APP_ENV` staging, debug off, log level info, database `equipment_staging`, queue driver database |
| `php -m` OPcache line | `Zend OPcache` (output) |
| Seed used | `DatabaseSeeder` (release script) and `UatSeeder` |
| Browser and window size | Chrome Incognito, about 1280 px wide (reported) |
| Facilitator | Developer (alone; no testers in chunk 3) |

## 3a Baseline (decision D11 and Week 16 entry conditions)
| Step | Check | Expected | Actual | Result |
|------|-------|----------|--------|--------|
| A1 | Addendum 1 committed alone | one commit, one file | `06f72bd`, 2026-11-15 15:17:21 +0800, 1 file, 38 insertions, push `4218418..06f72bd` (output) | pass |
| A2 | `php -m` OPcache | line present | `Zend OPcache` (listed twice: PHP and Zend module lists) (output) | pass |
| A3 | Reset `-SkipBuild` | staging up, 10 to 30 s | all checks ok, `ops:status` clean, **77 s** (output); a later reset took 12 s | deviation: prediction wrong (see D2) |
| A4 | `php artisan test` | 120 passed (750) | 120 passed (750 assertions), 99.88 s (output) | pass |
| A5 | `flutter test` | 15 passed | 15 passed (output) | pass |
| A6 | Postman on staging | 149/149 | 149/149 reported; developer has the Runner screenshot; run time not given to Claude | pass (reported), run time open |
| A7 | `scan-secrets.ps1 -StartBytes 5163800` | exit 0, positive control above zero | exit 0; positive control found in 13 commits; log slice scanned: 18,591 bytes (5,163,800 to 5,182,391) (output) | pass, with a small log slice (see D5) |
| A8 | Reset, `backup-restore.ps1` | every table `same` | 14 tables compared, all same; dump 16,165 bytes; SHA-256 `8B4E483CA4A9AEBA4F7DBF05A5B1B0E858395130D881AC975A75F131AF0E787D`; dump outside the repo, not opened (output) | pass |

## 3b Reminder path on staging (decision D10)
| Step | Check | Expected | Actual | Result |
|------|-------|----------|--------|--------|
| B1 | `NOTIFIER_CA_BUNDLE` set, file exists | set, exists | release script had warned it was empty; Caddy `root.crt` exists (`True`); after editing `.env.staging`: `set: True`, `exists: True` (output) | pass after setup fix (see D3) |
| B2 | `UatSeeder`; `app.env` and database | staging, `equipment_staging` | both as named; 2 seeded rows: id 1 reminder to `uatborrower2` SENT (attempts 1), id 2 overdue to `uatapprover` FAILED_NEEDS_HUMAN (attempts 3, "Simulated notifier failure (fake data).") (output) | pass |
| B3 | `reminders:run` | queued 2, skipped (limit) 0, skipped (sent recently) 1 | exactly that; new PENDING rows 3 (overdue UAT-006 to **`approver@example.test`**) and 4 (reminder UAT-005 to `uatborrower1`) (output) | pass; recipient observation (D1) |
| B4 | Worker with certificate | both SENT, attempts 1 | worker jobs DONE (1 s and 221.63 ms); rows 3 and 4 SENT, attempts 1; worker keeps running until Ctrl+C by design (output) | pass |
| B5 | `UatSeeder` again, `reminders:run` | same numbers | queued 2, skipped (limit) 0, skipped (sent recently) 1; `ops:status`: jobs waiting 2, failed jobs 0, reminders PENDING 2, Manual handling backlog 1, log 5,064 KB; rows 5 to 8 (output) | pass |
| B6 | Worker with closed-port URL | both FAILED_NEEDS_HUMAN, attempts 3, "Receiver not reachable.", 20 to 30 s | rows 7 and 8 FAILED_NEEDS_HUMAN, attempts 3, "Receiver not reachable."; 6 FAIL lines from 11:24:36 to 11:25:09 = **33 s** (output) | pass on state; time slightly over (D6) |
| B7 | `ops:status` after B6 | backlog 3, jobs waiting 0 | backlog **3**, jobs waiting 0, reminders PENDING 0, **failed jobs 2**, log 5,111 KB (output) | pass |
| B8 | Log lines | queued, sent / attempt_failed, needs_human | all present for notifications 3, 4, 7, 8; ids only; no e-mail, token or key in the lines; the error lines show a local file path (`SignedHttpNotifier.php:56`), so keep them out of shared screenshots (output) | pass |

First try of B4 and B6 worked; no failed first try in 3b.

Mismatch (settled 2026-11-15, see `test-data-plan.md`, "Settled mismatch"): items in Manual handling after B6 = **3** (ops:status; the developer reports the browser list also showed 3, and 2 remained after one was marked handled). The UAT-08 card says "listed once" and "empty afterwards". Developer's decision (card wording, expectation, or seeder change as its own segment): settled on 2026-11-15: the card and `uat-plan.md` section 5 were changed to expect 3 listed and 2 remaining; the seeder is unchanged (see `lab-log.md`).

## 3c The relay run alone (screen recording)
| Step | Check | Expected | Actual | Result |
|------|-------|----------|--------|--------|
| C1 | Reset, `UatSeeder`, health | all ok | health `{"status":"ok"}` on 9443 (output); seeder ran without error | pass |
| C2 | Recording and its length | 20 to 40 min | **6:04** (reported) | deviation: prediction wrong (D7) |
| C3 | UAT-01 to UAT-10 | all 10 completable alone; UAT-02 needs UAT-01 | developer reports all ten completed alone; no card needed a different item (reported; per-card detail is in the recording) | pass (reported) |
| C4 | UAT-08 operator action | see mismatch | 3 listed, 2 remain after one marked handled (reported: developer says the prediction held) | pass on prediction; mismatch stays open |
| C5 | UAT-09 API stopped and restarted | message, not blank; list loads after restart | developer reports it passed; message text is in the recording only | pass (reported); text not captured in writing |
| C6 | Keyboard-only walk | completable; at least one unclear focus spot (R3) | developer reports it done and passed; the answers on the "Enable accessibility" button and focus spots were not given to Claude in writing | pass (reported); details open |
| C7 | 200% zoom | narrow layout appears; no sideways scroll on login | developer reports done and passed | pass (reported) |
| C8 | 1280 px and 360 px screenshots | rail at 1280, bottom bar at 360 | developer reports all screenshots taken and matching | pass (reported) |
| C9 | Form and server validation agree | each refused with a message naming the problem | developer reports all four cases passed; exact messages and form-or-server origin are in the recording only | pass (reported); messages open |
| C10 | Integrity check, final reset | clean | **integrity check not run**: the reset ran before it (D8). Final reset: staging up in 11 s, `ops:status` all zeros (output) | deviation |

## Deviations
| # | Step | What differed | Cause | Decision |
|---|------|---------------|-------|----------|
| D1 | B3 | Overdue notice went to `approver@example.test`, not `uatapprover`; the seeded failed row for `uatapprover` was counted "sent recently" | Seeder pre-makes the failed row for `uatapprover`; the run then queued a second overdue notice for the dev approver | Observation; decide before chunk 4 which recipient UAT-08 expects |
| D2 | A3 | 77 s, prediction was 10 to 30 s | First reset after a while; a later reset took 12 s | Prediction wrong, kept |
| D3 | B1 | `NOTIFIER_CA_BUNDLE` was empty | Setup not done since Week 16 | Fixed in `.env.staging`; not repeated |
| D4 | A6 | Postman run time not recorded in text | Developer had the Runner screenshot only | Add the time from the screenshot in the evidence round |
| D5 | A7 | Only 18,591 bytes of the log were scanned | `-StartBytes` was the size before Postman, so only a small slice is new | Scan result stays valid for that slice only; the history part (13 commits) is the larger check |
| D6 | B6 | 33 s to the final state, prediction 20 to 30 s | Retry backoff plus 2 s per failed attempt | Prediction wrong by 3 s, kept |
| D7 | C2 | Recording is 6:04, prediction 20 to 40 min | One facilitator, no think-aloud, no testers; terminal work was off camera | Prediction wrong; expect a longer UAT session in chunk 4 |
| D8 | C10 | Integrity check not run before the final reset | The release-script reset was run first | No integrity result for this run; run it before any reset in a second short run if wanted, and always in chunk 4 |
| D9 | C1 | `UAT_PASSWORD` was set to a placeholder text by mistake, then re-seeded with a typed fake password | Pasted example text | Setup slip, no data affected (seeder resets its own rows) |
| D10 | B7, C4 | Manual handling shows 3 at the start and 2 after one is handled, not "listed once, empty afterwards" | See D1 and the open mismatch | Developer decides before chunk 4 |

Correction to a chat remark: the chat said notification ids "restart" after a reset. They do not: ids went 1 to 4, then 5 to 8, so the seeder deletes rows and the counter keeps going.

## Evidence to drop unsorted into `Downloads\FLUTTER\Week 17`
Terminal screenshots of: the addendum commit, `php -m` OPcache line, reset result, `php artisan test` summary, `flutter test` summary, Postman Runner summary, `scan-secrets.ps1` result, `backup-restore.ps1` result, `reminders:run` output (twice), the worker windows (success and failure), the SQL rows after each, `ops:status`; the screen recording (6:04); the 1280 px and 360 px screenshots; the keyboard and zoom notes. Claude renames and files them in chunk 6.

## Gate for chunk 3
All green, or each deviation written down above. Developer's statement and date: all steps of 3a, 3b and 3c were run and reported passed, with deviations D1 to D10 written down above; stated in chat by the developer on 2026-11-15, who asked Claude to write the record. Committed as `8228cf9`.
