# Distributed Monolith (Anti-Pattern) (Lead)

Microservices arxitekturasına keçəndə ən çox edilən səhv.
Sistem fiziki olaraq ayrılmış **görünür**, amma əslində monolith kimi **tightly coupled** qalır.
Hər iki arxitekturanın **ən pis** tərəflərini birləşdirir.

**Əlamətlər:**
- Servisləri ayrı deploy etmək mümkün deyil (birini dəyişəndə digəri qırılır)
- Servislər bir-birinin DB-sinə birbaşa yazır/oxuyur
- Bir servis düşəndə digərləri də düşür (no fault isolation)
- Hər deploy bütün servislər üçün koordinasiya tələb edir
- Shared database schema: bütün servislər eyni DB-yə bağlıdır

---

## Anti-Pattern Nümunəsi (Laravel)

```
❌ DISTRIBUTED MONOLITH — Bu yanlışdır:

project/
├── user-service/
│   ├── app/Services/UserService.php
│   │   // YANLIŞ: başqa servisin modelini import edir
│   │   use App\Models\Order;  // OrderService-in modeli!
│   │   use App\Models\Product; // ProductService-in modeli!
│   │
│   └── database/
│       └── (paylaşılan DB-ə qoşulur)
│
├── order-service/
│   ├── app/Services/OrderService.php
│   │   // YANLIŞ: birbaşa başqa servisin DB cədvəlinə query edir
│   │   DB::table('products')->where('id', $productId)->first(); // products → product-service-in cədvəlidir!
│   │   DB::table('users')->find($userId); // users → user-service-in cədvəlidir!
│   │
│   └── database/
│       └── (eyni paylaşılan DB)
│
├── product-service/
│   └── (eyni paylaşılan DB)
│
└── shared/
    └── database/
        └── shared_database.sql   # ← PROBLEM: hamı eyni DB-yə yazır
```

---

## Düzgün Struktur (Laravel)

```
✅ DÜZGÜN — Hər servis öz DB-sinə sahibdir:

project/
├── user-service/
│   ├── app/
│   │   ├── Services/UserService.php
│   │   │   // DÜZGÜN: yalnız öz modelini istifadə edir
│   │   └── Events/UserCreated.php       # Event publish edir
│   └── database/
│       └── user_service_db/              # Yalnız users, profiles cədvəlləri
│
├── order-service/
│   ├── app/
│   │   ├── Services/OrderService.php
│   │   │   // DÜZGÜN: user data lazımdırsa API call edir
│   │   ├── Clients/
│   │   │   ├── UserServiceClient.php     # HTTP call to user-service
│   │   │   └── ProductServiceClient.php  # HTTP call to product-service
│   │   └── Listeners/
│   │       └── HandleUserCreated.php     # Event-dən user snapshot saxla
│   └── database/
│       └── order_service_db/             # Yalnız orders, order_items
│           └── (user_name denormalized copy, cached for performance)
│
├── product-service/
│   └── database/
│       └── product_service_db/           # Yalnız products, categories
│
└── infrastructure/
    └── docker-compose.yml
        # Hər servis üçün ayrı DB container
```

---

## Spring Boot — Distributed Monolith Detection

```
project/
├── ❌ BAD SIGNS — Bu faylları axtarın:

│   user-service/src/.../UserService.java
│   │   @Autowired OrderRepository orderRepository;  // Başqa servisin repo!
│   │
│   OrderService.java
│   │   @Query("SELECT * FROM products WHERE id = ?") // Başqa servisin DB!
│   │   Product findProductById(Long id);
│   │
│   pom.xml / build.gradle
│   │   <dependency>product-service-model</dependency>  // Shared model!

├── ✅ GOOD SIGNS — Bunlar görünməlidir:
│
│   order-service/
│   ├── client/
│   │   └── ProductServiceClient.java       # Feign client (HTTP)
│   ├── event/
│   │   └── OrderPlacedEvent.java           # Publish to Kafka/RabbitMQ
│   └── listener/
│       └── UserCreatedListener.java        # Subscribe to user events
│
│   user-service/
│   └── event/
│       └── UserCreatedPublisher.java       # Publish events, no sync calls to others
```

---

## Golang — Correct Service Boundaries

```
project/
├── order-service/
│   ├── cmd/main.go
│   ├── internal/
│   │   ├── domain/
│   │   │   ├── order.go                    # Owns order domain
│   │   │   └── order_item.go
│   │   │
│   │   ├── client/                         # Komunikasiya HTTP/gRPC vasitəsilə
│   │   │   ├── product_client.go           # Calls product-service API
│   │   │   └── user_client.go              # Calls user-service API
│   │   │
│   │   ├── event/
│   │   │   ├── publisher.go                # Publish OrderPlaced event
│   │   │   └── subscriber.go              # Subscribe to external events
│   │   │
│   │   └── repository/
│   │       └── postgres_repo.go            # ONLY accesses own DB
│   │
│   └── go.mod
│
├── product-service/
│   ├── internal/
│   │   ├── domain/
│   │   │   └── product.go
│   │   └── repository/
│   │       └── postgres_repo.go            # ONLY accesses own DB
│   └── go.mod
│
└── infrastructure/
    └── docker-compose.yml
        # order-db: separate PostgreSQL container
        # product-db: separate PostgreSQL container
        # NO shared database!
```

---

## Distributed Monolith Əlamətləri (Checklist)

```
Deploy coupling:
  ☐ "user-service dəyişəndə order-service-i də redeploy etməliyik"
  ☐ Versioning olmadan shared library update

Data coupling:
  ☐ Bir servis başqa servisin DB cədvəlinə birbaşa query edir
  ☐ Shared database schema (hamı eyni DB)
  ☐ Foreign key across services

Runtime coupling:
  ☐ Synchronous call chain: A → B → C → D (timeout propagation)
  ☐ A düşəndə B, C, D də düşür
  ☐ Circuit breaker yoxdur

Test coupling:
  ☐ Unit test üçün digər servisləri ayağa qaldırmaq lazımdır
  ☐ Integration test bütün servislər üçün eyni anda işləyir

Əgər bunlardan 3+ varsa → Distributed Monolith-iniz var!
```
