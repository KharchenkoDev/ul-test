<?php

namespace App\Providers;

use App\Services\IdempotencyService;
use App\Services\Notification\NotificationProviderResolver;
use App\Services\WebhookService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationProviderResolver::class);
        $this->app->singleton(IdempotencyService::class);
        $this->app->singleton(WebhookService::class);
    }

    public function boot(): void {}
}
