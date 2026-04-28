# Data Archival & Table Partitioning (Lead)

## Problem Təsviri

Real ssenari: E-commerce platforma, 5 il əvvəl yaradılıb. `orders` cədvəli indi **500M sətir**, hər həftə 2-3M yeni sifariş əlavə olunur.

Vəziyyət:
- Sadə `WHERE user_id = ? AND created_at > ?` query: əvvəl 10ms, indi **8 saniyə**
- Index-lər artıq RAM-a sığmır (50GB index, server 32GB RAM)
- `VACUUM`/`ANALYZE` saatlarla çəkir, production-da DB performansını öldürür
- Backup 6 saat çəkir, restore 12 saat
- `DELETE FROM orders WHERE created_at < '2020-01-01'` lock yaradır, deadlock olur

```
orders cədvəlinin böyüməsi:
  2020: 50M sətir, 5GB index, queries < 50ms
  2022: 200M sətir, 20GB index, queries 200-500ms
  2024: 500M sətir, 50GB index, queries 2-8s

Index buffer pool-a sığmadıqda:
  Query → index page disk-dən oxunur → I/O wait
  100x daha yavaş
```

### Problem niyə yaranır?

Səbəblər **fundamental**:

1. **Index size linear olaraq böyüyür** — 500M sətir ilə B-tree-nin yarpaq səviyyəsində milyonlarla səhifə var. Bütün index RAM-a sığmadıqda, hər query 2-3 səhifə disk-dən oxumalıdır (B-tree depth 4-5). HDD-də bu 30-50ms, SSD-də 1-3ms.

2. **VACUUM/ANALYZE bütün cədvəli scan edir** — PostgreSQL/MySQL statistikanı yeniləməlidir, dead tuple-ları təmizləməlidir. 500M sətir cədvəlində bu prosesi tam icra etmək saatlarla çəkir və I/O bandwidth-i yığır.

3. **Backup vaxtı üstəl olunur** — `mysqldump`/`pg_dump` cədvəli sequential oxuyur. 500GB cədvəl + indeks = 6 saat read.

4. **Sıfırla silmə blok yaradır** — `DELETE FROM orders WHERE created_at < '...'` 50M sətir silsə, hər sətir transaction log-a yazılır (WAL bloating), index-lərdən silinir, foreign key check edilir. 30 dəqiqə ərzində table-level lock olur.

Həll **partitioning** (cədvəli daxili olaraq parçalamaq) və ya **archival** (köhnə dataı ayrı cədvələ köçürmək) və ya hər ikisidir.

---

## Həll 1: Table Partitioning (RANGE)

### Konsept

Cədvəli **partition-lara** bölürük (məsələn, ildə bir partition). Query plannerdə **partition pruning** baş verir — `WHERE created_at >= '2024-01-01'` query-si yalnız 2024-cü il partition-ını scan edir, qalanları skip edir.

```
orders (logical view)
  ├── orders_p2021 (50M sətir, 5GB)
  ├── orders_p2022 (75M sətir, 7GB)
  ├── orders_p2023 (100M sətir, 10GB)
  ├── orders_p2024 (150M sətir, 15GB) ← son year, hot
  ├── orders_p2025 (125M sətir, 12GB) ← current year, hottest
  └── orders_p_future (placeholder)

Query: WHERE created_at >= '2025-01-01'
  → DB yalnız orders_p2025 scan edir
  → Index 12GB → RAM-a sığır → 10ms cavab
```

### MySQL Partitioning

*Bu kod mövcud cədvəli range partitioning-li yeni cədvələ köçürmək üçün migration-ı göstərir:*

```sql
-- 1. Yeni partitioned cədvəl yarat
CREATE TABLE orders_partitioned (
    id BIGINT NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    -- ... digər sütunlar

    -- KRITIK: partition column primary key-də olmalıdır
    PRIMARY KEY (id, created_at),
    KEY idx_user_created (user_id, created_at),
    KEY idx_status (status, created_at)
)
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2021 VALUES LESS THAN (2022),
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

**Vacib qeyd:** MySQL-də partition column **mütləq** primary key-in bir hissəsi olmalıdır. Buna görə `PRIMARY KEY (id, created_at)` istifadə edirik (təkcə `id` yox).

### PostgreSQL Partitioning (declarative, version 11+)

*Bu kod PostgreSQL-də range partitioning-li cədvəli göstərir:*

```sql
-- 1. Parent (logical) table
CREATE TABLE orders (
    id BIGSERIAL,
    user_id BIGINT NOT NULL,
    status VARCHAR(20) NOT NULL,
    total NUMERIC(10,2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL,
    PRIMARY KEY (id, created_at)
) PARTITION BY RANGE (created_at);

-- 2. İllik partition-lar
CREATE TABLE orders_2023
    PARTITION OF orders
    FOR VALUES FROM ('2023-01-01') TO ('2024-01-01');

CREATE TABLE orders_2024
    PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2025-01-01');

CREATE TABLE orders_2025
    PARTITION OF orders
    FOR VALUES FROM ('2025-01-01') TO ('2026-01-01');

-- 3. İndeks-lər (hər partition-da avtomatik yaranır)
CREATE INDEX ON orders (user_id, created_at);
CREATE INDEX ON orders (status, created_at);
```

`pg_partman` extension istifadə edərək partition yaratmanı avtomatlaşdırmaq olar:

```sql
SELECT partman.create_parent(
    p_parent_table => 'public.orders',
    p_control => 'created_at',
    p_type => 'range',
    p_interval => '1 year',
    p_premake => 2  -- 2 il qabaqcadan partition yarat
);
```

### Yeni Partition Avtomatik Yaratmaq

*Bu kod hər ilin sonunda yeni partition əlavə edən Laravel command-ını göstərir:*

```php
// app/Console/Commands/AddYearlyPartitionCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddYearlyPartitionCommand extends Command
{
    protected $signature = 'db:add-yearly-partition 
                            {--year= : Partition yaratmaq üçün il (default: növbəti il)}
                            {--table=orders}';

    protected $description = 'Növbəti il üçün yeni partition əlavə edir';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?? date('Y') + 1);
        $nextYear = $year + 1;
        $table = $this->option('table');
        $partition = "p{$year}";

        // Yoxla — partition artıq mövcuddurmu?
        $exists = DB::selectOne("
            SELECT COUNT(*) as cnt
            FROM information_schema.partitions
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND partition_name = ?
        ", [$table, $partition]);

        if ($exists->cnt > 0) {
            $this->info("Partition {$partition} artıq mövcuddur.");
            return self::SUCCESS;
        }

        // p_future-i reorganize et — yeni partition əlavə et
        DB::statement("
            ALTER TABLE {$table} REORGANIZE PARTITION p_future INTO (
                PARTITION {$partition} VALUES LESS THAN ({$nextYear}),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");

        $this->info("Partition {$partition} {$table} cədvəlinə əlavə edildi.");
        return self::SUCCESS;
    }
}
```

```php
// app/Console/Kernel.php
Schedule::command('db:add-yearly-partition')
    ->yearlyOn(11, 1, '02:00') // Hər il 1 noyabr
    ->onOneServer();
```

---

## Həll 2: Data Archival (Köhnə Dataı Köçürmək)

Partition pruning gözəldir, amma **2+ illik data heç vaxt sorğulanmırsa**, niyə production DB-də saxlamalı? Onu **archive table**-a köçürün.

### Arxitektura

```
Production DB                   Archive DB / Cold Table
┌─────────────────┐             ┌─────────────────┐
│ orders          │             │ orders_archive  │
│ (son 2 il)      │  ────►      │ (2+ il əvvəl)   │
│ ~150M sətir     │  archival   │ ~350M sətir     │
│ Hot (active)    │   job       │ Cold (rare)     │
└─────────────────┘             └─────────────────┘

Query routing:
  Last 2 years   → orders (fast)
  Older          → orders_archive (slow OK)
```

### Migration

*Bu kod archive cədvəlini yaradır:*

```php
// database/migrations/2024_01_create_orders_archive_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders_archive', function (Blueprint $table) {
            $table->bigInteger('id')->primary();
            $table->bigInteger('user_id');
            $table->string('status', 20);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('archived_at')->useCurrent();

            // Archive-də az index — query rare-dir, write-i yavaşlatmayaq
            $table->index('user_id');
            $table->index('created_at');
        });
    }
};
```

### Archival Command (Chunked, Safe)

*Bu kod köhnə sifarişləri batch-batch archive cədvəlinə köçürən və silən command-ı göstərir:*

```php
// app/Console/Commands/ArchiveOldOrdersCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveOldOrdersCommand extends Command
{
    protected $signature = 'orders:archive
                            {--before-years=2 : Bu qədər il əvvəlki orders}
                            {--batch-size=1000}
                            {--max-batches=1000 : Job session-də max batch}
                            {--dry-run : Heç nə dəyişmə, sadəcə hesabla}';

    protected $description = 'Köhnə sifarişləri archive cədvəlinə köçürür';

    public function handle(): int
    {
        $cutoff = now()->subYears($this->option('before-years'))->startOfDay();
        $batchSize = (int) $this->option('batch-size');
        $maxBatches = (int) $this->option('max-batches');
        $dryRun = $this->option('dry-run');

        $this->info("Archiving orders before: {$cutoff->toDateString()}");
        $this->info("Batch size: {$batchSize}, Max batches: {$maxBatches}");

        if ($dryRun) {
            $count = DB::table('orders')
                ->where('created_at', '<', $cutoff)
                ->where('status', 'completed')
                ->count();
            $this->warn("DRY RUN: {$count} sətir archive ediləcəkdi.");
            return self::SUCCESS;
        }

        $totalArchived = 0;
        $batchNum = 0;
        $startTime = microtime(true);

        do {
            $batchNum++;

            // 1. Batch ID-lərini əldə et
            $orderIds = DB::table('orders')
                ->where('created_at', '<', $cutoff)
                ->where('status', 'completed')
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id')
                ->toArray();

            if (empty($orderIds)) {
                break;
            }

            // 2. Atomic köçürmə — transaction içində
            try {
                DB::transaction(function () use ($orderIds) {
                    // Archive cədvəlinə kopyala
                    DB::statement('
                        INSERT INTO orders_archive 
                        (id, user_id, status, total, currency, created_at, updated_at, archived_at)
                        SELECT id, user_id, status, total, currency, created_at, updated_at, NOW()
                        FROM orders 
                        WHERE id IN (' . implode(',', array_fill(0, count($orderIds), '?')) . ')
                    ', $orderIds);

                    // Originaldan sil
                    DB::table('orders')->whereIn('id', $orderIds)->delete();
                }, attempts: 3);

                $totalArchived += count($orderIds);

                if ($batchNum % 10 === 0) {
                    $rate = round($totalArchived / (microtime(true) - $startTime));
                    $this->info("Batch {$batchNum}: {$totalArchived} archived ({$rate}/s)");
                }
            } catch (\Throwable $e) {
                Log::error('Archive batch failed', [
                    'batch' => $batchNum,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Batch {$batchNum} failed: {$e->getMessage()}");
                continue;
            }

            // 3. DB-yə nəfəs aldır — yüksək write load-da production-u sıxma
            usleep(100_000); // 100ms

        } while ($batchNum < $maxBatches);

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Done. Total archived: {$totalArchived} in {$duration}s");

        return self::SUCCESS;
    }
}
```

### Schedule

```php
// app/Console/Kernel.php
Schedule::command('orders:archive --batch-size=1000 --max-batches=500')
    ->monthlyOn(1, '03:00')   // Hər ayın 1-də saat 03:00
    ->onOneServer()
    ->runInBackground()
    ->onFailure(function () {
        Notification::route('slack', config('alerts.slack_devops'))
            ->notify(new ArchivalFailedNotification());
    });
```

---

## Həll 3: Cold Storage (S3 + Parquet)

5+ il əvvəlki data **bir ildə bir-iki dəfə** sorğulanır (audit, legal). Onu DB-də saxlamağa dəyməz — S3 Glacier-ə export edin.

*Bu kod illik archive datasını CSV-yə export edib S3 Glacier-a yükləyən job-u göstərir:*

```php
// app/Jobs/ExportYearlyArchiveToS3Job.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ExportYearlyArchiveToS3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 saat

    public function __construct(
        public int $year
    ) {}

    public function handle(): void
    {
        $tmpFile = storage_path("tmp/orders_archive_{$this->year}.csv");

        if (!is_dir(dirname($tmpFile))) {
            mkdir(dirname($tmpFile), 0755, true);
        }

        $file = fopen($tmpFile, 'w');
        fputcsv($file, ['id', 'user_id', 'status', 'total', 'currency', 'created_at', 'updated_at']);

        $rowCount = 0;

        DB::table('orders_archive')
            ->whereYear('created_at', $this->year)
            ->orderBy('id')
            ->chunkById(10_000, function ($rows) use ($file, &$rowCount) {
                foreach ($rows as $row) {
                    fputcsv($file, [
                        $row->id, $row->user_id, $row->status,
                        $row->total, $row->currency,
                        $row->created_at, $row->updated_at,
                    ]);
                    $rowCount++;
                }
            });

        fclose($file);

        // Gzip et — disk-də və S3-də ~10x kiçik
        $gzFile = "{$tmpFile}.gz";
        $this->gzipFile($tmpFile, $gzFile);

        // S3 Glacier-a yüklə (cheaper storage class)
        $s3Path = "archives/orders/{$this->year}/orders_{$this->year}.csv.gz";
        Storage::disk('s3')->put($s3Path, fopen($gzFile, 'r'), [
            'StorageClass' => 'GLACIER', // və ya DEEP_ARCHIVE
        ]);

        // Cleanup
        unlink($tmpFile);
        unlink($gzFile);

        // Audit log
        DB::table('archive_exports')->insert([
            'year' => $this->year,
            'row_count' => $rowCount,
            's3_path' => $s3Path,
            'exported_at' => now(),
        ]);

        Log::info('Archive exported to S3', [
            'year' => $this->year,
            'rows' => $rowCount,
            's3_path' => $s3Path,
        ]);

        // Lokal archive-dan sil
        DB::table('orders_archive')
            ->whereYear('created_at', $this->year)
            ->delete();
    }

    private function gzipFile(string $source, string $dest): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($dest, 'wb9');

        while (!feof($in)) {
            gzwrite($out, fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
    }
}
```

---

## Trade-offs

| Yanaşma | Query Performance | Storage Cost | Complexity | Compliance | Nə zaman |
|---------|------------------|--------------|------------|------------|----------|
| **Heç nə etməmək** | Time-lə pisləşir | Eyni | 0 | OK | < 100M sətir |
| **Partitioning** | 10-100x sürətli (pruning) | Eyni | Orta | OK | Eyni cədvəldə hot+warm |
| **Archival** | Hot table sürətli | Daha az production | Yüksək | OK | Cold data > 2 il |
| **Cold storage (S3)** | N/A (export tələb) | Çox aşağı | Yüksək | OK + cheap | Cold data > 5 il |
| **Sadəcə silmək** | Hot, sadə | Aşağı | Aşağı | RİSK — GDPR right to access | Heç compliance riski yoxdursa |

**Praktiki kombinasiya:** Partitioning (son 2 il) + Archival (2-5 il) + Cold storage (> 5 il).

---

## Anti-patternlər

**1. Partition column-unu primary key-də saxlamamaq (MySQL)**
`PRIMARY KEY (id)` ilə partitioning **işləmir** — MySQL hata verir: "A PRIMARY KEY must include all columns in the table's partitioning function". Həll: `PRIMARY KEY (id, created_at)`. Bu o deməkdir ki, `id` artıq tək başına unique deyil — composite-dir.

**2. Yanlış column-a partition etmək**
`PARTITION BY HASH(user_id)` populyar görünə bilər, amma əgər query-lərinizin əksəriyyəti `WHERE created_at >= ...` olarsa, partition pruning baş vermir — DB **bütün** partition-ları scan edir. Partition strategiyası query pattern-ə uyğun olmalıdır.

**3. Partition pruning-i test etməmək**
Partitioning yaratdıqdan sonra `EXPLAIN PARTITIONS SELECT ...` ilə yoxla. Əgər `partitions` sütununda `p2021,p2022,p2023,p2024,p2025` görünürsə (hamısı), pruning **işləmir**. Adətən səbəb: query function istifadə edir (`WHERE YEAR(created_at) = 2024` — pruning baş vermir; `WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01'` baş verir).

**4. Silmə əvəzinə archive etməmək (GDPR)**
GDPR right to erasure — user "Mənim datamı silin" deyə bilər. Archived data da silinməlidir. Archive prosesinizdə **deletion mechanism** olmalıdır: user_id-yə görə həm production həm archive-dan sil.

**5. Archive-ı transaction-sız etmək**
`INSERT INTO archive ...; DELETE FROM orders ...;` — iki ayrı statement. Birinci uğurlu, ikinci uğursuz olsa, **dublikat data** yaranır. Mütləq DB transaction içində icra edin.

**6. Partitioned cədvəldə global unique index**
MySQL: partition key-i daxil etməyən unique index **mümkün deyil**. PostgreSQL: mümkündür amma performance cost-u var (hər partition-a yoxlayır). Solution: unique-i (id, partition_key) kombinasiyasına salın və ya application-level uniqueness check edin.

**7. Archive schedule-nu test etməmək production-dan əvvəl**
İlk dəfə run olunduqda archive job 50M sətir köçürür. DB-yə CPU/IO yüklənir, production yavaşlayır. Solution: staging-də test et, batch size və `usleep()` tune et, off-peak hours-da run et, monitoring qur.

---

## Interview Sualları və Cavablar

**S: Partition pruning nədir, necə işləyir?**
C: Partition pruning — query planner-in **WHERE clause-a baxaraq** yalnız relevant partition-ları scan etməsidir. Misal: `WHERE created_at >= '2024-06-01'` query-si ilə MySQL planner görür ki, bu predicate yalnız `p2024` və `p2025` partition-larında rows ola bilər — qalan partition-ları skip edir. `EXPLAIN PARTITIONS` ilə yoxlanır. Pruning baş verməyəndə query bütün partition-ları scan edir — sürət üstünlüyü itir.

**S: Wrong partition key examples nədir?**
C: 3 əsas səhv: (1) **HASH(user_id) partitioning** + temporal query (`WHERE created_at >= ...`) — DB hər partition-a baxmalıdır, pruning yox. (2) **RANGE(id)** + user-based query — auto-increment ID range-i ilə user-based query uyğun gəlmir. (3) **Wrong granularity** — illik partitioning amma query 1 günlük data axtarır → 1/365 partition oxunur, amma 1 partition hələ də 100M sətir ola bilər. Solution: query pattern-ə uyğun partition seç (vaxt-əsaslı sistemlərdə RANGE by date adi seçimdir).

**S: Archival vs partitioning — hansını istifadə edirsiniz?**
C: Hər ikisini birlikdə. **Partitioning** son 2-3 il üçün (queries hələ də gedir, performance vacibdir). **Archival** 2-5 il arası (production DB-də yer tutmasın, amma mövcud olsun — admin nadir hallarda sorğu edir). **Cold storage (S3)** 5+ il (yalnız audit/legal — DB-də saxlamağa dəyməz). Bu 3 layer kombinasiyası optimal cost/performance verir.

**S: GDPR right to erasure və archival — necə həll edirsiniz?**
C: GDPR Article 17 — user "Mənim datamı silin" deyə bilər və **bütün storage-larda** silinməlidir, archive daxil. Solution: (1) `user_data_deletion_requests` cədvəli — request-ləri track et. (2) Cron job archive cədvəlində + S3-də user_id-yə görə silmə icra edir. (3) Audit log — silmənin vaxtını və müvəffəqiyyətini sübut edə bilməlisiniz. (4) Legal hold — bəzi data (financial records) müəyyən müddət saxlanmalıdır → user-ə "30 gün sonra siləcəyik" cavab verə bilərsiniz.

**S: Mövcud böyük cədvəli partitioned-ə necə migrate edirsiniz (downtime olmadan)?**
C: 4 addım: (1) **Yeni partitioned table yarat** (orders_partitioned). (2) **Dual-write** — application yeni cədvələ də yazır (Outbox pattern və ya database trigger). (3) **Backfill** — köhnə dataı chunked olaraq köçür (1M sətir/batch, off-peak). (4) **Cut-over** — read-leri yeni cədvələ yönəlt, sonra köhnəni rename + drop et. Bu pattern adətən "expand-contract" və ya "blue-green schema migration" adlanır. Tools: gh-ost (MySQL), pg_repack (PostgreSQL).

---

## Əlaqəli Mövzular

- [19-zero-downtime-db-migration.md](19-zero-downtime-db-migration.md) — Schema migration without downtime
- [22-large-dataset-export.md](22-large-dataset-export.md) — Bulk export to CSV/S3
- [16-audit-and-gdpr-compliance.md](16-audit-and-gdpr-compliance.md) — GDPR compliance
- [45-soft-delete-gdpr.md](45-soft-delete-gdpr.md) — Soft delete patterns
