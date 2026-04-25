# Git Basics (Junior)

## İcmal

Git, Linus Torvalds tərəfindən 2005-ci ildə yaradılmış **distributed version control system** (paylanmış versiya nəzarət sistemi) dir. Git kodun tarixçəsini izləyir, komanda üzvlərinin eyni vaxtda işləməsinə imkan verir və hər dəyişikliyi geri qaytarmağa şərait yaradır.

## Niyə Vacibdir

Laravel layihəsinə qoşulan hər developer ilk gün `git clone`, `commit`, `log` işlədir. CI/CD pipeline-ları (GitHub Actions, GitLab CI) tamamilə git workflow-a əsaslanır; git-i bilməmək team productivity-ni aşağı salır.

### Centralized vs Distributed VCS

```
Centralized (SVN):                    Distributed (Git):

     ┌──────────┐                    ┌──────────┐
     │  Server  │                    │  Remote  │
     │   Repo   │                    │   Repo   │
     └────┬─────┘                    └────┬─────┘
          │                         ┌─────┼─────┐
     ┌────┼────┐              ┌─────┴──┐  │  ┌──┴─────┐
     │    │    │              │ Full   │  │  │ Full   │
   Dev1 Dev2 Dev3             │ Clone  │  │  │ Clone  │
   (no   (no  (no             │ Dev1   │  │  │ Dev3   │
   local local local          └────────┘  │  └────────┘
   hist) hist) hist)                ┌─────┴──┐
                                    │ Full   │
                                    │ Clone  │
                                    │ Dev2   │
                                    └────────┘
```

**Centralized VCS** (SVN, Perforce): Bir server var, developer-lər yalnız fayl kopyasını alır.
**Distributed VCS** (Git, Mercurial): Hər developer tam repo kopyasına malikdir, offline işləyə bilir.

### Git-in Üstünlükləri

- **Sürətli**: Demək olar ki, bütün əməliyyatlar lokal
- **Offline işləmə**: İnternet olmadan commit, branch, merge edə bilərsiniz
- **Branching ucuzdur**: Branch yaratmaq millisaniyələr çəkir
- **Data bütövlüyü**: Hər şey SHA-1 hash ilə yoxlanılır
- **Staging area**: Commit-ə nə daxil olacağını dəqiq seçə bilərsiniz

## Əsas Anlayışlar (Key Concepts)

### Üç Sahə (Three Areas)

```
  Working Directory          Staging Area           .git Repository
  (İş qovluğu)             (İndeks)               (Lokal repo)
 ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
 │                  │    │                  │    │                  │
 │  Faylları        │    │  Növbəti commit  │    │  Bütün commit    │
 │  redaktə         │───>│  üçün hazırlan-  │───>│  tarixçəsi       │
 │  etdiyiniz       │add │  mış snapshot    │commit burada saxla- │
 │  yer             │    │                  │    │  nılır            │
 │                  │    │                  │    │                  │
 └─────────────────┘    └─────────────────┘    └─────────────────┘
                              git add              git commit
```

1. **Working Directory**: Layihə fayllarınızın olduğu qovluq. Burada faylları yaradır, redaktə edir, silirsiniz.
2. **Staging Area (Index)**: `git add` ilə növbəti commit-ə daxil olacaq dəyişiklikləri seçirsiniz. `.git/index` faylında saxlanılır.
3. **Repository (.git directory)**: Layihənin bütün tarixçəsi, commit-lər, branch-lar və konfiqurasiya burada saxlanılır.

### Fayl Statusları

```
  Untracked ──> Staged ──> Committed ──> Modified ──> Staged ──> ...
      │            │           │             │            │
      │  git add   │ git commit│  edit file  │  git add   │
      ▼            ▼           ▼             ▼            ▼
```

Faylın 4 statusu ola bilər:
- **Untracked**: Git bu faylı izləmir (yeni yaradılmış fayl)
- **Modified**: İzlənilən fayl dəyişdirilib, amma staging area-ya əlavə olunmayıb
- **Staged**: Dəyişiklik növbəti commit-ə daxil olmağa hazırdır
- **Unmodified**: Son commit-dən bəri dəyişiklik yoxdur

## Əsas Əmrlər (Key Commands)

### git init - Yeni Repository Yaratmaq

```bash
# Yeni layihə üçün repo yaratmaq
mkdir my-laravel-app
cd my-laravel-app
git init

# Nəticə: Initialized empty Git repository in /path/my-laravel-app/.git/

# .git qovluğunun strukturu:
ls -la .git/
# HEAD           - Hazırda hansı branch-da olduğunuzu göstərir
# config         - Repo konfiqurasiyası
# hooks/         - Git hook script-ləri
# objects/       - Bütün məzmun (blob, tree, commit)
# refs/          - Branch və tag pointer-ləri
```

### git clone - Mövcud Repository-ni Klonlamaq

```bash
# HTTPS ilə clone
git clone https://github.com/user/laravel-project.git

# SSH ilə clone (daha təhlükəsiz)
git clone git@github.com:user/laravel-project.git

# Xüsusi ada clone
git clone https://github.com/user/project.git my-project

# Yalnız son commit-i clone (sürətli, CI/CD üçün)
git clone --depth 1 https://github.com/laravel/laravel.git

# Xüsusi branch-ı clone
git clone -b develop https://github.com/user/project.git
```

### git status - Repo Vəziyyətini Yoxlamaq

```bash
git status

# Qısa format
git status -s
# M  app/Models/User.php         # Modified, staged
#  M config/app.php               # Modified, not staged
# ?? app/Services/PaymentService.php  # Untracked
# A  app/Http/Controllers/Api/V2/OrderController.php  # New file, staged
# MM routes/api.php               # Staged + further modifications

# Branch məlumatı ilə
git status -sb
# ## feature/payment...origin/feature/payment [ahead 2, behind 1]
```

### git add - Dəyişiklikləri Stage Etmək

```bash
# Tək fayl əlavə et
git add app/Models/User.php

# Birdən çox fayl
git add app/Models/User.php app/Models/Order.php

# Bütün .php faylları
git add "*.php"

# Qovluqdakı hər şey
git add app/Http/Controllers/

# Bütün dəyişiklikləri (yeni, modified, deleted)
git add -A
# və ya
git add --all

# Yalnız izlənilən faylların dəyişikliklərini (yeni fayllar istisna)
git add -u

# İnteraktiv əlavə (hissə-hissə)
git add -p app/Models/User.php
# Hər dəyişiklik bloku üçün soruşacaq:
# y - stage this hunk
# n - skip this hunk
# s - split into smaller hunks
# q - quit
```

### git commit - Dəyişiklikləri Qeyd Etmək

```bash
# Mesaj ilə commit
git commit -m "Add payment processing service"

# Çoxsətirli mesaj
git commit -m "Add payment processing service

- Implement Stripe integration
- Add webhook handling
- Create PaymentService class"

# Stage + commit bir addımda (yalnız tracked files)
git commit -am "Fix user validation bug"

# Boş commit (CI trigger üçün)
git commit --allow-empty -m "Trigger CI pipeline"

# Son commit-i düzəltmək
git commit --amend -m "Fix: Add payment processing service"

# Son commit-ə fayl əlavə etmək (mesajı dəyişmədən)
git add forgotten-file.php
git commit --amend --no-edit
```

### git log - Tarixçəyə Baxmaq

```bash
# Standart log
git log

# Qısa format
git log --oneline
# a1b2c3d Add payment service
# d4e5f6g Fix user model
# h7i8j9k Initial commit

# Qrafik görünüş
git log --oneline --graph --all
# * a1b2c3d (HEAD -> feature/payment) Add payment service
# | * x1y2z3a (develop) Update config
# |/
# * d4e5f6g (main) Fix user model

# Son 5 commit
git log -5

# Müəllif üzrə filter
git log --author="Orkhan"

# Tarix aralığı
git log --since="2024-01-01" --until="2024-02-01"

# Fayla görə tarixçə
git log -- app/Models/User.php

# Dəyişiklik detalları ilə
git log -p

# Statistika ilə
git log --stat
```

## Nümunələr

### Ssenari 1: Yeni Laravel Layihəsini Git ilə Başlatmaq

```bash
# Laravel layihəsi yarat
composer create-project laravel/laravel my-app
cd my-app

# Git artıq init olunub (Laravel bunu edir), amma manual etsək:
git init

# .gitignore artıq mövcuddur (Laravel ilə gəlir)
cat .gitignore
# /node_modules
# /public/hot
# /public/storage
# /storage/*.key
# /vendor
# .env
# ...

# İlk commit
git add -A
git commit -m "Initial commit: Laravel 11 fresh installation"

# Remote əlavə et
git remote add origin git@github.com:orkhan/my-app.git
git push -u origin main
```

### Ssenari 2: Feature Üzərində İşləmək

```bash
# Yeni branch yarat və keç
git checkout -b feature/user-authentication

# Faylları redaktə et
# app/Http/Controllers/AuthController.php
# app/Models/User.php
# routes/web.php

# Status yoxla
git status
# Modified: app/Models/User.php
# New file: app/Http/Controllers/AuthController.php
# Modified: routes/web.php

# Əlaqəli faylları birlikdə stage et
git add app/Http/Controllers/AuthController.php app/Models/User.php
git commit -m "Add AuthController and update User model"

git add routes/web.php
git commit -m "Add authentication routes"
```

### Ssenari 3: Səhvən Stage Edilmiş Faylı Geri Almaq

```bash
# .env faylını səhvən stage etdiniz
git add .env

# Stage-dən çıxar (fayl dəyişikliyi qalır)
git restore --staged .env

# Bütün stage olunmuş dəyişiklikləri geri al
git restore --staged .

# Faylı tamamilə əvvəlki vəziyyətə qaytar (DİQQƏT: dəyişikliklər itir!)
git restore app/Models/User.php
```

## Vizual İzah (Visual Explanation)

### Commit Tarixçəsi

```
  Hər commit valideyn(lər)ə işarə edir:

  C1 <── C2 <── C3 <── C4  (main)
                         │
                         HEAD

  C1: Initial commit
  C2: Add models
  C3: Add controllers
  C4: Add routes (HEAD burada, yəni ən son commit)
```

### git add və git commit Axını

```
  1. Fayl redaktə et:

     Working Dir        Staging         Repository
     ┌──────────┐      ┌──────────┐    ┌──────────┐
     │ User.php │      │          │    │ C1       │
     │ (modified)│      │          │    │          │
     └──────────┘      └──────────┘    └──────────┘

  2. git add User.php:

     Working Dir        Staging         Repository
     ┌──────────┐      ┌──────────┐    ┌──────────┐
     │ User.php │      │ User.php │    │ C1       │
     │ (modified)│      │ (staged) │    │          │
     └──────────┘      └──────────┘    └──────────┘

  3. git commit -m "Update user":

     Working Dir        Staging         Repository
     ┌──────────┐      ┌──────────┐    ┌──────────┐
     │ User.php │      │ (clean)  │    │ C1 <- C2 │
     │ (clean)  │      │          │    │          │
     └──────────┘      └──────────┘    └──────────┘
```

## Praktik Baxış

### Tipik Laravel Workflow

```bash
# 1. Layihəni clone et
git clone git@github.com:company/laravel-app.git
cd laravel-app

# 2. Dependencies qur
composer install
npm install

# 3. Environment qur
cp .env.example .env
php artisan key:generate

# 4. Migration işlət
php artisan migrate

# 5. Feature üzərində işlə
git checkout -b feature/api-v2

# 6. Model, Controller, Migration yarat
php artisan make:model Order -mcr
# Yaradılan fayllar:
#   app/Models/Order.php
#   app/Http/Controllers/OrderController.php
#   database/migrations/2024_01_15_create_orders_table.php

git add app/Models/Order.php \
        app/Http/Controllers/OrderController.php \
        database/migrations/

git commit -m "Add Order model, controller, and migration"
```

### Nə Commit Etməməli

```bash
# Bu fayllar HEÇVAXT commit olunmamalıdır:
.env                 # Gizli məlumatlar (DB password, API keys)
/vendor              # Composer dependencies (composer.json kifayətdir)
/node_modules        # NPM dependencies
storage/logs/*.log   # Log faylları
.phpunit.result.cache
```

## Praktik Tapşırıqlar

1. **Yeni Laravel layihəsi üçün repo hazırla**
   ```bash
   laravel new blog
   cd blog
   git init
   git add .
   git commit -m "feat: initial Laravel setup"
   git log --oneline
   ```

2. **Selective staging məşqi**
   - `app/Models/User.php` faylında dəyişiklik et
   - `app/Http/Controllers/HomeController.php` faylında dəyişiklik et
   - Yalnız User.php-ni stage et, commit et, sonra Controller-i ayrı commit et

3. **git status çıxışını oxu**
   - `??` (untracked), `M` (modified), `A` (added) — hər birini praktikada gör

4. **Commit mesajı qaydası**
   - `feat:`, `fix:`, `docs:` prefixlərini işlət (Conventional Commits preview)

5. **Son commit-i düzəlt**
   ```bash
   git commit --amend -m "feat: correct commit message"
   # DİQQƏT: yalnız push olunmamış commit-lərdə
   ```

## Interview Sualları

### S1: Git nədir və SVN-dən nə ilə fərqlənir?
**Cavab**: Git distributed version control system-dir. SVN-dən əsas fərqi odur ki, Git-də hər developer tam repo kopyasına malikdir, offline işləyə bilir. SVN-də isə bir mərkəzi server var və developer-lər yalnız working copy-yə malikdir. Git-də branching çox sürətli və ucuzdur, SVN-də isə branch bütün qovluğun kopyasıdır.

### S2: Staging area nə üçün lazımdır?
**Cavab**: Staging area (index) commit-ə daxil olacaq dəyişiklikləri seçmək imkanı verir. Məsələn, 5 fayl dəyişdirmisiniz amma yalnız 3-ünü commit etmək istəyirsiniz. `git add` ilə yalnız lazımi faylları stage edib, mənalı commit yarada bilərsiniz. Bu, `git add -p` ilə hətta fayl daxilində hissə seçməyə imkan verir.

### S3: `git add -A`, `git add .` və `git add -u` arasında fərq nədir?
**Cavab**:
- `git add -A` (--all): Bütün dəyişiklikləri stage edir (yeni, modified, deleted) - bütün repo boyunca
- `git add .`: Cari qovluq və alt qovluqlardakı bütün dəyişiklikləri stage edir
- `git add -u`: Yalnız artıq izlənilən faylların dəyişikliklərini stage edir (yeni fayllar daxil deyil)

### S4: `git commit --amend` nə edir və nə vaxt istifadə olunmalıdır?
**Cavab**: Son commit-in mesajını və ya məzmununu dəyişdirir. Yeni commit yaratmır, son commit-i yenidən yazır (SHA dəyişir). Yalnız **push olunmamış** commit-lərə tətbiq olunmalıdır. Push olunmuş commit-i amend etsəniz, force push lazım olacaq ki, bu da komanda üzvlərinə problem yaradır.

### S5: `git clone --depth 1` nə edir?
**Cavab**: Shallow clone yaradır - yalnız son commit-i yükləyir, bütün tarixçəni deyil. CI/CD pipeline-larda sürəti artırmaq üçün istifadə olunur. Dezavantajı: tarixçəyə baxa bilməzsiniz, bəzi Git əməliyyatları işləməyə bilər.

### S6: `.git` qovluğunu silsəniz nə baş verir?
**Cavab**: Bütün Git tarixçəsi, branch-lar, commit-lər, konfiqurasiya itir. Working directory faylları qalır amma artıq Git repo deyil. Remote-da kopyası varsa `git clone` ilə bərpa oluna bilər, amma lokal commit-lər itir.

## Best Practices

1. **Kiçik, mənalı commit-lər edin**: Hər commit bir məntiqi dəyişiklik olmalıdır
2. **Yaxşı commit mesajı yazın**: İmperativ mood istifadə edin ("Add feature" not "Added feature")
3. **`.env` faylını heçvaxt commit etməyin**: `.gitignore`-a əlavə edin
4. **`git status`-u tez-tez yoxlayın**: Commit etməzdən əvvəl nə stage etdiyinizi bilin
5. **`git add -p` istifadə edin**: Böyük dəyişiklikləri kiçik commit-lərə bölmək üçün
6. **Branch-lardan istifadə edin**: `main`-də birbaşa işləməyin
7. **Mütəmadi commit edin**: Gün sonunda uncommitted iş qalmasın
8. **`git log` ilə tarixçəni yoxlayın**: Push etməzdən əvvəl commit-lərin düzgün olduğuna əmin olun

## Əlaqəli Mövzular

- [02-git-branching.md](02-git-branching.md) — commit-lər branch-larda necə qruplaşır
- [03-git-remote.md](03-git-remote.md) — commit-ləri remote-a göndərmək
- [04-gitignore.md](04-gitignore.md) — hansı faylları commit etməmək
