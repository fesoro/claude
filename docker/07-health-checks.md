# Health Checks

## Nədir? (What is it?)

Health check — konteynerin düzgün işlədiyini yoxlayan mexanizmdir. Docker mütəmadi olaraq konteynerdə əmr icra edir; əgər əmr uğursuz olsa, konteyner "unhealthy" kimi işarələnir. Bu, orchestrator-lara (Compose, Swarm, K8s) konteyneri yenidən başlatmaq və ya traffic-i yönləndirməmək imkanı verir.

```
Container Status:
  starting  ──>  healthy  ──>  unhealthy
     │              │              │
     │         Test PASS       Test FAIL
     │         (exit 0)        (exit 1)
     │              │              │
  start_period   interval      retries exhausted
```

## Əsas Konseptlər (Key Concepts)

### HEALTHCHECK Dockerfile İnstruksiyası

```dockerfile
HEALTHCHECK [OPTIONS] CMD command

# Seçimlər:
# --interval=30s      Yoxlamalar arası interval (default: 30s)
# --timeout=30s       Əmr timeout-u (default: 30s)
# --start-period=0s   İlk başlanğıc müddəti (default: 0s)
# --start-interval=5s Başlanğıc müddətində interval (default: 5s)
# --retries=3         Uğursuz cəhdlər sayı (default: 3)

# Nümunə
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

# Health check-i deaktiv etmək
HEALTHCHECK NONE
```

**Exit code-lar:**
- `0` — healthy (sağlam)
- `1` — unhealthy (xəstə)
- `2` — reserved (istifadə etməyin)

### Docker Compose-da Health Check

```yaml
services:
  app:
    image: myapp
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 40s
      start_interval: 5s

  # Shell form
  mysql:
    image: mysql:8.0
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  # Health check-i deaktiv etmək
  worker:
    image: myapp
    healthcheck:
      disable: true
```

### depends_on ilə Health Check

```yaml
services:
  app:
    build: .
    depends_on:
      mysql:
        condition: service_healthy    # MySQL healthy olana qədər gözlə
      redis:
        condition: service_healthy

  mysql:
    image: mysql:8.0
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  redis:
    image: redis:7-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3
```

### Curl-əsaslı Health Check-lər

```dockerfile
# HTTP endpoint yoxlama
HEALTHCHECK CMD curl -f http://localhost:80/health || exit 1

# Spesifik status code yoxlama
HEALTHCHECK CMD curl -sf -o /dev/null -w "%{http_code}" http://localhost/ | grep -q "200" || exit 1

# Timeout ilə
HEALTHCHECK --timeout=10s CMD curl -f --max-time 5 http://localhost/health || exit 1

# HTTPS
HEALTHCHECK CMD curl -fk https://localhost:443/health || exit 1

# curl olmadıqda wget
HEALTHCHECK CMD wget --quiet --tries=1 --spider http://localhost/ || exit 1
```

### Custom Health Check Scriptləri

```bash
#!/bin/sh
# docker/healthcheck.sh

# PHP-FPM yoxlama
SCRIPT_NAME=/ping \
SCRIPT_FILENAME=/ping \
REQUEST_METHOD=GET \
cgi-fcgi -bind -connect 127.0.0.1:9000 > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "PHP-FPM is not responding"
    exit 1
fi

# Database əlaqəsi yoxlama
php -r "
try {
    new PDO('mysql:host=mysql;dbname=laravel', 'laravel', 'secret');
    echo 'DB OK';
} catch (Exception \$e) {
    echo 'DB FAIL: ' . \$e->getMessage();
    exit(1);
}
" || exit 1

echo "All checks passed"
exit 0
```

```dockerfile
COPY docker/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
  CMD /usr/local/bin/healthcheck.sh
```

### Restart Policies

Health check ilə birlikdə restart policy istifadə olunur:

```bash
# Restart policy-lər
docker run -d --restart=no           myapp  # Default, yenidən başlatma
docker run -d --restart=always       myapp  # Həmişə yenidən başlat
docker run -d --restart=unless-stopped myapp # Docker daemon başlayanda da
docker run -d --restart=on-failure   myapp  # Yalnız xəta olduqda
docker run -d --restart=on-failure:5 myapp  # Maksimum 5 dəfə
```

```yaml
# Docker Compose-da
services:
  app:
    image: myapp
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 5s
      retries: 3
```

| Policy | Container exits | Docker daemon restarts |
|--------|----------------|----------------------|
| no | Yenidən başlatmır | Yenidən başlatmır |
| always | Həmişə başladır | Həmişə başladır |
| unless-stopped | Həmişə başladır | Əl ilə dayandırılıbsa — xeyr |
| on-failure | Yalnız non-zero exit | Yalnız non-zero exit |

## Praktiki Nümunələr (Practical Examples)

### Müxtəlif Service-lər üçün Health Check-lər

```yaml
services:
  # Nginx
  nginx:
    image: nginx:alpine
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
      timeout: 5s
      retries: 3

  # MySQL
  mysql:
    image: mysql:8.0
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p$$MYSQL_ROOT_PASSWORD"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  # PostgreSQL
  postgres:
    image: postgres:16
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Redis
  redis:
    image: redis:7-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3

  # MongoDB
  mongo:
    image: mongo:7
    healthcheck:
      test: ["CMD", "mongosh", "--eval", "db.adminCommand('ping')"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Elasticsearch
  elasticsearch:
    image: elasticsearch:8.10.0
    healthcheck:
      test: ["CMD-SHELL", "curl -sf http://localhost:9200/_cluster/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 5
      start_period: 60s

  # RabbitMQ
  rabbitmq:
    image: rabbitmq:3-management
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "-q", "ping"]
      interval: 30s
      timeout: 10s
      retries: 3
```

### Health Check Statusunu Yoxlamaq

```bash
# Container health statusu
docker inspect --format='{{.State.Health.Status}}' container_name

# Son health check nəticələri
docker inspect --format='{{json .State.Health}}' container_name | jq .

# Health check logları
docker inspect --format='{{range .State.Health.Log}}{{.Output}}{{end}}' container_name

# docker ps-da health status görünür
docker ps --format "table {{.Names}}\t{{.Status}}"
# NAMES          STATUS
# laravel-app    Up 5 minutes (healthy)
# laravel-mysql  Up 5 minutes (healthy)
# laravel-redis  Up 5 minutes (healthy)

# Yalnız unhealthy konteynerləri görmək
docker ps --filter health=unhealthy
```

## PHP/Laravel ilə İstifadə (Usage with PHP/Laravel)

### PHP-FPM Health Check

```dockerfile
# PHP-FPM status endpoint-i aktiv etmək üçün
# docker/php/www.conf
# pm.status_path = /status
# ping.path = /ping
# ping.response = pong

# Üsul 1: PHP-FPM ping (ən sadə)
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php-fpm-healthcheck || exit 1

# Üsul 2: fcgi-client ilə
RUN apk add --no-cache fcgi
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
      cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

# Üsul 3: Custom PHP script
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
  CMD php /var/www/html/docker/healthcheck.php || exit 1
```

### PHP Health Check Script

```php
<?php
// docker/healthcheck.php

$checks = [];
$healthy = true;

// 1. PHP-FPM işləyir?
$checks['php'] = 'ok';

// 2. Database əlaqəsi
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST') ?: 'mysql',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_DATABASE') ?: 'laravel'
    );
    $pdo = new PDO($dsn, getenv('DB_USERNAME') ?: 'laravel', getenv('DB_PASSWORD') ?: 'secret');
    $pdo->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Exception $e) {
    $checks['database'] = 'fail: ' . $e->getMessage();
    $healthy = false;
}

// 3. Redis əlaqəsi
try {
    $redis = new Redis();
    $redis->connect(getenv('REDIS_HOST') ?: 'redis', (int)(getenv('REDIS_PORT') ?: 6379));
    $redis->ping();
    $checks['redis'] = 'ok';
} catch (Exception $e) {
    $checks['redis'] = 'fail: ' . $e->getMessage();
    $healthy = false;
}

// 4. Storage yazıla bilər?
$testFile = '/var/www/html/storage/framework/cache/.healthcheck';
if (file_put_contents($testFile, 'ok') !== false) {
    unlink($testFile);
    $checks['storage'] = 'ok';
} else {
    $checks['storage'] = 'fail: storage not writable';
    $healthy = false;
}

// Nəticə
echo json_encode($checks, JSON_PRETTY_PRINT) . "\n";
exit($healthy ? 0 : 1);
```

### Laravel Health Endpoint

```php
// routes/web.php
Route::get('/health', function () {
    $checks = [
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ];

    try {
        DB::connection()->getPDO();
        $checks['database'] = 'connected';
    } catch (\Exception $e) {
        $checks['database'] = 'disconnected';
        $checks['status'] = 'degraded';
    }

    try {
        Cache::store('redis')->put('health-check', true, 10);
        $checks['redis'] = 'connected';
    } catch (\Exception $e) {
        $checks['redis'] = 'disconnected';
        $checks['status'] = 'degraded';
    }

    $statusCode = $checks['status'] === 'ok' ? 200 : 503;
    return response()->json($checks, $statusCode);
});
```

### Nginx Health Check

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    root /var/www/html/public;

    # Health check endpoint (PHP-yə getmir, sürətlidir)
    location = /nginx-health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # App health check (PHP ilə)
    location = /health {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```yaml
# docker-compose.yml
services:
  nginx:
    image: nginx:alpine
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/nginx-health"]
      interval: 15s
      timeout: 5s
      retries: 3

  app:
    build: .
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s
```

### Tam Stack Health Check Docker Compose

```yaml
services:
  nginx:
    image: nginx:alpine
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/nginx-health"]
      interval: 15s
      timeout: 5s
      retries: 3
    depends_on:
      app:
        condition: service_healthy

  app:
    build: .
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "php", "/var/www/html/docker/healthcheck.php"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy

  queue:
    build: .
    command: php artisan queue:work
    restart: unless-stopped
    healthcheck:
      test: ["CMD-SHELL", "php artisan queue:monitor redis:default --max=100 || exit 1"]
      interval: 60s
      timeout: 10s
      retries: 3
    depends_on:
      app:
        condition: service_healthy

  mysql:
    image: mysql:8.0
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3
```

## Interview Sualları (Interview Questions)

### 1. Docker health check nədir?
**Cavab:** Konteynerin düzgün işlədiyini yoxlamaq üçün mütəmadi icra olunan əmrdir. Exit code 0 healthy, 1 unhealthy deməkdir. Docker konteyneri starting, healthy, unhealthy kimi işarələyir. Orchestrator-lar bu məlumatı konteynerləri yenidən başlatmaq və ya traffic-i yönləndirmək üçün istifadə edir.

### 2. `start_period` nə üçün lazımdır?
**Cavab:** Konteynerin ilk başlanğıcı üçün vaxt verir. Bu müddətdə uğursuz health check-lər retry sayına daxil edilmir. Database və ya böyük tətbiqlər yüklənmə vaxtı tələb edir, start_period bu müddəti gözləyir.

### 3. Health check restart policy ilə necə işləyir?
**Cavab:** Docker Swarm-da unhealthy konteyner avtomatik yenidən başladılır. Standalone Docker-da health check yalnız statusu göstərir, avtomatik restart etmir — bunun üçün `--restart` policy lazımdır. Docker Compose v2-də health check depends_on condition ilə istifadə olunur.

### 4. CMD və CMD-SHELL arasında fərq nədir?
**Cavab:** `CMD` exec form-dur, əmri birbaşa icra edir, shell olmadan. `CMD-SHELL` shell vasitəsilə icra edir (`/bin/sh -c`), pipe və redirect istifadə etmək olur. Sadə əmrlər üçün CMD, mürəkkəb əmrlər üçün CMD-SHELL istifadə edin.

### 5. PHP-FPM-in health check-ini necə edirsiniz?
**Cavab:** PHP-FPM ping/status endpoint-ini aktiv edərək, cgi-fcgi ilə yoxlamaq, custom PHP script ilə database/redis əlaqəsini yoxlamaq, və ya Nginx vasitəsilə HTTP endpoint yoxlamaq mümkündür. Ən yaxşısı PHP-FPM-in öz ping endpoint-ini istifadə etməkdir.

### 6. Health check nə qədər tez olmalıdır?
**Cavab:** Timeout production-da 5-10 saniyə, interval 15-30 saniyə tövsiyə olunur. Çox tez-tez yoxlama resurs istehlak edir, çox nadir yoxlama problemi gec aşkar edir. Health check əmri yüngül olmalıdır — tam database query əvəzinə ping istifadə edin.

## Best Practices

1. **Hər service üçün health check yazın** — Nginx, PHP-FPM, MySQL, Redis hamısı üçün.
2. **Health check-i yüngül saxlayın** — Sadə ping/status, ağır query-lər yox.
3. **start_period istifadə edin** — İlk başlanğıc vaxtı verin.
4. **depends_on condition ilə istifadə edin** — Service-lərin hazırlığını gözləyin.
5. **Restart policy təyin edin** — `unless-stopped` və ya `on-failure`.
6. **Health endpoint yaradın** — `/health` endpoint-i database, cache, storage yoxlasın.
7. **Access log-u deaktiv edin** — Health check endpoint-inin loglarını söndürün.
8. **Müxtəlif səviyyədə yoxlayın** — Nginx (HTTP), PHP-FPM (process), App (dependency).
9. **Timeout-u real şərtlərə uyğunlaşdırın** — Yavaş network-da timeout artırın.
10. **Health check nəticələrini monitorinq edin** — Unhealthy alert-ləri qurun.
