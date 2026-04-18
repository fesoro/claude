# PHP-FPM Emergency

## Problem (nə görürsən)
PHP-FPM pool tükənib. Nginx istifadəçilərə 502/504 qaytarır. Hər worker məşğuldur və yeni request-lər növbələnir və ya rədd olunur. FPM log-ları "reached pm.max_children" xəbərdarlığını göstərir. Latency sadə endpoint-lər üçün belə göyə qalxır.

Simptomlar:
- Nginx `upstream prematurely closed connection` və ya `connect() to unix:/run/php-fpm.sock failed`
- 502/504 burst-lər
- `php-fpm status` `active_processes = max_children` göstərir
- `listen queue` böyüyür
- CPU əslində maksimumda deyil (worker-lar I/O-da blocked)

## Sürətli triage (ilk 5 dəqiqə)

### Pool saturation-u təsdiqlə

FPM statusunu aktivləşdir (`www.conf`-da):
```ini
pm.status_path = /fpm-status
ping.path = /fpm-ping
```

Sonra:
```bash
curl "http://localhost/fpm-status?full"
```

Kritik sahələr:
- `active processes: N` vs `total processes: M` — bərabərdirsə, saturated
- `listen queue: N` — worker gözləyən request-lər
- `max listen queue: N` — başlanğıcdan bəri ən pis görülən
- `slow requests: N` — `request_slowlog_timeout`-u aşan request-lərin sayı

### Sürətli qalib: FPM-i restart et

Yalnız son variant olaraq (in-flight request-ləri itirir):
```bash
systemctl reload php8.3-fpm      # graceful, SIGUSR2
systemctl restart php8.3-fpm     # hard
```

Daha yaxşı: Kubernetes-də pod-ların rolling restart-ı və ya nginx-i fərqli host-lara round-robin.

## Diaqnoz

### Process Manager modları

**Dynamic (default)**
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
```

Child-lar yük əsasında scale up/down olur. Dəyişən trafik üçün yaxşıdır.

**Static**
```ini
pm = static
pm.max_children = 50
```

Sabit pool. Proqnozlaşdırılan memory, ardıcıl perf. Sabit yüksək-trafikli servislər üçün yaxşıdır. Idle killer-lər yoxdur.

**Ondemand**
```ini
pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s
```

Child-lar request zamanı spawn olunur, idle olanda öldürülür. Aşağı idle memory, yüksək ilk-request latency. Aşağı-trafik / multi-tenant üçün yaxşıdır.

### `pm.max_children` ölçüsü

Formula:
```
pm.max_children = (total_ram - os_ram - other_services_ram) / average_php_process_memory
```

Nümunə:
- 8 GB RAM ümumi
- 1 GB OS üçün
- 500 MB nginx, monitoring üçün
- PHP worker başına ~120 MB
→ `(8192 - 1024 - 512) / 120 ≈ 55`

Faktiki worker-başına memory-ni ölç:
```bash
ps -o rss,command -p $(pgrep -f "php-fpm: pool www") | sort -n
```

"pool www" child-larının orta RSS-i = worker-başına memory.

`max_children`-i sadəcə yuxarı çəkmə — RAM-ı aşsan, kernel təsadüfi şeyləri OOM-kill edər.

### Yavaş request-lər

Worker-ı > 1s saxlayan hər request effektiv pool capacity-ni azaldır. Slow request log-u aktivləşdir:

```ini
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 1s
```

Sonra:
```bash
tail -f /var/log/php-fpm/slow.log
```

Hər giriş vaxtın harada sərf olunduğunu göstərən PHP stack trace-dir. Hansı sətir/function-ların blok etdiyini göstərir.

### `request_terminate_timeout`

```ini
request_terminate_timeout = 30s
```

> 30s ilişib qalan hər worker-i öldür. Bir runaway request-in bir worker-i əbədi yeməsinin qarşısını alır.

### nginx → FPM bağlantı səhvləri

Ümumi mesajlar:
- `connect() failed (11: Resource temporarily unavailable)` — FPM listen queue dolu
- `upstream prematurely closed connection` — FPM worker request ortasında öldü
- `upstream timed out` — worker sağdır amma yavaşdır

Nginx config tərəfində:
```nginx
fastcgi_connect_timeout 5s;
fastcgi_send_timeout 60s;
fastcgi_read_timeout 60s;
fastcgi_pool_size 128;
```

FPM tərəfi `listen.backlog`:
```ini
listen.backlog = 511
```

## Fix (qanaxmanı dayandır)

### Dərhal (< 5 dəq)

1. **Horizontal scale** — load balancer-in arxasında pod / VM əlavə et
2. **RAM icazə verirsə `pm.max_children`-i artır** (və FPM-i reload et):
   ```bash
   sed -i 's/pm.max_children = 50/pm.max_children = 80/' /etc/php-fpm.d/www.conf
   systemctl reload php-fpm
   ```
3. **Yavaş worker-ları öldür**:
   ```bash
   for p in $(ps -o pid= -p $(pgrep php-fpm) | head); do
     time=$(ps -o etimes= -p $p); 
     if [ "$time" -gt 60 ]; then kill -9 $p; fi
   done
   ```
4. **Shed load** nginx-də — aşağı prioritetli yollar üçün 503 qaytar

### Qısamüddətli (saatlar)

- Yavaş endpoint / job tap (slow log-a bax) və düzəlt
- Kod daxilində yavaş call timeout-larını aşağı sal (xarici API-lar, DB query-lər)
- Yavaş xarici asılılıqlar ətrafında circuit breaker əlavə et

### Uzunmüddətli

- Uzun müddətli işi (email, PDF, ağır DB) queue job-larına köçür
- FPM pool-larını böl: biri API üçün (sürətli), biri admin üçün (yavaş)
- Autoscaling ilə horizontal scale et

## Əsas səbəbin analizi

- Niyə worker-lar yığıldı? (yavaş DB, yavaş API, memory leak request-ləri OOM-a salır və retry olur)
- Bu trafik üçün `pm.max_children` düzgün ölçüldü?
- İstifadəçi təsirindən əvvəl alert aldıq?

## Qarşısının alınması

- Alert: `active_processes / max_children > 80%` > 2 dəq
- Alert: `slow_requests` sayğacı artır
- Alert: nginx 502/504 rate > eşik
- Load test realistik `max_children` ölçüsünü qoyur
- Dashboard-lar: FPM saturation, request duration, slowlog girişləri

## PHP/Laravel üçün qeydlər

### Laravel + FPM split pool-lar

Əgər admin dashboard-da yavaş hesabatlar varsa və public API sürətli olmalıdırsa, pool-ları ayır:

```ini
# /etc/php-fpm.d/api.conf
[api]
listen = /run/php-fpm-api.sock
pm = dynamic
pm.max_children = 50
request_terminate_timeout = 10s

# /etc/php-fpm.d/admin.conf
[admin]
listen = /run/php-fpm-admin.sock
pm = dynamic
pm.max_children = 10
request_terminate_timeout = 120s
```

Nginx müvafiq olaraq yönləndirir.

### Yavaş işi queue-lara köçür

```php
// Bad: synchronous PDF gen holds FPM worker 30s
return $pdf->generate($order);

// Good: queue it
GeneratePdfJob::dispatch($order);
return response()->json(['status' => 'processing']);
```

### Session lock contention-a diqqət et

Native fayl əsaslı session-lar (`SESSION_DRIVER=file`) session başına concurrent request-ləri serialize edir. Yavaş AJAX plus yavaş səhifə yüklənməsi = bir worker digər işləyərkən session lock-unu gözləyir.

Fix: Redis session-ları istifadə et:
```php
SESSION_DRIVER=redis
```

Və ya read-only endpoint-lərdə lock-u erkən azad et:
```php
session_write_close();
```

### Alternativ olaraq Laravel Octane

Octane Swoole/RoadRunner ilə FPM modelini tamamilə keçir — uzun ömürlü worker-lar, request başına memory reset. Throughput-u 3-5x artıra bilər. Amma: daha ehtiyatlı kod tələb olunur (leak-lər, statics).

## Yadda saxlanacaq komandalar

```bash
# FPM status
curl http://localhost/fpm-status?full

# FPM reload (graceful)
systemctl reload php8.3-fpm
kill -USR2 $(pgrep -o "php-fpm: master")

# FPM ping
curl http://localhost/fpm-ping

# Workers by memory
ps -o pid,rss,etimes,command -p $(pgrep -f "php-fpm: pool") | sort -k2 -n

# Long-running workers (> 60s)
ps -o pid,etimes,command -p $(pgrep -f "php-fpm: pool") | awk '$2 > 60'

# Tail slow log
tail -f /var/log/php-fpm/slow.log

# Tail FPM error log
tail -f /var/log/php-fpm/error.log

# Check pool config
php-fpm -t                    # test config
php-fpm -tt                   # verbose test
```

Nümunə nginx timeout konfiqurasiyası:
```nginx
location ~ \.php$ {
    fastcgi_pass unix:/run/php-fpm.sock;
    fastcgi_index index.php;
    fastcgi_connect_timeout 3s;
    fastcgi_send_timeout 30s;
    fastcgi_read_timeout 30s;
    fastcgi_buffers 16 32k;
    fastcgi_buffer_size 64k;
    include fastcgi_params;
}
```

## Interview sualı

"Production PHP-FPM pool saturated. Məni keçir."

Güclü cavab:
- "Əvvəlcə `pm.status_path`-i yoxlayıram. Active = max saturation-dır. Listen queue dərinliyi nə qədər gözlədiyini deyir."
- "Dərhal mitigation: horizontal scale, RAM headroom varsa `pm.max_children`-i qaldır, nginx-də shed load."
- "Paralel: yavaş request-ləri tap. `request_slowlog_timeout=1s` ilə `slowlog` hansı endpoint-lərin worker saxladığını üzə çıxarır."
- "Root cause adətən: timeout olmayan yavaş xarici call, DB query regresiya, və ya queue-a məxsus olan synchronous ağır iş edən yeni kod."
- "Fix-lər: bütün outbound call-lara timeout əlavə et, ağır işi Horizon job-larına köçür, FPM pool-larını latency class-larına görə böl."
- "Uzunmüddət: FPM saturation-da alert, `max_children`-i real memory ölçülərindən (təxminlə deyil) ölç, prinsipcə daha yüksək throughput üçün Octane nəzərdən keçir."

Bonus: "Bir dəfə ağır AJAX poll-da blok edən file session-lardan FPM saturation-umuz oldu. Fix Redis session-lara keçmək idi. Saturation yox oldu; max_children 200-dən 60-a düşdü."
