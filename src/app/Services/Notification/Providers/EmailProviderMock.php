<?php

namespace App\Services\Notification\Providers;

use App\Contracts\NotificationProviderInterface;
use App\Exceptions\ProviderPermanentException;
use App\Exceptions\ProviderUnavailableException;

class EmailProviderMock implements NotificationProviderInterface
{
    public function send(string $recipient, string $message): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new ProviderPermanentException("Email: неверный адрес «{$recipient}»");
        }

        if ($this->shouldFail()) {
            throw new ProviderUnavailableException('Email: шлюз временно недоступен');
        }
    }

    private function shouldFail(): bool
    {
        return random_int(1, 100) <= (int) (config('notification.mock_fail_rate', 0.2) * 100);
    }
}
