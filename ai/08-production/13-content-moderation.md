# Content Moderation: Toxic, CSAM, PII və NSFW Filtering (Lead)

> **Oxucu kütləsi:** Senior developerlər, Trust & Safety engineer-lər, legal/compliance tərəfdaşlar
> **Bu faylın 08 və 11-dən fərqi:** 08 — safety guardrails ümumi çərçivə (refusal, policy). 11 — PII redaction spesifik (Azerbaycan-specific patterns, reversible tokenization). Bu fayl — **end-to-end moderation stack**: harmful content taxonomy, provider comparison, stacked approach (regex → classifier → LLM judge), latency budget, CSAM legal path (PhotoDNA, NCMEC), regional fərqlər, Laravel middleware pattern, human review queue, appeals, transparency reports.

---

## Mündəricat

1. [Niyə Moderation Legally Məcburidir](#why)
2. [Harmful Content Taxonomy](#taxonomy)
3. [Harada Moderate Etmək: 4 Giriş Nöqtəsi](#where)
4. [Provider Müqayisə Matrisi](#providers)
5. [Stacked Approach: Defense-in-Depth](#stacked)
6. [Latency Budget](#latency)
7. [FP vs FN Tradeoff](#fp-fn)
8. [CSAM Special Legal Path](#csam)
9. [Age Gating və NSFW](#age-gating)
10. [Regional Tərtiblər: EU, ME, APAC](#regional)
11. [Laravel Middleware Pattern](#laravel)
12. [Moderation Log Retention](#retention)
13. [Human Review Queue](#review)
14. [Appeals Process](#appeals)
15. [Transparency Reports](#transparency)
16. [Anti-Pattern-lər](#anti)

---

## 1. Niyə Moderation Legally Məcburidir <a name="why"></a>

Bir neçə il əvvəl content moderation "nice to have" idi. 2024-2026-da bu **legal məcburidir**:

### 1.1 Section 230 Mühitinin Dəyişməsi

ABŞ-da Section 230 platformaya immunity verirdi. 2024-də dekret və məhkəmə qərarları bu immunity-nin AI-generated content üçün **tətbiq olunmadığını** təsdiqlədi. Platforma LLM-in çıxardığı content üçün publisher statusunda olur — mübahisəli, amma trend.

### 1.2 EU Digital Services Act (DSA)

2024-02 etibarilə qüvvədə. Çox böyük online platformalar (45M+ MAU EU-da) üçün:
- Illegal content bildiriş + təcili aradan qaldırma (Article 16)
- Proaktif risk assessment (Article 34)
- Independent audit annually (Article 37)

AI-generated illegal content burada daxildir. "Ama model cavab verdi" müdafiə deyil.

### 1.3 EU AI Act

High-risk AI sistemlər üçün:
- Content safety layer məcburi
- Human oversight (Article 14)
- Transparency (Article 13) — user bilməlidir AI ilə ünsiyyətdə olduğunu

### 1.4 Platform ToS (Provider-dən)

Anthropic, OpenAI, Google hər biri Acceptable Use Policy ilə gəlir. CSAM, terrorism, certain sexual content — API ilə sorğu belə edilə bilməz. Pozma → account termination, legal reporting.

### 1.5 Specific Laws

- **SESTA/FOSTA** (ABŞ): sex trafficking content
- **NetzDG** (Almaniya): hate speech 24 saat ərzində silinməli
- **Online Safety Act** (UK, 2023): platform-lar illegal + children üçün harmful content-dən qorumalıdır
- **UAE, SA regional laws**: religion, morality content

### 1.6 Real Financial Consequences

- **Snapchat 2023**: CSAM detection failure → $15M fine
- **TikTok 2024**: EU DSA violation → €345M
- **Telegram 2024**: Pavel Durov Fransa-da həbs, inkişaf etmiş content moderation tələbləri

"Biz kiçik şirkətik" müdafiəsi EU-da tutmur — AI Act hər ölçüdə qüvvədədir.

---

## 2. Harmful Content Taxonomy <a name="taxonomy"></a>

Ümumi industry taxonomy (Anthropic, OpenAI, Perspective müştərək qəbul edir):

```
┌──────────────────────────────────────────────────────────┐
│         Harmful Content Categories                       │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  HATE                                                    │
│    - Race, ethnicity, religion, gender, orientation      │
│    - Nationality, disability                             │
│    - Threatening variants (hate + violence)              │
│                                                          │
│  HARASSMENT                                              │
│    - Personal attacks, bullying                          │
│    - Threats against individuals                         │
│                                                          │
│  SEXUAL (Adult)                                          │
│    - Explicit sexual content                             │
│    - Non-consensual imagery (NCII)                       │
│    - Age-gating possibly, NCII never                     │
│                                                          │
│  CSAM (Child Sexual Abuse Material)                      │
│    - LEGAL REPORTING OBLIGATION                          │
│    - Zero-tolerance, separate pipeline                   │
│                                                          │
│  SELF-HARM                                               │
│    - Suicide ideation, method sharing                    │
│    - Eating disorders (pro-ana content)                  │
│                                                          │
│  VIOLENCE                                                │
│    - Graphic violence depiction                          │
│    - Terrorist propaganda                                │
│    - Weapons instructions                                │
│                                                          │
│  ILLEGAL GOODS / ACTS                                    │
│    - Drug synthesis, trafficking                         │
│    - Weapon manufacturing                                │
│    - Fraud, hacking                                      │
│                                                          │
│  PII (see file 53)                                       │
│    - Direct identifiers, financial, health, biometric    │
│                                                          │
│  MISINFO / DISINFO                                       │
│    - Political manipulation                              │
│    - Health misinformation (vaccine, medication)         │
│    - Election integrity                                  │
│                                                          │
│  COPYRIGHTED CONTENT                                     │
│    - Verbatim long excerpts                              │
│    - Regurgitation of training data                      │
│                                                          │
│  SPAM / SCAM                                             │
│    - Phishing, fraud attempts                            │
│    - Mass unsolicited content                            │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

Hər kateqoriyada severity gradasiyaları var:

- **Level 1**: borderline (implicit, ambiguous)
- **Level 2**: clear violation (explicit)
- **Level 3**: aggravated (imminent harm, targeted)

Policy hər level-ə fərqli action: L1 → warning, L2 → block, L3 → report + account suspension.

---

## 3. Harada Moderate Etmək: 4 Giriş Nöqtəsi <a name="where"></a>

LLM sistemində 4 yerdə content axır, hər biri ayrı moderation lazımdır:

```
┌────────────────────────────────────────────────────────────┐
│          Moderation Checkpoints in LLM Pipeline            │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  1) INPUT (user → system)                                  │
│     - User mesajı                                          │
│     - Upload (file, image)                                 │
│     - Goal: bad input-u early block                        │
│                                                            │
│  2) RETRIEVAL (RAG corpus → context)                       │
│     - Retrieved document content                           │
│     - Third-party API result                               │
│     - Goal: poisoned / embedded instructions tutmaq        │
│                                                            │
│  3) TOOL OUTPUT (tool → model context)                     │
│     - Web search result                                    │
│     - Database query result                                │
│     - Goal: injected content aşkarlamaq                    │
│                                                            │
│  4) OUTPUT (model → user)                                  │
│     - Model response                                       │
│     - Generated images, code, documents                    │
│     - Goal: harmful generation block etmək                 │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### Hər Yerdə Eyni Content? Xeyr.

Provider moderation API-lər əsasən **user-facing** content üçün calibrated-dir. Tool output-da technical content var — false positive çox olar. Hər endpoint üçün fərqli sensitivity.

| Checkpoint | Strictness | FP tolerance |
|-----------|------------|--------------|
| Input | Medium | Low (user frustration) |
| Retrieval | Medium-high | Medium |
| Tool output | High | Medium (indirect injection) |
| Output | Highest | Zero-to-low (legal exposure) |

---

## 4. Provider Müqayisə Matrisi <a name="providers"></a>

### 4.1 OpenAI Moderation API

- **Endpoint**: `POST /v1/moderations`
- **Model**: `omni-moderation-latest` (text + image, 2024-09 GA)
- **Cost**: FREE (OpenAI API-dən istifadə edənlər üçün)
- **Categories**: sexual, sexual/minors, harassment, harassment/threatening, hate, hate/threatening, illicit, illicit/violent, self-harm, self-harm/intent, self-harm/instructions, violence, violence/graphic
- **Output**: category booleans + scores 0-1
- **Latency**: ~100-200ms

Ən geniş istifadə olunan — pulsuz və API sadə.

### 4.2 Google Perspective API

- **Endpoint**: `commentanalyzer.googleapis.com/v1alpha1`
- **Cost**: 1 QPS free, above — kontakt
- **Categories**: TOXICITY, SEVERE_TOXICITY, IDENTITY_ATTACK, INSULT, PROFANITY, THREAT, SEXUALLY_EXPLICIT, FLIRTATION
- **Output**: score 0-1 hər kateqoriyada
- **Güclü tərəfi**: toxicity-də pioneer, çoxdilli (12+ dil), Azerbaijani yoxdur amma Russian, Turkish var
- **Latency**: ~150-300ms

Jigsaw (Google-un subsidiary-si) tərəfindən. Discord, Reddit, NYTimes istifadə edir.

### 4.3 AWS Comprehend

- **Service**: Amazon Comprehend
- **Cost**: $0.0001 / unit (100 char)
- **Features**: Toxic content detection, PII detection
- **Categories**: GRAPHIC, HARASSMENT_OR_ABUSE, HATE_SPEECH, INSULT, PROFANITY, SEXUAL, VIOLENCE_OR_THREAT
- **Güclü tərəfi**: AWS ekosistem, data residency

### 4.4 Azure Content Safety

- **Endpoint**: `contentsafety.*.cognitiveservices.azure.com`
- **Cost**: $0.75 / 1000 request (text), $1.50 / 1000 (image)
- **Categories**: Hate, SelfHarm, Sexual, Violence — 4 severity level
- **Features**: Text + image, prompt shield (injection), protected material (copyright)
- **Güclü tərəfi**: image moderation yaxşıdır, jailbreak shield ayrı endpoint

### 4.5 Anthropic Claude Built-in Refusal

- Claude özü refusal training-ə malikdir (Constitutional AI)
- Ayrıca moderation API yoxdur
- Strategy: prompt-da "if request is harmful, refuse politely with message X"
- Güclü: nüanslı, context-aware refusal
- Zəif: stochastic, bəzən bypass olur

### 4.6 Open-Source Options

**Detoxify** (Python package):
- BERT-based classifier
- Local, pulsuz
- Toxicity, severe toxicity, obscene, threat, insult, identity hate
- Latency: self-hosted, CPU ~200ms, GPU ~30ms

**Llama Guard 3** (Meta):
- 8B model fine-tuned for classification
- Local, Hugging Face
- 13 harm categories
- Latency: GPU ~100ms
- Güclü: customizable taxonomy, LLM-native

**NeMo Guardrails** (NVIDIA):
- Framework-level guardrails
- Input/output/dialog rails
- Compose məntiqi rules + models

### 4.7 Comparison Matrix

| Provider | Cost | Latency | Languages | Categories | Image |
|----------|------|---------|-----------|------------|-------|
| OpenAI Moderation | Free | 150ms | Multi | 13 | Yes |
| Google Perspective | Free/paid | 200ms | 12 | 8 | No |
| AWS Comprehend | $0.0001/unit | 200ms | Multi | 7 | No |
| Azure Content Safety | $0.75/1k | 150ms | Multi | 4 (+4sev) | Yes |
| Anthropic (built-in) | LLM cost | LLM latency | Multi | Refusal-based | Depends |
| Detoxify (local) | Free | 30-200ms | English | 6 | No |
| Llama Guard 3 | Free | 100ms | Multi | 13 | No |

### 4.8 Hybrid Strategy

Real-world tətbiqlər bir provider-ə deyil, **ensemble**-ə güvənir:

```
Input → OpenAI Moderation (pulsuz, fast)
     ↘
       Detoxify (local, ensemble)
     ↘
       Domain-specific regex (Azerbaycan curse words)
     ↘
       Llama Guard 3 (borderline üçün)
```

Hər hansı biri "high confidence harmful" desə → block.
Hamısı "clean" desə → allow.
Qarışıq siqnal → LLM judge layer.

---

## 5. Stacked Approach: Defense-in-Depth <a name="stacked"></a>

Heç bir tək moderation layer 100%-dir. Layer-lər:

```
┌──────────────────────────────────────────────────────────┐
│         Stacked Moderation Pipeline                      │
├──────────────────────────────────────────────────────────┤
│                                                          │
│   Layer 0: Regex / Keyword (fastest, cheapest)           │
│     - Obscenity wordlist                                 │
│     - Phone number, CC regex                             │
│     - Known jailbreak patterns                           │
│     - Latency: <5ms                                      │
│     - Coverage: ~40% of obvious cases                    │
│                                                          │
│   Layer 1: Classifier (fast)                             │
│     - OpenAI Moderation / Detoxify / Llama Guard         │
│     - Latency: 50-200ms                                  │
│     - Coverage: ~80% including nuanced                   │
│                                                          │
│   Layer 2: LLM Judge (expensive)                         │
│     - Only for borderline (Layer 1 score 0.3-0.7)        │
│     - Claude Sonnet checking full context                │
│     - Latency: 500-1500ms                                │
│     - Coverage: ~95% including context-dependent         │
│                                                          │
│   Layer 3: Human Review (async)                          │
│     - Appeals, edge cases                                │
│     - Latency: minutes-hours                             │
│     - Coverage: ~99% (human judgment)                    │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Fast-Fail Pattern

```php
public function moderate(string $content): ModerationResult
{
    // Layer 0: cheapest
    $regexResult = $this->regexModerator->check($content);
    if ($regexResult->definitelyHarmful()) {
        return ModerationResult::block('regex_match', $regexResult);
    }

    // Layer 1
    $classifierResult = $this->classifierEnsemble->check($content);
    if ($classifierResult->maxScore > 0.85) {
        return ModerationResult::block('classifier', $classifierResult);
    }
    if ($classifierResult->maxScore < 0.3) {
        return ModerationResult::allow();
    }

    // Layer 2 — only borderline reaches here
    $llmResult = $this->llmJudge->evaluate($content, $classifierResult);
    if ($llmResult->harmful) {
        return ModerationResult::block('llm_judge', $llmResult);
    }

    if ($llmResult->uncertain) {
        // Layer 3: async human review, in meantime allow with caveat
        HumanReviewJob::dispatch($content, $llmResult);
        return ModerationResult::allowWithFlag();
    }

    return ModerationResult::allow();
}
```

### Cost vs Coverage

Regex layer free çox content-in layer 1/2-yə çatmasının qarşısını alır. Əgər 95% content-i regex + classifier handle edirsə, yalnız 5% LLM judge-ə gedir — massive cost savings.

---

## 6. Latency Budget <a name="latency"></a>

User-facing LLM app-da moderation yan-təsir etməlidir, əsas latency-ə əlavə olmalı, istiqamətləndirməlidir.

### Budget Nümunəsi

```
User request → response SLO: p95 = 3000ms

Breakdown:
  Input moderation:    100ms  (3.3%)
  Retrieval:           300ms  (10%)
  LLM generation:     2200ms  (73%)
  Output moderation:   300ms  (10%)
  Rendering:           100ms  (3.3%)
  TOTAL:              3000ms
```

Moderation budget: 400ms hər iki endpoint üçün birgə.

### Parallel Execution

Input moderation **paralel** ola bilər retrieval ilə:

```php
[$moderationResult, $retrievalResult] = await::all([
    $this->moderator->checkAsync($userInput),
    $this->retriever->fetchAsync($userInput),
]);

if ($moderationResult->blocked) {
    return $this->refusalResponse($moderationResult);
}
// Proceed with LLM using retrievalResult
```

PHP Swoole, ReactPHP, və ya Guzzle async ilə mümkündür. Saf blocking Laravel-də queue-a çıxartmaq lazım gələ bilər.

### Streaming-də Output Moderation

Streaming LLM response real-time yığılır. Moderation:

**Pattern A**: buffer to end, moderate, release. Latency user üçün görünür.
**Pattern B**: streaming chunk-ları incremental moderate, ilk harmful token-da kəs.

```php
public function streamWithModeration(Iterable $stream)
{
    $buffer = '';
    foreach ($stream as $chunk) {
        $buffer .= $chunk;

        // Every N chars, incremental moderate
        if (strlen($buffer) % 500 === 0) {
            $quickCheck = $this->regexModerator->check($buffer);
            if ($quickCheck->definitelyHarmful()) {
                yield '[REFUSAL: Content policy violation]';
                return;
            }
        }
        yield $chunk;
    }

    // Full moderation after stream ends
    $fullCheck = $this->moderator->moderate($buffer);
    if ($fullCheck->blocked) {
        // Log, but response already sent — user saw it
        // This is why Pattern A is sometimes required for sensitive domains
        Log::warning('Post-stream moderation flagged', ...);
    }
}
```

### Budget Enforcement

```php
$timeout = 400; // ms
$result = Timeout::run($timeout, function () use ($content) {
    return $this->moderate($content);
});

if ($result === null) {
    // Moderation timed out — fallback policy
    // Option A: fail open (allow) — risky
    // Option B: fail closed (block) — safer for high-risk domain
    return ModerationResult::block('timeout');
}
```

High-risk domain-lərdə (kids app, medical) **fail-closed**. Low-risk domain-lərdə fail-open + async review.

---

## 7. FP vs FN Tradeoff <a name="fp-fn"></a>

Moderation iki cür error:

- **False Positive (FP)**: zərərsiz content block olunub — user frustrated
- **False Negative (FN)**: zərərli content keçib — legal/reputational risk

Klassifier threshold-dan asılı:

```
threshold = 0.9 → FP az, FN çox
threshold = 0.5 → balanced
threshold = 0.2 → FN az, FP çox
```

### Domain-Based Threshold

| Domain | FP Tolerance | FN Tolerance | Threshold |
|--------|-------------|-------------|-----------|
| Kids platform | High (block more) | Zero | 0.3 |
| Medical bot | Medium | Zero (safety advice) | 0.4 |
| Adult creative platform | Low (less blocking) | Medium | 0.7 |
| B2B enterprise | Medium | Low | 0.5 |
| Gaming chat | Medium | Low | 0.6 |

### Calibration with Real Data

Production log-dan sample 1000 content → human review → ground truth. Sonra:

```
Confusion Matrix at threshold 0.5:
              actual harmful   actual clean
predicted harmful    85 (TP)      15 (FP)     precision 85%
predicted clean      10 (FN)     890 (TN)     recall 89%

At threshold 0.3:
              actual harmful   actual clean
predicted harmful    92 (TP)      40 (FP)     precision 70%
predicted clean       3 (FN)     865 (TN)     recall 97%
```

Business-ə asılı: precision və ya recall prioritet. CSAM üçün recall 100% target (FN buraxılmaz), routine toxicity üçün precision vacib (FP user-i qıcıqlandırır).

### A/B Testing Threshold

Canary: 5% user-ə yeni threshold. FP rate user-də feedback ("why blocked?"), FN rate sampled audit ilə ölç.

---

## 8. CSAM Special Legal Path <a name="csam"></a>

CSAM (Child Sexual Abuse Material) fərqli kateqoriyadır — **zero tolerance + legal reporting obligation**.

### 8.1 Legal Framework

- **ABŞ**: 18 U.S.C. § 2258A — elektron xidmət təchizatçıları CSAM-ı NCMEC-ə (National Center for Missing & Exploited Children) bildirməlidir
- **EU**: Child Sexual Abuse Regulation (proposed 2022, active 2026) — AI systems under mandatory scanning
- **UK**: Online Safety Act 2023
- **Əksər ölkə**: CSAM hazırlamaq, saxlamaq, yaymaq cinayət sayılır — "serving for legitimate purpose" müdafiəsi yoxdur

### 8.2 PhotoDNA

Microsoft tərəfindən hazırlanmış hash-based detection. Məlum CSAM şəkillər üçün robust hash (crop, resize, rotation invariant). Platform photoDNA SDK istifadə edərək uploaded şəkilləri NCMEC hash database-lə müqayisə edir.

- **PhotoDNA Cloud Service**: pulsuz platforms üçün (Microsoft sponsored)
- **API**: `https://api.microsoftmoderator.com/photodna/`
- **Integration**: SDK (C#, Java), REST API

### 8.3 LLM Context

Siz text-based LLM app işlədirsiniz. CSAM hələ də concern-dir:

- User text-də CSAM scenario təsvir edir
- User şəkil yükləyir (multimodal)
- Model generated content-də problemli elementlər (child + sexual context)

### 8.4 Pipeline Architecture

```
┌──────────────────────────────────────────────────────────┐
│           CSAM Detection Pipeline                        │
├──────────────────────────────────────────────────────────┤
│                                                          │
│   User upload (image or text)                            │
│        │                                                 │
│        ▼                                                 │
│   ┌─────────────────┐                                   │
│   │  Image: PhotoDNA│ ──→ hash match?                   │
│   │  hash compute   │      ↓ yes                        │
│   └─────────────────┘      ↓                            │
│        │ no                ↓                            │
│        ▼                   ↓                            │
│   ┌─────────────────┐      ↓                            │
│   │  Classifier:    │      ↓                            │
│   │  sexual+minor   │      ↓                            │
│   └─────────────────┘      ↓                            │
│        │                   ↓                            │
│        ▼                   ↓                            │
│   ┌─────────────────────────────────────┐              │
│   │  IF detected:                       │              │
│   │   1. BLOCK request immediately      │              │
│   │   2. QUARANTINE artifact (isolated  │              │
│   │      bucket, encrypted)             │              │
│   │   3. REPORT to NCMEC via CyberTip   │              │
│   │      (automated API)                │              │
│   │   4. SUSPEND user account           │              │
│   │   5. PRESERVE evidence              │              │
│   │   6. LOG (separate secure log)      │              │
│   │   7. NOTIFY legal team              │              │
│   └─────────────────────────────────────┘              │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### 8.5 Implementation Notes

```php
// app/Services/Moderation/CsamDetector.php

class CsamDetector
{
    public function check(UploadedFile $file): CsamResult
    {
        if (!$file->isImage()) {
            return CsamResult::notApplicable();
        }

        $hash = $this->photoDnaClient->computeHash($file->path());
        $match = $this->photoDnaClient->matchAgainstNcmec($hash);

        if ($match->found) {
            // IMMEDIATE action
            $this->blockUser($file->user_id);
            $this->quarantine($file);
            $this->reportToNcmec($file, $match);
            $this->notifyLegal($file);

            return CsamResult::matched($match);
        }

        // Additional ML classifier for unhashed
        $mlResult = $this->classifier->check($file);
        if ($mlResult->score > 0.9) {
            $this->humanReview($file, $mlResult); // ayrı queue
            return CsamResult::suspicious($mlResult);
        }

        return CsamResult::clean();
    }
}
```

### 8.6 Critical Rules

- **Bu log-lar ayrı retention policy altındadır** — normal log-dan fərqli, legal retain
- **Engineer debug-də CSAM content-ə access almamalıdır** — encrypted, legal-only access
- **Outside consultant (red teamer) CSAM-ı "test etmə"** — heç bir halda
- **False positive də ciddidir** — incorrectly flagged user-ə appeal mechanism
- **Cross-border transfer** — CSAM evidence legal reporting üçün transfer, amma tam fərqli rules

### 8.7 Obligation Summary

Siz AI app operate edirsinizsə və user content qəbul edirsinizsə (hətta text-only):
- NCMEC CyberTipline-a registration (ABŞ)
- PhotoDNA və ya equivalent hash matching
- Detection → reporting workflow
- Legal team engagement
- User notification policy (pending investigation)

Əgər bunu qurmamısınızsa, operational risk yüksəkdir. 1-5M$ fines, criminal exposure kriter.

---

## 9. Age Gating və NSFW <a name="age-gating"></a>

Adult content (NSFW — sexual but legal) CSAM-dan fərqlidir. Qanuni amma yaş məhdudiyyətli.

### 9.1 Age Verification Strategies

- **Self-declaration**: "I am over 18" checkbox — qanuni minimal (ABŞ COPPA 13+)
- **ID verification**: sertifikatlı providers (Veriff, Jumio, Onfido)
- **Credit card check**: kart > 18 sahibi implied
- **Estimation**: face-based age estimation (Yoti, controversial)

EU AI Act high-risk sistem-lərdə ID verification required.

### 9.2 Dual-Endpoint Pattern

Eyni LLM, fərqli endpoint-lər:

```
/api/chat/general         → strict moderation, NSFW blocked
/api/chat/adult           → NSFW allowed, CSAM blocked
                            requires age-verified user session
```

Tolerate NSFW endpoint-ə age verification middleware:

```php
// app/Http/Middleware/RequireAdultVerification.php

public function handle(Request $request, Closure $next)
{
    $user = $request->user();
    if (!$user?->adult_verified_at) {
        return response()->json([
            'error' => 'adult_verification_required',
            'verify_url' => route('verify.adult'),
        ], 403);
    }

    if ($user->adult_verified_at->lt(now()->subYears(2))) {
        // Re-verify every 2 years
        return response()->json([
            'error' => 'reverification_required',
        ], 403);
    }

    return $next($request);
}
```

### 9.3 Moderation Config per Endpoint

```php
// config/moderation.php

return [
    'policies' => [
        'general' => [
            'block_categories' => [
                'hate', 'harassment', 'sexual', 'csam', 'self_harm',
                'violence', 'illicit', 'pii',
            ],
        ],
        'adult' => [
            'block_categories' => [
                'hate', 'harassment', 'csam', 'self_harm',
                'violence_graphic', 'illicit', 'pii',
            ],
            // sexual/non-csam allowed
        ],
        'kids' => [
            'block_categories' => [
                'hate', 'harassment', 'sexual', 'csam', 'self_harm',
                'violence', 'illicit', 'pii', 'scary',
                'complex_topics_unsafe_for_kids',
            ],
            'extra_lenient_refusal' => true,
        ],
    ],
];
```

### 9.4 Age Gating Pitfalls

- **Self-declaration easily bypassed**: legally minimum, not defense
- **ID verification privacy**: PII storage → GDPR concerns
- **False positives in age estimation**: dark skin tone, female — historically biased
- **Cross-border**: US minimum 13 (COPPA), UK 18, EU varies

---

## 10. Regional Tərtiblər <a name="regional"></a>

Content moderation universal deyil — region-a görə fərqli standartlar.

### 10.1 EU

- Strictest hate speech laws (Germany NetzDG, France)
- DSA transparency + audit
- GDPR data handling
- CSAM regulation 2026

Content: politik satire geniş tolerate, nazi symbolik tamamilə block

### 10.2 Middle East (UAE, KSA)

- Religious content strictness (Islam insults)
- Sexual content heavily restricted
- LGBTQ+ content illegal (UAE, KSA)
- Political content (royal family criticism) illegal

Platform UAE-də işlətdikdə: ayrı moderation policy layer.

### 10.3 APAC

- China: political content heavily restricted, no end-to-end encryption discussions
- Singapore: hate speech strict
- Indonesia: blasphemy laws
- India: new IT Rules 2021 — proactive monitoring

### 10.4 Implementation: Geofenced Policy

```php
// app/Services/Moderation/RegionalPolicy.php

class RegionalPolicy
{
    public function resolvePolicy(User $user): array
    {
        $country = $user->country_code ?? $this->geoipLookup(request()->ip());

        $basePolicy = config('moderation.policies.general');

        $regionalOverrides = config("moderation.regional.$country", []);

        return array_merge_recursive($basePolicy, $regionalOverrides);
    }
}
```

Config:

```php
// config/moderation.php

'regional' => [
    'DE' => [
        'block_categories' => ['nazi_symbols', 'holocaust_denial'],
        'extra_strict' => ['hate'],
    ],
    'AE' => [
        'block_categories' => ['lgbtq_content', 'religion_criticism'],
    ],
    'CN' => [
        'block_categories' => ['political_dissent', 'tiananmen'],
    ],
    'US' => [
        'extra_lenient' => ['political_speech'],
    ],
],
```

Ehtiyatlı olmaq: **Avropada** CN-yə xüsusi censorship tətbiq etmək reputational risk. Bəzi platformalar "operate or don't operate" qərar verir, middle ground-u qəbul etmir.

---

## 11. Laravel Middleware Pattern <a name="laravel"></a>

End-to-end integration nümunəsi:

### 11.1 Pre-Moderation Middleware

```php
// app/Http/Middleware/PreModerateInput.php

class PreModerateInput
{
    public function __construct(
        private ModerationPipeline $pipeline,
        private RegionalPolicy $policy,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $content = $request->input('message');
        if (!$content) return $next($request);

        $user = $request->user();
        $policy = $this->policy->resolvePolicy($user);

        $result = $this->pipeline->moderate($content, [
            'user_id' => $user?->id,
            'checkpoint' => 'input',
            'policy' => $policy,
        ]);

        if ($result->blocked) {
            ModerationLog::create([
                'user_id' => $user?->id,
                'checkpoint' => 'input',
                'content_hash' => sha1($content),
                'content_preview' => substr($content, 0, 200),
                'category' => $result->category,
                'severity' => $result->severity,
                'action' => 'blocked',
                'layer' => $result->triggeredLayer,
                'correlation_id' => $request->attributes->get('correlation_id'),
            ]);

            return response()->json([
                'error' => 'content_policy_violation',
                'category' => $result->category,
                'appeal_url' => route('moderation.appeal', ['log_id' => $log->id]),
            ], 400);
        }

        if ($result->flagged) {
            $request->attributes->set('moderation_flags', $result->flags);
        }

        return $next($request);
    }
}
```

### 11.2 Post-Moderation (Output)

```php
// app/Services/AI/ModeratedChat.php

public function chat(User $user, string $message): array
{
    // Assume pre-moderation already passed (middleware)

    $response = $this->gateway->chat(...);

    $outputResult = $this->moderationPipeline->moderate($response->text, [
        'user_id' => $user->id,
        'checkpoint' => 'output',
    ]);

    if ($outputResult->blocked) {
        // Model generated harmful content — replace with refusal
        ModerationLog::create([
            'user_id' => $user->id,
            'checkpoint' => 'output',
            'content_hash' => sha1($response->text),
            'category' => $outputResult->category,
            'action' => 'blocked',
        ]);

        return [
            'text' => 'I cannot provide that content. Please rephrase your request.',
            'refused' => true,
        ];
    }

    return ['text' => $response->text];
}
```

### 11.3 Tool Output Moderation

```php
// app/Services/AI/Tools/WebSearchTool.php

public function execute(array $args): array
{
    $results = $this->searchEngine->search($args['query']);

    // Moderate each result before returning to model
    $safeResults = [];
    foreach ($results as $result) {
        $check = $this->moderator->moderate($result['snippet'], [
            'checkpoint' => 'tool_output',
        ]);

        if ($check->blocked) {
            Log::info('Tool output filtered', [
                'tool' => 'web_search',
                'url' => $result['url'],
                'category' => $check->category,
            ]);
            continue;
        }

        // Strip potential injection — wrap in untrusted tags
        $result['snippet'] = "<untrusted>{$result['snippet']}</untrusted>";
        $safeResults[] = $result;
    }

    return $safeResults;
}
```

### 11.4 Async Audit Moderation

Real-time pipeline-ə əlavə olaraq offline audit:

```php
// Nightly job
class ModerationAuditJob implements ShouldQueue
{
    public function handle()
    {
        $logs = ModerationLog::where('created_at', '>=', now()->subDay())
            ->where('action', 'allowed')
            ->inRandomOrder()
            ->limit(1000)
            ->get();

        foreach ($logs as $log) {
            // Deeper check with Llama Guard 3
            $deeperCheck = $this->llamaGuard->check($log->content);
            if ($deeperCheck->harmful) {
                event(new MissedModerationDetected($log, $deeperCheck));
            }
        }
    }
}
```

Missed detection patterns → moderation policy update.

---

## 12. Moderation Log Retention <a name="retention"></a>

Moderation log-lar iki təzyiq arasındadır:

1. **Retain uzun müddət**: audit, regulatory, pattern analysis
2. **Retain qısa müddət**: GDPR right to erasure, privacy

### 12.1 Retention Policy Matrix

| Log Type | Retention | Justification |
|----------|-----------|---------------|
| Blocked content (allowed = false) | 180 gün | Audit trail, appeals |
| Allowed but flagged | 90 gün | Pattern analysis |
| CSAM-related | 7 il minimum | Legal requirement |
| User appeals | 2 il | Dispute resolution |
| Model training-ə daxil edilə biləcək | Yalnız user consent ilə | GDPR |

### 12.2 Tiered Storage

- **Hot (0-30 gün)**: Postgres, fast query
- **Warm (30-180 gün)**: S3, compressed
- **Cold (180+ gün)**: Glacier, retrieval izin

### 12.3 Erasure Request Handling

User GDPR erasure tələbi edir:

```php
class HandleErasureRequest implements ShouldQueue
{
    public function handle(ErasureRequest $request)
    {
        $userId = $request->user_id;

        // Moderation log-lar üçün retention override
        $logs = ModerationLog::where('user_id', $userId)->get();

        foreach ($logs as $log) {
            if ($log->isCsamRelated()) {
                // Legal override: CSAM evidence retained
                Log::info('Erasure blocked for CSAM log', ['log_id' => $log->id]);
                continue;
            }

            if ($log->isLegalHold()) {
                Log::info('Erasure blocked for legal hold', ['log_id' => $log->id]);
                continue;
            }

            // Pseudonymize: user_id → hash, content → redacted
            $log->update([
                'user_id' => null,
                'user_hash' => hash('sha256', $userId . config('app.erasure_salt')),
                'content_preview' => '[ERASED]',
                'content_hash' => '[ERASED]',
                'erased_at' => now(),
            ]);
        }
    }
}
```

---

## 13. Human Review Queue <a name="review"></a>

Borderline və appeals üçün human review lazımdır.

### 13.1 Queue Architecture

```php
// app/Models/ReviewTask.php

class ReviewTask extends Model
{
    protected $casts = [
        'context' => 'array',
        'classifier_scores' => 'array',
    ];

    // Fields:
    // - content_hash, content_preview
    // - classifier_scores (all layer scores)
    // - priority (low, medium, high, critical)
    // - queue_type (moderation_borderline, appeal, false_positive_report)
    // - assigned_to (reviewer user_id)
    // - sla_deadline
    // - decision (allow, block, uncertain)
    // - reviewer_notes
}
```

### 13.2 SLA by Priority

| Priority | SLA | Examples |
|----------|-----|----------|
| Critical | 1 hour | CSAM classifier hit, imminent threat |
| High | 4 hours | Severe harassment, self-harm intent |
| Medium | 24 hours | Borderline hate, ambiguous sexual |
| Low | 7 days | Routine appeals, mild profanity |

### 13.3 Reviewer Interface

Filament-based dashboard:

```php
// app/Filament/Resources/ReviewTaskResource.php

class ReviewTaskResource extends Resource
{
    protected static ?string $model = ReviewTask::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            BadgeColumn::make('priority')->colors([
                'danger' => 'critical',
                'warning' => 'high',
                'primary' => 'medium',
                'secondary' => 'low',
            ]),
            TextColumn::make('queue_type'),
            TextColumn::make('content_preview')->limit(100),
            TextColumn::make('sla_deadline')->since(),
            BadgeColumn::make('status'),
        ])->actions([
            Action::make('review')
                ->form([
                    Radio::make('decision')->options([
                        'allow' => 'Allow',
                        'block' => 'Block',
                        'escalate' => 'Escalate to senior reviewer',
                    ]),
                    Textarea::make('reviewer_notes'),
                ])
                ->action(function ($record, $data) {
                    $record->update([
                        'decision' => $data['decision'],
                        'reviewer_notes' => $data['reviewer_notes'],
                        'reviewed_by' => auth()->id(),
                        'reviewed_at' => now(),
                    ]);
                    event(new ReviewDecisionMade($record));
                }),
        ])->filters([
            SelectFilter::make('priority'),
            SelectFilter::make('queue_type'),
        ]);
    }
}
```

### 13.4 Reviewer Well-Being

CSAM, extreme violence content review-u edən insan psychological impact yaşayır:

- Short shifts (2 saat maximum)
- Mandatory breaks
- Mental health support (therapy reimbursement)
- Rotation (no reviewer stuck on hardest queue)
- Industry standards: ESAW (EU Online Safety Act Workers), survivors network

Avtomatlaşdırma maksimum — human yalnız ən çətin hallarda.

---

## 14. Appeals Process <a name="appeals"></a>

User content block olunub — appeal mexanizmi DSA, AI Act məcburiyyəti.

### 14.1 Appeal Flow

```
User content blocked
   ↓
Notification: "Your content was blocked. Reason: [category].
               Appeal here: [link]"
   ↓
User submits appeal with justification
   ↓
ReviewTask created (priority = medium, queue = appeal)
   ↓
Human reviewer within SLA
   ↓
Decision: uphold block / overturn (allow)
   ↓
Notify user of outcome
   ↓
If overturned:
   - Update content_policy_examples (training data)
   - Model/classifier re-tuning input
```

### 14.2 Appeal Template

```php
// Appeal submission form

class AppealForm extends Form
{
    public function schema(): array
    {
        return [
            Select::make('reason')->options([
                'false_positive' => 'This is a false positive',
                'context_missing' => 'Context was missing',
                'dispute_policy' => 'I dispute the policy',
            ]),
            Textarea::make('justification')->required()->minLength(50),
        ];
    }
}
```

### 14.3 Transparency in Appeals

User-ə göstərilən:
- What was blocked (content preview)
- Why (category)
- Estimated review time (based on SLA)
- Reviewer outcome + reasoning

Black-box "blocked, no explanation" → DSA violation in EU.

---

## 15. Transparency Reports <a name="transparency"></a>

EU DSA, UK Online Safety Act şirkətləri transparency report publish etməyə məcbur edir. Quarterly, content removals + category + appeal outcomes.

### 15.1 Report Template

```
Transparency Report Q1 2026
============================

Content Actions:
  Total requests moderated:    12,450,000
  Total blocked:                   142,000 (1.14%)

By Category:
  Harassment              45,000
  Hate                    30,000
  Sexual (non-CSAM)       12,000
  CSAM flagged                15 → reported to NCMEC
  Self-harm                8,000
  Violence                18,000
  PII-triggered           15,000
  Other                   14,000

Appeals:
  Total appeals submitted:    4,200
  Upheld (block confirmed):   3,100 (74%)
  Overturned (allowed):       1,000 (24%)
  Pending:                      100

Response Time:
  Median decision time:       6 hours
  SLA compliance:             96%

Law Enforcement Requests:
  Received:  23
  Complied:  20 (3 denied for insufficient legal basis)
```

### 15.2 Generation Query

```php
// app/Console/Commands/GenerateTransparencyReport.php

public function handle()
{
    $quarter = request('quarter', now()->quarter);
    $year = request('year', now()->year);
    $start = Carbon::create($year)->addQuarters($quarter - 1);
    $end = (clone $start)->addQuarter();

    $data = [
        'period' => "$year Q$quarter",
        'total_moderated' => ModerationLog::whereBetween('created_at', [$start, $end])->count(),
        'blocked' => ModerationLog::whereBetween('created_at', [$start, $end])
            ->where('action', 'blocked')->count(),
        'by_category' => ModerationLog::whereBetween('created_at', [$start, $end])
            ->where('action', 'blocked')
            ->groupBy('category')
            ->selectRaw('category, count(*) as count')
            ->get(),
        'appeals' => $this->appealStats($start, $end),
        'sla' => $this->slaStats($start, $end),
        'law_enforcement' => LawEnforcementRequest::whereBetween('created_at', [$start, $end])->stats(),
    ];

    $html = view('transparency.report', $data)->render();
    Storage::put("transparency/report-$year-Q$quarter.html", $html);
    $this->info("Report generated");
}
```

---

## 16. Anti-Pattern-lər <a name="anti"></a>

### Anti-Pattern 1: "Provider refusal yetər"

Anthropic/OpenAI-nın built-in refusal güclüdür, amma:
- Silent update-lərdə davranış dəyişir
- Jailbreak-də bypass olur
- Sizin domain-specific policy-ni bilmir (kids platform vs general)
- Moderation metrics yoxdur (observability sıfır)

**Həqiqət**: refusal built-in + ayrıca moderation layer BİRLİKDƏ. Defense-in-depth.

### Anti-Pattern 2: "Single Provider"

OpenAI Moderation-u çağırırsınız, OpenAI API down olur — sizin moderation da işləmir. **Ensemble** — iki-üç provider, local fallback.

### Anti-Pattern 3: Output Moderation Unutmaq

Input-u moderate edirsiniz, model cavabını user-ə birbaşa verirsiniz. Model hallucinate edir, harmful content çıxardır — siz responsible-siz. **Output moderation məcburidir**.

### Anti-Pattern 4: CSAM Ad-hoc Handling

Kimsə "sadəcə block et" deyir, pipeline yoxdur. NCMEC reporting obligation miss olur. **Day 1-dən struktur**: PhotoDNA + reporting + legal + retention.

### Anti-Pattern 5: Log-ları Normal Retain

Moderation log-lar 10 il-dir retain olunur (engineer "just in case" dedi). GDPR erasure tələbləri həll olunmur. Regulatory fines. **Retention policy defined və enforced**.

### Anti-Pattern 6: "Human review sistemimiz yoxdur"

Appeals yoxdur, borderline tamamilə ML-ə güvənilir. Legal (DSA) violation, user frustration. **Human review SLA-larla məcburidir**.

### Anti-Pattern 7: Reviewer Well-Being Ignorla

CSAM/gore content review edən insan 6 ay sonra burnt out. Legal risk (worker health claims). **Rotation + support + short shifts**.

### Anti-Pattern 8: "Regional policy — biz global eyni policy işlədirik"

Almaniya-da nazi symbolism tolerate edilir → €30M fine. UAE-də LGBTQ+ content allow → operation banned. **Regional overlays** məcburidir.

### Anti-Pattern 9: "Transparency report publish etmirik"

EU DSA required. Ignore → €6M+ fine. **Quarterly minimum, automated generation**.

### Anti-Pattern 10: Streaming-də Output Moderation Skip

Stream-in başında harmful content göndərib sonra "oops, I shouldn't have said that" — user artıq gördü. **Incremental stream moderation** və ya buffer-to-end yüksək-riskli domain üçün.

---

## Xülasə

Content moderation 2026-da **legal, operational, and ethical mandate** təşkil edir.

Əsas mesajlar:

1. **Moderation legally məcburidir** — DSA, AI Act, regional laws
2. **Taxonomy aydın** — hate, harassment, sexual, CSAM, self-harm, violence, illicit, PII, misinfo
3. **4 checkpoint** — input, retrieval, tool output, output
4. **Stacked approach** — regex → classifier → LLM judge → human
5. **Provider ensemble** — OpenAI + Google + local (Detoxify/Llama Guard)
6. **Latency budget <400ms** — paralel execution, incremental streaming
7. **CSAM ayrı path** — PhotoDNA, NCMEC, legal retention, zero tolerance
8. **Age gating** — dual-endpoint, verification, regional variance
9. **Regional policy overlay** — EU/ME/APAC fərqli standartlar
10. **Retention policy** — tiered, GDPR-compliant, legal hold exceptions
11. **Human review queue** — SLA by priority, well-being support
12. **Appeals məcburidir** — DSA, transparent reasoning
13. **Transparency reports** — quarterly, automated generation
14. **File 43 ilə** — refusal policies; **file 53 ilə** — PII redaction (bu faylın complement-i)

Sonrakı fayl (63) — AI Governance və compliance: EU AI Act, GDPR, SOC 2, ISO 42001.
