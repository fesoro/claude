# Backup & Recovery

## Backup Novleri

### 1. Logical Backup (SQL dump)

SQL statement-ler seklinde export.

```bash
# MySQL
mysqldump -u root -p --single-transaction --routines --triggers myapp > backup.sql

# Tek table
mysqldump -u root -p myapp users orders > partial_backup.sql

# Butun database-ler
mysqldump -u root -p --all-databases > full_backup.sql

# Compressed
mysqldump -u root -p myapp | gzip > backup.sql.gz

# PostgreSQL
pg_dump -U postgres -d myapp -F custom -f backup.dump    # Custom format (sıxılmış)
pg_dump -U postgres -d myapp -F plain -f backup.sql       # Plain SQL
pg_dumpall -U postgres -f all_databases.sql                # Butun DB-ler
```

**Restore:**

```bash
# MySQL
mysql -u root -p myapp < backup.sql
gunzip < backup.sql.gz | mysql -u root -p myapp

# PostgreSQL
pg_restore -U postgres -d myapp backup.dump
psql -U postgres -d myapp -f backup.sql
```

**Ustunlukleri:** Oxuna bilen format, ferqli versiyalara restore etmek olar, partial backup mumkun
**Menfi terefler:** Yavas (boyuk DB-lerde saatlar sure biler), restore zamani table lock

### 2. Physical Backup (File copy)

Database file-larinin birbaşa kopyasi.

```bash
# MySQL: Percona XtraBackup (production ucun en yaxsi)
xtrabackup --backup --target-dir=/backup/full/
xtrabackup --prepare --target-dir=/backup/full/

# Incremental backup
xtrabackup --backup --target-dir=/backup/inc1/ --incremental-basedir=/backup/full/

# PostgreSQL: pg_basebackup
pg_basebackup -U postgres -D /backup/base -Ft -z -P
```

**Ustunlukleri:** Suretli (file copy), boyuk DB-ler ucun ideal
**Menfi terefler:** Eyni versiyaya restore etmek lazim, partial restore cetin

### 3. Point-in-Time Recovery (PITR)

Full backup + transaction log (binlog/WAL) ile ixtiyari zamana restore.

```bash
# MySQL: Binlog ile PITR
# 1. Full backup restore et
mysql -u root -p myapp < full_backup.sql

# 2. Binlog-dan mueyyen zamana qeder replay et
mysqlbinlog --stop-datetime="2024-06-15 14:30:00" mysql-bin.000042 | mysql -u root -p

# PostgreSQL: WAL ile PITR
# recovery.conf (ve ya postgresql.auto.conf)
restore_command = 'cp /archive/%f %p'
recovery_target_time = '2024-06-15 14:30:00'
```

---

## Backup Strategiyalari

### 3-2-1 Qayda

- **3** nusxe (original + 2 backup)
- **2** ferqli media (local disk + cloud)
- **1** offsite (basqa lokasiya/region)

### Full + Incremental

```
Bazar: Full backup (10GB)
Bazarertesi: Incremental (200MB - yalniz deyisiklikler)
Cersembe: Incremental (300MB)
...
Novbeti bazar: Full backup (11GB)
```

```bash
# Cron job ile avtomatik backup
# /etc/cron.d/db_backup

# Her gun 3:00-da incremental
0 3 * * 1-6 /scripts/incremental_backup.sh

# Her bazar 2:00-da full
0 2 * * 0 /scripts/full_backup.sh
```

### Laravel-de Backup

```bash
composer require spatie/laravel-backup

php artisan backup:run           # Backup et
php artisan backup:run --only-db # Yalniz database
php artisan backup:list          # Backup-lari goster
php artisan backup:clean         # Kohne backup-lari sil
```

```php
// config/backup.php
'backup' => [
    'destination' => [
        'disks' => ['s3', 'local'],  // S3 + local
    ],
],
'cleanup' => [
    'strategy' => DefaultStrategy::class,
    'default_strategy' => [
        'keep_all_backups_for_days' => 7,
        'keep_daily_backups_for_days' => 30,
        'keep_weekly_backups_for_weeks' => 12,
    ],
],

// Schedule
$schedule->command('backup:run --only-db')->daily()->at('03:00');
$schedule->command('backup:clean')->daily()->at('04:00');
```

---

## Recovery Testleri

**En muhum qayda: Test olunmamis backup = backup yoxdur!**

```bash
# Ayda bir: Backup-dan restore test et
# 1. Test server-de restore et
mysql -u root -p test_db < backup.sql

# 2. Data integrity yoxla
mysql -u root -p test_db -e "SELECT COUNT(*) FROM users;"
mysql -u root -p test_db -e "CHECK TABLE orders;"

# 3. Application-i test DB ile islet ve yoxla
```

---

## Disaster Recovery

### RTO ve RPO

- **RTO (Recovery Time Objective):** Sistemin ne qeder tez berpasi lazimdir? (meselen 1 saat)
- **RPO (Recovery Point Objective):** Nə qədər data itkisi qəbul edilir? (meselen 5 deqiqe)

| Strategiya | RPO | RTO |
|-----------|-----|-----|
| Yalniz full backup (gunluk) | 24 saat | Saatlar |
| Full + binlog/WAL | Deqiqeler | 30 deq - 1 saat |
| Streaming replication | Saniyeler | Deqiqeler |
| Synchronous replication | 0 (sifir itkisi) | Saniyeler |

---

## Interview suallari

**Q: Backup strategiyaniz nedir?**
A: 3-2-1 qaydasi: 3 nusxe, 2 media, 1 offsite. Gunluk incremental + heftlik full backup. PITR ucun binlog/WAL archive. Ayliq restore test. RTO/RPO business teleblerinden asili olaraq mueyyenlesdirilir.

**Q: `DROP TABLE users` icra olundu, ne edersin?**
A: 1) Panic etme. 2) Eger binlog/WAL archive varsa: son full backup-i restore et, sonra `DROP TABLE`-dan evvelki zamana qeder PITR et. 3) Replica varsa ve henuz propagate olmayibsa, replica-dan data-ni geri kopyala. 4) Eger hec biri yoxdursa: son backup-dan restore et (RPO qeder data itirilir).

**Q: mysqldump production-da niye yavas ola biler?**
A: `--single-transaction` olmadan table lock qoyur. Boyuk table-larda saatlar sure biler ve diger query-leri bloklayir. `--single-transaction` (InnoDB) ile consistent snapshot alir, lock qoymadan. Amma yene de I/O ve CPU yukleyir - replica-dan backup almaq daha yaxsidir.
