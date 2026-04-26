# Kütləvi İdxal Emalı (Middle)

## Problem Təsviri

E-ticarət platformasında administrator hər gün onminlərlə məhsul, müştəri və ya sifariş məlumatını CSV/XML formatında idxal etməlidir. 100,000+ sətirlik fayl yükləndikdə aşağıdakı problemlər yaranır:

- **Timeout**: Veb server 30-60 saniyəlik PHP execution limitinə çatır
- **Yaddaş**: Bütün faylı RAM-a oxumaq `memory_limit`-i aşır
- **Validation xətaları**: Minlərlə sətir arasında 50-100 etibarsız sətir ola bilər
- **Qismən uğursuzluq**: Xətalı sətirlərdə bütün idxalı dayandırmaq əvəzinə davam etmək lazımdır
- **İzləmə**: İstifadəçi uzun sürən prosesin gedişatını görməlidir
- **Dublikatlar**: Eyni məlumat ikinci dəfə idxal edilə bilər

**Həll arxitekturası**: Upload → Queue → Chunk-larla Emal → Hesabat

---

## 1. Arxitektura: Upload → Queue → Chunk-larla Emal

Sinxron idxal əvəzinə asinxron pipeline istifadə edilir.

```
İstifadəçi → [HTTP Upload] → Fayl disk/S3-ə saxlanır
                                     ↓
                            [Import Job Queue-ya göndərilir]
                                     ↓
                    [Worker: fayl chunk-larla oxunur]
                            /              \
                    [Validation]        [DB Insert]
                            \              /
                         [Progress Update]
                                  ↓
                      [Xəta Hesabatı CSV]
```

*Bu kod yüklənmiş faylı diska saxlayıb import job-unu queue-ya göndərən və status URL-i qaytaran controller-ı göstərir:*

```php
// ImportController.php
class ImportController extends Controller
{
    public function upload(ImportRequest $request): JsonResponse
    {
        // 1. Faylı dərhal disk-ə saxla (yaddaşda saxlama)
        $path = $request->file('csv')->storeAs(
            'imports',
            Str::uuid() . '.csv',
            'local'
        );

        // 2. Import qeydini yarat
        $import = Import::create([
            'user_id'    => auth()->id(),
            'file_path'  => $path,
            'status'     => ImportStatus::PENDING,
            'total_rows' => 0,
        ]);

        // 3. Job-u queue-ya göndər — HTTP cavabı dərhal qayıdır
        ProcessImportJob::dispatch($import->id)
            ->onQueue('imports');

        return response()->json([
            'import_id' => $import->id,
            'status'    => 'queued',
            'track_url' => route('imports.status', $import->id),
        ], 202);
    }

    public function status(Import $import): JsonResponse
    {
        return response()->json([
            'status'          => $import->status,
            'total_rows'      => $import->total_rows,
            'processed_rows'  => $import->processed_rows,
            'failed_rows'     => $import->failed_rows,
            'progress_percent'=> $import->progress_percent,
            'error_report_url'=> $import->error_report_url,
        ]);
    }
}
```

---

## 2. Böyük Faylı Disk/S3-ə Axın Yükləmə

Böyük faylları yükləyərkən PHP tüm faylı yaddaşa çəkməməlidir.

*Bu kod S3 axın oxuma konfiqurasiyasını göstərir:*

```php
// config/filesystems.php — S3 konfiqurasiyası
's3' => [
    'driver' => 's3',
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'stream_reads' => true, // axın oxuma
],
```

*Bu kod böyük faylı yaddaşda saxlamadan S3-ə axın yükləyən və chunk-larla yükləməni birləşdirən servis sinfini göstərir:*

```php
// Böyük faylı axın ilə S3-ə yüklə
class StreamingUploadService
{
    public function uploadLargeFile(UploadedFile $file, string $destination): string
    {
        $stream = fopen($file->getRealPath(), 'r');

        try {
            // S3-ə axın yükləmə — yaddaşda bütün fayl saxlanmır
            Storage::disk('s3')->put(
                $destination,
                $stream,
                ['visibility' => 'private']
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $destination;
    }

    // Nginx/Apache ilə böyük fayl yükləmə üçün
    // php.ini: upload_max_filesize = 500M, post_max_size = 500M
    // nginx: client_max_body_size 500M;
    public function handleChunkedUpload(Request $request): JsonResponse
    {
        $chunkIndex  = $request->input('chunk_index');
        $totalChunks = $request->input('total_chunks');
        $fileId      = $request->input('file_id');

        // Hər chunk-u müvəqqəti saxla
        $request->file('chunk')->storeAs(
            "temp/{$fileId}",
            "chunk_{$chunkIndex}",
            'local'
        );

        if ($chunkIndex === $totalChunks - 1) {
            // Bütün chunk-lar gəldi — birləşdir
            $this->mergeChunks($fileId, $totalChunks);
        }

        return response()->json(['received' => $chunkIndex]);
    }

    private function mergeChunks(string $fileId, int $totalChunks): void
    {
        $finalPath = storage_path("app/imports/{$fileId}.csv");
        $output    = fopen($finalPath, 'w');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = storage_path("app/temp/{$fileId}/chunk_{$i}");
            $input     = fopen($chunkPath, 'r');
            stream_copy_to_stream($input, $output);
            fclose($input);
            unlink($chunkPath);
        }

        fclose($output);
    }
}
```

---

## 3. Chunk-lu Oxuma — PHP SplFileObject və Generator-lar

Bütün faylı yaddaşa yükləmək əvəzinə sətir-sətir oxuyuruq.

*Bu kod SplFileObject və generator ilə hər dəfə yalnız bir sətir yaddaşda saxlayaraq böyük CSV faylını oxuyan sinfi göstərir:*

```php
// SplFileObject ilə yaddaş effektiv oxuma
class CsvReader
{
    private SplFileObject $file;

    public function __construct(string $filePath)
    {
        $this->file = new SplFileObject($filePath, 'r');
        $this->file->setFlags(
            SplFileObject::READ_CSV       |
            SplFileObject::SKIP_EMPTY     |
            SplFileObject::DROP_NEW_LINE
        );
        $this->file->setCsvControl(',', '"', '\\');
    }

    /**
     * Generator: hər dəfə yalnız bir sətir yaddaşda saxlanır.
     * 100k sətir üçün belə yaddaş sabit qalır.
     */
    public function rows(): Generator
    {
        $this->file->rewind();
        $headers = $this->file->current(); // başlıq sətri
        $this->file->next();

        $lineNumber = 1;
        while (!$this->file->eof()) {
            $row = $this->file->current();
            $this->file->next();

            if (empty(array_filter($row))) {
                $lineNumber++;
                continue;
            }

            // Başlıqları dəyərlərə map et
            if (count($headers) === count($row)) {
                yield $lineNumber => array_combine($headers, $row);
            }

            $lineNumber++;
        }
    }

    /**
     * Generator: chunk-larla oxuma — hər chunk bir array
     */
    public function chunks(int $size = 1000): Generator
    {
        $chunk      = [];
        $lineNumber = 0;

        foreach ($this->rows() as $line => $row) {
            $chunk[$line] = $row;
            $lineNumber++;

            if ($lineNumber % $size === 0) {
                yield $chunk;
                $chunk = [];
            }
        }

        // Son qalan chunk
        if (!empty($chunk)) {
            yield $chunk;
        }
    }
}
```

*Bu kod CsvReader-in chunk-larla istifadəsini göstərir:*

```php
// İstifadəsi — yaddaş effektivliyi
$reader = new CsvReader('/path/to/file.csv');

foreach ($reader->chunks(500) as $chunk) {
    // $chunk maksimum 500 sətir ehtiva edir
    // Hər iterasiyada köhnə chunk garbage collected olur
    processChunk($chunk);
}
```

---

## 4. Validation Pipeline — Sıra-sıra Validation, Xətaların Toplanması

Hər sətir ayrıca validate edilir, xətalar toplanır, proses dayanmır.

*Bu kod hər sətiri ayrıca yoxlayan, xətaları toplayan amma prosesi dayandırmayan validation pipeline-ı göstərir:*

```php
// RowValidator.php
class ProductRowValidator
{
    private array $existingSkus = [];

    public function __construct(
        private readonly ValidatorFactory $validator,
        private readonly ProductRepository $repository
    ) {}

    public function validate(array $row, int $lineNumber): ValidationResult
    {
        // 1. Struktur validation
        $validator = $this->validator->make($row, [
            'sku'         => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9\-]+$/'],
            'name'        => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], $this->customMessages());

        if ($validator->fails()) {
            return ValidationResult::failure(
                $lineNumber,
                $row,
                $validator->errors()->toArray()
            );
        }

        // 2. Biznes qaydaları validation
        $errors = [];

        // Fayl daxilindəki dublikat yoxlaması
        $sku = strtoupper(trim($row['sku']));
        if (in_array($sku, $this->existingSkus, true)) {
            $errors['sku'][] = "SKU '{$sku}' bu faylda artıq mövcuddur.";
        } else {
            $this->existingSkus[] = $sku;
        }

        // Qiymət məntiqi
        if (isset($row['sale_price']) && $row['sale_price'] >= $row['price']) {
            $errors['sale_price'][] = 'Endirimli qiymət əsas qiymətdən az olmalıdır.';
        }

        if (!empty($errors)) {
            return ValidationResult::failure($lineNumber, $row, $errors);
        }

        return ValidationResult::success($lineNumber, $row);
    }

    private function customMessages(): array
    {
        return [
            'sku.required'         => 'SKU sahəsi mütləqdir.',
            'sku.regex'            => 'SKU yalnız böyük hərflər, rəqəmlər və tire (-) ehtiva edə bilər.',
            'price.numeric'        => 'Qiymət rəqəm olmalıdır.',
            'category_id.exists'   => 'Göstərilən kateqoriya mövcud deyil.',
        ];
    }
}

// ValidationResult value object
class ValidationResult
{
    private function __construct(
        public readonly bool $isValid,
        public readonly int $lineNumber,
        public readonly array $data,
        public readonly array $errors = []
    ) {}

    public static function success(int $lineNumber, array $data): self
    {
        return new self(true, $lineNumber, $data);
    }

    public static function failure(int $lineNumber, array $data, array $errors): self
    {
        return new self(false, $lineNumber, $data, $errors);
    }
}
```

---

## 5. Qismən Uğursuzluq İdarəsi — Etibarsız Sətirləri Keç vs Hamısını Ləğv Et

İki fərqli strategiya mövcuddur. Seçim iş tələbindən asılıdır.

*Bu kod import strategiyasını — etibarsızları keçmə, hamısı-ya-yoxsa-heç, xətada dayan — enum kimi müəyyən edir:*

```php
// ImportStrategy enum
enum ImportStrategy: string
{
    case SKIP_INVALID  = 'skip_invalid';   // Etibarsız sətirləri keç, digərlərini idxal et
    case ALL_OR_NONE   = 'all_or_none';    // Hər hansı xəta varsa — hamısını ləğv et
    case STOP_ON_ERROR = 'stop_on_error';  // İlk xətada uğursuzluq
}
```

*Bu kod seçilmiş strategiyaya görə hər chunk-u validate edib etibarlıları idxal edən, etibarsızları idarə edən processor-ı göstərir:*

```php
// ChunkProcessor.php
class ChunkProcessor
{
    public function __construct(
        private readonly ProductRowValidator $validator,
        private readonly ProductRepository $repository,
        private readonly ImportStrategy $strategy
    ) {}

    public function processChunk(array $chunk, Import $import): ChunkResult
    {
        $validRows   = [];
        $invalidRows = [];

        // 1. Bütün sətirləri validate et
        foreach ($chunk as $lineNumber => $row) {
            $result = $this->validator->validate($row, $lineNumber);

            if ($result->isValid) {
                $validRows[$lineNumber] = $result->data;
            } else {
                $invalidRows[$lineNumber] = $result->errors;
            }
        }

        // 2. Strategiyaya əsasən qərar ver
        return match ($this->strategy) {
            ImportStrategy::SKIP_INVALID  => $this->skipInvalidStrategy($validRows, $invalidRows),
            ImportStrategy::ALL_OR_NONE   => $this->allOrNoneStrategy($validRows, $invalidRows, $import),
            ImportStrategy::STOP_ON_ERROR => $this->stopOnErrorStrategy($validRows, $invalidRows),
        };
    }

    private function skipInvalidStrategy(array $validRows, array $invalidRows): ChunkResult
    {
        // Etibarlıları idxal et, etibarsızları xəta siyahısına əlavə et
        $insertedCount = 0;
        if (!empty($validRows)) {
            $insertedCount = $this->repository->batchInsert($validRows);
        }

        return new ChunkResult(
            inserted: $insertedCount,
            failed:   count($invalidRows),
            errors:   $invalidRows
        );
    }

    private function allOrNoneStrategy(
        array $validRows,
        array $invalidRows,
        Import $import
    ): ChunkResult {
        // Hər hansı xəta varsa — bu chunk-u idxal etmə
        if (!empty($invalidRows)) {
            return new ChunkResult(
                inserted: 0,
                failed:   count($validRows) + count($invalidRows),
                errors:   $invalidRows,
                aborted:  true
            );
        }

        $insertedCount = $this->repository->batchInsert($validRows);

        return new ChunkResult(inserted: $insertedCount, failed: 0, errors: []);
    }

    private function stopOnErrorStrategy(array $validRows, array $invalidRows): ChunkResult
    {
        if (!empty($invalidRows)) {
            throw new ImportValidationException(
                "Sətir xətaları aşkarlandı, idxal dayandırıldı.",
                $invalidRows
            );
        }

        $insertedCount = $this->repository->batchInsert($validRows);

        return new ChunkResult(inserted: $insertedCount, failed: 0, errors: []);
    }
}
```

---

## 6. Transaction Strategiyası — Chunk Başına Transaction-lar vs Hamısı-ya-Yoxsa-Heç

*Bu kod chunk başına transaction, atomik idxal və savepoint-lərlə qismən rollback strategiyalarını müqayisəli göstərir:*

```php
// Strategiya 1: Chunk başına transaction (tövsiyə edilir böyük fayllar üçün)
class ChunkTransactionImporter
{
    public function importChunk(array $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                Product::create($row);
            }
        });
        // Chunk uğurla commit edildi, növbəti chunk-a keç
        // Xəta olarsa — yalnız bu chunk rollback olur
    }
}

// Strategiya 2: Hamısı-ya-Yoxsa-Heç (kiçik fayllar üçün uyğun)
class AtomicImporter
{
    public function import(array $allRows): void
    {
        DB::transaction(function () use ($allRows) {
            foreach ($allRows as $row) {
                Product::create($row);
            }
            // Hər hansı xəta varsa — bütün idxal rollback olur
        });
    }
}

// Strategiya 3: Savepoint-lərlə chunk transaction (mürəkkəb ssenari)
class SavepointImporter
{
    public function importWithSavepoints(array $chunks): ImportSummary
    {
        $summary = new ImportSummary();

        DB::transaction(function () use ($chunks, $summary) {
            foreach ($chunks as $index => $chunk) {
                $savepointName = "chunk_{$index}";

                try {
                    DB::statement("SAVEPOINT {$savepointName}");

                    foreach ($chunk as $row) {
                        Product::create($row);
                        $summary->incrementInserted();
                    }

                    DB::statement("RELEASE SAVEPOINT {$savepointName}");

                } catch (\Exception $e) {
                    // Yalnız bu chunk-u geri al
                    DB::statement("ROLLBACK TO SAVEPOINT {$savepointName}");
                    $summary->addFailedChunk($index, $e->getMessage());
                }
            }
        });

        return $summary;
    }
}
```

**Tövsiyə**: 100k+ sətir üçün chunk başına ayrı transaction istifadə edin. Tək böyük transaction:
- Uzun müddət lock saxlayır
- Rollback uzun çəkir
- Deadlock ehtimalını artırır

---

## 7. İdxal Job-u Tərəqqi İzləmə

*Bu kod chunk-larla faylı oxuyub prosses edən, tərəqqini izləyən və xəta hesabatı yaradan import job-unu göstərir:*

```php
// ProcessImportJob.php
class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;   // 1 saat
    public int $tries   = 1;       // Yenidən cəhd etmə (idxal idempotent deyil)

    public function __construct(private readonly int $importId) {}

    public function handle(
        CsvReader $reader,
        ChunkProcessor $processor,
        ImportProgressTracker $tracker
    ): void {
        $import = Import::findOrFail($this->importId);
        $import->markAsProcessing();

        try {
            $filePath  = storage_path("app/{$import->file_path}");
            $totalRows = $this->countRows($filePath);

            $import->update(['total_rows' => $totalRows]);

            $processedRows = 0;
            $failedRows    = 0;
            $errorLog      = [];

            foreach ($reader->chunks(500) as $chunk) {
                $result = $processor->processChunk($chunk, $import);

                $processedRows += count($chunk);
                $failedRows    += $result->failed;

                // Xətaları topla
                foreach ($result->errors as $lineNumber => $errors) {
                    $errorLog[$lineNumber] = $errors;
                }

                // Tərəqqi yenilə (hər 500 sətirdən bir)
                $tracker->update($import, $processedRows, $failedRows);
            }

            // Xəta hesabatı CSV-ni yarat
            if (!empty($errorLog)) {
                $errorReportPath = $this->generateErrorReport($import, $errorLog);
                $import->update(['error_report_path' => $errorReportPath]);
            }

            $import->markAsCompleted($processedRows, $failedRows);

        } catch (\Throwable $e) {
            $import->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    private function countRows(string $filePath): int
    {
        // Sətir sayını sürətlə say (başlıq sətri çıxılır)
        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        return $file->key(); // başlıq sətri daxil olmaqla
    }
}
```

*Bu kod tərəqqini Redis-ə yazıb hər 5000 sətirdən bir DB-ni yeniləyən import tərəqqi izləyicisini göstərir:*

```php
// ImportProgressTracker.php
class ImportProgressTracker
{
    public function __construct(private readonly Cache $cache) {}

    public function update(Import $import, int $processed, int $failed): void
    {
        // Cache-ə yaz (DB-ə hər chunk üçün yazmaq yük yaradır)
        $this->cache->put(
            "import_progress_{$import->id}",
            [
                'processed' => $processed,
                'failed'    => $failed,
                'percent'   => round(($processed / $import->total_rows) * 100, 1),
                'updated_at'=> now()->toIso8601String(),
            ],
            ttl: 3600
        );

        // Hər 5000 sətirdə bir DB-ni yenilə
        if ($processed % 5000 === 0) {
            $import->update([
                'processed_rows' => $processed,
                'failed_rows'    => $failed,
            ]);
        }
    }

    public function get(int $importId): array
    {
        return $this->cache->get("import_progress_{$importId}", [
            'processed' => 0,
            'failed'    => 0,
            'percent'   => 0,
        ]);
    }
}
```

*Bu kod import modelinin status keçid metodlarını — processing, completed, failed — göstərir:*

```php
// Import model — status metodları
class Import extends Model
{
    protected $casts = [
        'status' => ImportStatus::class,
    ];

    public function markAsProcessing(): void
    {
        $this->update([
            'status'     => ImportStatus::PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(int $processed, int $failed): void
    {
        $this->update([
            'status'          => ImportStatus::COMPLETED,
            'processed_rows'  => $processed,
            'failed_rows'     => $failed,
            'completed_at'    => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status'       => ImportStatus::FAILED,
            'error_reason' => $reason,
            'completed_at' => now(),
        ]);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }
        return round(($this->processed_rows / $this->total_rows) * 100, 1);
    }

    public function getErrorReportUrlAttribute(): ?string
    {
        if (!$this->error_report_path) {
            return null;
        }
        return route('imports.error-report', $this->id);
    }
}
```

---

## 8. Xəta Hesabatı — Endirilə Bilən Xəta Hesabatı CSV

*8. Xəta Hesabatı — Endirilə Bilən Xəta Hesabatı CSV üçün kod nümunəsi:*
```php
// ErrorReportGenerator.php
class ErrorReportGenerator
{
    public function generate(Import $import, array $errorLog): string
    {
        $reportPath = "import_errors/import_{$import->id}_errors.csv";
        $fullPath   = storage_path("app/{$reportPath}");

        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file = new SplFileObject($fullPath, 'w');

        // Başlıq sətri
        $file->fputcsv([
            'Sətir Nömrəsi',
            'Sahə',
            'Xəta Mesajı',
            'Orijinal Dəyər',
        ]);

        // Xəta qeydlərini yaz
        foreach ($errorLog as $lineNumber => $fieldErrors) {
            foreach ($fieldErrors as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $file->fputcsv([
                        $lineNumber,
                        $field,
                        $message,
                        $import->getOriginalValue($lineNumber, $field) ?? '',
                    ]);
                }
            }
        }

        return $reportPath;
    }
}

// Xəta hesabatını endirmə endpoint-i
class ImportController extends Controller
{
    public function downloadErrorReport(Import $import): StreamedResponse
    {
        $this->authorize('view', $import);

        abort_unless($import->error_report_path, 404, 'Xəta hesabatı mövcud deyil.');

        $fullPath = storage_path("app/{$import->error_report_path}");
        abort_unless(file_exists($fullPath), 404);

        return response()->streamDownload(function () use ($fullPath) {
            $file = new SplFileObject($fullPath, 'r');
            while (!$file->eof()) {
                echo $file->fgets();
            }
        }, "import_{$import->id}_errors.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
```

---

## 9. İdxal Zamanı Dublikat Aşkarlaması

*9. İdxal Zamanı Dublikat Aşkarlaması üçün kod nümunəsi:*
```php
// DuplicateDetector.php
class DuplicateDetector
{
    private array $seenInFile   = [];  // Faylda görülən SKU-lar
    private array $existingInDb = [];  // DB-dəki SKU-lar (cache)

    public function __construct(private readonly ProductRepository $repository) {}

    /**
     * Böyük chunk idxalından əvvəl mövcud SKU-ları yüklə.
     * Chunk başına ayrı DB sorğusu əvəzinə bir dəfə yükləmək effektivdir.
     */
    public function preloadExistingSkus(array $skus): void
    {
        // Yalnız bu chunk-dakı SKU-ları yoxla
        $found = $this->repository->findSkusBulk($skus);

        foreach ($found as $sku) {
            $this->existingInDb[strtoupper($sku)] = true;
        }
    }

    public function isDuplicate(string $sku): DuplicateCheckResult
    {
        $normalizedSku = strtoupper(trim($sku));

        // 1. Fayl daxilində dublikat yoxla
        if (isset($this->seenInFile[$normalizedSku])) {
            return DuplicateCheckResult::fileDuplicate($normalizedSku);
        }

        // 2. DB-də mövcudluq yoxla
        if (isset($this->existingInDb[$normalizedSku])) {
            return DuplicateCheckResult::dbDuplicate($normalizedSku);
        }

        // Dublikat deyil — izlə
        $this->seenInFile[$normalizedSku] = true;

        return DuplicateCheckResult::unique($normalizedSku);
    }
}

// Dublikat strategiyaları
enum DuplicateStrategy: string
{
    case SKIP   = 'skip';    // Dublikatı keç
    case UPDATE = 'update';  // Mövcud qeydi yenilə (upsert)
    case ERROR  = 'error';   // Xəta kimi qeyd et
}

// ProductRepository — bulk yoxlama
class ProductRepository
{
    public function findSkusBulk(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        return Product::whereIn('sku', $skus)
            ->pluck('sku')
            ->toArray();
    }

    public function upsertBatch(array $rows): int
    {
        // MySQL INSERT ... ON DUPLICATE KEY UPDATE
        return Product::upsert(
            $rows,
            uniqueBy: ['sku'],         // Unique key
            update: ['name', 'price', 'stock', 'updated_at']  // Yenilənəcək sahələr
        );
    }
}
```

---

## 10. Batch Insert-lər — INSERT INTO ... VALUES Performansı

*10. Batch Insert-lər — INSERT INTO ... VALUES Performansı üçün kod nümunəsi:*
```php
// ProductRepository.php — batch insert
class ProductRepository
{
    /**
     * Tək-tək insert əvəzinə batch insert istifadə et.
     *
     * Tək-tək:  1000 sətir = 1000 SQL sorğusu
     * Batch:    1000 sətir = 1-2 SQL sorğusu
     */
    public function batchInsert(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $now = now();

        // Timestamps əlavə et
        $preparedRows = array_map(fn($row) => array_merge($row, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $rows);

        // Laravel batch insert — bir SQL sorğusu
        Product::insert($preparedRows);

        return count($rows);
    }

    /**
     * Çox böyük batch-ləri parçalayaraq insert et.
     * MySQL max_allowed_packet limitini aşmamaq üçün.
     */
    public function batchInsertChunked(array $rows, int $batchSize = 500): int
    {
        $inserted = 0;

        foreach (array_chunk($rows, $batchSize) as $batch) {
            $now = now();

            $preparedBatch = array_map(fn($row) => array_merge($row, [
                'created_at' => $now,
                'updated_at' => $now,
            ]), $batch);

            Product::insert($preparedBatch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    /**
     * DB::table() ilə daha sürətli insert (model events fire olmur)
     * Böyük idxallarda model event-lərə ehtiyac yoxsa bu yanaşma 3-5x sürətlidir.
     */
    public function rawBatchInsert(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        DB::table('products')->insert($rows);

        return count($rows);
    }
}
```

**Performans müqayisəsi** (10,000 sətir):
| Yanaşma | Vaxt | DB Sorğuları |
|---|---|---|
| Tək-tək `Model::create()` | ~45 saniyə | 10,000 |
| Batch `Model::insert()` | ~3 saniyə | 20 |
| Raw `DB::table()->insert()` | ~1.5 saniyə | 20 |

---

## 11. Queue Dizaynı: Fayl Başına Bir Job vs Chunk Başına Bir Job

*11. Queue Dizaynı: Fayl Başına Bir Job vs Chunk Başına Bir Job üçün kod nümunəsi:*
```php
// Strategiya A: Fayl başına bir Job (sadə, tövsiyə edilir)
class ProcessImportJob implements ShouldQueue
{
    public int $timeout = 3600; // 1 saat

    public function handle(): void
    {
        // Fayl chunk-larla emal edilir — tək job daxilinde
        foreach ($this->reader->chunks(500) as $chunk) {
            $this->processor->processChunk($chunk);
        }
    }
}

// Strategiya B: Chunk başına ayrı Job (paralel emal)
class DispatchChunkJobsJob implements ShouldQueue
{
    public function handle(CsvReader $reader): void
    {
        $import    = Import::findOrFail($this->importId);
        $chunkJobs = [];
        $chunkIndex = 0;

        foreach ($reader->chunks(500) as $chunk) {
            $chunkPath = $this->saveChunkToTemp($import->id, $chunkIndex, $chunk);

            // Hər chunk üçün ayrı job
            $chunkJobs[] = new ProcessChunkJob(
                importId:   $import->id,
                chunkPath:  $chunkPath,
                chunkIndex: $chunkIndex
            );

            $chunkIndex++;
        }

        // Bütün chunk job-larını göndər — paralel işlənəcək
        Bus::batch($chunkJobs)
            ->name("import_{$import->id}_chunks")
            ->then(function (Batch $batch) use ($import) {
                $import->markAsCompleted(
                    $batch->processedJobs() * 500,
                    $batch->failedJobs
                );
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($import) {
                $import->markAsFailed($e->getMessage());
            })
            ->dispatch();
    }
}
```

**Müqayisə**:
| | Fayl başına bir Job | Chunk başına ayrı Job |
|---|---|---|
| Mürəkkəblik | Sadə | Mürəkkəb |
| Paralel emal | Yox | Bəli (bir neçə worker) |
| Xəta idarəsi | Asandır | Hissə-hissə çətin |
| Tərəqqi izləmə | Asandır | Batch API lazımdır |
| Tövsiyə | 100k-ə qədər | 100k+ paralel tələb üçün |

---

## 12. Laravel Excel (Maatwebsite) — Chunk Oxuma

*12. Laravel Excel (Maatwebsite) — Chunk Oxuma üçün kod nümunəsi:*
```php
// Laravel Excel ilə chunk oxuma — daha az boilerplate
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ProductsImport implements
    ToModel,
    WithChunkReading,
    WithValidation,
    WithBatchInserts,
    SkipsOnFailure
{
    use SkipsFailures;

    private array $failures = [];

    // Hər dəfə 1000 sətir oxu
    public function chunkSize(): int
    {
        return 1000;
    }

    // Batch insert ölçüsü
    public function batchSize(): int
    {
        return 500;
    }

    public function model(array $row): ?Product
    {
        return new Product([
            'sku'         => strtoupper(trim($row['sku'])),
            'name'        => $row['name'],
            'price'       => $row['price'],
            'stock'       => (int) $row['stock'],
            'category_id' => $row['category_id'],
        ]);
    }

    public function rules(): array
    {
        return [
            'sku'         => ['required', 'string', 'max:100'],
            'name'        => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'sku.required'       => 'SKU mütləqdir.',
            'category_id.exists' => 'Kateqoriya tapılmadı.',
        ];
    }
}

// Controller-da istifadə
class ImportController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $import = new ProductsImport();

        Excel::import($import, $request->file('file'));

        $failures = $import->failures();

        return response()->json([
            'success'       => true,
            'failure_count' => count($failures),
            'failures'      => collect($failures)->map(fn($f) => [
                'row'    => $f->row(),
                'errors' => $f->errors(),
            ])->toArray(),
        ]);
    }

    // Queue ilə asinxron emal
    public function importAsync(Request $request): JsonResponse
    {
        $path = $request->file('file')->store('imports');

        Excel::queueImport(new ProductsImport(), $path)
             ->onQueue('imports');

        return response()->json(['status' => 'queued'], 202);
    }
}
```

---

## 13. Real Ssenari: 50k Sətirlik Məhsul Kataloqu İdxalı

Bu tam end-to-end ssenaridə 50,000 məhsul idxal edilir. Faylda 2,000 etibarsız sətir var.

*Bu tam end-to-end ssenaridə 50,000 məhsul idxal edilir. Faylda 2,000 e üçün kod nümunəsi:*
```php
// migrations/create_imports_table.php
Schema::create('imports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('file_path');
    $table->string('original_filename');
    $table->string('status', 50)->default('pending');  // pending|processing|completed|failed
    $table->unsignedInteger('total_rows')->default(0);
    $table->unsignedInteger('processed_rows')->default(0);
    $table->unsignedInteger('failed_rows')->default(0);
    $table->string('import_strategy', 50)->default('skip_invalid');
    $table->string('error_report_path')->nullable();
    $table->text('error_reason')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'status']);
    $table->index('created_at');
});
```

*$table->index('created_at'); üçün kod nümunəsi:*
```php
// Tam ProductImportService
class ProductImportService
{
    public function __construct(
        private readonly CsvReader $csvReader,
        private readonly ProductRowValidator $validator,
        private readonly DuplicateDetector $duplicateDetector,
        private readonly ProductRepository $repository,
        private readonly ImportProgressTracker $tracker,
        private readonly ErrorReportGenerator $errorReportGenerator
    ) {}

    public function process(Import $import): void
    {
        $filePath  = storage_path("app/{$import->file_path}");
        $errorLog  = [];
        $totalInserted = 0;
        $totalFailed   = 0;

        foreach ($this->csvReader->chunks(500) as $chunkRows) {
            // Chunk-dakı SKU-ları DB-dən əvvəlcədən yüklə (N+1 qarşısını al)
            $skusInChunk = array_column($chunkRows, 'sku');
            $this->duplicateDetector->preloadExistingSkus($skusInChunk);

            $validRows   = [];
            $chunkErrors = [];

            foreach ($chunkRows as $lineNumber => $row) {
                // 1. Validation
                $validationResult = $this->validator->validate($row, $lineNumber);
                if (!$validationResult->isValid) {
                    $chunkErrors[$lineNumber] = $validationResult->errors;
                    $totalFailed++;
                    continue;
                }

                // 2. Dublikat yoxla
                $dupResult = $this->duplicateDetector->isDuplicate($row['sku']);
                if ($dupResult->isDuplicate) {
                    $chunkErrors[$lineNumber] = [
                        'sku' => ["Dublikat SKU: {$row['sku']} ({$dupResult->type})"]
                    ];
                    $totalFailed++;
                    continue;
                }

                $validRows[] = $this->prepareRow($row);
            }

            // 3. Batch insert — transaction ilə
            if (!empty($validRows)) {
                DB::transaction(function () use ($validRows, &$totalInserted) {
                    $this->repository->batchInsertChunked($validRows, 250);
                    $totalInserted += count($validRows);
                });
            }

            // 4. Xətaları topla
            $errorLog = array_merge($errorLog, $chunkErrors);

            // 5. Tərəqqi yenilə
            $this->tracker->update(
                $import,
                $totalInserted + $totalFailed,
                $totalFailed
            );
        }

        // 6. Xəta hesabatı yarat
        if (!empty($errorLog)) {
            $reportPath = $this->errorReportGenerator->generate($import, $errorLog);
            $import->update(['error_report_path' => $reportPath]);
        }

        // 7. Final yeniləmə
        $import->markAsCompleted($totalInserted + $totalFailed, $totalFailed);
    }

    private function prepareRow(array $row): array
    {
        return [
            'sku'         => strtoupper(trim($row['sku'])),
            'name'        => trim($row['name']),
            'price'       => (float) $row['price'],
            'stock'       => (int) $row['stock'],
            'category_id' => (int) $row['category_id'],
            'description' => isset($row['description']) ? trim($row['description']) : null,
        ];
    }
}
```

*'description' => isset($row['description']) ? trim($row['description'] üçün kod nümunəsi:*
```php
// Queue worker konfiqurasiyası
// config/queue.php
'connections' => [
    'redis' => [
        'driver'     => 'redis',
        'queue'      => 'default',
        'retry_after'=> 3660,  // 1 saat + 1 dəqiqə (timeout-dan böyük olmalı)
        'block_for'  => null,
    ],
],

// Supervisor konfiqurasiyası
// /etc/supervisor/conf.d/laravel-imports.conf
// [program:laravel-import-worker]
// command=php /var/www/artisan queue:work redis --queue=imports --timeout=3600 --memory=512
// numprocs=2
// autostart=true
// autorestart=true
```

**Nəticə — 50,000 sətir idxalı**:
- Toplam vaxt: ~4 dəqiqə (2 worker ilə)
- Yaddaş istifadəsi: ~64 MB (sabit — chunk sayından asılı deyil)
- DB sorğuları: ~100 batch insert + ~100 tərəqqi yeniləmə
- 48,000 sətir uğurla idxal edildi
- 2,000 sətir xəta hesabatına yazıldı

---

## Əsas Çıxarışlar

1. **Heç vaxt böyük faylı bir dəfəyə yaddaşa yükləmə** — `SplFileObject` + generator-larla sətir-sətir oxu. Yaddaş istifadəsi fayl ölçüsündən asılı olmur.

2. **HTTP sorğusunda idxal etmə** — hər zaman queue istifadə et. Upload → saxla → job göndər → 202 qaytar.

3. **Chunk başına transaction** — 100k+ sətir üçün tək böyük transaction deadlock, uzun lock müddəti yaradır. Chunk başına kiçik transaction-lar daha etibarlıdır.

4. **Batch insert** tək-tək `INSERT`-dən 10-30x sürətlidir. `DB::table()->insert()` model event-ləri fire etmir — idxal üçün tövsiyə edilir.

5. **Validation xətalarını topla, dayandırma** — `skip_invalid` strategiyası real dünyada daha çox istifadə olunur. İstifadəçi xəta CSV-ni endirərək düzəldə bilər.

6. **Tərəqqi izləmə üçün cache istifadə et** — hər sətir üçün DB-ni yeniləmə. Cache-ə yaz, hər N sətirdən bir DB-ni sinxronlaşdır.

7. **Dublikat aşkarlaması iki səviyyədə** — həm fayl daxilindəki, həm DB-dəki dublikatları yoxla. Chunk başına `whereIn` ilə bulk yoxlama N+1 problemini aradan qaldırır.

8. **Laravel Excel (Maatwebsite)** `WithChunkReading`, `WithBatchInserts`, `SkipsOnFailure` kombinasiyası çox kod yazmadan eyni funksionallığı təmin edir.

9. **Queue timeout > job timeout** — supervisor/Redis `retry_after` dəyəri job `timeout`-undan böyük olmalıdır, əks halda job işləyərkən yenidən queue-ya qayıdır.

10. **Xəta hesabatını axın ilə endirt** — böyük xəta hesabatlarını `StreamedResponse` ilə göndər, bütün faylı yaddaşa yükləmə.

---

## Anti-patternlər

**1. Böyük faylı bir dəfəyə yaddaşa yükləmək**
`file_get_contents()` və ya `Storage::get()` ilə 100MB+ CSV-ni tam olaraq PHP yaddaşına çəkmək — `out of memory` xətası verir, import crash olur. `SplFileObject` və ya generator-larla sətir-sətir oxu, yaddaş istifadəsi fayl ölçüsündən asılı olmasın.

**2. Import-u sinxron HTTP request-inin içinde icra etmək**
Faylı upload etdikdən dərhal sonra 100K sətiri controller-da emal etmək — PHP-FPM timeout verir, istifadəçi boş ekrana baxır, yarımçıq import geri alınmır. Upload → saxla → queue job → `202 Accepted` axını tətbiq et.

**3. Bütün sətirləri tək böyük transaction-da işləmək**
100K sətirlik import üçün tək `DB::beginTransaction()` açmaq — uzun lock müddəti, deadlock riski, yarımçıq uğursuzluqda hamısı rollback olur. Hər chunk üçün ayrı kiçik transaction aç, uğursuz chunk-ları ayrıca qeyd et.

**4. Tək-tək `INSERT` etmək**
Hər sətir üçün ayrıca `INSERT INTO ...` çalışdırmaq — 10K sətir üçün 10K ayrı DB sorğusu, çox yavaş. `DB::table()->insert($chunk)` ilə batch insert istifadə et, sürəti 10-30x artır.

**5. Validation xətasında importu tam dayandırmaq**
İlk xətalı sətirdə bütün prosesi kəsmək — 50K sətirdən 1-i yanlış olduqda hamısı işlənməmiş qalır, istifadəçi yenidən başlamalı olur. `skip_invalid` strategiyasını tətbiq et, xətalı sətirləri topla, ayrıca xəta CSV-si kimi istifadəçiyə ver.

**6. Dublikat yoxlamasını sətir-sətir etmək**
Hər sətir üçün `SELECT COUNT(*) WHERE email = ?` ilə DB-yə sorğu atmaq — N+1 problemi, 10K sətir üçün 10K ayrı sorğu. Chunk-ı `whereIn` ilə bir sorğuda yoxla, bütün mövcud qeydləri bir dəfəyə al.

**7. Queue worker timeout-unu job timeout-undan kiçik etmək**
Supervisor/Redis `retry_after`-i job `$timeout`-undan kiçik təyin etmək — job hələ işləyərkən queue yenidən job-u işlənməmiş hesab edir, ikinci worker eyni faylı emal etməyə başlayır, dublikat insert baş verə bilər. `retry_after` həmişə job `timeout`-undan ən azı 60 saniyə böyük olmalıdır.

**8. Import job-una `$tries > 1` vermək**
Import job-una avtomatik retry icazəsi vermək — import idempotent deyil. Job yarımçıq tamamlanıb crash olduqda retry eyni sətirləri ikinci dəfə insert edər. Import job-unda `$tries = 1`, `$maxExceptions = 1` qur, xəta olduqda `markAsFailed()` çağır, manual retry imkanı istifadəçiyə ver.
