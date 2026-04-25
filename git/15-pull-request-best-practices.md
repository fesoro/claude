# Pull Request Best Practices (Middle)

## İcmal

**Pull Request (PR)** (GitLab-da Merge Request) - developer-in öz branch-ındakı dəyişiklikləri main branch-a birləşdirmək üçün etdiyi rəsmi sorğudur. PR kod review, CI/CD yoxlamaları, müzakirə və təsdiqləmə prosesini əhatə edir.

```
PR Axını:
┌──────────────────────────────────────────────────┐
│ 1. Feature branch yarat                          │
│ 2. Dəyişikliklər et və commit                    │
│ 3. Remote-a push                                 │
│ 4. PR yarat                                      │
│ 5. CI/CD işləyir (test, lint, build)             │
│ 6. Code review - reviewer-lər kommentariya       │
│ 7. Feedback əsasında düzəlişlər                  │
│ 8. Approve                                       │
│ 9. Merge (squash/rebase/merge commit)            │
│ 10. Branch sil                                   │
└──────────────────────────────────────────────────┘
```

### Yaxşı PR-ın Xüsusiyyətləri

```
┌────────────────────────────────────────────┐
│ KİÇİK       - 400 sətirdən az              │
│ FOKUSLU     - Bir məqsəd                   │
│ TESTLİ      - Yeni testlər var             │
│ SƏNƏDLƏŞMİŞ - Nə, niyə, necə               │
│ REVIEW-OLUNAN - 30 dəqiqədən az oxunur    │
│ YAŞIL CI    - Bütün yoxlamalar keçir      │
└────────────────────────────────────────────┘
```

## Niyə Vacibdir

Keyfiyyətli PR review prosesi bug-ları production-a çatmamış tutur. Böyük PR-lar review-u çətinləşdirir, kontekst itkisinə səbəb olur; yaxşı PR description isə team knowledge sharing-i artırır və onboarding zamanı kod anlamağı asanlaşdırır. Laravel layihələrindəki security yoxlamaları, N+1 problemləri, migration düzgünlüyü məhz PR review mərhələsində aşkar edilməlidir.

## Əsas Əmrlər (Key Commands)

### GitHub CLI (gh) ilə

```bash
# GitHub CLI quraşdır
# Ubuntu: sudo apt install gh
# Login
gh auth login

# PR yaratmaq
gh pr create --title "Add user auth" --body "Closes #123"
gh pr create --fill                    # Son commit-dən doldur
gh pr create --draft                   # Draft PR

# PR görmək
gh pr list                             # Açıq PR-lar
gh pr view 42                          # PR #42 görür
gh pr view 42 --web                    # Browser-də aç
gh pr checks 42                        # CI nəticələri

# PR idarə etmək
gh pr checkout 42                      # PR branch-ına keç
gh pr review 42 --approve              # Approve
gh pr review 42 --request-changes -b "Tests lazımdır"
gh pr review 42 --comment -b "LGTM"
gh pr merge 42 --squash                # Squash merge
gh pr merge 42 --rebase                # Rebase merge
gh pr merge 42 --merge                 # Merge commit
gh pr close 42                         # Bağla
```

### Standart Git Əmrləri

```bash
# PR üçün branch yarat
git checkout -b feature/user-auth main

# Dəyişikliklər et
git add .
git commit -m "feat(auth): add login endpoint"
git push -u origin feature/user-auth

# Main-dən geri sink
git fetch origin
git rebase origin/main                 # və ya
git merge origin/main

# PR çevir (GitHub-da)
# GitHub avtomatik "Compare & pull request" göstərir

# Feedback-dən sonra force push
git add .
git commit --amend --no-edit           # Əvvəlki commit-ə əlavə
git push --force-with-lease            # Təhlükəsiz force push
```

## Nümunələr

### 1. PR Şablonu (.github/pull_request_template.md)

```markdown
## Nə Edir? (What does this PR do?)

<!-- Dəyişikliyin qısa izahı -->

## Niyə? (Why?)

<!-- Problem, səbəb, kontekst -->
Closes #123

## Necə Test Olunub?

- [ ] Unit testlər əlavə olunub
- [ ] Feature testlər əlavə olunub
- [ ] Manual test edilib

## Screenshots (UI dəyişiklikləri varsa)

<!-- Əvvəl / Sonra -->

## Checklist

- [ ] Kod linting-dən keçir
- [ ] Testlər əlavə olunub və keçir
- [ ] Sənədləşdirmə yenilənib
- [ ] Breaking change yoxdur (və ya qeyd olunub)
- [ ] Migration və ya deployment qeydləri əlavə olunub

## Deployment Qeydləri

<!-- Xüsusi deployment addımları varsa -->
- [ ] Migration işlədilməli: `php artisan migrate`
- [ ] Cache təmizlənməli: `php artisan cache:clear`
- [ ] Yeni .env dəyişəni: `STRIPE_KEY`
```

### 2. Yaxşı PR Nümunəsi

```markdown
# Title: feat(auth): add Google OAuth login

## What
Google OAuth 2.0 login implementation for web app.

## Why
Closes #234. Users requested social login option to reduce signup friction.
45% of users abandoned signup form in analytics.

## How
- Added `Socialite` package
- New route `POST /auth/google/callback`
- New `GoogleAuthService` class
- Updated login UI with Google button

## Testing
- Unit tests: `tests/Unit/GoogleAuthServiceTest.php`
- Feature tests: `tests/Feature/GoogleLoginTest.php`
- Manual: Tested on Chrome, Firefox, Safari
- Edge cases: existing email, expired token

## Screenshots
| Before | After |
|--------|-------|
| ![](old-login.png) | ![](new-login.png) |

## Deployment
- [ ] Add `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` to .env
- [ ] Update OAuth redirect URI in Google Console
- [ ] Run `php artisan migrate` (users table has google_id column)
```

### 3. Pis PR Nümunəsi (necə olmamalıdır)

```markdown
# Title: fixes

## What
bug fixes

<!-- PROBLEMS:
- Title heç nə demir
- Body 2 söz
- Hansı bug? Harada? Niyə?
- Test yoxdur
- Çox böyük diff (50 fayl, 3000 sətir)
- 5 fərqli mövzu qarışıb
-->
```

### 4. CODEOWNERS Faylı

```bash
# .github/CODEOWNERS

# Default owner
* @team-lead

# Backend
*.php @backend-team
/app/ @backend-team
/database/ @backend-team @dba-team

# Frontend
*.vue @frontend-team
/resources/js/ @frontend-team

# Security-sensitive
/app/Http/Middleware/ @security-team
/config/auth.php @security-team

# DevOps
/.github/ @devops-team
/docker/ @devops-team
Dockerfile @devops-team

# Documentation
/docs/ @docs-team @team-lead
```

### 5. GitHub Branch Protection Rules

```yaml
# Settings > Branches > Add rule
Branch name pattern: main

Protect matching branches:
  ☑ Require a pull request before merging
    ☑ Require approvals: 2
    ☑ Dismiss stale pull request approvals when new commits are pushed
    ☑ Require review from Code Owners
  
  ☑ Require status checks to pass before merging
    ☑ Require branches to be up to date before merging
    Status checks:
      - ci/lint
      - ci/test
      - ci/build
      - ci/security-scan
  
  ☑ Require conversation resolution before merging
  ☑ Require signed commits
  ☑ Require linear history
  ☑ Include administrators
  ☐ Allow force pushes (DISABLED)
  ☐ Allow deletions (DISABLED)
```

### 6. Merge Stratejiləri

```bash
# 1. Merge commit (default)
main:    A ── B ── M
              │   /
feature:      C ── D
# Tarix qorunur, amma "noisy"

# 2. Squash merge (tövsiyə olunur feature üçün)
main:    A ── B ── S (A+B+C+D birləşir)
# Linear, təmiz tarix

# 3. Rebase merge
main:    A ── B ── C' ── D'
# Linear, hər commit saxlanılır

# gh CLI ilə
gh pr merge 42 --squash
gh pr merge 42 --rebase
gh pr merge 42 --merge
```

### 7. CI Workflow Nümunəsi

```yaml
# .github/workflows/pr-checks.yml
name: PR Checks

on:
  pull_request:
    branches: [main, develop]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: 8.2 }
      - run: composer install --prefer-dist
      - run: ./vendor/bin/pint --test
      - run: ./vendor/bin/phpstan analyse

  test:
    runs-on: ubuntu-latest
    needs: lint
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: 8.2, coverage: xdebug }
      - run: composer install
      - run: php artisan test --coverage --min=80

  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: composer audit
      - uses: aquasecurity/trivy-action@master

  size-check:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Check PR size
        run: |
          CHANGES=$(git diff --shortstat origin/main | awk '{print $4+$6}')
          if [ "$CHANGES" -gt 400 ]; then
            echo "PR too large ($CHANGES lines). Split into smaller PRs."
            exit 1
          fi
```

### 8. PR Labels

```
# GitHub labels
🟢 size/XS     (< 10 lines)
🟢 size/S      (10-100 lines)
🟡 size/M      (100-400 lines)
🟠 size/L      (400-1000 lines)
🔴 size/XL     (> 1000 lines - SPLIT!)

🐛 type/bug
✨ type/feature
📝 type/docs
♻️ type/refactor
🧪 type/test
🔒 type/security

🚧 status/wip
👀 status/needs-review
✅ status/approved
🔁 status/changes-requested
⛔ status/blocked

🔥 priority/critical
⚡ priority/high
🎯 priority/medium
🌱 priority/low
```

## Vizual İzah (Visual Explanation)

### PR Lifecycle

```
┌─────────────┐
│ 1. Branch   │
│    yarat    │
└──────┬──────┘
       │
       v
┌─────────────┐     ┌──────────────┐
│ 2. Kod yaz  │ ──> │ 3. Commit &  │
│    (feature)│     │    Push      │
└─────────────┘     └──────┬───────┘
                           │
                           v
                    ┌──────────────┐
                    │ 4. PR yarat  │
                    └──────┬───────┘
                           │
                           v
                ┌──────────────────────┐
                │ 5. CI işləyir        │
                │ • Lint  • Test       │
                │ • Build • Security   │
                └──────────┬───────────┘
                           │
              ┌────────────┴─────────────┐
              │                          │
         ❌ Fail                     ✅ Pass
              │                          │
              v                          v
       ┌─────────────┐           ┌──────────────┐
       │ Düzəlt &    │           │ 6. Review    │
       │ push        │           │  • Komment   │
       └──────┬──────┘           │  • Suggest   │
              │                  └──────┬───────┘
              └────┐                    │
                   │            ┌───────┴────────┐
                   │       ❌ Changes        ✅ Approve
                   │         requested           │
                   │            │                v
                   │            │        ┌──────────────┐
                   └────────────┘        │ 7. Merge     │
                                         │  (squash)    │
                                         └──────┬───────┘
                                                │
                                                v
                                         ┌──────────────┐
                                         │ 8. Branch    │
                                         │    sil       │
                                         └──────────────┘
```

### Merge Strategiyaları Müqayisəsi

```
Əvvəl:
main:    A ─── B
              
feature:       C ─── D ─── E (3 commits)

1. Merge Commit:
main:    A ─── B ────────── M
               \           /
feature:        C ── D ── E
(4 commit görünür, merge commit əlavə)

2. Squash:
main:    A ─── B ─── S
(S = C+D+E birləşmiş, 1 commit)

3. Rebase:
main:    A ─── B ─── C' ─── D' ─── E'
(Linear, 3 ayrı commit)
```

### PR Size vs Review Quality

```
PR Size          Review Time    Quality
────────────────────────────────────────
< 50 lines       ~5 min         ⭐⭐⭐⭐⭐ (excellent)
50-200 lines     ~15 min        ⭐⭐⭐⭐ (good)
200-400 lines    ~30 min        ⭐⭐⭐ (acceptable)
400-1000 lines   ~60 min        ⭐⭐ (rushed)
1000+ lines      "LGTM 👍"       ⭐ (rubber-stamp)
```

## Praktik Baxış

### 1. Laravel PR Şablonu

```markdown
<!-- .github/pull_request_template.md -->

## Laravel PR Checklist

### Kod
- [ ] `./vendor/bin/pint` - kod formatı
- [ ] `./vendor/bin/phpstan analyse` - static analysis
- [ ] `php artisan test` - bütün testlər keçir

### Database
- [ ] Migration əlavə olunub (dəyişiklik varsa)
- [ ] Seeder yenilənib
- [ ] `php artisan migrate:fresh --seed` işləyir

### API Dəyişiklikləri
- [ ] API dokumentasiyası yenilənib (Scribe/Swagger)
- [ ] Postman collection yenilənib
- [ ] Backward compatible (və ya version dəyişib)

### Laravel Konvensiyaları
- [ ] FormRequest validasiya istifadə olunub
- [ ] API Resource istifadə olunub
- [ ] Service/Repository pattern (ağır logic)
- [ ] Events/Listeners (əlaqəli əməliyyatlar)
```

### 2. Laravel CI Pipeline

```yaml
# .github/workflows/laravel-pr.yml
name: Laravel PR

on: [pull_request]

jobs:
  pest:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports: ['3306:3306']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mbstring, pdo, mysql
      
      - uses: actions/cache@v3
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}
      
      - run: composer install --prefer-dist --no-progress
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test --parallel
      
      - name: Pint
        run: ./vendor/bin/pint --test
      
      - name: PHPStan
        run: ./vendor/bin/phpstan analyse --memory-limit=2G

  feature-preview:
    runs-on: ubuntu-latest
    steps:
      - name: Deploy preview
        run: |
          echo "Deploy to preview-pr-${{ github.event.number }}.mycompany.com"
          # Vapor, Forge və ya custom deploy
```

### 3. Laravel PR-da Tez-tez Rast Gəlinən Problemlər

```php
// ❌ PIS: Direct DB query controller-də
class UserController {
    public function index() {
        return DB::select('SELECT * FROM users');
    }
}

// ✅ YAXŞI: Eloquent + Resource
class UserController {
    public function index() {
        $users = User::paginate(20);
        return UserResource::collection($users);
    }
}

// ❌ PIS: N+1 problem
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // N+1!
}

// ✅ YAXŞI: Eager loading
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count;
}

// ❌ PIS: Validation controller-də
public function store(Request $request) {
    $request->validate(['email' => 'required|email']);
    // ...
}

// ✅ YAXŞI: FormRequest
public function store(StoreUserRequest $request) {
    // Validation avtomatik
}
```

### 4. Review Checklist (Laravel-specific)

```markdown
## Laravel Code Review Checklist

### Security
- [ ] Mass assignment: $fillable/$guarded təyin edilib
- [ ] SQL injection: Eloquent/Query Builder istifadə olunur
- [ ] XSS: Blade {{ }} istifadə olunur (raw {{!! !!}} YOX)
- [ ] CSRF: POST formalarda @csrf var
- [ ] Authorization: Policy/Gate yoxlanılır
- [ ] Rate limiting: API route-larda var

### Performance
- [ ] N+1 yoxdur (eager loading ilə)
- [ ] Pagination var (böyük data üçün)
- [ ] Cache istifadə olunur (lazım olduqda)
- [ ] Index-lər migration-da var
- [ ] Queue işləyir (uzun tasks üçün)

### Code Quality
- [ ] PSR-12 standart
- [ ] Type hints və return types
- [ ] Pint ilə formatlanıb
- [ ] PHPStan level 8 keçir
- [ ] DocBlock vacib methodlarda

### Tests
- [ ] Feature test əlavə olunub
- [ ] Unit test (business logic üçün)
- [ ] RefreshDatabase trait istifadə olunur
- [ ] Factory-lər yaradılıb/yenilənib
```

## Praktik Tapşırıqlar

1. **PR template yarat**
   ```bash
   mkdir -p .github
   # .github/PULL_REQUEST_TEMPLATE.md yarat:
   ```
   ```markdown
   ## Nə dəyişdi
   -

   ## Niyə
   -

   ## Test planı
   - [ ] Unit test
   - [ ] Manuel test

   ## Screenshot (UI varsa)
   ```

2. **Böyük PR-ı böl**
   - 500+ sətirlik feature PR-ı götür
   - Minimal deployable unit-lərə ayır:
     1. Database migration + model
     2. Service layer
     3. Controller + route
     4. Frontend (varsa)

3. **Self-review məşqi**
   - PR açmadan əvvəl `git diff main...HEAD` — öz gözünlə bax
   - "Bu reviewera aydındırmı?" sualını özünə ver

4. **Review comment-ə cavab vermə**
   - Hər comment-ə ya "Done" ya da izahat yaz
   - Qəbul etmədiyini professional şəkildə ifadə et

## Interview Sualları

### Q1: Pull Request nədir?
**Cavab:** PR developer-in öz branch-ındakı dəyişiklikləri main branch-a birləşdirmək üçün etdiyi rəsmi sorğudur. Review, diskusiya, CI testlər və approval prosesini əhatə edir. GitLab-da "Merge Request" adlanır.

### Q2: Yaxşı PR-ın xüsusiyyətləri nələrdir?
**Cavab:**
1. **Kiçik** (400 sətirdən az)
2. **Fokuslu** (bir məqsəd, bir PR)
3. **Təsvirli başlıq və açıqlama**
4. **Test edilmiş** (unit + feature testlər)
5. **CI keçir** (lint, test, build)
6. **Self-review** (müəllif özü yoxlayıb)
7. **Screenshots** (UI dəyişiklikləri varsa)
8. **Issue-ya bağlı** (Closes #123)

### Q3: Merge strategiyalarını müqayisə et?
**Cavab:**
- **Merge commit** - bütün commit-lər + merge commit qalır (tarix qorunur, amma noisy)
- **Squash** - bütün commit-lər tək birləşir (təmiz linear tarix)
- **Rebase** - commit-lər main-ə köçürülür (linear, hər commit ayrı)
Tövsiyə: feature branch-lar üçün **squash**, release branch-lar üçün **merge commit**.

### Q4: PR-i niyə kiçik saxlamalıyıq?
**Cavab:** Studiyalara görə:
- 200 sətirdən çox PR-da review keyfiyyəti 60% azalır
- 1000+ sətirlik PR-larda reviewer-lər "LGTM" rubber-stamp verir
- Kiçik PR → tez merge → tez feedback → az konflikt
Qayda: 1 PR = 1 mantıqi dəyişiklik.

### Q5: Code review-da nəyə diqqət yetirirsən?
**Cavab:**
1. **Correctness** - kod doğru işləyir?
2. **Security** - SQL injection, XSS, auth?
3. **Performance** - N+1, cache, index?
4. **Readability** - başa düşüləndir?
5. **Tests** - kifayət qədər coverage?
6. **Edge cases** - null, empty, böyük data?
7. **Style** - kod konvensiyasına uyğun?
8. **Architecture** - pattern-lərə uyğun?

### Q6: Branch protection nə edir?
**Cavab:** GitHub/GitLab-da main branch-ı qoruyur:
- Direct push qadağan
- PR tələb olunur
- N sayda approval tələbi
- CI keçməlidir
- CODEOWNERS review tələbi
- Signed commits
- Force push qadağan

### Q7: CODEOWNERS faylı nədir?
**Cavab:** `.github/CODEOWNERS` faylı hansı faylların/qovluqların kimə aid olduğunu təyin edir. PR-da həmin faylları dəyişdikdə avtomatik həmin şəxs review üçün tagging olunur.
```
*.php @backend-team
/frontend/ @frontend-team
```

### Q8: "Stale approval" nədir?
**Cavab:** Əgər PR approve olunduqdan sonra yeni commit əlavə olunursa, approval "stale" (köhnəlmiş) olur və yenidən review lazımdır. Bu settings-də "Dismiss stale approvals" aktivləşdirilməklə təmin olunur.

### Q9: Draft PR nə üçün istifadə olunur?
**Cavab:** Draft PR hələ hazır olmayan amma early feedback istəyən PR-dır. Reviewer-lər görə bilər amma merge edə bilməz. İstifadə halları:
- Böyük feature-da erkən arxitektura feedback-i
- CI-da test etmək
- "Work in progress" siqnalı

### Q10: Conflict olan PR-i necə həll edirsən?
**Cavab:**
```bash
# 1. Main-dən sonuncu dəyişiklikləri çək
git checkout main
git pull

# 2. Feature branch-a qayıt
git checkout feature/my-branch

# 3. Merge və ya rebase
git rebase main  # Tövsiyə: linear tarix
# və ya
git merge main

# 4. Konfliktləri həll et
# Fayllarda <<<<<<< markerləri düzəlt
git add <resolved-files>
git rebase --continue  # və ya git commit

# 5. Force push (rebase üçün)
git push --force-with-lease
```

## Best Practices

### 1. PR Title - Conventional Commits
```
feat(auth): add Google OAuth login
fix(api): resolve N+1 in user endpoint
docs(readme): update deployment steps
refactor(user): extract service class
test(order): add edge case for refund
chore(deps): update Laravel to 11.5
```

### 2. Self-Review Etməmiş Push Etmə
```bash
# Push etməzdən əvvəl:
git diff origin/main...HEAD         # Bütün dəyişiklikləri gör
git log origin/main..HEAD            # Commit-lərə bax
# Console.log, TODO, dead code silmisən?
```

### 3. Çoxlu Kiçik PR, Az Böyük PR
```
❌ 1 × 2000 sətirlik PR  → noisy, zəif review
✅ 10 × 200 sətirlik PR  → fokuslu, keyfiyyətli review
```

### 4. CI Sürətli və Reliable Olsun
```yaml
# Paralel jobs
# Cache dependencies
# Fail fast
# < 10 dəqiqə total
```

### 5. Review-də Konstruktiv Ol
```
❌ "Bu pis kod."
✅ "Bu metodu `Str::slug()` ilə sadələşdirmək olar: https://..."

❌ "Niyə belə yazdın?"
✅ "Bu yanaşmanı X alternativinə nisbətən seçmə səbəbi nədir?"
```

### 6. Suggestion İstifadə Et
```markdown
<!-- GitHub review-də -->
```suggestion
    public function index(): JsonResponse
    {
        return response()->json($this->users->all());
    }
```
<!-- Müəllif 1 klik ilə qəbul edə bilər -->
```

### 7. "Nit" Prefix Önəmsiz Şeylər üçün
```
nit: dəyişən adı "user" əvəzinə "currentUser" daha aydın olar
```

### 8. Approve Etmədən Əvvəl Lokal Test Et
```bash
# PR-i lokal test et
gh pr checkout 42
composer install
php artisan test
# UI varsa: npm run dev
```

### 9. "Ship It" Mentalitetı Qoru
```
Mükəmməl ≠ yaxşı
PR 80% hazırdırsa, merge et və follow-up PR yaz
Uzun davam edən PR-lar stale olur
```

### 10. Post-merge Responsibility
```bash
# Merge etdikdən sonra:
1. Deploy-u izlə
2. Error tracker-a bax (Sentry, Bugsnag)
3. Feedback-i gözlə
4. Problem çıxarsa, dərhal revert/fix
```

## Əlaqəli Mövzular

- [14-github-flow.md](14-github-flow.md) — PR-ın workflow-dakı yeri
- [22-git-workflow-team.md](22-git-workflow-team.md) — komanda standartları
- [29-codeowners-branch-protection.md](29-codeowners-branch-protection.md) — məcburi review qaydaları
