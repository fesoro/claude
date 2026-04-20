# PHP Execution Models — CLI, FPM, CGI, Cron, Long-running

## Mündəricat
1. [Niyə model-lar fərqlidir?](#niyə-model-lar-fərqlidir)
2. [CLI (Command Line)](#cli-command-line)
3. [PHP-FPM (FastCGI Process Manager)](#php-fpm-fastcgi-process-manager)
4. [Köhnə CGI / mod_php](#köhnə-cgi--mod_php)
5. [Long-running (Octane, Swoole, RoadRunner)](#long-running)
6. [Cron jobs](#cron-jobs)
7. [Memory & process lifecycle](#memory--process-lifecycle)
8. [INI fərqlər](#ini-fərqlər)
9. [Debugging hər model üçün](#debugging-hər-model-üçün)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə model-lar fərqlidir?

```
PHP-in xüsusiyyəti: "shared-nothing" arxitektura.
Hər request təmiz state-də başlayır, sonra ölür.

AMMA bu yalnız WEB üçün doğrudur.
PHP fərqli kontekstlərdə işləyir:

  Web:        FPM, CGI, mod_php, Apache
  CLI:        artisan, console scripts, deployment
  Cron:       scheduled tasks
  Long-run:   Octane, Swoole, RoadRunner workers
  Embedded:   FrankenPHP, Caddy

Hər birində:
  - Memory limit fərqli
  - Timeout fərqli
  - INI config fərqli
  - Error handling fərqli
  - Bootstrap cost fərqli
```

---

## CLI (Command Line)

```
PHP CLI = "Standalone executable":
  php script.php
  php artisan migrate
  php bin/console command:run

Xüsusiyyətlər:
  ✓ Heç bir output buffering (default OFF)
  ✓ max_execution_time = 0 (unlimited!)
  ✓ memory_limit = 128M (artırılabilir)
  ✓ STDIN, STDOUT, STDERR streams
  ✓ pcntl extension dəstəklənir (signal handling, fork)
  ✓ readline (interactive prompt)

Use case:
  - Artisan commands
  - Migration scripts
  - Cron jobs
  - Background workers
  - Build / deployment scripts
  - Tests (PHPUnit, Pest)
  - Composer
```

```bash
# Configuration
php -i | grep "Configuration File"
# Loaded Configuration File: /etc/php/8.3/cli/php.ini

# CLI-specific config:
# /etc/php/8.3/cli/php.ini
memory_limit = 512M
max_execution_time = 0   # unlimited
display_errors = On
error_reporting = E_ALL

# Override per script
php -d memory_limit=2G script.php
php -d max_execution_time=3600 long-script.php
```

```php
<?php
// CLI-specific functions
echo "Stdout\n";
fwrite(STDERR, "Stderr\n");

// Read from stdin
$input = trim(fgets(STDIN));
$input = file_get_contents('php://stdin');

// Argument access
print_r($argv);    // ['script.php', 'arg1', 'arg2']
echo $argc;         // count

// Detect CLI mode
if (PHP_SAPI === 'cli') {
    echo "Running in CLI\n";
}

// Or:
if (php_sapi_name() === 'cli') { /* ... */ }
```

---

## PHP-FPM (FastCGI Process Manager)

```
PHP-FPM = web server-dən ayrı PHP worker pool.
Nginx (FastCGI client) ↔ PHP-FPM (FastCGI server) → response

Workflow:
  1. Nginx request alır
  2. Nginx FastCGI socket ilə FPM-ə göndərir
  3. FPM available worker-ə verir
  4. Worker PHP script işlədir
  5. Response Nginx-ə qaytarır
  6. Worker pool-a qayıdır (yeni request gözləyir)

Worker pool models:
  static:      Fixed worker sayı (predictable load)
  dynamic:     Min-Max range, demand-əsasən scale
  ondemand:    Yalnız request gələndə spawn (low traffic)
```

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
[www]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50              ; max workers
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500             ; worker N request-dən sonra restart (memory leak)
pm.process_idle_timeout = 10s

; Status
pm.status_path = /status
ping.path = /ping

; Slowlog
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s
```

```bash
# Worker hesablama
# Total RAM × 0.8 / avg_worker_memory = max_children
# Misal: 8GB × 0.8 / 64MB = ~100 worker

# Status check
curl http://localhost/status
# pool: www
# accepted conn: 12345
# listen queue: 0           ← bu 0 olmalıdır
# idle processes: 8
# active processes: 2
# max children reached: 0   ← bu 0 olmalıdır

# Reload (graceful)
sudo systemctl reload php8.3-fpm
# Worker-lər mövcud request-i bitirir, sonra restart
```

```
FPM xüsusiyyətləri:
  ✓ Shared-nothing (hər request təmiz)
  ✓ OPcache shared memory (bytecode cache)
  ✓ Pool-based isolation (multi-tenant)
  ✓ Slow request logging
  ✓ Status endpoint (monitoring)
  
  ✗ Bootstrap cost hər request (Laravel ~30-50ms)
  ✗ Long-running task üçün uyğun deyil (worker block)
  ✗ Connection pool yox (DB connect hər request)
```

---

## Köhnə CGI / mod_php

```
1. CGI (Common Gateway Interface) — KÖHNƏ
   Hər request üçün YENİ PHP process fork.
   Çox YAVAŞdır. Heç istifadə etmə.

2. mod_php (Apache module)
   PHP Apache process-ə bind olunmuş.
   Apache prefork MPM (process-per-request) ilə işləyir.
   
   ✗ Memory dolu (hər Apache process PHP yükləyir)
   ✗ Apache restart PHP-ni də restart edir
   ✗ Hər site üçün ayrı user mümkün deyil (mpm-prefork limit)
   
   2025-də NƏVƏ rast gəlinmir. FPM standartdır.

3. PHP-FPM + Nginx (modern)
   Nginx static fayl + reverse proxy
   PHP-FPM dynamic content
   Resource separation, daha sürətli, scalable
```

---

## Long-running

```
Long-running PHP — worker bir dəfə boot olunur, çoxlu request handle edir.

Tipik servisler:
  Laravel Octane + Swoole / RoadRunner / FrankenPHP
  ReactPHP
  Amphp

Üstünlük:
  ✓ Bootstrap cost amortize (1 dəfə)
  ✓ Connection pool keepalive
  ✓ 5-10× sürət artımı
  ✓ Memory data sharing imkanı

Çətinliklər:
  ✗ Memory leak — worker uzun yaşayır
  ✗ State pollution — request-arası
  ✗ Static property persistence
  ✗ Singleton container leakage
  ✗ Debug çətin (state müşahidə)

Solution:
  ✓ max_jobs = 500-1000 (worker restart)
  ✓ Scoped DI binding (per-request)
  ✓ Stateless code yazma intizamı
  ✓ Memory usage monitor (alerting)

Detallar: 162-laravel-octane-roadrunner-frankenphp.md
```

---

## Cron jobs

```bash
# crontab -e
# m h dom mon dow command

# Hər 5 dəqiqə
*/5 * * * * cd /var/www/app && php artisan queue:retry all >> /dev/null 2>&1

# Hər saat
0 * * * * php /var/www/app/artisan reports:hourly

# Gündəlik 03:00
0 3 * * * php /var/www/app/artisan backup:run

# Laravel single-cron (önerilir)
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1
# Bütün scheduled task-lar artisan/Schedule içində

# Lock-based (overlap qarşı)
* * * * * flock -n /tmp/myjob.lock -c 'php /var/www/app/artisan import:users'
```

```
Cron problem-lər:
  - Şəbəkə vaxt sinxronlaşması (NTP)
  - DST keçidi (gün vaxtı dəyişir)
  - Multi-server: eyni cron 5 yerdə → 5x işlədilir
    Həll: onOneServer() Laravel ya da DB-locking
  - Cron user fərqli mühit (PATH, env)
  - Output suppress edilmir → mailbox dolur
  
Modern alternativlər:
  - Kubernetes CronJob
  - AWS EventBridge / Lambda Schedule
  - Cloud Scheduler (GCP)
  - Systemd timers
```

---

## Memory & process lifecycle

```
                    | CLI         | FPM            | Octane          | Cron
────────────────────────────────────────────────────────────────────────────
Process duration   | Until exit  | Until N reqs   | Until N jobs   | Until exit
Bootstrap          | 1 dəfə      | Hər request    | 1 dəfə         | 1 dəfə
Memory cleanup     | Process exit | Process recycle| Manual + recycle| Process exit
GC trigger         | Automatic   | Per request    | Per request    | Automatic
File handles       | Process     | Per request    | Worker lifetime| Process
DB connections     | Process     | Per request    | Persistent     | Process

Tipik bug:
  - CLI script-də heap böyüyür → 2GB-da PHP crash
    Həll: $em->clear(), unset($var), gc_collect_cycles()
  
  - FPM worker-də memory leak (Eloquent N+1)
    Həll: max_requests = 500 (worker recycle)
  
  - Octane-də state leak request-lər arası
    Həll: scoped binding, flush listeners
```

---

## INI fərqlər

```ini
; /etc/php/8.3/cli/php.ini      — CLI
memory_limit = 512M
max_execution_time = 0           ; unlimited
display_errors = On
log_errors = On
error_reporting = E_ALL

; /etc/php/8.3/fpm/php.ini       — FPM  
memory_limit = 256M
max_execution_time = 30          ; web request 30s
display_errors = Off              ; production-da OFF (security)
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED

; OPcache (FPM-də ON, CLI-də adətən OFF)
opcache.enable = 1
opcache.enable_cli = 0           ; CLI-də OFF (bytecode cache lazım deyil)
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0  ; production: file dəyişməsini yoxlamır (deploy clear lazım)
```

---

## Debugging hər model üçün

```bash
# CLI
php -d xdebug.mode=debug script.php
XDEBUG_TRIGGER=1 php artisan tinker

# FPM
# Browser cookie: XDEBUG_SESSION=PHPSTORM
# Ya da query: ?XDEBUG_SESSION_START=1

# FPM slow log
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s
# 5s+ işləyən request stack trace ilə log-a yazılır

# Octane / Swoole
# stdout-dan log-a yönləndir, monitoring lazım
# Strace process-ə attach (Linux)
strace -p <pid>

# Cron
# stderr-i log-a yönləndir!
* * * * * php /script.php >> /var/log/cron.log 2>&1
```

---

## İntervyu Sualları

- PHP-FPM ilə CGI arasındakı fərq?
- mod_php niyə artıq istifadə olunmur?
- FPM `pm.max_children` necə hesablanır?
- `max_requests` worker-də niyə vacibdir?
- CLI-də `max_execution_time = 0` niyə default?
- OPcache `validate_timestamps = 0` production-da niyə?
- Long-running PHP-də memory leak necə qarşısı alınır?
- Cron-da `onOneServer` niyə lazımdır?
- `slowlog` FPM-də nəyə xidmət edir?
- `pm = ondemand` nə vaxt seçilir?
- PHP CLI-da `pcntl` niyə dəstəklənir, FPM-də yox?
- Octane-də FPM-dən fərqli nə var?
