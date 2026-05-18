<?php

namespace App\Services;

use App\Models\Notification;

class WebhookService
{
    public function processDelivery(string $notificationId, string $status, string $reason = ''): void
    {
        $notification = Notification::findOrFail($notificationId);

        match ($status) {
            'delivered' => $notification->markAsDelivered(),
            'rejected'  => $notification->markAsRejected($reason),
        };
    }
}
