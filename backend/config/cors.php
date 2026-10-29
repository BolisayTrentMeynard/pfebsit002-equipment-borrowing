<?php

// Lets the Flutter Web dev server (any localhost port) call the API from the browser.
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [],
    'allowed_origins_patterns' => ['#^http://(localhost|127\.0\.0\.1):\d+$#'],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Correlation-ID'],
    'max_age' => 0,
    'supports_credentials' => false,
];
