# Week 16 Test Data Plan

All data is fake. Nothing real is used or needed.

| Data | Source | Notes |
|------|--------|-------|
| Staging users and items | `DatabaseSeeder` (4 users, EQ-001 to EQ-006) on `equipment_staging` | Used for the Postman rerun on staging (AC-47) |
| Load-test borrowers and items | `LoadTestSeeder` (chunk 1): 30 borrowers `loadborrower01@example.test` to `loadborrower30@example.test`, 50 items `LT-001` to `LT-050` | Plus the existing approver and staff accounts. Guarded: refuses to run unless `APP_ENV` is local, staging or testing, and refuses when no password is supplied |
| Password for the fake accounts | Environment variable set by the developer in the PowerShell session at run time | Never written in a command line, script, config, screenshot or the repo. The seed passwords are dev-only and fake; if a seed password has to appear in a file it is named as dev-only (see R9) |
| Volume data | The same seeder with a count option, up to 5,000 request rows | Only for the optional volume run |
| Demo reminders | `DemoScenarioSeeder` | Only for the optional worker-stopped test |
| Reset between runs | `migrate:fresh` on `equipment_staging` followed by the seeders | Run before every counted run so all runs start from the same data. Reset only the staging database, never `equipment_borrowing` by mistake: the release script prints the database name first |
| Backup files | `mysqldump` output of `equipment_staging` | Kept outside the repo (git-ignored folder or Downloads). Deleted after the rehearsal. Scanned for secrets before anything is shared |

## Files that go in the repo
k6 scripts, the PowerShell sampling script, the release script, `.env.staging.example` (placeholders only) and the documents. Nothing that holds a token, a key or a password. k6 result JSON and the sampling CSV are summarised in the results sheet; raw k6 output files stay on the developer's PC or Drive after a check, because a verbose k6 output can echo request headers.

## Capture hygiene
Keep `.env` and `.env.staging` closed. Keep the Authorization header and login response bodies out of screenshots; the k6 script never prints a token. Do not run k6 with `--http-debug`. Check the terminal title, the address bar and any other open tabs before saving a screenshot. Do not export Postman run results.
