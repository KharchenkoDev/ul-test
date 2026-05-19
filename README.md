# Notification Service

Микросервис массовой рассылки SMS/Email уведомлений с приоритизацией, дедубликацией и гарантией доставки.

## Стек

| Слой | Технология |
|------|-----------|
| Язык / фреймворк | PHP 8.3 / Laravel 13 |
| База данных | PostgreSQL 16 |
| Брокер сообщений | RabbitMQ 3.13 |
| Кэш / идемпотентность | Redis 7.2 (phpredis) |
| Контейнеризация | Docker (multistage build) |

## Архитектура

```
POST /api/notifications/send
        │
        ▼
  IdempotencyService (Redis / Cache, TTL 24h)
        │ нет кэша
        ▼
  Notification::firstOrCreate (PostgreSQL)
        │
        ▼
  SendNotificationJob → RabbitMQ (x-max-priority=10)
        │
        ▼
  Worker: EmailProviderMock / SmsProviderMock
        │
        ▼
  Notification.status: queued → sent
        │
        ▼ (вебхук)
  POST /api/webhooks/delivery → delivered
```

**Приоритеты:** `transactional` → 10, `marketing` → 1. RabbitMQ выдаёт воркеру сообщения в порядке убывания приоритета.

**Идемпотентность (два уровня):**
1. Redis: batch-ключ → кешированный ответ на 24 часа
2. БД: `idempotency_key = "{batch_key}:{subscriber_id}"` — `UNIQUE` constraint

**Exactly-once:** перед отправкой джоб делает `$notification->refresh()` и проверяет `status === queued`. Если уже обработано — пропускает.

**Retry:** 3 попытки, backoff 30 с / 2 мин / 10 мин. `ProviderPermanentException` → немедленный `rejected` без retry.

## Быстрый старт

**Требования:** Docker >= 24, Docker Compose v2

```bash
# 1. Клонировать репозиторий
git clone <repo-url> && cd ul-test

# 2. Поднять проект (build + migrate + app key)
make install

# 3. Проверить работу
curl http://localhost:8080/up
```

Готово — сервис доступен на `http://localhost:8080`.

> Если порты заняты — можно изменить `.env`.

## API

### POST /api/notifications/send

Отправить уведомления нескольким подписчикам.

**Request:**
```json
{
  "channel": "email",
  "type": "transactional",
  "message": "Ваш заказ отправлен",
  "subscriber_ids": ["user@example.com", "admin@example.com"],
  "idempotency_key": "order-123-notify"
}
```

- `channel`: `sms` | `email`
- `type`: `transactional` | `marketing`
- `subscriber_ids`: массив до 1000 получателей
- `idempotency_key`: уникальный ключ запроса

**Response `202 Accepted`:**
```json
{
  "notifications": [
    {"id": "uuid", "subscriber_id": "user@example.com", "status": "queued"},
    {"id": "uuid", "subscriber_id": "admin@example.com", "status": "queued"}
  ]
}
```

---

### GET /api/notifications/subscribers/{subscriber_id}

История уведомлений для подписчика (по убыванию даты).

**Response `200 OK`:**
```json
{
  "data": [
    {
      "id": "uuid",
      "channel": "email",
      "type": "transactional",
      "status": "delivered",
      "message": "Ваш заказ отправлен",
      "error_message": null,
      "sent_at": "2026-05-18T12:00:00Z",
      "delivered_at": "2026-05-18T12:01:00Z",
      "created_at": "2026-05-18T11:59:00Z"
    }
  ]
}
```

---

### POST /api/webhooks/delivery

Подтверждение доставки от провайдера (статус → `delivered`).

**Request:**
```json
{
  "notification_id": "uuid",
  "status": "delivered"
}
```

**Response `200 OK`:** `{"ok": true}`

---

## Статусы уведомления

| Статус | Описание |
|--------|----------|
| `queued` | В очереди RabbitMQ |
| `sent` | Принято провайдером |
| `delivered` | Подтверждено вебхуком |
| `rejected` | Постоянная ошибка или исчерпаны попытки |

## Управление

```bash
make up            # запустить контейнеры
make down          # остановить
make shell         # sh в php-контейнере
make shell-worker  # sh в worker-контейнере
make logs          # tail -f логов
make migrate       # применить миграции
make test          # запустить тесты
```

## Тесты

```bash
make artisan c='test'
# или конкретный тест:
make artisan c='test --filter=NotificationApiTest'
```

Тесты используют SQLite in-memory + sync queue + array cache (внешние сервисы не нужны).

## Конфигурация

| Переменная | По умолчанию | Описание |
|-----------|-------------|---------|
| `NOTIFICATION_MOCK_FAIL_RATE` | `0.2` | Вероятность временного сбоя mock-провайдера (0–1) |
| `RABBITMQ_HOST` | `rabbitmq` | Хост RabbitMQ |
| `RABBITMQ_QUEUE` | `notifications` | Название очереди |
| `REDIS_HOST` | `redis` | Хост Redis |

RabbitMQ Management UI: [http://localhost:15672](http://localhost:15672) (guest/guest)

## Prod

```bash
make prod-up    # -f compose.yaml -f compose.prod.yaml up -d
make prod-down
```
