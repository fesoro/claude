# Reasoning Models: Extended Thinking, o1/o3, DeepSeek R1 və Test-Time Compute (Senior)

> Hədəf auditoriyası: Kompleks tapşırıqlar (coding, planning, math, multi-step analysis) üçün model seçimi edən senior developerlər və tech lead-lər. Bu fayl 01-how-ai-works.md-in davranış nəticəsini — niyə bəzi modellərin "düşünmə" addımı daxil etdiyini — izah edir. Claude-spesifik API detalları üçün 07-extended-thinking.md-ə bax; provayder müqayisəsi üçün 09-llm-provider-comparison.md.

---

## Mündəricat

1. [Reasoning Model Nədir?](#what-is-reasoning-model)
2. [Chain-of-Thought Prompting vs Built-in Reasoning](#cot-vs-builtin)
3. [Test-Time Compute Paradigm Shift](#test-time-compute)
4. [Necə Öyrədilir — RL on Reasoning Traces](#training)
5. [Thinking Budget — Visible vs Invisible Tokens](#thinking-budget)
6. [Provider Landscape 2026](#provider-landscape)
7. [Claude Extended Thinking](#claude-extended-thinking)
8. [OpenAI o-series (o1, o3, o4)](#openai-o-series)
9. [DeepSeek R1 — Open Weights Reasoning](#deepseek-r1)
10. [Gemini 2.5 Thinking](#gemini-thinking)
11. [Nə Zaman Reasoning Faydalıdır](#when-reasoning-helps)
12. [Nə Zaman Reasoning Zərərlidir](#when-reasoning-hurts)
13. [Prompting Fərqləri](#prompting-differences)
14. [Pricing və Cost Implication](#pricing)
15. [Streaming və Latency Reality](#streaming-latency)
16. [Laravel Kod Nümunələri](#laravel-examples)
17. [Evaluation — Necə Ölçmək](#evaluation)
18. [Anti-Pattern-lər](#anti-patterns)
19. [Qərar Çərçivəsi](#decision-framework)

---

## Reasoning Model Nədir?

Klassik LLM (Claude 3.5, GPT-4o, Gemini 1.5 tipli) bir prompt alır və dərhal cavab tokenləri yaratmağa başlayır. Reasoning model isə cavabı yaratmamışdan əvvəl **daxili mühakimə tokenlərindən ibarət ayrı mərhələ** icra edir — model öz-özünə "fikirləşir", plan qurur, alternativlər yoxlayır, sonra final cavabı formalaşdırır.

```
KLASİK MODEL:
 prompt → [forward pass × N tokens] → cavab
 
REASONING MODEL:
 prompt → [thinking pass × M tokens] → [answer pass × N tokens] → cavab
            (gizli və ya görünən)
```

Kritik fərqlər:

1. **Daxili plan**: model cavabdan əvvəl strukturlu mühakimə kəlmələri yaradır
2. **Budget**: developer "nə qədər düşünsün" limitini təyin edə bilir
3. **Dəqiqlik**: math, coding, planning benchmark-larda kəskin üstünlük
4. **Latency**: kəskin artır — saniyələr, bəzən dəqiqələr
5. **Cost**: thinking tokenlər də pullu sayılır (çoxunda output rate ilə)

---

## Chain-of-Thought Prompting vs Built-in Reasoning

2022-ci ildə populyarlaşan "Chain-of-Thought" (CoT) prompting texnikası göstərdi ki, **modelə "step-by-step düşün" deməklə** dəqiqlik artır:

```
Klassik prompt:   "23 × 47 = ?"
Cavab:            "1080" (yanlış)

CoT prompt:       "23 × 47 = ? Addım-addım izah et."
Cavab:            "23 × 40 = 920, 23 × 7 = 161, 920 + 161 = 1081"
```

CoT mexanizmi sadə idi: model daha çox intermediate token yaratdıqca, compute onun işləməsinə imkan verir. "Thinking out loud" effekti.

Problem: hər dəveloper özü prompt mühəndisliyi etməli idi.

### Built-in Reasoning (2024-də o1)

OpenAI o1 fərqli yanaşma təqdim etdi: **modelin özü RL ilə** daxili reasoning addımlarını yaratmağı öyrəndi. Developer isə prompt-u dəyişdirmədən, sadə "sual" verir — model avtomatik plan qurur.

Fərq əsaslıdır:

| Xüsusiyyət | Manual CoT | Built-in Reasoning |
|---|---|---|
| Kim tetikləyir | Developer | Model özü |
| Harada yerləşir | Normal cavab içində | Ayrı thinking bloku |
| Keyfiyyət | Tutarsız | Consistent |
| Təlim üsulu | Yoxdur (prompt texnikası) | RL on reasoning traces |
| Effektivlik | 5-15% boost | 20-50% boost hard tapşırıqlarda |
| Prompt komplekslik | Manual | Minimal |

---

## Test-Time Compute Paradigm Shift

2017-2023-cü illər ərzində LLM sahəsində əsas miqyaslanma **pretraining compute** idi — daha böyük model, daha çox data, daha çox GPU. "Scaling laws" (Kaplan, Chinchilla) bunu müəyyən edirdi.

2024-cü ildən (o1-in çıxışı ilə) yeni paradigma: **test-time compute** (inference-də daha çox hesablama = daha yaxşı cavab).

```
Əvvəlki eraya: keyfiyyət = f(model_size, pretraining_data)
İndi:           keyfiyyət = f(model_size, pretraining_data, THINKING_TOKENS)
```

Bu, biznes-lərin compute bütcəsini necə paylaşdıqlarını dəyişdirir:
- Əvvəl: bir dəfə pretraining-ə $100M xərclə, sonra ucuz inference
- İndi: inference-də də ciddi compute xərcləmək məntiqlidir — çünki bir cavab üçün 10.000 thinking token daha yaxşı nəticə verir

Göstərişli (Google DeepMind məqaləsindən 2024): **Küçük model + çox thinking**, **böyük model + az thinking**-dən tez-tez üstündür.

---

## Necə Öyrədilir — RL on Reasoning Traces

Müasir reasoning modellər iki mərhələdən keçir:

### Mərhələ 1: Pretraining

Klassik next-token prediction (01-how-ai-works.md-də izah olunub). Model dili öyrənir.

### Mərhələ 2: Reasoning RL

Model verified correct answer-i olan mürəkkəb tapşırıqlar üzərində işlədilir (math problemləri, coding challenges). Her tapşırıq üçün:

```
1. Model N fərqli "thinking trace" yaradır (temperature > 0)
2. Hər trace-in final cavabı yoxlanır (unit test / math checker)
3. Düzgün cavabla bitən trace-lər "positive", yanlışlar "negative"
4. Model RL ilə optimize olunur — positive trace-lərin ehtimalı artırılır
5. Model öyrənir: "uzun, strukturlu düşüncə = daha tez-tez düzgün cavab"
```

Bu sadə loop-un nəticələri möhtəşəmdir — o1 Olimpiada səviyyəli riyaziyyat məsələlərini həll edir, o3 frontier math benchmark-larda rekord qırır.

**Açar fərq**: model **öz-özünə öyrəndi** ki, plan qurmaq, alternativ yoxlamaq, səhvi düzəltmək faydalıdır. Human-ın CoT təlimi lazım deyil.

### Emerging Behaviors

Reasoning modellərdə müşahidə olunan (öyrədilməmiş) davranışlar:
- **Self-correction**: "Gözlə, yanlış hesabladım, yenidən baxım"
- **Backtracking**: "Bu yolu atım, başqa yanaşaq"
- **Verification**: "Cavab X. Yoxlayım: X × 2 = ... bəli, doğrudur"
- **Decomposition**: "Bu problemi 3 alt-problemə parçalayım"

Bu davranışlar yalnız RL train datasetində ölçüldükdə ortaya çıxdı — və onlar sonrakı təlimsiz də istifadə edildi.

---

## Thinking Budget — Visible vs Invisible Tokens

### Budget Anlayışı

Reasoning model developer-ə "nə qədər düşünsün" parametrini verir. Bu, thinking tokenlərinin maksimum sayıdır.

```
Claude: budget_tokens = 16000 (default), aralıq: 1024 — 64000+
OpenAI o1: reasoning_effort = "low" | "medium" | "high"
DeepSeek R1: max_tokens + CoT automatic
```

Model budget dolduqda (və ya özü kafi hiss etdikdə) thinking-i bitirir, cavab yaradır.

### Visible vs Invisible

Provayderlər arasında fərq var:

| Provayder | Thinking görmə | Billing |
|---|---|---|
| Claude | Görünür (`thinking` bloku) | Output rate ilə |
| OpenAI o1/o3 | Summarized (əsl trace gizli) | Output rate ilə (reasoning_tokens) |
| DeepSeek R1 | Tam görünür | Output rate ilə |
| Gemini 2.5 | Summarized | Output rate ilə |

OpenAI-in qapalı yanaşması (tam trace gizlədir) mübahisəlidir: developer debug edə bilmir, nə olduğunu görə bilmir, amma hələ də ödəyir.

### Niyə Vacibdir?

- **Debugging**: Claude-də thinking-i logları göndərmək olar — səhv harada baş verdi?
- **Audit**: Nizami domendə (tibb, maliyyə) modelin niyə bu cavabı verdiyini sübut etmək lazımdır
- **Trust**: istifadəçi reasoning-i görürsə cavaba daha çox güvənir
- **Privacy**: thinking istifadəçi mesajlarını ehtiva edə bilər → maskalamaq lazımdır (PII)

---

## Provider Landscape 2026

Bu dəqiqə mövcud reasoning modellər (dəqiq siyahı dəyişə bilər):

```
Anthropic:
  claude-opus-4-7           (thinking opsional, extended thinking feature)
  claude-sonnet-4-6         (thinking opsional)

OpenAI:
  o1                        (2024 sonunda)
  o1-pro                    (daha çox compute)
  o3                        (2025 əvvəli, frontier)
  o3-mini                   (ucuz, sürətli)
  o4-mini                   (2025 sonu, multimodal)

Google:
  gemini-2.5-pro            (thinking built-in)
  gemini-2.5-flash          (thinking opsional)

DeepSeek:
  deepseek-r1               (2025 əvvəli, open weights)
  deepseek-r1-distill       (Llama/Qwen üzərində)

Qwen:
  qwen-3-max-reasoning      (open weights)

xAI:
  grok-3-reasoning          (thinking mode)
```

---

## Claude Extended Thinking

Ətraflı: 07-extended-thinking.md.

Qısaca:
- Claude 3.7+ Sonnet və Opus modellərdə mövcuddur
- `thinking: { type: "enabled", budget_tokens: N }` parametri ilə aktivləşir
- Thinking response-də ayrı `thinking` bloku kimi qayıdır (visible)
- Redacted thinking blocks mövcuddur (security üçün)
- Interleaved thinking: tool use arasında da düşünmək olar (Claude 4.5+)

Nümunə (API request):

```json
{
  "model": "claude-opus-4-7",
  "max_tokens": 20000,
  "thinking": {
    "type": "enabled",
    "budget_tokens": 10000
  },
  "messages": [
    {"role": "user", "content": "Bu SQL query-ni optimize et: SELECT..."}
  ]
}
```

Cavab:

```json
{
  "content": [
    {
      "type": "thinking",
      "thinking": "Bu query-də JOIN sırası yanlışdır...",
      "signature": "abc123..."
    },
    {
      "type": "text",
      "text": "Aşağıdakı kimi optimize edin:\n\nSELECT ..."
    }
  ]
}
```

Claude-in thinking trace-i strukturlu, insan-oxunaqlı İngiliscədir. Debugging üçün çox dəyərlidir.

---

## OpenAI o-series

### o1 (2024)

İlk kommersial reasoning model. `reasoning_effort` parametri: `low`, `medium`, `high`.

Xüsusiyyətlər:
- `system` mesajı dəstəklənmir (yalnız user message)
- `temperature`, `top_p` parametrləri məhduddur
- Streaming dəstəklənmir (o1-ilk versiyada)
- Thinking trace gizlidir — yalnız "reasoning summary" alırsınız

### o3 (2025)

Frontier səviyyədə reasoning. Codeforces-də human Grandmaster rating qazandı.

Xüsusiyyətlər:
- Streaming dəstəklənir
- Multimodal (image input)
- `tools` dəstəklənir (function calling)
- Reasoning tokenləri input + output rate ilə qarışıq

### o4-mini (2025 sonu)

Multimodal, sürətli, sərfəli. Açıq-qaynaqdan məsləhət: ümumi tapşırıqlar üçün start nöqtəsi.

### Nümunə

```python
# o-series unikaldır: normal completions endpoint istifadə edilir
client.chat.completions.create(
    model="o3",
    reasoning_effort="high",
    messages=[
        {"role": "user", "content": "Böyük O mürəkkəbliyi analiz et..."}
    ],
)
```

---

## DeepSeek R1 — Open Weights Reasoning

2025-in böyük hadisəsi: **DeepSeek** (çin start-up) açıq çəkilərlə (MIT license-ə yaxın) frontier reasoning model buraxdı. Performance o1-ə yaxındır, cost isə 20-30x aşağı.

Xüsusiyyətlər:
- Tam açıq çəkilər — self-host mümkündür
- Thinking trace tam görünür (şeffafdır)
- Distilled versiyalar: Llama 8B, 32B, 70B üzərində
- API kiçik provayderlər (Together, Fireworks) tərəfindən təklif olunur

Praktiki effekt: Chinese AI inkişafı ilə qiymət/performance çox kəskin artdı. OpenAI/Anthropic modellərinə qarşı real alternativdir.

### Lokal Self-hosting

```
deepseek-r1-distill-llama-70b (GGUF quantized):
  RAM: ~40GB
  H100 GPU-da inference: ~30-60 token/s
  Single node $3-5k budget ilə
```

Gizlilik-kritik tətbiqlərdə (HR, maliyyə) self-hosted reasoning model əlverişlidir.

---

## Gemini 2.5 Thinking

Google-un reasoning yanaşması. Xüsusiyyətlər:
- `thinkingConfig: { thinkingBudget: N }` parametri
- Thinking trace summarized (OpenAI stilində)
- Multimodal (image, video, audio, PDF) — kompleks multimodal reasoning
- Long context (1M+ token) — uzun sənəd üzrə reasoning güclüdür
- Flash (ucuz) və Pro (bahalı) variantları

Unikallıq: uzun context + thinking birləşməsi. Məsələn, 500-səhifəlik hüquqi sənəd + specifik hüquqi sual — Gemini 2.5 Pro güclüdür.

---

## Nə Zaman Reasoning Faydalıdır

Reasoning model seçmək mantiqli:

### 1. Multi-step Mühakimə

```
"Bu SQL query çox yavaşdır. Schema-nı analiz edib, 3 fərqli
optimization təklif edin, hərəsinin trade-off-unu göstərin."
```

Klassik model səthi cavab verər; reasoning model hər təklifi ayrı-ayrı fikirləşir.

### 2. Mathematical / Logical Tapşırıqlar

```
"Bu kod snippet-in time complexity-ni analiz edin və 
neden N^2 yerinə N log N olduğunu göstərin."
```

### 3. Planning / Decomposition

```
"Mikroservis migrasiya planı hazırlayın. Mövcud monolit 
PHP Laravel-dən başlayaraq, addım-addım 18 ay ərzində."
```

### 4. Code Review / Refactoring

```
"Bu 500 sətirlik sinfi analiz edin. Bütün kod smells-i 
tapın, prioritetə görə sadalayın, refactor planı təklif edin."
```

### 5. Root-Cause Analysis

```
"Bu stack trace-i + log-ları + metrikaları analiz et.
Incident-in əsl səbəbi nədir?"
```

### 6. Complex Agentic Tasks

Çoxlu tool call + qərar qəbulu — interleaved thinking ilə Claude 4.5+ çox güclüdür.

### 7. Education / Explanation

"Kubernetes necə işləyir" sualına reasoning model daha struktur və dərin cavab verir.

---

## Nə Zaman Reasoning Zərərlidir

### 1. Sadə Transformasiya

```
"Bu JSON-u CSV-ə çevir"
```

Klassik model dərhal edər; reasoning model 5 saniyə düşünər, eyni nəticə.

### 2. Retrieval / RAG Cavabları

```
[Sənəd + "Bu sənəddə X nə deyir?"]
```

Sənəddə cavab bəllidirsə, düşüncə faydasızdır.

### 3. Latency-Critical UX

Chat UI-da 30+ saniyə gözləmə qəbuledilməzdir. Reasoning streaming-də belə **ilk user-visible token-a qədər** 5-10 saniyə gecikdirə bilər.

### 4. High-Volume Batch Jobs

Günlük 100k log entry-si klassifikasiya etmək. Thinking hər birinə $0.01 əlavə edərsə, $1000/gün ekstra.

### 5. Creative Writing

"Şeir yaz" — reasoning zərər vermir, amma faydası yoxdur. Baha və yavaş eynilə.

### 6. Ardıcıl Format Tapşırıqları

"Bu şablonu doldur" — structured output + klassik model kafi.

### 7. Spam Classification / Moderation

Binary təsnifat (spam / not spam) üçün reasoning ağır silahdır. Klassik Haiku / Flash istifadə et.

---

## Prompting Fərqləri

Reasoning model-ləri klassik prompt etiketləri ilə prompt etmək **əksinə effekt** verə bilər.

### 1. "Step by step düşün" Artıqdır

Model onsuz da bunu edir. Əlavə instruction onu yorur, budget israf edir.

```
YANLIŞ (reasoning model-də):
  "Step-by-step düşün və cavab ver"

DÜZGÜN:
  "Bu problemi analiz et və cavab ver"
```

### 2. Few-shot-un Faydası Azalır

Classic modellərdə few-shot examples böyük fərq yaradır. Reasoning modellərdə — model özü similar nümunələri thinking-də yaradır. Few-shot yalnız **format** üçün lazım ola bilər.

### 3. "Sən Expert X-sən" Roleplay Zəifdir

Reasoning modellər rol-playing prompt-larına daha az bağlıdır — onlar zatən sistematik düşünür. Rol deyil, **concrete instruction** ver.

### 4. Output Formatını Aç Göstər

```
DÜZGÜN:
"Cavabı aşağıdakı JSON formatında ver:
{\"summary\": str, \"risks\": [str], \"score\": int}"
```

### 5. Thinking Budget-i Tapşırığa Uyğunlaşdır

- Sadə sual: budget_tokens = 2000
- Orta mürəkkəblik: 8000
- Çətin analiz: 16000-32000
- Olimpik problem: 64000+

Həddindən artıq budget → pul israfı. Həddindən aşağı → model kəsir, cavab yarımçıq.

### 6. System Prompt Qısa Saxla

Klassik modellərdə uzun, strukturlu system prompt yaxşı idi. Reasoning modellərdə — thinking budget-inə təsir edir, cost artır. Qısa saxla.

---

## Pricing və Cost Implication

Thinking tokenlər output qiymət rate-i ilə hesablanır. Bu, cost profile-ini dəyişdirir.

### Tipik Cost Müqayisəsi (2026 təxmini)

```
Klassik Claude Sonnet 4.6:
  Input:  $3 / M token
  Output: $15 / M token
  1000 tok input + 500 tok output = $0.003 + $0.0075 = $0.0105

Reasoning Claude Sonnet 4.6 (extended thinking):
  Input:  $3 / M token
  Output: $15 / M token (thinking + cavab)
  1000 tok input + 8000 tok thinking + 500 tok cavab = $0.003 + $0.1275 = $0.1305

Nisbət: ~12x daha bahalı
```

### Monthly Impact

```
100k request / ay tətbiq üçün:
  Classic:   100k × $0.01 = $1,000/ay
  Reasoning: 100k × $0.13 = $13,000/ay

Fərq: $12k/ay. Reasoning-in qaytarışı olmadıqda — təhlükəli seçim.
```

### Hybrid Strategy

Real production-da **routing**-dan istifadə et:

```
İstifadəçi soruşur:
  ├── Sadə RAG soruş → Haiku (ucuz)
  ├── Orta mürəkkəblik → Sonnet klassik
  ├── Kompleks planning → Sonnet extended thinking
  └── Olimpik problem → Opus extended thinking
```

Bu router-i bir classifier + heuristic ilə qur. Cost 10x azalır.

### Prompt Caching + Reasoning

Prompt caching (09-prompt-caching.md) reasoning modellərdə tam işləyir. System prompt və sabit context-i cache et — yalnız thinking + cavab yeni cost-dur.

---

## Streaming və Latency Reality

### Ilk Token-a qədər Vaxt (TTFT)

Klassik model: 200-500ms
Reasoning model: **thinking müddəti + 200-500ms**

Thinking müddəti:
- Kiçik budget (2000 token): 2-5 saniyə
- Orta (10000 token): 15-30 saniyə
- Böyük (50000+): 1-3 dəqiqə

### Streaming Davranışı

Provayderlər arasında fərqlidir:

**Claude Extended Thinking (streaming):**
```
event: content_block_start (type=thinking)
event: content_block_delta (thinking: "Bu problemi analiz edim...")
event: content_block_delta (thinking: " Birinci: ...")
...
event: content_block_stop
event: content_block_start (type=text)
event: content_block_delta (text: "Cavab: ...")
```

Thinking delta-ları real vaxtda gəlir. UI-da "düşünür..." indikatoru göstər, thinking-i opsional aç.

**OpenAI o-series:**
- o1-də ilk vaxt streaming yoxdur
- o3-də streaming var, amma thinking birbaşa görünmür — yalnız reasoning summary

**DeepSeek R1:**
- Thinking streaming olaraq gəlir (`<think>...</think>` blokları içində)

### UX Pattern-lər

```
1. "Düşünür..." spinner göstər (0-10s)
2. "Analiz aparılır..." mətn (10-30s)
3. Thinking summary göstər (opsional, 15s+)
4. Final cavab stream et
5. Əgər 60s+ keçibsə — "Uzun cavab hazırlanır..." bildirişi
```

Mühüm: timeout-unu yüksəkləş (2-5 dəqiqə). HTTP client timeout-u default 30s reasoning request-i kəsir.

---

## Laravel Kod Nümunələri

### Basic Reasoning Request

```php
<?php

use Anthropic\Anthropic;

$claude = Anthropic::factory()
    ->withApiKey(config('services.anthropic.key'))
    ->make();

$response = $claude->messages()->create([
    'model' => 'claude-opus-4-7',
    'max_tokens' => 16000,
    'thinking' => [
        'type' => 'enabled',
        'budget_tokens' => 10000,
    ],
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Bu mikroservislərin arxitekturasında hansı problemlər var? [...]'
        ],
    ],
]);

// Thinking və cavabı ayır
foreach ($response->content as $block) {
    if ($block->type === 'thinking') {
        Log::channel('reasoning')->debug('Thinking', [
            'content' => $block->thinking,
            'signature' => $block->signature,
        ]);
    } elseif ($block->type === 'text') {
        echo $block->text;
    }
}
```

### Adaptive Reasoning Router

```php
<?php

namespace App\Services\LLM;

class ReasoningRouter
{
    public function route(string $userMessage): array
    {
        $complexity = $this->estimateComplexity($userMessage);

        return match (true) {
            $complexity < 0.3 => [
                'model' => 'claude-haiku-4-5',
                'thinking' => null, // no thinking
                'max_tokens' => 1024,
            ],
            $complexity < 0.6 => [
                'model' => 'claude-sonnet-4-6',
                'thinking' => null,
                'max_tokens' => 2048,
            ],
            $complexity < 0.85 => [
                'model' => 'claude-sonnet-4-6',
                'thinking' => [
                    'type' => 'enabled',
                    'budget_tokens' => 6000,
                ],
                'max_tokens' => 8192,
            ],
            default => [
                'model' => 'claude-opus-4-7',
                'thinking' => [
                    'type' => 'enabled',
                    'budget_tokens' => 20000,
                ],
                'max_tokens' => 24000,
            ],
        };
    }

    private function estimateComplexity(string $message): float
    {
        $signals = 0;
        $total = 0;

        // Length signal
        $total++;
        if (str_word_count($message) > 200) $signals++;

        // Reasoning keywords
        $total++;
        $reasoningWords = ['analyze', 'compare', 'tradeoff', 'optimize',
                           'design', 'architect', 'plan', 'analiz',
                           'müqayisə', 'optimize', 'dizayn', 'plan'];
        foreach ($reasoningWords as $word) {
            if (stripos($message, $word) !== false) {
                $signals++;
                break;
            }
        }

        // Code blocks
        $total++;
        if (substr_count($message, '```') >= 2) $signals++;

        // Math/numbers
        $total++;
        if (preg_match('/\d+\s*[\+\-\*\/×÷]\s*\d+/', $message)) $signals++;

        // Question complexity
        $total++;
        if (substr_count($message, '?') > 1) $signals++;

        return $total > 0 ? $signals / $total : 0.5;
    }
}
```

### Streaming with Thinking UI

```php
<?php

namespace App\Http\Controllers;

use Anthropic\Anthropic;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReasoningStreamController
{
    public function __invoke(Request $request, Anthropic $claude)
    {
        return new StreamedResponse(function () use ($claude, $request) {
            $stream = $claude->messages()->createStreamed([
                'model' => 'claude-opus-4-7',
                'max_tokens' => 16000,
                'thinking' => [
                    'type' => 'enabled',
                    'budget_tokens' => 10000,
                ],
                'messages' => [
                    ['role' => 'user', 'content' => $request->input('prompt')],
                ],
            ]);

            foreach ($stream as $event) {
                $type = $event->type;

                if ($type === 'content_block_start') {
                    $blockType = $event->contentBlock->type;
                    echo "event: block_start\n";
                    echo "data: " . json_encode(['type' => $blockType]) . "\n\n";
                } elseif ($type === 'content_block_delta') {
                    $delta = $event->delta;
                    if ($delta->type === 'thinking_delta') {
                        echo "event: thinking\n";
                        echo "data: " . json_encode(['text' => $delta->thinking]) . "\n\n";
                    } elseif ($delta->type === 'text_delta') {
                        echo "event: answer\n";
                        echo "data: " . json_encode(['text' => $delta->text]) . "\n\n";
                    }
                }

                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            echo "event: done\n";
            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

### Cost Tracking

```php
<?php

namespace App\Services\LLM;

use App\Models\LLMUsage;

class ReasoningCostTracker
{
    public function record(
        string $userId,
        string $model,
        int $inputTokens,
        int $thinkingTokens,
        int $outputTokens,
    ): void {
        $cost = $this->calculateCost($model, $inputTokens, $thinkingTokens, $outputTokens);

        LLMUsage::create([
            'user_id' => $userId,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'thinking_tokens' => $thinkingTokens,
            'output_tokens' => $outputTokens,
            'total_cost_usd' => $cost,
        ]);
    }

    private function calculateCost(
        string $model,
        int $input,
        int $thinking,
        int $output,
    ): float {
        $rates = [
            'claude-opus-4-7'    => ['in' => 15, 'out' => 75],   // per 1M
            'claude-sonnet-4-6'  => ['in' => 3,  'out' => 15],
            'claude-haiku-4-5'   => ['in' => 0.8, 'out' => 4],
        ];

        $r = $rates[$model] ?? $rates['claude-sonnet-4-6'];
        return ($input * $r['in'] + ($thinking + $output) * $r['out']) / 1_000_000;
    }
}
```

---

## Evaluation — Necə Ölçmək

Reasoning modelin sənin tapşırıqda həqiqətən üstün olduğunu **ölç**, "trend-də olduğu üçün" istifadə etmə.

### Evaluation Dataset Qur

```
100-500 real istifadəçi sualı topla:
  - 30% sadə (retrieval, classification)
  - 40% orta (analysis, summarization)
  - 30% mürəkkəb (planning, debugging, multi-step)

Hər birinə ideal cavab (gold standard) əlavə et.
```

### Comparative Run

```php
// Eyni prompt-u hər modelə göndər
foreach ($evalSet as $case) {
    foreach (['claude-sonnet-classic', 'claude-sonnet-thinking',
              'claude-opus-thinking', 'o3', 'gemini-2.5-pro'] as $model) {
        $result = callModel($model, $case['prompt']);
        $score = judgeQuality($result, $case['gold_answer']);

        EvalRun::create([
            'case_id' => $case['id'],
            'model' => $model,
            'score' => $score,
            'latency_ms' => $result->latency,
            'cost_usd' => $result->cost,
        ]);
    }
}
```

### Metrikalar

```
Model               | Quality | P50 latency | Cost/1k req
--------------------|---------|-------------|-------------
Sonnet classic      |  72%    |    1.2s     |   $10
Sonnet thinking     |  85%    |    8.5s     |   $130
Opus thinking       |  92%    |   18.3s     |   $380
o3                  |  90%    |   22.1s     |   $410
gemini-2.5-pro      |  88%    |   12.4s     |   $220
```

Bu cədvələ baxaraq qərar ver: "85% → 92% fərqi" $250 əlavə dəyərmi? Use-case-ə görə.

### Regression Testing

Yeni model versiyası çıxanda **bütün eval set-i yenidən run et**. Score-un düşməsi rollback səbəbidir.

---

## Anti-Pattern-lər

### 1. "Reasoning Model Hər Şey Üçün Daha Yaxşıdır"

Yanılış. 12x bahalı, 10x yavaş, və sadə tapşırıqlarda performance-də fərq yoxdur.

### 2. "Budget Yüksəltsəm, Keyfiyyət Hər Zaman Artır"

Sanki yanlış. Bir yerdən sonra diminishing returns. Budget 50k → 100k arasında çox vaxt fərq yoxdur. Eval ilə optimal-ı tap.

### 3. "Thinking-i UI-da Göstərməyəcəm, Deməli Lazım Deyil"

Thinking UI-da göstərilməsə də, debugging/audit üçün log etmək lazımdır. Production incident-də thinking trace-i həyat xilas edir.

### 4. "Reasoning-ə Keçsəm Prompt-u Dəyişməli Deyiləm"

Yanlış. Prompt-u optimize etmək lazımdır — "step by step düşün" kimi köhnə tricks zərərverən olur.

### 5. "HTTP Timeout-u Default Saxla"

Default 30s reasoning request-i kəsir. 300s+ lazımdır. Plus, Queue Job timeout-unu da yüksəlt.

### 6. "Budget-i Hard-code Et"

Müxtəlif tapşırıqlar müxtəlif budget tələb edir. Classifier ilə dinamik təyin et.

### 7. "Thinking Tokenləri Ödəmirəm" (səhv fikir)

Əksər provayderlərdə thinking tokenlər output rate ilə hesablanır. Cost monitoring-də bunu nəzərə al.

### 8. Classic və Reasoning Arasında Eyni Eval-i İstifadə Etmək

Reasoning-in overhead-i avropa format sadə tapşırıqlarda görünmür. Mürəkkəb tapşırıqlarda test et.

### 9. Reasoning-ə Tool Use Olmadan Keçmək (Agentic-də)

Agentic task-larda **interleaved thinking + tool use** ən güclüdür (Claude 4.5+). Tək thinking, tool-suz — yarımçıq həll.

### 10. "Model Hallucination-u Thinking-də Görünür, Deməli Final Cavab Doğrudur"

Thinking-də düzgün mühakimə görsənə bilər, amma final cavab yenə səhv ola bilər — model thinking-i xülasə edərkən itirə. Thinking ≠ final answer verification.

---

## Qərar Çərçivəsi

### Reasoning vs Classic?

```
Tapşırığın cavabı bilməli olmaq üçün neçə addım/fikir tələb edir?
  1 addım (lookup, format)    → Classic Haiku
  2-3 addım (simple analysis) → Classic Sonnet
  4-6 addım (decomposition)   → Classic Sonnet + CoT prompt
  7+ addım (planning, math)   → Reasoning (Sonnet thinking)
  Frontier-level              → Reasoning (Opus thinking, o3)

Latency tolerance?
  <2s    → Classic məcburi
  <10s   → Classic və ya short-budget reasoning
  <60s   → Reasoning OK
  >60s   → Long-budget reasoning uygun

Cost sensitivity?
  High volume (>100k/day)  → Classic + hybrid routing
  Low volume, high value    → Reasoning hər zaman OK
  Batch processing          → Reasoning + Batch API (50% endirim)

Domain?
  Customer chat            → Classic
  Code review / debugging  → Reasoning
  Math / science           → Reasoning
  Content generation       → Classic
  Agentic (tool chains)    → Reasoning + interleaved
  Simple classification    → Classic Haiku
```

### Hansı Provayder?

| Kriter | Tövsiyə |
|---|---|
| Ən yüksək keyfiyyət (coding) | Claude Opus extended thinking / o3 |
| Açıq trace / debug | Claude, DeepSeek R1 |
| Ən ucuz (açıq) | DeepSeek R1 (self-host və ya API) |
| Multimodal reasoning | Gemini 2.5 Pro / o3 |
| Uzun context + reasoning | Gemini 2.5 Pro |
| Enterprise / güvənli | Claude (Bedrock / Vertex) |
| Sərfəli bulud | Sonnet Thinking |

---

## Xülasə

- Reasoning modellər cavab verməzdən əvvəl daxili mühakimə tokenləri yaradır — bu, RL-lə öyrədilmiş davranışdır
- Test-time compute paradigmi 2024-2025-də sahəni dəyişdi: böyük model + sadə inference → orta model + ağır inference
- Claude extended thinking, OpenAI o-series, DeepSeek R1, Gemini 2.5 Thinking — əsas oyunçular
- Reasoning **hər tapşırıq üçün deyil** — complex mühakimə, math, planning-də üstündür; sadə retrieval/format-da pis seçimdir
- Cost: 10-15x daha baha (thinking tokenləri output rate ilə); latency: 5-60x daha yavaş
- Prompt-ları fərqli yazmaq lazım — "step by step" kimi tricks artıqdır
- Budget parametri kritikdir — tapşırıq əsasında uyğunlaşdır
- Hybrid routing + eval production-da standart yanaşma olmalıdır
- Thinking trace-ləri log et — debugging və audit üçün əvəzsizdir
- "Reasoning yeni və çətindir" deyərək rədd etmə, amma "trend-dir" deyib hər yerdə istifadə etmə

---

## Praktik Tapşırıqlar

### Tapşırıq 1: Standard vs Reasoning Model Müqayisəsi

Multi-step arxitektura dizayn tapşırığı (məs. "Bir e-ticarət platforması üçün event-driven ödəniş sistemi dizayn et") üçün `claude-sonnet-4-6` (standart) vs `claude-sonnet-4-6` extended thinking ilə cavabları müqayisə et. Budget: `max_tokens=8000, thinking.budget_tokens=4000`. Keyfiyyət fərqini və token xərcini qeyd et.

### Tapşırıq 2: Thinking Budget Testi

Eyni mürəkkəb problem üçün `budget_tokens=1000`, `3000`, `8000` ilə test et. Final cavab keyfiyyəti ilə thinking budget arasındakı korrelyasiyanı ölç. ROI tipping point-i tapırsınmı — bir həddən sonra daha çox thinking token keyfiyyət artırmır?

### Tapşırıq 3: Reasoning Model ROI Kalkulyatoru

Layihəndəki 5 AI feature-ı üçün hesabla: reasoning model + standard model xərc fərqi nədir? Hər feature üçün "keyfiyyət qərəzli cavab" faizini ölç. ROI-ni hesabla: `keyfiyyət_artımı × biznes_dəyəri / əlavə_xərc`. Reasoning modeli yalnız ROI-i müsbət olan feature-lara tətbiq et.

---

## Əlaqəli Mövzular

- `01-how-ai-works.md` — Test-time compute-un transformer arxitekturası ilə əlaqəsi
- `02-models-overview.md` — Reasoning modellərin müxtəlif provider-lərdəki icrası
- `10-model-selection-decision.md` — Reasoning model seçim meyarları
- `../02-claude-api/07-extended-thinking.md` — Claude-da extended thinking API implementasiyası
