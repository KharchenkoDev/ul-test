<?php

namespace App\Http\Controllers;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Http\Requests\SendNotificationsRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    /**
     * Запустить массовую рассылку уведомлений.
     *
     * Создаёт записи уведомлений и ставит их в очередь на отправку.
     * Идемпотентен: повторный запрос с тем же `idempotency_key` вернёт те же UUID без повторной постановки в очередь.
     */
    public function send(SendNotificationsRequest $request): JsonResponse
    {
        $result = $this->service->send(
            channel:        NotificationChannel::from($request->input('channel')),
            type:           NotificationType::from($request->input('type')),
            message:        $request->input('message'),
            subscriberIds:  $request->input('subscriber_ids'),
            idempotencyKey: $request->input('idempotency_key'),
        );

        return response()->json($result, 202);
    }

    /**
     * История уведомлений подписчика.
     *
     * Возвращает все уведомления для указанного `subscriber_id` в порядке убывания даты создания.
     */
    public function forSubscriber(string $subscriberId): JsonResponse
    {
        return response()->json(['data' => $this->service->forSubscriber($subscriberId)]);
    }
}
