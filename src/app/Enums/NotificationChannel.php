<?php

namespace App\Enums;

use App\Contracts\NotificationProviderInterface;
use App\Services\Notification\Providers\EmailProviderMock;
use App\Services\Notification\Providers\SmsProviderMock;

enum NotificationChannel: string
{
    case Sms   = 'sms';
    case Email = 'email';

    /** @return class-string<NotificationProviderInterface> */
    public function providerClass(): string
    {
        return match($this) {
            self::Sms   => SmsProviderMock::class,
            self::Email => EmailProviderMock::class,
        };
    }
}
