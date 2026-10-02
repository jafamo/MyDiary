.PHONY: up down build restart logs ps sh cs-check cs-fix phpstan test test-coverage composer-install migrate migration-diff console cache-clear deploy audio-retry embeddings-backfill sonar

COMPOSE = docker compose --env-file .env
SONAR_HOST_URL ?= https://sonarqube.jfarinos.keenetic.pro

# Entorno de esta máquina según APP_ENV de .env (y .env.local, que lo sobrescribe, como en Symfony).
# El mismo Makefile se usa en local y en producción: los comandos de desarrollo se bloquean con
# APP_ENV=prod y `deploy` se bloquea fuera de producción.
APP_ENV := $(shell sed -n "s/^APP_ENV=[\"']*\([a-z]*\).*/\1/p" .env .env.local 2>/dev/null | tail -n 1)
LOCAL_ONLY = @[ "$(APP_ENV)" != prod ] || { echo "Error: 'make $@' solo se ejecuta en local (APP_ENV=$(APP_ENV))."; exit 1; }
PROD_ONLY = @[ "$(APP_ENV)" = prod ] || { echo "Error: 'make $@' solo se ejecuta en producción (APP_ENV=$(APP_ENV))."; exit 1; }

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
	$(LOCAL_ONLY)
	$(COMPOSE) exec diary-php vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(LOCAL_ONLY)
	$(COMPOSE) exec diary-php vendor/bin/php-cs-fixer fix

phpstan:
	$(LOCAL_ONLY)
	$(COMPOSE) exec diary-php vendor/bin/phpstan analyse --memory-limit=1G $(ARGS)

test:
	$(LOCAL_ONLY)
	$(COMPOSE) exec diary-php bin/phpunit $(ARGS)

test-coverage:
	$(LOCAL_ONLY)
	$(COMPOSE) exec diary-php bin/phpunit --coverage-text $(ARGS)

sonar:
	$(LOCAL_ONLY)
	@test -n "$(SONAR_TOKEN)" || { echo "Error: define SONAR_TOKEN (p. ej. SONAR_TOKEN=xxx make sonar)"; exit 1; }
	docker run --rm --user $$(id -u):$$(id -g) \
		-e SONAR_HOST_URL=$(SONAR_HOST_URL) \
		-e SONAR_TOKEN \
		-v "$(CURDIR):/usr/src" \
		sonarsource/sonar-scanner-cli

composer-install:
	$(COMPOSE) exec diary-php composer install

migrate:
	$(COMPOSE) exec diary-php php bin/console doctrine:migrations:migrate --no-interaction

migration-diff:
	$(LOCAL_ONLY)
	$(COMPOSE) exec diary-php php bin/console doctrine:migrations:diff

console:
	$(COMPOSE) exec diary-php php bin/console $(ARGS)

audio-retry:
	$(COMPOSE) exec diary-php php bin/console app:audio:retry-transcription $(ARGS)

embeddings-backfill:
	$(COMPOSE) exec diary-php php bin/console app:transcription:backfill-embeddings
	$(COMPOSE) exec diary-php php bin/console app:daily-summary:backfill-embeddings

cache-clear:
	$(COMPOSE) exec diary-php php bin/console cache:clear

deploy:
	$(PROD_ONLY)
	git pull origin main
	$(MAKE) cache-clear
	$(COMPOSE) restart diary-php diary-messenger-worker
