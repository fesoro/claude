# Graceful Degradation (Senior)

## Problem necə yaranır?

Sistem tam monolith kimi davranırsa: recommendation engine down olduqda bütün məhsul səhifəsi 500 qaytarır. Axtarış servisi yavaşlayırsa bütün istifadəçilər gözləyir. Bu "all-or-nothing" yanaşmasıdır — kritik deyil feature-lərin uğursuzluğu bütün sistemi çökürdür.

Real nümunə: Netflix recommendation engine down olduqda filmlər hələ göstərilir, sadəcə "Top Picks" boş gəlir. Amazon review servisi down olsa da məhsul satışa davam edir.

---

## Degradation Levels

```
Normal    → Hamısı aktiv
Elevated  → Recommendation engine söndürülür
High      → Search cache-only (live indexing yox)
Critical  → Yalnız checkout + payment aktiv
Emergency → Maintenance page
```

---

## İmplementasiya

*Bu kod yük səviyyəsinə görə feature-ləri söndürən load shedder-ı, circuit breaker ilə graceful degradation-ı və kritik endpoint-ləri qoruyan middleware-i göstərir:*

```php
// Yük səviyyəsinə görə feature-ləri söndürür
class LoadShedder
{
    public function shouldShed(string $feature): bool
    {
        $load = $this->getSystemLoad();

        return match(true) {
            $load > 0.9 => in_array($feature, ['recommendations', 'search_suggestions', 'analytics']),
            $load > 0.7 => in_array($feature, ['recommendations']),
            default     => false,
        };
    }

    // Queue depth sistem yükünün göstəricisi kimi
    private function getSystemLoad(): float
    {
        $queueDepth = (int) Redis::llen('queues:default');
        return min(1.0, $queueDepth / 10000);
    }
}

// Controller-da graceful degradation
class ProductController extends Controller
{
    public function show(int $id): JsonResponse
    {
        // Kritik: məhsul məlumatı — həmişə işləməlidir
        $product  = Product::findOrFail($id);
        $response = ['product' => new ProductResource($product)];

        // Non-critical: recommendations — yük yüksəkdirsə skip et
        if (!app(LoadShedder::class)->shouldShed('recommendations')) {
            try {
                $response['recommendations'] = $this->getRecommendations($product);
            } catch (\Exception $e) {
                // Exception-u yutma — log et, amma sayfanı çökürtmə
                $response['recommendations'] = [];
                Log::warning('Recommendations degraded', ['error' => $e->getMessage()]);
            }
        }

        // Non-critical: reviews — circuit breaker ilə
        $response['reviews'] = app(ReviewCircuitBreaker::class)->call(
            fn() => $this->getReviews($product),
            fallback: fn() => ['items' => [], 'degraded' => true]
        );

        return response()->json($response);
    }
}

// Circuit Breaker + fallback
class ExternalServiceClient
{
    public function call(string $service, callable $request, mixed $fallback = null): mixed
    {
        $cb = app(CircuitBreaker::class)->for($service);

        if ($cb->isOpen()) {
            // CB açıqdır — servis down, dərhal fallback
            return is_callable($fallback) ? $fallback() : $fallback;
        }

        try {
            $result = $request();
            $cb->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $cb->recordFailure();
            Log::warning("Service {$service} failed", ['error' => $e->getMessage()]);
            return is_callable($fallback) ? $fallback() : $fallback;
        }
    }
}

// Request-level load shedding — kritik endpoint-lər qorunur
class LoadSheddingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Checkout, payment, auth — həmişə pass
        if ($this->isCritical($request)) {
            return $next($request);
        }

        if (app(LoadShedder::class)->shouldShed('api')) {
            return response()->json([
                'error'       => 'Service temporarily degraded. Please try again shortly.',
                'retry_after' => 30,
            ], 503)->header('Retry-After', 30);
        }

        return $next($request);
    }

    private function isCritical(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/checkout')
            || str_starts_with($request->path(), 'api/payments')
            || str_starts_with($request->path(), 'api/auth');
    }
}

// Feature flag-based manual degradation
class FeatureService
{
    public function isEnabled(string $feature): bool
    {
        try {
            return Cache::remember("feature:{$feature}", 60, fn() =>
                Feature::where('name', $feature)->where('enabled', true)->exists()
            );
        } catch (\Exception) {
            return false; // Cache/DB down → conservative: disable
        }
    }
}
```

---

## Health-based Automatic Degradation

*Bu kod DB, cache, queue, search dependency-lərini yoxlayaraq sistemin degradasiya səviyyəsini avtomatik müəyyənləşdirən sinifi göstərir:*

```php
class HealthBasedDegradation
{
    public function getLevel(): string
    {
        $checks = [
            'database' => $this->checkDb(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
            'search'   => $this->checkSearch(),
        ];

        if (!$checks['database']) return 'emergency';
        if (!$checks['cache'])    return 'critical';
        if (!$checks['queue'])    return 'high';
        if (!$checks['search'])   return 'elevated';

        return 'normal';
    }
}
```

---

## Health Check Endpoint

Monitoring sistemləri (Kubernetes, load balancer) üçün `/health` endpoint-i:

*Bu kod Kubernetes probe-ları üçün DB, cache, queue dependency-lərini yoxlayan `/health` endpoint-ini göstərir:*

```php
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $status = [
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
        ];

        $healthy   = !in_array('error', array_column($status, 'status'));
        $httpCode  = $healthy ? 200 : 503;

        return response()->json([
            'status'  => $healthy ? 'ok' : 'degraded',
            'checks'  => $status,
            'version' => config('app.version'),
        ], $httpCode);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'ok'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database unreachable'];
        }
    }
}
```

Kubernetes `livenessProbe` / `readinessProbe` bu endpoint-i yoxlayır. 503 gəlsə pod traffic almır.

---

## Bulkhead Pattern

Fərqli servislərin birbirinin connection pool-unu "doldurmasının" qarşısını almaq:

*Bu kod servisləri bir-birindən izolasiya edən bulkhead pattern-ini (ayrı connection pool-lar və ayrı queue worker-lar) göstərir:*

```php
// Hər xarici servis üçün ayrı HTTP client connection pool
// recommendation servisi 10 connection aldıqda payment-ə toxunmur
$httpClients = [
    'recommendations' => Http::pool(size: 10, timeout: 2),
    'payments'        => Http::pool(size: 50, timeout: 30), // Kritik — daha çox pool
    'search'          => Http::pool(size: 20, timeout: 5),
];

// Queue-da bulkhead: ayrı queue-lar = ayrı worker pool-ları
// php artisan queue:work --queue=critical (payments, orders)
// php artisan queue:work --queue=default  (emails, notifications)
// php artisan queue:work --queue=low      (analytics, reporting)
```

---

## Circuit Breaker State Machine

```
CLOSED → normal, bütün request-lər keçir
  ↓ (N failure/window)
OPEN → servis down qəbul edilir, heç bir request keçmir, fallback qaytarılır
  ↓ (timeout sonra, məsələn 30s)
HALF-OPEN → bir test request buraxılır
  ↓ uğurlu          ↓ uğursuz
CLOSED               OPEN (yenidən)
```

---

## Anti-patterns

- **Exception-u catch edib heç nə etməmək:** `catch {}` — silent fail, debugging mümkün deyil, log mütləqdir.
- **Kritik endpoint-ləri də shed etmək:** Checkout, payment shed edilə bilməz — revenue itkisi.
- **Degraded state-i istifadəçiyə göstərməmək:** UI-da "recommendations temporarily unavailable" bildirişi user-ı informasiyalı saxlayır.
- **Fallback-in özü fail olarsa:** Fallback-in da xəta idarəsi olmalıdır. Nested try-catch deyil, sadə default dəyər.

---

## İntervyu Sualları

**1. Graceful degradation nədir?**
Partial failure zamanı tam çökmək əvəzinə kritik funksiyaları aktiv saxlamaq. Non-critical feature-lər söndürülür, kritiklər işləyir. Netflix, Amazon, Twitter bu prinsipə görə işləyir.

**2. Load shedding nədir, niyə lazımdır?**
Həddindən artıq yük gəldikdə non-critical request-ləri bilərəkdən 503 ilə rədd et. Məqsəd: sistemi tam çökməkdən xilas etmək. Priority: checkout > search > recommendations. `Retry-After` header: client nə vaxt yenidən cəhd etsin.

**3. Feature shedding vs circuit breaker fərqi?**
Feature shedding: proaktiv — yük həddi keçdikdə feature söndürülür. Circuit breaker: reaktiv — servis fail olmağa başladıqda avtomatik açılır. İkisi tamamlayıcıdır: CB downstream servis problemlərini, feature shedding yük problemlərini həll edir.

**4. Bulkhead pattern nədir?**
Bir servisin resurslarını (connection pool, thread pool, queue workers) digər servislərdən izolasiya etmək. Recommendation servisi bütün connection-ları bağlasa payment xidmətinə toxunmur. Ayrı queue-lar + ayrı worker pool-lar = bulkhead. Gəmi bölmələrindən ilhamlanmışdır — bir bölmə su dolarsa gəmi batmır.

**5. Circuit breaker half-open state nədir?**
CB OPEN olduqdan sonra timeout bitdikdə (məsələn 30s) bir test request buraxılır. Request uğurlu → CB CLOSED (normal). Request uğursuz → CB OPEN qalır. Half-open: "servis bərpa olubmu?" sualını yoxlayan keçid vəziyyəti.

**6. Degraded mode-da API response-da nə göndərilməlidir?**
`degraded: true` flag + hansı feature-lərin aktiv olmadığı: `{"product": {...}, "reviews": {"degraded": true, "items": []}}`. Client bu field-ə baxaraq "Rəylər müvəqqəti olaraq əlçatmazdır" mesajı göstərə bilər. HTTP status kod 200 — partial data var, amma cavab uğurludur.

---

## Anti-patternlər

**1. Bütün xüsusiyyətləri eyni priority-də tutmaq**
Checkout, recommendations və analytics üçün eyni circuit breaker threshold-u istifadə etmək — recommendations fail olsa checkout-u da bloklayır. Xüsusiyyətlər kritiklik səviyyəsinə görə (P0: checkout/payment, P1: search, P2: recommendations) ayrılmalı, hər səviyyə üçün ayrı davranış müəyyənləşdirilməlidir.

**2. Circuit breaker açıldıqda heç bir fallback verməmək**
Servis down olduqda sadəcə 500 qaytarmaq — istifadəçi nə olduğunu bilmir, support-a müraciət edir. Circuit breaker açıldıqda mənalı fallback (cache, default dəyər, "temporarily unavailable" mesajı) təqdim edilməlidir.

**3. Degraded mode-u monitoring etməmək**
Sistemin hansı xüsusiyyətlərinin hazırda söndürüldüyünü izləməmək — degradation saatlarla fərq edilmədən davam edir. Hər circuit breaker state dəyişikliyi (closed→open, open→half-open) alert tetikləməli, dashboard-da görünməlidir.

**4. Load shedding threshold-unu statik məhdudla müəyyənləşdirmək**
`if ($queueSize > 1000) { return 503; }` kimi sabit həddi bütün şəraitlər üçün istifadə etmək — trafik pattern-ləri dəyişdikcə hədd ya çox tez, ya çox gec işləyir. Adaptive threshold: CPU, memory, response time metrikalarına görə dinamik hesablanmalıdır.

**5. Fallback məntiqini try-catch daxilində mürəkkəbləşdirmək**
Catch bloku içindən başqa bir servis çağırışı, DB sorğusu, hesablama etmək — fallback özü fail olursa nested exception yaranır, debug çətinləşir. Fallback ən sadə dəyər (static array, cached response, null) olmalıdır.

**6. Partial failure-ı tam failure kimi qəbul etmək**
Bir endpointin fail olmasını bütün servisi down kimi işarələmək — `/recommendations` xəta verəndə `/checkout`-u da söndürmək. İzolasiya: hər xüsusiyyətin öz health state-i olmalı, bir feature-ın circuit breaker-ı digərini təsirləndirməməlidir.

**7. Health check endpoint-ini DB-siz saxlamaq**
`/health` endpoint-inin yalnız `{"status": "ok"}` qaytarıb real dependency yoxlamalarını etməməsi — load balancer servisi sağlam hesab edir, lakin DB bağlantısı yoxdur. Health check: DB, cache, queue, kritik external API-ları sınaqdan keçirməli, biri uğursuz olsa 503 qaytarmalıdır.
