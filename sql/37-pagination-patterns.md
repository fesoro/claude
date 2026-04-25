# Pagination Patterns (Offset, Cursor, Keyset/Seek) (Middle)

## Pagination niye lazimdir?

Boyuk dataset-i bir defede frontend-e gondermek olmaz - memory bitir, network yavasdir, user gorunmeyen 10000 row-u oxumur. Pagination dataset-i kicik "page"-lere boler.

Uc esas pattern var:

| Pattern | URL gorunusu | Performance | Real-time data |
|---------|--------------|-------------|----------------|
| **Offset/Limit** | `?page=5&per_page=20` | Yavaslayir (deep page) | Tutarsiz (data deyiserse) |
| **Keyset/Seek** | `?after_id=12345` | Daimi suretli | Tutarli |
| **Cursor** | `?cursor=eyJpZCI6MTIzfQ==` | Daimi suretli | Tutarli |

---

## OFFSET / LIMIT - Klassik amma problemli

```sql
-- Sehife 1
SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 0;

-- Sehife 2
SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 20;

-- Sehife 5000 (problem!)
SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 99980;
```

### Niye deep pagination yavasdir?

Database `OFFSET 99980` deyende **99980 row-u oxuyub atir**, sonra 20-ni qaytarir.

```
EXPLAIN ANALYZE SELECT * FROM orders ORDER BY id LIMIT 20 OFFSET 100000;

-> Limit  (cost=15234.56..15237.60 rows=20)
   ->  Index Scan on orders  (rows=100020 read, 100000 discarded)
   Execution time: 245 ms
```

Page 1: 5ms. Page 5000: 245ms. Page 50000: 2400ms - **lineyer artir**!

### COUNT(*) bahasi

`paginate()` Laravel-de avtomatik `SELECT COUNT(*)` cagirir. Bu da yavas:

```sql
SELECT COUNT(*) FROM orders WHERE status = 'pending';
-- 50 milyon row var? Full scan ola biler. 5+ saniye!
```

**Alternativler:**

```sql
-- 1. Approximate count (PostgreSQL)
SELECT reltuples::BIGINT AS estimate
FROM pg_class WHERE relname = 'orders';

-- 2. MySQL: information_schema (qeyri-deqiq amma surətli)
SELECT TABLE_ROWS FROM information_schema.TABLES 
WHERE TABLE_NAME = 'orders';

-- 3. Cached counter (Redis)
-- App layer: order yaradanda Redis-de INCR
$count = Redis::get('orders:count') ?? Order::count();

-- 4. Materialized view (real-time olmayan)
CREATE MATERIALIZED VIEW order_counts AS
SELECT status, COUNT(*) FROM orders GROUP BY status;
```

### Tutarsizliq problemi

Page 1 yukleyirsen, sonra yeni order daxil olur, page 2-de **eyni row-u** gorursen (shift olur):

```
T=0: SELECT ... ORDER BY id DESC LIMIT 20 OFFSET 0
     [105, 104, 103, ..., 86]
T=1: Yeni order id=106 daxil olur
T=2: SELECT ... ORDER BY id DESC LIMIT 20 OFFSET 20
     [86, 85, 84, ..., 67]  -- 86 tekrarlandi!
```

---

## Keyset / Seek Pagination

Yerine `OFFSET` istifade etme - **son row-un id-sinden sonra** gel.

```sql
-- Page 1
SELECT * FROM orders ORDER BY id DESC LIMIT 20;
-- Son id = 86

-- Page 2 (id < 86)
SELECT * FROM orders WHERE id < 86 ORDER BY id DESC LIMIT 20;
-- Son id = 67

-- Page 3
SELECT * FROM orders WHERE id < 67 ORDER BY id DESC LIMIT 20;
```

**Index** uzerinde `WHERE id < ?` direct seek-dir - O(log N), heç vaxt yavaslamir!

```
Page 1:    5 ms
Page 100:  5 ms
Page 5000: 5 ms  ← daimi!
```

### Composite Keyset (created_at + id tiebreaker)

`created_at` unique deyilse - eyni saniyede 5 order ola biler. Tiebreaker lazimdir:

```sql
-- Index
CREATE INDEX idx_orders_created_id ON orders (created_at DESC, id DESC);

-- Page 1
SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 20;
-- Son: created_at = '2026-04-24 10:30:15', id = 4501

-- Page 2 (composite key tuple comparison)
SELECT * FROM orders 
WHERE (created_at, id) < ('2026-04-24 10:30:15', 4501)
ORDER BY created_at DESC, id DESC LIMIT 20;
```

Bezi DB tuple comparison desteklemir - manual yaz:

```sql
SELECT * FROM orders 
WHERE created_at < '2026-04-24 10:30:15'
   OR (created_at = '2026-04-24 10:30:15' AND id < 4501)
ORDER BY created_at DESC, id DESC LIMIT 20;
```

### Keyset-in mehdudiyyetleri

- **Random access yoxdur** - "sehife 500-e get" mumkun deyil, ardicil oxumalisan
- **"Previous page"** ucun ORDER BY-i terse cevirmek lazimdir
- **Total count** ile uygunlasmir - "Page 5 of 200" gostere bilmezsen

---

## Cursor Pagination - Encoded Token

Keyset-in elaqedar versiyasi - state-i opaque cursor icine paketle:

```php
// Cursor yaratmaq
$cursor = base64_encode(json_encode([
    'created_at' => '2026-04-24 10:30:15',
    'id' => 4501,
]));
// Netice: "eyJjcmVhdGVkX2F0IjoiMjAyNi0wNC0yNCAxMDozMDoxNSIsImlkIjo0NTAxfQ=="

// API response
return [
    'data' => $orders,
    'next_cursor' => $cursor,
    'prev_cursor' => $prevCursor,
];
```

**Frontend** sadece cursor-u sonraki request-e gondərir, manaminin bilmesine ehtiyac yoxdur.

```
GET /orders?cursor=eyJjcmVhdGVkX2F0IjoiMjAyNi0wNC0yNCAxMDozMDoxNSIsImlkIjo0NTAxfQ==
```

### Cursor security

Cursor encrypt et / sign et - user-in deyismeyini onle:

```php
// Sign with HMAC
$payload = json_encode($data);
$signature = hash_hmac('sha256', $payload, config('app.key'));
$cursor = base64_encode($payload . '.' . $signature);

// Verify
[$payload, $sig] = explode('.', base64_decode($cursor));
if (!hash_equals(hash_hmac('sha256', $payload, config('app.key')), $sig)) {
    abort(400, 'Invalid cursor');
}
```

---

## Laravel: paginate() vs simplePaginate() vs cursorPaginate()

| Method | SQL | UI gosterir | Performance |
|--------|-----|-------------|-------------|
| `paginate()` | LIMIT + OFFSET + COUNT(*) | "Page 5 of 200" | Yavaslayir (deep) |
| `simplePaginate()` | LIMIT+1 (next var?) | "Previous / Next" | Orta (hele OFFSET) |
| `cursorPaginate()` | WHERE id > ? LIMIT | "Previous / Next" | Daimi suretli |

```php
// 1. Full pagination (COUNT + OFFSET)
$orders = Order::orderBy('id', 'desc')->paginate(20);
// SELECT COUNT(*) FROM orders;
// SELECT * FROM orders ORDER BY id DESC LIMIT 20 OFFSET 0;

// 2. Simple pagination (no COUNT, hele OFFSET)
$orders = Order::orderBy('id', 'desc')->simplePaginate(20);
// SELECT * FROM orders ORDER BY id DESC LIMIT 21 OFFSET 0;

// 3. Cursor pagination (Laravel 8+)
$orders = Order::orderBy('id', 'desc')->cursorPaginate(20);
// SELECT * FROM orders ORDER BY id DESC LIMIT 21;
// Sonra: SELECT * FROM orders WHERE id < ? ORDER BY id DESC LIMIT 21;
```

**cursorPaginate()** avtomatik composite key idare edir:

```php
$orders = Order::orderBy('created_at', 'desc')
    ->orderBy('id', 'desc')
    ->cursorPaginate(20);
// Avtomatik tuple comparison qurur
```

### Hansini secmeli?

```
Admin panel "Page 5 of 200" lazimdirmi?    → paginate()
Sade "Next/Previous" kifayetdir?           → simplePaginate()
Mobile feed, infinite scroll?              → cursorPaginate()
Cox boyuk table (10M+)?                    → cursorPaginate() MEHCBURI
```

---

## Infinite Scroll - Real Pattern

```php
// API endpoint
public function feed(Request $request) {
    return Post::orderBy('id', 'desc')
        ->cursorPaginate(20);
}
```

```javascript
// Frontend (React)
const [posts, setPosts] = useState([]);
const [cursor, setCursor] = useState(null);

const loadMore = async () => {
    const url = cursor ? `/api/feed?cursor=${cursor}` : '/api/feed';
    const res = await fetch(url);
    const data = await res.json();
    setPosts([...posts, ...data.data]);
    setCursor(data.next_cursor);
};

// IntersectionObserver: page bottom-a catanda loadMore() cagir
```

---

## Eloquent Bulk Iteration: chunk() vs lazy()

Boyuk table-i process etmek (export, batch job)? `Model::all()` istifade etme - memory bitir!

| Method | Memory | SQL | Skip/missing risk |
|--------|--------|-----|--------------------|
| `chunk(N)` | Page-page | OFFSET-based | UPDATE-de skip ola biler |
| `chunkById(N)` | Page-page | id > ? | Tehlukesiz |
| `lazy(N)` | Generator | OFFSET-based | UPDATE-de skip |
| `lazyById(N)` | Generator | id > ? | Tehlukesiz |
| `cursor()` | Single row at a time | Tek query | PHP memory zeif |

### chunk() - tehlukeli pattern

```php
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        $user->update(['processed' => true]);  // PROBLEM!
    }
});
```

**Niye problem?** OFFSET ile isleyir:
```
Batch 1: LIMIT 100 OFFSET 0   → id 1-100
Batch 2: LIMIT 100 OFFSET 100 → id 201-300 (101-200 SKIP!)
```

`processed = true` olduqda, query filter-i digerlerini sizirir, OFFSET sehv olur.

### chunkById() - dogru variant

```php
User::where('processed', false)
    ->chunkById(100, function ($users) {
        foreach ($users as $user) {
            $user->update(['processed' => true]);
        }
    });
// SQL: WHERE processed = 0 AND id > ? ORDER BY id LIMIT 100
// id-ye gore irelileyir, skip yoxdur
```

### lazy() - generator versiyasi

```php
foreach (User::lazy(1000) as $user) {
    $user->processSomething();
}
// chunk()-un foreach-li versiyasi - daha temiz syntax
```

### cursor() - tek query, low memory

```php
foreach (User::cursor() as $user) {
    // Tek SELECT *, amma row-row yield
    $user->processSomething();
}
// PDO unbuffered query - memory az amma DB connection acliq
```

> **Diqqet:** `cursor()` MySQL-de `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` lazimdir. Eks halda yene memory dolur.

---

## Benchmark - Real Numbers

10 milyon row `orders` table-i, page size 20:

| Page | OFFSET | Keyset |
|------|--------|--------|
| 1 | 8 ms | 5 ms |
| 100 | 45 ms | 5 ms |
| 1000 | 380 ms | 5 ms |
| 10000 | 3800 ms | 5 ms |
| 100000 | 38000 ms | 5 ms |

`COUNT(*)` ayri:
- Index uzre `WHERE status = ?` COUNT: 850 ms
- `reltuples` estimate: 0.5 ms

---

## Anti-Pattern: ORDER BY RAND()

"Random row" gostermek ucun:

```sql
-- COX YAVASLI - butun table-i random sira ile sirelayir!
SELECT * FROM products ORDER BY RAND() LIMIT 10;
```

10 milyon row → 30 saniye+. **Yerine:**

```sql
-- 1. Random ID range (unique, no gaps)
SELECT * FROM products 
WHERE id >= (SELECT FLOOR(RAND() * (SELECT MAX(id) FROM products)))
LIMIT 10;

-- 2. PostgreSQL: TABLESAMPLE
SELECT * FROM products TABLESAMPLE SYSTEM (1) LIMIT 10;

-- 3. Pre-computed random column + index
ALTER TABLE products ADD COLUMN random_sort FLOAT DEFAULT RAND();
CREATE INDEX idx_random ON products (random_sort);
SELECT * FROM products WHERE random_sort > RAND() LIMIT 10;
```

---

## Interview suallari

**Q: Niye OFFSET 100000 ile pagination yavasdir?**
A: Database `OFFSET N` deyende N row-u oxumalidir, sonra atir, qalanini qaytarir. Index olsa bele, 100000 row scan + discard edir. Keyset pagination (`WHERE id > last_seen_id`) bunu O(log N) edir cunki birbasa o noqteye seek olur.

**Q: cursorPaginate() ile simplePaginate() arasinda secim?**
A: Read-heavy ve real-time data daxil olursa - `cursorPaginate()` hemise tutarlidir (yeni row daxil olsa bele duplicate gormezsen). `simplePaginate()` hele OFFSET istifade edir, hem yavas hem tutarsiz. Yalniz read-only static data ucun simplePaginate() ok.

**Q: Niye composite keyset-de tiebreaker (id) lazimdir?**
A: `ORDER BY created_at DESC` - eger 2 row eyni created_at-a sahibdirse, sira non-deterministicdir. Pagination zamani bezisi atlana ve ya tekrarlana biler. `(created_at, id)` tuple unique-dir, deterministic ordering verir.

**Q: chunk() vs chunkById() - hansini ne vaxt?**
A: chunkById() hemise daha tehlukesizdir, xususile UPDATE/DELETE eden iteration-larda. chunk() OFFSET istifade edir - eger filter-deki sutunu deyisirsen, OFFSET shift olur ve row skip ola biler. chunkById() id-ye gore irelileyir, bu problem yoxdur. Read-only iteration-da ferq yoxdur.

**Q: COUNT(*) ucun alternativler hansidir?**
A: 1) PostgreSQL `pg_class.reltuples` - approximate, ANALYZE sonra dogrulanir. 2) MySQL `information_schema.TABLES.TABLE_ROWS` - estimate. 3) Redis-de manual counter (INCR/DECR INSERT/DELETE-de). 4) Materialized view periodically refresh. 5) Estimated count + "Show exact count" button (lazy load).
