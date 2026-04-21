# 94. Feature Flags & Progressive Delivery

Feature flag (toggle) — kod deployment-i ilə feature release-ini ayıran runtime switch. Deploy fərqlidir, release fərqlidir. Bu fayl flag növlərini, architecture variant-larını (local vs server-side evaluation), consistency problem-lərini (sticky bucketing), progressive delivery pattern-lərini və LaunchDarkly/Unleash/Flagsmith/OpenFeature ekosistemini araşdırır.

## Niyə feature flag?

### Köhnə (bad) workflow

```
1. Feature branch — 3 həftə işlə
2. Merge to main
3. Deploy → 100% users-ə gedir
4. Bug tapıldı → emergency rollback / fix
5. Deploy yenidən
```

**Problem:** deploy = release, risk bütün user-ə, rollback deploy cycle lazım.

### Flag-based workflow

```
1. Feature code + flag guard:
   if ($flag->enabled('new-checkout', $user)) { ... }
2. Merge to main (continuous)
3. Deploy → 0% enabled (dark launch)
4. Enable for internal users → test
5. 1% → observe → 5% → 25% → 100%
6. Bug? Disable flag (seconds, no deploy)
7. Feature stable → remove flag from code (cleanup)
```

## Flag növləri

### 1. Release toggle (short-lived)

```php
if (Feature::enabled('new-pricing-page', $user)) {
    return view('pricing-v2');
}
return view('pricing-v1');
```

**Ömür:** həftələr-aylar. Rollout tamamlananda kod sadələşdirilir, flag silinir.

### 2. Experiment toggle (A/B)

```php
$variant = Feature::variant('checkout-button-color', $user);
// 'control' | 'green' | 'orange'
```

**Ömür:** eksperiment müddəti (həftələr). Statistik significance çıxandan sonra qalib seçilir.

### 3. Ops toggle

```php
if (Feature::enabled('use-new-image-service')) {
    return $this->newImageService->upload($file);
}
return $this->legacyS3->upload($file);
```

**Ömür:** uzun. Kill switch — service mis davranır, flag off edib köhnə-ə qayıt.

### 4. Permission toggle

```php
if (Feature::enabled('beta-api', $user)) {
    // Only beta customers
}
```

**Ömür:** permanent (entitlement). User.plan = "enterprise" → özellik aktiv.

| Tip | Dinamik mi? | Ömür | Audience |
|-----|-------------|------|----------|
| Release | Yes | Həftələr | All users (rollout) |
| Experiment | Yes | Həftələr | Randomized groups |
| Ops | Yes | Uzun | All users |
| Permission | Semi-static | Permanent | Specific users/plans |

## Architecture patterns

### Pattern 1 — Server-side evaluation

```
App Server → Flag Service (HTTP) → "is flag enabled?"
                                   returns bool
```

**Pro:** flag service central, latency uniform, real-time changes.

**Contra:** hər check = network call → latency (10-50ms). Flag service SPOF.

### Pattern 2 — Local evaluation (SDK polling)

```
Flag Service → SDK in app (every 30s pull flag ruleset)
App → SDK.isEnabled(flagKey, userContext) → local evaluation (<1 ms)
```

Flag rule definitions download olur, evaluation app-in özündə.

```
Ruleset example:
  flag "new-checkout":
    - if user.country == "US": 50% enabled
    - if user.plan == "enterprise": 100% enabled
    - default: disabled
```

**Pro:**
- Ultra-low latency (<1ms)
- Flag service down → last-known rules cached → still functional
- No per-check network call

**Contra:**
- Rule propagation delay (30s stale)
- Rules visible to app binary (security consideration)

LaunchDarkly, Unleash, Flagsmith hamısı local evaluation offers.

### Pattern 3 — Streaming (real-time push)

```
SDK → SSE / WebSocket to flag service
Rules updated → push to all SDKs (<1s)
```

LaunchDarkly "streaming mode" — instant kill switch.

## Evaluation algorithm — bucketing

User 42-nin 25% rollout-a daxil olub olmadığını necə qərar verirsən?

### Naive

```python
random.random() < 0.25   # ← PİS! Hər check fərqli nəticə
```

User page refresh edəndə dəyişir — UX broken.

### Sticky bucketing (correct)

```
bucket = hash(user_id + flag_key) % 100
enabled = bucket < rollout_percentage
```

```php
function isInRollout(string $flagKey, string $userId, int $percent): bool {
    $hash = crc32($flagKey . ':' . $userId);
    return ($hash % 100) < $percent;
}
```

**Deterministic:**
- Same user + flag → same result (stable experience)
- Different users → different results (~25% distribution)

### Flag-salted hash

```
bucket = hash(flagKey + userId) % 100
```

`flagKey` salt kimi — fərqli flag-lər fərqli bucket distribution verir. Kullanıcı "25% group A" + "25% group B" indipendent olsun.

### Gradual rollout

```
Day 1:  1% enabled  → bucket < 1
Day 2:  5%          → bucket < 5   (bucket<1 user-lər hələ də in, yeni user əlavə)
Day 3: 25%          → bucket < 25
Day 4: 100%         → all
```

Stable inclusion — bir dəfə flag alan user flag itirməz (sorted cohort in).

## Segment targeting

Rollout-dan başqa, konkret dim-lərə:

```yaml
flag: new-dashboard
rules:
  - if user.country in ['US', 'CA']: 100%
  - if user.plan == 'enterprise': 100%
  - if user.created_at > '2024-01-01': 50%
  - if user.email ends with '@internal.com': 100%
  - default: 0%
```

Evaluation order — first match wins.

## OpenFeature — vendor-neutral standard

2022-də CNCF-ə daxil olan spec. Fərqli flag vendor-ları (LaunchDarkly, Flagsmith, Unleash) ortaq API.

```php
use OpenFeature\OpenFeatureAPI;

OpenFeatureAPI::getInstance()->setProvider(new FlagsmithProvider($config));
$client = OpenFeatureAPI::getInstance()->getClient('my-app');

$enabled = $client->getBooleanValue('new-checkout', false, $evaluationContext);
```

Provider keçid — vendor swap minimal kod dəyişikliyi.

### Hooks

```php
$client->addHooks(new LoggingHook());
$client->addHooks(new MetricsHook());
```

Every evaluation → logged + metricized.

## Kill switch pattern

```php
// Critical ops flag — kill switch for new service
if (Feature::enabled('use-new-pricing-engine')) {
    try {
        return $this->newEngine->calculate($cart);
    } catch (\Throwable $e) {
        report($e);
        Feature::disable('use-new-pricing-engine');  // self-heal
        return $this->legacyEngine->calculate($cart);
    }
}
```

Monitoring triggered auto-disable — circuit breaker + flag.

## Progressive delivery

**Progressive delivery** = canary deploy + feature flag + automated rollback.

```
Stage 1 — Dark launch:
  Deploy code. Flag OFF. Run full integration tests.

Stage 2 — Internal:
  Flag ON for employees only. Dogfood.

Stage 3 — Canary 1%:
  Flag ON for 1% of prod users. Watch error rate.

Stage 4 — Ramp 5% → 25% → 50% → 100%:
  Automated rollout based on SLO health.

Stage 5 — Cleanup:
  Remove flag from code. Deploy simplification.
```

### Automation (Flagger, Argo Rollouts)

```yaml
# Flagger canary with flag integration
apiVersion: flagger.app/v1beta1
kind: Canary
spec:
  analysis:
    interval: 1m
    threshold: 5
    maxWeight: 50
    stepWeight: 5
    metrics:
    - name: error-rate
      thresholdRange: { max: 1 }
```

Errors artarsa — rollback automatic.

### LaunchDarkly + Datadog integration

Flag enable → LaunchDarkly sends event to Datadog → metric annotation on dashboards → sees impact on latency/errors.

## A/B testing integration

Feature flag variant + analytics event:

```php
$variant = Feature::variant('checkout-flow', $user); // 'A' or 'B'

// Log to analytics
analytics()->track($user, 'checkout_started', [
    'experiment' => 'checkout-flow',
    'variant' => $variant,
]);

// Later
analytics()->track($user, 'purchase_completed', [
    'experiment' => 'checkout-flow',
    'variant' => $variant,
    'revenue' => $order->total,
]);
```

Data warehouse query: conversion rate per variant, statistical significance (chi-square, Bayesian).

**Tool integration:**
- LaunchDarkly + Amplitude / Mixpanel
- Statsig — combined flag + analytics platform
- Optimizely — historical A/B focus

## Flag consistency pitfalls

### Double-reading

```php
// BAD
if (Feature::enabled('new-flow', $user)) {
    logEvent('saw-new-flow');
}
// ... 100 lines later ...
if (Feature::enabled('new-flow', $user)) {  // may differ if flag toggled mid-request
    renderNewUI();
}
```

**Fix:** evaluate once per request:

```php
$useNewFlow = Feature::enabled('new-flow', $user);
// use $useNewFlow everywhere in request
```

SDK-lərdə "evaluation cache per request" default var (LaunchDarkly `Variation`).

### Cross-service flag drift

```
Service A: flag ON
Service B (downstream): flag OFF (stale SDK cache)
→ A calls B with new API, B rejects
```

**Fix:**
- Synchronized flag refresh (streaming)
- Graceful degradation in downstream (accept both v1/v2)
- Flag versioning in request headers

## Flag debt

### Problem

```php
if (Feature::enabled('ab-test-2023-button-color')) {
    // winner already picked in 2024, but flag still in code
}
```

50 active flags + 200 stale flags = codebase noise. Dead code paths. Test matrix explosion.

### Solutions

1. **Expire date on flags** — 6 month default, auto-alert
2. **Code scan** — lint rule: flag not touched in N days → flag for removal
3. **Platform reminder** — LaunchDarkly "temporary flags not removed" weekly email
4. **Jira tickets** paired with flag creation

### PHP linter example

```
Find all Feature::enabled('...') calls
Cross-reference with flag platform API
Flag those "permanently 100%" → safe to remove
```

## SDK architecture

```
Application
    ↓
OpenFeature SDK (abstraction)
    ↓
Provider (LaunchDarkly SDK / Unleash SDK / ...)
    ├── Local cache (last known rules)
    ├── Event queue (evaluation events batched)
    └── Streaming connection (SSE)
         ↓
    Flag Service (SaaS or self-hosted)
         ├── Rules DB
         ├── User segment DB
         ├── Audit log
         └── Analytics pipeline
```

### Evaluation event data

```json
{
  "flagKey": "new-checkout",
  "userId": "user-42",
  "value": true,
  "variation": "control",
  "reason": "RULE_MATCH",
  "ruleId": "rule-1",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

Batched upload → flag service → analytics dashboards (exposure graphs).

## Vendor comparison

### LaunchDarkly (commercial)
- **Pros:** mature, streaming, audit, enterprise features
- **Cons:** expensive ($0.05+/MAU)
- **Use:** enterprise, large scale

### Unleash (open-source + SaaS)
- **Pros:** self-host option, privacy, active community
- **Cons:** fewer integrations
- **Use:** EU privacy, GDPR strict, self-hosted

### Flagsmith (open-source + SaaS)
- **Pros:** similar to Unleash, more opinionated UI
- **Cons:** smaller ecosystem

### Split (by Harness)
- **Pros:** strong A/B testing, attribution
- **Cons:** expensive

### GrowthBook (open-source)
- **Pros:** A/B + feature flag, warehouse-native analytics
- **Cons:** newer, smaller team

### Statsig
- **Pros:** combined flag + analytics + experiment
- **Cons:** less flag flexibility

### Homegrown (not recommended)
- Redis / DB-backed table
- Basic rollout %, simple targeting
- Works until you need audit, segments, analytics, streaming
- Build vs buy: >500 flags → buy

## Laravel implementation example

```php
// config/features.php
return [
    'driver' => env('FEATURE_DRIVER', 'flagsmith'),  // or 'local'
    'flagsmith' => [
        'api_url' => env('FLAGSMITH_URL'),
        'environment_key' => env('FLAGSMITH_ENV_KEY'),
    ],
];

// app/Support/Feature.php
class Feature
{
    public static function enabled(string $key, ?User $user = null): bool
    {
        $user ??= auth()->user();

        $context = [
            'user_id' => $user?->id,
            'country' => $user?->country,
            'plan' => $user?->plan,
            'email' => $user?->email,
        ];

        return app(FlagProvider::class)->isEnabled($key, $context);
    }

    public static function variant(string $key, ?User $user = null): string
    {
        $user ??= auth()->user();
        return app(FlagProvider::class)->getVariant($key, ['user_id' => $user?->id]) ?? 'control';
    }
}
```

Laravel 11+ has built-in `Laravel\Pennant` package.

### Laravel Pennant

```php
use Laravel\Pennant\Feature;

Feature::define('new-api', function (User $user) {
    return match (true) {
        $user->isInternal() => true,
        $user->created_at->isAfter('2024-01-01') => Lottery::odds(1, 10),
        default => false,
    };
});

// Usage
if (Feature::active('new-api')) {
    // ...
}

// Driver options: array (test), database, custom
```

## Best practices

1. **Every flag has an owner + expiry**
2. **Start with 1% canary, not 100%**
3. **Monitor SLO during rollout** — automated rollback
4. **Use evaluation cache per request** (don't re-check mid-request)
5. **Log flag exposure** for A/B attribution
6. **Audit trail** — who changed flag, when, why
7. **Clean up within SLA** (e.g., 90 days)
8. **Test both branches** in CI (not just default)
9. **Graceful degradation** — flag service down → safe default
10. **Don't stack many flags** — compound complexity

## Anti-patterns

1. **"Forever flags"** — became permanent if-else, clean up
2. **Single long flag check chain** — restructure into strategy pattern
3. **No monitoring of flag state** — secret config drift
4. **Client-side flag for security** — user toggles DevTools → "enterprise" features
5. **Complex targeting rules without test** — hard to audit
6. **Flag in hot loop** — 1M checks/req, cache once
7. **Using flags for config** — env vars / config files more appropriate

## Real-world

### Meta
- Gatekeeper system (internal)
- 100k+ flags
- Every request touches ~200 flags
- Full audit + A/B infrastructure

### Netflix
- Open-source Archaius
- Built dynamic config + experiment layer
- Product launch = multi-month gradual rollout

### Amazon
- Weblab — same pattern
- Every button color test = 2 weeks of A/B

### Spotify
- Remote Config + Feature Gates
- Integrates with release train

## Back-of-envelope

**SaaS with 1M MAU, 100 active flags:**
- 100 flag evaluations / page-view × 10 page-views / user / day × 1M users = 1B evals/day
- Local eval — no network cost
- Exposure events (1% sampled): 10M/day, 100 bytes each = 1 GB/day
- Storage (30 days): 30 GB

**LaunchDarkly pricing (example):**
- $500-2000/month for 100k MAU
- Scales to $10k+/month at 1M+

**Unleash self-hosted:**
- Postgres + small app container
- ~$50/month infra

## Yekun

Feature flags sadə ideyadır amma production stability-də transformativ. Deploy ilə release arasındakı ayırım continuous deployment-i mümkün edir — bütün code production-a getsin, release decision runtime-də. Local evaluation SDK-ları latency probleminin həllidir. Sticky bucketing A/B-də user experience stabiliyini təmin edir. Ən böyük təhlükə — **flag debt**: temporary flag permanent olur, 500 flag-dan sonra codebase-i idarə olunmaz edir. Progressive delivery (flag + canary + automated rollback) modern SRE practice-in əsasıdır.
