<?php

namespace App\Providers;

use App\Services\FakeNotifier;
use App\Services\Notifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The one place that decides which notifier is used. Swap FakeNotifier for a real one later.
        $this->app->bind(Notifier::class, FakeNotifier::class);
    }

    public function boot(): void
    {
        //
    }
}
