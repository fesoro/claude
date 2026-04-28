# GitHub Actions (Middle)

## Nədir? (What is it?)

GitHub Actions GitHub-un built-in CI/CD platformasıdır. Repository-dəki hadisələrə (push, PR, schedule) reaksiya olaraq avtomatik workflow-lar işlədə bilirsiniz. YAML faylları ilə konfiqurasiya olunur və `.github/workflows/` qovluğunda saxlanılır.

GitHub Actions pulsuz public repo-lar üçün limitsiz, private repo-lar üçün ayda 2000 dəqiqə (free plan) verir.

## Əsas Konseptlər (Key Concepts)

### Workflow Strukturu

```yaml
# .github/workflows/ci.yml
name: CI Pipeline          # Workflow adı

on:                        # Trigger (nə zaman işləsin)
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 6 * * 1'    # Hər bazar ertəsi saat 06:00
  workflow_dispatch:        # Manual trigger

env:                       # Global environment variables
  PHP_VERSION: '8.3'

jobs:                      # İşlər
  build:
    runs-on: ubuntu-latest # Runner
    steps:                 # Addımlar
      - uses: actions/checkout@v4
      - name: Run tests
        run: echo "Hello"
```

### Triggers (on)

```yaml
on:
  # Push hadisəsi
  push:
    branches: [main, 'release/**']
    tags: ['v*']
    paths:
      - 'src/**'
      - '!src/**/*.md'      # md faylları ignore et

  # Pull Request
  pull_request:
    types: [opened, synchronize, reopened]
    branches: [main]

  # Schedule (UTC)
  schedule:
    - cron: '30 5 * * 1-5'  # Weekdays 05:30 UTC

  # Manual
  workflow_dispatch:
    inputs:
      environment:
        description: 'Deploy environment'
        required: true
        default: 'staging'
        type: choice
        options: [staging, production]

  # Başqa workflow bitdikdə
  workflow_run:
    workflows: ["Build"]
    types: [completed]

  # Release yaradıldıqda
  release:
    types: [published]
```

### Jobs və Steps

```yaml
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: PHP Lint
        run: ./vendor/bin/pint --test

  test:
    needs: lint              # lint job-dan sonra işləsin
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run Tests
        run: php artisan test

  deploy:
    needs: [lint, test]      # Hər ikisi bitdikdən sonra
    if: github.ref == 'refs/heads/main'  # Yalnız main branch
    runs-on: ubuntu-latest
    environment: production  # GitHub Environment (approval)
    steps:
      - name: Deploy
        run: echo "Deploying..."
```

### Matrix Builds

```yaml
jobs:
  test:
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: false       # Bir fail olsa digərləri davam etsin
      matrix:
        os: [ubuntu-latest, ubuntu-22.04]
        php: ['8.1', '8.2', '8.3']
        laravel: ['10.*', '11.*']
        exclude:
          - php: '8.1'
            laravel: '11.*'  # Laravel 11 PHP 8.2+ tələb edir
        include:
          - php: '8.3'
            laravel: '11.*'
            coverage: true   # Yalnız bu kombinasiyada coverage

    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: ${{ matrix.coverage && 'xdebug' || 'none' }}

      - name: Install Laravel ${{ matrix.laravel }}
        run: composer require "laravel/framework:${{ matrix.laravel }}" --no-update

      - run: composer install --no-interaction

      - name: Run Tests
        run: php artisan test
```

### Secrets və Variables

```yaml
jobs:
  deploy:
    steps:
      # Repository secrets (Settings -> Secrets)
      - name: Deploy
        env:
          SSH_KEY: ${{ secrets.SSH_PRIVATE_KEY }}
          DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
        run: |
          echo "$SSH_KEY" > key.pem
          chmod 600 key.pem
          ssh -i key.pem user@server "deploy.sh"

      # GitHub-un built-in variables
      - name: Info
        run: |
          echo "Repo: ${{ github.repository }}"
          echo "Branch: ${{ github.ref_name }}"
          echo "SHA: ${{ github.sha }}"
          echo "Actor: ${{ github.actor }}"
          echo "Run: ${{ github.run_number }}"
```

### Caching

```yaml
jobs:
  test:
    steps:
      - uses: actions/checkout@v4

      # Composer cache
      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-

      # npm cache
      - name: Cache npm
        uses: actions/cache@v4
        with:
          path: node_modules
          key: npm-${{ hashFiles('package-lock.json') }}
          restore-keys: npm-

      - run: composer install --no-interaction
      - run: npm ci
```

### Artifacts

```yaml
jobs:
  build:
    steps:
      - name: Build
        run: npm run build

      - name: Upload Build Artifact
        uses: actions/upload-artifact@v4
        with:
          name: frontend-build
          path: public/build/
          retention-days: 5

  deploy:
    needs: build
    steps:
      - name: Download Build Artifact
        uses: actions/download-artifact@v4
        with:
          name: frontend-build
          path: public/build/
```

### Services (Database, Redis)

```yaml
jobs:
  test:
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping"
```

## Praktiki Nümunələr (Practical Examples)

### Complete Laravel CI/CD Pipeline

```yaml
# .github/workflows/laravel.yml
name: Laravel CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

env:
  PHP_VERSION: '8.3'
  NODE_VERSION: '20'

jobs:
  ##############################################
  # Code Quality
  ##############################################
  lint:
    name: Code Style & Static Analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}
          tools: composer:v2

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - run: composer install --no-interaction --prefer-dist

      - name: Laravel Pint (Code Style)
        run: ./vendor/bin/pint --test

      - name: PHPStan (Static Analysis)
        run: ./vendor/bin/phpstan analyse --memory-limit=512M

  ##############################################
  # Tests
  ##############################################
  test:
    name: Tests (PHP ${{ matrix.php }})
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.2', '8.3']

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports: ['3306:3306']
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

      redis:
        image: redis:7
        ports: ['6379:6379']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pdo_mysql, redis
          coverage: xdebug

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ matrix.php }}-${{ hashFiles('composer.lock') }}

      - run: composer install --no-interaction --prefer-dist

      - name: Prepare Environment
        run: |
          cp .env.testing .env
          php artisan key:generate

      - name: Run Migrations
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
        run: php artisan migrate --force

      - name: Run Tests
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
          REDIS_HOST: 127.0.0.1
        run: php artisan test --coverage-clover=coverage.xml

      - name: Upload Coverage
        if: matrix.php == '8.3'
        uses: codecov/codecov-action@v4
        with:
          file: coverage.xml
          token: ${{ secrets.CODECOV_TOKEN }}

  ##############################################
  # Security Audit
  ##############################################
  security:
    name: Security Audit
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ env.PHP_VERSION }}

      - run: composer install --no-interaction
      - name: Composer Audit
        run: composer audit
      - name: npm Audit
        run: npm audit --production

  ##############################################
  # Build Assets
  ##############################################
  build:
    name: Build Frontend
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: ${{ env.NODE_VERSION }}
          cache: 'npm'
      - run: npm ci
      - run: npm run build
      - uses: actions/upload-artifact@v4
        with:
          name: frontend-build
          path: public/build/

  ##############################################
  # Deploy to Staging
  ##############################################
  deploy-staging:
    name: Deploy to Staging
    needs: [lint, test, security, build]
    if: github.ref == 'refs/heads/develop'
    runs-on: ubuntu-latest
    environment:
      name: staging
      url: https://staging.example.com
    steps:
      - uses: actions/checkout@v4
      - uses: actions/download-artifact@v4
        with:
          name: frontend-build
          path: public/build/

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.STAGING_HOST }}
          username: deploy
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/staging
            git pull origin develop
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan queue:restart

  ##############################################
  # Deploy to Production
  ##############################################
  deploy-production:
    name: Deploy to Production
    needs: [lint, test, security, build]
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment:
      name: production
      url: https://example.com
    steps:
      - uses: actions/checkout@v4
      - uses: actions/download-artifact@v4
        with:
          name: frontend-build
          path: public/build/

      - name: Deploy to Production
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.PROD_HOST }}
          username: deploy
          key: ${{ secrets.SSH_PRIVATE_KEY }}
          script: |
            cd /var/www/production
            php artisan down --secret="bypass-token"
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            php artisan up

      - name: Health Check
        run: |
          sleep 10
          STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://example.com/api/health)
          if [ "$STATUS" != "200" ]; then
            echo "Health check failed with status $STATUS"
            exit 1
          fi

      - name: Notify Slack
        if: always()
        uses: 8398a7/action-slack@v3
        with:
          status: ${{ job.status }}
          fields: repo,message,commit,author
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK }}
```

### Reusable Workflow

```yaml
# .github/workflows/reusable-test.yml
name: Reusable Test Workflow

on:
  workflow_call:
    inputs:
      php-version:
        required: true
        type: string
    secrets:
      codecov-token:
        required: false

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ inputs.php-version }}
      - run: composer install
      - run: php artisan test

# Calling workflow
# .github/workflows/ci.yml
jobs:
  test-php82:
    uses: ./.github/workflows/reusable-test.yml
    with:
      php-version: '8.2'
    secrets:
      codecov-token: ${{ secrets.CODECOV_TOKEN }}
```

### Custom Action (Composite)

```yaml
# .github/actions/setup-laravel/action.yml
name: Setup Laravel
description: Setup PHP and install Laravel dependencies

inputs:
  php-version:
    description: PHP version
    default: '8.3'

runs:
  using: composite
  steps:
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ inputs.php-version }}
        extensions: mbstring, pdo_mysql, redis
        coverage: xdebug

    - name: Cache Composer
      uses: actions/cache@v4
      with:
        path: vendor
        key: composer-${{ hashFiles('composer.lock') }}

    - name: Install Dependencies
      shell: bash
      run: composer install --no-interaction --prefer-dist

    - name: Setup Environment
      shell: bash
      run: |
        cp .env.testing .env
        php artisan key:generate

# İstifadə
# jobs:
#   test:
#     steps:
#       - uses: actions/checkout@v4
#       - uses: ./.github/actions/setup-laravel
#         with:
#           php-version: '8.3'
```

## PHP/Laravel ilə İstifadə

### Laravel Forge ilə GitHub Actions

```yaml
# Forge webhook ilə deploy
deploy:
  steps:
    - name: Trigger Forge Deploy
      run: |
        curl -X POST \
          "${{ secrets.FORGE_DEPLOY_WEBHOOK }}" \
          -H "Content-Type: application/json"
```

### PR Preview Environments

```yaml
# .github/workflows/preview.yml
name: PR Preview

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  preview:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Create Preview
        env:
          PR_NUMBER: ${{ github.event.number }}
        run: |
          # Deploy to preview-pr-{number}.example.com
          echo "Deploying PR #$PR_NUMBER preview..."

      - name: Comment PR
        uses: actions/github-script@v7
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `Preview deployed: https://preview-pr-${context.issue.number}.example.com`
            })
```

## Interview Sualları

### Q1: GitHub Actions-da job-lar arasında data necə paylaşılır?
**Cavab:** İki üsul var: 1) Artifacts - `upload-artifact` və `download-artifact` actions ilə fayllar paylaşılır. 2) Outputs - bir job-un output-u digər job-da `needs.job-name.outputs.output-name` ilə istifadə olunur. Cache isə eyni key ilə müxtəlif run-lar arasında data paylaşır.

### Q2: Self-hosted runner nə vaxt istifadə olunmalıdır?
**Cavab:** GPU lazım olanda, xüsusi hardware tələb olanda, private network-a çıxış lazım olanda, GitHub-un free minutes limiti kifayət etmədikdə, compliance tələbləri olanda (data on-premise qalmalıdır).

### Q3: Matrix strategy nədir?
**Cavab:** Eyni job-u müxtəlif konfiqurasiyalarla paralel işlətmək üçündür. Məsələn: PHP 8.1, 8.2, 8.3 versiyaları ilə, MySQL və PostgreSQL ilə, Ubuntu və macOS-da test etmək. `exclude` ilə bəzi kombinasiyalar çıxarılır, `include` ilə əlavə olunur.

### Q4: Workflow-ları necə optimize etmək olar?
**Cavab:** Caching (composer, npm, Docker layers), matrix builds ilə paralel test, `paths` filter ilə lazımsız run-ları azaltmaq, reusable workflows ilə DRY, concurrency ilə eyni branch üçün köhnə run-ları cancel etmək, conditional steps ilə lazımsız addımları skip etmək.

### Q5: GitHub Actions-da secrets necə idarə olunur?
**Cavab:** Repository Settings-dən secrets əlavə olunur. Workflow-da `${{ secrets.NAME }}` ilə istifadə olunur. Secrets log-larda mask olunur. Environment-specific secrets üçün GitHub Environments istifadə olunur. Organization-level secrets bütün repo-lara paylaşıla bilər.

## Best Practices

1. **Pin action versions** - `uses: actions/checkout@v4` deyil `actions/checkout@v4.1.1` və ya SHA istifadə edin
2. **Concurrency** - Eyni branch üçün yalnız son workflow işləsin: `concurrency: { group: ${{ github.ref }}, cancel-in-progress: true }`
3. **Timeout** - Jobs-a timeout qoyun: `timeout-minutes: 15`
4. **Minimal permissions** - `permissions: { contents: read }` ilə minimum icazə verin
5. **Cache everything** - Composer, npm, Docker layer cache istifadə edin
6. **Fail fast** - Lint əvvəl, ağır testlər sonra
7. **Environment protection** - Production deploy üçün manual approval tələb edin
8. **Reusable workflows** - Təkrarlanan pipeline-ları workflow_call ilə paylaşın
9. **Status badges** - README-ə workflow status badge əlavə edin
10. **Dependabot** - Dependency update-ləri avtomatlaşdırın

---

## Praktik Tapşırıqlar

1. Laravel layihəsi üçün tam CI workflow yazın: `push` + `pull_request` trigger, PHP 8.3 matrix, `composer install --no-dev`, `php artisan test --parallel`, test fail olarsa deploy dayandırılsın
2. Reusable workflow yaradın: `deploy.yml` adlı workflow, `environment` input parametri qəbul etsin (staging/production), SSH ilə server-ə qoşulub deployment script-i işlətsin; başqa workflow-dan `uses:` ilə çağırın
3. GitHub Secrets qurun: `SSH_PRIVATE_KEY`, `APP_KEY`, `DB_PASSWORD`; workflow-da `${{ secrets.SSH_PRIVATE_KEY }}`ilə istifadə edin; secret-i echo ilə mask-lanmasını test edin
4. Composer cache konfiqurasiya edin: `actions/cache@v4` ilə `vendor/` cache edin, cache key-i `composer.lock` hash-inə bağlayın; iki ardıcıl run arasında sürət fərqini ölçün
5. Matrix build qurun: `php: [8.1, 8.2, 8.3]` × `laravel: [10, 11]` — 6 paralel job yaradın; yalnız PHP 8.3 + Laravel 11 kombinasiyasında production deploy baş versin
6. `workflow_run` trigger ilə CI→CD chain qurun: test workflow keçəndən sonra avtomatik deploy workflow başlasın; manual approval üçün `environment: production` protection rule qurun

## Əlaqəli Mövzular

- [GitLab CI/CD](05-gitlab-ci.md) — .gitlab-ci.yml, stages, Laravel pipeline
- [Jenkins](06-jenkins.md) — Jenkinsfile, shared library
- [CI/CD Konseptləri](03-cicd-concepts.md) — pipeline stages, trunk-based development
- [CI/CD Deployment](39-cicd-deployment.md) — artifact management, deploy stages
- [Container Security](29-container-security.md) — CI/CD-də image scanning
- [DORA Metrics](45-dora-metrics.md) — deployment frequency ölçmə
