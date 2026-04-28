# Performance Tuning (Senior)

## Nədir? (What is it?)

Performance tuning – sistemin (Linux kernel, web server, application, database) performansını sürətləndirmək, latency-ni azaltmaq, throughput-u artırmaq və resurs istifadəsini optimallaşdırmaq üçün aparılan tənzimləmələrdir. Laravel tətbiqlərinin production-da yüksək performansı üçün kernel parametrləri, TCP connection limitləri, PHP-FPM pool, OPcache, MySQL və Redis tənzimləmələri kritikdir. Düzgün tuning 5-10x performans artımı verə bilər.

## Əsas Konseptlər (Key Concepts)

### Linux Kernel Tuning (sysctl)

```bash
# sysctl = kernel parametrlərini idarə etmək
# Konfiqurasiya: /etc/sysctl.conf və /etc/sysctl.d/*.conf

# Mövcud parametrləri görmək
sysctl -a | grep net.core
sysctl net.ipv4.tcp_max_syn_backlog

# Müvəqqəti dəyişdirmək
sysctl -w net.core.somaxconn=65535

# Persistent etmək
cat > /etc/sysctl.d/99-laravel.conf <<EOF
# Network performance
net.core.somaxconn = 65535                 # Listen backlog (default 128)
net.core.netdev_max_backlog = 65535        # Network packet queue
net.core.rmem_max = 16777216               # Max receive buffer (16MB)
net.core.wmem_max = 16777216               # Max send buffer
net.ipv4.tcp_rmem = 4096 87380 16777216    # TCP receive buffer
net.ipv4.tcp_wmem = 4096 65536 16777216    # TCP send buffer

# TCP connection handling
net.ipv4.tcp_max_syn_backlog = 65535       # SYN queue
net.ipv4.tcp_syncookies = 1                # SYN flood protection
net.ipv4.tcp_synack_retries = 2            # Reduce from 5
net.ipv4.tcp_fin_timeout = 15              # Reduce from 60
net.ipv4.tcp_tw_reuse = 1                  # Reuse TIME_WAIT sockets
net.ipv4.tcp_keepalive_time = 300          # Reduce from 7200
net.ipv4.tcp_max_tw_buckets = 2000000      # Max TIME_WAIT sockets

# Port range for outbound
net.ipv4.ip_local_port_range = 1024 65535  # Geniş range

# File descriptors
fs.file-max = 2097152                      # System-wide FD limit
fs.nr_open = 1048576                       # Per-process FD limit

# Virtual memory
vm.swappiness = 10                         # Swap istifadəsini azalt (default 60)
vm.dirty_ratio = 15                        # Dirty page cache (default 20)
vm.dirty_background_ratio = 5              # Background writeback
vm.overcommit_memory = 1                   # Redis üçün

# Kernel scheduling
kernel.pid_max = 4194304                   # Max process count
EOF

# Apply
sysctl -p /etc/sysctl.d/99-laravel.conf

# Per-user limits
cat > /etc/security/limits.d/99-laravel.conf <<EOF
*               soft    nofile          65535
*               hard    nofile          65535
*               soft    nproc           65535
*               hard    nproc           65535
www-data        soft    nofile          65535
www-data        hard    nofile          65535
EOF

# Systemd service üçün
# /etc/systemd/system/nginx.service.d/override.conf
[Service]
LimitNOFILE=65535
LimitNPROC=65535
```

### Connection Limits

```bash
# Open file descriptors görmək
ulimit -n                          # Soft limit
ulimit -Hn                         # Hard limit
cat /proc/sys/fs/file-nr           # Allocated, free, max
lsof -p <PID> | wc -l              # Process üçün açıq fayllar

# TCP connections
ss -s                              # Connection stats
ss -tan | awk '{print $1}' | sort | uniq -c   # State breakdown
netstat -an | grep ESTABLISHED | wc -l

# TIME_WAIT problem
# Server çox connection accept edəndə TIME_WAIT yığılır
# Fix: tcp_tw_reuse=1, tcp_fin_timeout azalt

# SYN flood yoxlamaq
netstat -an | grep SYN_RECV | wc -l
# 1000+ varsa potensial attack
```

### PHP-FPM Tuning

```bash
# PHP-FPM konfiqurasiyası
# /etc/php/8.2/fpm/pool.d/www.conf

[www]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process manager types:
; static    - Fixed pm.max_children (tövsiyə olunur, predictable)
; dynamic   - Scales between min/max (default)
; ondemand  - Create on request (low-traffic)

pm = static
pm.max_children = 50
; Formula: RAM * 0.8 / avg PHP process RAM
; Məs. 8GB RAM, 100MB/process → 8*1024*0.8/100 = 65 workers

; Dynamic mode üçün:
; pm = dynamic
; pm.max_children = 50
; pm.start_servers = 10
; pm.min_spare_servers = 5
; pm.max_spare_servers = 15

pm.max_requests = 500             ; Restart worker after N requests (memory leak qoruması)
pm.status_path = /fpm-status      ; Monitoring endpoint
ping.path = /fpm-ping

; Request timeout
request_terminate_timeout = 60s

; Slow log
request_slowlog_timeout = 5s
slowlog = /var/log/php8.2-fpm-slow.log

; Emergency restart (kernel panic protection)
emergency_restart_threshold = 10
emergency_restart_interval = 1m
process_control_timeout = 10s

; Workers-ın resource limits
rlimit_files = 65535
rlimit_core = 0

; PHP config override (production)
php_admin_value[memory_limit] = 256M
php_admin_value[post_max_size] = 20M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[max_execution_time] = 60
php_admin_value[max_input_time] = 60

# Worker sayını hesablamaq
# RSS = ps aux | grep 'php-fpm: pool' | awk '{sum+=$6} END {print sum/NR/1024 " MB"}'

# FPM status yoxlamaq
curl http://localhost/fpm-status?full
```

### OPcache Tuning

```ini
; /etc/php/8.2/fpm/conf.d/10-opcache.ini

[opcache]
opcache.enable=1
opcache.enable_cli=0

; Memory (böyük Laravel app üçün)
opcache.memory_consumption=256        ; MB, (default 128)
opcache.interned_strings_buffer=16    ; MB (default 8)

; File cache
opcache.max_accelerated_files=20000   ; Laravel 10k+ fayl, buffer saxla
opcache.max_wasted_percentage=10

; Performance (production-da)
opcache.validate_timestamps=0         ; File dəyişikliklərini yoxlamaz (deploy-da restart lazım)
opcache.revalidate_freq=0

; Optimization
opcache.save_comments=1               ; Laravel annotation üçün lazımdır
opcache.enable_file_override=1
opcache.optimization_level=0x7FFEBFFF

; Preloading (PHP 7.4+)
opcache.preload=/var/www/laravel/preload.php
opcache.preload_user=www-data

; JIT (PHP 8+)
opcache.jit_buffer_size=100M
opcache.jit=tracing

; Monitoring
opcache.status_file=/var/log/opcache.log
```

```php
// preload.php - frequently used classes
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Laravel core files
$files = [
    'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
    'vendor/laravel/framework/src/Illuminate/Http/Request.php',
    // ...
];

foreach ($files as $file) {
    opcache_compile_file(__DIR__.'/'.$file);
}
```

```bash
# OPcache statistika
php -r "print_r(opcache_get_status());"
php -r "print_r(opcache_get_configuration());"

# Cache reset (deploy sonrası)
sudo systemctl reload php8.2-fpm
# və ya
php -r "opcache_reset();"
```

### MySQL Tuning

```ini
; /etc/mysql/mysql.conf.d/mysqld.cnf

[mysqld]
# Connection
max_connections = 500                 # Laravel app connection-ları
max_connect_errors = 1000
connect_timeout = 10
wait_timeout = 28800
interactive_timeout = 28800

# Buffer Pool (ən vacib parametr!)
innodb_buffer_pool_size = 4G          # RAM-ın 70-80% (dedicated DB server)
innodb_buffer_pool_instances = 4      # 1GB-a bir instance
innodb_log_file_size = 512M           # Redo log
innodb_log_buffer_size = 32M
innodb_flush_log_at_trx_commit = 2    # Performance (1=safest, 2=fast, 0=fastest)
innodb_flush_method = O_DIRECT

# Thread cache
thread_cache_size = 100
table_open_cache = 4000
table_definition_cache = 2000

# Query Cache (5.7 və əvvəli, 8.0-da yoxdur)
# Laravel-də query cache faydalı deyil, deaktiv edin

# InnoDB tuning
innodb_io_capacity = 2000             # SSD üçün
innodb_io_capacity_max = 4000
innodb_file_per_table = 1
innodb_read_io_threads = 8
innodb_write_io_threads = 8

# Temp tables
tmp_table_size = 128M
max_heap_table_size = 128M

# Binary log (replication)
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
expire_logs_days = 7

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Performance schema
performance_schema = ON
```

```sql
-- Analiz üçün faydalı sorgular
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';
SHOW STATUS LIKE 'Innodb_buffer_pool%';

-- Slow queries
SELECT * FROM mysql.slow_log ORDER BY query_time DESC LIMIT 10;

-- Missing indexes
SELECT * FROM sys.schema_unused_indexes;
SELECT * FROM sys.statements_with_full_table_scans;

-- Buffer pool hit ratio (should be > 99%)
SHOW STATUS LIKE 'Innodb_buffer_pool_read%';
-- hit_ratio = (reads_from_cache / total_reads) * 100
```

### Redis Tuning

```bash
# /etc/redis/redis.conf

maxmemory 4gb
maxmemory-policy allkeys-lru         # Laravel cache üçün ideal

# Persistence
save 900 1
save 300 10
appendonly yes
appendfsync everysec

# Connections
maxclients 10000
timeout 0
tcp-keepalive 60

# Performance
tcp-backlog 65535
```

## Praktiki Nümunələr (Practical Examples)

### Laravel Performance Audit

```bash
# 1. Database queries (N+1 problem)
# Laravel Debugbar (dev) və ya Telescope

# 2. Response time ölçmək
time curl http://localhost/api/users

# 3. Load test (Apache Bench)
ab -n 10000 -c 100 http://localhost/api/users

# 4. wrk (daha sürətli)
wrk -t 12 -c 400 -d 30s http://localhost/api/users

# 5. k6
k6 run --vus 100 --duration 30s script.js

# 6. PHP-FPM status
curl http://localhost/fpm-status?full | grep -E "accepted|active|queue"

# 7. MySQL slow queries
mysqldumpslow -s c -t 20 /var/log/mysql/slow.log
```

### Laravel optimization checklist

```bash
# Production deploy prosesi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
composer install --no-dev --optimize-autoloader
php artisan optimize

# Queue workers (Supervisor ilə)
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/laravel/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/log/laravel-worker.log
```

## PHP/Laravel ilə İstifadə

### Database optimization

```php
// N+1 query fix - Eager loading
// Zəif:
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count();  // Hər user üçün yeni query!
}

// Yaxşı:
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count;
}

// Və ya:
$users = User::with('posts')->get();

// Chunk böyük dataset-lər üçün
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Process
    }
});

// Lazy collection
User::lazy()->each(function ($user) {
    // Memory-efficient
});

// Index əlavə etmək
Schema::table('users', function (Blueprint $table) {
    $table->index('email');
    $table->index(['status', 'created_at']);  // Composite
});

// Explain query
DB::enableQueryLog();
$users = User::where('email', 'x@y.com')->get();
dump(DB::getQueryLog());

// Raw query ilə optimization
DB::table('users')->where('status', 'active')->count();  // O(1) with index
```

### Laravel Octane (performance boost)

```bash
# Octane = persistent PHP process (Swoole/RoadRunner)
# 2-10x daha sürətli!

composer require laravel/octane
php artisan octane:install --server=swoole

# Start
php artisan octane:start --host=0.0.0.0 --port=8000 --workers=8 --task-workers=6

# Restart (deploy sonrası)
php artisan octane:reload
```

```php
// Octane-safe practice
// Static property istifadə ETMƏ (yaddaşda qalır)
// Container singleton-lar array-a yazma

// config/octane.php
'listeners' => [
    RequestReceived::class => [
        EnsureUploadedFilesAreValid::class,
        EnsureUploadedFilesCanBeMoved::class,
    ],
    RequestHandled::class => [
        FlushSession::class,
        FlushAuthenticationState::class,
        // Reset container state between requests
    ],
],
```

### Cache strategies

```php
// Redis-də cache
use Illuminate\Support\Facades\Cache;

// Simple cache
$users = Cache::remember('users.active', 3600, function () {
    return User::where('active', true)->get();
});

// Tags (Redis/Memcached)
Cache::tags(['users', 'active'])->put('users.list', $users, 3600);
Cache::tags(['users'])->flush();

// Atomic lock
Cache::lock('update-score', 10)->block(5, function () {
    // Critical section
});

// Response cache
Route::get('/expensive', function () {
    return Cache::remember('expensive-response', 600, function () {
        return expensive_computation();
    });
});
```

## Interview Sualları (5-10 Q&A)

**S1: Laravel üçün PHP-FPM pool-u necə tənzimləmək?**
C: Formula: `max_children = (total_ram * 0.8) / avg_process_memory`. Məsələn 8GB RAM, 100MB/process → 65 worker. Process manager `static` daha predictable. `pm.max_requests = 500` memory leak üçün. Request terminate timeout request_timeout-dan azacıq böyük. Status endpoint (/fpm-status) monitoring üçün aktivləşdirin.

**S2: OPcache nə üçün lazımdır və necə konfiqurasiya edilir?**
C: OPcache – PHP bytecode-u memory-də cache edir, hər request-də compile etməz. 2-5x performans artımı. `memory_consumption=256MB`, `max_accelerated_files=20000` (Laravel üçün), `validate_timestamps=0` production-da. Deploy sonrası `systemctl reload php-fpm` lazımdır. JIT (PHP 8+) əlavə performans verir.

**S3: Linux-da TIME_WAIT socket yığılması necə həll olunur?**
C: Yüksək trafikli server-lərdə TCP connection-lar bağlandıqdan sonra 2MSL (60s) TIME_WAIT state-də qalır. Həll: `net.ipv4.tcp_tw_reuse=1` (yenidən istifadə), `net.ipv4.tcp_fin_timeout=15` azaldın, `net.ipv4.ip_local_port_range = 1024 65535` genişləndirin. Keep-alive connection-ları (HTTP/1.1 keepalive) istifadə edin.

**S4: innodb_buffer_pool_size nə üçün vacibdir?**
C: MySQL InnoDB-nin in-memory cache-idir. Data və index səhifələri burada saxlanır – disk I/O azaldılır. Dedicated DB server-də RAM-ın 70-80%-i tövsiyə olunur. Hit ratio 99%+ olmalıdır (`SHOW STATUS LIKE 'Innodb_buffer_pool%'`). Çox kiçik olsa, disk I/O bottleneck olur. Çox böyük olsa, OS üçün yer qalmır.

**S5: Laravel-də N+1 query problemi nədir?**
C: Relation-lara access edəndə hər dəfə yeni query gedir. Məs. 100 user-in postlarını göstərmək – 1 (user) + 100 (posts) = 101 query. Fix: `User::with('posts')->get()` (eager loading) – 2 query. `withCount`, `load` metodları da var. Laravel Debugbar və ya Telescope ilə aşkar edilir.

**S6: Laravel Octane nədir və nə üçün sürətlidir?**
C: Octane – Laravel app-i persistent process kimi işlədir (Swoole/RoadRunner ilə). Hər request üçün framework bootstrap təkrarlanmır, yalnız bir dəfə başlanğıcda. 2-10x sürətlidir. Dezavantaj: state leak riski (memory-də qalır), Octane-safe kod yazılmalıdır. Session, auth state reset olunmalıdır.

**S7: Redis-də maxmemory-policy nədir?**
C: Redis yaddaş dolsa hansı açarı sil? Options: `noeviction` (error qaytarır), `allkeys-lru` (ən az istifadə olunan sil), `allkeys-lfu` (ən az tez-tez istifadə olunan), `volatile-lru` (yalnız TTL olan), `allkeys-random`. Laravel cache üçün `allkeys-lru` tövsiyə olunur.

**S8: somaxconn nədir və niyə artırmaq lazımdır?**
C: Listen backlog queue ölçüsü – server accept etməmiş gözləyən connection-lar. Default 128, production-da azdır. Yüksək trafikdə queue dolsa, yeni connection-lar drop olunur ("connection refused"). `net.core.somaxconn=65535` və nginx/listen `backlog=65535` təyin edin.

**S9: MySQL slow query log necə istifadə olunur?**
C: `slow_query_log=1` aktivləşdirin, `long_query_time=2` ilə 2s-dən uzun query-lər log olunur. `log_queries_not_using_indexes=1` index olmayan query-lər. `mysqldumpslow -s c -t 20` ilə top 20 slow query. `pt-query-digest` (Percona) daha ətraflı analiz. EXPLAIN ilə query planını yoxlayın.

**S10: Laravel queue worker-ları necə optimallaşdırılır?**
C: Supervisor ilə idarə edin (avtomatik restart). Worker sayı = CPU core * 2. `--sleep=3` (boş queue-da), `--tries=3` (retry), `--max-time=3600` (memory leak üçün restart), `--timeout=60`. Horizon (Redis) load monitoring və auto-scaling verir. Redis connection `persistent=true`.

## Best Practices

1. **Monitor before tune**: Əvvəl metrikləri topla (Prometheus, CloudWatch), problem nədir?
2. **Baseline performance**: Dəyişiklikdən əvvəl benchmark götür, sonra müqayisə et.
3. **One change at a time**: Bir dəfəyə bir parametr dəyişdir, nəticəni ölç.
4. **OPcache prod-da hökmən**: OPcache aktivləşdir, 256MB memory, validate_timestamps=0.
5. **Laravel optimize**: `config:cache`, `route:cache`, `view:cache`, `event:cache`, `composer install --optimize-autoloader`.
6. **Database indexes**: Query patterns-ə görə index qur, `EXPLAIN` ilə yoxla.
7. **Connection pooling**: DB və Redis persistent connections (daha az latency).
8. **CDN istifadə et**: Statik fayllar – CloudFront/Cloudflare, origin yükü azalır.
9. **Lazy loading yox**: Eager loading (`with()`) N+1-i həll edir.
10. **Chunk large datasets**: `chunk()`, `lazy()` böyük data üçün.
11. **Queue uzun işləri**: Email, export, image processing queue-ya at.
12. **Horizontal scaling**: Vertikal limitə çatanda horizontal (daha çox server).
13. **Cache strategy**: Read-heavy data-nı cache et, TTL düzgün təyin et.
14. **HTTP/2 və Gzip**: Web server-də aktiv et (nginx gzip, http2).
15. **Regular profiling**: Blackfire, Xdebug ilə profiler, bottleneck-ləri tap.
16. **File descriptors**: `ulimit -n 65535`, systemd override ilə.
17. **Swap-ı azalt**: `vm.swappiness=10`, swap-ı minimuma endir (memory yaxşıdır).

---

## Praktik Tapşırıqlar

1. PHP-FPM worker sayını hesablayın: `ps aux | grep php-fpm | awk '{print $6}' | sort -n` ilə worker-lərin ortalama RAM istifadəsini tapın; `free -m` ilə mövcud RAM-ı görün; formul tətbiq edin (`pm.max_children = (RAM * 0.8) / avg_worker_mb`); dəyişiklikdən əvvəl/sonra `wrk` ilə benchmark edin
2. OPcache-i tune edin: `php -i | grep opcache` ilə cari vəziyyəti görün; `opcache.memory_consumption=256`, `opcache.validate_timestamps=0`, `opcache.jit=tracing`, `opcache.jit_buffer_size=64M` konfiqurasiyası; `opcache_get_status()` ilə hit ratio-nu yoxlayın (> 95% olmalıdır)
3. MySQL slow query log aktivləşdirin: `slow_query_log=1`, `long_query_time=0.1` (100ms), `log_queries_not_using_indexes=1`; `mysqldumpslow -s t -t 10 /var/log/mysql/slow.log` ilə top-10 ən yavaş sorğunu çıxarın; `EXPLAIN` ilə analyze edin
4. `sysctl` parametrlərini tune edin: `net.core.somaxconn=65535`, `net.ipv4.tcp_tw_reuse=1`, `fs.file-max=1000000`, `vm.swappiness=10`; dəyişiklikdən əvvəl `ss -s` ilə connection stats görün; `sysctl -p` ilə tətbiq edin; load test ilə fərqi ölçün
5. Laravel N+1 query problemini tapın: `Debugbar` və ya `Telescope` ilə query sayını izləyin; N+1 olan bir endpoint tapın; `with()` eager loading əlavə edin; query sayını azaltın; `EXPLAIN ANALYZE` ilə yeni sorğunun necə işlədiyini görün
6. `k6` ilə benchmark qurun: əvvəl baseline ölçün (100 VU, 60s), sonra OPcache + FPM tuning tətbiq edin, yenidən ölçün; `RPS`, `p95 latency`, `error rate` metriklerini müqayisə edin; optimization-ın nə qədər təsir etdiyini rəqəmlərlə göstərin

## Əlaqəli Mövzular

- [Linux Proses İdarəetmə](07-linux-process-management.md) — PHP-FPM pool, worker management
- [Nginx](11-nginx.md) — FastCGI cache, worker_processes
- [Load Testing](46-load-testing.md) — k6, wrk, Locust ilə benchmark
- [Observability](42-observability.md) — performance metric-lərin izlənməsi
- [Prometheus](18-monitoring-prometheus.md) — PHP-FPM metrikləri, custom metrics
