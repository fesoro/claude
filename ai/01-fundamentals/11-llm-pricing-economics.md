# LLM Pricing və Unit Economics (Senior)

> Hədəf auditoriyası: LLM xüsusiyyətləri qoşulmuş məhsul yaratan və bu xüsusiyyətlərin maliyyə sağlamlığını başa düşməli olan senior developerlər və texniki məhsul rəhbərləri. Bu sənəd "çağırış başına dolar" hesablamasını keçir — real unit economics qurur.

---

## Mündəricat

1. [Niyə Unit Economics Kritikdir](#niyə-kritikdir)
2. [Tokenlərin Qiymət Strukturu](#token-qiyməti)
3. [Cari Qiymət Cədvəli (2026-04)](#qiymət-cədvəli)
4. [Prompt Caching Riyaziyyatı](#caching-math)
5. [Batch API — 50% Endirim](#batch-api)
6. [Context Tax — Gizli Xərc](#context-tax)
7. [Nümunə A: Support Bot (10k ticket/ay)](#case-a-support)
8. [Nümunə B: RAG over 100k sənəd](#case-b-rag)
9. [Nümunə C: Code Reviewer (50 PR/gün)](#case-c-code)
10. [Break-even Formulları](#break-even)
11. [Per-User Pricing Strategiyaları](#per-user-pricing)
12. [Profitability Modelləşdirmə](#profitability)
13. [Laravel Xərc Tracking](#xərc-tracking)
14. [Xərc Optimizasiya Çeklist](#optimizasiya)

---

## Niyə Kritikdir

LLM xərcləri adi infrastruktur xərclərindən fərqlidir:

```
Adi infra:   sabit + az dəyişən. CPU/RAM avans ödənilir.
LLM xərci:   100% dəyişən. Hər sorğu cib qaynadır.
```

Bu dəyişənlik unit economics-i dramatik şəkildə dəyişdirir. Ənənəvi SaaS modelində margin 80%+ olur. LLM-intensive məhsullarda margin 20-40%-ə enə bilər — əgər planlaşdırmasan, 0-a və ya mənfiyə də düşə bilər.

### Məşhur Uğursuzluq Nümunələri

- Bir AI writing asistentı $20/ay tariflə, ağır istifadəçilər $200+ xərcləyirdi. Şirkət hər ağır istifadəçidə $180 itirirdi.
- Bir chat-bot unlimited plan təklif etdi. Bir istifadəçi günde 50k mesaj göndərdi. Aylıq zərər: $8k.
- Bir code reviewer enterprise tarifi satdı ($500/ay), amma hər repo üçün full context göndərirdi — nəticə: $700 xərc, $500 gəlir, hər müştəridə $200 zərər.

Unit economics olmadan LLM məhsulu — termostatsız mərkəzi istilik sistemi kimidir. Yandırır, amma kimin ödəyəcəyi bəlli deyil.

---

## Token Qiyməti

LLM bazasında üç növ tokеn var:

```
INPUT TOKENS
  Sorğuda göndərilən bütün token-lər.
  System prompt, istifadəçi mesajı, tool definitions, context.
  Qiymət: aşağı (çünki yalnız oxunur).

OUTPUT TOKENS
  Model tərəfindən generasiya olunan token-lər.
  Qiymət: YÜKSƏK (input-dan 3-5x baha) — çünki hesablama ağırdır.

CACHED TOKENS
  Prompt caching aktivdirsə, cache-dən oxunan token-lər.
  Qiymət: çox aşağı (input-un ~10%-i).
```

### Token-in "Sözə" Nisbəti

```
İngilis dili:   ~0.75 söz / token
Azərbaycan:     ~0.5 söz / token  (Türk qrupu dillərində daha çox token)
Kod:            ~2 simvol / token
Rəqəmlər:       ~1 rəqəm / token
JSON:           strukturaya görə çox yükdür
```

Qiymət hesablarkən həmişə "real token" say, "simvol/4" kimi yanlış təqribilərdən istifadə etmə.

---

## Qiymət Cədvəli

2026-04-21 tarixi ilə Anthropic qiymətləri (1M token başına, USD):

| Model | Input | Output | Cache Write 5min | Cache Write 1h | Cache Read | Batch Input | Batch Output |
|-------|-------|--------|-----------------|---------------|-----------|-------------|--------------|
| claude-opus-4-5 | $15.00 | $75.00 | $18.75 | $30.00 | $1.50 | $7.50 | $37.50 |
| claude-sonnet-4-5 | $3.00 | $15.00 | $3.75 | $6.00 | $0.30 | $1.50 | $7.50 |
| claude-haiku-4-5 | $1.00 | $5.00 | $1.25 | $2.00 | $0.10 | $0.50 | $2.50 |

### Asimmetriyalar

```
Output / Input nisbəti:    5x
Cache Read / Input:        10%  (90% endirim)
Batch / Normal:            50%  (50% endirim)
Cache Write 1h / 5min:     60%  artırma
```

### Effektiv Qiymət

```
Tipik sorğu: 5k input + 500 output
  input:  5000 × $3/1M  = $0.015
  output: 500  × $15/1M = $0.0075
  cəmi: $0.0225

Cache-li (cache hit 80%):
  cache read:  4000 × $0.30/1M = $0.0012
  cold input:  1000 × $3/1M     = $0.003
  output:      500  × $15/1M    = $0.0075
  cəmi: $0.0117 (48% qənaət)
```

---

## Caching Math

Prompt caching sistem prompt-u, tool definitions-ı və sabit kontekst-i cache-də saxlayır. Sonrakı sorğularda həmin hissələr üçün 90% endirim verilir.

### Nə Vaxt Caching Qazanır

```
Şərt: cache_write_cost + N × cache_read_cost < N × full_input_cost

Cache-li: n × ($3.75/1M + $0.30/1M)  ← n növbəti sorğu üçün
Cache-siz: n × $3.00/1M

Sonnet üçün:
  cache write:   $3.75/1M (bir dəfə)
  cache read:    $0.30/1M × N  (hər sorğuda)
  total cached:  $3.75 + N × $0.30

  no cache:      N × $3.00

  Break-even N: 3.75 + 0.30N = 3.00N  →  N = 1.39

  2-ci sorğudan etibarən caching ucuzlaşır.
```

### Praktiki Nümunə

System prompt 20k token, sorğu 500 token, output 300 token, Sonnet:

```
Cache-siz (N sorğu):
  Hər sorğu: (20000 + 500) × $3/1M + 300 × $15/1M
           = $0.0615 + $0.0045 = $0.066
  
  N sorğu xərci: N × $0.066

Cache-li:
  İlk sorğu:  20000 × $3.75/1M + 500 × $3/1M + 300 × $15/1M
            = $0.075 + $0.0015 + $0.0045 = $0.081
  
  Sonrakı:   20000 × $0.30/1M + 500 × $3/1M + 300 × $15/1M
            = $0.006 + $0.0015 + $0.0045 = $0.012
  
  N sorğu: $0.081 + (N-1) × $0.012

N = 100 üçün:
  cache-siz: $6.60
  cache-li:  $1.27  (81% qənaət)
```

### 5-dəqiqəlik vs 1-saatlıq Cache

```
5 min cache:
  write: $3.75/1M
  aktivlik: 5 dəqiqə (hər hit-də yenilənir)
  
1 hour cache:
  write: $6.00/1M (60% baha)
  aktivlik: 1 saat
```

Break-even:
- Əgər cache 5 dəqiqədə əminliklə hit olursa → 5min seç
- Əgər intervallar 5-60 dəqiqə arasındadırsa → 1h seç

---

## Batch API

Batch API real-time olmayan işlər üçün 50% endirim verir. Tamamlanma müddəti: 24 saatadək.

```
Batch istifadə senarioları:
  - Gecə batch sinifləndirmə
  - Tarixi sənədlərin təhlili
  - Offline report generasiyası
  - Test set üzərində model qiymətləndirməsi
  - Email kampaniyası mətn generasiyası
```

### Batch Qənaətinin Hesablanması

```
Normal Sonnet: 1M input + 100k output
  = 1M × $3 + 100k × $15/1M = $3 + $1.5 = $4.50

Batch Sonnet:
  = 1M × $1.50 + 100k × $7.50/1M = $1.50 + $0.75 = $2.25
  
Qənaət: $2.25 (50%)
```

### Batch + Caching Birləşməsi

Batch-da caching də işləyir:

```
Senario: 1000 sorğu, hər biri 15k shared prompt + 2k unique input + 500 output

Cache + Batch:
  cache write (bir dəfə): 15000 × $3.75/1M × 0.5 = $0.028
  cache read (999 dəfə):  999 × 15000 × $0.30/1M × 0.5 = $2.25
  cold input:             1000 × 2000 × $3/1M × 0.5   = $3.00
  output:                 1000 × 500 × $15/1M × 0.5   = $3.75
  cəmi: $9.03

No cache, no batch:
  input:  1000 × 17000 × $3/1M = $51
  output: 1000 × 500 × $15/1M  = $7.50
  cəmi: $58.50
  
Qənaət: 85%
```

---

## Context Tax

"Context tax" — modelə lazımsız kontekst göndərilməsindən yaranan gizli xərcdir:

```
Bir RAG sistemində top-10 sənəd retrieve edilir.
Amma yalnız top-3 həqiqətən faydalı olur.
Qalan 7 sənəd "context tax"-dır.
```

### Misal

```
Hər sorğuda 10 sənəd × 3k token = 30k token context

30k × $3/1M = $0.09 per query

Top-3-ə keçsək:
9k × $3/1M = $0.027

Qənaət: 70%, keyfiyyət eyni (ya da yaxşı — irrelevantlıq az)
```

### Re-ranking ilə Context Tax Azaltma

```
Step 1: Retrieval → top 20 sənəd (sürətli, ucuz)
Step 2: Re-rank (kiçik model) → top 3 sənəd (ucuz)
Step 3: LLM generate → top 3 ilə

Nəticə: daha az context, daha yaxşı keyfiyyət, daha az pul.
```

---

## Case A Support

Senario: Laravel-əsaslı SaaS məhsulu. Gündə 333 ticket, ayda 10k. Müştəri bilet göndərir, chat-bot cavab verir. Əgər bot həll edə bilmirsə, insana yönləndirir.

### İlk Yanaşma: Naiv

Hər ticket üçün:
- System prompt: 3k token (təlimatlar, məhsul məlumatı)
- Müştəri mesajı: ~500 token (orta)
- Cavab: ~300 token

Sonnet istifadə olunur:

```
Hər ticket:
  input:  3500 × $3/1M  = $0.0105
  output: 300  × $15/1M = $0.0045
  cəmi:   $0.015

10k ticket: $150/ay
```

### İkinci Yanaşma: Caching

System prompt cache-lənir:

```
Hər ticket (cache hit):
  cache read: 3000 × $0.30/1M = $0.0009
  cold input: 500  × $3/1M    = $0.0015
  output:     300  × $15/1M   = $0.0045
  cəmi:       $0.0069

10k ticket: $69/ay  (54% qənaət)
```

### Üçüncü Yanaşma: Router + Caching

Haiku router ticket-i sinifləndirir:
- 70% SIMPLE → Haiku cavab verir
- 25% MEDIUM → Sonnet cavab verir
- 5% COMPLEX → Human-a yönləndirilir

```
Router (Haiku, hər ticket):
  input:  3500 × $1/1M = $0.0035  (cache ilə $0.00085)
  output: 30   × $5/1M = $0.00015
  cəmi: ~$0.001 per ticket

Cavab:
  70% Haiku: 7000 × ($0.0008 + 300×$5/1M) = 7000 × $0.0023 = $16.10
  25% Sonnet: 2500 × $0.0069 = $17.25
  5% Human-a: $0 LLM xərci

Router xərci: 10000 × $0.001 = $10

Cəmi: $10 + $16.10 + $17.25 = $43.35/ay
```

### Müqayisə Cədvəli

| Yanaşma | Aylıq Xərc | Qənaət |
|---------|-----------|--------|
| Naiv Sonnet | $150 | 0% |
| Sonnet + cache | $69 | 54% |
| Router + cache | $43 | 71% |
| Yalnız Opus | $750 | -400% |

### Per-Ticket Unit Economics

Müştərinin orta ticket dəyəri: $5 (support əməkdaşının vaxtı)
AI xərci: $0.004
Nəticə: AI ticket başına $4.99 qənaət gətirir → aylıq ~$50k dəyər.

---

## Case B RAG

Senario: 100k sənədli enterprise knowledge base. İstifadəçi sual verir, sistem relevant sənədləri tapır və cavab generasiya edir.

### Komponent Xərcləri

```
1. Embedding (sənədlər üçün, bir dəfə):
   100k sənəd × 2k token = 200M token
   Voyage-3 embedding: $0.18 / 1M token
   Bir dəfəlik xərc: 200 × $0.18 = $36

2. Query embedding (hər sorğu):
   Orta query: 50 token
   $0.18/1M → çox ucuz

3. Vector search:
   Qdrant/Pinecone - aylıq sabit ($100-500)

4. Re-ranking:
   top-20 → top-5 (Haiku və ya kiçik cross-encoder)
   20 × 500 token = 10k input, 100 token output
   Haiku: 10000 × $1/1M + 100 × $5/1M = $0.0105

5. Generation:
   5 sənəd × 1k token = 5k input
   Sistem prompt + query = 1k
   Output: 500
   Sonnet: 6000 × $3/1M + 500 × $15/1M = $0.0255
```

### Hər Sorğu Xərci

```
Re-ranking: $0.011
Generation: $0.026
Embedding:  $0.00001
Vector search: amortized

Cəmi: ~$0.037 per query
```

### 10k query/gün = 300k/ay:

```
RAG xərci: 300k × $0.037 = $11,100/ay
Infrastructure (vector DB): $300/ay
Cəmi: $11,400/ay
```

### Caching ilə Optimizasiya

Sistem prompt 2k token + top sənədlər çox vaxt eyni olur (eyni sual növləri):

```
Cache hit 40% (təxminən):
  Hit case: $0.008 per query
  Miss case: $0.037 per query
  Orta: 0.4 × $0.008 + 0.6 × $0.037 = $0.0254

300k query: $7,620/ay
Qənaət: ~$3.5k/ay (31%)
```

### Hybrid Retrieval

BM25 + semantic search birləşdirmək, top-k ölçüsünü azaldır:

```
Əvvəl: top-10 sənəd, 10k input token
Sonra: top-3 sənəd, 3k input token

Generation input:
  əvvəl: 11000 × $3/1M = $0.033
  sonra:  4000 × $3/1M = $0.012
  qənaət per query: $0.021

300k/ay × $0.021 = $6,300 əlavə qənaət
```

---

## Case C Code

Senario: GitHub PR-lar üçün avtomatik code reviewer. 50 PR/gün, hər PR orta 500 sətir dəyişiklik.

### İnput Strukturu

```
Sistem prompt (coding style, guidelines): 5k token
Repo context (yalnız dəyişmiş fayllar + kontekst): 15k token
Diff: 3k token
Cəmi input: 23k token

Output: ~1500 token review
```

### Sonnet ilə Xərc

```
Per PR:
  input:  23000 × $3/1M  = $0.069
  output: 1500  × $15/1M = $0.0225
  cəmi:   $0.0915

50 PR × 22 iş günü = 1100 PR/ay
Aylıq: 1100 × $0.0915 = $100.65
```

### Opus ilə Xərc

```
Per PR:
  input:  23000 × $15/1M = $0.345
  output: 1500  × $75/1M = $0.1125
  cəmi:   $0.4575

Aylıq: 1100 × $0.4575 = $503
```

### Hybrid: Haiku Pre-scan + Sonnet Deep Review

```
Step 1 (Haiku): diff-i skan et, kritiklik səviyyəsini qərarlaşdır
  input: 3000 × $1/1M  = $0.003
  output: 50 × $5/1M   = $0.00025
  cəmi: $0.00325 per PR

Step 2 (Sonnet yalnız 30% PR üçün): tam review
  input:  23000 × $3/1M = $0.069
  output: 1500 × $15/1M = $0.0225
  cəmi: $0.0915

Per PR:
  Haiku: $0.00325
  Sonnet (30%): 0.3 × $0.0915 = $0.0275
  Comment-only (70%): 0 (Haiku-nun output-u yetər)
  
  Cəmi orta: $0.031 per PR

Aylıq: 1100 × $0.031 = $34
```

### Müqayisə

| Yanaşma | Aylıq Xərc | Keyfiyyət |
|---------|-----------|----------|
| Opus hər PR | $503 | Ən yüksək |
| Sonnet hər PR | $101 | Yüksək |
| Haiku → Sonnet hybrid | $34 | Yüksək (eyni) |
| Yalnız Haiku | $3.5 | Orta |

### Unit Economics

Developer 1 saat review vaxtı qənaət edir = ~$75 dəyər.
1100 PR × $75 = $82,500 dəyər aylıq.
AI xərci: $34.
ROI: 2400x.

---

## Break-even

### Per-User Break-even

```
Tarif: $X/ay
Xərc: Y × ortalama LLM xərci/istifadəçi

Break-even: X = Y

Əgər:
  Ortalama istifadəçi 1000 query/ay edir
  Hər query $0.01 xərc çəkir
  
  İstifadəçi xərci: $10/ay
  Tarif ən az $10 + operating costs olmalıdır
  
  60% gross margin üçün: tarif = $25/ay
```

### Power User Riski

```
P90 istifadəçi: 10x ortalama = 10k query = $100 xərc
Tarif $25 → power user-də $75 zərər

Həll:
  1. Tarifə query limit qoy (10k/ay)
  2. Artıq üçün pay-per-use ($0.02/query)
  3. Fair use policy
```

### Mental Model

```
Unit economics sağlam əgər:
  LTV > CAC + 3 × aylıq LLM xərci

Yəni:
  Customer Lifetime Value istifadəçi əldə etmə + 3 aylıq LLM xərcini örtməlidir
```

---

## Per-User Pricing

### Strategiya 1: Flat Rate + Quota

```
$29/ay → 1000 query
$99/ay → 5000 query
$299/ay → 25000 query

Qiymət per query: $0.029, $0.020, $0.012 (həcm endirimi)
Xərc per query: ~$0.01
Margin: 65%, 50%, 17% (yüksək tier-də margin azalır)
```

### Strategiya 2: Usage-Based

```
$0.05 per query
Ortalama istifadəçi 1000 query = $50/ay
Xərc: $10
Margin: 80%

Üstünlük: istifadə ilə skalalanır
Çatışmazlıq: istifadəçilər qiyməti qabaqcadan bilməyə çətinlik çəkir
```

### Strategiya 3: Hybrid

```
Base: $19/ay → 500 query daxil
Overage: $0.03 per query

Ortalama istifadəçi: 800 query
  Base: $19
  Overage: 300 × $0.03 = $9
  Cəmi: $28/ay

Xərc: 800 × $0.01 = $8
Margin: 71%
```

### Strategiya 4: Seat + Usage

```
$10/user/ay (base seat)
$0.02 per query (shared pool)

10 user komanda, 5000 query/ay:
  Seats: $100
  Usage: $100
  Cəmi: $200/ay

Xərc: $50
Margin: 75%
```

### Strategiya 5: Bring-your-own-API-key (BYOAK)

```
$15/ay məhsul abunəliyi
İstifadəçi öz Anthropic API açarından istifadə edir

Üstünlük: sən LLM xərci çəkmirsən
Çatışmazlıq: onboarding çətin, enterprise üçün pis görünür
```

---

## Profitability

### Gross Margin Analizi

```
MRR:               $100,000
LLM xərci:         -$25,000  (25%)
Infrastructure:    -$8,000   (8%)
Payment fees:      -$3,000   (3%)
Gross Profit:       $64,000  (64%)
```

Ənənəvi SaaS 80-85% gross margin-dir. LLM məhsulu 60-70% — bu normadır, amma səni fərqli satış/marketing qərarlarına vadar edir:

- CAC/LTV nisbəti daha ehtiyatlı olmalıdır
- Free tier-lər dərin limitli olmalıdır
- Enterprise satış paylaşılmış infrastruktura görə daha yaxşı margin gətirir

### LLM Xərc Göstəricilərinin Dashboard-u

Senior developer bu metrikaları izləməlidir:

```
Per day:
  - Total tokens (in/out/cached)
  - Total spend
  - Top 10 istifadəçi xərcləri
  - Model paylanması (Haiku/Sonnet/Opus %)
  - Cache hit rate
  - Ortalama latency

Per month:
  - MRR / LLM cost ratio
  - Cost per active user
  - P95 power user cost
  - Break-even istifadəçi sayı
```

---

## Xərc Tracking

Laravel-də hər LLM sorğusunun tokeni və xərci loglanmalıdır:

### Migration

```php
Schema::create('llm_usage_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained();
    $table->string('tenant_id')->nullable()->index();
    $table->string('model');
    $table->integer('input_tokens');
    $table->integer('output_tokens');
    $table->integer('cache_read_tokens')->default(0);
    $table->integer('cache_write_tokens')->default(0);
    $table->decimal('cost_usd', 10, 6);
    $table->string('feature')->index();
    $table->integer('latency_ms');
    $table->timestamp('created_at')->index();
});
```

### Pricing Service

```php
<?php

namespace App\Services\AI;

class PricingCalculator
{
    private const PRICES = [
        'claude-opus-4-5' => [
            'input' => 15.00,
            'output' => 75.00,
            'cache_write_5m' => 18.75,
            'cache_read' => 1.50,
        ],
        'claude-sonnet-4-5' => [
            'input' => 3.00,
            'output' => 15.00,
            'cache_write_5m' => 3.75,
            'cache_read' => 0.30,
        ],
        'claude-haiku-4-5' => [
            'input' => 1.00,
            'output' => 5.00,
            'cache_write_5m' => 1.25,
            'cache_read' => 0.10,
        ],
    ];

    public function calculate(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
    ): float {
        $prices = self::PRICES[$model] ?? throw new \InvalidArgumentException();

        $cost = 0.0;
        $cost += ($inputTokens / 1_000_000) * $prices['input'];
        $cost += ($outputTokens / 1_000_000) * $prices['output'];
        $cost += ($cacheReadTokens / 1_000_000) * $prices['cache_read'];
        $cost += ($cacheWriteTokens / 1_000_000) * $prices['cache_write_5m'];

        return round($cost, 6);
    }
}
```

### Usage Tracker

```php
<?php

namespace App\Services\AI;

use App\Models\LlmUsageLog;

class UsageTracker
{
    public function __construct(
        private PricingCalculator $pricing,
    ) {}

    public function track(array $response, array $context): void
    {
        $usage = $response['usage'];

        $cost = $this->pricing->calculate(
            model: $response['model'],
            inputTokens: $usage['input_tokens'],
            outputTokens: $usage['output_tokens'],
            cacheReadTokens: $usage['cache_read_input_tokens'] ?? 0,
            cacheWriteTokens: $usage['cache_creation_input_tokens'] ?? 0,
        );

        LlmUsageLog::create([
            'user_id' => $context['user_id'] ?? null,
            'tenant_id' => $context['tenant_id'] ?? null,
            'model' => $response['model'],
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'cache_read_tokens' => $usage['cache_read_input_tokens'] ?? 0,
            'cache_write_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
            'cost_usd' => $cost,
            'feature' => $context['feature'] ?? 'unknown',
            'latency_ms' => $context['latency_ms'] ?? 0,
        ]);
    }
}
```

### Quota Enforcement

```php
<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

class QuotaGuard
{
    public function canSpend(string $tenantId, float $expectedCost): bool
    {
        $monthlyCap = $this->getTenantCap($tenantId);
        $spent = $this->getCurrentMonthSpend($tenantId);

        return ($spent + $expectedCost) <= $monthlyCap;
    }

    public function getCurrentMonthSpend(string $tenantId): float
    {
        return Cache::remember(
            "llm.spend.{$tenantId}." . now()->format('Y-m'),
            60,
            fn () => \App\Models\LlmUsageLog::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->sum('cost_usd'),
        );
    }

    private function getTenantCap(string $tenantId): float
    {
        return \App\Models\Tenant::find($tenantId)->monthly_llm_cap ?? 100.0;
    }
}
```

### İstifadə

```php
$guard = app(QuotaGuard::class);

if (!$guard->canSpend($tenantId, expectedCost: 0.05)) {
    return response()->json([
        'error' => 'monthly_llm_quota_exceeded',
        'message' => 'Aylıq LLM limitiniz bitib. Tarifi yüksəldin.',
    ], 402);
}

$response = $claude->messages()->create([...]);
$tracker->track($response, [
    'tenant_id' => $tenantId,
    'feature' => 'chat',
    'latency_ms' => $latency,
]);
```

---

## Optimizasiya

Senior developer üçün xərc optimizasiya çeklist:

### 1. Sistem Prompt-u Kiçiklət

```
Əvvəl: 10k token təlimat
Sonra: 2k token (yalnız zəruri hissə)
Qənaət: 80% input xərci
```

### 2. Prompt Caching Aktiv Et

```
Sabit hissələr üçün cache_control marker əlavə et:
  - System prompt
  - Tool definitions
  - Few-shot examples
  - Sənəd kontekstləri
```

### 3. Doğru Modelə Yönləndir

```
Router pattern (əvvəlki fəsil).
Default model-i "Sonnet" et, Opus yalnız isbatlanmış zərurətlə.
```

### 4. Output-u Məhdudlaşdır

```
max_tokens parameter-i agresiv seç.
Əgər JSON response 100 token-dir — max_tokens=150 qoy, 4096 yox.
Output 5x bahadır — hər qurtardığın output token 5x pul qənaətidir.
```

### 5. Batch API İstifadə Et

```
Real-time olmayan işlər üçün batch API-yə köçür.
50% endirim.
```

### 6. Retry Strategy-ni Sərt Et

```
Uğursuz sorğu = tam qiymətdə çəkilir.
Gereksiz retry-lar cib yandırır.
Circuit breaker qoy.
```

### 7. Streaming ilə User-i Tut

```
Stream output — istifadəçi ilk token-dən görür.
Bu, "kəsdir" düyməsinə basmaq imkanı verir → yarımçıq output qısalır → xərc azalır.
```

### 8. Token Counting ilə Budgetə Riayət Et

```php
use Anthropic\Laravel\Facades\Anthropic;

$count = Anthropic::messages()->countTokens([
    'model' => 'claude-sonnet-4-5',
    'messages' => [...],
])['input_tokens'];

if ($count > 100_000) {
    throw new PromptTooLargeException();
}
```

### 9. Cache Hit Rate-i İzlə

```
Target: 70%+ cache hit rate
Əgər 20%-dir → prompt strukturu səhvdir, cache marker-lər optimal yerlərdə deyil
```

### 10. Monthly Cost Review

```
Hər ay:
  - Top 10 bahalı feature-ları aşkarla
  - Top 10 power user-ləri aşkarla
  - Model paylanmasını yenidən nəzərdən keçir
  - Prompt-ları audit et (lazımsız sahələr?)
```

---

## Yekun

LLM unit economics, ənənəvi SaaS-dən fərqli düşüncə tələb edir:

- **Hər sorğu dəyişən xərcdir** — sabit deyil
- **Power user-lər qatil ola bilər** — quota zəruri
- **Cache-lənməmiş prompt = 10x artıq pul** — cache mütləq
- **Yanlış model = 5-15x artıq pul** — router pattern mütləq
- **Batch + real-time qarışığı** — 50% qənaət məhsulun sağlamlığını xilas edə bilər
- **Tracking olmadan optimizasiya yoxdur** — hər sorğu loglanmalıdır
- **Break-even hesablanmalıdır** — hisslə deyil, spreadsheet ilə

Senior developer kimi LLM məhsulu — həm texniki, həm də maliyyə problemi həll edir. Bu iki tərəfi ayırsan, məhsul zərərdə olur. Birləşdirsən, margin 70% ola bilər — bu qənaət bütün şirkətin büdcəsini müəyyən edir.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Cost Tracking Dashboard

`ai_usage_logs` cədvəli yarat: `model`, `feature`, `input_tokens`, `output_tokens`, `cost_usd`, `created_at`. Hər API çağırışını burada log et. Filament-də aylıq xərc qrafiği, feature-a görə qrupluq, ən bahalı istifadəçilər üçün rapor hazırla.

### Tapşırıq 2: Break-Even Hesablaması

Ən çox istifadə olunan AI feature-ın aylıq xərclərini hesabla: API cost + developer saatı. Həmin feature-ın gətirdiyi gəlir (ya da qənaət) nədir? Break-even nöqtəsini tap. Əgər break-even məntiqsiz uzaqdırsa, model dəyişikliyi ya da feature prioritizasiyası barəsində düşün.

### Tapşırıq 3: Prompt Caching ROI

Sistemdəki ən tez-tez istifadə olunan 3 sistem promptunu müəyyənləşdir. Caching əvvəl vs sonra cache hit rate, input token xərci, aylıq qənaəti ölç. `cache_creation_input_tokens` vs `cache_read_input_tokens` ratio-nu log et. Caching-in həqiqi ROI-ni hesabla.

---

## Əlaqəli Mövzular

- `09-llm-provider-comparison.md` — Provayder xərclərinin müqayisəsi
- `10-model-selection-decision.md` — Cost-quality balansı üçün model seçimi
- `../02-claude-api/09-prompt-caching.md` — Xərc azaltmanın ən effektiv yolu
- `../08-production/04-cost-optimization.md` — Production-da sistematik cost optimization
