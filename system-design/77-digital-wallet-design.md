# Digital Wallet Design (Senior)

Bu fayl rəqəmsal pul kisəsi (digital wallet / e-money) sistemlərinin dizaynını
əhatə edir — PayPal, Revolut, Venmo, Wise, Cash App kimi məhsullar. Fayl 20
(ümumi payment system) ilə komplementardır; burada fokus **balance, ledger, P2P
transfer, multi-currency və compliance** üzərindədir.

---


## Niyə Vacibdir

Double-entry bookkeeping fintech-in fundamental konseptidir — hər əməliyyat iki entry yaradır, balans heç vaxt 'itmir'. Multi-currency, KYC/AML, fraud detection — real ödəniş sistemi üçün tələb olunan kompleksliyə hazırlaşdırır. Stripe, Revolut, PayPal arxitekturasının özəyidir.

## Tələblər

### 1.1 Funksional (Functional)

- İstifadəçi **balance** görür (multi-currency — USD, EUR, GBP, AZN)
- Bank / kart vasitəsilə **deposit** (pul yükləmə)
- **P2P transfer** (istifadəçidən istifadəçiyə)
- **Merchant payment** (mağazada ödəniş)
- **Withdrawal** (banka çıxarma)
- **Transaction history** (əməliyyat tarixçəsi)
- **KYC identity verification** (şəxsiyyət təsdiqi)
- Refund / chargeback
- Notification (push, email, SMS)

### 1.2 Qeyri-funksional (Non-functional)

- **Strong consistency** — pul yoxdan yaranmamalı, itməməlidir (conservation
  of money). Eventual consistency BURADA QƏBUL EDİLMİR.
- **Durability** — commit edilmiş tranzaksiya heç vaxt itməməlidir
- **Auditability** — hər hərəkət regulator üçün izlənməlidir (10 il saxlanılır)
- **Availability** — 99.99% (52 dəqiqə/il downtime max)
- **Read >> Write** — balance göstərişi saniyədə milyonlarla, transfer
  saniyədə minlərlə
- **Latency** — P2P transfer < 1 saniyə (user gözləyir)
- **Security** — PCI-DSS, TLS hər yerdə, at-rest encryption

### 1.3 Compliance (Tənzimləmə)

| Regulation | Məqsəd |
|------------|--------|
| **KYC** (Know Your Customer) | Şəxsiyyət təsdiqi — passport, selfie, address proof |
| **AML** (Anti-Money Laundering) | Şübhəli əməliyyatları aşkar et, raport ver |
| **PCI-DSS** | Kart məlumatları üçün təhlükəsizlik standartı |
| **PSD2** (EU) | Payment Services Directive — Strong Customer Auth, open banking |
| **SAR** (Suspicious Activity Report) | FinCEN-ə şübhəli əməliyyat raportu |
| **CTR** (Currency Transaction Report) | 10,000 USD-dən yuxarı əməliyyatlar |
| **GDPR** | Şəxsi məlumat qorunması (EU) |

---

## 2. Double-Entry Ledger Dizaynı

**Qızıl qayda:** Hər tranzaksiya **2 jurnal girişi** yaradır — **debit** və
**credit**. Bunların cəmi həmişə **sıfır**-dır (zero-sum).

### 2.1 Niyə double-entry?

- 500 il əvvəl mühasiblərin icad etdiyi sistem — sübut olunub
- Pul "itə" və ya "yarana" bilməz — hər debit-in bir credit qarşılığı var
- **Auditable** — hər dollar haradan gəldi, hara getdi izlənir
- **Immutable append-only** — heç nə silinmir, yalnız əlavə olunur

### 2.2 ASCII Diaqram — Alice → Bob 100 USD göndərir

```
   TRANSACTION T1 (kind=TRANSFER, amount=100 USD)
   +---------------------------------------------------+
   |                                                   |
   |  ledger_entries:                                  |
   |  +----+--------+-------------+--------+---------+ |
   |  | id | tx_id  | account_id  | amount | direction| |
   |  +----+--------+-------------+--------+---------+ |
   |  | 1  | T1     | alice_usd   | 100.00 | DEBIT   | |  -> Alice balance -100
   |  | 2  | T1     | bob_usd     | 100.00 | CREDIT  | |  -> Bob balance   +100
   |  +----+--------+-------------+--------+---------+ |
   |                                                   |
   |  SUM: -100 + 100 = 0  (zero-sum PRESERVED)        |
   +---------------------------------------------------+


   DEPOSIT — Alice bankdan 50 USD yükləyir
   +---------------------------------------------------+
   |  | 3  | T2     | bank_suspense | 50.00 | DEBIT  | |  -> bank owes platform
   |  | 4  | T2     | alice_usd     | 50.00 | CREDIT | |  -> Alice +50
   +---------------------------------------------------+


   WITHDRAWAL + FEE — Alice banka 30 USD çıxarır, 1 USD fee
   +---------------------------------------------------+
   |  | 5  | T3     | alice_usd     | 31.00 | DEBIT  | |  -> Alice -31
   |  | 6  | T3     | bank_suspense | 30.00 | CREDIT | |  -> 30 banka
   |  | 7  | T3     | fee_revenue   | 1.00  | CREDIT | |  -> 1 platforma
   +---------------------------------------------------+
   SUM: -31 + 30 + 1 = 0
```

### 2.3 Balance hesablanması

```
balance(account) = SUM(CREDIT entries) - SUM(DEBIT entries)
```

Milyonlarla entry üçün hər dəfə SUM hesablamaq bahadır — **cached snapshot +
delta** istifadə edirik (aşağıda).

---

## 3. Sxem (Schema)

### 3.1 `accounts`

```sql
CREATE TABLE accounts (
    id           BIGINT PRIMARY KEY,
    user_id      BIGINT,                   -- NULL for platform/system accounts
    currency     CHAR(3) NOT NULL,         -- USD, EUR, AZN
    type         VARCHAR(20) NOT NULL,     -- USER, PLATFORM, MERCHANT, SUSPENSE, FEE
    status       VARCHAR(20) NOT NULL,     -- ACTIVE, FROZEN, CLOSED
    created_at   TIMESTAMP,
    UNIQUE (user_id, currency)             -- hər user üçün hər currency-də 1 account
);
```

**Account tipləri:**

- **USER** — istifadəçi balansı
- **PLATFORM** — platformanın öz hesabı (revenue, operating)
- **MERCHANT** — tacir hesabı
- **SUSPENSE** — aralıq hesab (bank settlement, pending deposits)
- **FEE** — komissiya gəliri

### 3.2 `ledger_entries` (immutable, append-only)

```sql
CREATE TABLE ledger_entries (
    id               BIGINT PRIMARY KEY,
    transaction_id   BIGINT NOT NULL,
    account_id       BIGINT NOT NULL,
    amount           BIGINT NOT NULL,        -- fixed-point: micro-units
    currency         CHAR(3) NOT NULL,
    direction        CHAR(6) NOT NULL,       -- DEBIT | CREDIT
    posted_at        TIMESTAMP NOT NULL,
    INDEX (account_id, posted_at),
    INDEX (transaction_id)
);
-- DELETE / UPDATE QADAĞANDIR (DB policy və ya trigger ilə təmin edilir)
```

### 3.3 `transactions`

```sql
CREATE TABLE transactions (
    id                BIGINT PRIMARY KEY,
    kind              VARCHAR(20) NOT NULL,   -- TRANSFER, DEPOSIT, WITHDRAWAL, FEE, REFUND
    state             VARCHAR(20) NOT NULL,   -- PENDING, POSTED, REVERSED
    idempotency_key   VARCHAR(64) UNIQUE,     -- retry üçün
    metadata          JSON,                   -- FX rate, fee details, IP, device
    created_at        TIMESTAMP,
    posted_at         TIMESTAMP
);
```

### 3.4 `account_balances` (denormalized cache)

```sql
CREATE TABLE account_balances (
    account_id     BIGINT PRIMARY KEY,
    balance        BIGINT NOT NULL,        -- micro-units
    version        BIGINT NOT NULL,        -- optimistic lock üçün
    updated_at     TIMESTAMP
);
```

---

## 4. Atomicity — DB Transaction

**ACID** vacibdir. Iki account-a `SELECT ... FOR UPDATE` edirik, entry-lər
insert edirik, commit. **Deadlock** qarşısını almaq üçün account id-ləri
sıralı lock edirik (artan id ilə).

### 4.1 Laravel Nümunəsi — TransferMoneyService

```php
<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Exceptions\InsufficientFundsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferMoneyService
{
    public function transfer(
        int $fromAccountId,
        int $toAccountId,
        int $amountMicro,          // 100 USD = 100_000_000 micro-units
        string $currency,
        string $idempotencyKey
    ): Transaction {
        // 1. Idempotency check (file 28)
        $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use (
            $fromAccountId, $toAccountId, $amountMicro, $currency, $idempotencyKey
        ) {
            // 2. Lock accounts in sorted order to prevent deadlock
            [$firstId, $secondId] = $fromAccountId < $toAccountId
                ? [$fromAccountId, $toAccountId]
                : [$toAccountId, $fromAccountId];

            $first  = Account::where('id', $firstId)->lockForUpdate()->firstOrFail();
            $second = Account::where('id', $secondId)->lockForUpdate()->firstOrFail();

            $from = $firstId === $fromAccountId ? $first : $second;
            $to   = $firstId === $toAccountId   ? $first : $second;

            // 3. Validate currency + status
            if ($from->currency !== $currency || $to->currency !== $currency) {
                throw new \InvalidArgumentException('Currency mismatch');
            }
            if ($from->status !== 'ACTIVE' || $to->status !== 'ACTIVE') {
                throw new \DomainException('Account not active');
            }

            // 4. Read cached balance + check sufficiency
            $fromBalance = DB::table('account_balances')
                ->where('account_id', $from->id)
                ->lockForUpdate()
                ->value('balance');

            if ($fromBalance < $amountMicro) {
                throw new InsufficientFundsException();
            }

            // 5. Create transaction record
            $tx = Transaction::create([
                'kind'            => 'TRANSFER',
                'state'           => 'POSTED',
                'idempotency_key' => $idempotencyKey,
                'metadata'        => ['from' => $from->id, 'to' => $to->id],
                'posted_at'       => now(),
            ]);

            // 6. Insert two ledger entries (sum to zero)
            LedgerEntry::insert([
                [
                    'transaction_id' => $tx->id,
                    'account_id'     => $from->id,
                    'amount'         => $amountMicro,
                    'currency'       => $currency,
                    'direction'      => 'DEBIT',
                    'posted_at'      => now(),
                ],
                [
                    'transaction_id' => $tx->id,
                    'account_id'     => $to->id,
                    'amount'         => $amountMicro,
                    'currency'       => $currency,
                    'direction'      => 'CREDIT',
                    'posted_at'      => now(),
                ],
            ]);

            // 7. Update cached balances (same tx!)
            DB::table('account_balances')
                ->where('account_id', $from->id)
                ->update([
                    'balance'    => DB::raw("balance - {$amountMicro}"),
                    'version'    => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            DB::table('account_balances')
                ->where('account_id', $to->id)
                ->update([
                    'balance'    => DB::raw("balance + {$amountMicro}"),
                    'version'    => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            // 8. Emit event AFTER commit (via transactional outbox)
            event(new \App\Events\MoneyTransferred($tx->id));

            return $tx;
        }, attempts: 3);  // retry on deadlock
    }
}
```

**Qeyd:** `attempts: 3` — Laravel deadlock zamanı avtomatik retry edir.

### 4.2 Alternativ — Serializable Isolation

```php
DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
// ... transfer ...
```

Daha sadədir, amma throughput aşağı (PostgreSQL serialization failure retry
tələb edir).

---

## 5. Idempotency

Şəbəkə səhvi olduqda client retry edir. Eyni pulu **2 dəfə köçürmək olmaz**.

- Client hər API çağırışı üçün `Idempotency-Key: uuid-v4` göndərir
- Server DB-də `UNIQUE(idempotency_key)` saxlayır
- İkinci çağırışda eyni nəticəni qaytarır (yeni entry YARATMIR)

Detallar üçün fayl 28 (idempotency) bax.

---

## 6. Balance Computation

### 6.1 İki üsul

**Option A:** `SUM(entries)` hər dəfə — sadə amma yavaş (milyonlarla entry).

**Option B:** Denormalized `account_balances` cache — `UPDATE` hər tranzaksiya
ilə eyni DB tx içində.

Production-da **Option B** + reconciliation job istifadə olunur.

### 6.2 Reconciliation Job

Gündəlik run olur:

```sql
SELECT a.id,
       ab.balance AS cached,
       SUM(CASE WHEN le.direction = 'CREDIT' THEN le.amount ELSE -le.amount END) AS computed
FROM accounts a
LEFT JOIN ledger_entries le ON le.account_id = a.id
LEFT JOIN account_balances ab ON ab.account_id = a.id
GROUP BY a.id, ab.balance
HAVING cached != computed;
```

Uyğunsuzluq varsa — **alarm** → on-call investigation. Bu KRİTİK-dir.

---

## 7. Multi-Currency

### 7.1 Fixed-point arithmetic

**HEÇ VAXT float istifadə etmə!** `0.1 + 0.2 != 0.3` JavaScript/PHP-də.

```php
// YANLIŞ
$balance = 100.25;

// DÜZGÜN
$balanceMicro = 100_250_000;  // 1 USD = 1_000_000 micro-units (6 decimal)
```

### 7.2 FX (Foreign Exchange) transfer

Alice USD → Bob EUR göndərir:

```
T1 entries:
  1. alice_usd:   DEBIT  100 USD
  2. fx_pool_usd: CREDIT 100 USD
  3. fx_pool_eur: DEBIT   92 EUR  (rate 0.92 at 2026-04-18 14:32)
  4. bob_eur:     CREDIT  92 EUR
  5. fee_revenue: CREDIT   1 EUR  (spread as fee)

SUM per currency:
  USD: -100 + 100 = 0
  EUR:  -92 +  92 + 1 - 1 = 0   (fee adjustment)
```

FX rate + timestamp `transactions.metadata` içində saxlanılır (audit üçün).

---

## 8. Fund Sources — Deposit / Withdrawal

| Source | Speed | Reversibility | Risk |
|--------|-------|---------------|------|
| **ACH** (US bank) | 3-5 gün | Yes (60 days) | Low |
| **SEPA** (EU bank) | 1-2 gün | Yes (8 weeks B2C) | Low |
| **Wire transfer** | Same day | No | Low |
| **Kart** (Visa/MC) | Instant | Yes (chargeback 120 days) | High |
| **Internal** | Instant | No | None |

### 8.1 Pending vs Settled

ACH deposit 3-5 gün çəkir. Platforma 2 mərhələ istifadə edir:

1. **PENDING:** `bank_suspense` → `alice_pending_usd` (credit görünür amma
   istifadə oluna bilməz)
2. **SETTLED:** Bank ACH confirmation → `alice_pending_usd` → `alice_usd`

Bəzi platformalar **risk-based** əvvəlcədən pulu available edir (user
reputation + amount limit).

---

## 9. Chargeback & Refund

**Heç vaxt ledger entry SİLMƏ.** Tərs (reverse) entry yarat:

```
Original: Alice → Merchant 50 USD
Refund:   Merchant → Alice 50 USD (yeni transaction, kind=REFUND)
```

`transactions.state` = `REVERSED` işarələnir, amma `ledger_entries` toxunulmaz
qalır. Audit izi saxlanılır.

### Dispute workflow

```
user opens dispute → frozen funds (hold in dispute_suspense account)
                   → investigation
                   → resolve: refund | reject
```

---

## 10. Fraud Detection

### 10.1 Rules engine

- **Velocity:** son 1 saatda > 5 transfer?
- **Amount:** > 5,000 USD single transfer?
- **Geo:** IP Bakıdadır, amma istifadəçi həmişə İstanbuldan daxil olub?
- **Device:** yeni device?
- **Recipient:** yeni hesab (< 24 saat)?

### 10.2 ML scoring

Model → `risk_score` (0-1). Thresholdlar:

- `< 0.3` — auto-approve
- `0.3 - 0.7` — step-up auth (SMS OTP, biometric)
- `> 0.7` — block + manual review queue

### 10.3 AML SAR

$10,000+ və ya structuring (9,999 x 3) → avtomatik SAR raportu FinCEN-ə.

---

## 11. Fees

Fee **ayrı ledger entry** kimi yazılır:

```
T (TRANSFER + FEE):
  alice_usd:     DEBIT  101 USD   (100 + 1 fee)
  bob_usd:       CREDIT 100 USD
  fee_revenue:   CREDIT   1 USD
```

SUM: -101 + 100 + 1 = 0. Transaction metadata-da fee breakdown saxlanılır.

---

## 12. P2P Transfer — End-to-End Flow

```
1. Client: POST /transfers
   Headers: Idempotency-Key: uuid, Authorization: Bearer xxx
   Body: { to_user_id, amount, currency }

2. API Gateway:
   - Auth check (JWT)
   - Rate limit (10/min per user)

3. Transfer Service:
   a. Validate recipient exists, KYC sender done
   b. Fraud check (velocity, ML)
   c. If step-up needed → return 403 with challenge
   d. DB transaction:
      - lockForUpdate both accounts
      - insert 2 ledger entries
      - update both balances
      - insert transaction + idempotency key
   e. Transactional outbox: event = MoneyTransferred

4. Event consumers (async):
   - Notification service (push to Bob)
   - Analytics service (Kafka)
   - Fraud feedback loop

5. Response: 200 OK { transaction_id, new_balance }
```

---

## 13. Interest, Rewards, Cashback

Scheduled job (cron, nightly):

```
FOR EACH user_account WITH balance > 0:
    interest = balance * daily_rate
    CREATE transaction (kind=INTEREST):
        platform_interest_expense: DEBIT  interest
        user_account:              CREDIT interest
```

Cashback eynidir — merchant fee-dən bir hissə user-a qaytarılır.

---

## 14. Account Closure

1. User close sorğusu
2. Status → `FROZEN` (yeni transfer qəbul etmir)
3. Pending transactions gözlənilir
4. Remaining balance → bağlanan user-in bank hesabına withdrawal
5. Balance = 0 olanda status → `CLOSED`
6. Data archived (GDPR: 5 il saxla, sonra sil)

---

## 15. Reporting & Audit

- **Daily reconciliation report** — cached balance == SUM(entries)?
- **Regulatory exports** — SAR, CTR fayllar FinCEN upload
- **Audit trail** — hər admin hərəkəti `audit_log` cədvəlinə (who, what, when,
  IP)
- **Immutability** — ledger DB-də append-only policy + WORM storage backup

---

## 16. Real Sistemlər

| Sistem | Xüsusiyyət |
|--------|-----------|
| **PayPal** | 25+ il köhnə, COBOL legacy layer, gradually migrated to Java microservices |
| **Revolut** | Ledger microservice Java/Kotlin, Postgres + Kafka, dərin compliance team |
| **Stripe Treasury** | API-first banking-as-a-service, Ruby monolith + Scala for ledger |
| **Venmo** | PayPal tərkibində, social feed unikal feature |
| **Wise** | Multi-currency borderless account, peer-to-peer FX matching (həqiqi forex deyil) |
| **Cash App** | Bitcoin + stock trading integration |

---

## 17. Interview Sual-Cavab

**S1: Niyə double-entry ledger istifadə olunur, sadəcə `UPDATE balance -= 100`
niyə kifayət deyil?**

C: Double-entry **audit trail** və **conservation of money** təmin edir. Hər
hərəkət 2 entry yaradır, cəmi 0 — pul yoxdan yaranmamalı, itməməlidir. Sadə
`UPDATE` ilə əməliyyat tarixçəsi, audit, reconciliation mümkün deyil.
Regulator bunu qəbul etməz.

---

**S2: İki istifadəçi eyni vaxtda transfer edirsə necə?**

C: `SELECT ... FOR UPDATE` hər iki hesabı lock edir. İkinci tranzaksiya
birincinin commit-ini gözləyir. Deadlock qarşısını almaq üçün account id
sırası ilə lock edirik (kiçik → böyük). Laravel `DB::transaction(..., 3)` ilə
deadlock zamanı avtomatik retry.

---

**S3: Float niyə istifadə olunmur? Real misal ver.**

C: IEEE 754 floating point bəzi onluq ədədləri dəqiq təmsil edə bilmir.
`0.1 + 0.2 = 0.30000000000000004`. Milyonlarla tranzaksiyada kiçik səhvlər
toplanır. Əvəzinə **bigint micro-units** — 1 USD = 100_000_000 saxla, göstəriş
zamanı böl. Alternativ: DECIMAL(20, 6) SQL-də.

---

**S4: Server crash olursa balance update olmuş, ledger entry insert olmamış
ola bilərmi?**

C: Xeyr, **eyni DB transaction** içində edilir. Crash olarsa transaction
rollback olur — heç bir dəyişiklik qalmır. Outbox pattern (file 33) ilə event
publishing də atomic olur.

---

**S5: Chargeback necə işləyir? Entry silirsən?**

C: **Heç vaxt silmə.** Yeni transaction yarat `kind=REFUND`, tərs istiqamətdə
entry-lər. Original transaction `state=REVERSED` işarələnir amma entries
toxunulmaz qalır. Audit izi üçün kritikdir.

---

**S6: Multi-currency transfer necə qurulur?**

C: FX pool accounts istifadə olunur — `fx_pool_usd`, `fx_pool_eur`. Transfer
zamanı: sender-dən USD debit, FX pool USD credit, FX pool EUR debit, receiver
EUR credit + spread fee. Rate + timestamp `transactions.metadata`-da.
Currency-lər arası balance zero-sum yalnız currency daxilində (hər currency
ayrılıqda = 0).

---

**S7: Reconciliation nədir və niyə lazımdır?**

C: Cached `account_balances` ilə `SUM(ledger_entries)` gündəlik müqayisə
edilir. Uyğunsuzluq — ciddi bug deməkdir (pul itib/yaranıb). Alert çıxarılır,
on-call incident response başlayır. Bu regulator tələbidir. Heç vaxt susmaq
olmaz.

---

**S8: KYC və AML fərqi nədir?**

C: **KYC** — müştərini tanımaq (şəxsiyyət, ünvan, gəlir mənbəyi). Onboarding
zamanı bir dəfəlik prosesdir. **AML** — davamlı monitorinq (transaction
patterns, SAR filing, sanksiya siyahıları). KYC "kimdir?", AML "nə edir?"
sualına cavab verir. İkisi də eyni məqsədə xidmət edir — cinayət pulu
yuyulmasını qarşısı.

---

## 18. Best Practices

- **Double-entry ledger** — həmişə, heç bir istisna. Hər entry pair sum = 0.
- **Immutable append-only** — ledger DELETE/UPDATE yoxdur. Reverse üçün yeni
  tx yarat.
- **Fixed-point arithmetic** — float qadağan. bigint micro-units istifadə et.
- **Single DB transaction** — balance update + ledger insert + idempotency
  birlikdə.
- **Lock ordering** — deadlock-un qarşısını almaq üçün account id sırası ilə.
- **Idempotency key** — hər money-moving API-da məcburi.
- **Reconciliation job** — gündəlik, alert-li.
- **Separate accounts** — USER, PLATFORM, SUSPENSE, FEE — heç vaxt qarışdırma.
- **Audit log** — hər admin hərəkəti immutable-da.
- **Regulatory first** — compliance team arxitekturaya erkən cəlb et.
- **Transactional outbox** — event publishing üçün (file 33).
- **Step-up auth** — yüksək risk əməliyyatında 2FA məcburi.
- **Rate limiting** — user per action + global.
- **Test with chaos** — duplicate requests, deadlocks, crashes — test et.
- **Snapshot + delta** — böyük accounts üçün periodic snapshot + son entries.
- **Separate read replicas** — balance read üçün, leader yalnız write.
- **PII encryption at rest** — SSN, passport, kart nömrələri.
- **Sanctions screening** — OFAC, EU list hər transfer-də.
- **Dispute queue** — SLA-lı manual review team.
- **Documentation** — hər ledger convention-un niyəsini yaz (yeni mühəndislər
  üçün).

---

## Əlaqəli Mövzular

- `20-payment-system.md` — ümumi payment system (Stripe-style)
- `28-idempotency-exactly-once.md` — idempotency dərin
- `33-transactional-outbox.md` — event publishing atomicity
- `64-event-sourcing.md` — ledger event sourcing kimi
- `devops/` — PCI-DSS compliance infrastructure
