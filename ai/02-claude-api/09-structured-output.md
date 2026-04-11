# Claude-dan Strukturlu JSON Output — Miqyasda Etibarlı Çıxarma

> Strukturlu outputun üç yanaşması, schema tətbiqetməsi, deformasiyaya uğramış cavabların idarə edilməsi və tipli DTO-larla istehsala hazır çıxarıcı. Real dünya nümunəsi: faktura məlumatlarının çıxarılması.

---

## Mündəricat

1. [Strukturlu Output Problemi](#strukturlu-output-problemi)
2. [Yanaşma 1: Tool Use vasitəsilə JSON](#yanaşma-1-tool-use-vasitəsilə-json)
3. [Yanaşma 2: XML Parse etmə](#yanaşma-2-xml-parse-etmə)
4. [Yanaşma 3: Assistant Prefilling](#yanaşma-3-assistant-prefilling)
5. [Yanaşma Müqayisəsi](#yanaşma-müqayisəsi)
6. [AI Çıxarma üçün Schema Dizaynı](#ai-çıxarma-üçün-schema-dizaynı)
7. [Deformasiyaya Uğramış Cavabların İdarə Edilməsi](#deformasiyaya-uğramış-cavabların-idarə-edilməsi)
8. [Laravel: StructuredExtractor Sinifi](#laravel-structuredextractor-sinifi)
9. [Laravel: Eloquent Model Hidrasiyası](#laravel-eloquent-model-hidrasiyası)
10. [Laravel: Deformasiyaya Uğramış JSON üçün Yenidən Cəhd Mexanizmi](#laravel-deformasiyaya-uğramış-json-üçün-yenidən-cəhd-mexanizmi)
11. [Real Nümunə: Faktura Məlumatı DTO](#real-nümunə-faktura-məlumatı-dto)
12. [Performans və Xərc Optimizasiyası](#performans-və-xərc-optimizasiyası)

---

## Strukturlu Output Problemi

LLM-lər mətni ehtimallı şəkildə generasiya edir. Etibarlı şəkildə parse edilə bilən strukturlu output almaq üçün texnika tələb olunur:

```
NƏ YANLIŞ GEDƏ BİLƏR:
  1. Yanlış format:    Model JSON əvəzinə nəsr çıxarır
  2. Əlavə mətn:      JSON-dan əvvəl/sonra izahat mətni
  3. Deformasiyalı JSON:  Çatışmayan dırnaq, arxadan vergül, yanlış iç içəlik
  4. Schema uyğunsuzluğu: Düzgün struktur, yanlış sahə adları və ya tiplər
  5. Çatışmayan sahələr:  Model nullable sahələri tamamilə buraxır
  6. Hallüsinasiya edilmiş sahələr: Model schema-da olmayan əlavə sahələr əlavə edir
  7. Yanlış tiplər:     "amount": "500.00" əvəzinə "amount": 500.00

MÜDAXİLƏ OLMADAN TEZLIK:
  Sadə schema, aydın prompt:    İlk cəhddə ~85% düzgün
  Mürəkkəb schema, qeyri-müəyyən məlumat: İlk cəhddə ~60% düzgün

DÜZGÜN TEXNİKALARLA:
  Tool use + schema tətbiqetməsi:  ~98-99% düzgün
  XML sarma + yenidən cəhd:           ~95-97% düzgün
```

---

## Yanaşma 1: Tool Use vasitəsilə JSON

Ən etibarlı yanaşma. JSON Schema ilə tool təyin edin və Claude-u onu çağırmağa məcbur edin.

### Necə İşləyir

```
1. "input_schema"-sı İSTƏDİYİNİZ OUTPUT STRUKTURU olan bir tool təyin edin
2. tool_choice = {"type": "tool", "name": "your_tool"} təyin edin
3. Claude schema-ya uyğun etibarlı JSON generasiya etməyə MƏCBUR EDİLİR
4. Strukturlu məlumatı tool_use.input-da alırsınız — artıq parse edilmiş

Bu niyə etibarlıdır:
  - Claude tool çağırışları üçün etibarlı JSON çıxarmağı öyrənib
  - Schema modelin fine-tuning-i tərəfindən maşın tərəfindən tətbiq edilir
  - Siz cavab mətnini parse etmirsiniz — parse edilmiş input obyektini istifadə edirsiniz
```

```php
$response = Anthropic::messages()->create([
    'model'      => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'tools' => [
        [
            'name'        => 'record_invoice',
            'description' => 'Çıxarılmış faktura məlumatlarını qeyd et',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'vendor_name'    => ['type' => 'string'],
                    'invoice_number' => ['type' => 'string'],
                    'total_amount'   => ['type' => 'number'],
                    'due_date'       => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                ],
                'required' => ['vendor_name', 'invoice_number', 'total_amount'],
            ],
        ],
    ],
    'tool_choice' => ['type' => 'tool', 'name' => 'record_invoice'],
    'messages' => [
        ['role' => 'user', 'content' => "Faktura məlumatlarını çıxar:\n\n{$invoiceText}"],
    ],
]);

// Məlumat artıq parse edilib — JSON decode-a ehtiyac yoxdur
$toolUse = collect($response->content)
    ->firstWhere('type', 'tool_use');

$data = (array) $toolUse->input;
// $data['vendor_name'], $data['total_amount'] və s.
```

### Schema Məhdudiyyətləri

```json
{
  "type": "object",
  "properties": {
    "status": {
      "type": "string",
      "enum": ["pending", "paid", "overdue"]
    },
    "amount": {
      "type": "number",
      "minimum": 0,
      "maximum": 1000000
    },
    "date": {
      "type": "string",
      "description": "ISO 8601 tarixi: YYYY-MM-DD"
    },
    "line_items": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "description": {"type": "string"},
          "quantity":    {"type": "number", "minimum": 0},
          "unit_price":  {"type": "number", "minimum": 0}
        },
        "required": ["description", "quantity", "unit_price"]
      }
    },
    "notes": {
      "type": ["string", "null"],
      "description": "Əlavə qeydlər, yoxdursa null"
    }
  },
  "required": ["status", "amount", "line_items"]
}
```

---

## Yanaşma 2: XML Parse etmə

Strukturlu outputu məhdudlamaq üçün XML sarma teqləri istifadə edin, sonra XML və ya regex ilə parse edin.

### XML-in Yaxşı İşləməsinin Səbəbi

```
1. Claude-un təlimi geniş miqdarda XML/HTML məzmununu əhatə edir
2. Modellər prompt edildikdə təbii olaraq düzgün formatlı XML istehsal edir
3. Qarışıq mətn outputundan JSON-dan daha asan çıxarmaq
4. Boşluq və kiçik formatlaşdırma problemlərinə qarşı daha tolerantlıdır
5. İÇ İÇƏ strukturlu məzmun üçün yaxşı işləyir

İstifadə pattern-i:
  "Bu formatda cavab verin:
   <result>
     <vendor_name>sətir</vendor_name>
     <total>rəqəm</total>
   </result>"
```

```php
$response = Anthropic::messages()->create([
    'model'    => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'system'   => 'Faktura məlumatlarını çıxarın. Yalnız göstərilən XML formatında cavab verin.',
    'messages' => [[
        'role'    => 'user',
        'content' => <<<PROMPT
            Bu fakturadan məlumatları çıxarın və tam olaraq bu formatda cavab verin:

            <invoice>
              <vendor_name>{sətir}</vendor_name>
              <invoice_number>{sətir}</invoice_number>
              <total_amount>{rəqəm, valyuta simvolu olmadan}</total_amount>
              <due_date>{YYYY-MM-DD formatı və ya boş}</due_date>
            </invoice>

            Faktura mətni:
            {$invoiceText}
            PROMPT,
    ]],
]);

$text = $response->content[0]->text;

// XML-i parse et
if (!preg_match('/<invoice>(.*?)<\/invoice>/s', $text, $matches)) {
    throw new \RuntimeException("Cavabda <invoice> bloku tapılmadı");
}

$xml = simplexml_load_string("<root><invoice>{$matches[1]}</invoice></root>");
$data = [
    'vendor_name'    => (string) $xml->invoice->vendor_name,
    'invoice_number' => (string) $xml->invoice->invoice_number,
    'total_amount'   => (float)  $xml->invoice->total_amount,
    'due_date'       => (string) $xml->invoice->due_date ?: null,
];
```

---

## Yanaşma 3: Assistant Prefilling

İlk simvoldan JSON outputunu məcbur etmək üçün assistant-ın cavabını açılış `{` ilə əvvəlcədən doldurun.

```php
$response = Anthropic::messages()->create([
    'model'    => 'claude-sonnet-4-6',
    'max_tokens' => 1024,
    'system'   => 'Siz faktura məlumatlarını çıxarırsınız. Yalnız etibarlı JSON çıxarın, başqa heç nə yox.',
    'messages' => [
        [
            'role'    => 'user',
            'content' => "Bu fakturanı çıxarın:\n\n{$invoiceText}",
        ],
        [
            'role'    => 'assistant',
            'content' => '{',  // ← PREFILL: Claude buradan davam edir
        ],
    ],
]);

// Cavab prefill-dən SONRA başlayır
// Etibarlı JSON yenidən qurmaq üçün '{'-i geri əlavə etməliyik
$jsonString = '{' . $response->content[0]->text;

$data = json_decode($jsonString, true);
```

**Qeyd**: Prefilling ilə, model tək üst səviyyəli obyekt gözlənilirsə `stop_sequences: ['}']` təyin edin ki, model düzgün dayansın.

---

## Yanaşma Müqayisəsi

| Yanaşma | Etibarlılıq | Token Xərci | Parse Mürəkkəbliyi | Ən Yaxşı Olduğu Yer |
|----------|-------------|------------|-------------------|----------|
| Tool use (məcburi) | ★★★★★ | Ən yüksək (schema tokenləri) | Heç biri (artıq parse edilib) | Mürəkkəb schema-lar, istehsal |
| XML parse etmə | ★★★★☆ | Orta | Aşağı | Iç içə məzmun, daha az kritik |
| Prefilling | ★★★★☆ | Ən aşağı | Orta (JSON-u yenidən qur) | Sadə schema-lar, xərcə həssas |
| Xam JSON prompt | ★★★☆☆ | Aşağı | Yüksək (doğrula + parse et) | Yalnız inkişaf |

---

## AI Çıxarma üçün Schema Dizaynı

### Dizayn Prinsipləri

```
1. "ÇIXARMA"-ni "DOĞRULAMA"-dan AYIRIN
   AI sənəddəkini çıxarır.
   Sizin kodunuz çıxarılmış məlumatı biznes qaydalarına qarşı doğrulayır.
   
   Pis schema: "amount": {"type": "number", "minimum": 1}
   Niyə pisdir: Fakturada $0.00 olsaydı? AI uğursuz olacaq və ya təxmin edəcək.
   
   Yaxşı schema: "amount": {"type": ["number", "null"]}
   Sonra PHP-də doğrulayın: if ($data['amount'] <= 0) { reject(); }

2. İXTİYARİ SAHƏLƏR ÜÇÜN NULL İCASƏ VERİN
   Sənəddə olmaya bilən sahələr üçün həmişə null-a icazə verin.
   Modelin görmədyi məlumatı icad etməsini heç vaxt tələb etməyin.
   
   "phone": {"type": ["string", "null"]}
   "notes": {"type": ["string", "null"]}

3. FORMATLANMIŞ DƏYƏRLƏR ÜÇÜN SƏTİRLƏRİ TƏRCİH EDİN
   Modelin tarix sətirləri çıxarmasına, sonra PHP-də parse etməyə icazə verin.
   "date": {"type": ["string", "null"], "description": "Tapıldığı kimi tarixi tam mətni"}
   
   Schema-da xüsusi tarix formatını məcbur etməyin — model "Jan 15, 2024"-ü
   "2024-01-15"-ə etibarlı şəkildə çevirə bilməyəcək.

4. ENUM-LARDAN DİQQƏTLİ İSTİFADƏ EDİN
   Enum-u yalnız modelin dəyəri həmişə seçimlərdən birinə
   təsnif edə biləcəyinə əmin olduğunuzda istifadə edin.
   
   "other" və ya "unknown" kimi bir catch-all daxil edin:
   "payment_method": {"enum": ["check", "wire", "credit_card", "unknown"]}

5. SAHƏ TƏSVİRLƏRİNDƏ ÇIXARMA MƏQSƏDİNİ AÇIQLAYIIN
   "net_amount": {
     "type": ["number", "null"],
     "description": "Vergidən əvvəlki cəm. 'Net', 'Subtotal', 'Before Tax' kimi etiketlərə baxın."
   }
```

---

## Deformasiyaya Uğramış Cavabların İdarə Edilməsi

Ən yaxşı təcrübələrlə belə, cavablar bəzən deformasiyaya uğraya bilər. Möhkəm sistem bunu incəlikli şəkildə idarə edir.

### Ümumi Uğursuzluq Pattern-ləri

```php
// Pattern 1: Markdown-a sarılmış JSON
$response = "```json\n{\"vendor\": \"Acme\"}\n```";

// Pattern 2: Əvvəl/sonra izahat olan JSON
$response = "Budur çıxarılmış məlumatlar:\n{\"vendor\": \"Acme\"}\nDaha çox lazım olsa bildirin.";

// Pattern 3: Arxadan vergüllü JSON (texniki olaraq yanlış)
$response = '{"vendor": "Acme", "total": 500,}';

// Pattern 4: Tək dırnaq əvəzinə cüt dırnaq
$response = "{'vendor': 'Acme', 'total': 500}";

// Pattern 5: Sətir kimi rəqəmlər
$response = '{"vendor": "Acme", "total": "500.00"}';
```

---

## Laravel: StructuredExtractor Sinifi

```php
<?php

declare(strict_types=1);

namespace App\AI\Extraction;

use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Log;

/**
 * Claude tool use istifadə edərək ümumi strukturlu məlumat çıxarıcı.
 *
 * Strukturlu output məcbur etmək üçün tool_choice istifadə edir, JSON
 * parsing-ə ehtiyacı aradan qaldırır və etibarlılığı əhəmiyyətli dərəcədə artırır.
 *
 * İstifadə:
 *   $extractor = new StructuredExtractor();
 *
 *   $data = $extractor->extract(
 *       text: $invoiceText,
 *       schema: InvoiceSchema::get(),
 *       instructions: "Bu sənəddən bütün faktura məlumatlarını çıxarın.",
 *   );
 */
class StructuredExtractor
{
    private const DEFAULT_TOOL_NAME = 'extract_data';

    /**
     * JSON Schema istifadə edərək mətnden strukturlu məlumatları çıxarın.
     *
     * @param  string  $text          Çıxarılacaq mətn
     * @param  array   $schema        Output strukturunu təyin edən JSON Schema
     * @param  string  $instructions  Əlavə çıxarma təlimatları
     * @param  string  $model         İstifadə ediləcək model
     * @return array  Schema-ya uyğun çıxarılmış məlumatlar
     */
    public function extract(
        string $text,
        array $schema,
        string $instructions = '',
        string $model = 'claude-sonnet-4-6',
        string $toolName = self::DEFAULT_TOOL_NAME,
    ): array {
        $tools = [
            [
                'name'         => $toolName,
                'description'  => "Təqdim olunan mətnden çıxarılmış strukturlu məlumatları qeyd edin. " .
                    "Mövcud olmayan və ya müəyyənləşdirilə bilməyən sahələr üçün null istifadə edin.",
                'input_schema' => $schema,
            ],
        ];

        $userMessage = $this->buildExtractionMessage($text, $instructions);

        $response = Anthropic::messages()->create([
            'model'       => $model,
            'max_tokens'  => 2048,
            'temperature' => 0.0,
            'system'      => $this->buildSystemPrompt(),
            'tools'       => $tools,
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
            'messages'    => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        return $this->extractFromResponse($response, $toolName);
    }

    /**
     * Tək API çağırışında bir neçə mətndən çıxarma (toplu rejim).
     *
     * Emal etmək üçün bir neçə qısa sənədiniz olduqda faydalıdır.
     *
     * @param  array<string>  $texts     Çıxarılacaq mətnlər massivi
     * @param  array          $schema    JSON Schema
     * @param  string         $toolName  Çıxarma tool-unun adı
     * @return array[]  Hər giriş mətni üçün bir çıxarılmış məlumatlar massivi
     */
    public function extractBatch(
        array $texts,
        array $schema,
        string $instructions = '',
        string $model = 'claude-haiku-4-5-20251001', // Toplu üçün Haiku istifadə edin (xərc)
    ): array {
        // Kiçik toplu üçün birlikdə emal et; böyük toplu üçün paralelləşdir
        if (count($texts) <= 3) {
            return $this->extractBatchSingleCall($texts, $schema, $instructions, $model);
        }

        // Parçalayın və emal edin
        $results = [];
        foreach (array_chunk($texts, 3, true) as $chunk) {
            $chunkResults = $this->extractBatchSingleCall(
                array_values($chunk),
                $schema,
                $instructions,
                $model
            );
            $results = array_merge($results, $chunkResults);
        }

        return $results;
    }

    private function extractBatchSingleCall(
        array $texts,
        array $schema,
        string $instructions,
        string $model,
    ): array {
        $results = [];
        foreach ($texts as $text) {
            $results[] = $this->extract($text, $schema, $instructions, $model);
        }
        return $results;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'SYSTEM'
        Siz dəqiq məlumat çıxarma mütəxəssisisiniz. Vəzifəniz mətn sənədlərindən strukturlu məlumatları çıxarmaqdır.

        Qaydalar:
        - YALNIZ sənəddə açıq şəkildə mövcud olan məlumatları çıxarın
        - Mövcud olmayan və ya müəyyənləşdirilə bilməyən sahələr üçün null istifadə edin
        - Aydın şəkildə ifadə olunmayan dəyərləri çıxarmayın və ya təxmin etməyin
        - Pul dəyərləri üçün valyuta simvolları olmadan rəqəm kimi çıxarın
        - Tarixlər üçün mümkün olduqda ISO 8601 formatında çıxarın (YYYY-MM-DD)
        - Bir sahə bir neçə dəfə görünürsə, ən görkəmli/son dəyəri istifadə edin
        SYSTEM;
    }

    private function buildExtractionMessage(string $text, string $instructions): string
    {
        $prompt = '';

        if ($instructions) {
            $prompt .= "<instructions>\n{$instructions}\n</instructions>\n\n";
        }

        $prompt .= "<document>\n{$text}\n</document>";

        return $prompt;
    }

    private function extractFromResponse(object $response, string $toolName): array
    {
        $toolUseBlock = collect($response->content)
            ->firstWhere('type', 'tool_use');

        if (!$toolUseBlock) {
            Log::error('StructuredExtractor: Cavabda tool_use bloku yoxdur', [
                'stop_reason' => $response->stopReason,
                'content'     => json_encode($response->content),
            ]);
            throw new ExtractionException(
                "Model çıxarma tool-unu çağırmadı. Dayanma səbəbi: {$response->stopReason}"
            );
        }

        if ($toolUseBlock->name !== $toolName) {
            throw new ExtractionException(
                "Model yanlış tool çağırdı: {$toolUseBlock->name} (gözlənilən {$toolName})"
            );
        }

        return (array) $toolUseBlock->input;
    }
}

class ExtractionException extends \RuntimeException {}
```

---

## Laravel: Eloquent Model Hidrasiyası

```php
<?php

declare(strict_types=1);

namespace App\AI\Extraction;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use Illuminate\Support\Facades\DB;

/**
 * AI tərəfindən çıxarılmış məlumatlardan Eloquent modelləri hidratlayır.
 *
 * Tip məcburluğunu, doğrulamanı və AI output tipləri ilə
 * verilənlər bazası tipləri arasındakı uyğunsuzluğu idarə edir.
 */
class InvoiceHydrator
{
    private const INVOICE_SCHEMA = [
        'type'       => 'object',
        'properties' => [
            'vendor_name' => [
                'type'        => 'string',
                'description' => 'Faktura göndərəninin şirkət və ya fərd adı.',
            ],
            'vendor_email' => [
                'type'        => ['string', 'null'],
                'description' => 'Satıcının e-poçt ünvanı, yoxdursa null.',
            ],
            'invoice_number' => [
                'type'        => ['string', 'null'],
                'description' => 'Faktura istinad nömrəsi, ID-si və ya kodu.',
            ],
            'invoice_date' => [
                'type'        => ['string', 'null'],
                'description' => 'Fakturanın verildiyi tarix. YYYY-MM-DD formatında çıxarın.',
            ],
            'due_date' => [
                'type'        => ['string', 'null'],
                'description' => 'Ödəniş son tarixi. YYYY-MM-DD formatında çıxarın.',
            ],
            'currency' => [
                'type'        => 'string',
                'description' => 'Valyuta kodu (USD, EUR, GBP, və s.). Göstərilmədikdə USD-yə default.',
                'default'     => 'USD',
            ],
            'subtotal' => [
                'type'        => ['number', 'null'],
                'description' => 'Vergi və endirimlərdən əvvəl cəm. Yalnız rəqəm dəyəri.',
            ],
            'tax_amount' => [
                'type'        => ['number', 'null'],
                'description' => 'Ümumi vergi məbləği. Yalnız rəqəm dəyəri.',
            ],
            'total_amount' => [
                'type'        => 'number',
                'description' => 'Son ödənilməsi lazım olan məbləğ. Yalnız rəqəm dəyəri.',
            ],
            'line_items' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'description' => ['type' => 'string'],
                        'quantity'    => ['type' => ['number', 'null']],
                        'unit_price'  => ['type' => ['number', 'null']],
                        'total'       => ['type' => 'number'],
                    ],
                    'required' => ['description', 'total'],
                ],
            ],
        ],
        'required' => ['vendor_name', 'total_amount', 'line_items'],
    ];

    public function __construct(
        private readonly StructuredExtractor $extractor,
    ) {}

    /**
     * Faktura məlumatlarını çıxarın və sətir elementləri ilə Invoice modeli yaradın.
     */
    public function hydrateFromText(string $invoiceText, int $uploadedBy): Invoice
    {
        // Strukturlu məlumatları çıxar
        $data = $this->extractor->extract(
            text: $invoiceText,
            schema: self::INVOICE_SCHEMA,
            instructions: 'Bütün faktura məlumatlarını çıxarın. Sətir elementləri bütün fərdi ödənişləri, xidmətləri və ya məhsulları əhatə etməlidir.',
        );

        return DB::transaction(function () use ($data, $uploadedBy) {
            // Fakturanı yarat
            $invoice = Invoice::create([
                'vendor_name'    => $data['vendor_name'],
                'vendor_email'   => $data['vendor_email'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date'   => $this->parseDate($data['invoice_date'] ?? null),
                'due_date'       => $this->parseDate($data['due_date'] ?? null),
                'currency'       => strtoupper($data['currency'] ?? 'USD'),
                'subtotal'       => $this->parseAmount($data['subtotal'] ?? null),
                'tax_amount'     => $this->parseAmount($data['tax_amount'] ?? null),
                'total_amount'   => $this->parseAmount($data['total_amount']),
                'status'         => 'pending',
                'uploaded_by'    => $uploadedBy,
                'ai_extracted'   => true,
                'raw_extraction' => $data, // Audit üçün xam çıxarmanı saxla
            ]);

            // Sətir elementlərini yarat
            $lineItems = $data['line_items'] ?? [];
            foreach ($lineItems as $item) {
                InvoiceLineItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $item['description'],
                    'quantity'    => isset($item['quantity']) ? (float) $item['quantity'] : null,
                    'unit_price'  => isset($item['unit_price']) ? (float) $item['unit_price'] : null,
                    'total'       => (float) ($item['total'] ?? 0),
                ]);
            }

            return $invoice->load('lineItems');
        });
    }

    private function parseDate(?string $dateString): ?\DateTimeImmutable
    {
        if (!$dateString) {
            return null;
        }

        // Əvvəlcə ISO formatını sınayın
        try {
            return new \DateTimeImmutable($dateString);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseAmount(float|int|string|null $amount): ?float
    {
        if ($amount === null) {
            return null;
        }

        if (is_string($amount)) {
            // Valyuta simvollarını və formatlaşdırmanı silin
            $cleaned = preg_replace('/[^\d.]/', '', $amount);
            return $cleaned ? (float) $cleaned : null;
        }

        return (float) $amount;
    }
}
```

---

## Laravel: Deformasiyaya Uğramış JSON üçün Yenidən Cəhd Mexanizmi

```php
<?php

declare(strict_types=1);

namespace App\AI\Extraction;

use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Support\Facades\Log;

/**
 * Yenidən cəhd və düzəltmə mexanizmlərini olan möhkəm JSON çıxarıcı.
 *
 * Tool use istifadə edə bilmədiyiniz hallarda (məs., streaming,
 * və ya tool use-i yaxşı dəstəkləməyən modellər).
 */
class RobustJsonExtractor
{
    private const MAX_REPAIR_ATTEMPTS = 2;

    /**
     * Model cavabından JSON çıxarın, uğursuzluqda avtomatik düzəltmə ilə.
     *
     * @param  string  $prompt   Çıxarma prompt-u
     * @param  array   $schema   Gözlənilən schema (düzəltmə prompt-u üçün)
     * @param  string  $model    İstifadə ediləcək model
     * @return array  Parse edilmiş məlumatlar
     */
    public function extract(
        string $prompt,
        array $schema = [],
        string $model = 'claude-sonnet-4-6',
    ): array {
        $response = Anthropic::messages()->create([
            'model'       => $model,
            'max_tokens'  => 2048,
            'temperature' => 0.0,
            'system'      => 'Siz məlumat çıxarma köməkçisisiniz. Həmişə yalnız etibarlı JSON ilə cavab verin. İzahatlar, markdown kod blokları yox, yalnız xam JSON.',
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
                ['role' => 'assistant', 'content' => '{'], // Prefill
            ],
        ]);

        $rawText = '{' . $response->content[0]->text;

        // Olduğu kimi parse etməyə çalışın
        $parsed = $this->tryParse($rawText);
        if ($parsed !== null) {
            return $parsed;
        }

        Log::warning('RobustJsonExtractor: İlkin parse uğursuz oldu, düzəltmə cəhd edilir', [
            'raw_text' => substr($rawText, 0, 200),
        ]);

        // Təmizləmə texnikalarını sınayın
        $cleaned = $this->cleanupJson($rawText);
        $parsed = $this->tryParse($cleaned);
        if ($parsed !== null) {
            return $parsed;
        }

        // Claude-dan JSON-u düzəltməsini xahiş edin
        return $this->repairWithClaude($rawText, $schema, $model);
    }

    /**
     * JSON parse etməyə cəhd edin, uğursuzluqda null qaytarın.
     */
    private function tryParse(string $json): ?array
    {
        // Birbaşa parse cəhd et
        try {
            $result = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            return is_array($result) ? $result : null;
        } catch (\JsonException) {}

        return null;
    }

    /**
     * Ümumi təmizləmə transformasiyalarını tətbiq edin.
     */
    private function cleanupJson(string $raw): string
    {
        // Markdown kod bloklarını silin
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned);

        // Qarışıq mətndən JSON çıxarın
        if (preg_match('/(\{.*\})/s', $cleaned, $m)) {
            $cleaned = $m[1];
        }

        // Arxadan vergülləri düzəldin (JSON-da etibarsız)
        $cleaned = preg_replace('/,\s*([}\]])/s', '$1', $cleaned);

        // Tək dırnaqları cüt dırnaqlara çevirin (dəyərlərdəki apostroflarla diqqətli olun)
        // Yalnız açar dırnaqlarını əvəz edin, dəyər dırnaqlarını yox
        $cleaned = preg_replace("/(?<=[{,\[])(\s*)'([^']+)'\s*:/", '$1"$2":', $cleaned);

        return trim($cleaned);
    }

    /**
     * Etibarsız JSON-u düzəltmək üçün Claude-dan xahiş edin.
     */
    private function repairWithClaude(
        string $brokenJson,
        array $schema,
        string $model,
    ): array {
        for ($attempt = 1; $attempt <= self::MAX_REPAIR_ATTEMPTS; $attempt++) {
            $schemaDescription = empty($schema)
                ? ''
                : "\n\nGözlənilən schema:\n" . json_encode($schema, JSON_PRETTY_PRINT);

            $repairResponse = Anthropic::messages()->create([
                'model'       => 'claude-haiku-4-5-20251001', // Düzəltmə üçün ucuz model istifadə edin
                'max_tokens'  => 2048,
                'temperature' => 0.0,
                'system'      => 'Siz JSON düzəltmə köməkçisisiniz. Verilmiş JSON-u düzəldin və YALNIZ düzəldilmiş JSON-u qaytarın, başqa heç nə yox.',
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => "Bu etibarsız JSON-u düzəldin və yalnız düzəldilmiş versiyasını qaytarın:{$schemaDescription}\n\nEtibarsız JSON:\n{$brokenJson}",
                    ],
                    ['role' => 'assistant', 'content' => '{'],
                ],
            ]);

            $repairedText = '{' . $repairResponse->content[0]->text;
            $parsed = $this->tryParse($repairedText);

            if ($parsed !== null) {
                Log::info('RobustJsonExtractor: JSON düzəldildi', ['attempt' => $attempt]);
                return $parsed;
            }
        }

        throw new JsonExtractionException(
            "Düzəltmə cəhdlərindən sonra etibarlı JSON çıxarmaq uğursuz oldu. " .
            "Xam cavab: " . substr($brokenJson, 0, 500)
        );
    }
}

class JsonExtractionException extends \RuntimeException {}
```

---

## Real Nümunə: Faktura Məlumatı DTO

```php
<?php

declare(strict_types=1);

namespace App\AI\Extraction\DTOs;

/**
 * Çıxarılmış faktura məlumatları üçün güclü tipli DTO.
 *
 * PHP 8.2 readonly xassələri dəyişməzliyi təmin edir.
 * Statik fabrika metodu AI output-dan tip məcburluğunu idarə edir.
 */
readonly class InvoiceExtractionDTO
{
    public function __construct(
        public string $vendorName,
        public ?string $vendorEmail,
        public ?string $vendorPhone,
        public ?string $vendorAddress,
        public ?string $invoiceNumber,
        public ?\DateTimeImmutable $invoiceDate,
        public ?\DateTimeImmutable $dueDate,
        public string $currency,
        /** @var LineItemDTO[] */
        public array $lineItems,
        public float $subtotal,
        public ?float $taxAmount,
        public ?float $discountAmount,
        public float $totalAmount,
        public ?string $paymentTerms,
        public ?string $notes,
        public ExtractionConfidence $confidence,
    ) {}

    /**
     * Xam AI çıxarma output-undan DTO yarat.
     *
     * @param  array  $data  AI tool_use.input-dan xam massiv
     */
    public static function fromAIOutput(array $data): static
    {
        $lineItems = array_map(
            fn (array $item) => LineItemDTO::fromAIOutput($item),
            $data['line_items'] ?? []
        );

        $confidence = static::assessConfidence($data, $lineItems);

        return new static(
            vendorName:     static::requireString($data, 'vendor_name'),
            vendorEmail:    static::optionalEmail($data, 'vendor_email'),
            vendorPhone:    static::optionalString($data, 'vendor_phone'),
            vendorAddress:  static::optionalString($data, 'vendor_address'),
            invoiceNumber:  static::optionalString($data, 'invoice_number'),
            invoiceDate:    static::optionalDate($data, 'invoice_date'),
            dueDate:        static::optionalDate($data, 'due_date'),
            currency:       strtoupper(static::optionalString($data, 'currency') ?: 'USD'),
            lineItems:      $lineItems,
            subtotal:       static::optionalFloat($data, 'subtotal') ?? 0.0,
            taxAmount:      static::optionalFloat($data, 'tax_amount'),
            discountAmount: static::optionalFloat($data, 'discount_amount'),
            totalAmount:    static::requireFloat($data, 'total_amount'),
            paymentTerms:   static::optionalString($data, 'payment_terms'),
            notes:          static::optionalString($data, 'notes'),
            confidence:     $confidence,
        );
    }

    /**
     * Verilənlər bazasında saxlamaq üçün massivə çevir.
     */
    public function toStorageArray(): array
    {
        return [
            'vendor_name'     => $this->vendorName,
            'vendor_email'    => $this->vendorEmail,
            'vendor_phone'    => $this->vendorPhone,
            'vendor_address'  => $this->vendorAddress,
            'invoice_number'  => $this->invoiceNumber,
            'invoice_date'    => $this->invoiceDate?->format('Y-m-d'),
            'due_date'        => $this->dueDate?->format('Y-m-d'),
            'currency'        => $this->currency,
            'subtotal'        => $this->subtotal,
            'tax_amount'      => $this->taxAmount,
            'discount_amount' => $this->discountAmount,
            'total_amount'    => $this->totalAmount,
            'payment_terms'   => $this->paymentTerms,
            'notes'           => $this->notes,
            'confidence'      => $this->confidence->value,
            'line_item_count' => count($this->lineItems),
        ];
    }

    /**
     * Çıxarmanın tamlığına əsasən inam qiymətləndir.
     */
    private static function assessConfidence(array $data, array $lineItems): ExtractionConfidence
    {
        $score = 0;
        $maxScore = 10;

        if (!empty($data['vendor_name'])) $score += 2;
        if (!empty($data['invoice_number'])) $score += 2;
        if (!empty($data['invoice_date'])) $score += 1;
        if (!empty($data['due_date'])) $score += 1;
        if (isset($data['total_amount'])) $score += 2;
        if (!empty($lineItems)) $score += 1;
        if (count($lineItems) > 0) $score += 1;

        return match (true) {
            $score >= 8 => ExtractionConfidence::High,
            $score >= 5 => ExtractionConfidence::Medium,
            default     => ExtractionConfidence::Low,
        };
    }

    // Tip məcburluğu köməkçiləri
    private static function requireString(array $data, string $key): string
    {
        $val = $data[$key] ?? null;
        if (!$val || !is_string($val)) {
            throw new \InvalidArgumentException("Məcburi sahə '{$key}' çatışmır və ya etibarsızdır");
        }
        return trim($val);
    }

    private static function requireFloat(array $data, string $key): float
    {
        $val = $data[$key] ?? null;
        if ($val === null) {
            throw new \InvalidArgumentException("Məcburi sahə '{$key}' çatışmır");
        }
        return (float) $val;
    }

    private static function optionalString(array $data, string $key): ?string
    {
        $val = $data[$key] ?? null;
        return ($val && is_string($val)) ? trim($val) : null;
    }

    private static function optionalFloat(array $data, string $key): ?float
    {
        $val = $data[$key] ?? null;
        return $val !== null ? (float) $val : null;
    }

    private static function optionalEmail(array $data, string $key): ?string
    {
        $val = static::optionalString($data, $key);
        if ($val && filter_var($val, FILTER_VALIDATE_EMAIL)) {
            return $val;
        }
        return null;
    }

    private static function optionalDate(array $data, string $key): ?\DateTimeImmutable
    {
        $val = static::optionalString($data, $key);
        if (!$val) return null;

        try {
            return new \DateTimeImmutable($val);
        } catch (\Exception) {
            return null;
        }
    }
}

readonly class LineItemDTO
{
    public function __construct(
        public string $description,
        public ?float $quantity,
        public ?float $unitPrice,
        public float $total,
        public ?float $taxRate,
    ) {}

    public static function fromAIOutput(array $data): static
    {
        return new static(
            description: $data['description'] ?? '',
            quantity:    isset($data['quantity']) ? (float) $data['quantity'] : null,
            unitPrice:   isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            total:       (float) ($data['total'] ?? 0),
            taxRate:     isset($data['tax_rate']) ? (float) $data['tax_rate'] : null,
        );
    }
}

enum ExtractionConfidence: string
{
    case High   = 'high';
    case Medium = 'medium';
    case Low    = 'low';
}
```

### Controller-da Tam İstifadə

```php
<?php

namespace App\Http\Controllers;

use App\AI\Extraction\{InvoiceHydrator, StructuredExtractor};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceProcessingController extends Controller
{
    public function __construct(
        private readonly InvoiceHydrator $hydrator,
    ) {}

    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_text' => 'required|string|min:50',
        ]);

        try {
            $invoice = $this->hydrator->hydrateFromText(
                invoiceText: $request->input('invoice_text'),
                uploadedBy: $request->user()->id,
            );

            return response()->json([
                'success'    => true,
                'invoice_id' => $invoice->id,
                'data'       => [
                    'vendor'         => $invoice->vendor_name,
                    'total'          => $invoice->total_amount,
                    'line_items'     => $invoice->lineItems->count(),
                    'confidence'     => $invoice->raw_extraction['confidence'] ?? 'unknown',
                    'needs_review'   => ($invoice->raw_extraction['confidence'] ?? '') === 'low',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Faktura emal edilə bilmədi: ' . $e->getMessage(),
            ], 422);
        }
    }
}
```

---

## Performans və Xərc Optimizasiyası

```
ÇIXARMA ÜÇÜN XƏRc MÜQAYİSƏSİ:

Tool use (məcburi schema):
  +: Ən etibarlı, parsing kodu yoxdur
  -: Schema tokenləri sorğu başına ~200-500 token əlavə edir
  Xərc: Schema üçün sorğu başına Input tokenləri × $0.003 əlavə

XML yanaşması:
  +: Schema yükü yoxdur
  +: Iç içə/mürəkkəb output üçün yaxşı işləyir
  -: Regex/XML parsing kodu tələb edir
  Xərc: Yük yoxdur

Prefilling yanaşması:
  +: Ən aşağı yük
  -: JSON-u yenidən qurmaq lazımdır
  Xərc: 1-2 token qənaət edir (əhəmiyyətsiz)

TÖVSİYƏ:
  Tool use istifadə edin:
  - Mürəkkəb schema-lar (>5 sahə)
  - Yüksək etibarlılıq tələbləri
  - İstehsal məlumat pipeline-ları

  Prefilling + təmizləmə istifadə edin:
  - Sadə schema-lar (1-3 sahə)
  - Xərcə həssas yüksək həcmli tapşırıqlar
  - Model Haiku ilə çağrıldıqda (daha ucuz)

TOPLU OPTİMİZASİYA:
  Sənəd emalı pipeline-ları üçün:
  1. İlk çıxarma üçün Haiku istifadə edin (5x daha ucuz)
  2. Aşağı inamlı çıxarmaları işarələyin
  3. İşarələnənləri Sonnet ilə yenidən işləyin
  4. Yalnız Sonnet uğursuzluqları üçün insan nəzərdən keçirməsi

  Tipik nəticə:
  Haiku tərəfindən emal edilən 90%:  ~$0.001/sənəd
  Sonnet tərəfindən yenidən emal edilən 9%: ~$0.01/sənəd
  İnsan nəzərdən keçirməsi 1%:           ~$0.50/sənəd
  Çəkili ortalama:          ~$0.007/sənəd
  Hamısı Sonnet:             ~$0.01/sənəd
  Eyni keyfiyyətlə ~30% qənaət
```

---

*Əvvəlki: [08 — Tool Use](./08-tool-use.md) | Geri: [İndeks](../README.md)*
