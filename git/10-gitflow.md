# GitFlow Branching Model

## Nədir? (What is it?)

GitFlow, Vincent Driessen tərəfindən 2010-cu ildə təqdim edilmiş branch idarəetmə modelidir. Müəyyən branch-lərin müəyyən məqsədlər üçün istifadəsini təyin edir. Xüsusilə planlı release dövrləri olan layihələr üçün uyğundur.

```
GitFlow Branch Strukturu:

  main (production)     ──●──────────────────●──────────●──
                          │                  ↑          ↑
  hotfix                  │            ┌─────┘    ┌─────┘
                          │            │          │
  release                 │      ●───●─┘    ●───●─┘
                          │      ↑          ↑
  develop                 ●──●───●──●───●───●──●──●──●──
                             │      ↑   │       ↑
  feature                    ●──●───┘   ●───●───┘
```

### Beş Əsas Branch Tipi

```
┌──────────────┬──────────────────────────────────────────┐
│ Branch       │ Məqsəd                                   │
├──────────────┼──────────────────────────────────────────┤
│ main         │ Production kodu, hər commit = release    │
│ develop      │ İnteqrasiya branch-i, növbəti release    │
│ feature/*    │ Yeni xüsusiyyətlər (develop-dən açılır) │
│ release/*    │ Release hazırlığı (develop-dən açılır)   │
│ hotfix/*     │ Production bug fix (main-dən açılır)     │
└──────────────┴──────────────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### git-flow Extension ilə

```bash
# git-flow quraşdırma
# macOS
brew install git-flow-avh

# Ubuntu/Debian
apt-get install git-flow

# Layihədə init
git flow init
# Branch adları üçün default-ları qəbul edin:
#   Production: main
#   Development: develop
#   Feature prefix: feature/
#   Release prefix: release/
#   Hotfix prefix: hotfix/
```

### Feature Branch

```bash
# git-flow ilə
git flow feature start user-authentication
git flow feature finish user-authentication
git flow feature publish user-authentication  # Remote-a push

# Manual əmrlərlə
git checkout develop
git checkout -b feature/user-authentication
# ... işləyin, commit edin ...
git checkout develop
git merge --no-ff feature/user-authentication
git branch -d feature/user-authentication
```

### Release Branch

```bash
# git-flow ilə
git flow release start 2.1.0
git flow release finish 2.1.0

# Manual əmrlərlə
git checkout develop
git checkout -b release/2.1.0
# ... son düzəlişlər, version bump ...
git checkout main
git merge --no-ff release/2.1.0
git tag -a v2.1.0 -m "Release 2.1.0"
git checkout develop
git merge --no-ff release/2.1.0
git branch -d release/2.1.0
```

### Hotfix Branch

```bash
# git-flow ilə
git flow hotfix start 2.1.1
git flow hotfix finish 2.1.1

# Manual əmrlərlə
git checkout main
git checkout -b hotfix/2.1.1
# ... bug fix ...
git checkout main
git merge --no-ff hotfix/2.1.1
git tag -a v2.1.1 -m "Hotfix 2.1.1"
git checkout develop
git merge --no-ff hotfix/2.1.1
git branch -d hotfix/2.1.1
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Tam Feature Lifecycle

```bash
# 1. Feature başlat
git checkout develop
git pull origin develop
git checkout -b feature/shopping-cart

# 2. İşləyin və commit edin
git add .
git commit -m "feat: add cart model and migration"
git commit -m "feat: add cart controller and routes"
git commit -m "feat: add cart blade views"
git commit -m "test: add cart feature tests"

# 3. Develop-i feature-a merge edin (güncel saxlayın)
git checkout develop
git pull origin develop
git checkout feature/shopping-cart
git merge develop

# 4. Feature-ı bitirin
git checkout develop
git merge --no-ff feature/shopping-cart
git push origin develop
git branch -d feature/shopping-cart
git push origin --delete feature/shopping-cart
```

### Nümunə 2: Release Prosesi

```bash
# 1. Release branch yaradın
git checkout develop
git checkout -b release/3.0.0

# 2. Version bump
# composer.json, config/app.php, CHANGELOG.md yeniləyin
git commit -am "chore: bump version to 3.0.0"

# 3. Son test və düzəlişlər
git commit -am "fix: correct validation message typo"
git commit -am "docs: update API documentation for v3"

# 4. Main-ə merge və tag
git checkout main
git merge --no-ff release/3.0.0
git tag -a v3.0.0 -m "Release 3.0.0 - Shopping cart, payment integration"

# 5. Develop-ə geri merge
git checkout develop
git merge --no-ff release/3.0.0

# 6. Təmizlik
git branch -d release/3.0.0
git push origin main develop --tags
```

### Nümunə 3: Hotfix Prosesi

```bash
# Production-da kritik bug!
# 1. Hotfix branch (main-dən)
git checkout main
git checkout -b hotfix/3.0.1

# 2. Bug fix
git commit -am "fix: prevent SQL injection in search query"

# 3. Main-ə merge və tag
git checkout main
git merge --no-ff hotfix/3.0.1
git tag -a v3.0.1 -m "Hotfix: SQL injection fix"

# 4. Develop-ə də merge
git checkout develop
git merge --no-ff hotfix/3.0.1

# 5. Əgər release branch varsa, ora da merge
git checkout release/3.1.0
git merge --no-ff hotfix/3.0.1

# 6. Təmizlik
git branch -d hotfix/3.0.1
git push origin main develop --tags
```

## Vizual İzah (Visual Explanation)

### Tam GitFlow Diaqramı

```
main     ●───────────────────────●────────────────●───────●──
         │                       ↑                ↑       ↑
         │                  merge+tag          merge+tag  merge+tag
         │                  v1.0.0             v1.1.0    v1.1.1
         │                       │                │       │
hotfix   │                       │                │   ●───●
         │                       │                │   ↑
         │                       │                │   │(main-dən)
         │                       │                │   │
release  │                  ●──●─┘           ●──●─┘   │
         │                  ↑                ↑        │
         │             (develop-dən)    (develop-dən) │
         │                  │                │        │
develop  ●──●───●───●───●──●──●──●───●──●──●──●──●──●──
            │       ↑   │      ↑  │       ↑
            │       │   │      │  │       │
feature     ●───●───┘   ●──●──┘  ●───●───┘
           /cart    /payment     /search
```

### Branch Yaşam Dövrü

```
Feature Branch:
  develop ──→ feature/* ──→ develop
  Müddət: günlər/həftələr
  Kimdən: Developer

Release Branch:
  develop ──→ release/* ──→ main + develop
  Müddət: günlər (test/fix üçün)
  Kimdən: Release manager

Hotfix Branch:
  main ──→ hotfix/* ──→ main + develop
  Müddət: saatlar/günlər
  Kimdən: On-call developer
```

### GitFlow Decision Tree

```
┌─────────────────────────────┐
│ Nə etmək istəyirsiniz?     │
└──────────┬──────────────────┘
           ├── Yeni xüsusiyyət ──→ feature/* (develop-dən)
           ├── Release hazırlığı ──→ release/* (develop-dən)
           ├── Production bug fix ──→ hotfix/* (main-dən)
           └── Kiçik develop fix ──→ develop-ə birbaşa commit
```

## PHP/Laravel Layihələrdə İstifadə

### Laravel Layihəsi üçün GitFlow Setup

```bash
# 1. Repository init
git flow init -d  # Default-ları qəbul et

# 2. Branch protection (GitHub/GitLab-da):
#    main: protected, require PR, require CI pass
#    develop: protected, require PR

# 3. Feature branch nümunəsi
git flow feature start user-roles

# Laravel işləri
php artisan make:model Role -mfc
php artisan make:middleware CheckRole
php artisan make:policy RolePolicy

git add .
git commit -m "feat: add Role model, migration, factory, controller"

# Test yaz
php artisan make:test RoleManagementTest

git add .
git commit -m "test: add role management tests"

git flow feature finish user-roles
```

### Release Prosesində Laravel

```bash
git flow release start 2.0.0

# 1. Version yenilə
# config/app.php
sed -i "s/'version' => '.*'/'version' => '2.0.0'/" config/app.php

# 2. Cache təmizlə
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# 3. Son testlər
php artisan test
composer run phpstan

# 4. CHANGELOG yenilə
git add .
git commit -m "chore: prepare release 2.0.0"

git flow release finish 2.0.0
```

### Hotfix Nümunəsi

```bash
git flow hotfix start 2.0.1

# Bug fix
# app/Http/Controllers/PaymentController.php
git add .
git commit -m "fix: handle null response from payment gateway"

# Test əlavə et
git add tests/
git commit -m "test: add test for null payment response"

# Hotfix bitir
git flow hotfix finish 2.0.1
```

### CI/CD Pipeline (GitFlow ilə)

```yaml
# .github/workflows/gitflow.yml
name: GitFlow CI/CD

on:
  push:
    branches: [main, develop, 'release/**', 'hotfix/**']
  pull_request:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: php artisan test
      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse

  deploy-staging:
    needs: test
    if: github.ref == 'refs/heads/develop'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to staging
        run: echo "Deploy to staging server"

  deploy-production:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to production
        run: echo "Deploy to production server"
```

## Interview Sualları

### S1: GitFlow nədir və hansı branch-lərdən ibarətdir?

**Cavab**: GitFlow, Vincent Driessen-in branch idarəetmə modelidir. 5 branch tipindən ibarətdir:
- **main**: Production kodu, hər commit tag-lanır
- **develop**: İnteqrasiya branch-i, bütün feature-lar buraya merge olunur
- **feature/**: Yeni xüsusiyyətlər üçün, develop-dən açılır, develop-ə merge olunur
- **release/**: Release hazırlığı üçün, develop-dən açılır, main və develop-ə merge olunur
- **hotfix/**: Təcili production fix üçün, main-dən açılır, main və develop-ə merge olunur

### S2: `--no-ff` flag-ı niyə vacibdir GitFlow-da?

**Cavab**: `--no-ff` (no fast-forward) həmişə merge commit yaradır, hətta fast-forward mümkün olsa belə. Bu, branch-in mövcudluğunu tarixçədə saxlayır. GitFlow-da feature, release və hotfix branch-lərinin hər biri ayrıca merge commit ilə görünür və bu, "bu feature nə zaman merge olundu?" sualına cavab verməyi asanlaşdırır.

### S3: Hotfix branch-i niyə main-dən açılır, develop-dən yox?

**Cavab**: Çünki hotfix production-dakı bug-ı düzəldir. Production kodu main branch-dədir, develop isə hələ release olunmamış dəyişikliklər saxlayır. Hotfix main-dən açılır, düzəldilir, sonra həm main-ə (production üçün) həm develop-ə (gələcək release-ə) merge olunur.

### S4: GitFlow-un mənfi cəhətləri nələrdir?

**Cavab**:
- **Mürəkkəblik**: Çox branch var, yeni developer-lər üçün çaşdırıcıdır
- **Yavaş delivery**: Feature → develop → release → main uzun yoldur
- **Merge conflict-lər**: Uzun ömürlü branch-lər çox konflikt yaradır
- **CI/CD-yə uyğun deyil**: Continuous deployment üçün çox ağırdır
- **Overhead**: Kiçik komandalar və layihələr üçün həddən artıq prosesdir

### S5: GitFlow nə zaman uyğundur, nə zaman deyil?

**Cavab**:
**Uyğundur:**
- Planlı release dövrləri olan layihələr (ayda bir release)
- Eyni anda bir neçə versiya dəstəklənən layihələr
- Böyük komandalar (10+ developer)
- Enterprise/bank proqramları

**Uyğun deyil:**
- Continuous deployment edən layihələr
- Kiçik komandalar (2-5 developer)
- SaaS layihələr (tək versiya)
- Sürətli iteration tələb edən startuplar

### S6: Release branch-ində nə edilir?

**Cavab**: Release branch-ində:
1. Version nömrəsini yeniləmək
2. Son bug fix-lər (yalnız kritik)
3. Dokumentasiya yeniləmələri
4. CHANGELOG yazmaq
5. QA/test prosesi

Yeni feature əlavə edilmir. Yalnız release hazırlığı üçün işlər görülür.

### S7: Eyni vaxtda iki feature branch develop-ə merge olunsa nə baş verir?

**Cavab**: Hər feature branch ayrıca develop-ə merge olunur (`--no-ff` ilə). Əgər eyni faylları dəyişiblərsə, ikinci merge zamanı konflikt yaranacaq. Bunu azaltmaq üçün feature branch-ləri qısa ömürlü saxlamaq və develop-i tez-tez feature branch-ə merge etmək lazımdır.

## Best Practices

### 1. Branch Adlandırma

```
feature/JIRA-123-user-authentication
feature/add-payment-gateway
release/2.1.0
hotfix/2.1.1-fix-login
```

### 2. Merge Strategiyası

```bash
# Həmişə --no-ff istifadə edin
git merge --no-ff feature/shopping-cart

# Feature branch-ləri squash etməyin (tarix itir)
# Merge commit feature-ın başlanğıc və bitişini göstərir
```

### 3. Develop-i Aktual Saxlayın

```bash
# Feature branch-da işləyərkən
git checkout develop
git pull origin develop
git checkout feature/my-feature
git merge develop  # Tez-tez sync edin
```

### 4. Release Branch-i Qısa Saxlayın

```
✅ Yaxşı: Release branch 1-3 gün yaşayır
❌ Pis: Release branch 2 həftə açıq qalır

Uzun release = çox konflikt + develop bloklanır
```

### 5. Hotfix-i Həmişə İki Yerə Merge Edin

```bash
# UNUTMAYIN: hotfix həm main-ə, həm develop-ə merge olmalıdır
# Əks halda develop-də bug qalacaq

git checkout main
git merge --no-ff hotfix/critical-fix
git checkout develop
git merge --no-ff hotfix/critical-fix
```

### 6. GitFlow Alternativlərinə Baxın

```
Layihə tipi          → Tövsiyə olunan workflow
──────────────────────────────────────────────
SaaS, startup        → GitHub Flow / Trunk-Based
Enterprise, bank     → GitFlow
Açıq mənbə          → GitFlow (versioned releases)
Mobil tətbiq         → GitFlow (app store releases)
Microservice         → Trunk-Based Development
```
