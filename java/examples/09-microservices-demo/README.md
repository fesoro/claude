# 09 — Microservices Demo (Lead ⭐⭐⭐⭐)

İki Spring Boot servisi arasında REST + Kafka üzərindən kommunikasiya nümunəsi.

## Arxitektura

```
[Client]
   │
   ▼
[Order Service :8081]
   │  REST API (POST /api/orders)
   │  PostgreSQL (orders table)
   │  Kafka Producer (order.created topic)
   │
   ▼
[Kafka]
   │
   ▼
[Notification Service :8082]
   │  Kafka Consumer (order.created topic)
   │  Email mock (log-a yazır)
```

## Öyrənilən Mövzular

- Servislərarası Kafka event kommunikasiyası
- `@KafkaListener` ilə event consumption
- Kafka topic konfiqurasiyası
- Docker Compose ilə lokal microservice stack
- Shared DTO / Event contract
- Idempotent consumer (duplicate protection)

## Başlatma

```bash
# Infrastructure başlat
docker compose up -d

# Order Service
cd order-service
./mvnw spring-boot:run

# Notification Service (yeni terminal)
cd notification-service
./mvnw spring-boot:run
```

## Test

```bash
# Sifariş yarat
curl -X POST http://localhost:8081/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "productId": 1,
    "quantity": 3,
    "customerEmail": "ali@example.com"
  }'

# Notification Service log-una bax:
# INFO  OrderCreatedListener - Email göndərildi: ali@example.com → Order #1
```

## Fayl Strukturu

```
09-microservices-demo/
├── docker-compose.yml          — Kafka + PostgreSQL + Zookeeper
├── order-service/
│   ├── pom.xml
│   └── src/main/
│       ├── java/com/example/order/
│       │   ├── controller/OrderController.java
│       │   ├── service/OrderService.java
│       │   ├── entity/Order.java
│       │   ├── repository/OrderRepository.java
│       │   ├── event/OrderCreatedEvent.java
│       │   └── config/KafkaConfig.java
│       └── resources/application.yml
└── notification-service/
    ├── pom.xml
    └── src/main/
        ├── java/com/example/notification/
        │   ├── listener/OrderCreatedListener.java
        │   ├── service/NotificationService.java
        │   └── config/KafkaConfig.java
        └── resources/application.yml
```

## Java Versiyası

- Java 21+
- Spring Boot 3.3+
- Maven 3.9+
