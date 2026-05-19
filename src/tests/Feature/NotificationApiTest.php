<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Интеграционные тесты API уведомлений.
 * QUEUE_CONNECTION=sync (phpunit.xml) — джобы выполняются синхронно.
 * CACHE_STORE=array — идемпотентность без Redis.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['notification.mock_fail_rate' => 0]);
    }

    // Сценарий 1: отправка → queued → обработка → sent → вебхук → delivered
    public function test_full_delivery_flow(): void
    {
        $response = $this->postJson('/api/notifications/send', [
            'channel'         => 'email',
            'type'            => 'transactional',
            'message'         => 'Welcome!',
            'subscriber_ids'  => ['alice@example.com'],
            'idempotency_key' => 'flow-001',
        ]);

        $response->assertStatus(202);
        $id = $response->json('notifications.0.id');
        $this->assertNotNull($id);

        // Sync queue: джоб отработал → статус sent
        $this->assertDatabaseHas('notifications', ['id' => $id, 'status' => 'sent']);

        // Вебхук подтверждает доставку
        $this->postJson('/api/webhooks/delivery', [
            'notification_id' => $id,
            'status'          => 'delivered',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('notifications', ['id' => $id, 'status' => 'delivered']);
        $this->assertNotNull(Notification::find($id)->delivered_at);
    }

    // Сценарий 2: одинаковый idempotency_key → те же UUID, нет новых записей
    public function test_idempotency_prevents_duplicate_notifications(): void
    {
        $payload = [
            'channel'         => 'email',
            'type'            => 'transactional',
            'message'         => 'Hello',
            'subscriber_ids'  => ['bob@example.com'],
            'idempotency_key' => 'idem-001',
        ];

        $first  = $this->postJson('/api/notifications/send', $payload);
        $second = $this->postJson('/api/notifications/send', $payload);

        $first->assertStatus(202);
        $second->assertStatus(202);

        // Одинаковые UUID
        $this->assertEquals(
            $first->json('notifications.0.id'),
            $second->json('notifications.0.id'),
        );

        // Только одна запись в БД
        $this->assertDatabaseCount('notifications', 1);
    }

    // Сценарий 3: постоянная ошибка провайдера → немедленный rejected (без retry)
    public function test_permanent_provider_failure_rejects_notification(): void
    {
        // Невалидный email → EmailProviderMock бросает ProviderPermanentException
        $response = $this->postJson('/api/notifications/send', [
            'channel'         => 'email',
            'type'            => 'marketing',
            'message'         => 'Promo',
            'subscriber_ids'  => ['not-an-email'],
            'idempotency_key' => 'perm-fail-001',
        ]);

        $response->assertStatus(202);
        $id = $response->json('notifications.0.id');

        // Sync queue: постоянная ошибка → rejected без retry
        $this->assertDatabaseHas('notifications', ['id' => $id, 'status' => 'rejected']);
    }

    // Сценарий 4: приоритет — transactional имеет priority 10, marketing 1
    public function test_transactional_notification_has_higher_priority_than_marketing(): void
    {
        $this->postJson('/api/notifications/send', [
            'channel'         => 'email',
            'type'            => 'transactional',
            'message'         => 'Urgent',
            'subscriber_ids'  => ['user@example.com'],
            'idempotency_key' => 'prio-trans-001',
        ])->assertStatus(202);

        $this->postJson('/api/notifications/send', [
            'channel'         => 'email',
            'type'            => 'marketing',
            'message'         => 'Promo',
            'subscriber_ids'  => ['user@example.com'],
            'idempotency_key' => 'prio-mkt-001',
        ])->assertStatus(202);

        $transactional = Notification::where('idempotency_key', 'prio-trans-001:user@example.com')->first();
        $marketing     = Notification::where('idempotency_key', 'prio-mkt-001:user@example.com')->first();

        $this->assertSame(10, $transactional->type->priority());
        $this->assertSame(1, $marketing->type->priority());
        $this->assertGreaterThan($marketing->type->priority(), $transactional->type->priority());
    }

    public function test_subscriber_history_returns_notifications_in_descending_order(): void
    {
        config(['notification.mock_fail_rate' => 0]);

        $this->postJson('/api/notifications/send', [
            'channel'         => 'email',
            'type'            => 'transactional',
            'message'         => 'First',
            'subscriber_ids'  => ['user@example.com'],
            'idempotency_key' => 'history-001',
        ]);

        $this->postJson('/api/notifications/send', [
            'channel'         => 'email',
            'type'            => 'marketing',
            'message'         => 'Second',
            'subscriber_ids'  => ['user@example.com'],
            'idempotency_key' => 'history-002',
        ]);

        $response = $this->getJson('/api/notifications/subscribers/user@example.com');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertGreaterThanOrEqual($data[1]['created_at'], $data[0]['created_at']);
    }

    public function test_validation_rejects_unknown_channel(): void
    {
        $this->postJson('/api/notifications/send', [
            'channel'         => 'telegram',
            'type'            => 'transactional',
            'message'         => 'Hi',
            'subscriber_ids'  => ['user'],
            'idempotency_key' => 'val-001',
        ])->assertUnprocessable();
    }

    // Сценарий 7: провайдер сигнализирует о неудаче через вебхук → rejected
    public function test_provider_webhook_rejects_sent_notification(): void
    {
        $response = $this->postJson('/api/notifications/send', [
            'channel'         => 'sms',
            'type'            => 'transactional',
            'message'         => 'Your code: 1234',
            'subscriber_ids'  => ['79001112233'],
            'idempotency_key' => 'webhook-reject-001',
        ]);

        $response->assertStatus(202);
        $id = $response->json('notifications.0.id');

        // Sync queue: джоб отработал → sent
        $this->assertDatabaseHas('notifications', ['id' => $id, 'status' => 'sent']);

        // Провайдер сообщает о неудоставке через вебхук
        $this->postJson('/api/webhooks/delivery', [
            'notification_id' => $id,
            'status'          => 'rejected',
            'reason'          => 'Subscriber unreachable',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('notifications', [
            'id'            => $id,
            'status'        => 'rejected',
            'error_message' => 'Subscriber unreachable',
        ]);
    }
}
