<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebhookDeliveryRequest;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function __construct(private readonly WebhookService $service) {}

    /**
     * Webhook подтверждения статуса доставки.
     *
     * Вызывается SMS/Email-провайдером после финальной обработки сообщения.
     * Переводит уведомление в статус `delivered` или `rejected`.
     */
    public function delivery(WebhookDeliveryRequest $request): JsonResponse
    {
        $this->service->processDelivery(
            notificationId: $request->input('notification_id'),
            status:         $request->input('status'),
            reason:         $request->input('reason', ''),
        );

        return response()->json(['ok' => true]);
    }
}
