# Git Remote

## Nədir? (What is it?)

Remote repository, layihənizin şəbəkədə (GitHub, GitLab, Bitbucket) və ya başqa serverdə saxlanılan kopyasıdır. Git distributed olduğu üçün hər developer-in öz lokal repo kopyası var və remote ilə sinxronizasiya edir. Remote-lar komanda üzvlərinin eyni kod bazası üzərində işləməsini mümkün edir.

## Əsas Əmrlər (Key Commands)

### Remote İdarəetmə

```bash
# Remote-ları siyahıla
git remote
# origin

# URL ilə siyahıla
git remote -v
# origin  git@github.com:orkhan/laravel-app.git (fetch)
# origin  git@github.com:orkhan/laravel-app.git (push)

# Yeni remote əlavə et
git remote add origin git@github.com:orkhan/laravel-app.git
git remote add upstream git@github.com:laravel/laravel.git

# Remote URL-ni dəyiş
git remote set-url origin git@github.com:orkhan/new-repo.git

# Remote sil
git remote remove upstream

# Remote adını dəyiş
git remote rename origin main-remote

# Remote haqqında ətraflı məlumat
git remote show origin
# * remote origin
#   Fetch URL: git@github.com:orkhan/laravel-app.git
#   Push  URL: git@github.com:orkhan/laravel-app.git
#   HEAD branch: main
#   Remote branches:
#     develop       tracked
#     feature/auth  tracked
#     main          tracked
#   Local branches configured for 'git pull':
#     develop merges with remote develop
#     main    merges with remote main
```

### git fetch - Remote Dəyişiklikləri Yüklə (Merge Etmədən)

```bash
# Origin-dən bütün branch-ları fetch et
git fetch origin

# Yalnız bir branch
git fetch origin main

# Bütün remote-lardan fetch et
git fetch --all

# Silinmiş remote branch-ları təmizlə
git fetch --prune

# Tags da yüklə
git fetch --tags
```

```
  FETCH nə edir:

  Remote (origin):     C1 ── C2 ── C3 ── C4 ── C5
  Local (origin/main): C1 ── C2 ── C3
  Local (main):        C1 ── C2 ── C3

  git fetch origin:

  Remote (origin):     C1 ── C2 ── C3 ── C4 ── C5
  Local (origin/main): C1 ── C2 ── C3 ── C4 ── C5  (yeniləndi)
  Local (main):        C1 ── C2 ── C3               (dəyişmədi!)

  Fetch yalnız remote tracking branch-ı yeniləyir,
  sizin lokal branch-ınıza toxunmur.
```

### git pull - Fetch + Merge

```bash
# Fetch + merge (default)
git pull origin main

# Fetch + rebase (daha təmiz tarixçə)
git pull --rebase origin main

# Default olaraq rebase istifadə et
git config pull.rebase true

# Pull zamanı konflikti ləğv et
git pull --abort
```

```
  PULL = FETCH + MERGE:

  Remote:              C1 ── C2 ── C3 ── C4
  Local (main):        C1 ── C2 ── C5

  git pull origin main:

  Step 1 (fetch):
  Local (origin/main): C1 ── C2 ── C3 ── C4

  Step 2 (merge):
  Local (main):        C1 ── C2 ── C5 ── M1
                                \       /
                                 C3 ── C4

  PULL --rebase = FETCH + REBASE:

  Local (main):        C1 ── C2 ── C3 ── C4 ── C5'
  (C5 rebase olunur C4-ün üstünə)
```

### git push - Dəyişiklikləri Remote-a Göndər

```bash
# Branch-ı push et
git push origin main

# İlk dəfə push (upstream set et)
git push -u origin feature/user-auth
# Bundan sonra sadəcə 'git push' kifayətdir

# Bütün branch-ları push et
git push --all origin

# Tags push et
git push --tags

# Tək tag push et
git push origin v1.0.0

# Force push (DİQQƏTLİ!)
git push --force origin feature/my-branch

# Təhlükəsiz force push
git push --force-with-lease origin feature/my-branch

# Branch-ı remote-dan sil
git push origin --delete feature/old-branch
```

### Tracking Branches

```bash
# Branch-ın tracking məlumatını gör
git branch -vv
# * main         a1b2c3d [origin/main] Latest commit
#   develop      d4e5f6g [origin/develop: ahead 2, behind 1] ...
#   feature/auth h7i8j9k [origin/feature/auth: gone] ...

# Tracking branch set et
git branch -u origin/develop develop

# Remote tracking branch-ları gör
git branch -r

# Tracking branch yarat
git checkout --track origin/feature/payment
```

## Praktiki Nümunələr (Practical Examples)

### Fork Workflow

Open source layihələrdə istifadə olunan workflow:

```bash
# 1. Layihəni fork edin (GitHub UI-da)

# 2. Fork-u clone edin
git clone git@github.com:orkhan/laravel-framework.git
cd laravel-framework

# 3. Orijinal repo-nu upstream olaraq əlavə edin
git remote add upstream git@github.com:laravel/framework.git

# Remote-ları yoxlayın
git remote -v
# origin    git@github.com:orkhan/laravel-framework.git (fetch)
# origin    git@github.com:orkhan/laravel-framework.git (push)
# upstream  git@github.com:laravel/framework.git (fetch)
# upstream  git@github.com:laravel/framework.git (push)

# 4. Upstream-dan yeniləyin
git fetch upstream
git checkout main
git merge upstream/main

# 5. Feature branch yaradın
git checkout -b fix/validation-bug

# 6. Dəyişiklik edin və push edin
git add .
git commit -m "Fix email validation regex"
git push -u origin fix/validation-bug

# 7. GitHub-da PR açın (origin -> upstream)
```

```
  Fork Workflow diaqramı:

  upstream (laravel/framework):
  ┌─────────────────────────┐
  │ main: C1 ── C2 ── C3   │
  └─────────────────────────┘
        ▲                │
        │ PR             │ fork
        │                ▼
  origin (orkhan/laravel-framework):
  ┌─────────────────────────────────┐
  │ main:    C1 ── C2 ── C3        │
  │ fix/bug: C1 ── C2 ── C3 ── C4  │
  └─────────────────────────────────┘
        ▲                │
        │ push           │ clone
        │                ▼
  local:
  ┌─────────────────────────────────┐
  │ main:    C1 ── C2 ── C3        │
  │ fix/bug: C1 ── C2 ── C3 ── C4  │
  └─────────────────────────────────┘
```

### Multiple Remotes

```bash
# Staging və production üçün fərqli remote-lar
git remote add staging git@staging-server:app.git
git remote add production git@production-server:app.git

# Staging-ə deploy et
git push staging develop:main

# Production-a deploy et
git push production main

# Remote-ları siyahıla
git remote -v
# origin      git@github.com:company/app.git (fetch/push)
# staging     git@staging-server:app.git (fetch/push)
# production  git@production-server:app.git (fetch/push)
```

### `--force-with-lease` vs `--force`

```bash
# Ssenari: Rebase etdiniz və push etməlisiniz

# Təhlükəli: başqasının push-unu əzə bilər
git push --force origin feature/auth

# Təhlükəsiz: remote-da gözlənilməyən commit varsa dayandırır
git push --force-with-lease origin feature/auth
# Əgər başqası bu arada push edibsə:
# ! [rejected] feature/auth -> feature/auth (stale info)
```

## Vizual İzah (Visual Explanation)

### Fetch vs Pull

```
  ┌─────────────────────────────────────────────┐
  │              FETCH vs PULL                    │
  │                                               │
  │  FETCH:                                       │
  │  Remote ──(download)──> origin/main            │
  │  Local main toxunulmaz qalır                  │
  │                                               │
  │  PULL:                                        │
  │  Remote ──(download)──> origin/main            │
  │  origin/main ──(merge)──> local main          │
  │                                               │
  │  PULL --rebase:                               │
  │  Remote ──(download)──> origin/main            │
  │  local main ──(rebase onto)──> origin/main    │
  └─────────────────────────────────────────────┘
```

### Remote Tracking Branch Əlaqəsi

```
  GitHub (Remote):
  ┌──────────────────────────────┐
  │ refs/heads/main      -> C5  │
  │ refs/heads/develop   -> C8  │
  └──────────────────────────────┘
           │
           │ git fetch
           ▼
  Local Repository:
  ┌──────────────────────────────────────────┐
  │ refs/remotes/origin/main    -> C5       │  (remote tracking)
  │ refs/remotes/origin/develop -> C8       │  (remote tracking)
  │ refs/heads/main             -> C3       │  (local, behind)
  │ refs/heads/develop          -> C8       │  (local, up to date)
  └──────────────────────────────────────────┘
```

## PHP/Laravel Layihələrdə İstifadə

### Laravel Layihəsini İlk Dəfə Push Etmək

```bash
# Laravel layihəsi yarat
composer create-project laravel/laravel my-app
cd my-app

# GitHub-da repo yarat (GitHub CLI ilə)
gh repo create my-app --private

# Remote əlavə et və push et
git remote add origin git@github.com:orkhan/my-app.git
git push -u origin main

# Develop branch yarat
git checkout -b develop
git push -u origin develop
```

### Komanda ilə İşləmə Workflow

```bash
# Səhər - işə başla
git checkout develop
git pull origin develop              # Son dəyişiklikləri al

# Feature branch yarat
git checkout -b feature/JIRA-123-payment

# İşlə, commit et...
git add .
git commit -m "feat(payment): add Stripe integration"

# Push et (ilk dəfə)
git push -u origin feature/JIRA-123-payment

# PR yaratmadan əvvəl develop-i yenilə
git fetch origin develop
git rebase origin/develop

# Əgər rebase olubsa, force push et
git push --force-with-lease
```

### Deployment üçün Remote İstifadəsi

```bash
# Laravel Forge / Envoyer alternativ olaraq Git-based deploy

# Production server-i remote olaraq əlavə et
git remote add deploy ssh://user@server/var/www/app.git

# Deploy et
git push deploy main

# Server-dəki post-receive hook:
#!/bin/bash
# /var/www/app.git/hooks/post-receive
GIT_WORK_TREE=/var/www/app git checkout -f
cd /var/www/app
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Interview Sualları

### S1: `git fetch` ilə `git pull` arasında fərq nədir?
**Cavab**: `git fetch` remote-dan dəyişiklikləri yükləyir amma lokal branch-a merge etmir, yalnız remote tracking branch-ı yeniləyir. `git pull` isə `git fetch` + `git merge` (və ya `--rebase` ilə `git rebase`) birlikdədir. Fetch daha təhlükəsizdir çünki lokal işinizə toxunmur, əvvəlcə dəyişiklikləri görmək istəyirsinizsə fetch istifadə edin.

### S2: `origin` nədir?
**Cavab**: `origin`, `git clone` zamanı avtomatik yaradılan default remote adıdır. Xüsusi bir şey deyil, sadəcə konvensiyadır. İstənilən adla dəyişdirilə bilər. Birdən çox remote ola bilər: origin (öz fork-unuz), upstream (orijinal repo).

### S3: `--force-with-lease` nədir və niyə `--force`-dan yaxşıdır?
**Cavab**: `--force` remote-dakı bütün dəyişiklikləri əzir. `--force-with-lease` isə remote-dakı branch-ın sizin bildiyiniz vəziyyətdə olduğunu yoxlayır - əgər başqası bu arada push edibsə, push-u rədd edir. Bu, başqasının işini səhvən silmənin qarşısını alır.

### S4: Tracking branch nədir?
**Cavab**: Lokal branch-ın remote branch ilə əlaqəsini saxlayan mexanizmdir. `git push -u origin feature` ilə set olunur. Tracking branch sayəsində `git pull` və `git push` remote/branch göstərmədən işləyir. `git branch -vv` ilə ahead/behind statusunu görmək olar.

### S5: Fork workflow nədir və nə vaxt istifadə olunur?
**Cavab**: Developer orijinal repo-nu fork edir (öz kopyasını yaradır), fork-da işləyir və PR vasitəsilə dəyişiklikləri orijinal repo-ya təklif edir. Əsasən open source layihələrdə istifadə olunur çünki orijinal repo-ya birbaşa push icazəsi olmur.

### S6: `git push origin develop:main` nə edir?
**Cavab**: Lokal `develop` branch-ını remote-dakı `main` branch-a push edir. `local-branch:remote-branch` formatıdır. Bu, fərqli adlı branch-ları push etmək üçün istifadə olunur.

## Best Practices

1. **Hər iş gününə `git fetch` ilə başlayın**: Remote dəyişikliklərdən xəbərdar olun
2. **`git pull --rebase` istifadə edin**: Lazımsız merge commit-lərdən qaçının
3. **`--force-with-lease` istifadə edin**: Heçvaxt `--force` istifadə etməyin
4. **SSH istifadə edin**: HTTPS əvəzinə SSH key ilə autentifikasiya daha rahat və təhlükəsizdir
5. **Remote branch-ları təmizləyin**: `git fetch --prune` ilə silinmiş branch-ları təmizləyin
6. **Push etməzdən əvvəl pull edin**: Konfliktləri lokal həll edin
7. **Tracking branch-ları düzgün qurun**: `git push -u` ilə ilk push-da set edin
8. **Credentials saxlamayın**: Git credential helper və ya SSH key istifadə edin
