# Claude Vision və PDF Dəstəyi: Image, Document Understanding və Production Patterns

> Hədəf auditoriyası: Laravel tətbiqinə image/PDF upload + Claude ilə analiz əlavə edən senior developerlər. OCR, invoice extraction, document QA, multimodal chat kimi use-case-lər. Vision-un ümumi konsepti üçün 07-multimodal-ai.md-ə bax; bu fayl Claude-spesifik API detalları, production patterns və Laravel inteqrasiyasına fokuslanır.

---

## Mündəricat

1. [Claude Vision — Ümumi Baxış](#overview)
2. [API Formatları: Base64, URL, Files API](#api-formats)
3. [Dəstəklənən Format-lar](#supported-formats)
4. [Image Tokenization Math](#tokenization-math)
5. [PDF Dəstəyi — Native vs Converted](#pdf-support)
6. [Çox-səhifəli PDF Chunking](#multipage-chunking)
7. [OCR vs Native Vision](#ocr-vs-vision)
8. [Çoxlu Şəkil Bir Mesajda](#multi-image)
9. [Vision + Structured Output](#vision-structured)
10. [Vision + Tool Use](#vision-tools)
11. [Prompting Tips for Vision](#prompting-tips)
12. [Image Preprocessing](#preprocessing)
13. [Laravel Nümunəsi: Invoice Extractor](#laravel-invoice)
14. [Cost Estimation](#cost)
15. [Hallucination Risk-ləri Vision-də](#hallucinations)
16. [Privacy və Security](#privacy)
17. [Anti-Pattern-lər](#anti-patterns)
18. [Qərar Çərçivəsi](#decision-framework)

---

## Claude Vision — Ümumi Baxış

Claude 3+ modellərdə vision bütün əsas modellərdə mövcuddur (Haiku, Sonnet, Opus). Xüsusi "vision model" yoxdur — eyni model hər iki modalı qəbul edir.

```
Messages API:
 content: [
   { type: "text", text: "Bu şəkildə nə var?" },
   { type: "image", source: { ... } }
 ]
```

### Nə Edə Bilir?

- OCR (text extraction) — təbii dildə
- Document understanding (invoice, receipt, form)
- Chart / graph analysis
- Diagram interpretation
- UI screenshot analysis
- Handwriting recognition
- Object detection (təbii dildə təsvir)
- Scene understanding
- Comparison between images

### Nə Edə Bilmir (və ya Zəifdir)

- Coordinate-lər / bounding box verə bilmir (təbii dildə təsvir edir)
- Pixel-level precision yoxdur
- Yüksək text-dense şəkillərdə OCR dəqiqliyi azalır
- Tibbi şəkillər (X-ray, MRI) — diqqətli ol, diaqnostik deyil
- Face recognition (adamın adını bilmir, təsvir edir)
- NSFW / zərərli content — refuse edir

---

## API Formatları: Base64, URL, Files API

Üç yol ilə şəkil göndərə bilərsən:

### 1. Base64 (ən geniş yayılmış)

```json
{
  "content": [
    {
      "type": "image",
      "source": {
        "type": "base64",
        "media_type": "image/jpeg",
        "data": "iVBORw0KGgoAAAANSUhEUg..."
      }
    },
    { "type": "text", "text": "Describe this." }
  ]
}
```

Üstünlük: self-contained, URL host lazım deyil.  
Çatışmazlıq: request size artır (base64 33% overhead), hər dəfə yenidən göndərilir (caching yoxdur əgər cache_control qoyulmasa).

### 2. URL (public)

```json
{
  "source": {
    "type": "url",
    "url": "https://example.com/invoice.jpg"
  }
}
```

Üstünlük: request kiçik, yenidən istifadə asan.  
Çatışmazlıq: URL **public olmalıdır**, Anthropic pulled edir. Signed URL (expiring) ilə işləmir. Enterprise data üçün uyğun deyil.

### 3. Files API (tövsiyə olunan, production)

```json
{
  "source": {
    "type": "file",
    "file_id": "file_01ABC123..."
  }
}
```

Üstünlük:
- Bir dəfə upload, çoxlu istifadə
- Prompt caching ilə optimal
- Tenant isolation
- Lifecycle yönetimi

Files API haqqında ətraflı: 08-files-api-citations.md.

### Seçim Qaydası

```
Bir dəfə istifadə + kiçik fayl  → base64
Çox dəfə istifadə              → Files API
Public content                 → URL
Enterprise / regulated         → Files API
Development / prototyping      → base64
```

---

## Dəstəklənən Format-lar

### Şəkil Format-ları

| Format | MIME | Qeyd |
|---|---|---|
| JPEG | `image/jpeg` | ən yaygın, yaxşı kompressiya |
| PNG | `image/png` | lossless, UI screenshot üçün |
| GIF | `image/gif` | statik (ilk frame) |
| WebP | `image/webp` | müasir, sərfəli |

### Limit-lər

```
Fayl ölçüsü: 5 MB / şəkil
Ölçülər:      8000 × 8000 px maksimum
Mesaj başına: 20 şəkil maksimum
Məzmun başına: 100 şəkil (uzun mesajlarda)
```

(Dəqiq limitlər dəyişə bilər — rəsmi sənədə bax).

### PDF

PDF native dəstəklənir — Anthropic səhifələri avtomatik image-lərə convert edir və text extract edir.

```
Maks ölçü: 32 MB
Maks səhifə sayı: 100 səhifə / PDF
Maks document-lər: 1000 səhifə / request
```

---

## Image Tokenization Math

Şəkillər input token-ə çevrilir — cost calculasiya üçün kritikdir.

### Formula

```
tokens = (width × height) / 750

Misal:
  1024 × 1024 px → 1398 token
  512 × 512 px   → 350 token
  2048 × 2048 px → 5592 token
  100 × 100 px   → 13 token (kiçiklik üçün minimum)
```

Bu təxmini formuladır — Claude daxili tile-lara böltü və processing edir.

### Max Token Per Image

```
Claude tipik olaraq şəkli 1568 token-ə qədər sıxışdırır.
3072 × 3072 px-dən böyük şəkillər avtomatik kiçildir.
```

### Cost Misalı

Sonnet 4.6, input $3/M token:

```
1 fayl 1024×1024:        1398 tok × $3/M = $0.0042
100 fayl batch:           139,800 tok × $3/M = $0.42
10,000 fayl/gün:          13,980,000 tok/gün × $3/M = $42/gün
```

Çox-fayl iş yüklərində cost-u nəzərə al. Batch API ilə 50% endirim mümkündür.

### Resize Strategy

Şəkli kiçiltmək cost-u azalda bilər. 1024-dən böyük genişlikdə:

```
Original: 4000×3000 px  → 16000 token
Resized to 1024 width: 1024×768  → 1048 token
Qənaət: 15x
```

Amma keyfiyyət itkisi ola bilər. Text-dense (document) şəkillərdə çox kiçiltmə OCR-ı korlayır.

---

## PDF Dəstəyi — Native vs Converted

### Native Support (tövsiyə olunan)

Claude PDF-i birbaşa qəbul edir (2024 sonundan etibarən). Hər səhifəyə həm text, həm də visual kimi baxır.

```json
{
  "content": [
    {
      "type": "document",
      "source": {
        "type": "base64",
        "media_type": "application/pdf",
        "data": "JVBERi0xLjQK..."
      }
    },
    { "type": "text", "text": "Summarize this contract." }
  ]
}
```

Header lazım ola bilər:
```
anthropic-beta: pdfs-2024-09-25
```

(Rəsmi sənədə bax — beta header status-u dəyişə bilər).

### Manual Conversion (köhnə yol)

PDF-i özün PNG-lərə çevir (ImageMagick / Poppler):

```bash
pdftoppm -r 150 input.pdf page -png
# Generates: page-1.png, page-2.png, ...
```

Sonra hər səhifə ayrı image kimi göndər.

### Müqayisə

| Yanaşma | Üstünlük | Çatışmazlıq |
|---|---|---|
| Native PDF | Sadə, text + image birlikdə | Yeni feature, bəzi edge case |
| Manual PNG | Dəqiq kontrol, köhnə API | İnfra, preprocessing |

Yeni layihələrdə **native PDF** istifadə et.

### Text Extraction vs Vision

PDF-də Claude iki mənbədən istifadə edir:
1. **Embedded text** (digital PDF-lərdə) — sürətli, dəqiq
2. **Visual OCR** (skan PDF-lərdə) — lazım olduqda

Hybrid: digital PDF-lərdə hər ikisi istifadə olunur, yoxsa yalnız visual. Nəticə daha dəqiq olur.

---

## Çox-səhifəli PDF Chunking

Böyük PDF-lər (100+ səhifə) üçün strategiyalar:

### 1. Full Upload (kiçik PDF-lər üçün)

100 səhifəyə qədər native olaraq göndər. Claude hər səhifəyə attention verir.

### 2. Page Ranging

Yalnız lazımi səhifələri göndər:

```php
// Split PDF into ranges
$pages = $this->extractPages($pdfPath, [1, 3, 5, 10]);
```

### 3. Two-stage: Summary + Dig-in

```
Stage 1: İlk 20 səhifə + son 10 səhifə → Claude → "Hansı səhifələr relevant?"
Stage 2: Relevant səhifələri tam göndər → final cavab
```

### 4. RAG over PDF

Böyük sənəd üçün:
1. PDF-i səhifə-səhifə extract et
2. Hər səhifəni embed et
3. Sual üçün top-K səhifəni Claude-ə göndər

Bu, 04-rag folderindəki pattern-dir. Cost və sürət üçün ən effektivdir.

---

## OCR vs Native Vision

Klassik OCR (Tesseract, AWS Textract, Google Vision) vs Claude Vision müqayisəsi:

| Xüsusiyyət | Klassik OCR | Claude Vision |
|---|---|---|
| Text extraction dəqiqliyi | Yüksək (təmiz text) | Orta-yüksək |
| Structured data (tables) | Plain text | Strukturlu şərh |
| Handwriting | Orta | Yaxşı |
| Context understanding | Yoxdur | Güclü |
| Multi-language | Dəstəklənir | Yüksək |
| Cost per image | $0.0015 (Textract) | $0.0042 (Sonnet) |
| Latency | 1-3s | 3-8s |
| Anlamaq / reasoning | Yoxdur | Var |

### Hybrid Yanaşma (production-da tövsiyə)

```
Pipeline:
1. AWS Textract / Tesseract ilə raw text çıxar (sürətli, ucuz)
2. Claude-ə göndər: raw text + orijinal şəkil
3. Claude structured output yaratsın (Textract-ın bilmədiyi kontekst)
```

### Yalnız OCR Kifayət Edərsə

Sadə fayl uploader-də (məs., "PDF-i text-ə çevir"), Claude artıqdır. Textract daha sürətli və ucuzdur.

### Yalnız Vision Lazımdırsa

Diagram interpretation, chart reading, UI understanding — OCR kömək etmir. Vision zəruridir.

---

## Çoxlu Şəkil Bir Mesajda

```json
{
  "content": [
    { "type": "text", "text": "Bu iki şəkli müqayisə et:" },
    { "type": "image", "source": {...} },
    { "type": "text", "text": "Yuxarıdakı versiya 1." },
    { "type": "image", "source": {...} },
    { "type": "text", "text": "Yuxarıdakı versiya 2. Fərqləri sadala." }
  ]
}
```

Claude hər şəkli ayrı-ayrı analiz edir, müqayisə edə bilir.

### Çoxlu Şəkil Use-case-ləri

- Before/after müqayisəsi
- UI regression testi (2 screenshot)
- Multi-page form (hər səhifə şəkil)
- Product comparison
- Time-series (chart variantları)

### Limit

20 şəkil / mesaj. Daha çox lazımsa, çoxlu turn-a böl.

---

## Vision + Structured Output

Ən güclü kombinasiya: şəkildən **strukturlu data** çıxarmaq.

### Invoice Extraction Misalı

```php
$schema = [
    'type' => 'object',
    'properties' => [
        'invoice_number' => ['type' => 'string'],
        'issue_date' => ['type' => 'string', 'format' => 'date'],
        'due_date' => ['type' => 'string', 'format' => 'date'],
        'vendor' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'tax_id' => ['type' => ['string', 'null']],
                'address' => ['type' => 'string'],
            ],
        ],
        'line_items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'description' => ['type' => 'string'],
                    'quantity' => ['type' => 'number'],
                    'unit_price' => ['type' => 'number'],
                    'total' => ['type' => 'number'],
                ],
            ],
        ],
        'subtotal' => ['type' => 'number'],
        'tax' => ['type' => 'number'],
        'total' => ['type' => 'number'],
        'currency' => ['type' => 'string'],
    ],
    'required' => ['invoice_number', 'total', 'currency'],
];
```

### Prompt Nümunəsi

```
You are a document extraction assistant. Extract invoice data from
the image into the JSON schema below. If a field is not visible or
unclear, use null. Do not infer or hallucinate values.

Only output valid JSON matching this schema:
{schema here}
```

---

## Vision + Tool Use

Vision-u tool use ilə birləşdirmək — "describe then query" pattern-i.

```
User: [Screenshot of error]

Model thinking: "Screenshot-da PHP Fatal Error görünür. Query database."

Tool call: search_error_db(query="Call to undefined method User::hasRole")

Tool result: { suggested_fix: "Install spatie/laravel-permission" }

Model text: "Screenshot-dakı xəta spatie/laravel-permission paketinin
             yüklənməməsi səbəbilədir. Quraşdırın: composer require..."
```

### Nümunə: UI Bug Reporter

```php
$response = $claude->messages()->create([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 2000,
    'tools' => [
        [
            'name' => 'search_issue_tracker',
            'description' => 'Search internal issue tracker for similar bugs',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                    'component' => ['type' => 'string'],
                ],
            ],
        ],
    ],
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'image/png',
                        'data' => $base64Screenshot,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => 'Bu screenshot-dakı UI bug-u analiz et. '
                           . 'Oxşar bug varsa issue tracker-də axtarış et.',
                ],
            ],
        ],
    ],
]);
```

---

## Prompting Tips for Vision

### 1. Explicit Instruction

```
YAXŞI DEYIL:
 "Bu şəkildə nə var?"

YAXŞI:
 "Bu invoice şəklindən aşağıdakı sahələri çıxar: invoice number,
 date, total amount, currency. Əgər sahə görünmürsə, null qaytar."
```

### 2. Hallucination-ı Sındır

Vision model görə bilmədiyi halda **uydurma meyli** var. Prompt-da açıq şəkildə qarşısını al:

```
Tapşırıq:
- Əgər məzmun şəkildə dəqiq görünmürsə, "unclear" yaz
- Əgər sahə yoxdursa, null qaytar
- Heç vaxt görməyindən emin olmadığın məlumatı qaytarma
```

### 3. Position Instructions

```
Şəkil layout-ı:
- Sol yuxarı küncdə logo var
- Yuxarıda vendor məlumatı
- Ortada line items cədvəli
- Aşağıda total

Hər sahə üçün şəkildə harada gördüyünü qeyd et.
```

### 4. Expected Format Nümunəsi

Few-shot examples vision-də güclü işləyir:

```
Nümunə 1:
 Input: [invoice şəkli]
 Output: {"invoice_number": "INV-001", "total": 1500.00, ...}

Nümunə 2:
 Input: [invoice şəkli]
 Output: {"invoice_number": "2024-XX-42", "total": 99.99, ...}

İndi bu şəkli analiz et:
 Input: [user invoice]
 Output: ?
```

### 5. Multi-image Clarification

```
Aşağıda 3 şəkil var. Onları müqayisə et:
- Şəkil 1 (Before): ...
- Şəkil 2 (After): ...
- Şəkil 3 (Expected): ...

Fərq təhlili: ...
```

Açıq şəkildə label et ki, model hansının hansı olduğunu bilsin.

---

## Image Preprocessing

Göndərmədən əvvəl optimallaşdırma cost + quality təsir edir.

### 1. Resize

```php
use Intervention\Image\Facades\Image;

$img = Image::make($uploadedFile);

// Maksimum genişlik 1568 px (Claude-in token limiti nöqtəsi)
if ($img->width() > 1568) {
    $img->resize(1568, null, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
}

$base64 = base64_encode($img->encode('jpeg', 85)->__toString());
```

### 2. Format Convert

PNG → JPEG (text-dense olmayan şəkillər üçün) — faylın ölçüsünü azaldır.

### 3. DPI Nəzarəti

Document şəkillərdə minimum 150 DPI tövsiyə olunur. 300 DPI ən yaxşıdır, amma fayl ölçüsü artır.

```bash
pdftoppm -r 150 input.pdf page -png
```

### 4. Deskew / Rotation Correction

Əyri şəkillər OCR-ı çətinləşdirir. Open-source alətlər:

```php
// Intervention Image ilə
$img->rotate(-2.5);  // əgər bilərsən

// Yaxud OpenCV / Hough transform ilə avtomatik
```

### 5. Cropping

Şəkildə yalnız lazımi hissə varsa, crop et:

```php
$img->crop(1000, 500, 100, 200);  // width, height, x, y
```

### 6. Color Adjustment

Scan-dır aşağı kontrast? Contrast artır:

```php
$img->contrast(20)->brightness(10);
```

### 7. Validation

Göndərmədən əvvəl yoxla:

```php
$size = strlen(base64_decode($base64));
if ($size > 5 * 1024 * 1024) {
    throw new \Exception('Şəkil 5 MB-dan böyükdür');
}

$dims = $img->getSize();
if ($dims->getWidth() > 8000 || $dims->getHeight() > 8000) {
    throw new \Exception('Şəkil ölçüləri limiti keçir');
}
```

---

## Laravel Nümunəsi: Invoice Extractor

Tam production-ready invoice extraction pipeline.

### 1. Controller

```php
<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessInvoiceJob;
use Illuminate\Http\Request;

class InvoiceUploadController
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $path = $request->file('file')->store('invoices', 's3');

        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        ProcessInvoiceJob::dispatch($invoice->id);

        return response()->json([
            'invoice_id' => $invoice->id,
            'status' => 'processing',
        ]);
    }
}
```

### 2. Job

```php
<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\InvoiceExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProcessInvoiceJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $invoiceId) {}

    public function handle(InvoiceExtractor $extractor): void
    {
        $invoice = Invoice::findOrFail($this->invoiceId);
        $invoice->update(['status' => 'extracting']);

        try {
            $data = $extractor->extract($invoice->file_path);

            $invoice->update([
                'extracted_data' => $data,
                'status' => 'completed',
                'total_amount' => $data['total'] ?? null,
                'currency' => $data['currency'] ?? null,
                'vendor_name' => $data['vendor']['name'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $invoice->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### 3. Extractor Service

```php
<?php

namespace App\Services;

use Anthropic\Anthropic;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class InvoiceExtractor
{
    public function __construct(private Anthropic $claude) {}

    public function extract(string $filePath): array
    {
        $contents = Storage::disk('s3')->get($filePath);
        $mimeType = $this->detectMime($filePath);

        $content = $this->buildContent($contents, $mimeType);

        $response = $this->claude->messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 2000,
            'temperature' => 0,
            'system' => $this->systemPrompt(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ]);

        $text = $response->content[0]->text;

        return $this->parseJson($text);
    }

    private function buildContent(string $rawFile, string $mimeType): array
    {
        $content = [];

        if ($mimeType === 'application/pdf') {
            $content[] = [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode($rawFile),
                ],
            ];
        } else {
            // Image: preprocess first
            $img = Image::make($rawFile);
            if ($img->width() > 1568) {
                $img->resize(1568, null, fn($c) => $c->aspectRatio());
            }
            $encoded = $img->encode('jpeg', 85)->__toString();

            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'image/jpeg',
                    'data' => base64_encode($encoded),
                ],
            ];
        }

        $content[] = [
            'type' => 'text',
            'text' => "Bu invoice-dən məlumatı extract et. Yalnız JSON qaytar.",
        ];

        return $content;
    }

    private function systemPrompt(): string
    {
        return <<<SYSTEM
You are an invoice data extraction assistant. Extract the following
fields from the invoice image/PDF into JSON format:

- invoice_number (string)
- issue_date (ISO 8601 date, e.g. "2026-04-24")
- due_date (ISO 8601 date or null)
- vendor.name (string)
- vendor.tax_id (string or null)
- vendor.address (string or null)
- line_items (array of {description, quantity, unit_price, total})
- subtotal (number)
- tax (number)
- total (number)
- currency (ISO 4217 code, e.g. "USD", "EUR", "AZN")

Rules:
1. Return ONLY valid JSON, no markdown, no explanation
2. If a field is not visible, use null (or empty array for line_items)
3. Do NOT infer or guess values — use null if uncertain
4. Currency MUST be an ISO code
5. All monetary values as numbers (not strings)
SYSTEM;
    }

    private function parseJson(string $text): array
    {
        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\n?/m', '', trim($text));
        $text = preg_replace('/\n?```$/', '', $text);

        $data = json_decode(trim($text), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from model: ' . json_last_error_msg());
        }
        return $data;
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
```

### 4. Validation və Retry

Extracted data-nı DB-yə yazmazdan əvvəl validate et:

```php
use Illuminate\Support\Facades\Validator;

$validator = Validator::make($data, [
    'invoice_number' => 'required|string|max:100',
    'total' => 'required|numeric|min:0',
    'currency' => 'required|string|size:3',
    'line_items' => 'array',
    'line_items.*.total' => 'numeric',
]);

if ($validator->fails()) {
    // Re-extract with stricter prompt
    $data = $this->extractWithSchemaRepair($filePath, $validator->errors());
}
```

---

## Cost Estimation

### Tipik Use-case-lər

```
Invoice extraction (1-sahəli):
  1024×1024 image → ~1400 token
  Prompt + response ~500 token
  Cost: (1400 + 500) × $3/M + 500 × $15/M = ~$0.013

Contract review (20-səhifəlik PDF):
  20 səhifə × 1400 tok = 28,000 token
  Response 2000 tok
  Cost: 28,000 × $3/M + 2000 × $15/M = $0.11

Screenshot bug analysis:
  1 image + thinking (budget 4000)
  Cost: ~$0.05-0.08
```

### Monthly Estimation

```
Scenario: SaaS platform, 10,000 invoice/ay

Avg cost: $0.015/invoice
Monthly: $150/ay
Yearly:  $1,800/ay
```

Bu, bir insanın 2 saatlıq işinə bərabərdir — ROI çox yüksəkdir.

### Batch API Endirimi

Real-time lazım olmayan invoice-lər üçün Batch API (10-batch-api.md):

```
Standard: $150/ay
Batch:    $75/ay (50% endirim)
```

### Caching ilə Optimallaşdırma

System prompt böyükdürsə (detailed extraction rules), cache et:

```php
[
  'system' => [
    ['type' => 'text', 'text' => '[3000-token extraction rules]',
     'cache_control' => ['type' => 'ephemeral']],
  ],
]
```

Cache read cost: $0.30/M. Qənaət: minute-lərlə requestlər arasında 90%.

---

## Hallucination Risk-ləri Vision-də

Vision-də hallucination xüsusi risklidir, çünki:

1. Model şəkildə olmayan məzmun "görə" bilər
2. Blurry/low-quality şəkil yaxşı əhatələnmiş kimi təsvir edilir
3. Text-dense şəkillərdə misread → confident yanlış cavab

### Aşkarlama

```
1. Multi-sample: temperature > 0 ilə 3 dəfə extract et,
   müqayisə et. Fərqlilik = hallucination risk.

2. Schema validation: həmişə validate et. Rəqəm
   sahəsində mətn gəlirsə → problem.

3. Cross-check: total = subtotal + tax. Arithmetic
   test et.

4. Qanuni validasiya: invoice_number formatı,
   currency ISO code və s.
```

### Azaldılma

1. **Explicit "don't guess"** prompt: "Əgər görmürsənsə, null qaytar"
2. **Structured output + required fields**: skema pozulursa reject
3. **Low temperature**: 0 istifadə et
4. **Pre-process**: şəkil keyfiyyətini artır
5. **Human-in-loop**: critical data-nı manual review et

### Confidence Scoring

Prompt-a əlavə et:

```
Hər sahə üçün confidence qaytar (0-1):
{
  "total": 1234.56,
  "total_confidence": 0.95,
  "vendor_name": "Acme Corp",
  "vendor_name_confidence": 0.80
}

Threshold 0.7-dən aşağı olan sahələri manual review-a yönəlt.
```

---

## Privacy və Security

### Müştəri Şəkilləri Risk Kateqoriyaları

1. **PII**: ID kartı, pasport, sürücülük vəsiqəsi
2. **PCI**: credit card image-ları
3. **PHI**: tibbi sənədlər
4. **Proprietary**: şirkət daxili məlumat
5. **User-generated**: user content (moderation)

### Compliance

```
GDPR / Azərbaycan "Fərdi Məlumatlar" qanunu:
- Upload-dan əvvəl consent
- Data retention siyasəti (nə qədər saxlanır?)
- Right to delete (user silməyi tələb edə bilər)

PCI-DSS:
- Credit card numbers saxlanmamalıdır
- Image-ləri Claude-ə göndərmədən mask et

HIPAA (ABŞ):
- Anthropic BAA (Business Associate Agreement) lazımdır
- Enterprise tier tələb olunur
```

### Anthropic-in Data İstifadəsi

Claude API-də göndərilən data:
- Default olaraq model training-də **istifadə edilmir**
- 30 gün ərzində saxlanır (abuse detection üçün)
- Enterprise tier-də zero retention opsiyası var

Rəsmi siyasətə bax: https://www.anthropic.com/legal

### PII Redaction

Göndərmədən əvvəl mask et:

```php
use App\Services\PIIRedactor;

$redactedImage = $piiRedactor->maskIdCard($originalImage);
// ID nömrəsini, adı və s. qara kvadratla örtür
```

Lokal library və ya Presidio / AWS Comprehend istifadə et.

### Audit Log

Hər vision request log olunmalıdır:

```php
VisionRequestLog::create([
    'user_id' => $user->id,
    'tenant_id' => $tenant->id,
    'file_hash' => hash('sha256', $fileContents),
    'model' => 'claude-sonnet-4-6',
    'timestamp' => now(),
    'result_type' => 'invoice_extraction',
    'tokens_used' => $response->usage->input_tokens,
]);
```

### Access Control

Upload-dan sonra file-ları tenant-izolasiyada saxla:

```php
Storage::disk('s3')->put(
    "tenants/{$tenant->id}/invoices/{$uuid}.pdf",
    $contents,
    'private'
);
```

Signed URL-lərlə temporary access ver.

---

## Anti-Pattern-lər

### 1. Şəkil Keyfiyyətini İgnore Etmək

Blurry / kiçik / low-DPI şəkil → pis cavab. "Niyə Claude yanlış oxudu" deyə şikayətlənmə — input-un keyfiyyətini yoxla.

### 2. PDF-i Vision kimi İşlətmək (tekst var)

Digital PDF-də text extraction + Claude text input birlikdə işləsi daha effektivdir. Native PDF support bunu avtomatik edir.

### 3. Çox Böyük Şəkil Göndərmək

4000×4000 px + orijinal ölçü → 17k token × $3/M = $0.05/request. Resize et, 1568 px kifayətdir.

### 4. Structured Output-sız Extraction

"Bu invoice-dən məlumatı çıxar" → model sərbəst format verir, parse çətin, hallucination səviyyəsi artır. Həmişə JSON schema ver.

### 5. Screenshot-a PII Maskalamamaq

User screenshot upload edəndə email, credit card nömrəsi görünür. Production-a getməzdən əvvəl redact et.

### 6. Tool-un Parametri Kimi Image Qəbul Etmək (səhv fikir)

Tool inputs scalar-dırlar, image deyil. Image mesajın content-ində olmalıdır.

### 7. Temperature Yüksək Saxlamaq

Vision extraction-da `temperature=0` standart olmalıdır. 0.7 → nondeterministik, hallucination yüksək.

### 8. Cache Control-suz System Prompt

Böyük extraction prompt hər request-də yenidən tokenizə edilir. Cache et.

### 9. Manual Verification-siz Production-a Çıxmaq

İlk 100 extracted invoice-u manual review et. Pattern-lar görərsən — bəzi field-lər həmişə səhv. Prompt-u ona görə düzəlt.

### 10. "Vision = Tam Həll" Düşüncəsi

Vision OCR-in əvəzi deyil — onun tamamlayıcısıdır. Pure OCR bəzi use-case-lərdə hələ də daha yaxşıdır.

---

## Qərar Çərçivəsi

### Vision vs OCR vs Hybrid?

```
Məqsədiniz nədir?
  ├── Təmiz text extraction → Textract / Tesseract
  ├── Structured data (invoice, form) → Hybrid (OCR + Claude)
  ├── Document understanding (contract review) → Claude Vision
  ├── UI / screenshot analysis → Claude Vision
  ├── Chart / diagram interpretation → Claude Vision
  ├── Handwriting-heavy → Claude Vision (yaxşı)
  └── Realtime mobile scanning → Native OCR (on-device)
```

### API Format Seçimi

| Scenario | Format |
|---|---|
| Ad-hoc / low volume | Base64 |
| Recurring documents | Files API |
| Public media (blog, wiki) | URL |
| Multi-tenant SaaS | Files API + tenant isolation |
| High-volume batch | Files API + Batch endpoint |

### Model Seçimi

| Use case | Model |
|---|---|
| Simple extraction (invoice) | Sonnet 4.6 |
| Long document review | Sonnet 4.6 + extended thinking |
| Chart / reasoning over visual | Opus 4.7 + thinking |
| High-volume, low-complexity | Haiku 4.5 |

### Preprocessing Pipeline

```
Upload → Validate size/type → Detect MIME →
  ├── Image?
  │    ├── Resize if > 1568px
  │    ├── Convert to JPEG (non-diagram)
  │    ├── Redact PII if needed
  │    └── Base64 / Files upload
  │
  └── PDF?
       ├── Check page count (< 100)
       ├── Split if > 50 pages
       └── Native PDF upload
```

---

## Xülasə

- Claude Vision bütün əsas modellərdə (Haiku/Sonnet/Opus) mövcuddur — xüsusi vision model yoxdur
- API: base64 (ad-hoc), URL (public), Files API (production) — hər birinin öz istifadə yeri
- Dəstəklənən: JPEG/PNG/GIF/WebP, max 5 MB/8000×8000 px; PDF native 100 səhifəyə qədər
- Tokenization: təxminən (width × height) / 750, max ~1568 token/image
- OCR ilə hybrid yanaşma production-da ən effektivdir: ucuz OCR + güclü Claude reasoning
- Vision + structured output = invoice/receipt/form extraction üçün güclü pattern
- Vision + tool use = multimodal agentic workflows
- Hallucination riski vision-də yüksəkdir — explicit "don't guess" + schema + low temperature
- Privacy: PII redaction, tenant isolation, audit logging zəruridir
- Preprocessing: resize, format convert, contrast adjustment cost + quality qənaəti verir
- Qərar: sadə OCR-də Textract; kompleks tapşırıqlarda Claude Vision; yüksək volume-də Batch + caching

---

*Növbəti: [17 — Files API və Citations](./08-files-api-citations.md)*
