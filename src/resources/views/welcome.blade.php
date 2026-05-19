<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notification Service</title>
    <link rel="stylesheet" href="/css/welcome.css">
</head>
<body>
    <div class="card">
        <span class="badge">Микросервис</span>

        <h1>Notification Service</h1>

        <p class="description">
            Массовая рассылка SMS и Email уведомлений с приоритизацией,
            дедубликацией и гарантией доставки через Transactional Outbox.
        </p>

        <ul class="features">
            <li>Идемпотентная отправка по <code>idempotency_key</code></li>
            <li>Приоритизация: transactional (10) > marketing (1)</li>
            <li>Статусы: queued → sent → delivered / rejected</li>
            <li>Webhook для подтверждения доставки от провайдера</li>
            <li>Transactional Outbox — гарантия постановки в очередь</li>
        </ul>

        <div class="actions">
            <a href="/docs/api" class="btn btn-primary">
                OpenAPI / Swagger UI
            </a>
            <a href="/api/notifications/subscribers/example" class="btn btn-secondary">
                Пример API
            </a>
        </div>

        <div class="stack">
            <span class="tag">Laravel 13</span>
            <span class="tag">PHP 8.3</span>
            <span class="tag">RabbitMQ</span>
            <span class="tag">PostgreSQL</span>
            <span class="tag">Redis</span>
            <span class="tag">Docker</span>
        </div>
    </div>
</body>
</html>
