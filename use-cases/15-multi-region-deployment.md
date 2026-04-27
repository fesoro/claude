# Multi-Region Deployment Dizaynı (Lead)

## Problem

Böyük miqyaslı tətbiq dünyada müxtəlif regionlarda istifadəçilərə xidmət göstərməlidir: Avropa (EU), Amerika (US), Asiya (Asia). Əsas problemlər:

- **Latency**: İstifadəçi Tokiodadırsa, ABŞ-dakı serverə sorğu göndərməsi 200-300ms gecikmə yaradır
- **Data Residency**: GDPR tələb edir ki, Avropa istifadəçilərinin datası EU sərhədlərindən çıxmasın
- **Availability**: Bir region çökəndə digər regionlar işləməyə davam etməlidir
- **Consistency**: Bütün regionlarda eyni data görünməlidir (eventual vs strong consistency)

---

## Active-Active vs Active-Passive

### Active-Passive (Standby)

```
[Users] → [Primary Region: EU] ←→ [Standby Region: US]
                                        (passive, read-only replica)
```

- Yalnız bir region yazma qəbul edir
- Failover zamanı standby region aktiv olur (1-5 dəqiqə downtime)
- Sadə implementation, lakin resurs israfı
- Use case: Kiçik tətbiqlər, budget-conscious deploymentlər

### Active-Active (Multi-Master)

```
[EU Users] → [EU Region] ←→ sync ←→ [US Region] ← [US Users]
                    ↕                        ↕
              [Asia Region] ←→ sync ←→ [Asia Region]
```

- Bütün regionlar yazma qəbul edir
- Çox aşağı latency
- Conflict resolution lazımdır
- Use case: Global SaaS, e-commerce, social media

---

## Data Replication Strategiyaları

### Synchronous Replication

```
Write Request → Primary → [replicate] → Secondary1 → ack
                                     → Secondary2 → ack
                        → Confirm write after ALL acks
```

- **Müsbət**: Strong consistency, heç bir data itkisi yoxdur
- **Mənfi**: Yüksək latency (bütün replica-lar cavab verənə qədər gözləmək)
- Use case: Maliyyə tranzaksiyaları, tibbi qeydlər

### Asynchronous Replication

```
Write Request → Primary → Confirm write immediately
                       → [async replicate] → Secondary1
                                          → Secondary2
```

- **Müsbət**: Aşağı latency, primary node cavab verə bilir
- **Mənfi**: Replication lag — secondary-lər geridə qala bilər
- Use case: Sosial media postları, analitik məlumatlar

---

## Eventual Consistency

Eventual consistency o deməkdir ki, bütün yazma əməliyyatları nəticədə bütün replica-lara çatacaq — lakin eyni anda deyil.

**CAP Teoremi**: Distributed sistemdə eyni anda 3-dən yalnız 2-ni əldə etmək olar:
- **C**onsistency — bütün node-lar eyni datanı görür
- **A**vailability — hər sorğu cavab alır
- **P**artition tolerance — şəbəkə bölünməsinə dözümlülük

Çox zaman **AP** (Available + Partition tolerant) seçilir, yəni eventual consistency qəbul edilir.

---

## Conflict Resolution

### Last-Write-Wins (LWW)

Ən sadə yanaşma: timestamp-ə baxaraq ən son yazılanı qəbul et.

*Bu kod timestamp müqayisəsi ilə Last-Write-Wins conflict resolution strategiyasını göstərir:*

```php
// Hər record-da updated_at + region_id saxlanılır
// Conflict zamanı ən böyük timestamp qalib gəlir
if ($incomingRecord->updated_at > $existingRecord->updated_at) {
    $existingRecord->update($incomingRecord->toArray());
}
```

**Problem**: Clock skew — müxtəlif serverlarda saatlar tam sinxron olmaya bilər.

### Vector Clocks

Hər node öz sayğacını saxlayır. Konflikt zamanı hansı versiyanın daha yeni olduğunu müəyyən etmək mümkündür.

*Bu kod hər node üçün ayrı sayğac saxlayan Vector Clock data strukturunu göstərir:*

```php
// Vector clock nümunəsi
// [eu: 3, us: 2, asia: 1] — EU 3 dəfə yazmışdır
class VectorClock
{
    private array $clock = [];

    public function increment(string $nodeId): void
    {
        $this->clock[$nodeId] = ($this->clock[$nodeId] ?? 0) + 1;
    }

    public function merge(array $other): void
    {
        foreach ($other as $node => $time) {
            $this->clock[$node] = max($this->clock[$node] ?? 0, $time);
        }
    }

    public function happensBefore(array $other): bool
    {
        foreach ($this->clock as $node => $time) {
            if ($time > ($other[$node] ?? 0)) {
                return false;
            }
        }
        return $this->clock !== $other;
    }
}
```

### CRDTs (Conflict-free Replicated Data Types)

Xüsusi data strukturları ki, avtomatik olaraq konflikt olmadan birləşdirilə bilər.

- **G-Counter**: Yalnız artım, hər node öz sayğacını saxlayır
- **PN-Counter**: Həm artım, həm azalma
- **LWW-Register**: Last-write-wins register
- **OR-Set**: Add/remove konfliktlərini həll edir

---

## Geo-Routing

### AWS Route53

```
DNS sorğusu → Route53 → Latency-based routing → Ən yaxın region
                      → Geolocation routing → GDPR üçün EU → EU region
                      → Health check → Sağlam region seç
```

### Cloudflare

```
User → Cloudflare Edge (200+ PoP) → Origin server (ən yaxın)
```

Cloudflare Workers ilə region routing:

*Bu kod Cloudflare Worker edge-də ölkəyə görə sorğunu uyğun regionun origin serverinə yönləndirməyi göstərir:*

```javascript
// Cloudflare Worker — edge-də region seçimi
addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const country = request.cf.country;
    const euCountries = ['DE', 'FR', 'IT', 'ES', 'NL', 'PL'];

    let origin;
    if (euCountries.includes(country)) {
        origin = 'https://eu.myapp.com';
    } else if (request.cf.continent === 'AS') {
        origin = 'https://asia.myapp.com';
    } else {
        origin = 'https://us.myapp.com';
    }

    return fetch(origin + new URL(request.url).pathname, request);
}
```

---

## Database Strategiyaları

### Database per Region

```
EU Region → EU PostgreSQL (master)
US Region → US PostgreSQL (master)
Asia Region → Asia PostgreSQL (master)

Cross-region sync: CDC (Change Data Capture) ilə
```

**Müsbət**: Tam data residency, aşağı latency
**Mənfi**: Cross-region join-lər mümkün deyil, sync mürəkkəbdir

### Global Database

```
Amazon Aurora Global Database:
- 1 Primary Region (yazma)
- 5-ə qədər Secondary Region (oxuma)
- <1 saniyə replication lag
- Failover: ~1 dəqiqə
```

### Read from Nearest, Write to Home Region

```
User (Tokyo) oxuma sorğusu → Asia replica (10ms latency)
User (Tokyo) yazma sorğusu → US Primary (150ms latency) [home region]
                           → async replication → Asia replica
```

---

## Laravel-də Implementation

### Multi-Region Database Config

*Bu kod hər region üçün ayrı read replica connection-larını konfiqurasiya edən database.php-ni göstərir:*

```php
// config/database.php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        // Primary (yazma üçün)
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE'),
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            'sticky'    => true, // write-after-read consistency
        ],

        // EU replica (oxuma üçün)
        'mysql_eu_read' => [
            'driver'   => 'mysql',
            'host'     => env('DB_EU_READ_HOST'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'sticky'   => false,
        ],

        // Asia replica (oxuma üçün)
        'mysql_asia_read' => [
            'driver'   => 'mysql',
            'host'     => env('DB_ASIA_READ_HOST'),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'sticky'   => false,
        ],
    ],
];
```

### Region-Aware Database Service

*Bu kod cari region-a görə ən yaxın read replica-nı seçib, yazma əməliyyatlarını primary-ə yönləndirən service-i göstərir:*

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RegionAwareDatabaseService
{
    private string $currentRegion;
    private string $userHomeRegion;

    // Read replica mapping: region → connection name
    private array $readConnections = [
        'eu'    => 'mysql_eu_read',
        'us'    => 'mysql_us_read',
        'asia'  => 'mysql_asia_read',
    ];

    public function __construct()
    {
        $this->currentRegion = config('app.region', 'us');
        $this->userHomeRegion = $this->resolveUserHomeRegion();
    }

    /**
     * Oxuma sorğuları üçün — ən yaxın region replica-sını istifadə et
     */
    public function readConnection(): string
    {
        return $this->readConnections[$this->currentRegion] ?? 'mysql';
    }

    /**
     * Yazma sorğuları üçün — həmişə primary (home region) istifadə et
     */
    public function writeConnection(): string
    {
        return 'mysql'; // primary connection
    }

    /**
     * GDPR: EU istifadəçilərinin datası yalnız EU-da saxlanılmalıdır
     */
    public function getConnectionForUser(int $userId): string
    {
        $userRegion = cache()->remember(
            "user_region_{$userId}",
            3600,
            fn() => DB::table('users')->where('id', $userId)->value('home_region')
        );

        // Data residency: EU istifadəçisi üçün EU connection
        if ($userRegion === 'eu' && $this->currentRegion === 'eu') {
            return 'mysql_eu_primary';
        }

        return 'mysql';
    }

    private function resolveUserHomeRegion(): string
    {
        // Header-dən, session-dan və ya IP-dən region müəyyən et
        return request()->header('X-User-Region', $this->currentRegion);
    }
}
```

### Region-Aware Cache Service

*Bu kod region-spesifik cache key-ləri istifadə edən, global invalidation üçün Redis Pub/Sub-dan yararlanan cache service-ini göstərir:*

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class RegionAwareCacheService
{
    private string $region;

    public function __construct()
    {
        $this->region = config('app.region', 'us');
    }

    /**
     * Region-spesifik cache key — fərqli regionlarda eyni key konflikt yaratmır
     */
    public function key(string $baseKey): string
    {
        return "{$this->region}:{$baseKey}";
    }

    /**
     * Local region cache-dən oxu
     */
    public function get(string $key): mixed
    {
        return Cache::store('redis_local')->get($this->key($key));
    }

    /**
     * Cache yaz — yalnız local region-a
     */
    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        Cache::store('redis_local')->put($this->key($key), $value, $ttl);
    }

    /**
     * Global invalidation — bütün regionlarda cache sil
     * Redis Pub/Sub ilə digər regionlara xəbər göndər
     */
    public function invalidateGlobally(string $key): void
    {
        // Local-dan sil
        Cache::store('redis_local')->forget($this->key($key));

        // Digər regionlara invalidation event göndər
        Redis::publish('cache-invalidation', json_encode([
            'key'        => $key,
            'source'     => $this->region,
            'timestamp'  => now()->toIso8601String(),
        ]));
    }

    /**
     * Cross-region cache — bütün regionlarda eyni dəyər saxla
     * (region-neutral data üçün: config, feature flags və s.)
     */
    public function putGlobal(string $key, mixed $value, int $ttl = 3600): void
    {
        foreach (['eu', 'us', 'asia'] as $region) {
            Cache::store("redis_{$region}")->put($key, $value, $ttl);
        }
    }
}
```

### Distributed Session Handler

*Bu kod bütün regionların oxuya bildiyinə global Redis cluster-ında session saxlayan custom session handler-i göstərir:*

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class DistributedSessionHandler implements \SessionHandlerInterface
{
    private string $prefix = 'session:';
    private int $lifetime;

    public function __construct(int $lifetime = 7200)
    {
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * Session oxu — hər regionda eyni Redis cluster-dan
     * (Global Redis cluster: ElastiCache Global Datastore və ya Redis Enterprise)
     */
    public function read(string $id): string|false
    {
        $data = Redis::connection('global')->get($this->prefix . $id);
        return $data ?? '';
    }

    /**
     * Session yaz — global Redis-ə, bütün regionlar oxuya bilər
     */
    public function write(string $id, string $data): bool
    {
        Redis::connection('global')->setex(
            $this->prefix . $id,
            $this->lifetime,
            $data
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        Redis::connection('global')->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis TTL-i özü idarə edir
        return 0;
    }
}
```

### config/session.php — Global Session Config

*Bu kod bütün regionlarda eyni session-u təmin edən global Redis connection-lu session konfigurasiyasını göstərir:*

```php
// config/session.php
return [
    'driver'   => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'connection' => 'global', // Global Redis connection

    // Cookie region-independent olmalıdır
    'domain'   => env('SESSION_DOMAIN', '.myapp.com'),
    'secure'   => true,
    'same_site' => 'lax',
];
```

### Health Check Endpoint

*Bu kod DB, Redis və cache-in latency-sini ölçərək load balancer üçün region sağlamlıq yoxlaması yapan controller-i göstərir:*

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'region'    => config('app.region'),
            'timestamp' => now()->toIso8601String(),
            'status'    => 'ok',
            'checks'    => [],
        ];

        // Database health check
        try {
            DB::connection()->getPdo();
            $checks['checks']['database'] = ['status' => 'ok', 'latency_ms' => $this->measureLatency(fn() => DB::select('SELECT 1'))];
        } catch (\Exception $e) {
            $checks['checks']['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            $checks['status'] = 'degraded';
        }

        // Redis health check
        try {
            Redis::ping();
            $checks['checks']['redis'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $checks['checks']['redis'] = ['status' => 'error', 'message' => $e->getMessage()];
            $checks['status'] = 'degraded';
        }

        // Replication lag check
        try {
            $lag = $this->checkReplicationLag();
            $checks['checks']['replication_lag_seconds'] = $lag;
            if ($lag > 30) {
                $checks['status'] = 'degraded';
            }
        } catch (\Exception $e) {
            $checks['checks']['replication'] = ['status' => 'unknown'];
        }

        $httpStatus = $checks['status'] === 'ok' ? 200 : 503;

        return response()->json($checks, $httpStatus);
    }

    private function measureLatency(callable $fn): float
    {
        $start = microtime(true);
        $fn();
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function checkReplicationLag(): int
    {
        // MySQL replication lag-ı yoxla
        $status = DB::connection('mysql_read')->select('SHOW SLAVE STATUS');
        return $status[0]->Seconds_Behind_Master ?? 0;
    }
}
```

### Failover Logic — Middleware

*Failover Logic — Middleware üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegionFailoverMiddleware
{
    private array $fallbackOrder = ['us', 'eu', 'asia'];

    public function handle(Request $request, Closure $next): mixed
    {
        // Primary connection sağlamlığını yoxla
        if (!$this->isPrimaryHealthy()) {
            $this->switchToFallback();
        }

        return $next($request);
    }

    private function isPrimaryHealthy(): bool
    {
        return cache()->remember('primary_db_health', 30, function () {
            try {
                DB::connection('mysql')->select('SELECT 1');
                return true;
            } catch (\Exception $e) {
                Log::critical('Primary database is down', [
                    'region' => config('app.region'),
                    'error'  => $e->getMessage(),
                ]);
                return false;
            }
        });
    }

    private function switchToFallback(): void
    {
        $currentRegion = config('app.region');

        foreach ($this->fallbackOrder as $region) {
            if ($region === $currentRegion) {
                continue;
            }

            if ($this->isRegionHealthy($region)) {
                Log::warning("Failing over to region: {$region}");
                config(['database.default' => "mysql_{$region}"]);
                break;
            }
        }
    }

    private function isRegionHealthy(string $region): bool
    {
        try {
            DB::connection("mysql_{$region}")->select('SELECT 1');
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
```

### Region Routing — Laravel AppServiceProvider

*Region Routing — Laravel AppServiceProvider üçün kod nümunəsi:*
```php
<?php

namespace App\Providers;

use App\Http\Middleware\RegionFailoverMiddleware;
use App\Services\RegionAwareDatabaseService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegionAwareDatabaseService::class);
    }

    public function boot(): void
    {
        // Request geldiğinde region-ı müəyyən et
        $this->detectRegion();
    }

    private function detectRegion(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        // 1. App region environment variable-dan
        $region = config('app.region');

        // 2. Header-dən (load balancer tərəfindən set edilir)
        if (request()->hasHeader('X-Region')) {
            $region = request()->header('X-Region');
        }

        // 3. CloudFront/Cloudflare header-ləri
        if (request()->hasHeader('CloudFront-Viewer-Country')) {
            $country = request()->header('CloudFront-Viewer-Country');
            $region = $this->countryToRegion($country);
        }

        config(['app.current_region' => $region]);
    }

    private function countryToRegion(string $countryCode): string
    {
        $euCountries = ['DE', 'FR', 'GB', 'IT', 'ES', 'NL', 'SE', 'PL', 'AT', 'BE'];
        $asiaCountries = ['JP', 'CN', 'KR', 'IN', 'SG', 'AU', 'TH', 'VN', 'ID'];

        if (in_array($countryCode, $euCountries)) {
            return 'eu';
        }

        if (in_array($countryCode, $asiaCountries)) {
            return 'asia';
        }

        return 'us';
    }
}
```

---

## CDN Strategiyası

```
Static assets → CloudFront/Cloudflare (edge cache)
API responses → Region-specific CDN (seçici)
User uploads  → S3 + CloudFront (region-based bucket)
```

*User uploads  → S3 + CloudFront (region-based bucket) üçün kod nümunəsi:*
```php
// config/filesystems.php — Multi-region S3
'disks' => [
    's3_eu' => [
        'driver' => 's3',
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => 'eu-west-1',
        'bucket' => env('AWS_EU_BUCKET'),
        'url'    => 'https://eu-cdn.myapp.com',
    ],
    's3_us' => [
        'driver' => 's3',
        'region' => 'us-east-1',
        'bucket' => env('AWS_US_BUCKET'),
        'url'    => 'https://us-cdn.myapp.com',
    ],
],
```

---

## DNS Failover

Route53 Health Check + Failover routing:

```
Primary Record: eu.myapp.com → EU Load Balancer (health check: /health)
Secondary Record: eu.myapp.com → US Load Balancer (failover)

Failover TTL: 60 saniyə (aşağı TTL = sürətli failover, lakin daha çox DNS sorğu)
```

---

## İntervyu Sualları

**S: Active-Active vs Active-Passive fərqi nədir, nə zaman hansını seçərsiniz?**

C: Active-Active-də bütün regionlar yazma qəbul edir — daha yüksək availability, daha aşağı latency, lakin conflict resolution lazımdır. Active-Passive-də yalnız bir region aktiv yazır — sadədir, lakin failover zamanı downtime var. Budget məhdudiyyəti varsa Active-Passive, global yüksək availability lazımdırsa Active-Active seçilir.

**S: Eventual consistency nə zaman qəbul edilə bilər, nə zaman edilə bilməz?**

C: Sosial media like sayı, oxunma sayı, analitik — eventual consistency qəbul edilə bilər. Bank balansı, inventar miqdarı, sifarişlər — strong consistency lazımdır.

**S: Cross-region latency necə minimizə edilir?**

C: Read replica-larını istifadəçiyə yaxın regionda yerləşdir. Write-ları home region-a yönləndir. CDN-lə static content-i edge-ə keş et. Connection pooling və persistent connections istifadə et.

**S: Data residency tələblərini necə həyata keçirərdiniz?**

C: User-in home region-ını qeydiyyat zamanı müəyyən et. Bütün həmin istifadəçinin datası yalnız həmin region-ın database-inə yazılsın. Cross-region replikasiya yalnız anonymized/aggregated data üçün. GDPR audit log-larını ayrıca saxla.

**S: Region çökdükdə istifadəçiləri necə başqa regiona yönləndirərdiniz?**

C: Route53 health check-lər primary region-ı monitor edir. Health check fail olarsa, DNS avtomatik failover region-ına yönləndirir (TTL: 60s). Tətbiq səviyyəsində isə middleware health endpoint-lərini yoxlayır, DB connection-ı dinamik dəyişdirir.

**S: Split-brain problemi nədir, Active-Active-də necə baş verir?**
C: İki region eyni anda eyni resursu yeniləyirsə və aralarındakı şəbəkə kəsilirsə, hər iki region "özü master-dır" düşünür. Şəbəkə bərpa olduqda iki fərqli versiya var — conflict. Həll: Last-Write-Wins (timestamp əsaslı, clock skew riski var), Vector Clocks (hansının daha sonra yazıldığını müəyyən edir), ya da yazma əməliyyatları üçün yalnız bir "home region" istifadə et — oxuma üçün aktiv-aktiv, yazma üçün home-region routing.

**S: Replication lag nədir, hansı ssenarilərdə problem yaradır?**
C: Async replication-da primary-a yazılan data replica-ya gecikmə ilə çatır (millisaniyədən saniyələrə). Problem: istifadəçi yazır (primary), dərhal oxuyur (replica) — köhnə data görür. Həll: `sticky: true` Laravel DB config-ında (eyni request-də yazandan sonra primary-dan oxu). Kritik data-nı həmişə primary-dan oxu, statistik data-nı replica-dan oxu. Health endpoint-ə replication lag yoxlaması əlavə et — 30 saniyədən çox lag varsa `degraded` status qaytar.

**S: GDPR və multi-region birlikdə: EU user-inin datası US server-ə çatarsa nə olur?**
C: GDPR Article 44-46: EU datası yalnız "adequate protection" ölkələrinə transfer edilə bilər. Həll: EU user-lərini qeydiyyat zamanı EU region-a assign et, bütün yazma əməliyyatları EU DB-sinə getsin. Cross-region replikasiyada EU user PII-si US-ə getməsin. Həssas data üçün field-level şifrələmə (açar yalnız EU-da qalır).

**S: Multi-region-da database migration necə aparılır?**
C: Expand/Contract pattern burada daha vacibdir: yeni sütun əvvəlcə bütün regionlara eyni anda deploy edilir (backward compatible). Kod dəyişikliyi bütün regionlarda yeni sütunu istifadə etdikdən sonra köhnəsi silinir. Blue-Green deployment: hər region üçün ayrıca rolling deploy. Alternativ: AWS Aurora Global Database — migration primary region-da edilir, secondary-lar avtomatik sync olunur.

---

## Anti-patterns

**1. Bütün regionlarda eyni DB-ə yazma**
US region-dan EU DB-yə write — 200ms+ cross-region latency. Hər region öz primary DB-inə yazmalı, async replikasiya ilə sync olunmalıdır.

**2. Session-ları local server-ə saxlamaq**
User EU server-ə bağlandı, sonra US server-ə yönləndirildi — session yoxdur. Session mərkəzi Redis cluster-ında saxlanmalıdır.

**3. Çox uzun DNS TTL failover üçün**
TTL 3600s olduqda region çöküşündə 1 saat keçənə qədər köhnə IP-ə yönlənməyə davam edir. Health check-based routing ilə TTL 60s olmalıdır.

**4. Cross-region write-ları sync transaction-la idarə etmək**
2PC cross-region → blocking, 200ms+ latency. Eventual consistency + Saga pattern regional write-lar üçün daha uyğundur.

**5. Stateful session olmayan endpoint-lərdə sticky session**
Load balancer sticky session = bütün trafik bir serverə gedir, horizontal scaling faydası itir. Stateless JWT + merkəzi session store daha yaxşıdır.
