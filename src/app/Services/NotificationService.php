<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    /**
     * @param  string[]  $subscriberIds
     * @return array{notifications: array<array{id: string, subscriber_id: string, status: string}>}
     */
    public function send(
        NotificationChannel $channel,
        NotificationType $type,
        string $message,
        array $subscriberIds,
        string $idempotencyKey,
    ): array {
        return $this->idempotency->remember(
            $idempotencyKey,
            fn () => $this->createAndDispatch($channel, $type, $message, $subscriberIds, $idempotencyKey),
        );
    }

    /** @return array<int, array{id: string, channel: string, type: string, status: string, message: string, error_message: string|null, sent_at: string|null, delivered_at: string|null, created_at: string}> */
    public function forSubscriber(string $subscriberId): array
    {
        return Notification::query()
            ->forSubscriber($subscriberId)
            ->latest()
            ->get(['id', 'channel', 'type', 'status', 'message', 'error_message', 'sent_at', 'delivered_at', 'created_at'])
            ->all();
    }

    private function createAndDispatch(
        NotificationChannel $channel,
        NotificationType $type,
        string $message,
        array $subscriberIds,
        string $idempotencyKey,
    ): array {
        // Outbox Pattern: создаём уведомления и записи outbox в одной транзакции.
        // Даже если процесс упадёт после commit, outbox:process подберёт записи.
        [$notifications, $newIds] = DB::transaction(
            function () use ($channel, $type, $message, $subscriberIds, $idempotencyKey) {
                $all    = [];
                $newIds = [];

                foreach ($subscriberIds as $subscriberId) {
                    $notification = Notification::firstOrCreate(
                        ['idempotency_key' => "{$idempotencyKey}:{$subscriberId}"],
                        [
                            'subscriber_id' => $subscriberId,
                            'channel'       => $channel,
                            'type'          => $type,
                            'message'       => $message,
                            'status'        => 'queued',
                        ],
                    );

                    if ($notification->wasRecentlyCreated) {
                        OutboxMessage::create(['notification_id' => $notification->id]);
                        $newIds[] = $notification->id;
                    }

                    $all[] = $notification;
                }

                return [$all, $newIds];
            }
        );

        // Best-effort немедленный dispatch из outbox.
        // При крэше между транзакцией и здесь — outbox:process восстановит.
        if ($newIds !== []) {
            OutboxMessage::whereIn('notification_id', $newIds)
                ->with('notification')
                ->get()
                ->each(function (OutboxMessage $outbox): void {
                    dispatch(new SendNotificationJob($outbox->notification));
                    $outbox->delete();
                });
        }

        return [
            'notifications' => array_map(
                fn (Notification $n) => [
                    'id'            => $n->id,
                    'subscriber_id' => $n->subscriber_id,
                    'status'        => $n->status->value,
                ],
                $notifications,
            ),
        ];
    }
}
