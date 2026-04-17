# Probabilistic Data Structures

## Nədir? (What is it?)

**Probabilistic data structures** — tam dəqiqlikdən imtina edərək çox az yaddaşla və sürətlə böyük data setləri üzərində sualları cavablandıran strukturlarıdır. False positive və ya kiçik səhv payı (~1-5%) olsa da, yaddaş qazancı böyük data-da çox vacibdir.

İstifadə halları: spam filtering, duplicate detection, cardinality estimation, caching layer.

## Əsas Konseptlər (Key Concepts)

### 1. Bloom Filter

Element setdədir? sualını cavablandırır.
- **False positive**: ola bilər (element setdə yoxdur, amma "var" deyir)
- **False negative**: MÜMKÜN DEYİL ("yoxdur" deyirsə, həqiqətən yoxdur)

**Necə işləyir:**
```
Bit array (m bit): [0,0,0,0,0,0,0,0,0,0]
k hash function-ları

Add("apple"):
  h1(apple) = 2
  h2(apple) = 5  
  h3(apple) = 7
  Set bits: [0,0,1,0,0,1,0,1,0,0]

Contains("apple")?
  h1,h2,h3 bit-lərini yoxla → hamısı 1 → "VAR" (yəqin)

Contains("mango")?  
  h1(mango) = 3 → 0 → "YOX" (dəqiq)
```

**Ölçü hesabı:**
```
n = element sayı
p = arzuolunan false positive nisbəti (0.01 = 1%)
m = -n * ln(p) / (ln(2))^2  // bit sayı
k = (m/n) * ln(2)             // hash function sayı

Nümunə: 1M element, 1% FP → m = 9.6 Mbit (~1.2 MB), k = 7
```

### 2. Count-Min Sketch

Element-in neçə dəfə görüldüyünü təxmin edir (frequency estimation).
- **Overestimate**: ola bilər (həqiqi count-dan böyük qaytarır)
- **Underestimate**: MÜMKÜN DEYİL

**Struktur:**
```
2D array: d rows × w columns

increment("apple"):
  for i in 1..d:
    matrix[i][hash_i("apple") % w] += 1

count("apple"):
  return MIN(matrix[i][hash_i("apple") % w])  // ən aşağı qiymət
```

İstifadə: trending topics, DoS detection, heavy hitters.

### 3. HyperLogLog

Unikal element sayını (cardinality) çox az yaddaşla təxmin edir.
- **~12 KB ilə 1B unikal element** sayır
- **Səhv payı**: ~2%

**Əsas ideya:** Hash-in başında neçə sıfır var - maksimum sıfır uzunluğu → log(unikal sayı).

```
hash("user1") = 00101101...  → 2 leading zeros
hash("user2") = 11010010...  → 0 leading zeros  
hash("user3") = 00001110...  → 4 leading zeros

Max = 4 → ~2^4 = 16 unikal elementə işarə
```

### 4. Skip List

Probabilistic sorted list-dir. Balanced BST-nin probabilistic alternativi.
- **O(log n)** search, insert, delete — ortalama
- **Sadə implement** — balanced tree rotation-sız

Redis Sorted Set-lərdə istifadə olunur.

### 5. Quotient Filter

Bloom filter alternativi. Üstünlük: silmə dəstəyi var, daha cache-friendly.

## Praktiki Nümunələr (Practical Examples)

### Bloom Filter — PHP İmplementasiya

```php
<?php

class BloomFilter
{
    private array $bitArray;
    private int $size;
    private int $hashCount;
    
    public function __construct(int $expectedItems, float $falsePositiveRate = 0.01)
    {
        $this->size = (int) ceil(-($expectedItems * log($falsePositiveRate)) / (log(2) ** 2));
        $this->hashCount = (int) ceil(($this->size / $expectedItems) * log(2));
        $this->bitArray = array_fill(0, $this->size, 0);
    }
    
    private function hashes(string $item): array
    {
        $h1 = crc32($item);
        $h2 = hexdec(substr(md5($item), 0, 8));
        
        $hashes = [];
        for ($i = 0; $i < $this->hashCount; $i++) {
            $hashes[] = ($h1 + $i * $h2) % $this->size;
        }
        return $hashes;
    }
    
    public function add(string $item): void
    {
        foreach ($this->hashes($item) as $index) {
            $this->bitArray[$index] = 1;
        }
    }
    
    public function contains(string $item): bool
    {
        foreach ($this->hashes($item) as $index) {
            if ($this->bitArray[$index] === 0) {
                return false; // dəqiq yoxdur
            }
        }
        return true; // yəqin var
    }
}

// İstifadə
$bloom = new BloomFilter(1_000_000, 0.01); // 1M item, 1% FP
$bloom->add("user@example.com");
$bloom->add("admin@example.com");

var_dump($bloom->contains("user@example.com"));  // true
var_dump($bloom->contains("unknown@test.com"));  // false (ya false positive)
```

### HyperLogLog — Redis ilə

```php
// Redis HyperLogLog
class UniqueVisitorCounter
{
    public function trackVisit(int $pageId, string $userId): void
    {
        Redis::pfadd("page:{$pageId}:visitors", $userId);
    }
    
    public function uniqueVisitors(int $pageId): int
    {
        return Redis::pfcount("page:{$pageId}:visitors");
    }
    
    public function mergeAndCount(array $pageIds): int
    {
        $keys = array_map(fn($id) => "page:{$id}:visitors", $pageIds);
        return Redis::pfcount(...$keys);
    }
}

// Milyard ziyarətçi izlə, yalnız ~12 KB Redis istifadə olunur
```

### Count-Min Sketch — Trending Topics

```php
class CountMinSketch
{
    private array $matrix;
    private int $width;
    private int $depth;
    
    public function __construct(int $width = 1024, int $depth = 5)
    {
        $this->width = $width;
        $this->depth = $depth;
        $this->matrix = array_fill(0, $depth, array_fill(0, $width, 0));
    }
    
    private function hash(string $item, int $i): int
    {
        return hexdec(substr(hash('sha256', $item . $i), 0, 8)) % $this->width;
    }
    
    public function increment(string $item): void
    {
        for ($i = 0; $i < $this->depth; $i++) {
            $this->matrix[$i][$this->hash($item, $i)]++;
        }
    }
    
    public function estimate(string $item): int
    {
        $min = PHP_INT_MAX;
        for ($i = 0; $i < $this->depth; $i++) {
            $min = min($min, $this->matrix[$i][$this->hash($item, $i)]);
        }
        return $min;
    }
}

$sketch = new CountMinSketch();
foreach ($tweets as $tweet) {
    foreach (extractHashtags($tweet) as $tag) {
        $sketch->increment($tag);
    }
}

echo $sketch->estimate("#covid");     // ~1_234_567
echo $sketch->estimate("#ai");         // ~987_654
```

## PHP/Laravel ilə Tətbiq

### Laravel Cache-də Bloom Filter

Cache miss-ləri azaltmaq üçün — DB-ə getməzdən əvvəl yoxla:

```php
class CachedUserService
{
    public function exists(string $email): bool
    {
        // 1. Bloom filter yoxla (sürətli, az yaddaş)
        if (!$this->emailBloomFilter->contains($email)) {
            return false; // Dəqiq yoxdur — DB-ə getmə
        }
        
        // 2. Redis cache yoxla
        if (Cache::has("user:{$email}")) {
            return true;
        }
        
        // 3. DB yoxla (ehtiyat halda false positive üçün)
        return User::where('email', $email)->exists();
    }
}
```

### Redis PFCOUNT — Unique IP Sayı

```php
// Middleware
public function handle(Request $request, Closure $next)
{
    $date = now()->format('Y-m-d');
    Redis::pfadd("ip:{$date}", $request->ip());
    return $next($request);
}

// Hər günün unikal IP sayı
foreach (daysInMonth() as $date) {
    echo "{$date}: " . Redis::pfcount("ip:{$date}") . " unique IPs\n";
}
```

### Spam URL Filtering

```php
class SpamUrlFilter
{
    private BloomFilter $filter;
    
    public function __construct()
    {
        $this->filter = Cache::rememberForever('spam_bloom_filter', function() {
            $filter = new BloomFilter(10_000_000, 0.001);
            foreach (SpamUrl::cursor() as $url) {
                $filter->add($url->domain);
            }
            return $filter;
        });
    }
    
    public function isPotentialSpam(string $url): bool
    {
        $domain = parse_url($url, PHP_URL_HOST);
        return $this->filter->contains($domain);
    }
}
```

## Real-World Nümunələr

- **Google Chrome**: Bloom filter — malicious URL database (offline)
- **Cassandra**: SSTable-larda Bloom filter (disk I/O azaltmaq)
- **Redis**: HyperLogLog (`PFADD`, `PFCOUNT`), Sorted Set (skip list)
- **Bitcoin**: Bloom filter — lightweight client transaction filtering
- **Akamai**: Count-min sketch — top content traffic analysis

## Interview Sualları

**1. Bloom filter false positive niyə var, false negative yoxdur?**
Bit set edildikdən sonra clear edilmir (standard Bloom). Element varsa, onun bitləri mütləq 1-dir. Amma başqa element-lərin bitləri kəsişib 1 olub → yalnış "var" cavabı.

**2. HyperLogLog niyə yalnız 12 KB istifadə edir?**
Hər hash-in leading zero sayı saxlanılır (log_2(log_2(n))). 12 KB ilə 2^64 element sayıla bilər, 2% səhv payı ilə.

**3. Count-min sketch nə vaxt istifadə olunur?**
Heavy hitters detection (ən populyar müştərilər, ən trend tag-lər), DDoS detection (bir IP çoxmu sorğu atır), real-time analytics.

**4. Bloom filter-də element silinir?**
Standard Bloom yox. Counting Bloom (hər bit əvəzinə counter) var. Yaxud Quotient filter istifadə olunur.

**5. Redis `PFADD` internally necə işləyir?**
HyperLogLog strukturunu istifadə edir. 12 KB-lıq sparse struct saxlanılır. Sparse → dense automatic keçid var (başlanğıcda kompakt).

**6. Bloom filter size necə hesablanır?**
`m = -n * ln(p) / ln(2)^2` — n elementləri, p false positive rate. Məs: 1M element, 1% FP → 9.6M bit (~1.2 MB).

**7. Skip list vs Balanced BST?**
- Skip list: sadə implement, probabilistic O(log n)
- BST (red-black): garanteed O(log n), amma rotation mürəkkəb

Redis sorted set skip list istifadə edir.

**8. Distributed Bloom filter?**
Bit array-i nodes arası partition edirsən. Hər node öz bit-lərinə məsuldur. Lookup: hamısı yoxlanılır. Və ya mərkəzləşmiş bir Bloom + hər node-da cache.

## Best Practices

1. **Tələbə uyğun seç** — exact need-sizsə probabilistic cəlb edicidir
2. **False positive rate təyin et** — 1% çox vaxt kifayətdir
3. **Size pre-calculate et** — rəssam səhvlər runtime-da çətin
4. **Redis HyperLogLog** istifadə et — manual coding əvəzinə
5. **Cache miss detection** — Bloom filter ilə DB hit-lərini azalt
6. **Multi-hash independence** — hash funksiyaları asılı olmasın
7. **Memory vs CPU trade-off** — daha çox hash = daha aşağı FP
8. **Monitoring** — estimated vs actual müqayisə et
9. **Merging** — HyperLogLog-lar birləşdirilə bilər (union)
10. **Stream processing** — Kafka + CMS ilə real-time counting
