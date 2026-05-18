<?php

namespace App\Services\Notification\Providers;

use App\Contracts\NotificationProviderInterface;
use App\Exceptions\ProviderPermanentException;
use App\Exceptions\ProviderUnavailableException;

class SmsProviderMock implements NotificationProviderInterface
{
    public function send(string $recipient, string $message): void
    {
        if (str_contains($recipient, 'invalid')) {
            throw new ProviderPermanentException("SMS: неверный номер телефона «{$recipient}»");
        }

        if ($this->shouldFail()) {
            throw new ProviderUnavailableException('SMS: шлюз временно недоступен');
        }
    }

    private function shouldFail(): bool
    {
        return random_int(1, 100) <= (int) (config('notification.mock_fail_rate', 0.2) * 100);
    }
}
