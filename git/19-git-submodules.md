# Git Submodules (Senior)

## İcmal

Git submodule, bir Git repository-ni başqa bir Git repository-nin daxilində saxlamaq mexanizmidir. Ana repo (parent) alt repo-nun (submodule) müəyyən bir commit-inə referans saxlayır. Bu, paylaşılan kitabxanaları, SDK-ları, və ya ümumi komponentləri bir neçə layihədə istifadə etmək üçün faydalıdır.

## Niyə Vacibdir

Paylaşılan library-ları, infrastructure kodu, ya da internal package-ları bir neçə repo-da eyni versiyada saxlamaq üçün submodule istifadə olunur. Alternativlər (subtree, package registry) trade-off-larla gəlir; seçim layihənin ölçüsünə görə dəyişir. Laravel layihələrində Composer private package daha yaxşı həll olsa da, submodule-lar bəzən build scripts, frontend theme-lar və ya infrastructure repo-larının paylaşılmasında tətbiq olunur.

```
Submodule Strukturu:

my-laravel-app/              (Ana repo)
├── app/
├── config/
├── packages/
│   ├── payment-sdk/         (Submodule → github.com/company/payment-sdk)
│   └── shared-helpers/      (Submodule → github.com/company/shared-helpers)
├── .gitmodules              (Submodule konfiqurasiyası)
└── .git/

Ana repo payment-sdk-nın v1.2.0 commit-inə işarə edir.
payment-sdk yeniləndikdə, ana repo AVTOMATİK yenilənmir.
Manual olaraq yeniləmək lazımdır.
```

### Submodule Necə İşləyir?

```
Ana repo commit-ində submodule:

tree abc1234
├── app/          → tree def5678
├── packages/
│   └── payment-sdk → commit 777aaaa  ← Bu sadəcə commit hash-dir
├── .gitmodules   → blob fff0000
└── README.md     → blob eee1111

.gitmodules faylı:
[submodule "packages/payment-sdk"]
    path = packages/payment-sdk
    url = https://github.com/company/payment-sdk.git
```

## Əsas Əmrlər (Key Commands)

### Submodule Əlavə Etmə

```bash
# Submodule əlavə et
git submodule add https://github.com/company/payment-sdk.git packages/payment-sdk

# Spesifik branch ilə
git submodule add -b main https://github.com/company/payment-sdk.git packages/payment-sdk

# Nəticə: .gitmodules faylı yaranır və packages/payment-sdk əlavə olunur
git commit -m "chore: add payment-sdk submodule"
```

### Submodule ilə Clone

```bash
# Clone zamanı submodule-ları da çək
git clone --recurse-submodules https://github.com/company/my-app.git

# Əgər artıq clone edibsinizsə
git submodule init
git submodule update

# və ya bir əmrlə
git submodule update --init --recursive
```

### Submodule Yeniləmə

```bash
# Submodule-u remote-un son versiyasına yenilə
cd packages/payment-sdk
git fetch
git checkout v1.3.0   # və ya git pull origin main
cd ../..

# Ana repo-da commit et (yeni submodule referansı)
git add packages/payment-sdk
git commit -m "chore: update payment-sdk to v1.3.0"

# Və ya bir əmrlə
git submodule update --remote packages/payment-sdk
git add packages/payment-sdk
git commit -m "chore: update payment-sdk to latest"

# Bütün submodule-ları yenilə
git submodule update --remote
```

### Submodule Silmə

```bash
# 1. .gitmodules faylından bölməni silin
git config -f .gitmodules --remove-section submodule.packages/payment-sdk

# 2. .git/config-dən silin
git config --remove-section submodule.packages/payment-sdk

# 3. Staging-dən silin
git rm --cached packages/payment-sdk

# 4. Fayl sistemindən silin
rm -rf packages/payment-sdk
rm -rf .git/modules/packages/payment-sdk

# 5. Commit edin
git commit -m "chore: remove payment-sdk submodule"

# Alternativ (Git 2.29+, daha sadə):
git rm packages/payment-sdk
rm -rf .git/modules/packages/payment-sdk
git commit -m "chore: remove payment-sdk submodule"
```

### Digər Əmrlər

```bash
# Bütün submodule-ların statusu
git submodule status

# Hər submodule-da əmr işlət
git submodule foreach 'git pull origin main'
git submodule foreach 'git status'

# Submodule diff-i göstər
git diff --submodule

# Submodule summary
git submodule summary
```

## Nümunələr

### Nümunə 1: Paylaşılan Laravel Package

```bash
# Şirkətin paylaşılan package-ı var: company/laravel-helpers
# Bir neçə layihə istifadə edir

# Layihə 1-ə əlavə et
cd project-1
git submodule add https://github.com/company/laravel-helpers.git packages/laravel-helpers

# composer.json-da local path repository əlavə edin
```

```json
// composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/laravel-helpers"
        }
    ],
    "require": {
        "company/laravel-helpers": "*@dev"
    }
}
```

```bash
composer update company/laravel-helpers
git add .gitmodules packages/laravel-helpers composer.json composer.lock
git commit -m "chore: add laravel-helpers submodule"
```

### Nümunə 2: CI/CD ilə Submodule

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          submodules: recursive  # Submodule-ları da checkout et
          token: ${{ secrets.PAT_TOKEN }}  # Private repo üçün

      - name: Install dependencies
        run: composer install

      - name: Run tests
        run: php artisan test
```

### Nümunə 3: Submodule-u Spesifik Tag-da Saxlamaq

```bash
# Submodule-u spesifik versiyaya pin edin
cd packages/payment-sdk
git fetch --tags
git checkout v2.1.0
cd ../..

git add packages/payment-sdk
git commit -m "chore: pin payment-sdk to v2.1.0"

# Bu, stabillik təmin edir - submodule yalnız
# siz istədikdə yenilənir
```

### Nümunə 4: Submodule-da Dəyişiklik Etmə

```bash
# Submodule-da dəyişiklik edin
cd packages/payment-sdk

# Yeni branch yaradın
git checkout -b fix/timeout-issue

# Dəyişiklik edin
git add .
git commit -m "fix: increase timeout to 30s"
git push origin fix/timeout-issue

# PR yaradın payment-sdk repo-sunda
# PR merge olduqdan sonra:
git checkout main
git pull origin main

# Ana repo-ya qayıdın
cd ../..
git add packages/payment-sdk
git commit -m "chore: update payment-sdk with timeout fix"
git push
```

## Vizual İzah (Visual Explanation)

### Submodule Referans Mexanizmi

```
Ana Repo (my-app):
  commit A ──→ payment-sdk @ commit X
  commit B ──→ payment-sdk @ commit X  (dəyişməyib)
  commit C ──→ payment-sdk @ commit Y  (yeniləndi!)
  commit D ──→ payment-sdk @ commit Y

Payment-SDK Repo:
  commit W ── commit X ── commit Y ── commit Z
                ↑              ↑
           my-app@A,B     my-app@C,D

Ana repo payment-sdk-nın commit hash-ini saxlayır.
payment-sdk-da yeni commit-lər olsa belə,
ana repo yalnız göstərilən commit-i istifadə edir.
```

### Clone Prosesi

```
git clone --recurse-submodules url

┌─────────────────┐
│ Ana repo clone   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ .gitmodules oxu  │
│ (submodule list) │
└────────┬────────┘
         │
    ┌────┴────┐
    ▼         ▼
┌────────┐ ┌────────┐
│ Sub 1  │ │ Sub 2  │
│ clone  │ │ clone  │
│+checkout│ │+checkout│
│ to hash│ │ to hash│
└────────┘ └────────┘
```

### Submodule vs Alternatives

```
┌──────────────┬────────────────────────────────────┐
│ Yanaşma      │ Xüsusiyyətlər                      │
├──────────────┼────────────────────────────────────┤
│ Submodule    │ Ayrı repo, ayrı tarixçə            │
│              │ Pin to specific commit               │
│              │ Mürəkkəb workflow                    │
├──────────────┼────────────────────────────────────┤
│ Subtree      │ Kodu ana repo-ya kopyalayır         │
│              │ Tarixçə ana repo-dadır               │
│              │ Daha sadə workflow                   │
├──────────────┼────────────────────────────────────┤
│ Package      │ Composer/npm ilə idarə olunur       │
│ Manager      │ Versioned releases (semver)          │
│              │ Ən təmiz həll                        │
├──────────────┼────────────────────────────────────┤
│ Monorepo     │ Hər şey bir repo-da                 │
│              │ Paylaşma asandır                     │
│              │ Repo böyüyə bilər                    │
└──────────────┴────────────────────────────────────┘
```

## Praktik Baxış

### Paylaşılan Laravel Package (Submodule)

```bash
# Package strukturu
packages/shared-helpers/
├── src/
│   ├── Helpers/
│   │   ├── StringHelper.php
│   │   └── DateHelper.php
│   └── SharedHelpersServiceProvider.php
├── tests/
├── composer.json
└── README.md
```

```json
// packages/shared-helpers/composer.json
{
    "name": "company/shared-helpers",
    "autoload": {
        "psr-4": {
            "Company\\SharedHelpers\\": "src/"
        }
    }
}
```

```json
// Ana layihə composer.json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/shared-helpers",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "company/shared-helpers": "*@dev"
    }
}
```

### Submodule Əvəzinə Composer Package (Alternativ)

```bash
# Private Composer repo (Satis və ya Private Packagist)

# 1. Package repo-sunda tag yaradın
cd payment-sdk
git tag -a v1.2.0 -m "Release 1.2.0"
git push origin --tags

# 2. Ana layihədə composer require
composer require company/payment-sdk:^1.2

# Bu, submodule-dan daha təmiz həlldir!
```

### Git Subtree (Alternativ)

```bash
# Subtree əlavə etmə
git subtree add --prefix=packages/payment-sdk \
    https://github.com/company/payment-sdk.git main --squash

# Subtree yeniləmə
git subtree pull --prefix=packages/payment-sdk \
    https://github.com/company/payment-sdk.git main --squash

# Subtree-yə push (dəyişiklikləri geri göndər)
git subtree push --prefix=packages/payment-sdk \
    https://github.com/company/payment-sdk.git main
```

## Praktik Tapşırıqlar

1. **Submodule əlavə et**
   ```bash
   git submodule add git@github.com:company/shared-config.git config/shared
   git commit -m "chore: add shared-config submodule"
   git push
   ```

2. **Submodule-lu repo-nu clone et**
   ```bash
   git clone --recurse-submodules git@github.com:company/main-app.git
   # ya da mövcud repo-da:
   git submodule update --init --recursive
   ```

3. **Submodule-u yenilə**
   ```bash
   cd config/shared
   git pull origin main
   cd ../..
   git add config/shared
   git commit -m "chore: update shared-config to latest"
   ```

4. **Submodule-u sil (alternativi inline et)**
   ```bash
   git submodule deinit config/shared
   git rm config/shared
   rm -rf .git/modules/config/shared
   git commit -m "chore: inline shared-config"
   ```

## Interview Sualları

### S1: Git submodule nədir?

**Cavab**: Submodule, bir Git repo-sunu başqa bir repo-nun daxilində saxlamaq mexanizmidir. Ana repo submodule-un spesifik commit hash-inə referans saxlayır. Submodule-un öz tarixçəsi, branch-ləri və remote-u var. `.gitmodules` faylında konfiqurasiya saxlanır.

### S2: Submodule-un mənfi cəhətləri nələrdir?

**Cavab**:
- **Mürəkkəblik**: Clone, pull, checkout zamanı əlavə addımlar lazımdır
- **Unutqanlıq**: `git clone` default olaraq submodule-ları çəkmir
- **Versiya sync**: Ana repo ilə submodule arasında versiya uyğunsuzluğu ola bilər
- **CI/CD**: Pipeline-da xüsusi konfiqurasiya lazımdır
- **Yeni developer-lər**: Çaşdırıcı ola bilər

### S3: Submodule alternativləri nələrdir?

**Cavab**:
1. **Package manager** (Composer, npm): Ən yaxşı həll. Versioned, asanlıqla idarə olunur.
2. **Git subtree**: Kodu ana repo-ya kopyalayır, submodule-dan sadədir.
3. **Monorepo**: Hər şey bir repo-da, paylaşma asandır.
4. **Copy-paste**: Kiçik, nadir dəyişən kod üçün ən sadə yol.

Laravel layihəsində Composer package (private Packagist ilə) ən tövsiyə olunan yanaşmadır.

### S4: `git clone` submodule-ları avtomatik çəkirmi?

**Cavab**: Xeyr, default olaraq çəkmir. İki yol var:
```bash
# Clone zamanı
git clone --recurse-submodules url

# Clone-dan sonra
git submodule update --init --recursive
```

### S5: Submodule-u necə yeniləyirsiniz?

**Cavab**:
```bash
# Remote-dan yenilə
git submodule update --remote packages/payment-sdk

# Ana repo-da commit et
git add packages/payment-sdk
git commit -m "chore: update payment-sdk"
```
Submodule avtomatik yenilənmir. Manual olaraq yeniləyib ana repo-da commit etmək lazımdır.

### S6: Submodule-da dəyişiklik edib ana repo-ya necə əks etdirirsiniz?

**Cavab**:
1. Submodule qovluğuna keçin
2. Dəyişiklik edin, commit edin, push edin (submodule repo-suna)
3. Ana repo-ya qayıdın
4. `git add <submodule-path>` (yeni commit hash-i stage edin)
5. Ana repo-da commit edin

### S7: Submodule nə zaman istifadə etmək məntiqlidir?

**Cavab**:
- Böyük dependency ki, package manager ilə idarə olunmur
- Firmware, hardware spec-ləri kimi xarici repo-lar
- Monorepo-ya keçid mümkün deyilsə, lakin kod paylaşımı lazımdırsa
- Documentation repo-ları (docs submodule kimi)

Amma çox vaxt Composer/npm package daha yaxşı həlldir.

## Best Practices

### 1. Submodule Əvəzinə Package Manager İstifadə Edin

```
Laravel layihəsi üçün prioritet sıra:
1. Composer package (Private Packagist / Satis)
2. Git subtree
3. Monorepo
4. Git submodule (son çarə)
```

### 2. `.gitmodules`-u Yoxlayın

```bash
# Submodule URL-ləri düzgün olmalıdır
cat .gitmodules

# SSH əvəzinə HTTPS istifadə edin (CI üçün)
[submodule "packages/sdk"]
    path = packages/sdk
    url = https://github.com/company/sdk.git
```

### 3. CI/CD-də Submodule Checkout

```yaml
# GitHub Actions
- uses: actions/checkout@v4
  with:
    submodules: recursive
    token: ${{ secrets.PAT_TOKEN }}
```

### 4. Submodule-ları Pin Edin

```bash
# Həmişə spesifik commit/tag-a pin edin
cd packages/sdk
git checkout v1.2.0
cd ../..
git add packages/sdk
git commit -m "chore: pin sdk to v1.2.0"
```

### 5. README-də Sənədləşdirin

```markdown
## Setup

Bu layihə submodule istifadə edir:

git clone --recurse-submodules <url>

# Əgər artıq clone edibsinizsə:
git submodule update --init --recursive
```

### 6. post-checkout Hook

```bash
#!/bin/bash
# .git/hooks/post-checkout
# Checkout-dan sonra submodule-ları avtomatik yenilə
git submodule update --init --recursive
```

## Əlaqəli Mövzular

- [20-git-worktrees.md](20-git-worktrees.md) — paralel iş mühiti
- [28-monorepo-vs-polyrepo.md](28-monorepo-vs-polyrepo.md) — repo strukturu qərarları
