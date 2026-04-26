# Large Dataset Export (Middle)

## Ssenari

100,000+ sətirlik order məlumatlarını CSV/Excel formatında export etmək. Memory-efficient, background job ilə, progress tracking ilə.

---

## Problem

```
Adi yanaşma (DANGEROUS!):
  $orders = Order::all();  // 100K row RAM-a yüklənir!
  // PHP 8MB limit → Fatal Error: Allowed memory exhausted
  // Yoxsa 512MB alır → server yavaşlayır

Həllər:
  1. Chunked reading → Generator
  2. Background Job → User-ə link göndər  
  3. Streaming Response (küçük exportlar üçün)
  4. S3-ə yüklə → Pre-signed URL
```

---

## Arxitektura

```
User: "Export CSV" düyməsinə basır
     │
     ▼
┌──────────────┐
│   API        │ → Job dispatch eder, job_id qaytarır
└──────┬───────┘
       │
       ▼
┌──────────────┐
│  Export Job  │ → DB-ni chunk-la oxuyur, S3-ə yazar
└──────┬───────┘    progress: Redis-ə yazır
       │
       ▼
┌──────────────┐
│     S3       │ ← CSV/XLSX faylı
└──────────────┘
       │
User polling: GET /exports/{job_id}/status
       │
       ▼
"Ready! Download link: https://s3.../file.csv?expires=3600"
```

---

## Streaming CSV (kiçik-orta dataset)

*Bu kod chunk-larla oxuyaraq birbaşa çıxış axınına CSV yayan memory-efficient streaming export-u göstərir:*

```php
class OrderExportController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        $filters = $request->validated();
        
        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            
            // UTF-8 BOM (Excel-in düzgün oxuması üçün)
            fwrite($handle, "\xEF\xBB\xBF");
            
            // Header row
            fputcsv($handle, [
                'ID', 'Order Number', 'Customer', 'Email',
                'Total', 'Status', 'Created At'
            ]);
            
            // Chunk ilə oxu — memory O(chunk_size)
            Order::query()
                ->with('customer:id,name,email')
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->orderBy('id')
                ->chunk(500, function ($orders) use ($handle) {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->id,
                            $order->order_number,
                            $order->customer->name,
                            $order->customer->email,
                            number_format($order->total / 100, 2),
                            $order->status,
                            $order->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                    
                    // Chunk-lar arası flush
                    ob_flush();
                    flush();
                });
            
            fclose($handle);
        }, 'orders-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
```

---

## Background Export (böyük dataset)

*Bu kod generator ilə chunk-larla oxuyub S3-ə yükləyən, progress izləyən və istifadəçiyə link göndərən background export job-unu göstərir:*

```php
// Export Job
class ExportOrdersJob implements ShouldQueue
{
    public int $tries   = 3;
    public int $timeout = 3600;  // 1 saat
    
    public function __construct(
        private readonly string $exportId,
        private readonly int $userId,
        private readonly array $filters,
    ) {}
    
    public function handle(ExportProgressTracker $tracker): void
    {
        $tracker->start($this->exportId);
        
        try {
            $filePath = $this->generateExport($tracker);
            
            // S3-ə yüklə
            $s3Key = "exports/{$this->exportId}/orders.csv";
            Storage::disk('s3')->put($s3Key, fopen($filePath, 'r'));
            
            // Geçici fayl sil
            unlink($filePath);
            
            // Pre-signed URL (1 saat etibarlı)
            $downloadUrl = Storage::disk('s3')
                ->temporaryUrl($s3Key, now()->addHour());
            
            $tracker->complete($this->exportId, $downloadUrl);
            
            // User-ə email/notification göndər
            User::find($this->userId)->notify(
                new ExportReadyNotification($downloadUrl, $this->exportId)
            );
            
        } catch (\Exception $e) {
            $tracker->fail($this->exportId, $e->getMessage());
            throw $e;
        }
    }
    
    private function generateExport(ExportProgressTracker $tracker): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'export_');
        $handle  = fopen($tmpFile, 'w');
        
        // UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['ID', 'Order Number', 'Customer', 'Total', 'Status', 'Date']);
        
        $total     = $this->countOrders();
        $processed = 0;
        
        foreach ($this->readOrdersInChunks() as $order) {
            fputcsv($handle, $this->toRow($order));
            $processed++;
            
            // Hər 1000 row-da progress yenilə
            if ($processed % 1000 === 0) {
                $tracker->updateProgress(
                    $this->exportId,
                    $processed,
                    $total
                );
            }
        }
        
        fclose($handle);
        return $tmpFile;
    }
    
    // Generator — memory O(1)
    private function readOrdersInChunks(): \Generator
    {
        $lastId = 0;
        $chunkSize = 500;
        
        while (true) {
            $orders = Order::query()
                ->with('customer:id,name,email')
                ->where('id', '>', $lastId)
                ->when($this->filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
                ->when($this->filters['from'] ?? null, fn($q, $d) => $q->where('created_at', '>=', $d))
                ->when($this->filters['to'] ?? null, fn($q, $d) => $q->where('created_at', '<=', $d))
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();
            
            if ($orders->isEmpty()) break;
            
            foreach ($orders as $order) {
                yield $order;
            }
            
            $lastId = $orders->last()->id;
            
            // Memory temizle
            unset($orders);
        }
    }
    
    private function toRow(Order $order): array
    {
        return [
            $order->id,
            $order->order_number,
            $order->customer->name ?? 'N/A',
            number_format($order->total / 100, 2),
            $order->status,
            $order->created_at->format('Y-m-d H:i:s'),
        ];
    }
    
    private function countOrders(): int
    {
        return Order::query()
            ->when($this->filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->count();
    }
}

// Progress Tracker (Redis-based)
class ExportProgressTracker
{
    public function start(string $exportId): void
    {
        Cache::put("export:$exportId", [
            'status'   => 'processing',
            'progress' => 0,
            'total'    => 0,
        ], 3600);
    }
    
    public function updateProgress(string $exportId, int $processed, int $total): void
    {
        Cache::put("export:$exportId", [
            'status'   => 'processing',
            'progress' => $processed,
            'total'    => $total,
            'percent'  => $total > 0 ? round($processed / $total * 100) : 0,
        ], 3600);
    }
    
    public function complete(string $exportId, string $downloadUrl): void
    {
        Cache::put("export:$exportId", [
            'status'       => 'completed',
            'progress'     => 100,
            'download_url' => $downloadUrl,
            'expires_at'   => now()->addHour()->toISOString(),
        ], 3600);
    }
    
    public function fail(string $exportId, string $error): void
    {
        Cache::put("export:$exportId", [
            'status' => 'failed',
            'error'  => $error,
        ], 3600);
    }
    
    public function getStatus(string $exportId): ?array
    {
        return Cache::get("export:$exportId");
    }
}

// Controller
class ExportController extends Controller
{
    public function create(ExportOrdersRequest $request): JsonResponse
    {
        $exportId = Str::uuid()->toString();
        
        ExportOrdersJob::dispatch(
            $exportId,
            $request->user()->id,
            $request->validated()
        )->onQueue('exports');
        
        return response()->json([
            'export_id' => $exportId,
            'status_url' => route('exports.status', $exportId),
        ], 202);
    }
    
    public function status(string $exportId, ExportProgressTracker $tracker): JsonResponse
    {
        $status = $tracker->getStatus($exportId);
        
        if (!$status) {
            return response()->json(['error' => 'Export tapılmadı'], 404);
        }
        
        return response()->json($status);
    }
}
```

---

## Excel Export (Laravel Excel)

*Bu kod Laravel Excel paketi ilə chunk-larla oxuyub queue-da Excel faylı yaradan export sinfini göstərir:*

```php
// maatwebsite/laravel-excel

class OrdersExport implements FromQuery, WithHeadings, WithChunkReading, ShouldQueue
{
    public function __construct(
        private readonly array $filters
    ) {}
    
    public function query(): Builder
    {
        return Order::query()
            ->with('customer')
            ->when($this->filters['status'] ?? null, fn($q, $s) => $q->where('status', $s));
    }
    
    public function headings(): array
    {
        return ['ID', 'Order Number', 'Customer', 'Total', 'Status', 'Date'];
    }
    
    public function chunkSize(): int
    {
        return 500;  // Memory-efficient
    }
    
    public function map($order): array
    {
        return [
            $order->id,
            $order->order_number,
            $order->customer->name,
            $order->total / 100,
            $order->status,
            $order->created_at->format('Y-m-d'),
        ];
    }
}

// İstifadə
Excel::queue(new OrdersExport($filters), 'exports/orders.xlsx', 's3')
    ->chain([
        new NotifyUserOfCompletedExport(auth()->id()),
    ]);
```

---

## İntervyu Sualları

**S: 1M sətirlik CSV export-da memory problemi necə həll edilir?**
C: Generator + cursor-based chunk reading. `yield` ilə hər sətri lazım olduqda al — bütün collection RAM-a yüklənmir. `fputcsv` direkt output stream-ə yazar (RAM-da saxlamaz). `unset($orders)` hər chunk-dan sonra GC-nin işini asanlaşdırır. Cursor-based pagination (`WHERE id > lastId`): OFFSET-dən fərqli olaraq sabit performans verir.

**S: Problem niyə yaranır — `Order::all()` niyə pisdir?**
C: Eloquent bütün result set-i PHP-nin heap yaddaşına Collection kimi yükləyir. 100K order orta hesabla 50-200MB RAM tutua bilər. PHP memory_limit (128MB/256MB) aşıldığında `Fatal error: Allowed memory size exhausted` baş verir. Hətta limit yüksək tutulsa belə, bu qədər obyektin serialize/deserialize edilməsi CPU bottleneck yaradır.

**S: Background export-da user experience necə qurulur?**
C: `202 Accepted` → `export_id` + `status_url` qaytarılır (job sync gözlənilmir). Client hər 2-3 saniyədə `status_url`-i poll edir. Progress percent göstərilir (1000 sətirdən birə Redis update). Hazır olduqda pre-signed download URL qaytarılır (1 saatlik expiry). Email/push notification da göndərilə bilər — user browser-i bağlasa belə məlumat alacaq.

**S: Pre-signed URL nədir, niyə lazımdır?**
C: S3-dəki private fayla müvəqqəti, imzalı URL. Fayl `public` deyil — birbaşa URL-i olan hər kəs endirə bilər, amma URL expires olunur (1 saat). Fayl private bucket-da qalır, payload-da user auth lazım deyil. S3-in özü URL-dəki imzanı yoxlayır. Alternativ: serverdən stream et, amma bu server yükünü artırır.

**S: Cursor-based pagination OFFSET-dən niyə yaxşıdır?**
C: `OFFSET 500000` → DB mütləq ilk 500K sətri oxuyub atır (wasted I/O). Cədvəl böyüdükcə performance eksponensial yavaşlayır. `WHERE id > lastId ORDER BY id LIMIT 1000` → B-tree index üzərindən birbaşa keçid, daim sabit O(log n) performans. Şərt: sıralama sütununda index olmalıdır, boşluqlar varsa (deleted rows) problem olmur çünki hər chunk tamamlanmış son ID-ni tutur.

**S: Paralel export necə qurulur (1M sətiri sürətləndirmək üçün)?**
C: ID range-lərini böl: 0-250K, 250K-500K, 500K-750K, 750K-1M — 4 parallel job. Hər job ayrı temp file yaradır. Bütün job-lar bitdikdən sonra fan-in job temp file-ları birləşdirir, S3-ə yükləyir. Bus::batch() ilə idarə et, `then()` callback-ında merge et. Diqqət: parallel export zamanı header sətri yalnız ilk chunk-da olmalıdır.

**S: Export faylı çox böyükdürsə (5GB+) nə etmək lazımdır?**
C: Multi-part S3 upload istifadə et — Laravel Storage driver bunu avtomatik idarə edir. Alternativ: CSV-ni ZIP ilə compress et (10x kiçilə bilər). Parçalı export: hər 100K sətir ayrı fayl, ZIP arxivi — user bir neçə fayl endirir. Mövcud olanı da göstər: "Birinci 500K hazırdır, endirin, qalan hazırlanır".

**S: Export job-u timeout-la bitdikdə nə baş verir, necə handle etmək olar?**
C: `$timeout = 3600` queue job-una set et. Çox böyük export üçün checkpoint saxla: hər 10K sətirdə `lastProcessedId`-ni Redis-ə yaz. Job restart olduqda (timeout, worker restart) checkpoint-dən davam et. Idempotent olsun: artıq yazılmış temp file varsa əvvəldən başlama.

---

## Anti-patternlər

**1. Bütün data-nı yaddaşa yükləyib sonra CSV yazmaq**
`$records = DB::table('orders')->get()` ilə milyonlarla sətiri Collection-a yükləmək — PHP memory limitini aşır, `out of memory` xətası verir. Generator + `cursor()` ilə sətir-sətir oxu, `fputcsv` ilə birbaşa stream-ə yaz.

**2. Export-u HTTP request-inin içində sinxron icra etmək**
1M sətirlik export-u birbaşa controller-dan çalışdırmaq — request onlarla saniyə (bəzən dəqiqələrlə) bloklanır, nginx/PHP-FPM timeout verir, user boş ekrana baxır. Background job ilə `202 Accepted` qaytar, status URL-i poll et.

**3. OFFSET-based pagination ilə böyük dataset-i oxumaq**
`LIMIT 1000 OFFSET 500000` kimi sorğularla export etmək — DB hər dəfə əvvəldən 500K sətri oxuyub atır, get-gedə yavaşlayır, eksponensial performans itkisi baş verir. `WHERE id > lastId ORDER BY id` cursor-based pagination istifadə et.

**4. Export faylını public URL-də saxlamaq**
Hazır CSV-ni `public/exports/filename.csv` kimi birbaşa web-accessible yerdə saxlamaq — hər kəs URL-i bilsə başqasının data-sını endirə bilər. Faylı private S3 bucket-da saxla, müvəqqəti pre-signed URL ilə göndər.

**5. Export progress-ini hər sətir üçün DB-yə yazmaq**
Hər oxunan sətirdə `UPDATE export_jobs SET processed = ? WHERE id = ?` etmək — DB-yə həddindən artıq yük, export özündən daha çox DB vaxtı aparır. Progress-i Redis-də cache-lə, hər N sətirdən bir DB-ni yenilə.

**6. Böyük export faylını serverdə saxlamaq**
Hazır export faylını serverin local disk-inde saxlamaq — disk dolur, multi-server mühitdə faylın hansı serverdə olduğu bilinmir, server restart-da fayl itir. S3 kimi distributed object storage istifadə et.
