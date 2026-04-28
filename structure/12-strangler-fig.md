# Strangler Fig Pattern (Senior)

Legacy sistemdən müasir arxitekturaya **tədricən** keçid pattern-i.
Yeni funksionallıq paralel sistemdə qurulur, köhnə hissələr biri-biri ardınca əvəz olunur,
tam migration sonunda köhnə sistem "boğulur" (strangled) və silinir.

**Adın mənası:** Amazon meşəsindəki strangler fig ağacı öz sahibini tədricən bürüyüb öldürür.

**Əsas anlayışlar:**
- **Strangler Facade** — HTTP proxy/routing layer: köhnə ↔ yeni sistemlər arasında
- **Feature Toggle** — Trafikin hansı sistemə getdiyini idarə edir
- **Extract & Expand** — Köhnə feature-ı yeni sistemdə rebuild et
- **Cutover** — Trafiği yeni sistemə keçir, köhnə kodu sil
- **Parallel Run** — Köhnə + yeni sistem eyni vaxtda işləyir, nəticələr müqayisə olunur

**Nə vaxt istifadə et:**
- Big bang rewrite risk-i çox yüksəkdir
- Köhnə sistem hissə-hissə əvəz edilə bilər
- Uzunmüddətli migration lazımdır (aylar/illər)

---

## Laravel (Legacy → Modern)

```
project/
├── legacy-app/                                 # Köhnə Laravel 5.x / PHP app
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── UserController.php
│   │   │   ├── OrderController.php
│   │   │   └── ProductController.php
│   │   ├── Models/
│   │   └── Services/
│   └── routes/
│       └── web.php
│
├── new-app/                                    # Yeni Laravel 11.x app
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   └── UserController.php             # Migration: User endpoint-lər köçürüldü
│   │   ├── Modules/
│   │   │   └── User/                          # Modular monolith structure
│   │   │       ├── Api/
│   │   │       └── Internal/
│   │   └── Services/
│   └── routes/
│       └── api.php
│
├── facade/                                     # Strangler Facade (Nginx / API Gateway)
│   ├── nginx.conf                             # Routing rules: /api/users → new-app
│   │                                          #               /api/orders → legacy-app
│   ├── router/
│   │   ├── StranglerRouter.php                # PHP-based router (alternative)
│   │   ├── FeatureToggle.php                  # Toggle: köhnə vs yeni
│   │   └── TrafficSplitter.php                # Canary: 10% → new, 90% → legacy
│   └── middleware/
│       └── StranglerMiddleware.php
│
├── migration/
│   ├── phases/
│   │   ├── phase1-user-service.md             # Migration plan
│   │   ├── phase2-order-service.md
│   │   └── phase3-payment-service.md
│   ├── DataMigrator.php                       # DB data sync legacy → new
│   └── ParallelRunner.php                     # Eyni request-i hər ikisini çağır, diff
│
└── infrastructure/
    ├── docker-compose.yml                     # Hər iki app eyni anda işləyir
    └── nginx/
        └── strangler.conf                     # Routing config
```

---

## Spring Boot (Java)

```
project/
├── legacy-service/                            # Köhnə Spring Boot 2.x
│   ├── src/main/java/com/example/legacy/
│   │   ├── controller/
│   │   │   ├── UserController.java
│   │   │   ├── OrderController.java
│   │   │   └── ProductController.java
│   │   ├── service/
│   │   └── repository/
│   └── pom.xml
│
├── new-service/                               # Yeni Spring Boot 3.x + modern patterns
│   ├── src/main/java/com/example/newapp/
│   │   ├── module/
│   │   │   └── user/                          # User module migrated
│   │   │       ├── api/
│   │   │       └── internal/
│   │   └── config/
│   └── pom.xml
│
├── facade-service/                            # Spring Cloud Gateway as Strangler Facade
│   ├── src/main/java/com/example/facade/
│   │   ├── FacadeApplication.java
│   │   ├── config/
│   │   │   └── RouteConfig.java               # Routing rules
│   │   ├── filter/
│   │   │   ├── FeatureToggleFilter.java       # Hangi endpoint yeni sisteme gedir
│   │   │   ├── ParallelRunFilter.java         # Parallel run + comparison
│   │   │   └── TrafficSplitFilter.java        # Canary: % traffic split
│   │   └── toggle/
│   │       ├── FeatureToggleService.java
│   │       └── FeatureToggleRepository.java   # DB/Redis-based toggles
│   └── src/main/resources/
│       └── application.yml                    # Route definitions
│
├── migration/
│   ├── data-migration/
│   │   ├── UserDataMigrator.java
│   │   └── OrderDataMigrator.java
│   └── testing/
│       └── ParallelRunComparator.java
│
└── infrastructure/
    ├── docker-compose.yml
    └── k8s/
        ├── legacy-deployment.yaml
        ├── new-deployment.yaml
        └── gateway-deployment.yaml
```

---

## Golang

```
project/
├── legacy/                                    # Köhnə Go app
│   ├── cmd/
│   │   └── main.go
│   └── internal/
│       ├── handler/
│       │   ├── user_handler.go
│       │   └── order_handler.go
│       └── service/
│
├── new-app/                                   # Yeni Go app
│   ├── cmd/
│   │   └── main.go
│   └── internal/
│       ├── module/
│       │   └── user/                          # Migrated module
│       │       ├── api/
│       │       └── internal/
│       └── config/
│
├── facade/                                    # Strangler Facade (reverse proxy)
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── proxy/
│   │   │   ├── router.go                      # Route: /users → new, /orders → legacy
│   │   │   ├── legacy_proxy.go               # Proxy to legacy
│   │   │   └── new_proxy.go                  # Proxy to new app
│   │   ├── toggle/
│   │   │   ├── feature_toggle.go             # Toggle interface
│   │   │   └── redis_toggle.go               # Redis-backed toggles
│   │   ├── parallel/
│   │   │   ├── runner.go                     # Parallel run: send to both
│   │   │   └── comparator.go                 # Compare responses
│   │   └── config/
│   │       └── config.go
│   └── go.mod
│
├── migration/
│   ├── cmd/
│   │   └── migrate/
│   │       └── main.go
│   ├── user_migrator.go
│   └── order_migrator.go
│
└── infrastructure/
    ├── docker-compose.yml
    └── nginx/
        └── strangler.conf
```

---

## Migration Faza Nümunəsi

```
Faza 1 (1-2 ay): User endpoints → Yeni sistemə
  - /api/users/* → new-app
  - Parallel run 2 həftə (comparison mode)
  - Cutover + legacy user code sil

Faza 2 (2-3 ay): Order endpoints → Yeni sistemə
  - /api/orders/* → new-app
  - User dependency migrated, indi order module qur
  - Cutover + legacy order code sil

Faza 3 (son ay): Yerdə qalanlar + legacy app sil
  - Feature toggle service artıq lazımsızdır
  - Facade router artıq tamamilə new-app-a yönləndirir
  - Legacy app söndürülür
```
