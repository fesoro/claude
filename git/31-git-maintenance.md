# Git Maintenance (Lead)

## İcmal

**Git Maintenance** — Git repository-nin sağlam, sürətli və kompakt qalması üçün
icra olunan əməliyyatlar toplusudur. Zaman keçdikcə repository-də aşağıdakı
problemlər yığılır:

## Niyə Vacibdir

Repository zamanla pack file-larla şişir, dangling object-lər yığılır, commit-graph köhnəlir. `git gc` və `git maintenance` bu problemləri aradan qaldırır; CI agent-ləri və developer maşınlarında git əməliyyatlarını sürətləndirir.

- **Loose objects** — hər commit/blob ayrı fayl kimi `.git/objects/` altında saxlanılır
- **Unreachable objects** — heç bir ref-ə bağlı olmayan "asılı" obyektlər (deleted branches, reset-dən qalan commit-lər)
- **Böyüyən reflog** — hər HEAD hərəkəti reflog-da qalır
- **Parçalanmış pack fayllar** — çoxlu kiçik packfile-lar axtarışı yavaşladır
- **Köhnə refs** — `packed-refs` yenilənməyəndə `refs/` qovluğu dolur

Maintenance bu problemləri həll edir: **garbage collection**, **repacking**,
**pruning**, **integrity check**, və **commit-graph** yaradılması.

### Niyə vacibdir?

```
Maintenance olunmayan repo (6 ay sonra):
├── .git/objects/ → 50,000+ loose fayl → `git status` yavaş
├── .git/packed-refs yoxdur → `git branch` yavaş
├── 5 GB disk yeri → əslində 500 MB lazımdır
└── `git log --graph` → 30 saniyə

Maintenance olunan repo:
├── .git/objects/pack/ → 1 böyük packfile
├── .git/objects/info/commit-graph → O(1) traversal
├── 500 MB disk yeri
└── `git log --graph` → 0.5 saniyə
```

---

## Əsas Əmrlər (Key Commands)

### 1. `git gc` — Garbage Collection

Loose object-ləri pack-layır, unreachable obyektləri silir, reflog-u expire edir.

```bash
# Default maintenance (safe, recommended)
git gc

# Aqressiv optimallaşdırma (uzun çəkir, daha kiçik packfile)
git gc --aggressive

# Yalnız təcili təmizlik (heç bir prune etmir)
git gc --auto

# Zorla pruning (2 həftədən köhnə unreachable obyektləri sil)
git gc --prune=now

# Dərhal bütün unreachable obyektləri sil (TƏHLÜKƏLİ)
git gc --prune=now --aggressive
```

### 2. `git repack` — Packfile-ları yenidən qur

```bash
# Bütün loose object-ləri bir pack-ə yığ
git repack -a -d

# -a: bütün obyektlər, -d: köhnə pack-ları sil
# -f: force (delta-ları yenidən hesabla)
git repack -a -d -f --depth=250 --window=250

# Geometric repacking (Git 2.33+) — yalnız kiçik pack-ları birləşdirir
git repack --geometric=2 -d
```

### 3. `git prune` — Unreachable obyektləri sil

```bash
# Unreachable obyektləri sil (default: 2 həftədən köhnə)
git prune

# Dərhal sil (expire olmuş + yeni)
git prune --expire=now

# Dry-run — nə silinəcəyini göstər
git prune --dry-run --verbose
```

> **Diqqət**: Adətən `git prune`-u birbaşa çağırmırsan — `git gc` onu avtomatik
> çağırır. Əgər bir obyekti silmək istəsən, birbaşa `git gc --prune=now` işlət.

### 4. `git fsck` — File System Consistency Check

Repository-nin bütövlüyünü yoxlayır: corrupt obyekt, dangling reference, itən
obyekt.

```bash
# Əsas yoxlama
git fsck

# Bütün detalları göstər (dangling, unreachable)
git fsck --full --unreachable --dangling

# Reflog-u da yoxla
git fsck --reflogs

# Strict mode (sərt yoxlama)
git fsck --strict
```

**Nümunə output**:
```
Checking object directories: 100% (256/256), done.
dangling commit a1b2c3d4e5f6...
missing blob f0e1d2c3b4a5...
```

### 5. Reflog Expiration

```bash
# Reflog-u əl ilə təmizlə
git reflog expire --expire=90.days.ago --all
git reflog expire --expire-unreachable=30.days.ago --all

# Bütün reflog-u sil (TƏHLÜKƏLİ)
git reflog expire --expire=now --all

# Default expiration (config-dən)
git config --get gc.reflogExpire          # 90 days
git config --get gc.reflogExpireUnreachable # 30 days
```

### 6. `git maintenance` — Modern Maintenance (Git 2.29+)

```bash
# Background maintenance işə sal (systemd/launchd timer)
git maintenance start

# Əl ilə bütün taskları icra et
git maintenance run

# Yalnız bir task
git maintenance run --task=gc
git maintenance run --task=commit-graph
git maintenance run --task=prefetch
git maintenance run --task=loose-objects
git maintenance run --task=incremental-repack
git maintenance run --task=pack-refs

# Maintenance dayandır
git maintenance stop

# Siyahı
git maintenance register   # repository-ni qlobal maintenance-ə əlavə et
git maintenance unregister # çıxar
```

### 7. `git pack-refs` — Refs-ləri pack et

```bash
# Bütün refs-ləri packed-refs faylına yığ
git pack-refs --all

# Həmçinin artıq pack olmuş refs-ləri də daxil et
git pack-refs --all --prune
```

### 8. Commit-Graph — Performance üçün

```bash
# Commit-graph faylı yarat (traversal O(n) → O(1))
git commit-graph write --reachable --changed-paths

# Yoxla
git commit-graph verify

# Auto-write konfiqurasiyası
git config --global core.commitGraph true
git config --global gc.writeCommitGraph true
git config --global fetch.writeCommitGraph true
```

---

## Praktiki Nümunələr

### Nümunə 1: Böyük Laravel monorepo-nu optimallaşdır

```bash
cd ~/projects/laravel-monorepo

# 1. Vəziyyəti ölç
du -sh .git/
# 4.2 GB

git count-objects -v
# count: 45892          (loose objects)
# size: 892340
# in-pack: 125000
# size-pack: 3400000
# prune-packable: 0
# garbage: 0

# 2. Pack refs
git pack-refs --all --prune

# 3. Aggressive GC
git gc --aggressive --prune=now

# 4. Commit-graph yarat
git commit-graph write --reachable --changed-paths

# 5. Nəticə
du -sh .git/
# 1.1 GB ✅

git count-objects -v
# count: 0
# in-pack: 175000
# size-pack: 1100000
```

### Nümunə 2: Korrupt repository-ni bərpa et

```bash
# Simptom: `git pull` error verir
# "error: object file .git/objects/ab/c123... is empty"

# 1. Bütövlüyü yoxla
git fsck --full
# fatal: loose object abc123... (stored in .git/objects/ab/c123) is corrupt

# 2. Reflog-dan itən obyekti tap
git reflog

# 3. Unreachable obyektlər arasında axtar
git fsck --lost-found
# ls .git/lost-found/commit/

# 4. Remote-dan yenidən fetch et
git fetch origin --prune
git reset --hard origin/main

# 5. Corrupt obyekt faylını sil
rm .git/objects/ab/c123...

# 6. Yenidən fsck
git fsck --full
```

### Nümunə 3: Böyük faylı tarixçədən sil və temizlə

```bash
# 1. Böyük faylları tap
git rev-list --objects --all |
  git cat-file --batch-check='%(objecttype) %(objectname) %(objectsize) %(rest)' |
  awk '/^blob/ {print $3, $4}' |
  sort -rn |
  head -20

# 2. Faylı tarixçədən sil (git-filter-repo istifadə et)
git filter-repo --path storage/app/huge-dump.sql --invert-paths

# 3. Köhnə obyekti təmizlə
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Nəticə: disk yeri azaldı, amma diqqət —
# bütün team force push-dan sonra yenidən clone etməlidir!
```

### Nümunə 4: Automated maintenance (cron)

```bash
# ~/bin/git-maintain.sh
#!/bin/bash
set -e

REPOS=(
  ~/projects/laravel-api
  ~/projects/laravel-admin
  ~/projects/microservice-users
)

for repo in "${REPOS[@]}"; do
  echo "Maintaining $repo..."
  cd "$repo"
  git maintenance run --task=gc
  git maintenance run --task=commit-graph
  git maintenance run --task=pack-refs
done

# Crontab
# 0 3 * * 0 /home/developer/bin/git-maintain.sh >> /var/log/git-maintain.log 2>&1
```

### Nümunə 5: Background maintenance qeydiyyatı

```bash
# Laravel layihəsi üçün background maintenance
cd ~/projects/my-laravel-app
git maintenance start

# Yoxla
systemctl --user list-timers git-maintenance*
# git-maintenance-hourly.timer
# git-maintenance-daily.timer
# git-maintenance-weekly.timer

# Təsdiqlə ~/.gitconfig
cat ~/.gitconfig
# [maintenance]
#     repo = /home/user/projects/my-laravel-app
#     strategy = incremental
```

---

## Vizual İzah (ASCII Diagrams)

### Objects lifecycle

```
  Working Dir          .git/objects/          .git/objects/pack/
  ───────────         ───────────────        ────────────────────
  new file ───add──→  blob (loose)  ──gc──→  packfile
                           │                       │
                           │  unreachable          │  delta compression
                           ↓                       ↓
                       prune (delete)         smaller disk usage
```

### Garbage Collection axını

```
┌──────────────────────────────────────────────────────┐
│                    git gc                             │
└────────────────────────┬─────────────────────────────┘
                         │
         ┌───────────────┼───────────────┐
         ↓               ↓               ↓
   ┌──────────┐   ┌────────────┐  ┌─────────────┐
   │ pack-refs│   │   repack   │  │    prune    │
   │ (refs →  │   │ (loose →   │  │ (unreachable│
   │  packed) │   │  pack)     │  │  → deleted) │
   └──────────┘   └────────────┘  └─────────────┘
         │               │               │
         └───────────────┼───────────────┘
                         ↓
              ┌────────────────────┐
              │ reflog expire      │
              │ commit-graph write │
              └────────────────────┘
```

### Reflog expiration timeline

```
  Now  ←──────── 30 days ────────→ ←──── 60 days ────→
   │                                │
   │   Reachable entries            │   Unreachable entries
   │   (gc.reflogExpire=90d)        │   (gc.reflogExpireUnreachable=30d)
   │                                │
   └──── keep ─────────────────────→│←──── delete ───
```

### Commit-graph effekti

```
Commit-graph YOXDUR:
  git log --graph → parent chain traversal
  500,000 commit × fs read = 30s
  
Commit-graph VAR:
  git log --graph → mmap commit-graph file
  500,000 commit × memory read = 0.3s  (100x speedup)
```

---

## Praktik Baxış

### 1. CI/CD pipeline-da periodic maintenance

```yaml
# .github/workflows/git-maintenance.yml
name: Git Maintenance
on:
  schedule:
    - cron: '0 2 * * 0'  # Hər bazar saat 02:00
  workflow_dispatch:

jobs:
  maintenance:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Run maintenance
        run: |
          git maintenance run --task=gc
          git maintenance run --task=commit-graph
          git maintenance run --task=pack-refs

      - name: Report size
        run: |
          echo "## Repo stats" >> $GITHUB_STEP_SUMMARY
          du -sh .git >> $GITHUB_STEP_SUMMARY
          git count-objects -v >> $GITHUB_STEP_SUMMARY
```

### 2. Laravel Artisan komandası ilə maintenance

```php
<?php
// app/Console/Commands/GitMaintain.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GitMaintain extends Command
{
    protected $signature = 'git:maintain {--aggressive}';
    protected $description = 'Run Git maintenance on project repository';

    public function handle(): int
    {
        $repoPath = base_path();
        $this->info("Maintaining repo at {$repoPath}");

        $commands = [
            ['git', 'pack-refs', '--all', '--prune'],
            ['git', 'gc', $this->option('aggressive') ? '--aggressive' : '--auto'],
            ['git', 'commit-graph', 'write', '--reachable', '--changed-paths'],
            ['git', 'fsck', '--full'],
        ];

        foreach ($commands as $cmd) {
            $this->line('→ ' . implode(' ', $cmd));
            $process = new Process($cmd, $repoPath);
            $process->setTimeout(600);
            $process->run(fn ($type, $buffer) => $this->line(trim($buffer)));

            if (!$process->isSuccessful()) {
                $this->error('Command failed: ' . implode(' ', $cmd));
                return Command::FAILURE;
            }
        }

        $this->info('Maintenance completed.');
        return Command::SUCCESS;
    }
}
```

**Schedule-da qeydiyyat** (`app/Console/Kernel.php`):
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('git:maintain')
             ->weekly()
             ->sundays()
             ->at('02:00')
             ->onOneServer();
}
```

### 3. Git hook: pre-receive-də fsck

```bash
#!/bin/bash
# .git/hooks/pre-receive (server-side)

while read oldrev newrev refname; do
  # Yeni obyektləri yoxla
  git rev-list $oldrev..$newrev --objects | \
    git cat-file --batch-check='%(objecttype) %(objectname)' | \
    while read type hash; do
      if ! git fsck --strict --no-dangling $hash > /dev/null 2>&1; then
        echo "REJECTED: Corrupt object $hash"
        exit 1
      fi
    done
done
```

### 4. Docker development environment-də maintenance

```dockerfile
# Dockerfile.dev
FROM php:8.3-fpm

# Git maintenance həftədə bir dəfə
RUN apt-get update && apt-get install -y git cron

COPY docker/maintain.sh /usr/local/bin/
COPY docker/maintain.cron /etc/cron.d/git-maintain

RUN chmod +x /usr/local/bin/maintain.sh && \
    chmod 0644 /etc/cron.d/git-maintain && \
    crontab /etc/cron.d/git-maintain
```

```bash
# docker/maintain.sh
#!/bin/bash
cd /var/www/html
git maintenance run --task=incremental-repack --task=commit-graph
```

---

## Praktik Tapşırıqlar

1. **Əl ilə GC çalışdır, ölçü müqayisə et**
   ```bash
   du -sh .git  # əvvəl
   git gc --aggressive --prune=now
   du -sh .git  # sonra
   git count-objects -vH
   ```

2. **Avtomatik maintenance cron qur**
   ```bash
   git maintenance start
   # Cron-u yoxla:
   git maintenance run --task=gc
   git maintenance run --task=commit-graph
   git maintenance run --task=prefetch
   ```

3. **reflog expire et**
   ```bash
   git reflog expire --expire=30.days --all
   git gc --prune=30.days
   ```

4. **Repo sağlamlığını yoxla**
   ```bash
   git fsck --unreachable
   git fsck --lost-found  # dangling object-lər .git/lost-found/-a
   ```

## Interview Sualları (Q&A)

### Q1: `git gc` nə edir? Nə vaxt avtomatik işləyir?
**Cavab**: `git gc` (garbage collection) aşağıdakıları edir:
1. Loose obyektləri packfile-a yığır (`git repack`)
2. Pack refs-ləri `.git/packed-refs`-ə yazır
3. Unreachable obyektləri silir (`git prune`)
4. Reflog-u expire edir

**Avtomatik tetikleyiciler**:
- `git commit` — 7,000+ loose object varsa
- `git merge`, `git pull`, `git rebase` — sonunda `gc --auto` çağırılır
- `gc.auto=6700` (default) — bu hədddən çox loose obyekt varsa

`gc.auto=0` ilə söndürmək olar.

### Q2: `--aggressive` flag-ı nə edir və nə vaxt istifadə etməli?
**Cavab**: `git gc --aggressive` delta-ları sıfırdan hesablayır (daha uzun
pəncərə və dərinlik: `--window=250 --depth=250`). Nəticədə packfile daha kiçik
olur, lakin proses 10-50x yavaşdır.

**Nə vaxt**:
- İlk dəfə böyük repo klon edəndən sonra
- Böyük fayl tarixçədən silindikdən sonra
- Hər bir neçə ayda bir, off-hours-da

**Nə vaxt YOX**:
- Hər dəfə — daimi performans qazancı yoxdur
- CI-də (vaxt itkisi)

### Q3: `git fsck` nə yoxlayır? Nə tapa bilər?
**Cavab**: `git fsck` (file system check) `.git/objects/` içindəki obyekt
integrity-sini yoxlayır:
- **Corrupt objects** — SHA-1 hash uyğunsuzluğu, pozuq zlib stream
- **Missing objects** — referens olunan, amma olmayan
- **Dangling objects** — heç bir ref-dən erişilməyən (normal — silinmiş branch-lardan qalıb)
- **Unreachable objects** — reflog-dan da çıxmış obyektlər

```bash
git fsck --full --unreachable --dangling --strict
```

### Q4: Commit-graph nədir və niyə lazımdır?
**Cavab**: Commit-graph — bütün commit-lərin metadata-sını (parent, tree, commit
time, generation number) binary formatda saxlayan fayl: `.git/objects/info/commit-graph`.

**Faydalar**:
- `git log --graph`, `git merge-base`, `git branch --contains` komandaları
  **10-100x** sürətli olur
- Böyük monorepo-larda vacibdir (1M+ commit)

```bash
git config core.commitGraph true
git config gc.writeCommitGraph true
git commit-graph write --reachable --changed-paths
```

### Q5: `git maintenance` və `git gc` arasında fərq?
**Cavab**:

| Xüsusiyyət | `git gc` | `git maintenance` |
|------------|----------|-------------------|
| Git versiyası | Bütün versiyalar | 2.29+ |
| Background run | Yox | Bəli (systemd/launchd) |
| Incremental | Məhdud | Bəli (`--geometric`) |
| Commit-graph | Optional | Built-in task |
| Task seçimi | Yox | `--task=gc|commit-graph|prefetch|...` |
| Registered repos | Yox | `git maintenance register` |

`git maintenance` daha modern və esnekdir — böyük monorepolar üçün tövsiyə olunur.

### Q6: Reflog nə qədər saxlanılır? Necə dəyişmək olar?
**Cavab**: Default:
- **Reachable** entries (reachable commit-lərə işarə edən): **90 gün**
- **Unreachable** entries (orphan commit-lərə): **30 gün**

```bash
# Konfigurasiya
git config gc.reflogExpire 180.days
git config gc.reflogExpireUnreachable 60.days

# Əl ilə expire
git reflog expire --expire=30.days.ago --all
```

Reflog `git gc` işləyəndə expire olunur. `git reflog delete` konkret entry-ni silə bilər.

### Q7: Unreachable obyektləri necə tapmaq və silmək olar?
**Cavab**:
```bash
# Tap
git fsck --unreachable --no-reflogs

# Birbaşa sil
git gc --prune=now

# Və ya iki addımla
git reflog expire --expire=now --all
git gc --prune=now
```

**Diqqət**: `--prune=now` geri alına bilməz. Əvvəlcə `--dry-run` ilə yoxla.

### Q8: Packfile nədir? `git repack` nə edir?
**Cavab**: **Packfile** — çoxlu obyekt delta-kompressiya ilə bir faylda saxlanır
(`.git/objects/pack/pack-*.pack`). Faydalar:
- Disk yerindən qənaət (delta compression)
- Fewer fs operations → daha sürətli read

**`git repack -a -d`**:
- `-a`: bütün obyektləri bir pack-a yığ
- `-d`: köhnə (indi lazımsız) pack fayllarını sil

**Geometric repacking** (Git 2.33+):
```bash
git repack --geometric=2 -d
```
Yalnız kiçik pack-ları birləşdirir (böyük pack-a toxunmur). Bu böyük repolarda
maintenance vaxtını dramatik azaldır.

### Q9: `.git` qovluğu çox böyükdür — necə azaldım?
**Cavab**: Addım-addım:

1. **Böyük obyektləri tap**:
```bash
git rev-list --objects --all | \
  git cat-file --batch-check='%(objecttype) %(objectname) %(objectsize) %(rest)' | \
  sort -k3 -n | tail -20
```

2. **Lazımsız faylları tarixçədən sil**:
```bash
# git-filter-repo (tövsiyə olunur)
git filter-repo --path huge-file.sql --invert-paths
```

3. **Aggressive GC**:
```bash
git reflog expire --expire=now --all
git gc --aggressive --prune=now
```

4. **Nəticə yoxla**:
```bash
du -sh .git/
git count-objects -vH
```

5. **Force push** və team-ə yeni clone məsləhət gör.

### Q10: `git prune` niyə birbaşa çağırılmır?
**Cavab**: `git prune` **təhlükəli**dir: reflog-u yoxlamadan unreachable
obyektləri silir. Eyni zamanda `git gc` olmadan repack etmədən silmə əmələ gəlsə,
disk fragmentasiyası olar.

**Doğru yol**:
```bash
git gc --prune=now  # Əvvəlcə reflog expire, sonra repack, sonra prune
```

`git prune` yalnız xüsusi recovery ssenarilərində istifadə olunur (məsələn,
cloned repository-də konkret obyekti silmək).

---

## Best Practices

### 1. Avtomatik maintenance qur
```bash
# Hər repo üçün bir dəfə
git maintenance start

# Və ya CI cron ilə həftəlik
0 2 * * 0 cd /repo && git maintenance run
```

### 2. Commit-graph-ı həmişə yandır
```bash
git config --global core.commitGraph true
git config --global gc.writeCommitGraph true
git config --global fetch.writeCommitGraph true
```

### 3. `--aggressive` GC-ni ölçülü istifadə et
- Hər maintenance-də YOX
- Böyük əməliyyatdan sonra (filter-repo, böyük fayl silmə) BƏLI
- Off-hours-da BƏLI

### 4. `git fsck`-ni həftədə bir işlət
Korrupt obyektləri erkən aşkar et. CI-də automate et:
```yaml
- run: git fsck --full
```

### 5. Force push sonrası team-ə xəbər ver
Tarixçə yenidən yazıldıqdan sonra hamı repository-ni yenidən clone etməlidir.
Əks halda köhnə obyektlər təkrar push olunar.

### 6. Böyük faylları Git LFS-ə keçir
Video, binary asset, SQL dump faylları → LFS. Bax: `27-git-lfs.md`.

### 7. `gc.auto` default-u dəyişmə
`gc.auto=6700` Git-in balans etdiyi dəyərdir. 0 etməklə background GC-ni bağlama
— əvəzində `git maintenance` istifadə et.

### 8. Shallow clone CI-də
CI-də `fetch-depth: 1` ilə maintenance-ə ehtiyac olmur:
```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 1
```

### 9. `packed-refs`-i unutma
1000+ branch/tag-li repo-da `git pack-refs --all --prune` ref operations-ı
sürətləndirir.

### 10. Monitoring qur
```bash
# Həftəlik hesabat
git count-objects -vH > /var/log/git-stats-$(date +%F).log
du -sh .git >> /var/log/git-stats-$(date +%F).log
```

### 11. Backup-dan əvvəl `fsck`
Repository-ni backup etmədən əvvəl bütövlüyü təsdiq et:
```bash
git fsck --full && tar czf backup.tar.gz .git
```

### 12. `gc.pruneExpire` ilə təhlükəsizliyi artır
```bash
# Default: 2.weeks.ago
git config gc.pruneExpire 4.weeks.ago
```
Bu, səhvlə silinən obyektləri daha uzun saxlayır.

---

## Xülasə

**Git Maintenance** = uzunmüddətli repository sağlamlığı. Böyük Laravel/PHP
monorepo-larda kritikdir:

- `git gc` — əsas maintenance (həftəlik)
- `git maintenance start` — modern background task (tövsiyə olunur)
- `git fsck` — həftəlik integrity yoxlama
- `git commit-graph write` — 100x log performance
- `git repack --geometric=2` — incremental optimallaşdırma

Automate et, monitor et, və team-i məlumatlandır.

## Əlaqəli Mövzular

- [30-git-performance-large-repos.md](30-git-performance-large-repos.md) — performans optimizasiyası
- [32-git-internals.md](32-git-internals.md) — daxili struktur
