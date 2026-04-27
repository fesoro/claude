# Data Partitioning (Senior)

## İcmal

Data partitioning (sharding) - böyük verilənlər bazasını kiçik, daha asan idarə edilən hissələrə (partition/shard) bölmək prosesidir. Hər partition ayrı serverdə saxlanıla və emal edilə bilər. Bu, sistemin horizontal scalability qazanmasını təmin edir.

Partitioning əsas məqsədləri:
- **Scalability** - Bir serverdə yerləşməyən data üçün
- **Performance** - Sorğular kiçik dataset üzərində işləyir
- **Availability** - Bir shard fail olsa belə digərləri işləyir
- **Geographic distribution** - Data istifadəçiyə yaxın saxlanılır


## Niyə Vacibdir

Tək verilənlər bazası böyük məlumat həcmini saxlaya bilmir. Sharding olmadan horizontal scale mümkün deyil; consistent hashing yeni shard əlavəsini minimal rebalancing ilə həll edir. Laravel, Eloquent multi-DB, PlanetScale — real layihələrdə şardlama qərarları buradan çıxır.

## Əsas Anlayışlar

### 1. Horizontal vs Vertical Partitioning

**Vertical Partitioning (Columns):**
Cədvəli sütunlara görə bölmək. Məsələn, `users` cədvəlini `users_profile` (ad, email) və `users_settings` (preferences, theme) olaraq iki cədvələ bölmək. Normalization-a bənzəyir.

Üstünlükləri:
- Tez-tez istifadə olunan sütunlar ayrılır
- Row size kiçilir, cache daha effektiv işləyir
- Həssas data (password) ayrıca saxlanıla bilər

**Horizontal Partitioning (Rows):**
Cədvəli sətirlərə görə bölmək. Məsələn, `orders` cədvəlində 2024-ci il orderləri bir shardda, 2025 ayrı shardda.

Üstünlükləri:
- Table size hər shard üçün kiçilir
- Paralel emal mümkündür
- Index-lər daha kiçik və sürətli

### 2. Partition Strategies

**Range-Based Partitioning:**
Key aralığına görə partition. Məsələn, A-M istifadəçiləri shard1-də, N-Z shard2-də.

Üstünlüklər: Range query-lər sürətli
Çatışmazlıq: Hot spots (bəzi range-lər daha çox trafik alır)

**Hash-Based Partitioning:**
Key-i hash edib modulo ilə shard təyin etmək: `shard = hash(key) % N`

Üstünlüklər: Uniform distribution
Çatışmazlıq: Range query-lər çətin, re-sharding ağrılı

**List-Based Partitioning:**
Spesifik dəyərlər siyahısına görə. Məsələn, ölkə kodu:
- USA, CA → shard1
- UK, DE → shard2
- AZ, TR → shard3

Üstünlük: Business logic-ə uyğun
Çatışmazlıq: Balansı əl ilə idarə etmək lazım

**Composite Partitioning:**
Bir neçə strategiyanın birləşməsi. Məsələn, əvvəlcə region-a görə list, sonra date-ə görə range.

### 3. Consistent Hashing

Klassik hash % N problem var: node əlavə edəndə ya silinəndə, demək olar ki, bütün key-lər yenidən map olunur.

**Consistent Hashing Solution:**
- Hash space-i dairə (ring) kimi düşün (0 - 2^32)
- Hər node ring üzərində bir nöqtəyə hash olunur
- Key-lər də ring üzərinə hash olunur
- Key, clockwise gedərkən ilk rastlaşdığı node-a təyin olunur

Node əlavə/silinəndə yalnız K/N key köçürülür (K - total keys, N - nodes).

**Virtual Nodes (vnodes):**
Bir fiziki node ring üzərində bir neçə nöqtədə (vnodes) təmsil olunur. Bu, distribution-u daha uniform edir və node əlavə/silmə zamanı load yenidən paylanır.

### 4. Rebalancing

Node əlavə və ya silinəndə data-nı yenidən paylamaq:

**Fixed number of partitions:**
- Əvvəlcədən çox partition yarat (1000+)
- Node-a birdən çox partition təyin et
- Node əlavə olanda partition-ları köçür

**Dynamic partitioning:**
- Partition böyüyəndə avtomatik split olur
- HBase, MongoDB bu yanaşmanı istifadə edir

**Partitioning proportional to nodes:**
- Node sayına görə partition sayı tənzimlənir
- Cassandra bu üsuldur

### 5. Hot Spots

Hot spot - bəzi partition-ların digərlərindən daha çox yük aldığı vəziyyət. Səbəbləri:
- **Celebrity problem** - populyar istifadəçi (Elon Musk-ın tvitləri)
- **Sequential keys** - auto-increment ID hər dəfə son shard-a gedir
- **Time-based keys** - cari vaxt yazıları bir shard-da toplanır

Həllər:
- **Random prefix** - key-ə random prefix əlavə et
- **Reverse timestamp** - timestamp-ı tərs çevirir
- **Composite keys** - user_id + timestamp kimi

### 6. Cross-Partition Queries

Bir neçə shard-dan data almaq çətindir:
- **Scatter-gather** - bütün shard-lara sorğu göndər, nəticələri birləşdir
- **Denormalization** - data-nı dublikat saxla
- **Secondary indexes** - global index (Cassandra material view)

## Arxitektura

```
                    ┌──────────────┐
                    │  Application  │
                    └──────┬───────┘
                           ↓
                    ┌──────────────┐
                    │Shard Router   │
                    │(Consistent    │
                    │ Hashing Ring) │
                    └──┬───┬───┬───┘
                       ↓   ↓   ↓
                    Shard1 Shard2 Shard3
                     DB1    DB2    DB3
```

Consistent Hashing Ring:
```
          Node A (hash=10)
              ●
         ●           ●
    key3            Node B (hash=90)
     (hash=80)
         ●           ●
      key1
     (hash=50)     Node C (hash=200)
              ●
          key2 (hash=150)
```

## Nümunələr

### Consistent Hashing Implementation

```php
<?php
namespace App\Services;

class ConsistentHash
{
    private array $ring = [];
    private array $sortedKeys = [];
    private int $replicas;

    public function __construct(int $replicas = 150)
    {
        $this->replicas = $replicas;
    }

    public function addNode(string $node): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $hash = $this->hash($node . ':' . $i);
            $this->ring[$hash] = $node;
        }
        $this->sortKeys();
    }

    public function removeNode(string $node): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $hash = $this->hash($node . ':' . $i);
            unset($this->ring[$hash]);
        }
        $this->sortKeys();
    }

    public function getNode(string $key): ?string
    {
        if (empty($this->ring)) {
            return null;
        }

        $hash = $this->hash($key);

        foreach ($this->sortedKeys as $nodeHash) {
            if ($hash <= $nodeHash) {
                return $this->ring[$nodeHash];
            }
        }

        // Ring-in sonuna gəlindi, ilk node-a qayıt
        return $this->ring[$this->sortedKeys[0]];
    }

    private function hash(string $key): int
    {
        return crc32($key);
    }

    private function sortKeys(): void
    {
        $this->sortedKeys = array_keys($this->ring);
        sort($this->sortedKeys);
    }
}
```

### Shard Router for Multiple Databases

```php
<?php
namespace App\Services;

use App\Services\ConsistentHash;
use Illuminate\Support\Facades\DB;

class ShardRouter
{
    private ConsistentHash $hash;
    private array $connections = [
        'shard1' => 'mysql_shard1',
        'shard2' => 'mysql_shard2',
        'shard3' => 'mysql_shard3',
    ];

    public function __construct()
    {
        $this->hash = new ConsistentHash(150);
        foreach (array_keys($this->connections) as $shard) {
            $this->hash->addNode($shard);
        }
    }

    public function getConnection(int|string $userId): string
    {
        $shardName = $this->hash->getNode((string) $userId);
        return $this->connections[$shardName];
    }

    public function query(int $userId, callable $callback)
    {
        $connection = $this->getConnection($userId);
        return $callback(DB::connection($connection));
    }

    public function queryAllShards(callable $callback): array
    {
        $results = [];
        foreach ($this->connections as $shardName => $connection) {
            $results[$shardName] = $callback(DB::connection($connection));
        }
        return $results;
    }
}
```

### Sharded User Model

```php
<?php
namespace App\Models;

use App\Services\ShardRouter;
use Illuminate\Database\Eloquent\Model;

class ShardedUser extends Model
{
    protected $table = 'users';
    protected $fillable = ['id', 'email', 'name'];

    public static function findById(int $id): ?self
    {
        $router = app(ShardRouter::class);
        $connection = $router->getConnection($id);

        return (new self)
            ->setConnection($connection)
            ->where('id', $id)
            ->first();
    }

    public static function createUser(array $data): self
    {
        $router = app(ShardRouter::class);
        $data['id'] = self::generateId();
        $connection = $router->getConnection($data['id']);

        $user = new self($data);
        $user->setConnection($connection);
        $user->save();

        return $user;
    }

    private static function generateId(): int
    {
        // Snowflake-style ID generation
        return (int) (microtime(true) * 1000) . random_int(100, 999);
    }

    public function save(array $options = [])
    {
        $router = app(ShardRouter::class);
        $this->setConnection($router->getConnection($this->id));
        return parent::save($options);
    }
}
```

### Scatter-Gather Query

```php
<?php
namespace App\Services;

class ScatterGatherService
{
    public function __construct(private ShardRouter $router) {}

    public function searchUsersByEmail(string $email): array
    {
        $results = $this->router->queryAllShards(function ($db) use ($email) {
            return $db->table('users')
                ->where('email', 'like', "%{$email}%")
                ->get()
                ->toArray();
        });

        // Nəticələri birləşdir
        $merged = [];
        foreach ($results as $shardResults) {
            $merged = array_merge($merged, $shardResults);
        }

        return $merged;
    }

    public function countTotalUsers(): int
    {
        $counts = $this->router->queryAllShards(function ($db) {
            return $db->table('users')->count();
        });

        return array_sum($counts);
    }
}
```

### Partition Configuration (config/database.php)

```php
'connections' => [
    'mysql_shard1' => [
        'driver' => 'mysql',
        'host' => env('DB_SHARD1_HOST', '127.0.0.1'),
        'port' => env('DB_SHARD1_PORT', '3306'),
        'database' => env('DB_SHARD1_DATABASE', 'app_shard1'),
        'username' => env('DB_SHARD1_USERNAME', 'root'),
        'password' => env('DB_SHARD1_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
    'mysql_shard2' => [
        'driver' => 'mysql',
        'host' => env('DB_SHARD2_HOST', '127.0.0.1'),
        'port' => env('DB_SHARD2_PORT', '3307'),
        'database' => env('DB_SHARD2_DATABASE', 'app_shard2'),
        'username' => env('DB_SHARD2_USERNAME', 'root'),
        'password' => env('DB_SHARD2_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
    // ...
],
```

## Real-World Nümunələr

- **Instagram** - User ID-ləri 4096 logical shard üzərində bölüb, hər DB server çox logical shard saxlayır
- **Twitter** - Tweet-lər Snowflake ID-lərlə bölünür, timestamp prefix ilə
- **Pinterest** - MySQL sharding, user ID hash əsaslı
- **Discord** - Guild ID əsasında Cassandra sharding
- **Uber** - Geographic partitioning (city-based shards)
- **MongoDB** - Native sharding, range, hashed, zone-based
- **Cassandra** - Consistent hashing ring, virtual nodes
- **DynamoDB** - Partition key + sort key model

## Praktik Tapşırıqlar

**Q1: Sharding və replication fərqi nədir?**
Sharding - data-nı bölmək (hər shard-da fərqli data). Replication - data-nı çoxaltmaq (bütün replica-larda eyni data). Sharding scalability üçün, replication availability və read scalability üçündür. Adətən ikisi birlikdə istifadə olunur - hər shard-ın öz replica set-i olur.

**Q2: Niyə consistent hashing istifadə edirik, sadə modulo kifayət etmir?**
Sadə modulo (`hash(key) % N`) ilə N dəyişəndə, təxminən bütün key-lər yenidən map olunur. Consistent hashing ilə yalnız K/N key köçürülür. 1 milyon key və 10 node olduqda, modulo 900K key köçürəcək, consistent hashing isə yalnız 100K. Bu re-sharding-i praktik edir.

**Q3: Hot spot problemini necə həll edərsən?**
Bir neçə üsul var:
- **Celebrity** üçün - populyar user-ə birdən çox shard təyin et
- **Sequential keys** üçün - random prefix, hash əlavə et
- **Time series** üçün - timestamp-ı tərs çevir
- **Read-heavy hot spots** üçün - cache layer əlavə et
- **Write-heavy** - write-i bir neçə key-ə böl

**Q4: Shard key necə seçirsən?**
Yaxşı shard key:
- **High cardinality** - unique dəyərlərin sayı çox olmalıdır
- **Even distribution** - uniform paylanmalıdır
- **Query pattern-ə uyğun** - ən tez-tez istifadə olunan filter
- **Immutable** - dəyişməməlidir (başqa shard-a köçürmə lazım olmasın)

Məsələn, e-commerce üçün user_id yaxşı shard key ola bilər, amma product_category hot spot yaradar.

**Q5: Cross-shard join necə edilir?**
Bir neçə strategiya:
1. **Denormalization** - data-nı dublikat saxla, join etmə
2. **Application-level join** - bir neçə shard-dan götür, kodda birləşdir
3. **Materialized views** - pre-computed join
4. **Distributed query engines** - Presto, Spark SQL
5. **Colocation** - əlaqəli data-nı eyni shard-a yerləşdir (məsələn, user-ın orderləri)

**Q6: Rebalancing zamanı nələr baş verir?**
Node əlavə edildikdə:
1. Yeni node ring-ə əlavə olunur
2. Müəyyən key range-lər yeni node-a təyin olunur
3. Data köçürülmə prosesi başlayır (migration)
4. Migration zamanı oxumalar həm köhnə, həm yeni node-dan edilir
5. Migration bitdikdən sonra köhnə node-dan data silinir

Bu zaman traffic-ə təsir etməmək üçün throttling və background migration istifadə olunur.

**Q7: Vertical və horizontal sharding fərqi?**
Vertical - cədvəli sütunlara görə bölmək (eyni bir neçə sütun fərqli cədvəldə). Horizontal - sətirlərə görə bölmək (fərqli sətirlər fərqli shard-da). Vertical adətən application design zamanı edilir, horizontal isə database böyüdükdə.

**Q8: Secondary index shard-da necə işləyir?**
Secondary index - primary shard key olmayan field üzərində index. İki yanaşma:
- **Local index** - hər shard öz indexini saxlayır. Sorğu bütün shard-lara göndərilir (scatter-gather).
- **Global index** - ayrıca shard-larda saxlanan global index. Tez, amma consistency problemləri var.
Cassandra hər ikisini dəstəkləyir.

**Q9: Sharding nə vaxt lazım olur?**
Sharding etmədən əvvəl bu seçimləri yoxla:
- Read replicas (read scale)
- Caching (Redis, Memcached)
- Vertical scaling (daha güclü server)
- Query optimization, indexing
- Archive old data

Sharding yalnız: single DB 100M+ row, write throughput bottleneck, storage limit, regional distribution zəruridir.

**Q10: MongoDB sharding necə işləyir?**
MongoDB native sharding arxitekturası:
- **mongos** - query router
- **config servers** - metadata (3 node replica set)
- **shards** - actual data (hər biri replica set)
- **Chunks** - data 64MB chunk-lara bölünür
- **Balancer** - chunk-ları avtomatik balansa salır
Shard key range, hashed və ya zone-based ola bilər.

## Praktik Baxış

1. **Start without sharding** - Sharding kompleksity artırır, lazım olana qədər etmə
2. **Plan shard key carefully** - Shard key sonradan dəyişdirmək çətindir
3. **Over-provision partitions** - Az node, çox logical partition (1000+)
4. **Monitor hot spots** - Per-shard metrics izlə (QPS, latency, size)
5. **Use consistent hashing with vnodes** - Daha yaxşı distribution
6. **Avoid cross-shard transactions** - Distributed transactions yavaş və mürəkkəbdir
7. **Colocate related data** - User və onun orderlərini eyni shard-da saxla
8. **Plan for rebalancing** - Online migration, throttling
9. **Backup per shard** - Hər shard üçün ayrı backup strategy
10. **Test with production-like data** - Kiçik dataset-də sharding effektivliyi görsənmir


## Əlaqəli Mövzular

- [Database Design](09-database-design.md) — partisiya məntiqi əsası
- [Database Replication](43-database-replication.md) — shard-daxili replica
- [Distributed Systems](25-distributed-systems.md) — partition fundamentalları
- [KV Store](50-key-value-store-design.md) — consistent hashing praktikası
- [Distributed ID](68-distributed-id-generation.md) — shard-dostu ID strategiyası
