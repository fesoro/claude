# Multimodal AI — Vizyon, Sənədlər və Daha Çox (Middle)

> AI modellərinin şəkilləri və sənədləri necə emal etdiyini, Claude-un vizyon imkanlarının praktikada necə göründüyünü və real dünya istifadə halları üçün produksiyaya hazır Laravel kodunu dərinlemesine araşdırma.

---

## Mündəricat

1. [Multimodal AI Nədir?](#multimodal-ai-nədir)
2. [Vision Transformer-lər (ViT) — AI Necə Görür](#vision-transformerlər)
3. [Vizyonu Dilə Bağlamaq](#vizyonu-dilə-bağlamaq)
4. [Model İmkanları Matrisi](#model-imkanları-matrisi)
5. [Claude-un Vizyon İmkanları](#claudeun-vizyon-imkanları)
6. [Şəkil Token Qiyməti və Limitlər](#şəkil-token-qiyməti-və-limitlər)
7. [Vizyon Prompt-ları üçün Ən Yaxşı Praktikalar](#vizyon-promptları-üçün-ən-yaxşı-praktikalar)
8. [Laravel: Yüklənmiş Şəkilin Analizi](#laravel-yüklənmiş-şəkilin-analizi)
9. [Laravel: PDF Fakturasından Strukturlu Məlumat Çıxarımı](#laravel-pdf-fakturasından-strukturlu-məlumat-çıxarımı)
10. [Laravel: İstifadəçi Yüklənmiş Şəkillərin Xəta Emalı ilə İşlənməsi](#laravel-istifadəçi-yüklənmiş-şəkillərin-xəta-emalı-ilə-işlənməsi)
11. [Ümumi Uğursuzluq Halları](#ümumi-uğursuzluq-halları)
12. [Arxitekt Mülahizələri](#arxitekt-mülahizələri)

---

## Multimodal AI Nədir?

Multimodal AI modellər yalnız mətn deyil, birdən çox giriş növünü (modalite) emal edə bilir. Cari modalite mənzərəsi:

```
MODALİTƏ        CARİ VƏZİYYƏT          NÜMUNƏLƏRİ
────────────────────────────────────────────────────────
Mətn            Yetkin, geniş yayılmış  Bütün modellər
Şəkillər        Produksiyaya hazır      Claude, GPT-4o, Gemini
PDF-lər         Şəkil çevrilməsi ilə    Claude, Gemini
Audio (giriş)   Mövcuddur              GPT-4o, Gemini
Audio (çıxış)   Mövcuddur              GPT-4o
Video           Mövcuddur              Gemini 1.5 Pro
Strukturlu data Bəzi modellərə xasdır  Gemini
3D/Nöqtə buludu Tədqiqat mərhələsi     —
```

Arxitektura çətinliyi: tamamilə fərqli məlumat növlərini (piksellər vs. tokenlər) tək modeldə necə birləşdirirsiniz?

---

## Vision Transformer-lər

### Şəkillər Tokenlərə Necə Çevrilir

LLM-lərdə vizyon üçün dominant yanaşma proyeksiya qatı ilə birlikdə **Vision Transformer (ViT)**-dir:

```
ADDIM 1: PATCH ÇIXARIMI
  Giriş şəkli: 1024 × 768 piksel

  Sabit ölçülü patch-lərə bölün (məs., 14×14 və ya 16×16 piksel):
  1024/14 × 768/14 = 73 × 54 = 3,942 patch

  Hər patch = kiçik bir şəkil kaşası

ADDIM 2: PATCH EMBEDDINGI
  Hər patch (14×14 = 196 piksel × 3 rəng kanalı = 588 dəyər)
  düzləndirilir və d_model embedding vektoruna proyeksiya edilir.

  Bu, öyrənilmiş xətti qat tərəfindən edilir (şəkillər üçün öyrənilmiş
  tokenizer kimi, lakin diskret tokenlər əvəzinə çıxış davamlı
  embedding vektorlarıdır).

ADDIM 3: POZİSYON KODLAŞDIRMASİ
  Modelin hər patch-in şəkildə harada olduğunu bilməsi üçün
  hər patch embedding-ə 2D pozisyon məlumatı əlavə edin.

ADDIM 4: VİZYON TRANSFORMER QATLARI
  Patch embedding-ləri transformer bloklarından keçirin.
  Özünə diqqət patch-lərin bir-birinə diqqət yetirməsinə icazə verir.
  Nəticə: kontekstualizasiya edilmiş patch təsvirləri.

ADDIM 5: MƏTİN EMBEDDİNG FƏZASİNA PROYEKSIYA
  Öyrənilmiş proyeksiya (MLP) vizyon embedding-lərini
  mətn tokeni embedding-ləri ilə eyni vektor fəzasına çevirir.
  İndi şəkil patch-ləri və mətn tokenləri eyni fəzada yaşayır
  və bir-birinə diqqət yetirə bilir.

ADDIM 6: BİRLƏŞİK EMAL
  Dil modeli qarışıq mətn və şəkil tokenləri emal edir.
  [mətn tokeni 1] [mətn tokeni 2] [şəkil patch 1] [şəkil patch 2] ...
  Diqqət mətn və şəkil təsvirləri arasında sərbəst axır.
```

### Bu Niyə İşləyir

Proyeksiya addımı əsas anlayışdır. Şəkil patch-ləri mətn embedding fəzasına proyeksiya edildikdə, transformer hər ikisine eyni özünə diqqət mexanizmini tətbiq edə bilir. Model milyardlarla şəkil-mətn cütlərindən ibarət ön öyrənmə zamanı vizual və mətnli anlayışlar arasındakı korrelyasiyaları öyrənir.

```
Modelin öyrəndiklərinin nümunəsi:
  Yolun küncündəki qırmızı səkkizbuçağı göstərən patch
  ardıcıl olaraq "DAYANMA işarəsi" mətnilə cütləşdirilir
  
  Terminaldakı "/usr/bin/python3"-ü göstərən patch
  "Python interpreter yolu" ilə əlaqələndirilir
  
  Qolda göyərtini göstərən patch
  "kontuziya", "travma", tibbi təsvirlərlə korrelyasiya edir
```

---

## Vizyonu Dilə Bağlamaq

Multimodal modellər üçün iki əsas arxitektura yanaşması var:

### Yanaşma 1: Erkən Birləşmə (Başdan-Sona)
Vizyon kodlayıcısı başdan dil modeli ilə birgə öyrədilir. Şəkil və mətn təsvirləri emalın əvvəlindən birləşdirilir.

```
İstifadə edir: GPT-4o (qismən), Gemini (yerli çoxmodallı)
Üstünlüklər: Sıx inteqrasiya, daha yaxşı çarpaz-modal əsaslandırma
Çatışmazlıqlar: Öyrənmək bahalıdır, böyük çoxmodallı datasetlər tələb edir
```

### Yanaşma 2: Adapter ilə Geç Birləşmə
Əvvəlcədən öyrədilmiş vizyon kodlayıcısı proyeksiya qatı ("adapter") vasitəsilə əvvəlcədən öyrədilmiş dil modeli ilə bağlanır. Yalnız adapter və bəzən hər iki komponent çoxmodallı datada incə tənzimlənir.

```
İstifadə edir: LLaVA, bir çox açıq mənbəli modellər
Üstünlüklər: Mövcud güclü unimodal modellərdən istifadə edə bilər
Çatışmazlıqlar: Təsvirləri tam inteqrasiya etməyə bilər

Arxitektura:
  Əvvəlcədən öyrədilmiş ViT → [Adapter / MLP] → Əvvəlcədən öyrədilmiş LLM
                                ↑ başlanğıcda öyrədiləni budur
```

Claude-un dəqiq arxitekturası ictimaiyyətə açıqlanmayıb, lakin dil modeli fəzasına proyeksiya edilmiş embedding-lər yaradan vizyon kodlayıcısından istifadə edir.

---

## Model İmkanları Matrisi

| Model | Şəkillər | PDF-lər | Audio Giriş | Audio Çıxış | Video | Sorğu Başına Maks Şəkillər |
|-------|--------|------|----------|-----------|-------|-------------------|
| Claude Haiku 4.5 | Bəli | Bəli* | Xeyr | Xeyr | Xeyr | 20 |
| Claude Sonnet 4.6 | Bəli | Bəli* | Xeyr | Xeyr | Xeyr | 20 |
| Claude Opus 4.6 | Bəli | Bəli* | Xeyr | Xeyr | Xeyr | 20 |
| GPT-4o | Bəli | Bəli* | Bəli | Bəli | Xeyr | 10 |
| GPT-4o-mini | Bəli | Xeyr | Xeyr | Xeyr | Xeyr | 10 |
| Gemini 1.5 Pro | Bəli | Bəli | Bəli | Xeyr | Bəli | 16 |
| Gemini 1.5 Flash | Bəli | Bəli | Bəli | Xeyr | Bəli | 16 |
| LLaMA 3.2 Vision | Bəli | Xeyr | Xeyr | Xeyr | Xeyr | 1 |

*PDF dəstəyi şəkil çevrilməsi ilə (hər səhifə şəkilə çevrilir)

---

## Claude-un Vizyon İmkanları

Claude-un vizyonu geniş çeşidli tapşırıqlar üçün produksiya keyfiyyətindədir:

### Claude-un Yaxşı Edə Bildikləri

```
SƏNƏD ANALİZİ:
  ✓ Şəkillərdən mətn oxuyun (OCR keyfiyyəti)
  ✓ Cədvəllər, formalar, fakturalar, qəbzləri analiz edin
  ✓ Skan edilmiş sənədlərdən strukturlu məlumat çıxarın
  ✓ Sənəd sxemini anlayın (başlıqlar, bölmələr, qeydlər)
  ✓ Əl ilə yazılmış mətni oxuyun (orta dəqiqliklə)

VİZUAL ANLAYIŞ:
  ✓ Şəkilləri ətraflı təsvir edin
  ✓ Obyektləri, insanları, səhnələri müəyyən edin
  ✓ Qrafiklər, diaqramlar oxuyun
  ✓ Texniki diaqramları anlayın (UML, sxemlər, elektrik dövrəsi diaqramları)
  ✓ Kod ekran görüntülərini oxuyun
  ✓ UI/UX ekran görüntülərini analiz edin

VİZUALLAR HAQQINDA ƏSASLANDIRMA:
  ✓ "Bu xəta mesajında nə yanlışdır?"
  ✓ "Bu qrafikdə hansı elementin ən yüksək dəyəri var?"
  ✓ "Bu faktura bu satınalma sifarişinə uyğundurmu?"
  ✓ "Bu UI-ın hansı əlçatımlılıq problemləri var?"
```

### Claude-un Edə Bilmədikləri

```
  ✗ Şəkil generasiyası (yalnız mətn çıxışı)
  ✗ Video kadrlarını emal etmə (kadrları əl ilə çıxarmaq lazımdır)
  ✗ Audio emal etmə
  ✗ Dəqiq piksel səviyyəsindəki lokalizasiya ("düymə x=342, y=156-dadır")
  ✗ Dəqiq tibbi görüntü diaqnozu (bunun üçün öyrədilməyib)
  ✗ Etibarlı üz tanıma/müəyyənləşdirmə
  ✗ Çox kiçik və ya sıxılmış mətni etibarlı oxuma
```

---

## Şəkil Token Qiyməti və Limitlər

Claude şəkillər üçün **kaşa əsaslı qiymətləndirmə modeli** istifadə edir:

```
CLAUDE ŞƏKİL QİYMƏTLƏNDİRMƏSİ:

1. Şəkil 1568×1568 pikseldən böyük olmamaq üçün yenidən ölçüləndirilir
   (aspekt nisbəti qorunur)
   VƏ qısa kənar ən az 200 piksel olmalıdır

2. Şəkil 512×512 piksel kaşalara bölünür

3. Hər kaşa ~1500 token dəyərindədir ("əsas" kaşa)
   Şəkil başına sabit yük ~1500 token

4. Ümumi tokenlər = (kaşa_sayı + 1) × 1500

Nümunələr:
  200×200 şəkil:    1 kaşa  → 3,000 token  (Sonnet ilə ~$0.009)
  800×600 şəkil:    4 kaşa  → 7,500 token  (~$0.022)
  1568×1568 şəkil: 9 kaşa  → 15,000 token (~$0.045)

LİMİTLƏR:
  Maks şəkil ölçüsü: 8,192 × 8,192 piksel (yenidən ölçüləndirilir)
  Maks fayl ölçüsü:  Şəkil başına 5 MB (base64 kodlaşdırılmış)
  Sorğu başına maks şəkil sayı: 20
  Dəstəklənən formatlar: JPEG, PNG, GIF, WEBP

XƏRCLƏRİ OPTİMALLAŞDIRMA:
  Göndərməzdən əvvəl şəkilləri yenidən ölçüləndirin — piksel kaşaları üçün ödəyirsiniz.
  800×600 adətən sənəd analizi üçün kifayət edir.
  1568×1568 yalnız incə detal üçün lazımdır (texniki diaqramlar).
```

---

## Vizyon Prompt-ları üçün Ən Yaxşı Praktikalar

```
1. NƏ ÇIXARACAĞINIZI KONKRET DEYİN
   Pis:  "Bu şəkildə nə var?"
   Yaxşı: "Bu fakturadan aşağıdakıları çıxarın: (1) satıcı adı,
          (2) faktura nömrəsi, (3) miqdar və qiymətlərlə sıra elementləri,
          (4) ödənilməli ümumi məbləğ, (5) ödəniş son tarixi."

2. MODELƏ ŞƏKİL NÖVÜNÜ DEYİN
   Yaxşı: "Bu xəta logunun ekran görüntüsüdür."
         "Bu skan edilmiş tibbi formdur."
         "Bu sistem dizayn sessiyasından ağ lövhə diaqramıdır."
   Niyə:  Məzmun üçün gözləntiləri müəyyən edir və şərhi təsir edir.

3. STRUKTURLU ÇIXIŞ ÜÇÜN XML ETİKETLƏRİ İSTİFADƏ EDİN
   Pis:  "Məlumatı JSON formatında çıxar"
   Yaxşı: "Çıxarılmış məlumatı <invoice> XML etikətləri arasında JSON kimi çıxar."

4. QEYRI-AÇIQ MƏZMUN ÜÇÜN EMAL TƏYİN EDİN
   "Hər hansı mətn qeyri-açıq və ya oxunmaz olduqda, [OXUNMUR] kimi göstərin.
    Aydın oxuya bilmədiyiniz dəyərləri təxmin etməyin."

5. ÇOX SƏHIFƏLI SƏNƏDLƏR ÜÇÜN SƏHİFƏLƏRİ NÖMRƏLƏYIN
   Hər səhifəni mətn etiketi ilə ayrı şəkil kimi göndərin:
   [{"type": "text", "text": "Səhifə 1:"},
    {"type": "image_url", ...},
    {"type": "text", "text": "Səhifə 2:"},
    {"type": "image_url", ...}]
```

---

## Laravel: Yüklənmiş Şəkilin Analizi

```php
<?php

declare(strict_types=1);

namespace App\AI\Vision;

use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Claude-un vizyon imkanlarından istifadə edərək şəkilləri analiz etmək üçün servis.
 */
class ImageAnalyzer
{
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg' => 'image/jpeg',
        'image/jpg'  => 'image/jpeg',
        'image/png'  => 'image/png',
        'image/gif'  => 'image/gif',
        'image/webp' => 'image/webp',
    ];

    private const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024; // 5MB
    private const MAX_DIMENSION_PX = 1568;

    /**
     * Yüklənmiş faylı analiz edin və təsvir və ya strukturlu məlumat qaytarın.
     *
     * @param  UploadedFile  $file    Yüklənmiş şəkil faylı
     * @param  string        $prompt  Nəyi analiz etmək və ya çıxarmaq
     * @param  string        $model   İstifadə ediləcək Claude modeli
     * @return ImageAnalysisResult
     */
    public function analyze(
        UploadedFile $file,
        string $prompt = 'Bu şəkildə gördüklərini ətraflı təsvir et.',
        string $model = 'claude-sonnet-4-6',
    ): ImageAnalysisResult {
        $this->validateFile($file);

        $imageData = $this->prepareImageData($file);

        $response = Anthropic::messages()->create([
            'model'      => $model,
            'max_tokens' => 2048,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $imageData['media_type'],
                                'data'       => $imageData['base64'],
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);

        Log::info('Şəkil analiz edildi', [
            'model'         => $model,
            'input_tokens'  => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]);

        return new ImageAnalysisResult(
            content: $response->content[0]->text,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            model: $model,
        );
    }

    /**
     * Tək bir API çağırışında birdən çox şəkli analiz edin.
     *
     * @param  array<UploadedFile>  $files
     */
    public function analyzeMultiple(
        array $files,
        string $prompt,
        string $model = 'claude-sonnet-4-6',
    ): ImageAnalysisResult {
        if (count($files) > 20) {
            throw new \InvalidArgumentException('Sorğu başına maksimum 20 şəkil');
        }

        $contentBlocks = [];

        foreach ($files as $index => $file) {
            $this->validateFile($file);
            $imageData = $this->prepareImageData($file);

            // Aydınlıq üçün hər şəkli etiketləyin
            $contentBlocks[] = [
                'type' => 'text',
                'text' => "Şəkil " . ($index + 1) . ":",
            ];

            $contentBlocks[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $imageData['media_type'],
                    'data'       => $imageData['base64'],
                ],
            ];
        }

        // Son prompt
        $contentBlocks[] = [
            'type' => 'text',
            'text' => $prompt,
        ];

        $response = Anthropic::messages()->create([
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => [
                ['role' => 'user', 'content' => $contentBlocks],
            ],
        ]);

        return new ImageAnalysisResult(
            content: $response->content[0]->text,
            inputTokens: $response->usage->inputTokens,
            outputTokens: $response->usage->outputTokens,
            model: $model,
        );
    }

    /**
     * Faylın dəstəklənən şəkil növü olduğunu və ölçü limitlərini keçmədiyini yoxlayın.
     */
    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw new ImageTooLargeException(
                "Şəkil fayl ölçüsü ({$file->getSize()} bayt) 5MB limitini keçir. " .
                "Zəhmət olmasa yükləməzdən əvvəl şəkili yenidən ölçüləndirin və ya sıxışdırın."
            );
        }

        $mimeType = $file->getMimeType();
        if (!isset(self::SUPPORTED_MIME_TYPES[$mimeType])) {
            throw new UnsupportedImageTypeException(
                "Dəstəklənməyən şəkil növü '{$mimeType}'. " .
                "Dəstəklənən növlər: " . implode(', ', array_keys(self::SUPPORTED_MIME_TYPES))
            );
        }
    }

    /**
     * Şəkili oxuyun və base64 kimi kodlaşdırın.
     *
     * @return array{base64: string, media_type: string}
     */
    private function prepareImageData(UploadedFile $file): array
    {
        $fileContents = file_get_contents($file->getRealPath());

        if ($fileContents === false) {
            throw new \RuntimeException("Şəkil faylını oxumaq alınmadı: {$file->getClientOriginalName()}");
        }

        return [
            'base64'     => base64_encode($fileContents),
            'media_type' => self::SUPPORTED_MIME_TYPES[$file->getMimeType()],
        ];
    }
}

/**
 * Şəkil analiz əməliyyatından nəticə.
 */
readonly class ImageAnalysisResult
{
    public function __construct(
        public string $content,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {}

    public function toArray(): array
    {
        return [
            'content'       => $this->content,
            'input_tokens'  => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'model'         => $this->model,
        ];
    }
}

class ImageTooLargeException extends \RuntimeException {}
class UnsupportedImageTypeException extends \RuntimeException {}
```

### Controller-də İstifadə

```php
<?php

namespace App\Http\Controllers;

use App\AI\Vision\ImageAnalyzer;
use App\AI\Vision\ImageTooLargeException;
use App\AI\Vision\UnsupportedImageTypeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageAnalysisController extends Controller
{
    public function __construct(
        private readonly ImageAnalyzer $analyzer,
    ) {}

    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'image'  => ['required', 'file', 'max:5120'], // 5MB limit
            'prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->analyzer->analyze(
                file: $request->file('image'),
                prompt: $request->input('prompt', 'Bu şəkili ətraflı təsvir edin.'),
            );

            return response()->json([
                'success' => true,
                'data'    => $result->toArray(),
            ]);
        } catch (ImageTooLargeException | UnsupportedImageTypeException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }
}
```

---

## Laravel: PDF Fakturasından Strukturlu Məlumat Çıxarımı

Praktikada PDF-lər şəkillərə çevrilir (hər səhifə üçün bir şəkil) və çıxarım üçün Claude-a göndərilir.

```php
<?php

declare(strict_types=1);

namespace App\AI\Vision;

use Anthropic\Laravel\Facades\Anthropic;
use App\DTOs\InvoiceData;
use App\DTOs\LineItem;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToImage\Pdf as PdfToImage; // composer require spatie/pdf-to-image

/**
 * Claude vizyonu istifadə edərək PDF fayllarından strukturlu faktura məlumatı çıxarır.
 *
 * Tələb olunur: spatie/pdf-to-image, GhostScript və Imagick PHP uzantısı.
 * Quraşdırın: composer require spatie/pdf-to-image
 *          apt-get install ghostscript
 */
class InvoiceExtractor
{
    private const EXTRACTION_PROMPT = <<<'PROMPT'
Siz ekspert faktura analizçisisiniz. Bu faktura şəklindən BÜTÜN məlumatları çıxarın.

Tam olaraq bu strukturla JSON obyekti qaytarın:
<invoice_data>
{
  "vendor": {
    "name": "string",
    "address": "string or null",
    "email": "string or null",
    "phone": "string or null",
    "tax_id": "string or null"
  },
  "invoice_number": "string",
  "invoice_date": "YYYY-MM-DD or null",
  "due_date": "YYYY-MM-DD or null",
  "currency": "USD/EUR/GBP/etc",
  "line_items": [
    {
      "description": "string",
      "quantity": number,
      "unit_price": number,
      "total": number,
      "tax_rate": number or null
    }
  ],
  "subtotal": number,
  "tax_amount": number or null,
  "discount_amount": number or null,
  "total_amount": number,
  "payment_terms": "string or null",
  "notes": "string or null",
  "confidence": "high|medium|low"
}
</invoice_data>

Qaydalar:
- Bütün pul dəyərləri valyuta simvolları olmadan float kimi
- Yalnız YYYY-MM-DD formatında tarixlər
- Sahə mövcud deyilsə və ya oxunmursa, null istifadə edin
- Sahələrin 20%-dən çoxu null və ya qeyri-müəyyəndirsə "confidence"-ı "low" kimi təyin edin
- <invoice_data> etikətlərinin xaricindəki heç bir mətni daxil etməyin
PROMPT;

    /**
     * PDF fakturasından strukturlu məlumat çıxarın.
     *
     * @param  string  $pdfPath  PDF faylının mütləq yolu
     * @return InvoiceData
     */
    public function extract(string $pdfPath): InvoiceData
    {
        $this->validatePdfPath($pdfPath);

        $pageImages = $this->convertPdfToImages($pdfPath);

        try {
            $rawJson = $this->callClaudeWithImages($pageImages);
            return $this->parseAndHydrate($rawJson);
        } finally {
            // Müvəqqəti şəkil fayllarını təmizləyin
            foreach ($pageImages as $imagePath) {
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }
    }

    /**
     * PDF səhifələrini müvəqqəti şəkil fayllarına çevirin.
     *
     * @return array<string> Müvəqqəti şəkil fayl yollarının siyahısı
     */
    private function convertPdfToImages(string $pdfPath): array
    {
        $pdf = new PdfToImage($pdfPath);
        $pageCount = $pdf->getNumberOfPages();
        $tempDir = sys_get_temp_dir();
        $imagePaths = [];

        // Xərc nəzarəti üçün ilk 10 səhifəylə məhdudlaşdırın
        $maxPages = min($pageCount, 10);

        for ($page = 1; $page <= $maxPages; $page++) {
            $outputPath = $tempDir . '/invoice_page_' . uniqid() . '_' . $page . '.jpg';

            $pdf->setPage($page)
                ->setResolution(150)         // 150 DPI OCR üçün kifayətdir
                ->setOutputFormat('jpg')
                ->saveImage($outputPath);

            $imagePaths[] = $outputPath;
        }

        return $imagePaths;
    }

    /**
     * Mesajlar massivini qurun və Claude-u çağırın.
     *
     * @param  array<string>  $imagePaths
     */
    private function callClaudeWithImages(array $imagePaths): string
    {
        $contentBlocks = [];

        foreach ($imagePaths as $index => $imagePath) {
            // Səhifə etiketi əlavə edin
            if (count($imagePaths) > 1) {
                $contentBlocks[] = [
                    'type' => 'text',
                    'text' => "Faktura səhifəsi " . ($index + 1) . " / " . count($imagePaths) . ":",
                ];
            }

            $imageContents = file_get_contents($imagePath);
            if ($imageContents === false) {
                throw new \RuntimeException("Şəkil oxunmadı: {$imagePath}");
            }

            $contentBlocks[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'image/jpeg',
                    'data'       => base64_encode($imageContents),
                ],
            ];
        }

        $contentBlocks[] = [
            'type' => 'text',
            'text' => self::EXTRACTION_PROMPT,
        ];

        $response = Anthropic::messages()->create([
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 2048,
            'temperature' => 0.0, // Deterministik çıxarım
            'messages'   => [
                ['role' => 'user', 'content' => $contentBlocks],
            ],
        ]);

        $content = $response->content[0]->text;

        Log::info('Faktura çıxarıldı', [
            'input_tokens'  => $response->usage->inputTokens,
            'output_tokens' => $response->usage->outputTokens,
            'pages'         => count($imagePaths),
        ]);

        return $content;
    }

    /**
     * XML sarılmış JSON cavabını analiz edin və DTO-ya yükləyin.
     */
    private function parseAndHydrate(string $rawResponse): InvoiceData
    {
        // <invoice_data> etikətləri arasından JSON çıxarın
        if (!preg_match('/<invoice_data>\s*(\{.*?\})\s*<\/invoice_data>/s', $rawResponse, $matches)) {
            throw new ExtractionParseException(
                "Cavabda <invoice_data> etikətləri tapılmadı. Xam cavab: " .
                substr($rawResponse, 0, 500)
            );
        }

        $jsonString = $matches[1];

        try {
            $data = json_decode($jsonString, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ExtractionParseException(
                "Çıxarılmış JSON analiz edilmədi: {$e->getMessage()}. JSON: " .
                substr($jsonString, 0, 500)
            );
        }

        return $this->hydrateDto($data);
    }

    private function hydrateDto(array $data): InvoiceData
    {
        $lineItems = array_map(
            fn (array $item) => new LineItem(
                description: $item['description'] ?? '',
                quantity: (float) ($item['quantity'] ?? 0),
                unitPrice: (float) ($item['unit_price'] ?? 0),
                total: (float) ($item['total'] ?? 0),
                taxRate: isset($item['tax_rate']) ? (float) $item['tax_rate'] : null,
            ),
            $data['line_items'] ?? []
        );

        return new InvoiceData(
            vendorName: $data['vendor']['name'] ?? 'Naməlum',
            vendorAddress: $data['vendor']['address'] ?? null,
            vendorEmail: $data['vendor']['email'] ?? null,
            invoiceNumber: $data['invoice_number'] ?? null,
            invoiceDate: isset($data['invoice_date']) ? new \DateTimeImmutable($data['invoice_date']) : null,
            dueDate: isset($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
            currency: $data['currency'] ?? 'USD',
            lineItems: $lineItems,
            subtotal: (float) ($data['subtotal'] ?? 0),
            taxAmount: isset($data['tax_amount']) ? (float) $data['tax_amount'] : null,
            discountAmount: isset($data['discount_amount']) ? (float) $data['discount_amount'] : null,
            totalAmount: (float) ($data['total_amount'] ?? 0),
            paymentTerms: $data['payment_terms'] ?? null,
            notes: $data['notes'] ?? null,
            confidence: $data['confidence'] ?? 'low',
        );
    }

    private function validatePdfPath(string $pdfPath): void
    {
        if (!file_exists($pdfPath)) {
            throw new \InvalidArgumentException("PDF faylı tapılmadı: {$pdfPath}");
        }

        if (pathinfo($pdfPath, PATHINFO_EXTENSION) !== 'pdf') {
            throw new \InvalidArgumentException("Fayl PDF olmalıdır: {$pdfPath}");
        }

        if (filesize($pdfPath) > 50 * 1024 * 1024) { // 50MB limit
            throw new \InvalidArgumentException("PDF faylı çox böyükdür (maks 50MB)");
        }
    }
}

// DTO sinifləri
readonly class InvoiceData
{
    public function __construct(
        public string $vendorName,
        public ?string $vendorAddress,
        public ?string $vendorEmail,
        public ?string $invoiceNumber,
        public ?\DateTimeImmutable $invoiceDate,
        public ?\DateTimeImmutable $dueDate,
        public string $currency,
        /** @var LineItem[] */
        public array $lineItems,
        public float $subtotal,
        public ?float $taxAmount,
        public ?float $discountAmount,
        public float $totalAmount,
        public ?string $paymentTerms,
        public ?string $notes,
        public string $confidence,
    ) {}
}

readonly class LineItem
{
    public function __construct(
        public string $description,
        public float $quantity,
        public float $unitPrice,
        public float $total,
        public ?float $taxRate,
    ) {}
}

class ExtractionParseException extends \RuntimeException {}
```

---

## Laravel: İstifadəçi Yüklənmiş Şəkillərin Xəta Emalı ilə İşlənməsi

İstifadəçi şəkil yüklənmələrinin tam həyat dövrünü — yenidən ölçüləndirmə, yoxlama və zərif xəta emalı daxil olmaqla — idarə edən produksiyaya hazır servis.

```php
<?php

declare(strict_types=1);

namespace App\AI\Vision;

use Anthropic\Exceptions\ApiException;
use Anthropic\Laravel\Facades\Anthropic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager; // composer require intervention/image
use Intervention\Image\Drivers\Gd\Driver;

/**
 * AI analizi üçün produksiyaya hazır şəkil emal servisi.
 *
 * Emal edir: yoxlama, yenidən ölçüləndirmə, format çevrilməsi, xəta bərpası,
 * xərc qiymətləndirilməsi və yenidən cəhd məntiqi.
 */
class ProductionImageProcessor
{
    private const MAX_DIMENSION = 1568;       // Claude-un optimal maks ölçüsü
    private const PREFERRED_DIMENSION = 800;  // Keyfiyyət/xərc optimal balansı
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const JPEG_QUALITY = 85;

    private readonly ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Yüklənmiş şəkili emal edin və Claude ilə analiz edin.
     *
     * @throws ImageProcessingException bərpa edilə bilməyən xətalarda
     */
    public function process(
        UploadedFile $file,
        string $analysisPrompt,
        array $options = [],
    ): ProcessingResult {
        $startTime = microtime(true);

        // Addım 1: Faylı yoxlayın
        $validation = $this->validate($file);
        if (!$validation->isValid) {
            return ProcessingResult::failure(
                error: $validation->error,
                errorCode: $validation->errorCode,
            );
        }

        // Addım 2: API ötürümü üçün şəkili optimize edin
        try {
            $optimizedImage = $this->optimize($file, $options);
        } catch (\Exception $e) {
            Log::error('Şəkil optimizasiyası uğursuz oldu', [
                'error' => $e->getMessage(),
                'file'  => $file->getClientOriginalName(),
            ]);
            // Optimizasiya uğursuz olarsa orijinala qayıdın
            $optimizedImage = OptimizedImage::fromOriginal($file);
        }

        // Addım 3: API çağırışından əvvəl xərci qiymətləndirin
        $estimatedTokens = $this->estimateTokenCost(
            $optimizedImage->width,
            $optimizedImage->height
        );

        // Addım 4: Yenidən cəhd məntiqi ilə Claude API-ni çağırın
        try {
            $apiResult = $this->callWithRetry(
                imageData: $optimizedImage->base64,
                mediaType: $optimizedImage->mediaType,
                prompt: $analysisPrompt,
                model: $options['model'] ?? 'claude-sonnet-4-6',
                maxRetries: $options['max_retries'] ?? 2,
            );
        } catch (ImageContentPolicyException $e) {
            return ProcessingResult::failure(
                error: 'Şəkil məzmun siyasəti tərəfindən rədd edildi: ' . $e->getMessage(),
                errorCode: 'content_policy',
            );
        } catch (\Exception $e) {
            return ProcessingResult::failure(
                error: 'AI analizi uğursuz oldu: ' . $e->getMessage(),
                errorCode: 'api_error',
            );
        }

        $processingTime = microtime(true) - $startTime;

        return ProcessingResult::success(
            content: $apiResult['content'],
            inputTokens: $apiResult['input_tokens'],
            outputTokens: $apiResult['output_tokens'],
            estimatedTokens: $estimatedTokens,
            processingTimeMs: (int) ($processingTime * 1000),
            imageWidth: $optimizedImage->width,
            imageHeight: $optimizedImage->height,
        );
    }

    /**
     * Yüklənmiş faylı yoxlayın.
     */
    private function validate(UploadedFile $file): ValidationResult
    {
        // Faylın həqiqətən yüklənib-yüklənmədiyini yoxlayın (PHP xətası deyil)
        if (!$file->isValid()) {
            return ValidationResult::invalid(
                "Fayl yüklənməsi uğursuz oldu: " . $file->getErrorMessage(),
                'upload_error'
            );
        }

        // Fayl ölçüsünü yoxlayın
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $sizeMB = round($file->getSize() / 1024 / 1024, 1);
            return ValidationResult::invalid(
                "Fayl ölçüsü {$sizeMB}MB 5MB limitini keçir. Zəhmət olmasa şəkili sıxışdırın.",
                'file_too_large'
            );
        }

        // Həqiqi fayl məzmunundan MIME növünü yoxlayın (yalnız uzantıya görə deyil)
        $mimeType = $file->getMimeType();
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowed, true)) {
            return ValidationResult::invalid(
                "'{$mimeType}' fayl növü dəstəklənmir. JPEG, PNG, GIF və ya WebP istifadə edin.",
                'invalid_type'
            );
        }

        // Bunun həqiqətən şəkil olduğunu yoxlayın (gizlənmiş fayl deyil)
        try {
            $img = $this->imageManager->read($file->getRealPath());
            if ($img->width() === 0 || $img->height() === 0) {
                return ValidationResult::invalid('Etibarsız və ya zədələnmiş şəkil faylı.', 'corrupt');
            }
        } catch (\Exception $e) {
            return ValidationResult::invalid(
                'Şəkil faylı oxunmadı. Zədələnmiş ola bilər.',
                'corrupt'
            );
        }

        return ValidationResult::valid();
    }

    /**
     * Şəkili optimize edin: çox böyüksə yenidən ölçüləndirin, ölçü effektivliyi üçün JPEG-ə çevirin.
     */
    private function optimize(UploadedFile $file, array $options): OptimizedImage
    {
        $img = $this->imageManager->read($file->getRealPath());
        $originalWidth = $img->width();
        $originalHeight = $img->height();

        $targetDimension = $options['quality'] === 'high'
            ? self::MAX_DIMENSION
            : self::PREFERRED_DIMENSION;

        // Hədəfdən böyüksə yenidən ölçüləndirin
        if ($originalWidth > $targetDimension || $originalHeight > $targetDimension) {
            $img->scaleDown($targetDimension, $targetDimension);
        }

        // Ən yaxşı ölçü/keyfiyyət nisbəti üçün JPEG-ə çevirin
        $encoded = $img->toJpeg(quality: self::JPEG_QUALITY);

        return new OptimizedImage(
            base64: base64_encode($encoded->toString()),
            mediaType: 'image/jpeg',
            width: $img->width(),
            height: $img->height(),
            originalWidth: $originalWidth,
            originalHeight: $originalHeight,
        );
    }

    /**
     * Eksponensial geri çəkilmə yenidən cəhdi ilə Claude API-ni çağırın.
     */
    private function callWithRetry(
        string $imageData,
        string $mediaType,
        string $prompt,
        string $model,
        int $maxRetries,
    ): array {
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    $delay = (int) (pow(2, $attempt - 1) * 1000); // 1s, 2s, 4s
                    usleep($delay * 1000);
                }

                $response = Anthropic::messages()->create([
                    'model'      => $model,
                    'max_tokens' => 2048,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type'   => 'image',
                                    'source' => [
                                        'type'       => 'base64',
                                        'media_type' => $mediaType,
                                        'data'       => $imageData,
                                    ],
                                ],
                                ['type' => 'text', 'text' => $prompt],
                            ],
                        ],
                    ],
                ]);

                return [
                    'content'       => $response->content[0]->text,
                    'input_tokens'  => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                ];
            } catch (ApiException $e) {
                // Məzmun siyasəti pozuntuları yenidən cəhd edilməməlidir
                if ($e->getCode() === 400 && str_contains($e->getMessage(), 'content')) {
                    throw new ImageContentPolicyException($e->getMessage(), previous: $e);
                }

                // Sürət limiti — geri çəkilmə ilə yenidən cəhd edin
                if ($e->getCode() === 429) {
                    $lastException = $e;
                    continue;
                }

                // Server xətaları — yenidən cəhd edin
                if ($e->getCode() >= 500) {
                    $lastException = $e;
                    continue;
                }

                // Digər müştəri xətaları — yenidən cəhd etməyin
                throw $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Yenidən cəhdlərdən sonra API çağırışı uğursuz oldu');
    }

    /**
     * API çağırışından əvvəl şəkil token xərcini qiymətləndirin.
     */
    private function estimateTokenCost(int $width, int $height): int
    {
        $tilesX = (int) ceil($width / 512);
        $tilesY = (int) ceil($height / 512);
        return ($tilesX * $tilesY + 1) * 1500;
    }
}

// Dəstəkçi dəyər obyektləri
readonly class OptimizedImage
{
    public function __construct(
        public string $base64,
        public string $mediaType,
        public int $width,
        public int $height,
        public int $originalWidth,
        public int $originalHeight,
    ) {}

    public static function fromOriginal(UploadedFile $file): self
    {
        return new self(
            base64: base64_encode(file_get_contents($file->getRealPath())),
            mediaType: $file->getMimeType(),
            width: 0,
            height: 0,
            originalWidth: 0,
            originalHeight: 0,
        );
    }
}

readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public string $error = '',
        public string $errorCode = '',
    ) {}

    public static function valid(): self { return new self(true); }
    public static function invalid(string $error, string $code): self
    {
        return new self(false, $error, $code);
    }
}

readonly class ProcessingResult
{
    public function __construct(
        public bool $success,
        public string $content = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $estimatedTokens = 0,
        public int $processingTimeMs = 0,
        public int $imageWidth = 0,
        public int $imageHeight = 0,
        public string $error = '',
        public string $errorCode = '',
    ) {}

    public static function success(
        string $content,
        int $inputTokens,
        int $outputTokens,
        int $estimatedTokens,
        int $processingTimeMs,
        int $imageWidth,
        int $imageHeight,
    ): self {
        return new self(
            success: true,
            content: $content,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            estimatedTokens: $estimatedTokens,
            processingTimeMs: $processingTimeMs,
            imageWidth: $imageWidth,
            imageHeight: $imageHeight,
        );
    }

    public static function failure(string $error, string $errorCode): self
    {
        return new self(success: false, error: $error, errorCode: $errorCode);
    }
}

class ImageContentPolicyException extends \RuntimeException {}
class ImageProcessingException extends \RuntimeException {}
```

---

## Ümumi Uğursuzluq Halları

```
UĞURSUZLUQ: Şəkil çox tünd/açıqdır
  Simptom: "Bu şəkildəki mətni aydın görmək mümkün deyil"
  Düzəliş: Göndərməzdən əvvəl kontrast artırma ilə ön emal edin
           Prompt-da işıqlandırma şəraitini qeyd edin

UĞURSUZLUQ: Mətn çox kiçikdir
  Simptom: Model oxuya bildiyiniz mətni oxunmaz kimi bildirir
  Düzəliş: Mətn ağırlıqlı bölgələri kəsin və yaxınlaşdırın
           Daha yüksək rezolyusiyada göndərin (MAX_DIMENSION istifadə edin)

UĞURSUZLUQ: Əl yazısı mətninin dəqiqliyi aşağıdır
  Simptom: Əl yazısı məzmununda transkripsiyan xətaları
  Düzəliş: Daha yüksək rezolyusiya istifadə edin, prompt-da "əl yazısı" qeyd edin,
           modeldən qeyri-müəyyən simvolları [?] ilə göstərməsini xahiş edin

UĞURSUZLUQ: Çox sütunlu sxemin qarışması
  Simptom: Sütunlardan qarışıq mətn
  Düzəliş: Sxemi prompt-da təsvir edin:
           "Bu iki sütunlu sənəddir. Əvvəlcə sol sütunu,
            sonra sağ sütunu oxuyun."

UĞURSUZLUQ: Çıxarımdan sonra JSON analizi uğursuz olur
  Simptom: Çıxarım cavabında deformasiya olmuş JSON
  Düzəliş: XML sarıcı etikətlər + daha ciddi prompt istifadə edin
           "Bu etibarsız JSON-u düzəldin: {bad_json}" ilə yenidən cəhd əlavə edin
           Sxemi məcbur etmək üçün tool_use istifadə edin (09-cu faylə baxın)

UĞURSUZLUQ: Böyük paket işlərində sürət limitləri
  Simptom: Çoxlu sənəd emal edərkən HTTP 429 xətaları
  Düzəliş: Eksponensial geri çəkilmə yenidən cəhdi əlavə edin
           Sürət limiti ilə növbə işlərini
           Daha sadə sənədlər üçün Sonnet əvəzinə Haiku istifadə edin
```

---

## Arxitekt Mülahizələri

### Vizyon vs. OCR-i Nə Zaman İstifadə Etməli

```
VİZYON (Claude) İSTİFADƏ EDİN:
  ✓ Sənəd strukturu önəmlidir (formalar, fakturalar, cədvəllər)
  ✓ Yalnız mətn çıxarmaq deyil, konteksti anlamaq lazımdır
  ✓ Sənəddə qarışıq məzmun var (mətn + diaqramlar + möhürlər)
  ✓ Vizual məzmuna əsasən qərar qəbul etmək lazımdır
  ✓ Sürət/xərcdən daha çox dəqiqlik önəmlidir

ƏNƏNƏVI OCR (Tesseract, AWS Textract) İSTİFADƏ EDİN:
  ✓ Təmiz, yazılmış sənədlərdən sadə mətn çıxarımı
  ✓ Çox yüksək həcm (milyonlarla sənəd)
  ✓ Xərc kritikdir
  ✓ Mövcud OCR infrastrukturunuz var
  ✓ Aşağı emal üçün xam mətnə ehtiyacınız var

HİBRİD YANAŞMA:
  1. Mətn əldə etmək üçün ucuz OCR işlədin
  2. OCR etibarlılığı həddən aşağıdırsa, Claude vizyonuna yönləndirin
  3. Claude yalnız çətin halları idarə edir
  4. Hər ikisinin ən yaxşısı: xərc-effektiv + kənar halları idarə edir
```

### Saxlama və Məxfilik

```
Claude API-yə göndərilən şəkillər:
  - HTTPS üzərindən ötürülür (ötürüm zamanı şifrələnir)
  - Anthropic-in məlumat emalı siyasətləri tətbiq edilir
  - Standart olaraq öyrənmə üçün istifadə edilmir (API istifadəsi)
  - Emal sonrası silinir

Həssas sənədlər üçün (tibbi, hüquqi, maliyyə):
  1. Anthropic-in məlumat emalı sazişini nəzərdən keçirin
  2. Nəzərə alın: yerli vizyon modeli istifadə edin (LLaVA, LLaMA Vision)
  3. Və ya: əvvəlcə lokal OCR ilə mətni çıxarın, yalnız mətni API-yə göndərin
  4. Açıq nəzərdən keçirilməmiş və təsdiqlənməmiş şəkilləri PII ilə heç vaxt göndərməyin
```

---

*Əvvəlki: [04 — Temperature və Parametrlər](./04-temperature-parameters.md) | Növbəti: [06 — Claude API Bələdçisi](../02-claude-api/01-claude-api-guide.md)*
