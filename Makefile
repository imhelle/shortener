.DEFAULT_GOAL := help
DC := docker compose
# Всё, что создаёт файлы в ./app, гоняем от www-data (uid хоста), а не от root.
EXEC := $(DC) exec -u www-data php

help: ## Список команд
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

build: ## Собрать образы
	$(DC) build

up: ## Поднять контейнеры
	$(DC) up -d

down: ## Остановить контейнеры
	$(DC) down

restart: down up ## Перезапустить

logs: ## Логи всех сервисов
	$(DC) logs -f

sh: ## Шелл в php-контейнере
	$(EXEC) sh

composer: ## make composer c="require symfony/uid"
	$(EXEC) composer $(c)

console: ## make console c="cache:clear"
	$(EXEC) php bin/console $(c)

db: ## mysql-клиент внутри контейнера
	$(DC) exec db mysql -u$${MYSQL_USER:-shortener} -p$${MYSQL_PASSWORD:-shortener} $${MYSQL_DATABASE:-shortener}

.PHONY: help build up down restart logs sh composer console db
