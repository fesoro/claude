# Bulkhead Pattern (Senior)

## Mündəricat
1. [Problem: Resource Exhaustion](#problem-resource-exhaustion)
2. [Bulkhead nədir?](#bulkhead-nədir)
3. [Thread Pool Bulkhead](#thread-pool-bulkhead)
4. [Semaphore Bulkhead](#semaphore-bulkhead)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [Queue Worker İzolyasiyası](#queue-worker-izolyasiyası)
7. [Circuit Breaker ilə birlikdə](#circuit-breaker-ilə-birlikdə)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Resource Exhaustion

```
Gəmi bölmələri olmadan:
┌───────────────────────────────────┐
│           Gəmi anbari             │
│                                   │
│  Su daxil oldu → hər şey batır!   │
│                                   │
└───────────────────────────────────┘

Gəmi bölmələri ilə (bulkhead):
┌──────────┬──────────┬─────────────┐
│  Bölmə 1 │  Bölmə 2 │   Bölmə 3  │
│   (su)   │  (quru)  │   (quru)   │
│          │          │             │
└──────────┴──────────┴─────────────┘
  Su daxil oldu → yalnız bir bölmə batır!
```

**Proqramlaşdırmada problem:**

```
Bütün servislərin eyni thread pool-u var:

Thread Pool: [T1, T2, T3, T4, T5, T6, T7, T8] (8 thread)

Servis A sorğuları (yavaş): T1, T2, T3, T4, T5, T6 → hamısını tutur!
Servis B sorğuları (kritik): T7, T8 → yalnız 2 thread qaldı

→ Servis A-nın problemi Servis B-ni blokur
→ Kritik sorğular kəsilir
```

---

## Bulkhead nədir?

Resursları (thread, connection, memory) izolyasiya et ki, bir komponentin çöküşü başqalarını etkiləməsin.

```
Thread Pool Bulkhead:

┌──────────────────────────────────────────────────────┐
│                   Application                        │
├────────────────────┬─────────────────────────────────┤
│   Payment Pool     │      Catalog Pool                │
│ [T1 T2 T3 T4 T5]  │  [T6 T7 T8 T9 T10]              │
│                    │                                  │
│  Payment Service   │   Catalog Service                │
│   (yavaş/error)    │   (normal işləyir)               │
└────────────────────┴──────────────────────────────────┘

Payment Pool tükənsə → Catalog Pool normal qalır!
```

---

## Thread Pool Bulkhead

```
Her servis üçün ayrı thread pool:

Pool A (Payment): max=5, queue=10
Pool B (Inventory): max=5, queue=10  
Pool C (Notification): max=3, queue=50

Payment yavaşlayırsa:
  → Pool A dolar (5 active + 10 queued = 15)
  → 16-cı sorğu rejected → fast fail
  → Pool B, C normal işləyir
```

---

## Semaphore Bulkhead

Thread pool əvəzinə, concurrent sorğu sayını limitlə:

```
Semaphore Bulkhead:
  maxConcurrentCalls = 10
  
  11-ci paralel sorğu gəlsə → dərhal rejected
  (thread pool-da isə queue-a alınardı)
  
  Daha sadə, daha az overhead
  Lakin timeout management çətin
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
class Semaphore
{
    private int $permits;
    
    public function __construct(
        private string $name,
        private int $maxConcurrent,
        private ?int $timeoutMs = null,
    ) {
        $this->permits = $maxConcurrent;
    }
    
    public function acquire(): bool
    {
        $waited = 0;
        $interval = 10; // ms
        
        while ($this->permits <= 0) {
            if ($this->timeoutMs !== null && $waited >= $this->timeoutMs) {
                return false;
            }
            usleep($interval * 1000);
            $waited += $interval;
        }
        
        $this->permits--;
        return true;
    }
    
    public function release(): void
    {
        $this->permits++;
    }
}

class BulkheadService
{
    private array $semaphores = [];
    
    public function call(string $pool, callable $fn, int $maxConcurrent = 10): mixed
    {
        if (!isset($this->semaphores[$pool])) {
            $this->semaphores[$pool] = new Semaphore($pool, $maxConcurrent, timeoutMs: 1000);
        }
        
        $semaphore = $this->semaphores[$pool];
        
        if (!$semaphore->acquire()) {
            throw new BulkheadRejectedException(
                "Bulkhead '$pool' dolu. Sorğu rədd edildi."
            );
        }
        
        try {
            return $fn();
        } finally {
            $semaphore->release();
        }
    }
}

// İstifadə
class OrderService
{
    public function __construct(
        private BulkheadService $bulkhead
    ) {}
    
    public function processPayment(array $paymentData): PaymentResult
    {
        try {
            return $this->bulkhead->call('payment', function () use ($paymentData) {
                return $this->paymentGateway->charge($paymentData);
            }, maxConcurrent: 5);
        } catch (BulkheadRejectedException $e) {
            // Payment pool dolu — queue-a at
            dispatch(new ProcessPaymentJob($paymentData))->delay(now()->addSeconds(5));
            throw new PaymentDeferredException('Ödəniş bir az sonra işlənəcək');
        }
    }
    
    public function fetchInventory(int $productId): InventoryStatus
    {
        // Ayrı pool — payment bulkhead-dən təsirlenmir
        return $this->bulkhead->call('inventory', function () use ($productId) {
            return $this->inventoryService->getStatus($productId);
        }, maxConcurrent: 20);
    }
}
```

**Redis ilə distributed semaphore:**

```php
class RedisDistributedSemaphore
{
    public function __construct(
        private Redis $redis,
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
            [$key, $this->maxConcurrent, $this->ttlSeconds],
            1
        );
    }
    
    public function release(): void
    {
        $key = "bulkhead:{$this->name}";
        $this->redis->decr($key);
    }
    
    public function currentCount(): int
    {
        return (int) ($this->redis->get("bulkhead:{$this->name}") ?? 0);
    }
}
```

---

## Queue Worker İzolyasiyası

PHP-də bulkhead-in ən praktik forması — ayrı queue worker-lər:

*PHP-də bulkhead-in ən praktik forması — ayrı queue worker-lər üçün kod nümunəsi:*
```php
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
dispatch(new ProcessPaymentJob($data))->onQueue('payments');
dispatch(new SendEmailJob($data))->onQueue('notifications');
dispatch(new GenerateReportJob($data))->onQueue('reports');
```

**Supervisor konfiqurasiyası (worker izolyasiyası):**

```ini
; /etc/supervisor/conf.d/workers.conf

[program:payment-worker]
command=php artisan queue:work --queue=payments --max-jobs=100
numprocs=3          ; Ödəniş üçün 3 worker
; Critical — az worker, amma dedicated

[program:notification-worker]  
command=php artisan queue:work --queue=notifications --max-jobs=500
numprocs=5          ; Bildirişlər üçün 5 worker
; High volume, non-critical

[program:report-worker]
command=php artisan queue:work --queue=reports --timeout=300
numprocs=1          ; Reports üçün 1 worker (resurs intensiv)
; Long-running, isolated
```

**Docker ilə worker izolyasiyası:**

```yaml
# docker-compose.yml
services:
  payment-worker:
    image: app:latest
    command: php artisan queue:work --queue=payments
    deploy:
      replicas: 3
      resources:
        limits:
          memory: 256M
          cpus: '0.5'
    environment:
      - QUEUE_CONNECTION=redis

  report-worker:
    image: app:latest
    command: php artisan queue:work --queue=reports --timeout=600
    deploy:
      replicas: 1
      resources:
        limits:
          memory: 1G      # Reports üçün daha çox RAM
          cpus: '1.0'
```

---

## Circuit Breaker ilə birlikdə

```
Bulkhead + Circuit Breaker = Resilience

CB: Xətalı servisə sorğu göndərməyi dayandır
Bulkhead: Resursları izolyasiya et

┌────────────────────────────────────────────┐
│             Resilient Service              │
│                                            │
│  ┌─────────────┐   ┌─────────────────────┐ │
│  │  Bulkhead   │   │  Circuit Breaker    │ │
│  │  (semaphore)│──►│  (state: CLOSED)   │ │
│  │  max=10     │   │  threshold=5       │ │
│  └─────────────┘   └──────────┬──────────┘ │
│                                │            │
└────────────────────────────────┼────────────┘
                                 │
                    ┌────────────▼────────────┐
                    │    External Service     │
                    └─────────────────────────┘

Ssenari:
1. Bulkhead: 11-ci sorğu gəlsə → reject (fast fail)
2. CB: 5 uğursuzluqdan sonra → OPEN (fast fail)
3. Hər ikisi birlikdə: resurs tükənmıri, xətalı servis izolyasiya olunur
```

*3. Hər ikisi birlikdə: resurs tükənmıri, xətalı servis izolyasiya olun üçün kod nümunəsi:*
```php
class ResilientExternalClient
{
    public function __construct(
        private RedisDistributedSemaphore $bulkhead,
        private RedisCircuitBreaker $circuitBreaker,
    ) {}
    
    public function call(callable $fn): mixed
    {
        // Əvvəlcə bulkhead yoxla
        if (!$this->bulkhead->acquire()) {
            throw new BulkheadRejectedException('Resurs limiti aşıldı');
        }
        
        try {
            // Sonra circuit breaker vasitəsilə çağır
            return $this->circuitBreaker->call($fn);
        } finally {
            $this->bulkhead->release();
        }
    }
}
```

---

## İntervyu Sualları

**1. Bulkhead pattern nədir, gəmi analogiyası ilə izah et.**
Gəmidə su keçirməyən bölmələr bir bölmə deşilsə gəminin batmasını önləyir. Proqramlaşdırmada resursları (thread pool, connection pool) servislər arasında izolyasiya edir. Bir servisin resurs tükənməsi digər servisləri etkiləmir.

**2. Thread pool bulkhead vs semaphore bulkhead fərqi.**
Thread pool: Hər servis üçün ayrı thread pool (executor). Daha izolə, amma overhead var. Semaphore: Concurrent sorğu sayını məhdudlaşdır. Sadə, az overhead, lakin timeout management çətin.

**3. PHP-də bulkhead pattern-i necə implementasiya etmək olar?**
Əən praktik yol: ayrı Laravel queue worker-lər hər servis/iş növü üçün. Supervisor ilə ayrı process-lər. Docker-da resource limits ilə. Kod səviyyəsində Redis semaphore ilə concurrent sorğuları limit et.

**4. Bulkhead olmadan nə baş verir?**
Yavaş servis bütün thread-ləri tutur. Digər kritik sorğular thread tapa bilmir. Bütün sistem responsive olmur. Bu resource exhaustion-dır — cascading failure-ın bir forması.

**5. Bulkhead-in "reject" siyasəti nə olmalıdır?**
Depends on criticality: Payment sorğuları → queue-a at, sonra işlə. Notification → drop et (uncritical). Report → user-a error qaytar, yenidən cəhd et. Hər case üçün fallback strategiyası müəyyən edilməlidir.

**6. Bulkhead pattern mikroservislərdə service mesh ilə necə tətbiq olunur?**
Istio/Envoy service mesh-ə `connectionPool` konfiqurasiyası əlavə etməklə hər servis üçün max pending requests, max connections, max retries limit qoyulur — kod yazmadan infrastruktur səviyyəsindən. Bu PHP kodu dəyişdirmədən bütün servislərə tətbiq edilir.

**7. Database connection pool bulkhead-i necə konfiqurasiya edilir?**
Laravel-də `config/database.php`-də `pool` yoxdur (PHP per-request-dir), amma PgBouncer/ProxySQL connection pooler-lərini servis növünə görə ayrı pool-lara bölmək mümkündür. Məs: OLAP sorğuları üçün ayrı ProxySQL instansiyası, OLTP üçün ayrı. Hər pool maksimum connection sayı ilə məhdudlaşdırılır.

---

## Anti-patternlər

**1. Bütün servisləri paylaşılan tək thread pool-da işlətmək**
Yavaş bir xarici servis (məs: SMS göndərmə) bütün thread-ləri tutur, payment kimi kritik sorğular thread tapa bilmir. Hər servis üçün ayrı thread pool ayırın; SMS pool dolsa payment pool-u təsirlənməsin.

**2. Eyni Laravel queue worker-ini bütün job növləri üçün istifadə**
`php artisan queue:work` hər növ job-u emal edir — ağır report job-ları email bildiriş job-larını gecikdirir. Hər job növü üçün ayrı queue adı (payment, notifications, reports) və ayrı worker proses konfiqurasiyası qurun.

**3. Bulkhead limitini sonsuz (ya da çox yüksək) qoymaq**
Thread/connection limiti yoxdursa bulkhead mövcud deyil — bir servisin yükü sistemi yenə əzir. Realistik limit müəyyən edin: production yükünü ölçün, ortalama + spike-a görə hesablayın.

**4. Reject siyasəti olmadan implementasiya**
Bulkhead dolu olduqda sorğu nə olacağı müəyyən edilməyib — timeout gözləyib yenə eyni problemi yaradır. Hər bulkhead üçün aydın reject siyasəti: kritik → queue-a at, az kritik → istifadəçiyə xəta qaytar, uncritical → sil.

**5. Bulkhead-i yalnız kod səviyyəsində tətbiq etmək**
PHP-də semaphore ilə məhdudlaşdırırsınız, lakin infrastructure paylaşılırdır — DB connection pool, memory hələ də ortaqdır. Docker/K8s-də resource limits (CPU, memory) ilə infrastructure səviyyəsindəki izolyasiyanı da təmin edin.

**6. Monitoring olmadan bulkhead işlətmək**
Bulkhead dolu hallar log edilmir — limitin nə tez dolduğunu bilmirsiniz, limit nə çox dar, nə çox genişdir. Reject sayını, queue dərinliyini, bekleme müddətini metrics ilə izləyin və limitləri dinamik tənzimləyin.
