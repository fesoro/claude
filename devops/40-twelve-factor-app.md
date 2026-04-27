# 12-Factor App (Middle)

## Mündəricat
1. [12-Factor App nədir?](#12-factor-app-nədir)
2. [I-VI Faktorlar](#i-vi-faktorlar)
3. [VII-XII Faktorlar](#vii-xii-faktorlar)
4. [PHP Kontekstdə](#php-kontekstdə)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## 12-Factor App nədir?

```
Adam Wiggins (Heroku) tərəfindən 2012-ci ildə yayımlandı.
Cloud-native, SaaS aplikasiyaları üçün best practice metodologiyası.

Məqsəd:
  ✓ Deployment portability (hər cloud provider-da işlə)
  ✓ Modern CI/CD uyğunluğu
  ✓ Horizontal scale imkanı
  ✓ Developer experience standartlaşdırılması

12 faktor:
  I.   Codebase      → Bir codebase, çox deploy
  II.  Dependencies  → Açıq şəkildə elan et
  III. Config        → Mühitdə saxla
  IV.  Backing Services → Əlavə resurslar kimi
  V.   Build, Release, Run → Mərhələləri ayır
  VI.  Processes     → Stateless proses-lər
  VII. Port Binding  → Özü port bağlayır
  VIII.Concurrency   → Proses modeli ilə scale
  IX.  Disposability → Sürətli start/stop
  X.   Dev/Prod Parity → Mühitlər mümkün qədər eyni
  XI.  Logs          → Event stream kimi
  XII. Admin Processes → Birdəfəlik tapşırıqlar
```

---

## I-VI Faktorlar

```
I — Codebase:
  Bir git repo → çox deploy (staging, production, dev).
  Çox app = ayrı repo.
  Paylaşılan kod → library (Composer package).

II — Dependencies:
  Bütün asılılıqlar açıq elan edilir.
  composer.json — sistem-level paketlərə güvənmə!
  
  ❌ "Server-da imagemagick quraşdırılmalıdır" (gizli dependency)
  ✅ composer require intervention/image (açıq, versioned)
  
  vendor/ qovluğu → deploy artefaktının hissəsi
  ya da deploy zamanı composer install

III — Config:
  Mühitlər arası fərqli olan hər şey environment variable-dadır.
  
  ❌ config/database.php → hardcoded credentials
  ✅ .env faylı (local), environment variables (production)
  
  Test: Codebase indi public ola bilər? Heç bir secret yoxdur?
  
  Laravel .env:
    DB_HOST=localhost
    DB_PASSWORD=secret
    APP_KEY=base64:...

IV — Backing Services:
  DB, cache, queue, email — hamısı "attached resource" kimi.
  URL/connection string config-dadır.
  Local MySQL → RDS → swap without code change.

V — Build, Release, Run:
  Build:   Kod + dependencies → artifact (Docker image)
  Release: Artifact + Config → deployable release
  Run:     Release → production-da çalışır
  
  ❌ Production server-da "git pull && composer install"
  ✅ CI: build image → release: config inject → deploy: run

VI — Processes:
  Stateless! Shared-nothing.
  Heç bir in-memory data (request-lər arası qalmır).
  Session → Redis/DB-də.
  Cache → Redis-də.
  File upload → S3-də.
```

---

## VII-XII Faktorlar

```
VII — Port Binding:
  App öz HTTP server-ini başladır, porta bağlanır.
  Nginx/Apache-yə ehtiyac yoxdur (optional).
  
  PHP: PHP-FPM socket bağlayır → Nginx yalnız reverse proxy.
  Swoole: PHP özü HTTP server işlədir, porta bağlanır.
  
  docker run -p 8080:8080 my-php-app

VIII — Concurrency:
  Yük artanda proses artır (subprocess, worker-lər).
  Scale: daha çox proses (horizontal), böyük proses deyil (vertical-first).
  
  Web workers: PHP-FPM process-lər
  Queue workers: Supervisor worker-lər
  Scheduled jobs: Cron worker-lər

IX — Disposability:
  Sürətli start (saniyələr).
  Graceful shutdown (SIGTERM → cari request tamamla → çıx).
  Ani ölümə hazır: crash recovery mümkün.
  
  PHP-FPM: pm.max_requests → worker restart (disposable!)
  Worker: SIGTERM handler → cari job tamamla.

X — Dev/Prod Parity:
  Mühitlər mümkün qədər eyni olmalıdır.
  
  Gap-lər:
  - Time gap: Feature→Production arası uzun vaxt
  - Personnel gap: Dev yazdı, Ops deploy etdi
  - Tools gap: Dev SQLite, Prod PostgreSQL
  
  ✅ Docker Compose: Tam production stack local-da
  ✅ CI/CD: Continuous deployment (time gap → 0)

XI — Logs:
  App log faylına yazmır!
  stdout-a yazar → infrastructure idarə edir.
  
  ❌ file_put_contents('/var/log/app.log', $message);
  ✅ error_log($message); // stdout/stderr
      ya da Logger → stdout handler
  
  Faydası: K8s, Docker log aggregation avtomatik işləyir.

XII — Admin Processes:
  DB migration, console command → eyni release-də, eyni codebase.
  Birdəfəlik tapşırıqlar REPL/script ilə.
  
  ✅ php artisan migrate (bir dəfə, deploy zamanı)
  ✅ php artisan db:seed (test data)
  ❌ SSH server-a → manual SQL çalışdır
```

---

## PHP Kontekstdə

```
12-Factor PHP cheat sheet:

Faktor         PHP implementasiyası
─────────────────────────────────────────────────────────
I Codebase     Git + composer.json
II Dependencies composer.json + composer.lock → vendor/
III Config     .env + $_ENV / getenv()
IV Backing Svc DB_URL, REDIS_URL environment variable
V Build/Release Docker multi-stage build
VI Processes   PHP-FPM shared-nothing (✅ native!)
VII Port       PHP-FPM unix socket ya da TCP port
VIII Concurrency pm.max_children, queue workers
IX Disposability pm.max_requests, SIGTERM handler
X Dev/Prod     Docker Compose, same PHP version
XI Logs        monolog → StreamHandler(stdout)
XII Admin      php artisan, custom CLI commands
```

---

## PHP İmplementasiyası

```php
<?php
// Factor III — Config (environment-dan)
class DatabaseConfig
{
    public static function fromEnvironment(): self
    {
        return new self(
            host:     getenv('DB_HOST') ?: 'localhost',
            port:     (int)(getenv('DB_PORT') ?: 5432),
            database: getenv('DB_NAME') ?: throw new \RuntimeException('DB_NAME required'),
            username: getenv('DB_USER') ?: throw new \RuntimeException('DB_USER required'),
            password: getenv('DB_PASS') ?: throw new \RuntimeException('DB_PASS required'),
        );
    }
}

// Factor XI — Logs to stdout
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

$logger = new Logger('app');
$handler = new StreamHandler('php://stdout', Logger::DEBUG);
$handler->setFormatter(new JsonFormatter());
$logger->pushHandler($handler);

// Log structured JSON to stdout
// K8s / Docker bu logu avtomatik toplayan infrastructure-a göndərir
$logger->info('Order created', ['order_id' => 42, 'customer' => 'ali@example.com']);
// {"message":"Order created","context":{"order_id":42},"datetime":"..."}
```

```dockerfile
# Factor V — Build, Release, Run (Docker multi-stage)
# BUILD stage
FROM php:8.3-fpm AS build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader
COPY . .
RUN php artisan config:cache
RUN php artisan route:cache

# RELEASE stage — config inject (runtime-da, burada deyil)
FROM php:8.3-fpm AS release
WORKDIR /app
COPY --from=build /app-laravel .

# Config environment variable-lardan gəlir (docker run -e ya da K8s secret)
# Image-ə hardcode deyil!

# RUN stage — deploy zamanı
CMD ["php-fpm"]
```

```yaml
# Factor X — Dev/Prod Parity (docker-compose.yml)
version: '3.8'
services:
  app:
    build: .
    environment:
      - APP_ENV=local
      - DB_HOST=postgres
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:16-alpine  # Production ilə eyni version!
    environment:
      POSTGRES_DB: myapp
      POSTGRES_PASSWORD: secret

  redis:
    image: redis:7-alpine  # Production ilə eyni version!

  worker:
    build: .
    command: php artisan queue:work
    depends_on:
      - redis
```

---

## İntervyu Sualları

- 12-Factor app-in məqsədi nədir? Niyə cloud-native-lə əlaqəlidir?
- Config-i environment variable-da saxlamağın üstünlüyü nədir?
- "Stateless processes" (Factor VI) PHP üçün natural niyədir?
- Logs stdout-a yazılmalıdır — bu production-da necə idarə edilir?
- Dev/Prod parity (Factor X) niyə vacibdir? Hansı gap-lər var?
- Factor IX (Disposability) PHP worker-larda necə tətbiq edilir?
- "Backing services" bağımsız dəyişdirmək (MySQL → PostgreSQL) niyə mümkün olmalıdır?
