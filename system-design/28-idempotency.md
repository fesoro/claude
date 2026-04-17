# Idempotency

## Nədir? (What is it?)

Idempotency - eyni əməliyyatın bir neçə dəfə icra edilməsinin sistemə eyni təsir etməsi xüsusiyyətidir. Yəni əməliyyatı bir dəfə və ya 100 dəfə çağırsan, nəticə eyni olur.

**Riyazi tərif:** f(f(x)) = f(x)

**Real nümunə:**
- **Idempotent:** "email 'ok@test.com' olan user-in adını 'Ali' et" - neçə dəfə çağır, ad Ali qalır
- **Non-idempotent:** "balance-ı 100 AZN artır" - iki dəfə çağırsan 200 AZN artır

Niyə vacibdir?
- **Network retries** - Timeout-da client yenidən cəhd edir
- **Exactly-once delivery** - Message queue-larda duplicate gələ bilər
- **Client errors** - "Submit" düyməsinə iki dəfə basmaq
- **Payment systems** - İki dəfə ödəniş qəbul edilməməli

## Əsas Konseptlər (Key Concepts)

### 1. HTTP Methods Idempotency

HTTP spec-ə görə:
- **GET** - Idempotent (data dəyişdirmir)
- **PUT** - Idempotent (full replace)
- **DELETE** - Idempotent (artıq yoxdursa, 404 deyil - 204)
- **POST** - Idempotent DEYİL (yeni resurs yaradır)
- **PATCH** - Idempotent olmaya bilər (increment operations)

**Diqqət:** Idempotent ≠ Safe. GET safe-dir (state dəyişdirmir). PUT/DELETE idempotent-dir amma safe deyil.

### 2. Idempotency Keys

Idempotency key - client tərəfindən generasiya olunan unique identifier. Hər unique əməliyyat üçün bir key. Eyni key ilə gələn sorğular eyni nəticəni qaytarır.

```http
POST /api/payments HTTP/1.1
Content-Type: application/json
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000

{
  "amount": 100,
  "currency": "AZN"
}
```

Server tərəfi:
1. Key-i database-də axtar
2. Tapılsa - cache-dəki response-u qaytar
3. Tapılmasa - əməliyyatı icra et, key+response saxla
4. TTL verərək köhnə key-ləri sil (24 saat tipik)

### 3. Retry Safety

Network flaky olanda client retry edir. Retry strategies:
- **Exponential backoff** - 1s, 2s, 4s, 8s, 16s
- **Jitter** - retry-lər sinxron olmasın deyə random əlavə
- **Max attempts** - sonsuz retry etmə (adətən 3-5 dəfə)
- **Circuit breaker** - çox fail olanda dayandır

Retry yalnız idempotent əməliyyatlar üçün təhlükəsizdir!

### 4. Database Constraints

Idempotency-ni təmin etmək üçün DB constraints:
- **UNIQUE constraints** - duplicate insert qarşısı
- **INSERT ... ON DUPLICATE KEY UPDATE** (MySQL)
- **INSERT ... ON CONFLICT** (PostgreSQL)
- **Optimistic locking** - version field ilə concurrent update-lər

### 5. Idempotency Patterns

**1. Natural Idempotency:**
`UPDATE users SET status='active' WHERE id=1` - neçə dəfə icra et, status active qalır.

**2. Upsert Pattern:**
Record yoxdursa insert, varsa update. Hər iki halda eyni son nəticə.

**3. Status Check:**
```
if (order.status != 'paid') {
    chargePayment();
    order.status = 'paid';
}
```

**4. Deduplication Store:**
Request ID-ləri Redis-də saxla. Hər sorğu öncə burada yoxlan.

**5. Event Sourcing:**
Event-lər append-only, deduplication event ID əsasında.

### 6. API Design for Idempotency

Yaxşı API dizaynı:
- Client-də resource ID generasiya et (UUID, ULID)
- PUT /resources/{id} - idempotent create/update
- Idempotency-Key header istifadəçi tərəfdən
- Same key + same body = same response
- Same key + different body = 409 Conflict

## Arxitektura (Architecture)

```
Client
  ↓ POST /api/payments
  ↓ Idempotency-Key: abc-123
  ↓
API Gateway
  ↓ Check Redis: key "abc-123"?
  ↓    Found → return cached response
  ↓    Not found → continue
  ↓
Laravel App
  ↓ Begin Transaction
  ↓ Create Payment
  ↓ Store (key, response_hash, response) in Redis/DB
  ↓ Commit
  ↓
Return Response
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Idempotency Middleware

```php
<?php
// app/Http/Middleware/Idempotency.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class Idempotency
{
    private const CACHE_TTL = 86400; // 24 hours
    private const LOCK_TTL = 30; // 30 seconds

    public function handle(Request $request, Closure $next): Response
    {
        // Yalnız non-idempotent method-lar üçün işlə
        if (!in_array($request->method(), ['POST', 'PATCH'])) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json([
                'error' => 'Idempotency-Key header is required'
            ], 400);
        }

        // Key format validation (UUID)
        if (!$this->isValidKey($key)) {
            return response()->json([
                'error' => 'Invalid Idempotency-Key format'
            ], 400);
        }

        $cacheKey = $this->buildCacheKey($key, $request);
        $lockKey = "idempotency_lock:{$key}";

        // Cached response yoxla
        $cached = Cache::get($cacheKey);
        if ($cached) {
            // Request body-nin eyni olduğunu yoxla
            if ($cached['request_hash'] !== $this->hashRequest($request)) {
                return response()->json([
                    'error' => 'Idempotency-Key used with different request'
                ], 409);
            }

            $response = response(
                $cached['body'],
                $cached['status']
            );
            foreach ($cached['headers'] as $name => $value) {
                $response->header($name, $value);
            }
            $response->header('X-Idempotent-Replay', 'true');
            return $response;
        }

        // Lock al - concurrent eyni sorğuları qarşısını al
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        if (!$lock->get()) {
            return response()->json([
                'error' => 'Request is already being processed'
            ], 409);
        }

        try {
            // İkinci dəfə yoxla (double-check)
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return response($cached['body'], $cached['status']);
            }

            // Request icra et
            $response = $next($request);

            // Yalnız uğurlu response-ları cache et
            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'request_hash' => $this->hashRequest($request),
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'headers' => $this->getResponseHeaders($response),
                ], self::CACHE_TTL);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function isValidKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z0-9\-_]{16,64}$/', $key) === 1;
    }

    private function buildCacheKey(string $key, Request $request): string
    {
        $userId = $request->user()?->id ?? 'guest';
        return "idempotency:{$userId}:{$request->path()}:{$key}";
    }

    private function hashRequest(Request $request): string
    {
        return hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'body' => $request->all(),
        ]));
    }

    private function getResponseHeaders(Response $response): array
    {
        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            if (!in_array(strtolower($name), ['set-cookie', 'date'])) {
                $headers[$name] = $values[0] ?? '';
            }
        }
        return $headers;
    }
}
```

### Register Middleware

```php
<?php
// bootstrap/app.php (Laravel 11) və ya app/Http/Kernel.php

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'idempotent' => \App\Http\Middleware\Idempotency::class,
        ]);
    })
    ->create();
```

### Usage in Routes

```php
<?php
// routes/api.php

Route::middleware(['auth:sanctum', 'idempotent'])->group(function () {
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/transfers', [TransferController::class, 'create']);
});
```

### Idempotent Payment Controller

```php
<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        return DB::transaction(function () use ($validated) {
            // Check if payment already exists for this order
            $existingPayment = Payment::where('order_id', $validated['order_id'])
                ->where('status', 'completed')
                ->first();

            if ($existingPayment) {
                return response()->json([
                    'payment' => $existingPayment,
                    'idempotent' => true,
                ], 200);
            }

            $payment = Payment::create([
                'order_id' => $validated['order_id'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'status' => 'pending',
                'reference' => 'PAY-' . uniqid(),
            ]);

            // Call payment gateway
            $result = $this->chargePayment($payment);

            $payment->update([
                'status' => $result['success'] ? 'completed' : 'failed',
                'gateway_reference' => $result['reference'] ?? null,
            ]);

            return response()->json(['payment' => $payment], 201);
        });
    }

    private function chargePayment(Payment $payment): array
    {
        // Gateway API call
        return ['success' => true, 'reference' => 'GW-' . uniqid()];
    }
}
```

### Database-Level Idempotency with Unique Constraints

```php
<?php
// Migration
Schema::create('money_transfers', function (Blueprint $table) {
    $table->id();
    $table->string('idempotency_key')->unique();
    $table->unsignedBigInteger('from_account_id');
    $table->unsignedBigInteger('to_account_id');
    $table->decimal('amount', 15, 2);
    $table->string('status');
    $table->timestamps();

    $table->index(['from_account_id', 'created_at']);
});
```

```php
<?php
namespace App\Services;

use App\Models\MoneyTransfer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function transfer(string $idempotencyKey, int $fromId, int $toId, float $amount): MoneyTransfer
    {
        try {
            return DB::transaction(function () use ($idempotencyKey, $fromId, $toId, $amount) {
                // UNIQUE constraint duplicate key qarşısını alır
                $transfer = MoneyTransfer::create([
                    'idempotency_key' => $idempotencyKey,
                    'from_account_id' => $fromId,
                    'to_account_id' => $toId,
                    'amount' => $amount,
                    'status' => 'processing',
                ]);

                DB::table('accounts')->where('id', $fromId)->decrement('balance', $amount);
                DB::table('accounts')->where('id', $toId)->increment('balance', $amount);

                $transfer->update(['status' => 'completed']);

                return $transfer;
            });
        } catch (QueryException $e) {
            // Duplicate idempotency_key - mövcud transferi qaytar
            if ($e->errorInfo[1] === 1062) { // MySQL duplicate
                return MoneyTransfer::where('idempotency_key', $idempotencyKey)->firstOrFail();
            }
            throw $e;
        }
    }
}
```

### Idempotent Job Processing

```php
<?php
namespace App\Jobs;

use App\Models\ProcessedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public string $eventId,
        public array $payload
    ) {}

    public function handle(): void
    {
        // Idempotency yoxlaması - duplicate processing qarşısı
        if (ProcessedEvent::where('event_id', $this->eventId)->exists()) {
            return; // Artıq emal olunub
        }

        try {
            // Business logic
            $this->processEvent();

            // Idempotency marker
            ProcessedEvent::create([
                'event_id' => $this->eventId,
                'processed_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Retry olunacaq
            throw $e;
        }
    }

    private function processEvent(): void
    {
        // Actual processing
    }
}
```

### Client-Side Idempotency Key Generation

```php
<?php
// Client tərəfdən
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

$idempotencyKey = (string) Str::uuid();

$response = Http::withHeaders([
    'Idempotency-Key' => $idempotencyKey,
    'Authorization' => 'Bearer ' . $token,
])->post('https://api.example.com/payments', [
    'amount' => 100,
    'currency' => 'AZN',
]);

// Network error olarsa, eyni key ilə retry et
if ($response->failed()) {
    $response = Http::withHeaders([
        'Idempotency-Key' => $idempotencyKey, // eyni key!
    ])->retry(3, 1000)->post(...);
}
```

## Real-World Nümunələr

- **Stripe** - `Idempotency-Key` header, 24 saat saxlayır, standard oldu
- **PayPal** - `PayPal-Request-Id` header istifadə edir
- **AWS** - SQS message deduplication ID, 5 dəqiqə window
- **Kafka** - Idempotent producer (enable.idempotence=true)
- **Square** - `idempotency_key` body field
- **Twilio** - Unique message SID ilə duplicate SMS qarşısı
- **GitHub API** - Webhook delivery ID-lər

## Interview Sualları

**Q1: Idempotency və immutability fərqi?**
Idempotency - eyni əməliyyatı bir neçə dəfə icra etmək eyni nəticə verir. Immutability - yaradıldıqdan sonra dəyişmir. Məsələn, `UPDATE user SET name='Ali'` idempotent-dir amma user immutable deyil. Blockchain bloklar həm immutable, həm əməliyyatlar idempotent-dir.

**Q2: Niyə POST idempotent deyil, amma PUT idempotent-dir?**
POST - hər çağırışda yeni resurs yaradır (`/orders` → 3 POST = 3 order). PUT - müəyyən resursu full replace edir (`/users/1` → 3 PUT = eyni user). PUT-da client resource URI-ni bilir, POST-da server təyin edir. Ona görə client-driven ID ilə POST-u idempotent etmək olar.

**Q3: İdempotency key-i necə generasiya edərsən?**
UUID v4 və ya ULID ən yaxşı seçimdir:
- Sufficiently random (collision ehtimalı praktik olaraq sıfır)
- Client-side generation mümkün
- Fixed length (storage planning)
- Opaque (business info ifşa etmir)

Timestamp-based ID-lər sorted istifadə üçün, amma predictable-dir.

**Q4: Idempotency key nə qədər saxlanmalıdır?**
Typical 24 saat (Stripe standard). Faktorlar:
- **Network retry window** - client neçə müddət retry edir
- **Storage cost** - çox key = çox yaddaş
- **Business logic** - payment üçün 24s kifayət

Çox uzun saxlamaq storage problemi, çox qısa retry uğursuz olur.

**Q5: Concurrent requests eyni idempotency key ilə gəlsə?**
Race condition! Həlli:
1. Distributed lock (Redis) - birinci sorğu icra olunur, digəri gözləyir
2. Database UNIQUE constraint - ikinci INSERT fail olur
3. Optimistic concurrency - version/timestamp check
4. Atomic GET-SET (Redis `SETNX`)

Yaxşı implementation: lock + double-check.

**Q6: Idempotency retry-larla necə əlaqəlidir?**
Retry yalnız idempotent əməliyyatlar üçün təhlükəsizdir. Non-idempotent POST retry edərsən iki order yarada bilərsən. Həll:
1. Client idempotency key generate edir
2. Retry-lərdə eyni key istifadə edir
3. Server key-ə əsasən duplicate-i aşkar edir

Exponential backoff + jitter da vacibdir (thundering herd qarşısı).

**Q7: Database-dan istifadə edərək idempotency necə təmin olunur?**
Strategiyalar:
1. **UNIQUE constraints** - duplicate INSERT fail olur
2. **ON CONFLICT / ON DUPLICATE KEY** - upsert pattern
3. **SELECT FOR UPDATE** - pessimistic lock
4. **Version columns** - optimistic locking
5. **Idempotency table** - request_id + response saxla

Payment sistemdə UNIQUE (order_id) üzrə constraint double charge qarşısını alır.

**Q8: Webhook-da idempotency necə edilir?**
Webhook receiver-də:
1. Hər webhook unique `event_id` ilə gəlir
2. Database-də processed_events cədvəli saxla
3. İşləməzdən əvvəl event_id yoxla
4. Emal olunubsa, 200 OK qaytar (retry qarşısı)
5. Transaction-da: emal et + event_id save et
6. Timestamp check - çox köhnə event-lər rədd olunsun (replay attack)

**Q9: Kafka idempotent producer necə işləyir?**
Kafka-da `enable.idempotence=true` olduqda:
- Producer hər message-ə sequence number verir
- Broker partition başına producer_id + sequence_number izləyir
- Duplicate sequence number-li message-lər rədd olunur
- Out-of-order message-lər də tutulur
- Exactly-once semantik producer-broker arasında

**Q10: Stripe idempotency pattern-i necə işləyir?**
Stripe API-də:
1. Client `Idempotency-Key` header göndərir
2. Stripe key + response-u 24 saat saxlayır
3. Eyni key + eyni body = cached response qaytarır
4. Eyni key + fərqli body = `409 Conflict` error
5. Key paralel request-də olarsa, `409 Conflict`
6. Stripe key-i bütün endpoint-lər üçün scoped saxlayır

## Best Practices

1. **Client generates key** - Client-side UUID, server-də deyil
2. **Use UUID v4 or ULID** - Collision-free
3. **Require key for mutations** - POST/PATCH/PUT üçün məcburi et
4. **Short TTL** - 24 saat adətən kifayətdir
5. **Hash request body** - Eyni key fərqli body aşkar et
6. **Use Redis for speed** - In-memory lookup
7. **Lock during processing** - Concurrent eyni key-ləri idarə et
8. **Don't cache errors** - 5xx response-ları saxlama
9. **Atomic DB operations** - Transactions, UNIQUE constraints
10. **Document idempotency** - API docs-da aydın göstər
11. **Test with chaos** - Random failures, retries simulate et
12. **Log duplicate detection** - Metrics: idempotent replay sayı
13. **Natural idempotency preferred** - Upsert-lər simplest
14. **Version in payload** - Large updates üçün version field
15. **Monitor cache hit rate** - Idempotency effectiveness
