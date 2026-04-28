# Database Read Replica Routing (Lead)

## Problem Təsviri

E-commerce platformanızda trafik artır. Monitoring göstərir ki, primary database CPU 90%-dədir, slow query log dolub-daşır, yazma əməliyyatları oxuma sorğularını bloklamağa başlayıb. Şikayət: product listing 3-4 saniyə açılır, order history timeout olur.

Səbəb sadədir: bütün sorğular — product search, order history, user profile, product detail, dashboard analytics — primary DB-yə gedir. Halbuki real dünyada e-commerce trafiki belə bölünür:

```
95% oxuma  — product listing, search, order history, user profile, reviews
 5% yazma  — yeni sifariş, status update, inventory azaldılması
```

Nəticə: primary DB read traffic-dən boğulur, write lock-lar read sorğuları ilə rəqabətə girir, latency artır.

**Vertical scaling limiti:** Primary server-ə daha çox RAM/CPU əlavə etmək müvəqqəti həlldir. Fiziki limit var, hər upgrade downtime riski daşıyır, qiyməti dəfələrlə artır.

**Doğru həll:** Read sorğularını ayrı read replica-lara yönləndirmək — horizontal scaling-in ən sadə forması.

---

## Arxitektura

```
Application (Laravel)
         │
         ▼
┌────────────────────────────┐
│   DB Connection Manager    │  ← sticky routing, lag-aware fallback
└────────────────────────────┘
         │                  │
         ▼                  ▼
  ┌─────────────┐    ┌──────────────────────┐
  │  Primary DB │    │  Read Replica(s)      │
  │  (writes)   │    │  replica-1 10.0.1.2  │
  │  10.0.1.1   │    │  replica-2 10.0.1.3  │
  └─────────────┘    └──────────────────────┘
         │                  ▲
         └──── replication ─┘
              (~100ms lag, eventual consistency)
```

**Replication necə işləyir:** Primary DB hər yazma əməliyyatını binary log-a yazır. Replica-lar bu log-u oxuyur və öz daxilinə tətbiq edir. Bu proses asinxrondur — adi şəraitdə gecikmə 10-200ms olur, yük artanda saniyələrə çıxa bilər.

---

## Həll 1: Laravel Built-in Read/Write Splitting

Laravel-in built-in read/write connection sistemi ən sadə başlanğıc nöqtəsidir.

### Konfiqurasiya

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',

    // Read sorğuları bu host-lara round-robin ilə paylanır
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', '10.0.1.2'),
            env('DB_READ_HOST_2', '10.0.1.3'),
        ],
    ],

    // Yazma əməliyyatları yalnız primary-ə gedir
    'write' => [
        'host' => [env('DB_HOST', '10.0.1.1')],
    ],

    // VACIB: eyni request daxilində write-dan sonra read-ləri primary-dən oxu
    'sticky' => true,

    'database' => env('DB_DATABASE', 'myapp'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'port' => env('DB_PORT', '3306'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

### sticky: true Necə İşləyir

`sticky` parametri Laravel-in **read-your-writes** problemini həll etmək üçün sadə mexanizmdir.

```
Request başladı
      │
      ▼
  DB::insert()  ──→ Primary-ə yazıldı
      │
      ▼                        sticky: false olsaydı
  DB::select()  ──→ Replica-ya gedir  ──→ YENİ YAZILAN DATA GÖRÜNMƏYƏ BİLƏR!
                                           (replication lag)
```

```
sticky: true ilə:
      │
      ▼
  DB::insert()  ──→ Primary-ə yazıldı ──→ "bu request-də write var" flag qoyulur
      │
      ▼
  DB::select()  ──→ Primary-dən oxunur  ──→ YENİ YAZILAN DATA GÖRÜNÜR ✓
```

Bu yalnız eyni HTTP request daxilində işləyir. Növbəti request artıq replica-ya gedir.

### Avtomatik Routing

```php
// Bu sorğu avtomatik olaraq replica-ya gedir
$products = DB::table('products')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->paginate(20);

// Bu sorğu primary-ə gedir (INSERT)
DB::table('orders')->insert([...]);

// sticky: true aktiv olduğu üçün bu da primary-ə gedir (eyni request-də write var)
$updatedOrder = DB::table('orders')->where('id', $orderId)->first();
```

---

## Həll 2: Lag-Aware Replica Routing

Sadə read/write splitting-in böyük problemi var: replica-nın neçə saniyə geridə qaldığını bilmir. Yük artanda lag 30-60 saniyəyə çıxa bilər. Bu zaman köhnəlmiş (stale) data göstərilir.

**Həll:** Sorğudan əvvəl replica-nın lag-ını yoxla, yüksəkdirsə primary-ə yönləndir.

```php
// app/Services/ReplicaHealthService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReplicaHealthService
{
    // Maksimum qəbul edilə bilən lag (saniyə)
    private const MAX_LAG_SECONDS = 5;

    // Lag yoxlamasının nə qədər cache-lənəcəyi (saniyə)
    // Hər sorğuda yoxlamaq — overhead yaradır; 10 saniyə optimal balans
    private const LAG_CHECK_TTL = 10;

    /**
     * Replica-nın istifadə oluna bilən vəziyyətdə olub olmadığını yoxlayır.
     * Nəticəni 10 saniyə cache-ləyir ki, hər sorğuda DB round-trip olmasın.
     */
    public function isReplicaHealthy(string $connection = 'mysql_read'): bool
    {
        $cacheKey = "replica_health:{$connection}";

        return Cache::remember($cacheKey, self::LAG_CHECK_TTL, function () use ($connection) {
            return $this->checkReplicaLag($connection);
        });
    }

    private function checkReplicaLag(string $connection): bool
    {
        try {
            $status = DB::connection($connection)->selectOne('SHOW SLAVE STATUS');

            if ($status === null) {
                // SHOW SLAVE STATUS boş qaytarırsa — bu replica deyil (primary-dir)
                // Ehtiyatla: bəzən replica konfiq xətası da boş qaytarır
                Log::warning("ReplicaHealthService: {$connection} SLAVE STATUS boşdur");
                return false;
            }

            $lag = $status->Seconds_Behind_Master;

            // NULL lag: replica master-ə qoşulmayıb — istifadə etmə
            if ($lag === null) {
                Log::warning("ReplicaHealthService: {$connection} master-ə qoşulmayıb (lag=NULL)");
                return false;
            }

            if ($lag > self::MAX_LAG_SECONDS) {
                Log::warning("ReplicaHealthService: {$connection} lag yüksəkdir", [
                    'lag_seconds' => $lag,
                    'max_allowed' => self::MAX_LAG_SECONDS,
                ]);
                return false;
            }

            return true;

        } catch (\Throwable $e) {
            // Replica down, connection refused, credential xətası — primary-dən oxu
            Log::error("ReplicaHealthService: {$connection} əlçatan deyil", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Cache-i məcburi sıfırla — deployment sonrası və ya manual trigger üçün.
     */
    public function invalidateCache(string $connection = 'mysql_read'): void
    {
        Cache::forget("replica_health:{$connection}");
    }
}
```

### Lag-Aware DB Helper

```php
// app/Services/SmartDbService.php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SmartDbService
{
    public function __construct(
        private ReplicaHealthService $replicaHealth
    ) {}

    /**
     * Oxuma sorğuları üçün ən optimal connection-u seçir.
     *
     * Replica sağlamdırsa → replica
     * Replica lagdadırsa və ya down-dursa → primary
     *
     * @param bool $forcePrimary Kritik data üçün məcburi primary seçimi
     */
    public function readConnection(bool $forcePrimary = false): \Illuminate\Database\Connection
    {
        if ($forcePrimary || !$this->replicaHealth->isReplicaHealthy()) {
            return DB::connection('mysql');
        }

        return DB::connection('mysql_read');
    }

    /**
     * Yazma əməliyyatları həmişə primary-ə gedir.
     */
    public function writeConnection(): \Illuminate\Database\Connection
    {
        return DB::connection('mysql');
    }
}
```

### Çoxlu Replica — Sağlam Olanı Seç

```php
// app/Services/ReplicaRouter.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReplicaRouter
{
    // Bütün mövcud replica connection adları
    private array $replicas = ['mysql_read_1', 'mysql_read_2'];

    /**
     * Sağlam replica-lar arasından random seçim edir (load balancing).
     * Heç biri sağlam deyilsə primary-yə fallback edir.
     */
    public function getReadConnection(): \Illuminate\Database\Connection
    {
        $healthyReplicas = array_filter(
            $this->replicas,
            fn(string $replica) => $this->isHealthy($replica)
        );

        if (empty($healthyReplicas)) {
            Log::warning('ReplicaRouter: Bütün replica-lar sağlam deyil, primary-ə fallback');
            return DB::connection('mysql');
        }

        // Sağlam replica-lar arasında random seçim — sadə load balancing
        $selected = $healthyReplicas[array_rand($healthyReplicas)];
        return DB::connection($selected);
    }

    private function isHealthy(string $connection): bool
    {
        return Cache::remember("replica_ok:{$connection}", 10, function () use ($connection) {
            try {
                $lag = DB::connection($connection)
                    ->selectOne('SHOW SLAVE STATUS')
                    ?->Seconds_Behind_Master;

                return $lag !== null && $lag <= 5;
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
```

---

## Həll 3: Explicit Connection Seçimi

Ən şəffaf yanaşma: kod birbaşa hansı connection-dan oxuyacağını bildirir. Complexity əvəzinə maksimum nəzarət.

### Kritik vs. Qeyri-Kritik Read-lərin Ayrılması

```php
// app/Services/OrderService.php
namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Ödəniş statusunu oxuyur — həmişə primary-dən.
     *
     * Niyə: user yeni sifariş verdi, dərhal status səhifəsinə yönləndirildi.
     * Replica 200ms geridədirsə status hələ "pending" görünə bilər → user panic edir.
     * Bu hallarda stale data = dəhşətli UX.
     */
    public function getOrderStatus(int $orderId): string
    {
        return DB::connection('mysql')
            ->table('orders')
            ->where('id', $orderId)
            ->value('status');
    }

    /**
     * İnventory sayını oxuyur — həmişə primary-dən.
     *
     * Niyə: replica 100ms geridədirsə, bir məhsulun son 1 ədədi 2 nəfərə eyni anda
     * "mövcud" görünə bilər. Hər ikisi sifarişi tamamlayar → overselling.
     */
    public function getStockCount(int $productId): int
    {
        return (int) DB::connection('mysql')
            ->table('product_inventory')
            ->where('product_id', $productId)
            ->value('quantity');
    }

    /**
     * Son sifarişlər siyahısı — replica-dan oxumaq tamamilə təhlükəsizdir.
     *
     * Niyə: 200ms gecikmiş siyahı user experience-ı pozmur.
     * Bu sorğuların replika-ya yönləndirilməsi primary-nin CPU-sunu azaldır.
     */
    public function getRecentOrders(int $userId, int $limit = 20): Collection
    {
        return DB::connection('mysql_read')
            ->table('orders')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Sifariş tarixi (dashboard) — replica-dan.
     *
     * Niyə: tarixi məlumatlar dəyişmir, stale data riski minimal.
     */
    public function getOrderHistory(int $userId, array $filters = []): Collection
    {
        $query = DB::connection('mysql_read')
            ->table('orders')
            ->where('user_id', $userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
```

### Product Service

```php
// app/Services/ProductService.php
namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Product listing — replica ideal seçimdir.
     * 95% trafik buradan gəlir. Replica olmasa primary batır.
     */
    public function listProducts(array $filters = []): LengthAwarePaginator
    {
        $query = DB::connection('mysql_read')
            ->table('products')
            ->where('active', true);

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'LIKE', "%{$filters['search']}%")
                  ->orWhere('description', 'LIKE', "%{$filters['search']}%");
            });
        }

        return $query
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Product detail — replica-dan.
     * Stale description/price 200ms geridə olması qəbul edilə bilər.
     */
    public function getProduct(int $productId): ?object
    {
        return DB::connection('mysql_read')
            ->table('products')
            ->where('id', $productId)
            ->where('active', true)
            ->first();
    }

    /**
     * Checkout-dan əvvəl qiymət yoxlaması — PRIMARY-dən.
     *
     * Niyə: replica-dakı qiyməti göstərdin, primary-dəki qiymət artıq dəyişmiş ola bilər.
     * User köhnə qiymətlə ödəniş edir → biznes itkisi (qiymət artıbsa) və ya
     * müştəri şikayəti (qiymət azalıbsa).
     */
    public function getPriceForCheckout(int $productId): ?float
    {
        return DB::connection('mysql')
            ->table('products')
            ->where('id', $productId)
            ->where('active', true)
            ->value('price');
    }
}
```

---

## Read-Your-Writes Problemi

Bu pattern-in ən çox qurban verdiyi UX ssenarisi:

```
1. User review yazır  → POST /reviews  → Primary-ə yazılır ✓
2. Redirect: /reviews sayfasına          (yeni HTTP request)
3. GET /reviews       → Replica-dan oxunur
4. Replica 300ms geridədir              → User-in öz review-i görünmür!
5. User: "Mənim review-im hara getdi?"
```

### Sessiya əsaslı Sticky Read

```php
// app/Http/Middleware/StickyPrimaryMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class StickyPrimaryMiddleware
{
    // Write-dan sonra neçə saniyə primary-dən oxumaq lazımdır
    private const PRIMARY_STICKY_SECONDS = 5;

    /**
     * Write əməliyyatından sonra növbəki request-ləri primary-ə yönləndirir.
     *
     * Alternativ: Laravel-in `sticky: true` — yalnız eyni request-də işləyir.
     * Bu middleware cross-request sticky behavior-u təmin edir.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sessiyada "primary-dən oxu" flag aktiv ola bilər
        $usePrimaryUntil = session('use_primary_until', 0);

        if (time() < $usePrimaryUntil) {
            // Hər iki connection-u primary-yə yönləndir
            Config::set(
                'database.connections.mysql_read.host',
                Config::get('database.connections.mysql.write.host')
            );
        }

        $response = $next($request);

        // Write əməliyyatı baş verdisə növbəki request-lər üçün flag qoy
        // Bu yoxlama sadələşdirilmişdir; real halda DB::getQueryLog() yoxlanır
        if ($this->requestHadWrites($request)) {
            session(['use_primary_until' => time() + self::PRIMARY_STICKY_SECONDS]);
        }

        return $response;
    }

    private function requestHadWrites(Request $request): bool
    {
        // POST/PUT/PATCH/DELETE metodları adətən write əməliyyatı edir
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }
}
```

### Write Flag Service

```php
// app/Services/WriteTracker.php
namespace App\Services;

use Illuminate\Support\Facades\Session;

class WriteTracker
{
    private const SESSION_KEY = 'db_write_at';
    private const STICKY_SECONDS = 5;

    /**
     * Write əməliyyatından sonra çağırılır.
     * Növbəki request-lərin primary-dən oxumasını təmin edir.
     */
    public function markWriteOccurred(): void
    {
        Session::put(self::SESSION_KEY, time());
    }

    /**
     * Primary-dən oxumaq lazım olduğunu yoxlayır.
     */
    public function shouldUsePrimary(): bool
    {
        $writeAt = Session::get(self::SESSION_KEY, 0);
        return time() - $writeAt < self::STICKY_SECONDS;
    }

    /**
     * Service layer-da istifadə — service özü write-ı qeyd edir.
     */
    public function getReadConnection(bool $isCritical = false): \Illuminate\Database\Connection
    {
        if ($isCritical || $this->shouldUsePrimary()) {
            return \DB::connection('mysql');
        }

        return \DB::connection('mysql_read');
    }
}
```

---

## Trade-off Cədvəli

| Yanaşma | Üstünlüklər | Risklər | Nə zaman istifadə et |
|---------|-------------|---------|----------------------|
| **sticky: true (built-in)** | Zero config, avtomatik | Yalnız eyni request-də işləyir; lag yoxlaması yoxdur | Başlanğıc həll, sadə application-lar |
| **Lag-aware routing** | Stale data riskini azaldır; avtomatik fallback | Hər 10s bir `SHOW SLAVE STATUS` — minimal overhead | Replica lag-ı dəyişkən olan yüklü sistemlər |
| **Explicit connection seçimi** | Tam nəzarət; kod niyyəti aydın olur | Boilerplate artır; yeni developer-lər səhv edə bilər | Kritik vs. qeyri-kritik read-ləri fərqli olan sistemlər |
| **Bütün sorğuları primary-dən** | Sadəlik; heç vaxt stale data yoxdur | Replica boşa gedir; primary CPU problem həll olunmur | DEYİL — replica-nın heç bir mənası qalmır |

---

## Anti-patternlər

**1. Ödəniş statusunu replica-dan oxumaq**
User yeni sifariş verdi, ödəniş confirmed oldu, amma replica 2 saniyə geridədir. User "pending" görür, dərhal dəstəklə əlaqə saxlayır. Həll: ödəniş, inventory, balans kimi kritik data həmişə primary-dən oxunmalıdır.

**2. Replication lag-ı monitoring etməmək**
Normal vaxt lag 50ms, peak saatda 30 saniyə. Heç bir alert yoxdur. User-lər köhnəlmiş data görür, support şikayəti artır, siz niyə olduğunu başa düşmürsünüz. Həll: `Seconds_Behind_Master` metrikasını Prometheus/Datadog-a çəkin, 5 saniyə keçəndə alert qoyun.

**3. Replica down olduqda fallback olmamaq**
Replica maintenance-ə gedir. Bütün read sorğuları `Connection refused` xətası ilə partlayır. Həll: try/catch + primary-ə fallback mütləq olmalıdır.

**4. sticky: true-nu anlamadan istifadə etmək**
Developer düşünür: "sticky: true qoydum, artıq read-your-writes problemi yoxdur." Sonra istifadəçi yorum yazdıqdan sonra başqa səhifəyə (yeni request) keçir — yorum görünmür. sticky yalnız eyni HTTP request daxilində işləyir. Həll: cross-request sticky üçün sessiya əsaslı middleware lazımdır.

**5. Replica-dan stale data oxuyub write etmək**
```php
// YANLIŞ!
$count = DB::connection('mysql_read')->table('inventory')->value('quantity'); // 5 görünür
if ($count > 0) {
    // Primary-də əslində 0-dır (başqası almışdı)
    DB::table('inventory')->decrement('quantity'); // -1 oldu!
}

// DOĞRU: Kritik write-dan əvvəl primary-dən oxu
$count = DB::connection('mysql')->table('inventory')
    ->lockForUpdate()
    ->value('quantity');
```

**6. Bütün oxuma sorğularını primary-dən oxumaq**
"Sadə olsun" deyə hər şeyi primary-ə yönləndirirsən. Replica var, amma boşdur. Primary CPU 90%-də qalmaqda davam edir. Bu, sistemin niyə yavaşladığını həll etmir — read replica-nı tam istifadəsiz saxlayır.

**7. Replica-nı primary ilə eyni fiziki serverdə host etmək**
Disk failure oldu — həm primary, həm replica getdi. Replication-ın əsas məqsədlərindən biri high availability-dir. Replica mütləq ayrı fiziki hardware-da (ideally ayrı datacenter) olmalıdır.

---

## Praktik Tapşırıqlar

### Tapşırıq 1 — Konfiqurasiya və Test

1. `config/database.php`-də read/write konfiqurasiyasını set up edin
2. `SHOW SLAVE STATUS` ilə replica-nı yoxlayın
3. `DB::listen()` ilə sorğuların hansı connection-a getdiyini müşahidə edin:

```php
// AppServiceProvider::boot() içində
DB::listen(function ($query) {
    \Log::debug('DB Query', [
        'sql' => $query->sql,
        'connection' => $query->connectionName,
        'time' => $query->time,
    ]);
});
```

### Tapşırıq 2 — Lag-Aware Service

1. `ReplicaHealthService` yazın
2. `SHOW SLAVE STATUS`-u parse edin
3. Cache-in düzgün işlədiyini test edin (10 saniyə ərzində DB-yə yalnız 1 sorğu getməlidir)
4. Replica-nı əl ilə stop edin, fallback-ın işlədiyini yoxlayın:

```bash
# MySQL replica-nı dayandırmaq (test üçün)
mysql -h 10.0.1.2 -e "STOP SLAVE;"

# Test sonrası yenidən başlatmaq
mysql -h 10.0.1.2 -e "START SLAVE;"
```

### Tapşırıq 3 — Read-Your-Writes

1. User review yazdıqda `WriteTracker::markWriteOccurred()` çağırın
2. Növbəki request-də `shouldUsePrimary()` yoxlamasını tətbiq edin
3. Manuel test: review yazın, dərhal redirect olun, review-in görünüb-görünmədiyini yoxlayın

### Tapşırıq 4 — Monitoring Dashboard

```php
// Artisan command: replica status-unu yoxlamaq
// php artisan replica:status

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckReplicaStatus extends Command
{
    protected $signature = 'replica:status';
    protected $description = 'Replica replication lag-ını göstər';

    public function handle(): int
    {
        $connections = ['mysql_read_1', 'mysql_read_2'];

        $rows = [];
        foreach ($connections as $connection) {
            try {
                $status = DB::connection($connection)->selectOne('SHOW SLAVE STATUS');
                $rows[] = [
                    $connection,
                    $status->Seconds_Behind_Master ?? 'NULL',
                    $status->Slave_IO_Running ?? 'N/A',
                    $status->Slave_SQL_Running ?? 'N/A',
                ];
            } catch (\Throwable $e) {
                $rows[] = [$connection, 'DOWN', 'N/A', 'N/A'];
            }
        }

        $this->table(
            ['Connection', 'Lag (s)', 'IO Thread', 'SQL Thread'],
            $rows
        );

        return 0;
    }
}
```

---

## Interview Sualları və Cavablar

**S: Eventual consistency vs. strong consistency — replika-da hansını seçirsiniz?**

C: Default olaraq eventual consistency qəbul edirik — çoxu read-in 200ms stale olması problem yaratmır (product listing, user profile, tarixi sifarişlər). Amma kritik data üçün — ödəniş statusu, inventory sayı, balans — strong consistency tələb edirik, yəni primary-dən oxuyuruq. Seçim mövzuya görə dəyişir; blanket policy "hər şey eventual" və ya "hər şey strong" — ikisi də yanlışdır.

**S: Read-your-writes-ı necə idarə edirsiniz?**

C: Üç təbəqə: (1) `sticky: true` — eyni request daxilindəki write-dan sonra read-i primary-ə yönləndirir; (2) sessiya əsaslı sticky middleware — write baş verdikdən sonra 5 saniyə ərzindəki bütün request-ləri primary-yə yönləndirir; (3) kritik read-ləri həmişə explicit olaraq primary-dən oxumaq. Üçü birlikdə istifadə edilir.

**S: Replication lag-ı necə monitor edirsiniz?**

C: `SHOW SLAVE STATUS`-dan `Seconds_Behind_Master` metrikasını 30 saniyədə bir Prometheus-a push edirik. Grafana dashboard-da real-time görünür. Alert şərtləri: lag > 5s → Warning, lag > 30s → Critical (on-call tetiklenir). Bundan əlavə replica I/O thread-in running olub olmadığını da yoxlayırıq — bəzən lag artmır amma replication tamam durur.

**S: Replika nə zaman istifadə etməmək lazımdır?**

C: (1) Write-intensive workload — yazma 80%+ olduqda replica-ya yönləndirilən sorğu azdır, overhead-a dəyməz. (2) Çox kiçik trafik — bir serverin öhdəsindən gəldiyi yükdə operational complexity əlavə etmək gərəkmiyor. (3) Zero replication lag tələb oldunan sistemlər — bütün oxumalar strong consistency istəsə, replika heç işə yaramır. (4) Hesabat/analytics üçün daha yaxşı OLAP tool-ları var (ClickHouse, BigQuery) — replica-da ağır analitik sorğu çalışdırmaq yanlış yanaşmadır.

**S: Multi-region deployment-də replication necə dəyişir?**

C: Inter-region latency 50-150ms olur, bu da replication lag-a birbaşa əlavə olunur. Yəni başqa region-dakı replica 100-300ms geridədir — normal. Bu halda: (1) Hər region öz replica set-inə malikdir; (2) Write-lar həmişə primary region-a gedir (cross-region write qəbul edilmir); (3) Local read-ləri local replica-dan edirik; (4) Lagı daha geniş qəbul edirik (MAX_LAG_SECONDS = 30). Əgər global strong consistency lazımdırsa, CockroachDB/Spanner kimi distributed SQL sistemləri nəzərdən keçirilməlidir — amma qiymət/complexity dəfələrlə artır.

---

## Əlaqəli Mövzular

- `62-database-transactions.md` — Write əməliyyatlarında transaction idarəsi
- `63-pessimistic-optimistic-locking.md` — Race condition-un önlənməsi
- `64-database-connection-pooling.md` — Connection overhead azaldılması
- `65-caching-strategies.md` — Read replica-nı tamamlayan cache layer
