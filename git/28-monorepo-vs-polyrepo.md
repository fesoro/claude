# Monorepo vs Polyrepo (Lead)

## İcmal

**Monorepo** - bütün layihələr (frontend, backend, mobile, kitabxanalar) tək bir repozitoriyada saxlanılır.
**Polyrepo** (multi-repo) - hər layihə/servis öz ayrı repozitoriyasında saxlanılır.

```
MONOREPO                          POLYREPO
┌─────────────────────┐           ┌──────────┐ ┌──────────┐ ┌──────────┐
│ my-company/         │           │ frontend │ │ backend  │ │ mobile   │
│ ├── frontend/       │           │ (repo 1) │ │ (repo 2) │ │ (repo 3) │
│ ├── backend/        │           └──────────┘ └──────────┘ └──────────┘
│ ├── mobile/         │           ┌──────────┐ ┌──────────┐
│ ├── shared-lib/     │           │ shared   │ │ docs     │
│ └── docs/           │           │ (repo 4) │ │ (repo 5) │
└─────────────────────┘           └──────────┘ └──────────┘
   Bir git repo                       Çoxlu git repo
```

### Real-World Nümunələr

```
┌─────────────────────────────────────────────────────────┐
│ Monorepo Istifadə Edənlər:                             │
│ • Google       - 2B+ sətir kod, tək repo (Piper)       │
│ • Facebook/Meta - Mercurial monorepo                    │
│ • Microsoft    - Windows (270GB repo!)                  │
│ • Twitter      - Scala monorepo                         │
│ • Uber         - Go services monorepo                   │
├─────────────────────────────────────────────────────────┤
│ Polyrepo Istifadə Edənlər:                             │
│ • Netflix      - Mikroservis başına repo               │
│ • Amazon       - Hər team öz repo-su                   │
│ • Spotify      - "Squad" başına repo                    │
└─────────────────────────────────────────────────────────┘
```

## Niyə Vacibdir

Şirkətin repo strukturu qərarı onboarding sürətini, CI/CD pipeline mürəkkəbliyini, dependency management-i kökündən dəyişir. Lead developer bu qərarı verir; yanlış seçim texniki borcun ən böyük mənbəyinə çevrilə bilər.

## Əsas Əmrlər (Key Commands)

### Monorepo Tools

```bash
# Nx (Nrwl) - JS/TS monorepo
npx create-nx-workspace@latest
nx build my-app
nx test my-lib
nx affected:test              # Yalnız dəyişmiş layihələri test et
nx graph                      # Dependency qrafını göstər

# Turborepo (Vercel)
npx create-turbo@latest
turbo run build
turbo run test --filter=web
turbo prune --scope=admin     # Yalnız admin-ə lazım olanları saxla

# Lerna (JS monorepo)
npx lerna init
lerna bootstrap               # Link paketlər
lerna publish                 # Dəyişmiş paketləri yayımla
lerna run test                # Hamısında test işlət

# Rush (Microsoft)
rush install
rush build
rush publish

# Bazel (Google)
bazel build //my-app:all
bazel test //my-lib:test

# Pants (Twitter)
pants test ::
pants package backend:deploy
```

### Polyrepo Tools

```bash
# Git submodules
git submodule add https://github.com/user/lib.git libs/lib
git submodule update --init --recursive

# Git subtree
git subtree add --prefix=libs/lib https://github.com/user/lib.git main

# Meta (meta-repo)
meta init
meta project add libs/frontend
meta exec 'git pull'          # Bütün repo-larda işlət

# Gita (git multi-repo)
gita add .
gita ll                       # Bütün repo-ların statusu
gita pull
```

## Nümunələr

### 1. Monorepo Strukturu (Nx + Laravel + Vue)

```bash
# Layihə strukturu
my-company/
├── apps/
│   ├── api/                  # Laravel API
│   │   ├── app/
│   │   ├── composer.json
│   │   └── routes/
│   ├── admin/                # Vue admin panel
│   │   ├── src/
│   │   └── package.json
│   └── website/              # Next.js public site
│       └── src/
├── libs/
│   ├── ui-components/        # Paylaşılan UI
│   ├── api-client/           # TypeScript API client
│   └── shared-types/         # Paylaşılan tiplər
├── tools/
│   └── scripts/
├── package.json              # Root
├── nx.json
└── tsconfig.base.json
```

```bash
# Əmrlər
nx serve api                  # Laravel serve
nx serve admin                # Vue serve
nx build website
nx test api-client
nx affected:build --base=main # Yalnız dəyişən layihələr
```

### 2. Polyrepo Strukturu

```bash
# Ayrı repo-lar
github.com/mycompany/api              (Laravel)
github.com/mycompany/admin            (Vue)
github.com/mycompany/website          (Next.js)
github.com/mycompany/ui-components    (Paylaşılan UI)
github.com/mycompany/api-client       (API client)

# UI Components-i package manager ilə paylaş
# Admin package.json:
{
  "dependencies": {
    "@mycompany/ui-components": "^2.3.0",
    "@mycompany/api-client": "^1.5.0"
  }
}

# Yerli development üçün npm link
cd ui-components
npm link
cd ../admin
npm link @mycompany/ui-components
```

### 3. Turborepo ilə Laravel + Vue Monorepo

```json
// package.json (root)
{
  "name": "my-company",
  "private": true,
  "workspaces": ["apps/*", "libs/*"],
  "scripts": {
    "build": "turbo run build",
    "test": "turbo run test",
    "dev": "turbo run dev --parallel"
  },
  "devDependencies": {
    "turbo": "latest"
  }
}
```

```json
// turbo.json
{
  "pipeline": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": ["dist/**", "build/**"]
    },
    "test": {
      "dependsOn": ["build"],
      "outputs": []
    },
    "dev": {
      "cache": false,
      "persistent": true
    }
  }
}
```

### 4. Monorepo CI/CD (GitHub Actions)

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  affected:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0       # Monorepo üçün lazım!

      - uses: actions/setup-node@v4
        with:
          node-version: 20

      - run: npm ci

      # Yalnız dəyişmiş layihələri test et
      - run: npx nx affected:test --base=origin/main
      - run: npx nx affected:build --base=origin/main
      - run: npx nx affected:lint --base=origin/main
```

### 5. Polyrepo CI/CD

```yaml
# api/.github/workflows/ci.yml (Laravel repo-da)
name: Laravel CI

on:
  push:
    branches: [main, develop]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - run: composer install
      - run: php artisan test

  deploy:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - run: echo "Deploy API"
```

### 6. Shared Package - Monorepo-da

```typescript
// libs/api-client/src/index.ts
export class ApiClient {
  constructor(private baseUrl: string) {}

  async getUsers() {
    return fetch(`${this.baseUrl}/users`).then(r => r.json());
  }
}
```

```json
// libs/api-client/package.json
{
  "name": "@mycompany/api-client",
  "version": "1.0.0",
  "main": "src/index.ts"
}
```

```typescript
// apps/admin/src/App.vue-də istifadə
import { ApiClient } from '@mycompany/api-client';
const client = new ApiClient('/api');
```

### 7. Dependency Management

```bash
# MONOREPO: Tək package.json və ya workspaces
npm install react         # Bütün layihələr eyni versiya

# POLYREPO: Hər repo-da ayrı
cd api && composer install
cd ../admin && npm install
cd ../website && npm install
# Hər biri fərqli versiya ola bilər!
```

## Vizual İzah (Visual Explanation)

### Monorepo Struktur

```
my-company/
│
├── apps/                            ← Tətbiqlər
│   ├── api (Laravel)
│   ├── admin (Vue)
│   └── website (Next.js)
│
├── libs/                            ← Paylaşılan kitabxanalar
│   ├── ui-components  ◄─────┐
│   ├── api-client     ◄──┐  │
│   └── shared-types      │  │
│                         │  │
├── tools/                 │  │      ← Build scripts
└── .github/workflows/     │  │
                           │  │
    apps/admin ────────────┴──┘
    apps/website ──────────┘
    (libs-dən istifadə)

Üstünlük: Atomik dəyişikliklər (UI dəyişikliyi + istifadəçi eyni PR-da)
```

### Polyrepo Struktur

```
github.com/mycompany/
│
├── api (repo 1)              ─────┐
├── admin (repo 2)            ─────┤
├── website (repo 3)          ─────┤   Ayrı repo-lar
├── ui-components (repo 4)    ◄────┤
└── api-client (repo 5)       ◄────┘

ui-components → npm publish → apps-də npm install

Üstünlük: Təcridlik, ayrı CI/CD, ayrı icazələr
```

### CI Performansı Müqayisəsi

```
MONOREPO (1000 layihə, 1 fayl dəyişib):
┌───────────────────────────────────────┐
│ Pis: Bütün 1000 layihəni test et      │ ← 2 saat
│ Yaxşı: Yalnız təsirlənənləri (nx)    │ ← 5 dəqiqə
└───────────────────────────────────────┘

POLYREPO (1 repo dəyişib):
┌───────────────────────────────────────┐
│ Yalnız o repo-nun CI-sı işləyir       │ ← 3 dəqiqə
│ Amma: dependent repo-lar manual update │
└───────────────────────────────────────┘
```

### Dependency Grafı

```
MONOREPO:                     POLYREPO:
                              
   admin ──┐                     admin ──── npm ──── ui-comp@2.1
           │                                    
   website─┤──> ui-components    website ── npm ──── ui-comp@2.0 (köhnə!)
           │                                    
   api ────┘                     api ─────── npm ──── ui-comp@1.9 (lap köhnə)
                                 
   Hamısı eyni versiyada         Fərqli versiyalar, nəqlə
   anında yenilənir              uyğunsuzluq riski
```

## Praktik Baxış

### 1. Laravel + Vue Monorepo (Turborepo)

```bash
# Struktur
laravel-monorepo/
├── apps/
│   ├── api/                 # Laravel API
│   │   ├── app/
│   │   ├── composer.json
│   │   └── artisan
│   └── admin/               # Vue SPA
│       ├── src/
│       └── package.json
├── packages/
│   ├── api-client/          # TS client
│   └── shared/              # Paylaşılan
└── turbo.json
```

```bash
# Setup
cd laravel-monorepo
npm install

# Dev mode (ikisi də eyni zamanda)
turbo run dev --parallel

# Build
turbo run build

# Test (affected)
turbo run test --filter=...[origin/main]
```

### 2. Laravel Mikroservis Polyrepo

```
github.com/mycompany/
├── auth-service           (Laravel API)
├── payment-service        (Laravel API)
├── notification-service   (Laravel API)
├── admin-dashboard        (Vue)
└── shared-php-lib         (Composer package)

# shared-php-lib-i istifadə etmək üçün:
# auth-service/composer.json
{
  "require": {
    "mycompany/shared-php-lib": "^1.0"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/mycompany/shared-php-lib"
    }
  ]
}
```

### 3. Laravel + Packages (composer monorepo)

```bash
# composer.json - root
{
  "name": "mycompany/monorepo",
  "require": {
    "php": "^8.2",
    "laravel/framework": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Packages\\Auth\\": "packages/auth/src/",
      "Packages\\Billing\\": "packages/billing/src/"
    }
  },
  "repositories": [
    { "type": "path", "url": "packages/*" }
  ]
}

# Struktur
my-laravel/
├── app/                     # Main app
├── packages/
│   ├── auth/
│   │   ├── src/
│   │   └── composer.json    # Local package
│   └── billing/
│       ├── src/
│       └── composer.json
└── composer.json
```

### 4. Monorepo-da Laravel Shared Config

```php
// packages/shared/src/Config.php
namespace Mycompany\Shared;

class Config
{
    public static function apiUrl(): string
    {
        return env('API_URL', 'http://localhost:8000');
    }
}
```

```php
// apps/api/app/Http/Controllers/SomeController.php
use Mycompany\Shared\Config;

public function index()
{
    return Http::get(Config::apiUrl() . '/endpoint');
}
```

### 5. CI - Laravel Polyrepo ilə Shared Library

```yaml
# shared-php-lib repo-sunda:
name: Publish

on:
  push:
    tags: ['v*']

jobs:
  publish:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer validate
      # Packagist avtomatik yenilənir

# auth-service repo-sunda:
# composer update mycompany/shared-php-lib
```

## Praktik Tapşırıqlar

1. **Monorepo-da shared library**
   ```
   company-mono/
   ├── packages/
   │   └── shared-auth/        ← paylaşılan kod
   ├── services/
   │   ├── api-gateway/
   │   └── payment-service/
   └── composer.json           ← path repositories
   ```
   ```json
   // composer.json (api-gateway)
   "repositories": [
     {"type": "path", "url": "../../packages/shared-auth"}
   ]
   ```

2. **CI-da path-based trigger**
   ```yaml
   # .github/workflows/payment.yml
   on:
     push:
       paths:
         - 'services/payment-service/**'
         - 'packages/shared-auth/**'
   ```

3. **Polyrepo-da cross-repo dependency matrix**
   ```
   services:
     api-gateway:    shared-auth@^1.2
     payment:        shared-auth@^1.2, stripe-sdk@^3.0
   packages:
     shared-auth:    laravel/framework@^11.0
   ```
   - Version matrix-i idarə etmək üçün spreadsheet aç

4. **Qərar framework-u tətbiq et**
   - Team ölçüsü?
   - Service-lər nə qədər bir-birinə bağlıdır?
   - CI/CD infrastructure-u nə qədər güclüdür?
   - Cavablara əsasən monorepo vs polyrepo seç

## Interview Sualları

### Q1: Monorepo nədir və üstünlükləri nələrdir?
**Cavab:** Monorepo bütün kod bazasını tək repo-da saxlayır. Üstünlükləri:
1. **Atomik dəyişikliklər** - şared lib + istifadəçi eyni PR-da
2. **Asan refactoring** - bütün codebase-də axtar/dəyişdir
3. **Paylaşılan tooling** - tək CI/CD, tək linting config
4. **Anında visibility** - kim hansı kodu istifadə edir görürsən
5. **Dependency sync** - bütün layihələr eyni versiyada

### Q2: Polyrepo-nun üstünlükləri nələrdir?
**Cavab:**
1. **Təcridlik** - servislər müstəqil deploy
2. **Access control** - hər team öz repo-sunda
3. **Texnologiya müxtəlifliyi** - hər repo öz stack-ı
4. **CI sürəti** - kiçik repo = tez CI
5. **Git performansı** - kiçik history
6. **Açıq mənbə dostu** - kitabxanaları asanlıqla ayırırsan

### Q3: Google niyə monorepo istifadə edir?
**Cavab:** Google Piper adlı daxili monorepo istifadə edir (2B+ sətir). Səbəblər:
- Atomik dəyişikliklər milyardlarla sətir boyu
- Mərkəzləşmiş dependency management (YEK versiya)
- Mass refactoring asan
- "Trunk-based development" standardı
Amma: xüsusi tooling lazımdır (Piper, Bazel, CitC).

### Q4: Monorepo tool-ları hansılardır?
**Cavab:**
- **Nx** - JS/TS, affected builds, generators
- **Turborepo** - Vercel, sadə, cache
- **Lerna** - JS paketləri, npm publish
- **Rush** - Microsoft, enterprise
- **Bazel** - Google, çox dil (Go, Java, Python)
- **Pants** - Twitter, multi-language

### Q5: Monorepo-da CI/CD necə optimallaşdırılır?
**Cavab:**
1. **Affected builds** - yalnız dəyişmiş layihələri build et (nx affected)
2. **Remote caching** - Turborepo/Nx Cloud
3. **Parallelization** - layihələri paralel işlət
4. **Shallow clone** - CI-da tam history lazım deyil
5. **Incremental builds** - yalnız dəyişmiş modulları yenidən build

### Q6: Monorepo-nun çatışmazlıqları nələrdir?
**Cavab:**
1. **Git performansı** - böyük repo = yavaş git status, log
2. **Tooling mürəkkəbliyi** - Bazel, Nx öyrənmək lazım
3. **Access control çətinliyi** - hamı hər şeyi görür
4. **Clone ölçüsü** - yeni developer üçün yavaş
5. **CI pipeline mürəkkəbliyi** - affected builds setup

### Q7: Polyrepo-da shared code necə paylaşılır?
**Cavab:**
- **Package manager** - npm, Composer (Packagist)
- **Git submodules** - nested repo-lar
- **Git subtree** - alternativ submodule-a
- **Private registry** - npm Enterprise, GitHub Packages
- **Mono-on-poly** - meta tools (meta, gita)

### Q8: Monorepo-da böyük CI/CD problemini necə həll edirsən?
**Cavab:**
```bash
# 1. Affected (dəyişən layihələr)
nx affected:test --base=main

# 2. Distributed caching
nx connect-to-nx-cloud

# 3. Parallel execution
turbo run build --parallel

# 4. Conditional workflows
# .github/workflows/api.yml:
on:
  push:
    paths: ['apps/api/**']
```

### Q9: Microsoft Windows repo-su niyə vacib?
**Cavab:** Windows repo 270GB, 3.5M fayl ilə dünyanın ən böyük monorepo-larından biri. Microsoft VFS for Git (Virtual File System) yaratdı - yalnız lazım olan faylları yükləyir. Git partial clone bu problemi həll etmək üçün gəldi.

### Q10: Monorepo və polyrepo arasında necə seçim edirsən?
**Cavab:** Faktorlara baxırsan:
- **Komanda ölçüsü** - 5 dev → polyrepo OK, 500 dev → monorepo daha yaxşı
- **Kod paylaşma ehtiyacı** - çox shared code → monorepo
- **Texnologiya müxtəlifliyi** - çox fərqli stack → polyrepo
- **Deploy avtonomluğu** - mikroservislər → polyrepo
- **Mövcud tooling** - Nx/Turborepo varmı?
- **Layihə yetkinliyi** - startup → polyrepo daha asan

## Best Practices

### 1. Monorepo-da Strukturu Düzgün Qur
```
my-monorepo/
├── apps/          # Endpoint tətbiqlər
├── libs/          # Paylaşılan kitabxanalar
├── tools/         # Build/dev scripts
└── docs/          # Sənədləşdirmə
```

### 2. Affected Builds İstifadə Et
```bash
# Yalnız dəyişmiş layihələri build et
nx affected:build --base=origin/main --parallel=3

# CI-da vaxt qənaət edir (5dəq əvəzinə 2saat)
```

### 3. Remote Caching Quraşdır
```bash
# Nx Cloud
nx connect-to-nx-cloud

# Turborepo
npx turbo login
npx turbo link
```

### 4. Ortaq Dependency Versiyası
```json
// monorepo/package.json
{
  "workspaces": ["apps/*", "libs/*"]
}
// Hamısı eyni React versiyası
```

### 5. Polyrepo-da Shared Lib-ləri Publish Et
```bash
# Kiçik, fokuslu paketlər
# npm publish və ya composer Packagist
# Semantic versioning istifadə et
```

### 6. Code Ownership Fayl
```
# monorepo/.github/CODEOWNERS
apps/api/       @backend-team
apps/admin/     @frontend-team
libs/ui/        @design-system-team
```

### 7. Atomik Commit-lər
```bash
# Monorepo üstünlüyü:
# Bir PR-da: libs/api-client + apps/admin dəyişiklik
git add libs/api-client/ apps/admin/
git commit -m "feat(api): add user endpoint and use in admin"
```

### 8. Branch Protection
```yaml
# GitHub
- main branch: PR tələb edilir
- required checks: lint, test, build
- CODEOWNERS review tələb olunur
```

### 9. Dependency Boundaries (Nx)
```json
// nx.json
{
  "implicitDependencies": {
    "package.json": {
      "dependencies": "*"
    }
  }
}
// apps/admin libs/ui-i istifadə edə bilər
// libs/ui apps/admin-i istifadə EDƏ BİLMƏZ
```

### 10. Documentation
```
monorepo/
├── README.md              # Ümumi start
├── CONTRIBUTING.md        # Necə contribute
├── apps/api/README.md     # Hər app-in öz docs-u
└── docs/
    ├── architecture.md
    └── deployment.md
```

## Əlaqəli Mövzular

- [19-git-submodules.md](19-git-submodules.md) — polyrepo-da paylaşma
- [30-git-performance-large-repos.md](30-git-performance-large-repos.md) — monorepo performansı
- [22-git-workflow-team.md](22-git-workflow-team.md) — komanda workflow-u
