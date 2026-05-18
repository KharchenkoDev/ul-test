<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\ProviderPermanentException;
use App\Models\Notification;
use App\Services\Notification\NotificationProviderResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Задержки между попытками: 30 с → 2 мин → 10 мин */
    public array $backoff = [30, 120, 600];

    /** Приоритет AMQP-сообщения: читается кастомным драйвером при публикации */
    public int $priority;

    public function __construct(private readonly Notification $notification)
    {
        $this->onQueue('notifications');
        $this->priority = $notification->type->priority();
    }

    public function handle(NotificationProviderResolver $resolver): void
    {
        // Exactly-once: если статус уже не queued — пропускаем (защита от повторной обработки)
        $this->notification->refresh();
        if ($this->notification->status !== NotificationStatus::Queued) {
            return;
        }

        try {
            $provider = $resolver->resolve($this->notification->channel);
            $provider->send($this->notification->subscriber_id, $this->notification->message);
        } catch (ProviderPermanentException $e) {
            // Постоянный сбой — незачем повторять, сразу отклоняем
            $this->notification->markAsRejected($e->getMessage());
            $this->fail($e);
            return;
        }

        // ProviderUnavailableException не перехватываем — Laravel запустит retry
        $this->notification->markAsSent();
    }

    public function failed(Throwable $exception): void
    {
        // Вызывается после исчерпания всех попыток
        if ($this->notification->status === NotificationStatus::Queued) {
            $this->notification->markAsRejected($exception->getMessage());
        }
    }
}
