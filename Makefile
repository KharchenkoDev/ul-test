ifneq (,$(wildcard ./.env))
    include .env
    export
endif

NGINX_PORT ?= 8080

DOCKER_COMP = docker compose

PHP_EXEC   = $(DOCKER_COMP) exec php
WORKER_EXEC= $(DOCKER_COMP) exec worker

PHP      = $(PHP_EXEC) php
COMPOSER = $(PHP_EXEC) composer
ARTISAN  = $(PHP) artisan

.DEFAULT_GOAL := help
.PHONY: help install build up down stop start restart shell shell-worker logs ps permissions \
        composer composer-install artisan key-generate migrate migrate-fresh \
        test prod-up prod-down

## —— 🐘 Laravel Docker Makefile ———————————————————————————————————————————————
help: ## Вывести эту справку
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9][a-zA-Z0-9_-]+:.*?## / { printf "\033[36m%-20s\033[0m %s\n", $$1, $$2 } /^## .*$$/ { printf "\033[33m%s\033[0m\n", substr($$0, 4) }' $(MAKEFILE_LIST)

## —— Setup ————————————————————————————————————————————————————————————————————
install: ## Полная установка для dev-режима; безопасно повторять на уже инициализированном проекте
	@test -f .env     || (cp .env.example .env         && echo "Создан .env (infrastructure)")
	@test -f src/.env || (cp src/.env.example src/.env && echo "Создан src/.env (application)")
	@grep -q "^UID=" .env || echo "UID=$$(id -u)" >> .env
	@grep -q "^GID=" .env || echo "GID=$$(id -g)" >> .env
	@$(DOCKER_COMP) build
	@$(DOCKER_COMP) up --detach --wait
	@$(MAKE) permissions --no-print-directory
	@$(COMPOSER) install --no-interaction
	@grep -q "^APP_KEY=base64:" src/.env 2>/dev/null || $(ARTISAN) key:generate
	@$(ARTISAN) migrate --no-interaction

## —— Docker ———————————————————————————————————————————————————————————————————
build: ## Собрать Docker-образы
	@$(DOCKER_COMP) build

up: ## Запустить контейнеры в фоне
	@$(DOCKER_COMP) up --detach
	@echo "App:        http://localhost:$(NGINX_PORT)"
	@echo "RabbitMQ:   http://localhost:$(RABBITMQ_MANAGEMENT_PORT:-15672)"

down: ## Остановить и удалить контейнеры
	@$(DOCKER_COMP) down --remove-orphans

stop: ## Остановить контейнеры (без удаления)
	@$(DOCKER_COMP) stop

start: ## Запустить остановленные контейнеры
	@$(DOCKER_COMP) start

restart: stop start ## Перезапустить контейнеры

shell: ## Открыть sh в PHP-контейнере
	@$(PHP_EXEC) sh

shell-worker: ## Открыть sh в worker-контейнере
	@$(WORKER_EXEC) sh

logs: ## Следить за логами контейнеров
	@$(DOCKER_COMP) logs -f

ps: ## Статус контейнеров
	@$(DOCKER_COMP) ps

permissions: ## Выставить права на storage/ и bootstrap/cache/
	@$(PHP_EXEC) chmod -R 777 storage bootstrap/cache

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Запустить Composer; передай команду через c=, например: make composer c='require laravel/sanctum'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

composer-install: ## Установить зависимости Composer
	@$(COMPOSER) install

## —— Laravel ——————————————————————————————————————————————————————————————————
artisan: ## Запустить artisan; передай команду через c=, например: make artisan c='make:controller Foo'
	@$(eval c ?=)
	@$(ARTISAN) $(c)

key-generate: ## Сгенерировать APP_KEY
	@$(ARTISAN) key:generate

migrate: ## Выполнить миграции
	@$(ARTISAN) migrate

migrate-fresh: ## Сбросить БД и накатить миграции с сидерами
	@$(ARTISAN) migrate:fresh --seed

## —— Testing ——————————————————————————————————————————————————————————————————
test: ## Запустить все тесты
	@$(ARTISAN) test

## —— Production ———————————————————————————————————————————————————————————————
prod-up: ## Запустить в production-режиме (target=prod, --build)
	@$(DOCKER_COMP) -f compose.yaml -f compose.prod.yaml up --detach --build

prod-down: ## Остановить production-контейнеры
	@$(DOCKER_COMP) -f compose.yaml -f compose.prod.yaml down
