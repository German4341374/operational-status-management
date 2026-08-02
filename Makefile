SHELL := /bin/sh

.PHONY: setup lint analyse test check migrate up down logs clean password-hash

setup:
	cp .env.example .env
	composer install

lint:
	composer lint

analyse:
	composer analyse

test:
	composer test

check:
	composer check

migrate:
	php bin/migrate.php

up:
	docker compose up --build --detach --wait

down:
	docker compose down

logs:
	docker compose logs --follow nginx application database

password-hash:
	@php -r 'echo password_hash(readline("New admin password: "), PASSWORD_DEFAULT), PHP_EOL;'

clean:
	rm -rf build .phpunit.cache .phpstan-cache .php-cs-fixer.cache
	docker compose down --volumes --remove-orphans
