# Spring Makefile — eyni interfeys (make help)
.PHONY: help up down build test seed migrate logs shell run

help:  ## bütün command-lar
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up:  ## docker-compose up (servislər)
	docker compose up -d mysql rabbitmq redis mailpit

up-all:  ## hər şey Docker-də (app + nginx daxil)
	docker compose up --build

down:  ## docker compose down
	docker compose down

build:  ## maven package
	./mvnw clean package -DskipTests

run:  ## lokal Spring Boot işə sal (Docker servislər lazımdır)
	./mvnw spring-boot:run

logs:  ## app log-ları
	docker compose logs -f app

shell:  ## app container-ə daxil ol
	docker compose exec app sh

migrate:  ## Flyway avtomatik işləyir, manual çağırış
	./mvnw flyway:migrate

seed:  ## seed profili ilə işə sal
	./mvnw spring-boot:run -Dspring-boot.run.profiles=seed

test:  ## bütün testlər (unit + feature)
	./mvnw test

it:  ## integration testlər (Testcontainers)
	./mvnw verify

clean:  ## target/ təmizlə
	./mvnw clean
