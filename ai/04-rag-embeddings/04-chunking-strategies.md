# Sənəd Chunking Strategiyaları: RAG-ın Ən Az Qiymətləndirilən Hissəsi (Middle)

## Niyə Chunking Əsasdır

RAG sistem uğursuzluqlarının əksəriyyəti model seçimi ya da prompt mühəndisliyindən deyil, zəif chunking qərarlarından irəli gəlir. Pipeline yalnız əldə edilən vahidlər qədər yaxşıdır.

Chunking-dəki əsas gərginlik:

- **Çox böyük**: Chunk-lar uyğunluq ballarını durulaşdıran uyğunsuz məlumat ehtiva edir; kontekst pəncərəsi limitlərini aşırsınız; LLM yanlış hissələrə diqqət yetirir
- **Çox kiçik**: Hər chunk mənalı olmaq üçün kifayət qədər kontekstdən məhrumdur; cavablar çox sayda chunk-ı birləşdirməyi tələb edir; cümləni ortadan bölmək uyumu itirir

Universal olaraq düzgün chunk ölçüsü yoxdur. Optimal strategiya aşağıdakılara əsaslanır:
1. Sənəd növü (nəsr, kod, cədvəllər, PDF-lər, markdown)
2. Sorğu növü (spesifik faktlar vs geniş xülasələr)
3. Embedding modeli kontekst pəncərəsi (Cohere: 512 token; OpenAI: 8191 token)
4. LLM kontekst pəncərəsi büdcəsi (nə qədər kontekst daxil edə bilərsiniz?)
5. Tələb olunan cavab dəqiqliyi

---

## Chunking Strategiyaları İzahı

### 1. Sabit Ölçülü Chunking

Mətni tam N simvol ya da token ölçülü chunk-lara böl, isteğe bağlı üst-üstə düşmə ilə.

```
|--chunk 1 (512 token)--|
                    |--chunk 2 (512 token)--|
                         ^ üst-üstə düşmə (50 token)
```

**Üstünlüklər**: Sadə, proqnozlaşdırıla bilən, tətbiqi asan.
**Çatışmazlıqlar**: Cümlələri, abzasları və məntiqi vahidləri ixtiyari olaraq bölür. İki chunk-a bölünmüş cümlə mənasını itirir.

**Nə zaman istifadə edilir**: Yalnız başlanğıc xətti kimi. Demək olar ki həmişə digər strategiyalar tərəfindən üstələnir.

---

### 2. Cümlə Chunking

Cümlə sərhədi aşkarlamasından istifadə edərək təbii cümlə sərhədlərindən böl, sonra cümlələri hədəf ölçülü chunk-lara qruplaşdır.

**Üstünlüklər**: Tam fikirləri qoruyur. Hər chunk semantik cəhətdən uyumludur.
**Çatışmazlıqlar**: Cümlələr uzunluq baxımından kəskin şəkildə fərqlənir; chunk-ların qeyri-bərabər token sayı olur.

**Nə zaman istifadə edilir**: Danışıq məzmunu, Sual-Cavab sənədləri, dəstək biletləri.

---

### 3. Rekursiv Simvol Bölgüsü

Chunk-lar ölçü limitindən aşağı olana qədər sıra ilə ayırıcılar iyerarxiyasını sınayır:

```
["\n\n", "\n", ". ", "! ", "? ", " ", ""]
```

Əvvəlcə ikiqat yeni sətirdən (abzaslar) bölməyi sınayır. Hələ çox böyükdürsə, tək yeni sətirdən bölür. Hələ çox böyükdürsə, cümlə sonu durğu işarəsindən bölür. Beləcə davam edir.

Bu, sənəd strukturunu qoruyarkən ölçü limitlərini də hörmətlə nəzərə aldığı üçün **ən çox tövsiyə edilən standart strategiyadır**.

**Üstünlüklər**: Mümkün olduqda sənəd strukturunu hörmətlə saxlayır. Zərif şəkildə alternativə keçir.
**Çatışmazlıqlar**: Hələ semantikanı anlamır — bir abzas ölçü limitini keçirsə abzasın ortasından bölür.

---

### 4. Semantik Chunking

Mətni strukturdan deyil, semantik oxşarlığa görə qruplaşdır. Alqoritm:
1. Mətni cümlələrə böl
2. Hər cümləni embed et
3. Bitişik cümlələr arasında cosine similarity hesabla
4. Oxşarlıq threshold-dan aşağı düşdüyü yerlərdən böl (semantik sərhəd)

**Üstünlüklər**: Chunk-lar mövzu sərhədlərinə uyğun gəlir. Üstün retrieval keyfiyyəti.
**Çatışmazlıqlar**: Əvvəlcə bütün cümlələri embed etməyi tələb edir (bahalı). Dəyişən chunk ölçüləri.

**Nə zaman istifadə edilir**: Dəqiqlik xərcdən daha az önəm kəsb etdiyi yüksək keyfiyyətli RAG.

---

### 5. İyerarxik (Valideyn-Uşaq) Chunking

Sənədləri çoxlu qranülyarlıqda saxla:
- Böyük valideyn chunk-ları (2048 token) — yaratma üçün zəngin kontekst
- Kiçik uşaq chunk-ları (256 token) — retrieval üçün dəqiq

**Yalnız uşaq chunk-larını indeksə əlavə et**. Retrieval zamanı valideyn chunk-ı qaytar.

```
Sənəd
├── Valideyn Chunk 1 (2048 token)
│   ├── Uşaq Chunk 1a (256 token) ← indeksləşdirilmiş
│   ├── Uşaq Chunk 1b (256 token) ← indeksləşdirilmiş
│   └── Uşaq Chunk 1c (256 token) ← indeksləşdirilmiş
└── Valideyn Chunk 2 (2048 token)
    ├── Uşaq Chunk 2a (256 token) ← indeksləşdirilmiş
    └── Uşaq Chunk 2b (256 token) ← indeksləşdirilmiş
```

**Üstünlüklər**: Hər iki dünyanın ən yaxşısı — retrieval dəqiqliyi + yaratma konteksti.
**Çatışmazlıqlar**: 3-4x daha mürəkkəb. İki səviyyəli saxlama və retrieval tələb edir.

---

### 6. Geç Chunking (Late Chunking)

Əvvəlcə tam sənədi embed edən, sonra embeddingləri (mətni deyil) chunk-a ayıran daha yeni bir texnika. jina-embeddings-v2 kimi dəstəkləyən modellərdə mövcuddur.

**Mexanizm**: Transformerin diqqəti chunk-lamadan əvvəl tam sənəd kontekstini görür. Hər chunk-ın embeddingi ətraf sənəd kontekstini əks etdirir.

**Üstünlüklər**: "İtmiş kontekst" problemini aradan qaldırır — chunk-lar kontekst-xəbərdardır.
**Çatışmazlıqlar**: Spesifik model dəstəyi tələb edir. Universal şəkildə mövcud deyil.

---

### Chunk Ölçüsü vs. Üst-üstə Düşmə Kompromisləri

| Chunk Ölçüsü | Üst-üstə Düşmə | Təsiri |
|-----------|---------|--------|
| 128-256 token | 0-20 | Çox dəqiq, amma kontekstdən məhrum; çox cümləli cavabları qaçıra bilər |
| 512 token | 50-100 | Əksər istifadə halları üçün yaxşı standart |
| 1024 token | 100-200 | Daha yaxşı kontekst, aşağı dəqiqlik; xülasə sorğuları üçün yaxşı |
| 2048 token | 200-400 | Tam abzas konteksti; iyerarxik strategiyada valideyn chunk kimi istifadə et |

**Üst-üstə düşmənin məqsədi**: İki chunk-ın sərhədindəki açar cümlənin ən azı bir chunk-da tam kontekstdə yer almasını təmin edir. Üst-üstə düşmə olmadan, sərhəddə bölünmüş cümlə iki natamam chunk-dadır, onların heçbiri yaxşı əldə edilmir.

**Baş qaydası**: Üst-üstə düşmə = chunk ölçüsünün 10-20%-i.

---

## Xüsusi Hallar

### Kod Chunking

Kod heç vaxt funksiyanın ya da sinifin ortasından bölünməməlidir. Düzgün yanaşma:
1. AST-i analiz et
2. Funksiya/sinif/metod sərhədlərindən böl
3. Sinif adını və funksiya imzasını chunk metadatasına daxil et

```python
# Düzgün: bir chunk = bir funksiya
def calculate_tax(income: float, rate: float) -> float:
    """Gəlir vergisini hesabla."""
    return income * rate

# Yanlış: bu funksiyanı iki chunk-a bölmək mənanı itirir
```

PHP üçün xüsusi olaraq: sinif metod sərhədlərindən böl. Sinif docblock-unu ilk metod chunk-u ilə daxil et.

### Cədvəllər

Xam markdown cədvəlini tək chunk kimi embed etmək adətən işləyir, amma sətir səviyyəsindəki əldə edilmənin önünü alır. Seçimlər:
1. **Cədvəl-mətn kimi**: Sətir başına təbii dil cümlələrinə çevir ("Sətir 1: A Məhsulu, Qiymət $10, Stok 50 ədəd")
2. **Strukturlaşdırılmış çıxarma**: Hər sətri metadata sütun adları ilə ayrı chunk kimi saxla
3. **Xülasə + cədvəl**: Cədvəlin mətn xülasəsini yarat və onu xam cədvəllə yanaşı embed et

### PDF İşləmə

PDF-lər ən dağınıq formatdır:
- Hər səhifədəki başlıq/altbilgi mətni chunk-ları korlandırır
- Çox sütunlu düzənlər qarışıq mətn yaradır
- Səhifə nömrələri cümləni ortadan bölür
- Fiqurlar və şəkillərin mətn təsvirləri yoxdur

Ən yaxşı yanaşma: oxuma sırasını qoruyan və başlıq/altbilgiləri ayıra bilən xüsusi PDF çıxarma kitabxanasından (pdfplumber, pymupdf, Adobe PDF Extract API) istifadə et.

---

## Laravel Tətbiqi

### Çoxlu Strategiyalı ChunkingService

```php
<?php

namespace App\Services\RAG;

class ChunkingService
{
    /**
     * Əsas giriş nöqtəsi. Uyğun strategiyaya yönləndir.
     *
     * @param string $text Tam sənəd mətni
     * @param array $options Strategiya konfiqurasiyası
     * @return array ['text' => string, 'metadata' => array, 'token_count' => int] massivi
     */
    public function chunk(string $text, array $options = []): array
    {
        $strategy = $options['strategy'] ?? 'recursive';
        $chunkSize = $options['chunk_size'] ?? 512;
        $overlap = $options['overlap'] ?? 50;
        $baseMetadata = $options['metadata'] ?? [];

        return match($strategy) {
            'fixed'     => $this->fixedSizeChunking($text, $chunkSize, $overlap, $baseMetadata),
            'sentence'  => $this->sentenceChunking($text, $chunkSize, $overlap, $baseMetadata),
            'recursive' => $this->recursiveChunking($text, $chunkSize, $overlap, $baseMetadata),
            'paragraph' => $this->paragraphChunking($text, $chunkSize, $baseMetadata),
            'code'      => $this->codeChunking($text, $baseMetadata),
            default     => throw new \InvalidArgumentException("Bilinməyən chunking strategiyası: {$strategy}"),
        };
    }

    /**
     * Simvol səviyyəsində üst-üstə düşmə ilə sabit ölçülü chunking.
     */
    public function fixedSizeChunking(
        string $text,
        int $chunkSize = 512,
        int $overlap = 50,
        array $baseMetadata = [],
    ): array {
        // Token təxminini simvollara çevir (təxminən 4 simvol/token)
        $charSize = $chunkSize * 4;
        $charOverlap = $overlap * 4;
        $step = $charSize - $charOverlap;

        $chunks = [];
        $position = 0;
        $index = 0;
        $textLength = strlen($text);

        while ($position < $textLength) {
            $chunkText = substr($text, $position, $charSize);

            if (trim($chunkText) !== '') {
                $chunks[] = [
                    'text' => trim($chunkText),
                    'metadata' => array_merge($baseMetadata, [
                        'strategy' => 'fixed',
                        'start_char' => $position,
                        'end_char' => $position + strlen($chunkText),
                    ]),
                    'token_count' => $this->estimateTokens($chunkText),
                ];
            }

            $position += $step;
            $index++;
        }

        return $chunks;
    }

    /**
     * Rekursiv simvol bölgüsü — sıra ilə ayırıcıları sınayır.
     */
    public function recursiveChunking(
        string $text,
        int $targetTokens = 512,
        int $overlapTokens = 50,
        array $baseMetadata = [],
    ): array {
        $separators = ["\n\n", "\n", ". ", "! ", "? ", "; ", ", ", " ", ""];
        $rawChunks = $this->splitRecursive($text, $separators, $targetTokens * 4);
        
        // Üst-üstə düşmə əlavə et
        $chunks = $this->addOverlap($rawChunks, $overlapTokens * 4);

        return array_values(array_map(function ($chunkText, $index) use ($baseMetadata) {
            return [
                'text' => trim($chunkText),
                'metadata' => array_merge($baseMetadata, [
                    'strategy' => 'recursive',
                    'chunk_index' => $index,
                ]),
                'token_count' => $this->estimateTokens($chunkText),
            ];
        }, $chunks, array_keys($chunks)));
    }

    private function splitRecursive(string $text, array $separators, int $maxChars): array
    {
        if (strlen($text) <= $maxChars) {
            return [$text];
        }

        if (empty($separators)) {
            // Son çarə: sərt bölgü
            return str_split($text, $maxChars);
        }

        $separator = array_shift($separators);
        $splits = $separator !== '' ? explode($separator, $text) : str_split($text, 1);

        $chunks = [];
        $current = '';

        foreach ($splits as $split) {
            $candidate = $current . ($current !== '' ? $separator : '') . $split;

            if (strlen($candidate) <= $maxChars) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    if (strlen($current) > $maxChars) {
                        // Cari parça hələ çox böyükdür, rekursiv çağır
                        $subChunks = $this->splitRecursive($current, $separators, $maxChars);
                        $chunks = array_merge($chunks, $subChunks);
                    } else {
                        $chunks[] = $current;
                    }
                }
                $current = $split;
            }
        }

        if ($current !== '') {
            if (strlen($current) > $maxChars) {
                $subChunks = $this->splitRecursive($current, $separators, $maxChars);
                $chunks = array_merge($chunks, $subChunks);
            } else {
                $chunks[] = $current;
            }
        }

        return array_filter($chunks, fn($c) => trim($c) !== '');
    }

    private function addOverlap(array $chunks, int $overlapChars): array
    {
        if (count($chunks) <= 1 || $overlapChars === 0) {
            return $chunks;
        }

        $result = [];
        for ($i = 0; $i < count($chunks); $i++) {
            $chunk = $chunks[$i];

            // Əvvəlki chunk-dan son hissəni əlavə et
            if ($i > 0) {
                $prev = $chunks[$i - 1];
                $overlapText = substr($prev, max(0, strlen($prev) - $overlapChars));
                $chunk = $overlapText . ' ' . $chunk;
            }

            $result[] = $chunk;
        }

        return $result;
    }

    /**
     * Cümlə-xəbərdar chunking.
     */
    public function sentenceChunking(
        string $text,
        int $targetTokens = 512,
        int $overlapSentences = 2,
        array $baseMetadata = [],
    ): array {
        $sentences = $this->splitIntoSentences($text);
        $chunks = [];
        $currentChunk = [];
        $currentTokens = 0;
        $targetChars = $targetTokens * 4;

        foreach ($sentences as $sentence) {
            $sentenceTokens = $this->estimateTokens($sentence);

            if ($currentTokens + $sentenceTokens > $targetTokens && !empty($currentChunk)) {
                $chunkText = implode(' ', $currentChunk);
                $chunks[] = $chunkText;

                // Üst-üstə düşmə kimi son N cümləni saxla
                $currentChunk = array_slice($currentChunk, -$overlapSentences);
                $currentTokens = $this->estimateTokens(implode(' ', $currentChunk));
            }

            $currentChunk[] = $sentence;
            $currentTokens += $sentenceTokens;
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return array_map(fn($text, $idx) => [
            'text' => trim($text),
            'metadata' => array_merge($baseMetadata, [
                'strategy' => 'sentence',
                'chunk_index' => $idx,
            ]),
            'token_count' => $this->estimateTokens($text),
        ], $chunks, array_keys($chunks));
    }

    private function splitIntoSentences(string $text): array
    {
        // Böyük hərflə başlayan boşluğun ardından gələn cümlə sonu durğu işarəsindən böl
        $pattern = '/(?<=[.!?])\s+(?=[A-Z])/';
        $sentences = preg_split($pattern, $text);
        return array_filter(array_map('trim', $sentences), fn($s) => $s !== '');
    }

    /**
     * Abzas-əsaslı chunking. Ölçü limitinə qədər abzasları qruplaşdırır.
     */
    public function paragraphChunking(
        string $text,
        int $targetTokens = 512,
        array $baseMetadata = [],
    ): array {
        $paragraphs = preg_split('/\n\n+/', $text);
        $paragraphs = array_filter(array_map('trim', $paragraphs), fn($p) => $p !== '');

        $chunks = [];
        $current = '';
        $currentTokens = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraphTokens = $this->estimateTokens($paragraph);

            if ($currentTokens + $paragraphTokens > $targetTokens && $current !== '') {
                $chunks[] = $current;
                $current = $paragraph;
                $currentTokens = $paragraphTokens;
            } else {
                $current .= ($current !== '' ? "\n\n" : '') . $paragraph;
                $currentTokens += $paragraphTokens;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_map(fn($text, $idx) => [
            'text' => $text,
            'metadata' => array_merge($baseMetadata, [
                'strategy' => 'paragraph',
                'chunk_index' => $idx,
            ]),
            'token_count' => $this->estimateTokens($text),
        ], $chunks, array_keys($chunks));
    }

    /**
     * Kod-xəbərdar chunking: funksiya/sinif/metod sərhədlərindən bölür.
     * PHP faylları üçün işləyir.
     */
    public function codeChunking(string $code, array $baseMetadata = []): array
    {
        // PHP funksiyaları və metodlarını çıxar
        $pattern = '/
            (?:\/\*\*[\s\S]*?\*\/\s*)?     # İsteğe bağlı docblock
            (?:(?:public|private|protected|static|abstract|final)\s+)*  # Modifikatorlar
            function\s+\w+\s*\([^)]*\)     # Funksiya imzası
            (?:\s*:\s*[\w\\\\|?]+)?        # İsteğe bağlı qaytarma növü
            \s*\{                          # Açılış mötərizə
        /x';

        // Bütün funksiya mövqelərini tap
        preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
        $positions = array_column($matches[0], 1);

        if (empty($positions)) {
            // Funksiya tapılmadı, rekursiv chunking-a düş
            return $this->recursiveChunking($code, 512, 0, $baseMetadata);
        }

        $chunks = [];

        for ($i = 0; $i < count($positions); $i++) {
            $start = $positions[$i];
            $end = $i + 1 < count($positions) ? $positions[$i + 1] : strlen($code);

            $chunkText = substr($code, $start, $end - $start);
            $functionName = $this->extractFunctionName($chunkText);

            $chunks[] = [
                'text' => trim($chunkText),
                'metadata' => array_merge($baseMetadata, [
                    'strategy' => 'code',
                    'function_name' => $functionName,
                    'language' => 'php',
                ]),
                'token_count' => $this->estimateTokens($chunkText),
            ];
        }

        return $chunks;
    }

    private function extractFunctionName(string $code): ?string
    {
        preg_match('/function\s+(\w+)/', $code, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Token sayını təxmin et. Kobud yaxınlaşma: 1 token ≈ 4 simvol.
     * İstehsal üçün Python sidecar vasitəsilə tiktoken istifadə et ya da mühafizəkar say.
     */
    private function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
```

### PDF Mətn Çıxarmasının İdarəsi

```php
<?php

namespace App\Services\RAG;

use Illuminate\Support\Facades\Process;

class TextExtractor
{
    /**
     * Müxtəlif fayl formatlarından mətn çıxar.
     */
    public function extract(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match($extension) {
            'pdf'  => $this->extractPdf($filePath),
            'docx' => $this->extractDocx($filePath),
            'html', 'htm' => $this->extractHtml($filePath),
            'md'   => $this->extractMarkdown($filePath),
            'txt'  => file_get_contents($filePath),
            'php', 'py', 'js', 'ts' => $this->extractCode($filePath),
            default => throw new \RuntimeException("Dəstəklənməyən fayl növü: {$extension}"),
        };
    }

    private function extractPdf(string $filePath): string
    {
        // pdftotext (poppler-utils) istifadə edir — əksər Linux serverlərdə mövcuddur
        // apt-get install poppler-utils
        $result = Process::run(['pdftotext', '-layout', '-nopgbrk', $filePath, '-']);

        if (!$result->successful()) {
            throw new \RuntimeException('PDF çıxarma uğursuz oldu: ' . $result->errorOutput());
        }

        $text = $result->output();

        // Ümumi PDF artefaktlarını təmizlə
        $text = $this->cleanPdfText($text);

        return $text;
    }

    private function cleanPdfText(string $text): string
    {
        // Səhifə nömrələrini sil (öz sətirindəki tək rəqəmlər)
        $text = preg_replace('/^\s*\d+\s*$/m', '', $text);

        // Sətir kəsimlərindən defisi normallaşdır (söz- \nkəsimi → sözkəsimi)
        $text = preg_replace('/-\s*\n\s*/', '', $text);

        // Artıq boş sətirləri sil
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Ümumi başlıq/altbilgi nümunələrini sil (sənədə görə xüsusiləşdir)
        // məs., hər səhifədə görünən "Şirkət Adı - Gizli"
        // $text = preg_replace('/^Şirkət Adı - Gizli$/m', '', $text);

        return trim($text);
    }

    private function extractDocx(string $filePath): string
    {
        // Shell vasitəsilə python-docx istifadə edir (ya da PHP DOCX kitabxanası istifadə et)
        // Alternativ: mammoth.js, pandoc
        $result = Process::run(['pandoc', '--to=plain', $filePath]);

        if (!$result->successful()) {
            throw new \RuntimeException('DOCX çıxarma uğursuz oldu: ' . $result->errorOutput());
        }

        return $result->output();
    }

    private function extractHtml(string $filePath): string
    {
        $html = file_get_contents($filePath);

        // Skriptləri, üslubları, naviqasiya elementlərini sil
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $html = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $html);
        $html = preg_replace('/<nav\b[^>]*>[\s\S]*?<\/nav>/i', '', $html);

        // Blok elementlərini yeni sətirə çevir
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // Qalan teqləri sil
        $text = strip_tags($html);

        // HTML varlıqlarını deşifrə et
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\n{3,}/', "\n\n", trim($text));
    }

    private function extractMarkdown(string $filePath): string
    {
        $content = file_get_contents($filePath);

        // Embedding üçün markdown formatlamasını sil
        // Mətn məzmununu saxla
        $content = preg_replace('/^#{1,6}\s+/m', '', $content); // Başlıqlar
        $content = preg_replace('/\*\*(.+?)\*\*/', '$1', $content); // Qalın
        $content = preg_replace('/\*(.+?)\*/', '$1', $content); // Kursiv
        $content = preg_replace('/`(.+?)`/', '$1', $content); // Sətiriçi kod
        $content = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $content); // Keçidlər

        return $content;
    }

    private function extractCode(string $filePath): string
    {
        // Kod faylları üçün olduğu kimi saxla (sintaksis mənalıdır)
        return file_get_contents($filePath);
    }
}
```

### Üst-üstə Düşmə İdarəsi və Chunk Metadatası

```php
<?php

namespace App\Services\RAG;

class ChunkMetadataService
{
    /**
     * Chunk-ları mövqe və struktur metadatası ilə zənginləşdir.
     * Bu metadata dəqiq istinadlara və kontekst retrieval-ına imkan verir.
     *
     * @param array $chunks ChunkingService::chunk()-dan
     * @param array $documentMeta Sənəd səviyyəsindəki metadata
     * @return array Zənginləşdirilmiş chunk-lar
     */
    public function enrich(array $chunks, array $documentMeta = []): array
    {
        $totalChunks = count($chunks);
        $enriched = [];

        foreach ($chunks as $index => $chunk) {
            $enriched[] = [
                'text' => $chunk['text'],
                'token_count' => $chunk['token_count'],
                'metadata' => array_merge($chunk['metadata'] ?? [], $documentMeta, [
                    // İstinadlar üçün mövqe metadatası
                    'chunk_index' => $index,
                    'total_chunks' => $totalChunks,
                    'is_first' => $index === 0,
                    'is_last' => $index === $totalChunks - 1,
                    'position_pct' => round(($index / max(1, $totalChunks - 1)) * 100),

                    // Dublikat aşkarlaması üçün məzmun barmaq izi
                    'content_hash' => md5($chunk['text']),

                    // Orijinal sənəddəki simvol mövqeləri
                    'char_start' => $chunk['metadata']['start_char'] ?? null,
                    'char_end' => $chunk['metadata']['end_char'] ?? null,
                ]),
            ];
        }

        return $enriched;
    }

    /**
     * Başlıq kontekstini çıxar və enjekte et.
     * Chunk başlıq ehtiva etmədikdə, ən yaxın əvvəlki başlığı tap.
     * Bu, başlıqların semantik ağırlıq daşıması sayəsında retrieval-ı əhəmiyyətli dərəcədə yaxşılaşdırır.
     *
     * @param string $fullText Tam sənəd mətni
     * @param array $chunks char_start metadatalı chunk-lar
     * @return array heading_context metadata-ya əlavə edilmiş chunk-lar
     */
    public function injectHeadingContext(string $fullText, array $chunks): array
    {
        // Bütün başlıqları mövqeləri ilə çıxar
        preg_match_all(
            '/^(#{1,6})\s+(.+)$/m',
            $fullText,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $headings = [];
        foreach ($matches[2] as $i => $match) {
            $headings[] = [
                'text' => $match[0],
                'level' => strlen($matches[1][$i][0]),
                'position' => $match[1],
            ];
        }

        return array_map(function ($chunk) use ($headings) {
            $charStart = $chunk['metadata']['char_start'] ?? 0;

            // Bu chunk-dan əvvəlki ən yaxın başlığı tap
            $nearestHeading = null;
            foreach ($headings as $heading) {
                if ($heading['position'] <= $charStart) {
                    $nearestHeading = $heading;
                } else {
                    break;
                }
            }

            if ($nearestHeading) {
                $chunk['metadata']['section_heading'] = $nearestHeading['text'];
                $chunk['metadata']['heading_level'] = $nearestHeading['level'];

                // Daha yaxşı semantik uyğunluq üçün başlığı mətndən əvvəl əlavə et
                // Yalnız chunk artıq başlıqla başlamırsa
                if (!preg_match('/^#{1,6}\s/', $chunk['text'])) {
                    $chunk['text'] = "## {$nearestHeading['text']}\n\n{$chunk['text']}";
                }
            }

            return $chunk;
        }, $chunks);
    }

    /**
     * İyerarxik (valideyn-uşaq) chunk strukturu yarat.
     * Dəqiq retrieval üçün kiçik chunk-lar, yaratma konteksti üçün böyük chunk-lar.
     */
    public function buildHierarchical(
        string $text,
        array $documentMeta = [],
        int $parentTokens = 1024,
        int $childTokens = 256,
    ): array {
        $chunkingService = new ChunkingService();

        // Böyük valideyn chunk-ları
        $parentChunks = $chunkingService->recursiveChunking($text, $parentTokens, 0, $documentMeta);

        $hierarchy = [];

        foreach ($parentChunks as $parentIndex => $parent) {
            // Hər valideyndən kiçik uşaq chunk-ları
            $childChunks = $chunkingService->recursiveChunking(
                $parent['text'],
                $childTokens,
                20,
                $documentMeta
            );

            $hierarchy[] = [
                'parent' => array_merge($parent, [
                    'metadata' => array_merge($parent['metadata'], [
                        'parent_index' => $parentIndex,
                        'type' => 'parent',
                    ]),
                ]),
                'children' => array_map(function ($child, $childIndex) use ($parentIndex, $parent) {
                    return array_merge($child, [
                        'metadata' => array_merge($child['metadata'], [
                            'parent_index' => $parentIndex,
                            'child_index' => $childIndex,
                            'type' => 'child',
                            // Retrieval-ın onu qaytara bilməsi üçün valideyn mətnini saxla
                            'parent_text' => $parent['text'],
                        ]),
                    ]);
                }, $childChunks, array_keys($childChunks)),
            ];
        }

        return $hierarchy;
    }
}
```

---

## Memarlar üçün Fikirlər

### Chunk-Sorğu Ölçü Asimmetriyası

Embedding modelləri müəyyən bir paylanma mətnlərindən əyidilir. 3 sözlük sorğu ("python async await") 500-tokenli sənəd chunk-ından fərqli statistik profil daşıyır. Embedding fəzası onları natamam şəkildə xəritəyə çevirir.

**Həllər**:
1. **HyDE**: Sorğuya cavab verəcək hipotetik sənəd yarat, onu embed et (ölçü boşluğunu bağlayır)
2. **Cohere-nin input_type-ı**: `search_query` vs `search_document` giriş növləri bu boşluğu bağlayan asimmetrik embeddinglər yaradır
3. **Sorğu genişlənməsi**: Embed etmədən əvvəl qısa sorğuları sinonimlər və əlaqəli terminlərlə genişləndir

### Kontekst-Xəbərdar Qabaqdan Əlavə Etmə

Güclü praktik hiylə: embed etmədən əvvəl hər chunk-a sənəd başlığını və bölmə başlığını qabağa əlavə et:

```php
$enrichedText = "Sənəd: {$documentTitle}\nBölmə: {$sectionHeading}\n\n{$chunkText}";
$embedding = $embeddingService->embed($enrichedText);
```

Bu, "bölmə 3.2 sonlandırma haqqında nə deyir?" sualının, "bölmə 3.2" chunk mətninin özündə görünmürsə belə, düzgün chunk-ı əldə etməsini təmin edir.

### Cədvəllər üçün Chunking

```php
// Daha yaxşı embedding üçün markdown cədvəlini təbii dilə çevir
public function tableToNaturalLanguage(string $markdownTable): string
{
    $lines = array_filter(explode("\n", trim($markdownTable)));
    $headers = array_map('trim', explode('|', trim($lines[0], '|')));
    $rows = array_slice(array_values($lines), 2); // Başlıq ayırıcısını atla

    $sentences = [];
    foreach ($rows as $row) {
        $values = array_map('trim', explode('|', trim($row, '|')));
        $pairs = array_combine($headers, $values);
        $sentence = implode(', ', array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys($pairs),
            array_values($pairs)
        ));
        $sentences[] = $sentence . '.';
    }

    return implode(' ', $sentences);
}
```

### Chunk Keyfiyyətinin İzlənməsi

Zəif chunking-i müəyyənləşdirmək üçün chunk səviyyəsindəki metrikaleri izlə:

```sql
-- Çox qısa olan chunk-ları tap (çox güman ki bölmə artefaktlarıdır)
SELECT AVG(token_count), MIN(token_count), MAX(token_count),
       PERCENTILE_CONT(0.05) WITHIN GROUP (ORDER BY token_count) as p5,
       PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY token_count) as p95
FROM knowledge_chunks;

-- Heç vaxt əldə edilməyən chunk-ları tap (zəif keyfiyyət siqnalı)
-- Sorğuları işlətdikdən sonra, hansı chunk_id-lər rag_queries.retrieved_chunks-da görünür?
```
