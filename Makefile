.PHONY: setup up dev down logs shell migrate reset test test-e2e

# Arch often ships `docker` as a Podman shim without `compose` — use podman compose instead.
# Override for real Docker: make up COMPOSE="docker compose"
COMPOSE ?= podman compose
COMPOSE_FILES = -f docker-compose.yml -f docker-compose.build.yml
DEV_COMPOSE_FILES = -f docker-compose.yml -f docker-compose.build.yml -f docker-compose.dev.yml

.env:
	@test -f .env || cp .env.example .env
	@echo "Created .env from .env.example."
	@if grep -q '^ADMIN_PASSWORD=$$' .env 2>/dev/null; then \
		printf 'Admin username [%s]: ' "$$(grep '^ADMIN_USERNAME=' .env | cut -d= -f2-)"; \
		read -r admin_user; \
		admin_user=$${admin_user:-$$(grep '^ADMIN_USERNAME=' .env | cut -d= -f2-)}; \
		printf 'Admin password: '; \
		stty -echo; read -r admin_pass; stty echo; echo; \
		if [ -z "$$admin_pass" ]; then \
			echo "ADMIN_PASSWORD is required." >&2; \
			exit 1; \
		fi; \
		sed -i "s/^ADMIN_USERNAME=.*/ADMIN_USERNAME=$${admin_user}/" .env; \
		sed -i "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=$${admin_pass}|" .env; \
		echo "Admin credentials saved to .env."; \
	fi

setup: .env

up:
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

assets:
	npm ci && npm run build
