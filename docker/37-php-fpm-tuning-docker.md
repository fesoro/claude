# PHP-FPM Tuning in Docker

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

PHP-FPM (FastCGI Process Manager) konteynerdə default parametrlərlə işləyir — və bu default-lar **kiçik maşınlar üçün hazırlanıb, sənin production-un üçün deyil**. Yanlış tuning:

- Çox az process → request-lər növbəyə düşür, latency artır
- Çox process → RAM bitir, OOMKilled
- OpCache off → hər request-də PHP faylları yenidən parse olunur, CPU 5-10x artır

Bu fayl PHP-FPM pool-u, OpCache və JIT-i **konteyner mühitində** necə düzgün konfiqurasiya etməli olduğunu göstərir.

## PHP-FPM Pool — `www.conf`

Default `/usr/local/etc/php-fpm.d/www.conf` faylı belədir:

```ini
[www]
user = www-data
group = www-data
listen = 9000
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

**Problem:** `pm.max_children = 5` — yalnız 5 paralel request! Production-da bu çox azdır.

### Düzgün Konfiqurasiya (production nümunəsi)

```ini
# /usr/local/etc/php-fpm.d/www.conf
[www]
user = www-data
group = www-data

; Nginx ilə eyni konteynerdə: Unix socket (daha sürətli)
; listen = /var/run/php-fpm.sock
; listen.owner = www-data
; listen.group = www-data
; listen.mode = 0660

; Ayrı konteynerlərdə: TCP
listen = 9000

; Pool manageri
pm = dynamic

; Pool ölçüləndirmə (aşağıda hesablama)
pm.max_children = 40
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.max_requests = 500          ; hər process 500 request-dən sonra restart (memory leak)

; Status endpoint — monitoring üçün
pm.status_path = /fpm-status
ping.path = /fpm-ping
ping.response = pong

; Slow request logging
slowlog = /proc/self/fd/2
request_slowlog_timeout = 5s

; Error logging (stdout/stderr-ə)
access.log = /proc/self/fd/2
catch_workers_output = yes
decorate_workers_output = no

; Environment variables (Docker-dan gələnlər üçün clear_env = no vacibdir!)
clear_env = no

; Request limit-ləri
request_terminate_timeout = 60s
php_admin_value[memory_limit] = 256M

; Emergency restart (bütün child process-lər crash edəndə)
emergency_restart_threshold = 10
emergency_restart_interval = 1m
process_control_timeout = 10s
```

### `pm.max_children` Necə Hesablanır?

**Formula:**
```
pm.max_children = (Konteynerə verilən RAM) / (orta bir request-in RAM istifadəsi)
```

**Nümunə:**
- Konteyner limit-i: 1 GB RAM
- PHP-FPM özü: ~50 MB
- Hər request ortalama: ~40-60 MB (Laravel, orta app)
- Təhlükəsizlik buferi: 15-20%

```
(1024 - 50) * 0.85 / 50 = ~16-17 process
```

Real request RAM-ını ölçmək:
```bash
docker compose exec app sh -c '
  for i in 1 2 3 4 5; do
    ps --no-headers -o rss -C php-fpm | awk "{sum+=\$1} END {print sum/NR/1024 \" MB\"}"
    sleep 2
  done
'
```

### `pm = dynamic` vs `static` vs `ondemand`

| Mode | Nə edir | Nə vaxt istifadə |
|------|---------|-------------------|
| `static` | `max_children` qədər process həmişə açıq | CPU bound, sabit yük, ən sürətli |
| `dynamic` | `min_spare` - `max_spare` aralığında avtomatik | Dəyişkən trafik (default tövsiyə) |
| `ondemand` | İdle-də 0 process, request gələndə açır | Çox az trafik, çox tətbiq bir serverdə |

**Kubernetes-də tövsiyə:** `static` — HPA onsuz da pod-ları ölçüləndirir, pool daxilində dinamiklik lazım deyil.

## OpCache — Mütləq Aktiv Olmalıdır

OpCache olmadan PHP production-da **10x yavaşdır**. Konteynerdə default aktiv olsa da, parametrləri production üçün tune edilməlidir.

### Production `opcache.ini`

```ini
; /usr/local/etc/php/conf.d/opcache.ini
[opcache]
opcache.enable=1
opcache.enable_cli=0                      ; CLI üçün lazımsız (artisan əmrləri)

; Yaddaş
opcache.memory_consumption=256            ; MB — böyük Laravel üçün 256, kiçik üçün 128
opcache.interned_strings_buffer=16        ; MB — təkrarlanan string-lər üçün

; Maximum cached files
opcache.max_accelerated_files=20000       ; Laravel + vendor + route cache ~10k fayl

; Cache invalidation
opcache.validate_timestamps=0             ; PRODUCTION: 0 (dəyişiklik üçün restart)
opcache.revalidate_freq=0                 ; Dev: 2, prod: 0

; Optimization
opcache.save_comments=1                   ; Laravel annotation/attribute üçün lazım!
opcache.fast_shutdown=1

; Preloading (PHP 7.4+, Laravel Octane/Swoole ilə)
; opcache.preload=/var/www/html/preload.php
; opcache.preload_user=www-data

; JIT (PHP 8+) — CPU-bound work-load üçün
opcache.jit_buffer_size=100M
opcache.jit=tracing                       ; tracing > function > disable
```

### `validate_timestamps=0` — Production Kritik

Bu parametr 0 olmalıdır production-da. Niyə?

- **`=1`:** Hər request-də hər fayl üçün `stat()` çağırışı (FS I/O) — dev üçün yaxşı, çünki dəyişikliklər anında görünür
- **`=0`:** Fayl cache-i əbədidir, dəyişiklik görünmür — production üçün **10-30% daha sürətli**

**Problem:** `validate_timestamps=0` ilə deploy etsən, yeni kod görünmür!

**Həll:** Hər deploy-da OpCache reset et:
```bash
# Yeni konteyner = yeni OpCache. Amma mövcud konteynerdə:
docker compose exec app php -r 'opcache_reset();'
# Və ya FPM reload:
docker compose kill -s USR2 app
```

Kubernetes-də bu problem yoxdur — `kubectl rollout` yeni pod-lar yaradır, OpCache təbii olaraq təmiz olur.

### `opcache.save_comments=1` — Laravel Üçün Kritik

Laravel annotation və PHP 8 Attribute-ları (Route attribute-ları, validation, Livewire component-ləri) şərhlərdə saxlayır. `save_comments=0` edərsə:
- Route discovery işləmir
- Attribute-based validation işləmir
- Doctrine annotation-ları işləmir

**Həmişə 1 olmalıdır.**

### JIT — Faydalı Yoxsa Yox?

PHP 8-in JIT-i **CPU-bound** iş üçündür (math, image processing, parsing). Web request-lərdə qazanc:
- Laravel web request: **~3-5% daha sürətli** (çox az fərq)
- Math-heavy script: **~30-50% daha sürətli**

**Tövsiyə:** JIT aktiv et (`opcache.jit=tracing`, `jit_buffer_size=100M`), amma web performans üçün OpCache-in özü daha vacibdir.

## PHP Runtime Config — `php.ini`

```ini
; /usr/local/etc/php/conf.d/zz-custom.ini
[PHP]
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 20M
upload_max_filesize = 20M

; Error handling (konteynerdə stdout/stderr istifadə)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /proc/self/fd/2                ; stderr-ə yaz
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Timezone
date.timezone = UTC

; Sessions (Laravel Redis istifadə edirsə lazımsız)
session.save_handler = files
session.save_path = "/tmp"

; Realpath cache (fayl sistemi I/O-nu azaldır)
realpath_cache_size = 4096K
realpath_cache_ttl = 600

; Garbage collection
zend.enable_gc = 1
```

## FPM Status və Monitoring

FPM status endpoint-i vacib metriklər verir:

```nginx
# Nginx config-də
location ~ ^/(fpm-status|fpm-ping)$ {
    access_log off;
    allow 127.0.0.1;
    allow 10.0.0.0/8;      # Prometheus-a icazə
    deny all;
    fastcgi_pass app:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
}
```

Status output:
```
pool:                 www
process manager:      dynamic
start time:           10/Apr/2026:14:23:01 +0000
idle processes:       3
active processes:     12
total processes:      15
max active processes: 20
max children reached: 0              <-- əgər >0 isə pm.max_children artırmaq lazımdır
listen queue:         0              <-- əgər >0 isə request-lər gözləyir
listen queue len:     128
slow requests:        2
```

**Vacib siqnallar:**
- `max children reached > 0` → `pm.max_children` artır
- `listen queue > 0` → pool tutumu bitib, request-lər gözləyir
- `slow requests > 0` → `slowlog`-a bax, N+1 query və ya xarici API gecikməsi

### Prometheus Exporter

[`hipages/php-fpm_exporter`](https://github.com/hipages/php-fpm_exporter) — sidecar kimi əlavə et, `/metrics` endpoint verir:

```yaml
# docker-compose.yml
services:
  app:
    # ...
  
  fpm-exporter:
    image: hipages/php-fpm_exporter:latest
    environment:
      PHP_FPM_SCRAPE_URI: tcp://app:9000/fpm-status
    ports:
      - "9253:9253"
```

## Memory Limits — Kubernetes/Compose

Konteynerə RAM limiti verməyəndə FPM `pm.max_children` × `memory_limit` qədər RAM tuta bilər. Hostda OOM-killer başqa process-ləri öldürə bilər.

```yaml
# docker-compose.yml
services:
  app:
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: '1.0'
        reservations:
          memory: 512M
          cpus: '0.5'
```

```yaml
# Kubernetes
resources:
  requests:
    memory: "512Mi"
    cpu: "500m"
  limits:
    memory: "1Gi"
    cpu: "1000m"
```

**Qayda:** `pm.max_children × php_memory_limit × 1.2 ≤ container_limit`

## Tipik Səhvlər (Gotchas)

### 1. `clear_env = yes` (default)

FPM default-da `env` təmizləyir. `docker run -e APP_KEY=...` və ya Compose `environment:`-dəki variables PHP-yə çatmır!

**Həll:** `clear_env = no` və ya hər variable-ı `env[APP_KEY] = $APP_KEY` ilə whitelist.

### 2. OpCache açıq deyil (CLI-da)

`artisan migrate` bəzən `opcache_reset()` yoxdur deyə error verir.

**Səbəb:** CLI üçün `opcache.enable_cli=0`. `opcache_reset()` yalnız aktiv olanda işləyir.

**Həll:** CLI-dan `opcache_reset()` çağırmağa cəhd etmə — FPM-də reset fərqlidir.

### 3. `realpath_cache` kiçikdir

Laravel-də minlərlə fayl var. Default `realpath_cache_size = 16K` çox azdır, 4096K-ya artır.

### 4. Log-lar fayla yazılır, stdout-a yox

Konteyner best practice: bütün log-lar `stdout`/`stderr`-ə. PHP-də:
```ini
error_log = /proc/self/fd/2
access.log = /proc/self/fd/2
```

### 5. `pm = ondemand` production-da

`ondemand` idle-də 0 process saxlayır, amma hər request yeni process fork edir — ilk request çox yavaşdır. Production-da `dynamic` və ya `static` istifadə et.

## Interview sualları

- **Q:** `pm.max_children`-i necə hesablayırsız?
  - `(konteyner RAM - FPM overhead) / bir request RAM`. Nümunə: 1GB / 50MB ≈ 16-17. Test edib FPM status-dan `max children reached` metrikin-ə bax.

- **Q:** `validate_timestamps=0` niyə production-da lazımdır?
  - Hər request-də fayl `stat()` çağırışı aradan qalxır — 10-30% sürət artımı. Deploy-da OpCache reset et (yeni konteyner və ya `USR2` signal).

- **Q:** `pm = static` vs `dynamic`?
  - `static`: sabit process sayı, ən sürətli, K8s HPA ilə yaxşı. `dynamic`: load-a görə ölçüləndirir, tək-host-da yaxşı.

- **Q:** OpCache-də `save_comments=0` niyə problemdir?
  - Laravel və modern PHP annotation/Attribute istifadə edir — şərhlərdə saxlanır. 0 edərsən, route/validation attribute-ları itir.

- **Q:** Konteynerdə FPM status-u necə monitor edirsiz?
  - `pm.status_path = /fpm-status`, Nginx-dən expose et, `hipages/php-fpm_exporter` ilə Prometheus-a yaz. `max children reached`, `listen queue`, `slow requests` baxırıq.
