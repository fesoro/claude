# Bulkhead Pattern (Senior ⭐⭐⭐)

## İcmal

Bulkhead Pattern — resursları (thread, connection, memory, queue worker) izolyasiya edərək bir komponentin çöküşünün digərlərinə yayılmasını önləyir. Gəmidəki su keçirməyən bölmələr kimi: bir bölmə deşilsə gəmi batmır, yalnız o bölmə batar. Proqramlaşdırmada bir servisin resource tükənməsi digər kritik servisləri bloklamamalıdır.

## Niyə Vacibdir

Bütün servislərin paylaşılan tək thread pool-u varsa: yavaş SMS göndərmə servisi bütün thread-ləri tutur, payment kimi kritik sorğular thread tapa bilmir. Cascading failure belə başlayır. Bulkhead hər servisi öz resursu ilə izolyasiya edir — payment pool dolsa SMS pool normal qalır.

## Əsas Anlayışlar

- **Thread Pool Bulkhead**: hər servis üçün ayrı thread pool; bir pool dolsa digəri etkilənmir
- **Semaphore Bulkhead**: concurrent sorğu sayını limitlə; sadə, az overhead, lakin timeout management çətin
- **Queue Bulkhead**: Laravel-də hər iş növü üçün ayrı queue worker — ən praktik PHP yanaşması
- **Resource isolation**: CPU, memory, connection pool-u da izolyasiya etmək (Docker resource limits)
- **Reject policy**: bulkhead dolu olduqda nə etmək — queue-a at, xəta qaytar, drop et

## Praktik Baxış

- **Real istifadə**: payment vs notification servisləri, report generation vs API request, email vs SMS worker-ləri
- **Trade-off-lar**: cascading failure önlənir; hər servis müstəqil scale edilə bilər; lakin resource fragmentation (çox pool → az istifadə olunan resurslara israf); monitoring mürəkkəbləşir
- **İstifadə etməmək**: tək servis, az trafik; overhead-i justify etməyən sadə tətbiqlər
- **Common mistakes**: limit sonsuz qoymaq (bulkhead yoxdur); reject policy müəyyən etməmək; monitoring olmadan işlətmək

## Anti-Pattern Nə Zaman Olur?

**Çox bulkhead — resource fragmentation:**
10 ayrı pool, hər birinin max=5 thread-i var, sistem 8 thread-li — hər pool adətən 1-2 thread işlədir, qalanlar boş durur. Real yük paylanmasını ölç, pool-ları realistik ölçülərlə qur. Çox kiçik pool-lar reject rate-i artırır, çox böyük pool-lar bulkhead-i mənasız edir.

**Bulkhead olmadan single point of failure:**
Bütün iş növləri eyni `default` queue-da işlənir — ağır report job-ları payment job-larını gecikdirir. PHP-də ən praktik bulkhead: ayrı queue + ayrı Supervisor worker process. Docker resource limits ilə infrastructure izolyasiyası əlavə et.

**Reject siyasəti olmadan implementasiya:**
Bulkhead dolu olduqda sorğu nə olacağı müəyyən edilməyib — timeout gözləyib yenə eyni problemi yaradır. Hər bulkhead üçün aydın reject siyasəti: kritik → queue-a at, az kritik → istifadəçiyə xəta qaytar, uncritical → drop et.

**Yalnız kod səviyyəsindəki izolyasiya:**
Semaphore ilə concurrent limit qoyursunuz, amma infrastructure paylaşılırdır — DB connection pool, memory hələ də ortaqdır. Docker/K8s-də resource limits (CPU, memory) ilə infrastructure izolyasiyası da lazımdır.

## Nümunələr

### Ümumi Nümunə

```
Thread Pool Bulkhead:

┌────────────────────────────────────────────────────────┐
│                   Application                          │
├─────────────────────┬──────────────────────────────────┤
│   Payment Pool      │      Notification Pool           │
│ [T1 T2 T3 T4 T5]   │  [T6 T7 T8 T9 T10]              │
│  (yavaş/error)      │   (normal işləyir)               │
└─────────────────────┴──────────────────────────────────┘

Payment Pool tükənsə → Notification Pool normal qalır!
```

### PHP/Laravel Nümunəsi

```php
<?php

// Redis-based Distributed Semaphore — PHP multi-process üçün
// WHY: PHP FPM hər request ayrı prosesdə — shared memory yoxdur
class RedisDistributedSemaphore
{
    public function __construct(
        private \Illuminate\Redis\Connections\Connection $redis,
        private string $name,
        private int $maxConcurrent,
        private int $ttlSeconds = 30,  // deadlock önlə
    ) {}

    public function acquire(): bool
    {
        $key = "bulkhead:{$this->name}";

        // Lua script — atomic check-and-increment
        $script = <<<LUA
        local current = redis.call('GET', KEYS[1])
        if current == false then
            redis.call('SET', KEYS[1], 1, 'EX', ARGV[2])
            return 1
        elseif tonumber(current) < tonumber(ARGV[1]) then
            redis.call('INCR', KEYS[1])
            return 1
        else
            return 0
        end
        LUA;

        return (bool) $this->redis->eval(
            $script,
            1,
            $key,
            $this->maxConcurrent,
            $this->ttlSeconds,
        );
    }

    public function release(): void
    {
        $this->redis->decr("bulkhead:{$this->name}");
    }
}

class BulkheadService
{
    private array $semaphores = [];

    public function call(string $pool, callable $fn, int $maxConcurrent = 10): mixed
    {
        if (!isset($this->semaphores[$pool])) {
            $this->semaphores[$pool] = new RedisDistributedSemaphore(
                redis: \Redis::connection(),
                name: $pool,
                maxConcurrent: $maxConcurrent,
            );
        }

        $semaphore = $this->semaphores[$pool];

        if (!$semaphore->acquire()) {
            throw new BulkheadRejectedException("Bulkhead '{$pool}' dolu. Sorğu rədd edildi.");
        }

        try {
            return $fn();
        } finally {
            $semaphore->release();
        }
    }
}

// İstifadə — hər servis öz pool-una sahib
class OrderService
{
    public function __construct(
        private BulkheadService $bulkhead,
        private PaymentGateway $paymentGateway,
        private InventoryClient $inventoryClient,
    ) {}

    public function processPayment(array $paymentData): PaymentResult
    {
        try {
            return $this->bulkhead->call('payment', function () use ($paymentData) {
                return $this->paymentGateway->charge($paymentData);
            }, maxConcurrent: 5);
        } catch (BulkheadRejectedException $e) {
            // Payment pool dolu — queue-a at, 5 saniyə sonra yenidən cəhd
            dispatch(new ProcessPaymentJob($paymentData))->delay(now()->addSeconds(5));
            throw new PaymentDeferredException('Ödəniş bir az sonra işlənəcək');
        }
    }

    public function fetchInventory(int $productId): InventoryStatus
    {
        // Ayrı pool — payment bulkhead-dən müstəqil
        return $this->bulkhead->call('inventory', function () use ($productId) {
            return $this->inventoryClient->getStatus($productId);
        }, maxConcurrent: 20);
    }
}
```

```php
<?php

// Laravel Queue Worker İzolyasiyası — ən praktik PHP bulkhead
// config/queue.php
return [
    'connections' => [
        'payment_queue' => [
            'driver' => 'redis',
            'queue'  => 'payments',
        ],
        'notification_queue' => [
            'driver' => 'redis',
            'queue'  => 'notifications',
        ],
        'report_queue' => [
            'driver' => 'redis',
            'queue'  => 'reports',
        ],
    ],
];

// Job-ları müxtəlif queue-lara göndər
// dispatch(new ProcessPaymentJob($data))->onQueue('payments');
// dispatch(new SendEmailJob($data))->onQueue('notifications');
// dispatch(new GenerateReportJob($data))->onQueue('reports');
```

```ini
; /etc/supervisor/conf.d/workers.conf
; Hər queue öz worker process-inə sahibdir — tam izolyasiya

[program:payment-worker]
command=php artisan queue:work --queue=payments --max-jobs=100
numprocs=3
; Kritik — az worker, amma dedicated

[program:notification-worker]
command=php artisan queue:work --queue=notifications --max-jobs=500
numprocs=5
; High volume, non-critical

[program:report-worker]
command=php artisan queue:work --queue=reports --timeout=300
numprocs=1
; Long-running, resource intensive, izolə
```

## Praktik Tapşırıqlar

1. `RedisDistributedSemaphore` class yazın; `acquire()` + `release()`; parallel test: 10 goroutine eyni pool-a daxil olmağa çalışır, yalnız 5-i uğurlu olur; retry-dan sonra uğurlu olur
2. Laravel queue-larını ayırın: `payments`, `notifications`, `reports`; Supervisor konfiqurasiyası yazın; ağır report job notification job-larını gecikdirmir — test
3. Docker Compose-da resource limits əlavə edin: payment worker 256MB RAM, 0.5 CPU; report worker 1GB RAM, 1 CPU; memory limit-i aşdıqda nə baş verir?
4. Monitoring: bulkhead reject sayını, queue depth-ini, wait time-ı metric olaraq izləyin; Prometheus/Grafana ilə dashboard qurun; limit həddini dinamik tənzimləyin

## Əlaqəli Mövzular

- [Circuit Breaker](16-circuit-breaker.md) — bulkhead + CB = tam resilience; CB xətalı servisi, bulkhead resource-u qoruyur
- [Retry Pattern](17-retry-pattern.md) — bulkhead reject edəndə retry stratejisi
- [Saga Pattern](03-saga-pattern.md) — saga addımları ayrı worker pool-larında izolyasiya oluna bilər
- [Service Layer](../laravel/02-service-layer.md) — bulkhead service qatında tətbiq edilir
