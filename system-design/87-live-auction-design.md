# Live Auction System Design (eBay-like)

Real-time bidding sistemi — istifadəçilər məhsullara canlı təklif verir, qalib son təklifə görə müəyyən edilir. eBay, Sotheby's, Catawiki kimi platformalar.

---

## Tələblər (Requirements)

### Funksional (Functional)

- İstifadəçi məhsula **bid** (təklif) qoya bilər
- Bütün izləyicilər **real-time** qiymət yeniləmələri görür
- **Proxy bidding** — istifadəçi max məbləğ verir, sistem avtomatik artırır
- Hərracı `end_time` vaxtında avtomatik bağlanır
- Qalib **consistent** (eyni nəticə hamı üçün)
- Fraud prevention (shill bidding, collusion)

### Qeyri-funksional (Non-functional)

- **Latency:** bid acknowledge <200ms
- **Availability:** 99.95% (hərrac itirilsə, pul itir)
- **Consistency:** strong (qalib dəqiq müəyyən olmalı)
- **Durability:** heç bir bid itməməlidir (append-only log)

---

## Miqyas (Scale)

| Metric | Target |
|---|---|
| Aktiv hərrac | 10M |
| Peak concurrent bidder | 100k (celebrity item) |
| Bid rate (popular auction) | 10k bids/sec |
| WebSocket viewer | 500k concurrent |
| Bid ack latency | p99 <200ms |

---

## Hərrac tipləri (Auction Types)

### 1. English auction (climb-up)

- Qiymət **qalxır**, ən yüksək təklif qalib olur
- Ən məşhur (eBay)

### 2. Dutch auction (clock-down)

- Qiymət **düşür**, ilk qəbul edən qalib
- Gül hərracları (Aalsmeer)

### 3. Sealed-bid

- Gizli zərfdə təklif; ən yüksək qalib
- **First-price** — öz təklifini ödəyir
- **Second-price (Vickrey)** — ikinci yüksək təklifi ödəyir; truthful bidding

### 4. Live (hybrid)

- Canlı auctioneer + online bidder (Sotheby's)
- Lot-by-lot, aparıcı hammer drop edir

---

## Proxy bidding (Avtomatik təklif)

İstifadəçi **max amount** verir. Sistem yalnız **minimum increment** qədər artırır, digərlərinə max gizli qalır.

```
User A max: $100 (gizli)
User B max: $80  (gizli)

current_price = $50
B bid $60 → A otomatik $65 (B+increment)
B bid $75 → A otomatik $80 (B+increment)
B bid $82 → B > A max yoxdur, A dayanır, B=$82 highest
```

Max məbləği heç kəsə göstərmə — rəqiblər yalnız current_price görür.

---

## Data model

```sql
CREATE TABLE auctions (
    id              BIGINT PRIMARY KEY,
    seller_id       BIGINT NOT NULL,
    item_id         BIGINT NOT NULL,
    start_time      TIMESTAMP NOT NULL,
    end_time        TIMESTAMP NOT NULL,
    start_price     DECIMAL(12,2) NOT NULL,
    current_price   DECIMAL(12,2) NOT NULL,
    min_increment   DECIMAL(12,2) NOT NULL,
    highest_bidder_id BIGINT NULL,
    status          ENUM('scheduled','active','closed','cancelled'),
    version         INT NOT NULL DEFAULT 0,
    INDEX (end_time, status)
);

CREATE TABLE bids (
    id              BIGINT PRIMARY KEY,
    auction_id      BIGINT NOT NULL,
    user_id         BIGINT NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    max_amount      DECIMAL(12,2) NULL,
    client_bid_id   CHAR(36) NOT NULL,
    created_at      TIMESTAMP(6) NOT NULL,
    UNIQUE KEY (auction_id, client_bid_id),
    INDEX (auction_id, created_at)
);
```

`client_bid_id` — **idempotency key** (UUID). Eyni bid iki dəfə qəbul olunmaz.

---

## Arxitektura (Architecture)

```
  [Browser/Mobile]
        |
        | HTTPS  WebSocket
        v
  [CDN / Edge]
        |
        v
  [API Gateway] --- [Auth / Rate Limit]
        |
   +----+----+-----------+
   |         |           |
[Bid API] [Read API] [Viewer WS]
   |         |           ^
   v         v           |
[Bid Worker (per-auction shard)]
   |         |           |
   v         v           |
 [MySQL]  [Redis]---pub/sub
   |         |
   +---> [Kafka: bid-events]
             |
        +----+----+
        |         |
   [Scheduler] [Fraud Engine]
```

---

## Concurrency control

Eyni hərraca eyni anda 1000 bid gəlir. Mutlaq serialize olmalıdır.

### Variant 1: Optimistic locking (MySQL)

```php
public function placeBid(int $auctionId, int $userId, float $amount, string $clientBidId): Bid
{
    return DB::transaction(function () use ($auctionId, $userId, $amount, $clientBidId) {
        $a = DB::table('auctions')->where('id', $auctionId)->first();

        if ($a->status !== 'active') throw new AuctionClosedException();
        if (now()->gte($a->end_time)) throw new AuctionEndedException();
        if ($amount < $a->current_price + $a->min_increment) {
            throw new BidTooLowException();
        }

        $affected = DB::table('auctions')
            ->where('id', $auctionId)
            ->where('version', $a->version)
            ->update([
                'current_price' => $amount,
                'highest_bidder_id' => $userId,
                'version' => $a->version + 1,
            ]);

        if ($affected === 0) {
            throw new ConcurrentBidException(); // retry
        }

        return Bid::create([
            'auction_id'    => $auctionId,
            'user_id'       => $userId,
            'amount'        => $amount,
            'client_bid_id' => $clientBidId,
        ]);
    });
}
```

Client retry edir. Yüksək contention-da throughput aşağı.

### Variant 2: Pessimistic `SELECT FOR UPDATE`

```php
$a = DB::table('auctions')->where('id', $auctionId)->lockForUpdate()->first();
// serialize per auction; həmişə işləyir amma slow
```

### Variant 3: Redis atomic (fast path)

Lua script atomik yoxlama + update:

```lua
-- KEYS[1] = auction:{id}:price
-- ARGV[1] = bid_amount
-- ARGV[2] = min_increment
local current = tonumber(redis.call('GET', KEYS[1]) or 0)
local inc = tonumber(ARGV[2])
local bid = tonumber(ARGV[1])
if bid < current + inc then return {0, current} end
redis.call('SET', KEYS[1], bid)
return {1, bid}
```

Sonra asinxron MySQL-a sync. Hot path üçün optimal.

---

## Per-auction serialization

**Partition by `auction_id`** — hər hərrac bir worker-ə gedir. Kafka/Redis stream consumer per partition → linearized sequence.

```
bid → Kafka topic "bids" partition key=auction_id
       ↓
    Worker N (owns auctions 0..99)
       ↓
    Process sequentially → DB write → broadcast
```

10k bid/sec bir auction-a gəlsə, tək worker işləyir (CPU-bound batch), back-pressure client-ə qayıdır.

---

## Bid validation

1. `amount > current_price + min_increment`
2. `user.credit_limit >= amount` (və ya payment method verified)
3. `now < end_time AND status = active`
4. **Rate limit:** user max 60 bids/min
5. KYC verified user (yüksək məbləğ üçün)
6. Seller öz məhsuluna bid edə bilməz (shill prevention)

---

## Real-time broadcast

Accepted bid → fan-out:

```
Worker → Redis pub/sub "auction:{id}:updates"
                ↓
        WebSocket server (Reverb/Soketi) subscribes
                ↓
        Push to all connected viewers
```

Laravel event:

```php
class BidAccepted implements ShouldBroadcast
{
    public function __construct(public Bid $bid, public Auction $auction) {}

    public function broadcastOn(): Channel
    {
        return new Channel("auction.{$this->auction->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'current_price' => $this->auction->current_price,
            'highest_bidder' => $this->bid->user->masked_name,
            'bid_count' => $this->auction->bids_count,
            'server_time' => now()->toIso8601String(),
        ];
    }
}
```

Frontend (Pusher/Echo):

```js
Echo.channel(`auction.${id}`).listen('BidAccepted', (e) => {
    ui.updatePrice(e.current_price);
});
```

---

## Anti-sniping (Son saniyə bid)

Problem: bidders son 3 saniyədə bid atıb digərlərinə cavab verməyə vaxt verməməyə çalışır.

**Soft-close:** Əgər son N dəqiqədə bid gəlibsə, `end_time` N dəqiqə uzadılır.

```php
if ($auction->end_time->diffInSeconds(now()) < 120) {
    $auction->end_time = now()->addMinutes(2);
    $auction->save();
    event(new AuctionExtended($auction));
}
```

eBay auto-extend etmir (original time hard), amma əksər platformalar edir (Catawiki, Heritage).

---

## Auction closing

### Dedicated scheduler

```php
// app/Console/Commands/CloseAuctions.php
public function handle(): void
{
    Auction::where('status', 'active')
        ->where('end_time', '<=', now())
        ->chunkById(500, function ($batch) {
            foreach ($batch as $auction) {
                CloseAuctionJob::dispatch($auction->id);
            }
        });
}
```

`schedule:run` hər dəqiqə. Job-da atomic transition:

```php
DB::transaction(function () use ($id) {
    $a = Auction::lockForUpdate()->find($id);
    if ($a->status !== 'active' || $a->end_time > now()) return;
    $a->status = 'closed';
    $a->save();
    if ($a->highest_bidder_id) {
        event(new AuctionWon($a));
        PaymentHoldJob::dispatch($a);
    }
});
```

**Reconciliation job** — 5 dəqiqədə bir `status=active AND end_time < now()-5min` axtarır (missed closures).

---

## Payment / Escrow

1. Hərrac bağlanır → qalib müəyyən
2. Payment method-da **hold** (authorize, capture sonra)
3. 24 saat pul yatırmasa → hold cancel, **second bidder offer** (time-boxed 12 saat)
4. Bad buyer strike sistemi

---

## Fraud patterns

| Pattern | Detection |
|---|---|
| **Shill bidding** (seller own item) | Same IP/device/account graph; velocity anomaly |
| **Bid retraction abuse** | Track retract count; rate limit |
| **Chargeback fraud** | Payment score, history |
| **Colluding accounts** | Network analysis, shared payment method |
| **Bot bidding** | Rate limit, CAPTCHA on threshold |

Fraud engine asinxron (Kafka consumer) — bidləri izləyir, şübhəli olanları flag edir.

---

## Hot auction problem

Celebrity NFT → 100k concurrent viewer. 

- **WebSocket horizontal scale** — sticky session, ayrı cluster
- **Delta broadcast** — full state yox, incremental update
- **Viewer throttling** — hər 500ms bir snapshot push (10k update/sec UI-a lazım deyil)
- **Pre-cache** — hərraca start-dan əvvəl warm-up CDN, edge

---

## Bid history

**Append-only log** (Kafka topic `bid-events`). Event sourcing:

- Replay-dan state reconstruct etmək olar
- Audit / regulatory compliance
- ML üçün dataset

---

## Leaderboard (Redis)

```
ZADD auction:{id}:bidders {amount} {user_id}
ZREVRANGE auction:{id}:bidders 0 9  # top 10
```

Real-time top bidder list O(log N).

---

## Interview Q&A

### Q1: Eyni millisecond-da iki eyni məbləğdə bid gəlsə?

Tie-break: **first-in-time wins**. DB-də `created_at TIMESTAMP(6)` (microsecond). Serialization ilə yalnız biri qəbul olunur — ikinci `BidTooLow` alır (çünki artıq current_price = o amount-dur, yeni bid `> current + increment` olmalıdır).

### Q2: Qalibi necə consistent müəyyən edirsən?

Server `end_time` (UTC, NTP-sync) **authoritative**. Client time ignore. Atomic close transaction — `status: active → closed` tək yerdən, linearizability təmin olunur. Split-brain olmaması üçün leader-based scheduler (Redlock / DB advisory lock).

### Q3: 10k bid/sec tək auction üçün necə?

Redis Lua script fast path → accepted/rejected O(1). Async MySQL persist. Per-auction Kafka partition ilə serialize. Worker batch insert hər 100ms (10k bid = 100 batch insert/sec). WebSocket broadcast throttle (500ms snapshot).

### Q4: Proxy bidding-i necə implement edirsən?

Hər bid qəbul olanda, `max_amount IS NOT NULL` bütün aktiv max bidders arasında ən yüksək olanı tap. Əgər current_price < onun max-ı → avtomatik `current_price + increment` bid qoy (amount o user-in max-ından keçməyəcək). Bir neçə iterasiya ola bilər — "bid war" resolve olunur tək request daxilində.

### Q5: Network fail olarsa bid retry?

**Idempotency:** client UUID generate edir (`client_bid_id`), server DB unique key-lə eyni bidi iki dəfə qəbul etmir. Client retry safe. HTTP 409 "duplicate" qaytaranda existing bid-i qaytar.

### Q6: Seller öz məhsuluna bid etməsin necə?

Validation level-də `bidder_id != seller_id`. Amma shill bidding başqa account-dan olur — graph analysis (IP, device fingerprint, payment method, social graph) lazımdır. Fraud engine async flag edir → manual review.

### Q7: Hərrac zamanı seller cancel edə bilər?

Business rule. Əksər platformalar: bid varsa **cancel olmaz** (və ya yüksək penalty). Moderator force-close edə bilər — `status = cancelled`, bütün bid-lər refund, WebSocket notify.

### Q8: Clock skew / DST problemi?

Bütün server UTC işləyir, NTP sync məcburi (max drift 50ms). Client time-a heç vaxt güvənmə — server timestamp authoritative. End-time yaxınlaşanda client-ə server_time hər tick-də göndər ki, countdown düzgün göstərilsin.

---

## Best Practices

- **Idempotency** hər bid endpoint-də (`client_bid_id`) — double-submit problem həll olur
- **Server time authoritative** — client countdown display only
- **Per-auction partition** — concurrency sərhədli, hot auction isolate olunur
- **Append-only bid log** — audit, replay, event sourcing
- **Atomic close transition** — `SELECT FOR UPDATE` + `status = closed` tək transaction
- **Reconciliation job** — missed closure-lar üçün safety net
- **Soft-close (anti-sniping)** — fair bidding üçün son dəqiqə extend
- **Rate limiting** — 60 bids/min per user; bot prevention
- **Payment hold before winner announce** — no-pay riski azaldır
- **Masked bidder name** — `j***@ex.com` privacy üçün
- **Observability:** p99 bid ack latency <200ms, accepted/rejected ratio, WS disconnect rate, close job lag
- **Graceful degradation** — Redis down olarsa MySQL-a fallback (slow but correct)
- **Disaster recovery** — bid log multi-region replicated (financial data)
- **Chaos testing** — kill bid worker mid-auction, verify no lost bids

---

## Əlaqəli mövzular

- [Flash Sale Design](86-flash-sale-design.md) — hot product, similar concurrency problem
- [Payment Systems](54-payment-systems.md) — escrow, hold, capture
- [WebSocket Scale](57-websocket-scale.md) — real-time broadcast
- [Event Sourcing](34-event-sourcing.md) — bid log replay
- [Distributed Locks](29-distributed-locks.md) — per-auction serialization
