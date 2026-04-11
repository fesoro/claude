# Materialized View, CDC, Polyglot Persistence

## Mündəricat
1. [Materialized View Pattern](#materialized-view-pattern)
2. [Event-carried State Transfer](#event-carried-state-transfer)
3. [Change Data Capture (CDC)](#change-data-capture-cdc)
4. [Polyglot Persistence](#polyglot-persistence)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Materialized View Pattern

```
// Bu kod materialized view-un ağır sorğunu precomputed cədvəllə necə sürətləndirdiyini göstərir
Problem: Mürəkkəb hesablama hər request-də təkrarlanır

  SELECT p.id, p.name, AVG(r.rating), COUNT(r.id), MIN(price)...
  FROM products p
  LEFT JOIN reviews r ON ...
  LEFT JOIN prices pr ON ...
  GROUP BY p.id
  → Hər dəfə ağır query!

Həll: Materialized View
  Hesablanmış nəticəni saxla (precomputed)
  
  product_stats_mv:
  product_id | avg_rating | review_count | min_price | updated_at
  
  SELECT * FROM product_stats_mv WHERE product_id = 123
  → 1ms!

Normal View:   SELECT çalışanda hesablanır (virtual)
Materialized:  Disk-ə yazılmış, periodically refreshed

PostgreSQL:
  CREATE MATERIALIZED VIEW product_stats_mv AS
    SELECT p.id, AVG(r.rating)... FROM products p JOIN reviews r...;
  
  REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats_mv;
  -- CONCURRENTLY: refresh zamanı read mümkün (no lock)
```

---

## Event-carried State Transfer

```
Problem: Servislərarası data paylaşımı

  Order Service, User adına ehtiyac duyur
  Hər request-də User Service-ə sorğu at?
  → Network call, latency, coupling

Həll: Event-carried State Transfer
  Event payload-ına lazımlı data daxil et
  
  // Adi event (event notification):
  OrderPlaced { orderId: "123", userId: 456 }
  Consumer → User Service-dən userId ilə data al (2. call!)
  
  // Event-carried State Transfer:
  OrderPlaced {
    orderId: "123",
    user: { id: 456, name: "Ali", email: "ali@..." }  ← tam data
  }
  Consumer → 2. call lazım deyil!

✅ Loose coupling
✅ Resilient (User Service down olsa problem yox)
❌ Event payload böyüyür
❌ User data stale ola bilər (snapshot)
❌ PII sensitivity (email event-də)
```

---

## Change Data Capture (CDC)

```
DB-dəki dəyişiklikləri real-time olaraq capture et

MySQL Binary Log (binlog):
  Hər INSERT/UPDATE/DELETE binlog-a yazılır
  Debezium bu log-u oxuyur → Kafka-ya publish edir
  
  ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
  │  MySQL   │───►│ Debezium │───►│  Kafka   │───►│ Consumer │
  │  binlog  │    │ connector│    │  topic   │    │ services │
  └──────────┘    └──────────┘    └──────────┘    └──────────┘

Niyə CDC?
  Outbox polling-dən daha az latency
  Application kodu dəyişmir
  Legacy sistemlər üçün ideal
  DB triggers əvəzinə

Debezium event nümunəsi:
  {
    "op": "u",  // u=update, c=create, d=delete
    "before": {"id": 1, "status": "pending"},
    "after":  {"id": 1, "status": "paid"},
    "source": {"table": "orders", "ts_ms": 1705312200000}
  }
```

---

## Polyglot Persistence

```
"Bir proyektdə müxtəlif iş üçün müxtəlif DB"

Antipattern: Hər şey üçün MySQL
  MySQL: User profiles ✅
  MySQL: Product catalog (flexible attributes) ❌ (MongoDB daha yaxşı)
  MySQL: Session storage ❌ (Redis daha sürətli)
  MySQL: Search ❌ (Elasticsearch daha yaxşı)
  MySQL: Time-series metrics ❌ (InfluxDB/TimescaleDB)
  MySQL: Graph (friendship) ❌ (Neo4j)

Polyglot:
┌─────────────────────────────────────────────────┐
│                  Application                    │
├──────────┬──────────┬──────────┬────────────────┤
│ MySQL    │ MongoDB  │  Redis   │ Elasticsearch  │
│          │          │          │                │
│ Orders   │ Products │ Sessions │ Search/Full    │
│ Users    │ Content  │ Cache    │ text index     │
│ Payments │ Catalog  │ Rate lim.│                │
└──────────┴──────────┴──────────┴────────────────┘

✅ Hər data tipi üçün optimal alət
❌ Operasional complexity (4 fərqli DB)
❌ Cross-store transactions çətin
❌ Team must know multiple technologies
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Materialized View — manual refresh
class ProductStatsMaterializedView
{
    public function refresh(int $productId = null): void
    {
        $query = DB::table('products as p')
            ->leftJoin('reviews as r', 'r.product_id', '=', 'p.id')
            ->leftJoin('order_items as oi', 'oi.product_id', '=', 'p.id')
            ->select([
                'p.id as product_id',
                DB::raw('COALESCE(AVG(r.rating), 0) as avg_rating'),
                DB::raw('COUNT(DISTINCT r.id) as review_count'),
                DB::raw('COALESCE(SUM(oi.quantity), 0) as total_sold'),
                DB::raw('NOW() as refreshed_at'),
            ])
            ->groupBy('p.id')
            ->when($productId, fn($q) => $q->where('p.id', $productId));

        DB::table('product_stats_mv')->upsert(
            $query->get()->toArray(),
            ['product_id'],
            ['avg_rating', 'review_count', 'total_sold', 'refreshed_at']
        );
    }
}

// Event-carried State Transfer
class OrderPlacedEvent
{
    public function __construct(
        public readonly string $orderId,
        // Snapshot — event-in publish olunduğu andakı user datası
        public readonly array $userSnapshot,
        public readonly array $items,
        public readonly int   $total,
    ) {}
}

// Polyglot — Repository pattern ilə abstraction
interface ProductRepository
{
    public function find(int $id): ?Product;
    public function search(string $query): array;
    public function save(Product $product): void;
}

class MySQLProductRepository implements ProductRepository
{
    public function find(int $id): ?Product
    {
        return Product::find($id);
    }

    public function save(Product $product): void
    {
        $product->save();
        // Elasticsearch-a da sync et
        app(ElasticsearchProductIndex::class)->index($product);
    }

    public function search(string $query): array
    {
        // Elasticsearch-a delegate et
        return app(ElasticsearchProductIndex::class)->search($query);
    }
}

// CDC consumer (Kafka + Debezium)
class OrderChangesConsumer
{
    public function consume(array $cdcEvent): void
    {
        $operation = $cdcEvent['op'];  // c, u, d

        match($operation) {
            'c' => $this->onInsert($cdcEvent['after']),
            'u' => $this->onUpdate($cdcEvent['before'], $cdcEvent['after']),
            'd' => $this->onDelete($cdcEvent['before']),
        };
    }

    private function onUpdate(array $before, array $after): void
    {
        if ($before['status'] !== $after['status']) {
            // Status dəyişdi → notification, projection update
            $this->notificationService->send($after['user_id'], 
                "Sifarişinizin statusu: {$after['status']}");
            
            app(OrderSummaryProjector::class)->onStatusChanged(
                $after['id'], $after['status']
            );
        }
    }
}
```

---

## İntervyu Sualları

**1. Materialized View nədir, normal view-dan fərqi?**
Normal view: SELECT çalışanda hesablanır (virtual, disk-də yoxdur). Materialized view: nəticə disk-ə yazılır, periodically refresh. Mürəkkəb aggregation hər request-də deyil, bir dəfə hesablanır. PostgreSQL: `REFRESH MATERIALIZED VIEW CONCURRENTLY`.

**2. CDC nədir, Outbox pattern-dən fərqi?**
Outbox: application outbox cədvəlinə yazır, relay polling edir. CDC: DB binlog-u oxuyur (Debezium), application kodu dəyişmir. CDC daha az latency, legacy sistemlər üçün uyğun. Outbox daha portable (binlog access lazım deyil).

**3. Event-carried State Transfer nə zaman istifadə edilir?**
Consumer event-də digər servisin data-sına ehtiyac duyduqda. Alternativ sorğu atmaq əvəzinə event payload-ına data daxil et. Tradeoff: event böyüyür, stale snapshot riski. PII data (email) üçün ehtiyatlı ol.

**4. Polyglot persistence-in əsas çətinliyi nədir?**
Operasional complexity: 4 fərqli DB monitoring, backup, scaling. Cross-store transaction yoxdur — eventual consistency. Team hər DB-ni bilməlidir. Data sync: MySQL-dən Elasticsearch-a sync lag ola bilər.

**5. Debezium necə konfiqurasiya edilir, nə tələb edir?**
MySQL üçün: `binlog_format=ROW` aktivləşdirilməlidir. Debezium connector Kafka Connect üzərindən işləyir. `database.history.kafka.topic` ilə schema tarixçəsi saxlanılır. Minimal konfiqurasiya: host, port, user (REPLICATION slave privilege), monitored tables. MySQL 5.7+ dəstəklənir.

**6. Tightly vs loosely coupled polyglot persistence fərqi nədir?**
Tightly coupled: app eyni request-də MySQL-ə həm yazır, həm Elasticsearch-a — ikisi atomik deyil, biri fail edərsə inconsistency. Loosely coupled: MySQL-ə yaz → CDC/event → Elasticsearch async sync — eventual consistency, amma daha robust. Loosely coupled tövsiyə edilir.

---

## Anti-patternlər

**1. Materialized view-u manual refresh etmək (production-da)**
`REFRESH MATERIALIZED VIEW` komandası manual çağırılır, unudulur ya da gecikir — istifadəçilər köhnə aggregated data görür. Avtomatik refresh qurun: PostgreSQL-də pg_cron ilə schedule, ya da CDC pipeline-ı refresh-i trigger etsin.

**2. `REFRESH MATERIALIZED VIEW` ilə (CONCURRENTLY olmadan) yüksək trafik zamanı**
`REFRESH MATERIALIZED VIEW mv_stats` — view tam lock götürür, refresh bitənə qədər heç kim oxuya bilmir. `REFRESH MATERIALIZED VIEW CONCURRENTLY` istifadə edin: lock yoxdur, refresh zamanı köhnə data oxunmağa davam edir; bu seçenek unikal index tələb edir.

**3. CDC pipeline-ı olmadan DB-dən Elasticsearch-a sinxronizasiya**
Application-da hər write-dan sonra Elasticsearch-a da yazmaq — iki yazma atomik deyil, biri uğursuz olarsa inconsistency yaranır, kodu qarışdırır. Debezium CDC ilə binlog-dan event-lər oxuyun: application kodu dəyişmədən, gecikmə minimumda Elasticsearch yenilənir.

**4. Polyglot persistence-i gərəksiz yerdə tətbiq etmək**
Sadə tətbiq üçün MySQL + MongoDB + Elasticsearch + Redis qurmaq — operasional yük ağır, hər DB-nin ayrı backup, monitoring, disaster recovery proseduru lazımdır. Bir DB-dən başlayın; yeni storage yalnız real tələb (full-text search, time-series) yarandıqda əlavə edin.

**5. Event-carried State Transfer-də PII data göndərmək**
Event payload-ına istifadəçinin email, şifrə, şəxsiyyət nömrəsi daxil etmək — broker log-larında PII görünür, GDPR pozuntusu yaranır. Event-ə yalnız ID göndərin; consumer əlavə data lazımdırsa API vasitəsilə alsın; PII-yə ehtiyac varsa şifrələyin.

**6. CDC consumer-ını idempotent yazmamaq**
Debezium event-ləri at-least-once delivery ilə göndərir — network bölünməsindən sonra eyni DB event iki dəfə gəlir, consumer iki dəfə Elasticsearch-a yazır, data corrupted olur. Hər CDC consumer-ı idempotent yazın: event-in `binlog_position` ya da `lsn`-ini unikal key kimi istifadə edin.
