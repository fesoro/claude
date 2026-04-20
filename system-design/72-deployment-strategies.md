# Deployment Strategies (Blue-Green, Canary, Shadow)

## Nədir? (What is it?)

Deployment strategy - yeni kod versiyasını production-a çıxarmaq üçün seçilən yanaşmadır. Məqsəd: downtime-ı minimumda saxlamaq, regression-ları tez aşkar etmək, təhlükəsiz rollback təmin etmək və DB migration-larla uyğun olmaq.

Bir neçə əsas strategiya var: **Recreate**, **Rolling**, **Blue-Green**, **Canary**, **Shadow**, **A/B Test**, **Feature Flag**. Hər biri downtime, risk, infra cost və rollback speed baxımından fərqlidir.

Qızıl qayda: **deploy ≠ release**. Kod production-a getmək (deploy) ilə istifadəçilərin onu görmək (release) ayrıla bilər - feature flag bu ayrılığı reallaşdırır.

## Requirements (Tələblər)

Müasir deployment pipeline aşağıdakıları təmin etməlidir:

1. **Zero-downtime** - istifadəçi request-i 5xx görməsin
2. **Fast regression detection** - yeni versiya xəta versə dəqiqələr içində aşkar olunsun
3. **Safe rollback** - əvvəlki versiyaya sekundlarla qayıtmaq
4. **DB migration support** - schema dəyişikliyi code deploy-u bloklmasın
5. **Progressive exposure** - 1% user ilə başla, observability loop ilə genişlət

## Deployment Strategies (Əsas Strategiyalar)

### 1. Recreate

v1-i tamamilə dayandır, sonra v2-ni başlat.

```
Time →
[v1 v1 v1]  → [   stopped   ] → [v2 v2 v2]
             ↑ downtime 30s-5min ↑
```

- **Plus**: sadə, resurs 1× qalır, state migration asan
- **Minus**: downtime var, istifadəçi 5xx görür
- **Nə vaxt**: batch job, cron worker, non-critical admin panel, staging

### 2. Rolling Update

Pod-ları N dənə-N dənə əvəzlə. Kubernetes Deployment-in default-u.

```
Step 1: [v1 v1 v1 v1]
Step 2: [v1 v1 v1 v2]   maxSurge=1
Step 3: [v1 v1 v2 v2]
Step 4: [v1 v2 v2 v2]
Step 5: [v2 v2 v2 v2]
```

- **Plus**: zero-downtime, resurs overhead kiçikdir
- **Minus**: eyni anda həm v1, həm v2 işlədir - backward compatibility tələb edir; rollback yavaşdır
- **Kubernetes knob**: `maxSurge` (əlavə pod limit), `maxUnavailable` (offline pod limit), `PodDisruptionBudget`

### 3. Blue-Green

İki tam ayrı environment - Blue (aktiv), Green (yeni). LB-ni bir kliklə Green-ə keçir.

```
Before switch:
                 ┌─────────[Blue v1]◀── users
    Load Balancer│
                 └─────────[Green v2]  (warm, 0 traffic)

After switch (instant):
                 ┌─────────[Blue v1]  (standby for rollback)
    Load Balancer│
                 └─────────[Green v2]◀── users
```

- **Plus**: instant cutover, instant rollback (LB-ni geri çevir), test full stack before flip
- **Minus**: 2× infra cost during overlap window, DB shared olduğu üçün schema compatible olmalıdır
- **Nə vaxt**: kritik release-lər, audit/compliance tələb olunan mühit

### 4. Canary

v2-ni kiçik faiz istifadəçiyə göstər, monitor et, tədricən genişləndir.

```
                          ┌── 95% ──▶ [v1 v1 v1 v1]  (baseline)
    Load Balancer / Mesh ─┤
                          └── 5%  ──▶ [v2]            (canary)

Progression: 1% → 5% → 25% → 50% → 100%
Halt/rollback if: error_rate(v2) > error_rate(v1) × 1.5
                  p99_latency(v2) > p99_latency(v1) × 1.3
```

- **Plus**: blast radius kiçik, real trafikdə yoxlanış, tədrici rollout
- **Minus**: orkestrasyon lazımdır (Flagger, Argo Rollouts, Spinnaker)
- **Canary vs A/B test**: canary **rollout** strategiyasıdır (hər iki versiya eyni feature-dir, sən sadəcə yeni buildi ölçürsən). A/B test **experiment**-dir (iki fərqli UX-i compare edirsən business metric üçün).

### 5. Shadow / Dark Launch

v2-ə traffic mirror et, amma cavabını istifadəçiyə göstərmə.

```
                     ┌──▶ [v1] ──▶ response ──▶ user
    Request ─────────┤
                     └──▶ [v2] ──▶ response ──▶ /dev/null
                              (compare silently)
```

- **Plus**: real load altında test, user impact yoxdur, performance regression tapır
- **Minus**: side-effect-ləri idarə etmək çətindir (DB write, notification, payment - duplicate olmasın), iki servis cost
- **Nə vaxt**: search ranking dəyişikliyi, ML model v2, critical path rewrite

### 6. Feature Flag (Release Toggle)

Kod production-dadır amma `if (flag)` ilə bağlıdır. Flag-ı runtime-da aç.

- **Kill switch** - bir kliklə feature söndür
- **Percentage rollout** - 5% user-ə ver, tədricən artır
- **Targeted** - ölkə, user tier, internal employee
- **Tool-lar**: LaunchDarkly, Unleash, Flagsmith, ConfigCat, Laravel Pennant
- **Flag debt** - köhnə flag-lar kodu şirəsiz edir. Hər flag-a TTL və owner qoy, quarterly cleanup.

### Progressive Delivery

Progressive delivery = **canary + feature flags + automated SLI analysis + rollback loop**. Argo Rollouts və Flagger bu loop-u avtomatlaşdırır.

## Traffic Splitting Layers (Trafikin Bölünməsi)

| Layer | Tool | Granularity |
|-------|------|-------------|
| DNS | Route53 weighted | Region/region |
| CDN | Cloudflare Workers, Fastly VCL | Header/cookie/geo |
| Load Balancer | NGINX `split_clients`, ALB weighted target groups | IP/header |
| Service Mesh | Istio VirtualService weight | Pod/namespace |
| Application | Feature flag client SDK | User ID, attributes |

NGINX canary split nümunəsi:

```nginx
split_clients "${remote_addr}${http_user_agent}" $upstream {
    5%     api_v2;
    *      api_v1;
}

upstream api_v1 { server backend-v1:8080; }
upstream api_v2 { server backend-v2:8080; }
```

## Kubernetes Rolling Update Mechanics

```yaml
spec:
  replicas: 10
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 2           # up to 12 pods during rollout
      maxUnavailable: 1     # at least 9 always ready
  template:
    spec:
      containers:
        - name: app
          readinessProbe:
            httpGet: { path: /health/ready, port: 8080 }
            periodSeconds: 5
          lifecycle:
            preStop:
              exec: { command: ["sh", "-c", "sleep 15"] }
```

`readinessProbe` gate edir - pod hazır olmayınca LB trafiki göndərməz. `preStop` hook SIGTERM-dən əvvəl connection drain verir.

## Canary Analysis (SLI Comparison)

Flagger / Argo Rollouts baseline (v1) ilə canary (v2) pod-larını müqayisə edir:

```yaml
# Flagger Canary analysis
analysis:
  interval: 1m
  threshold: 5          # breach count before rollback
  maxWeight: 50
  stepWeight: 10
  metrics:
    - name: request-success-rate
      thresholdRange: { min: 99 }
    - name: request-duration-p99
      thresholdRange: { max: 500 }
```

Hər intervalda canary SLI baseline-dan pis olsa, `threshold` dəfə üst-üstə - rollout dayandırılır və traffic geri qaytarılır.

## Blue-Green və DB Migration (Ən Çətin Hissə)

Blue və Green eyni DB-yə bağlıdır. Schema həm v1, həm v2 ilə işləməlidir. Həll: **expand-contract pattern**.

### Expand-Contract (Parallel Change)

Köhnə column `full_name` → yeni column `first_name`, `last_name` migration nümunəsi:

1. **Expand** - nullable `first_name`, `last_name` əlavə et. `full_name` qalır. Deploy.
2. **Dual write** - kod hər iki column-a yazır. Read hələ `full_name`-dən.
3. **Backfill** - background job bütün sətirlərdə `first_name`/`last_name` doldurur.
4. **Flip read** - kod yeni column-lardan oxuyur. `full_name` yalnız yazılır (safety net).
5. **Stop write** - `full_name`-ə yazma kəsilir.
6. **Contract** - `full_name` silinir.

Hər addım öz-başına deploy olunur. Heç bir addımda köhnə kod sınmır.

### Online Schema Change Tools

Böyük table-larda `ALTER TABLE` lock edir - production-da qəbul olunmur. Alətlər:

- **pt-online-schema-change** (Percona) - MySQL üçün shadow table + trigger
- **gh-ost** (GitHub) - MySQL, trigger-siz, binlog-based
- **pg-osc**, **pg_repack** - PostgreSQL
- **Stripe sqlhero**, **Shopify lhm** - app-level migration orchestration

Laravel qayda: **migrasiyaları deployment-dən ayır**. Migration-ı deploy-dan əvvəl ayrıca pipeline-da sürət (backward-compatible olduğundan), app deploy-u sonra.

## Rollback Strategies (Geri Qayıtmaq)

| Problem | Rollback Method | Speed |
|---------|-----------------|-------|
| Bad artifact | Redeploy previous image tag | 1-3 min |
| Feature bug | Toggle feature flag off | seconds |
| DB migration broken | Forward fix (yeni migration) | minutes-hours |
| LB misconfig | Revert LB config | seconds |
| Canary regression | Flagger auto-halt | auto |

**Forward fix** DB migration-larda lazımdır - əksər DDL reversible deyil (drop column qaytarılmaz). Ona görə expand-contract lazımdır.

## Zero-Downtime Deployment Checklist

1. **Graceful shutdown** - SIGTERM alır, yeni request qəbul etmir, mövcudları bitirir, sonra exit
2. **Readiness probe** - LB pod-a trafik yalnız hazır olduqda göndərsin
3. **Liveness probe** - deadlock olmuş pod-u kill etsin (yeniləmə ilə qarışdırma)
4. **Connection draining** - LB-də `deregistration_delay` 30-60 saniyə
5. **Backward-compatible API** - v1 client v2 servisə zəng edə bilsin
6. **Backward-compatible DB** - əvvəlki bənddə gördük (expand-contract)
7. **Idempotent requests** - retry zamanı duplicate yaratmasın

## Laravel Feature Flag Example

### Laravel Pennant (rəsmi, Laravel 10+)

```php
// app/Providers/AppServiceProvider.php
use Laravel\Pennant\Feature;

Feature::define('new-checkout', fn (User $user) => match (true) {
    $user->isInternalEmployee()        => true,
    $user->country === 'AZ'            => Lottery::odds(1, 20),  // 5%
    default                            => false,
});
```

```php
// app/Http/Controllers/CheckoutController.php
use Laravel\Pennant\Feature;

public function show(Request $request)
{
    if (Feature::for($request->user())->active('new-checkout')) {
        return view('checkout.v2');
    }
    return view('checkout.v1');
}
```

### Zero-Downtime Migration (Laravel)

```php
// Step 1: expand - deploy A
Schema::table('users', fn (Blueprint $t) => $t->string('first_name')->nullable());

// Step 2: backfill command (no DDL lock)
User::whereNull('first_name')->chunkById(1000, function ($users) {
    foreach ($users as $u) {
        [$first] = explode(' ', $u->full_name, 2) + [null];
        $u->update(['first_name' => $first]);
    }
});

// Step 3: after read flip - deploy B drops old column
Schema::table('users', fn (Blueprint $t) => $t->dropColumn('full_name'));
```

### Queue Worker Graceful Shutdown

Laravel queue worker SIGTERM-i handle edir - inflight job-u bitirir, sonra exit. Kubernetes-də `terminationGracePeriodSeconds: 120` qoy (queue job-un max runtime-ı qədər) və `command: ["php", "artisan", "queue:work", "--max-time=3600"]`.

## AWS Lambda / Serverless Deployment

Lambda alias üzərində weighted version:

```json
{
  "FunctionVersion": "42",
  "RoutingConfig": {
    "AdditionalVersionWeights": { "43": 0.05 }
  }
}
```

**CodeDeploy Canary** preset-ləri: `Canary10Percent5Minutes`, `Linear10PercentEvery1Minute`, `AllAtOnce`. CloudWatch alarm-lər rollback tetikler.

## Monitoring During Rollout (4 Golden Signals)

1. **Latency** - p50, p95, p99 request duration
2. **Traffic** - req/sec, baseline-dan deviation
3. **Errors** - 5xx rate, 4xx rate (user-side bug detection)
4. **Saturation** - CPU, memory, DB connection pool, queue depth

Plus **business metrics**: checkout conversion, signup rate, payment success - texniki metric yaxşıdır amma business metric çökürsə rollback.

## Cost Trade-offs

| Strategy | Infra Multiplier | Rollback Speed | Risk |
|----------|-----------------|----------------|------|
| Recreate | 1× | minutes | downtime |
| Rolling | 1-1.2× | minutes | partial users see new |
| Blue-Green | 2× (during window) | seconds | shared DB |
| Canary | 1.05-1.5× | seconds | orchestration complexity |
| Shadow | 2× | N/A | side-effects |

## GitOps Integration

Argo CD + Flagger Kubernetes-də declarative canary-ni həyata keçirir: developer `image: app:v2`-i git-ə push edir → Argo CD sync edir → Flagger yeni ReplicaSet-i canary kimi görür → Prometheus SLI-lərinə baxır, 5% → 25% → 100% genişləndirir → regression varsa avtomatik rollback.

## When to Use What (Strategiya Seçimi)

| Servis tipi | Tövsiyə olunan strategy |
|-------------|--------------------------|
| Kritik payment / checkout | Canary (1%→100%) + feature flag + shadow test |
| Read-only API (catalog) | Rolling update |
| Stateful DB migration | Blue-Green + expand-contract |
| Batch / cron job | Recreate |
| ML model update | Shadow → Canary |
| Frontend SPA | Blue-Green (CDN swap) |
| Internal admin panel | Rolling / Recreate |

## Interview Sualları

**Q1: Blue-Green və Canary arasında əsas fərq nədir, hansı nə vaxt seçilir?**
Blue-Green - iki tam identical environment (Blue = v1, Green = v2) var. LB bir anda bütün trafiki Green-ə keçirir. Rollback LB flip ilə 1 saniyədə olur. 2× infra cost var. Canary - yalnız kiçik faiz pod v2-dir, traffic tədricən dəyişir (1% → 100%). Overhead az, amma orkestrasyon (Flagger/Argo) lazımdır. Blue-Green seç: instant cutover lazımdır, audit/compliance sınaq bütün stack-də mümkün olsun deyir, infra cost qəbul edilir. Canary seç: blast radius minimum saxlamaq istəyirsən, real user-lərlə yoxlamaq lazımdır, progressive exposure vacibdir. Kritik payment servisdə çox vaxt ikisi birlikdə - blue-green infra topologiyası + canary-style traffic ramp.

**Q2: Deploy və release ayırmağın mənası nədir, feature flag niyə vacibdir?**
Klassik: kod master-ə merge, deploy edilir, eyni anda istifadəçi görür - deploy = release. Problem: deploy zamanı bug çıxsa, bütün user-lərə təsir edir, rollback kod artifact-ın dəyişməsini tələb edir. Feature flag kodu production-a göndərməyə (deploy) imkan verir, amma istifadəçiyə göstərməməyə (release ayrıca). Faydası: (1) kod gizli prod-da, real data ilə smoke test; (2) release - sadəcə config toggle, saniyələrlə kill switch; (3) targeted release - internal employee → 1% → 5% country-by-country; (4) A/B experiment infrastructure-u eynidir; (5) incident zamanı feature söndür, redeploy etmə. Qiyməti: flag debt - köhnə flag-lar code-u korlayır, hər flag-a TTL və owner qoy.

**Q3: Rolling update zamanı `maxSurge` və `maxUnavailable` nə edir?**
Bunlar Kubernetes Deployment strategy-sinin knob-larıdır. `maxSurge` - rollout zamanı desired replica-dan neçə pod əlavə ola bilər (əlavə 2 isə 10 yerinə 12 pod qısamüddətli olacaq). `maxUnavailable` - neçə pod offline qala bilər (əlavə 1 isə minimum 9 hazır qalmalıdır). `maxSurge=0, maxUnavailable=1` → overhead yox, amma yavaş, degraded capacity. `maxSurge=25%, maxUnavailable=0` → tam capacity qorunur, amma 25% əlavə resurs lazımdır. Production üçün typically `maxSurge=25%, maxUnavailable=0` tövsiyə olunur - SLO-nu pozmur. `PodDisruptionBudget` əlavə olaraq minimum ready pod sayını tətbiq edir ki, eyni anda rolling update + node drain baş versə sistem yıxılmasın.

**Q4: Blue-Green deployment-də DB-nin tək nüsxə olması niyə problemdir, necə həll olunur?**
Blue-Green iki application environment verir, amma DB adətən paylaşılır (iki DB sync etmək real-time practically imkansızdır). Problem: Green-ə `ALTER TABLE DROP COLUMN email_verified` migration-ını tətbiq etsəniz, Blue-də hələ o column-u oxuyan kod crash olur - rollback əzabındadır. Həll: **expand-contract pattern**. Schema dəyişiklikləri multi-step olur: (1) **expand** - yeni column/table əlavə et, köhnə qalır, ikisi eyni vaxt yaşayır; (2) kod dual-write edir; (3) backfill job data-nı köçürür; (4) read flip - kod yeni yeni column-dan oxuyur; (5) yalnız bütün versiya-lar yeni-ni oxuyandan sonra **contract** - köhnə column-u sil. Hər addım öz-başına deploy olunur, Blue-Green rollback hər addımda mümkündür.

**Q5: Canary analysis zamanı hansı metriclər istifadə olunur və regression necə aşkar edilir?**
Canary SLI-ləri baseline ilə müqayisə olunur, mütləq threshold deyil, **relative** threshold istifadə olunur - çünki trafik-dən asılı olaraq baseline dəyişir. Əsas metrics: (1) **error rate** - canary_errors / canary_requests > baseline_errors / baseline_requests × 1.5; (2) **p99 latency** - canary p99 > baseline p99 × 1.3; (3) **CPU/memory saturation** - resource leak; (4) **business metrics** - checkout conversion, signup success - technical metriclər yaxşı görünsə belə, conversion çökürsə rollback; (5) **custom health** - queue depth, DB connection usage. Flagger/Argo Rollouts Prometheus query-lərilə bunu avtomatlaşdırır: interval 1 dəqiqə, threshold breach-lər 5 dəfə üst-üstə olsa rollback. Eyni anda çox metric izlənsin, heç biri regression göstərməməlidir.

**Q6: Shadow deployment nədir, hansı çətinliklər var?**
Shadow (dark launch) - yeni versiyaya request mirror olunur, cavab isə istifadəçiyə qaytarılmır (yalnız log/compare üçün istifadə olunur). Məqsəd: real production trafiki altında v2-ni test etmək, user impact sıfır. Çətinliklər: (1) **side-effects** - v2 DB-yə yazır, email göndərir, payment edir - shadow-da bunlar təkrar baş verməməlidir. Həll: shadow environment read-only DB replica, sandbox payment gateway, mock notification. (2) **Cost** - iki tam servis lazımdır, shadow traffic real traffic qədərdir. (3) **Response comparison** - v1 və v2 cavablarını diff etmək lazımdır, binary response-larda çətin. (4) **Deterministic olmayan API** - timestamp, ID generation fərqli olur, structural diff lazımdır. İstifadə sahəsi: search/ranking v2, ML model v2, critical path rewrite - oxumaq əsasdır.

**Q7: Zero-downtime deployment üçün graceful shutdown necə işləyir?**
Pod silinən zaman Kubernetes bu ardıcıllığı icra edir: (1) Pod `Terminating` state-ə keçir, LB endpoint-dən çıxarılır (bu async-dir, bir neçə saniyə tələb edir); (2) `preStop` hook icra olunur - adətən `sleep 15` (LB drain-i gözləmək üçün); (3) `SIGTERM` əsas process-ə göndərilir; (4) `terminationGracePeriodSeconds` (default 30s) gözlənilir; (5) Hələ də işləyirsə `SIGKILL`. Application bu mərhələdə: yeni request qəbul etməməlidir (health check `/ready` 503 qaytarmalıdır), mövcud inflight request-ləri bitirməlidir, connection-ları bağlamalıdır. Laravel queue worker-də `--max-time` və SIGTERM handler inflight job-u bitirib exit edir. HTTP server (PHP-FPM, nginx) `graceful stop` command olmalıdır. Readiness probe-u `Terminating` olan kimi 503 qaytarmaq lazımdır ki, LB daha trafik yollamasın.

**Q8: Feature flag "debt" nədir və onu necə idarə etmək olar?**
Feature flag code-a `if (flag) { new } else { old }` branch əlavə edir. Release-dən sonra flag silinmirsə, kodda ölü if-else yığılır - oxumaq çətin, test coverage splitləşir, yeni developer çaşqın olur. Real nümunə: 200 flag var, onların 150-si 6 aydır true - heç kim silmir, qorxur. İdarəetmə: (1) **Flag TTL** - hər flag yaranarkən expiry date (90 gün) qoyulur, platform bunu reminder edir; (2) **Owner** - flag-ın sahibi (team/person) qeyd olunur, cleanup məsuliyyəti onundur; (3) **Type** - release flag (short-lived, rollout üçün), experiment flag (A/B, 30 gün), ops flag (kill switch, permanent), permission flag (permanent). Release flag 30-90 gün sonra silinməlidir. (4) **Quarterly cleanup** - sprint-lərə flag cleanup task-i daxil olunur; (5) **Linter** - CI köhnə flag istifadəsini tapan automated tool. LaunchDarkly/Unleash dashboard flag age göstərir.

## Best Practices

1. **Deploy ≠ release** - feature flag ilə kod və visibility-ni ayır
2. **Always have rollback plan** - hər deploy-dan öncə "necə geri qaytaracağıq" sualına cavab
3. **Backward-compatible schema** - expand-contract pattern, heç vaxt eyni addımda add + drop
4. **Automate canary analysis** - manual SLI izləmə insan xətası yaradır, Flagger/Argo Rollouts istifadə et
5. **Readiness probe düzgün** - DB/cache-i yoxlasın, yalanchy true qaytarmasın
6. **Graceful shutdown test edilib** - staging-də pod kill edərək test
7. **Progressive rollout** - 1% → 5% → 25% → 100%, peak hours-da dayandır
8. **Kill switch hər yerdə** - kritik feature üçün feature flag ilə bir kliklə söndür
9. **DB migration separation** - migration-ı app deploy-dan ayrı pipeline-da, backward-compatible formada icra et
10. **Monitor business metrics** - technical SLI-lər yaşıldır, amma conversion düşürsə rollback
11. **Blue-Green for critical flips** - audit tələb olunan frontend/API swap-lar üçün
12. **Shadow before canary** - ML/critical rewrite-da əvvəl shadow, sonra canary
13. **Feature flag hygiene** - TTL, owner, quarterly cleanup, 50-dən çox flag qorxulu siqnaldır
14. **Idempotent request design** - retry-lar safe olsun, duplicate payment yaratmasın
15. **GitOps declarative** - Argo CD ilə hər environment state-i git-də olsun
16. **Peak-aware deployment** - Friday 5PM və peak-hours-da kritik deploy yox
17. **Runbook hər strategy üçün** - canary halt olsa nə edirik, blue flip geri çevirmək üçün aduklar
18. **Practice rollback monthly** - real dəfə rollback işlət ki, muscle memory olsun
