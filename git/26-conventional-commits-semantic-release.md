# Conventional Commits & Semantic Release (Senior)

## İcmal

**Conventional Commits** – kommit mesajları üçün strukturlu spesifikasiyadır. Məqsəd: commit mesajlarını maşın tərəfindən oxuna bilən formada yazmaq və bununla avtomatlaşdırma imkanları yaratmaq.

**Niyə vacibdir?**
- **Avtomatik changelog** generasiyası mümkün olur.
- **Semantic versioning** (MAJOR.MINOR.PATCH) avtomatik müəyyənləşir.
- **Release notes** hazır gəlir.
- **Commit tarixi** sənəd kimi oxunur.
- **CI/CD**-də release prosesini tamamilə avtomatlaşdırır.

**Semantic Release** – Conventional Commits əsasında avtomatik:
1. Versiya nömrəsini təyin edir (v1.2.3 → v1.3.0).
2. CHANGELOG.md yazır.
3. Git tag yaradır və push edir.
4. npm/Packagist/Composer-ə publish edir.
5. GitHub Release yaradır.

---

## Niyə Vacibdir

CI/CD-nin avtomatik version bump etməsi, CHANGELOG yaratması, npm/Composer paket publish etməsi — hamısı commit mesaj formatına əsaslanır. Human error-u azaldır, release prosesini standartlaşdırır; komanda üçün commit tarixçəsi machine-readable olur.

## Conventional Commits Spesifikasiyası

### Struktura
```
<type>(<scope>): <subject>

<body>

<footer>
```

### Nümunə
```
feat(auth): add two-factor authentication

Implement TOTP-based 2FA using Google Authenticator.
Users can enable/disable from profile settings.

Closes #123
BREAKING CHANGE: login endpoint now requires 'code' field
```

### Types
| Type       | Təsvir                                    | Version təsiri |
|------------|-------------------------------------------|----------------|
| `feat`     | Yeni xüsusiyyət                           | MINOR (+0.1.0) |
| `fix`      | Bug fix                                   | PATCH (+0.0.1) |
| `docs`     | Yalnız sənədləşdirmə                      | Yox            |
| `style`    | Formatlaşdırma, whitespace                | Yox            |
| `refactor` | Refactoring (yeni feature/fix yox)        | Yox            |
| `perf`     | Performance təkmilləşdirməsi              | PATCH          |
| `test`     | Test əlavə etmə/düzəltmə                  | Yox            |
| `build`    | Build system, dependency                  | Yox            |
| `ci`       | CI konfiqurasiyası                        | Yox            |
| `chore`    | Digər dəyişikliklər                       | Yox            |
| `revert`   | Əvvəlki kommitin ləğv edilməsi            | Asılıdır       |

### BREAKING CHANGE
MAJOR versiya bump yaradır:
```
feat(api)!: remove /v1/users endpoint

BREAKING CHANGE: /v1/users endpoint removed, use /v2/users instead
```
Alternativ forma: type-dan sonra `!` işarəsi.

---

## Əsas Əmrlər və Alətlər

### Commitlint
```bash
# Quraşdırma
npm install --save-dev @commitlint/cli @commitlint/config-conventional

# commitlint.config.js
module.exports = { extends: ['@commitlint/config-conventional'] };

# Manual yoxlama
echo "feat: add login" | npx commitlint

# Husky ilə inteqrasiya
npx husky add .husky/commit-msg 'npx commitlint --edit $1'
```

### Commitizen (interactive commit)
```bash
# Quraşdırma
npm install --save-dev commitizen cz-conventional-changelog

# package.json
{
  "config": { "commitizen": { "path": "cz-conventional-changelog" } }
}

# İstifadə
git cz
# və ya
npx cz
```

### Semantic Release
```bash
# Quraşdırma
npm install --save-dev semantic-release @semantic-release/changelog \
  @semantic-release/git @semantic-release/github

# CI-də çalışdırma
npx semantic-release
```

---

## Nümunələr

### Nümunə 1: Düzgün Conventional Commit mesajları

```bash
# Feature
git commit -m "feat(cart): add discount code support"

# Bug fix (issue link ilə)
git commit -m "fix(payment): handle timeout on Stripe API

Retry logic added with exponential backoff.
Max 3 retries before failure.

Fixes #456"

# Breaking change
git commit -m "feat(api)!: change user response format

BREAKING CHANGE: 'full_name' replaced with 'first_name' and 'last_name'"

# Refactor
git commit -m "refactor(order): extract OrderCalculator service"

# Chore
git commit -m "chore(deps): update laravel/framework to 11.0"
```

### Nümunə 2: commitlint konfiqurasiyası

`.commitlintrc.js`:
```javascript
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [2, 'always', [
      'feat', 'fix', 'docs', 'style', 'refactor',
      'perf', 'test', 'build', 'ci', 'chore', 'revert'
    ]],
    'subject-case': [2, 'never', ['pascal-case', 'upper-case']],
    'subject-max-length': [2, 'always', 72],
    'body-max-line-length': [2, 'always', 100],
    'scope-enum': [2, 'always', [
      'auth', 'cart', 'payment', 'order', 'user', 'api', 'deps'
    ]]
  }
};
```

Yanlış mesaj:
```bash
$ git commit -m "added login feature"
⧗   input: added login feature
✖   subject may not be empty [subject-empty]
✖   type may not be empty [type-empty]
✖   found 2 problems, 0 warnings
```

### Nümunə 3: Commitizen ilə interactive commit

```bash
$ git cz

? Select the type of change:
  feat:     A new feature
  fix:      A bug fix
  docs:     Documentation only changes
> refactor: A code change that neither fixes a bug nor adds a feature

? What is the scope (press enter to skip): cart

? Write a short description (max 72): extract pricing logic to service

? Provide longer description (optional):
  Moves discount and tax calculation to PricingService.
  Improves testability and separation of concerns.

? Are there breaking changes? No
? Does this change affect any open issues? Yes
? Issue references: Refs #234

→ refactor(cart): extract pricing logic to service
```

### Nümunə 4: Semantic Release konfiqurasiyası

`.releaserc.json`:
```json
{
  "branches": ["main", {"name": "beta", "prerelease": true}],
  "plugins": [
    "@semantic-release/commit-analyzer",
    "@semantic-release/release-notes-generator",
    ["@semantic-release/changelog", {
      "changelogFile": "CHANGELOG.md"
    }],
    ["@semantic-release/git", {
      "assets": ["CHANGELOG.md", "composer.json"],
      "message": "chore(release): ${nextRelease.version} [skip ci]\n\n${nextRelease.notes}"
    }],
    "@semantic-release/github"
  ]
}
```

### Nümunə 5: GitHub Actions ilə avtomatik release

`.github/workflows/release.yml`:
```yaml
name: Release

on:
  push:
    branches: [main]

jobs:
  release:
    runs-on: ubuntu-latest
    permissions:
      contents: write
      issues: write
      pull-requests: write
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
          persist-credentials: false

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: 20

      - name: Install
        run: npm ci

      - name: Release
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: npx semantic-release
```

### Nümunə 6: Avtomatik generasiya olunan CHANGELOG

Kommitlər:
```
feat(auth): add OAuth2 support
fix(cart): handle empty discount
perf(db): add index on users.email
feat(api)!: remove /v1 endpoints
```

Avtomatik CHANGELOG.md:
```markdown
# Changelog

## [2.0.0] - 2026-04-17

### ⚠ BREAKING CHANGES
- **api**: remove /v1 endpoints

### Features
- **auth**: add OAuth2 support (#123)

### Bug Fixes
- **cart**: handle empty discount (#124)

### Performance
- **db**: add index on users.email (#125)
```

---

## Vizual İzah (Visual Explanation)

### Commit-dən release-ə axın

```
 ┌─────────────────┐
 │ git commit      │
 │ feat: add login │
 └─────────────────┘
         │
         v
 ┌─────────────────┐    Husky commit-msg hook
 │ commitlint      │───>  Yoxlayır format
 └─────────────────┘
         │ (passes)
         v
 ┌─────────────────┐
 │ git push        │
 └─────────────────┘
         │
         v
 ┌─────────────────┐
 │ CI: PR Review   │───>  Merge to main
 └─────────────────┘
         │
         v
 ┌─────────────────────────────────┐
 │ Semantic Release                │
 ├─────────────────────────────────┤
 │ 1. Analyze commits since        │
 │    last tag                     │
 │ 2. Determine next version       │
 │    feat → MINOR                 │
 │    fix  → PATCH                 │
 │    BREAKING → MAJOR             │
 │ 3. Generate CHANGELOG           │
 │ 4. Create git tag v1.2.0        │
 │ 5. Create GitHub release        │
 │ 6. Publish to npm/Packagist     │
 └─────────────────────────────────┘
```

### Version bump logic

```
Last version: v1.2.3

Kommitlər:
  feat: ...       ──> MINOR bump
  fix: ...        ──> PATCH bump
  BREAKING ...    ──> MAJOR bump
  docs: ...       ──> Yox

Ən yüksək bump qalib gəlir:
┌──────────────┬──────────────┐
│ BREAKING     │ v2.0.0       │
├──────────────┼──────────────┤
│ feat + fix   │ v1.3.0       │
├──────────────┼──────────────┤
│ yalnız fix   │ v1.2.4       │
├──────────────┼──────────────┤
│ yalnız docs  │ heç nə       │
└──────────────┴──────────────┘
```

### Husky hooks zənciri

```
git commit
   │
   v
┌──────────────┐
│ pre-commit   │──> lint-staged (ESLint, Pint)
└──────────────┘
   │
   v
┌──────────────┐
│ commit-msg   │──> commitlint
└──────────────┘
   │
   v
┌──────────────┐
│ pre-push     │──> tests, type-check
└──────────────┘
   │
   v
git push
```

---

## Praktik Baxış

### Husky Node layihəsi olmayan Laravel üçün

Laravel PHP olsa da, Husky Node.js-ə əsaslanır. Minimal setup:

```bash
cd my-laravel-app

# package.json yarat
npm init -y

# Husky quraşdır
npm install --save-dev husky @commitlint/cli @commitlint/config-conventional

# commitlint.config.js
echo "module.exports = {extends: ['@commitlint/config-conventional']};" > commitlint.config.js

# Husky init
npx husky install
npm pkg set scripts.prepare="husky install"

# Commit-msg hook
npx husky add .husky/commit-msg 'npx --no-install commitlint --edit $1'
```

### Laravel scope nümunələri

`.commitlintrc.js`:
```javascript
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-enum': [2, 'always', [
      // Laravel-xüsusi scope-lar
      'auth',        // Authentication
      'model',       // Eloquent models
      'migration',   // Database migrations
      'seeder',      // Seeders
      'controller',  // Controllers
      'middleware',  // Middleware
      'job',         // Queue jobs
      'mail',        // Mailables
      'notification',// Notifications
      'api',         // API endpoints
      'blade',       // Blade views
      'livewire',    // Livewire components
      'config',      // config/*.php
      'deps',        // composer.json
    ]]
  }
};
```

### Composer paketi üçün semantic-release

`.releaserc.json`:
```json
{
  "branches": ["main"],
  "plugins": [
    "@semantic-release/commit-analyzer",
    "@semantic-release/release-notes-generator",
    ["@semantic-release/changelog", {"changelogFile": "CHANGELOG.md"}],
    ["@semantic-release/exec", {
      "prepareCmd": "sed -i 's/\"version\": \".*\"/\"version\": \"${nextRelease.version}\"/' composer.json"
    }],
    ["@semantic-release/git", {
      "assets": ["CHANGELOG.md", "composer.json"],
      "message": "chore(release): ${nextRelease.version} [skip ci]"
    }],
    "@semantic-release/github"
  ]
}
```

Bu konfiqurasiya `composer.json`-dakı versiyanı avtomatik yeniləyir.

### Laravel nümunə kommitlər

```bash
# Yeni model və migration
git commit -m "feat(model): add Subscription model with trial period"

# Bug fix
git commit -m "fix(cart): prevent negative quantity in OrderItem

Validator added in OrderItemRequest::rules()

Fixes #789"

# Performance
git commit -m "perf(query): eager load relations in ProductController@index

N+1 query issue resolved.
Response time: 1.2s → 180ms"

# Breaking change
git commit -m "feat(api)!: rename /api/v1/products response fields

BREAKING CHANGE: 'name' renamed to 'title', 'desc' renamed to 'description'"
```

---

## Praktik Tapşırıqlar

1. **commitlint qur**
   ```bash
   npm install --save-dev @commitlint/cli @commitlint/config-conventional
   echo "module.exports = {extends: ['@commitlint/config-conventional']}" > commitlint.config.js
   # Husky ilə qoş:
   echo "npx commitlint --edit" > .husky/commit-msg
   ```

2. **semantic-release konfiqurasiya et**
   ```bash
   npm install --save-dev semantic-release \
     @semantic-release/changelog \
     @semantic-release/git
   ```
   ```json
   // .releaserc.json
   {
     "branches": ["main"],
     "plugins": [
       "@semantic-release/commit-analyzer",
       "@semantic-release/release-notes-generator",
       "@semantic-release/changelog",
       ["@semantic-release/git", {"assets": ["CHANGELOG.md"]}]
     ]
   }
   ```

3. **Avtomatik tag + CHANGELOG test**
   ```bash
   # feat: commit et → minor version bump
   git commit -m "feat: add invoice export"
   # fix: commit et → patch version bump
   git commit -m "fix: null pointer in payment"
   # BREAKING CHANGE → major version bump
   git commit -m "feat!: new API v2"
   ```

4. **PHP/Composer üçün**
   ```bash
   # composer.json-da version sahəsi yox — tag-dan oxunur
   # Packagist avtomatik tag-ı götürür
   git tag v1.2.0
   git push origin v1.2.0
   ```

## Interview Sualları (Q&A)

### Q1: Conventional Commits niyə standartdır? Normal commit mesajları kifayət deyilmi?

**Cavab:** Normal mesajlar insan tərəfindən oxunur, maşın tərəfindən anlamlı deyil. Conventional Commits:
- Changelog-u avtomatik generasiya edir.
- Semantic versioning-i müəyyənləşdirir.
- Release prosesini avtomatlaşdırır.
- Commit tarixi yaxşı strukturlaşdırılır (filter: `git log --grep="^feat"`)
- Team-də tək dil yaradır.

### Q2: `feat` və `fix` arasında fərq nədir? Bəzən mübahisəli olur.

**Cavab:**
- **feat:** İstifadəçi üçün yeni funksionallıq (end-user value).
- **fix:** Mövcud funksionallıqda səhvin düzəldilməsi.

Mübahisəli hallarda sual: "Əvvəl bu funksiya işləyirdi?" Əgər bəli və indi işləmirdi → fix. Əgər yox idi → feat.

### Q3: Semantic Release versiyaları necə təyin edir?

**Cavab:** Algoritm:
1. Son git tag-dan bəri olan kommitləri analiz edir.
2. Hər kommitin tipinə görə "bump type" təyin edir:
   - `BREAKING CHANGE` → major
   - `feat` → minor
   - `fix`, `perf` → patch
3. Ən böyük bump tipi qalib gəlir.
4. Heç bir releaseable commit yoxdursa (yalnız docs/chore), release buraxılmır.

### Q4: Squash merge Conventional Commits-i pozurmu?

**Cavab:** Ola bilər. PR-dəki 10 kommitdən 1 nəticə kommiti yaranır və onun mesajı çox vaxt pis olur. Həll yolları:
- PR title Conventional Commits formatında olsun.
- GitHub "Squash" zamanı PR title-ı default commit message kimi istifadə edir.
- Squash bitirəndən əvvəl manual düzəldin.

### Q5: `BREAKING CHANGE` footer-də yoxdursa, `feat!` istifadə edilə bilərmi?

**Cavab:** Bəli, amma məsləhət görmürəm. `!` kommitin qısaltmasıdır, amma `BREAKING CHANGE:` footer-də breaking detalları yazmaq daha yaxşıdır. Ən yaxşı təcrübə – hər ikisini istifadə etmək:
```
feat(api)!: remove deprecated endpoints

BREAKING CHANGE: /v1/products removed, migrate to /v2/products.
See migration guide: docs/v2-migration.md
```

### Q6: Husky `commit-msg` hook bypass edilə bilərmi?

**Cavab:** Bəli, `--no-verify` flagı ilə:
```bash
git commit --no-verify -m "broken message"
```
Ona görə server-side (CI) də yoxlama olmalıdır. GitHub Actions-da PR-də commitlint işlədin, breaking commit-ləri bloklayın.

### Q7: Semantic Release monorepo-da necə işləyir?

**Cavab:** `semantic-release` default olaraq bir paket üçündür. Monorepo üçün:
- **Lerna + semantic-release** – hər paket üçün ayrı versiya.
- **semantic-release-monorepo** – scope əsasında paket müəyyənləşir.
- **Changesets** (Atlassian) – monorepo üçün daha populyardır.

Nümunə scope-əsaslı:
```
feat(ui): ...   → @mycompany/ui paketi
fix(api): ...   → @mycompany/api paketi
```

### Q8: CHANGELOG.md avtomatik generasiya edilsə, biz onu commit etməliyikmi?

**Cavab:** Bəli, semantic-release `@semantic-release/git` plugin-i CHANGELOG.md-ni repozitoriyaya commit edir. Bu `[skip ci]` ilə edilir ki, infinite loop yaranmasın:
```
chore(release): 2.1.0 [skip ci]
```

### Q9: Layihəmizdə 2 il normal commit tarixi var. Conventional Commits-ə keçə bilərik?

**Cavab:** Bəli:
1. Gələcək kommitlər üçün qaydaları tətbiq edin.
2. Köhnə tarixi saxlayın (rewrite etməyin).
3. İlk semantic-release buraxarkən başlanğıc versiyanı (v1.0.0) əl ilə tag edin:
```bash
git tag v1.0.0
git push origin v1.0.0
```
4. Bundan sonra semantic-release avtomatik işləyəcək.

### Q10: Commitizen və commitlint arasında fərq nədir?

**Cavab:**
- **Commitizen** – commit yazmağa kömək edən interactive CLI (`git cz`).
- **Commitlint** – commit mesajını qaydalara uyğun yoxlayan validator.

İkisi birlikdə çox güclüdür: commitizen düzgün format yaratmağa kömək edir, commitlint isə düzgün olmayanları rədd edir.

---

## Best Practices

1. **Subject-i imperative mood-da yazın**: "add login" (düzgün), "added login" (səhv), "adds login" (səhv).

2. **Subject-də nöqtə qoymayın**: "feat: add login" (düzgün), "feat: add login." (səhv).

3. **Subject 50-72 simvoldan çox olmasın**: Terminal və GitHub-da yaxşı görünür.

4. **Body-də "niyə", subject-də "nə"**: Body izah etsin niyə dəyişiklik lazımdır.

5. **Issue reference-ləri footer-də olsun**:
   ```
   Closes #123
   Refs #456
   ```

6. **Scope-u ardıcıl istifadə edin**: Enum ilə məhdudlaşdırın ki, bütün team eyni scope-ları istifadə etsin.

7. **BREAKING CHANGE-i açıq göstərin**: Migration guide olmalıdır, istifadəçi nə etməli olduğunu bilməlidir.

8. **chore(release) kommitlərini `[skip ci]` ilə işarələyin**: İnfinite CI loop-dan qaçın.

9. **`revert` commit-lər üçün xüsusi format**:
   ```
   revert: feat(auth): add OAuth2

   This reverts commit abc123.
   Reason: OAuth2 library has security issue.
   ```

10. **Husky-ni team-ə zorla tətbiq edin**: `prepare` script ilə avtomatik quraşdırılsın:
    ```json
    "scripts": { "prepare": "husky install" }
    ```

11. **CI-də server-side validation**: Husky bypass edilə bilər, CI edilə bilməz.

12. **Release branch policy**: `main` protected olsun, yalnız PR ilə merge mümkün olsun. Bu, Conventional Commits-in düzgün tətbiqi üçün vacibdir.

13. **Changelog-da user-facing dil**: `feat: implement caching layer` deyil, `feat: faster response times via caching`.

14. **Prerelease branch-ləri istifadə edin**: `beta`, `alpha` branch-lərdə test üçün avtomatik prerelease versiya yaradın: `v2.0.0-beta.1`.

## Əlaqəli Mövzular

- [11-git-tags.md](11-git-tags.md) — version tag-lar
- [18-git-hooks.md](18-git-hooks.md) — commit-msg hook
- [22-git-workflow-team.md](22-git-workflow-team.md) — komanda standartları
