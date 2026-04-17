# Performance Testing

## Nədir? (What is it?)

Performance testing, proqram təminatının müxtəlif yük şəraitində necə davrandığını ölçmək
prosesidir. Məqsəd sistemin sürət, sabitlik, scalability və resurs istifadəsi baxımından
tələblərə uyğun olduğunu təmin etməkdir.

Performance testing funksional testlərdən fərqlidir - "düzgün işləyir?" deyil, "nə qədər
sürətli və sabit işləyir?" sualına cavab verir. Production-a deploy etmədən əvvəl bottleneck-ləri
tapır və sistemin nə qədər yükə dözə biləcəyini müəyyən edir.

### Niyə Performance Testing Vacibdir?

1. **İstifadəçi təcrübəsi** - 3 saniyədən yavaş səhifə istifadəçilərin 40%-ni itirir
2. **Scalability planlaması** - Sistemin nə qədər istifadəçiyə xidmət edə biləcəyini bilmək
3. **Bottleneck tapma** - Ən yavaş hissələri müəyyən etmək
4. **SLA təminatı** - Response time, uptime tələblərini ödəmək
5. **Cost optimization** - Lazımsız infrastructure xərclərinin qarşısını almaq

## Əsas Konseptlər (Key Concepts)

### Performance Test Növləri

```
1. Load Testing
   ├── Normal/gözlənilən yükü simulyasiya edir
   ├── "100 concurrent user-ə davam edə bilir?"
   └── Ən çox istifadə olunan növ

2. Stress Testing
   ├── Sistemin limitlərini tapır
   ├── Yükü artırıb qırılma nöqtəsini müəyyən edir
   └── "Nə qədər yükə dözür?"

3. Spike Testing
   ├── Qəfil yük artımını simulyasiya edir
   ├── Black Friday, viral content ssenarilər
   └── "Qəfil 10x traffic-ə davam edə bilir?"

4. Soak/Endurance Testing
   ├── Uzun müddət yük altında saxlayır
   ├── Memory leak, resource exhaustion tapır
   └── "24 saat stabil işləyir?"

5. Capacity Testing
   ├── Maksimum istifadəçi sayını müəyyən edir
   ├── Infrastructure planlaması üçün
   └── "Nə qədər scale etməliyik?"
```

### Əsas Metriklər

| Metrik | Təsvir | Məqbul Hədd |
|--------|--------|-------------|
| Response Time | Cavab vaxtı | < 200ms API, < 3s səhifə |
| Throughput | Saniyədə request sayı (RPS) | Tətbiqə bağlı |
| Error Rate | Uğursuz request faizi | < 1% |
| Latency (P50) | Median cavab vaxtı | < 100ms |
| Latency (P95) | 95-ci percentile | < 500ms |
| Latency (P99) | 99-cu percentile | < 1s |
| Concurrent Users | Eyni anda aktiv istifadəçilər | Tətbiqə bağlı |
| CPU Usage | Prosessor istifadəsi | < 70% |
| Memory Usage | Yaddaş istifadəsi | < 80% |

### Percentile Anlayışı

```
100 request göndərdik, response time-ları sıraladıq:

P50 (Median): 50-ci request-in vaxtı → 120ms
  → Request-lərin yarısı bundan sürətli

P95: 95-ci request-in vaxtı → 450ms
  → Request-lərin 95%-i bundan sürətli

P99: 99-cu request-in vaxtı → 1200ms
  → Request-lərin 99%-i bundan sürətli

Niyə vacibdir: Average misleading ola bilər.
Average 200ms olsa belə, bəzi request-lər 5 saniyə çəkə bilər.
P95/P99 real istifadəçi təcrübəsini daha yaxşı göstərir.
```

## Praktiki Nümunələr (Practical Examples)

### k6 ilə Load Testing

```javascript
// load-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '1m', target: 50 },   // Ramp up to 50 users
        { duration: '3m', target: 50 },   // Stay at 50 users
        { duration: '1m', target: 100 },  // Ramp up to 100
        { duration: '3m', target: 100 },  // Stay at 100
        { duration: '2m', target: 0 },    // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'],  // 95% of requests under 500ms
        http_req_failed: ['rate<0.01'],     // Error rate under 1%
    },
};

export default function () {
    const BASE_URL = 'http://localhost:8000/api';

    // Login
    const loginRes = http.post(`${BASE_URL}/login`, JSON.stringify({
        email: 'test@example.com',
        password: 'password',
    }), { headers: { 'Content-Type': 'application/json' } });

    check(loginRes, {
        'login status is 200': (r) => r.status === 200,
        'login has token': (r) => JSON.parse(r.body).token !== undefined,
    });

    const token = JSON.parse(loginRes.body).token;
    const authHeaders = {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
    };

    // Get posts
    const postsRes = http.get(`${BASE_URL}/posts`, authHeaders);
    check(postsRes, {
        'posts status is 200': (r) => r.status === 200,
        'posts response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(1); // Think time
}
```

### k6 Stress Test

```javascript
// stress-test.js
import http from 'k6/http';
import { check } from 'k6';

export const options = {
    stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 200 },
        { duration: '5m', target: 200 },
        { duration: '2m', target: 300 },
        { duration: '5m', target: 300 },
        { duration: '2m', target: 400 },  // Qırılma nöqtəsini tap
        { duration: '5m', target: 400 },
        { duration: '5m', target: 0 },
    ],
};

export default function () {
    const res = http.get('http://localhost:8000/api/posts');
    check(res, {
        'status is 200': (r) => r.status === 200,
    });
}
```

### Locust ilə Load Testing (Python)

```python
# locustfile.py
from locust import HttpUser, task, between

class WebsiteUser(HttpUser):
    wait_time = between(1, 5)

    def on_start(self):
        """Login before starting tasks"""
        response = self.client.post("/api/login", json={
            "email": "test@example.com",
            "password": "password"
        })
        self.token = response.json()["token"]
        self.headers = {"Authorization": f"Bearer {self.token}"}

    @task(3)
    def view_posts(self):
        self.client.get("/api/posts", headers=self.headers)

    @task(1)
    def create_post(self):
        self.client.post("/api/posts", json={
            "title": "Load Test Post",
            "body": "Content for load testing."
        }, headers=self.headers)

    @task(2)
    def view_single_post(self):
        self.client.get("/api/posts/1", headers=self.headers)
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Query Performance Testing

```php
<?php

namespace Tests\Performance;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function posts_index_executes_within_acceptable_query_count(): void
    {
        User::factory()->has(Post::factory()->count(5))->count(10)->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(3, $queryCount,
            "Posts index should execute in 3 or fewer queries, got {$queryCount}");
    }

    /** @test */
    public function dashboard_loads_within_time_limit(): void
    {
        User::factory()->has(Post::factory()->count(10))->count(20)->create();

        $start = microtime(true);

        $response = $this->actingAs(User::first())
            ->get('/dashboard');

        $duration = (microtime(true) - $start) * 1000; // milliseconds

        $response->assertStatus(200);
        $this->assertLessThan(500, $duration,
            "Dashboard should load in under 500ms, took {$duration}ms");
    }

    /** @test */
    public function no_n_plus_one_queries_on_user_posts(): void
    {
        User::factory()->has(Post::factory()->count(3))->count(10)->create();

        $queries = collect();
        DB::listen(function ($query) use ($queries) {
            $queries->push([
                'sql' => $query->sql,
                'time' => $query->time,
            ]);
        });

        $users = User::with('posts')->get();
        $users->each(fn ($user) => $user->posts->toArray());

        $selectQueries = $queries->filter(
            fn ($q) => str_starts_with($q['sql'], 'select')
        );

        // 1 query for users + 1 query for all posts (eager loaded)
        $this->assertLessThanOrEqual(2, $selectQueries->count());
    }

    /** @test */
    public function api_response_time_under_threshold(): void
    {
        Post::factory()->count(100)->create();

        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $this->getJson('/api/posts');
            $times[] = (microtime(true) - $start) * 1000;
        }

        $average = array_sum($times) / count($times);
        $p95 = $this->percentile($times, 95);

        $this->assertLessThan(200, $average, "Average response time too high: {$average}ms");
        $this->assertLessThan(500, $p95, "P95 response time too high: {$p95}ms");
    }

    private function percentile(array $data, int $percentile): float
    {
        sort($data);
        $index = ceil(($percentile / 100) * count($data)) - 1;
        return $data[$index];
    }
}
```

### Memory Usage Testing

```php
<?php

/** @test */
public function large_data_export_does_not_exceed_memory_limit(): void
{
    Post::factory()->count(10000)->create();

    $memoryBefore = memory_get_usage(true);

    // Chunk istifadə edən export
    $exported = 0;
    Post::chunk(100, function ($posts) use (&$exported) {
        $exported += $posts->count();
    });

    $memoryAfter = memory_get_usage(true);
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // MB

    $this->assertEquals(10000, $exported);
    $this->assertLessThan(50, $memoryUsed,
        "Memory usage should be under 50MB, used {$memoryUsed}MB");
}

/** @test */
public function lazy_collection_uses_constant_memory(): void
{
    Post::factory()->count(5000)->create();

    $memoryBefore = memory_get_usage(true);

    Post::lazy()->each(function ($post) {
        // Process each post
        $post->title;
    });

    $memoryAfter = memory_get_usage(true);
    $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

    $this->assertLessThan(10, $memoryUsed,
        "Lazy collection should use minimal memory, used {$memoryUsed}MB");
}
```

### Benchmark Helper

```php
<?php

namespace Tests\Support;

class Benchmark
{
    public static function measure(callable $callback, int $iterations = 100): array
    {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $callback();
            $times[] = (hrtime(true) - $start) / 1_000_000; // ms
        }

        sort($times);

        return [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'p50' => $times[(int)(count($times) * 0.5)],
            'p95' => $times[(int)(count($times) * 0.95)],
            'p99' => $times[(int)(count($times) * 0.99)],
        ];
    }
}
```

## Interview Sualları

### 1. Load testing və stress testing arasındakı fərq nədir?
**Cavab:** Load testing gözlənilən/normal yükü simulyasiya edir - "100 user-ə xidmət edə bilirik?". Stress testing isə yükü artıraraq sistemin qırılma nöqtəsini tapır - "neçə user-ə qədər dözür?". Load testing keçsə sistem hazırdır, stress testing keçməsə normaldır, limit nöqtəsini bilmək üçündür.

### 2. P95 latency nə deməkdir və niyə average-dən vacibdir?
**Cavab:** P95 latency o deməkdir ki, request-lərin 95%-i bu vaxtdan az müddətdə cavab alır. Average misleading ola bilər - 99 request 50ms, 1 request 10 saniyə olsa, average 149ms olur amma 1 istifadəçi çox pis təcrübə yaşayır. P95/P99 real istifadəçi təcrübəsini daha doğru əks etdirir.

### 3. N+1 query problemi nədir və necə tapılır?
**Cavab:** N+1 problemi: 1 query users üçün + N query hər user-in posts-u üçün. 100 user = 101 query. Eager loading (`User::with('posts')`) ilə 2 query-ə endirilir. Testdə `DB::listen()` ilə query sayını count edib assert edirik. `laravel-query-detector` package otomatik tapır.

### 4. Soak testing nə üçün istifadə olunur?
**Cavab:** Soak (endurance) testing sistemi uzun müddət (saatlar/günlər) normal yük altında saxlayır. Memory leak, database connection pool tükənməsi, log fayllarının böyüməsi, cache overflow kimi zaman ərzində yaranan problemləri tapır. Short-duration testlər bu problemləri göstərmir.

### 5. Performance testing-də threshold-lar necə müəyyən edilir?
**Cavab:** SLA tələblərindən başlanır (məs. response < 200ms). Baseline test keçirilir - hazırkı performansı ölçür. Business tələbləri nəzərə alınır (concurrent users sayı). Industry standard-lar istifadə olunur (web page < 3s). Threshold-lar P95/P99 ilə ifadə olunmalıdır, average ilə yox.

### 6. k6, JMeter və Locust arasındakı fərqlər nələrdir?
**Cavab:** k6: JavaScript ilə yazılır, developer-friendly, CLI-based, cloud integration var. JMeter: GUI var, Java-based, plugin ecosystem geniş, enterprise standard. Locust: Python ilə yazılır, distributed testing asan, code-first approach. Kiçik komandalar üçün k6, enterprise üçün JMeter tövsiyə olunur.

### 7. Laravel-də performance optimization üçün nə edərsiniz?
**Cavab:** 1) Eager loading (N+1 həll), 2) Query caching (Redis/Memcached), 3) Route/config/view caching, 4) Database indexing, 5) Queue ilə ağır işləri async etmək, 6) CDN static assets üçün, 7) Pagination böyük data set-lər üçün, 8) Lazy collections memory optimization üçün.

## Best Practices / Anti-Patterns

### Best Practices

1. **Realistic scenarios yazın** - Real istifadəçi davranışını modelləyin
2. **Think time əlavə edin** - İstifadəçilər arasında gözləmə vaxtı qoyun
3. **Threshold-lar təyin edin** - P95 < 500ms kimi obyektiv metriklər
4. **Baseline yaradın** - Dəyişiklikləri müqayisə etmək üçün referans nöqtəsi
5. **Production-a yaxın mühitdə test edin** - Staging environment eyni spec-də olmalı
6. **Ramp-up istifadə edin** - Yükü tədricən artırın, birdən verməyin

### Anti-Patterns

1. **Yalnız happy path test etmək** - Error ssenariləri da yük altında test edin
2. **Average ilə qiymətləndirmək** - Percentile metriklər istifadə edin
3. **Kiçik data set-lə test etmək** - Production-a yaxın data həcmi lazımdır
4. **Cache-i nəzərə almamaq** - Cold start və warm cache ayrıca test edin
5. **Network latency-ni unutmaq** - Local testlər real şəraiti əks etdirmir
6. **Bir dəfə test edib unutmaq** - Performance regression üçün CI/CD-yə əlavə edin
