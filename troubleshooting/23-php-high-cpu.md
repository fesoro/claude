# PHP High CPU (Senior)

## Problem (nə görürsən)
PHP prosesləri tam yüklənib. Load average qalxır. Latency qalxır. Request-lər növbələnir. Ya PHP-FPM worker-larının hamısı məşğuldur, ya da queue worker bir şey üzərində hot-spin edir.

Simptomlar:
- `top` php proseslərini 100% CPU-da göstərir
- 8-core maşında load average 10+
- PHP-FPM `listen queue` böyüyür — request-lər worker gözləyir
- Normal trafikdə belə p95 latency qalxır
- Scale up qısa müddətə kömək edir, sonra yenə saturate olur

## Sürətli triage (ilk 5 dəqiqə)

### Ağacın təpəsi

```bash
# See what's hot
top -H -p $(pgrep -d, php)

# Sort by CPU
ps aux --sort=-pcpu | head -20

# htop if installed
htop -p $(pgrep -d, php)
```

Bu bir prosesdir, yoxsa hamısı? Bir hot proses = ehtimal konkret job/request; hamısı hot = ehtimal PHP-FPM pool saturation.

### PHP-FPM pool saturate olunub?

`/etc/php/8.3/fpm/pool.d/www.conf`-də FPM status səhifəsini aktivləşdir:
```ini
pm.status_path = /fpm-status
```

Sonra:
```bash
curl http://localhost/fpm-status
```

Çıxış bunları daxil edir:
```
pool:                 www
process manager:      dynamic
active processes:     50
total processes:      50
idle processes:       0
listen queue:         127       ← BAD: requests waiting
max listen queue:     512
slow requests:        3
```

Əgər `active processes = total processes` və `listen queue > 0`-dırsa, pool saturate olunub.

## Diaqnoz

### Sualı daralt

1. CPU həmişə yüksəkdir, yoxsa burst-dir?
2. Deploy, traffic spike və ya konkret job ilə korrelyasiya edir?
3. Hansı endpoint qeyri-adi yavaşdır? (access log-lara bax)
4. OPcache son vaxtlar isindi?

### Hot request-i tap

Nginx access log yavaş üzrə sort edilib:
```bash
# Assumes $request_time is last field
awk '$NF > 1.0 {print}' /var/log/nginx/access.log | tail -50

# Top 10 slowest recent
awk '$NF > 0 {print $NF, $7}' /var/log/nginx/access.log \
  | sort -rn | head -20
```

### Production profiling

**XHProf**:
```bash
pecl install xhprof
```

Təsadüfi request sample-ını sar:
```php
// in public/index.php before Laravel bootstrap
if (mt_rand(0, 99) === 0) {       // 1% sample
    xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);
    register_shutdown_function(function () {
        $data = xhprof_disable();
        file_put_contents('/tmp/xhprof/'.uniqid().'.data', serialize($data));
    });
}
```

`xhprof_html` UI ilə bax.

**Blackfire** — production sample edə bilər:
```bash
blackfire probe enable
```

**spx** — təmiz CLI UI, CLI script-lər üçün əla:
```bash
SPX_ENABLED=1 php artisan queue:work --once
```

**perf + FlameGraph** — sistem səviyyəsində:
```bash
perf record -F 99 -g -p $(pgrep -f php-fpm | head -1) -- sleep 30
perf script > out.perf
./FlameGraph/stackcollapse-perf.pl out.perf | ./FlameGraph/flamegraph.pl > flame.svg
```

### Ümumi səbəblər

1. **Regex catastrophic backtracking** — düşmən input-a qarşı `/^(a+)+$/`. PHP-nin PCRE-sində backtrack limiti var amma əvvəlcə yavaşdır.
2. **bcrypt cost çox yüksək** — login-də cost 14+ worker başına 500ms/req alır.
3. **Base case olmayan rekursiv function** və ya geniş branching ilə.
4. **Döngü daxilində N+1 query** — DB gözləmə ilə CPU qatlanır.
5. **Serializasiya hot nöqtələri** — `serialize(huge_array)` və ya böyük obyektlərin JSON-u.
6. **OPcache soyuq** deploy-dan sonra, hər şeyi eyni anda JIT kompilyasiya edir.
7. **Hər request-də O(n²) iterate edən middleware**.
8. **Döngüdə `array_merge`** — O(n²) davranış.
9. **Laravel: `lazy`/`cursor` olmadan böyük nəticə dəstlərinin Eloquent hydration-u**.

## Fix (qanaxmanı dayandır)

Tətbiq sürətinə görə sıralanıb:

1. **Horizontal scale** — daha çox PHP-FPM pod / worker, vaxt qazandırır
2. **Memory icazə verirsə `pm.max_children`-i qaldır** (bax [php-fpm-emergency.md](php-fpm-emergency.md))
3. **Rollback** son deploy ilə korrelyasiyalıdırsa
4. **Hot job-u öldür** identifikasiya olunubsa — həmin queue-nu pause et, həmin feature flag-i söndür
5. **Runaway endpoint-i circuit break et** — nginx səviyyəsində 429 qaytar

Heç vaxt bütün pool-u `kill -9` etmə — zəncirvari retry-lərə səbəb olur.

## Əsas səbəbin analizi

Sabit olandan sonra:
- Flame graph-ları götür
- Hot function-u identifikasiya et
- Load test ilə təkrarlandır
- Fix-in CPU-nu azaltdığını yoxla

## Qarşısının alınması

- Göndərməzdən əvvəl yeni endpoint-ləri load test et
- 1-5% sample-də işləyən production APM profiler (Datadog, New Relic, Tideways)
- Alert: FPM `active_processes > 80%` max-dan, slow request count > 0
- Hər outbound call-da timeout məcbur et
- Regex timeout: `ini_set('pcre.backtrack_limit', 1000000)`
- Xarici call-lar ətrafında circuit breaker-lər

## PHP/Laravel üçün qeydlər

### N+1 ovu

```php
// Bad — fires N queries
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count();
}

// Good — eager load
$users = User::withCount('posts')->get();
```

Dev-də Laravel Debugbar və ya Telescope ilə aşkarla. Prod-da istifadə et:
```php
Model::preventLazyLoading(! app()->isProduction());
```

Non-prod-da aktiv edilib, test-lər zamanı lazy load-larda exception alırsan.

### Middleware auditi

```php
// Slow middleware on every request = global CPU cost
// Check app/Http/Kernel.php $middleware array
```

Hər middleware-i profile et. Ümumi yavaşlar:
- Hər request-də DB-yə yazan session driver
- Səhv konfiqurasiya edilibsə CSRF token generasiyası
- Hər request-də Sanctum stateful token axtarışı
- Redis əvəzinə DB istifadə edən custom rate-limit middleware

### Bcrypt cost

```php
// config/hashing.php
'bcrypt' => ['rounds' => env('BCRYPT_ROUNDS', 12)],
```

Cost 12 ~250ms-dir. Cost 13 = ~500ms. Cost 14 = ~1s. Niyəsini bilmədən 12-dən artıq getmə.

### OPcache soyuq

Deploy-dan sonra, ilk request-lər kompilyasiya edir. Bunu isindir:
```bash
# preload a few routes via curl right after deploy
for url in /api/health /api/users /api/orders; do
  curl -sf "https://myapp.com$url" >/dev/null &
done
```

Və ya ehtiyatla opcache preloading (PHP 7.4+) istifadə et.

## Yadda saxlanacaq komandalar

```bash
# Top by CPU
top -c -o %CPU

# Just php processes
top -p $(pgrep -d, php)

# Process tree
ps auxf | grep php

# FPM status
curl http://localhost/fpm-status?full

# Active FPM connections
ss -tn state established sport = :9000 | wc -l

# perf flame graph
perf record -F 99 -g -p $PID -- sleep 30
perf script | stackcollapse-perf.pl | flamegraph.pl > /tmp/flame.svg

# Check a specific job
time php artisan queue:work --once --queue=default

# Laravel request profiling (dev)
php artisan route:cache
php artisan config:cache
php artisan view:cache
```

## Interview sualı

"PHP-də yüksək CPU-nu necə debug edirsən?"

Güclü cavab:
- "Əvvəlcə `top`-a və FPM statusuna baxıram. Pool saturation-dır, yoxsa bir hot proses?"
- "Pool saturate olunubsa, anormal yavaş endpoint-lər üçün slow request log-una və access log-lara baxıram."
- "Production profiling üçün Blackfire və ya Tideways istifadə edirəm — aşağı overhead, canlıda işlətmək təhlükəsizdir."
- "Dərin araşdırma üçün flame graph almaq üçün `perf` istifadə edirəm. Flame graph dərhal hot function-u göstərir."
- "Rast gəldiyim ümumi səbəblər: N+1 query-lər, catastrophic regex, bcrypt misconfigured, hər request-də DB axtarış edən middleware."
- "Qısamüddətli mitigation: worker-ları scale et, rollback et, hot path-ı feature flag et. Uzunmüddətli: kodu düzəlt, load test əlavə et, profiling SLO əlavə et."

Bonus: "Bir dəfə payments endpoint-ində 100% CPU vardı. Flame graph göstərdi ki, 70% vaxt kupon kodunu validate edən regex-də `preg_match`-dir. İstifadəçi 100KB kupon string-i göndərmişdi. Upstream-də uzunluq limiti əlavə edərək düzəltdik."
