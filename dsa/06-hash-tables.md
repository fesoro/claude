# Hash Tables (Junior)

## Konsept (Concept)

Hash table key-value cutlerini saxlayan data strukturudur. Key hash funksiyasindan kecirilir ve naticede massivde indeks alinir. Ortalama O(1) vaxtda axtaris, elave, silme.

```
Key -> Hash Function -> Index -> Value

"name"  -> hash("name")  -> 2 -> "Orkhan"
"age"   -> hash("age")   -> 5 -> 25
"city"  -> hash("city")  -> 2 -> COLLISION!

Chaining (Zencirleme):
+---+
| 0 | -> null
+---+
| 1 | -> null
+---+
| 2 | -> ["name":"Orkhan"] -> ["city":"Baku"] -> null
+---+
| 3 | -> null
+---+
| 4 | -> null
+---+
| 5 | -> ["age":25] -> null
+---+

Open Addressing (Linear Probing):
+---+---------+---------+
| 0 |         |         |
| 1 |         |         |
| 2 | "name"  | "Orkhan"|  <- hash("name")=2
| 3 | "city"  | "Baku"  |  <- hash("city")=2, collision, next slot
| 4 |         |         |
| 5 | "age"   | 25      |
+---+---------+---------+
```

## Nece Isleyir? (How does it work?)

### Hash Funksiyasi
1. Key-i qebul edir (string, int, vs.)
2. Deterministic natice qaytarir (eyni key -> eyni hash)
3. Naticeni massiv boyutuna gore modul alir: `index = hash(key) % capacity`

### Collision (Toqqusma) hallari:
1. **Chaining:** Her slot linked list saxlayir. Eyni indeksli elementler siyahiya elave olunur.
2. **Open Addressing:**
   - Linear Probing: Novbeti bosh slot (i+1, i+2, ...)
   - Quadratic Probing: (i+1^2, i+2^2, ...)
   - Double Hashing: hash2(key) addim ile

### Load Factor
```
load_factor = n / capacity  (n = element sayi)

load_factor > 0.75 olduqda -> resize (adeten 2x)
Butun elementler yeniden hash olunur (rehash)
```

## Implementasiya (Implementation)

### Chaining ile Hash Table
```php
<?php
class HashTable {
    private array $buckets;
    private int $capacity;
    private int $size = 0;

    public function __construct(int $capacity = 16) {
        $this->capacity = $capacity;
        $this->buckets = array_fill(0, $capacity, []);
    }

    private function hash(string $key): int {
        $hash = 0;
        for ($i = 0; $i < strlen($key); $i++) {
            $hash = ($hash * 31 + ord($key[$i])) % $this->capacity;
        }
        return $hash;
    }

    public function put(string $key, mixed $value): void {
        $index = $this->hash($key);

        // Movcud key-i yenile
        foreach ($this->buckets[$index] as &$pair) {
            if ($pair[0] === $key) {
                $pair[1] = $value;
                return;
            }
        }

        // Yeni elave
        $this->buckets[$index][] = [$key, $value];
        $this->size++;

        // Resize yoxla
        if ($this->size / $this->capacity > 0.75) {
            $this->resize();
        }
    }

    public function get(string $key): mixed {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as $pair) {
            if ($pair[0] === $key) {
                return $pair[1];
            }
        }
        return null;
    }

    public function delete(string $key): bool {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as $i => $pair) {
            if ($pair[0] === $key) {
                unset($this->buckets[$index][$i]);
                $this->buckets[$index] = array_values($this->buckets[$index]);
                $this->size--;
                return true;
            }
        }
        return false;
    }

    private function resize(): void {
        $oldBuckets = $this->buckets;
        $this->capacity *= 2;
        $this->buckets = array_fill(0, $this->capacity, []);
        $this->size = 0;

        foreach ($oldBuckets as $bucket) {
            foreach ($bucket as $pair) {
                $this->put($pair[0], $pair[1]);
            }
        }
    }

    public function has(string $key): bool {
        return $this->get($key) !== null;
    }
}
```

### Open Addressing (Linear Probing)
```php
<?php
class OpenAddressingHashTable {
    private array $keys;
    private array $values;
    private array $occupied;
    private int $capacity;
    private int $size = 0;

    public function __construct(int $capacity = 16) {
        $this->capacity = $capacity;
        $this->keys = array_fill(0, $capacity, null);
        $this->values = array_fill(0, $capacity, null);
        $this->occupied = array_fill(0, $capacity, false);
    }

    private function hash(string $key): int {
        return crc32($key) % $this->capacity;
    }

    public function put(string $key, mixed $value): void {
        if ($this->size / $this->capacity > 0.5) {
            $this->resize();
        }

        $index = abs($this->hash($key));
        while ($this->occupied[$index]) {
            if ($this->keys[$index] === $key) {
                $this->values[$index] = $value;
                return;
            }
            $index = ($index + 1) % $this->capacity;
        }

        $this->keys[$index] = $key;
        $this->values[$index] = $value;
        $this->occupied[$index] = true;
        $this->size++;
    }

    public function get(string $key): mixed {
        $index = abs($this->hash($key));
        $start = $index;

        while ($this->occupied[$index]) {
            if ($this->keys[$index] === $key) {
                return $this->values[$index];
            }
            $index = ($index + 1) % $this->capacity;
            if ($index === $start) break;
        }

        return null;
    }

    private function resize(): void {
        $oldKeys = $this->keys;
        $oldValues = $this->values;
        $oldOccupied = $this->occupied;
        $oldCapacity = $this->capacity;

        $this->capacity *= 2;
        $this->keys = array_fill(0, $this->capacity, null);
        $this->values = array_fill(0, $this->capacity, null);
        $this->occupied = array_fill(0, $this->capacity, false);
        $this->size = 0;

        for ($i = 0; $i < $oldCapacity; $i++) {
            if ($oldOccupied[$i]) {
                $this->put($oldKeys[$i], $oldValues[$i]);
            }
        }
    }
}
```

### PHP Array as Hash Table
```php
<?php
// PHP array == hash table (ordered hash map)
$map = [];

// Insert: O(1) amortized
$map['name'] = 'Orkhan';
$map['age'] = 25;

// Lookup: O(1) average
echo $map['name']; // 'Orkhan'

// Exists check: O(1) average
isset($map['name']); // true -- istifade edin
array_key_exists('name', $map); // true -- null deyerleri de tapir

// Delete: O(1) amortized
unset($map['age']);

// Iterate (insertion order saxlanilir):
foreach ($map as $key => $value) {
    echo "$key: $value\n";
}

// PHP array internals:
// - Daxili olaraq hash table + doubly linked list
// - Insertion order saxlanilir (linked list ile)
// - Default capacity: 8, load factor ~0.5 de resize
// - Key: integer ve ya string (diger tipler cast olunur)
// - String key hash: DJBX33A (DJB hash funksiyasi)
```

### Frequency Counter Pattern
```php
<?php
// Herf tezliklerini say
function charFrequency(string $s): array {
    $freq = [];
    for ($i = 0; $i < strlen($s); $i++) {
        $ch = $s[$i];
        $freq[$ch] = ($freq[$ch] ?? 0) + 1;
    }
    return $freq;
}

// Anagram yoxla
function isAnagram(string $s, string $t): bool {
    if (strlen($s) !== strlen($t)) return false;
    return charFrequency($s) === charFrequency($t);
}

// Alternatif PHP yolu:
function isAnagramPHP(string $s, string $t): bool {
    return count_chars($s, 1) === count_chars($t, 1);
}
```

### Group Anagrams
```php
<?php
function groupAnagrams(array $strs): array {
    $groups = [];
    foreach ($strs as $str) {
        $key = str_split($str);
        sort($key);
        $key = implode('', $key);
        $groups[$key][] = $str;
    }
    return array_values($groups);
}
// ["eat","tea","tan","ate","nat","bat"]
// -> [["eat","tea","ate"], ["tan","nat"], ["bat"]]
```

### Two Sum (Hash Table ile)
```php
<?php
function twoSum(array $nums, int $target): array {
    $map = []; // value -> index

    foreach ($nums as $i => $num) {
        $complement = $target - $num;
        if (isset($map[$complement])) {
            return [$map[$complement], $i];
        }
        $map[$num] = $i;
    }
    return [];
}
// O(n) vaxt, O(n) yer -- brute force O(n^2) evezine
```

## Vaxt ve Yaddas Murekkebliyi (Time & Space Complexity)

| Emeliyyat | Average | Worst (hamisi collision) |
|-----------|---------|------------------------|
| Insert | O(1) | O(n) |
| Search | O(1) | O(n) |
| Delete | O(1) | O(n) |

| Collision Strategy | Average Lookup | Worst Lookup | Cache Performance |
|-------------------|---------------|-------------|-------------------|
| Chaining | O(1 + n/m) | O(n) | Zeyif (pointer chase) |
| Linear Probing | O(1) | O(n) | Yaxsi (cache-friendly) |
| Double Hashing | O(1) | O(n) | Orta |

Space: O(n)

## Tipik Meseleler (Common Problems)

### 1. First Non-Repeating Character
```php
<?php
function firstUniqChar(string $s): int {
    $freq = [];
    for ($i = 0; $i < strlen($s); $i++) {
        $freq[$s[$i]] = ($freq[$s[$i]] ?? 0) + 1;
    }
    for ($i = 0; $i < strlen($s); $i++) {
        if ($freq[$s[$i]] === 1) return $i;
    }
    return -1;
}
```

### 2. Longest Consecutive Sequence
```php
<?php
function longestConsecutive(array $nums): int {
    $set = array_flip($nums); // O(n) ile set yarad
    $maxLen = 0;

    foreach ($nums as $num) {
        // Yalniz ardicilligin baslangicindan basla
        if (!isset($set[$num - 1])) {
            $current = $num;
            $len = 1;
            while (isset($set[$current + 1])) {
                $current++;
                $len++;
            }
            $maxLen = max($maxLen, $len);
        }
    }

    return $maxLen;
}
// [100, 4, 200, 1, 3, 2] -> 4 (ardicillik: 1,2,3,4)
```

### 3. Subarray Sum Equals K
```php
<?php
function subarraySum(array $nums, int $k): int {
    $prefixSumCount = [0 => 1];
    $currentSum = 0;
    $count = 0;

    foreach ($nums as $num) {
        $currentSum += $num;
        if (isset($prefixSumCount[$currentSum - $k])) {
            $count += $prefixSumCount[$currentSum - $k];
        }
        $prefixSumCount[$currentSum] = ($prefixSumCount[$currentSum] ?? 0) + 1;
    }

    return $count;
}
```

### 4. LRU Cache (Hash + Doubly Linked List)
```php
<?php
class LRUNode {
    public string $key;
    public mixed $value;
    public ?LRUNode $prev = null;
    public ?LRUNode $next = null;

    public function __construct(string $key, mixed $value) {
        $this->key = $key;
        $this->value = $value;
    }
}

class LRUCache {
    private int $capacity;
    private array $map = [];
    private LRUNode $head;
    private LRUNode $tail;

    public function __construct(int $capacity) {
        $this->capacity = $capacity;
        $this->head = new LRUNode('', null);
        $this->tail = new LRUNode('', null);
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function get(string $key): mixed {
        if (!isset($this->map[$key])) return -1;
        $node = $this->map[$key];
        $this->moveToFront($node);
        return $node->value;
    }

    public function put(string $key, mixed $value): void {
        if (isset($this->map[$key])) {
            $this->map[$key]->value = $value;
            $this->moveToFront($this->map[$key]);
            return;
        }

        $node = new LRUNode($key, $value);
        $this->map[$key] = $node;
        $this->addToFront($node);

        if (count($this->map) > $this->capacity) {
            $lru = $this->tail->prev;
            $this->removeNode($lru);
            unset($this->map[$lru->key]);
        }
    }

    private function addToFront(LRUNode $node): void {
        $node->next = $this->head->next;
        $node->prev = $this->head;
        $this->head->next->prev = $node;
        $this->head->next = $node;
    }

    private function removeNode(LRUNode $node): void {
        $node->prev->next = $node->next;
        $node->next->prev = $node->prev;
    }

    private function moveToFront(LRUNode $node): void {
        $this->removeNode($node);
        $this->addToFront($node);
    }
}
```

## Interview Suallari

**S: Hash collision nedir ve nece hall olunur?**
C: Iki ferqli key-in eyni hash deyeri almasi. Chaining (linked list), open addressing (linear/quadratic probing, double hashing) ile hall olunur.

**S: Load factor nedir?**
C: element_sayi / capacity. 0.75-den boyuk olduqda resize (rehash) edilir. Yuksek load factor = cox collision = yavas.

**S: Niye hash table worst case O(n)?**
C: Butun key-ler eyni indekse dussesi (pis hash funksiyasi ve ya adversarial input). Chaining-de uzun linked list, probing-de uzun cluster yaranir.

**S: PHP array vs SplFixedArray?**
C: PHP array hash map-dir -- her element ~72 byte. SplFixedArray fixed-size massivdir -- daha az yaddas, amma yalniz integer key.

## PHP/Laravel ile Elaqe

```php
<?php
// 1. PHP array == ordered hash map
// Daxili: HashTable struct, Bucket linked list, arran + packed optimizasiya

// 2. Laravel Cache (hash table pattern)
// Cache::put('key', 'value', $ttl);
// Cache::get('key');
// Redis, Memcached arxa planda hash table istifade edir

// 3. Laravel Config (dot notation hash)
// config('app.name') -> nested array lookup

// 4. Session handling
// session()->put('user_id', 123);
// Daxili olaraq hash table-da saxlanilir

// 5. Route parameter binding
// Route::get('/users/{user}', ...) -- hash table-da route match

// 6. Eloquent attribute casting
// protected $casts = ['settings' => 'array'];
// JSON <-> PHP array (hash map)

// 7. Collection keyBy - O(n) yaratma, sonra O(1) lookup
$users = collect($users)->keyBy('id');
$user = $users[42]; // O(1) evezine $users->find(42) O(n)
```

## Praktik Tapşırıqlar

1. **LeetCode 1** — Two Sum (hash map ilə O(n))
2. **LeetCode 49** — Group Anagrams
3. **LeetCode 128** — Longest Consecutive Sequence
4. **LeetCode 560** — Subarray Sum Equals K (prefix sum + hash)
5. **LeetCode 146** — LRU Cache

### Step-by-step: Group Anagrams

```
["eat","tea","tan","ate","nat","bat"]

"eat" → sort → "aet" → groups["aet"] = ["eat"]
"tea" → sort → "aet" → groups["aet"] = ["eat","tea"]
"tan" → sort → "ant" → groups["ant"] = ["tan"]
"ate" → sort → "aet" → groups["aet"] = ["eat","tea","ate"]
"nat" → sort → "ant" → groups["ant"] = ["tan","nat"]
"bat" → sort → "abt" → groups["abt"] = ["bat"]

Nəticə: [["eat","tea","ate"],["tan","nat"],["bat"]]
```

## Əlaqəli Mövzular

- [01-big-o-notation.md](01-big-o-notation.md) — O(1) average, O(n) worst case
- [02-arrays.md](02-arrays.md) — Array vs Hash Table müqayisəsi
- [10-prefix-sum.md](10-prefix-sum.md) — Prefix sum + hash map pattern
- [35-design-problems.md](35-design-problems.md) — LRU/LFU cache dizaynı
