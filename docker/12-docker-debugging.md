# Docker Debugging (Docker-da Debug Etmə)

## Nədir? (What is it?)

Docker debugging — konteynerləşdirilmiş tətbiqlərdə yaranan problemləri tapmaq və həll etmək prosesidir. Konteynerlər izolə olduğu üçün ənənəvi debug üsulları fərqlidir. Docker bir sıra güclü alətlər təqdim edir: `docker exec`, `docker inspect`, `docker stats`, `docker logs` və s.

## Əsas Konseptlər (Key Concepts)

### 1. docker exec — Konteyner İçində Əmr İcra Etmə

```bash
# Konteynerdə interaktiv shell açmaq
docker exec -it myapp bash
docker exec -it myapp sh    # Alpine image-lərdə (bash yoxdur)

# Tək əmr icra etmək
docker exec myapp php artisan tinker
docker exec myapp cat /etc/hosts
docker exec myapp env

# Fərqli user ilə
docker exec -u root myapp whoami
docker exec -u www-data myapp id

# Environment variable ilə
docker exec -e DEBUG=true myapp php artisan config:show

# Working directory ilə
docker exec -w /var/www/html myapp ls -la
```

### 2. docker inspect — Ətraflı Məlumat

```bash
# Konteynerin tam məlumatı (JSON)
docker inspect myapp

# IP ünvanı
docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' myapp

# Mount-lar
docker inspect -f '{{json .Mounts}}' myapp | jq

# Environment variables
docker inspect -f '{{json .Config.Env}}' myapp | jq

# Port mapping
docker inspect -f '{{json .NetworkSettings.Ports}}' myapp | jq

# Health status
docker inspect -f '{{json .State.Health}}' myapp | jq

# Restart count
docker inspect -f '{{.RestartCount}}' myapp

# Konteyner state
docker inspect -f '{{json .State}}' myapp | jq
# {"Status":"running","Running":true,"Pid":12345,...}

# Image ID
docker inspect -f '{{.Image}}' myapp

# Konteyner yaranma tarixi
docker inspect -f '{{.Created}}' myapp
```

### 3. docker stats — Real-time Resurs İstifadəsi

```bash
# Bütün konteynerlərin resurs istifadəsi (real-time)
docker stats

# Müəyyən konteyner
docker stats myapp

# Bir dəfəlik snapshot (stream olmadan)
docker stats --no-stream

# Format ilə
docker stats --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}"

# Nümunə çıxış:
# NAME       CPU %   MEM USAGE / LIMIT     NET I/O
# app        2.50%   128MiB / 512MiB       10MB / 5MB
# mysql      5.30%   256MiB / 1GiB         50MB / 30MB
# redis      0.10%   10MiB / 128MiB        1MB / 500kB
```

### 4. docker events — Real-time Hadisələr

```bash
# Real-time hadisələri izləmək
docker events

# Filtr ilə
docker events --filter type=container
docker events --filter container=myapp
docker events --filter event=die
docker events --filter event=health_status

# Zaman aralığı ilə
docker events --since 1h
docker events --since 2026-04-16T10:00:00

# Format ilə
docker events --format '{{.Time}} {{.Actor.Attributes.name}} {{.Action}}'
```

### 5. docker logs — Log Analizi

```bash
# Bütün log-lar
docker logs myapp

# Son 50 sətir
docker logs --tail 50 myapp

# Real-time
docker logs -f myapp

# Timestamp ilə
docker logs -t myapp

# Son 30 dəqiqəlik log-lar
docker logs --since 30m myapp

# Error-ları filter etmək
docker logs myapp 2>&1 | grep -i error

# PHP Fatal Error axtarış
docker logs myapp 2>&1 | grep "Fatal error"
```

### 6. docker top — Konteyner Prosesləri

```bash
# Konteyner daxilindəki proseslər
docker top myapp

# Ətraflı (ps formatı ilə)
docker top myapp -aux

# Nümunə çıxış:
# PID   USER   COMMAND
# 1     root   php-fpm: master process
# 15    www    php-fpm: pool www
# 16    www    php-fpm: pool www
```

### 7. docker diff — Fayl Sistemi Dəyişiklikləri

```bash
# Konteynerdə dəyişdirilmiş fayllar
docker diff myapp

# A = Added (əlavə edilmiş)
# C = Changed (dəyişdirilmiş)
# D = Deleted (silinmiş)
# Nümunə:
# C /var/www/html/storage/logs
# A /var/www/html/storage/logs/laravel.log
# C /tmp
```

## Praktiki Nümunələr (Practical Examples)

### Crashed Konteynerləri Debug Etmək

```bash
# 1. Konteyner statusunu yoxla
docker ps -a --filter name=myapp
# STATUS: Exited (137) 5 minutes ago  ← OOM killed
# STATUS: Exited (1) 2 minutes ago    ← Error ilə çıxdı
# STATUS: Exited (0)                  ← Normal çıxış

# Exit code-ların mənası:
# 0   = Normal çıxış
# 1   = Application error
# 137 = SIGKILL (OOM killer və ya docker kill)
# 139 = SIGSEGV (Segmentation fault)
# 143 = SIGTERM (docker stop)

# 2. Son log-lara bax
docker logs --tail 100 myapp

# 3. State-i yoxla
docker inspect -f '{{json .State}}' myapp | jq
# OOMKilled: true olub-olmadığını yoxla

# 4. Crashed konteynerdə shell açmaq
# (konteyner dayandırılmış vəziyyətdə olsa belə faylları görmək)
docker commit myapp debug-image
docker run -it --rm debug-image sh

# 5. Entrypoint-u override edərək debug etmək
docker run -it --rm --entrypoint sh myapp:latest
```

### Network Debug

```bash
# Konteynerin şəbəkə məlumatı
docker inspect -f '{{json .NetworkSettings}}' myapp | jq

# Konteyner daxilindən şəbəkə testi
docker exec myapp ping -c 3 mysql
docker exec myapp nslookup mysql
docker exec myapp curl -v http://nginx:80

# Port-ların dinlənməsi
docker exec myapp netstat -tlnp
docker exec myapp ss -tlnp

# Şəbəkədəki bütün konteynerlər
docker network inspect bridge --format '{{json .Containers}}' | jq

# DNS resolution
docker exec myapp cat /etc/resolv.conf

# iptables qaydaları (host-da)
sudo iptables -t nat -L -n | grep docker
```

### Memory və CPU Debug

```bash
# Real-time resurs izləmə
docker stats myapp --no-stream

# Konteyner memory limit
docker inspect -f '{{.HostConfig.Memory}}' myapp
# 0 = limitsiz

# cgroup memory limit yoxlama (konteyner daxilindən)
docker exec myapp cat /sys/fs/cgroup/memory.max

# Memory istifadəsinin ətraflı analizi
docker exec myapp cat /proc/meminfo

# PHP memory limit
docker exec myapp php -i | grep memory_limit

# PHP-FPM proseslərinin memory istifadəsi
docker exec myapp ps aux --sort=-%mem | head -20
```

### Disk Space Debug

```bash
# Docker disk istifadəsi
docker system df
# TYPE            TOTAL   ACTIVE  SIZE    RECLAIMABLE
# Images          15      5       5.5GB   3.2GB (58%)
# Containers      8       3       200MB   150MB (75%)
# Local Volumes   10      4       2GB     1.5GB (75%)
# Build Cache     50      0       1.5GB   1.5GB (100%)

# Ətraflı
docker system df -v

# Lazımsızları təmizləmək
docker system prune           # Dayandırılmış konteyner, unused network, dangling image
docker system prune -a        # Bütün unused image-lər daxil
docker system prune --volumes # Volume-lər daxil

# Konteyner daxilindəki disk istifadəsi
docker exec myapp df -h
docker exec myapp du -sh /var/www/html/storage/*
```

### PHP/Laravel Xüsusi Debug

```bash
# PHP versiyası və module-lar
docker exec myapp php -v
docker exec myapp php -m

# PHP konfiqurasiyası
docker exec myapp php -i
docker exec myapp php -i | grep -i "upload_max_filesize"
docker exec myapp php -i | grep -i "memory_limit"
docker exec myapp php -i | grep -i "opcache"

# Laravel environment
docker exec myapp php artisan env
docker exec myapp php artisan config:show database

# Laravel route-ları
docker exec myapp php artisan route:list

# Database əlaqəsi
docker exec myapp php artisan db:show
docker exec myapp php artisan migrate:status

# Queue status
docker exec myapp php artisan queue:monitor redis:default

# Tinker ilə debug
docker exec -it myapp php artisan tinker
```

### strace ilə Debug

```bash
# strace quraşdırmaq (Alpine)
docker exec -u root myapp apk add --no-cache strace

# strace quraşdırmaq (Debian/Ubuntu)
docker exec -u root myapp apt-get update && apt-get install -y strace

# PHP prosesini trace etmək
docker exec -u root myapp strace -p $(pgrep -f "php-fpm: pool") -f -e trace=file

# System call-ları izləmək
docker exec -u root myapp strace -c php artisan route:list

# QEYD: strace üçün SYS_PTRACE capability lazımdır
docker run --cap-add=SYS_PTRACE myapp:latest
```

### Debug Konteyner Yaratmaq

```bash
# Əsas konteynerə əlavə debug alətləri ilə container
docker run -it --rm \
    --network container:myapp \
    --pid container:myapp \
    nicolaka/netshoot \
    bash

# netshoot daxilində:
# tcpdump, curl, dig, nslookup, iftop, drill, netstat, ss, ip, iptables
# mtr, traceroute, nmap, ping, iperf3, ethtool

# Konteyner şəbəkəsini sniff etmək
tcpdump -i any port 80

# DNS test
dig mysql +short
```

### Docker Compose Debug

```bash
# Bütün service-lərin statusu
docker compose ps

# Bir service-in log-ları
docker compose logs app
docker compose logs -f --tail 100 app

# Config-i yoxlamaq (final nəticə)
docker compose config

# Hadisələr
docker compose events

# Service-i yenidən build etmək
docker compose build --no-cache app

# Environment variable-ları görmək
docker compose exec app env

# Bir service-ə shell
docker compose exec app bash
```

## PHP/Laravel ilə İstifadə (Usage with PHP/Laravel)

### Laravel Telescope Docker-da

```php
// Telescope yalnız local/debug mühitdə aktiv
// app/Providers/TelescopeServiceProvider.php
public function register()
{
    if (! $this->app->environment('local', 'testing')) {
        return;
    }

    $this->app->register(TelescopeServiceProvider::class);
}
```

### Xdebug Docker-da

```dockerfile
# Development image-ə Xdebug əlavə etmək
FROM php:8.3-fpm-alpine AS development

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
```

```ini
; docker/php/xdebug.ini
[xdebug]
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.log=/tmp/xdebug.log
xdebug.idekey=PHPSTORM
```

```yaml
# docker-compose.yml
services:
  app:
    build:
      context: .
      target: development
    environment:
      XDEBUG_MODE: debug
    extra_hosts:
      - "host.docker.internal:host-gateway"
```

### Laravel Debug Əmrləri Konteynerdə

```bash
# Database debug
docker exec myapp php artisan db:monitor
docker exec myapp php artisan db:show --counts

# Cache debug
docker exec myapp php artisan cache:clear
docker exec myapp php artisan config:clear

# Permission debug
docker exec myapp ls -la storage/
docker exec myapp ls -la bootstrap/cache/

# Composer debug
docker exec myapp composer diagnose
docker exec myapp composer show --installed
```

## Interview Sualları (Interview Questions)

### S1: Konteyner exit code 137 nə deməkdir?
**C:** Exit code 137 = 128 + 9 (SIGKILL). Bu adətən OOM (Out of Memory) killer tərəfindən konteyner öldürüldüyünü göstərir. `docker inspect` ilə `State.OOMKilled` yoxlanılmalıdır. Həll: memory limit artırmaq, memory leak tapmaq, və ya tətbiqin memory istifadəsini optimallaşdırmaq.

### S2: İşləyən konteynerdə necə debug edirsiniz?
**C:** 1) `docker logs` ilə log-ları yoxlamaq, 2) `docker exec -it bash/sh` ilə konteynerə daxil olmaq, 3) `docker stats` ilə resurs istifadəsinə baxmaq, 4) `docker inspect` ilə konfiqurasiyanı yoxlamaq, 5) lazım gəldikdə `docker top` ilə prosesləri görmək. Network problem üçün netshoot konteyner istifadə etmək.

### S3: Crashed konteynerləri necə debug edirsiniz?
**C:** 1) `docker logs` ilə son log-ları oxumaq, 2) `docker inspect` ilə exit code və OOMKilled yoxlamaq, 3) `docker commit` ilə crashed konteynerdan image yaratmaq və shell açmaq, 4) `docker run --entrypoint sh` ilə konteyneri entrypoint olmadan işə salmaq, 5) `docker events` ilə hadisələrə baxmaq.

### S4: `docker exec` ilə `docker attach` arasında fərq nədir?
**C:** `docker exec` konteynerdə YENİ proses işlədir (məsələn, bash). `docker attach` isə konteynerın əsas prosesinə (PID 1) qoşulur. `attach`-dan `Ctrl+C` ilə çıxdıqda konteyner dayandırıla bilər. `exec` isə əsas prosesə təsir etmir. Debug üçün demək olar ki, həmişə `exec` istifadə olunur.

### S5: Docker-da memory leak-i necə aşkar edirsiniz?
**C:** 1) `docker stats` ilə memory istifadəsini real-time izləmək, 2) Memory sürekli artırsa leak var, 3) Konteyner daxilində `ps aux --sort=-%mem` ilə hansı prosesin memory yediyini tapmaq, 4) PHP üçün `memory_get_usage()` və `memory_get_peak_usage()`, 5) PHP-FPM `pm.max_requests` ilə prosesləri vaxtaşırı yenidən yaratmaq.

### S6: Konteynerlər arasında network problemi necə debug olunur?
**C:** 1) `docker network inspect` ilə konteynerlərin eyni network-da olduğunu yoxlamaq, 2) `docker exec` ilə `ping`, `nslookup`, `curl` ilə test etmək, 3) DNS resolution yoxlamaq (`/etc/resolv.conf`), 4) Port-ların düzgün expose olduğunu yoxlamaq, 5) Netshoot konteyner istifadə etmək (`tcpdump`, `dig`).

### S7: `docker system prune` nə edir və nə vaxt istifadə etmək lazımdır?
**C:** Dayandırılmış konteynerlər, istifadə olunmayan network-lər, dangling (tag-sız) image-lər və build cache-i silir. `-a` flaqı ilə bütün unused image-ləri, `--volumes` ilə unused volume-ləri silir. Disk dolduqda istifadə olunur. DİQQƏT: `--volumes` data itkisinə səbəb ola bilər, ehtiyatlı istifadə edin.

## Best Practices

1. **Həmişə log-lardan başlayın** — `docker logs --tail 100` ilə
2. **Exit code-ları bilin** — 0, 1, 137, 139, 143
3. **`docker inspect` öyrənin** — Go template format ilə istənilən məlumatı çıxarmaq
4. **Debug alətlərini produksiya image-ə qoymayın** — ayrı debug image yaradın
5. **Xdebug yalnız development-də** — produksiyada performans itkisi
6. **netshoot konteyner istifadə edin** — network debug üçün
7. **`docker stats` ilə resursları izləyin** — memory leak, CPU spike
8. **`docker events` istifadə edin** — real-time hadisə izləmə
9. **Health check-lər əlavə edin** — problemləri erkən aşkar etmək üçün
10. **Debug üçün `--cap-add=SYS_PTRACE` istifadə edin** — strace, gdb üçün lazımdır
