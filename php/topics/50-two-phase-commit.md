# Two-Phase Commit (2PC) — Distributed Transactions

## Mündəricat
1. [Distributed Transaction Problemi](#distributed-transaction-problemi)
2. [2PC nədir?](#2pc-nədir)
3. [Mərhələlər](#mərhələlər)
4. [Uğursuzluq Ssenariləri](#uğursuzluq-ssenariləri)
5. [XA Transactions](#xa-transactions)
6. [2PC vs Saga](#2pc-vs-saga)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Distributed Transaction Problemi

```
Bir neçə sistemdə eyni anda dəyişiklik etmək lazımdır:

  ┌─────────────────┐    ┌─────────────────┐
  │   Order DB      │    │   Payment DB    │
  │                 │    │                 │
  │  order yaradıl  │    │  charge edilsin │
  │                 │    │                 │
  └─────────────────┘    └─────────────────┘
  
  Problem: Bu iki əməliyyat atomik deyil!
  Order yaranıb, payment uğursuz → inkonsistentlik
  Payment alınıb, order uğursuz → pul alındı amma order yoxdur
```

---

## 2PC nədir?

Two-Phase Commit — distributed sistemdə atomik commit/rollback protokolu.

```
Rollar:
  Coordinator (TX Manager): Prosesi idarə edir
  Participants (Resource Managers): DB-lər, message broker-lər

2 mərhələ:
  Phase 1: PREPARE (Voting Phase)
  Phase 2: COMMIT or ROLLBACK (Decision Phase)
```

---

## Mərhələlər

```
Phase 1 — PREPARE:

Coordinator                 DB1              DB2
    │                        │                │
    │─── PREPARE ───────────►│                │
    │                        │ (lock resources)│
    │                        │◄─── READY ─────│  
    │                        │                │
    │─── PREPARE ────────────────────────────►│
    │                                          │ (lock resources)
    │◄─── READY ───────────────────────────────│
    │
    │ Hər ikisi READY → Phase 2-yə keç
    │ Biri ABORT göndərsə → bütünü rollback et

Phase 2 — COMMIT:

Coordinator                 DB1              DB2
    │                        │                │
    │─── COMMIT ────────────►│                │
    │                        │ (unlock, commit)│
    │◄─── ACK ───────────────│                │
    │                        │                │
    │─── COMMIT ─────────────────────────────►│
    │                                          │ (unlock, commit)
    │◄─── ACK ─────────────────────────────────│
    │
    │ Transaction tamamlandı ✅
```

---

## Uğursuzluq Ssenariləri

```
Ssenari 1: Phase 1-də DB2 çöküb
  Coordinator: PREPARE göndərdi → DB1 READY, DB2 timeout
  Qərar: ROLLBACK hər ikisini
  DB1: locks azad edilir

Ssenari 2: Phase 2-də DB1 commit etdi, DB2 çöküb
  Coordinator: COMMIT göndərdi
  DB1: commit etdi ✅
  DB2: crash ❌ → recover olanda coordinator-a soruşur
  Coordinator: "COMMIT qərarını verdim" → DB2 commit edir ✅

Ssenari 3: Coordinator çöküb Phase 2-də
  DB1, DB2: PREPARE-ə READY verdilər, indi gözləyirlər
  DB1, DB2: LOCKS TUTULUB! (blocking!)
  Coordinator recover olana qədər heç kim davam edə bilmir
  → Bu 2PC-nin ən böyük problemidir: BLOCKING PROTOCOL
```

**2PC problemi:**

```
Coordinator çökürsə:
  - Bütün participants bloklanır (locks tutulub)
  - Coordinator recover olana qədər gözləyirlər
  - Bu production-da ciddi bottleneck-dir

Praktikada:
  - Koordinator yüksək availability tələb edir
  - Network partition zamanı split-brain
  - Mikroservislər üçün impraktikdir
```

---

## XA Transactions

XA — X/Open distributed transaction standard.

*XA — X/Open distributed transaction standard üçün kod nümunəsi:*
```php
// PHP PDO ilə XA (MySQL XA transactions)

class XATransactionManager
{
    private array $connections = [];
    private string $xid;
    
    public function __construct(array $dsns)
    {
        $this->xid = uniqid('xa_', true);
        
        foreach ($dsns as $name => $dsn) {
            $pdo = new PDO($dsn['dsn'], $dsn['user'], $dsn['pass']);
            $this->connections[$name] = $pdo;
        }
    }
    
    public function begin(): void
    {
        foreach ($this->connections as $name => $pdo) {
            // XA transaction başlat
            $pdo->exec("XA START '{$this->xid}_{$name}'");
        }
    }
    
    public function prepare(): array
    {
        $votes = [];
        
        foreach ($this->connections as $name => $pdo) {
            try {
                $pdo->exec("XA END '{$this->xid}_{$name}'");
                $pdo->exec("XA PREPARE '{$this->xid}_{$name}'");
                $votes[$name] = 'ready';
            } catch (\PDOException $e) {
                $votes[$name] = 'abort';
                Log::error("XA PREPARE uğursuz: $name", ['error' => $e->getMessage()]);
            }
        }
        
        return $votes;
    }
    
    public function commit(): void
    {
        foreach ($this->connections as $name => $pdo) {
            $pdo->exec("XA COMMIT '{$this->xid}_{$name}'");
        }
    }
    
    public function rollback(): void
    {
        foreach ($this->connections as $name => $pdo) {
            try {
                $pdo->exec("XA ROLLBACK '{$this->xid}_{$name}'");
            } catch (\Exception $e) {
                // Onsuz da rollback olub ola bilər
                Log::warning("XA ROLLBACK xəta: $name", ['error' => $e->getMessage()]);
            }
        }
    }
    
    public function execute(callable $fn): bool
    {
        $this->begin();
        
        try {
            $fn($this->connections);
            
            $votes = $this->prepare();
            
            if (in_array('abort', $votes)) {
                $this->rollback();
                return false;
            }
            
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}

// İstifadə
$manager = new XATransactionManager([
    'orders'   => ['dsn' => 'mysql:host=orders-db;dbname=orders', ...],
    'payments' => ['dsn' => 'mysql:host=payment-db;dbname=payments', ...],
]);

$success = $manager->execute(function (array $connections) {
    $connections['orders']->exec("INSERT INTO orders VALUES (...)");
    $connections['payments']->exec("INSERT INTO charges VALUES (...)");
});
```

---

## 2PC vs Saga

```
┌───────────────────┬────────────────────┬──────────────────────┐
│                   │        2PC         │        Saga          │
├───────────────────┼────────────────────┼──────────────────────┤
│ Consistency       │ Strong (ACID)      │ Eventual             │
│ Availability      │ Aşağı (blocking)   │ Yüksək               │
│ Performance       │ Yavaş (locks)      │ Sürətli              │
│ Complexity        │ Orta               │ Yüksək               │
│ Isolation         │ Güclü              │ Zəif (dirty reads)   │
│ Coordinator SPOF  │ Var (kritik)       │ Orchestr-da var      │
│ Compensate lazım  │ Yox (rollback var) │ Var                  │
│ Mikroservis uyğun │ ❌                 │ ✅                   │
│ Network partition │ Blocks             │ Resilient            │
│ Nə zaman istifadə │ Eyni DB cluster    │ Fərqli servislər     │
│                   │ Same datacenter    │ Distributed systems  │
└───────────────────┴────────────────────┴──────────────────────┘
```

**Nə zaman 2PC:**
- Eyni DB cluster-da (MySQL Galera, PostgreSQL BDR)
- Kritik financial transactions (banking)
- Az gecikdirmə tələb etmir
- Network reliable-dır

**Nə zaman Saga:**
- Fərqli servislərdə
- Yüksək availability lazımdır
- Mikroservis arxitekturası
- Network unreliable ola bilər

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Sadəşdirilmiş 2PC simulation (real XA əvəzinə)

class TwoPhaseCommitCoordinator
{
    private array $participants = [];
    private string $transactionId;
    
    public function __construct()
    {
        $this->transactionId = Str::uuid()->toString();
    }
    
    public function addParticipant(TransactionParticipant $participant): void
    {
        $this->participants[] = $participant;
    }
    
    public function execute(array $operations): bool
    {
        // Phase 1: Prepare
        $preparedParticipants = [];
        
        foreach ($this->participants as $index => $participant) {
            try {
                $participant->prepare($this->transactionId, $operations[$index]);
                $preparedParticipants[] = $participant;
                Log::info("2PC Prepare OK", ['participant' => $participant->name()]);
            } catch (\Exception $e) {
                Log::error("2PC Prepare FAIL", [
                    'participant' => $participant->name(),
                    'error' => $e->getMessage(),
                ]);
                
                // Hazırlanmış olanları rollback et
                foreach ($preparedParticipants as $prepared) {
                    try {
                        $prepared->rollback($this->transactionId);
                    } catch (\Exception $rollbackEx) {
                        Log::critical("2PC Rollback FAIL", [
                            'participant' => $prepared->name(),
                        ]);
                    }
                }
                
                return false;
            }
        }
        
        // Phase 2: Commit (hamı hazır)
        foreach ($this->participants as $participant) {
            try {
                $participant->commit($this->transactionId);
                Log::info("2PC Commit OK", ['participant' => $participant->name()]);
            } catch (\Exception $e) {
                // CRITICAL: Bəziləri commit etdi, bəziləri etmədi
                // Bu heuristic failure-dır — manual müdaxilə lazımdır!
                Log::critical("2PC Commit FAIL after partial commit!", [
                    'transaction_id' => $this->transactionId,
                    'participant' => $participant->name(),
                    'error' => $e->getMessage(),
                ]);
                
                // Monitoring/alerting yüksəlt
                throw new HeuristicFailureException(
                    "2PC partial commit: manual resolution required",
                    $this->transactionId
                );
            }
        }
        
        return true;
    }
}

interface TransactionParticipant
{
    public function prepare(string $txId, array $operation): void;
    public function commit(string $txId): void;
    public function rollback(string $txId): void;
    public function name(): string;
}
```

---

## İntervyu Sualları

**1. 2PC-nin 2 mərhələsini izah et.**
Phase 1 (Prepare/Voting): Coordinator hər participant-a "prepare" göndərir. Participant resursları lock edir, write-ahead log-a yazar, "READY" cavabı verir. Phase 2 (Commit/Rollback): Hamı READY verdi → coordinator COMMIT göndərir. Biri ABORT verdi → ROLLBACK göndərir.

**2. 2PC-nin ən böyük problemi nədir?**
Blocking protocol. Coordinator Phase 2-dən əvvəl çöksə, bütün participants lock tutaraq gözləyir. Bu resources bloklanır, sistem irəliləyə bilmir. Coordinator recover olana qədər hər şey donur.

**3. 2PC mikroservislər üçün niyə uyğun deyil?**
Mikroservislər fərqli proseslər/maşınlardır. Network unreliable, partitions possible. Coordinator SPOF olur. Blocking locks availability-ni azaldır. Müxtəlif texnologiyalar XA-nı dəstəkləməyə bilər. Saga daha praktikdir.

**4. XA transaction nədir?**
X/Open distributed transaction standard. Bir neçə resource manager (DB, message broker) üçün 2PC protokolunu standartlaşdırır. MySQL, PostgreSQL XA-nı dəstəkləyir. PHP PDO ilə `XA START/END/PREPARE/COMMIT/ROLLBACK` komandaları ilə işləyir.

**5. 2PC-yi nə zaman istifadə etmək məqsədəuyğundur?**
Eyni datacenter-də bir neçə DB (MySQL Galera Cluster), kritik financial transactions, network reliable olduqda, strong consistency vacib olduqda. Mikroservis distributed scenarios üçün Saga daha yaxşıdır.

**6. "Heuristic failure" nədir?**
Phase 2-də coordinator commit qərarı verdi, amma birinci participant commit etdi, ikincisi network kəsilməsi səbəbindən etmədi. Coordinator geri qayıtdıqda ikincisini commit etdirir — amma bu arada ikincinin özü müstəqil rollback etmiş ola bilər. Bu inkonsistentliyə "heuristic failure" deyilir. Manual DBA müdaxiləsi tələb edir. Bu 2PC-nin ən ciddi production riski hesab olunur.

**7. 3PC (Three-Phase Commit) 2PC-nin blocking problemini həll edirmi?**
3PC blocking problemini azaldır amma tam həll etmir. "Pre-commit" mərhələsi əlavə edilir: participants hazır olduqlarını bildirir, amma hələ commit etmirlər. Bu sayədə coordinator çöksə participants timeout-dan sonra öz qərarını verə bilər. Lakin network partition-da split-brain riski hələ var. Praktikada 3PC nadirən istifadə edilir — Paxos/Raft consensus alqoritmlərini (etcd, ZooKeeper) istifadə etmək daha sağlamdır.

---

## Anti-patternlər

**1. Mikroservis arxitekturasında 2PC tətbiq etmək**
Fərqli proseslərdə çalışan servisləri 2PC ilə koordinasiya etmək — coordinator SPOF olur, network partition halında bütün servisler lock-da donar. Mikroservislər üçün Saga Pattern istifadə edin: compensating transaction-larla eventual consistency.

**2. Coordinator single point of failure olaraq buraxmaq**
Coordinator-ın yüksək əlçatanlığı təmin edilmədən 2PC istifadəsi — coordinator Phase 2-dən əvvəl çöksə bütün iştirakçılar bloklanır. Coordinator-ı replikalayın (active-passive), ya da recovery protokolu tətbiq edin.

**3. Uzun sürən 2PC transaction-ları**
Phase 1-də resursları uzun müddət lock saxlamaq — digər əməliyyatlar bloklanır, throughput kəskin azalır. 2PC transaction-larını mümkün qədər qısa tutun; uzun iş axınları üçün Saga-ya keçin.

**4. XA transaction-ı hər yerdə default kimi istifadə**
Hər DB əməliyyatında XA başlatmaq — overhead böyüyür, performans aşağı düşür, halbuki əksər hallarda tək DB transactional yetərlidir. XA-nı yalnız bir neçə ayrı resource manager (fərqli DB, broker) arasında atomiklik tələb olunduqda istifadə edin.

**5. 2PC yerinə Saga-nın idempotency tələblərini göz ardı etmək**
Saga-ya keçdikdə compensating transaction-ların idempotent olmadığı aşkar edilir — retry zamanı double refund, double debit baş verir. Hər Saga addımı idempotent yazılsın: `saga_id` ilə işlənib-işlənmədiyini yoxlayın.

**6. Test mühitində 2PC-nin blocking davranışını sınaqdan keçirməmək**
Development-də coordinator heç vaxt çökmür — production-da coordinator crash ssenarisi heç test edilməyib. Chaos engineering ilə coordinator-ı dayandırın, sistemin necə davrandığını müşahidə edin, timeout/recovery mexanizmini doğrulayın.
