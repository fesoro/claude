# Multimodal RAG: Şəkil + Mətn Retrieval (Lead)

> **Kim üçündür:** RAG sistemləri quran senior developerlər ki, yalnız mətn deyil, şəkil, PDF layout, diaqram da qayıtarmaq lazımdır.
>
> **Əhatə dairəsi:** Image embedding (CLIP, BLIP-2, OpenAI), late interaction modellər (ColPali), PDF visual understanding, Laravel + pgvector implementasiyası, evaluation.

---

## 1. Niyə Multimodal RAG?

Ənənəvi RAG yalnız mətn üzərindən axtarır. Lakin real məlumatlar:

```
Mühasibat PDF:
  - Cədvəllər (mətn kimi OCR-dən keçirilir → struktur itirilir)
  - Qrafiklər (OCR ilə oxunmur!)
  - Şirkət şəkilləri

Məhsul kataloqu:
  - Şəkillər əsas məzmun daşıyıcısıdır
  - "Bu cihazın arxasındakı port nədir?" → şəkil lazımdır

Texniki sənədlər:
  - Arxitektura diaqramları
  - Elektrik sxemləri
  - UI mockup-ları
```

**Multimodal RAG həll edir:**
- "Şirkətin Q3 gəlir qrafikini göstər" → şəkli retrieve et, cavab ver
- "Bu məhsul mavi rəngdə var?" → şəkildən bil
- "Arxitektura diaqramında hansı servis database-ə qoşulur?" → diaqramı anla

---

## 2. Multimodal Embedding Modelləri

### 2.1 CLIP (OpenAI, 2021)

Mətn və şəkli eyni vector space-ə yerləşdirir. "dog playing" sorğusu ilə köpəyin şəklini tapmaq mümkündür.

```
Arxitektura:
  Mətn encoder → [512-dim vektor]
  Şəkil encoder → [512-dim vektor]
  Contrastive learning: eyni mənalı cütlər bir-birinə yaxın

Üstünlük: Open-source, sürətli
Çatışmazlıq: Mürəkkəb sənəd layout-unu anlamır
```

### 2.2 OpenAI / Cohere Multimodal Embeddings

```
OpenAI: text-embedding-3-large + DALL-E vision
Cohere: embed-v3 (native multimodal)

Üstünlük: API, kolay inteqrasiya
Çatışmazlıq: Xərc, API dependency
```

### 2.3 BLIP-2 (Salesforce, 2023)

Şəkli anlamaq üçün daha güclü, lakin ağır.

```
Arxitektura: ViT (şəkil encoder) + Flan-T5 (dil modeli)
Üstünlük: Şəkli müfəssəl anlamaq, caption yaratmaq
İstifadə: Şəkil → mətn pipeline (caption-based RAG üçün)
```

### 2.4 ColPali (2024) — Sənəd Retrieval üçün Ən Güclü

PDF sənədlərini **şəkil kimi** embed edir — OCR lazım deyil.

```
Ənənəvi PDF RAG:
  PDF → OCR → Mətn → Chunking → Embedding
  Problem: Cədvəl, qrafik, layout itirilir

ColPali:
  PDF page → Screenshot → PaliGemma (vision model) → Token-level patches
  Hər page bir şəkil kimi encode olunur
  Late interaction: query patch-ları ilə doc patch-ları müqayisə

Benchmark: DocVQA-da ənənəvi RAG-dan 20-30% yaxşı
```

---

## 3. Caption-Based Multimodal RAG

Ən praktik başlanğıc yanaşma: şəkilləri əvvəlcə caption-a çevir, sonra mətn RAG.

### 3.1 Arxitektura

```
Ingest pipeline:
  Şəkil/PDF → [Claude Vision / GPT-4o Vision] → Caption (mətn)
                                                      │
                                           Embedding + pgvector

Query pipeline:
  "Bu server nə qədər RAM-a malikdir?" → Embedding → Cosine search
      → Caption: "Server specifications table showing 64GB RAM..."
      → Original image URL da retrieve edilir
      → LLM: caption + şəkil URL ilə cavab
```

### 3.2 Laravel Ingest Pipeline

```php
<?php
// app/Services/RAG/MultimodalIngester.php

namespace App\Services\RAG;

use App\Services\AI\ClaudeService;
use App\Services\AI\EmbeddingService;
use App\Models\MultimodalDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MultimodalIngester
{
    public function __construct(
        private readonly ClaudeService   $claude,
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Şəkli ingest et: caption yarat + embed et
     */
    public function ingestImage(
        UploadedFile $image,
        int          $tenantId,
        array        $metadata = [],
    ): MultimodalDocument {
        // 1. S3-ə yüklə
        $path     = $image->store("multimodal/{$tenantId}", 's3');
        $imageUrl = Storage::disk('s3')->url($path);

        // 2. Claude Vision ilə caption yarat
        $caption = $this->generateCaption($imageUrl, $metadata);

        // 3. Caption-ı embed et
        $embedding = $this->embeddings->embed($caption);

        // 4. DB-ə yaz
        return MultimodalDocument::create([
            'tenant_id'   => $tenantId,
            'type'        => 'image',
            'file_path'   => $path,
            'public_url'  => $imageUrl,
            'caption'     => $caption,
            'embedding'   => $this->formatVector($embedding),
            'metadata'    => $metadata,
        ]);
    }

    private function generateCaption(string $imageUrl, array $metadata = []): string
    {
        $context = isset($metadata['document_title'])
            ? "Bu şəkil '{$metadata['document_title']}' sənədinə aiddir."
            : '';

        $response = $this->claude->messages(
            messages: [[
                'role'    => 'user',
                'content' => [
                    [
                        'type'       => 'image',
                        'source'     => ['type' => 'url', 'url' => $imageUrl],
                    ],
                    [
                        'type' => 'text',
                        'text' => <<<PROMPT
                        {$context}
                        Bu şəkli ətraflı izah et. Xüsusilə aşağıdakılara diqqət et:
                        - Cədvəl varsa: sütun adları, əsas dəyərlər
                        - Qrafik varsa: oxlar, dəyərlər, trend
                        - Diaqram varsa: komponentlər, əlaqələr
                        - Mətn varsa: tam oxu
                        - Rəqəmlər varsa: hamısını qeyd et
                        
                        Cavab tam, axtarış üçün yararlı olsun.
                        PROMPT,
                    ],
                ],
            ]],
            model: 'claude-sonnet-4-5',
        );

        return $response;
    }

    private function formatVector(array $v): string
    {
        return '[' . implode(',', $v) . ']';
    }
}
```

### 3.3 PDF Page-by-Page Ingest

```php
<?php
// app/Services/RAG/PDFMultimodalIngester.php

namespace App\Services\RAG;

use Spatie\PdfToImage\Pdf;  // composer require spatie/pdf-to-image

class PDFMultimodalIngester
{
    public function __construct(
        private readonly MultimodalIngester $imageIngester,
    ) {}

    public function ingestPDF(string $pdfPath, int $tenantId, string $documentTitle): array
    {
        $pdf   = new Pdf($pdfPath);
        $pages = $pdf->pageCount();
        $docs  = [];

        for ($page = 1; $page <= $pages; $page++) {
            // Hər page-i PNG-ə çevir
            $imagePath = sys_get_temp_dir() . "/pdf_page_{$page}.png";
            $pdf->selectPage($page)
                ->setOutputFormat('png')
                ->setResolution(150)
                ->saveImage($imagePath);

            // Şəkil kimi ingest et
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $imagePath,
                "page_{$page}.png",
                'image/png',
                null,
                true,
            );

            $doc = $this->imageIngester->ingestImage(
                $uploadedFile,
                $tenantId,
                [
                    'document_title' => $documentTitle,
                    'page_number'    => $page,
                    'total_pages'    => $pages,
                    'source_pdf'     => basename($pdfPath),
                ],
            );

            $docs[] = $doc;

            // PDF converter yaddaşı boşalt
            unlink($imagePath);

            // Rate limiting (vision API üçün)
            usleep(100_000); // 100ms
        }

        return $docs;
    }
}
```

---

## 4. Multimodal Retrieval

```php
<?php
// app/Services/RAG/MultimodalRetriever.php

namespace App\Services\RAG;

use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\DB;

class MultimodalRetriever
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Həm mətn həm şəkil caption-ları üzərindən axtarış
     */
    public function retrieve(
        string $query,
        int    $tenantId,
        int    $limit       = 5,
        float  $minScore    = 0.7,
        array  $types       = ['text', 'image'],
    ): array {
        $queryEmbedding = $this->embeddings->embed($query);
        $vectorStr      = '[' . implode(',', $queryEmbedding) . ']';

        $results = DB::select(
            <<<SQL
            SELECT
                id,
                type,
                file_path,
                public_url,
                caption,
                metadata,
                1 - (embedding <=> :vector::vector) AS score
            FROM multimodal_documents
            WHERE tenant_id = :tenant_id
              AND type      = ANY(:types)
              AND 1 - (embedding <=> :vector::vector) >= :min_score
            ORDER BY embedding <=> :vector::vector
            LIMIT :limit
            SQL,
            [
                'vector'    => $vectorStr,
                'tenant_id' => $tenantId,
                'types'     => '{' . implode(',', $types) . '}',
                'min_score' => $minScore,
                'limit'     => $limit,
            ],
        );

        return array_map(fn($r) => [
            'id'         => $r->id,
            'type'       => $r->type,
            'content'    => $r->caption,
            'image_url'  => $r->type === 'image' ? $r->public_url : null,
            'metadata'   => json_decode($r->metadata, true),
            'score'      => round($r->score, 4),
        ], $results);
    }
}
```

---

## 5. Multimodal Response Generation

```php
<?php
// app/Services/RAG/MultimodalRAGService.php

namespace App\Services\RAG;

use App\Services\AI\ClaudeService;

class MultimodalRAGService
{
    public function __construct(
        private readonly MultimodalRetriever $retriever,
        private readonly ClaudeService       $claude,
    ) {}

    public function answer(string $query, int $tenantId): array
    {
        // 1. Retrieval
        $results = $this->retriever->retrieve($query, $tenantId);

        if (empty($results)) {
            return [
                'answer'  => 'Bu mövzu ilə əlaqəli məlumat tapılmadı.',
                'sources' => [],
            ];
        }

        // 2. Mesaj qurma (şəkil + mətn birlikdə)
        $contentBlocks = [];

        foreach ($results as $i => $result) {
            $sourceNum = $i + 1;

            if ($result['type'] === 'image' && $result['image_url']) {
                // Şəkli birbaşa Claude-a göndər
                $contentBlocks[] = [
                    'type'   => 'text',
                    'text'   => "Mənbə {$sourceNum} (şəkil):",
                ];
                $contentBlocks[] = [
                    'type'   => 'image',
                    'source' => ['type' => 'url', 'url' => $result['image_url']],
                ];
            } else {
                $contentBlocks[] = [
                    'type' => 'text',
                    'text' => "Mənbə {$sourceNum}: {$result['content']}",
                ];
            }
        }

        $contentBlocks[] = [
            'type' => 'text',
            'text' => "\nSual: {$query}\n\nYuxarıdakı mənbələrə əsasən cavab ver. Şəkillərdən məlumatı birbaşa istifadə et.",
        ];

        // 3. Claude Vision ilə cavab
        $answer = $this->claude->messages(
            messages: [['role' => 'user', 'content' => $contentBlocks]],
            systemPrompt: "Sən multimodal RAG sistemidir. Mətn və şəkil mənbələrindən istifadə edərək dəqiq cavablar verirsin.",
            model: 'claude-sonnet-4-5',
        );

        return [
            'answer'  => $answer,
            'sources' => $results,
        ];
    }
}
```

---

## 6. Use Case-lər

### 6.1 Məhsul Kataloqu Axtarışı

```
Sual: "Qırmızı rəngli, oval formada olan qolbaqlarım varmı?"
Pipeline:
  → "red oval bracelet" → embedding
  → pgvector: şəkil captionları arasında axtarış
  → "Red oval silver bracelet with floral design" caption-u tapılır
  → Şəkil + caption Claude-a göndərilir → cavab
```

### 6.2 Texniki Sənəd Axtarışı

```
Sual: "Arxitektura diaqramında caching layer hansı servislərə qulluq edir?"
Pipeline:
  → Arxitektura diaqramı PDF-i page-by-page ingest edilib
  → Caption: "System architecture diagram showing Redis cache layer connected to..."
  → Retrieval → Şəkli birbaşa Claude-a göndər → Dəqiq cavab
```

### 6.3 Mühasibat Hesabatları

```
Sual: "Q3 gəlir qrafikinə görə ən yüksək ay hansıdır?"
Pipeline:
  → Qrafik şəkil kimi ingest edilib
  → Caption: "Bar chart showing quarterly revenue with peak in August..."
  → Şəkli Claude-a ver → "Qrafikə görə ən yüksək ay Avqustdur"
```

---

## 7. Evaluation

```php
// Multimodal RAG evaluation
$testCases = [
    [
        'query'    => 'Cədvəldə neçə sütun var?',
        'source'   => 'table_image_001',
        'expected' => '5',  // Şəkilə baxıb sayılacaq
    ],
    [
        'query'    => 'Hansı məhsul ən baha satılır?',
        'source'   => 'price_table_001',
        'expected' => 'Premium Package - 299 AZN',
    ],
];

foreach ($testCases as $test) {
    $result = $ragService->answer($test['query'], tenantId: 1);
    
    // LLM-as-judge: cavab doğrudurmu?
    $isCorrect = $judge->evaluate($result['answer'], $test['expected']);
    
    $scores[] = $isCorrect ? 1.0 : 0.0;
}

$accuracy = array_sum($scores) / count($scores);
// Target: >0.80
```

---

## 8. Anti-Pattern-lər

### OCR-ə Etibar Etmək

```
Problem: OCR cədvəl strukturunu, sütun əlaqələrini itirir

YANLIŞ:
  PDF → OCR → "Name Age City Alice 25 Baku Bob 30 Ganja"
  Sual: "Alice neçə yaşındadır?" → Model qaçıra bilər

DOĞRU: ColPali / Vision-based approach
  PDF page → Screenshot → Claude Vision
  "25 yaşlı Alice, Bakıda yaşayır" → dəqiq çıxarma
```

### Çox Böyük Şəkillər

```php
// Şəkil boyutu məhdudlaşdır
// Claude Vision: max 5MB, tövsiyə: <2MB

$image = Image::make($path)
    ->resize(1024, null, function ($c) { $c->aspectRatio(); })
    ->quality(85);
```

### Caption-ı Index Etmədən Saxlamaq

```sql
-- YANLIŞ: Caption-a müntəzəm axtarış — yavaş
SELECT * FROM multimodal_documents WHERE caption LIKE '%gəlir qrafik%';

-- DOĞRU: Embedding üzərindən semantic search
-- Yuxarıdakı pgvector sorğusu
```

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Caption Pipeline Qurmaq

**Məqsəd:** Şirkətin məhsul kataloq şəkillərini multimodal RAG-a daxil etmək.

**Addımlar:**
1. 10 məhsul şəkli seçin
2. `MultimodalIngester::ingestImage()` ilə hər birini emal edin
3. Yaradılan caption-ları yoxlayın — nə qədər dəqiqdir?
4. `MultimodalRetriever::retrieve("göy rəngli məhsullar")` ilə axtarın
5. Nəticədə şəkil caption-larının necə göründüyünü izləyin

**Keyfiyyət meyarı:** Captionlarda rəng, forma, məhsul tipi düzgün qeyd olunmalıdır.

### Tapşırıq 2: PDF Sənəd Anlama

**Məqsəd:** Cədvəlləri olan PDF hesabatını emal etmək.

```php
// Laravel artisan command kimi test edin
php artisan tinker

$ingester = app(PDFMultimodalIngester::class);
$docs = $ingester->ingestPDF(
    storage_path('test-report.pdf'),
    tenantId: 1,
    documentTitle: 'Q3 Maliyyə Hesabatı'
);

// Hər page üçün yaradılan caption-u yoxlayın
foreach ($docs as $doc) {
    echo "Səhifə {$doc->metadata['page_number']}: {$doc->caption}\n";
}

// Test sorğusu
$service = app(MultimodalRAGService::class);
$result = $service->answer("Q3-də ən yüksək xərc hansı sahədədir?", tenantId: 1);
echo $result['answer'];
```

**Ənənəvi OCR ilə müqayisə edin:** Eyni sualı OCR-dən keçirilmiş mətnlə soruşun. Nəticə fərqi nə qədərdir?

### Tapşırıq 3: Multimodal vs Yalnız Mətn Benchmark

```php
// benchmark/multimodal_vs_text_rag.php

$testQuestions = [
    "Cədvəldə neçə sütun var?",
    "Hansı ay ən yüksək satışları göstərir?",
    "Şirkətin logotipi hansı rəngdədir?",
    "Sistemin arxitekturasında hansı verilənlər bazası istifadə olunur?",
];

$textOnlyScores  = [];
$multimodalScores = [];

foreach ($testQuestions as $q) {
    // Yalnız mətn RAG
    $textResult = $textRagService->answer($q, tenantId: 1);
    $textOnlyScores[] = $judge->evaluate($textResult, $groundTruth[$q]);

    // Multimodal RAG
    $mmResult = $multimodalRAGService->answer($q, tenantId: 1);
    $multimodalScores[] = $judge->evaluate($mmResult['answer'], $groundTruth[$q]);
}

$textAccuracy = array_sum($textOnlyScores) / count($textOnlyScores);
$mmAccuracy   = array_sum($multimodalScores) / count($multimodalScores);

echo "Mətn RAG: {$textAccuracy} | Multimodal RAG: {$mmAccuracy}";
// Gözlənilən: cədvəl/qrafik sualları üçün multimodal 20-30% yaxşı
```

---

## Əlaqəli Mövzular

- [03-rag-architecture.md](03-rag-architecture.md) — Ənənəvi RAG əsasları
- [04-chunking-strategies.md](04-chunking-strategies.md) — Sənəd chunking
- [../02-claude-api/06-vision-pdf-support.md](../02-claude-api/06-vision-pdf-support.md) — Claude Vision API
- [02-vector-databases.md](02-vector-databases.md) — pgvector qurulumu
- [07-contextual-retrieval.md](07-contextual-retrieval.md) — Retrieval dəqiqliyini artırmaq
