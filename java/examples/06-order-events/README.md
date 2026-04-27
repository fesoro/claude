# Order Events (⭐⭐⭐ Senior)

Spring Application Events ilə event-driven order sistemi. Order yaradılanda, statusu dəyişəndə — event publish olur, listener-lər async emal edir. State machine pattern ilə order status axışı.

## Öyrənilən Konseptlər

- `ApplicationEventPublisher` — event publish etmək
- `@EventListener` — sinxron event emalı
- `@TransactionalEventListener` — transaction commit-dən sonra fire
- `@Async` — asinxron listener
- State machine pattern — `OrderStatus` enum ilə valid keçidlər
- `@Embedded` / `@Embeddable` — value object

## İşə Salma

```bash
cd java/examples/06-order-events
./mvnw spring-boot:run
# → http://localhost:8080
```

## Endpoints

| Method | Path | Təsvir |
|--------|------|--------|
| POST | /api/orders | Yeni sifariş yarat |
| GET | /api/orders | Bütün sifarişlər |
| GET | /api/orders/{id} | Sifariş detalları |
| PATCH | /api/orders/{id}/confirm | Sifariş təsdiqlə |
| PATCH | /api/orders/{id}/ship | Göndərildi olaraq işarələ |
| PATCH | /api/orders/{id}/deliver | Çatdırıldı olaraq işarələ |
| PATCH | /api/orders/{id}/cancel | Sifariş ləğv et |

## İstifadə Nümunəsi

```bash
# Yeni sifariş
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "customerEmail": "ali@example.com",
    "items": [
      {"productName": "Laptop", "quantity": 1, "unitPrice": 1200.00},
      {"productName": "Mouse",  "quantity": 2, "unitPrice": 25.00}
    ]
  }'

# Sifarişi təsdiqlə (PENDING → CONFIRMED)
curl -X PATCH http://localhost:8080/api/orders/1/confirm

# Göndər (CONFIRMED → SHIPPED)
curl -X PATCH http://localhost:8080/api/orders/1/ship

# Çatdır (SHIPPED → DELIVERED)
curl -X PATCH http://localhost:8080/api/orders/1/deliver
```

## Event Axışı

```
Order yaranır
    → OrderCreatedEvent publish
        → NotificationListener: email göndərir (async)
        → InventoryListener: stok azaldır (async)

Status dəyişir
    → OrderStatusChangedEvent publish
        → AuditListener: log yazır
        → NotificationListener: müştəriyə bildiriş (SHIPPED/DELIVERED üçün)
```

## State Machine

```
PENDING → CONFIRMED → SHIPPED → DELIVERED
    ↓          ↓
  CANCELLED  CANCELLED
```
Yanlış keçid (məs: PENDING → SHIPPED) `IllegalStateException` atır.

## İrəli Getmək Üçün

- Kafka ilə event sistemi → `java/spring/75-kafka-producer-consumer.md`
- Outbox pattern → `java/advanced/08-outbox-pattern.md`
