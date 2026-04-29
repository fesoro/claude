# Git Advanced — Dərin Mexanizmlər (Lead)

## İcmal

Bu mövzu Git-in az tanınan amma güclü mexanizmlərini əhatə edir: **rerere**, **notes**, **replace**, **alternates** və **grafts**. Bunlar gündəlik işdə nadir istifadə olunsa da, böyük layihələrdə, uzun tarixli monorepolarda və xüsusi Git workflow-larında həlledici rol oynayır.

## Niyə Vacibdir

- **rerere** — uzun ömürlü branch-lərdə eyni conflict-ləri dəfələrlə həll etmək məcburiyyətini aradan qaldırır
- **notes** — commit mesajını dəyişmədən əlavə metadata (CI nəticəsi, review qeydləri) bağlamağa imkan verir
- **replace** — tarix cerrahiyyəsi zamanı SHA-ları "kölgədə" əvəz etməyə imkan verir
- **alternates** — disk yerindən qənaət edərək obyektləri repolar arasında paylaşır
- **grafts** — süni tarix birləşmələri yaradır (köhnəlmiş, amma anlamaq lazımdır)

---

## 1. git rerere — Reuse Recorded Resolution

### Nədir?

**rerere** (Reuse Recorded Resolution) — Git-in conflict həllini "yadda saxlayan" mexanizmidir. Eyni conflict bir daha baş verəndə Git avtomatik həl tətbiq edir.

### Aktiv etmək

```bash
# Global olaraq aktiv et
git config --global rerere.enabled true

# Yalnız bu repo üçün
git config rerere.enabled true

# Avtomatik stage et (tövsiyə olunur)
git config rerere.autoUpdate true
```

### Necə işləyir?

```
1. Conflict baş verir
2. Sən onu əl ilə həll edirsən
3. git rerere həl metodunu .git/rr-cache/ altında saxlayır
4. Növbəti dəfə eyni conflict olduqda Git avtomatik tətbiq edir
```

### Praktik nümunə: uzun ömürlü feature branch

```bash
# main-dən ayrılmış, 3 həftəlik feature branch
git checkout feature/big-refactor

# Hər həftə main-i rebase edirik
git rebase main
# CONFLICT: app/Models/User.php
# ... həll edirik ...
git rerere   # həl yadda saxlanılır
git add .
git rebase --continue

# Bir həftə sonra yenidən rebase
git rebase main
# Eyni conflict — Git avtomatik həl tətbiq etdi!
git add .
git rebase --continue
```

### rerere cache idarəetmək

```bash
# Cache-i göstər
ls .git/rr-cache/
# 7a8b9c.../ (conflict ID-ləri)

# Spesifik həl-i unut
git rerere forget <fayl>

# Bütün cache-i sil
rm -rf .git/rr-cache/

# rerere tətbiq olunmamış conflictləri göstər
git rerere status

# Mövcud conflict-ə rerere tətbiq et
git rerere
```

### `.git/rr-cache/` strukturu

```
.git/rr-cache/
└── 7a8b9c1d2e3f.../
    ├── preimage   ← conflict öncəsi (HEAD + incoming)
    └── postimage  ← həl edilmiş hal
```

### Laravel-də istifadə: uzun DDD refaktor branch-i

```bash
# Domain-Driven Design refaktor — 2 aylıq branch
cd ~/projects/laravel-enterprise
git config rerere.enabled true
git config rerere.autoUpdate true

# Hər sprint-da main rebase
git rebase main
# CONFLICT: app/Providers/AppServiceProvider.php
# (hər iki tərəf yeni service register edib)
# Həll et:
nano app/Providers/AppServiceProvider.php
git rerere   # yadda saxladı

git add .
git rebase --continue

# Növbəti sprint-da rebase
git rebase main
# Eyni conflict → avtomatik həl!
```

---

## 2. git notes — Commit Metadata

### Nədir?

**git notes** — bir commit-ə onun SHA-sını dəyişmədən əlavə mətn (note) bağlamağa imkan verir. Notes ayrı ref-lər (`refs/notes/commits`) altında saxlanılır.

### Əsas əmrlər

```bash
# Commit-ə note əlavə et
git notes add -m "Performance: 120ms response time" HEAD
git notes add -m "Reviewed by: @senior-dev" abc1234

# Commit-in note-unu göstər
git notes show HEAD
git notes show abc1234

# Log-da note-ları da göstər
git log --notes

# Note-u redaktə et
git notes edit HEAD

# Note-u sil
git notes remove HEAD

# Notes-u remote-a push et (adi push-da getmir!)
git push origin refs/notes/commits

# Remote-dan notes fetch et
git fetch origin refs/notes/commits:refs/notes/commits

# Bütün notes ref-lərini siyahıla
git notes list
```

### Notes namespace-ləri

```bash
# Default namespace: refs/notes/commits
# Custom namespace:
git notes --ref=ci-results add -m "build: passed, tests: 145/145" HEAD
git notes --ref=review-status add -m "approved by: tech-lead" HEAD

# Custom namespace-dən oxu
git notes --ref=ci-results show HEAD
git log --notes=ci-results
```

### CI nəticələrini commit-ə əlavə etmək

```yaml
# .github/workflows/note-ci-results.yml
name: CI Results as Notes

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Run tests
        id: tests
        run: |
          ./vendor/bin/pest --log-junit results.xml
          echo "count=$(grep -c 'testcase' results.xml)" >> $GITHUB_OUTPUT

      - name: Add note
        run: |
          git config user.email "ci@example.com"
          git config user.name "CI Bot"
          git notes add -m "ci: tests=${{ steps.tests.outputs.count }}, run=${{ github.run_id }}, status=passed" HEAD
          git push origin refs/notes/commits
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

### Deployment tracking ilə notes

```bash
# Deploy etdikdən sonra commit-ə note əlavə et
COMMIT=$(git rev-parse HEAD)
git notes --ref=deployments add \
  -m "deployed: production, time: $(date -u +%Y-%m-%dT%H:%M:%SZ), deployer: $(whoami)" \
  $COMMIT
git push origin refs/notes/deployments

# Hansı commit-lərin deploy olduğunu görmək
git log --notes=deployments --format="%h %s%n  %N" | grep -A1 "deployed:"
```

### Migration risk qeydləri

```bash
# Böyük migration üçün risk qeydini commit-ə əlavə et
MIGRATION_SHA=$(git log --oneline -1 database/migrations/ | awk '{print $1}')
git notes add \
  -m "migration-risk: ALTER TABLE orders adds index on user_id+status, ~15min on prod (800k rows). gh-ost needed for zero-downtime." \
  $MIGRATION_SHA
git push origin refs/notes/commits
```

---

## 3. git replace — Obyekt Əvəzetmə

### Nədir?

**git replace** — Git obyekt bazasında bir SHA-nı başqa SHA ilə "əvəz edir". Original SHA hər yerdə görünür, amma Git daxilən replacement SHA-nı oxuyur. Tarix cerrahiyyəsi (history surgery) üçün istifadə olunur.

### Əsas əmrlər

```bash
# Bir commit-i başqa ilə əvəz et
git replace <original-sha> <replacement-sha>

# Aktiv replacements-i göstər
git replace -l

# Spesifik replacement sil
git replace -d <original-sha>

# Replace olmadan bax (bypass)
git --no-replace-objects log
git --no-replace-objects cat-file -p <sha>

# Replacements-i push et
git push origin refs/replace/*

# Fetch et
git fetch origin refs/replace/*:refs/replace/*
```

### Praktik istifadə: commit mesajını SHA dəyişmədən "düzəltmək"

```bash
# Köhnə commit-in düzəldilmiş versiyasını yarat
OLD_SHA="abc1234abc1234"

# Commit məzmununu çıxar
git cat-file commit $OLD_SHA > /tmp/old-commit.txt

# Mesajı dəyiş (son sətir)
sed -i 's/wrong message/correct message/' /tmp/old-commit.txt

# Yeni commit obyekti yarat
NEW_SHA=$(git hash-object -t commit -w /tmp/old-commit.txt)

# Orijinalı əvəz et
git replace $OLD_SHA $NEW_SHA

# Yoxla
git log --oneline $OLD_SHA  # Düzgün mesajı göstərir
git --no-replace-objects log --oneline $OLD_SHA  # Orijinal mesajı göstərir
```

### Praktik istifadə: SVN miqrasiyasında tarix birləşdirmə

```bash
# SVN-dən Git-ə keçiddə tarix qırığı var
# Köhnə son commit (SVN-dən):
OLD_LAST="def5678def5678"
# Yeni Git-in ilk commit-i:
NEW_FIRST=$(git log --oneline | tail -1 | awk '{print $1}')

# Yeni commit-in parent-ini köhnə commit et
git replace $NEW_FIRST \
  $(git commit-tree $(git rev-parse ${NEW_FIRST}^{tree}) \
    -p $OLD_LAST \
    -m "$(git log -1 --format=%s $NEW_FIRST)")

# İndi git log hər iki tarixi bir arada göstərir
git log --oneline --graph | head -20
```

### `.git/refs/replace/` strukturu

```
.git/refs/replace/
└── abc1234abc1234abc1234...  ← orijinal SHA (fayl adı)
                                  İçi: replacement SHA
```

---

## 4. git alternates — Paylaşılan Obyekt Bazası

### Nədir?

**Alternates** — bir Git repo-nun `.git/objects/info/alternates` faylında başqa repo-nun obyekt bazasından istifadə etməsinə imkan verir. Eyni kod bazasının çoxlu klonları olduqda disk yerindən qənaət etmək üçün istifadə olunur.

### Necə işləyir?

```
Repo A (shared mirror):
.git/objects/
├── ab/c123...  (blob: User.php 2022)
├── de/f456...  (tree: app/ v1)
└── pack/pack-abc.pack

Repo B (.git/objects/info/alternates → Repo A):
.git/objects/
└── (yalnız Repo A-da olmayan yeni obyektlər)

Repo B git log işlədəndə Repo A-nın obyektlərini də oxuyur.
Disk: Repo A = 2 GB, Repo B = 50 MB (yeni dəyişikliklər)
```

### `--reference` ilə klon

```bash
# Mövcud klonu reference kimi istifadə et
git clone --reference /path/to/existing-clone git@github.com:org/repo.git

# --dissociate: reference kopyalanır, sonra əlaqə kəsilir (safe)
git clone --dissociate --reference /path/to/ref git@github.com:org/repo.git
```

### Alternates faylı

```bash
# .git/objects/info/alternates məzmunu
cat .git/objects/info/alternates
# /home/developer/projects/laravel-api/.git/objects

# Əl ilə əlavə et
mkdir -p .git/objects/info
echo "/absolute/path/to/reference/.git/objects" >> .git/objects/info/alternates
```

### CI runner-lərdə alternates

```bash
# 1. CI server-də bir dəfə tam mirror yarat (cron ilə yenilənir)
git clone --mirror git@github.com:org/laravel-app.git /ci/shared/laravel-app.git
# Cron: 0 * * * * cd /ci/shared/laravel-app.git && git fetch --all

# 2. Hər CI job-da reference klon (sürətli!)
git clone \
  --reference /ci/shared/laravel-app.git \
  --dissociate \
  git@github.com:org/laravel-app.git \
  /tmp/build-$BUILD_ID

# 3. /tmp/build-$BUILD_ID öz-özünə kifayətlənir (--dissociate sayəsində)
# Mirror silinsə belə klon çalışır
```

### Mühüm xəbərdarlıq

```
⚠️ --reference istifadə edərkən:
- Reference repo silinsə, klonlar pozula bilər (--dissociate olmadan)
- Reference repo-nun GC-si lazımi obyektləri silə bilər
- Həll: --dissociate flag-ı ilə klon et
```

---

## 5. git grafts — Süni Tarix Birləşmələri (Köhnə)

### Nədir?

**Grafts** (`.git/info/grafts`) — commit-lərin parent-lərinə süni əlavələr edir. `git replace` gəldikdən sonra **köhnəlmiş** sayılır, amma mövcud layihələrdə rastlaşa bilərsiniz.

```
⚠️ Xəbərdarlıq:
git grafts köhnə mexanizmdir.
Yeni layihələrdə git replace istifadə edin.
```

### `.git/info/grafts` formatı

```
# Format: <commit-sha> <parent1-sha> [<parent2-sha> ...]
# Bir sətirdə: "bu commit-in REAL parent-i bunlardır"

abc1234 def5678
# abc1234 commit-inin parent-i def5678-dir (real SHA nə olursa olsun)

# Merge graft (iki parent):
ghi9012 jkl3456 mno7890
```

### Praktik nümunə: miqrasiya keçidi

```bash
# VCS-dən Git-ə keçiddə tarix qırığı var
# İlk Git commit: abc1234
# Köhnə son commit: def5678

mkdir -p .git/info
echo "abc1234 def5678" >> .git/info/grafts

# İndi git log hər iki tarixi birlikdə göstərir
git log --oneline --graph | head -20

# Grafts-ı kalıcı etmək (tövsiyə olunur):
git filter-repo --graft abc1234:def5678
# Bu graft-ı obyekt bazasına "yapışdırır", .git/info/grafts silmək olar
```

### Replace vs Grafts müqayisəsi

| Xüsusiyyət | `git grafts` | `git replace` |
|------------|--------------|---------------|
| Fayl yeri | `.git/info/grafts` | `.git/refs/replace/` |
| Push olunur | Xeyr (yalnız local) | Bəli (`refs/replace/*`) |
| Modern? | Köhnə (deprecated) | Müasir |
| Qlobal paylaşma | Çətin | Asandır |
| Tövsiyə | Yalnız köhnə repolar | Yeni layihələr |

---

## Vizual İzah

### rerere iş axını

```
  Conflict baş verir
        │
        v
  Sən həl edirsən (əl ilə)
        │
        v
  git rerere ──────────> .git/rr-cache/<id>/postimage
        │
    [yadda saxlandı]
        │
  Sonradan eyni conflict
        │
        v
  git rerere ──────────> Avtomatik tətbiq edir
        │
  [manual həl lazım deyil!]
```

### git notes strukturu

```
  Commit Object              Notes Object Store
  ┌─────────────┐            ┌─────────────────────────────┐
  │ SHA: abc123 │            │ refs/notes/commits           │
  │ tree: ...   │            │                              │
  │ parent: ... │            │ abc123 → "CI: passed, t=145" │
  │ author: ... │            │ def456 → "reviewed: OK"      │
  │ message: .. │            └─────────────────────────────┘
  └─────────────┘
     (dəyişmir)

  git log --notes:
  commit abc123
  Author: ...
      feat: add payment

  Notes:
      CI: passed, t=145
```

### replace mexanizmi

```
  git log görünüşü:                    Realdakı:
  ┌──────────────────┐                 ┌──────────────────────┐
  │ abc123: correct  │  <-- replace    │ .git/refs/replace/   │
  │       message    │     table       │  abc123 → xyz789     │
  └──────────────────┘                 └──────────────────────┘
                                       Git xyz789-u oxuyur,
                                       amma abc123 göstərir
```

### alternates disk qənaəti

```
  Shared Mirror (2 GB):
  .git/objects/pack/
    pack-main.pack   (2 GB — bütün tarix)

  CI Build Clone (50 MB):
  .git/objects/
    ab/c123...       (yalnız yeni build artifacts)
  .git/objects/info/alternates:
    /ci/shared/laravel-app.git/objects  ← köhnə obyektlər buradan

  Nəticə: 2 GB + 2 GB + 2 GB = 6 GB əvəzinə 2 GB + 50 MB + 50 MB
```

---

## Praktik Baxış

### rerere — uzun ömürlü branch strategiyası

```bash
cd ~/projects/laravel-enterprise
git config rerere.enabled true
git config rerere.autoUpdate true

# DDD refaktor branch-i
git checkout feature/ddd-refactor

# Sprint 1 sonu
git rebase main
# CONFLICT: app/Providers/AppServiceProvider.php
# ... həll et ...
git add app/Providers/AppServiceProvider.php
git rebase --continue
# rerere yadda saxladı

# Sprint 2 sonu
git rebase main
# Eyni CONFLICT → git avtomatik tətbiq etdi!
git add app/Providers/AppServiceProvider.php
git rebase --continue
# Həl üçün vaxt sərf etmək lazım olmadı
```

### notes — release management

```bash
# Release zamanı hər commit-ə test state əlavə et
git log v1.2.0..v1.3.0 --format="%H" | while read sha; do
  git notes add -m "release-candidate: v1.3.0, qa-status: pending" $sha
done
git push origin refs/notes/commits

# QA-dan sonra update
git notes --ref=qa-status add -m "qa-approved: 2026-04-29, tester: ali" HEAD
git push origin refs/notes/qa-status

# Release note-larına bax
git log v1.2.0..v1.3.0 --notes --format="%h %s%n  %N"
```

### replace — production audit

```bash
# Production-da bug var, commit mesajı yanlış yazılıb
# (SHA-nı dəyişə bilmərik — CI/CD pipeline SHA-ya bağlıdır)
OLD_SHA="abc1234abc1234"

git cat-file commit $OLD_SHA > /tmp/fix-commit.txt
# Fayl məzmunu:
# tree ...
# parent ...
# author ...
# committer ...
#
# [ORIG] fix payment typo

# Mesajı düzəlt
sed -i 's/\[ORIG\] fix payment typo/fix(payment): prevent negative amount on refund (CVE-2026-001)/' \
  /tmp/fix-commit.txt

NEW_SHA=$(git hash-object -t commit -w /tmp/fix-commit.txt)
git replace $OLD_SHA $NEW_SHA
git push origin refs/replace/*
# Artıq audit log doğru mesajı göstərir
```

---

## Praktik Tapşırıqlar

1. **rerere aktiv et və test et**
   ```bash
   git config rerere.enabled true
   git config rerere.autoUpdate true

   # Test: intentional conflict yarat, həll et, yenidən yarat
   # rerere avtomatik tətbiq etdimi?
   ls .git/rr-cache/  # conflict ID-lər görünməlidir
   ```

2. **git notes ilə metadata əlavə et**
   ```bash
   git notes add -m "manual-review: passed, reviewer: $(git config user.name), date: $(date +%Y-%m-%d)" HEAD
   git log --notes -1
   git notes show HEAD

   # Custom namespace:
   git notes --ref=performance add -m "p95: 45ms, p99: 120ms" HEAD
   git log --notes=performance -1
   ```

3. **notes-u push/fetch et**
   ```bash
   git push origin refs/notes/commits
   # Yeni terminaldən:
   git fetch origin refs/notes/commits:refs/notes/commits
   git log --notes
   ```

4. **replace ilə test et**
   ```bash
   git commit --allow-empty -m "original wrong message"
   ORIG=$(git rev-parse HEAD)

   # Düzəldilmiş versiya
   git commit --allow-empty --amend -m "fix: correct message"
   FIXED=$(git rev-parse HEAD)

   # Geri qayıt və replace et
   git reset --hard $ORIG
   git replace $ORIG $FIXED

   git log --oneline -1         # "fix: correct message" görünür
   git --no-replace-objects log --oneline -1  # "original wrong message"
   ```

---

## Interview Sualları (Q&A)

### Q1: rerere nədir və nə vaxt faydalıdır?

**Cavab:** `rerere` (Reuse Recorded Resolution) — Git-in conflict həllini yadda saxlayan mexanizmidir. Uzun ömürlü feature branch-ləri tez-tez main-ə rebase edildikdə eyni konfliktlər dəfələrlə baş verir. Rerere birinci həl-i `.git/rr-cache/` altında saxlayır, sonrakılarda avtomatik tətbiq edir. `git config rerere.enabled true` ilə aktiv olunur.

### Q2: git notes commit-i dəyişdirirmi?

**Cavab:** Xeyr. Notes ayrı `refs/notes/commits` ref-i altında saxlanılır. Original commit SHA-sı dəyişmir — buna görə CI pipeline-lar, deployment sistemlər, issue tracker-lər pozulmur. Ancaq notes default olaraq `git push` ilə getmir — ayrıca `git push origin refs/notes/commits` lazımdır.

### Q3: git replace və git graft arasında fərq nədir?

**Cavab:**
- `grafts` — `.git/info/grafts` local faylı, push edilmir, köhnə mexanizmdir (deprecated)
- `replace` — `.git/refs/replace/` altında saxlanılır, `git push origin refs/replace/*` ilə paylaşıla bilər, müasir üsuldur

Yeni layihələrdə `git replace` istifadə edin. Köhnə grafts olan repo-nu `git filter-repo --graft` ilə kalıcı hala gətirin.

### Q4: Alternates nəyə lazımdır?

**Cavab:** CI-da hər build üçün full clone bahalıdır. Alternates ilə server-də bir "mirror" clone saxlanılır, yeni buildlər yalnız fərqli (yeni) obyektləri yükləyir. `--reference` ilə klon edib `--dissociate` istifadə edilsə, klon öz-özünə kifayətlənir — mirror silinsə belə çalışır.

### Q5: rerere cache-i komanda üzvləri arasında paylaşmaq olarmı?

**Cavab:** Default olaraq `.git/rr-cache/` yalnız lokaldır. Paylaşmaq üçün:
```bash
# rr-cache-i remote-a push et (custom ref ilə)
git push origin HEAD:refs/rerere-cache

# Başqa developer fetch edir
git fetch origin refs/rerere-cache
# Sonra .git/rr-cache/-i əl ilə yeniləmək lazımdır
```
Praktikada rerere çox vaxt şəxsi developer iş axını üçün yetərlidir.

### Q6: git notes ile git commit --amend fərqi nədir?

**Cavab:**
- `git commit --amend` SHA-nı dəyişdirir — existing CI pipeline-lar, PR-lər, tag-lar pozulur
- `git notes add` SHA-nı saxlayır — yalnız əlavə metadata əlavə edir, heç nəyi pozmur

Audit trail, post-deployment metadata, review qeydlərini geri əlavə etmək üçün notes idealdır.

---

## Best Practices

1. **rerere-ni uzun ömürlü branch-lərdə mütləq aktiv edin**: `git config rerere.enabled true` + `git config rerere.autoUpdate true`. DDD refaktor, feature flag arxasında böyük feature-lar üçün vacibdir.

2. **notes-u CI/CD-yə inteqrasiya edin**: Hər build nəticəsini, deploy tarixini, QA statusunu notes vasitəsilə commit-ə bağlayın. SHA dəyişmir, amma məlumat tarixdə qalır.

3. **notes namespace-ləri ayırın**: `--ref=ci-results`, `--ref=deployments`, `--ref=reviews` kimi ayrı namespace-lər istifadə edin, karışıqlıq olmur.

4. **replace-i tarix cerrahiyyəsi üçün istifadə edin**: Köhnə commit SHA-larını pozmadan metadata yeniləmə, tarix birləşdirmə üçün idealdır. Team-ə `refs/replace/*` push etməyi unutmayın.

5. **grafts-dan qaçının**: Yeni layihələrdə `git replace` istifadə edin. Köhnə repo-da grafts varsa, `git filter-repo` ilə kalıcı hala gətirin.

6. **alternates-da `--dissociate` istifadə edin**: Reference silinəndə klonların sınmaması üçün vacibdir. CI build-lərdə bu kritikdir.

7. **notes-u fetch konfiqine əlavə edin**: `.git/config`-ə
   ```
   [remote "origin"]
       fetch = +refs/notes/*:refs/notes/*
       fetch = +refs/replace/*:refs/replace/*
   ```
   bu şəkildə `git fetch` avtomatik notes və replace-ləri gətirəcək.

---

## Əlaqəli Mövzular

- [08-git-reset-revert.md](08-git-reset-revert.md) — reflog ilə recovery
- [23-git-advanced-commands.md](23-git-advanced-commands.md) — filter-repo, plumbing commands
- [32-git-internals.md](32-git-internals.md) — Git object modeli (replace/notes-u anlamaq üçün)
