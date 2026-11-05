# Decision Record 004: Database changed from SQLite to MySQL

Date: 2026-11-05. Project: #5 Equipment Borrowing Reminder System (scope unchanged). Role that accepts the result: instructor (the requirement came from the instructor).

## Context
Week 13 was built and submitted on SQLite (Laravel default, no setup). On 2026-11-05 the instructor said to use MySQL, not SQLite. The developer already has MySQL through XAMPP.

## Options
| Option | For | Against |
|--------|-----|---------|
| A. Stay on SQLite | Nothing to change, already tested | Does not meet the instructor's requirement |
| B. MySQL (MariaDB from XAMPP) | Meets the requirement, already installed, Eloquent makes it a configuration change | Needs MySQL running (XAMPP Control Panel) and two databases; MariaDB behaves slightly differently from SQLite |

## Decision
Option B. The change is on branch `week-14-mysql`, commit `4ef867e`: "Switch database from SQLite to MySQL (instructor requirement)", 5 files, no `.env`.

## What changed
- `backend/.env.example`: MySQL settings, database `equipment_borrowing`, user `root`, empty password (local XAMPP default, fake data only).
- `backend/phpunit.xml`: tests use a separate database, `equipment_borrowing_test`, instead of SQLite in memory.
- `backend/config/database.php`: the fallback default is `mysql`.
- `README.md`: MySQL and the two databases to create.
- The `api_tokens` migration: `expires_at` is now `dateTime`. MariaDB can automatically update the first NOT NULL `timestamp` column, which could change the stored expiry on its own. A `dateTime` column does not do that.
- No SQLite-only code was found in the application.

## Evidence (per the developer, 2026-11-05)
`migrate:fresh --seed` ran, `php artisan test` showed 31 passed (89 assertions) on MySQL, `php artisan serve` ran, and the browser test with the Flutter app worked. After the Week 14 code the suite is 47 passed (127 assertions) on MySQL.

## Consequences and risks
- `main` and the `week-13` tag keep the SQLite version that was submitted, and the Week 13 evidence says SQLite on purpose, because that is what was run then.
- The old `backend/database/database.sqlite` is no longer used (git-ignored, can be deleted).
- The `expires_at` bug shows that a database change can alter behaviour even when the code is the same. Any later database change needs the full test run again.
- Both databases must exist before `migrate` or `php artisan test`. The README says so.
- Limit: the tests ran on MySQL via XAMPP only. Other MySQL or MariaDB versions were not tried.
