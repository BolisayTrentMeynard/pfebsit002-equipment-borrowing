# Week 15 Test Data Plan

All data is fake. Nothing real is used or needed.

| Data | Source | Notes |
|------|--------|-------|
| Users `borrower1@example.test`, `borrower2@example.test`, `approver@example.test`, `staff@example.test` | `DatabaseSeeder` | Dev-only accounts. The password is typed by the developer into a Postman variable at run time (a "secret" variable, current value only). It is never put in the collection file, the environment file or a screenshot |
| Six equipment items EQ-001 to EQ-006 | `DatabaseSeeder` | The boundary and normal cases use different items because a borrower cannot hold two open requests for one item |
| Demo reminders | `DemoScenarioSeeder` | Used for the Caddy-stopped and reliability runs, same as Week 14 |
| Reset between runs | `php artisan migrate:fresh --seed` | Run before the baseline and before the rerun so both start from the same data. The closed-history screenshot must be taken before a reset |

## Postman files in the repo (added in chunk 1)
`evidence/week-15/postman/` holds the collection JSON and an environment file with placeholders only (`baseUrl`, empty `password`, empty `token`). No token, key or password value is saved in either file. Run results are not exported because they can contain tokens.

## Capture hygiene
Keep `.env` closed. Keep the Authorization header and the login response body out of screenshots (the collection stores the token in a variable and never prints it). Check the address bar and the console before saving a screenshot.
