# Laravel Docker Dev/Prod Setup

## Быстрый старт (dev)

### 1. Настроить порты (при необходимости)

Если порты по умолчанию заняты, отредактируй `.env` перед запуском:

```dotenv
NGINX_PORT=8080
MYSQL_PORT=3306
```

### 2. Настроить приложение

Создай `src/.env` из примера и задай нужные значения:

```bash
cp src/.env.example src/.env
```

### 3. Запустить установку

```bash
make install
```


## Production

```bash
make prod-up
```

## Требования

- Docker >= 24
- Docker Compose v2
