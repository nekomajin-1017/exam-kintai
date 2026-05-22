SHELL := /bin/zsh

SAIL := ./vendor/bin/sail
COMPOSER_IMAGE := laravelsail/php85-composer:latest

.PHONY: setup env composer-install up key migrate seed down

setup: env composer-install up key migrate

env:
	@if [ ! -f .env ]; then cp .env.example .env; fi

composer-install:
	docker run --rm \
		-u "$$(id -u):$$(id -g)" \
		-v "$$(pwd):/var/www/html" \
		-w /var/www/html \
		$(COMPOSER_IMAGE) \
		composer install --ignore-platform-reqs

up:
	$(SAIL) up -d --build

key:
	$(SAIL) artisan key:generate

migrate:
	$(SAIL) artisan migrate:fresh --seed

seed:
	$(SAIL) artisan db:seed

down:
	$(SAIL) down
