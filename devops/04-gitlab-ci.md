# GitLab CI/CD

## Nədir? (What is it?)

GitLab CI/CD GitLab-ın built-in CI/CD platformasıdır. `.gitlab-ci.yml` faylı ilə konfiqurasiya olunur və repository-nin root-unda saxlanılır. GitLab Runner adlı agent-lar vasitəsilə job-lar icra olunur. GitHub Actions-dan fərqli olaraq GitLab həm SaaS, həm də self-hosted ola bilir.

GitLab CI pipeline-lar, environments, review apps, container registry və auto DevOps kimi güclü xüsusiyyətlər təqdim edir.

## Əsas Konseptlər (Key Concepts)

### .gitlab-ci.yml Strukturu

```yaml
# .gitlab-ci.yml
# Global settings
image: php:8.3-cli           # Default Docker image
default:
  retry: 1                    # Fail olsa 1 dəfə təkrarla

# Variables
variables:
  MYSQL_ROOT_PASSWORD: password
  MYSQL_DATABASE: testing
  APP_ENV: testing

# Cache (bütün job-lar üçün)
cache:
  key: ${CI_COMMIT_REF_SLUG}
  paths:
    - vendor/
    - node_modules/

# Stages sırası
stages:
  - install
  - quality
  - test
  - build
  - deploy

# Jobs
install-deps:
  stage: install
  script:
    - composer install --no-interaction
    - npm ci

lint:
  stage: quality
  script:
    - ./vendor/bin/pint --test

test:
  stage: test
  script:
    - php artisan test

deploy:
  stage: deploy
  script:
    - ./deploy.sh
  only:
    - main
```

### Stages

```yaml
stages:
  - install       # 1-ci: Dependencies
  - quality       # 2-ci: Lint, static analysis
  - test          # 3-ci: Unit, integration tests
  - build         # 4-cu: Build artifacts
  - staging       # 5-ci: Deploy to staging
  - production    # 6-cı: Deploy to production

# Eyni stage-dakı job-lar PARALEL işləyir
# Fərqli stage-lar ARDIŞIL işləyir

lint:
  stage: quality    # paralel
phpstan:
  stage: quality    # paralel

unit-test:
  stage: test       # lint və phpstan bitdikdən sonra
```

### Jobs

```yaml
# Job syntax
job-name:
  stage: test
  image: php:8.3-cli          # Job-specific image
  services:                    # Əlavə containers
    - mysql:8.0
    - redis:7
  variables:                   # Job-specific variables
    DB_HOST: mysql
  before_script:               # Hər job-dan əvvəl
    - composer install
  script:                      # Əsas komandalar (required)
    - php artisan test
  after_script:                # Job-dan sonra (hətta fail olsa)
    - echo "Cleanup"
  artifacts:                   # Nəticə faylları
    paths:
      - coverage/
    reports:
      junit: results.xml
    expire_in: 1 week
  rules:                       # Nə vaxt işləsin
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
    - if: $CI_COMMIT_BRANCH == "main"
  tags:                        # Runner label
    - docker
    - linux
  allow_failure: false         # Fail olsa pipeline davam etsin?
  timeout: 30 minutes
  retry:
    max: 2
    when:
      - runner_system_failure
      - stuck_or_timeout_failure
```

### Variables

```yaml
# Global variables
variables:
  PHP_VERSION: "8.3"
  DEPLOY_PATH: "/var/www/app"

# Predefined CI/CD variables (GitLab tərəfindən)
# CI_COMMIT_SHA          - Full commit SHA
# CI_COMMIT_REF_NAME     - Branch/tag adı
# CI_COMMIT_REF_SLUG     - URL-safe branch adı
# CI_PROJECT_NAME         - Project adı
# CI_PIPELINE_ID          - Pipeline ID
# CI_JOB_ID               - Job ID
# CI_MERGE_REQUEST_IID    - MR number
# CI_ENVIRONMENT_NAME     - Environment adı

# İstifadə
deploy:
  script:
    - echo "Deploying $CI_COMMIT_REF_NAME to $DEPLOY_PATH"
    - echo "Pipeline: $CI_PIPELINE_ID, Job: $CI_JOB_ID"

# Group/Project level variables (Settings -> CI/CD -> Variables)
# Protected: yalnız protected branches-da mövcud
# Masked: log-larda gizlədilir
deploy:
  script:
    - ssh -i $SSH_PRIVATE_KEY deploy@$PROD_HOST "deploy.sh"
```

### Artifacts və Cache

```yaml
# Artifacts - job nəticələri, digər job-lara ötürülür
build:
  stage: build
  script:
    - npm run build
  artifacts:
    paths:
      - public/build/
    reports:
      junit: test-results.xml
      coverage_report:
        coverage_format: cobertura
        path: coverage.xml
    expire_in: 7 days

# Cache - dependency-lər, run-lar arasında paylaşılır
install:
  cache:
    key:
      files:
        - composer.lock    # Lock fayl dəyişsə cache yenilənir
    paths:
      - vendor/
    policy: push           # Bu job cache yazır

test:
  cache:
    key:
      files:
        - composer.lock
    paths:
      - vendor/
    policy: pull           # Bu job cache oxuyur
```

### Environments

```yaml
deploy-staging:
  stage: staging
  script:
    - ./deploy.sh staging
  environment:
    name: staging
    url: https://staging.example.com
    on_stop: stop-staging         # Environment dayandırma job-u

stop-staging:
  stage: staging
  script:
    - ./teardown.sh staging
  environment:
    name: staging
    action: stop
  when: manual
  rules:
    - if: $CI_COMMIT_BRANCH == "develop"

deploy-production:
  stage: production
  script:
    - ./deploy.sh production
  environment:
    name: production
    url: https://example.com
  when: manual                     # Manual trigger
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
```

### Review Apps

```yaml
# Hər MR üçün ayrı preview environment
deploy-review:
  stage: deploy
  script:
    - kubectl apply -f k8s/review.yml
    - kubectl set image deployment/review app=registry.example.com/app:$CI_COMMIT_SHA
  environment:
    name: review/$CI_COMMIT_REF_SLUG
    url: https://$CI_COMMIT_REF_SLUG.review.example.com
    on_stop: stop-review
    auto_stop_in: 1 week
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"

stop-review:
  stage: deploy
  script:
    - kubectl delete namespace review-$CI_COMMIT_REF_SLUG
  environment:
    name: review/$CI_COMMIT_REF_SLUG
    action: stop
  when: manual
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
      when: manual
```

### Rules vs Only/Except

```yaml
# Rules (yeni, tövsiyə olunan)
deploy:
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
      when: always
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"
      when: manual
    - when: never                  # Default: işləmə

# Changes-based rules
test-frontend:
  rules:
    - changes:
        - "resources/js/**/*"
        - "package.json"
      when: always
    - when: never

# Only/Except (köhnə, istifadə etməyin)
deploy:
  only:
    - main
  except:
    - tags
```

### Include (reusable configs)

```yaml
# .gitlab-ci.yml
include:
  # Local file
  - local: '.gitlab/ci/test.yml'

  # Başqa project-dən
  - project: 'devops/ci-templates'
    ref: main
    file: '/templates/laravel.yml'

  # Remote URL
  - remote: 'https://example.com/ci/template.yml'

  # Template
  - template: 'Auto-DevOps.gitlab-ci.yml'
```

## Praktiki Nümunələr (Practical Examples)

### Complete Laravel GitLab CI Pipeline

```yaml
# .gitlab-ci.yml
image: php:8.3-cli

variables:
  MYSQL_ROOT_PASSWORD: password
  MYSQL_DATABASE: testing
  DB_HOST: mysql
  DB_DATABASE: testing
  DB_USERNAME: root
  DB_PASSWORD: password
  REDIS_HOST: redis

stages:
  - install
  - quality
  - test
  - build
  - staging
  - production

# ──────────────────────────────────
# Install Dependencies
# ──────────────────────────────────
composer:
  stage: install
  script:
    - apt-get update && apt-get install -y git unzip libzip-dev
    - docker-php-ext-install pdo_mysql zip
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-interaction --prefer-dist
  cache:
    key:
      files:
        - composer.lock
    paths:
      - vendor/
    policy: push
  artifacts:
    paths:
      - vendor/
    expire_in: 1 hour

npm:
  stage: install
  image: node:20-alpine
  script:
    - npm ci
  cache:
    key:
      files:
        - package-lock.json
    paths:
      - node_modules/
    policy: push
  artifacts:
    paths:
      - node_modules/
    expire_in: 1 hour

# ──────────────────────────────────
# Code Quality (parallel)
# ──────────────────────────────────
pint:
  stage: quality
  needs: [composer]
  script:
    - ./vendor/bin/pint --test

phpstan:
  stage: quality
  needs: [composer]
  script:
    - ./vendor/bin/phpstan analyse --memory-limit=512M

security-audit:
  stage: quality
  needs: [composer]
  script:
    - composer audit --format=json
  allow_failure: true

# ──────────────────────────────────
# Tests
# ──────────────────────────────────
phpunit:
  stage: test
  needs: [composer]
  services:
    - mysql:8.0
    - redis:7
  before_script:
    - apt-get update && apt-get install -y libzip-dev
    - docker-php-ext-install pdo_mysql zip
    - cp .env.testing .env
    - php artisan key:generate
    - php artisan migrate --force
  script:
    - php artisan test --log-junit report.xml --coverage-text --coverage-cobertura=coverage.xml
  coverage: '/Lines:\s+(\d+\.\d+)%/'
  artifacts:
    reports:
      junit: report.xml
      coverage_report:
        coverage_format: cobertura
        path: coverage.xml
    when: always

browser-test:
  stage: test
  needs: [composer, npm]
  image: cypress/browsers:latest
  services:
    - mysql:8.0
  script:
    - php artisan serve &
    - npx cypress run
  artifacts:
    paths:
      - cypress/screenshots/
      - cypress/videos/
    when: on_failure
    expire_in: 3 days
  rules:
    - if: $CI_PIPELINE_SOURCE == "merge_request_event"

# ──────────────────────────────────
# Build
# ──────────────────────────────────
build-assets:
  stage: build
  image: node:20-alpine
  needs: [npm]
  script:
    - npm run build
  artifacts:
    paths:
      - public/build/
    expire_in: 7 days
  rules:
    - if: $CI_COMMIT_BRANCH =~ /^(main|develop)$/

# ──────────────────────────────────
# Deploy Staging
# ──────────────────────────────────
deploy-staging:
  stage: staging
  needs: [phpunit, build-assets]
  image: alpine:latest
  before_script:
    - apk add --no-cache openssh-client rsync
    - eval $(ssh-agent -s)
    - echo "$SSH_PRIVATE_KEY" | ssh-add -
    - mkdir -p ~/.ssh && chmod 700 ~/.ssh
    - echo "$SSH_KNOWN_HOSTS" >> ~/.ssh/known_hosts
  script:
    - |
      rsync -avz --delete \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='tests' \
        --exclude='.env' \
        ./ deploy@$STAGING_HOST:/var/www/staging/
    - |
      ssh deploy@$STAGING_HOST '
        cd /var/www/staging
        composer install --no-dev --optimize-autoloader
        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan queue:restart
        sudo systemctl reload php8.3-fpm
      '
  environment:
    name: staging
    url: https://staging.example.com
  rules:
    - if: $CI_COMMIT_BRANCH == "develop"

# ──────────────────────────────────
# Deploy Production
# ──────────────────────────────────
deploy-production:
  stage: production
  needs: [phpunit, build-assets]
  image: alpine:latest
  before_script:
    - apk add --no-cache openssh-client curl
    - eval $(ssh-agent -s)
    - echo "$SSH_PRIVATE_KEY" | ssh-add -
    - mkdir -p ~/.ssh && chmod 700 ~/.ssh
    - echo "$SSH_KNOWN_HOSTS" >> ~/.ssh/known_hosts
  script:
    - |
      ssh deploy@$PROD_HOST '
        cd /var/www/production
        php artisan down --secret="deploy-bypass"
        git pull origin main
        composer install --no-dev --optimize-autoloader
        php artisan migrate --force
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan queue:restart
        sudo systemctl reload php8.3-fpm
        php artisan up
      '
    - |
      # Health check
      sleep 5
      STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://example.com/api/health)
      if [ "$STATUS" != "200" ]; then
        echo "Health check failed! Rolling back..."
        ssh deploy@$PROD_HOST 'cd /var/www/production && git checkout HEAD~1 && php artisan up'
        exit 1
      fi
  environment:
    name: production
    url: https://example.com
  when: manual
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
```

### Docker-based Pipeline

```yaml
# .gitlab-ci.yml
stages:
  - build
  - test
  - push
  - deploy

variables:
  DOCKER_IMAGE: $CI_REGISTRY_IMAGE:$CI_COMMIT_SHA

build-image:
  stage: build
  image: docker:24
  services:
    - docker:24-dind
  script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker build -t $DOCKER_IMAGE .
    - docker push $DOCKER_IMAGE

test:
  stage: test
  image: $DOCKER_IMAGE
  services:
    - mysql:8.0
  script:
    - php artisan test

push-latest:
  stage: push
  image: docker:24
  services:
    - docker:24-dind
  script:
    - docker login -u $CI_REGISTRY_USER -p $CI_REGISTRY_PASSWORD $CI_REGISTRY
    - docker pull $DOCKER_IMAGE
    - docker tag $DOCKER_IMAGE $CI_REGISTRY_IMAGE:latest
    - docker push $CI_REGISTRY_IMAGE:latest
  rules:
    - if: $CI_COMMIT_BRANCH == "main"
```

## PHP/Laravel ilə İstifadə

### Laravel-specific CI Template

```yaml
# .gitlab/ci/laravel-template.yml
.laravel-base:
  image: php:8.3-cli
  before_script:
    - apt-get update -qq && apt-get install -y -qq git unzip libzip-dev libpng-dev
    - docker-php-ext-install pdo_mysql zip gd bcmath
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

.laravel-test:
  extends: .laravel-base
  services:
    - mysql:8.0
    - redis:7
  variables:
    DB_HOST: mysql
    REDIS_HOST: redis
  before_script:
    - !reference [.laravel-base, before_script]
    - composer install --no-interaction
    - cp .env.testing .env
    - php artisan key:generate
    - php artisan migrate --force
```

## Interview Sualları

### Q1: GitLab CI-da cache və artifact fərqi nədir?
**Cavab:** Cache dependency-ləri (vendor/, node_modules/) pipeline run-lar arasında saxlayır, build speed üçündür. Artifact isə bir job-un nəticəsini (build output, test reports) eyni pipeline-dakı sonrakı job-lara ötürür. Cache pull/push policy ilə idarə olunur, artifact-lar expire_in ilə silinir.

### Q2: GitLab Runner nədir və növləri hansılardır?
**Cavab:** GitLab Runner job-ları icra edən agentdir. Executor növləri: Shell (birbaşa host-da), Docker (container-da), Kubernetes (pod-da), VirtualBox, SSH. Docker executor ən populyardır. Runner shared (bütün projects) və ya specific (bir project) ola bilər.

### Q3: Review Apps nədir?
**Cavab:** Hər merge request üçün avtomatik yaradılan müvəqqəti mühitdir. MR açıldıqda deploy olunur, MR merge/close olanda silinir. Feature-ları production-a deploy etmədən test etmək üçündür. Dynamic environments ilə yaradılır.

### Q4: GitLab CI vs GitHub Actions?
**Cavab:** GitLab CI: `.gitlab-ci.yml`, stages-based, built-in container registry, review apps, Auto DevOps, self-hosted option. GitHub Actions: event-driven, marketplace ecosystem, reusable workflows, daha çevik trigger system. GitLab daha "batteries included", GitHub Actions daha modular.

### Q5: needs keyword nə edir?
**Cavab:** `needs` ilə DAG (Directed Acyclic Graph) yaradılır. Default-da eyni stage-dakı job-lar paralel, fərqli stage-lar ardışıl işləyir. `needs` ilə bir job başqa stage-dakı job bitən kimi (stage-ın tam bitməsini gözləmədən) başlaya bilər. Bu pipeline-ı sürətləndirir.

### Q6: Pipeline-ı necə optimize edərsiniz?
**Cavab:** Cache istifadəsi, `needs` ilə DAG, paralel jobs, `rules` ilə lazımsız job-ları skip etmək, lightweight Docker images, interruptible jobs, resource_group ilə concurrency control.

## Best Practices

1. **Cache Lock Files** - `composer.lock` və `package-lock.json` key ilə cache edin
2. **Needs keyword** - DAG istifadə edərək pipeline-ı sürətləndirin
3. **Rules over Only/Except** - `rules` daha güclü və oxunaqlıdır
4. **Include templates** - Təkrarlanan config-ləri ayrı fayllara çıxarın
5. **Protected variables** - Secrets-ı protected branches ilə məhdudlaşdırın
6. **Review Apps** - Hər MR üçün preview environment yaradın
7. **Environments** - Deploy tracking üçün environments istifadə edin
8. **Artifact expire** - Artifacts-a expire_in qoyun, disk space qənaət edin
9. **Retry on failure** - Flaky infrastructure failures üçün retry istifadə edin
10. **interruptible** - Yeni push olanda köhnə pipeline-ı cancel edin
