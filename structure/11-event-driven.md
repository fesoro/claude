# Event-Driven Architecture (Senior)

Event-Driven Architecture event-ləri komponentlər arasında əsas kommunikasiya mexanizmi kimi istifadə edir.
Komponentlər event-ləri async olaraq produce və consume edir — bu, loose coupling və scalability verir.

**Əsas anlayışlar:**
- **Event** — Baş vermiş nəyinsə qeydi
- **Event Producer** — Event yaradır və yayımlayır
- **Event Consumer** — Event-i dinləyir və reaksiya verir
- **Event Bus/Broker** — Event-ləri producer ilə consumer arasında daşıyır
- **Event Store** — Event-ləri saxlayır (Event Sourcing-də)
- **Saga/Process Manager** — Çoxmərhələli workflow-ları event-lər vasitəsilə koordinasiya edir

---

## Laravel

```
app/
├── Events/                             # Event definitions
│   ├── User/
│   │   ├── UserRegistered.php
│   │   ├── UserEmailVerified.php
│   │   ├── UserProfileUpdated.php
│   │   └── UserDeactivated.php
│   ├── Order/
│   │   ├── OrderPlaced.php
│   │   ├── OrderConfirmed.php
│   │   ├── OrderShipped.php
│   │   ├── OrderDelivered.php
│   │   └── OrderCancelled.php
│   ├── Payment/
│   │   ├── PaymentInitiated.php
│   │   ├── PaymentCompleted.php
│   │   └── PaymentFailed.php
│   └── Inventory/
│       ├── StockReserved.php
│       ├── StockReleased.php
│       └── StockDepleted.php
│
├── Listeners/                          # Event consumers
│   ├── User/
│   │   ├── SendWelcomeEmail.php
│   │   ├── CreateUserProfile.php
│   │   └── NotifyAdminOnDeactivation.php
│   ├── Order/
│   │   ├── ReserveInventory.php
│   │   ├── InitiatePayment.php
│   │   ├── SendOrderConfirmation.php
│   │   ├── NotifyShipping.php
│   │   └── UpdateOrderAnalytics.php
│   ├── Payment/
│   │   ├── ConfirmOrder.php
│   │   ├── ReleaseInventoryOnFailure.php
│   │   └── NotifyPaymentFailure.php
│   └── Inventory/
│       └── AlertLowStock.php
│
├── Sagas/                              # Process managers
│   ├── OrderFulfillmentSaga.php
│   └── PaymentProcessSaga.php
│
├── Models/
│   ├── User.php
│   ├── Order.php
│   └── Payment.php
│
├── Services/
│   ├── EventStore/
│   │   ├── EventStoreInterface.php
│   │   └── DatabaseEventStore.php
│   └── EventBus/
│       ├── EventBusInterface.php
│       └── AsyncEventBus.php
│
├── Infrastructure/
│   ├── Queue/
│   │   ├── Jobs/
│   │   │   ├── ProcessEventJob.php
│   │   │   └── ReplayEventsJob.php
│   │   └── Middleware/
│   │       └── EventRetryMiddleware.php
│   ├── Messaging/
│   │   ├── RabbitMQ/
│   │   │   ├── RabbitMQPublisher.php
│   │   │   └── RabbitMQConsumer.php
│   │   └── Kafka/
│   │       ├── KafkaPublisher.php
│   │       └── KafkaConsumer.php
│   └── Providers/
│       └── EventServiceProvider.php
│
├── Http/
│   └── Controllers/
│       ├── UserController.php
│       └── OrderController.php
│
└── Console/
    └── Commands/
        ├── ConsumeEventsCommand.php
        └── ReplayEventsCommand.php
```

---

## Symfony

```
src/
├── Event/                              # Event definitions
│   ├── User/
│   │   ├── UserRegisteredEvent.php
│   │   ├── UserEmailVerifiedEvent.php
│   │   └── UserDeactivatedEvent.php
│   ├── Order/
│   │   ├── OrderPlacedEvent.php
│   │   ├── OrderConfirmedEvent.php
│   │   ├── OrderShippedEvent.php
│   │   └── OrderCancelledEvent.php
│   ├── Payment/
│   │   ├── PaymentInitiatedEvent.php
│   │   ├── PaymentCompletedEvent.php
│   │   └── PaymentFailedEvent.php
│   └── Inventory/
│       ├── StockReservedEvent.php
│       └── StockDepletedEvent.php
│
├── EventHandler/                       # Event consumers
│   ├── User/
│   │   ├── SendWelcomeEmailHandler.php
│   │   └── CreateUserProfileHandler.php
│   ├── Order/
│   │   ├── ReserveInventoryHandler.php
│   │   ├── InitiatePaymentHandler.php
│   │   └── SendOrderConfirmationHandler.php
│   ├── Payment/
│   │   ├── ConfirmOrderHandler.php
│   │   └── ReleaseInventoryOnFailureHandler.php
│   └── Inventory/
│       └── AlertLowStockHandler.php
│
├── Saga/                               # Process managers
│   ├── OrderFulfillmentSaga.php
│   └── PaymentProcessSaga.php
│
├── Domain/
│   ├── User/
│   │   └── Entity/
│   │       └── User.php
│   ├── Order/
│   │   └── Entity/
│   │       └── Order.php
│   └── Payment/
│       └── Entity/
│           └── Payment.php
│
├── Infrastructure/
│   ├── EventStore/
│   │   ├── EventStoreInterface.php
│   │   └── DoctrineEventStore.php
│   ├── Messenger/
│   │   ├── Transport/
│   │   │   ├── RabbitMQTransport.php
│   │   │   └── KafkaTransport.php
│   │   ├── Middleware/
│   │   │   └── EventLoggingMiddleware.php
│   │   └── Serializer/
│   │       └── EventSerializer.php
│   └── Persistence/
│       └── Doctrine/
│           └── Repository/
│
└── UI/
    ├── Http/
    │   └── Controller/
    │       ├── UserController.php
    │       └── OrderController.php
    └── CLI/
        └── Command/
            ├── ConsumeEventsCommand.php
            └── ReplayEventsCommand.php

config/
├── packages/
│   └── messenger.yaml                  # Async transport config
└── services.yaml
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── event/                              # Event definitions
│   ├── user/
│   │   ├── UserRegisteredEvent.java
│   │   ├── UserEmailVerifiedEvent.java
│   │   └── UserDeactivatedEvent.java
│   ├── order/
│   │   ├── OrderPlacedEvent.java
│   │   ├── OrderConfirmedEvent.java
│   │   ├── OrderShippedEvent.java
│   │   └── OrderCancelledEvent.java
│   ├── payment/
│   │   ├── PaymentInitiatedEvent.java
│   │   ├── PaymentCompletedEvent.java
│   │   └── PaymentFailedEvent.java
│   ├── inventory/
│   │   ├── StockReservedEvent.java
│   │   └── StockDepletedEvent.java
│   └── base/
│       ├── DomainEvent.java
│       └── IntegrationEvent.java
│
├── eventhandler/                       # Event consumers
│   ├── user/
│   │   ├── SendWelcomeEmailHandler.java
│   │   └── CreateUserProfileHandler.java
│   ├── order/
│   │   ├── ReserveInventoryHandler.java
│   │   ├── InitiatePaymentHandler.java
│   │   └── SendOrderConfirmationHandler.java
│   ├── payment/
│   │   ├── ConfirmOrderHandler.java
│   │   └── ReleaseInventoryOnFailureHandler.java
│   └── inventory/
│       └── AlertLowStockHandler.java
│
├── saga/                               # Orchestrated workflows
│   ├── OrderFulfillmentSaga.java
│   ├── OrderFulfillmentSagaState.java
│   ├── PaymentProcessSaga.java
│   └── PaymentProcessSagaState.java
│
├── domain/
│   ├── user/
│   │   ├── User.java
│   │   └── UserRepository.java
│   ├── order/
│   │   ├── Order.java
│   │   └── OrderRepository.java
│   └── payment/
│       ├── Payment.java
│       └── PaymentRepository.java
│
├── infrastructure/
│   ├── eventstore/
│   │   ├── EventStore.java
│   │   ├── JpaEventStore.java
│   │   └── EventStoreEntity.java
│   ├── messaging/
│   │   ├── kafka/
│   │   │   ├── KafkaEventPublisher.java
│   │   │   ├── KafkaEventConsumer.java
│   │   │   └── KafkaConfig.java
│   │   └── rabbitmq/
│   │       ├── RabbitMQEventPublisher.java
│   │       ├── RabbitMQEventConsumer.java
│   │       └── RabbitMQConfig.java
│   ├── serialization/
│   │   └── EventSerializer.java
│   └── config/
│       └── EventDrivenConfig.java
│
└── interfaces/
    └── rest/
        ├── controller/
        │   ├── UserController.java
        │   └── OrderController.java
        └── dto/

src/main/resources/
├── application.yml
└── db/migration/
```

---

## Golang

```
project/
├── cmd/
│   ├── api/
│   │   └── main.go                     # HTTP API entry point
│   └── consumer/
│       └── main.go                     # Event consumer entry point
│
├── internal/
│   ├── event/                          # Event definitions
│   │   ├── user/
│   │   │   ├── user_registered.go
│   │   │   ├── user_verified.go
│   │   │   └── user_deactivated.go
│   │   ├── order/
│   │   │   ├── order_placed.go
│   │   │   ├── order_confirmed.go
│   │   │   ├── order_shipped.go
│   │   │   └── order_cancelled.go
│   │   ├── payment/
│   │   │   ├── payment_initiated.go
│   │   │   ├── payment_completed.go
│   │   │   └── payment_failed.go
│   │   └── base.go                     # Event interface
│   │
│   ├── handler/                        # Event consumers
│   │   ├── user/
│   │   │   ├── send_welcome_email.go
│   │   │   └── create_profile.go
│   │   ├── order/
│   │   │   ├── reserve_inventory.go
│   │   │   ├── initiate_payment.go
│   │   │   └── send_confirmation.go
│   │   └── payment/
│   │       ├── confirm_order.go
│   │       └── release_inventory.go
│   │
│   ├── saga/                           # Process managers
│   │   ├── order_fulfillment.go
│   │   └── payment_process.go
│   │
│   ├── domain/
│   │   ├── user/
│   │   │   ├── user.go
│   │   │   └── repository.go
│   │   ├── order/
│   │   │   ├── order.go
│   │   │   └── repository.go
│   │   └── payment/
│   │       ├── payment.go
│   │       └── repository.go
│   │
│   ├── infrastructure/
│   │   ├── eventstore/
│   │   │   ├── store.go               # Interface
│   │   │   └── postgres_store.go
│   │   ├── messaging/
│   │   │   ├── publisher.go           # Interface
│   │   │   ├── subscriber.go          # Interface
│   │   │   ├── nats/
│   │   │   │   ├── publisher.go
│   │   │   │   └── subscriber.go
│   │   │   └── kafka/
│   │   │       ├── publisher.go
│   │   │       └── subscriber.go
│   │   ├── persistence/
│   │   │   └── postgres/
│   │   │       ├── user_repo.go
│   │   │       └── order_repo.go
│   │   └── config/
│   │       └── config.go
│   │
│   └── interfaces/
│       └── http/
│           ├── handler/
│           │   ├── user_handler.go
│           │   └── order_handler.go
│           └── router/
│               └── router.go
│
├── pkg/
│   ├── eventbus/
│   │   ├── bus.go                      # Generic event bus
│   │   └── middleware.go
│   └── retry/
│       └── retry.go
├── go.mod
└── Makefile
```
