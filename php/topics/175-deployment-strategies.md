# Deployment Strategies — Blue-Green, Canary, Rolling, Shadow

## Mündəricat
1. [Niyə strategiya vacibdir?](#niyə-strategiya-vacibdir)
2. [Recreate (basit)](#recreate-basit)
3. [Rolling Update](#rolling-update)
4. [Blue-Green](#blue-green)
5. [Canary Deployment](#canary-deployment)
6. [A/B Testing deployment](#ab-testing-deployment)
7. [Shadow / Dark launch](#shadow--dark-launch)
8. [Feature Flags ilə deployment decoupling](#feature-flags-ilə-deployment-decoupling)
9. [Database migration coordination](#database-migration-coordination)
10. [Rollback strategiyaları](#rollback-strategiyaları)
11. [Kubernetes nümunələri](#kubernetes-nümunələri)
12. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə strategiya vacibdir?

```
"Deploy et, yenidən start et" — legacy yanaşma.
Problem:
  1. Downtime (5-60 saniyə və ya daha çox)
  2. User active session-lar itir
  3. In-flight request-lər fail olur
  4. Rollback yavaş
  5. Qüsurlu release bütün user-ləri vurur

Modern strategy:
  - Zero-downtime
  - Proqressiv rollout (10% → 50% → 100%)
  - Observable (metrikləri monitor et)
  - Atomik rollback

Seçim faktorları:
  - Traffic həcmi
  - Downtime tolerance
  - Infrastructure xərci (2× resource?)
  - Database migration mürəkkəbliyi
  - Rollback sürəti
```

---

## Recreate (basit)

```
Bütün köhnə instance-ləri dayandır → hamısını yeni versiya ilə başlat.

[v1]──┐
[v1]──┼── stop all → [v2]──┐
[v1]──┘               [v2]──┤
                      [v2]──┘

Pros:
  ✓ Sadə, database migration complex deyil
  ✓ Tam clean state

Cons:
  ✗ Downtime (30s - 5min)
  ✗ Rollback — yenidən deploy lazım
  ✗ Production-da istifadə etmə (bəzi dev/internal app-lər OK)

Nə vaxt istifadə:
  - Internal dev tool (monitoring yox)
  - Scheduled maintenance window mövcuddur
  - Resource məhduddur (2× kapasitə yoxdur)
```

---

## Rolling Update

```
Instance-ləri N-N qruplarda yenilə — bir hissə işləyir, digəri yenilənir.

[v1][v1][v1][v1]  — start
[v2][v1][v1][v1]  — 1-ci yenilənir
[v2][v2][v1][v1]  — 2-ci yenilənir
[v2][v2][v2][v1]  — 3-cü yenilənir
[v2][v2][v2][v2]  — tam keçid

maxUnavailable=1 — hər anda maks 1 instance down
maxSurge=1       — hər anda maks 1 əlavə instance (total N+1)

Pros:
  ✓ Zero downtime
  ✓ Resource efficient (1× kapasitə kifayət)
  ✓ Kubernetes default strategy

Cons:
  ✗ Rollout yavaş (N × restart time)
  ✗ Eyni anda v1 və v2 işləyir — backward-compatible olmalıdır!
  ✗ Database migration diqqətlə — hər iki versiya işləyə bilməlidir

Database uyğunluğu:
  ❌ Köhnə column drop → v1 crash olur
  ✓ Expand/contract pattern istifadə et
```

---

## Blue-Green

```
İki eyni mühit var: Blue (production), Green (idle).
Yeni version Green-ə deploy et, test et, LB-ni switch et.

Addım 1:          Addım 2:           Addım 3:
Blue = v1   LB    Blue = v1   LB     Blue = v1   (standby)
Green = idle ─→   Green = v2  ─X→    Green = v2  LB ─→
                                     
                                     Switch!

Pros:
  ✓ Instant rollback (LB geri switch)
  ✓ Tam test Green-də trafik almazdan əvvəl
  ✓ Zero downtime
  ✓ Clear "before/after" state

Cons:
  ✗ 2× resource lazım (prod-un ikiqat ölçüsü)
  ✗ Database paylaşılır — hər iki version ona yaza bilər
  ✗ Long-running connection-lar (WebSocket) switch zamanı kəsilir
  ✗ Cost yüksək

Database problem:
  Blue v1 schema A istəyir
  Green v2 schema B istəyir
  → Schema hər iki version üçün uyğun olmalıdır (expand/contract)

AWS implementation:
  Route53 weighted routing (100/0 → 0/100)
  ALB target group swap
  CodeDeploy blue/green config
```

---

## Canary Deployment

```
Azacıq trafiyi yeni versionə yönəlt → müşahidə et → tədricən artır.

Sequence:
  0%   v2 → bütün v1 (pre-deploy)
  5%   v2 → 5 dəqiqə müşahidə, metrikləri yoxla
  25%  v2 → 15 dəqiqə
  50%  v2 → 1 saat
  100% v2 → tam keçid

      95% ────►[v1][v1][v1][v1]
  LB ─┤
      5%  ────►[v2]   ← canary

Pros:
  ✓ Risk azaltma — problemi erkən tut, az user etkilenir
  ✓ Real-world data (synthetic test ilə tapılmayan bug)
  ✓ Rollback asan — 5% olan canary-ni ləğv et

Cons:
  ✗ Deployment uzun (saat-günlər)
  ✗ Monitoring vacib — automation olmadan zəhmət
  ✗ Eyni anda v1 və v2 işləyir (backward-compat)
  ✗ Traffic routing infrastructure lazım (Istio, ALB weighted TG)

Automated canary (Flagger, Argo Rollouts):
  - Trafiyi artır
  - Success rate, latency, error rate izlə
  - Threshold sapdırsa → auto-rollback
  - Hamısı keçərsə → 100%-ə keç

Criteria:
  error_rate < 0.5%
  p99_latency < baseline + 10%
  success_rate > 99.5%
```

---

## A/B Testing deployment

```
Canary-ə oxşar, amma məqsədi BUSINESS TEST, "safety" deyil.

A (control) — 50% user-lər, v1 (köhnə feature)
B (treatment) — 50% user-lər, v2 (yeni feature)

Məqsəd: metriklər hansı versiya daha yaxşıdır?
  conversion rate, time on page, checkout completion

User routing:
  cookie, user_id hash → stable assignment (eyni user hər zaman eyni branch-da)

Technical requirements:
  - İki version paralel işləyir (həftələrlə)
  - Analytics tracking
  - Feature flag sistemi (LaunchDarkly, Optimizely)

Fərq:
  Canary = deployment strategy (safe rollout)
  A/B = experiment (business hypothesis test)
```

---

## Shadow / Dark launch

```
Yeni versionə REAL trafiyi göndər, AMMA cavabı IGNORE et.

           ┌─► v1 (real, user-ə cavab gedir)
  Request ─┤
           └─► v2 (shadow, cavab atılır, yalnız müşahidə)

Use case:
  - Yeni ML model — v2 nə proqnozlaşdırır? (real data üzərində)
  - Rewrite — yeni codebase eyni nəticə qaytarırmı?
  - Performance test — production load-da v2 necə davranır?

Challenges:
  - Write operation-lar problemdir (duplicate charge?)
    → Yalnız read request-lərdə shadow
    → Yazıları no-op et (database-ə toxunma)
  - External side effect-lər (email, SMS) dublikat olar
    → Feature flag ilə söndür
  - v2 cavab latency-si user-i gözlətmə
    → Async shadow (fire-and-forget)

Implementation:
  Istio mirror policy:
    http:
    - route:
      - destination: { host: v1 }
      mirror:
        host: v2
      mirrorPercentage:
        value: 10.0   # 10% trafiyi v2-ə də göndər
```

---

## Feature Flags ilə deployment decoupling

```
"Deploy ≠ Release" prinsipi

Deployment: kod production-a yerləşdirilir
Release: feature user-lərə görünür

Feature flag ilə:
  1. Kod deploy olunur (flag OFF)
  2. Dark launch — internal user-lər sınayır
  3. 1% user — A/B test
  4. Bütün user-lər — full release
  5. Flag kaldırılır — kod clean-up

Fayda:
  ✓ Risk azaltma — instant rollback (flag OFF)
  ✓ Continuous deployment, gradual release
  ✓ A/B testing
  ✓ Kill switch (production fail olanda)
  ✓ Cohort targeting (premium user, beta-testers)

Tool:
  LaunchDarkly (paid)
  Flagsmith (open source)
  Unleash
  Laravel Pennant
```

```php
<?php
// Laravel Pennant nümunəsi
use Laravel\Pennant\Feature;

if (Feature::for($user)->active('new-checkout')) {
    return new NewCheckoutController()->handle($request);
}
return new LegacyCheckoutController()->handle($request);

// Definition
Feature::define('new-checkout', function (User $user) {
    return match(true) {
        $user->isAdmin()              => true,
        $user->isInBetaProgram()      => true,
        $user->id % 100 < 5           => true,   // 5% rollout
        default                        => false,
    };
});
```

---

## Database migration coordination

```
Expand/Contract pattern — deployment ilə sync:

1. Expand migration (backward compatible):
   - Yeni column əlavə (nullable)
   - Köhnə data duplicate edilir
   v1 və v2 ikisi də işləyir

2. Code deploy (v2 yeni column istifadə edir):
   - Yavaş-yavaş rollout
   - v2 yeni column-a yazır, köhnə column-u da yeniləyir

3. Data backfill:
   - Köhnə row-ları yeni column-a kopyala

4. Contract migration (köhnə sil):
   - v2 tam stabil olduqdan SONRA
   - Eski column drop edilir

Nümunə: email → contact_email rename
  Step 1: contact_email column əlavə (nullable)
  Step 2: v2 deploy — yazanda hər iki column yenilə
  Step 3: backfill contact_email = email
  Step 4: v2-də yalnız contact_email istifadə
  Step 5: Sonra email column drop

Nə qədər vaxt?
  Hər addım arasında saat-günlər lazımdır (monitor üçün).

Bax 110-zero-downtime-migrations.md.
```

---

## Rollback strategiyaları

```
1. Version rollback (deployment)
   - Blue-Green: LB geri switch (seconds)
   - Canary: 0% trafik, yenidən köhnə versiona
   - Rolling: kubectl rollout undo (dəqiqələr)

2. Feature flag rollback (release)
   - Instant (saniyə)
   - Deploy yoxdur
   - Partial rollback mümkündür (yalnız problemli feature)

3. Database rollback
   - ÇETİN — forward-only migration daha təhlükəsizdir
   - Backup restore (saatlar)
   - Point-in-time recovery

Best practices:
  ✓ Rollback "normal" olsun — developer qorxmamalıdır
  ✓ Automated rollback (metric threshold)
  ✓ Runbook hər critical servis üçün
  ✓ GameDay-lərdə rollback drill
  ✓ Hər deploy öncəsi "rollback plan" review
  ✓ Database migration reversible (down() method)
```

---

## Kubernetes nümunələri

```yaml
# Rolling update (default)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: api
spec:
  replicas: 10
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxUnavailable: 1    # eyni anda max 1 down
      maxSurge: 2          # eyni anda max +2 extra
  template:
    spec:
      containers:
      - name: api
        image: api:v2
        readinessProbe:
          httpGet:
            path: /ready
            port: 8080
          initialDelaySeconds: 5
          periodSeconds: 3
        livenessProbe:
          httpGet:
            path: /health
            port: 8080
```

```yaml
# Blue-Green with Argo Rollouts
apiVersion: argoproj.io/v1alpha1
kind: Rollout
metadata:
  name: api
spec:
  replicas: 10
  strategy:
    blueGreen:
      activeService: api-active
      previewService: api-preview
      autoPromotionEnabled: false    # manual promote
      prePromotionAnalysis:
        templates:
        - templateName: success-rate
  selector:
    matchLabels:
      app: api
```

```yaml
# Canary with Argo Rollouts
apiVersion: argoproj.io/v1alpha1
kind: Rollout
metadata:
  name: api
spec:
  replicas: 10
  strategy:
    canary:
      steps:
      - setWeight: 5
      - pause: {duration: 5m}
      - analysis:
          templates:
          - templateName: success-rate
          args:
          - name: service-name
            value: api
      - setWeight: 25
      - pause: {duration: 10m}
      - setWeight: 50
      - pause: {duration: 1h}
      - setWeight: 100
```

---

## İntervyu Sualları

- Blue-Green və Canary arasındakı əsas fərq nədir?
- Rolling update-də `maxUnavailable` və `maxSurge` nə üçündür?
- Canary deployment-də hansı metrikləri izləyirsiniz?
- Feature flag deployment strategy-nin necə dəyişdirir?
- Shadow deployment niyə write-heavy servis üçün problemlidir?
- Database migration zero-downtime-a necə uyğunlaşdırılır?
- Rollback-ın ən sürətli yolu hansı strategiya ilə mümkündür?
- A/B test və canary deployment nə ilə fərqlənir?
- "Deploy ≠ Release" prinsipi nə deməkdir?
- Argo Rollouts niyə default Kubernetes Deployment-dən üstündür?
- Backward-incompatible migration zaman zaman necə edilməlidir?
- 50 microservice-li sistemdə hansı strategiyanı seçərdiniz və niyə?
