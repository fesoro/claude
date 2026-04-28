# Serverless Architecture (Lead)

Serverless architecture kodu cloud provider tərəfindən idarə olunan stateless, event-triggered function-larda işlədir.
Server idarəetməsi yoxdur, auto-scaling var, yalnız icra üçün ödəyirsən.

**Əsas anlayışlar:**
- **Function** — Tək məqsədli, stateless execution vahidi
- **Trigger/Event Source** — Function-u nə çağırır (HTTP, queue, schedule və s.)
- **API Gateway** — HTTP request-ləri function-lara yönləndirir
- **Event Bus** — Event-ləri function-lar arasında yönləndirir
- **Layer** — Function-lar arasında paylaşılan kod/dependency-lər
- **Cold Start** — İlk invocation zamanı container init gecikmə
- **Warm Start** — Container artıq hazırdır, gecikmə yoxdur
- **Provisioned Concurrency** — Cold start-ın qarşısını almaq üçün pre-warm

**Trade-offs:**
- Yaxşı: zero infra management, automatic scaling, pay-per-use, fault isolation per function
- Pis: cold start latency, vendor lock-in, debugging çətinliyi, max execution time limiti (15 min AWS)
- Nə vaxt istifadə etmə: latency-sensitive (< 100ms), long-running processes, complex state

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

---

## Cold Start Problemi və Həlləri

```
Cold Start nədir:
  Lambda function ilk dəfə (və ya uzun müddət idle sonra) çağırıldıqda
  AWS container-ı başlatmaq, runtime yükləmək, kodu init etmək lazımdır.
  PHP/Java: 1-5 saniyə | Go: < 100ms | Node.js: ~200ms

Həll 1 — Provisioned Concurrency (AWS):
  # serverless.yml
  functions:
    api:
      handler: handler.php
      provisionedConcurrency: 5   # 5 warm instance daima hazır

Həll 2 — Ping / Warmer:
  # Hər 5 dəqiqə scheduled event ilə function-u ping et
  functions:
    warmer:
      handler: warmer.go
      events:
        - schedule: rate(5 minutes)

Həll 3 — Minimize init code:
  # PHP: dependency yüklənməsini function çağırıldıqca yox, global scope-da et
  # Go: init() funksiyası — DB connection, config yüklənməsi global-da

Həll 4 — Go, Rust seç:
  # Go: ~50ms cold start
  # PHP (Bref): ~500ms-1s cold start
  # Java: 2-5s cold start (GraalVM Native → 100ms-ə endirir)
```

---

## VPC + Database Bağlantısı

```
VPC içindəki Lambda:
  Problem: Lambda VPC-yə qoşulduqda cold start +1-2s artır
  Problem: DB connection pool — hər function öz connection açır
           1000 concurrent Lambda = 1000 DB connection!

Həll — RDS Proxy:
  Lambda → RDS Proxy → PostgreSQL
  RDS Proxy connection pool-u idarə edir
  Lambda-dan gördüyü: normal DB connection

  # terraform/rds_proxy.tf
  resource "aws_db_proxy" "main" {
    name       = "laravel-proxy"
    engine_family = "POSTGRESQL"
    vpc_subnet_ids = var.private_subnet_ids
    auth { ... }
    target { db_instance_identifier = aws_db_instance.main.id }
  }

Həll — DynamoDB (VPC-siz):
  DynamoDB serverless-friendly: HTTP API, connection yoxdur
  Lambda → DynamoDB (no VPC needed)
```

---

## Cost Optimization

```
Pay-per-use hesablaması:
  AWS Lambda: $0.0000166667 per GB-second + $0.2 per 1M requests
  
  128MB, 200ms, 10M calls/ay:
  = 10M * 0.128GB * 0.2s * $0.0000166667 = $4.27/ay
  
  Vs EC2 t3.medium: ~$30/ay (always on)
  
  Break-even: ~1M requests/ay Lambda daha ucuzdur
  Yüksək traffic-də EC2/ECS daha ekonomikdir

Xərc azaltma qaydaları:
  - Memory right-sizing: çox memory = sürətli = ucuz (CPU ↑ ilə)
  - Timeout minimization: 30s default → 5s lazım olduqca
  - Avoid VPC əgər məcburi deyilsə (cold start + ENI xərc)
  - Batch processing: 1000 ayrı call əvəzinə 1 batch call
```
