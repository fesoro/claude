# Banking / FinTech App — DB Design (Lead ⭐⭐⭐⭐)

## Tövsiyə olunan DB Stack
```
Primary:      PostgreSQL        (hesablar, əməliyyatlar — ACID kritik)
Audit:        PostgreSQL        (immutable audit log — ayrı schema/DB)
Cache:        Redis             (session, rate limiting, OTP)
Analytics:    ClickHouse        (əməliyyat analitikası, hesabatlar)
Message Queue: Kafka            (əməliyyat event-ləri)
Fraud:        PostgreSQL/Flink  (real-time fraud detection)
```

---

## Niyə PostgreSQL (ACID) Mütləqdir?

```
Banking-in qızıl qaydası:
  "Pul heç vaxt itirilməməlidir"

ACID tələbləri:
  Atomicity:   Transfer = Debit + Credit YA İKİSİ OLUR YA HEÇBİRİ
  Consistency: Balans mənfi ola bilməz (CHECK constraint)
  Isolation:   İki eş-zamanlı transfer race condition yaratmamalıdır
  Durability:  Commit = disk-ə yazılmış (WAL)

NoSQL-in problemi banking-də:
  ✗ MongoDB eventual consistency → pul kaybolur
  ✗ Cassandra tunable consistency → risk
  ✗ Redis (without RDB) → restart-da data itirilir

PostgreSQL üstünlüklər:
  ✓ SELECT ... FOR UPDATE (pessimistic locking)
  ✓ SERIALIZABLE isolation level
  ✓ CHECK constraints (balans < 0 mümkün deyil)
  ✓ Row-level locking
  ✓ Triggers: audit log
  ✓ UUID v4 secure IDs
```

---

## Schema Design

```sql
-- ==================== MÜŞTƏRILƏR ====================
CREATE TABLE customers (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type            VARCHAR(20) NOT NULL,  -- 'individual', 'business'
    status          VARCHAR(20) DEFAULT 'pending',
    -- pending, active, suspended, closed
    
    -- KYC (Know Your Customer)
    kyc_status      VARCHAR(20) DEFAULT 'not_started',
    -- not_started, pending, verified, rejected
    kyc_verified_at TIMESTAMPTZ,
    
    -- Individual
    first_name      VARCHAR(100),
    last_name       VARCHAR(100),
    national_id     VARCHAR(50) UNIQUE,   -- şifrəli saxla!
    date_of_birth   DATE,
    
    -- Business
    company_name    VARCHAR(255),
    tax_id          VARCHAR(50),
    
    email           VARCHAR(255) UNIQUE NOT NULL,
    phone           VARCHAR(20) UNIQUE,
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- KYC documents
CREATE TABLE kyc_documents (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id  UUID NOT NULL REFERENCES customers(id),
    type         VARCHAR(50) NOT NULL,  -- 'id_card', 'passport', 'utility_bill'
    storage_key  TEXT NOT NULL,         -- S3 key (encrypted)
    status       VARCHAR(20) DEFAULT 'pending',
    reviewer_id  UUID REFERENCES customers(id),
    notes        TEXT,
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    reviewed_at  TIMESTAMPTZ
);

-- ==================== HESABLAR ====================
CREATE TABLE accounts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_number  VARCHAR(30) UNIQUE NOT NULL,  -- IBAN formatı
    customer_id     UUID NOT NULL REFERENCES customers(id),
    type            VARCHAR(20) NOT NULL,
    -- checking, savings, loan, credit
    currency        CHAR(3) NOT NULL DEFAULT 'AZN',
    
    -- Balans (NUMERIC dəqiqlik üçün, FLOAT yox!)
    balance         NUMERIC(19,4) NOT NULL DEFAULT 0,
    available_balance NUMERIC(19,4) NOT NULL DEFAULT 0,
    -- available = balance - holds (rezerv edilmiş)
    
    -- Limitlər
    daily_limit     NUMERIC(19,4),
    monthly_limit   NUMERIC(19,4),
    
    status          VARCHAR(20) DEFAULT 'active',
    -- active, frozen, closed, dormant
    
    opened_at       TIMESTAMPTZ DEFAULT NOW(),
    closed_at       TIMESTAMPTZ,
    
    CONSTRAINT balance_non_negative
        CHECK (balance >= 0),  -- overdraft icazə verilmirsə
    CONSTRAINT available_non_negative
        CHECK (available_balance >= 0)
);

CREATE INDEX idx_accounts_customer ON accounts(customer_id);
CREATE INDEX idx_accounts_number ON accounts(account_number);

-- ==================== ƏMƏLİYYATLAR ====================
-- Double-entry bookkeeping (bank mühasibatlığı standartı)
-- Hər transfer = 2 journal entry (debit + credit)

CREATE TABLE transactions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    reference       VARCHAR(50) UNIQUE NOT NULL,  -- T-20260410-ABC123
    type            VARCHAR(30) NOT NULL,
    -- transfer, deposit, withdrawal, payment, fee, interest, refund
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    -- pending, processing, completed, failed, reversed
    
    -- İstiqamət: haradan haraya
    from_account_id UUID REFERENCES accounts(id),
    to_account_id   UUID REFERENCES accounts(id),
    
    amount          NUMERIC(19,4) NOT NULL,
    currency        CHAR(3) NOT NULL,
    
    -- Kurs çevrilməsi (cross-currency)
    exchange_rate   NUMERIC(12,6),
    exchange_amount NUMERIC(19,4),
    fee_amount      NUMERIC(19,4) DEFAULT 0,
    
    description     TEXT,
    metadata        JSONB DEFAULT '{}',  -- external ref, payment details
    
    -- İdempotency
    idempotency_key VARCHAR(100) UNIQUE,  -- double-submit önlənir
    
    initiated_by    UUID,               -- customer_id
    authorized_by   UUID,               -- officer_id (böyük məbləğ)
    
    initiated_at    TIMESTAMPTZ DEFAULT NOW(),
    completed_at    TIMESTAMPTZ,
    failed_at       TIMESTAMPTZ,
    
    CONSTRAINT amount_positive CHECK (amount > 0),
    CONSTRAINT different_accounts CHECK (from_account_id != to_account_id)
);

CREATE INDEX idx_txn_from_account ON transactions(from_account_id, initiated_at DESC);
CREATE INDEX idx_txn_to_account   ON transactions(to_account_id, initiated_at DESC);
CREATE INDEX idx_txn_status       ON transactions(status, initiated_at DESC)
    WHERE status = 'pending';
CREATE INDEX idx_txn_reference    ON transactions(reference);

-- Double-entry ledger (mühasibat journal)
CREATE TABLE ledger_entries (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID NOT NULL REFERENCES transactions(id),
    account_id     UUID NOT NULL REFERENCES accounts(id),
    entry_type     VARCHAR(10) NOT NULL,  -- 'debit', 'credit'
    amount         NUMERIC(19,4) NOT NULL,
    balance_after  NUMERIC(19,4) NOT NULL,  -- snapshot
    created_at     TIMESTAMPTZ DEFAULT NOW()
);
-- QAYDA: SUM(debits) = SUM(credits) hər transaction üçün

CREATE INDEX idx_ledger_account ON ledger_entries(account_id, created_at DESC);
CREATE INDEX idx_ledger_txn     ON ledger_entries(transaction_id);

-- ==================== KARTLAR ====================
CREATE TABLE cards (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_id      UUID NOT NULL REFERENCES accounts(id),
    card_number_hash VARCHAR(64) NOT NULL,  -- SHA-256 (last 4 digits + hash)
    last_four        CHAR(4) NOT NULL,
    card_type        VARCHAR(20) NOT NULL,  -- 'debit', 'credit', 'prepaid'
    network          VARCHAR(20) NOT NULL,  -- 'visa', 'mastercard'
    status           VARCHAR(20) DEFAULT 'inactive',
    expiry_month     SMALLINT NOT NULL,
    expiry_year      SMALLINT NOT NULL,
    is_virtual       BOOLEAN DEFAULT FALSE,
    daily_limit      NUMERIC(19,4),
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    activated_at     TIMESTAMPTZ,
    blocked_at       TIMESTAMPTZ
);

-- ==================== IMMUTABLE AUDIT LOG ====================
-- Ayrı schema (daha güclü izolyasiya)
CREATE SCHEMA audit;

CREATE TABLE audit.financial_events (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_type      VARCHAR(50) NOT NULL,
    entity_type     VARCHAR(50) NOT NULL,
    entity_id       UUID NOT NULL,
    actor_id        UUID,
    actor_ip        INET,
    
    -- Dəyişiklik snapshot-u
    before_state    JSONB,
    after_state     JSONB,
    
    -- Kriptografik chain
    previous_hash   VARCHAR(64),
    event_hash      VARCHAR(64) NOT NULL,
    
    metadata        JSONB DEFAULT '{}',
    occurred_at     TIMESTAMPTZ DEFAULT NOW()
);
-- INSERT ONLY — UPDATE/DELETE icazəsi yoxdur (DB user səviyyəsindən)
-- REVOKE UPDATE, DELETE ON audit.financial_events FROM app_user;
```

---

## Kritik İş Məntiqi: Atomik Transfer

```sql
-- Transfer əməliyyatı (PostgreSQL transaction)
BEGIN;
  -- 1. Hesabları LOCK et (deadlock önləmək üçün sıralı: kiçik ID əvvəl)
  SELECT id, balance, available_balance, status
  FROM accounts
  WHERE id IN ('from_id', 'to_id')
  ORDER BY id  -- sıralı lock → deadlock önlənir
  FOR UPDATE;

  -- 2. Yoxlamalar
  -- status = 'active'?
  -- available_balance >= amount?
  -- daily limit aşılmayıb?

  -- 3. Balansları yenilə
  UPDATE accounts
  SET balance = balance - 100.00,
      available_balance = available_balance - 100.00,
      updated_at = NOW()
  WHERE id = 'from_id';

  UPDATE accounts
  SET balance = balance + 100.00,
      available_balance = available_balance + 100.00,
      updated_at = NOW()
  WHERE id = 'to_id';

  -- 4. Transaction qeydi
  INSERT INTO transactions (reference, type, status, from_account_id, to_account_id, amount, ...)
  VALUES (..., 'completed', ...);

  -- 5. Ledger entries (double-entry)
  INSERT INTO ledger_entries (transaction_id, account_id, entry_type, amount, balance_after)
  VALUES
    (txn_id, 'from_id', 'debit',  100.00, new_from_balance),
    (txn_id, 'to_id',   'credit', 100.00, new_to_balance);

COMMIT;
```

---

## Fraud Detection Schema

```sql
-- Şüphəli əməliyyatlar üçün risk scoring
CREATE TABLE transaction_risk (
    transaction_id  UUID PRIMARY KEY REFERENCES transactions(id),
    risk_score      SMALLINT NOT NULL,  -- 0-100
    risk_level      VARCHAR(10) NOT NULL,  -- low, medium, high, critical
    flags           JSONB DEFAULT '[]',  -- ["unusual_amount", "new_device", "foreign_ip"]
    action          VARCHAR(20) DEFAULT 'allow',  -- allow, review, block
    reviewed_by     UUID,
    reviewed_at     TIMESTAMPTZ,
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- Device fingerprint
CREATE TABLE customer_devices (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id     UUID NOT NULL REFERENCES customers(id),
    device_hash     VARCHAR(64) NOT NULL,
    device_info     JSONB,  -- {os, browser, screen}
    first_seen      TIMESTAMPTZ DEFAULT NOW(),
    last_seen       TIMESTAMPTZ DEFAULT NOW(),
    is_trusted      BOOLEAN DEFAULT FALSE,
    UNIQUE (customer_id, device_hash)
);
```

---

## Redis Dizaynı

```
# OTP (one-time password)
SET otp:{phone} {code} EX 300        -- 5 dəqiqə

# Session (bank session qısa TTL)
SET session:{token} {user_json} EX 1800  -- 30 dəqiqə

# Rate limiting (login cəhdləri)
INCR login_attempts:{email}
EXPIRE login_attempts:{email} 900    -- 15 dəqiqə, max 5 cəhd

# Daily spending (sürətli limit yoxlama)
INCRBY daily_spend:{account_id}:{date} {amount}
EXPIRE daily_spend:{account_id}:{date} 86400

# Transaction idempotency (double submit)
SET idempotent:{key} {txn_id} EX 86400
```

---

## Kritik Dizayn Qərarları

```
1. NUMERIC(19,4) pul üçün (FLOAT YOXDUR!):
   FLOAT: 0.1 + 0.2 = 0.30000000000000004
   NUMERIC: exact decimal arithmetic
   Banking-də cent səviyyəsindəki xəta qəbuledilməzdir

2. Double-entry bookkeeping:
   Hər transfer: debit (from) + credit (to)
   SUM(debits) = SUM(credits) → balans yoxlanılır
   Accounting anomaly detection mümkün

3. Idempotency key:
   Eyni transfer formu iki dəfə submit → eyni nəticə
   UNIQUE constraint on idempotency_key
   Network xətası → retry safe

4. Pessimistic locking (SELECT FOR UPDATE):
   Race condition: iki transfer eyni hesabdan eş-zamanlı
   Lock → sıralı icra → double-spend yoxdur
   Deadlock: həmişə sıralı (ORDER BY id) lock

5. Audit log immutability:
   REVOKE UPDATE, DELETE on audit table
   Hash chaining: tamper detection
   7 il saxlama (tənzimləyici tələb)

6. Kart nömrəsi heç vaxt plain text saxlanmaz:
   PCI DSS: kart məlumatları tokenize edilir
   last_four + hash saxlanılır
   Tam nömrə: payment processor-da (Stripe token)
```

---

## Best Practices

```
✓ Pul üçün həmişə NUMERIC (FLOAT yox)
✓ Hər transfer transaction-da (atomic)
✓ Double-entry bookkeeping (ledger)
✓ Idempotency key → double-submit önlənir
✓ SELECT FOR UPDATE (sıralı ID ilə deadlock önlənir)
✓ Audit log ayrı schema, REVOKE DELETE
✓ Kart məlumatları PCI DSS uyğunluğu
✓ Sensitive data (national_id) şifrəli saxlanır
✓ Fraud scoring async (main flow yavaşlamasın)

Anti-patterns:
✗ Balance UPDATE-ni transaction xaricinə çıxarmaq
✗ SELECT balance → check → UPDATE (race condition)
✗ Float istifadəsi pul üçün
✗ Audit log-u silmək/yeniləmək
✗ Kart nömrəsini plain text DB-də saxlamaq
```

---

## Tanınmış Sistemlər

```
PayPal:
  Oracle (köhnə) → MySQL + PostgreSQL (yeni)
  PostgreSQL: hesablar, əməliyyatlar (ACID kritik)
  Niyə köçdülər? Oracle lisenziya xərci + vendor lock-in
  
Stripe:
  PostgreSQL          → əməliyyatlar, hesablar
  MongoDB (köhnə)     → event log (postgres-ə köçdülər)
  Niyə PostgreSQL?    → ACID + JSONB flexibility

N26 (Digital Bank):
  PostgreSQL          → core banking
  Kafka               → event streaming
  Apache Flink        → real-time fraud detection

Monzo:
  PostgreSQL          → accounts
  Cassandra           → events/transactions (scale)
  Kubernetes + Docker → microservices
```

---

## SWIFT / SEPA Integration Patterns

```
SWIFT = Society for Worldwide Interbank Financial Telecommunication
  International bank-to-bank transfers
  MT103: customer credit transfer message
  
SEPA = Single Euro Payments Area
  EU-daxili EUR transfers
  SEPA Credit Transfer (SCT): 1 iş günü
  SEPA Instant Credit Transfer (SCT Inst): 10 saniyə

DB tələbləri:

CREATE TABLE wire_transfers (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id  UUID REFERENCES transactions(id),
    
    -- Network
    network         ENUM('swift', 'sepa', 'ach', 'fedwire') NOT NULL,
    
    -- Sender
    sender_iban     VARCHAR(34),
    sender_bic      VARCHAR(11),   -- Bank Identifier Code
    sender_name     VARCHAR(140),
    
    -- Receiver
    receiver_iban   VARCHAR(34),
    receiver_bic    VARCHAR(11),
    receiver_name   VARCHAR(140),
    receiver_bank   VARCHAR(140),
    receiver_country CHAR(2),
    
    -- Transfer details
    amount          NUMERIC(19,4) NOT NULL,
    currency        CHAR(3) NOT NULL,
    purpose_code    VARCHAR(4),    -- SEPA purpose code
    remittance_info VARCHAR(140),  -- "Invoice #INV-2024-001"
    
    -- Status tracking
    status          ENUM('pending', 'submitted', 'processing',
                         'completed', 'returned', 'rejected') NOT NULL,
    
    -- Network references
    uetr            VARCHAR(36),   -- SWIFT Unique End-to-End Transaction Ref
    transaction_ref VARCHAR(35),   -- Network-assigned ref
    
    -- Timestamps
    submitted_at    TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    value_date      DATE,
    
    -- Fees
    fee_amount      NUMERIC(10,4) DEFAULT 0,
    correspondent_fee NUMERIC(10,4) DEFAULT 0
);

State machine:
  pending → submitted (API call to SWIFT/SEPA gateway)
  submitted → processing (network acknowledged)
  processing → completed (funds delivered)
  processing → returned (receiver bank returned funds)
```

---

## Regulatory Reporting & GL Reconciliation

```
Regulatory reporting:
  Banks must report to central bank
  AML (Anti-Money Laundering) reports
  CTR (Currency Transaction Report): $10K+ cash
  SAR (Suspicious Activity Report)

CREATE TABLE regulatory_reports (
    id          UUID PRIMARY KEY,
    report_type ENUM('ctr', 'sar', 'aml_alert', 'ofac_match') NOT NULL,
    status      ENUM('draft', 'submitted', 'acknowledged') DEFAULT 'draft',
    
    -- Related entities
    account_id  BIGINT,
    transaction_ids UUID[],
    
    -- Report content
    payload     JSONB NOT NULL,
    
    submitted_at TIMESTAMPTZ,
    regulator_ref VARCHAR(100)
);

General Ledger (GL) Reconciliation:
  "Are our books balanced?"
  
  Daily reconciliation job:
  SELECT
    SUM(amount) FILTER (WHERE entry_type = 'debit') AS total_debit,
    SUM(amount) FILTER (WHERE entry_type = 'credit') AS total_credit
  FROM journal_entries
  WHERE DATE(created_at) = CURRENT_DATE - 1;
  
  total_debit = total_credit → OK
  Difference → alert → investigate
  
  Suspense account:
  Unreconciled amounts → suspense account
  Must be cleared within N days (regulatory)
```
