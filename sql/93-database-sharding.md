# Database Sharding (Lead)

---

## 1. Sharding nədir — Horizontal Partitioning

**Sharding** — böyük bir verilənlər bazasını bir neçə kiçik, müstəqil hissəyə (shard) bölmək prosesidir. Hər shard öz məlumat dəstini saxlayır və ayrı bir server (və ya server qrupu) üzərində işləyir.

```
         Bütün məlumat (monolitik DB)
         +---------------------------+
         |  Users: 1 — 100,000,000   |
         +---------------------------+
                      |
              Sharding tətbiq edilir
                      |
         +------------+------------+
         |            |            |
    +----+----+  +----+----+  +----+----+
    | Shard 1 |  | Shard 2 |  | Shard 3 |
    | 1—33M   |  | 33—66M  |  | 66—100M |
    +---------+  +---------+  +---------+
    (Server A)   (Server B)   (Server C)
```

**Əsas ideya:** Verilənlər bir maşında saxlanmır — hər shard müstəqil bir DB instancedır.

**Niyə lazımdır:**
- Tək server artıq trafiki kaldıra bilmir (vertical scaling limitinə çatıldı)
- Write yükü çox yüksəkdir (read replica-lar kifayət etmir)
- Dataset o qədər böyükdür ki, tək diskin tutumu çatmır
- Latency tələbləri geografik bölgüyə görə fərqlənir

**Horizontal Partitioning vs Vertical Scaling:**
```
Vertical Scaling (Scale Up):        Horizontal Sharding (Scale Out):
+------------------+                +--------+ +--------+ +--------+
|  Daha böyük      |                | Shard1 | | Shard2 | | Shard3 |
|  server alırsan  |                | (kiçik)| | (kiçik)| | (kiçik)|
|  (CPU, RAM, SSD) |                +--------+ +--------+ +--------+
+------------------+                Limit yoxdur, shard əlavə etmək olar
Fiziki limit var!
```

---

## 2. Sharding vs Partitioning vs Replication — Fərqlər

### Partitioning (Bölünmə)
Məlumatı **eyni DB server** daxilində məntiqi hissələrə bölür.

```
+------------------------------+
|         Tək Server           |
|  +----------+ +----------+   |
|  |Partition1| |Partition2|   |  <- Eyni DB engine
|  | Jan-Jun  | | Jul-Dec  |   |
|  +----------+ +----------+   |
+------------------------------+
```

- MySQL PARTITION BY, PostgreSQL table partitioning
- Sorğular eyni DB bağlantısından gedir
- Admin tərəfdən şəffafdır
- Horizontal scale yoxdur

### Sharding (Parçalanma)
Məlumatı **fərqli fiziki serverlərdə** saxlayır.

```
+----------+    +----------+    +----------+
| Server A |    | Server B |    | Server C |
| Shard 1  |    | Shard 2  |    | Shard 3  |
| users    |    | users    |    | users    |
| 1—10M    |    | 10—20M   |    | 20—30M   |
+----------+    +----------+    +----------+
Application hər sorğu üçün doğru serveri seçməlidir
```

- Fərqli bağlantılar, fərqli hostlar
- Horizontal scale var
- Mürəkkəbliyi artırır

### Replication (Replikasiya)
**Eyni məlumatın** bir neçə serverdə kopyasını saxlamaq.

```
        Write
          |
    +-----+-----+
    |   Master  |
    +-----+-----+
          |  Replikasiya
    +-----+-----+
    |            |
+---+----+  +----+---+
| Slave1 |  | Slave2 |  <- Eyni məlumat
| (Read) |  | (Read) |
+--------+  +--------+
```

- Read yükünü azaldır
- Write yükünü azaltmır
- Fault tolerance təmin edir
- Məlumat eynidir (eventual consistency)

### Müqayisə Cədvəli

| Xüsusiyyət         | Partitioning | Sharding      | Replication     |
|--------------------|-------------|---------------|-----------------|
| Fərqli serverlər   | Xeyr        | Bəli          | Bəli            |
| Write scale        | Az          | Bəli          | Xeyr            |
| Read scale         | Az          | Bəli          | Bəli            |
| Məlumat fərqliliyi | Bəli        | Bəli          | Xeyr (eynidir)  |
| Mürəkkəblik        | Az          | Yüksək        | Orta            |
| Fault tolerance    | Aşağı       | Orta          | Yüksək          |

---

## 3. Shard Key Seçimi — Vacibliyi və Meyarlar

Shard key — məlumatın hansı sharda gedəcəyini müəyyən edən sütun (və ya sütunlar toplusu). **Yanlış shard key seçimi bütün arxitekturanı məhv edə bilər.**

### Əsas Meyarlar

#### 3.1 Cardinality (Kardinallik)
Shard key-in fərqli dəyərlərinin sayı.

```
Aşağı cardinality (PIST):           Yüksək cardinality (YAXŞI):
+--------+--------+                  +---------+---------+
| status | count  |                  | user_id |  count  |
+--------+--------+                  +---------+---------+
| active | 5M     |  <- 1 sharda    | 1001    |   1     |
| banned | 100K   |  <- 1 sharda    | 1002    |   1     |
| guest  | 2M     |  <- 1 sharda    | ...     |  ...    |
+--------+--------+                  | 5000000 |   1     |
Yalnız 3 dəyər = 3 shard max         +---------+---------+
                                     Milyonlarla unikal dəyər
```

**Qayda:** Shard key-in minimum kardinalliği shard sayından çox olmalıdır.

#### 3.2 Distribution (Paylama)
Məlumatın shardlar arasında bərabər paylanması.

```
Qeyri-bərabər paylama (PIST):
Shard 1: ||||||||||||||||||||  (18M records)
Shard 2: ||||                  (3M records)
Shard 3: ||||||||||||||        (13M records)

Bərabər paylama (YAXŞI):
Shard 1: ||||||||||||          (10M records)
Shard 2: ||||||||||||          (10M records)
Shard 3: ||||||||||||          (10M records)
```

#### 3.3 Query Patterns (Sorğu Nümunələri)
Ən çox istifadə olunan sorğular bir sharda yönəlməlidir.

```
// PİST: Bir user-in bütün sifarişlərini tapmaq
// order_id ilə shard edilib, amma sorğu user_id-yədir
SELECT * FROM orders WHERE user_id = 123;
// Bu sorğu BÜTÜN shardlara gedir! (fan-out)

// YAXŞI: user_id ilə shard edilib
// Bir user-in sifarişləri həmişə eyni shardda
SELECT * FROM orders WHERE user_id = 123;
// Yalnız 1 sharda gedir
```

#### 3.4 Shard Key Seçiminin Qaydaları

1. **Dəyişməzlik:** Shard key dəyişməməlidir (email dəyişə bilər, UUID dəyişməz)
2. **Sorğu uyğunluğu:** Ən çox işlədilən WHERE şərtini əhatə etməlidir
3. **Bərabər paylama:** Hotspot yaratmamalıdır
4. **Monoton artım problemi:** Auto-increment ID-lər range sharding-də hotspot yaradır

*4. **Monoton artım problemi:** Auto-increment ID-lər range sharding-də üçün kod nümunəsi:*
```php
// Pis shard key: created_at (zamanla yeni yazılar həmişə son sharda gedir)
// Pis shard key: status (aşağı cardinality)
// Pis shard key: email (dəyişə bilər)

// Yaxşı shard key: user_id (UUID və ya hash)
// Yaxşı shard key: tenant_id (SaaS app-lər üçün)
// Yaxşı shard key: composite (region + user_id)
```

---

## 4. Sharding Strategiyaları

### 4.1 Range-Based Sharding (Diapazon əsaslı)

Məlumat müəyyən bir diapazona görə bölünür.

```
Shard Key: user_id

Shard 1: user_id  1       — 10,000,000
Shard 2: user_id  10M+1   — 20,000,000
Shard 3: user_id  20M+1   — 30,000,000
Shard 4: user_id  30M+1   — ...

+------------------+
| Router/Proxy     |
| user_id = 5M     |-----> Shard 1
| user_id = 15M    |-----> Shard 2
| user_id = 25M    |-----> Shard 3
+------------------+
```

**Üstünlüklər:**
- Range sorğuları effektivdir (`WHERE user_id BETWEEN 1 AND 1000`)
- İmplementasiyası sadədir
- Sıralı məlumat üçün yaxşıdır

**Çatışmazlıqlar:**
- Hotspot riski yüksəkdir (yeni yazılar həmişə son sharda gedir)
- Paylama qeyri-bərabər ola bilər
- Manual rebalancing tələb edir

*- Manual rebalancing tələb edir üçün kod nümunəsi:*
```php
function getShardByRange(int $userId): int
{
    $ranges = [
        ['min' => 1,          'max' => 10_000_000, 'shard' => 1],
        ['min' => 10_000_001, 'max' => 20_000_000, 'shard' => 2],
        ['min' => 20_000_001, 'max' => 30_000_000, 'shard' => 3],
    ];

    foreach ($ranges as $range) {
        if ($userId >= $range['min'] && $userId <= $range['max']) {
            return $range['shard'];
        }
    }

    return 3; // Son shard yeni yazıları qəbul edir
}
```

### 4.2 Hash-Based Sharding (Xeş əsaslı)

Shard key-in xeş dəyəri hesablanır, modulo ilə shard müəyyən edilir.

```
shard_number = hash(shard_key) % total_shards

user_id=100:  hash(100) % 4 = 2  --> Shard 2
user_id=101:  hash(101) % 4 = 3  --> Shard 3
user_id=102:  hash(102) % 4 = 0  --> Shard 0
user_id=103:  hash(103) % 4 = 1  --> Shard 1

+------------------+
| Router           |
| hash(user_id)%4  |
+------------------+
   |    |    |    |
  S0   S1   S2   S3
```

**Üstünlüklər:**
- Bərabər paylama
- Hotspot riski azdır
- Sadə hesablama

**Çatışmazlıqlar:**
- Range sorğuları bütün sharddlara gedir
- Shard sayı dəyişdikdə, demək olar ki, bütün məlumatı köçürmək lazımdır
- Consistent hashing olmadan resharding çox bahadır

*- Consistent hashing olmadan resharding çox bahadır üçün kod nümunəsi:*
```php
function getShardByHash(int|string $shardKey, int $totalShards): int
{
    $hash = crc32((string) $shardKey);
    // Mənfi dəyərlərdən qorunmaq üçün
    return abs($hash) % $totalShards;
}

// İstifadə:
$shard = getShardByHash($userId, 4); // 0, 1, 2, ya 3 qaytarır
```

### 4.3 Directory-Based Sharding (Lookup / Qovluq əsaslı)

Ayrıca bir "lookup cədvəli" hər shard key-in hansı shardda olduğunu saxlayır.

```
         Lookup Cədvəli (ayrı DB/cache)
         +------------------+
         | shard_key | shard |
         +------------------+
         | user_1001 |   2   |
         | user_1002 |   1   |
         | user_1003 |   3   |
         | user_1004 |   2   |
         +------------------+
                  |
    +-------------+-------------+
    |             |             |
+---+---+     +---+---+     +---+---+
|Shard1 |     |Shard2 |     |Shard3 |
+-------+     +-------+     +-------+
```

**Üstünlüklər:**
- Ən çevik yanaşma
- Resharding asandır (lookup cədvəlini dəyiş)
- Fərqli shard ölçüləri mümkündür
- Xüsusi iş məntiqi əlavə etmək olar

**Çatışmazlıqlar:**
- Lookup cədvəli single point of failure ola bilər
- Əlavə gecikməli (hər sorğudan əvvəl lookup lazımdır)
- Cache etməzsən, performans aşağı düşür

*- Cache etməzsən, performans aşağı düşür üçün kod nümunəsi:*
```php
class DirectoryShardRouter
{
    public function __construct(
        private Redis $lookupCache,
        private PDO   $lookupDb
    ) {}

    public function getShard(int $userId): int
    {
        $cacheKey = "shard:user:{$userId}";

        // Önce keşi yoxla
        $shard = $this->lookupCache->get($cacheKey);
        if ($shard !== false) {
            return (int) $shard;
        }

        // DB-dən al
        $stmt = $this->lookupDb->prepare(
            'SELECT shard_id FROM shard_map WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $shard = (int) $stmt->fetchColumn();

        // Keşə yaz (1 saat)
        $this->lookupCache->setex($cacheKey, 3600, $shard);

        return $shard;
    }
}
```

### 4.4 Geographic Sharding (Coğrafi əsaslı)

İstifadəçinin coğrafi yerləşməsinə görə shard seçilir.

```
               Global Load Balancer
                       |
         +-------------+-------------+
         |             |             |
    +----+----+   +----+----+   +----+----+
    |  EU     |   |  US     |   |  APAC   |
    |  Shard  |   |  Shard  |   |  Shard  |
    | (Paris) |   |(Virginia)|  |(Tokyo)  |
    +---------+   +---------+   +---------+
    EU users       US users      Asia users
```

**Üstünlüklər:**
- Aşağı latency (məlumat istifadəçiyə yaxındır)
- GDPR kimi qanunlara uyğunluq (məlumat Avropada saxlanır)
- Regional qazalar təsirini azaldır

**Çatışmazlıqlar:**
- Köçən istifadəçilər üçün mürəkkəblik
- Cross-region sorğular çox yavaşdır
- Müxtəlif regionlarda fərqli yük ola bilər

*- Müxtəlif regionlarda fərqli yük ola bilər üçün kod nümunəsi:*
```php
function getShardByCountry(string $countryCode): string
{
    $regionMap = [
        'AZ' => 'eu-shard',
        'DE' => 'eu-shard',
        'FR' => 'eu-shard',
        'US' => 'us-shard',
        'CA' => 'us-shard',
        'JP' => 'apac-shard',
        'CN' => 'apac-shard',
    ];

    return $regionMap[$countryCode] ?? 'us-shard'; // Default
}
```

---

## 5. Hotspot Problemi

**Hotspot** — bəzi shardların digərlərinə nisbətən qeyri-mütənasib yük alması.

### Hotspot Növləri

#### 5.1 Write Hotspot (Yazma)
```
Shard 1: ████████████████████  (95% yazma)
Shard 2: █                     (3% yazma)
Shard 3: █                     (2% yazma)

Səbəb: Auto-increment ID + Range sharding
Yeni yazılar həmişə ən son ID-yə gedir = Shard 1
```

#### 5.2 Read Hotspot (Oxuma)
```
Məşhur istifadəçi (1M follower):
Shard 2: ████████████████████  (Hər saniyə minlərlə read)
Shard 1: ██                    (Normal yük)
Shard 3: ██                    (Normal yük)

Səbəb: "Celebrity" problem — məşhur entity çox oxunur
```

#### 5.3 Temporal Hotspot (Zamana görə)
```
Yeni yazılar həmişə "bu günün" shardına gedir:
TODAY shard:   ████████████████████  (çox aktiv)
YESTERDAY:     ████                  (az read)
LAST_WEEK:     █                     (nadir read)
```

### Hotspot-dan Qaçınma Yolları

**1. Shard Key-ə Random Suffix əlavə etmək:**
```php
// Hotspot key: product_id = 5 (çox satılan məhsul)
// Hotspot yarat: hash(5) % 4 = həmişə eyni shard

// Həll: Shard key-i genişlət
function getHotKeyShardKey(int $productId, int $suffixRange = 10): string
{
    $suffix = random_int(1, $suffixRange);
    return "{$productId}_{$suffix}"; // "5_3", "5_7" kimi
}

// Oxuyarkən bütün suffix variantlarını sorğula və birləşdir
function readFromAllSuffixes(int $productId, int $suffixRange = 10): array
{
    $results = [];
    for ($i = 1; $i <= $suffixRange; $i++) {
        $key = "{$productId}_{$i}";
        $shard = getShardByHash($key, 4);
        $results[] = queryFromShard($shard, $key);
    }
    return array_merge(...$results);
}
```

**2. Hash-based sharding istifadə etmək (range əvəzinə):**
```
Range (HOTSPOT riski):          Hash (Bərabər paylama):
user_id ASC: 1,2,3,4...         hash(user_id) % 4:
Yeni yazılar son sharda!        1 -> Shard 2
                                2 -> Shard 0
                                3 -> Shard 3
                                4 -> Shard 1
```

**3. Read Hotspot üçün Cache əlavə etmək:**
```php
// Məşhur istifadəçi profili çox oxunur
// Həll: Redis keşdə saxla
function getUserProfile(int $userId): array
{
    $cacheKey = "user:profile:{$userId}";
    $cached = $this->redis->get($cacheKey);

    if ($cached) {
        return json_decode($cached, true);
    }

    $shard = $this->router->getShard($userId);
    $profile = $this->shards[$shard]->query(
        'SELECT * FROM users WHERE id = ?', [$userId]
    );

    $this->redis->setex($cacheKey, 300, json_encode($profile));
    return $profile;
}
```

**4. Composite Shard Key:**
```php
// Yalnız user_id əvəzinə (user_id, created_month) cütü
// Bu, temporal hotspot-ları azaldır
$shardKey = $userId . '_' . date('Ym'); // "12345_202403"
$shard = getShardByHash($shardKey, $totalShards);
```

---

## 6. Cross-Shard Sorğular

### Problem
```
// Sadə sorğu (bir shardda):
SELECT * FROM users WHERE user_id = 123;
// -> Yalnız Shard 2-yə gedir. Sürətli!

// Cross-shard sorğu:
SELECT * FROM users WHERE age > 25 ORDER BY name LIMIT 10;
// -> Bütün sharddlara gedir! (Fan-out)
```

### Fan-Out Sorğuları

```
           Application
               |
    +----------+----------+
    |          |          |
+---+---+  +---+---+  +---+---+
|Shard 1|  |Shard 2|  |Shard 3|
|top 10 |  |top 10 |  |top 10 |
+---+---+  +---+---+  +---+---+
    |          |          |
    +----------+----------+
               |
        Merge & Sort
        (Qlobal top 10)
```

**Çətinliklər:**

1. **Aggregation:** `COUNT(*)`, `SUM()`, `AVG()` — hər sharddan ayrı nəticə, sonra birləşdirmək lazımdır
2. **ORDER BY + LIMIT:** Hər sharddan N yazı al, hamısını birləşdir, sonra sırala — çox baha
3. **JOIN:** Cross-shard JOIN mümkün deyil (fərqli serverlərdədir)
4. **GROUP BY:** Hər shardda qrupla, sonra birləşdir

### PHP-də Fan-Out İmplementasiyası

*PHP-də Fan-Out İmplementasiyası üçün kod nümunəsi:*
```php
class CrossShardQueryExecutor
{
    public function __construct(
        private array $shards, // [shard_id => PDO]
        private int   $totalShards
    ) {}

    /**
     * Bütün sharddlarda sorğu icra et, nəticələri birləşdir
     */
    public function fanOut(string $query, array $params = []): array
    {
        $promises = [];

        // Parallel icra (ideal halda async)
        foreach ($this->shards as $shardId => $pdo) {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $promises[$shardId] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_merge(...array_values($promises));
    }

    /**
     * COUNT-u bütün sharddlarda icra et
     */
    public function countAll(string $table, string $where = '1=1'): int
    {
        $total = 0;
        foreach ($this->shards as $pdo) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}");
            $total += (int) $stmt->fetchColumn();
        }
        return $total;
    }

    /**
     * Cross-shard ORDER BY LIMIT (N*shardCount oxuyur, sonra N qaytarır)
     */
    public function queryWithGlobalSort(
        string $table,
        string $orderBy,
        int    $limit,
        string $direction = 'ASC'
    ): array {
        $allResults = [];

        foreach ($this->shards as $pdo) {
            // Hər sharddan LIMIT qədər al (ən pis hal üçün)
            $stmt = $pdo->query(
                "SELECT * FROM {$table} ORDER BY {$orderBy} {$direction} LIMIT {$limit}"
            );
            $allResults = array_merge($allResults, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Qlobal sıralama
        usort($allResults, function ($a, $b) use ($orderBy, $direction) {
            $cmp = $a[$orderBy] <=> $b[$orderBy];
            return $direction === 'DESC' ? -$cmp : $cmp;
        });

        return array_slice($allResults, 0, $limit);
    }
}
```

### Cross-Shard Sorğulardan Qaçınma Strategiyaları

1. **Denormalization:** Çox lazımlı məlumatı hər sharda kopyala
2. **Application-level JOIN:** Hər sharddan ayrıca al, PHP-də birləşdir
3. **Scatter-Gather pattern:** Bütün sharddlara göndər, cavabları birləşdir
4. **Global secondary index:** Ayrıca bir xidmət müəyyən sütunlar üzrə qlobal indeks saxlayır (Elasticsearch kimi)

---

## 7. Cross-Shard Transaction-lar

### Problem
```
// Bir istifadəçidən digərinə pul köçürmə
// user_id=100 -> Shard 1
// user_id=200 -> Shard 2

BEGIN TRANSACTION;
  UPDATE accounts SET balance = balance - 100 WHERE user_id = 100; -- Shard 1
  UPDATE accounts SET balance = balance + 100 WHERE user_id = 200; -- Shard 2
COMMIT;

// Bu mümkün deyil! Fərqli serverlərdədir.
```

### Həll Yolları

#### 7.1 Two-Phase Commit (2PC)

```
Koordinator (Application)
        |
   PREPARE fazı
        |
+-------+-------+
|               |
v               v
Shard 1         Shard 2
PREPARE OK      PREPARE OK
        |
   COMMIT fazı
        |
+-------+-------+
|               |
v               v
Shard 1         Shard 2
COMMIT          COMMIT
```

**Addımlar:**
1. **Phase 1 (Prepare):** Koordinator bütün iştirakçılardan "hazırsınız?" soruşur
2. **Phase 2 (Commit):** Hamı "hazır" deyirsə, COMMIT göndər; biri "xeyr" deyirsə, ROLLBACK

**Çatışmazlıqlar:**
- Koordinator uğursuz olarsa, iştirakçılar bloklanır
- Yavaşdır (2 round-trip)
- Blocking protocol (lock-lar uzun müddət tutulur)

*- Blocking protocol (lock-lar uzun müddət tutulur) üçün kod nümunəsi:*
```php
class TwoPhaseCommitCoordinator
{
    private array $participants = [];

    public function addParticipant(DatabaseParticipant $participant): void
    {
        $this->participants[] = $participant;
    }

    public function execute(callable $transactionFn): bool
    {
        // Phase 1: Prepare
        $prepared = [];
        foreach ($this->participants as $participant) {
            try {
                $participant->prepare();
                $prepared[] = $participant;
            } catch (\Exception $e) {
                // Biri uğursuz oldu, hamısını abort et
                foreach ($prepared as $p) {
                    $p->rollback();
                }
                return false;
            }
        }

        // Phase 2: Commit
        foreach ($this->participants as $participant) {
            try {
                $participant->commit();
            } catch (\Exception $e) {
                // Bu nöqtədə rollback çox çətindir (split-brain riski)
                $this->logCompensatingAction($participant, $e);
                return false;
            }
        }

        return true;
    }
}
```

#### 7.2 Saga Pattern

Hər addım müstəqil transaction, uğursuzluq halında kompensasiya əməliyyatı icra edilir.

```
Addım 1: Shard1-dən 100$ çıx
    Uğurlu -> Addım 2-yə keç
    Uğursuz -> Heç nə (hər şey sıfırdır)

Addım 2: Shard2-yə 100$ əlavə et
    Uğurlu -> TAMAMLANDI
    Uğursuz -> Kompensasiya: Shard1-ə 100$ geri əlavə et (rollback əməliyyatı)
```

*Uğursuz -> Kompensasiya: Shard1-ə 100$ geri əlavə et (rollback əməliyy üçün kod nümunəsi:*
```php
class MoneyTransferSaga
{
    public function __construct(
        private ShardRouter $router,
        private EventBus    $eventBus
    ) {}

    public function execute(int $fromUserId, int $toUserId, float $amount): void
    {
        $sagaId = uniqid('saga_', true);

        try {
            // Step 1: Pul çıxarma
            $this->debitUser($fromUserId, $amount, $sagaId);

            // Step 2: Pul əlavə etmə
            $this->creditUser($toUserId, $amount, $sagaId);

            $this->eventBus->publish('saga.completed', ['saga_id' => $sagaId]);

        } catch (CreditFailedException $e) {
            // Step 2 uğursuz oldu, Step 1-i geri qaytar
            $this->compensateDebit($fromUserId, $amount, $sagaId);
            throw $e;
        }
    }

    private function debitUser(int $userId, float $amount, string $sagaId): void
    {
        $shard = $this->router->getShard($userId);
        // Idempotent əməliyyat — saga_id ilə dublikatdan qorun
        $shard->execute(
            'INSERT INTO transactions (saga_id, user_id, amount, type)
             VALUES (?, ?, ?, "debit")
             ON DUPLICATE KEY UPDATE id=id',
            [$sagaId, $userId, $amount]
        );
        $shard->execute(
            'UPDATE accounts SET balance = balance - ? WHERE user_id = ?',
            [$amount, $userId]
        );
    }

    private function compensateDebit(int $userId, float $amount, string $sagaId): void
    {
        $shard = $this->router->getShard($userId);
        $shard->execute(
            'UPDATE accounts SET balance = balance + ? WHERE user_id = ?
             WHERE EXISTS (SELECT 1 FROM transactions WHERE saga_id = ? AND type = "debit")',
            [$amount, $userId, $sagaId]
        );
    }
}
```

#### 7.3 Eventual Consistency

Dərhal konsistentlik tələb etməyin, zamanla konsistent ol.

```
Transfer request gəldi
        |
   Message Queue-ya yaz
        |
   +----+----+
   |         |
Worker 1   Worker 2
Shard1-dən  Shard2-yə
çıxar       əlavə edir
(async)     (async)
```

**Hansını seçmək:**
- Maliyyə sistemləri → 2PC və ya Saga
- Social media beğənilər → Eventual consistency (bir az delay OK)
- E-commerce sifarişlər → Saga pattern (kompensasiya mümkündür)

---

## 8. Resharding

### Problem
Mövcud shardlar dolduqda və ya yük artdıqda yeni shard əlavə etmək lazımdır.

```
Əvvəl (3 shard):
Shard 1: ████████████████████ (dolu, 10M records)
Shard 2: ████████████████████ (dolu, 10M records)
Shard 3: ████████████████████ (dolu, 10M records)

Sonra (4 shard əlavə etmək istəyirik):
Shard 1: ?
Shard 2: ?
Shard 3: ?
Shard 4: ? (yeni)

Naiv hash: shard = hash(key) % 3 --> shard = hash(key) % 4
Problem: Demək olar bütün məlumatı köçürmək lazımdır!
```

### Naiv Hash-in Problemi

```
3 shard ilə:              4 shard ilə:
user_id=1: 1%3=1          1%4=1 (eyni)
user_id=2: 2%3=2          2%4=2 (eyni)
user_id=3: 3%3=0          3%4=3 (fərqli! Köçmək lazımdır)
user_id=4: 4%3=1          4%4=0 (fərqli! Köçmək lazımdır)
user_id=5: 5%3=2          5%4=1 (fərqli! Köçmək lazımdır)
user_id=6: 6%3=0          6%4=2 (fərqli! Köçmək lazımdır)

3-dən 4-ə keçdikdə ~75% məlumat köçürlür!
```

### Consistent Hashing (Konsistent Xeşləmə)

```
Hash ring (0 — 2^32 dairəvi):

              0
           /     \
    3GB  /         \ 1GB
        /           \
  2.5GB               1.5GB
        \           /
         \         /
          \       /
              2GB

Serverlər ring-ə yerləşdirilir:
Server A @ 1GB
Server B @ 2GB
Server C @ 3GB

user_id xeşi hesablanır, saat istiqamətindəki növbəti serverə gedir:
hash(user_id) = 1.3GB -> Server B (növbəti saat istiqamətindəki server)
hash(user_id) = 2.5GB -> Server C
hash(user_id) = 0.5GB -> Server A
```

**Yeni server əlavə etdikdə:**
```
Server D @ 1.5GB əlavə edildi:
Əvvəl: 1GB — 2GB arası Server B-nin idi
İndi:  1GB — 1.5GB Server B, 1.5GB — 2GB Server D

Yalnız 1.5GB ilə 2GB arası köçür (minimum dəyişiklik)!
```

**PHP-də Consistent Hashing:**
```php
class ConsistentHashRing
{
    private array $ring = [];
    private array $sortedKeys = [];
    private int   $replicas;

    public function __construct(int $replicas = 150)
    {
        // Replicas: virtual node sayı (yükü bərabərləşdirir)
        $this->replicas = $replicas;
    }

    public function addNode(string $node): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $hash = crc32("{$node}:{$i}");
            $this->ring[$hash] = $node;
        }
        ksort($this->ring);
        $this->sortedKeys = array_keys($this->ring);
    }

    public function removeNode(string $node): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $hash = crc32("{$node}:{$i}");
            unset($this->ring[$hash]);
        }
        $this->sortedKeys = array_keys($this->ring);
    }

    public function getNode(string $key): string
    {
        if (empty($this->ring)) {
            throw new \RuntimeException('Ring is empty');
        }

        $hash = crc32($key);

        // Saat istiqamətindəki ilk node-u tap
        foreach ($this->sortedKeys as $ringHash) {
            if ($hash <= $ringHash) {
                return $this->ring[$ringHash];
            }
        }

        // Ring-in sonuna çatdıq, birinci node-a qayıt
        return $this->ring[$this->sortedKeys[0]];
    }
}

// İstifadə:
$ring = new ConsistentHashRing(150);
$ring->addNode('shard1.db.local');
$ring->addNode('shard2.db.local');
$ring->addNode('shard3.db.local');

$targetShard = $ring->getNode((string) $userId);

// Yeni shard əlavə et — minimal köçürmə
$ring->addNode('shard4.db.local');
```

### Resharding Prosesi

```
1. Yeni shard-ı hazırla (boş server)
2. Köçürüləcək məlumatı müəyyən et
3. Background migration başlat:
   - Məlumatı yeni sharda kopyala
   - Hər iki shardı sync saxla (double-write)
4. Traffic-i yeni sharda yönləndir
5. Köhnə sharddan məlumatı sil
6. Monitoring (xəta yoxdur?)
```

*6. Monitoring (xəta yoxdur?) üçün kod nümunəsi:*
```php
class ShardMigrator
{
    public function migrate(
        int    $oldShardId,
        int    $newShardId,
        string $table,
        array  $userIds
    ): void {
        $oldShard = $this->getShardConnection($oldShardId);
        $newShard = $this->getShardConnection($newShardId);

        $batchSize = 1000;
        $chunks = array_chunk($userIds, $batchSize);

        foreach ($chunks as $chunk) {
            // Məlumatı köhnə sharddan oxu
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $oldShard->prepare(
                "SELECT * FROM {$table} WHERE user_id IN ({$placeholders})"
            );
            $stmt->execute($chunk);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Yeni sharda yaz
            $newShard->beginTransaction();
            foreach ($rows as $row) {
                $this->insertRow($newShard, $table, $row);
            }
            $newShard->commit();

            // Lookup cədvəlini yenilə
            foreach ($chunk as $userId) {
                $this->updateShardMap($userId, $newShardId);
            }

            // Limitsiz yükdən qorun
            usleep(10_000); // 10ms gözlə
        }
    }
}
```

---

## 9. PHP İmplementasiyası: Shard Router

### Əsas Arxitektura

```
+------------------+
|   Application    |
+--------+---------+
         |
+--------+---------+
|   ShardRouter    |  <- Hansı DB-yə getməli?
+--------+---------+
         |
+--------+---------+---------+
|        |         |         |
| PDO    | PDO     | PDO     |
|Shard 1 |Shard 2  |Shard 3  |
+--------+---------+---------+
```

### PHP Shard Router İmplementasiyası

*PHP Shard Router İmplementasiyası üçün kod nümunəsi:*
```php
<?php

declare(strict_types=1);

class ShardConfiguration
{
    public function __construct(
        public readonly int    $shardId,
        public readonly string $host,
        public readonly int    $port,
        public readonly string $dbName,
        public readonly string $username,
        public readonly string $password,
    ) {}
}

class ShardConnectionPool
{
    /** @var array<int, PDO> */
    private array $connections = [];

    /** @var array<int, ShardConfiguration> */
    private array $configs;

    public function __construct(array $configs)
    {
        foreach ($configs as $config) {
            $this->configs[$config->shardId] = $config;
        }
    }

    public function getConnection(int $shardId): PDO
    {
        if (!isset($this->connections[$shardId])) {
            $config = $this->configs[$shardId]
                ?? throw new \InvalidArgumentException("Unknown shard: {$shardId}");

            $dsn = "mysql:host={$config->host};port={$config->port};dbname={$config->dbName};charset=utf8mb4";

            $this->connections[$shardId] = new PDO($dsn, $config->username, $config->password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => true,
            ]);
        }

        return $this->connections[$shardId];
    }
}

interface ShardStrategy
{
    public function getShardId(int|string $shardKey): int;
}

class HashShardStrategy implements ShardStrategy
{
    public function __construct(private int $totalShards) {}

    public function getShardId(int|string $shardKey): int
    {
        return abs(crc32((string) $shardKey)) % $this->totalShards;
    }
}

class RangeShardStrategy implements ShardStrategy
{
    /** @var array<array{min: int, max: int, shard: int}> */
    private array $ranges;

    public function __construct(array $ranges)
    {
        $this->ranges = $ranges;
    }

    public function getShardId(int|string $shardKey): int
    {
        foreach ($this->ranges as $range) {
            if ($shardKey >= $range['min'] && $shardKey <= $range['max']) {
                return $range['shard'];
            }
        }
        throw new \OutOfRangeException("No shard found for key: {$shardKey}");
    }
}

class ShardRouter
{
    public function __construct(
        private ShardConnectionPool $pool,
        private ShardStrategy       $strategy,
    ) {}

    public function getShardId(int|string $shardKey): int
    {
        return $this->strategy->getShardId($shardKey);
    }

    public function getConnection(int|string $shardKey): PDO
    {
        $shardId = $this->getShardId($shardKey);
        return $this->pool->getConnection($shardId);
    }

    public function getAllConnections(): array
    {
        // Fan-out sorğular üçün
        return $this->pool->getAllConnections();
    }
}

// Repository tərəfindən istifadə
class UserRepository
{
    public function __construct(private ShardRouter $router) {}

    public function findById(int $userId): ?array
    {
        $conn = $this->router->getConnection($userId);
        $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $userData): int
    {
        $userId = $userData['id']; // UUID və ya pre-generated ID
        $conn   = $this->router->getConnection($userId);

        $stmt = $conn->prepare(
            'INSERT INTO users (id, name, email, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $userData['name'], $userData['email']]);

        return $userId;
    }

    public function update(int $userId, array $data): void
    {
        $conn = $this->router->getConnection($userId);
        // ... update logic
    }

    public function delete(int $userId): void
    {
        $conn = $this->router->getConnection($userId);
        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Fan-out: Bütün shardlarda axtarış
     */
    public function searchByEmail(string $email): ?array
    {
        foreach ($this->router->getAllConnections() as $conn) {
            $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $result = $stmt->fetch();
            if ($result) {
                return $result;
            }
        }
        return null;
    }
}

// Bootstrap / DI Container
$configs = [
    new ShardConfiguration(0, 'shard0.db.local', 3306, 'app_db', 'user', 'pass'),
    new ShardConfiguration(1, 'shard1.db.local', 3306, 'app_db', 'user', 'pass'),
    new ShardConfiguration(2, 'shard2.db.local', 3306, 'app_db', 'user', 'pass'),
    new ShardConfiguration(3, 'shard3.db.local', 3306, 'app_db', 'user', 'pass'),
];

$pool     = new ShardConnectionPool($configs);
$strategy = new HashShardStrategy(totalShards: 4);
$router   = new ShardRouter($pool, $strategy);

$userRepo = new UserRepository($router);
```

---

## 10. Global Cədvəllər (Shard Edilməmiş Reference Data)

Bəzi cədvəllər shard edilmir — hər shardda eyni məlumat olur, ya da ayrıca bir "global" serverdə saxlanır.

### Nümunələr

```
Shard edilən:                    Shard edilməyən (global):
- users                          - countries
- orders                         - currencies
- user_sessions                  - product_categories
- messages                       - configuration
- payments                       - feature_flags
```

### Strategiyalar

#### 10.1 Hər Shardda Kopyala (Broadcast Tables)

```
        Global Cədvəl: countries (250 ölkə)
               |
    +----------+----------+
    |          |          |
+---+---+  +---+---+  +---+---+
|Shard 1|  |Shard 2|  |Shard 3|
|country|  |country|  |country|  <- Eyni məlumat hər yerdə
|  250  |  |  250  |  |  250  |
+-------+  +-------+  +-------+
```

**Üstünlüklər:** JOIN-lər local olaraq işləyir
**Çatışmazlıqlar:** Update-lər bütün sharddlarda eyni anda edilməlidir

***Çatışmazlıqlar:** Update-lər bütün sharddlarda eyni anda edilməlidir üçün kod nümunəsi:*
```php
class GlobalTableSynchronizer
{
    public function broadcastUpdate(string $table, array $data): void
    {
        foreach ($this->router->getAllConnections() as $conn) {
            $conn->beginTransaction();
            // Cədvəli sil və yenidən yaz (kiçik cədvəllər üçün)
            $conn->exec("TRUNCATE TABLE {$table}");
            foreach ($data as $row) {
                $this->insertRow($conn, $table, $row);
            }
            $conn->commit();
        }
    }
}
```

#### 10.2 Ayrıca Global DB

```
+------------------+
|   Global DB      |  <- Heç shard edilmir
|  (Read-heavy)    |
|  - countries     |
|  - currencies    |
|  - config        |
+------------------+
        |
+-------+-------+
|       |       |
S1      S2      S3   <- Lazım gəldikdə Global DB-yə sorğu
```

#### 10.3 Application Cache-ə Yüklə

*10.3 Application Cache-ə Yüklə üçün kod nümunəsi:*
```php
class ReferenceDataService
{
    private array $cache = [];

    public function getCountries(): array
    {
        if (empty($this->cache['countries'])) {
            // Yalnız bir dəfə yüklə, Redis-də saxla
            $cached = $this->redis->get('ref:countries');
            if ($cached) {
                $this->cache['countries'] = json_decode($cached, true);
            } else {
                $data = $this->globalDb->query('SELECT * FROM countries')->fetchAll();
                $this->redis->setex('ref:countries', 86400, json_encode($data));
                $this->cache['countries'] = $data;
            }
        }
        return $this->cache['countries'];
    }
}
```

---

## 11. Nə Vaxt Shard Etməli

### PREMATURE OPTIMIZATION — Ən Böyük Günah

```
Sharding əlavə edir:
- Operational mürəkkəblik
- Cross-shard transaction headaches
- Fan-out sorğu performans problemləri
- Monitoring mürəkkəbliyi
- Developer onboarding çətinliyi
```

### Shardingdən Əvvəl Yoxla:

```
Step 1: Query optimizasiyası
        - İndekslər düzgündürmü?
        - N+1 problem varmı?
        - EXPLAIN ANALYZE baxdın?

Step 2: Caching əlavə et
        - Redis/Memcached
        - Application-level cache
        - Query result cache

Step 3: Read Replica-lar
        - Write: Master
        - Read: Slave-lər
        - 80% read, 20% write — bu böyük win-dir

Step 4: Vertical Scaling
        - Daha çox RAM (in-memory buffer pool)
        - Daha sürətli SSD
        - Daha güclü CPU

Step 5: DB-yə özəl optimizasiyalar
        - InnoDB buffer pool tune
        - Partitioning (eyni server, sürətli)
        - Archiving (köhnə məlumatı köç)

Step 6: Sharding (lazım gəlibsə)
```

### Sharding Zamanı Gəldiyi Göstəricilər

```
1. Write throughput:
   Master DB CPU daim > 70-80%
   Write latency artır, replica-lar kömək etmir

2. Storage:
   Tək server saxlaya bilmir (TB-larla data)
   Backup/restore çox uzun çəkir

3. Dataset:
   InnoDB buffer pool dataset-in 10%-nə belə çatmır
   Cache hit rate çox aşağıdır

4. SLA pozulur:
   p99 latency SLA-nı keçir
   Optimizasiyalar artıq kömək etmir
```

---

## 12. Sharding Alternativləri

### 12.1 Read Replica-lar

```
              +----------+
Write ------> |  Master  |
              +----+-----+
                   |  Replikasiya (async)
              +----+-----+
              |          |
         +----+--+   +---+---+
Read --> |Slave 1|   |Slave 2|
         +-------+   +-------+
```

*+-------+   +-------+ üçün kod nümunəsi:*
```php
class ReadWriteRouter
{
    public function getConnection(string $operation): PDO
    {
        return match($operation) {
            'write' => $this->masterConnection,
            'read'  => $this->readReplicas[array_rand($this->readReplicas)],
            default => throw new \InvalidArgumentException("Unknown: {$operation}"),
        };
    }
}
```

**Nə zaman:** Read ağır iş yükü, write az

### 12.2 Caching

```
Request
   |
Redis Cache ---> HIT -> Response
   |
  MISS
   |
  DB ----> Response + Cache-ə yaz
```

**Nə zaman:** Eyni məlumat tez-tez oxunur, stale data OK

### 12.3 Table Partitioning (Eyni server)

*12.3 Table Partitioning (Eyni server) üçün kod nümunəsi:*
```sql
-- MySQL Range Partitioning
CREATE TABLE orders (
    id         INT,
    user_id    INT,
    created_at DATE
) PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION pmax  VALUES LESS THAN MAXVALUE
);
```

**Nə zaman:** Böyük cədvəl, zaman əsaslı sorğular

### 12.4 Arxivləmə

```
users cədvəli (300M) -->  users_active (50M)  +  users_archive (250M)
                          (Sürətli sorğular)     (Nadir sorğular, ayrı DB)
```

### 12.5 NewSQL / Distributed DB

```
CockroachDB, PlanetScale, Vitess, TiDB:
- Şəffaf sharding (application bilmir)
- ACID garantiyalar
- Horizontal scale
- SQL interface qalır
```

---

## 13. Real Nümunə: user_id-yə görə Shard Edilmiş User Data

### Ssenari
Sosial media platforması: 50 milyon aktiv istifadəçi, gündə 500M yazı əməliyyatı.

### DB Sxemi

*DB Sxemi üçün kod nümunəsi:*
```sql
-- Hər shardda eyni sxem
CREATE TABLE users (
    id         BIGINT       PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(255) NOT NULL,
    bio        TEXT,
    avatar_url VARCHAR(512),
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email    (email)
);

CREATE TABLE posts (
    id         BIGINT       PRIMARY KEY,
    user_id    BIGINT       NOT NULL,
    content    TEXT         NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at DESC)
    -- user_id ilə sharding: bir user-in postları həmişə eyni shardda
);

CREATE TABLE followers (
    follower_id  BIGINT NOT NULL,
    following_id BIGINT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id)
    -- PROBLEM: follower və following fərqli sharddlarda ola bilər!
);
```

### Shard Arxitekturası

```
                   App Servers
                       |
              +--------+--------+
              |                 |
         ShardRouter         ShardRouter
              |
    +---------+---------+---------+
    |         |         |         |
+---+---+ +---+---+ +---+---+ +---+---+
|Shard 0| |Shard 1| |Shard 2| |Shard 3|
|user_id| |user_id| |user_id| |user_id|
|%4 = 0 | |%4 = 1 | |%4 = 2 | |%4 = 3 |
+---+---+ +---+---+ +---+---+ +---+---+
    |         |         |         |
+---+---+ +---+---+ +---+---+ +---+---+
|Replica| |Replica| |Replica| |Replica|  <- Read replicas
+-------+ +-------+ +-------+ +-------+

Ayrıca:
+------------------+
|   Global DB      |
|  - usernames     |  <- Unique constraint üçün global lookup
|  - emails        |
|  - shard_map     |  <- user_id -> shard mapping
+------------------+
```

### Followers Problemi

```
user_id=100 -> Shard 0
user_id=201 -> Shard 1

// 201, 100-ü follow edir
// followers cədvəlinə yazmaq lazımdır
// Amma onlar fərqli sharddadır!

Həll 1: Hər iki istiqamətdə yaz
Shard 0: INSERT (follower=201, following=100) -- "Kim məni follow edir?"
Shard 1: INSERT (follower=201, following=100) -- "Mən kimi follow edirəm?"

Həll 2: Social graph ayrıca xidmət
Dedicated graph DB (Neo4j) ya da ayrıca service
```

### PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
<?php

class SocialShardedUserService
{
    private const TOTAL_SHARDS = 4;

    public function __construct(
        private ShardRouter        $router,
        private PDO                $globalDb,
        private Redis              $cache,
    ) {}

    public function registerUser(string $username, string $email): int
    {
        // 1. Global DB-də username/email unique-liyini yoxla
        $this->ensureUniqueGlobally($username, $email);

        // 2. Yeni user ID yarat (UUID v4 ya da distributed ID generator)
        $userId = $this->generateUserId();

        // 3. Shard müəyyən et
        $shardId = abs(crc32((string) $userId)) % self::TOTAL_SHARDS;
        $conn    = $this->router->getConnection($userId);

        // 4. Sharda yaz
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO users (id, username, email, created_at) VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$userId, $username, $email]);

            // 5. Global lookup-a yaz
            $this->globalDb->prepare(
                'INSERT INTO user_shard_map (user_id, shard_id, username, email) VALUES (?, ?, ?, ?)'
            )->execute([$userId, $shardId, $username, $email]);

            $conn->commit();
        } catch (\Exception $e) {
            $conn->rollBack();
            throw $e;
        }

        return $userId;
    }

    public function getUserById(int $userId): ?array
    {
        $cacheKey = "user:{$userId}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== false) {
            return json_decode($cached, true);
        }

        $conn = $this->router->getConnection($userId);
        $stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch() ?: null;

        if ($user) {
            $this->cache->setex($cacheKey, 300, json_encode($user));
        }

        return $user;
    }

    public function getUserByUsername(string $username): ?array
    {
        // Username-in shard-ını bilmirik, global lookup lazımdır
        $stmt = $this->globalDb->prepare(
            'SELECT user_id, shard_id FROM user_shard_map WHERE username = ?'
        );
        $stmt->execute([$username]);
        $mapping = $stmt->fetch();

        if (!$mapping) {
            return null;
        }

        return $this->getUserById($mapping['user_id']);
    }

    public function getUserPosts(int $userId, int $limit = 20, int $offset = 0): array
    {
        // Postlar da user_id ilə sharddır, eyni shardda
        $conn = $this->router->getConnection($userId);
        $stmt = $conn->prepare(
            'SELECT * FROM posts
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function getFeed(int $userId, array $followingIds): array
    {
        // Fan-out problem: following fərqli sharddlarda ola bilər
        $postsByShards = [];

        // Following-ləri shardlara görə qruplaşdır
        $shardGroups = [];
        foreach ($followingIds as $followingId) {
            $shardId                   = abs(crc32((string) $followingId)) % self::TOTAL_SHARDS;
            $shardGroups[$shardId][]   = $followingId;
        }

        // Hər shard üçün sorğu icra et
        foreach ($shardGroups as $shardId => $userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $conn         = $this->router->getConnectionByShardId($shardId);
            $stmt         = $conn->prepare(
                "SELECT p.*, u.username, u.avatar_url
                 FROM posts p
                 JOIN users u ON u.id = p.user_id
                 WHERE p.user_id IN ({$placeholders})
                 ORDER BY p.created_at DESC
                 LIMIT 50"
            );
            $stmt->execute($userIds);
            $postsByShards[] = $stmt->fetchAll();
        }

        // Bütün nəticələri birləşdir və sırala
        $allPosts = array_merge(...$postsByShards);
        usort($allPosts, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return array_slice($allPosts, 0, 20);
    }

    private function generateUserId(): int
    {
        // Snowflake ID alqoritmi (64-bit, zaman əsaslı, unikal)
        // timestamp(41bit) + datacenter(5bit) + worker(5bit) + sequence(12bit)
        return SnowflakeIdGenerator::generate();
    }

    private function ensureUniqueGlobally(string $username, string $email): void
    {
        $stmt = $this->globalDb->prepare(
            'SELECT COUNT(*) FROM user_shard_map WHERE username = ? OR email = ?'
        );
        $stmt->execute([$username, $email]);

        if ($stmt->fetchColumn() > 0) {
            throw new \DomainException('Username or email already taken');
        }
    }
}
```

---

## 14. İntervyu Sualları

### Əsas Suallar

**S1: Sharding nədir və nə zaman lazımdır?**

Sharding — verilənlər bazasını bir neçə müstəqil hissəyə bölmək metodudur. Lazımdır:
- Tək server write yükünü kaldıra bilmir
- Dataset tək serverin storage limitini keçir
- Vertical scaling artıq praktik (baha) deyil

Shardingdən əvvəl: indeks optimizasiyası, caching, read replica-lar, vertical scaling sınanmalıdır.

---

**S2: Hash-based vs Range-based sharding — nə zaman hansını seçmək lazımdır?**

- **Hash-based:** Bərabər paylama lazımdırsa, hotspot olmamalıdırsa, range sorğusu nadirsə
- **Range-based:** Range sorğuları çox işlənilirsə (`BETWEEN`, zaman əsaslı), sequential access patternisə, resharding planlanırsa

---

**S3: Shard key seçiminin kriteriyaları hansılardır?**

1. **Yüksək cardinality** — fərqli dəyər sayı shard sayından çox olmalı
2. **Bərabər paylama** — hotspot yaratmamalı
3. **Query uyğunluğu** — ən çox işlənən WHERE şərtinə uyğun olmalı
4. **Dəyişməzlik** — dəyişdirilə bilməməlidir (update = köçürmək lazımdır)
5. **Monoton artım yoxdur** — auto-increment + range = hotspot

---

**S4: Cross-shard transaction problemi necə həll edilir?**

- **Two-Phase Commit (2PC):** Güclü consistency, amma yavaş və blocking
- **Saga Pattern:** Compensating transactions, eventual consistency, mikroservislər üçün uyğun
- **Eventual Consistency:** Biraz gecikməli, amma çox performanslı (sosial media üçün OK)
- **Shard key-i seçərək cross-shard minimize et:** Bir user-in bütün məlumatı eyni shardda saxlanır

---

**S5: Hotspot nədir, necə aşkarlanır və necə həll edilir?**

Hotspot — bəzi shardların digərlərinə nisbətən qeyri-mütənasib yük alması.

Aşkarlama: Monitoring dashboardlarda CPU/QPS/latency per shard izlənilir.

Həll yolları:
- Hash-based sharding (range əvəzinə)
- Shard key-ə random suffix əlavə etmək (write hotspot)
- Məşhur entity-lər üçün ayrıca caching (read hotspot)
- Composite shard key

---

**S6: Resharding nədir və consistent hashing nə üçün lazımdır?**

Resharding — mövcud shardlar dolduqda yeni shard əlavə etmək prosesi.

Adi hash (`% N`) ilə shard sayı dəyişdikdə, demək olar ki, bütün məlumatı köçürmək lazımdır (`N`-dən `N+1`-ə keçdikdə ~N/(N+1) məlumat köçür).

Consistent hashing ilə virtual node ring istifadə edilir — yeni shard əlavə etdikdə yalnız ring-in bir hissəsindəki məlumat köçür (≈ 1/N).

---

**S7: Directory-based sharding-in üstünlükləri və çatışmazlıqları?**

Üstünlüklər:
- Ən çevik (hər entity ayrıca shard seçilə bilər)
- Resharding asandır (lookup cədvəlini yenilə)
- Xüsusi iş məntiqi əlavə edilə bilər

Çatışmazlıqlar:
- Lookup cədvəli single point of failure
- Əlavə gecikməli (hər sorğudan əvvəl lookup)
- Cache etmək mütləq lazımdır

---

**S8: Global cədvəllər nədir, necə idarə edilir?**

Global cədvəllər shard edilməyən reference data-dır (ölkələr, valyutalar, konfiqurasiyalar).

İdarəetmə yolları:
1. Hər shardda kopyasını saxla + dəyişiklikdə bütün sharddlara yaz
2. Ayrıca global DB, sorğularda join əvəzinə application-level birləşdir
3. Application memory-də ya da Redis-də cache et

---

**S9: Fan-out sorğusu nədir, performans problemi necə azaldılır?**

Fan-out — bir sorğunun bütün sharddlara yayılması.

Azaltma yolları:
- Shard key-ə uyğun sorğu yazmaq (fan-out-u sıfıra endirmək)
- Global secondary index (Elasticsearch, ayrıca xidmət)
- Denormalization (lazımlı məlumatı hər sharda kopyalamaq)
- Sorğunu zaman aralığı ilə məhdudlaşdırmaq
- Async fan-out (background job)

---

**S10: Sharding-in alternativlərini sıralayın.**

1. Query optimizasiyası (indekslər, EXPLAIN)
2. Application-level caching (Redis)
3. Read replica-lar (oxuma yükünü paylayır)
4. Vertical scaling (daha güclü server)
5. Table partitioning (eyni server, məntiqi bölünmə)
6. Arxivləmə (köhnə məlumatı ayrı DB-yə köç)
7. NewSQL (CockroachDB, Vitess — şəffaf sharding)
8. Dedicated OLAP (analitik sorğuları başqa yerə yönləndir)

---

**S11: Siz bir e-commerce platformasını dizayn edirsiniz. orders cədvəlini necə shard edərdiniz?**

Shard key seçimi: `user_id` (order_id deyil)

Niyə `user_id`:
- "Mənim sifarişlərim" sorğusu (ən çox işlənilən) bir sharda gedir
- Bir user-in sifarişləri həmişə eyni shardda
- Bərabər paylama (hash-based ilə)

Problemlər:
- "Ən çox satılan məhsullar" → fan-out (ya da ayrıca analytics DB)
- Məhsul inventarı → ayrıca shard edilmir, ya da `product_id` ilə başqa shard

---

**S12: PHP-də iki fərqli shardda olan istifadəçilər arasında pul köçürmə necə edilir?**

```
user_id=100 -> Shard 0
user_id=205 -> Shard 1
```

Saga Pattern ilə:
1. Shard 0-dan debit et, `transaction_log`-a yaz
2. Shard 1-ə credit et, `transaction_log`-a yaz
3. Hər ikisi uğurludursa, tamamlandı
4. Credit uğursuzsa, Shard 0-da compensating debit (geri yüklə)
5. Debit uğursuzsa, heç nə etmə

Idempotency üçün `saga_id` və ya `transaction_id` istifadə et.

---

## Əlavə Qeydlər

```
Sharding MÜTLƏQ lazımdırsa, başla:
1. Abstraksiya qatı (Repository pattern) — shard routing gizlədir
2. Test mühitini qurun (production-a bənzər 3+ shard)
3. Monitoring: per-shard metrics (QPS, latency, storage)
4. Migration planı: zero-downtime resharding proseduru
5. Runbook: "Shard X aşağı düşdü" ssenarisi üçün plan
```

---

*Son yeniləmə: 2026-04-09 | Senior PHP Developer Interview Hazırlığı*
