# Stock Trading System Design

## Nədir? (What is it?)

**Stock Trading System** — investorların real-time olaraq sərvət qiymətli kağızlarını (səhmlər, istiqrazlar, kripto) alıb-satdığı sistem. Nasdaq, NYSE, NSE, Binance kimi borsalar belə işləyir.

**Əsas tələblər:**
- **Ultra-low latency** — order matching <10 mikrosaniyə
- **Super-high throughput** — saniyədə milyonlarla sifariş
- **Strict ordering** — price-time priority
- **Fault tolerance** — hər əməliyyat qeyd edilmiş olmalıdır
- **Regulatory compliance** — audit trail, MiFID II, SEC

## Əsas Konseptlər (Key Concepts)

### Order Types (Sifariş Növləri)

1. **Market Order** — cari qiymətdə dərhal al/sat
2. **Limit Order** — müəyyən qiymət və ya daha yaxşı
3. **Stop Order** — qiymət X-ə çatsa market order-ə çevrilir
4. **Stop-Limit Order** — stop + limit combo
5. **Fill-or-Kill (FOK)** — hamısı dolmalıdır yoxsa cancel
6. **Immediate-or-Cancel (IOC)** — dərhal icra olunan hissə, qalan cancel
7. **Good-Till-Cancelled (GTC)** — manual cancel edilənədək aktiv
8. **Good-Till-Date (GTD)** — konkret tarixədək

### Order Book (Sifariş Kitabı)

Hər symbol (AAPL, MSFT) üçün iki tərəfli sort olunmuş siyahı.

```
AAPL Order Book:

BUY SIDE (Bids)              SELL SIDE (Asks)
Price  | Qty | Orders        Price  | Qty | Orders
150.10 | 500 | 3             150.15 | 200 | 2
150.09 | 300 | 1             150.16 | 700 | 5
150.08 | 800 | 4             150.17 | 400 | 3
150.05 | 1000| 7             150.20 | 1500| 10

Best Bid: 150.10 (ən yüksək al)
Best Ask: 150.15 (ən aşağı sat)
Spread: 0.05
Mid Price: 150.125
```

### Price-Time Priority

Match edilmə qaydaları:
1. **Price priority** — yaxşı qiymət əvvəl icra olunur
   - Buy: yüksək qiymət əvvəldir
   - Sell: aşağı qiymət əvvəldir
2. **Time priority** — eyni qiymətdə əvvəl gələn order əvvəl icra olunur (FIFO)

```
AAPL-də 150.10-da 3 bids (eyni qiymət):
- Order A: 100 shares, 10:00:00.001
- Order B: 200 shares, 10:00:00.005
- Order C: 150 shares, 10:00:00.010

Market sell 300 shares gəldi:
→ 100 shares Order A ilə match
→ 200 shares Order B ilə match
```

### Matching Engine

Order-ları FIFO qaydası ilə match edən core komponent.

```
New Order gəlir:
  BUY 100 AAPL @ 150.15

1. Sell side-da 150.15 və ya daha aşağı axtar
2. Ən aşağı qiymət (150.15) → match
3. 200 qty var, 100 ilə match → 100 qty qaldı
4. Trade yaradılır: BUY @ 150.15, 100 shares
5. İki tərəfə bildir (fill notification)
6. Market data-ya yayımla
```

**Matching Engine tələbləri:**
- **Deterministic** — eyni input eyni output
- **Fast** — FPGA, kernel bypass (DPDK, Solarflare)
- **Single-threaded per symbol** — race condition qarşısı
- **In-memory** — disk çox yavaşdır
- **Log-based** — WAL (write-ahead log) durability üçün

### FIX Protocol

**Financial Information eXchange** — maliyyə industry-sinin messaging protokolu.

```
8=FIX.4.4|9=76|35=D|49=CLIENT|56=BROKER|34=1|52=20240416-10:00:00|11=ORDER123|55=AAPL|54=1|38=100|40=2|44=150.15|10=123|

35=D → New Order Single
55=AAPL → symbol
54=1 → buy
38=100 → quantity
40=2 → limit order
44=150.15 → price
```

- **Session Layer** — logon, heartbeat, seq number
- **Application Layer** — orders, executions, market data
- **TCP əsaslı**, **plain text**, **low-latency variants**: FAST, SBE

### Market Data Distribution

- **Level 1** — best bid/ask, last trade
- **Level 2** — order book depth (hər qiymət səviyyəsi)
- **Level 3** — hər order, market maker ID
- **Multicast UDP** — bir mesaj milyonlarla receiver-ə
- **Protocols**: NYSE XDP, Nasdaq ITCH, CME MDP

### Latency-də Optimallaşdırmalar

- **Kernel bypass** — DPDK, Solarflare, Onload
- **FPGA** — matching engine donanımda
- **Lock-free data structures**
- **Co-location** — trader-in server-i borsa yanında
- **Microwave/laser links** — fiberdan 30% sürətli
- **Memory-mapped files** — zero-copy I/O
- **Warm caches** — JIT, CPU affinity
- **C++, Rust, Java (low-GC)**

## Arxitektura

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                              │
│  Web Trader | Mobile App | Algo Traders | Market Makers     │
└────────────────────┬────────────────────────────────────────┘
                     │ FIX / REST / WebSocket
                     ▼
┌─────────────────────────────────────────────────────────────┐
│              GATEWAY / ORDER ROUTER                          │
│  - Authentication, Rate Limiting                             │
│  - Pre-trade risk checks                                     │
│  - Routing to correct matching engine                        │
└────────┬────────────────────────────────┬───────────────────┘
         │                                 │
         ▼                                 ▼
┌─────────────────────┐          ┌─────────────────────┐
│ Matching Engine     │          │ Matching Engine     │
│ (AAPL, TSLA, ...)   │          │ (MSFT, GOOG, ...)   │
│ - Order Book        │          │ - Order Book        │
│ - In-memory         │          │ - In-memory         │
│ - Single-thread     │          │ - Single-thread     │
└─────────┬───────────┘          └─────────┬───────────┘
          │                                 │
          │ Trades, Market Data             │
          ▼                                 ▼
┌─────────────────────────────────────────────────────────────┐
│         MARKET DATA DISTRIBUTION (Multicast)                 │
└────────┬────────────────────────────────┬───────────────────┘
         │                                 │
         ▼                                 ▼
┌─────────────────┐                ┌─────────────────┐
│ Trade Reporter  │                │ Subscribers     │
│ - Settlement    │                │ - Traders       │
│ - Clearing      │                │ - Data vendors  │
└─────────────────┘                └─────────────────┘

Persistence: Kafka/Aeron → Cassandra/ClickHouse (trade history)
Audit: WAL, immutable log, replay capability
```

## PHP/Laravel ilə Tətbiq

> Qeyd: PHP ultra-low latency matching engine üçün uyğun DEYİL. Production-da C++/Rust/Java istifadə olunur. Aşağıdakı kodlar konsepti göstərmək üçündür, retail broker backend-i üçün PHP məqbuldur.

### Order Book Data Structure

```php
<?php

namespace App\Trading;

class OrderBook
{
    // Price → OrderQueue
    // SplPriorityQueue istifadə etmək olar, sadə üçün sorted array
    private array $bids = []; // price => [orders]
    private array $asks = []; // price => [orders]

    public function __construct(public readonly string $symbol) {}

    public function addOrder(Order $order): array
    {
        $trades = $this->match($order);

        if ($order->remainingQty > 0 && $order->type === 'limit') {
            $this->enqueue($order);
        }

        return $trades;
    }

    private function match(Order $incoming): array
    {
        $trades = [];
        $book = $incoming->side === 'buy' ? 'asks' : 'bids';
        $prices = array_keys($this->$book);

        // Buy → ascending asks, Sell → descending bids
        $incoming->side === 'buy' ? sort($prices) : rsort($prices);

        foreach ($prices as $price) {
            // Limit price check
            if ($incoming->type === 'limit') {
                if ($incoming->side === 'buy' && $price > $incoming->price) break;
                if ($incoming->side === 'sell' && $price < $incoming->price) break;
            }

            while (!empty($this->{$book}[$price]) && $incoming->remainingQty > 0) {
                $resting = $this->{$book}[$price][0];
                $qty = min($incoming->remainingQty, $resting->remainingQty);

                $trades[] = new Trade(
                    symbol: $this->symbol,
                    buyOrderId: $incoming->side === 'buy' ? $incoming->id : $resting->id,
                    sellOrderId: $incoming->side === 'sell' ? $incoming->id : $resting->id,
                    price: $price,
                    quantity: $qty,
                    timestamp: hrtime(true),
                );

                $incoming->remainingQty -= $qty;
                $resting->remainingQty -= $qty;

                if ($resting->remainingQty === 0) {
                    array_shift($this->{$book}[$price]);
                    if (empty($this->{$book}[$price])) unset($this->{$book}[$price]);
                }
            }

            if ($incoming->remainingQty === 0) break;
        }

        return $trades;
    }

    private function enqueue(Order $order): void
    {
        $book = $order->side === 'buy' ? 'bids' : 'asks';
        $this->{$book}[$order->price][] = $order;
    }

    public function cancelOrder(string $orderId): bool
    {
        foreach (['bids', 'asks'] as $book) {
            foreach ($this->$book as $price => $orders) {
                foreach ($orders as $idx => $o) {
                    if ($o->id === $orderId) {
                        array_splice($this->{$book}[$price], $idx, 1);
                        if (empty($this->{$book}[$price])) unset($this->{$book}[$price]);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function bestBid(): ?float
    {
        if (empty($this->bids)) return null;
        return max(array_keys($this->bids));
    }

    public function bestAsk(): ?float
    {
        if (empty($this->asks)) return null;
        return min(array_keys($this->asks));
    }

    public function snapshot(int $depth = 10): array
    {
        $bidPrices = array_keys($this->bids);
        rsort($bidPrices);
        $askPrices = array_keys($this->asks);
        sort($askPrices);

        return [
            'symbol' => $this->symbol,
            'bids' => array_slice(array_map(fn($p) => [
                'price' => $p,
                'quantity' => array_sum(array_map(fn($o) => $o->remainingQty, $this->bids[$p])),
            ], $bidPrices), 0, $depth),
            'asks' => array_slice(array_map(fn($p) => [
                'price' => $p,
                'quantity' => array_sum(array_map(fn($o) => $o->remainingQty, $this->asks[$p])),
            ], $askPrices), 0, $depth),
            'timestamp' => hrtime(true),
        ];
    }
}
```

### Order & Trade Models

```php
class Order
{
    public int $remainingQty;

    public function __construct(
        public readonly string $id,
        public readonly int $userId,
        public readonly string $symbol,
        public readonly string $side,        // buy | sell
        public readonly string $type,        // limit | market | stop
        public readonly int $quantity,
        public readonly ?float $price = null,
        public readonly ?float $stopPrice = null,
        public readonly string $tif = 'GTC', // GTC, IOC, FOK, GTD
        public readonly int $timestamp = 0,
    ) {
        $this->remainingQty = $quantity;
    }
}

class Trade
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $buyOrderId,
        public readonly string $sellOrderId,
        public readonly float $price,
        public readonly int $quantity,
        public readonly int $timestamp,
    ) {}
}
```

### Pre-trade Risk Checks

```php
class RiskService
{
    public function validate(Order $order, User $user): void
    {
        // 1. Funds check
        if ($order->side === 'buy') {
            $required = $order->quantity * ($order->price ?? $this->markPrice($order->symbol));
            if ($user->balance < $required) {
                throw new InsufficientFundsException();
            }
        }

        // 2. Position limit
        $position = $user->positions()->where('symbol', $order->symbol)->value('quantity') ?? 0;
        if ($order->side === 'sell' && $position < $order->quantity) {
            throw new InsufficientSharesException();
        }

        // 3. Max order size
        if ($order->quantity > config('trading.max_order_size', 1_000_000)) {
            throw new OrderTooLargeException();
        }

        // 4. Fat-finger check — qiymət çox uzaqdırsa
        $mark = $this->markPrice($order->symbol);
        if ($order->type === 'limit' && abs($order->price - $mark) / $mark > 0.10) {
            throw new FatFingerException();
        }

        // 5. Rate limiting
        $recent = Redis::incr("orders:{$user->id}:count");
        Redis::expire("orders:{$user->id}:count", 1);
        if ($recent > 100) {
            throw new RateLimitException();
        }
    }

    private function markPrice(string $symbol): float
    {
        return (float) Redis::get("market:price:{$symbol}");
    }
}
```

### Matching Engine Service (Symbol-partitioned)

```php
class MatchingEngineService
{
    private array $books = [];

    public function submitOrder(Order $order): array
    {
        $book = $this->getBook($order->symbol);

        // Audit log
        $this->writeToWAL($order);

        $trades = $book->addOrder($order);

        foreach ($trades as $trade) {
            $this->writeToWAL($trade);
            $this->broadcastTrade($trade);
            $this->updateMarketData($order->symbol, $trade);
            dispatch(new SettleTradeJob($trade));
        }

        // Order book snapshot broadcast
        broadcast(new \App\Events\OrderBookUpdated($book->snapshot(10)));

        return $trades;
    }

    private function getBook(string $symbol): OrderBook
    {
        return $this->books[$symbol] ??= new OrderBook($symbol);
    }

    private function writeToWAL(Order|Trade $entity): void
    {
        // Append-only log — disaster recovery üçün
        file_put_contents(
            storage_path('wal/' . date('Y-m-d') . '.log'),
            json_encode($entity) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function broadcastTrade(Trade $trade): void
    {
        broadcast(new \App\Events\TradeExecuted($trade));
    }

    private function updateMarketData(string $symbol, Trade $trade): void
    {
        Redis::set("market:price:{$symbol}", $trade->price);
        Redis::lpush("market:trades:{$symbol}", json_encode([
            'price' => $trade->price,
            'qty' => $trade->quantity,
            'ts' => $trade->timestamp,
        ]));
        Redis::ltrim("market:trades:{$symbol}", 0, 999);
    }
}
```

### REST API Controller

```php
class OrderController extends Controller
{
    public function __construct(
        private RiskService $risk,
        private MatchingEngineService $engine,
    ) {}

    public function place(PlaceOrderRequest $request)
    {
        $user = $request->user();

        $order = new Order(
            id: (string) Str::uuid(),
            userId: $user->id,
            symbol: strtoupper($request->symbol),
            side: $request->side,
            type: $request->type,
            quantity: $request->quantity,
            price: $request->price,
            timestamp: hrtime(true),
        );

        $this->risk->validate($order, $user);

        $trades = $this->engine->submitOrder($order);

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->remainingQty === 0 ? 'filled' : 'open',
            'filled_qty' => $order->quantity - $order->remainingQty,
            'trades' => $trades,
        ]);
    }

    public function cancel(string $orderId)
    {
        $cancelled = $this->engine->cancelOrder($orderId);
        return response()->json(['cancelled' => $cancelled]);
    }

    public function orderBook(string $symbol)
    {
        $snapshot = $this->engine->snapshot(strtoupper($symbol));
        return response()->json($snapshot);
    }
}
```

## Real-World Nümunələr

- **Nasdaq** — INET matching engine, FPGA, 25 mikrosaniyə round-trip
- **NYSE** — Pillar platform, distributed matching
- **CME Group** — futures/options, Globex electronic trading
- **Binance** — crypto, Go + C++, milyonlarla orders/saniyə
- **Robinhood** — retail broker, Python/Go, AWS
- **Interactive Brokers** — professional traders
- **LMAX Disruptor** — open-source low-latency messaging framework
- **Aeron** — ultra-low latency messaging (Martin Thompson)
- **Chronicle Queue** — Java persistent queue, <1 microsecond latency

## Interview Sualları

**1. Order matching engine niyə single-threaded olmalıdır?**
Race condition qarşısı üçün. Birdən çox thread eyni order book-a dəyişiklik etsə data corruption olar. Hər symbol öz thread-i (sharding) — AAPL bir thread, MSFT başqa. Thread-safe concurrent data structure deyil, deterministic behavior üçün single-threaded optimal.

**2. Ultra-low latency necə əldə edilir?**
- **Kernel bypass** (DPDK, Solarflare)
- **FPGA** donanım matching
- **Memory-mapped files, lock-free queues** (LMAX Disruptor)
- **CPU pinning, NUMA-aware**
- **Co-location** — exchange yanında server
- **C++/Rust/Java (no-GC)**
- **Pre-warming** — JIT compilation, cache
- **Microwave/laser links** — Chicago ↔ NYC

**3. Price-time priority dəqiq nə deməkdir?**
1. Yaxşı qiymət (buy yüksək, sell aşağı) əvvəl match.
2. Eyni qiymətdə əvvəl göndərilən order (FIFO) əvvəl match.
Bu qayda market fairness üçün standart.

**4. FIX protokolu niyə bu qədər populyar?**
1992-də yaradılıb, finans industry standardı. Plain-text tag=value format, extensible, broker-broker, broker-exchange communication üçün standart. Modern alternativlər: FAST (compressed FIX), SBE (Simple Binary Encoding) — daha sürətli.

**5. Persistence — hər order-in disk-ə yazılması lazım deyilmi, yavaşlatmır?**
WAL (write-ahead log) append-only yazılır — çox sürətli (sequential IO). Group commit, async replication istifadə olunur. Əsas matching engine in-memory, WAL reliability üçün. Fail olsa WAL-dan replay.

**6. Market data necə milyonlarla client-ə çatdırılır?**
**Multicast UDP** — bir mesaj network-də bir dəfə göndərilir, bütün subscribers alır. TCP unicast milyardlarla client üçün uyğun deyil. Sıralanmış sequence number, retransmission service (TCP/UDP hibrid).

**7. Flash crash kimi incidentlər necə qarşısı alınır?**
- **Circuit breakers** — 7%, 13%, 20% düşdükdə market durur
- **Price limits** — limit up/down bands
- **Kill switch** — trader üçün avtomatik dayandırma
- **Position limits** — maksimum short/long
- **Fat-finger checks** — qiymət anomaliya detection

**8. Settlement vs clearing fərqi?**
- **Clearing**: hesablaşma (kim kimə nə qədər borcludur)
- **Settlement**: transfer (əslində kassa/səhm dəyişir)
- **T+2** (trade + 2 business days) — səhmlər üçün standart
- **CCP (Central Counterparty)** — risk cəmləşdirir

**9. Exchange niyə symbol-based partition?**
Bir symbol-un order book-u tam-konsistent olmalıdır. İki symbol-un tamamilə müstəqil matching-i var → paralellik imkanı. Hər symbol ayrı thread/server → horizontal scaling.

**10. Cancel/replace necə atomic olur?**
- **Optimistic**: cancel + new order iki ayrı mesaj, arada match ola bilər
- **Atomic replace**: tək FIX mesajı (35=G), engine daxilində atomic
- **Latency critical**: müasir exchanges atomic support edir

**11. Dark pool nədir, normal exchange-dən necə fərqlənir?**
Dark pool — order book publik göstərilmir. Böyük institutional trader-lər marketə impact etmədən alış-veriş edir. Normal exchange "lit pool" — hər şey şəffaf.

## Best Practices

1. **Single-threaded matching per symbol** — race condition yoxdur
2. **In-memory order book** — disk çox yavaşdır
3. **WAL (append-only log)** — hər event yazılsın, replay imkanı
4. **Symbol partitioning** — horizontal scaling
5. **Pre-trade risk checks** — matching-ə çatmamış filter et
6. **Circuit breakers** — anomaliyalar üçün
7. **Idempotent client order IDs** — network retry üçün
8. **Nanosecond timestamping** — regulatory audit
9. **Sequence numbers** — market data gap detection
10. **Multicast UDP** — market data distribution
11. **FIX protocol** — industry standard
12. **Disaster recovery** — primary/backup site, replication
13. **Post-trade reconciliation** — hər gecə
14. **Regulatory reporting** — MiFID II, SEC Reg NMS
15. **Monitoring** — latency histogram, p99/p99.9 tracking
16. **Chaos testing** — network partition, GC pause simulation
17. **Hardware timestamping** — NIC-də nanosecond dəqiqlik
