# Git Troubleshooting

## Nədir? (What is it?)

Git troubleshooting Git ilə işləyərkən qarşılaşılan tipik problemlərin həlli və təcili halda qurtarma üsullarıdır. Ən vacib prinsip: **Git almost never loses data**. Əksər hallarda `reflog` və ya `fsck` ilə datanı qurtarmaq mümkündür.

```
Git Problem Kateqoriyaları:
┌──────────────────────────────────────────────┐
│ 1. Yanlış commit (reset/revert)              │
│ 2. Silinmiş branch (reflog)                  │
│ 3. Konflikt həlli                            │
│ 4. Published dəyişiklikləri ləğv etmək       │
│ 5. Böyük fayllar (LFS)                       │
│ 6. Korlanmış repo                            │
│ 7. Performance problemləri                   │
│ 8. Remote sinx problemləri                   │
└──────────────────────────────────────────────┘
```

### Qızıl Qayda

```
┌────────────────────────────────────────────────┐
│ Əvvəl BACKUP, sonra təmir!                    │
│ git tag backup-$(date +%s)                    │
│ git branch backup-branch                      │
└────────────────────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### Qurtarma Əmrləri

```bash
# Reflog - Git-in "undo history"si
git reflog                         # Bütün HEAD dəyişiklikləri
git reflog show <branch>           # Xüsusi branch üçün
git reflog --all                   # Bütün ref-lər

# Bütövlük yoxlaması
git fsck                           # Repo yoxla
git fsck --full --strict           # Detallı
git fsck --lost-found              # Orphan obyektləri tap

# Cache/Index bərpa
git reset                          # Staged dəyişiklikləri geri al
git restore --staged <file>        # Faylı unstage et
git checkout -- <file>             # İşlək dəyişiklikləri at
git clean -fd                      # Untracked faylları sil

# Remote sinx
git fetch --all --prune
git remote prune origin
git pull --rebase                  # Rebase ilə pull

# Repo təmizləmə
git gc --aggressive --prune=now    # Garbage collection
git prune                          # Unreachable obyektləri sil
git repack -a -d                   # Packfile yenidən yarat
```

## Praktiki Nümunələr (Practical Examples)

### Problem 1: Yanlış Commit Mesajı

```bash
# Son commit mesajını düzəlt
git commit --amend -m "Düzgün mesaj"

# Əgər artıq push edilibsə
git commit --amend -m "Düzgün mesaj"
git push --force-with-lease        # Təhlükəsiz force push

# DİQQƏT: main/shared branch-da --amend ETMƏ
```

### Problem 2: Yanlış Commit-ə Fayl Daxil Etdim

```bash
# Son commit-ə fayl əlavə et (yeni commit yaratmadan)
git add forgotten-file.php
git commit --amend --no-edit

# Əgər yanlış fayl əlavə etmisənsə
git reset HEAD~1                   # Commit-i geri al, dəyişiklikləri saxla
# Düzgün faylları seç
git add correct-files
git commit -m "..."
```

### Problem 3: Silinmiş Branch-ı Bərpa Et

```bash
# Branch təsadüfən silindi!
git branch -D feature/important    # "deleted branch feature/important (was abc123)"

# Reflog-dan tap
git reflog
# abc123 HEAD@{5}: checkout: moving from feature/important to main

# Yenidən yarat
git checkout -b feature/important abc123

# Və ya reflog show ilə
git reflog show feature/important  # Əgər hələ cache-də varsa

# Əgər reflog-da yoxdursa, fsck ilə
git fsck --lost-found
# Dangling commits: abc123, def456
git show abc123                    # Yoxla
git checkout -b recovered abc123
```

### Problem 4: Hard Reset-dən Sonra Qurtarma

```bash
# Qorxu: git reset --hard HEAD~5
# 5 commit itdi!

# Reflog ilə bərpa
git reflog
# 7a8b9c HEAD@{0}: reset: moving to HEAD~5
# abc123 HEAD@{1}: commit: Last good commit  ← Bu!

git reset --hard HEAD@{1}
# Hər şey geri qayıtdı

# Alternativ
git reset --hard abc123
```

### Problem 5: Published Commit-i Geri Al

```bash
# Commit artıq push olunub, force push OLMAZ (main-də)
# Həll: revert commit yarat
git revert abc123
# Yeni commit: "Revert abc123"
git push

# Çoxlu commit geri al
git revert abc123..def456          # Range
git revert HEAD~3..HEAD            # Son 3 commit

# Merge commit geri al
git revert -m 1 abc123             # -m 1 = parent 1 saxla
```

### Problem 6: Konflikti Həll Et

```bash
# git merge/rebase zamanı konflikt
git status
# both modified: app/Models/User.php

# Faylı aç
<<<<<<< HEAD (cari branch)
    public string $email;
=======
    public string $emailAddress;
>>>>>>> feature/rename

# Düzəlt (birini seç və ya birləşdir)
    public string $email;

# İşarə sonra
git add app/Models/User.php
git rebase --continue              # və ya git merge --continue

# Rebase-i ləğv et
git rebase --abort

# Tool istifadə et
git mergetool
git config --global merge.tool vimdiff
```

### Problem 7: Böyük Fayllar və Git LFS

```bash
# Problem: 500MB video fayl commit olundu
git push
# fatal: file size exceeds GitHub's 100MB limit

# Həll 1: Son commit-dən çıxar
git reset HEAD~1
git rm --cached large-video.mp4
echo "large-video.mp4" >> .gitignore
git add .gitignore
git commit -m "fix: remove large file"

# Həll 2: Git LFS istifadə et
# Quraşdır
sudo apt install git-lfs           # Ubuntu
brew install git-lfs               # macOS
git lfs install

# Track
git lfs track "*.mp4"
git lfs track "storage/app/videos/*"
git add .gitattributes

# Indi LFS istifadə edir
git add large-video.mp4
git commit -m "feat: add intro video (LFS)"
git push

# Mövcud tarixçədən böyük fayl sil (git filter-repo)
pip install git-filter-repo
git filter-repo --path large-video.mp4 --invert-paths
git push --force
```

### Problem 8: "detached HEAD" Mesajı

```bash
# Tag və ya commit-ə checkout etdin
git checkout v1.0.0
# HEAD is now at abc123... You are in 'detached HEAD' state

# Dəyişiklik etdin, amma branch-da deyilsən!
# Həll 1: Yeni branch yarat
git checkout -b feature/from-v1.0

# Həll 2: Geri qayıt
git checkout main

# Əgər dəyişiklik etdin və itirdinsə
git reflog                         # Commit-i tap
git checkout -b recovered abc123
```

### Problem 9: Korlanmış Repo

```bash
# Xəta: "error: object file is empty"
# və ya "fatal: bad object"

# 1. Fsck ilə yoxla
git fsck --full
# missing blob abc123
# missing tree def456

# 2. Boş fayları tap və sil
find .git/objects -size 0 -delete

# 3. Yenidən fetch et
git fetch origin

# 4. Hələ problem varsa, yenidən clone et
cd ..
mv my-repo my-repo-backup
git clone <url> my-repo
cd my-repo
# Local dəyişiklikləri köçür
cp ../my-repo-backup/app/Models/NewModel.php app/Models/

# 5. Ekstrim: bundle yarat və bərpa et
git bundle create backup.bundle --all
git clone backup.bundle recovered-repo
```

### Problem 10: "Your branch is ahead by X commits"

```bash
# git status
# Your branch is ahead of 'origin/main' by 3 commits

# Bu o deməkdir ki 3 commit push etməmisən
git log origin/main..HEAD          # Push edilməyən commit-lər

# Push et
git push origin main

# Əgər push EDILMƏSİN istəyirsənsə (diverged)
git reset --hard origin/main       # Remote-a sinx, local dəyişikliklər itir
# DİQQƏT: local commit-lər silinir!
```

### Problem 11: Merge Artefaktları

```bash
# Conflict markerləri qalıb faylda
grep -r "<<<<<<<" .
grep -r ">>>>>>>" .

# Təmizlə
# Əl ilə düzəlt və ya
git reset --merge                  # Merge-i ləğv et

# Və ya checkout-la əvvəlki versiyanı al
git checkout --theirs app/User.php  # Remote versiyası
git checkout --ours app/User.php    # Local versiyası
```

### Problem 12: Git Slow

```bash
# Git əmrləri yavaşdır

# 1. Garbage collection
git gc --aggressive --prune=now

# 2. Packfile yenidən yarat
git repack -a -d --depth=250 --window=250

# 3. Status-u sürətləndir (fsmonitor)
git config core.fsmonitor true
git config core.untrackedCache true

# 4. Böyük repo üçün partial clone
git clone --filter=blob:none <url>

# 5. Shallow clone (tam history lazım deyilsə)
git clone --depth 1 <url>

# 6. Fetch-i optimallaşdır
git config fetch.writeCommitGraph true

# 7. Kommit grafı
git commit-graph write --reachable
```

### Problem 13: Remote Authentication Problem

```bash
# Xəta: "Permission denied (publickey)"

# SSH yoxla
ssh -T git@github.com
# Hi username! You've successfully authenticated...

# SSH key yarat
ssh-keygen -t ed25519 -C "email@example.com"
cat ~/.ssh/id_ed25519.pub          # GitHub-a əlavə et

# HTTPS ilə istifadə edirsənsə
# GitHub Personal Access Token (PAT) lazımdır
git remote set-url origin https://USERNAME:PAT@github.com/user/repo.git

# Credential cache
git config --global credential.helper cache
git config --global credential.helper store
```

### Problem 14: "refusing to merge unrelated histories"

```bash
# İki ayrı repo-nu birləşdirəndə
git pull origin main
# fatal: refusing to merge unrelated histories

# Həll
git pull origin main --allow-unrelated-histories

# Sonra konflikti həll et
```

### Problem 15: .gitignore İşləmir

```bash
# .gitignore-a əlavə etdim amma hələ izlənir
# Səbəb: fayl artıq tracked-dir

git rm --cached file.log           # Cache-dən çıxar
git rm --cached -r vendor/         # Qovluq
git commit -m "chore: apply .gitignore"

# Və ya
git rm -r --cached .               # Hər şeyi
git add .
git commit -m "chore: refresh .gitignore"
```

## Vizual İzah (Visual Explanation)

### Reflog ilə Qurtarma

```
ZAMAN AXINI:
                                       │
                                       │ git reset --hard HEAD~3
                                       v
Əvvəl:  A ── B ── C ── D ── E ── F (HEAD)
                                    
Sonra:  A ── B ── C (HEAD)
                   │
                   │ D, E, F "itdi"?
                   │
                   v
        reflog hələ bilir:
        HEAD@{0}: reset to HEAD~3
        HEAD@{1}: commit F    ← Bərpa nöqtəsi!
        HEAD@{2}: commit E
        HEAD@{3}: commit D

Bərpa: git reset --hard HEAD@{1}
       → A ── B ── C ── D ── E ── F
```

### Silinmiş Branch-ı Qurtarma

```
Əvvəl:
main:       A ── B
                 │
feature:          ── C ── D ── E

git branch -D feature  (E silindi!)

Sonra:
main:       A ── B
                  
     C, D, E - orphan obyektlər

Qurtarma:
1. git reflog → E-nin SHA-sını tap
2. git checkout -b feature <E-sha>
3. main:   A ── B
                │
   feature:     ── C ── D ── E (bərpa olundu!)
```

### Konflikt Həlli

```
Fayl: app/User.php

Əvvəl merge:
main branch:        feature branch:
┌──────────────┐    ┌──────────────┐
│ $email =     │    │ $email =     │
│   "old";     │    │   "new";     │
└──────────────┘    └──────────────┘

Merge zamanı:
┌─────────────────────────┐
│ <<<<<<< HEAD            │
│ $email = "old";         │
│ =======                 │
│ $email = "new";         │
│ >>>>>>> feature         │
└─────────────────────────┘

Həll:
┌─────────────────────────┐
│ $email = "new";         │ ← bir versiya seç
└─────────────────────────┘

git add User.php
git commit
```

## PHP/Laravel Layihələrdə İstifadə

### 1. Laravel .env Təsadüfən Commit Olundu

```bash
# Problem: .env secrets ilə commit olundu!
git log --all --oneline -- .env

# Həll 1: Son commit-dən çıxar (hələ push olunmayıb)
git rm --cached .env
git commit --amend --no-edit

# Həll 2: git filter-repo ilə bütün tarixdən sil
pip install git-filter-repo
git filter-repo --path .env --invert-paths

# Secrets rotasiya et (mütləq!)
# - DB password dəyiş
# - API keys yenilə
# - AWS credentials yeniləndir

# Force push
git push --force --all
git push --force --tags

# Komandaya xəbər ver: hamı re-clone etməlidir
```

### 2. Laravel storage/ Fayllarını Sil

```bash
# storage/logs/*.log commit olundu
git rm -r --cached storage/logs/
git rm -r --cached storage/app/public/

# .gitignore yoxla
cat .gitignore | grep storage

# Düzgün Laravel .gitignore:
# storage/*.key
# storage/app/*
# !storage/app/public
# storage/app/public/*
# !storage/app/public/.gitkeep
# storage/framework/cache/*
# storage/framework/sessions/*
# storage/framework/views/*
# storage/logs/*.log
# !storage/logs/.gitkeep

git add .gitignore
git commit -m "chore: fix .gitignore for storage"
```

### 3. vendor/ Commit Olundu

```bash
# 50MB+ vendor qovluğu commit olundu
git rm -r --cached vendor/
echo "vendor/" >> .gitignore
git add .gitignore
git commit -m "chore: remove vendor from git"

# Tarixdən tamamilə sil
git filter-repo --path vendor --invert-paths
git push --force

# Nəticə: repo ölçüsü 200MB → 20MB
```

### 4. Laravel Migration Konflikti

```bash
# İki developer eyni vaxtda migration yazdı
database/migrations/
├── 2024_01_01_000000_create_users_table.php
├── 2024_01_15_120000_add_avatar_to_users.php  ← Dev A
├── 2024_01_15_130000_add_bio_to_users.php     ← Dev B

# Problem: eyni cədvələ iki migration
# Həll: chronological order düzəlt

# Dev B öz migration-ını rename et
mv 2024_01_15_130000_add_bio_to_users.php \
   2024_01_15_140000_add_bio_to_users.php

# Və ya bir migration-ı digərinə əlavə et
# Sonra rollback/re-run
php artisan migrate:rollback --step=2
php artisan migrate
```

### 5. Composer.lock Konflikti

```bash
# composer.lock merge konflikti
git merge develop
# CONFLICT (content): composer.lock

# Həll: lock-u yenidən yarat
git checkout --theirs composer.lock
# və ya
rm composer.lock
composer install
git add composer.lock
git commit -m "fix: resolve composer.lock conflict"
```

### 6. Laravel Cache Problemləri

```bash
# Deploy sonra "class not found"
# Cache əski

# Təmizlə
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload

# Git-dən sonra
git pull
composer install
npm install
php artisan migrate
php artisan optimize:clear
```

### 7. Laravel CI/CD-də Git Problem

```yaml
# .github/workflows/deploy.yml
# Xəta: "shallow update not allowed"

# Həll: fetch-depth: 0
- uses: actions/checkout@v4
  with:
    fetch-depth: 0   # Tam tarixçə

# Submodule varsa
- uses: actions/checkout@v4
  with:
    submodules: recursive
    fetch-depth: 0
```

## Interview Sualları

### Q1: Silinmiş commit-i necə bərpa edirsən?
**Cavab:** `git reflog` ilə. Reflog HEAD-in hər dəyişikliyinin loqudur (90 gün saxlanılır). Silinmiş commit-in SHA-sını tapıb bərpa edirsən:
```bash
git reflog
# abc123 HEAD@{5}: commit: Important work
git checkout -b recovered abc123
```

### Q2: Yanlışlıqla push etdim, indi nə edim?
**Cavab:** Bu main/shared branch-dır yoxsa yox?
- **Feature branch (öz branch-ın)**: `git push --force-with-lease` işlədə bilərsən
- **Main/shared**: Force push ETMƏ! `git revert` ilə yeni commit yarat:
```bash
git revert abc123
git push
```

### Q3: git reset --hard --nin qarşısında qorxmalıyıq mı?
**Cavab:** Xeyr, reflog səni qoruyur! 90 gün ərzində hər şeyi geri qaytarmaq olar:
```bash
git reset --hard HEAD~5     # Guya 5 commit itdi
git reflog                  # Onların SHA-larını tap
git reset --hard HEAD@{1}   # Bərpa
```
Amma untracked fayllar (yeni, amma commit olunmayan) itə bilər - onları backup et!

### Q4: Git LFS nə üçün istifadə olunur?
**Cavab:** Git LFS (Large File Storage) böyük binary faylları (video, PDF, PSD) ayrıca serverdə saxlayır. Repo-da yalnız kiçik pointer fayl qalır. İstifadə halları:
- 100MB+ fayllar
- Binary assets (video, images)
- Design faylları (Figma, PSD)
- Dataset-lər
```bash
git lfs install
git lfs track "*.mp4"
git add .gitattributes
```

### Q5: Konfliktləri necə həll edirsən?
**Cavab:**
1. `git status` ilə konfliktli faylları tap
2. Faylı aç, `<<<<<<<`, `=======`, `>>>>>>>` markerlərini gör
3. Düzgün versiyanı seç (birini və ya birləşdir)
4. Markerləri sil
5. `git add <file>` ilə mark as resolved
6. `git rebase --continue` və ya `git merge --continue`

Tool-lar: `git mergetool`, VS Code, IntelliJ.

### Q6: Korlanmış repo-nu necə bərpa edirsən?
**Cavab:**
```bash
# 1. Yoxla
git fsck --full

# 2. Boş obyekt fayllarını sil
find .git/objects -size 0 -delete

# 3. Remote-dan missing obyektləri çək
git fetch origin

# 4. Hələ qırılıbsa, yenidən clone et
cd ..
git clone <url> fresh-repo
# Local dəyişiklikləri köçür
```

### Q7: "detached HEAD" nədir və necə düzəlir?
**Cavab:** Branch-da deyil, commit-də dayandığın vəziyyətdir. `git checkout <commit>` və ya `git checkout <tag>` ilə olur. Təhlükə: bu vəziyyətdə commit etsən, ref yoxdursa GC-də itir!

Həll:
```bash
# Yeni branch yarat
git checkout -b feature/from-commit

# Və ya main-ə qayıt
git checkout main
```

### Q8: Böyük fayl tarixdən necə silinir?
**Cavab:** `git filter-repo` (modern, tövsiyə olunur):
```bash
pip install git-filter-repo
git filter-repo --path large-file.zip --invert-paths
git push --force --all
git push --force --tags
```

Köhnə: `git filter-branch` (yavaş, depreacted).

Qeyd: Komanda hamı yenidən clone etməlidir, çünki tarix dəyişdi!

### Q9: `git push --force-with-lease` nə üçün daha təhlükəsizdir?
**Cavab:**
- `--force` - körköründən push edir, başqasının işini üstünə yazır
- `--force-with-lease` - remote son fetch-dən sonra dəyişməyibsə push edir

Əgər başqası yeni commit push edibsə, `--force-with-lease` uğursuz olur və səni xəbərdar edir.

### Q10: .gitignore əlavə etdim, amma fayl hələ trackl-anır. Niyə?
**Cavab:** `.gitignore` yalnız **untracked** fayllara tətbiq olunur. Əgər fayl artıq tracked-dir (əvvəlcədən commit olunub), `.gitignore` onu atlamır. Həll:
```bash
git rm --cached file.log
git commit -m "chore: stop tracking file.log"
# Artıq .gitignore qarşı çıxmağa başlayır
```

## Best Practices

### 1. Həmişə Backup Edin
```bash
# Təhlükəli əməliyyatdan əvvəl
git tag backup-$(date +%Y%m%d-%H%M%S)
git branch backup-before-rebase
```

### 2. force-with-lease İstifadə Edin
```bash
# Təhlükəli
git push --force

# Təhlükəsiz
git push --force-with-lease
```

### 3. Reflog-u Geniş Saxlayın
```bash
git config --global gc.reflogExpire "1 year"
git config --global gc.reflogExpireUnreachable "3 months"
```

### 4. Müntəzəm GC İşlədin
```bash
# Həftədə 1 dəfə
git gc --auto
```

### 5. Kritik Addımda git status Yoxlayın
```bash
# Hər rebase/merge-dən əvvəl və sonra
git status
git log --oneline -5
```

### 6. İstifadəçi Avatarı və İmzası
```bash
# Rəsmi commit-lər üçün
git config --global commit.gpgsign true
git config --global user.signingkey <KEY>
```

### 7. Təhlükəli Əmrləri Alias-lamayın
```bash
# PIS
git config --global alias.nuke 'reset --hard'

# Yaxşı
git config --global alias.undo 'reset HEAD~1'
```

### 8. fsck Müntəzəm Yoxlayın
```bash
# Ayda 1 dəfə və ya şübhəli hallarda
git fsck --full --strict
```

### 9. Remote Backup
```bash
# Yalnız local repo-nuz varsa:
# Remote yaradın (hətta özəl)
git remote add backup git@github.com:me/backup.git
git push backup --all
git push backup --tags
```

### 10. Post-Mortem Yazın
```markdown
## Incident: Accidental force push to main

Date: 2026-04-17
Impact: 3 commits lost for 20 minutes

### What happened?
Developer ran `git push --force` on main instead of feature branch.

### How was it fixed?
Reflog recovery: `git reset --hard HEAD@{2}`

### How to prevent?
1. Branch protection: no force push
2. Team training on --force-with-lease
3. Alias `git push --force` to warn first
```
