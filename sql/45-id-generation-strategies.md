# ID Generation Strategies (Auto-increment, UUID, ULID, Snowflake)

> **Seviyye:** Intermediate ⭐⭐

## Niye vacibdir?

Primary key seciminin sonradan deyismek cox bahalidir. Index size, INSERT performance, replication, distributed system, security - hamisi ID strategy-den asili olur.

---

## 1. BIGINT AUTO_INCREMENT (Sequential)

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(320) NOT NULL UNIQUE
);
```

**Ustunlukler:**
- 8 byte - en kicik index
- Sequential INSERT - B-tree fragmentation yox
- Suretli range query: `WHERE id BETWEEN 1000 AND 2000`
- Human-readable, debug ucun rahat

**Dezavantajlar:**
- **Sequence exposure problem**: `/api/users/123` - hacker `124, 125, ...` deye gedir, IDOR (Insecure Direct Object Reference) saldirisi
- **Business intelligence sizmasi**: rakib gorur ki `user_id = 50000`, demek 50K user var
- **Distributed system-de problem**: 2 server eyni ID generate ede biler (sequence conflict)
- **Replication conflict**: master-master setup-da auto_increment offset ayarlamaq lazimdir

**MySQL `auto_increment` daxili:**
- Memory-de tutulur (restart-dan sonra `MAX(id) + 1`-den davam edir)
- `innodb_autoinc_lock_mode` = 0 (traditional), 1 (consecutive - default), 2 (interleaved)
- ROLLBACK olunsa bele ID istifade olunmus sayilir (gap qalir)

---

## 2. PostgreSQL Sequences

```sql
CREATE SEQUENCE users_id_seq START 1 INCREMENT 1;
CREATE TABLE users (
    id BIGINT PRIMARY KEY DEFAULT nextval('users_id_seq')
);

-- Daha sade: SERIAL / BIGSERIAL
CREATE TABLE users (id BIGSERIAL PRIMARY KEY);

-- Modern (PostgreSQL 10+):
CREATE TABLE users (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY
);
```

**MySQL auto_increment vs PostgreSQL sequence:**

| Xususiyyet | MySQL AUTO_INCREMENT | PostgreSQL SEQUENCE |
|------------|---------------------|---------------------|
| Object tipi | Table column attribute | Bagimsiz database object |
| Cache | innodb buffer | `CACHE N` (default 1, prod 50+) |
| Multiple table-de share | Yox | Beli (`nextval('shared_seq')`) |
| Reset | `ALTER TABLE ... AUTO_INCREMENT = 1` | `ALTER SEQUENCE ... RESTART 1` |
| Gap-siz garanti | Yox | Yox (cache + rollback) |

---

## 3. UUID v4 (Random)

128-bit (16 byte). Tamamile random.

```sql
-- MySQL 8.0+
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID())
);

-- PostgreSQL (uuid-ossp extension)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4()
);
```

**Misal:** `f47ac10b-58cc-4372-a567-0e02b2c3d479`

**Ustunlukler:**
- Globally unique, distributed system-de conflict yox
- Client tereflide generate olunur (round-trip yox)
- Predict edile bilmir (security)
- Replication-da safe

**Dezavantajlar - INDEX FRAGMENTATION:**
- Random sira ile insert - B-tree leaf-leri her yere dagilir
- Page split cox olur - INSERT yavas, disk write artir
- Range query cox yavas (`WHERE id > 'abc...'`)

**Storage tradeoff:**

| Storage | Olcu | Index olcu | Oxunaqliliq |
|---------|------|-----------|-------------|
| `CHAR(36)` | 36 byte | Boyuk | Beli |
| `BINARY(16)` | 16 byte | Yarisi | Yox (HEX() lazim) |
| `UUID` (PostgreSQL native) | 16 byte | Optimal | Beli |

```sql
-- MySQL: BINARY(16) optimization
INSERT INTO users (id) VALUES (UUID_TO_BIN(UUID(), 1));
-- Flag 1 = byte swap (timestamp-i one cek -> sequential)

SELECT BIN_TO_UUID(id, 1) FROM users;
```

---

## 4. UUID v7 (RFC 9562, 2024 standart)

48-bit Unix timestamp (ms) + 74-bit random + version/variant bits. Time-ordered.

```
0190a8b2-7c3d-7000-8abc-1234567890ab
└──── timestamp ───┘└── random ──┘
```

**Ustunlukler:**
- Globally unique (UUID v4 kimi)
- **Sequential INSERT** (auto_increment kimi - fragmentation yox)
- Yaranma vaxti ID-den oxuna bilir (`time-extraction`)
- 2024+ industry standart (Postgres 17 built-in, Laravel 11+ built-in)

```php
use Illuminate\Support\Str;

$id = Str::uuid7();      // Laravel 11+
// Yaxud
$id = Str::orderedUuid(); // UUID v6/v7 stil sequential
```

**Laravel HasUuids trait (UUID v7):**

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model {
    use HasUuids;
    
    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }
    
    public function uniqueIds(): array
    {
        return ['id', 'public_id'];
    }
}
```

```php
// config/database.php yaxud migration:
Schema::create('orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // PostgreSQL native UUID (16 byte)
    // MySQL CHAR(36)
});
```

---

## 5. ULID (Universally Unique Lexicographically Sortable Identifier)

128-bit. 48-bit timestamp (ms) + 80-bit random. Base32 encoded -> 26 char string.

```
01HQXR2K9P3M7N4VEW8ZYBCDFG
└── time ─┘└─── random ──┘
```

**Ustunlukler:**
- UUID v7 kimi sequential, amma 26 char (UUID 36 char-dan kicik)
- Case-insensitive base32 (no `O/0`, `I/1` qarisi)
- URL-safe
- Lexicographic sort = chronological sort

**Laravel:**

```php
$ulid = Str::ulid();        // 01HQXR2K9P3M7N4VEW8ZYBCDFG
$timestamp = Str::ulid()->toBase32(); 

// HasUlids trait (Laravel 9+)
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Post extends Model {
    use HasUlids;
}

// Migration
Schema::create('posts', function (Blueprint $table) {
    $table->ulid('id')->primary();
});
```

**ULID vs UUID v7 muqayisesi:**

| Xususiyyet | UUID v7 | ULID |
|------------|---------|------|
| Olcu (binary) | 16 byte | 16 byte |
| String uzunluk | 36 char | 26 char |
| Hex format | Beli | Yox (base32) |
| RFC standart | Beli (9562) | Yox |
| URL-safe | Lazim escape | Beli |
| Native DB type | Postgres `UUID` | Yox (CHAR/BINARY) |

---

## 6. Snowflake ID (Twitter)

64-bit, 3 hisseden ibaret:

```
| 1 bit | 41 bit timestamp | 10 bit machine | 12 bit sequence |
| sign  | (ms since epoch) | (1024 worker)  | (4096 per ms)   |
```

**Ustunlukler:**
- 8 byte (BIGINT-e siger - kicik index)
- Sequential (per machine)
- Distributed - merkezi koordinator yox
- Twitter, Discord, Instagram istifade edir

**Dezavantajlar:**
- Clock skew problemi (NTP zeruri)
- Machine ID idare etmek lazim (Zookeeper/etcd)
- 41-bit timestamp ~ 69 il (epoch-dan)

**PHP (godruoyi/php-snowflake):**

```php
use Godruoyi\Snowflake\Snowflake;

$snowflake = new Snowflake(
    datacenter: 1,
    workerId: env('SERVER_ID', 1)
);
$id = $snowflake->id(); // 1541815603606036480

// Discord ID-leri Snowflake-dir
// Twitter ID-leri Snowflake-dir
```

---

## 7. KSUID (Segment.com)

160-bit. 32-bit timestamp (sec) + 128-bit random. Base62 encoded -> 27 char.

```
0ujsswThIGTUYm2K8FjOOfXtY1K
```

- ULID-den boyukdur (160 vs 128 bit)
- Daha guclu randomness (128-bit vs 80-bit)
- Adgear/Segment standartlari

## 8. NanoID

URL-safe, custom alphabet. Default 21 char (UUID v4 ile eyni collision probability).

```
V1StGXR8_Z5jdHi6B-myT
```

```php
$id = (new \Hidehalo\Nanoid\Client())->generateId(21);
```

---

## Composite Strategy: Internal BIGINT + External UUID

**En yaxsi** strategy boyuk sistemde:

```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- daxili (FK ucun)
    public_id CHAR(26) NOT NULL UNIQUE,             -- xarici (URL, API)
    -- ...
    INDEX idx_public (public_id)
);
```

**Niye?**
- FK-ler BIGINT - kicik, suretli JOIN
- Public API ULID/UUID gosterir - IDOR yox
- Internal sequence sirri qalir
- Stripe modeli: `cus_AbC123XyZ`, `pi_3OqR5e2eZvKYlo2C0fGhIjKl`

**Laravel:**

```php
class Order extends Model {
    protected static function booted(): void
    {
        static::creating(function ($order) {
            $order->public_id = 'ord_' . Str::ulid();
        });
    }
    
    public function getRouteKeyName(): string
    {
        return 'public_id'; // /orders/ord_01HQX... routes
    }
}
```

---

## Performance Benchmarks (10M row INSERT)

| Strategy | INSERT time | Index size | Range scan |
|----------|------------|------------|------------|
| BIGINT auto_increment | 1.0x (baseline) | 80 MB | En suretli |
| UUID v4 (CHAR 36) | 3.5x yavas | 360 MB | Cox yavas |
| UUID v4 (BINARY 16) | 2.8x yavas | 160 MB | Cox yavas |
| UUID v7 (BINARY 16) | 1.1x yavas | 160 MB | Suretli |
| ULID (CHAR 26) | 1.3x yavas | 260 MB | Suretli |
| Snowflake (BIGINT) | 1.05x yavas | 80 MB | Suretli |

> Random UUID sequential UUID/Snowflake-den 3x yavas, cunki page split + random disk I/O.

---

## Security: Sequential Exposure Attacks

**IDOR (Insecure Direct Object Reference):**

```
GET /api/invoices/1234   -> own invoice
GET /api/invoices/1235   -> baska user-in invoice-i (auth check yoxsa)
```

**Solution:**
1. Auth check her yerde (best practice)
2. Public ID-leri obfuscate et (UUID/ULID)
3. Hashids istifade et (1234 -> "wpVL4j6m")

```php
// vinkla/hashids
$hashids = new \Hashids\Hashids('salt', 8);
$encoded = $hashids->encode(1234);     // "wpVL4j6m"
$decoded = $hashids->decode($encoded); // [1234]
```

---

## Hansi seciler?

| Use case | Tovsiye |
|----------|---------|
| Sade monolith, tek DB | BIGINT auto_increment |
| Multi-region, distributed | UUID v7 yaxud Snowflake |
| Public API exposure | Composite (BIGINT + ULID) |
| URL-da kicik gorunsun | Hashids (BIGINT-e) |
| Microservices, Kafka | UUID v7 / ULID |
| High-throughput log | Snowflake |
| Default Laravel layihe | UUID v7 (HasUuids) |

---

## Interview suallari

**Q: UUID v4 niye production-da problem yaradir?**
A: Random olduguna gore B-tree index-de leaf node-lar her yerde yarani, page split cox olur. INSERT 3-4x yavas, index 2x boyuk, range scan slow. Helli: UUID v7 yaxud ULID istifade et (time-ordered, sequential INSERT).

**Q: Niye public API-de auto_increment ID gostermek pisdir?**
A: 1) IDOR riski (ardicil ID-leri sinaq), 2) Business intelligence sizmasi (rakib `MAX(id)`-den user/order sayini bilir), 3) Crawl ucun asanliq. Helli: composite ID (internal BIGINT FK ucun + public ULID/UUID).

**Q: UUID v7 ile Snowflake arasinda ferq nedir?**
A: UUID v7 - 128 bit, RFC standart, koordinator lazim deyil, distributed system-de conflict 0%. Snowflake - 64 bit (BIGINT), kicik index, amma machine ID idare etmek lazim (Zookeeper). Snowflake daha sequential, UUID v7 daha portable.

**Q: BINARY(16) vs CHAR(36) UUID ucun?**
A: BINARY(16) 16 byte vs CHAR(36) 36 byte. Index 2x kicik, JOIN suretli. Dezavantaj: oxunaqli deyil (`HEX()` yaxud `BIN_TO_UUID()` lazim). Production-da BINARY(16) tovsiye olunur. PostgreSQL-de native `UUID` tipi 16 byte saxlayir + oxunaqli format gosterir.

**Q: Laravel-de UUID model-de nece islet?**
A: `use HasUuids;` trait elave et, `$keyType = 'string'` ve `$incrementing = false` qoy. Migration-da `$table->uuid('id')->primary()`. Laravel 11+ default UUID v7 yaradir (`Str::uuid7()`).
