# Chaos Engineering (Lead)

## İcmal

Chaos Engineering - production sistemləri üzərində məqsədli şəkildə eksperimentlər aparmaq discipline-dır. Məqsəd: sistemin turbulent şərtlərə (instance crash, network latency, dependency failure) davamlılığını qabaqcadan yoxlamaq və confidence yaratmaq.

Klassik test funksional davranışı yoxlayır: "kod düzgün nəticə verir?". Chaos engineering resilience-i yoxlayır: "bir şey sınsa, sistem ayaqda qalır?".

Netflix 2010-cu illərdə AWS-ə köçəndə bu yanaşmanı populyarlaşdırdı. `Chaos Monkey` təsadüfi olaraq production EC2 instance-larını dayandırırdı - bu qaydada komandalar "hər zaman crash baş verə bilər" deyə kod yazmağa məcbur oldu.

### Principles of Chaos (Netflix)

Netflix-in rəsmi principlesofchaos.org-da 5 əsas prinsip var:

1. **Hypothesis around steady-state** - eksperimentdən əvvəl "normal davranış" ölçüsünü təyin et (məs. checkout uğur nisbəti > 99.5%).
2. **Vary real-world events** - server crash, region failure, network packet loss, clock skew - real dünyada baş verən hadisələri simulyasiya et.
3. **Run experiments in production** - stage environment real trafikin, config-in, data-nın tam replikası deyil. Production-da (kontrollu) yoxlamaq lazımdır.
4. **Automate experiments continuously** - manual game day-lər yaxşıdır, amma daimi avtomatlaşdırılmış eksperimentlər regression aşkar edir.
5. **Minimize blast radius** - eksperiment zərər vursa belə, ən kiçik sahəyə (1 pod, 1 AZ, 1% traffic) dəysin.


## Niyə Vacibdir

'Sistem resilient-dir' demək yetmez — sübut etmək lazımdır. Netflix Chaos Monkey kimi fault injection production-da gizli zəiflikləri üzə çıxarır. Resilience-i yalnız chaos testi ilə sübut etmək mümkündür; GameDay planlanmış xaos tədbirlərini komandaya öyrədir.

## Niyə Lazımdır? (Why)

Distributed sistemlərdə latent failure-lar var - yalnız spesifik şərtlərdə (yüksək load + network latency + cache miss) üzə çıxır. İnteqrasiya testləri bunları tapa bilmir.

Chaos engineering aşağıdakıları validate edir:

- **Retry logic işləyir?** - downstream 500 qaytarsa, client real həyatda retry edirmi, yoxsa crash edir?
- **Circuit breaker açılır?** - dependency down olanda circuit breaker qoşulurmu, yoxsa thread pool dolur?
- **Fallback cache işləyir?** - Redis yıxılsa, sistem DB-yə düşür, yoxsa 500 qaytarır?
- **Timeout qoyulub?** - HTTP client-də infinite timeout varsa, bu yalnız real latency-də üzə çıxır.
- **Graceful degradation** - recommendation service down olsa, product page yüklənir, yoxsa bütün səhifə ölür?

Klassik nümunə: komanda Hystrix-ə güvənir, amma `@HystrixCommand` annotation-ını yanlış bean-ə qoyub - circuit breaker heç vaxt işləmir. Yalnız chaos experiment göstərir.

## Steady-State Metric (Baseline SLI)

Hər eksperimentin əsasında steady-state metric durur - business-level SLI. Nümunələr:

- **E-commerce**: checkout success rate per minute
- **Video streaming**: stream-starts per second
- **Ride-sharing**: successful ride matches per 5 min
- **Bank**: transaction approval rate

Metric dəqiq və real-time olmalıdır. Eksperiment zamanı:

1. Baseline-ı 10-15 dəqiqə ölçürsən
2. Fault inject edirsən
3. Metric-i izləyirsən - significant deviation (məs. >2%) varsa, eksperiment dayandırılır (abort).

## Fault Tipləri (Fault Types)

### 1. Infrastructure Faults

- **Instance termination** - EC2/VM-i gözlənilmədən söndürmək. Chaos Monkey.
- **AZ failure** - bütün availability zone-u offline etmək. Chaos Gorilla.
- **Region failure** - AWS region-u tam itirmək. Chaos Kong.
- **Node drain** - Kubernetes node-dan pod-ları köçürmək.

### 2. Network Faults

- **Latency injection** - dependency zənglərinə +500ms əlavə etmək.
- **Packet loss** - network-də 5-20% paket itkisi.
- **Network partition** - iki servisi bir-birindən ayırmaq (split-brain testing).
- **DNS failure** - resolve-u blokla.
- **Bandwidth throttling** - 100Mbps-dən 1Mbps-ə düşür.

### 3. Resource Exhaustion

- **CPU stress** - pod-da 100% CPU yaratmaq (stress-ng).
- **Memory pressure** - OOM-ə qədər memory doldurmaq.
- **Disk fill** - disk-i 95%-ə qədər doldurmaq.
- **File descriptor exhaustion** - limit-i doldur.

### 4. Application Faults

- **Exception injection** - kod random `RuntimeException` throw etsin.
- **Slow responses** - method response-a artificial delay əlavə.
- **Dependency failure** - external API-ni mocklayıb 503 qaytar.
- **Thread pool saturation**.

### 5. Data Faults

- **Corrupt messages** - Kafka topic-ə invalid message göndər.
- **Clock skew** - NTP-ni kəs, server-in saatını pozsun.
- **DNS poison** - yanlış IP qaytar.
- **Database connection kill** - aktiv connection-ları kəs.

## Tools (Alətlər)

### Chaos Monkey və Simian Army (Netflix)

Netflix-in açıq mənbəli alətlər toplusu:

- **Chaos Monkey** - production-da random EC2 instance-ları terminate edir
- **Chaos Gorilla** - bütün AZ-i offline edir
- **Chaos Kong** - AWS region-u itirir
- **Latency Monkey** - dependency zənglərinə latency əlavə edir
- **Conformity Monkey** - best practice pozuntularını tapır

Indi əksəriyyəti `Spinnaker` integration kimi yaşayır.

### Gremlin

Commercial SaaS platforma. UI, API, SDK var. Tez başlanğıc üçün ideal.

- Geniş fault katalogu (CPU, IO, network, shutdown, time)
- Blast radius və duration control
- "Halt" düyməsi - bütün eksperimentləri dərhal dayandırır
- SRE komandası üçün audit log

### Chaos Mesh (CNCF)

Kubernetes-native, CNCF incubating project. YAML CRD-lərlə eksperiment təyin edirsən.

```yaml
apiVersion: chaos-mesh.org/v1alpha1
kind: NetworkChaos
metadata:
  name: checkout-latency
  namespace: production
spec:
  action: delay
  mode: one
  selector:
    namespaces:
      - production
    labelSelectors:
      app: laravel-api
  delay:
    latency: "500ms"
    jitter: "100ms"
  duration: "5m"
```

### LitmusChaos (CNCF)

Kubernetes-native, GitOps friendly. ChaosEngine, ChaosExperiment, ChaosResult CRD-ləri. Prometheus integration hazırdır.

### AWS Fault Injection Simulator (FIS)

AWS-in managed xidməti. EC2, ECS, EKS, RDS üçün fault inject edir. IAM ilə icazə idarə olunur.

### Toxiproxy (Shopify)

Dev/CI üçün TCP proxy. Application ilə DB arasında qoyub latency/packet loss əlavə edirsən. Production üçün deyil, local testing üçün.

```bash
toxiproxy-cli toxic add mysql -t latency -a latency=1000
```

### Pumba

Docker container-ları üçün chaos aləti. `pumba kill`, `pumba netem delay` komandaları.

## Game Days (Planlı Xaos Tədbirləri)

Game day - komanda bir yerə toplaşıb planlı eksperiment keçirir. Strukturu:

1. **Planning** - hansı fault, hansı servis, hansı saat, kim observe edir
2. **Hypothesis** - "Redis 5 dəqiqə down olsa, checkout success rate 99% qalacaq"
3. **Notification** - on-call, customer support, business xəbərdar olur
4. **Execute** - fault inject olunur, metriklər izlənir
5. **Observe** - SLI deviation, alert-lər, log pattern-lər
6. **Abort conditions** - hansı şərtdə eksperiment dərhal dayandırılır
7. **Retrospective** - nə öyrənildi, hansı action item-lər

AWS Well-Architected Framework game day-ləri quarterly tövsiyə edir.

## Blast Radius Control

Eksperimentin təsir sahəsini kiçik saxlamaq prinsipi. Səhv get-sə zərər minimum olsun:

1. **Start small** - 1 pod, 1 instance ilə başla, sonra genişləndir
2. **Canary first** - 1% traffic → 5% → 25% → 100%
3. **Off-peak hours** - gecə saatında eksperiment (amma Netflix iddialı şəkildə iş saatlarında edir - on-call onsuz da orada)
4. **Feature flag kill switch** - `chaos_enabled=false` dərhal dayandırsın
5. **Abort conditions** - SLI 2% deviate etsə, avtomatik dayandırma (Gremlin "Halt", Chaos Mesh `schedule.suspend`)
6. **Single AZ initially** - bir AZ-də yoxla, sonra multi-AZ

## Prerequisites (Əvvəlcədən Hazır Olmalı)

Chaos engineering-i tətbiq etməzdən öncə bu komponentlər olmalıdır:

- **Observability** - metrics (Prometheus), tracing (Jaeger/OTEL), logs (ELK). Metric görmürsənsə, eksperimentin nəticəsini bilmirsən.
- **Alerting** - SLI breach-də alert tetiklənir
- **Runbooks** - hər failure scenario üçün nə etmək yazılıb
- **Rollback test edilib** - rollback real dəfə işlədilməsə, chaos zamanı işləməyəcək
- **Staging experiments first** - production-a getməzdən öncə eksperiment staging-də keçir
- **Blameless culture** - incident baş verəndə kimi günahlandırmaq yox, nə öyrənmək

Netflix belə deyir: "Chaos engineering without observability is just chaos".

## CI/CD ilə Integration

Chaos testing-i pipeline-ın bir hissəsi etmək:

```yaml
# .github/workflows/chaos-staging.yml
name: Chaos Tests (Staging)
on: [push]
jobs:
  chaos:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to staging
        run: kubectl apply -f k8s/staging/
      - name: Smoke test
        run: ./scripts/smoke-test.sh
      - name: Inject network latency
        run: kubectl apply -f chaos/network-latency.yaml
      - name: Verify SLI holds
        run: ./scripts/check-sli.sh --threshold 98
      - name: Cleanup chaos
        run: kubectl delete -f chaos/network-latency.yaml
```

Prod-da avtomatik chaos yalnız observability və SLI gauge-ləri hazır olduqda başlanır.

## Ən Çox Tapılan Problemlər (Common Findings)

Real şirkətlərin chaos experiment-lərində tez-tez tapılan şeylər:

1. **Missing timeouts** - `Guzzle` client-də timeout qoyulmayıb, slow dependency bütün PHP-FPM worker-ləri bloklayır
2. **Unbounded retries** - exponential backoff yoxdur, client dependency-ə 1000 req/sec yollayır → cascade failure
3. **Cache kritikdir, opsional deyil** - Redis down olsa, DB yıxılır. Halbuki cache yalnız "performance optimization" kimi təqdim olunurdu
4. **Config drift** - region A-da feature flag ON, region B-də OFF
5. **Health check yalandır** - `/health` endpoint DB-yə baxmır, pod "healthy" göstərir amma actual request-lər fail edir
6. **Thundering herd** - restart-dan sonra hamısı eyni anda cache warm edir, DB çökür
7. **Connection pool exhaustion** - slow query-lər pool-u doldurur, bütün request-lər timeout-a düşür
8. **Graceful shutdown işləmir** - SIGTERM-i ignore edir, aktiv request-lər kəsilir

## Laravel Chaos Middleware (Practical Example)

Production deyil, staging environment-də istifadə üçün Laravel middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ChaosMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('chaos.enabled')) {
            return $next($request);
        }

        $rate = config('chaos.rate', 0.02); // 2% requests

        if (mt_rand() / mt_getrandmax() > $rate) {
            return $next($request);
        }

        $fault = config('chaos.faults')[array_rand(config('chaos.faults'))];

        match ($fault) {
            'latency' => usleep(random_int(500_000, 2_000_000)),    // 0.5-2s sleep
            'error'   => abort(500, 'Chaos: simulated failure'),
            'timeout' => sleep(30),                                  // simulate dead dep
            'partial' => abort(503, 'Chaos: service unavailable'),
            default   => null,
        };

        return $next($request);
    }
}
```

`config/chaos.php`:

```php
return [
    'enabled' => env('CHAOS_ENABLED', false),
    'rate'    => (float) env('CHAOS_RATE', 0.02),
    'faults'  => ['latency', 'error', 'timeout', 'partial'],
];
```

### Horizon Queue Chaos

Random queue worker öldürmək - supervisor restart strategiyasını test etmək üçün:

```php
// app/Console/Commands/ChaosKillWorker.php
public function handle()
{
    if (app()->environment('production')) {
        $this->error('Refusing to run in production');
        return 1;
    }

    $workers = \Laravel\Horizon\Contracts\MasterSupervisorRepository::class;
    $pid = exec('pgrep -f "horizon:work" | shuf -n 1');

    if ($pid) {
        exec("kill -9 {$pid}");
        $this->info("Killed worker PID {$pid}");
    }
}
```

Cron ilə hər 15 dəqiqədən bir staging-də işlədirsən - Horizon-un failure recovery-sini validate edirsən.

### HTTP Client Fault Injection

Guzzle middleware ilə downstream dependency-ə fault inject:

```php
$stack = HandlerStack::create();
$stack->push(Middleware::mapRequest(function ($request) {
    if (config('chaos.http_fault') && mt_rand(1, 100) <= 5) {
        throw new \RuntimeException('Chaos: dependency failure');
    }
    return $request;
}));
```

Bu, retry və circuit breaker logic-ini real-istic test edir.

## Safety (Təhlükəsizlik)

1. **Blameless culture** - failure tapılanda komanda günahlandırılmır, process və arxitektura təkmilləşdirilir
2. **Business approval** - ilk prod eksperimenti üçün CTO/VP təsdiqi
3. **Customer communication** - customer-facing ola bilən eksperimentlər üçün status page hazır
4. **Reverse-trigger** - "Halt All" düyməsi hər zaman hazırdır, 1 kliklə bütün chaos dayanır
5. **On-call awareness** - eksperimentin vaxtı on-call rotation-a məlumdur
6. **Compliance check** - PCI/HIPAA environment-ində legal/compliance təsdiqi tələb olunur
7. **No chaos during incidents** - aktiv incident varkən yeni chaos başlamır
8. **Audit trail** - kim nə vaxt hansı eksperiment etdi, qeyd olunur

## Praktik Tapşırıqlar

**Q1: Chaos engineering və klassik testing arasında fərq nədir?**
Klassik testing funksional davranışı yoxlayır (unit, integration, E2E) - "kod düzgün nəticə verirmi?". Chaos engineering resilience-i yoxlayır - "kod nasazlığa necə reaksiya verir?". Unit test tək funksiyanı isolated test edir, chaos experiment distributed sistemin emergent davranışını turbulent şəraitdə yoxlayır. Chaos hypothesis-driven-dir: steady-state metric təyin edirsən, fault inject edirsən, metric-in qorunduğunu yoxlayırsan. Həmçinin chaos çox vaxt production-da aparılır, testing isə environments-də.

**Q2: "Minimize blast radius" nə deməkdir və necə tətbiq olunur?**
Eksperimentin potensial zərərini kiçik saxlamaq. Metodlar: (1) 1 pod və ya 1% traffic ilə başla, uğurlu olsa genişləndir; (2) feature flag kill switch - bir kliklə chaos söndürülür; (3) abort conditions - SLI 2%-dən çox deviate edərsə avtomatik dayandır; (4) bir AZ, bir region - multi-AZ-də heç vaxt başlama; (5) duration limit qoy - 5 dəqiqə, yoxsa unutsan saatlarla davam edər. Netflix-də `Chaos Monkey` yalnız business hours-da işləyir çünki on-call əldə hazırdır.

**Q3: Steady-state hypothesis necə formulə edilir?**
Business-level SLI-dir, texnoloji yox. Pis nümunə: "CPU 80%-dən aşağı qalacaq". Yaxşı nümunə: "checkout success rate per minute 99.5%-dən yuxarı qalacaq". Metric: (1) real-time ölçülməlidir (1-5 dəqiqə window); (2) customer impact-ı əks etdirməlidir; (3) normal variation məlum olmalıdır (baseline 10 dəqiqə ölçülür). Eksperiment: baseline → fault inject → SLI müşahidə. Əgər 2%-dən çox deviation varsa, hypothesis yalnışdır - sistem resilient deyil, arxitektura düzəldilməlidir.

**Q4: Production-da chaos etmək təhlükəli deyil?**
Təhlükəli ola bilər, amma kontrollu edildikdə staging-dən daha faydalıdır. Səbəb: staging real production trafikini, real data volume-unu, real third-party dependency-ni əks etdirmir. Bu problemlər yalnız production-da üzə çıxır - məsələ chaos etməməkdir, seçimdir: "öz vaxtımda, kontrollu şəkildə sındıraq" vs "müştəri üçün peak Friday evening-də sınacaq". Təhlükəsizlik üçün: blast radius minimum, kill switch hazır, observability tam, business təsdiq edib, on-call awareness. Netflix, Slack, LinkedIn, Microsoft hamısı production-da chaos etməkdə.

**Q5: Circuit breaker qurmuşuq, lakin chaos experiment göstərdi ki, dependency down olanda sistem hələ də yıxılır. Səbəbi nə ola bilər?**
Bir neçə ehtimal: (1) **Circuit breaker threshold çox yüksəkdir** - 50% failure-dan sonra açılır, amma dependency artıq 100% fail edir və hər yeni request təkrar pool-u tutur; (2) **Thread pool bulkhead yoxdur** - circuit açılsa da, mövcud slow request-lər thread-ləri bloklayır; (3) **Circuit yanlış bean-ə tətbiq olunub** - annotation düzgün injection nöqtəsində deyil; (4) **Fallback yoxdur** - circuit açılır, amma exception throw edir, client 500 görür; (5) **Timeout qoyulmayıb** - circuit açılmazdan öncə request 30s gözləyir. Chaos experiment hər dörd problemi də üzə çıxarır.

**Q6: Chaos Monkey, Gremlin və Chaos Mesh arasında hansını seçmək lazımdır?**
**Chaos Monkey** - AWS EC2 üçün, dar scope (instance termination), open-source, Spinnaker integration. Çoxlu fault növü lazımdırsa kifayət etmir. **Gremlin** - commercial SaaS, UI-driven, SRE-friendly, geniş fault katalogu, audit/RBAC, tez başlanğıc üçün ideal, amma bahalı. **Chaos Mesh / LitmusChaos** - Kubernetes-native, YAML CRD-lər, GitOps friendly, open-source, Prometheus integration. K8s ekosistemində olursan, bunu seç. AWS-də EKS kullanırsansa AWS FIS-i də baxmaq lazımdır (IAM integration var). Mənim tövsiyəm: K8s varsa Chaos Mesh ilə başla, enterprise SaaS lazımdırsa Gremlin-ə bax.

**Q7: Game day və automated chaos arasında fərq nədir?**
**Game day** - planlı, vaxtı təyin edilmiş tədbir. Komanda bir yerdə toplaşır, hypothesis yazılır, fault inject olunur, hamı real-time observe edir, retrospective keçirilir. AWS quarterly tövsiyə edir. Məqsəd: komandanın incident response prosesini test etmək, runbook-ları validate etmək, knowledge gap-ları tapmaq. **Automated chaos** - CI/CD və ya scheduled chaos daima işləyir, regression-ları tutur. Nümunə: Netflix `Chaos Monkey` iş saatlarında random instance terminate edir. Hər ikisi lazımdır - game day öyrənmə üçün, automation regression prevention üçün. Game day-siz automation boş qalır (tapılan problemlər heç vaxt discuss edilmir), automation-suz game day-lər rare event qalır.

**Q8: Observability olmadan chaos engineering tətbiq etmək olar?**
Yox, məna daşımır. Chaos experiment-in əsası steady-state metric-dir - metric görmürsənsə, fault-un təsirini görmürsən. Eksperiment kor-koranə aparılar, müştəri incident-i olar amma heç kim tapa bilməz. Əvvəl bu olmalıdır: (1) **Metrics** - Prometheus/DataDog ilə SLI-lər ölçülür; (2) **Distributed tracing** - Jaeger/OTEL, request hansı servisdən keçir görünür; (3) **Structured logging** - ELK/Loki, error-ları filter etmək mümkündür; (4) **Alerting** - SLI breach-də PagerDuty alert. Netflix-in prinsipi: "observability must come before chaos". Əks halda sən sadəcə production-u sındırırsan, öyrənmirsən.

**Q9: Laravel monolith üçün chaos engineering dəyərlidirmi?**
Dəyərlidir, amma fərqli fault-lara fokuslan. Microservices-də əsas chaos dependency failure, network partition-dır. Monolith-də: (1) **Database faults** - read replica lag, connection pool exhaustion, slow query; (2) **External dependencies** - Stripe API down, SMS gateway timeout; (3) **Cache failures** - Redis down, cache stampede; (4) **Queue failures** - Horizon worker crash, Redis connection itkisi; (5) **Resource exhaustion** - PHP-FPM worker saturation, memory leak. Toxiproxy + Laravel chaos middleware kifayətdir. Full Chaos Mesh artıqdır. İlk question: "Stripe 30 saniyə down olsa, checkout page yüklənirmi?" - əksər Laravel app bunu uğursuz keçir.

**Q10: Chaos engineering nə vaxt başlatmaq ERKƏNDİR?**
Bu şərtlərdə hələ başlama: (1) **Observability yoxdur** - əvvəl Prometheus/Grafana qur; (2) **Rollback test edilməyib** - rollback özü chaos zamanı fail edə bilər; (3) **MTTR yüksəkdir** - incident-lər 4+ saat çəkir, əvvəl on-call process düzəldilməli; (4) **Culture blaming-dir** - hər incident-də kimi günahlandırırsan, chaos-dan heç kim qorxmayacaq; (5) **Single point of failure kritikdir** - əvvəl redundancy qur; (6) **Tək bir eng-lik komanda** - incident olsa kim response edəcək? Chaos engineering əlavə maturity tələb edir, foundational reliability practices olmadan xərc çox olur.

## Praktik Baxış

1. **Observability əvvəldir** - chaos başlamazdan əvvəl metrics, tracing, logging tam olmalıdır
2. **Start in staging** - first experiment staging-də keç, sonra production-a
3. **Hypothesis-driven** - hər eksperimentin dəqiq steady-state metric-i olmalıdır
4. **Small blast radius** - 1 pod, 1% traffic, 5 dəqiqə ilə başla
5. **Abort conditions hardcode edilib** - SLI threshold-da avtomatik dayandırma
6. **Game day quarterly** - komandanı incident-ə həqiqətən hazırlamaq üçün
7. **Automate regression chaos** - scheduled experiments CI/CD-də
8. **Blameless postmortem** - chaos tapdığı problemə görə heç kim günahlandırılmır
9. **Kill switch hazır** - bir config flag ilə chaos söndürülür
10. **Prod chaos yalnız iş saatlarında** - gecə 3-də on-call yoxdur
11. **Config feature flag-ində** - `chaos.enabled`, `chaos.rate` - environment-specific
12. **Toxiproxy ilə local** - dev-də real latency/loss simulyasiyası
13. **Horizon queue chaos** - worker kill → supervisor restart path-ını validate
14. **Audit log** - kim, nə vaxt, hansı fault - compliance üçün
15. **On-call rotation ilə sync** - eksperiment on-call üçün sürpriz olmasın
16. **Runbook hər failure mode üçün** - yeni failure mode tapsan dərhal runbook yaz
17. **Practice rollback** - rollback path-ını aktiv işlət, muscle memory yarat
18. **Gradual adoption** - infra → network → application → data faults


## Əlaqəli Mövzular

- [SLA/SLO](44-sla-slo-sli.md) — error budget xərclənməsi
- [Circuit Breaker](07-circuit-breaker.md) — chaos-da resilience mexanizmi
- [Disaster Recovery](30-disaster-recovery.md) — DR planını sınaqdan keçirmək
- [Backpressure](57-backpressure-load-shedding.md) — yük altında sistem davranışı
- [Multi-Region](85-multi-region-active-active.md) — regional failover sınaqları
