# Performance Testing: Load, Stress, Soak (Lead ⭐⭐⭐⭐)

## İcmal
Performance testing — sistemin müxtəlif yük şəraitlərindəki davranışını ölçən test növüdür. Load testing, stress testing, soak/endurance testing, spike testing — hər biri fərqli ssenari üçün nəzərdə tutulmuşdur. Lead səviyyəsindəki developer bu testləri yalnız bilmir — team üçün strategiya qurur, alətləri seçir, nəticələri interpret edir, SLO-larla birləşdirir.

## Niyə Vacibdir
Funksional testlər sistemin doğru işlədiyini göstərir; performance testlər "100 istifadəçidə doğru işləyir, 10,000-də necədir?" sualını cavablandırır. Black Friday, product launch, viral moment — bunların hamısı gözlənilmədən yük artımı yaradır. Performance test olmadan bu anlar outage ilə nəticələnir. Lead developer kimi bu test növlərini planlaya bilmək, SLO-larla bağlamaq, CI/CD-ə inteqrasiya etmək vacib bacarıqlardır.

## Əsas Anlayışlar

- **Load Testing**: Normal və gözlənilən peak yükləri simulasiya edir. Məqsəd: gündəlik istifadə şəraitini benchmark etmək. "500 concurrent user-ə cavab verə bilirikmi?" Metrikalar: response time, throughput, error rate.

- **Stress Testing**: Sistemin limitlərini aşmaq üçün progressiv yük artırılması. Breaking point-i tapmaq, graceful degradation-ı yoxlamaq. "Sistem necə çökür — xəta mesajı verirmi, yoxsa tamamilə dondurulurmu?" Normal capacity-nin 150-200%-inə qədər yük.

- **Soak Testing (Endurance Testing)**: Uzun müddət (saatlar, günlər) orta yük altında sistem. Memory leak, connection pool tükənməsi, log disk dolması, scheduled job akkumulasiyası aşkarlamaq. "Sistem 48 saat sonra da eyni sürətdədir?"

- **Spike Testing**: Qısa müddətdə kəskin yük artışı. Auto-scaling reaksiyasını, database connection pool-u yoxlamaq. Flash sale — 10 saniyədə 0-dan 5000 req/s.

- **Scalability Testing**: Yük artdıqca sistemin horizontal/vertical scale olub-olmadığını yoxlayır. Kubernetes HPA (Horizontal Pod Autoscaler) düzgün işləyirmi?

- **p50, p95, p99 latency**: p50 (median) — istifadəçilərin yarısı bu vaxtda cavab alır. p95 — 95% request bu vaxtdan tez cavab alır. p99 — "worst case for almost everyone". Ortalama yanlıdır: 99% request 50ms, 1% request 30s isə ortalama 350ms görünür, amma real istifadəçilərin 1%-i 30 saniyə gözləyir.

- **Throughput (RPS — Requests Per Second)**: Saniyədəki uğurlu request sayı. Load test-in əsas metrikası. Peak capacity planlaması üçün vacib.

- **Error Rate**: Xətalı response faizi. 1% threshold standard. Load altında error rate artırsa — bottleneck var.

- **Baseline əhəmiyyəti**: İlk performance test baseline yaradır. Sonrakı testlər bu baseline ilə müqayisə edilir. "Bu deployment-dan sonra p99 50ms artdı" — performance regression.

- **Ramp-up period**: Birbaşa peak yük vermək realist deyil. Tədricən artırmaq (ramp-up): 0 → 100 user 2 dəqiqədə. Sistem real-world davranışını göstərir.

- **k6 (Grafana k6)**: JavaScript ilə test scenario yazılır. CI/CD inteqrasiyası üçün ideal. Cloud da dəstəklənir. Developer-friendly syntax.

- **Apache JMeter**: GUI əsaslı, enterprise popular. XML-based test plan. Plugin ekosistemi geniş. Yüksək resource istehlakı.

- **Gatling**: Scala DSL, yüksək sürət. HTML report out-of-the-box. Backend-heavy sistemlər üçün.

- **Artillery**: YAML konfiqurasiya. Node.js, sürətli başlama. Serverless load testing dəstəyi.

- **Bottleneck identifikasiyası**: Database slow query (N+1, missing index, connection pool). Memory leak (soak test ilə aşkar edilir — heap artır, azalmır). CPU-intensive hesablamalar. Sync I/O blocking. Network bandwidth. Disk I/O (excessive logging).

- **Production-da test etməmək**: Staging environment-da — production ilə eyni infrastructure, real data-nın subset-i (ya da anonymized). Load test production-da edilmir — real istifadəçilərə təsir edir.

- **SLO ilə bağlantı**: Performance test SLO-nun hədəflərini verify edir. "p99 < 500ms" SLO-su → load test bu threshold-u yoxlayır. CI-da threshold keçilsə build fail olur.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Performance testing necə edirsiniz?" sualına tool adı saymaqla cavab vermə. "Layihəmizin SLO-ları var: p99 < 500ms, error rate < 1%. Bunu verify etmək üçün CI-da k6 ilə load test icra edirik. Hər major release-dən əvvəl stress test, memory leak üçün quarterly soak test." Bu struktur cavabdır.

**Junior-dan fərqlənən senior cavabı:**
Junior: "JMeter ilə load test edirik."
Senior: "k6 ilə CI pipeline-a load test əlavə etmişik. p95 < 300ms threshold keçilsə PR merge olmur. Baseline deployment-dan əvvəl çəkilir, sonra müqayisə edilir."
Lead: "Performance test strategiyasını SLO-larla bağlamışam. Error budget-in 20%-i performance regression-dan gəlirsə — feature freeze, performance fix priority."

**Follow-up suallar:**
- "Load test nəticəsini necə interpret edirsiniz?"
- "Memory leak tapırsanız nə edirsiniz?"
- "Production-da performance test etmək düzgündürmü?"
- "p50 vs p99 — hansı daha vacibdir?"
- "Performance regression necə aşkar edilir?"

**Ümumi səhvlər:**
- Yalnız happy path üçün load test (error scenario-lar yoxlanmır)
- Production-da load test etmək
- Ramp-up olmadan birbaşa peak yük vermək
- Baseline olmadan test nəticələrini dəyərləndirmək
- p50 (ortalama) ilə baxıb p99-u görmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Test növlərini fərqləndirmək "yaxşı"dır. Percentile metrikalarını, baseline comparison-ı, CI/CD inteqrasiyasını, SLO bağlantısını birlikdə izah etmək "əla"dır.

## Nümunələr

### Tipik Interview Sualı
"Yeni bir feature deploy etmədən əvvəl performance-ı necə yoxlarsınız?"

### Güclü Cavab
"Əvvəlcə feature-in hansı endpoint-ləri dəyişdirdiyini analiz edərəm. k6 ilə load test yazaram — normal peak traffic ssenarisi. Threshold: p95 < 300ms, error rate < 0.1%. CI pipeline-da bu test avtomatik işləyir, keçilsə PR merge olmur. Baseline — əvvəlki main branch üzərindəki test nəticəsi. Major release üçün stress test əlavə edirəm — capacity-nin 3x-ni simulasiya edirəm. Quarterly soak test 24 saat boyu 70% yük altında memory istifadəsini izlədiyimizi görür. Son incident: soak test-də görürük ki, 18 saatdan sonra memory 2x artır — EventListener-in unbind edilmədiyini tapdıq."

### Arxitektura Diaqramı — Performance Test Pipeline

```
Developer → PR açır
              │
              ▼
      ┌───────────────┐
      │  CI Pipeline  │
      │               │
      │ 1. Unit tests │
      │ 2. Integration│
      │ 3. Build      │
      │ 4. Load Test  │ ← k6 load test (5 dəq)
      │    (staging)  │   p95 < 300ms threshold
      └───────┬───────┘
              │ PASS
              ▼
      Deploy to Staging
              │
              ▼
    (Manual) Stress Test ← Major release üçün
              │
              ▼
      Deploy to Production
              │
              ▼
    (Quarterly) Soak Test ← 24 saat monitoring
```

### Kod Nümunəsi

```javascript
// ═══════════════════════════════════════════════════
// k6 — Load Test (Normal günlük yük)
// ═══════════════════════════════════════════════════
import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrikalar
const errorRate = new Rate('errors');
const apiLatency = new Trend('api_latency');
const successfulCheckouts = new Counter('successful_checkouts');

export const options = {
    // Load test ssenarisi — ramp up → steady → ramp down
    stages: [
        { duration: '2m', target: 50  },  // ramp-up: 2 dəqiqədə 50 user
        { duration: '5m', target: 50  },  // steady state: 5 dəqiqə
        { duration: '2m', target: 100 },  // ramp-up to peak: 100 user
        { duration: '5m', target: 100 },  // peak steady
        { duration: '2m', target: 0   },  // ramp-down
    ],
    thresholds: {
        // Bu threshold-lar CI-da fail edəndə build fails
        'http_req_duration': ['p(95)<300', 'p(99)<1000'], // 95% < 300ms, 99% < 1s
        'http_req_failed':   ['rate<0.01'],               // error rate < 1%
        'errors':            ['rate<0.01'],
    },
    // CI-da az log
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(95)', 'p(99)'],
};

const BASE_URL = __ENV.BASE_URL || 'https://staging-api.example.com';

export default function () {
    group('Product page', () => {
        const res = http.get(`${BASE_URL}/api/products/1`, {
            headers: { 'Authorization': `Bearer ${__ENV.API_TOKEN}` },
            tags: { name: 'product_page' },
        });

        const success = check(res, {
            'status is 200':     (r) => r.status === 200,
            'response time OK':  (r) => r.timings.duration < 300,
            'has product data':  (r) => JSON.parse(r.body).id !== undefined,
        });

        errorRate.add(!success);
        apiLatency.add(res.timings.duration);
    });

    sleep(1); // virtual user hər request arasında 1 saniyə gözləyir
}
```

```javascript
// ═══════════════════════════════════════════════════
// k6 — Stress Test (Breaking point tapma)
// ═══════════════════════════════════════════════════
export const options = {
    stages: [
        { duration: '2m',  target: 100  },  // normal load
        { duration: '5m',  target: 100  },
        { duration: '2m',  target: 200  },  // 2x
        { duration: '5m',  target: 200  },
        { duration: '2m',  target: 300  },  // 3x
        { duration: '5m',  target: 300  },
        { duration: '2m',  target: 400  },  // 4x — breaking point yaxınlaşır
        { duration: '5m',  target: 400  },
        { duration: '5m',  target: 0    },  // ramp-down — sistem bərpa olurmu?
    ],
    thresholds: {
        // Stress test-də threshold failure expected ola bilər — track edirik amma stop etmirik
        'http_req_duration': ['p(99)<3000'],  // 3 saniyə max
        'http_req_failed':   ['rate<0.10'],   // 10% error kabul edilir stress altında
    },
};

// İzlədiyimiz şey: hansı user sayında error rate artmağa başlayır?
// "400 user-dən sonra error rate 5%-ə qalxdı, bu bizim breaking point"
```

```javascript
// ═══════════════════════════════════════════════════
// k6 — Soak Test (Uzunmüddətli dayanıqlılıq)
// ═══════════════════════════════════════════════════
export const options = {
    stages: [
        { duration: '5m',  target: 70  },   // ramp-up
        { duration: '23h', target: 70  },   // 23 saat orta yük (70% capacity)
        { duration: '1h',  target: 0   },   // ramp-down
    ],
    // Memory leak aşkarlama üçün uzun müddət
};

// Soak test-də izlədiyimiz:
// - Response time zamanla artırsa? (t=0: 100ms, t=12h: 300ms — memory leak?)
// - Error rate artırsa? (connection pool tükənir?)
// - Server memory usage artan trend göstərirsə?
// Tool: Grafana dashboard-da memory, CPU, DB connections
```

```javascript
// ═══════════════════════════════════════════════════
// k6 — Spike Test (Flash sale simulasiya)
// ═══════════════════════════════════════════════════
export const options = {
    stages: [
        { duration: '10s', target: 10    },   // normal
        { duration: '1m',  target: 10    },
        { duration: '10s', target: 5000  },   // ani spike — 10 saniyədə 5000 user
        { duration: '3m',  target: 5000  },   // spike davam edir
        { duration: '10s', target: 10    },   // geri düşür
        { duration: '3m',  target: 10    },   // bərpa
        { duration: '10s', target: 0     },
    ],
};
// Auto-scaling (Kubernetes HPA) spike-ı idarə edirmi?
// DB connection pool bu yükdə tükənirmi?
```

```yaml
# CI/CD — GitHub Actions: Load Test Integration
# .github/workflows/performance.yml
name: Performance Tests

on:
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 2 * * 1'   # Həftəlik soak test — Bazar ertəsi saat 02:00

jobs:
  load-test:
    runs-on: ubuntu-latest
    if: github.event_name == 'pull_request'
    steps:
      - uses: actions/checkout@v4

      - name: Deploy to staging
        run: ./scripts/deploy-staging.sh

      - name: Wait for staging to be ready
        run: |
          until curl -s https://staging-api.example.com/health | grep '"status":"ok"'; do
            sleep 5
          done

      - name: Run k6 load test
        uses: grafana/k6-action@v0.3.1
        with:
          filename: tests/performance/load-test.js
        env:
          BASE_URL: ${{ vars.STAGING_URL }}
          API_TOKEN: ${{ secrets.STAGING_API_TOKEN }}

      - name: Upload results
        uses: actions/upload-artifact@v4
        with:
          name: k6-results
          path: results.json

  soak-test:
    runs-on: ubuntu-latest
    if: github.event_name == 'schedule'
    timeout-minutes: 1500   # 25 saat
    steps:
      - uses: actions/checkout@v4
      - name: Run 24h soak test
        uses: grafana/k6-action@v0.3.1
        with:
          filename: tests/performance/soak-test.js
        env:
          BASE_URL: ${{ vars.STAGING_URL }}
```

```php
// Performance Test üçün Laravel Artisan komanda
// Bottleneck aşkarlama helper-ları

// Slow query log aktiv etmək (development/staging)
// config/database.php-də:
// 'options' => [
//     PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION long_query_time = 0.1"
// ],

// Query performance analizi:
DB::listen(function ($query) {
    if ($query->time > 100) { // 100ms-dən yavaş
        Log::warning('Slow query detected', [
            'sql'      => $query->sql,
            'time_ms'  => $query->time,
            'bindings' => $query->bindings,
        ]);
    }
});

// N+1 detection — development-da:
// config/debugbar.php: 'collectors' => ['queries' => true]
// Laravel Telescope: query-ləri izlə
// Laravel Debugbar: N+1 warning göstərir

// Memory usage monitoring:
$memBefore = memory_get_usage(true);
// ... operation ...
$memAfter = memory_get_usage(true);
$diff = $memAfter - $memBefore;
if ($diff > 10 * 1024 * 1024) { // 10MB artım
    Log::warning('High memory usage in operation', [
        'before_mb' => $memBefore / 1024 / 1024,
        'after_mb'  => $memAfter / 1024 / 1024,
        'diff_mb'   => $diff / 1024 / 1024,
    ]);
}
```

### Müqayisə Cədvəli — Performance Test Növləri

| Test Növü | Yük profili | Müddət | Nəyi tapır | Alət |
|---------|-----------|--------|-----------|------|
| Load | Normal+peak | 10-30 dəq | Baseline performance | k6, JMeter |
| Stress | Artan (limit aşır) | 30-60 dəq | Breaking point | k6, Gatling |
| Soak | Orta, uzun | 12-48 saat | Memory leak, degradation | k6, Locust |
| Spike | Ani kəskin artım | 5-15 dəq | Auto-scaling, pool exhaustion | k6, Artillery |
| Scalability | Artan, izlənilən | 30-60 dəq | Scale-out davranışı | k6 + Prometheus |

### Key Performance Thresholds (Standard)

| Metrika | Kabul ediləbilən | Yaxşı | Əla |
|---------|-----------------|-------|-----|
| p50 latency | < 200ms | < 100ms | < 50ms |
| p95 latency | < 500ms | < 300ms | < 100ms |
| p99 latency | < 1000ms | < 500ms | < 200ms |
| Error rate | < 1% | < 0.1% | < 0.01% |
| Throughput | SLO-ya görə | - | - |

## Praktik Tapşırıqlar

1. k6 install edib lokal Laravel app-ına basic load test yaz — p95 threshold qoy.
2. Stress test keçir: sistemin breaking point-ini tap. Hansı user sayında error rate artır?
3. Soak test ssenarisi yaz: 2 saatlıq test. Memory usage trend-i izlə.
4. GitHub Actions-a k6 load test əlavə et — PR-larda avtomatik işləsin.
5. Baseline çək: `main` branch-dəki test nəticəsini saxla. Yeni branch ilə müqayisə et.
6. Bottleneck analizi: `DB::listen` ilə slow query-ləri tap. N+1 aşkar et.
7. Spike test: Flash sale simulasiyası — Kubernetes HPA nə qədər sürətlə scale edir?
8. SLO-larla bağlama: p99 < 500ms SLO-su üçün CI threshold qur, SLO pozulsa alert.

## Əlaqəli Mövzular

- [05-sla-slo-sli.md](../12-devops/05-sla-slo-sli.md) — SLO-lar performance threshold-larla necə əlaqəlidir
- [04-observability-pillars.md](../12-devops/04-observability-pillars.md) — Performance bottleneck-ləri izləmək
- [09-testing-in-cicd.md](09-testing-in-cicd.md) — Performance test CI/CD-də
