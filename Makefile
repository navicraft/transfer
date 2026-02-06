.PHONY: help build up down restart logs shell composer-install migrate test test-unit test-integration create-accounts clean

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Build Docker containers
	docker-compose build

up: ## Start Docker containers
	docker-compose up -d

down: ## Stop Docker containers
	docker-compose down

restart: down up ## Restart Docker containers

logs: ## View Docker logs
	docker-compose logs -f

shell: ## Access PHP container shell
	docker-compose exec php bash

composer-install: ## Install Composer dependencies
	docker-compose exec php composer install

migrate: ## Run database migrations
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

migrate-test: ## Run database migrations for test environment
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction --env=test

create-accounts: ## Create sample accounts
	docker-compose exec php php bin/console app:create-accounts

test: ## Run all tests
	docker-compose exec php vendor/bin/phpunit

test-unit: ## Run unit tests only
	docker-compose exec php vendor/bin/phpunit --testsuite Unit

test-integration: ## Run integration tests only
	docker-compose exec php vendor/bin/phpunit --testsuite Integration

clean: ## Clean up containers and volumes
	docker-compose down -v

setup: build up composer-install migrate create-accounts ## Complete setup (build, start, install, migrate, create accounts)
	@echo "Setup complete! API is available at http://localhost:8080"
	@echo "MySQL is available at localhost:3306"
