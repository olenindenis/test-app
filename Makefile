.PHONY: up down install migrate test shell

up:
	docker compose up -d --build

down:
	docker compose down

install:
	docker compose run --rm --no-deps app composer install
	docker compose run --rm --no-deps app sh -c "[ -f .env ] || (cp .env.example .env && php artisan key:generate)"

migrate:
	docker compose run --rm app php artisan migrate --force

test:
	docker compose run --rm app php artisan test

shell:
	docker compose run --rm app sh
