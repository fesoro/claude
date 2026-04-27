# Load Testing (Lead ⭐⭐⭐⭐)

## İcmal

Load testing — sistemin müəyyən yük altında necə davrandığını ölçən test növüdür. Performance testing üst anlayışı altında: load test (normal yük), stress test (limit aşımı), soak test (uzun müddət yük), spike test (ani yük artımı) kimi alt növlər mövcuddur. Senior developer bu testləri bilir, Lead developer bunları infrastruktur qərarları ilə birlikdə planlaşdırır.

## Niyə Vacibdir

"Tətbiqimiz 10K concurrent user-ə dözür" — bu iddia test edilmədən sözdür. Real production incident-lərin böyük hissəsi gözlənilməyən yük artımından qaynaqlanır: flash sale, viral post, media coverage. Load test olmadan capacity planning, SLO təyin etmək, auto-scaling threshold seçmək — hamısı qeyri-dəqiqdir. Bir Lead developer deployment öncəsini load test nəticəsi ilə əsaslandıra bilməlidir.

## Əsas Anlayışlar

- **Test növləri:**
  - **Load test** — gözlənilən normal yük (baseline)
  - **Stress test** — limitin kəşfi (nə vaxt sınır?)
  - **Spike test** — ani artım (5x traffic 10 saniyədə)
  - **Soak test (endurance)** — uzun müddət sabit yük (leak, degradation)
  - **Breakpoint test** — davamlı artış, tamamilə yıxılana qədər

- **Key metrics:**
  - **VU (Virtual Users)** — concurrent simulyasiya olunan istifadəçi
  - **RPS/TPS** — requests/transactions per second
  - **Latency P50/P95/P99** — percentile latency
  - **Error rate** — 4xx/5xx faizi
  - **Throughput** — bytes/sec
  - **Apdex** — user satisfaction score

- **Alətlər:**
  - **k6** — JavaScript scripting, modern, CI/CD-ə uyğun
  - **Gatling** — Scala DSL, yüksək performance
  - **Apache JMeter** — GUI, çox geniş, köhnə amma davamlı
  - **Locust** — Python, distributed, developer-friendly
  - **Artillery** — YAML/JS, microservice yükü
  - **Vegeta** — Go, CLI, sadə HTTP load

- **Bottleneck növləri:**
  - **CPU-bound** — hesablama əməliyyatı, horizontal scale
  - **I/O-bound** — DB/disk gözləmə, connection pool/cache
  - **Memory-bound** — heap dolur, GC pressure
  - **Network-bound** — bandwidth, egress cost
  - **DB-bound** — query, connection, lock

- **Capacity planning:**
  - `Peak RPS = Average RPS × peak multiplier`
  - `Server count = Peak RPS / (1 server max RPS)`
  - Safety margin: 30-50% əlavə (spike üçün)

## Praktik Baxış

**k6 ilə temel load test:**

```javascript
// load_test.js
import http from 'k6/http';
import { sleep, check } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const failedRequests = new Counter('failed_requests');
const latencyTrend = new Trend('request_latency');

export const options = {
  stages: [
    { duration: '30s', target: 10 },   // ramp-up: 0 → 10 VU
    { duration: '1m', target: 100 },   // ramp-up: 10 → 100 VU
    { duration: '3m', target: 100 },   // plateau: 100 VU saxla
    { duration: '30s', target: 0 },    // ramp-down: 100 → 0
  ],
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'], // SLO
    http_req_failed: ['rate<0.01'],                  // Error rate < 1%
  },
};

export default function () {
  const BASE_URL = 'https://api.example.com';

  // 1. Authenticate
  const loginRes = http.post(`${BASE_URL}/auth/login`, JSON.stringify({
    email: 'test@example.com',
    password: 'password',
  }), {
    headers: { 'Content-Type': 'application/json' },
  });

  check(loginRes, {
    'login 200': (r) => r.status === 200,
    'has token': (r) => JSON.parse(r.body).token !== undefined,
  });

  if (loginRes.status !== 200) {
    failedRequests.add(1);
    return;
  }

  const token = JSON.parse(loginRes.body).token;

  // 2. Authenticated request
  const ordersRes = http.get(`${BASE_URL}/api/orders`, {
    headers: {
      Authorization: `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  latencyTrend.add(ordersRes.timings.duration);

  check(ordersRes, {
    'orders 200': (r) => r.status === 200,
    'has data': (r) => JSON.parse(r.body).data !== undefined,
    'latency ok': (r) => r.timings.duration < 500,
  });

  sleep(1); // Virtual user "think time"
}
```

**k6 — stress test:**

```javascript
// stress_test.js
export const options = {
  stages: [
    { duration: '1m', target: 100 },
    { duration: '2m', target: 500 },
    { duration: '2m', target: 1000 },
    { duration: '2m', target: 2000 },
    { duration: '2m', target: 0 },    // recovery
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // stress-də 2s qəbuledilən
    http_req_failed: ['rate<0.05'],    // 5% error rate
  },
};
```

**k6 — spike test:**

```javascript
// spike_test.js
export const options = {
  stages: [
    { duration: '10s', target: 10 },    // normal traffic
    { duration: '10s', target: 1000 },  // spike: 100x artım
    { duration: '3m', target: 1000 },   // spike saxla
    { duration: '10s', target: 10 },    // normal-ə qayıt
    { duration: '3m', target: 10 },     // recovery izlə
  ],
};
```

**Locust (Python) — daha mürəkkəb senariolar:**

```python
# locustfile.py
from locust import HttpUser, task, between
import json, random

class OrderUser(HttpUser):
    wait_time = between(1, 3)  # 1-3 saniyə düşünmə vaxtı
    token = None

    def on_start(self):
        """User "girişi" — hər VU üçün 1 dəfə"""
        response = self.client.post("/auth/login", json={
            "email": f"user{random.randint(1,1000)}@test.com",
            "password": "password"
        })
        self.token = response.json().get("token")

    @task(3)  # 3x ehtimallı (browse daha çox)
    def browse_orders(self):
        self.client.get("/api/orders", headers=self._headers())

    @task(1)  # 1x ehtimallı
    def create_order(self):
        self.client.post("/api/orders", json={
            "product_id": random.randint(1, 100),
            "quantity": random.randint(1, 5),
        }, headers=self._headers())

    def _headers(self):
        return {"Authorization": f"Bearer {self.token}"}
```

**Result interpretation:**

```bash
# k6 çalışdır
k6 run --out json=results.json load_test.js

# Summary çıxışı:
#
#          ✓ login 200
#          ✓ has token
#          ✗ latency ok
#            ↳  12% — 120 out of 1000
#
# http_req_duration.............: avg=387ms  min=45ms   med=210ms  max=3.2s   p(90)=890ms  p(99)=2.1s
# http_req_failed...............: 2.30%  ✗ 23 out of 1000
#
# FAILED: p(95)=890ms > threshold 500ms
```

**Sistemin darboğazını tapmaq:**

```bash
# Load test zamanı eyni anda:

# CPU izlə
watch -n 1 "top -bn1 | grep 'Cpu(s)'"

# Memory
watch -n 1 free -m

# DB connections
watch -n 1 "psql -c 'SELECT count(*) FROM pg_stat_activity'"

# Nginx connections
watch -n 1 "netstat -an | grep :80 | wc -l"

# PHP-FPM status
watch -n 1 "curl -s http://localhost/fpm-status?full | grep -E 'active|idle|total'"
```

**CI/CD inteqrasiyası:**

```yaml
# .github/workflows/performance.yml
name: Performance Tests

on:
  push:
    branches: [main]

jobs:
  performance:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Start services
        run: docker compose up -d

      - name: Run load test
        uses: grafana/k6-action@v0.3.0
        with:
          filename: tests/performance/smoke_test.js
        env:
          K6_CLOUD_TOKEN: ${{ secrets.K6_CLOUD_TOKEN }}

      - name: Fail on threshold breach
        run: |
          if [ $? -ne 0 ]; then
            echo "Performance regression detected!"
            exit 1
          fi
```

**Smoke test (CI üçün):**

```javascript
// smoke_test.js — CI-da hər deploy sonrası
export const options = {
  vus: 5,
  duration: '1m',
  thresholds: {
    http_req_duration: ['p(99)<500'],
    http_req_failed: ['rate<0.001'],
  },
};
```

**Trade-offs:**
- k6 — az resource, modern API, amma güclü GUI yoxdur
- JMeter — GUI güclü, amma Java heavy, setup uzun
- Locust — Python, extensible, amma k6-dan yavaş
- Load test environment = production ilə eyni olmalı (fərqli DB data volume!)
- Shared environment-də test = nəticə etibarsız

**Common mistakes:**
- Production-da test etmək (real user-ə təsir)
- Staging-i production ilə eyni konfigurasyonda kurmamaq
- Yalnız latency baxmaq (error rate-ə baxmamaq)
- Think time-sız test (real user heç dayanmadan sorğu göndərmir)
- Load test sonrası cleanup etməmək (test data-nı silmək)

## Nümunələr

### Real Ssenari: Pre-launch capacity planning

```
Tələb: E-commerce sayt media coverage gözlənilir, anlık 5K concurrent user.

Hazırlıq:
1. k6 ilə real user journey script yaz (browse → cart → checkout)
2. Staging-də 1K VU ilə test başlat
3. 800 VU-da P99 latency 500ms keçir, error rate artır
4. Bottleneck: DB connection pool dolu (pgBouncer 20 connection)
5. pgBouncer pool 50-yə artırıldı
6. 1.5K VU-da yeni bottleneck: PHP-FPM CPU 100%
7. Horizontal scale: 2 server → 4 server

Final test: 5K VU → P99 280ms, error rate 0.1%
Launch: media coverage gəldi, 4K peak → sayt sağlam qaldı
```

### Kod Nümunəsi

```javascript
// Realistic order flow test
import http from 'k6/http';
import { sleep, check, group } from 'k6';
import { SharedArray } from 'k6/data';

// Test məlumatları (fixture)
const users = new SharedArray('users', function () {
    return JSON.parse(open('./fixtures/test_users.json'));
});

export const options = {
    scenarios: {
        // 80% browse, 20% checkout
        browsing: {
            executor: 'constant-vus',
            vus: 80,
            duration: '5m',
            exec: 'browseProducts',
        },
        checkout: {
            executor: 'constant-vus',
            vus: 20,
            duration: '5m',
            exec: 'checkoutFlow',
        },
    },
    thresholds: {
        'http_req_duration{flow:browse}': ['p(95)<300'],
        'http_req_duration{flow:checkout}': ['p(95)<800'],
    },
};

export function browseProducts() {
    group('browse', function () {
        const res = http.get('https://api.test/api/products?page=1', {
            tags: { flow: 'browse' },
        });
        check(res, { 'products 200': (r) => r.status === 200 });
        sleep(2);
    });
}

export function checkoutFlow() {
    const user = users[Math.floor(Math.random() * users.length)];

    group('checkout', function () {
        // Login
        const token = authenticate(user);

        // Add to cart
        http.post('https://api.test/api/cart', JSON.stringify({
            product_id: 1, quantity: 1
        }), {
            headers: { Authorization: `Bearer ${token}` },
            tags: { flow: 'checkout' },
        });

        sleep(1);

        // Place order
        const orderRes = http.post('https://api.test/api/orders', JSON.stringify({
            payment_method: 'test_card'
        }), {
            headers: { Authorization: `Bearer ${token}` },
            tags: { flow: 'checkout' },
        });

        check(orderRes, { 'order created': (r) => r.status === 201 });
        sleep(3);
    });
}
```

## Praktik Tapşırıqlar

1. **k6 qur:** k6 install et, local Laravel API üçün sadə load test yaz (50 VU, 1 dəq), `p(95)<500ms` threshold qoy.

2. **Stages:** Ramp-up → plateau → ramp-down scenario yaz, load altında memory/CPU-u `htop` ilə izlə.

3. **Bottleneck tap:** Load test zamanı `SHOW POOLS` (pgBouncer) + `php-fpm status` + `top` eyni vaxtda izlə, hansı əvvəl dolur?

4. **CI inteqrasiya:** GitHub Actions-a smoke test əlavə et, threshold pozulsa workflow fail etsin.

5. **Report:** k6 HTML report generator ilə son test nəticəsini vizuallaşdır, P50/P95/P99 müqayisəsini şərh et.

## Əlaqəli Mövzular

- `11-apm-tools.md` — Load test zamanı APM metrikası
- `05-connection-pool-tuning.md` — Load altında connection pool bottleneck
- `01-performance-profiling.md` — Load test + profiling birlikdə
- `15-indexing-strategy.md` — Load altında query davranışı
- `09-async-batch-processing.md` — Queue yükünü load test ilə ölçmək
