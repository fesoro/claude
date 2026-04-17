# Dependabot və Renovate

## Nədir? (What is it?)

**Dependabot** və **Renovate** – layihənin asılılıqlarını (dependencies) avtomatik yeniləyən botlardır. Yeni versiya buraxılan kimi avtomatik olaraq pull request açırlar.

**Problem:** Müasir Laravel layihəsinin `composer.json`-da 50+ paket var. Hər paketi əl ilə izləmək, security advisory-ləri yoxlamaq, compatibility test etmək mümkün deyil.

**Həll:** Bot:
1. Asılılıqları skan edir.
2. Yeni versiya çıxdıqda PR yaradır.
3. Release notes və changelog-u PR-ə əlavə edir.
4. CVE varsa security patch PR-i dərhal açır.
5. CI yaşıldırsa auto-merge edə bilər.

### Dependabot vs Renovate müqayisəsi

| Xüsusiyyət            | Dependabot              | Renovate                      |
|-----------------------|-------------------------|-------------------------------|
| Kimindir?             | GitHub (native)         | Mend (ex-WhiteSource)         |
| Konfiqurasiya         | `.github/dependabot.yml`| `renovate.json` / `.github/renovate.json5` |
| Qruplaşdırma          | Məhduddur               | Çox güclüdür                  |
| Auto-merge            | Bəli                    | Bəli (daha elastik)           |
| Monorepo dəstəyi      | OK                      | Əla                           |
| Package manager-lər   | 20+                     | 80+                           |
| Schedule              | Günlük/həftəlik         | Cron, timezone, istənilən     |
| Dashboard             | Yox                     | Dependency Dashboard issue    |
| Self-hosted           | Yox                     | Bəli                          |

**Qərar:** GitHub Enterprise-də Dependabot yetərlidir. Böyük, mürəkkəb layihələrdə Renovate daha güclüdür.

---

## Əsas Konfiqurasiyalar

### Dependabot əsas config

`.github/dependabot.yml`:
```yaml
version: 2
updates:
  # Composer (Laravel)
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "Asia/Baku"
    open-pull-requests-limit: 10
    labels:
      - "dependencies"
      - "php"

  # npm (frontend)
  - package-ecosystem: "npm"
    directory: "/"
    schedule:
      interval: "weekly"
    ignore:
      - dependency-name: "laravel-mix"
        versions: ["7.x"]

  # Docker
  - package-ecosystem: "docker"
    directory: "/docker"
    schedule:
      interval: "weekly"

  # GitHub Actions
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
```

### Renovate əsas config

`renovate.json`:
```json
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": ["config:recommended"],
  "timezone": "Asia/Baku",
  "schedule": ["before 9am on monday"],
  "labels": ["dependencies"],
  "prConcurrentLimit": 5,
  "prHourlyLimit": 2,
  "packageRules": [
    {
      "matchUpdateTypes": ["patch", "minor"],
      "matchCurrentVersion": "!/^0/",
      "automerge": true,
      "automergeType": "pr"
    },
    {
      "groupName": "Laravel ecosystem",
      "matchPackagePatterns": ["^laravel/", "^spatie/"],
      "schedule": ["before 9am on monday"]
    },
    {
      "matchDepTypes": ["devDependencies"],
      "automerge": true
    }
  ],
  "vulnerabilityAlerts": {
    "labels": ["security"],
    "automerge": true
  }
}
```

---

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: Dependabot PR-i necə görünür

```
Title: Bump laravel/framework from 11.0.0 to 11.5.2

Bumps laravel/framework from 11.0.0 to 11.5.2.

Release notes:
  https://github.com/laravel/framework/releases

Changelog:
  - Fixed: cookie encryption issue (#12345)
  - Added: new Redis connection options
  - Changed: Eloquent query builder internals

Commits:
  - abc1234: v11.5.2 release
  - def5678: fix cookie encryption
  ...

Compatibility score: 98% (based on 1,234 other PRs)
```

### Nümunə 2: Group updates (Renovate)

`renovate.json`:
```json
{
  "packageRules": [
    {
      "groupName": "PHPStan & Pest",
      "matchPackageNames": [
        "phpstan/phpstan",
        "phpstan/phpstan-strict-rules",
        "larastan/larastan",
        "pestphp/pest",
        "pestphp/pest-plugin-laravel"
      ],
      "schedule": ["before 9am on monday"]
    },
    {
      "groupName": "Tailwind ecosystem",
      "matchPackagePatterns": ["^tailwindcss", "^@tailwindcss/"],
      "automerge": true
    }
  ]
}
```

Nəticə: 5 ayrı PR əvəzinə 1 qruplaşdırılmış PR:
```
chore(deps): update PHPStan & Pest (patch)
  - phpstan/phpstan: 1.10.1 → 1.10.5
  - larastan/larastan: 2.8.0 → 2.8.3
  - pestphp/pest: 2.24.0 → 2.24.2
```

### Nümunə 3: Security updates (urgent)

`.github/dependabot.yml`:
```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "daily"  # security üçün hər gün
    open-pull-requests-limit: 20
    # Security updates həmişə işləyir, konfiqurasiyadan asılı deyil
```

GitHub Dependabot security advisory aşkar etdikdə dərhal PR açır:
```
[SECURITY] Bump guzzlehttp/guzzle from 7.5.0 to 7.5.1

CVE-2023-12345: CRLF injection in cookie header

Severity: HIGH (7.5/10)
```

### Nümunə 4: Auto-merge minor updates (Renovate)

```json
{
  "packageRules": [
    {
      "description": "Auto-merge patch and minor dev deps",
      "matchDepTypes": ["devDependencies", "require-dev"],
      "matchUpdateTypes": ["patch", "minor"],
      "automerge": true,
      "automergeType": "pr",
      "automergeStrategy": "squash"
    },
    {
      "description": "Major updates need manual review",
      "matchUpdateTypes": ["major"],
      "automerge": false,
      "labels": ["major-update", "needs-review"]
    }
  ]
}
```

### Nümunə 5: Ignore müəyyən paketlər

```json
{
  "packageRules": [
    {
      "description": "Laravel-ni yalnız LTS-ə qaldıraq",
      "matchPackageNames": ["laravel/framework"],
      "allowedVersions": "^11.0"
    },
    {
      "description": "PHP 8.3 qalsın",
      "matchPackageNames": ["php"],
      "enabled": false
    }
  ]
}
```

### Nümunə 6: Dependency Dashboard (Renovate-ə xas)

Renovate bir GitHub issue yaradır və orada bütün asılılıqların statusu göstərilir:
```
## Dependency Dashboard

### Open
- [ ] chore(deps): update laravel/framework to v12 (major)
- [ ] chore(deps): update tailwindcss to v4 (major)

### Rate Limited
- chore(deps): update @types/node (minor)

### Detected dependencies
composer.json:
  - laravel/framework: 11.5.2
  - spatie/laravel-permission: 6.0.1
  ...
```

---

## Vizual İzah (Visual Explanation)

### Dependency update axını

```
                        Package Registry
                       (Packagist/npm)
                              │
                              │ new version
                              v
 ┌──────────────────────────────────────────────┐
 │          Dependabot / Renovate bot            │
 └──────────────────────────────────────────────┘
                              │
                              │ scan + compare
                              v
 ┌──────────────────────────────────────────────┐
 │       composer.json / package.json           │
 └──────────────────────────────────────────────┘
                              │
                              │ PR create
                              v
 ┌──────────────────────────────────────────────┐
 │           GitHub Pull Request                 │
 │  Title: Bump laravel/framework to 11.5.2      │
 │  Labels: [dependencies], [php]                │
 └──────────────────────────────────────────────┘
                              │
                              v
 ┌──────────────────────────────────────────────┐
 │              CI Pipeline                      │
 │  • PHPUnit tests                              │
 │  • PHPStan                                    │
 │  • Security scan                              │
 └──────────────────────────────────────────────┘
                              │
                  ┌───────────┴───────────┐
                  │                       │
              passes?                 fails?
                  │                       │
                  v                       v
         ┌─────────────────┐    ┌─────────────────┐
         │  Auto-merge     │    │  Manual review  │
         │  (if configured)│    │  needed         │
         └─────────────────┘    └─────────────────┘
```

### Dependabot vs Renovate

```
Dependabot:
┌─────────────────┐
│ GitHub native   │
│ Basic config    │
│ Per-ecosystem   │
│ No dashboard    │
└─────────────────┘
       ↓
   1 PR per dep

Renovate:
┌─────────────────┐
│ Flexible config │
│ Grouping rules  │
│ Dashboard issue │
│ Self-hosted OK  │
└─────────────────┘
       ↓
  Grouped PRs + dashboard
```

### Auto-merge decision tree

```
                New PR from bot
                      │
                      v
              ┌───────────────┐
              │ CI passing?   │
              └───────┬───────┘
                      │
              ┌───yes ┴ no────┐
              │               │
              v               v
        Update type?      Block merge
              │          Notify team
        ┌─────┼─────┐
        │     │     │
     patch  minor  major
        │     │     │
        v     v     v
    auto   auto   manual
    merge  merge  review
```

---

## PHP/Laravel Layihələrdə İstifadə

### Tipik Laravel Dependabot config

`.github/dependabot.yml`:
```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
    open-pull-requests-limit: 10
    reviewers:
      - "backend-team"
    labels:
      - "dependencies"
      - "backend"
    ignore:
      # Major Laravel updates manual
      - dependency-name: "laravel/framework"
        update-types: ["version-update:semver-major"]
      # Pin PHP version
      - dependency-name: "php"
    groups:
      laravel:
        patterns:
          - "laravel/*"
      spatie:
        patterns:
          - "spatie/*"
      dev-tools:
        patterns:
          - "phpstan/*"
          - "larastan/*"
          - "pestphp/*"
          - "laravel/pint"
```

### Laravel-specific Renovate config

`renovate.json5`:
```json5
{
  "$schema": "https://docs.renovatebot.com/renovate-schema.json",
  "extends": [
    "config:recommended",
    ":semanticCommits",
    ":dependencyDashboard"
  ],
  "timezone": "Asia/Baku",
  "schedule": ["after 8am and before 6pm on monday"],
  "labels": ["dependencies"],
  "commitMessagePrefix": "chore(deps):",
  "packageRules": [
    // Laravel core - manual review
    {
      "description": "Laravel framework major updates",
      "matchPackageNames": ["laravel/framework"],
      "matchUpdateTypes": ["major"],
      "dependencyDashboardApproval": true,
      "labels": ["laravel-major", "needs-review"]
    },
    // Laravel ecosystem - grouped
    {
      "groupName": "Laravel ecosystem",
      "matchPackagePatterns": ["^laravel/", "^nunomaduro/"],
      "matchUpdateTypes": ["patch", "minor"]
    },
    // Spatie packages
    {
      "groupName": "Spatie packages",
      "matchPackagePatterns": ["^spatie/"]
    },
    // Dev deps auto-merge
    {
      "description": "Auto-merge dev dependencies",
      "matchDepTypes": ["require-dev"],
      "matchUpdateTypes": ["patch", "minor"],
      "automerge": true
    },
    // Security updates immediate
    {
      "matchDatasources": ["packagist"],
      "matchUpdateTypes": ["security"],
      "prPriority": 10,
      "labels": ["security", "urgent"]
    }
  ],
  "vulnerabilityAlerts": {
    "enabled": true,
    "labels": ["security"],
    "automerge": false
  },
  "composerIgnorePlatformReqs": []
}
```

### GitHub Actions – Dependabot PR-lərində test

`.github/workflows/dependabot-ci.yml`:
```yaml
name: Dependabot CI

on:
  pull_request:
    branches: [main]

jobs:
  test:
    if: github.actor == 'dependabot[bot]'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install
        run: composer install --no-interaction

      - name: Tests
        run: ./vendor/bin/pest

      - name: PHPStan
        run: ./vendor/bin/phpstan analyse

      - name: Auto-merge patch updates
        if: contains(github.event.pull_request.title, 'patch')
        uses: dependabot/fetch-metadata@v2
        with:
          github-token: ${{ secrets.GITHUB_TOKEN }}
```

### Composer audit ilə inteqrasiya

```bash
# Manual security check
composer audit

# Nəticə:
# Found 2 security vulnerability advisories:
# +-------------------+----------------------------------+
# | Package           | symfony/http-kernel              |
# | CVE               | CVE-2024-12345                   |
# | Fixed in          | 6.4.5                            |
# +-------------------+----------------------------------+
```

CI-də:
```yaml
- name: Security audit
  run: composer audit --no-dev --format=json > audit.json

- name: Upload audit
  uses: actions/upload-artifact@v4
  with:
    name: security-audit
    path: audit.json
```

---

## Interview Sualları (Q&A)

### Q1: Dependabot və Renovate arasında hansını seçərdin?

**Cavab:**
- **Kiçik/orta layihələr, GitHub-da:** Dependabot. Setup sadədir, GitHub-da native.
- **Böyük/mürəkkəb layihələr:** Renovate. Qruplaşdırma, dashboard, daha elastik schedule.
- **Self-hosted və ya GitLab:** Renovate (Dependabot yalnız GitHub-dır).
- **Monorepo:** Renovate daha yaxşıdır.

### Q2: Hər həftə 20 dep PR açılır. Necə idarə edirsən?

**Cavab:**
1. **Qruplaşdır**: Laravel, Spatie, dev-tools ayrı-ayrı qruplar.
2. **Auto-merge patch/minor**: Təkcə major manual.
3. **Schedule**: Həftədə bir dəfə, iş saatında.
4. **`open-pull-requests-limit`**: 5-10 ilə məhdudlaşdır.
5. **Kritik olmayan dev deps-i ignore et** və ya daha az tez yenilə.

### Q3: Auto-merge təhlükəlidirmi?

**Cavab:** Təhlükələr:
- Test coverage aşağıdırsa, broken dəyişiklik production-a çata bilər.
- Supply chain attack (kompromat olmuş paket) auto-merge edilir.

Mitigasiya:
- Yalnız patch və minor auto-merge.
- Güclü CI (tests, PHPStan, integration tests).
- `major` həmişə manual.
- Subresource integrity yoxlaması (npm audit, composer audit).
- Major paketlər üçün waiting period (Renovate `stabilityDays`).

### Q4: `stabilityDays` nədir?

**Cavab:** Renovate-də paketin yeni versiyasını açmadan əvvəl gözləməli olduğun gün sayı. Məsələn:
```json
{
  "stabilityDays": 3
}
```
3 gün ərzində community-də problem aşkarlansa, Renovate PR açmayacaq. Bu, "bad release"-lərdən qoruyur.

### Q5: Monorepo-da Dependabot necə işləyir?

**Cavab:** Hər `package.json`/`composer.json` üçün ayrı `directory` təyin etmək lazımdır:
```yaml
updates:
  - package-ecosystem: "composer"
    directory: "/backend"
  - package-ecosystem: "composer"
    directory: "/packages/auth"
  - package-ecosystem: "npm"
    directory: "/frontend"
```
Renovate avtomatik aşkar edir – konfiqurasiya lazım deyil.

### Q6: Security PR-lərini necə prioritet vermək olar?

**Cavab:**
- **Dependabot:** Security updates həmişə açılır, konfiqurasiya etmirsə də. Label: `security`.
- **Renovate:** `vulnerabilityAlerts` enabled + `prPriority: 10`.
- **CI-də:** Security label-lı PR-lərə ayrı pipeline – sürətli test + avtomatik merge.

### Q7: Renovate-in "Dependency Dashboard" nədir?

**Cavab:** Renovate GitHub-da bir issue yaradır. Bu issue-də:
- Açılmamış updates (rate-limited).
- Manual approval gözləyən updates.
- Detected dependencies siyahısı.
- Error və warning-lər.

Team bu issue-ni gündəlik baxıb, manual action ala bilər.

### Q8: Package-in major versiyasını necə bloklaya bilərəm?

**Cavab:**

Dependabot:
```yaml
ignore:
  - dependency-name: "laravel/framework"
    update-types: ["version-update:semver-major"]
```

Renovate:
```json
{
  "packageRules": [{
    "matchPackageNames": ["laravel/framework"],
    "matchUpdateTypes": ["major"],
    "enabled": false
  }]
}
```

### Q9: Laravel 10-dan 11-ə upgrade necə olur avtomatik?

**Cavab:** **Olmur**. Major update manual etmək lazımdır çünki:
- Breaking changes var (PHP version, deprecations).
- Config files dəyişə bilər.
- `composer.json`-da digər paketlər də yenilənməlidir.

Bot PR açar, amma sən manual:
1. Upgrade guide-ı oxu.
2. Local-da test et.
3. Deprecations-ı düzəlt.
4. Sonra merge et.

### Q10: Private registry (məsələn, private Packagist) necə konfiqurasiya olunur?

**Cavab:**

Dependabot (`.github/dependabot.yml`):
```yaml
registries:
  private-packagist:
    type: composer-repository
    url: https://packagist.my-company.com
    username: ${{secrets.PACKAGIST_USER}}
    password: ${{secrets.PACKAGIST_PASS}}

updates:
  - package-ecosystem: "composer"
    directory: "/"
    registries:
      - private-packagist
```

Renovate:
```json
{
  "hostRules": [{
    "hostType": "packagist",
    "matchHost": "packagist.my-company.com",
    "username": "myuser",
    "password": "{{ secrets.PACKAGIST_TOKEN }}"
  }]
}
```

---

## Best Practices

1. **Weekly schedule istifadə edin** (daily yox): Bazar ertəsi səhər ideal vaxtdır.

2. **Open PR limiti təyin edin**: 5-10 PR kifayətdir. Çoxu overwhelming olur.

3. **Qruplaşdırın**: Eyni ailədə paketləri (məs. laravel/*) bir PR-də birləşdirin.

4. **Auto-merge yalnız safe updates üçün**:
   - Patch: həmişə
   - Minor: testlər yaşıldırsa
   - Major: heç vaxt auto-merge

5. **CI tam işləsin**: Tests, linters, security scan – hamısı Dependabot PR-ində də işləsin.

6. **Labels istifadə edin**: `dependencies`, `security`, `major-update` – triage-ı asanlaşdırır.

7. **Reviewers təyin edin**: Bot PR-ləri birbaşa team-ə yönləndirin.

8. **Major updates üçün changelog oxuyun**: Bot yalnız PR açır, test etmək və oxumaq sizin işinizdir.

9. **Security alerts-ə sürətli reaksiya**: Security label-lı PR-ləri 24 saat içində merge edin.

10. **`stabilityDays` təyin edin**: Yeni buraxılmış versiyaları bir neçə gün gözləyin (bad releases üçün sığorta).

11. **Dependency Dashboard izləyin** (Renovate): Həftədə bir dəfə baxın, manual actions qalmasın.

12. **Unused dependencies təmizləyin**: `composer-unused` və ya `depcheck` ilə ayda bir dəfə audit edin. Az dep = az Dependabot PR.

13. **Lock file commit edin**: `composer.lock` və `package-lock.json` həmişə commit olunmalıdır, bot onları yeniləyəcək.

14. **Production və dev deps ayırın**: Dev deps daha aqressiv yenilənə bilər, production konservativ olmalıdır.

15. **Bot-a access vermə səviyyəsini ölçün**: Write access lazımdır, amma admin yox. Branch protection ilə məhdudlaşdırın.
