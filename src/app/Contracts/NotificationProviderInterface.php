<?php

namespace App\Contracts;

use App\Exceptions\ProviderPermanentException;
use App\Exceptions\ProviderUnavailableException;

interface NotificationProviderInterface
{
    /**
     * @throws ProviderUnavailableException  шлюз временно недоступен — джоб уйдёт на retry
     * @throws ProviderPermanentException    неверный получатель — джоб немедленно отклоняется
     */
    public function send(string $recipient, string $message): void;
}
