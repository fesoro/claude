# UUID vs Auto-Increment IDs (Middle ⭐⭐)

## İcmal
Primary key seçimi görünən qədər sadə deyil. Auto-increment sürətlidir lakin distributed sistemlərdə işləmir; UUID qlobal unikaldır lakin index performance-ı pisdir. Bu sual interview-da data modeling qərar vermə bacarığınızı yoxlayır.

## Niyə Vacibdir
ID strategiyası database performance-ını, security-ni (enumerable IDs), sharding qabiliyyətini, ve microservice arxitekturasını birbaşa təsir edir. İnterviewer sizin bu trade-off-ları bildiyinizi, ULID/UUID v7 kimi müasir alternativləri tanıyıb-tanımadığınızı yoxlayır.

## Əsas Anlayışlar

- **Auto-Increment (SERIAL/BIGSERIAL):** Sequential, monoton artan integer. B-Tree index üçün ideal — yeni row həmişə sonuna yazılır, page split yoxdur. Lakin distributed sistemlərdə fərqli node-lar eyni ID-ni yarada bilər
- **UUID v4:** 128-bit random — qlobal unikal, heç bir koordinasiya lazım deyil. Lakin tamamilə random olduğundan B-Tree index-ə yazılanda random leaf node-a gedir → page split, bloat
- **B-Tree Fragmentation:** UUID v4 ilə hər yeni INSERT random yerə gedir. Index page-ləri "boş qalır" çünki ortaya insertion olur. Sequential ID ilə həmişə sona yazılır → sıxlıq maksimal
- **UUID v7:** Time-ordered UUID — ilk 48 bit timestamp, sonrakı bitlər random. Monoton artır → B-Tree üçün sequential kimidir. Qlobal unikal + sortable
- **ULID (Universally Unique Lexicographically Sortable Identifier):** 48 bit timestamp + 80 bit random. Crockford Base32 ilə encode olunur — `01HYEBBK4QWDQ5A8XKJBMQZ8RV`. Case-insensitive, URL-safe, 26 karakter
- **Snowflake ID:** Twitter-in distributed ID formatı. 64-bit: 41 bit timestamp + 10 bit worker ID + 12 bit sequence. Per-millisecond 4096 ID yaratmaq mümkün. Sortable, compact (bigint-dir)
- **Security (IDOR — Insecure Direct Object Reference):** Sequential ID URL-də görünürsə — `/orders/1001`, `/orders/1002` — başqasının məlumatlarına access-i enumerate etmək asandır. UUID bu riski azaldır
- **Natural Key vs Surrogate Key:** E-mail, telefon, SSN natural key-dir lakin dəyişə bilər (e-mail dəyişilir). Surrogate key (integer ya UUID) stabıldır, dəyişmir
- **Composite Key:** Çox column-dan ibarət primary key — join-ləri çətinləşdirir, ORM-lərdə işləmək narahatdır, foreign key-lər böyüyür
- **Global Uniqueness:** Microservices arxitekturasında fərqli servislərdən gələn data birləşdirilərsə (data lake, event store) ID conflict olmamalıdır
- **Client-side ID Generation:** UUID/ULID client tərəfindən yaranıb server-ə göndərilə bilər — idempotent POST-lar üçün ideal (retry zamanı eyni ID göndərilir → duplicate yaranmır)
- **Index Size Fərqi:** UUID 16 byte, bigint 8 byte. 10 milyon row + 5 foreign key olan böyük sistemdə bu fərq GB-larla indeks artımı deməkdir
- **ObjectId (MongoDB):** 12 byte: 4 byte timestamp + 5 byte random + 3 byte counter. Sortable, compact, MongoDB-nin default-u
- **Dual ID Pattern:** Internal bigint (performance) + external UUID/ULID (API exposure). İnternals-da sürətli, xarici dünyaya güvənlidir
- **CUID2 / NanoID:** Application-level ID alternativləri — URL-safe, collision-resistant, compact

## Praktik Baxış

**Interview-da yanaşma:**
- "Hansı sistemi dizayn edirik?" sualı ilə başlayın — single DB vs distributed vs microservices
- Sequential ID security riskini mütləq qeyd edin
- UUID v7 / ULID-i modern alternativ kimi təqdim edin — "UUID v4-ü heç istifadə etməyin, UUID v7 ya ULID seçin"

**Follow-up suallar:**
- "UUID-nin index performance-ına təsiri niyədir?" — Random insertion → B-Tree page split → fragmentation → bloat → slow read
- "Microservices-də auto-increment niyə problem yaradır?" — Servis A id=1, Servis B də id=1 yaradır, data merge edəndə conflict
- "Snowflake ID-nin dezavantajı nədir?" — Clock skew (server saatı geri getsə ID conflict), worker ID idarəetməsi lazımdır
- "Client-generated ID-nin faydası nədir?" — Idempotent POST: eyni ID ilə ikinci request duplicate yaratmır
- "UUID v7 ilə UUID v4 arasındakı performans fərqi nə qədərdir?" — Praktiki: 10M row-da INSERT ~3-5x sürətli, index ölçüsü ~20-30% kiçik

**Ümumi səhvlər:**
- "UUID həmişə daha yaxşıdır" demək — index performance cost-unu qeyd etməmək
- Sequential ID security riskini qeyd etməmək — IDOR real vulnerability-dir
- B-Tree fragmentation izah etməmək — "niyə yavaşdır?" sualına cavab verə bilməmək
- UUID v4 tövsiyə etmək — UUID v7 ya ULID daha müasir seçimdir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- UUID v7 / ULID-i bilmək və niyə daha yaxşı olduğunu izah etmək
- "Biz internal resource-lar üçün bigint, external API üçün ULID istifadə etdik" — real nümunə
- Index size fərqini foreign key-lərdə hesablamaq
- Client-side ID generation pattern-ını bilmək

## Nümunələr

### Tipik Interview Sualı
"E-commerce API-nızdakı `/orders/{id}` endpoint-ında hansı ID tipi istifadə edərdiniz? Niyə? Security riskini necə düşünürsünüz?"

### Güclü Cavab
Mən iki qatlı yanaşma istifadə edərdim: daxili (internal) `bigint` primary key + xarici (external) `ULID` ya da `UUID v7`.

**Daxili bigint:** Sequential — performanslı B-Tree index, JOIN-lər sürətli, disk ölçüsü kiçik. FK-lar 8 byte.

**Xarici ULID:** API-da expose olunan ID. Sequential deyil → IDOR attack-dan qoruyur: `/orders/01HYEBBK4Q` — başqasının order-ini tapmaq enum attack-la praktik deyil. Həm də time-ordered olduğundan B-Tree index üçün yaxşıdır.

UUID v4-dən qaçınardım — tamamilə random, yüksək write workload-da index fragmentation ciddi problem yaradır. UUID v7 eyni güvəncəni verir lakin time-prefix sayəsində B-Tree-yə sequential kimi yazılır.

Microservices kontekstindəsə — order service, payment service, notification service — hər servis öz ULID-lərini müstəqil generate edir, conflict riski yoxdur, data lake-də merge zamanı problem yaranmır.

### Kod Nümunəsi
```sql
-- Auto-increment (PostgreSQL)
CREATE TABLE orders_bigint (
    id          BIGSERIAL PRIMARY KEY,         -- 8 byte, sequential
    public_id   CHAR(26) UNIQUE                -- ULID — API üçün
                DEFAULT NULL,
    user_id     BIGINT NOT NULL,
    total       DECIMAL(10,2),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- UUID v4 (random) — KÖHNƏ YANAŞMA
CREATE TABLE orders_uuid_v4 (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY  -- random → fragmentation
);

-- UUID v7 (time-ordered) — MÜASİR YANAŞMA
-- PostgreSQL 17+ native dəstək:
CREATE TABLE orders_uuid_v7 (
    id UUID DEFAULT uuidv7() PRIMARY KEY  -- time-prefix → sequential kimi
);
-- PostgreSQL 14-16 üçün extension:
CREATE EXTENSION IF NOT EXISTS pg_uuidv7;
CREATE TABLE orders (
    id UUID DEFAULT uuid_generate_v7() PRIMARY KEY
);

-- ULID nümunəsi (application-generated)
-- Format: 01HYEBBK4QWDQ5A8XKJBMQZ8RV
-- İlk 10 karakter = Crockford Base32 timestamp
-- Son 16 karakter = random

-- Index ölçüsünü müqayisə et
CREATE TABLE t_bigint (id BIGINT PRIMARY KEY);
CREATE TABLE t_uuid4  (id UUID    PRIMARY KEY);
CREATE TABLE t_uuid7  (id UUID    PRIMARY KEY);
CREATE TABLE t_ulid   (id CHAR(26) PRIMARY KEY);

-- 1M row insert etdikdən sonra:
SELECT
  relname AS table_name,
  pg_size_pretty(pg_relation_size(oid))   AS table_size,
  pg_size_pretty(pg_indexes_size(oid))    AS index_size
FROM pg_class
WHERE relname IN ('t_bigint', 't_uuid4', 't_uuid7', 't_ulid')
  AND relkind = 'r';
-- Nəticə (approximate):
-- t_bigint: table 35MB, index 22MB
-- t_uuid4:  table 65MB, index 57MB  (fragmented!)
-- t_uuid7:  table 65MB, index 37MB  (sequential, less bloat)
-- t_ulid:   table 70MB, index 42MB
```

```php
// Laravel-də ULID istifadəsi (Laravel 9+)
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Order extends Model
{
    use HasUlids;
    // Primary key avtomatik ULID olur
    // id: 01HYEBBK4QWDQ5A8XKJBMQZ8RV

    // Route model binding — url-safe
    public function getRouteKeyName(): string
    {
        return 'id'; // ULID public-dir, security problemi yox
    }
}

// Dual ID pattern
class Order extends Model
{
    // internal: bigint (performans)
    // public_id: ULID (API exposure)

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    // API-da public_id ilə route bind et
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}

// Migration
Schema::create('orders', function (Blueprint $table) {
    $table->id();                          // bigint AUTOINCREMENT — internal
    $table->char('public_id', 26)->unique(); // ULID — API
    $table->foreignId('user_id')->constrained();
    $table->decimal('total', 10, 2);
    $table->timestamps();

    // Index
    $table->index('public_id');
    $table->index('created_at');
});
```

```php
// Client-side ID generation — idempotent POST
// Client ULID yaradır, serverə göndərir
// Retry-da eyni ULID → duplicate yaranmır

// Client tərəfi (JavaScript):
// const orderId = ulid(); // "01HYEBBK4QWDQ5A8XKJBMQZ8RV"
// fetch('/api/orders', { method: 'POST', body: JSON.stringify({ id: orderId, ... }) })

// Server tərəfi (Laravel):
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $orderId = $request->input('id'); // Client-generated ULID

        // Idempotent: eyni ID ikinci dəfə gəlirsə 200 qaytar, 201 deyil
        $order = Order::firstOrCreate(
            ['public_id' => $orderId],
            [
                'user_id' => auth()->id(),
                'total'   => $request->input('total'),
                'status'  => 'pending',
            ]
        );

        $statusCode = $order->wasRecentlyCreated ? 201 : 200;
        return response()->json($order, $statusCode);
    }
}
```

```java
// Java-da Snowflake ID generatoru
public class SnowflakeIdGenerator {
    private static final long EPOCH           = 1700000000000L; // Custom epoch (2023-11-15)
    private static final int  WORKER_BITS     = 10;
    private static final int  SEQUENCE_BITS   = 12;
    private static final long MAX_WORKER_ID   = ~(-1L << WORKER_BITS);  // 1023
    private static final long MAX_SEQUENCE    = ~(-1L << SEQUENCE_BITS); // 4095
    private static final int  TIMESTAMP_SHIFT = WORKER_BITS + SEQUENCE_BITS;
    private static final int  WORKER_SHIFT    = SEQUENCE_BITS;

    private final long workerId;
    private long       lastTimestamp = -1L;
    private long       sequence      = 0L;

    public SnowflakeIdGenerator(long workerId) {
        if (workerId > MAX_WORKER_ID || workerId < 0) {
            throw new IllegalArgumentException("Worker ID out of range");
        }
        this.workerId = workerId;
    }

    public synchronized long nextId() {
        long timestamp = System.currentTimeMillis() - EPOCH;

        if (timestamp < lastTimestamp) {
            throw new RuntimeException("Clock moved backwards! Refusing to generate ID.");
        }

        if (timestamp == lastTimestamp) {
            sequence = (sequence + 1) & MAX_SEQUENCE;
            if (sequence == 0) {
                // 4096 ID/ms limitinə çatdıq — növbəti ms-ə qədər gözlə
                timestamp = waitForNextMillis(lastTimestamp);
            }
        } else {
            sequence = 0;
        }

        lastTimestamp = timestamp;

        return (timestamp  << TIMESTAMP_SHIFT)
             | (workerId   << WORKER_SHIFT)
             | sequence;
    }

    private long waitForNextMillis(long lastTs) {
        long ts = System.currentTimeMillis() - EPOCH;
        while (ts <= lastTs) {
            ts = System.currentTimeMillis() - EPOCH;
        }
        return ts;
    }

    // ID-dən timestamp extract et
    public static long extractTimestamp(long id) {
        return (id >> TIMESTAMP_SHIFT) + EPOCH;
    }
}

// İstifadə:
// SnowflakeIdGenerator gen = new SnowflakeIdGenerator(workerId);
// long id = gen.nextId();
// 64-bit integer: sortable, compact, 4096 ID/ms/worker
// Nəticə nümunəsi: 7125349387234304512
```

### İkinci Nümunə — Security Test

```bash
# IDOR Attack nümunəsi — sequential ID ilə
# Attacker öz order-ini tapır: GET /api/orders/1001
# Sonra: GET /api/orders/1000, /999, /998 ... → başqasının orderləri!

# UUID/ULID ilə bu mümkün deyil:
# GET /api/orders/01HYEBBK4QWDQ5A8XKJBMQZ8RV
# Növbəti ULID-i tapmaq praktiki cəhətdən mümkün deyil

# Benchmark: UUID v4 vs UUID v7 index performansı
# pgbench ilə 1M row INSERT:
pgbench -c 10 -j 4 -t 100000 -f uuid_v4_insert.sql mydb  # UUID v4
pgbench -c 10 -j 4 -t 100000 -f uuid_v7_insert.sql mydb  # UUID v7
# Nəticə: UUID v7 adətən 2-4x daha sürətli INSERT

# Index bloat yoxla
SELECT
  indexrelname,
  pg_size_pretty(pg_relation_size(indexrelid)) AS index_size,
  idx_scan, idx_tup_read
FROM pg_stat_user_indexes
WHERE relname IN ('t_uuid4', 't_uuid7')
ORDER BY pg_relation_size(indexrelid) DESC;
```

## Praktik Tapşırıqlar

- 1M row insert edin: UUID v4 vs UUID v7 vs ULID vs bigint — B-Tree index ölçüsünü və INSERT süratini ölçün; `pg_stat_user_indexes`-dən nəticələri müqayisə edin
- `/orders/{id}` endpoint-ını sequential bigint ilə test edin — başqa user-in order-ini görə bilirsinizmi? Sonra ULID əlavə edib eyni testi edin
- Laravel-in `HasUlids` trait-ini araşdırın, route model binding-i `public_id` üzərindən qurun
- Snowflake ID generatoru yazın, generate edilmiş ID-dən timestamp-ı extract edin, decode edin
- Dual ID pattern implement edin: internal bigint + external ULID, API endpoint-ları `public_id` üzərindən qeyd edin
- Client-side ID generation ilə idempotent POST test edin: eyni ULID ilə 3 dəfə eyni request göndərin, yalnız bir record yaranır?

## Əlaqəli Mövzular
- `04-index-types.md` — UUID-nin B-Tree fragmentation problemi dərindən izah olunur
- `15-soft-delete-patterns.md` — ID seçimi + soft delete pattern birlikdə
- `03-normalization-denormalization.md` — Foreign key ölçüsü denormalization qərarına təsir edir
- `17-polyglot-persistence.md` — Distributed sistemdə global unique ID strategiyası
