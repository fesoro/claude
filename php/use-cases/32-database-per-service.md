# Database per Service Pattern

## Ssenari

Mikroservislərdə hər servisin öz ayrı database-i olur. Data ownership, cross-service queries, eventual consistency problemlərinin həlli.

---

## Problem: Shared Database

```
❌ Shared Database anti-pattern:

┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ Order Svc    │  │ Payment Svc  │  │ Inventory Svc│
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                 │                  │
       └─────────────────┼──────────────────┘
                         │
                ┌────────▼─────────┐
                │   Shared DB      │
                │  orders table    │
                │  payments table  │
                │  inventory table │
                └──────────────────┘

Problemlər:
  ❌ Tight coupling — schema dəyişsə hamı təsirlənir
  ❌ Servislərin müstəqil deploy edilməsi çətin
  ❌ Bir servisin ağır query-si hamını yavaşladır
  ❌ DB teknologiyası hamsına məcburidir
```

---

## Database per Service

```
✅ Database per Service:

┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ Order Svc    │  │ Payment Svc  │  │ Inventory Svc│
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘
       │                 │                  │
┌──────▼───────┐  ┌──────▼───────┐  ┌──────▼───────┐
│  Orders DB   │  │ Payments DB  │  │ Inventory DB │
│  (MySQL)     │  │  (MySQL)     │  │  (Redis +    │
│              │  │              │  │   MySQL)     │
└──────────────┘  └──────────────┘  └──────────────┘

✅ Loose coupling — schema dəyişikliyi izolə
✅ Polyglot persistence (hər servis uyğun DB)
✅ Independent scale
✅ Independent deploy
❌ Cross-service query çətin
❌ Distributed transactions
❌ Data duplication
```

---

## Data Ownership

```
Hər servis öz data-sına sahib çıxır:

Order Service owns:
  orders, order_items, order_status_history

Payment Service owns:
  payments, refunds, payment_methods

Inventory Service owns:
  products (stock info), reservations, movements

User Service owns:
  users, addresses, preferences

Qayda: Hər cədvəl yalnız bir servisə məxsusdur!
  Order-da customer adı lazımdır?
    → User Service-dən API ilə al (ID saxla, name yox)
    → Yaxud denormalize et (event-driven sync)
```

---

## Cross-service Query Problemi

```
Problem: "User-in bütün order-larını customer info ilə gətr"

SQL-də:
  SELECT o.*, u.name, u.email
  FROM orders o
  JOIN users u ON o.user_id = u.id
  WHERE u.id = 123

Ayrı DB-lərdə bu sorğu mümkün deyil!

Həllər:
1. API Composition (Gateway)
2. CQRS Read Model
3. Event-driven denormalization
```

### 1. API Composition

*Bu kod gateway-in paralel sorğu atıb nəticəni birləşdirərək cross-service join problemini həll etdiyini göstərir:*

```php
// API Gateway / BFF sorğuları birləşdirir
class OrderDashboardComposer
{
    public function getUserOrders(int $userId): array
    {
        // Paralel sorğular
        [$user, $orders] = collect([
            Http::get("/users/$userId"),
            Http::get("/orders?user_id=$userId"),
        ])->map(fn($r) => $r->json())->all();
        
        // Merge
        return array_map(fn($order) => [
            ...$order,
            'customer_name'  => $user['name'],
            'customer_email' => $user['email'],
        ], $orders);
    }
}
```

### 2. CQRS Read Model

*Bu kod eventlərdən denormalize read model quran, sifarişlər yarandıqda, dəyişdikdə və user məlumatları yeniləndikdə görünüşü sinxronizasiya edən projection-ı göstərir:*

```php
// Ayrı Read DB — denormalized, sorğuya optimized
// Write: hər servis öz DB-sinə
// Read: eventlərdən build edilmiş read model

// Read Model cədvəli (Order Service-də deyil, ayrı Read Service-də)
// user_orders_view:
//   order_id, user_id, user_name, user_email,
//   order_total, order_status, items_count, created_at

class OrdersReadModelProjection
{
    // OrderPlaced event gəldikdə read model yenilə
    public function onOrderPlaced(OrderPlaced $event): void
    {
        // User məlumatını User Service-dən al (bir dəfəlik)
        $user = Http::get("/users/{$event->customerId}")->json();
        
        DB::table('user_orders_view')->insert([
            'order_id'      => $event->orderId,
            'user_id'       => $event->customerId,
            'user_name'     => $user['name'],
            'user_email'    => $user['email'],
            'order_total'   => $event->totalAmount,
            'order_status'  => 'pending',
            'items_count'   => count($event->items),
            'created_at'    => $event->occurredAt,
        ]);
    }
    
    // OrderConfirmed event gəldikdə
    public function onOrderConfirmed(OrderConfirmed $event): void
    {
        DB::table('user_orders_view')
            ->where('order_id', $event->orderId)
            ->update(['order_status' => 'confirmed']);
    }
    
    // UserProfileUpdated event gəldikdə
    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        // Denormalized data-nı yenilə
        DB::table('user_orders_view')
            ->where('user_id', $event->userId)
            ->update([
                'user_name'  => $event->newName,
                'user_email' => $event->newEmail,
            ]);
    }
}

// Read: 1 sorğu, join yoxdur
$orders = DB::table('user_orders_view')
    ->where('user_id', 123)
    ->orderBy('created_at', 'desc')
    ->get();
```

### 3. Event-driven Denormalization

*Bu kod User servisinin event-lərindən lokal user snapshot-unu yeniləyən event-driven denormalizasiya yanaşmasını göstərir:*

```php
// Order Service öz DB-sində minimal user data saxlayır
// user_snapshots: user_id, name, email, updated_at

class UserSnapshotHandler
{
    // User Service UserUpdated event publish edir
    public function handleUserUpdated(UserUpdated $event): void
    {
        DB::table('user_snapshots')->upsert(
            [
                'user_id'    => $event->userId,
                'name'       => $event->name,
                'email'      => $event->email,
                'updated_at' => $event->occurredAt,
            ],
            ['user_id'],
            ['name', 'email', 'updated_at']
        );
    }
}

// Order-da snapshot-dan oxu
class OrderController
{
    public function show(string $orderId): JsonResponse
    {
        $order = Order::with('userSnapshot')->findOrFail($orderId);
        
        return response()->json([
            'id'       => $order->id,
            'status'   => $order->status,
            'total'    => $order->total,
            'customer' => [
                'name'  => $order->userSnapshot->name,
                'email' => $order->userSnapshot->email,
                // Eventual consistency: snapshot köhnə ola bilər
            ],
        ]);
    }
}
```

---

## Migration Strategy

*Bu kod shared DB-dən ayrı DB-yə keçid üçün service layer → dual write → tam keçid mərhələlərini göstərir:*

```php
// Monolitdən Database per Service-ə keçid
// Strangler Fig + Expand/Contract

// Mərhələ 1: Service layer yaradılır, hələ shared DB
class InventoryService
{
    public function reserve(string $orderId, int $productId, int $qty): bool
    {
        // Hələ shared DB-dən
        return DB::table('inventory')
            ->where('product_id', $productId)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty) > 0;
    }
}

// Mərhələ 2: Ayrı DB, dual write
class InventoryService
{
    public function reserve(string $orderId, int $productId, int $qty): bool
    {
        // Shared DB-yə yaz (köhnə)
        $result = DB::connection('shared')->table('inventory')
            ->where('product_id', $productId)
            ->decrement('stock', $qty);
        
        // Yeni DB-yə də yaz (yeni)
        DB::connection('inventory_service')->table('inventory')
            ->where('product_id', $productId)
            ->decrement('stock', $qty);
        
        return $result > 0;
    }
}

// Mərhələ 3: Yalnız yeni DB
class InventoryService
{
    public function reserve(string $orderId, int $productId, int $qty): bool
    {
        return DB::connection('inventory_service')
            ->table('inventory')
            ->where('product_id', $productId)
            ->where('available', '>=', $qty)
            ->decrement('available', $qty) > 0;
    }
}
```

---

## İntervyu Sualları

**1. Database per service pattern-in faydaları nələrdir?**
Loose coupling: schema dəyişikliyi izolə. Polyglot persistence: hər servis uyğun DB (Inventory → Redis, Analytics → ClickHouse). Independent scale: Inventory DB ayrıca scale. Independent deploy: DB migration yalnız o servisin deploy-una bağlı.

**2. Cross-service join problemi necə həll edilir?**
API Composition: Gateway paralel sorğu atır, merge edir. CQRS Read Model: eventlərdən build edilmiş denormalized view. Event-driven snapshot: digər servisin data-sını lokal saxla, event-lərlə yenilə. Hər birinin consistency vs complexity tradeoff-u var.

**3. Eventual consistency bu pattern-də necə görünür?**
User adını dəyişdi → UserUpdated event → Order Service snapshot yeniləndi. Qısa müddətdə Order Service köhnə adı göstərə bilər. Acceptable for most cases (user profile). Financial data üçün acceptable deyil — Saga/2PC lazımdır.

**4. Monolitdən bu pattern-ə keçid zamanı nə etmək lazımdır?**
Strangler Fig ilə tədricən. Əvvəlcə service layer yaradılır (shared DB). Sonra dual write (shared + new DB). Verify: new DB-nin data-sı doğrudur. Son olaraq shared DB-dən keçid. Feature flags ilə rollback imkanı saxlanılır.

---

## Saga Pattern ilə Distributed Data Consistency

*Bu kod cross-service yazma əməliyyatı üçün outbox ilə saga başladıb xəta halında kompensasiya edən orchestrator-u göstərir:*

```php
// Database per service-də cross-service write: Saga lazımdır
// Choreography saga — eventlərlə kompensasiya

// Ssenari: Sifariş yarat + Inventory azalt + Payment al
// Hər addım öz DB-sini yeniləyir, fail olduqda compensation event publish edir

// Order Service: Saga-nı başlat
class OrderSagaOrchestrator
{
    public function startOrderSaga(CreateOrderCommand $cmd): void
    {
        // 1. Lokal DB-yə yaz
        $order = DB::transaction(function () use ($cmd) {
            $order = Order::create(['status' => 'pending', ...]);

            // Outbox: event eyni transaction-da
            OutboxEvent::create([
                'event_type' => 'order.saga.started',
                'payload'    => json_encode(['order_id' => $order->id, 'items' => $cmd->items]),
            ]);
            return $order;
        });
    }

    // Compensation: inventory fail → order cancel
    public function compensate(string $orderId, string $reason): void
    {
        DB::transaction(function () use ($orderId, $reason) {
            Order::where('id', $orderId)->update(['status' => 'cancelled']);
            OutboxEvent::create([
                'event_type' => 'order.saga.compensated',
                'payload'    => json_encode(['order_id' => $orderId, 'reason' => $reason]),
            ]);
        });
    }
}
```

---

## İntervyu Sualları

**5. Eventual consistency istifadəçi üçün nə deməkdir?**
İstifadəçi User adını dəyişir → Order Service-dəki snapshot bir neçə saniyə köhnə qalır. UX perspektivindən: əksər hallarda qəbul ediləndir. "Sizin sifarişlər" səhifəsində köhnə ad görünə bilər — bu financial data deyil, acceptable. Payment məbləği kimi kritik data üçün Saga/2PC lazımdır.

**6. Polyglot persistence seçimini necə əsaslandırırsınız?**
Inventory Service: Redis (sürətli stock check, pub/sub realtime updates). Analytics Service: ClickHouse/Redshift (column store, aggregation). Search Service: Elasticsearch (full-text). User Service: PostgreSQL (relational, ACID). Order Service: MySQL/PostgreSQL. Seçim: data-nın yazılma/oxunma nisbəti, sorğu tipi, consistency tələbi.

---

## Anti-patternlər

**1. Servislərin bir-birinin DB-sinə birbaşa müraciəti**
Inventory servisinin Order servisinin DB cədvəllərinə birbaşa `SELECT` etməsi — servisləri schema səviyyəsindən bir-birinə bağlayır, bir servisin migration-ı digərini sındırır, müstəqil deploy imkanı itirilir. Hər servis yalnız öz DB-si ilə işləsin, digərinin data-sına API və ya eventlər vasitəsilə çatsın.

**2. Cross-service data-ya ehtiyac olduqda sinxron zəncirvari sorğular etmək**
Order detallarını göstərmək üçün Order servisinin Product servisinə, sonra User servisinə ardıcıl sorğu atması — gecikməni toplam edir, bir servis yavaşlarsa hamısı yavaşlayır, N+1 problemi kimi kaskad yavaşlıq yaranır. API Composition Gateway ilə paralel sorğular at, ya da event-driven snapshot ilə lokal data saxla.

**3. Servislərarası distributed transaction tətbiq etməyə çalışmaq**
2PC (Two-Phase Commit) ilə bir neçə servisin DB-sini eyni anda atomik şəkildə yeniləməyə cəhd etmək — servisləri sıx bağlayır, koordinator servis single point of failure olur, lock müddəti uzanır. Saga pattern (choreography və ya orchestration) ilə eventual consistency qur.

**4. Monolitdən keçişdə birbaşa shared DB-ni kəsmək**
Servisləri ayırarkən shared DB-dən bir addımda tam ayrılmaq — köhnə kod hələ shared DB-yə istinad edir, yeni servis hazır deyil, downtime qaçılmaz olur. Strangler Fig pattern ilə tədricən keç: əvvəlcə servis layeri, sonra dual-write, doğrulama, son olaraq tam keçid.

**5. Event-driven snapshot-ları vaxtında yeniləməmək**
Lokal saxlanılan başqa servisin data-sını (məs. user adı) event-lərlə sinxronizasiya mexanizmi qurub sonra nəzərdən keçirməmək — event consumer down olarsa snapshot köhnəlir, görüntülənən data yanlış olur. Consumer healthcheck, lag monitoring qur; kritik data üçün acceptable staleness limitini müəyyənləşdir.

**6. Hər servis üçün eyni DB texnologiyasını seçmək**
Polyglot persistence üstünlüyündən istifadə etməyib bütün servisləri MySQL-də saxlamaq — Inventory üçün Redis, Analytics üçün ClickHouse, Search üçün Elasticsearch daha münasib ola bilər. Hər servisin data-sının xarakterini analiz et (yazılma/oxunma nisbəti, sorğu tipi, ölçeklenme tələbi), uyğun DB-ni seç.
