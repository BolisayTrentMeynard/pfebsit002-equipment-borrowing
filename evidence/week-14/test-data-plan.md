# Test Data Plan (Week 14)

All fake. Same seeded users and equipment as Week 13 (`evidence/week-13/test-data-plan.md`).

| Item | Value |
|------|-------|
| Messages | Ids only, for example `notification_id` 0 in the tamper demo, so no real notification is touched |
| Signing key (running demo) | Generated on the developer's PC, only in the private `.env`. Never in screenshots |
| Signing keys (automated tests) | Made-up strings marked `ONLY` in `SignedMessageTest`; they protect nothing |
| Clock cases | Timestamps 10 minutes in the past (demo) and 301 seconds past or future (tests) |
| Reminders for AC-22 and AC-23 | `php artisan db:seed --class=DemoScenarioSeeder` (3 released items due in 10 hours). With `signed_http` the Week 13 failure switches (`NOTIFIER_*_TAGS`) do not apply, so failure is made by stopping Caddy |
| Capture hygiene | `.env` closed; no key or `Authorization` header value visible; address bar and tokens checked |
