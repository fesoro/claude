# Optimistic Locking & Version Columns

> **Seviyye:** Intermediate ⭐⭐

## Concurrency Problem

Iki user eyni anda eyni row-u update edir. Ne olur?

```
T=0: User A: SELECT product (stock=10, price=100)
T=1: User B: SELECT product (stock=10, price=100)
T=2: User A: UPDATE price = 120
T=3: User B: UPDATE price = 90  ← User A-nin deyisikliyi itdi! (Lost Update)
```

Bu **Lost Update** problemidir. Hell yollari:

| Approach | Mexanizm | Performance | Ne vaxt |
|----------|----------|-------------|---------|
| **Pessimistic** | `SELECT ... FOR UPDATE` lock | Yavas (lock wait) | Yuksek konflikt |
| **Optimistic** | Version column check | Suretli | Asagi konflikt |
| **Last-write-wins** | Hec bir kontrol | Suretli (data itir) | Audit/log |

---

## Optimistic vs Pessimistic - Derin Muqayise

### Pessimistic Locking

"Sehv olacaq" deye onceden lock al:

```sql
START TRANSACTION;

-- Row-u lock et (digerleri gozleyir)
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- Hesabla, deyisdir
UPDATE products SET stock = stock - 1 WHERE id = 1;

COMMIT;  -- Lock aciq olunur
```

**Plus:**
- Conflict olmaz - lock al, isle, burax
- Sade ve qayda ile

**Minus:**
- Lock baglar - diger transaction-lar gozleyir
- Deadlock riski
- Long transaction = uzun lock = throughput azalir
- Distributed system-de lock difficult

### Optimistic Locking

"Sehv olmayacaq" deye gozle, amma yoxla:

```sql
-- 1. Read (lock yox)
SELECT id, name, price, version FROM products WHERE id = 1;
-- Returns: id=1, version=5

-- 2. Calculate locally
new_price = 120

-- 3. Update with version check
UPDATE products 
SET price = 120, version = version + 1
WHERE id = 1 AND version = 5;

-- 4. Affected rows yoxla
-- 1 row → ugurlu
-- 0 row → conflict! Basqa kim deyisdirib, version 5 deyil indi
```

**Plus:**
- Lock yoxdur - throughput yuksek
- Distributed system-de iseleyir (REST API ucun ideal)
- Read-heavy workload-da idealdir

**Minus:**
- Conflict bas verende retry lazim
- High-contention scenarios-da cox retry
- Application code daha murekkeb

---

## Version Column Pattern

### Schema

```sql
CREATE TABLE products (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    price DECIMAL(10, 2),
    stock INT,
    version INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Migration (Laravel)
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->integer('stock');
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

### Manual Implementation

```php
class ProductService
{
    public function updatePrice(int $productId, float $newPrice): void
    {
        $product = Product::findOrFail($productId);
        $oldVersion = $product->version;

        $affected = DB::table('products')
            ->where('id', $productId)
            ->where('version', $oldVersion)
            ->update([
                'price' => $newPrice,
                'version' => $oldVersion + 1,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw new OptimisticLockException(
                "Product {$productId} was modified by another process"
            );
        }
    }
}
```

### Eloquent Sade Implementation

```php
class Product extends Model
{
    public function updateWithVersion(array $data): bool
    {
        $version = $this->version;
        $data['version'] = $version + 1;

        $affected = static::where('id', $this->id)
            ->where('version', $version)
            ->update($data);

        if ($affected === 0) {
            throw new OptimisticLockException();
        }

        $this->fill($data);
        $this->version = $version + 1;

        return true;
    }
}

// Istifade
$product = Product::find(1);
$product->price = 120;
$product->updateWithVersion(['price' => 120]);
```

---

## updated_at as Version - Niye Tehlukeli?

Bezi developerlar version column yerine `updated_at` istifade edir:

```sql
UPDATE products 
SET price = 120, updated_at = NOW()
WHERE id = 1 AND updated_at = '2026-04-24 10:30:15.123';
```

**Problemler:**

1. **Timestamp granularity** - 2 update eyni millisaniyede ola biler:
```
T=10:30:15.001: User A: UPDATE
T=10:30:15.001: User B: UPDATE  ← Eyni timestamp!
```

2. **Clock skew** - distributed system-de clock-lar sinxron deyil

3. **NOW() vs application time** - aplikasiya `Carbon::now()` set edir, MySQL `NOW()` ferqli ola biler

4. **Microsecond precision** - bezi MySQL versiyalari 1 saniye precision (microsecond yox)

```sql
-- MySQL 5.7+ microsecond support
created_at TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6)
-- Hele de garanti deyil
```

**Qayda:** Optimistic locking ucun **dedicated INTEGER version column** istifade et.

---

## Retry Pattern

Conflict bas verende automatik retry:

```php
class OptimisticRetry
{
    public static function execute(callable $operation, int $maxAttempts = 3): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                return $operation();
            } catch (OptimisticLockException $e) {
                $lastException = $e;
                $attempts++;
                
                // Exponential backoff with jitter
                $delay = (2 ** $attempts) * 100 + random_int(0, 100);
                usleep($delay * 1000);
            }
        }

        throw $lastException;
    }
}

// Istifade
OptimisticRetry::execute(function () use ($productId, $newPrice) {
    $product = Product::findOrFail($productId);
    $product->updateWithVersion(['price' => $newPrice]);
}, maxAttempts: 5);
```

> **Diqqet:** Retry-da `findOrFail` her defe yeni read edir - en son version-i alir. Eks halda eyni version-le tekrar 0 affected row alirsan.

### Retry Limits

```
Conflict rate 5% → 1 retry kifayetdir
Conflict rate 30% → 3-5 retry, exponential backoff
Conflict rate 80% → Optimistic locking SEHV pattern, pessimistic ist!
```

---

## Conflict Resolution Strategies

Conflict bas verende ne etmek?

### 1. Last-Write-Wins (LWW)

Sonra gelen qazansin:

```php
try {
    $product->updateWithVersion($data);
} catch (OptimisticLockException $e) {
    // Sadece reload + retry, son deyisikliyi imza et
    $product->refresh();
    $product->updateWithVersion($data);
}
```

> **Risk:** User-in deyisikliklerinin ustuncen yazir. Audit/log scenarios-da OK, financial data-da yox.

### 2. Reject (User-e qaytar)

```php
try {
    $product->updateWithVersion($data);
} catch (OptimisticLockException $e) {
    return response()->json([
        'error' => 'This record was modified by another user',
        'current_version' => Product::find($product->id),
    ], 409);  // HTTP 409 Conflict
}
```

User reload eder, deyisikliklerini yeniden eder.

### 3. Merge (Three-way merge)

Git kimi - original, user-A version, user-B version - merge et:

```php
$original = $this->snapshot;     // User read edende
$theirs = Product::find($id);    // Indi DB-deki
$mine = $userInput;              // User-in deyisikliyi

$merged = [];
foreach ($mine as $field => $value) {
    if ($original[$field] === $theirs[$field]) {
        // Bu sahede konflikt yox
        $merged[$field] = $value;
    } elseif ($mine[$field] === $theirs[$field]) {
        // Eyni deyisiklik
        $merged[$field] = $value;
    } else {
        // Conflict bu sahede - user-e gosterer
        throw new MergeConflictException($field, $original, $theirs, $mine);
    }
}
```

Murekkeb amma collaborative editing-de (Google Docs kimi) lazimdir.

---

## ETag / If-Match HTTP Integration

REST API-de optimistic locking-i ETag header ile imza et:

```php
// GET endpoint
public function show(Product $product)
{
    return response()
        ->json($product)
        ->header('ETag', '"' . md5($product->updated_at . $product->version) . '"');
}

// PATCH endpoint
public function update(Request $request, Product $product)
{
    $clientETag = $request->header('If-Match');
    $currentETag = '"' . md5($product->updated_at . $product->version) . '"';

    if ($clientETag !== $currentETag) {
        return response()->json([
            'error' => 'Resource modified',
        ], 412);  // HTTP 412 Precondition Failed
    }

    $product->updateWithVersion($request->validated());
    
    return response()->json($product);
}
```

**Client request:**
```
PATCH /api/products/1
If-Match: "abc123def456"
Content-Type: application/json

{"price": 120}
```

Sender ETag deyiserse - DB deyismis demekdir → 412 qaytar.

---

## Real Example: E-commerce Inventory

```php
class InventoryService
{
    public function reserveStock(int $productId, int $quantity): void
    {
        OptimisticRetry::execute(function () use ($productId, $quantity) {
            $product = Product::findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new InsufficientStockException();
            }

            $affected = DB::table('products')
                ->where('id', $productId)
                ->where('version', $product->version)
                ->where('stock', '>=', $quantity)  // double-check!
                ->update([
                    'stock' => $product->stock - $quantity,
                    'version' => $product->version + 1,
                ]);

            if ($affected === 0) {
                throw new OptimisticLockException();
            }
        }, maxAttempts: 3);
    }
}
```

**Niye double-check `stock >= quantity` UPDATE-de?**

User A retry edir. T1-de stock=10 oxudu. T2-de basqa biri 8 ald. Indi stock=2. User A retry-da version yoxlayir - yeni version oxuyur, stock=2 gorur, exception atir. Amma `stock >= quantity` UPDATE-de garantili tekrar yoxlama edir.

---

## Ne vaxt Pessimistic Lock daha yaxsi?

```
Conflict rate: ?
- < 5%   → Optimistic (cox suretli)
- 5-30%  → Optimistic + retry
- 30-70% → Test et, ola biler pessimistic
- > 70%  → Pessimistic (retry storm olar)

Critical financial transaction?
- Bank transfer, payment       → Pessimistic + serializable isolation
- Comment likes, view counter  → Optimistic ya counter pattern

Long transaction?
- Lock baglamaq olmaz → Optimistic
```

```php
// PESSIMISTIC: Bank transfer - garantili
DB::transaction(function () use ($from, $to, $amount) {
    // Hemise sira ile lock al (deadlock olmasin)
    [$first, $second] = $from->id < $to->id ? [$from, $to] : [$to, $from];
    
    $first->lockForUpdate()->refresh();
    $second->lockForUpdate()->refresh();
    
    if ($from->balance < $amount) {
        throw new InsufficientFundsException();
    }
    
    $from->decrement('balance', $amount);
    $to->increment('balance', $amount);
});
```

---

## High-Contention Scenarios

### Counter (likes, views)

`UPDATE counters SET value = value + 1` - optimistic lock burada COX retry yaradir. Alternativler:

```sql
-- 1. Atomic increment (lock yox, version yox)
UPDATE posts SET like_count = like_count + 1 WHERE id = ?;

-- 2. Sharded counter (high contention ucun)
-- 1 row yerine 10 row, app-de RANDOM bir row update et
UPDATE post_likes_shards 
SET count = count + 1 
WHERE post_id = ? AND shard = RAND() * 10;

-- Read: SUM
SELECT SUM(count) FROM post_likes_shards WHERE post_id = ?;

-- 3. Async (Redis-de increment, periodically DB-ye flush)
Redis::incr("post:{$id}:likes");
```

---

## Laravel Packages

```php
// 1. Spatie/laravel-eloquent-optimistic-lock - Spatie ekosistemi
// composer require spatie/laravel-optimistic-locking
class Product extends Model
{
    use HasOptimisticLocking;
}

$product->update(['price' => 120]);
// Avtomatik version check, exception throw
```

Manual implementation adeten kifayetdir - package overhead-i evez etmir.

---

## Optimistic Locking Pitfalls

### 1. Forgotten version increment

```php
// PIS - version artmir
DB::table('products')->where('id', 1)->where('version', 5)->update(['price' => 120]);
// Sonraki update yene version=5 yoxlayir → infinite loop!

// DOGRU
DB::table('products')->where('id', 1)->where('version', 5)
    ->update(['price' => 120, 'version' => 6]);
```

### 2. Hidden updates

```php
// Trigger / observer / cascade update version-i artirir
// Application bilmir, retry storm yaranir
```

### 3. Read after write

```php
// Update etdin, version artdi, amma model object hele kohne version
$product->update(['version' => 6, ...]);
$product->version === 5;  // Stale!

// Hell: refresh()
$product->refresh();
```

---

## Interview suallari

**Q: Optimistic vs Pessimistic locking - ne vaxt hansi?**
A: Optimistic - asagi konflikt rate (read-heavy, distributed REST API). Pessimistic - yuksek konflikt, kritik consistency (financial transaction). Optimistic suretli amma retry lazimdir, pessimistic garantili amma lock baglar. Conflict rate 30%+ olduqda pessimistic-e kec.

**Q: Niye updated_at-i version kimi istifade etmek pisdir?**
A: 1) Timestamp granularity - eyni saniyede 2 update ola biler. 2) Clock skew - distributed system-de clock fərqlidir. 3) Application/DB time mismatch. 4) MySQL precision varies. Dedicated INTEGER version column hemise daha etibarlidir.

**Q: Optimistic lock conflict olduqda ne etmeli?**
A: 3 strategiya: 1) **Retry** (exponential backoff) - automatik tekrarlama, async job-larda yaxsi. 2) **Reject** (HTTP 409/412) - user-e bildir, reload eler. 3) **Merge** (three-way) - Git kimi, collaborative editing-de. Choice business logic-den asilidir.

**Q: ETag/If-Match nece optimistic locking-e baglidir?**
A: HTTP-de optimistic locking-in standart yoludur. GET-de ETag (resource hash) qaytar. Client PATCH zamani If-Match header-de ETag gonder. ETag uygundursa - update icaze ver, deyilse - 412 Precondition Failed. Stateless API-da version column-un HTTP-yə cixarisi.

**Q: High-contention counter (post likes) ucun optimistic lock niye sehvdir?**
A: Cox concurrent user eyni anda counter-i artirmaq isteyir - cox conflict, cox retry, throughput cokur. Hell: 1) Atomic `value = value + 1` (DB tek statement, lock-free). 2) Sharded counter (10 row, random update). 3) Redis-de async increment, periodic flush. Optimistic lock unique field-leri qoruyan critical update-ler ucun, counter ucun yox.
