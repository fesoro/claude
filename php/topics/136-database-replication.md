# Database Replication (Senior)

## Mündəricat
1. [Replication nədir?](#replication-nədir)
2. [Replication Növləri](#replication-növləri)
3. [MySQL Replication Mexanizmi](#mysql-replication-mexanizmi)
4. [Read/Write Splitting](#readwrite-splitting)
5. [Laravel Konfiqurasiyası](#laravel-konfiqurasiyası)
6. [Replication Lag Problemi](#replication-lag-problemi)
7. [Failover](#failover)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Replication nədir?

*Bu kod replication arxitekturasını, məqsədlərini və vizual sxemini göstərir:*

```
// Bu kod replication arxitekturasını və məqsədlərini izah edir
Replication — DB məlumatlarını bir serverdən digərinə kopyalamaq.

Məqsədlər:
  ✅ Read scalability (oxuma yükünü paylaş)
  ✅ High availability (master çöksə replica devreye girer)
  ✅ Backup (replica-dan backup al, master-ı dayandırmadan)
  ✅ Analytics (ağır sorğular replica-ya yönləndir)
  ✅ Geographic distribution (müxtəlif region-larda replika)

Arxitektura:
┌───────────────┐
│    Master     │ ← bütün WRITE əməliyyatları
│  (Primary)    │
└───────┬───────┘
        │ Binary Log (binlog)
        │ replika edir
   ┌────┴────┐
   │         │
┌──▼───┐ ┌───▼──┐
│Repli │ │Repli │ ← bütün READ əməliyyatları
│ca 1  │ │ca 2  │
└──────┘ └──────┘
```

---

## Replication Növləri

```
// Bu kod sinxron və asinxron replication növlərini müqayisə edir
1. Asynchronous Replication (default MySQL)
   Master → commit → client-ə ACK
            ↓ (async)
          Replica (gecikmə ola bilər)
   
   ✅ Sürətli write
   ❌ Replica lag ola bilər
   ❌ Master çöksə son data itirilə bilər

2. Semi-synchronous Replication
   Master → ən azı 1 replica acknowledge edəndə commit
   
   ✅ Data safety yüksəkdir
   ❌ Write latency artır (replica ACK-ni gözlə)

3. Synchronous Replication (MySQL Cluster / Galera)
   Master → bütün replica-lar commit edəndə commit
   
   ✅ Zero data loss
   ❌ Yavaş (hamının ACK-ni gözlə)
   ❌ Network partition-da block olur
```

---

## MySQL Replication Mexanizmi

```
// Bu kod MySQL binary log əsaslı replication mexanizmini göstərir
Master:
  1. Write əməliyyatı gəlir
  2. Binary Log (binlog)-a yazılır
  3. InnoDB engine-ə yazılır
  4. Client-ə ACK

Replica:
  1. IO Thread: Master-ın binlog-unu oxuyur
  2. Relay Log-a yazır
  3. SQL Thread: Relay Log-u icra edir
  4. Replica DB yenilənir

┌─────────────────────────────────────────────────┐
│ Master                                          │
│  InnoDB Storage ←──── Write Query               │
│       ↓                                         │
│  Binary Log (binlog)                            │
└──────────────────────┬──────────────────────────┘
                       │ (binlog stream)
┌──────────────────────▼──────────────────────────┐
│ Replica                                         │
│  IO Thread → Relay Log → SQL Thread             │
│                              ↓                  │
│                         InnoDB Storage          │
└─────────────────────────────────────────────────┘

Replication Format:
  STATEMENT: SQL statement-ları kopyalanır (az log, amma edge cases)
  ROW: Dəyişən row-lar kopyalanır (daha etibarlı, daha çox log)
  MIXED: Situasiyaya görə ikisini istifadə edir
```

---

## Read/Write Splitting

```
// Bu kod read/write əməliyyatlarının düzgün yönləndirilməsini izah edir
Read replika-larını düzgün istifadə et:

Write (INSERT/UPDATE/DELETE):
  → Master-a göndər

Read (SELECT):
  → Replica-ya göndər (load balance)

Exception: Write-dan sonra dərhal read
  → Replication lag var!
  → Master-dan oxu (read-after-write consistency)
```

---

## Laravel Konfiqurasiyası

*Laravel Konfiqurasiyası üçün kod nümunəsi:*
```php
// Bu kod Laravel-də read/write connection ayrımının konfiqurasiyasını göstərir
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    
    'read' => [
        'host' => [
            env('DB_REPLICA1_HOST', '10.0.0.2'),
            env('DB_REPLICA2_HOST', '10.0.0.3'),
        ],
        'port' => 3306,
    ],
    
    'write' => [
        'host' => env('DB_MASTER_HOST', '10.0.0.1'),
        'port' => 3306,
    ],
    
    // Sticky: Write-dan sonra eyni request-də read master-dan oxu
    'sticky' => true,
    
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset'  => 'utf8mb4',
],
```

**`sticky` parametri:**

```php
// Bu kod sticky parametrinin replication lag problemini necə həll etdiyini göstərir
// sticky: true olmadan (problem):
$user = User::create(['name' => 'Ali']);  // Master-a yazıldı
$found = User::find($user->id);  // Replica-dan oxudu → tapılmadı! (lag)

// sticky: true ilə (həll):
// Write olduqdan sonra eyni HTTP request-də master-dan oxunur
$user = User::create(['name' => 'Ali']);  // Master-a yazıldı
$found = User::find($user->id);  // Master-dan oxunur ✅
// Növbəti request-də replica-dan oxunur
```

**Manual connection seçimi:**

```php
// Bu kod explicit olaraq master və ya replica connection seçimini göstərir
// Həmişə master-dan oxu (kritik əməliyyatlar)
$balance = DB::connection('mysql::write')
    ->table('accounts')
    ->where('id', $accountId)
    ->value('balance');

// Açıq şəkildə replica
$stats = DB::connection('mysql::read')
    ->table('orders')
    ->selectRaw('COUNT(*) as count, SUM(total) as revenue')
    ->where('created_at', '>=', now()->subDays(30))
    ->first();

// Eloquent model-də
class Order extends Model
{
    // Bu model həmişə master-dan oxusun
    public function newQuery()
    {
        return parent::newQuery()->useWritePdo();
    }
}
```

**Multiple read connections:**

```php
// Bu kod replica-lar arasında round-robin yük balanslaşdırması həyata keçirir
// Custom load balancer
class ReplicaLoadBalancer
{
    private array $replicas;
    private int $currentIndex = 0;
    
    public function __construct(array $replicaConnections)
    {
        $this->replicas = $replicaConnections;
    }
    
    public function getConnection(): string
    {
        // Round-robin
        $connection = $this->replicas[$this->currentIndex];
        $this->currentIndex = ($this->currentIndex + 1) % count($this->replicas);
        return $connection;
    }
}
```

---

## Replication Lag Problemi

```
// Bu kod replication lag problemini və həll yollarını izah edir
Problem: Master-a yazıldı, replica-da hələ yoxdur.

Ssenari:
  1. User payment tamamladı (master-a yazıldı)
  2. Redirect: Order confirmation page
  3. Order status replica-dan oxundu → "pending" görünür
  4. User: "Pulum alındı ama order hələ pending?!"

Həllər:

1. Sticky Sessions (Laravel-in sticky: true)
   → Eyni request-də write-dan sonra master oxu

2. Monotonic Read Consistency
   → Hər user üçün eyni replica-dan oxu
   → Az lag "geri gedişini" görməz user

3. Write-after-Read for critical paths
   → Payment confirmation: həmişə master-dan oxu

4. Lag monitoring
   → SHOW SLAVE STATUS → Seconds_Behind_Master
   → Threshold keçsə → alerting
```

*→ Threshold keçsə → alerting üçün kod nümunəsi:*
```php
// Bu kod write-dan sonra master-dan oxumağı zəmanətləyən helper sinfi göstərir
// Read-after-write consistency üçün helper
class ConsistentReadService
{
    public function findAfterWrite(string $model, $id): mixed
    {
        // Short delay əvəzinə, master-dan oxu
        return DB::connection('mysql_write')
            ->table((new $model)->getTable())
            ->find($id);
    }
    
    public function waitForReplication(callable $readFn, int $maxWaitMs = 500): mixed
    {
        $startTime = microtime(true);
        
        while (true) {
            $result = $readFn();
            if ($result !== null) return $result;
            
            $elapsed = (microtime(true) - $startTime) * 1000;
            if ($elapsed > $maxWaitMs) {
                throw new ReplicationLagException('Replication lag timeout');
            }
            
            usleep(50 * 1000); // 50ms gözlə
        }
    }
}
```

---

## Failover

```
// Bu kod master çöküşündə failover prosesini addım-addım izah edir
Master çöküşü:
  1. Replica-lardan biri yeni Master olur (promotion)
  2. Digər replica-lar yeni Master-a qoşulur
  3. Application yeni Master-a yönləndirilir

Alətlər:
  MySQL: MHA (Master High Availability), Orchestrator
  Cloud: AWS RDS Multi-AZ (auto failover ~1-2 dəq)
        Google Cloud SQL, Azure Database

Manual failover:
  1. Replica-nı stop et: STOP REPLICA
  2. Master et: RESET MASTER
  3. Digərlərini yeni master-a qoşdur: CHANGE MASTER TO ...
  
Automatic failover:
  ProxySQL + Orchestrator → DNS/VIP dəyişikliyi
  Application → eyni endpoint, arxada master dəyişir
```

**Laravel-də failover:**

```php
// Bu kod failover sonrası Laravel-in yeni master endpoint-ə keçişini göstərir
// .env
DB_MASTER_HOST=db-master.internal
DB_REPLICA1_HOST=db-replica1.internal
DB_REPLICA2_HOST=db-replica2.internal

// Failover sonrası (ops dəyişir):
DB_MASTER_HOST=db-replica1.internal  # Eski replica1 indi master

// Application restart lazım deyil əgər ProxySQL/HAProxy istifadə edilsə
// ProxySQL → arxada topology dəyişir, application eyni endpoint istifadə edir
```

---

## İntervyu Sualları

**1. Database replication nədir, nə üçün istifadə edilir?**
Master DB-nin məlumatlarını bir və ya bir neçə Replica DB-yə kopyalamaq. Məqsəd: Read scalability (replica-lardan oxu), HA (master çöksə replica devreye girer), Analytics (ağır sorğular replica-ya). Write hər zaman master-a gedir.

**2. Async vs sync replication fərqi nədir?**
Async: Master commit edir, replica sonra catch up edir. Sürətli amma lag var, data loss riski. Sync: Hamı commit edəndə ACK. Data safe amma yavaş. Semi-sync: ən azı 1 replica ACK — ortaq yol.

**3. Replication lag nədir, necə idarə edilir?**
Master-da yazılan data replica-ya gecikmə ilə gəlir. Həllər: sticky: true (eyni request-də write-dan sonra master-dan oxu), kritik əməliyyatlarda master-dan oxu, lag monitoring (Seconds_Behind_Master), ProxySQL routing.

**4. Laravel-də read/write splitting necə konfiqurasiya edilir?**
database.php-da read/write blokları: read array-ında replica host-ları, write-da master. sticky: true ilə write-dan sonra eyni request-də master-dan oxunur. DB::connection('mysql::write') ilə explicit master seçimi.

**5. Master failover prosesi necə işləyir?**
Master çöküşündə: replica-lardan biri master-a promote edilir (ən çox binlog-u olanı). Digər replica-lar yeni master-a reconfigurasiya edilir. Application yeni master endpoint-ə yönləndirilir. Otomasiya üçün: MHA, Orchestrator, AWS RDS Multi-AZ (auto, ~1-2 dəq).

---

## Anti-patternlər

**1. Bütün sorğuları replica-dan oxumaq**
Write-dan dərhal sonra həmin datanı replica-dan oxumaq — replication lag səbəbindən köhnə data qaytarılır, istifadəçi öz yazdığını görmür. `sticky: true` konfiqurasiyası ilə eyni request-dəki write-dan sonra master-dan oxuyun; kritik əməliyyatlarda həmişə master seçin.

**2. Replication lag-ı monitoring etməmək**
`Seconds_Behind_Master` dəyəri saatlarla artır, komanda xəbər tutmur — istifadəçilər köhnə data görür, anomaliyalar gec aşkar edilir. Lag metrikini Prometheus/Grafana-ya qoşun, threshold keçildikdə (məs: >30s) alert göndərilsin.

**3. Async replication ilə kritik financial data-nı replica-dan oxumaq**
Bank balansı, ödəniş statusu kimi critical data-nı lag-lı replica-dan oxumaq — müştəriyə yanlış balans göstərilir, double charge riski yaranır. Kritik financial sorğular həmişə master-dan oxusun; replica yalnız analytics, raporlama, axtarış üçün istifadə edilsin.

**4. Master failover-i manual prosedur olaraq saxlamaq**
Master çöküşündə DBA-nın əl ilə promote etməsini gözləmək — downtime dəqiqələrlə uzanır, data itkisi riski artar. MHA, Orchestrator, ya da AWS RDS Multi-AZ ilə avtomatik failover qurun; manual müdaxilə yalnız fallback ssenarilər üçün qalsın.

**5. Replica-ya write göndərməyə imkan vermək**
Laravel konfiqurasiyası səhv olduqda `UPDATE` sorğusu replica-ya gedir — master ilə uyuşmazlıq yaranır, replica master-a catch-up edəndə data itirilir. Replica-ları `read_only=ON` ilə konfiqurasiya edin; application səviyyəsindəki routing isə DB-level qorumanın əvəzini tutmur.

**6. Çoxlu replica-ya eyni yük vermək**
Bütün read sorğuları bərabər bölünür, lakin ağır analytic sorğular digər sorğuları gecikdirir. Replica-ları istifadə növünə görə ayırın: bir replica OLAP/raporlama üçün, digərləri normal application read-ləri üçün; ProxySQL ilə ağır sorğuları izolə edin.
