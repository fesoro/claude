# Optimistic Locking & Concurrent Edit Conflict Detection (Senior)

## Problem Təsviri

E-commerce, CMS, ERP kimi multi-user sistemlərdə eyni resource-u eyni vaxtda bir neçə istifadəçinin redaktə etməsi ciddi data bütövlüyü probleminə yol açır. Bu problemi "lost update" (itirilmiş yeniləmə) adlandırırıq.

Real ssenari: iki manager eyni məhsulu eyni anda redaktə edir.

```
Manager A → product açır (price: $100, description: "Old desc")
Manager B → eyni product açır (price: $100, description: "Old desc")

Manager A → price-ı $120-yə dəyişir → Save → DB: price=$120 ✓
Manager B → description-ı dəyişir  → Save → DB: price=$100, description="New" ✗

Nəticə: Manager A-nın $120 price dəyişikliyi itdi! DB-də yenə $100 var.
```

```
Timeline:
t=0  A reads:  {price: 100, description: "Old"}
t=0  B reads:  {price: 100, description: "Old"}
t=1  A writes: {price: 120, description: "Old"}  ← DB updated
t=2  B writes: {price: 100, description: "New"}  ← A's change overwritten!
```

### Problem niyə yaranır?

HTTP stateless-dir — hər request öz başına bir əməliyyatdır. Server Request B icra edəndə Request A-nın data oxuduğundan xəbəri yoxdur. Standart `UPDATE` sorğusu yalnız `id`-yə görə tapır və üzərinə yazır — **last write wins** davranışı. Conflict detection yoxdur.

### Nəticələri

- **İş məlumatları itirilir** — əməkdaşın saatlarla gördüyü iş silinir
- **Audit trail pozulur** — kim nə vaxt nə dəyişdi anlaşılmır
- **User etibarı sarsılır** — "sistem mənim dəyişiklikləri saxlamır" şikayəti
- **Maliyyə zərərləri** — price, stock kimi kritik sahələrdə lost update qiymətlidir

---

## Həll 1: Optimistic Locking (Version Column)

### Konsept

Optimistic locking — conflict-in baş verməyəcəyini "umur" (optimistic), amma baş verdikdə aşkarlayır. Hər row-a `version` sütunu əlavə olunur. Client oxuduğu version-ı geri göndərir; server `WHERE id = ? AND version = ?` ilə yeniləyir — başqa biri dəyişibsə, 0 row affected olur → conflict!

```
Manager A oxuyur: {id: 1, price: 100, version: 5}
Manager B oxuyur: {id: 1, price: 100, version: 5}

Manager A yeniləyir: UPDATE products SET price=120, version=6 WHERE id=1 AND version=5
  → 1 row affected ✓ (version hələ 5-dir)

Manager B yeniləyir: UPDATE products SET price=100, version=6 WHERE id=1 AND version=5
  → 0 rows affected ✗ (version artıq 6-dır) → ConflictException!
```

### Migration

```php
// database/migrations/2024_01_01_add_version_to_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Default 1 — ilk yaradılanda version=1 başlayır
            $table->unsignedInteger('version')->default(1)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
```

### Exception

```php
// app/Exceptions/ConflictException.php
namespace App\Exceptions;

use RuntimeException;

class ConflictException extends RuntimeException
{
    public function __construct(
        string $message = 'Bu record başqa biri tərəfindən dəyişdirilib.',
        public readonly ?array $currentData = null
    ) {
        parent::__construct($message);
    }
}
```

### Model — Optimistic Locking Metodu

*Bu kod version column ilə conflict detection edən Eloquent model metodunu göstərir:*

```php
// app/Models/Product.php
namespace App\Models;

use App\Exceptions\ConflictException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = [
        'name',
        'price',
        'description',
        'stock',
        'version',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Optimistic locking ilə yeniləmə.
     * Client-in gördüyü version ilə DB-dəki version uyğun gəlməsə → conflict.
     *
     * @throws ConflictException
     */
    public function optimisticUpdate(array $attributes, int $expectedVersion): void
    {
        $newVersion = $expectedVersion + 1;

        $affected = DB::table('products')
            ->where('id', $this->id)
            ->where('version', $expectedVersion)  // ← əsas şərt
            ->update(array_merge($attributes, [
                'version' => $newVersion,
                'updated_at' => now(),
            ]));

        if ($affected === 0) {
            // Ya record silinib, ya da başqası dəyişdirib
            $current = static::find($this->id);

            throw new ConflictException(
                message: 'Bu məhsul başqa bir istifadəçi tərəfindən dəyişdirilib. Zəhmət olmasa yeniləyin.',
                currentData: $current?->toArray()
            );
        }

        // Model-i yenilənmiş vəziyyətə gətir
        $this->fill(array_merge($attributes, ['version' => $newVersion]));
        $this->syncOriginal();
    }

    /**
     * Silmədə də version yoxlaması.
     *
     * @throws ConflictException
     */
    public function optimisticDelete(int $expectedVersion): void
    {
        $affected = DB::table('products')
            ->where('id', $this->id)
            ->where('version', $expectedVersion)
            ->delete();

        if ($affected === 0) {
            throw new ConflictException('Silinmə uğursuz: record dəyişdirilmiş və ya artıq silinib.');
        }
    }
}
```

### Service Layer

*Bu kod optimistic locking-i service layer-də encapsulate edərək retry mexanizmi ilə birlikdə göstərir:*

```php
// app/Services/ProductService.php
namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductService
{
    /**
     * Məhsulu yenilə — optimistic locking ilə.
     *
     * @throws ConflictException
     */
    public function update(Product $product, array $attributes, int $version): Product
    {
        Log::info('Product update attempt', [
            'product_id' => $product->id,
            'expected_version' => $version,
            'attributes' => array_keys($attributes),
        ]);

        $product->optimisticUpdate($attributes, $version);

        Log::info('Product updated successfully', [
            'product_id' => $product->id,
            'new_version' => $product->version,
        ]);

        return $product->fresh();
    }

    /**
     * Yüksək contention ssenarisində retry ilə yeniləmə.
     * Hər cəhddə DB-dən fresh data oxuyub callback-ı yenidən çağırır.
     *
     * @param callable $updateCallback fn(Product): array — yeni attributes qaytarır
     * @throws ConflictException — maxRetries bitdikdə
     */
    public function updateWithRetry(
        Product $product,
        callable $updateCallback,
        int $maxRetries = 3
    ): Product {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {
                $current = $product->fresh(); // Hər cəhddə fresh oxu
                $attributes = $updateCallback($current);
                $current->optimisticUpdate($attributes, $current->version);
                return $current->fresh();

            } catch (ConflictException $e) {
                $attempts++;

                Log::warning('Optimistic locking conflict — retrying', [
                    'product_id' => $product->id,
                    'attempt' => $attempts,
                    'max_retries' => $maxRetries,
                ]);

                if ($attempts >= $maxRetries) {
                    throw $e;
                }

                // Exponential backoff: 50ms, 100ms, 200ms
                usleep(50_000 * (2 ** ($attempts - 1)));
            }
        }

        throw new ConflictException('Maksimum cəhd sayına çatıldı.');
    }
}
```

### Controller

*Bu kod optimistic locking-i HTTP endpoint-ə inteqrasiya edən controller-i göstərir:*

```php
// app/Http/Controllers/ProductController.php
namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    /**
     * Məhsul məlumatlarını oxu — version daxil olmaqla.
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }

    /**
     * Məhsulu yenilə — client version göndərməlidir.
     *
     * Request body: { name, price, description, version }
     * version field — client-in oxuduğu version.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'price'       => 'sometimes|numeric|min:0',
            'description' => 'sometimes|string',
            'stock'       => 'sometimes|integer|min:0',
            'version'     => 'required|integer|min:1',
        ]);

        $version = $validated['version'];
        $attributes = collect($validated)->except('version')->toArray();

        try {
            $updated = $this->productService->update($product, $attributes, $version);

            return response()->json([
                'message' => 'Məhsul uğurla yeniləndi.',
                'data'    => new ProductResource($updated),
            ]);

        } catch (ConflictException $e) {
            // 409 Conflict — client yeni data ilə yenidən cəhd etməlidir
            return response()->json([
                'error'   => 'CONFLICT',
                'message' => $e->getMessage(),
                'current' => $e->currentData
                    ? new ProductResource(new Product($e->currentData))
                    : null,
            ], 409);
        }
    }
}
```

### API Resource — Version Daxil

```php
// app/Http/Resources/ProductResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'price'       => $this->price,
            'description' => $this->description,
            'stock'       => $this->stock,
            'version'     => $this->version,  // ← client bu dəyəri saxlamalıdır
            'updated_at'  => $this->updated_at->toIso8601String(),
        ];
    }
}
```

---

## Həll 2: ETag + If-Match HTTP Headers

### Konsept

HTTP standartı özü conflict detection üçün `ETag` / `If-Match` mexanizmi təqdim edir. Server response-a `ETag` header-i əlavə edir (resource-un hash-i). Client yeniləmə zamanı `If-Match` header-ini göndərir. Server hash-lər uyğun gəlmirsə `412 Precondition Failed` qaytarır.

```
GET /api/products/1
← 200 OK
   ETag: "a3f5c9d2"
   {id: 1, price: 100, ...}

PATCH /api/products/1
  If-Match: "a3f5c9d2"    ← client saxladığı hash-i göndərir
  {price: 120}

→ Uyğun gəlirsə:    200 OK (yeniləndi)
→ Uyğun gəlmirsə:   412 Precondition Failed (başqası dəyişdirib)
```

### ETag Middleware — GET Cavablarına ETag Əlavə Et

*Bu kod GET sorğularına ETag header-i əlavə edən və şərtli request-ləri idarə edən middleware-i göstərir:*

```php
// app/Http/Middleware/ETagMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Yalnız GET sorğularına ETag əlavə edirik
        if (!$request->isMethod('GET') || !$response->isSuccessful()) {
            return $response;
        }

        $content = $response->getContent();
        $etag = '"' . md5($content) . '"';

        $response->setEtag($etag, weak: false);

        // If-None-Match yoxlaması — 304 Not Modified
        $clientEtags = $request->getETags();
        if (!empty($clientEtags) && in_array($etag, $clientEtags)) {
            return response('', 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'private, must-revalidate',
            ]);
        }

        $response->headers->set('Cache-Control', 'private, must-revalidate');

        return $response;
    }
}
```

### If-Match Validation — Write Əməliyyatları

*Bu kod PATCH/PUT/DELETE sorğularında If-Match header-ini yoxlayan middleware-i göstərir:*

```php
// app/Http/Middleware/IfMatchMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IfMatchMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Yalnız state-dəyişdirən method-lara tətbiq et
        if (!in_array($request->method(), ['PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $ifMatch = $request->header('If-Match');

        if (empty($ifMatch)) {
            // If-Match tələb olunur — göndərilməyibsə 428 Precondition Required
            return response()->json([
                'error'   => 'PRECONDITION_REQUIRED',
                'message' => 'If-Match header tələb olunur. Zəhmət olmasa resurs-u əvvəlcə oxuyun.',
            ], 428);
        }

        // Cari resource-u tap və ETag-ını hesabla
        // Route model binding istifadə edirik: /products/{product}
        $route = $request->route();
        $model = $route?->parameter('product'); // ProductController::update-dəki $product

        if (!$model) {
            return $next($request);
        }

        $currentEtag = '"' . md5(json_encode($model->toArray())) . '"';

        // If-Match uyğun gəlmirsə 412 qaytarırıq
        if ($ifMatch !== '*' && $ifMatch !== $currentEtag) {
            return response()->json([
                'error'   => 'PRECONDITION_FAILED',
                'message' => 'Resurs dəyişdirilib. Zəhmət olmasa son versiyonu oxuyun.',
                'current_etag' => $currentEtag,
            ], 412);
        }

        return $next($request);
    }
}
```

### Middleware Qeydiyyatı

```php
// bootstrap/app.php (Laravel 11)
use App\Http\Middleware\ETagMiddleware;
use App\Http\Middleware\IfMatchMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            ETagMiddleware::class,
            IfMatchMiddleware::class,
        ]);
    })
    ->create();
```

### Routes

```php
// routes/api.php
use App\Http\Controllers\ProductController;

Route::prefix('products')->group(function () {
    Route::get('/{product}', [ProductController::class, 'show']);       // ETag əlavə olunur
    Route::patch('/{product}', [ProductController::class, 'update']);   // If-Match yoxlanır
    Route::delete('/{product}', [ProductController::class, 'destroy']); // If-Match yoxlanır
});
```

---

## Həll 3: Pessimistic Locking (SELECT FOR UPDATE)

### Konsept

Pessimistic locking — conflict-in mütləq baş verəcəyini "güman edir" (pessimistic) və resource-u öncədən kilidləyir. Başqa transaction eyni row-u oxumağa çalışanda lock azad olana qədər gözləyir.

```
Transaction A: BEGIN
  SELECT * FROM products WHERE id=1 FOR UPDATE  ← row kilitləndi
  ... business logic (300ms) ...
  UPDATE products SET price=120 WHERE id=1
COMMIT  ← lock azad oldu

Transaction B: BEGIN
  SELECT * FROM products WHERE id=1 FOR UPDATE  ← A bitənə qədər GÖZLƏYIR
  ... (A commit edəndən sonra davam edir, fresh data ilə)
```

### Eloquent ilə Pessimistic Locking

*Bu kod Eloquent-in `lockForUpdate()` metodunu Laravel transaction daxilində istifadəsini göstərir:*

```php
// app/Services/StockService.php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Stock azaltma — pessimistic locking ilə race condition önlənir.
     * Yüksək contention ssenarisində (order placement) istifadə olunur.
     *
     * @throws \RuntimeException
     */
    public function decrementStock(int $productId, int $quantity): Product
    {
        return DB::transaction(function () use ($productId, $quantity) {

            // lockForUpdate() → SELECT ... FOR UPDATE
            // Eyni transaction bitənə qədər başqa sorğular gözləyir
            $product = Product::lockForUpdate()->findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new \RuntimeException(
                    "Kifayət qədər stok yoxdur. Mövcud: {$product->stock}, Tələb: {$quantity}"
                );
            }

            $product->decrement('stock', $quantity);

            return $product->fresh();
        });
    }

    /**
     * Shared lock — oxumaq üçün, amma başqasının yazmasını bloklamır.
     * Report, hesablama kimi hallarda istifadə olunur.
     */
    public function getStockForReport(int $productId): int
    {
        return DB::transaction(function () use ($productId) {
            // sharedLock() → SELECT ... LOCK IN SHARE MODE
            $product = Product::sharedLock()->findOrFail($productId);
            return $product->stock;
        });
    }
}
```

### Timeout Handling — Deadlock Riski

```php
// app/Services/OrderService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class OrderService
{
    public function placeOrder(array $items, int $userId): array
    {
        try {
            return DB::transaction(function () use ($items, $userId) {
                $totalAmount = 0;
                $lockedProducts = [];

                // Bütün məhsulları eyni sırada lock et — deadlock önlənir
                // KRITIK: həmişə eyni sıralamada lock et (id-yə görə ascending)
                $productIds = collect($items)->pluck('product_id')->sort()->values();

                $products = Product::lockForUpdate()
                    ->whereIn('id', $productIds)
                    ->orderBy('id') // Eyni sıra → deadlock yoxdur
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $products[$item['product_id']];

                    if ($product->stock < $item['quantity']) {
                        throw new \RuntimeException(
                            "{$product->name}: kifayət qədər stok yoxdur."
                        );
                    }

                    $product->decrement('stock', $item['quantity']);
                    $totalAmount += $product->price * $item['quantity'];
                }

                $order = Order::create([
                    'user_id' => $userId,
                    'total'   => $totalAmount,
                    'status'  => 'confirmed',
                ]);

                return $order->toArray();
            }, attempts: 3); // Laravel deadlock-da 3 dəfə retry edir

        } catch (QueryException $e) {
            // MySQL error 1205: Lock wait timeout exceeded
            // MySQL error 1213: Deadlock found
            if (in_array($e->getCode(), ['1205', '1213'])) {
                throw new \RuntimeException(
                    'Sistem həddən artıq yüklüdür. Zəhmət olmasa bir az sonra cəhd edin.'
                );
            }
            throw $e;
        }
    }
}
```

---

## Conflict Resolution Strategiyaları

Conflict aşkar edildikdə onu necə həll edəcəyimiz mühüm design qərarıdır:

### Strategiya 1: Reject & Reload (Ən Sadə)

```
Server → 409 Conflict qaytarır
Client → "Bu məhsul başqası tərəfindən dəyişdirilib. Zəhmət olmasa yeniləyin."
User   → Reload → Yeni versiyanı görür → Öz dəyişikliklərini yenidən tətbiq edir
```

**Nə vaxt:** Əksər hallarda bu yetərlidir. Implement etmək asandır. User öz dəyişikliklərini nəzərə alaraq qərar verir.

### Strategiya 2: Diff Göstər & Seçim Tər

```php
// app/Services/ConflictResolverService.php
namespace App\Services;

class ConflictResolverService
{
    /**
     * Client-in dəyişiklikləri ilə cari DB vəziyyətini müqayisə et.
     * User hansı dəyişikliklərin conflict-li olduğunu görür.
     */
    public function buildConflictDiff(
        array $originalData,   // Client-in oxuduğu ilkin data
        array $clientChanges,  // Client-in etmək istədiyi dəyişikliklər
        array $currentData     // DB-dəki cari vəziyyət
    ): array {
        $conflicts = [];
        $safe = [];

        foreach ($clientChanges as $field => $clientValue) {
            $originalValue = $originalData[$field] ?? null;
            $currentValue = $currentData[$field] ?? null;

            if ($currentValue !== $originalValue) {
                // Bu sahə başqası tərəfindən dəyişdirilib — conflict!
                $conflicts[$field] = [
                    'original' => $originalValue,
                    'yours'    => $clientValue,
                    'current'  => $currentValue,
                ];
            } else {
                // Bu sahə dəyişdirilməyib — təhlükəsiz tətbiq edilə bilər
                $safe[$field] = $clientValue;
            }
        }

        return [
            'has_conflicts' => !empty($conflicts),
            'conflicts'     => $conflicts,  // User qərar verməlidir
            'safe_changes'  => $safe,       // Avtomatik tətbiq edilə bilər
        ];
    }
}
```

**Response nümunəsi:**

```json
{
  "error": "CONFLICT",
  "diff": {
    "has_conflicts": true,
    "conflicts": {
      "price": {
        "original": 100,
        "yours": 120,
        "current": 95
      }
    },
    "safe_changes": {
      "description": "Yeni məhsul təsviri"
    }
  },
  "current_version": 7
}
```

### Strategiya 3: Field-level Auto-merge

Eyni sahə conflict-li deyilsə, avtomatik merge edilir. Yalnız eyni sahədə conflict varsa user-dən qərar alınır.

```php
public function autoMerge(array $original, array $client, array $current): array
{
    $merged = $current; // Bazamız: cari DB vəziyyəti

    foreach ($client as $field => $value) {
        $originalValue = $original[$field] ?? null;

        // Client bu sahəni dəyişdirib AMMa başqası da dəyişdirməyibsə → tətbiq et
        if ($value !== $originalValue && $current[$field] === $originalValue) {
            $merged[$field] = $value;
        }
        // Əgər hər ikisi eyni sahəni dəyişdiribsə → conflict (manual resolve lazım)
    }

    return $merged;
}
```

**Nə vaxt istifadə:** Document editor, wiki, text editor kimi hallarda. Fərqli sahələr üçün parallel edit çox olduqda.

### Strategiya 4: Last-Write-Wins (Yalnız Şüurlu Qərar ilə)

```php
// YALNIZ həqiqətən qəbul olunabilərsə istifadə et
// Məs: user status (online/offline), son görünüş vaxtı
$user->update(['last_seen_at' => now()]); // version yoxlamadan
```

**Nə vaxt:** Məlumatın itirilməsi qəbul oluna bilən hallarda — analytics, activity tracking, non-critical metadata.

---

## Trade-off Müqayisəsi

| Yanaşma | Nə vaxt | Üstünlük | Risk | DB Yükü |
|---------|---------|---------|------|---------|
| **Optimistic Locking** | Aşağı/Orta contention | Lock yoxdur, performans yüksək | Yüksək contentionda çox retry | Minimal |
| **Pessimistic Locking** | Yüksək contention, kritik əməliyyat | Conflict baş vermir | Deadlock riski, throughput azalır | Yüksək |
| **ETag + If-Match** | REST API, HTTP caching | HTTP standartı, caching bonus | Client tərəfin düzgün implement etməsi | Minimal |
| **Last-Write-Wins** | Non-critical data | Ən sadə, sürətli | Data itkisi | Sıfır |
| **Auto-merge** | Document/text editing | Ən az conflict, user dostu | Complexity, sahə spesifik məntiq | Minimal |

---

## Anti-patternlər

**1. Last-write-wins — heç bir conflict detection yoxdur**

```php
// YANLIŞ: version yoxlaması olmadan sadə update
$product->update($request->only(['price', 'description']));

// DÜZGÜN: client version-ı göndərməlidir
$product->optimisticUpdate($request->only(['price', 'description']), $request->integer('version'));
```

**2. Pessimistic lock-u transaction xaricində tutmaq**

```php
// YANLIŞ: lock transaction xaricindədir — heç işləmir!
$product = Product::lockForUpdate()->find(1);
// ... başqa kod ...
DB::transaction(fn() => $product->update(['stock' => 0]));

// DÜZGÜN: lock mütləq transaction daxilində olmalıdır
DB::transaction(function () {
    $product = Product::lockForUpdate()->findOrFail(1);
    $product->update(['stock' => 0]);
});
```

**3. Version column-unu bəzi update-lərdə atlayıb bəzilərində istifadə etmək**

```php
// YANLIŞ: bəzən version artırmadan update — version tracking pozulur
DB::table('products')->where('id', 1)->update(['views' => DB::raw('views + 1')]);
// Bu, version-ı increment etmir, amma row-u dəyişir

// DÜZGÜN: non-conflicting sahələr üçün ayrı mexanizm
// analytics/stats sahələrini əsas cədvəldən ayır, ya da explicitly skip et
```

**4. Conflict-i user-ə göstərmədən silent discard etmək**

```php
// YANLIŞ: conflict olarsa sadəcə ignore et
try {
    $product->optimisticUpdate($data, $version);
} catch (ConflictException $e) {
    // Heç nə etmə — user bilmir, data itirildi!
}

// DÜZGÜN: həmişə user-ə bildirməli, current data göstərməlisən
catch (ConflictException $e) {
    return response()->json([
        'error'   => 'CONFLICT',
        'message' => $e->getMessage(),
        'current' => $e->currentData,
    ], 409);
}
```

**5. ETag-i cache-busting üçün versioning URL-ə əlavə etmək**

```
// YANLIŞ: ETag-ı URL parametri kimi istifadə etmək
PATCH /api/products/1?etag=abc123

// DÜZGÜN: ETag HTTP protokol header-idir
PATCH /api/products/1
If-Match: "abc123"
```

**6. Yüksək contention ssenarisində optimistic locking — sonsuz retry**

```
Ssenari: 100 user eyni anda eyni məhsula order verir.
99 user conflict alır → hər biri retry edir → yenə 98 conflict → ...
Bu, "thundering herd" problemi yaradır.

Həll: Yüksək contention halında pessimistic locking + queue ilə serialization.
```

**7. Deadlock üçün lock sırasının göz ardı edilməsi**

```php
// YANLIŞ: fərqli sırada lock → deadlock riski
// Transaction A: product 1 → product 2
// Transaction B: product 2 → product 1  ← deadlock!

// DÜZGÜN: həmişə eyni sırada lock et
$products = Product::lockForUpdate()
    ->whereIn('id', $productIds)
    ->orderBy('id')  // ← sabit sıra
    ->get();
```

---

## Interview Sualları və Cavablar

**S: Optimistic locking ilə pessimistic locking arasındakı fərq nədir? Hansı zaman hansını seçərsiniz?**

C: Optimistic locking conflict-in nadir baş verəcəyini fərz edir — lock tutmur, conflict aşkarlandıqda (0 rows affected) exception atır. Pessimistic locking isə öncədən lock alır, conflict baş vermir amma throughput azalır. Contention aşağıdırsa (əksər hallarda bir user redaktə edir) optimistic; yüksəkdirsə (stock azaltma, payment) pessimistic tercih edilir.

**S: ETag nədir? HTTP-də conflict detection üçün necə istifadə olunur?**

C: ETag (Entity Tag) — resource-un cari vəziyyətinin identifier-idir, adətən content hash-i. Server GET response-a `ETag: "abc123"` header-i əlavə edir. Client yeniləmə zamanı `If-Match: "abc123"` header-i ilə göndərir. Server hash-ləri müqayisə edir — uyğun gəlmirsə `412 Precondition Failed` qaytarır. Bu, HTTP standartının bir hissəsidir və caching ilə konflikt detection-u eyni mexanizmlə həll edir.

**S: Version column approach-nı necə implement edərdiniz? DB-də nə baş verir?**

C: `products` cədvəlinə `version INTEGER DEFAULT 1` sütunu əlavə olunur. Oxuduqda client version-ı alır. Yeniləmədə: `UPDATE products SET price=?, version=version+1 WHERE id=? AND version=?`. DB `affected_rows` qaytarır — 1-dirsə uğurlu, 0-dırsa başqası dəyişdirib, conflict exception atılır. Bu atomic əməliyyatdır — race condition yoxdur. Eloquent-in öz optimistic locking dəstəyi `useOptimisticLocking()` metodu ilə mövcuddur, amma custom versiyanı daha çox control üçün prefer edirəm.

**S: Conflict resolution strategiyaları hansılardır? Hansını nə vaxt seçərsiniz?**

C: 4 əsas strategiya: (1) Reject & Reload — ən sadə, əksər hallar üçün yetərli; user dəyişiklikləri itirilir; (2) Diff göstər — user conflict-li sahələri görür, seçim edir; UX dostu amma implement mürəkkəb; (3) Auto-merge — eyni sahə conflict-li deyilsə avtomatik birləşdirir; document editor üçün ideal; (4) Last-write-wins — conflict detection yoxdur, yalnız non-critical data üçün. Əksər admin panellər üçün Reject & Reload yetərlidir. Collaborative editing (Google Docs kimi) üçün auto-merge və ya CRDT lazımdır.

**S: High contention ssenarisində (eyni anda 500 user eyni stock-a order verir) nə edərdiniz?**

C: Optimistic locking burada problemdir — 499 user conflict alıb retry edər, thundering herd. Düzgün yanaşma: (1) Pessimistic locking + DB serialization — `SELECT FOR UPDATE` ilə sırayla işlə; (2) Queue-based serialization — order-ları queue-ya at, worker sırayla işlə; (3) Redis atomic decrement — `DECR stock:productId` atomic-dir, 0-dan aşağı düşməmək üçün Lua script; (4) Reservation pattern — stoku pre-reserve et, timeout-da azad et. Real sistemlərdə adətən Redis atomic operations + async order processing birlikdə istifadə olunur.

---

## Əlaqəli Mövzular

- [02-double-charge-prevention.md](02-double-charge-prevention.md) — Idempotency key ilə dublikat ödəniş önlənməsi
- [14-pessimistic-locking-queue.md](14-pessimistic-locking-queue.md) — Queue ilə yüksək contention idarəsi
- [34-race-condition-prevention.md](34-race-condition-prevention.md) — Race condition prevention strategiyaları
- [48-distributed-lock.md](48-distributed-lock.md) — Redis ilə distributed locking
