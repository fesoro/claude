# Disaster Recovery

## Nədir? (What is it?)

Disaster Recovery (DR) - katastrofik hadisələrdən (natural disasters, hardware failures, cyber attacks, human errors) sonra sistemin bərpa edilməsi üçün hazırlanan planlar və proseslərdir. DR biznesin davamlılığının (Business Continuity) bir hissəsidir.

Mümkün fəlakətlər:
- **Hardware failures** - Data center yanğını, disk çatışması
- **Natural disasters** - Zəlzələ, daşqın, tufan
- **Cyber attacks** - Ransomware, DDoS, data breach
- **Human errors** - Təsadüfən `DROP DATABASE production`
- **Software bugs** - Data corruption
- **Provider outages** - AWS region failure

Yaxşı DR plan - "haçan" deyil, "nə qədər tez" sualına cavab verir.

## Əsas Konseptlər (Key Concepts)

### 1. RTO və RPO

İki əsas metrika:

**RTO (Recovery Time Objective):**
Fəlakətdən sonra sistem nə qədər sürətlə bərpa olunmalıdır? Downtime tolerance.
- **Tier 1 (Mission Critical):** 0-1 saat (banking, healthcare)
- **Tier 2 (Important):** 4-24 saat (e-commerce)
- **Tier 3 (Standard):** 24-72 saat (internal tools)
- **Tier 4 (Non-critical):** 72+ saat (archives)

**RPO (Recovery Point Objective):**
Nə qədər data itkisi qəbul edilə bilər?
- **Zero:** Sinxron replication (banking)
- **Minutes:** Async replication (e-commerce)
- **Hours:** Regular backups
- **Days:** Periodic exports

**Xərc vs RTO/RPO:** RTO/RPO 0-a yaxınlaşdıqca xərc eksponensial artır.

### 2. Backup Strategies

**3-2-1 Backup Rule:**
- **3** nüsxə (orijinal + 2 backup)
- **2** fərqli media (disk + tape/cloud)
- **1** offsite (fərqli coğrafi yer)

**Backup növləri:**
- **Full backup** - hər şey, tam bərpa, amma yavaş və çox yer
- **Incremental** - son dəyişikliklər, sürətli backup, yavaş restore
- **Differential** - son full-dan dəyişikliklər, orta yanaşma
- **Snapshot** - point-in-time copy (LVM, ZFS, AWS EBS)
- **Continuous (CDP)** - hər dəyişiklik saxlanır

**Backup testing:** Test edilməmiş backup mövcud deyil! Quarterly restore drills edin.

### 3. Failover Strategies

**Active-Passive (Hot Standby):**
- Standby server hazırdır amma traffic qəbul etmir
- Primary fail olanda standby aktivləşir
- RTO: dəqiqələr

**Active-Active:**
- Bütün server-lər aktiv
- Load bölünür
- Biri fail olarsa digərləri davam edir
- RTO: saniyələr

**Cold Standby:**
- Server söndürülmüş, konfiqurasiya edilməmiş
- Fail olanda manual start
- RTO: saatlar, gün
- Ən ucuz

**Warm Standby:**
- Server işləyir amma stale data ilə
- Replikasiya gecikmə ilə
- RTO: dəqiqələr

### 4. Multi-Region Deployment

**Strategiyalar:**

**Active-Active Multi-Region:**
- Hər region bağımsız tam cluster
- DNS-based routing (latency, geo)
- Global database (DynamoDB Global, Spanner)
- Ən yüksək availability, ən çətin

**Active-Passive Multi-Region:**
- Primary region əsas, secondary standby
- Async replication
- DR region yalnız failover zamanı istifadə

**Pilot Light:**
- Secondary region minimal resurslarla işləyir
- DB replication aktivdir
- Fail-over zamanı scale up edilir

**Warm Standby:**
- Secondary region scaled-down produksiya
- Fail-over zamanı full scale

### 5. Data Replication

**Synchronous Replication:**
Yazılar bütün replicas-a yazılana qədər tamamlanmır.
- **Pros:** Zero data loss (RPO=0)
- **Cons:** Yüksək latency, uzaq region-larda praktik deyil
- **Use:** Eyni region, financial

**Asynchronous Replication:**
Primary yazır, replicas arxa planda yenilənir.
- **Pros:** Aşağı latency
- **Cons:** Data loss riski (RPO > 0)
- **Use:** Multi-region, read scaling

**Semi-Synchronous:**
En az bir replica təsdiq edəndə yazı tamamlanır.
- Balanced approach
- MySQL semi-sync

### 6. Chaos Engineering

Chaos engineering - sistemdə qəsdən fail yaradaraq zəif yerləri aşkarlamaq.

**Netflix Principles:**
1. Steady state hypothesis yarat
2. Real-world events variation
3. Production-da testlər apar (təhlükəsiz)
4. Avtomatlaşdır

**Alətlər:**
- **Chaos Monkey** - random instance kill
- **Chaos Gorilla** - AZ outage simulation
- **Chaos Kong** - region outage
- **Gremlin** - managed chaos platform
- **Litmus** - Kubernetes chaos

**Nümunə experiment:**
- Hypothesis: DB crash-a dözərik, cache-dən oxu işləyər
- Action: DB shutdown
- Measure: Error rate, latency
- Learn: Cache TTL uzatmaq lazım

## Arxitektura (Architecture)

### Multi-Region DR Architecture

```
                 Route 53 (Health Check + Failover)
                            │
              ┌─────────────┴─────────────┐
              ↓                           ↓
      ┌───────────────┐           ┌───────────────┐
      │ Primary Region│           │ DR Region     │
      │   (us-east-1) │           │   (us-west-2) │
      └───────┬───────┘           └───────┬───────┘
              │                           │
      ┌───────┴───────┐           ┌───────┴───────┐
      │ Load Balancer │           │ Load Balancer │
      └───────┬───────┘           └───────┬───────┘
              │                           │
      ┌───────┴───────┐           ┌───────┴───────┐
      │ App Servers   │           │ App Servers   │
      │ (Auto Scaling)│           │ (Pilot Light) │
      └───────┬───────┘           └───────┬───────┘
              │                           │
      ┌───────┴───────┐           ┌───────┴───────┐
      │ RDS Primary   │──replica──→│ RDS Replica  │
      │ Multi-AZ      │           │               │
      └───────────────┘           └───────────────┘
              │                           │
              └────────┬──────────────────┘
                       ↓
              S3 Cross-Region Replication
              (Backups, User Files)
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Database Backup Command

```php
<?php
// app/Console/Commands/DatabaseBackup.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup
                            {--type=full : full, incremental}
                            {--upload=s3 : Destination storage}';

    protected $description = 'Backup database and upload to remote storage';

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $type = $this->option('type');
        $filename = "backup_{$type}_{$timestamp}.sql.gz";
        $localPath = storage_path("backups/{$filename}");

        $this->ensureBackupDirectory();

        $this->info("Starting {$type} database backup...");

        $db = config('database.connections.mysql');

        $command = sprintf(
            'mysqldump -h%s -u%s -p%s --single-transaction --quick --lock-tables=false %s | gzip > %s',
            escapeshellarg($db['host']),
            escapeshellarg($db['username']),
            escapeshellarg($db['password']),
            escapeshellarg($db['database']),
            escapeshellarg($localPath)
        );

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(3600); // 1 hour
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Backup failed: ' . $process->getErrorOutput());
            return Command::FAILURE;
        }

        $fileSize = filesize($localPath);
        $this->info("Backup size: " . $this->formatBytes($fileSize));

        // Upload to S3
        if ($this->option('upload') === 's3') {
            $this->uploadToS3($localPath, $filename);
        }

        // Local cleanup (keep last 7 days)
        $this->cleanupOldBackups();

        $this->info('Backup completed successfully');
        return Command::SUCCESS;
    }

    private function uploadToS3(string $localPath, string $filename): void
    {
        $this->info("Uploading to S3...");
        $stream = fopen($localPath, 'r');
        Storage::disk('s3')->putFileAs(
            'backups/database',
            new \Illuminate\Http\File($localPath),
            $filename
        );
        fclose($stream);
        $this->info("Uploaded to S3: backups/database/{$filename}");
    }

    private function cleanupOldBackups(): void
    {
        $cutoff = now()->subDays(7);
        $files = glob(storage_path('backups/backup_*.sql.gz'));
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff->timestamp) {
                unlink($file);
                $this->line("Deleted old backup: " . basename($file));
            }
        }
    }

    private function ensureBackupDirectory(): void
    {
        $dir = storage_path('backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

### Schedule Backups

```php
<?php
// app/Console/Kernel.php (Laravel 10)
// və ya routes/console.php (Laravel 11)

use Illuminate\Support\Facades\Schedule;

Schedule::command('db:backup --type=full')
    ->dailyAt('02:00')
    ->appendOutputTo(storage_path('logs/backup.log'))
    ->onFailure(function () {
        \Notification::route('slack', config('services.slack.webhook'))
            ->notify(new BackupFailedNotification());
    });

Schedule::command('db:backup --type=incremental')
    ->everyFourHours()
    ->appendOutputTo(storage_path('logs/backup-incremental.log'));
```

### Failover Health Checker

```php
<?php
// app/Services/FailoverManager.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FailoverManager
{
    private const CACHE_KEY = 'failover:active_region';
    private const HEALTH_THRESHOLD = 3;

    public function checkPrimaryHealth(): bool
    {
        try {
            $response = Http::timeout(5)->get(config('services.primary.health_url'));

            if (!$response->successful()) {
                $this->incrementFailure('primary');
                return false;
            }

            $this->resetFailures('primary');
            return true;
        } catch (\Exception $e) {
            Log::error('Primary health check failed', ['error' => $e->getMessage()]);
            $this->incrementFailure('primary');
            return false;
        }
    }

    public function shouldFailover(): bool
    {
        return Cache::get('failover:failures:primary', 0) >= self::HEALTH_THRESHOLD;
    }

    public function initiateFailover(): void
    {
        Log::warning('Initiating failover to secondary region');

        // Update DNS (Route 53)
        $this->updateDnsToSecondary();

        // Promote read replica to primary
        $this->promoteReplica();

        // Mark active region
        Cache::put(self::CACHE_KEY, 'secondary', now()->addDay());

        // Notify team
        $this->notifyOncall();

        Log::info('Failover completed');
    }

    private function updateDnsToSecondary(): void
    {
        // AWS SDK: Route 53 health check triggers DNS failover
        // və ya manual Route 53 record update
    }

    private function promoteReplica(): void
    {
        // AWS RDS: aws rds promote-read-replica
        // Manual promotion or automated via Lambda
    }

    private function incrementFailure(string $region): void
    {
        $key = "failover:failures:{$region}";
        $failures = Cache::increment($key);
        Cache::expire($key, 300); // 5 minutes

        if ($failures >= self::HEALTH_THRESHOLD && !$this->isFailedOver()) {
            $this->initiateFailover();
        }
    }

    private function resetFailures(string $region): void
    {
        Cache::forget("failover:failures:{$region}");
    }

    private function isFailedOver(): bool
    {
        return Cache::get(self::CACHE_KEY) === 'secondary';
    }

    private function notifyOncall(): void
    {
        // PagerDuty, Slack, SMS
    }
}
```

### Read Replica Usage

```php
<?php
// config/database.php

'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1'),
            env('DB_READ_HOST_2'),
        ],
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST'),
    ],
    'sticky' => true, // After write, read from master in same request
    'driver' => 'mysql',
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

### S3 Cross-Region Replication

```php
<?php
// config/filesystems.php

'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
    ],

    's3_dr' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DR_REGION', 'us-west-2'),
        'bucket' => env('AWS_DR_BUCKET'),
    ],
],
```

### Chaos Engineering Command

```php
<?php
// app/Console/Commands/ChaosTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ChaosTest extends Command
{
    protected $signature = 'chaos:run
                            {experiment : Experiment name}
                            {--duration=60 : Duration in seconds}';

    protected $description = 'Run chaos engineering experiments';

    public function handle(): int
    {
        $experiment = $this->argument('experiment');

        if (!$this->confirm("Run chaos experiment '{$experiment}' in " . app()->environment() . "?")) {
            return Command::FAILURE;
        }

        match($experiment) {
            'redis-down' => $this->redisDown(),
            'slow-db' => $this->slowDatabase(),
            'memory-pressure' => $this->memoryPressure(),
            default => $this->error("Unknown experiment: {$experiment}"),
        };

        return Command::SUCCESS;
    }

    private function redisDown(): void
    {
        $this->info("Simulating Redis outage for {$this->option('duration')}s...");

        Cache::put('chaos:redis:disabled', true, $this->option('duration'));

        sleep((int) $this->option('duration'));

        Cache::forget('chaos:redis:disabled');
        $this->info('Experiment completed. Check dashboards.');
    }

    private function slowDatabase(): void
    {
        $this->info('Injecting 500ms latency to all DB queries...');
        // DB listener ilə delay əlavə et
    }

    private function memoryPressure(): void
    {
        $this->info('Creating memory pressure...');
        $data = [];
        $endTime = time() + (int) $this->option('duration');
        while (time() < $endTime) {
            $data[] = str_repeat('x', 1024 * 1024); // 1MB
            if (count($data) > 100) break; // Safety
            usleep(100000);
        }
    }
}
```

## Real-World Nümunələr

- **AWS Outage 2017 (S3 us-east-1)** - 4 saat downtime, milyonlarla sayt təsirləndi
- **GitLab 2017** - Accidental `rm -rf` production DB, 6 saat data itkisi (backup test olunmamışdı)
- **Facebook 2021** - BGP misconfiguration, 6 saat global outage
- **OVH Data Center Fire 2021** - Strasburgda yanğın, 3.6 milyon sayt təsirləndi
- **Netflix** - Chaos Monkey, multi-region deployment standart
- **Stripe** - Active-active multi-region, zero-downtime failover
- **Amazon.com** - Cell-based architecture, regional isolation

## Interview Sualları

**Q1: RTO və RPO fərqi?**
RTO (Recovery Time Objective) - downtime tolerance, sistem nə qədər tez bərpa olunmalıdır. RPO (Recovery Point Objective) - data loss tolerance, nə qədər son data itkisi qəbul edilə bilər. Məs., RTO=1 saat, RPO=5 dəqiqə - 1 saat ərzində bərpa ol, son 5 dəqiqənin data-sı itə bilər. Hər ikisi 0-a yaxınlaşdıqca xərc eksponensial artır.

**Q2: 3-2-1 backup qaydası nədir?**
3 nüsxə data, 2 fərqli media tipi (disk + tape/cloud), 1 nüsxə fərqli coğrafi yerdə (offsite). Bu qayda tək fail point-dən qoruyur - disk xarabdır, amma cloud-da var; data center yanır, amma offsite-də var. Modern variant: 3-2-1-1-0 (1 offsite + 1 offline/air-gapped, 0 errors in recovery tests).

**Q3: Active-Active və Active-Passive fərqi?**
Active-Active - bütün region/server-lər trafik qəbul edir, load bölünür. Fail olarsa digərləri davam edir, minimal RTO. Amma data consistency çətindir (conflict resolution). Active-Passive - primary aktiv, secondary standby. Daha sadə, amma failover zamanı downtime var. Active-Active daha bahalı amma resilient.

**Q4: Chaos engineering nə üçün lazımdır?**
Traditional testing known issues-ı test edir. Chaos engineering məlum olmayan, production-only problemləri aşkar edir: network latency, partial failures, cascading failures. Netflix kəşf etdi ki, testdə working-dir amma production-da AZ outage-da fail edir. Chaos Monkey ilə proaktiv test edirlər. "Break things on purpose" - real fəlakətdən öncə zəif yerləri tap.

**Q5: Database failover necə işləyir?**
MySQL nümunə:
1. Primary DB monitoring altındadır (MHA, Orchestrator)
2. Primary fail edir (health check fail)
3. Orchestrator quorum ilə ən yeni replica-nı seçir
4. Seçilən replica primary-yə promote olunur
5. Digər replicas yeni primary-yə qoşulur
6. Application DNS/proxy yenilənir
Tipik RTO: 30s-2min. Managed services (RDS Multi-AZ) avtomatlaşdırır.

**Q6: Pilot Light nədir?**
Pilot Light DR strategiyası - secondary region-da minimal core infrastructure işləyir:
- DB replikasiyası aktiv (data hazır)
- Application server-lər söndürülmüş və ya minimal
- Infrastructure-as-Code hazır
Failover zamanı: AMIs-dan instance-lar start olunur, autoscaling açılır, DNS yönləndirilir.
RTO: 10-30 dəqiqə, orta xərc, yaxşı balans.

**Q7: Cross-region replication-ın çətinlikləri?**
- **Latency** - region-lar arası 50-200ms, sinxron replikasiya praktik deyil
- **Consistency** - async replikasiya data loss riski
- **Cost** - cross-region traffic bahalıdır
- **Conflict resolution** - active-active-də concurrent writes
- **Schema changes** - bütün region-larda sinxron apply etmək çətin
- **Compliance** - data residency qanunları (GDPR, Azərbaycan data yerli qalmalı)

**Q8: Backup test niyə vacibdir və necə edilir?**
"Test edilməmiş backup mövcud deyil" - bir çox şirkətdə restore zamanı backup korrupt çıxır. Test pattern-ləri:
1. **Quarterly full restore** - test ortamına bərpa
2. **Automated restore testing** - hər gün random backup-dan restore
3. **Checksum verification** - file integrity
4. **Application-level tests** - bərpa edilən DB-də query-lər
5. **Disaster simulation drill** - illik tam DR test
Gitlab 2017 backup test etməmişdi, 5 backup metodundan heç biri işləmədi.

**Q9: Split-brain multi-region-da necə həll olunur?**
Split-brain (hər iki region özünü primary hesab edir):
- **Quorum-based** - hər cluster üçün 3+ region (quorum təmin et)
- **Witness/tiebreaker** - 3-cü kiçik region tiebreaker kimi
- **Fencing** - köhnə primary-ni network-dən kəs (STONITH)
- **Manual intervention** - avtomatik failover etmə, insan təsdiqləsin
- **Conflict resolution** - CRDT, last-write-wins
Banking sistemlər manual failover seçir (data integrity > availability).

**Q10: Runbook nədir və niyə lazımdır?**
Runbook - fəlakət zamanı addım-addım təlimat. Müzakirə vaxtı yoxdur, stress altında aydın plan lazımdır. Yaxşı runbook:
- Trigger (nə olanda icra et)
- Prerequisite (kim, hansı icazələr)
- Steps (konkret komandalar)
- Rollback (geri qayıtmaq)
- Verification (uğurlu olduğunu necə yoxlamaq)
- Escalation (kim-ə xəbər vermək)

Təcrübəli DevOps komandaları runbook-u test edir, yeni işçiləri onunla öyrədir.

## Best Practices

1. **Test backups regularly** - Quarterly restore drills
2. **Automate everything** - Manual steps fail source-u
3. **Document runbooks** - Stress altında improvise etmə
4. **Multi-AZ minimum** - Tək AZ-dən asılı olma
5. **Monitor replication lag** - RPO təminatı
6. **Practice chaos engineering** - Proaktiv resilience
7. **Immutable backups** - Ransomware-dən qoruma (WORM)
8. **Encrypt backups** - At rest və in transit
9. **Test DNS TTL** - Failover-da DNS cache problem-dir
10. **Separate credentials** - DR environment ayrı access
11. **Measure MTTR** - Mean Time To Recovery track et
12. **Postmortem culture** - Blameless retrospectives
13. **Geographic diversity** - Regionlar uzaq olmalıdır (fault domains)
14. **Test with production data** - Staging real scenario əks etdirməlidir
15. **Define SLA/SLO clearly** - Business ilə uyğunlaşdır
16. **Keep backups offline/air-gapped** - Network-dən ayrılmış
17. **Version application with DB migrations** - Schema backward-compat
18. **Warm up cache before cutover** - Cache miss storm qarşısı
19. **Graceful degradation** - Tam fail etmə, feature disable et
20. **Regular DR drills** - Illik tam simulation
