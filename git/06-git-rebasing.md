# Git Rebasing (Middle)

## İcmal

Rebase, bir branch-ın commit-lərini başqa bir branch-ın üstünə "köçürən" əməliyyatdır. Texniki olaraq, rebase commit-ləri yenidən yaradır (yeni SHA hash-ləri ilə) və onları hədəf branch-ın sonuna qoyur. Nəticədə xətti (linear) tarixçə yaranır.

Merge iki branch-ı birləşdirir və merge commit yaradır, rebase isə branch-ın başlanğıc nöqtəsini dəyişir.

## Niyə Vacibdir

Clean, linear commit history interview-larda və code review-larda peşəkarlıq göstəricisidir. `git rebase -i` ilə WIP commit-ləri squash etmək PR-ı review üçün daha oxunaqlı edir; CI/CD-də merge conflict-ləri azaldır.

## Əsas Əmrlər (Key Commands)

```bash
# Feature branch-ı main üzərinə rebase et
git checkout feature/user-auth
git rebase main

# Və ya qısa yol
git rebase main feature/user-auth

# Interactive rebase (son 3 commit)
git rebase -i HEAD~3

# Rebase zamanı konflikti həll etdikdən sonra davam et
git rebase --continue

# Rebase-i ləğv et
git rebase --abort

# Rebase-i mövcud vəziyyətdə saxla
git rebase --skip

# --onto ilə rebase
git rebase --onto main feature-a feature-b
```

## Nümunələr

### Sadə Rebase

```
  ƏVVƏL:

  main:     C1 ── C2 ── C5 ── C6
                    \
  feature:           C3 ── C4

  git checkout feature
  git rebase main

  SONRA:

  main:     C1 ── C2 ── C5 ── C6
                                 \
  feature:                        C3' ── C4'

  C3' və C4' YENİ commit-lərdir (fərqli SHA)
  Eyni dəyişiklikləri edirlər amma yeni valideynləri var
```

### Rebase vs Merge Müqayisəsi

```
  MERGE nəticəsi:

  main:     C1 ── C2 ── C5 ── C6 ── M1
                    \                /
  feature:           C3 ── C4 ──────

  REBASE + Fast-Forward nəticəsi:

  main:     C1 ── C2 ── C5 ── C6 ── C3' ── C4'

  Rebase xətti tarixçə yaradır, merge isə branch tarixçəsini qoruyur.
```

### Interactive Rebase

Interactive rebase commit tarixçəsini redaktə etmək üçün ən güclü alətdir.

```bash
git rebase -i HEAD~4
```

Açılan editor:
```
pick a1b2c3d Add User model
pick d4e5f6g Add validation rules
pick h7i8j9k Fix typo in validation
pick m1n2o3p Add UserController

# Commands:
# p, pick   = commit-i olduğu kimi saxla
# r, reword = commit-i saxla amma mesajı dəyiş
# e, edit   = commit-i saxla amma düzəliş üçün dayan
# s, squash = əvvəlki commit ilə birləşdir (mesajları birləşdir)
# f, fixup  = squash kimi amma bu commit-in mesajını at
# d, drop   = commit-i sil
# Sıranı dəyişmək commit sırasını dəyişir
```

### Squashing - Commit-ləri Birləşdirmək

```bash
git rebase -i HEAD~3

# Editor-da:
pick a1b2c3d Add user authentication
squash d4e5f6g Fix auth bug
squash h7i8j9k Add auth tests

# Nəticə: 3 commit 1 commit olacaq
# Yeni mesaj yazmaq üçün editor açılacaq
```

```
  ƏVVƏL:
  ... ── C1 ── C2 ── C3   (feature)
         Add   Fix   Add
         auth  bug   tests

  SONRA:
  ... ── C1'                (feature)
         Add user authentication
         (with bug fix and tests)
```

### Fixup - Mesajsız Squash

```bash
# Typo düzəldin və commit etdiniz:
git commit -m "fixup: typo in UserController"

# Sonra interactive rebase ilə birləşdirin:
git rebase -i HEAD~3

# Editor-da:
pick a1b2c3d Add UserController
fixup d4e5f6g fixup: typo in UserController
pick h7i8j9k Add routes

# Nəticə: fixup commit-in mesajı atılır, dəyişikliyi əvvəlki commit-ə birləşir
```

### Rebase --onto

Bir branch-ı başqa branch-ın üstündən götürüb digərinə qoymaq:

```bash
git rebase --onto main feature-a feature-b
```

```
  ƏVVƏL:

  main:       C1 ── C2
                \
  feature-a:    C3 ── C4
                       \
  feature-b:            C5 ── C6

  git rebase --onto main feature-a feature-b

  SONRA:

  main:       C1 ── C2
                \       \
  feature-a:    C3 ── C4 \
                          \
  feature-b:               C5' ── C6'

  feature-b artıq main-dən ayrılır, feature-a-dan deyil
```

### Rebase Zamanı Konflikt

```bash
git checkout feature
git rebase main

# CONFLICT (content): Merge conflict in app/Models/User.php
# error: could not apply a1b2c3d... Add user validation

# Konflikti həll et
vim app/Models/User.php  # marker-ləri sil, düzgün kodu seç

# Stage et və davam et
git add app/Models/User.php
git rebase --continue

# Hər commit üçün ayrı konflikt ola bilər!
# Merge-dən fərqli olaraq, rebase hər commit-i tək-tək tətbiq edir
```

## Vizual İzah (Visual Explanation)

### Qızıl Qayda (Golden Rule of Rebasing)

```
  ╔═══════════════════════════════════════════════════════════╗
  ║  HEÇVAXT paylaşılmış (push olunmuş) branch-ı            ║
  ║  rebase ETMƏYİN!                                        ║
  ║                                                          ║
  ║  Rebase commit SHA-larını dəyişir.                       ║
  ║  Başqaları köhnə SHA-lara əsaslanaraq işləyirsə,        ║
  ║  tarixçə ziddiyyəti yaranır.                             ║
  ╚═══════════════════════════════════════════════════════════╝

  Siz rebase etdiniz:
  main:  C1 ── C2 ── C3
                       \
  feature:              C4' ── C5'  (yeni SHA)

  Başqa developer hələ köhnə SHA-lara əsaslanır:
  feature:  C4 ── C5 ── C6  (köhnə SHA)

  Push etdikdə xaos yaranır!
```

### Nə Vaxt Rebase, Nə Vaxt Merge?

```
  REBASE istifadə edin:
  ✓ Lokal, push olunmamış branch-ları yeniləmək üçün
  ✓ PR-dan əvvəl commit tarixçəsini təmizləmək üçün
  ✓ Feature branch-ı main ilə güncel saxlamaq üçün

  MERGE istifadə edin:
  ✓ Feature branch-ı main-ə birləşdirmək üçün
  ✓ Paylaşılmış branch-ları birləşdirmək üçün
  ✓ Branch tarixçəsini qorumaq istədikdə
  ✓ Public/shared branch-larda
```

## Praktik Baxış

### PR Hazırlığı üçün Rebase

```bash
# Feature branch-da çox WIP commit var:
# "WIP: start auth"
# "fix typo"
# "WIP: continue auth"
# "debug: add dd()"
# "remove debug"
# "Add authentication feature"

# PR-dan əvvəl təmizləyin:
git rebase -i HEAD~6

pick a1b2c3d WIP: start auth
fixup d4e5f6g fix typo
fixup h7i8j9k WIP: continue auth
fixup m1n2o3p debug: add dd()
fixup q1r2s3t remove debug
reword u4v5w6x Add authentication feature

# Nəticə: 1 təmiz commit
# "feat: add user authentication with Laravel Sanctum"
```

### Laravel Migration-ları ilə Rebase

```bash
# Problematik ssenari:
# main-də yeni migration əlavə olunub
# feature branch-da da yeni migration var
# Rebase zamanı migration timestamp konflikti ola bilər

# Həll:
git checkout feature/orders
git rebase main

# Əgər migration timestamp konflikti varsa:
# Migration faylının adını yeni timestamp ilə dəyişin
php artisan migrate:fresh --seed  # Development DB-ni yenidən qur
```

### Commit Mesajlarını Conventional Commits Formatına Gətirmək

```bash
git rebase -i HEAD~4

# Hər commit üçün "reword" seçin:
reword a1b2c3d add order model
reword d4e5f6g add order controller
reword h7i8j9k add order tests
reword m1n2o3p add order routes

# Hər birinin mesajını dəyişin:
# "feat(order): add Order model with relationships"
# "feat(order): add OrderController with CRUD operations"
# "test(order): add feature tests for order management"
# "feat(order): add API routes for order endpoints"
```

## Praktik Tapşırıqlar

1. **Feature branch-ı main-ə rebase et**
   ```bash
   git checkout feature/payment
   git rebase main
   # conflict varsa həll et, sonra:
   git rebase --continue
   ```

2. **Interactive rebase ilə commit-ləri təmizlə**
   ```bash
   git rebase -i HEAD~4
   # Açılan editorda:
   # pick → squash (s) — əvvəlkiyə birləş
   # pick → reword (r) — mesajı dəyiş
   # pick → drop (d) — sil
   ```

3. **--autosquash workflow**
   ```bash
   git commit -m "fixup! feat: add payment"  # avtomatik fixup markerı
   git rebase -i --autosquash main
   ```

4. **Rebase golden rule testi**
   ```bash
   # YANLIŞ: push olunmuş branch-ı rebase et
   git push --force-with-lease origin feature/shared  # fərqi anla
   # Niyə shared branch-da rebase tehlikelidir?
   ```

## Interview Sualları

### S1: Rebase ilə merge arasında fərq nədir?
**Cavab**: Merge iki branch-ı merge commit ilə birləşdirir, branch tarixçəsini qoruyur. Rebase isə commit-ləri yenidən yaradır və hədəf branch-ın sonuna qoyur, xətti tarixçə yaradır. Merge təhlükəsizdir (tarixçəni dəyişmir), rebase isə commit SHA-larını dəyişir. Merge paylaşılmış branch-lar üçün, rebase lokal branch-lar üçün istifadə olunur.

### S2: "Golden rule of rebasing" nədir?
**Cavab**: Heçvaxt paylaşılmış (push olunmuş) branch-ı rebase etməyin. Rebase commit-lərin SHA-larını dəyişir. Əgər başqa developer-lər köhnə commit-lərə əsaslanaraq işləyirlərsə, rebase tarixçə ziddiyyəti yaradır və force push tələb edir. Bu, komanda üzvlərinin işini poza bilər.

### S3: Interactive rebase nə üçün istifadə olunur?
**Cavab**: Commit tarixçəsini redaktə etmək üçün: commit-ləri birləşdirmək (squash/fixup), mesajları dəyişmək (reword), commit-ləri silmək (drop), sıranı dəyişmək, commit-i redaktə etmək (edit). PR-dan əvvəl tarixçəni təmizləmək üçün çox faydalıdır.

### S4: Squash ilə fixup arasında fərq nədir?
**Cavab**: Hər ikisi commit-ləri əvvəlki commit ilə birləşdirir. Fərq mesajdadır: squash hər iki commit-in mesajını birləşdirir (editor açılır), fixup isə birləşdirilən commit-in mesajını atır, yalnız əvvəlki commit-in mesajını saxlayır.

### S5: `git rebase --onto` nə üçün istifadə olunur?
**Cavab**: Bir branch-ı bir base-dən götürüb başqa base-ə qoymaq üçün. Məsələn, feature-b feature-a-dan ayrılıb amma feature-a-dan asılı deyilsə, `git rebase --onto main feature-a feature-b` ilə feature-b-ni birbaşa main-dən ayrılmış kimi edə bilərsiniz.

### S6: Rebase zamanı konfliktlər merge-dən niyə fərqlidir?
**Cavab**: Rebase hər commit-i tək-tək tətbiq edir, buna görə hər commit üçün ayrı konflikt ola bilər. Merge isə yalnız bir dəfə (son nəticələri müqayisə edərək) konflikt yaradır. Rebase daha çox vaxt ala bilər amma hər commit-in düzgün olmasını təmin edir.

## Best Practices

1. **Lokal branch-ları rebase edin, paylaşılmış branch-ları merge edin**
2. **PR-dan əvvəl interactive rebase ilə tarixçəni təmizləyin**: WIP commit-ləri squash edin
3. **Mütəmadi olaraq base branch-dan rebase edin**: `git rebase main` ilə güncel qalın
4. **Force push dikkatli istifadə edin**: Rebase sonrası `git push --force-with-lease` istifadə edin (`--force` deyil!)
5. **`--force-with-lease` `--force`-dan təhlükəsizdir**: Remote-da gözlənilməyən dəyişiklik varsa push-u dayandırır
6. **Uzun branch-ları rebase etməkdən çəkinin**: Çox commit varsa, çox konflikt ola bilər
7. **Hər rebase əvvəlcə backup branch yaradın**: `git branch backup-feature` sonra rebase edin
8. **Commit-ləri məntiqi olaraq qruplaşdırın**: İnteractive rebase ilə əlaqəli dəyişiklikləri birləşdirin

## Əlaqəli Mövzular

- [05-git-merging.md](05-git-merging.md) — merge vs rebase seçimi
- [08-git-reset-revert.md](08-git-reset-revert.md) — rebase-dən sonra geri dönmək
- [23-git-advanced-commands.md](23-git-advanced-commands.md) — filter-repo, rerere kimi advanced əməliyyatlar
