<?php

// Settings for signed notifier messages (Week 14). The signing key is a SECRET: it lives in .env only,
// never in code, never in the repo, never in logs.
return [
    // 'fake' = Week 13 behaviour (log line only). 'signed_http' = send a signed message over HTTPS.
    'driver' => env('NOTIFIER_DRIVER', 'fake'),

    // Where the sender posts. In local dev: https://localhost:8443/api/notifier/receive (Caddy in front of artisan serve).
    'url' => env('NOTIFIER_URL', 'https://localhost:8443/api/notifier/receive'),

    // Shared secret for HMAC-SHA256, at least 32 characters. Generate with: php artisan notifier:generate-key
    'signing_key' => env('NOTIFIER_SIGNING_KEY'),

    // Optional old key, accepted by the receiver only while a new key is rolled out (key rotation).
    'previous_signing_key' => env('NOTIFIER_SIGNING_KEY_PREVIOUS'),

    // A message older (or newer) than this many seconds is rejected as expired.
    'tolerance_seconds' => (int) env('NOTIFIER_TOLERANCE_SECONDS', 300),

    'timeout_seconds' => 5,

    // Path to the local CA certificate that Caddy created, so PHP trusts https://localhost:8443.
    // Windows example: C:\Users\<you>\AppData\Roaming\Caddy\pki\authorities\local\root.crt
    // Leave empty to use the normal system certificates. Certificate checking is never switched off.
    'ca_bundle' => env('NOTIFIER_CA_BUNDLE'),

    'min_key_length' => 32,
];
