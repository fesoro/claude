# Service-Oriented Architecture (SOA)

SOA software-i aydın təyin edilmiş contract-lar vasitəsilə ünsiyyət quran coarse-grained servislərin toplusu kimi təşkil edir.
Microservices-dən fərqli olaraq, SOA servisləri daha böyükdür, daha çox infrastructure paylaşır
və ünsiyyət üçün tez-tez Enterprise Service Bus (ESB) istifadə edir.

**Əsas anlayışlar:**
- **Service** — Coarse-grained, yenidən istifadə oluna bilən business bacarığı
- **Service Contract** — Servisin interface-ini təyin edən WSDL/OpenAPI
- **ESB (Enterprise Service Bus)** — Mərkəzi kommunikasiya onurğası
- **Service Registry** — Service discovery və metadata
- **Orchestration** — Mərkəzi koordinator servis qarşılıqlı əlaqələrini idarə edir
- **Choreography** — Servislər event-lərə müstəqil reaksiya verir

---

## Laravel

```
project/
├── services/
│   ├── identity-service/                   # Identity & Access Management
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   └── Controllers/
│   │   │   │       ├── AuthController.php
│   │   │   │       ├── UserController.php
│   │   │   │       └── RoleController.php
│   │   │   ├── Services/
│   │   │   │   ├── AuthenticationService.php
│   │   │   │   ├── AuthorizationService.php
│   │   │   │   └── UserManagementService.php
│   │   │   ├── Models/
│   │   │   │   ├── User.php
│   │   │   │   └── Role.php
│   │   │   └── Contracts/
│   │   │       └── IdentityServiceContract.php
│   │   ├── routes/
│   │   ├── database/
│   │   ├── composer.json
│   │   └── Dockerfile
│   │
│   ├── commerce-service/                   # E-Commerce (Orders + Products)
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   └── Controllers/
│   │   │   │       ├── OrderController.php
│   │   │   │       ├── ProductController.php
│   │   │   │       └── CartController.php
│   │   │   ├── Services/
│   │   │   │   ├── OrderService.php
│   │   │   │   ├── ProductCatalogService.php
│   │   │   │   ├── CartService.php
│   │   │   │   └── PricingService.php
│   │   │   ├── Models/
│   │   │   └── Contracts/
│   │   │       └── CommerceServiceContract.php
│   │   ├── routes/
│   │   ├── database/
│   │   ├── composer.json
│   │   └── Dockerfile
│   │
│   ├── billing-service/                    # Billing & Payments
│   │   ├── app/
│   │   │   ├── Http/
│   │   │   │   └── Controllers/
│   │   │   │       ├── PaymentController.php
│   │   │   │       └── InvoiceController.php
│   │   │   ├── Services/
│   │   │   │   ├── PaymentProcessingService.php
│   │   │   │   ├── InvoiceService.php
│   │   │   │   └── Gateway/
│   │   │   │       ├── StripeGateway.php
│   │   │   │       └── PayPalGateway.php
│   │   │   └── Contracts/
│   │   │       └── BillingServiceContract.php
│   │   ├── composer.json
│   │   └── Dockerfile
│   │
│   └── notification-service/
│       ├── app/
│       │   ├── Services/
│       │   │   ├── NotificationOrchestrator.php
│       │   │   ├── EmailService.php
│       │   │   ├── SmsService.php
│       │   │   └── PushService.php
│       │   └── Contracts/
│       │       └── NotificationServiceContract.php
│       ├── composer.json
│       └── Dockerfile
│
├── esb/                                    # Enterprise Service Bus
│   ├── app/
│   │   ├── Router/
│   │   │   └── MessageRouter.php
│   │   ├── Transformer/
│   │   │   ├── DataTransformer.php
│   │   │   └── MessageTranslator.php
│   │   ├── Orchestrator/
│   │   │   ├── OrderOrchestrator.php
│   │   │   └── RegistrationOrchestrator.php
│   │   └── Registry/
│   │       └── ServiceRegistry.php
│   ├── composer.json
│   └── Dockerfile
│
├── shared/
│   ├── contracts/                          # Service contracts
│   │   ├── identity-contract/
│   │   │   └── openapi.yaml
│   │   ├── commerce-contract/
│   │   │   └── openapi.yaml
│   │   └── billing-contract/
│   │       └── openapi.yaml
│   ├── sdk/
│   │   ├── identity-sdk/
│   │   ├── commerce-sdk/
│   │   └── billing-sdk/
│   └── message-schemas/
│       ├── events/
│       └── commands/
│
└── infrastructure/
    ├── docker-compose.yml
    └── kubernetes/
```

---

## Spring Boot (Java)

```
project/
├── identity-service/
│   ├── src/main/java/com/example/identity/
│   │   ├── IdentityServiceApplication.java
│   │   ├── controller/
│   │   │   ├── AuthController.java
│   │   │   └── UserController.java
│   │   ├── service/
│   │   │   ├── AuthenticationService.java
│   │   │   ├── AuthorizationService.java
│   │   │   └── UserManagementService.java
│   │   ├── repository/
│   │   ├── entity/
│   │   ├── dto/
│   │   └── config/
│   ├── src/main/resources/
│   │   └── application.yml
│   ├── build.gradle
│   └── Dockerfile
│
├── commerce-service/
│   ├── src/main/java/com/example/commerce/
│   │   ├── CommerceServiceApplication.java
│   │   ├── controller/
│   │   │   ├── OrderController.java
│   │   │   ├── ProductController.java
│   │   │   └── CartController.java
│   │   ├── service/
│   │   │   ├── OrderService.java
│   │   │   ├── ProductCatalogService.java
│   │   │   └── PricingService.java
│   │   ├── repository/
│   │   ├── entity/
│   │   ├── client/                        # Feign clients to other services
│   │   │   ├── IdentityClient.java
│   │   │   └── BillingClient.java
│   │   └── config/
│   ├── build.gradle
│   └── Dockerfile
│
├── billing-service/
│   ├── src/main/java/com/example/billing/
│   │   ├── BillingServiceApplication.java
│   │   ├── controller/
│   │   │   ├── PaymentController.java
│   │   │   └── InvoiceController.java
│   │   ├── service/
│   │   │   ├── PaymentProcessingService.java
│   │   │   ├── InvoiceService.java
│   │   │   └── gateway/
│   │   │       ├── PaymentGateway.java
│   │   │       ├── StripeGateway.java
│   │   │       └── PayPalGateway.java
│   │   ├── repository/
│   │   └── config/
│   ├── build.gradle
│   └── Dockerfile
│
├── notification-service/
│   ├── src/main/java/com/example/notification/
│   │   ├── service/
│   │   │   ├── NotificationOrchestrator.java
│   │   │   ├── EmailService.java
│   │   │   └── SmsService.java
│   │   ├── consumer/
│   │   │   └── EventConsumer.java
│   │   └── template/
│   ├── build.gradle
│   └── Dockerfile
│
├── esb/                                    # Spring Integration ESB
│   ├── src/main/java/com/example/esb/
│   │   ├── EsbApplication.java
│   │   ├── router/
│   │   │   └── MessageRouter.java
│   │   ├── transformer/
│   │   │   └── MessageTransformer.java
│   │   ├── orchestrator/
│   │   │   ├── OrderOrchestrator.java
│   │   │   └── RegistrationOrchestrator.java
│   │   └── config/
│   │       └── IntegrationConfig.java
│   ├── build.gradle
│   └── Dockerfile
│
├── shared/
│   ├── common-lib/
│   │   ├── src/main/java/com/example/common/
│   │   │   ├── dto/
│   │   │   ├── event/
│   │   │   └── exception/
│   │   └── build.gradle
│   ├── contracts/
│   │   ├── identity-api.yaml
│   │   ├── commerce-api.yaml
│   │   └── billing-api.yaml
│   └── proto/
│
└── infrastructure/
    ├── docker-compose.yml
    └── kubernetes/
```

---

## Golang

```
project/
├── identity-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   ├── auth_handler.go
│   │   │   └── user_handler.go
│   │   ├── service/
│   │   │   ├── authentication.go
│   │   │   ├── authorization.go
│   │   │   └── user_management.go
│   │   ├── repository/
│   │   ├── model/
│   │   ├── router/
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── commerce-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   ├── order_handler.go
│   │   │   ├── product_handler.go
│   │   │   └── cart_handler.go
│   │   ├── service/
│   │   │   ├── order_service.go
│   │   │   ├── product_catalog.go
│   │   │   └── pricing.go
│   │   ├── client/
│   │   │   ├── identity_client.go
│   │   │   └── billing_client.go
│   │   ├── repository/
│   │   ├── model/
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── billing-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── handler/
│   │   ├── service/
│   │   │   ├── payment_processing.go
│   │   │   ├── invoice.go
│   │   │   └── gateway/
│   │   │       ├── gateway.go
│   │   │       ├── stripe.go
│   │   │       └── paypal.go
│   │   ├── repository/
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── notification-service/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── service/
│   │   │   ├── orchestrator.go
│   │   │   ├── email.go
│   │   │   └── sms.go
│   │   ├── consumer/
│   │   │   └── event_consumer.go
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── esb/
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── router/
│   │   │   └── message_router.go
│   │   ├── transformer/
│   │   │   └── transformer.go
│   │   ├── orchestrator/
│   │   │   ├── order_orchestrator.go
│   │   │   └── registration_orchestrator.go
│   │   ├── registry/
│   │   │   └── service_registry.go
│   │   └── config/
│   ├── go.mod
│   └── Dockerfile
│
├── shared/
│   ├── pkg/
│   │   ├── httpclient/
│   │   ├── messaging/
│   │   └── logger/
│   ├── contracts/
│   │   ├── identity-api.yaml
│   │   └── commerce-api.yaml
│   ├── proto/
│   └── go.mod
│
└── infrastructure/
    ├── docker-compose.yml
    └── kubernetes/
```
