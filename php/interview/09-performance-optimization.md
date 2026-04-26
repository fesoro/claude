# Performance Optimization (Senior)

## 1. Laravel-də caching strategiyaları

```php
// Application-level caching
$users = Cache::remember('users:active', 3600, function () {
    return User::where('active', true)->with('roles')->get();
});

// Cache invalidation — model observer ilə
class UserObserver {
    public function saved(User $user): void {
        Cache::forget("user:{$user->id}");
        Cache::tags(['users'])->flush();
    }
}

// Route caching (production)
php artisan route:cache    // route-ları cache-lə
php artisan config:cache   // config-ləri cache-lə
php artisan view:cache     // view-ları compile et
php artisan event:cache    // event-ləri cache-lə
php artisan optimize       // hamısını bir yerdə

// Query caching
$products = Cache::remember(
    'products:category:' . $categoryId . ':page:' . $page,
    600,
    fn () => Product::where('category_id', $categoryId)->paginate(20)
);

// HTTP caching
return response()->json($data)
    ->header('Cache-Control', 'public, max-age=300')
    ->header('ETag', md5(json_encode($data)));
```

---

## 2. Database Performance

```php
// 1. Index əlavə et
Schema::table('orders', function (Blueprint $table) {
    $table->index(['user_id', 'status', 'created_at']);
});

// 2. Lazy loading-dən qaçın
Model::preventLazyLoading(!app()->isProduction());

// 3. Select yalnız lazımlı sütunları
User::select('id', 'name', 'email')->get();

// 4. Chunk böyük datasetlər üçün
User::chunkById(1000, function ($users) {
    // 1000-lik hissələrlə işlə
});

// 5. Database connection pooling (Octane/Swoole)
// config/database.php
'mysql' => [
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 10,
    ],
],

// 6. Read/Write splitting
'mysql' => [
    'read' => [
        'host' => ['read-replica-1', 'read-replica-2'],
    ],
    'write' => [
        'host' => ['primary-db'],
    ],
],

// 7. Query debugging
DB::enableQueryLog();
// ... sorğular
$queries = DB::getQueryLog();
// Laravel Debugbar və ya Telescope istifadə et
```

---

## 3. Laravel Octane

Tətbiqi yaddaşda saxlayıb hər request üçün yenidən boot etmir → çox sürətli.

```php
// Swoole və ya RoadRunner ilə işləyir
// composer require laravel/octane
// php artisan octane:install

// Ehtiyatlı olmaq lazımdır:
// 1. Static/global state — request-lər arası paylaşıla bilər
class BadService {
    private static array $cache = []; // TEHLÜKƏLİ — request-lər arası paylaşılır
}

// 2. Singleton-lar — request sonrası sıfırlanmır
// Octane listener-lərdə sıfırla:
$this->app['view']->flushFinderCache();

// 3. Config/env dəyərləri boot-da cache olunur
config('app.name'); // Octane-da runtime-da dəyişməz

// php artisan octane:start --workers=4 --task-workers=6
```

---

## 4. Queue ilə Performance

```php
// Uzun əməliyyatları queue-ya göndər
// Pis — user 10 saniyə gözləyir
public function store(Request $request): JsonResponse {
    $order = Order::create($request->validated());
    $this->sendEmail($order);           // 2 san
    $this->processPayment($order);      // 3 san
    $this->updateInventory($order);     // 1 san
    $this->notifyWarehouse($order);     // 2 san
    $this->generateInvoice($order);     // 2 san
    return response()->json($order);    // Total: ~10 san
}

// Yaxşı — user dərhal cavab alır
public function store(Request $request): JsonResponse {
    $order = Order::create($request->validated());

    Bus::chain([
        new ProcessPayment($order),
        new UpdateInventory($order),
        new SendOrderConfirmation($order),
        new GenerateInvoice($order),
    ])->dispatch();

    return response()->json($order, 201); // Dərhal cavab
}
```

---

## 5. Frontend Asset Optimization

```php
// Vite config (vite.config.js)
export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['vue', 'axios'],
                },
            },
        },
    },
});

// Lazy loading images
<img loading="lazy" src="{{ $product->image_url }}" />

// CDN istifadə
ASSET_URL=https://cdn.example.com
```

---

## 6. Monitoring və Profiling

```php
// Laravel Telescope — development debugging
// composer require laravel/telescope --dev

// Laravel Debugbar
// composer require barryvdh/laravel-debugbar --dev

// Custom performance logging
$start = microtime(true);
$result = $this->heavyOperation();
$duration = microtime(true) - $start;
Log::info("Heavy operation took {$duration}s");

// Slow query logging
DB::listen(function ($query) {
    if ($query->time > 100) { // 100ms-dən yavaş
        Log::warning('Slow query', [
            'sql' => $query->sql,
            'time' => $query->time . 'ms',
            'bindings' => $query->bindings,
        ]);
    }
});

// Health check endpoint
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Cache::store('redis')->get('health-check');
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});
```
