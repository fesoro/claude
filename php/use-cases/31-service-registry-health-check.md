# 31. Service Registry və Health Check

## Ssenari

Siz böyük bir e-ticarət platforması inkişaf etdirirsiniz. Sistem 15+ mikroservisdən ibarətdir: `order-service`, `payment-service`, `inventory-service`, `user-service`, `notification-service`, `shipping-service` və s. Hər servis müxtəlif serverlərda deploy olunub və auto-scaling ilə instance sayı dinamik olaraq dəyişir.

**Problemlər:**

- `order-service` `payment-service`-ə müraciət etmək istəyir, amma hansı IP/port-da işlədiyini bilmir
- Yeni instance yarananda digər servislər bundan xəbərsizdir
- Bir instance çökürsə, trafikin ora göndərilməsi davam edir — istifadəçilər xəta alır
- Deployment zamanı köhnə instance hələ request qəbul edir, yenisi isə hazır deyil
- Database bağlantısı kəsilib, amma servis "sağlam" görünür

**Həll yolu:** Service Registry (Consul/etcd) ilə servislərin avtomatik qeydiyyatı, Health Check mexanizmləri ilə sağlamlıq monitorinqi və automatic failover ilə etibarlı kommunikasiya.

---

## Arxitektura

```
                         ┌─────────────────────────────────┐
                         │        CONSUL CLUSTER            │
                         │                                  │
                         │  ┌───────────┐  ┌───────────┐   │
                         │  │  Leader    │  │ Follower  │   │
                         │  │  Node      │──│ Node      │   │
                         │  └───────────┘  └───────────┘   │
                         │        │         ┌───────────┐   │
                         │        │         │ Follower  │   │
                         │        └─────────│ Node      │   │
                         │                  └───────────┘   │
                         └──────────┬──────────────────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
     ┌────────▼────────┐  ┌────────▼────────┐  ┌────────▼────────┐
     │  order-service   │  │ payment-service │  │inventory-service│
     │  ┌────────────┐  │  │  ┌────────────┐ │  │ ┌────────────┐  │
     │  │ /health    │  │  │  │ /health    │ │  │ │ /health    │  │
     │  │ /ready     │  │  │  │ /ready     │ │  │ │ /ready     │  │
     │  └────────────┘  │  │  └────────────┘ │  │ └────────────┘  │
     │  Instance 1..N   │  │  Instance 1..N  │  │ Instance 1..N   │
     └──────────────────┘  └─────────────────┘  └─────────────────┘
              │                     │                     │
              │         ┌──────────▼──────────┐          │
              │         │   LOAD BALANCER     │          │
              └────────►│   (Consul-aware)    │◄─────────┘
                        │                     │
                        │  - Health filtering │
                        │  - Round-robin      │
                        │  - Weighted routing │
                        └──────────┬──────────┘
                                   │
                        ┌──────────▼──────────┐
                        │     API GATEWAY     │
                        │                     │
                        │  - Rate limiting    │
                        │  - Authentication   │
                        │  - Request routing  │
                        └─────────────────────┘

    ┌─────────────────────────────────────────────────┐
    │              HEALTH CHECK AXINI                  │
    │                                                  │
    │  1. Servis başlayır → Consul-a qeydiyyat olur   │
    │  2. Consul hər 10s /health endpoint-ə sorğu     │
    │  3. 3 ardıcıl uğursuz cavab → "critical" status │
    │  4. Load balancer critical servisi çıxarır       │
    │  5. Servis bərpa olur → "passing" status         │
    │  6. Load balancer servisi geri qaytarır          │
    │  7. Servis dayanır → Consul-dan qeydiyyatı silir│
    └─────────────────────────────────────────────────┘
```

---

## 1. Health Check Növləri

Mikroservis arxitekturasında üç əsas health check növü var:

### Liveness Probe (Canlılıq Yoxlaması)

Servisin işlədiyini yoxlayır. Uğursuz olarsa, servis yenidən başladılır.

```php
<?php

namespace App\Health;

class LivenessCheck
{
    /**
     * Servisin əsas prosesinin işlədiyini yoxlayır.
     * Bu yoxlama minimal olmalıdır — sadəcə "proses yaşayır" cavabı.
     */
    public function check(): HealthResult
    {
        return new HealthResult(
            status: HealthStatus::HEALTHY,
            message: 'Service is alive',
            details: [
                'pid' => getmypid(),
                'uptime_seconds' => $this->getUptimeSeconds(),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'php_version' => PHP_VERSION,
            ]
        );
    }

    private function getUptimeSeconds(): int
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME'];
        return (int) (microtime(true) - $startTime);
    }
}
```

### Readiness Probe (Hazırlıq Yoxlaması)

Servisin trafiqi qəbul etməyə hazır olduğunu yoxlayır. Uğursuz olarsa, servis load balancer-dən çıxarılır, amma yenidən başladılmır.

```php
<?php

namespace App\Health;

class ReadinessCheck
{
    public function __construct(
        private DatabaseCheck $databaseCheck,
        private RedisCheck $redisCheck,
        private QueueCheck $queueCheck,
        private ExternalServiceCheck $externalServiceCheck,
    ) {}

    /**
     * Servisin bütün asılılıqlarının hazır olduğunu yoxlayır.
     */
    public function check(): HealthResult
    {
        $checks = [
            'database' => $this->databaseCheck->check(),
            'redis' => $this->redisCheck->check(),
            'queue' => $this->queueCheck->check(),
            'external_services' => $this->externalServiceCheck->check(),
        ];

        $overallStatus = $this->determineOverallStatus($checks);

        return new HealthResult(
            status: $overallStatus,
            message: $overallStatus === HealthStatus::HEALTHY
                ? 'Service is ready to accept traffic'
                : 'Service is not ready — some dependencies are unhealthy',
            details: array_map(
                fn (HealthResult $result) => [
                    'status' => $result->status->value,
                    'message' => $result->message,
                    'response_time_ms' => $result->responseTimeMs,
                ],
                $checks
            ),
        );
    }

    private function determineOverallStatus(array $checks): HealthStatus
    {
        foreach ($checks as $check) {
            if ($check->status === HealthStatus::UNHEALTHY) {
                return HealthStatus::UNHEALTHY;
            }
        }

        foreach ($checks as $check) {
            if ($check->status === HealthStatus::DEGRADED) {
                return HealthStatus::DEGRADED;
            }
        }

        return HealthStatus::HEALTHY;
    }
}
```

### Startup Probe (Başlanğıc Yoxlaması)

Servisin ilkin yüklənməsini tamamladığını yoxlayır. Migration, cache warming, config loading kimi proseslər.

```php
<?php

namespace App\Health;

class StartupCheck
{
    private bool $isReady = false;
    private array $completedSteps = [];
    private array $requiredSteps = [
        'config_loaded',
        'database_migrated',
        'cache_warmed',
        'routes_cached',
        'service_registered',
    ];

    public function markStepComplete(string $step): void
    {
        $this->completedSteps[] = $step;

        if (empty(array_diff($this->requiredSteps, $this->completedSteps))) {
            $this->isReady = true;
        }
    }

    public function check(): HealthResult
    {
        $pendingSteps = array_diff($this->requiredSteps, $this->completedSteps);

        return new HealthResult(
            status: $this->isReady ? HealthStatus::HEALTHY : HealthStatus::UNHEALTHY,
            message: $this->isReady
                ? 'Startup complete'
                : 'Startup in progress, waiting for: ' . implode(', ', $pendingSteps),
            details: [
                'completed_steps' => $this->completedSteps,
                'pending_steps' => array_values($pendingSteps),
                'progress_percent' => round(
                    count($this->completedSteps) / count($this->requiredSteps) * 100
                ),
            ],
        );
    }
}
```

---

## 2. Əsas Health Check Modeli

```php
<?php

namespace App\Health;

enum HealthStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';
}

class HealthResult
{
    public readonly float $responseTimeMs;

    public function __construct(
        public readonly HealthStatus $status,
        public readonly string $message,
        public readonly array $details = [],
        ?float $responseTimeMs = null,
    ) {
        $this->responseTimeMs = $responseTimeMs ?? 0.0;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'message' => $this->message,
            'details' => $this->details,
            'response_time_ms' => round($this->responseTimeMs, 2),
        ];
    }
}

interface HealthCheckInterface
{
    public function name(): string;
    public function check(): HealthResult;
}
```

---

## 3. Database, Redis, Queue Health Check-ləri

### Database Health Check

```php
<?php

namespace App\Health\Checks;

use App\Health\HealthCheckInterface;
use App\Health\HealthResult;
use App\Health\HealthStatus;
use Illuminate\Support\Facades\DB;

class DatabaseCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            // Əsas bağlantı yoxlaması
            DB::connection()->getPdo();

            // Sadə sorğu ilə cavab vaxtını ölçmək
            $result = DB::select('SELECT 1 AS ok');

            $responseTime = (microtime(true) - $start) * 1000;

            // Bağlantı pool vəziyyəti
            $connections = DB::connection()->getDoctrineConnection();
            $poolStats = [
                'active_connections' => $this->getActiveConnectionCount(),
                'max_connections' => config('database.connections.mysql.pool.max', 10),
            ];

            // Yavaş cavab — degraded status
            if ($responseTime > 500) {
                return new HealthResult(
                    status: HealthStatus::DEGRADED,
                    message: "Database responding slowly ({$responseTime}ms)",
                    details: $poolStats,
                    responseTimeMs: $responseTime,
                );
            }

            return new HealthResult(
                status: HealthStatus::HEALTHY,
                message: 'Database connection is healthy',
                details: array_merge($poolStats, [
                    'driver' => config('database.default'),
                    'database' => config('database.connections.' . config('database.default') . '.database'),
                ]),
                responseTimeMs: $responseTime,
            );
        } catch (\Throwable $e) {
            $responseTime = (microtime(true) - $start) * 1000;

            return new HealthResult(
                status: HealthStatus::UNHEALTHY,
                message: 'Database connection failed: ' . $e->getMessage(),
                details: [
                    'error_class' => get_class($e),
                    'error_code' => $e->getCode(),
                ],
                responseTimeMs: $responseTime,
            );
        }
    }

    private function getActiveConnectionCount(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($result[0]->Value ?? 0);
        } catch (\Throwable) {
            return -1;
        }
    }
}
```

### Redis Health Check

```php
<?php

namespace App\Health\Checks;

use App\Health\HealthCheckInterface;
use App\Health\HealthResult;
use App\Health\HealthStatus;
use Illuminate\Support\Facades\Redis;

class RedisCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'redis';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            // PING ilə bağlantı yoxlaması
            $pong = Redis::ping();

            if ($pong !== true && $pong !== 'PONG' && $pong !== '+PONG') {
                throw new \RuntimeException("Unexpected PING response: {$pong}");
            }

            // Yaddaş istifadəsini yoxla
            $info = Redis::info('memory');
            $usedMemory = $info['used_memory'] ?? 0;
            $maxMemory = $info['maxmemory'] ?? 0;

            $responseTime = (microtime(true) - $start) * 1000;

            $memoryUsagePercent = $maxMemory > 0
                ? round(($usedMemory / $maxMemory) * 100, 2)
                : 0;

            // Yaddaş 90%-dən çoxdursa — degraded
            if ($memoryUsagePercent > 90) {
                return new HealthResult(
                    status: HealthStatus::DEGRADED,
                    message: "Redis memory usage is high: {$memoryUsagePercent}%",
                    details: [
                        'used_memory_mb' => round($usedMemory / 1024 / 1024, 2),
                        'max_memory_mb' => round($maxMemory / 1024 / 1024, 2),
                        'memory_usage_percent' => $memoryUsagePercent,
                        'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    ],
                    responseTimeMs: $responseTime,
                );
            }

            return new HealthResult(
                status: HealthStatus::HEALTHY,
                message: 'Redis connection is healthy',
                details: [
                    'used_memory_mb' => round($usedMemory / 1024 / 1024, 2),
                    'memory_usage_percent' => $memoryUsagePercent,
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    'redis_version' => $info['redis_version'] ?? 'unknown',
                ],
                responseTimeMs: $responseTime,
            );
        } catch (\Throwable $e) {
            return new HealthResult(
                status: HealthStatus::UNHEALTHY,
                message: 'Redis connection failed: ' . $e->getMessage(),
                responseTimeMs: (microtime(true) - $start) * 1000,
            );
        }
    }
}
```

### Queue Health Check

```php
<?php

namespace App\Health\Checks;

use App\Health\HealthCheckInterface;
use App\Health\HealthResult;
use App\Health\HealthStatus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class QueueCheck implements HealthCheckInterface
{
    private const MAX_ACCEPTABLE_QUEUE_SIZE = 10000;
    private const MAX_ACCEPTABLE_FAILED_JOBS = 100;

    public function name(): string
    {
        return 'queue';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);

        try {
            $queueSizes = $this->getQueueSizes();
            $failedJobCount = $this->getFailedJobCount();
            $workerStatus = $this->checkWorkerStatus();

            $responseTime = (microtime(true) - $start) * 1000;

            // Worker-lər işləmirsə — unhealthy
            if (!$workerStatus['running']) {
                return new HealthResult(
                    status: HealthStatus::UNHEALTHY,
                    message: 'No active queue workers detected',
                    details: [
                        'queue_sizes' => $queueSizes,
                        'failed_jobs' => $failedJobCount,
                        'workers' => $workerStatus,
                    ],
                    responseTimeMs: $responseTime,
                );
            }

            // Çoxlu uğursuz iş və ya böyük növbə — degraded
            if ($failedJobCount > self::MAX_ACCEPTABLE_FAILED_JOBS) {
                return new HealthResult(
                    status: HealthStatus::DEGRADED,
                    message: "High number of failed jobs: {$failedJobCount}",
                    details: [
                        'queue_sizes' => $queueSizes,
                        'failed_jobs' => $failedJobCount,
                    ],
                    responseTimeMs: $responseTime,
                );
            }

            $totalSize = array_sum($queueSizes);
            if ($totalSize > self::MAX_ACCEPTABLE_QUEUE_SIZE) {
                return new HealthResult(
                    status: HealthStatus::DEGRADED,
                    message: "Queue backlog is large: {$totalSize} jobs pending",
                    details: [
                        'queue_sizes' => $queueSizes,
                        'failed_jobs' => $failedJobCount,
                    ],
                    responseTimeMs: $responseTime,
                );
            }

            return new HealthResult(
                status: HealthStatus::HEALTHY,
                message: 'Queue system is healthy',
                details: [
                    'queue_sizes' => $queueSizes,
                    'failed_jobs' => $failedJobCount,
                    'total_pending' => $totalSize,
                ],
                responseTimeMs: $responseTime,
            );
        } catch (\Throwable $e) {
            return new HealthResult(
                status: HealthStatus::UNHEALTHY,
                message: 'Queue check failed: ' . $e->getMessage(),
                responseTimeMs: (microtime(true) - $start) * 1000,
            );
        }
    }

    private function getQueueSizes(): array
    {
        $queues = config('health.monitored_queues', ['default', 'high', 'low', 'notifications']);
        $sizes = [];

        foreach ($queues as $queue) {
            $sizes[$queue] = Queue::size($queue);
        }

        return $sizes;
    }

    private function getFailedJobCount(): int
    {
        return \DB::table('failed_jobs')->count();
    }

    private function checkWorkerStatus(): array
    {
        // Supervisor vasitəsilə worker proseslərini yoxla
        try {
            $lastRestart = Redis::get('illuminate:queue:restart');
            $lastHeartbeat = Redis::get('queue:worker:heartbeat');

            $isRunning = $lastHeartbeat
                && (time() - (int) $lastHeartbeat) < 120; // 2 dəqiqəlik timeout

            return [
                'running' => $isRunning,
                'last_heartbeat' => $lastHeartbeat ? date('Y-m-d H:i:s', (int) $lastHeartbeat) : null,
                'last_restart' => $lastRestart ? date('Y-m-d H:i:s', (int) $lastRestart) : null,
            ];
        } catch (\Throwable) {
            return ['running' => false, 'error' => 'Cannot determine worker status'];
        }
    }
}
```

---

## 4. HealthCheckService — Mərkəzi Health Check Xidməti

```php
<?php

namespace App\Health;

use Illuminate\Support\Collection;

class HealthCheckService
{
    /** @var HealthCheckInterface[] */
    private array $checks = [];
    private array $cachedResults = [];
    private float $cacheTtlSeconds = 5.0;

    public function registerCheck(HealthCheckInterface $check): self
    {
        $this->checks[$check->name()] = $check;
        return $this;
    }

    /**
     * Bütün qeydiyyatlı yoxlamaları icra edir.
     */
    public function runAll(): HealthReport
    {
        $results = [];
        $start = microtime(true);

        foreach ($this->checks as $name => $check) {
            $results[$name] = $this->runCheck($check);
        }

        $totalTime = (microtime(true) - $start) * 1000;

        return new HealthReport(
            serviceName: config('app.service_name', config('app.name')),
            status: $this->determineOverallStatus($results),
            checks: $results,
            totalResponseTimeMs: $totalTime,
            timestamp: now()->toIso8601String(),
            version: config('app.version', '1.0.0'),
            environment: config('app.env'),
        );
    }

    /**
     * Yalnız müəyyən yoxlamaları icra edir.
     */
    public function runSpecific(array $checkNames): HealthReport
    {
        $results = [];
        $start = microtime(true);

        foreach ($checkNames as $name) {
            if (isset($this->checks[$name])) {
                $results[$name] = $this->runCheck($this->checks[$name]);
            }
        }

        return new HealthReport(
            serviceName: config('app.service_name'),
            status: $this->determineOverallStatus($results),
            checks: $results,
            totalResponseTimeMs: (microtime(true) - $start) * 1000,
            timestamp: now()->toIso8601String(),
            version: config('app.version', '1.0.0'),
            environment: config('app.env'),
        );
    }

    /**
     * Sadəcə liveness — minimal yoxlama.
     */
    public function liveness(): HealthResult
    {
        return new HealthResult(
            status: HealthStatus::HEALTHY,
            message: 'Service is alive',
            details: [
                'pid' => getmypid(),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
        );
    }

    private function runCheck(HealthCheckInterface $check): HealthResult
    {
        $name = $check->name();

        // Cache-dən oxu
        if (isset($this->cachedResults[$name])) {
            $cached = $this->cachedResults[$name];
            if ((microtime(true) - $cached['time']) < $this->cacheTtlSeconds) {
                return $cached['result'];
            }
        }

        $start = microtime(true);

        try {
            $result = $check->check();
        } catch (\Throwable $e) {
            $result = new HealthResult(
                status: HealthStatus::UNHEALTHY,
                message: "Check '{$name}' threw exception: " . $e->getMessage(),
                responseTimeMs: (microtime(true) - $start) * 1000,
            );
        }

        // Cache-ə yaz
        $this->cachedResults[$name] = [
            'result' => $result,
            'time' => microtime(true),
        ];

        return $result;
    }

    private function determineOverallStatus(array $results): HealthStatus
    {
        $hasUnhealthy = false;
        $hasDegraded = false;

        foreach ($results as $result) {
            match ($result->status) {
                HealthStatus::UNHEALTHY => $hasUnhealthy = true,
                HealthStatus::DEGRADED => $hasDegraded = true,
                default => null,
            };
        }

        if ($hasUnhealthy) return HealthStatus::UNHEALTHY;
        if ($hasDegraded) return HealthStatus::DEGRADED;
        return HealthStatus::HEALTHY;
    }
}
```

### HealthReport Modeli

```php
<?php

namespace App\Health;

class HealthReport
{
    public function __construct(
        public readonly string $serviceName,
        public readonly HealthStatus $status,
        public readonly array $checks,
        public readonly float $totalResponseTimeMs,
        public readonly string $timestamp,
        public readonly string $version,
        public readonly string $environment,
    ) {}

    public function toArray(): array
    {
        return [
            'service' => $this->serviceName,
            'status' => $this->status->value,
            'version' => $this->version,
            'environment' => $this->environment,
            'timestamp' => $this->timestamp,
            'total_response_time_ms' => round($this->totalResponseTimeMs, 2),
            'checks' => array_map(
                fn (HealthResult $r) => $r->toArray(),
                $this->checks
            ),
        ];
    }

    public function httpStatusCode(): int
    {
        return match ($this->status) {
            HealthStatus::HEALTHY => 200,
            HealthStatus::DEGRADED => 200, // Hələ də trafiqi qəbul edir
            HealthStatus::UNHEALTHY => 503,
        };
    }
}
```

---

## 5. Laravel Health Check Endpoint-ləri

### Controller

```php
<?php

namespace App\Http\Controllers;

use App\Health\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __construct(
        private HealthCheckService $healthService,
    ) {}

    /**
     * GET /health
     * 
     * Tam sağlamlıq hesabatı. Consul və monitoring alətləri üçün.
     */
    public function health(): JsonResponse
    {
        $report = $this->healthService->runAll();

        return response()->json(
            $report->toArray(),
            $report->httpStatusCode()
        );
    }

    /**
     * GET /health/live
     *
     * Kubernetes liveness probe. Minimal yoxlama.
     */
    public function liveness(): JsonResponse
    {
        $result = $this->healthService->liveness();

        return response()->json($result->toArray(), 200);
    }

    /**
     * GET /health/ready
     *
     * Kubernetes readiness probe. Asılılıqları yoxlayır.
     */
    public function readiness(): JsonResponse
    {
        $report = $this->healthService->runAll();

        return response()->json(
            $report->toArray(),
            $report->httpStatusCode()
        );
    }

    /**
     * GET /health/startup
     *
     * Kubernetes startup probe.
     */
    public function startup(): JsonResponse
    {
        $startupCheck = app(\App\Health\StartupCheck::class);
        $result = $startupCheck->check();

        $statusCode = $result->status === \App\Health\HealthStatus::HEALTHY ? 200 : 503;

        return response()->json($result->toArray(), $statusCode);
    }

    /**
     * GET /health/{check}
     *
     * Tək bir yoxlamanın nəticəsi.
     */
    public function specific(string $check): JsonResponse
    {
        $report = $this->healthService->runSpecific([$check]);

        if (empty($report->checks)) {
            return response()->json([
                'error' => "Unknown health check: {$check}",
            ], 404);
        }

        return response()->json(
            $report->toArray(),
            $report->httpStatusCode()
        );
    }
}
```

### Routes

```php
// routes/api.php

use App\Http\Controllers\HealthController;

// Health check endpoint-ləri — authentication tələb etmir
Route::prefix('health')->withoutMiddleware(['auth', 'throttle'])->group(function () {
    Route::get('/', [HealthController::class, 'health']);
    Route::get('/live', [HealthController::class, 'liveness']);
    Route::get('/ready', [HealthController::class, 'readiness']);
    Route::get('/startup', [HealthController::class, 'startup']);
    Route::get('/{check}', [HealthController::class, 'specific']);
});
```

### ServiceProvider

```php
<?php

namespace App\Providers;

use App\Health\HealthCheckService;
use App\Health\Checks\DatabaseCheck;
use App\Health\Checks\RedisCheck;
use App\Health\Checks\QueueCheck;
use App\Health\Checks\DiskSpaceCheck;
use Illuminate\Support\ServiceProvider;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HealthCheckService::class, function ($app) {
            $service = new HealthCheckService();

            $service
                ->registerCheck(new DatabaseCheck())
                ->registerCheck(new RedisCheck())
                ->registerCheck(new QueueCheck())
                ->registerCheck(new DiskSpaceCheck());

            return $service;
        });
    }
}
```

---

## 6. Consul ilə Service Registry

### ServiceRegistryClient

```php
<?php

namespace App\ServiceRegistry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConsulServiceRegistry
{
    private string $consulUrl;
    private string $serviceId;
    private string $serviceName;
    private string $serviceAddress;
    private int $servicePort;

    public function __construct()
    {
        $this->consulUrl = config('services.consul.url', 'http://consul:8500');
        $this->serviceName = config('app.service_name', 'laravel-service');
        $this->serviceAddress = config('app.service_address', gethostname());
        $this->servicePort = (int) config('app.service_port', 8080);
        $this->serviceId = "{$this->serviceName}-{$this->serviceAddress}-{$this->servicePort}";
    }

    /**
     * Servisi Consul-a qeydiyyat edir.
     */
    public function register(): bool
    {
        $payload = [
            'ID' => $this->serviceId,
            'Name' => $this->serviceName,
            'Address' => $this->serviceAddress,
            'Port' => $this->servicePort,
            'Tags' => [
                'laravel',
                'version-' . config('app.version', '1.0.0'),
                'env-' . config('app.env'),
            ],
            'Meta' => [
                'version' => config('app.version', '1.0.0'),
                'framework' => 'laravel',
                'php_version' => PHP_VERSION,
                'started_at' => now()->toIso8601String(),
            ],
            'Check' => [
                'HTTP' => "http://{$this->serviceAddress}:{$this->servicePort}/api/health",
                'Method' => 'GET',
                'Interval' => '10s',
                'Timeout' => '5s',
                'DeregisterCriticalServiceAfter' => '90s',
                'Header' => [
                    'Accept' => ['application/json'],
                ],
            ],
            'EnableTagOverride' => false,
        ];

        try {
            $response = Http::timeout(10)
                ->put("{$this->consulUrl}/v1/agent/service/register", $payload);

            if ($response->successful()) {
                Log::info("Service registered with Consul", [
                    'service_id' => $this->serviceId,
                    'address' => "{$this->serviceAddress}:{$this->servicePort}",
                ]);
                return true;
            }

            Log::error("Failed to register with Consul", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error("Consul registration exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Servisi Consul-dan silir (graceful shutdown zamanı).
     */
    public function deregister(): bool
    {
        try {
            $response = Http::timeout(5)
                ->put("{$this->consulUrl}/v1/agent/service/deregister/{$this->serviceId}");

            if ($response->successful()) {
                Log::info("Service deregistered from Consul", [
                    'service_id' => $this->serviceId,
                ]);
                return true;
            }

            Log::error("Failed to deregister from Consul", [
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error("Consul deregistration exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Digər servisi Consul-dan tapır (service discovery).
     */
    public function discover(string $serviceName, bool $onlyHealthy = true): array
    {
        try {
            $endpoint = $onlyHealthy
                ? "{$this->consulUrl}/v1/health/service/{$serviceName}?passing=true"
                : "{$this->consulUrl}/v1/health/service/{$serviceName}";

            $response = Http::timeout(5)->get($endpoint);

            if (!$response->successful()) {
                Log::warning("Service discovery failed for: {$serviceName}");
                return [];
            }

            $services = $response->json();

            return array_map(function ($entry) {
                $service = $entry['Service'];
                return new ServiceInstance(
                    id: $service['ID'],
                    name: $service['Service'],
                    address: $service['Address'],
                    port: $service['Port'],
                    tags: $service['Tags'] ?? [],
                    meta: $service['Meta'] ?? [],
                    healthy: $this->isHealthy($entry['Checks'] ?? []),
                );
            }, $services);
        } catch (\Throwable $e) {
            Log::error("Service discovery exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Servisin sağlam instance-larından birini seçir (load balancing).
     */
    public function resolveService(string $serviceName): ?ServiceInstance
    {
        $instances = $this->discover($serviceName, onlyHealthy: true);

        if (empty($instances)) {
            return null;
        }

        // Sadə round-robin (random seçim)
        return $instances[array_rand($instances)];
    }

    /**
     * KV store-a dəyər yazır (konfiqurasiya paylaşımı üçün).
     */
    public function putKV(string $key, string $value): bool
    {
        try {
            $response = Http::timeout(5)
                ->withBody($value, 'application/octet-stream')
                ->put("{$this->consulUrl}/v1/kv/{$key}");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * KV store-dan dəyər oxuyur.
     */
    public function getKV(string $key): ?string
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->consulUrl}/v1/kv/{$key}?raw");

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['Status'] !== 'passing') {
                return false;
            }
        }
        return true;
    }
}
```

### ServiceInstance Model

```php
<?php

namespace App\ServiceRegistry;

class ServiceInstance
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $address,
        public readonly int $port,
        public readonly array $tags = [],
        public readonly array $meta = [],
        public readonly bool $healthy = true,
    ) {}

    public function baseUrl(): string
    {
        return "http://{$this->address}:{$this->port}";
    }

    public function version(): string
    {
        return $this->meta['version'] ?? 'unknown';
    }
}
```

---

## 7. Circuit Breaker ilə Health Check İnteqrasiyası

```php
<?php

namespace App\ServiceRegistry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResilientServiceClient
{
    private const STATE_CLOSED = 'closed';       // Normal işləyir
    private const STATE_OPEN = 'open';           // Kəsilib, sorğu göndərmir
    private const STATE_HALF_OPEN = 'half_open'; // Test rejimi

    public function __construct(
        private ConsulServiceRegistry $registry,
        private int $failureThreshold = 5,
        private int $recoveryTimeSeconds = 30,
        private int $timeoutSeconds = 5,
    ) {}

    /**
     * Circuit breaker ilə qorunan servis çağırışı.
     */
    public function call(
        string $serviceName,
        string $method,
        string $path,
        array $data = [],
        array $headers = [],
    ): ServiceResponse {
        $circuitKey = "circuit:{$serviceName}";
        $state = $this->getCircuitState($circuitKey);

        // Circuit açıqdırsa — tez uğursuzluq
        if ($state === self::STATE_OPEN) {
            if (!$this->shouldAttemptRecovery($circuitKey)) {
                Log::warning("Circuit is OPEN for {$serviceName}, fast-failing");

                return new ServiceResponse(
                    success: false,
                    statusCode: 503,
                    body: ['error' => "Service {$serviceName} circuit is open"],
                    fromCircuitBreaker: true,
                );
            }

            // Recovery vaxtı gəlib — half-open rejimə keç
            $this->setCircuitState($circuitKey, self::STATE_HALF_OPEN);
        }

        // Servisi tap
        $instance = $this->registry->resolveService($serviceName);

        if (!$instance) {
            Log::error("No healthy instance found for {$serviceName}");
            $this->recordFailure($circuitKey);

            return new ServiceResponse(
                success: false,
                statusCode: 503,
                body: ['error' => "No healthy instance of {$serviceName} available"],
            );
        }

        // Sorğu göndər
        try {
            $url = $instance->baseUrl() . $path;
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($headers)
                ->{$method}($url, $data);

            if ($response->successful()) {
                $this->recordSuccess($circuitKey);

                return new ServiceResponse(
                    success: true,
                    statusCode: $response->status(),
                    body: $response->json() ?? [],
                );
            }

            // Server xətası
            if ($response->serverError()) {
                $this->recordFailure($circuitKey);
            }

            return new ServiceResponse(
                success: false,
                statusCode: $response->status(),
                body: $response->json() ?? ['error' => $response->body()],
            );
        } catch (\Throwable $e) {
            $this->recordFailure($circuitKey);

            Log::error("Service call to {$serviceName} failed", [
                'instance' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            return new ServiceResponse(
                success: false,
                statusCode: 0,
                body: ['error' => $e->getMessage()],
            );
        }
    }

    private function getCircuitState(string $key): string
    {
        return Cache::get("{$key}:state", self::STATE_CLOSED);
    }

    private function setCircuitState(string $key, string $state): void
    {
        Cache::put("{$key}:state", $state, now()->addMinutes(10));

        Log::info("Circuit state changed", [
            'circuit' => $key,
            'new_state' => $state,
        ]);
    }

    private function recordFailure(string $key): void
    {
        $failures = (int) Cache::get("{$key}:failures", 0) + 1;
        Cache::put("{$key}:failures", $failures, now()->addMinutes(10));
        Cache::put("{$key}:last_failure", time(), now()->addMinutes(10));

        if ($failures >= $this->failureThreshold) {
            $this->setCircuitState($key, self::STATE_OPEN);
        }
    }

    private function recordSuccess(string $key): void
    {
        $state = $this->getCircuitState($key);

        if ($state === self::STATE_HALF_OPEN) {
            // Half-open-dən uğurlu cavab — circuit-i bağla
            $this->setCircuitState($key, self::STATE_CLOSED);
        }

        Cache::put("{$key}:failures", 0, now()->addMinutes(10));
    }

    private function shouldAttemptRecovery(string $key): bool
    {
        $lastFailure = Cache::get("{$key}:last_failure", 0);
        return (time() - $lastFailure) >= $this->recoveryTimeSeconds;
    }
}

class ServiceResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly int $statusCode,
        public readonly array $body,
        public readonly bool $fromCircuitBreaker = false,
    ) {}
}
```

### İstifadə Nümunəsi

```php
<?php

namespace App\Services;

use App\ServiceRegistry\ResilientServiceClient;

class OrderService
{
    public function __construct(
        private ResilientServiceClient $client,
    ) {}

    public function createOrder(array $orderData): array
    {
        // İnventarı yoxla — inventory-service
        $inventoryCheck = $this->client->call(
            serviceName: 'inventory-service',
            method: 'post',
            path: '/api/inventory/check',
            data: [
                'items' => $orderData['items'],
            ],
        );

        if (!$inventoryCheck->success) {
            throw new \RuntimeException(
                'Inventory check failed: ' . ($inventoryCheck->body['error'] ?? 'Unknown error')
            );
        }

        // Ödənişi başlat — payment-service
        $payment = $this->client->call(
            serviceName: 'payment-service',
            method: 'post',
            path: '/api/payments',
            data: [
                'amount' => $orderData['total_amount'],
                'currency' => $orderData['currency'],
                'customer_id' => $orderData['customer_id'],
            ],
        );

        if (!$payment->success) {
            throw new \RuntimeException(
                'Payment failed: ' . ($payment->body['error'] ?? 'Unknown error')
            );
        }

        // Bildiriş göndər — notification-service (asinxron, uğursuzluğu ignore et)
        $this->client->call(
            serviceName: 'notification-service',
            method: 'post',
            path: '/api/notifications',
            data: [
                'type' => 'order_created',
                'customer_id' => $orderData['customer_id'],
                'order_id' => $orderData['id'] ?? null,
            ],
        );

        return [
            'order' => $orderData,
            'payment' => $payment->body,
            'inventory' => $inventoryCheck->body,
        ];
    }
}
```

---

## 8. Graceful Shutdown — Düzgün Dayanma

Servis dayanarkən əvvəlcə Consul-dan qeydiyyatını silməli, aktiv sorğuları tamamlamalı və sonra prosesi dayandırmalıdır.

```php
<?php

namespace App\Providers;

use App\ServiceRegistry\ConsulServiceRegistry;
use App\Health\StartupCheck;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class ServiceRegistryProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = app(ConsulServiceRegistry::class);
        $startupCheck = app(StartupCheck::class);

        // Servis başlayanda qeydiyyat ol
        $this->app->booted(function () use ($registry, $startupCheck) {
            if (config('services.consul.enabled', false)) {
                $registered = $registry->register();

                if ($registered) {
                    $startupCheck->markStepComplete('service_registered');
                }
            }
        });

        // Graceful shutdown üçün signal handler
        if (php_sapi_name() === 'cli') {
            $this->registerShutdownHandlers($registry);
        }
    }

    private function registerShutdownHandlers(ConsulServiceRegistry $registry): void
    {
        $shutdown = function () use ($registry) {
            echo "[Shutdown] Deregistering from Consul...\n";
            $registry->deregister();

            echo "[Shutdown] Waiting for in-flight requests...\n";
            sleep(5); // Load balancer-in yeniləmə vaxtı

            echo "[Shutdown] Goodbye.\n";
            exit(0);
        };

        // SIGTERM — Kubernetes-dən gələn normal dayandırma siqnalı
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, $shutdown);
            pcntl_signal(SIGINT, $shutdown);
        }

        // PHP shutdown handler (əlavə təhlükəsizlik)
        register_shutdown_function(function () use ($registry) {
            if (config('services.consul.enabled', false)) {
                $registry->deregister();
            }
        });
    }
}
```

### Octane ilə Graceful Shutdown

```php
<?php

// app/Listeners/DeregisterOnShutdown.php

namespace App\Listeners;

use App\ServiceRegistry\ConsulServiceRegistry;
use Laravel\Octane\Events\WorkerStopping;

class DeregisterOnShutdown
{
    public function __construct(
        private ConsulServiceRegistry $registry,
    ) {}

    public function handle(WorkerStopping $event): void
    {
        $this->registry->deregister();

        // Aktiv sorğuların tamamlanmasını gözlə
        usleep(500_000); // 500ms
    }
}

// EventServiceProvider
// Laravel\Octane\Events\WorkerStopping::class => [
//     DeregisterOnShutdown::class,
// ],
```

---

## 9. Kubernetes Health Probe Konfiqurasiyası

```yaml
# kubernetes/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: order-service
  labels:
    app: order-service
    version: "1.5.0"
spec:
  replicas: 3
  selector:
    matchLabels:
      app: order-service
  template:
    metadata:
      labels:
        app: order-service
    spec:
      terminationGracePeriodSeconds: 60
      containers:
        - name: order-service
          image: registry.example.com/order-service:1.5.0
          ports:
            - containerPort: 8080
              name: http
          
          # Startup Probe — servisin başlanğıc yüklənməsi
          # İlk 300 saniyə ərzində hər 10s yoxlayır
          startupProbe:
            httpGet:
              path: /api/health/startup
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 10
            failureThreshold: 30
            timeoutSeconds: 5
          
          # Liveness Probe — servisin canlılığı
          # Uğursuz olarsa container restart olunur
          livenessProbe:
            httpGet:
              path: /api/health/live
              port: 8080
            initialDelaySeconds: 0
            periodSeconds: 15
            failureThreshold: 3
            timeoutSeconds: 5
            successThreshold: 1
          
          # Readiness Probe — trafiqi qəbul etməyə hazırlıq
          # Uğursuz olarsa Service endpoint-lərindən çıxarılır
          readinessProbe:
            httpGet:
              path: /api/health/ready
              port: 8080
            initialDelaySeconds: 0
            periodSeconds: 10
            failureThreshold: 3
            timeoutSeconds: 5
            successThreshold: 1
          
          resources:
            requests:
              memory: "256Mi"
              cpu: "250m"
            limits:
              memory: "512Mi"
              cpu: "500m"
          
          env:
            - name: APP_SERVICE_NAME
              value: "order-service"
            - name: APP_VERSION
              value: "1.5.0"
            - name: CONSUL_ENABLED
              value: "true"
            - name: CONSUL_URL
              valueFrom:
                configMapKeyRef:
                  name: consul-config
                  key: url
          
          # Graceful shutdown — SIGTERM gəlir, proses 60s müddətində bitirir
          lifecycle:
            preStop:
              exec:
                command:
                  - /bin/sh
                  - -c
                  - |
                    # Consul-dan qeydiyyatı sil
                    curl -s -X PUT http://consul:8500/v1/agent/service/deregister/$HOSTNAME
                    # Load balancer-in yeniləmə vaxtı
                    sleep 10

---
# kubernetes/service.yaml
apiVersion: v1
kind: Service
metadata:
  name: order-service
spec:
  selector:
    app: order-service
  ports:
    - port: 80
      targetPort: 8080
      protocol: TCP
  type: ClusterIP
```

---

## 10. Konfiqurasiya

```php
<?php

// config/health.php

return [
    /*
    |--------------------------------------------------------------------------
    | Health Check Konfiqurasiyası
    |--------------------------------------------------------------------------
    */

    'enabled' => env('HEALTH_CHECK_ENABLED', true),

    // Hansı yoxlamalar aktiv olsun
    'checks' => [
        'database' => env('HEALTH_CHECK_DATABASE', true),
        'redis' => env('HEALTH_CHECK_REDIS', true),
        'queue' => env('HEALTH_CHECK_QUEUE', true),
        'disk' => env('HEALTH_CHECK_DISK', true),
    ],

    // Queue monitoring
    'monitored_queues' => ['default', 'high', 'low', 'notifications'],

    // Cache TTL (saniyə) — eyni health nəticəsini bu qədər müddət cache-ləyir
    'cache_ttl' => env('HEALTH_CACHE_TTL', 5),

    // Yavaş cavab həddi (ms)
    'slow_threshold_ms' => env('HEALTH_SLOW_THRESHOLD', 500),

    // Disk space minimum (MB)
    'min_disk_space_mb' => env('HEALTH_MIN_DISK_MB', 500),
];

// config/services.php (consul bölməsi)
return [
    // ... digər servislər

    'consul' => [
        'enabled' => env('CONSUL_ENABLED', false),
        'url' => env('CONSUL_URL', 'http://consul:8500'),
        'token' => env('CONSUL_TOKEN'),
        'health_check_interval' => env('CONSUL_HEALTH_INTERVAL', '10s'),
        'deregister_timeout' => env('CONSUL_DEREGISTER_TIMEOUT', '90s'),
    ],

    // Circuit breaker
    'circuit_breaker' => [
        'failure_threshold' => env('CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_time' => env('CIRCUIT_BREAKER_RECOVERY', 30),
        'timeout' => env('CIRCUIT_BREAKER_TIMEOUT', 5),
    ],
];
```

---

## 11. DiskSpace Health Check (Əlavə)

```php
<?php

namespace App\Health\Checks;

use App\Health\HealthCheckInterface;
use App\Health\HealthResult;
use App\Health\HealthStatus;

class DiskSpaceCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'disk_space';
    }

    public function check(): HealthResult
    {
        $start = microtime(true);
        $path = base_path();

        $totalBytes = disk_total_space($path);
        $freeBytes = disk_free_space($path);

        if ($totalBytes === false || $freeBytes === false) {
            return new HealthResult(
                status: HealthStatus::UNHEALTHY,
                message: 'Unable to determine disk space',
                responseTimeMs: (microtime(true) - $start) * 1000,
            );
        }

        $usedPercent = round((1 - $freeBytes / $totalBytes) * 100, 2);
        $freeMb = round($freeBytes / 1024 / 1024, 2);
        $minFreeMb = config('health.min_disk_space_mb', 500);

        $details = [
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
        ];

        $responseTime = (microtime(true) - $start) * 1000;

        if ($freeMb < $minFreeMb) {
            return new HealthResult(
                status: HealthStatus::UNHEALTHY,
                message: "Disk space critically low: {$freeMb}MB free",
                details: $details,
                responseTimeMs: $responseTime,
            );
        }

        if ($usedPercent > 85) {
            return new HealthResult(
                status: HealthStatus::DEGRADED,
                message: "Disk usage is high: {$usedPercent}%",
                details: $details,
                responseTimeMs: $responseTime,
            );
        }

        return new HealthResult(
            status: HealthStatus::HEALTHY,
            message: 'Disk space is sufficient',
            details: $details,
            responseTimeMs: $responseTime,
        );
    }
}
```

---

## 12. Health Check Nəticə Nümunəsi

`GET /api/health` endpoint-inin qaytardığı tipik cavab:

```json
{
    "service": "order-service",
    "status": "healthy",
    "version": "1.5.0",
    "environment": "production",
    "timestamp": "2026-04-11T14:32:05+00:00",
    "total_response_time_ms": 12.45,
    "checks": {
        "database": {
            "status": "healthy",
            "message": "Database connection is healthy",
            "details": {
                "active_connections": 5,
                "max_connections": 10,
                "driver": "mysql",
                "database": "orders"
            },
            "response_time_ms": 3.21
        },
        "redis": {
            "status": "healthy",
            "message": "Redis connection is healthy",
            "details": {
                "used_memory_mb": 45.32,
                "memory_usage_percent": 18.5,
                "connected_clients": 12,
                "redis_version": "7.2.4"
            },
            "response_time_ms": 1.05
        },
        "queue": {
            "status": "healthy",
            "message": "Queue system is healthy",
            "details": {
                "queue_sizes": {
                    "default": 23,
                    "high": 2,
                    "low": 156,
                    "notifications": 8
                },
                "failed_jobs": 3,
                "total_pending": 189
            },
            "response_time_ms": 5.67
        },
        "disk_space": {
            "status": "healthy",
            "message": "Disk space is sufficient",
            "details": {
                "total_gb": 100.0,
                "free_gb": 62.45,
                "used_percent": 37.55
            },
            "response_time_ms": 0.12
        }
    }
}
```

---

## Xülasə

| Komponent | Məqsəd | Alət |
|---|---|---|
| Service Registry | Servislərin avtomatik qeydiyyatı və kəşfi | Consul, etcd |
| Health Check | Servis sağlamlığının monitorinqi | Laravel endpoint-ləri |
| Liveness Probe | Prosesin canlılığını yoxlama | `GET /health/live` |
| Readiness Probe | Trafiqi qəbul etmə hazırlığı | `GET /health/ready` |
| Startup Probe | İlkin yüklənmənin tamamlanması | `GET /health/startup` |
| Circuit Breaker | Uğursuz servislərə sorğunun dayandırılması | Cache-based state |
| Graceful Shutdown | Düzgün qeydiyyatdan çıxma | SIGTERM handler |
| Service Discovery | Digər servislərin tapılması | Consul HTTP API |

**Əsas qaydalar:**

1. Liveness probe minimal olmalıdır — DB, Redis yoxlamayın
2. Readiness probe bütün kritik asılılıqları yoxlamalıdır
3. Health check endpoint-ləri authentication tələb etməməlidir
4. Graceful shutdown zamanı əvvəlcə registry-dən çıxın, sonra sorğuları tamamlayın
5. Circuit breaker ilə cascade failure-ın qarşısını alın
6. Health check nəticələrini qısa müddətlik cache-ləyin (hər sorğuda DB-yə müraciət etməyin)
