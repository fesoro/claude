# Database Anti-patterns (Senior)

Bu siyahi production-da gorulen, ekserisini ozum yasamis ya da fix etmis senior PHP/Laravel developer-in bilməli oldugu səhvlərdir. Hər biri **anti-pattern → niye pisdir → fix** strukturunda.

---

## 1. EAV (Entity-Attribute-Value) Sui-istifadəsi

**Anti-pattern:**

```sql
CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(255));
CREATE TABLE product_attributes (
    product_id INT, attribute_name VARCHAR(50), attribute_value TEXT
);
-- iPhone color, ram, storage, weight, ... her seyi row kimi
```

**Niye pisdir:** Sade query çətindir (`SELECT * WHERE color='black' AND ram='8GB'` = 2 self-join), tip yoxdur (her sey TEXT), index praktiki deyil, ORM cətindir.

**Fix:**
- Ümumi attribute-lar üçün **column** yarat (`color`, `ram`, `storage`)
- Variable attribute üçün **JSONB column** istifadə et (Postgres):
  ```sql
  ALTER TABLE products ADD COLUMN attrs JSONB;
  CREATE INDEX ON products USING GIN (attrs);
  -- WHERE attrs @> '{"color": "black"}'
  ```
- Real EAV lazimdirsa (Magento), hazir solution istifade et, oz tekerini ixtira etme.

---

## 2. Soft Foreign Key (Constraint Yoxdur)

**Anti-pattern:**

```sql
CREATE TABLE orders (id BIGINT PRIMARY KEY, user_id BIGINT);
-- user_id-ye FK constraint yoxdur, "performance ucun"
```

**Niye pisdir:** Orphan row-lar (user silinir, order qalir), data integrity sira-sira pozulur, JOIN-də gozlenilməyən nəticelər.

**Fix:**

```php
Schema::table('orders', function (Blueprint $t) {
    $t->foreignId('user_id')
      ->constrained()
      ->onDelete('restrict');  // ya da 'cascade' / 'set null'
    $t->index('user_id');  // FK-ye index lazim
});
```

> **Qeyd:** PlanetScale (sharded Vitess) FK destekleyir, amma ortamda diqqətli ol. Real performance problemi olduqda bele FK saxla, sadece check trigger-i optimize et.

---

## 3. Polymorphic Associations (Laravel morphTo) - Hər vaxt deyil

**Anti-pattern:**

```php
// commentable_type, commentable_id
class Comment extends Model {
    public function commentable() { return $this->morphTo(); }
}
```

**Niye pisdir:** FK constraint qoymaq mumkun deyil (commentable_id heç bir konkret table-a bağlı deyil), JOIN murekkebdir, refactor zamanı string-based type-lar pozulur.

**Fix:**
- Az tip varsa (post, video) — **ayrı junction table** (`post_comments`, `video_comments`)
- Cox tip lazimdirsa (10+) — yenə morph qebul olunan ola biler, amma minimum **morphMap** istifade et:
  ```php
  Relation::enforceMorphMap([
      'post' => Post::class,
      'video' => Video::class,
  ]);
  ```
- Trigger ile soft check qur (validation).

---

## 4. God Table (1000 Sutun)

**Anti-pattern:** `users` table-da `address_line_1, address_line_2, billing_address, shipping_address, last_order_id, last_order_total, total_lifetime_value, ...` — 200+ sutun.

**Niye pisdir:** Row size buyuyur (cache miss), `SELECT *` = TB transfer, index spam, schema deyisikliyi cətin, locking artir.

**Fix:**
- **Vertical partition** — `users_addresses`, `users_metrics`, `users_preferences`
- 1:1 relation ile bağla
- Hot/cold data ayır

---

## 5. String-de ID / Tarix / Boolean

**Anti-pattern:**

```sql
CREATE TABLE events (
    id VARCHAR(50),          -- niye STRING?
    event_date VARCHAR(20),  -- '2026-04-24' string
    is_active VARCHAR(5)     -- 'true' / 'yes' / 'Y' qarişıq
);
```

**Niye pisdir:** Index buyuyur, müqayisə yavasdır (`'2026-04-24' < '2026-4-25'` yanlısdır!), validation yoxdur.

**Fix:** `BIGINT`, `TIMESTAMP`/`DATE`, `BOOLEAN`. ID üçün `UUID` lazimdirsa, `uuid` tipi (Postgres) — string deyil.

---

## 6. CSV Sutununda (Comma-separated values)

**Anti-pattern:**

```sql
CREATE TABLE posts (id INT, tags VARCHAR(500));
-- tags = 'php,laravel,mysql,redis'
```

**Niye pisdir:** `WHERE tags LIKE '%laravel%'` → full scan, "laravel" ile "laravel-news" qariniqlir, count cətindir, normalize olunmamis.

**Fix:** Junction table:

```php
Schema::create('post_tag', function ($t) {
    $t->foreignId('post_id')->constrained();
    $t->foreignId('tag_id')->constrained();
    $t->primary(['post_id', 'tag_id']);
});
// Eloquent: belongsToMany
```

Postgres-de array tipi (`tags TEXT[] + GIN index`) qebul edilen ortabab həll-dir, amma normalize daha yaxsidir.

---

## 7. SELECT *

**Anti-pattern:**

```php
$users = User::all();  // SELECT * FROM users
foreach ($users as $u) { echo $u->email; }
```

**Niye pisdir:** Lazimsız sutunlar (BLOB, TEXT) çəkir, network traffic, memory, covering index istifade olunmur.

**Fix:**

```php
$users = User::select('id', 'email')->get();
// ve ya
DB::table('users')->pluck('email');
```

> Eloquent ucun: Model-de `$hidden` istifade etmek user-i bele yox, `select` lazımdır.

---

## 8. NULL Semantikasini Ignore Etmek

**Anti-pattern:**

```sql
SELECT * FROM users WHERE manager_id != 5;
-- manager_id = NULL olan user-lar GELMEYECEK!
```

**Niye pisdir:** `NULL != 5` → `NULL` (TRUE deyil). Bu səhv əksər developer-in birinci ay yaşadığı baş ağrısıdır.

**Fix:**

```sql
SELECT * FROM users WHERE manager_id != 5 OR manager_id IS NULL;
-- ya da
SELECT * FROM users WHERE COALESCE(manager_id, 0) != 5;
```

---

## 9. Read-heavy üçün Over-normalization

**Anti-pattern:** Homepage feed-i göstərmək üçün 8 JOIN — `users → posts → likes → comments → users(comment_author) → ...`.

**Fix:** Read üçün **denormalize** et:
- Materialized view (Postgres)
- `cached_*` sütunları (`posts.likes_count`, `posts.last_comment_at`)
- Redis read-model
- CQRS pattern

---

## 10. Under-normalization (Data Duplication)

**Anti-pattern:**

```sql
CREATE TABLE orders (
    id BIGINT, user_id BIGINT,
    user_name VARCHAR(255),         -- duplicated from users
    user_email VARCHAR(255),        -- duplicated
    user_address_line_1 VARCHAR(255) -- ... niye?
);
```

**Niye pisdir:** User adı dəyişəndə 1M order yenilənməlidir. Inconsistency garantirovannydir.

**Fix:** Denormalize **yalniz** snapshot lazim olduqda (invoice tarixinde adres qalmalidir → `shipping_address_snapshot` JSON sütunu məntiqlidir; user adı üçün `user_id` + JOIN).

---

## 11. Hər Şey üçün ORM (Raw SQL Çəkindirmə)

**Anti-pattern:** 500K row-da Eloquent collection ile loop:

```php
User::where('status', 'active')->get()->each(function ($u) {
    $u->update(['last_seen' => now()]);
});
```

**Niye pisdir:** 500K query, 500K hydration, RAM dolur.

**Fix:**

```php
DB::table('users')
  ->where('status', 'active')
  ->update(['last_seen' => now()]);

// Ya da chunk-da
User::where('status', 'active')->chunkById(1000, function ($users) {
    /* process */
});
```

ORM = developer rahatlığı. Bulk işlem, complex aggregation, window function üçün **raw query** ya da query builder istifade et.

---

## 12. Migration 1 Saat Cekir

**Anti-pattern:**

```php
Schema::table('large_table', function ($t) {
    $t->string('new_column')->default('pending');  // 100M row
});
// MySQL-de cədvəl LOCK olur, downtime
```

**Fix (online schema change):**

1. Sutunu `nullable` elave et (instant DDL)
2. Background-da default deyer doldur (chunk-da update)
3. Sonradan `NOT NULL` constraint elave et (yenidən instant DDL)

```php
// 1
Schema::table('large_table', fn($t) => $t->string('new_column')->nullable());

// 2 (artisan command)
DB::table('large_table')->whereNull('new_column')
    ->orderBy('id')
    ->chunkById(10000, fn($r) => DB::table('large_table')
        ->whereIn('id', $r->pluck('id'))
        ->update(['new_column' => 'pending'])
    );

// 3
DB::statement('ALTER TABLE large_table MODIFY new_column VARCHAR(255) NOT NULL');
```

Tools: `pt-online-schema-change`, `gh-ost` (PlanetScale), Postgres `ALTER TABLE ... ADD COLUMN` artiq instant-dir 11+.

---

## 13. UUIDv4 Clustered Primary Key (MySQL InnoDB)

**Anti-pattern:** MySQL-də `id BINARY(16)` PK + UUIDv4. Random — InnoDB clustered index → fragmentation, page split.

**Niye pisdir:** Insert yavaşlayır (B-tree-də random insert), index size 2-3x böyüyür, range scan slow.

**Fix:**
- **UUIDv7** (timestamp-based, sequential) — Laravel 10+ `Str::orderedUuid()`
- **ULID** — eyni faydalar, ister sıraya ister random
- Public exposure ucun UUID, daxili PK ucun BIGINT

```php
// Migration
$table->ulid('id')->primary();  // Laravel-de hazir

// Model
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Order extends Model { use HasUlids; }
```

---

## 14. JSON Sutunu Schema-nin Yerinə

**Anti-pattern:**

```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    data JSONB  -- name, price, category, stock... her şey burada
);
```

**Niye pisdir:** Index murekkeb, schema dokumentasyonu yox, JOIN cətin, validation yox, "schema-less" illuziası.

**Fix:** Strukturu məlum sahələr üçün **column**. JSON yalnız:
- Variable, az-istifadə olunan attribute-lar
- API response cache
- External system payload

---

## 15. FK Sutununda Index Yoxdur

**Anti-pattern:** `orders.user_id` FK var, amma index yoxdur.

**Niye pisdir:** `WHERE user_id = X` → full scan. JOIN yavaş. CASCADE DELETE → exponential.

**Fix:** Hər FK sutunu **mütleq index** olmalıdır. Postgres avtomatik FK ucun index yaratmır (PK ucun yaradir). MySQL avtomatik yaradir, amma Laravel migration-de aciq qoy:

```php
$t->foreignId('user_id')->constrained()->index();
```

---

## 16. Over-indexing (Yazma Cezasi)

**Anti-pattern:** Hər sutun ucun index. 30 indeks olan table.

**Niye pisdir:** Hər INSERT/UPDATE bütün index-leri yenilemelidir → yazma latency 5-10x artır. Disk 3-5x.

**Fix:**
- `pg_stat_user_indexes` ile **istifade olunmayan** index-leri sil
- Composite index düzgün sıralama (most selective first, ya da query-ə uyğun)
- Partial index istifadə et (`WHERE status = 'active'`)

```sql
-- Postgres: heç istifade olunmamis index
SELECT relname AS table, indexrelname AS index, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0
ORDER BY pg_relation_size(indexrelid) DESC;
```

---

## 17. Long-Running Transaction

**Anti-pattern:**

```php
DB::transaction(function () {
    $orders = Order::all();  // 1M row
    foreach ($orders as $o) {
        $o->process();  // hər biri 50ms
    }
});
// 50,000 saniye lock
```

**Niye pisdir:** Postgres MVCC dead tuple yığar (vacuum problem), MySQL-də deadlock + lock wait timeout.

**Fix:** Kicik transaction-lar, batch processing, queue.

---

## 18. Loop İçində SELECT (N+1)

**Anti-pattern:**

```php
$posts = Post::all();
foreach ($posts as $p) {
    echo $p->user->name;  // N+1: hər post ucun bir SELECT
}
```

**Fix:** Eager loading:

```php
$posts = Post::with('user')->get();
// 1 SELECT users + 1 SELECT posts
```

`Model::preventLazyLoading()` (Laravel 8+) prod-da exception atir, dev-de warn.

---

## 19. Blade Template-de Query

**Anti-pattern:**

```blade
@foreach($posts as $post)
    {{ $post->comments->count() }}
@endforeach
```

**Niye pisdir:** N+1, kontroldan kənar query-lər, debugging cətindir.

**Fix:** Controller-de `withCount('comments')` ile hesabla, view-a göndər.

---

## 20. CHAR(N) Lazimsiz

**Anti-pattern:** `CHAR(255)` boş yer ile dolur, fixed length.

**Fix:** `VARCHAR(N)` (variable). `CHAR` yalniz hemise eyni uzunluq olduqda mənalıdır (fixed code: ISO ölkə kodu `CHAR(2)`).

---

## 21. Faylları DB-də Saxlamaq

**Anti-pattern:** `image BLOB` — 5MB foto database-de.

**Niye pisdir:** DB size partlayır, backup yavaşlayır, query yavaşlayır, replicasiya gec.

**Fix:** S3 / object storage. DB-də yalnız URL/path saxla.

---

## 22. Isolation Level Ignore

**Anti-pattern:** Read Committed default ile money transfer:

```php
$balance = Account::find(1)->balance;  // 100
if ($balance >= 50) {
    Account::find(1)->decrement('balance', 50);  // race condition!
}
```

**Fix:**
- `SELECT FOR UPDATE` (pessimistic)
- `SERIALIZABLE` isolation
- Optimistic locking (`version` column)
- Atomic operation:
  ```php
  $rows = DB::update('UPDATE accounts SET balance = balance - 50 WHERE id = 1 AND balance >= 50');
  if ($rows === 0) throw new InsufficientFundsException();
  ```

---

## 23. Transaction İçində HTTP Çağırışı

**Anti-pattern:**

```php
DB::transaction(function () {
    $order = Order::create([...]);
    Http::post('https://payment.com/charge', [...]);  // 5 saniye
    $order->update(['status' => 'paid']);
});
```

**Niye pisdir:** Transaction 5+ saniye lock saxlayır, HTTP tail timeout-da retry → çift charge.

**Fix:** Outbox pattern:
1. Transaction-da `Order` + `outbox_events` yaz
2. Async worker outbox-dan oxuyub HTTP çağırış edir
3. Idempotency key ile duplicate cəkindir

---

## 24. Prepared Statement İstifadə Etməmek (SQL Injection)

**Anti-pattern:**

```php
DB::statement("SELECT * FROM users WHERE email = '$email'");  // SQL inj
```

**Fix:** Hər vaxt parameter binding:

```php
DB::select("SELECT * FROM users WHERE email = ?", [$email]);
// ya da Eloquent / query builder (avtomatik bind edir)
User::where('email', $email)->first();
```

---

## 25. "Magic" String Status Sutunlari

**Anti-pattern:**

```php
$order->status = 'pendng';  // typo, hec yer xəta atmir
```

**Fix:**
- ENUM (PHP 8.1+):
  ```php
  enum OrderStatus: string {
      case Pending = 'pending';
      case Paid = 'paid';
      case Shipped = 'shipped';
  }
  // Migration
  $t->string('status')->default(OrderStatus::Pending->value);
  // Cast
  protected $casts = ['status' => OrderStatus::class];
  ```
- Lookup table (`order_statuses` table + FK)

---

## 26. UPDATE / DELETE Without WHERE (Production-da)

**Real hadise:** Junior bir developer query tool-da `UPDATE users SET role='admin'` yazib WHERE qoymadan Enter-e basir → bütün user-lər admin.

**Fix:**
- Production DB ucun **read-only** user istifade et default
- Write yalniz audit ile, deploy via migration
- `safe-updates` mode (MySQL): `SET sql_safe_updates = 1` — WHERE/LIMIT olmadan UPDATE/DELETE bloklanir
- PIT (point-in-time recovery) backup hemise olsun

---

## 27. OFFSET Pagination Boyuk Cədvəlde

**Anti-pattern:**

```sql
SELECT * FROM posts ORDER BY id LIMIT 20 OFFSET 1000000;
-- DB 1M row oxumalidir, sonra atmalidir
```

**Fix:** **Cursor pagination** / keyset pagination:

```php
// Laravel cursor pagination (10+)
$posts = Post::orderBy('id')->cursorPaginate(20);

// Manual
$lastId = $request->input('cursor', 0);
$posts = Post::where('id', '>', $lastId)->orderBy('id')->limit(20)->get();
```

---

## 28. UNIQUE Constraint Yoxdur, App Validation

**Anti-pattern:** Email unique olmali, amma DB-də constraint yoxdur — yalniz `Validator::unique` istifade edilir. Race condition-da iki concurrent register eyni email-i kecire bilir.

**Fix:** **Hem app, hem DB** səviyyəsində UNIQUE:

```php
$t->string('email')->unique();  // Migration
// + app validation
```

DB constraint last line of defense-dir.

---

## 29. BIT(1) Yerinə BOOLEAN

**Anti-pattern:** MySQL-də `BIT(1) is_active` — Laravel-də anlaşılmaz cast, debug cətin.

**Fix:** `TINYINT(1)` (Laravel `boolean()` migration buni yaradir) ya da Postgres `BOOLEAN`.

---

## 30. Bonus: Connection Pool Yox

**Anti-pattern:** Lambda 1000 concurrent → RDS-ə 1000 birbasa connection → Postgres `max_connections` (100 default) yetmir.

**Fix:** RDS Proxy / pgBouncer / PlanetScale built-in pool. Always-on app-da connection pool tunings (Laravel `pool` config).

---

## Anti-pattern Sezme Checklist

Code review zamani bu suallari ver:

| Sual | Bayraq |
|------|--------|
| Yeni FK qoyulub, index var? | Yox → fix |
| Migration boyuk cədvəldə column elave edir? | Beli → online schema change |
| Eloquent loop-da query var? | Beli → eager loading |
| Transaction-da HTTP çağırışı? | Beli → outbox |
| OFFSET 100000+? | Beli → cursor pagination |
| `SELECT *` boyuk cədvəlde? | Beli → select specific |
| Status string `'pending'`? | Beli → enum |
| UUIDv4 clustered PK MySQL-də? | Beli → ULID/UUIDv7 |
| Read-heavy 5+ JOIN? | Beli → denormalize/cache |

---

## Interview suallari

**Q: Production-da gördüyün eən pis database anti-pattern hansı idi?**
A: Tipik cavab — N+1 sorğu Blade template-de, EAV table-da kategoriya ad-i axtarmaq, ya da UUIDv4 PK boyuk MySQL table-da. Cavabın strukturlu olsun: (1) anti-pattern nə idi, (2) hansı problem yaratdı (saytın yavaşlaması, downtime), (3) necə fix etdin (migration, kod refactor, indexing), (4) gələcəkdə qarşı almaq üçün kommandaya nə etdin (lint rule, code review checklist).

**Q: Soft FK (constraint yoxdur) ne vaxt qebul edilir?**
A: Cox nadir. Misal: cross-shard table-larda (PlanetScale/Vitess) cross-shard FK enforcement bahadir. Audit/event log table-da source row silinende belə həmin event-i saxlamaq lazimdir. Migration vaxti müvəqqəti. Bu hallarda app-da check + nightly orphan detection job lazimdir. Default davranis hər vaxt FK olmalıdır.

**Q: ORM ne vaxt mənfi tesir edir?**
A: 3 əsas hal: (1) Bulk operation — 500K row-u Eloquent collection-da loop = RAM ölümü; (2) Complex analytics — window function, CTE, recursive query ORM-de cətin oxunur; (3) N+1 hidden — relationship-leri lazy load Eloquent-in default davranisi, code review-da goz yayinir. Best practice: 80% ORM (CRUD, sade query), 20% raw SQL / query builder (bulk, analytics, performance-critical).

**Q: UUIDv4 vs UUIDv7 vs ULID — hansını secersən?**
A: Yeni layihədə **UUIDv7** ya da **ULID** secərəm. Hər ikisi timestamp-prefix-li — sequential insert (B-tree fragmentation yoxdur). UUIDv7 RFC 9562 standartdir, daha geniş dəstək. ULID Crockford base32 encoding-dir, oxunaqli. UUIDv4 yalniz public-facing token / share-link üçün (random olduğu üçün enumerate olunmur). Daxili PK ucun BIGINT auto-increment hələ də ən yaxşıdır əgər public exposure problemi yoxdursa.

**Q: SELECT * niye pisdir, amma çoxları yenə də istifade edir?**
A: Ümumi səbəb — developer rahatlığı, "nə lazım olsa orada var" düşüncəsi. Real problemlər: (1) network/memory artıq yük (BLOB/TEXT sutunlari), (2) covering index istifadə olunmur (DB row data-yə getmek məcburiyyətindədir), (3) yeni sütun elavə olunduqda kod silently dəyişir (API contract pozulur), (4) ORM model-də `$hidden` array tetbiq olunmur. Helli: kod review qaydası + Laravel-də `Model::shouldBeStrict()` (eager + accessor + assignment strict modu).
