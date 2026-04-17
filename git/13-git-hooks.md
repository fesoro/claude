# Git Hooks

## Nədir? (What is it?)

Git hooks, Git-in müəyyən hadisələr (events) zamanı avtomatik işlədilən script-lərdir. Commit etmək, push etmək, merge etmək kimi əməliyyatlardan əvvəl və ya sonra custom script-lər icra edə bilərsiniz. Hooks `.git/hooks/` qovluğunda saxlanır.

```
Git Hook İcra Nöqtələri:

  git commit:
  ┌────────────┐   ┌─────────────┐   ┌────────────┐   ┌──────────────┐
  │ pre-commit │──>│ prepare-    │──>│ commit-msg │──>│ post-commit  │
  │            │   │ commit-msg  │   │            │   │              │
  └────────────┘   └─────────────┘   └────────────┘   └──────────────┘
    Kod yoxla       Mesaj hazırla    Mesajı yoxla      Bildiriş göndər

  git push:
  ┌────────────┐                     ┌──────────────┐
  │ pre-push   │────────────────────>│ post-push    │
  └────────────┘                     └──────────────┘
    Test işlət                        Bildiriş

  Server-side (git receive):
  ┌──────────────┐   ┌────────────┐   ┌───────────────┐
  │ pre-receive  │──>│  update    │──>│ post-receive  │
  └──────────────┘   └────────────┘   └───────────────┘
    Yoxla              Branch yoxla     Deploy/bildiriş
```

### Hook Tiplər

```
┌─────────────────────────────────────────────────────┐
│                 Client-Side Hooks                    │
├──────────────────┬──────────────────────────────────┤
│ pre-commit       │ Commit-dən əvvəl (lint, format)  │
│ prepare-commit-  │ Default mesaj hazırlama          │
│   msg            │                                  │
│ commit-msg       │ Commit mesajını yoxlama          │
│ post-commit      │ Commit-dən sonra (bildiriş)      │
│ pre-push         │ Push-dan əvvəl (test)            │
│ pre-rebase       │ Rebase-dən əvvəl                 │
│ post-checkout    │ Checkout-dan sonra               │
│ post-merge       │ Merge-dən sonra                  │
├──────────────────┼──────────────────────────────────┤
│                 Server-Side Hooks                    │
├──────────────────┼──────────────────────────────────┤
│ pre-receive      │ Push qəbul etmədən əvvəl         │
│ update           │ Hər branch yenilənmədən əvvəl    │
│ post-receive     │ Push qəbul edildikdən sonra      │
└──────────────────┴──────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### Manual Hook Yaratma

```bash
# Hook-lar .git/hooks/ qovluğundadır
ls .git/hooks/
# pre-commit.sample  commit-msg.sample  pre-push.sample ...

# Sample-ı aktivləşdirmək üçün .sample uzantısını silin
cp .git/hooks/pre-commit.sample .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

# Yeni hook yaratmaq
touch .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

# Hook-u deaktiv etmək
chmod -x .git/hooks/pre-commit
# və ya silin
```

### Sadə pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Running pre-commit checks..."

# PHP syntax yoxlaması
FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')
if [ -n "$FILES" ]; then
    for FILE in $FILES; do
        php -l "$FILE"
        if [ $? -ne 0 ]; then
            echo "PHP syntax error: $FILE"
            exit 1
        fi
    done
fi

echo "Pre-commit checks passed!"
exit 0
```

### commit-msg Hook

```bash
#!/bin/bash
# .git/hooks/commit-msg

COMMIT_MSG_FILE=$1
COMMIT_MSG=$(cat "$COMMIT_MSG_FILE")

# Conventional Commits formatını yoxla
PATTERN="^(feat|fix|docs|style|refactor|test|chore|perf|ci|build|revert)(\(.+\))?: .{1,72}"

if ! echo "$COMMIT_MSG" | grep -qE "$PATTERN"; then
    echo "ERROR: Commit message format yanlışdır!"
    echo ""
    echo "Düzgün format: type(scope): description"
    echo "Nümunə: feat(auth): add login functionality"
    echo ""
    echo "Tipləri: feat, fix, docs, style, refactor, test, chore, perf, ci, build, revert"
    exit 1
fi

exit 0
```

### pre-push Hook

```bash
#!/bin/bash
# .git/hooks/pre-push

echo "Running tests before push..."

# PHPUnit testlərini işlət
php artisan test --parallel
if [ $? -ne 0 ]; then
    echo "Tests failed! Push cancelled."
    exit 1
fi

# PHPStan
./vendor/bin/phpstan analyse --no-progress
if [ $? -ne 0 ]; then
    echo "PHPStan errors found! Push cancelled."
    exit 1
fi

echo "All checks passed. Pushing..."
exit 0
```

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Husky + lint-staged (Node.js/Laravel Mix)

```bash
# Husky quraşdırma
npm install --save-dev husky lint-staged
npx husky init

# .husky/pre-commit
cat > .husky/pre-commit << 'EOF'
npx lint-staged
EOF

chmod +x .husky/pre-commit
```

```json
// package.json
{
    "lint-staged": {
        "*.php": [
            "./vendor/bin/php-cs-fixer fix",
            "./vendor/bin/phpstan analyse --no-progress"
        ],
        "*.{js,vue}": [
            "eslint --fix",
            "prettier --write"
        ],
        "*.{css,scss}": [
            "prettier --write"
        ]
    }
}
```

### Nümunə 2: PHP Pre-Commit Hook (PHPStan + PHP-CS-Fixer)

```bash
#!/bin/bash
# .git/hooks/pre-commit (və ya .husky/pre-commit)

STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

if [ -z "$STAGED_PHP_FILES" ]; then
    echo "No PHP files staged. Skipping PHP checks."
    exit 0
fi

echo "🔍 Running PHP-CS-Fixer..."
FIXER_OUTPUT=""
for FILE in $STAGED_PHP_FILES; do
    if [ -f "$FILE" ]; then
        ./vendor/bin/php-cs-fixer fix "$FILE" --quiet
        if [ $? -ne 0 ]; then
            FIXER_OUTPUT="$FIXER_OUTPUT\n  - $FILE"
        fi
        # Düzəldilmiş faylı yenidən stage et
        git add "$FILE"
    fi
done

echo "🔍 Running PHPStan..."
echo "$STAGED_PHP_FILES" | xargs ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
if [ $? -ne 0 ]; then
    echo ""
    echo "❌ PHPStan errors found! Fix them before committing."
    exit 1
fi

echo "🔍 Running PHP Syntax Check..."
for FILE in $STAGED_PHP_FILES; do
    if [ -f "$FILE" ]; then
        php -l "$FILE" > /dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "❌ PHP syntax error in: $FILE"
            exit 1
        fi
    fi
done

echo "✅ All PHP checks passed!"
exit 0
```

### Nümunə 3: Composer Script ilə Hook İdarəetmə

```json
// composer.json
{
    "scripts": {
        "post-install-cmd": [
            "@setup-hooks"
        ],
        "post-update-cmd": [
            "@setup-hooks"
        ],
        "setup-hooks": [
            "cp hooks/pre-commit .git/hooks/pre-commit",
            "cp hooks/commit-msg .git/hooks/commit-msg",
            "chmod +x .git/hooks/pre-commit",
            "chmod +x .git/hooks/commit-msg"
        ],
        "pre-commit": [
            "@php-cs-fixer",
            "@phpstan",
            "@test"
        ],
        "php-cs-fixer": "./vendor/bin/php-cs-fixer fix --dry-run --diff",
        "phpstan": "./vendor/bin/phpstan analyse",
        "test": "php artisan test --parallel"
    }
}
```

### Nümunə 4: GrumPHP (PHP Hook Manager)

```bash
# GrumPHP quraşdırma
composer require --dev phpro/grumphp
```

```yaml
# grumphp.yml
grumphp:
    hooks_dir: ~
    hooks_preset: local
    stop_on_failure: true
    tasks:
        phplint: ~
        phpstan:
            level: 6
            configuration: phpstan.neon
        phpcs:
            standard: PSR12
        phpunit:
            testsuite: Unit
        composer:
            no_check_lock: true
        git_commit_message:
            matchers:
                - '/^(feat|fix|docs|style|refactor|test|chore)(\(.+\))?: .{1,72}/'
            case_insensitive: false
            enforce_no_subject_trailing_period: true
            max_subject_width: 72
```

### Nümunə 5: Server-Side Hook (GitLab/GitHub)

```bash
#!/bin/bash
# server-side: pre-receive hook
# Push-da protected branch-ə force push-u bloklayır

while read OLD_REV NEW_REV REF_NAME; do
    # main branch-ə force push yoxla
    if [ "$REF_NAME" = "refs/heads/main" ]; then
        # Force push detection
        MERGE_BASE=$(git merge-base "$OLD_REV" "$NEW_REV" 2>/dev/null)
        if [ "$MERGE_BASE" != "$OLD_REV" ]; then
            echo "ERROR: Force push to main branch is not allowed!"
            exit 1
        fi
    fi

    # Böyük fayl yoxlaması
    MAX_SIZE=10485760  # 10MB
    OBJECTS=$(git rev-list "$OLD_REV..$NEW_REV")
    for OBJ in $OBJECTS; do
        LARGE_FILES=$(git diff-tree -r --diff-filter=ACM "$OBJ" | \
            awk '{print $4, $6}' | while read SIZE FILE; do
                REAL_SIZE=$(git cat-file -s "$SIZE" 2>/dev/null)
                if [ "${REAL_SIZE:-0}" -gt "$MAX_SIZE" ]; then
                    echo "$FILE ($REAL_SIZE bytes)"
                fi
            done)
        if [ -n "$LARGE_FILES" ]; then
            echo "ERROR: File too large (>10MB):"
            echo "$LARGE_FILES"
            exit 1
        fi
    done
done

exit 0
```

### Nümunə 6: post-merge Hook (Dependency Update)

```bash
#!/bin/bash
# .git/hooks/post-merge

# composer.lock dəyişibsə, composer install işlət
CHANGED_FILES=$(git diff-tree -r --name-only --no-commit-id ORIG_HEAD HEAD)

if echo "$CHANGED_FILES" | grep -q "composer.lock"; then
    echo "composer.lock changed. Running composer install..."
    composer install --no-interaction
fi

if echo "$CHANGED_FILES" | grep -q "package-lock.json"; then
    echo "package-lock.json changed. Running npm install..."
    npm install
fi

if echo "$CHANGED_FILES" | grep -q "database/migrations"; then
    echo "New migrations detected. Running migrations..."
    php artisan migrate
fi
```

## Vizual İzah (Visual Explanation)

### Hook İcra Sırası (Commit)

```
Developer: git commit -m "feat: add feature"
           │
           ▼
    ┌──────────────┐
    │  pre-commit  │──── Exit 1 → COMMIT CANCELLED
    │  (lint, test)│
    └──────┬───────┘
           │ Exit 0
           ▼
    ┌───────────────────┐
    │ prepare-commit-msg│
    │ (template hazırla)│
    └──────┬────────────┘
           │
           ▼
    ┌──────────────┐
    │  commit-msg  │──── Exit 1 → COMMIT CANCELLED
    │ (mesaj yoxla)│
    └──────┬───────┘
           │ Exit 0
           ▼
    ┌──────────────┐
    │   COMMIT     │
    │   YARADILIR  │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐
    │ post-commit  │
    │ (bildiriş)   │
    └──────────────┘
```

### Hook Qaçırma Qərarı

```
Hook exit kodu:
  0     = OK, davam et
  1-125 = ERROR, əməliyyatı ləğv et
  
İstisna:
  git commit --no-verify   → pre-commit və commit-msg atlanır
  git push --no-verify     → pre-push atlanır

  ⚠️  --no-verify istifadə etməyin! (CI-da yaxalanacaq)
```

### Layihə Hook Strukturu

```
project/
├── .git/
│   └── hooks/           ← Lokal, .gitignore-da (paylaşılmır)
│       ├── pre-commit
│       ├── commit-msg
│       └── pre-push
│
├── hooks/               ← Repo-da saxlanır (paylaşılır)
│   ├── pre-commit
│   ├── commit-msg
│   └── install.sh       ← Hook-ları .git/hooks-a kopyalayır
│
├── .husky/              ← Husky istifadə edərsə
│   ├── pre-commit
│   └── commit-msg
│
└── grumphp.yml          ← GrumPHP istifadə edərsə
```

## PHP/Laravel Layihələrdə İstifadə

### Tam PHP Pre-Commit Setup

```bash
# 1. Alətləri quraşdırın
composer require --dev friendsofphp/php-cs-fixer
composer require --dev phpstan/phpstan
composer require --dev phpstan/phpstan-laravel

# 2. PHP-CS-Fixer config
cat > .php-cs-fixer.dist.php << 'PHPEOF'
<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
PHPEOF

# 3. PHPStan config
cat > phpstan.neon << 'NEONEOF'
includes:
    - vendor/phpstan/phpstan-laravel/extension.neon

parameters:
    level: 6
    paths:
        - app
    ignoreErrors: []
NEONEOF
```

### Hook Paylaşma Strategiyaları

```bash
# Strategiya 1: core.hooksPath (Git 2.9+)
# Repo-dakı hooks/ qovluğunu istifadə et
git config core.hooksPath hooks/

# Strategiya 2: Composer post-install
# composer.json scripts bölməsinə əlavə edin (yuxarıdakı nümunəyə baxın)

# Strategiya 3: Makefile
# Makefile
install-hooks:
	cp hooks/* .git/hooks/
	chmod +x .git/hooks/*

setup: install-hooks
	composer install
	cp .env.example .env
	php artisan key:generate
	php artisan migrate

# Strategiya 4: Husky (əgər Node.js istifadə edirsinizsə)
npx husky init
```

### Laravel Pint (Laravel-in öz formatteri)

```bash
# Laravel Pint quraşdırma (Laravel 10+)
composer require --dev laravel/pint

# Pre-commit hook-da istifadə
#!/bin/bash
# .git/hooks/pre-commit

STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

if [ -n "$STAGED_PHP_FILES" ]; then
    echo "$STAGED_PHP_FILES" | xargs ./vendor/bin/pint
    echo "$STAGED_PHP_FILES" | xargs git add
fi

exit 0
```

```json
// pint.json
{
    "preset": "laravel",
    "rules": {
        "simplified_null_return": true,
        "no_unused_imports": true,
        "ordered_imports": {
            "sort_algorithm": "alpha"
        }
    }
}
```

## Interview Sualları

### S1: Git hook nədir və nə üçün istifadə olunur?

**Cavab**: Git hook-lar, Git əməliyyatları zamanı avtomatik icra olunan script-lərdir. `.git/hooks/` qovluğunda saxlanır. Əsas istifadə sahələri:
- **pre-commit**: Kod formatı, lint, syntax yoxlaması
- **commit-msg**: Commit mesaj formatını yoxlama
- **pre-push**: Test icra etmə
- **post-merge**: Dependency yeniləmə

Hook exit 0 qaytarsa əməliyyat davam edir, 0-dan fərqli qaytarsa ləğv olunur.

### S2: Client-side və server-side hook-lar arasında fərq nədir?

**Cavab**:
- **Client-side**: Developer-in lokal maşınında işləyir (pre-commit, commit-msg, pre-push). Developer `--no-verify` ilə atlaya bilər.
- **Server-side**: Git server-ində işləyir (pre-receive, update, post-receive). Atlamaq mümkün deyil, məcburi qaydalardır.

Server-side hook-lar daha etibarlıdır çünki bypass oluna bilmir.

### S3: Hook-lar repo ilə paylaşılırmı?

**Cavab**: `.git/hooks/` qovluğu `.git` daxilindədir və Git tərəfindən track olunmur. Paylaşmaq üçün:
1. `hooks/` qovluğunu repo-ya əlavə edib, `git config core.hooksPath hooks/` istifadə etmək
2. Husky (npm) istifadə etmək
3. GrumPHP (composer) istifadə etmək
4. Composer scripts ilə hook-ları kopyalamaq

### S4: `--no-verify` flag-ı nə edir və nə zaman istifadə etmək olar?

**Cavab**: `git commit --no-verify` pre-commit və commit-msg hook-larını atlayır. `git push --no-verify` pre-push hook-unu atlayır. Yalnız aşağıdakı hallarda istifadə etmək olar:
- Hook-da bug var və düzəltmə üzərində işləyirsiniz
- Çox təcili hotfix (amma CI-da yaxalanacaq)

Ümumi istifadə tövsiyə olunmur, hook-lar keyfiyyət qapısıdır.

### S5: Husky nədir?

**Cavab**: Husky, Node.js ekosistemində Git hook-ları idarə etmək üçün alətdir. `.husky/` qovluğunda hook-lar saxlanır və repo ilə paylaşılır. `npm install` zamanı avtomatik quraşdırılır. `lint-staged` ilə birlikdə yalnız staged faylları yoxlamaq imkanı verir.

### S6: Pre-commit hook-da hansı yoxlamaları edərdiniz?

**Cavab** (PHP/Laravel üçün):
1. PHP syntax yoxlaması (`php -l`)
2. Code formatting (PHP-CS-Fixer, Laravel Pint)
3. Static analysis (PHPStan level 6+)
4. Debug statement-lərin yoxlanması (`dd()`, `dump()`, `var_dump()`)
5. `.env` faylının commit edilmədiyini yoxlamaq
6. Böyük faylların commit edilmədiyini yoxlamaq

### S7: GrumPHP nədir?

**Cavab**: GrumPHP, PHP layihələri üçün Git hook manager-dir. YAML konfiqurasiyası ilə idarə olunur. Dəstəklədiyi task-lar: PHPStan, PHP-CS-Fixer, PHPUnit, composer validate, commit message validation və s. `composer install` zamanı avtomatik hook-ları quraşdırır.

### S8: Hook-lar CI/CD-ni əvəz edirmi?

**Cavab**: Xeyr. Hook-lar birinci müdafiə xəttidir (fast feedback), CI/CD isə son müdafiə xəttidir (guaranteed check). Hook-lar `--no-verify` ilə atlanıla bilər, amma CI/CD atlanıla bilməz. İkisi bir-birini tamamlayır:
- Hook: Sürətli feedback (saniyələr), lokal
- CI/CD: Tam yoxlama (dəqiqələr), server-də, bypass olunmaz

## Best Practices

### 1. Hook-ları Sürətli Saxlayın

```
Pre-commit hook idealda:
  ✅ < 5 saniyə (yalnız staged faylları yoxla)
  ⚠️  5-30 saniyə (qəbul edilir)
  ❌ > 30 saniyə (developer-lər --no-verify istifadə edəcək)

Trick: Yalnız staged faylları yoxlayın, bütün layihəni yox
```

### 2. Yalnız Staged Faylları Yoxlayın

```bash
# Bütün faylları yox, yalnız staged olanları
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

# Boş olarsa, keçin
if [ -z "$STAGED_FILES" ]; then
    exit 0
fi
```

### 3. Hook-ları Komanda ilə Paylaşın

```bash
# core.hooksPath layihə README-də qeyd edin
git config core.hooksPath hooks/

# Və ya Makefile-da:
make setup  # Hook-ları quraşdırır
```

### 4. Debug Statement-ləri Yoxlayın

```bash
# Pre-commit hook-a əlavə edin
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')
if echo "$STAGED_FILES" | xargs grep -l "dd(\|dump(\|var_dump(\|print_r(" 2>/dev/null; then
    echo "ERROR: Debug statements found! Remove dd(), dump(), var_dump()"
    exit 1
fi
```

### 5. .env Faylını Qoruyun

```bash
# Pre-commit hook
STAGED_FILES=$(git diff --cached --name-only)
if echo "$STAGED_FILES" | grep -q "^\.env$"; then
    echo "ERROR: .env file should not be committed!"
    echo "Use .env.example instead."
    exit 1
fi
```

### 6. CI/CD ilə Eyni Qaydaları Tətbiq Edin

```
Hook və CI/CD eyni alətləri işlətməlidir:

Hook (lokal, sürətli):
  - PHP-CS-Fixer (yalnız staged fayllar)
  - PHPStan (yalnız staged fayllar)
  - php -l (syntax check)

CI/CD (server, tam):
  - PHP-CS-Fixer (bütün layihə)
  - PHPStan (bütün layihə)
  - PHPUnit (bütün testlər)
  - Security audit
```
