# CI/CD və Deployment (Middle)

## CI/CD Nədir?

**CI/CD** — müasir software delivery-nin əsasını təşkil edən üç əlaqəli praktikanın abbreviaturasıdır.

### Continuous Integration (CI)
Developers-in kod dəyişikliklərini tez-tez (gündə bir neçə dəfə) shared branch-ə merge etməsi praktikasıdır. Hər merge zamanı avtomatik build və test prosesi işə düşür. Məqsəd: integration bug-larını erkən tapmaq.

**Əsas prinsiplər:**
- Hər commit avtomatik test trigger edir
- Build artifact-lar saxlanılır
- Uğursuz build-lər dərhal developer-ə bildiriş göndərir
- Test coverage minimum threshold-u keçməlidir

### Continuous Delivery (CD - Delivery)
Hər uğurlu CI build-dən sonra kod production-a deploy edilməyə **hazır** vəziyyətdə saxlanılır. Ancaq actual deploy manualdır — bir düymə basılışıyla.

### Continuous Deployment (CD - Deployment)
Hər uğurlu pipeline addımından sonra kod **avtomatik olaraq** production-a deploy edilir. Heç bir manual addım yoxdur.

```
CI/CD Pipeline Spektri:
──────────────────────────────────────────────────────────
Code Commit → Build → Test → [Manual Gate?] → Deploy
                                    │
                          YES ──────┘──────── NO
                           │                  │
                    Continuous           Continuous
                    Delivery             Deployment
──────────────────────────────────────────────────────────
```

---

## CI/CD Pipeline Addımları

Standart Laravel CI/CD pipeline aşağıdakı mərhələlərdən keçir:

```
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐
│  Code   │───▶│  Lint   │───▶│  Test   │───▶│  Build  │───▶│ Deploy  │
│ Commit  │    │ & SAST  │    │ Suite   │    │ Artifact│    │         │
└─────────┘    └─────────┘    └─────────┘    └─────────┘    └─────────┘
                    │               │               │               │
               - PHP CS Fixer  - PHPUnit       - Docker        - Staging
               - PHPStan       - Pest          - Composer      - Production
               - Psalm         - Browser       - npm build     - Smoke test
               - OWASP ZAP     - Integration   - .env inject
```

### 1. Lint Stage
- **PHP CS Fixer** — PHP code style düzəltmə
- **PHPStan / Psalm** — static analysis, type checking
- **ESLint** — JavaScript lint
- **Rector** — avtomatik code upgrade

### 2. Test Stage
- **Unit Tests** — izolə edilmiş sinif testləri
- **Feature Tests** — HTTP request/response testləri
- **Integration Tests** — real database ilə testlər
- **Browser Tests** — Laravel Dusk ilə E2E testlər

### 3. Build Stage
- Composer dependencies install
- npm/yarn build (assets)
- Docker image build
- Artifact versioning

### 4. Deploy Stage
- Server-ə artifact deploy
- Database migration
- Cache clear & rebuild
- Service restart
- Smoke tests

---

## GitHub Actions ilə Laravel CI Pipeline

`.github/workflows/laravel.yml` — tam nümunə:

*`.github/workflows/laravel.yml` — tam nümunə üçün kod nümunəsi:*
```yaml
name: Laravel CI/CD Pipeline

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

env:
  PHP_VERSION: '8.3'
  NODE_VERSION: '20'

jobs:
  # ─────────────────────────────────────────────
  # JOB 1: Code Quality & Static Analysis
  # ─────────────────────────────────────────────
  lint:
    name: Lint & Static Analysis
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          extensions: mbstring, xml, ctype, json, bcmath, pdo, pdo_mysql
          coverage: none
          tools: cs2pr

      - name: Cache Composer packages
        uses: actions/cache@v3
        with:
          path: vendor
          key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-php-

      - name: Install Composer dependencies
        run: composer install --no-progress --prefer-dist --optimize-autoloader

      - name: Run PHP CS Fixer (dry-run)
        run: vendor/bin/php-cs-fixer fix --dry-run --diff --format=checkstyle | cs2pr

      - name: Run PHPStan
        run: vendor/bin/phpstan analyse --error-format=github

      - name: Run Rector (dry-run)
        run: vendor/bin/rector process --dry-run

  # ─────────────────────────────────────────────
  # JOB 2: Test Suite
  # ─────────────────────────────────────────────
  test:
    name: Test Suite (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest
    needs: lint

    strategy:
      matrix:
        php: ['8.2', '8.3']

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: laravel_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd="redis-cli ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, xml, ctype, json, bcmath, pdo, pdo_mysql, redis
          coverage: xdebug

      - name: Copy .env.testing
        run: cp .env.testing.example .env.testing

      - name: Install Composer dependencies
        run: composer install --no-progress --prefer-dist

      - name: Generate application key
        run: php artisan key:generate --env=testing

      - name: Run database migrations
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: laravel_test
          DB_USERNAME: root
          DB_PASSWORD: password
        run: php artisan migrate --env=testing --force

      - name: Run PHPUnit with coverage
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: laravel_test
          DB_USERNAME: root
          DB_PASSWORD: password
          REDIS_HOST: 127.0.0.1
          REDIS_PORT: 6379
        run: |
          vendor/bin/phpunit \
            --coverage-clover=coverage.xml \
            --log-junit=test-results.xml \
            --testdox

      - name: Check minimum coverage (80%)
        run: |
          COVERAGE=$(php -r "
            \$xml = simplexml_load_file('coverage.xml');
            \$metrics = \$xml->project->metrics;
            echo round(
              (\$metrics['coveredstatements'] / \$metrics['statements']) * 100, 2
            );
          ")
          echo "Coverage: ${COVERAGE}%"
          if (( $(echo "$COVERAGE < 80" | bc -l) )); then
            echo "Coverage ${COVERAGE}% is below minimum 80%"
            exit 1
          fi

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: coverage.xml
          flags: unittests

      - name: Upload test results
        uses: actions/upload-artifact@v3
        if: always()
        with:
          name: test-results-php${{ matrix.php }}
          path: test-results.xml

  # ─────────────────────────────────────────────
  # JOB 3: Build Docker Image
  # ─────────────────────────────────────────────
  build:
    name: Build & Push Docker Image
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/main'

    outputs:
      image-tag: ${{ steps.meta.outputs.tags }}
      image-digest: ${{ steps.build.outputs.digest }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Login to GitHub Container Registry
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract Docker metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ github.repository }}
          tags: |
            type=ref,event=branch
            type=sha,prefix=sha-
            type=raw,value=latest,enable=${{ github.ref == 'refs/heads/main' }}

      - name: Build and push Docker image
        id: build
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max
          build-args: |
            APP_ENV=production

  # ─────────────────────────────────────────────
  # JOB 4: Deploy to Production
  # ─────────────────────────────────────────────
  deploy:
    name: Deploy to Production
    runs-on: ubuntu-latest
    needs: build
    if: github.ref == 'refs/heads/main'
    environment:
      name: production
      url: https://myapp.com

    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /var/www/myapp
            docker pull ghcr.io/${{ github.repository }}:latest
            docker-compose up -d --no-deps app
            docker-compose exec -T app php artisan migrate --force
            docker-compose exec -T app php artisan optimize
            docker-compose exec -T app php artisan horizon:terminate
            docker-compose exec -T app php artisan queue:restart

      - name: Run smoke tests
        run: |
          sleep 10
          curl --fail https://myapp.com/health || exit 1
          curl --fail https://myapp.com/api/ping || exit 1

      - name: Notify Slack on success
        if: success()
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: |
            {
              "text": "✅ Deploy uğurlu: ${{ github.sha }} → production"
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}

      - name: Notify Slack on failure
        if: failure()
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: |
            {
              "text": "❌ Deploy uğursuz: ${{ github.sha }}"
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

---

## GitLab CI Nümunəsi

`.gitlab-ci.yml`:

*`.gitlab-ci.yml` üçün kod nümunəsi:*
```yaml
image: php:8.3-fpm

variables:
  MYSQL_ROOT_PASSWORD: secret
  MYSQL_DATABASE: laravel_test
  COMPOSER_HOME: "$CI_PROJECT_DIR/.composer"

cache:
  paths:
    - vendor/
    - .composer/

stages:
  - lint
  - test
  - build
  - deploy

# ─── Lint Stage ───────────────────────────────
phpstan:
  stage: lint
  before_script:
    - apt-get update && apt-get install -y git unzip
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-progress --prefer-dist
  script:
    - vendor/bin/phpstan analyse --error-format=gitlab > phpstan-report.json
  artifacts:
    reports:
      codequality: phpstan-report.json

# ─── Test Stage ───────────────────────────────
unit-tests:
  stage: test
  services:
    - mysql:8.0
    - redis:7-alpine
  variables:
    DB_HOST: mysql
    DB_DATABASE: laravel_test
    DB_USERNAME: root
    DB_PASSWORD: secret
    REDIS_HOST: redis
  before_script:
    - composer install --no-progress --prefer-dist
    - cp .env.testing.example .env.testing
    - php artisan key:generate --env=testing
    - php artisan migrate --env=testing --force
  script:
    - vendor/bin/phpunit --coverage-text --colors=never
  coverage: '/^\s*Lines:\s*\d+.\d+\%/'
  artifacts:
    reports:
      junit: test-results.xml

# ─── Build Stage ──────────────────────────────
build-image:
  stage: build
  image: docker:24
  services:
    - docker:24-dind
  only:
    - main
  script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker build -t $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA .
    - docker tag $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA $CI_REGISTRY_IMAGE:latest
    - docker push $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA
    - docker push $CI_REGISTRY_IMAGE:latest

# ─── Deploy Stage ─────────────────────────────
deploy-production:
  stage: deploy
  environment:
    name: production
    url: https://myapp.com
  only:
    - main
  when: manual  # Continuous Delivery üçün manual gate
  script:
    - apt-get install -y openssh-client
    - eval $(ssh-agent -s)
    - echo "$PROD_SSH_KEY" | ssh-add -
    - ssh -o StrictHostKeyChecking=no $PROD_USER@$PROD_HOST "
        cd /var/www/myapp &&
        docker pull $CI_REGISTRY_IMAGE:latest &&
        docker-compose up -d &&
        php artisan migrate --force &&
        php artisan optimize
      "
```

---

## Laravel Deployment Checklist

Production-a deploy etməzdən əvvəl aşağıdakı addımları izləmək lazımdır:

*Production-a deploy etməzdən əvvəl aşağıdakı addımları izləmək lazımdı üçün kod nümunəsi:*
```bash
#!/bin/bash
# deploy.sh — Laravel production deployment script

set -e  # Hər hansı error-da dayanır

echo "=== 1. Maintenance mode aktivləşdirilir ==="
php artisan down --retry=60 --secret="my-bypass-secret"

echo "=== 2. Yeni kod pull edilir ==="
git pull origin main

echo "=== 3. Composer dependencies yenilənir ==="
composer install --no-dev --optimize-autoloader --no-interaction

echo "=== 4. Assets build edilir ==="
npm ci --production
npm run build

echo "=== 5. Config cache ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "=== 6. Database migration ==="
php artisan migrate --force

echo "=== 7. Queue workers yenidən başladılır ==="
php artisan queue:restart

echo "=== 8. Horizon terminate (əgər istifadə edilərsə) ==="
php artisan horizon:terminate

echo "=== 9. Scheduled tasks yenidən qeydiyyatdan keçir ==="
php artisan schedule:clear-mutex

echo "=== 10. Opcache sıfırlanır ==="
php artisan opcache:clear  # spatie/laravel-opcache paketi

echo "=== 11. Maintenance mode söndürülür ==="
php artisan up

echo "=== 12. Health check ==="
curl --fail https://myapp.com/health || (echo "Health check failed!" && exit 1)

echo "=== Deploy tamamlandı! ==="
```

### Kritik Artisan Əmrləri

*Kritik Artisan Əmrləri üçün kod nümunəsi:*
```bash
# Bütün cache-ləri optimallaşdır
php artisan optimize

# Hər cache-i ayrıca clear et
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache
php artisan view:clear   && php artisan view:cache
php artisan event:clear  && php artisan event:cache

# Migration — force flag olmadan production-da soruşur
php artisan migrate --force

# Queue worker-ləri graceful restart et
php artisan queue:restart

# Horizon-u graceful terminate et (yeni prosess avtomatik başlayır)
php artisan horizon:terminate
```

---

## Zero-Downtime Deployment Strategiyaları

### 1. Blue-Green Deployment

İki eyni environment saxlanılır. Yeni versiyan "Green"-ə deploy edilir, test keçdikdən sonra load balancer "Blue"-dan "Green"-ə keçir.

```
              ┌─────────────────┐
              │  Load Balancer  │
              └────────┬────────┘
                       │
           ┌───────────┴───────────┐
           ▼                       ▼
    ┌─────────────┐         ┌─────────────┐
    │    BLUE     │         │    GREEN    │
    │  (v1.0 —   │         │  (v2.0 —   │
    │  LIVE)      │         │  STANDBY)   │
    └─────────────┘         └─────────────┘

Deploy zamanı:
1. Green-ə yeni kod deploy edilir
2. Green-də smoke tests keçirilir
3. Load balancer Green-ə yönləndirilir
4. Blue backup kimi saxlanılır (rollback üçün)
```

*4. Blue backup kimi saxlanılır (rollback üçün) üçün kod nümunəsi:*
```nginx
# Nginx upstream switching
upstream app_backend {
    server green_server:9000;  # green aktiv
    # server blue_server:9000;  # blue standby
}
```

**Üstünlükləri:** Ani rollback, production-da test mümkünlüyü
**Çatışmazlıqları:** İki qat infrastructure xərci, database sync problemi

### 2. Canary Deployment

Yeni versiyan əvvəlcə kiçik bir istifadəçi faizinə (məsələn, 5%) göndərilir, problem olmadıqda tədricən artırılır.

```
              ┌─────────────────┐
              │  Load Balancer  │
              └────────┬────────┘
                       │
           ┌───────────┴───────────┐
           │ 95%                   │ 5%
           ▼                       ▼
    ┌─────────────┐         ┌─────────────┐
    │  STABLE     │         │   CANARY    │
    │  (v1.0)     │         │   (v2.0)    │
    └─────────────┘         └─────────────┘
```

*└─────────────┘         └─────────────┘ üçün kod nümunəsi:*
```nginx
# Nginx weighted canary
upstream app_backend {
    server stable_server:9000 weight=95;
    server canary_server:9000 weight=5;
}
```

### 3. Rolling Deployment

Server-lər bir-bir yenilənir. Heç vaxt bütün server-lər eyni anda offline olmur.

```
Başlanğıc:  [v1][v1][v1][v1]
Step 1:     [v2][v1][v1][v1]
Step 2:     [v2][v2][v1][v1]
Step 3:     [v2][v2][v2][v1]
Final:      [v2][v2][v2][v2]
```

---

## Laravel Envoyer

Laravel Envoyer — zero-downtime deployment üçün SaaS xidmətidir. Atomic deployment strategiyası istifadə edir.

```
/var/www/myapp/
├── current -> releases/20240315_143022/  (symlink)
├── releases/
│   ├── 20240315_143022/   (cari aktiv release)
│   ├── 20240314_091512/   (əvvəlki release)
│   └── 20240313_184231/   (ondan əvvəlki release)
└── shared/
    ├── .env               (bütün release-lər üçün ortaq)
    ├── storage/           (upload-lar, logs)
    └── bootstrap/cache/
```

**Atomic deployment prosesi:**
1. Yeni `releases/timestamp/` qovluğu yaradılır
2. Kod ora deploy edilir
3. `shared/` symlink-ləri qurulur
4. `composer install`, `migrate`, `optimize` işə salınır
5. `current` symlink atomik olaraq yeni release-ə dəyişdirilir
6. Nginx/PHP-FPM dərhal yeni kodu görür — downtime yoxdur

---

## Deployer PHP

`deploy.php` — Deployer ilə tam nümunə:

*`deploy.php` — Deployer ilə tam nümunə üçün kod nümunəsi:*
```php
<?php

namespace Deployer;

require 'recipe/laravel.php';

// ─── Server konfiqurasiyası ───────────────────
host('production')
    ->setHostname('myserver.com')
    ->setRemoteUser('deploy')
    ->setIdentityFile('~/.ssh/id_rsa')
    ->set('deploy_path', '/var/www/myapp')
    ->set('branch', 'main');

host('staging')
    ->setHostname('staging.myserver.com')
    ->setRemoteUser('deploy')
    ->setIdentityFile('~/.ssh/id_rsa')
    ->set('deploy_path', '/var/www/myapp_staging')
    ->set('branch', 'develop');

// ─── Shared files (release-lər arasında ortaq) ───
set('shared_files', [
    '.env',
]);

set('shared_dirs', [
    'storage',
    'bootstrap/cache',
]);

// ─── Writable directories ────────────────────
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// ─── Custom task-lar ─────────────────────────
task('artisan:horizon:terminate', function () {
    run('{{bin/php}} {{release_path}}/artisan horizon:terminate');
});

task('artisan:queue:restart', function () {
    run('{{bin/php}} {{release_path}}/artisan queue:restart');
});

task('health:check', function () {
    $response = run('curl -s -o /dev/null -w "%{http_code}" https://myapp.com/health');
    if ($response !== '200') {
        throw new \Exception("Health check failed! HTTP status: {$response}");
    }
    info('Health check passed!');
});

// ─── Deployment pipeline ─────────────────────
after('deploy:failed', 'deploy:unlock');
after('artisan:migrate', 'artisan:horizon:terminate');
after('artisan:migrate', 'artisan:queue:restart');
after('deploy:publish', 'health:check');

// Deployment sırası:
// deploy:prepare → deploy:vendors → artisan:migrate
// → artisan:optimize → deploy:publish → health:check
```

*// → artisan:optimize → deploy:publish → health:check üçün kod nümunəsi:*
```bash
# Deploy etmək üçün
dep deploy production

# Rollback etmək üçün
dep rollback production

# Staging-ə deploy
dep deploy staging
```

---

## Docker + Laravel

### Dockerfile

*Dockerfile üçün kod nümunəsi:*
```dockerfile
# ─── Build Stage ─────────────────────────────
FROM php:8.3-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# PHP-FPM konfiqurasiyası
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# ─── Dependencies Stage ──────────────────────
FROM base AS dependencies

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

# ─── Production Stage ────────────────────────
FROM base AS production

COPY --from=dependencies /var/www/html /var/www/html

# Storage permissions
RUN chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]

# ─── Nginx Stage ─────────────────────────────
FROM nginx:1.25-alpine AS nginx

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 80
```

### docker-compose.yml

*docker-compose.yml üçün kod nümunəsi:*
```yaml
version: '3.8'

services:
  # ─── PHP-FPM ────────────────────────────────
  app:
    build:
      context: .
      target: production
    container_name: laravel_app
    restart: unless-stopped
    volumes:
      - app_storage:/var/www/html/storage
      - app_cache:/var/www/html/bootstrap/cache
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_HOST=mysql
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app_network

  # ─── Nginx ──────────────────────────────────
  nginx:
    build:
      context: .
      target: nginx
    container_name: laravel_nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
    depends_on:
      - app
    networks:
      - app_network

  # ─── MySQL ──────────────────────────────────
  mysql:
    image: mysql:8.0
    container_name: laravel_mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/my.cnf:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app_network

  # ─── Redis ──────────────────────────────────
  redis:
    image: redis:7-alpine
    container_name: laravel_redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD} --appendonly yes
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app_network

  # ─── Queue Worker ────────────────────────────
  queue:
    build:
      context: .
      target: production
    container_name: laravel_queue
    restart: unless-stopped
    command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
    depends_on:
      - mysql
      - redis
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_HOST=mysql
      - REDIS_HOST=redis
    networks:
      - app_network

  # ─── Scheduler ───────────────────────────────
  scheduler:
    build:
      context: .
      target: production
    container_name: laravel_scheduler
    restart: unless-stopped
    command: >
      sh -c "while true; do
        php artisan schedule:run --verbose --no-interaction &
        sleep 60
      done"
    depends_on:
      - mysql
      - redis
    networks:
      - app_network

volumes:
  mysql_data:
  redis_data:
  app_storage:
  app_cache:

networks:
  app_network:
    driver: bridge
```

---

## Kubernetes + Laravel

### Deployment YAML

*Deployment YAML üçün kod nümunəsi:*
```yaml
# k8s/deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
  namespace: production
  labels:
    app: laravel
    version: v1.0.0
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1        # Eyni anda maximum 1 əlavə pod
      maxUnavailable: 0  # Heç bir pod unavailable olmasın (zero-downtime)
  template:
    metadata:
      labels:
        app: laravel
    spec:
      initContainers:
        # Migration yalnız bir dəfə işə düşsün
        - name: migrate
          image: ghcr.io/myorg/laravel-app:latest
          command: ["php", "artisan", "migrate", "--force"]
          envFrom:
            - secretRef:
                name: laravel-secrets
            - configMapRef:
                name: laravel-config

      containers:
        - name: laravel-app
          image: ghcr.io/myorg/laravel-app:latest
          ports:
            - containerPort: 9000
          envFrom:
            - secretRef:
                name: laravel-secrets
            - configMapRef:
                name: laravel-config
          resources:
            requests:
              cpu: "250m"
              memory: "256Mi"
            limits:
              cpu: "500m"
              memory: "512Mi"
          readinessProbe:
            httpGet:
              path: /health
              port: 9000
            initialDelaySeconds: 10
            periodSeconds: 5
          livenessProbe:
            httpGet:
              path: /health
              port: 9000
            initialDelaySeconds: 30
            periodSeconds: 15
          volumeMounts:
            - name: storage
              mountPath: /var/www/html/storage
      volumes:
        - name: storage
          persistentVolumeClaim:
            claimName: laravel-storage-pvc

---
apiVersion: v1
kind: Service
metadata:
  name: laravel-service
  namespace: production
spec:
  selector:
    app: laravel
  ports:
    - protocol: TCP
      port: 80
      targetPort: 9000
  type: ClusterIP

---
apiVersion: v1
kind: ConfigMap
metadata:
  name: laravel-config
  namespace: production
data:
  APP_ENV: "production"
  APP_DEBUG: "false"
  DB_CONNECTION: "mysql"
  DB_HOST: "mysql-service"
  REDIS_HOST: "redis-service"
  QUEUE_CONNECTION: "redis"

---
# Secrets (real-da base64 encoded olmalıdır)
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secrets
  namespace: production
type: Opaque
stringData:
  APP_KEY: "base64:your-app-key-here"
  DB_PASSWORD: "your-db-password"
  REDIS_PASSWORD: "your-redis-password"
```

---

## Environment Management

### .env və Secrets

*.env və Secrets üçün kod nümunəsi:*
```php
// config/app.php — bütün config-lər env() vasitəsilə gəlir
'debug' => env('APP_DEBUG', false),
'key'   => env('APP_KEY'),

// Heç vaxt .env faylını git-ə commit etmə!
// .gitignore-a əlavə et:
// .env
// .env.production
// .env.staging
```

### HashiCorp Vault ilə Laravel

*HashiCorp Vault ilə Laravel üçün kod nümunəsi:*
```php
// app/Providers/VaultServiceProvider.php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Vault\Client as VaultClient;

class VaultServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (app()->environment('production')) {
            $this->loadSecretsFromVault();
        }
    }

    private function loadSecretsFromVault(): void
    {
        $vault = new VaultClient(config('vault.address'));
        $vault->setToken(config('vault.token'));

        // Vault-dan secret-ləri al
        $secrets = $vault->read('secret/laravel/production');

        // Laravel config-ə inject et
        config([
            'database.connections.mysql.password' => $secrets['db_password'],
            'mail.mailers.smtp.password'           => $secrets['smtp_password'],
            'services.stripe.secret'               => $secrets['stripe_secret'],
        ]);
    }
}
```

---

## Database Migration Strategiyası: Expand-Contract Pattern

Zero-downtime deployment zamanı migration-lar ciddi problemlər yarada bilər. Expand-Contract (Parallel Change) pattern-i bu problemi həll edir.

```
❌ Yanlış yanaşma (downtime yaradır):
   Bir migration-da köhnə sütunu rename et
   → Köhnə kod yeni sütun adını tapa bilmir = Error!

✅ Düzgün yanaşma (Expand-Contract):
   Step 1 (Expand):   Yeni sütun əlavə et, hər iki sütunu doldur
   Step 2 (Migrate):  Köhnə sütundan yeni sütuna data kopyala
   Step 3 (Contract): Köhnə sütunu sil (növbəti deploy-da)
```

*Step 3 (Contract): Köhnə sütunu sil (növbəti deploy-da) üçün kod nümunəsi:*
```php
// STEP 1: Expand — yeni sütun əlavə et
// Migration: 2024_01_15_add_full_name_to_users.php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('full_name')->nullable()->after('email');
        // first_name və last_name hələ saxlanılır!
    });
}

// Model-də hər iki sütunu doldur
class User extends Model
{
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Hər iki sütunu sync saxla
            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->full_name = trim("{$user->first_name} {$user->last_name}");
            }
            if ($user->isDirty('full_name')) {
                [$user->first_name, $user->last_name] = explode(' ', $user->full_name, 2);
            }
        });
    }
}

// STEP 2: Data migration (artisan command ilə)
// php artisan migrate:data users:full-name
public function handle(): void
{
    User::whereNull('full_name')->chunkById(1000, function ($users) {
        foreach ($users as $user) {
            $user->update([
                'full_name' => trim("{$user->first_name} {$user->last_name}")
            ]);
        }
    });
}

// STEP 3: Contract — köhnə sütunları sil (növbəti deploy-da)
// Migration: 2024_02_01_remove_first_last_name_from_users.php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['first_name', 'last_name']);
    });
}
```

---

## Feature Flags ilə Safe Deployment

*Feature Flags ilə Safe Deployment üçün kod nümunəsi:*
```php
// Feature flags ilə yeni kodu tədricən aktivləşdir
// Paket: laravel/pennant

// 1. Feature müəyyən et
use Laravel\Pennant\Feature;

Feature::define('new-checkout-flow', function (User $user) {
    // İlk mərhələdə yalnız internal users üçün
    if ($user->isInternal()) {
        return true;
    }

    // Canary: istifadəçilərin 10%-i üçün
    return $user->id % 10 === 0;
});

// 2. Kodda istifadə et
if (Feature::active('new-checkout-flow')) {
    return new NewCheckoutController($request);
}

return new OldCheckoutController($request);

// 3. Blade-də istifadə
@feature('new-checkout-flow')
    <x-new-checkout />
@else
    <x-old-checkout />
@endfeature

// 4. Middleware ilə route protection
Route::middleware(EnsureFeatureIsActive::using('new-checkout-flow'))
    ->group(function () {
        Route::get('/checkout/v2', [NewCheckoutController::class, 'index']);
    });
```

---

## Rollback Strategiyası

*Rollback Strategiyası üçün kod nümunəsi:*
```bash
# Deployer ilə rollback
dep rollback production

# Köhnə Docker image-ə rollback
docker-compose down
docker tag myapp:v1.2.3 myapp:latest  # əvvəlki versiyaya tag
docker-compose up -d

# Git revert ilə rollback
git revert HEAD~1 --no-edit
git push origin main
# → CI/CD pipeline avtomatik deploy edər

# Database rollback (diqqətlə!)
php artisan migrate:rollback --step=1

# Köhnə symlink-ə qayıtmaq (Envoyer/Deployer atomic deployment)
ln -sfn /var/www/app/releases/20240314_091512 /var/www/app/current
php artisan queue:restart
```

---

## Smoke Tests After Deployment

*Smoke Tests After Deployment üçün kod nümunəsi:*
```php
// tests/Feature/SmokeTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    /** @test */
    public function health_endpoint_returns_ok(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'ok',
                     'database' => 'connected',
                     'redis' => 'connected',
                 ]);
    }

    /** @test */
    public function homepage_loads_successfully(): void
    {
        $this->get('/')->assertStatus(200);
    }

    /** @test */
    public function api_ping_works(): void
    {
        $this->getJson('/api/ping')
             ->assertStatus(200)
             ->assertJson(['pong' => true]);
    }

    /** @test */
    public function critical_api_endpoints_are_accessible(): void
    {
        $endpoints = ['/api/v1/products', '/api/v1/categories'];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertStatus(200);
        }
    }
}

// app/Http/Controllers/HealthController.php
public function check(): JsonResponse
{
    $checks = [
        'status'   => 'ok',
        'database' => $this->checkDatabase(),
        'redis'    => $this->checkRedis(),
        'storage'  => $this->checkStorage(),
        'queue'    => $this->checkQueue(),
    ];

    $httpStatus = in_array('error', $checks) ? 503 : 200;

    return response()->json($checks, $httpStatus);
}

private function checkDatabase(): string
{
    try {
        DB::connection()->getPdo();
        return 'connected';
    } catch (\Exception) {
        return 'error';
    }
}

private function checkRedis(): string
{
    try {
        Redis::ping();
        return 'connected';
    } catch (\Exception) {
        return 'error';
    }
}
```

---

## Laravel Forge

Laravel Forge — server provisioning və deployment üçün SaaS platformadır. Manuel server konfiqurasiyası olmadan Laravel deploy etmək imkanı verir.

**Əsas imkanlar:**
- DigitalOcean, AWS, Linode, Vultr ilə bir kliklə server yarat
- Nginx, PHP-FPM, MySQL, Redis avtomatik konfiqurasiya
- SSL sertifikat (Let's Encrypt) avtomatik
- Deployment script-lər
- Cron jobs idarəsi
- Queue workers idarəsi
- Database backup

*- Database backup üçün kod nümunəsi:*
```bash
# Forge deployment script nümunəsi (Forge dashboard-da konfiqurasiya edilir)
cd /home/forge/myapp.com

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
    $FORGE_PHP artisan event:cache
    $FORGE_PHP artisan queue:restart
    $FORGE_PHP artisan horizon:terminate
fi
```

---

## İntervyu Sualları

**1. CI/CD ilə CD (Continuous Delivery vs Continuous Deployment) fərqi nədir?**
> Continuous Delivery-də hər uğurlu build production-a deploy edilməyə hazırdır, lakin faktiki deploy manual approval tələb edir. Continuous Deployment-da isə hər uğurlu pipeline avtomatik olaraq production-a çatır, heç bir manual addım yoxdur.

**2. Zero-downtime deployment necə əldə edilir?**
> Bir neçə yol mövcuddur: Atomic symlink switching (Envoyer/Deployer), Blue-Green deployment, Rolling deployment, Kubernetes rolling updates. Əsas prinsip: yeni kod deploy edilərkən köhnə kod hələ istifadəçilərə xidmət etməlidir.

**3. Database migration zamanı downtime necə qarşısı alınır?**
> Expand-Contract pattern istifadə edilir: əvvəlcə yeni sütun əlavə edilir (expand), data kopyalanır, sonra köhnə sütun silinir (contract). Bu şəkildə köhnə kod yeni sütunu, yeni kod isə köhnə sütunu görə bilir.

**4. Docker multi-stage build-in faydası nədir?**
> Final image kiçik olur. Build tools (composer, node) yalnız build stage-də mövcuddur, production image-inə daxil olmur. Bu security-ni artırır və image ölçüsünü azaldır.

**5. `php artisan queue:restart` nə edir?**
> Cache-ə bir flag yazır. Queue worker-lər bu flag-i görüb mövcud job-u bitirdikdən sonra gracefully dayanır. Supervisor onları yenidən başladır. Bu şəkildə job-lar yarımçıq qalmır.

**6. `php artisan horizon:terminate` ilə `queue:restart` fərqi nədir?**
> `horizon:terminate` — Horizon-un master supervisor prosesini dayanmağa məcbur edir. Horizon özü yenidən başlayır (əgər Supervisor tərəfindən idarə edilirsə). `queue:restart` — standart queue worker-lər üçündür.

**7. Blue-Green deployment-in çatışmazlığı nədir?**
> İki qat infrastructure xərci tələb olunur. Database migration zamanı iki versiya eyni anda işləyirsə backward-compatible migration-lar lazımdır. Stateful application-larda session management çətin olur.

**8. Feature flag nə üçün istifadə edilir?**
> Yeni feature-ı kod base-ə merge etməyə imkan verir, lakin istifadəçilərə göstərmir. Canary release (kiçik faiz), A/B testing, kill switch (anında söndürmə) üçün istifadə edilir.

**9. Kubernetes readinessProbe ilə livenessProbe fərqi nədir?**
> `readinessProbe` — pod-un traffic qəbul etməyə hazır olub olmadığını yoxlayır. Uğursuz olsa, pod service endpoint-lərdən çıxarılır. `livenessProbe` — pod-un işlək olub olmadığını yoxlayır. Uğursuz olsa, pod restart edilir.

**10. GitHub Actions-da `needs` açar sözü nədir?**
> Job-lar arasında dependency müəyyən edir. `needs: test` yazılmış job yalnız `test` job-u uğurla tamamlandıqda işə düşür. Bu şəkildə pipeline ardıcıl addımlarla işləyir.

**11. Canary deployment-i Kubernetes-də necə həyata keçirirsiniz?**
> İki ayrı Deployment yaradılır: biri köhnə versiyan üçün (məsələn, 9 replica), digəri yeni versiyan üçün (1 replica). Eyni Service hər ikisini label selector vasitəsilə yönləndirir. Yeni versiya stabilirsə, köhnənin replica sayı azaldılır, yeninin artırılır.

**12. HashiCorp Vault niyə .env faylından üstündür?**
> Vault dynamic secrets yaradır (hər dəfə yeni credential), audit log saxlayır, secret-ləri rotate edir, fine-grained access control verir. `.env` faylı isə static-dir, disk-də açıq mətn kimi saxlanılır, rotation çətindir.

---

## Anti-patternlər

**1. CI pipeline-sız birbaşa production-a deploy etmək**
Test, lint, security scan keçmədən kodu birbaşa server-ə push etmək — sınıq kod production-a çatır, regression-lar gec aşkar olunur, hotfix dövrü uzanır. Hər commit-də avtomatik test + lint + audit işlədən CI pipeline qur, yalnız yaşıl build-i deploy et.

**2. DB migration-larını backward-incompatible etmək**
Köhnə versiya hələ işləyərkən cədvəldən sütun silmək ya da adını dəyişmək — Blue-Green ya da rolling deployment zamanı köhnə pod-lar sınır, downtime yaranır. Expand-Contract (Parallel Change) pattern-i tətbiq et: əvvəl yeni sütunu əlavə et, kodu keçir, sonra köhnəni sil.

**3. Secretləri environment variable əvəzinə `.env` faylı ilə idarə etmək**
Docker image-ə ya da repo-ya `.env` faylı yerləşdirmək — şifrə, API key-lər image layerlarında, git tarixçəsində qalır. HashiCorp Vault, AWS Secrets Manager ya da Kubernetes Secrets istifadə et, `.env`-i yalnız local development üçün saxla.

**4. Deployment-i test etmədən feature flag-siz yeni funksionallıq buraxmaq**
Bütün istifadəçilərə birdən yeni funksiyanı açmaq — gizli bug bütün bazanı eyni anda təsir edir, rollback tez edilə bilmir. Feature flag ilə əvvəl canary (1-5%) istifadəçilərə aç, metrikaları izlə, sabitdirsə tam aç.

**5. Kubernetes readiness probe-suz pod-u service-ə əlavə etmək**
`readinessProbe` konfiqurasiya etmədən deploy etmək — bootstrap hələ tamamlanmamış pod traffic alır, 502 xətaları baş verir. `readinessProbe`-u DB bağlantısı, cache bağlantısı yoxlayan endpoint-ə yönləndir, yalnız hazır olan pod traffic alsın.

**6. Rollback planı olmadan deploy etmək**
"Əgər bir şey sınarsa görərik" münasibətiylə deploy etmək — kritik xəta zamanı əvvəlki versiyaya qayıtmaq üçün prosedur bilinmir, downtime uzanır. Hər deploy üçün rollback addımlarını əvvəlcədən sənədlə, Blue-Green deployment-da köhnə mühiti hazır saxla, database migration-larını revertible et.
