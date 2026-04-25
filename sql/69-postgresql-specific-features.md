# PostgreSQL Specific Features (LISTEN/NOTIFY, Advisory Locks, RLS, Arrays) (Senior)

PostgreSQL MySQL-den farqli olaraq boyuk movzuda extra feature destekleyir. Bu feature-lar adeten Redis/RabbitMQ/middleware-i evez ede biler.

---

## 1. LISTEN / NOTIFY (Built-in Pub/Sub)

PostgreSQL-in daxili pub/sub mexanizmi. Redis pub/sub-a oxsar, amma DB-nin daxilindedir.

```sql
-- Subscriber
LISTEN cache_invalidate;

-- Publisher (basqa session)
NOTIFY cache_invalidate, 'user:123';
-- Yaxud:
SELECT pg_notify('cache_invalidate', 'user:123');
```

**Xususiyyetler:**
- Payload limit: **8000 byte** (deyismek mumkun deyil)
- Ayni transaction icinde NOTIFY -> COMMIT-den sonra deliver olunur
- Subscriber offline olsa, mesaj itir (queue yoxdur)
- Same database-de isleyir

### Use Case 1: Cache Invalidation

```sql
-- Trigger: row deyisende NOTIFY
CREATE OR REPLACE FUNCTION notify_user_change() RETURNS TRIGGER AS $$
BEGIN
    PERFORM pg_notify('user_changed', json_build_object(
        'id', NEW.id,
        'action', TG_OP
    )::text);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER user_change_trigger
AFTER INSERT OR UPDATE OR DELETE ON users
FOR EACH ROW EXECUTE FUNCTION notify_user_change();
```

### PHP Subscriber

```php
$pdo = new PDO('pgsql:host=localhost;dbname=app', 'user', 'pass');
$pdo->exec('LISTEN user_changed');

while (true) {
    $result = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 5000); // 5 sn timeout
    if ($result) {
        $payload = json_decode($result['payload'], true);
        Cache::forget('user:' . $payload['id']);
        echo "Invalidated: user {$payload['id']}\n";
    }
}
```

### Laravel Long-Running Worker

```php
// app/Console/Commands/ListenForChanges.php
class ListenForChanges extends Command
{
    protected $signature = 'listen:changes';
    
    public function handle(): void
    {
        $pdo = DB::connection('pgsql')->getPdo();
        $pdo->exec('LISTEN user_changed');
        
        while (! $this->shouldStop()) {
            $notification = $pdo->pgsqlGetNotify(PDO::FETCH_ASSOC, 1000);
            if ($notification) {
                $this->handleNotification($notification);
            }
        }
    }
}
```

**Limitations:**
- 8KB payload kicik - boyuk data ucun ID gonder, sonra fetch et
- HA cluster-de notification yalniz local node-da cap olunur
- Boyuk volume-de Redis pub/sub yaxud Kafka istifade et

---

## 2. Advisory Locks

User-defined lock-lar. DB schema-ya bagli deyil, application-level mutex.

```sql
-- Session-level lock (session sonunda azad olur)
SELECT pg_advisory_lock(12345);
-- ... critical section ...
SELECT pg_advisory_unlock(12345);

-- Transaction-level lock (COMMIT/ROLLBACK-de azad olur)
BEGIN;
SELECT pg_advisory_xact_lock(12345);
-- ... critical section ...
COMMIT;

-- Non-blocking try
SELECT pg_try_advisory_lock(12345);
-- true = aldim, false = baska tutub
```

### Use Case: Cron Job Leader Election

Cox server-li environment-de eyni cron 5 defe islememeli:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        $lockId = crc32('daily-report-job');
        
        $acquired = DB::selectOne(
            "SELECT pg_try_advisory_lock(?) AS acquired", [$lockId]
        )->acquired;
        
        if (! $acquired) {
            return; // baska server isleyir
        }
        
        try {
            $this->generateDailyReport();
        } finally {
            DB::statement("SELECT pg_advisory_unlock(?)", [$lockId]);
        }
    })->daily();
}
```

### Use Case: Distributed Mutex

```php
class AdvisoryLock
{
    public static function withLock(string $key, callable $callback)
    {
        $lockId = crc32($key);
        
        DB::beginTransaction();
        try {
            DB::selectOne("SELECT pg_advisory_xact_lock(?)", [$lockId]);
            $result = $callback();
            DB::commit();
            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// Istifade
AdvisoryLock::withLock("user-{$userId}-process", function () use ($userId) {
    // Eyni anda yalniz 1 process bu user ucun isleyir
    User::find($userId)->processSomething();
});
```

| Lock type | Scope | Manual unlock | Use case |
|-----------|-------|---------------|----------|
| `pg_advisory_lock` | Session | Lazimdir | Long-running worker |
| `pg_advisory_xact_lock` | Transaction | Yox (COMMIT-de) | Web request critical path |
| `pg_try_advisory_lock` | Session | Lazimdir | Leader election (skip if locked) |
| `pg_advisory_lock_shared` | Session, shared | Lazimdir | Read-many, write-once |

---

## 3. Row-Level Security (RLS)

DB seviyyesinde row visibility/access nezareti. Multi-tenant SaaS ucun guclu.

### Sade Misal: Multi-Tenant

```sql
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NOT NULL,
    amount DECIMAL(12,2),
    description TEXT
);

-- RLS aktiv et
ALTER TABLE invoices ENABLE ROW LEVEL SECURITY;

-- Policy: yalniz oz tenant-i gor
CREATE POLICY tenant_isolation ON invoices
    USING (tenant_id = current_setting('app.current_tenant')::BIGINT);

-- Policy: yalniz oz tenant-ine yaza biler
CREATE POLICY tenant_write ON invoices
    FOR INSERT WITH CHECK (tenant_id = current_setting('app.current_tenant')::BIGINT);
```

### Laravel Middleware

```php
class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = auth()->user()->tenant_id;
        
        DB::statement("SET LOCAL app.current_tenant = ?", [$tenantId]);
        
        return $next($request);
    }
}
```

```php
// Indi her query avtomatik filter olunur:
Invoice::all(); 
// Daxilde: SELECT * FROM invoices WHERE tenant_id = current_setting(...)
// Application-da WHERE elave etmek lazim deyil!
```

### USING vs WITH CHECK

| Clause | Ne icin | Misal |
|--------|---------|-------|
| `USING` | SELECT/UPDATE/DELETE filter | "yalniz oz row-larini gor" |
| `WITH CHECK` | INSERT/UPDATE constraint | "baska tenant-e yaza bilmez" |

```sql
CREATE POLICY user_data ON documents
    FOR ALL                              -- SELECT, INSERT, UPDATE, DELETE
    USING (owner_id = current_user_id()) -- gorme
    WITH CHECK (owner_id = current_user_id()); -- yazma
```

### Force RLS for Owner

```sql
-- Default: table owner ve superuser RLS-i bypass edir
-- Force et:
ALTER TABLE invoices FORCE ROW LEVEL SECURITY;
```

### Limitations:
- Performance overhead (her query-de policy expression check)
- Migration-larda RLS-i muveqqeti deaktiv lazim ola biler
- Application bug-i (tenant context set olmasa) - hec ne gormez (failure-safe)

---

## 4. Array Types

PostgreSQL native array dest verir.

```sql
CREATE TABLE posts (
    id BIGSERIAL PRIMARY KEY,
    title TEXT,
    tags TEXT[],            -- string array
    view_counts INT[],      -- integer array
    metadata JSONB
);

INSERT INTO posts (title, tags) VALUES 
    ('PHP Tips', ARRAY['php', 'laravel', 'tutorial']),
    ('SQL Guide', ARRAY['sql', 'postgresql', 'database']);
```

### Operatorlar

```sql
-- Containment: array tags 'php' var?
SELECT * FROM posts WHERE 'php' = ANY(tags);
SELECT * FROM posts WHERE tags @> ARRAY['php'];  -- contains

-- Overlap: ortak element var?
SELECT * FROM posts WHERE tags && ARRAY['php', 'python'];

-- Length
SELECT title, array_length(tags, 1) FROM posts;

-- Append/remove
UPDATE posts SET tags = array_append(tags, 'beginner') WHERE id = 1;
UPDATE posts SET tags = array_remove(tags, 'tutorial') WHERE id = 1;

-- Unnest (rows-a cevir)
SELECT id, unnest(tags) AS tag FROM posts;
```

### GIN Index (suretli array search)

```sql
CREATE INDEX idx_posts_tags ON posts USING GIN(tags);

-- Bu indi suretlidir:
SELECT * FROM posts WHERE tags @> ARRAY['php'];
```

### Laravel ile

```php
// Migration
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->text('title');
    $table->json('tags'); // Laravel array kimi cast olunur
});

// Query
Post::whereJsonContains('tags', 'php')->get();

// Yaxud raw:
DB::table('posts')->whereRaw("tags @> ARRAY[?]::text[]", ['php'])->get();
```

---

## 5. hstore (Key-Value)

JSONB-den evvel asagi-saviyyeli key-value store. Yeni layihelerde JSONB istifade et.

```sql
CREATE EXTENSION hstore;

CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    attributes hstore
);

INSERT INTO products (attributes) 
    VALUES ('color => "red", size => "large", weight => "2kg"');

SELECT attributes -> 'color' FROM products;  -- 'red'
SELECT * FROM products WHERE attributes ? 'color';  -- color key var?
SELECT * FROM products WHERE attributes @> 'color => "red"';  -- contains
```

> **Yeni layihelerde JSONB istifade et** - daha guclu, daha cevik (nested struct), GIN index destekler.

---

## 6. Range Types

Aralig (interval) tipi - tarix, saat, eded araliqlari.

```sql
-- Built-in tipler:
-- int4range, int8range, numrange, tsrange, tstzrange, daterange

CREATE TABLE bookings (
    id BIGSERIAL PRIMARY KEY,
    room_id INT,
    period TSTZRANGE  -- timestamp with timezone range
);

INSERT INTO bookings (room_id, period) VALUES 
    (1, '[2026-04-25 14:00, 2026-04-25 16:00)'),
    (1, '[2026-04-26 09:00, 2026-04-26 11:00)');

-- Square bracket [ ] = inclusive, paren ( ) = exclusive

-- Overlap check (vacib!)
SELECT * FROM bookings 
WHERE period && '[2026-04-25 15:00, 2026-04-25 17:00)'::tstzrange;
```

### EXCLUDE USING gist (Conflict Prevention)

Eyni room-da overlap-li booking olmasin:

```sql
ALTER TABLE bookings ADD CONSTRAINT no_overlap
    EXCLUDE USING gist (room_id WITH =, period WITH &&);

-- Indi bu INSERT FAIL olur:
INSERT INTO bookings (room_id, period) 
    VALUES (1, '[2026-04-25 15:00, 2026-04-25 17:00)');
-- ERROR: conflicting key value violates exclusion constraint
```

> **Booking sistemi ucun gold standard** - application-level race condition riski tamamile yox edilir.

### Laravel Misal

```php
class BookRoom
{
    public function handle(int $roomId, Carbon $start, Carbon $end): Booking
    {
        try {
            return DB::transaction(fn() => Booking::create([
                'room_id' => $roomId,
                'period' => "[{$start->toIso8601String()}, {$end->toIso8601String()})",
            ]));
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'no_overlap')) {
                throw new RoomNotAvailableException();
            }
            throw $e;
        }
    }
}
```

---

## 7. ENUM Types

```sql
CREATE TYPE order_status AS ENUM ('pending', 'paid', 'shipped', 'delivered', 'cancelled');

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    status order_status NOT NULL DEFAULT 'pending'
);

-- Yeni deyer elave et (yalniz sona, alphabetic deyil)
ALTER TYPE order_status ADD VALUE 'refunded' AFTER 'cancelled';

-- Deyer silinmir (mehdudiyyet) - column tipini deyisib geri qoymaq lazimdir
```

**ENUM vs CHECK constraint:**

```sql
-- ENUM ustunluyu: kicik storage (4 byte vs string size)
-- CHECK constraint dezavantaji: validation slow, deyer deyisende butun row scan

-- Modern tovsiye: VARCHAR + CHECK (cevik)
status VARCHAR(20) CHECK (status IN ('pending', 'paid', ...))
```

Laravel Enum cast (PHP 8.1+):

```php
enum OrderStatus: string {
    case Pending = 'pending';
    case Paid = 'paid';
}

class Order extends Model {
    protected $casts = ['status' => OrderStatus::class];
}
```

---

## 8. Partial Unique Indexes

Yalniz subset row-larda unique:

```sql
-- Soft-delete-de unique email problemi
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(320),
    deleted_at TIMESTAMPTZ
);

-- YANLIS - silinmis user-i tekrar register etmek mumkun deyil
CREATE UNIQUE INDEX ON users (email);

-- DOGRU - yalniz aktiv user-lerde unique
CREATE UNIQUE INDEX uniq_active_email ON users (email) 
    WHERE deleted_at IS NULL;
```

### Misal: Bir user yalniz 1 default address

```sql
CREATE UNIQUE INDEX one_default_address ON addresses (user_id)
    WHERE is_default = true;
```

---

## 9. INCLUDE Indexes (Covering Index)

Index-de elave column saxla (sort/where ucun deyil, retrieval ucun):

```sql
-- Adi index
CREATE INDEX idx_orders_user ON orders (user_id);
SELECT user_id, total FROM orders WHERE user_id = 1;
-- Index seek + heap fetch (total ucun)

-- Covering index
CREATE INDEX idx_orders_user_covering ON orders (user_id) INCLUDE (total, status);
SELECT user_id, total, status FROM orders WHERE user_id = 1;
-- Yalniz index seek - heap fetch yox (Index-Only Scan)
```

**Tradeoff:** Index olcusu artir, amma boyuk read query suretlenir.

---

## 10. Generated Columns

Tovre oluna bilen sutunlar (calculated):

```sql
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    price DECIMAL(10,2),
    discount DECIMAL(3,2),
    final_price DECIMAL(10,2) GENERATED ALWAYS AS (price * (1 - discount)) STORED
);

INSERT INTO products (price, discount) VALUES (100, 0.10);
SELECT final_price FROM products; -- 90.00
```

**STORED vs VIRTUAL:**
- `STORED` - disk-de saxlanir (PostgreSQL 12+)
- `VIRTUAL` - hesablanir runtime-da (MySQL-de var, PostgreSQL-de yox)

```sql
-- Search ucun search_vector generated column
CREATE TABLE articles (
    id BIGSERIAL,
    title TEXT,
    body TEXT,
    search_vector TSVECTOR GENERATED ALWAYS AS (
        to_tsvector('english', coalesce(title, '') || ' ' || coalesce(body, ''))
    ) STORED
);

CREATE INDEX ON articles USING GIN(search_vector);
```

---

## 11. Full-Text Search (tsvector)

```sql
SELECT * FROM articles 
WHERE search_vector @@ to_tsquery('english', 'postgres & performance');

-- Ranking
SELECT id, title,
    ts_rank(search_vector, query) AS rank
FROM articles, to_tsquery('english', 'postgres') AS query
WHERE search_vector @@ query
ORDER BY rank DESC LIMIT 10;
```

**Operatorlar:**
- `&` - AND
- `|` - OR
- `!` - NOT
- `<->` - sequence (`postgres <-> performance` = "postgres performance")
- `:*` - prefix (`postg:*`)

### Laravel Scout PostgreSQL Driver

```php
class Article extends Model {
    use Searchable;
    
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}

Article::search('postgres performance')->get();
```

---

## Summary Cedveli

| Feature | Use Case | Replaces |
|---------|----------|----------|
| LISTEN/NOTIFY | Cache invalidation, real-time events | Redis pub/sub (kicik scale) |
| Advisory Locks | Cron leader election, distributed mutex | Redis SETNX, Zookeeper |
| RLS | Multi-tenant data isolation | Application-level WHERE |
| Arrays | Tags, simple lists | Junction table (kicik scale) |
| Range types + EXCLUDE | Booking, scheduling | Application race-condition logic |
| Partial Index | Soft-delete unique | Filtered constraint |
| INCLUDE Index | Cover query | Multiple compound index |
| Generated Columns | Calculated fields | Application accessor |
| Full-Text Search | Search-in-content | Elasticsearch (kicik scale) |

---

## Interview suallari

**Q: LISTEN/NOTIFY-i ne vaxt Redis pub/sub-dan ustun secersen?**
A: 1) Mevcud Postgres infrastruktur var, elave service istemirsen. 2) Trigger-le DB deyisikliyini publish etmek lazimdir. 3) Mesaj kucukdur (<8KB). 4) Eyni transaction-da publish istesen (COMMIT-den sonra deliver olunur). Redis pub/sub: yuksek throughput, multiple DB across, persistent queue (Streams ile).

**Q: Advisory lock vs row lock vs Redis lock?**
A: Advisory lock - DB row-a baglanmir, application-level mutex (cron leader, distributed semaphore). Row lock (FOR UPDATE) - mueyyen row-i lock, transaction sonu ile bitir. Redis lock - DB-den kenarda, network round-trip var, TTL ile auto-expire. Hesab: critical section DB-dedirse advisory > Redis (latency az).

**Q: RLS-in performance tesiri necedir?**
A: Her query-ye policy expression elave olunur (WHERE clause kimi). Index uygundursa overhead minimaldir. Murekkeb policy (subquery, function call) yavaslada biler. Diqqet: tenant_id-de index olmasa, RLS sequential scan yarada biler. EXPLAIN ANALYZE ile yoxla.

**Q: EXCLUDE USING gist niye unique index-den ustun ola biler?**
A: Unique index = ekzaktly eyni deyer-i bloklayir. EXCLUDE USING gist = istenilen relation-i (overlap, contains) bloklayir. Booking sistemi: 14:00-16:00 + 15:00-17:00 = overlap (eyni deyil, amma intersect). Unique index bunu tutmaz, EXCLUDE tutar. Application race condition tamamile yox edilir DB level-de.

**Q: PostgreSQL array vs junction table?**
A: Array - kicik fixed list (3-10 element), tag-ler. JOIN lazim deyil, GIN index ile suretli search. Junction table - boyuk many-to-many, FK constraint lazim, count/aggregate suretli. Tag-ler ucun array, role-permission ucun junction table. Array dezavantaji: deyisme bahalidir (butun array yeniden yazilir).
