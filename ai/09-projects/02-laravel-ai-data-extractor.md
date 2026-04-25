# Claude Vision ilə Sənəd Məlumatlarının Çıxarılması Sistemi (Middle)

Claude-un vision (görüntü tanıma) imkanlarından istifadə edərək fakturalar, CV-lər, şəxsiyyət sənədləri və formalardan strukturlaşdırılmış məlumatları çıxarın. Əminlik (confidence) hesablaması, manual nəzərdən keçirmə növbəsi və webhook callback-ləri daxildir.

---

## Arxitektura İcmalı

```
Yükləmə endpoint-i (şəkil / PDF)
        │
        ▼
DocumentUploadController
  ├── Faylı S3/yerli yaddaşda saxla
  ├── Sənəd növünü müəyyən et (invoice/cv/id)
  └── ExtractDocumentDataJob-u göndər
              │  növbəyə alındı
              ▼
    ExtractDocumentDataJob
      ├── PDF-lər üçün: səhifələri şəkillərə çevir (Imagick/Ghostscript)
      ├── Çox səhifəli sənədlər üçün: hər səhifəni ayrıca işlə
      ├── Claude-a vision + çıxarma promptu ilə göndər
      ├── Strukturlaşdırılmış nəticəni parse et + yoxla
      ├── Əminlik hesablarını hesabla
      ├── Tipli çıxarma cədvəlində saxla
      └── Aşağı əminlik varsa → manual nəzərdən keçirməyə göndər
              │
              ▼
    Webhook callback (ixtiyari)
    Manual nəzərdən keçirmə növbəsi UI
```

---

## Verilənlər Bazası Miqrasiyaları

```php
// database/migrations/2024_01_01_create_extraction_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Əsas sənəd qeydləri
        Schema::create('extracted_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedSmallInteger('page_count')->default(1);
            $table->string('document_type'); // invoice, cv, id_document, receipt, form
            $table->string('status')->default('pending'); // pending, processing, completed, review, failed
            $table->string('extraction_model')->default('claude-opus-4-5');
            $table->float('overall_confidence')->nullable(); // 0.0 - 1.0
            $table->boolean('needs_review')->default(false);
            $table->string('review_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('webhook_url')->nullable(); // Tamamlandıqda bildiriş göndər
            $table->string('webhook_secret')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type', 'status']);
            $table->index(['status', 'needs_review']);
        });

        // Faktura çıxarmaları
        Schema::create('extracted_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('extracted_documents')->cascadeOnDelete();

            // Satıcı məlumatları
            $table->string('vendor_name')->nullable();
            $table->string('vendor_address')->nullable();
            $table->string('vendor_tax_id')->nullable();
            $table->string('vendor_phone')->nullable();
            $table->string('vendor_email')->nullable();

            // Faktura detalları
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->json('line_items')->nullable(); // {description, qty, unit_price, total} massivi

            // Ödəyicinin məlumatları
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_address')->nullable();

            // Sahə üzrə əminlik (0-100)
            $table->json('confidence_scores')->nullable();
            $table->timestamps();
        });

        // CV/Resume çıxarmaları
        Schema::create('extracted_cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('extracted_documents')->cascadeOnDelete();

            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('website')->nullable();
            $table->text('summary')->nullable();

            $table->json('skills')->nullable();           // ["PHP", "Laravel", "PostgreSQL"]
            $table->json('experience')->nullable();       // [{company, role, start, end, description}]
            $table->json('education')->nullable();        // [{institution, degree, field, year}]
            $table->json('certifications')->nullable();   // [{name, issuer, date}]
            $table->json('languages')->nullable();        // [{language, proficiency}]
            $table->json('confidence_scores')->nullable();
            $table->timestamps();
        });

        // Şəxsiyyət sənədi çıxarmaları (pasport, sürücülük vəsiqəsi, şəxsiyyət vəsiqəsi)
        Schema::create('extracted_id_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('extracted_documents')->cascadeOnDelete();

            $table->string('document_subtype')->nullable(); // passport, driving_license, national_id
            $table->string('issuing_country')->nullable();
            $table->string('document_number')->nullable();
            $table->string('full_name')->nullable();
            $table->string('given_names')->nullable();
            $table->string('surname')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('mrz_line1')->nullable(); // Maşınla Oxunan Zona (MRZ)
            $table->string('mrz_line2')->nullable();
            $table->json('confidence_scores')->nullable();
            $table->timestamps();
        });
    }
};
```

---

## Modellər

```php
// app/Models/ExtractedDocument.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExtractedDocument extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'file_path', 'original_filename', 'mime_type',
        'file_size', 'page_count', 'document_type', 'status', 'extraction_model',
        'overall_confidence', 'needs_review', 'review_reason', 'reviewed_by',
        'reviewed_at', 'webhook_url', 'webhook_secret', 'error_message',
        'input_tokens', 'output_tokens',
    ];

    protected $casts = [
        'needs_review' => 'boolean',
        'reviewed_at' => 'datetime',
        'overall_confidence' => 'float',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->ulid ??= Str::ulid());
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ExtractedInvoice::class, 'document_id');
    }

    public function cv(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ExtractedCv::class, 'document_id');
    }

    public function idDocument(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ExtractedIdDocument::class, 'document_id');
    }

    public function extraction(): Model|null
    {
        return match($this->document_type) {
            'invoice' => $this->invoice,
            'cv' => $this->cv,
            'id_document' => $this->idDocument,
            default => null,
        };
    }

    public function isLowConfidence(): bool
    {
        return $this->overall_confidence !== null && $this->overall_confidence < 0.70;
    }

    public function getRouteKeyName(): string { return 'ulid'; }
}
```

```php
// app/Models/ExtractedInvoice.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractedInvoice extends Model
{
    protected $fillable = [
        'document_id', 'vendor_name', 'vendor_address', 'vendor_tax_id',
        'vendor_phone', 'vendor_email', 'invoice_number', 'invoice_date',
        'due_date', 'currency', 'subtotal', 'tax_amount', 'discount_amount',
        'total_amount', 'payment_terms', 'line_items', 'bill_to_name',
        'bill_to_address', 'confidence_scores',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'line_items' => 'array',
        'confidence_scores' => 'array',
    ];

    public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExtractedDocument::class, 'document_id');
    }
}
```

---

## Çıxarma Promptları

```php
// app/Services/Extraction/ExtractionPrompts.php
<?php

namespace App\Services\Extraction;

class ExtractionPrompts
{
    public static function invoice(): string
    {
        return <<<'PROMPT'
        Extract all data from this invoice document. Return a JSON object with this exact structure:

        {
          "vendor_name": "string or null",
          "vendor_address": "full address as string or null",
          "vendor_tax_id": "VAT/EIN/tax number or null",
          "vendor_phone": "string or null",
          "vendor_email": "string or null",
          "invoice_number": "string or null",
          "invoice_date": "YYYY-MM-DD format or null",
          "due_date": "YYYY-MM-DD format or null",
          "currency": "ISO 4217 code like USD, EUR or null",
          "subtotal": number or null,
          "tax_amount": number or null,
          "discount_amount": number or null,
          "total_amount": number or null,
          "payment_terms": "string or null",
          "bill_to_name": "string or null",
          "bill_to_address": "string or null",
          "line_items": [
            {
              "description": "string",
              "quantity": number or null,
              "unit": "string or null",
              "unit_price": number or null,
              "total": number or null
            }
          ],
          "confidence": {
            "vendor_name": 0-100,
            "invoice_number": 0-100,
            "invoice_date": 0-100,
            "total_amount": 0-100,
            "line_items": 0-100
          }
        }

        Rules:
        - All monetary values should be numbers (not strings), without currency symbols
        - Dates must be YYYY-MM-DD format
        - If a field is not present in the document, use null
        - confidence scores reflect how certain you are about each field:
          100 = clearly visible, unambiguous
          70-99 = visible but could be misread
          50-69 = partially visible or unclear
          0-49 = guessed or very unclear
        - Return ONLY the JSON object, no explanation
        PROMPT;
    }

    public static function cv(): string
    {
        return <<<'PROMPT'
        Extract all information from this CV/resume. Return a JSON object:

        {
          "full_name": "string or null",
          "email": "string or null",
          "phone": "string or null",
          "location": "city, country or null",
          "linkedin_url": "string or null",
          "github_url": "string or null",
          "website": "string or null",
          "summary": "professional summary paragraph or null",
          "skills": ["skill1", "skill2"],
          "experience": [
            {
              "company": "string",
              "role": "job title",
              "start_date": "YYYY-MM or YYYY",
              "end_date": "YYYY-MM or YYYY or 'Present'",
              "description": "responsibilities and achievements",
              "location": "string or null"
            }
          ],
          "education": [
            {
              "institution": "string",
              "degree": "BSc, MSc, PhD, etc.",
              "field": "Computer Science, etc.",
              "graduation_year": "YYYY or null",
              "grade": "GPA or classification or null"
            }
          ],
          "certifications": [
            {
              "name": "certification name",
              "issuer": "issuing organization",
              "date": "YYYY-MM or YYYY or null"
            }
          ],
          "languages": [
            {
              "language": "English",
              "proficiency": "Native|Fluent|Professional|Conversational|Basic"
            }
          ],
          "confidence": {
            "full_name": 0-100,
            "email": 0-100,
            "experience": 0-100,
            "skills": 0-100
          }
        }

        - experience should be ordered most recent first
        - skills should be a flat array of individual skill strings
        - Return ONLY the JSON object
        PROMPT;
    }

    public static function idDocument(): string
    {
        return <<<'PROMPT'
        Extract information from this identity document (passport, driving license, or national ID card).
        Return a JSON object:

        {
          "document_subtype": "passport|driving_license|national_id|other",
          "issuing_country": "ISO 3166-1 alpha-2 country code (e.g., US, GB, DE) or null",
          "document_number": "string or null",
          "full_name": "complete name as printed or null",
          "given_names": "first and middle names or null",
          "surname": "family name or null",
          "date_of_birth": "YYYY-MM-DD or null",
          "gender": "M|F|X or null",
          "nationality": "ISO country code or null",
          "issue_date": "YYYY-MM-DD or null",
          "expiry_date": "YYYY-MM-DD or null",
          "place_of_birth": "string or null",
          "mrz_line1": "first MRZ line exactly as printed or null",
          "mrz_line2": "second MRZ line exactly as printed or null",
          "confidence": {
            "document_number": 0-100,
            "full_name": 0-100,
            "date_of_birth": 0-100,
            "expiry_date": 0-100
          }
        }

        CRITICAL: For identity documents, accuracy is paramount.
        - If you cannot read a field clearly, set confidence below 50
        - Transcribe the MRZ exactly as printed, character by character
        - Do NOT guess or infer — return null for unclear fields
        - Return ONLY the JSON object
        PROMPT;
    }
}
```

---

## Çıxarma Xidməti

```php
// app/Services/Extraction/DocumentExtractionService.php
<?php

namespace App\Services\Extraction;

use App\Models\ExtractedDocument;
use App\Models\ExtractedInvoice;
use App\Models\ExtractedCv;
use App\Models\ExtractedIdDocument;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DocumentExtractionService
{
    // Model seçimi: sənədlər üçün dəqiqlik baxımından claude-opus-4-5 istifadə edin
    // claude-haiku-4-5 10x ucuzdur, lakin mürəkkəb sənədlərdə daha az dəqiqdir
    private string $model = 'claude-opus-4-5';

    // Çıxarmanın etibarlı hesab edilməsi üçün mütləq olması lazım olan sahələr
    private array $requiredFields = [
        'invoice' => ['vendor_name', 'total_amount', 'invoice_date'],
        'cv' => ['full_name', 'email'],
        'id_document' => ['full_name', 'document_number'],
    ];

    // Manual nəzərdən keçirməni tətikləmək üçün minimum əminlik həddi
    private float $reviewThreshold = 0.70;

    public function extract(ExtractedDocument $document): void
    {
        $document->update(['status' => 'processing']);

        try {
            // 1. Sənəddən şəkilləri hazırla
            $images = $this->prepareImages($document);

            if (empty($images)) {
                throw new \RuntimeException('Could not extract images from document');
            }

            // 2. API sorğusunu qur
            $prompt = $this->getPrompt($document->document_type);
            $content = $this->buildContent($images, $prompt);

            // 3. Claude-u çağır
            $response = Anthropic::messages()->create([
                'model' => $this->model,
                'max_tokens' => 4096,
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);

            $rawText = $response->content[0]->text ?? '';
            $inputTokens = $response->usage->inputTokens;
            $outputTokens = $response->usage->outputTokens;

            // 4. JSON cavabını parse et
            $extracted = $this->parseJson($rawText);

            if ($extracted === null) {
                throw new \RuntimeException('Claude returned invalid JSON: ' . substr($rawText, 0, 200));
            }

            // 5. Əminliyi hesabla və yoxla
            $confidence = $this->computeOverallConfidence($extracted, $document->document_type);
            $missingRequired = $this->getMissingRequiredFields($extracted, $document->document_type);

            // 6. Strukturlaşdırılmış məlumatları saxla
            $this->storeExtraction($document, $extracted);

            // 7. Manual nəzərdən keçirməyə ehtiyac olub-olmadığını müəyyən et
            $needsReview = false;
            $reviewReason = null;

            if ($confidence < $this->reviewThreshold) {
                $needsReview = true;
                $reviewReason = sprintf('Aşağı əminlik: %.0f%%', $confidence * 100);
            } elseif (!empty($missingRequired)) {
                $needsReview = true;
                $reviewReason = 'Tələb olunan sahələr yoxdur: ' . implode(', ', $missingRequired);
            }

            $document->update([
                'status' => $needsReview ? 'review' : 'completed',
                'overall_confidence' => $confidence,
                'needs_review' => $needsReview,
                'review_reason' => $reviewReason,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]);

            // 8. Webhook konfiqurasiya edilibsə işə sal
            if ($document->webhook_url) {
                $this->fireWebhook($document);
            }

        } catch (\Exception $e) {
            Log::error("Extraction failed for document {$document->id}", ['error' => $e->getMessage()]);
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sənədi Claude-un vision girişi üçün şəkil(lər)ə çevir.
     * Base64 kodlanmış şəkillər massivini qaytarır.
     */
    private function prepareImages(ExtractedDocument $document): array
    {
        $path = Storage::path($document->file_path);
        $mime = $document->mime_type;

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            // Birbaşa şəkil — sadəcə kodla
            return [
                [
                    'data' => base64_encode(file_get_contents($path)),
                    'media_type' => $mime,
                ]
            ];
        }

        if ($mime === 'application/pdf') {
            return $this->convertPdfToImages($path, $document->page_count);
        }

        throw new \InvalidArgumentException("Unsupported file type: {$mime}");
    }

    /**
     * PDF səhifələrini Ghostscript və ya Imagick vasitəsilə şəkillərə çevir.
     * Base64 kodlanmış JPEG şəkillər massivini qaytarır (hər səhifə üçün bir, maksimum 10 səhifə).
     */
    private function convertPdfToImages(string $pdfPath, int $pageCount): array
    {
        $maxPages = min($pageCount, 10); // Claude-un şəkil sayına limiti var
        $images = [];

        // Əvvəlcə Imagick-i sınayın (ən çox yayılmış), Ghostscript CLI-a keçin
        if (extension_loaded('imagick')) {
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150); // OCR üçün 150 DPI yaxşıdır
            $imagick->readImage($pdfPath);

            for ($page = 0; $page < $maxPages; $page++) {
                $imagick->setIteratorIndex($page);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);

                // Çox böyükdürsə ölçüsünü azalt (Claude-un hər şəkil üçün maksimum ölçüsü ~5MB-dır)
                $imagick->scaleImage(2000, 0, true); // Maksimum 2000px en, nisbəti qoru

                $images[] = [
                    'data' => base64_encode($imagick->getImageBlob()),
                    'media_type' => 'image/jpeg',
                ];
            }

            $imagick->destroy();
        } else {
            // Alternativ: CLI vasitəsilə Ghostscript
            for ($page = 1; $page <= $maxPages; $page++) {
                $outputPath = sys_get_temp_dir() . '/extraction_page_' . $page . '_' . uniqid() . '.jpg';
                $cmd = sprintf(
                    'gs -dNOPAUSE -sDEVICE=jpeg -r150 -dFirstPage=%d -dLastPage=%d -sOutputFile=%s -dBATCH %s 2>/dev/null',
                    $page, $page,
                    escapeshellarg($outputPath),
                    escapeshellarg($pdfPath)
                );
                exec($cmd);

                if (file_exists($outputPath)) {
                    $images[] = [
                        'data' => base64_encode(file_get_contents($outputPath)),
                        'media_type' => 'image/jpeg',
                    ];
                    unlink($outputPath);
                }
            }
        }

        return $images;
    }

    /**
     * Şəkillər və prompt ilə Claude API content massivini qur.
     * Çox səhifəli sənədlər üçün bütün səhifələri bir sorğuda göndər.
     */
    private function buildContent(array $images, string $textPrompt): array
    {
        $content = [];

        foreach ($images as $i => $image) {
            if (count($images) > 1) {
                $content[] = [
                    'type' => 'text',
                    'text' => 'Page ' . ($i + 1) . ':',
                ];
            }

            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image['media_type'],
                    'data' => $image['data'],
                ],
            ];
        }

        $content[] = [
            'type' => 'text',
            'text' => $textPrompt,
        ];

        return $content;
    }

    private function getPrompt(string $documentType): string
    {
        return match($documentType) {
            'invoice' => ExtractionPrompts::invoice(),
            'cv' => ExtractionPrompts::cv(),
            'id_document' => ExtractionPrompts::idDocument(),
            default => throw new \InvalidArgumentException("Unknown document type: {$documentType}"),
        };
    }

    private function parseJson(string $text): ?array
    {
        // Claude saf JSON qaytarmalıdır, lakin bəzən markdown kod bloklarına bürüyür
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        $text = trim($text);

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Mətndə JSON obyektini tapmağa çalış
            if (preg_match('/\{.*\}/s', $text, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    private function computeOverallConfidence(array $extracted, string $type): float
    {
        $confidenceScores = $extracted['confidence'] ?? [];

        if (empty($confidenceScores)) {
            // Əminlik hesabları verilməyib — mühafizəkar standart dəyər istifadə et
            return 0.60;
        }

        // Əsas sahələrə daha çox çəki ver
        $weights = match($type) {
            'invoice' => ['total_amount' => 3, 'invoice_number' => 2, 'vendor_name' => 2, 'invoice_date' => 2],
            'cv' => ['full_name' => 3, 'email' => 3, 'experience' => 2],
            'id_document' => ['full_name' => 3, 'document_number' => 3, 'date_of_birth' => 2, 'expiry_date' => 2],
            default => [],
        };

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($confidenceScores as $field => $score) {
            $weight = $weights[$field] ?? 1;
            $weightedSum += ($score / 100) * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0.60;
    }

    private function getMissingRequiredFields(array $extracted, string $type): array
    {
        $required = $this->requiredFields[$type] ?? [];
        return array_filter($required, fn($field) => empty($extracted[$field]));
    }

    private function storeExtraction(ExtractedDocument $document, array $data): void
    {
        // Saxlamadan əvvəl confidence açarını sil (onu ayrıca idarə edirik)
        unset($data['confidence']);

        match($document->document_type) {
            'invoice' => ExtractedInvoice::updateOrCreate(
                ['document_id' => $document->id],
                array_merge($data, ['confidence_scores' => $data['confidence'] ?? null]),
            ),
            'cv' => ExtractedCv::updateOrCreate(
                ['document_id' => $document->id],
                $data,
            ),
            'id_document' => ExtractedIdDocument::updateOrCreate(
                ['document_id' => $document->id],
                $data,
            ),
        };
    }

    /**
     * Çıxarma tamamlandıqda webhook callback-i işə sal.
     * Qəbul edən tərəfin yoxlaması üçün HMAC-SHA256 ilə imzalanır.
     */
    private function fireWebhook(ExtractedDocument $document): void
    {
        $payload = [
            'event' => 'extraction.completed',
            'document_id' => $document->ulid,
            'document_type' => $document->document_type,
            'status' => $document->status,
            'confidence' => $document->overall_confidence,
            'needs_review' => $document->needs_review,
            'timestamp' => now()->toIso8601String(),
        ];

        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, $document->webhook_secret ?? '');

        try {
            \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature-256' => 'sha256=' . $signature,
                'X-Document-ID' => $document->ulid,
            ])
            ->timeout(10)
            ->post($document->webhook_url, $payload);
        } catch (\Exception $e) {
            Log::warning("Webhook delivery failed for document {$document->id}", [
                'url' => $document->webhook_url,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

---

## Job (Növbə Tapşırığı)

```php
// app/Jobs/ExtractDocumentData.php
<?php

namespace App\Jobs;

use App\Models\ExtractedDocument;
use App\Services\Extraction\DocumentExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractDocumentData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(private readonly int $documentId) {}

    public function handle(DocumentExtractionService $service): void
    {
        $document = ExtractedDocument::find($this->documentId);
        if (!$document) return;

        $service->extract($document);
    }

    public function failed(\Throwable $exception): void
    {
        $document = ExtractedDocument::find($this->documentId);
        $document?->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
```

---

## Controller (İdarəedici)

```php
// app/Http/Controllers/ExtractionController.php
<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractDocumentData;
use App\Models\ExtractedDocument;
use Illuminate\Http\Request;

class ExtractionController extends Controller
{
    /**
     * Çıxarma üçün sənəd yüklə.
     *
     * POST /extract
     * multipart/form-data:
     *   - file: sənəd
     *   - type: invoice|cv|id_document
     *   - webhook_url: (ixtiyari) tamamlandıqda bildiriş göndəriləcək URL
     *   - webhook_secret: (ixtiyari) HMAC sirri
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp'],
            'type' => ['required', 'in:invoice,cv,id_document'],
            'webhook_url' => ['nullable', 'url'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('extractions', 'private');

        // Mümkünsə PDF səhifə sayını hesabla
        $pageCount = 1;
        if ($file->getMimeType() === 'application/pdf') {
            try {
                $imagick = new \Imagick($file->path());
                $pageCount = $imagick->getNumberImages();
            } catch (\Exception $e) {
                // Səhifə sayını tapa bilmiriksə, 1 qəbul et
            }
        }

        $document = ExtractedDocument::create([
            'user_id' => auth()->id(),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'page_count' => min($pageCount, 10), // Maksimum 10 səhifəylə məhdudlaşdır
            'document_type' => $request->input('type'),
            'extraction_model' => config('extraction.model', 'claude-opus-4-5'),
            'webhook_url' => $request->input('webhook_url'),
            'webhook_secret' => $request->input('webhook_secret'),
        ]);

        ExtractDocumentData::dispatch($document->id)
            ->onQueue('extractions');

        return response()->json([
            'document_id' => $document->ulid,
            'status' => 'pending',
            'message' => 'Sənəd çıxarma üçün növbəyə alındı',
            'poll_url' => route('extraction.status', $document->ulid),
        ], 202);
    }

    /**
     * Çıxarmanın status və nəticələrini al.
     */
    public function status(string $ulid)
    {
        $document = ExtractedDocument::where('ulid', $ulid)->firstOrFail();

        // İcazə yoxlaması
        if ($document->user_id && $document->user_id !== auth()->id()) {
            abort(403);
        }

        $response = [
            'document_id' => $document->ulid,
            'status' => $document->status,
            'document_type' => $document->document_type,
            'confidence' => $document->overall_confidence,
            'needs_review' => $document->needs_review,
            'review_reason' => $document->review_reason,
        ];

        if ($document->status === 'completed' || $document->status === 'review') {
            $extraction = $document->extraction();
            if ($extraction) {
                $response['data'] = $extraction->toArray();
            }
        }

        if ($document->status === 'failed') {
            $response['error'] = $document->error_message;
        }

        return response()->json($response);
    }

    /**
     * Manual nəzərdən keçirmə endpoint-i: çıxarma məlumatlarını düzəlt.
     */
    public function review(Request $request, string $ulid)
    {
        $document = ExtractedDocument::where('ulid', $ulid)->firstOrFail();
        $this->authorize('review', $document);

        $request->validate([
            'corrections' => ['required', 'array'],
        ]);

        // Çıxarmanı düzəlişlərlə yenilə
        $extraction = $document->extraction();
        if ($extraction) {
            $extraction->update($request->input('corrections'));
        }

        $document->update([
            'status' => 'completed',
            'needs_review' => false,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Nəzərdən keçirmə uğurla saxlanıldı']);
    }

    /**
     * Manual nəzərdən keçirmə növbəsini qaytarır (admin UI üçün).
     */
    public function reviewQueue(Request $request)
    {
        $documents = ExtractedDocument::where('needs_review', true)
            ->where('status', 'review')
            ->with(['invoice', 'cv', 'idDocument'])
            ->orderBy('created_at')
            ->paginate(20);

        return view('extractions.review-queue', compact('documents'));
    }
}
```

---

## Manual Nəzərdən Keçirmə UI (Blade)

```blade
{{-- resources/views/extractions/review-queue.blade.php --}}
<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Manual Nəzərdən Keçirmə Növbəsi</h1>
            <span class="bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full font-medium">
                {{ $documents->total() }} gözləyir
            </span>
        </div>

        @forelse($documents as $document)
            <div class="bg-white border rounded-xl p-6 mb-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-3 mb-1">
                            <span class="text-sm font-medium text-gray-500 uppercase tracking-wide">
                                {{ $document->document_type }}
                            </span>
                            <span class="bg-yellow-50 text-yellow-700 text-xs px-2 py-0.5 rounded-full border border-yellow-200">
                                {{ round($document->overall_confidence * 100) }}% əminlik
                            </span>
                        </div>
                        <h3 class="font-medium text-gray-900">{{ $document->original_filename }}</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $document->review_reason }}</p>
                    </div>
                    <div class="text-right text-sm text-gray-400">
                        {{ $document->created_at->diffForHumans() }}
                    </div>
                </div>

                {{-- Nəzərdən keçirmə üçün çıxarılmış məlumatlar --}}
                @if($document->document_type === 'invoice' && $document->invoice)
                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        @foreach(['vendor_name', 'invoice_number', 'invoice_date', 'total_amount'] as $field)
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ str_replace('_', ' ', $field) }}</label>
                                @php $confidence = $document->invoice->confidence_scores[$field] ?? null; @endphp
                                <input
                                    type="text"
                                    value="{{ $document->invoice->$field }}"
                                    class="w-full border rounded px-3 py-1.5 text-sm
                                        {{ $confidence && $confidence < 70 ? 'border-yellow-400 bg-yellow-50' : 'border-gray-200' }}"
                                    data-field="{{ $field }}"
                                    data-document="{{ $document->ulid }}"
                                />
                                @if($confidence !== null)
                                    <span class="text-xs text-gray-400">{{ $confidence }}% əminlik</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 flex gap-3">
                    <button
                        onclick="approveDocument('{{ $document->ulid }}')"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700"
                    >
                        Təsdiq et və Tamamla
                    </button>
                    <a href="{{ Storage::url($document->file_path) }}" target="_blank"
                       class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200">
                        Sənədə Bax
                    </a>
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-gray-400">
                <p class="text-xl mb-2">✅</p>
                <p>Nəzərdən keçirilməsi lazım olan sənəd yoxdur</p>
            </div>
        @endforelse

        {{ $documents->links() }}
    </div>

    <script>
    async function approveDocument(documentId) {
        const corrections = {};

        // Daxiletmə sahələrindəki düzəlişləri topla
        document.querySelectorAll(`[data-document="${documentId}"]`).forEach(input => {
            corrections[input.dataset.field] = input.value;
        });

        const response = await fetch(`/extract/${documentId}/review`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ corrections }),
        });

        if (response.ok) {
            window.location.reload();
        } else {
            alert('Nəzərdən keçirməni saxlamaq mümkün olmadı');
        }
    }
    </script>
</x-app-layout>
```

---

## Marşrutlar (Routes)

```php
// routes/api.php
use App\Http\Controllers\ExtractionController;

Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/extract', [ExtractionController::class, 'upload']);
    Route::get('/extract/{ulid}', [ExtractionController::class, 'status']);
});

// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::post('/extract/{ulid}/review', [ExtractionController::class, 'review'])
        ->middleware('can:review,extracted_document');
    Route::get('/extractions/review-queue', [ExtractionController::class, 'reviewQueue'])
        ->middleware('can:viewAny,App\Models\ExtractedDocument');
});
```

---

## Biznes Qaydaları Yoxlaması

```php
// app/Services/Extraction/InvoiceValidator.php
<?php

namespace App\Services\Extraction;

use App\Models\ExtractedInvoice;

/**
 * Çıxarmadan sonra biznes qaydalarını tətbiq et.
 * Bu qaydalar Claude-un nəticəsinin strukturca düzgün, lakin
 * rəqəmcə uyuşmaz olduğu halları aşkar edir.
 */
class InvoiceValidator
{
    public function validate(ExtractedInvoice $invoice): array
    {
        $warnings = [];

        // Sətir məhsullarının cəminin ara cəmə uyğunluğunu yoxla
        if ($invoice->line_items && $invoice->subtotal) {
            $lineItemsTotal = collect($invoice->line_items)->sum('total');
            $difference = abs($lineItemsTotal - $invoice->subtotal);
            if ($difference > 0.01) {
                $warnings[] = "Sətir məhsullarının cəmi ({$lineItemsTotal}) ara cəmlə ({$invoice->subtotal}) uyğun gəlmir";
            }
        }

        // Ara cəm + vergi - endirim = ümumi cəm yoxlaması
        if ($invoice->subtotal && $invoice->total_amount) {
            $calculatedTotal = $invoice->subtotal
                + ($invoice->tax_amount ?? 0)
                - ($invoice->discount_amount ?? 0);
            $difference = abs($calculatedTotal - $invoice->total_amount);
            if ($difference > 0.01) {
                $warnings[] = "Hesablanmış cəm ({$calculatedTotal}) göstərilən cəmlə ({$invoice->total_amount}) uyğun gəlmir";
            }
        }

        // Ödəmə tarixinin faktura tarixindən sonra olduğunu yoxla
        if ($invoice->invoice_date && $invoice->due_date) {
            if ($invoice->due_date < $invoice->invoice_date) {
                $warnings[] = "Ödəmə tarixi faktura tarixindən əvvəldir";
            }
        }

        // Valyutanın etibarlı ISO 4217 kodu olduğunu yoxla
        if ($invoice->currency && !in_array(strtoupper($invoice->currency), ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'])) {
            $warnings[] = "Qeyri-adi valyuta kodu: {$invoice->currency}";
        }

        return $warnings;
    }
}
```

---

## İstehsalat Mülahizələri

### Şəkil Keyfiyyəti Vacibdir

Claude-un çıxarma dəqiqliyi şəkil keyfiyyətindən çox asılıdır. Ən yaxşı nəticələr üçün:
- PDF həlli: 150-200 DPI (yüksək = daha çox token = daha baha)
- Şəkillər: 85% keyfiyyətli JPEG kifayətdir
- Maksimum 2000px en (daha böyük dəqiqliyi artırmır, ancaq xərci artırır)

### Sənəd Başına Xərc (təxmini)

| Sənəd Növü | Səhifə | Giriş Tokenları | Xərc |
|------------|--------|-----------------|------|
| Sadə faktura | 1 | ~4,000 | ~$0.06 |
| Mürəkkəb faktura | 2 | ~8,000 | ~$0.12 |
| CV/Resume | 2 | ~6,000 | ~$0.09 |
| Pasport | 1 | ~3,000 | ~$0.05 |

Şəkil tokenları üstünlük təşkil edir. 150 DPI-da tam səhifəlik şəkil ≈ 1500 token.

### Çox Səhifəli Sənədlərin Miqyaslandırılması

10 səhifədən çox olan sənədlər üçün partiyalara bölün:

```php
// Səhifələri 5-lik partiyalarla işlə, nəticələri birləşdir
$chunks = array_chunk($images, 5);
$allExtracted = [];
foreach ($chunks as $i => $chunk) {
    $result = $this->callClaude($chunk, $prompt . " (Pages " . ($i*5+1) . "-" . (($i+1)*5) . ")");
    $allExtracted = $this->mergeExtractions($allExtracted, $result);
}
```

### İdempotentlik (Eyni Sorğunun Təkrar İşlənməsi)

Eyni sənədin yenidən çıxarma üçün növbəyə alınması təhlükəsiz olmalıdır — `storeExtraction` metodu `updateOrCreate` istifadə edir. Dəyişməmiş faylların yenidən işlənməsinin qarşısını almaq üçün hash yoxlaması əlavə edin:

```php
$fileHash = hash_file('sha256', $filePath);
if (ExtractedDocument::where('file_hash', $fileHash)->exists()) {
    // Mövcud çıxarmanı qaytar
}
```
