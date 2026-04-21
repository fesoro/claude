# Laravel Makefile — eyni interfeys 3 stack-də (make help)
.PHONY: help up down build test seed migrate logs shell composer

help:  ## bütün command-lar
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up:  ## docker-compose up
	docker-compose up -d

down:  ## docker-compose down
	docker-compose down

build:  ## image-i yenidən qur
	docker-compose build --no-cache

logs:  ## servisin log-larını izlə
	docker-compose logs -f app

shell:  ## app container-ə daxil ol
	docker-compose exec app bash

migrate:  ## DB migration-ları işlət
	docker-compose exec app php artisan migrate

seed:  ## Test data əlavə et
	docker-compose exec app php artisan db:seed

test:  ## Bütün testlər
	docker-compose exec app php artisan test

composer:  ## composer install
	docker-compose exec app composer install

fresh:  ## DB sıfırla + miqrate + seed
	docker-compose exec app php artisan migrate:fresh --seed
