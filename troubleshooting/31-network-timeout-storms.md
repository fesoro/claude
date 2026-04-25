# Network Timeout Storms (Senior)

## Problem (nə görürsən)

Bir servisdə timeout başlayır, bu kaskad şəkildə yayılır. Yavaş upstream servis bütün worker-ları bloklamağa başlayır, bu isə yeni request-lərin stack olmasına səbəb olur. Az sayda yavaş sorğu bütün sistemi iflic edə bilir.

Simptomlar:
- Birdən bütün request-lər timeout verir, amma problem başqa bir servisdədir
- `cURL error 28: Operation timed out` seli log-larda
- PHP-FPM worker-larının hamısı məşğul, yeni request-lər `502` görür
- Queue backlog artır — worker-lar ayrı bir service-i gözləyir
- Latency normal aniden `30s → timeout`-a qalxır
- Database yox, sənin koding-in de yox — amma hər şey yavaşdır

## Sürətli triage (ilk 5 dəqiqə)

### Problem haradandır?

```bash
# PHP-FPM worker-lar məşğuldur?
systemctl status php8.3-fpm
# Yoxsa "active workers" sayına bax

# Hansı URL-lər yavaşdır?
tail -f /var/log/nginx/access.log | grep " [0-9]\{2,\}\." | awk '{print $7, $NF}' | sort -k2 -rn | head -20
# Son kolon request_time (əgər log formatında varsa)

# Aktiv TCP bağlantıları
ss -s
netstat -an | grep ESTABLISHED | wc -l
```

### Hansı host-a bağlantı yavaşdır?

```bash
# Spesifik servislərə latency yoxla
time curl -sf https://api.stripe.com/v1 -o /dev/null
time curl -sf https://db-host:5432 -o /dev/null 2>&1  # Connection check

# DNS yavaşdır?
time dig api.stripe.com
time nslookup api.stripe.com

# Database connection
time mysql -h db-host -u user -p -e "SELECT 1"
```

### Worker-lar nə gözləyir?

```bash
# PHP-FPM worker stack trace (Linux-da)
strace -p $(pgrep -f "php-fpm: pool" | head -1) -e trace=network 2>&1 | head -20

# Daha sadə: worker-ın socket bağlantılarına bax
ls -la /proc/$(pgrep -f "php-fpm: pool" | head -1)/fd | grep socket
```

## Diaqnoz

### Kaskad timeout mexanizmi

```
Vəziyyət:
- 60 PHP-FPM worker var
- Upstream servis (məs: payment-service) 30s cavab verir
- Hər saniyə 5 request gəlir

Nə baş verir:
- 0s: 5 worker payment-service-i gözləyir
- 6s: 30 worker artıq gözləyir (pool dolmağa başlayır)
- 12s: 60 worker hamısı gözləyir — pool DOLU
- 13s: Yeni request → 502 Bad Gateway (worker yoxdur)
- 30s: İlk worker timeout alır, azad olur
- Amma 30s ərzində gələn request-lər hamısı 502 almışdı
```

Bu **timeout amplification** — bir servisdə gecikmə hər şeyi iflic edir.

### Timeout dəyərləri yanlışdır

```bash
# php.ini default timeout dəyərləri
php -i | grep -E "default_socket_timeout|max_execution_time"
# default_socket_timeout = 60  ← çox yüksək!
# max_execution_time = 30

# Guzzle/Http facade timeout
grep -r "timeout\|connect_timeout" config/ app/
```

Əgər `default_socket_timeout=60` varsa — yavaş servis bütün worker-ı 60 saniyə bloklar.

### Connection pool problemi

```bash
# PgBouncer/MySQL pool dolub?
# PgBouncer stats
psql -h 127.0.0.1 -p 6432 -U pgbouncer pgbouncer -c "SHOW POOLS;"
psql -h 127.0.0.1 -p 6432 -U pgbouncer pgbouncer -c "SHOW CLIENTS;"

# MySQL connection sayı
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -e "SHOW PROCESSLIST;" | grep -v Sleep | head -20
```

### Retry storm

```bash
# Log-larda retry pattern varmı?
grep "retry\|Retrying" storage/logs/laravel.log | grep "$(date '+%Y-%m-%d %H')" | wc -l
```

Servis yavaş olduqda client-lər retry edir → server-ə daha çox yük → daha da yavaşlayır → daha çox retry → **retry storm**.

## Fix (qanaxmanı dayandır)

### Anlıq: Yavaş servisi circuit break et

```php
// config/services.php
'payment' => [
    'timeout' => env('PAYMENT_TIMEOUT', 5),
    'connect_timeout' => env('PAYMENT_CONNECT_TIMEOUT', 2),
],

// Service class
Http::withOptions([
    'timeout' => config('services.payment.timeout'),
    'connect_timeout' => config('services.payment.connect_timeout'),
])->post('https://payment-service/charge', $data);
```

Timeout-ları **dərhal azalt** ki, worker-lar tez azad olsun.

### Yavaş endpoint-i feature flag ilə söndür

```bash
# Anlıq: payment endpoint-i söndür
php artisan tinker --execute="
    cache()->put('feature.payments_enabled', false, 300);
"

# Controller-da
if (! Cache::get('feature.payments_enabled', true)) {
    return response()->json(['error' => 'Ödənişlər müvəqqəti dayandırılıb'], 503);
}
```

### PHP-FPM worker-ları restart et

Worker-lar stuck vəziyyətdədirsə:
```bash
systemctl reload php8.3-fpm   # Graceful — mövcud request-lər bitər
# Yoxsa
systemctl restart php8.3-fpm  # Sürətli amma aktiv request-lər kəsilir
```

### Queue worker-ları azalt (yük azaldır)

```bash
# Supervisor-da worker sayını azalt
# /etc/supervisor/conf.d/laravel-worker.conf
; numprocs=10  → 3
supervisorctl reread
supervisorctl update
```

## Circuit Breaker ilə doğru dizayn

```php
// Sadə Redis-based circuit breaker
class CircuitBreaker
{
    public function __construct(
        private string $service,
        private int $failureThreshold = 5,
        private int $resetTimeout = 30,
    ) {}

    public function call(callable $fn): mixed
    {
        if ($this->isOpen()) {
            throw new ServiceUnavailableException("{$this->service} circuit is open");
        }

        try {
            $result = $fn();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function isOpen(): bool
    {
        return Cache::get("circuit:{$this->service}:open", false);
    }

    private function onFailure(): void
    {
        $failures = Cache::increment("circuit:{$this->service}:failures");
        if ($failures >= $this->failureThreshold) {
            Cache::put("circuit:{$this->service}:open", true, $this->resetTimeout);
        }
    }

    private function onSuccess(): void
    {
        Cache::forget("circuit:{$this->service}:failures");
        Cache::forget("circuit:{$this->service}:open");
    }
}

// İstifadə
$breaker = new CircuitBreaker('payment-service', failureThreshold: 3, resetTimeout: 60);
$result = $breaker->call(fn() => $paymentService->charge($amount));
```

## Timeout dəyərlərini düzgün qur

```php
// Laravel HTTP client — hər servis üçün ayrı timeout
class StripeService
{
    private Http $client;

    public function __construct()
    {
        $this->client = Http::baseUrl('https://api.stripe.com/v1')
            ->timeout(5)           // Total timeout
            ->connectTimeout(2)    // Connection timeout ayrı
            ->retry(2, 500, fn($e) => $e instanceof ConnectionException);
    }
}
```

```ini
; php.ini — socket timeout-u azalt (default 60 çox yüksəkdir)
default_socket_timeout = 10

; max_execution_time — request max ömrü
max_execution_time = 30
```

## Əsas səbəbin analizi

- Timeout storm-u başladan servis hansıydı? Niyə yavaşladı?
- Timeout dəyərləri düzgün qurulmuşdumu?
- Circuit breaker var idimi?
- Worker sayı yük altında yetərli idimi?
- Connection pool dolu idi? Limit nə idi?

## Qarşısının alınması

**Layered timeout strategiyası:**
```
Client → Nginx (60s) → PHP-FPM (30s) → App code (5s per service)
```

Hər layer bir öncəki layer-dan AZ timeout olmalıdır. Əks halda yuxarıdan kaskad olur.

```bash
# Nginx upstream timeout
upstream php-fpm {
    server 127.0.0.1:9000;
    keepalive 32;
}

location ~ \.php$ {
    fastcgi_read_timeout 30s;      # PHP max execution < bu dəyər olmalı
    fastcgi_connect_timeout 5s;
    fastcgi_send_timeout 10s;
}
```

**Monitoring:**
```bash
# p99 latency → alert
# Prometheus: http_request_duration_seconds{quantile="0.99"}
# Alert: p99 > 3s → warning, p99 > 10s → critical

# PHP-FPM active workers
# Alert: active_workers / max_workers > 80% → scale ya da investigate
```

## Yadda saxlanacaq komandalar

```bash
# Yavaş request-ləri tap (nginx log-dan)
awk '$NF > 2 {print $7, $NF}' /var/log/nginx/access.log | sort -k2 -rn | head -20

# Aktiv bağlantılar
ss -s
netstat -an | grep ESTABLISHED | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | head

# DNS latency
time dig api.stripe.com

# Xarici servis latency
time curl -sf https://api.stripe.com/v1 -o /dev/null -w "%{time_total}\n"

# PHP-FPM process durations
ps aux | grep "php-fpm: pool" | awk '{print $10, $11, $12}'  # TIME column

# PgBouncer pool status
psql -h 127.0.0.1 -p 6432 -U pgbouncer pgbouncer -c "SHOW POOLS;"

# Circuit breaker state
redis-cli KEYS "circuit:*"
redis-cli GET "circuit:payment-service:open"
```

## Interview sualı

"Birdən bütün request-lər timeout verməyə başladı amma database OK-dir. Nədir?"

Güclü cavab:
- "Bu classic timeout kaskad. Bir xarici servis yavaşladı, worker-lar onu gözləyir, pool dolur, yeni request-lər 502 görür."
- "Triage: hansı servis çağırışı yavaş? Nginx log-larından yavaş endpoint-ləri tapıram. Sonra `time curl` ilə upstream-ləri yoxlayıram."
- "Anlıq fix: yavaş servisin timeout-unu azaldıram (5s-ə) ki, worker-lar tez azad olsun. Circuit breaker-i açıram."
- "Kök problem: timeout dəyərləri çox yüksəkdi (`default_socket_timeout=60`). Hər servis üçün ayrı timeout + circuit breaker qurdum."
- "Post-incident: layered timeout strategiyası tətbiq etdim — Nginx > PHP > service timeout, hər biri bir öncəkindən az."
