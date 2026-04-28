# PayPal — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     PayPal Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ Oracle               │ Legacy core: accounts, transactions      │
│ MySQL (Percona)      │ New services, user data (migration)      │
│ PostgreSQL           │ Analytics, reporting, newer systems      │
│ Cassandra            │ Risk scoring signals, activity logs      │
│ HBase                │ Fraud detection feature store            │
│ Redis                │ Session, rate limiting, idempotency      │
│ Elasticsearch        │ Transaction search, dispute resolution   │
│ Apache Kafka         │ Event streaming, payment events          │
│ Hadoop/Hive          │ Big data analytics, ML training data     │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Tarixi: Oracle-dən MySQL-ə

```
PayPal DB tarixi:

1998-2010: Oracle mərkəzi
  eBay (PayPal-ın sahibi) → Oracle RAC
  Financial data: ACID kritik
  "Oracle bizim üçün işləyirdi, amma bahalı idi"
  
  Oracle lisenz xərci: milyonlarla dollar/il
  Oracle RAC: yüksək availability, amma scale mürəkkəb
  Vertical scaling limitinə çatıldı

2010-2015: MySQL-ə köçüş başladı
  PayPal's "NDB Cluster" experiments
  Percona XtraDB Cluster (Galera-based)
  Horizontal sharding
  
  "We saved $XX million by moving from Oracle to MySQL"

2015-sonra: Polyglot dövrü
  New services: PostgreSQL, Cassandra
  Real-time fraud: HBase feature store
  Analytics: Hadoop stack

Əsas dərs:
  Oracle → MySQL köçüşü 3+ il çəkdi
  "Migration is not a weekend project"
  Parallel running strategy: dual write → verify → cutover
```

---

## Niyə Maliyyə = ACID?

```
PayPal-ın əsas tələbi:

Pul transferi atomik olmalıdır:
  Göndərəndən silinmə + Alıcıya əlavə = EYNI ƏMƏLİYYAT

Əgər atomik olmasaydı:
  Scenario 1: Silindi, əlavə edilmədi → pul "yox olur"
  Scenario 2: Silinmədi, əlavə edildi → pul "yarandı"
  
  Hər iki halda maliyyə böhranı!

ACID tələbləri:
  A — Atomicity:    Hər iki əməliyyat ya olur, ya da heç biri
  C — Consistency:  Məbləğlər düzgün, constraint-lər qorunur
  I — Isolation:    Eyni anda iki transfer eyni hesabı pozmasın
  D — Durability:   Konfirm edilmiş transaction itmir

NoSQL (eventual consistency) niyə OLMAZ?
  "User A transferred $1000, but recipient sees $0 for 2 seconds"
  Bu maliyyə tənzimləyicilərinin tələblərinə ziddir
  PCI-DSS, SOX compliance tələb edir consistency
```

---

## MySQL Schema: Core Financial

```sql
-- ==================== ACCOUNTS ====================
CREATE TABLE accounts (
    id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    account_type    ENUM('personal', 'business', 'merchant') NOT NULL,
    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    
    -- Balans: DECIMAL, FLOAT deyil!
    -- FLOAT: 100.01 + 200.02 = 300.02999... (floating point error)
    -- DECIMAL: exact numeric storage
    balance         DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    reserved_balance DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
    -- reserved: pending transactions üçün hold
    
    status          ENUM('active', 'limited', 'suspended', 'closed') NOT NULL,
    
    -- Compliance
    kyc_status      ENUM('none', 'pending', 'verified', 'failed') DEFAULT 'none',
    
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user (user_id),
    CONSTRAINT chk_balance CHECK (balance >= 0),
    CONSTRAINT chk_reserved CHECK (reserved_balance >= 0)
) ENGINE=InnoDB;

-- ==================== TRANSACTIONS ====================
CREATE TABLE transactions (
    id              VARCHAR(36) PRIMARY KEY,  -- UUID
    reference_id    VARCHAR(100) UNIQUE NOT NULL,  -- idempotency key
    -- reference_id: sender tərəfindən göndərilir, duplicate protection
    
    type            ENUM('payment', 'refund', 'withdrawal', 'deposit',
                         'fee', 'reversal', 'adjustment') NOT NULL,
    status          ENUM('pending', 'processing', 'completed',
                         'failed', 'reversed', 'disputed') NOT NULL,
    
    sender_account_id   BIGINT UNSIGNED,
    receiver_account_id BIGINT UNSIGNED,
    
    amount          DECIMAL(19,4) NOT NULL,
    currency        CHAR(3) NOT NULL,
    fee_amount      DECIMAL(19,4) DEFAULT 0.0000,
    
    -- FX conversion
    exchange_rate   DECIMAL(15,8),
    original_amount DECIMAL(19,4),
    original_currency CHAR(3),
    
    description     VARCHAR(500),
    metadata        JSON,  -- {ip, device_id, merchant_category}
    
    initiated_at    DATETIME NOT NULL,
    completed_at    DATETIME,
    
    INDEX idx_sender   (sender_account_id, initiated_at DESC),
    INDEX idx_receiver (receiver_account_id, initiated_at DESC),
    INDEX idx_status   (status, initiated_at DESC),
    INDEX idx_ref      (reference_id)
) ENGINE=InnoDB;

-- ==================== DOUBLE-ENTRY LEDGER ====================
-- Hər transaction → iki journal entry (double-entry bookkeeping)
-- Total debit = Total credit həmişə!
CREATE TABLE journal_entries (
    id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(36) NOT NULL,
    account_id     BIGINT UNSIGNED NOT NULL,
    entry_type     ENUM('debit', 'credit') NOT NULL,
    amount         DECIMAL(19,4) NOT NULL,
    balance_after  DECIMAL(19,4) NOT NULL,  -- snapshot
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_transaction (transaction_id),
    INDEX idx_account     (account_id, created_at DESC)
) ENGINE=InnoDB;

-- ==================== PAYMENT METHODS ====================
CREATE TABLE payment_methods (
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    type         ENUM('card', 'bank_account', 'paypal_balance') NOT NULL,
    
    -- Tokenized (Braintree/Vault)
    token        VARCHAR(255) NOT NULL,  -- never store raw card data!
    
    -- Card info (PCI-safe subset)
    last_four    CHAR(4),
    card_brand   VARCHAR(20),  -- 'visa', 'mastercard', 'amex'
    exp_month    TINYINT,
    exp_year     SMALLINT,
    
    -- Bank account
    bank_name    VARCHAR(100),
    account_last_four CHAR(4),
    routing_type VARCHAR(20),  -- 'ach', 'wire', 'sepa'
    
    is_default   BOOLEAN DEFAULT FALSE,
    is_verified  BOOLEAN DEFAULT FALSE,
    
    INDEX idx_user (user_id)
) ENGINE=InnoDB;
```

---

## Transfer Prosesi: Atomik Əməliyyat

```sql
-- PayPal payment transfer (simplified)
-- Bu əməliyyat tək transaction içindədir

START TRANSACTION;

-- 1. Sender balansını yoxla və lock et
SELECT balance, status
FROM accounts
WHERE id = ? AND status = 'active'
FOR UPDATE;  -- Row lock: başqa transfer eyni hesaba toxuna bilməz

-- 2. Yetərli balans?
-- Application layer yoxlayır
-- Yetərsiz → ROLLBACK

-- 3. Sender balansını azalt
UPDATE accounts
SET balance = balance - ?, updated_at = NOW()
WHERE id = ? AND balance >= ?;  -- Double check

-- 4. Receiver balansını artır
UPDATE accounts
SET balance = balance + ?, updated_at = NOW()
WHERE id = ?;

-- 5. Transaction record
INSERT INTO transactions (id, reference_id, type, status,
    sender_account_id, receiver_account_id, amount, currency, initiated_at)
VALUES (UUID(), ?, 'payment', 'completed', ?, ?, ?, ?, NOW());

-- 6. Journal entries (double-entry)
INSERT INTO journal_entries (transaction_id, account_id, entry_type, amount, balance_after)
VALUES
    (LAST_INSERT_ID(), sender_id,   'debit',  amount, sender_new_balance),
    (LAST_INSERT_ID(), receiver_id, 'credit', amount, receiver_new_balance);

COMMIT;

-- Əgər hər hansı addım fail olsa → ROLLBACK
-- Pul nə itir, nə yaranır
```

---

## Idempotency Pattern

```
PayPal-ın duplicate payment problemi:

Scenario:
  Client → POST /pay → Server proses edir → Network timeout
  Client bilmir: "Pul getdi, ya getmədi?"
  Client retry edir → İki dəfə pul gedir!

Həll: Idempotency Key (reference_id)

Client hər request üçün unikal ID göndərir:
  POST /pay
  Idempotency-Key: uuid-12345
  
Server:
  1. Bu key daha əvvəl görülübmü? → Redis/DB-dən yoxla
  2. Yoxdursa → proses et, key-i saxla
  3. Varsa → əvvəlki nəticəni qaytar (yenidən proses etmə!)

Redis:
  SET idempotency:{key} {result_json} EX 86400  -- 24 saat

MySQL:
  UNIQUE constraint on reference_id
  Duplicate INSERT → error → return existing
```

---

## Fraud Detection: HBase + Cassandra

```
PayPal-ın risk sistemi:

Real-time fraud scoring:
  Hər transaction → risk score hesabla → approve/deny/review

Feature Store (HBase):
  user:txn_count_1h   → son 1 saatdakı transaction sayı
  user:amount_sum_24h → son 24 saatdakı məbləğ
  user:unique_ips_7d  → son 7 gündəki unikal IP-lər
  ip:txn_count_1h     → bu IP-dən son 1 saatdakı transaction
  device:new_accounts → bu cihazdan yaradılmış yeni hesab sayı

HBase why?
  ✓ Wide-column: hər user üçün yüzlərlə feature
  ✓ Millisecond reads (in-memory region server)
  ✓ Time-series features: window aggregations

Cassandra (Activity Log):
  CREATE TABLE risk_signals (
      user_id    UUID,
      signal_time TIMESTAMP,
      signal_type TEXT,   -- 'new_ip', 'new_device', 'unusual_amount'
      value       TEXT,
      PRIMARY KEY (user_id, signal_time)
  ) WITH CLUSTERING ORDER BY (signal_time DESC);

ML Model:
  XGBoost/LightGBM → risk score 0-100
  >80: auto-reject
  60-80: step-up verification (SMS OTP)
  <60: approve
```

---

## Redis Patterns

```
# Idempotency cache
SET idempotency:{reference_id} {result_json} EX 86400

# Rate limiting (per user)
INCR rate:payment:{user_id}:{minute}
EXPIRE rate:payment:{user_id}:{minute} 60

# Session
SET session:{token} {user_json} EX 3600

# Pending transaction lock
SET txn:lock:{sender_id} {txn_id} EX 30 NX
-- NX: yalnız yoxdursa set et → concurrent double-spend prevention

# Exchange rates cache
SET fx:USD:EUR 0.92 EX 300  -- 5 dəqiqə

# Feature flags
HSET feature:flags checkout_v2_enabled 1 crypto_enabled 0
```

---

## Scale Faktları

```
Numbers (2023):
  435M+ active accounts
  22B+ payment transactions per year
  $1.36 trillion total payment volume (2022)
  ~600K transactions per hour
  200+ countries and regions
  25+ currencies

Infrastructure:
  Multiple data centers (active-active)
  99.99% uptime SLA
  PCI-DSS Level 1 compliance
  SOX compliance (financial reporting)

Migration stats:
  Oracle → MySQL: 2012-2016 (4 yıl)
  Cost savings: $X million/year in licensing
  
Fraud:
  Real-time scoring: <100ms
  Fraud rate: ~0.1% (industry avg ~1.5%)
  $X billion fraud prevented annually
```

---

## PayPal-dan Öyrəniləcəklər

```
1. DECIMAL, FLOAT yox:
   Maliyyə hesablamaları → DECIMAL(19,4)
   IEEE 754 floating point xətaları → maliyyədə qəbuledilməzdir

2. Double-entry bookkeeping:
   Hər transfer → iki journal entry
   Audit: total debit = total credit həmişə
   Reconciliation asanlaşır

3. Idempotency everywhere:
   Bütün payment API-larında idempotency key
   Retry-safe operations

4. Never store raw card data:
   PCI-DSS: kart nömrəsini HEÇ VAXT saxlama
   Tokenization (Braintree/Stripe token)
   Token = kart referansı, özü deyil

5. Migration is multi-year:
   Oracle → MySQL 4 il çəkdi
   Parallel run → verify → cutover
   "Big Bang migration" işləmir

6. Compliance shapes architecture:
   PCI-DSS: kart məlumatları izolyasiyası
   GDPR: data residency (EU datası EU-da)
   SOX: audit trail immutability
```
