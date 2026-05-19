<?php

namespace App\Services\Notification;

use App\Contracts\NotificationProviderInterface;
use App\Enums\NotificationChannel;
use App\Services\Notification\Providers\EmailProviderMock;
use App\Services\Notification\Providers\SmsProviderMock;

class NotificationProviderResolver
{
    public function resolve(NotificationChannel $channel): NotificationProviderInterface
    {
        $class = match ($channel) {
            NotificationChannel::Sms   => SmsProviderMock::class,
            NotificationChannel::Email => EmailProviderMock::class,
        };

        return app($class);
    }
}
