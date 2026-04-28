# Decomposition Strategies (Lead)

Monolith-i microservices-ə necə **parçalayacağını** müəyyən edən strategiyalar.
Yanlış decomposition → distributed monolith. Düzgün decomposition → loose coupling, high cohesion.

**Əsas anlayışlar:**
- **Decompose by Business Capability** — Biznes funksiyasına görə bölmə
- **Decompose by Subdomain (DDD)** — DDD bounded context-lərə görə bölmə
- **Decompose by Team** — Conway's Law: arxitektura team strukturunu əks etdirir
- **Strangler Fig** — Tədricən köçürmə (12-strangler-fig.md-ə bax)
- **Database per Service** — Hər servis öz DB-sinə sahibdir
- **Service Coupling** — Afferent + Efferent coupling analizi

**Decomposition qaydaları:**
- Single Responsibility Principle
- High Cohesion — Bir servis bir business domain-i əhatə edir
- Loose Coupling — Servislər bir-birindən minimal asılıdır
- Autonomous — Servis digər servislərsiz deploy oluna bilər
- Data Ownership — Heç vaxt başqa servisin DB-sinə access etmə

---

## Business Capability Decomposition (Laravel)

```
project/
│
├── identity-service/                          # Capability: Who are you?
│   ├── app/
│   │   ├── Services/
│   │   │   ├── AuthenticationService.php
│   │   │   ├── AuthorizationService.php
│   │   │   └── UserManagementService.php
│   │   └── Models/
│   │       ├── User.php
│   │       └── Role.php
│   └── database/                             # Owns: users, roles tables
│
├── catalog-service/                           # Capability: What do we sell?
│   ├── app/
│   │   ├── Services/
│   │   │   ├── ProductCatalogService.php
│   │   │   ├── CategoryService.php
│   │   │   └── PricingService.php
│   │   └── Models/
│   │       ├── Product.php
│   │       └── Category.php
│   └── database/                             # Owns: products, categories
│
├── inventory-service/                         # Capability: How much do we have?
│   ├── app/
│   │   ├── Services/
│   │   │   └── StockManagementService.php
│   │   └── Models/
│   │       └── StockItem.php
│   └── database/                             # Owns: inventory table
│
├── ordering-service/                          # Capability: Taking orders
│   ├── app/
│   │   ├── Services/
│   │   │   ├── OrderService.php
│   │   │   └── CartService.php
│   │   └── Models/
│   │       ├── Order.php
│   │       └── Cart.php
│   └── database/                             # Owns: orders, carts
│
├── payment-service/                           # Capability: Taking money
│   ├── app/
│   │   └── Services/
│   │       └── PaymentService.php
│   └── database/                             # Owns: payments, invoices
│
├── shipping-service/                          # Capability: Sending goods
│   ├── app/
│   │   └── Services/
│   │       └── ShippingService.php
│   └── database/                             # Owns: shipments
│
└── notification-service/                      # Capability: Telling customers
    └── app/
        └── Services/
            └── NotificationService.php
```

---

## DDD Subdomain Decomposition (Spring Boot)

```
project/
│
├── core-domain/                               # Core — Competitive advantage
│   ├── ordering/                             # Core subdomain: most complex logic
│   │   └── ordering-service/
│   │       ├── src/main/java/com/example/ordering/
│   │       │   ├── domain/
│   │       │   │   ├── aggregate/
│   │       │   │   │   └── Order.java
│   │       │   │   └── service/
│   │       │   │       └── OrderPricingDomainService.java
│   │       │   └── application/
│   │       └── database/                     # Owns: orders schema
│   │
│   └── pricing/                              # Core subdomain: pricing strategy
│       └── pricing-service/
│           ├── src/main/java/com/example/pricing/
│           │   └── domain/
│           │       ├── PricingEngine.java
│           │       └── DiscountPolicy.java
│           └── database/
│
├── supporting-domain/                         # Supporting — Important but not unique
│   ├── inventory/
│   │   └── inventory-service/
│   │       └── src/main/java/com/example/inventory/
│   │
│   └── shipping/
│       └── shipping-service/
│           └── src/main/java/com/example/shipping/
│
└── generic-domain/                            # Generic — Buy/use existing solution
    ├── identity/                              # → Use Keycloak/Auth0
    │   └── identity-service/                 # Thin wrapper around external IAM
    │       └── src/main/java/com/example/identity/
    │
    └── notification/                          # → Use SendGrid/Twilio
        └── notification-service/
            └── src/main/java/com/example/notification/
```

---

## Golang — Decomposition Decision Matrix

```
project/
├── decomposition-analysis/
│   ├── coupling-matrix.md                    # Service coupling map
│   │   # Example:
│   │   # ordering ←→ inventory (sync call) — HIGH coupling: BAD
│   │   # ordering → notification (async event) — LOW coupling: GOOD
│   │
│   ├── data-ownership.md                     # Which service owns which data
│   │   # BAD: ordering-service queries inventory DB directly
│   │   # GOOD: ordering-service calls inventory-service API
│   │
│   └── team-topology.md                      # Conway's Law alignment
│
├── user-service/
│   ├── cmd/main.go
│   ├── internal/
│   └── go.mod
│
├── product-service/
│   ├── cmd/main.go
│   ├── internal/
│   └── go.mod
│
├── order-service/
│   ├── cmd/main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   └── order_handler.go
│   │   ├── service/
│   │   │   └── order_service.go
│   │   └── client/
│   │       ├── product_client.go             # HTTP call to product-service
│   │       └── inventory_client.go           # HTTP call to inventory-service
│   └── go.mod
│
└── shared/
    └── pkg/
        ├── eventbus/                          # Async communication
        └── httpclient/                        # Resilient HTTP client
```

---

## Decomposition Anti-Patterns

```
❌ YANLIŞ — Database coupling:
   Order Service → directly queries products table (inventory DB)
   Result: Distributed Monolith

❌ YANLIŞ — Synchronous chain:
   API → Order → Inventory → Product → Pricing → Payment
   Result: Cascading failures, high latency

❌ YANLIŞ — Shared library with business logic:
   common-lib contains OrderStatusEnum, PricingLogic
   Result: Teams cannot deploy independently

✅ DÜZGÜN — Event-driven communication:
   Order placed → event published
   Inventory service listens → decrements stock
   Notification service listens → sends confirmation

✅ DÜZGÜN — Database per service:
   order-service: orders_db (PostgreSQL)
   product-service: products_db (PostgreSQL)
   search-service: Elasticsearch
   session-service: Redis

✅ DÜZGÜN — API-first communication:
   Order service calls Inventory service via REST/gRPC
   Never directly accesses database
```
