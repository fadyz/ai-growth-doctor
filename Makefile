.PHONY: dev up down build logs shell migrate test assets

dev:
	docker compose up --build

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f web worker mysql

shell:
	docker compose exec web bash

migrate:
	docker compose exec web php artisan migrate

test:
	docker compose exec web ./vendor/bin/phpunit

assets:
	docker compose --profile assets up node
