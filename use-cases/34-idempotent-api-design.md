# Idempotent API Design (Middle)

## Problem necə yaranır?

Network qeyri-sabitdir. Client request göndərir, server işləyir, cavab göndərir — lakin cavab yolda itirilir. Client timeout alır. Yenidən cəhd etməlidir — amma server artıq işi görüb. Yenidən göndərsə double processing baş verər.

```
Client → POST /payments (charge $100)
Server işləyir, charge edir...
Cavab client-ə çatmır (network error)
Client timeout alır → yenidən göndərir
Server yenidən charge edir → $200 çıxılır!
```

Bu problem `POST` və `PATCH`-da baş verir. `GET`, `PUT`, `DELETE` metodoloji olaraq idempotentdir (eyni nəticəni verir). `POST` hər dəfə yeni resurs yaradır — idempotent deyil.

---

## Idempotency Key nədir?

Client tərəfindən yaradılan UUID. Server bu key ilə cavabı cache edir. Eyni key ilə ikinci request gəldikdə real operation icra etmir — cache-dən eyni cavabı qaytarır.

**Niyə client yaradır?** Server generate etsə retry-da yeni key olardı → yeni operation. Client retry edərkən eyni key-i göndərməlidir — buna görə client generate edir.

---

## İmplementasiya

*Bu kod idempotency middleware-ni və DB-level idempotency ilə dublikat ödənişi önləyən servis sinifini göstərir:*

```php
// Middleware: POST/PATCH request-lərini Idempotency-Key header-ı ilə idarə edir
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PATCH'])) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (!$key) {
            return response()->json(['error' => 'Idempotency-Key header required'], 422);
        }

        // User-specific key: fərqli istifadəçilər eyni key göndərə bilər
        $cacheKey = "idempotency:{$key}:" . $request->user()->id;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            // Əvvəl işlənib — eyni cavabı qaytar, real operation yox
            return response()->json(
                $cached['body'],
                $cached['status'],
                ['X-Idempotent-Replayed' => 'true']
            );
        }

        $response = $next($request);

        // Yalnız uğurlu cavabları cache-lə (5xx cache-lənmir — retry edilməlidir)
        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'body'   => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], 86400); // 24 saat
        }

        return $response;
    }
}

// DB-level idempotency — distributed sistemlər, cache eviction riski olmadan
class PaymentService
{
    public function charge(int $userId, int $amount, string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($userId, $amount, $idempotencyKey) {
            // UNIQUE constraint: eyni idempotency_key ikinci dəfə insert olunmur
            $existing = Payment::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) return $existing; // Dublikat — mövcud nəticəni qaytar

            // Gateway-ə idempotency key ötür — gateway-də də dublikat charge olmur
            $gatewayResult = $this->gateway->charge($amount, $idempotencyKey);

            return Payment::create([
                'user_id'         => $userId,
                'amount'          => $amount,
                'idempotency_key' => $idempotencyKey,
                'gateway_id'      => $gatewayResult['id'],
                'status'          => 'completed',
            ]);
        });
    }
}
```

---

## Cache vs DB — Hansını seçmək?

**Cache (Redis):**
- Sürətli, sadə
- Risk: Redis restart, eviction, TTL bitməsi → key itirilir → real operation yenidən icra olunur
- Məqbul: Yüksək yüklü, eventual consistency tolerate edilən sistemlər

**DB (UNIQUE constraint):**
- Persistent, restart-dan sonra da qorunur
- Race condition: UNIQUE constraint DB səviyyəsində bloklanır
- Məqbul: Payment, order kimi kritik əməliyyatlar

**İkisi birlikdə:** Cache L1 (sürətli check), DB L2 (persistent zəmanət).

---

## In-flight Request problemi

İki eyni key ilə request eyni anda gəlirsə (race condition): hər ikisi cache-i miss edir, hər ikisi işləməyə başlayır.

Həll: `Cache::add()` — atomic set-if-not-exists. İlk request key-i set edir, ikinci false alır, gözləyir. Daha etibarlı: DB UNIQUE constraint + `lockForUpdate`.

---

## Anti-patterns

- **Server-side key generation:** Retry-da fərqli key → yeni operation → double charge.
- **Yalnız Redis-ə güvənmək:** Eviction, restart, TTL bitməsi halında idempotency pozulur. Kritik əməliyyatlarda DB constraint mütləqdir.
- **5xx cavabları cache-ləmək:** Server error retry edilməlidir. Cache-lənmiş 500 cavabı client-ə daim xəta göstərər.
- **Key-i user-specific etməmək:** `idempotency:{key}` — fərqli user eyni key göndərsə başqa istifadəçinin cavabını alar.

---

## İntervyu Sualları

**1. Idempotency key nədir, kim yaradır?**
Client tərəfindən yaradılan UUID. Retry-da eyni key göndərilir — server eyni cavabı qaytarır, operation təkrarlanmır. Server generate etsə retry-da yeni key olardı → yeni charge.

**2. Cache vs DB idempotency fərqi nədir?**
Cache: sürətli, lakin eviction/restart riski. DB UNIQUE constraint: persistent, race condition-a davamlı. Kritik əməliyyatlar (payment) üçün DB. Non-critical (notification) üçün cache kifayət edir.

**3. Race condition — iki eyni request eyni anda gəlirsə?**
Cache `add()` (SET NX) atomic — yalnız biri uğurlu olur, digəri gözləyir. DB UNIQUE constraint: eyni anda insert cəhdi olduqda biri constraint violation alır, transaction rollback. `lockForUpdate` ilə mövcud record tapılır, eyni nəticə qaytarılır.

**4. Idempotency key nə qədər saxlanmalıdır?**
24-48 saat standard. Client-in retry window-u nə qədərdirsə o qədər saxla. Uzun saxlamaq storage artırır, qısa saxlamaq window bitdikdən sonra gələn retry-ı bloklamır.

---

## Idempotency + Outbox birlikdə

*Bu kod idempotency yoxlaması ilə ödənişi həm DB-yə, həm outbox-a eyni transaksiyada yazan servis metodunu göstərir:*

```php
// Kritik ssenari: Payment charge edildi, lakin DB yazılmadı (crash).
// Idempotency tək başına bunu həll etmir — Outbox lazımdır.

class PaymentService
{
    public function charge(int $userId, int $amount, string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($userId, $amount, $idempotencyKey) {
            // 1. Idempotency yoxla
            $existing = Payment::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()->first();
            if ($existing) return $existing;

            // 2. Gateway-ə charge et
            $gatewayResult = $this->gateway->charge($amount, $idempotencyKey);

            // 3. DB-yə yaz
            $payment = Payment::create([
                'user_id'         => $userId,
                'amount'          => $amount,
                'idempotency_key' => $idempotencyKey,
                'gateway_id'      => $gatewayResult['id'],
                'status'          => 'completed',
            ]);

            // 4. Outbox: event eyni transaction-da — crash-dan qorunma
            OutboxEvent::create([
                'event_type' => 'payment.completed',
                'payload'    => json_encode(['payment_id' => $payment->id]),
            ]);

            return $payment;
            // Commit: payment + outbox eyni anda. Gateway artıq charge edib.
            // Idempotency key zəmanəti: retry → existing qayıdır.
        });
    }
}
```

---

## Idempotency Key Format Konvensiyaları

```
Standard formatlar:
  UUID v4:     "550e8400-e29b-41d4-a716-446655440000"
  ULID:        "01ARZ3NDEKTSV4RRFFQ69G5FAV"  (time-sortable)

Client tərəfindən yaradılma nümunəsi (JS/mobile):
  const key = crypto.randomUUID();
  fetch('/payments', {
    headers: { 'Idempotency-Key': key }
  });

PHP server validation:
  if (!Str::isUuid($key)) {
      return response()->json(['error' => 'Invalid idempotency key format'], 422);
  }

Stripe konvensiyası: 255 karakter max, alphanumeric + dash/underscore.
```

---

## Anti-patternlər

**1. Idempotency key-i URL query param kimi göndərmək**
`POST /payments?idempotency_key=abc` — key URL-dədir, server-side log-larda, proxy cache-lərdə görünür, təhlükəsizlik riski yaranır. Key `Idempotency-Key` HTTP header-ı kimi göndərilməlidir.

**2. Cavabı sonsuz müddət cache-ləmək**
Idempotency key-i TTL-siz DB-də saxlamaq — storage sonsuz böyüyür, köhnə key-lər yer tutur. Key-lər 24-48 saatdan artıq saxlanmamalı, expired key-lər cron job ilə silinməlidir.

**3. Partial response-u cache-ləmək**
Əməliyyat yarımçıq tamamlandıqda (timeout, partial failure) nəticəni cache-ləmək — retry eyni yarımçıq cavabı alır, əməliyyat heç vaxt tamamlanmır. Yalnız tam uğurlu (2xx) və ya tam uğursuz cavablar cache-lənməlidir.

**4. Key-i user konteksti olmadan validasiya etmək**
Yalnız key-in mövcudluğunu yoxlamaq, hansı user-a aid olduğunu yoxlamamaq — fərqli user eyni key göndərsə başqa user-ın cavabını alır. Cache key mütləq `user_id:idempotency_key` formatında olmalıdır.

**5. GET/DELETE endpoint-lərinə idempotency tətbiq etmək**
GET artıq öz-özlüyündə idempotentdir, DELETE isə natural idempotentdir (ikinci dəfə çağırılsa 404 qaytar, cavabı cache-ləmə). Idempotency key mexanizmi yalnız state dəyişdirən POST/PUT əməliyyatları üçün lazımdır.

**6. In-flight request-lər üçün 200 əvəzinə 409 qaytarmaq**
Eyni key ilə request hələ işlənərkən ikinci request gəldikdə 409 Conflict qaytarmaq — client retry edib üçüncü charge yarada bilər. Doğru cavab 409 deyil, `processing` statuslu 202 Accepted və ya müştəriyə "gözlə" siqnalı verən cavabdır.
