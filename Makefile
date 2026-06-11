.PHONY: setup setup-lerd setup-docker up dev down logs shell migrate reset test test-e2e

# Arch often ships `docker` as a Podman shim without `compose` — use podman compose instead.
# Override for real Docker: make up COMPOSE="docker compose"
COMPOSE ?= docker compose
COMPOSE_ENV := $(if $(wildcard .env.docker),--env-file .env.docker,)
COMPOSE_FILES = -f docker-compose.yml -f docker-compose.build.yml
DEV_COMPOSE_FILES = -f docker-compose.yml -f docker-compose.build.yml -f docker-compose.dev.yml

# Lerd local development (preferred for working on TubeCast)
setup-lerd:
	@test -f .env || cp .env.example .env
	@echo "Created .env for lerd. Next: lerd env_setup && lerd setup"

setup: setup-lerd

# Optional Docker overrides (production-like local container builds)
setup-docker:
	@test -f .env.docker || cp .env.docker.example .env.docker
	@echo "Created .env.docker. Run: make up"

up:
	$(COMPOSE) $(COMPOSE_ENV) $(COMPOSE_FILES) up -d --build

dev: setup-docker
	$(COMPOSE) $(COMPOSE_ENV) $(DEV_COMPOSE_FILES) up -d --build

logs:
	$(COMPOSE) $(COMPOSE_ENV) $(COMPOSE_FILES) logs -f tubecast

shell:
	$(COMPOSE) $(COMPOSE_ENV) $(COMPOSE_FILES) exec tubecast sh

migrate:
	$(COMPOSE) $(COMPOSE_ENV) $(COMPOSE_FILES) exec tubecast php tempest migrate:up --force

down:
	$(COMPOSE) $(COMPOSE_ENV) $(COMPOSE_FILES) down

reset:
	$(COMPOSE) $(COMPOSE_ENV) $(COMPOSE_FILES) down -v

test:
	$(COMPOSE) $(COMPOSE_ENV) $(DEV_COMPOSE_FILES) exec -T tubecast composer test

test-e2e:
	$(COMPOSE) $(COMPOSE_ENV) $(DEV_COMPOSE_FILES) exec -T tubecast composer test:e2e

assets:
	npm ci && npm run build
