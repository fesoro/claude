# Microservices Architecture

Microservices splits an application into small, independently deployable services.
Each service owns its data, runs in its own process, and communicates via APIs or messaging.

**Key concepts:**
- **Service** — Independent, deployable unit with its own database
- **API Gateway** — Single entry point for clients
- **Service Discovery** — How services find each other
- **Message Broker** — Async communication between services
- **Circuit Breaker** — Fault tolerance pattern
- **Saga** — Distributed transaction management

---

## Laravel

```
project/
├── api-gateway/                            # API Gateway Service
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── GatewayController.php
│   │   │   └── Middleware/
│   │   │       ├── AuthenticateRequest.php
│   │   │       ├── RateLimiter.php
│   │   │       └── RequestLogger.php
│   │   ├── Services/
│   │   │   ├── ServiceRegistry.php
│   │   │   ├── LoadBalancer.php
│   │   │   └── CircuitBreaker.php
│   │   └── Routes/
│   │       └── gateway.php
│   ├── config/
│   ├── composer.json
│   └── Dockerfile
│
├── user-service/                           # User Microservice
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── UserController.php
│   │   │   ├── Requests/
│   │   │   │   ├── CreateUserRequest.php
│   │   │   │   └── UpdateUserRequest.php
│   │   │   └── Resources/
│   │   │       └── UserResource.php
│   │   ├── Services/
│   │   │   └── UserService.php
│   │   ├── Repositories/
│   │   │   ├── UserRepositoryInterface.php
│   │   │   └── EloquentUserRepository.php
│   │   ├── Models/
│   │   │   └── User.php
│   │   ├── Events/
│   │   │   ├── UserCreated.php
│   │   │   └── UserUpdated.php
│   │   └── Listeners/
│   ├── database/
│   │   └── migrations/
│   ├── routes/
│   │   └── api.php
│   ├── config/
│   ├── composer.json
│   └── Dockerfile
│
├── order-service/                          # Order Microservice
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   └── OrderController.php
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Services/
│   │   │   ├── OrderService.php
│   │   │   └── OrderSaga.php
│   │   ├── Repositories/
│   │   ├── Models/
│   │   │   ├── Order.php
│   │   │   └── OrderItem.php
│   │   ├── Events/
│   │   │   ├── OrderPlaced.php
│   │   │   ├── OrderConfirmed.php
│   │   │   └── OrderCancelled.php
│   │   ├── Listeners/
│   │   │   ├── HandlePaymentCompleted.php
│   │   │   └── HandleInventoryReserved.php
│   │   └── Clients/                       # HTTP clients to other services
│   │       ├── UserServiceClient.php
│   │       └── InventoryServiceClient.php
│   ├── database/
│   ├── routes/
│   ├── composer.json
│   └── Dockerfile
│
├── payment-service/                        # Payment Microservice
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       └── PaymentController.php
│   │   ├── Services/
│   │   │   ├── PaymentService.php
│   │   │   └── PaymentGateway/
│   │   │       ├── StripeGateway.php
│   │   │       └── PayPalGateway.php
│   │   ├── Models/
│   │   │   └── Payment.php
│   │   ├── Events/
│   │   │   ├── PaymentCompleted.php
│   │   │   └── PaymentFailed.php
│   │   └── Listeners/
│   │       └── HandleOrderPlaced.php
│   ├── database/
│   ├── routes/
│   ├── composer.json
│   └── Dockerfile
│
├── notification-service/                   # Notification Microservice
│   ├── app/
│   │   ├── Services/
│   │   │   ├── NotificationService.php
│   │   │   ├── EmailNotifier.php
│   │   │   ├── SmsNotifier.php
│   │   │   └── PushNotifier.php
│   │   ├── Listeners/
│   │   │   ├── HandleUserCreated.php
│   │   │   ├── HandleOrderPlaced.php
│   │   │   └── HandlePaymentCompleted.php
│   │   └── Templates/
│   ├── composer.json
│   └── Dockerfile
│
├── shared/                                 # Shared libraries
│   ├── packages/
│   │   ├── event-bus/
│   │   │   ├── src/
│   │   │   │   ├── EventBusInterface.php
│   │   │   │   ├── RabbitMQEventBus.php
│   │   │   │   └── Event.php
│   │   │   └── composer.json
│   │   └── service-client/
│   │       ├── src/
│   │       │   ├── HttpClient.php
│   │       │   └── CircuitBreaker.php
│   │       └── composer.json
│   └── proto/                             # gRPC proto definitions
│       ├── user.proto
│       └── order.proto
│
├── infrastructure/
│   ├── docker-compose.yml
│   ├── docker-compose.dev.yml
│   ├── kubernetes/
│   │   ├── namespaces/
│   │   ├── deployments/
│   │   │   ├── user-service.yaml
│   │   │   ├── order-service.yaml
│   │   │   ├── payment-service.yaml
│   │   │   └── notification-service.yaml
│   │   ├── services/
│   │   ├── ingress/
│   │   └── configmaps/
│   ├── terraform/
│   │   ├── main.tf
│   │   ├── variables.tf
│   │   └── modules/
│   └── monitoring/
│       ├── prometheus/
│       └── grafana/
│
└── docs/
    ├── architecture.md
    └── api-contracts/
```

---

## Symfony

```
project/
├── api-gateway/
│   ├── src/
│   │   ├── Controller/
│   │   │   └── GatewayController.php
│   │   ├── Service/
│   │   │   ├── ServiceRegistry.php
│   │   │   ├── LoadBalancer.php
│   │   │   └── CircuitBreaker.php
│   │   └── Middleware/
│   ├── config/
│   ├── composer.json
│   └── Dockerfile
│
├── user-service/
│   ├── src/
│   │   ├── Controller/
│   │   │   └── UserController.php
│   │   ├── Service/
│   │   │   └── UserService.php
│   │   ├── Repository/
│   │   │   └── UserRepository.php
│   │   ├── Entity/
│   │   │   └── User.php
│   │   ├── Event/
│   │   │   ├── UserCreatedEvent.php
│   │   │   └── UserUpdatedEvent.php
│   │   ├── EventHandler/
│   │   └── DTO/
│   │       ├── CreateUserDTO.php
│   │       └── UserResponseDTO.php
│   ├── config/
│   │   ├── packages/
│   │   │   └── messenger.yaml
│   │   └── services.yaml
│   ├── migrations/
│   ├── composer.json
│   └── Dockerfile
│
├── order-service/
│   ├── src/
│   │   ├── Controller/
│   │   │   └── OrderController.php
│   │   ├── Service/
│   │   │   ├── OrderService.php
│   │   │   └── OrderSaga.php
│   │   ├── Repository/
│   │   ├── Entity/
│   │   │   ├── Order.php
│   │   │   └── OrderItem.php
│   │   ├── Event/
│   │   │   ├── OrderPlacedEvent.php
│   │   │   └── OrderCancelledEvent.php
│   │   ├── EventHandler/
│   │   │   ├── HandlePaymentCompleted.php
│   │   │   └── HandleInventoryReserved.php
│   │   └── Client/
│   │       ├── UserServiceClient.php
│   │       └── InventoryServiceClient.php
│   ├── config/
│   ├── composer.json
│   └── Dockerfile
│
├── payment-service/
│   ├── src/
│   │   ├── Controller/
│   │   ├── Service/
│   │   │   ├── PaymentService.php
│   │   │   └── Gateway/
│   │   │       ├── StripeGateway.php
│   │   │       └── PayPalGateway.php
│   │   ├── Entity/
│   │   ├── Event/
│   │   └── EventHandler/
│   ├── config/
│   ├── composer.json
│   └── Dockerfile
│
├── notification-service/
│   ├── src/
│   │   ├── Service/
│   │   │   ├── NotificationService.php
│   │   │   ├── EmailNotifier.php
│   │   │   └── SmsNotifier.php
│   │   ├── EventHandler/
│   │   │   ├── HandleUserCreated.php
│   │   │   └── HandleOrderPlaced.php
│   │   └── Template/
│   ├── config/
│   ├── composer.json
│   └── Dockerfile
│
├── shared/
│   ├── packages/
│   │   └── event-bus/
│   └── proto/
│
└── infrastructure/
    ├── docker-compose.yml
    ├── kubernetes/
    └── monitoring/
```

---

## Spring Boot (Java)

```
project/
├── api-gateway/                            # Spring Cloud Gateway
│   ├── src/main/java/com/example/gateway/
│   │   ├── GatewayApplication.java
│   │   ├── config/
│   │   │   ├── RouteConfig.java
│   │   │   ├── SecurityConfig.java
│   │   │   └── RateLimiterConfig.java
│   │   ├── filter/
│   │   │   ├── AuthFilter.java
│   │   │   └── LoggingFilter.java
│   │   └── fallback/
│   │       └── FallbackController.java
│   ├── src/main/resources/
│   │   └── application.yml
│   ├── build.gradle
│   └── Dockerfile
│
├── service-discovery/                      # Eureka Server
│   ├── src/main/java/com/example/discovery/
│   │   └── DiscoveryApplication.java
│   ├── src/main/resources/
│   │   └── application.yml
│   ├── build.gradle
│   └── Dockerfile
│
├── config-server/                          # Spring Cloud Config
│   ├── src/main/java/com/example/config/
│   │   └── ConfigApplication.java
│   ├── src/main/resources/
│   │   └── application.yml
│   ├── build.gradle
│   └── Dockerfile
│
├── user-service/
│   ├── src/main/java/com/example/user/
│   │   ├── UserServiceApplication.java
│   │   ├── controller/
│   │   │   └── UserController.java
│   │   ├── service/
│   │   │   ├── UserService.java
│   │   │   └── UserServiceImpl.java
│   │   ├── repository/
│   │   │   └── UserRepository.java
│   │   ├── entity/
│   │   │   └── User.java
│   │   ├── dto/
│   │   │   ├── CreateUserRequest.java
│   │   │   └── UserResponse.java
│   │   ├── event/
│   │   │   ├── UserCreatedEvent.java
│   │   │   └── UserUpdatedEvent.java
│   │   ├── messaging/
│   │   │   └── UserEventPublisher.java
│   │   ├── exception/
│   │   │   └── UserNotFoundException.java
│   │   └── config/
│   │       └── KafkaConfig.java
│   ├── src/main/resources/
│   │   └── application.yml
│   ├── build.gradle
│   └── Dockerfile
│
├── order-service/
│   ├── src/main/java/com/example/order/
│   │   ├── OrderServiceApplication.java
│   │   ├── controller/
│   │   │   └── OrderController.java
│   │   ├── service/
│   │   │   ├── OrderService.java
│   │   │   └── OrderSaga.java
│   │   ├── repository/
│   │   ├── entity/
│   │   │   ├── Order.java
│   │   │   └── OrderItem.java
│   │   ├── dto/
│   │   ├── event/
│   │   │   ├── OrderPlacedEvent.java
│   │   │   └── OrderCancelledEvent.java
│   │   ├── messaging/
│   │   │   ├── OrderEventPublisher.java
│   │   │   └── OrderEventConsumer.java
│   │   ├── client/
│   │   │   ├── UserServiceClient.java     # Feign client
│   │   │   └── InventoryServiceClient.java
│   │   └── config/
│   ├── src/main/resources/
│   ├── build.gradle
│   └── Dockerfile
│
├── payment-service/
│   ├── src/main/java/com/example/payment/
│   │   ├── PaymentServiceApplication.java
│   │   ├── controller/
│   │   ├── service/
│   │   │   ├── PaymentService.java
│   │   │   └── gateway/
│   │   │       ├── PaymentGateway.java
│   │   │       ├── StripeGateway.java
│   │   │       └── PayPalGateway.java
│   │   ├── entity/
│   │   ├── event/
│   │   ├── messaging/
│   │   └── config/
│   ├── src/main/resources/
│   ├── build.gradle
│   └── Dockerfile
│
├── notification-service/
│   ├── src/main/java/com/example/notification/
│   │   ├── NotificationApplication.java
│   │   ├── service/
│   │   │   ├── NotificationService.java
│   │   │   ├── EmailNotifier.java
│   │   │   └── SmsNotifier.java
│   │   ├── messaging/
│   │   │   └── NotificationEventConsumer.java
│   │   └── template/
│   ├── src/main/resources/
│   ├── build.gradle
│   └── Dockerfile
│
├── shared/
│   ├── common-lib/
│   │   ├── src/main/java/com/example/common/
│   │   │   ├── event/
│   │   │   │   └── DomainEvent.java
│   │   │   ├── dto/
│   │   │   └── exception/
│   │   └── build.gradle
│   └── proto/
│
└── infrastructure/
    ├── docker-compose.yml
    ├── kubernetes/
    │   ├── deployments/
    │   ├── services/
    │   └── ingress/
    └── monitoring/
        ├── prometheus/
        └── grafana/
```

---

## Golang

```
project/
├── api-gateway/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   └── proxy_handler.go
│   │   ├── middleware/
│   │   │   ├── auth.go
│   │   │   ├── rate_limiter.go
│   │   │   └── logging.go
│   │   ├── router/
│   │   │   └── router.go
│   │   ├── service/
│   │   │   ├── registry.go
│   │   │   ├── load_balancer.go
│   │   │   └── circuit_breaker.go
│   │   └── config/
│   │       └── config.go
│   ├── go.mod
│   └── Dockerfile
│
├── user-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   └── user_handler.go
│   │   ├── service/
│   │   │   └── user_service.go
│   │   ├── repository/
│   │   │   ├── repository.go            # Interface
│   │   │   └── postgres_repo.go
│   │   ├── model/
│   │   │   └── user.go
│   │   ├── event/
│   │   │   ├── publisher.go
│   │   │   └── events.go
│   │   ├── router/
│   │   │   └── router.go
│   │   └── config/
│   │       └── config.go
│   ├── migrations/
│   ├── go.mod
│   └── Dockerfile
│
├── order-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   └── order_handler.go
│   │   ├── service/
│   │   │   ├── order_service.go
│   │   │   └── saga.go
│   │   ├── repository/
│   │   ├── model/
│   │   │   ├── order.go
│   │   │   └── order_item.go
│   │   ├── event/
│   │   │   ├── publisher.go
│   │   │   ├── consumer.go
│   │   │   └── events.go
│   │   ├── client/
│   │   │   ├── user_client.go
│   │   │   └── inventory_client.go
│   │   └── config/
│   ├── migrations/
│   ├── go.mod
│   └── Dockerfile
│
├── payment-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   ├── service/
│   │   │   ├── payment_service.go
│   │   │   └── gateway/
│   │   │       ├── gateway.go
│   │   │       ├── stripe.go
│   │   │       └── paypal.go
│   │   ├── model/
│   │   ├── event/
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── notification-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── service/
│   │   │   ├── notification.go
│   │   │   ├── email.go
│   │   │   └── sms.go
│   │   ├── event/
│   │   │   └── consumer.go
│   │   ├── template/
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── shared/
│   ├── pkg/
│   │   ├── eventbus/
│   │   │   ├── bus.go
│   │   │   ├── nats_bus.go
│   │   │   └── kafka_bus.go
│   │   ├── httpclient/
│   │   │   ├── client.go
│   │   │   └── circuit_breaker.go
│   │   └── logger/
│   │       └── logger.go
│   ├── proto/
│   │   ├── user/
│   │   │   └── user.proto
│   │   └── order/
│   │       └── order.proto
│   └── go.mod
│
└── infrastructure/
    ├── docker-compose.yml
    ├── kubernetes/
    │   ├── deployments/
    │   ├── services/
    │   └── ingress/
    ├── terraform/
    └── monitoring/
```
