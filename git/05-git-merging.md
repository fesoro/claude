# Git Merging (Middle)

## İcmal

Merge, iki branch-ın tarixçəsini birləşdirmək prosesidir. Git iki branch-dakı dəyişiklikləri müqayisə edir və onları bir commit-də birləşdirir. Merge iki əsas formada baş verir: fast-forward və 3-way merge.

## Niyə Vacibdir

Feature branch-ların main-ə birləşdirilməsi gündəlik workflow-un əsasıdır. Conflict resolution bacarığı olmadan komanda işi yavaşıyır; yanlış merge strategy seçimi commit tarixçəsini qarışdırır.

## Əsas Əmrlər (Key Commands)

```bash
# Branch-ı hazırkı branch-a merge et
git merge feature/user-auth

# Merge mesajı ilə
git merge feature/user-auth -m "Merge feature/user-auth into develop"

# Fast-forward-u söndür (həmişə merge commit yarat)
git merge --no-ff feature/user-auth

# Yalnız fast-forward (fast-forward mümkün deyilsə, ləğv et)
git merge --ff-only feature/user-auth

# Merge-i ləğv et (konflikt zamanı)
git merge --abort

# Merge-i davam etdir (konflikt həll edildikdən sonra)
git merge --continue

# Squash merge (bütün commit-ləri bir commit-ə yığ)
git merge --squash feature/user-auth
git commit -m "Add user authentication feature"
```

## Nümunələr

### Fast-Forward Merge

Branch yaratdıqdan sonra base branch-da heç bir yeni commit olmazsa, Git sadəcə pointer-i irəli aparır.

```
  ƏVVƏL:

  main:     C1 ── C2 ── C3
                          \
  feature:                 C4 ── C5

  git checkout main
  git merge feature

  SONRA (fast-forward):

  main:     C1 ── C2 ── C3 ── C4 ── C5
                                      ^
                                    main, feature
```

```bash
git checkout main
git merge feature/quick-fix
# Updating a1b2c3d..d4e5f6g
# Fast-forward
#  app/Models/User.php | 5 +++++
#  1 file changed, 5 insertions(+)
```

### No-FF Merge (--no-ff)

Həmişə merge commit yaradır, branch tarixçəsini qoruyur.

```
  ƏVVƏL:

  main:     C1 ── C2 ── C3
                          \
  feature:                 C4 ── C5

  git checkout main
  git merge --no-ff feature

  SONRA (no-ff):

  main:     C1 ── C2 ── C3 ────── M1
                          \       /
  feature:                 C4 ── C5

  M1 = merge commit (iki valideynli)
```

### 3-Way Merge

Hər iki branch-da yeni commit-lər olduqda Git 3-way merge edir: iki branch-ın ucları və onların ortaq əcdadı (common ancestor) müqayisə olunur.

```
  ƏVVƏL:

  main:     C1 ── C2 ── C3 ── C6 ── C7
                          \
  feature:                 C4 ── C5

  Common ancestor: C3

  git checkout main
  git merge feature

  SONRA:

  main:     C1 ── C2 ── C3 ── C6 ── C7 ── M1
                          \                /
  feature:                 C4 ── C5 ──────

  M1 = merge commit
  Git C3 (ancestor), C7 (main tip), C5 (feature tip) müqayisə edir
```

### Merge Conflict

İki branch eyni faylın eyni sətirlərini dəyişdirdikdə konflikt yaranır.

```bash
# main branch-da User.php:
public function getFullName()
{
    return $this->first_name . ' ' . $this->last_name;
}

# feature branch-da User.php:
public function getFullName(): string
{
    return "{$this->first_name} {$this->last_name}";
}

# Merge etdikdə:
git checkout main
git merge feature/update-user

# CONFLICT (content): Merge conflict in app/Models/User.php
# Automatic merge failed; fix conflicts and then commit the result.
```

### Konfliktin Faylda Görünüşü

```php
public function getFullName()
<<<<<<< HEAD
{
    return $this->first_name . ' ' . $this->last_name;
}
=======
: string
{
    return "{$this->first_name} {$this->last_name}";
}
>>>>>>> feature/update-user
```

Marker-lərin mənası:
- `<<<<<<< HEAD`: Hazırkı branch-ın (main) versiyası başlayır
- `=======`: Ayırıcı
- `>>>>>>> feature/update-user`: Merge olunan branch-ın versiyası bitir

### Konflikti Həll Etmək

```php
// Düzgün versiyanı seçin və marker-ləri silin:
public function getFullName(): string
{
    return "{$this->first_name} {$this->last_name}";
}
```

```bash
# Həll olunmuş faylı stage et
git add app/Models/User.php

# Merge-i tamamla
git commit
# və ya
git merge --continue

# Commit mesajı avtomatik yaradılacaq:
# "Merge branch 'feature/update-user' into main"
```

### Merge Strategiyaları

```bash
# Recursive (default) - ən çox istifadə olunan
git merge feature

# Ours - konflikt zamanı həmişə bizim versiyanı götür
git merge -s ours feature
# Qeyd: Bu feature branch-ın BÜTÜN dəyişikliklərini yox sayır!

# Theirs - strategiya deyil, amma recursive-in parametridir
git merge -X theirs feature
# Konflikt zamanı həmişə gələn branch-ın versiyasını götürür

# Ours (recursive parametr olaraq)
git merge -X ours feature
# Konflikt zamanı həmişə bizim versiyanı götürür

# Octopus - 3+ branch-ı birləşdirmək üçün
git merge feature1 feature2 feature3
```

```
  -s ours vs -X ours fərqi:

  -s ours (strategy):
  Feature branch-ın bütün dəyişikliklərini TAMAMILƏ yox sayır.
  Merge commit yaradır amma heç bir dəyişiklik gəlmir.

  -X ours (strategy option):
  Mümkün qədər avtomatik merge edir.
  Yalnız KONFLİKT olan yerlərdə bizim versiyanı seçir.
```

## Vizual İzah (Visual Explanation)

### Merge Növlərinin Müqayisəsi

```
  1. Fast-Forward:     Tarixçə düz xətt olur
     ────────────────────────>

  2. --no-ff:          Merge commit yaranır, branch görünür
     ────────┬────────M────>
              \      /
               ──────

  3. 3-way:            Hər iki branch-da commit var
     ──────┬─────────M────>
            \       /
             ───────

  4. Squash:           Bütün commit-lər bir commit olur
     ────────────────S────>
     (feature branch tarixçəsi görünmür)
```

### Merge Conflict Resolution Axını

```
  git merge feature
       │
       ▼
  Konflikt var? ──NO──> Avtomatik merge ✓
       │
      YES
       │
       ▼
  Faylları redaktə et
  (<<<< ==== >>>> silin)
       │
       ▼
  git add <files>
       │
       ▼
  git merge --continue
       │
       ▼
  Merge tamamlandı ✓
```

## Praktik Baxış

### Migration Konfliktləri

```bash
# Developer A: 2024_01_15_100000_add_phone_to_users.php
# Developer B: 2024_01_15_100001_add_avatar_to_users.php

# Merge zamanı migration faylları adətən konflikt yaratmır
# (fərqli fayl adları), amma migration sırası problemi ola bilər.

# Merge sonrası:
php artisan migrate:status   # Statusu yoxla
php artisan migrate          # Yeni migration-ları işlət
```

### Route Konfliktləri

```php
// main branch - routes/api.php:
Route::prefix('v1')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('orders', OrderController::class);
});

// feature branch - routes/api.php:
Route::prefix('v1')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('products', ProductController::class);
});

// Merge sonrası düzgün nəticə:
Route::prefix('v1')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('products', ProductController::class);
});
```

### composer.lock Konfliktləri

```bash
# composer.lock konflikti ən çox rastlanan Laravel konfliktlərindən biridir

# Həll:
# 1. Konflikti həll etməyin, əvvəlcə bir tərəfin versiyasını qəbul edin:
git checkout --theirs composer.lock

# 2. Sonra composer update işlədin:
composer update

# 3. Stage və commit:
git add composer.lock composer.json
git merge --continue
```

### Merge Strategiyasını Laravel CI/CD-də İstifadə

```bash
# Staging deploy üçün develop-ə merge
git checkout develop
git merge --no-ff feature/user-auth
git push origin develop
# CI/CD pipeline staging-ə deploy edəcək

# Production deploy üçün main-ə merge
git checkout main
git merge --no-ff develop
git tag -a v1.2.0 -m "Release 1.2.0"
git push origin main --tags
```

## Praktik Tapşırıqlar

1. **Conflict yarat və həll et**
   ```bash
   # main-də UserController dəyiş
   git checkout -b feature/refactor-user
   # eyni faylda fərqli dəyişiklik et
   git checkout main
   git merge feature/refactor-user
   # konflikti manuall həll et
   git add .
   git commit
   ```

2. **Fast-forward vs 3-way müqayisə**
   ```bash
   git merge feature/fast   # FF mümkünsə FF edir
   git merge --no-ff feature/explicit  # həmişə merge commit yarad
   git log --graph --oneline  # fərqi gör
   ```

3. **Squash merge**
   ```bash
   git merge --squash feature/messy-branch
   git commit -m "feat: user profile refactor"
   # feature branch-ın bütün commit-ləri 1-ə sıxışdı
   ```

4. **Merge abort**
   ```bash
   git merge feature/problem
   # konflikti görüb geri çəkilmək istəyirsən
   git merge --abort
   ```

## Interview Sualları

### S1: Fast-forward merge ilə 3-way merge arasında fərq nədir?
**Cavab**: Fast-forward merge yalnız base branch-da heç bir yeni commit olmadıqda baş verir - Git sadəcə branch pointer-i irəli aparır, heç bir merge commit yaranmır. 3-way merge isə hər iki branch-da yeni commit-lər olduqda baş verir - Git ortaq əcdadı (common ancestor) tapır, hər iki branch-ın dəyişikliklərini müqayisə edir və yeni merge commit yaradır.

### S2: `--no-ff` flag-i nə üçün istifadə olunur?
**Cavab**: `--no-ff` fast-forward mümkün olsa belə merge commit yaradır. Bu, branch tarixçəsini qoruyur - `git log --graph`-da branch-ın harada ayrıldığını və merge olduğunu görmək olar. GitFlow strategiyasında bu vacibdir. Branch-sız fast-forward merge-də tarixçədə branch olduğu görünmür.

### S3: Merge conflict necə həll olunur?
**Cavab**: 1) `git status` ilə konfliktli faylları tapın. 2) Faylları açın, `<<<<<<<`, `=======`, `>>>>>>>` marker-lərini tapın. 3) Düzgün kodu seçin, marker-ləri silin. 4) `git add` ilə həll olunmuş faylları stage edin. 5) `git merge --continue` və ya `git commit` ilə merge-i tamamlayın. Alternativ olaraq `git mergetool` vizual alət istifadə edə bilərsiniz.

### S4: `git merge --squash` nə edir?
**Cavab**: Feature branch-ın bütün commit-lərini working directory-yə tətbiq edir amma commit yaratmır. Sonra siz tək commit ilə bütün dəyişiklikləri commit edə bilərsiniz. Bu, tarixçəni təmiz saxlayır amma branch tarixçəsini itirir. PR-larda "Squash and merge" bunun ekvivalentidir.

### S5: `-s ours` ilə `-X ours` arasında fərq nədir?
**Cavab**: `-s ours` (strategy) merge olunan branch-ın BÜTÜN dəyişikliklərini yox sayır - merge commit yaradır amma heç bir dəyişiklik gətirmir. `-X ours` (strategy option) isə mümkün qədər avtomatik merge edir, yalnız konflikt olan yerlərdə bizim versiyanı seçir. `-X ours` daha çox istifadə olunur.

### S6: Merge-i necə ləğv edirsiniz?
**Cavab**: Konflikt zamanı `git merge --abort` bütün merge prosesini ləğv edir və branch-ı merge-dən əvvəlki vəziyyətə qaytarır. Artıq tamamlanmış merge-i geri almaq üçün `git revert -m 1 <merge-commit>` istifadə olunur.

## Best Practices

1. **`--no-ff` istifadə edin**: Feature branch-ları merge edərkən tarixçəni qorumaq üçün
2. **Merge etməzdən əvvəl branch-ı yeniləyin**: `git pull origin main` ilə base branch-ı yeniləyin
3. **Kiçik, tez-tez merge edin**: Böyük merge-lər daha çox konflikt yaradır
4. **Merge commit mesajını mənalı yazın**: Niyə merge etdiyinizi qeyd edin
5. **CI testlərini merge-dən əvvəl yoxlayın**: `git merge --no-commit` ilə əvvəlcə test edin
6. **Konflikt həlli zamanı testlər işlədin**: Merge sonrası kodun düzgün işlədiyinə əmin olun
7. **`git mergetool` istifadə edin**: VS Code, IntelliJ kimi IDE-lərin merge tool-u konfliktləri həll etməyi asanlaşdırır
8. **Squash merge-i uyğun yerdə istifadə edin**: Çox kiçik, WIP commit-ləri olan branch-lar üçün squash daha təmiz tarixçə verir

## Əlaqəli Mövzular

- [02-git-branching.md](02-git-branching.md) — branch-lar
- [06-git-rebasing.md](06-git-rebasing.md) — merge-ə alternativ
- [16-git-troubleshooting.md](16-git-troubleshooting.md) — merge problemlərini həll etmək
