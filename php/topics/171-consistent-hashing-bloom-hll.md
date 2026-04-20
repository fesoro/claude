# Consistent Hashing, Bloom Filter, HyperLogLog

## Mündəricat
1. [Klassik distributed hashing problemi](#klassik-distributed-hashing-problemi)
2. [Consistent Hashing](#consistent-hashing)
3. [Virtual nodes (vnodes)](#virtual-nodes-vnodes)
4. [PHP implementasiyası](#php-implementasiyası-consistent-hash)
5. [Bloom Filter](#bloom-filter)
6. [HyperLogLog](#hyperloglog)
7. [Hansını nə vaxt istifadə etməli](#hansını-nə-vaxt-istifadə-etməli)
8. [Real-world use cases](#real-world-use-cases)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Klassik distributed hashing problemi

```
Cache server-ləri: S1, S2, S3 (3 server)
Key-ə əsasən server seç: hash(key) % N
  hash("user:1") % 3 = 2 → S2
  hash("user:2") % 3 = 0 → S0

Problem: N dəyişəndə (server əlavə/çıxma) HAMISI remap olur!
  4 server oldu → hash(key) % 4
  "user:1": 2 → 3 (başqa servər!)
  "user:2": 0 → 1
  
  Nəticə: cache miss storm — bütün data yenidən çəkilir.

Misal:
  100 server var, 1 server getdi → 99 server qaldı
  Naïve modulo: 99% key başqa serverə düşür!
  Consistent hash: ~1% key remap olur
```

---

## Consistent Hashing

```
İdeya: Hash space-i (0 ... 2^32-1) bir RİNG kimi düşün.
Həm key, həm server eyni ring üstündə yerləşdirilir.

Hash ring:
  
      0/2^32
        │
   S1 ●─┘
        │
   ● S2 │
        │
        │ K1 ● ← key
   S3 ● │
        │
   ● K2 │ ← başqa key
        │

Key-i hansı server-ə göndəririk?
  Ring üzərində saat əqrəbi istiqamətində ilk gələn server.
  
  K1 → S1 (ən yaxın next server)
  K2 → S3

Server əlavə olanda:
  S4 əlavə edildi: ring-də müəyyən nöqtədə.
  YALNIZ S4 ilə əvvəlki server arasındakı key-lər remap olur.
  Digər key-lər toxunulmazdır.

Server çıxanda:
  S2 getdi → S2-yə gedən key-lər indi S3-ə (növbəti server).
  Yalnız S2-nin bölümü təsirlənir, digərləri eynidir.
```

```
Müqayisə (100 server, 1 əlavə olur):
  
  Naïve modulo:       ~99% key remap
  Consistent hashing: ~1% key remap (1/N)

Problem: Uneven distribution
  Yalnız 3 server varsa, ring-də fiziki olaraq pis paylanmış ola bilər.
  S1 ring-in 50%-ni "tutur", S2 30%, S3 20%.
  Load balance pis.

Həlli: Virtual nodes (vnodes)
```

---

## Virtual nodes (vnodes)

```
Hər server ring-də BİR dəfə deyil, ÇOXLU dəfə yerləşdirilir.

S1_0, S1_1, ..., S1_150 — S1-in 150 vnode-u
S2_0, S2_1, ..., S2_150 — S2-in 150 vnode-u
S3_0, S3_1, ..., S3_150 — S3-in 150 vnode-u

Hər vnode fərqli hash-ə malikdir (hash(server + index)).
Nəticədə 450 nöqtə ring-də, uniform distribution.

Əlavə fayda:
  Zəif server-ə az vnode, güclü-yə çox vnode → heterogeneous cluster.
  
  S1 (güclü):  200 vnode
  S2 (orta):   100 vnode
  S3 (zəif):    50 vnode
```

---

## PHP implementasiyası (consistent hash)

```php
<?php
class ConsistentHash
{
    private array $ring = [];           // position => server
    private array $sortedKeys = [];
    private int $replicas;
    
    public function __construct(int $replicas = 150)
    {
        $this->replicas = $replicas;
    }
    
    public function addServer(string $server): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $hash = $this->hash($server . ':' . $i);
            $this->ring[$hash] = $server;
        }
        $this->sortRing();
    }
    
    public function removeServer(string $server): void
    {
        for ($i = 0; $i < $this->replicas; $i++) {
            $hash = $this->hash($server . ':' . $i);
            unset($this->ring[$hash]);
        }
        $this->sortRing();
    }
    
    public function getServer(string $key): string
    {
        if (empty($this->ring)) {
            throw new \RuntimeException('No servers');
        }
        
        $hash = $this->hash($key);
        
        // Binary search — ring-də ilk server hash >= key hash
        $pos = $this->binarySearch($hash);
        
        return $this->ring[$this->sortedKeys[$pos]];
    }
    
    private function hash(string $s): int
    {
        return crc32($s);  // sadə hash; production-da MurmurHash
    }
    
    private function sortRing(): void
    {
        ksort($this->ring);
        $this->sortedKeys = array_keys($this->ring);
    }
    
    private function binarySearch(int $hash): int
    {
        $low  = 0;
        $high = count($this->sortedKeys) - 1;
        
        // Wrap-around — ring-ın sonundan başına keçid
        if ($hash > $this->sortedKeys[$high]) {
            return 0;  // ilk serverə qayıt
        }
        
        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if ($this->sortedKeys[$mid] < $hash) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }
        return $low;
    }
}

// İstifadə
$ch = new ConsistentHash(replicas: 150);
$ch->addServer('redis-1:6379');
$ch->addServer('redis-2:6379');
$ch->addServer('redis-3:6379');

$server = $ch->getServer('user:42');
// → "redis-2:6379"

// Key hər zaman eyni server-ə gedir (server dəyişmədiyi müddətdə)
$ch->getServer('user:42');  // eyni nəticə

// Server əlavə et → cache-in ~1/4-i remap
$ch->addServer('redis-4:6379');
```

---

## Bloom Filter

```
Bloom filter — "bu element SET-də olmaya bilər / ola bilər" cavab verir.
  False positive: var deyir, amma əslində yoxdur (nadir hal)
  False negative: yoxdur deyir → MÜTLƏQ YOXDUR (heç vaxt yanlış deyil)

Necə işləyir?
  - Bit array (m bit, hamısı 0)
  - k fərqli hash function
  
  Element əlavə et:
    x-dən k hash: h1(x), h2(x), ..., hk(x)
    Hər hash → array-də bir index
    Həmin bit-lərin hamısını 1-ə qur.
  
  Element var?
    k hash al → k bit yoxla
    Əgər hamısı 1 → "ola bilər var"
    Əgər biri 0 → "MÜTLƏQ yoxdur"
```

```
Misal:
  m = 10 bit, k = 3 hash function
  
  Əlavə et "apple":
    h1("apple") = 1
    h2("apple") = 4
    h3("apple") = 7
    Bit-lər: [0,1,0,0,1,0,0,1,0,0]
  
  Əlavə et "banana":
    h1("banana") = 2
    h2("banana") = 5
    h3("banana") = 7   (collision - 7 artıq 1 idi)
    Bit-lər: [0,1,1,0,1,1,0,1,0,0]
  
  Soruş "apple"? 1,4,7 → hamısı 1 → "ola bilər var" ✓
  Soruş "orange"? h1=1, h2=4, h3=5 → hamısı 1 → FALSE POSITIVE!
  Soruş "grape"? h1=3 → 0 → "YOXDUR" ✓
```

```php
<?php
// Sadə Bloom filter
class BloomFilter
{
    private string $bits;
    private int $size;
    private int $hashCount;
    
    public function __construct(int $size = 10000, int $hashCount = 7)
    {
        $this->size = $size;
        $this->hashCount = $hashCount;
        $this->bits = str_repeat("\0", (int) ceil($size / 8));
    }
    
    public function add(string $item): void
    {
        foreach ($this->getHashes($item) as $pos) {
            $this->setBit($pos);
        }
    }
    
    public function mayContain(string $item): bool
    {
        foreach ($this->getHashes($item) as $pos) {
            if (!$this->getBit($pos)) {
                return false;   // Mütləq yoxdur!
            }
        }
        return true;  // Ola bilər var (false positive ola bilər)
    }
    
    private function getHashes(string $item): array
    {
        // Double hashing — iki hash, k dəyər türət
        $h1 = crc32($item);
        $h2 = crc32(strrev($item));
        $result = [];
        for ($i = 0; $i < $this->hashCount; $i++) {
            $result[] = ($h1 + $i * $h2) % $this->size;
        }
        return $result;
    }
    
    private function setBit(int $pos): void
    {
        $byte = (int) ($pos / 8);
        $bit = $pos % 8;
        $this->bits[$byte] = chr(ord($this->bits[$byte]) | (1 << $bit));
    }
    
    private function getBit(int $pos): bool
    {
        $byte = (int) ($pos / 8);
        $bit = $pos % 8;
        return (ord($this->bits[$byte]) & (1 << $bit)) !== 0;
    }
}

$bf = new BloomFilter(size: 10000, hashCount: 7);
$bf->add("user:1");
$bf->add("user:2");

$bf->mayContain("user:1");    // true
$bf->mayContain("user:999");  // false (adətən)

// Redis BF.ADD, BF.EXISTS — native Bloom filter!
```

```
Ölçü hesablama:
  n = 1M element
  p = 1% false positive rate
  
  m = -n * ln(p) / (ln(2)^2) = ~9.6 bit/element
  k = (m/n) * ln(2) = ~7 hash function

  1M element × 9.6 bit = 1.2 MB (yəni PostgreSQL index-dən 100× kiçik!)
```

---

## HyperLogLog

```
HyperLogLog (HLL) — unique count-u (cardinality) TAHMINI edir.
Probabilistic — 1% error, amma YADDAS MƏCBURI AZ (12 KB).

Problem:
  "Saytı bu gün neçə unik istifadəçi ziyarət edib?"
  Naïve: SET-də hər IP saxla → 1M IP = 40 MB
  HLL:   12 KB (həmişə!), ~1% error

Necə işləyir?
  - Hər elementi hash et
  - Hash-in əvvəlindəki "leading zero"-ları say
  - Ən uzun leading-zero → cardinality proxy
  - Bias correction + harmonic mean

Matematik intuisiya:
  2^n element-dən ən az biri n leading zero-ya malikdir (təxminən).
  Yəni "max leading zero = n" → ~2^n unique element.

HLL istifadə:
  redis:  PFADD, PFCOUNT, PFMERGE
  postgresql: hll extension
  bigquery: HLL_COUNT.MERGE
```

```php
<?php
// Redis HLL
$redis = new \Redis();
$redis->connect('localhost');

$redis->pfadd('unique_visitors:today', 'user-1');
$redis->pfadd('unique_visitors:today', 'user-2');
$redis->pfadd('unique_visitors:today', 'user-1');  // duplicate, count artmır

$count = $redis->pfcount('unique_visitors:today');  // 2 (yaxın dəqiq)

// MERGE — iki HLL birləşdir
$redis->pfmerge('unique_visitors:week', 
    'unique_visitors:monday',
    'unique_visitors:tuesday'
);

$weekCount = $redis->pfcount('unique_visitors:week');

// Strəgət: 10M unique user ÜÇÜN 12 KB yaddaş!
```

---

## Hansını nə vaxt istifadə etməli

```
Consistent Hashing:
  ✓ Cache cluster (Redis, Memcached)
  ✓ Load balancer (sticky session)
  ✓ Sharded DB (user_id → shard)
  ✓ CDN node selection

Bloom Filter:
  ✓ "Bu username alınıb?" — false positive OK, false negative YOX
  ✓ "Bu URL-i artıq crawl etmişik?" (web crawler)
  ✓ Cache — "DB-də var?" yoxlaması (cache bypass)
  ✓ Spam filter — "bu email adresi blacklist-də?"

HyperLogLog:
  ✓ Unique visitor count (tam dəqiqlik lazım deyil)
  ✓ Unique words in large corpus
  ✓ DISTINCT-in approximation (analytics)
  ✓ Deduplication statistics

NƏ VAXT istifadə ETMƏ:
  Consistent hash: 2-3 node var → over-engineering
  Bloom filter:    false positive qəbul olunmur
  HLL:            tam dəqiq count tələb olunur (maliyə, billing)
```

---

## Real-world use cases

```
Kompaniya          | Texnika           | İstifadə
────────────────────────────────────────────────────────
DynamoDB (AWS)     | Consistent hash   | Data sharding
Cassandra          | Consistent hash   | Data partitioning
Redis Cluster      | Hash slot (16384) | Shard assignment (16K slot)
Memcached          | Consistent hash   | Key → node
Nginx upstream     | Consistent hash   | Sticky session

Chrome             | Bloom filter      | "Malicious URL check"
Medium             | Bloom filter      | "İstifadəçi bu məqaləni oxuyubmu?"
Bitcoin SPV        | Bloom filter      | Wallet "bu tx məni maraqlandırır?"
PostgreSQL         | Bloom index       | Large table query filter

Reddit             | HLL               | Unique view count
Google Analytics   | HLL               | Unique visitors
Snowflake DW       | HLL               | APPROX_COUNT_DISTINCT
BigQuery           | HLL               | HLL_COUNT family
```

---

## İntervyu Sualları

- Naïve `hash(key) % N` niyə distributed cache-də problemlidir?
- Consistent hashing ring-ində virtual node-lar nəyə xidmət edir?
- 100 server var, 1-i getdi — neçə % key remap olur? (cons. hash vs modulo)
- Bloom filter false positive və false negative fərqi nədir?
- Bloom filter nə vaxt false positive verir?
- Bloom filter-in m (bit) və k (hash) necə seçilir?
- 1M element üçün 1% false positive — nə qədər yaddaş lazımdır?
- HyperLogLog necə ~12 KB-da 10M unique count saxlayır?
- `COUNT(DISTINCT)` vs HLL — nə vaxt hansı?
- Redis PFCOUNT, PFMERGE hansı işləri görür?
- Sharded database-də consistent hashing rolu?
- Browser Safe Browsing Bloom filter niyə istifadə edir?
