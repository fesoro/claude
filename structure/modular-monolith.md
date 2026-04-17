# Modular Monolith

A Modular Monolith organizes code into independent modules within a single deployable unit.
Each module has clear boundaries and encapsulated data, but they share the same process and deployment.

**Key concepts:**
- **Module** — Self-contained business capability with its own domain, data, and API
- **Module API** — Public interface exposed to other modules
- **Internal** — Private implementation hidden from other modules
- **Integration Events** — Async communication between modules
- **Module Registry** — Registers and bootstraps all modules

**Advantages over traditional monolith:** Strong boundaries. Over microservices: No distributed system complexity.

---

## Laravel

```
app/
├── Modules/
│   ├── User/                               # User Module
│   │   ├── Api/                            # Public module API
│   │   │   ├── UserModuleInterface.php     # Contract for other modules
│   │   │   ├── UserDTO.php
│   │   │   └── Events/
│   │   │       ├── UserCreated.php
│   │   │       └── UserDeactivated.php
│   │   │
│   │   ├── Internal/                       # Private implementation
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── UserController.php
│   │   │   │   ├── Requests/
│   │   │   │   │   └── CreateUserRequest.php
│   │   │   │   └── Resources/
│   │   │   │       └── UserResource.php
│   │   │   ├── Services/
│   │   │   │   └── UserService.php
│   │   │   ├── Repositories/
│   │   │   │   ├── UserRepositoryInterface.php
│   │   │   │   └── EloquentUserRepository.php
│   │   │   ├── Models/
│   │   │   │   ├── User.php
│   │   │   │   └── UserProfile.php
│   │   │   ├── Events/
│   │   │   │   └── Listeners/
│   │   │   └── Database/
│   │   │       ├── Migrations/
│   │   │       ├── Seeders/
│   │   │       └── Factories/
│   │   │
│   │   ├── Routes/
│   │   │   └── api.php
│   │   ├── Config/
│   │   │   └── user.php
│   │   ├── Tests/
│   │   │   ├── Unit/
│   │   │   ├── Integration/
│   │   │   └── Feature/
│   │   ├── UserModule.php                  # Module bootstrap
│   │   └── UserModuleServiceProvider.php
│   │
│   ├── Order/                              # Order Module
│   │   ├── Api/
│   │   │   ├── OrderModuleInterface.php
│   │   │   ├── OrderDTO.php
│   │   │   └── Events/
│   │   │       ├── OrderPlaced.php
│   │   │       └── OrderCancelled.php
│   │   │
│   │   ├── Internal/
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   └── OrderController.php
│   │   │   │   ├── Requests/
│   │   │   │   └── Resources/
│   │   │   ├── Services/
│   │   │   │   └── OrderService.php
│   │   │   ├── Repositories/
│   │   │   ├── Models/
│   │   │   │   ├── Order.php
│   │   │   │   └── OrderItem.php
│   │   │   ├── Listeners/
│   │   │   │   └── HandlePaymentCompleted.php
│   │   │   └── Database/
│   │   │       └── Migrations/
│   │   │
│   │   ├── Routes/
│   │   ├── Tests/
│   │   └── OrderModuleServiceProvider.php
│   │
│   ├── Payment/                            # Payment Module
│   │   ├── Api/
│   │   │   ├── PaymentModuleInterface.php
│   │   │   └── Events/
│   │   │       ├── PaymentCompleted.php
│   │   │       └── PaymentFailed.php
│   │   ├── Internal/
│   │   │   ├── Http/
│   │   │   ├── Services/
│   │   │   │   ├── PaymentService.php
│   │   │   │   └── Gateways/
│   │   │   │       ├── StripeGateway.php
│   │   │   │       └── PayPalGateway.php
│   │   │   ├── Models/
│   │   │   └── Database/
│   │   ├── Routes/
│   │   ├── Tests/
│   │   └── PaymentModuleServiceProvider.php
│   │
│   └── Notification/                       # Notification Module
│       ├── Api/
│       ├── Internal/
│       ├── Routes/
│       ├── Tests/
│       └── NotificationModuleServiceProvider.php
│
├── Shared/                                 # Shared kernel
│   ├── ModuleInterface.php
│   ├── EventBus/
│   │   ├── IntegrationEventBus.php
│   │   └── IntegrationEvent.php
│   └── Providers/
│       └── ModuleRegistryServiceProvider.php
│
config/
routes/
    └── web.php
```

---

## Symfony

```
src/
├── Module/
│   ├── User/
│   │   ├── Api/                            # Public API
│   │   │   ├── UserModuleInterface.php
│   │   │   ├── DTO/
│   │   │   │   └── UserDTO.php
│   │   │   └── Event/
│   │   │       └── UserCreatedEvent.php
│   │   │
│   │   ├── Internal/                       # Private
│   │   │   ├── Controller/
│   │   │   │   └── UserController.php
│   │   │   ├── Service/
│   │   │   │   └── UserService.php
│   │   │   ├── Repository/
│   │   │   │   └── UserRepository.php
│   │   │   ├── Entity/
│   │   │   │   └── User.php
│   │   │   ├── EventHandler/
│   │   │   └── Migration/
│   │   │
│   │   ├── Resources/
│   │   │   ├── config/
│   │   │   │   ├── routes.yaml
│   │   │   │   └── services.yaml
│   │   │   └── doctrine/
│   │   │       └── User.orm.xml
│   │   ├── Tests/
│   │   └── UserModule.php
│   │
│   ├── Order/
│   │   ├── Api/
│   │   │   ├── OrderModuleInterface.php
│   │   │   ├── DTO/
│   │   │   └── Event/
│   │   ├── Internal/
│   │   │   ├── Controller/
│   │   │   ├── Service/
│   │   │   ├── Repository/
│   │   │   ├── Entity/
│   │   │   └── EventHandler/
│   │   ├── Resources/
│   │   ├── Tests/
│   │   └── OrderModule.php
│   │
│   ├── Payment/
│   │   ├── Api/
│   │   ├── Internal/
│   │   ├── Resources/
│   │   ├── Tests/
│   │   └── PaymentModule.php
│   │
│   └── Notification/
│       ├── Api/
│       ├── Internal/
│       ├── Resources/
│       ├── Tests/
│       └── NotificationModule.php
│
├── Shared/
│   ├── Module/
│   │   ├── ModuleInterface.php
│   │   └── ModuleRegistry.php
│   └── EventBus/
│       ├── IntegrationEventBus.php
│       └── IntegrationEvent.php
│
config/
├── packages/
└── services.yaml
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── module/
│   ├── user/
│   │   ├── api/                            # Public API
│   │   │   ├── UserModuleApi.java          # Interface
│   │   │   ├── dto/
│   │   │   │   └── UserDTO.java
│   │   │   └── event/
│   │   │       └── UserCreatedEvent.java
│   │   │
│   │   ├── internal/                       # Private (package-private)
│   │   │   ├── controller/
│   │   │   │   └── UserController.java
│   │   │   ├── service/
│   │   │   │   ├── UserService.java
│   │   │   │   └── UserModuleApiImpl.java
│   │   │   ├── repository/
│   │   │   │   └── UserRepository.java
│   │   │   ├── entity/
│   │   │   │   └── User.java
│   │   │   ├── eventhandler/
│   │   │   └── config/
│   │   │       └── UserModuleConfig.java
│   │   └── UserModuleInitializer.java
│   │
│   ├── order/
│   │   ├── api/
│   │   │   ├── OrderModuleApi.java
│   │   │   ├── dto/
│   │   │   │   └── OrderDTO.java
│   │   │   └── event/
│   │   │       ├── OrderPlacedEvent.java
│   │   │       └── OrderCancelledEvent.java
│   │   │
│   │   ├── internal/
│   │   │   ├── controller/
│   │   │   │   └── OrderController.java
│   │   │   ├── service/
│   │   │   │   ├── OrderService.java
│   │   │   │   └── OrderModuleApiImpl.java
│   │   │   ├── repository/
│   │   │   ├── entity/
│   │   │   │   ├── Order.java
│   │   │   │   └── OrderItem.java
│   │   │   └── eventhandler/
│   │   │       └── HandlePaymentCompleted.java
│   │   └── OrderModuleInitializer.java
│   │
│   ├── payment/
│   │   ├── api/
│   │   ├── internal/
│   │   └── PaymentModuleInitializer.java
│   │
│   └── notification/
│       ├── api/
│       ├── internal/
│       └── NotificationModuleInitializer.java
│
├── shared/
│   ├── module/
│   │   ├── ModuleApi.java
│   │   └── ModuleRegistry.java
│   └── event/
│       ├── IntegrationEventBus.java
│       ├── SpringIntegrationEventBus.java
│       └── IntegrationEvent.java
│
└── config/
    └── AppConfig.java

src/main/resources/
├── application.yml
└── db/migration/
    ├── user/
    ├── order/
    └── payment/
```

---

## Golang

```
project/
├── cmd/
│   └── api/
│       └── main.go
│
├── internal/
│   ├── module/
│   │   ├── user/
│   │   │   ├── api/                        # Public API
│   │   │   │   ├── module.go              # Module interface
│   │   │   │   ├── dto.go
│   │   │   │   └── events.go
│   │   │   │
│   │   │   ├── internal/                   # Private implementation
│   │   │   │   ├── handler/
│   │   │   │   │   └── user_handler.go
│   │   │   │   ├── service/
│   │   │   │   │   ├── user_service.go
│   │   │   │   │   └── module_impl.go
│   │   │   │   ├── repository/
│   │   │   │   │   ├── repository.go      # Interface
│   │   │   │   │   └── postgres_repo.go
│   │   │   │   ├── model/
│   │   │   │   │   └── user.go
│   │   │   │   └── eventhandler/
│   │   │   │
│   │   │   ├── migration/
│   │   │   ├── tests/
│   │   │   │   ├── unit/
│   │   │   │   ├── integration/
│   │   │   │   └── fixture/
│   │   │   └── module.go                  # Module bootstrap
│   │   │
│   │   ├── order/
│   │   │   ├── api/
│   │   │   │   ├── module.go
│   │   │   │   ├── dto.go
│   │   │   │   └── events.go
│   │   │   ├── internal/
│   │   │   │   ├── handler/
│   │   │   │   ├── service/
│   │   │   │   ├── repository/
│   │   │   │   ├── model/
│   │   │   │   └── eventhandler/
│   │   │   │       └── payment_completed.go
│   │   │   ├── migration/
│   │   │   ├── tests/
│   │   │   └── module.go
│   │   │
│   │   ├── payment/
│   │   │   ├── api/
│   │   │   ├── internal/
│   │   │   ├── migration/
│   │   │   ├── tests/
│   │   │   └── module.go
│   │   │
│   │   └── notification/
│   │       ├── api/
│   │       ├── internal/
│   │       ├── tests/
│   │       └── module.go
│   │
│   └── shared/
│       ├── module/
│       │   ├── module.go                  # Module interface
│       │   └── registry.go
│       └── eventbus/
│           ├── bus.go
│           └── event.go
│
├── pkg/
├── go.mod
└── Makefile
```
