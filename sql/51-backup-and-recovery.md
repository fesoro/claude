# Backup & Recovery (Middle)

## Niyə senior üçün kritikdir?

Backup olmasa — **şirkət batır**. Test olunmamış backup — **yoxdur**. Production incident-lərinin 30%+-ı **"biz bunu heç test etməmişik"**-lə bitir. Senior mühəndis `RPO` və `RTO` biznes dilinə tərcümə edə bilməlidir, backup/restore pipeline-i dizayn edib dövri drill (təcrübə) keçirməlidir.

---

## Backup növləri

### 1. Logical Backup (SQL dump)

SQL statement-ləri şəklində export.

```bash
# MySQL
mysqldump -u root -p --single-transaction --routines --triggers myapp > backup.sql

# Tek table
mysqldump -u root -p myapp users orders > partial_backup.sql

# Butun database-ler
mysqldump -u root -p --all-databases > full_backup.sql

# Compressed + paralel (mydumper)
mydumper -h localhost -u root -p pass -B myapp -o /backup/ --compress --threads=8

# PostgreSQL
pg_dump -U postgres -d myapp -F custom -f backup.dump    # Custom format (sıxılmış)
pg_dump -U postgres -d myapp -F directory -j 8 -f backup/  # Paralel (8 thread)
pg_dumpall -U postgres -f all_databases.sql                # Butun DB-ler + roles
```

**Restore:**

```bash
# MySQL
mysql -u root -p myapp < backup.sql
gunzip < backup.sql.gz | mysql -u root -p myapp
myloader -h localhost -u root -B myapp -d /backup/ --threads=8  # Paralel restore

# PostgreSQL
pg_restore -U postgres -d myapp -j 8 backup.dump  # Paralel (8 thread)
psql -U postgres -d myapp -f backup.sql
```

**Üstünlüklər:** Oxunaqlı format, fərqli DB versiyalarına restore, partial backup, schema changes araşdırmaq üçün diffable.
**Mənfi tərəflər:** Yavaş (böyük DB-lərdə saatlar sürə bilər), restore zamanı index rebuild, CPU-intensive.

**Böyük ölçüdə (100GB+) logical backup TƏZYİQLİ, fiziki istifadə et.**

---

### 2. Physical Backup (File copy)

Database file-larının birbaşa kopyası.

#### MySQL: Percona XtraBackup / mariabackup

```bash
# Full backup
xtrabackup --backup --target-dir=/backup/full/
xtrabackup --prepare --target-dir=/backup/full/

# Incremental (ilk incremental full-dan)
xtrabackup --backup --target-dir=/backup/inc1/ \
  --incremental-basedir=/backup/full/

# İkinci incremental (birincinin üstündən)
xtrabackup --backup --target-dir=/backup/inc2/ \
  --incremental-basedir=/backup/inc1/

# Restore: apply-log incremental-ları sırası ilə
xtrabackup --prepare --apply-log-only --target-dir=/backup/full/
xtrabackup --prepare --apply-log-only --target-dir=/backup/full/ \
  --incremental-dir=/backup/inc1/
xtrabackup --prepare --target-dir=/backup/full/ \
  --incremental-dir=/backup/inc2/    # SON-uncuda --apply-log-only VERMƏ

# Final: fayl-ları datadir-ə köçür
systemctl stop mysql
rm -rf /var/lib/mysql/*
xtrabackup --copy-back --target-dir=/backup/full/
chown -R mysql:mysql /var/lib/mysql
systemctl start mysql
```

#### PostgreSQL: pg_basebackup

```bash
# Əsas physical backup
pg_basebackup -U postgres -D /backup/base -Ft -z -P -X stream

# -Ft: tar format
# -z: gzip sıxılma
# -P: progress
# -X stream: WAL-ı stream et (backup end-ə qədər)
```

#### PostgreSQL: pgBackRest (production-grade)

**Ən yaxşı PG backup aləti** — paralel, parçalara bölünmüş, deduplication, PITR.

```ini
# /etc/pgbackrest/pgbackrest.conf
[global]
repo1-path=/var/lib/pgbackrest
repo1-retention-full=4
process-max=8
compress-type=zst

[myapp]
pg1-path=/var/lib/postgresql/15/main
pg1-port=5432
```

```bash
# Stanza yarat
pgbackrest --stanza=myapp stanza-create

# Full backup
pgbackrest --stanza=myapp --type=full backup

# Incremental
pgbackrest --stanza=myapp --type=incr backup

# Differential (sonuncu full-dan)
pgbackrest --stanza=myapp --type=diff backup

# Restore (specific time-a)
pgbackrest --stanza=myapp --type=time \
  "--target=2024-06-15 14:30:00+00" restore
```

#### PostgreSQL: WAL-G / WAL-E (cloud-native)

S3, GCS, Azure Blob-a paralel upload. Neon, Supabase, RDS bunu istifadə edir.

```bash
# WAL-G environment
export WALG_S3_PREFIX="s3://mybucket/wal"
export AWS_REGION="us-east-1"

# Full backup
wal-g backup-push /var/lib/postgresql/15/main

# WAL archive (postgresql.conf)
archive_command = 'wal-g wal-push %p'
archive_mode = on

# Fetch
wal-g backup-fetch /var/lib/postgresql/15/main LATEST

# WAL replay-ı WAL-G ilə
restore_command = 'wal-g wal-fetch %f %p'
```

**Üstünlüklər:** Sürətli (file copy), böyük DB-lər üçün ideal, paralel, sıxılma.
**Mənfi tərəflər:** Eyni DB engine versiyasına restore, arxitektura-spesifik (x86 → ARM olmaz).

---

### 3. Streaming Replica as Backup

Live replica bir "hot standby" kimi işləyir. Backup alarkən **replica-dan götür, primary-ni yükləmə**.

```bash
# PostgreSQL streaming replica-dan pg_basebackup
pg_basebackup -h replica.db -U repl -D /backup/ -Ft -z -P

# MySQL: Replica-da xtrabackup işlət
# Replica: STOP REPLICA; xtrabackup --backup; START REPLICA;
```

**Niyə replica-dan:** Primary-nin I/O-su tutulmur, application-e təsir yoxdur.

---

### 4. Snapshot-based Backup (Cloud/filesystem)

`EBS Snapshot`, `GCE disk snapshot`, `ZFS snapshot`, `LVM snapshot` — filesystem-səviyyəli copy-on-write.

```bash
# AWS EBS snapshot (RDS bunu edir)
aws ec2 create-snapshot --volume-id vol-abc123 --description "pre-deploy backup"

# ZFS
zfs snapshot tank/pgdata@2024-06-15
zfs send tank/pgdata@2024-06-15 | gzip > /backup/snapshot.gz

# LVM
# 1. Snapshot yarat (DB-ni FLUSH + FREEZE et)
mysql -e "FLUSH TABLES WITH READ LOCK;"
lvcreate -L 10G -s -n mysql_snap /dev/vg0/mysql_data
mysql -e "UNLOCK TABLES;"
# 2. Snapshot-dan kopyala
mount /dev/vg0/mysql_snap /mnt/snap
rsync -a /mnt/snap/ /backup/mysql_data/
```

**Üstünlüklər:** Anında, böyük volume-lar üçün çox sürətli, incremental (copy-on-write).
**Mənfi tərəflər:** Filesystem-specific, consistency üçün FLUSH + FREEZE lazımdır (yaxud crash-consistent qəbul et).

---

## Point-in-Time Recovery (PITR) — dərin

### Konsept

1. **Full backup** — bir base nöqtəsi (məsələn, bazar gecəsi).
2. **WAL / binlog archive** — o andan bəri bütün dəyişikliklər.
3. **Restore** = full backup + WAL replay müəyyən vaxta qədər.

```
Bazar 00:00    Pazartesi 10:30    Çərşənbə 15:00 (hadisə: DROP TABLE)
     |                |                     |
Full backup ←←← WAL archive ←←← WAL archive
                                             ↑
                                  Target: 14:59:59-a qədər replay et
```

### MySQL: Binlog ilə PITR

```bash
# 1. Full backup (xtrabackup və ya mysqldump) restore et

# 2. Binlog position / timestamp tap (restore başlanğıcı)
# Hadisə vaxtını müəyyən et: məsələn 2024-06-15 14:30:00

# 3. Relevant binlog-ları tap
ls /var/log/mysql/mysql-bin.*

# 4. Timestamp aralığı ilə replay et
mysqlbinlog --start-datetime="2024-06-15 00:00:00" \
            --stop-datetime="2024-06-15 14:29:59" \
            /var/log/mysql/mysql-bin.000042 \
            /var/log/mysql/mysql-bin.000043 | \
  mysql -u root -p

# Və ya GTID ilə (daha dəqiq)
mysqlbinlog --exclude-gtids='source_uuid:42-100' \
  mysql-bin.* | mysql -u root -p
```

### PostgreSQL: WAL ilə PITR

```ini
# postgresql.conf
wal_level = replica                  # minimal, replica, logical
archive_mode = on
archive_command = 'cp %p /archive/%f' # ya wal-g wal-push, ya pgBackRest
max_wal_senders = 10
```

```ini
# Restore zamanı postgresql.conf (PG 12+):
restore_command = 'cp /archive/%f %p'
recovery_target_time = '2024-06-15 14:29:59'
recovery_target_action = 'promote'   # recovery bitənə qədər promote et
```

Və ya **specific transaction**-ə:

```ini
recovery_target_xid = '123456789'       # Müəyyən transaction ID
recovery_target_name = 'deploy-v2.3'    # Müəyyən savepoint (pg_create_restore_point-lə)
recovery_target_lsn = '0/3000028'       # WAL LSN
```

```bash
# Restore prosesi
systemctl stop postgresql

# 1. Base backup restore et
rm -rf /var/lib/postgresql/15/main/*
tar -xzf /backup/base.tar.gz -C /var/lib/postgresql/15/main/

# 2. recovery.signal yarat (PG 12+)
touch /var/lib/postgresql/15/main/recovery.signal

# 3. postgresql.conf-a recovery parametrləri əlavə et

# 4. Başla — PG WAL-ı replay edir
systemctl start postgresql

# 5. Recovery bitdikdə pg_log göstərər:
# "database system is ready to accept connections"
# "recovery has completed"
```

### Logical Replication Slot + Backup

**PG 10+** — logical replication slot təyin edərək istənilən subscriber üçün WAL qorunur:

```sql
-- Slot yarat (WAL silinməyəcək subscriber alana qədər)
SELECT pg_create_logical_replication_slot('backup_slot', 'pgoutput');

-- Slot silmək (vacib! yoxsa WAL disk-i dolar)
SELECT pg_drop_replication_slot('backup_slot');
```

**Təhlükə:** Slot-u izləmirsənsə, disk 100% dolub DB crash edir. `pg_stat_replication_slots` monitor et.

---

## Backup strategiyaları

### 3-2-1 Qayda

- **3** nüsxə (original + 2 backup)
- **2** fərqli media (local disk + cloud S3)
- **1** offsite (başqa lokasiya / region)

### Grandfather-Father-Son (GFS)

- **Daily (Son)** — 7 gün
- **Weekly (Father)** — 4 həftə
- **Monthly (Grandfather)** — 12 ay
- **Yearly** — N il (compliance üçün: SOX 7 il, GDPR kategoriyasından asılı)

### Full + Incremental + WAL archive

```
Bazar 00:00 — Full backup (gzipped, S3-ə upload)
Mon-Sat 03:00 — Incremental backup
Hər 5 dəqiqədə — WAL archive (S3-ə upload)

Retention:
- Hourly WAL: 7 gün
- Daily incrementals: 14 gün
- Weekly full: 3 ay
- Monthly full: 1 il
- Yearly full: 7 il (compliance)
```

### Encrypted backup (GDPR, HIPAA)

```bash
# GPG ilə şifrələ
pg_dump myapp | gpg --encrypt --recipient backup@company.com > backup.sql.gpg

# S3 server-side encryption
aws s3 cp backup.dump s3://backup-bucket/ \
  --sse aws:kms --sse-kms-key-id alias/backup-key

# pgBackRest ilə
# pgbackrest.conf:
repo1-cipher-type=aes-256-cbc
repo1-cipher-pass=long-random-passphrase
```

---

## Cloud Managed Database Backup

| Cloud | Backup növü | PITR | Cross-region |
|-------|-------------|------|--------------|
| **AWS RDS** | Snapshot (EBS) | 35 gün | Replicate via snapshot copy |
| **AWS Aurora** | Continuous backup S3-də | 35 gün | Global Database |
| **GCP Cloud SQL** | Snapshot + binary log | 7 gün (default) | Cross-region replica |
| **PlanetScale** | Automatic per-branch | 30 gün | Multi-region |
| **Supabase** | pg_dump daily + WAL | 7-28 gün (plan-a görə) | Read replicas |
| **Neon** | Point-in-time (branching) | 7 gün (Free) / 30 gün (Pro) | Copy-on-write branches |

**Vacib:** Cloud-managed backup-lar da **test olunmalıdır**. AWS RDS snapshot-ı var, amma **başqa region-a restore edə bilirsənmi?**

```bash
# AWS RDS snapshot test
aws rds restore-db-instance-from-db-snapshot \
  --db-instance-identifier test-restore \
  --db-snapshot-identifier prod-snapshot-2024-06-15
```

---

## Laravel-də Backup

### spatie/laravel-backup

```bash
composer require spatie/laravel-backup

php artisan backup:run           # Backup et
php artisan backup:run --only-db # Yalniz database
php artisan backup:list          # Backup-ları göstər
php artisan backup:clean         # Köhnə backup-ları sil
```

```php
// config/backup.php
'backup' => [
    'destination' => [
        'disks' => ['s3', 'local'],  // Multi-destination
        'filename_prefix' => 'myapp-',
    ],
    'source' => [
        'databases' => ['mysql'],
        'files' => [
            'include' => [base_path('storage/app/public')],
            'exclude' => [base_path('storage/logs')],
        ],
    ],
    'encryption' => 'default',  // Laravel APP_KEY ilə
],

'cleanup' => [
    'strategy' => DefaultStrategy::class,
    'default_strategy' => [
        'keep_all_backups_for_days' => 7,
        'keep_daily_backups_for_days' => 30,
        'keep_weekly_backups_for_weeks' => 12,
        'keep_monthly_backups_for_months' => 12,
        'keep_yearly_backups_for_years' => 3,
        'delete_oldest_backups_when_using_more_megabytes_than' => 50000,
    ],
],

'monitor_backups' => [
    'notifications' => [
        'mail' => ['to' => 'ops@company.com'],
        'slack' => ['webhook_url' => env('SLACK_WEBHOOK')],
    ],
],
```

```php
// app/Console/Kernel.php
$schedule->command('backup:run --only-db')->dailyAt('03:00');
$schedule->command('backup:run')->weeklyOn(0, '02:00');  // Yalnız bazar tam backup
$schedule->command('backup:clean')->dailyAt('04:00');
$schedule->command('backup:monitor')->dailyAt('05:00');  // Backup-ların sağlamlığı
```

### Real-time binlog replikasiya

Laravel app-ı **read-only replica**-ya bağla, write-lər primary-yə. Backup zamanı primary-ni yükləmə — **replica-dan götür**.

```php
// config/database.php
'mysql' => [
    'read'  => ['host' => ['replica1.db', 'replica2.db']],
    'write' => ['host' => 'primary.db'],
    'sticky' => true,
],
```

---

## Recovery Test (ən çox unutulan hissə!)

**"Test olunmamış backup = backup yoxdur"** — bu qayda 100% həqiqətdir.

### Disaster Recovery Drill

```bash
# Ayda bir: tam restore simulyasiyası
# 1. Yeni staging server-də restore et
aws ec2 run-instances --image-id ami-xxx --instance-type db.r5.large ...

# 2. Restore et
pgbackrest --stanza=myapp --type=time \
  "--target=2024-06-15 14:00:00" restore

# 3. Integrity check
psql -c "SELECT COUNT(*) FROM users;"
psql -c "SELECT COUNT(*) FROM orders WHERE created_at < '2024-06-15 14:00:00';"

# 4. Application-ı staging DB ilə işlət
# smoke test: login, order yarat, payment flow

# 5. RTO ölç: backup bildirilməsindən restore bitənə qədər nə qədər vaxt?
```

### Automated backup verification

```bash
#!/bin/bash
# Hər səhər: dünənki backup-ı test DB-ə restore et
BACKUP=$(aws s3 ls s3://backups/daily/ | sort | tail -1 | awk '{print $4}')
aws s3 cp s3://backups/daily/$BACKUP /tmp/

dropdb test_restore
createdb test_restore
pg_restore -d test_restore /tmp/$BACKUP

# Sanity check
COUNT=$(psql -t -c "SELECT COUNT(*) FROM test_restore.users;")
if [ "$COUNT" -lt 1000000 ]; then
  echo "ALERT: Restore verification failed. Count=$COUNT" | mail -s "Backup FAIL" ops@company.com
fi
```

---

## Disaster Recovery

### RTO və RPO

- **RTO (Recovery Time Objective):** Sistemin nə qədər tez bərpası lazımdır? (məs. 1 saat)
- **RPO (Recovery Point Objective):** Nə qədər data itkisi qəbul edilir? (məs. 5 dəqiqə)

| Strategiya | RPO | RTO | Xərc |
|-----------|-----|-----|------|
| Yalnız full backup (gündəlik) | 24 saat | Saatlar | $ |
| Full + binlog/WAL archive | Dəqiqələr | 30 dəq - 1 saat | $$ |
| Streaming replication (async) | Saniyələr | Dəqiqələr | $$$ |
| Synchronous replication | 0 (sıfır itki) | Saniyələr | $$$$ |
| Multi-region active-active | 0 | 0 | $$$$$ |

### RTO/RPO iş ehtiyaclarına uyğun

```
E-commerce ödəniş sistemi:  RPO = 0 saniyə, RTO < 1 dəq  → sync replication
Blog comment:                RPO = 5 dəq,    RTO = 30 dəq → async replica + WAL
Analytics reporting:         RPO = 24 saat,  RTO = 4 saat  → daily backup
```

---

## Runbook — "DROP TABLE baş verdi"

1. **PANIC ETMƏ.** DML-lər dayandır: `REVOKE INSERT, UPDATE, DELETE FROM app_user;`
2. Binlog/WAL position-u müəyyən et: hadisə nə zaman baş verdi?
3. Full backup + WAL archive mövcuddurmu? Yoxla.
4. Staging server-də restore et (**production-a toxunma!**)
5. PITR ilə hadisədən 1 saniyə əvvəlki halı bərpa et.
6. İtirilən data-nı **CSV export** et, production-a manuel insert et.
7. Postmortem yaz: niyə baş verdi? Nə edə bilərdik?

```bash
# PG-də incident-dən bir dəqiqə əvvəlki vaxta qədər restore
pgbackrest --stanza=myapp \
  --type=time "--target=2024-06-15 14:29:00" \
  --target-action=promote restore

# Mövcud DB-ni `DROP TABLE`-dan qabaq snapshot-la müqayisə et
pg_dump -t users staging_db > recovered.sql
pg_dump -t users prod_db > current.sql
diff recovered.sql current.sql  # Hansı row-lar itib?
```

---

## Interview sualları

**Q: Backup strategiyanız necədir?**
A: 3-2-1 qayda: 3 nüsxə, 2 media, 1 offsite. Gündəlik incremental + həftəlik full backup, **WAL/binlog archive hər 5 dəq-də**. Logical backup schema snapshot üçün, physical backup fast restore üçün. Encrypted at rest (KMS), retention GFS modelinə görə (7d/4w/12m/7y). **Ayda bir** manuel restore drill.

**Q: `DROP TABLE users` icra olundu, nə edərsən?**
A: 1) Panic etmə, write-ləri dayandır. 2) WAL/binlog archive varsa: son full backup-ı restore et, sonra `DROP TABLE`-dan əvvəlki zamana qədər PITR et. 3) Replica varsa və hələ propagate olmayıbsa, replica-dan data-nı geri kopyala. 4) Heç biri yoxdursa: son backup-dan restore et (RPO qədər data itir). 5) Postmortem: DDL üçün audit, `pg_hba.conf` role ayırması, schema-dan qarşıdurma.

**Q: mysqldump production-da niyə yavaş ola bilər?**
A: `--single-transaction` olmadan table lock qoyur. InnoDB-də `--single-transaction` ilə consistent snapshot alır, lock olmur, amma I/O + CPU yükü var. 100GB+ DB üçün fiziki backup (xtrabackup / Percona) **sürətli və lock-suz**. Həmçinin primary-ni yükləmə — **replica-dan götür**.

**Q: WAL archive və streaming replication fərqi?**
A: **WAL archive** — file-ları saxlama (disk/S3), backup üçün. **Streaming replication** — canlı subscriber-ə push, HA üçün. Hər ikisi eyni WAL-dan qidalanır. Production-da **hər ikisi** — streaming replication HA, WAL archive PITR və disaster recovery üçün.

**Q: RPO-nu 0 etmək üçün nə lazımdır?**
A: Synchronous replication — hər commit ən azı 1 replica-ya yazılana qədər geri dönməz. Tradeoff: **yüksək latency** (network round-trip), **replica down olarsa primary də dayanır** (quorum lazımdır). Kritik mali tranzaksiyalar üçün dəyərli; read-heavy workload-lar üçün həddi artıq. PostgreSQL-də `synchronous_commit = on` + `synchronous_standby_names`. Multi-region sync replication adətən bahalıdır (>10ms latency).

**Q: Niyə backup-dan restore test çox vacibdir?**
A: Backup "var" olmaq işə yaramaz, "restore olunmaq" lazımdır. Disk corrupt ola bilər, encryption key itə bilər, storage provider down ola bilər, binary version uyğun gəlməyə bilər. **Netflix Chaos Engineering** prinsipi: real disaster drill ayda bir keçirilməlidir. Minimum: hər həftə automated restore verification (yeni staging-ə restore + smoke test).

**Q: Backup retention qərarını necə verirsən?**
A: 1) **Compliance** (SOX 7 il, GDPR "right to be forgotten" əksi, HIPAA 6 il). 2) **Storage xərci** (S3 Glacier ucuz, amma retrieval yavaş). 3) **RPO iyerarxiyası**: last 7 days (fast access, hot), 30 days (warm), 1 year (Glacier). 4) **Business**: finance data uzun, log data qısa. Adətən GFS modelini biznes ehtiyaclarına uyğunlaşdırıram.
