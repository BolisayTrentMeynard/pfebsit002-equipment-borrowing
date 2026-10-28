# PFEBSIT 002 Finals: Week 13 (Equipment Borrowing Reminder System)

Individual finals project for PFEBSIT 002 (Weeks 13-18), built with a Flutter Web app and a Laravel backend. This repository is the **Week 13** snapshot (Advanced Automation and Scripting). Each week has its own repository.

- **Student:** Bolisay Trent Meynard, BSIT 3A (solo)
- **Selected project:** #5 Equipment Borrowing Reminder System (backups: #25 Inventory Reorder Alert System, #21 Certificate Generation Queue)
- **Backend:** Laravel (PHP)

## Project proposal

This project automates the borrowing cycle for shared school equipment in a small, controlled office setting. A borrower submits a borrow request, an approver accepts or rejects it, and staff release the item with a recorded due date. A scheduled job then checks due dates, sends reminders before the deadline, and escalates overdue items to the approver, with a limit on repeated notifications so nobody is spammed. When the item comes back, staff record the return and inspection result, and the request is closed. A daily summary lists the items currently in borrowers' custody. The backend (Laravel) validates every request, requires login, restricts actions by role (borrower, approver, staff) and logs every state change. Notifications use bounded retries, and a notification that keeps failing goes to a human for manual handling instead of retrying forever. The Flutter Web app covers the request, approval, release, return and overdue screens with validation and loading, empty, error and unauthorized states. All data is fake test data (sample equipment and made-up names), with no real personal information.

## Status (Week 13)

- Setup done: Flutter Web app and Laravel backend scaffolds both run locally.
- Project features are **not built yet**. Week 13 design documents and the automation work come next.

## Repository layout

```
app/        Flutter Web app
backend/    Laravel API
evidence/   Evidence index and small evidence files (large files are linked from Google Drive)
README.md
```

## How to run

You need Flutter (with Chrome), PHP 8.2 or newer, Composer and Git. These steps follow the standard Laravel and Flutter setup and have not yet been tested from a fresh clone.

Backend:

```
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The API runs at http://127.0.0.1:8000.

Flutter Web app (in a second terminal):

```
cd app
flutter pub get
flutter run -d chrome
```

## Configuration and secrets

The real `.env` file is never committed. Use `backend/.env.example` as the template. No API keys, passwords, tokens or real personal data belong in this repository, including screenshots and logs. All data is fake test data.

## Evidence

Evidence is listed in `evidence/INDEX.md`. Large files (recordings) are kept on Google Drive and linked from the index.

## AI assistance and third-party code

I used Claude (Anthropic) as an AI assistant for planning, setup guidance and drafting. The project starts from the default Flutter and Laravel scaffolds. I will list any other third-party packages and code here as they are added, and I can explain and defend my own contribution.
