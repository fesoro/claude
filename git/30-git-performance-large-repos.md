# Git Performance və Böyük Repozitoriyalar

## Nədir? (What is it?)

Böyük repozitoriyalar (Google, Microsoft, Facebook kimi nəhəng kod bazaları) Git-in default konfiqurasiyası ilə işləmir – `git clone` saatlarla davam edə, `git status` dəqiqələrlə ləngiyə bilər.

**Böyük repo problemi:**
- **Linux kernel:** ~3 milyon kommit, 5 GB tarix.
- **Microsoft Windows:** 300 GB-dan çox.
- **Google monorepo:** 80+ TB (Git deyil, Piper istifadə edir).
- **Chromium:** 30 GB+ tarix.

**Həll yolları:**
1. **Shallow clone** – yalnız son kommitləri yükləmək.
2. **Partial clone** – yalnız lazımi obyektləri yükləmək.
3. **Sparse checkout** – yalnız lazımi qovluqları işə salmaq.
4. **VFS for Git** (Microsoft) – virtual filesystem.
5. **Scalar** – Microsoft-un Git optimizasiya aləti.
6. **Commit-graph** – graph tarixini cache-ləmək.

---

## Əsas Əmrlər (Key Commands)

### Shallow clone
```bash
# Yalnız son kommit
git clone --depth=1 https://github.com/org/huge-repo.git

# Son 50 kommit
git clone --depth=50 https://github.com/org/huge-repo.git

# Yalnız müəyyən branch
git clone --depth=1 --single-branch --branch=main <url>

# Sonradan tam tarixə qaytarmaq
git fetch --unshallow
```

### Partial clone (Git 2.19+)
```bash
# Blob obyektlərini yükləmə, yalnız ehtiyacda
git clone --filter=blob:none <url>

# 1 KB-dan böyük blob-ları yükləmə
git clone --filter=blob:limit=1k <url>

# Yalnız tree obyektləri (ən az)
git clone --filter=tree:0 <url>
```

### Sparse checkout (cone mode)
```bash
# Repo klonla
git clone --filter=blob:none --no-checkout <url>
cd <repo>

# Cone mode aktivləşdir
git sparse-checkout init --cone

# Yalnız müəyyən qovluqları aktivləşdir
git sparse-checkout set apps/web packages/ui

# Checkout
git checkout main

# Siyahıya baxmaq
git sparse-checkout list

# Deaktivləşdirmək
git sparse-checkout disable
```

### Scalar (Microsoft)
```bash
# Scalar ilə klon (Git 2.38+ daxil)
scalar clone https://github.com/microsoft/huge-repo.git

# Avtomatik: partial clone, sparse, background maintenance
```

### Performance diaqnostikası
```bash
# Git status performans
GIT_TRACE=1 git status

# Repo ölçüsü
du -sh .git
git count-objects -vH

# Böyük fayllar
git rev-list --objects --all \
  | git cat-file --batch-check='%(objecttype) %(objectsize) %(rest)' \
  | awk '$1=="blob"' | sort -k2 -n -r | head -10
```

---

## Praktiki Nümunələr (Practical Examples)

### Nümunə 1: CI-də sürətli klon

Default klon (yavaş):
```yaml
- uses: actions/checkout@v4
  # Bütün tarixi yükləyir, bəlkə də 500 MB
```

Shallow klon (sürətli):
```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 1  # yalnız son kommit
```

Semantic-release üçün shallow işləmir, full tarix lazımdır:
```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 0  # tam tarix
```

Alternativ: blob-less clone (orta yol):
```bash
git clone --filter=blob:none --no-tags <url>
# 90% yer qənaəti, lazım olanda blob lazy load
```

### Nümunə 2: Monorepo-da sparse checkout

Çox böyük monorepo (məs. Nx/Turborepo):
```
huge-monorepo/
├── apps/
│   ├── web/          (Next.js - 2 GB)
│   ├── mobile/       (React Native - 3 GB)
│   ├── admin/        (Vue - 1.5 GB)
├── packages/
│   ├── ui/
│   ├── utils/
│   ├── api-client/
└── tools/
```

Frontend developer yalnız `apps/web` və `packages/ui` üçün:
```bash
# 1. Partial clone
git clone --filter=blob:none --sparse https://github.com/org/huge-monorepo.git
cd huge-monorepo

# 2. Sparse checkout (cone mode – daha sürətli)
git sparse-checkout init --cone

# 3. Lazımi qovluqları əlavə et
git sparse-checkout set apps/web packages/ui

# Nəticə: yalnız bu qovluqlar `git status` və `ls` göstərir
ls
# apps/ packages/ README.md package.json (yalnız lazımi hissələr)
```

Disk: 6.5 GB → 500 MB.

### Nümunə 3: Tarixi kəsmək

Köhnə tarixi silmək lazımdırsa:
```bash
# Son 1 ilin tarixini saxla
git clone --shallow-since="2025-04-01" <url>

# Və ya lokaldan:
git pull --shallow-since="2025-04-01"
```

### Nümunə 4: Commit-graph (Git 2.24+)

```bash
# Commit-graph faylı yarat (performance boost)
git commit-graph write --reachable --changed-paths

# Generalized Bloom filter (Git 2.27+)
git config core.commitGraph true
git config gc.writeCommitGraph true
```

Sonra:
```bash
# Əvvəl: 1.5s
# Sonra: 200ms
time git log --oneline
```

### Nümunə 5: Scalar ilə Windows repo klon

```bash
# Microsoft Windows repo (istifadəçi olmasan da)
scalar clone https://github.com/microsoft/vscode.git

# Scalar avtomatik:
# - partial clone (blob:none)
# - sparse checkout
# - background maintenance (hər saat)
# - commit-graph
# - prefetch
```

### Nümunə 6: Böyük fayl tapmaq və silmək

```bash
# Top 20 böyük obyekt
git rev-list --objects --all \
  | git cat-file --batch-check='%(objecttype) %(objectname) %(objectsize:disk) %(rest)' \
  | awk '/^blob/ {print $3" "$4}' \
  | sort -k1 -n -r \
  | head -20 \
  | numfmt --field=1 --to=iec-i --suffix=B --padding=7

# Nəticə:
# 150MiB assets/video.mp4
# 80MiB  node_modules/big-lib.tar
# 50MiB  docs/old-release.pdf
```

Silmək:
```bash
# git-filter-repo ilə (tövsiyə olunur)
git filter-repo --path assets/video.mp4 --invert-paths

# Force push
git push origin main --force-with-lease
```

---

## Vizual İzah (Visual Explanation)

### Clone növləri müqayisəsi

```
Full clone:
┌──────────────────────────────────────┐
│ Bütün kommitlər                      │
│ Bütün tree obyektləri                │
│ Bütün blob obyektləri (fayllar)      │
│ Bütün branches və tags               │
└──────────────────────────────────────┘
Ölçü: 100%

Shallow clone (depth=1):
┌──────────────────────────────────────┐
│ Yalnız son kommit                    │
│ Yalnız son state-in tree-ləri        │
│ Yalnız son state-in blob-ları        │
└──────────────────────────────────────┘
Ölçü: ~5-10%

Partial clone (blob:none):
┌──────────────────────────────────────┐
│ Bütün kommitlər                      │
│ Bütün tree obyektləri                │
│ Blob-lar YOX (lazım olanda yüklənir) │
│ Bütün branches və tags               │
└──────────────────────────────────────┘
Ölçü: ~10-15%

Sparse checkout:
┌──────────────────────────────────────┐
│ Full repo klonlanır (adətən partial) │
│ Amma working tree-də yalnız          │
│ seçilmiş qovluqlar görünür           │
└──────────────────────────────────────┘
Disk (working tree): 10-20%
```

### Sparse checkout nümunəsi

```
Repository structure:          Sparse checkout:
┌─ apps/                       ┌─ apps/
│  ├─ web/     (2 GB)          │  └─ web/       <-- yalnız bu
│  ├─ mobile/  (3 GB)          │
│  └─ admin/   (1.5 GB)        │
├─ packages/                   ├─ packages/
│  ├─ ui/                      │  └─ ui/         <-- və bu
│  ├─ utils/                   │
│  └─ api/                     │
└─ docs/                       └─ [ignored]

Total: 7 GB              Working tree: 2.5 GB
```

### Partial clone axını

```
  git clone --filter=blob:none
          │
          v
  ┌─────────────────┐
  │ Commits + trees │──> yüklənir
  └─────────────────┘
  ┌─────────────────┐
  │ Blobs (fayllar) │──> yüklənmir
  └─────────────────┘
          │
          │ git checkout main
          v
  ┌─────────────────┐
  │ Lazımi blob-ları│
  │ server-dən al   │──> lazy fetch (on-demand)
  └─────────────────┘
```

### Performance hierarchy

```
  Fastest ←────────────────────────────→ Slowest

  Sparse     Partial   Shallow   Full
  checkout   clone     clone     clone
     │          │         │        │
     v          v         v        v
   ~50MB    ~200MB     ~500MB   ~5GB
   ~5s      ~15s       ~30s    ~10min
```

---

## VFS for Git (Microsoft)

### Nədir?
**Virtual File System for Git** – Microsoft Windows komandasının 300+ GB repo-nu Git-də saxlamaq üçün yaratdığı həll.

### Necə işləyir?
- Working directory "virtual" – fayllar real sistemdə yoxdur.
- Fayla `open` çağrıldıqda Git arxa planda onu yükləyir.
- `git status` yalnız "dəyişdirilmiş" faylları göstərir (virtual fayllar toxunulmamış sayılır).

### Məhdudiyyətlər
- **Yalnız Windows**-da işləyir.
- GVFS protocol istifadə edir (Azure DevOps dəstəkləyir).
- GitHub tam dəstəkləmir.

### Scalar – VFS-in varisi
Microsoft VFS for Git-i tərk edib **Scalar**-a keçib:
- Cross-platform (Windows, macOS, Linux).
- Standart Git protokolu ilə işləyir.
- Git 2.38+ Scalar-ı daxili olaraq dəstəkləyir.

```bash
# Scalar install
git clone https://github.com/microsoft/scalar
# və ya Git ilə birlikdə gəlir

# İstifadə
scalar register ~/path/to/repo   # mövcud repo
scalar clone <url>               # yeni repo
scalar run maintenance           # manual maintenance
```

---

## Google Monorepo Strategiyası

Google-un kod bazası 80+ TB, Git istifadə etmir:
- **Piper** – Google-un öz VCS sistemi (closed source).
- **Mercurial** əsaslı (Piper-in public alternativi – Sapling Meta-dan).
- **Depot tools** – Chromium üçün.

### Əsas prinsiplər (kopyalana bilər):
1. **Virtualized filesystem**: Yalnız lazımi fayllar disk-də.
2. **Distributed builds** (Bazel): Heç vaxt bütün kodu build etmirsən.
3. **Lazy evaluation**: Commit graph-ın yalnız görünən hissəsi yüklənir.
4. **Code search index**: GitHub-dakı kimi deyil, xüsusi indexing.

### Digər nəhənglərin seçimləri
- **Meta (Facebook):** Sapling (Mercurial fork, açıq mənbə, Git-like CLI).
- **Twitter:** Monorepo, Bazel, Pants.
- **Uber:** Monorepo, Bazel.

---

## PHP/Laravel Layihələrdə İstifadə

### Tipik senariy: 5 GB-lıq Laravel enterprise app

Problem:
- 10 illik tarix (20000+ kommit).
- `/public/assets/` qovluğunda 2 GB köhnə asset.
- `/storage/` backup-ları accidentally commit edilib.
- `git clone` 15 dəqiqə çəkir.

Həll 1: Asset-ləri təmizlə
```bash
# Böyük faylları aşkar et
git rev-list --objects --all | git cat-file \
  --batch-check='%(objecttype) %(objectsize:disk) %(rest)' \
  | awk '/blob/ && $2 > 1000000' | sort -k2 -n -r

# Tarixdən çıxar
git filter-repo --path public/assets/old --invert-paths
git filter-repo --path storage/backups --invert-paths

# Force push (team ilə razılaşdırıldıqdan sonra)
git push --force-with-lease
```

Həll 2: CI-də shallow clone
```yaml
# .github/workflows/deploy.yml
- uses: actions/checkout@v4
  with:
    fetch-depth: 1  # 15 dəqiqə → 30 saniyə
```

Həll 3: Yeni developer onboarding
```bash
# docs/setup.md
# Böyük tarix sizə lazım deyil
git clone --depth=50 --single-branch git@github.com:company/laravel-app.git
cd laravel-app

# Ehtiyac olduqda tarix genişləndirin
git fetch --deepen=500
```

### Laravel monorepo (modules)

```
laravel-enterprise/
├── packages/           <-- modular packages
│   ├── auth/
│   ├── billing/
│   ├── reports/
│   └── admin-panel/
├── apps/
│   ├── api/           <-- Laravel main
│   ├── customer-portal/
│   └── admin-spa/
└── infrastructure/
```

Billing team üçün sparse checkout:
```bash
git clone --filter=blob:none --sparse git@github.com:co/mono.git
cd mono
git sparse-checkout init --cone
git sparse-checkout set packages/billing apps/api
```

### Assets üçün Git LFS
```bash
# 2 GB storage-da CMS şəkilləri
git lfs track "public/uploads/**/*"
git add .gitattributes
git commit -m "chore: move uploads to LFS"
```

### Composer vendor-i commit etməyin
`.gitignore`:
```
/vendor
/node_modules
/storage/*.sqlite
```

Bu əsas qayda – çox layihədə görülən səhv vendor-i commit edib repozitoriyanı 5x böyütməkdir.

---

## Interview Sualları (Q&A)

### Q1: Shallow clone və partial clone arasında fərq nədir?

**Cavab:**
- **Shallow clone:** Tarixi kəsir. `--depth=N` ilə yalnız son N kommit qalır. Tarixə girişin lazımdırsa problem yaradır (məs. `git blame`).
- **Partial clone:** Tarix qalır, amma blob obyektləri lazy yüklənir. Tarix görə bilərsən, amma köhnə fayl məzmunu yalnız lazım olduqda yüklənir.

Müasir Git-də partial clone daha yaxşı seçimdir.

### Q2: `git status` niyə yavaşdır və necə sürətləndirmək olar?

**Cavab:** Git hər fayl üçün `stat` syscall edir (inode, mtime). Böyük repo-da:
```bash
# Fsmonitor (Git 2.37+)
git config core.fsmonitor true

# Untracked cache
git config core.untrackedCache true

# Built-in fsmonitor (Git 2.36+)
git config core.useBuiltinFSMonitor true

# Preload index (SSD-də)
git config core.preloadIndex true
```
Linux-da 15s → 1.5s, Windows-da 30s → 2s.

### Q3: Monorepo-da yeni developer nə etməlidir?

**Cavab:** Partial + sparse:
```bash
git clone --filter=blob:none --sparse git@github.com:co/monorepo.git
cd monorepo
git sparse-checkout init --cone
git sparse-checkout set <my-team-folders>
```
Nəticə: 10 GB → 500 MB. Onboarding 2 saat → 5 dəqiqə.

### Q4: VFS for Git hələ də istifadə olunurmu?

**Cavab:** Xeyr, Microsoft VFS-i deprecate edib. Yerinə **Scalar** gəldi:
- Cross-platform.
- Standart Git protokolu (Azure DevOps əvəzinə GitHub da işləyir).
- Git 2.38+ built-in.

Scalar arxasında eyni texnologiyalar var (partial clone + sparse + background maintenance).

### Q5: `git fetch` niyə yavaşdır?

**Cavab:** Səbəblər:
1. **Çoxlu branches**: `git remote prune origin` köhnə remote branch-ləri silir.
2. **Tags**: `--no-tags` istifadə et.
3. **Refs çoxdur**: `git pack-refs --all` refs-i pack fayla yığır.

Konfiq:
```bash
git config remote.origin.fetch "+refs/heads/main:refs/remotes/origin/main"
# Yalnız main-i fetch et
```

### Q6: Git-in 4 GB fayl limiti varmı?

**Cavab:** Git özü məhdudiyyət qoymur, amma:
- Bəzi filesystem-lər (FAT32) 4 GB məhdudlaşdırır.
- GitHub 100 MB/fayl limiti qoyur (normal push üçün).
- LFS ilə 2 GB/fayl (GitHub Free).
- Packfile maksimum 4 GB (Git 2.20+ daha böyük ola bilər).

Praktikada 100 MB-dan böyük fayl → LFS istifadə et.

### Q7: `git gc` avtomatik işləyirmi?

**Cavab:** Bəli, Git müəyyən şərtlərdə `git gc --auto` işlədir:
- 6,700-dən çox loose obyekt var.
- 50-dən çox packfile var.

Manual optimization:
```bash
git gc --aggressive --prune=now  # tam optimizasiya
git maintenance start             # background maintenance (Git 2.31+)
```

### Q8: Sparse checkout "cone mode" nədir?

**Cavab:** Cone mode yalnız tam qovluqlar (subtree) seçməyə imkan verir, fayl pattern-ləri yox. Bu məhdudiyyət performance artırır:
- **Non-cone:** `apps/web/src/**/*.ts` kimi pattern-lər.
- **Cone:** yalnız `apps/web` (bütün alt məzmunu ilə).

Cone mode 10x sürətli çünki Git pattern-i hər fayla qarşı yoxlamır.

### Q9: `git clone --reference` nə işə yarayır?

**Cavab:** Yerli başqa klondan obyektləri paylaşmaq:
```bash
git clone --reference ~/other-repo-clone git@github.com:co/repo.git
```
Yeni klon disk-də yer tutmur (hardlink), amma əlaqəli olduğundan reference klonu silmək təhlükəlidir. CI runner-lərdə istifadə olunur (shared cache).

### Q10: Google niyə Git istifadə etmir?

**Cavab:** Google-un kod bazası 80+ TB. Git:
- Bu ölçüdə `git status` saatlarla çəkər.
- Network transfer imkansız olar.
- Distributed model Google-un tələblərinə uyğun gəlmir (onlar central source of truth istəyirlər).

Google **Piper** istifadə edir – centralized, cloud-based, yalnız lazımi fayllar yüklənir (CitC – Clients in the Cloud). Git bu ölçüdə fundamental uyğun deyil.

---

## Best Practices

1. **CI-də həmişə shallow clone istifadə edin** (`fetch-depth: 1`), tam tarix lazım olmayan hallarda.

2. **Semantic-release və `git blame` üçün `fetch-depth: 0` lazımdır** – bu halları nəzərə alın.

3. **Monorepo üçün sparse checkout konfiqurasiya edin**: Onboarding docs-da göstərin.

4. **Böyük fayllar Git-ə düşməsin**: pre-commit hook ilə 10 MB-dan böyük faylları bloklayın.

5. **`git maintenance` aktivləşdirin** (Git 2.31+):
   ```bash
   git maintenance start
   ```

6. **Commit-graph yazın**:
   ```bash
   git config gc.writeCommitGraph true
   git commit-graph write --reachable --changed-paths
   ```

7. **`core.fsmonitor` aktivləşdirin** (Git 2.37+): File system event monitoring ilə `git status` dramatik sürətlənir.

8. **`git lfs` adopt edin**: Binary fayllar Git tarixinə daxil olmamalıdır.

9. **Tarixi təmiz saxlayın**: Accidentally commit olunmuş böyük fayllar dərhal `git filter-repo` ilə silinməlidir (tarix dəyişir, team koordinasiya lazımdır).

10. **Scalar istifadə edin** çox böyük repolar üçün (Microsoft kimi): built-in Git 2.38+ versiyasında.

11. **Packfiles-i regular olaraq optimize edin**: `git gc` ya manual, ya da auto.

12. **Partial clone + sparse checkout = ideal kombinasiya**:
    ```bash
    git clone --filter=blob:none --sparse <url>
    ```

13. **Fetch zamanı refspec məhdudlaşdırın**: Yalnız lazımi branch-ləri fetch edin.

14. **`git config protocol.version 2`** (Git 2.18+): Yeni protokol daha səmərəlidir.

15. **Ayrı alət seçin lazım olsa**: 100 GB+ olduqda DVC, Perforce, və ya cloud-based VCS düşünün.
