# Replication

> **Seviyye:** Advanced ⭐⭐⭐

## Replication nedir?

Data-ni bir database server-den (master/primary) diger server-lere (slave/replica) kopyalamaq prosesidir.

**Meqsedler:**
- **High Availability** - master crash olsa, replica devam edir
- **Read Scaling** - read query-leri replica-lara yonlendir
- **Backup** - replica-dan backup al, master-i yavaslama
- **Geographic Distribution** - istifadeciye yaxin replica

---

## Replication Novleri

### 1. Master-Slave (Primary-Replica)

En genis yayilmis model.

```
        [Master/Primary]
         (Read + Write)
        /      |      \
  [Replica1] [Replica2] [Replica3]
   (Read)     (Read)     (Read)
```

- **Master:** Butun write-lar buraya gedir
- **Replica-lar:** Yalniz read ucun, master-den data alir

**MySQL config (Master):**

```ini
# my.cnf (Master)
[mysqld]
server-id = 1
log-bin = mysql-bin          # Binary log aciq olmalidir
binlog-format = ROW          # ROW formati en etibarlisidir
```

**MySQL config (Replica):**

```ini
# my.cnf (Replica)
[mysqld]
server-id = 2
relay-log = relay-bin
read_only = 1                # Replica-ya yazma qadagasi
```

```sql
-- Replica-da master-e qosulma
CHANGE REPLICATION SOURCE TO
    SOURCE_HOST = '192.168.1.100',
    SOURCE_USER = 'replication_user',
    SOURCE_PASSWORD = 'password',
    SOURCE_LOG_FILE = 'mysql-bin.000001',
    SOURCE_LOG_POS = 154;

START REPLICA;
SHOW REPLICA STATUS\G
```

### 2. Master-Master (Multi-Primary)

Her iki node hem read, hem write qebul edir.

```
  [Master 1] <----> [Master 2]
  (Read+Write)      (Read+Write)
```

**Problemler:**
- **Write conflict:** Eyni row eyni anda her iki master-de update olunsa?
- **Auto-increment conflict:** Her iki master eyni ID yaradirsa?

```ini
# Master 1
auto_increment_offset = 1
auto_increment_increment = 2   # ID-ler: 1, 3, 5, 7, ...

# Master 2
auto_increment_offset = 2
auto_increment_increment = 2   # ID-ler: 2, 4, 6, 8, ...
```

**Tovsiye:** Master-Master-den qacinmaq daha yaxsidir. Complex ve conflict-prone-dur. Evezine ProxySQL ve ya HAProxy ile master failover istifade et.

### 3. Cascading Replication

```
[Master] --> [Replica1] --> [Replica2] --> [Replica3]
```

Master-in yukunu azaldir (yalniz 1 replica-ya gonderir).

---

## Replication Usullari

### Statement-Based Replication (SBR)

SQL statement-leri oldugu kimi replica-ya gonderilir.

```sql
-- Master-de icra olunan:
UPDATE orders SET status = 'shipped' WHERE created_at < '2024-01-01';
-- Eyni SQL replica-da da icra olunur
```

**Problemler:**
- `NOW()`, `RAND()`, `UUID()` ferqli neticeler verir
- Trigger-ler, stored procedure-lar ferqli davrana biler
- Non-deterministic function-lar problem yaradir

### Row-Based Replication (RBR)

Deyismis row-larin actual data-si gonderilir. **Tovsiye olunan usul.**

```
-- Master-de 5000 row update olunursa:
-- 5000 row-un evvelki ve sonraki veziyyeti gonderilir (binary log-da)
```

**Ustunlukleri:** Deterministic, etibarlidi.
**Cetinliyi:** Coxlu row deyisikliyinde binary log boyuyur.

### Mixed Replication

MySQL evvelce SBR cehdedir, problem olarsa avtomatik RBR-e kecir.

```ini
binlog-format = MIXED
```

---

## Replication Lag

Replica master-den geride qala biler. Bu **replication lag** adlanir.

```
Master:   [Write 1] [Write 2] [Write 3] [Write 4]
                                              ^-- indi buradaDir
Replica:  [Write 1] [Write 2]
                          ^-- 2 write geridedir (lag)
```

### Lag-in sebebleri:
1. Replica yavasdir (hardware, CPU)
2. Boyuk transaction-lar (tek ALTER TABLE milyon row-u deyisir)
3. Network yavasdir
4. Replica tek thread ile apply edir (MySQL 5.6-)

### Lag-i yoxlamaq:

```sql
-- MySQL
SHOW REPLICA STATUS\G
-- Seconds_Behind_Source: 5  (5 saniye geridedir)

-- PostgreSQL
SELECT now() - pg_last_xact_replay_timestamp() AS replication_lag;
```

### Lag problemleri ve hell yollari:

```php
// Problem: User write edir, sonra read edir, amma replica henuz yenilenmeyib
$order = Order::create([...]); // Master-e yazilir
return Order::find($order->id); // Replica-dan oxunur - tapilmaya biler!

// Hell 1: Write-dan sonra master-den oxu
$order = Order::create([...]);
return Order::on('mysql_write')->find($order->id); // Master-den oxu

// Hell 2: Sticky connection (Laravel)
// Session-da write olubsa, o session ucun read-lari da master-den et
```

**Laravel Read/Write Splitting:**

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['replica1.db.com', 'replica2.db.com'],
    ],
    'write' => [
        'host' => 'master.db.com',
    ],
    'sticky' => true, // Write-dan sonra eyni request-de read-lar master-den gedir
],
```

---

## Failover

Master crash olsa ne olar?

### Manual Failover

```bash
# 1. Replica-ni master-e cevirme
mysql> STOP REPLICA;
mysql> RESET REPLICA ALL;
mysql> SET GLOBAL read_only = 0;

# 2. Diger replica-lari yeni master-e yonlendir
mysql> CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'new_master_ip', ...;
mysql> START REPLICA;
```

### Automatic Failover

Tools:
- **MySQL Group Replication / InnoDB Cluster** - MySQL-in oz cluster helli
- **Orchestrator** - MySQL failover tool
- **Patroni** - PostgreSQL failover (etcd/consul ile)
- **HAProxy / ProxySQL** - Proxy seviyyesinde yonlendirme

---

## PostgreSQL Replication

### Streaming Replication (Physical)

```ini
# postgresql.conf (Primary)
wal_level = replica
max_wal_senders = 10

# pg_hba.conf
host replication repl_user 192.168.1.0/24 md5
```

```bash
# Replica yaratma
pg_basebackup -h primary_ip -U repl_user -D /var/lib/postgresql/data -R
# -R flag standby.signal yaradir ve primary_conninfo elave edir
```

### Logical Replication (PostgreSQL 10+)

Table seviyyesinde, muxtelif schema-lar arasinda replicate ede biler.

```sql
-- Publisher (Primary)
CREATE PUBLICATION my_pub FOR TABLE orders, users;

-- Subscriber (Replica)
CREATE SUBSCRIPTION my_sub
    CONNECTION 'host=primary_ip dbname=mydb user=repl_user'
    PUBLICATION my_pub;
```

**Ustunluyu:** Selective replication (yalniz lazimi table-lar), ferqli versiyalar arasinda.

---

## Interview suallari

**Q: Replication lag problemini nece hell edersin?**
A: 1) Read-after-write consistency ucun: write-dan sonra master-den oxu (sticky session). 2) Lag monitoring elave et (alert 5+ saniye lag-da). 3) Multi-threaded replication istifade et. 4) Boyuk transaction-lari kicik hisselere bol.

**Q: Master-Slave vs Master-Master?**
A: Master-Slave sadedir, conflict yoxdur, cogu ssenari ucun kifayetdir. Master-Master write scaling verir amma conflict hell etmek cetindir. Eslinde cox az ssenari Master-Master teleb edir - read scaling ucun replica-lar, write scaling ucun sharding daha yaxsidir.

**Q: Synchronous vs Asynchronous replication?**
A: **Async:** Master COMMIT edir, replica-ni gozlemir. Suretlidir, amma master crash olsa data itmesi mumkundur. **Sync:** Master replica-nin confirm etmesini gozleyir. Data itkisi yoxdur, amma yavasdır (network latency). **Semi-sync:** En az 1 replica confirm etsin. MySQL semi-sync plugin ile destekleyir.
