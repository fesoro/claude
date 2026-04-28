# Feature Flags / A/B Testing — DB Design (Middle ⭐⭐)

## İcmal

Feature flag sistemi yeni funksionallığı tədricən açmağa, A/B test keçirməyə, instant rollback etməyə imkan verir. Deploy ≠ Release: kod production-da olur, amma flag bağlıdır. Stripe, Facebook, LinkedIn bu pattern-dən geniş istifadə edir.

---

## Tövsiyə olunan DB Stack

```
Flags / rules:  PostgreSQL   (source of truth)
Evaluation:     Redis        (flag cache — hər request-də DB-yə getmə)
Analytics:      ClickHouse   (impression, conversion tracking)
```

---

## Feature Flag Növləri

```
┌────────────────────┬──────────────────────────────────────────────────┐
│ Növ                │ Nümunə                                           │
├────────────────────┼──────────────────────────────────────────────────┤
│ Boolean toggle     │ new_checkout = true/false                        │
│                    │ Hamıya açıq / hamıya bağlı                       │
├────────────────────┼──────────────────────────────────────────────────┤
│ Percentage rollout │ new_dashboard: 10% user-ə açıq                  │
│                    │ Tədricən artır: 10% → 25% → 50% → 100%         │
├────────────────────┼──────────────────────────────────────────────────┤
│ User targeting     │ user_id IN [123, 456] → açıq                   │
│                    │ Beta testers, internal team                      │
├────────────────────┼──────────────────────────────────────────────────┤
│ Segment-based      │ Plan = 'pro' → açıq                             │
│                    │ Country = 'AZ' → açıq                           │
│                    │ Account age > 30 days → açıq                    │
├────────────────────┼──────────────────────────────────────────────────┤
│ A/B Test           │ 50% control, 50% treatment                      │
│                    │ Variant A vs B vs C                              │
├────────────────────┼──────────────────────────────────────────────────┤
│ Kill switch        │ Yeni feature problem yaratdı → anında bağla     │
│                    │ Rollback without redeploy                        │
└────────────────────┴──────────────────────────────────────────────────┘
```

---

## Core Schema

```sql
-- ==================== FEATURE FLAGS ====================
CREATE TABLE feature_flags (
    id          SERIAL PRIMARY KEY,
    key         VARCHAR(100) UNIQUE NOT NULL,  -- 'new_checkout_flow'
    name        VARCHAR(255) NOT NULL,          -- "New Checkout Flow v2"
    description TEXT,

    -- Flag type
    type        VARCHAR(20) NOT NULL DEFAULT 'boolean',
    -- 'boolean', 'percentage', 'multivariate'

    -- Global state
    is_enabled  BOOLEAN NOT NULL DEFAULT FALSE,  -- master switch

    -- Default value (enabled olduqda hamı üçün)
    default_value VARCHAR(50) DEFAULT 'true',    -- boolean üçün 'true'/'false'

    -- Metadata
    owner       VARCHAR(100),   -- 'payments-team', 'frontend-team'
    tags        VARCHAR(50)[],  -- ['checkout', 'experiment', 'beta']

    -- Lifecycle
    expires_at  TIMESTAMPTZ,    -- NULL = permanent
    archived_at TIMESTAMPTZ,    -- archived = köhnə, silinmir (audit)

    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== TARGETING RULES ====================
-- Hansı user-lərin hansı variant-ı aldığını müəyyən edir
CREATE TABLE flag_rules (
    id          SERIAL PRIMARY KEY,
    flag_id     INT NOT NULL REFERENCES feature_flags(id) ON DELETE CASCADE,

    -- Rule priority (aşağı = daha əvvəl yoxlanılır)
    priority    SMALLINT NOT NULL DEFAULT 100,

    -- Rule type
    rule_type   VARCHAR(30) NOT NULL,
    -- 'user_list', 'percentage', 'attribute', 'segment'

    -- Rule definition
    conditions  JSONB NOT NULL DEFAULT '{}',
    -- user_list:  {"user_ids": [123, 456, 789]}
    -- percentage: {"percentage": 25, "seed": "flag_key"}
    -- attribute:  {"attribute": "plan", "operator": "eq", "value": "pro"}
    -- segment:    {"segment_id": 5}

    -- Variant returned if rule matches
    variant     VARCHAR(50) NOT NULL DEFAULT 'true',
    -- 'true', 'false', 'variant_a', 'variant_b', 'control'

    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== VARIANTS (A/B Testing) ====================
CREATE TABLE flag_variants (
    id          SERIAL PRIMARY KEY,
    flag_id     INT NOT NULL REFERENCES feature_flags(id),

    key         VARCHAR(50) NOT NULL,   -- 'control', 'treatment', 'variant_b'
    name        VARCHAR(100),
    description TEXT,

    -- Traffic allocation (cəmi 100 olmalıdır)
    weight      SMALLINT NOT NULL,      -- 50 = 50%

    -- Variant-specific payload (config)
    payload     JSONB DEFAULT '{}',
    -- {"button_color": "blue", "cta_text": "Buy Now"}

    UNIQUE (flag_id, key)
);

-- ==================== USER SEGMENTS ====================
CREATE TABLE segments (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,  -- 'pro_users', 'beta_testers'
    description TEXT,

    -- Segment definition (rule-based)
    rules       JSONB NOT NULL DEFAULT '[]',
    -- [{"attribute": "plan", "op": "eq", "value": "pro"},
    --  {"attribute": "country", "op": "in", "value": ["AZ", "TR"]}]

    -- OR: explicit user list
    user_ids    BIGINT[],               -- explicit override

    -- Precomputed (batch job ilə yenilənir)
    user_count  INTEGER DEFAULT 0,
    computed_at TIMESTAMPTZ,

    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== FLAG AUDIT LOG ====================
-- Kim, nə vaxt, nəyi dəyişdi
CREATE TABLE flag_audit_log (
    id          BIGSERIAL PRIMARY KEY,
    flag_id     INT NOT NULL REFERENCES feature_flags(id),

    changed_by  BIGINT,                -- admin user_id
    action      VARCHAR(30) NOT NULL,  -- 'enabled', 'disabled', 'rule_added', 'rollout_changed'

    old_value   JSONB,
    new_value   JSONB,

    comment     TEXT,                  -- "Rolled out to 25% — no issues"

    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== EXPERIMENTS (A/B Tests) ====================
CREATE TABLE experiments (
    id          SERIAL PRIMARY KEY,
    flag_id     INT NOT NULL REFERENCES feature_flags(id),

    name        VARCHAR(255) NOT NULL,  -- "New Checkout Flow A/B Test"
    hypothesis  TEXT,                   -- "New flow increases conversion by 5%"

    -- Primary metric
    metric_key  VARCHAR(100),           -- 'checkout_conversion', 'revenue_per_user'

    -- Target sample size (statistik əhəmiyyət üçün)
    target_sample_size INTEGER,

    -- Status
    status      VARCHAR(20) DEFAULT 'running',
    -- 'draft', 'running', 'paused', 'completed', 'rolled_back'

    -- Result decision
    winner_variant VARCHAR(50),         -- 'treatment' or NULL

    started_at  TIMESTAMPTZ,
    ended_at    TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Flag Evaluation Logic

```
Flag evaluation sırası (priority descending):

1. Flag mövcuddur?  → xeyr: default (false)
2. Flag arxivlənib? → xeyr: false
3. Flag enabled?    → xeyr: false
4. Expires?         → keçib: false
5. Qaydalar yoxla (priority sırası ilə):
   Rule 1 (priority 1): user_id IN [123, 456] → match → 'true'
   Rule 2 (priority 10): plan = 'pro' → match → 'true'
   Rule 3 (priority 50): percentage = 25% → user hash % 100 < 25 → 'true'
6. Heç bir qayda match etmədisə → default_value

Percentage hashing (consistent assignment):
  seed    = "{flag_key}:{user_id}"  → MD5 → integer → % 100
  bucket  = hash_int % 100
  enabled = bucket < percentage
  
  Qayda: eyni user həmişə eyni variant-ı alır (consistent)
  Qayda: flag key-i seed-ə daxildir → fərqli flag-lar fərqli split
```

---

## Redis Cache

```
Flag-ları hər request-də PostgreSQL-dən oxumaq olmaz → cache lazım

Strategy:
  Bütün flag-ları Redis-ə cache et (TTL: 30 saniyə)
  Flag dəyişdikdə: cache invalidate et

Redis structure:
  HSET flags:all {flag_key} {flag_json}
  -- hər flag: key, type, is_enabled, rules (JSON)
  
  EXPIRE flags:all 30     -- 30 saniyəlik TTL

  Flag dəyişdi:
  DEL flags:all           -- next request DB-dən yenidən yükləyir

User assignment cache:
  SETEX flag:{flag_key}:user:{user_id} 300 {variant}
  -- 5 dəqiqə: eyni user-ə eyni variant

Evaluation flow:
  1. Redis: GET flag:{key}:user:{user_id} → hit → return cached variant
  2. Redis miss → load flag rules from Redis HGET flags:all {key}
  3. Evaluate rules in-memory
  4. Cache result: SET flag:{key}:user:{user_id} {variant} EX 300
```

---

## Laravel Implementation

```php
class FeatureFlagService
{
    public function isEnabled(string $flagKey, ?User $user = null): bool
    {
        return $this->evaluate($flagKey, $user) !== 'false';
    }

    public function getVariant(string $flagKey, User $user): string
    {
        $cacheKey = "flag:{$flagKey}:user:{$user->id}";

        return Cache::remember($cacheKey, 300, function () use ($flagKey, $user) {
            return $this->evaluateWithRules($flagKey, $user);
        });
    }

    private function evaluateWithRules(string $flagKey, User $user): string
    {
        $flag = $this->getFlagFromCache($flagKey);

        if (!$flag || !$flag['is_enabled']) {
            return 'false';
        }

        foreach ($flag['rules'] as $rule) {
            if ($variant = $this->matchRule($rule, $user)) {
                return $variant;
            }
        }

        return $flag['default_value'] ?? 'false';
    }

    private function matchRule(array $rule, User $user): ?string
    {
        return match ($rule['rule_type']) {
            'user_list'  => in_array($user->id, $rule['conditions']['user_ids'])
                              ? $rule['variant'] : null,
            'percentage' => $this->inPercentage($rule, $user)
                              ? $rule['variant'] : null,
            'attribute'  => $this->matchAttribute($rule, $user)
                              ? $rule['variant'] : null,
            default      => null,
        };
    }

    private function inPercentage(array $rule, User $user): bool
    {
        $seed   = $rule['conditions']['seed'] . ':' . $user->id;
        $bucket = crc32($seed) % 100;
        return abs($bucket) < $rule['conditions']['percentage'];
    }
}

// Blade template
@if(feature('new_checkout_flow'))
    <x-new-checkout />
@else
    <x-old-checkout />
@endif

// Controller
if ($this->flags->isEnabled('new_pricing_page', $user)) {
    return view('pricing.v2');
}
```

---

## A/B Test Lifecycle

```
1. Experiment yaradılır:
   - Hypothesis yazılır
   - Variants müəyyən edilir (control 50% + treatment 50%)
   - Metric seçilir (conversion rate, revenue, click-through)
   - Minimum sample size hesablanır (statistik əhəmiyyət)

2. Flag aktiv edilir:
   - is_enabled = TRUE
   - percentage rule: 50/50 split

3. Data toplanır (ClickHouse):
   - Impression: user flag-ı gördü
   - Conversion: user hədəf əməliyyatı etdi

4. Analiz:
   - Control vs Treatment: conversion rate müqayisəsi
   - Statistical significance: p-value < 0.05?
   - Minimum detectable effect keçildimi?

5. Qərar:
   - Winner: flag 100%-ə açılır (treatment qalib)
   - No winner: flag bağlanır (control qalır)
   - Rollback: problem varsa → anında flag bağla

Timeline:
  Typical A/B test: 1-2 həftə (kifayət qədər traffic)
  Minimum: 1000 conversion per variant (statistik etibarlılıq)
```

---

## Analytics (ClickHouse)

```sql
CREATE TABLE flag_events (
    event_time      DateTime,
    event_date      Date MATERIALIZED toDate(event_time),

    flag_key        LowCardinality(String),
    variant         LowCardinality(String),   -- 'control', 'treatment'
    user_id         Int64,

    event_type      LowCardinality(String),   -- 'impression', 'conversion'
    conversion_key  LowCardinality(String),   -- 'checkout_completed', 'signup'

    session_id      String

) ENGINE = MergeTree()
  PARTITION BY event_date
  ORDER BY (flag_key, event_date, user_id)
  TTL event_date + INTERVAL 90 DAY;

-- A/B test nəticəsi
SELECT
    variant,
    countIf(event_type = 'impression') AS users,
    countIf(event_type = 'conversion') AS conversions,
    conversions / users                AS conversion_rate
FROM flag_events
WHERE flag_key = 'new_checkout_flow'
  AND event_date >= '2026-04-01'
GROUP BY variant;

-- Nəticə:
-- control:   1,024 users, 143 conversions, 13.96%
-- treatment: 1,031 users, 162 conversions, 15.71%  ← winner (+12.5% lift)
```

---

## Tanınmış Sistemlər

```
LaunchDarkly:
  SaaS feature flag platform
  SDK: PHP, Java, Go, JS, ...
  Real-time streaming (flag dəyişdi → SDK anında bilir)
  Targeting: segments, % rollout, A/B
  PostgreSQL + Redis + streaming

Unleash (open-source):
  Self-hosted LaunchDarkly alternativi
  PostgreSQL backend
  Gradual rollout, A/B, kill switch
  Laravel SDK mövcuddur

Flipper (Ruby):
  Simple, Redis/PostgreSQL backend
  Boolean + percentage + actor targeting

Facebook GateKeeper:
  Internal system
  2007-dən bəri: hər feature flag ilə release
  "Ship to 0%, test, ramp up"
  Hundreds of simultaneous experiments

LinkedIn:
  XLNT (experiment platform)
  A/B test + multivariate
  Member assignment consistent via hash
  Pinot (real-time analytics) + Kafka
```

---

## Anti-Patterns

```
✗ Flag-ı heç vaxt silməmək:
  6 ay sonra 500 flag → kod xaotik
  Lifecycle: create → ramp up → 100% → cleanup (flag silinir)
  "Flag debt": texniki borc kimi idarə et

✗ Flags-ı hardcode etmək:
  if user_id == 123: → production-da dəyişdirmək olmur
  DB-dən oxu → admin panel-dən dəyiş

✗ Hər request-də DB-dən oxumaq:
  100ms əlavə latency × 1000 req/s = böyük problem
  Redis cache (30s TTL) mütləqdır

✗ A/B test-i erkən bitirmək:
  2 gün sonra "treatment qalib görünür" → dəyişikliklər edilir
  Novelty effect: yeni şeylər müvəqqəti yüksək engagement verir
  Minimum 1 həftə, statistik əhəmiyyət lazımdır

✗ Flags-da business logic:
  flag = 'show_discount_to_premium_users'
  → Bu flag deyil, business rule-dur → kodda yaz
  Flag: "new payment form UI enabled" (infrastruktur)

✗ Bir flag-da çox şey:
  'new_feature' → checkout + UI + email + pricing dəyişir
  Granular flags: hər dəyişiklik ayrı flag → rollback daha asan
```
