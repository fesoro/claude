# Docker Environment Variables & Secrets for Laravel

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Laravel konteynerdə env variables ilə konfiqurasiya olunur — `.env` file, `environment:` compose, K8s `Secret`. Amma Docker-də 3 tipik problem:

1. **`.env` image-ə düşür** — sensitive data public registry-də
2. **`config:cache` build-time-da işlədildi** — köhnə env-lə cache-lənib, runtime-da dəyişiklik görünmür
3. **`APP_KEY` dəyişir** — session/cookie-lər məhv olur, hamı logout

Bu fayl env/secret management-in düzgün yollarını göstərir — dev-dən prod-a, Docker Compose-dən Kubernetes-ə.

## Laravel Config Ladder

Laravel config bu ardıcıllıqla oxunur:

```
1. config/*.php fayllarındakı default-lar
2. .env faylı (runtime-da)
3. php artisan config:cache → bootstrap/cache/config.php (cache aktivdirsə)
4. Environment variables (runtime-da yalnız config:cache yoxdursa)
```

**Mühüm:** `config:cache` edildikdə Laravel **yalnız cache fayldan oxuyur** — `.env`-i ignore edir! Bu ən böyük gotcha-dır.

## Problem: `config:cache` build-time-da

### Nə baş verir?

```dockerfile
# YANLIŞ: build-time-da cache
COPY . /var/www/html
RUN php artisan config:cache
```

Build zamanı `.env` ya yoxdur, ya da stub-dur. Cache boş credential-larla yaranır:
```php
// bootstrap/cache/config.php (build-də)
return [
    'database' => [
        'connections' => [
            'mysql' => [
                'host' => '',          // .env boşdu!
                'password' => '',
            ],
        ],
    ],
];
```

Runtime-da konteyner başlayır, `DB_HOST=mysql` env variable verilir — **amma Laravel cache-dən oxuyur, env-i ignore edir**. Connection fail.

### Həll: Runtime-da cache

Entrypoint-də (38-ci fayl):
```sh
# Əvvəlcə build-də yaradılmış cache-i sil
rm -f bootstrap/cache/config.php

# İndi .env / env variables-dən yenidən cache yarat
php artisan config:cache
```

Və ya build-də heç cache etmə, runtime-da hər zaman cache et:
```dockerfile
# Build-də
RUN php artisan view:cache route:cache event:cache
# config:cache ETMƏ
```

```sh
# Entrypoint-də
php artisan config:cache
```

## Problem: `APP_KEY` Dəyişir

`APP_KEY` Laravel-in **session, cookie, encrypted data şifrə açarıdır**. Dəyişsə:
- Bütün session-lar məhv olur (hər istifadəçi logout)
- Encrypted DB field-lər oxunmur (`Crypt::decrypt()` fails)
- "Password reset" link-ləri işləmir
- Remember-me cookie-ləri yanır

### Həll: `APP_KEY`-i Secret kimi saxla

**Dev:**
```
# .env (git-ignore)
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

**Production:**
```yaml
# Kubernetes Secret
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secrets
stringData:
  APP_KEY: "base64:xxxxxxxxxxxxxxxxx="
  DB_PASSWORD: "real-prod-password"
```

```yaml
# Deployment
envFrom:
- secretRef:
    name: laravel-secrets
```

**Docker Swarm:**
```bash
echo "base64:xxxxx=" | docker secret create app_key -
```

**BU KEY BİR DƏFƏ YARADILIR, ƏBƏDİ SAXLANILIR.** Rotation etsən, bütün user-lər logout olacaq.

### Fallback: Key Rotation Strategy

Laravel 11+ `APP_PREVIOUS_KEYS`:
```
APP_KEY=base64:new-key
APP_PREVIOUS_KEYS=base64:old-key
```

Laravel əvvəlcə yeni key-lə decrypt-a çalışır, fail olsa old key-i sınayır. Migration bitəndən sonra old key-i sil.

## Dev Environment — `.env`

### Qaydalar

1. **`.env` heç vaxt commit-ə düşməsin** (`.gitignore`)
2. **`.env.example`** commit et — bütün key-lərin boş siyahısı
3. Docker-də `.env` tipik olaraq host-dan bind mount olunur və ya Compose-də göstərilir

### Compose-də `env_file`

```yaml
services:
  app:
    env_file:
      - .env                 # Əsas
      - .env.docker          # Override (DB_HOST=mysql)
    # environment: direktiv env_file-i override edir
    environment:
      - XDEBUG_MODE=debug    # Yalnız dev
```

### Laravel `.env.docker` Override

```
# .env.docker — Docker-specific
DB_HOST=mysql
DB_PORT=3306
REDIS_HOST=redis
REDIS_PORT=6379
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Host-dan startup etsən `DB_HOST=127.0.0.1` yaxşıdır. Docker-də service name (`mysql`) olmalıdır. `.env.docker` bu fərqi idarə edir.

### `.env.example`

```
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=1025
```

Yeni developer:
```bash
cp .env.example .env
php artisan key:generate
```

## Production — Build-Time vs Runtime Env

### Build-Time (ARG) — Public Info Only

`ARG` variables image history-də görünür — **sensitive data ÜÇÜN YOX**:
```dockerfile
ARG APP_VERSION=dev
ARG BUILD_DATE

LABEL version="${APP_VERSION}" build-date="${BUILD_DATE}"
```

### Runtime (ENV) — Sensitive Data

Runtime-da env variable mount olunur — image-də qalmır:
```yaml
services:
  app:
    image: myapp:v1
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_HOST=db.internal
    env_file:
      - /etc/myapp/production.env
```

`/etc/myapp/production.env` serverdə qalır, image-də yoxdur.

## Docker Secrets (Swarm)

Swarm mode-da native secret management:

```bash
# Secret yarat
echo "real-prod-password" | docker secret create db_password -
echo "base64:xxxxx" | docker secret create app_key -
```

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:v1
    secrets:
      - source: db_password
        target: /run/secrets/db_password
      - source: app_key
        target: /run/secrets/app_key

secrets:
  db_password:
    external: true
  app_key:
    external: true
```

Laravel-də oxu:
```php
// config/database.php
'password' => file_exists('/run/secrets/db_password')
    ? trim(file_get_contents('/run/secrets/db_password'))
    : env('DB_PASSWORD'),
```

Yoxsa `.env`-də xüsusi hack:
```
DB_PASSWORD="file:/run/secrets/db_password"
```

Və ya entrypoint-də secret-ləri env variable-a çevir:
```sh
# entrypoint.sh
if [ -f /run/secrets/db_password ]; then
    export DB_PASSWORD=$(cat /run/secrets/db_password)
fi
if [ -f /run/secrets/app_key ]; then
    export APP_KEY=$(cat /run/secrets/app_key)
fi
exec "$@"
```

## Kubernetes Secrets

### Base Secret (Opaque)

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: laravel-env
type: Opaque
stringData:
  APP_KEY: "base64:xxxxxxxxxxxxxxxxx="
  DB_PASSWORD: "secret-password"
  STRIPE_SECRET: "sk_live_xxx"
```

```bash
kubectl apply -f secret.yaml
# Və ya komandadan:
kubectl create secret generic laravel-env \
    --from-literal=APP_KEY='base64:xxxxx=' \
    --from-literal=DB_PASSWORD='secret' \
    --from-literal=STRIPE_SECRET='sk_live_xxx'
```

### Deployment-də istifadə

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  template:
    spec:
      containers:
      - name: app
        image: myapp:v1
        envFrom:
        - secretRef:
            name: laravel-env      # Bütün key-lər env variable olur
        - configMapRef:
            name: laravel-config   # Public config (APP_ENV, APP_URL)
        env:                        # Override-lər
        - name: HOSTNAME
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
```

### External Secrets (Vault, AWS Secrets Manager)

[External Secrets Operator](https://external-secrets.io) — secret-ləri Vault/AWS/GCP-dən K8s Secret-ə sinxronlaşdırır:

```yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: laravel-env
spec:
  refreshInterval: 15m
  secretStoreRef:
    name: vault-backend
    kind: ClusterSecretStore
  target:
    name: laravel-env
    creationPolicy: Owner
  data:
  - secretKey: APP_KEY
    remoteRef:
      key: secret/data/laravel
      property: app_key
  - secretKey: DB_PASSWORD
    remoteRef:
      key: secret/data/laravel
      property: db_password
```

Vault-da secret rotate olunsa, 15 dəqiqədən sonra K8s Secret yenilənir, pod-lar restart olunur (əgər `reloader` annotation varsa).

## Laravel Config for Cached Environments

### `APP_KEY_CACHE` — Config Cache Bust

Bəzi tətbiqlərdə `config.php` hash-i env-lə yoxlanır:
```php
// bootstrap/cache/config.php-də version check
if (hash('sha256', env('APP_KEY')) !== $cachedHash) {
    throw new \Exception('Config cache stale');
}
```

Amma adi Laravel bu yoxlamanı etmir — entrypoint-də `config:clear && config:cache` lazımdır hər dəfə.

### Reloader (K8s)

Secret dəyişəndə pod-ları avtomatik restart et:
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  annotations:
    reloader.stakater.com/auto: "true"    # Stakater Reloader
```

Reloader Secret-ə bağlı deployment-ləri izləyir, dəyişəndə rollout restart edir.

## CI/CD — Env Variables Inject

### GitHub Actions

```yaml
- name: Deploy
  env:
    DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
    APP_KEY: ${{ secrets.APP_KEY }}
  run: |
    kubectl create secret generic laravel-env \
      --from-literal=DB_PASSWORD="$DB_PASSWORD" \
      --from-literal=APP_KEY="$APP_KEY" \
      --dry-run=client -o yaml | kubectl apply -f -
    
    kubectl rollout restart deployment/laravel-app
```

### GitLab CI

```yaml
deploy:
  script:
    - echo "APP_KEY=$APP_KEY" > .env.production
    - echo "DB_PASSWORD=$DB_PASSWORD" >> .env.production
    - docker compose -f docker-compose.prod.yml up -d
  # $APP_KEY, $DB_PASSWORD → GitLab CI/CD variables-də
```

## Env Variables Validation

Entrypoint-də məcburi env-ləri yoxla:
```sh
# entrypoint.sh
: "${APP_KEY:?APP_KEY is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

# APP_KEY format yoxla
if ! echo "$APP_KEY" | grep -q '^base64:'; then
    echo "APP_KEY must start with 'base64:'"
    exit 1
fi
```

Laravel-in özü 11-də env validation:
```php
// bootstrap/app.php
use Illuminate\Support\Facades\Config;

Config::validate([
    'app.key' => ['required', 'starts_with:base64:'],
    'database.connections.mysql.host' => ['required'],
]);
```

## Common Env Variables

### Laravel Core
```
APP_NAME="My App"
APP_ENV=production
APP_KEY=base64:xxxxx=
APP_DEBUG=false
APP_URL=https://app.example.com
APP_TIMEZONE=UTC
APP_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=stderr         # Konteynerdə stderr-ə yaz
LOG_LEVEL=warning        # prod: warning, dev: debug
```

### Database
```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=myapp_user
DB_PASSWORD=xxxxx
```

### Redis
```
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CLIENT=phpredis        # predis | phpredis (phpredis daha sürətli)
```

### Queue / Session / Cache
```
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Container-Specific
```
CONTAINER_ROLE=app          # app | worker | scheduler | horizon
RUN_MIGRATIONS=false        # Yalnız bir pod-da true
WARMUP_CACHE=true
```

## Tipik Səhvlər (Gotchas)

### 1. `.env` image-də

`COPY . .` `.env`-i də kopyalayır — credentials registry-də.

**Həll:** `.dockerignore`-da `.env`:
```
.env
.env.*
!.env.example
```

### 2. `config:cache` build-time-da, .env runtime-da

Cache köhnə env-lə yaranıb, runtime dəyişiklik görünmür.

**Həll:** Runtime-da (entrypoint-də) cache yarat.

### 3. `env()` Controller-də

```php
// WRONG
public function index() {
    $key = env('STRIPE_KEY');      // config:cache ilə null qaytaracaq!
}

// RIGHT
public function index() {
    $key = config('services.stripe.key');
}
```

`env()` yalnız `config/*.php` fayllarında işləyir. Controller/model-də `config()` istifadə et.

### 4. Multiline env variable

```
PRIVATE_KEY="-----BEGIN RSA KEY-----
line1
line2
-----END RSA KEY-----"
```

Shell export-da multiline problemdir.

**Həll:** Base64 encode, yaxud Docker secret file (yuxarıda).

### 5. `null` vs empty string

```
DB_PASSWORD=
# vs
DB_PASSWORD=null
```

Laravel `env('DB_PASSWORD', 'default')` — boş stringdirsə defaulta keçmir, boş qalır. `null` isə string "null"-dur (!).

**Həll:** Boş saxla və ya real null üçün xüsusi:
```php
'password' => env('DB_PASSWORD') === 'null' ? null : env('DB_PASSWORD'),
```

### 6. APP_KEY olmadan

Laravel fail:
```
RuntimeException: No application encryption key has been specified.
```

**Həll:** Entrypoint-də `: "${APP_KEY:?required}"` validation.

### 7. Env variable escape

```
APP_NAME="My "Quoted" App"    # Shell syntax error
```

**Həll:** Tək quote, ya da escape:
```
APP_NAME='My "Quoted" App'
```

### 8. Public key image-də, private runtime-da qatır

```dockerfile
COPY keys/public.pem /app/keys/
# Private key image-də OLMASIN
```

Private key Docker secret-dən və ya K8s Secret-dən mount.

## Interview sualları

- **Q:** `APP_KEY` niyə dəyişməz olmalıdır?
  - Session, cookie, encrypted DB field-lər APP_KEY ilə şifrələnir. Dəyişsə, bütün session-lar məhv, encrypted data oxunmur, hamı logout olur.

- **Q:** `config:cache` build-time-da niyə problemdir?
  - Build vaxtı `.env` yoxdur, cache boş credential-larla yaranır. Runtime-da env variables Laravel tərəfindən ignore olunur — cache prioritetdir. Həll: entrypoint-də runtime cache.

- **Q:** Controller-də `env()` istifadə olunmalıdırmı?
  - **Heç vaxt!** `env()` yalnız `config/*.php`-də işləyir. `config:cache` olduqda `env()` null qaytarır. Həmişə `config('services.key')`.

- **Q:** Docker secret-ləri Laravel-də necə oxuyursunuz?
  - Secret `/run/secrets/name` fayl kimi mount olunur. Config-də `file_get_contents('/run/secrets/...')` və ya entrypoint-də env variable-a çevir.

- **Q:** Kubernetes Secret-də sensitive data varmı?
  - Base64 encoded-dir, amma **encrypted deyil**. RBAC ilə kim oxuyabildiyini məhdudlaşdır. Production-da External Secrets + Vault/AWS Secrets Manager.

- **Q:** `.env.example` niyə vacibdir?
  - Yeni developer `cp .env.example .env` edib başlayır. Bütün tələb olunan key-ləri göstərir. `.env` git-ignore-da, amma `.env.example` commit-ed-dir.

- **Q:** Secret rotation necə edilir?
  - `APP_PREVIOUS_KEYS` (Laravel 11+) köhnə key-i fallback kimi saxlayır. DB encrypted field-ləri re-encrypt et. Vault-da rotate → ExternalSecret refresh → pod restart (Reloader).


## Əlaqəli Mövzular

- [kubernetes-configmaps-secrets.md](22-kubernetes-configmaps-secrets.md) — K8s ConfigMap/Secret
- [dev-vs-prod-docker-setup.md](44-dev-vs-prod-docker-setup.md) — Dev vs prod mühiti
- [docker-security.md](10-docker-security.md) — Secret security
