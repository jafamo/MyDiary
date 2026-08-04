.PHONY: up down build restart logs ps sh

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
