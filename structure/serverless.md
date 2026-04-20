# Serverless Architecture

Serverless architecture kodu cloud provider tərəfindən idarə olunan stateless, event-triggered function-larda işlədir.
Server idarəetməsi yoxdur, auto-scaling var, yalnız icra üçün ödəyirsən.

**Əsas anlayışlar:**
- **Function** — Tək məqsədli, stateless execution vahidi
- **Trigger/Event Source** — Function-u nə çağırır (HTTP, queue, schedule və s.)
- **API Gateway** — HTTP request-ləri function-lara yönləndirir
- **Event Bus** — Event-ləri function-lar arasında yönləndirir
- **Layer** — Function-lar arasında paylaşılan kod/dependency-lər

---

## Laravel (Laravel Vapor / Bref)

```
project/
├── app/
│   ├── Functions/                          # Lambda function handlers
│   │   ├── Http/
│   │   │   ├── CreateUser.php
│   │   │   ├── GetUser.php
│   │   │   ├── ListUsers.php
│   │   │   ├── PlaceOrder.php
│   │   │   ├── GetOrder.php
│   │   │   └── ProcessWebhook.php
│   │   ├── Queue/
│   │   │   ├── SendWelcomeEmail.php
│   │   │   ├── ProcessPayment.php
│   │   │   └── GenerateReport.php
│   │   ├── Schedule/
│   │   │   ├── CleanupExpiredSessions.php
│   │   │   └── GenerateDailyReport.php
│   │   └── Event/
│   │       ├── HandleS3Upload.php
│   │       └── HandleSnsNotification.php
│   │
│   ├── Services/
│   │   ├── UserService.php
│   │   ├── OrderService.php
│   │   └── PaymentService.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   └── Order.php
│   │
│   └── Shared/
│       ├── Middleware/
│       │   ├── AuthMiddleware.php
│       │   └── CorsMiddleware.php
│       └── Helpers/
│           └── ResponseHelper.php
│
├── serverless.yml                          # Serverless framework config
├── vapor.yml                               # Laravel Vapor config
├── composer.json
└── Dockerfile
```

---

## Spring Boot (Java / AWS Lambda)

```
project/
├── functions/
│   ├── create-user/
│   │   ├── src/main/java/com/example/
│   │   │   ├── CreateUserHandler.java
│   │   │   ├── CreateUserRequest.java
│   │   │   └── CreateUserResponse.java
│   │   └── build.gradle
│   │
│   ├── get-user/
│   │   ├── src/main/java/com/example/
│   │   │   ├── GetUserHandler.java
│   │   │   └── GetUserResponse.java
│   │   └── build.gradle
│   │
│   ├── place-order/
│   │   ├── src/main/java/com/example/
│   │   │   ├── PlaceOrderHandler.java
│   │   │   ├── PlaceOrderRequest.java
│   │   │   └── PlaceOrderResponse.java
│   │   └── build.gradle
│   │
│   ├── process-payment/
│   │   ├── src/main/java/com/example/
│   │   │   └── ProcessPaymentHandler.java
│   │   └── build.gradle
│   │
│   └── send-notification/
│       ├── src/main/java/com/example/
│       │   └── SendNotificationHandler.java
│       └── build.gradle
│
├── shared/
│   ├── common/
│   │   ├── src/main/java/com/example/common/
│   │   │   ├── service/
│   │   │   │   ├── UserService.java
│   │   │   │   └── OrderService.java
│   │   │   ├── repository/
│   │   │   │   └── DynamoDbUserRepository.java
│   │   │   ├── model/
│   │   │   │   ├── User.java
│   │   │   │   └── Order.java
│   │   │   └── util/
│   │   │       └── ResponseHelper.java
│   │   └── build.gradle
│   └── layer/                              # Lambda layer
│       └── build.gradle
│
├── infrastructure/
│   ├── template.yaml                       # AWS SAM template
│   ├── samconfig.toml
│   └── api-gateway/
│       └── api.yaml
│
├── build.gradle                            # Root build
└── settings.gradle
```

---

## Golang (AWS Lambda)

```
project/
├── functions/
│   ├── create-user/
│   │   └── main.go                        # Lambda handler
│   ├── get-user/
│   │   └── main.go
│   ├── list-users/
│   │   └── main.go
│   ├── place-order/
│   │   └── main.go
│   ├── get-order/
│   │   └── main.go
│   ├── process-payment/
│   │   └── main.go
│   ├── send-notification/
│   │   └── main.go
│   ├── handle-s3-upload/
│   │   └── main.go
│   └── daily-cleanup/
│       └── main.go
│
├── internal/
│   ├── service/
│   │   ├── user_service.go
│   │   ├── order_service.go
│   │   └── payment_service.go
│   │
│   ├── repository/
│   │   ├── user_repository.go             # Interface
│   │   ├── order_repository.go
│   │   └── dynamodb/
│   │       ├── user_repo.go
│   │       └── order_repo.go
│   │
│   ├── model/
│   │   ├── user.go
│   │   ├── order.go
│   │   └── payment.go
│   │
│   └── shared/
│       ├── middleware/
│       │   └── auth.go
│       ├── response/
│       │   └── api_response.go
│       └── config/
│           └── config.go
│
├── infrastructure/
│   ├── template.yaml                      # AWS SAM / CloudFormation
│   ├── serverless.yml                     # OR Serverless Framework
│   └── terraform/
│       ├── main.tf
│       ├── lambda.tf
│       ├── api_gateway.tf
│       ├── dynamodb.tf
│       └── variables.tf
│
├── scripts/
│   ├── build.sh
│   └── deploy.sh
│
├── go.mod
├── go.sum
└── Makefile
```
