# Docker File Permissions for PHP/Laravel

## Nədir? (What is it?)

Docker-də PHP/Laravel ilə **ən çox tripping-edən problem** — fayl icazələri. Laravel konteyner başladıqda `Permission denied: storage/logs/laravel.log` verir. Bind mount-dan sonra host-da yaradılan fayllar `root`-undur. Sonra developer `sudo chown -R user:user .` edir və təkrar başlayır.

Bu problem aşağıdakı səbəblərdən yaranır:
- **PHP-FPM default-da `www-data` user altında işləyir** — UID 82 (Alpine) və ya UID 33 (Debian)
- **Host developer-in UID-i** adətən 1000 (Linux)
- **Bind mount** host-un UID-ni saxlayır — konteyner içində 1000 UID-i yoxdur, `www-data` yaza bilmir

Həll: **UID/GID-i match etmək** və storage icazələrini düzgün qurmaq.

## Problem Demosu

### Host-da:
```bash
$ id
uid=1000(orkhan) gid=1000(orkhan)
```

### Konteynerdə (default):
```bash
$ docker compose exec app id
uid=82(www-data) gid=82(www-data)
```

### Bind Mount Nəticəsi:
```yaml
# docker-compose.yml
services:
  app:
    volumes:
      - .:/var/www/html           # Host .-sini konteynerə mount et
```

Konteynerdə `www-data` (UID 82) `/var/www/html`-də fayl yaratmağa çalışır → **UID 1000 owns it, UID 82 can't write**:
```
[ErrorException] file_put_contents(/var/www/html/storage/logs/laravel.log): Permission denied
```

## Həll 1: Build-Time UID Match (Ən Təmiz)

Dockerfile-da `www-data`-nın UID-ni host UID-ə uyğunlaşdır.

### Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

# Alpine-də www-data UID-i dəyiş
RUN deluser www-data 2>/dev/null; \
    addgroup -g ${GID} -S www-data && \
    adduser -u ${UID} -D -S -G www-data www-data

# Debian variantı:
# RUN groupmod -g ${GID} www-data && \
#     usermod -u ${UID} -g ${GID} www-data

USER www-data
WORKDIR /var/www/html
```

### Build

```bash
docker build --build-arg UID=$(id -u) --build-arg GID=$(id -g) -t myapp:dev .
```

### Compose-da

```yaml
services:
  app:
    build:
      context: .
      args:
        UID: ${UID:-1000}
        GID: ${GID:-1000}
    volumes:
      - .:/var/www/html
```

`.env`-də:
```
UID=1000
GID=1000
```

Və ya komanda vaxtı:
```bash
UID=$(id -u) GID=$(id -g) docker compose up -d
```

### Nəticə:
- Konteynerdə `www-data` UID 1000
- Host-da developer UID 1000
- Bind mount-da hər iki tərəf eyni UID görür — yazma işləyir

### Pozitiv tərəf:
- Təmiz, əlavə script lazım deyil
- Production image ilə də eyni user istifadə edilə bilər

### Neqativ tərəf:
- Hər developer fərqli UID-ə malik ola bilər (komandada)
- macOS-da Docker Desktop UID-i idarə edir, problem az olur

## Həll 2: Runtime UID Match (Entrypoint-də)

`gosu` və ya `su-exec` ilə runtime-da user dəyiş:

### Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache su-exec

COPY docker/entrypoint-fix-perms.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint-fix-perms.sh

ENTRYPOINT ["/usr/local/bin/entrypoint-fix-perms.sh"]
CMD ["php-fpm"]
```

### `entrypoint-fix-perms.sh`

```bash
#!/bin/sh
set -e

# HOST_UID və HOST_GID env-dən oxu
HOST_UID=${HOST_UID:-1000}
HOST_GID=${HOST_GID:-1000}

# www-data-nı yenidən yarat düzgün UID/GID ilə
if [ "$(id -u www-data)" != "$HOST_UID" ]; then
    deluser www-data 2>/dev/null || true
    addgroup -g $HOST_GID -S www-data
    adduser -u $HOST_UID -D -S -G www-data www-data
fi

# Storage və cache mülkiyyəti
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# su-exec ilə www-data olaraq əmr işlət
exec su-exec www-data "$@"
```

### Compose

```yaml
services:
  app:
    environment:
      HOST_UID: ${UID:-1000}
      HOST_GID: ${GID:-1000}
    volumes:
      - .:/var/www/html
```

### Pozitiv:
- Build-time-da UID təyin etmək lazım deyil
- Hər developer öz UID-i ilə işləyir

### Neqativ:
- Konteyner root kimi başlayır (icazəni dəyişmək üçün)
- Entrypoint daha mürəkkəb

## Həll 3: Named Volume İstifadə Et (Production)

Bind mount yalnız dev-də lazımdır. Production-da **COPY** edib storage üçün named volume istifadə et:

```yaml
services:
  app:
    image: myapp:v1      # Kod image-də
    volumes:
      - storage:/var/www/html/storage

volumes:
  storage:
```

**Named volume Docker-in idarə etdiyi volume-dur** — UID problemi yoxdur, konteynerə öz UID-i ilə yaradılır.

Build-time-da storage qovluğu düzgün hazırlanır:
```dockerfile
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
```

## Hansı Qovluqlar Yaz-Oxu İcazəsi Tələb Edir?

Laravel-də:

```
storage/
├── app/
│   └── public/          ← user upload-ları, yazılabilən
├── framework/
│   ├── cache/           ← Laravel cache
│   ├── sessions/        ← session faylları
│   ├── testing/
│   └── views/           ← compiled blade
└── logs/                ← laravel.log

bootstrap/
└── cache/               ← config:cache, route:cache
```

Bunlar `www-data`-nın yaza biləcəyi olmalıdır. Kod (controller-lər və s.) yalnız oxuma icazəsi olması kifayətdir.

```dockerfile
# Production Dockerfile-da:
COPY --chown=www-data:www-data . /var/www/html

RUN chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
```

**Qaydalar:**
- `755` — yalnız owner yaza bilər, başqaları oxuyur
- `775` — owner və group yaza bilər (nginx + php-fpm hər ikisi `www-data` group-da)
- `777` — **heç vaxt production-da!** (security risk)

## `chown -R` Hər Başlanğıcda — Yavaş!

Entrypoint-də:
```sh
chown -R www-data:www-data /var/www/html/storage
```

Kiçik layihədə tez, amma storage-də 10000+ log fayl varsa — 5-30 saniyə start time artır.

### Həll 1: Yalnız dəyişəni `chown`

```sh
# Fayl sahibliyi artıq düzdürsə keç
if [ "$(stat -c %u /var/www/html/storage)" != "$(id -u www-data)" ]; then
    chown -R www-data:www-data /var/www/html/storage
fi
```

### Həll 2: Build-time-da bir dəfə

Production-da bind mount yoxdur — build-də chown et, bir dəfə, əbədi.

### Həll 3: `fsgroup` (Kubernetes)

```yaml
spec:
  template:
    spec:
      securityContext:
        fsGroup: 82           # www-data GID
        fsGroupChangePolicy: "OnRootMismatch"    # Yalnız dəyişdiyində
```

K8s volume-u group 82 olaraq mount edir — avtomatik icazə verir.

## Alpine vs Debian UID Fərqi

| Image | www-data UID | www-data GID |
|-------|--------------|---------------|
| `php:8.3-fpm-alpine` | 82 | 82 |
| `php:8.3-fpm` (Debian) | 33 | 33 |
| `nginx:alpine` | 101 (nginx) | 101 |

**Qarışıqlıq yaranır** nginx və php-fpm fərqli user-lər istifadə edəndə:
- nginx `nginx` user (UID 101)
- php-fpm `www-data` user (UID 82)

**Həll:** Hər ikisini eyni UID-ə çevir və ya eyni group-a qoş:

```dockerfile
# Nginx image-də
RUN addgroup -g 82 www-data && \
    adduser -u 82 -D -S -G www-data www-data && \
    sed -i 's/user  nginx/user  www-data/' /etc/nginx/nginx.conf
```

## SELinux / AppArmor (RHEL, Fedora)

RHEL-based sistemlərdə SELinux bind mount-u blok edə bilər:
```
Permission denied: even with correct chmod/chown
```

**Həll:** Volume-a `:z` və ya `:Z` flag əlavə et:
```yaml
volumes:
  - .:/var/www/html:z          # shared label
  - .:/var/www/html:Z          # private label
```

Və ya SELinux-u dev-də disable: `sudo setenforce 0`.

## macOS — Docker Desktop

macOS-da bind mount performans yavaşdır (osxfs, VirtioFS, gRPC-FUSE). Icazə adətən problem deyil — Docker Desktop UID-i idarə edir.

**Performance təkmilləşdirmə:**
```yaml
volumes:
  - .:/var/www/html:cached    # macOS cache flag
  # və ya delegated — konteyner dəyişiklikləri host-a yavaş yayılır
```

Yeni: **Mutagen** sync, ya da Docker Desktop VirtioFS (default).

## Windows WSL2

WSL2-də fayl-ları `\\wsl$` altında saxla, Windows fayl sistemində (`C:\Users`) yox — 10x daha sürətli bind mount.

```bash
# WSL2-də düzgün path
cd ~/projects/myapp        # /home/user/...
# YANLIŞ:
# cd /mnt/c/Users/me/myapp
```

## User Namespace Remapping (Advanced)

Docker Engine-də user namespace aktiv et: konteyner root host-da UID 100000+-ə map olunur. Security bonus, UID problemi həll olur.

`/etc/docker/daemon.json`:
```json
{
  "userns-remap": "default"
}
```

**Dezavantaj:** Bəzi volume-lar işləməyə bilər, konfiqurasiya çətindir. Production server-lərdə tövsiyə, dev-də yox.

## Tipik Səhvlər (Gotchas)

### 1. `chmod 777 -R storage`

Developer səyimlərin heç bitmədiyini görüb 777 edir. **Security disaster** — hər kəs yaza bilər.

**Həll:** `775` və düzgün UID match.

### 2. `chown` host-da, container-də deyil

```bash
# Host-da:
sudo chown -R www-data:www-data storage
```

Host-da `www-data` UID 33, container-də UID 82 — uyğunsuz.

**Həll:** Container daxilindən chown et, və ya host-da `sudo chown -R 1000:1000 storage` (match container user).

### 3. Git-clone sonra chown itir

Git clone host user-in UID-i ilə edir, container-də start-da chown etməlisən.

**Həll:** Entrypoint-də ehtiyat olaraq chown.

### 4. `.env` faylı root-undur

Host-da `.env` yaratdın amma konteyner root-la yaradıb, oxuya bilmirsən.

**Həll:** Konteyner `www-data`-da işləməlidir, root-da yox.

### 5. Laravel `storage:link` problemi

`public/storage` → `../storage/app/public` symlink host-da yaradılır, amma host path fərqlidir.

**Həll:** `storage:link` konteyner daxilindən işlət:
```bash
docker compose exec app php artisan storage:link
```

### 6. Docker-in-Docker UID dolaşıq

CI/CD-də nested Docker → UID qarışıq. Production çoxsəviyyəli user mapping.

**Həll:** `fsgroup` (K8s), rootless Docker (22-ci fayl).

### 7. Composer Install yanlış User

Build-də `root` işləyir amma final image `www-data` → vendor qovluğu `root:root`-dur, `www-data` oxuya bilir amma update edə bilməz.

**Həll:**
```dockerfile
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
```

## Cheat Sheet

```bash
# Host user UID/GID
id -u  # 1000
id -g  # 1000

# Container user UID/GID
docker compose exec app id

# Container user dəyişdir
docker compose exec -u 1000 app bash
docker compose exec -u root app chown -R www-data:www-data storage

# Build-time UID
docker build --build-arg UID=$(id -u) --build-arg GID=$(id -g) .

# Volume sahibliyi yoxla
docker compose exec app ls -la /var/www/html/storage

# Permission sıfırla (acil hal)
docker compose exec -u root app sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache'
```

## Interview sualları

- **Q:** `Permission denied: storage/logs/laravel.log` niyə olur?
  - Konteynerdə PHP-FPM `www-data` (UID 82) altında işləyir. Host-da developer UID 1000. Bind mount UID fərqli olduğu üçün `www-data` yaza bilmir.

- **Q:** Ən təmiz həll?
  - Build-time-da `www-data`-nın UID-ni host UID-ə uyğunlaşdır: `ARG UID=1000` + `usermod -u ${UID} www-data`. Compose-də `--build-arg UID=$(id -u)`.

- **Q:** Production-da bu problem varmı?
  - Yox — production-da bind mount istifadə olunmur, kod image-də COPY olunub. Storage named volume olur, container öz UID-i ilə yaradır.

- **Q:** `chmod 777` niyə pis?
  - Hər kəs yaza bilər — container hack olunsa, bütün fayllar modifikasiya oluna bilər. Laravel üçün `775` kifayətdir (owner + group).

- **Q:** Kubernetes-də bu problemi necə həll edirsiz?
  - `securityContext.fsGroup: 82` — volume `www-data` group-a mount olunur, avtomatik yaza bilir. `fsGroupChangePolicy: OnRootMismatch` performans üçün.

- **Q:** Nginx və PHP-FPM fərqli user-lərdədir — niyə?
  - Default Alpine image-lərində nginx UID 101, www-data UID 82. İkisini eyni group-a qoy və ya nginx-i də www-data user-lə işlət.
