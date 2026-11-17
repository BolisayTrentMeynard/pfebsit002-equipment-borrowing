# Conditional Handover Statement (Week 17, AC-66)

Written 2026-11-17 in chunk 6b by Claude from the repository records and the developer's reports. **Docs only. Built, untested.** The developer reviews and signs it as his own. **Sponsor acknowledgement pending.** No sponsor is named, so every decision here is the developer's, not an office's.

## 1. The statement
The Equipment Borrowing Reminder System (project #5: a Laravel API with a Flutter Web client) was built and tested on one Windows PC, in a local staging setup, with fake data. On the evidence in this repository it may be handed to a named maintainer for **continued evaluation and small controlled trials**: about 5 users, fake or consenting test data, local staging, under the conditions in section 4. It is **not ready for production use**, it must not hold real institutional data, and this statement does not say otherwise.

## 2. What was demonstrated (and its limit)
| Area | What was shown | Limit | Evidence |
|------|----------------|-------|----------|
| The registered workflow | Request, approval, release, return with inspection, closure, reminders, escalation, manual handling and the overdue and custody lists work for three roles | Three testers on the developer's PC, fake data. Results per the developer's reading of the recording | [`uat-session-record.md`](uat-session-record.md) |
| Usability in the session | 12 of 12 attempts completed without help (threshold 10); no card over its time limit; all three testers accepted | Facilitator's own reading, no stopwatch or sheets, UAT-06 "too long" case not exercised (AC-57 partly) | [`uat-session-record.md`](uat-session-record.md), [`findings-register.md`](findings-register.md) |
| API contract | 16 operations in `backend/openapi.yaml`, lint clean, route and role rules kept true by a drift test | Hand-written; one client | [`../../backend/openapi.yaml`](../../backend/openapi.yaml), [`evaluation-matrix.md`](evaluation-matrix.md) |
| Reminder path | A reminder was sent with a signed message; a permanent failure reached Manual handling and was marked handled by an approver | Started by hand; nothing runs it unattended | [`pre-uat-readiness.md`](pre-uat-readiness.md), UAT-08 in the session record |
| Staging operation | Release and reset script, readiness and `ops:status`, backup and restore, rollback rehearsal to `week-15` | One restore on an almost empty database; local only | [`../week-16/`](../week-16/INDEX.md) |
| Load at about 5 users | 5 users for 10 minutes, 0 errors, thresholds met | The 25-user peak missed its p95 target by 47.5 ms; the spike was not repeated after OPcache | [`../week-16/performance-results.md`](../week-16/performance-results.md) |
| Findings | 6 findings; none Critical or High; every one has a disposition | The exit decision is the developer's | [`findings-register.md`](findings-register.md) |

## 3. What was not demonstrated: future operational work
These are conditions before any real deployment. **None is met today.** Each has a backlog item in [`maintenance-plan.md`](maintenance-plan.md).
| Not done | Backlog | Risk |
|----------|---------|------|
| A server that runs several workers, and the peak and spike runs repeated on it | B1 | R20 |
| The scheduler run by the operating system and a supervised queue worker | B2 | R32 |
| A database account with limited rights and a secret from a store (now XAMPP `root`, no password) | B3 | R34 |
| A publicly trusted certificate with renewal (now Caddy's local CA) | B4 | R1 |
| An external readiness probe with a long timeout and an alert to a named owner | B5, B14 | R22, R23 |
| Log rotation and a retention rule | B6 | R25 |
| Scheduled backups, a copy off the machine, a restore tested on realistic data | B7 | R28 |
| Lists with a limit and a test at volume | B8 | R30 |
| The OPcache setting in version control or a build image | B9 | R21 |
| A rollback that keeps the readiness route | B10 | R27 |
| A review of the manual-handling recipient fields and an exact CORS origin | B11 | R14, R15 |
| A named owner, a contact channel, a response time, and a sponsor's acknowledgement | B14 | R22, R37 |
| A setup followed from a fresh clone by someone other than the author | (6d) | R42 |

## 4. Conditions for the continued controlled use
1. OPcache stays on in `C:\xampp\php\php.ini`; check with `php -m` before a session.
2. Staging is reset with `deploy\staging-release.ps1` before each session; `APP_ENV=staging`, `APP_DEBUG=false`, `LOG_LEVEL=info`.
3. The maintainer watches `/up`, readiness and `ops:status`; there is no on-call and no alert.
4. A backup is taken before UAT data is entered; the dump stays outside the repo.
5. `reminders:run` and `queue:work` are started by hand and any plan says so.
6. Only fake or consenting test data; no secrets, names, faces or voices in anything that goes in the repo; the Week 14 to 16 scans are rerun after changes.
7. No new feature, screen, route, role or channel without the sponsor's written approval ([`change-control-notes.md`](change-control-notes.md)).
8. About 5 users at most.

## 5. Open items the reader should know
- Open risks: R9, R14, R17, R20, R24, R29, R30, R32, R37, R42 ([`residual-risk-register.md`](residual-risk-register.md)).
- Deferred findings: FIND-02 (script false alarm, B15), FIND-05 (demo items visible, B16). FIND-04 (card wording) is fixed in the documents but the revised cards were not run with testers.
- Not recorded by the developer's choice: exact times, the UAT-08 note text, the UAT-09 message text and restart time, the testers' own words.

## 6. Acknowledgement
| Who | Decision | Note |
|-----|----------|------|
| T1 borrower, T2 approver, T3 staff | Accept (2026-11-17) | The developer's report; the signed sheet is private and was not seen by Claude |
| Sponsor | **Pending** | No instructor or lab custodian is named yet |
| Developer | Signs this statement as his own | Name and date: to be written by the developer on the submitted copy |

## 7. What this statement is not
It is not a security assessment, a performance guarantee or an approval by an office. It describes one setup and three people. It says nothing about real data, real users or hosting.
