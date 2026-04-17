# Backup Strategies (Backup Strategiyaları)

## Nədir? (What is it?)

Backup strategiyaları – data itkisinin qarşısını almaq və disaster (fəlakət) zamanı sistemi bərpa etmək üçün istifadə olunan metodlar və prosedurlardır. Kritik anlayışlar: RTO (Recovery Time Objective – bərpa vaxtı), RPO (Recovery Point Objective – icazə verilən data itkisi), 3-2-1 qaydası, incremental/differential backup-lar. Laravel üçün Spatie Backup paketi database və fayl backup-ı üçün populyar həllidir.

## Əsas Konseptlər (Key Concepts)

### Backup Növləri

```bash
# 1. FULL BACKUP (Tam backup)
# Bütün data kopyalanır
# + Asan bərpa
# - Yer çox tutur, uzun çəkir

tar -czf full-backup-$(date +%Y%m%d).tar.gz /var/www/laravel /var/lib/mysql

# 2. INCREMENTAL BACKUP (Artımlı)
# Yalnız son backup-dan sonrakı dəyişikliklər
# + Tez, az yer
# - Bərpa zamanı bütün incremental zəncir lazımdır

# rsync ilə
rsync -av --link-dest=/backups/previous /var/www/laravel /backups/current

# tar snapshot ilə
tar --listed-incremental=/backups/snapshot.snar -czf incremental.tar.gz /var/www/laravel

# 3. DIFFERENTIAL BACKUP (Diferensial)
# Son full backup-dan sonrakı dəyişikliklər
# + Bərpa 2 fayla (full + differential)
# - Hər gün böyüyür

# 4. SNAPSHOT (AWS EBS, LVM)
# Filesystem/volume səviyyəsində nöqtə-zaman kopyası
# + İnstant, consistent
# - Storage provider asılılığı

# EBS snapshot
aws ec2 create-snapshot --volume-id vol-1234 --description "Daily backup"

# LVM snapshot
lvcreate -L 5G -s -n data_snap /dev/vg0/data
mount /dev/vg0/data_snap /mnt/snapshot
tar -czf backup.tar.gz /mnt/snapshot
umount /mnt/snapshot
lvremove /dev/vg0/data_snap
```

### 3-2-1 Backup Qaydası

```
3 - Ən azı 3 nüsxə data (original + 2 backup)
2 - 2 fərqli media/storage tipində
1 - 1 offsite (coğrafi fərqli yerdə)

Nümunə:
- Primary: Production server (original)
- Local backup: Ayrı server/NAS
- Offsite: AWS S3 (başqa region)

# Təkmil variant: 3-2-1-1-0
# +1 offline (air-gapped, ransomware qoruması)
# 0 - verification errors (backup test edilmiş)
```

### RTO və RPO

```
RTO (Recovery Time Objective) - sistem nə qədər tez bərpa olunur?
- RTO = 1 saat → 1 saat downtime icazə
- Aşağı RTO → hot standby, automated failover
- Yüksək RTO → tape restore OK

RPO (Recovery Point Objective) - nə qədər data itkisi icazə?
- RPO = 1 saat → 1 saatlıq data itkisi icazə
- Aşağı RPO → continuous replication, sync backup
- Yüksək RPO → daily backup OK

Kateqoriyalar:
Tier 1 (Critical): RTO < 1h, RPO < 15min (banking, e-commerce)
Tier 2 (Important): RTO < 4h, RPO < 1h (CRM, internal apps)
Tier 3 (Normal): RTO < 24h, RPO < 24h (blog, wiki)
Tier 4 (Low): RTO/RPO > 24h (archive, reports)

# Laravel production üçün tipik hədəflər:
# RTO = 2 saat
# RPO = 15 dəqiqə (MySQL binlog replication)
```

### Database Backup

```bash
# MySQL logical backup
mysqldump -u root -p --single-transaction --routines --triggers \
    --all-databases > backup-$(date +%Y%m%d).sql

# Yalnız Laravel DB
mysqldump -u root -p --single-transaction --routines --triggers \
    laravel > laravel-$(date +%Y%m%d).sql

# Compressed
mysqldump -u root -p laravel | gzip > laravel-$(date +%Y%m%d).sql.gz

# Split tables
mysqldump laravel users orders --single-transaction > critical.sql

# Binary log (incremental)
mysqlbinlog --start-datetime="2024-01-01 00:00:00" /var/log/mysql/mysql-bin.000001 > incremental.sql

# Point-in-time recovery
mysql < full-backup.sql                                # Full restore
mysqlbinlog mysql-bin.000001 | mysql                   # Apply changes

# Physical backup (Percona XtraBackup) - daha sürətli
xtrabackup --backup --target-dir=/backup/full
xtrabackup --prepare --target-dir=/backup/full
xtrabackup --copy-back --target-dir=/backup/full

# Restore vaxtı tipik:
# mysqldump: 10GB DB → 30-60 dəq
# XtraBackup: 10GB DB → 5-10 dəq

# PostgreSQL
pg_dump -U postgres laravel > laravel-$(date +%Y%m%d).sql
pg_dumpall -U postgres > all-databases.sql

# Continuous archiving (PITR)
# postgresql.conf:
# wal_level = replica
# archive_mode = on
# archive_command = 'cp %p /backup/wal/%f'

# MongoDB
mongodump --db laravel --out /backup/$(date +%Y%m%d)
mongorestore --db laravel /backup/20240415/laravel
```

### File Backup

```bash
# rsync (incremental, efficient)
rsync -av --progress --delete /var/www/laravel/ backup@server:/backups/laravel/

# rsync with --link-dest (hard link snapshot)
DATE=$(date +%Y%m%d)
PREV=$(ls -t /backups | head -1)
rsync -av --link-dest=/backups/$PREV /var/www/laravel/ /backups/$DATE/

# rsnapshot (automated rotating backup)
# /etc/rsnapshot.conf
# interval hourly 24
# interval daily 7
# interval weekly 4
# interval monthly 12

# Borg Backup (deduplication, encryption)
borg init --encryption=repokey /backup/borg-repo
borg create /backup/borg-repo::$(date +%Y-%m-%d) /var/www/laravel
borg list /backup/borg-repo
borg restore /backup/borg-repo::2024-04-15

# Restic (S3 uyğun, encryption)
export RESTIC_REPOSITORY=s3:s3.amazonaws.com/my-backup-bucket
export RESTIC_PASSWORD=secretkey
restic init
restic backup /var/www/laravel
restic snapshots
restic restore latest --target /tmp/restore

# Duplicity (encrypted, GPG)
duplicity /var/www/laravel s3://s3.amazonaws.com/backup/

# AWS S3 sync
aws s3 sync /var/www/laravel s3://my-backup/laravel/ \
    --storage-class STANDARD_IA \
    --delete
```

### Backup Automation

```bash
# Cron job
# /etc/cron.d/laravel-backup
# m h dom mon dow user command
0  2  *   *   *   root /usr/local/bin/backup-laravel.sh > /var/log/backup.log 2>&1
30 2  *   *   0   root /usr/local/bin/backup-full-weekly.sh

# Backup script nümunəsi
#!/bin/bash
# /usr/local/bin/backup-laravel.sh

set -e

BACKUP_DIR="/backup/laravel"
DATE=$(date +%Y%m%d-%H%M%S)
RETENTION_DAYS=30
S3_BUCKET="s3://my-backups/laravel"

# Logging
exec > >(tee -a /var/log/backup.log)
exec 2>&1

echo "[$(date)] Backup started"

# 1. Database backup
mysqldump -u backup -p$DB_PASS --single-transaction laravel | \
    gzip > "$BACKUP_DIR/db-$DATE.sql.gz"

# 2. Files backup (exclude cache/logs)
tar --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    --exclude='node_modules' \
    -czf "$BACKUP_DIR/files-$DATE.tar.gz" \
    /var/www/laravel

# 3. Upload to S3
aws s3 cp "$BACKUP_DIR/db-$DATE.sql.gz" "$S3_BUCKET/db/"
aws s3 cp "$BACKUP_DIR/files-$DATE.tar.gz" "$S3_BUCKET/files/"

# 4. Verify
aws s3 ls "$S3_BUCKET/db/db-$DATE.sql.gz"

# 5. Cleanup old backups
find "$BACKUP_DIR" -type f -mtime +$RETENTION_DAYS -delete

# 6. Notification
curl -X POST "$SLACK_WEBHOOK" -d "{\"text\":\"Backup completed: $DATE\"}"

echo "[$(date)] Backup completed"
```

## Praktiki Nümunələr (Practical Examples)

### Disaster Recovery Plan

```markdown
# DR Plan for Laravel App

## RTO: 2 hours
## RPO: 15 minutes

## Scenarios

### Scenario 1: Database corruption
- Detection: Monitoring alert (replication lag, errors)
- Action:
  1. Identify corruption point
  2. Restore last known good backup
  3. Apply binlog for PITR (point in time)
  4. Verify data integrity
  5. Switch app to restored DB
- Time: 30-60 min

### Scenario 2: Full server loss
- Detection: No response to health checks
- Action:
  1. Provision new EC2 from AMI
  2. Restore database from latest backup
  3. Sync files from S3
  4. Update DNS (Route53)
  5. Validate
- Time: 1-2 hours

### Scenario 3: Region outage (AWS)
- Detection: Multiple AZ down
- Action:
  1. Failover to DR region
  2. Restore from cross-region backup
  3. DNS failover
- Time: 1-2 hours

## Testing Schedule
- Weekly: Backup integrity check
- Monthly: Restore test (staging)
- Quarterly: Full DR drill
- Yearly: Cross-region failover test
```

### Backup verification script

```bash
#!/bin/bash
# verify-backup.sh - restore to test server

BACKUP=$1
TEST_DB="laravel_test_restore"

# 1. Download latest backup
aws s3 cp s3://my-backups/laravel/db/$BACKUP /tmp/

# 2. Decompress
gunzip /tmp/$BACKUP

# 3. Restore to test DB
mysql -e "DROP DATABASE IF EXISTS $TEST_DB; CREATE DATABASE $TEST_DB;"
mysql $TEST_DB < /tmp/${BACKUP%.gz}

# 4. Verify data
USERS=$(mysql $TEST_DB -sN -e "SELECT COUNT(*) FROM users")
if [ "$USERS" -lt 100 ]; then
    echo "ERROR: User count suspiciously low: $USERS"
    exit 1
fi

# 5. Run Laravel tests against restored DB
cd /var/www/laravel && DB_DATABASE=$TEST_DB php artisan migrate:status

# 6. Cleanup
mysql -e "DROP DATABASE $TEST_DB;"
rm /tmp/${BACKUP%.gz}

echo "Backup verified successfully"
```

## PHP/Laravel ilə İstifadə

### Spatie Laravel Backup

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

```php
// config/backup.php
return [
    'backup' => [
        'name' => env('APP_NAME'),
        'source' => [
            'files' => [
                'include' => [
                    base_path('app'),
                    base_path('config'),
                    base_path('public'),
                    storage_path('app/public'),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('logs'),
                    storage_path('framework/cache'),
                ],
            ],
            'databases' => ['mysql'],
        ],
        'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,
        'destination' => [
            'filename_prefix' => '',
            'disks' => ['local', 's3'],
        ],
    ],
    
    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
    
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail', 'slack'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['slack'],
        ],
        'mail' => ['to' => 'admin@example.com'],
        'slack' => ['webhook_url' => env('SLACK_WEBHOOK')],
    ],
    
    'monitor_backups' => [
        [
            'name' => env('APP_NAME'),
            'disks' => ['local', 's3'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],
];
```

```bash
# Komandalar
php artisan backup:run                    # Full backup
php artisan backup:run --only-db          # Yalnız DB
php artisan backup:run --only-files       # Yalnız fayllar
php artisan backup:list                   # List
php artisan backup:clean                  # Köhnə backup-ları sil
php artisan backup:monitor                # Health check
```

```php
// Schedule backup
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('backup:clean')->daily()->at('01:00');
    $schedule->command('backup:run --only-db')->daily()->at('02:00');
    $schedule->command('backup:run')->weekly()->sundays()->at('03:00');
    $schedule->command('backup:monitor')->daily()->at('06:00');
}
```

### Custom restore command

```php
// app/Console/Commands/RestoreBackup.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestoreBackup extends Command
{
    protected $signature = 'backup:restore {filename} {--disk=s3} {--confirm}';
    protected $description = 'Restore backup from storage';

    public function handle()
    {
        if (!$this->option('confirm') && !$this->confirm('Restore will OVERWRITE current data. Continue?')) {
            return;
        }

        $filename = $this->argument('filename');
        $disk = $this->option('disk');
        
        $this->info("Downloading $filename from $disk...");
        $contents = Storage::disk($disk)->get($filename);
        $tempPath = storage_path("app/restore-$filename");
        file_put_contents($tempPath, $contents);
        
        $this->info("Extracting...");
        // Extract zip
        $zip = new \ZipArchive();
        $zip->open($tempPath);
        $zip->extractTo(storage_path('app/restore-temp'));
        $zip->close();
        
        // Restore database
        $sqlFile = storage_path('app/restore-temp/db-dumps/mysql-laravel.sql');
        if (file_exists($sqlFile)) {
            $this->info("Restoring database...");
            $db = config('database.connections.mysql');
            exec("mysql -u{$db['username']} -p{$db['password']} {$db['database']} < $sqlFile");
        }
        
        // Restore files
        $this->info("Restoring files...");
        exec("cp -r " . storage_path('app/restore-temp/files/') . "* " . base_path());
        
        // Cleanup
        unlink($tempPath);
        exec("rm -rf " . storage_path('app/restore-temp'));
        
        $this->info("Restore completed");
    }
}
```

## Interview Sualları (5-10 Q&A)

**S1: RTO və RPO arasında fərq nədir?**
C: RTO (Recovery Time Objective) – sistem nə qədər tez bərpa olunmalıdır (downtime). RPO (Recovery Point Objective) – nə qədər data itkisi icazə verilir. Məsələn RTO=1h, RPO=15min: 1 saatdan gec bərpa olmamalı və son 15 dəqiqəlik data itkisi maksimum. Hər ikisi biznes tələblərindən gəlir, aşağı olduqca infrastruktur daha baha olur.

**S2: 3-2-1 backup qaydası nədir?**
C: 3 nüsxə data (original + 2 backup), 2 fərqli media tipində, 1 offsite (coğrafi ayrı). Məs. production DB + local backup server + AWS S3 başqa region. Bu qayda müxtəlif failure scenarios-a qarşı qoruyur: disk çökməsi, server yanğını, region outage, ransomware. 3-2-1-1-0 daha qabaqcıl: +1 offline, 0 verification error.

**S3: Full, incremental və differential backup fərqi?**
C: Full – bütün data. Incremental – son backup-dan sonrakı dəyişikliklər (az yer, amma bərpa üçün bütün zəncir lazım). Differential – son full-dan sonrakı dəyişikliklər (hər gün böyüyür, amma bərpa 2 fayla). Strategy: həftədə bir full, hər gün incremental (yaxud differential).

**S4: MySQL-də point-in-time recovery (PITR) necə işləyir?**
C: Full backup + binary log-lar. Full backup bərpa olunur, sonra binlog istənilən ana qədər apply olunur. Konfiqurasiya: `log_bin=ON`. Bərpa: `mysql < full.sql`, `mysqlbinlog --stop-datetime="2024-04-15 14:30:00" mysql-bin.000001 | mysql`. 15 dəqiqəlik RPO-nu təmin edir (binlog flush interval).

**S5: Backup verification niyə vacibdir?**
C: Backup götürmək kifayət deyil – bərpa edilə bilməsi vacibdir. Səssiz korrupsiyanın qarşısını alır. Test strategiyası: (1) Checksum yoxlama; (2) Avtomatik restore test (staging-ə); (3) Schema integrity check; (4) Business-level validation. "Untested backup is not a backup" – aylıq DR drill-lər keçirin.

**S6: Disaster Recovery və Backup fərqi nədir?**
C: Backup – data kopyası (re-creation üçün). DR – bütün sistemin (infrastruktur, data, konfiqurasiya) bərpası üçün plan və prosedurlar. DR backup-ı əhatə edir amma daha geniş: runbook, alternative site, failover prosedurları, communication plan. DR Plan sənəd olaraq yazılıb test edilməlidir.

**S7: Hot, warm, cold DR site fərqi?**
C: Hot – tamamilə identik, real-time replication, RTO dəqiqələr (baha). Warm – əsas infrastruktur var, data periodic sync, RTO saatlar (orta). Cold – yalnız backup saxlanır, lazım olanda provision, RTO günlər (ucuz). AWS-də Pilot Light (minimum running), Warm Standby (scale-up), Multi-Site (active-active) modelləri var.

**S8: Spatie Laravel Backup-ın üstünlükləri nələrdir?**
C: Laravel native, sadə konfiqurasiya, database + files birgə backup, S3/local disk dəstəyi, rotation policy (daily/weekly/monthly keep), health monitoring, notification (email/Slack), artisan command. Dezavantaj: yalnız logical backup, böyük DB (100GB+) üçün slow. Böyük instanslar üçün XtraBackup + AWS Backup daha yaxşı.

**S9: Ransomware-ə qarşı backup strategiyası necə olmalıdır?**
C: (1) Immutable backup – yaradıldıqdan sonra dəyişdirilə bilməz (AWS S3 Object Lock, WORM storage); (2) Air-gapped copy – şəbəkədən ayrı saxlanır (offline); (3) Multi-factor auth backup sistem üçün; (4) Separate credentials – kompromise olunmuş admin-in backup-a çatmaması; (5) Versioning – köhnə versiyalar saxlanır; (6) Regular recovery test.

**S10: Backup retention policy necə qurulmalıdır?**
C: GFS (Grandfather-Father-Son) strategy: daily (7 gün), weekly (4 həftə), monthly (12 ay), yearly (7 il). Compliance tələblərinə görə dəyişir (GDPR, HIPAA, SOC 2). Cost-aware: köhnə backup-lar cold storage-ə (S3 Glacier, $0.004/GB/ay). Legal hold: müəyyən backup-lar silinməməlidir. Laravel Spatie-də konfiqurasiya edilə bilər.

## Best Practices

1. **3-2-1 qaydası**: 3 nüsxə, 2 media tipi, 1 offsite.
2. **Automated backups**: Cron/scheduler ilə avtomatik, manual əvəzinə.
3. **Regular testing**: Aylıq restore test, rüblük full DR drill.
4. **Monitoring**: Backup job uğursuz olsa xəbərdar et (Slack, email).
5. **Encryption**: Backup-lar həm transit, həm at-rest encrypted olsun.
6. **Offsite copy**: Başqa region/cloud-da kopya saxla.
7. **Retention policy**: GFS strategy, cost-aware (cold storage köhnələr üçün).
8. **Documentation**: DR runbook yazılmış, komandanın hamısına məlum.
9. **RTO/RPO müəyyən et**: Biznes tələblərinə görə, budget uyğun.
10. **Point-in-time recovery**: MySQL binlog, PostgreSQL WAL aktivləşdir.
11. **Incremental strategy**: Böyük dataset üçün, disk/bandwidth qənaəti.
12. **Verify restore**: Checksum, automated restore test staging-də.
13. **Separate credentials**: Backup user-i production admin-dən ayrı.
14. **Versioning**: S3 Object Versioning, accidental delete-ə qarşı.
15. **Database consistency**: `--single-transaction` (MySQL), WAL archiving (PostgreSQL).
16. **Exclude unnecessary**: vendor/, node_modules/, cache files – backup yer tutmasın.
17. **Disaster communication plan**: Incident zamanı kim kimə xəbər verir?
