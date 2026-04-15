.PHONY: up down build logs ps composer sf migrate seed test test-frontend

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f

ps:
	docker compose ps

composer:
	docker compose exec php composer $(CMD)

sf:
	docker compose exec php php bin/console $(CMD)

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

seed:
	docker compose exec php php bin/console app:seed:roles
	docker compose exec php php bin/console app:seed:demo

test:
	docker compose exec php php bin/phpunit

test-unit:
	docker compose exec php php bin/phpunit --testsuite=unit

test-integration:
	docker compose exec php php bin/phpunit --testsuite=integration

test-api:
	docker compose exec php php bin/phpunit --testsuite=api

test-frontend:
	docker compose exec node npm test

shell-php:
	docker compose exec php sh

shell-mysql:
	docker compose exec mysql mysql -u meridian_app -pmeridian_secret meridian
