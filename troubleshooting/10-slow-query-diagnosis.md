# Slow Query Diagnosis (Middle)

## Problem (nə görürsən)
Database yavaşdır. Və ya konkret bir query yavaşdır. Hər halda, hansı query-nin, niyə və necə düzəltməyin tapmalısan. MySQL slow log, `EXPLAIN`, query digest və Laravel-spesifik alətlər sənin dostlarındır.

Simptomlar:
- Konkret endpoint yavaşdır (p95 > 2s), digərləri qaydasındadır
- DB CPU yüksəkdir amma app CPU normaldır
- Laravel log `QueryException` timeout-larını göstərir
- Connection pool dolur, query-lər asılı qalır
- "1k sətirdə işləyirdi, 10k-da sınıqdır"

## Sürətli triage (ilk 5 dəqiqə)

### DB bottleneck-dirmi?

```bash
# MySQL active queries
mysql -e "SHOW FULL PROCESSLIST" | grep -v Sleep

# Long-running queries (> 5s)
mysql -e "SELECT id, user, host, db, command, time, state, info 
          FROM information_schema.processlist 
          WHERE time > 5 AND command != 'Sleep'"

# Postgres
psql -c "SELECT pid, now() - query_start AS duration, state, query 
         FROM pg_stat_activity 
         WHERE state != 'idle' 
         ORDER BY duration DESC;"
```

Əgər çox query `Sending data` və ya `Locked` state-də qalıbsa, hədəfini tapdın.

### Runaway query-ni öldür

```bash
# MySQL
mysql -e "KILL 12345;"

# Postgres
psql -c "SELECT pg_terminate_backend(12345);"
```

## Diaqnoz

### Slow query log (MySQL)

Restart olmadan canlı aktivləşdir:
```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;                 -- log queries > 1s
SET GLOBAL log_queries_not_using_indexes = 'ON';
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow.log';
```

`my.cnf`-də daimi et:
```ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
log_queries_not_using_indexes = 1
min_examined_row_limit = 100
```

### pt-query-digest

Slow log-u analiz edir:
```bash
pt-query-digest /var/log/mysql/slow.log > slow-report.txt
```

Çıxış query-ləri ümumi vaxta görə sıralayır. Ən yuxarı 1-3 query adətən DB yükünün çoxunu əhatə edir.

Nümunə parça:
```
# Rank Query ID           Response time  Calls  R/Call    Item
#    1 0xABCDEF12...      4892.3s 64%   1200    4.08s     SELECT orders WHERE user_id = ?
#    2 0x123456AB...       843.2s 11%   50000   0.017s    SELECT users WHERE email = ?
```

Rank 1-ə fokuslan: az çağırış amma hər biri yavaş. Rank 2 çox çağırışdır amma hər biri sürətli — fərqli optimization strategiyası.

### EXPLAIN əsasları

```sql
EXPLAIN SELECT * FROM orders WHERE user_id = 42 AND created_at > '2026-01-01';
```

Əsas sütunlar:
- `type`: `ALL` (full scan, pis) → `index` (index scan) → `range` → `ref` → `eq_ref` → `const` (ən yaxşı)
- `possible_keys`: MySQL-in nəzərdən keçirdiyi index-lər
- `key`: həqiqətən istifadə edilən index (NULL = yox)
- `rows`: təxmini scan edilən sətirlər — həqiqətən qaytarılan sətirlərlə müqayisə et
- `Extra`: `Using filesort`, `Using temporary`, `Using where` — qırmızı bayraqlar

Əgər `rows` = 1,000,000 və query-n 10 sətir qaytarırsa, index çatışmır.

Modern MySQL/Postgres-də həqiqi icra üçün `EXPLAIN ANALYZE` istifadə et:
```sql
EXPLAIN ANALYZE SELECT ...;
```

### Postgres: EXPLAIN (ANALYZE, BUFFERS)

```sql
EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) 
SELECT * FROM orders WHERE user_id = 42;
```

Bunlara bax:
- Böyük cədvəllərdə `Seq Scan` = pis
- `Buffers: shared read=X` = soyuq cache disk I/O
- Faktiki vs təxmini sətir sayları (100x fərqli olsa, statistika köhnədir: `ANALYZE table_name`)

### Laravel runtime analizi

Müvəqqəti olaraq bütün query-ləri logla:
```php
// AppServiceProvider::boot() in non-prod
DB::listen(function ($query) {
    if ($query->time > 100) {
        Log::warning('Slow query', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time_ms' => $query->time,
        ]);
    }
});
```

Və ya lazım olduqda query log-u aktivləşdir:
```php
DB::enableQueryLog();
// ... code ...
dd(DB::getQueryLog());
```

### Laravel Telescope / Debugbar (yalnız dev)

- **Telescope** — request başına bütün query-ləri qeyd edir, query-lənən UI
- **Debugbar** — query detalları ilə inline UI, local dev üçün ən yaxşı
- **Clockwork** — oxşar, daha az invaziv

Prod-da Telescope tam-qeydiyyatı heç vaxt aktivləşdirmə — səni öldürər.

## Fix (qanaxmanı dayandır)

### Ümumi pattern-lər

**1. Çatışmayan index**
```sql
CREATE INDEX idx_orders_user_created ON orders (user_id, created_at);
```

**2. Indekslənmiş sütun üzərində function**
```sql
-- Bad — can't use index on created_at
WHERE DATE(created_at) = '2026-04-17'

-- Good
WHERE created_at >= '2026-04-17 00:00:00' 
  AND created_at < '2026-04-18 00:00:00'
```

**3. Qarşı wildcard-lı LIKE**
```sql
-- Bad
WHERE email LIKE '%@gmail.com'

-- Better: add a generated column of reversed email, index it
-- Or use full-text index, or ES/OpenSearch for search
```

**4. İndex-ləri məğlub edən OR clause-lar**
```sql
-- Bad
WHERE (user_id = 5 OR email = 'a@b.com')

-- Better: UNION
SELECT * FROM users WHERE user_id = 5
UNION
SELECT * FROM users WHERE email = 'a@b.com'
```

**5. Covering index**
Həmişə eyni filter ilə eyni sütunları SELECT edirsənsə, hər şeyi əhatə edən index yarat:
```sql
CREATE INDEX idx_covering ON orders (user_id, status, total, created_at);
-- Now `SELECT user_id, status, total, created_at` is index-only
```

**6. Index ip-ucu (nadir hallarda lazımdır)**
```sql
SELECT * FROM orders USE INDEX (idx_user_id) WHERE user_id = 5;
```

### Read replica nə vaxt əlavə etmək

Əgər oxumalar yazmalardan çoxdursa və düzgün indeksləmisənsə:
- Analitik query-ləri replica-ya yüklə
- Laravel: read/write bağlantıları konfiqurasiya et
```php
'mysql' => [
    'read' => ['host' => ['replica-1', 'replica-2']],
    'write' => ['host' => ['primary']],
],
```

"Öz yazını oxu" pattern-ləri üçün replikasiya gecikməsinə diqqət et.

## Əsas səbəbin analizi

- 1-ci gündən çatışmayan index idi, yoxsa yeni koddan regresiya idi?
- Dev-də `EXPLAIN` tutdumu? Yoxsa, niyə? (Adətən: dev DB-də data yoxdur.)
- Review tutdumu?
- Slow-query alert-imiz var?

## Qarşısının alınması

- Slow query log prod-da həmişə aktiv, `long_query_time = 0.5` və ya `1`
- pt-query-digest həftəlik işə salın, top 10 nəzərdən keçirilir
- Yeni custom query üçün code review-da `EXPLAIN` məcburidir
- Kifayət qədər ölçülü seed dataseti ilə Laravel testlər
- CI addımı: hər yeni query-də `EXPLAIN` işə sal, `type = ALL`-dursa fail et
- Controller-lərdə xam SQL qadağan et; service/repository tələb et

## PHP/Laravel üçün qeydlər

### Eloquent tələləri

```php
// Bad — loads all columns, all rows, then counts
User::all()->count();

// Good
User::count();

// Bad — N+1
foreach (Post::all() as $post) {
    echo $post->user->name;
}

// Good
foreach (Post::with('user')->get() as $post) {
    echo $post->user->name;
}
```

### Böyük əməliyyatlar üçün chunking

```php
// Runs one query per chunk, constant memory
Order::where('processed', false)
    ->chunkById(500, function ($orders) {
        foreach ($orders as $order) {
            $order->process();
        }
    });
```

### Laravel-də index ip-ucları

```php
DB::table('orders')
    ->from(DB::raw('orders USE INDEX (idx_user_id)'))
    ->where('user_id', 5)
    ->get();
```

### Bir query üçün read/write split

```php
$users = DB::connection('mysql::read')->table('users')->get();
```

## Yadda saxlanacaq komandalar

```sql
-- MySQL
SHOW FULL PROCESSLIST;
SHOW CREATE TABLE orders;
SHOW INDEX FROM orders;
EXPLAIN SELECT ...;
EXPLAIN ANALYZE SELECT ...;   -- MySQL 8.0.18+
SHOW GLOBAL STATUS LIKE 'Slow_queries';

-- Postgres
SELECT * FROM pg_stat_activity WHERE state != 'idle';
\d+ orders
EXPLAIN (ANALYZE, BUFFERS) SELECT ...;
SELECT pg_stat_statements ORDER BY total_time DESC LIMIT 10;

-- Kill a query
KILL 12345;                   -- MySQL
SELECT pg_terminate_backend(12345);  -- Postgres
```

```bash
# pt-query-digest
pt-query-digest /var/log/mysql/slow.log

# Laravel
php artisan db:show
php artisan db:table users
```

## Interview sualı

"Yavaş query-nin diaqnozunu təsvir et."

Güclü cavab:
- "Aktiv query-ləri görmək üçün `SHOW FULL PROCESSLIST`-lə başlayıram. Aydın runaway olanları öldürürəm."
- "Aktiv deyilsə slow query log-u aktivləşdir. 10 dəqiqəlik trafikdən sonra `pt-query-digest` işə sal."
- "Digest-dən top query → onu `EXPLAIN` et. `type: ALL`, çatışmayan index, `Using filesort` və ya kəskin səhv sətir təxminləri axtar."
- "Ən ümumi fix-lər: index əlavə et, WHERE-dən function-u çıxar, OR-u UNION-a yenidən strukturlaşdır."
- "Laravel-də request başına yavaş query-ləri tutmaq üçün non-prod-da `DB::listen` istifadə edirəm. Dev üçün `Telescope`, inline `Debugbar`."
- "Fix-i `EXPLAIN ANALYZE` ilə yeni plan göstərərək yoxlayıram, sonra real dünya yaxşılaşmasını təsdiqləmək üçün load test edirəm."

Bonus: "Bir dəfə 5s query 3-sütunlu covering index ilə düzəldildi. p95 4s-dən 40ms-ə düşdü. Açar kompozit index-i WHERE + ORDER BY + SELECT sütunlarına uyğunlaşdırmaq idi."
