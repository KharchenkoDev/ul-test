<?php

namespace App\Services\Notification;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;

class NotificationProviderResolver
{
    public function resolve(NotificationChannel $channel): NotificationProviderInterface
    {
        return app($channel->providerClass());
    }
}
