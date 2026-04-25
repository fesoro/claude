# Git Branching (Junior)

## İcmal

Git-də branch, commit tarixçəsindəki bir xəttə işarə edən yüngül pointer-dir. Branch yaratmaq sadəcə 41 baytlıq fayl yaratmaqdır (SHA-1 hash + newline). Bu, Git-in ən güclü xüsusiyyətlərindən biridir və digər VCS-lərdən fərqli olaraq millisaniyələr çəkir.

Branch, əslində müəyyən bir commit-ə işarə edən hərəkətli pointer-dir. Hər yeni commit ilə branch pointer irəli hərəkət edir.

## Niyə Vacibdir

Komandada hər developer ayrı branch-da işləyir; branch olmadan eyni faylda paralel dəyişikliklər idarə olunmaz. Feature, bugfix, hotfix branch-ları CI/CD pipeline-da fərqli deploy davranışlarını tetikləyir.

## Əsas Əmrlər (Key Commands)

### Branch Yaratmaq

```bash
# Yeni branch yarat (keçmə)
git branch feature/user-auth

# Yeni branch yarat və keç
git checkout -b feature/user-auth

# Müasir üsul (Git 2.23+)
git switch -c feature/user-auth

# Müəyyən commit-dən branch yarat
git branch hotfix/login-bug abc1234

# Remote branch-dan lokal branch yarat
git checkout -b feature/payment origin/feature/payment
# və ya
git switch -c feature/payment origin/feature/payment
```

### Branch-lar Arasında Keçmək

```bash
# Köhnə üsul
git checkout main

# Müasir üsul (Git 2.23+)
git switch main

# Əvvəlki branch-a qayıt
git checkout -
git switch -
```

### Branch-ları Siyahılamaq

```bash
# Lokal branch-lar
git branch
#   develop
# * feature/user-auth    <-- hazırda bu branch-dasınız
#   main

# Remote branch-lar
git branch -r
#   origin/develop
#   origin/main
#   origin/feature/payment

# Hamısı
git branch -a

# Son commit məlumatı ilə
git branch -v
#   develop      a1b2c3d Update config
# * feature/auth d4e5f6g Add login page
#   main         h7i8j9k Release v1.0

# Merge olunmuş branch-lar (silinə bilər)
git branch --merged main

# Merge olunmamış branch-lar
git branch --no-merged main
```

### Branch Silmək

```bash
# Merge olunmuş branch-ı sil
git branch -d feature/user-auth

# Merge olunmamış branch-ı zorla sil (DİQQƏT!)
git branch -D feature/experimental

# Remote branch-ı sil
git push origin --delete feature/user-auth
# və ya
git push origin :feature/user-auth
```

### Branch Adını Dəyişmək

```bash
# Hazırda olduğunuz branch-ın adını dəyişin
git branch -m new-name

# Başqa branch-ın adını dəyişin
git branch -m old-name new-name

# Remote-da da yeniləyin
git push origin :old-name
git push origin -u new-name
```

## Nümunələr

### HEAD Nədir?

HEAD, hazırda hansı branch-da (və ya commit-də) olduğunuzu göstərən xüsusi pointer-dir.

```
  Normalda HEAD bir branch-a işarə edir:

  HEAD -> main -> C4

  C1 <── C2 <── C3 <── C4  (main)
                         ^
                         HEAD (main vasitəsilə)
```

```bash
# HEAD-in nəyə işarə etdiyini görmək
cat .git/HEAD
# ref: refs/heads/main

# Daha ətraflı
git log --oneline -1 HEAD
# a1b2c3d (HEAD -> main) Latest commit message
```

### Detached HEAD

HEAD birbaşa commit-ə işarə edəndə "detached HEAD" vəziyyəti yaranır. Bu, branch-a deyil, konkret commit-ə keçdiyinizdə baş verir.

```bash
# Detached HEAD yaratmaq
git checkout abc1234    # konkret commit-ə keç
# WARNING: You are in 'detached HEAD' state...

# Detached HEAD-dən çıxmaq
git switch main         # branch-a qayıt

# Detached HEAD-dəki işi saxlamaq
git switch -c new-branch  # yeni branch yarat
```

```
  Detached HEAD:

  C1 <── C2 <── C3 <── C4  (main)
                  ^
                  HEAD (birbaşa commit-ə işarə edir)

  Burada commit etsəniz:

  C1 <── C2 <── C3 <── C4  (main)
                  \
                   C5  <── HEAD

  Branch-a keçsəniz, C5 "orphan" olacaq və gc ilə silinəcək!
```

### Branch Pointer-ləri

```
  Başlanğıc vəziyyət:

  C1 <── C2 <── C3   (main, HEAD)

  git checkout -b feature:

  C1 <── C2 <── C3   (main, feature, HEAD)

  feature branch-da commit et:

  C1 <── C2 <── C3   (main)
                  \
                   C4  (feature, HEAD)

  Daha bir commit:

  C1 <── C2 <── C3   (main)
                  \
                   C4 <── C5  (feature, HEAD)
```

### Local vs Remote Branches

```bash
# Remote branch-ları yenilə
git fetch origin

# Remote tracking branch-ları gör
git branch -vv
# * main         a1b2c3d [origin/main] Latest commit
#   feature/auth d4e5f6g [origin/feature/auth: ahead 2] Add login
#   develop      h7i8j9k [origin/develop: behind 3] Old commit

# ahead 2: 2 lokal commit push olunmayıb
# behind 3: remote-da 3 yeni commit var
```

```
  Local vs Remote:

  origin/main:     C1 <── C2 <── C3
  local main:      C1 <── C2 <── C3 <── C4 <── C5
                                         (ahead 2)

  git push sonrası:
  origin/main:     C1 <── C2 <── C3 <── C4 <── C5
  local main:      C1 <── C2 <── C3 <── C4 <── C5
```

## Vizual İzah (Visual Explanation)

### Tipik Branch Workflow

```
  main:     C1 ── C2 ── C3 ────────── C7 (merge) ── C9 (merge)
                    \                 /               /
  feature/A:         C4 ── C5 ── C6                 /
                                                   /
  feature/B:              C8 ─────────────────────
                          (C3-dən ayrılıb)

  Timeline: ──────────────────────────────────────────>
```

### Branch-ların Necə Saxlandığı

```
  .git/
  ├── HEAD                  -> ref: refs/heads/main
  ├── refs/
  │   ├── heads/
  │   │   ├── main          -> a1b2c3d (SHA-1 hash)
  │   │   ├── develop       -> d4e5f6g
  │   │   └── feature/
  │   │       └── user-auth -> h7i8j9k
  │   ├── remotes/
  │   │   └── origin/
  │   │       ├── main      -> a1b2c3d
  │   │       └── develop   -> x1y2z3a
  │   └── tags/
  │       └── v1.0          -> m1n2o3p
```

## Praktik Baxış

### Laravel Layihəsində Branch Strategiyası

```bash
# main - production kodu
# develop - development kodu
# feature/* - yeni xüsusiyyətlər
# hotfix/* - təcili düzəlişlər
# release/* - release hazırlığı

# Yeni feature başlat
git switch -c feature/order-management develop

# Controller yarat
php artisan make:controller OrderController --resource

# Model və migration
php artisan make:model Order -m

# Test yaz
php artisan make:test OrderTest

# Commit et
git add app/Http/Controllers/OrderController.php \
        app/Models/Order.php \
        database/migrations/ \
        tests/Feature/OrderTest.php

git commit -m "feat: add order management module"
```

### Migration-lar və Branch Konflikti

```bash
# Problem: İki branch eyni vaxtda migration yaradır

# Branch A: 2024_01_15_100000_create_orders_table.php
# Branch B: 2024_01_15_100001_create_invoices_table.php

# Merge edəndə migration sırası problemi ola bilər.
# Həll: merge sonrası migration-ları yenidən sıralayın:

php artisan migrate:fresh --seed  # Development-də
```

## Praktik Tapşırıqlar

1. **Feature branch workflow**
   ```bash
   git checkout -b feature/user-authentication
   # dəyişikliklər et
   git add .
   git commit -m "feat: add login controller"
   git checkout main
   git merge feature/user-authentication
   git branch -d feature/user-authentication
   ```

2. **Detached HEAD vəziyyəti test et**
   ```bash
   git log --oneline  # köhnə commit SHA-sını gör
   git checkout <sha>  # detached HEAD
   git checkout main    # geri qayıt
   ```

3. **Branch-ları siyahıla**
   ```bash
   git branch          # local
   git branch -r       # remote
   git branch -a       # hamısı
   git branch -v       # son commit ilə
   ```

4. **Branch adlandırma praktikası**
   - `feature/TICKET-123-user-login` formatında 3 branch yarat
   - `bugfix/null-pointer-orders` formatında 1 branch yarat
   - Hamısını siyahıla, sonra sil

## Interview Sualları

### S1: Git branch nədir, texniki olaraq necə işləyir?
**Cavab**: Branch, 40 simvoldan ibarət SHA-1 hash-i saxlayan fayldır (`.git/refs/heads/branch-name`). Bu hash son commit-ə işarə edir. Branch yaratmaq sadəcə bu faylı yaratmaq deməkdir, buna görə çox sürətlidir. Commit etdikdə branch pointer avtomatik yeni commit-ə keçir.

### S2: Detached HEAD nədir və nə vaxt baş verir?
**Cavab**: HEAD birbaşa commit-ə (branch-a deyil) işarə etdikdə detached HEAD yaranır. Bu `git checkout <commit-hash>` və ya `git checkout <tag>` ilə baş verir. Bu vəziyyətdə commit etsəniz, branch-a keçdikdə həmin commit-lər itirilə bilər (garbage collection ilə silinər). Həll: `git switch -c new-branch` ilə yeni branch yaradın.

### S3: `git checkout` vs `git switch` vs `git restore` fərqi nədir?
**Cavab**: `git checkout` çox funksiyalıdır - həm branch keçidi, həm fayl bərpası edir. Git 2.23-də bu ikiyə bölündü: `git switch` yalnız branch keçidi üçün, `git restore` yalnız fayl bərpası üçün. Bu, əmrləri daha aydın edir və səhv ehtimalını azaldır.

### S4: `git branch --merged` nə göstərir?
**Cavab**: Hazırkı branch-a (və ya göstərilən branch-a) artıq merge olunmuş branch-ları göstərir. Bu branch-lar təhlükəsiz silinə bilər çünki onların bütün commit-ləri artıq hədəf branch-dadır.

### S5: Remote branch-ı necə silirsiniz?
**Cavab**: `git push origin --delete branch-name` əmri ilə. Bu remote-dakı branch-ı silir. Digər developer-lər `git fetch --prune` ilə (və ya `git remote prune origin`) öz lokal remote-tracking referanslarını təmizləyə bilər.

### S6: Branch adlandırma konvensiyaları hansılardır?
**Cavab**: `feature/`, `bugfix/`, `hotfix/`, `release/` prefiksləri istifadə olunur. Kebab-case istifadə edilir. Jira ticket nömrəsi əlavə oluna bilər: `feature/PROJ-123-user-authentication`. Aydın, qısa və təsviri adlar seçilməlidir.

## Best Practices

1. **Branch-ları qısa ömürlü saxlayın**: Uzun yaşayan branch-lar merge conflict riski artırır
2. **Mənalı adlar verin**: `feature/user-auth` yaxşıdır, `my-branch` pisdir
3. **Merge olunmuş branch-ları silin**: Repo-nu təmiz saxlayın
4. **`main`/`master`-da birbaşa commit etməyin**: Həmişə branch istifadə edin
5. **Mütəmadi olaraq base branch-dan yeniləyin**: `git merge main` və ya `git rebase main`
6. **Detached HEAD-dən xəbərdar olun**: Commit etməzdən əvvəl branch-da olduğunuzu yoxlayın
7. **Protected branches quraşdırın**: GitHub/GitLab-da main branch-a birbaşa push-u bloklayın
8. **Remote branch-ları `git fetch --prune` ilə təmizləyin**: Silinmiş remote branch-ların lokal referanslarını silin

## Əlaqəli Mövzular

- [01-git-basics.md](01-git-basics.md) — commit əsasları
- [05-git-merging.md](05-git-merging.md) — branch-ları birləşdirmək
- [06-git-rebasing.md](06-git-rebasing.md) — branch-ı rebase etmək
