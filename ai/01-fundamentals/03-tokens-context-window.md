# Token-lər və Context Window-lar — Dərin Araşdırma (Junior)

> Hədəf auditoriyası: Tokenizasiyanı dərin səviyyədə başa düşməli və context-i ağıllı idarə edən istehsal sistemləri qurmalı olan senior developerlər və arxitektorlar.

---

## Mündəricat

1. [Token Nədir?](#what-is-a-token)
2. [Byte-Pair Encoding Dərindən](#byte-pair-encoding-in-depth)
3. [Niyə Token ≠ Söz](#why-token--word)
4. [Context Window-lar — Texniki Gerçəklik](#context-windows--technical-reality)
5. [Context Ölçüsünün Yaddaş Nəticələri](#memory-implications-of-context-size)
6. [Context Dolu Olduğunda Nə Baş Verir](#what-happens-when-context-is-full)
7. [Kəsmə Strategiyaları](#truncation-strategies)
8. [Pozisional Encoding və Uzun Context](#positional-encoding-and-long-context)
9. ["Ortada İtmək" Problemi](#the-lost-in-the-middle-problem)
10. [Praktiki Token Sayma](#practical-token-counting)
11. [Laravel Kodu: Token Sayıcı Utility](#laravel-code-token-counter-utility)
12. [Laravel Kodu: Context Window Meneceri](#laravel-code-context-window-manager)
13. [Arxitektor Mülahizələri](#architect-considerations)

---

## Token Nədir?

Token, dil modelinin işlətdiyi mətnin əsas vahididir. Bu nə söz, nə də simvoldur — modelin korpusunda öyrədilmiş sıxışdırma alqoritmi ilə müəyyən edilən **dəyişən uzunluqlu alt-söz vahididir**.

Konkret nümunələr (GPT-4/Claude tokenizasiyası, təxmini):

```
Mətn                    Token-lər   Say
─────────────────────────────────────────
"hello"                 [hello]    1
"Hello"                 [Hello]    1 ("hello"-dan fərqli token)
"HELLO"                 [HEL][LO]  2
"tokenization"          [token][ization]  2
"untokenizable"         [un][token][izable]  3
" tokenization"         [ tokenization]  1 (boşluq token-in hissəsidir!)
"2024"                  [2024]  1
"20245"                 [2024][5]  2
"1,234,567"             [1][,][234][,][567]  5
"Hello, World!"         [Hello][,][ World][!]  4
"foo@bar.com"           [foo][@][bar][.][com]  5
"\n\n"                  [\n\n]  1 (iki yeni sətir = bir token)
"    "                  [ ][ ][ ][ ]  1-4 (tokenizatordan asılı)
"ChatGPT"               [Chat][G][PT]  3
"JavaScript"            [JavaScript]  1
"TypeScript"            [Type][Script]  2
```

**Kod, nəsrə nisbətdə çox fərqli tokenizasiya edilir:**

```
PHP kodu: "$user->getName()"
Token-lər: [$][user][->][getName][()]  ≈ 5 token

PHP kodu: "public function handle(Request $request): Response"
Token-lər: ≈ 9 token

Kobud qayda: 1 token ≈ İngilis nəsrinin 4 simvolu
             1 token ≈ kodun 3-4 simvolu (daha çox işarə)
             100 token ≈ İngiliscənin 75 sözü
```

---

## Byte-Pair Encoding Dərindən

BPE (Byte-Pair Encoding), tokenizator lüğəti qurmaq üçün istifadə edilən alqoritmdir. Onu başa düşmək tokenizasiya davranışını proqnozlaşdırmağa və sürprizlərdən qaçınmağa kömək edir.

### Alqoritm

```
1. Başlanğıc: lüğət = bütün ayrı-ayrı baytlar (256 token)

2. Korpus analizi: bütün öyrənmə datasetini işlə
   mətn korpusu: "low low low lowest newest"

3. Ən tez-tez rast gəlinən bayt cütünü tap:
   "lo" ən çox rast gəlinir → tək token "lo"-ya birləşdir
   lüğətə əlavə olunur: "lo"
   korpus olur: "lo w lo w lo w lo west newest"

4. Növbəti ən tez-tez rast gəlinən cütü tap:
   "lo w" → "low"
   lüğətə əlavə olunur: "low"
   korpus: "low low low lowest newest"

5. Növbəti tap:
   "low" ardından "e" → "lowe"
   ...

6. Lüğət hədəf ölçüyə çatana qədər təkrar et
   GPT-2:    50.257 token
   GPT-4:   100.277 token
   Claude:  ~100.000 token (dəqiq ölçü açıqlanmayıb)
   LLaMA:   32.000 token (kiçik = uzun ardıcıllıqlar)
```

### Praktiki Nəticə: Tokenizasiya Qeyri-Açıqdır

```
"unfortunately" sözü bunlar ola bilər:
  - Öyrənmə məlumatlarında tez-tez görünürsə 1 token
  - [un][fortunate][ly] — görünmürsə 3 token

Böyük/kiçik hərflər önəmlidir:
  "cat" → 1 token
  "Cat" → 1 token (fərqli ID)
  "CAT" → 2-3 token (daha az yayılmış nümunə)

Aparıcı boşluq önəmlidir:
  "Paris" → token ID 17370
  " Paris" → token ID 3442 (tamamilə fərqli token!)
  Buna görə sözlərin ətrafına boşluq əlavə etmək model davranışını dəyişdirə bilər.

Rəqəmlər qeyri-ardıcıldır:
  "1", "2", ..., "9" → hər biri 1 token (tək rəqəmlər)
  "10"-dan müəyyən rəngə qədər → hər biri 1 token
  "1000" → 1 ya da 2 token ola bilər
  "10000" → çox güman 2 token
  "1,000,000" → çoxlu token (durğu işarələri bölür)

Buna görə modellər arifmetika ilə çətinlik çəkir: onlar
heç vaxt ayrı-ayrı rəqəmləri görmürlər — sıxışdırılmış ardıcıllıqları görürlər.
```

---

## Niyə Token ≠ Söz

Bu fərqin prompt dizayn etməniz və model davranışını şərh etməniz üçün dərin nəticələri var.

### Simvol Səviyyəsindəki Tapşırıqlar Çətindir

```
Sual: "'strawberry' sözündə neçə 'r' var?"
Düzgün cavab: 3

Model görür: ["st"][ "raw"]["berry"] ← təxmini tokenizasiya
Model heç vaxt ayrı-ayrı simvolları görmür!
Alt-söz token-lərindən simvol məzmunu haqqında DÜŞÜNMƏK məcburiyyətindədir.
Bu həqiqətən çətindir, sanki buzlu şüşənin arxasından baş aşağı
bir sözün hərflərini saymağınızı istəyirlər.

Eynilə çətin olanlar:
- Hərfləri saymaq: "'programming'-də neçə hərf var?"
- Sözü tərsinə çevirmək: "'hello'-nu tərsinə çevir"
- Sezar şifri: hər hərfi 3 irəli sürüş
- Anagram aşkarlaması
```

### Token Büdcəsi vs Söz Büdcəsi

```
Sənəd: "The quick brown fox jumps over the lazy dog."
Söz:    9 söz
Token:  ~10 token (durğu işarələri sayılır, boşluqlar daxildir)

Məqalə (1000 söz) ≈ 1.300-1.500 token
Kitab fəsli (5000 söz) ≈ 6.500-7.500 token
Tam roman (100.000 söz) ≈ 130.000-150.000 token

200k context window tutacaq:
  ≈ 150.000 İngilis nəsr sözü
  ≈ 500 səhifəlik mətn
  ≈ 6.000-8.000 sətir kod
  ≈ Orta ölçülü kod bazası
```

### Çoxdilli Tokenizasiya Fərqlilikləri

İngilis dilindən öyrədilmiş tokenizatorlar qeyri-Latın hərfləri üçün səmərəsizdir:

```
İngilis: "Hello, how are you?"     ≈ 5 token
Fransız: "Bonjour, comment allez-vous?" ≈ 8 token
Çin:     "你好，你怎么样？"          ≈ 14-20 token (hər simvol ≈ 2 token)
Ərəb:    "مرحبا، كيف حالك؟"        ≈ 20-30 token
Yapon:   "こんにちは、お元気ですか？" ≈ 15-25 token

Nəticələr:
1. Çin/Yapon istifadəçiləri eyni məzmun üçün token baxımından 3-4x artıq ödəyir
2. Latın olmayan hərflər üçün effektiv context window daha kiçikdir
3. Model performansı az tokenizasiya edilmiş dillər üçün bir az zəif ola bilər
   (model qeyri-İngilis mətn haqqında düşünmək üçün daha az "token büdcəsinə" malikdir)
```

---

## Context Window-lar — Texniki Gerçəklik

### "Context Window" Əslində Nə Deməkdir

Context window, modelin bir anda diqqət edə biləcəyi maksimum token sayıdır. Window xaricindəki hər şey modelə tamamilə görünməzdir.

```
Context window = Giriş token-ləri + Çıxış token-ləri

Claude Sonnet 4.6 üçün (200k context):
  Maksimum giriş:  ~196.000 token (çıxış üçün yer saxlamaqla)
  Maksimum çıxış: API çağırışı başına 8.192 token-ə qədər

200k context sorğusu üçün:
  Əgər prompt = 150.000 token isə, maksimum çıxış = 8.192 token
  (200k BİRLƏŞİK hədddir, ayrı deyil)
```

### KV Cache Yaddaş Xərci

Context-dəki hər token bütün qatlar üçün Key və Value matrislərinin saxlanılmasını tələb edir:

```
Token başına KV cache yaddaşı:
  = 2 (K və V) × qat_sayı × d_head × baş_sayı × float_başına_bayt
  
Tipik 70B model üçün:
  = 2 × 80 × 128 × 64 × 2 bayt (fp16)
  = 2 × 80 × 8192 × 2 bayt
  = ~2,6 MB token başına

200k context üçün:
  = 200.000 × 2,6 MB = 520 GB!
  
Əməldə:
  - Modellər bunu 4-8x azaltmaq üçün grouped-query attention (GQA) istifadə edir
  - Kvantizasiya 4-8 bitə endirir
  - Yenə də: uzun context-lər böyük GPU yaddaşı tələb edir

Buna görə 200k context eyni modeldə 10k context-dən token başına daha baha başa gəlir
— yalnız hesablama deyil, yaddaş bant genişliyi də.
```

---

## Context Dolu Olduğunda Nə Baş Verir

Context window-nu aşan bir sorğu göndərdiyinizdə, bir neçə seçiminiz var — lakin model API bunu avtomatik etməyəcək. Siz idarə etməlisiniz.

```
Seçim 1: SƏRT KƏSMƏ (sadə, pis)
  Tarixin əvvəlindən mesajları kəs
  Problem: kritik context-i (ilk tapşırıqlar, istifadəçi üstünlükləri) itirir

Seçim 2: ORTADAN KES (daha yaxşı)
  Sistem promptunu + son mesajları saxla, ortanı kəs
  Problem: erkən context-ə istinad edən söhbətlər uğursuz olur

Seçim 3: XÜLASƏLƏŞDİR VƏ SIXıŞDIR (yaxşı)
  Köhnə söhbəti dövrü olaraq kompakt xülasəyə cəmlə
  Saxla: sistem promptu + xülasə + son N mesaj
  Problem: əlavə LLM çağırışı tələb edir; xülasələr detal itirə bilər

Seçim 4: RAG/ƏLDƏETMƏ (uzun sessiyalar üçün ən yaxşısı)
  Bütün mesajları vektoral verilənlər bazasında saxla
  Hər yeni sorğu üçün yalnız lazımlı olanları al
  Problem: daha mürəkkəb infrastruktur; semantik boşluqlar mümkündür

Seçim 5: SÜRÜŞƏn PƏNCƏRƏ (sadə, söhbət üçün effektiv)
  Həmişə son N token + sistem promptunu saxla
  Hədd yaxınlaşanda ən köhnə mesajları sil
  Problem: köhnə context-ə açıq istinad edə bilmir
```

---

## Kəsmə Strategiyaları

### Prioritetə Əsaslanan Kəsmə

Bütün context eyni önəmdə deyil. Yaxşı kəsmə strategiyası yüksək prioritetli məzmunu qoruyur:

```
Prioritet sırası (ən yüksəkdən ən aşağıya):
  1. Sistem promptu (heç vaxt kəsmə)
  2. Ən son istifadəçi mesajı (heç vaxt kəsmə)
  3. Ən son köməkçi cavabları (saxlamağa çalış)
  4. Cari növbənin alət çağırış nəticələri
  5. Son söhbət tarixi
  6. Köhnə söhbət tarixi (əvvəlcə kəs)
  7. Arxa plan context / RAG parçaları (ikinci kəs)
```

### Xülasəyə Alma Strategiyası

```
Tetikleyici: söhbət context window-nun 80%-ni keçir

1. Son 10 növbədən köhnə mesajları al
2. Ucuz modelə (Haiku) göndər:
   "Bu söhbət tarixini qısaca xülasələyin, gələcək istinad üçün lazımlı
    əsas faktları, qərarları və context-i qoruyaraq."
3. Köhnə mesajları tək "xülasə" mesajı ilə əvəz et
4. Sıxlaşdırılmış context ilə söhbəti davam etdir

Mesaj formatı:
  {"role": "user", "content": "[SÖHBƏTİN XÜLASƏSİ]\n{xülasə}\n[XÜLASƏNİN SONU]"}
```

---

## Pozisional Encoding və Uzun Context

Müasir modellər Q/K vektorlarının fırlanması vasitəsilə mövqeyi işləyən **RoPE (Rotary Position Embedding)** istifadə edir. Bu, modellərə nisbi məsafə üçün təbii induktiv meyl verir.

### Uzun Context-in Çətinliyi

200k window-la belə, modellər onu mükəmməl istifadə etmir:

```
Context alınması tapşırıqlarında performans:

Context-dəki mövqe   Geri çağırma dəqiqliyi (tipik)
────────────────────────────────────────────────
Başlanğıc (0-10%)     95-99%  ← "Öncelik effekti"
Orta (40-60%)         60-80%  ← "Ortada itib-getmək"
Son (90-100%)         90-98%  ← "Yeniliyə meyl effekti"

Bu deməkdir: ən vacib məlumat context-in
BAŞINDA ya da SONUNDA olmalıdır, ortada basdırılmış deyil.
```

### Praktiki Uzun Context Mövqeləndirməsi

```
OPTIMAL CONTEXT STRUKTURU:

[Sistem promptu — ən kritik tapşırıqlar]
[Ən lazımlı alınan sənədlər/context]
[Söhbət tarixi — ən köhnədən ən yeniyə]
[Ən son mesajlar — ən kritik]
[Son tapşırıq / cari sorğu]

ƏN PISI STRUKTUR:
[Sistem promptu]
[Köhnə söhbət]
[Vacib istinad sənədi] ← ORTADA BASDIRILMIŞ, görməzdən gəlinə bilər
[Daha çox köhnə söhbət]
[Cari sorğu]
```

---

## "Ortada İtmək" Problemi

"Lost in the Middle" (Liu et al., 2023) araşdırma məqaləsi göstərdi ki, LLM-lər sistematik olaraq uzun context-lərin ortasında görünən məlumatda performansını azaldır, hətta bu məlumat cavab olsa belə.

```
Təcrübə: Çox sənədli sual-cavab
  - Cavab sənədini 20-dən 1-ci mövqeyə qoy:  92% dəqiqlik
  - Cavab sənədini 20-dən 10-cu mövqeyə qoy: 67% dəqiqlik
  - Cavab sənədini 20-dən 20-ci mövqeyə qoy: 90% dəqiqlik

RAG dizaynı üçün nəticə:
  N sənəd alarkən, onları sadəcə təsadüfi birləşdirməyin.
  Ən yüksək-uyğunluqlu parçaları context-in BAŞINA,
  ya da sorğudan əvvəl SONUNA qoyun. Ortaya heç vaxt basdırmayın.

Uzun sənədlər üçün nəticə:
  100 səhifəlik sənəd haqqında sual verdikdə, model
  40-60-cı səhifələrdəki məlumatı qaçıra bilər. Lazımlı
  bölmələri önəmli mövqelərə gətirən parçalama strategiyaları düşünün.
```

---

## Praktiki Token Sayma

### Qiymətləndirmə Düsturları

```
Sürətli qiymətlər:
  İngilis nəsri:    1 token ≈ 4 simvol ≈ 0,75 söz
  Kod (PHP/JS):     1 token ≈ 3 simvol
  JSON:             1 token ≈ 3-4 simvol (çox durğu işarəsi)
  Çin/Yapon:        1 token ≈ 1,5 simvol

Promptlar üçün praktiki qaydalar:
  Qısa tapşırıq:       50-200 token
  Orta sistem promptu: 200-500 token
  Uzun sistem promptu: 500-2000 token
  Tək email:           200-500 token
  Veb səhifə:          1000-5000 token
  Kitab fəsli:         3000-8000 token
```

---

## Laravel Kodu: Token Sayıcı Utility

```php
<?php

declare(strict_types=1);

namespace App\AI\Tokens;

/**
 * BPE-yaxınlaşma yanaşması istifadə edən token sayıcı utility.
 *
 * İstehsal istifadəsi üçün modelin tokenize endpoint-ini çağırmağı
 * ya da düzgün BPE kitabxanasından istifadə etməyi nəzərdən keçirin.
 * Bu tətbiq uçuş öncəsi context window yoxlamaları üçün uyğun sürətli
 * yaxınlaşma təmin edir.
 *
 * Dəqiq Claude token sayımları üçün istifadədin: count_tokens parametri
 * ilə POST /v1/messages, ya da Anthropic-in tokenizatorunu.
 */
class TokenCounter
{
    /**
     * Müxtəlif məzmun növləri üçün token başına orta simvol sayı.
     * Bunlar empirik şəkildə əldə edilmiş yaxınlaşmalardır.
     */
    private const CHARS_PER_TOKEN = [
        'english'  => 4.0,
        'code'     => 3.0,
        'json'     => 3.5,
        'markdown' => 3.8,
        'chinese'  => 1.5,
        'arabic'   => 1.2,
        'mixed'    => 3.5,
    ];

    /**
     * Bir sətir üçün token sayını qiymətləndir.
     *
     * Düzgün yaxınlaşma nisbəti seçmək üçün məzmun aşkarlamasından istifadə edir.
     */
    public function estimate(string $text, string $contentType = 'auto'): int
    {
        if (empty($text)) {
            return 0;
        }

        if ($contentType === 'auto') {
            $contentType = $this->detectContentType($text);
        }

        $charsPerToken = self::CHARS_PER_TOKEN[$contentType] ?? self::CHARS_PER_TOKEN['mixed'];

        // Əsas qiymət
        $estimate = (int) ceil(mb_strlen($text) / $charsPerToken);

        // Ümumi nümunələr üçün düzəlişlər
        $estimate += $this->countSpecialTokens($text);

        return max(1, $estimate);
    }

    /**
     * Mesajlar massivi üçün token-ləri qiymətləndir (Claude/OpenAI formatı).
     *
     * Hər mesajın rol, ayırıcılar və s. üçün əlavə token-ləri var.
     * Claude API mesaj başına əlavə yükü: ~4 token
     * Sorğunun özü üçün əlavə yük: ~3 token
     */
    public function estimateMessages(array $messages): int
    {
        $total = 3; // Sorğu səviyyəsindəki əlavə yük

        foreach ($messages as $message) {
            $total += 4; // Mesaj başına əlavə yük (rol + ayırıcılar)

            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $total += $this->estimate($content);
            } elseif (is_array($content)) {
                // Çox hissəli məzmun (mətn + şəkillər)
                foreach ($content as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $total += $this->estimate($part['text'] ?? '');
                    } elseif (($part['type'] ?? '') === 'image') {
                        $total += $this->estimateImageTokens($part);
                    }
                }
            }
        }

        return $total;
    }

    /**
     * Base64 ilə kodlanmış şəkil üçün token-ləri qiymətləndir.
     *
     * Claude plitəyə əsaslanan sistem istifadə edir:
     * - Şəkillər 512×512 piksel plitəyə bölünür
     * - Hər plitə 1500 token dəyərindədir
     * - Şəkil başına 1500 token əsas xərc var
     *
     * @see https://docs.anthropic.com/claude/docs/vision
     */
    public function estimateImageTokens(array $imagePart): int
    {
        // Metadatamız varsa, dəqiq hesablama üçün istifadə et
        if (isset($imagePart['width'], $imagePart['height'])) {
            return $this->calculateVisionTokens(
                $imagePart['width'],
                $imagePart['height']
            );
        }

        // Base64 sətrimiz varsa, fayl ölçüsündən qiymətləndir
        if (isset($imagePart['source']['data'])) {
            $base64 = $imagePart['source']['data'];
            // Fayl ölçüsündən piksel sayını yaxınlaşdır
            // Orta foto: piksel başına ~3 bayt (JPEG sıxışdırılmış)
            $bytes = strlen($base64) * 0.75; // base64 → bayt
            $estimatedPixels = $bytes / 3;
            $estimatedWidth = (int) sqrt($estimatedPixels * 1.33); // 4:3 nisbət fərz et
            $estimatedHeight = (int) ($estimatedWidth / 1.33);

            return $this->calculateVisionTokens($estimatedWidth, $estimatedHeight);
        }

        // Mühafizəkar standart: orta ölçülü şəkil fərz et
        return 1500;
    }

    /**
     * Claude-un plitəyə əsaslanan qiymətləndirilməsindən istifadə edərək dəqiq görüntü token-lərini hesabla.
     */
    public function calculateVisionTokens(int $width, int $height): int
    {
        // Claude şəkilləri en-boy nisbətini qoruyaraq 1568×1568-ə sığdırmaq üçün ölçüsünü dəyişir
        $maxDimension = 1568;
        if ($width > $maxDimension || $height > $maxDimension) {
            $scale = $maxDimension / max($width, $height);
            $width = (int) ($width * $scale);
            $height = (int) ($height * $scale);
        }

        // Həmçinin, qısa kənar minimum 200px-dir
        if (min($width, $height) < 200) {
            $scale = 200 / min($width, $height);
            $width = (int) ($width * $scale);
            $height = (int) ($height * $scale);
        }

        // 512×512 plitələri hesabla
        $tilesX = (int) ceil($width / 512);
        $tilesY = (int) ceil($height / 512);
        $totalTiles = $tilesX * $tilesY;

        // Plitə başına 1500 token + 1500 əsas xərc
        return ($totalTiles + 1) * 1500;
    }

    /**
     * Mesajlar dəstinin context window-na sığıb-sığmadığını yoxla.
     *
     * @param int $contextWindow   Modelin ümumi context window-u
     * @param int $reserveForOutput Model çıxışı üçün saxlanacaq token-lər
     */
    public function willFit(
        array $messages,
        int $contextWindow = 200_000,
        int $reserveForOutput = 4_096
    ): bool {
        $estimated = $this->estimateMessages($messages);
        return $estimated <= ($contextWindow - $reserveForOutput);
    }

    /**
     * Ətraflı token büdcəsi analizi al.
     */
    public function analyze(
        array $messages,
        int $contextWindow = 200_000,
        int $reserveForOutput = 4_096
    ): array {
        $estimated = $this->estimateMessages($messages);
        $available = $contextWindow - $reserveForOutput;
        $remaining = $available - $estimated;

        return [
            'estimated_input_tokens' => $estimated,
            'context_window' => $contextWindow,
            'reserved_for_output' => $reserveForOutput,
            'available_for_input' => $available,
            'remaining_tokens' => $remaining,
            'utilization_percent' => round(($estimated / $available) * 100, 1),
            'will_fit' => $remaining >= 0,
            'approximate' => true, // Həmişə yaxınlaşma olaraq işarələ
        ];
    }

    private function detectContentType(string $text): string
    {
        // Çin/Yapon simvollarını yoxla
        $cjkChars = preg_match_all('/[\x{4e00}-\x{9fff}\x{3040}-\x{309f}\x{30a0}-\x{30ff}]/u', $text);
        if ($cjkChars > mb_strlen($text) * 0.2) {
            return 'chinese';
        }

        // Ərəb simvollarını yoxla
        $arabicChars = preg_match_all('/[\x{0600}-\x{06ff}]/u', $text);
        if ($arabicChars > mb_strlen($text) * 0.2) {
            return 'arabic';
        }

        // Kod nümunələrini yoxla
        if (
            str_contains($text, '<?php') ||
            str_contains($text, 'function ') ||
            str_contains($text, 'class ') ||
            preg_match('/[{};]\s*\n/', $text)
        ) {
            return 'code';
        }

        // JSON-u yoxla
        $trimmed = trim($text);
        if (
            (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
        ) {
            return 'json';
        }

        // Markdown-u yoxla
        if (preg_match('/^#{1,6}\s|^\*\*|^-\s|\[.*\]\(.*\)/m', $text)) {
            return 'markdown';
        }

        return 'english';
    }

    private function countSpecialTokens(string $text): int
    {
        $extra = 0;

        // Yeni sətirlərin çox vaxt öz token-ləri olur
        $newlineCount = substr_count($text, "\n");
        $extra += (int) ($newlineCount * 0.3); // hər yeni sətir ayrı deyil

        // Kod mötərizələri və durğu işarələri çox vaxt ayrı token-lərdir
        $symbolCount = preg_match_all('/[{}()\[\];:,<>\/\\\\]/', $text);
        $extra += (int) ($symbolCount * 0.1);

        return $extra;
    }
}
```

---

## Laravel Kodu: Context Window Meneceri

```php
<?php

declare(strict_types=1);

namespace App\AI\Context;

use App\AI\Tokens\TokenCounter;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Context window həddlərini aşmamaq üçün söhbət tarixini idarə edir.
 *
 * Strategiya: Prioritetə əsaslanan saxlama
 *  1. Sistem promptu həmişə qorunur
 *  2. Son mesajlar həmişə qorunur (konfiqurasiya edilə bilən say)
 *  3. Büdcənin üstündə olduqda orta mesajlar xülasələndirilir ya silinir
 *  4. İstəyə bağlı: ucuz model vasitəsilə xülasəyə alma tetikleyin
 */
class ContextWindowManager
{
    public function __construct(
        private readonly TokenCounter $tokenCounter,
        private readonly int $contextWindow = 200_000,
        private readonly int $reserveForOutput = 8_192,
        private readonly int $warningThresholdPercent = 80,
    ) {}

    /**
     * Context window-a sığan mesajlar massivi hazırla.
     *
     * Lazım gəlsə tarix kəsilmiş, API-ya göndərilməyə hazır
     * mesajlar massivi qaytarır.
     *
     * @param  array  $systemPrompt  Sətir kimi sistem promptu
     * @param  array  $history       Bütün tarixi mesajlar
     * @param  array  $currentMessage Əlavə ediləcək yeni mesaj(lar)
     * @param  string $strategy      'truncate' | 'summarize' | 'sliding_window'
     */
    public function prepare(
        string $systemPrompt,
        array $history,
        array $currentMessage,
        string $strategy = 'sliding_window',
    ): ContextResult {
        $budget = $this->contextWindow - $this->reserveForOutput;

        // Əsas mesajlar strukturunu qur
        $systemTokens = $this->tokenCounter->estimate($systemPrompt);
        $currentTokens = $this->tokenCounter->estimateMessages($currentMessage);

        $availableForHistory = $budget - $systemTokens - $currentTokens;

        if ($availableForHistory <= 0) {
            throw new ContextWindowException(
                "Sistem promptu ({$systemTokens} token) + cari mesaj ({$currentTokens} token) " .
                "artıq context büdcəsini ({$budget} token) aşır"
            );
        }

        // Tarixi mövcud büdcəyə sığdır
        [$fittedHistory, $truncatedCount] = $this->fitHistory(
            $history,
            $availableForHistory,
            $strategy
        );

        $allMessages = array_merge(
            $fittedHistory,
            $currentMessage
        );

        $totalEstimate = $systemTokens + $this->tokenCounter->estimateMessages($allMessages);

        return new ContextResult(
            systemPrompt: $systemPrompt,
            messages: $allMessages,
            estimatedTokens: $totalEstimate,
            contextWindow: $this->contextWindow,
            truncatedMessages: $truncatedCount,
            warningThreshold: $this->warningThresholdPercent,
        );
    }

    /**
     * Söhbət tarixini mövcud token büdcəsinə sığdır.
     *
     * @return array{0: array, 1: int} [mesajlar, kəsilmiş_say]
     */
    private function fitHistory(
        array $history,
        int $tokenBudget,
        string $strategy,
    ): array {
        if (empty($history)) {
            return [[], 0];
        }

        // Hər mesajın token xərci hesabla
        $messagesWithCost = array_map(function (array $message) {
            return [
                'message' => $message,
                'tokens' => $this->tokenCounter->estimateMessages([$message]),
            ];
        }, $history);

        $totalHistoryTokens = array_sum(array_column($messagesWithCost, 'tokens'));

        if ($totalHistoryTokens <= $tokenBudget) {
            // Bütün tarix sığır — kəsmə lazım deyil
            return [$history, 0];
        }

        return match ($strategy) {
            'truncate'       => $this->truncateFromStart($messagesWithCost, $tokenBudget),
            'sliding_window' => $this->slidingWindow($messagesWithCost, $tokenBudget),
            'priority'       => $this->priorityTruncate($messagesWithCost, $tokenBudget),
            default          => $this->slidingWindow($messagesWithCost, $tokenBudget),
        };
    }

    /**
     * Sadə kəsmə: əvvəlcə ən köhnə mesajları çıxar.
     *
     * @return array{0: array, 1: int}
     */
    private function truncateFromStart(array $messagesWithCost, int $budget): array
    {
        $truncated = 0;
        $usedTokens = 0;
        $kept = [];

        // Ən sondan geriyə doğru işlə
        foreach (array_reverse($messagesWithCost) as $item) {
            if ($usedTokens + $item['tokens'] <= $budget) {
                array_unshift($kept, $item['message']);
                $usedTokens += $item['tokens'];
            } else {
                $truncated++;
            }
        }

        return [$kept, $truncated];
    }

    /**
     * Sürüşən pəncərə: həmişə son N tam mübadilə saxla.
     *
     * Söhbət cütlərinin (istifadəçi+köməkçi) heç vaxt bölünməməsini təmin edir.
     *
     * @return array{0: array, 1: int}
     */
    private function slidingWindow(array $messagesWithCost, int $budget): array
    {
        $truncated = 0;
        $usedTokens = 0;
        $kept = [];

        // Mümkün olduqda cütlərə qruplaşdır (istifadəçi mesajı + köməkçi cavabı)
        $pairs = $this->groupIntoPairs($messagesWithCost);

        foreach (array_reverse($pairs) as $pair) {
            $pairTokens = array_sum(array_column($pair, 'tokens'));

            if ($usedTokens + $pairTokens <= $budget) {
                foreach (array_reverse($pair) as $item) {
                    array_unshift($kept, $item['message']);
                }
                $usedTokens += $pairTokens;
            } else {
                $truncated += count($pair);
            }
        }

        return [$kept, $truncated];
    }

    /**
     * Prioritet kəsmə: önəm skorlarına görə kəs.
     *
     * Açıq işarəçiləri olan mesajlar (məs., [IMPORTANT]) daha uzun saxlanılır.
     *
     * @return array{0: array, 1: int}
     */
    private function priorityTruncate(array $messagesWithCost, int $budget): array
    {
        // Hər mesajı önəminə görə qiymətləndir
        $scored = array_map(function (array $item, int $index) use ($messagesWithCost) {
            $content = is_string($item['message']['content'] ?? '')
                ? $item['message']['content']
                : '';

            $score = 0;

            // Yenilik bonusu (son mesajlar daha vacibdir)
            $score += $index / count($messagesWithCost) * 50;

            // Açıq önəm işarəçiləri
            if (str_contains(strtolower($content), '[important]')) {
                $score += 30;
            }

            // Alət nəticələri vacibdir
            if (($item['message']['role'] ?? '') === 'tool') {
                $score += 20;
            }

            // İstifadəçi mesajları köməkçidən bir az daha vacibdir
            if (($item['message']['role'] ?? '') === 'user') {
                $score += 5;
            }

            return array_merge($item, ['score' => $score, 'original_index' => $index]);
        }, $messagesWithCost, array_keys($messagesWithCost));

        // Skora görə azalan sırada çeşidlə, büdcə daxilində ən yüksək skorluları saxla
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $kept = [];
        $usedTokens = 0;
        $truncated = 0;

        foreach ($scored as $item) {
            if ($usedTokens + $item['tokens'] <= $budget) {
                $kept[] = $item;
                $usedTokens += $item['tokens'];
            } else {
                $truncated++;
            }
        }

        // Ardıcıl söhbət axını üçün orijinal sıraya görə yenidən çeşidlə
        usort($kept, fn ($a, $b) => $a['original_index'] <=> $b['original_index']);

        return [array_column($kept, 'message'), $truncated];
    }

    /**
     * Mübadilələri bölməmək üçün mesajları istifadəçi/köməkçi cütlərinə qruplaşdır.
     */
    private function groupIntoPairs(array $messagesWithCost): array
    {
        $pairs = [];
        $currentPair = [];

        foreach ($messagesWithCost as $item) {
            $role = $item['message']['role'] ?? 'user';

            if ($role === 'user' && !empty($currentPair)) {
                $pairs[] = $currentPair;
                $currentPair = [];
            }

            $currentPair[] = $item;
        }

        if (!empty($currentPair)) {
            $pairs[] = $currentPair;
        }

        return $pairs;
    }
}

/**
 * ContextWindowManager::prepare()-dan nəticə obyekti
 */
readonly class ContextResult
{
    public float $utilizationPercent;
    public bool $isNearLimit;
    public bool $wasTruncated;

    public function __construct(
        public string $systemPrompt,
        public array $messages,
        public int $estimatedTokens,
        public int $contextWindow,
        public int $truncatedMessages,
        int $warningThreshold = 80,
    ) {
        $this->utilizationPercent = round(($estimatedTokens / $contextWindow) * 100, 1);
        $this->isNearLimit = $this->utilizationPercent >= $warningThreshold;
        $this->wasTruncated = $truncatedMessages > 0;
    }

    /**
     * API sorğusu üçün son mesajlar massivini qur.
     */
    public function toApiMessages(): array
    {
        return $this->messages;
    }

    /**
     * Context istifadəsi yüksəkdirsə xəbərdarlıq loqlat.
     */
    public function warnIfNearLimit(string $conversationId = ''): void
    {
        if ($this->isNearLimit) {
            Log::warning('Context window həddə yaxın', [
                'conversation_id' => $conversationId,
                'utilization_percent' => $this->utilizationPercent,
                'estimated_tokens' => $this->estimatedTokens,
                'context_window' => $this->contextWindow,
                'truncated_messages' => $this->truncatedMessages,
            ]);
        }
    }
}

/**
 * Context həddlər daxilində qurula bilmədikdə atılan istisna.
 */
class ContextWindowException extends \RuntimeException {}
```

### Controller-da İstifadə Nümunəsi

```php
<?php

namespace App\Http\Controllers;

use App\AI\Context\ContextWindowManager;
use App\AI\Tokens\TokenCounter;
use Anthropic\Laravel\Facades\Anthropic;

class ChatController extends Controller
{
    public function __construct(
        private readonly ContextWindowManager $contextManager,
    ) {}

    public function send(ChatRequest $request, Conversation $conversation): JsonResponse
    {
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();

        $currentMessage = [[
            'role' => 'user',
            'content' => $request->input('message'),
        ]];

        // Context hazırla — tarixi window-a sığdırır, kəsməni idarə edir
        $context = $this->contextManager->prepare(
            systemPrompt: config('ai.system_prompt'),
            history: $history,
            currentMessage: $currentMessage,
            strategy: 'sliding_window',
        );

        // Həddə yaxınlaşsaq xəbərdarlıq et
        $context->warnIfNearLimit($conversation->id);

        $response = Anthropic::messages()->create([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 4096,
            'system' => $context->systemPrompt,
            'messages' => $context->toApiMessages(),
        ]);

        // ... cavabı saxla və qaytar
    }
}
```

---

## Arxitektor Mülahizələri

### Token Səmərəliliyi üçün Dizayn

```
1. SİSTEM PROMPTLARINI SIXIŞDIRin
   Uzun: "Siz istifadəçilərə suallarında kömək edən faydalı bir AI
          köməkçisisiniz. Xahiş edirik nəzakətli və faydalı olun."
   Kompakt: "Siz faydalı köməkçisiniz. Qısa və dəqiq olun."
   Qənaət: ~20 token → hər API çağırışında daimi qənaət

2. TARİXDƏ TEKRARLAMA EDƏ BİLMƏYİN
   Hər mesajla sənədləri yenidən göndərməyin.
   Bunun əvəzinə sənəd deposu + alma istifadədin.

3. TOKEN SAYMA ÜÇÜN HAIKU-dan İSTİFADƏ EDİN
   Faktiki tapşırıq üçün Sonnet-i çağırmadan əvvəl
   Anthropic-in token say API-sini Haiku (Sonnet-dən ucuz) ilə çağırın.

4. STATİK PREFİKSLƏRİ CACHE-ə ALIN
   Claude prompt caching dəstəkləyir (5 dəqiqəlik TTL).
   Promptları statik sistem promptu əvvəlcə gəlsin deyə strukturlandırın.
   Cache vurması = cache edilmiş hissədə 90% xərc azalması.

5. TOKEN İSTİFADƏSİNİ İZLƏYİN
   Hər API çağırışı üçün giriş/çıxış token-lərini loqlayın.
   Orta, P95, P99 token istifadəsini izləmək üçün dashboard qurun.
   Context həddlərinə yaxınlaşan sorğular üçün siqnallar qurun.
```

### Context Window Ölçüləndirilmə Qərarı

```
Böyük context window-a nə zaman ehtiyac var?

Kiçik (< 32k):   Tək sual-cavab, qısa sənədlər, təsnifat
Orta (32-128k): Çox növbəli çat, orta sənədlər, kod nəzəriyyəsi
Böyük (128-200k): Tam kod bazaları, uzun sənədlər, toplu analiz
Maksimum (1M):   Çoxlu kitab, video transkripsiyalar, nəhəng repolar

Qayda: İstifadə vəziyyətinizə etibarlı şəkildə sığan ən kiçik context-dən istifadədin.
       Daha böyük context-lər = daha yavaş prefill + daha baha KV cache.
```

---

*Əvvəlki: [02 — Modellərə Baxış](./02-models-overview.md) | Növbəti: [04 — Temperature və Parametrlər](./04-temperature-parameters.md)*
