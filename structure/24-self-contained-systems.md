# Self-Contained Systems (Architect)

SCS (Self-Contained Systems) hər bir **vertikal slice**-ı tam müstəqil bir web tətbiqinə çevirir.
Microservices-dən fərqli olaraq, hər SCS öz UI-ını da özündə saxlayır.
Alman arxitekt Otto Group tərəfindən populyarlaşdırılmışdır.

**Əsas anlayışlar:**
- **SCS** — Öz UI + Backend + DB-si olan, müstəqil deploy olunan tam tətbiq
- **Autonomous Team** — Hər SCS ayrı team tərəfindən idarə olunur
- **Minimal Communication** — SCS-lər arasında yalnız async events (mümkün qədər az sync)
- **UI Integration** — Fragment-lər (iframe, web components, SSI) ilə birləşir
- **No Shared DB** — Hər SCS öz data storage-ına sahibdir

**Microservices ilə fərqi:**
- Microservices: back-end only, mərkəzləşdirilmiş UI
- SCS: full-stack vertical (UI + API + DB), hər team tam feature deliver edir
- SCS daha az operasional mürəkkəblik (az servis, amma hər biri daha böyük)

---

## Laravel (E-Commerce SCS)

```
project/
│
├── scs-catalog/                               # Product Catalog SCS
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── ProductController.php          # Backend API
│   │   │   └── CategoryController.php
│   │   ├── Services/
│   │   │   └── ProductService.php
│   │   └── Models/
│   │       ├── Product.php
│   │       └── Category.php
│   ├── resources/
│   │   └── views/                            # SCS owns its own UI
│   │       ├── products/
│   │       │   ├── index.blade.php
│   │       │   └── show.blade.php
│   │       └── components/
│   │           └── product-card.blade.php    # Fragment for composite UI
│   ├── database/
│   │   └── migrations/                       # Own DB schema
│   ├── routes/
│   │   ├── web.php
│   │   └── api.php
│   └── Dockerfile
│
├── scs-checkout/                              # Checkout & Orders SCS
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── CartController.php
│   │   │   └── OrderController.php
│   │   ├── Services/
│   │   │   └── OrderService.php
│   │   ├── Models/
│   │   │   ├── Order.php
│   │   │   └── Cart.php
│   │   └── Events/
│   │       └── OrderPlaced.php               # Async event to other SCS
│   ├── resources/views/
│   │   ├── cart/
│   │   └── checkout/
│   ├── database/
│   └── Dockerfile
│
├── scs-account/                               # User Account SCS
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── AuthController.php
│   │   │   └── ProfileController.php
│   │   └── Models/
│   │       └── User.php
│   ├── resources/views/
│   │   ├── auth/
│   │   └── profile/
│   ├── database/
│   └── Dockerfile
│
├── scs-search/                                # Search SCS
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   └── SearchController.php
│   │   └── Services/
│   │       └── SearchService.php
│   ├── resources/views/
│   │   └── search/
│   ├── database/                             # Elasticsearch or search-optimized DB
│   └── Dockerfile
│
├── frontend-shell/                            # Composite UI — assembles SCS fragments
│   ├── nginx.conf                            # SSI (Server-Side Includes) assembly
│   └── resources/views/
│       └── layout.html                       # Includes SCS fragments via SSI/iframe
│
└── infrastructure/
    ├── docker-compose.yml
    └── nginx/
        └── composite.conf                    # Routes to correct SCS
```

---

## Spring Boot (Java)

```
project/
│
├── scs-catalog/
│   ├── src/main/java/com/example/catalog/
│   │   ├── CatalogApplication.java
│   │   ├── controller/
│   │   │   ├── ProductController.java         # REST API
│   │   │   └── CategoryController.java
│   │   ├── service/
│   │   │   └── ProductService.java
│   │   ├── repository/
│   │   │   └── ProductRepository.java
│   │   └── entity/
│   │       └── Product.java
│   ├── src/main/resources/
│   │   ├── templates/                         # Thymeleaf UI templates
│   │   │   ├── products/
│   │   │   │   ├── list.html
│   │   │   │   └── detail.html
│   │   │   └── fragments/
│   │   │       └── product-card.html          # Fragment for SSI inclusion
│   │   └── application.yml
│   └── Dockerfile
│
├── scs-checkout/
│   ├── src/main/java/com/example/checkout/
│   │   ├── CheckoutApplication.java
│   │   ├── controller/
│   │   ├── service/
│   │   ├── event/
│   │   │   └── OrderPlacedEvent.java         # Kafka/RabbitMQ event
│   │   └── messaging/
│   │       └── OrderEventPublisher.java
│   ├── src/main/resources/
│   │   └── templates/checkout/
│   └── Dockerfile
│
├── scs-account/
│   ├── src/main/java/com/example/account/
│   │   ├── AccountApplication.java
│   │   └── (auth, profile management)
│   ├── src/main/resources/templates/
│   └── Dockerfile
│
└── frontend-shell/
    └── nginx.conf                             # SSI composite assembly
```

---

## Golang

```
project/
│
├── scs-catalog/
│   ├── cmd/main.go
│   ├── internal/
│   │   ├── handler/
│   │   │   ├── product_handler.go             # REST + HTML handlers
│   │   │   └── fragment_handler.go            # Returns HTML fragments for SSI
│   │   ├── service/
│   │   │   └── product_service.go
│   │   └── repository/
│   │       └── postgres_repo.go
│   ├── templates/
│   │   ├── products/
│   │   │   ├── list.html
│   │   │   └── detail.html
│   │   └── fragments/
│   │       └── product-card.html
│   └── go.mod
│
├── scs-checkout/
│   ├── cmd/main.go
│   ├── internal/
│   │   ├── handler/
│   │   ├── service/
│   │   └── publisher/
│   │       └── order_event.go                # Kafka publish
│   └── go.mod
│
├── scs-account/
│   └── (auth, profile)
│
└── infrastructure/
    ├── docker-compose.yml
    └── nginx/
        └── ssi-composite.conf               # SSI assembly
```

---

## UI Integration Nümunəsi (SSI)

```html
<!-- frontend-shell/layout.html -->
<!-- Nginx SSI: hər SCS öz fragment-ini render edir -->

<html>
<head>
  <!-- Header from account SCS -->
  <!--#include virtual="http://scs-account/fragments/header" -->
</head>
<body>
  <nav>
    <!--#include virtual="http://scs-account/fragments/nav" -->
  </nav>

  <main>
    <!-- Content comes from whichever SCS owns the current route -->
    <!--#include virtual="$request_uri" -->
  </main>

  <aside>
    <!-- Mini cart from checkout SCS -->
    <!--#include virtual="http://scs-checkout/fragments/mini-cart" -->
  </aside>
</body>
</html>
```
