# Is It My Service? (Junior)

## Problem (nə görürsən)
Alert sənin servisində işə düşdü. Amma error rate spike-nın səbəbi upstream və ya downstream olan nə isə ola bilər. Bütün komandanı oyatmadan əvvəl bir suala cavab verməlisən: bu həqiqətən mənim servisimdir, yoxsa mən başqasının outage-nin qurbanıyam?

Ümumi simptomlar:
- Servisindən `502 Bad Gateway` — amma backend qaydasındadır
- Xarici API-ya çağırışlarda timeout
- Auth servis işləmir, sənin servisin 401 qaytarır çünki token-lər yoxlana bilmir
- Asılılıq üçün DNS resolution uğursuz olur
- Redis əlçatan deyil, sənin servisin yıxılır çünki cache layer ölüb

## Sürətli triage (ilk 5 dəqiqə)

### Addım 1: Sənin servisin həqiqətən işləyir?

```bash
# Kubernetes
kubectl get pods -n production -l app=myapp
kubectl top pods -n production -l app=myapp

# Health endpoint
curl -f https://myapp.example.com/healthz

# Laravel health check
curl -f https://myapp.example.com/up
```

Əgər pod-lar CrashLoopBackOff və ya OOMKilled-dirsə, səbəb hələ də xarici ola bilər (məs., DB sıradan çıxması boot zamanı app-i crash etdirir), amma simptom sənin üstündədir.

### Addım 2: Çağırdığın asılılıqları yoxla

Servisinin çağırdığı bütün servis/API-ları siyahıla:
- Database (MySQL/Postgres)
- Cache (Redis)
- Queue broker (Redis/RabbitMQ/SQS)
- Daxili microservice-lər (auth, payments, search)
- Xarici API-lar (Stripe, SendGrid, Twilio, və s.)

Hər biri üçün status səhifəsini və ya dashboard-u yoxla. Əgər `payments-service` səhvlərdə spike-a malikdirsə və 2 dəqiqə əvvəl səninkindən başlayıb, sən onların probleminin downstream-indəsən.

### Addım 3: Infrastructure layer-ini yoxla

Hamıya təsir edən app-dən aşağı olan şeylər:
- Cloud provider statusu (AWS, GCP, Azure)
- CDN (Cloudflare, Fastly, Akamai)
- DNS resolution
- Load balancer sağlamlığı
- Kubernetes control plane

```bash
# DNS check
dig myapp.example.com
dig +short dependency-api.internal

# AWS service health
curl -s https://status.aws.amazon.com/rss/all.rss | head -50

# Cloudflare status
curl -s https://www.cloudflarestatus.com/api/v2/status.json | jq .
```

## Diaqnoz

### Upstream vs downstream terminologiyası

- **Upstream** = SƏNİ çağıran servis/sistemlər. Onlarda pis data göndərən bug olsa, sən onu səhv kimi görürsən.
- **Downstream** = SƏNİN çağırdığın servislər. Onlar yavaş və ya səhv verirsə, sən yavaş və ya səhv verən olursan.

### Hipotez ağacı

```
My error rate is up
├─ My code broke
│  └─ Check recent deploys → rollback candidate
├─ My infrastructure broke
│  ├─ Pods OOMKilled, CPU saturated → scale
│  ├─ Node failed → cordon/drain, replace
│  └─ Config change → revert
├─ My downstream is broken (most common!)
│  ├─ DB slow/down
│  ├─ Cache down
│  ├─ Internal service down
│  └─ External API slow/down
└─ My upstream sent bad input
   ├─ Client pushed new version with bug
   ├─ Webhook source changed payload format
   └─ Traffic spike (legitimate or attack)
```

### Toplanacaq dəlillər

Hər hipotez üçün dəlil:
- Downstream: asılılığın dashboard-u, onların alert-ləri, status səhifəsi
- Upstream: mənbəyə görə traffic rate, user-agent paylanması, coğrafi paylanma
- Öz kod: deploy timeline vs səhv başlanğıc vaxtı
- Infra: node health, network plugin, ingress controller

### Correlation ID tracing

Əgər hər request-də correlation ID / trace ID logluyursan:

```bash
# Find a failing request's trace
grep "status=500" storage/logs/laravel.log | head -1
# 2026-04-17 14:35:12 [ERROR] trace_id=abc123 status=500 ...

# Follow the trace across services
grep "abc123" /var/log/auth-service/*.log
grep "abc123" /var/log/payments-service/*.log
```

Əgər trace göstərir ki, auth-service sənin servisindən əvvəl 500 qaytarıb, cavabın əlindədir.

## Fix (qanaxmanı dayandır)

### Downstream-dirsə

1. Mümkündürsə degraded rejimə fallback et
   - Cache düşüb → birbaşa DB read-lər (yavaş amma işləyir)
   - Search servis düşüb → sadə LIKE query
   - Payment provider A düşüb → provider B-yə keç
2. Uğursuz asılılığı circuit break et ki, onun ağrısını artırmayasan
3. Downstream komandanı page et
4. Status səhifəsini yenilə: "degraded service due to external dependency"

### Upstream-dirsə

1. Mənbəni identifikasiya et (IP diapazon, user-agent, müştəri ID)
2. WAF/CDN səviyyəsində rate limit və ya blok et
3. O komanda/müştəri ilə kommunikasiya et

### Sənin üstündədirsə

1. Son deploy varsa rollback
2. Saturation varsa scale
3. İlişib qalıbsa restart (son variant)

### Downstream komandanı nə vaxt page etmək

Onları page et əgər:
- Servisləri sənin səhvlərinə töhfə verdiyi təsdiq olunub
- Sənin mitigation-un (cache, retry, fallback) tam örtə bilmir
- Onların status səhifəsi/kanalını yoxlamısan və heç kim cavab vermir

Onları page etmə:
- Onlarınkinə bənzəyən öz bug-ın üçün
- Onların elan etdiyi planlı texniki xidmət üçün
- Özü bərpa olan qısa blip üçün

## Əsas səbəbin analizi

Incident-dən sonra, sərhəd sualı hələ də post-mortem üçün vacibdir:
- Uğursuzluq harada başladı?
- Niyə sənə yayıldı?
- Circuit breaker / timeout / fallback-lar onu saxlaya bilərdimi?
- İzolyasiyaya sən sahibsən, yoxsa upstream/downstream komanda?

## Qarşısının alınması

- Bütün xarici çağırışların ətrafında circuit breaker-lər (Laravel: `laravel-zero/circuit-breaker`, və ya xam `Http::timeout()`)
- Hər network çağırışında timeout — PHP-nin default 0 timeout-u (limitsiz) fəlakətdir
- Exponential backoff + jitter ilə retry
- Graceful degradation: cached/stale data > error page
- Asılılıqları daxil edən health check-lər: `/healthz/deep` vs `/healthz/live`
- Öz sağlamlığınla yanaşı asılılıq sağlamlığını göstərən dashboard-lar

## PHP/Laravel üçün qeydlər

### Laravel HTTP client-də timeout-lar

```php
// Bad — no timeout
$response = Http::get('https://api.external.com/data');

// Good — explicit timeout and retry
$response = Http::timeout(3)
    ->retry(2, 100)
    ->get('https://api.external.com/data');
```

### Laravel-də asılılıqları yoxla

```php
// routes/console.php or a health check controller
Route::get('/healthz/deep', function () {
    $checks = [
        'db' => DB::connection()->getPdo() !== null,
        'redis' => Redis::ping() === 'PONG',
        'queue' => Queue::size() < 10000,
    ];
    $ok = !in_array(false, $checks, true);
    return response()->json($checks, $ok ? 200 : 503);
});
```

### Circuit breaker pattern

```php
use Illuminate\Support\Facades\Cache;

if (Cache::has('circuit:payments:open')) {
    return $this->fallbackResponse();
}

try {
    return Http::timeout(2)->get('https://payments.internal/charge');
} catch (\Throwable $e) {
    Cache::put('circuit:payments:open', true, now()->addSeconds(30));
    throw $e;
}
```

## Yadda saxlanacaq komandalar

```bash
# DNS
dig dependency.internal
nslookup dependency.internal

# Connectivity
nc -zv dependency.internal 443
curl -v --max-time 5 https://dependency.internal/healthz

# Check all kubernetes pods in namespace
kubectl get pods -n production
kubectl get events -n production --sort-by='.lastTimestamp' | tail -30

# Database
mysql -e "SELECT 1"
redis-cli PING

# AWS RDS
aws rds describe-db-instances --db-instance-identifier prod-mysql

# Trace a request (if using OTel)
curl -H "traceparent: 00-abc123..." https://myapp.example.com/api/thing
```

## Interview sualı

"Servisin səhv verməyə başlayır. Bunun həqiqətən sənin servisin olduğunu necə bilirsən?"

Güclü cavab:
- "Əvvəlcə öz pod-larımı və deploy timeline-ımı yoxlayıram. Asan eliminasiya."
- "Sonra asılılıq siyahımı gəzirəm: çağırdığım hər servis və API. Onların dashboard-ları, status səhifələri."
- "Fail olan request-i servislər arasında izləmək üçün correlation ID istifadə edirəm — əgər trace məndən əvvəl downstream-in səhv verdiyini göstərirsə, cavabımı tez alıram."
- "Infrastructure-i yoxlayıram: DNS, cloud provider statusu, Kubernetes control plane."
- "Kritik olaraq: 'mənim günahım olmasa belə', istifadəçilərim hələ də səhv görür. Ona görə əlim altında mitigation levy-lərim hazırdır — circuit breaker, fallback, degraded rejim — upstream komanda öz tərəflərini düzəldərkən onları çəkə bilirəm."

Müsahibə siqnalı: sistemləri bütöv düşünürsən, sahiblik = günah qəbul etmirsən və failure isolation üçün dizayn edirsən.
