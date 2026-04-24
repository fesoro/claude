# AI MVP Playbook — 1 Həftədə Gerçək AI Feature Shippe Et

> **Kontekst**: sən senior PHP dev-sən, CEO dedi "biz də AI etməliyik". 1 həftən var. Nə edəsən? Bu playbook günbəgün real addımları verir — fine-tune yox, custom RAG framework yox, sadəcə **prompt + integration + feedback loop**.

---

## Mündəricat

1. [Prinsiplər](#principles)
2. [Day 0 — Use-case Seç](#day0)
3. [Day 1 — Tinker ilə Sübut Et](#day1)
4. [Day 2 — Baseline Ölç](#day2)
5. [Day 3 — Laravel-ə İnteqrasiya Et](#day3)
6. [Day 4 — 3 İstifadəçiyə Ship Et](#day4)
7. [Day 5 — İterə, Guardrails Əlavə Et](#day5)
8. [Day 6 — Observability](#day6)
9. [Day 7 — Genişləndir və ya Kill Et](#day7)
10. [Anti-patterns](#anti-patterns)
11. [PHP Scaffolding Checklist](#scaffolding)
12. [Case Study: Contract-Clause Extractor 6 Günə](#case-study)

---

## Prinsiplər <a name="principles"></a>

### Birinci həftənin qızıl qaydaları

1. **BİR konkret use-case**. "AI every" yox. "X komandası üçün həftədə 5 saat qənaət" konkret hədəf.
2. **Prompt-first**. Fine-tune etmə. Custom RAG qurma. İlk olaraq prompt-un nə edə biləcəyini sına.
3. **Eval-first**. İlk gün 10 test input/output-u hazırla. "80%-i qəbul olunandır" hədəfini müəyyən et.
4. **Ship over perfect**. Day 4-də 3 real istifadəçiyə göndər. Perfection day 30-un problemi.
5. **Kill criteria**. Day 7-də "işləmir" qərarı vermək də success-dur.
6. **Dev-şübhə**: LLM hallucinasiya edəcək. Guardrails planı ilk gündən olsun.

---

## Day 0 — Use-case Seç <a name="day0"></a>

### Use-case Scoring Matrix

Namizəd use-case-ləri bu kriteriyalarla qiymətləndir:

| Kriter | Ağırlıq | Sual |
|--------|---------|------|
| **Volume** | 3x | Gündə neçə dəfə edilir? (10+ = yaxşı) |
| **Zaman dəyəri** | 3x | Hər dəfə nə qədər vaxt alır? (10+ dəq = yaxşı) |
| **Pattern** | 2x | Strukturu varmı? (email-ı kateqoriyalamaq = bəli) |
| **Toleransı** | 2x | 80% accuracy kifayət edirmi? (bəli = yaxşı) |
| **Dəyərləndirmə** | 2x | Nəticəni insan 10 saniyəyə yoxlaya bilirmi? |
| **Data pristine** | 1x | Giriş/çıxış məlumatı əlimdə varmı? |

Hər kriter 1-5 arası. Ən yüksək skor qalibdir.

### Tipik yaxşı MVP namizədləri (PHP backend şirkəti üçün)

| Use-case | Niyə yaxşı MVP |
|----------|----------------|
| Support ticket kateqoriyalaşdırma | Volume yüksək, pattern aydın, insan yoxlayır |
| Email draft generator | Zaman dəyəri yüksək, toleranslı (draft → human) |
| Release notes generator (git log-dan) | Strukturu var, gündə 1 dəfə, faydalı |
| Meeting transcript → action items | Pattern aydın, data əlimizdə |
| Internal documentation search | Volume yüksək, baseline pis (grep) |

### Anti-use-case-lər (day 7-də kill olunacaq)

- **Medical advice / legal opinion** — tolerance yoxdur
- **Financial decision automation** — audit riski
- **Creative branding copy** — quality subyektiv
- **Code generation for prod** — review burden yüksək

### Məşq

Gündəlik dəftərinə 3 use-case yaz, skorla, #1-i seç. 30 dəqiqə. Bitdi.

---

## Day 1 — Tinker ilə Sübut Et <a name="day1"></a>

### Məqsəd: "Model bunu edə bilir?" sualına **bəli** və ya **xeyr** cavab ver.

**Qayda**: kod yazma. Laravel-ə toxunma. Sadəcə `php artisan tinker` və ya Anthropic Console Playground.

```php
// php artisan tinker

use Anthropic\Anthropic;

$client = Anthropic::client(env('ANTHROPIC_API_KEY'));

$response = $client->messages()->create([
    'model' => 'claude-sonnet-4-5',
    'max_tokens' => 500,
    'messages' => [[
        'role' => 'user',
        'content' => <<<PROMPT
        Bu support email-ini kategoriyalaşdır:

        Email: "Salam, mənim sifarişim 5 gündür gəlmir. Tracking linki işləmir. Xahiş edirəm kömək edin."

        Output JSON:
        {"category": "support|sales|billing|spam", "priority": "low|normal|high|urgent", "reason": "brief"}
        PROMPT,
    ]],
]);

dd($response->content[0]->text);
```

### 10 Test Input

Dəftərdə 10 real input hazırla:

```
1. "Sifariş gecikib, tracking işləmir" → support, high
2. "Qiymət endirimi varmı?" → sales, normal
3. "Hesabımda yanlış ödəniş göstərilir" → billing, high
4. "PENIS ENLARGEMENT PILLS" → spam, low
...
```

### Eval-i əllə qaç

Hər 10 input-u yuxarıdakı kod ilə keç. Nəticələri qeyd et:

| № | Expected category | Actual category | Expected priority | Actual priority | OK? |
|---|--|--|--|--|----|
| 1 | support | support | high | high | ✓ |
| 2 | sales | sales | normal | normal | ✓ |
| 3 | billing | billing | high | urgent | partial |
| ... |

**Uğur meyarı**: `8/10 tamamilə doğru` və ya `9/10 qismən doğru`. Bundan az?

### Prompt-ı dəyişdir — hələ də model yeterli deyilsə

1. **Few-shot examples** əlavə et: "Here are 3 examples: ..."
2. **Sonnet → Opus** upgrade et (gun 1 üçün acceptable, prod-da cost-check lazım)
3. **Structured output tools** istifadə et (bax 02-claude-api/03-structured-output.md)

Hələ də pis? **Use-case səhvdir** — day 0-a qayıt.

### Day 1 nəticə

```
✓ 10 test input hazır
✓ Prompt v1 draft
✓ 8/10 accuracy əldə edildi
✓ Model seçildi: claude-sonnet-4-5
✓ Token sayı per request: ~500 input + 100 output
```

---

## Day 2 — Baseline Ölç <a name="day2"></a>

### Əvvəl insan bunu neçə vaxtda edirdi?

Müsahibə et:
- Support manager: "Email gələndə hansı prosesi keçirsən?"
- Cavab: "Oxuyuram, Slack-da mesajlayıram, CRM-ə əlavə edirəm, Jira-da ticket açıram — orta 3 dəqiqə".

### Baseline metric-lər

| Metric | Current (insan) | Target (AI ilə) |
|--------|-----------------|-----------------|
| Kategoriyalaşdırma vaxtı/email | 45 saniyə | <5 saniyə |
| Orta kategoriya accuracy | 92% (manager qiymətləndirib) | ≥85% |
| Gündəlik emaillərin sayı | 200 | 200 (həmin volume) |
| Aylıq zaman xərci | 200 × 45s × 22 iş günü = **55 saat** | — |

### İş adamlarına necə izah etmək

"Manager hal-hazırda ayda 55 saat email kategoriyalaşdırır. Bu AI feature eyni işi 5-dəq-lik insan review ilə 5 saata endirir. Ayda 50 saat qənaət = ~$1000 dəyər. AI xərci: ~$30/ay."

**Bu slide-ı hazırla — Day 4 istifadəçi demosunda lazımdır.**

---

## Day 3 — Laravel-ə İnteqrasiya Et <a name="day3"></a>

### Qayda: minimal viable integration

- 1 route
- 1 Job
- 1 service class
- 1 Filament view (əgər internal tool-dursa)

### Scaffold

```bash
php artisan make:job ClassifyEmailJob
php artisan make:service EmailClassifier
php artisan make:migration create_email_classifications_table
```

### Migration

```php
Schema::create('email_classifications', function (Blueprint $table) {
    $table->id();
    $table->text('email_body');
    $table->string('category')->nullable();
    $table->string('priority')->nullable();
    $table->float('confidence')->nullable();
    $table->json('raw_response')->nullable();
    $table->string('human_override')->nullable();  // feedback
    $table->string('status');  // pending, classified, reviewed
    $table->timestamps();
});
```

### Service

```php
<?php
namespace App\Services;

use Anthropic\Anthropic;

class EmailClassifier
{
    public function __construct(private Anthropic $claude) {}

    public function classify(string $emailBody): array
    {
        $response = $this->claude->messages()->create([
            'model' => 'claude-sonnet-4-5',
            'max_tokens' => 200,
            'messages' => [[
                'role' => 'user',
                'content' => $this->buildPrompt($emailBody),
            ]],
        ]);

        return json_decode($response->content[0]->text, true);
    }

    private function buildPrompt(string $body): string
    {
        return <<<PROMPT
        Classify this email. Output JSON only.

        Categories: support, sales, billing, spam
        Priorities: low, normal, high, urgent

        Email:
        {$body}

        JSON: {"category": "...", "priority": "...", "confidence": 0.0-1.0}
        PROMPT;
    }
}
```

### Job

```php
<?php
namespace App\Jobs;

use App\Models\EmailClassification;
use App\Services\EmailClassifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ClassifyEmailJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $classificationId) {}

    public function handle(EmailClassifier $classifier): void
    {
        $c = EmailClassification::find($this->classificationId);
        $result = $classifier->classify($c->email_body);

        $c->update([
            'category' => $result['category'],
            'priority' => $result['priority'],
            'confidence' => $result['confidence'],
            'raw_response' => $result,
            'status' => 'classified',
        ]);
    }
}
```

### Filament Resource (Day 3 üçün kifayət)

```php
// app/Filament/Resources/EmailClassificationResource.php
// Minimal: columns email_body (truncated), category, priority, confidence, status, created_at
// Action: "Override" → manager yanlışdırsa düzgün kategoriyanı seçir
```

### Day 3 nəticə

```
✓ Laravel-də işləyir
✓ 10 test email job-a göndərildi, 9/10 doğru
✓ Filament-də nəticələr görünür
✓ Deploy staging-ə
```

---

## Day 4 — 3 İstifadəçiyə Ship Et <a name="day4"></a>

### 3 nəfərli cerberus — istifadəçi seç

1. **Skeptic** — "AI əyrilik edir" deyən birisi. Qorxduqları nədir?
2. **Çoban** — işi həqiqətən edən komanda üzvü. Real feedback verir.
3. **Champion** — AI-ə maraqlı, tolerant istifadəçi. Positive signal.

### Demo-nu planla

30 dəqiqə, 3 hissəli:

1. **Day 2 baseline slide-ı göstər** (55 saat/ay problemi)
2. **Canlı demo** — staging-də 3 real email-i AI-dan keçir
3. **Feedback sualları**:
   - Bu nəticəni qəbul edəsənmi?
   - Nə yanlışdır?
   - Bir şey qorxudur?

### İlk dəfə 3 faydalı signal toplayırsan:

- **Yanlış kategoriyalar** — prompt dəyişdirmə üçün input
- **Edge case-lər** — yadına gəlməyən input-lar
- **UX dalğalanma** — "bu tablonu necə istifadə edim?" sualları

### Day 4 nəticə

```
✓ 3 nəfər canlı demo gördü
✓ 12 feedback item toplandı (8 prompt-a aid, 4 UX-a aid)
✓ "2-ci mərhələ" meyarı: manager günortayadək "davam et" deyir
```

---

## Day 5 — İterə, Guardrails Əlavə Et <a name="day5"></a>

### Prompt iteration

Feedback-dən real error pattern-ləri çıxar. Prompt-a yeni few-shot example əlavə et:

```
Email: "Invoice-um yoxdur, emailimdə görmürəm"
Expected: billing, normal (NOT support)

Email: "Hesabıma girə bilmirəm, parol dəyişəsilə müşahidəsində"
Expected: support, high (account-related urgency)
```

### Guardrails

Hər AI output-a əl çatmayan şeyləri filtrlə:

```php
// Validate response
public function classify(string $emailBody): array
{
    $result = $this->callClaude($emailBody);

    // Guardrail 1: valid enum
    if (!in_array($result['category'] ?? null, ['support', 'sales', 'billing', 'spam'])) {
        Log::warning('Invalid category from LLM', $result);
        return ['category' => 'support', 'priority' => 'normal', 'confidence' => 0.3]; // safe default
    }

    // Guardrail 2: low confidence → human review
    if (($result['confidence'] ?? 0) < 0.6) {
        $result['status'] = 'needs_review';
    }

    // Guardrail 3: urgent priority → require human approval before auto-routing
    if ($result['priority'] === 'urgent') {
        $result['requires_approval'] = true;
    }

    return $result;
}
```

### Kill switch

Feature-i **bir env var** ilə söndürə bilmək üçün:

```php
if (!config('features.email_classifier.enabled')) {
    return; // no-op
}
```

`php artisan tinker` → `config(['features.email_classifier.enabled' => false])` — incident zamanı cavab 30 saniyə.

---

## Day 6 — Observability <a name="day6"></a>

### Minimal Dashboard

3 metric göstər:

1. **Throughput** — gündəlik classified email sayı
2. **Accuracy** — human override-ların %-i (bu "inverse accuracy" — aşağı yaxşıdır)
3. **Cost** — gündəlik token xərci (`usage.input_tokens + usage.output_tokens × price`)

### Telescope + Log channel

```php
// config/logging.php
'ai' => [
    'driver' => 'daily',
    'path' => storage_path('logs/ai.log'),
    'days' => 30,
],
```

```php
Log::channel('ai')->info('email.classified', [
    'email_id' => $c->id,
    'category' => $result['category'],
    'priority' => $result['priority'],
    'confidence' => $result['confidence'],
    'input_tokens' => $response->usage->input_tokens,
    'output_tokens' => $response->usage->output_tokens,
    'latency_ms' => $durationMs,
]);
```

### Alertinq

```
- AI error rate > 10% → Slack alert
- Daily token spend > $5 → warning
- P95 latency > 10s → degraded alert
```

### Feedback loop

Filament-də "override" edilən hər halını `training_data` table-a at. Day 30-da bu dataset-lə prompt yenidən iterate edəcəksən.

---

## Day 7 — Genişləndir və ya Kill Et <a name="day7"></a>

### Success kriter-lər yoxla

| Kriter | Target | Actual |
|--------|--------|--------|
| Human override rate | <15% | ? |
| Daily processed | ≥50 | ? |
| User satisfaction (3 user-dən 2) | 3/3 | ? |
| Cost/day | <$5 | ? |
| P95 latency | <5s | ? |

### Qərar Treesi

```
Accuracy ≥ 85% və User satisfaction 3/3?
├── Bəli → ROLLOUT: bütün komandaya
│          ├── Rollout plan (2 həftə)
│          ├── Train komandanı (15 dəq onboard)
│          └── Monitor 30 gün, sonra review
└── Xeyr → KILL və ya PIVOT
           ├── Accuracy aşağı → prompt yenidən iterate, 1 həftə əlavə zaman
           ├── User satisfaction aşağı → UX feedback ciddi götür
           └── Cost yüksək → Haiku-ya downgrade və ya classic regex-ə qayıt
```

### Kill etmək də success-dur

**"Biz AI build etmədik" ilə "AI işləmir, build etdik amma kill etdik" — ikincisi 100x daha yaxşı qərardır**. Həm komanda AI-ni praktik öyrənir, həm də pis-yazılmış feature prod-a getmir.

---

## Anti-patterns <a name="anti-patterns"></a>

| Anti-pattern | Niyə Səhv? |
|--------------|-----------|
| **Day 1: fine-tune etmək** | 80% of use cases prompt ilə həll olunur. Fine-tune əmək + vaxt. Day 1-də yox. |
| **Day 2: custom RAG framework qurmaq** | Laravel-ə hələ toxunmadan pgvector schema dizayn edirsən. Prompt hələ sübut edilməyib. |
| **Day 3: GPT + Claude + Gemini gateway qurmaq** | Multi-provider day 30 probleminadır. Day 3-də tək provayder ilə. |
| **Day 4: complete UI** | Filament-də 1 ekran kifayət. Vuetify + Tailwind + animation… day 30. |
| **Day 5: Automated eval suite** | 10 manual test kifayət. Day 30-da pytest-like eval. |
| **Day 7: "100% accuracy olmasa launch etmərəm"** | Perfection trap. 85% insan oversight ilə production-ready. |
| **Hər gün CEO-ya demo göstərmək** | Day 4-ə qədər intranet, Day 7-də CEO. Vaxtını xərcləmə. |
| **"Bu prompt-u secret saxlayaq"** | Prompt IP deyil. Commit et. Git history sənə dost olacaq. |

---

## PHP Scaffolding Checklist <a name="scaffolding"></a>

Yeni AI feature başlayanda bu addımları copy-paste et:

```bash
# 1. Env
echo "ANTHROPIC_API_KEY=" >> .env
echo "AI_FEATURE_EMAIL_CLASSIFIER_ENABLED=true" >> .env

# 2. Package
composer require anthropic-ai/sdk-php

# 3. Scaffold
php artisan make:job {FeatureName}Job
php artisan make:service Ai/{FeatureName}Service
php artisan make:migration create_{feature}_table

# 4. Config
# config/ai.php
```

```php
// config/ai.php
return [
    'features' => [
        'email_classifier' => ['enabled' => env('AI_FEATURE_EMAIL_CLASSIFIER_ENABLED', false)],
    ],
    'models' => [
        'cheap' => 'claude-haiku-4-5',
        'smart' => 'claude-sonnet-4-5',
    ],
    'budgets' => [
        'daily_usd' => env('AI_DAILY_BUDGET_USD', 10),
    ],
];
```

```php
// app/Services/Ai/Base.php
abstract class BaseAiService
{
    public function __construct(protected Anthropic\Anthropic $claude) {}

    protected function call(array $messages, string $model = 'claude-sonnet-4-5', int $maxTokens = 500): array
    {
        // Budget check
        $spent = Cache::get('ai.daily_spent', 0);
        if ($spent > config('ai.budgets.daily_usd')) {
            throw new \RuntimeException('Daily AI budget exceeded');
        }

        $response = $this->claude->messages()->create([
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ]);

        // Track spend
        $cost = $this->calculateCost($model, $response->usage);
        Cache::increment('ai.daily_spent', $cost);

        return $response->toArray();
    }

    private function calculateCost(string $model, object $usage): float
    {
        // Haiku 4.5: $1/M in, $5/M out; Sonnet 4.5: $3/M in, $15/M out
        $rates = [
            'claude-haiku-4-5' => ['in' => 1.0, 'out' => 5.0],
            'claude-sonnet-4-5' => ['in' => 3.0, 'out' => 15.0],
        ];
        $r = $rates[$model] ?? $rates['claude-sonnet-4-5'];
        return ($usage->input_tokens * $r['in'] + $usage->output_tokens * $r['out']) / 1_000_000;
    }
}
```

Checklist bir dəfə. Sonra hər feature 1 gündə scaffold olunur.

---

## Case Study: Contract-Clause Extractor 6 Günə <a name="case-study"></a>

Real bir tətbiq — SaaS B2B şirkəti, legal team həftədə 50 müqavilə review edirdi. Hər müqavilə 3 saat oxunma. Hədəf: AI 10 risky clause-u tapsın, insan 20 dəqiqədə review etsin.

### Timeline

**Day 0** — Use-case scoring: 5/5/5/5/5/4 = 29/30. Volume yüksək, toleranslı (insan last-line of-defence). **Seçildi**.

**Day 1** — Tinker:
```php
$contract = file_get_contents('sample_contract.pdf'); // extract text
$response = $claude->messages()->create([
    'model' => 'claude-opus-4-5',  // Opus — legal accuracy lazım
    'system' => 'You are a legal contract reviewer for SaaS B2B deals...',
    'messages' => [['role' => 'user', 'content' => "Extract all risky clauses:\n{$contract}"]],
]);
```

10 real müqavilə test. Opus 9/10 risky-clause-u tapdı. Sonnet — 7/10 (Sonnet yetmir bu case üçün).

**Day 2** — Baseline: Legal 1 saatda 20 saat review. Target 20 dəq × 50 = ~17 saat. 3 saat qənaət = ~$1500/həftə.

**Day 3** — Laravel integration:
- PDF upload → text extract (Spatie PDF)
- `ExtractClausesJob` → Opus
- Filament ekran: müqavilə list + extracted clauses + risk level

**Day 4** — Demo legal team-ə (3 hüquqşünas). Feedback:
- "Jurisdiction clause-larını da çıxar"
- "Termination notice period-lərini də"
- "Indemnity clauses vacib"

**Day 5** — Prompt-ı few-shot ilə zənginləşdir, guardrail: `confidence < 0.7` → human-only review.

**Day 6** — Observability: Telescope + cost dashboard.

**Day 7** — Rollout: bütün legal team (5 nəfər). 30 gün monitor.

### Nəticə

- Həftəlik vaxt qənaəti: 9 saat (planlaşdırılan: 7.5 saat) — **over-performed**
- Accuracy: 92% (iki legal partner cross-check)
- Cost: $180/həftə (Opus-lu)
- Qərar: **Genişləndir** — 3 ay sonra clause library-ə əlavə, clause-spesifik template match

### Açar dərslər

1. Opus seçildi — Sonnet kifayət etmirdi. Test **day 1**-də oldu, day 7-də yox.
2. Legal team **day 4**-də seed oldular — day 7-də onlar "champion" olublar.
3. Guardrail "low confidence" — 1 dəfə istənilənsiz clause-u insan-only kateqoriyasına ötürmüş, audit log göstərdi.

---

## Xülasə

| Gün | Çıxarış |
|-----|---------|
| 0 | Use-case scoring. 1 namizəd seçildi. |
| 1 | Tinker. 10 test. 8+/10 accuracy sübut. |
| 2 | Baseline. Business case slide. |
| 3 | Laravel scaffold. Filament UI minimal. |
| 4 | 3 user canlı demo. Feedback toplandı. |
| 5 | Prompt iterate. Guardrails. Kill switch. |
| 6 | Observability. Token spend tracking. |
| 7 | Success criteria review. Rollout və ya kill. |

**Prinsip**: AI MVP prompt + data pipeline + minimal UI + kill switch. Custom fine-tune, vector DB, agent framework = day 30 problemi.

Növbəti: [03-build-vs-buy-ai.md](./03-build-vs-buy-ai.md) — komponentləri öz quraşdır, yoxsa al?
