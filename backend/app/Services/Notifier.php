<?php

namespace App\Services;

use App\Models\ReminderNotification;

/** The external dependency. Throws TransientNotifierException or PermanentNotifierException on failure. */
interface Notifier
{
    public function send(ReminderNotification $notification): void;
}
