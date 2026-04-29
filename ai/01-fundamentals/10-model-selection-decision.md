# Model Seçmə — Qərar Çərçivəsi (Opus vs Sonnet vs Haiku) (Senior)

> Hədəf auditoriyası: Produksiyada LLM infrastrukturu qurmalı olan senior PHP/Laravel developerlər. Hər tapşırıq üçün doğru model seçmək — həm keyfiyyəti, həm gecikməni, həm də xərcləri eyni anda optimallaşdırmaq mənasına gəlir.

---

## Mündəricat

1. [Niyə Model Seçimi Kritikdir](#niyə-kritikdir)
2. [Üç Səviyyəli Model Piramidası](#üç-səviyyəli-piramida)
3. [Keyfiyyət, Gecikmə və Xərc Üçbucağı](#üçbucaq)
4. [Qiymət Cədvəli (1M Token)](#qiymət-cədvəli)
5. [Cost-per-Task Hesablama](#cost-per-task)
6. [Qərar Ağacı — Hansı Modeli Seçmək](#qərar-ağacı)
7. [10 Real Use-Case və Tövsiyə](#10-use-case)
8. [Nə Vaxt Kiçik Modeldən İstifadə Etmək](#nə-vaxt-kiçik)
9. [Nə Vaxt Böyük Modelə Pul Vermək](#nə-vaxt-böyük)
10. [Router Pattern — Classify-then-Route](#router-pattern)
11. [Laravel-də Avtomatik Routing](#laravel-auto-routing)
12. [Cascade Pattern — Escalation](#cascade-pattern)
13. [A/B Testing və Model Canary](#ab-testing)
14. [Mental Modellər](#mental-modellər)

---

## Niyə Kritikdir

Bir startup günə 100k LLM çağırışı edir. Əgər hər biri Opus-a gedirsə, aylıq xərc asanlıqla $50-100k-a çatır. Eyni trafikin 80%-i Haiku-da icra oluna bilərsə, xərc $10k-ya düşür — keyfiyyət itkisi isə yalnız 5% tapşırıqda hiss olunur.

Model seçmə, **produkt arxitekturası**dır. "Ən yaxşı model hansıdır?" sualı yanlışdır. Düzgün sual:

> "Bu konkret tapşırıq üçün, verilmiş keyfiyyət dərəcəsində, ən ucuz və ən sürətli model hansıdır?"

Senior developer üçün bu, database seçimi qədər əsaslı qərardır. Postgres-i hər şey üçün istifadə etmək olar, amma session storage-ı üçün Redis daha məqsədəuyğundur. LLM-lərdə də eyni məntiq işləyir.

### Yanlış Yanaşmanın Qiyməti

```
SSS chat-bot, gündə 50k sorğu, hər biri ~500 token input + 200 token output.

Yalnız Opus:    50k × (500 × $15 + 200 × $75) / 1M  = $1125/gün  = $33.7k/ay
Yalnız Sonnet:  50k × (500 × $3  + 200 × $15) / 1M  = $225/gün   = $6.75k/ay
Yalnız Haiku:   50k × (500 × $1  + 200 × $5)  / 1M  = $75/gün    = $2.25k/ay
Router (80/15/5): ~$150/gün = $4.5k/ay  (keyfiyyət kifayət qədər yüksək)
```

Eyni məhsul, 15 dəfə fərqli xərc. Bu, bir developer maaşı ilə bərabərdir.

---

## Üç Səviyyəli Piramida

Anthropic model ailəsi üç səviyyədə qurulub. Hər səviyyənin öz "iş yeri" var:

```
                     +---------------------+
                     |  claude-opus-4-7    |   Flagship
                     |  Ən güclü mühakimə  |   Az istifadə olunur
                     +---------------------+   Yüksək xərc
                    /                       \
                   /                         \
           +---------------------+
           |  claude-sonnet-4-6  |            Production workhorse
           |  Balans: sürət+keyfiyyət |       Əksər use-case
           +---------------------+
          /                       \
         /                         \
  +---------------------+
  |  claude-haiku-4-5   |                    Sürətli və ucuz
  |  Sadə tapşırıqlar   |                    Yüksək həcm
  +---------------------+
```

### Hər Səviyyənin Xarakteristikası

| Xüsusiyyət | Haiku 4.5 | Sonnet 4.5 | Opus 4.5 |
|-----------|-----------|-----------|----------|
| Məqsəd | Sürət, həcm | Balans | Ən mürəkkəb mühakimə |
| Tipik gecikmə (TTFT) | ~300-500ms | ~700-1200ms | ~1.5-3s |
| Output throughput | ~150 tok/s | ~70 tok/s | ~40 tok/s |
| Mühakimə dərinliyi | Səthi-orta | Dərin | Çox dərin |
| Alət istifadəsi | Yaxşı | Çox yaxşı | Əla |
| Kontekst pəncərəsi | 200k | 200k / 1M | 200k |
| İdeal trafik | >100k sorğu/gün | 1k-100k/gün | <1k/gün və ya kritik |

### "Model Ölçüsü" Yanıldıcıdır

Model ölçüsü yalnız bir ölçüdür. Senior developer üçün vacib ölçülər:

1. **Mühakimə dərinliyi** — çoxaddımlı planlaşdırma, bağımlılık izləməsi
2. **Təlimatlara sadiqlik** — system prompt-u dəqiq necə icra edir
3. **Hallucination dərəcəsi** — məlumat olmadıqda uydurma ehtimalı
4. **Alət orkestrasiyası** — parallel/sequential tool-lərin idarə olunması
5. **Uzun kontekstdə diqqət** — 100k+ token-də "needle in haystack"

Haiku 4.5, Opus 3-dən daha güclüdür bir çox metrikdə. "Ölçü" zamanla yenilənir.

---

## Üçbucaq

Hər LLM seçimi bu üç dəyər arasında güzəştdir:

```
                    KEYFİYYƏT
                       /\
                      /  \
                     /    \
                    /      \
                   /  Opus  \
                  / Sonnet   \
                 /  Haiku     \
                /______________\
          XƏRC                GECİKMƏ
```

Bunların üçünü də eyni anda maksimuma çatdırmaq mümkün deyil. Həmişə biri digərləri üçün qurban verilir.

### İki Seçim Qaydası

İstənilən tapşırıq üçün üç ölçüdən **yalnız ikisini** optimallaşdıra bilərsən:

- **Keyfiyyət + Sürət** — Xərc artır (böyük model, batch yoxdur)
- **Keyfiyyət + Ucuz** — Gecikmə artır (batch API, prompt caching)
- **Sürət + Ucuz** — Keyfiyyət aşağı düşür (Haiku, qısa prompt)

Bu üçbucaqda hansı küncdə durduğunu bilmək, doğru model seçməyin birinci addımıdır.

---

## Qiymət Cədvəli

2026-04-21 tarixinə olan Anthropic qiymətləri (1M token başına, USD):

| Model | Input | Output | Cache Write (5m) | Cache Read | Batch (50% off) |
|-------|-------|--------|------------------|------------|-----------------|
| claude-opus-4-7 | $15.00 | $75.00 | $18.75 | $1.50 | $7.50 / $37.50 |
| claude-sonnet-4-6 | $3.00 | $15.00 | $3.75 | $0.30 | $1.50 / $7.50 |
| claude-haiku-4-5 | $1.00 | $5.00 | $1.25 | $0.10 | $0.50 / $2.50 |

### Nisbi Xərc

```
Haiku  →  1x        (baza)
Sonnet →  3x input,  3x output
Opus   → 15x input, 15x output
```

Opus, Haiku-dan 15 dəfə bahalıdır. Bu, "15 dəfə daha yaxşı" demək deyil. Bir çox tapşırıqda fərq hiss olunmur.

### Cache-in Gücü

```
İnput token $3.00 → cache read $0.30  → 90% endirim
10 istifadəçi eyni system prompt-u oxuyursa (8k token):
  Cache-siz: 10 × 8k × $3   = $0.24
  Cache-li:  1 × 8k × $3.75 + 9 × 8k × $0.30 = $0.05
  Qənaət: 79%
```

---

## Cost-per-Task

Real tapşırıq xərci üç komponentdən ibarətdir:

```
Cost-per-Task = (input_tokens × input_price)
              + (output_tokens × output_price)
              + (cached_tokens × cache_read_price)
              + retry_cost × failure_rate
```

### Nümunə: Support Ticket Sinifləndirməsi

Giriş: ticket mətni ~400 token + system prompt 2k token + 5 example = 2.5k token
Çıxış: JSON response ~50 token

```
Haiku-da:
  input:  2500 × $1.00/1M   = $0.0025
  output: 50   × $5.00/1M   = $0.00025
  cəmi:   $0.00275 / ticket
  100k ticket: $275/ay

Sonnet-də:
  input:  2500 × $3.00/1M   = $0.0075
  output: 50   × $15.00/1M  = $0.00075
  cəmi:   $0.00825 / ticket
  100k ticket: $825/ay

Opus-da:
  cəmi:   $0.04125 / ticket
  100k ticket: $4125/ay
```

Sadə sinifləndirmə üçün Haiku kifayətdir. Opus $3850 artıq yandırmaq mənasız olardı.

### Nümunə: Kod Review

Giriş: diff 3k token + repo context 15k token + 3 few-shot examples 9k token = 27k token
Çıxış: təfsilatlı review 1.5k token

```
Sonnet-də:
  input:  27000 × $3.00/1M  = $0.081
  output: 1500  × $15.00/1M = $0.0225
  cəmi:   $0.1035 / review

Opus-da:
  input:  27000 × $15.00/1M = $0.405
  output: 1500  × $75.00/1M = $0.1125
  cəmi:   $0.5175 / review

Cache-li Sonnet (repo context sabit):
  ilk sorğu:     $0.103
  sonrakılar:    27000 × $0.30/1M + 1500 × $15/1M = $0.0307
  10 review:     $0.103 + 9 × $0.031 = $0.381
  Cache-siz:     10 × $0.103 = $1.035
  Qənaət: 63%
```

---

## Qərar Ağacı

```
Başla:  "Bu tapşırıq üçün hansı model?"
   │
   ├── Tapşırıq çoxaddımlı planlaşdırma tələb edirmi?
   │     │
   │     ├── Hə → Mühakimə çox kritikdir?
   │     │        ├── Hə → OPUS
   │     │        └── Orta → SONNET
   │     │
   │     └── Yox → Növbəti sual
   │
   ├── Saniyədə >10 sorğu gələcəkmi? (yüksək həcm)
   │     │
   │     ├── Hə → Gecikmə <1s lazımdır?
   │     │        ├── Hə → HAIKU
   │     │        └── Yox → Batch API ilə SONNET
   │     │
   │     └── Yox → Növbəti sual
   │
   ├── Tapşırıq sinifləndirmə, extraction, routing-dirmi?
   │     │
   │     ├── Hə → HAIKU (few-shot ilə)
   │     └── Yox → Növbəti sual
   │
   ├── Səhv maliyyə və ya hüquqi nəticələr yaradırmı?
   │     │
   │     ├── Hə → OPUS (+ human review)
   │     └── Yox → SONNET (default)
   │
   └── Default: SONNET
```

### Qərar Ağacının Tətbiqi

Sonnet 80/20 qaydasının modelidir — use-case-lərin 80%-i üçün doğru seçimdir. Haiku və Opus isə kənar hallar üçündür.

---

## 10 Use-Case

| # | Use-Case | Model | Səbəb |
|---|----------|-------|-------|
| 1 | Support ticket → kateqoriya | Haiku | Sinifləndirmə sadədir, həcm yüksəkdir |
| 2 | Müştəri mesajı → sentiment | Haiku | Binary/ternary output, aşağı mürəkkəblik |
| 3 | SQL generasiyası (sadə) | Sonnet | Schema başa düşmək lazımdır, amma Opus lazımsızdır |
| 4 | SQL generasiyası (complex joins) | Opus | Səhv query = səhv biznes hesabat |
| 5 | RAG chat-bot (SSS) | Sonnet | Retrieval konteksti ilə mühakimə |
| 6 | Kod review yüngül | Sonnet | Style + sadə bug axtarışı |
| 7 | Kod review kritik sistemlər | Opus | Təhlükəsizlik, race condition tapmaq |
| 8 | Çoxdilli tərcümə | Haiku | Dil modelləri tərcümədə güclüdür |
| 9 | Müqavilə analizi | Opus | Hüquqi nəticələr, incə detal |
| 10 | Agent: çox addımlı iş axını | Sonnet/Opus | Planlaşdırma və tool orkestrasiyası |

### Detallı Nümunə: Use-Case 7

Təsəvvür edin Laravel-də payment gateway kodu review olunur. Developerin əlavə etdiyi funksiya:

```php
public function processRefund(int $orderId, float $amount): void
{
    DB::beginTransaction();
    $order = Order::find($orderId);
    $gateway->refund($order->payment_id, $amount);
    $order->update(['refunded' => true]);
    DB::commit();
}
```

Sonnet bu kodda bəlkə race condition və ya idempotency problemini görməz. Opus görəcək:

```
1. Gateway call DB transaction içindədir — xarici API timeout = DB lock tutulu qalır.
2. Idempotency yoxdur — double-refund mümkündür.
3. Order::find() yerinə lockForUpdate yoxdur — concurrent refund mümkündür.
4. $amount validasiya yoxdur — amount > order.total ola bilər.
5. Gateway call uğursuz olarsa retry strategiyası yoxdur.
```

Bu kod review-ın pul qiyməti: Opus-da $0.50 vs Sonnet-də $0.10. Amma bir double-refund = $1000+ itki. Burada Opus ucuzdur.

---

## Nə Vaxt Kiçik

Haiku seçmək doğru qərardır əgər:

1. **Tapşırıq dar və təkrarlanandır** — email sinifləndirmə, entity extraction, intent detection
2. **Həcm yüksəkdir** — minutda 1000+ sorğu
3. **Gecikmə istifadəçi təcrübəsi üçün kritikdir** — real-time autocomplete, live chat
4. **Output strukturludur və qısadır** — JSON schema ilə
5. **Few-shot example-lərlə performance yaxşılaşır** — in-context learning

### Haiku-nun Güclü Tərəfləri

```
"Aşağıdakı mətndən şirkət adını çıxar:
 Müqavilə Tesla Inc və Apple arasında imzalanıb."
 
Haiku output: ["Tesla Inc", "Apple"]

Zaman: ~400ms
Xərc: $0.0004
Dəqiqlik: ~98%
```

Burada Opus istifadə etmək — Ferrari ilə baqqala getmək kimidir.

### Haiku-nun Zəif Tərəfləri

Haiku bu hallarda uğursuz olur:

- **Çox addımlı mühakimə** — "əgər X, onda Y, amma Z halında..." qaydaları
- **Uzun kontekstdə incə detal** — 50k+ token içində spesifik fakta istinad
- **Yaradıcılıq** — marketinq mətni, hekayə
- **Mürəkkəb kod** — distributed system logic, concurrent code

---

## Nə Vaxt Böyük

Opus seçmək doğru qərardır əgər:

1. **Mühakimə dərinliyi lazımdır** — strategic planning, root cause analysis
2. **Səhvin qiyməti yüksəkdir** — hüquq, maliyyə, səhiyyə
3. **Yaradıcılıq tələb olunur** — məhsul dizaynı, essay yazma
4. **Agent çox addımlıdır** — 10+ tool call, planlaşdırma dərinlikdə
5. **İnsan review-undan əvvəl filter var** — az sorğu, amma dərin

### Opus-un İstifadə Nümunəsi

```
Agent: "Bu Laravel tətbiqində memory leak var. Kod bazasını oxu,
 səbəbi tap və düzəlt təklif et."

Opus:
1. Composer.json oxuyur → package siyahısı
2. config/queue.php → Horizon aşkarlanır
3. app/Jobs/ folder oxuyur → 15 job class
4. Hər birində static property və singleton axtarır
5. ReportGenerationJob-da DB::listen() tapır — hər job-da event listener əlavə olunur, heç vaxt təmizlənmir
6. Fix təklif edir: __destruct-də DB::flushQueryLog və listener-i unbind
```

Sonnet bəzən step 5-də büdrəyir — əlaqəni qurmur. Opus isə dərin axtarış edir.

---

## Router Pattern

Production sistemlərdə "tək model" arxitekturası optimal deyil. **Classify-then-Route** pattern-i istifadə olunur:

```
    [İstifadəçi sorğusu]
           │
           ▼
    +---------------+
    | Haiku Router  |   (çox ucuz, çox sürətli)
    | Sinifləndirir |
    +---------------+
           │
    ┌──────┼──────┬──────┐
    ▼      ▼      ▼      ▼
  simple  med   complex  critical
   │      │      │        │
   ▼      ▼      ▼        ▼
  Haiku Sonnet  Opus   Opus+review
```

### Router-in İşləmə Məntiqi

1. Gələn sorğu Haiku-ya göndərilir (çox ucuzdur)
2. Haiku sorğunun mürəkkəblik səviyyəsini qiymətləndirir
3. Nəticəyə əsasən trafik uyğun modelə yönləndirilir

Bu yanaşma orta halda 60-80% xərc qənaəti gətirir.

### Router Prompt Nümunəsi

```
Sən bir sorğu router-isən. Aşağıdakı sorğu üçün təxmin et:

- Sinif: [SIMPLE, MEDIUM, COMPLEX, CRITICAL]
- Səbəb: bir cümlə

SIMPLE: extraction, classification, basic Q&A
MEDIUM: RAG, code generation, summarization
COMPLEX: multi-step planning, debugging, analysis
CRITICAL: financial, legal, security code review

JSON output:
{"class": "SIMPLE|MEDIUM|COMPLEX|CRITICAL", "reason": "..."}

Sorğu:
{user_query}
```

---

## Laravel Auto-Routing

Laravel-də bu pattern-in implementasiyası:

```php
<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModelRouter
{
    private const MODELS = [
        'SIMPLE'   => 'claude-haiku-4-5',
        'MEDIUM'   => 'claude-sonnet-4-6',
        'COMPLEX'  => 'claude-opus-4-7',
        'CRITICAL' => 'claude-opus-4-7',
    ];

    public function __construct(
        private readonly ClaudeClient $client,
    ) {}

    public function route(string $userQuery, ?string $forceClass = null): array
    {
        $class = $forceClass ?? $this->classify($userQuery);
        $model = self::MODELS[$class];

        Log::info('model_router.decision', [
            'class' => $class,
            'model' => $model,
            'query_length' => strlen($userQuery),
        ]);

        $response = $this->client->messages()->create([
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $userQuery],
            ],
        ]);

        return [
            'class' => $class,
            'model' => $model,
            'response' => $response,
        ];
    }

    private function classify(string $query): string
    {
        $cacheKey = 'router.classify.' . md5($query);

        return Cache::remember($cacheKey, 300, function () use ($query) {
            $result = $this->client->messages()->create([
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 100,
                'system' => $this->routerSystemPrompt(),
                'messages' => [
                    ['role' => 'user', 'content' => $query],
                ],
            ]);

            $json = json_decode($result['content'][0]['text'], true);

            return $json['class'] ?? 'MEDIUM';
        });
    }

    private function routerSystemPrompt(): string
    {
        return <<<'PROMPT'
        Sən bir sorğu router-isən. Aşağıdakı sorğunu sinifləndir:

        SIMPLE: extraction, classification, basic Q&A
        MEDIUM: RAG, code generation, summarization
        COMPLEX: multi-step planning, debugging, analysis
        CRITICAL: financial, legal, security code review

        JSON output sadəcə:
        {"class": "SIMPLE|MEDIUM|COMPLEX|CRITICAL", "reason": "..."}
        PROMPT;
    }
}
```

### İstifadə

```php
$router = app(ModelRouter::class);

$result = $router->route('Order #1234-ün statusunu göstər');
// class: SIMPLE, model: haiku

$result = $router->route('Bu Laravel kodda race condition tap', $forceClass = 'CRITICAL');
// class: CRITICAL, model: opus
```

### Router Metrikaları

Production-da router-in effektivliyini ölçmək üçün:

```php
// Weekly report
$stats = DB::table('model_router_logs')
    ->selectRaw('class, COUNT(*) as count, AVG(total_cost) as avg_cost')
    ->groupBy('class')
    ->get();

// Nümunə çıxış:
// SIMPLE:   45000 sorğu, $0.0003 orta
// MEDIUM:    8000 sorğu, $0.008 orta
// COMPLEX:    800 sorğu, $0.05 orta
// CRITICAL:    50 sorğu, $0.20 orta
```

---

## Cascade Pattern

Bəzən tapşırığın mürəkkəbliyini əvvəlcədən bilmək mümkün deyil. Bu halda **Cascade (Escalation)** pattern-i istifadə olunur:

```
   Sorğu
     │
     ▼
  [Haiku cəhd et] ─── uğursuz/qeyri-kafi ───┐
     │                                       │
   uğurlu                                    ▼
     │                                  [Sonnet cəhd et] ─── uğursuz ───┐
     ▼                                       │                            │
  Qaytar                                    uğurlu                        ▼
                                              │                      [Opus işlə]
                                              ▼                            │
                                            Qaytar                         ▼
                                                                        Qaytar
```

### Laravel Cascade Implementasiyası

```php
class CascadeClaudeService
{
    private const LEVELS = [
        'claude-haiku-4-5',
        'claude-sonnet-4-6',
        'claude-opus-4-7',
    ];

    public function ask(string $prompt, callable $validator): array
    {
        foreach (self::LEVELS as $model) {
            $response = $this->client->messages()->create([
                'model' => $model,
                'max_tokens' => 2048,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            $text = $response['content'][0]['text'];

            if ($validator($text)) {
                Log::info('cascade.success', ['model' => $model]);
                return ['model' => $model, 'text' => $text];
            }

            Log::info('cascade.escalate', ['model' => $model]);
        }

        throw new \RuntimeException('Bütün modellər uğursuz oldu');
    }
}

// İstifadə
$result = $service->ask(
    'Laravel-də polymorphic relation misalı ver',
    fn($text) => str_contains($text, 'morphTo') && str_contains($text, 'morphMany'),
);
```

### Cascade-in Üstünlüyü

Orta halda 70-90% sorğu Haiku-da həll olunur. Yalnız qalanlar yüksək modellərə eskalasiya olur. Bu, xərci kəskin azaldır.

---

## AB Testing

Production-da modellər arasında keyfiyyət müqayisəsi üçün A/B test strukturu:

```php
class ModelExperiment
{
    public function route(string $userId, string $prompt): string
    {
        // Stable hash ilə istifadəçini bucket-ə yerləşdir
        $bucket = hexdec(substr(md5($userId), 0, 4)) % 100;

        // 90% Haiku (kontrol), 10% Sonnet (variant)
        $model = $bucket < 90 ? 'claude-haiku-4-5' : 'claude-sonnet-4-6';

        $response = $this->client->messages()->create([
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        // Hər iki variant üçün metrikaları yaz
        DB::table('model_experiments')->insert([
            'user_id' => $userId,
            'model' => $model,
            'latency_ms' => $response['_latency'],
            'input_tokens' => $response['usage']['input_tokens'],
            'output_tokens' => $response['usage']['output_tokens'],
            'created_at' => now(),
        ]);

        return $response['content'][0]['text'];
    }
}
```

### Analiz

```sql
SELECT 
    model,
    COUNT(*) as total,
    AVG(latency_ms) as avg_latency,
    AVG(input_tokens + output_tokens) as avg_tokens,
    -- keyfiyyət ölçüsü (thumbs-up nisbəti)
    AVG(CASE WHEN rating = 'up' THEN 1 ELSE 0 END) as quality_score
FROM model_experiments
LEFT JOIN user_feedback USING (experiment_id)
WHERE created_at > NOW() - INTERVAL '7 days'
GROUP BY model;
```

Əgər quality_score fərqi 2%-dən azdırsa, ucuz model seçilməlidir.

---

## Mental Modellər

### 1. "Nə Qədər Müdrik İnsan Lazımdır?"

Tapşırığa belə bax:
- **Junior** (1 il təcrübə) həll edə bilərmi? → Haiku
- **Mid-level** (3-5 il) həll edə bilərmi? → Sonnet
- **Senior staff engineer** lazımdırmı? → Opus

### 2. "Səhvin Qiyməti Nə Qədərdir?"

```
Səhv qiyməti:
  <$1    → Haiku ok
  $1-100 → Sonnet
  >$100  → Opus + human review
```

### 3. "Nə Qədər Token Oxumaq Lazımdır?"

```
<2k token kontekst   → Haiku kifayətdir
2k-20k kontekst      → Sonnet
>20k kontekst        → Sonnet (yaxşı long-context) və ya Opus
```

### 4. "Yaradıcılıq vs Təkrarlanma"

```
Deterministik (classification, extraction) → Haiku
Semi-deterministik (code gen, summary)     → Sonnet
Yaradıcı (strategy, design, essay)         → Opus
```

### 5. "Waterfall vs Router"

Tək modelə bağlı qalma. Arxitektur belə olmalıdır:

```
┌─────────────────────────────────────┐
│ Application Layer                    │
├─────────────────────────────────────┤
│ AI Abstraction (ModelSelector)       │  ← model seçimini burada təkil et
├─────────────────────────────────────┤
│ Provider Clients                     │
│ ├── Anthropic (Haiku/Sonnet/Opus)    │
│ ├── OpenAI (fallback)                │
│ └── Local (OSS models)               │
└─────────────────────────────────────┘
```

Bu abstraksiya sənə sabahın sabah Sonnet 5.0-a keçmək və ya Haiku-nu aşağı səviyyəli use-case-lərə endirmək imkanı verir — kod dəyişmədən.

---

## Yekun

Model seçimi konfiq parametri deyil — arxitektur qərarıdır:

1. **Default: Sonnet** — use-case-lərin 80%-i üçün doğru
2. **Yüksək həcm + sadə tapşırıq → Haiku** (10-15x ucuz)
3. **Kritik mühakimə + az həcm → Opus** (5x baha, amma dəyər verir)
4. **Router pattern** — produksiyada 60-80% qənaət
5. **Cascade pattern** — mürəkkəblik qabaqcadan məlum olmadıqda
6. **Cache** — sabit system prompt varsa mütləq
7. **Batch API** — real-time olmayan işlər üçün 50% endirim
8. **A/B test** — keyfiyyət fərqini ölçmədən ucuz modelə keç

Senior developer kimi, LLM xərclərini database index-ləri qədər ciddi qəbul et. Hər sorğu pul yandırır — ya ağıllı yandır, ya da arxitekturanı dəyişdir.

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Feature-Model Mapping

Layihəndəki bütün AI feature-ları siyahıya al. Hər feature üçün: (a) task tipi nədir, (b) latency tələbi nədir, (c) accuracy mühümdür? Bu kriteriyalar əsasında optimal model seç. Nəticəni `config/ai.php`-yə yaz. Mapping-in əsaslandırmasını bir markdown faylda sənədləşdir.

### Tapşırıq 2: Dynamic Complexity Router

Gələn sorğunun mürəkkəbliyini ölçən bir `ComplexityClassifier` implement et: qısa/sadə sorğular → Haiku, orta → Sonnet, uzun/multi-step → Opus. Heuristic meyarlar: token sayı, sual sayı, "why/how/design/analyze" kimi açar sözlər. 50 real sorğu üzərindən routing qərarlarını insan ilə yoxla.

### Tapşırıq 3: Cost Monitoring Dashboard

`ai_requests` cədvəlinə `model`, `input_tokens`, `output_tokens`, `cost_usd`, `feature_name` sütunları əlavə et. 1 həftə data topla. Ən bahalı feature-ları müəyyənləşdir. Hansı feature-lar cheaper modellərə keçidə adaydır?

---

## Əlaqəli Mövzular

- `09-llm-provider-comparison.md` — Provider seçimi — model seçiminin əvvəlki addımı
- `11-llm-pricing-economics.md` — Model seçiminin maliyyə nəticələri
- `02-models-overview.md` — Müxtəlif model ailələrinin xüsusiyyətləri
- `../08-production/03-llm-observability.md` — Model performance-ını production-da izlə
