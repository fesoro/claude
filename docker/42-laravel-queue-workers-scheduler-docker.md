# Laravel Queue Workers, Scheduler & Horizon in Docker

> **Səviyyə (Level):** ⭐⭐⭐ Senior

## Nədir? (What is it?)

Laravel tətbiqinin 3 fərqli runtime rolu var:

1. **Web** — HTTP request-lərə cavab verir (PHP-FPM)
2. **Queue worker** — arxa planda job-ları icra edir (`queue:work`)
3. **Scheduler** — cron kimi vaxtaşırı işləyir (`schedule:run` hər dəqiqə)

Docker-də bunları **ayrı konteynerlər** kimi işlətmək lazımdır. Niyə:
- Hər birinin fərqli ölçüləndirmə tələbi var (worker-ları 10-a qaldır, web 3-ə bəs etsin)
- Graceful shutdown fərqlidir (worker mid-job dayanmalı deyil)
- Resource limit-i fərqlidir (worker RAM-aktır)

## Arxitektura

```
┌────────────────────────────────────────────────────┐
│  Eyni Docker image (myapp:v1.2.3)                   │
│  Fərqli CMD ilə 4 container rolu                    │
├────────────────────────────────────────────────────┤
│  web       → php-fpm                                 │
│  worker    → php artisan queue:work                  │
│  scheduler → schedule:run loop                       │
│  horizon   → php artisan horizon                     │
└────────────────────────────────────────────────────┘
         ↓
┌────────────────────────────────────────────────────┐
│  Redis (queue backend) / SQS / database             │
└────────────────────────────────────────────────────┘
```

## Docker Compose Setup

```yaml
services:
  # Base build — image bir dəfə qurulur
  app:
    build:
      context: .
      target: production
    image: myapp:${APP_VERSION:-latest}
    # Web role — PHP-FPM
    command: ["php-fpm"]
    environment:
      - APP_ROLE=web
    volumes:
      - storage:/var/www/html/storage
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started
  
  # Queue worker
  worker:
    image: myapp:${APP_VERSION:-latest}
    command: [
      "php", "artisan", "queue:work",
      "--queue=high,default,low",
      "--sleep=3",
      "--tries=3",
      "--max-time=3600",
      "--max-jobs=1000",
      "--timeout=90"
    ]
    environment:
      - APP_ROLE=worker
      - RUN_MIGRATIONS=false
    volumes:
      - storage:/var/www/html/storage
    depends_on:
      - app
    deploy:
      replicas: 3
      restart_policy:
        condition: any
        delay: 5s
  
  # Scheduler — single instance
  scheduler:
    image: myapp:${APP_VERSION:-latest}
    command: ["/usr/local/bin/scheduler-loop.sh"]
    environment:
      - APP_ROLE=scheduler
      - RUN_MIGRATIONS=false
    depends_on:
      - app
    restart: unless-stopped
  
  # Horizon (queue:work alternativi — Redis-lə daha yaxşı monitoring)
  horizon:
    image: myapp:${APP_VERSION:-latest}
    command: ["php", "artisan", "horizon"]
    environment:
      - APP_ROLE=horizon
      - RUN_MIGRATIONS=false
    stop_signal: SIGTERM
    stop_grace_period: 60s
    depends_on:
      - app

volumes:
  storage:
```

## Queue Worker Konteyneri

### `queue:work` Flag-ları (Production)

```bash
php artisan queue:work \
    --queue=high,default,low \    # Prioritet sırası (soldan sağa)
    --sleep=3 \                    # Job yoxdursa 3s gözlə (polling)
    --tries=3 \                    # 3 cəhd (sonra failed_jobs-a düşür)
    --max-time=3600 \              # 1 saatdan sonra worker çıxsın (memory leak)
    --max-jobs=1000 \              # 1000 job-dan sonra çıxsın
    --timeout=90 \                 # Hər job max 90s (sonra kill)
    --memory=512                   # 512 MB-dan sonra çıxsın
```

**Niyə `--max-time` və `--max-jobs`?**

PHP long-running process-lərdə yavaş yavaş RAM sızdırır (opcache sabitdir, amma istifadəçi kodu, ORM, konnektorlar). Worker periyodik restart olmalıdır. Docker `restart: unless-stopped` avtomatik ayağa qaldırır.

### `queue:listen` vs `queue:work`

| Flag | Davranış |
|------|----------|
| `queue:listen` | Hər job üçün **yeni PHP process** fork edir — kod dəyişikliyi hiss olunur (dev) |
| `queue:work` | Bir PHP process job-ları ardıcıl icra edir — daha sürətli (prod) |

**Dev-də** `queue:listen`, **prod-da** `queue:work`.

### Graceful Shutdown

`queue:work` SIGTERM alanda:
1. Cari job-u bitirir
2. Yeni job götürmür
3. Çıxır

```yaml
worker:
  image: myapp
  command: ["php", "artisan", "queue:work", ...]
  stop_signal: SIGTERM
  stop_grace_period: 120s    # Uzun job-lar üçün
```

**Kubernetes-də:**
```yaml
spec:
  template:
    spec:
      terminationGracePeriodSeconds: 120
```

Əgər job > `stop_grace_period`-dan uzun çəkirsə, SIGKILL olur və **job itir** (retry olmur — `attempts` sayı artmır). Long-running job-lar üçün:
- Job-u kiçik hissələrə böl (`batch`, chain)
- Checkpoint (progress DB-yə yaz)
- `retry_after` artır (job default queue-da yenidən visible olmaq üçün)

### Worker Logging

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 120,          // Job lock > timeout olmalıdır
        'block_for' => null,
        'after_commit' => true,        // Laravel 10+ — DB transaction bitəndən sonra dispatch
    ],
],
```

Worker log-ları `stdout`-a:
```php
// app/Providers/AppServiceProvider.php
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;

Queue::before(fn(JobProcessing $e) => Log::info("Job started", [
    'job' => $e->job->resolveName(),
    'queue' => $e->job->getQueue(),
    'uuid' => $e->job->uuid(),
]));

Queue::after(fn(JobProcessed $e) => Log::info("Job completed", [
    'job' => $e->job->resolveName(),
    'uuid' => $e->job->uuid(),
]));
```

## Scheduler Konteyneri

Laravel Scheduler Linux cron-a bənzəyir — amma `cron` Docker-də yaxşı işləmir (stdout log-ları sistema gedir). Həll: **scheduler loop script**.

### `docker/scheduler-loop.sh`

```bash
#!/bin/sh
set -e

trap 'log "received signal, exiting"; exit 0' TERM INT

log() { echo "[scheduler] $(date '+%Y-%m-%d %H:%M:%S') $1"; }

log "Scheduler started"

while true; do
    # schedule:run 1 dəqiqədə bir dəfə işləməlidir
    # background-da işlət ki, hamısını bitirmək üçün gözləməyək
    php /var/www/html/artisan schedule:run --verbose --no-interaction &
    
    # 60 saniyə gözlə
    sleep 60
done
```

### Alternativ: `supervisord` + cron

```ini
[program:cron]
command=crond -f
autostart=true
autorestart=true
```

Crontab-da:
```
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/stdout 2>&1
```

**Loop yanaşması daha sadə və idiomatik Docker-dir.**

### Kubernetes-də — CronJob

Kubernetes-də native `CronJob` istifadə etmək daha yaxşıdır:

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: schedule-run
spec:
  schedule: "* * * * *"      # Hər dəqiqə
  concurrencyPolicy: Forbid  # Əvvəlki işləyirsə yenisi başlamasın
  successfulJobsHistoryLimit: 1
  failedJobsHistoryLimit: 3
  jobTemplate:
    spec:
      template:
        spec:
          restartPolicy: Never
          containers:
          - name: scheduler
            image: myapp:v1.2.3
            command: ["php", "artisan", "schedule:run"]
            envFrom:
            - secretRef:
                name: app-env
```

Alternativ: Laravel-ın `schedule:work` komandası (Laravel 9.34+) — loop iş görür:
```yaml
command: ["php", "artisan", "schedule:work"]
```

## Horizon Konteyneri

Horizon `queue:work`-u əvəz edir, Redis queue-lər üçün advanced dashboard və supervisor verir.

### Niyə Horizon?

- Real-time dashboard (/horizon URL)
- Queue metrics: throughput, wait time, failed jobs
- Tag-ged job-lar
- Auto-balancing strategies (simple, auto)
- Automatic retry config

### Dockerfile-a Horizon

Horizon adi `composer require laravel/horizon` ilə gəlir. Əlavə quraşdırma lazım deyil.

```yaml
horizon:
  image: myapp:v1
  command: ["php", "artisan", "horizon"]
  stop_signal: SIGTERM
  stop_grace_period: 60s
  restart: unless-stopped
  deploy:
    resources:
      limits:
        memory: 1G
```

### `config/horizon.php`

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'emails', 'reports'],
            'balance' => 'auto',          // simple | auto | false
            'autoScalingStrategy' => 'time',  // time | size
            'maxProcesses' => 10,
            'minProcesses' => 3,
            'maxTime' => 3600,
            'maxJobs' => 1000,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 90,
            'nice' => 0,
        ],
    ],
],
```

### Horizon Signal Handling

Horizon SIGTERM alanda bütün supervisor-ları (daxili worker-ları) graceful dayandırır. **stop_grace_period > timeout × tries** olmalıdır. Default Horizon 60-90 saniyə gözləyə bilər.

### Horizon-u K8s-də Scale Etmək

**TƏHLÜKƏLİ:** Horizon özü supervisor-du, **yalnız 1 replica olmalıdır**. Əgər 3 replica qaldırsan, 3 Horizon dashboard olacaq, metriklər qarışacaq.

Amma daxili worker-lar üçün Horizon-un `maxProcesses` var — o, tək pod-da 10 paralel worker ayırır. Əgər daha çox lazımdırsa:
- `maxProcesses`-i artır
- Və ya Horizon-dan imtina et, `queue:work` ilə Deployment (3+ replica) qur

## Worker Ölçüləndirmə — HPA

### Queue Length-ə görə KEDA

Redis queue uzunluğuna görə avtomatik scale:

```yaml
apiVersion: keda.sh/v1alpha1
kind: ScaledObject
metadata:
  name: worker-scaler
spec:
  scaleTargetRef:
    name: worker
  minReplicaCount: 1
  maxReplicaCount: 20
  pollingInterval: 15
  cooldownPeriod: 300
  triggers:
  - type: redis
    metadata:
      address: redis:6379
      listName: queues:default
      listLength: "50"        # 50-dən çox job varsa scale et
```

KEDA queue boş olanda 0-a qədər endirə bilər (scale-to-zero).

### CPU-based HPA (sadə)

```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: worker
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: worker
  minReplicas: 2
  maxReplicas: 15
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
```

Queue-based (KEDA) adətən daha yaxşıdır — CPU spike olmaya bilər, amma 10k job növbəyə düşə bilər.

## Supervisor Pattern (Docker İçində)

Kritiklərdən biri: **bir konteynerdə bir process-dən çox saxlamayın** (12-factor). Amma bəzi hallarda supervisor-la bir neçə worker bir konteynerdə:

```ini
# docker/supervisor/workers.conf
[program:worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=4                ; 4 paralel worker
redirect_stderr=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stopwaitsecs=3600

[program:worker-emails]
command=php /var/www/html/artisan queue:work --queue=emails --sleep=3 --tries=3
numprocs=2
...
```

**Nə vaxt?** Kiçik layihələr, tək host. **Production K8s-də?** Hər queue üçün ayrı Deployment və KEDA scaler.

## Tipik Səhvlər (Gotchas)

### 1. Kod dəyişiklikləri worker-a çatmır

**Səbəb:** `queue:work` bir dəfə boot olur, sonra kod mənbəyi cache-də qalır.

**Həll:** Deploy-dan sonra worker-ları restart et — `kubectl rollout restart deployment/worker` və ya `docker compose restart worker`. Horizon-da `php artisan horizon:terminate` — graceful restart.

### 2. Horizon çoxlu pod-da

**Problem:** 3 Horizon pod-u → 3 fərqli dashboard. Metriklər qarışıq.

**Həll:** Horizon deployment `replicas: 1`. Daxili scaling Horizon-un `maxProcesses`-i ilə idarə olunur.

### 3. Scheduler çoxlu instance-da

**Problem:** 3 scheduler pod-u → hər job 3 dəfə işləyir!

**Həll:** Scheduler Deployment `replicas: 1` və ya K8s `CronJob` istifadə et (natively tək dəfə).

### 4. Mid-job SIGKILL — job itir

**Problem:** `stop_grace_period: 10s`, amma job 60s çəkir. SIGKILL → job failed_jobs-a yazılmır, itir.

**Həll:** `stop_grace_period`-ı artır (`120s+`). Job-u idempotent et. Checkpoint istifadə et.

### 5. `queue:listen` production-da

**Problem:** Hər job yeni process fork edir — çox yavaşdır.

**Həll:** `queue:work` production-da. `listen` yalnız dev.

### 6. Redis connection drop

**Problem:** Worker uzun müddət idle qalanda Redis connection itir — `RedisException: Connection lost`.

**Həll:** Horizon bunu avtomatik idarə edir. `queue:work`-da `'read_write_timeout' => 60` `config/database.php` redis-də.

### 7. `--tries=1` və retry yox

**Problem:** Network xətaları üçün retry yoxdur, hər kiçik problem failed_jobs-a yazılır.

**Həll:** `--tries=3`, job-da `$backoff = [60, 300, 900]` (exponential backoff).

### 8. Storage volume paylaşılmır

**Problem:** Worker upload-un emal edir (thumbnail yaradır), amma `storage/app/public` yoxdur.

**Həll:** Web + worker eyni storage volume paylaşmalıdır. S3 istifadə edirsənsə problem yoxdur.

## Interview sualları

- **Q:** Niyə worker-i ayrı konteynerdə saxlayırsınız, FPM-lə eyni yerdə yox?
  - Fərqli lifecycle: worker RAM-aktır, restart olmalıdır periyodik. Fərqli ölçüləndirmə: queue uzunluğuna görə scale olunur (KEDA). Fərqli graceful shutdown vaxtı.

- **Q:** Scheduler-ı K8s-də necə işlədirsiz?
  - **Native CronJob** ən təmiz həlldir — hər dəqiqə Job açır, bir dəfə işləyir. `concurrencyPolicy: Forbid` paralel işləməsin. Alternativ: `schedule:work` komandası tək Deployment-də.

- **Q:** Horizon-u scale edirsinizmi?
  - **Yox** — Horizon deployment `replicas: 1`. Daxili worker sayı `maxProcesses`-lə idarə olunur. Çoxlu pod = çoxlu dashboard + metrik qarışıqlığı.

- **Q:** Long-running job SIGTERM alanda nə baş verir?
  - Laravel `queue:work` cari job-u bitirir, sonra çıxır. Əgər `stop_grace_period`-dan uzun çəkirsə SIGKILL — job itir. Həll: checkpoint, kiçik job-lara böl.

- **Q:** `--max-time` və `--max-jobs` niyə?
  - PHP long-running process-də RAM sızdırır. Periyodik restart təmiz başlanğıc verir. Docker avtomatik yenidən başladır.

- **Q:** Queue worker-ları necə ölçüləndirirsiniz?
  - **KEDA + Redis queue length** — 50-dən çox job varsa scale up, boş olanda scale to zero. CPU-based HPA faydasızdır — queue-da job var amma CPU idle ola bilər.


## Əlaqəli Mövzular

- [kubernetes-jobs-cronjobs.md](32-kubernetes-jobs-cronjobs.md) — K8s Job, CronJob
- [kubernetes-autoscaling.md](31-kubernetes-autoscaling.md) — KEDA ilə queue scaling
- [docker-env-secrets-laravel.md](46-docker-env-secrets-laravel.md) — Queue env konfigurasiyası
