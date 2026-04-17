# Git Advanced (Qabaqcıl Git)

## Nədir? (What is it?)

Qabaqcıl Git bilikləri – branching strategies (GitFlow, trunk-based), rebase vs merge, cherry-pick, bisect, hooks, submodules və monorepo idarəçiliyi – təcrübəli DevOps mühəndisi və senior developer üçün vacibdir. Bu anlayışlar komanda birgə işinin səmərəliliyini, kod keyfiyyətini və release prosesini təyin edir.

## Əsas Konseptlər (Key Concepts)

### Branching Strategies

```bash
# 1. Git Flow (Vincent Driessen, 2010)
# Main branch-lər:
#   main      - Production kodu, yalnız release
#   develop   - Integration branch
# Support branch-lər:
#   feature/* - Yeni funksiya (develop-dan ayrılır)
#   release/* - Release hazırlığı
#   hotfix/*  - Production fix (main-dən ayrılır)
#   bugfix/*  - Bug fix

# Git Flow istifadəsi
git flow init
git flow feature start new-feature
git flow feature finish new-feature

git flow release start 1.2.0
git flow release finish 1.2.0

git flow hotfix start critical-bug
git flow hotfix finish critical-bug

# Uygun: Böyük komandalar, scheduled release, versioned products

# 2. Trunk-Based Development
# Yalnız bir long-lived branch: main (trunk)
# Short-lived feature branch-lər (1-2 gün)
# Davamlı main-ə merge (günə bir neçə dəfə)
# Feature flag-lər ilə incomplete feature-ləri gizlədir

git checkout main
git pull
git checkout -b feature/user-auth
# Kiçik dəyişiklik et
git commit -am "Add login form"
git push -u origin feature/user-auth
# PR aç, CI/CD keçsin, merge et
git checkout main && git pull && git branch -d feature/user-auth

# Uygun: CI/CD yüksək, yaxşı test coverage, tez-tez deploy

# 3. GitHub Flow (sadə)
# main + feature branches
# main həmişə deploy-ready

# 4. GitLab Flow
# main + environment branches (staging, production)
```

### Rebase vs Merge

```bash
# MERGE (3-way merge commit yaradır)
# Tarixçəni olduğu kimi saxlayır, branch hekayəsi görünür

git checkout main
git merge feature/new-login

# Tarixçə:
# *   Merge commit
# |\
# | * feature commit 2
# | * feature commit 1
# * | main commit 2
# * | main commit 1
# |/

# REBASE (commit-ləri yeni base-ə köçürür)
# Xətti tarixçə yaradır

git checkout feature/new-login
git rebase main

# Tarixçə (rebase sonrası):
# * feature commit 2 (new hash)
# * feature commit 1 (new hash)
# * main commit 2
# * main commit 1

# INTERACTIVE REBASE (commit-ləri redaktə etmək)
git rebase -i HEAD~5

# Mümkün əmrlər:
# pick    - commit-i qəbul et
# reword  - commit mesajını dəyişdir
# edit    - dayan və redaktə et
# squash  - əvvəlki commit-ə birləşdir, mesajları da
# fixup   - squash kimi, amma mesajı atır
# drop    - commit-i sil

# AMAN DƏYƏR: Public branch-i rebase etməyin!
# Yalnız local və ya öz feature branch-ınızı rebase edin

# Pull --rebase
git pull --rebase origin main
# Əmsalın: git fetch + git rebase origin/main

# Config globally
git config --global pull.rebase true

# RESET növləri
git reset --soft HEAD~1    # Commit sil, dəyişikliklər staged qalır
git reset --mixed HEAD~1   # Commit sil, dəyişikliklər unstaged (default)
git reset --hard HEAD~1    # Commit və dəyişiklikləri tam sil (TƏHLÜKƏLİ)
```

### Cherry-Pick və Bisect

```bash
# CHERRY-PICK: bir branch-dən digərinə konkret commit köçür
git checkout main
git cherry-pick abc1234              # bir commit
git cherry-pick abc1234..def5678     # commit range
git cherry-pick -x abc1234           # "cherry picked from..." mesajı əlavə et
git cherry-pick --no-commit abc1234  # auto-commit etməz

# Conflict olanda
git cherry-pick abc1234
# ... conflict çıxır ...
git add <resolved-files>
git cherry-pick --continue
# və ya
git cherry-pick --abort

# İstifadə nümunəsi: hotfix-i main və develop-a köçürmək
git checkout main
git cherry-pick <hotfix-commit>
git checkout develop
git cherry-pick <hotfix-commit>

# BISECT: binary search ilə bug commit-i tapır
git bisect start
git bisect bad                 # Hazırkı commit buggydır
git bisect good v1.0.0         # Bu versiya yaxşı idi

# Git orta commit-ə check-out edir
# Sən test edirsən:
git bisect good    # və ya
git bisect bad

# Binary search davam edir...
# Sonda: bad commit tapılır
git bisect reset

# Avtomatik bisect (test script ilə)
git bisect start HEAD v1.0.0
git bisect run npm test        # Test uğursuzdursa "bad", uğurludursa "good"
```

### Git Hooks

```bash
# Git hooks = .git/hooks/ qovluğunda script-lər
# Local hooks (shared olmur) və ya framework (Husky, pre-commit)

# Client-side hooks:
# pre-commit        - commit-dən əvvəl (lint, test)
# prepare-commit-msg - commit mesajı hazırlanmadan əvvəl
# commit-msg        - commit mesajı yoxlaması
# post-commit       - commit sonrası
# pre-push          - push-dan əvvəl (test, build)

# Server-side hooks:
# pre-receive       - push qəbulundan əvvəl
# update            - hər branch üçün
# post-receive      - push sonrası (deploy, notification)

# pre-commit hook nümunəsi
cat > .git/hooks/pre-commit <<'EOF'
#!/bin/sh
# PHP syntax check
for file in $(git diff --cached --name-only --diff-filter=ACM | grep '\.php$'); do
    php -l "$file" > /dev/null
    if [ $? -ne 0 ]; then
        echo "Syntax error in $file"
        exit 1
    fi
done

# Run PHP-CS-Fixer
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Run tests
./vendor/bin/phpunit --filter=Unit
EOF
chmod +x .git/hooks/pre-commit

# commit-msg hook (conventional commits)
cat > .git/hooks/commit-msg <<'EOF'
#!/bin/sh
msg=$(cat "$1")
pattern='^(feat|fix|docs|style|refactor|test|chore)(\(.+\))?: .+'

if ! echo "$msg" | grep -qE "$pattern"; then
    echo "Commit message must follow conventional commits format"
    echo "Example: feat(auth): add OAuth support"
    exit 1
fi
EOF
chmod +x .git/hooks/commit-msg

# Husky (JS/TS projects)
npm install -D husky
npx husky init
npx husky add .husky/pre-commit "npm test"
```

### Submodules

```bash
# Submodule = repo içində başqa repo
# Shared libraries, themes, vendor code üçün

# Submodule əlavə et
git submodule add https://github.com/org/library.git libs/library
git commit -m "Add library submodule"

# Submodule-ları klonla
git clone --recurse-submodules https://github.com/org/main-repo.git
# və ya klondan sonra
git submodule init
git submodule update

# Submodule-ları yenilə
git submodule update --remote --merge
git submodule foreach git pull origin main

# Submodule sil
git submodule deinit libs/library
git rm libs/library
rm -rf .git/modules/libs/library

# Alternativ: git subtree
git subtree add --prefix libs/library https://github.com/org/library.git main --squash
git subtree pull --prefix libs/library https://github.com/org/library.git main --squash
# Submodule-dan fərqli: history repo-ya daxil olur, klonlamaqda problem yoxdur
```

### Monorepo

```bash
# Monorepo = birdən çox project bir repo-da
# Nümunə: Google, Facebook, Uber

# Üstünlükləri:
# - Atomic changes (cross-project)
# - Code sharing asanlığı
# - Tek CI/CD setup
# - Dependency management

# Mənfiləri:
# - Repo ölçüsü
# - Build performance
# - Access control (bütün layihələrə giriş)

# Monorepo alətləri:
# Nx (JavaScript/TypeScript)
# Lerna (JavaScript)
# Bazel (Google, multi-lang)
# Rush (Microsoft)
# Turborepo (Vercel)

# PHP/Laravel monorepo (Symfony monorepo-builder)
composer require --dev symplify/monorepo-builder
vendor/bin/monorepo-builder split

# Sparse checkout (yalnız bir qismini klonlamaq)
git clone --filter=blob:none --sparse https://github.com/org/monorepo.git
cd monorepo
git sparse-checkout set packages/api apps/web
```

### Advanced Git Commands

```bash
# REFLOG - silinmiş commit-ləri tapmaq
git reflog                           # Bütün HEAD dəyişiklikləri
git reset --hard HEAD@{2}            # 2 əməl geri qayıt
git checkout -b recover-branch HEAD@{5}

# STASH - işi müvəqqəti saxla
git stash                             # Dəyişiklikləri saxla
git stash push -m "WIP: feature X"   # Ad ilə
git stash push --keep-index          # Staged-ı saxla
git stash list                       # Stash siyahısı
git stash pop                        # Son stash-i geri al və sil
git stash apply stash@{2}            # Konkret stash-i tətbiq et (silməz)
git stash drop stash@{0}             # Stash sil
git stash branch new-branch stash@{0} # Stash-dən branch yarat

# WORKTREE - eyni repo-dan çoxsaylı checkout
git worktree add ../project-hotfix main
cd ../project-hotfix
# Hotfix üzərində işlə, əsas işini dayandırmadan
git worktree list
git worktree remove ../project-hotfix

# BLAME - kim yazıb?
git blame app/User.php                     # Hər sətri kim yazıb
git blame -L 10,20 app/User.php           # Yalnız 10-20 sətr
git log -p -S "functionName" app/User.php  # Funksiyanın yazılma tarixi

# FILTER-BRANCH / git-filter-repo
# History-dən fayl silmək (məs. yanlışlıqla commit olunmuş secret)
git filter-repo --invert-paths --path secrets.env
# AND ya (köhnə üsul):
git filter-branch --index-filter \
  'git rm --cached --ignore-unmatch secrets.env' HEAD

# TAG
git tag v1.2.0                       # Lightweight
git tag -a v1.2.0 -m "Release 1.2"   # Annotated (tövsiyə olunur)
git push origin v1.2.0
git push origin --tags               # Bütün tag-lər
git tag -d v1.2.0                    # Local sil
git push origin :refs/tags/v1.2.0    # Remote sil
```

## Praktiki Nümunələr (Practical Examples)

### Pre-commit hook Laravel üçün

```bash
#!/bin/sh
# .git/hooks/pre-commit

STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')

if [ -z "$STAGED_FILES" ]; then
    exit 0
fi

# 1. PHP syntax check
for file in $STAGED_FILES; do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "PHP syntax error: $file"
        exit 1
    fi
done

# 2. Laravel Pint (code style)
./vendor/bin/pint $STAGED_FILES --test
if [ $? -ne 0 ]; then
    echo "Code style issues. Run: ./vendor/bin/pint"
    exit 1
fi

# 3. PHPStan (static analysis)
./vendor/bin/phpstan analyse $STAGED_FILES --memory-limit=1G
if [ $? -ne 0 ]; then
    echo "PHPStan errors"
    exit 1
fi

# 4. Unit tests (fast only)
./vendor/bin/phpunit --testsuite=Unit --stop-on-failure
if [ $? -ne 0 ]; then
    echo "Unit tests failed"
    exit 1
fi

echo "All pre-commit checks passed"
exit 0
```

### Semantic release workflow

```yaml
# .github/workflows/release.yml
name: Release

on:
  push:
    branches: [main]

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
        with:
          fetch-depth: 0
      
      - name: Get version
        id: version
        run: |
          VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "v0.0.0")
          NEXT=$(echo $VERSION | awk -F. -v OFS=. '{$NF++; print}')
          echo "next=$NEXT" >> $GITHUB_OUTPUT
      
      - name: Create tag
        run: |
          git tag ${{ steps.version.outputs.next }}
          git push origin ${{ steps.version.outputs.next }}
      
      - name: Create release
        uses: softprops/action-gh-release@v1
        with:
          tag_name: ${{ steps.version.outputs.next }}
          generate_release_notes: true
```

## PHP/Laravel ilə İstifadə

### Laravel Husky və lint-staged

```json
// package.json
{
  "scripts": {
    "prepare": "husky install"
  },
  "devDependencies": {
    "husky": "^8.0.0",
    "lint-staged": "^14.0.0"
  },
  "lint-staged": {
    "*.php": [
      "./vendor/bin/pint",
      "./vendor/bin/phpstan analyse --memory-limit=1G"
    ],
    "*.{js,vue}": ["eslint --fix", "prettier --write"]
  }
}
```

```bash
# Husky setup
npm install
npx husky add .husky/pre-commit "npx lint-staged"
npx husky add .husky/commit-msg "npx commitlint --edit $1"
```

### Conventional Commits

```
feat:     Yeni funksionallıq
fix:      Bug fix
docs:     Documentation
style:    Code style (formatting)
refactor: Refactoring
perf:     Performance
test:     Testing
build:    Build system
ci:       CI konfiqurasiyası
chore:    Digər

# Nümunələr:
feat(auth): add OAuth2 Google provider
fix(api): handle null user in payment controller
refactor(user): extract notification logic to service
docs: update API documentation for v2
feat!: change user model (BREAKING CHANGE)

# Body və footer
feat(auth): add password reset

Add password reset functionality via email link.
Uses Laravel built-in password broker.

Closes #123
BREAKING CHANGE: old /reset endpoint removed
```

### Laravel monorepo strukturu

```
monorepo/
├── packages/
│   ├── auth/           # Shared auth package
│   ├── billing/        # Billing package
│   └── notifications/  # Notifications
├── apps/
│   ├── admin/          # Admin Laravel app
│   ├── api/            # API Laravel app
│   └── customer/       # Customer Laravel app
├── composer.json       # Root composer
└── monorepo-builder.php
```

```php
// monorepo-builder.php
use Symplify\MonorepoBuilder\Config\MBConfig;

return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([
        __DIR__ . '/packages',
        __DIR__ . '/apps',
    ]);
    
    $mbConfig->defaultBranch('main');
    
    $mbConfig->dataToAppend([
        'require-dev' => [
            'phpunit/phpunit' => '^10.0',
            'phpstan/phpstan' => '^1.10',
        ],
    ]);
};
```

## Interview Sualları (5-10 Q&A)

**S1: Git Flow və Trunk-Based Development arasında fərq nədir?**
C: Git Flow – çoxsaylı long-lived branch (main, develop, release), strukturlaşdırılmış, scheduled release üçün uyğun. Trunk-Based – yalnız main branch + short-lived feature branch-lər, continuous deployment üçün ideal. Git Flow daha kompleks amma daha çox kontrol verir, Trunk-Based daha sadə və CI/CD ilə yaxşı işləyir. Modern praktika continuous deployment tələb edir, buna görə Trunk-Based populyarlaşır.

**S2: Rebase və Merge arasında nə zaman hansını seçmək?**
C: Merge – feature branch-ın tam tarixçəsini saxlamaq lazımdırsa (PR merge), public branch-lərdə. Rebase – tarixçəni xətti və təmiz saxlamaq, kiçik feature-lər üçün, yalnız local/öz branch-ınızda. "Public history-ni rebase etməyin" qaydası vacibdir – başqaları bu commit-lərə söykənibsə, onları itirə bilərlər.

**S3: git cherry-pick necə işləyir və nə zaman istifadə olunur?**
C: Cherry-pick – bir branch-dən konkret commit-i götürüb başqa branch-ə tətbiq edir. Yeni commit yaradır (fərqli hash). İstifadə: hotfix-i main-ə və develop-a eyni vaxtda köçürmək, release branch-ə seçili commit-lər seçmək, yanlış branch-də commit olunmuş dəyişikliyi düzəltmək.

**S4: git bisect nədir və necə istifadə olunur?**
C: Bisect – binary search ilə bug-ı yaradan commit-i tapan alət. `git bisect start`, `git bisect bad` (indiki), `git bisect good v1.0` (sağlam versiya). Git orta commit-ə keçir, test edirsən, `good/bad` deyirsən. Binary search ilə tez bir zamanda problem commit tapılır. `git bisect run ./test.sh` ilə avtomatlaşdırıla bilər.

**S5: git reset --soft, --mixed, --hard fərqi?**
C: `--soft` – HEAD hərəkət edir, staging və working directory toxunulmaz (commit sil, dəyişiklik staged qalır). `--mixed` (default) – HEAD və staging sıfırlanır, working directory qorunur (commit və staging sil). `--hard` – hər üçü sıfırlanır, dəyişikliklər tam itir (TƏHLÜKƏLİ). Uncommitted dəyişiklikləri itirməmək üçün stash istifadə edin.

**S6: Submodule və subtree arasında fərq nədir?**
C: Submodule – başqa repo-ya pointer (commit hash), ayrıca klonlamaq lazım, history ayrı. Subtree – başqa repo-nun history-sini əsas repo-ya daxil edir, klonlamaq sadə. Submodule daha təmiz amma mürəkkəb, subtree daha rahat amma repo böyüyür. Alternativ: monorepo, npm/composer package.

**S7: git hooks client-side və server-side fərqi?**
C: Client-side – hər developer-in local repo-sunda (pre-commit, commit-msg, pre-push), versiyalanmır, manual qurulur (Husky ilə avtomatik). Server-side – git server-də (pre-receive, post-receive), push qəbulundan əvvəl/sonra işləyir, bütün komandaya tətbiq olunur. CI/CD əsasən server-side hook-un daha kompleks alternativdir.

**S8: Interactive rebase nə üçün istifadə olunur?**
C: `git rebase -i HEAD~5` – son 5 commit-i redaktə etmək imkanı: commit-ləri birləşdirmək (squash/fixup), mesajları dəyişdirmək (reword), sırası dəyişdirmək, silmək (drop). PR göndərməzdən əvvəl "WIP" commit-lərini təmizləmək üçün populyar. Public history-də istifadə etməyin.

**S9: Monorepo və polyrepo üstünlüklərini müqayisə edin.**
C: Monorepo – atomic cross-project change, kod paylaşmaq asan, tek CI/CD, dependency management sadə. Polyrepo – ayrı versiyalama, müstəqil team-lər, kiçik repo ölçüsü, granular access control. Google, Facebook monorepo, əksər open source layihələr polyrepo. Hibrid yanaşma – related layihələr monorepo-da, ayrı domains polyrepo.

**S10: git reflog nə üçün istifadə olunur?**
C: Reflog – local repo-da HEAD-in bütün hərəkət tarixçəsi (commit, reset, rebase, merge). Silinmiş branch-ləri, itirilmiş commit-ləri tapmaq üçün həyat xilaskarıdır. `git reflog` → istədiyin HEAD@{N}-i tap → `git reset --hard HEAD@{N}` və ya yeni branch yarat. Default 90 gün saxlanır.

## Best Practices

1. **Commit atomicity**: Bir commit – bir məntiqi dəyişiklik. Böyük commit-ləri bölün.
2. **Conventional Commits**: `feat:`, `fix:`, `docs:` prefix-ləri istifadə edin (automated changelog üçün).
3. **Meaningful commit messages**: "Fix bug" yox, "fix(auth): handle expired JWT token" yazın.
4. **Branch naming**: `feature/JIRA-123-user-auth`, `bugfix/payment-null-pointer`, `hotfix/security-patch`.
5. **Pull Request review**: Kiçik PR (< 400 sətr), tez review, konkret məqsəd.
6. **Rebase before merge**: Feature branch-ı merge-dən əvvəl main-ə rebase edin (clean history).
7. **Never force push to shared branches**: `--force-with-lease` istifadə edin, `--force` təhlükəlidir.
8. **Pre-commit hooks**: Lint, format, unit test – lokal qaçırın, CI-dən əvvəl.
9. **.gitignore strictly**: `.env`, `node_modules`, `vendor`, `storage/logs` – heç vaxt commit etməyin.
10. **Secrets scanning**: git-secrets, gitleaks istifadə edin, yanlışlıqla secret commit etməmək üçün.
11. **Signed commits**: GPG signing aktivləşdirin (`git config commit.gpgsign true`).
12. **Tags for releases**: Hər release üçün annotated tag (`git tag -a v1.0.0 -m`).
13. **Protected branches**: main və develop branch-lər protected olsun (direct push qadağan).
14. **Squash merge for feature**: Feature branch-ları main-ə squash merge edin (linear history).
15. **Regular cleanup**: Köhnə branch-ları silin (`git branch -d`, `git remote prune`).
