# Decision Record 001: How to run the reminder automation

## Context
The system must check due dates automatically, notify people, retry on transient failures and hand permanent failures to a human. It must be small enough for one student to build, test and defend, and run on a laptop (PHP 8.2, SQLite).

## Decision question
Which mechanism should run and protect the reminder job: a scheduled artisan command that dispatches queued jobs with retry settings, or a single scheduled command that does everything in one loop?

## Chosen approach
Laravel scheduler triggers a command once per day. The command finds due items and dispatches one **queued job per notification** (database queue). Each job has its own bounded `tries` (3) and backoff, and a `failed()` handler that marks the notification FAILED_NEEDS_HUMAN.

## Alternative (not selected)
One scheduled command that loops over items and retries inside the loop.
- Advantages: simplest code, no queue worker to run.
- Why rejected: one failing notification can delay or block the others, retry logic is hand-written and harder to test, and there is no built-in failed-job record.

## Consequences
- Good: failures are isolated per item, retry and failure handling are built in and testable, matches the Week 13 "retry then human" requirement.
- Costs: a queue worker (`php artisan queue:work`) must be running during demos, and one more moving part to explain.
- New responsibilities: monitor the failed-jobs table and manual-handling list.

## Evidence (to be added)
Retry demo log, permanent-failure log, test results (evidence round).

## Review condition
Revisit if the queue worker proves unreliable in the demo environment (Week 16 deployment) or if volume grows beyond a small office.
