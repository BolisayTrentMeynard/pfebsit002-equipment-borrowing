<?php

// Settings for the UAT seeder (Week 17). Nothing here is secret except the password, which is read from
// the environment of the shell that runs the seeder and is never written to a file in the repo.
return [
    'password' => env('UAT_PASSWORD'), // one password for the four fake UAT accounts, 12+ characters
];
