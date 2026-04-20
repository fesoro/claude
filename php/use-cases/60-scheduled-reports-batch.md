# Use Case: Scheduled Reports & Batch Export

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
