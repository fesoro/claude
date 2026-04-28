# Stripe — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     Stripe Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL           │ Core: charges, customers, invoices, plans │
│ Redis                │ Idempotency keys, rate limiting, cache   │
│ Kafka                │ Event streaming, webhook delivery        │
│ ClickHouse           │ Analytics, financial reporting           │
│ Hadoop / Spark       │ Fraud ML, batch analytics                │
│ Elasticsearch        │ Dispute search, transaction search       │
└──────────────────────┴──────────────────────────────────────────┘

Şirkət: $95B valuation (2023), $817B ödəniş prosesslədi (2022)
```

---

## Stripe-ın Əsas Prinsipi: Idempotency

```
Ödəniş sistemlərinin ən böyük problemi:
  Network timeout → "Ödəniş getdi, yoxsa getmədi?"
  Retry etmək → double charge riski!
  
Stripe-ın həlli: Idempotency Keys
  Hər API request-ə idempotency key əlavə et
  Eyni key ilə 2 request → eyni cavab (yenidən charge edilmir)
  
  POST /v1/charges
  Idempotency-Key: order_123_attempt_1
  
  1ci request: charge yaradılır, response saxlanılır
  2ci request (retry): saxlanmış response qaytarılır, charge yoxdur

DB schema:
  idempotency_keys table:
    key           VARCHAR(255) PRIMARY KEY
    response_body JSONB          -- cached response
    request_path  VARCHAR(255)   -- /v1/charges
    user_id       BIGINT
    created_at    TIMESTAMPTZ
    expires_at    TIMESTAMPTZ    -- 24 saat
    
Redis cache:
  SET idem:{key} {response_json} EX 86400
  → İlk DB-yə bax, sonra Redis
```

---

## Core Schema

```sql
-- ==================== CUSTOMERS ====================
CREATE TABLE customers (
    id              VARCHAR(255) PRIMARY KEY,   -- 'cus_abc123' Stripe format
    user_id         BIGINT,                     -- your platform user
    
    email           VARCHAR(255),
    name            VARCHAR(255),
    phone           VARCHAR(50),
    
    -- Address (billing)
    address_line1   VARCHAR(255),
    address_city    VARCHAR(100),
    address_country CHAR(2),
    address_zip     VARCHAR(20),
    
    -- Metadata (custom key-value)
    metadata        JSONB DEFAULT '{}',         -- your internal data
    
    -- Default payment method
    default_source  VARCHAR(255),
    
    currency        CHAR(3) DEFAULT 'usd',
    
    -- Balance (credit)
    balance         INTEGER DEFAULT 0,          -- centlərlə (amount*100)
    
    livemode        BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== PAYMENT METHODS ====================
CREATE TABLE payment_methods (
    id              VARCHAR(255) PRIMARY KEY,   -- 'pm_abc123'
    customer_id     VARCHAR(255) REFERENCES customers(id),
    
    type            VARCHAR(50) NOT NULL,   -- 'card', 'sepa_debit', 'us_bank_account'
    
    -- Card details (tokenized — full PAN heç vaxt saxlanmır)
    card_brand      VARCHAR(20),            -- 'visa', 'mastercard'
    card_last4      CHAR(4),
    card_exp_month  SMALLINT,
    card_exp_year   SMALLINT,
    card_country    CHAR(2),
    card_fingerprint VARCHAR(255),          -- same card → same fingerprint
    
    -- Billing details
    billing_name    VARCHAR(255),
    billing_email   VARCHAR(255),
    billing_address JSONB DEFAULT '{}',
    
    is_default      BOOLEAN DEFAULT FALSE,
    livemode        BOOLEAN DEFAULT FALSE,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CHARGES ====================
CREATE TABLE charges (
    id              VARCHAR(255) PRIMARY KEY,   -- 'ch_abc123'
    customer_id     VARCHAR(255) REFERENCES customers(id),
    payment_method_id VARCHAR(255) REFERENCES payment_methods(id),
    
    -- Amount (always in smallest currency unit)
    amount          INTEGER NOT NULL,           -- 1000 = $10.00
    amount_captured INTEGER DEFAULT 0,
    amount_refunded INTEGER DEFAULT 0,
    currency        CHAR(3) NOT NULL,           -- 'usd', 'eur', 'azn'
    
    -- Status
    status          VARCHAR(20) DEFAULT 'pending',
    -- pending, succeeded, failed
    
    -- Payment processor details
    payment_intent_id VARCHAR(255),
    
    -- Failure info
    failure_code    VARCHAR(100),
    failure_message TEXT,
    
    -- Capture
    captured        BOOLEAN DEFAULT FALSE,
    
    -- Description
    description     VARCHAR(500),
    statement_descriptor VARCHAR(22),          -- on bank statement
    
    -- Metadata
    metadata        JSONB DEFAULT '{}',
    receipt_email   VARCHAR(255),
    
    -- Risk assessment
    risk_score      SMALLINT,                  -- 0-100
    risk_level      VARCHAR(20),               -- 'normal', 'elevated', 'highest'
    
    livemode        BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_charges_customer  ON charges(customer_id, created_at DESC);
CREATE INDEX idx_charges_status    ON charges(status, created_at DESC);
CREATE INDEX idx_charges_created   ON charges(created_at DESC);

-- ==================== REFUNDS ====================
CREATE TABLE refunds (
    id              VARCHAR(255) PRIMARY KEY,   -- 'rf_abc123'
    charge_id       VARCHAR(255) NOT NULL REFERENCES charges(id),
    
    amount          INTEGER NOT NULL,
    currency        CHAR(3) NOT NULL,
    
    status          VARCHAR(20) DEFAULT 'pending',
    -- pending, succeeded, failed, cancelled
    
    reason          VARCHAR(50),
    -- 'duplicate', 'fraudulent', 'requested_by_customer'
    
    failure_reason  VARCHAR(100),
    
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== PAYMENT INTENTS ====================
-- Modern Stripe API (2019+): charge-ı əvəzlədi
CREATE TABLE payment_intents (
    id              VARCHAR(255) PRIMARY KEY,   -- 'pi_abc123'
    customer_id     VARCHAR(255) REFERENCES customers(id),
    
    amount          INTEGER NOT NULL,
    amount_received INTEGER DEFAULT 0,
    currency        CHAR(3) NOT NULL,
    
    status          VARCHAR(50) DEFAULT 'requires_payment_method',
    -- requires_payment_method → requires_confirmation →
    -- requires_action → processing → succeeded / payment_failed / cancelled
    
    -- Automatic payment methods
    payment_method_id VARCHAR(255),
    payment_method_types VARCHAR(50)[] DEFAULT '{"card"}',
    
    -- 3DS / SCA
    next_action     JSONB DEFAULT '{}',         -- redirect_to_url, use_stripe_sdk
    
    -- On behalf of (Connect)
    on_behalf_of    VARCHAR(255),               -- connected account ID
    transfer_data   JSONB DEFAULT '{}',
    application_fee_amount INTEGER,
    
    -- Idempotency
    idempotency_key VARCHAR(255) UNIQUE,
    
    -- Metadata
    description     VARCHAR(1000),
    metadata        JSONB DEFAULT '{}',
    
    livemode        BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    cancelled_at    TIMESTAMPTZ,
    
    CONSTRAINT check_amount CHECK (amount > 0)
);

CREATE INDEX idx_pi_customer ON payment_intents(customer_id, created_at DESC);
CREATE INDEX idx_pi_status   ON payment_intents(status, created_at DESC);
```

---

## Subscription Billing Schema

```sql
-- ==================== PRODUCTS & PRICES ====================
CREATE TABLE products (
    id          VARCHAR(255) PRIMARY KEY,   -- 'prod_abc123'
    name        VARCHAR(500) NOT NULL,
    description TEXT,
    active      BOOLEAN DEFAULT TRUE,
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE prices (
    id          VARCHAR(255) PRIMARY KEY,   -- 'price_abc123'
    product_id  VARCHAR(255) REFERENCES products(id),
    
    -- Amount
    unit_amount INTEGER,                    -- NULL if metered
    currency    CHAR(3) NOT NULL,
    
    -- Billing model
    type        VARCHAR(20) DEFAULT 'recurring',  -- 'one_time', 'recurring'
    
    -- Recurring settings
    interval    VARCHAR(10),               -- 'day', 'week', 'month', 'year'
    interval_count SMALLINT DEFAULT 1,    -- hər 3 ayda → interval_count=3, interval='month'
    
    -- Usage-based (metered) billing
    billing_scheme VARCHAR(20) DEFAULT 'per_unit',  -- 'per_unit', 'tiered'
    usage_type  VARCHAR(10) DEFAULT 'licensed',      -- 'licensed', 'metered'
    
    -- Tiered pricing
    tiers       JSONB DEFAULT '[]',         -- [{up_to: 100, unit_amount: 20}, ...]
    tiers_mode  VARCHAR(20),               -- 'graduated', 'volume'
    
    active      BOOLEAN DEFAULT TRUE,
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== SUBSCRIPTIONS ====================
CREATE TABLE subscriptions (
    id              VARCHAR(255) PRIMARY KEY,  -- 'sub_abc123'
    customer_id     VARCHAR(255) REFERENCES customers(id),
    
    status          VARCHAR(20) DEFAULT 'active',
    -- trialing, active, past_due, canceled, unpaid, incomplete, paused
    
    -- Billing dates
    current_period_start TIMESTAMPTZ NOT NULL,
    current_period_end   TIMESTAMPTZ NOT NULL,
    trial_start          TIMESTAMPTZ,
    trial_end            TIMESTAMPTZ,
    
    -- Cancellation
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    canceled_at          TIMESTAMPTZ,
    cancel_at            TIMESTAMPTZ,
    
    -- Payment
    default_payment_method_id VARCHAR(255),
    latest_invoice_id         VARCHAR(255),
    
    -- Metadata
    metadata    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Subscription items (multiple prices per subscription)
CREATE TABLE subscription_items (
    id              VARCHAR(255) PRIMARY KEY,
    subscription_id VARCHAR(255) REFERENCES subscriptions(id),
    price_id        VARCHAR(255) REFERENCES prices(id),
    quantity        INTEGER DEFAULT 1,
    metadata        JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Webhook Delivery System

```
Stripe events → client-lərin endpoint-lərinə çatdırılır

Event types: 250+ event type
  payment_intent.succeeded
  invoice.payment_failed
  customer.subscription.deleted
  charge.dispute.created
  ...

Delivery:
  Sıra: attempt → response → retry
  
  HTTP POST client endpoint-ə
  Success: 200 response
  Failure: 4xx/5xx → retry

Retry schedule:
  Immediately
  5 min
  30 min
  2 hour
  8 hour
  2 day
  3 day
  ...
  3 günlük window

DB schema:
  webhook_endpoints:
    id, url, events (array), is_enabled, secret_key
    
  webhook_events:
    id, endpoint_id, event_type, payload (JSON)
    status (pending/sent/failed), attempt_count
    last_attempted_at, next_retry_at, response_code
    
Redis queue:
  webhook:queue → pending deliveries
  Sorted set by next_retry_at timestamp
```

---

## Fraud Detection

```
Radar (Stripe-ın fraud sistemi):

ML features per transaction:
  - Card fingerprint risk history
  - Email / IP geolocation mismatch
  - Velocity: bu kart bu gün neçə charge?
  - Device fingerprint
  - BIN (bank ID) - prepaid vs debit vs credit
  - Customer lifetime value
  - Merchant category
  
Risk scoring:
  0-100: low to high risk
  > 65: extra authentication (3DS2)
  > 90: block automatically

Custom rules:
  "Block if amount > $500 AND country != user country"
  "Review if first purchase AND amount > $100"
  
Redis velocity checks:
  INCR card:{fingerprint}:charges:1h    → last hour charge count
  INCR email:{hash}:charges:24h         → daily email charge count
  INCR ip:{ip}:charges:5m               → 5 min rate limit
```

---

## Connect: Marketplace Payments

```
Stripe Connect: multi-party payments
  Platform (siz) + Connected Accounts (vendor-lər)
  
Use cases:
  Airbnb: Platform = Airbnb, Connected = Hosts
  Lyft:   Platform = Lyft, Connected = Drivers
  Shopify: Platform = Shopify, Connected = Merchants

3 model:
  Standard: Stripe account (vendor onboards directly)
  Express:  Stripe handles onboarding UI
  Custom:   Platform controls everything

Charge flow:
  Customer → Platform charge ($100)
  → $3 fee retained by Platform
  → $97 transferred to Connected Account
  
DB:
  transfers:
    id, source_transaction, destination, amount, currency
    metadata, created_at
    
  transfer_reversals:
    id, transfer_id, amount, reason
```

---

## Scale Faktları

```
Numbers (2022):
  $817 billion payment volume
  135+ currencies
  135+ countries
  Millions of API requests/day
  
  API uptime: 99.999% SLA
  
  Engineering: ~8,000 employees
  
Şirkət tarixi:
  2010: Patrick + John Collison (Irish brothers)
  2011: Launched (7 lines of code integration)
  2021: $95B valuation
  
Architecture decisions:
  PostgreSQL over MySQL: JSONB, arrays, advanced features
  Monolith → Services (gradual)
  "We went from MySQL to PostgreSQL for JSONB"
  — Nelson Minar, Stripe Engineering
```

---

## Stripe-dan Öyrəniləcəklər

```
1. Idempotency as first-class feature:
   Bütün write operations: idempotency key
   Network retry = safe
   "Payment API-ni idempotent etməzsən, problem yaranır"

2. Amount in smallest unit:
   $10.00 → 1000 (cents)
   Floating point error yoxdur: 0.1 + 0.2 ≠ 0.3
   INTEGER arithmetic: dəqiq

3. Event-driven webhooks:
   Client sistemlərini real-time notify et
   At-least-once delivery + idempotent handlers

4. Risk at charge time:
   Hər charge: ML risk score
   Threshold-a görə 3DS2 tələb et

5. Livemode / testmode:
   Eyni DB, livemode boolean
   Test data production-a qarışmır
   "cus_test_" vs "cus_live_" prefix convention
```
