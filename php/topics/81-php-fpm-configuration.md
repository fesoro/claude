# PHP-FPM Konfiqurasiyası

## Mündəricat
1. [PHP-FPM nədir?](#php-fpm-nədir)
2. [Process Manager Növləri](#process-manager-növləri)
3. [Worker Pool Sizing](#worker-pool-sizing)
4. [Əsas Parametrlər](#əsas-parametrlər)
5. [OPcache ilə əlaqə](#opcache-ilə-əlaqə)
6. [Status Page və Monitoring](#status-page-və-monitoring)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## PHP-FPM nədir?

```
PHP-FPM (FastCGI Process Manager) — PHP proseslərini idarə edən daemon.

Nginx/Apache → FastCGI (TCP/Unix socket) → PHP-FPM Master → Workers

┌─────────┐   HTTP    ┌─────────┐  FastCGI  ┌──────────────────────┐
│  Nginx  │──────────►│  Nginx  │──────────►│   PHP-FPM Master     │
└─────────┘           └─────────┘           │  ┌────────────────┐  │
                                             │  │  Worker Pool   │  │
                                             │  │  ┌──────────┐  │  │
                                             │  │  │ Worker 1 │  │  │
                                             │  │  │ Worker 2 │  │  │
                                             │  │  │ Worker 3 │  │  │
                                             │  │  └──────────┘  │  │
                                             │  └────────────────┘  │
                                             └──────────────────────┘

Master prosesi worker-ları idarə edir.
Hər worker bir PHP prosesidir.
Worker request alır → işləyir → növbəti request-i gözləyir.
```

---

## Process Manager Növləri

### static
```
Sabit sayda worker. Həmişə eyni sayda proses işləyir.

pm = static
pm.max_children = 20

┌──────────────────────────────────────────┐
│  Həmişə 20 worker aktiv                  │
│  W1 W2 W3 W4 W5 W6 W7 W8 W9 W10         │
│  W11 W12 W13 W14 W15 W16 W17 W18 W19 W20│
└──────────────────────────────────────────┘

+ Sadə, proqnozlaşdırıla bilən
+ Yük artanda worker yaratma gecikmə yoxdur
- Aşağı yükdə belə RAM istifadə edir
- Yüksək yükdə kafi olmaya bilər
```

### dynamic
```
Yükə görə worker sayı dəyişir.

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10

Yük az:          Yük çox:
┌──────────┐     ┌──────────────────────────┐
│ 5 worker │     │ 50 worker-a qədər böyüyür│
└──────────┘     └──────────────────────────┘

+ RAM effektiv istifadə
+ Yükə adaptasiya
- Worker yaratma CPU xərci var
- min/max_spare düzgün seçilməlidir
```

### ondemand
```
Yalnız request gəldikdə worker yaradılır.

pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s

İlk request:     Boş qaldıqda:
┌───────────┐    ┌───────────┐
│ Worker    │    │  Worker   │
│ yaradıldı │    │  silindi  │
└───────────┘    └───────────┘

+ Ən aşağı RAM istifadəsi
- İlk request-də gecikmə (cold start)
- Yüksək trafik altında tövsiyə edilmir
```

---

## Worker Pool Sizing

```
Düstur:
  max_children = RAM / hər worker üçün RAM

Nümunə:
  Cəmi RAM: 4 GB
  PHP-FPM üçün: 3 GB (1 GB sistem + DB)
  Hər worker: ~50 MB
  max_children = 3000 MB / 50 MB = 60

Worker RAM-ı necə ölçmək:
  ps --no-headers -o "rss,cmd" -C php-fpm | awk '{sum+=$1} END {print sum/NR/1024 " MB"}'

Diqqət:
  Bütün max_children eyni anda aktiv olmaya bilər
  Database connection limit ilə uyğunlaşdır:
    max_children <= DB max_connections - sistem rezervi
```

---

## Əsas Parametrlər

```ini
[www]
; Socket (Unix socket TCP-dən sürətlidir eyni serverdə)
listen = /var/run/php-fpm/www.sock
listen.owner = nginx
listen.group = nginx

; Process manager
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20

; Worker restart (memory leak qarşısını almaq üçün)
pm.max_requests = 500

; Timeout
request_terminate_timeout = 60s
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm/slow.log

; Environment
env[HOSTNAME] = $HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin

; PHP ini override
php_admin_value[memory_limit] = 256M
php_admin_value[error_log] = /var/log/php-fpm/error.log
php_flag[display_errors] = off
```

---

## OPcache ilə əlaqə

```
Hər PHP-FPM worker öz OPcache payına malikdir.
Shared memory vasitəsilə paylaşılır.

┌─────────────────────────────────────────────┐
│              Shared Memory                  │
│  ┌──────────────────────────────────────┐  │
│  │           OPcache                    │  │
│  │  compiled bytecode cache             │  │
│  └──────────────────────────────────────┘  │
│                                             │
│  Worker 1 ──┐                              │
│  Worker 2 ──┼──► Shared OPcache okuyur     │
│  Worker 3 ──┘                              │
└─────────────────────────────────────────────┘

opcache.memory_consumption = 256    ; MB
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0     ; Production: 0, Dev: 1
opcache.revalidate_freq = 0

max_accelerated_files worker sayından çox olmalıdır!
```

---

## Status Page və Monitoring

```ini
; pool konfiqurasiyasına əlavə et
pm.status_path = /fpm-status
ping.path = /fpm-ping
ping.response = pong
```

```
GET /fpm-status?full

pool:                 www
process manager:      dynamic
start time:           01/Jan/2024:10:00:00 +0000
start since:          86400
accepted conn:        1234567
listen queue:         0          ← bu artırsa problem var!
max listen queue:     10
listen queue len:     128
idle processes:       5
active processes:     3
total processes:      8
max active processes: 45
max children reached: 0          ← bu > 0 olarsa, max_children artır

listen queue > 0 → worker-lar request-ləri yetiştirə bilmir!
```

---

## PHP İmplementasiyası

```php
<?php
// health_check.php — FPM vəziyyətini yoxlayan skript

function checkFpmStatus(string $statusUrl): array
{
    $ch = curl_init($statusUrl . '?json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return ['status' => 'error', 'message' => 'FPM unreachable'];
    }

    $data = json_decode($response, true);

    $listenQueue = $data['listen queue'] ?? 0;
    $maxChildrenReached = $data['max children reached'] ?? 0;

    $warnings = [];
    if ($listenQueue > 0) {
        $warnings[] = "Listen queue: {$listenQueue} — worker-lar yetişmir";
    }
    if ($maxChildrenReached > 0) {
        $warnings[] = "Max children reached: {$maxChildrenReached} — pm.max_children artır";
    }

    return [
        'status' => empty($warnings) ? 'ok' : 'warning',
        'active_processes' => $data['active processes'],
        'idle_processes' => $data['idle processes'],
        'warnings' => $warnings,
    ];
}

// Worker restart sayacı (pm.max_requests simulyasiyası)
// Bu PHP kodunda deyil, pool konfiqurasiyasında edilir:
// pm.max_requests = 500
// 500 request-dən sonra worker yenidən başlayır → memory leak önlənir
```

```php
<?php
// Worker memory istifadəsini izləmək
// (deploy skriptlərində istifadə olunur)

function getWorkerMemoryUsage(): array
{
    $output = shell_exec("ps --no-headers -o 'pid,rss,comm' -C php-fpm");
    $workers = [];

    foreach (explode("\n", trim($output)) as $line) {
        [$pid, $rss, $comm] = preg_split('/\s+/', trim($line), 3);
        $workers[] = [
            'pid' => (int)$pid,
            'memory_mb' => round($rss / 1024, 2),
        ];
    }

    $avgMemory = array_sum(array_column($workers, 'memory_mb')) / count($workers);

    return [
        'worker_count' => count($workers),
        'avg_memory_mb' => round($avgMemory, 2),
        'total_memory_mb' => round(array_sum(array_column($workers, 'memory_mb')), 2),
    ];
}
```

---

## İntervyu Sualları

- `pm = static` vs `dynamic` vs `ondemand` — hər birini nə vaxt seçərsiniz?
- `pm.max_children` dəyərini necə hesablayırsınız?
- `listen queue` artırsa nə edərsiniz?
- `pm.max_requests` nə üçün lazımdır?
- PHP-FPM worker-ları OPcache-i necə paylaşır?
- Unix socket vs TCP socket — fərqi nədir, nə vaxt hansını seçərsiniz?
- Production-da `opcache.validate_timestamps = 0` niyə vacibdir?
- Worker sayı artırdınız amma performance yaxşılaşmadı — səbəb nə ola bilər?
