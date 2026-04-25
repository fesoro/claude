# AI Feature-in Economics — Vahid İqtisadiyyat və Qiymət Qoyma (Senior)

> Hədəf auditoriyası: AI feature-ləri qurub satan senior PHP/Laravel developer-lər, product manager-lər və founder-lər. "Nə qədər başa gəlir?", "Neçə manata satım?", "Kim qazanc gətirir, kim batırır?" suallarına cavab axtaranlar.

> Tarix: 2026-04-21. Bütün qiymətlər bu tarixə görə. Modellər: `claude-sonnet-4-5`, `claude-haiku-4-5`.

---

## Mündəricat

1. [Niyə Vahid İqtisadiyyat AI-da Fərqlidir](#why-unit-economics-differ)
2. [COGS per AI Call — Əsas Formula](#cogs-per-ai-call)
3. [Token Bazasının Qurulması](#token-baseline)
4. [Margin per User — Addım-addım Hesablama](#margin-per-user)
5. [Break-even Token Analizi](#break-even-tokens)
6. [Pricing Modelləri — Included vs Metered vs Credits](#pricing-models)
7. [Worked Example — Support Bot $29/ay](#support-bot-example)
8. [Worked Example — Writing Assistant $20/ay](#writing-assistant-example)
9. [Worked Example — Code Review Bot $0.10/PR](#code-review-example)
10. [Free Tier Dizaynı — Cəlb Etmək amma Batmamaq](#free-tier-design)
11. [Real Bazar Müqayisəsi — Copilot/Cursor/Fin/Notion AI](#real-market-comparison)
12. [Cost Attribution — Spreadsheet Modelləri](#cost-attribution)
13. [Keyfiyyət-Xərc Tradeoff-u](#quality-cost-tradeoff)
14. [LTV:CAC AI COGS ilə](#ltv-cac)
15. [Qərar Ağacları və Cheat Sheet](#decision-trees)

---

## Niyə Vahid İqtisadiyyat AI-da Fərqlidir

Ənənəvi SaaS-da hər əlavə istifadəçinin marginal xərci demək olar ki, sıfırdır. Bir Laravel app-a 1 istifadəçi əlavə etsəz, CPU-da 0.001 sent fərq gedir. AI-da isə tam əks: **hər istifadəçi, hər söhbət, hər prompt real COGS yaradır**.

Bu, SaaS biznesinin fiziki strukturunu dəyişir:

| Parametr                 | Ənənəvi SaaS     | AI SaaS                     |
|--------------------------|------------------|-----------------------------|
| Marginal xərc            | ~$0              | $0.01 - $10 (istifadəyə görə)|
| Gross margin              | 80-90%           | 30-70% (istifadəyə çox həssas)|
| Power user-lərin riski   | Aşağı            | Yüksək (uc case-lər batırır)|
| Free tier abuse          | Aşağı            | Yüksək (tokeni yeyirlər)    |
| Fiyatlandırma simplicity  | Flat ok          | Flat = təhlükəli            |

### Mental Model: AI Feature = Utility + Platform

AI feature-i iki qatdan ibarətdir:
- **Utility qatı**: model çağırışı, real zamanda token yanır (elektrik kimi)
- **Platform qatı**: UI, auth, inteqrasiyalar, storage (ənənəvi SaaS)

Gross margin = Platform margin (yüksək) × Utility margin (dəyişkən).

Əgər utility qatı gross revenue-nin 60%-ni yeyirsə, siz artıq SaaS biznesi yox, **əlavə dəyərli utility reseller** olursunuz. Bu, yanlış deyil, ancaq qiymət strategiyanızı buna görə qurmalısınız.

---

## COGS per AI Call — Əsas Formula

Bir API çağırışının cost-unu hesablayan formula çox sadədir:

```
COGS_call = (input_tokens × input_price_per_M / 1_000_000)
          + (output_tokens × output_price_per_M / 1_000_000)
          + (cached_tokens × cached_price_per_M / 1_000_000)
          + overhead (retries, logging, embeddings)
```

### Aprel 2026 Qiymətləri (1M token başına)

| Model              | Input   | Output  | Cached Input | Qeyd                         |
|--------------------|---------|---------|--------------|------------------------------|
| claude-sonnet-4-5  | $3.00   | $15.00  | $0.30        | Balanced workhorse           |
| claude-haiku-4-5   | $0.25   | $1.25   | $0.025       | 12x ucuz, 80% keyfiyyət      |
| voyage-3 (embed)   | $0.12   | —       | —            | 1M token embedding           |
| text-embedding-3-sm| $0.02   | —       | —            | OpenAI, ucuz embedding       |

### Praktiki Nümunə — Bir Support Sorğusu

İstifadəçi sualı: 200 token. System prompt + FAQ kontekst: 3,500 token. Cavab: 400 token.

```
Haiku ilə:
  input:  (200 + 3500) × $0.25 / 1,000,000 = $0.000925
  output: 400 × $1.25 / 1,000,000 = $0.0005
  total:  $0.001425 (~0.14 sent)

Sonnet ilə:
  input:  3700 × $3.00 / 1,000,000 = $0.0111
  output: 400 × $15.00 / 1,000,000 = $0.006
  total:  $0.0171 (~1.71 sent)

Sonnet-in cost-u Haiku-nun 12x-i.
```

### Prompt Cache-i Necə Hər Şeyi Dəyişir

Eyni system prompt (3500 token FAQ) eyni istifadəçi sessiyasında 5 dəfə istifadə olunursa:

```
Cache-siz (Sonnet):
  5 × ($0.0171) = $0.0855

Cache-li (Sonnet, 90% cache hit):
  1-ci çağırış:   $0.0171 (cache write)
  2-5-ci çağırış: 4 × [(3500 × $0.30/M) + (200 × $3/M) + (400 × $15/M)]
                = 4 × ($0.00105 + $0.0006 + $0.006)
                = 4 × $0.00765 = $0.0306
  Toplam:         $0.0477 (44% qənaət)
```

**Qayda**: əgər eyni context 2-dən çox dəfə istifadə olunursa, cache aktivləşdir.

---

## Token Bazasının Qurulması

Biznes modeli qurmadan əvvəl **3 ədəd** bilməlisən:

1. **İstifadəçi başına orta sessiya sayı (aylıq)** — engagement
2. **Sessiya başına orta AI çağırış sayı** — depth
3. **Çağırış başına orta token** — complexity

Bu üç ədədi bilmirsənsə, pricing qurma. Əvvəlcə ölç.

### Baseline Ölçmə Checklist (PHP-də)

```php
// app/Services/AI/UsageTracker.php
class UsageTracker
{
    public function track(
        int $userId,
        string $feature,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cachedTokens = 0,
    ): void {
        DB::table('ai_usage')->insert([
            'user_id'        => $userId,
            'feature'        => $feature,
            'model'          => $model,
            'input_tokens'   => $inputTokens,
            'output_tokens'  => $outputTokens,
            'cached_tokens'  => $cachedTokens,
            'cost_cents'     => $this->calculateCostCents(
                $model, $inputTokens, $outputTokens, $cachedTokens,
            ),
            'created_at'     => now(),
        ]);
    }

    private function calculateCostCents(
        string $model, int $in, int $out, int $cached,
    ): int {
        $prices = [
            'claude-sonnet-4-5' => ['in' => 300, 'out' => 1500, 'cache' => 30],
            'claude-haiku-4-5'  => ['in' => 25,  'out' => 125,  'cache' => 2.5],
        ]; // sent/1M token
        $p = $prices[$model];
        $cost = ($in * $p['in'] + $out * $p['out'] + $cached * $p['cache'])
              / 1_000_000;
        return (int) round($cost);
    }
}
```

### Aylıq İstifadəçi Profili Cədvəli

Real istifadəçilərin 80/20 qaydası çox ağır keçir. Məsələn, Intercom Fin-də:

| Percentile | Sessiyalar/ay | Çağırış/sessiya | Token/çağırış |
|------------|---------------|-----------------|----------------|
| P50 (median) | 4          | 3               | 2,000          |
| P75         | 12            | 6               | 2,500          |
| P90         | 30            | 10              | 3,500          |
| P95         | 60            | 15              | 5,000          |
| P99 (top power)| 200        | 25              | 8,000          |

**Median istifadəçi = 24,000 token/ay. P99 = 40M token/ay (1666x!).**

Əgər flat pricing qurursansa, P99 istifadəçiləri həmişə zərərlə xidmət edirsən.

---

## Margin per User — Addım-addım Hesablama

Formula:

```
Margin_user = Revenue_user - COGS_user - Allocated_overhead

COGS_user = Σ(tokens_i × price_i) + embeddings + vector_ops + retries
```

### Addım 1: Revenue-ni Təmizlə

$29/ay planında:
- Stripe fee: 2.9% + $0.30 = $1.14
- Refund/chargeback reserve: 2%  = $0.58
- **Net revenue: $27.28**

### Addım 2: AI COGS-u Topla

Median istifadəçi (24k input + 6k output token Sonnet):
```
Input:  24,000 × $3.00 / 1M  = $0.072
Output: 6,000  × $15.00 / 1M = $0.090
Total:  $0.162/ay
```

### Addım 3: İnfrastruktur Overhead

Hər istifadəçiyə allocated:
- Database (Postgres + pgvector): $0.15
- Queue workers + Redis: $0.10
- Logging/monitoring: $0.05
- **Infra total: $0.30**

### Addım 4: Gross Margin

```
Net revenue:     $27.28
AI COGS:          $0.162
Infra:            $0.30
Support cost:     $0.50  (CS labor allocated)
Total COGS:       $0.962
Gross margin:    $26.318 (96.5%)
```

Yaxşı görünür? Amma bu **median istifadəçi**-dir. P99 üçün:

```
Input:  300,000 × $3 / 1M  = $0.90
Output: 80,000  × $15 / 1M = $1.20
AI COGS: $2.10/ay → hələ yaxşı (92% margin)
```

Amma əgər P99 Opus-a (yoxdur artıq, amma fərz et Opus 4.6 = $15/$75) yönlənirsə:
```
AI COGS: ~$10/ay → margin 63%-ə düşür
```

---

## Break-even Token Analizi

**Break-even sual**: "$29/ay satıram, neçə token-dən sonra batmağa başlayıram?"

Formula:
```
break_even_tokens = (Revenue - FixedCosts) / price_per_token

Sonnet, blended 80% input / 20% output:
  blended_price = 0.8 × $3 + 0.2 × $15 = $5.40 / 1M token
  = $0.0000054 / token

$29 planında, fixed costs $0.80 varsa:
  budget = $29 - $0.80 - $1.14 stripe = $27.06
  break_even = 27.06 / 0.0000054 = 5,011,111 token/ay
  ≈ 5M token/ay
```

### Gerçəyin Tətbiqi

| Plan           | Budget (AI üçün) | Sonnet break-even | Haiku break-even |
|----------------|------------------|-------------------|------------------|
| $10/ay          | $7                | 1.3M token        | 14M token        |
| $29/ay          | $27               | 5M token          | 54M token        |
| $99/ay          | $92               | 17M token          | 184M token       |
| $299/ay         | $277              | 51M token          | 554M token       |

**Praktik qayda**: hədəf istifadə break-even-in **maksimum 30%**-i olmalıdır ki, marketing, support və biznes istəyi üçün margin qalsın.

---

## Pricing Modelləri — Included vs Metered vs Credits

### Model 1: Included (Unlimited)

Istifadə məhdudiyyətsiz, flat ödəniş.

```
Nümunə: Notion AI $10/user/ay "unlimited" AI
```

**Üstünlüklər**:
- Sadə messaging
- Müqayisə asan
- Istifadəçi AI istifadəsindən çəkinmir

**Çətinliklər**:
- Power user-lər batırır
- Soft cap şərtləri gizli lazımdır
- Fraud/abuse riski

**Nə vaxt işləyir**: istifadə aşağı variance olanda (Notion kimi — çoxları gündə 5 dəfə yazır).

### Model 2: Metered (Pay-per-use)

Hər istifadənin bir qiyməti var.

```
Nümunə: Vercel v0 API, OpenAI API birbaşa
Üstünlükləri: margin həmişə qorunur
Çətinliyi: istifadəçi "meter trevogası" yaşayır
```

**Nə vaxt işləyir**: developer-tool-larda, B2B infra-da, dəqiq cost attribution lazımdır.

### Model 3: Credits (Hybrid)

Hər ay X kredit verilir, power user-lər top-up alır.

```
Nümunə: Cursor 500 "premium request"/ay, sonrası $0.04/request
         Perplexity Pro 5x/gün complex search
         Claude.ai limitlər (həftəlik session quota)
```

**Üstünlüklər**:
- Müəyyən upside (flat plan kimi hiss edir)
- Abuse qarşısı alınır
- Upsell mexanizmi var

**Çətinliklər**:
- "Credit" UX başa düşülməyə bilər
- Credit nə qədər? 1 kredit = 1 çağırış? 1k token? 1 mesaj?

### Qiymət Modelini Seçmə Qərar Ağacı

```
İstifadə variance P95/P50 > 10x?
├── BƏLİ → Credits və ya Metered
│         └── B2C? → Credits (UX sadəliyi)
│             B2B? → Metered (cost attribution)
└── XEYR → Included (flat) ok
          └── Amma soft cap şərtlərini siyasətdə yaz
```

### Hybrid Pricing — Real Modellər

**Cursor (2026 yanvar):**
- $20/ay → 500 premium request (Sonnet/Opus)
- 500-dən sonra $0.04/request və ya Haiku-ya auto-fallback
- Unlimited "cmd+K" (Haiku)

**Intercom Fin:**
- $0.99 per resolution (təmiz metered)
- Resolve olmasa, ödəniş yoxdur (performance-based)

**Notion AI:**
- $10/user/ay (included), 200 "block" action limit
- 200-dən sonra yavaşlama yox, sadəcə "soft throttle"

**GitHub Copilot:**
- $10/ay individual (subsidized!)
- $19/ay business, $39/ay enterprise
- Unlimited completion (Microsoft/GitHub cost-u udur)

**Perplexity Pro:**
- $20/ay, 300 "Pro search"/gün
- Hər Pro search ~3-5 AI çağırışı (reasoning + web + synthesis)

---

## Worked Example — Support Bot $29/ay

Ssenariy: kiçik biznes üçün AI support bot. Customer site-ına embed olunur, müştəri suallarına cavab verir.

### Tələblər
- Per-tenant (biznes) $29/ay
- Tenant-in ~5 workspace user-i, ~500 son-müştəri/ay
- Tenant-in FAQ/knowledge base: 50k token
- Ortalama müştəri sorğusu: 150 token, cavab: 300 token

### COGS Hesablaması

**Month 1 — Naive yanaşma (hər sorğuda full FAQ):**
```
Çağırış başına:
  input:  50,000 + 150 = 50,150 tokens × $3/M = $0.15045
  output: 300 × $15/M  = $0.0045
  total:  $0.15495/sorğu

Aylıq (500 sorğu):
  500 × $0.15495 = $77.48/tenant

Revenue: $27.28 (net)
COGS: $77.48
Margin: -$50.20 → HƏR TENANT $50 BATIRIR
```

**Month 2 — Prompt cache:**
```
Cache setup:
  50,000 tokens cache write: 1-ci sorğu tam qiymət ($0.15045)
  Növbəti: input 50,000 × $0.30/M = $0.015
           + 150 × $3/M = $0.00045
           + 300 × $15/M = $0.0045
           = $0.01995/sorğu

Aylıq:
  $0.15045 (1 write) + 499 × $0.01995 = $10.11/tenant

Margin: $27.28 - $10.11 = $17.17 (63% margin) → KARLIDIR
```

**Month 3 — Model routing (70% Haiku):**
```
Haiku 70%:
  input:  50,150 × $0.25/M = $0.01254
  output: 300 × $1.25/M   = $0.000375
  per call: $0.01291 (Haiku)
  + cache: əvvəl hesabladığımız kimi sonrakı çağırışlarda cache hit
  Haiku + cache: ~$0.00168/call

Sonnet 30%:
  $0.01995/call (cache ilə)

Blended (500 call):
  350 Haiku × $0.00168 = $0.588
  150 Sonnet × $0.01995 = $2.99
  Cache write (~1): $0.15
  Total: $3.73/tenant

Margin: $27.28 - $3.73 = $23.55 (86% margin)
```

### Neçə Söhbətdən Sonra Batırır?

Break-even (Naive Sonnet, cache-siz):
```
budget = $27.28 (net revenue) - $1 infra = $26.28
cost per call = $0.15495
break-even calls = 26.28 / 0.15495 = 169 call/ay
```

Təklif olunur "unlimited" amma həqiqətdə **170+ call-dan sonra batır**.

### Pricing Qərarları

Bu analiza görə 3 variant var:

1. **$29 unlimited, amma 500 soft cap**: Haiku fallback + cache = rahat margin
2. **$19/ay 100 resolution included, sonra $0.15/resolution**: metered upside
3. **$0.99/resolution flat (Intercom stili)**: performance-based

---

## Worked Example — Writing Assistant $20/ay

Ssenariy: Notion/Google Docs stili yazı asistenti. Rewrite, expand, summarize.

### Tələblər
- $20/ay individual
- İstifadəçi orta 30 generation/ay (ölçülüb)
- P95: 200 gen/ay
- P99: 500 gen/ay
- Per-generation: 1500 input + 500 output

### Cost Profili

```
Sonnet base cost:
  input:  1500 × $3/M = $0.0045
  output: 500 × $15/M = $0.0075
  per gen: $0.012

Haiku:
  input:  1500 × $0.25/M = $0.000375
  output: 500 × $1.25/M = $0.000625
  per gen: $0.001 (12x ucuz!)
```

### Margin by Percentile

Sonnet-only:
| Percentile | Generations | Cost    | Revenue $18.86 (net) | Margin  |
|------------|-------------|---------|----------------------|---------|
| P50        | 30          | $0.36   | $18.86               | 98%     |
| P90        | 150         | $1.80   | $18.86               | 90%     |
| P95        | 200         | $2.40   | $18.86               | 87%     |
| P99        | 500         | $6.00   | $18.86               | 68%     |
| P99.9      | 2000        | $24.00  | $18.86               | -27% (ZƏRƏR) |

P99.9 gerçəkdə var (1 bot/skripter 5000 gen edir).

### Strategiya

1. **Soft cap 500 gen/ay**, sonra warning
2. **1000-dən sonra throttle** (2 saniyə wait)
3. **2000-dən sonra hard block**, upgrade təklif

### Notion AI-nın Real Strategiyası (2026 məlumat)

- Individual $10/ay (unlimited)
- AI action (Q&A, summarize, etc.) + məhsul məhəbbət dəyəri
- Power user-lərin çoxu artıq $20 Plus planındadır (başqa feature-lərə görə)
- AI cost tam plan qiymətindən yığılır, flat "sigorta"

**Güclü tərəfi**: AI tək başına qazanmır, platform retention-ı artırır. Lifetime value ilə hesabla.

---

## Worked Example — Code Review Bot $0.10/PR

Ssenariy: GitHub/GitLab-a inteqrasiya olunmuş AI code reviewer.

### Tələblər
- Per-PR $0.10 (B2B metered)
- Orta PR: 500 sətr dəyişiklik
- Token: 8,000 input (diff + context) + 1,500 output (review)

### Cost

**Sonnet ilə:**
```
input:  8,000 × $3/M   = $0.024
output: 1,500 × $15/M  = $0.0225
per PR: $0.0465

Revenue: $0.10
Margin:  $0.0535 (53%)
```

**Strategiya — Multi-pass Pipeline:**

```
Pass 1: Haiku — "Is this PR worth deep review?" (classifier)
  500 input + 50 output = $0.000125 + $0.0000625 = $0.0002

Pass 2 (70% PRs): Sonnet — full review
  8000 + 1500 = $0.0465

Pass 3 (5% PRs): Sonnet — second pass for security-sensitive
  12,000 + 2000 = $0.066

Blended (her 100 PR):
  100 × $0.0002 (Haiku) = $0.02
  70 × $0.0465 = $3.255
  5 × $0.066 = $0.33
  Total: $3.605
  Revenue: 100 × $0.10 = $10
  Margin: $6.395 (64%)
```

### Qiymət Senitivliyi

| Qiymət/PR | Sonnet COGS | Margin |
|-----------|-------------|--------|
| $0.05     | $0.0465     | 7%     |
| $0.10     | $0.0465     | 53%    |
| $0.20     | $0.0465     | 77%    |
| $0.50     | $0.0465     | 91%    |

Amma qiymət çox olsa, istifadəçi getmir. **Optimal $0.10 - $0.20 zonası** (CodeRabbit, Greptile bunu təsdiqləyir).

---

## Free Tier Dizaynı — Cəlb Etmək amma Batmamaq

Free tier AI-da təhlükəlidir. Bir istifadəçinin COGS-u $5/ay olsa, 10,000 free user = $50k/ay yanır.

### Dizayn Prinsipləri

1. **Tokens, sessiyalar yox**: istifadəçi "3 söhbət" deyil, "1000 token" başa düşsün (yox, düzəliş: əksi — "3 sessiya/gün" daha UX-friendly)
2. **Hard limit + soft throttle**: free user 10 call-dan sonra 2 saniyə wait
3. **Haiku-only free tier**: Sonnet paid user üçün saxla
4. **Context limit**: free 2k context, paid 32k+
5. **Feature gating**: advanced feature-lər (vision, long context) paid-də

### Free Tier Cost Modeli

Misal: 10,000 free user, 3 call/həftə, Haiku:
```
Ayda: 10,000 × 12 call × 2000 token × $0.25/M = $60/ay
əlavə cache ilə: $30/ay
```

Free tier $30-60/ay ödəyirsən, amma cəlbetmə dəyəri çox yüksəkdir (əgər 2% free → paid convert olursa, 200 paid user = $29 × 200 = $5,800 revenue).

### Anti-Abuse Checklist

- Email verification (disposable email bloku)
- IP rate limit (hər IP-dan max 10 hesab)
- Device fingerprinting
- Free tier credit hər gün refresh, yığılma yox
- Captcha suspicious pattern-də

---

## Real Bazar Müqayisəsi

### GitHub Copilot — Subsidized Power

```
Qiymət: $10/ay individual (~ $8/ay net)
Istifadə: medianda 300 completion/gün = 9000/ay
Cost (Microsoft-un): təxmini $20-30/ay/user
Zərər per user: $12-22/ay
```

**Niyə davam edir?** Microsoft GitHub ecosystem-ə bağlanır, Azure credits satır, VS Code-a user çəkir. Copilot **loss leader**-dir. Balansı Azure gəliri ilə bağlayır.

### Cursor — Tiered Power

```
Qiymət: $20/ay Pro, $40/ay Business
Limit: 500 premium req + unlimited Haiku
Real usage (leaked survey): medianda 300 premium/ay
Cost: ~$8-12/ay/user
Margin: 40-60% (yaxşı!)
```

**Strategiya**: power user-ləri credit-lə məhdudlaşdır, casual user-lərlə margin gətir.

### Intercom Fin — Outcome-Based

```
Qiymət: $0.99/resolution
Resolution rate: 50-70% (qalanı human-a escalation)
Cost per resolution: ~$0.20-0.40 (multi-turn + RAG)
Margin: 60-80%
```

**Strategiya**: performance-based pricing AI-ya çox uyğundur — müştəri ödəyir yalnız dəyər aldıqda.

### Notion AI — Platform Play

```
Qiymət: $10/ay add-on (və ya Plus+/Business-də daxil)
Cost: təxmini $2-4/ay/user
Margin: 60-80%
```

**Strategiya**: retention məhşuğu. AI tək gəlir mərkəzi deyil, platform dəyəri artırır.

### Perplexity Pro — Usage Cap

```
Qiymət: $20/ay
Limit: 300 Pro search/gün = ~9000/ay (practically unlimited)
Real usage: medianda 50/ay
Cost per Pro search: ~$0.02-0.05 (multi-model + web)
Medianda cost: $1-2.5/ay
Margin: 85%+
```

**Strategiya**: generosity signal — "pro" istəyən güclü söz-sözlü marketinq gətirir.

### Summary Table

| Məhsul          | Qiymət       | Tipik COGS | Gross Margin | Model           |
|-----------------|--------------|------------|--------------|-----------------|
| Copilot Individual | $10/ay       | $20-30     | Mənfi        | Loss leader     |
| Cursor Pro       | $20/ay       | $10        | 50%          | Credits + tier  |
| Intercom Fin     | $0.99/res    | $0.30      | 70%          | Outcome         |
| Notion AI        | $10/ay addon | $3         | 70%          | Platform bundle |
| Perplexity Pro   | $20/ay       | $2         | 90%          | Usage cap       |

---

## Cost Attribution — Spreadsheet Modelləri

Birdən çox feature olanda, hansı feature hansı istifadəçini yandırır? Cost attribution təbəqəsi qur.

### 3-Dimensional Model

```
cost_row = {
  tenant_id,
  user_id,
  feature,        // "chatbot", "summarizer", "search"
  model,          // claude-sonnet-4-5
  input_tokens,
  output_tokens,
  cached_tokens,
  cost_cents,
  timestamp
}
```

### PHP İmplementasiyası

```php
// app/Services/AI/CostAttribution.php
class CostAttribution
{
    public function perFeature(Carbon $month): array
    {
        return DB::table('ai_usage')
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->selectRaw('feature, SUM(cost_cents) / 100 AS cost_usd,
                         COUNT(*) AS calls,
                         AVG(cost_cents) / 100 AS avg_cost')
            ->groupBy('feature')
            ->orderByDesc('cost_usd')
            ->get()->toArray();
    }

    public function perTenant(Carbon $month): array
    {
        return DB::table('ai_usage')
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->selectRaw('tenant_id, SUM(cost_cents) / 100 AS cost_usd,
                         COUNT(*) AS calls')
            ->groupBy('tenant_id')
            ->orderByDesc('cost_usd')
            ->get()->toArray();
    }

    public function topBurners(int $limit = 10): array
    {
        return DB::table('ai_usage')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('user_id, tenant_id,
                         SUM(cost_cents) / 100 AS cost_30d')
            ->groupBy('user_id', 'tenant_id')
            ->orderByDesc('cost_30d')
            ->limit($limit)
            ->get()->toArray();
    }
}
```

### Spreadsheet Template (Google Sheets Mock)

```
Sheet "Feature_Economics":

A: Feature      B: Calls  C: Avg Token  D: Cost/call  E: Monthly Cost  F: Revenue  G: Margin
chatbot         50,000    3,500         $0.0075       $375             $1,450     74%
summarizer      12,000    8,000         $0.039        $468             $600       22%
search          25,000    2,000         $0.0045       $112             $500       78%

Əgər "summarizer" 22% margin-də, deməli:
- Ya qiyməti artır
- Ya Haiku-ya köçür
- Ya cache optimallaşdır
```

### Tenant-level Analysis

```
Sheet "Tenant_Profit":

A: Tenant      B: Users  C: Plan  D: Revenue  E: AI COGS  F: Infra  G: Support  H: Margin
Acme Corp       50       Pro      $1,450      $320        $25       $80         71%
Globex          200      Ent      $4,900      $2,100      $100      $400        47%
Initech         5        Pro      $145        $850        $2.50     $10         -593% (!)
```

Initech kimi tenant-lər "whale burner" adlanır. Bu account-ları **manually kontakt edib** upsell etmək və ya müqavilə dəyişdirmək lazımdır.

---

## Keyfiyyət-Xərc Tradeoff-u

"Niyə Opus/Sonnet istifadə etmirsən? Daha yaxşı olar ki!" deyə product manager-lər tez-tez soruşur. Cavab: **10x xərc = 2-5% keyfiyyət** əksər taskda.

### Ölçmə Metodikası

1. 200 nümunə dataset (real user query-lər)
2. Hər modeldə cavab generat et
3. LLM-judge (başqa Sonnet) + human sample qiymətləndirmə
4. 1-5 skor, average çıxart

### Real Nəticələr (hipotetik, lakin tipik)

| Task                    | Haiku   | Sonnet  | Opus    | Opus ÜSTün. |
|-------------------------|---------|---------|---------|-------------|
| Summarize article       | 4.2     | 4.5     | 4.55    | 1.1%        |
| Draft email             | 4.0     | 4.4     | 4.5     | 2.3%        |
| Code generation (CRUD)  | 3.8     | 4.6     | 4.65    | 1.1%        |
| Complex reasoning       | 3.2     | 4.1     | 4.6     | 12.2%       |
| Multi-step agent        | 3.0     | 4.2     | 4.7     | 11.9%       |
| Creative writing        | 3.9     | 4.3     | 4.55    | 5.8%        |

**Qayda**: Opus yalnız complex reasoning və multi-step agent-də əsaslandırılmış üstünlük verir. Bu 2 task %5-dən az request-dir.

### Routing Cost Matrix

```
100k request/ay ssenari:
  hamı Sonnet:  100,000 × $0.017 = $1,700
  hamı Haiku:   100,000 × $0.00142 = $142
  routed:
    80% Haiku (sadə):   80,000 × $0.00142 = $113.6
    15% Sonnet:         15,000 × $0.017 = $255
    5% Opus (complex):  5,000 × $0.10 = $500
    Total: $868.6

Saving vs all-Sonnet: 49%
Keyfiyyət impact: ~0.5% (sadə request-lərdə Haiku qəbuledilən)
```

---

## LTV:CAC AI COGS ilə

Standart formula:
```
LTV = (Revenue/ay - COGS/ay) × Avg_lifetime_months
CAC_ratio = LTV / CAC
```

AI olanda COGS aylıq yox, **user activity-dən asılı** dəyişkən olur.

### Cohort Analysis

```
Month 1 (trial): avg COGS $2/user (yüksək, çünki test edirlər)
Month 2: $5/user (aktiv istifadə)
Month 3+: $3/user (routine)

Avg lifetime: 18 ay
LTV = Σ(Revenue - COGS) over 18 months

Revenue: $29/ay
Net revenue: $27.28

Month 1: $27.28 - $2 - $0.50 infra = $24.78
Month 2: $27.28 - $5 - $0.50 = $21.78
Month 3-18: 16 × ($27.28 - $3 - $0.50) = 16 × $23.78 = $380.48

Total LTV = $24.78 + $21.78 + $380.48 = $427.04
```

Əgər CAC $150-dirsə:
```
LTV:CAC = 427 / 150 = 2.85
```

SaaS-da **3:1 minimum**-dur. 2.85 narahatedicidir. Seçim:
1. CAC-ı aşağı sal (marketinq effiktivliyi)
2. COGS-u aşağı sal (routing, caching)
3. Qiyməti qaldır
4. Retention-i uzat (lifetime artır)

### AI Retention Effekti

AI feature çox vaxt retention-i artırır. Eyni istifadəçi AI-siz 12 ay qalır, AI-lı 20 ay. Bu durum-da LTV boost = 67%.

Amma AI COGS üzündən gross margin düşür. **Net effekt** hesablayırsan:
```
Without AI: revenue $29, margin 85% = $24.65 × 12 = $295.80 LTV
With AI:    revenue $29, margin 70% = $20.30 × 20 = $406.00 LTV

AI qərarı: +$110.20 LTV (faydalıdır)
```

---

## Qərar Ağacları və Cheat Sheet

### "Feature-imi necə pricingləyim?" Ağacı

```
Feature real ölçülə bilir? (resolve count, generated word, etc.)
├── BƏLİ → Outcome-based (Fin stili) ən yaxşı
│         Amma kalibrasyon çətindir, MVP-də included başla
└── XEYR → Usage variance P95/P50 > 5x?
          ├── BƏLİ → Credits + overage
          │         (Cursor stili)
          └── XEYR → Flat included
                    (Notion AI stili)
```

### "Haiku-mu, Sonnet-mi?" Ağacı

```
Task complex reasoning tələb edir?
├── BƏLİ (3+ addım, tool-use, agent) → Sonnet
└── XEYR → Response bir klassifikasiya/istehlak/qısa generasiyadır?
          ├── BƏLİ → Haiku (12x ucuz)
          └── XEYR (uzun, formal, creative) → A/B test et:
                    Sonnet vs Haiku 100 nümunədə,
                    fərq 5%-dən az isə Haiku saxla
```

### "Cache-ləyim yoxsa yox?" Ağacı

```
Eyni context 2-dən çox istifadə olunur?
├── BƏLİ → Cache et (5 min TTL default)
│         Cache write +25% cost, cache read -90%
└── XEYR → Cache-siz ucuz
```

### 10-Saniyəlik Cheat Sheet

1. **Hər feature üçün aylıq COGS izlə** (tenant + user + feature level)
2. **P95/P99 istifadəçi profilini bil** — onlar pricingini dəyişir
3. **Model routing default-dur** (Haiku 70%, Sonnet 25%, Opus 5%)
4. **Prompt cache default-dur** (eyni context 2+ dəfə)
5. **Break-even = Revenue / blended_price** — heç vaxt 30%-dən yuxarı keçmə
6. **Free tier Haiku-only + hard cap**
7. **Pricing model variance-ə bağlı**: variance yüksək → credits, aşağı → flat
8. **Outcome-based pricing (mümkün olduqda)** margin-də ən yüksək
9. **LTV:CAC AI-da **net effect**-lə hesabla** (AI retention boost - COGS)
10. **Whale burner-ləri manually kontakt et** — zərərli tenant-lar

---

## Son Söz — Biznes Kimi Düşün, Developer Kimi Qur

AI feature-i sənin platformadakı utility qatı-dır. Sən onu eyni disiplinlə qurmalısan ki:
- **Hər çağırış loglanır** (cost attribution)
- **Hər user-in profili var** (P50-P99)
- **Hər feature-in margin-i bilinir** (feature eco sheet)
- **Hər plan break-even üzərində rahat işləyir**

Əgər bu 4 data point-u göstərə bilmirsənsə, pricing dəyişmə — əvvəlcə ölç. AI biznesində **intuiton sənin düşmənindir**. Rəqəmlər isə dostun.

Bu sənədi düzəltdikcə, sənin öz app-ın üçün oxşar spreadsheet hazırla: hər satırda bir feature, sütunlar revenue/COGS/margin. Hər həftə baxmaq, hər ay düzəltmək — AI margin-ə sevgi məktubu budur.
