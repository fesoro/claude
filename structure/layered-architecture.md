# Layered (N-Tier) Architecture

The most traditional architecture pattern. Code is organized into horizontal layers,
each with a specific responsibility. Each layer only communicates with the layer directly below it.

**Layers (top-down):**
- **Presentation** — UI, Controllers, API endpoints
- **Business/Service** — Business logic, validation
- **Data Access** — Database queries, ORM, repositories
- **Database** — Actual data storage

---

## Laravel

```
app/
├── Http/                                   # Presentation Layer
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── UserController.php
│   │   │   ├── OrderController.php
│   │   │   ├── ProductController.php
│   │   │   └── AuthController.php
│   │   └── Web/
│   │       ├── HomeController.php
│   │       ├── DashboardController.php
│   │       └── ProfileController.php
│   ├── Requests/
│   │   ├── CreateUserRequest.php
│   │   ├── UpdateUserRequest.php
│   │   ├── PlaceOrderRequest.php
│   │   └── CreateProductRequest.php
│   ├── Resources/
│   │   ├── UserResource.php
│   │   ├── UserCollection.php
│   │   ├── OrderResource.php
│   │   └── ProductResource.php
│   ├── Middleware/
│   │   ├── Authenticate.php
│   │   ├── RateLimiter.php
│   │   └── LogRequest.php
│   └── ViewComposers/
│       └── NavigationComposer.php
│
├── Services/                               # Business/Service Layer
│   ├── UserService.php
│   ├── OrderService.php
│   ├── ProductService.php
│   ├── PaymentService.php
│   ├── NotificationService.php
│   ├── AuthService.php
│   └── ReportService.php
│
├── Repositories/                           # Data Access Layer
│   ├── Contracts/
│   │   ├── UserRepositoryInterface.php
│   │   ├── OrderRepositoryInterface.php
│   │   └── ProductRepositoryInterface.php
│   ├── Eloquent/
│   │   ├── UserRepository.php
│   │   ├── OrderRepository.php
│   │   └── ProductRepository.php
│   └── Cache/
│       └── CachedUserRepository.php
│
├── Models/                                 # Database Layer (Eloquent)
│   ├── User.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Product.php
│   ├── Category.php
│   └── Payment.php
│
├── Events/
│   └── OrderPlaced.php
├── Listeners/
│   └── SendOrderConfirmation.php
├── Mail/
│   └── OrderConfirmationMail.php
├── Exceptions/
│   └── Handler.php
└── Providers/
    ├── AppServiceProvider.php
    └── RepositoryServiceProvider.php

database/
├── migrations/
├── seeders/
└── factories/
resources/
├── views/
├── js/
└── css/
routes/
├── api.php
└── web.php
config/
tests/
├── Unit/
│   ├── Services/
│   └── Repositories/
└── Feature/
    └── Http/
```

---

## Symfony

```
src/
├── Controller/                             # Presentation Layer
│   ├── Api/
│   │   ├── UserController.php
│   │   ├── OrderController.php
│   │   ├── ProductController.php
│   │   └── AuthController.php
│   └── Web/
│       ├── HomeController.php
│       ├── DashboardController.php
│       └── ProfileController.php
│
├── Form/
│   ├── UserType.php
│   ├── OrderType.php
│   └── ProductType.php
│
├── DTO/
│   ├── Request/
│   │   ├── CreateUserDTO.php
│   │   ├── PlaceOrderDTO.php
│   │   └── CreateProductDTO.php
│   └── Response/
│       ├── UserResponseDTO.php
│       ├── OrderResponseDTO.php
│       └── ProductResponseDTO.php
│
├── Service/                                # Business/Service Layer
│   ├── UserService.php
│   ├── OrderService.php
│   ├── ProductService.php
│   ├── PaymentService.php
│   ├── NotificationService.php
│   └── AuthService.php
│
├── Repository/                             # Data Access Layer
│   ├── UserRepository.php
│   ├── OrderRepository.php
│   ├── ProductRepository.php
│   └── CategoryRepository.php
│
├── Entity/                                 # Database Layer (Doctrine)
│   ├── User.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Product.php
│   ├── Category.php
│   └── Payment.php
│
├── EventSubscriber/
│   └── ExceptionSubscriber.php
├── Security/
│   ├── Voter/
│   │   └── OrderVoter.php
│   └── Authenticator/
│       └── ApiTokenAuthenticator.php
└── Twig/
    └── Extension/
        └── AppExtension.php

config/
├── packages/
│   ├── doctrine.yaml
│   ├── security.yaml
│   └── twig.yaml
├── routes/
│   ├── api.yaml
│   └── web.yaml
└── services.yaml

templates/
├── base.html.twig
├── home/
├── dashboard/
└── profile/

migrations/
tests/
├── Unit/
│   └── Service/
├── Integration/
│   └── Repository/
└── Functional/
    └── Controller/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── controller/                             # Presentation Layer
│   ├── UserController.java
│   ├── OrderController.java
│   ├── ProductController.java
│   ├── AuthController.java
│   └── advice/
│       └── GlobalExceptionHandler.java
│
├── dto/
│   ├── request/
│   │   ├── CreateUserRequest.java
│   │   ├── UpdateUserRequest.java
│   │   ├── PlaceOrderRequest.java
│   │   └── CreateProductRequest.java
│   └── response/
│       ├── UserResponse.java
│       ├── OrderResponse.java
│       └── ProductResponse.java
│
├── service/                                # Business/Service Layer
│   ├── UserService.java
│   ├── UserServiceImpl.java
│   ├── OrderService.java
│   ├── OrderServiceImpl.java
│   ├── ProductService.java
│   ├── ProductServiceImpl.java
│   ├── PaymentService.java
│   └── NotificationService.java
│
├── repository/                             # Data Access Layer
│   ├── UserRepository.java
│   ├── OrderRepository.java
│   ├── ProductRepository.java
│   ├── CategoryRepository.java
│   └── PaymentRepository.java
│
├── entity/                                 # Database Layer (JPA)
│   ├── User.java
│   ├── Order.java
│   ├── OrderItem.java
│   ├── Product.java
│   ├── Category.java
│   └── Payment.java
│
├── mapper/
│   ├── UserMapper.java
│   ├── OrderMapper.java
│   └── ProductMapper.java
│
├── exception/
│   ├── ResourceNotFoundException.java
│   ├── BusinessException.java
│   └── UnauthorizedException.java
│
├── security/
│   ├── SecurityConfig.java
│   ├── JwtTokenProvider.java
│   └── JwtAuthFilter.java
│
└── config/
    ├── WebConfig.java
    ├── SwaggerConfig.java
    └── CacheConfig.java

src/main/resources/
├── application.yml
├── application-dev.yml
├── application-prod.yml
├── db/migration/
└── templates/

src/test/java/com/example/app/
├── unit/
│   └── service/
├── integration/
│   └── repository/
└── functional/
    └── controller/
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
│   ├── handler/                            # Presentation Layer
│   │   ├── user_handler.go
│   │   ├── order_handler.go
│   │   ├── product_handler.go
│   │   ├── auth_handler.go
│   │   └── middleware/
│   │       ├── auth.go
│   │       ├── logging.go
│   │       └── rate_limiter.go
│   │
│   ├── request/
│   │   ├── create_user.go
│   │   ├── place_order.go
│   │   └── create_product.go
│   │
│   ├── response/
│   │   ├── user_response.go
│   │   ├── order_response.go
│   │   └── error_response.go
│   │
│   ├── router/
│   │   └── router.go
│   │
│   ├── service/                            # Business/Service Layer
│   │   ├── user_service.go
│   │   ├── order_service.go
│   │   ├── product_service.go
│   │   ├── payment_service.go
│   │   └── notification_service.go
│   │
│   ├── repository/                         # Data Access Layer
│   │   ├── user_repository.go             # Interface
│   │   ├── order_repository.go
│   │   ├── product_repository.go
│   │   ├── postgres/
│   │   │   ├── user_repo.go              # Implementation
│   │   │   ├── order_repo.go
│   │   │   ├── product_repo.go
│   │   │   └── connection.go
│   │   └── cache/
│   │       └── cached_user_repo.go
│   │
│   ├── model/                              # Database Layer
│   │   ├── user.go
│   │   ├── order.go
│   │   ├── order_item.go
│   │   ├── product.go
│   │   ├── category.go
│   │   └── payment.go
│   │
│   └── config/
│       └── config.go
│
├── migrations/
│   ├── 001_create_users.up.sql
│   ├── 001_create_users.down.sql
│   ├── 002_create_products.up.sql
│   └── 002_create_products.down.sql
│
├── pkg/
│   ├── validator/
│   │   └── validator.go
│   └── logger/
│       └── logger.go
│
├── go.mod
├── go.sum
└── Makefile
```
