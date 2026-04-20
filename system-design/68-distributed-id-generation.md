# Distributed ID Generation (Snowflake, ULID, UUIDv7)

## Nədir? (What is it?)

Distributed ID generation — bir-biri ilə koordinasiya etmədən çoxsaylı server, shard
və ya mikroservisdə unikal, (çox vaxt) sıralana bilən identifikatorlar istehsal etmək
problemidir. Monolit app-də auto-increment primary key kifayət edir, amma sistem
horizontal scale olan kimi DB-yə gedib ID almaq bottleneck olur, multi-master
replication ID toqquşması yaradır, offline client isə insert-dən qabaq ID görmək
istəyir. Snowflake (Twitter), ULID, UUIDv7 kimi sxemlər bu problemi həll edir.

Sadə dillə: hər server özü-özünə ID düzəltsin, amma sonra heç kim eyni ID-ni yaratmasın
və IDs təxminən zaman sırasına görə düzülsün ki, DB index performansı pozulmasın.

```
Node-1:  id = 1524376218932453376    Node-2:  id = 1524376218932982001
Node-3:  id = 1524376218933112849    Node-4:  id = 1524376218933245104
         \\____________________________________________________________/
                          Heç bir koordinasiya yoxdur,
                          amma hamısı unikal və time-ordered
```

## Niyə auto-increment yetmir? (Why not auto-increment?)

- **DB-level bottleneck** — hər insert üçün sequence/counter latch, write master-a sıxıştırır
- **Order/count leak** — `/users/1523` → "sizdə 1523 user var" rəqibə məlumat verir
  (Germany Tank Problem); həmçinin enumeration attack (`/orders/1`, `/orders/2` ...)
- **Insert-dən əvvəl ID alınmır** — event sourcing, outbox, async queue üçün ID
  qabaqcadan lazımdır (client-side generated)
- **Multi-master merge çətin** — iki DC eyni zamanda `id=1001` yarada bilər
- **Shard-lara paylananda çətinlik** — global unique counter shard-a necə bölünsün?
- **Cross-service reference** — mikroservislər arasında ID keçərkən collision riski

## Tələblər (Requirements)

- **Globally unique** — toqquşma ehtimalı praktik olaraq 0
- **Roughly sortable** — zaman sırasına yaxın (B-tree index locality üçün vacib)
- **No coordination** — hər node lokal olaraq istehsal etsin (no lock, no RPC)
- **Compact** — ideal olaraq 64-bit (bigint-ə sığsın), ya da 128-bit maksimum
- **High throughput** — milyonlarla ID/saniyə/node
- **Monotonic (istəyə görə)** — eyni node-da ardıcıl ID-lər artan
- **Opaque to client** — client sayını/sırasını təxmin edə bilməsin (security)

## UUIDv4 — Təsadüfi (Random)

```
550e8400-e29b-41d4-a716-446655440000
xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
         version 4 ─┘  └─ variant bits
```

- **122 bit random** (4 bit version, 2 bit variant sabit)
- **Pros**: heç bir koordinasiya, universal support, trivial `random_bytes(16)`
- **Cons**:
  - 16 byte (auto-increment BIGINT-dən 2× böyük)
  - Tam random → B-tree-də hər insert fərqli səhifəyə düşür (index fragmentation)
  - Zaman sırası yoxdur — "son yaradılanlar" sorğusu üçün əlavə `created_at` sütunu lazımdır
  - text formatda 36 char (storage + compare əlavə yük)

## UUIDv1 — Timestamp + MAC

- 60-bit timestamp (100ns since 1582-10-15) + 14-bit clock seq + 48-bit MAC address
- **Privacy leak** — MAC address-i açır (Melissa virus 1999-da belə izlənmişdi)
- Time-ordered, amma yüksək bit-lər aşağıda olduğundan (endianness) leksikoqrafik sort pozulur
- Praktikada köhnəlmişdir — UUIDv6/v7 əvəzləyir

## UUIDv7 — Yeni Standart (RFC 9562, 2024)

```
 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                      unix_ts_ms (48 bit)                      |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|unix_ts_ms ...|  ver  |      rand_a (12 bit)                   |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|var|                   rand_b (62 bit)                         |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
```

- 48-bit millisecond timestamp (yüksək bit-lərdə!) + version + 74-bit random
- **Leksikoqrafik və binary sort time-ordered** — index-friendly
- 128-bit, UUIDv4 ilə eyni ölçü — drop-in əvəz
- RFC 9562 (2024) — PostgreSQL 18, MySQL 8.4, Laravel 10+, Ramsey 4.7+ dəstəkləyir
- **Yeni layihələr üçün default seçim**

## ULID — Universally Unique Lexicographically Sortable

```
 01ARZ3NDEKTSV4RRFFQ69G5FAV
 |--------||----------------|
  48-bit ms   80-bit random

 Encoding: Crockford Base32 (0-9, A-Z - {I, L, O, U})
 Length:   26 characters (UUID-dən 10 char qısa)
```

- 128-bit (time 48 + random 80), amma mətn 26 char
- **Monotonic mode** — eyni ms içində random-ı artıraraq ardıcıllığı qoruyur
- Case-insensitive, URL-safe, ambiguous simvol yoxdur (I/L, O/0)
- JavaScript, PHP, Go, Rust üçün mature kitabxanalar
- Laravel 9+ `Str::ulid()` və `HasUlids` trait ilə birinci-klass dəstək

## KSUID — K-Sortable Unique IDentifier (Segment)

- 160-bit: 32-bit custom epoch timestamp + 128-bit random payload, 27 char Base62
- Segment (analytics) şirkəti üçün dizayn edilib; niche — müasir stack UUIDv7/ULID seçir

## Twitter Snowflake — 64-bit ID

```
 63                                                              0
  0  41 bit timestamp (ms since custom epoch)   10 wrk  12 seq
 +-+---------------------------------------+----------+----------+
 |0|             timestamp                  | worker   | sequence |
 +-+---------------------------------------+----------+----------+
   |<------------ 41 bits ----------------->|<- 10 -->|<-- 12 -->|
                                             node_id   0-4095

 Sign bit (1): həmişə 0 → signed BIGINT-ə uyğun (pozitiv)
 Timestamp   (41): ~69 il (2^41 ms) custom epoch-dan
 Worker ID   (10): 1024 node
 Sequence    (12): hər ms-də 4096 ID/worker
```

- **Max throughput**: 4096 × 1024 = ~4.2M ID/ms globally (~4 mlrd/s)
- `worker_id` koordinasiya tələb edir (Zookeeper, etcd, env var, DHCP-tipli assignment)
- Ms eyni qalsa `sequence` artır, sequence 4095 olsa növbəti ms-i gözlə
- Time-ordered (yüksək bit timestamp), BIGINT-ə tam sığır

### Snowflake Variantları

| Variant | Timestamp | Shard/Worker | Sequence | Qeyd |
|---------|-----------|--------------|----------|------|
| Twitter | 41 bit ms | 10 bit (1024) | 12 bit | Orijinal |
| Instagram | 41 bit ms | 13 bit shard | 10 bit | Shard-per-user photo table |
| Discord | 42 bit ms | 10 bit wrk + proc | 12 bit | Snowflake + epoch 2015 |
| Sonyflake | 39 bit (10ms!) | 16 bit machine | 8 bit | Daha çox worker, az seq |

## Database-Based Sequences

**Shared SQL Sequence** (`CREATE SEQUENCE`, `nextval`) — SPOF, hər insert DB
round-trip; yalnız kiçik sistemlər üçün.

**Ticket Server (Flickr)** — ayrı MySQL host `REPLACE INTO tickets` + `LAST_INSERT_ID()`.
Flickr 2 server istifadə edirdi (cüt/tək ID → HA). App DB-dən ayrı, amma hələ də bottleneck.

**Segment / HiLo Pattern** — worker DB-dən blok reserve edir (A: [1000,1999],
B: [2000,2999]), sonra lokal RAM-da counter artırır. `id = (hi << N) | lo`.
Hibernate/NHibernate default-u. Restart-da qalan ID-lər gap kimi itir (OK).

## Time-Based Problems

### Clock Drift / NTP

- Snowflake-də saat **geriyə getsə** → eyni ms + sequence → **duplicate ID**!
- Həll:
  - `last_timestamp` yadda saxla; geriyə gedirsə **gözlə** (`sleep(delta)`)
  - Böyük drift (>5s) → exception at, node-u xəstə elan et
  - Monotonic clock (`CLOCK_MONOTONIC`) istifadə et, amma epoch üçün real clock lazımdır
  - Logical clock (Lamport/Hybrid Logical Clock) — timestamp + counter

### Leap Second

- 2012-də bir çox sistem crash olmuşdu (leap second kernel bug)
- Google "leap smearing" — 24 saata yayır, heç vaxt -1 san olmur
- Snowflake node-u NTP server-lərlə smeared mode-da sync etmək tövsiyə olunur

## Collision Probability (Birthday Paradox)

UUIDv4: 2^122 bit space → 50% collision ~2^61 ≈ 2.3×10^18 ID (saniyədə 1 mlrd ID
yaratsan, ~100 il sonra). UUIDv7: random 74-bit → 2^37 ID/ms-də collision riski
(praktik deyil). ULID monotonic mode eyni ms-də random-ı increment edir → deterministik.

## Sorting / Indexing Implications

UUIDv4 random insert hər dəfə fərqli B-tree səhifəsinə düşür (page split, cache miss).
UUIDv7/ULID/Snowflake yüksək bit-də timestamp → həmişə ən sağdakı səhifəyə insert
(sequential, cache-friendly). MySQL clustered PK üçün kritik — UUIDv4 PK ilə 1B row
cədvəl 2× böyük, 3-5× yavaş insert. Percona benchmark: UUIDv4 → 10K insert/s,
UUIDv7 → 45K insert/s (eyni hardware).

## Storage Format: BINARY(16) vs VARCHAR(36)

```
VARCHAR(36): '550e8400-e29b-41d4-a716-446655440000' → 36 byte + 1 byte length
BINARY(16):  0x550E8400E29B41D4A716446655440000   → 16 byte
```

- Binary **3× kiçik** → index 3× kiçik → cache-ə daha çox sığır
- Byte comparison string comparison-dan 2-3× sürətli
- MySQL `UUID_TO_BIN(uuid, 1)` ilə time-swap: yüksək byte-ları önə çəkir (sort üçün)
- PostgreSQL-də native `uuid` tipi var (16 byte)

## Pros/Cons Matrix

| Xüsusiyyət | UUIDv4 | UUIDv7 | ULID | Snowflake |
|-----------|--------|--------|------|-----------|
| Ölçü (bit) | 128 | 128 | 128 | 64 |
| Text uzunluğu | 36 | 36 | 26 | 19 (decimal) |
| Time-ordered | Yox | Bəli | Bəli | Bəli |
| Koordinasiya | Yox | Yox | Yox | worker_id |
| Opaque | Bəli | Qismən | Qismən | Yox (time açıq) |
| BIGINT sığır | Yox | Yox | Yox | Bəli |
| Monotonic | Yox | Yox | Opsional | Bəli (per node) |
| Standart | RFC 9562 | RFC 9562 | spec | de-facto |

## Nə Zaman Hansını Seçmək? (When to pick what?)

- **Public-facing ID** (API response, URL) → **UUIDv7 / ULID** — opaque, guessable deyil
- **Internal high-throughput** (event, log, message) → **Snowflake** — 64-bit, BIGINT PK
- **Sadə standalone app** (< 1M row, tək server) → **auto-increment** — KISS
- **Multi-master SQL** (Galera, MySQL Group Replication) → **UUIDv7** — toqquşma yoxdur
- **Mobile/offline-first** (client insert-dən əvvəl ID yaradır) → **ULID / UUIDv7**
- **Short URL / coupon code** → base62 Snowflake və ya Hashids (obfuscation)
- **Financial / audit log** → Snowflake (strict ordering + audit-friendly)

## PHP / Laravel Nümunələri

### Ramsey UUID (UUIDv4, v7)

```php
use Ramsey\Uuid\Uuid;

// UUIDv4 — pure random
$id = Uuid::uuid4()->toString();   // 550e8400-e29b-41d4-a716-446655440000

// UUIDv7 — time-ordered, RFC 9562 (Ramsey 4.7+)
$id = Uuid::uuid7()->toString();   // 018f4c12-3a2b-7abc-8def-0123456789ab

// Binary storage (DB üçün 3x kiçik)
$bin = Uuid::uuid7()->getBytes();  // 16 byte binary string
```

### Laravel — Str::ulid(), Str::uuid7()

```php
use Illuminate\Support\Str;

$ulid = (string) Str::ulid();      // 01HPN8X7QRBKPGFVZ4MC3DK2WA
$uuid = (string) Str::uuid7();     // Laravel 10+

// Eloquent model — ULID primary key
class Post extends Model
{
    use HasUlids;   // Laravel 9+ — auto ULID PK

    protected $keyType = 'string';
    public $incrementing = false;
}

// UUID primary key
class Order extends Model
{
    use HasUuids;   // default UUIDv4

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();  // override → UUIDv7
    }
}

// Migration
Schema::create('posts', function (Blueprint $table) {
    $table->ulid('id')->primary();    // CHAR(26)
    // və ya binary:
    // $table->uuid('id')->primary(); // PostgreSQL native, MySQL CHAR(36)
    $table->string('title');
    $table->timestamps();
});
```

### Snowflake PHP (godruoyi/php-snowflake)

```php
use Godruoyi\Snowflake\Snowflake;

$snowflake = new Snowflake(
    datacenter: 1,   // 0-31  (5 bit)
    workerId: 3      // 0-31  (5 bit) — env-dən və ya Redis-dən al
);

$snowflake->setStartTimeStamp(strtotime('2024-01-01') * 1000);

$id = $snowflake->id();   // "1524376218932453376" (string, 64-bit)

// Laravel service container
$this->app->singleton(Snowflake::class, function () {
    return (new Snowflake(
        datacenter: config('app.datacenter'),
        workerId: (int) env('WORKER_ID')
    ))->setStartTimeStamp(1704067200000);
});

// Controller-də
$order = Order::create([
    'id' => app(Snowflake::class)->id(),
    'amount' => 100,
]);
```

## Security — Opaque ID Vacibdir

- **Enumeration attack**: `/api/users/1001` → `/api/users/1002` dataleak
- **German Tank Problem**: `order_id=5234` görsə, gündəlik order sayını təxmin edir
- **Race condition exploit**: sequential ID ilə TOCTOU attack asanlaşır
- **Authorization obfuscation** deyil: UUID istifadəsi authz əvəzləyə bilməz — hər
  sorğuda `user_id` yoxlanmalıdır
- Snowflake time bit-ləri açıq saxlayır → rəqib "bu ID 2025-03-14 14:22-də yaranmışdır"
  deyə bilir. Kritik hallarda UUIDv7 + pepper hash istifadə et

## Best Practices

- **Yeni layihədə UUIDv7 və ya ULID default seçim et** — UUIDv4 köhnəlmişdir
- **Binary storage** (`BINARY(16)` MySQL, native `uuid` PostgreSQL) — 3× storage qənaəti
- **Snowflake-də worker_id coordination** — env var + CI check + Redis/etcd reservation
- **Clock drift monitoring** — NTP status Prometheus-a çıxar, alarm qoy
- **Custom epoch** Snowflake-də — 1970 yox, layihə başlanğıcı (41 bit ömrü uzadır)
- **Index-friendly format** — PK həmişə time-ordered (UUIDv7/ULID/Snowflake)
- **Public ID ≠ internal ID** — DB-də auto-increment BIGINT + `public_id` ULID ikili
  model bəzən optimal olur (kiçik FK, opaque API)
- **Never expose Snowflake worker_id** — attacker-ə infra topology açır
- **Test clock-backward scenario** — CI-də saatı geri qur, exception atılırmı yoxla
- **Migration strategy** — köhnə auto-increment-dən UUIDv7-yə keçəndə dual-write dövrü et

## Interview Questions

**Q1: UUIDv4 niyə MySQL clustered PK üçün pis seçimdir?**
InnoDB PK clustered-dır — data fiziki olaraq PK sırasına saxlanır. UUIDv4 random →
hər insert B-tree-nin fərqli səhifəsinə düşür, page split artır, buffer pool cache
miss olur. UUIDv7/Snowflake yüksək bit-lərdə timestamp saxlayır → hər insert ən
sağdakı səhifəyə gedir (sequential), cache-friendly, fragmentation minimal.

**Q2: Snowflake-də saat geri getsə nə olur və necə həll edirsən?**
Saat geri getsə, keçmiş `(timestamp, worker_id, sequence)` kombinasiyası yenidən
istehsal oluna bilər → duplicate ID. Həll: `last_timestamp` state saxla; yeni
timestamp < last olsa, kiçik fərq üçün gözlə (`sleep(delta)`), böyük fərq üçün
exception at və node-u sıradan çıxar. NTP-ni "slew" modunda çalışdır (ani step yox).

**Q3: ULID və UUIDv7 arasındakı əsas fərq nədir?**
Hər ikisi 128-bit və time-ordered. Fərqlər: ULID 26 char Crockford Base32, UUIDv7
36 char hex — ULID daha kompakt mətn. ULID rəsmi monotonic mode spec-də var, UUIDv7
tətbiqdən asılıdır. UUIDv7 RFC 9562 IETF standart, ULID community spec. Müasir DB-lər
UUIDv7-ni native dəstəkləyir, ULID daha oxunaqlıdır.

**Q4: 64-bit Snowflake vs 128-bit UUIDv7 — hansını seçərdin?**
Snowflake BIGINT-ə sığır → 2× az storage, FK müqayisəsi sürətli; amma worker_id
coordination tələb edir və time/worker leak edir. UUIDv7 koordinasiya tələb etmir,
opaque-a yaxındır, amma 2× böyük. High-throughput internal sistem (event bus, log)
üçün Snowflake; public API, mobile client, multi-master DB üçün UUIDv7.

**Q5: HiLo pattern nədir və nə vaxt istifadə edirsən?**
Worker DB-dən "high" dəyər reserve edir (məs. 1000), sonra lokal RAM-da "low"
counter 0-999 artırır, ID = (hi << 10) | lo. Üstünlük: DB round-trip 1000 insertdə
1 dəfə. Çatışmazlıq: restart-da qalan ID-lər gap kimi itir. Hibernate default
generator-ı budur — Snowflake mümkün olmayanda yaxşı kompromis.

**Q6: UUIDv4 collision riski yoxdursa, niyə UUIDv7 tövsiyə olunur?**
Collision problem deyil — əsas səbəb **index performance**. UUIDv4 random →
B-tree fragmentation, MySQL clustered PK-da 3-5× yavaş insert. UUIDv7 time-ordered →
sequential insert, cache-friendly. Əlavə: UUIDv7 "yaradılma vaxtı" sütununu əvəz edir.

**Q7: Birdən çox datacenter-də ID necə unikal saxlayırsan?**
Snowflake-də bit-ləri bölmək: 41 timestamp + 5 datacenter + 5 worker + 12 sequence
→ 32 DC × 32 worker × 4096 ID/ms. Və ya hər DC öz `worker_id` range-ində (A: 0-511,
B: 512-1023). UUIDv7/ULID üçün problem yoxdur — 74-bit random hər DC-də unikaldır.

**Q8: Public API-də Snowflake ID-ni göstərmək niyə problemlidir?**
Timestamp açıq (user qeydiyyat vaxtı leak), worker_id infra topology açır, sequence
business metric leak edir ("saniyədə X qeydiyyat"). Həll: public ID kimi UUIDv7
göstər, daxildə Snowflake saxla; və ya Snowflake-i AES encrypt et.
