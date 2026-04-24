# Idempotency Keys & Dedupe Patterns

> **Seviyye:** Intermediate ⭐⭐

## Idempotency nedir?

Bir emeliyyatin **eyni input** ile bir defe ve ya 100 defe icra olunmasi **eyni netice** verir. Side effect yalniz **bir defe** bas verir.

```
1-ci defe POST /charge { amount: 100, key: "abc" } -> charge yaranir, 200 OK
2-ci defe POST /charge { amount: 100, key: "abc" } -> ayni cavab, charge YOX (cached)
3-cu defe POST /charge { amount: 100, key: "abc" } -> ayni cavab, charge YOX
```

**Niye lazimdir?**
- **Network retry**: Client timeout aldi, request server-de ugurlu oldu, client retry edir -> double charge
- **Double-click**: User submit duymesini 2 defe sixir -> 2 order
- **Queue replay**: Message broker (RabbitMQ, SQS) message-i 2 defe deliver edir (at-least-once delivery)
- **Webhook retries**: Stripe/GitHub webhook deliver alinmadi, 5 defe retry edir
- **Mobile network**: Zeif 3G - request gedir, response itir, app retry edir

---

## Idempotent Operations Tebii Olaraq

| Operation | Idempotent? | Niye |
|-----------|-------------|------|
| `GET /users/1` | Ha | Read-only |
| `PUT /users/1 { name: "Ali" }` | Ha | Eyni state-e setter |
| `DELETE /users/1` | Ha | Sonra silinmis qalir |
| `POST /charges` | **Yox** | Her defe yeni charge yaranir |
| `UPDATE counter SET n = n + 1` | **Yox** | Her defe artir |

POST hadiseleri xususi qaydaya teleb edir - **idempotency key** lazimdir.

---

## Stripe-style Idempotency-Key Header

Stripe API client-den unique key qebul edir:

```
POST /v1/charges HTTP/1.1
Idempotency-Key: 11dee1cf-a682-4f71-9f4c-9d5a1b1234ab
Content-Type: application/json

{ "amount": 1000, "currency": "usd", "source": "tok_visa" }
```

**Server logic:**
1. Key DB-de var? -> kohne response qaytar (200 OK with cached body)
2. Key yoxdur? -> emeliyyati icra et, response saxla, qaytar
3. Key var, amma "in_progress"? -> 409 Conflict ve ya goz lat

**Stripe rules:**
- Key 24 saat saxlanir (TTL)
- Key + endpoint + body hash birlikde yoxlanir
- Eyni key, ferqli body -> 400 Bad Request

---

## Dedupe Table Schema

```sql
CREATE TABLE idempotency_keys (
    key VARCHAR(255) PRIMARY KEY,
    user_id BIGINT NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_hash CHAR(64) NOT NULL,        -- SHA-256 of request body
    response_status SMALLINT NULL,
    response_body JSON NULL,
    status ENUM('in_progress', 'completed', 'failed') DEFAULT 'in_progress',
    locked_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NOT NULL,         -- created_at + 24h
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at),
    INDEX idx_user_endpoint (user_id, endpoint)
);
```

**Cleanup job** (Laravel scheduler):

```php
// app/Console/Kernel.php
$schedule->call(fn() => DB::table('idempotency_keys')
    ->where('expires_at', '<', now())
    ->delete()
)->hourly();
```

---

## Laravel Middleware Implementation

```php
// app/Http/Middleware/Idempotency.php
class Idempotency
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (!$key) {
            return $next($request);
        }

        $hash = hash('sha256', $request->getContent());
        $endpoint = $request->method() . ' ' . $request->path();

        // Atomic insert (or fetch existing)
        $record = DB::transaction(function () use ($key, $request, $hash, $endpoint) {
            $existing = DB::table('idempotency_keys')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->request_hash !== $hash) {
                    abort(400, 'Idempotency key reused with different body');
                }
                return $existing;
            }

            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'status' => 'in_progress',
                'locked_at' => now(),
                'expires_at' => now()->addDay(),
                'created_at' => now(),
            ]);

            return null; // First time
        });

        // Cached response var
        if ($record && $record->status === 'completed') {
            return response($record->response_body, $record->response_status)
                ->header('Idempotent-Replay', 'true');
        }

        // In-progress (concurrent request)
        if ($record && $record->status === 'in_progress') {
            abort(409, 'Request already in progress');
        }

        // Yeni request - icra et, neticeni saxla
        $response = $next($request);

        DB::table('idempotency_keys')->where('key', $key)->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $response;
    }
}
```

**Route-da istifade:**

```php
Route::post('/charges', [ChargeController::class, 'create'])
    ->middleware(['auth', 'idempotency']);
```

---

## Cache-Based Implementation (Sade variant)

DB table yerine Redis (TTL avtomatik):

```php
class CacheIdempotency
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (!$key) return $next($request);

        $cacheKey = "idempotent:{$key}";

        // SETNX (atomic) - lock al
        $acquired = Cache::add($cacheKey . ':lock', 1, 60); // 60s lock

        if (!$acquired) {
            // Basqa request isleyir - cached cavab var?
            if ($cached = Cache::get($cacheKey)) {
                return response($cached['body'], $cached['status']);
            }
            abort(409, 'Concurrent request');
        }

        try {
            $response = $next($request);
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], now()->addDay());
            return $response;
        } finally {
            Cache::forget($cacheKey . ':lock');
        }
    }
}
```

**DB vs Cache muqayisesi:**

| Aspekt | DB Table | Redis Cache |
|--------|----------|-------------|
| Persistence | Restart-a davamli | Restart-da itir (RDB/AOF olmasa) |
| TTL | Manual cleanup lazim | Avtomatik |
| Atomic insert | UNIQUE + lockForUpdate | SETNX |
| Audit trail | Var | Yoxdur |
| Multi-server | Native | Native (shared Redis) |

---

## UPSERT with Idempotency Key

DB-level dedupe (key column UNIQUE):

```sql
-- PostgreSQL
INSERT INTO payments (idempotency_key, user_id, amount, status)
VALUES ('abc123', 1, 100.00, 'pending')
ON CONFLICT (idempotency_key) DO NOTHING
RETURNING id, status;

-- MySQL
INSERT INTO payments (idempotency_key, user_id, amount, status)
VALUES ('abc123', 1, 100.00, 'pending')
ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
```

```php
// Laravel upsert
Payment::upsert(
    [['idempotency_key' => $key, 'user_id' => 1, 'amount' => 100, 'status' => 'pending']],
    ['idempotency_key'],   // unique by
    []                      // do not update existing
);

$payment = Payment::where('idempotency_key', $key)->first();
```

---

## Request Hash vs Explicit Key

| Pattern | Pros | Cons |
|---------|------|------|
| **Explicit Key** (Stripe) | Client kontrol edir, reliable | Client UUID generasiya etmelidir |
| **Auto Hash** (request body) | Client-de deyisiklik lazim deyil | Eyni body 2 defe gondermek olmur (intentional duplicate?) |

**Hibrid yanasma:**
```php
$key = $request->header('Idempotency-Key')
    ?? hash('sha256', $request->user()->id . $request->getContent());
```

---

## Concurrent Same-Key Handling

Iki request ayni key ile **ayni anda** gelir:

**Strateqiya 1: Lock + Wait**
```php
$lock = Cache::lock("idem:{$key}", 30);
$lock->block(10); // 10s gozle
try {
    // Process
} finally {
    $lock->release();
}
```

**Strateqiya 2: Reject (409 Conflict)**
```php
if (!Cache::add("idem:{$key}", 1, 60)) {
    abort(409, 'Already processing');
}
```

**Strateqiya 3: DB Row Lock**
```php
DB::transaction(function () use ($key) {
    $row = DB::table('idempotency_keys')
        ->where('key', $key)
        ->lockForUpdate()  // Diger request burada gozler
        ->first();
    // ...
});
```

---

## Queue Job Idempotency

Laravel `ShouldBeUnique`:

```php
class ProcessOrder implements ShouldQueue, ShouldBeUnique
{
    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return "order:{$this->orderId}";
    }

    public int $uniqueFor = 3600; // 1 saat

    public function handle(): void
    {
        // Eyni $orderId ile yeni dispatch -> ignore olunur
    }
}

// Multiple dispatch -> yalniz 1 isleyir
ProcessOrder::dispatch(42);
ProcessOrder::dispatch(42); // skip (Redis lock var)
```

**Manual dedupe (`processed_jobs` table):**

```php
public function handle(): void
{
    $exists = DB::table('processed_jobs')
        ->where('job_key', $this->uniqueId())
        ->exists();

    if ($exists) return; // already processed

    DB::transaction(function () {
        // do work
        DB::table('processed_jobs')->insert([
            'job_key' => $this->uniqueId(),
            'processed_at' => now(),
        ]);
    });
}
```

---

## Webhook Delivery Idempotency

Stripe/GitHub webhook event-i 2 defe gondere biler:

```php
// webhooks/stripe.php
public function handle(Request $request)
{
    $event = $request->json()->all();
    $eventId = $event['id']; // 'evt_1abc...'

    // Insert; conflict olarsa - skip
    $inserted = DB::table('processed_webhooks')->insertOrIgnore([
        'event_id' => $eventId,
        'received_at' => now(),
    ]);

    if (!$inserted) {
        return response('Already processed', 200);
    }

    // Process event
    match ($event['type']) {
        'payment_intent.succeeded' => $this->handlePayment($event),
        // ...
    };

    return response('OK', 200);
}
```

`processed_webhooks` table:

```sql
CREATE TABLE processed_webhooks (
    event_id VARCHAR(100) PRIMARY KEY,
    received_at TIMESTAMP NOT NULL
);
```

---

## Outbox Pattern Integration

Outbox + idempotency = exactly-once-effect (at-least-once delivery + consumer-side dedupe):

```sql
-- Producer side: outbox
CREATE TABLE outbox_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id UUID NOT NULL UNIQUE,
    aggregate_id BIGINT,
    event_type VARCHAR(100),
    payload JSON,
    created_at TIMESTAMP,
    published_at TIMESTAMP NULL
);

-- Consumer side: dedupe
CREATE TABLE consumed_events (
    event_id UUID PRIMARY KEY,
    consumed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

```php
// Consumer
public function handleEvent(array $event): void
{
    $inserted = DB::table('consumed_events')
        ->insertOrIgnore(['event_id' => $event['event_id']]);

    if (!$inserted) return; // Already handled

    // Business logic
}
```

---

## Testing Strategies

```php
// Tests/Feature/IdempotencyTest.php
public function test_same_idempotency_key_returns_same_response()
{
    $key = (string) Str::uuid();

    $response1 = $this->postJson('/charges', ['amount' => 100], [
        'Idempotency-Key' => $key,
    ]);

    $response2 = $this->postJson('/charges', ['amount' => 100], [
        'Idempotency-Key' => $key,
    ]);

    $response1->assertStatus(201);
    $response2->assertStatus(201)->assertHeader('Idempotent-Replay', 'true');

    $this->assertSame($response1->json('id'), $response2->json('id'));
    $this->assertCount(1, Charge::all()); // Yalniz 1 charge yarandi
}

public function test_same_key_different_body_rejected()
{
    $key = (string) Str::uuid();
    $this->postJson('/charges', ['amount' => 100], ['Idempotency-Key' => $key])
        ->assertCreated();
    $this->postJson('/charges', ['amount' => 200], ['Idempotency-Key' => $key])
        ->assertStatus(400);
}
```

---

## Real-World Use Cases

| Use Case | Pattern | TTL |
|----------|---------|-----|
| Stripe payment | Idempotency-Key header + DB | 24h |
| Webhook receiver | event_id UNIQUE | 7-30d |
| Queue job | ShouldBeUnique + Redis lock | 1h-24h |
| SMS gateway | client_message_id UNIQUE | 24h |
| Order placement | Order::firstOrCreate by client_token | 1h |
| Email send | (recipient + template + day) hash | 24h |

---

## Interview suallari

**Q: Idempotency-Key TTL niye 24 saat?**
A: Stripe convention. Cox uzun TTL = DB sismek; cox qisa = legitimate retry-ler problem yaradir. 24h muddet praktiki balansdir - mobile app offline 1-2 gun qala biler, cron retry-leri 6-12 saat icinde olar. Sensitive sistemlerde 7 gun, lightweight sistemlerde 1 saat istifade etmek olar.

**Q: PUT idempotent oldugu halda niye Idempotency-Key lazimdir?**
A: PUT logical olaraq idempotent-dir, amma side-effect-ler problemdir. Misal: PUT /users/1 { balance: 100 } - duzgun set edir, amma audit log-a 2 defe yazilir, notification 2 defe gonderilir. Idempotency-Key butun side-effect-leri exactly-once edir.

**Q: Concurrent eyni-key request gelirse?**
A: Uc sec: 1) **Lock + wait** - 2-ci request 1-ci-nin bitmesini gozleyir, sonra cached cavab alir (best UX). 2) **409 Conflict** - 2-ci derhal red olunur (sade, amma client retry yazmalidir). 3) **DB row lock** (lockForUpdate) - similar to lock+wait, amma DB seviyyesinde. Stripe 409 qaytarir.

**Q: Idempotency vs Distributed Transactions ferqi?**
A: Distributed transaction (2PC) bir nece sistem arasinda ATOM commit etmeye calisir - murekkeb ve fragile. Idempotency exactly-once-effect alir - retry-leri saglam ele alir. Modern microservices distributed TX yerine **idempotency + saga + outbox** kombinasiyasini istifade edir.

**Q: Queue-da ShouldBeUnique vs application-level dedupe?**
A: ShouldBeUnique queue seviyyesinde - eyni job dispatch-ini blok edir. Application-level dedupe (processed_jobs) job consume olunduqdan sonra isleyir - retry-de tekrar islememeyi temin edir. Ikisi birlikde lazimdir: ShouldBeUnique = "ayni job 2 defe queue-a girmesin", processed_jobs = "ayni job 2 defe icra olunmasin".
