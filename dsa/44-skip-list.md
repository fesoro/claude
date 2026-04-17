# Skip List - Probabilistic Data Structure

## Konsept (Concept)

**Skip List** — sıralanmış linked list üzərində qurulan **ehtimallı (probabilistic)** məlumat strukturudur. Adi linked list-də axtarış O(n) alır, lakin Skip List `log n` səviyyə əlavə etməklə orta halda **O(log n)** axtarış təmin edir.

Balanced BST-ə (AVL, Red-Black) alternativdir. Üstünlük — **implementasiyası sadədir**, **lock-free concurrent versiyaları mövcuddur** və **cache-friendly** ola bilir.

### Əsas xüsusiyyətlər
- **Probabilistic**: Node-un səviyyəsi `coin flip` (çox vaxt p=0.5) ilə təyin olunur
- **Expected O(log n)**: Orta hal, amma worst-case O(n) ola bilər (çox nadir)
- **Dynamic**: Insert və delete zamanı yenidən balanslamaq lazım deyil
- **Sorted**: Elementlər həmişə sıralı formada saxlanılır
- **Range query**: Linked list strukturu sayəsində `[a, b]` aralığının iterasiyası asandır

### Kim istifadə edir?
- **Redis** — `ZSET` (sorted set) daxilində score-lara görə sıralanmış elementləri saxlamaq üçün Skip List + Hash Table kombinasiyası
- **LevelDB / RocksDB** — MemTable implementasiyası
- **Apache Cassandra** — in-memory data strukturu kimi
- **Java** — `ConcurrentSkipListMap` və `ConcurrentSkipListSet`

## Necə İşləyir?

### Multi-level struktur

```
Level 3:  HEAD -----------------------------> 50 -------------> NIL
Level 2:  HEAD ---------> 20 ---------------> 50 -------> 80 -> NIL
Level 1:  HEAD ---> 10 -> 20 -------> 40 ---> 50 -> 70 -> 80 -> NIL
Level 0:  HEAD --> 10 -> 20 -> 30 -> 40 -> 50 -> 70 -> 80 -> 90 -> NIL
```

- **Level 0** — bütün elementlər (adi sorted linked list)
- **Hər yuxarı səviyyə** — aşağı səviyyənin ehtimalla (p=0.5) alt çoxluğu
- **Express lane** — yuxarı səviyyələr "tez keçid" rolunu oynayır

### Axtarış (Search)
```
search(key):
  curr = head, level = topLevel
  while curr != NIL:
    if curr.next[level] != NIL && curr.next[level].key < key:
      curr = curr.next[level]        // sağa get
    else if level > 0:
      level--                        // aşağı en
    else:
      break
  return curr.next[0].key == key
```

En yuxarı səviyyədən başlayırıq, `key` tapılana qədər ya **sağa**, ya da **aşağı** hərəkət edirik.

### Əlavə etmə (Insert)
1. Yeni node üçün random səviyyə tap: `while (rand() < p) level++`
2. `update[]` massivini saxla — hər səviyyədə yeni node-un əvvəli
3. Səviyyə-səviyyə əlaqələri yenilə
4. Ehtimal: Level 1 → 50%, Level 2 → 25%, Level 3 → 12.5%, ...

### Silmə (Delete)
1. Axtarış zamanı bütün səviyyələrdə əvvəlki node-u tap
2. Hər səviyyədə `prev.next = node.next` yenilə
3. Boş qalmış yuxarı səviyyələri azalt

## İmplementasiya (Implementation) - PHP

### 1. Node sinfi

```php
class SkipListNode
{
    public int|string $key;
    public mixed $value;
    /** @var array<int, SkipListNode|null> */
    public array $forward;

    public function __construct(int|string $key, mixed $value, int $level)
    {
        $this->key = $key;
        $this->value = $value;
        $this->forward = array_fill(0, $level + 1, null);
    }
}
```

### 2. Skip List sinfi

```php
class SkipList
{
    private const MAX_LEVEL = 16;
    private const P = 0.5;

    private SkipListNode $head;
    private int $level = 0;
    private int $size = 0;

    public function __construct()
    {
        // Sentinel head — ən aşağı açar
        $this->head = new SkipListNode(PHP_INT_MIN, null, self::MAX_LEVEL);
    }

    private function randomLevel(): int
    {
        $lvl = 0;
        while (mt_rand() / mt_getrandmax() < self::P && $lvl < self::MAX_LEVEL) {
            $lvl++;
        }
        return $lvl;
    }

    public function search(int|string $key): mixed
    {
        $curr = $this->head;
        for ($i = $this->level; $i >= 0; $i--) {
            while ($curr->forward[$i] !== null && $curr->forward[$i]->key < $key) {
                $curr = $curr->forward[$i];
            }
        }
        $curr = $curr->forward[0];
        if ($curr !== null && $curr->key === $key) {
            return $curr->value;
        }
        return null;
    }

    public function insert(int|string $key, mixed $value): void
    {
        $update = array_fill(0, self::MAX_LEVEL + 1, $this->head);
        $curr = $this->head;

        for ($i = $this->level; $i >= 0; $i--) {
            while ($curr->forward[$i] !== null && $curr->forward[$i]->key < $key) {
                $curr = $curr->forward[$i];
            }
            $update[$i] = $curr;
        }

        $curr = $curr->forward[0];

        // Eyni açar varsa — value-nu yenilə
        if ($curr !== null && $curr->key === $key) {
            $curr->value = $value;
            return;
        }

        $newLevel = $this->randomLevel();
        if ($newLevel > $this->level) {
            for ($i = $this->level + 1; $i <= $newLevel; $i++) {
                $update[$i] = $this->head;
            }
            $this->level = $newLevel;
        }

        $newNode = new SkipListNode($key, $value, $newLevel);
        for ($i = 0; $i <= $newLevel; $i++) {
            $newNode->forward[$i] = $update[$i]->forward[$i];
            $update[$i]->forward[$i] = $newNode;
        }
        $this->size++;
    }

    public function delete(int|string $key): bool
    {
        $update = array_fill(0, self::MAX_LEVEL + 1, $this->head);
        $curr = $this->head;

        for ($i = $this->level; $i >= 0; $i--) {
            while ($curr->forward[$i] !== null && $curr->forward[$i]->key < $key) {
                $curr = $curr->forward[$i];
            }
            $update[$i] = $curr;
        }

        $curr = $curr->forward[0];
        if ($curr === null || $curr->key !== $key) {
            return false;
        }

        for ($i = 0; $i <= $this->level; $i++) {
            if ($update[$i]->forward[$i] !== $curr) {
                break;
            }
            $update[$i]->forward[$i] = $curr->forward[$i];
        }

        // Boş yuxarı səviyyələri azalt
        while ($this->level > 0 && $this->head->forward[$this->level] === null) {
            $this->level--;
        }
        $this->size--;
        return true;
    }

    public function size(): int
    {
        return $this->size;
    }

    /**
     * Range query: [min, max] aralığındakı bütün elementlər
     * @return array<int, array{key: int|string, value: mixed}>
     */
    public function range(int|string $min, int|string $max): array
    {
        $result = [];
        $curr = $this->head;

        // $min-ə qədər dığırlayırıq
        for ($i = $this->level; $i >= 0; $i--) {
            while ($curr->forward[$i] !== null && $curr->forward[$i]->key < $min) {
                $curr = $curr->forward[$i];
            }
        }

        $curr = $curr->forward[0];
        while ($curr !== null && $curr->key <= $max) {
            $result[] = ['key' => $curr->key, 'value' => $curr->value];
            $curr = $curr->forward[0];
        }
        return $result;
    }
}
```

### 3. İstifadə nümunəsi

```php
$sl = new SkipList();
$sl->insert(10, 'ten');
$sl->insert(5, 'five');
$sl->insert(20, 'twenty');
$sl->insert(15, 'fifteen');
$sl->insert(30, 'thirty');

echo $sl->search(15);           // "fifteen"
echo $sl->search(99) ?? 'null'; // "null"

print_r($sl->range(10, 25));
// [[10=>'ten'], [15=>'fifteen'], [20=>'twenty']]

$sl->delete(15);
echo $sl->size();               // 4
```

### 4. Redis ZSET kimi SortedSet wrapper (qısa)

`zadd / zscore / zrangeByScore` qurmaq üçün yuxarıdakı `SkipList`-ə `compositeKey = sprintf("%020.6f:%s", $score, $member)` tətbiq et: score → kompozit açar → SkipList-ə insert, member → score üçün ayrıca hash map. `zrangeByScore($min, $max)` → `SkipList::range(composite($min, ''), composite($max, "\xff"))` qaytarır. Redis ZSET məhz bu iki strukturu birləşdirir.

## Vaxt və Yaddaş Mürəkkəbliyi

| Əməliyyat    | Orta hal     | Worst case |
|--------------|--------------|------------|
| Search       | O(log n)     | O(n)       |
| Insert       | O(log n)     | O(n)       |
| Delete       | O(log n)     | O(n)       |
| Range query  | O(log n + k) | O(n + k)   |
| Min / Max    | O(1) / O(log n) | O(n)    |

- **Yaddaş**: O(n) gözlənilən (hər element orta 2 pointer saxlayır çünki `1 + 0.5 + 0.25 + ... = 2`)
- **Worst case yaddaş**: O(n log n) — bütün node-lar ən yuxarı səviyyəyə düşərsə
- **Worst case çox nadirdir** — ehtimalı təxminən `1/n^c` (c > 0)

### Red-Black Tree ilə müqayisə
| Xüsusiyyət              | Skip List           | Red-Black Tree    |
|-------------------------|---------------------|-------------------|
| Axtarış/İnsert/Delete   | O(log n) expected   | O(log n) worst    |
| Implementasiya          | Sadə                | Mürəkkəb (rotate) |
| Concurrency (lock-free) | Asan                | Çətin             |
| Cache locality          | Yaxşı (linked list) | Orta              |
| Range query             | Təbii sürətli       | In-order traversal|

## Tipik Məsələlər (Common Problems)

1. **Design a sorted set** (LeetCode 1206) — Skip List ilə `add`, `erase`, `search`
2. **LRU / LFU cache** — Skip List + Hash Map kombinasiyası
3. **Leaderboard / Ranking** — score üzrə top-k, rank query
4. **Time-series data** — timestamp üzrə range query
5. **Redis ZRANGEBYSCORE simulyasiyası**
6. **In-memory search engine** — term frequency üzrə sıralama
7. **Event scheduling** — priority_queue alternativi (lakin silinmə ilə)

## Interview Sualları

### 1. Skip List nədir və necə işləyir?
**Cavab**: Sıralanmış linked list üzərində çoxsəviyyəli ehtimallı strukturdur. Hər element `random level` alır və yuxarı səviyyələr "express lane" kimi işləyir. Axtarış yuxarıdan başlayır: sağa get — açar kiçikdirsə, aşağı en — böyükdürsə. Orta halda O(log n).

### 2. Niyə Red-Black Tree əvəzinə Skip List istifadə edilir?
**Cavab**: 
- **İmplementasiya sadədir** — rotate, color-fixing yoxdur
- **Concurrent (lock-free) versiyaları asan** — node-lar arası əlaqələr atomic CAS ilə yenilənir
- **Range query təbii sürətli** — bottom level adi sorted linked list-dir
- **Cache locality yaxşı** — linear traversal
- **Redis** məhz bu səbəblərdən seçmişdir (antirez izah edib)

### 3. Redis ZSET daxilində Skip List necə istifadə olunur?
**Cavab**: Redis ZSET **iki struktur** saxlayır:
- **Skip List**: score → member üzrə sıralanmış (range query, rank üçün)
- **Hash Table**: member → score (O(1) `ZSCORE`, `ZADD` üçün)

`ZRANGEBYSCORE` → Skip List istifadə edir. `ZSCORE` → Hash Table. Kiçik set-lərdə (< 128 element, hər element < 64 bayt) Redis `ziplist` (kompakt format) istifadə edir, sonra Skip List-ə keçir.

### 4. Skip List-də worst case-i nə vaxt görürsən?
**Cavab**: Əgər hər node yalnız Level 0-da qalırsa (bütün random level-lər 0 gəlirsə), struktur adi linked list-ə çevrilir — O(n). Lakin `p=0.5` ilə bütün elementlərin level 0-da qalma ehtimalı `0.5^n` — praktik olaraq baş vermir.

### 5. `MAX_LEVEL` necə seçilir?
**Cavab**: `log_{1/p}(n)` formulasına əsasən. `p=0.5` və `n=2^32` üçün `MAX_LEVEL = 32`. Redis **32** istifadə edir (64-bit scores üçün kifayətdir). Həddən artıq yuxarı limit yaddaş israfıdır, çox aşağı — performansı aşağı salır.

### 6. Skip List-də duplicate key-lərlə necə işləyirsən?
**Cavab**: 
- Birincisi — duplicate-ləri icazə vermirsən (set kimi davranır)
- İkincisi — `value` list saxlayırsan hər node-da
- Üçüncüsü — "unique key" yaradırsan (məsələn `(key, counter)`), Redis bunu `score:member` kompozit açarı ilə edir

### 7. Skip List `concurrent` necə edilir?
**Cavab**: 
- **Optimistic locking** — axtarış kilidşiz, insert/delete `compare-and-swap` ilə
- **Lock-free**: hər `forward pointer` atomic dəyişdirilir
- **Marker nodes** — silməzdən əvvəl "tombstone" qoyulur ki, başqa thread-lər yanlış oxumasın
- Java-da `ConcurrentSkipListMap` nümunəsidir

### 8. Balanced BST-dən (AVL/RB) nə zaman üstünlük verir?
**Cavab**:
- **Sadə concurrent implementasiya** lazımdır
- **Worst case deyil, average case vacibdir**
- **Range query** çox olur
- **Randomization** qəbul edilir
- Java/Go kimi standart library-də hazır varsa

### 9. `p` əmsalını dəyişsək nə olar?
**Cavab**:
- `p=0.5` — hündürlük `log_2 n`, hər element orta 2 pointer
- `p=0.25` — hündürlük `log_4 n` (daha alçaq), hər element orta `4/3` pointer — **daha az yaddaş, daha çox müqayisə**
- Redis `p=1/4` istifadə edir — yaddaş qənaəti üçün

### 10. Skip List-də rank (order statistic) necə tapılır?
**Cavab**: Hər `forward[i]` yanında `span[i]` saxlayırsan — "bu linkdə neçə node keçilir". Axtarış zamanı keçilən `span`-ları toplayırsan — bu `rank`-dır. Redis-də `ZRANK` belə işləyir, O(log n).

## PHP/Laravel ilə Əlaqə

### 1. Redis ZSET Laravel-də

```php
use Illuminate\Support\Facades\Redis;

// Leaderboard (Skip List əsaslı)
Redis::zadd('leaderboard', 1500, 'user:1');
Redis::zadd('leaderboard', 2300, 'user:2');
Redis::zadd('leaderboard', 1800, 'user:3');

// Top 10 — O(log n + k)
$top10 = Redis::zrevrange('leaderboard', 0, 9, 'WITHSCORES');

// User rank — O(log n)
$rank = Redis::zrevrank('leaderboard', 'user:1');

// Score range — O(log n + k)
$mid = Redis::zrangebyscore('leaderboard', 1000, 2000);
```

Arxada **Skip List** işləyir — bütün əməliyyatlar O(log n).

### 2. Time-series cache

```php
// Son 1 saatın event-ləri
$now = time();
Redis::zadd('events:user:1', $now, json_encode($event));

// Köhnə event-ləri sil
Redis::zremrangebyscore('events:user:1', 0, $now - 3600);

// Son N dəqiqənin event-ləri
$events = Redis::zrangebyscore('events:user:1', $now - 300, $now);
```

### 3. Rate limiting (sliding window) — Skip List arxada

```php
$key = "rate:{$userId}";
$now = microtime(true);
$windowStart = $now - 60;

Redis::pipeline(function ($pipe) use ($key, $now, $windowStart) {
    $pipe->zremrangebyscore($key, 0, $windowStart);
    $pipe->zadd($key, $now, (string) $now);
    $pipe->zcard($key);
    $pipe->expire($key, 60);
});
```

### 4. PHP-də custom Skip List nə vaxt?
**Az halda** — Redis əksər sorted set lazımlıqlarını ödəyir, `ksort($array)` statik datada kifayətdir. **Custom lazım**: in-process ordered map, tez-tez insert+range query, öyrənmə/müsahibə.
