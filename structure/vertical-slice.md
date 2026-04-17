# Vertical Slice Architecture

Vertical Slice Architecture (Jimmy Bogard) organizes code by feature instead of by technical layer.
Each feature/slice contains everything it needs: handler, validation, data access, response.

**Key concepts:**
- **Slice/Feature** — A complete vertical cut through all layers for one use case
- **No shared abstractions** — Each slice owns its full implementation
- **Minimal coupling** — Slices don't depend on each other
- **MediatR/CQRS** — Often used to dispatch requests to slices

**Advantage:** Changing a feature only touches one folder. No shotgun surgery across layers.

---

## Laravel

```
app/
├── Features/                               # Each feature is a vertical slice
│   ├── User/
│   │   ├── CreateUser/
│   │   │   ├── CreateUserController.php    # Endpoint
│   │   │   ├── CreateUserRequest.php       # Validation
│   │   │   ├── CreateUserAction.php        # Business logic
│   │   │   ├── CreateUserResponse.php      # Response
│   │   │   └── CreateUserTest.php          # Test
│   │   │
│   │   ├── GetUser/
│   │   │   ├── GetUserController.php
│   │   │   ├── GetUserAction.php
│   │   │   ├── GetUserResponse.php
│   │   │   └── GetUserTest.php
│   │   │
│   │   ├── UpdateUser/
│   │   │   ├── UpdateUserController.php
│   │   │   ├── UpdateUserRequest.php
│   │   │   ├── UpdateUserAction.php
│   │   │   └── UpdateUserTest.php
│   │   │
│   │   ├── ListUsers/
│   │   │   ├── ListUsersController.php
│   │   │   ├── ListUsersAction.php
│   │   │   ├── ListUsersResponse.php
│   │   │   ├── UserFilter.php
│   │   │   └── ListUsersTest.php
│   │   │
│   │   └── DeleteUser/
│   │       ├── DeleteUserController.php
│   │       ├── DeleteUserAction.php
│   │       └── DeleteUserTest.php
│   │
│   ├── Order/
│   │   ├── PlaceOrder/
│   │   │   ├── PlaceOrderController.php
│   │   │   ├── PlaceOrderRequest.php
│   │   │   ├── PlaceOrderAction.php
│   │   │   ├── PlaceOrderResponse.php
│   │   │   └── PlaceOrderTest.php
│   │   │
│   │   ├── GetOrder/
│   │   │   ├── GetOrderController.php
│   │   │   ├── GetOrderAction.php
│   │   │   ├── GetOrderResponse.php
│   │   │   └── GetOrderTest.php
│   │   │
│   │   ├── CancelOrder/
│   │   │   ├── CancelOrderController.php
│   │   │   ├── CancelOrderAction.php
│   │   │   └── CancelOrderTest.php
│   │   │
│   │   └── ListOrdersByUser/
│   │       ├── ListOrdersByUserController.php
│   │       ├── ListOrdersByUserAction.php
│   │       └── ListOrdersByUserTest.php
│   │
│   ├── Payment/
│   │   ├── ProcessPayment/
│   │   │   ├── ProcessPaymentController.php
│   │   │   ├── ProcessPaymentRequest.php
│   │   │   ├── ProcessPaymentAction.php
│   │   │   └── ProcessPaymentTest.php
│   │   └── RefundPayment/
│   │       ├── RefundPaymentController.php
│   │       ├── RefundPaymentAction.php
│   │       └── RefundPaymentTest.php
│   │
│   └── Auth/
│       ├── Login/
│       │   ├── LoginController.php
│       │   ├── LoginRequest.php
│       │   ├── LoginAction.php
│       │   └── LoginTest.php
│       ├── Register/
│       │   ├── RegisterController.php
│       │   ├── RegisterRequest.php
│       │   ├── RegisterAction.php
│       │   └── RegisterTest.php
│       └── Logout/
│           ├── LogoutController.php
│           └── LogoutTest.php
│
├── Models/                                 # Shared Eloquent models
│   ├── User.php
│   ├── Order.php
│   └── Payment.php
│
├── Shared/                                 # Minimal shared code
│   ├── Middleware/
│   └── Exceptions/
│
routes/
├── api.php                                 # Imports feature routes
config/
database/
```

---

## Symfony

```
src/
├── Feature/
│   ├── User/
│   │   ├── CreateUser/
│   │   │   ├── CreateUserController.php
│   │   │   ├── CreateUserRequest.php
│   │   │   ├── CreateUserHandler.php
│   │   │   ├── CreateUserResponse.php
│   │   │   └── CreateUserTest.php
│   │   │
│   │   ├── GetUser/
│   │   │   ├── GetUserController.php
│   │   │   ├── GetUserHandler.php
│   │   │   ├── GetUserResponse.php
│   │   │   └── GetUserTest.php
│   │   │
│   │   ├── UpdateUser/
│   │   │   ├── UpdateUserController.php
│   │   │   ├── UpdateUserRequest.php
│   │   │   ├── UpdateUserHandler.php
│   │   │   └── UpdateUserTest.php
│   │   │
│   │   └── ListUsers/
│   │       ├── ListUsersController.php
│   │       ├── ListUsersHandler.php
│   │       ├── ListUsersResponse.php
│   │       └── ListUsersTest.php
│   │
│   ├── Order/
│   │   ├── PlaceOrder/
│   │   │   ├── PlaceOrderController.php
│   │   │   ├── PlaceOrderRequest.php
│   │   │   ├── PlaceOrderHandler.php
│   │   │   ├── PlaceOrderResponse.php
│   │   │   └── PlaceOrderTest.php
│   │   │
│   │   ├── GetOrder/
│   │   │   ├── GetOrderController.php
│   │   │   ├── GetOrderHandler.php
│   │   │   └── GetOrderTest.php
│   │   │
│   │   └── CancelOrder/
│   │       ├── CancelOrderController.php
│   │       ├── CancelOrderHandler.php
│   │       └── CancelOrderTest.php
│   │
│   ├── Payment/
│   │   ├── ProcessPayment/
│   │   └── RefundPayment/
│   │
│   └── Auth/
│       ├── Login/
│       └── Register/
│
├── Entity/                                 # Shared Doctrine entities
│   ├── User.php
│   ├── Order.php
│   └── Payment.php
│
├── Shared/
│   ├── EventSubscriber/
│   └── Security/
│
config/
├── routes/
│   └── features.yaml
└── services.yaml
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── feature/
│   ├── user/
│   │   ├── createuser/
│   │   │   ├── CreateUserController.java
│   │   │   ├── CreateUserRequest.java
│   │   │   ├── CreateUserHandler.java
│   │   │   ├── CreateUserResponse.java
│   │   │   └── CreateUserValidator.java
│   │   │
│   │   ├── getuser/
│   │   │   ├── GetUserController.java
│   │   │   ├── GetUserHandler.java
│   │   │   └── GetUserResponse.java
│   │   │
│   │   ├── updateuser/
│   │   │   ├── UpdateUserController.java
│   │   │   ├── UpdateUserRequest.java
│   │   │   └── UpdateUserHandler.java
│   │   │
│   │   ├── listusers/
│   │   │   ├── ListUsersController.java
│   │   │   ├── ListUsersHandler.java
│   │   │   ├── ListUsersResponse.java
│   │   │   └── UserFilter.java
│   │   │
│   │   └── deleteuser/
│   │       ├── DeleteUserController.java
│   │       └── DeleteUserHandler.java
│   │
│   ├── order/
│   │   ├── placeorder/
│   │   │   ├── PlaceOrderController.java
│   │   │   ├── PlaceOrderRequest.java
│   │   │   ├── PlaceOrderHandler.java
│   │   │   └── PlaceOrderResponse.java
│   │   │
│   │   ├── getorder/
│   │   │   ├── GetOrderController.java
│   │   │   ├── GetOrderHandler.java
│   │   │   └── GetOrderResponse.java
│   │   │
│   │   └── cancelorder/
│   │       ├── CancelOrderController.java
│   │       └── CancelOrderHandler.java
│   │
│   ├── payment/
│   │   ├── processpayment/
│   │   └── refundpayment/
│   │
│   └── auth/
│       ├── login/
│       └── register/
│
├── entity/                                 # Shared JPA entities
│   ├── User.java
│   ├── Order.java
│   └── Payment.java
│
├── shared/
│   ├── config/
│   │   └── SecurityConfig.java
│   └── exception/
│       └── GlobalExceptionHandler.java
│
src/main/resources/
└── application.yml

src/test/java/com/example/app/feature/
├── user/
│   ├── createuser/
│   │   └── CreateUserHandlerTest.java
│   └── getuser/
│       └── GetUserHandlerTest.java
├── order/
│   └── placeorder/
│       └── PlaceOrderHandlerTest.java
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
│   ├── feature/
│   │   ├── user/
│   │   │   ├── create_user/
│   │   │   │   ├── handler.go             # HTTP handler
│   │   │   │   ├── request.go             # Request DTO + validation
│   │   │   │   ├── action.go              # Business logic
│   │   │   │   ├── response.go            # Response DTO
│   │   │   │   └── handler_test.go
│   │   │   │
│   │   │   ├── get_user/
│   │   │   │   ├── handler.go
│   │   │   │   ├── action.go
│   │   │   │   ├── response.go
│   │   │   │   └── handler_test.go
│   │   │   │
│   │   │   ├── update_user/
│   │   │   │   ├── handler.go
│   │   │   │   ├── request.go
│   │   │   │   ├── action.go
│   │   │   │   └── handler_test.go
│   │   │   │
│   │   │   ├── list_users/
│   │   │   │   ├── handler.go
│   │   │   │   ├── action.go
│   │   │   │   ├── response.go
│   │   │   │   ├── filter.go
│   │   │   │   └── handler_test.go
│   │   │   │
│   │   │   └── delete_user/
│   │   │       ├── handler.go
│   │   │       ├── action.go
│   │   │       └── handler_test.go
│   │   │
│   │   ├── order/
│   │   │   ├── place_order/
│   │   │   │   ├── handler.go
│   │   │   │   ├── request.go
│   │   │   │   ├── action.go
│   │   │   │   ├── response.go
│   │   │   │   └── handler_test.go
│   │   │   │
│   │   │   ├── get_order/
│   │   │   │   ├── handler.go
│   │   │   │   ├── action.go
│   │   │   │   └── handler_test.go
│   │   │   │
│   │   │   └── cancel_order/
│   │   │       ├── handler.go
│   │   │       ├── action.go
│   │   │       └── handler_test.go
│   │   │
│   │   ├── payment/
│   │   │   ├── process_payment/
│   │   │   └── refund_payment/
│   │   │
│   │   └── auth/
│   │       ├── login/
│   │       └── register/
│   │
│   ├── model/                              # Shared models
│   │   ├── user.go
│   │   ├── order.go
│   │   └── payment.go
│   │
│   ├── shared/
│   │   ├── middleware/
│   │   │   └── auth.go
│   │   ├── database/
│   │   │   └── connection.go
│   │   └── config/
│   │       └── config.go
│   │
│   └── router/
│       └── router.go                       # Registers all feature routes
│
├── pkg/
├── go.mod
└── Makefile
```
