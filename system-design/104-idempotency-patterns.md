# Idempotency Patterns (Senior)

## Mündəricat
1. At-most-once, at-least-once, exactly-once çatdırılma
2. Idempotency key pattern
3. Ödəniş emalı nümunəsi
4. Idempotency middleware
5. PHP İmplementasiyası
6. İntervyu Sualları

---

## Çatdırılma Semantikası

Distributed sistemlərdə network xətaları, restart-lar, timeout-lar qaçılmazdır. Sual odur ki, mesaj/sorğu neçə dəfə çatdırılır:

```
                    ┌─────────────────────────────┐
                    │   Çatdırılma Semantikası     │
                    ├────────────┬────────────────┤
                    │   Növ      │  Çatdırılma    │
                    ├────────────┼────────────────┤
                    │ At-most-once  │ 0 və ya 1   │
                    │ At-least-once │ 1+          │
                    │ Exactly-once  │ həmişə 1    │
                    └────────────┴────────────────┘
```

### At-most-once

Göndər, cavab gözləmə. Network xətasında yenidən cəhd yoxdur.

```
Client → POST /payment
             ↓
        Network timeout
             ↓
Client: "bilmirəm, bir daha göndərərəm? YOX"
→ Payment itə bilər (under-charge)
```

**İstifadə yeri:** Log, analitika — itirilsə problem deyil.

### At-least-once

Cavab alana qədər yenidən cəhd et. Mesaj birdən çox çata bilər.

```
Client → POST /payment → Server icra etdi, amma cavab itdi
Client timeout → yenidən POST /payment
→ Payment iki dəfə çıxıla bilər (over-charge)!
```

**İstifadə yeri:** Message queue consumer-lar (idempotency ilə birlikdə).

### Exactly-once

Ən çətini. Praktikada "at-least-once + idempotency consumer" ilə simulyasiya edilir.

```
Producer → [Message Queue] → Consumer
                                 ↓
                         idempotency_key yoxla
                                 ↓
                    artıq var? → skip
                    yoxdur?   → icra et + key-i saxla
```

---

## Idempotency Key Pattern

Eyni sorğunu dəfələrlə göndərmək eyni nəticəni verməlidir.

```
İlk sorğu:
POST /payments
Headers: Idempotency-Key: pay_3f8a2b1c4d5e6f7a
Body: { amount: 100, currency: "AZN", to: "acc_123" }

Server:
1. "pay_3f8a2b1c4d5e6f7a" cache-də varmı? → YOX
2. Ödənişi icra et
3. Nəticəni cache-ə yaz: key → {status: 200, body: {...}}
4. Cavab qaytar

Retry (network timeout sonra):
POST /payments
Headers: Idempotency-Key: pay_3f8a2b1c4d5e6f7a  ← eyni key

Server:
1. "pay_3f8a2b1c4d5e6f7a" cache-də varmı? → BƏLİ
2. Cached cavabı qaytar (ödəniş yenidən icra edilmir!)
```

**Key generasiya:** Client tərəfindən yaradılır, UUID v4 tövsiyə olunur.

---

## Ödəniş Emalı Nümunəsi

```
Ssenari: Stripe-ə ödəniş göndər

t=0:  App → Stripe API: charge $100 (Idempotency-Key: uuid-abc)
t=5:  Network timeout — cavab gəlmədi
t=6:  App bilmir: charge oldu ya olmadı?
t=7:  App retry → Stripe API: charge $100 (Idempotency-Key: uuid-abc)
t=8:  Stripe: "Bu key-i artıq gördüm, eyni cavabı qaytarıram"
t=9:  App cavabı alır: charge_id=ch_xyz, status=succeeded

Nəticə: Bir dəfə charge olundu ✅
```

**İki növ idempotency:**

```
1. Natural idempotency:
   PUT /users/42 { name: "Ali" }
   → N dəfə göndərsən eyni nəticə (HTTP PUT semantikası)

2. Explicit idempotency key:
   POST /payments (Idempotency-Key: uuid)
   → POST natural olaraq idempotent deyil, key ilə edilir
```

---

## Idempotency Middleware

```
Request Pipeline:

┌─────────┐    ┌─────────────────┐    ┌──────────────┐
│ Request │───▶│ Idempotency     │───▶│  Controller  │
│         │    │ Middleware      │    │              │
└─────────┘    └────────┬────────┘    └──────┬───────┘
                        │ key var?            │
                        │ evet → cache        │ response
                        │ hayır → pass        ▼
                        │              ┌──────────────┐
                        └◀─────────────│ Cache Store  │
                          key + resp   └──────────────┘
```

---

## PHP İmplementasiyası

```php
<?php

class IdempotencyMiddleware
{
    private Redis $redis;
    private int $ttl;

    // Pending marker: eyni anda iki request eyni key ilə gəlsə
    private const PENDING_MARKER = '__PROCESSING__';
    private const PENDING_TTL    = 30; // saniyə

    public function __construct(Redis $redis, int $ttl = 86400)
    {
        $this->redis = $redis;
        $this->ttl   = $ttl;
    }

    public function handle(Request $request, callable $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        // Key yoxdursa birbaşa keç (idempotency tətbiq edilmir)
        if (!$idempotencyKey) {
            return $next($request);
        }

        $cacheKey = $this->buildCacheKey($request, $idempotencyKey);

        // Mövcud cavab varmı?
        $cached = $this->redis->get($cacheKey);

        if ($cached === self::PENDING_MARKER) {
            // Başqa request eyni anda emal edilir
            return new Response(['error' => 'Request is being processed'], 409);
        }

        if ($cached !== false) {
            $data = json_decode($cached, true);
            return new Response(
                $data['body'],
                $data['status'],
                array_merge($data['headers'], ['X-Idempotent-Replayed' => 'true'])
            );
        }

        // Pending marker qoy (race condition qorunması)
        $set = $this->redis->set(
            $cacheKey,
            self::PENDING_MARKER,
            ['NX', 'EX' => self::PENDING_TTL]
        );

        if (!$set) {
            // Başqa request artıq pending marker qoyub
            return new Response(['error' => 'Concurrent request detected'], 409);
        }

        try {
            $response = $next($request);

            // Yalnız uğurlu cavabları cache-lə (4xx/5xx cache-lənmir)
            if ($response->getStatus() < 500) {
                $this->redis->setex($cacheKey, $this->ttl, json_encode([
                    'status'  => $response->getStatus(),
                    'body'    => $response->getBody(),
                    'headers' => $response->getHeaders(),
                ]));
            } else {
                // Server xətasında pending marker-ı sil (retry mümkün olsun)
                $this->redis->del($cacheKey);
            }

            return $response;
        } catch (\Throwable $e) {
            $this->redis->del($cacheKey);
            throw $e;
        }
    }

    private function buildCacheKey(Request $request, string $idempotencyKey): string
    {
        // Key scope: endpoint + client + idempotency-key
        $clientId = $request->header('X-Client-ID') ?? 'anonymous';
        $endpoint = $request->method() . ':' . $request->path();

        return sprintf(
            'idempotency:%s:%s:%s',
            $endpoint,
            $clientId,
            hash('sha256', $idempotencyKey)
        );
    }
}

// Ödəniş servisi — idempotency key ilə
class PaymentService
{
    private PDO $db;
    private GatewayInterface $gateway;

    public function charge(
        string $idempotencyKey,
        int $userId,
        int $amountCents,
        string $currency
    ): PaymentResult {
        // DB səviyyəsində idempotency (Redis olmadan da işləyir)
        $existing = $this->findByIdempotencyKey($idempotencyKey);
        if ($existing) {
            return PaymentResult::fromRecord($existing);
        }

        // İlk dəfə emal et
        $this->db->beginTransaction();

        try {
            // İdempotency key-i əvvəlcə yaz (unique constraint)
            $this->db->prepare(<<<SQL
                INSERT INTO payments
                  (idempotency_key, user_id, amount_cents, currency, status, created_at)
                VALUES (:key, :user, :amount, :currency, 'pending', NOW())
            SQL)->execute([
                ':key'      => $idempotencyKey,
                ':user'     => $userId,
                ':amount'   => $amountCents,
                ':currency' => $currency,
            ]);

            $paymentId = (int) $this->db->lastInsertId();

            // External gateway çağır
            $gatewayResult = $this->gateway->charge([
                'amount'   => $amountCents,
                'currency' => $currency,
                'metadata' => ['payment_id' => $paymentId],
            ]);

            // Nəticəni yaz
            $this->db->prepare(
                'UPDATE payments SET status = :status, gateway_id = :gid WHERE id = :id'
            )->execute([
                ':status' => $gatewayResult->success ? 'completed' : 'failed',
                ':gid'    => $gatewayResult->transactionId,
                ':id'     => $paymentId,
            ]);

            $this->db->commit();
            return new PaymentResult($paymentId, $gatewayResult->transactionId, 'completed');
        } catch (\PDOException $e) {
            $this->db->rollBack();

            // Duplicate key xətası: eyni idempotency key artıq var
            if ($e->getCode() === '23000') {
                $existing = $this->findByIdempotencyKey($idempotencyKey);
                return PaymentResult::fromRecord($existing);
            }

            throw $e;
        }
    }

    private function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payments WHERE idempotency_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// Queue consumer — at-least-once + idempotency
class OrderEventConsumer
{
    private Redis $redis;
    private OrderService $orderService;

    public function handle(array $message): void
    {
        $messageId = $message['id']; // Queue-dan gələn unikal ID
        $lockKey   = "processed:{$messageId}";

        // Artıq emal edilibmi?
        if ($this->redis->exists($lockKey)) {
            return; // Skip — idempotent consumer
        }

        // Emal et
        $this->orderService->process($message['payload']);

        // Emal olundu olaraq işarələ (24 saat saxla)
        $this->redis->setex($lockKey, 86400, '1');
    }
}
```

---

## İntervyu Sualları

- At-most-once, at-least-once, exactly-once arasındakı fərqi real nümunə ilə izah edin.
- Idempotency key-i server yaratmalıdır yoxsa client? Niyə?
- Eyni idempotency key ilə iki sorğu eyni anda gəlsə nə baş verər? Bu race condition-ı necə həll edərsiniz?
- HTTP metodlarından hansıları natural idempotent-dir? POST idempotent olmayan niyədir?
- Ödəniş sistemlərində idempotency niyə kritikdir? Nə baş verə bilər?
- Idempotency key-i cache-dən nə vaxt silmək lazımdır?
- Message queue-dan eyni mesaj iki dəfə gəlsə consumer necə qorunmalıdır?
- Database-based idempotency ilə Redis-based idempotency arasındakı fərq nədir? Hər birinin üstünlük və çatışmazlıqları nədir?
