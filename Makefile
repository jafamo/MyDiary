.PHONY: up down build restart logs ps sh cs-check cs-fix test test-coverage composer-install migrate migration-diff console

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

test:
	$(COMPOSE) exec diary-php bin/phpunit $(ARGS)

test-coverage:
	$(COMPOSE) exec diary-php bin/phpunit --coverage-text $(ARGS)

composer-install:
	$(COMPOSE) exec diary-php composer install

migrate:
	$(COMPOSE) exec diary-php php bin/console doctrine:migrations:migrate --no-interaction

migration-diff:
	$(COMPOSE) exec diary-php php bin/console doctrine:migrations:diff

console:
	$(COMPOSE) exec diary-php php bin/console $(ARGS)
