# Git Internals

## Nədir? (What is it?)

Git daxilən **content-addressable filesystem** (məzmuna görə ünvanlanan fayl sistemi) kimi işləyir. Bu, Git-in fayllar haqqında məlumatları hash dəyərləri (SHA-1 və ya SHA-256) əsasında saxladığı deməkdir. Git-in daxili strukturunu anlamaq, mürəkkəb problemləri həll etməyə və Git-in necə işlədiyini dərindən başa düşməyə kömək edir.

```
Git-in iki səviyyəsi:
┌──────────────────────────────────────────┐
│ Porcelain Commands (İstifadəçi səviyyəsi)│
│ git add, git commit, git push, git pull  │
├──────────────────────────────────────────┤
│ Plumbing Commands (Aşağı səviyyə)        │
│ git hash-object, git cat-file,           │
│ git update-ref, git write-tree           │
└──────────────────────────────────────────┘
```

### Git-in 4 Obyekt Tipi

```
┌──────────────────────────────────────────────┐
│ 1. Blob   - Fayl məzmunu                     │
│ 2. Tree   - Qovluq strukturu                 │
│ 3. Commit - Snapshot + metadata              │
│ 4. Tag    - Annotated tag                    │
└──────────────────────────────────────────────┘
```

## Əsas Əmrlər (Key Commands)

### Plumbing Commands

```bash
# Obyekt yaratmaq və SHA-1 hesablamaq
git hash-object <file>
git hash-object -w <file>           # Obyekti .git/objects-ə yaz

# Obyektin məzmununu oxumaq
git cat-file -p <sha>                # Print (məzmunu göstər)
git cat-file -t <sha>                # Type (blob/tree/commit/tag)
git cat-file -s <sha>                # Size

# Tree yaratmaq
git write-tree                       # Index-dən tree yaradır
git read-tree <sha>                  # Tree-ni index-ə oxuyur

# Commit yaratmaq
git commit-tree <tree-sha> -m "msg"  # Tree-dən commit yaradır

# Ref-ləri idarə etmək
git update-ref refs/heads/main <sha>
git symbolic-ref HEAD refs/heads/main

# Garbage collection
git gc                               # Lazımsız obyektləri təmizlə
git gc --aggressive                  # Daha dərin optimizasiya

# Repo bütövlüyünü yoxla
git fsck                             # File system check
git fsck --full                      # Tam yoxlama

# Pack fayllarını araşdırmaq
git verify-pack -v .git/objects/pack/pack-*.idx
git show-ref                         # Bütün ref-ləri göstər
```

## Praktiki Nümunələr (Practical Examples)

### 1. Blob Obyekti Yaratmaq

```bash
# Test faylı yarat
echo "Hello Laravel" > test.txt

# Hash hesabla (yazmadan)
git hash-object test.txt
# 1c3fdd5... (SHA-1 hash)

# Hash hesabla və yaz
git hash-object -w test.txt
# 1c3fdd5...

# .git/objects strukturuna bax
ls .git/objects/1c/
# 3fdd5b5a9e5e2e3... (ilk 2 simvol qovluq, qalanı fayl adı)

# Məzmunu oxu
git cat-file -p 1c3fdd5
# Hello Laravel
```

### 2. Tree Obyekti Yaratmaq

```bash
# Fayl əlavə et və index-ə yerləşdir
git add test.txt

# Index-dən tree yarat
git write-tree
# 5b1d3b... (tree SHA)

# Tree məzmununu oxu
git cat-file -p 5b1d3b
# 100644 blob 1c3fdd5... test.txt

# Tree-nin formatı:
# <mode> <type> <hash>    <name>
# 100644 = normal fayl
# 100755 = executable fayl
# 040000 = qovluq (tree)
# 120000 = symlink
```

### 3. Commit Obyekti Yaratmaq

```bash
# Tree-dən commit yarat
git commit-tree 5b1d3b -m "Initial commit"
# abc123... (commit SHA)

# Commit məzmununu oxu
git cat-file -p abc123
# tree 5b1d3b...
# author Orkhan <email> 1713369600 +0400
# committer Orkhan <email> 1713369600 +0400
#
# Initial commit

# HEAD-i bu commit-ə yönəlt
git update-ref refs/heads/main abc123

# Yoxla
git log
```

### 4. .git Qovluq Strukturu

```bash
# Laravel layihəsində
cd my-laravel-project
tree -L 2 .git/

.git/
├── HEAD                    # Cari branch-a göstərici
├── config                  # Local konfiqurasiya
├── description             # Repo təsviri (GitWeb üçün)
├── hooks/                  # Git hooks
│   ├── pre-commit.sample
│   ├── commit-msg.sample
│   └── ...
├── index                   # Staging area (binary)
├── info/
│   └── exclude             # Local gitignore
├── logs/                   # Ref dəyişikliklərinin tarixçəsi
│   ├── HEAD
│   └── refs/
├── objects/                # Bütün obyektlər
│   ├── 1c/
│   │   └── 3fdd5b...       # Blob/tree/commit
│   ├── info/
│   └── pack/               # Packfiles
│       ├── pack-*.pack
│       └── pack-*.idx
└── refs/                   # Bütün ref-lər
    ├── heads/              # Local branch-lar
    │   ├── main
    │   └── develop
    ├── remotes/            # Remote branch-lar
    │   └── origin/
    └── tags/               # Tag-lər
        └── v1.0.0
```

### 5. Objekt Strukturu Araşdırması

```bash
# Son commit-i tap
git rev-parse HEAD
# abc123...

# Commit-i araşdır
git cat-file -p HEAD

# Çıxış:
# tree def456...
# parent 789abc...
# author Orkhan <email> 1713369600 +0400
# committer Orkhan <email> 1713369600 +0400
#
# Add user model

# Tree-ni araşdır
git cat-file -p def456

# 100644 blob 111... composer.json
# 100644 blob 222... .env.example
# 040000 tree 333... app
# 040000 tree 444... routes

# Alt tree-ni araşdır (app/)
git cat-file -p 333

# 040000 tree 555... Models
# 040000 tree 666... Http
# 100644 blob 777... Console/Kernel.php
```

### 6. SHA-256 ilə Git (Git 2.29+)

```bash
# SHA-256 ilə yeni repo yarat
git init --object-format=sha256

# Fərq:
# SHA-1:   40 simvol  (1c3fdd5b5a9e5e2e3f4d5e6f7a8b9c0d1e2f3a4b)
# SHA-256: 64 simvol  (1c3fdd5b5a9e5e2e3f4d5e6f...)

# Təhlükəsizlik: SHA-1 collision tapıldı (SHAttered 2017)
# SHA-256 hal-hazırda tövsiyə olunur, amma hələ standart deyil
```

### 7. Packfiles

```bash
# Packfile məlumatı
git count-objects -v
# count: 150              (loose obyektlər)
# size: 600
# in-pack: 10000          (packfile-dakı obyektlər)
# packs: 3
# size-pack: 50000
# prune-packable: 0
# garbage: 0

# GC işlət (packfile yarat)
git gc

# Packfile məzmununu oxu
git verify-pack -v .git/objects/pack/pack-abc123.idx | head
# abc123... commit 245 178 12
# def456... tree 180 140 190
# ...
```

## Vizual İzah (Visual Explanation)

### Git Obyekt Modeli

```
COMMIT                    TREE                   BLOB
┌──────────────┐         ┌──────────────┐      ┌─────────────┐
│ tree: abc    │ ──────> │ app/  → tree │ ──> │ User.php    │
│ parent: xyz  │         │ routes/→tree │      │ <?php...    │
│ author: ...  │         │ .env → blob  │      └─────────────┘
│ message: ... │         └──────────────┘
└──────────────┘                 │
        │                        └──> blob
        │                             blob
        v
    parent commit
```

### Content-Addressable Storage

```
Fayl məzmunu: "Hello Laravel\n"
         │
         v
    SHA-1 hash
         │
         v
    1c3fdd5b5a9e5e2e3f4d5e6f7a8b9c0d1e2f3a4b
         │
         v
.git/objects/1c/3fdd5b5a9e5e2e3f4d5e6f7a8b9c0d1e2f3a4b
    (ilk 2 simvol) / (qalan 38 simvol)
```

### Refs Sistemi

```
refs/
├── heads/                  ──> refs/heads/main → abc123 (commit)
│   ├── main                    (branch = commit-ə göstərici)
│   └── feature/auth
├── remotes/
│   └── origin/
│       ├── main
│       └── HEAD            ──> ref: refs/remotes/origin/main
└── tags/
    └── v1.0.0              ──> def456 (tag obyekti və ya commit)

HEAD ──> refs/heads/main    (cari branch-a symbolic ref)
```

### Commit History Grafı

```
C3 ──> C2 ──> C1 ──> (ilk commit, parent yoxdur)
 │      │      │
 v      v      v
T3     T2     T1    (hər commit öz tree-sini göstərir)
 │      │      │
 v      v      v
B1,B2  B1,B2  B1    (tree-lər blob-ları göstərir)

Qeyd: Dəyişməyən blob-lar təkrar saxlanılmır!
```

## PHP/Laravel Layihələrdə İstifadə

### 1. Laravel Obyektlərini Araşdırmaq

```bash
# Laravel layihəsinə daxil ol
cd my-laravel-app

# Son commit-in tree-sini tap
git cat-file -p HEAD

# tree abc123...
# parent def456...
# author Orkhan
# ...
# Add user authentication

# Tree-ni araşdır
git cat-file -p abc123 | head
# 100644 blob 111... .env.example
# 100644 blob 222... .gitignore
# 100644 blob 333... artisan
# 100644 blob 444... composer.json
# 040000 tree 555... app
# 040000 tree 666... bootstrap
# 040000 tree 777... config

# app/Models/User.php-i tap
git cat-file -p 555          # app tree
git cat-file -p <Models-sha> # Models tree
git cat-file -p <User-sha>   # User.php blob
```

### 2. Laravel-də Böyük Fayl Problemi

```bash
# storage/app/ böyük fayllar varsa
cd my-laravel-app
du -sh .git/
# 500MB !

# Böyük obyektləri tap
git rev-list --objects --all | \
  git cat-file --batch-check='%(objectsize) %(rest)' | \
  sort -n | tail -20

# 52428800 storage/app/backups/db.sql
# 104857600 public/videos/intro.mp4

# Həll: git filter-repo ilə sil
git filter-repo --path storage/app/backups/db.sql --invert-paths
```

### 3. .gitignore-dan Çıxarılmış Faylları Araşdırmaq

```bash
# .env faylı təsadüfən commit olunubsa
git log --all --full-history -- .env

# Bütün tarixdə .env blob-larını tap
git rev-list --all | while read commit; do
  git ls-tree -r $commit | grep "\.env$"
done

# Bu blob-u tamamilə silmək üçün
git filter-repo --path .env --invert-paths
```

### 4. Laravel Packfile Optimizasiyası

```bash
# CI/CD-də tez clone üçün
cd my-laravel-app

# GC işlət
git gc --aggressive --prune=now

# Nəticə:
# Əvvəl: 200MB .git/
# Sonra: 80MB .git/

# Dəfərli Laravel-lə:
git gc --auto                 # Avtomatik gərək olduqda
```

### 5. Reflog və Qurtarma

```bash
# Laravel-də yanlış rebase etdiniz
git reflog
# abc123 HEAD@{0}: rebase finished
# def456 HEAD@{1}: rebase start
# 789abc HEAD@{2}: commit: Add payment

# Əvvəlki vəziyyətə qayıt
git reset --hard HEAD@{2}

# Reflog faylını birbaşa oxu
cat .git/logs/HEAD
```

### 6. Laravel-də Custom Hook Obyekti

```bash
# Pre-commit-də PHPStan
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/sh
# Plumbing əmrlərindən istifadə edərək staged PHP fayllarını tap
git diff --cached --name-only --diff-filter=ACM | grep '\.php$' | \
while read file; do
  # Blob-u tap
  sha=$(git ls-files --stage "$file" | awk '{print $2}')
  # Blob-un məzmununu oxu və analiz et
  git cat-file -p "$sha" | ./vendor/bin/phpstan analyse --
done
EOF
chmod +x .git/hooks/pre-commit
```

## Interview Sualları

### Q1: Git obyekt tipləri hansılardır?
**Cavab:** Git-də 4 əsas obyekt tipi var:
1. **Blob** - fayl məzmunu (ad yoxdur, yalnız məzmun)
2. **Tree** - qovluq strukturu (fayl adları, icazələr, blob/tree göstəriciləri)
3. **Commit** - tree snapshot + metadata (author, parent, mesaj)
4. **Tag** - annotated tag (commit-ə göstərici + əlavə metadata)

### Q2: Git niyə SHA-1 istifadə edir?
**Cavab:** SHA-1 content-addressable storage üçün istifadə olunur. Hər bir obyektin unikal identifikatoru var. Bu, data bütövlüyünü təmin edir - əgər hash dəyişirsə, məzmun dəyişib. 2017-də SHA-1 collision tapıldı (SHAttered attack), ona görə də Git SHA-256 dəstəyi əlavə etdi (Git 2.29+).

### Q3: Plumbing və Porcelain əmrləri arasında fərq nədir?
**Cavab:**
- **Porcelain** - yüksək səviyyə, istifadəçi üçün nəzərdə tutulmuş (git add, git commit, git push)
- **Plumbing** - aşağı səviyyə, script/avtomatlaşdırma üçün (git hash-object, git cat-file, git update-ref)

### Q4: Packfile nədir?
**Cavab:** Packfile Git-in sıxışdırılmış obyekt saxlama formatıdır. Zaman keçdikcə loose obyektlər (hər biri ayrı fayl) packfile-lara yığılır. Oxşar obyektlər delta compression ilə yer qənaət edir. `git gc` avtomatik packfile yaradır.

### Q5: .git/HEAD faylında nə var?
**Cavab:**
- Adətən: `ref: refs/heads/main` (symbolic ref cari branch-a)
- Detached HEAD: birbaşa commit SHA (`abc123...`)
- HEAD cari working directory-nin hansı commit-də olduğunu göstərir

### Q6: Ref nədir və harada saxlanılır?
**Cavab:** Ref (reference) commit-ə göstəricidir. Saxlanılır:
- `.git/refs/heads/` - local branch-lar
- `.git/refs/remotes/` - remote branch-lar
- `.git/refs/tags/` - tag-lər
- `.git/packed-refs` - sıxışdırılmış ref-lər (performance üçün)
- `.git/HEAD` - cari branch

### Q7: Git eyni məzmunu iki dəfə saxlayırmı?
**Cavab:** Xeyr. Git content-addressable-dir. Əgər iki fayl eyni məzmuna malikdirsə, yalnız bir blob saxlanılır (hash eynidir). Bu Git-in yer qənaətini təmin edir.

### Q8: Commit-in parent-i neçə olabilir?
**Cavab:**
- **0 parent** - ilk commit (root)
- **1 parent** - adi commit
- **2+ parent** - merge commit (2 və ya daha çox branch birləşdirilib)

### Q9: `git gc` nə edir?
**Cavab:** Garbage collection:
1. Loose obyektləri packfile-a toplayır
2. Unreferenced (orphan) obyektləri silir (reflog-dan sonra)
3. Refs-ləri sıxışdırır (packed-refs)
4. Disk yerini optimallaşdırır

### Q10: Əgər `.git/objects/` qovluğundan bir fayl silsək nə olur?
**Cavab:** Repo korlanar. `git fsck` xətası verər: "missing blob/tree/commit". Həll yolları:
- `git fsck --lost-found` ilə qalıqları tap
- Remote-dan yenidən clone et
- Reflog-dan bərpa etməyə çalış
- Backup-dan restore et

## Best Practices

### 1. Müntəzəm GC İşlət
```bash
# Böyük repo-larda
git gc --auto              # Avtomatik, sürətli
git gc --aggressive        # Yavaş, amma daha yaxşı sıxışdırma (ayda 1 dəfə)
```

### 2. fsck ilə Bütövlüyü Yoxla
```bash
# CI/CD-də və ya şübhələndikdə
git fsck --full --strict
```

### 3. Reflog-u Yadda Saxla
```bash
# Reflog expire müddətini uzat (default 90 gün)
git config gc.reflogExpire "200 days"
git config gc.reflogExpireUnreachable "30 days"
```

### 4. Böyük Fayllar üçün LFS
```bash
# Binary/böyük fayllar üçün
git lfs install
git lfs track "*.psd"
git lfs track "storage/app/videos/*.mp4"
```

### 5. Shallow Clone ilə CI Sürətləndir
```bash
# CI/CD-də tam tarix lazım deyilsə
git clone --depth 1 https://github.com/user/repo.git
```

### 6. Partial Clone (Git 2.19+)
```bash
# Böyük monorepo-lar üçün
git clone --filter=blob:none <url>    # Blob-ları lazım olduqda yüklə
git clone --filter=tree:0 <url>       # Yalnız lazımi tree-lər
```

### 7. Obyekt Formatı Seç
```bash
# Yeni layihələr üçün
git init --object-format=sha256     # Daha təhlükəsiz (gələcək)
# Qeyd: hələ geniş dəstəklənmir
```

### 8. Plumbing-dən Script-lərdə İstifadə
```bash
# Avtomatlaşdırma üçün plumbing daha stabildir
# Yaxşı
git rev-parse HEAD
git for-each-ref --format='%(refname)'

# Pis (çıxış formatı dəyişə bilər)
git log | head -1 | awk '{print $2}'
```

### 9. .git/hooks-u Version Control Altına Al
```bash
# Custom hooks qovluğu yarat
mkdir .githooks
mv .git/hooks/pre-commit .githooks/
git config core.hooksPath .githooks
git add .githooks
```

### 10. Pack-refs Kullan
```bash
# Çoxlu branch/tag olan repo-larda
git pack-refs --all
# .git/packed-refs faylında toplanır
# git status və git branch daha sürətli olur
```
