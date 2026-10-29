# Decision Record 002: API authentication

## Context
The Flutter Web app needs to log in and call the Laravel API with a role per user. Week 14 adds encryption and keys, so the login design should stay small and easy to explain.

## Decision question
Use the Laravel Sanctum package, or a small token table built in the project?

## Chosen approach
A small `api_tokens` table. Login returns a random 60-character token once. Only its SHA-256 hash is stored, the token expires after 8 hours, and a middleware looks it up on every request. Roles are stored on the user and checked on the server by a `role:` middleware.

## Alternative (not selected)
Laravel Sanctum personal access tokens.
- Advantages: maintained by the Laravel team, more features (abilities, SPA cookies).
- Why not selected now: it needs a package install and extra configuration on the student's machine, and Week 13 needs only simple token login. The custom version is about 60 lines the student can explain fully.

## Consequences
- Good: no new dependency, easy to defend, tokens are never stored in plain text.
- Costs: the student maintains the auth code, with no refresh tokens or per-token abilities. Expired tokens are not cleaned up automatically.
- Review condition: switch to Sanctum if the project needs token abilities or cookie-based login (Week 14/15 hardening).
