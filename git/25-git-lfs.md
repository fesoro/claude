# Git LFS (Senior)

## İcmal

**Git LFS** – böyük binary fayllar (video, şəkil, PSD, ML modellər, arxiv faylları) üçün Git-ə əlavə olunan genişlənmədir. Git əvvəlcə yalnız mətn faylları üçün dizayn edilib; böyük binary fayllar repozitoriyanı həddindən artıq şişirdir və performansı aşağı salır.

**Problem:** 500 MB-lıq fayl Git-ə əlavə edildikdə:
- Hər `git clone` bu faylı yükləyir (ağır clone).
- Hər versiyası tam şəkildə saxlanılır (diff hesablana bilmir).
- Repozitoriya sürətlə qiqabaytlara çatır.

**Həll:** Git LFS fayl məzmununu Git repozitoriyasına deyil, ayrı LFS server-inə (GitHub LFS, GitLab LFS, AWS S3) yükləyir. Git repozitoriyasında yalnız **pointer file** (kiçik mətn faylı) saxlanılır.

**Tipik istifadə halları:**
- Dizayn faylları (`.psd`, `.ai`, `.sketch`, `.fig`)
- Video və audio (`.mp4`, `.mov`, `.wav`)
- ML model faylları (`.pkl`, `.h5`, `.onnx`, `.safetensors`)
- Arxiv və database dump-ları (`.zip`, `.sql`, `.dump`)
- Böyük şəkillər (`.tiff`, `.raw`)

---

## Niyə Vacibdir

ML model-ləri, böyük SQL dump-lar, media faylları, PDF-lər olan Laravel layihələrindəki repo şişkinlik problemini həll edir. Normal git binary faylları diff edə bilmir, hər versiyasını tam saxlayır — LFS isə yalnız pointer saxlayır, real fayl ayrı storage-da olur.

## Əsas Əmrlər (Key Commands)

### Quraşdırma
```bash
# Ubuntu/Debian
sudo apt install git-lfs

# macOS
brew install git-lfs

# Global olaraq aktivləşdirmək (bir dəfəlik)
git lfs install

# Layihədə aktivləşdirmək
cd my-project
git lfs install --local
```

### Fayl izləmə (tracking)
```bash
# Müəyyən genişlənməni izləmək
git lfs track "*.psd"
git lfs track "*.zip"
git lfs track "assets/videos/**"

# İzlənən fayllar siyahısı
git lfs track

# İzləmədən çıxarmaq
git lfs untrack "*.psd"
```

### İdarəetmə
```bash
# LFS faylları siyahısı
git lfs ls-files

# LFS status
git lfs status

# LFS məzmununu yükləmək (başqa klondan sonra)
git lfs pull

# Yalnız pointer-ləri yükləmək (məzmunsuz)
GIT_LFS_SKIP_SMUDGE=1 git clone <repo>

# LFS obyektlərini server-ə yükləmək
git lfs push origin main
```

### Migrasiya
```bash
# Tarixdəki böyük faylları LFS-ə köçürmək
git lfs migrate import --include="*.psd" --include="*.zip"

# Bütün tarixi dəyişdirmək (təhlükəli!)
git lfs migrate import --everything --include="*.mp4"

# LFS-dən geri çıxarmaq
git lfs migrate export --include="*.psd"
```

---

## Nümunələr

### Nümunə 1: Yeni Laravel layihəsi üçün LFS qurmaq

```bash
cd ~/projects/laravel-shop

# LFS-i aktivləşdirmək
git lfs install

# Dizayn faylları və media üçün tracking
git lfs track "public/videos/*.mp4"
git lfs track "storage/app/designs/*.psd"
git lfs track "database/seeds/*.sql"

# .gitattributes faylı commit edilməlidir
git add .gitattributes
git commit -m "chore: configure Git LFS for media files"

# İndi böyük fayl əlavə edin
cp ~/Downloads/promo.mp4 public/videos/
git add public/videos/promo.mp4
git commit -m "feat: add promo video"
git push origin main
```

### Nümunə 2: Pointer faylı necə görünür?

Git LFS olmadan fayl binary olaraq saxlanılır. LFS ilə isə:
```bash
$ cat public/videos/promo.mp4
version https://git-lfs.github.com/spec/v1
oid sha256:4cac19622fc3ada9c0fdeadb33f88f367b541f38b89102a3f1261ac81fd5bcb5
size 157286400
```

Bu ~130 byte-lıq pointer faylıdır. Əsl 150 MB-lıq video LFS server-indədir.

### Nümunə 3: Köhnə layihədə böyük faylları tapmaq və LFS-ə köçürmək

```bash
# Repozitoriyadakı ən böyük 10 faylı tapmaq
git rev-list --objects --all \
  | git cat-file --batch-check='%(objecttype) %(objectname) %(objectsize) %(rest)' \
  | awk '/^blob/ {print $3, $4}' \
  | sort -n -r | head -10

# Nəticə:
# 157286400 public/videos/intro.mp4
# 52428800  storage/designs/logo.psd
# 20971520  database/dump.sql

# Onları LFS-ə köçürmək
git lfs migrate import --include="*.mp4,*.psd,*.sql" --include-ref=refs/heads/main

# Force push lazım olacaq (tarix dəyişib)
git push --force-with-lease origin main
```

### Nümunə 4: Team member üçün clone

```bash
# Normal clone (LFS faylları avtomatik yüklənir)
git clone https://github.com/company/laravel-shop.git

# Yalnız pointer-lər (tez klon üçün)
GIT_LFS_SKIP_SMUDGE=1 git clone https://github.com/company/laravel-shop.git

# Sonradan yükləmək
cd laravel-shop
git lfs pull

# Yalnız müəyyən fayllar üçün
git lfs pull --include="public/videos/*"
```

### Nümunə 5: LFS storage quota yoxlaması

```bash
# LFS obyektlərinin ölçüsü
git lfs ls-files -s

# Nəticə:
# 4cac1962 * public/videos/promo.mp4 (150 MB)
# 7f3a2b5c * storage/designs/logo.psd (50 MB)
# 2e9d8f1a * database/seeds/data.sql (20 MB)
```

GitHub-da default LFS limiti:
- Free: 1 GB storage + 1 GB bandwidth/ay
- Pro: 2 GB storage + data pack ala bilərsən

---

## Vizual İzah (Visual Explanation)

### Git LFS arxitekturası

```
   Developer                 Git Repo                  LFS Server
                                                  (GitHub/GitLab/S3)
  ┌──────────┐          ┌──────────────────┐      ┌──────────────────┐
  │ video.mp4│          │                  │      │                  │
  │ (150 MB) │─tracked─>│ pointer file     │      │  video.mp4       │
  └──────────┘          │ (130 bytes)      │      │  (150 MB)        │
                        │                  │      │                  │
                        │ version: 1       │      │  sha256:4cac...  │
                        │ oid: sha256:4cac │<────>│                  │
                        │ size: 157286400  │      │                  │
                        └──────────────────┘      └──────────────────┘
                               │
                               │ git push
                               v
                        ┌──────────────────┐
                        │  GitHub Repo     │
                        │  (only pointers) │
                        └──────────────────┘
```

### Pointer vs Real file

```
Without LFS:                    With LFS:
┌──────────────────┐           ┌──────────────────┐
│ Git Object       │           │ Git Object       │
│                  │           │ (pointer)        │
│ [150 MB binary]  │           │ "version https...│
│                  │           │  oid sha256:...  │
│                  │           │  size 157286400" │
└──────────────────┘           └──────────────────┘
Git tarixi böyüyür              Git tarixi kiçik qalır
Slow clone/fetch                Fast clone/fetch
```

### LFS workflow

```
  git add video.mp4
        │
        v
 ┌──────────────────┐
 │ .gitattributes   │──pattern match──> "*.mp4 filter=lfs"
 │ rules            │
 └──────────────────┘
        │
        v
 ┌──────────────────┐     ┌──────────────────┐
 │ Clean filter     │───> │ Upload to LFS    │
 │ (replace with    │     │ server           │
 │ pointer)         │     └──────────────────┘
 └──────────────────┘
        │
        v
 ┌──────────────────┐
 │ Commit pointer   │
 │ to Git           │
 └──────────────────┘

  git checkout:
  ┌──────────────────┐     ┌──────────────────┐
  │ Read pointer     │───> │ Download from    │
  │                  │     │ LFS server       │
  └──────────────────┘     └──────────────────┘
        │
        v
 ┌──────────────────┐
 │ Smudge filter    │──replaces pointer with real content
 │ (restore file)   │
 └──────────────────┘
```

---

## Praktik Baxış

### Tipik Laravel `.gitattributes` (LFS)

```
# Videos
*.mp4 filter=lfs diff=lfs merge=lfs -text
*.mov filter=lfs diff=lfs merge=lfs -text
*.webm filter=lfs diff=lfs merge=lfs -text

# Design files
*.psd filter=lfs diff=lfs merge=lfs -text
*.ai filter=lfs diff=lfs merge=lfs -text
*.sketch filter=lfs diff=lfs merge=lfs -text
*.fig filter=lfs diff=lfs merge=lfs -text

# Archives
*.zip filter=lfs diff=lfs merge=lfs -text
*.tar.gz filter=lfs diff=lfs merge=lfs -text

# Database dumps
database/seeds/*.sql filter=lfs diff=lfs merge=lfs -text

# Large assets
public/assets/videos/** filter=lfs diff=lfs merge=lfs -text
storage/app/media/large/** filter=lfs diff=lfs merge=lfs -text
```

### Deployment səhvi – çox rast gələn problem

Forge/Envoyer-də deploy zamanı:
```bash
# deploy.sh
cd /var/www/laravel-shop
git pull origin main

# BUG: LFS faylları pointer olaraq qalır
# php artisan serve zamanı "file corrupted" xətası
```

Həll:
```bash
# deploy.sh
cd /var/www/laravel-shop
git pull origin main
git lfs pull  # <-- bu əlavə edilməlidir

# və ya
git lfs install  # server-də bir dəfə
git pull  # avtomatik smudge olacaq
```

### CI/CD-də LFS

`.github/workflows/deploy.yml`:
```yaml
name: Deploy Laravel

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          lfs: true  # <-- bu vacibdir!

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'

      - name: Deploy to server
        run: rsync -avz ./ server:/var/www/laravel-shop/
```

### Laravel seeding – böyük fixture faylları

```php
// database/seeders/ProductSeeder.php
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // LFS ilə izlənən böyük SQL fayl
        $sql = file_get_contents(database_path('seeds/products_data.sql'));
        DB::unprepared($sql);
    }
}
```

`.gitattributes`:
```
database/seeds/*.sql filter=lfs diff=lfs merge=lfs -text
```

---

## Alternativlər

### 1. Git Annex
```bash
# Git Annex daha çox elastikdir
git annex init
git annex add large-file.bin
git annex sync
```
**Fərq:** Annex metadata-ya əsaslanır, LFS server-sentric. Annex təhsil/elmi məlumatlar üçün populyardır.

### 2. DVC (Data Version Control) – ML üçün
```bash
# DVC ML layihələri üçün ideal
pip install dvc
dvc init
dvc add data/dataset.csv
git add data/dataset.csv.dvc
dvc push  # to S3/GCS/Azure
```
**Üstünlük:** Data pipeline-lar, experiment tracking, S3/GCS native dəstək.

### 3. Git-fat (köhnə)
Artıq aktiv dəstəklənmir, yalnız legacy layihələrdə görünür.

### 4. Müqayisə cədvəli

| Tool       | İstifadə halı              | Server lazım? | ML dəstəyi |
|------------|----------------------------|---------------|------------|
| Git LFS    | Dizayn, media, arxivlər    | Bəli          | Məhdud     |
| Git Annex  | Elmi məlumatlar            | Xeyr (P2P)    | Yaxşı      |
| DVC        | ML datasets, modellər      | Bəli (S3/GCS) | Əla        |
| Pure Git   | Yalnız kod                 | Yox           | Yox        |

---

## Praktik Tapşırıqlar

1. **LFS qurulumu**
   ```bash
   git lfs install  # bir dəfə global

   # Mövcud repo-da aktiv et
   git lfs track "*.pdf"
   git lfs track "*.zip"
   git lfs track "storage/app/uploads/**"
   git add .gitattributes
   git commit -m "chore: configure git lfs tracking"
   ```

2. **Mövcud faylları LFS-ə migrate et**
   ```bash
   git lfs migrate import --include="*.pdf" --everything
   # Tarixçədəki bütün PDF-lər LFS-ə köçürüldü
   git push --force-with-lease
   ```

3. **LFS status yoxla**
   ```bash
   git lfs ls-files       # LFS-dəki fayllar
   git lfs status         # current state
   git lfs env            # konfiqurasiya
   ```

4. **Selective download (CI üçün)**
   ```bash
   # CI-da LFS fayllarına ehtiyac yoxdursa:
   GIT_LFS_SKIP_SMUDGE=1 git clone <repo>
   # Sonra lazım olanda:
   git lfs pull
   ```

## Interview Sualları (Q&A)

### Q1: Git LFS-in əsas işləmə prinsipi nədir?

**Cavab:** Git LFS `git add`-dən əvvəl `clean filter` tətbiq edir. Bu filter böyük faylı aşağıdakı ilə əvəz edir:
- `version` – LFS spec versiyası
- `oid sha256:...` – fayl məzmununun hash-i
- `size` – fayl ölçüsü

Bu pointer faylı Git-ə commit olunur. Real məzmun isə LFS server-inə (məs. GitHub LFS) yüklənir. `git checkout` zamanı `smudge filter` pointer-i real fayl ilə əvəz edir.

### Q2: LFS fayllarını regular Git-ə qaytarmaq olar?

**Cavab:** Bəli, `git lfs migrate export` ilə:
```bash
git lfs migrate export --include="*.psd" --everything
```
Amma bu tarixi yenidən yazır və repozitoriya ölçüsünü artıracaq. Team-lə koordinasiya lazımdır (force push).

### Q3: Mən `git pull` etdim, amma faylın məzmunu pointer görünür. Niyə?

**Cavab:** 3 səbəb ola bilər:
1. **Git LFS quraşdırılmayıb**: `git lfs install` lazımdır.
2. **`GIT_LFS_SKIP_SMUDGE=1` aktivdir**: Pointer-lər yüklənir amma məzmun yox. `git lfs pull` işlədin.
3. **LFS quota bitib**: GitHub LFS limit doldurursa fayl yüklənmir. `git lfs fetch --all` xəta verəcək.

### Q4: GitHub LFS quota və qiyməti necədir?

**Cavab:** GitHub default:
- **Free:** 1 GB storage + 1 GB bandwidth/ay
- **Data pack:** $5/ay ilə +50 GB

Quota hər istifadəçi/təşkilat üçündür. Böyük layihələr üçün self-hosted LFS (məs. GitLab, Gitea) və ya AWS S3-based LFS istifadə edilə bilər.

### Q5: LFS və binary diff işləyirmi?

**Cavab:** Default olaraq xeyr. Binary fayllar üçün diff mənasız olduğundan LFS sadəcə "Binary files differ" göstərir. Xüsusi diff driver-lər konfiqurasiya edilə bilər:
```
*.psd diff=exif
```
`exif` driver metadata-nı müqayisə edir. Ancaq real binary diff üçün Git uyğun deyil – `git-annex` daha yaxşıdır.

### Q6: `git lfs migrate import` və `git lfs migrate export` arasında fərq?

**Cavab:**
- **Import:** Regular Git fayllarını LFS-ə köçürür (tarix yenilənir, fayllar LFS pointer-ə çevrilir).
- **Export:** LFS fayllarını regular Git-ə qaytarır (pointer-lər real məzmunla əvəzlənir).

Hər ikisi `git filter-branch` kimi tarixi yenidən yazır, ona görə force push tələb edir.

### Q7: Layihədə böyük fayl səhvən LFS-sız commit edildi. Nə etməli?

**Cavab:**
```bash
# 1. Faylı LFS-ə köçürmək
git lfs track "big-file.zip"
git add .gitattributes
git rm --cached big-file.zip
git add big-file.zip

# 2. Tarixdən təmizləmək
git lfs migrate import --include="big-file.zip" --everything

# 3. Force push
git push --force-with-lease

# 4. Team-ə xəbər ver: onlar re-clone etməlidir
```

### Q8: LFS ilə `git clone` niyə bəzən yavaşdır?

**Cavab:** Çünki clone iki mərhələdə olur:
1. Git obyektləri yüklənir (pointers daxil).
2. LFS faylları yüklənir (böyük olan hissə).

CI-də optimallaşdırmaq üçün:
```bash
# Shallow clone + lazy LFS
GIT_LFS_SKIP_SMUDGE=1 git clone --depth=1 <repo>
git lfs pull --include="only/needed/files"
```

### Q9: `.gitattributes` fayli commit edilməlidirmi?

**Cavab:** **Bəli, mütləq!** `.gitattributes` LFS tracking qaydalarını saxlayır. Bu fayl commit edilməsə:
- Yeni team members LFS-i avtomatik istifadə etməyəcək.
- Böyük fayllar regular Git-ə daxil olub repozitoriyanı şişirdəcək.

### Q10: DVC nə vaxt LFS-dən daha yaxşıdır?

**Cavab:** DVC ML layihələri üçün daha güclüdür:
- Data pipeline-lar (input → transform → output).
- Experiment tracking (hər model versiyası üçün metric-lər).
- S3, GCS, Azure ilə native inteqrasiya.
- Data lineage və reproducibility.

Sadə dizayn/media fayllar üçün LFS kifayətdir.

---

## Best Practices

1. **.gitattributes-ı erkən konfiqurasiya edin**: Layihə başlayanda, böyük fayllar əlavə edilməzdən əvvəl.

2. **Sadə pattern-lər seçin**: `*.mp4` yaxşıdır, `public/videos/2024/**/*.mp4` çətin saxlanılır.

3. **Binary olan hər şeyi LFS-ə əlavə etməyin**: Kiçik binary (< 100 KB) Git-də qalması daha yaxşıdır – LFS hər əməliyyata latency əlavə edir.

4. **Team-ə `git lfs install` öyrədin**: Onboarding checklist-ə daxil edin.

5. **Deploy script-ində `git lfs pull` olsun**: Server-də LFS fayllarının yüklənməməsi production bug-ıdır.

6. **CI/CD-də `lfs: true` bayrağı istifadə edin**: GitHub Actions, GitLab CI, CircleCI hamısı dəstəkləyir.

7. **Quota-nı izləyin**: Hər ay GitHub LFS dashboard-a baxın. Limit doldurmasın.

8. **Old LFS obyektləri təmizləyin**: `git lfs prune` istifadə olunmayan obyektləri yerli olaraq silir.

9. **Sensitive data-nı LFS-ə qoymayın**: LFS obyektləri repository silinsə də server-də qala bilər. Sensitive üçün ayrı secure storage istifadə edin.

10. **Alternative-i nəzərdən keçirin**: ML layihələri üçün DVC, dağıdılmış elmi məlumatlar üçün Git Annex daha uyğun ola bilər.

11. **Locking funksiyasından istifadə edin** (kollektiv dizayn faylları üçün):
    ```bash
    git lfs lock "design.psd"  # başqası redaktə edə bilməz
    git lfs unlock "design.psd"
    ```

12. **LFS versioning policy qurun**: Hansı fayl növləri LFS-də olmalıdır, quota necə bölünür, kim nəyə icazəlidir – bunu README-də sənədləşdirin.

## Əlaqəli Mövzular

- [30-git-performance-large-repos.md](30-git-performance-large-repos.md) — large repo optimizasiyası
- [31-git-maintenance.md](31-git-maintenance.md) — repo saxlanması
