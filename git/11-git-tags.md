# Git Tags (Middle)

## İcmal

Git tag-lar, commit tarixçəsindəki müəyyən nöqtələri qeyd etmək üçün istifadə olunan referanslardır. Əsasən software release-ləri (v1.0.0, v2.1.3) işarələmək üçün istifadə olunur. Tag-lar branch-lərdən fərqli olaraq hərəkət etmir, həmişə eyni commit-ə işarə edir.

```
Tag-lar commit tarixçəsində:

●──●──●──●──●──●──●──●──●──●──●──●──●──●──
         ↑        ↑              ↑     ↑
       v1.0.0   v1.1.0        v2.0.0 v2.0.1
       (release) (release)   (major)  (hotfix)

Tag = sabit referans (hərəkət etmir)
Branch = hərəkət edən referans (hər commit ilə irəliləyir)
```

### Lightweight vs Annotated Tags

```
Lightweight Tag:
  Sadəcə commit-ə pointer (bookmark kimi)
  Metadata yoxdur
  git tag v1.0.0

Annotated Tag:
  Tam Git object-dir
  Tagger adı, email, tarix
  Mesaj (release notes)
  GPG imzası (optional)
  git tag -a v1.0.0 -m "Release 1.0.0"
```

## Niyə Vacibdir

Release management üçün tag-lar vacibdir: `v1.2.3` tag-ı CI/CD-yə production deploy-u tetikləyir, `git describe` ilə build-ə version nömrəsi əlavə olunur, Laravel package-ları Composer-a Packagist üzərindən tag-la publish olunur. Semantic versioning ilə tag-lar olmadan deployment pipeline avtomatlaşdırılması və changelog generasiyası mümkün olmaz.

## Əsas Əmrlər (Key Commands)

### Tag Yaratma

```bash
# Lightweight tag
git tag v1.0.0

# Annotated tag (tövsiyə olunan)
git tag -a v1.0.0 -m "Release 1.0.0 - Initial stable release"

# Keçmiş commit-ə tag
git tag -a v0.9.0 -m "Beta release" abc1234

# İmzalı tag (GPG)
git tag -s v1.0.0 -m "Signed release 1.0.0"
```

### Tag Siyahılama

```bash
# Bütün tag-lar
git tag

# Pattern ilə filtrlə
git tag -l "v2.*"
git tag -l "v1.0.*"

# Tag detalları
git show v1.0.0

# Tag-ları tarixlə sırala
git tag --sort=-creatordate

# Son 5 tag
git tag --sort=-creatordate | head -5
```

### Tag Paylaşma (Remote)

```bash
# Tək tag-ı push et
git push origin v1.0.0

# Bütün tag-ları push et
git push origin --tags

# Yalnız annotated tag-ları push et
git push origin --follow-tags

# Remote-dan tag-ları fetch et
git fetch --tags
```

### Tag Silmə

```bash
# Lokal tag silmə
git tag -d v1.0.0

# Remote tag silmə
git push origin --delete v1.0.0
# və ya
git push origin :refs/tags/v1.0.0

# Lokal və remote birlikdə
git tag -d v1.0.0
git push origin --delete v1.0.0
```

### Tag ilə Checkout

```bash
# Tag-a checkout (detached HEAD)
git checkout v1.0.0

# Tag-dan branch yaratma
git checkout -b hotfix/v1.0.1 v1.0.0
```

## Nümunələr

### Nümunə 1: Semantic Versioning ilə Release

```bash
# Semantic Versioning: MAJOR.MINOR.PATCH
# MAJOR: breaking changes (1.0.0 → 2.0.0)
# MINOR: yeni feature, backward compatible (1.0.0 → 1.1.0)
# PATCH: bug fix (1.0.0 → 1.0.1)

# İlk stable release
git tag -a v1.0.0 -m "Release 1.0.0
- User authentication
- Product catalog
- Shopping cart
- Payment integration (Stripe)"

# Feature release
git tag -a v1.1.0 -m "Release 1.1.0
- Add order tracking
- Add email notifications
- Improve search performance"

# Bug fix release
git tag -a v1.1.1 -m "Release 1.1.1
- Fix payment timeout issue
- Fix email template encoding"

# Breaking change (API v2)
git tag -a v2.0.0 -m "Release 2.0.0
BREAKING CHANGES:
- API response format changed
- Minimum PHP version: 8.2
- Removed deprecated endpoints"

git push origin --tags
```

### Nümunə 2: Pre-Release Tag-lar

```bash
# Alpha (çox erkən, daxili test)
git tag -a v2.0.0-alpha.1 -m "Alpha 1 - Core restructuring"
git tag -a v2.0.0-alpha.2 -m "Alpha 2 - New payment system"

# Beta (xarici test, feature complete)
git tag -a v2.0.0-beta.1 -m "Beta 1 - Feature complete, testing"
git tag -a v2.0.0-beta.2 -m "Beta 2 - Bug fixes from beta testing"

# Release Candidate (production ready candidate)
git tag -a v2.0.0-rc.1 -m "RC1 - Ready for final testing"
git tag -a v2.0.0-rc.2 -m "RC2 - Final fixes"

# Final release
git tag -a v2.0.0 -m "Release 2.0.0 - Stable"
```

### Nümunə 3: Release Branch ilə Tag

```bash
# GitFlow release prosesi
git checkout -b release/1.2.0 develop

# Version bump
sed -i "s/'version' => '.*'/'version' => '1.2.0'/" config/app.php
git commit -am "chore: bump version to 1.2.0"

# Son düzəlişlər
git commit -am "fix: correct validation message"

# Main-ə merge və tag
git checkout main
git merge --no-ff release/1.2.0
git tag -a v1.2.0 -m "Release 1.2.0 - Order management improvements"

# Push
git push origin main --follow-tags

# Develop-ə geri merge
git checkout develop
git merge --no-ff release/1.2.0
git push origin develop

# Release branch sil
git branch -d release/1.2.0
```

### Nümunə 4: İki Tag Arasındakı Dəyişikliklər

```bash
# Release notes yaratmaq üçün
git log v1.1.0..v1.2.0 --oneline
# abc1234 feat: add order export
# def5678 fix: payment retry logic
# ghi9012 docs: update API documentation

# Tam format
git log v1.1.0..v1.2.0 --pretty=format:"- %s (%h)" --no-merges

# Dəyişən fayllar
git diff v1.1.0..v1.2.0 --stat
```

## Vizual İzah (Visual Explanation)

### Tag vs Branch

```
Branch (hərəkət edir):

  ●──●──●──●──●
                ↑
              main (hər commit ilə irəliləyir)

Tag (sabit qalır):

  ●──●──●──●──●──●──●──●
         ↑           ↑
       v1.0.0      v1.1.0
    (həmişə burada) (həmişə burada)
```

### Semantic Versioning

```
  v MAJOR . MINOR . PATCH
  │   │       │       │
  │   │       │       └── Bug fixes (geriyə uyğun)
  │   │       └────────── Yeni feature-lar (geriyə uyğun)
  │   └────────────────── Breaking changes (geriyə uyğun DEYİL)
  └────────────────────── Version prefix

  Nümunələr:
  1.0.0 → 1.0.1  (bug fix)
  1.0.1 → 1.1.0  (yeni feature)
  1.1.0 → 2.0.0  (breaking change)

  Pre-release:
  2.0.0-alpha.1 → 2.0.0-beta.1 → 2.0.0-rc.1 → 2.0.0
```

### Release Pipeline ilə Tag

```
Developer                    CI/CD                  Production
   │                          │                        │
   │── git tag v1.2.0 ──────>│                        │
   │   git push --tags        │                        │
   │                          │── Run tests ──────────>│
   │                          │── Build artifact       │
   │                          │── Deploy staging       │
   │                          │── Smoke tests          │
   │                          │── Deploy production ──>│
   │                          │                        │
   │                          │── GitHub Release ──>   │
   │                          │   (auto-generated      │
   │                          │    release notes)      │
```

## Praktik Baxış

### Laravel Version Management

```php
// config/app.php
return [
    'version' => '2.1.0',  // Tag ilə sync saxlayın
    // ...
];

// Version-ı göstərmək üçün
// routes/web.php
Route::get('/version', function () {
    return response()->json([
        'version' => config('app.version'),
        'php' => PHP_VERSION,
        'laravel' => app()->version(),
    ]);
});
```

### Avtomatik Tag ilə Deploy

```yaml
# .github/workflows/release.yml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Get version
        id: version
        run: echo "VERSION=${GITHUB_REF#refs/tags/v}" >> $GITHUB_OUTPUT

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Install & Build
        run: |
          composer install --no-dev --optimize-autoloader
          npm ci && npm run build

      - name: Deploy
        run: |
          echo "Deploying version ${{ steps.version.outputs.VERSION }}"
          # Laravel Forge, Envoyer, SSH deploy...

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          generate_release_notes: true
```

### CHANGELOG Automation

```bash
# conventional-changelog ilə avtomatik CHANGELOG
# Tag-lar arasındakı commit-lərdən yaradılır

# Nümunə CHANGELOG.md:
cat << 'EOF'
# Changelog

## [2.1.0] - 2026-04-16
### Added
- Order tracking system (#142)
- Email notification preferences (#138)

### Fixed
- Payment gateway timeout (#145)
- Search pagination issue (#143)

### Changed
- Upgrade to PHP 8.3 (#140)

## [2.0.0] - 2026-03-01
### Breaking Changes
- API v2 response format
- Minimum PHP 8.2 required

### Added
- New REST API v2
- Rate limiting
EOF

# git-cliff ilə avtomatik CHANGELOG
# cargo install git-cliff
git cliff --tag v2.1.0 -o CHANGELOG.md
```

### Composer Package Versioning

```json
// composer.json (package üçün)
{
    "name": "company/payment-sdk",
    "version": "1.2.0",
    "require": {
        "php": "^8.1"
    }
}
```

```bash
# Packagist üçün tag yaradın
git tag -a v1.2.0 -m "Release 1.2.0"
git push origin v1.2.0

# Packagist avtomatik yeni versiyanı detect edir
# composer require company/payment-sdk:^1.2
```

## Praktik Tapşırıqlar

1. **Annotated tag yarat və push et**
   ```bash
   git tag -a v1.0.0 -m "Release 1.0.0: user authentication"
   git push origin v1.0.0
   git push origin --tags  # bütün tag-ları push et
   ```

2. **Tag-dan branch yarat**
   ```bash
   git checkout -b hotfix/v1.0.1 v1.0.0
   # v1.0.0 state-indən yeni branch
   ```

3. **Semantic versioning tətbiq et**
   ```bash
   # Patch: bug fix
   git tag -a v1.0.1 -m "fix: null pointer in OrderController"
   # Minor: yeni feature
   git tag -a v1.1.0 -m "feat: add payment gateway"
   # Major: breaking change
   git tag -a v2.0.0 -m "feat!: new API structure"
   ```

4. **Tag-ı sil**
   ```bash
   git tag -d v1.0.0-beta        # local sil
   git push origin :v1.0.0-beta  # remote-dan sil
   ```

## Interview Sualları

### S1: Lightweight və annotated tag arasındakı fərq nədir?

**Cavab**:
- **Lightweight tag**: Sadəcə commit-ə pointer-dir. Metadata yoxdur. `git tag v1.0` ilə yaradılır.
- **Annotated tag**: Tam Git object-dir. Tagger adı, email, tarix, mesaj və optional GPG imzası saxlayır. `git tag -a v1.0 -m "msg"` ilə yaradılır.

Release-lər üçün həmişə annotated tag istifadə olunmalıdır çünki kim, nə zaman, niyə release etdiyini sənədləşdirir.

### S2: Semantic Versioning nədir?

**Cavab**: MAJOR.MINOR.PATCH formatında versiya sistemidir:
- **MAJOR** (2.0.0): Breaking changes, geriyə uyğun deyil
- **MINOR** (1.1.0): Yeni feature, geriyə uyğun
- **PATCH** (1.0.1): Bug fix, geriyə uyğun

Pre-release: `1.0.0-alpha.1`, `1.0.0-beta.1`, `1.0.0-rc.1`

### S3: Tag-ı necə remote-a push edirsiniz?

**Cavab**:
```bash
# Tək tag
git push origin v1.0.0

# Bütün tag-lar
git push origin --tags

# Yalnız annotated tag-lar
git push origin --follow-tags
```

`git push` default olaraq tag-ları push etmir, ayrıca göstərmək lazımdır.

### S4: Yanlış tag yaratdınız, necə düzəldirsiniz?

**Cavab**:
```bash
# Lokal tag silmə
git tag -d v1.0.0

# Remote-dan silmə
git push origin --delete v1.0.0

# Düzgün tag yaratma
git tag -a v1.0.0 -m "Corrected release"
git push origin v1.0.0
```

Diqqət: Əgər başqaları tag-ı pull edibsə, onlara da xəbər verin.

### S5: Tag-dan necə branch yaradırsınız?

**Cavab**:
```bash
git checkout -b hotfix/v1.0.1 v1.0.0
```
Bu, v1.0.0 tag-ından yeni branch yaradır. Hotfix və ya keçmiş release-dən branch açmaq üçün istifadə olunur.

### S6: İki tag arasındakı dəyişiklikləri necə görürsünüz?

**Cavab**:
```bash
# Commit-lər
git log v1.0.0..v1.1.0 --oneline

# Diff
git diff v1.0.0..v1.1.0

# Statistika
git diff v1.0.0..v1.1.0 --stat
```

### S7: Tag-lar CI/CD pipeline-da necə istifadə olunur?

**Cavab**: Tag push edildikdə CI/CD pipeline trigger olur. Nümunə:
- `v*` pattern-i ilə tag push → build, test, deploy
- Tag adından version nömrəsi çıxarılır
- Artifact yaradılır (Docker image, package)
- Staging-ə deploy, smoke test, production-a deploy
- GitHub/GitLab release avtomatik yaradılır

## Best Practices

### 1. Annotated Tag İstifadə Edin

```bash
# Həmişə annotated tag (release-lər üçün)
git tag -a v1.0.0 -m "Release message"

# Lightweight tag yalnız lokal/müvəqqəti markers üçün
git tag temp-test-point
```

### 2. Semantic Versioning Tətbiq Edin

```
v1.0.0 → v1.0.1 (bug fix)
v1.0.1 → v1.1.0 (yeni feature)
v1.1.0 → v2.0.0 (breaking change)

Pre-release-lər üçün:
v2.0.0-alpha.1, v2.0.0-beta.1, v2.0.0-rc.1
```

### 3. Tag Mesajlarında Release Notes Yazın

```bash
git tag -a v1.2.0 -m "Release 1.2.0

Features:
- Add order tracking (#142)
- Add email notifications (#138)

Fixes:
- Fix payment timeout (#145)

Breaking Changes:
- None"
```

### 4. Tag-ları `--follow-tags` ilə Push Edin

```bash
# Default push davranışına əlavə edin
git config --global push.followTags true

# Artıq git push avtomatik annotated tag-ları push edəcək
git push origin main
# v1.2.0 tag-ı da push olunur
```

### 5. Tag Naming Convention

```
Production releases: v1.0.0, v1.1.0, v2.0.0
Pre-releases:        v2.0.0-alpha.1, v2.0.0-beta.1
Hotfixes:            v1.0.1, v1.0.2
```

### 6. Köhnə Tag-ları Silməyin

```
Tag-lar tarixçədir, silməyin!
Yanlış tag varsa:
  1. Yeni düzgün tag yaradın
  2. Release notes-da qeyd edin
  3. Köhnə tag-ı yalnız heç kim istifadə etmirsə silin
```

## Əlaqəli Mövzular

- [12-git-config.md](12-git-config.md) — git konfiqurasiyası
- [26-conventional-commits-semantic-release.md](26-conventional-commits-semantic-release.md) — tag-ları avtomatik yaratmaq
