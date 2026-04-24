# 61 — LLM Deployment Strategies: Canary, Shadow, Blue-Green və A/B Testing

> **Oxucu kütləsi:** Senior developerlər, platform/DevOps engineer-lər, AI product owner-lər
> **Bu faylın 07-workflows/07 və 06-dan fərqi:** 07-workflows/07 — AI A/B testing metodologiyası (experimentation, statistical significance). 06 — ümumi testing strategiyaları. Bu fayl — **deployment rollout**-a fokuslanır: prompt / model version / RAG config / tool schema dəyişikliklərini prod-a necə təhlükəsiz şəkildə çatdırmaq, auto-rollback trigger-ları, config-as-code, correlation ID propagation, regression suite CI-də, shadow mode-un cost tradeoff-u.

---

## Mündəricat

1. [Niyə LLM Deployment Unikaldır](#unique)
2. [Nə Deploy Edirik? 4 Dəyişən Məkan](#what)
3. [Deployment Strategiya Matrisi](#matrix)
4. [Canary Deploy](#canary)
5. [Shadow Mode](#shadow)
6. [Blue-Green Cutover](#blue-green)
7. [A/B Testing Sticky Variants ilə](#ab)
8. [Config-as-Code: Prompts in Git](#config)
9. [Feature Flag Integration](#flags)
10. [Variant Selection Laravel-də](#laravel)
11. [LLM Variant Comparison Metodları](#compare)
12. [CI Regression Suite (Golden Prompts)](#golden)
13. [Correlation ID və Variant Tag Propagation](#correlation)
14. [Auto-Rollback Triggers](#rollback)
15. [Incident Runbook: "New Variant Broke Prod"](#runbook)
16. [Cost Tradeoff: Shadow Mode](#cost)
17. [Anti-Pattern-lər](#anti)

---

## 1. Niyə LLM Deployment Unikaldır <a name="unique"></a>

Klassik web servis deployment-də siz **kod** deploy edirsiniz. Binary dəyişir, amma davranış deterministik olaraq təyin edilib. Unit test passirsə, integration passirsə, 99% hadisədə prod-da da işləyir.

LLM deployment-də siz **stochastic sistemi** dəyişirsiniz:

- Prompt-u dəyişdirdiniz → 47 test case-dən 45-i yaxşılaşdı, 2-si pisləşdi
- Model versiyasını yenilədilər → JSON schema compliance 99%-dən 94%-ə düşdü (silent)
- RAG corpus-a yeni documents əlavə etdiniz → retrieval precision 0.82-dən 0.71-ə endi
- Yeni tool əlavə etdiniz → model əvvəlki tool-u da yanlış seçməyə başladı (confusion)

Hər dəyişiklik **probabilistic impact** daşıyır. Unit test "passed/failed" binary deyil — distribution shift-dir.

### Klassik Deploy vs LLM Deploy

| Aspekt | Klassik Web | LLM Application |
|--------|-------------|-----------------|
| Behavior determinism | Tam | Stochastic |
| Regression detection | Unit/E2E test | Eval suite + user feedback |
| Rollback time | Dəqiqələr | Yalnız kod deyil, prompt/config/RAG da |
| Blast radius | Clear | Semantic (hər user-i fərqli təsir edir) |
| Metric for "works" | Status 200, latency | Quality score, groundedness, user satisfaction |
| Deploy frequency | Daily-hourly | Hər prompt tweak potensial impact |

### Deployment Risk Katgoriyaları

```
┌──────────────────────────────────────────────────────────┐
│               LLM Deploy Risk Matrix                     │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  LOW RISK                                                │
│    - Typo fix in system prompt                           │
│    - Adding comment to tool schema                       │
│                                                          │
│  MEDIUM RISK                                             │
│    - Adding new instruction to system prompt             │
│    - Adding new optional tool                            │
│    - Adding documents to RAG corpus                      │
│                                                          │
│  HIGH RISK                                               │
│    - Model version change (sonnet-4-5 → sonnet-4-6)      │
│    - Removing system prompt instruction                  │
│    - Changing tool signature (breaking)                  │
│    - Major prompt restructure                            │
│                                                          │
│  CRITICAL                                                │
│    - Provider swap (Anthropic → OpenAI)                  │
│    - Changing core policy (refuse/allow boundaries)      │
│    - Multi-component simultaneous change                 │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

Risk səviyyəsi deployment strategiyası seçimini diktə edir.

---

## 2. Nə Deploy Edirik? 4 Dəyişən Məkan <a name="what"></a>

Senior engineer-in ilk suallarından: "bu dəyişikliyi deploy etmək nə deməkdir?"

```
┌────────────────────────────────────────────────────────────┐
│          LLM Application Deploy Axes                       │
├────────────────────────────────────────────────────────────┤
│                                                            │
│   Axis 1: CODE                                             │
│     - Laravel app, middleware, job, tool implementations   │
│                                                            │
│   Axis 2: PROMPTS                                          │
│     - System prompt                                        │
│     - Few-shot examples                                    │
│     - Output format instructions                           │
│     - Persona definitions                                  │
│                                                            │
│   Axis 3: MODEL CONFIG                                     │
│     - Provider (Anthropic/OpenAI/Google)                   │
│     - Model version (snapshot vs alias)                    │
│     - temperature, top_p, max_tokens                       │
│     - Tools enabled                                        │
│                                                            │
│   Axis 4: DATA                                             │
│     - RAG corpus (new docs, removed docs)                  │
│     - Embedding model                                      │
│     - Chunking strategy                                    │
│     - Retrieval top-k, threshold                           │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

**Hər ox ayrı deploy edilməlidir**. Simultaneous 4-axes change → bir şey qırıldıqda hansı dəyişikliyin səbəb olduğunu bilmirsiniz.

### Sample Release Cadence

- **Code**: hər commit (dəqiqə-saat)
- **Prompts**: həftədə 1-2 dəfə, canary
- **Model**: ayda 1 dəfə, shadow + canary
- **Data**: həftədə dəfələrlə, ingest pipeline-da validation
- **Tool schema**: ayda 1-2 dəfə, breaking change-lər shadow

---

## 3. Deployment Strategiya Matrisi <a name="matrix"></a>

| Strategy | Traffic split | Rollback time | Compare online | Risk | Cost |
|----------|--------------|---------------|----------------|------|------|
| Big bang | 0% → 100% | Dəqiqələr | No | Yüksək | Base |
| Blue-green | 100% → 100% (instant cutover) | Saniyələr | No | Orta | 2x infra |
| Canary | 1% → 10% → 50% → 100% | Dəqiqələr | Partial | Aşağı | Base |
| Shadow | 0% user-visible, 100% dual-send | Dərhal (feature flag off) | Full offline | Çox aşağı | 2x LLM cost |
| A/B (sticky) | 50/50 sticky user | Feature flag off | Full business metrics | Aşağı | 1x + eval cost |
| Rings | Internal → beta → GA | Günlər | Partial | Çox aşağı | Base |

### Decision Tree

```
Dəyişiklik breaking (tool schema change)?
├── Yes → Shadow + gradual canary
└── No
    ├── Quality sensitive (user-facing generation)?
    │   ├── Yes → Canary 1%→10%→50%→100% + rollback triggers
    │   └── No (internal tool, admin feature)?
    │       └── Blue-green or big bang
    └── Multi-axis change (prompt + model + RAG)?
        └── STOP. Split into separate deploys.
```

---

## 4. Canary Deploy <a name="canary"></a>

Canary — ən geniş istifadə olunan LLM deploy strategy. Kiçik faiz user-ə yeni variant, qalanına köhnə. Metrikaları müqayisə et.

### Tipik Progression

```
Hour 0:  1%   → monitor 2h
Hour 2:  5%   → monitor 4h
Hour 6:  10%  → monitor 8h
Hour 14: 25%  → monitor 12h
Hour 26: 50%  → monitor 24h
Day 2:   100%
```

Total cycle: 2-3 gün high-risk dəyişikliklər üçün. Low-risk üçün 1-2 saatda sürüklə.

### Canary Seçim Meyarları

**Random** (ən geniş):
- Hər request üçün random 1% seçilir
- Üstünlük: sadə
- Çatışmazlıq: eyni user bəzi request-lərdə yeni, bəzilərində köhnə görür (user confusion)

**User-sticky**:
- `hash(user_id) mod 100 < 1` → variant A
- Üstünlük: consistent user experience
- Çatışmazlıq: 1% user "qurban" olur, əgər yeni variant pisdirsə onlar tam pis təcrübə alır

**Tenant-sticky (B2B)**:
- Tenant səviyyəsində split
- Üstünlük: contract-əsasən təhlükəsiz tenant-lara canary olunur
- Çatışmazlıq: daha az sample size

### Canary üçün Guardrail Metrics

Hər canary faizindən növbəti faizə keçmək üçün yoxlanılır:

- **Error rate**: canary vs control, delta <0.5% olmalıdır
- **p95 latency**: delta <200ms
- **Token usage**: delta <+15% (aksident olaraq daha uzun çıxışlar)
- **Cost per request**: delta <+20%
- **Quality score** (eval sample): delta >-2%
- **User satisfaction** (if thumbs up/down available): delta >-3%
- **Safety violations** (moderation triggered): delta should be zero or negative

Hər hansı threshold aşılırsa — **auto-rollback**, incident yaradılır.

### Canary vs Staging Fərqi

Staging sizin öz synthetic data-nız. Canary real user distribution-udur. Staging-də keçən test prod-da düşə bilər (edge case-lər, unusual language, attachment types). Canary — real-distribution validation.

---

## 5. Shadow Mode <a name="shadow"></a>

Shadow — **user-visible olmayan** paralel execution. Hər request eyni vaxtda həm köhnə həm yeni variant-a göndərilir. User yalnız köhnə variant-dan cavab alır. Yeni variant-ın cavabı log-lanır, müqayisə üçün.

### Architecture

```
       User Request
             │
             ▼
    ┌────────────────┐
    │   Dispatcher   │
    └────┬───────┬───┘
         │       │
         ▼       ▼
    ┌────────┐  ┌────────┐
    │Control │  │Shadow  │
    │(old)   │  │(new)   │
    └───┬────┘  └────┬───┘
        │           │
        ▼           ▼
    User ←     Logged only
```

### Nə Vaxt Shadow İstifadə Edilir

- **Provider change** (Anthropic → OpenAI) — semantic equivalence-i yoxla
- **Major prompt restructure** — 100+ golden prompt-a qarşı A/B compare
- **Model version upgrade** (major) — mevcut prompt-lar yeni model-də necədir?
- **Tool schema breaking change** — eski + yeni eyni request-ə necə reaksiya verir?

### Shadow Implementation Laravel-də

```php
// app/Jobs/ShadowLlmRequest.php

namespace App\Jobs;

use App\Models\ShadowComparison;
use App\Services\AI\AiGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ShadowLlmRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $messages,
        public array $controlOptions,
        public array $shadowOptions,
        public string $correlationId,
        public string $controlResponseText,
    ) {}

    public function handle(AiGateway $gateway, ShadowComparator $comparator): void
    {
        try {
            $shadowResponse = $gateway->chat($this->messages, $this->shadowOptions);

            $comparison = $comparator->compare(
                controlText: $this->controlResponseText,
                shadowText: $shadowResponse->text,
                controlOptions: $this->controlOptions,
                shadowOptions: $this->shadowOptions,
            );

            ShadowComparison::create([
                'correlation_id' => $this->correlationId,
                'control_variant' => $this->controlOptions['variant_tag'],
                'shadow_variant' => $this->shadowOptions['variant_tag'],
                'control_text_hash' => sha1($this->controlResponseText),
                'shadow_text_hash' => sha1($shadowResponse->text),
                'semantic_similarity' => $comparison->semanticSimilarity,
                'length_delta' => $comparison->lengthDelta,
                'tool_call_match' => $comparison->toolCallMatch,
                'judge_preference' => $comparison->judgePreference, // "control", "shadow", "tie"
                'control_cost_usd' => $this->controlOptions['cost_estimated'] ?? 0,
                'shadow_cost_usd' => $shadowResponse->usage['cost_usd'] ?? 0,
                'shadow_latency_ms' => $shadowResponse->latencyMs,
            ]);
        } catch (\Throwable $e) {
            // Shadow failure user-i təsir etməməli
            Log::warning('Shadow request failed', [
                'correlation_id' => $this->correlationId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
```

Controller-də:

```php
public function chat(Request $request, AiGateway $gateway)
{
    $correlationId = (string) Str::uuid();
    $messages = $request->input('messages');

    $controlOptions = [
        'model' => config('ai.variants.control.model'),
        'system' => config('ai.variants.control.system_prompt'),
        'variant_tag' => 'control_v1.4.2',
    ];
    $response = $gateway->chat($messages, $controlOptions);

    // Async shadow dispatch (no user-visible latency)
    if (config('ai.shadow.enabled')) {
        ShadowLlmRequest::dispatch(
            messages: $messages,
            controlOptions: $controlOptions,
            shadowOptions: [
                'model' => config('ai.variants.shadow.model'),
                'system' => config('ai.variants.shadow.system_prompt'),
                'variant_tag' => 'shadow_v1.5.0-rc1',
            ],
            correlationId: $correlationId,
            controlResponseText: $response->text,
        );
    }

    return response()->json([
        'text' => $response->text,
        'correlation_id' => $correlationId,
    ]);
}
```

### Comparator

```php
// app/Services/AI/Deploy/ShadowComparator.php

class ShadowComparator
{
    public function __construct(
        private EmbeddingService $embeddings,
        private LlmJudge $judge,
    ) {}

    public function compare(
        string $controlText,
        string $shadowText,
        array $controlOptions,
        array $shadowOptions,
    ): ComparisonResult {
        $controlEmb = $this->embeddings->embed($controlText);
        $shadowEmb = $this->embeddings->embed($shadowText);
        $similarity = $this->cosineSimilarity($controlEmb, $shadowEmb);

        $lengthDelta = strlen($shadowText) - strlen($controlText);

        $judgment = $this->judge->compare(
            prompt: $controlOptions['original_prompt'] ?? '',
            responseA: $controlText,
            responseB: $shadowText,
        );

        return new ComparisonResult(
            semanticSimilarity: $similarity,
            lengthDelta: $lengthDelta,
            toolCallMatch: true, // placeholder
            judgePreference: $judgment->winner,
        );
    }
}
```

### Shadow Mode Müddəti

Shadow neçə müddət qaçsın?
- Minimal: 1 həftə, 10,000+ real request
- Tipik: 2-4 həftə
- Business-critical: 6-8 həftə

Statistik significance + edge case coverage-ə əsasən qərar verin.

---

## 6. Blue-Green Cutover <a name="blue-green"></a>

İki identik environment: "blue" (köhnə, prod) və "green" (yeni). Bütün trafiki blue-dan green-ə anında dəyişdirirsiniz. Problem olarsa, anında geri.

LLM kontekstində blue-green **infrastruktur deyil, konfiqurasiya** səviyyəsindədir. İki config file:

```php
// config/ai.php

return [
    'active_variant' => env('AI_ACTIVE_VARIANT', 'blue'),

    'variants' => [
        'blue' => [
            'model' => 'claude-sonnet-4-5',
            'system_prompt_path' => 'prompts/v1.4.2.md',
            'rag_config' => [
                'corpus_version' => 'corpus_2026_03_15',
                'top_k' => 5,
            ],
        ],
        'green' => [
            'model' => 'claude-sonnet-4-6',
            'system_prompt_path' => 'prompts/v1.5.0.md',
            'rag_config' => [
                'corpus_version' => 'corpus_2026_04_10',
                'top_k' => 7,
            ],
        ],
    ],
];
```

Rollback: `AI_ACTIVE_VARIANT=blue` deploy. Saniyələrdə effekt.

### Nə Vaxt Blue-Green

- Low-traffic admin-facing LLM (canary statistical sample-ı kifayət deyil)
- Qısa cycle-lı changes (typo fix)
- Non-user-facing (internal agent, batch job)

User-facing yüksək-trafik üçün canary üstündür.

---

## 7. A/B Testing Sticky Variants ilə <a name="ab"></a>

File 37 A/B testing metodologiyasını əhatə edir. Burada **deployment kontekstində** diqqət mərkəzinə:

### Deployment vs Experimentation

- **Deployment A/B**: "yeni variant artıq hazırdır, təhlükəsiz roll out edək" → canary + rollback
- **Experimentation A/B**: "hansı variant biznes metrikasında daha yaxşıdır öyrənək" → 50/50 uzun müddət

Deployment A/B tez bitir (bir neçə gün), experimentation A/B uzun çəkir (həftələr-aylar).

### Sticky User Assignment

```php
// app/Services/AI/Deploy/VariantSelector.php

class VariantSelector
{
    public function selectVariant(int $userId, string $experimentKey): string
    {
        $hash = crc32("$experimentKey:$userId");
        $bucket = $hash % 100;

        $experiment = config("ai.experiments.$experimentKey");
        $cumulative = 0;
        foreach ($experiment['variants'] as $variant => $weight) {
            $cumulative += $weight;
            if ($bucket < $cumulative) {
                return $variant;
            }
        }
        return array_key_first($experiment['variants']);
    }
}
```

Config:

```php
'experiments' => [
    'sales_bot_prompt_v2' => [
        'start' => '2026-04-15',
        'end' => '2026-05-15',
        'variants' => [
            'control' => 50,
            'treatment_v2' => 50,
        ],
        'metrics' => ['conversion_rate', 'session_length', 'csat'],
    ],
],
```

### Primary vs Guardrail Metrics

- **Primary**: conversion rate, NPS, task completion
- **Guardrail**: error rate, latency, cost, safety violations
- **Anti-metric**: escalation to human agent

Treatment primary-də +3% qazanıb, amma cost +40% artıbsa — trade-off analiz-i lazımdır.

---

## 8. Config-as-Code: Prompts in Git <a name="config"></a>

Prompt production code-un bir hissəsidir. Git-də saxlanılmalıdır, SHA versioned olmalıdır, code review-dan keçməlidir.

### Struktur

```
app/
├── prompts/
│   ├── support_bot/
│   │   ├── v1.0.0.md
│   │   ├── v1.0.1.md
│   │   ├── v1.1.0.md      <- current
│   │   └── v1.2.0-rc1.md   <- canary
│   ├── sales_bot/
│   │   └── ...
│   └── code_reviewer/
│       └── ...
```

### Prompt Metadata Header

Hər prompt fayl YAML frontmatter:

```markdown
---
name: support_bot
version: 1.1.0
sha: auto-computed
released: 2026-04-20
model: claude-sonnet-4-5
temperature: 0.3
max_tokens: 2000
tools:
  - search_knowledge_base
  - escalate_to_human
changelog: |
  - Added policy on refund escalation
  - Removed outdated business hours info
reviewers:
  - alice@company.com
  - bob@company.com
---

# Support Bot System Prompt

You are a customer support assistant for Acme Corp...
```

### Loader

```php
// app/Services/AI/Prompts/PromptLoader.php

class PromptLoader
{
    public function load(string $name, string $version): Prompt
    {
        $path = resource_path("prompts/$name/v$version.md");
        $content = file_get_contents($path);

        [$frontmatter, $body] = $this->parseFrontmatter($content);
        $sha = substr(sha1($body), 0, 8);

        return new Prompt(
            name: $frontmatter['name'],
            version: $frontmatter['version'],
            sha: $sha,
            body: trim($body),
            metadata: $frontmatter,
        );
    }
}
```

### CI Validation

Hər PR-də:

```yaml
# .github/workflows/prompt-validation.yml
- name: Validate prompts
  run: |
    php artisan prompts:validate
    php artisan prompts:test-golden-suite --variant=pr_candidate
    php artisan prompts:compute-diff --against=main
```

`prompts:validate` yoxlayır:
- Frontmatter parse olunur
- Version bumped (prev-dən böyük)
- Reviewers mövcuddur
- Changelog var
- Tool-lar app-da mövcuddur

---

## 9. Feature Flag Integration <a name="flags"></a>

Feature flag sistemi — GrowthBook, LaunchDarkly, Unleash, Laravel Pennant.

### Laravel Pennant ilə Setup

```php
// app/Providers/AppServiceProvider.php

use Laravel\Pennant\Feature;

public function boot()
{
    Feature::define('prompt_v1.5.0', function (User $user) {
        // 10% canary
        return Lottery::odds(10, 100);
    });

    Feature::define('model_sonnet_4_6', function (User $user) {
        // Beta tester-lər + 5% random
        if ($user->hasRole('beta_tester')) return true;
        return Lottery::odds(5, 100);
    });

    Feature::define('shadow_provider_openai', function (User $user) {
        // Internal only
        return str_ends_with($user->email, '@company.com');
    });
}
```

Request handler-də:

```php
public function chat(Request $request)
{
    $user = $request->user();

    $promptVersion = Feature::for($user)->active('prompt_v1.5.0')
        ? '1.5.0'
        : '1.4.2';

    $model = Feature::for($user)->active('model_sonnet_4_6')
        ? 'claude-sonnet-4-6'
        : 'claude-sonnet-4-5';

    $prompt = app(PromptLoader::class)->load('support_bot', $promptVersion);

    $response = $this->gateway->chat($request->input('messages'), [
        'system' => $prompt->body,
        'model' => $model,
        'variant_tag' => "prompt={$prompt->version}|model={$model}",
    ]);

    return response()->json($response->toArray());
}
```

### Flag Lifecycle

- **Dev**: always off in prod
- **Canary**: 1% → 100% over days
- **General Availability**: 100%, flag kept as kill switch
- **Cleanup**: 30 gün 100% stabil isə flag code-dan sil

Flag debt risk — 50+ köhnə flag toplanırsa, system çətin başa düşülür. Quarterly flag cleanup.

---

## 10. Variant Selection Laravel-də <a name="laravel"></a>

Variant selection middleware-dən dispatcher-ə qədər:

```php
// app/Http/Middleware/AssignLlmVariant.php

class AssignLlmVariant
{
    public function __construct(
        private VariantSelector $selector,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $experiments = config('ai.active_experiments', []);
        $assignments = [];

        foreach ($experiments as $key => $_) {
            $assignments[$key] = $this->selector->selectVariant($user->id, $key);
        }

        $request->attributes->set('llm_variants', $assignments);

        // Expose to logs
        Log::withContext(['llm_variants' => $assignments]);

        return $next($request);
    }
}
```

Sonra service layer:

```php
// app/Services/AI/SupportBot.php

public function answer(User $user, string $message): string
{
    $variants = request()->attributes->get('llm_variants', []);
    $promptVariant = $variants['support_bot_prompt'] ?? 'control';
    $modelVariant = $variants['support_bot_model'] ?? 'control';

    $prompt = $this->promptLoader->load(
        'support_bot',
        config("ai.variants.support_bot_prompt.$promptVariant.version"),
    );
    $model = config("ai.variants.support_bot_model.$modelVariant.model");

    $response = $this->gateway->chat(
        messages: [['role' => 'user', 'content' => $message]],
        options: [
            'system' => $prompt->body,
            'model' => $model,
            'variant_tag' => "prompt=$promptVariant|model=$modelVariant",
            'correlation_id' => (string) Str::uuid(),
        ],
    );

    $this->recordMetrics($user, $variants, $response);

    return $response->text;
}
```

---

## 11. LLM Variant Comparison Metodları <a name="compare"></a>

Deployment üçün "hansı variant daha yaxşıdır?" sualına cavab:

### 11.1 Deterministic Eval Set (Golden Prompts)

500-2000 prompt + expected properties. Hər variant bunları keçir.

```php
// Eval schema
[
    'id' => 'golden_42',
    'input' => 'Siparişim nə vaxt gələcək?',
    'expected_properties' => [
        'mentions_order_id' => true,
        'mentions_tracking' => true,
        'length_max_words' => 100,
        'tone' => 'polite',
        'no_pii' => true,
    ],
    'forbidden_patterns' => [
        '/password/i',
        '/credit card/i',
    ],
]
```

Hər variant üçün passing percentage müqayisə edilir.

### 11.2 Semantic Similarity

Shadow mode-da paralel cavabları embedding-ə çevir, cosine similarity ölç. 0.85+ — mənaca uyğun, amma nüans fərqi. 0.95+ — demək olar eyni. <0.7 — fərqli cavab, daha dərin araşdırma lazımdır.

### 11.3 LLM-as-Judge

Üçüncü LLM (güclü, Opus 4.7) iki cavabı müqayisə edir:

```
System: You are an impartial judge. Compare two responses to the user query.
Select A, B, or TIE based on: helpfulness, accuracy, safety, tone.

User query: {prompt}
Response A: {control}
Response B: {treatment}

Output JSON: {"winner": "A|B|TIE", "reasoning": "..."}
```

Bias-a diqqət: A/B pozisiyasını dəyişdirib iki dəfə qaçır (position bias).

### 11.4 User Feedback (Thumbs Up/Down)

Prod-da user-dən feedback topla. Variant-lar arasında thumbs up rate fərqi.

Caveat: user feedback seyrəkdir (<5% cavab verir), bias var (narazı user-lər daha çox feedback verir).

### 11.5 Business Metrics

Final həqiqət: variant biznesə necə təsir edir?

- Support: ticket deflection rate, time-to-resolution, escalation rate
- Sales: conversion rate, average order value
- Content: engagement time, share rate

Business metrics ən etibarlı, ən gec siqnal.

### Compound Decision Rule

```
IF golden_suite_pass_rate delta < -2% → ROLLBACK
ELSE IF safety_violations delta > 0 → ROLLBACK
ELSE IF business_metric_primary delta < -1% (p<0.05) → ROLLBACK
ELSE IF cost_per_request delta > +25% AND no business win → ROLLBACK
ELSE IF golden AND business neutral or positive → PROMOTE
```

---

## 12. CI Regression Suite (Golden Prompts) <a name="golden"></a>

Hər PR-də prompt/config dəyişikliyi golden suite-ə qarşı yoxlanılır.

### Golden Suite Struktur

```php
// database/seeders/GoldenPromptsSeeder.php

class GoldenPromptsSeeder extends Seeder
{
    public function run()
    {
        $prompts = [
            [
                'category' => 'basic_qa',
                'input' => 'Siparişim nə vaxt gələcək?',
                'expected' => [
                    'must_contain' => ['sifarişinizi', 'izləyə'],
                    'must_not_contain' => ['email:', 'password:'],
                    'max_words' => 100,
                    'tone' => 'polite',
                ],
            ],
            [
                'category' => 'refund_policy',
                'input' => 'Məhsulu qaytarmaq istəyirəm',
                'expected' => [
                    'must_mention_policy_days' => 14,
                    'must_not_promise' => ['100% refund', 'immediate'],
                ],
            ],
            // ... 500+
        ];

        foreach ($prompts as $p) {
            GoldenPrompt::create($p);
        }
    }
}
```

### Runner

```php
// app/Console/Commands/RunGoldenSuite.php

class RunGoldenSuite extends Command
{
    protected $signature = 'golden:run {--variant=} {--output=}';

    public function handle(AiGateway $gateway, GoldenJudge $judge)
    {
        $variant = $this->option('variant') ?? 'control';
        $prompts = GoldenPrompt::all();
        $results = [];
        $passed = 0;

        foreach ($prompts as $prompt) {
            $response = $gateway->chat(
                [['role' => 'user', 'content' => $prompt->input]],
                ['variant_tag' => $variant]
            );

            $judgment = $judge->evaluate($prompt, $response);
            $results[] = [
                'id' => $prompt->id,
                'passed' => $judgment->passed,
                'failures' => $judgment->failures,
                'score' => $judgment->score,
            ];
            if ($judgment->passed) $passed++;
        }

        $passRate = $passed / count($prompts);
        $this->info("Variant $variant: $passed/" . count($prompts) . " ($passRate)");

        if ($this->option('output')) {
            file_put_contents($this->option('output'), json_encode([
                'variant' => $variant,
                'pass_rate' => $passRate,
                'results' => $results,
            ]));
        }
    }
}
```

### PR Check

```yaml
# .github/workflows/golden-check.yml
- name: Run golden suite (control)
  run: php artisan golden:run --variant=control --output=control.json

- name: Run golden suite (PR variant)
  run: php artisan golden:run --variant=pr_candidate --output=candidate.json

- name: Compare
  run: |
    php artisan golden:compare control.json candidate.json
    # fail if pass_rate delta < -2%
```

Bot comment PR-də:

```
Golden suite results:
Control: 487/500 (97.4%)
Candidate: 491/500 (98.2%)
Delta: +0.8pp ✓

New passing: golden_142 (refund_policy)
New failing: golden_89 (tone_regression)
```

---

## 13. Correlation ID və Variant Tag Propagation <a name="correlation"></a>

Hər request-in hər log line-ında variant məlumatı olmalıdır. Debug vaxtında "bu variant bu cavabı niyə verdi?" sualına cavab vermək üçün.

### Minimum Propagated Fields

```
correlation_id     UUID, per-request
session_id         per-conversation
user_id            identity
variant_tag        "prompt=v1.5|model=sonnet-4-6|rag=corpus-2026-04"
prompt_sha         "a3f8c2e1"
model_version      "claude-sonnet-4-6-20260401"
rag_version        "corpus_2026_04_10"
```

### Laravel Log Context

```php
// app/Http/Middleware/LlmCorrelationContext.php

public function handle(Request $request, Closure $next)
{
    $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();
    $sessionId = $request->input('session_id') ?? 'anon-' . Str::random(8);

    $variants = $request->attributes->get('llm_variants', []);
    $variantTag = collect($variants)
        ->map(fn ($v, $k) => "$k=$v")
        ->join('|');

    Log::withContext([
        'correlation_id' => $correlationId,
        'session_id' => $sessionId,
        'user_id' => $request->user()?->id,
        'variant_tag' => $variantTag,
    ]);

    $response = $next($request);
    $response->headers->set('X-Correlation-ID', $correlationId);
    return $response;
}
```

### Downstream Propagation

HTTP client (Claude API çağırışı), queue job, database record — hamısı correlation ID daşımalıdır:

```php
// Queue
ProcessLlmResult::dispatch($data)->withCorrelationId($correlationId);

// DB
LlmInteraction::create([
    'correlation_id' => $correlationId,
    'variant_tag' => $variantTag,
    // ...
]);
```

### Query Patterns

Bu sahəməni indeksləyin. Query-lər:

```sql
-- Specific incident debug
SELECT * FROM llm_interactions WHERE correlation_id = 'abc-123';

-- Variant performance
SELECT variant_tag, AVG(latency_ms), AVG(quality_score)
FROM llm_interactions
WHERE created_at > NOW() - INTERVAL '24 hours'
GROUP BY variant_tag;

-- Regression after deploy
SELECT variant_tag, COUNT(*) FILTER (WHERE error = TRUE) / COUNT(*)::float AS error_rate
FROM llm_interactions
WHERE created_at > '2026-04-20 14:00'
GROUP BY variant_tag;
```

---

## 14. Auto-Rollback Triggers <a name="rollback"></a>

İnsan intervensiyası olmadan canary-ni geri qaytaran avtomatik sistem.

### Trigger Types

```
┌──────────────────────────────────────────────────────────┐
│            Auto-Rollback Trigger Examples                │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  1) Error Rate Spike                                     │
│     canary_error_rate > control_error_rate + 2pp         │
│     SUSTAINED for 5 minutes                              │
│                                                          │
│  2) Latency Regression                                   │
│     canary p95 > control p95 * 1.5                       │
│     SUSTAINED for 10 minutes                             │
│                                                          │
│  3) Cost Explosion                                       │
│     canary cost per request > control * 2                │
│     SUSTAINED for 15 minutes                             │
│                                                          │
│  4) Safety Violation                                     │
│     canary_safety_violations > 0 AND control_sv == 0    │
│     IMMEDIATE                                            │
│                                                          │
│  5) Quality Regression                                   │
│     canary_eval_score < control_eval_score - 3pp         │
│     SUSTAINED across 1000+ samples                       │
│                                                          │
│  6) Support Ticket Spike                                 │
│     tickets_per_hour > baseline * 2                      │
│     FROM canary users                                    │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Implementation

```php
// app/Jobs/AutoRollbackMonitor.php

class AutoRollbackMonitor implements ShouldQueue
{
    public function handle()
    {
        $active = Deployment::where('phase', 'canary')->get();

        foreach ($active as $deployment) {
            $metrics = $this->collectMetrics($deployment);
            $triggers = $this->evaluateTriggers($deployment, $metrics);

            if (!empty($triggers)) {
                $this->rollback($deployment, $triggers);
            }
        }
    }

    private function rollback(Deployment $deployment, array $triggers): void
    {
        DB::transaction(function () use ($deployment, $triggers) {
            $deployment->update([
                'phase' => 'rolled_back',
                'rollback_reason' => json_encode($triggers),
                'rolled_back_at' => now(),
            ]);

            // Feature flag switch off
            Feature::for('*')->deactivate($deployment->flag_key);

            // Config snap back
            Cache::put('ai:active_variant', 'control', now()->addHour());
        });

        event(new AutoRollbackTriggered($deployment, $triggers));

        Log::error('Auto-rollback triggered', [
            'deployment_id' => $deployment->id,
            'variant_tag' => $deployment->variant_tag,
            'triggers' => $triggers,
        ]);

        // PagerDuty, Slack notification
        $this->notifyOncall($deployment, $triggers);
    }
}
```

Schedule:

```php
$schedule->job(new AutoRollbackMonitor)->everyMinute();
```

### Safeguards

- Rollback yalnız canary fazasında işləyir (fully-deployed variant-ı rollback etmək ayrıca prosedur)
- "Rollback cooldown" — eyni variant 24 saatda iki dəfə rollback olsa, manual intervention required
- Rollback event-ləri audit log-a (kim, nə vaxt, hansı trigger)
- Alert — deyil səssizcə rollback, pager on-call-a

---

## 15. Incident Runbook: "New Variant Broke Prod" <a name="runbook"></a>

T0: canary 10%-ə çatdı. T+30min alert: error rate +5pp.

### Phase 1: Triage (0-5 dəqiqə)

1. Acknowledge pager
2. Dashboard-da canary variant metrics
3. Sample 5 failing request (correlation ID ilə)
4. Blast radius: yalnız canary bucket? Yoxsa control-u da təsir edir?

### Phase 2: Rollback Decision (5-10 dəqiqə)

Decision matrix:

| Signal | Action |
|--------|--------|
| Auto-rollback artıq trigger olunub | Təsdiqlə, postmortem planla |
| Error rate canary only, control stabil | Manual rollback (flag off) |
| Error rate hər yerdə | Infrastructure incident, başqa runbook |
| Quality regression, no error | Daha yavaş — eval et, sonra rollback |

Rollback:
```bash
php artisan feature:deactivate prompt_v1.5.0
# or
AI_ACTIVE_VARIANT=blue php artisan config:cache
```

### Phase 3: Verification (10-20 dəqiqə)

- Error rate düşür?
- Canary bucket-ləri control config-ə keçibmi?
- User-facing metric-lər normallaşır?

### Phase 4: Communication (20-30 dəqiqə)

- Internal Slack: incident status
- Affected user notification (əgər visibility varsa)
- Status page update

### Phase 5: Postmortem (3-5 gün)

- Timeline
- Root cause: prompt dəyişikliyi edge case-i aşkarladı / model silent update / RAG corpus regression
- Gaps: golden suite niyə tutmadı? Trigger niyə gec işə düşdü?
- Action items: yeni test case, trigger sensitivity, deploy process dəyişikliyi

---

## 16. Cost Tradeoff: Shadow Mode <a name="cost"></a>

Shadow mode-un ən böyük çatışmazlığı: **LLM cost 2x**. Hər real request iki dəfə inference edilir.

### Konkret Nümunə

- Daily request: 1,000,000
- Cost per request (Sonnet): $0.006
- Normal daily cost: $6,000
- Shadow-da: $12,000 ($6,000 əlavə)
- Aylıq shadow cost: $180,000

### Cost Reduction Strategies

**1. Sampling**: 100%-in əvəzinə 10% shadow

```php
if (config('ai.shadow.enabled') && random_int(1, 100) <= 10) {
    ShadowLlmRequest::dispatch(...);
}
```

10% shadow → statistical power hələ də yaxşıdır (100,000 sample/gün).

**2. Strategic Shadow**: Yalnız əhəmiyyətli deploy-da shadow, routine-də yox.

**3. Offline Shadow**: Real request-dən dərhal sonra shadow etmək əvəzinə, logged request-ləri batch-də yenidən qaç:

```php
// Gecə batch
$requests = InteractionLog::where('created_at', '>', now()->subDay())
    ->inRandomOrder()->limit(10000)->get();

foreach ($requests as $req) {
    OfflineShadowJob::dispatch($req, 'shadow_variant_v1.5');
}
```

Cost eyni, amma spread over time, user-visible latency-a dəxli yox (shadow onsuz da user-visible deyil).

**4. Shadow on Haiku**: Shadow variant Haiku üzərində — 10x ucuz. Əsas variant Sonnet. Tradeoff: comparison apples-to-oranges.

### Shadow Duration vs Cost

```
Duration    Cost (shadow 100%)   Cost (shadow 10%)
1 week      $42k                 $4.2k
2 weeks     $84k                 $8.4k
4 weeks     $168k                $16.8k
```

Budget-ə görə strategy.

---

## 17. Anti-Pattern-lər <a name="anti"></a>

### Anti-Pattern 1: "Simple prompt tweak, skip shadow"

İn 2023 bir incident-də bir `{user_name}` placeholder template fix-i 2 saatdan çox prod outage-a səbəb oldu, çünki real data-da edge case var idi (null user_name). "Sadə dəyişiklik" yalnız diff-də sadədir. Prod distribution-da heç nə sadə deyil.

### Anti-Pattern 2: Simultan Multi-Axis Change

```
PR: "Update model to sonnet-4-6, add 3 new tools,
     restructure system prompt, update RAG corpus"
```

Regresiya olarsa, hansı axis səbəb olub? Separate PR-lər.

### Anti-Pattern 3: "Staging passirsə prod-da keçəcək"

Staging synthetic + dev data-ya görə uyğun deyil. Real user distribution fərqlidir. Canary məcburidir.

### Anti-Pattern 4: Manual Rollback

İnsan 3 dəqiqədə acknowledge edir, 5 dəqiqədə rollback edir. Bu vaxtda 100,000 user-ə pis cavab gedib. **Auto-rollback trigger-ləri qurmaq birdəfəlik investisiyadır**, sonra hər incidentdə dəqiqələr qazandırır.

### Anti-Pattern 5: "Canary 1% yetər"

Low-traffic app üçün (1000 req/gün) 1% = 10 request — statistical significance yoxdur. Minimum sample size hesablayın: 95% confidence, 2pp effect size → adətən 10,000+ request.

### Anti-Pattern 6: Variant Tag-ı Log-a Qoymamaq

Incident-də logs-ı baxırsınız, hamısı eyni görünür — hansı variant-dan olduğunu bilmirsiniz. Root cause analiz mümkün deyil.

### Anti-Pattern 7: "Flag-i heç vaxt silmirik"

6 ay sonra 80 flag. Sistem oxunmur, test combinasiyası partlayır. Quarterly flag cleanup.

### Anti-Pattern 8: Golden Suite Outdated

Golden prompts 2 il əvvəl yazılıb, product dəyişib. Suite keçir, real user-lər pis cavablar alır. **Golden suite-i live data-dan rejeneration** (anonymized, consent-li) — aylıq review.

### Anti-Pattern 9: Shadow-u unutmaq

Shadow 1 həftə qaçdı, kimsə nəticəyə baxmadı. Cost sayırsa, effektivlik yox. Shadow dispatch hər gün automated dashboard-da review.

### Anti-Pattern 10: "Rollback bad, keep pushing"

Rollback failure deyil, **risk azaltma məhsuludur**. Rollback statistics performance metric-dir, öyrənmək üçün siqnaldır. Cəsarətli rollback mədəniyyəti lazımdır.

---

## Xülasə

LLM deployment klassik deploy-dan fundamentally fərqlidir. **Kod + prompt + model + data** 4 axis ayrı deploy olunmalıdır.

Əsas mesajlar:

1. **Canary default** — 1% → 100% gradual, auto-rollback trigger-li
2. **Shadow mode** — major changes üçün (provider swap, model upgrade), cost 2x amma risk məhv edir
3. **Blue-green** — low-traffic admin feature üçün
4. **A/B testing** — experimentation üçün, sticky user assignment
5. **Prompts in git** — SHA versioned, code review, CI validation
6. **Feature flags** — Pennant/LaunchDarkly, lifecycle management
7. **Correlation ID + variant tag** — hər log-da olmalıdır
8. **Golden suite** — PR gate, 500+ prompts minimum
9. **Auto-rollback** — insan deyil, dəqiqələr hesab edilir
10. **Multi-axis simultaneous change — yox**, ayrı deploy-lar

Sonrakı fayl (62) — content moderation: toxic, NSFW, CSAM, PII filtering.
