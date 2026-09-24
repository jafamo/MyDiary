me sal e.PHONY: up down build restart logs ps sh cs-check cs-fix test test-coverage composer-install migrate migration-diff console cache-clear deploy audio-retry embeddings-backfill sonar

COMPOSE = docker compose --env-file .env
SONAR_HOST_URL ?= https://sonarqube.jfarinos.keenetic.pro

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

sonar:
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
	git pull origin main
	$(MAKE) cache-clear
	$(COMPOSE) restart diary-php diary-messenger-worker
