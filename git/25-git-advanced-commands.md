# Git Advanced Commands

## Nədir? (What is it?)

Git-in gündəlik istifadədən kənar, amma xüsusi vəziyyətlərdə çox güclü olan əmrləri var. Bu əmrlər repo tarixini yenidən yazmaq, böyük data idarə etmək, konflikt həlli-ni avtomatlaşdırmaq və performance optimize etmək üçün istifadə olunur.

```
Advanced Əmrlər Kateqoriyaları:
┌──────────────────────────────────────────────────┐
│ Tarix yazmaq:   filter-branch, filter-repo       │
│ Konflikt auto:  rerere                           │
│ Metadata:       notes                            │
│ Arxiv/Backup:   archive, bundle                  │
│ Optimallaşdırma: gc, fsck, repack, pack-refs    │
│ Böyük repo:     sparse-checkout, partial-clone  │
│ Debug:          blame, grep, log --follow       │
│ Script:         plumbing əmrlər                  │
└──────────────────────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### filter-repo (modern, tövsiyə olunur)

```bash
# Quraşdır
pip install git-filter-repo

# Fayl sil (bütün tarixdən)
git filter-repo --path secrets.env --invert-paths

# Qovluq sil
git filter-repo --path vendor/ --invert-paths

# Yalnız bir qovluğu saxla (extract)
git filter-repo --path app/ --path tests/

# Fayl rename et (tarix ilə)
git filter-repo --path-rename old-name:new-name

# Author email dəyiş
git filter-repo --email-callback '
    return email.replace(b"old@company.com", b"new@company.com")
'

# Böyük obyektləri sil
git filter-repo --strip-blobs-bigger-than 10M

# Mail başlıqları təmizlə
git filter-repo --mailmap .mailmap
```

### filter-branch (köhnə, deprecated)

```bash
# Fayl sil tarixdən (YAVAŞ)
git filter-branch --force --index-filter \
  'git rm --cached --ignore-unmatch secrets.env' \
  --prune-empty --tag-name-filter cat -- --all

# Müəllif dəyiş
git filter-branch --env-filter '
if [ "$GIT_COMMITTER_EMAIL" = "old@email.com" ]; then
  export GIT_COMMITTER_EMAIL="new@email.com"
  export GIT_COMMITTER_NAME="New Name"
fi' --tag-name-filter cat -- --branches --tags

# Qeyd: filter-repo daha sürətli və təhlükəsizdir
```

### rerere (Reuse Recorded Resolution)

```bash
# Aktivləşdir
git config --global rerere.enabled true

# Konflikti həll et (adi yolla)
# git konflikt həllini yadda saxlayır

# Növbəti dəfə eyni konflikt baş verəndə
# avtomatik həll olunur

# Status
git rerere status
git rerere diff

# Həlləri gör
ls .git/rr-cache/

# Təmizlə
git rerere clear
git rerere gc
```

### notes

```bash
# Note əlavə et
git notes add -m "Code review: LGTM" HEAD
git notes add -m "Deploy to prod 2024-01-15" abc123

# Note göstər
git notes show HEAD
git log --show-notes

# Edit
git notes edit HEAD

# Sil
git notes remove HEAD

# Notes push et (default push etmir)
git push origin refs/notes/*
git fetch origin refs/notes/*:refs/notes/*
```

### archive

```bash
# Snapshot yarat (zip/tar)
git archive --format=zip --output=release-v1.0.zip v1.0
git archive --format=tar.gz --output=release.tar.gz HEAD

# Xüsusi qovluq
git archive --format=zip HEAD:app/ > app-snapshot.zip

# Prefix ilə (unzip təmiz qovluq üçün)
git archive --format=tar.gz --prefix=myapp-v1.0/ v1.0 > myapp-v1.0.tar.gz

# Remote-dan
git archive --remote=git@github.com:user/repo.git --format=tar HEAD > repo.tar
```

### bundle

```bash
# Bütün repo-nu bundle-ya yığ
git bundle create backup.bundle --all

# Yalnız bir branch
git bundle create main.bundle main

# Range
git bundle create incremental.bundle main~10..main

# Bundle-dan clone
git clone backup.bundle restored-repo

# Bundle-i mövcud repo-da fetch et
git fetch ../backup.bundle main:restored-main

# Yoxla
git bundle verify backup.bundle
```

### gc (Garbage Collection)

```bash
# Default (auto-avaried)
git gc

# Aqressiv (yavaş amma yaxşı sıxışdırma)
git gc --aggressive

# Prune et (90 gündən köhnə unreachable obyektlər)
git gc --prune=now

# Hər ikisi
git gc --aggressive --prune=now

# Avtomatik threshold
git config gc.auto 6700                    # Avto GC loose obyekt limiti
git config gc.autoPackLimit 50             # Avto GC pack limiti
```

### fsck (File System Check)

```bash
# Əsas yoxlama
git fsck

# Detallı
git fsck --full --strict --unreachable

# Lost and found
git fsck --lost-found
# .git/lost-found/commit/ və .git/lost-found/other/

# Dangling commit-lər (orphan)
git fsck --dangling
```

### sparse-checkout (Git 2.25+)

```bash
# Böyük monorepo-nun yalnız bir hissəsini checkout et

# Aktiv et
git sparse-checkout init --cone

# Lazımi qovluqları seç
git sparse-checkout set apps/api libs/shared

# Listlə
git sparse-checkout list

# Yenilə
git sparse-checkout add apps/admin

# Sıfırla (hər şeyi)
git sparse-checkout disable

# Advanced (pattern-lər)
git sparse-checkout set --no-cone
echo "/*.md" > .git/info/sparse-checkout
echo "!/test/" >> .git/info/sparse-checkout
git checkout HEAD
```

### partial clone (Git 2.19+)

```bash
# Blob-ları lazım olduqda yüklə (treeless)
git clone --filter=blob:none <url>

# Tree-siz
git clone --filter=tree:0 <url>

# Böyük blob-ları istisna
git clone --filter=blob:limit=10m <url>

# Sparse checkout ilə birlikdə
git clone --filter=blob:none --sparse <url>
cd repo
git sparse-checkout set apps/api
```

## Praktiki Nümunələr (Practical Examples)

### 1. Həssas Məlumatları Sil (filter-repo)

```bash
# Problem: .env faylı tarixdə qalıb
git log --all --full-history -- .env

# Həll
pip install git-filter-repo
git filter-repo --path .env --invert-paths

# Force push (bütün komanda yenidən clone etməlidir!)
git remote add origin <url>  # filter-repo remote-u silir
git push --force --all
git push --force --tags

# Credentials rotasiya et (MÜTLƏQ)!
# DB password dəyiş
# API keys yenilə
```

### 2. Büyük Faylları Təmizlə

```bash
# Repo ölçüsü 500MB → lazımdır 50MB

# Böyük fayları tap
git rev-list --objects --all | \
  git cat-file --batch-check='%(objectsize:disk) %(rest)' | \
  sort -n | tail -20

# Nəticə:
# 104857600 storage/app/backup.sql
# 52428800 public/video.mp4

# 10MB-dən böyükləri sil
git filter-repo --strip-blobs-bigger-than 10M

# Yoxla
du -sh .git/
```

### 3. Qovluqdan Yeni Repo Yarat (Extract)

```bash
# Monorepo-dan bir qovluq çıxarıb ayrıca repo et

# Yalnız packages/auth/ saxla
git filter-repo --path packages/auth/ --path-rename packages/auth/:

# İndi packages/auth/ məzmunu repo root-unda
git remote add origin <new-repo-url>
git push -u origin main
```

### 4. rerere İlə Təkrarlanan Konflikti Həll

```bash
# Ssenariya: uzun davam edən feature branch
# Hər dəfə main-dən rebase edəndə eyni konflikt

# Aktivləşdir
git config --global rerere.enabled true

# İlk konflikti həll et
git rebase main
# Konflikt: app/Config.php
# Həll et, add, rebase --continue

# Növbəti həftə eyni rebase
git rebase main
# Eyni konflikt - avtomatik həll olunur!
# Resolved 'app/Config.php' using previous resolution.
```

### 5. notes ilə Metadata

```bash
# Deploy informasiyası commit-ə əlavə et
git notes add -m "Deployed to prod: 2024-04-17 14:30" abc123
git notes add -m "QA approved by: @jane" abc123
git notes add -m "Rollback: v2.4.1" abc123

# Log-da göstər
git log --show-notes

# Xüsusi namespace
git notes --ref=deploy add -m "Prod deploy" HEAD
git notes --ref=qa add -m "Passed" HEAD
```

### 6. bundle ilə Offline Transfer

```bash
# Ofislər arası internet yoxdur, USB ilə transfer

# A ofisdə
git bundle create company-repo.bundle --all

# USB-yə yaz, B ofisə gətir

# B ofisdə
git clone company-repo.bundle my-repo
cd my-repo

# Incremental update (növbəti dəfə)
# A ofisdə (yalnız yeni commit-lər)
git bundle create update.bundle main~50..main

# B ofisdə
git fetch ../update.bundle main:main
```

### 7. archive ilə Release Paket

```bash
# Release zip yarat (git metadata olmadan)
git archive --format=zip \
            --prefix=myapp-v2.0/ \
            --output=myapp-v2.0.zip \
            v2.0

# İçində:
# myapp-v2.0/
# ├── app/
# ├── config/
# ├── composer.json
# └── ...
# (.git yoxdur, .gitignore yoxdur)

# GitHub release-ə yüklə
gh release create v2.0 myapp-v2.0.zip --notes "Release v2.0"
```

### 8. sparse-checkout ilə Monorepo

```bash
# 10GB monorepo var, yalnız frontend lazımdır

git clone --filter=blob:none --sparse <url>
cd repo

# Yalnız apps/admin və libs/ui
git sparse-checkout init --cone
git sparse-checkout set apps/admin libs/ui

# Nəticə: yalnız 500MB yükləndi
ls -la
# apps/admin/
# libs/ui/
# (digər qovluqlar yoxdur)
```

### 9. partial clone ilə CI

```bash
# CI-da tam history lazım deyil, build üçün

# 1GB repo yerinə 100MB
git clone --filter=blob:none --depth 1 <url> /workspace

cd /workspace
# İstədiyin zaman blob-lar fetch olunur
git log --oneline   # Tez, blob-lar yüklənmir
cat README.md       # Blob fetch edilir bu an
```

### 10. Bulk Email Dəyişdirmə

```bash
# Çoxlu developer company domain-i dəyişdi
# @oldcompany.com → @newcompany.com

# .mailmap yarat
cat > .mailmap << 'EOF'
New Name <new@newcompany.com> <old@oldcompany.com>
Jane Doe <jane@newcompany.com> <jane.doe@oldcompany.com>
EOF

# filter-repo ilə tətbiq et
git filter-repo --mailmap .mailmap

# Yoxla
git log --pretty=format:'%an <%ae>' | sort -u
```

## Vizual İzah (Visual Explanation)

### filter-repo Əməliyyatı

```
Əvvəl:
A ─── B ─── C ─── D ─── E (HEAD)
│     │     │     │     │
tree: tree: tree: tree: tree:
.env  .env  .env  .env  .env
app/  app/  app/  app/  app/

git filter-repo --path .env --invert-paths

Sonra:
A' ── B' ── C' ── D' ── E' (HEAD)
│     │     │     │     │
tree: tree: tree: tree: tree:
app/  app/  app/  app/  app/

Yeni SHA-lar! Tarix tamamilə yenidən yazıldı.
```

### sparse-checkout Vizual

```
Repo (remote): 1GB
┌────────────────────┐
│ apps/              │
│ ├── api/     500MB │
│ ├── admin/   200MB │ ← istəyirəm
│ └── mobile/  200MB │
│ libs/              │
│ ├── ui/       50MB │ ← istəyirəm
│ └── shared/   50MB │
└────────────────────┘

Normal clone:
┌────────────────────┐
│ 1GB working tree   │
└────────────────────┘

Sparse checkout:
┌────────────────────┐
│ 250MB working tree │ ← yalnız lazımi
│ apps/admin/        │
│ libs/ui/           │
└────────────────────┘
```

### bundle File Format

```
my-repo.bundle
┌────────────────────────────────────┐
│ Bundle header                      │
│ # v2 git bundle                    │
│ refs: main, develop, v1.0          │
│ prerequisites: (empty for --all)   │
├────────────────────────────────────┤
│ Packfile (sıxışdırılmış obyektlər) │
│ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓  │
└────────────────────────────────────┘

git clone bundle → yeni repo
git fetch bundle → mövcud repo yenilə
```

### rerere İş Prinsipi

```
İlk konflikt:
┌───────────────────────┐
│ <<<<<<< HEAD          │
│ code A                │
│ =======               │
│ code B                │
│ >>>>>>> feature       │
└───────────┬───────────┘
            │
            │ Developer həll edir: code A
            │
            v
┌───────────────────────┐
│ rerere yadda saxlayır:│
│ Pre-image → Post-image│
│ A vs B    →  A        │
└───────────┬───────────┘
            │
            │ Sonrakı eyni konflikt...
            │
            v
    AVTOMATIK həll!
```

## PHP/Laravel Layihələrdə İstifadə

### 1. Laravel .env və Secrets Təmizləmə

```bash
# Problem: Laravel .env tarixdə qalıb
git log --all --full-history -- .env

# filter-repo ilə tamamilə sil
git filter-repo --path .env --invert-paths \
                --path .env.production --invert-paths \
                --path config/secrets.php --invert-paths

# AWS/Laravel keys rotasiya et
php artisan key:generate --force

# Force push (komandaya xəbər ver!)
git push --force --all
```

### 2. Laravel Monorepo sparse-checkout

```bash
# Böyük Laravel monorepo
# my-laravel-monorepo/
# ├── apps/
# │   ├── api/
# │   ├── admin/
# │   └── public-site/
# └── packages/

# Yalnız API üzərində işləyirəm
git clone --filter=blob:none --sparse <repo-url>
cd my-laravel-monorepo
git sparse-checkout init --cone
git sparse-checkout set apps/api packages/shared

# İndi yalnız lazımı qovluqlar var
cd apps/api
composer install
php artisan serve
```

### 3. Release Archive

```bash
# Laravel v2.0 release zip yarat
git archive --format=zip \
            --prefix=my-laravel-v2.0/ \
            --output=release.zip \
            v2.0

# Production deploy üçün (vendor yoxdur)
unzip release.zip
cd my-laravel-v2.0
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
```

### 4. Laravel Backup Bundle

```bash
# Offline backup
git bundle create laravel-backup-$(date +%Y%m%d).bundle --all

# Restore
git clone laravel-backup-20240417.bundle my-laravel-restored
cd my-laravel-restored
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 5. Laravel CI ilə partial clone

```yaml
# .github/workflows/test.yml
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          # CI-da tam history lazım deyil
          fetch-depth: 1
          # Böyük fayllar lazım deyil
          filter: blob:none
      
      - uses: shivammathur/setup-php@v2
      - run: composer install --no-dev
      - run: php artisan test
```

### 6. Böyük Composer Paket Temizlemek

```bash
# composer.phar və ya vendor commit olunub
git filter-repo --path vendor/ --invert-paths
git filter-repo --path composer.phar --invert-paths

# Repo ölçüsü
du -sh .git/
# Əvvəl: 500MB → Sonra: 50MB
```

### 7. Laravel Notes - Deploy Log

```bash
# Hər production commit-ə deploy info əlavə et
#!/bin/bash
# deploy.sh
COMMIT=$(git rev-parse HEAD)
DATE=$(date +"%Y-%m-%d %H:%M")

git notes --ref=deploy add -m "Deployed to prod: $DATE by $USER" $COMMIT
git push origin refs/notes/deploy

echo "Deploy logged for $COMMIT"
```

### 8. rerere Uzun Laravel Feature

```bash
# 2 aylıq refactoring branch
# Hər dəfə main-dən rebase edirəm
# Composer.lock hər dəfə konflikt

git config rerere.enabled true

# İlk rebase-də həll et
git rebase main
# composer.lock konflikt
rm composer.lock
composer install
git add composer.lock
git rebase --continue

# Növbəti həftə
git rebase main
# rerere avtomatik composer.lock həll edir
```

## Interview Sualları

### Q1: filter-repo və filter-branch arasında fərq nədir?
**Cavab:**
- **filter-branch** - köhnə, yavaş, shell-based, rəsmi deprecated
- **filter-repo** - yeni (Python), 10-100 dəfə sürətli, daha təhlükəsiz

Git rəsmi olaraq filter-repo tövsiyə edir. filter-branch üstündə xəbərdarlıq göstərir.

### Q2: rerere nədir və nə vaxt istifadə olunur?
**Cavab:** "Reuse Recorded Resolution" - Git konflikt həllini yadda saxlayır və növbəti dəfə avtomatik tətbiq edir. İstifadə halları:
- Uzun davam edən feature branch-lər
- Tez-tez rebase edilən repo-lar
- Composer.lock, package-lock.json tip fayllarda təkrar konflikt

```bash
git config --global rerere.enabled true
```

### Q3: git notes nə üçündür?
**Cavab:** Notes commit-ə əlavə metadata yapıştırır BÜTÜN commit-in hash-ını dəyişdirmədən. İstifadə halları:
- Deploy loqları
- QA approvals
- Code review kommentari
- Build nəticələri
- Release qeydləri

Fərq: commit message-i dəyişmək SHA-nı dəyişir, notes isə yox.

### Q4: archive və bundle arasında fərq nədir?
**Cavab:**
- **archive** - snapshot (zip/tar), git metadata YOXDUR, istifadəyə hazır
- **bundle** - git repo (portable), git metadata VAR, clone/fetch olunur

```bash
# archive - production deploy üçün
git archive --format=zip v1.0 > release.zip

# bundle - backup, offline transfer üçün
git bundle create backup.bundle --all
```

### Q5: sparse-checkout nə vaxt faydalıdır?
**Cavab:** Böyük monorepo-larda yalnız bir hissəsini checkout etmək istədikdə:
- Disk yerinə qənaət
- Tez build (az fayl)
- Daha aydın workspace

```bash
git sparse-checkout set apps/admin libs/ui
```

Qeyd: Repo-nun bütün history-si yenə də yüklənir (yalnız working tree seçilir). Tam ölçü qənaəti üçün `partial clone` əlavə et.

### Q6: partial clone sparse-checkout-dan nə ilə fərqlənir?
**Cavab:**
- **sparse-checkout** - working tree-ni məhdudlaşdırır, .git yenə tam
- **partial clone** - .git-i məhdudlaşdırır (blob-lar lazım olduqda yüklənir)

Birlikdə istifadə etmək ən yaxşısıdır:
```bash
git clone --filter=blob:none --sparse <url>
```

### Q7: git gc nə vaxt işlətməliyik?
**Cavab:**
- **Auto** - Git özü işlədir (hər 2 həftədə, 6700+ loose obyekt olduqda)
- **Manual** - böyük əməliyyatdan sonra (filter-repo, böyük fayl silindikdə)
- **--aggressive** - ayda 1 dəfə və ya CI-da (yavaş)
- **--prune=now** - secret silindikdən sonra dərhal

```bash
git gc --aggressive --prune=now
```

### Q8: fsck-ın --lost-found bayrağı nə edir?
**Cavab:** Orphan (dangling) obyektləri (heç bir ref tərəfindən göstərilməyən) tapır və `.git/lost-found/` qovluğunda saxlayır. Accidentally silinmiş commit-ləri bərpa etmək üçün əvəzsizdir.

```bash
git fsck --lost-found
ls .git/lost-found/commit/
git show <sha>    # Yoxla
git checkout -b recovered <sha>
```

### Q9: bundle offline olaraq repo transfer etmək üçün necə işləyir?
**Cavab:**
```bash
# Source machine-də
git bundle create repo.bundle --all

# USB/email ilə transfer

# Destination machine-də
git clone repo.bundle new-repo
# və ya
git fetch /path/to/repo.bundle main:main
```

Bundle sıxışdırılmış packfile-dır, network olmadan repo köçürməyə imkan verir.

### Q10: Mass refactoring üçün (milyonlarla sətir) hansı əmrlər lazımdır?
**Cavab:**
1. **filter-repo** - fayl renaming, content replacing
2. **rerere** - təkrar konfliktlər
3. **interactive rebase** - commit-ləri təşkil etmə
4. **bisect** - bug regression tapma
5. **cherry-pick** - xüsusi commit-ləri transfer

```bash
# Fayl rename ilə tarix
git filter-repo --path-rename old/:new/

# Email dəyişdirmə
git filter-repo --email-callback 'return email.replace(b"@old", b"@new")'
```

## Best Practices

### 1. filter-repo-dan Əvvəl Backup
```bash
# Tarix dəyişməzdən əvvəl mütləq backup
cp -r my-repo my-repo-backup
# və ya
git bundle create backup.bundle --all
```

### 2. rerere-ni Aktivləşdir
```bash
# Ümumi konfiqurasiyada
git config --global rerere.enabled true
# Uzun feature branch-lərdə vaxt qənaət edir
```

### 3. CI-da Partial Clone
```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 1
    filter: blob:none
# CI sürətini 3-5x artırır
```

### 4. Böyük Fayllar Üçün LFS
```bash
# filter-repo ilə silməzdən əvvəl LFS-a köçür
git lfs migrate import --include="*.mp4,*.psd"
```

### 5. notes-u Namespace-lə
```bash
# Fərqli məqsədlər üçün ayrı refs
git notes --ref=deploy add ...
git notes --ref=qa add ...
git notes --ref=review add ...
```

### 6. archive ilə Release
```bash
# CI-da release paket yarat
git archive --format=tar.gz \
            --prefix=app-${VERSION}/ \
            HEAD | gzip > release.tar.gz
```

### 7. Müntəzəm fsck
```bash
# Ayda 1 dəfə sağlamlıq yoxlaması
git fsck --full --strict
```

### 8. sparse-checkout Ənənəvi Monorepo Üçün
```bash
# Yeni developer üçün:
git clone --filter=blob:none --sparse <url>
cd repo
git sparse-checkout set apps/my-app libs/shared
# 10x tez onboarding
```

### 9. Force Push-dan Sonra Komandaya Bildir
```markdown
# Slack/Email:
"main branch tarixi yenidən yazıldı (git filter-repo).
Bütün developer-lər:
1. Öz dəyişikliklərinizi backup edin
2. `git fetch --all`
3. `git reset --hard origin/main`
və ya yenidən clone edin"
```

### 10. filter-repo Python 3 İstifadə Et
```bash
# Quraşdırma variantları:
pip3 install git-filter-repo                  # pip
sudo apt install git-filter-repo              # Debian/Ubuntu
brew install git-filter-repo                  # macOS
# Script olaraq: https://github.com/newren/git-filter-repo
```
