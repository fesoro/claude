# E-Commerce DDD (⭐⭐⭐⭐ Lead)

Domain-Driven Design (DDD) layer arxitekturası ilə e-ticarət backend. Aggregate Root, Value Object, Domain Event, Repository interface, Application Service — tam DDD pattern-ləri real kodda.

## Öyrənilən Konseptlər

- **Aggregate Root** — `Product`, `Order` — domain mərkəzini qoruyur
- **Value Object** — `Money` — immutable, identity yoxdur
- **Domain Event** — `OrderPlacedEvent` — domain daxilindəki event-lər
- **Repository Interface** — domain layer-da interfeys, infrastructure-da impl
- **Application Service** — use case-ləri orkestrasiya edir
- **Layer ayrılığı:** `domain` → `application` → `infrastructure` → `presentation`
- **Aggregate invariant** — `Order.addItem()` içindən `totalAmount()` düzgün hesablanır

## Arxitektura

```
presentation/     ← HTTP, DTO, Controller
    ↓
application/      ← Use case-lər, Application Service
    ↓
domain/           ← Business logic, Entity, Value Object, Domain Event, Repository INTERFACE
    ↓
infrastructure/   ← JPA implementation, DB
```

**Əsas qayda:** Domain layer hər hansı framework-ə (Spring, JPA) BAĞLI DEYİL — yalnız interfeys bilir.

## İşə Salma

```bash
cd java/examples/08-ecommerce-ddd
./mvnw spring-boot:run
# → http://localhost:8080
```

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| GET | /api/products | Məhsul siyahısı |
| POST | /api/products | Yeni məhsul |
| GET | /api/products/{id} | Məhsul detalı |
| POST | /api/orders | Sifariş ver |
| GET | /api/orders/{id} | Sifariş detalları |
| PATCH | /api/orders/{id}/confirm | Sifarişi təsdiqlə |
| PATCH | /api/orders/{id}/cancel | Sifarişi ləğv et |

## İstifadə Nümunəsi

```bash
# Məhsul yarat
curl -X POST http://localhost:8080/api/products \
  -H "Content-Type: application/json" \
  -d '{"name":"MacBook Pro","price":2500.00,"stock":10}'

# Sifariş ver
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customerEmail": "ali@example.com",
    "items": [{"productId": 1, "quantity": 2}]
  }'

# Sifarişi təsdiqlə
curl -X PATCH http://localhost:8080/api/orders/1/confirm
```

## DDD vs Ənənəvi Approach

| Ənənəvi | DDD |
|---------|-----|
| `ProductService.create()` hər şeyi bilir | `Product.create()` — domain özü cavabdehdir |
| Anemic model (yalnız getter/setter) | Rich model (business method-lar) |
| DB schema→Code | Domain→DB schema |
| Service = script | Service = use case orkestrasiyası |

## PHP/Laravel ilə Müqayisə

| Laravel | DDD |
|---------|-----|
| `App\Models\Product` (Active Record) | `domain/product/Product.java` (Domain Entity) |
| `ProductController` DB-ə birbaşa yazar | `ProductController` → `ProductService` → `ProductRepository` |
| `protected $fillable` | Aggregate Root invariantları |

## İrəli Getmək Üçün

- CQRS: Command/Query ayrılığı
- Event Sourcing: `OrderPlacedEvent`-i persist et
- Hexagonal Architecture → `java/advanced/03-hexagonal-architecture.md`
