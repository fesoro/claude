# Database Replication (Senior ⭐⭐⭐)

## İcmal
Replication — database-in kopyasını bir və ya bir neçə server-də saxlamaq prosesidir. Yüksək availability, read scaling, disaster recovery üçün istifadə olunur. Senior interview-larda replication types, consistency models, failover strategiyaları soruşulur.

## Niyə Vacibdir
Hər production sisteminin bir nöqtəsindən replication keçir. İnterviewer bu sualla sizin distributed data consistency-ni, "eventual consistency" trade-off-larını, real failover ssenariyini başa düşüb-anladığınızı yoxlayır. "Read replica əlavə etdik" yetərli deyil — niyə, nə zaman, hansı problemlər yarandı soruşulur. Read-your-writes problemi, replication lag monitoring, slot-un disk dolma riski — bunları bilmək Senior səviyyəsinə daxildir.

## Əsas Anlayışlar

- **Primary (Master)**: Yazma sorğuları qəbul edən əsas node. Single point of truth — bütün write-lar buradan keçir.
- **Replica (Slave/Standby)**: Primary-nin kopyası — adətən oxuma üçün istifadə olunur. Hot standby: sorğu qəbul edir. Warm standby: hazır amma sorğu qəbul etmir. Cold standby: start etmək lazımdır.
- **Streaming Replication**: PostgreSQL-in native replication — WAL record-larını real-time TCP üzərindən göndərir. Ən geniş istifadə olunan üsul.
- **Logical Replication**: Row-level dəyişiklikləri göndərir. Selective table replication — yalnız lazım olan cədvəllər. Cross-major-version, cross-database mümkün. Selective table subscription.
- **Synchronous Replication**: Primary commit etmədən əvvəl ən az bir replica-nın WAL-ı disk-ə yazdığının (flush) təsdiqi gözlənilir. Strong consistency, amma latency artır.
- **Asynchronous Replication**: Primary commit edir, replica sonra tətbiq edir. Sürətli, amma replication lag riski var. Default PostgreSQL mode.
- **Replication Lag**: Replica-nın primary-dən nə qədər geridə olduğu. Milliseconds — normal. Saniyə/dəqiqə — problem. `pg_stat_replication` ilə izlənilir.
- **Read Replica**: Oxuma sorğularını ayrı server-ə yönləndirmək. Primary-nin yükünü azaldır. Amma lag var.
- **Failover**: Primary düşdükdə replica-nı primary-yə çevirmək. Manual: administrator replika-nı promote edir. Automatic: Patroni, Repmgr.
- **Automatic Failover**: Patroni (etcd/Consul/ZooKeeper), AWS RDS Multi-AZ, Google Cloud HA — health check + leader election + automatic promote.
- **Read-Your-Writes Consistency**: User öz yazdığını oxumalıdır. Replica lag-dan asılı olaraq: yeni yazılan record replica-da görünmeyebilir. Kritik problem.
- **Semi-Synchronous Replication**: MySQL — ən az bir replica-nın cavabını gözlə, hamısını deyil. PostgreSQL-də `synchronous_standby_names = '1 (replica1, replica2)'` ilə.
- **Replication Slots**: PostgreSQL-in WAL-ı replica oxuyana qədər disk-də saxlaması. Replica uzun müddət offline olsa WAL toplanır, disk dolur. Monitoring vacibdir.
- **Cascading Replication**: Replica, başqa bir replica-nın upstream-i ola bilər — çox replica tier. Primary → Replica1 → Replica2.
- **Active-Active Replication**: Hər iki node yazma qəbul edir. Conflict resolution lazımdır. Çox mürəkkəb, az istifadə olunur. BDR (Bi-Directional Replication) PostgreSQL extension-ı.
- **PITR (Point-in-Time Recovery)**: WAL arxivindən istənilən nöqtəyə geri qayıtmaq. Replication deyil, amma WAL üzərindən işləyir.
- **Replication vs Backup**: Replication HA üçündür. Backup disaster recovery üçün. Replication backup-ın yerini tutmur — corrupt data da replikasiya olunur.

## Praktik Baxış

**Interview-da yanaşma:**
- Sync vs async trade-off-u izah edin: "Financial data üçün sync, analytics üçün async"
- Read replica əlavə etdiyinizdə "read-your-writes" problemini qeyd edin
- Failover RTO/RPO-nu soruşun: "Manual failover 5-10 dəq, Patroni ilə otomatik 30 saniyə"

**Follow-up suallar interviewerlər soruşur:**
- "Replication lag-ı necə izlərdiniz? Alert nə zaman atılmalıdır?"
- "Sync replication-da primary-nin performance-ına təsiri?"
- "Active-active replication-ın problemi nədir?" — Write conflict, complexity
- "Replication slot nədir, disk dolma riski?"
- "`sticky = true` Laravel-də nə edir?"
- "Read-your-writes problemi nədir, necə həll edərdiniz?"

**Ümumi candidate səhvləri:**
- "Read replica əlavə etdik, problem həll oldu" — lag, consistency-ni qeyd etməmək
- Replication failover-i backup kimi qəbul etmək — replikasyon backup deyil!
- Replication slot-ların disk dolma riskini bilməmək
- `sticky = true`-nun nə etdiyini bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Read-your-writes problem-i bilmək və həll yolu: "Critical reads primary-dən, analytics replica-dan"
- Patroni / AWS RDS Multi-AZ automatic failover-i izah etmək
- Replication vs backup fərqini aydın bilmək
- Replication slot + disk dolma risk-ini qeyd etmək

## Nümunələr

### Tipik Interview Sualı
"E-commerce platformanızda read-heavy workload var — sifariş sayfasını sürətləndirmek üçün nə edərdiniz? Trade-off-ları nələrdir?"

### Güclü Cavab
"Read replica əlavə edərdim. Order listing, product catalog, user dashboard kimi oxuma sorğularını replica-ya yönləndirərdim; order placement, payment kimi kritik yazmaları primary-də saxlardım.

Trade-off 1 — Replication lag: Asynchronous replication-da replica primary-dən geridə qala bilər. Müştəri sifariş verən kimi 'sifarişlərim' sayfasına baxsa — sifariş replica-da görünməyə bilər. Bu 'read-your-writes' problemidir.

Həll: Laravel-də `sticky = true` — eyni HTTP request-də write varsa, sonrakı read-lər primary-dən gəlir. Kritik sonrakı sorğular üçün `DB::connection('pgsql_primary')` ilə explicit primary selection.

Trade-off 2 — Infrastructure complexity: İki server, failover, lag monitoring əlavə olunur. Sadəlik itirilir.

Trade-off 3 — Read replica sorğu növü: Sadəcə oxuma. Write da gedirsə — Laravel xəta verir.

Monitoring: `pg_stat_replication` ilə lag-ı izləyirəm, 10 saniyədən çox olsa alert atıram.

Əlavə olaraq: Replication, backup deyil. Corrupt data da replikasiya olunur — ayrıca backup lazımdır."

### Kod Nümunəsi — Primary Konfiqurasiyası

```bash
# postgresql.conf — Primary
wal_level = replica          # WAL məlumatları replication üçün
max_wal_senders = 5          # Max 5 replica qoşula bilər
max_replication_slots = 5    # Max 5 replication slot
wal_keep_size = 1024         # MB — slot olmadan WAL-ı bu qədər saxla

# Async replication (default) — sürətli
synchronous_commit = off

# Sync replication — strong consistency
# synchronous_commit = on
# synchronous_standby_names = '1 (replica1)'  # Ən az 1 replica ACK gözlə

# pg_hba.conf — Primary
# Replica server-ə replication icazəsi ver
host replication replicator replica-ip/32 md5

# Yeni replication user yarat
CREATE USER replicator REPLICATION LOGIN PASSWORD 'secure_password';
```

```bash
# Replica-nı hazırla
pg_basebackup \
  -h primary-ip \
  -U replicator \
  -D /var/lib/postgresql/data \
  -P -Xs -R              # -R: standby.signal + primary_conninfo yaradır

# postgresql.conf — Replica
hot_standby = on         # Read sorğularına icazə ver
hot_standby_feedback = on  # Replica-nın VACUUM-u bloklamasın

# Replica-nı başlat
systemctl start postgresql

# Status yoxla
psql -c "SELECT pg_is_in_recovery();"  # → true (replica)
```

### Kod Nümunəsi — Replication Monitoring SQL

```sql
-- Primary-də replication statusunu izlə
SELECT
    client_addr,
    state,                    -- streaming, startup, catchup
    sent_lsn,                 -- Primary-nin göndərdiyi WAL
    write_lsn,                -- Replica-nın disk-ə yazdığı WAL
    flush_lsn,                -- Replica-nın disk-ə flush etdiyi
    replay_lsn,               -- Replica-nın tətbiq etdiyi WAL
    pg_wal_lsn_diff(sent_lsn, replay_lsn) AS lag_bytes,
    write_lag,                -- Write latency
    flush_lag,                -- Flush latency
    replay_lag,               -- Replay latency
    sync_state                -- sync, async, quorum
FROM pg_stat_replication;

-- Replica-da lag-ı saniyə ilə gör
SELECT
    now() - pg_last_xact_replay_timestamp() AS replication_lag,
    pg_is_in_recovery()                     AS is_replica,
    pg_last_wal_receive_lsn()               AS received_lsn,
    pg_last_wal_replay_lsn()                AS replayed_lsn;

-- Replication slot-ları yoxla — disk dolma riski
SELECT
    slot_name,
    active,
    pg_size_pretty(
        pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)
    ) AS retained_wal_size
FROM pg_replication_slots
ORDER BY pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn) DESC;
-- retained_wal_size çox böyüksə — replica uzun müddət bağlıdır!
-- Alert: 10GB-dan böyüksə disk dolma riski

-- Alert query (Grafana/Prometheus üçün)
SELECT
    EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp()))::int AS lag_seconds
FROM pg_stat_replication
WHERE client_addr = 'replica-ip'::inet;
-- lag_seconds > 10 → Alert
```

### Kod Nümunəsi — Laravel Read/Write Split

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',

    // Read — replica-lar (load balanced)
    'read' => [
        ['host' => env('DB_READ_HOST_1'), 'port' => env('DB_PORT', 5432)],
        ['host' => env('DB_READ_HOST_2'), 'port' => env('DB_PORT', 5432)],
    ],

    // Write — primary
    'write' => [
        'host' => env('DB_WRITE_HOST'),
        'port' => env('DB_PORT', 5432),
    ],

    // Write-dan sonra eyni request-də primary-dən oxu
    // Read-your-writes problemini bir HTTP request daxilində həll edir
    'sticky' => true,

    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],

// Usage
// Avtomatik: write → primary, read → replica
User::create([...]);            // → Primary
User::where('id', 1)->first(); // → sticky=true: əvvəl write var → Primary
// ...
User::where('email', '...')->get(); // → Replica (yeni request-dirsə)

// Explicit primary seçimi — kritik oxumalar üçün
$recentOrder = DB::connection('pgsql')
    ->table('orders')
    ->where('id', $newOrderId)
    ->first();
// Yaxud: named connection
$recentOrder = Order::on('pgsql_primary')->find($newOrderId);

// Read-your-writes problemi həlli — cache yanaşması
public function placeOrder(array $data): Order
{
    $order = DB::transaction(function () use ($data) {
        return Order::create($data);
    });

    // Yeni order-i cache-ə yaz — replica lag zamanı buradan oxun
    Cache::put("order:{$order->id}", $order, 30); // 30 saniyə

    return $order;
}

public function getOrder(int $id): Order
{
    // Cache-dən yoxla — replica-ya getməzdən əvvəl
    return Cache::remember("order:{$id}", 30, fn() =>
        Order::find($id) // Bu artıq replica-dan gələ bilər
    );
}
```

### Kod Nümunəsi — Patroni (Automatic Failover)

```yaml
# /etc/patroni/patroni.yml
scope: postgres-ha-cluster
namespace: /db/
name: node1

restapi:
  listen: 0.0.0.0:8008
  connect_address: 192.168.1.10:8008

etcd3:
  hosts: etcd1:2379,etcd2:2379,etcd3:2379

bootstrap:
  dcs:
    ttl: 30                    # Leader lease müddəti
    loop_wait: 10              # Health check intervalı
    retry_timeout: 10
    maximum_lag_on_failover: 1048576  # 1MB lag-dan çox olan replica promote edilmir
    master_start_timeout: 300
    synchronous_mode: false    # Async replication
    postgresql:
      use_pg_rewind: true      # Failover sonrası köhnə primary-i rebuild et
      use_slots: true
      parameters:
        max_connections: 200
        wal_level: replica
        hot_standby: on

postgresql:
  listen: 0.0.0.0:5432
  connect_address: 192.168.1.10:5432
  data_dir: /data/postgresql
  bin_dir: /usr/lib/postgresql/15/bin
  authentication:
    replication:
      username: replicator
      password: replication_password
    superuser:
      username: postgres
      password: postgres_password

# HAProxy config (client connection-ı yönləndirir)
# frontend postgres_write
#   bind *:5000
#   default_backend postgres_primary
# backend postgres_primary
#   option httpchk GET /primary    ← Patroni REST API check
#   server node1 192.168.1.10:5432 check port 8008
#   server node2 192.168.1.11:5432 check port 8008
```

### Attack/Failure Nümunəsi — Replication Slot Disk Dolma

```
Ssenari: Replica offline oldu, disk doldu

1. Replication slot yaradılıb: 'replica1'
2. Replica serveri qəza keçirdi → offline
3. Primary WAL yazır, slot-u saxlayır: "replica1 henüz oxumadı"
4. WAL faylları silinmir — toplanır
5. 2 gün sonra: /var/lib/postgresql/data/pg_wal/ → 500GB!
6. Disk doldu → Primary da crash etdi!

Monitoring:
SELECT slot_name, pg_size_pretty(
    pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)
) AS retained_wal_size
FROM pg_replication_slots;
-- retained_wal_size = 490GB → ALARM!

Tez həll (emergency):
SELECT pg_drop_replication_slot('replica1');
-- Replica geri gəldikdə pg_basebackup ilə yenidən qurmaq lazımdır

Düzgün yanaşma:
-- Monitoring: retained_wal_size > 10GB → Alert
-- max_slot_wal_keep_size = 10GB -- Slot-un saxlaya biləcəyi max WAL
-- Bu limitdən çox olsa slot deaktiv olur (data loss riski, lakin disk qorunur)
ALTER SYSTEM SET max_slot_wal_keep_size = '10GB';
SELECT pg_reload_conf();
```

### İkinci Nümunə — Active-Active vs Primary-Replica Müqayisəsi

```
Primary-Replica (active-passive) replication:
+ Sadə konfiqurasiya
+ Conflict yoxdur — yalnız 1 yazma nodu
+ Strong consistency (sync mode-da)
- Primary düşsə write unavailable (failover müddəti)
- Read yalnız replica-dan scale olunur

Active-Active replication:
+ Hər node yazma qəbul edir — yüksək availability
+ Geographic distribution mümkün
- Conflict resolution mürəkkəbdir
  "İki node eyni row-u eyni anda update etdi"
  "Hansını qəbul edəcəyik? Last-write-wins? Merge?"
- Consistency zəifdir (eventual consistency)
- Implementation çox mürəkkəb (BDR, CockroachDB, Spanner)

Nə vaxt active-active?
- Multi-region yazma tələb olunursa
- Latency toleransı çox aşağıdırsa
- Conflict resolution strategiyası aydındırsa

Nə vaxt primary-replica?
- Əksər production sistemləri — daha sadə, daha az xəta
- Strong consistency lazımdırsa
- Team BDR kimi mürəkkəb sistemə hazır deyilsə
```

## Praktik Tapşırıqlar

- Docker Compose ilə 1 primary + 1 replica PostgreSQL cluster qurun — `hot_standby=on`
- Replication lag-ı `pg_stat_replication`-dan izləyin: replica-da uzun query çalışdırın, primary-dəki lag-ı görün
- Laravel-də read/write split konfiqurasiya edin, `DB::getQueryLog()` ilə hansı sorğunun haradan getdiyini doğrulayın
- Replication slot yaradın, replika-nı durdurun, primary-dəki WAL saxlama artımını izləyin
- `pg_basebackup` ilə replica-nı bərpa edin — PITR test edin
- `sticky = true` ilə read-your-writes problemi düzəlibmi yoxlayın: write et → dərhal read → response-da yeni data varmı?

## Əlaqəli Mövzular
- `11-write-ahead-logging.md` — Replication-ın əsası WAL-dır
- `09-connection-pooling.md` — Read replica-ya ayrı pool — PgBouncer ilə
- `13-optimistic-pessimistic-locking.md` — Replica lag + optimistic lock problemi
- `16-database-migration-strategies.md` — Schema migration ilə replication — replica-nı necə update etmək?
