# Design Problems (Dizayn Meseleleri)

## Konsept (Concept)

Design problems interview-larda class və data structure dizaynını yoxlayır. Burada məqsəd sadəcə alqoritm yazmaq deyil, **clean API**, **time/space trade-offs** və **OOP prinsipləri** nümayiş etdirməkdir.

```
Tipik design problems:
- LRU Cache (Least Recently Used)
- LFU Cache (Least Frequently Used)
- Min Stack (O(1) min element)
- Iterator (Flatten nested list, BST iterator)
- Circular Buffer / Queue
- Rate Limiter
- Trie (autocomplete)
- HashMap / HashSet (from scratch)
- Twitter-like feed
- File System
```

### Əsas prinsiplər:
1. **Clean interface**: Public metodlar sadə və adi olmalıdır
2. **Time complexity**: Adətən O(1) amortized tələb olunur
3. **Thread safety**: Bəzən tələb olunur (race conditions)
4. **Memory efficiency**: Eviction policies, capacity limits
5. **Edge cases**: Empty, full, boundary conditions

### Design patterns-in tətbiqi:
- **Doubly Linked List + HashMap**: LRU Cache
- **HashMap + frequency buckets**: LFU Cache
- **Stack with auxiliary stack**: Min Stack
- **Iterator pattern**: BST, nested lists
- **Token bucket / Sliding window**: Rate limiter

## Necə İşləyir? (How does it work?)

### LRU Cache (Doubly Linked List + HashMap):
```
Capacity = 3

put(1, "A"):  head <-> (1:A) <-> tail, map={1: node}
put(2, "B"):  head <-> (2:B) <-> (1:A) <-> tail
put(3, "C"):  head <-> (3:C) <-> (2:B) <-> (1:A) <-> tail
get(1):       head <-> (1:A) <-> (3:C) <-> (2:B) <-> tail
put(4, "D"):  full! evict (2:B)
              head <-> (4:D) <-> (1:A) <-> (3:C) <-> tail

Head = most recently used
Tail = least recently used
```

### LFU Cache (HashMap + Frequency buckets):
```
freqMap: freq -> DoublyLinkedList of (key, value)
keyMap: key -> (value, freq)
minFreq: current minimum frequency

put(1, "A"): keyMap={1:(A, 1)}, freqMap={1:[1]}, minFreq=1
put(2, "B"): keyMap={1:(A,1), 2:(B,1)}, freqMap={1:[2,1]}
get(1):      freq=1->2, freqMap={1:[2], 2:[1]}, minFreq=1
put(3, "C"): full! evict minFreq key (2:B)
             keyMap={1:(A,2), 3:(C,1)}, freqMap={1:[3], 2:[1]}, minFreq=1
```

### Min Stack:
```
push(3):  stack=[3],    minStack=[3]
push(5):  stack=[3,5],  minStack=[3]  (5 > 3, min olmadi)
push(2):  stack=[3,5,2], minStack=[3,2]
push(1):  stack=[3,5,2,1], minStack=[3,2,1]
pop():    stack=[3,5,2], minStack=[3,2]
top():    2
getMin(): 2
```

## Implementasiya (Implementation)

```php
<?php

// LRU Cache
class LRUNode
{
    public $key;
    public $value;
    public ?LRUNode $prev = null;
    public ?LRUNode $next = null;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }
}

class LRUCache
{
    private int $capacity;
    private array $map = []; // key -> LRUNode
    private LRUNode $head;
    private LRUNode $tail;

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
        $this->head = new LRUNode(0, 0);
        $this->tail = new LRUNode(0, 0);
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function get($key): int
    {
        if (!isset($this->map[$key])) return -1;
        $node = $this->map[$key];
        $this->moveToHead($node);
        return $node->value;
    }

    public function put($key, $value): void
    {
        if (isset($this->map[$key])) {
            $node = $this->map[$key];
            $node->value = $value;
            $this->moveToHead($node);
            return;
        }

        $node = new LRUNode($key, $value);
        $this->map[$key] = $node;
        $this->addToHead($node);

        if (count($this->map) > $this->capacity) {
            $lru = $this->tail->prev;
            $this->removeNode($lru);
            unset($this->map[$lru->key]);
        }
    }

    private function addToHead(LRUNode $node): void
    {
        $node->prev = $this->head;
        $node->next = $this->head->next;
        $this->head->next->prev = $node;
        $this->head->next = $node;
    }

    private function removeNode(LRUNode $node): void
    {
        $node->prev->next = $node->next;
        $node->next->prev = $node->prev;
    }

    private function moveToHead(LRUNode $node): void
    {
        $this->removeNode($node);
        $this->addToHead($node);
    }
}

// LFU Cache
class LFUCache
{
    private int $capacity;
    private int $minFreq = 0;
    private array $keyMap = [];      // key -> [value, freq]
    private array $freqMap = [];     // freq -> [key => true] (order preserved)

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
    }

    public function get($key): int
    {
        if (!isset($this->keyMap[$key]) || $this->capacity <= 0) return -1;
        $this->touch($key);
        return $this->keyMap[$key][0];
    }

    public function put($key, $value): void
    {
        if ($this->capacity <= 0) return;

        if (isset($this->keyMap[$key])) {
            $this->keyMap[$key][0] = $value;
            $this->touch($key);
            return;
        }

        if (count($this->keyMap) >= $this->capacity) {
            // Evict least frequent, oldest among them
            $evictKey = array_key_first($this->freqMap[$this->minFreq]);
            unset($this->freqMap[$this->minFreq][$evictKey]);
            unset($this->keyMap[$evictKey]);
        }

        $this->keyMap[$key] = [$value, 1];
        $this->freqMap[1][$key] = true;
        $this->minFreq = 1;
    }

    private function touch($key): void
    {
        $freq = $this->keyMap[$key][1];
        unset($this->freqMap[$freq][$key]);
        if (empty($this->freqMap[$freq])) {
            unset($this->freqMap[$freq]);
            if ($this->minFreq === $freq) $this->minFreq++;
        }
        $this->keyMap[$key][1] = $freq + 1;
        $this->freqMap[$freq + 1][$key] = true;
    }
}

// Min Stack
class MinStack
{
    private array $stack = [];
    private array $minStack = [];

    public function push(int $val): void
    {
        $this->stack[] = $val;
        if (empty($this->minStack) || $val <= end($this->minStack)) {
            $this->minStack[] = $val;
        }
    }

    public function pop(): void
    {
        $val = array_pop($this->stack);
        if ($val === end($this->minStack)) {
            array_pop($this->minStack);
        }
    }

    public function top(): int
    {
        return end($this->stack);
    }

    public function getMin(): int
    {
        return end($this->minStack);
    }
}

// Circular Buffer (Ring Buffer)
class CircularBuffer
{
    private array $buffer;
    private int $capacity;
    private int $head = 0;
    private int $tail = 0;
    private int $size = 0;

    public function __construct(int $capacity)
    {
        $this->capacity = $capacity;
        $this->buffer = array_fill(0, $capacity, null);
    }

    public function enqueue($value): bool
    {
        if ($this->isFull()) return false;
        $this->buffer[$this->tail] = $value;
        $this->tail = ($this->tail + 1) % $this->capacity;
        $this->size++;
        return true;
    }

    public function dequeue()
    {
        if ($this->isEmpty()) return null;
        $value = $this->buffer[$this->head];
        $this->buffer[$this->head] = null;
        $this->head = ($this->head + 1) % $this->capacity;
        $this->size--;
        return $value;
    }

    public function front()
    {
        return $this->isEmpty() ? null : $this->buffer[$this->head];
    }

    public function isEmpty(): bool { return $this->size === 0; }
    public function isFull(): bool { return $this->size === $this->capacity; }
}

// BST Iterator
class TreeNode
{
    public $val;
    public ?TreeNode $left = null;
    public ?TreeNode $right = null;
    public function __construct($val) { $this->val = $val; }
}

class BSTIterator
{
    private array $stack = [];

    public function __construct(?TreeNode $root)
    {
        $this->pushLeft($root);
    }

    public function next(): int
    {
        $node = array_pop($this->stack);
        $this->pushLeft($node->right);
        return $node->val;
    }

    public function hasNext(): bool
    {
        return !empty($this->stack);
    }

    private function pushLeft(?TreeNode $node): void
    {
        while ($node !== null) {
            $this->stack[] = $node;
            $node = $node->left;
        }
    }
}

// Rate Limiter (Token Bucket)
class TokenBucketLimiter
{
    private int $capacity;
    private float $tokens;
    private float $refillRate; // tokens per second
    private float $lastRefill;

    public function __construct(int $capacity, float $refillRate)
    {
        $this->capacity = $capacity;
        $this->tokens = $capacity;
        $this->refillRate = $refillRate;
        $this->lastRefill = microtime(true);
    }

    public function allow(int $cost = 1): bool
    {
        $this->refill();
        if ($this->tokens >= $cost) {
            $this->tokens -= $cost;
            return true;
        }
        return false;
    }

    private function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefill;
        $this->tokens = min($this->capacity, $this->tokens + $elapsed * $this->refillRate);
        $this->lastRefill = $now;
    }
}

// Sliding Window Rate Limiter
class SlidingWindowLimiter
{
    private int $limit;
    private int $windowSize; // seconds
    private array $requests = []; // user -> [timestamps]

    public function __construct(int $limit, int $windowSize)
    {
        $this->limit = $limit;
        $this->windowSize = $windowSize;
    }

    public function allow(string $userId): bool
    {
        $now = time();
        $this->requests[$userId] = array_values(array_filter(
            $this->requests[$userId] ?? [],
            fn($t) => $t > $now - $this->windowSize
        ));

        if (count($this->requests[$userId]) >= $this->limit) {
            return false;
        }

        $this->requests[$userId][] = $now;
        return true;
    }
}

// Istifade
$lru = new LRUCache(2);
$lru->put(1, 1);
$lru->put(2, 2);
echo $lru->get(1); // 1
$lru->put(3, 3); // 2 evict
echo $lru->get(2); // -1

$minStack = new MinStack();
$minStack->push(3);
$minStack->push(5);
$minStack->push(2);
echo $minStack->getMin(); // 2

$limiter = new TokenBucketLimiter(10, 1.0);
echo $limiter->allow() ? "OK" : "LIMITED"; // OK
```

## Vaxt və Yaddaş Mürəkkəbliyi (Time & Space Complexity)

| Struktur | Operation | Time | Space |
|----------|-----------|------|-------|
| LRU Cache | get/put | O(1) | O(capacity) |
| LFU Cache | get/put | O(1) amortized | O(capacity) |
| Min Stack | push/pop/top/getMin | O(1) | O(n) |
| Circular Buffer | enqueue/dequeue | O(1) | O(capacity) |
| BST Iterator | next/hasNext | O(1) amortized | O(h) |
| Token Bucket | allow | O(1) | O(1) |
| Sliding Window | allow | O(1) amortized | O(n) |

## Tipik Məsələlər (Common Problems)

### 1. Design Twitter
```php
class Twitter
{
    private int $timer = 0;
    private array $tweets = [];    // user -> [[tweetId, time]]
    private array $following = []; // user -> [followed users]

    public function postTweet(int $userId, int $tweetId): void
    {
        $this->tweets[$userId][] = [$tweetId, $this->timer++];
    }

    public function getNewsFeed(int $userId): array
    {
        $heap = new SplPriorityQueue();
        $users = array_merge([$userId], array_keys($this->following[$userId] ?? []));

        foreach ($users as $u) {
            foreach ($this->tweets[$u] ?? [] as $tweet) {
                $heap->insert($tweet[0], $tweet[1]);
            }
        }

        $result = [];
        for ($i = 0; $i < 10 && !$heap->isEmpty(); $i++) {
            $result[] = $heap->extract();
        }
        return $result;
    }

    public function follow(int $followerId, int $followeeId): void
    {
        if ($followerId === $followeeId) return;
        $this->following[$followerId][$followeeId] = true;
    }

    public function unfollow(int $followerId, int $followeeId): void
    {
        unset($this->following[$followerId][$followeeId]);
    }
}
```

### 2. Flatten Nested List Iterator
```php
class NestedIterator
{
    private array $stack = [];

    public function __construct(array $nestedList)
    {
        $this->stack = array_reverse($nestedList);
    }

    public function next(): int
    {
        $this->ensureTopIsInteger();
        return array_pop($this->stack);
    }

    public function hasNext(): bool
    {
        $this->ensureTopIsInteger();
        return !empty($this->stack);
    }

    private function ensureTopIsInteger(): void
    {
        while (!empty($this->stack) && is_array(end($this->stack))) {
            $list = array_pop($this->stack);
            foreach (array_reverse($list) as $item) {
                $this->stack[] = $item;
            }
        }
    }
}
```

### 3. Trie (Prefix Tree)
```php
class TrieNode
{
    public array $children = [];
    public bool $isEnd = false;
}

class Trie
{
    private TrieNode $root;

    public function __construct()
    {
        $this->root = new TrieNode();
    }

    public function insert(string $word): void
    {
        $node = $this->root;
        for ($i = 0; $i < strlen($word); $i++) {
            $ch = $word[$i];
            if (!isset($node->children[$ch])) {
                $node->children[$ch] = new TrieNode();
            }
            $node = $node->children[$ch];
        }
        $node->isEnd = true;
    }

    public function search(string $word): bool
    {
        $node = $this->findNode($word);
        return $node !== null && $node->isEnd;
    }

    public function startsWith(string $prefix): bool
    {
        return $this->findNode($prefix) !== null;
    }

    private function findNode(string $str): ?TrieNode
    {
        $node = $this->root;
        for ($i = 0; $i < strlen($str); $i++) {
            $ch = $str[$i];
            if (!isset($node->children[$ch])) return null;
            $node = $node->children[$ch];
        }
        return $node;
    }
}
```

### 4. Design HashMap (from scratch)
```php
class MyHashMap
{
    private array $buckets;
    private int $size = 1000;

    public function __construct()
    {
        $this->buckets = array_fill(0, $this->size, []);
    }

    public function put(int $key, int $value): void
    {
        $h = $key % $this->size;
        foreach ($this->buckets[$h] as &$pair) {
            if ($pair[0] === $key) { $pair[1] = $value; return; }
        }
        $this->buckets[$h][] = [$key, $value];
    }

    public function get(int $key): int
    {
        $h = $key % $this->size;
        foreach ($this->buckets[$h] as $pair) {
            if ($pair[0] === $key) return $pair[1];
        }
        return -1;
    }

    public function remove(int $key): void
    {
        $h = $key % $this->size;
        foreach ($this->buckets[$h] as $i => $pair) {
            if ($pair[0] === $key) {
                array_splice($this->buckets[$h], $i, 1);
                return;
            }
        }
    }
}
```

### 5. Snapshot Array
```php
class SnapshotArray
{
    private array $history;
    private int $snapId = 0;

    public function __construct(int $length)
    {
        $this->history = array_fill(0, $length, [[0, 0]]); // index -> [[snapId, value]]
    }

    public function set(int $index, int $val): void
    {
        $last = end($this->history[$index]);
        if ($last[0] === $this->snapId) {
            $this->history[$index][count($this->history[$index]) - 1][1] = $val;
        } else {
            $this->history[$index][] = [$this->snapId, $val];
        }
    }

    public function snap(): int
    {
        return $this->snapId++;
    }

    public function get(int $index, int $snap): int
    {
        $records = $this->history[$index];
        $l = 0; $r = count($records) - 1;
        while ($l <= $r) {
            $m = intval(($l + $r) / 2);
            if ($records[$m][0] <= $snap) $l = $m + 1;
            else $r = $m - 1;
        }
        return $records[$r][1];
    }
}
```

## Interview Sualları

1. **LRU Cache niye doubly linked list + hashmap istifadə edir?**
   - Hashmap O(1) lookup, doubly linked list O(1) insertion/removal. Birlikdə hər iki əməliyyat O(1) olur.

2. **LRU-da singly linked list niye kifayət etmir?**
   - Middle node-u silmek üçün previous node lazımdır. Doubly linked list bunu O(1) təmin edir.

3. **LFU-da eyni frequency-də hansı key evict olunur?**
   - Ən köhnə (LRU within same frequency). Ona görə hər frequency üçün linked list saxlanır.

4. **Min Stack-də auxiliary stack niye lazımdır?**
   - Hər push-da həmin ana qədər olan minimum-u saxlamaq üçün. Pop-da düzgün minimum-a qayıtmaq.

5. **Token bucket vs leaky bucket fərqi?**
   - **Token bucket**: Bursts-ə icazə verir, max tokens = capacity.
   - **Leaky bucket**: Sabit dərəcədə axır, bursts icazəsi yoxdur.

6. **Sliding window rate limiter-in memory problemi?**
   - Hər user üçün timestamp-ları saxlamaq lazımdır. Redis sorted set istifadəsi praktikdir.

7. **BST iterator niye O(h) space-dedir?**
   - Stack hündürlüyü ən çox ağacın hündürlüyü qədər ola bilər (left-most yol).

8. **Trie vs Hash Map: avtomatik tamamlamaq üçün hansı daha yaxşıdır?**
   - **Trie**: Prefix search O(p) (p prefix uzunluğu), çox söz paylaşıldığında memory efficient.
   - **Hash map**: Exact match O(1), amma prefix search bütün key-ləri iterate etmək tələb edir.

9. **Thread safety üçün nə lazımdır?**
   - Locks (mutex), atomic operations, və ya concurrent data structures.

## PHP/Laravel ilə Əlaqə

### Laravel cache (LRU-like):
```php
// Laravel-də cache driver-ləri LRU qismən implementasiya edir
Cache::put('key', 'value', now()->addMinutes(10));
$value = Cache::get('key');

// Redis-də maxmemory-policy: allkeys-lru
// maxmemory 256mb
// maxmemory-policy allkeys-lru
```

### Rate limiting (Laravel):
```php
// Laravel built-in rate limiter
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function ($request) {
    return Limit::perMinute(60)->by($request->user()->id);
});

// Route-da:
Route::middleware('throttle:api')->group(function () {
    // routes
});

// Custom rate limiter:
if (RateLimiter::tooManyAttempts('login:' . $email, 5)) {
    throw new ThrottleRequestsException();
}
RateLimiter::hit('login:' . $email, 60);
```

### Custom cache driver:
```php
class LRUCacheStore implements \Illuminate\Contracts\Cache\Store
{
    private LRUCache $cache;

    public function get($key) { return $this->cache->get($key); }
    public function put($key, $value, $seconds) { $this->cache->put($key, $value); return true; }
    // ... diger metodlar
}
```

### Circular buffer for logging:
```php
// Son N log entry-ni saxla
class LogBuffer
{
    private CircularBuffer $buffer;

    public function __construct(int $capacity = 1000)
    {
        $this->buffer = new CircularBuffer($capacity);
    }

    public function log(string $message): void
    {
        $this->buffer->enqueue([time(), $message]);
    }

    public function recent(int $n): array
    {
        // Son n log-u qaytar
    }
}
```

### Queue job worker:
```php
// Laravel queue circular buffer prinsipi ile işləyir
Queue::push(new ProcessOrder($order));

// Workers orders-ı FIFO order-da götürür
// Priority queue isə rate limiter ile birləşə bilər
```

### Real nümunələr:
1. **Session storage** - Redis-də LRU eviction
2. **API rate limiting** - bütün SaaS məhsullarda
3. **Database query cache** - MySQL-in query cache-i LRU-dur
4. **CDN edge cache** - CloudFlare LRU/LFU-ya əsaslanır
5. **Autocomplete** - Trie ile search suggestions
6. **Undo/Redo** - Stack-based design
7. **Event streaming** - Circular buffer for metrics
8. **Notification feeds** - Twitter-like design
