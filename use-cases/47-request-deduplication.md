# Request Deduplication (Middle)

## Problem necə yaranır?

**Double submit:** User "Sifariş ver" düyməsinə iki dəfə basar (internet yavaş, düymə disabled olmayıb). İki eyni POST request → iki sifariş.

**Network retry:** Client request göndərir, server işləyir, cavab yolda itirilir. Client timeout alır, yenidən cəhd edir — server artıq işi görmüşdür.

**Idempotency key olmadıqda:** Hər POST yeni resource yaradır. Retry = yeni resource.

**Idempotency ilə deduplication fərqi:** Idempotency client-in açıq şəkildə key göndərməsini tələb edir. Deduplication key olmadan da işləyə bilər — content hash, DB business rule, time window.

---

## Həll Metodları

### 1. Content Hash (Middleware-level)

Eyni user, eyni path, eyni body → eyni fingerprint → dublikat:

*Bu kod eyni user+endpoint+body kombinasiyasını dublikat kimi aşkarlayan content hash deduplication middleware-ini göstərir:*

```php
class ContentHashDeduplicationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PATCH', 'PUT'])) {
            return $next($request);
        }

        // Fingerprint: user + endpoint + body hash
        $fingerprint = hash('sha256',
            ($request->user()?->id ?? 'guest') . '|' .
            $request->path() . '|' .
            json_encode($request->all())
        );

        $cacheKey   = "dedup:{$fingerprint}";
        $windowSecs = 30;

        if ($cached = Cache::get($cacheKey)) {
            return response()->json(
                $cached['body'],
                $cached['status'],
                ['X-Deduplicated' => 'true']
            );
        }

        $response = $next($request);

        // Yalnız uğurlu cavabları cache-lə
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'body'   => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], $windowSecs);
        }

        return $response;
    }
}
```

**Zəif tərəfi:** 30s window-dan sonra gələn retry bloklanmır. Eyni body ilə legitimate fərqli request false positive ola bilər.

### 2. DB Unique Constraint (Business-level)

Ən etibarlı metod — cache eviction riski yoxdur:

*Bu kod `checkout_session_id` üzərindəki DB unique constraint ilə eyni session-dan ikinci sifariş yaradılmasını önləyən servis metodunu göstərir:*

```php
class OrderService
{
    public function placeOrder(int $userId, array $items, string $sessionId): Order
    {
        return DB::transaction(function () use ($userId, $items, $sessionId) {
            // checkout_session_id UNIQUE constraint: eyni session-dan iki sifariş mümkün deyil
            $existing = Order::where('user_id', $userId)
                ->where('checkout_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($existing) return $existing; // Dublikat → mövcud sifarişi qaytar

            return Order::create([
                'user_id'             => $userId,
                'checkout_session_id' => $sessionId,
                'items'               => $items,
                'status'              => 'pending',
            ]);
        });
    }
}
```

### 3. Time Window + Atomic Lock

Eyni user, eyni action, qısa müddətdə → dublikat:

*Bu kod atomic `Cache::add` (SET NX) ilə qısa zaman pəncərəsi ərzində eyni əməliyyatın dublikat olub-olmadığını yoxlayan sinifi göstərir:*

```php
class ActionDeduplicator
{
    public function isDuplicate(string $action, int $userId, array $params, int $windowSeconds = 5): bool
    {
        $key = "dedup:{$action}:{$userId}:" . md5(serialize($params));

        // Cache::add = SET NX — atomic, race condition yoxdur
        // İlk çağırış: key yoxdur → set edir → false (not duplicate)
        // Sonrakı çağırış window-da: key var → set etmir → true (duplicate)
        $isNew = Cache::add($key, 1, $windowSeconds);

        return !$isNew;
    }
}

// Ödəniş controller-da
class PaymentController extends Controller
{
    public function charge(ChargeRequest $request): JsonResponse
    {
        if (app(ActionDeduplicator::class)->isDuplicate(
            'payment',
            $request->user()->id,
            ['amount' => $request->amount, 'method' => $request->payment_method],
            windowSeconds: 10
        )) {
            return response()->json(['message' => 'Əməliyyat artıq icra edilir.'], 409);
        }

        $payment = $this->paymentService->charge(
            $request->user()->id,
            $request->amount,
        );

        return response()->json(['payment_id' => $payment->id], 201);
    }
}
```

### 4. Form Token (Double-submit prevention)

Server single-use token verir, form göndəriləndə istehlak edilir:

*Bu kod formun ikinci dəfə göndərilməsini önləmək üçün tək istifadəlik form token yaradan və istehlak edən servis sinifini göstərir:*

```php
class FormTokenService
{
    // Form render zamanı token yaradılır, hidden field-ə yerləşdirilir
    public function generate(string $formName, int $userId): string
    {
        $token = Str::random(32);
        Cache::put("form_token:{$formName}:{$userId}", $token, 300);
        return $token;
    }

    // Form submit zamanı token yoxlanır + silinir (single-use)
    public function consume(string $formName, int $userId, string $token): bool
    {
        $key   = "form_token:{$formName}:{$userId}";
        $valid = Cache::get($key) === $token;

        if ($valid) Cache::forget($key); // Bir dəfəlik

        return $valid;
    }
}
```

### 5. Stripe-style Idempotency Key

Client hər request üçün UUID yaradır, eyni əməliyyatın retry-larında eyni UUID göndərir:

*Bu kod Stripe-stilind Idempotency-Key header-ı ilə eyni əməliyyatın retry-larını dublikat kimi aşkarlayan middleware-i göstərir:*

```php
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            return $next($request); // Key yoxdur — idempotency tələb edilmir
        }

        $cacheKey = "idempotency:{$request->user()->id}:{$idempotencyKey}";

        // Eyni key ilə cavab artıq saxlanıbmı?
        if ($stored = Cache::get($cacheKey)) {
            return response()->json($stored['body'], $stored['status'])
                ->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        // Uğurlu cavabı 24 saat saxla
        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'body'   => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], 86400);
        }

        return $response;
    }
}

// Client: hər yeni ödəniş üçün yeni UUID
// POST /payments
// Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
// Retry (network xəta): eyni header ilə yenidən göndər → eyni cavab alınır
```

---

## Metodların Müqayisəsi

| Metod | Etibarlılıq | İstifadə |
|-------|------------|---------|
| Content hash | Orta (cache eviction) | General middleware |
| DB unique constraint | Yüksək | Business-critical ops |
| Time window lock | Orta | Qısa müddətli dedup |
| Form token | Yüksək (UI) | HTML form double-submit |
| Idempotency key | Yüksək | Payment API, external integrasiya |

---

## Anti-patterns

- **Cache-only deduplication kritik əməliyyatlarda:** Eviction, Redis restart → dublikat. DB constraint mütləqdir.
- **Çox uzun dedup window:** 5 dəqiqəlik window — legitimate retry bloklanır.
- **False positive-ə görə error qaytarmaq:** Dublikat aşkarlandıqda mövcud nəticəni qaytar (200/201), xəta yox.

---

## İntervyu Sualları

**1. Deduplication vs idempotency fərqi?**
Idempotency: client açıq key göndərir. Deduplication: key olmadan — content hash, business rule, time window ilə dublikat aşkarlanır. Idempotency daha güclü zəmanət verir; deduplication client dəyişikliyi tələb etmir.

**2. Content hash deduplication-ın zəif tərəfi?**
Cache eviction → dedup pozulur. False positive: eyni body-li fərqli legitimate request. Time window: 30s-dən sonra gələn retry bloklanmır. Kritik əməliyyatlar üçün DB constraint daha etibarlıdır.

**3. Race condition — iki eyni request eyni anda?**
Cache `add()` (SET NX) atomic — yalnız biri true alır. DB UNIQUE constraint: eyni anda insert cəhdi olduqda biri constraint violation alır. `lockForUpdate` ilə mövcud record tapılır, eyni nəticə qaytarılır.

**4. Stripe-style idempotency key nədir?**
Client hər yeni əməliyyat üçün UUID yaradır. Retry edərkən eyni UUID göndərir. Server key-i görür, əvvəlki cavabı cache-dən qaytarır — idempotent nəticə. Key 24 saat saxlanır. `Idempotency-Replayed: true` header ilə replayed olduğu bildirilir. Payment API-larında standart yanaşmadır.

**5. Queue worker-ında deduplication?**
Queue retry mexanizmi eyni job-u bir neçə dəfə göndərə bilər (worker crash, ack timeout). Worker tərəfindən `job_id` unique constraint: `ProcessedJob::create(['job_id' => $this->job->uuid()])` — duplicate throws, job skip. Ya da `lockForUpdate` ilə DB record yoxlaması.

**6. Dublikat aşkarlandıqda hansı HTTP status qaytarılmalıdır?**
**200/201 (eyni cavab)** — client üçün ən yaxşı UX. Dublikat = uğurlu əməliyyat, artıq görülüb. `X-Deduplicated: true` header ilə dublikat olduğunu bildirmək olar. **409 Conflict** yanlışdır — client yenidən cəhd edir, problem həll edilmir. Dublikat aşkarlandıqda mövcud nəticəni qaytar.

---

## Anti-patternlər

**1. Content hash-i zaman komponenti olmadan istifadə etmək**
`MD5(user_id + amount + currency)` ilə dedup window-suz deduplication etmək — istifadəçi eyni məbləği iki dəfə ödəmək istəsə (məsələn, iki ayrı sifariş üçün) ikincisi həmişə dublikat kimi bloklananır. Hash-ə `time_window` (məsələn, 30 saniyəlik slot) daxil edilməlidir.

**2. Dedup nəticəsini xəta kimi qaytarmaq**
Dublikat aşkarlandıqda `409 Conflict` ya da `400 Bad Request` qaytarmaq — client yenidən cəhd edir, problemi həll etmir. Düzgün davranış: mövcud əməliyyatın nəticəsini `200 OK` ilə qaytarmaq, sanki yeni əməliyyat kimi.

**3. Deduplication key-i request body-dən almadan server-side generasiya etmək**
Hər gələn request üçün serverdə yeni UUID yaratmaq — hər retry fərqli key alır, deduplication heç işləmir. Idempotency key client tərəfindən generasiya edilməli, eyni retry üçün eyni key göndərilməlidir.

**4. Deduplication window-u çox qısa saxlamaq**
30 saniyəlik dedup window istifadə etmək — network latency, mobile app background-a keçmə, yenidən açılma kimi ssenarilərdə retry window-u keçir, istifadəçi duplicate charge alır. Business context-ə görə window seçilməlidir: payment üçün 24 saat, form submit üçün 5 dəqiqə.

**5. Distributed sistemdə yalnız bir node-da dedup cache saxlamaq**
Tək Redis instance-da dedup key-lərini saxlamaq — Redis down olarsa bütün deduplication mexanizmi işləmir, duplicate request-lər keçir. Redis Cluster ya da sentinel istifadə edilməli, DB-based UNIQUE constraint ikinci qoruma xətti kimi olmalıdır.

**6. Deduplication-ı yalnız HTTP layer-də etmək, iş növbəsini nəzərə almamaq**
API gateway-də dedup tətbiq etmək, lakin eyni request queue-ya bir neçə dəfə düşəndə worker-ların da dedup etməsini unutmaq — queue retry mexanizmi eyni job-u bir neçə dəfə göndərə bilər. Worker tərəfindən də `job_id` unique constraint ilə idempotency təmin edilməlidir.

**7. Idempotency key-i yalnız RAM-da saxlayıb Redis eviction-u nəzərə almamaq**
Idempotency key-lərini TTL olmadan ya da çox qısa TTL ilə Redis-də saxlamaq — key evict edilir, eyni key ilə gələn retry yeni əməliyyat kimi işlənir, dublikat baş verir. Kritik əməliyyatlar üçün idempotency key-lər həm Redis-də (sürətli lookup), həm DB-də (etibarlı) saxlanmalıdır.
