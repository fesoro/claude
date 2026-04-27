# Stock Trading Platform — DB Design (Robinhood / Binance style)

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                  Stock Trading DB Stack                          │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ User accounts, portfolio, transactions   │
│ Redis                │ Order book (in-memory), price cache      │
│ TimescaleDB          │ Tick data, price history (time-series)   │
│ Apache Kafka         │ Order events, trade execution pipeline   │
│ ClickHouse           │ Analytics, trade history reports         │
│ Elasticsearch        │ Stock search, news search                │
└──────────────────────┴──────────────────────────────────────────┘

Niyə bu kombinasiya?
  PostgreSQL: ACID — pul hərəkəti atomik olmalı
  Redis: Order book RAM-da (microsecond latency lazım)
  TimescaleDB: Tick data — saniyəlik milyonlarla price update
  Kafka: Decoupled matching engine → settlement → notification
```

---

## Order Book Nədir?

```
Order Book = Alış (Bid) + Satış (Ask) siyahısı

Nümunə: AAPL stock
  
  ASK (Satmaq istəyənlər):    BID (Almaq istəyənlər):
  Price    Quantity            Price    Quantity
  -------  --------            -------  --------
  $185.50    100               $185.20    200
  $185.45    250               $185.15    500
  $185.40    150     ←SPREAD→  $185.10    300
  $185.35    300               $185.05    150
  
  Spread = Best Ask - Best Bid = $185.35 - $185.20 = $0.15
  
  Trade olur:
    Kim almaq istəyir $185.35-ə = ASK-daki $185.35-lik order
    Buyer bid: $185.35 ≥ Seller ask: $185.35 → MATCH!
    
Order types:
  Market order:   "İstənilən qiymətə al"
  Limit order:    "Yalnız $185.30 ya aşağıya al"
  Stop-loss:      "$180-a düşsə sat"
  Stop-limit:     "$180-a düşsə, $179.50-ə limit satış"
```

---

## PostgreSQL Schema

```sql
-- ==================== USERS & ACCOUNTS ====================
CREATE TABLE users (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    email           VARCHAR(255) UNIQUE NOT NULL,
    phone           VARCHAR(20) UNIQUE,
    
    -- KYC (Know Your Customer) — tənzimləyici tələb
    kyc_status      ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    ssn_last_four   CHAR(4),   -- Social Security Number (US)
    
    -- Trading permissions
    can_trade_options   BOOLEAN DEFAULT FALSE,
    can_trade_margin    BOOLEAN DEFAULT FALSE,
    can_trade_crypto    BOOLEAN DEFAULT FALSE,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Brokerage account (1 user → multiple accounts possible)
CREATE TABLE accounts (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    user_id         BIGINT NOT NULL REFERENCES users(id),
    account_type    ENUM('cash', 'margin', 'ira', 'roth_ira') NOT NULL,
    
    -- Balances (DECIMAL, never FLOAT!)
    cash_balance    NUMERIC(19,4) NOT NULL DEFAULT 0,
    buying_power    NUMERIC(19,4) NOT NULL DEFAULT 0,
    -- margin account: buying_power > cash_balance
    
    portfolio_value NUMERIC(19,4) DEFAULT 0,  -- denormalized, batch updated
    
    status          ENUM('active', 'restricted', 'closed') DEFAULT 'active',
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== INSTRUMENTS ====================
CREATE TABLE instruments (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    symbol          VARCHAR(10) UNIQUE NOT NULL,   -- 'AAPL', 'BTC-USD'
    name            VARCHAR(100) NOT NULL,
    type            ENUM('stock', 'etf', 'crypto', 'option', 'futures'),
    exchange        VARCHAR(20),  -- 'NASDAQ', 'NYSE', 'BINANCE'
    currency        CHAR(3) DEFAULT 'USD',
    
    -- Trading hours
    trading_hours_start TIME,
    trading_hours_end   TIME,
    timezone            VARCHAR(50),
    
    is_tradeable    BOOLEAN DEFAULT TRUE,
    is_fractional   BOOLEAN DEFAULT FALSE,  -- fractional shares allowed?
    
    -- Current price (denormalized, updated frequently)
    last_price      NUMERIC(19,6),
    price_updated_at TIMESTAMPTZ
);

-- ==================== ORDERS ====================
CREATE TABLE orders (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    client_order_id VARCHAR(50) UNIQUE,   -- idempotency: client sends own ID
    account_id      BIGINT NOT NULL REFERENCES accounts(id),
    instrument_id   BIGINT NOT NULL REFERENCES instruments(id),
    
    -- Order details
    side            ENUM('buy', 'sell') NOT NULL,
    type            ENUM('market', 'limit', 'stop', 'stop_limit') NOT NULL,
    
    quantity        NUMERIC(19,8) NOT NULL,  -- 8 decimals for crypto/fractional
    limit_price     NUMERIC(19,6),   -- limit/stop-limit üçün
    stop_price      NUMERIC(19,6),   -- stop/stop-limit üçün
    
    -- Time in force
    time_in_force   ENUM('day', 'gtc', 'ioc', 'fok') NOT NULL DEFAULT 'day',
    -- day: bu gün bitər, gtc: ləğv olunana qədər, ioc: dərhal ya ləğv, fok: tamam ya ləğv
    
    -- Execution
    status          ENUM('pending', 'open', 'partially_filled',
                         'filled', 'cancelled', 'rejected', 'expired') NOT NULL,
    
    filled_quantity NUMERIC(19,8) DEFAULT 0,
    avg_fill_price  NUMERIC(19,6),
    
    -- Fees
    commission      NUMERIC(10,4) DEFAULT 0,
    
    -- Extended hours
    extended_hours  BOOLEAN DEFAULT FALSE,
    
    submitted_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    filled_at       TIMESTAMPTZ,
    cancelled_at    TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ,
    
    INDEX idx_account  (account_id, submitted_at DESC),
    INDEX idx_status   (status, submitted_at DESC),
    INDEX idx_instrument (instrument_id, status)
);

-- ==================== TRADES (EXECUTIONS) ====================
-- Hər order fill → 1+ trade record
CREATE TABLE trades (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    order_id        BIGINT NOT NULL REFERENCES orders(id),
    
    -- Counterparty (market maker ya digər trader)
    counterparty_order_id BIGINT REFERENCES orders(id),
    
    quantity        NUMERIC(19,8) NOT NULL,
    price           NUMERIC(19,6) NOT NULL,
    
    -- Settlement
    settlement_date DATE,   -- T+2 (stocks), T+0 (crypto)
    is_settled      BOOLEAN DEFAULT FALSE,
    
    executed_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    INDEX idx_order (order_id)
);

-- ==================== PORTFOLIO ====================
-- User-ın cari pozisiyaları
CREATE TABLE positions (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    account_id      BIGINT NOT NULL REFERENCES accounts(id),
    instrument_id   BIGINT NOT NULL REFERENCES instruments(id),
    
    quantity        NUMERIC(19,8) NOT NULL DEFAULT 0,
    avg_cost        NUMERIC(19,6) NOT NULL,   -- average cost basis
    
    -- Real-time P&L (batch updated)
    current_price   NUMERIC(19,6),
    unrealized_pnl  NUMERIC(19,4),
    
    updated_at      TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE (account_id, instrument_id)
);

-- ==================== LEDGER ====================
-- Bütün pul hərəkətləri (deposit, withdrawal, trade, fee, dividend)
CREATE TABLE ledger_entries (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    account_id      BIGINT NOT NULL REFERENCES accounts(id),
    type            ENUM('deposit', 'withdrawal', 'trade_buy', 'trade_sell',
                         'fee', 'dividend', 'interest', 'adjustment') NOT NULL,
    amount          NUMERIC(19,4) NOT NULL,  -- positive = credit, negative = debit
    balance_after   NUMERIC(19,4) NOT NULL,
    
    reference_id    BIGINT,   -- trade_id, order_id, etc.
    description     VARCHAR(200),
    
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    INDEX idx_account (account_id, created_at DESC)
);
```

---

## Redis: Order Book (In-Memory)

```
Order book RAM-da saxlanır (microsecond latency lazım):

# Limit order book (sorted set)
# Score = price (bid: negative for desc, ask: positive for asc)

# BID side (alış): ən yüksək qiymət ilk
ZADD orderbook:AAPL:bid -185.20 "order:123:200"
ZADD orderbook:AAPL:bid -185.15 "order:124:500"
ZADD orderbook:AAPL:bid -185.10 "order:125:300"

# ASK side (satış): ən aşağı qiymət ilk
ZADD orderbook:AAPL:ask 185.35 "order:126:300"
ZADD orderbook:AAPL:ask 185.40 "order:127:150"
ZADD orderbook:AAPL:ask 185.45 "order:128:250"

# Best bid/ask
ZRANGE orderbook:AAPL:bid 0 0 WITHSCORES   -- best bid
ZRANGE orderbook:AAPL:ask 0 0 WITHSCORES   -- best ask

# Order match check:
best_bid = ABS(ZRANGE bid 0 0 WITHSCORES)[1]  → $185.20
best_ask = ZRANGE ask 0 0 WITHSCORES[1]        → $185.35
best_bid < best_ask → no match yet

# Price cache
SET price:AAPL 185.35 EX 5
SET price:BTC-USD 43250.00 EX 1
```

---

## TimescaleDB: Tick Data

```sql
-- Price history (candlestick/OHLC data)
-- TimescaleDB hypertable: auto-partitioned by time

CREATE TABLE price_ticks (
    time       TIMESTAMPTZ NOT NULL,
    symbol     VARCHAR(10) NOT NULL,
    price      NUMERIC(19,6) NOT NULL,
    volume     NUMERIC(19,8) NOT NULL,
    side       CHAR(1)   -- 'B' buy, 'S' sell
);

SELECT create_hypertable('price_ticks', 'time');
CREATE INDEX ON price_ticks (symbol, time DESC);

-- OHLCV candles (1 minute)
CREATE MATERIALIZED VIEW candles_1m
WITH (timescaledb.continuous) AS
SELECT
    time_bucket('1 minute', time) AS bucket,
    symbol,
    FIRST(price, time) AS open,
    MAX(price)         AS high,
    MIN(price)         AS low,
    LAST(price, time)  AS close,
    SUM(volume)        AS volume
FROM price_ticks
GROUP BY bucket, symbol;

-- Query: Son 1 saatın 1-dəqiqəlik OHLCV-si
SELECT * FROM candles_1m
WHERE symbol = 'AAPL'
  AND bucket >= NOW() - INTERVAL '1 hour'
ORDER BY bucket ASC;
```

---

## Order Matching Engine (Kafka Pipeline)

```
Order flow:

Client → API → Kafka: "new_order" event
                  ↓
           Matching Engine (stateful, Redis order book)
           
           Market order:
             → Check best ask (for buy) or best bid (for sell)
             → Instant fill at market price
             
           Limit order:
             → Can it fill now? Check opposite side
             → Yes: fill immediately
             → No: add to order book (Redis ZADD)
             
           Match found → Kafka: "trade_executed" event
                  ↓
           Settlement Service:
             → Update positions table
             → Update cash balance
             → Create ledger entries
             → Update order status
                  ↓
           Notification Service:
             → Push notification to user
             → Email confirmation

ACID guarantee:
  Position update + balance update + ledger = single transaction
  "Order filled" = atomik
```

---

## Position Update: Weighted Average Cost

```sql
-- Yeni trade gəldi: 100 AAPL @ $185.35 alındı

-- Mövcud position: 50 AAPL @ $180.00 (avg cost)
-- Yeni: 100 AAPL @ $185.35
-- Nəticə: 150 AAPL @ avg cost?

-- Weighted average:
-- new_avg = (old_qty * old_avg + new_qty * new_price) / (old_qty + new_qty)
-- new_avg = (50 * 180.00 + 100 * 185.35) / 150
-- new_avg = (9000 + 18535) / 150 = $183.57

INSERT INTO positions (account_id, instrument_id, quantity, avg_cost)
VALUES (:account_id, :instrument_id, 100, 185.35)
ON CONFLICT (account_id, instrument_id)
DO UPDATE SET
    avg_cost = (positions.quantity * positions.avg_cost + 100 * 185.35)
               / (positions.quantity + 100),
    quantity = positions.quantity + 100,
    updated_at = NOW();

-- Satış zamanı: avg_cost dəyişmir, quantity azalır
-- Realized P&L = (sell_price - avg_cost) * quantity_sold
```

---

## Best Practices

```
✓ NUMERIC(19,4) for money — FLOAT yox!
✓ client_order_id (idempotency) — network retry-da duplicate order olmaz
✓ Order book Redis-də — DB-də deyil (microsecond latency)
✓ Ledger table — hər pul hərəkəti immutable record
✓ Weighted average cost — portfolio P&L hesabı
✓ TimescaleDB tick data — millions of inserts/sec
✓ Settlement separate from matching — async pipeline

Anti-patterns:
✗ Order book-u PostgreSQL-də saxlamaq (çox yavaş)
✗ Float for price/quantity (rounding errors)
✗ Update positions directly without ledger (audit trail yox)
✗ Synchronous matching (blocking API request)
✗ Real-time portfolio value SQL-də hesablamaq hər request-də
```
