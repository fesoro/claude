# Scheduled Reports & Batch Export (Lead)

## Problem
- Admin dashboard: hər həftə CEO-ya revenue report email
- User-ə aylıq invoice PDF
- Ambar report gündəlik CSV export
- Report generate 30 dəq+ çəkə bilər (milyonlarla row)
- Failure tolerance, retry, audit

---

## Həll: Job-based pipeline + chunked processing + S3 storage

```
Schedule trigger (cron)
     ↓
Report Job dispatched (queue)
     ↓
Chunked data fetch (memory-safe)
     ↓
Format (PDF/CSV/Excel)
     ↓
Upload to S3 (encrypted)
     ↓
Email with signed URL (7 gün valid)
     ↓
Audit log
```

---

## 1. Scheduled cron

```php
<?php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Həftəlik executive report
    $schedule->job(new GenerateWeeklyRevenueReport)
        ->weeklyOn(Schedule::MONDAY, '08:00')
        ->timezone('Asia/Baku')
        ->onOneServer()        // multi-server: yalnız 1 server execute
        ->runInBackground();
    
    // Aylıq invoice (bütün user-lər üçün)
    $schedule->command('invoices:generate-monthly')
        ->monthlyOn(1, '03:00')
        ->withoutOverlapping(120)   // 2 saat lock
        ->onOneServer()
        ->emailOutputOnFailure('devops@example.com');
    
    // Gündəlik ambar export
    $schedule->job(new ExportWarehouseDataJob)
        ->dailyAt('04:00')
        ->timezone('UTC')
        ->onFailure(function () {
            Log::critical('Warehouse export failed');
            Notification::route('slack', config('alerts.slack'))
                ->notify(new JobFailedNotification('warehouse-export'));
        });
}
```

---

## 2. Chunked batch processing (memory safe)

```php
<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateWeeklyRevenueReport implements ShouldQueue
{
    use Queueable;
    
    public int $timeout = 3600;   // 1 saat
    public int $tries = 2;
    public int $backoff = 600;    // 10 dəq retry
    
    public function handle(): void
    {
        $start = now()->subWeek();
        $end = now();
        
        $path = storage_path("reports/revenue-" . $end->format('Y-W') . ".csv");
        $handle = fopen($path, 'w');
        fputcsv($handle, ['date', 'orders', 'revenue', 'avg_order']);
        
        // Chunk — 10k row-luq batch-lar
        Order::where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_order')
            ->groupBy('date')
            ->orderBy('date')
            ->chunkById(1000, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [$row->date, $row->orders, $row->revenue, round($row->avg_order, 2)]);
                }
            });
        
        fclose($handle);
        
        // Upload S3
        $s3Path = "reports/weekly/revenue-" . $end->format('Y-W') . ".csv";
        Storage::disk('s3')->put($s3Path, file_get_contents($path), [
            'ServerSideEncryption' => 'AES256',
            'CacheControl' => 'private, max-age=604800',
        ]);
        
        // Signed URL (7 gün)
        $url = Storage::disk('s3')->temporaryUrl($s3Path, now()->addWeek());
        
        // Email
        Mail::to(config('reports.recipients'))
            ->send(new WeeklyRevenueReportMail($url, $start, $end));
        
        // Cleanup local
        unlink($path);
        
        // Audit
        ReportLog::create([
            'type'        => 'weekly-revenue',
            'period_from' => $start,
            'period_to'   => $end,
            's3_path'     => $s3Path,
            'row_count'   => Order::where('status', 'paid')->whereBetween('created_at', [$start, $end])->count(),
            'generated_at'=> now(),
        ]);
    }
}
```

---

## 3. Large dataset (streaming export)

```php
<?php
// Problem: 10M order-lu export — memory OOM
// Həll: lazy collection + file streaming

class ExportWarehouseDataJob implements ShouldQueue
{
    public int $timeout = 7200;   // 2 saat
    
    public function handle(): void
    {
        $path = storage_path('warehouse-' . now()->format('Y-m-d') . '.csv');
        $fh = fopen($path, 'w');
        fputcsv($fh, ['sku', 'name', 'stock', 'location', 'last_updated']);
        
        // Lazy cursor — PDO server-side cursor, memory efficient
        DB::table('warehouse_items')
            ->orderBy('sku')
            ->lazy(1000)          // Laravel 9+ lazy collection
            ->each(function ($item) use ($fh) {
                fputcsv($fh, [$item->sku, $item->name, $item->stock, $item->location, $item->updated_at]);
            });
        
        fclose($fh);
        
        // Compress (10 GB → 1 GB)
        $gzPath = $path . '.gz';
        exec("gzip -9 {$path} -c > {$gzPath}");
        
        // Multipart upload S3 (1 GB+ üçün)
        $this->uploadLarge($gzPath, 'warehouse/' . basename($gzPath));
        
        unlink($path);
        unlink($gzPath);
    }
    
    private function uploadLarge(string $path, string $key): void
    {
        $client = Storage::disk('s3')->getClient();
        
        $uploader = new \Aws\S3\MultipartUploader($client, $path, [
            'bucket'   => config('filesystems.disks.s3.bucket'),
            'key'      => $key,
            'part_size' => 10 * 1024 * 1024,    // 10 MB parts
        ]);
        
        try {
            $result = $uploader->upload();
        } catch (\Aws\Exception\MultipartUploadException $e) {
            Log::error('S3 multipart failed', ['exception' => $e]);
            throw $e;
        }
    }
}
```

---

## 4. PDF invoice generation

```php
<?php
// composer require dompdf/dompdf  (light)
// composer require spatie/laravel-pdf  (Puppeteer, heavier but HTML5/CSS3)

use Spatie\LaravelPdf\Facades\Pdf;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate-monthly';
    
    public function handle(): int
    {
        $month = now()->subMonth();
        
        User::whereHas('subscription', fn($q) => $q->where('status', 'active'))
            ->chunk(100, function ($users) use ($month) {
                foreach ($users as $user) {
                    GenerateInvoiceJob::dispatch($user->id, $month->format('Y-m'))
                        ->onQueue('invoices');
                }
            });
        
        $this->info('Invoice generation jobs queued');
        return self::SUCCESS;
    }
}

class GenerateInvoiceJob implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 300;
    
    public function __construct(public int $userId, public string $month) {}
    
    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        $payments = Payment::where('user_id', $this->userId)
            ->where('created_at', 'like', "{$this->month}%")
            ->get();
        
        if ($payments->isEmpty()) return;
        
        // PDF render
        $invoiceNumber = "INV-{$this->month}-{$user->id}";
        $pdfContent = Pdf::view('invoices.template', [
                'user'     => $user,
                'payments' => $payments,
                'number'   => $invoiceNumber,
                'month'    => $this->month,
            ])
            ->format('a4')
            ->margins(20, 20, 20, 20)
            ->base64();
        
        // Upload S3
        $path = "invoices/{$user->id}/{$invoiceNumber}.pdf";
        Storage::disk('s3')->put($path, base64_decode($pdfContent), ['ServerSideEncryption' => 'AES256']);
        
        // DB record
        Invoice::create([
            'user_id'  => $user->id,
            'number'   => $invoiceNumber,
            'month'    => $this->month,
            's3_path'  => $path,
            'total'    => $payments->sum('amount'),
        ]);
        
        // Email
        $signedUrl = Storage::disk('s3')->temporaryUrl($path, now()->addMonth());
        Mail::to($user)->send(new InvoiceMail($invoiceNumber, $signedUrl));
    }
    
    public function failed(\Throwable $e): void
    {
        Log::error("Invoice generation failed for user {$this->userId}", ['exception' => $e]);
    }
}
```

---

## 5. Progress tracking (UI feedback)

```php
<?php
// User admin panel-dən "Generate Report" tıklayır
// Real-time progress göstər

class ReportGenerationController
{
    public function start(Request $req): JsonResponse
    {
        $reportId = (string) Str::uuid();
        
        // Progress state-i Redis-də
        Redis::hset("report:$reportId", [
            'status'   => 'queued',
            'progress' => 0,
            'user_id'  => auth()->id(),
            'started_at' => now()->toIso8601String(),
        ]);
        Redis::expire("report:$reportId", 3600);
        
        GenerateCustomReportJob::dispatch($reportId, $req->all());
        
        return response()->json(['report_id' => $reportId]);
    }
    
    public function status(string $reportId): JsonResponse
    {
        $state = Redis::hGetAll("report:$reportId");
        if (empty($state)) return response()->json(['error' => 'not found'], 404);
        return response()->json($state);
    }
}

class GenerateCustomReportJob implements ShouldQueue
{
    public function __construct(public string $reportId, public array $params) {}
    
    public function handle(): void
    {
        Redis::hSet("report:{$this->reportId}", 'status', 'processing');
        
        $total = Order::count();
        $processed = 0;
        
        Order::lazy(1000)->each(function ($order) use (&$processed, $total) {
            // process...
            $processed++;
            
            if ($processed % 100 === 0) {
                $pct = round($processed / $total * 100, 1);
                Redis::hSet("report:{$this->reportId}", 'progress', $pct);
            }
        });
        
        // Upload, notify
        Redis::hSet("report:{$this->reportId}", [
            'status'      => 'completed',
            'download_url' => $signedUrl,
            'finished_at' => now()->toIso8601String(),
        ]);
    }
}
```

---

## 6. Pitfalls

```
❌ In-memory all rows — OOM
   ✓ lazy(), cursor(), chunkById()

❌ Report file local server-də qalır
   ✓ S3 upload, signed URL

❌ Email attachment 25 MB+ limit
   ✓ S3 link, cloud storage

❌ Job timeout → partial file
   ✓ Idempotent (check S3 first, resume), long timeout

❌ Multi-server cron duplicate execution
   ✓ onOneServer() + lock (Redis/DB)

❌ Invoice DB ilə file sync deyil
   ✓ Outbox pattern: DB insert + S3 upload single transaction
   ✓ Failure-də retry idempotent

❌ PII compliance (GDPR) — report user data saxlayır
   ✓ Encryption at rest, signed URL short TTL, audit log
   ✓ User "right to erasure" → report-ları da sil

❌ Scheduled time DST boundary — 2 dəfə və ya 0 dəfə execute
   ✓ UTC istifadə et scheduler-də, display-də TZ convert
```

---

## 7. Monitoring

```
Metrics:
  - Report generation duration (per type)
  - Queue backlog
  - Failed generation count
  - S3 upload failures
  - Email delivery rate

Alerts:
  - Weekly report generation > 2 saat duration → warning
  - Monthly invoice batch > 6 saat → critical
  - Failed email > 5% → investigate
  - S3 upload fail rate > 1% → investigate
```

---

## Problem niyə yaranır?

Əksər developer report-u bir HTTP request daxilində generate etməyə çalışır: controller çağırılır, SQL query işləyir, fayl yaranır, email göndərilir — hamısı 30 saniyə ərzində. Bu yanaşma kiçik datalar üçün işləyə bilər. Amma 5 milyon sətirlik `orders` cədvəlindən report çəkmək istədikdə PHP prosesi bütün sətirləri eyni anda RAM-a yükləməyə çalışır. `Order::all()` çağırışı 2-3 GB memory istifadə edir, PHP-nin `memory_limit` (adətən 256 MB) aşılır, proses `Fatal error: Allowed memory size exhausted` ilə məhv olur. İstifadəçi sadəcə timeout alır — nə error log-u, nə də xəbərdarlıq. CEO isə düşünür ki, sistem işlədi.

İkinci böyük problem — zaman. Nginx və PHP-FPM-in default timeout-u 60-120 saniyədir. 30 dəqiqəlik report prosesi üçün bu tamamilə yetərsizdir. Server timeout verir, HTTP connection kəsilir, PHP prosesi isə arxa planda davam edə bilər (əgər `set_time_limit(0)` yazılıbsa), amma nəticəni heç kim almayacaq. Daha pis variant: proses yarımçıq fayl yaradıb dayanır. Bu yarımçıq fayl S3-yə yüklənib email göndərilsə, alıcı pozulmuş CSV açar.

Ən gizli problem isə monitoring çatışmazlığıdır. Report job queue-ya düşür, 2 saat işləyir, bir SSL xətası ilə fail olur — heç kim bilmir. Laravel-in default failed jobs cədvəli dolur, amma heç kim `php artisan queue:failed` çalışdırmır. Növbəti həftə CEO emaili gəlmir, IT deyir "sistemi yoxlayacağıq", 3 gün sonra anlaşılır ki, report 10 gündür generate olunmur. Buna görə hər report üçün ayrı status tracking, retry mexanizmi və alerting vacibdir — yalnız queue-ya dispatch etmək yetərli deyil.

---

## Report Status Tracking

Real dünyada user admin paneldən "Generate Report" basır və çıxıb gedə bilər. Sistəm report-u arxa planda hazırlamalı, user istənilən vaxt status yoxlaya bilməlidir. Bunun üçün `report_requests` cədvəli və `ReportRequestService` lazımdır.

### Migration

```php
<?php
// database/migrations/2024_01_10_create_report_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();                         // Public ID (URL-də göstərilir)
            $table->foreignId('requester_id')->constrained('users');
            $table->string('type');                                 // weekly-revenue, warehouse-export, invoice-batch
            $table->json('filters')->nullable();                    // date_from, date_to, category_id ...
            $table->string('status')->default('queued');            // queued, processing, completed, failed, cancelled
            $table->unsignedInteger('progress')->default(0);        // 0-100 %
            $table->string('s3_path')->nullable();                  // Hazır fayılın yolu
            $table->string('format')->default('csv');               // csv, pdf, xlsx
            $table->unsignedBigInteger('row_count')->nullable();    // Nə qədər sətir generate olundu
            $table->unsignedInteger('file_size_kb')->nullable();    // Fayl ölçüsü KB
            $table->text('error_message')->nullable();              // Fail olduqda səbəb
            $table->unsignedTinyInteger('attempt')->default(0);     // Neçənci cəhd
            $table->timestamp('scheduled_for')->nullable();         // Zamanlanmış report üçün
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_requests');
    }
};
```

### Enum və Model

```php
<?php
// app/Enums/ReportStatus.php
namespace App\Enums;

enum ReportStatus: string
{
    case QUEUED     = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED  = 'completed';
    case FAILED     = 'failed';
    case CANCELLED  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED     => 'Növbədə gözləyir',
            self::PROCESSING => 'Hazırlanır',
            self::COMPLETED  => 'Hazırdır',
            self::FAILED     => 'Uğursuz',
            self::CANCELLED  => 'Ləğv edildi',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED]);
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }
}
```

```php
<?php
// app/Models/ReportRequest.php
namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportRequest extends Model
{
    protected $casts = [
        'filters'        => 'array',
        'status'         => ReportStatus::class,
        'scheduled_for'  => 'datetime',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    // Müvəqqəti download URL (7 gün) — yalnız completed report üçün
    public function getDownloadUrl(): ?string
    {
        if ($this->status !== ReportStatus::COMPLETED || !$this->s3_path) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl($this->s3_path, now()->addWeek());
    }

    // Progress yenilə (job daxilindən çağırılır)
    public function updateProgress(int $percent): void
    {
        $this->update(['progress' => min(100, max(0, $percent))]);
    }

    // Job başlayanda çağırılır
    public function markProcessing(): void
    {
        $this->update([
            'status'     => ReportStatus::PROCESSING,
            'started_at' => now(),
            'attempt'    => $this->attempt + 1,
        ]);
    }

    // Job uğurla bitdikdə
    public function markCompleted(string $s3Path, int $rowCount, int $fileSizeKb): void
    {
        $this->update([
            'status'       => ReportStatus::COMPLETED,
            'progress'     => 100,
            's3_path'      => $s3Path,
            'row_count'    => $rowCount,
            'file_size_kb' => $fileSizeKb,
            'completed_at' => now(),
        ]);
    }

    // Job fail olduqda
    public function markFailed(string $reason): void
    {
        $this->update([
            'status'        => ReportStatus::FAILED,
            'error_message' => $reason,
        ]);
    }
}
```

### ReportRequestService

```php
<?php
// app/Services/ReportRequestService.php
namespace App\Services;

use App\Enums\ReportStatus;
use App\Jobs\GenerateReportJob;
use App\Models\ReportRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReportRequestService
{
    // User report request edir
    public function request(
        User    $requester,
        string  $type,
        array   $filters = [],
        ?Carbon $scheduledFor = null,
        string  $format = 'csv'
    ): ReportRequest {
        // Eyni tipdə artıq gözləyən/işləyən report varsa, yenisini yaratma
        $existing = ReportRequest::where('requester_id', $requester->id)
            ->where('type', $type)
            ->whereIn('status', [ReportStatus::QUEUED->value, ReportStatus::PROCESSING->value])
            ->where('filters', json_encode($filters))
            ->first();

        if ($existing) {
            Log::info('Report request deduped — existing in-progress', [
                'uuid'        => $existing->uuid,
                'requester'   => $requester->id,
                'type'        => $type,
            ]);

            return $existing;
        }

        $report = ReportRequest::create([
            'requester_id'  => $requester->id,
            'type'          => $type,
            'filters'       => $filters,
            'format'        => $format,
            'status'        => ReportStatus::QUEUED,
            'scheduled_for' => $scheduledFor ?? now(),
        ]);

        Log::info('Report request created', [
            'uuid'        => $report->uuid,
            'type'        => $type,
            'requester'   => $requester->id,
            'scheduled'   => $report->scheduled_for,
        ]);

        GenerateReportJob::dispatch($report)
            ->delay($report->scheduled_for)
            ->onQueue('reports');

        return $report;
    }

    // User statusu yoxlayır
    public function getStatus(string $uuid, User $requester): ?ReportRequest
    {
        return ReportRequest::where('uuid', $uuid)
            ->where('requester_id', $requester->id)
            ->first();
    }

    // Fail olmuş report-u yenidən cəhd et
    public function retry(ReportRequest $report, User $requester): ReportRequest
    {
        if ($report->requester_id !== $requester->id) {
            abort(403, 'Bu report-a retry icazəniz yoxdur.');
        }

        if (!$report->status->canRetry()) {
            abort(422, "Status '{$report->status->label()}' olan report retry edilə bilməz.");
        }

        if ($report->attempt >= 5) {
            abort(422, 'Maksimum retry sayına (5) çatılıb. Support ilə əlaqə saxlayın.');
        }

        $report->update([
            'status'        => ReportStatus::QUEUED,
            'error_message' => null,
            'progress'      => 0,
        ]);

        Log::info('Report retry requested', [
            'uuid'      => $report->uuid,
            'attempt'   => $report->attempt,
            'requester' => $requester->id,
        ]);

        GenerateReportJob::dispatch($report)->onQueue('reports');

        return $report->fresh();
    }

    // Audit: user-ın bütün report tarixçəsi
    public function history(User $requester, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return ReportRequest::where('requester_id', $requester->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
```

### GenerateReportJob — Status tracking ilə

```php
<?php
// app/Jobs/GenerateReportJob.php
namespace App\Jobs;

use App\Models\ReportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;    // 2 saat
    public int $tries   = 1;       // Manual retry (ReportRequestService üzərindən)

    public function __construct(public ReportRequest $report) {}

    public function handle(): void
    {
        $this->report->markProcessing();

        try {
            $result = match ($this->report->type) {
                'weekly-revenue'   => $this->generateRevenueReport(),
                'warehouse-export' => $this->generateWarehouseExport(),
                default            => throw new \InvalidArgumentException("Unknown report type: {$this->report->type}"),
            };

            $this->report->markCompleted(
                s3Path:    $result['s3_path'],
                rowCount:  $result['row_count'],
                fileSizeKb: $result['file_size_kb'],
            );

            // Email — yalnız report hazır olduqdan sonra, ayrı addımda
            \App\Jobs\SendReportEmailJob::dispatch($this->report)->onQueue('emails');

            Log::info('Report generated successfully', [
                'uuid'     => $this->report->uuid,
                'type'     => $this->report->type,
                'rows'     => $result['row_count'],
                'size_kb'  => $result['file_size_kb'],
                'duration' => now()->diffInSeconds($this->report->started_at) . 's',
            ]);

        } catch (\Throwable $e) {
            $this->report->markFailed($e->getMessage());

            Log::error('Report generation failed', [
                'uuid'      => $this->report->uuid,
                'type'      => $this->report->type,
                'error'     => $e->getMessage(),
                'attempt'   => $this->report->attempt,
            ]);

            throw $e;   // Queue-a fail bildiririk ki, failed_jobs cədvəlinə düşsün
        }
    }

    private function generateRevenueReport(): array
    {
        $filters  = $this->report->filters ?? [];
        $from     = $filters['date_from'] ?? now()->subWeek()->toDateString();
        $to       = $filters['date_to']   ?? now()->toDateString();

        $tmpPath  = storage_path("app/tmp/revenue-{$this->report->uuid}.csv");
        $fh       = fopen($tmpPath, 'w');
        fputcsv($fh, ['date', 'orders', 'revenue', 'avg_order']);

        $total      = DB::table('orders')->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])->count();
        $rowCount   = 0;

        DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_order')
            ->groupBy('date')
            ->orderBy('date')
            ->lazy(500)
            ->each(function ($row) use ($fh, &$rowCount, $total) {
                fputcsv($fh, [$row->date, $row->orders, $row->revenue, round($row->avg_order, 2)]);
                $rowCount++;

                if ($rowCount % 50 === 0) {
                    $this->report->updateProgress((int) ($rowCount / $total * 90));
                }
            });

        fclose($fh);

        $s3Path      = "reports/{$this->report->type}/{$this->report->uuid}.csv";
        $fileSizeKb  = (int) (filesize($tmpPath) / 1024);

        Storage::disk('s3')->put($s3Path, fopen($tmpPath, 'r'), [
            'ServerSideEncryption' => 'AES256',
            'ContentType'          => 'text/csv',
        ]);

        unlink($tmpPath);

        return [
            's3_path'     => $s3Path,
            'row_count'   => $rowCount,
            'file_size_kb' => $fileSizeKb,
        ];
    }

    private function generateWarehouseExport(): array
    {
        $tmpPath = storage_path("app/tmp/warehouse-{$this->report->uuid}.csv");
        $fh      = fopen($tmpPath, 'w');
        fputcsv($fh, ['sku', 'name', 'stock', 'location', 'last_updated']);

        $total    = DB::table('warehouse_items')->count();
        $rowCount = 0;

        DB::table('warehouse_items')
            ->orderBy('sku')
            ->lazy(1000)
            ->each(function ($item) use ($fh, &$rowCount, $total) {
                fputcsv($fh, [$item->sku, $item->name, $item->stock, $item->location, $item->updated_at]);
                $rowCount++;

                if ($rowCount % 1000 === 0) {
                    $this->report->updateProgress((int) ($rowCount / $total * 90));
                }
            });

        fclose($fh);

        $s3Path     = "reports/warehouse/{$this->report->uuid}.csv";
        $fileSizeKb = (int) (filesize($tmpPath) / 1024);

        Storage::disk('s3')->put($s3Path, fopen($tmpPath, 'r'), ['ServerSideEncryption' => 'AES256']);
        unlink($tmpPath);

        return [
            's3_path'      => $s3Path,
            'row_count'    => $rowCount,
            'file_size_kb' => $fileSizeKb,
        ];
    }
}
```

### Controller — API endpoints

```php
<?php
// app/Http/Controllers/ReportController.php
namespace App\Http\Controllers;

use App\Services\ReportRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportRequestService $service) {}

    // Report sifariş et
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'           => 'required|string|in:weekly-revenue,warehouse-export,invoice-batch',
            'filters'        => 'nullable|array',
            'filters.date_from' => 'nullable|date',
            'filters.date_to'   => 'nullable|date|after_or_equal:filters.date_from',
            'format'         => 'nullable|in:csv,pdf,xlsx',
            'scheduled_for'  => 'nullable|date|after:now',
        ]);

        $report = $this->service->request(
            requester:    $request->user(),
            type:         $validated['type'],
            filters:      $validated['filters'] ?? [],
            scheduledFor: isset($validated['scheduled_for']) ? \Carbon\Carbon::parse($validated['scheduled_for']) : null,
            format:       $validated['format'] ?? 'csv',
        );

        return response()->json([
            'report_id'   => $report->uuid,
            'status'      => $report->status->value,
            'status_label' => $report->status->label(),
            'scheduled_for' => $report->scheduled_for,
        ], 202);
    }

    // Status yoxla
    public function show(string $uuid, Request $request): JsonResponse
    {
        $report = $this->service->getStatus($uuid, $request->user());

        if (!$report) {
            return response()->json(['error' => 'Report tapılmadı.'], 404);
        }

        return response()->json([
            'report_id'    => $report->uuid,
            'type'         => $report->type,
            'status'       => $report->status->value,
            'status_label' => $report->status->label(),
            'progress'     => $report->progress,
            'row_count'    => $report->row_count,
            'file_size_kb' => $report->file_size_kb,
            'download_url' => $report->getDownloadUrl(),
            'error'        => $report->error_message,
            'attempt'      => $report->attempt,
            'started_at'   => $report->started_at,
            'completed_at' => $report->completed_at,
            'created_at'   => $report->created_at,
        ]);
    }

    // Retry
    public function retry(string $uuid, Request $request): JsonResponse
    {
        $report = ReportRequest::where('uuid', $uuid)->firstOrFail();
        $report = $this->service->retry($report, $request->user());

        return response()->json([
            'report_id' => $report->uuid,
            'status'    => $report->status->value,
            'attempt'   => $report->attempt,
        ]);
    }

    // Tarixçə
    public function history(Request $request): JsonResponse
    {
        $reports = $this->service->history($request->user());

        return response()->json($reports->map(fn($r) => [
            'report_id'    => $r->uuid,
            'type'         => $r->type,
            'status'       => $r->status->value,
            'status_label' => $r->status->label(),
            'created_at'   => $r->created_at,
            'completed_at' => $r->completed_at,
            'download_url' => $r->getDownloadUrl(),
        ]));
    }
}
```

---

## Trade-offs

| Yanaşma | Üstünlük | Çatışmazlıq | Nə zaman |
|---------|----------|-------------|----------|
| **Sinxron (HTTP request)** | Sadədir, debug asandır, dərhal nəticə | Timeout riski, OOM, server bloklanır, retry yoxdur | Yalnız < 1000 sətir, < 5 saniyə |
| **Background job (queue)** | Memory-safe, timeout yoxdur, retry var, server bloklanmır | Mürəkkəb infrastruktur (worker, queue), status tracking lazımdır | İstənilən böyük report, ≥ 10k sətir |
| **Pre-computed reports (cron)** | Həmişə hazır, sıfır gözləmə vaxtı, DB yükü gecə | Stale data riski (son versiya deyil), boş hesablamalar (heç kim açmasa da generate olur) | CEO-nun sabit həftəlik email-i, real-time lazım deyil |
| **On-demand + cache** | Eyni report-u birdən çox user istəyirsə bir dəfə generate olunur, resursu qənaət | Cache invalidation mürəkkəbliyi, stale data riski | Bir neçə user eyni periodu görürsə (dashboard-da shared report) |

---

## Anti-patternlər

**1. `get()` ilə bütün datanı memory-ə yükləmək (OOM)**

```php
// YANLIŞ — 5M sətiri RAM-a yükləyir
$orders = Order::where('status', 'paid')->get();

// DÜZGÜN — lazy streaming, əvgölçüsü sabit qalır
Order::where('status', 'paid')->lazy(1000)->each(fn($o) => /* ... */);
```

`get()` bütün sətirləri PHP Collection-a yükləyir. 5 milyon sətir üçün bu 1-3 GB RAM deməkdir. `lazy()` isə PDO server-side cursor istifadə edir — yalnız hazırki batch RAM-dadır.

**2. Report job-unu timeout olmadan çalışdırmaq**

```php
// YANLIŞ — timeout set edilməyib, default 60 saniyə ilə öldürülür
class GenerateReportJob implements ShouldQueue {}

// DÜZGÜN
class GenerateReportJob implements ShouldQueue
{
    public int $timeout = 7200;   // 2 saat
    public int $tries   = 1;      // Timeout-dan sonra yenidən başlatma (data corrupt ola bilər)
}
```

Timeout olmayan job queue worker-in default timeout-u (adətən 60s) ilə kill edilir. Yarımçıq CSV S3-yə yüklənib email göndərilə bilər.

**3. Uğursuz report-u monitoring etməmək**

```php
// YANLIŞ — failed_jobs cədvəlinə düşür, heç kim baxa bilmir
class GenerateReportJob implements ShouldQueue {}

// DÜZGÜN — fail olduqda DB-də qeyd et, Slack/email alert göndər
public function failed(\Throwable $e): void
{
    $this->report->markFailed($e->getMessage());

    Notification::route('slack', config('alerts.reports_channel'))
        ->notify(new ReportFailedNotification($this->report, $e));
}
```

CEO-nun həftəlik report-u 3 həftədir gəlmirsə, ilk həftə "gecikir" düşünülür, ikinci həftə "bəlkə email spama düşdü", üçüncü həftə nəhayət IT-yə müraciət edir.

**4. Eyni report-u paralel generate etmək (race condition)**

```php
// YANLIŞ — user "Generate" iki dəfə basır, iki job eyni faylı yaradır
GenerateReportJob::dispatch($report);

// DÜZGÜN — dispatch etmədən əvvəl mövcud in-progress yoxla
$existing = ReportRequest::where('requester_id', $user->id)
    ->where('type', $type)
    ->whereIn('status', ['queued', 'processing'])
    ->where('filters', json_encode($filters))
    ->first();

if ($existing) return $existing;   // Mövcud request-i qaytarırıq
```

İki paralel job eyni `warehouse-2024-01-15.csv` faylına yazmağa çalışırsa, nəticə corrupted data olacaq.

**5. Report fayllarını server disk-də saxlamaq (multi-server problem)**

```php
// YANLIŞ — yalnız bir serverin disk-ində var
$path = storage_path('reports/revenue.csv');
// Digər server bu faylı görə bilmir

// DÜZGÜN — paylaşılan S3-yə yükləyin
Storage::disk('s3')->put("reports/{$filename}", fopen($tmpPath, 'r'));
// Bütün serverlər eyni S3 bucket-i görür
```

Load balancer arxasında 3 server var. Report 1-ci serverdə generate olundu, disk-ə yazıldı. User 2-ci serverə sorğu göndərdi — fayl yoxdur, 404. S3 (və ya digər shared storage) bu problemi tamamilə aradan qaldırır.

**6. Hər report-u tam yenidən generate etmək (incremental mümkün olduqda)**

```php
// YANLIŞ — hər gecə 50M sətiri tam yenidən hesablayır
Order::all()->groupBy('date')->each(fn($group) => /* aggregate */);

// DÜZGÜN — yalnız dünənki datanı hesabla, əvvəlki nəticəyə əlavə et
$yesterday = now()->subDay()->toDateString();
$stats = Order::whereDate('created_at', $yesterday)
    ->selectRaw('SUM(total) as revenue, COUNT(*) as orders')
    ->first();

DailyRevenueSnapshot::updateOrCreate(
    ['date' => $yesterday],
    ['revenue' => $stats->revenue, 'orders' => $stats->orders]
);
```

Anbar reportu üçün 5 illik tarixin hamısını yenidən hesablamaq əvəzinə, yalnız son 24 saatın dəyişikliklərini əlavə etmək kifayətdir. Bu, generation vaxtını saatlardan dəqiqələrə endirir.

**7. Report delivery-ni (email) report generation ilə eyni job-da etmək**

```php
// YANLIŞ — email fail olsa, bütün report yenidən generate olunur
class GenerateReportJob implements ShouldQueue
{
    public function handle(): void
    {
        // ... 2 saatlıq generation ...
        Mail::to($user)->send(new ReportMail($url));   // SMTP fail → job retry → generation yenidən başlayır!
    }
}

// DÜZGÜN — generation bitdikdən sonra ayrı email job-u dispatch et
class GenerateReportJob implements ShouldQueue
{
    public function handle(): void
    {
        // ... generation ...
        $this->report->markCompleted($s3Path, $rowCount, $fileSizeKb);
        SendReportEmailJob::dispatch($this->report)->onQueue('emails');
    }
}
```

Email göndərmək generation-dan ayrılmalıdır. SMTP serverinin 30 saniyəlik fasilası üçün 2 saatlıq generation-ı yenidən başlatmaq resurs israfıdır.

---

## Interview Sualları və Cavablar

**S: 10M row CSV-ni PHP-də necə generate edərdiniz?**

C: `Order::all()` çağırmaq OOM-a gətirir. Düzgün yanaşma: `lazy(1000)` (PDO server-side cursor) ilə sətirləri streaming edərək birbaşa `fopen()` handle-a `fputcsv()` yazırıq. Nə PHP Collection, nə də bütün data RAM-da olur. Eyni zamanda geçici fayl `/tmp`-da yaranır, bitdikdən sonra S3 Multipart Upload API ilə yüklənir (1 GB+ üçün `\Aws\S3\MultipartUploader`), local fayl silinir. Bütün proses `ShouldQueue` job daxilindədir, `timeout = 7200` ilə.

**S: Report generate zamanı timeout-u necə önləyərdiniz?**

C: Üç səviyyədə: (1) Job səviyyəsində `public int $timeout = 7200` — queue worker prosesi 2 saat sonra kill etmir. (2) PHP səviyyəsində job daxilindən `set_time_limit(0)` — script-in öz timeout-u yoxdur. (3) Database sorğuları üçün `DB::statement("SET statement_timeout = '2h'")` (PostgreSQL). HTTP request zamanı generate etmirik — onun Nginx/FPM timeout-u (60-120s) aşmaq mümkün deyil. Buna görə report həmişə queue job-da işləməlidir.

**S: Eyni report-u bir neçə user request edibsə, necə idarə edərdiniz?**

C: `report_requests` cədvəlindən `status IN ('queued', 'processing')` olan eyni `type + filters` kombinasiyasını yoxlayırıq. Varsa, mövcud request-i qaytarırıq — yeni job dispatch etmirik. Bu həm resurs qənaəti, həm də race condition-dan müdafiədir. Başqa yanaşma: report generate edildikdən sonra nəticəni `type + filters + date` kombinasiyasına görə cache-ləmək — növbəti eyni request üçün S3-dən hazır fayl qaytarılır.

**S: Scheduled report-un failure-ını necə monitor edərdiniz?**

C: Çoxkatmanlı yanaşma: (1) `ReportRequest` cədvəlindəki `status = 'failed'` sətirləri üçün Laravel scheduled job `0 9 * * *` — hər gün saat 9-da yoxla, report gözlənilən vaxtdan 2 saat sonra hələ `completed` deyilsə Slack alert. (2) Job-un `failed()` metodu — hər fail anında dərhal Slack/email. (3) DataDog/Grafana-da `report_generation_duration_seconds` histogram — 95th percentile 1 saatı keçirsə alert. (4) Dead letter queue monitoring — `php artisan queue:failed` çıxışını Prometheus ilə expose etmək.

**S: `cursor()` vs `chunk()` vs `chunkById()` — fərqləri nədir, report üçün hansını seçərdiniz?**

C: `cursor()` — PDO server-side cursor, bütün nəticəni DB-dən alır amma PHP tərəfdə bir-bir işləyir. Yaddaş effektiv, amma uzun müddətli DB connection tut, çox böyük dataset üçün DB buffer problemi. `chunk($n, callback)` — `LIMIT/OFFSET` ilə bölür. Sadədir, amma böyük `OFFSET` dəyərləri ilə yavaşlayır (DB hər dəfə əvvəlki sətirləri keçir), sort dəyişsə sətirləri atlaya bilər. `chunkById($n, callback)` — `WHERE id > last_id` ilə növbəti chunk-ı alır. `OFFSET` problemi yoxdur, index istifadə edir, performans sabitdir. Report üçün **`chunkById()`** tövsiyə olunur — böyük dataset-lərdə `OFFSET`-dən 10-50x sürətlidir. Əgər Eloquent builder istifadə etmək istəmirsə, `DB::table(...)->lazy(1000)` (Laravel 9+, `cursor()`-ın daha güclü versiyası) da əla seçimdir.
