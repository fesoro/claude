# Load Testing (Senior)

## Nədir? (What is it?)

Load testing – sistemin real yük altında necə davrandığını anlamaq üçün süni trafik yaratmaq prosesidir. "API işləyirmi?" sualının cavabı unit test-dir; "API 1000 eyni anda sorğu zamanı işləyirmi?" sualının cavabı isə load test-dir. Backend developer kimi bottleneck-ləri production-a çatmadan tapmaq, PHP-FPM worker sayını, database connection pool-unu, Redis quraşdırmasını optimize etmək üçün load testing vacibdir. Əsas alətlər: **k6** (JavaScript, modern), **Apache Bench (ab)** (sadə, terminal), **Locust** (Python, distributed), **Gatling** (Scala/JVM, CI uyğun).

## Əsas Konseptlər (Key Concepts)

### Load Testing Növləri

```
LOAD TEST (yük testi):
  Gözlənilən normal yükü simulyasiya et
  Məqsəd: Sistem gözlənildiyi kimi işləyirmi?
  Nümunə: 100 eyni anda istifadəçi, 30 dəqiqə

STRESS TEST (stres testi):
  Sistemin həddini aş
  Məqsəd: Sistem necə uğursuz olur? Breaking point nədir?
  Nümunə: 100 → 500 → 1000 → 5000 concurrent user

SPIKE TEST (ani artım):
  Ani yük sıçrayışı
  Məqsəd: Ani trafik artımında sistem dayanıqlımı?
  Nümunə: 10 user → anında 1000 user → 10 user

SOAK TEST (davamlılıq testi):
  Uzun müddətli stabil yük
  Məqsəd: Memory leak, connection leak, database connection exhaustion tapın
  Nümunə: 200 concurrent user, 8+ saat

VOLUME TEST:
  Böyük data həcmi ilə test
  Məqsəd: Böyük dataset-lər performansa necə təsir edir?
  Nümunə: 10M row-lu cədvəldə sorğu

BREAKPOINT TEST:
  Tədricən artan yük — break point tapınca
  Məqsəd: Maksimum kapasitə nədir?
```

### Əsas Metriklər

```
VUs (Virtual Users) / Concurrent Users:
  Eyni anda sistemi test edən virtual istifadəçi sayı

Throughput (RPS - Requests Per Second):
  Sistemin saniyədə idarə etdiyi sorğu sayı
  Higher = better, amma error rate ilə birlikdə baxılmalı

Response Time (Latency):
  p50 (median): Sorğuların 50%-i bu vaxtda tamamlanır
  p90: Sorğuların 90%-i bu vaxtda
  p95: Sorğuların 95%-i bu vaxtda (User experience üçün əsas)
  p99: Sorğuların 99%-i bu vaxtda (Worst case)
  max: Ən uzun sorğu (spike-ları göstərir)

Error Rate:
  Uğursuz sorğuların nisbəti (HTTP 4xx/5xx, timeout)
  Yük altında error rate artırsa — bottleneck var

Apdex Score (Application Performance Index):
  0-1 arası — user satisfaction ölçüsü
  Apdex(T) = (Satisfied + 0.5 × Tolerating) / Total
  T = threshold (məs: 300ms)
  > 0.9 = Mükəmməl, 0.7-0.85 = Yaxşı, < 0.5 = Zəif
```

### Bottleneck Analizi

```
LOAD TEST → Yüksək latency/error rate → Bottleneck haradadır?

Yoxlama sırası:
1. CPU saturation (top, htop)
   → PHP-FPM worker-lər çox az?
   → Bəzən: OPcache konfiqurasiya problemi

2. Memory pressure (free -h, vmstat)
   → PHP process-lər çox RAM yeyir?
   → Redis/MySQL buffer pool kifayətli deyil?

3. Database (SHOW PROCESSLIST, slow query log)
   → Query-lər yavaşdır → index lazımdır
   → Connection pool bitib → max_connections artır
   → Lock contention → query optimize et

4. PHP-FPM pool
   → pm.max_children kiçikdir?
   → max_requests sıfırlanırsa worker restart olur → latency spike

5. External services
   → Payment gateway, email provider timeout verirsə
   → Circuit breaker yoxdur → cascade failure

6. Nginx
   → worker_processes, worker_connections
   → upstream keepalive mövcuddurmu?
```

## Praktiki Nümunələr (Practical Examples)

### k6 ilə Load Testing

```javascript
// k6 install: https://k6.io/docs/getting-started/installation/
// brew install k6  /  choco install k6  /  apt install k6

// basic-load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const apiDuration = new Trend('api_duration');

// Test konfiqurasiyası
export const options = {
  stages: [
    { duration: '1m', target: 50 },   // 0 → 50 VU ramp up
    { duration: '3m', target: 50 },   // 50 VU sabit
    { duration: '1m', target: 100 },  // 50 → 100 VU ramp up
    { duration: '3m', target: 100 },  // 100 VU sabit
    { duration: '1m', target: 0 },    // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% sorğu 500ms-dən az
    http_req_failed: ['rate<0.01'],    // 1%-dən az xəta
    errors: ['rate<0.01'],
  },
};

// Base URL
const BASE_URL = __ENV.BASE_URL || 'https://api.example.com';

export default function () {
  // Autentifikasiya
  const loginRes = http.post(`${BASE_URL}/api/login`, {
    email: 'test@example.com',
    password: 'password',
  }, {
    headers: { 'Content-Type': 'application/json' },
  });

  check(loginRes, {
    'login status 200': (r) => r.status === 200,
    'login has token': (r) => r.json('token') !== undefined,
  });

  errorRate.add(loginRes.status !== 200);

  if (loginRes.status !== 200) {
    return;
  }

  const token = loginRes.json('token');
  const headers = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };

  // Products list API
  const productsRes = http.get(`${BASE_URL}/api/products?page=1&per_page=20`, { headers });
  
  check(productsRes, {
    'products status 200': (r) => r.status === 200,
    'products has data': (r) => r.json('data') !== undefined,
  });
  
  apiDuration.add(productsRes.timings.duration);
  errorRate.add(productsRes.status >= 400);

  // Think time (real user davranışını simulyasiya et)
  sleep(1 + Math.random() * 2);  // 1-3 saniyə
}
```

```bash
# Test işlət
k6 run basic-load-test.js

# Environment variable ilə
BASE_URL=https://staging.example.com k6 run basic-load-test.js

# Output formatları
k6 run --out json=results.json basic-load-test.js
k6 run --out csv=results.csv basic-load-test.js

# Grafana + InfluxDB ilə real-time
k6 run --out influxdb=http://localhost:8086/k6 basic-load-test.js
```

### k6 Stress Test

```javascript
// stress-test.js — Breaking point tapma
import http from 'k6/http';
import { check } from 'k6';

export const options = {
  stages: [
    { duration: '2m', target: 100 },
    { duration: '2m', target: 200 },
    { duration: '2m', target: 400 },
    { duration: '2m', target: 800 },
    { duration: '2m', target: 1000 },
    { duration: '3m', target: 0 },    // cooldown
  ],
  thresholds: {
    http_req_failed: ['rate<0.1'],    // 10% xəta olana qədər davam et
  },
};

export default function () {
  const res = http.get(`${__ENV.BASE_URL}/api/products`);
  check(res, { 'success': (r) => r.status < 400 });
}
```

### k6 Soak Test

```javascript
// soak-test.js — Memory leak, connection leak tapma
export const options = {
  stages: [
    { duration: '10m', target: 200 },   // Ramp up
    { duration: '8h',  target: 200 },   // 8 saat stabil yük
    { duration: '5m',  target: 0 },     // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<1000'],  // Zamanla yavaşlama varsa soak test tutacaq
    http_req_failed: ['rate<0.05'],
  },
};
```

### Apache Bench (ab) — Sürətli Test

```bash
# Install: sudo apt install apache2-utils

# Sadə: 1000 sorğu, 50 eyni anda
ab -n 1000 -c 50 https://api.example.com/api/products

# Header ilə (auth token)
ab -n 1000 -c 50 \
   -H "Authorization: Bearer eyJ0eXAi..." \
   https://api.example.com/api/products

# POST sorğu
ab -n 500 -c 20 \
   -m POST \
   -T "application/json" \
   -p post_data.json \
   https://api.example.com/api/orders

# post_data.json:
# {"product_id": 1, "quantity": 2}

# Nəticə çıxışı:
# Requests per second: 432.15 [#/sec]
# Time per request: 115.702 [ms] (mean)
# 50%: 98ms  75%: 125ms  90%: 145ms  95%: 165ms  99%: 312ms
```

### Locust (Python) — Distributed Test

```python
# locustfile.py
from locust import HttpUser, task, between
from locust import LoadTestShape

class LaravelUser(HttpUser):
    wait_time = between(1, 3)  # 1-3 saniyə think time
    
    def on_start(self):
        """Her user başladıqda login"""
        response = self.client.post("/api/login", json={
            "email": "test@example.com",
            "password": "password"
        })
        self.token = response.json()["token"]
        self.client.headers.update({
            "Authorization": f"Bearer {self.token}"
        })
    
    @task(3)  # 3x tezlikdə (products daha çox sorğulanır)
    def list_products(self):
        with self.client.get("/api/products", catch_response=True) as response:
            if response.status_code == 200:
                data = response.json()
                if not data.get("data"):
                    response.failure("Empty data")
            else:
                response.failure(f"Got {response.status_code}")
    
    @task(1)
    def view_product(self):
        self.client.get(f"/api/products/{random.randint(1, 100)}")
    
    @task(1)
    def create_order(self):
        self.client.post("/api/orders", json={
            "product_id": random.randint(1, 50),
            "quantity": random.randint(1, 5)
        })


class SpikeTestShape(LoadTestShape):
    """Spike test: ani trafik artışı"""
    stages = [
        {"duration": 60, "users": 10, "spawn_rate": 10},
        {"duration": 120, "users": 200, "spawn_rate": 200},  # Spike!
        {"duration": 180, "users": 10, "spawn_rate": 10},
    ]
    
    def tick(self):
        run_time = self.get_run_time()
        for stage in self.stages:
            if run_time < stage["duration"]:
                tick_data = (stage["users"], stage["spawn_rate"])
                return tick_data
        return None
```

```bash
# Locust işlət
pip install locust

# Web UI ilə (browser-dan idarə)
locust -f locustfile.py --host=https://staging.example.com

# Headless (CI/CD üçün)
locust -f locustfile.py \
  --host=https://staging.example.com \
  --users 200 \
  --spawn-rate 20 \
  --run-time 5m \
  --headless \
  --csv=results
```

## PHP/Laravel ilə İstifadə

### PHP-FPM Tuning Load Test Nəticəsinə Əsasən

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
; Load test zamanı "max children reached" xətası görürsünüzsə:

pm = dynamic
pm.max_children = 50        ; Maksimum worker sayı
                              ; CPU cores × 4-8 (CPU-bound app)
                              ; CPU cores × 10-20 (I/O-bound app)
pm.start_servers = 10        ; Başlanğıcda açıq worker sayı
pm.min_spare_servers = 5     ; Minimum boş worker
pm.max_spare_servers = 20    ; Maksimum boş worker
pm.max_requests = 1000       ; Worker restart əvvəli maksimum request
                              ; Memory leak-ə qarşı qoruyur

; Timeout
request_terminate_timeout = 60s
```

```bash
# Load test zamanı PHP-FPM-i izlə
watch -n 1 'sudo php-fpm8.2 -t && sudo -u www-data php -r "
$fp = fsockopen(\"/var/run/php/php8.2-fpm.sock\", -1, \$errno, \$errstr);
\$req = \"GET /status HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n\";
fwrite(\$fp, \$req);
while (!\$feof(\$fp)) echo fgets(\$fp);
"'

# /status endpoint-ini aktiv et (pool config-də):
# pm.status_path = /status

# Metrics:
# active processes (load altında artır)
# max children reached (0 olmalı — artırsa pm.max_children artır)
# idle processes (çox boş varsa max_children azalt)
```

### Database Connection Pool Monitoring

```bash
# MySQL max connections
SHOW VARIABLES LIKE 'max_connections';  # Default: 151
SHOW STATUS LIKE 'Max_used_connections';  # Faktiki maksimum

# Aktiv sorğular (load test zamanı)
SHOW FULL PROCESSLIST;

# Connection limit problem:
# "Too many connections" xətası → max_connections artır
# Laravel config/database.php-də:
# 'options' => [PDO::ATTR_PERSISTENT => true] — connection reuse

# PgBouncer (PostgreSQL üçün connection pooler)
# Laravel-in özündəki pool = pconnect = həmişəkar connection
```

### Laravel Octane ilə Performance Müqayisəsi

```bash
# Standard PHP-FPM (hər request yeni process/state)
ab -n 5000 -c 100 http://localhost:8000/api/products
# Requests per second: ~150-300

# Laravel Octane + Swoole/RoadRunner
# Hər request eyni process-də (hot state, bootstrap yoxdur)
ab -n 5000 -c 100 http://localhost:8000/api/products
# Requests per second: ~1000-3000

# Octane quraşdırma
composer require laravel/octane
php artisan octane:install  # swoole/roadrunner seçin
php artisan octane:start --server=swoole --workers=4 --task-workers=2
```

### k6 CI/CD Pipeline İnteqrasiyası

```yaml
# .github/workflows/load-test.yml
name: Load Test

on:
  pull_request:
    branches: [main]

jobs:
  load-test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: testing
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup k6
        uses: grafana/setup-k6-action@v1
      
      - name: Start Laravel app
        run: |
          composer install --no-interaction
          cp .env.testing .env
          php artisan key:generate
          php artisan migrate --seed
          php artisan serve --port=8000 &
          sleep 3  # Server başlayana qədər gözlə
      
      - name: Run load test
        run: |
          k6 run \
            --env BASE_URL=http://localhost:8000 \
            --summary-trend-stats="avg,min,med,max,p(90),p(95),p(99)" \
            tests/load/basic-load-test.js
        
      - name: Upload results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: k6-results
          path: results.json
```

## Interview Sualları (Q&A)

**S1: Load test, stress test, spike test, soak test — fərqləri nədir?**
C: Load test — gözlənilən normal yükü test edir. Stress test — sistemin həddini aşır, breaking point tapır. Spike test — ani trafik artışına (məs: flash sale) cavabı test edir. Soak test — uzun müddətli stabil yük altında memory leak, connection leak kimi problemi tapır. Real production-da hamısı lazımdır.

**S2: p50, p95, p99 latency fərqi nədir? Hansını izləmək daha vacibdir?**
C: p50 (median) — sorğuların yarısı bu vaxtda tamamlanır. p95 — 95%-i bu vaxt edir, yəni 5% daha yavaşdır. p99 — 99%-i bu vaxt edir. User experience üçün p95 kritikdir. p99 edge case-ləri göstərir. Average hər zaman gizlə bilir — 90% çox sürətli, 10% çox yavaşsa average "yaxşı" görünür. Hər zaman percentile-larla izlə.

**S3: Load test zamanı "max children reached" xətası görürsənsə nə edirsən?**
C: PHP-FPM worker pool dolub deməkdir. Həll: (1) `pm.max_children` artır (RAM-a baxaraq), (2) php.ini-də `memory_limit` azalt, (3) Real bottleneck DB-dədirsə — sorğular yavaşdır, FPM worker uzun tutur. Profiling et: Blackfire, Telescope. (4) OPcache düzgün konfiqurasiya et.

**S4: k6 thresholds nə üçündür?**
C: Performance regression test üçün. `http_req_duration: ['p(95)<500']` deyir: "95% sorğu 500ms-dən az olmalıdır". Threshold pozulursa k6 non-zero exit code ilə çıxır → CI/CD pipeline fail olur. Hər deploy-da performance regression avtomatik aşkarlanır.

**S5: Load test staging-dəmi, production-damı aparılmalıdır?**
C: İdeal olaraq staging-də. Production data-sının həcmini test DB-sinə kopyalamaq lazımdır (əks halda real yükü simulyasiya etmirsiniz). Prod-a bənzər infrastructure: eyni instance tip, eyni MySQL version, eyni Redis konfiqurasiyası. Bəzi şirkətlər ("canary") production-da az trafiklə test aparır.

**S6: PHP-FPM-in `pm.max_children` dəyərini necə hesablayırsan?**
C: Hər PHP worker ortalama N MB RAM istifadə edir (production-da `ps aux | grep php-fpm` ilə yoxla). Server-də nə qədər RAM var? Formula: `(Total RAM - OS overhead) / per-process RAM`. Məsələn: 4GB RAM, OS 512MB, PHP worker 128MB → (3584 / 128) ≈ 28. Amma I/O-bound (DB, Redis çoxsa) CPU-bound-dan daha çox işçi saxlayırsınız.

## Best Practices

1. **Baseline müəyyən et** — deployment-dan əvvəl current performansı ölç.
2. **Realistic senariolar** — Real user behavior-u simulyasiya et: login, browse, order.
3. **Staging = Prod** — Eyni infrastructure, eyni data həcmi, eyni konfigurasiya.
4. **Think time əlavə et** — Real user-lər sorğu arası pauza edir; `sleep(1-3s)`.
5. **Threshold-lar qoy** — p95, error rate — CI/CD pipeline fail etsin.
6. **Monitoring ilə birləşdir** — Test zamanı Grafana-ya bax: PHP-FPM, MySQL, Redis.
7. **Profiling əlavə et** — Load test + Blackfire/XHProf → bottleneck-i dəqiq tap.
8. **Gradual ramp-up** — Ani 1000 user spike əvəzinə tədricən artır.
9. **Soak test unut** — Haftalıq/aylıq soak test keçir: memory leak, connection leak.
10. **CI pipeline-a daxil et** — Hər PR ilə smoke load test; major release-lərdə full load test.
11. **External service mock** — Stripe, SendGrid timeout-u mock et, real charge etmə.
12. **Database həcmini artır** — Test DB-ni prod-a bənzər data ilə doldur.

## Praktik Tapşırıqlar

1. k6 install edin; `basic-load-test.js` yazın: `/api/products` endpoint üçün 50 VU, 3 dəqiqə; p95 < 500ms threshold; nəticəni analiz edin
2. Apache Bench ilə `ab -n 1000 -c 50` işlədin; k6 ilə müqayisə edin — hansı daha çox məlumat verir?
3. Laravel staging-inizdə load test aparın: PHP-FPM `pm.status` izləyin; "max children reached" nədir?
4. Stress test aparın: 10 → 1000 VU; sistemin neçə VU-da "break" etdiyini müəyyən edin
5. Soak test (2 saat, 50 VU): memory usage artırsa memory leak var — onu tapın
6. k6 thresholds ilə GitHub Actions-a inteqrasiya edin: p95 < 500ms, error < 1%

## Əlaqəli Mövzular

- [Performance Tuning](30-performance-tuning.md) — PHP-FPM, OPcache, MySQL optimizasiya
- [Monitoring Prometheus](18-monitoring-prometheus.md) — Load test zamanı real-time metrik
- [Grafana](19-monitoring-grafana.md) — Load test dashboard, vizualizasiya
- [Nginx](11-nginx.md) — Worker connections, upstream timeout tuning
- [SLA/SLO/SLI](43-sla-slo-sli.md) — Performance threshold-lar SLO olaraq
- [Observability](42-observability.md) — Load test zamanı traces, metrics, logs
