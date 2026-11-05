# Decision Record 003: How to protect the notifier message

## Context
The queue worker sends a small message to the notifier receiver for each reminder. The message holds ids and a type only. The module asks for authenticated protection with an established library, keys outside the code, and rejection of altered or replayed messages.

## Decision question
Which protection fits: only HTTPS, HMAC signing, or authenticated encryption (for example AES-256-GCM or libsodium secretbox) of the body?

## Protection objective
Integrity and authenticity: the receiver must know the message came from the project's sender, was not changed, is fresh and was not already used. Secrecy of the body is not needed (ids only, fake data) and HTTPS already hides it on the wire.

## Chosen approach
HTTPS plus HMAC-SHA256 over `v1.<message id>.<unix timestamp>.<raw body>`, sent in three headers (`X-Message-Id`, `X-Message-Timestamp`, `X-Message-Signature`).
- Library: PHP `hash_hmac` and `hash_equals` (constant-time compare). No home-made crypto.
- Freshness: timestamp must be within 300 seconds of the receiver's clock.
- Replay: the message id is stored for 600 seconds (twice the window) with an atomic `Cache::add`; a second use gives 409. The id is stored only after the signature is valid, so strangers cannot fill the store.
- Every retry builds a new id and timestamp, so a legitimate retry is never a replay.
- Key: random 64 hex characters from `php artisan notifier:generate-key`, kept in `.env`; minimum length 32 is enforced; there is no unsigned fallback.

## Alternatives (not selected)
| Option | Advantage | Why not selected |
|--------|-----------|------------------|
| HTTPS only | Simplest | No proof of who sent the message and no replay protection inside the application |
| AES-256-GCM or libsodium secretbox (authenticated encryption) | Gives secrecy and integrity | Secrecy is not needed for ids; more code and key handling to explain. Would be chosen if messages carried personal or secret content |
| Asymmetric signatures (Ed25519) | No shared secret; receiver cannot forge | Sender and receiver are in the same project and trust zone, so a shared key is enough. Better if the receiver were a separate organization |

## Consequences
- Good: small, explainable, standard primitives, tests for altered, expired and replayed messages.
- Costs: shared secret means both sides must keep it safe; clocks must be roughly in sync (300 s); the replay store is a cache, so a cache flush inside the window would allow one replay (accepted risk, see residual-risk register).
- Review condition: switch to authenticated encryption if the message ever carries names, contact details or free text.
