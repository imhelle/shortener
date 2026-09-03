.DEFAULT_GOAL := help
DC := docker compose
# Anything that creates files in ./app runs as www-data (the host uid), not root.
EXEC := $(DC) exec -u www-data php

help: ## List the available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

build: ## Build the images
	$(DC) build

up: ## Start the containers
	$(DC) up -d

down: ## Stop the containers
	$(DC) down

restart: down up ## Restart

logs: ## Follow the logs of every service
	$(DC) logs -f

sh: ## Shell inside the php container
	$(EXEC) sh

composer: ## make composer c="require symfony/uid"
	$(EXEC) composer $(c)

console: ## make console c="cache:clear"
	$(EXEC) php bin/console $(c)

db: ## mysql client inside the container
	$(DC) exec db mysql -u$${MYSQL_USER:-shortener} -p$${MYSQL_PASSWORD:-shortener} $${MYSQL_DATABASE:-shortener}

# Through $(EXEC) rather than directly: PHPUnit writes its cache into
# .phpunit.cache/, and owned by root it would be unusable from PhpStorm.
test: ## Run the tests. make test c="--filter ShortCodeGenerator"
	$(EXEC) php bin/phpunit $(c)

# The checks to run before a commit. Each catches its own class of error:
# --no-check-publish — the project is not a package, it has no name or description;
# lint:container adds the argument type check a normal build never does;
# the prod warmup assembles the container from when@prod branches — an error
# can live only there and never show up in dev.
lint: ## Pre-commit checks: composer, yaml, container, prod build
	$(EXEC) composer validate --strict --no-check-publish
	$(EXEC) php bin/console lint:yaml config
	$(EXEC) php bin/console lint:container
	$(EXEC) sh -c 'APP_ENV=prod php bin/console cache:warmup'

.PHONY: help build up down restart logs sh composer console db test lint
