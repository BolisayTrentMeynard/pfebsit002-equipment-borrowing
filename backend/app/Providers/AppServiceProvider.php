<?php

namespace App\Providers;

use App\Services\FakeNotifier;
use App\Services\Notifier;
use App\Services\SignedHttpNotifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The one place that decides which notifier is used. 'fake' (default) only logs; 'signed_http'
        // sends a signed message over HTTPS (Week 14).
        $this->app->bind(Notifier::class, fn ($app) => config('notifier.driver') === 'signed_http'
            ? $app->make(SignedHttpNotifier::class)
            : $app->make(FakeNotifier::class));
    }

    public function boot(): void
    {
        //
    }
}
