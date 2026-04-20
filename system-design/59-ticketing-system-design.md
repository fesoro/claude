# Ticketing System Design (Ticketmaster)

## Nədir? (What is it?)

**Ticketing System** — konsert, idman, teatr biletlərinin onlayn satışı üçün platforma. Ticketmaster, BookMyShow, Eventbrite, StubHub kimi sistemlər. Booking system-dən (Airbnb, otel) əsas fərqi: **flash sale** — 10:00-da bilet açılır, 100k istifadəçi eyni anda "Buy" düyməsinə basır, 50k yer var, 5 dəqiqəyə hər şey bitir. Yəni problem availability tapmaq deyil, **nəhəng concurrency** altında ədalətli və düzgün satmaqdır.

**Booking vs Ticketing fərqləri:** Booking-də load sabitdir, inventory tarix aralığıdır, rəqabət azdır. Ticketing-də flash sale spike var (10:00), inventory konkret seat-dir (A12), rəqabət yüksək, fairness və anti-bot kritikdir.

## Tələblər (Requirements)

### Funksional (Functional)

- **Event satışı** — konsert, idman, teatr biletləri
- **Double booking qarşısı** — eyni seat iki nəfərə satıla bilməz
- **Flash sale dəstəyi** — 100k istifadəçi eyni anda Taylor Swift bileti üçün gözləyir
- **Fair queuing** — FIFO və ya randomized, "ən sürətli internet udur" yox
- **Payment integration** — Stripe, Adyen, local PSPs
- **Waitlist** — sold-out olanda sıra gözləmək
- **Refund** — cancel, partial refund
- **Secondary market** — ticket transfer, resale

### Non-functional (Non-Functional)

- **Consistency** — strong (iki nəfər eyni yerə sahib ola bilməz)
- **Availability** — peak zamanı 99.9%+
- **Fairness** — randomized queue, per-account ticket cap
- **Anti-bot** — CAPTCHA, rate limit, device fingerprint
- **Latency** — queue position update <1s, seat selection <200ms
- **Scalability** — 1M concurrent waiting users

## Capacity Estimation (Tutum Hesablaması)

**Ssenari:** Taylor Swift konserti, 50k yer, 100k–1M istifadəçi gözləyir.

- **Concurrent buyers (booking API-də):** 100k peak
- **Queue-da gözləyən:** 500k–1M
- **Queue token release rate:** 1000/s (booking API bu qədər tolere edir)
- **Seat hold TTL:** 5 dəqiqə
- **Payment completion rate:** ~70% (bəziləri timeout, card declined)
- **DB writes (booking commit):** ~200/s (1000/s × 0.3 churn + 0.7 final)
- **Redis ops:** 10k/s (seat locks, queue ops)

## Arxitektura (Architecture)

```
                      ┌──────────────────────────┐
                      │   CDN (static event info)│
                      │   seat maps, images      │
                      └──────────────────────────┘
                                 ▲
                                 │
┌─────────┐      ┌─────────────────────────────────┐
│ Client  │─────▶│   Virtual Waiting Room (Queue)  │
│(browser)│      │   - JWT queue token             │
└─────────┘      │   - Redis sorted set (position) │
     │           │   - SSE/WebSocket position push │
     │           └─────────────────────────────────┘
     │                       │ valid entry token
     │                       ▼
     │           ┌─────────────────────────────────┐
     │           │  API Gateway (rate limit, auth) │
     │           └─────────────────────────────────┘
     │                       │
     │        ┌──────────────┼──────────────┐
     │        ▼              ▼              ▼
     │  ┌──────────┐  ┌──────────┐  ┌──────────┐
     │  │ Catalog  │  │  Seat    │  │ Payment  │
     │  │ Service  │  │  Service │  │ Service  │
     │  └──────────┘  └──────────┘  └──────────┘
     │        │             │              │
     │        │             ▼              │
     │        │      ┌─────────────┐       │
     │        │      │ Redis Locks │       │
     │        │      │ seat:{id}   │       │
     │        │      │ TTL=5min    │       │
     │        │      └─────────────┘       │
     │        │             │              │
     │        ▼             ▼              ▼
     │  ┌──────────────────────────────────────┐
     │  │  PostgreSQL (events, seats, bookings)│
     │  │  UNIQUE(event_id, seat_id)           │
     │  └──────────────────────────────────────┘
     │                      │
     │                      ▼
     │           ┌──────────────────┐
     └──────────▶│ Horizon workers  │
                 │ email, PDF, SMS  │
                 └──────────────────┘
```

## Virtual Waiting Room (Virtual Gözləmə Otağı)

**Problem:** 10:00-da 1M istifadəçi eyni anda gəlir → origin servers ölür.

**Həll:** Client əvvəlcə **queue service**-ə düşür, booking API-yə deyil.

### Axın (Flow)

1. Client `/queue/join` endpoint-ə getir → queue service Redis sorted set-ə `ZADD queue:{event} {timestamp} {user_id}` edir
2. Service **signed JWT queue token** qaytarır: `{user_id, event_id, position, issued_at}`
3. Client WebSocket və ya SSE ilə öz pozisiyasını izləyir
4. Queue service saniyədə N (məs. 1000) token release edir — ZRANGE-dən ən yuxarıdakıları çıxarır, onlara **entry token** verir
5. Yalnız entry token sahibi `/booking/*` API-yə keçə bilir (middleware JWT yoxlayır)
6. Bu sayede booking API stabil 1000 RPS görür, 1M yox

### Queue Implementation

**Redis Sorted Set:**

```redis
ZADD queue:taylor-swift-2026 <timestamp> <user_id>
ZRANK queue:taylor-swift-2026 <user_id>   # position qaytarır
ZPOPMIN queue:taylor-swift-2026 1000      # top 1000 release et
```

**Atomic position assignment üçün Lua script:**

```lua
-- KEYS[1] = queue key, ARGV[1] = user_id, ARGV[2] = now
local exists = redis.call('ZSCORE', KEYS[1], ARGV[1])
if exists then return exists end
redis.call('ZADD', KEYS[1], ARGV[2], ARGV[1])
return ARGV[2]
```

Bu race-free-dir — eyni user ikiqat yer almır.

### Fairness (Ədalət)

- **Randomized start** — queue açılanda token-lərə random score verilir (pure timestamp deyil); "fast internet advantage" azalır
- **Per-account cap** — bir user max 4 bilet; check həm queue join-da, həm də booking commit-də
- **Same-card limit** — eyni credit card ilə N biletdən çox yox
- **Device fingerprint** — eyni brauzer/cihazdan çoxlu hesab → bot şübhəsi

## Seat Selection və Locking

### Axın (Flow)

1. User entry token ilə `/events/{id}/seats` çağırır → available seat map alır
2. User seat A12 seçir → `POST /holds {seat_id: A12}`
3. Backend Redis-də lock alır: `SET seat:event1:A12 user123 NX EX 300` (5 dəqiqə TTL)
4. Digər user-lər A12-ni "unavailable" kimi görür
5. User payment edir → success olanda DB-də booking yaradılır, lock silinir
6. Timeout (5 dəqiqə) olsa lock avtomatik expire olur, seat yenidən available

### Overselling Qarşısı (Preventing Overselling)

**İki səviyyə:**

**1. Redis lock** — UX üçün (user görsün ki, seat tutulub)

**2. DB-də hard constraint** — həqiqi source of truth:

```sql
-- Unique constraint
ALTER TABLE bookings ADD CONSTRAINT uq_event_seat
  UNIQUE (event_id, seat_id);

-- Ya da atomic update (general admission tipli events üçün)
UPDATE inventory
SET available = available - 1
WHERE event_id = ? AND available > 0
RETURNING available;
```

Əgər DB insert unique violation verərsə → "seat artıq satılıb" xətası; Redis lock-a baxmayaraq DB son söz deyir.

## Laravel Implementation

### Seat Lock (Cache::lock)

```php
use Illuminate\Support\Facades\Cache;

class SeatHoldService
{
    public function hold(int $eventId, string $seatId, int $userId): string
    {
        $lockKey = "seat:{$eventId}:{$seatId}";
        $lock = Cache::lock($lockKey, 300); // 5 min TTL

        if (! $lock->get()) {
            throw new SeatUnavailableException();
        }

        $holdId = (string) Str::uuid();
        Cache::put("hold:{$holdId}", [
            'event_id' => $eventId,
            'seat_id' => $seatId,
            'user_id' => $userId,
            'lock_owner' => $lock->owner(),
        ], 300);

        return $holdId;
    }

    public function release(string $holdId): void
    {
        $hold = Cache::get("hold:{$holdId}");
        if (! $hold) return;

        Cache::restoreLock("seat:{$hold['event_id']}:{$hold['seat_id']}",
            $hold['lock_owner'])->release();
        Cache::forget("hold:{$holdId}");
    }
}
```

### Queue Token Middleware

```php
class RequireEntryToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-Entry-Token');

        try {
            $payload = JWT::decode($token, config('app.queue_jwt_secret'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'invalid_token'], 403);
        }

        if ($payload->exp < now()->timestamp) {
            return response()->json(['error' => 'token_expired'], 403);
        }

        $request->attributes->set('queue_user', $payload->sub);
        return $next($request);
    }
}
```

### Idempotent Payment Webhook

```php
class StripeWebhookController
{
    public function handle(Request $request)
    {
        $eventId = $request->input('id'); // Stripe event id

        // Idempotency: əgər artıq process olub, skip
        if (PaymentEvent::where('provider_event_id', $eventId)->exists()) {
            return response('ok', 200);
        }

        DB::transaction(function () use ($request, $eventId) {
            PaymentEvent::create(['provider_event_id' => $eventId]);

            $holdId = $request->input('data.object.metadata.hold_id');
            $hold = Cache::get("hold:{$holdId}");

            Booking::create([
                'user_id' => $hold['user_id'],
                'event_id' => $hold['event_id'],
                'seat_id' => $hold['seat_id'],
                'payment_id' => $request->input('data.object.id'),
                'status' => 'confirmed',
            ]);

            // Async tasks
            SendTicketEmailJob::dispatch($hold['user_id'], $holdId);
            GenerateTicketPdfJob::dispatch($holdId);
        });

        return response('ok', 200);
    }
}
```

Horizon bu job-ları process edir (email, PDF generation sinxron olmur).

## Data Model

```sql
CREATE TABLE events (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255),
    venue_id BIGINT,
    starts_at TIMESTAMPTZ,
    sale_opens_at TIMESTAMPTZ
);

CREATE TABLE seats (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT REFERENCES events(id),
    section VARCHAR(50),
    row VARCHAR(10),
    number VARCHAR(10),
    price_cents INT,
    status VARCHAR(20) DEFAULT 'available' -- available|held|sold
);

CREATE TABLE holds (
    id UUID PRIMARY KEY,
    seat_id BIGINT REFERENCES seats(id),
    user_id BIGINT,
    expires_at TIMESTAMPTZ
);

CREATE TABLE bookings (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT,
    event_id BIGINT,
    seat_id BIGINT,
    payment_id VARCHAR(100),
    state VARCHAR(20), -- pending|confirmed|refunded
    created_at TIMESTAMPTZ
);

CREATE UNIQUE INDEX uq_event_seat_confirmed
    ON bookings(event_id, seat_id)
    WHERE state = 'confirmed';
```

Partial unique index — yalnız `confirmed` state-də uniqueness-i məcbur edir, refund olmuş yerlər yenidən satıla bilir.

## Caching və Bot Prevention

**Cache:** Event catalog, venue map, seat coordinates CDN-də (immutable). Event details Redis-də 1 saat TTL. Dynamic olan yalnız **inventory state**-dir.

**Bot Prevention:**
- CAPTCHA (hCaptcha, reCAPTCHA v3) queue join və seat select
- Rate limit per-IP (10 req/min) və per-account (3 queue join/gün)
- Device fingerprint — çoxlu hesab eyni cihazdan → flag
- Queue token JWT nonce — replay attack qarşısı
- Honeypot fields + behavioral analysis (mouse/typing pattern)

## Refund və Transfer

### Refund Flow

```php
class RefundService
{
    public function refund(Booking $booking, string $reason): void
    {
        DB::transaction(function () use ($booking) {
            // Idempotency check
            if ($booking->state === 'refunded') return;

            // Stripe refund — idempotency key booking_id
            $this->stripe->refunds->create([
                'payment_intent' => $booking->payment_id,
            ], ['idempotency_key' => "refund_{$booking->id}"]);

            $booking->update(['state' => 'refunded']);

            // Seat yenidən satıla bilir (partial unique index sayəsində)
            Seat::where('id', $booking->seat_id)->update(['status' => 'available']);
        });
    }
}
```

### Ticket Transfer

User A → B-yə ötürür, backend yeni QR generate edir (köhnəsi invalidate). Escrow: pul B-dən alınır, A-ya payout event-dən sonra (chargeback qarşısı).

## Trade-offs (Kompromislər)

### Optimistic vs Pessimistic Locking

- **Pessimistic (Redis lock + DB SELECT FOR UPDATE):** strong guarantee, lakin contention-da slow
- **Optimistic (version column, retry):** fast happy path, lakin flash sale-də çox retry → user frustration

Ticketing-də **pessimistic lock** + **short TTL (5 min)** standartdır.

### Short vs Long TTL

| TTL | Plus | Minus |
|-----|------|-------|
| Qısa (2 min) | Seat churn çox, sıra tez hərəkət edir | User payment bitirməyə bilmir |
| Uzun (15 min) | Rahat payment | Hoarding, seat az görünür |

5 dəqiqə — industry standard tradeoff.

### Queue FIFO vs Randomized

FIFO sadədir amma "fast network" üstünlüyü verir. Randomized ədalətlidir amma user şikayət edir. Ticketmaster hybrid: ilk 30 saniyə randomized, sonra FIFO.

## Interview Q&A

**Q1: 100k nəfər eyni anda Buy-a basır, sistem necə sağ qalır?**
A: Virtual waiting room — queue service istifadəçiləri Redis sorted set-də saxlayır, booking API-yə saniyədə ancaq 1000 entry token release edir. Booking service stabil load görür, 100k yox. Queue service özü horizontal scalable (stateless + Redis).

**Q2: Eyni seat-i iki nəfər eyni anda seçərsə nə olur?**
A: İki səviyyə defense. Birinci, Redis `SET NX EX` atomic lock — yalnız bir nəfər alır, digər rədd olunur. İkinci, DB-də `UNIQUE(event_id, seat_id) WHERE state='confirmed'` — Redis-dən keçsə belə DB son sözü deyir. Unique violation olsa user-ə "seat just taken" deyirik.

**Q3: Fair queuing necə qururuq?**
A: Randomized starting position (pure timestamp yox) — networking üstünlüyünü azaldır. Per-account və per-card cap ticket hoarding-i məhdudlaşdırır. Bot detection (CAPTCHA, fingerprint, rate limit) — real user-lərə yer qoyur. Queue token JWT-lə imzalanır ki, replay edilə bilməsin.

**Q4: User seat seçib 5 dəqiqə gözləyir, sonra browser bağlayır — nə olur?**
A: Redis TTL (EX 300) avtomatik expire edir, lock özü-özünə düşür. Background worker-ə ehtiyac yoxdur. Seat yenidən available olur və digər queue user-lərinə görünür. DB-də booking heç yaradılmadığı üçün temizlik asandır.

**Q5: Payment gateway timeout versə, user pulunu verib biletsiz qalarsa?**
A: Idempotent webhook handler. Stripe webhook (`payment_intent.succeeded`) bizə gəlir — booking-i confirm edir. Əgər webhook geciksə, user client-də "pending" görür. Double webhook gəlsə, `PaymentEvent` cədvəlində idempotency key qoruyur, ikinci process olmur. Heç webhook gəlməsə, cron background reconciliation işləyir — Stripe API-dən payment status soruşur.

**Q6: Scalping (biletləri alıb baha qiymətə satmaq) qarşısı necə?**
A: Çoxqat approach — per-account cap (max 4), same-card limit, device fingerprint, CAPTCHA. Name-printed tickets + ID check konsertdə. Rəsmi resale platforma (escrow, price cap) scalper-lərə qanuni kanal verir. ML model şübhəli pattern detect edir.

**Q7: General admission (yeri olmayan) event üçün fərq nədir?**
A: Seat-by-seat lock əvəzinə atomic counter istifadə edirik: `UPDATE inventory SET available = available - 1 WHERE event_id = ? AND available > 0 RETURNING available`. Bu row-level lock ilə DB atomic-dir, overselling qeyri-mümkün. Redis-də `DECR inventory:{event}` + threshold check ilə də olar (performans üçün), lakin reconciliation lazım.

**Q8: 1M concurrent queue gözləyir, Redis sorted set saxlaya bilər?**
A: Bəli — 1M entry ~100MB memory (hər entry ~100 byte). Real boğaz position update push-dur. WebSocket əvəzinə SSE with polling (5 saniyədə bir) — hər user 5s-də poll etsə 200k RPS, 10 node arxasında HAProxy ilə həll olur. Hər dəyişmədə push şərt deyil, approximate position kifayətdir.

## Best Practices

1. **İki səviyyə lock** — Redis (fast, UX) + DB unique constraint (correctness)
2. **Idempotency key hər yerdə** — payment webhook, refund, booking
3. **Short TTL + extendable** — 5 min default, aktiv user üçün uzat
4. **Queue-first architecture** — booking API-yə queue-sız girilməsin
5. **Webhook-ları async işlə** — Horizon/Sidekiq, sinxron 500 verməsin
6. **Partial unique index** — refund-dan sonra seat reuse mümkün olsun
7. **CDN-dən maksimum istifadə** — seat maps, event details immutable
8. **Graceful degradation** — queue service down olsa throttled direct mode
9. **Observability** — queue length, release rate, hold success ratio
10. **Load test annual** — real flash sale-dən əvvəl weak link-i tap
