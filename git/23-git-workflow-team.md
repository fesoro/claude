# Team Git Workflow

## Nədir? (What is it?)

**Team Git Workflow** - komandada Git-in effektiv istifadəsi üçün razılaşdırılmış konvensiyalar, qaydalar və proseslər toplusudur. Branch adlandırma, commit mesajları, PR prosesi, code review kultürü və protected branches bu workflow-un əsas komponentləridir.

```
Team Workflow Pyramidası:
                ┌─────────────┐
                │  Mədəniyyət │       ← Trust, respect, learning
                └─────────────┘
              ┌─────────────────┐
              │    Proseslər    │     ← PR, review, release
              └─────────────────┘
          ┌─────────────────────────┐
          │     Konvensiyalar       │   ← Branch/commit naming
          └─────────────────────────┘
      ┌─────────────────────────────────┐
      │         Tools & Otomasiya       │  ← CI/CD, linters, hooks
      └─────────────────────────────────┘
```

### Vacib Komponentlər

```
┌──────────────────────────────────────────────────┐
│ 1. Branch Naming Convention                      │
│ 2. Commit Message Convention (Conventional)     │
│ 3. PR Process & Templates                       │
│ 4. Code Review Culture                          │
│ 5. Protected Branches & Rules                   │
│ 6. Code Owners                                  │
│ 7. Release Process                              │
│ 8. Hotfix Process                               │
└──────────────────────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### Branch Management

```bash
# Branch yaratma konvensiyaları
git checkout -b feature/JIRA-123-user-auth
git checkout -b bugfix/JIRA-456-login-error
git checkout -b hotfix/JIRA-789-payment-crash
git checkout -b chore/update-dependencies
git checkout -b docs/api-reference

# Branch-ları listlə
git branch -a                      # Hamısı (local + remote)
git branch --merged main           # Main-ə merge olunanlar
git branch --no-merged main        # Merge olunmamışlar
git branch -vv                     # Remote tracking ilə

# Köhnə branch-ları təmizlə
git branch --merged main | grep -v main | xargs git branch -d
git remote prune origin            # Silinmiş remote-ları təmizlə

# Commit imzalama (signed commits)
git config --global commit.gpgsign true
git config --global user.signingkey <GPG-KEY-ID>
git commit -S -m "feat: signed commit"
```

### Commit Message (Conventional Commits)

```bash
# Format: <type>(<scope>): <subject>
git commit -m "feat(auth): add Google OAuth login"
git commit -m "fix(api): resolve N+1 in user endpoint"
git commit -m "docs(readme): update installation steps"
git commit -m "refactor(user): extract service class"
git commit -m "test(order): add edge case tests"
git commit -m "chore(deps): update Laravel to 11.5"
git commit -m "style(pint): format code with Laravel Pint"
git commit -m "perf(query): optimize user search query"
git commit -m "ci(github): add security scan workflow"
git commit -m "build(docker): update PHP image to 8.3"
git commit -m "revert: revert feat(auth): Google OAuth"

# Breaking change
git commit -m "feat(api)!: change user endpoint response format

BREAKING CHANGE: user.id is now UUID instead of integer"
```

### Code Review

```bash
# gh CLI ilə
gh pr list                         # Açıq PR-lar
gh pr view 42                      # PR #42
gh pr checkout 42                  # PR-i checkout et
gh pr review 42 --approve
gh pr review 42 --request-changes -b "Tests lazımdır"
gh pr review 42 --comment -b "Nice work!"

# PR-da konflikti yoxla
gh pr view 42 --json mergeable
```

## Praktiki Nümunələr (Practical Examples)

### 1. Branch Naming Convention

```bash
# Format: <type>/<ticket>-<short-description>

# Feature
feature/JIRA-123-user-authentication
feature/SHOP-456-add-payment-gateway
feature/AUTH-789-google-oauth

# Bug fix
bugfix/JIRA-101-fix-login-validation
fix/SHOP-202-cart-total-calculation

# Hotfix (production-dan)
hotfix/CRITICAL-payment-crash
hotfix/SEC-001-xss-vulnerability

# Refactoring
refactor/extract-user-service
refactor/JIRA-300-clean-auth-module

# Documentation
docs/api-v2-reference
docs/deployment-guide

# Chore (housekeeping)
chore/update-laravel-11
chore/cleanup-unused-deps

# Release
release/v2.5.0
release/v3.0.0-beta

# Experiment
spike/investigate-redis-cluster
experiment/try-livewire
```

### 2. Conventional Commits Spesifikasiyası

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Type-lar:**
```
feat:     Yeni feature
fix:      Bug fix
docs:     Sənəd dəyişikliyi
style:    Format, whitespace (kod işini dəyişmir)
refactor: Code refactoring
perf:     Performance yaxşılaşdırma
test:     Test əlavə/yeniləmə
chore:    Build, tools, dependencies
ci:       CI/CD dəyişikliyi
build:    Build sistemi
revert:   Əvvəlki commit-i ləğv etmək
```

**Nümunələr:**
```bash
feat(auth): add password reset via email

Implement password reset flow:
- POST /password/email - send reset link
- POST /password/reset - reset password with token
- Token expires in 60 minutes

Closes #234
Co-authored-by: Jane Doe <jane@example.com>

---

fix(api): resolve N+1 query in user listing

Users endpoint was executing 1 + N queries for posts.
Added eager loading to fetch in 2 queries.

Before: 101 queries for 100 users
After: 2 queries

Fixes #456

---

feat(api)!: migrate user IDs to UUID

BREAKING CHANGE: User.id type changed from integer to UUID.
All API consumers must update their client code.

Migration path:
1. Old endpoints return both `id` (int) and `uuid` until v3.0
2. New clients should use `uuid`
3. v3.0 will drop `id` field

Closes #789
```

### 3. commitlint Setup

```bash
# commitlint quraşdır
npm install --save-dev @commitlint/cli @commitlint/config-conventional
```

```javascript
// commitlint.config.js
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [2, 'always', [
      'feat', 'fix', 'docs', 'style', 'refactor',
      'perf', 'test', 'chore', 'ci', 'build', 'revert'
    ]],
    'scope-enum': [2, 'always', [
      'auth', 'api', 'ui', 'db', 'deps', 'config'
    ]],
    'subject-case': [2, 'always', 'lower-case'],
    'subject-max-length': [2, 'always', 72],
    'body-max-line-length': [2, 'always', 100]
  }
};
```

```bash
# Husky ilə hook
npx husky add .husky/commit-msg 'npx commitlint --edit $1'
```

### 4. Code Review Checklist

```markdown
## Review Checklist

### Functionality
- [ ] Kod tələbi yerinə yetirir?
- [ ] Edge case-lər işlənib? (null, empty, böyük data)
- [ ] Error handling düzgündür?
- [ ] Backward compatibility qorunur?

### Security
- [ ] SQL injection yoxdur (prepared statements)
- [ ] XSS yoxdur (output escaping)
- [ ] CSRF qorunur
- [ ] Secrets hardcoded deyil
- [ ] Authentication/authorization doğrudur

### Performance
- [ ] N+1 query yoxdur
- [ ] Index-lər var
- [ ] Cache lazım olduqda istifadə olunur
- [ ] Böyük datalarda pagination var
- [ ] Uzun task-lar queue-da

### Tests
- [ ] Unit testlər var
- [ ] Feature testlər var
- [ ] Coverage kifayətdir (>80%)
- [ ] Testlər CI-da keçir

### Code Quality
- [ ] Kod oxunaqlıdır
- [ ] Adlar aydındır (dəyişən, method)
- [ ] DRY (təkrar yoxdur)
- [ ] SOLID prinsipləri
- [ ] Style guide-a uyğun

### Documentation
- [ ] Method-lar docblock-lanıb
- [ ] README yenilənib (lazım olduqda)
- [ ] API docs yenilənib
- [ ] Migration guide (breaking change varsa)
```

### 5. GitHub Branch Protection Rules

```yaml
# main branch
Branch: main
Rules:
  - Require PR before merging: ✓
    - Required approvals: 2
    - Dismiss stale reviews: ✓
    - Require CODEOWNERS review: ✓
  - Require status checks: ✓
    - Strict (up-to-date): ✓
    - Checks:
      - ci/lint
      - ci/test
      - ci/build
      - ci/security
  - Require conversation resolution: ✓
  - Require signed commits: ✓
  - Require linear history: ✓
  - Include administrators: ✓
  - Restrict force push: ✓
  - Restrict deletions: ✓

# develop branch
Branch: develop
Rules:
  - Require PR: ✓
    - Approvals: 1
  - Status checks: ✓
  - Force push: disabled
```

### 6. CODEOWNERS Faylı

```bash
# .github/CODEOWNERS

# Hər şeyin default owner-i
* @tech-lead

# Backend (Laravel)
*.php @backend-team
/app/ @backend-team
/database/ @backend-team @database-team
/config/ @backend-team @tech-lead
/routes/ @backend-team

# Frontend
*.vue @frontend-team
*.ts @frontend-team
/resources/js/ @frontend-team

# Security-sensitive
/app/Http/Middleware/ @security-team
/config/auth.php @security-team
/.env.example @security-team @tech-lead

# DevOps
/.github/ @devops-team
/docker/ @devops-team
Dockerfile @devops-team
/nginx.conf @devops-team

# Documentation
/docs/ @docs-team
README.md @tech-lead

# Tests
/tests/ @qa-team @backend-team
```

### 7. Release Workflow

```bash
# Release branch yarat
git checkout -b release/v2.5.0 develop

# Versiya yenilə
# composer.json, package.json, CHANGELOG.md
git add .
git commit -m "chore(release): bump version to 2.5.0"

# Son testlər və düzəlişlər
git commit -m "fix(api): resolve critical bug found in QA"

# Main-ə merge
git checkout main
git merge --no-ff release/v2.5.0
git tag -a v2.5.0 -m "Release v2.5.0"
git push origin main --tags

# Develop-a geri merge
git checkout develop
git merge --no-ff release/v2.5.0
git push origin develop

# Release branch-ı sil
git branch -d release/v2.5.0
git push origin --delete release/v2.5.0
```

### 8. PR Template (Conventional)

```markdown
<!-- .github/pull_request_template.md -->

## Description

<!-- Nə və niyə etdiniz -->

## Type of Change

- [ ] Bug fix (non-breaking)
- [ ] New feature (non-breaking)
- [ ] Breaking change (fix/feature that breaks existing)
- [ ] Documentation update
- [ ] Refactoring (no functional change)
- [ ] Performance improvement

## Related Issues

Closes #
Related to #

## How Has This Been Tested?

- [ ] Unit tests
- [ ] Feature tests
- [ ] Manual testing
- [ ] Integration tests

### Test Configuration
- PHP Version:
- Laravel Version:
- DB:

## Checklist

- [ ] Code follows style guidelines
- [ ] Self-review done
- [ ] Comments added where necessary
- [ ] Documentation updated
- [ ] No new warnings
- [ ] Tests added/updated
- [ ] All tests pass
- [ ] Dependent changes merged

## Screenshots (if applicable)

| Before | After |
|--------|-------|
|        |       |
```

## Vizual İzah (Visual Explanation)

### Team Workflow

```
  Developer 1                    Developer 2
       │                              │
       │ git checkout -b              │ git checkout -b
       │ feature/USR-101              │ feature/USR-102
       │                              │
       v                              v
  ┌─────────┐                    ┌─────────┐
  │ feature │                    │ feature │
  │ USR-101 │                    │ USR-102 │
  └────┬────┘                    └────┬────┘
       │ commit, commit              │ commit, commit
       │ push                        │ push
       │                             │
       v                             v
  ┌──────────┐                  ┌──────────┐
  │  PR #1   │                  │  PR #2   │
  └────┬─────┘                  └─────┬────┘
       │                              │
       ├── CI checks ✓                ├── CI checks ✓
       ├── Code review ✓              ├── Code review ✓
       ├── CODEOWNERS ✓               ├── CODEOWNERS ✓
       │                              │
       v                              v
       └──────────┐   ┌───────────────┘
                  v   v
              ┌──────────┐
              │   main   │  ← protected
              └─────┬────┘
                    │
                    v
              ┌──────────┐
              │   Prod   │
              └──────────┘
```

### Commit Message Anatomy

```
feat(auth): add Google OAuth login
 │     │        │
 │     │        └─> Subject (imperative, lowercase, < 72 chars)
 │     │
 │     └─> Scope (hansı modul)
 │
 └─> Type (feat/fix/docs/...)

Body (optional):
Implement Google OAuth 2.0 authentication flow:
- Add Socialite package
- New /auth/google routes
- GoogleAuthService class

Footer (optional):
Closes #234
Co-authored-by: Jane Doe <jane@example.com>
BREAKING CHANGE: User table has new google_id column
```

### Review Kültürü

```
┌────────────────────────────────────────────┐
│           YAXŞI REVIEW                     │
├────────────────────────────────────────────┤
│ ✓ Konstruktiv, hörmətli                   │
│ ✓ Kodu review edir, insanı yox            │
│ ✓ Suallarla aydınlıq gətirir              │
│ ✓ Nümunə kod verir                        │
│ ✓ "Nit" önəmsiz şeylər üçün               │
│ ✓ Öyrətmək və öyrənmək üçün               │
│ ✓ Approve verir yaxşı koda                │
├────────────────────────────────────────────┤
│           PİS REVIEW                       │
├────────────────────────────────────────────┤
│ ✗ Aqressiv, sərt                          │
│ ✗ Şəxsi hücum                             │
│ ✗ Qeyri-konstruktiv ("pis kod")          │
│ ✗ Nitpicking hər şeyi                    │
│ ✗ Rubber-stamping ("LGTM 👍")             │
│ ✗ Review bloke edir (günlərlə)           │
└────────────────────────────────────────────┘
```

## PHP/Laravel Layihələrdə İstifadə

### 1. Laravel Team Workflow

```bash
# 1. Issue JIRA-dan
# LAR-234: Add user profile picture upload

# 2. Branch yarat
git checkout -b feature/LAR-234-profile-picture develop

# 3. Laravel pattern-lərə uyğun kod yaz
# - FormRequest: UpdateProfilePictureRequest
# - Service: ProfilePictureService
# - API Resource: UserResource
# - Event: ProfilePictureUpdated

# 4. Testlər yaz
php artisan make:test ProfilePictureTest --pest

# 5. Pint ilə format
./vendor/bin/pint

# 6. Local check-lər
php artisan test
./vendor/bin/phpstan analyse

# 7. Commit (Conventional)
git add .
git commit -m "feat(user): add profile picture upload

- Upload up to 5MB JPG/PNG
- Auto-resize to 512x512
- Store in S3
- Old picture auto-deleted

Closes LAR-234"

# 8. Push və PR
git push -u origin feature/LAR-234-profile-picture
gh pr create --fill
```

### 2. Laravel .husky Setup

```bash
# Husky quraşdır
npm install --save-dev husky lint-staged
npx husky install

# Pre-commit hook
npx husky add .husky/pre-commit "npx lint-staged"

# Commit-msg hook
npx husky add .husky/commit-msg 'npx commitlint --edit $1'
```

```json
// package.json
{
  "lint-staged": {
    "*.php": [
      "./vendor/bin/pint",
      "./vendor/bin/phpstan analyse --no-progress --"
    ],
    "*.{js,vue,ts}": [
      "eslint --fix",
      "prettier --write"
    ]
  }
}
```

### 3. Laravel Release Automation

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
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - uses: googleapis/release-please-action@v4
        with:
          release-type: php
          package-name: my-laravel-app
```

```json
// release-please-config.json
{
  "packages": {
    ".": {
      "changelog-path": "CHANGELOG.md",
      "release-type": "php",
      "bump-minor-pre-major": true
    }
  },
  "plugins": [
    {
      "type": "version-bump",
      "fileType": "composer",
      "path": "composer.json"
    }
  ]
}
```

### 4. Laravel Hotfix Workflow

```bash
# Production-da kritik bug!

# 1. Main-dən hotfix branch
git checkout main
git pull
git checkout -b hotfix/CRITICAL-payment-crash

# 2. Bug fix + test
# app/Services/PaymentService.php düzəlt
# tests/Feature/PaymentTest.php test əlavə et

git add .
git commit -m "fix(payment): resolve null pointer in Stripe webhook

Null check added for optional metadata field.
Added test for webhook with missing metadata.

Fixes CRITICAL-001"

# 3. Fast-track PR
git push -u origin hotfix/CRITICAL-payment-crash
gh pr create --title "HOTFIX: Payment crash" \
             --body "Critical production fix" \
             --label "hotfix,priority-critical"

# 4. Emergency review (1 reviewer kifayətdir)
# Normalda 2 reviewer, hotfix-də 1

# 5. Merge və deploy
gh pr merge --squash

# 6. Develop-a geri sink
git checkout develop
git merge main
git push

# 7. Post-mortem yaz
# - Nə oldu?
# - Niyə?
# - Nə öyrəndik?
# - Necə qarşısını alacağıq?
```

## Interview Sualları

### Q1: Conventional Commits nədir?
**Cavab:** Conventional Commits commit mesajları üçün standart format təqdim edir:
```
<type>(<scope>): <subject>
```
Üstünlükləri:
- Avtomatik CHANGELOG generasiyası
- Semantic versioning ilə inteqrasiya
- Commit tarixi oxumaq asandır
- Tool-lar (release-please, semantic-release) dəstəkləyir

### Q2: Branch naming convention niyə vacibdir?
**Cavab:**
1. **Avtomatik klassifikasiya** - CI/CD branch tipinə görə fərqli qaydalar
2. **Ticket izləmə** - JIRA-123 ilə PM tool-da linkləmə
3. **Team kommunikasiyası** - branch adından nə olduğunu anlayırsan
4. **Axtarış** - `git branch | grep feature/`
5. **Hər kəs eyni formatda** - consistency

### Q3: Code review kültürü necə qurulur?
**Cavab:**
1. **Kod review, insan yox** - "bu pis" əvəzinə "bu daha yaxşı olar"
2. **Öyrətmək/öyrənmək** - niyə bu yanaşma?
3. **Konstruktiv feedback** - həll yolu təklif et
4. **Tez-tez review** - PR 1 gündən çox gözləməsin
5. **Şəxsi örnəyə** - senior-lar da review alır
6. **"Nit:" prefix** - önəmsiz şeylər üçün
7. **Approve easy** - yaxşı kodu tərifləyin

### Q4: Protected branches nə edir?
**Cavab:** Main/develop kimi vacib branch-ları qoruyur:
- Direct push qadağan
- PR tələb olunur
- N approval tələbi
- CI keçməlidir
- CODEOWNERS review tələbi
- Signed commits
- Linear history tələbi
- Force push qadağan

### Q5: CODEOWNERS faylı necə işləyir?
**Cavab:** `.github/CODEOWNERS` hansı fayl/qovluğun kimə aid olduğunu təyin edir:
```
*.php @backend-team
/frontend/ @frontend-team
```
PR-da həmin faylları dəyişdikdə:
1. Avtomatik review üçün taglenir
2. Branch protection CODEOWNERS approve tələb edə bilər
3. Məsuliyyət sahələri aydın olur

### Q6: Signed commits niyə vacibdir?
**Cavab:** GPG/SSH ilə imzalanmış commit-lər:
1. **Authenticity** - həqiqətən sən commit etmisən
2. **Integrity** - commit dəyişdirilməyib
3. **Audit trail** - compliance tələbləri
4. **Spoofing qarşısı** - kimsə `git config user.name "CEO"` edə bilməz

```bash
git config --global commit.gpgsign true
git commit -S -m "feat: signed"
```

### Q7: Breaking change-i necə işarələməli?
**Cavab:** Conventional Commits-də 2 yol:
1. `!` type-dan sonra: `feat(api)!: change user id format`
2. Footer-də: `BREAKING CHANGE: description`

```
feat(api)!: change user ID from integer to UUID

BREAKING CHANGE: All clients must update ID type.
Migration: use uuid field alongside id until v3.0.
```

Bu semantic versioning-də MAJOR bump tetikleyir.

### Q8: Review comments-ə necə cavab verilməli?
**Cavab:**
1. **Hər comment-ə cavab ver** - ya "done", ya izah
2. **Konflikt olarsa müzakirə et** - başqa vaxt səhv ola bilər
3. **Öyrən** - niyə bu yanaşma daha yaxşı?
4. **Təvazökar ol** - ego-nu kənara qoy
5. **Resolved etmə əgər əhəmiyyətli dəyişiklik varsa** - reviewer yoxlayır

### Q9: Commit message-ları necə lint edirsən?
**Cavab:** commitlint + husky:
```bash
npm i -D @commitlint/cli @commitlint/config-conventional husky
npx husky install
npx husky add .husky/commit-msg 'npx commitlint --edit $1'
```
Commit-msg hook hər commit-də formatı yoxlayır. Format səhvi varsa commit rədd edilir.

### Q10: Hotfix workflow necə olmalıdır?
**Cavab:**
```bash
# 1. Main-dən hotfix branch
git checkout -b hotfix/CRITICAL-bug main

# 2. Fix + test yaz (şərt)
# 3. Fast-track PR (1 reviewer)
# 4. Emergency deploy
# 5. Main-dən develop-a merge (sync)
# 6. Post-mortem yaz
```
Qeyd: Hotfix hər zaman main-dən branch olur, develop-dan yox!

## Best Practices

### 1. Branch Adları ASCII və Qısa
```bash
✅ feature/user-auth
✅ fix/login-bug
❌ feature/user_authentication_with_google_oauth_and_facebook_login
❌ feature/istifadəçi-auth (unicode)
```

### 2. Commit Mesajı Imperative Mood
```bash
✅ "add user endpoint"
✅ "fix null pointer in auth"
❌ "added user endpoint"
❌ "fixes null pointer"
```

### 3. 1 Commit = 1 Mantıqi Dəyişiklik
```bash
# Pis
git commit -m "add auth, fix bug, update docs"

# Yaxşı
git commit -m "feat(auth): add login endpoint"
git commit -m "fix(api): null check in user controller"
git commit -m "docs(api): update auth examples"
```

### 4. PR Kiçik və Fokuslu
```
< 400 sətir → tez review, keyfiyyətli
> 1000 sətir → "LGTM" rubber-stamp
```

### 5. CI Sürətli
```yaml
# < 10 dəqiqə
# Parallel jobs
# Cache dependencies
# Fail fast
```

### 6. Review Zaman Limiti
```
SLA: 24 saat içində ilk review
Max: 3 gün gözləmə
Əgər blocked, async-dən sync-ə keç (call)
```

### 7. Squash Merge Feature-lər Üçün
```bash
# Feature branch-ın 20 WIP commit-i
# Main-də 1 təmiz commit-ə çevril
gh pr merge 42 --squash
```

### 8. Tag-lər Semver Uyğun
```bash
git tag v1.0.0        # MAJOR.MINOR.PATCH
git tag v1.1.0        # new feature
git tag v1.1.1        # bug fix
git tag v2.0.0        # breaking change
```

### 9. CHANGELOG.md Avtomatik
```bash
# release-please və ya conventional-changelog
# Commit-lərdən avtomatik CHANGELOG yaradılır
```

### 10. Documentation Koddan Ayrı Deyil
```bash
# Hər PR-də lazım olduqda yenilə:
- README.md
- API docs
- Architecture docs
- Migration guide
```
