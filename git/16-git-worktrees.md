# Git Worktrees

## Nədir? (What is it?)

Git worktree, eyni repository-dən birdən çox working directory yaratmaq imkanı verir. Hər worktree fərqli branch-ə checkout oluna bilər, beləcə `git stash` və ya branch dəyişdirmə ehtiyacı olmadan eyni vaxtda bir neçə branch üzərində işləyə bilərsiniz.

```
Normal workflow (1 working directory):

  ~/project/  ← Tək working directory
  └── .git/
  
  Branch dəyişmək üçün:
  git stash → git checkout other-branch → iş gör → 
  git checkout original → git stash pop
  
  Problem: Stash itə bilər, build cache sıfırlanır

Worktree workflow (çoxlu working directory):

  ~/project/           ← main branch (əsas repo)
  └── .git/
  
  ~/project-feature/   ← feature branch (worktree)
  ~/project-hotfix/    ← hotfix branch (worktree)
  
  Hər biri ayrı branch-dədir, eyni vaxtda!
```

### Necə İşləyir?

```
Bir .git directory, çoxlu working directory:

~/.../project/.git/           ← Tək Git database
         │
         ├──→ ~/project/           (main branch)
         ├──→ ~/project-feature/   (feature/search)
         └──→ ~/project-hotfix/    (hotfix/payment)

Bütün worktree-lər eyni Git database-i paylaşır:
  - Eyni commit-lər
  - Eyni branch-lər
  - Eyni remote-lar
  - Eyni stash
```

## Əsas Əmrlər (Key Commands)

### Worktree Yaratma

```bash
# Mövcud branch üçün worktree
git worktree add ../project-feature feature/search

# Yeni branch ilə worktree
git worktree add -b feature/new-api ../project-api

# Detached HEAD ilə (spesifik commit)
git worktree add ../project-v1 v1.0.0

# Tag-dan worktree
git worktree add ../project-release v2.0.0
```

### Worktree Siyahılama

```bash
git worktree list
# /home/user/project          abc1234 [main]
# /home/user/project-feature  def5678 [feature/search]
# /home/user/project-hotfix   ghi9012 [hotfix/payment]

# Detallı format
git worktree list --porcelain
```

### Worktree Silmə

```bash
# Worktree-ni sil
git worktree remove ../project-feature

# Force silmə (dəyişikliklər varsa)
git worktree remove --force ../project-feature

# Əl ilə silinmiş worktree-ləri təmizlə
git worktree prune
```

### Worktree-lər Arasında Hərəkət

```bash
# Terminal 1: main branch
cd ~/project
git log --oneline -5

# Terminal 2: feature branch
cd ~/project-feature
git log --oneline -5

# Hər biri müstəqildir, eyni vaxtda fərqli branch-lər
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Hotfix Edərkən Feature İşini Dayandırmamaq

```bash
# Feature üzərində işləyirsiniz
cd ~/myapp
# ... feature/shopping-cart branch-ində işləyirsiniz ...

# Təcili hotfix lazımdır!
# Stash etmək əvəzinə worktree yaradın:
git worktree add ../myapp-hotfix hotfix/payment-fix

# Hotfix işləyin (ayrı terminal/qovluq)
cd ../myapp-hotfix
# ... bug fix edin ...
git add .
git commit -m "fix: handle null payment response"
git push origin hotfix/payment-fix

# Hotfix bitdi, worktree silin
cd ../myapp
git worktree remove ../myapp-hotfix

# Feature işiniz olduğu kimi qalıb!
```

### Nümunə 2: Code Review üçün

```bash
# PR yoxlamaq istəyirsiniz, amma öz branch-inizdən çıxmaq istəmirsiniz
git fetch origin
git worktree add ../review-pr-42 origin/feature/user-roles

# Ayrı qovluqda PR-ı yoxlayın
cd ../review-pr-42
php artisan test
# Kodu nəzərdən keçirin...

# Review bitdi
cd ../myapp
git worktree remove ../review-pr-42
```

### Nümunə 3: Müqayisə üçün

```bash
# Cari versiya ilə production versiyasını müqayisə edin
git worktree add ../myapp-prod v2.0.0

# İki terminal açın:
# Terminal 1: ~/myapp (develop)
# Terminal 2: ~/myapp-prod (v2.0.0)
# API davranışını müqayisə edin

# Bitirdikdən sonra
git worktree remove ../myapp-prod
```

### Nümunə 4: Eyni Vaxtda Müxtəlif Branch-lərdə Test

```bash
# CI-da müxtəlif branch-ləri paralel test edin
git worktree add ../test-main main
git worktree add ../test-develop develop
git worktree add ../test-feature feature/api-v2

# Hər birində test işlədin
cd ../test-main && php artisan test &
cd ../test-develop && php artisan test &
cd ../test-feature && php artisan test &
wait

# Təmizlik
git worktree remove ../test-main
git worktree remove ../test-develop
git worktree remove ../test-feature
```

## Vizual İzah (Visual Explanation)

### Worktree Strukturu

```
Git Repository Database (.git):
┌─────────────────────────────────────┐
│  objects/  refs/  HEAD  config      │
│  (bütün commit-lər, branch-lər)    │
└────┬──────────┬──────────┬─────────┘
     │          │          │
     ▼          ▼          ▼
┌─────────┐ ┌─────────┐ ┌─────────┐
│ ~/app   │ │~/app-fix│ │~/app-pr │
│ main    │ │ hotfix  │ │ feature │
│         │ │         │ │         │
│ Working │ │ Working │ │ Working │
│Directory│ │Directory│ │Directory│
└─────────┘ └─────────┘ └─────────┘
```

### Worktree vs Stash vs Clone

```
┌─────────────┬─────────────────────────────────────┐
│ Yanaşma     │ Xüsusiyyətlər                       │
├─────────────┼─────────────────────────────────────┤
│ Stash       │ ✗ Branch dəyişmək lazımdır           │
│             │ ✗ Build cache sıfırlanır              │
│             │ ✗ Stash itə bilər                     │
│             │ ✓ Əlavə disk yeri tələb etmir         │
├─────────────┼─────────────────────────────────────┤
│ Worktree    │ ✓ Branch dəyişmə yoxdur              │
│             │ ✓ Build cache qorunur                 │
│             │ ✓ Eyni Git database                   │
│             │ ✗ Disk yeri (working files)            │
├─────────────┼─────────────────────────────────────┤
│ Clone       │ ✓ Tam müstəqil repo                  │
│             │ ✗ Git database dublikat               │
│             │ ✗ Daha çox disk yeri                   │
│             │ ✗ Remote sync lazımdır                 │
└─────────────┴─────────────────────────────────────┘
```

### Tipik İş Axını

```
Əsas iş: feature/cart üzərində (~/app)
         │
         │ Təcili hotfix lazımdır!
         │
         ├──→ git worktree add ../app-hotfix hotfix/x
         │         │
         │         ├── Fix et
         │         ├── Commit
         │         ├── Push
         │         │
         │    git worktree remove ../app-hotfix
         │
         └──→ Feature işinə davam et (heç nə dəyişməyib)
```

## PHP/Laravel Layihələrdə İstifadə

### Laravel Worktree Setup

```bash
# Feature işi zamanı hotfix worktree yaradın
cd ~/projects/laravel-app
git worktree add ../laravel-app-hotfix hotfix/critical-bug

# Hotfix worktree-də
cd ../laravel-app-hotfix

# Dependencies (symlink ilə sürətləndirmə)
composer install
npm install

# .env faylı hər worktree-dən ayrıdır
cp .env.example .env
php artisan key:generate

# Database: ayrı database istifadə edin
# .env: DB_DATABASE=laravel_hotfix

php artisan migrate:fresh --seed
php artisan test
```

### Worktree Helper Script

```bash
#!/bin/bash
# scripts/worktree-create.sh

BRANCH=$1
DIRNAME=${2:-$(echo "$BRANCH" | sed 's/\//-/g')}
WORKTREE_PATH="../$(basename $(pwd))-$DIRNAME"

echo "Creating worktree at $WORKTREE_PATH for branch $BRANCH"

# Worktree yarat
git worktree add "$WORKTREE_PATH" "$BRANCH" 2>/dev/null || \
git worktree add -b "$BRANCH" "$WORKTREE_PATH"

cd "$WORKTREE_PATH"

# Laravel setup
if [ -f "composer.json" ]; then
    echo "Installing Composer dependencies..."
    composer install --quiet

    if [ -f ".env.example" ]; then
        cp .env.example .env
        php artisan key:generate --quiet
        echo "Remember to configure .env (especially DB_DATABASE)"
    fi
fi

if [ -f "package.json" ]; then
    echo "Installing npm dependencies..."
    npm install --silent
fi

echo "Worktree ready at: $WORKTREE_PATH"
```

```bash
# İstifadə
./scripts/worktree-create.sh feature/new-api
./scripts/worktree-create.sh hotfix/urgent-fix
```

### Worktree Cleanup Script

```bash
#!/bin/bash
# scripts/worktree-cleanup.sh

echo "Current worktrees:"
git worktree list

echo ""
echo "Removing all non-main worktrees..."

git worktree list --porcelain | grep "^worktree" | while read -r line; do
    path=$(echo "$line" | cut -d' ' -f2)
    if [ "$path" != "$(pwd)" ]; then
        echo "Removing: $path"
        git worktree remove "$path" --force 2>/dev/null
    fi
done

git worktree prune
echo "Done!"
```

## Interview Sualları

### S1: Git worktree nədir?

**Cavab**: Worktree, eyni Git repository-dən birdən çox working directory yaratmaq imkanı verir. Hər worktree fərqli branch-ə checkout oluna bilər. Bütün worktree-lər eyni `.git` database-ini paylaşır, ona görə commit-lər, branch-lər və remote-lar bütün worktree-lərdə ortaqdır.

### S2: Worktree ilə yeni clone arasındakı fərq nədir?

**Cavab**:
- **Worktree**: Eyni Git database, az disk yeri, branch-lər paylaşılır, sürətli yaratma
- **Clone**: Tam müstəqil repo, çox disk yeri, ayrı remote sync, yavaş

Worktree daha səmərəlidir çünki Git objects kopyalanmır.

### S3: Nə zaman worktree istifadə edərdiniz?

**Cavab**:
1. Təcili hotfix lazım olduqda, amma cari branch-dəki işi pozmaq istəmədikdə
2. PR/code review edərkən öz branch-dən çıxmadan
3. İki branch-in davranışını müqayisə edərkən
4. Uzun sürən test/build zamanı başqa branch-də işləmək üçün
5. CI/CD-də paralel test üçün

### S4: Eyni branch-i iki worktree-də istifadə edə bilərsinizmi?

**Cavab**: Xeyr, bir branch yalnız bir worktree-də checkout oluna bilər. Əgər `main` əsas repo-da checkout edilbisə, başqa worktree-də `main`-ə checkout edə bilməzsiniz. Bu, data corruption-un qarşısını almaq üçündür.

### S5: Worktree-ni silsəniz, branch-ə nə olur?

**Cavab**: Heç nə. Worktree silmək yalnız working directory-ni silir. Branch, commit-lər və bütün Git data olduğu kimi qalır. `git worktree remove` ilə təmiz silmə, `git worktree prune` ilə əl ilə silinmiş worktree-lərin referansını təmizləmək olur.

### S6: Worktree ilə stash arasında hansını seçərdiniz?

**Cavab**: 
- **Stash**: Qısa fasilə üçün (5-10 dəqiqə başqa branch-ə baxmaq)
- **Worktree**: Uzun müddətli paralel iş üçün (hotfix, code review, müqayisə)

Stash daha sadədir amma risk var (unutmaq, itirmək). Worktree daha təmiz həlldir.

## Best Practices

### 1. Worktree-ləri Repo Qonşuluğunda Yaradın

```bash
# Yaxşı: Ana repo ilə eyni səviyyədə
~/projects/myapp/              # Əsas repo
~/projects/myapp-hotfix/       # Worktree
~/projects/myapp-review/       # Worktree

# Pis: İç-içə
~/projects/myapp/worktrees/hotfix/   # Çaşdırıcı
```

### 2. Bitirdikdən Sonra Silin

```bash
# Worktree-ləri yığmayın
git worktree list  # Tez-tez yoxlayın
git worktree remove ../unused-worktree
git worktree prune  # Orphan worktree-ləri təmizləyin
```

### 3. .env Faylına Diqqət

```bash
# Hər worktree-yə ayrı .env lazımdır
# Xüsusilə DB_DATABASE fərqli olmalıdır!

# Əsas repo:    DB_DATABASE=myapp
# Hotfix:       DB_DATABASE=myapp_hotfix
# Feature:      DB_DATABASE=myapp_feature
```

### 4. node_modules/vendor Strategiyası

```bash
# Her worktree-de ayri composer install/npm install lazimdir
# Disk yeri problemdirsa:
# 1. pnpm istifade edin (shared store)
# 2. composer install --no-dev (dev dependency-ler olmadan)
```

### 5. Git Alias Yaradın

```bash
# ~/.gitconfig
[alias]
    wt = worktree
    wta = worktree add
    wtl = worktree list
    wtr = worktree remove
    wtp = worktree prune

# İstifadə
git wta ../myapp-hotfix hotfix/fix
git wtl
git wtr ../myapp-hotfix
```

### 6. Worktree-ni .gitignore-a Əlavə Etməyin

```
Worktree-lər repo xaricindədir (eyni səviyyədə qonşu qovluq).
.gitignore-a əlavə etmək lazım deyil.
Worktree qovluğunun daxilində .gitignore əsas repo ilə eynidir.
```
