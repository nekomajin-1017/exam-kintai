SHELL := /bin/bash

SAIL := ./vendor/bin/sail
COMPOSER_IMAGE := composer:2

.PHONY: help init up down restart logs ps shell composer-install env prepare key wait-db migrate fresh test pint doctor reset

help:
	@echo "Available targets:"
	@echo "  make doctor    - 前提チェック（Docker/ポート）"
	@echo "  make up        - 初回セットアップ込みで起動"
	@echo "  make init      - up + key generate + migrate"
	@echo "  make reset     - コンテナ/volumeを完全削除"
	@echo "  make down      - 停止"
	@echo "  make restart   - 再起動"
	@echo "  make logs      - ログ表示"
	@echo "  make ps        - コンテナ状態"
	@echo "  make shell     - laravel.test にシェル接続"
	@echo "  make test      - phpunit 実行"
	@echo "  make pint      - pint 実行"

doctor:
	@command -v docker >/dev/null 2>&1 || (echo "docker が見つかりません"; exit 1)
	@docker info >/dev/null 2>&1 || (echo "Docker daemon に接続できません"; exit 1)
	@if command -v lsof >/dev/null 2>&1; then \
		app_port=$$(grep -E '^APP_PORT=' .env 2>/dev/null | tail -n 1 | cut -d= -f2); \
		if [ -z "$$app_port" ]; then app_port=$$(grep -E '^APP_PORT=' .env.example 2>/dev/null | tail -n 1 | cut -d= -f2); fi; \
		if [ -z "$$app_port" ]; then app_port=80; fi; \
		pma_port=$$(grep -E '^FORWARD_PHPMYADMIN_PORT=' .env 2>/dev/null | tail -n 1 | cut -d= -f2); \
		if [ -z "$$pma_port" ]; then pma_port=$$(grep -E '^FORWARD_PHPMYADMIN_PORT=' .env.example 2>/dev/null | tail -n 1 | cut -d= -f2); fi; \
		if [ -z "$$pma_port" ]; then pma_port=8080; fi; \
		for p in $$app_port $$pma_port; do \
			if lsof -iTCP:$$p -sTCP:LISTEN -Pn >/dev/null 2>&1; then \
				echo "port $$p は既に使用中です"; \
				exit 1; \
			fi; \
		done; \
	fi
	@echo "doctor ok"

env:
	@if [ ! -f .env ]; then cp .env.example .env; fi

composer-install:
	@if [ ! -f vendor/autoload.php ]; then \
		if command -v composer >/dev/null 2>&1; then \
			composer install; \
		else \
			docker run --rm -u "$$(id -u):$$(id -g)" -v "$$(pwd):/app" -w /app $(COMPOSER_IMAGE) composer install --ignore-platform-reqs; \
		fi \
	fi

prepare: env composer-install

up: doctor prepare
	$(SAIL) up -d --build
	$(MAKE) fresh

down:
	@if [ -x $(SAIL) ]; then $(SAIL) down; fi

reset:
	@if [ -x $(SAIL) ]; then $(SAIL) down -v --remove-orphans; else docker compose -f compose.yaml down -v --remove-orphans; fi

restart: down up

logs:
	$(SAIL) logs -f

ps:
	$(SAIL) ps

shell:
	$(SAIL) shell

key:
	@if ! grep -q '^APP_KEY=base64:' .env; then $(SAIL) artisan key:generate; fi

wait-db:
	@$(SAIL) exec -T mysql sh -lc 'until mysqladmin ping -h127.0.0.1 -uroot -p"$$MYSQL_ROOT_PASSWORD" --silent; do echo "waiting for mysql..."; sleep 2; done'

migrate:
	$(SAIL) artisan migrate

fresh:
	$(SAIL) artisan migrate:fresh --seed

test:
	$(SAIL) test

pint:
	$(SAIL) pint

init: up key wait-db migrate
