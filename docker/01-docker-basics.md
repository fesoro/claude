# Docker Əsasları (Docker Basics)

## Nədir? (What is it?)

Docker, tətbiqləri konteynerlərdə paketləmək, göndərmək və işlətmək üçün açıq mənbəli platformadır. Konteyner — tətbiqi bütün asılılıqları ilə birlikdə izolə edilmiş mühitdə işlədən yüngül, portativ vahiddir.

Docker 2013-cü ildə Solomon Hykes tərəfindən yaradılıb. Linux kernel xüsusiyyətlərindən (namespaces, cgroups, UnionFS) istifadə edərək prosesləri izolə edir.

### Konteyner vs Virtual Maşın (Container vs VM)

```
┌─────────────────────────────────────────────────────┐
│              Virtual Maşınlar (VMs)                  │
├─────────────┬─────────────┬─────────────────────────┤
│   App A     │   App B     │   App C                 │
│   Bins/Libs │   Bins/Libs │   Bins/Libs             │
│   Guest OS  │   Guest OS  │   Guest OS              │
├─────────────┴─────────────┴─────────────────────────┤
│              Hypervisor (VMware, KVM)                │
├─────────────────────────────────────────────────────┤
│              Host OS                                 │
├─────────────────────────────────────────────────────┤
│              Infrastructure                          │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│              Konteynerlər (Containers)               │
├─────────────┬─────────────┬─────────────────────────┤
│   App A     │   App B     │   App C                 │
│   Bins/Libs │   Bins/Libs │   Bins/Libs             │
├─────────────┴─────────────┴─────────────────────────┤
│              Docker Engine                           │
├─────────────────────────────────────────────────────┤
│              Host OS                                 │
├─────────────────────────────────────────────────────┤
│              Infrastructure                          │
└─────────────────────────────────────────────────────┘
```

| Xüsusiyyət | Konteyner | Virtual Maşın |
|-------------|-----------|---------------|
| Başlama vaxtı | Saniyələr | Dəqiqələr |
| Ölçü | MB-lar | GB-lar |
| Performans | Native-ə yaxın | Overhead var |
| İzolyasiya | Proses səviyyəsi | Tam hardware səviyyəsi |
| OS | Host kernel paylaşır | Öz kernel-i var |
| Portativlik | Çox yüksək | Orta |
| Sıxlıq | Bir host-da minlərlə | Bir host-da onlarla |

## Əsas Konseptlər (Key Concepts)

### Docker Engine Arxitekturası

Docker Engine üç əsas komponentdən ibarətdir:

1. **Docker Daemon (dockerd)** — Arka planda işləyən proses. Image-ləri, konteynerləri, şəbəkələri və volume-ləri idarə edir.
2. **Docker CLI (docker)** — İstifadəçinin daemon ilə əlaqə qurduğu komanda xətti aləti.
3. **REST API** — CLI və daemon arasında əlaqəni təmin edən API.

```
┌──────────┐     REST API     ┌──────────────┐
│ Docker   │ ───────────────> │ Docker       │
│ CLI      │                  │ Daemon       │
└──────────┘                  │ (dockerd)    │
                              │              │
                              │ ┌──────────┐ │
                              │ │containerd│ │
                              │ └──────────┘ │
                              │ ┌──────────┐ │
                              │ │  runc    │ │
                              │ └──────────┘ │
                              └──────────────┘
```

- **containerd** — Konteyner lifecycle-ını idarə edən yüksək səviyyəli runtime
- **runc** — OCI standartına uyğun aşağı səviyyəli konteyner runtime

### Image vs Konteyner

**Image** — Konteyner yaratmaq üçün şablon (read-only). Layer-lərdən ibarətdir.

```
┌─────────────────────────┐
│  Yazıla bilən layer     │  <-- Konteyner layer-i
├─────────────────────────┤
│  PHP extensions         │  <-- Image layer 4
├─────────────────────────┤
│  PHP 8.3                │  <-- Image layer 3
├─────────────────────────┤
│  apt-get packages       │  <-- Image layer 2
├─────────────────────────┤
│  Ubuntu 22.04           │  <-- Base layer 1
└─────────────────────────┘
```

**Konteyner** — Image-in işləyən nüsxəsi. Image + yazıla bilən layer.

```bash
# Image-dən konteyner yaratmaq
docker run nginx          # image-dən konteyner yaradır və işlədir
docker create nginx       # konteyner yaradır amma işlətmir
```

### Docker Hub

Docker Hub — Docker image-lərinin saxlandığı rəsmi bulud registry-dir.

```bash
# Docker Hub-dan image çəkmək
docker pull php:8.3-fpm
docker pull mysql:8.0
docker pull redis:7-alpine

# Image axtarmaq
docker search laravel

# Docker Hub-a giriş
docker login
docker push myusername/myapp:v1.0
```

## Praktiki Nümunələr (Practical Examples)

### Əsas Docker Əmrləri

#### Konteyner İşlətmə (docker run)

```bash
# Sadə konteyner işlətmək
docker run hello-world

# Arka planda işlətmək (-d = detached)
docker run -d nginx

# Port mapping ilə (-p host:container)
docker run -d -p 8080:80 nginx

# Ad vermək (--name)
docker run -d --name my-nginx -p 8080:80 nginx

# Mühit dəyişənləri ilə (-e)
docker run -d --name my-mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=laravel \
  -p 3306:3306 \
  mysql:8.0

# Volume mount ilə (-v)
docker run -d --name my-nginx \
  -v /home/user/html:/usr/share/nginx/html \
  -p 8080:80 \
  nginx

# İnteraktiv mod (-it)
docker run -it ubuntu:22.04 /bin/bash

# Avtomatik silmək (--rm) — konteyner dayandıqda silinir
docker run --rm -it php:8.3-cli php -v

# Resurs limiti ilə
docker run -d --name my-app \
  --memory="512m" \
  --cpus="1.5" \
  nginx
```

#### Konteynerləri Görmək (docker ps)

```bash
# İşləyən konteynerləri görmək
docker ps

# Bütün konteynerləri görmək (dayandırılmış daxil)
docker ps -a

# Yalnız ID-ləri görmək
docker ps -q

# Format ilə
docker ps --format "table {{.ID}}\t{{.Names}}\t{{.Status}}\t{{.Ports}}"

# Son N konteyner
docker ps -n 5

# Ölçüləri görmək
docker ps -s
```

#### Konteyneri Dayandırmaq (docker stop/kill)

```bash
# Graceful dayandırma (SIGTERM, sonra SIGKILL)
docker stop my-nginx

# Dərhal dayandırma (SIGKILL)
docker kill my-nginx

# Timeout ilə dayandırma
docker stop -t 30 my-nginx

# Bütün konteynerləri dayandırmaq
docker stop $(docker ps -q)
```

#### Konteyneri Silmək (docker rm)

```bash
# Bir konteyner silmək
docker rm my-nginx

# İşləyən konteyneri zorla silmək
docker rm -f my-nginx

# Bütün dayandırılmış konteynerləri silmək
docker container prune

# Bütün konteynerləri silmək
docker rm -f $(docker ps -aq)
```

#### Konteynerdə Əmr İcra Etmək (docker exec)

```bash
# Konteyner daxilində bash açmaq
docker exec -it my-nginx /bin/bash

# Bir əmr icra etmək
docker exec my-nginx nginx -t

# Root istifadəçi kimi
docker exec -u root -it my-app /bin/bash

# Mühit dəyişəni ilə
docker exec -e MY_VAR=hello my-app env

# PHP/Laravel nümunəsi
docker exec -it laravel-app php artisan migrate
docker exec -it laravel-app php artisan cache:clear
docker exec -it laravel-app composer install
```

#### Logları Görmək (docker logs)

```bash
# Konteyner loglarını görmək
docker logs my-nginx

# Son N sətir
docker logs --tail 100 my-nginx

# Canlı izləmə (follow)
docker logs -f my-nginx

# Zaman damğası ilə
docker logs -t my-nginx

# Müəyyən vaxtdan sonra
docker logs --since "2024-01-01T00:00:00" my-nginx
docker logs --since 30m my-nginx
```

### Konteyner Lifecycle-ı (Həyat Dövrü)

```
  docker create         docker start         docker pause
  ┌─────────┐          ┌─────────┐          ┌─────────┐
  │ Created │ ───────> │ Running │ ───────> │ Paused  │
  └─────────┘          └─────────┘          └─────────┘
       │                    │                     │
       │                    │ docker stop    docker unpause
       │                    │                     │
       │                    v                     │
       │               ┌─────────┐                │
       │               │ Stopped │ <──────────────┘
       │               │(Exited) │
       │               └─────────┘
       │                    │
       │                    │ docker rm
       │                    v
       │               ┌─────────┐
       └──────────────>│ Deleted │
           docker rm   └─────────┘
```

```bash
# Tam lifecycle nümunəsi
docker create --name lifecycle-demo nginx    # Created
docker start lifecycle-demo                   # Running
docker pause lifecycle-demo                   # Paused
docker unpause lifecycle-demo                 # Running
docker stop lifecycle-demo                    # Exited
docker start lifecycle-demo                   # Running (yenidən)
docker rm -f lifecycle-demo                   # Deleted
```

### Image Əmrləri

```bash
# Image-ləri siyahılamaq
docker images
docker image ls

# Image çəkmək
docker pull php:8.3-fpm-alpine

# Image silmək
docker rmi nginx:latest
docker image rm nginx:latest

# İstifadə olunmayan image-ləri silmək
docker image prune -a

# Image məlumatı
docker image inspect php:8.3-fpm

# Image tarixçəsi (layer-lər)
docker image history php:8.3-fpm

# Image tag-ləmək
docker tag myapp:latest myregistry.com/myapp:v1.0

# Image ölçüsü
docker images --format "{{.Repository}}:{{.Tag}} {{.Size}}"
```

### Sistem Təmizliyi

```bash
# Bütün istifadə olunmayan resursları silmək
docker system prune

# Volume-lar da daxil
docker system prune --volumes

# Disk istifadəsini görmək
docker system df

# Ətraflı disk istifadəsi
docker system df -v
```

## PHP/Laravel ilə İstifadə (Usage with PHP/Laravel)

```bash
# PHP CLI konteyneri ilə sürətli test
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php -r "echo 'Hello Laravel!';"

# Composer əmrlərini Docker ilə işlətmək
docker run --rm -v $(pwd):/app -w /app composer:latest composer install

# Laravel proyekti yaratmaq
docker run --rm -v $(pwd):/app -w /app composer:latest \
  composer create-project laravel/laravel myapp

# PHP versiyasını yoxlamaq
docker run --rm php:8.3-cli php -v

# PHP extension-larını görmək
docker run --rm php:8.3-cli php -m

# Laravel artisan əmrləri
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php artisan key:generate

# MySQL konteyneri ilə Laravel
docker run -d --name laravel-mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=laravel \
  -e MYSQL_USER=laravel \
  -e MYSQL_PASSWORD=secret \
  -p 3306:3306 \
  mysql:8.0

# Redis konteyneri
docker run -d --name laravel-redis \
  -p 6379:6379 \
  redis:7-alpine
```

## Interview Sualları (Interview Questions)

### 1. Docker nədir və niyə istifadə olunur?
**Cavab:** Docker konteynerləşdirmə platformasıdır. Tətbiqləri bütün asılılıqları ilə birlikdə izolə edilmiş mühitdə paketləyir. Bu, "mənim maşınımda işləyir" problemini həll edir, development və production mühitlərini eyniləşdirir, tez deployment təmin edir.

### 2. Konteyner və VM arasında fərq nədir?
**Cavab:** VM tam OS işlədir, hypervisor lazımdır, GB-larla yer tutur, dəqiqələrlə başlayır. Konteyner host OS kernel-ini paylaşır, MB-larla yer tutur, saniyələrlə başlayır, daha çox sıxlıq təmin edir. VM daha güclü izolyasiya verir, konteyner daha yüngül və sürətlidir.

### 3. Docker image və konteyner arasında fərq nədir?
**Cavab:** Image — read-only şablondur, layer-lərdən ibarətdir. Konteyner — image-in işləyən nüsxəsidir, üstündə yazıla bilən layer əlavə olunur. Bir image-dən çoxlu konteyner yaradıla bilər. Analogiya: image = sinif, konteyner = obyekt.

### 4. Docker Engine-in komponentləri hansılardır?
**Cavab:** Docker CLI (istifadəçi interfeysi), Docker Daemon/dockerd (arka plan prosesi, API-ni dinləyir), containerd (konteyner lifecycle idarəsi), runc (aşağı səviyyəli OCI runtime). CLI REST API vasitəsilə daemon-a sorğu göndərir.

### 5. `docker run` əmri nə edir?
**Cavab:** `docker run` əslində üç əmri birləşdirir: `docker pull` (image yoxdursa çəkir), `docker create` (konteyner yaradır), `docker start` (konteyneri işlədir). `-d` flaqı detached modda, `-it` isə interaktiv modda işlədir.

### 6. `docker stop` və `docker kill` arasında fərq nədir?
**Cavab:** `docker stop` əvvəl SIGTERM siqnalı göndərir, prosesə graceful shutdown imkanı verir (default 10 saniyə). Əgər vaxt bitərsə SIGKILL göndərir. `docker kill` dərhal SIGKILL göndərir, proses dərhal dayandırılır.

### 7. Docker layer caching nə üçün vacibdir?
**Cavab:** Docker hər Dockerfile instruksiyasını ayrı layer kimi saxlayır. Əgər bir layer dəyişməyibsə, cache-dən istifadə edir. Bu, build vaxtını əhəmiyyətli dərəcədə azaldır. Ona görə az dəyişən instruksiyalar əvvəl, tez dəyişən instruksiyalar (COPY . .) sonra yazılmalıdır.

### 8. Dangling image nədir?
**Cavab:** Dangling image — heç bir tag-ı olmayan image-dir (`<none>:<none>` kimi görünür). Adətən yeni build zamanı köhnə image-in tag-ı yeni image-ə keçdikdə yaranır. `docker image prune` ilə təmizlənir.

### 9. Docker Hub nədir?
**Cavab:** Docker Hub — Docker image-lərinin saxlandığı default bulud registry-dir. Rəsmi image-lər (nginx, php, mysql), icma image-ləri və özəl image-lər saxlamaq olur. `docker pull` default olaraq Docker Hub-dan çəkir.

### 10. `docker exec` və `docker attach` arasında fərq nədir?
**Cavab:** `docker exec` konteynerdə yeni proses başladır (məs. yeni bash shell). `docker attach` konteynerin əsas prosesinə (PID 1) qoşulur. `exec` daha təhlükəsizdir çünki əsas prosesə təsir etmir; `attach`-dan çıxmaq konteyneri dayandıra bilər.

## Best Practices

1. **Bir konteyner — bir proses.** Hər konteyner bir məsuliyyət daşımalıdır.
2. **Rəsmi image-lərdən istifadə edin.** Təhlükəsiz və optimize olunmuş base image-lər seçin.
3. **Tag-lardan istifadə edin.** `latest` əvəzinə konkret versiya göstərin (`php:8.3-fpm`, `mysql:8.0`).
4. **Konteynerləri əlaqədar (ephemeral) hesab edin.** Məlumatları volume-larda saxlayın, konteynerlər istənilən vaxt silinə bilər.
5. **Resource limit-ləri qoyun.** `--memory` və `--cpus` ilə resurs limitlərini təyin edin.
6. **Logları stdout/stderr-ə yazın.** Fayla yazmaq əvəzinə standart axınlara yazın.
7. **Lazımsız prosesləri işlətməyin.** SSH server, cron daemon və s. konteynerdə lazım deyil (əksər hallarda).
8. **`.dockerignore` istifadə edin.** Lazımsız faylların image-ə düşməsinin qarşısını alın.
9. **Health check əlavə edin.** Konteynerin sağlam işlədiyini yoxlamaq üçün.
10. **Non-root istifadəçi istifadə edin.** Təhlükəsizlik üçün root-dan qaçın.
