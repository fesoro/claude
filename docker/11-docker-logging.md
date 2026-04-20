# Docker Logging

## Nədir? (What is it?)

Docker logging — konteynerlərdən log məlumatlarını toplamaq, saxlamaq və analiz etmək prosesidir. Docker konteynerlərin stdout və stderr çıxışlarını tutur və konfiqurasiya olunan logging driver vasitəsilə saxlayır.

Produksiya mühitində düzgün logging olmadan problem-ləri tapmaq, performansı izləmək və audit tələblərini yerinə yetirmək mümkün deyil.

## Əsas Konseptlər

### 1. Docker Logging Arxitekturası

```
┌──────────────┐    stdout/stderr    ┌──────────────┐
│  Konteyner   │ ──────────────────→ │ Docker Daemon│
│  (App proses)│                     │              │
└──────────────┘                     └──────┬───────┘
                                            │
                                    ┌───────▼────────┐
                                    │ Logging Driver  │
                                    ├────────────────┤
                                    │ • json-file    │
                                    │ • syslog       │
                                    │ • fluentd      │
                                    │ • awslogs      │
                                    │ • gcplogs      │
                                    │ • journald     │
                                    │ • splunk       │
                                    └────────────────┘
```

**Əsas prinsip:** Konteynerlərdə log-lar fayla deyil, stdout/stderr-ə yazılmalıdır. Docker onları tutur və logging driver vasitəsilə istənilən yerə yönləndirir.

### 2. Logging Driver-lər

```bash
# Default logging driver-i görmək
docker info --format '{{.LoggingDriver}}'
# json-file

# Konteyner yaradarkən driver seçmək
docker run -d --log-driver=syslog --name myapp php:8.3-fpm

# Driver seçenekleri ilə
docker run -d \
    --log-driver=json-file \
    --log-opt max-size=10m \
    --log-opt max-file=3 \
    --name myapp \
    php:8.3-fpm
```

**Mövcud driver-lər:**

| Driver | Təsvir | docker logs dəstəyi |
|--------|--------|---------------------|
| json-file | Default, JSON formatında fayla yazır | ✅ |
| local | Optimallaşdırılmış local format | ✅ |
| syslog | Syslog daemon-a göndərir | ❌ |
| journald | systemd journal-a yazır | ✅ |
| fluentd | Fluentd-ə göndərir | ❌ |
| awslogs | AWS CloudWatch-a göndərir | ❌ |
| gcplogs | Google Cloud Logging-ə göndərir | ❌ |
| splunk | Splunk-a göndərir | ❌ |
| none | Log tutulmur | ❌ |

### 3. json-file Driver (Default)

```bash
# Default driver — JSON formatında saxlayır
# Log faylının yeri:
# /var/lib/docker/containers/<container-id>/<container-id>-json.log

# Log faylını birbaşa oxumaq
sudo cat /var/lib/docker/containers/<id>/<id>-json.log

# JSON format:
# {"log":"Laravel development server started\n","stream":"stdout","time":"2026-04-16T10:30:00.123456789Z"}
```

### 4. Log Rotation

```bash
# Konteyner səviyyəsində
docker run -d \
    --log-opt max-size=10m \
    --log-opt max-file=5 \
    --name myapp \
    myapp:latest

# Daemon səviyyəsində (bütün konteynerlər üçün)
# /etc/docker/daemon.json
```

```json
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "5",
    "compress": "true"
  }
}
```

```bash
# Daemon-u restart edin
sudo systemctl restart docker
```

**Log rotation olmadan problem:**
```bash
# Log faylının ölçüsünü yoxlayın
sudo du -sh /var/lib/docker/containers/*/*-json.log
# 5.2G  ← Bu, diski doldura bilər!

# Bütün konteynerlərin log ölçüsü
sudo sh -c 'du -sh /var/lib/docker/containers/*/*-json.log' | sort -h
```

### 5. docker logs Əmri

```bash
# Bütün log-ları görmək
docker logs myapp

# Son 100 sətir
docker logs --tail 100 myapp

# Real-time izləmək (tail -f kimi)
docker logs -f myapp

# Zaman aralığı ilə
docker logs --since 2026-04-16T10:00:00 myapp
docker logs --since 30m myapp
docker logs --until 2026-04-16T11:00:00 myapp

# Timestamps ilə
docker logs -t myapp

# stderr yalnız
docker logs myapp 2>&1 1>/dev/null

# stdout yalnız
docker logs myapp 2>/dev/null
```

### 6. Docker Compose-da Logging

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:latest
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "5"
        tag: "{{.Name}}/{{.ID}}"

  nginx:
    image: nginx:alpine
    logging:
      driver: json-file
      options:
        max-size: "5m"
        max-file: "3"
```

### 7. Mərkəzləşdirilmiş Logging (Centralized Logging)

#### Fluentd ilə

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:latest
    logging:
      driver: fluentd
      options:
        fluentd-address: localhost:24224
        tag: laravel.app

  fluentd:
    image: fluent/fluentd:v1.16
    ports:
      - "24224:24224"
    volumes:
      - ./fluentd/conf:/fluentd/etc
    depends_on:
      - elasticsearch

  elasticsearch:
    image: elasticsearch:8.12.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
    ports:
      - "9200:9200"

  kibana:
    image: kibana:8.12.0
    ports:
      - "5601:5601"
    depends_on:
      - elasticsearch
```

**Fluentd konfiqurasiyası:**

```xml
<!-- fluentd/conf/fluent.conf -->
<source>
  @type forward
  port 24224
</source>

<filter laravel.**>
  @type parser
  key_name log
  <parse>
    @type json
  </parse>
</filter>

<match laravel.**>
  @type elasticsearch
  host elasticsearch
  port 9200
  index_name laravel-logs
  type_name _doc
  logstash_format true
  logstash_prefix laravel
  <buffer>
    flush_interval 5s
  </buffer>
</match>
```

#### AWS CloudWatch ilə

```yaml
services:
  app:
    image: myapp:latest
    logging:
      driver: awslogs
      options:
        awslogs-group: /ecs/laravel-app
        awslogs-region: eu-west-1
        awslogs-stream-prefix: app
```

## PHP/Laravel ilə İstifadə

### Laravel-i stdout/stderr-ə Log Yazmaq

```php
// config/logging.php
return [
    'default' => env('LOG_CHANNEL', 'stderr'),

    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
        ],

        // Docker üçün — stdout-a JSON formatında
        'docker' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'info'),
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stdout',
            ],
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
        ],

        // Stack — bir neçə channel-ə eyni anda
        'stack' => [
            'driver' => 'stack',
            'channels' => ['docker'],
            'ignore_exceptions' => false,
        ],
    ],
];
```

### Structured Logging (JSON Format)

```php
// app/Logging/JsonFormatter.php
namespace App\Logging;

use Monolog\Formatter\JsonFormatter as BaseJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends BaseJsonFormatter
{
    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $record->datetime->format('c'),
            'level' => $record->level->getName(),
            'message' => $record->message,
            'context' => $record->context,
            'extra' => array_merge($record->extra, [
                'app' => config('app.name'),
                'environment' => config('app.env'),
                'request_id' => request()?->header('X-Request-ID'),
            ]),
        ];

        return json_encode($data) . "\n";
    }
}
```

### Request Logging Middleware

```php
// app/Http/Middleware/RequestLogMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::info('HTTP Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
        ]);

        return $response;
    }
}
```

### Nginx Log-larını Docker-a Yönləndirmək

```dockerfile
# Nginx log-larını stdout/stderr-ə yönləndirmək
FROM nginx:alpine

# Access log → stdout, Error log → stderr
RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log
```

### PHP-FPM Log Konfiqurasiyası

```ini
; docker/php-fpm/www.conf
[www]
; PHP-FPM log-larını stderr-ə yönləndirmək
catch_workers_output = yes
decorate_workers_output = no

; Slow log (yavaş sorğuları tutmaq)
slowlog = /proc/self/fd/2
request_slowlog_timeout = 5s

; Error log
php_admin_value[error_log] = /proc/self/fd/2
php_admin_flag[log_errors] = on
```

## İntervyu Sualları

### S1: Docker-da log-lar harada saxlanılır?
**C:** Default olaraq (json-file driver), log-lar `/var/lib/docker/containers/<container-id>/<container-id>-json.log` faylında JSON formatında saxlanılır. Hər log sətri timestamp, stream tipi (stdout/stderr) və log məzmununu ehtiva edir. Logging driver dəyişdirildikdə log-lar fərqli yerə yönləndirilir.

### S2: Konteynerlərdə niyə log-lar fayla deyil stdout/stderr-ə yazılmalıdır?
**C:** Docker stdout/stderr-i tutur və logging driver vasitəsilə idarə edir. Bu, log rotation, mərkəzləşdirilmiş logging, `docker logs` əmri kimi Docker xüsusiyyətlərindən istifadə etməyə imkan verir. Fayla yazılan log-lar Docker tərəfindən idarə olunmur və konteyner silinəndə itə bilər.

### S3: Log rotation niyə vacibdir və necə konfiqurasiya olunur?
**C:** Log rotation olmadan log faylları böyüyür və diski doldurur. `--log-opt max-size=10m --log-opt max-file=5` ilə konteyner səviyyəsində, `/etc/docker/daemon.json`-da isə bütün konteynerlər üçün konfiqurasiya olunur. Produksiyada log rotation MÜTLƏQDİR.

### S4: `docker logs` əmri bütün logging driver-lərlə işləyirmi?
**C:** Xeyr. `docker logs` yalnız `json-file`, `local` və `journald` driver-ləri ilə işləyir. `syslog`, `fluentd`, `awslogs` kimi remote driver-lərdə `docker logs` işləmir — log-lar birbaşa remote sistemə göndərilir. Bu driver-lərdə log-ları görmək üçün remote sistemi (CloudWatch, Kibana) istifadə etmək lazımdır.

### S5: EFK stack nədir?
**C:** Elasticsearch + Fluentd + Kibana. Fluentd konteynerlərdən log-ları toplayır, Elasticsearch-də saxlayır, Kibana ilə vizualizasiya və axtarış edilir. Docker mühitlərində mərkəzləşdirilmiş logging üçün populyar həlldir. Alternativ olaraq ELK stack (Logstash əvəzinə Fluentd) istifadə olunur.

### S6: Laravel-də Docker üçün logging necə konfiqurasiya olunur?
**C:** `config/logging.php`-də `stderr` və ya custom `docker` channel yaradılır, `StreamHandler` ilə `php://stdout`-a yazılır. JSON format istifadə olunur (`JsonFormatter`). `.env`-də `LOG_CHANNEL=stderr` qoyulur. Nginx və PHP-FPM log-ları da stdout/stderr-ə yönləndirilir.

### S7: Structured logging nə üstünlük verir?
**C:** JSON formatında structured log-lar maşın tərəfindən parse oluna bilir, axtarış və filter etmək asandır (Elasticsearch, CloudWatch), kontekst məlumatı (user_id, request_id, duration) əlavə etmək mümkündür, log aggregation tool-ları ilə yaxşı işləyir. Plain text log-larda isə parse etmək regex tələb edir.

## Best Practices

1. **stdout/stderr-ə yazın** — fayla deyil, Docker log sistemindən istifadə edin
2. **Log rotation MÜTLƏQDİR** — `max-size` və `max-file` konfiqurasiya edin
3. **JSON format istifadə edin** — structured logging, parse etmək asandır
4. **Mərkəzləşdirilmiş logging qurun** — produksiyada EFK/ELK stack
5. **Log level-ləri düzgün istifadə edin** — DEBUG yalnız development-də
6. **Request ID əlavə edin** — distributed tracing üçün
7. **Sensitive data log-lamayın** — password, token, credit card
8. **Nginx/PHP-FPM log-larını stdout/stderr-ə yönləndirin**
9. **`none` driver-dən uzaq durun** — debug çətinləşir
10. **Log-ları monitor edin** — alert qurun (error rate artanda)
