<?php

// Settings for the load-test seeder (Week 16). Nothing here is secret except the password, which is
// read from the environment of the shell that runs the seeder and is never written to a file.
return [
    'password' => env('LOAD_TEST_PASSWORD'),          // one password for all fake load-test accounts, 12+ characters
    'borrowers' => (int) env('LOAD_TEST_BORROWERS', 30),
    'items' => (int) env('LOAD_TEST_ITEMS', 50),
    'volume_requests' => (int) env('LOAD_TEST_VOLUME_REQUESTS', 0), // extra CLOSED history rows for the volume run, max 5000
];
