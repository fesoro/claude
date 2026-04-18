# Reading Metrics

## Problem (nə görürsən)
Grafana və ya Datadog dashboard. Bir neçə panel. Xətlər yuxarı və ya aşağı gedir. Sən bu panelləri tez və doğru oxumalısan ki, servisinə nə baş verdiyini başa düşəsən. Qrafiki səhv oxumaq incident-də dəqiqələri itirir və komandanı yanlış istiqamətə yönəldir.

## Sürətli triage (ilk 5 dəqiqə)

### Üç çərçivə

**RED** — request-driven servislər üçün (API, web app):
- **Rate** — saniyədə request
- **Errors** — saniyədə səhv olan request (və ya error rate %)
- **Duration** — latency (p50/p95/p99)

**USE** — resurslar üçün (CPU, disk, network):
- **Utilization** — resursun məşğul olduğu % vaxt
- **Saturation** — resursu gözləyən queue/backlog
- **Errors** — resursda error hadisələri

**Four Golden Signals** (Google SRE kitabı) — superset:
- Latency
- Traffic
- Errors
- Saturation

Web servis üçün RED ilə başla. Host/konteynerlər üçün USE əlavə et. Saturation utilization-un qaçırdığı şeyləri tutur (məs., 80% CPU amma load average 50 = saturated).

## Diaqnoz

### Faizlər: p50, p95, p99

**p50 (median)** — istifadəçilərin yarısı daha tez, yarısı daha yavaş. Tipik təcrübəni göstərir.

**p95** — istifadəçilərin 95%-i bundan sürətlidir. "Əksər istifadəçilər yaxşı təcrübə yaşayır" həddidir.

**p99** — 99% bundan sürətlidir. Quyruq. Ən əsəbi istifadəçilərin yaşadığı yerdir.

**p99.9** — ən quyruğu önəmsəyən sistemlər üçün (payments, auth).

Qaydalar:
- p50 yuxarı qalxır = sistemli yavaşlama, hamıya təsir edir
- p50 qaydasında, p99 spike = quyruq latency problemi (bir yavaş asılılıq, bir neçə pis istifadəçi, GC pause)
- p99 sabit amma p50 sürünür = səssiz regresiya, istifadəçilər fərq edir
- p99 > 2x p50 = uzun quyruq, araşdır

### Orta göstəricilər yalan danışır

"Average latency"-yə heç vaxt güvənmə:
- Bir 10s request + doqquz 100ms request = average 1s, median 100ms
- Orta problemi gizlədir, p95/p99 onu göstərir

Dashboard yalnız `avg()` göstərirsə, bu qırmızı bayraqdır. Query-ni yenidən yaz:

Prometheus:
```promql
# Bad
avg(http_request_duration_seconds)

# Good
histogram_quantile(0.95, sum by (le) (rate(http_request_duration_seconds_bucket[5m])))
```

### Rate vs count

- `rate()` — pəncərə üzrə saniyədə rate
- `increase()` — pəncərə üzrə ümumi count
- `count` — ani dəyər

```promql
# Requests per second over 5 min
rate(http_requests_total[5m])

# Total errors in last hour
increase(http_errors_total[1h])
```

### Error rate hesablaması

```promql
sum(rate(http_requests_total{status=~"5.."}[5m])) 
  / 
sum(rate(http_requests_total[5m]))
```

Oxu belə: saniyədə səhv olan request-lər bölünür saniyədə ümumi request-lərə = error rate.

### Aqreqasiya tələləri

- Instance-lar arasında faizləri ortalamaq mənasızdır. Histogram bucket-lərindən yenidən aqreqasiya etməlisən.
- Fərqli label dəstləri arasında rate-ləri cəmləmək, label-lar üst-üstə düşərsə ikiqat saya bilər.
- `max_over_time` `max()` ilə eyni deyil.
- Düşən metrika "yaxşılaşdı" və ya "metrika emit olunmağı dayandırdı" mənasına gələ bilər (məs., pod crash olub).

## Fix (qanaxmanı dayandır)

Metriklər oxumaq heç nəyi düzəltmir — sənə harada baxmağı deyir:

- Traffic spike + latency spike + error spike yoxdur = capacity problemi → scale
- Deploy ilə korrelyasiyalı error spike = pis deploy → rollback
- Latency spike + downstream servisdə latency spike = downstream problem → onları page et
- Saturation (queue depth, thread pool) qalxır = backpressure yaranır → shed load və ya scale
- CPU yüksək, latency yüksək, traffic normal = hot path regresiya → profile

## Əsas səbəbin analizi

Incident sonrası metriklər hekayəni danışır:
- Dəqiq başlanğıc/bitiş vaxtı
- Təsirin miqyası (istifadəçilər, request-lər, gəlir)
- Tədrici idi, yoxsa qəfil
- Deploy və ya xarici hadisələrlə korrelyasiya

Bunları post-mortem-ə şəkil/link kimi ixrac et.

## Qarşısının alınması

- Hər servis minimum RED metriklərini göstərməlidir
- Host/konteynerlər USE metriklərini göstərməlidir
- Dashboard-lar faizləri göstərməlidir, ortalamaları yox
- Alert-lər faizlər üzrədir, ortalamalar üzrə yox
- Runbook-lar konkret dashboard-lara URL ilə istinad edir
- Rüblük dashboard review — ölü panelləri çıxar, yeniləri əlavə et

## PHP/Laravel üçün qeydlər

### Laravel-dən metriklər göstərmək

Paket: `superbalist/laravel-prometheus-exporter` və ya custom middleware.

```php
// Middleware for request metrics
public function handle($request, Closure $next)
{
    $start = microtime(true);
    $response = $next($request);
    $duration = microtime(true) - $start;
    
    $this->histogram->observe($duration, [
        'route' => $request->route()?->getName() ?? 'unknown',
        'method' => $request->method(),
        'status' => $response->getStatusCode(),
    ]);
    
    return $response;
}
```

### Horizon metriklər

Horizon `/horizon`-da öz dashboard-unu göstərir:
- Throughput (jobs/min)
- Runtime (queue başına)
- Failed jobs sayı
- Queue wait time

Prometheus-a export et:
```php
// app/Console/Commands/HorizonMetrics.php
$metrics = resolve(Laravel\Horizon\Contracts\MetricsRepository::class);
$throughput = $metrics->throughputForJob('App\Jobs\SendEmail');
// expose via /metrics endpoint
```

### PHP-FPM metriklər

`www.conf`-da status səhifəsini aktivləşdir:
```ini
pm.status_path = /fpm-status
```

`php-fpm_exporter` ilə scrape et:
```bash
./php-fpm_exporter --phpfpm.scrape-uri="http://127.0.0.1/fpm-status"
```

Əsas metriklər:
- `phpfpm_active_processes`
- `phpfpm_listen_queue`
- `phpfpm_slow_requests`

### MySQL metriklər

Prometheus üçün `mysqld_exporter` istifadə et. Əsas metriklər:
- `mysql_global_status_threads_running`
- `mysql_global_status_slow_queries`
- `mysql_global_status_innodb_row_lock_time_avg`

## Yadda saxlanacaq komandalar

```promql
# Request rate
sum(rate(http_requests_total[5m])) by (service)

# Error rate
sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m]))

# p95 latency
histogram_quantile(0.95, sum by (le) (rate(http_request_duration_seconds_bucket[5m])))

# CPU utilization
rate(container_cpu_usage_seconds_total[5m])

# Memory usage
container_memory_usage_bytes / container_spec_memory_limit_bytes

# PHP-FPM active processes
phpfpm_active_processes

# Queue depth (from Horizon export)
horizon_queue_pending_jobs{queue="default"}

# DB connections
mysql_global_status_threads_connected
```

PromQL tips:
- Counter-lər üzərində həmişə `rate()` istifadə et (heç vaxt `delta()` və ya çıxma)
- Dashboard üçün `[5m]` pəncərə, alert üçün `[1m]` istifadə et
- `sum by` qruplaşdırmaq üçün, `avg by` nadir hallarda doğru olanıdır

## Interview sualı

"Incident zamanı metrika dashboard-unu necə oxuyursan?"

Güclü cavab:
- "Servislər üçün RED, resurslar üçün USE istifadə edirəm. Ümumi ifadələrlə soruşulsa Four Golden Signals."
- "Faizlərə baxıram, ortalamalara yox. Ortalamalar quyruğu gizlədir."
- "p50 spike = sistemli, p99 spike = quyruq problemi. Fərqli root cause-lar."
- "Zamanı korrelyasiya edirəm: məhz bu dəqiqədə nə başladı? Adətən deploy və ya planlı job."
- "Metrikanın həqiqətən emit olunduğunu yoxlayıram — spike-dən sonra sıfırda flatline 'bərpa oldu' və ya 'pod crash oldu, hesabat verməyi dayandırdı' mənasına gələ bilər."
- "Metrikləri log-larla birlikdə oxuyuram. Metriklər miqyası göstərir, log-lar detalı göstərir."

Bonus: konkret nümunə gətir. "p50 80ms-də qaldığı halda p99-un 8s-ə spike etdiyini gördük. Üç DB replica-nın birinin runaway query-ə görə yavaş olduğu ortaya çıxdı. Onu öldürdük, p99 düşdü."
