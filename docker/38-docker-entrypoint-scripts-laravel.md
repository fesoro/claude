# Docker Entrypoint Scripts for Laravel

## Nədir? (What is it?)

`ENTRYPOINT` — konteyner başlayanda **hər dəfə** icra olunan script-dir. Laravel üçün bu script adətən bunları edir:

1. **Wait-for dependencies** — DB/Redis hazır olana qədər gözlə
2. **Runtime environment check** — `APP_KEY`, `DB_HOST` və s. təyin olunubmu
3. **Migration** — `php artisan migrate --force`
4. **Config/route/view cache** — runtime-da (build-time-da yox)
5. **Storage link** — `php artisan storage:link`
6. **`exec`** ilə əsas prosesi işə sal (PHP-FPM, worker, scheduler)

Bu fayl hazır, production-ready entrypoint script-lər verir və signal handling (SIGTERM) xüsusiyyətlərini izah edir.

## Tam Production Entrypoint

### `docker/entrypoint.sh`

```bash
#!/bin/sh
set -e

# Renglər (log oxunaqlı olsun deyə)
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo "${GREEN}[entrypoint]${NC} $1"; }
warn() { echo "${YELLOW}[entrypoint]${NC} $1"; }
err() { echo "${RED}[entrypoint]${NC} $1" >&2; }

# ============================================================
# 1. Environment validation
# ============================================================
: "${APP_KEY:?APP_KEY is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"

# ============================================================
# 2. Storage link (idempotent)
# ============================================================
if [ ! -L /var/www/html/public/storage ]; then
    log "Creating storage symlink..."
    php artisan storage:link || warn "storage:link failed (maybe already exists)"
fi

# ============================================================
# 3. Wait for database
# ============================================================
log "Waiting for database at $DB_HOST:${DB_PORT:-3306}..."
timeout=60
while ! nc -z "$DB_HOST" "${DB_PORT:-3306}" 2>/dev/null; do
    timeout=$((timeout - 1))
    if [ $timeout -le 0 ]; then
        err "Database not reachable after 60 seconds"
        exit 1
    fi
    sleep 1
done
log "Database is reachable"

# ============================================================
# 4. Wait for Redis (varsa)
# ============================================================
if [ -n "$REDIS_HOST" ]; then
    log "Waiting for Redis at $REDIS_HOST:${REDIS_PORT:-6379}..."
    timeout=30
    while ! nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}" 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            err "Redis not reachable after 30 seconds"
            exit 1
        fi
        sleep 1
    done
    log "Redis is reachable"
fi

# ============================================================
# 5. Cache-lər (build-time-da yox, runtime-da)
# ============================================================
# DİQQƏT: Yalnız bir pod (leader) bunları etməlidir, əks halda bütün pod-lar
# eyni vaxtda cache yazırsa racing olur. Bunun üçün migration job-a bax (aşağı).
if [ "$RUN_MIGRATIONS" = "true" ]; then
    log "Running migrations..."
    php artisan migrate --force --no-interaction
fi

log "Caching config..."
php artisan config:cache

log "Caching routes..."
php artisan route:cache

log "Caching views..."
php artisan view:cache

log "Caching events..."
php artisan event:cache

# ============================================================
# 6. Optional: cache warmup
# ============================================================
if [ "$WARMUP_CACHE" = "true" ]; then
    log "Warming up application cache..."
    php artisan app:cache-warmup || warn "cache-warmup failed (not fatal)"
fi

# ============================================================
# 7. Exec main process (PID 1-i mirası ver)
# ============================================================
log "Starting: $*"
exec "$@"
```

Dockerfile-da istifadə:
```dockerfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
```

### Niyə `exec "$@"` Vacibdir?

`exec` olmadan:
```sh
/usr/local/bin/entrypoint.sh     # PID 1 — bash
└── php-fpm                        # PID 2 — signal almır
```

`exec` ilə:
```sh
php-fpm                            # PID 1 — signal-ları alır
```

`exec` komanda-nı shell-i əvəz edir. Bu `docker stop` zamanı SIGTERM-in düzgün PHP-FPM-ə çatması üçün vacibdir.

## Signal Handling (SIGTERM / Graceful Shutdown)

### PID 1 Problemi

Linux-da PID 1 xüsusi davranışdır: signal-lar **default handler olmadığı üçün** ignore olunur. Əgər entrypoint bash script-dirsə və `exec` yoxdursa — `docker stop` signal-ı PHP-FPM-ə çatmır, 10 saniyə sonra SIGKILL olur, mid-request işlər itir.

**Həllər:**

#### Həll 1: `tini` (tövsiyə)

```dockerfile
RUN apk add --no-cache tini
ENTRYPOINT ["/sbin/tini", "--", "entrypoint.sh"]
```

`tini` mini init system-dir — PID 1 olaraq child-ların signal-larını idarə edir, zombie process-ləri təmizləyir.

#### Həll 2: Docker `--init`

```bash
docker run --init myapp
# və ya compose:
services:
  app:
    init: true
```

Docker-in öz `tini` bundle-i var. Amma image-in içinə əlavə etmək daha pristine.

#### Həll 3: Bash trap + exec

```sh
trap 'kill -TERM "$child" 2>/dev/null; wait "$child"' TERM INT
"$@" &
child=$!
wait "$child"
```

Çətin və səhv-qənaətdir — `tini` istifadə et.

### PHP-FPM-in SIGTERM Davranışı

| Signal | PHP-FPM davranışı |
|--------|---------------------|
| `SIGTERM` | **Graceful shutdown** — cari request-ləri bitirir, sonra dayanır |
| `SIGQUIT` | `SIGTERM` ilə eynidır (graceful) |
| `SIGINT` | `SIGTERM` kimi |
| `SIGUSR2` | **Reload** — config-i yenidən oxu, worker-ları yenilə |
| `SIGKILL` | Məcburi, request-lər kəsilir |

**Kubernetes:** `terminationGracePeriodSeconds: 30` — SIGTERM-dən sonra 30 saniyə gözlə (default), sonra SIGKILL. Laravel-də request-lər 60 saniyə çəkirsə, bunu artır.

```yaml
# K8s deployment
spec:
  template:
    spec:
      terminationGracePeriodSeconds: 60
      containers:
      - name: app
        lifecycle:
          preStop:
            exec:
              command: ["/bin/sh", "-c", "sleep 5"]    # Load Balancer-ə deregister vaxtı ver
```

## Migration Strategiyası

Migration-ı hər pod-un öz entrypoint-ində etmək **təhlükəlidir** — 3 pod ilə Deploy-da hər üçü eyni vaxt migration icra etməyə çalışacaq. Həllər:

### Strategiya 1: `INIT_CONTAINER` (Kubernetes)

```yaml
spec:
  template:
    spec:
      initContainers:
      - name: migrate
        image: myapp:latest
        command: ["php", "artisan", "migrate", "--force"]
        env:
        - name: DB_HOST
          value: mysql
      containers:
      - name: app
        # ... əsas app ...
```

Init container **bir dəfə** işləyir (pod başlamadan öncə), sonra əsas konteyner başlayır. Amma yenə də hər pod-da bir dəfə işləyir — `migrate` idempotent olduğu üçün problem deyil, amma **3 dəfə DB-yə qoşulma var**.

### Strategiya 2: Kubernetes Job (tövsiyə)

```yaml
apiVersion: batch/v1
kind: Job
metadata:
  name: migrate-$(VERSION)
spec:
  backoffLimit: 2
  template:
    spec:
      restartPolicy: OnFailure
      containers:
      - name: migrate
        image: myapp:v1.2.3
        command: ["php", "artisan", "migrate", "--force"]
        envFrom:
        - secretRef:
            name: app-env
```

Deploy-dan öncə Job işləyir — bir dəfə, mərkəzləşdirilmiş. CI/CD pipeline-da:
```yaml
# GitHub Actions
- name: Run migrations
  run: kubectl apply -f k8s/migrate-job.yaml && kubectl wait --for=condition=complete job/migrate-v1.2.3

- name: Rollout app
  run: kubectl set image deployment/app app=myapp:v1.2.3
```

### Strategiya 3: Laravel Migration Lock (sadə app-lər)

Laravel 10+ avtomatik migration lock-u var (DB-də `migrations` table-a lock alır). Bütün pod-lar `migrate` çağırsa belə yalnız biri icra edir. Amma hər pod başlangıcda 5-10 saniyə gözləyir.

Entrypoint-də:
```sh
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force --isolated
fi
```

Və yalnız bir pod-a `RUN_MIGRATIONS=true` ver (məsələn, leader election).

### Strategiya 4: Manual (`kubectl exec`)

Kiçik komandalar üçün:
```bash
kubectl exec -it deploy/app -- php artisan migrate --force
```

Sadədir amma avtomatlaşdırma yoxdur.

## Cache-lərin Runtime-da Yaradılması

Production-da `config:cache` build-time-da etsən, `.env` dəyişəndə (new DB password) yeni deploy lazım olur. Əgər runtime-da edirsənsə, hər pod başladıqda env-dən oxuyur.

### Paralel Pod-lar Problemi

3 pod eyni `config.php` cache-i yazmağa çalışır — şans azdır amma:
- Hər pod öz konteynerində öz `bootstrap/cache/`-inə yazır (əgər volume paylaşılmırsa problem yoxdur)
- Əgər `bootstrap/cache/` shared volume-dədirsə, race condition ola bilər

**Həll:** `bootstrap/cache/` asla shared volume olmasın — hər konteynerin öz write-only yeri olsun.

## Müxtəlif Rollar Üçün Entrypoint

Eyni image, fərqli `CMD`:

```yaml
# docker-compose.yml
services:
  app:                    # Web (PHP-FPM)
    image: myapp:v1
    command: ["php-fpm"]
  
  worker:                 # Queue worker
    image: myapp:v1
    environment:
      RUN_MIGRATIONS: "false"
    command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600"]
  
  scheduler:              # Cron
    image: myapp:v1
    environment:
      RUN_MIGRATIONS: "false"
    command: ["/usr/local/bin/scheduler-loop.sh"]
  
  horizon:                # Horizon
    image: myapp:v1
    command: ["php", "artisan", "horizon"]
```

`scheduler-loop.sh`:
```sh
#!/bin/sh
while true; do
    php /var/www/html/artisan schedule:run --verbose --no-interaction
    sleep 60
done
```

## Health Check Endpoint

Entrypoint bitdikdən sonra Nginx `/health` endpoint-ini Kubernetes liveness/readiness üçün istifadə edə bilər:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'db' => DB::connection()->getPdo() ? 'ok' : 'error',
        'redis' => Redis::ping() ? 'ok' : 'error',
    ]);
});

Route::get('/ready', function () {
    // DB və Redis-ə qoşulma yoxlanılır
    try {
        DB::connection()->getPdo();
        Redis::ping();
        return response()->json(['status' => 'ready']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'not ready', 'error' => $e->getMessage()], 503);
    }
});
```

K8s probe-lar:
```yaml
livenessProbe:
  httpGet:
    path: /health
    port: 80
  initialDelaySeconds: 30
  periodSeconds: 30

readinessProbe:
  httpGet:
    path: /ready
    port: 80
  initialDelaySeconds: 10
  periodSeconds: 10
```

## Tipik Səhvlər (Gotchas)

### 1. `exec` yox

**Problem:** `docker stop` 10 saniyə gözləyir, SIGKILL atır, in-flight request-lər itir.

**Həll:** Entrypoint son satırı `exec "$@"` olmalıdır. Entrypoint-i `tini` ilə sar.

### 2. `set -e` olmadan

**Problem:** Əmr uğursuz olur (məsələn, `migrate` xətası), entrypoint davam edir, broken state-də konteyner başlayır.

**Həll:** `set -e` əvvəldə.

### 3. `wait-for-it.sh` istifadə etmək

Sevimli kitabxanadır amma asılılıq əlavə edir. `nc -z` kifayətdir (Alpine-də `netcat-openbsd` paketi).

### 4. Migration hər pod-da

**Problem:** 3 pod başlayır, 3 dəfə migration (bəzən race condition). 

**Həll:** Yuxarıda — K8s Job, `--isolated` flag, və ya yalnız leader pod-da `RUN_MIGRATIONS=true`.

### 5. `.env` build image-ə düşüb

Deploy-dan sonra credential-lar köhnə .env-dən oxunur.

**Həll:** `.dockerignore`-a `.env` əlavə et. Runtime-da `env_file: [.env.production]` və ya K8s Secret.

### 6. `chown` entrypoint-də hər dəfə

**Problem:** Start time-ı uzatır (minlərlə fayl üçün 5-30 saniyə).

**Həll:** `chown` build-time-da, bir dəfə. Bind mount-da UID match et (42-ci fayl).

## Interview sualları

- **Q:** Niyə `exec` istifadə edirsiz entrypoint-in sonunda?
  - Shell process-ini PHP-FPM ilə əvəz etmək üçün — PID 1 olsun, SIGTERM birbaşa FPM-ə çatsın, graceful shutdown işləsin.

- **Q:** PID 1 problemi nədir?
  - Linux-da PID 1 default signal handler yoxdur — signal-lar ignore olunur. `tini` və ya `docker run --init` ilə həll olunur.

- **Q:** Migration-ı K8s-də necə icra edirsiz?
  - **Job** ilə — CI/CD pipeline-da deploy-dan öncə. Init container hər pod-da işləyir (dublikat). `--isolated` flag Laravel 10+ da lock verir amma Job pattern daha təmiz.

- **Q:** `config:cache` build-time-da yoxsa runtime-da?
  - Runtime-da (entrypoint-də) — çünki `.env` build-time-da olmur. Runtime-da hər pod özü cache yaradır.

- **Q:** `terminationGracePeriodSeconds` nə üçün artırırsız?
  - Uzun request-lər (upload, PDF render) SIGTERM-dən sonra bitirmək üçün vaxt lazımdır. Default 30s, adətən 60s kifayətdir.
