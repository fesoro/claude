# Retry Storm, Graceful Shutdown, Health Check (Senior)

## Retry Storm

### Problem necə yaranır?

Service B yavaşlayır (timeout: 5s). 500 client eyni anda sorğu göndərir → timeout → hamısı eyni anda retry edir. İndi 1000 sorğu. Hər retry timeout → yenə retry. Service B-nin üzərinə düşən yük eksponensial artır — tamamilə çökür.

```
// Bu kod retry storm-un eksponensial artımını zamana görə göstərir
t=0s:  500 sorğu
t=5s:  500 timeout → 500 retry = 1000 aktiv sorğu
t=10s: 1000 timeout → 1000 retry = 2000 aktiv sorğu
...
```

**Retry storm şərtləri:**
1. Çox client eyni anda retry edir (synchronized)
2. Retry interval çox qısa
3. Circuit breaker yoxdur
4. Backoff yoxdur

### Həll: Exponential Backoff + Jitter

**Exponential backoff** — hər retry-da delay ikiqat artır:
```
// Bu kod exponential backoff-un hər retry-da gecikmənin ikiqat artmasını göstərir
Attempt 1: 1s gözlə
Attempt 2: 2s gözlə
Attempt 3: 4s gözlə
Attempt 4: 8s gözlə (max cap)
```

**Jitter olmadan problem:** Bütün client-lər eyni anda retry edir (thundering herd). Jitter: hər client fərqli random vaxt gözləyir → load dağılır.

***Jitter olmadan problem:** Bütün client-lər eyni anda retry edir (thu üçün kod nümunəsi:*
```php
// Bu kod exponential backoff və full jitter ilə retry mexanizmini göstərir
class RetryWithBackoff
{
    // callable fail etdikdə exponential backoff + jitter ilə yenidən cəhd edir
    public function execute(
        callable $fn,
        int $maxAttempts = 5,
        int $baseDelayMs = 100,
        int $maxDelayMs  = 30000
    ): mixed {
        $attempt = 0;

        while (true) {
            try {
                return $fn();
            } catch (TransientException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) throw $e;

                // Full jitter: 0 ilə cap arasında random
                $exponential = $baseDelayMs * (2 ** $attempt);
                $capped      = min($maxDelayMs, $exponential);
                $jitter      = random_int(0, $capped);

                usleep($jitter * 1000);
            }
        }
    }
}
```

**Circuit Breaker ilə birlikdə:** Threshold keçildikdə retry etmədən dərhal fail ver. Service B-nin recovery şansı olur. CB half-open state-də test sorğusu göndərir — servis ayıldımı yoxlayır.

---

## Graceful Shutdown

### Problem necə yaranır?

Kubernetes pod-u yeniləyərkən SIGTERM göndərir. Container dərhal dayanarsa:
- In-flight HTTP request yarım cavab alır → client error
- DB transaction commit olmur → data inconsistency
- Queue job ACK göndərilmir → requeue → duplicate processing

### Həll

```
// Bu kod graceful shutdown prosesinin SIGTERM-dən SIGKILL-ə qədər olan mərhələlərini göstərir
t=0s:   SIGTERM → yeni request qəbul etmə
t=0-30s: Mövcud request-ləri tamamla, queue job-u bitir
t=30s:  SIGKILL (Kubernetes force kill)
```

*t=30s:  SIGKILL (Kubernetes force kill) üçün kod nümunəsi:*
```php
// Bu kod SIGTERM siqnalını tutaraq worker-in cari işi bitirməsini təmin edən graceful shutdown-u göstərir
class GracefulShutdownHandler
{
    private bool $shuttingDown = false;

    public function register(): void
    {
        // SIGTERM gəldikdə flag set et — ani çıxış yox
        pcntl_signal(SIGTERM, function () {
            $this->shuttingDown = true;
        });
        pcntl_signal(SIGINT, fn() => $this->shuttingDown = true);
    }

    public function isShuttingDown(): bool
    {
        pcntl_signal_dispatch(); // Pending siqnalları işlə
        return $this->shuttingDown;
    }
}

// Long-running worker — shutdown flag yoxlayır
class OrderProcessor
{
    public function run(): void
    {
        $this->shutdown->register();

        while (!$this->shutdown->isShuttingDown()) {
            $job = $this->queue->pop('orders');
            if (!$job) { sleep(1); continue; }

            // Job işlənir — bu loop iterasiyası tamamlanır, sonra dayanır
            $this->processJob($job);
            $job->ack();
        }
        // DB connections bağla, log yaz
        Log::info('Worker gracefully stopped');
    }
}
```

**Kubernetes konfiqurasiyası:**
```yaml
# Bu kod Kubernetes-də graceful shutdown üçün terminationGracePeriodSeconds və preStop hook konfiqurasiyasını göstərir
spec:
  terminationGracePeriodSeconds: 30  # SIGTERM → SIGKILL arası vaxt
  containers:
    lifecycle:
      preStop:
        exec:
          command: ["sleep", "5"]  # Load balancer-dən çıxmaq üçün 5s gözlə
```

`preStop` hook vacibdir: Kubernetes pod-u SIGTERM göndərməzdən əvvəl load balancer-dən çıxarır, lakin bu propagasiya ~2-3s çəkir. `preStop: sleep 5` ilə yeni traffic gəlməsi önlənir.

---

## Health Check Patterns

### 3 Kubernetes probe növü

**Liveness Probe — "Process canlıdır?"**
- Fail → container restart
- Yalnız process-in özünü yoxla (deadlock, infinite loop aşkarlama)
- ❌ DB yoxlama: DB down → liveness fail → restart → yenə fail → restart loop!

**Readiness Probe — "Traffic almağa hazırdır?"**
- Fail → service endpoint-dən çıxar, traffic kəsilir, restart yox
- DB, cache, downstream service-ləri yoxla
- Startup zamanı: DB bağlantısı qurulana qədər fail et → premature traffic yoxdur

**Startup Probe — "Container tam başladı?"**
- Liveness/Readiness başlamazdan əvvəl işləyir
- Yavaş başlayan app-lar üçün: PHP preloading, cache warm-up tamamlanana qədər

*- Yavaş başlayan app-lar üçün: PHP preloading, cache warm-up tamamlana üçün kod nümunəsi:*
```php
// Bu kod Kubernetes üçün liveness və readiness health check endpoint-lərini göstərir
class HealthController extends Controller
{
    // Liveness — yalnız "process cavab verirmi?" yoxlayır
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'alive']);
        // DB yoxlama yoxdur — DB down olsa restart loop yaranar
    }

    // Readiness — bütün dependency-lər hazırdır?
    public function ready(): JsonResponse
    {
        $checks  = [];
        $healthy = true;

        try {
            DB::selectOne('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception) {
            $checks['database'] = 'fail';
            $healthy = false;
        }

        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (\Exception) {
            $checks['redis'] = 'fail';
            $healthy = false;
        }

        // Queue worker heartbeat yoxlama
        $heartbeat = Cache::get('queue:worker:heartbeat');
        $checks['queue'] = ($heartbeat && now()->diffInSeconds($heartbeat) < 60)
            ? 'ok' : 'degraded';

        return response()->json(
            ['status' => $healthy ? 'ready' : 'not_ready', 'checks' => $checks],
            $healthy ? 200 : 503
        );
    }
}
```

---

## Anti-patterns

- **Retry-da jitter olmamaq:** Bütün client-lər synchronized retry → retry storm.
- **Infinite retry:** Max attempt limiti olmadan — resurs tükənir.
- **Liveness probe-da DB yoxlamaq:** DB down → restart loop → database daha da yüklənir.
- **terminationGracePeriodSeconds çox qısa:** In-flight işlər tamamlanmadan force kill.
- **preStop hook-suz:** Load balancer yeni traffic göndərməyə davam edir, pod isə bağlanır → connection error.

---

## İntervyu Sualları

**1. Retry storm nədir, necə önlənir?**
Çox client eyni anda xətalı servisə retry edir → yük eksponensial artır. Önlə: exponential backoff (delay artır), jitter (synchronized retry pozulur), circuit breaker (threshold-dan sonra retry etmə), max retry limit.

**2. Exponential backoff vs jitter — hər ikisi niyə lazımdır?**
Backoff tək başına: bütün client-lər 4s gözləyir, sonra eyni anda retry edir — yenə herd. Jitter: hər client fərqli vaxt gözləyir — load dağılır, servis toparlanır.

**3. Graceful shutdown niyə vacibdir?**
SIGTERM-də ani dayanma: broken HTTP responses, uncommitted transactions, lost queue jobs. Graceful: yeni sorğu qəbul etmə, mövcudları tamamla, sonra çıx. K8s `terminationGracePeriodSeconds` bu window-u təyin edir.

**4. Liveness vs readiness probe fərqi nədir?**
Liveness fail → restart. Readiness fail → traffic kəsilir, restart yox. Liveness-də DB yoxlama anti-pattern: DB down → restart loop. Readiness-də DB yoxlama düzgündür: DB bağlantısı olmadan traffic almaq məna kəsb etmir.

**5. preStop hook niyə lazımdır?**
SIGTERM göndərilməzdən əvvəl Kubernetes pod-u endpoint-dən çıxarır, lakin bu dəyişiklik load balancer-ə çatmaq üçün 2-3s lazım olur. Bu müddət ərzində yeni request-lər hələ gəlir. preStop sleep 5 ilə həmin lag qarşılanır.

**6. Bulkhead pattern nədir?**
Gəmi bölmə divari metaforu — bir servisin problemi digərini batırmasın. Ayrı thread pool-lar, connection pool-lar: Payment service üçün 20 thread, User service üçün 20 thread. Payment service hang etdikdə User service-in thread pool-u toxunulmur. Hystrix/Resilience4j kimi kitabxanalar tətbiq edir.

**7. Transient vs permanent xəta fərqi nədir, retry nə zaman edilmənlidir?**
Transient (keçici): network timeout, 503 Service Unavailable, rate limit (429) — retry mənalıdır. Permanent: 404 Not Found, 400 Bad Request, 422 Unprocessable — retry faydasızdır, sadəcə resurs israfı. Retry logic-ə yalnız transient xəta kodlarını daxil edin.

---

## Anti-patternlər

**1. Jitter olmadan exponential backoff tətbiq etmək**
Bütün client-lər 1s → 2s → 4s eyni cədvələ görə retry edir — synchronized retry storm yaranır, servis hər wave-dən sonra yenidən yüklənir. Jitter əlavə edin: `random(0, delay)` ilə retry zamanını randomlaşdırın; client-lər fərqli anda retry edib servis üzərindəki yükü dağıtsın.

**2. Circuit Breaker olmadan sonsuz retry**
Xətalı servisə exponential backoff ilə deyil, amma yenə də sonsuz dəfə retry etmək — servis recover olmaq şansı tapmır, thread pool tükənir. Max retry limitini müəyyən edin; Circuit Breaker açıqdırsa retry dərhal dayansın; servis sağlam olanda HALF-OPEN ilə test edin.

**3. SIGTERM-i tutmadan ani shutdown**
PHP-FPM ya da Laravel worker SIGTERM aldıqda işlənməkdə olan request-i yarımçıq kəsir — DB transaction rollback olmur, queue job yarımçıq qalır. SIGTERM handler qurun: yeni sorğu qəbul etməyi dayandırın, mövcud işlər tamamlansın, sonra çıxın; `terminationGracePeriodSeconds`-u real iş müddətinə görə tənzimləyin.

**4. Liveness probe-da DB bağlantısını yoxlamaq**
DB geçici yavaşlayır, liveness fail olur, pod restart edilir — DB problem varsa bütün pod-lar restart loopuna girər, DB-yə əlavə yük düşür. Liveness-də yalnız prosesin sağlamlığını yoxlayın (PHP-FPM cavab verir?); DB yoxlamasını readiness probe-a verin.

**5. Retry-ı non-idempotent əməliyyatlarda düşünmədən tətbiq etmək**
HTTP POST retry edilir, server request-i aldı amma cavab verə bilmədi — retry ikinci ödəniş, ikinci sifariş yaradır. Retry yalnız idempotent əməliyyatlara (GET, ya da idempotency key-li POST) tətbiq edin; idempotency key olmadan retry zamanı side effect-lər çoxalır.

**6. preStop hook-u olmadan pod shutdown**
Kubernetes pod-u endpoint-dən çıxarır, amma load balancer bu dəyişikliyi 2-3s gecikmə ilə görür — bu müddətdə yeni request-lər artıq shutting down olan pod-a gəlir. `preStop: exec: command: ["sleep", "5"]` konfiqurasiya edin ki, load balancer yenilənənə qədər pod aktiv qalsın.
