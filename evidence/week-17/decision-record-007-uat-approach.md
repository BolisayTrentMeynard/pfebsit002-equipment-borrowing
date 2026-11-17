# Decision Record 007: How to run UAT (Week 17)

Date: 2026-11-15. Status: approved by the developer on 2026-11-15 (D1 to D13), before any build.

## Context
The prototype is functionally complete and tested technically (Weeks 13 to 16). Week 17 needs a controlled UAT with representative scenarios, findings with dispositions, retests, and an honest conclusion. The developer works alone; the system runs on one Windows PC behind Caddy's local certificate; the repo is public; data is fake.

## Decision question
How do we get a UAT that shows whether other people can use the system, without overstating what it proves?

## Options
| Option | For | Against |
|--------|-----|---------|
| **A. Moderated session, 3 testers (one per role) on the developer's PC (chosen)** | Real people, one per role; the developer can count help and time; works with the local certificate; findings are first-hand | Needs recruiting; 3 people are a small sample; the developer is also the author (bias), reduced by the facilitator rules |
| B. Developer tests all roles alone | No recruiting | Not UAT: the author knows every screen and cannot be a first-time user. Allowed only as a labeled fallback ("developer walkthrough, not stakeholder UAT") |
| C. Remote, unmoderated | Easy for testers | The app runs on `localhost` with a local certificate; no way to count help or time; testers' devices would show certificate warnings |
| D. Questionnaire only | Fast | Measures opinion, not whether the tasks can be done |

## Decision
Option A. Fallback to B only if fewer than 2 testers are found, and the labeling is then part of every record. Rules for validity are in `uat-plan.md` (verbatim cards, help counted, no fixing during sessions, stop rule, run header). Privacy: testers are T1 to T3 with initials only.

## Consequences
- New work for the developer: recruiting, running the session, the operator actions, the paper sheet.
- The conclusion is limited: "these 3 people could (or could not) do these tasks on this setup". It is not acceptance by an office.
- Findings may ask for features outside the registered scope; they are change requests and are not built.
- Fixes are capped (5), tests first, and avoid migrations and `composer.lock` changes so a rollback to `week-16` stays safe.

## Evidence
`acceptance-criteria.md` (AC-54 to AC-61), `uat-plan.md`, `task-cards.md`, `observation-sheet.md`, `week17-predictions.txt`.

## Review condition
Revisit if no tester is available by the session date, or if a sponsor asks for a different format.
