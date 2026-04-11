# System Design: A/B Testing Platform

## Mündəricat
1. [Tələblər](#tələblər)
2. [Yüksək Səviyyəli Dizayn](#yüksək-səviyyəli-dizayn)
3. [Komponent Dizaynı](#komponent-dizaynı)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Tələblər

```
Funksional:
  Experiment yaratmaq: variasiyalar, traffic bölgüsü
  User assignment: user-ı variasiyaya göndər (sticky)
  Metric collection: conversion, click, revenue
  Statistical analysis: hansı variasiya kazandı?
  Segmentation: yalnız premium user-lər, EU user-lər

Qeyri-funksional:
  Aşağı gecikmə: assignment < 1ms (request path-dadır)
  Sticky: eyni user həmişə eyni variasiyada
  Yüksək mövcudluq: experiment service down olsa site işləyir
  Scale: 100M user, 100 paralel experiment

Fundamental qaydalar:
  Bir user-ə eyni experimentdə həmişə eyni variasiya
  Fərqli user-lər statistik baxımdan müstəqil
```

---

## Yüksək Səviyyəli Dizayn

```
┌────────────┐  Assignment  ┌───────────────────┐
│  App Code  │─────────────►│ Experiment Service│
└────────────┘              └──────────┬────────┘
     │                                 │
     │ result                   ┌──────▼──────┐
     ▼                          │   Config    │
  Show variant A/B              │   Cache     │
                                │  (Redis)    │
                                └─────────────┘

┌────────────┐  events  ┌───────────────────┐  ┌──────────────┐
│  App Code  │─────────►│  Analytics Queue  │─►│  Analysis DB │
│ (tracking) │          │  (Kafka)          │  │ (ClickHouse) │
└────────────┘          └───────────────────┘  └──────────────┘

Dashboard:
  ┌───────────────────────────────────────┐
  │  Experiment: "Checkout Button Color"  │
  │  Control (50%): Red button   → 3.2%   │
  │  Variant (50%): Green button → 4.1%   │
  │  Statistical significance: 95% ✓      │
  │  Winner: Green button (p < 0.05)      │
  └───────────────────────────────────────┘
```

---

## Komponent Dizaynı

```
User Assignment — deterministik, sticky:
  hash(user_id + experiment_id) % 100 → bucket (0-99)
  bucket < traffic_percentage → experiment
  bucket within experiment → variant
  
  Üstünlük:
  - DB sorğusu yoxdur
  - Hər çağırışda eyni nəticə (sticky)
  - Redis/DB olmadan işləyir

Traffic Allocation:
  Experiment: 10% traffic
  user_id + exp_id hash → 0-99
  0-9 → experiment (10%)
  
  Variant allocation (experimentdaxili):
  0-49 → Control
  50-99 → Variant

Exclusion Rules:
  Bir user-in bir anda bir experiment-ə iştirak etməsi
  Overlapping experiments mümkün ama segment fərqli olmalı

Config:
  Experiment config Redis-də cache edilir
  Hər sorğuda DB-yə getmir
  Config dəyişəndə Redis invalidate edilir
  Graceful fallback: Redis down → DB → hardcoded default

Statistical Significance:
  Chi-square test
  p-value < 0.05 → statistik əhəmiyyətlidir
  Minimum sample size: Fisher formula

Guardrail Metrics:
  "A/B test keçirərkən digər KPI-lər pisləşmirmi?"
  Yeni checkout buttonu: conversion artar, amma page load artırsa?
  → Experiment avtomatik dayandırılır
```

---

## PHP İmplementasiyası

```php
<?php
// Experiment Assignment (deterministik hash)
namespace App\Experiment;

class ExperimentAssignment
{
    public function __construct(
        private ExperimentRepository $experiments,
        private \Redis               $redis,
    ) {}

    public function assign(string $userId, string $experimentKey): ?VariantResult
    {
        // Config-i cache-dən al (DB-yə getmə)
        $experiment = $this->getExperimentConfig($experimentKey);

        if ($experiment === null || !$experiment['active']) {
            return null; // Experiment yoxdur/bitib → control behavior
        }

        // 1. Bu user experiment-ə daxildirmi?
        $globalBucket = $this->getBucket($userId, $experimentKey . ':global');

        if ($globalBucket >= $experiment['traffic_percentage']) {
            return null; // Bu user experiment-də deyil
        }

        // 2. Hansi variasiya?
        $variantBucket = $this->getBucket($userId, $experimentKey . ':variant');
        $variant       = $this->getVariant($experiment['variants'], $variantBucket);

        return new VariantResult(
            experimentKey: $experimentKey,
            variantKey:    $variant['key'],
            config:        $variant['config'] ?? [],
        );
    }

    private function getBucket(string $userId, string $salt): int
    {
        // Deterministik: eyni user+salt → həmişə eyni bucket
        $hash = crc32($userId . ':' . $salt);
        return abs($hash) % 100;
    }

    private function getVariant(array $variants, int $bucket): array
    {
        $cumulative = 0;
        foreach ($variants as $variant) {
            $cumulative += $variant['weight'];
            if ($bucket < $cumulative) {
                return $variant;
            }
        }
        return $variants[0]; // Fallback control
    }

    private function getExperimentConfig(string $key): ?array
    {
        $cacheKey = "experiment:{$key}";
        $cached   = $this->redis->get($cacheKey);

        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $experiment = $this->experiments->findByKey($key);
        if ($experiment) {
            $this->redis->setex($cacheKey, 60, json_encode($experiment));
        }

        return $experiment;
    }
}
```

```php
<?php
// Event tracking
class ExperimentTracker
{
    public function __construct(
        private \RdKafka\Producer $producer,
    ) {}

    public function trackAssignment(string $userId, VariantResult $result): void
    {
        $this->publish('experiment.assigned', [
            'user_id'        => $userId,
            'experiment_key' => $result->experimentKey,
            'variant_key'    => $result->variantKey,
            'timestamp'      => microtime(true),
        ]);
    }

    public function trackConversion(
        string $userId,
        string $experimentKey,
        string $metricName,
        float  $value = 1.0,
    ): void {
        $this->publish('experiment.converted', [
            'user_id'        => $userId,
            'experiment_key' => $experimentKey,
            'metric'         => $metricName,
            'value'          => $value,
            'timestamp'      => microtime(true),
        ]);
    }

    private function publish(string $eventType, array $data): void
    {
        $topic = $this->producer->newTopic('experiment.events');
        $topic->produce(\RD_KAFKA_PARTITION_UA, 0, json_encode(
            array_merge(['event_type' => $eventType], $data)
        ));
        $this->producer->poll(0);
    }
}
```

```php
<?php
// Usage in application code
class CheckoutController
{
    public function show(Request $request): Response
    {
        $userId     = $request->getAttribute('user_id');
        $assignment = $this->experiments->assign($userId, 'checkout_button_color');

        // Variant config-ə görə UI dəyişdir
        $buttonColor = match ($assignment?->variantKey) {
            'green'  => '#28a745',
            'orange' => '#fd7e14',
            default  => '#dc3545', // Control (red)
        };

        // Assignment-ı qeyd et (hər dəfə yox, bir dəfə)
        if ($assignment !== null) {
            $this->tracker->trackAssignment($userId, $assignment);
        }

        return $this->render('checkout', ['button_color' => $buttonColor]);
    }

    public function complete(Request $request): Response
    {
        $userId = $request->getAttribute('user_id');
        $order  = $this->processOrder($request);

        // Conversion track et
        $this->tracker->trackConversion($userId, 'checkout_button_color', 'purchase', $order->getTotal());

        return $this->redirectToSuccess();
    }
}
```

---

## İntervyu Sualları

- Deterministik hash assignment niyə DB-yə yazdan daha yaxşıdır?
- Experiment service down olduqda nə baş verməlidir?
- Statistical significance nədir? p-value nədir?
- "Novelty effect" nədir? A/B test nəticəsini necə təhrif edir?
- Segmentation experiments-ə necə tətbiq edilir?
- Multiple simultaneous experiments — user interaction problemi?
