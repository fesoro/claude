# DORA Metrics & Engineering Effectiveness (Lead)

## İcmal
DORA (DevOps Research and Assessment) — Google tərəfindən aparılmış geniş araşdırmadan çıxan 4 metrik dəstidir. Bu metriklər yüksək performanslı engineering komandalarını zəiflərdən ayırır. Hər metrik konkret, ölçülə bilən, tənbəlliklə oynana bilməyən göstəricidir.

## Niyə Vacibdir
"Bizim komanda yaxşı işləyir" subyektivdir. DORA məlumat əsaslı danışmağa imkan verir: "Deploy tezliyimiz ayda 2-dir, industry median həftə 1-dir." Engineering director-a, CPO-ya başa salmaq üçün rəqəm lazımdır. Düzəliş edib-etmədiyini görmək üçün baseline lazımdır.

## 4 Əsas DORA Metrik

### 1. Deployment Frequency (DF)
**Nə ölçür:** Production-a nə tez deploy edilir

| Performans Səviyyəsi | Tezlik |
|---------------------|--------|
| Elite | Gündə birdən çox |
| High | Gündə bir dəfə — həftədə bir |
| Medium | Həftədə bir — aylıq |
| Low | Ayda bir — daha az |

```php
// Ölçmə üsulu — CI/CD sistemindən (GitHub Actions log):
// Deploy workflow-larını say, zaman aralığına böl

// Sadə hesablama:
$deploys    = Deploy::where('environment', 'production')
                    ->whereBetween('deployed_at', [now()->subDays(30), now()])
                    ->count();
$frequency  = $deploys / 30; // gündə ortalama
```

### 2. Lead Time for Changes (LTF)
**Nə ölçür:** Commit-dən production-a nə qədər vaxt keçir

| Performans | Vaxt |
|-----------|------|
| Elite | 1 saatdan az |
| High | 1 gün — 1 həftə |
| Medium | 1 həftə — 1 ay |
| Low | 1 aydan çox |

```bash
# Git ilə hesablama:
# Commit timestamp-dən production deploy timestamp-ə qədər

git log --format="%H %ci" -- | head -20
# Deploy timestamp: CI/CD webhook-larından

# Lead time = deploy_time - first_commit_time (PR-dakı)
```

### 3. Mean Time to Recovery (MTTR)
**Nə ölçür:** Production incident-dən bərpaya qədər ortalama vaxt

| Performans | Vaxt |
|-----------|------|
| Elite | 1 saatdan az |
| High | 1 gün |
| Medium | 1 gün — 1 həftə |
| Low | 1 həftədən çox |

```php
$incidents = Incident::where('resolved_at', '!=', null)
    ->select(DB::raw('AVG(TIMESTAMPDIFF(MINUTE, started_at, resolved_at)) as mttr_minutes'))
    ->first();

// MTTR = sum(resolved_at - started_at) / count(incidents)
```

### 4. Change Failure Rate (CFR)
**Nə ölçür:** Deploy-ların neçə faizi rollback, hotfix, incident tələb etdi

| Performans | CFR |
|-----------|-----|
| Elite | 0–5% |
| High | 5–10% |
| Medium | 10–15% |
| Low | 15–30% |

```php
$totalDeploys  = Deploy::whereMonth('deployed_at', now()->month)->count();
$failedDeploys = Deploy::whereMonth('deployed_at', now()->month)
                       ->where('status', 'failed_or_rolled_back')
                       ->count();

$cfr = ($failedDeploys / $totalDeploys) * 100; // faiz
```

## Metriklər Arasındakı Tension

**DF ↑ + CFR ↑** = Sürətli deploy edirik amma xəta edirik → test / review quality problemi  
**DF ↓ + CFR ↓** = Az deploy, az xəta → batch size böyük, batch riski yüksək  
**LTF ↓ + MTTR ↑** = Sürətli deliver amma toparlanmaq çətin → observability problemi  

Hədəf: **DF ↑ + LTF ↓ + MTTR ↓ + CFR ↓** — bunları eyni vaxtda artırmaq

## PHP/Laravel Layihəsində Ölçmə

### GitHub Actions ilə DF/LTF
```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

jobs:
  deploy:
    steps:
      - name: Deploy
        run: ./deploy.sh production
      
      - name: Record deploy metrics
        if: success()
        run: |
          curl -X POST "${{ secrets.METRICS_WEBHOOK }}" \
            -d '{"event":"deploy","env":"production","commit":"${{ github.sha }}","timestamp":"'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'"}'
```

### Artisan Command ilə Metrics
```php
// app/Console/Commands/RecordDeployMetrics.php
class RecordDeployMetrics extends Command
{
    protected $signature = 'metrics:record-deploy {environment}';

    public function handle(): int
    {
        Deploy::create([
            'environment'  => $this->argument('environment'),
            'commit_sha'   => config('app.version'),
            'deployed_at'  => now(),
            'deployed_by'  => env('DEPLOY_USER', 'ci'),
        ]);
        
        return Command::SUCCESS;
    }
}

// deploy.sh-da:
// php artisan metrics:record-deploy production
```

### Incident Tracking
```php
// Incident yarandıqda (PagerDuty webhook, manual, ya da alert)
class IncidentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $incident = Incident::create([
            'title'      => $request->title,
            'severity'   => $request->severity,
            'started_at' => now(),
            'deploy_id'  => Deploy::latest('production')->first()?->id,
        ]);
        
        return response()->json($incident, 201);
    }
    
    public function resolve(Request $request, Incident $incident): JsonResponse
    {
        $incident->update([
            'resolved_at' => now(),
            'root_cause'  => $request->root_cause,
        ]);
        
        // Əgər deploy nəticəsindədirsə CFR-i yenilə
        if ($incident->deploy_id) {
            Deploy::find($incident->deploy_id)
                  ->update(['status' => 'failed']);
        }
        
        return response()->json($incident);
    }
}
```

## DORA Dashboard

```php
// DORA metrics hesablaması
class DoraMetricsService
{
    public function calculate(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to);
        
        $deploys = Deploy::whereBetween('deployed_at', [$from, $to])
                         ->where('environment', 'production')
                         ->get();
        
        $incidents = Incident::whereBetween('started_at', [$from, $to])
                             ->whereNotNull('resolved_at')
                             ->get();
        
        $df   = $deploys->count() / $days;
        $ltf  = $deploys->avg('lead_time_minutes');      // commit-dən deploy-a
        $cfr  = $deploys->where('status', 'failed')->count() / max($deploys->count(), 1) * 100;
        $mttr = $incidents->avg(fn(Incident $i) => $i->started_at->diffInMinutes($i->resolved_at));
        
        return compact('df', 'ltf', 'cfr', 'mttr');
    }
    
    public function classify(float $df, float $ltf, float $cfr, float $mttr): string
    {
        // Simplified: Elite olması üçün bütün metriklər Elite range-dədir
        if ($df >= 1 && $ltf <= 60 && $cfr <= 5 && $mttr <= 60) {
            return 'Elite';
        }
        if ($df >= 0.14 && $ltf <= 1440 && $cfr <= 10 && $mttr <= 1440) {
            return 'High';
        }
        return 'Medium or Low';
    }
}
```

## DORA-nı Artırmaq Üçün Praktik Addımlar

### DF artırmaq (deploy tezliyi)
- Feature flags tətbiq et → deploy etmək = release deyil
- PR ölçüsünü kiçilt (small batches)
- Automated tests → developer özbaşına merge edə bilsin
- Trunk-based development

### LTF azaltmaq
- PR review turnaround SLA: 2 iş saatı
- CI pipeline sürətini artır (paralel tests, caching)
- Code freeze azalt

### MTTR azaltmaq  
- Structured logging + distributed tracing (OpenTelemetry)
- Runbook-lar: hər incident növü üçün step-by-step
- Feature flags: anında rollback
- Automated rollback: healthcheck fail → öncəki versiyaya qayıt

### CFR azaltmaq
- E2E test suite
- Canary deployment: 5% traffic ilə başla
- Staging mühiti production-a bənzər olsun

## SPACE Framework (DORA-nın Tamamlayıcısı)

DORA yalnız delivery speed-i ölçür. SPACE daha geniş:
- **S**atisfaction — developer məmnuniyyəti (survey)
- **P**erformance — keyfiyyət, reliability
- **A**ctivity — commit, PR, review sayı
- **C**ommunication — async/sync collaboration
- **E**fficiency — flow state, interruption-lar

## Praktik Baxış

### Anti-pattern-lər
- **Metriklər üçün optimize etmək**: "Deploy tezliyini artırmaq üçün boş commit at" → metrik yüksəlir, dəyər yoxdur
- **Yalnız DF-ə baxmaq**: DF 10x artdı amma CFR da 5x artdı → real improvement yoxdur
- **İnkrement əvəzinə batch**: Böyük feature tam bitənə qədər merge etməmək → LTF yüksək
- **MTTR-ı gizlətmək**: "Incident yox idi" → incident log edilmirdi

### Trade-off-lar
- **Ölçmə infrastrukturunun qiyməti**: CI pipeline metrics, incident DB, dashboard — bunlar iş tələb edir. Kiçik komanda üçün overhead-i nəzərə al.
- **Vanity metrics vs actionable**: "PR sayı" ölçmək asan amma dəyərsiz. DORA 4-ü məqsədli.

## Praktik Tapşırıqlar

1. `deploys` cədvəli yarat, CI/CD-dən webhook ilə doldur; aylıq DF hesabla
2. `incidents` cədvəli yarat; MTTR hesablaması üçün `started_at` + `resolved_at`
3. Aylıq DORA report Artisan command yaz: 4 metrikin cari dəyəri + "Elite/High/Medium/Low" klasifikasiyası
4. Grafana dashboard: 30 günlük rolling window ilə 4 DORA metrik qraf
5. "Lead time" ölçmə: Git commit timestamp-dən GitHub Actions deploy finish-ə qədər

## Əlaqəli Mövzular
- [Architecture Decision Records](208-architecture-decision-records.md)
- [CI/CD & Deployment](080-ci-cd-and-deployment.md)
- [SLA/SLO/SLI/Error Budget](158-sla-slo-sli-error-budget.md)
- [Observability](145-observability-metrics-tracing.md)
- [Zero-Downtime Deployment](131-zero-downtime-deployment.md)
