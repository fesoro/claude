# GitHub Flow (Middle)

## İcmal

GitHub Flow, GitHub tərəfindən təklif olunan sadə branching strategiyasıdır. GitFlow-dan fərqli olaraq yalnız bir əsas branch (main) və qısa ömürlü feature branch-lərdən ibarətdir. Hər dəyişiklik Pull Request vasitəsilə review olunur və main-ə merge olunduqdan sonra dərhal deploy edilir.

```
GitHub Flow (sadə):

main:  ●──●──●──●──●──●──●──●──●──●──●──●──
        ↑     ↑     ↑           ↑     ↑
        │     │     │           │     │
       ●──●──┘    ●─┘         ●──●──┘  ●─┘
      feature   feature       feature  feature
      branch    branch        branch   branch

GitFlow (mürəkkəb, müqayisə üçün):

main:     ●───────────────●───────────●──
develop:  ●──●──●──●──●──●──●──●──●──●──
           │        ↑         ↑
feature:   ●──●──●──┘   ●──●─┘
release:              ●──┘
hotfix:                        ●──┘
```

### GitHub Flow-un 6 Addımı

```
1. main-dən branch yarat
2. Commit-lər et
3. Pull Request aç
4. Code review al
5. Deploy et (və ya test et)
6. main-ə merge et
```

## Niyə Vacibdir

Startup-lar və SaaS layihələrinin əksəriyyəti GitHub Flow-u seçir: sadədir, CI/CD ilə qüsursuz işləyir, hər PR deploy edilə bilən vəziyyətdə olur. GitFlow-un complexity-si olmadan sürətli delivery imkanı verir. Laravel layihələrindəki günlük deploy ritmi üçün bu model idealdır — main branch həmişə production-a göndərilə bilən vəziyyətdə olur.

## Əsas Əmrlər (Key Commands)

### Tam Workflow

```bash
# 1. Main-dən yeni branch
git checkout main
git pull origin main
git checkout -b feature/user-profile-page

# 2. Dəyişikliklər edin və commit edin
git add .
git commit -m "feat: add user profile page layout"
git commit -m "feat: add profile edit functionality"
git commit -m "test: add profile page tests"

# 3. Remote-a push edin
git push origin feature/user-profile-page

# 4. GitHub-da Pull Request yaradın
gh pr create \
  --title "feat: add user profile page" \
  --body "## Summary
  - Add profile page layout
  - Add edit functionality
  - Add tests

  ## Screenshots
  [profile page screenshot]

  ## Test Plan
  - [ ] Manual test on staging
  - [ ] Automated tests pass"

# 5. Review-dən sonra merge
gh pr merge --squash

# 6. Yerli branch-i silin
git checkout main
git pull origin main
git branch -d feature/user-profile-page
```

### PR İdarəetməsi (gh CLI ilə)

```bash
# PR yaratmaq
gh pr create --title "feat: add search" --body "Description"

# PR-ları siyahılamaq
gh pr list

# PR statusunu yoxlamaq
gh pr status

# PR review etmək
gh pr review 42 --approve
gh pr review 42 --request-changes --body "Please fix X"

# PR merge etmək
gh pr merge 42 --squash
gh pr merge 42 --rebase
gh pr merge 42 --merge

# PR-ı yerli yoxlamaq
gh pr checkout 42
```

### Branch Yeniləmə

```bash
# Feature branch-da main-in dəyişikliklərini almaq
git checkout feature/my-feature
git fetch origin
git rebase origin/main

# Konflikt varsa
git add .
git rebase --continue

# Push (rebase-dən sonra force lazımdır)
git push --force-with-lease origin feature/my-feature
```

## Nümunələr

### Nümunə 1: Tam Feature Development Cycle

```bash
# === Gün 1: İşə Başla ===
git checkout main && git pull origin main
git checkout -b feature/order-tracking

# Model və migration
php artisan make:model OrderTracking -mfc
git add .
git commit -m "feat: add OrderTracking model and migration"

# === Gün 1: Push və Draft PR ===
git push origin feature/order-tracking
gh pr create --title "feat: order tracking system" \
  --body "WIP: Order tracking implementation" \
  --draft

# === Gün 2: Davam et ===
git checkout feature/order-tracking
git pull origin main --rebase

# Controller və routes
git add .
git commit -m "feat: add tracking controller and API routes"

# Views
git add .
git commit -m "feat: add tracking status views"

# Tests
git add .
git commit -m "test: add order tracking feature tests"

# === Gün 2: PR-ı Review üçün Hazır Et ===
git push origin feature/order-tracking
gh pr ready  # Draft-dan çıxar

# === Gün 3: Review feedback-ə cavab ver ===
git add .
git commit -m "fix: address review feedback - improve validation"
git push origin feature/order-tracking

# === Review approve oldukdan sonra ===
gh pr merge --squash --delete-branch
```

### Nümunə 2: Hotfix (GitHub Flow-da)

```bash
# GitHub Flow-da hotfix = normal feature branch
# Fərq: daha kiçik, daha sürətli review

git checkout main && git pull origin main
git checkout -b fix/payment-timeout

# Fix edin
git add .
git commit -m "fix: increase payment gateway timeout to 30s"

git push origin fix/payment-timeout
gh pr create \
  --title "fix: increase payment gateway timeout" \
  --body "Production-da timeout error-ları artıb. Timeout-u 10s-dən 30s-ə artırırıq." \
  --label "hotfix,urgent"

# Sürətli review və merge
gh pr merge --squash
```

### Nümunə 3: Deploy Preview ilə

```bash
# Vercel, Netlify və ya custom preview deploy

git checkout -b feature/new-landing-page
# ... dəyişikliklər ...
git push origin feature/new-landing-page
gh pr create --title "feat: redesign landing page"

# CI/CD avtomatik preview URL yaradır:
# https://preview-feature-new-landing-page.app.com

# PR-da preview link paylaşılır
# QA/designer preview-da yoxlayır
# Approve olduqdan sonra merge → production deploy
```

## Vizual İzah (Visual Explanation)

### GitHub Flow Lifecycle

```
┌──────────────────────────────────────────────────────┐
│                   GitHub Flow                         │
├──────────────────────────────────────────────────────┤
│                                                       │
│  1. Branch Yarat                                      │
│     main ──→ feature/xyz                              │
│                                                       │
│  2. Commit Et                                         │
│     feature/xyz: ●──●──●──●                           │
│                                                       │
│  3. Pull Request Aç                                   │
│     ┌─────────────────────────────────┐               │
│     │ PR #42: feat: add xyz           │               │
│     │ ┌────────┐ ┌─────────┐          │               │
│     │ │ Review │ │   CI    │          │               │
│     │ │Pending │ │ Running │          │               │
│     │ └────────┘ └─────────┘          │               │
│     └─────────────────────────────────┘               │
│                                                       │
│  4. Review + CI                                       │
│     ┌─────────────────────────────────┐               │
│     │ PR #42: feat: add xyz           │               │
│     │ ┌────────┐ ┌─────────┐          │               │
│     │ │   ✓    │ │    ✓    │          │               │
│     │ │Approved│ │ Passed  │          │               │
│     │ └────────┘ └─────────┘          │               │
│     └─────────────────────────────────┘               │
│                                                       │
│  5. Merge to Main                                     │
│     main: ●──●──●──●──[squash merge]──●               │
│                                                       │
│  6. Auto Deploy                                       │
│     main merge → production deploy                    │
│                                                       │
└──────────────────────────────────────────────────────┘
```

### Merge Strategiyaları

```
Squash Merge (tövsiyə olunan):

feature:  A──B──C──D
               ↓
main:     ...──ABCD  (tək commit)

Pros: Təmiz tarixçə, hər PR = 1 commit
Cons: Detallı commit tarixçəsi itir

─────────────────────────────────────

Rebase Merge:

feature:  A──B──C──D
               ↓
main:     ...──A'──B'──C'──D' (xətti tarixçə)

Pros: Xətti tarixçə, commit-lər saxlanır
Cons: Çox commit ola bilər

─────────────────────────────────────

Merge Commit:

feature:  A──B──C──D
               ↓      \
main:     ...────────── M (merge commit)

Pros: Tam tarixçə, branch görünür
Cons: Qarışıq tarixçə, çox merge commit
```

### PR Review Prosesi

```
Author                    Reviewer              CI
  │                          │                   │
  │──── PR yaradır ─────────>│                   │
  │                          │<── Test işlədir ──│
  │                          │                   │
  │<── Comment yazır ────────│    ✓ Tests pass   │
  │                          │                   │
  │──── Fix edir, push ─────>│                   │
  │                          │<── Test işlədir ──│
  │                          │                   │
  │<── Approve ──────────────│    ✓ Tests pass   │
  │                          │                   │
  │──── Merge ───────────────────────────────────>
  │                                              │
  │                          Auto Deploy ────────>
```

## Praktik Baxış

### PR Template (.github/pull_request_template.md)

```markdown
## Nə dəyişdi?
<!-- Qısa təsvir -->

## Niyə dəyişdi?
<!-- Səbəb və ya issue linki -->

## Necə test etmək olar?
<!-- Addımlar -->

## Checklist
- [ ] Tests yazılıb və keçir
- [ ] PHPStan error yoxdur
- [ ] Migration əlavə edilibsə, rollback işləyir
- [ ] API dəyişilibsə, documentation yenilənib
- [ ] .env.example yenilənib (yeni env var varsa)

## Screenshots (UI dəyişiklikləri üçün)
<!-- Əvvəl/sonra screenshots -->
```

### GitHub Actions CI Pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on:
  pull_request:
    branches: [main]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_mysql
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Copy env
        run: cp .env.example .env && php artisan key:generate

      - name: Run tests
        run: php artisan test --parallel --coverage-clover=coverage.xml
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: PHPStan
        run: ./vendor/bin/phpstan analyse

      - name: PHP-CS-Fixer
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### Auto Merge Setup

```yaml
# .github/workflows/auto-merge.yml
name: Auto Merge Dependabot

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  auto-merge:
    if: github.actor == 'dependabot[bot]'
    runs-on: ubuntu-latest
    steps:
      - name: Auto-approve
        uses: hmarr/auto-approve-action@v4

      - name: Auto-merge
        uses: pascalgn/automerge-action@v0.16.3
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          MERGE_METHOD: squash
```

### Branch Protection Settings

```
Repository Settings → Branches → main:

✓ Require a pull request before merging
  ✓ Require approvals: 1
  ✓ Dismiss stale pull request approvals when new commits are pushed
  ✓ Require review from Code Owners

✓ Require status checks to pass before merging
  ✓ Require branches to be up to date before merging
  Required checks:
    - tests
    - phpstan
    - php-cs-fixer

✓ Require conversation resolution before merging

✗ Allow force pushes (DISABLED)
✗ Allow deletions (DISABLED)
```

## Praktik Tapşırıqlar

1. **Tam GitHub Flow cycle**
   ```bash
   git checkout -b feature/stripe-integration
   # Kod yaz, test et
   git push origin feature/stripe-integration
   # GitHub-da PR aç
   # Review keç
   # main-ə merge et
   git checkout main
   git pull
   git branch -d feature/stripe-integration
   ```

2. **CI status check tələbi**
   - GitHub Settings → Branches → Branch protection rules
   - "Require status checks to pass before merging" aktiv et
   - Test suite-ni required check kimi əlavə et

3. **Draft PR workflow**
   ```bash
   git push origin feature/wip
   # GitHub-da "Create draft pull request"
   # Hazır olanda "Ready for review"
   ```

4. **Squash merge policy**
   - Repository Settings → Pull Requests → Allow squash merging aktiv et
   - Main branch-da clean history saxlamaq üçün

## Interview Sualları

### S1: GitHub Flow nədir və GitFlow-dan nə ilə fərqlənir?

**Cavab**: GitHub Flow, yalnız main branch və qısa ömürlü feature branch-lərdən ibarət sadə workflow-dur. GitFlow-dan fərqləri:
- develop, release, hotfix branch-ləri yoxdur
- Main həmişə deploy oluna bilər
- Hər dəyişiklik PR vasitəsilə olur
- Merge = deploy (continuous deployment)
- Daha az ceremony, daha sürətli delivery

### S2: PR-da Squash Merge, Rebase Merge və Merge Commit arasındakı fərq nədir?

**Cavab**:
- **Squash Merge**: Bütün branch commit-lərini bir commit-ə sıxışdırır. Təmiz tarixçə verir, amma detallı commit-lər itir.
- **Rebase Merge**: Branch commit-lərini main-in üzərinə xətti şəkildə qoyur. Linear tarixçə, amma çox commit ola bilər.
- **Merge Commit**: Merge commit yaradır. Branch tarixçəsi qorunur, amma tarixçə qarışıq ola bilər.

Tövsiyə: Squash merge (hər PR = 1 commit, təmiz `git log`).

### S3: Draft PR nədir və nə zaman istifadə olunur?

**Cavab**: Draft PR, hələ review üçün hazır olmayan PR-dır. İstifadə halları:
- Erkən feedback almaq (WIP)
- CI pipeline-ı işə salmaq
- Komandaya nə üzərində işlədiyinizi göstərmək
- Əmin olmadığınız yanaşma haqqında müzakirə başlatmaq

`gh pr create --draft` ilə yaradılır, `gh pr ready` ilə review-a hazır edilir.

### S4: Code review zamanı nələrə diqqət edirsiniz?

**Cavab**:
1. **Funksionallıq**: Kod nəzərdə tutulan işi düzgün edir?
2. **Test**: Yeni kod üçün test yazılıb?
3. **Security**: SQL injection, XSS, auth yoxlamaları
4. **Performance**: N+1 queries, böyük loop-lar
5. **Readability**: Dəyişən adları, kod strukturu
6. **Laravel conventions**: Service layer, Form Request, Resource
7. **Edge cases**: Null handling, empty arrays, boundary values

### S5: Main branch qorunması (protection) niyə vacibdir?

**Cavab**: Branch protection:
- Birbaşa push-u bloklayır (PR tələb edir)
- Code review tələb edir (ən azı 1 approve)
- CI/CD testlərinin keçməsini tələb edir
- Yanlışlıqla force push-un qarşısını alır
- Kod keyfiyyətini və stabilliyi təmin edir

### S6: PR çox böyük olduqda nə edirsiniz?

**Cavab**: Böyük PR-ı kiçik hissələrə bölürəm:
1. Database/model dəyişiklikləri ayrı PR
2. Business logic ayrı PR
3. UI/frontend ayrı PR
4. Test-lər müvafiq PR-lara daxil edilir
5. Feature flag arxasında yarımçıq funksionallıq gizlədilir

İdeal PR ölçüsü: 200-300 dəyişdirilmiş sətir.

### S7: Stale PR-lar (köhnəlmiş) ilə necə davranırsınız?

**Cavab**:
1. Həftəlik PR review toplantıları keçirmək
2. GitHub-da stale bot quraşdırmaq (30 gün sonra xəbərdarlıq)
3. PR-ı kiçik saxlamaq (böyük PR-lar daha çox stale olur)
4. Clear ownership - hər PR-ın müəyyən reviewer-i olmalıdır
5. Deadline qoymaq (2-3 iş günü review üçün)

## Best Practices

### 1. Main Həmişə Deployable Olmalıdır

```
Main branch-a merge olunan hər commit:
  ✓ Bütün testlər keçir
  ✓ Code review olunub
  ✓ CI pipeline pass
  ✓ Production-a deploy oluna bilər
```

### 2. Kiçik və Fokuslu PR-lar

```
✅ Yaxşı PR:
   Başlıq: "feat: add email verification"
   Fayllar: 5-8
   Sətir: ~200
   Review: 30 dəqiqə

❌ Pis PR:
   Başlıq: "feat: user management system"
   Fayllar: 40+
   Sətir: 2000+
   Review: 2+ saat (yəqin ki, düzgün review olunmayacaq)
```

### 3. Descriptive PR Description

```
Yaxşı PR description daxildir:
  ✓ Nə dəyişdi (qısa)
  ✓ Niyə dəyişdi (kontekst)
  ✓ Necə test etmək (addımlar)
  ✓ Screenshots (UI üçün)
  ✓ Breaking changes (varsa)
```

### 4. Sürətli Review Dövrü

```
PR yaradıldı → Review → Merge

İdeal timeline:
  PR yaradıldı:         Saat 10:00
  İlk review:           Saat 12:00 (2 saat)
  Fix + re-review:      Saat 14:00
  Merge:                Saat 14:30

Pis timeline:
  PR yaradıldı:         Bazar ertəsi
  İlk review:           Çərşənbə (2 gün!)
  Fix + re-review:      Cümə
  Merge:                Növbəti həftə
```

### 5. Avtomatlaşdırma

```
Avtomatlaşdırılmalı olan proseslər:
  ✓ CI/CD (test, lint, deploy)
  ✓ Reviewer assignment (CODEOWNERS)
  ✓ Stale PR notification
  ✓ Dependabot auto-merge
  ✓ Preview deploys
  ✓ Release notes generation
```

### 6. CODEOWNERS Faylı

```
# .github/CODEOWNERS
# Hər PR-da avtomatik reviewer assign olunur

# Default
* @team-lead

# Backend
app/ @backend-team
database/ @backend-team
routes/ @backend-team

# Frontend
resources/js/ @frontend-team
resources/css/ @frontend-team

# DevOps
.github/ @devops-team
docker-compose.yml @devops-team
```

## Əlaqəli Mövzular

- [13-gitflow.md](13-gitflow.md) — daha strukturlu alternativ
- [17-trunk-based-development.md](17-trunk-based-development.md) — daha sürətli alternativ
- [15-pull-request-best-practices.md](15-pull-request-best-practices.md) — PR keyfiyyəti
