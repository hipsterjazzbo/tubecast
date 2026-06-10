.PHONY: setup up dev down logs shell migrate reset test test-e2e

# Arch often ships `docker` as a Podman shim without `compose` — use podman compose instead.
# Override for real Docker: make up COMPOSE="docker compose"
COMPOSE ?= podman compose
COMPOSE_FILES = -f docker-compose.yml
DEV_COMPOSE_FILES = -f docker-compose.yml -f docker-compose.dev.yml

.env:
	@test -f .env || cp .env.example .env
	@echo "Created .env from .env.example — edit as needed."

setup: .env

up: .env
	$(COMPOSE) $(COMPOSE_FILES) up -d --build

dev: .env
	$(COMPOSE) $(DEV_COMPOSE_FILES) up -d --build

logs:
	$(COMPOSE) $(COMPOSE_FILES) logs -f tubecast

shell:
	$(COMPOSE) $(COMPOSE_FILES) exec tubecast sh

migrate:
	$(COMPOSE) $(COMPOSE_FILES) exec tubecast php tempest migrate:up --force

down:
	$(COMPOSE) $(COMPOSE_FILES) down

reset:
	$(COMPOSE) $(COMPOSE_FILES) down -v

test:
	$(COMPOSE) $(DEV_COMPOSE_FILES) exec -T tubecast composer test

test-e2e:
	$(COMPOSE) $(DEV_COMPOSE_FILES) exec -T tubecast composer test:e2e
