# MVC (Model-View-Controller)

MVC is the most fundamental architectural pattern separating an application into three components:
- **Model** — Data and business logic
- **View** — User interface presentation
- **Controller** — Handles input and coordinates between Model and View

---

## Laravel

```
app/
├── Http/
│   ├── Controllers/                        # Controllers
│   │   ├── Web/
│   │   │   ├── HomeController.php
│   │   │   ├── UserController.php
│   │   │   ├── OrderController.php
│   │   │   ├── ProductController.php
│   │   │   ├── DashboardController.php
│   │   │   └── ProfileController.php
│   │   └── Api/
│   │       ├── UserApiController.php
│   │       ├── OrderApiController.php
│   │       └── ProductApiController.php
│   ├── Requests/
│   │   ├── StoreUserRequest.php
│   │   ├── UpdateUserRequest.php
│   │   ├── StoreOrderRequest.php
│   │   └── StoreProductRequest.php
│   ├── Resources/
│   │   ├── UserResource.php
│   │   └── OrderResource.php
│   └── Middleware/
│       ├── Authenticate.php
│       └── VerifyAdmin.php
│
├── Models/                                 # Models
│   ├── User.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Product.php
│   ├── Category.php
│   ├── Payment.php
│   └── Traits/
│       ├── HasSlug.php
│       └── Searchable.php
│
├── Events/
│   └── OrderPlaced.php
├── Listeners/
│   └── SendOrderNotification.php
├── Mail/
│   └── OrderConfirmation.php
├── Notifications/
│   └── OrderStatusChanged.php
└── Providers/

resources/
├── views/                                  # Views
│   ├── layouts/
│   │   ├── app.blade.php
│   │   └── admin.blade.php
│   ├── components/
│   │   ├── navbar.blade.php
│   │   ├── sidebar.blade.php
│   │   └── alert.blade.php
│   ├── users/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   ├── orders/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── create.blade.php
│   ├── products/
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   ├── dashboard/
│   │   └── index.blade.php
│   └── auth/
│       ├── login.blade.php
│       └── register.blade.php
├── js/
└── css/

routes/
├── web.php
└── api.php
database/
├── migrations/
├── seeders/
└── factories/
config/
```

---

## Symfony

```
src/
├── Controller/                             # Controllers
│   ├── Web/
│   │   ├── HomeController.php
│   │   ├── UserController.php
│   │   ├── OrderController.php
│   │   ├── ProductController.php
│   │   └── DashboardController.php
│   └── Api/
│       ├── UserApiController.php
│       ├── OrderApiController.php
│       └── ProductApiController.php
│
├── Entity/                                 # Models (Doctrine entities)
│   ├── User.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Product.php
│   ├── Category.php
│   └── Payment.php
│
├── Repository/
│   ├── UserRepository.php
│   ├── OrderRepository.php
│   └── ProductRepository.php
│
├── Form/
│   ├── UserType.php
│   ├── OrderType.php
│   └── ProductType.php
│
├── Service/
│   ├── OrderService.php
│   └── PaymentService.php
│
├── EventSubscriber/
│   └── ExceptionSubscriber.php
│
├── Security/
│   └── Voter/
│       └── OrderVoter.php
│
└── Twig/
    └── Extension/
        └── AppExtension.php

templates/                                  # Views (Twig)
├── base.html.twig
├── user/
│   ├── index.html.twig
│   ├── show.html.twig
│   ├── create.html.twig
│   └── edit.html.twig
├── order/
│   ├── index.html.twig
│   ├── show.html.twig
│   └── create.html.twig
├── product/
│   ├── index.html.twig
│   ├── show.html.twig
│   └── edit.html.twig
├── dashboard/
│   └── index.html.twig
├── components/
│   ├── navbar.html.twig
│   └── sidebar.html.twig
└── auth/
    ├── login.html.twig
    └── register.html.twig

config/
├── routes/
│   ├── web.yaml
│   └── api.yaml
├── packages/
└── services.yaml
public/
├── css/
└── js/
migrations/
```

---

## Spring Boot (Java / Spring MVC)

```
src/main/java/com/example/app/
├── controller/                             # Controllers
│   ├── web/
│   │   ├── HomeController.java
│   │   ├── UserController.java
│   │   ├── OrderController.java
│   │   ├── ProductController.java
│   │   └── DashboardController.java
│   ├── api/
│   │   ├── UserApiController.java
│   │   ├── OrderApiController.java
│   │   └── ProductApiController.java
│   └── advice/
│       └── GlobalExceptionHandler.java
│
├── model/                                  # Models (JPA entities)
│   ├── User.java
│   ├── Order.java
│   ├── OrderItem.java
│   ├── Product.java
│   ├── Category.java
│   └── Payment.java
│
├── repository/
│   ├── UserRepository.java
│   ├── OrderRepository.java
│   └── ProductRepository.java
│
├── service/
│   ├── UserService.java
│   ├── OrderService.java
│   └── ProductService.java
│
├── dto/
│   ├── request/
│   │   ├── CreateUserRequest.java
│   │   └── PlaceOrderRequest.java
│   └── response/
│       ├── UserResponse.java
│       └── OrderResponse.java
│
├── mapper/
│   ├── UserMapper.java
│   └── OrderMapper.java
│
├── config/
│   ├── SecurityConfig.java
│   └── WebMvcConfig.java
│
└── exception/
    ├── ResourceNotFoundException.java
    └── BusinessException.java

src/main/resources/
├── templates/                              # Views (Thymeleaf)
│   ├── layout/
│   │   └── main.html
│   ├── fragments/
│   │   ├── navbar.html
│   │   └── sidebar.html
│   ├── user/
│   │   ├── list.html
│   │   ├── detail.html
│   │   ├── create.html
│   │   └── edit.html
│   ├── order/
│   │   ├── list.html
│   │   ├── detail.html
│   │   └── create.html
│   ├── product/
│   │   ├── list.html
│   │   └── detail.html
│   └── auth/
│       ├── login.html
│       └── register.html
├── static/
│   ├── css/
│   └── js/
├── application.yml
└── db/migration/
```

---

## Golang

```
project/
├── cmd/
│   └── web/
│       └── main.go
│
├── internal/
│   ├── handler/                            # Controllers (handlers)
│   │   ├── home_handler.go
│   │   ├── user_handler.go
│   │   ├── order_handler.go
│   │   ├── product_handler.go
│   │   ├── api/
│   │   │   ├── user_api_handler.go
│   │   │   ├── order_api_handler.go
│   │   │   └── product_api_handler.go
│   │   └── middleware/
│   │       ├── auth.go
│   │       └── logging.go
│   │
│   ├── model/                              # Models
│   │   ├── user.go
│   │   ├── order.go
│   │   ├── order_item.go
│   │   ├── product.go
│   │   ├── category.go
│   │   └── payment.go
│   │
│   ├── repository/
│   │   ├── user_repository.go
│   │   ├── order_repository.go
│   │   └── product_repository.go
│   │
│   ├── service/
│   │   ├── user_service.go
│   │   ├── order_service.go
│   │   └── product_service.go
│   │
│   ├── router/
│   │   └── router.go
│   │
│   └── config/
│       └── config.go
│
├── web/                                    # Views
│   ├── templates/
│   │   ├── layout/
│   │   │   └── base.html
│   │   ├── components/
│   │   │   ├── navbar.html
│   │   │   └── sidebar.html
│   │   ├── user/
│   │   │   ├── index.html
│   │   │   ├── show.html
│   │   │   ├── create.html
│   │   │   └── edit.html
│   │   ├── order/
│   │   │   ├── index.html
│   │   │   ├── show.html
│   │   │   └── create.html
│   │   ├── product/
│   │   │   ├── index.html
│   │   │   └── show.html
│   │   └── auth/
│   │       ├── login.html
│   │       └── register.html
│   └── static/
│       ├── css/
│       └── js/
│
├── migrations/
├── pkg/
├── go.mod
└── Makefile
```
