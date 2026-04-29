# Idempotency Design (Lead ⭐⭐⭐⭐)

## İcmal
Idempotency, bir əməliyyatın bir dəfə və ya dəfələrlə tətbiq olunmasının eyni nəticəni verməsidir. Distributed sistemlərdə şəbəkə xətaları, timeouts, retry-lar qaçılmazdır — idempotency olmadan bu retry-lar dublikat ödəniş, dublikat sifariş kimi kritik problemlər yaradır. Interview-larda "Bu əməliyyatı necə idempotent edərdiniz?" sualı payment system, messaging, API dizaynı mövzularında mütləq gəlir.

## Niyə Vacibdir
Stripe, PayPal, Amazon — hər ödəniş sisteminin əsasında idempotency dayanır. Distributed sistemlərdə at-least-once delivery defaultdur (Kafka, SQS, HTTP retry). Idempotency olmadan retry = duplicate charge, duplicate order, duplicate record. Lead mühəndis idempotency key, deduplication window, exactly-once semantics mexanizmlərini praktiki olaraq izah edə bilir.

## Əsas Anlayışlar

### 1. Niyə Idempotency Lazımdır

**Network timeout problemi:**
```
Client → POST /payments → Server
Client: 30s timeout, cavab gəlmdi → ERROR

Reality:
- Server ödənişi aldı
- DB-yə yazdı
- Response göndərdi
- Response lost (network problem)

Client nə etmə:
1. Retry → Duplicate payment ← PROBLEM
2. No retry → Client error görür, amma payment deducted ← PROBLEM
3. Idempotency key ilə retry → SAFE ✓
```

**At-least-once delivery:**
```
Kafka → Consumer processes message
Consumer: message processed, crash before commit
Kafka: consumer offset not advanced
Kafka: message yenidən deliver eder

Idempotent consumer olmasa → duplicate processing
```

### 2. Idempotency Key Pattern

**API Request:**
```http
POST /api/payments
Idempotency-Key: a7f3d0b9-c4e2-4f8a-b1d5-2e9c8f7a3b6d
Content-Type: application/json

{
  "amount": 100,
  "currency": "USD",
  "to": "account_456"
}
```

**Server-side logic:**
```
1. Idempotency-Key-i al
2. Redis/DB-də bu key var mı?
   - Var → əvvəlki response-u qaytar (process etmə)
   - Yoxdur → process et → result-u key ilə saxla → response qaytar
3. Key-in TTL: 24 saat (sonra GC)
```

**Redis implementation:**
```lua
-- Atomic check-and-set
local key = "idem:" .. idempotency_key
local exists = redis.call("GET", key)

if exists then
    return exists  -- cached response
end

-- Process (bu Redis dışında olur)
-- ...

-- Save result
redis.call("SET", key, response, "EX", 86400)  -- 24 hour TTL
return response
```

### 3. HTTP Methods Idempotency
```
GET    → Idempotent (dəfələrlə eyni nəticə)
HEAD   → Idempotent
PUT    → Idempotent ("user-ı bu vəziyyətə qoy" — dəfələrlə ok)
DELETE → Idempotent (eyni resource-u dəfələrlə sil → eyni nəticə)
POST   → NOT idempotent by default (hər call yeni resource yaradır)
PATCH  → NOT idempotent (PATCH /counter {increment: 1} = dəfələrlə +1)
```

**PUT vs PATCH idempotency:**
```
PUT /users/123 {"name": "Ali", "age": 30}
→ Dəfələrlə çağırsan eyni user
→ Idempotent ✓

PATCH /users/123 {"age": {increment: 1}}
→ Hər call yaşı 1 artırır
→ NOT idempotent ✗

PATCH /users/123 {"age": 30}
→ Yaşı 30-a SET edir
→ Idempotent ✓
```

### 4. Database Level Idempotency

**Unikal constraint:**
```sql
CREATE TABLE payments (
    id UUID PRIMARY KEY,
    idempotency_key VARCHAR(255) UNIQUE,  -- duplicate constraint
    amount DECIMAL,
    status VARCHAR(20),
    created_at TIMESTAMP
);

INSERT INTO payments (id, idempotency_key, amount, ...)
VALUES ('uuid', 'idem-key-123', 100, ...)
ON CONFLICT (idempotency_key) 
DO NOTHING  -- duplicate silently ignored
RETURNING id, status;  -- returns existing row
```

**Conditional update (optimistic idempotency):**
```sql
-- Yalnız "pending" ödənişi "completed"-ə dəyiş
UPDATE payments 
SET status = 'completed', processed_at = NOW()
WHERE id = :payment_id 
  AND status = 'pending'  -- state machine, idempotent
RETURNING *;

-- Artıq completed → 0 rows updated → idempotent behavior
```

### 5. Event/Message Idempotency

**Deduplication ID:**
```
Kafka message:
{
  "event_id": "evt_abc123",  // unique per event
  "type": "order.created",
  "order_id": "ord_456",
  "timestamp": "2026-04-26T10:00:00Z"
}

Consumer:
1. event_id-ni Redis/DB-də yoxla
2. Var → skip (already processed)
3. Yoxdur → process → event_id-ni mark et (TTL: 7 gün)
```

**SQS deduplication:**
```
SQS FIFO Queue:
- MessageDeduplicationId field
- 5 dəqiqəlik deduplication window
- Eyni ID-li mesaj 5 dəq içinde → silently dropped
```

### 6. Distributed Transaction Idempotency (Saga)
```
Order saga:
Step 1: Reserve inventory (idempotency_key: "ord_123:reserve")
Step 2: Charge payment  (idempotency_key: "ord_123:charge")
Step 3: Ship order      (idempotency_key: "ord_123:ship")

Saga orchestrator crash olsa:
→ Restart, hər step-i retry edir
→ İdempotency key olduğu üçün dublicate olmur
→ Already completed step-lar keçilir
```

### 7. Idempotency Key Strategiyaları

**Client-generated UUID:**
```
Client: UUID.randomUUID() → idempotency key
Server: key-i saxla
Pros: Sadə, client control-da
Cons: Malicious client key-i idarə edə bilər
```

**Deterministic key:**
```
Key = hash(user_id + amount + target_account + date)
Pros: Replay-safe, predictable
Cons: Eyni parameters ilə ikinci ödəniş mümkün deyil
     (intentional duplicate blocked)
```

**Server-issued token:**
```
Step 1: GET /payment-intents → server token qaytarır
Step 2: POST /payments?intent_token=xxx
Server token = server-side idempotency anchor
Stripe bu pattern-i istifadə edir (PaymentIntent)
```

### 8. Race Condition Protection
```
Problem:
T1: Check key → not exists
T2: Check key → not exists
T1: Process payment
T2: Process payment  ← DUPLICATE!

Həll 1: Database unique constraint (atomic)
INSERT → unique constraint violation → handle error

Həll 2: Redis SET NX (SET if Not eXists)
SETNX idem:key:abc "processing" → 0 (exists) or 1 (set)
Atomic! Only 1 winner.

Həll 3: Pessimistic lock
SELECT ... FOR UPDATE WHERE idempotency_key = ?
Lock gets released after commit
```

### 9. Idempotency Response Caching
```
Idempotency sadəcə "duplicate-i block etmək" deyil,
həm də "eyni response qaytarmaq"-dır.

Client: Timeout oldu, bilmir ödəniş keçdi ya yox
Client: Retry edir (eyni idempotency key ilə)
Server: Əvvəlki response-u Redis-dən qaytarır

Response:
{
  "payment_id": "pay_abc",
  "status": "success",
  "amount": 100,
  "idempotent": true  // optional flag
}

Client: Response aldı → məsələ həll oldu
```

### 10. Deduplication Window
```
TTL seçimi:
- Çox qısa (5 dəq): Retry window-dan sonra → duplicate mümkün
- Çox uzun (30 gün): Memory/storage baha
- Optimal: 24-48 saat (əksər retry window-unu əhatə edir)

Idempotency key cleanup:
- TTL expire: Redis/cache avtomatik silir
- DB: Periodic cleanup job (> 7 gün köhnə key-ləri sil)
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "Bu əməliyyat retry-safe-dirmi?" sualını özünüzə verin
2. Idempotency key-in client vs server generated olmasını müzakirə et
3. Race condition-u qeyd et → atomic check-and-set həll et
4. Response caching-i izah et (sadəcə block deyil, same response)
5. TTL/deduplication window seçimini əsaslandır

### Ümumi Namizəd Səhvləri
- "POST idempotent deyil" demək, həll göstərməmək
- Race condition-ı unutmaq (iki paralel request eyni key ilə)
- Response caching-i bilməmək (idempotent = same response, not just block)
- DB unique constraint vs Redis SETNX fərqini müzakirə etməmək
- Deduplication window-ın həm çox qısa, həm çox uzun olmasının problemini bilməmək

### Senior vs Architect Fərqi
**Senior**: Idempotency key pattern implement edir, database unique constraint + Redis SETNX istifadə edir, Kafka consumer idempotency qurur.

**Architect**: Idempotency-ni system-wide design principle kimi tətbiq edir, Stripe PaymentIntent kimi "idempotency as a product feature" dizayn edir, distributed saga-larda idempotency koordinasiyasını idarə edir, idempotency key-in storage cost vs risk trade-off-unu hesablayır, at-exactly-once processing altyapısını (Kafka Transactions) qurur.

## Nümunələr

### Tipik Interview Sualı
"Design a payment API that is safe to retry. Prevent duplicate charges even if the network fails."

### Güclü Cavab
```
Payment API idempotency:

Client request:
POST /api/v1/payments
Headers:
  Authorization: Bearer {token}
  Idempotency-Key: {client-generated UUID v4}
  Content-Type: application/json

Body:
  {"amount": 9900, "currency": "USD", "card_token": "tok_xxx"}

Server flow:
1. Extract Idempotency-Key header
2. Validate: UUID format, max 255 chars
3. Redis lookup: GET "idem:{user_id}:{key}"
   - HIT → return cached response (HTTP 200 with original result)
   - MISS → proceed

4. Atomic claim: SET NX "idem:{user_id}:{key}" "processing" EX 300
   - FAIL (race condition) → wait 100ms, retry lookup → return cached
   - SUCCESS → proceed to process

5. Process payment (Stripe/payment processor API)
   - Generate internal payment_id
   - Charge card
   - Save to DB

6. Cache result: SET "idem:{user_id}:{key}" {json_response} EX 86400
   - 24-hour TTL

7. Return response

Error handling:
- Payment fails → cache failure response (same key returns same failure)
- Server crash mid-process → key expires (300s), client can retry fresh
- DB write fails after payment success → CRITICAL: payment_id in response allows recovery

Monitoring:
- Idempotency key hit rate (cache hits vs misses)
- Duplicate attempt count per user (abuse detection)
- Key store size (Redis memory)
```

### Flow Diaqramı
```
Client ──RETRY──► POST /payments (same Idempotency-Key)
                        │
                   [Redis lookup]
                   Hit? ──YES──► Return cached response
                   │ NO
                   [SET NX "processing"]
                   Won race? ──NO──► Wait, retry lookup
                   │ YES
                   [Process payment]
                        │
                   [Cache result in Redis, TTL 24h]
                        │
                   [Return response]
```

## Praktik Tapşırıqlar
- Laravel Middleware olaraq idempotency key checking implement edin
- Redis SETNX ilə atomic deduplication test edin
- Kafka consumer-ı idempotent edin (deduplication DB ilə)
- Stripe API-nin idempotency key davranışını test edin
- Load test: 100 concurrent request ilə eyni idempotency key → yalnız 1 charge

## Əlaqəli Mövzular
- [08-message-queues.md](08-message-queues.md) — At-least-once vs exactly-once
- [17-distributed-transactions.md](17-distributed-transactions.md) — Saga idempotency
- [25-outbox-pattern.md](25-outbox-pattern.md) — Reliable event delivery
- [13-idempotency-design.md] ← bu fayl
- [23-eventual-consistency.md](23-eventual-consistency.md) — Eventual consistency with idempotency
