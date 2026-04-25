# High Availability & Failover (Patroni, Orchestrator, Aurora) (Senior)

## HA goals: RTO ve RPO

| Termin | Menasi | Misal |
|--------|--------|-------|
| **RTO** (Recovery Time Objective) | Failover ne qeder cekmelidir | "5 dq icinde geri qalxmali" |
| **RPO** (Recovery Point Objective) | Ne qeder data itkisi qebul olunur | "Maksimum 1 saniyelik" |
| **MTTR** (Mean Time To Recovery) | Real ortalama recovery vaxti | Realdiya 12 dq |
| **MTBF** (Mean Time Between Failures) | Iki failure arasi ortalama vaxt | 6 ay |

**Trade-off:** Sinx replication = RPO=0, amma latency artir. Async = RPO>0, amma tez.

---

## Availability tier-leri

| Tier | Architecture | RTO | RPO | Cost |
|------|--------------|-----|-----|------|
| **Single instance** | 1 server | Hours-days | Last backup | $ |
| **Primary + Standby** | 1 master + 1 replica | 5-30 min (manual) | Saniyeler | $$ |
| **Auto-failover** | Patroni/Orchestrator | 30 san - 2 dq | Saniyeler | $$$ |
| **Multi-AZ** | RDS Multi-AZ, Aurora | < 1 dq | 0 (sync) | $$$$ |
| **Multi-region** | Cross-region replica + DNS failover | Dq-saatler | Bir nece saniye | $$$$$ |

---

## Failover types

```
Manual failover:    Engineer ozu kecid edir (en safe, en yavas)
Automatic failover: Cluster manager ozu (split-brain riski)
Planned switchover: Maintenance ucun, zero-downtime mumkundur
Disaster recovery:  Region down, manual + uzun
```

### Split-brain problemi

Iki node "men master-em" deyirse — data divergence yaranir. Hellini quorum + fencing/STONITH verir.

---

## PostgreSQL Patroni

`Patroni` — PostgreSQL ucun ən populyar HA solution. Etcd/Consul/ZooKeeper distributed lock istifade edir, leader election edir.

**Architecture:**

```
+----------+     +----------+     +----------+
| etcd-1   |<--->| etcd-2   |<--->| etcd-3   |  (consensus)
+----------+     +----------+     +----------+
     ^                ^                ^
     |                |                |
+----v----+      +----v----+      +----v----+
| Patroni |      | Patroni |      | Patroni |
| pg-1    |      | pg-2    |      | pg-3    |
| (leader)|<====>| (replica)|<===>| (replica)|
+---------+      +---------+      +---------+
     ^
     | (HAProxy/PgBouncer routes here)
```

```yaml
# patroni.yml
scope: postgres-prod
namespace: /service/
name: pg-node-1

restapi:
  listen: 0.0.0.0:8008

etcd3:
  hosts: etcd1:2379,etcd2:2379,etcd3:2379

bootstrap:
  dcs:
    ttl: 30
    loop_wait: 10
    retry_timeout: 10
    maximum_lag_on_failover: 1048576  # 1MB max lag for promotion
    synchronous_mode: true             # RPO=0
    synchronous_mode_strict: false     # Allow async if no replica
  postgresql:
    parameters:
      wal_level: replica
      hot_standby: 'on'
      max_wal_senders: 10
      synchronous_commit: 'on'

postgresql:
  listen: 0.0.0.0:5432
  data_dir: /var/lib/postgresql/data
  authentication:
    replication:
      username: replicator
      password: xxx
```

**Patroni features:**
- Auto-failover (etcd lease ile)
- Manual failover: `patronictl failover --master pg-1`
- Switchover (graceful): `patronictl switchover`
- REST API: `GET /master`, `GET /replica` (HAProxy istifade ucun)

### HAProxy ile traffic routing

```ini
# haproxy.cfg
listen postgres-write
    bind *:5000
    option httpchk GET /master
    http-check expect status 200
    server pg-1 pg-1:5432 check port 8008
    server pg-2 pg-2:5432 check port 8008 backup
    server pg-3 pg-3:5432 check port 8008 backup

listen postgres-read
    bind *:5001
    option httpchk GET /replica
    balance roundrobin
    server pg-1 pg-1:5432 check port 8008
    server pg-2 pg-2:5432 check port 8008
    server pg-3 pg-3:5432 check port 8008
```

---

## Diger PostgreSQL HA tools

| Tool | Approach | Plus | Minus |
|------|----------|------|-------|
| **Patroni** | DCS (etcd/consul) | Production-tested, flexible | Setup murekkebdir |
| **repmgr** | Custom daemon | Sade, EnterpriseDB destekli | Auto-failover az reliable |
| **pg_auto_failover** | Monitor node + keeper | Sade setup | Az feature, monitor SPOF |
| **Stolon** | etcd-based | Cloud-native, K8s-friendly | Az populyar |

---

## MySQL Orchestrator (GitHub)

`Orchestrator` — MySQL replication topology manager + auto-failover.

```
+---------------+
| Orchestrator  |  (HTTP UI + API, MySQL-de saxlanir state)
+-------+-------+
        |
        | (SSH / MySQL connection)
        v
+-------+-------+----------+
| Master        | Replicas  |
| db-master     | db-r1, r2 |
+---------------+----------+
```

**Features:**
- Topology discovery (auto)
- Visual UI (drag-drop replicas)
- Pseudo-GTID (yoxdursa)
- Auto-failover with hooks
- Anti-flapping protection

```ini
# orchestrator.conf.json
{
  "MySQLTopologyUser": "orc_topology",
  "MySQLTopologyPassword": "xxx",
  "RecoveryPeriodBlockSeconds": 3600,
  "RecoverMasterClusterFilters": ["*"],
  "PromotionIgnoreHostnameFilters": [],
  "PreFailoverProcesses": [
    "echo 'Master {failureCluster} failover starting' | slack-notify"
  ],
  "PostFailoverProcesses": [
    "echo 'Promoted: {successorHost}' | slack-notify"
  ]
}
```

| MySQL HA Tool | Status |
|---------------|--------|
| **Orchestrator** | Aktiv (GitHub) |
| **MHA (Master HA)** | Deprecated (2018) |
| **MySQL Group Replication** | Aktiv (Oracle), MySQL 5.7+ |
| **MySQL InnoDB Cluster** | Group Repl + Router + Shell |
| **Galera (Percona/MariaDB)** | Aktiv, multi-master sync |
| **ProxySQL** | Connection routing (Orc + ProxySQL pop combo) |

---

## Galera Cluster (multi-master)

3+ node, **synchronous replication**, hamisi yaza biler.

**Plus:** RPO=0, hec bir failover lazim deyil (her node master).
**Minus:** Yazma latency artir (bir node-un yazmasi quorum gozleyir), conflict (deadlock) artir, write-throughput tekce node kimi.

```ini
# /etc/mysql/my.cnf (Percona XtraDB Cluster)
[mysqld]
wsrep_provider=/usr/lib/galera/libgalera_smm.so
wsrep_cluster_address="gcomm://node1,node2,node3"
wsrep_node_address="node1"
wsrep_node_name="node1"
wsrep_sst_method=xtrabackup-v2
binlog_format=ROW
default_storage_engine=InnoDB
```

---

## AWS Aurora — fast failover

Aurora storage layer **compute-dan ayrilib**. 6 kopya 3 AZ-de, quorum-based writes (4/6).

```
+----------------+       +-----------------+
| Aurora Writer  |<----->| Aurora Reader 1 |
| (instance)     |       | (instance)      |
+--------+-------+       +--------+--------+
         |                        |
         v                        v
+--------+------------------------+--------+
|     Distributed Storage Layer (6x)        |
|     (separate from compute)               |
+-------------------------------------------+
```

**Failover:** ~30 saniye (ACU-suz Multi-AZ RDS-de 60-120 san).

**Aurora Multi-Master:** 2 writer (eyni vaxt), conflict resolution app-da. Limited regions, MySQL only.

**Aurora Global Database:** Cross-region replica, < 1 san lag, RPO 1 san, RTO 1 dq promote.

```php
// Laravel: Aurora endpoint istifade et
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => 'mycluster.cluster-xxx.us-east-1.rds.amazonaws.com',  // writer
        // ...
    ],
    'mysql_read' => [
        'driver' => 'mysql',
        'host' => 'mycluster.cluster-ro-xxx.us-east-1.rds.amazonaws.com',  // reader
    ],
],
```

---

## Quorum writes

3 node-da yazma: minimum N/2+1 acknowledgment ister (split-brain qarsisi).

```
N=3 -> quorum=2 (yazma 2 node tasdiq edirse OK)
N=5 -> quorum=3
N=7 -> quorum=4
```

PostgreSQL `synchronous_standby_names`:

```ini
# postgresql.conf
synchronous_standby_names = 'ANY 2 (replica1, replica2, replica3)'
# Hansisa 2 replica acknowledge etmeli
```

---

## Fencing / STONITH

"Shoot The Other Node In The Head" — kohne master-i mecburi shutdown et ki, split-brain olmasin.

| Method | Necedir |
|--------|---------|
| **Power fence** | IPMI ile elektrik kessin |
| **Network fence** | Switch port disable (kohne master traffic almasin) |
| **VIP migration** | Virtual IP yeni master-e kecirilsin |
| **DNS failover** | TTL-i kicik (~10 san), Route53 failover |

---

## Client-side connection retry

App-da bele connection error olarsa retry pattern.

```php
// Laravel database.php
'mysql' => [
    // ...
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
    ]) : [],
    'sticky' => true,           // Read-after-write consistency
    'read_timeout' => 5,
],
```

**Custom retry logic:**

```php
use Illuminate\Database\QueryException;

function dbRetry(callable $fn, int $maxAttempts = 5): mixed
{
    $attempt = 0;
    $delay = 100;  // ms
    
    while (true) {
        try {
            return $fn();
        } catch (QueryException $e) {
            $attempt++;
            
            // Connection error mi?
            $code = $e->getCode();
            $isTransient = in_array($code, ['HY000', '2002', '2006', '2013']);
            
            if (!$isTransient || $attempt >= $maxAttempts) {
                throw $e;
            }
            
            // Reconnect
            DB::reconnect();
            
            // Exponential backoff + jitter
            usleep($delay * 1000 + random_int(0, 100) * 1000);
            $delay = min($delay * 2, 5000);
        }
    }
}

// Istifade
$user = dbRetry(fn() => User::find($id));
```

### Laravel sticky reads

```php
// Read replica olanda, eyni request-de yazidan sonra read primary-den (consistency)
'mysql' => [
    'read' => ['host' => ['replica1', 'replica2']],
    'write' => ['host' => 'primary'],
    'sticky' => true,  // Read-after-write same request -> primary
],
```

---

## Failover dryrun ve testing

Production HA-ni production-da test et!

```bash
# Patroni: switchover (planned)
patronictl -c /etc/patroni.yml switchover --master pg-1 --candidate pg-2

# Crash test: master-i kill et
docker kill pg-master  # ya da: sudo systemctl stop postgresql

# Orchestrator: graceful master takeover
orchestrator-client -c graceful-master-takeover -i db-master.example.com

# Chaos engineering
gremlin attack --target pg-master --type shutdown --duration 60s
```

**Game day:** komanda ile birlikde failover test et — runbook hazirla, post-mortem yaz.

---

## Service mesh integration

K8s-de PostgreSQL operator (Zalando, CrunchyData) Patroni-ni avtomatik configure edir.

```yaml
# Zalando postgres-operator
apiVersion: "acid.zalan.do/v1"
kind: postgresql
metadata:
  name: acid-prod
spec:
  numberOfInstances: 3
  postgresql:
    version: "15"
    parameters:
      synchronous_commit: "on"
  resources:
    limits:
      memory: 8Gi
      cpu: 4
  patroni:
    synchronous_mode: true
    pg_hba:
      - hostssl all all 0.0.0.0/0 md5
```

Service: `acid-prod` (write) ve `acid-prod-repl` (read) avtomatik yaranir.

---

## Interview suallari

**Q: PostgreSQL HA setup-ini sifirdan necə qurarsan?**
A: Zalando postgres-operator (K8s) ya da Patroni + etcd istifade edirem. 3 PostgreSQL node, 3 etcd node (quorum). `synchronous_mode: true` (RPO=0). HAProxy front-de — `/master` health check ile yalniz leader-e write yonlendirir, `/replica` ile read traffic balanc olunur. Failover testleri her aybiri game day-de edirik.

**Q: Sync vs async replication-da hansini secersen?**
A: Trade-off RPO/latency arasinda. **Sync**: RPO=0, amma her commit replica acknowledgment gozleyir — write latency artir, replica down olarsa primary blok olur (bunun ucun `synchronous_mode_strict: false`). **Async**: latency dusuk, amma failover-da N saniyelik data itkisi mumkun. Financial app-da sync, analytics-de async qebul olunur. PostgreSQL-de **quorum sync** (`ANY 2 (r1, r2, r3)`) yaxsi balansdir.

**Q: Split-brain nedir, nece qabaqlamaq olar?**
A: Iki node ozunu master sayanda, paralel yazilir, data divergence yaranir. Qabaqlama: 1) DCS (etcd/consul) ile leader lease — yalniz tek leader. 2) Quorum: N/2+1 acknowledgment ister. 3) STONITH: kohne master fenced (network/power kessin). 4) VIP/DNS yalniz aktiv leader-e yonelsin. Patroni butun bunlari edir.

**Q: Aurora niye adi RDS Multi-AZ-den daha tez failover edir?**
A: Aurora-da storage compute-dan ayrilib — failover yalniz **compute instance** kecidi-dir, data eyni saxlanir (6x repl 3 AZ-de). RDS Multi-AZ-de standby ayri instance-dur, failover-da DNS deyisilir + standby-de WAL replay biter — 60-120 san. Aurora ~30 san. Aurora replica-lar promotion-a hazir, instant.

**Q: Laravel app-da master failover olanda app crash olur. Necə hell etmek olar?**
A: 1) `sticky: true` — read-after-write consistency (read query yazidan sonra primary-den). 2) Connection retry middleware/wrapper — `2006 MySQL server has gone away` error-da `DB::reconnect()` + retry. 3) Health check endpoint app-da: dependency olarak DB ping et, K8s readiness probe ile traffic yalniz saglam pod-lara getsin. 4) PgBouncer/ProxySQL ortada — failover-da connection re-route edir, app-a impact azaldir.
