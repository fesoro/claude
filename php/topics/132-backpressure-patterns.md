# Backpressure Patterns (Senior)

## Mündəricat
1. Fast producer / slow consumer problemi
2. Backpressure strategiyaları
3. Load shedding vs rejection
4. PHP İmplementasiyası
5. İntervyu Sualları

---

## Fast Producer / Slow Consumer Problemi

Producer mesajları çox sürətli göndərir, consumer isə emal edə bilmir:

```
Producer: 10,000 req/s
     ↓
  [Buffer/Queue]
     ↓
Consumer: 1,000 req/s

t=0:   Queue: 0
t=1s:  Queue: 9,000
t=2s:  Queue: 18,000
t=3s:  Queue: 27,000
...
t=10s: Memory exhausted → OOM → Crash 💥
```

**Real ssenarilar:**
- Web serverlərə birdən çox sorğu (traffic spike)
- Message queue consumer yavaşlaması
- Upstream service-in aşağı salması (DB yavaşlayır)
- Batch processor-un input stream-dən geri qalması

```
Normal vəziyyət:
  [API] → [Queue 500 items] → [Worker]

Spike zamanı:
  [API] → [Queue 50,000 items] → [Worker]
                ↑
           Memory problem
           Latency artır
           Timeout-lar başlayır
```

---

## Backpressure Strategiyaları

### 1. Drop (Atma)

Gələn sorğuları buffer dolu olduqda sadəcə at. Ən sürətli, amma data itirilir.

```
Buffer: [■■■■■■■■■■] DOLU

Yeni gələn: DROP → 429 Too Many Requests

İstifadə yeri: Log events, metrics, real-time analytics
(itirilsə problem olmayan data)
```

### 2. Buffer (Tampon)

Gələn sorğuları yaddaşa al, consumer hazır olduqda emal et.

```
Producer → [■■■■░░░░░░] → Consumer
                ↑
          Buffer dolur...

Problem: Memory sınırı, latency artır
İstifadə yeri: Email göndərmə, batch processing
```

### 3. Block (Bloklamaq)

Producer buffer dolu olduqda bloklanır — consumer boşalana qədər gözləyir.

```
Producer → [■■■■■■■■■■] → Consumer
   ↑                           ↓
   BLOCKED ←←←←←←←← Consumer boşaldı

İstifadə yeri: Sync pipeline-lar, controlled environments
Problem: Deadlock riski, timeout-lar
```

### 4. Throttle (Sürət Məhdudiyyəti)

Producer-in sürətini consumer-ə uyğunlaşdır.

```
Consumer → "Mən 100 req/s emal edə bilirəm"
Producer → sürəti 100 req/s-ə düşürür

Adaptive throttle:
  Consumer load < 50% → sürət artır
  Consumer load > 80% → sürət azalır
  Consumer load > 95% → reject başla
```

---

## Load Shedding vs Rejection

### Load Shedding

Sistemin sağlığını qorumaq üçün bəzi sorğuları qəsdən at. Prioritetə görə.

```
Priority Queue:

HIGH:   [Payment, Auth]     → HəMİŞƏ işlə
MEDIUM: [User Profile]      → Normal yükdə işlə
LOW:    [Analytics, Logs]   → Yük artanda at

Load = 90% → LOW priority-ni at
Load = 95% → LOW + MEDIUM-u at
Load = 99% → Yalnız HIGH-ı saxla
```

### Simple Rejection

Threshold keçildikdə bütün sorğuları rədd et (fərq qoyulmur).

```
Queue depth > 10,000 → 503 Service Unavailable
             ↑
     Bütün sorğular rədd edilir
```

**Fərq:**
```
                Load Shedding          Rejection
Priority       Var (smart)             Yox (blunt)
Müştəri UX     Bəziləri işləyir        Hamı fail olur
Komplekslik    Yüksək                  Aşağı
İstifadə yeri  Critical sistemlər      Sadə rate limit
```

---

## PHP İmplementasiyası

```php
<?php

// 1. Queue Depth Check — Worker-in sürətini idarə et
class BackpressureAwareProducer
{
    private Redis $redis;
    private string $queueKey;

    // Threshold-lar
    private const WARN_THRESHOLD   = 5_000;
    private const REJECT_THRESHOLD = 10_000;
    private const DROP_LOW_AT      = 7_000;

    public function publish(array $message, string $priority = 'medium'): PublishResult
    {
        $queueDepth = (int) $this->redis->lLen($this->queueKey);

        // Tam dolu: rədd et
        if ($queueDepth >= self::REJECT_THRESHOLD) {
            return PublishResult::rejected("Queue full: {$queueDepth} items");
        }

        // Load shedding: aşağı öncelikli mesajları at
        if ($queueDepth >= self::DROP_LOW_AT && $priority === 'low') {
            return PublishResult::dropped("Low priority message dropped under load");
        }

        // Xəbərdarlıq: gec gəlməsi mümkün
        $delayed = $queueDepth >= self::WARN_THRESHOLD;

        $this->redis->rPush($this->queueKey, json_encode([
            'payload'    => $message,
            'priority'   => $priority,
            'queued_at'  => microtime(true),
            'queue_depth_at_publish' => $queueDepth,
        ]));

        return PublishResult::accepted($delayed ? 'warning:high_load' : null);
    }
}

// 2. Adaptive Consumer — yükə görə sürəti tənzimləyən worker
class AdaptiveConsumer
{
    private Redis $redis;
    private string $queueKey;
    private int $batchSize;
    private float $maxProcessingRate; // req/saniyə

    // Adaptive rate control
    private float $currentRate;
    private float $lastAdjustment;

    private const MIN_RATE = 10;   // req/s
    private const MAX_RATE = 1000; // req/s

    public function __construct(Redis $redis, string $queueKey, float $maxRate = 500)
    {
        $this->redis            = $redis;
        $this->queueKey         = $queueKey;
        $this->maxProcessingRate = $maxRate;
        $this->currentRate      = $maxRate;
        $this->lastAdjustment   = microtime(true);
        $this->batchSize        = 10;
    }

    public function run(): void
    {
        while (true) {
            $startTime = microtime(true);

            // Batch al
            $messages = $this->fetchBatch();

            if (empty($messages)) {
                usleep(100_000); // 100ms gözlə
                continue;
            }

            // Emal et
            $processed = 0;
            foreach ($messages as $raw) {
                $message = json_decode($raw, true);

                try {
                    $this->processMessage($message);
                    $processed++;
                } catch (\Throwable $e) {
                    $this->handleError($message, $e);
                }

                // Rate limit tətbiq et
                $this->rateLimit($processed);
            }

            // Metrics topla
            $elapsed = microtime(true) - $startTime;
            $this->adjustRate($elapsed, $processed);
        }
    }

    private function fetchBatch(): array
    {
        $batch = [];
        for ($i = 0; $i < $this->batchSize; $i++) {
            $item = $this->redis->lPop($this->queueKey);
            if ($item === false) {
                break;
            }
            $batch[] = $item;
        }
        return $batch;
    }

    private function rateLimit(int $processedInBatch): void
    {
        if ($this->currentRate <= 0) {
            return;
        }

        $minIntervalMicros = (int) (1_000_000 / $this->currentRate);
        usleep($minIntervalMicros);
    }

    /**
     * Emal müddətinə görə sürəti tənzimləyir
     * Sürətli emal → rate artır
     * Yavaş emal   → rate azalır
     */
    private function adjustRate(float $elapsed, int $processed): void
    {
        $now = microtime(true);

        if ($now - $this->lastAdjustment < 5.0) {
            return; // 5 saniyədən tez tənzimləmə
        }

        $actualRate = $processed / max($elapsed, 0.001);

        if ($actualRate >= $this->currentRate * 0.9) {
            // Tam sürətdə işləyirik, artıra bilərik
            $this->currentRate = min(
                $this->currentRate * 1.1,
                $this->maxProcessingRate
            );
        } else {
            // Geridə qalırıq, sürəti azalt
            $this->currentRate = max(
                $this->currentRate * 0.8,
                self::MIN_RATE
            );
        }

        $this->lastAdjustment = $now;
    }

    private function processMessage(array $message): void
    {
        // İş burada görülür
    }

    private function handleError(array $message, \Throwable $e): void
    {
        // Dead letter queue-ya göndər
        $this->redis->rPush('queue:dead-letter', json_encode([
            'message' => $message,
            'error'   => $e->getMessage(),
            'failed_at' => time(),
        ]));
    }
}

// 3. HTTP Middleware — inbound backpressure
class BackpressureMiddleware
{
    private Redis $redis;

    private const COUNTERS_KEY = 'backpressure:active_requests';
    private const MAX_CONCURRENT = 200;
    private const WARN_CONCURRENT = 150;

    public function handle(Request $request, callable $next): Response
    {
        $current = (int) $this->redis->incr(self::COUNTERS_KEY);
        $this->redis->expire(self::COUNTERS_KEY, 60);

        if ($current > self::MAX_CONCURRENT) {
            $this->redis->decr(self::COUNTERS_KEY);
            return new Response(
                ['error' => 'Server overloaded, please retry later'],
                503,
                ['Retry-After' => '5']
            );
        }

        try {
            $response = $next($request);

            if ($current > self::WARN_CONCURRENT) {
                // Yüksək yük siqnalı: client-ə bildiri əlavə et
                $response->withHeader('X-Server-Load', 'high');
            }

            return $response;
        } finally {
            $this->redis->decr(self::COUNTERS_KEY);
        }
    }
}
```

---

## İntervyu Sualları

- Backpressure problemi nədir? Fast producer / slow consumer ssenarisini izah edin.
- Drop, buffer, block, throttle strategiyalarını müqayisə edin. Hər birini nə zaman seçərsiniz?
- Load shedding nədir? Priority-based load shedding necə işləyir?
- TCP-nin özündə backpressure mexanizmi varmı?
- Message queue-da consumer yavaşlayanda nə baş verir? Bu problemi necə detect edərsiniz?
- Reactive Streams spesifikasiyasında backpressure necə tətbiq edilir?
- HTTP/2 flow control backpressure-a necə kömək edir?
- Sistemin "load" göstəricisi olaraq hansı metrikləri izləyərsiniz?
