# Git Stashing

## Nədir? (What is it?)

`git stash` dəyişikliklərinizi müvəqqəti olaraq saxlayır (stash edir) ki, branch dəyişdirə biləsiniz və ya başqa iş görə biləsiniz. Stash, working directory və staging area dəyişikliklərini stack strukturunda saxlayır. Daha sonra bu dəyişiklikləri istənilən branch-da bərpa edə bilərsiniz.

Stash, "yarımçıq işi rəfə qoymaq" kimidir - commit etmək istəmirsiniz amma itirmək də istəmirsiniz.

## Əsas Əmrlər (Key Commands)

```bash
# Dəyişiklikləri stash et (tracked files)
git stash

# Mesaj ilə stash et
git stash save "WIP: payment integration halfway done"
# və ya (daha yeni sintaksis)
git stash push -m "WIP: payment integration halfway done"

# Untracked faylları da daxil et
git stash -u
git stash --include-untracked

# İgnore olunmuş faylları da daxil et
git stash -a
git stash --all

# Stash siyahısı
git stash list
# stash@{0}: On feature/payment: WIP: payment integration
# stash@{1}: On develop: Fix config issue
# stash@{2}: WIP on main: a1b2c3d Initial commit

# Son stash-ı tətbiq et (stash qalır)
git stash apply

# Konkret stash-ı tətbiq et
git stash apply stash@{2}

# Son stash-ı tətbiq et və sil
git stash pop

# Konkret stash-ı sil
git stash drop stash@{1}

# Bütün stash-ları sil
git stash clear

# Stash-ın məzmununu gör
git stash show
# app/Models/User.php     | 10 +++++++---
# app/Services/Payment.php |  5 +++++

# Dəyişiklik detalları ilə
git stash show -p stash@{0}
```

### Partial Stash (Hissəvi Stash)

```bash
# Yalnız müəyyən faylları stash et
git stash push -m "stash only models" app/Models/

# İnteraktiv - hər dəyişiklik bloku üçün soruş
git stash push -p -m "partial stash"
# y - stash this hunk
# n - skip this hunk
# s - split into smaller hunks
# q - quit

# Yalnız staged dəyişiklikləri stash et
git stash push --staged -m "only staged changes"
```

### Stash Branch

```bash
# Stash-dan yeni branch yarat
git stash branch feature/payment-v2 stash@{0}
# Bu:
# 1. Stash yaradıldığı commit-dən yeni branch yaradır
# 2. Stash dəyişikliklərini tətbiq edir
# 3. Stash-ı silir (pop kimi)
```

## Praktiki Nümunələr (Practical Examples)

### Ssenari 1: Təcili Hotfix

```bash
# Feature üzərində işləyirsiniz
git checkout feature/user-dashboard
# ... faylları redaktə etdiniz, amma hələ commit etməmisiniz

# Təcili bug bildirildi! main-ə keçməlisiniz
git stash push -m "WIP: dashboard charts halfway done"

# Hotfix branch-a keç
git checkout main
git checkout -b hotfix/login-crash

# Bug-ı düzəlt
vim app/Http/Controllers/AuthController.php
git add .
git commit -m "fix: resolve login crash on invalid session"

# Merge et
git checkout main
git merge --no-ff hotfix/login-crash
git push origin main
git branch -d hotfix/login-crash

# Əvvəlki işə qayıt
git checkout feature/user-dashboard
git stash pop
# Yarımçıq işiniz geri gəldi!
```

### Ssenari 2: Branch Dəyişdirmə Problemi

```bash
# Dəyişiklikləriniz var amma branch dəyişə bilmirsiniz:
git checkout develop
# error: Your local changes to the following files would be overwritten
# by checkout: app/Models/User.php

# Həll 1: Stash et
git stash
git checkout develop
# ... işinizi görün ...
git checkout feature/auth
git stash pop

# Həll 2: Dəyişiklikləri özünüzlə aparın (konflikt yoxdursa)
git checkout develop  # Git buna icazə verər əgər konflikt yoxdursa
```

### Ssenari 3: Stash-dan Seçmə Bərpa

```bash
# Stash-dakı yalnız bir faylı bərpa et
git checkout stash@{0} -- app/Models/User.php

# Stash-ın diff-ini başqa branch-a tətbiq et
git stash show -p stash@{0} | git apply
```

## Vizual İzah (Visual Explanation)

### Stash Stack Strukturu

```
  Stash stack (LIFO - Last In, First Out):

  ┌─────────────────────────────┐
  │ stash@{0}: WIP on feature   │  <── git stash pop (bu çıxar)
  ├─────────────────────────────┤
  │ stash@{1}: Fix config       │
  ├─────────────────────────────┤
  │ stash@{2}: Debug attempt    │
  └─────────────────────────────┘

  git stash      -> stack-ın üstünə əlavə edir
  git stash pop  -> stack-ın üstündən çıxarır
  git stash apply -> kopyasını götürür amma stack-da qalır
```

### Stash İş Axını

```
  Working Dir (dirty)
       │
       │ git stash
       ▼
  Working Dir (clean)     Stash Stack
  ┌──────────────┐       ┌──────────┐
  │ (dəyişiklik  │       │ stash@{0}│
  │  yoxdur)     │       │ (saved)  │
  └──────────────┘       └──────────┘
       │                      │
       │ (başqa iş gör)       │
       │                      │
       │ git stash pop        │
       ▼                      ▼
  Working Dir (dirty)     Stash Stack
  ┌──────────────┐       ┌──────────┐
  │ (dəyişikliklər│       │ (boş)   │
  │  geri gəldi) │       │          │
  └──────────────┘       └──────────┘
```

### Apply vs Pop

```
  git stash apply:                    git stash pop:

  Stash Stack:                        Stash Stack:
  ┌──────────┐   kopyala             ┌──────────┐   köçür
  │ stash@{0}│ ─────────>            │ stash@{0}│ ─────────>
  │          │   Working Dir          │  (silir) │   Working Dir
  └──────────┘                        └──────────┘

  Stash qalır!                        Stash silinir!
```

## PHP/Laravel Layihələrdə İstifadə

### Config Dəyişiklikləri ilə İşləmə

```bash
# .env faylında dəyişiklik etdiniz (development üçün)
# Amma .env gitignore-dadır, stash edilmir

# config/ fayllarında dəyişiklik:
# config/database.php - test üçün SQLite istifadə edirsiniz
git stash push -m "local db config" config/database.php

# Branch dəyişdirin, işinizi görün
git checkout hotfix/urgent
# ...
git checkout feature/my-work
git stash pop
```

### Migration Testing

```bash
# Yeni migration yazdınız amma test etmək istəyirsiniz
# əvvəlcə main-dəki migration-ları

git stash push -m "new orders migration" database/migrations/

# Main-ə keç və migrate et
git checkout main
php artisan migrate:fresh --seed

# Test et...

# Geri qayıt
git checkout feature/orders
git stash pop

# Yeni migration-ı test et
php artisan migrate
```

### Composer Dependencies

```bash
# Yeni paket əlavə etdiniz amma branch dəyişməlisiniz
# composer.json və composer.lock dəyişib

git stash push -m "new stripe package" composer.json composer.lock

git checkout develop
composer install  # develop-in dependencies-ini qur

# Geri qayıdın
git checkout feature/payment
git stash pop
composer install  # stash-dakı dependencies-i qur
```

## Interview Sualları

### S1: `git stash` nə edir?
**Cavab**: Working directory və staging area-dakı dəyişiklikləri müvəqqəti saxlayır və working directory-ni təmiz vəziyyətə qaytarır. Dəyişikliklər stash stack-ında saxlanılır və istənilən vaxt bərpa oluna bilər. Commit etmək istəmədiyiniz yarımçıq iş üçün istifadə olunur.

### S2: `git stash apply` ilə `git stash pop` arasında fərq nədir?
**Cavab**: Hər ikisi stash-dakı dəyişiklikləri working directory-yə qaytarır. Fərq: `apply` stash-ı stack-da saxlayır (eyni stash-ı bir neçə branch-da tətbiq edə bilərsiniz), `pop` isə uğurlu tətbiqdən sonra stash-ı stack-dan silir. Konflikt olarsa `pop` da stash-ı silmir.

### S3: Untracked faylları necə stash edirsiniz?
**Cavab**: Default olaraq `git stash` yalnız tracked faylları stash edir. Untracked faylları da daxil etmək üçün `git stash -u` (--include-untracked) istifadə olunur. Gitignore-dakı faylları da daxil etmək üçün `git stash -a` (--all) istifadə olunur.

### S4: Stash konflikt yarada bilərmi?
**Cavab**: Bəli. Stash tətbiq edərkən working directory-də fərqli dəyişikliklər varsa, merge conflict yarana bilər. Bu halda konflikti manual həll etməlisiniz. `pop` zamanı konflikt olarsa, stash silinmir.

### S5: Faylın yalnız bir hissəsini stash etmək olarmı?
**Cavab**: Bəli, `git stash push -p` (--patch) ilə interaktiv olaraq hansı dəyişiklik bloklarını (hunk) stash etmək istədiyinizi seçə bilərsiniz. Həmçinin `git stash push path/to/file` ilə yalnız müəyyən faylları stash edə bilərsiniz.

### S6: `git stash branch` nə edir?
**Cavab**: Stash-ın yaradıldığı commit-dən yeni branch yaradır, stash dəyişikliklərini tətbiq edir və stash-ı silir. Bu, stash-ı tətbiq edərkən konflikt riski olduqda faydalıdır - yeni branch stash-ın yaradıldığı vəziyyətdən başlayır.

## Best Practices

1. **Stash-a mənalı mesaj yazın**: `git stash push -m "WIP: description"` - nə stash etdiyinizi bilmək üçün
2. **Stash-ları uzun müddət saxlamayın**: Stash müvəqqəti həll olmalıdır, uzun müddətli iş üçün branch istifadə edin
3. **`git stash list`-i mütəmadi yoxlayın**: Unudulmuş stash-lar yığılmasın
4. **`pop` əvəzinə `apply` + `drop` istifadə edin**: Əvvəlcə düzgün tətbiq olunduğunu yoxlayın
5. **Partial stash istifadə edin**: Bütün dəyişiklikləri yox, yalnız lazım olanları stash edin
6. **Stash branch istifadə edin**: Stash uzun müddət qalacaqsa, branch-a çevirin
7. **Stash-ı commit alternativi kimi istifadə etməyin**: WIP commit etmək çox vaxt daha yaxşıdır
8. **`git stash clear` ilə ehtiyatlı olun**: Bütün stash-ları birdən silir, geri qaytarmaq olmaz
