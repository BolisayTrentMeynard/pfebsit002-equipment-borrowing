# Contribution Record and AI Assistance (Week 17, chunk 6b)

Written 2026-11-17 by Claude from the progress log's working method and the repository history as reported by the developer. **Docs only. Built, untested.** The developer must read it, correct anything that is wrong, and be able to explain his own part in the Week 18 viva. It records who did what; it does not grade it.

## How the work was done
The project was built in small chunks. For each chunk the developer asked Claude (Anthropic's AI assistant) for one piece; Claude planned and wrote the code, tests and documents and handed over one zip; the developer put it in the project, ran it on his own PC, tested it, decided what to keep, and committed and pushed. Claude's sandbox had PHP on SQLite, Composer, Node, a diagram renderer and the Redocly CLI, but **no MySQL server, no Flutter, no k6 and no Windows**, so the results that count (PHPUnit on MySQL, `flutter test`, k6, Postman, PowerShell scripts) are the developer's runs.

## Contributions
| Area | Developer | Claude (AI assistant) |
|------|-----------|-----------------------|
| Project choice and scope | Chose project #5, got it registered, approved the scope and every plan | Proposed plans and stress-tested them against the module |
| Code (Laravel API, Flutter Web, seeders, tests) | Asked for each chunk, ran and tested it, fixed set-up problems, committed | Wrote the code and tests from the requests and the module |
| Environment and operation | Ran XAMPP MySQL, Caddy and the scripts; ran the worker, scheduler command, restarts and resets | Wrote the scripts and the run guides |
| Week 17 UAT | Lined up three testers, ran consent, facilitated and operated the session, recorded the screen, ran the integrity check, read the recording afterwards, decided every finding disposition | Wrote the plan, task cards, record, findings register and triage; filled the record from the developer's reports |
| Decisions (DR-007, DR-008, dispositions) | Approved or changed each decision | Proposed the options, scores and wording |
| Evidence | Saved the captures, terminal output and screenshots | Wrote the documents from the captures and the zip; could not make raw captures or see the recording |
| Commits and releases | Reviewed, committed and pushed every chunk; decides the tag | Nothing (Claude has no access to the repository) |

## What the developer should be ready to explain (viva)
The architecture and why Option A (DR-008); how a request moves from PENDING to CLOSED; how the signed notifier message and the retry and manual-handling path work; what each test layer checks (PHPUnit, Postman, k6, UAT); what the session did and did not show; the known limits and the conditions before any real use.

## Third-party code and acknowledgement
Flutter and Laravel scaffolds, the Laravel framework and its Composer packages, the Flutter `http` package, Caddy, k6, the Redocly CLI (lint only). AI assistance: Claude (Anthropic), as above. This must also be stated in the root README before submission ([`handover-readme-draft.md`](handover-readme-draft.md), section 14).

## Left for the developer
Check each row above, change any that is not true in his own words, and add a line about what he found hardest. Claude cannot know that. The optional reflection on the four module prompts is not written (it is first in the cut order of the plan).
