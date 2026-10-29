<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automation: check due dates every morning, summarise custody every evening.
Schedule::command('reminders:run')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('custody:summary')->dailyAt('17:00');
