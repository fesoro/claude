# Böyük Fayl Upload və Processing

## Problem Təsviri

Application-da böyük faylların upload və processing edilməsi ciddi texniki çağırışlar yaradır:

- **Böyük CSV/Excel faylları** — 100MB+ fayl, milyon sətir, import etmək lazımdır
- **Image upload** — resize, crop, thumbnail yaratmaq, optimization
- **Video upload** — transcode, thumbnail extraction
- **Network kəsilmələri** — upload yarıda qalır, yenidən başlamaq lazım olur
- **Memory overflow** — PHP memory limit (128MB default) ilə 500MB fayl işləmək mümkün deyil
- **Timeout** — HTTP timeout (30-60 saniyə) böyük fayllar üçün yetərli deyil
- **User gözləyir** — processing bitənə qədər loading spinner

```
User → 500MB CSV Upload → PHP Memory: 128MB → FATAL ERROR!
User → 2GB Video Upload → HTTP Timeout: 60s → Connection Reset!
```

---

## Həll 1: Chunked Upload

### Konsept

Böyük faylı kiçik hissələrə (chunk) bölüb, ayrı-ayrı upload edirik. Server hissələri birləşdirir.

```
Fayl: 100MB
Chunk 1: 0-5MB    → Upload → Server
Chunk 2: 5-10MB   → Upload → Server
...
Chunk 20: 95-100MB → Upload → Server → Birləşdir → Tam fayl
```

### Backend Implementation

*Bu kod fayl upload sessiyası başladan, chunk-ları qəbul edən və birləşdirən controller-i göstərir:*

```php
// app/Http/Controllers/ChunkedUploadController.php
namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFileJob;
use App\Models\FileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedUploadController extends Controller
{
    /**
     * Upload sessiyası başlatmaq — fayl haqqında metadata alır.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'filesize' => 'required|integer|min:1|max:5368709120', // max 5GB
            'mime_type' => 'required|string',
            'total_chunks' => 'required|integer|min:1|max:10000',
            'chunk_size' => 'required|integer|min:1048576', // min 1MB
        ]);

        $upload = FileUpload::create([
            'upload_id' => Str::uuid()->toString(),
            'user_id' => $request->user()->id,
            'original_filename' => $validated['filename'],
            'filesize' => $validated['filesize'],
            'mime_type' => $validated['mime_type'],
            'total_chunks' => $validated['total_chunks'],
            'chunk_size' => $validated['chunk_size'],
            'uploaded_chunks' => 0,
            'status' => 'initiated',
            'storage_path' => 'uploads/chunks/' . Str::uuid()->toString(),
        ]);

        // Chunk-lar üçün temp directory yarat
        Storage::makeDirectory($upload->storage_path);

        return response()->json([
            'upload_id' => $upload->upload_id,
            'chunk_size' => $validated['chunk_size'],
            'total_chunks' => $validated['total_chunks'],
        ], 201);
    }

    /**
     * Tək bir chunk upload etmək.
     */
    public function uploadChunk(Request $request, string $uploadId): JsonResponse
    {
        $upload = FileUpload::where('upload_id', $uploadId)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['initiated', 'uploading'])
            ->firstOrFail();

        $validated = $request->validate([
            'chunk_index' => 'required|integer|min:0|max:' . ($upload->total_chunks - 1),
            'chunk' => 'required|file|max:' . ceil($upload->chunk_size / 1024), // KB
        ]);

        $chunkIndex = $validated['chunk_index'];
        $chunkFile = $request->file('chunk');

        // Chunk-u saxla
        $chunkPath = $upload->storage_path . '/chunk_' . str_pad($chunkIndex, 5, '0', STR_PAD_LEFT);
        Storage::putFileAs(
            dirname($chunkPath),
            $chunkFile,
            basename($chunkPath)
        );

        // Upload progress yenilə
        $upload->increment('uploaded_chunks');
        $upload->update(['status' => 'uploading']);

        $progress = ($upload->uploaded_chunks / $upload->total_chunks) * 100;

        // Bütün chunk-lar yüklənibsə, birləşdir
        if ($upload->uploaded_chunks >= $upload->total_chunks) {
            return $this->assembleChunks($upload);
        }

        return response()->json([
            'chunk_index' => $chunkIndex,
            'uploaded_chunks' => $upload->uploaded_chunks,
            'total_chunks' => $upload->total_chunks,
            'progress' => round($progress, 2),
        ]);
    }

    /**
     * Chunk-ları birləşdirərək tam faylı yaratmaq.
     */
    private function assembleChunks(FileUpload $upload): JsonResponse
    {
        $upload->update(['status' => 'assembling']);

        $finalPath = 'uploads/complete/' . $upload->upload_id . '/' . $upload->original_filename;

        // Chunk-ları sıra ilə birləşdir
        $outputStream = Storage::writeStream($finalPath, '');
        $outputHandle = fopen(Storage::path($finalPath), 'wb');

        for ($i = 0; $i < $upload->total_chunks; $i++) {
            $chunkPath = $upload->storage_path . '/chunk_' . str_pad($i, 5, '0', STR_PAD_LEFT);
            $chunkFullPath = Storage::path($chunkPath);

            if (!file_exists($chunkFullPath)) {
                $upload->update(['status' => 'failed', 'error_message' => "Chunk {$i} tapılmadı"]);
                fclose($outputHandle);
                return response()->json(['error' => "Chunk {$i} əksikdir"], 400);
            }

            $chunkHandle = fopen($chunkFullPath, 'rb');
            stream_copy_to_stream($chunkHandle, $outputHandle);
            fclose($chunkHandle);
        }

        fclose($outputHandle);

        // Temp chunk-ları sil
        Storage::deleteDirectory($upload->storage_path);

        // Upload tamamlandı
        $upload->update([
            'status' => 'uploaded',
            'storage_path' => $finalPath,
        ]);

        // Processing job dispatch et
        ProcessUploadedFileJob::dispatch($upload);

        return response()->json([
            'upload_id' => $upload->upload_id,
            'status' => 'uploaded',
            'message' => 'Fayl uğurla yükləndi. Processing başladı.',
            'progress' => 100,
        ]);
    }

    /**
     * Upload statusunu yoxlamaq.
     */
    public function status(Request $request, string $uploadId): JsonResponse
    {
        $upload = FileUpload::where('upload_id', $uploadId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'upload_id' => $upload->upload_id,
            'status' => $upload->status,
            'uploaded_chunks' => $upload->uploaded_chunks,
            'total_chunks' => $upload->total_chunks,
            'progress' => $upload->total_chunks > 0
                ? round(($upload->uploaded_chunks / $upload->total_chunks) * 100, 2)
                : 0,
            'processing_progress' => $upload->processing_progress,
            'result_url' => $upload->result_url,
            'error_message' => $upload->error_message,
        ]);
    }
}
```

### FileUpload Model

*Bu kod fayl upload prosesini izləyən FileUpload modelini göstərir:*

```php
// app/Models/FileUpload.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileUpload extends Model
{
    protected $fillable = [
        'upload_id',
        'user_id',
        'original_filename',
        'filesize',
        'mime_type',
        'total_chunks',
        'chunk_size',
        'uploaded_chunks',
        'status',
        'storage_path',
        'result_url',
        'processing_progress',
        'error_message',
    ];

    protected $casts = [
        'filesize' => 'integer',
        'total_chunks' => 'integer',
        'chunk_size' => 'integer',
        'uploaded_chunks' => 'integer',
        'processing_progress' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->uploaded_chunks >= $this->total_chunks;
    }
}
```

### Migration

*Bu kod fayl upload metadata-sını saxlayan cədvəli yaradır:*

```php
// database/migrations/2024_01_04_create_file_uploads_table.php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('upload_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('original_filename');
            $table->bigInteger('filesize');
            $table->string('mime_type');
            $table->integer('total_chunks');
            $table->integer('chunk_size');
            $table->integer('uploaded_chunks')->default(0);
            $table->string('status')->default('initiated');
            $table->string('storage_path')->nullable();
            $table->string('result_url')->nullable();
            $table->integer('processing_progress')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }
};
```

### Frontend Chunked Upload

*Bu kod faylı hissələrə bölüb paralel upload edən JavaScript sinifini göstərir:*

```javascript
class ChunkedUploader {
    constructor(file, options = {}) {
        this.file = file;
        this.chunkSize = options.chunkSize || 5 * 1024 * 1024; // 5MB
        this.totalChunks = Math.ceil(file.size / this.chunkSize);
        this.uploadId = null;
        this.uploadedChunks = new Set();
        this.onProgress = options.onProgress || (() => {});
        this.onComplete = options.onComplete || (() => {});
        this.onError = options.onError || (() => {});
        this.maxConcurrent = options.maxConcurrent || 3;
    }

    async start() {
        try {
            // 1. Upload sessiyası başlat
            const initResponse = await fetch('/api/uploads/initiate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
                body: JSON.stringify({
                    filename: this.file.name,
                    filesize: this.file.size,
                    mime_type: this.file.type,
                    total_chunks: this.totalChunks,
                    chunk_size: this.chunkSize,
                }),
            });

            const { upload_id } = await initResponse.json();
            this.uploadId = upload_id;

            // 2. Chunk-ları paralel upload et
            await this.uploadAllChunks();

            this.onComplete(upload_id);
        } catch (err) {
            this.onError(err);
        }
    }

    async uploadAllChunks() {
        const chunks = Array.from({ length: this.totalChunks }, (_, i) => i);
        const queue = [...chunks];
        const active = new Set();

        return new Promise((resolve, reject) => {
            const processNext = async () => {
                if (queue.length === 0 && active.size === 0) {
                    resolve();
                    return;
                }

                while (queue.length > 0 && active.size < this.maxConcurrent) {
                    const chunkIndex = queue.shift();
                    active.add(chunkIndex);

                    this.uploadChunk(chunkIndex)
                        .then(() => {
                            active.delete(chunkIndex);
                            this.uploadedChunks.add(chunkIndex);
                            this.onProgress({
                                uploaded: this.uploadedChunks.size,
                                total: this.totalChunks,
                                percent: Math.round((this.uploadedChunks.size / this.totalChunks) * 100),
                            });
                            processNext();
                        })
                        .catch((err) => {
                            active.delete(chunkIndex);
                            // Retry — chunk-u queue-ya geri qoy
                            queue.push(chunkIndex);
                            processNext();
                        });
                }
            };

            processNext();
        });
    }

    async uploadChunk(chunkIndex) {
        const start = chunkIndex * this.chunkSize;
        const end = Math.min(start + this.chunkSize, this.file.size);
        const chunk = this.file.slice(start, end);

        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('chunk_index', chunkIndex);

        const response = await fetch(`/api/uploads/${this.uploadId}/chunk`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
            },
            body: formData,
        });

        if (!response.ok) {
            throw new Error(`Chunk ${chunkIndex} upload failed`);
        }

        return response.json();
    }
}

// İstifadə
const uploader = new ChunkedUploader(file, {
    chunkSize: 5 * 1024 * 1024, // 5MB
    maxConcurrent: 3,
    onProgress: ({ percent }) => {
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `${percent}%`;
    },
    onComplete: (uploadId) => {
        showSuccess('Fayl yükləndi, processing başladı!');
        pollStatus(uploadId);
    },
    onError: (err) => {
        showError('Upload xətası: ' + err.message);
    },
});

uploader.start();
```

---

## Həll 2: S3 Direct Upload (Pre-signed URL)

### Konsept

Fayl server üzərindən keçmədən birbaşa S3-ə yüklənir. Server yalnız pre-signed URL yaradır.

```
1. Frontend → Server: "Upload etmək istəyirəm"
2. Server → Frontend: Pre-signed URL qaytarır
3. Frontend → S3: Birbaşa upload (server yükü yoxdur!)
4. Frontend → Server: "Upload tamamdır"
5. Server → S3-dən oxu → Processing başlat
```

*Bu kod server yükünü azaldaraq birbaşa S3-ə upload üçün pre-signed URL yaradan controller-i göstərir:*

```php
// app/Http/Controllers/S3DirectUploadController.php
namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFileJob;
use App\Models\FileUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3DirectUploadController extends Controller
{
    /**
     * S3 pre-signed upload URL yaradır.
     */
    public function createPresignedUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'mime_type' => 'required|string|in:text/csv,image/jpeg,image/png,application/pdf',
            'filesize' => 'required|integer|min:1|max:1073741824', // max 1GB
        ]);

        $key = 'uploads/' . $request->user()->id . '/' . Str::uuid() . '/' . $validated['filename'];

        $upload = FileUpload::create([
            'upload_id' => Str::uuid()->toString(),
            'user_id' => $request->user()->id,
            'original_filename' => $validated['filename'],
            'filesize' => $validated['filesize'],
            'mime_type' => $validated['mime_type'],
            'storage_path' => $key,
            'status' => 'awaiting_upload',
            'total_chunks' => 1,
            'chunk_size' => $validated['filesize'],
        ]);

        // Pre-signed URL yarat (15 dəqiqə keçərli)
        $s3Client = Storage::disk('s3')->getClient();
        $command = $s3Client->getCommand('PutObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => $key,
            'ContentType' => $validated['mime_type'],
            'ACL' => 'private',
        ]);

        $presignedUrl = (string) $s3Client->createPresignedRequest($command, '+15 minutes')->getUri();

        return response()->json([
            'upload_id' => $upload->upload_id,
            'presigned_url' => $presignedUrl,
            'method' => 'PUT',
            'headers' => [
                'Content-Type' => $validated['mime_type'],
            ],
            'expires_in' => 900, // 15 dəqiqə
        ]);
    }

    /**
     * Upload tamamlandıqda frontend bunu çağırır.
     */
    public function confirmUpload(Request $request, string $uploadId): JsonResponse
    {
        $upload = FileUpload::where('upload_id', $uploadId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'awaiting_upload')
            ->firstOrFail();

        // S3-də faylın mövcudluğunu yoxla
        if (!Storage::disk('s3')->exists($upload->storage_path)) {
            return response()->json(['error' => 'Fayl S3-də tapılmadı'], 400);
        }

        $upload->update([
            'status' => 'uploaded',
            'uploaded_chunks' => 1,
        ]);

        // Processing başlat
        ProcessUploadedFileJob::dispatch($upload);

        return response()->json([
            'upload_id' => $upload->upload_id,
            'status' => 'uploaded',
            'message' => 'Processing başladı',
        ]);
    }
}
```

### Frontend — S3 Direct Upload

*Bu kod pre-signed URL alıb faylı birbaşa S3-ə yükləyən, sonra serveri xəbərdar edən JavaScript funksiyasını göstərir:*

```javascript
async function directS3Upload(file) {
    // 1. Pre-signed URL al
    const response = await fetch('/api/uploads/presigned-url', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
            filename: file.name,
            mime_type: file.type,
            filesize: file.size,
        }),
    });

    const { upload_id, presigned_url, headers } = await response.json();

    // 2. Birbaşa S3-ə upload et
    const uploadResponse = await fetch(presigned_url, {
        method: 'PUT',
        headers: headers,
        body: file,
    });

    if (!uploadResponse.ok) {
        throw new Error('S3 upload failed');
    }

    // 3. Server-ə təsdiq göndər
    await fetch(`/api/uploads/${upload_id}/confirm`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
        },
    });

    return upload_id;
}
```

---

## Həll 3: CSV Import — Queue-based Processing

### Konsept

Böyük CSV faylını bir dəfəyə memory-ə yükləmək əvəzinə, `LazyCollection` ilə chunk-larla oxuyub, hər chunk-u ayrı job olaraq queue-ya göndəririk.

```
CSV (1M sətir) → Job 1 (sətir 1-1000) → DB Insert
                → Job 2 (sətir 1001-2000) → DB Insert
                → ...
                → Job 1000 (sətir 999001-1000000) → DB Insert
```

### Import Job — Master

*Bu kod böyük CSV-ni LazyCollection ilə oxuyub hər 1000 sətiri ayrı job-a göndərən master import job-u göstərir:*

```php
// app/Jobs/ProcessCsvImportJob.php
namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 dəqiqə

    public function __construct(
        public FileUpload $fileUpload
    ) {}

    public function handle(): void
    {
        $this->fileUpload->update(['status' => 'processing']);

        $filePath = Storage::path($this->fileUpload->storage_path);

        // LazyCollection — memory-efficient oxuma
        $rows = LazyCollection::make(function () use ($filePath) {
            $handle = fopen($filePath, 'r');

            // İlk sətir header
            $headers = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                yield array_combine($headers, $row);
            }

            fclose($handle);
        });

        // Sətirləri say
        $totalRows = 0;
        $chunks = [];

        // 1000-lik chunk-lara böl
        $rows->chunk(1000)->each(function ($chunk, $index) use (&$totalRows, &$chunks) {
            $chunks[] = new ProcessCsvChunkJob(
                fileUploadId: $this->fileUpload->id,
                rows: $chunk->values()->all(),
                chunkIndex: $index
            );
            $totalRows += $chunk->count();
        });

        // Batch yaratmaq — bütün chunk job-ları bir batch-da
        $batch = Bus::batch($chunks)
            ->then(function ($batch) {
                // Hamısı tamamlandıqda
                $fileUpload = FileUpload::find($this->fileUpload->id);
                $fileUpload->update([
                    'status' => 'completed',
                    'processing_progress' => 100,
                ]);
            })
            ->catch(function ($batch, \Throwable $e) {
                // Hər hansı biri uğursuz olduqda
                $fileUpload = FileUpload::find($this->fileUpload->id);
                $fileUpload->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            })
            ->finally(function ($batch) {
                // Batch tamamlandıqda (uğurlu və ya uğursuz)
            })
            ->name("CSV Import: {$this->fileUpload->original_filename}")
            ->allowFailures()
            ->dispatch();

        // Batch ID-ni saxla (progress tracking üçün)
        $this->fileUpload->update([
            'metadata' => json_encode([
                'batch_id' => $batch->id,
                'total_rows' => $totalRows,
                'total_chunks' => count($chunks),
            ]),
        ]);
    }
}
```

### Import Job — Chunk İşləyicisi

*Bu kod CSV chunk-unu validate edərək bulk upsert ilə verilənlər bazasına yazan job-u göstərir:*

```php
// app/Jobs/ProcessCsvChunkJob.php
namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProcessCsvChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $fileUploadId,
        public array $rows,
        public int $chunkIndex
    ) {}

    public function handle(): void
    {
        // Batch ləğv edilibsə, dayandır
        if ($this->batch()?->cancelled()) {
            return;
        }

        $imported = 0;
        $errors = [];
        $insertData = [];

        foreach ($this->rows as $index => $row) {
            // Validation
            $validator = Validator::make($row, [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:50',
                'price' => 'required|numeric|min:0',
                'quantity' => 'required|integer|min:0',
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'row' => ($this->chunkIndex * 1000) + $index + 2, // +2: header + 0-index
                    'errors' => $validator->errors()->toArray(),
                    'data' => $row,
                ];
                continue;
            }

            $insertData[] = [
                'name' => $row['name'],
                'sku' => $row['sku'],
                'price' => (float) $row['price'],
                'quantity' => (int) $row['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $imported++;
        }

        // Bulk insert — performans üçün
        if (!empty($insertData)) {
            // Upsert — dublikat SKU-ları update et
            Product::upsert(
                $insertData,
                ['sku'], // unique key
                ['name', 'price', 'quantity', 'updated_at'] // update olunacaq column-lar
            );
        }

        // Error-ları logla
        if (!empty($errors)) {
            Log::warning('CSV import chunk errors', [
                'file_upload_id' => $this->fileUploadId,
                'chunk_index' => $this->chunkIndex,
                'error_count' => count($errors),
                'errors' => array_slice($errors, 0, 10), // İlk 10 error
            ]);
        }

        Log::info('CSV chunk processed', [
            'file_upload_id' => $this->fileUploadId,
            'chunk_index' => $this->chunkIndex,
            'imported' => $imported,
            'errors' => count($errors),
        ]);
    }
}
```

---

## Progress Tracking (Redis + Broadcasting)

### Progress Service

*Progress Service üçün kod nümunəsi:*
```php
// app/Services/UploadProgressService.php
namespace App\Services;

use App\Events\UploadProgressUpdated;
use App\Models\FileUpload;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;

class UploadProgressService
{
    /**
     * Upload/processing progress-i yeniləyir və broadcast edir.
     */
    public function updateProgress(FileUpload $upload, int $progress, string $message = ''): void
    {
        $upload->update(['processing_progress' => $progress]);

        // Redis-ə yaz (tez oxumaq üçün)
        Redis::hset("upload_progress:{$upload->upload_id}", [
            'progress' => $progress,
            'status' => $upload->status,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ]);
        Redis::expire("upload_progress:{$upload->upload_id}", 86400);

        // WebSocket ilə real-time push
        broadcast(new UploadProgressUpdated(
            userId: $upload->user_id,
            uploadId: $upload->upload_id,
            progress: $progress,
            status: $upload->status,
            message: $message
        ))->toOthers();
    }

    /**
     * Batch progress-i yoxlamaq (Job Batch əsaslı).
     */
    public function getBatchProgress(FileUpload $upload): array
    {
        $metadata = json_decode($upload->metadata ?? '{}', true);
        $batchId = $metadata['batch_id'] ?? null;

        if (!$batchId) {
            return [
                'progress' => $upload->processing_progress,
                'status' => $upload->status,
            ];
        }

        $batch = Bus::findBatch($batchId);

        if (!$batch) {
            return ['progress' => 0, 'status' => 'unknown'];
        }

        return [
            'progress' => $batch->progress(),
            'status' => $batch->finished() ? 'completed' : 'processing',
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'processed_jobs' => $batch->processedJobs(),
        ];
    }
}
```

### Broadcasting Event

*Broadcasting Event üçün kod nümunəsi:*
```php
// app/Events/UploadProgressUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $uploadId,
        public int $progress,
        public string $status,
        public string $message = ''
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'upload.progress';
    }
}
```

---

## Image Processing Pipeline

*Image Processing Pipeline üçün kod nümunəsi:*
```php
// app/Jobs/ProcessImageJob.php
namespace App\Jobs;

use App\Models\FileUpload;
use App\Services\UploadProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProcessImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    private array $sizes = [
        'thumbnail' => ['width' => 150, 'height' => 150],
        'small' => ['width' => 300, 'height' => 300],
        'medium' => ['width' => 600, 'height' => 600],
        'large' => ['width' => 1200, 'height' => 1200],
    ];

    public function __construct(
        public FileUpload $fileUpload
    ) {}

    public function handle(UploadProgressService $progressService): void
    {
        $this->fileUpload->update(['status' => 'processing']);

        $manager = new ImageManager(new Driver());
        $originalPath = Storage::path($this->fileUpload->storage_path);
        $basePath = dirname($this->fileUpload->storage_path);
        $filename = pathinfo($this->fileUpload->original_filename, PATHINFO_FILENAME);

        $totalSteps = count($this->sizes) + 1; // +1 optimization üçün
        $currentStep = 0;

        $results = [];

        foreach ($this->sizes as $sizeName => $dimensions) {
            $currentStep++;
            $progress = (int) (($currentStep / $totalSteps) * 100);

            $progressService->updateProgress(
                $this->fileUpload,
                $progress,
                "{$sizeName} versiyası yaradılır..."
            );

            $image = $manager->read($originalPath);

            // Aspect ratio saxlayaraq resize
            $image->scaleDown(
                width: $dimensions['width'],
                height: $dimensions['height']
            );

            // WebP formatında saxla (daha kiçik ölçü)
            $outputPath = "{$basePath}/{$filename}_{$sizeName}.webp";
            $image->toWebp(quality: 80)->save(Storage::path($outputPath));

            $results[$sizeName] = $outputPath;
        }

        // Original-ı optimize et
        $currentStep++;
        $progressService->updateProgress(
            $this->fileUpload,
            (int) (($currentStep / $totalSteps) * 100),
            'Original optimize edilir...'
        );

        $image = $manager->read($originalPath);
        $optimizedPath = "{$basePath}/{$filename}_optimized.webp";
        $image->toWebp(quality: 85)->save(Storage::path($optimizedPath));
        $results['optimized'] = $optimizedPath;

        // Tamamlandı
        $this->fileUpload->update([
            'status' => 'completed',
            'processing_progress' => 100,
            'metadata' => json_encode(['variants' => $results]),
        ]);

        $progressService->updateProgress($this->fileUpload, 100, 'Tamamlandı!');
    }
}
```

---

## Memory Optimization Texnikaları

### 1. Generator ilə böyük fayl oxuma

*1. Generator ilə böyük fayl oxuma üçün kod nümunəsi:*
```php
// Yanlış — bütün faylı memory-ə yükləyir
$lines = file('huge_file.csv'); // 500MB → OUT OF MEMORY!

// Düzgün — generator ilə sətir-sətir oxuyur
function readCsvLazy(string $path): \Generator
{
    $handle = fopen($path, 'r');
    $headers = fgetcsv($handle);

    while (($row = fgetcsv($handle)) !== false) {
        yield array_combine($headers, $row); // Hər dəfə 1 sətir
    }

    fclose($handle);
}

// İstifadə
foreach (readCsvLazy('/path/to/file.csv') as $row) {
    // Hər sətir ayrıca işlənir — memory sabit qalır
    processRow($row);
}
```

### 2. Laravel LazyCollection

*2. Laravel LazyCollection üçün kod nümunəsi:*
```php
use Illuminate\Support\LazyCollection;

// Faylı lazy oxu
LazyCollection::make(function () {
    $handle = fopen(storage_path('app/huge.csv'), 'r');
    while (($line = fgets($handle)) !== false) {
        yield $line;
    }
    fclose($handle);
})
->chunk(1000)
->each(function ($chunk) {
    // 1000-lik chunk-lar ilə işlə
    DB::table('records')->insert($chunk->map(function ($line) {
        $data = str_getcsv($line);
        return [
            'name' => $data[0],
            'email' => $data[1],
        ];
    })->all());
});
```

### 3. Stream Processing

*3. Stream Processing üçün kod nümunəsi:*
```php
// Böyük faylı stream ilə S3-ə köçürmək (memory-efficient)
$inputStream = fopen('/tmp/huge_file.csv', 'rb');
Storage::disk('s3')->writeStream('exports/huge_file.csv', $inputStream);
fclose($inputStream);

// S3-dən stream ilə oxumaq
$stream = Storage::disk('s3')->readStream('exports/huge_file.csv');
while (($line = fgets($stream)) !== false) {
    // Sətir-sətir oxu
}
fclose($stream);
```

---

## Error Handling və Retry

*Error Handling və Retry üçün kod nümunəsi:*
```php
// app/Jobs/ProcessUploadedFileJob.php
namespace App\Jobs;

use App\Models\FileUpload;
use App\Services\UploadProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUploadedFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120]; // Retry arasında gözləmə
    public int $timeout = 600; // 10 dəqiqə
    public int $maxExceptions = 2; // 2 exception-dan sonra dayandır

    public function __construct(
        public FileUpload $fileUpload
    ) {}

    /**
     * Fayl tipinə görə uyğun processing job-u dispatch edir.
     */
    public function handle(UploadProgressService $progressService): void
    {
        $mimeType = $this->fileUpload->mime_type;

        try {
            match (true) {
                str_starts_with($mimeType, 'image/') => ProcessImageJob::dispatch($this->fileUpload),
                $mimeType === 'text/csv' => ProcessCsvImportJob::dispatch($this->fileUpload),
                str_starts_with($mimeType, 'video/') => $this->handleVideo(),
                default => throw new \RuntimeException("Dəstəklənməyən fayl tipi: {$mimeType}"),
            };
        } catch (\Throwable $e) {
            Log::error('File processing failed', [
                'upload_id' => $this->fileUpload->upload_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function handleVideo(): void
    {
        // Video processing (FFmpeg ilə)
        $this->fileUpload->update([
            'status' => 'processing',
        ]);

        // Video processing job dispatch...
    }

    /**
     * Bütün retry-lar uğursuz olduqda çağırılır.
     */
    public function failed(\Throwable $exception): void
    {
        $this->fileUpload->update([
            'status' => 'failed',
            'error_message' => "Processing uğursuz oldu: {$exception->getMessage()}",
        ]);

        Log::critical('File processing permanently failed', [
            'upload_id' => $this->fileUpload->upload_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
```

---

## Routes

*Routes üçün kod nümunəsi:*
```php
// routes/api.php
use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\S3DirectUploadController;

Route::middleware('auth:sanctum')->prefix('uploads')->group(function () {
    // Chunked Upload
    Route::post('/initiate', [ChunkedUploadController::class, 'initiate']);
    Route::post('/{uploadId}/chunk', [ChunkedUploadController::class, 'uploadChunk']);
    Route::get('/{uploadId}/status', [ChunkedUploadController::class, 'status']);

    // S3 Direct Upload
    Route::post('/presigned-url', [S3DirectUploadController::class, 'createPresignedUrl']);
    Route::post('/{uploadId}/confirm', [S3DirectUploadController::class, 'confirmUpload']);
});
```

---

## Interview Sualları və Cavablar

**S: PHP-də böyük fayl işləyərkən memory problemi niyə yaranır?**
C: `file_get_contents()` yaxud `fread()` bütün faylı memory-ə yükləyir. 512MB fayl + 128MB PHP limit = fatal error. Həll: stream-based oxuma — `fgets()`, `SplFileObject`, `LazyCollection::make(fn() => yield...)`. Bu yanaşmada eyni anda yalnız 1 sətir memory-də olur.

**S: S3 pre-signed URL nədir, niyə istifadə edirik?**
C: Server tərəfindən imzalanmış müvəqqəti URL — client birbaşa S3-ə yükləyir, server arasından keçmir. Üstünlükləri: (1) PHP server-in bandwidth-ni azaldır, (2) PHP memory limit problem olmur, (3) S3-in multipart upload-u ilə böyük fayllar dəstəklənir. URL expiry (15 dəq) və allowed MIME types ilə qorunur.

**S: Chunked upload-da chunk-ların sırası necə idarə edilir?**
C: Hər chunk-a `chunk_index` (0-based) verilir. Server hər chunk-u `chunk_000`, `chunk_001`... kimi ayrıca saxlayır. Assembly zamanı sıra ilə birləşdirir. Paralel yükləmə mümkündür — sıra yalnız birləşdirmə zamanı vacibdir. `Content-Range` header-i ilə da idarə etmək mümkündür.

**S: CSV import zamanı partial failure necə idarə edilir?**
C: İki yanaşma: (1) All-or-nothing — hər hansı xəta olsa rollback, user bütün CSV-ni düzəldib yenidən yükləyir. (2) Partial success — uğurlu sətirləri import et, xətalı sətirləri error report-a yaz (CSV formatında), user yalnız xətalıları düzəldir. Böyük import-larda partial success daha yaxşıdır.

**S: Job timeout CSV processing üçün nə qədər olmalıdır?**
C: Sətir sayından asılıdır. 1M sətirlik CSV üçün 10+ dəqiqə lazım ola bilər. Job-u chunk-lara böl: hər chunk-u ayrı job kimi dispatch et (Bus::batch). Bu həm timeout-u həll edir, həm paralel processing imkanı verir. Hər job-un öz timeout-u olur (məs. 5 dəqiqə / 50K sətir).

---

## Interview-da Bu Sualı Necə Cavablandırmaq

1. **Memory problemi** — PHP memory limit var, böyük faylı birdəfəyə memory-ə yükləmək mümkün deyil
2. **Chunked upload** — faylı hissələrə bölmək, resumable upload
3. **S3 direct upload** — server yükünü azaltmaq
4. **Queue-based processing** — user gözləməsin, background-da işlənsin
5. **Progress tracking** — Redis + WebSocket ilə real-time progress
6. **LazyCollection/Generator** — memory-efficient data processing
7. **Batch processing** — Laravel Bus::batch ilə paralel chunk processing
8. **Error handling** — retry, failed job handling, partial import

---

## Anti-patterns

**1. Faylı PHP server üzərindən yükləmək**
`$request->file('csv')->store(...)` — fayl PHP server-dən keçir, RAM-ı tutur. Böyük fayllar üçün S3 pre-signed URL ilə client-dən birbaşa S3-ə yüklə.

**2. Böyük faylı bir dəfəyə memory-ə yükləmək**
`file_get_contents('100mb.csv')` — PHP memory limit (128/256MB) ilə crash. `SplFileObject` yaxud `LazyCollection` ilə sətir-sətir oxu.

**3. Upload-ı HTTP request-in içindən process etmək**
Upload tamamlandığında dərhal CSV-ni parse etmək — user 30+ saniyə gözləyir. Queue-ya dispatch et, 202 Accepted qaytar.

**4. Validation olmadan store etmək**
File type-ı yoxlamamaq — MIME type spoofing, PHP shell upload. `mimes:csv,xlsx`, `max:10240` validation mütləqdir.

**5. Import idempotenliyi yoxdur**
Eyni fayl iki dəfə yüklənir → ikiqat data. Fayl hash-ı (`md5_file`) və ya idempotency key ilə dublikat yüklənmənin qarşısını al.

**6. Uğursuz rowları tamamilə ignore etmək**
1000 sətirlik CSV-nin 50-si invalid — hamısı uğursuz sayılır. Partial success: uğurlu olanları import et, uğursuzu error report-a yaz.
