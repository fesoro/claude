# Cloud Databases (RDS, Aurora, Cloud SQL, PlanetScale, Neon, Supabase) (Senior)

## Niye Managed Database?

Self-hosted Postgres/MySQL ile yaşamaq mümkündür, amma əməliyyat baş ağrısı çoxdur:

| Ish | Self-hosted | Managed |
|-----|-------------|---------|
| Backup planlamasi | Cron + pg_dump + S3 | Avtomatik daily + PITR |
| Failover | Patroni / repmgr setup | 1-click ya da avtomatik |
| Patch & upgrade | Manual, downtime | In-place, çox vaxt zero-DT |
| Monitoring | Prometheus + Grafana setup | CloudWatch / built-in |
| HA replica | Manual config | Multi-AZ checkbox |
| Connection limit tuning | pg_bouncer setup | Built-in proxy |
| Security patches | DBA mesuliyyəti | Provider |

> **Tradeoff:** Daha az nəzarət, daha yüksək qiymet, vendor lock-in. Amma kicik-orta komanda ucun **vacht qənaeti çox böyükdür**.

---

## AWS RDS (Relational Database Service)

AWS-in əsas managed DB xidmeti. Postgres, MySQL, MariaDB, Oracle, SQL Server destekleyir.

### Esas Konseptler

```
DB Instance         = Database server (m5.large, r6i.xlarge ...)
DB Subnet Group     = VPC-de hansi subnet-lerde isleyir
Parameter Group     = my.cnf / postgresql.conf settings
Option Group        = Pluginler, extensions
Security Group      = Firewall rules (inbound 5432/3306)
```

### Multi-AZ vs Read Replica

```
Multi-AZ (HA):
  Primary (eu-west-1a) ─sync─> Standby (eu-west-1b)
  Failover otomatik 60-120s
  Standby read EDIRMIR (passive)
  Cost: 2x

Read Replica:
  Primary (eu-west-1a) ─async─> Replica (eu-west-1b, eu-west-1c)
  Read scaling (lag 100ms-seconds)
  Manual promote (failover deyil)
  Cost: hər replica üçün ayrıca
```

### RDS Misal (Terraform)

```hcl
resource "aws_db_instance" "main" {
  identifier     = "myapp-prod"
  engine         = "postgres"
  engine_version = "16.3"
  instance_class = "db.r6g.large"

  allocated_storage     = 100
  max_allocated_storage = 1000     # autoscaling storage
  storage_type          = "gp3"
  storage_encrypted     = true

  multi_az          = true          # HA
  backup_retention_period = 14      # 14 gun PITR
  backup_window     = "03:00-04:00"
  maintenance_window = "sun:04:00-sun:05:00"

  parameter_group_name = aws_db_parameter_group.main.name
  vpc_security_group_ids = [aws_security_group.db.id]

  performance_insights_enabled = true
  monitoring_interval          = 60
  enabled_cloudwatch_logs_exports = ["postgresql"]

  iam_database_authentication_enabled = true   # IAM token auth
}
```

### IAM Auth (parolsuz baglanma)

```bash
# Token al (15 deqiqe gecerlidir)
TOKEN=$(aws rds generate-db-auth-token \
  --hostname myapp.cluster-xxx.eu-west-1.rds.amazonaws.com \
  --port 5432 --region eu-west-1 \
  --username myapp_user)

PGPASSWORD=$TOKEN psql "host=... user=myapp_user dbname=myapp sslmode=require"
```

```php
// Laravel-de — periodic token refresh
// app/Providers/AppServiceProvider.php
public function boot()
{
    if (app()->environment('production')) {
        config(['database.connections.pgsql.password' => $this->getRdsToken()]);
    }
}

private function getRdsToken(): string
{
    return Cache::remember('rds_token', 600, function () {
        $client = new \Aws\Rds\RdsClient([...]);
        return $client->createPresignedRdsAuthToken([
            'Region' => 'eu-west-1',
            'Endpoint' => env('DB_HOST') . ':5432',
            'Username' => env('DB_USERNAME'),
        ]);
    });
}
```

---

## AWS Aurora — RDS-in Boyuk Qardaşı

Aurora = MySQL/Postgres uyumlu, AMMA storage layer **tamamilə yenidən yazilib**.

### Memarlıq

```
Compute (1-15 instance, share storage)
   │
   ├──> Storage Layer (6 replica, 3 AZ)
   │      Auto-scaling 10GB → 128TB
   │      Eyni storage-i butun compute oxuyur
   │
   └──> Failover ~30s (storage-dan oxumaqda davam edir)
```

**Faydalari:**
- Read replica daha sürətli (storage paylasilir, async lag yox)
- Failover sürətli (~30s, RDS multi-AZ ~2 dəq)
- Storage avtomatik 10GB → 128TB (provision lazim deyil)
- Backup storage-də saxlanir (pulsuz, separate cost yoxdur)
- 5x sürət MySQL-den, 3x Postgres-den (claim)

**Aurora variantlari:**

| Tip | Use case | Bill |
|-----|----------|------|
| **Aurora Provisioned** | Stabile traffic | Saatlik instance |
| **Aurora Serverless v1** | Dev/test (deprecated) | Auto pause |
| **Aurora Serverless v2** | Burst-prone prod | ACU bazli (0.5-128) |
| **Aurora Global Database** | Multi-region | Primary + secondary cluster |

### Aurora Global Database

```
Primary cluster (eu-west-1) ──physical replica──> Secondary (us-east-1)
                                                  Lag: <1 saniye
                                                  Read-only

Failover (planned/disaster): Secondary → Primary, RTO ~1 minute
```

### Aurora Serverless v2

```hcl
resource "aws_rds_cluster" "aurora" {
  engine         = "aurora-postgresql"
  engine_version = "16.3"
  engine_mode    = "provisioned"

  serverlessv2_scaling_configuration {
    min_capacity = 0.5    # 0.5 ACU = ~1GB RAM, 0.25 vCPU
    max_capacity = 8.0    # peak vaxt avtomatik artir
  }
}
```

> **Qiymət:** 1 ACU ≈ $0.12/saat. 0.5 min cap = ~$87/ay (24×7). Burst-də 8 ACU çıxsa, o saat üçün 16x bill.

---

## GCP Cloud SQL ve Azure Database

```
GCP Cloud SQL:
  - MySQL, Postgres, SQL Server
  - HA: regional, automatic failover (~60s)
  - Read replica: same region, cross-region
  - Backup: automatic, PITR 7 gun

Azure Database for PostgreSQL/MySQL:
  - Flexible Server (yeni) vs Single Server (legacy)
  - Zone-redundant HA
  - Burstable, General Purpose, Memory Optimized tier
```

---

## PlanetScale — Vitess as a Service

MySQL + Vitess (YouTube-un sharding sistemi). Esas özəllik: **branching**.

### Branching (Git for DB)

```bash
pscale branch create myapp dev-feature-x
# Schema dəyişikliklərini bu branch-də et

pscale shell myapp dev-feature-x
> ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255);

# Production-a deploy
pscale deploy-request create myapp dev-feature-x main
pscale deploy-request deploy myapp 1

# Heç bir downtime — gh-ost esasli online schema change
```

### Connection — Laravel

```bash
DB_CONNECTION=mysql
DB_HOST=aws.connect.psdb.cloud
DB_PORT=3306
DB_USERNAME=xxxxxxxx
DB_PASSWORD=pscale_pw_xxxxxxxx
MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem
```

> **Tələ:** PlanetScale `LONG_QUERY` -ni 30s-də bağlayır. ETL ucun chunk-lara bol. Foreign key 2024-den evvel qadagan idi, indi destekleyir amma sharded ortamda diqqetli ol.

---

## Neon — Serverless Postgres

PostgreSQL **storage / compute ayrılma** ile yenidən qurulub. Branching var (Postgres üçün).

### Esas xüsusiyyetlər

- **Branching (CoW)** — db-ni copy-on-write ile saniye icinde branch et
- **Scale to zero** — kompüter işləmirsə, dayanir (0$ idle)
- **Bottomless storage** — S3-tipli object storage, sonsuz
- **Time travel** — 7-30 gun ərzində istənilən vaxta qayit
- **Postgres native** — extension-lar (pgvector, postgis, ...) işləyir

### Pricing

```
Free tier:    0.5 GB storage, 191 saat compute / ay
Launch:       $19/ay — 10 GB, autoscale
Scale:        $69/ay — 50 GB
Business:     fixed contract
```

### Aurora vs Neon

| Cehet | Aurora Serverless v2 | Neon |
|-------|---------------------|------|
| Engine | MySQL/Postgres uyumlu | Yalniz Postgres |
| Min cost | ~$87/ay (0.5 ACU 24/7) | $0 (scale-to-zero) |
| Idle | Hələ də xərc gedir | Free |
| Branching | Yox (DB clone, baha) | Native CoW (saniye) |
| Storage layer | Aurora storage | Neon Pageserver + S3 |
| Cold start | <1s | ~500ms-1s |
| Best for | Stabile workload | Variable / dev / preview env |

---

## Supabase — Postgres + Backend Suite

PostgreSQL + Auth + Realtime + Storage + Edge Functions. Open-source Firebase alternativi.

### Bilməli Olduqlar

- **Database**: PG 15-16, full SQL, RLS (Row Level Security)
- **PostgREST**: schema-dan otomatik REST API
- **Realtime**: Postgres logical replication → WebSocket
- **Auth**: GoTrue (email, OAuth, magic link, SSO)
- **Storage**: S3-uyumlu (avatar, file)
- **Edge Functions**: Deno serverless

### Laravel-de

```php
// Supabase = Postgres connection-dan başqa bir şey deyil
DB_CONNECTION=pgsql
DB_HOST=db.xxxxxxxx.supabase.co
DB_PORT=5432
DB_USERNAME=postgres
DB_PASSWORD=your-password

// Connection pool ucun pgBouncer endpoint istifade et:
DB_HOST=db.xxxxxxxx.supabase.co
DB_PORT=6543   // Transaction pooling
```

---

## RDS Proxy — Connection Pooling

Lambda + RDS-də connection storm problemini hell edir. Postgres-de pgBouncer kimi isleyir.

```
Lambda 1000 concurrent → RDS Proxy → 50 backend conn → Postgres
                       (multiplex)
```

```hcl
resource "aws_db_proxy" "main" {
  name                = "myapp-proxy"
  engine_family       = "POSTGRESQL"
  vpc_subnet_ids      = var.subnet_ids
  vpc_security_group_ids = [aws_security_group.proxy.id]
  role_arn            = aws_iam_role.proxy.arn

  auth {
    auth_scheme = "SECRETS"
    secret_arn  = aws_secretsmanager_secret.db.arn
    iam_auth    = "REQUIRED"
  }
}
```

> **Qiymət:** Vault-l 0.015 / vCPU / saat. Aurora 4 vCPU = ~$43/ay extra.

---

## Read Replica Routing — Laravel

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'read' => [
        'host' => [
            'replica1.cluster-xxx.eu-west-1.rds.amazonaws.com',
            'replica2.cluster-xxx.eu-west-1.rds.amazonaws.com',
        ],
    ],
    'write' => [
        'host' => 'primary.cluster-xxx.eu-west-1.rds.amazonaws.com',
    ],
    'sticky' => true,  // request-de write olarsa, sonraki read primary-den
    'port' => 5432,
    'database' => 'myapp',
    'username' => 'app',
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8',
],

// Eloquent
$user = User::find(1);              // → replica
User::create([...]);                // → primary
$user2 = User::find(2);             // sticky=true → primary (eyni request)

// Force read/write
DB::connection('pgsql::read')->select(...);
DB::connection('pgsql')->statement(...);  // primary
```

> **Tələ:** Read replica lag (100ms-3s). User profile yeniledikden sonra dərhal göstərmək lazimdirsa, primary-dən oxu (sticky=true bunu otomatlasdirir).

---

## Backup, PITR, Disaster Recovery

| Concept | Aciqlama |
|---------|----------|
| **Snapshot** | Diskinin daimi kopiyasi (manual ya da automatic) |
| **PITR (Point-in-time recovery)** | Hər saniyəyə qayıtmaq (transaction log + snapshot) |
| **Cross-region backup** | Snapshot-i basqa region-a copy |
| **Read Replica → Promote** | Replica-ni standalone DB et |
| **RPO** | Recovery Point Objective — neçe data itirile biler? |
| **RTO** | Recovery Time Objective — ne qeder vaxtda qayıdacaq? |

```
Aurora:        RPO ~1s, RTO 30s (multi-AZ failover)
RDS Multi-AZ:  RPO ~0,  RTO 60-120s
RDS Single-AZ: RPO 5min, RTO 10-30min (snapshot restore)
Aurora Global: RPO <1s, RTO ~1min (cross-region)
```

---

## Cost Optimization Trick-leri

1. **Reserved Instance (RDS)** — 1-3 il commit, 30-70% endirim
2. **Aurora I/O Optimized** — read/write IO-ya qiymət vermir, instance bahalı (heavy IO ucun yaxsi)
3. **gp3 storage** — gp2-dan ucuz + IOPS ayri scale
4. **Storage autoscaling** — ehtiyyatdan ortuq alma
5. **Performance Insights ile bottleneck tap** — gərəksiz instance upgrade etme
6. **Idle env-leri durdur** — non-prod RDS gece dayandirma (Lambda + EventBridge)
7. **Aurora Serverless v2** — variable workload üçün
8. **Read replica only o vaxt elave et ki, primary CPU 70%+ olsun**

---

## Secrets Management

```php
// AWS Secrets Manager + auto-rotate
// app/Providers/AppServiceProvider.php
public function register()
{
    if (app()->environment('production')) {
        $client = new \Aws\SecretsManager\SecretsManagerClient([
            'region' => 'eu-west-1',
            'version' => 'latest',
        ]);

        $secret = json_decode(
            $client->getSecretValue(['SecretId' => 'prod/myapp/db'])->get('SecretString'),
            true
        );

        config([
            'database.connections.pgsql.username' => $secret['username'],
            'database.connections.pgsql.password' => $secret['password'],
        ]);
    }
}
```

---

## Cloud DB Müqayisə Cedveli

| DB | Min ay | Branching | Scale-to-zero | Postgres uyumlu | Multi-region |
|----|--------|-----------|---------------|-----------------|--------------|
| **RDS Postgres** | ~$15 (db.t4g.micro) | Yox | Yox | Native | Read replica |
| **Aurora Postgres** | ~$87 (t4g.medium) | Yox (clone) | v2 yox tam | Native | Global DB |
| **Aurora Serverless v2** | ~$87 (0.5 ACU 24/7) | Yox | Yox (min 0.5 ACU) | Native | Yox (provisioned-de var) |
| **Cloud SQL** | ~$10 (shared core) | Yox | Yox | Native | Cross-region replica |
| **PlanetScale** | $0 (Hobby 5GB) | Beli, native | Beli (idle Hobby) | Yalniz MySQL | Multi-region replica |
| **Neon** | $0 (free 0.5GB) | Beli (CoW) | Beli | Native | Read replica |
| **Supabase** | $0 (free 500MB) | PR-bazli | Yox (paused) | Native | Tek region (Pro tek) |

---

## Interview suallari

**Q: Aurora ile RDS Postgres arasinda secim necedir?**
A: RDS Postgres = adi Postgres + managed (backup, patch, HA). Aurora = MySQL/Postgres uyumlu interfeys, AMMA storage tamamile yeniden yazilib (6 replica 3 AZ-de paylasilib). Aurora faydalari: failover ~30s (RDS ~120s), read replica daha sürətli, storage avtomatik scale. Cost: Aurora ~20% baha. Production OLTP üçün Aurora; budget tight kicik prod ucun RDS Postgres.

**Q: Neon-un branching xususiyyeti nece isleyir?**
A: Neon storage layer-i copy-on-write esaslıdır. Branch yaratmaq metadata əməliyyatıdır — saniye iceri yeni "DB" alirsiniz, eyni storage page-leri share olunur. Yalniz dəyişiklik olduqda yeni page yazılır. Bu PR preview environment, dev sandbox, A/B test üçün ideal — hər branch real data ilə test edə bilər, amma production storage-i təsir etmir.

**Q: RDS-də read replica lag necə hell olunur Laravel-de?**
A: Laravel `read/write` connection split + `sticky=true` istifade etmek. Sticky write etdikden sonra eyni request-de oxumalari primary-yə yonləndirir, lag problemini gizledir. Daha incə yanasma: critical path-de `DB::connection('pgsql')->table(...)` ile force primary, qalan oxumalari replica-ya. Real-time daha vacibdirsa Aurora Global Database (lag <1s) ya da CDC + cache lazimdir.

**Q: PlanetScale niye foreign key-i evvel qadagan etmisdi?**
A: PlanetScale Vitess esaslidir, sharding ile isleyir. Cross-shard FK enforcement çətin və bahadir — hər insert üçün başqa shard-da check lazimdir. Performance səbəbiylə default qadagan idi (application-da check tovsiye olunurdu). 2024-de online FK destek elave etdiler, amma sharded ortamda eyni shard-da olan rowlar üçün işləyir. Best practice — sharding key-i (məs. customer_id) ortaq olan related table-lar.

**Q: Aurora Serverless v2 hansi case ucun, ne vaxt provisioned daha yaxsidir?**
A: Serverless v2 — variable / spiky workload (gece az, gunduz cox), staging/dev environment, yeni başlayan startup (kapasita bilmir). 0.5 ACU min cost ~$87/ay. Provisioned — stabile yuksek workload (24/7 ortalama 4+ vCPU), peak/baseline ratio dushukdur. Provisioned + Reserved Instance 1-il commit ile 40% endirim alirsan; Serverless burada bahaliya basa gelir.
