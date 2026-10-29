<?php

// Limits and failure injection for the reminder automation. Nothing here is secret.
$list = fn (string $key) => array_values(array_filter(array_map('trim', explode(',', (string) env($key, '')))));

return [
    'max_attempts' => 3,                         // tries per notification, then a human takes over
    'backoff' => [5, 15],                        // seconds to wait before retry 2 and retry 3
    'reminder_window_hours' => 24,               // remind when due within this many hours
    'max_reminders' => 3,                        // per request and recipient
    'max_escalations' => 2,                      // overdue escalations per request and recipient
    'min_interval_hours' => 20,                  // no second notification of the same kind inside this time

    // Failure injection for the fake notifier (demo and tests only). Comma separated asset tags.
    'transient_fail_tags' => $list('NOTIFIER_TRANSIENT_TAGS'), // fails the first 2 attempts, then works
    'always_fail_tags' => $list('NOTIFIER_ALWAYS_FAIL_TAGS'),  // fails every attempt
];
