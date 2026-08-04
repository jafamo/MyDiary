.PHONY: up down build restart logs ps sh cs-check cs-fix

COMPOSE = docker compose --env-file .env

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

restart: down up

logs:
	$(COMPOSE) logs -f $(SERVICE)

ps:
	$(COMPOSE) ps

sh:
	$(COMPOSE) exec diary-php sh

cs-check:
	$(COMPOSE) exec diary-php vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(COMPOSE) exec diary-php vendor/bin/php-cs-fixer fix
