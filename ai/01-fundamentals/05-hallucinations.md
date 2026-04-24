# LLM Hallucinations: Səbəblər, Taksonomiya, Aşkarlanma və Azaldılma

> Hədəf auditoriyası: Produksiya LLM sistemləri qurub istifadəçilərə cavabların doğruluğuna görə cavabdeh olan senior backend developerlər. Bu fayl 01-how-ai-works.md-dəki generasiya mexanizmini (növbəti-token proqnozu) hallüsinasiyanın **səbəbi** kimi açıqlayır və 10-prompt-injection-defenses.md-dən fərqli olaraq **düşmənçi** deyil, **statistik** uğursuzluqlara fokuslanır. RAG həllərinin dərin detalları 04-rag folderində, citations isə 08-files-api-citations.md-də.

---

## Mündəricat

1. [Hallüsinasiya Nədir və Nə Deyil](#what-is-and-isnt)
2. [Niyə LLM-lər Hallüsinasiya Edir — Mexanizm](#why-llms-hallucinate)
3. [Taksonomiya — Hallüsinasiyaların Kateqoriyaları](#taxonomy)
4. [Confidence Miscalibration](#confidence-miscalibration)
5. [Aşkarlama Metodları](#detection-methods)
6. [Azaldılma Strategiyaları](#mitigation)
7. [Produksiya Playbook-u: Laravel](#production-playbook)
8. [Model Müqayisəsi — Claude/GPT/Gemini Profilləri](#model-comparison)
9. [İstifadəçi Üzünə Dizayn Nümunələri](#user-facing-patterns)
10. [Senior-Səviyyə Anti-Pattern-lər](#anti-patterns)
11. [Qərar Çərçivəsi](#decision-framework)

---

## Hallüsinasiya Nədir və Nə Deyil

Sahədə "hallucination" sözü bulanıq istifadə olunur. Dəqiqləşdirək:

**Hallucination** — modelin **yüksək inamla** həqiqətdə yalan, uydurulmuş və ya dəstəklənməmiş məlumat istehsal etməsi. Vacib: yanlışlıq təsadüfi səhv yox, **statistik olaraq inanılan uydurma**-dır. Model həqiqət axtarmır — növbəti tokeni proqnozlaşdırır.

**Hallucination DEYİL**:
- Bilinməyən şeyin "bilmirəm" deyilərək qayıdılması (düzgün refuzal)
- Köhnəlmiş məlumat (model knowledge cutoff-dan sonrakını bilmir)
- İstifadəçi yanlış premise verdikdə modelin onu qəbul etməsi (bu sycophancy-dir, ayrı problem)
- Subyektiv rəy fərqliliyi
- Kreativ yazıda uydurma (əgər tələb olunubsa)

```
Misal 1 (HALLUCINATION):
 İstifadəçi: "Laravel 10.5-də hansı yeni database driver əlavə olundu?"
 Model:      "Laravel 10.5-də YugabyteDB driver native dəstəyə gəldi..."
 Gerçəklik: Elə bir şey yoxdur. Model inanılan amma yalan fakt yaratdı.

Misal 2 (HALLUCINATION DEYİL):
 İstifadəçi: "2026-cı ildə nə baş verdi?"
 Model:      "Bilmirəm, mənim knowledge cutoff-um..."
 Bu doğru cavabdır — məhdudiyyətin etiraf edilməsi.

Misal 3 (KƏNAR HAL — faithfulness hallucination):
 İstifadəçi: [1000 sözlük sənəd] + "Bu sənəddəki 3 əsas riski sadala"
 Model:       Sənəddə olmayan 1 risk əlavə edir, 2-sini doğru götürür
 Bu da hallucination-dır — qaynağa "sadiq" deyil.
```

---

## Niyə LLM-lər Hallüsinasiya Edir — Mexanizm

### Sebeb 1: Next-Token Prediction Bilik Axtarışı Deyil

LLM bir database-ə **sorğu atmır**. Hər addımda yalnız bunu soruşur: "Bu prefiks verilmiş halda, ən inanılan növbəti token hansıdır?" Öyrənmə zamanı "Laravel 10.5-də YugabyteDB əlavə olundu" kimi cümlələr statistik olaraq mümkün görünür — çünki:

- "Laravel X.Y-də Z driver əlavə olundu" **paternı** korpus boyu yüzlərlə dəfə rast gəlir
- "YugabyteDB" və "Laravel" tokenləri bir-birinə semantik yaxındır (DB konteksti)
- Model üçün **düzgün cavab** ilə **inanılan cavab** arasındakı fərq yoxdur

```
Model baxışı:
  prefiks = "Laravel 10.5-də hansı yeni database driver"
  P(" YugabyteDB driver native dəstəyə gəldi") = 0.04
  P(" əlavə olunmadı, 10.5 minor release idi") = 0.003
  P(" soruşursan, dəqiq xatırlamıram") = 0.001

Model statistik olaraq "inanılan yalanı" seçir, çünki o,
paternə daha yaxşı uyur. Sadəcə "həqiqət" ölçüsü yoxdur.
```

### Sebeb 2: Training Data Gaps və Uzun Quyruq

Pretraining korpusu nə qədər böyük olsa da, **uzun quyruq** faktları (konkret versiyalar, spesifik tarixlər, adamların dəqiq bioqrafiyası) az nümunələrlə öyrənilir. Model bu faktları "dumanlı" şəkildə saxlayır — ümumi pattern var, amma dəqiqlik yoxdur.

```
Geniş fakt (çox nümunə):      "Paris Fransanın paytaxtıdır" — dəqiq
Uzun quyruq (az nümunə):      "Laravel 8.12-də bug-fix X"   — dumanlı
Niş/qapalı məlumat (0 nümunə): "XYZ şirkətinin CEO-su kimdir" — uydurma
```

### Sebeb 3: Confidence Calibration Pis Öyrədilib

LLM təkcə cavab vermir, həm də **nə qədər əmin olduğunu** çıxarır. Lakin RLHF prosesində insan annotator-lar "əminlik" və "doğruluğu" həmişə ayırd etmir — nəticədə model "bilmirəm" deməkdənsə inamlı yalan deməyi öyrənir (çünki inamlı cavab reward modeli tərəfindən daha yüksək qiymətləndirilir).

### Sebeb 4: Compression Lossy-dir

01-how-ai-works.md-də dediyimiz kimi, model trilyonlarla token korpusu milyardlarla parametrə sıxışdırır. Bu itkili sıxışdırmadır. Nadir/spesifik faktlar sıxışdırmada məlumat itirir.

### Sebeb 5: Autoregressive Commitment

Model tokeni seçəndən sonra onu geri qaytara bilmir. Əgər 3-cü tokendə səhvə başlayıbsa, qalan 200 tokeni həmin səhvi **rasionallaşdırmağa** sərf edəcək.

```
Token 1-3: "Laravel 10.5-də YugabyteDB"
Token 4+:  Artıq bu səhvə "commit" olub.
          Model indi bu yalanı dəstəkləyən texniki detallar uydurur.
          "Driver Aspire paketi ilə birlikdə..." — daha çox uydurma.
```

Bu "snowball" effektidir. Qarşısını almağın yollarından biri extended thinking (07-extended-thinking.md) — model cavabı commit etməmişdən əvvəl daxili plan qurur.

---

## Taksonomiya — Hallüsinasiyaların Kateqoriyaları

Tədqiqat sahəsində bir neçə ortoqonal taksonomiya var. Ən faydalıları:

### 1. Intrinsic vs Extrinsic

```
INTRINSIC: Cavab qaynağa (source document) ziddir.
  İstifadəçi: [mətn: "qiymət $100-dır"] + "Qiymət nədir?"
  Model:      "Qiymət $150-dir"
  Qaynaqla birbaşa ziddiyyət.

EXTRINSIC: Cavab qaynaqda olmayan, yoxlanıla bilməyən
  məlumat əlavə edir.
  Model:      "$100-dır və bu keçən il 10% artmışdı"
  Artım haqqında qaynaqda söz yox — əlavə olunmuş uydurma.
```

### 2. Factual vs Faithfulness

```
FACTUAL: Dünya bilgisinə zidd.
  "Einstein Fizika üzrə 2 Nobel aldı" (yalandır, 1 aldı)

FAITHFULNESS: Kontekstə (RAG/sənəd) sadiq deyil.
  Qaynaqdakı mətn "X həyatda qalıb" deyir,
  model "X öldü" yazır. Dünya bilgisi dəyərsizdir,
  məqsəd qaynağa sadiqlikdir.
```

RAG sistemlərində əsas risk **faithfulness** hallucination-udur.

### 3. Closed-domain vs Open-domain

```
CLOSED-DOMAIN: Giriş sənədi ilə məhdud tapşırıq
  (sənəddən cavab çıxarmaq, translate, summarize).
  Burada hallucination = sənəddə olmayan şey uydurmaq.

OPEN-DOMAIN: Sərbəst bilgi sorğuları.
  ("Rust-da Tokio runtime necə işləyir?")
  Burada hallucination = dünyanın faktlarına zidd.
```

### 4. Detection Perspektivindən

| Tip | Aşkarlanması | Nümunə |
|---|---|---|
| **Confabulation** | Çətin | "Einstein ___-də doğulub" — uydurma tarix |
| **Context Neglect** | Asan (qaynaqla müqayisə) | Verilmiş sənəddə olmayan rəqəm |
| **Logical Inconsistency** | Orta | "X hamısından böyükdür, Y X-dən böyükdür" |
| **Citation Fabrication** | Orta (link yoxlaması) | Olmayan URL/paper |
| **Arithmetic Error** | Asan (re-compute) | Səhv hesablama |
| **Code Hallucination** | Orta (lint/run) | Olmayan funksiya/API çağırışı |

---

## Confidence Miscalibration

LLM-lər həm düzgün, həm yalan cavabları **eyni inamla** verir. Bu ən təhlükəli aspektdir — istifadəçi üçün xəbərdarlıq siqnalı yoxdur.

```
Gerçəklik:           Modelin inamı:
Düzgün cavab (85%):  "Təbii ki, X-dir"  ← eyni ton
Yalan cavab (15%):   "Təbii ki, X-dir"  ← eyni ton

Yaxşı kalibrə olmuş model:
Düzgün cavab (85%):  "X-dir"
Yalan cavab (15%):   "Mənə görə X-dir, amma dəqiq deyiləm"
```

Bunu ölçmək üçün **Expected Calibration Error (ECE)** metrikasından istifadə olunur. Müasir modellər pre-RLHF daha yaxşı kalibrə olunub; RLHF-dən sonra inamı şişirtmək çox vaxt baş verir.

Praktiki nəticə: İstifadəçiyə göstərilən cavabda model özünün söylədiyi **əminlik səviyyəsinə güvənmə**. Verifikasiyanı xaricdən qur.

---

## Aşkarlama Metodları

### 1. Self-Consistency Sampling

Eyni prompt-u temperature > 0 ilə N dəfə çağır. Əgər cavablar bir-biriylə uyğundursa, inam yüksəkdir; uyğun deyilsə, model bilmirsə amma uydurursa.

```
Prompt: "Laravel 10.5-də hansı driver əlavə olundu?"
N=5 sample:
 1. "YugabyteDB driver"
 2. "Heç bir driver, minor release"
 3. "DuckDB driver native dəstəyi"
 4. "Laravel 10.5-də native database driver dəyişikliyi yoxdur"
 5. "RedisCluster driver"

Uyğunsuzluq yüksəkdir → model bilmir, HALLUCINATES.
```

Laravel-də Job-lar vasitəsilə paralel N çağırış etmək asandır.

### 2. SelfCheckGPT

Self-consistency-nin texniki formalizasiyası. N variant çıxışı əsas cavabla müqayisə et (NLI — Natural Language Inference modeli və ya embedding similarity ilə). Əgər variantlar əsas cavabı **dəstəkləmirsə**, hallucination ehtimalı yüksəkdir.

```
Pipeline:
1. Əsas cavab al (T=0)
2. N variant al (T=0.7)
3. Hər cümlə üçün:
   - Hər variantda bu cümlə dəstəklənir?
   - Dəstək skoru: (N-ziddiyyət)/N
4. Skor aşağıdırsa → hallucination flag
```

### 3. Semantic Entropy

Daha dəqiq: çıxışları embedding-lə klasterizə et. Əgər cavablar semantik olaraq bir klasterdədirsə, inam yüksəkdir; çoxlu klaster varsa, model ehtimallar arasında bölünüb.

```
"Kapital Fransa?" → 10 sample, hamısı "Paris" → 1 cluster → LOW entropy
"Hansı sıxlıq?"    → 10 sample, 3 fərqli rəqəm → 3 cluster → HIGH entropy
```

### 4. NLI-based Groundedness

RAG cavablarında: hər çıxış cümləsini retrieved qaynaqla NLI modeli (məs., DeBERTa-v3 NLI) ilə yoxla. "Entailment", "contradiction", "neutral" skoru çıxar. Neutral/contradiction hissələr hallucination risklidir.

### 5. Groundedness Scoring (LLM-as-judge)

Ayrıca bir LLM çağırışı ilə cavab + qaynaq verərək soruş: "Bu cavabdakı hər iddia qaynaqda dəstəklənir mi? Dəstəklənməyən cümlələri siyahıla." Ucuzdur (Haiku ilə etmək olar), ancaq judge modelinin özü də hallucinate edə bilər.

### 6. Logit/Probability Analizi

Açıq-qaynaqlı modellərdə (Anthropic API-da tam məhdud): token ehtimallarını yoxla. Yüksək perplexity hissələri şübhəlidir. Anthropic-də bu yoxdur, ancaq streaming zamanı orta token generasiya vaxtı proksidir (qeyri-müəyyənlik olanda model "düşünür").

### 7. Schema Validation

Structured output (JSON Schema) istifadə edirsənsə, çıxışın sxemə uyğunluğu hallucination filtridir. Sxem pozulursa — rejection + retry.

### 8. External Fact-Check

Retriable fakt (tarix, rəqəm, müəssisə) varsa, cavabı kənar mənbə ilə yoxla: Wikipedia API, rəsmi docs, company DB. Ən etibarlı aşkarlama metodudur.

---

## Azaldılma Strategiyaları

### 1. RAG (Retrieval Augmented Generation)

Modelin öz yaddaşına güvənmə — cavab verməzdən əvvəl **autoritativ qaynaqdan** material al və prompt-a əlavə et. Bu, factual hallucination-u kəskin azaldır, amma faithfulness hallucination-u qalır (model mətn oxuyur, amma səhv şərh edə bilər).

### 2. Grounding + Explicit Refusal

System prompt-da:

```
Siz yalnız təqdim olunmuş <documents> bloku əsasında cavab verirsiniz.
Əgər cavab sənəddə yoxdursa, DƏQIQ BELƏ YAZIN: "Təqdim olunan
sənədlərdə bu suala cavab tapmadım."
Heç vaxt sənəddən kənar məlumat uydurmayın.
```

Bu tək başına 30-50% azalma verir. Claude xüsusilə refuzal-da güclüdür.

### 3. Temperature = 0 (və ya çox aşağı)

Faktual tapşırıqlarda temperature=1 hallucination riskini artırır — model statistik olaraq ikinci-ən-inanılan varyantı seçə bilər. `temperature=0` determinizm vermir (floating-point non-determinism qalır) amma riski azaldır.

### 4. Structured Output / JSON Schema

Pulsuz ərazini bağlamaq: model `{"author": "X", "year": Y}` formatında çıxış verməyə məcburdur. Schema-da `year: integer | null` olsa, "bilmirəm" null-a məcbur edilə bilər.

```php
$schema = [
    'type' => 'object',
    'properties' => [
        'author' => ['type' => ['string', 'null']],
        'year'   => ['type' => ['integer', 'null']],
        'confidence' => [
            'type' => 'string',
            'enum' => ['high', 'medium', 'low', 'unknown']
        ],
    ],
    'required' => ['author', 'year', 'confidence'],
];
```

### 5. Extended Thinking

07-extended-thinking.md-də ətraflı: model cavabı komit etməzdən əvvəl daxili mühakimə aparır. Mürəkkəb mantiq/riyaziyyat tapşırıqlarında hallucination-u kəskin azaldır.

### 6. Few-shot Examples with Uncertainty

System prompt-da bilmədiyi halda "bilmirəm" deyən nümunələr ver:

```
Nümunə 1:
 Sual: "Laravel 8.12.3 versiyasının release tarixi?"
 Cavab: "Dəqiq versiya tarixi yaddaşımda yoxdur."

Nümunə 2:
 Sual: "PHP-nin 3.0 versiyası hansı ildə çıxıb?"
 Cavab: "1998-ci ildə çıxıb."
```

Bu "calibration training" effektini yaradır.

### 7. Citations (08-files-api-citations.md)

Claude Citations feature-ı ilə model hansı span-a əsaslandığını göstərir. Əgər cavabın verdiyi iddia citation-suz gəlirsə — şübhəlidir.

### 8. Guardrails (Post-processing)

Pre-response validation layer:
- Regex ilə yalançı URL-ləri yoxla
- Hər rəqəmi DB-də təsdiqlə
- Tarixləri ranqa (1990-2026) yoxla
- Şirkətin bilinməyən adlarını flag et

### 9. Two-pass Verification

Birinci çağırış cavabı yaradır. İkinci çağırış "yoxlayıcı" rolundadır:

```
Verifier prompt:
"Aşağıdakı cavabda qaynaqda olmayan iddialar var?
Varsa, dəqiq cümləni sitat gətir."
```

Ucuz modelle (Haiku) etmək olar. Cavab "xeyr" deyilsə, original cavabı istifadəçiyə qaytarma.

### 10. Constrained Decoding

Bəzi provayderlər (Anthropic bilavasitə deyil) məcburi grammar/regex verir. Məsələn, `\\d{4}-\\d{2}-\\d{2}` formatlı tarix. Bu, formatca hallucination-u azaldır (amma məzmunu yox).

---

## Produksiya Playbook-u: Laravel

### Məqsəd

Laravel tətbiqində hallucination rate-ini ölçmək, loglamaq, trend izləmək və eskalasiya etmək.

### Arxitektura

```
┌──────────────┐    ┌────────────────┐    ┌──────────────┐
│ User Request │───▶│ Claude Service │───▶│   Claude API │
└──────────────┘    └────────────────┘    └──────────────┘
                           │
                           ▼
                    ┌────────────────┐
                    │ HallucinationMonitor │
                    │  - self-check sample │
                    │  - NLI groundedness  │
                    │  - schema validate   │
                    └────────────────┘
                           │
              ┌────────────┼─────────────┐
              ▼            ▼             ▼
         ┌────────┐  ┌──────────┐  ┌──────────┐
         │ logs   │  │ metrics  │  │ alerts   │
         │ (DB)   │  │ (Prom.)  │  │ (ops)    │
         └────────┘  └──────────┘  └──────────┘
```

### 1. LLMCall Eloquent Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LLMCall extends Model
{
    protected $fillable = [
        'user_id', 'request_id', 'model', 'prompt_hash',
        'prompt_tokens', 'completion_tokens', 'temperature',
        'response_text', 'groundedness_score', 'hallucination_flag',
        'source_documents', 'self_check_variance', 'verifier_verdict',
        'latency_ms', 'cost_usd',
    ];

    protected $casts = [
        'source_documents' => 'array',
        'hallucination_flag' => 'boolean',
        'groundedness_score' => 'float',
        'self_check_variance' => 'float',
    ];
}
```

### 2. HallucinationMonitor Service

```php
<?php

namespace App\Services\LLM;

use Anthropic\Anthropic;
use App\Models\LLMCall;
use Illuminate\Support\Facades\Log;

class HallucinationMonitor
{
    public function __construct(
        private Anthropic $claude,
        private float $groundednessThreshold = 0.7,
        private int $selfCheckSamples = 3,
    ) {}

    public function evaluate(
        string $prompt,
        string $response,
        array $sourceDocuments = [],
    ): HallucinationReport {
        $groundednessScore = !empty($sourceDocuments)
            ? $this->checkGroundedness($response, $sourceDocuments)
            : null;

        $variance = $this->selfCheckVariance($prompt, $response);

        $flag = false;
        $reasons = [];

        if ($groundednessScore !== null && $groundednessScore < $this->groundednessThreshold) {
            $flag = true;
            $reasons[] = "low_groundedness:{$groundednessScore}";
        }

        if ($variance > 0.5) {
            $flag = true;
            $reasons[] = "high_variance:{$variance}";
        }

        return new HallucinationReport(
            flagged: $flag,
            groundedness: $groundednessScore,
            variance: $variance,
            reasons: $reasons,
        );
    }

    private function checkGroundedness(string $response, array $docs): float
    {
        $verifierPrompt = <<<PROMPT
Aşağıdakı cavabın verilmiş qaynaqlarda NE QƏDƏR dəstəkləndiyini
qiymətləndirin. Hər iddia üçün:
- supported: qaynaqda tam var
- partial: qısmən var / yozum var
- unsupported: qaynaqda yoxdur

Yalnız JSON qaytarın:
{"supported": N, "partial": N, "unsupported": N, "score": 0.0-1.0}

QAYNAQLAR:
{$this->formatDocs($docs)}

CAVAB:
{$response}
PROMPT;

        $result = $this->claude->messages()->create([
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 500,
            'temperature' => 0,
            'messages' => [
                ['role' => 'user', 'content' => $verifierPrompt],
            ],
        ]);

        $json = json_decode($result->content[0]->text, true);
        return (float) ($json['score'] ?? 0.5);
    }

    private function selfCheckVariance(string $prompt, string $baseResponse): float
    {
        $samples = [];
        for ($i = 0; $i < $this->selfCheckSamples; $i++) {
            $r = $this->claude->messages()->create([
                'model' => 'claude-haiku-4-5',
                'max_tokens' => 300,
                'temperature' => 0.8,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
            $samples[] = $r->content[0]->text;
        }

        return $this->semanticVariance($baseResponse, $samples);
    }

    private function semanticVariance(string $base, array $samples): float
    {
        // Basit: Levenshtein / token-overlap
        // Production: embedding-based cosine distance
        $totalDistance = 0;
        foreach ($samples as $s) {
            $similarity = similar_text(
                mb_strtolower($base),
                mb_strtolower($s),
                $percent,
            );
            $totalDistance += (1 - $percent / 100);
        }
        return $totalDistance / count($samples);
    }

    private function formatDocs(array $docs): string
    {
        return collect($docs)->map(fn($d, $i) => "[Doc {$i}]\n{$d}")->implode("\n\n");
    }
}
```

### 3. Dispatching as Queue Job

Self-check bahalıdır — synchronous cavab gecikdirməmək üçün background-a:

```php
<?php

namespace App\Jobs;

use App\Models\LLMCall;
use App\Services\LLM\HallucinationMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateHallucinationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $llmCallId) {}

    public function handle(HallucinationMonitor $monitor): void
    {
        $call = LLMCall::findOrFail($this->llmCallId);

        $report = $monitor->evaluate(
            prompt: $call->prompt_text ?? '',
            response: $call->response_text,
            sourceDocuments: $call->source_documents ?? [],
        );

        $call->update([
            'hallucination_flag' => $report->flagged,
            'groundedness_score' => $report->groundedness,
            'self_check_variance' => $report->variance,
        ]);

        if ($report->flagged) {
            \App\Events\HallucinationDetected::dispatch($call, $report);
        }
    }
}
```

### 4. Metrik İxrac

Prometheus-a hallucination rate çıxar:

```php
// Scheduled job, hər 1 dəqiqə
$rate = LLMCall::where('created_at', '>=', now()->subMinute())
    ->selectRaw('SUM(hallucination_flag) / COUNT(*) as rate')
    ->value('rate') ?? 0.0;

Prometheus::getOrRegisterGauge('app', 'llm_hallucination_rate_1m', 'Hallucination rate')
    ->set($rate);
```

### 5. Alerting

Hallucination rate 5% threshold-u keçəndə PagerDuty / Slack:

```yaml
# alertmanager rule
- alert: HighHallucinationRate
  expr: llm_hallucination_rate_1m > 0.05
  for: 5m
  annotations:
    summary: "Hallucination rate {{ $value | humanizePercentage }} > 5% son 5 dəqiqədə"
```

### 6. Baseline Təyin Etmək

Produksiyaya çıxmadan əvvəl **known-answer evaluation set** yarat: 200-500 sual, əl ilə doğru cavablar. Hər deploy-dan əvvəl bunu işlət, baseline hallucination rate tut (məs., 3-8%). Deploy-dan sonra rate əhəmiyyətli artırsa — rollback.

---

## Model Müqayisəsi — Claude/GPT/Gemini Profilləri

Modellər fərqli "hallucination profil"-inə sahibdir. Hər modelin öz zəifliyi və güclü tərəfi var. Ümumi trend (2026 əvvəli):

| Model | Factual Acc. | Faithfulness | Refuzal Rate | Confabulation Risk |
|---|---|---|---|---|
| Claude Sonnet 4.6+ | yüksək | çox yüksək | yüksək | aşağı |
| Claude Opus 4.7 | çox yüksək | çox yüksək | yüksək | çox aşağı |
| GPT-4o / GPT-4.1 | yüksək | orta-yüksək | orta | orta |
| o1 / o3 | çox yüksək | yüksək | aşağı | aşağı |
| Gemini 2.5 Pro | yüksək | orta | orta | orta |
| Open-source (Llama 3 70B) | orta | orta | aşağı | yüksək |

**Qeydlər:**
- **Claude** refuzal-da ən konservativdir — "bilmirəm" deməyə meyilli. Faithfulness (RAG-da) liderdir.
- **GPT-4** daha inamlıdır, bəzən yanlış də olsa cavab verir. Mürəkkəb mühakimədə güclü.
- **Gemini 2.5** uzun context-də yaxşı, amma niş domen-lərdə confabulation meyli.
- **o-series reasoning** modellər hallucination-u thinking ilə azaldır, amma kostu artır.

### Konkret Zəifliklər

- **Citation fabrikasiyası**: GPT-4 daha çox uydurur (paper, URL). Claude daha az — amma yenə baş verir.
- **Kod hallucination**: Bütün modellər "olmayan funksiya" uydurur. Claude yerli framework-lərdə daha yaxşı.
- **Niş dillər (Azərbaycanca)**: Hamısı İngiliscədə daha dəqiq. Azərbaycan faktları üzərində RAG şiddətlə tövsiyə olunur.

---

## İstifadəçi Üzünə Dizayn Nümunələri

Hallucination-u 0-a endirmək mümkün deyil. Dizaynda bunu **qəbul et** və istifadəçini informalaşdır.

### 1. Confidence Badges

```
┌───────────────────────────────────────┐
│ Cavab: Laravel 10.5-də yeni driver... │
│                                       │
│ [high confidence] [verified]          │ ← yaşıl
└───────────────────────────────────────┘

┌───────────────────────────────────────┐
│ Cavab: Mənim bildiyimə görə 2024-də...│
│                                       │
│ [low confidence] [unverified]         │ ← sarı
│ Zəhmət olmasa yoxlayın.               │
└───────────────────────────────────────┘
```

### 2. Explicit "Bilmirəm"

Sistem prompt-da güclü instruction:

```
Əgər dəqiq bilmirsənsə, "Bu haqda dəqiq məlumatım yoxdur, yoxlamağı
tövsiyə edirəm" de. Uydurma cəhdi etmə.
```

Claude-da bu çox effektivdir.

### 3. Citations (Source Attribution)

08-files-api-citations.md-də ətraflı. Hər iddia qarşısında [1], [2] kimi qaynaq nömrələri göstər. İstifadəçi yoxlaya bilər.

### 4. "Verify This" Buttons

Hər kritik iddia yanında "Mənbəyi yoxla" düyməsi — external search və ya DB-yə link.

### 5. Disclaimer (hüquqi minimum)

"Bu sistem AI tərəfindən yaradılıb. Kritik qərarlarda mütəxəssislə məsləhətləşin" — xüsusilə maliyyə, tibb, hüquq domen-lərində zəruridir.

---

## Senior-Səviyyə Anti-Pattern-lər

### 1. "LLM Həqiqət Mənbəyi" Anti-Pattern

Anti-pattern: "Claude internet üzərində öyrədilib, ona görə hər şeyi bilir" və birbaşa faktual cavabı DB ilə yoxlamadan istifadəçiyə göstərmək.

Doğru: LLM mühakimə/rəbt/sintez üçün yaxşıdır. Faktları RAG + authoritative DB ilə təchiz et.

### 2. Temperature = 1 "Yaradıcılıq Üçün"

Faktual/axtarış cavablarında temperature=1 hallucination-u ikiqat artırır. Yaradıcı yazıda OK, rəsmi cavabda 0-0.3.

### 3. Self-Check-i İgnore Etmək

"Çox bahalı" deyərək self-consistency və groundedness-i atıb birbaşa istifadəçiyə göndərmək. İlk production incident-dən sonra yenidən qurmalısan.

### 4. Hallucination-u Tək Metrika İlə Ölçmək

"Bizim hallucination rate 3%-dir" — amma hansı test set üzərində? Hansı domendə? Bir slice həqiqi rate-i gizlədə bilər. Multiple cohort-lar üçün ölç.

### 5. "Prompt-u Gücləndirməklə Tam Həll Edərik"

"Hallucinate etmə, dəqiq ol, bilmədiyin zaman bilmirəm de" sözləri inhibition faktoru kimi faydalıdır, amma 0-a endirmir. Tək bu ilə 40% kəsir — qalanı architecture işidir.

### 6. Qaynaq Sənədləri Güvənmək

RAG-da qaynaq sənədlərinin özü ya yanlış, ya köhnə ola bilər. "Groundedness" yoxlaması **faithfulness-i** təsdiq edir, amma **factuality-ni** təsdiq etmir.

### 7. Tək Model Provayderə Güvənmək

Bir modelin "kor nöqtəsi" digərinin fortessi ola bilər. Kritik tapşırıqlarda **second opinion** — 2 model çağırışı və çarpaz yoxlama.

### 8. "Bizdə Test Var, Yaxşıdır"

Unit testlər hallucination tuta bilməz — onlar konkret inputa konkret output test edir. Hallucination distribution problemidir, minlərlə nümunə lazımdır. Nightly evaluation pipeline qur.

### 9. Production-da İlk Dəfə Qarşılaşmaq

Staging-də evaluation set-ini run et. Hallucination baseline-ı bil. Yeni model/prompt versiyası əvvəlcə baseline-la müqayisə olunmalıdır.

### 10. Silent Failure

Hallucination aşkarlandığında istifadəçiyə bildiriş olmadan cavab göstərmək. Ən azı log + flagging olmalıdır ki, support team görsün.

---

## Qərar Çərçivəsi

### Nə Zaman Hansı Strategiya?

| Tapşırıq | Əsas Risk | Tövsiyə Strateji |
|---|---|---|
| Q&A sənəd üzərində | Faithfulness | RAG + citations + groundedness check |
| Faktual sorğu (open-domain) | Factuality | Web search tool + verification |
| Kod generasiyası | Fake API | Run/compile + test |
| Summarization | Extrinsic | Constraint ilə: "yalnız sənəddəki" |
| Translation | Omission | Two-pass: translate + reverse-translate |
| Classification | Edge cases | Schema + confidence threshold |
| Agentic (tool use) | Chain error | Extended thinking + checkpoint |
| Customer support | Policy hallucination | RAG + strict refuzal + escalation |

### Budget Allokasiyası

Hallucination azaldılması bahalıdır. Budget-i belə paylayın:

```
1. RAG qurma (birinci və ən təsirli):          40%
2. Prompt engineering + few-shot:               15%
3. Schema validation + structured output:       10%
4. Verifier model (post-check):                 15%
5. Monitoring + alerting infra:                 10%
6. Evaluation dataset + nightly test:           10%
```

RAG hələ qurulmayıbsa, digər hər şey marginal effekt verir.

### "Ship" vs "Hold" Qərarı

Model yeni deployment-a hazırdır əgər:
- Evaluation set üzərində hallucination rate baseline-dan pis deyil
- Kritik domendə (tibb, maliyyə) <1% hallucination
- Low-confidence cavablarda refuzal rate ≥95%
- Monitoring infra prod-a bərabər qurulub
- Rollback planı var (prev version bir klikə)

---

## Xülasə

- Hallucination, LLM-in **next-token prediction** mexanizminin təbii nəticəsidir, bug deyil
- Taksonomiya: intrinsic/extrinsic, factual/faithfulness, closed/open-domain
- Aşkarlama: self-consistency, SelfCheckGPT, semantic entropy, NLI groundedness, LLM-as-judge
- Azaldılma: RAG > prompt engineering > structured output > extended thinking > verifier
- Produksiya-da: hər çağırışı logla, arxa planda evaluate et, rate-i metrika kimi izlə, alert qur
- Model seçimində Claude RAG/faithfulness tapşırıqlarında üstündür, GPT-4 daha inamlıdır (yaxşı və pis mənada)
- İstifadəçini informalaşdır: citations, confidence badges, "verify" linkləri
- Anti-pattern: LLM-i tək həqiqət mənbəyi kimi istifadə etmək
- Qızıl qayda: **Hallucination-u tam aradan qaldırmaq mümkün deyil; sən onu ölçən və idarə edən sistem qurursan**

---

*Növbəti: [10 — Embedding və Generativ Modellər](./06-embedding-vs-generative-models.md)*
