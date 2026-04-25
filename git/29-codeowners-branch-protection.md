# CODEOWNERS & Branch Protection (Lead)

## İcmal

**CODEOWNERS** — repository-də hansı faylların/qovluqların hansı şəxs və ya
komanda tərəfindən idarə olunduğunu təyin edən xüsusi fayldır. Pull request
açıldıqda GitHub/GitLab avtomatik olaraq müvafiq code owner-ləri reviewer kimi
təyin edir.

**Branch Protection Rules** — kritik branch-ları (main, production, release)
qəza dəyişikliklərdən qoruyan qaydalar toplusudur. Bunlara daxildir: məcburi
review, status check, force push qadağası, signed commits və s.

Birlikdə bu iki mexanizm **təhlükəsiz və keyfiyyətli code review** prosesinin
əsasını təşkil edir.

## Niyə Vacibdir

Main branch-a birbaşa push-u bloklamaq, critical fayllara (security, payment, config) mandatory review tətbiq etmək — governance olmadan istənilən developer istənilən kodu deploy edə bilər. CODEOWNERS + branch protection bu riskləri azaldır.

### Niyə lazımdır?

```
CODEOWNERS və Branch Protection OLMADAN:
  ❌ Junior developer production-a direct push edir
  ❌ Payments kodu frontend dev tərəfindən review olunur
  ❌ Migration faylına heç kim diqqət etmir
  ❌ CI qırılan PR merge olunur
  ❌ Force push tarixçəni pozur

CODEOWNERS və Branch Protection ilə:
  ✅ /payments/** → @payments-team məcburi review
  ✅ main branch-a birbaşa push olmur
  ✅ Bütün CI check-lər PASS olmalıdır
  ✅ 2 approve olmadan merge yoxdur
  ✅ Force push blocked
```

---

## Əsas Əmrlər və Sintaksis

### CODEOWNERS Fayl Yerləri

Aşağıdakı 3 yerdən biri qəbul olunur (ilk tapılan istifadə olunur):

```
.github/CODEOWNERS
CODEOWNERS
docs/CODEOWNERS
```

GitLab üçün: `.gitlab/CODEOWNERS` və ya `CODEOWNERS` və ya `docs/CODEOWNERS`.

### CODEOWNERS Sintaksisi

```
# Bu şərhdir
# <pattern>  <owner1> <owner2> ...

# Default owner (digər bütün fayllar)
*                           @orkhan-shukurlu

# Spesifik qovluq
/app/Services/Payment/      @acme/payments-team
/app/Http/Controllers/      @acme/backend-team

# Glob pattern
*.tf                        @acme/devops
*.yml                       @acme/devops
/.github/**                 @acme/devops @orkhan-shukurlu

# Fayl səviyyəsi
/composer.json              @acme/backend-team @tech-lead
/composer.lock              @acme/backend-team

# Negation (istisna) — Git 2.34+ YOX, GitHub: yalnız spesifik syntax
# GitHub-da istisna yoxdur — ən sonuncu uyğun gələn qayda qalib gəlir
/docs/                      @docs-team
/docs/api/                  @api-team       # /docs/api/** üçün api-team

# Email address owner
*.sql                       db-admin@acme.com

# Birdən çox owner (hər hansı biri review edə bilər, əgər "require owners" off-dursa)
/app/Models/                @senior-dev @tech-lead @acme/backend-team
```

### GitHub Branch Protection API ilə

```bash
# GitHub CLI ilə branch protection
gh api \
  --method PUT \
  repos/acme/laravel-api/branches/main/protection \
  --input - <<'JSON'
{
  "required_status_checks": {
    "strict": true,
    "contexts": ["ci/phpunit", "ci/phpstan", "ci/security"]
  },
  "enforce_admins": true,
  "required_pull_request_reviews": {
    "required_approving_review_count": 2,
    "dismiss_stale_reviews": true,
    "require_code_owner_reviews": true,
    "require_last_push_approval": true
  },
  "restrictions": null,
  "required_linear_history": true,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "required_conversation_resolution": true,
  "require_signed_commits": true
}
JSON
```

### GitLab Push Rules (shell yoxdur — API və ya UI)

```bash
# GitLab API
curl --request PUT \
  --header "PRIVATE-TOKEN: $GITLAB_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "commit_message_regex": "^(feat|fix|chore|docs)\\(.+\\): .+",
    "deny_delete_tag": true,
    "member_check": true,
    "prevent_secrets": true,
    "reject_unsigned_commits": true,
    "max_file_size": 50
  }' \
  "https://gitlab.com/api/v4/projects/$PROJECT_ID/push_rule"
```

---

## Praktiki Nümunələr

### Nümunə 1: Full-stack Laravel layihəsi üçün CODEOWNERS

```
# .github/CODEOWNERS
# ================================================================
# Global default — backend tech lead
# ================================================================
*                                       @acme/tech-leads

# ================================================================
# Backend (PHP/Laravel)
# ================================================================
/app/                                   @acme/backend-team
/config/                                @acme/backend-team
/database/migrations/                   @acme/backend-team @acme/db-admins
/database/seeders/                      @acme/backend-team
/routes/                                @acme/backend-team
/tests/Feature/                         @acme/backend-team @acme/qa-team
/tests/Unit/                            @acme/backend-team

# Kritik biznes məntiqi — əlavə review
/app/Services/Payment/                  @acme/payments-team @acme/security
/app/Services/Billing/                  @acme/payments-team
/app/Http/Middleware/Authenticate.php   @acme/security

# ================================================================
# Frontend (Vue/Inertia)
# ================================================================
/resources/js/                          @acme/frontend-team
/resources/css/                         @acme/frontend-team
/resources/views/                       @acme/frontend-team @acme/backend-team
/public/                                @acme/frontend-team

# ================================================================
# DevOps & Infra
# ================================================================
/docker/                                @acme/devops
/Dockerfile                             @acme/devops
/docker-compose*.yml                    @acme/devops
/.github/                               @acme/devops @acme/tech-leads
/.gitlab-ci.yml                         @acme/devops
/terraform/                             @acme/devops
/k8s/                                   @acme/devops
/deploy/                                @acme/devops

# ================================================================
# Dependency files — tech lead approval
# ================================================================
/composer.json                          @acme/tech-leads
/composer.lock                          @acme/backend-team
/package.json                           @acme/tech-leads
/package-lock.json                      @acme/frontend-team

# ================================================================
# Documentation
# ================================================================
/docs/                                  @acme/tech-writers
/README.md                              @acme/tech-writers @acme/tech-leads
/CHANGELOG.md                           @acme/tech-leads

# ================================================================
# Security-sensitive
# ================================================================
/.env.example                           @acme/security @acme/tech-leads
/app/Http/Middleware/                   @acme/security
/config/auth.php                        @acme/security
/config/sanctum.php                     @acme/security
```

### Nümunə 2: Monorepo CODEOWNERS

```
# .github/CODEOWNERS

# Package-based ownership
/packages/auth/         @acme/auth-team
/packages/billing/      @acme/billing-team
/packages/notifications/ @acme/comms-team

# Cross-package utilities
/packages/shared/       @acme/platform-team @acme/tech-leads

# Applications
/apps/api/              @acme/backend-team
/apps/admin-panel/      @acme/backend-team @acme/admin-squad
/apps/mobile/           @acme/mobile-team

# Shared config
/apps/*/config/         @acme/tech-leads
/apps/*/composer.json   @acme/tech-leads
```

### Nümunə 3: main və develop üçün branch protection

**main branch** (production):
```json
{
  "required_pull_request_reviews": {
    "required_approving_review_count": 2,
    "dismiss_stale_reviews": true,
    "require_code_owner_reviews": true,
    "require_last_push_approval": true
  },
  "required_status_checks": {
    "strict": true,
    "contexts": [
      "ci/phpunit",
      "ci/phpstan-level-max",
      "ci/php-cs-fixer",
      "ci/security-audit",
      "ci/lighthouse"
    ]
  },
  "enforce_admins": true,
  "required_linear_history": true,
  "allow_force_pushes": false,
  "allow_deletions": false,
  "require_signed_commits": true,
  "required_conversation_resolution": true
}
```

**develop branch** (daha elastik):
```json
{
  "required_pull_request_reviews": {
    "required_approving_review_count": 1,
    "dismiss_stale_reviews": false,
    "require_code_owner_reviews": false
  },
  "required_status_checks": {
    "strict": false,
    "contexts": ["ci/phpunit", "ci/phpstan"]
  },
  "enforce_admins": false,
  "allow_force_pushes": false
}
```

### Nümunə 4: GitLab protected branches və push rules

```bash
# main branch-i qoruyurıq
curl --request POST \
  --header "PRIVATE-TOKEN: $TOKEN" \
  "https://gitlab.com/api/v4/projects/$PROJECT_ID/protected_branches?name=main&push_access_level=0&merge_access_level=40&code_owner_approval_required=true"

# push_access_level: 0=No one, 30=Developer, 40=Maintainer, 60=Admin
# merge_access_level: eyni

# Approval rules
curl --request POST \
  --header "PRIVATE-TOKEN: $TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "name": "Require 2 approvals from backend team",
    "approvals_required": 2,
    "group_ids": [backend_group_id],
    "applies_to_all_protected_branches": true
  }' \
  "https://gitlab.com/api/v4/projects/$PROJECT_ID/approval_rules"
```

### Nümunə 5: CODEOWNERS doğrulanması (CI)

```yaml
# .github/workflows/codeowners-validation.yml
name: Validate CODEOWNERS

on:
  pull_request:
    paths:
      - '.github/CODEOWNERS'
      - 'CODEOWNERS'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Validate CODEOWNERS syntax
        uses: mszostok/codeowners-validator@v0.7.4
        with:
          checks: "files,syntax,owners,duplicated_pattern"
          experimental_checks: "notowned"
          github_access_token: ${{ secrets.CODEOWNERS_TOKEN }}
```

---

## Vizual İzah (ASCII Diagrams)

### CODEOWNERS matching priority

```
  PR dəyişiklikləri:
  ├── /app/Services/Payment/StripeGateway.php
  ├── /app/Models/User.php
  └── /docs/api.md

  CODEOWNERS:
  *                       @tech-leads          ← default
  /app/Services/Payment/  @payments-team       ← overrides default
  /app/Models/            @backend-team        ← specific
  /docs/                  @docs-team           ← specific

  Nəticə (auto-assigned reviewers):
  ┌─────────────────────────────────────────────┐
  │ @payments-team   (Payment/StripeGateway)    │
  │ @backend-team    (Models/User)              │
  │ @docs-team       (docs/api.md)              │
  │ @tech-leads      (heç bir spesifik match)   │
  └─────────────────────────────────────────────┘
  
  Qayda: Ən sonuncu uyğun gələn satır qazanır
```

### Branch Protection qəbul axını

```
  ┌────────────────┐
  │   git push     │
  │ origin main    │
  └────────┬───────┘
           │
           ↓
  ┌──────────────────────┐
  │ Protected branch?    │
  └────────┬─────────────┘
           │
      YES ↓                    ↓ NO
  ┌──────────────────────┐  ┌────────┐
  │ Direct push allowed? │  │ ACCEPT │
  └────────┬─────────────┘  └────────┘
           │
      NO  ↓
  ┌──────────────────────┐
  │  ❌ REJECT           │
  │  "Protected branch"  │
  └──────────────────────┘

  ┌───────────────────────────────────┐
  │         PR merge axını            │
  └───────────────────────────────────┘
           │
           ↓
  ┌──────────────────────┐
  │ Required approvals?  │ ← >=2 approver
  │ Code owner approval? │ ← CODEOWNERS
  │ Status checks pass?  │ ← CI green
  │ Conversation resolved│ ← all threads ✓
  │ Up-to-date with base │ ← no stale
  │ Signed commits?      │ ← GPG verified
  └────────┬─────────────┘
           │
      ALL YES ↓               ANY NO ↓
  ┌──────────────────┐   ┌───────────────────┐
  │  ✅ MERGE        │   │  ❌ BLOCKED       │
  │  ALLOWED         │   │  Fix requirements │
  └──────────────────┘   └───────────────────┘
```

### Team review pyramidi

```
                    ┌──────────┐
                    │   main   │  ← require_signed_commits
                    └─────┬────┘     require_linear_history
                          │          2 code owner approvals
                    ┌─────┴────┐
                    │ release  │  ← 2 approvals, tech lead
                    └─────┬────┘
                          │
                    ┌─────┴────┐
                    │ develop  │  ← 1 approval, status checks
                    └─────┬────┘
                          │
                    ┌─────┴────┐
                    │ feature  │  ← minimal protection
                    └──────────┘
```

---

## Praktik Baxış

### 1. Laravel layihəsi üçün tam CODEOWNERS

```
# .github/CODEOWNERS
# =========================================
# Laravel E-commerce Application
# =========================================

# Default — bütün PR-lər tech lead tərəfindən nəzərdən keçirilir
*                                       @acme/tech-leads

# ============ Domain: Orders ============
/app/Domain/Orders/                     @acme/orders-squad
/app/Http/Controllers/OrderController.php  @acme/orders-squad
/database/migrations/*_create_orders_*.php @acme/orders-squad @acme/db-admins

# ============ Domain: Payments ============
# Kritik — iki team-dən onay lazımdır
/app/Domain/Payments/                   @acme/payments-squad @acme/security
/app/Services/Stripe/                   @acme/payments-squad @acme/security
/config/services.php                    @acme/payments-squad @acme/tech-leads

# ============ Domain: Auth & Security ============
/app/Http/Middleware/                   @acme/security
/app/Http/Requests/Auth/                @acme/security
/config/auth.php                        @acme/security
/config/sanctum.php                     @acme/security

# ============ Migrations — həmişə DBA ============
/database/migrations/                   @acme/db-admins @acme/backend

# ============ Infra ============
/.github/workflows/                     @acme/devops
/docker/                                @acme/devops
/Dockerfile                             @acme/devops
/deploy/                                @acme/devops
/terraform/                             @acme/devops

# ============ Config ============
/composer.json                          @acme/tech-leads
/composer.lock                          @acme/backend
/.env.example                           @acme/security @acme/tech-leads
/.gitignore                             @acme/tech-leads

# ============ Tests ============
/tests/Feature/Payments/                @acme/payments-squad @acme/qa
/tests/Feature/                         @acme/qa @acme/backend

# ============ Frontend (Inertia + Vue) ============
/resources/js/                          @acme/frontend
/resources/css/                         @acme/frontend
/resources/views/                       @acme/frontend @acme/backend
/vite.config.js                         @acme/frontend
```

### 2. Composer üçün dependency review

**Problem**: Junior developer `composer require` ilə təsadüfən 50 MB
`laravel/dusk` quraşdıraraq production-a çıxarır.

**Həll**: `composer.json` və `composer.lock` CODEOWNERS ilə qorunur:

```
/composer.json      @acme/tech-leads
/composer.lock      @acme/backend-team
```

Branch protection qaydası:
```json
"require_code_owner_reviews": true
```

Nəticə: hər composer dəyişikliyi tech lead-dən approval tələb edir.

### 3. Migration dosyaları üçün DBA review

`database/migrations/*.php` dəyişiklikləri həmişə DBA tərəfindən nəzərdən
keçirilməlidir (index, foreign key, big table alter).

```
# CODEOWNERS
/database/migrations/   @acme/db-admins @acme/backend-team
```

**Workflow**:
```yaml
# .github/workflows/migration-check.yml
name: Migration Review Checklist

on:
  pull_request:
    paths:
      - 'database/migrations/**'

jobs:
  checklist:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Comment migration checklist
        uses: actions/github-script@v7
        with:
          script: |
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `## 🗄️ Migration Checklist (DBA)
              - [ ] Foreign keys with ON DELETE action?
              - [ ] Indexes on all foreign keys?
              - [ ] NO dropping columns in production migration?
              - [ ] Big table ALTER uses online DDL?
              - [ ] Reversible (down() method implemented)?
              - [ ] Tested on staging with production data size?`
            })
```

### 4. CI-də CODEOWNERS check

```yaml
# .github/workflows/pr-checks.yml
name: PR Checks

on:
  pull_request:
    branches: [main, develop]

jobs:
  codeowners:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Check CODEOWNERS coverage
        run: |
          # Bütün faylların bir owner-i olduğunu yoxla
          bash scripts/check-codeowners-coverage.sh

  require-review:
    runs-on: ubuntu-latest
    steps:
      - name: Require code owner review
        uses: actions/github-script@v7
        with:
          script: |
            const { data: reviews } = await github.rest.pulls.listReviews({
              owner: context.repo.owner,
              repo: context.repo.repo,
              pull_number: context.issue.number,
            });
            const approved = reviews.some(r => r.state === 'APPROVED');
            if (!approved) {
              core.setFailed('Code owner approval required');
            }
```

### 5. Artisan ilə CODEOWNERS yoxlama

```php
<?php
// app/Console/Commands/CheckCodeowners.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class CheckCodeowners extends Command
{
    protected $signature = 'codeowners:check';
    protected $description = 'Check that all domain folders have owners';

    public function handle(): int
    {
        $codeowners = file_get_contents(base_path('.github/CODEOWNERS'));
        $patterns = $this->extractPatterns($codeowners);

        $finder = new Finder();
        $finder->in(base_path('app/Domain'))->directories()->depth(0);

        $missing = [];
        foreach ($finder as $dir) {
            $path = '/app/Domain/' . $dir->getFilename() . '/';
            if (!$this->hasOwner($path, $patterns)) {
                $missing[] = $path;
            }
        }

        if (!empty($missing)) {
            $this->error('Missing CODEOWNERS for:');
            foreach ($missing as $path) {
                $this->line("  $path");
            }
            return Command::FAILURE;
        }

        $this->info('All domains have owners.');
        return Command::SUCCESS;
    }

    private function extractPatterns(string $content): array
    {
        $lines = array_filter(explode("\n", $content),
            fn ($l) => !empty(trim($l)) && !str_starts_with(trim($l), '#'));
        return array_map(fn ($l) => explode(' ', trim($l))[0], $lines);
    }

    private function hasOwner(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
```

---

## Praktik Tapşırıqlar

1. **CODEOWNERS faylı yaz**
   ```
   # .github/CODEOWNERS

   # Bütün fayllar üçün default
   *                          @team-backend

   # Payment kritik — yalnız senior review
   app/Services/Payment*      @senior-dev-1 @senior-dev-2

   # Infrastructure
   docker/                    @devops-team
   .github/workflows/         @devops-team

   # Security
   config/auth.php            @security-team
   ```

2. **Branch protection qaydaları (GitHub)**
   - Settings → Branches → Add rule → `main`
   - ✅ Require pull request reviews (2 reviewer)
   - ✅ Dismiss stale reviews
   - ✅ Require review from Code Owners
   - ✅ Require status checks: `test`, `phpstan`
   - ✅ Require branches to be up to date
   - ✅ Include administrators

3. **Status check tələb et**
   ```yaml
   # .github/workflows/required.yml
   name: Required Checks
   on: [pull_request]
   jobs:
     test:
       runs-on: ubuntu-latest
       steps:
         - run: php artisan test --coverage --min=80
     phpstan:
       runs-on: ubuntu-latest
       steps:
         - run: vendor/bin/phpstan analyse --level=8
   ```

4. **CODEOWNERS-i test et**
   ```bash
   # PR aç, CODEOWNERS-dəki fayl dəyişdir
   # Avtomatik reviewer-lar əlavə olunmalıdır
   ```

## Interview Sualları (Q&A)

### Q1: CODEOWNERS faylı nədir və hansı yerlərdə ola bilər?
**Cavab**: CODEOWNERS — repository-də kimin hansı fayla/qovluğa görə məsul
olduğunu təyin edən xüsusi fayl formatıdır. GitHub/GitLab PR-larda avtomatik
olaraq müvafiq reviewer-ləri təyin edir.

**Mümkün yerlər** (ilk tapılan istifadə olunur):
- `.github/CODEOWNERS` (ən çox istifadə olunan)
- `CODEOWNERS` (repo root)
- `docs/CODEOWNERS`

GitLab üçün əlavə: `.gitlab/CODEOWNERS`.

### Q2: CODEOWNERS-də pattern-lər necə işləyir? Priority qaydası nədir?
**Cavab**: Sintaksis `gitignore` pattern-ləri ilə eynidır. **Ən sonuncu uyğun
gələn satır qalib gəlir** (GitHub). GitLab-da bu davranış dəyişə bilər (sections ilə).

```
*                   @default-team
/app/               @backend
/app/Services/      @senior-backend
/app/Services/Pay/  @payments-team
```

`/app/Services/Pay/Stripe.php` üçün → **@payments-team** (ən spesifik/axırıncı
match).

Qeyd: GitHub CODEOWNERS-də **negation** (`!pattern`) dəstəklənmir, ancaq
`gitignore`-da var.

### Q3: Branch Protection-da əsas qaydalar nələrdir?
**Cavab**: GitHub-da main branch üçün adi qaydalar:

1. **Require pull request reviews before merging** (1-6 approvals)
2. **Dismiss stale pull request approvals when new commits are pushed**
3. **Require review from Code Owners** — CODEOWNERS aktiv
4. **Require status checks to pass** (CI must be green)
5. **Require branches to be up to date** (rebase/merge before merge)
6. **Require conversation resolution** (bütün comment-lər resolved)
7. **Require signed commits**
8. **Require linear history** (merge commit qadağan)
9. **Include administrators** (admin-lər də qaydalara tabi)
10. **Restrict who can push** (yalnız konkret istifadəçilər)
11. **Allow force pushes** — OFF
12. **Allow deletions** — OFF

### Q4: `dismiss_stale_reviews` nə edir və niyə vacibdir?
**Cavab**: Reviewer approve etdikdən sonra yeni commit push edilərsə, o approval
avtomatik rədd olunur. Niyə vacib:

- Developer reviewer-i "aldadıb" əvvəl safe dəyişiklik pusht edir, approve
  alıb, sonra malicious kod əlavə edir
- Reviewer köhnə versiyaya görə approve edib, amma sonradan fərqli logika girib

**Nəticə**: yeni commit → yeni review lazımdır.

### Q5: `require_code_owner_reviews` nə verir?
**Cavab**: PR-də dəyişdirilən bütün fayllar üçün CODEOWNERS-də qeyd olunmuş
şəxs/team **məcburi** reviewer olur. Onların approval-i olmadan merge
mümkün deyil.

**Nümunə**:
- PR-də `/app/Services/Payment/Stripe.php` dəyişib
- CODEOWNERS: `/app/Services/Payment/ @payments-team`
- `@payments-team` üzvlərindən biri mütləq approve etməlidir (hətta 10 başqa
  developer approve etsə belə)

### Q6: Force push niyə qorunmalı branch-larda qadağandır?
**Cavab**: Force push (`git push --force`) remote branch-ın tarixçəsini yenidən
yazır. Bu:

- **Başqa developer-lərin işini silə bilər** — onların commit-ləri itir
- **PR tarixini pozur** — review yox olur
- **Audit trail qırılır** — hansı commit nə vaxt mövcud idi, bilmək olmur
- **Production deploy-lar anlaşılmaz olur** — "main 2 saat əvvəl başqa commit idi"

Əvəzinə: `git revert` istifadə et və ya yeni PR ilə fix ver.

Əgər rebase mütləq lazımdırsa: `git push --force-with-lease` (safer) və ya
məhdud icazə (yalnız maintainer-lər).

### Q7: `required_status_checks.strict` nə edir?
**Cavab**: `strict: true` — PR branch-ı **base branch-ın ən son commit-ni**
içindirməlidir (up-to-date). Əks halda merge button disable olur.

```
main: ──A──B──C──D
            \
feature:     E──F
```

`strict: true` ilə:
- Feature branch `C` və `D`-ni ehtiva etmir → MERGE BLOCKED
- Developer `git rebase main` və ya `merge main` etməlidir
- Sonra CI yenidən işləyir (yeni commit üstündə)

Faydası: merge-dən sonra "semantic conflict"-lər olmur (CI ən son baza ilə test
edir).

### Q8: CODEOWNERS-də team və individual arasında fərq?
**Cavab**:

| Format | Nümunə | İstifadə |
|--------|--------|----------|
| User | `@orkhan-shukurlu` | Konkret şəxs |
| Team | `@acme/backend-team` | GitHub team (org/team-slug) |
| Email | `dev@acme.com` | GitHub-da emailə bağlı user |

**Team istifadə et** çünki:
- Developer aralıq team-dən çıxsa, CODEOWNERS update lazım deyil
- Bütün team üzvləri notification alır
- Approval bir üzvdən kifayətdir (individual-larda hər birindən ayrı gözlənilir)

### Q9: CODEOWNERS-də bir neçə owner olduqda kim approve etməlidir?
**Cavab**: Default olaraq — **hər hansı BIR owner** kifayətdir.

```
/app/Payment/  @user1 @user2 @team-finance
```

@user1 VƏ YA @user2 VƏ YA @team-finance-in bir üzvü approve etsə kifayətdir.

Əgər hamının approve-u lazımdırsa (rare), bunu Protection Rules-da fərdi olaraq
konfiqurə etmək lazımdır (GitHub Enterprise-da "require multiple approvals from
code owners").

GitLab-da bu daha güclü — CODEOWNERS sections ilə fərqli approval rule-ları
qurmaq olur:
```
[Backend][2]
/app/    @backend-team

[Security][1]
/security/ @security
```

### Q10: CODEOWNERS olmadan team workflow-unu necə qurmaq olar?
**Cavab**: CODEOWNERS olmasa da alternativ:

1. **GitHub "Require review from specific people"** — bütün PR-lərə təyin
   (amma spesifiklik yoxdur)
2. **GitHub Actions ilə auto-assign** — pattern-based reviewer assignment:
   ```yaml
   - uses: kentaro-m/auto-assign-action@v2
     with:
       configuration-path: .github/auto-assign.yml
   ```
3. **Labels + required labels** — "needs-payments-review" label
4. **GitLab MR approval rules** — group-based approval
5. **Danger.js** — custom rule: "if payment files changed, comment @team"

Ancaq **CODEOWNERS ən standart və audit-friendly həlldir**.

### Q11: `require_linear_history` nə verir?
**Cavab**: Merge commit-ləri qadağan edir — yalnız **rebase** və ya **squash**
merge icazə verilir.

```
AÇIQ (merge commits):
main:  A──B────────M──E
              \  /
feature:       C──D

LINEAR (rebase/squash):
main:  A──B──C'──D'──E
```

**Faydaları**:
- `git log --graph` təmiz
- `git bisect` daha asan
- `git revert` birbaşa işləyir
- CI tarixçəsi izlənə bilən

**Dezavantaj**: feature branch tarixçəsi itir (squash zamanı).

### Q12: Admins də branch protection-a tabe olmalıdırmı?
**Cavab**: **Bəli** — `enforce_admins: true` seçilməlidir.

**Niyə**:
- Admin səhvlə force push edə bilər
- Yoxlama (compliance) tələbləri (SOC2, ISO27001) bunu tələb edir
- "Break glass" emergency üçün ayrıca mexanizm var (admin override log)

**Nə zaman `false`**: yalnız kiçik solo repos və ya acil hallarda (amma log-la).

---

## Best Practices

### 1. Default owner təyin et
```
# Bütün faylların bir sahibi olmalıdır
*   @acme/tech-leads
```
"Orphan" fayllar olmasın — hər şeyin konkret məsul şəxsi olmalıdır.

### 2. Team istifadə et, individual yox
```
# YAX
/app/  @john-smith @jane-doe

# YAXŞI
/app/  @acme/backend-team
```
Developer team-i dəyişəndə CODEOWNERS sınmır.

### 3. Kritik kodu ikiqat qoru
```
/app/Services/Payment/  @payments-team @security-team
/config/auth.php         @security-team @tech-leads
```
İki fərqli team-dən approval → insider threat qoruması.

### 4. Branch protection enforce admin
`enforce_admins: true` — heç kim qaydaları bypass edə bilməsin.

### 5. Required status checks siyahısını minimal saxla
```json
"contexts": ["ci/phpunit", "ci/phpstan", "ci/security"]
```
Hər CI job-ı required etmə — yalnız **həqiqətən kritik** olanları.

### 6. `dismiss_stale_reviews: true`
Hər yeni commit-dən sonra yenidən review.

### 7. CODEOWNERS faylını code review et
`.github/CODEOWNERS`-in öz CODEOWNERS-i olmalıdır:
```
/.github/CODEOWNERS  @acme/tech-leads @acme/security
```

### 8. Signed commits tələb et
```json
"require_signed_commits": true
```
Identity spoofing-dən qoruma. Bax: `26-signed-commits.md`.

### 9. Linear history
`required_linear_history: true` — təmiz tarixçə, asan debug.

### 10. `allow_force_pushes: false` və `allow_deletions: false`
Main branch heç vaxt pozulmasın.

### 11. Required conversation resolution
```json
"required_conversation_resolution": true
```
Həll olunmamış comment varsa merge blok.

### 12. Monitor və audit
```bash
# Aylıq audit
gh api repos/acme/app/branches/main/protection > protection-$(date +%Y%m).json
```
Dəyişiklikləri izləyin.

### 13. CODEOWNERS-i sənədləşdir
Repo wiki və ya README-də izah et: "Why @payments-team owns /Payment/ folder".

### 14. Environment-based rules
- `main` — 2 approvals, CODEOWNERS, signed, linear
- `staging` — 1 approval, CI
- `develop` — 1 approval
- `feature/*` — protection yox

### 15. Emergency procedures
"Break glass" prosedur: admin override + log + post-mortem. Heç vaxt qaydaları
silib sonra qaytarma.

---

## Xülasə

**CODEOWNERS + Branch Protection** = təhlükəsiz, audit-friendly, keyfiyyətli
code review prosesi.

- CODEOWNERS avtomatik reviewer assignment və approval enforcement verir
- Branch protection force push, bypass və keyfiyyətsiz merge-ə qarşı qoruyur
- Birlikdə GitFlow/GitHub Flow workflow-larının əsasını təşkil edir
- Compliance (SOC2, PCI-DSS) və enterprise security üçün vacibdir

PHP/Laravel layihəsində hər domain qovluğu, migration, config və security-həssas
kod üçün spesifik CODEOWNERS qayda qoy. Branch protection ilə main-i tam qoru:
2 approval, signed commits, linear history, enforce admins.

## Əlaqəli Mövzular

- [15-pull-request-best-practices.md](15-pull-request-best-practices.md) — PR prosesi
- [22-git-workflow-team.md](22-git-workflow-team.md) — komanda standartları
- [24-signed-commits.md](24-signed-commits.md) — commit imzalanması
