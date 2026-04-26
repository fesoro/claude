# Coding Challenges (Senior)

## 1. Palindrome yoxla

```php
function isPalindrome(string $str): bool {
    $str = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
    return $str === strrev($str);
}

isPalindrome("A man, a plan, a canal: Panama"); // true
isPalindrome("hello"); // false
```

---

## 2. Array-dan dublikatları sil (unique saxla, sıranı qoru)

```php
function removeDuplicates(array $arr): array {
    return array_values(array_unique($arr));
}

// Associative array üçün (key-ə görə unique)
function uniqueByKey(array $items, string $key): array {
    $seen = [];
    return array_filter($items, function ($item) use ($key, &$seen) {
        if (in_array($item[$key], $seen)) return false;
        $seen[] = $item[$key];
        return true;
    });
}
```

---

## 3. İki sıralanmış array-ı birləşdir (merge sorted arrays)

```php
function mergeSorted(array $a, array $b): array {
    $result = [];
    $i = $j = 0;

    while ($i < count($a) && $j < count($b)) {
        if ($a[$i] <= $b[$j]) {
            $result[] = $a[$i++];
        } else {
            $result[] = $b[$j++];
        }
    }

    while ($i < count($a)) $result[] = $a[$i++];
    while ($j < count($b)) $result[] = $b[$j++];

    return $result;
}

mergeSorted([1, 3, 5], [2, 4, 6]); // [1, 2, 3, 4, 5, 6]
```

---

## 4. FizzBuzz (klassik)

```php
function fizzBuzz(int $n): array {
    $result = [];
    for ($i = 1; $i <= $n; $i++) {
        $result[] = match(true) {
            $i % 15 === 0 => 'FizzBuzz',
            $i % 3 === 0  => 'Fizz',
            $i % 5 === 0  => 'Buzz',
            default        => $i,
        };
    }
    return $result;
}
```

---

## 5. Anagram yoxla

```php
function isAnagram(string $a, string $b): bool {
    $normalize = fn(string $s) => count_chars(strtolower(preg_replace('/\s+/', '', $s)), 1);
    return $normalize($a) === $normalize($b);
}

isAnagram("listen", "silent"); // true
isAnagram("hello", "world");  // false
```

---

## 6. Flatten nested array

```php
function flatten(array $arr): array {
    $result = [];
    array_walk_recursive($arr, function ($value) use (&$result) {
        $result[] = $value;
    });
    return $result;
}

flatten([1, [2, [3, 4]], [5, 6]]); // [1, 2, 3, 4, 5, 6]

// Depth limit ilə
function flattenDepth(array $arr, int $depth = 1): array {
    $result = [];
    foreach ($arr as $item) {
        if (is_array($item) && $depth > 0) {
            $result = array_merge($result, flattenDepth($item, $depth - 1));
        } else {
            $result[] = $item;
        }
    }
    return $result;
}
```

---

## 7. Balanced brackets yoxla

```php
function isBalanced(string $str): bool {
    $stack = [];
    $pairs = ['(' => ')', '[' => ']', '{' => '}'];

    for ($i = 0; $i < strlen($str); $i++) {
        $char = $str[$i];
        if (isset($pairs[$char])) {
            $stack[] = $pairs[$char];
        } elseif (in_array($char, $pairs)) {
            if (empty($stack) || array_pop($stack) !== $char) {
                return false;
            }
        }
    }

    return empty($stack);
}

isBalanced("({[]})");  // true
isBalanced("({[})");   // false
isBalanced("((())");   // false
```

---

## 8. Rate Limiter implement et

```php
class RateLimiter {
    private array $requests = []; // key => [timestamps]

    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool {
        $now = time();
        $windowStart = $now - $windowSeconds;

        // Köhnə request-ləri sil
        $this->requests[$key] = array_filter(
            $this->requests[$key] ?? [],
            fn($ts) => $ts > $windowStart
        );

        if (count($this->requests[$key]) >= $maxAttempts) {
            return false;
        }

        $this->requests[$key][] = $now;
        return true;
    }
}

// Redis ilə production version
class RedisRateLimiter {
    public function attempt(string $key, int $max, int $windowSeconds): bool {
        $current = Redis::incr($key);
        if ($current === 1) {
            Redis::expire($key, $windowSeconds);
        }
        return $current <= $max;
    }
}
```

---

## 9. Simple Cache implement et (LRU)

```php
class LruCache {
    private array $cache = [];
    private array $order = []; // access order

    public function __construct(private int $capacity) {}

    public function get(string $key): mixed {
        if (!array_key_exists($key, $this->cache)) {
            return null;
        }

        // Move to end (most recently used)
        $this->order = array_diff($this->order, [$key]);
        $this->order[] = $key;

        return $this->cache[$key];
    }

    public function put(string $key, mixed $value): void {
        if (array_key_exists($key, $this->cache)) {
            $this->order = array_diff($this->order, [$key]);
        } elseif (count($this->cache) >= $this->capacity) {
            $evict = array_shift($this->order);
            unset($this->cache[$evict]);
        }

        $this->cache[$key] = $value;
        $this->order[] = $key;
    }
}
```

---

## 10. Laravel-specific: Custom Collection macro yaz

```php
// AppServiceProvider::boot()
Collection::macro('toCsv', function (array $headers = []): string {
    $output = fopen('php://temp', 'r+');

    if ($headers) {
        fputcsv($output, $headers);
    }

    $this->each(function ($row) use ($output) {
        fputcsv($output, (array) $row);
    });

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return $csv;
});

// İstifadə
$csv = User::all()->map(fn ($u) => ['name' => $u->name, 'email' => $u->email])
    ->toCsv(['Name', 'Email']);
```

---

## 11. Debounce / Throttle implement et

```php
class Throttle {
    private array $lastExecution = [];

    public function __invoke(string $key, int $intervalMs, Closure $callback): mixed {
        $now = hrtime(true) / 1e6; // milliseconds
        $last = $this->lastExecution[$key] ?? 0;

        if ($now - $last < $intervalMs) {
            return null;
        }

        $this->lastExecution[$key] = $now;
        return $callback();
    }
}
```

---

## 12. Binary Search

```php
function binarySearch(array $sorted, int $target): int {
    $low = 0;
    $high = count($sorted) - 1;

    while ($low <= $high) {
        $mid = intdiv($low + $high, 2);

        if ($sorted[$mid] === $target) return $mid;
        if ($sorted[$mid] < $target) $low = $mid + 1;
        else $high = $mid - 1;
    }

    return -1;
}

binarySearch([1, 3, 5, 7, 9, 11], 7); // 3 (index)
```

---

## 13. Simple Dependency Injection Container yaz

```php
class Container {
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, Closure $factory): void {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, Closure $factory): void {
        $this->bindings[$abstract] = function () use ($abstract, $factory) {
            if (!isset($this->instances[$abstract])) {
                $this->instances[$abstract] = $factory($this);
            }
            return $this->instances[$abstract];
        };
    }

    public function make(string $abstract): mixed {
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        // Auto-resolve via reflection
        $reflector = new ReflectionClass($abstract);
        $constructor = $reflector->getConstructor();

        if (!$constructor) return new $abstract();

        $params = array_map(function (ReflectionParameter $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $this->make($type->getName());
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new Exception("Cannot resolve {$param->getName()}");
        }, $constructor->getParameters());

        return $reflector->newInstanceArgs($params);
    }
}

// İstifadə
$container = new Container();
$container->bind(LoggerInterface::class, fn() => new FileLogger('/tmp/app.log'));
$container->singleton(Database::class, fn() => new Database('localhost', 'app'));

$logger = $container->make(LoggerInterface::class);
$service = $container->make(UserService::class); // auto-resolve
```
