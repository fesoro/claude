# MySQL/Postgres/Redis Servislərini Docker-də (Local Dev üçün)

> **Səviyyə (Level):** ⭐⭐ Middle
> **Oxu müddəti:** ~20-25 dəqiqə
> **Kateqoriya:** Docker / Local Development

## Nədir? (What is it?)

Docker Compose ilə local development-də MySQL, Postgres, Redis və digər backend servislərini bir əmrlə qaldırmaq — hər developer üçün eyni versiya, eyni konfiq. Bu sənəd həm əsas DB image-lərini (Postgres, MySQL, Redis), həm də Laravel developer-lərin tez-tez ehtiyac duyduğu köməkçi servisləri (Mailpit, MinIO, Meilisearch, Soketi) əhatə edir.

**Əsas qayda — local YES, production NO:**

| Mühit | Tövsiyə |
|-------|---------|
| Local development | Docker-də DB (tez-sıfırlanan, təmiz, versiya-ayrılmış) |
| CI/CD test | Docker-də DB (ephemeral test DB, testcontainers) |
| Staging | Managed DB (prod-a oxşar olsun deyə) |
| **Production** | **Managed DB** (RDS, Cloud SQL, Aurora, Supabase, Neon) |

**Niyə prod-da Docker-də DB pis fikirdir?**

- **Backup** — avtomatik daily snapshot, point-in-time recovery (PITR) yox
- **Replication** — multi-AZ failover yox
- **IAM integrasiyası** — AWS IAM auth, secret rotation yox
- **Upgrade** — minor/major versiya upgrade əl ilə, downtime riski
- **Tuning** — DB admin tərəfindən expert tuning yoxdur
- **Disk failure** — EBS snapshot-lar, replication yoxdur — 1 disk ölümü = data loss
- **Compliance** — SOC2, HIPAA audit-də managed DB-lər daha rahat keçir

Local dev-də bunların heç biri vacib deyil — əksinə, tez restart, `down -v`, versiya dəyişdirmə rahat olmalıdır.

## Əsas Konseptlər

### 1. Postgres Service

```yaml
# docker-compose.yml
services:
  postgres:
    image: postgres:16-alpine
    container_name: laravel-postgres
    restart: unless-stopped
    ports:
      - "${DB_EXTERNAL_PORT:-5432}:5432"
    environment:
      POSTGRES_DB: "${DB_DATABASE:-laravel}"
      POSTGRES_USER: "${DB_USERNAME:-laravel}"
      POSTGRES_PASSWORD: "${DB_PASSWORD:-secret}"
      # Data qovluğunu açıq təyin etmək (bəzi upgrade hallarında faydalı)
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres-data:/var/lib/postgresql/data
      - ./docker/postgres/init:/docker-entrypoint-initdb.d:ro
      - ./docker/postgres/postgresql.conf:/etc/postgresql/postgresql.conf:ro
    command: postgres -c config_file=/etc/postgresql/postgresql.conf
    networks:
      - backend
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-laravel} -d ${DB_DATABASE:-laravel}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

volumes:
  postgres-data:
    driver: local
```

**Əsas nüanslar:**

- **Image variant:** `postgres:16-alpine` — kiçik (~80MB), debian variant ~150MB. Alpine Laravel üçün kifayətdir.
- **Tag strategy:** major versiyanı pin et (`postgres:16`), **latest istifadə etmə** — Postgres major upgrade avtomatik baş vermir, data format dəyişir.
- **Volume:** Named volume (`postgres-data`) — bind mount **DEYİL**. Niyə aşağıda.
- **Healthcheck:** `pg_isready` — server qoşulmaya hazır olduqda `exit 0` verir.

### 2. Init Scripts (`/docker-entrypoint-initdb.d/`)

Postgres (və MySQL) konteyneri **ilk dəfə** başlayanda (volume boşdursa) bu qovluqdakı `.sql`, `.sh`, `.sql.gz` fayllarını sıra ilə icra edir:

```
docker/postgres/init/
├── 01-create-databases.sql
├── 02-create-extensions.sql
└── 03-seed.sh
```

```sql
-- docker/postgres/init/01-create-databases.sql
CREATE DATABASE laravel_testing;
GRANT ALL PRIVILEGES ON DATABASE laravel_testing TO laravel;

-- Əlavə schema
CREATE SCHEMA IF NOT EXISTS analytics;
GRANT USAGE ON SCHEMA analytics TO laravel;
```

```sql
-- docker/postgres/init/02-create-extensions.sql
\c laravel
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";      -- trigram search
CREATE EXTENSION IF NOT EXISTS "vector";        -- pgvector (əgər image-də varsa)
```

```bash
#!/bin/bash
# docker/postgres/init/03-seed.sh
# DİQQƏT: Yalnız ilk boot-da işləyir. Volume silinəndə yenidən işləyir.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    INSERT INTO feature_flags (name, enabled) VALUES
        ('new_checkout', true),
        ('beta_search', false);
EOSQL
```

**Vacib:** Init script-lər **YALNIZ volume boş olanda** işləyir. Əgər volume artıq var, skript işləməz. Yenidən işlətmək üçün `docker compose down -v`.

### 3. Postgres Performance Tuning (Local)

```conf
# docker/postgres/postgresql.conf
# Local development üçün aggressiv defaultlar

shared_buffers = 256MB             # RAM-ın 25%-i
effective_cache_size = 1GB
work_mem = 16MB
maintenance_work_mem = 128MB

# WAL
wal_level = replica
max_wal_size = 1GB
min_wal_size = 80MB

# Log slow queries (development-də faydalı)
log_min_duration_statement = 100   # 100ms-dən uzun query-ləri log-la
log_statement = 'none'
log_destination = 'stderr'
logging_collector = off             # stdout-a yaz ki Docker log-da görünsün

# Connections
max_connections = 100
```

**Prod-da** bu fayl tamamilə fərqli olmalıdır — managed DB özü tune edir.

### 4. MySQL 8.4 Service

```yaml
services:
  mysql:
    image: mysql:8.4
    container_name: laravel-mysql
    restart: unless-stopped
    ports:
      - "${DB_EXTERNAL_PORT:-3306}:3306"
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:-rootsecret}"
      MYSQL_DATABASE: "${DB_DATABASE:-laravel}"
      MYSQL_USER: "${DB_USERNAME:-laravel}"
      MYSQL_PASSWORD: "${DB_PASSWORD:-secret}"
    volumes:
      - mysql-data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/custom.cnf:ro
      - ./docker/mysql/init:/docker-entrypoint-initdb.d:ro
    networks:
      - backend
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "-u", "root", "-p${DB_ROOT_PASSWORD:-rootsecret}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    command:
      - --default-authentication-plugin=caching_sha2_password
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci

volumes:
  mysql-data:
    driver: local
```

```ini
# docker/mysql/my.cnf
[mysqld]
# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# InnoDB
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2    # Local dev üçün sürət (prod-da 1)

# Connections
max_connections = 100
max_allowed_packet = 64M

# Slow query log (development)
slow_query_log = 1
long_query_time = 1
slow_query_log_file = /var/lib/mysql/slow.log

# Binlog (əgər replication test edirsənsə)
# log_bin = mysql-bin
# server_id = 1

[client]
default-character-set = utf8mb4
```

**MySQL 8.4 nüansları:**

- **`caching_sha2_password`** — default auth plugin. PHP 7.4+ dəstəkləyir. PHP 7.3 və aşağı olsa `mysql_native_password` lazım olur.
- **Version pin:** `mysql:8.4` (LTS), `mysql:8.0` köhnə LTS. `mysql:latest` **istifadə etmə**.
- **Image ölçüsü:** `mysql:8.4` ~600MB. Alternativ: `mariadb:11` (~130MB, MySQL drop-in).

### 5. Redis Service

```yaml
services:
  redis:
    image: redis:7-alpine
    container_name: laravel-redis
    restart: unless-stopped
    ports:
      - "${REDIS_EXTERNAL_PORT:-6379}:6379"
    volumes:
      - redis-data:/data
      - ./docker/redis/redis.conf:/usr/local/etc/redis/redis.conf:ro
    command:
      - redis-server
      - /usr/local/etc/redis/redis.conf
      - --requirepass
      - "${REDIS_PASSWORD:-}"
    networks:
      - backend
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD:-}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3

volumes:
  redis-data:
    driver: local
```

```conf
# docker/redis/redis.conf
# Persistence: AOF + RDB hybrid

# RDB snapshots
save 900 1          # 15 dəq-də 1 dəyişiklik
save 300 10         # 5 dəq-də 10 dəyişiklik
save 60 10000       # 1 dəq-də 10k dəyişiklik

# AOF (Append Only File) — daha etibarlı
appendonly yes
appendfsync everysec    # 1 saniyə max data loss

# Memory
maxmemory 512mb
maxmemory-policy allkeys-lru   # Cache üçün LRU evict

# Network
timeout 300
tcp-keepalive 60

# Logging
loglevel notice
logfile ""              # stdout-a yaz
```

**Persistence fərqi:**

| Rejim | Davranış |
|-------|----------|
| **RDB** | Periyodik snapshot (binary dump). Sürətli restart, amma son dəqiqələr itə bilər. |
| **AOF** | Hər yazma əmri log-a yazılır. Daha etibarlı, restart yavaş. |
| **AOF + RDB** | İkisi birdən — tövsiyə. |
| **No persistence** | `--save ""` və `appendonly no` — pure cache kimi. |

Laravel **session/queue** üçün AOF tövsiyə olunur (səs itkisi pis olar). Pure cache üçün (view cache) persistence lazım deyil.

### 6. Bonus: Laravel Dev üçün Digər Servislər

#### Mailpit (MailHog-un yenilənmiş varianti)

```yaml
services:
  mailpit:
    image: axllent/mailpit:latest
    container_name: laravel-mailpit
    restart: unless-stopped
    ports:
      - "1025:1025"      # SMTP
      - "8025:8025"      # Web UI
    environment:
      MP_MAX_MESSAGES: 5000
      MP_DATABASE: /data/mailpit.db
      MP_SMTP_AUTH_ACCEPT_ANY: 1
      MP_SMTP_AUTH_ALLOW_INSECURE: 1
    volumes:
      - mailpit-data:/data
    networks:
      - backend

volumes:
  mailpit-data:
```

Laravel `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.test"
```

Niyə Mailpit, MailHog deyil? MailHog 2020-dən bəri inactive. Mailpit aktiv saxlanılır, daha sürətli, SQLite storage, HTTPS UI.

#### MinIO (S3 Əvəzləyicisi)

```yaml
services:
  minio:
    image: minio/minio:latest
    container_name: laravel-minio
    restart: unless-stopped
    ports:
      - "9000:9000"      # S3 API
      - "9001:9001"      # Console UI
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin
    volumes:
      - minio-data:/data
    command: server /data --console-address ":9001"
    networks:
      - backend
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 5s
      retries: 3

  # Bucket-ı avtomatik yaratmaq üçün bir dəfəlik client
  minio-setup:
    image: minio/mc:latest
    depends_on:
      minio:
        condition: service_healthy
    entrypoint: >
      /bin/sh -c "
      mc alias set local http://minio:9000 minioadmin minioadmin &&
      mc mb local/laravel-uploads --ignore-existing &&
      mc anonymous set download local/laravel-uploads
      "
    networks:
      - backend

volumes:
  minio-data:
```

Laravel `.env`:
```env
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=laravel-uploads
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=http://localhost:9000/laravel-uploads
```

`config/filesystems.php`-də:
```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
],
```

#### Meilisearch / Typesense (Laravel Scout)

```yaml
services:
  meilisearch:
    image: getmeili/meilisearch:v1.8
    container_name: laravel-meilisearch
    restart: unless-stopped
    ports:
      - "7700:7700"
    environment:
      MEILI_MASTER_KEY: "${MEILI_MASTER_KEY:-masterKey}"
      MEILI_ENV: development
    volumes:
      - meili-data:/meili_data
    networks:
      - backend
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--spider", "http://localhost:7700/health"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  meili-data:
```

Laravel Scout:
```bash
composer require laravel/scout meilisearch/meilisearch-php
```

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey
```

#### Soketi (Pusher-compatible WebSocket)

```yaml
services:
  soketi:
    image: quay.io/soketi/soketi:1.6-16-alpine
    container_name: laravel-soketi
    restart: unless-stopped
    ports:
      - "6001:6001"      # App
      - "9601:9601"      # Metrics
    environment:
      SOKETI_DEBUG: 1
      SOKETI_METRICS_SERVER_PORT: 9601
      SOKETI_DEFAULT_APP_ID: app-id
      SOKETI_DEFAULT_APP_KEY: app-key
      SOKETI_DEFAULT_APP_SECRET: app-secret
    networks:
      - backend
```

Laravel `.env`:
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=app-id
PUSHER_APP_KEY=app-key
PUSHER_APP_SECRET=app-secret
PUSHER_HOST=soketi
PUSHER_PORT=6001
PUSHER_SCHEME=http
PUSHER_APP_CLUSTER=mt1
```

## Tam docker-compose.yml — Hamısı Bir Yerdə

```yaml
# docker-compose.yml — Full local dev stack
services:
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    container_name: laravel-app
    volumes:
      - .:/var/www/html
    environment:
      DB_CONNECTION: pgsql
      DB_HOST: postgres              # Service adı = hostname
      DB_PORT: 5432
      DB_DATABASE: laravel
      DB_USERNAME: laravel
      DB_PASSWORD: secret
      REDIS_HOST: redis
      REDIS_PASSWORD: ""
      MAIL_HOST: mailpit
      MAIL_PORT: 1025
      AWS_ENDPOINT: http://minio:9000
      AWS_BUCKET: laravel-uploads
      MEILISEARCH_HOST: http://meilisearch:7700
      PUSHER_HOST: soketi
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
      mailpit:
        condition: service_started
    networks: [backend]

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: laravel
      POSTGRES_USER: laravel
      POSTGRES_PASSWORD: secret
    volumes:
      - postgres-data:/var/lib/postgresql/data
      - ./docker/postgres/init:/docker-entrypoint-initdb.d:ro
    ports: ["5432:5432"]
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U laravel"]
      interval: 10s
      retries: 5
    networks: [backend]

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes: [redis-data:/data]
    ports: ["6379:6379"]
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      retries: 3
    networks: [backend]

  mailpit:
    image: axllent/mailpit:latest
    ports: ["1025:1025", "8025:8025"]
    volumes: [mailpit-data:/data]
    networks: [backend]

  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin
    ports: ["9000:9000", "9001:9001"]
    volumes: [minio-data:/data]
    networks: [backend]

  meilisearch:
    image: getmeili/meilisearch:v1.8
    environment:
      MEILI_MASTER_KEY: masterKey
    ports: ["7700:7700"]
    volumes: [meili-data:/meili_data]
    networks: [backend]
    profiles: [search]     # Yalnız --profile search ilə

  soketi:
    image: quay.io/soketi/soketi:1.6-16-alpine
    environment:
      SOKETI_DEFAULT_APP_ID: app-id
      SOKETI_DEFAULT_APP_KEY: app-key
      SOKETI_DEFAULT_APP_SECRET: app-secret
    ports: ["6001:6001"]
    networks: [backend]
    profiles: [websockets]

networks:
  backend:
    driver: bridge

volumes:
  postgres-data:
  redis-data:
  mailpit-data:
  minio-data:
  meili-data:
```

Başlatmaq:
```bash
# Minimal stack
docker compose up -d

# Search də lazımdır
docker compose --profile search up -d

# Hamısı
docker compose --profile search --profile websockets up -d
```

## Laravel Konteynerdən DB-yə Qoşulmaq

Konteynerdən hostname **service adıdır**, `localhost` **DEYİL**:

```env
# DOĞRU (app konteynerindən)
DB_HOST=postgres
REDIS_HOST=redis

# SƏHV
DB_HOST=localhost            # Bu, app konteynerinin özüdür
DB_HOST=127.0.0.1
```

**Host makinadan** (IDE, TablePlus, DBeaver) qoşulmaq üçün:
```env
# .env (local machine-dən)
DB_HOST=127.0.0.1
DB_PORT=5432        # compose-da port: "5432:5432" mapping var
```

## Data Persistence: Named Volume vs Bind Mount

### Named Volume (TÖVSİYƏ)

```yaml
volumes:
  - postgres-data:/var/lib/postgresql/data
```

- **Performans:** Docker-idarə olunan, native filesystem driver
- **Permissions:** Docker həll edir, `chown` lazım deyil
- **Cross-platform:** macOS/Windows-da sürətli (bind mount yavaş)
- **Yerləşmə:** `/var/lib/docker/volumes/<name>/_data`

### Bind Mount (Pis Fikir)

```yaml
volumes:
  - ./data/postgres:/var/lib/postgresql/data    # ETMƏ!
```

Problemləri:
- **macOS/Windows:** 10-100x yavaş (filesystem translation)
- **Permissions:** UID mismatch — Postgres konteynerdə `postgres:999`, host-da developer, izin xətaları
- **Git:** `./data` qovluğunu `.gitignore` etmək lazım, yaddan çıxarsa DB data commit olur
- **Backup:** Bind mount qovluğunu kopyalamaq — konteyner işləyərkən korrupsiya riski

### Reset Etmək

```bash
# Konteynerləri dayandır və volume-ları sil
docker compose down -v

# Sadəcə konkret volume
docker volume rm myproject_postgres-data

# Növbəti `up` init scripts-i yenidən işlədəcək
docker compose up -d
```

## Backup & Restore

### Postgres Backup

```bash
# Full dump
docker compose exec -T postgres pg_dump -U laravel laravel > backup.sql

# Custom format (sürətli restore)
docker compose exec -T postgres pg_dump -U laravel -Fc laravel > backup.dump

# Gzip ilə
docker compose exec -T postgres pg_dump -U laravel laravel | gzip > backup.sql.gz
```

### Postgres Restore

```bash
# SQL faildən
docker compose exec -T postgres psql -U laravel -d laravel < backup.sql

# Custom format
docker compose exec -T postgres pg_restore -U laravel -d laravel --clean < backup.dump
```

### MySQL Backup

```bash
# Dump
docker compose exec -T mysql mysqldump -u root -prootsecret laravel > backup.sql

# Gzip
docker compose exec -T mysql mysqldump -u root -prootsecret laravel | gzip > backup.sql.gz
```

### Redis Backup

```bash
# BGSAVE ilə snapshot
docker compose exec redis redis-cli BGSAVE

# dump.rdb faylını kopyala
docker compose cp redis:/data/dump.rdb ./backup/redis-dump.rdb
```

## Per-Developer Test DB (Profiles)

Feature branch-larında test DB-ni əsas DB-dən ayırmaq:

```yaml
services:
  postgres-test:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: laravel_testing
      POSTGRES_USER: laravel
      POSTGRES_PASSWORD: secret
    tmpfs:
      - /var/lib/postgresql/data      # RAM-da — test-lər bitəndən sonra silinir
    ports:
      - "5433:5432"
    profiles: [testing]
    networks: [backend]
```

```bash
# Test DB-ni qaldır
docker compose --profile testing up -d postgres-test

# Test-ləri işlət
DB_HOST=postgres-test php artisan test
```

`tmpfs` istifadəsi → DB RAM-dadır, **CI-da çox sürətli**. 100k test insertsaniyələrdir.

## Best Practices

1. **Image tag-lərini pin et** — `postgres:16-alpine`, `latest` heç vaxt.
2. **Named volume istifadə et** — bind mount DB data üçün problemlidir.
3. **Healthcheck əlavə et** — `depends_on: condition: service_healthy` bunu tələb edir.
4. **Init scripts-i `.sql` və ya `.sh` olaraq saxla** — versiyalayıb commit et.
5. **`.env`-dən credentials oxu** — hardcoded parol YOX.
6. **Prod-da managed DB istifadə et** — RDS, Cloud SQL, Aurora, Neon, Supabase.
7. **Ports-u yalnız lazım olduqda aç** — `127.0.0.1:5432:5432` localhost-a bind et, 0.0.0.0 yox.
8. **`down -v` ilə sıfırla** — init scripts-i yenidən işləsin.
9. **Profiles ilə optional servisləri ayır** — Meilisearch, Soketi həmişə lazım deyil.
10. **Backup strategiyasını lokal-da test et** — prod-a keçməzdən əvvəl `pg_dump`/`pg_restore` prosesini bil.

## Tələlər (Gotchas)

### 1. Volume qalıb, init scripts işləmir

**Problem:** `init.sql`-a yeni script əlavə etdim, `docker compose up` etdim, amma işləmədi.

**Səbəb:** Init scripts **YALNIZ volume boş olanda** işləyir. Mövcud volume varsa atlanır.

**Həll:**
```bash
docker compose down -v    # Volume-ları sil
docker compose up -d      # Yenidən init
```

### 2. Postgres major upgrade

**Problem:** `postgres:15` → `postgres:16` dəyişdim, `Cannot read pg_control` xətası.

**Səbəb:** Postgres major versiya upgrade data formatını dəyişir, avtomatik deyil.

**Həll:** Local dev-də ən sadə:
```bash
# Dump, upgrade, restore
docker compose exec -T postgres pg_dump -U laravel laravel > dump.sql
docker compose down -v
# docker-compose.yml-da image dəyiş
docker compose up -d postgres
docker compose exec -T postgres psql -U laravel laravel < dump.sql
```

### 3. Windows/macOS-da bind mount yavaş

**Problem:** Bind mount ilə DB data → query-lər 10x yavaş.

**Həll:** Named volume istifadə et. Bind mount yalnız `./app` kod üçün.

### 4. `localhost` konteynerdən işləmir

**Problem:** `DB_HOST=localhost` — Connection refused.

**Həll:** Service adını istifadə et: `DB_HOST=postgres`.

### 5. Redis persistence off

**Problem:** Restart-dan sonra cache və **session itir**, amma queue data-sı gözlənilən idi.

**Həll:** Laravel queue üçün `--appendonly yes`. Pure cache üçün bu vacib deyil.

### 6. Port conflict host-da

**Problem:** `bind: address already in use` — host-da artıq Postgres işləyir.

**Həll:**
```yaml
ports:
  - "5433:5432"    # Host 5433, container 5432
```
Və host-dan qoşulanda `-p 5433`.

### 7. MySQL 8 authentication

**Problem:** PHP 7.3 və köhnə MySQL client `caching_sha2_password` dəstəkləmir.

**Həll:**
```yaml
command:
  - --default-authentication-plugin=mysql_native_password
```
Və ya PHP-ni yenilə.

### 8. MinIO bucket-ları əl ilə yaradılmalıdır

**Problem:** Laravel `S3` adapter `The bucket does not exist` verir.

**Həll:** Yuxarıdakı `minio-setup` service-i ilə avtomatik bucket yaradılır.

## Müsahibə Sualları

### 1. Niyə production-da DB-ni Docker-də işlətmirik?

**Cavab:** Managed DB (RDS, Cloud SQL, Aurora, Neon) avtomatik backup, point-in-time recovery, multi-AZ failover, minor/major upgrade, IAM integration, security patching verir. Docker-də öz-özlük DB üçün bunların hamısını əl ilə qurmaq lazımdır — 1 disk ölümü data loss deməkdir. Local dev-də isə Docker DB idealdır — sürətli, sıfırlanan, versiya-ayrılmış.

### 2. Named volume və bind mount arasında fərq nədir DB data üçün?

**Cavab:** Named volume Docker tərəfindən idarə olunur, native filesystem-də saxlanılır — macOS/Windows-da bind mount-dan 10-100x sürətli. Permission problemləri yoxdur (konteyner UID match-i). Bind mount DB data üçün pis fikirdir — yavaşdır, permission xətaları verir, backup korrupsiyası ola bilər.

### 3. `docker-entrypoint-initdb.d` nə vaxt işləyir?

**Cavab:** Yalnız ilk boot-da — volume boş olanda. Postgres/MySQL konteyneri data qovluğunun boş olduğunu görürsə, bu qovluqdakı `.sql`, `.sh` fayllarını sıra ilə icra edir. Mövcud volume varsa atlanır. Yenidən işlətmək üçün `docker compose down -v` və `up`.

### 4. Redis persistence rejimlərini izah edin.

**Cavab:** **RDB** — periyodik binary snapshot, sürətli restart, son dəqiqələr itə bilər. **AOF** — hər yazma əmri log-a yazılır, etibarlı, yavaş restart. **AOF + RDB** — hybrid, tövsiyə. Laravel queue və session üçün AOF lazımdır; sırf cache üçün persistence lazım deyil (`--save ""`, `appendonly no`).

### 5. Konteynerdən DB-yə qoşulurkən `localhost` niyə işləmir?

**Cavab:** Konteyner öz network namespace-indədir, `localhost` konteynerin özüdür. Başqa konteynerə qoşulmaq üçün **service adı hostname kimi işlədilir** (Docker DNS). Yəni `DB_HOST=postgres` (compose service-inin adı), `DB_HOST=localhost` deyil. Host makinadan qoşulmaq üçün `127.0.0.1` və mapped port.

### 6. Mailpit-in MailHog-dan fərqi nədir?

**Cavab:** MailHog 2020-dən inactive (maintenance mode). Mailpit onun aktiv mənəvi varisi — Go-da yazılıb, SQLite storage (disk-resident, böyük mesaj həcmi), daha sürətli UI, JSON API, HTTPS dəstək, avtomatik chaos monkey. Laravel docker stacks-də default olaraq Mailpit seçilir.

### 7. Per-developer test DB necə qurulur Docker-də?

**Cavab:** `tmpfs` mount ilə ayrı Postgres konteyner — RAM-da data, `profiles: [testing]` ilə aktivləşdir. 100k insertsaniyələrdir. CI-da parallel test run üçün ideal. Test bitəndən sonra `docker compose down` → data silinir.

### 8. MinIO niyə S3 local əvəzedicisidir?

**Cavab:** MinIO S3-compatible API verir — eyni `aws-sdk` kodu, Laravel `S3Adapter`, URL signature-lər işləyir. `AWS_ENDPOINT` ilə MinIO-ya yönəltmək kifayətdir, kod dəyişmir. Console UI-da bucket, IAM policy, versioning test etmək olur. Prod-da isə real S3/R2/Spaces istifadə olunur.
