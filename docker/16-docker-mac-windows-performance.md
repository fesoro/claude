# Docker Performance Mac və Windows-da

> **Səviyyə (Level):** ⭐⭐ Middle
> **Oxu müddəti:** ~15-20 dəqiqə
> **Kateqoriya:** Docker / Development Experience

## Nədir? (What is it?)

"Laravel-im lokal macOS-da 5 saniyədə yüklənir, CI-də 500 ms-dir. Niyə?" — Bu klassik Docker Desktop problemidir. Səbəb: **Docker Desktop Mac və Windows-da konteynerləri nativ işlətmir** — arada Linux VM (virtual maşın) var.

Memo:
- **Linux** — Docker host OS-a birbaşa quraşdırılır. Konteynerlər host kernel-ini paylaşır. Filesystem native-dir — sürətli.
- **macOS** — Docker Desktop kiçik VM işlədir (`hyperkit`, indi `Virtualization.framework`). Kod host-dadır, konteynerin filesystem-i VM-dədir. Fayl oxumaq `host → VM → container` yolu keçir.
- **Windows** — Docker Desktop WSL 2 backend istifadə edir (Windows Subsystem for Linux). Eyni VM problem + Windows↔Linux filesystem köprüsü (9P protokol).

**Əsas bottleneck:** fayl sinxronizasiyası (file sync). PHP layihəsində `composer install` 15000+ kiçik fayl yaradır, hər request 100+ fayl oxuyur (autoload, view, config). Əgər hər fayl oxuma VM sərhədi keçirsə — milisaniyələr yığılır.

Tipik rəqəmlər:

| Setup | `php artisan migrate:fresh` | HTTP request (soyuq opcache) |
|-------|-----------------------------|-------------------------------|
| Linux native | ~0.8 s | ~40 ms |
| macOS VirtioFS (yeni) | ~2-4 s | ~150 ms |
| macOS gRPC FUSE (köhnə) | ~8-15 s | ~500 ms - 2 s |
| Windows WSL 2, kod `/mnt/c/` | ~12-30 s | ~1-3 s |
| Windows WSL 2, kod `/home/user/` | ~1-2 s | ~60 ms |
| Mutagen sync | ~1 s | ~50 ms |

Yəni kod harada yaşadığı **10-30x fərq yaradır**.

## Əsas Konseptlər

### 1. macOS — VirtioFS, gRPC FUSE, osxfs

Docker Desktop Mac-də üç nəsil filesystem sürücüsü gördü:

| Sürücü | Dövr | Performans | Status |
|--------|------|------------|--------|
| **osxfs** | 2016-2020 | Çox yavaş (bizim nümunə 15 saniyə) | Tamamilə köhnədir |
| **gRPC FUSE** | 2020-2022 | Yavaş-orta | Köhnə, hələ var |
| **VirtioFS** | 2022+ | Sürətli (3-5x yaxşıdır) | Default (M1/M2/M3, macOS 12.5+) |

**Yoxla hansıdır:**
Docker Desktop → Settings → General → "Choose file sharing implementation":
- `VirtioFS` — istifadə et
- `gRPC FUSE` — geri dön VirtioFS-ə
- `osxfs (legacy)` — 2016-dan qalmadır, heç vaxt

**`:cached` və `:delegated` Mount Flag-ları**

osxfs dövründə bu flag-lar istifadə olunurdu:

```yaml
# Köhnə docker-compose.yml
volumes:
  - ./:/var/www/html:cached     # Host authoritative, container yavaş oxuyur
  - ./:/var/www/html:delegated  # Container authoritative, host yavaş oxuyur
```

| Flag | Məna |
|------|------|
| `consistent` (default) | Host ↔ container tam sinxron — ən yavaş |
| `:cached` | Host yazdığı — container bir az gec görə bilər (OK) |
| `:delegated` | Container yazdığı — host bir az gec görə bilər |

**VirtioFS dövründə bu flag-lar effektsizdir** — Docker onları ignore edir. Amma köhnə `docker-compose.yml`-larda hələ çox görünür. Compose warn vermir.

### 2. macOS Alternativ: Mutagen Sync

Mutagen fərqli yanaşma: **bind mount yoxdur** — 2 ayrı filesystem (host və konteyner volume) arasında file watcher ilə sync edir. Kiçik fayl üçün çox sürətlidir.

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:dev
    volumes:
      - app-code:/var/www/html     # Named volume, bind yox
      - vendor-cache:/var/www/html/vendor

volumes:
  app-code:
  vendor-cache:
```

```yaml
# mutagen.yml
sync:
  app:
    alpha: "./"
    beta: "docker://myapp_app_1/var/www/html"
    mode: "two-way-resolved"
    ignore:
      paths:
        - /node_modules
        - /vendor
        - /.git
        - /storage/logs
```

İşə sal:
```bash
mutagen-compose up   # Docker Compose + Mutagen bir yerdə
```

Fayl dəyişdirəndə ~100 ms-də konteynerdə görünür. Nativə yaxın.

**Minus:** Config mürəkkəb, paid tool (Mutagen Pro), debug çətin olur. Əvvəl VirtioFS sına.

### 3. Windows — WSL 2 və Filesystem Crossing

Windows-da Docker Desktop default olaraq **WSL 2 backend** istifadə edir — çox yaxşıdır, amma bir KRİTİK qayda var:

**Kodunu `/mnt/c/` altında SAXLAMA. `/home/<user>/` altında saxla.**

Niyə?
- WSL 2 özünü Linux VM kimi işlədir, öz ext4 filesystem-i var (`/home/...`, `/tmp/...`)
- Windows diskləri (C:\, D:\) `/mnt/c/`, `/mnt/d/` kimi mount olunur
- `/mnt/c/` 9P protokolu ilə işləyir — **çox yavaşdır** (10-30x)
- Native WSL filesystem-i (`/home/user/`) native Linux sürətidir

**Doğru workflow:**

```bash
# WSL 2 terminal (Ubuntu)
cd ~
mkdir code
cd code
git clone git@github.com:mycompany/myapp.git
cd myapp
docker compose up
```

**Yanlış workflow:**

```bash
# Windows PowerShell və ya cmd
cd C:\Users\Me\Projects\myapp
docker compose up
# docker-compose.yml: volumes: - .:/var/www/html  → /mnt/c/Users/... mount edir → YAVAŞ
```

Test:
```bash
# /mnt/c/ altında
time php artisan migrate:fresh
# 25 saniyə

# /home/user/ altında
time php artisan migrate:fresh
# 1.2 saniyə
```

20x fərq.

**VS Code Remote-WSL Extension** — Windows-da VS Code açırsan, `WSL: Open Folder`-lə `/home/user/code/myapp`-ı aç. IDE host-dadır, fayl WSL-də — Docker-də də WSL-də. Native sürət.

### 4. Selective Bind Mount — Nə Lazımdır, Onu Mount Et

Bütün kodu mount etmək yerinə, yalnız dəyişən hissələri mount et, vendor kimi böyük qovluqları anonymous volume-də saxla:

```yaml
services:
  app:
    build:
      context: .
      target: dev
    volumes:
      # Mənbə kodu — host-dan gəlir, canlı dəyişiklik
      - ./app:/var/www/html/app
      - ./config:/var/www/html/config
      - ./routes:/var/www/html/routes
      - ./resources:/var/www/html/resources
      - ./database:/var/www/html/database
      - ./public:/var/www/html/public
      - ./tests:/var/www/html/tests
      - ./composer.json:/var/www/html/composer.json
      - ./composer.lock:/var/www/html/composer.lock
      - ./artisan:/var/www/html/artisan
      
      # vendor — image-dəki versiya (host boşdur, ya da yavaş olur)
      - /var/www/html/vendor
      
      # node_modules — eyni
      - /var/www/html/node_modules
      
      # storage — named volume (log/cache əsl dəyişir)
      - storage-data:/var/www/html/storage

volumes:
  storage-data:
```

Niyə yaxşıdır?
- `vendor/` ~20000 fayl, heç vaxt "canlı" dəyişmir — bind mount etməyə ehtiyac yoxdur
- Anonymous volume VM içində qalır — native sürət
- Yalnız PHP kodu bind mount olur — dəyişdirəndə dərhal konteynerdə görünür

Test nümunəsi:

| Setup | `composer install` sonra `up` | HTTP request |
|-------|-------------------------------|--------------|
| `./:/var/www/html` (hamısı bind) | 6 s (vendor symlink sync) | 800 ms |
| Selective + anonymous vendor | 1 s | 90 ms |

### 5. OPcache Dev Setup

VirtioFS belə bir az yavaşlıq var. OPcache `validate_timestamps` parametri bunu yumşaldır:

```ini
; docker/php/opcache-dev.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000

; VACIB — dev-də kod dəyişir, yoxlama lazımdır
opcache.validate_timestamps=1

; Hər 2 saniyə bir dəfə yoxla (hər requestdə deyil)
opcache.revalidate_freq=2

; File timestamps yaxşı gəlir mi?
opcache.file_cache=/tmp/opcache
```

`revalidate_freq=0` → hər request fayl timestamp-ını yoxlayır — VirtioFS-də yavaşdır. `revalidate_freq=2` → 2 saniyədə bir dəfə, bu da developer üçün kifayətdir (faylı dəyişirsən, 2 saniyə gözləyirsən, refresh).

Production-da:
```ini
opcache.validate_timestamps=0   ; Heç vaxt yoxlama
```

## Tam İşləyən Compose Nümunələri

### macOS — VirtioFS (modern)

```yaml
# docker-compose.override.yml (macOS dev)
services:
  app:
    build:
      context: .
      target: dev
    volumes:
      - ./app:/var/www/html/app
      - ./config:/var/www/html/config
      - ./routes:/var/www/html/routes
      - ./resources:/var/www/html/resources
      - ./database:/var/www/html/database
      - ./public:/var/www/html/public
      - ./tests:/var/www/html/tests
      - ./composer.json:/var/www/html/composer.json
      - ./composer.lock:/var/www/html/composer.lock
      - ./artisan:/var/www/html/artisan
      - /var/www/html/vendor
      - /var/www/html/node_modules
      - storage-data:/var/www/html/storage
    environment:
      - OPCACHE_VALIDATE_TIMESTAMPS=1
      - OPCACHE_REVALIDATE_FREQ=2

volumes:
  storage-data:
```

Docker Desktop → Settings → General → file sharing: **VirtioFS** seçilib olmalıdır.

### Windows WSL 2

```bash
# WSL 2 terminal
cd ~/code/myapp   # DƏQİQ /home altında

# Yoxla harda olduğunu
pwd
# /home/orkhan/code/myapp   ← yaxşı
# DEYİL: /mnt/c/Users/Orkhan/Projects/myapp   ← pis
```

```yaml
# docker-compose.yml (dəyişmir Mac ilə müqayisədə)
services:
  app:
    build:
      context: .
      target: dev
    volumes:
      - .:/var/www/html
      - /var/www/html/vendor
      - /var/www/html/node_modules
```

WSL 2 native filesystem-də `.:/var/www/html` bind mount nativə yaxındır.

### Mutagen (macOS paid alternative)

```yaml
# docker-compose.yml
services:
  app:
    image: myapp:dev
    volumes:
      - app-sync:/var/www/html
      - /var/www/html/vendor
      - /var/www/html/node_modules

volumes:
  app-sync:
```

```yaml
# mutagen.yml
sync:
  defaults:
    mode: "two-way-resolved"
    ignore:
      vcs: true
      paths:
        - "/vendor"
        - "/node_modules"
        - "/storage/logs"
        - "/storage/framework/cache"
        - "/.git"
        - "/.idea"
  
  app:
    alpha: "."
    beta: "volume://myapp_app-sync"
```

```bash
mutagen-compose up -d
```

Fayl dəyişəndə Mutagen 100-200 ms-də sync edir. Native sürətə çox yaxındır.

## Alternativlər — Lima, Colima, OrbStack

Docker Desktop Mac lisenziya problemi (böyük şirkətlərdə paid) və RAM istifadəsi (~4 GB) səbəbindən alternativlər yarandı:

| Tool | Nə edir | Performans |
|------|---------|------------|
| **Colima** | Lima-based Docker replacement (CLI) | VirtioFS default, sürətli |
| **Lima** | Linux VM manager (manual Docker setup) | Customizable |
| **OrbStack** | Docker Desktop alternativi (paid, macOS-only) | Əfsanəvi sürətli, low RAM |
| **Rancher Desktop** | Docker Desktop replace + K8s | Orta performans |

**Colima quraşdırma:**
```bash
brew install colima docker
colima start --cpu 4 --memory 8 --vm-type vz --mount-type virtiofs
docker compose up
```

`--vm-type vz` → Apple Virtualization.framework (M1/M2/M3-də sürətli).
`--mount-type virtiofs` → VirtioFS aktiv.

**OrbStack** — bir çox developer-in Mac üçün yeni default-u. Əsl rəqib:
- RAM: OrbStack ~200 MB vs Docker Desktop 2-4 GB
- CPU: ~0% idle vs Docker Desktop 5-10%
- File sync: native-ə yaxın

```bash
brew install --cask orbstack
# Docker CLI avtomatik qoşulur
docker compose up
```

Saniyəlik fərqləri düşünürsən — OrbStack Laravel dev üçün şiddətlə tövsiyə.

## Benchmark — Real Ölçmə

Layihəndə hansı setup daha sürətlidir? Ölç:

```bash
# Container içində
docker compose exec app bash

# 1. Database fresh migrate
time php artisan migrate:fresh --seed

# 2. Test suite
time php artisan test

# 3. Cache warm-up
time php artisan optimize

# 4. Composer install sıfırdan
rm -rf vendor && time composer install
```

Real müştəri nümunəsi (M1 MacBook Pro, Laravel 11):

| Setup | `migrate:fresh --seed` | `artisan test` (850 test) |
|-------|-------------------------|---------------------------|
| Docker Desktop osxfs (2020) | 45 s | 180 s |
| Docker Desktop VirtioFS (2024) | 6 s | 28 s |
| OrbStack | 2 s | 11 s |
| Native (host-da PHP) | 1.5 s | 8 s |

Developer günündə 20 dəfə test run edir — 180s vs 11s → günündə 1 saat qazanc.

## Tələlər (Gotchas)

### 1. Windows-da `/mnt/c/` altında kod

Ən çox yayılan səhv. Developer Windows-da yeni başladı, `C:\Projects\myapp`-da git clone etdi. `docker compose up` → hər şey 20x yavaş.

**Həll:** Kodu `~/code/myapp`-a köçür (WSL native FS). VS Code-da `WSL: Open Folder` ilə aç.

### 2. macOS-da köhnə gRPC FUSE

Docker Desktop yenilənib amma setting köhnə. `Settings → General → file sharing` yoxla, VirtioFS seç.

### 3. Bind mount `.:/var/www/html` + böyük `vendor/`

macOS-da `vendor/` 20000 faylı host-dan mount edir. Hər `autoload.php` oxuma VM sərhədi keçir.

**Həll:** Anonymous volume `vendor/` üçün (yuxarı pattern).

### 4. `:cached` flag hələ yazılır

Köhnə `docker-compose.yml`-da:
```yaml
- .:/var/www/html:cached
```

VirtioFS-lə effekt etmir. Zərərli deyil, amma ümid yaratmaq səhvdir.

**Həll:** Fləgi sil (cleanup).

### 5. `.git/` bind mount

`.git/` qovluğu 10000+ fayldır. Konteynerdə lazım deyil, amma mount olunur.

**Həll:** `.dockerignore`-a `.git/` əlavə et, və selective mount-da `.git/` köçürmə.

### 6. Mutagen sync konflikt

İki tərəf eyni faylı dəyişir — konflikt.

**Həll:** `mode: "two-way-resolved"` + storage/logs, cache kimi konteynerin yazdığı qovluqları ignore et.

### 7. WSL 2 RAM artımı

WSL 2 VM vaxt keçdikcə RAM-ı azad etmir. `Vmmem` process 10 GB+ istifadə edə bilər.

**Həll:** `.wslconfig` (Windows user home):
```ini
[wsl2]
memory=4GB
processors=4
swap=2GB
```

`wsl --shutdown` ilə restart et.

### 8. Docker Desktop disk dolur

VM virtual disk-i (`Docker.raw`) böyüyür, amma silinmir. 100 GB-a çata bilər.

**Həll:** `Settings → Resources → Disk image size` və ya `docker system prune -a --volumes`.

### 9. Saat drift konteynerdə

Mac yuxuya gedəndə VM saatı dayanır, oyananda saat səhv olur. MySQL-də "MySQL server has gone away".

**Həll:** Docker Desktop auto time sync-i aktiv et. Və ya:
```bash
docker run --rm --privileged alpine hwclock -s
```

### 10. Colima-da `localhost` qoşulmur

Docker Desktop `localhost:3306`-ı otomatik forward edir. Colima etməyə bilər.

**Həll:** `colima start --network-address` və ya portları mövcud compose-də açıq saxla.

## Müsahibə Sualları

- **Q:** Niyə Docker Mac/Windows-da Linux-dan yavaşdır?
  - macOS və Windows-da Docker konteynerləri nativ işlətmir — arada Linux VM var. Host faylı konteynerə çatmaq üçün VM sərhədi keçir (macOS: VirtioFS/gRPC FUSE, Windows: 9P protokol). Laravel-də hər request 100+ fayl oxuyur — mikrosaniyələr yığılır.

- **Q:** macOS-da VirtioFS vs gRPC FUSE?
  - VirtioFS Docker Desktop 4.6+ (2022) yeni nəsildir — 3-5x sürətli. gRPC FUSE köhnədir. macOS 12.5+ və M1/M2/M3-də VirtioFS default-dur. Settings → General-də yoxla.

- **Q:** Windows WSL 2-də kod harda saxlanmalıdır?
  - **`/home/user/`** (WSL native ext4 filesystem). `/mnt/c/`-də saxlamaq 10-30x yavaşlıq — 9P protokolu ilə Windows filesystem-ə çatır. VS Code Remote-WSL extension istifadə et.

- **Q:** `:cached` və `:delegated` flag-ları bu gün hələ lazımdır?
  - **Xeyr.** osxfs (2016-2020) dövründən qalmadır. VirtioFS-lə effektsizdir. gRPC FUSE-də bir qədər təsir edir. Yeni layihələrdə istifadə etmə.

- **Q:** Dev-də `vendor/` qovluğu niyə bind mount olunmur?
  - 20000+ fayl, heç vaxt "canlı" dəyişmir — yalnız `composer install` zamanı. Bind mount-la host↔VM sync ödəyirsən boş yerə. Anonymous volume-də image-dəki versiya qalır, native sürət. Eyni pattern `node_modules/` üçün.

- **Q:** Mutagen nədir, nə vaxt istifadə edərdiniz?
  - macOS-da bind mount əvəzinə file watcher + sync daemon. Kiçik fayl dəyişiklikləri ~100 ms-də konteynerə çatır — native sürətə yaxın. Qurmaq mürəkkəbdir. Əvvəlcə VirtioFS sına, kifayət etmirsə Mutagen və ya OrbStack.

- **Q:** OpCache dev-də necə konfiqurasiya olunmalıdır?
  - `validate_timestamps=1` (kod dəyişir, yoxla) + `revalidate_freq=2` (2 saniyə). Hər request-də stat-lamaq yavaşdır VirtioFS-də. Production-da `validate_timestamps=0`.

- **Q:** OrbStack nədir?
  - macOS-only Docker Desktop alternativi (paid, ~$8/ay). Daha az RAM (200 MB vs 4 GB), daha az CPU, native-ə çox yaxın file sync. Bir çox Laravel developer M1/M2/M3 Mac-də Docker Desktop-dan keçir OrbStack-ə.

- **Q:** Docker Desktop əvəzinə açıq mənbə nə var?
  - **Colima** (Lima üzərində, VirtioFS default), **Rancher Desktop** (K8s də var), **Lima** (manual). Hamısı macOS-da. Windows-da alternativ yoxdur — WSL 2 + Docker Desktop (və ya qurğusuz Docker Engine).

- **Q:** Test-lər CI-də işləyir amma lokal yavaşdır — niyə?
  - CI native Linux-dadır (GitHub Actions Ubuntu runner). Lokal Mac/Windows-da VM sərhədi var. CI-də 30 saniyə → lokal 5 dəqiqə olmaq normal ola bilər. Həll: VirtioFS, selective mount, OrbStack, və ya testləri konteynerdə deyil bir dəfə CI-də icra et.
