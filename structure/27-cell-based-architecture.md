# Cell-Based Architecture (Architect)

İnfrastrukturu müstəqil, izolə edilmiş **cell**-lərə bölür. Hər cell tam bir deployment stack-ı saxlayır.
Bir cell-in xətası digər cell-ləri etkiləmir — **blast radius** məhdudlaşdırılır.
AWS, Slack, DoorDash bu pattern-i istifadə edir.

**Əsas anlayışlar:**
- **Cell** — Müstəqil, tam functional deployment unit (own LB, services, DB)
- **Cell Router** — İstifadəçini düzgün cell-ə yönləndirir
- **Blast Radius Isolation** — Bir cell-in xətası yalnız həmin cell-in user-lərini etkiləyir
- **Horizontal Scaling** — Yeni cell əlavə etməklə scale out
- **Cell Affinity** — İstifadəçi hər zaman eyni cell-ə yönləndirilir
- **Shuffle Sharding** — İstifadəçiləri cell-lərə bölmə strategiyası

**Nə vaxt lazımdır:**
- Global multi-region tətbiq
- Tenant isolation (SaaS multi-tenancy)
- Catastrophic failure izolasiyası
- 99.99%+ SLA tələbi

---

## Spring Boot (Java) — Cell Architecture

```
project/
│
├── cell-router/                               # Global router (DNS/L7 level)
│   ├── src/main/java/com/example/router/
│   │   ├── RouterApplication.java
│   │   ├── controller/
│   │   │   └── RoutingController.java
│   │   ├── service/
│   │   │   ├── CellRouter.java               # User → Cell mapping
│   │   │   ├── CellRegistry.java             # Available cells + health
│   │   │   └── ShuffleSharding.java          # Distribute users across cells
│   │   └── config/
│   │       └── RoutingConfig.java
│   └── src/main/resources/
│       └── application.yml
│
├── cell-template/                             # Template for each cell
│   ├── user-service/
│   │   ├── src/main/java/com/example/cell/user/
│   │   │   └── (standard user service)
│   │   └── Dockerfile
│   │
│   ├── order-service/
│   │   ├── src/main/java/com/example/cell/order/
│   │   └── Dockerfile
│   │
│   ├── product-service/
│   │   └── Dockerfile
│   │
│   └── database/
│       ├── postgresql.yaml                    # Cell-local PostgreSQL
│       └── redis.yaml                         # Cell-local Redis
│
├── infrastructure/
│   ├── cells/
│   │   ├── cell-eu-west-1/                    # Europe cell
│   │   │   ├── kubernetes/
│   │   │   │   ├── user-service.yaml
│   │   │   │   ├── order-service.yaml
│   │   │   │   └── product-service.yaml
│   │   │   └── terraform/
│   │   │       └── cell.tf
│   │   │
│   │   ├── cell-us-east-1/                    # US East cell
│   │   │   ├── kubernetes/
│   │   │   └── terraform/
│   │   │
│   │   ├── cell-ap-southeast-1/               # Asia cell
│   │   │   ├── kubernetes/
│   │   │   └── terraform/
│   │   │
│   │   └── cell-us-west-2/                    # US West cell (redundancy)
│   │       ├── kubernetes/
│   │       └── terraform/
│   │
│   ├── global/
│   │   ├── cell-router/                       # Global routing layer
│   │   ├── dns/                               # Route 53 / Cloudflare
│   │   └── monitoring/                        # Cross-cell observability
│   │
│   └── terraform/
│       ├── modules/
│       │   └── cell/                          # Reusable cell module
│       │       ├── main.tf
│       │       ├── variables.tf
│       │       └── outputs.tf
│       └── environments/
│           ├── eu-west-1.tfvars
│           └── us-east-1.tfvars
```

---

## Laravel (Multi-Tenant SaaS Cells)

```
project/
│
├── cell-router/                               # Tenant → Cell routing
│   ├── app/
│   │   ├── Http/Middleware/
│   │   │   └── TenantCellRouter.php          # Subdomain → Cell redirect
│   │   └── Services/
│   │       ├── TenantCellRegistry.php        # tenant_id → cell_url
│   │       └── CellHealthChecker.php
│   └── routes/api.php
│
├── cell-app/                                  # Standard Laravel app (deployed per cell)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── UserController.php
│   │   │   └── OrderController.php
│   │   └── Services/
│   └── config/
│       └── database.php                       # Points to cell-local DB
│
├── infrastructure/
│   ├── cells/
│   │   ├── cell-tier1/                        # Premium tenants (dedicated cell)
│   │   │   ├── docker-compose.yml
│   │   │   └── nginx.conf
│   │   ├── cell-tier2-a/                      # Standard tenants, shard A
│   │   │   ├── docker-compose.yml
│   │   │   └── nginx.conf
│   │   └── cell-tier2-b/                      # Standard tenants, shard B
│   │       └── docker-compose.yml
│   └── global/
│       └── cell-router/
│           └── nginx.conf                     # Global routing
```

---

## Golang

```
project/
├── cell-router/
│   ├── cmd/main.go
│   ├── internal/
│   │   ├── router/
│   │   │   ├── cell_router.go                # user_id → cell assignment
│   │   │   └── shuffle_sharding.go           # 2-of-8 cell assignment
│   │   ├── registry/
│   │   │   ├── cell_registry.go              # DynamoDB/Redis cell registry
│   │   │   └── health_checker.go             # Poll cell health endpoints
│   │   └── proxy/
│   │       └── reverse_proxy.go
│   └── go.mod
│
├── cell-service/                              # Template service (deployed per cell)
│   ├── cmd/main.go
│   ├── internal/
│   │   ├── handler/
│   │   ├── service/
│   │   └── repository/
│   │       └── postgres_repo.go              # Cell-local PostgreSQL
│   └── go.mod
│
└── infrastructure/
    ├── cells/
    │   ├── cell-a/
    │   │   └── k8s/
    │   └── cell-b/
    │       └── k8s/
    └── global/
        └── terraform/
```

---

## Cell Routing Strategiyası

```
Shuffle Sharding (AWS Route 53 pattern):

8 cell varsa, hər user 2 cell-ə assign edilir (2-of-8 = 28 possible pair)
User1 → cell-1, cell-3
User2 → cell-1, cell-5
User3 → cell-2, cell-4

Üstünlük: 
- cell-1 düşsə, User1 → cell-3-ə keçir
- User1 ilə User2 eyni cell-də (cell-1), amma User2 digər cell-ə (cell-5) keçir
- Blast radius: 2 cell xəta versə, cəmi 1/28 user pair-i etkilənir

Tenant-based assignment:
- Premium tenant → dedicated cell (tek bir şirkət)
- Standard tenant → shared cell (xeyli şirkət, amma izolasiya var)
- Free tenant → crowded cell (çoxlu şirkət, az izolasiya)

DoorDash cell model:
- Hər şəhər/region ayrı cell
- City-level outage → yalnız həmin şəhərin sifarişləri etkilənir
- Global service (auth, payment) ayrı infra qatında
```
