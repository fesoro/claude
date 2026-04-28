# Two-Phase Commit (2PC) (Lead ⭐⭐⭐⭐)

## İcmal

Two-Phase Commit (2PC) — bir neçə resource manager (DB, message broker) arasında atomik commit/rollback təmin edən distributed transaction protokolu. Coordinator bütün participant-lardan "hazırıq" sözü alır (Phase 1), sonra hamısına birlikdə commit/rollback əmri verir (Phase 2). Ya hamısı commit edir, ya heç biri.

## Niyə Vacibdir

Bir neçə DB-yə eyni anda yazaraq atomiklik lazım olduqda — məsələn, bank köçürmə: debit və credit fərqli DB-lərdədir. Biri uğurlu, biri uğursuz olsa inkonsistentlik baş verir. 2PC bu problemi protokol səviyyəsində həll edir. Lakin bu həll gəlir: lock-lar, blocking, SPOF riski. Buna görə mikroservis arxitekturasında 2PC-nin yerini Saga tutmuşdur.

## Əsas Anlayışlar

- **Coordinator**: transaction-ı idarə edən komponent; Phase 1 və 2-ni başladır
- **Participant (Resource Manager)**: DB, broker — prepare/commit/rollback cavabı verir
- **Phase 1 (Prepare/Voting)**: coordinator hər participant-a "prepare" göndərir; participant resursları lock edir, WAL-a yazır, READY/ABORT cavabı verir
- **Phase 2 (Commit/Decision)**: hamısı READY → COMMIT; biri ABORT → ROLLBACK
- **XA Transaction**: X/Open 2PC standard; MySQL, PostgreSQL XA-nı dəstəkləyir
- **Blocking protocol**: coordinator Phase 2-dən əvvəl çöksə participant-lar lock tutaraq gözləyir — 2PC-nin ən böyük problemi

## Praktik Baxış

- **Real istifadə**: eyni datacenter-dəki bir neçə DB (MySQL Galera, PostgreSQL BDR), kritik financial transaction, XA-ı dəstəkləyən sistemlər
- **Trade-off-lar**: strong ACID consistency; lakin blocking protocol (coordinator SPOF), lock-lar performance-ı aşağı salır, mikroservislər üçün impraktik, network partition-da deadlock riski
- **İstifadə etməmək**: microservice arxitekturasında (Saga seçin); high-throughput sistemlərdə; network unreliable olduqda; cross-cloud service-lər üçün
- **Common mistakes**: 2PC-ni hər distributed əməliyyat üçün default seçmək; coordinator-ı replikalamadan tək instansiya kimi çalışdırmaq; 2PC-nin heuristic failure (partial commit) riskini test etməmək

## Anti-Pattern Nə Zaman Olur?

**High-throughput sistemdə 2PC — lock hell:**
Phase 1-də resource-lar lock olur, commit-ə qədər başqaları gözləyir. Yüzlərlə paralel transaction varsa deadlock riski ciddi artır, throughput kəskin düşür. Uzun sürən 2PC transaction-ları sistemi paraliz edə bilər. Yüksək trafik üçün Saga (eventual consistency) daha uyğundur.

**2PC cross-cloud service-lər üçün:**
Cloud service-lər çox vaxt XA protokolunu dəstəkləmir. Coordinator cross-cloud network-dədirsə latency artır, partition riski çoxalır. Cross-cloud distributed transaction üçün Saga + idempotency daha realistikdir.

**Coordinator-ı SPOF kimi buraxmaq:**
Coordinator Phase 2-dən əvvəl çöksə bütün participant-lar lock tutaraq gözləyir — sistem donur. Coordinator active-passive replica ilə replikalanmalıdır; recovery protokolu olmalıdır.

**XA-nı her yerdə default istifadə etmək:**
Hər DB əməliyyatında XA overhead böyüyür, performans aşağı düşür. XA yalnız bir neçə ayrı resource manager (fərqli DB, broker) arasında atomiklik tələb olunduqda istifadə edin.

## Nümunələr

### Ümumi Nümunə

```
Phase 1 — PREPARE:

Coordinator          DB1              DB2
    │                 │                │
    │─── PREPARE ────►│                │
    │                 │ (lock resources)│
    │◄─── READY ──────│                │
    │                 │                │
    │─── PREPARE ─────────────────────►│
    │                                   │ (lock resources)
    │◄─── READY ───────────────────────│

Hamısı READY → Phase 2-yə keç

Phase 2 — COMMIT:

Coordinator          DB1              DB2
    │                 │                │
    │─── COMMIT ─────►│                │
    │◄─── ACK ────────│                │
    │                 │                │
    │─── COMMIT ──────────────────────►│
    │◄─── ACK ────────────────────────│

Transaction tamamlandı ✅
```

Coordinator Phase 2-dən əvvəl çöksə: DB1 və DB2 lock tutaraq gözləyir. Coordinator recover olana qədər heç kim irəliləyə bilmir — bu 2PC-nin ən böyük problemidir.

### PHP/Laravel Nümunəsi

```php
<?php

// PHP PDO ilə XA Transaction (MySQL XA)
class XATransactionManager
{
    private array $connections = [];
    private string $xid;

    public function __construct(array $dsns)
    {
        $this->xid = uniqid('xa_', true);

        foreach ($dsns as $name => $dsn) {
            $pdo = new \PDO($dsn['dsn'], $dsn['user'], $dsn['pass']);
            $this->connections[$name] = $pdo;
        }
    }

    public function execute(callable $fn): bool
    {
        // XA START — hər connection-da transaction başlat
        foreach ($this->connections as $name => $pdo) {
            $pdo->exec("XA START '{$this->xid}_{$name}'");
        }

        try {
            $fn($this->connections);

            // Phase 1: PREPARE
            $votes = $this->prepare();

            if (in_array('abort', $votes)) {
                $this->rollback();
                return false;
            }

            // Phase 2: COMMIT (hamısı ready)
            $this->commit();
            return true;

        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    private function prepare(): array
    {
        $votes = [];

        foreach ($this->connections as $name => $pdo) {
            try {
                $pdo->exec("XA END '{$this->xid}_{$name}'");
                $pdo->exec("XA PREPARE '{$this->xid}_{$name}'");
                $votes[$name] = 'ready';
            } catch (\PDOException $e) {
                $votes[$name] = 'abort';
                \Log::error("XA PREPARE uğursuz: {$name}", ['error' => $e->getMessage()]);
            }
        }

        return $votes;
    }

    private function commit(): void
    {
        foreach ($this->connections as $name => $pdo) {
            $pdo->exec("XA COMMIT '{$this->xid}_{$name}'");
        }
    }

    private function rollback(): void
    {
        foreach ($this->connections as $name => $pdo) {
            try {
                $pdo->exec("XA ROLLBACK '{$this->xid}_{$name}'");
            } catch (\Exception $e) {
                \Log::warning("XA ROLLBACK xəta: {$name}", ['error' => $e->getMessage()]);
            }
        }
    }
}

// İstifadə — bank köçürmə: debit bir DB, credit digər DB
$manager = new XATransactionManager([
    'accounts_db' => ['dsn' => 'mysql:host=accounts-db;dbname=accounts', 'user' => '...', 'pass' => '...'],
    'ledger_db'   => ['dsn' => 'mysql:host=ledger-db;dbname=ledger',     'user' => '...', 'pass' => '...'],
]);

$manager->execute(function (array $connections) use ($fromId, $toId, $amount) {
    $connections['accounts_db']->exec("UPDATE accounts SET balance = balance - {$amount} WHERE id = '{$fromId}'");
    $connections['accounts_db']->exec("UPDATE accounts SET balance = balance + {$amount} WHERE id = '{$toId}'");
    $connections['ledger_db']->exec("INSERT INTO transactions (from_id, to_id, amount) VALUES ('{$fromId}', '{$toId}', {$amount})");
});
```

```php
<?php

// 2PC vs Saga müqayisəsi — nə zaman hansını seçmək
// ┌──────────────────────┬────────────────────┬──────────────────────┐
// │                      │        2PC         │        Saga          │
// ├──────────────────────┼────────────────────┼──────────────────────┤
// │ Consistency          │ Strong (ACID)      │ Eventual             │
// │ Availability         │ Aşağı (blocking)   │ Yüksək               │
// │ Performance          │ Yavaş (locks)      │ Sürətli              │
// │ Microservice uyğun   │ ❌                 │ ✅                   │
// │ Nə zaman             │ Eyni DB cluster    │ Fərqli servislər     │
// └──────────────────────┴────────────────────┴──────────────────────┘

// Nə zaman 2PC:
// - Eyni DB cluster (MySQL Galera, PostgreSQL BDR)
// - Kritik financial tx, eyni datacenter
// - Network reliable, network partition riski az

// Nə zaman Saga:
// - Fərqli microservice-lər, fərqli DB-lər
// - High availability lazımdır
// - Network unreliable ola bilər
```

## Praktik Tapşırıqlar

1. MySQL XA transaction yazın: iki fərqli DB-yə eyni anda insert edin; Phase 1-də bir DB-yi fail etdirin — ikinci DB-nin rollback etdiyini yoxlayın
2. `TwoPhaseCommitCoordinator` class yazın: `addParticipant()`, `execute()`; Phase 1-də biri abort versə hamısı rollback etsin; test yazın
3. Coordinator crash ssenarisi simulyasiyası: Phase 1 tamamlandı, Phase 2-dən əvvəl coordinator-ı kill edin; participant-ların locked qaldığını müşahidə edin; recovery protokolunu implement edin
4. 2PC vs Saga benchmark: eyni distributed əməliyyatı hər iki yol ilə implement edin; throughput, latency, lock duration ölçün; qərar: hansı kontekstdə hansı daha uyğundur

## Əlaqəli Mövzular

- [Saga Pattern](03-saga-pattern.md) — 2PC-nin mikroservis alternativı; eventual consistency
- [Outbox Pattern](04-outbox-pattern.md) — 2PC-siz event reliable publish etmək
- [Choreography vs Orchestration](11-choreography-vs-orchestration.md) — saga koordinasiya üsulları
- [Unit of Work](../laravel/14-unit-of-work.md) — tək DB əhatəsindəki transaction koordinasiyası
