# High-Throughput API Dizaynı (Senior)

## Problem Statement

API günlük 50 milyondan çox request alır. Ortalama response time 200ms-dir, lakin peak saatlarında 2-3 saniyəyə çıxır. Database CPU-su 90%-ə çatır, timeout-lar artır. Minimal latency ilə necə scale edərik?

### Problem niyə yaranır?

Ən çox rast gəlinən ssenari: application kiçik başlayır, DB index-lər olmadan yazılır, N+1 query-lər nəzərə alınmır — az user-də fərq edilmir. User base böyüdükcə (10K → 100K → 1M) bu texniki borclar üzə çıxır. Konkret: `Order::all()` ilə 100K order yükləmək, hər birinin user-ini lazy load etmək = 100,001 DB sorğusu. Caching olmadan hər 200ms-lik request eyni DB sorğusunu təkrar-təkrar icra edir. DB CPU spike → connection pool tükənir → timeout → cascade failure.

---

## Bottleneck-ləri Tapmaq

### Profiling Toolları
- **Laravel Telescope** — query, job, request monitoring
- **Blackfire.io** — deep PHP profiling (call graph, bottleneck detection)
- **APM: Datadog / New Relic** — distributed tracing, p95/p99 latency
- **Laravel Debugbar** — local development-də N+1, slow query aşkar etmə

### Tipik Bottleneck-lər

| Bottleneck | Əlamət | Həll |
|---|---|---|
| N+1 query | DB query sayı hər request-də artır | Eager loading, batch fetch |
| Missing index | Full table scan, slow query log | EXPLAIN ANALYZE + index |
| No caching | Eyni data dəfələrlə DB-dən çəkilir | Redis cache layer |
| Sync heavy work | Request uzun gözləyir | Queue-ya at |
| Connection exhaustion | Too many connections error | Connection pooling (PgBouncer) |
| JSON serialization | CPU spike, slow response | Partial response, caching |

---

## 1. Database Optimization

### Read Replica Splitting

*Bu kod write/read replica ayrımı üçün Laravel database konfigurasiyanı göstərir:*

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', '10.0.1.10'),
            env('DB_READ_HOST_2', '10.0.1.11'),
        ],
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST', '10.0.1.1'),
    ],
    'sticky'    => true, // Write-dan sonra read replica-dan deyil, write-dan oxu
    'driver'    => 'mysql',
    'database'  => env('DB_DATABASE'),
    'username'  => env('DB_USERNAME'),
    'password'  => env('DB_PASSWORD'),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
],
```

*Bu kod oxuma əməliyyatlarını açıq şəkildə read replica-ya yönləndirməyi göstərir:*

```php
// Read replica-ya explicit yönləndir
$products = DB::connection('mysql::read')
    ->table('products')
    ->where('category_id', $categoryId)
    ->get();

// Eloquent ilə
$users = User::on('mysql::read')->where('active', true)->get();
```

### Query Optimization — N+1 Elimination

*Bu kod N+1 query probleminin necə yaranda və eager loading ilə necə həll edildiyini müqayisəli göstərir:*

```php
// YAVAŞ — N+1 problem
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name;        // hər iteration-da 1 query
    echo $order->items->count();    // hər iteration-da 1 query
}

// SÜRƏTLİ — eager loading
$orders = Order::with(['user:id,name', 'items'])
    ->select('id', 'user_id', 'total', 'created_at')
    ->whereDate('created_at', today())
    ->get();

// Daha da sürətli — select lazım olan column-ları
$orders = Order::query()
    ->select('orders.id', 'orders.total', 'orders.created_at', 'users.name as user_name')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->whereDate('orders.created_at', today())
    ->get();
```

### Index Strategiyası

*Bu kod çox istifadə olunan sorğuları sürətləndirən composite index-ləri yaradan migration-u göstərir:*

```php
// database/migrations/2024_01_01_add_performance_indexes.php
public function up(): void
{
    // Composite index — birlikdə filter edilən column-lar
    Schema::table('orders', function (Blueprint $table) {
        $table->index(['user_id', 'status', 'created_at'], 'orders_user_status_date');
        $table->index(['status', 'created_at'], 'orders_status_date');
    });

    // Covering index — query-nin bütün column-larını əhatə edir (table scan olmur)
    Schema::table('products', function (Blueprint $table) {
        $table->index(['category_id', 'status', 'price', 'name'], 'products_category_covering');
    });

    // Full-text search üçün
    Schema::table('products', function (Blueprint $table) {
        $table->fullText(['name', 'description'], 'products_search');
    });
}
```

*Bu kod MySQL slow query log-u aktivləşdirib EXPLAIN ANALYZE ilə query planını analiz etməyi göstərir:*

```sql
-- Slow query-ləri tap (MySQL slow query log)
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;  -- 500ms-dən uzun query-ləri log et

-- Query planını analiz et
EXPLAIN ANALYZE
SELECT p.id, p.name, c.name as category
FROM products p
JOIN categories c ON c.id = p.category_id
WHERE p.status = 'active' AND p.category_id = 5
ORDER BY p.created_at DESC
LIMIT 20;
```

### Denormalization for Read Performance

*Bu kod join-dən qaçmaq üçün user_name-i orders cədvəlindəki denormalized sütunda Observer ilə saxlamağı göstərir:*

```php
// Məsələn, hər order-ın user_name-ini ayrıca saxla
// Join atmadan oxumaq üçün

Schema::table('orders', function (Blueprint $table) {
    $table->string('user_name')->nullable(); // denormalized
    $table->string('user_email')->nullable();
});

// Observer ilə sinxron saxla
class OrderObserver
{
    public function creating(Order $order): void
    {
        $user = User::find($order->user_id);
        $order->user_name  = $user->name;
        $order->user_email = $user->email;
    }
}
```

---

## 2. Caching Layers

### Redis — Cache-Heavy Architecture

*Bu kod batch cache miss-ləri, tag-based invalidation və kateqoriya üzrə cache idarəetməsini göstərən product cache service-ini göstərir:*

```php
// app/Services/ProductCacheService.php
<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductCacheService
{
    private const TTL_PRODUCT = 3600;          // 1 saat
    private const TTL_CATEGORY_LIST = 1800;    // 30 dəq
    private const TTL_HOT_PRODUCTS = 300;      // 5 dəq

    public function getProduct(int $id): ?array
    {
        return Cache::remember("product:{$id}", self::TTL_PRODUCT, function () use ($id) {
            return Product::with('category', 'images')
                ->find($id)
                ?->toArray();
        });
    }

    public function getProductsBatch(array $ids): array
    {
        $cacheKeys = array_map(fn($id) => "product:{$id}", $ids);
        $cached    = Cache::many($cacheKeys);

        $missingIds = [];
        $result     = [];

        foreach ($ids as $id) {
            $key = "product:{$id}";
            if ($cached[$key] !== null) {
                $result[$id] = $cached[$key];
            } else {
                $missingIds[] = $id;
            }
        }

        // Cache miss-ləri bir sorğu ilə DB-dən çək
        if (!empty($missingIds)) {
            $products = Product::whereIn('id', $missingIds)
                ->with('category')
                ->get()
                ->keyBy('id');

            $toStore = [];
            foreach ($missingIds as $id) {
                if (isset($products[$id])) {
                    $data         = $products[$id]->toArray();
                    $result[$id]  = $data;
                    $toStore["product:{$id}"] = $data;
                }
            }

            // Hamısını birlikdə cache-ə yaz
            Cache::putMany($toStore, self::TTL_PRODUCT);
        }

        return $result;
    }

    /**
     * Cache invalidation — məhsul dəyişəndə
     */
    public function invalidateProduct(int $id): void
    {
        Cache::forget("product:{$id}");
        // Category listini də sil
        $product = Product::find($id);
        if ($product) {
            Cache::forget("category:{$product->category_id}:products");
        }
    }

    /**
     * Tag-based cache — kateqoriyaya görə toplu silmə
     */
    public function getCategoryProducts(int $categoryId, int $page): array
    {
        return Cache::tags(["category:{$categoryId}", 'products'])
            ->remember("category:{$categoryId}:page:{$page}", self::TTL_CATEGORY_LIST, function () use ($categoryId, $page) {
                return Product::where('category_id', $categoryId)
                    ->where('status', 'active')
                    ->select('id', 'name', 'price', 'thumbnail')
                    ->paginate(20, ['*'], 'page', $page)
                    ->toArray();
            });
    }

    public function invalidateCategoryCache(int $categoryId): void
    {
        Cache::tags(["category:{$categoryId}"])->flush();
    }
}
```

### HTTP Cache Headers

*Bu kod ETag və If-Modified-Since ilə conditional GET-i dəstəkləyən HTTP cache headerləri göstərir:*

```php
// app/Http/Controllers/ProductController.php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, int $id)
    {
        $product     = Product::findOrFail($id);
        $lastModified = $product->updated_at;
        $etag        = md5($product->updated_at . $product->id);

        // Conditional GET — cache validation
        if ($request->header('If-None-Match') === $etag) {
            return response()->noContent(304);
        }

        if ($request->header('If-Modified-Since')) {
            $ifModifiedSince = \Carbon\Carbon::parse($request->header('If-Modified-Since'));
            if ($lastModified->lte($ifModifiedSince)) {
                return response()->noContent(304);
            }
        }

        return response()->json($product)
            ->header('ETag', $etag)
            ->header('Last-Modified', $lastModified->toRfc7231String())
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60');
    }
}
```

---

## 3. Laravel Octane ilə Performance

*Bu kod Octane-i Swoole ilə konfiqurasiya edib worker sayı və max_requests parametrlərini göstərir:*

```php
// config/octane.php
return [
    'server'   => env('OCTANE_SERVER', 'swoole'),
    'workers'  => env('OCTANE_WORKERS', 'auto'), // CPU core sayı qədər
    'task_workers' => env('OCTANE_TASK_WORKERS', 6),
    'max_requests' => env('OCTANE_MAX_REQUESTS', 500), // Memory leak önləmək üçün
];
```

*'max_requests' => env('OCTANE_MAX_REQUESTS', 500), // Memory leak önlə üçün kod nümunəsi:*
```php
// app/Providers/AppServiceProvider.php
// Octane ilə singleton-lar application life boyunca yaşayır
// Stateful service-ləri reset etmək lazımdır

use Laravel\Octane\Facades\Octane;

public function boot(): void
{
    // Hər request-dən sonra reset ediləcək service-lər
    Octane::tick('flush-resolved-facades', function () {
        // ...
    })->seconds(5);
}
```

*Bu kod Octane-in `concurrently()` metodunu istifadə edərək bir neçə DB sorğusunu paralel işlətməyi göstərir:*

```php
// Octane-də parallel task-lar
use Laravel\Octane\Facades\Octane;

public function dashboard()
{
    [$users, $orders, $revenue] = Octane::concurrently([
        fn() => User::where('active', true)->count(),
        fn() => Order::whereDate('created_at', today())->count(),
        fn() => Order::whereDate('created_at', today())->sum('total'),
    ]);

    return response()->json(compact('users', 'orders', 'revenue'));
}
```

---

## 4. Queue-based Async Processing

*Bu kod ağır hesabat yaratmanı queue-ya göndərərək dərhal 202 qaytaran, sonra status pollunq endpoint-i olan controller-i göstərir:*

```php
// Ağır əməliyyatları sync etmə, queue-ya at
// app/Http/Controllers/ReportController.php

public function generate(Request $request)
{
    $report = Report::create([
        'user_id' => $request->user()->id,
        'type'    => $request->type,
        'status'  => 'pending',
        'filters' => $request->filters,
    ]);

    // Sync emal etmə — queue-ya göndər
    GenerateReport::dispatch($report)
        ->onQueue('reports')
        ->delay(now()->addSeconds(2));

    return response()->json([
        'report_id' => $report->id,
        'status'    => 'queued',
        'poll_url'  => route('reports.status', $report),
    ], 202);
}

public function status(Report $report)
{
    return response()->json([
        'status'     => $report->status,
        'download_url' => $report->status === 'completed' ? $report->download_url : null,
    ]);
}
```

---

## 5. Rate Limiting

*Bu kod auth statusu, istifadəçi planı və endpoint-ə görə fərqli rate limit qaydaları təyin etməyi göstərir:*

```php
// app/Http/Kernel.php — throttle middleware qur
protected $middlewareGroups = [
    'api' => [
        \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
    ],
];

// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // Authenticated user-lar üçün
    RateLimiter::for('api', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(120)->by($request->user()->id)
            : Limit::perMinute(20)->by($request->ip());
    });

    // Search endpoint — daha aşağı limit
    RateLimiter::for('search', function (Request $request) {
        return [
            Limit::perMinute(30)->by($request->user()?->id ?? $request->ip()),
            Limit::perHour(500)->by($request->user()?->id ?? $request->ip()),
        ];
    });

    // Premium user-lar üçün yüksək limit
    RateLimiter::for('premium-api', function (Request $request) {
        if ($request->user()?->isPremium()) {
            return Limit::perMinute(1000)->by($request->user()->id);
        }
        return Limit::perMinute(120)->by($request->user()?->id ?? $request->ip());
    });
}
```

---

## 6. Connection Pooling

*Bu kod PostgreSQL üçün PgBouncer connection pool konfigurasiyasını göstərir:*

```bash
# PgBouncer konfiqurasiyası (PostgreSQL üçün)
# /etc/pgbouncer/pgbouncer.ini

[databases]
myapp = host=localhost dbname=myapp

[pgbouncer]
pool_mode = transaction          # Transaction-per-connection
max_client_conn = 1000           # Maksimum client connection
default_pool_size = 25           # Hər DB-yə pool size
reserve_pool_size = 5
reserve_pool_timeout = 3
server_idle_timeout = 600
client_idle_timeout = 0
```

*Bu kod Laravel-də persistent connection və pool parametrlərini konfiqurasiya etməyi göstərir:*

```php
// Laravel-də connection pool simulyasiyası
// config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true, // Persistent connections
    ],
    'pool' => [
        'min_connections' => 2,
        'max_connections' => 20,
        'connect_timeout' => 10.0,
        'wait_timeout'    => 3.0,
        'heartbeat'       => -1,
        'max_idle_time'   => 60.0,
    ],
],
```

---

## 7. Response Compression + Partial Response

*Bu kod client-in dəstəklədiyi formatda gzip/deflate response sıxışdırması tətbiq edən middleware-i göstərir:*

```php
// app/Http/Middleware/CompressResponse.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CompressResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$request->acceptsEncoding('gzip')) {
            return $response;
        }

        $content = $response->getContent();

        if (strlen($content) < 1024) {
            return $response; // Kiçik response-ları compress etmə
        }

        $compressed = gzencode($content, 6);

        return $response
            ->setContent($compressed)
            ->header('Content-Encoding', 'gzip')
            ->header('Content-Length', strlen($compressed));
    }
}
```

*->header('Content-Length', strlen($compressed)); üçün kod nümunəsi:*
```php
// Sparse fieldsets — yalnız lazım olan fieldlər
// GET /api/products?fields=id,name,price

// app/Http/Resources/ProductResource.php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $fields = $request->query('fields')
            ? explode(',', $request->query('fields'))
            : null;

        $data = [
            'id'          => $this->id,
            'name'        => $this->name,
            'price'       => $this->price,
            'description' => $this->description,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'images'      => ImageResource::collection($this->whenLoaded('images')),
            'created_at'  => $this->created_at->toIso8601String(),
        ];

        if ($fields) {
            return array_intersect_key($data, array_flip($fields));
        }

        return $data;
    }
}
```

---

## 8. Database Sharding Konsepti

```
Sharding — datanı horizontal bölmək (bir böyük cədvəl → çox kiçik cədvəl/DB)

Strategiyalar:
1. Range-based: user_id 1-1M → DB1, 1M-2M → DB2
2. Hash-based: user_id % shard_count → shard seçimi
3. Directory-based: ayrıca mapping cədvəli (ən çevik, ən yavaş)
```

*3. Directory-based: ayrıca mapping cədvəli (ən çevik, ən yavaş) üçün kod nümunəsi:*
```php
// app/Services/ShardingService.php
<?php

namespace App\Services;

class ShardingService
{
    private array $shards = [
        0 => 'mysql_shard_0',
        1 => 'mysql_shard_1',
        2 => 'mysql_shard_2',
        3 => 'mysql_shard_3',
    ];

    public function getConnectionForUser(int $userId): string
    {
        $shardId = $userId % count($this->shards);
        return $this->shards[$shardId];
    }

    public function getUserOrders(int $userId): \Illuminate\Support\Collection
    {
        $connection = $this->getConnectionForUser($userId);

        return \DB::connection($connection)
            ->table('orders')
            ->where('user_id', $userId)
            ->get();
    }
}
```

---

## 9. Benchmarking + Monitoring

*9. Benchmarking + Monitoring üçün kod nümunəsi:*
```php
// app/Http/Middleware/PerformanceMonitoring.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $start  = microtime(true);
        $memory = memory_get_usage();

        $response = $next($request);

        $duration = (microtime(true) - $start) * 1000; // ms
        $memUsed  = memory_get_usage() - $memory;

        // Slow request-ləri log et
        if ($duration > 500) {
            Log::warning('Slow request detected', [
                'url'      => $request->fullUrl(),
                'method'   => $request->method(),
                'duration' => round($duration, 2) . 'ms',
                'memory'   => round($memUsed / 1024 / 1024, 2) . 'MB',
                'user_id'  => $request->user()?->id,
            ]);
        }

        return $response
            ->header('X-Response-Time', round($duration, 2) . 'ms')
            ->header('X-Memory-Usage', round($memUsed / 1024, 2) . 'KB');
    }
}
```

*->header('X-Memory-Usage', round($memUsed / 1024, 2) . 'KB'); üçün kod nümunəsi:*
```php
// Prometheus metrics (Laravel Prometheus paketi)
// app/Http/Middleware/PrometheusMetrics.php
use Prometheus\CollectorRegistry;

class PrometheusMetrics
{
    public function __construct(
        private CollectorRegistry $registry
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $start    = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $start;

        $route      = $request->route()?->getName() ?? 'unknown';
        $statusCode = $response->getStatusCode();

        // HTTP request counter
        $this->registry
            ->getOrRegisterCounter('app', 'http_requests_total', 'Total HTTP requests', ['route', 'method', 'status'])
            ->inc([$route, $request->method(), $statusCode]);

        // HTTP request duration histogram
        $this->registry
            ->getOrRegisterHistogram('app', 'http_request_duration_seconds', 'Request duration', ['route'],
                [0.01, 0.05, 0.1, 0.3, 0.5, 1.0, 2.0, 5.0])
            ->observe($duration, [$route]);

        return $response;
    }
}
```

---

## 10. Load Balancing Strategiyaları

*10. Load Balancing Strategiyaları üçün kod nümunəsi:*
```nginx
# nginx.conf — Upstream load balancing

upstream php_backend {
    least_conn;  # Ən az connection-u olan server-ə yönləndir

    server 10.0.1.10:9000 weight=3;  # Güclü server
    server 10.0.1.11:9000 weight=2;
    server 10.0.1.12:9000 weight=1;

    keepalive 32;  # Connection keep-alive pool
}

upstream octane_backend {
    ip_hash;  # Session affinity (eyni user → eyni server)
    server 10.0.1.10:8000;
    server 10.0.1.11:8000;
}

server {
    listen 80;

    location /api/ {
        proxy_pass http://php_backend;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

        # Health check
        proxy_next_upstream error timeout invalid_header http_500 http_502;
        proxy_next_upstream_tries 2;
        proxy_connect_timeout 2s;
        proxy_read_timeout 10s;
    }
}
```

---

## Arxitektura Diaqramı

```
                     ┌──────────────┐
                     │   CloudFlare │  ← CDN + DDoS protection
                     │   (CDN/WAF)  │
                     └──────┬───────┘
                            │
                     ┌──────▼───────┐
                     │ Load Balancer│  ← Nginx / HAProxy / AWS ALB
                     │  (least_conn)│
                     └──┬───────┬───┘
               ┌────────┘       └────────┐
        ┌──────▼──────┐         ┌────────▼────┐
        │  App Server  │         │  App Server  │  ← Laravel Octane
        │  (Swoole)    │         │  (Swoole)    │
        └──────┬───────┘         └──────┬───────┘
               └────────┬───────────────┘
                        │
           ┌────────────┼────────────┐
    ┌───────▼──┐  ┌──────▼─────┐  ┌──▼──────┐
    │  Redis   │  │  MySQL     │  │  MySQL  │
    │  Cache   │  │  (Write)   │  │  (Read) │
    └──────────┘  └────────────┘  └─────────┘
```

---

## İntervyu Sualları

**S: N+1 problemi nədir və necə aşkar edilir?**
C: ORM istifadə edərkən bir ana query + N tane uşaq query icra edilir. Məsələn, 100 order üçün 100 ayrı user query-si. Laravel Debugbar, Telescope, ya da `DB::enableQueryLog()` ilə aşkar edilir. `with()` ilə eager loading ilə həll olunur.

**S: Read replica nə zaman lazım olur?**
C: Yazma/oxuma ratio 20/80-dirsə (əksər hallarda belədir), write load-u write server-ə, read load-u birneçə read replica-ya yönləndirmək məntiqlər. `sticky=true` seçimi write-dan sonra eyni request-də read replica yerinə master-dən oxumasını təmin edir.

**S: Redis cache invalidation strategiyası nədir?**
C: Üç yanaşma var: TTL-based (sadə, stale data riski var), event-driven (Observer ilə dəyişiklikdə sil), tag-based cache (əlaqəli key-ləri toplu sil). Production-da hybrid: qısa TTL + event-driven invalidation.

**S: Octane Swoole worker-lar arasında state necə paylaşılır?**
C: Paylaşılmır — hər worker müstəqildir. Paylaşılan state üçün Redis istifadə edilir. Singleton-lar request life-time-da yaşayır, sonra reset edilir. Bu memory leak riskini yaradır — `max_requests` ilə worker-ı yenidən başlatmaq lazımdır.

**S: Rate limiting Redis-siz necə işləyir?**
C: Laravel-in default driver `cache`-dir, bu memcached, database, file da ola bilər. Production-da mütləq Redis — atomik increment, TTL dəstəyi, cluster support var. Sliding window algorithm üçün Redis sorted sets lazımdır.

**S: Sharding zamanı JOIN-lər necə işləyir?**
C: Cross-shard JOIN işləmir. Ya application layer-da birləşdiririk (scatter-gather), ya da sharding sxemini elə dizayn edirik ki, əlaqəli data eyni shard-da qalsın (user + orders). Bu sharding-in ən böyük çətinliyidir.

---

## Anti-patternlər

**1. DB-ni rate limit state üçün istifadə etmək**
Hər request-də `UPDATE rate_limits SET count = count + 1` — yüksək yükdə DB darboğazı. Redis atomic increment (INCR) istifadə edin.

**2. Şaquli scale etmək (vertical scaling)**
Daha güclü server almaq müvəqqəti həll. Horizontal scaling + load balancer + stateless application arxitekturası düzgün yanaşma.

**3. N+1 sorğu**
Loop içində DB query — 1000 user üçün 1001 sorğu. Eager loading (`with()`), batch processing, ya da Redis cache ilə həll olunur.

**4. Hər şeyi eyni queue-da işləmək**
Kritik ödəniş job-ları ilə bulk email job-ları eyni queue-da yarışırsa, ödənişlər gecikir. Prioritet queue-lar ayırın: `critical`, `default`, `low`.

**5. Synchronous cache invalidation**
Məhsul yenilənəndə loop içində 500 cache key delete etmək — request blocking. Tag-based invalidation (`Cache::tags(['products'])->flush()`) ya da event-driven invalidation.

**6. Connection pool olmadan yüksək concurrency**
Hər PHP worker ayrı DB connection açır. 500 concurrent request = 500 DB connection → "too many connections" xətası. PgBouncer (PostgreSQL) yaxud ProxySQL (MySQL) ilə connection pooling — yüzlərlə PHP worker cəmi 20-50 DB connection paylaşır.

**7. Octane-da stateful singleton-lar**
Laravel Octane-da application boot bir dəfə olur. Stateful singleton (request-specific data saxlayan) worker-lar arasında paylaşılır — data leak, yanlış user-ə məlumat göstərilmə riski. `$this->app->scoped()` ilə request-scoped binding istifadə edin.

**8. Caching olmadan hot endpoint**
Eyni data hər request-də DB-dən çəkilir — DB CPU spike, latency artır. `Cache::remember()` ilə uyğun TTL — əksər oxuma-ağır endpointlər üçün 60-300 saniyə TTL kifayətdir.**
Hər request yeni DB connection açırsa — connection limit aşılır. Laravel connection pool, PgBouncer, ya da persistent connection konfiqurasiyası lazımdır.
