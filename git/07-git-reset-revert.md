# Git Reset, Revert, and Recovery

## Nədir? (What is it?)

Git-də dəyişiklikləri geri almaq üçün bir neçə alət var: `reset`, `revert`, `restore`, və `checkout`. Hər birinin fərqli istifadə sahəsi var. Bunları düzgün başa düşmək vacibdir çünki yanlış istifadə iş itkisinə səbəb ola bilər.

- **Reset**: Branch pointer-i geri aparır (tarixçəni dəyişir)
- **Revert**: Əks commit yaradır (tarixçəni qoruyur)
- **Restore**: Faylları bərpa edir
- **Reflog**: İtirilmiş commit-ləri tapmaq üçün

## Əsas Əmrlər (Key Commands)

### git reset

```bash
# Soft reset: commit-i geri al, dəyişikliklər staged qalır
git reset --soft HEAD~1

# Mixed reset (default): commit geri, dəyişikliklər unstaged qalır
git reset HEAD~1
git reset --mixed HEAD~1

# Hard reset: commit geri, dəyişikliklər tamamilə silinir (DİQQƏT!)
git reset --hard HEAD~1

# Konkret commit-ə reset
git reset --soft abc1234

# Faylı unstage et (stage-dən çıxar)
git reset HEAD app/Models/User.php
# Müasir alternativ:
git restore --staged app/Models/User.php
```

```
  HEAD~1 = bir commit geri
  HEAD~2 = iki commit geri
  HEAD~n = n commit geri

  Reset növləri:

  Əvvəlki vəziyyət:
  C1 ── C2 ── C3 (HEAD)
  Working Dir: clean
  Staging: clean

  ┌─────────────────────────────────────────────────┐
  │              git reset --soft HEAD~1             │
  ├─────────────────────────────────────────────────┤
  │ Repository: C1 ── C2 (HEAD)                     │
  │ Staging:    C3-ün dəyişiklikləri (staged)       │
  │ Working:    C3-ün dəyişiklikləri (görünür)      │
  │ İstifadə:   Commit mesajını dəyişmək istəyəndə │
  └─────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────┐
  │              git reset --mixed HEAD~1            │
  ├─────────────────────────────────────────────────┤
  │ Repository: C1 ── C2 (HEAD)                     │
  │ Staging:    boş (unstaged)                      │
  │ Working:    C3-ün dəyişiklikləri (görünür)      │
  │ İstifadə:   Commit-i parçalayıb yenidən etmək  │
  └─────────────────────────────────────────────────┘

  ┌─────────────────────────────────────────────────┐
  │              git reset --hard HEAD~1             │
  ├─────────────────────────────────────────────────┤
  │ Repository: C1 ── C2 (HEAD)                     │
  │ Staging:    boş                                 │
  │ Working:    C2-nin vəziyyəti (C3 itdi!)         │
  │ İstifadə:   Tamamilə geri qayıtmaq istəyəndə  │
  └─────────────────────────────────────────────────┘
```

### git revert

```bash
# Son commit-i revert et (əks commit yaradır)
git revert HEAD

# Konkret commit-i revert et
git revert abc1234

# Commit yaratmadan revert et (manual commit etmək üçün)
git revert --no-commit abc1234

# Merge commit-i revert et (-m ilə hansı parent-i saxlamaq)
git revert -m 1 <merge-commit-hash>
# -m 1: birinci parent (adətən main branch)
# -m 2: ikinci parent (merge olunan branch)

# Bir neçə commit-i revert et
git revert abc1234..def5678

# Revert-i ləğv et
git revert --abort
```

```
  REVERT nə edir:

  ƏVVƏL:
  C1 ── C2 ── C3 (HEAD)
              (bug burada)

  git revert C3:

  SONRA:
  C1 ── C2 ── C3 ── C3' (HEAD)
              (bug)  (bug-ın əksi - düzəliş)

  C3' commit-i C3-ün tam əksini edir.
  Tarixçə qorunur, heç nə silinmir.
```

### git restore

```bash
# Working directory-dəki dəyişikliyi geri al
git restore app/Models/User.php

# Staged dəyişikliyi geri al (unstage)
git restore --staged app/Models/User.php

# Konkret commit-dən faylı bərpa et
git restore --source=HEAD~2 app/Models/User.php

# Bütün faylları bərpa et
git restore .

# Staged və working dəyişiklikləri birlikdə geri al
git restore --staged --worktree app/Models/User.php
```

### git reflog - Xilas Edici

```bash
# Reflog-a bax
git reflog
# a1b2c3d (HEAD -> main) HEAD@{0}: reset: moving to HEAD~2
# d4e5f6g HEAD@{1}: commit: Add payment service
# h7i8j9k HEAD@{2}: commit: Add user model
# m1n2o3p HEAD@{3}: checkout: moving from develop to main

# Son 10 giriş
git reflog -10

# Branch-ın reflog-u
git reflog show feature/auth

# Reflog ilə itirilmiş commit-i bərpa et
git reset --hard d4e5f6g  # commit hash-ı reflog-dan götürün
```

## Praktiki Nümunələr (Practical Examples)

### Ssenari 1: Son Commit-i Düzəltmək

```bash
# Səhv commit mesajı yazdınız
git commit -m "Add user mdoel"  # typo!

# Üsul 1: --amend (push olunmayıbsa)
git commit --amend -m "Add user model"

# Üsul 2: soft reset + yenidən commit
git reset --soft HEAD~1
git commit -m "Add user model"
```

### Ssenari 2: Səhv Faylı Commit Etdiniz

```bash
# .env faylını səhvən commit etdiniz
git add .
git commit -m "Add config changes"

# Soft reset - commit geri, dəyişikliklər staged
git reset --soft HEAD~1

# .env-i unstage et
git restore --staged .env

# Yenidən commit et
git commit -m "Add config changes"
```

### Ssenari 3: Push Olunmuş Commit-i Geri Almaq

```bash
# Hard reset ETMƏYIN çünki push olunub!
# Revert istifadə edin:

git revert HEAD
# Editor açılır: "Revert "Add broken feature""
# Mesajı redaktə edib commit edin

git push origin main
# Tarixçə qorunur, komanda üzvləri problemsiz pull edə bilər
```

### Ssenari 4: Hard Reset Sonrası Bərpa

```bash
# Səhvən hard reset etdiniz
git reset --hard HEAD~3
# 3 commit itdi!

# Reflog xilas edir:
git reflog
# abc1234 HEAD@{0}: reset: moving to HEAD~3
# def5678 HEAD@{1}: commit: Important feature
# ghi9012 HEAD@{2}: commit: Critical bug fix
# jkl3456 HEAD@{3}: commit: Add user model

# İtirilmiş commit-ə qayıdın
git reset --hard def5678
# Bütün 3 commit geri gəldi!
```

### Ssenari 5: Merge Commit-i Revert Etmək

```bash
# Merge commit-i geri almaq daha mürəkkəbdir
git log --oneline
# abc1234 Merge branch 'feature/broken' into main
# def5678 (feature/broken) Add broken code
# ghi9012 Normal commit on main

git revert -m 1 abc1234
# -m 1: main branch tərəfini saxla (merge-dən əvvəlki main)
# -m 2: feature branch tərəfini saxla

# DİQQƏT: Revert olunmuş branch-ı sonra yenidən merge etmək
# üçün əvvəlcə revert-i revert etməlisiniz:
git revert <revert-commit-hash>
git merge feature/broken
```

## Vizual İzah (Visual Explanation)

### Reset vs Revert

```
  RESET (tarixçəni dəyişir - push olunmamış üçün):

  ƏVVƏL:  C1 ── C2 ── C3 ── C4 (HEAD)
  SONRA:  C1 ── C2 (HEAD)
  C3, C4 "itir" (reflog-da qalır)

  REVERT (tarixçəyə əlavə edir - push olunmuş üçün):

  ƏVVƏL:  C1 ── C2 ── C3 ── C4 (HEAD)
  SONRA:  C1 ── C2 ── C3 ── C4 ── C4' ── C3' (HEAD)
  C4' = C4-ün əksi, C3' = C3-ün əksi
```

### Üç Reset Növü

```
                    Repository    Staging    Working Dir
  ──────────────────────────────────────────────────────
  --soft            ← geri aparır  saxlayır   saxlayır
  --mixed (default) ← geri aparır  təmizləyir saxlayır
  --hard            ← geri aparır  təmizləyir təmizləyir
```

### Reflog Tarixçəsi

```
  Normal log görünüşü:
  C1 ── C2 ── C3 (HEAD)

  Reflog görünüşü (bütün HEAD hərəkətləri):
  HEAD@{0}: commit: C3
  HEAD@{1}: checkout: develop -> main
  HEAD@{2}: commit: something on develop
  HEAD@{3}: reset: HEAD~1
  HEAD@{4}: commit: now deleted commit  <-- bu hələ mövcuddur!
  HEAD@{5}: commit: C2
  HEAD@{6}: commit: C1

  Reflog BÜTÜN HEAD hərəkətlərini saxlayır.
  Default olaraq 90 gün saxlanılır.
```

## PHP/Laravel Layihələrdə İstifadə

### Migration Geri Almaq

```bash
# Səhv migration commit etdiniz
git log --oneline
# abc1234 Add broken migration
# def5678 Previous good commit

# Üsul 1: Əgər push olunmayıbsa
git reset --soft HEAD~1
# Migration faylını düzəldin
vim database/migrations/2024_01_15_create_orders_table.php
git add database/migrations/
git commit -m "Add orders migration (fixed)"

# Üsul 2: Əgər push olunubsa
git revert HEAD
# Sonra düzgün migration əlavə edin
```

### Laravel Config Cache Problemi

```bash
# Production-da yanlış config push etdiniz
git revert HEAD -m "Revert broken config changes"
git push origin main

# Server-də:
php artisan config:clear
php artisan config:cache

# Konfiqurasiya bərpa olundu
```

### Vendor Qovluğunu Commit Etdiniz

```bash
# Səhvən vendor/ commit etdiniz (çox böyük!)
git reset --soft HEAD~1

# .gitignore-a əlavə edin (artıq olmalıdır)
echo "/vendor" >> .gitignore

# vendor-u Git tracking-dən çıxar
git rm -r --cached vendor/

# Yenidən commit
git add .gitignore
git commit -m "Remove vendor from tracking, update .gitignore"
```

## Interview Sualları

### S1: `git reset --soft`, `--mixed`, `--hard` arasında fərq nədir?
**Cavab**: `--soft` yalnız HEAD pointer-i geri aparır, dəyişikliklər staged qalır (yenidən commit etmək asan). `--mixed` (default) HEAD-i geri aparır və staging area-nı təmizləyir, dəyişikliklər working directory-də qalır. `--hard` hər üçünü geri aparır - dəyişikliklər tamamilə silinir.

### S2: `git reset` ilə `git revert` arasında fərq nədir?
**Cavab**: `reset` branch pointer-i geri aparır, tarixçəni dəyişir - push olunmamış commit-lər üçün. `revert` əks commit yaradır, tarixçəni qoruyur - push olunmuş commit-lər üçün. Paylaşılmış branch-da reset force push tələb edir ki, bu problemlidir; revert isə normal push ilə işləyir.

### S3: Reflog nədir və nə vaxt istifadə olunur?
**Cavab**: Reflog HEAD-in bütün hərəkətlərinin lokal log-udur (commit, reset, checkout, merge, rebase). Səhvən itirilmiş commit-ləri bərpa etmək üçün istifadə olunur. `git reset --hard` sonrası belə, reflog vasitəsilə commit-ə qayıtmaq olar. Default 90 gün saxlanılır. Yalnız lokal-da mövcuddur.

### S4: Merge commit-i necə revert edirsiniz?
**Cavab**: `git revert -m 1 <merge-commit>` ilə. `-m` (mainline) parametri hansı parent-i saxlamaq istədiyinizi göstərir: `-m 1` birinci parent (adətən main), `-m 2` ikinci parent (merge olunan branch). Sonra bu branch-ı yenidən merge etmək üçün əvvəlcə revert-i revert etməlisiniz.

### S5: `git checkout -- file` ilə `git restore file` arasında fərq nədir?
**Cavab**: Funksional olaraq eynidir - hər ikisi faylı son commit vəziyyətinə qaytarır. `git restore` Git 2.23-də əlavə olundu, `checkout`-un fayl bərpa funksiyasını ayırmaq üçün. `restore` daha aydındır çünki `checkout` həm branch keçidi, həm fayl bərpası üçün istifadə olunurdu.

### S6: `git reset --hard` etdikdən sonra dəyişiklikləri bərpa etmək olarmı?
**Cavab**: Commit olunmuş dəyişikliklər bəli - reflog vasitəsilə. `git reflog` ilə commit hash-ını tapın və `git reset --hard <hash>` edin. Amma commit olunmamış dəyişikliklər (working directory-dəki) hard reset ilə birdəfəlik itir, bərpa mümkün deyil.

## Best Practices

1. **Push olunmuş commit-lər üçün `revert` istifadə edin**: Heçvaxt paylaşılmış branch-da `reset` etməyin
2. **`reset --hard` əvvəl düşünün**: Geri dönüşsüz ola bilər (uncommitted dəyişikliklər üçün)
3. **Reflog-u bilin**: Səhv zamanı xilas edir
4. **`restore` istifadə edin**: `checkout --` əvəzinə daha aydındır
5. **`--force-with-lease` istifadə edin**: Reset sonrası force push lazım olarsa
6. **Əvvəlcə backup branch yaradın**: `git branch backup` sonra reset edin
7. **`git diff` ilə əvvəlcə yoxlayın**: Reset/revert etməzdən əvvəl nəyi itirəcəyinizi bilin
8. **Commit mesajlarında revert səbəbini yazın**: Niyə revert etdiyinizi qeyd edin
