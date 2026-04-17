# Git Cherry-Pick

## Nədir? (What is it?)

Cherry-pick, başqa bir branch-dəki **konkret commit-i** götürüb cari branch-ə tətbiq etmək deməkdir. Merge və ya rebase-dən fərqli olaraq, bütün branch-i birləşdirmək əvəzinə, yalnız lazım olan commit-ləri seçirsiniz.

```
Cherry-pick əvvəl:

main:      A --- B --- C --- D
                  \
feature:           E --- F --- G
                         ↑
                   Bu commit lazımdır

Cherry-pick sonra (F commit-ini main-ə):

main:      A --- B --- C --- D --- F'
                  \
feature:           E --- F --- G

F' = F-in kopyasıdır (yeni SHA hash ilə)
```

**Vacib**: Cherry-pick orijinal commit-i kopyalayır, eyni dəyişiklikləri yeni commit kimi yaradır. Orijinal commit yerində qalır.

## Əsas Əmrlər (Key Commands)

### Tək Commit Cherry-Pick

```bash
# Commit hash ilə cherry-pick
git cherry-pick abc1234

# Commit mesajını dəyişdirmədən
git cherry-pick abc1234

# Commit mesajını redaktə etməklə
git cherry-pick --edit abc1234
# və ya qısa formada
git cherry-pick -e abc1234

# Commit yaratmadan (dəyişiklikləri staging area-ya əlavə edir)
git cherry-pick --no-commit abc1234
# və ya qısa formada
git cherry-pick -n abc1234
```

### Birdən Çox Commit Cherry-Pick

```bash
# Bir neçə commit-i sıra ilə
git cherry-pick abc1234 def5678 ghi9012

# Commit aralığı (E daxil deyil, F, G, H daxildir)
git cherry-pick E..H

# Commit aralığı (E də daxil olmaqla)
git cherry-pick E^..H

# Başqa branch-in son commit-i
git cherry-pick feature-branch
```

### Cherry-Pick Zamanı Konflikt

```bash
# Konflikt baş verdikdə:
# 1. Faylları redaktə edib konflikti həll edin
# 2. Staging area-ya əlavə edin
git add <resolved-files>

# 3. Cherry-pick-i davam etdirin
git cherry-pick --continue

# Cherry-pick-i ləğv edin
git cherry-pick --abort

# Cari commit-i atlayıb növbətiyə keçin
git cherry-pick --skip
```

### Əlavə Seçimlər

```bash
# Orijinal commit referansını mesaja əlavə et
git cherry-pick -x abc1234
# Mesaja "(cherry picked from commit abc1234)" əlavə olunur

# Merge commit-ini cherry-pick (parent nömrəsi lazımdır)
git cherry-pick -m 1 abc1234
# -m 1 = birinci parent (adətən main branch)
# -m 2 = ikinci parent (merge olunan branch)

# İmzalı cherry-pick
git cherry-pick -S abc1234

# Strategiya seçimi
git cherry-pick --strategy=recursive --strategy-option=theirs abc1234
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Hotfix-i Develop Branch-ə Tətbiq Etmək

```
Ssenari: main branch-dəki bug fix develop branch-ə də lazımdır.

main:      ... --- X --- HOTFIX --- Y
                          ↑
develop:   ... --- A --- B --- C
```

```bash
# 1. Hotfix commit hash-ini tapın
git log main --oneline -5
# a1b2c3d Fix: payment gateway timeout issue

# 2. Develop branch-ə keçin
git checkout develop

# 3. Hotfix-i cherry-pick edin
git cherry-pick a1b2c3d

# Nəticə:
# develop:   ... --- A --- B --- C --- HOTFIX'
```

### Nümunə 2: Feature Branch-dən Spesifik Dəyişiklik

```bash
# Feature branch-dəki yalnız database migration commit-ini götürmək
git log feature/user-roles --oneline
# f1e2d3c Add role middleware
# b4a5c6d Create roles migration     <-- Bu lazımdır
# e7f8a9b Update User model

git checkout develop
git cherry-pick b4a5c6d
```

### Nümunə 3: Birdən Çox Commit Aralığı

```bash
# feature branch-dən 3 ardıcıl commit-i götürmək
git log feature/api-v2 --oneline
# aaa1111 Add rate limiting
# bbb2222 Add API authentication     <-- Bunlardan
# ccc3333 Create API routes           <-- başlayaraq
# ddd4444 Setup API base controller   <-- bura qədər
# eee5555 Initial API structure

# ddd4444-dən bbb2222-yə qədər (ddd4444 daxil)
git checkout main
git cherry-pick ddd4444^..bbb2222
```

### Nümunə 4: No-Commit ilə Dəyişiklikləri Birləşdirmək

```bash
# Bir neçə commit-in dəyişikliklərini bir commit-də birləşdirmək
git cherry-pick --no-commit abc1234
git cherry-pick --no-commit def5678
git cherry-pick --no-commit ghi9012

# Hamısını bir commit kimi saxlamaq
git commit -m "feat: combine selected changes from feature branch"
```

## Vizual İzah (Visual Explanation)

### Cherry-Pick Prosesi

```
1. Başlanğıc vəziyyət:

   main:     A --- B --- C
                    \
   feature:          D --- E --- F
                           ↑
                     Bunu istəyirik

2. git checkout main:

   main:     A --- B --- C  ← HEAD
                    \
   feature:          D --- E --- F

3. git cherry-pick E:

   Git E commit-inin diff-ini hesablayır (D → E arasındakı fərq)
   Bu diff-i C commit-inin üzərinə tətbiq edir

   main:     A --- B --- C --- E'  ← HEAD (yeni SHA!)
                    \
   feature:          D --- E --- F  (dəyişməz)
```

### Cherry-Pick vs Merge vs Rebase

```
Əvvəlki vəziyyət (hamısı üçün eyni):

main:      A --- B --- C
                  \
feature:           D --- E --- F

─────────────────────────────────────────────

Cherry-pick E (yalnız bir commit):

main:      A --- B --- C --- E'
                  \
feature:           D --- E --- F

─────────────────────────────────────────────

Merge (bütün branch):

main:      A --- B --- C ─────── M (merge commit)
                  \              /
feature:           D --- E --- F

─────────────────────────────────────────────

Rebase (bütün branch, tarix yenidən yazılır):

main:      A --- B --- C
                        \
feature:                 D' --- E' --- F'
```

### Konflikt Həlli Prosesi

```
git cherry-pick abc1234
        │
        ▼
   ┌─────────────┐
   │  Konflikt    │──── Yox ──── Commit yaradılır ✓
   │  var?        │
   └──────┬──────┘
          │ Bəli
          ▼
   ┌─────────────────┐
   │ Faylları redaktə │
   │ et, konflikti    │
   │ həll et          │
   └──────┬──────────┘
          ▼
   ┌─────────────────┐
   │ git add <files>  │
   └──────┬──────────┘
          ▼
   ┌──────────────────────┐
   │ git cherry-pick       │
   │ --continue            │
   └──────┬───────────────┘
          ▼
   Commit yaradılır ✓
```

## PHP/Laravel Layihələrdə İstifadə

### Ssenari 1: Hotfix-i Bütün Mühitlərə Tətbiq Etmək

```bash
# Production-da bug tapıldı, hotfix main-dədir
# Staging (develop) branch-ə də tətbiq etmək lazımdır

# Hotfix commit-ini tapın
git log main --oneline --grep="fix: payment"
# a1b2c3d fix: handle null payment response from gateway

# Develop-ə tətbiq
git checkout develop
git cherry-pick a1b2c3d

# Release branch-ə də tətbiq (əgər varsa)
git checkout release/2.1
git cherry-pick a1b2c3d
```

### Ssenari 2: Migration-ı Ayrı Branch-ə Çıxarmaq

```bash
# Feature branch-də migration yaratdınız amma
# başqa developer-ə migration ayrıca lazımdır

git log feature/user-management --oneline
# 111aaaa Add user management UI
# 222bbbb Add UserPolicy class
# 333cccc Create users migration and model  <-- Bu lazımdır

git checkout develop
git cherry-pick --no-commit 333cccc

# Migration faylını yoxlayın
ls database/migrations/
# 2026_04_15_create_users_table.php

git add database/migrations/
git commit -m "feat: add users migration from user-management feature"
```

### Ssenari 3: Config Dəyişikliyini Bütün Branch-lərə

```bash
# .env.example-a yeni dəyişən əlavə edilib
git log --oneline --all --grep="REDIS_PREFIX"
# abc1234 chore: add REDIS_PREFIX to .env.example

# Bütün aktiv feature branch-lərə tətbiq
for branch in feature/cart feature/notifications feature/search; do
    git checkout $branch
    git cherry-pick abc1234
done

git checkout develop  # Geri qayıt
```

### Ssenari 4: Yalnız Test Fayllarını Götürmək

```bash
# Feature branch-dən yalnız test commit-ini götürmək
git log feature/api --oneline
# aaa Test: add API endpoint tests   <-- Bu
# bbb feat: implement API endpoints
# ccc feat: add API routes

git checkout develop
git cherry-pick --no-commit aaa

# Yalnız test fayllarını saxla
git reset HEAD .
git add tests/Feature/Api/
git commit -m "test: add API endpoint tests"
git checkout -- .  # Qalan dəyişiklikləri sil
```

## Interview Sualları

### S1: Cherry-pick nədir və nə zaman istifadə olunur?

**Cavab**: Cherry-pick, başqa branch-dəki spesifik commit-i cari branch-ə kopyalayan Git əmridir. Yeni commit yaradılır (fərqli SHA hash ilə), lakin eyni dəyişiklikləri daşıyır.

**İstifadə halları:**
- Hotfix-i bir neçə branch-ə tətbiq etmək
- Feature branch-dən spesifik dəyişikliyi götürmək
- Yanlış branch-ə edilmiş commit-i düzəltmək
- Release branch-ə seçilmiş xüsusiyyətləri əlavə etmək

### S2: Cherry-pick ilə merge arasındakı fərq nədir?

**Cavab**:
| Xüsusiyyət | Cherry-pick | Merge |
|------------|-------------|-------|
| Nə edir | Tək commit kopyalayır | Bütün branch birləşdirir |
| Tarix | Yeni commit yaradır | Merge commit yaradır |
| SHA | Yeni SHA hash | Merge commit-in öz SHA-sı |
| Əlaqə | Branch-lər arasında əlaqə yoxdur | Branch tarixçəsi qorunur |
| İstifadə | Seçmə dəyişikliklər | Tam branch inteqrasiyası |

### S3: Cherry-pick zamanı konflikt olarsa nə edərsiniz?

**Cavab**: 
1. `git status` ilə konfliktli faylları görürəm
2. Faylları açıb `<<<<<<<`, `=======`, `>>>>>>>` markerlərini tapıram
3. Düzgün kodu saxlayıram
4. `git add <file>` ilə həll olunmuş faylları staging-ə əlavə edirəm
5. `git cherry-pick --continue` ilə prosesi davam etdirirəm
6. Əgər ləğv etmək istəsəm `git cherry-pick --abort` istifadə edirəm

### S4: `-x` flag-ı nə edir?

**Cavab**: `git cherry-pick -x abc1234` commit mesajına avtomatik olaraq `(cherry picked from commit abc1234)` əlavə edir. Bu, commit-in haradan gəldiyini izləmək üçün çox faydalıdır, xüsusilə hotfix-ləri müxtəlif branch-lərə tətbiq edərkən.

### S5: Merge commit-ini cherry-pick edə bilərsinizmi?

**Cavab**: Bəli, `-m` flag-ı ilə. Merge commit-in iki parent-i olur, ona görə hansı parent-in perspektivindən diff hesablanacağını göstərmək lazımdır:
```bash
git cherry-pick -m 1 <merge-commit-hash>
# -m 1 = main branch (birinci parent)
# -m 2 = merge olunan branch (ikinci parent)
```

### S6: Cherry-pick-in riskləri nələrdir?

**Cavab**:
- **Dublikat commit-lər**: Eyni dəyişiklik iki fərqli commit kimi mövcud olur, sonra merge zamanı konflikt yarada bilər
- **Kontekst itkisi**: Cherry-pick edilən commit öncəki commit-lərə bağlı ola bilər
- **Tarix mürəkkəbliyi**: Çox istifadə etsəniz, hansı dəyişikliyin haradan gəldiyini izləmək çətinləşir

### S7: `--no-commit` flag-ını nə zaman istifadə edərsiniz?

**Cavab**: Bir neçə commit-in dəyişikliklərini bir commit-də birləşdirmək istədikdə. `--no-commit` dəyişiklikləri staging area-ya əlavə edir, lakin commit yaratmır. Bu, seçilmiş dəyişiklikləri toplu şəkildə commit etmək imkanı verir.

### S8: Yanlış branch-ə commit etmisinizsə, cherry-pick ilə necə düzəldərsiniz?

**Cavab**:
```bash
# 1. Yanlış branch-dəki commit hash-ini not edin
git log --oneline -1
# abc1234 feat: add new feature

# 2. Düzgün branch-ə keçin
git checkout correct-branch

# 3. Commit-i cherry-pick edin
git cherry-pick abc1234

# 4. Yanlış branch-ə qayıdıb commit-i silin
git checkout wrong-branch
git reset --hard HEAD~1
```

## Best Practices

### 1. `-x` Flag-ını İstifadə Edin

```bash
# Hotfix cherry-pick edərkən mənbəni qeyd edin
git cherry-pick -x abc1234
# Bu, commit-in mənşəyini izləməyə kömək edir
```

### 2. Cherry-Pick-i Minimal Saxlayın

```
✅ Yaxşı istifadə halları:
   - Hotfix-i bir neçə branch-ə tətbiq
   - Yanlış branch-ə edilmiş commit-i düzəltmə
   - Spesifik bugfix-i backport etmə

❌ Pis istifadə halları:
   - Bütün feature-ı cherry-pick ilə köçürmə (merge istifadə edin)
   - Mütəmadi olaraq branch-lər arasında sync (rebase istifadə edin)
   - Hər yeniləmə üçün cherry-pick (workflow-u yenidən nəzərdən keçirin)
```

### 3. Konflikt Riskini Azaldın

```bash
# Cherry-pick etməzdən əvvəl branch-in yenilənmiş olduğundan əmin olun
git fetch origin
git pull origin develop

# Sonra cherry-pick edin
git cherry-pick abc1234
```

### 4. Test Edin

```bash
# Cherry-pick-dən sonra testləri işlədin
git cherry-pick abc1234
php artisan test
composer run phpstan
```

### 5. Dokumentasiya Edin

```bash
# PR/commit mesajında cherry-pick-i qeyd edin
git cherry-pick -x abc1234
# Əlavə olaraq PR description-da:
# "Cherry-picked hotfix a1b2c3d from main branch"
```

### 6. Alternativləri Nəzərə Alın

```
Sual: Cherry-pick, yoxsa başqa üsul?

Tək hotfix-i paylamaq         → Cherry-pick ✓
Bütün feature-ı köçürmək      → Merge ✓
Branch-i yeniləmək             → Rebase ✓
Dəyişikliyi geri almaq        → Revert ✓
Müvəqqəti saxlamaq            → Stash ✓
```
