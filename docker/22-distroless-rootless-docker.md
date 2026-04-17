# Distroless və Rootless Docker

## Nədir? (What is it?)

**Distroless** — Google tərəfindən yaradılan minimal Docker image-ləridir. Bu image-lər yalnız tətbiqi işlətmək üçün zəruri olan runtime-ı ehtiva edir (məs. PHP, Node.js, Java). Shell (`bash`, `sh`), paket meneceri (`apt`, `apk`), yaxud digər standart Linux alətləri yoxdur.

**Rootless Docker** — Docker daemon və konteynerlərin root olmayan istifadəçi altında işlədilməsidir. Təhlükəsizlik baxımından çox vacibdir — container escape baş verərsə, root imtiyazları alınmır.

## Əsas Konseptlər (Key Concepts)

### 1. Distroless Image-in Üstünlükləri

```
Standart Ubuntu image:     ~70 MB
Alpine image:              ~5 MB
Distroless image:          ~2 MB (yalnız runtime)
```

**Üstünlüklər:**
- **Kiçik attack surface** — shell, package manager yoxdur, hacker-in işlətə biləcəyi alətlər azdır
- **Kiçik image ölçüsü** — tez yüklənir, tez deploy olunur
- **Az CVE** — daha az paket = daha az vulnerability
- **Immutable** — production-da container daxilində heç nə quraşdırıla bilməz

**Çatışmazlıqlar:**
- Debug etmək çətindir (shell yoxdur)
- Troubleshooting üçün `debug` tag-li image variantı lazım olur

### 2. Distroless Image Kateqoriyaları

```
gcr.io/distroless/static         # Yalnız static binary üçün (Go, Rust)
gcr.io/distroless/base           # glibc + libssl (dinamik binary)
gcr.io/distroless/cc             # C/C++ libraries
gcr.io/distroless/java           # OpenJDK runtime
gcr.io/distroless/python3        # Python runtime
gcr.io/distroless/nodejs         # Node.js runtime
```

PHP üçün rəsmi distroless image yoxdur, amma custom build etmək olur.

### 3. Rootless Docker

Normal Docker-də:
```
dockerd → root (UID 0)
container → root (həm host-da həm container-də UID 0)
```

Rootless Docker-də:
```
dockerd → istifadəçi (UID 1000)
container → container daxilində UID 0, host-da UID 100000 (namespace remap)
```

## Praktiki Nümunələr (Practical Examples)

### Distroless ilə Go Application

```dockerfile
# Build stage
FROM golang:1.22 AS build
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o app .

# Distroless final stage
FROM gcr.io/distroless/static-debian12
COPY --from=build /app/app /
USER nonroot:nonroot
EXPOSE 8080
ENTRYPOINT ["/app"]
```

Image ölçüsü: ~15MB (Ubuntu-da eyni tətbiq ~300MB olardı).

### Distroless ilə PHP (Custom Approach)

PHP runtime dinamik yüklənən extension-lardan asılıdır, ona görə distroless-ə uyğunlaşmaq çətindir. Alternativ — chainguard images və ya minimal Alpine:

```dockerfile
# Chainguard PHP (distroless-bənzər, təhlükəsiz)
FROM cgr.dev/chainguard/php:latest

WORKDIR /app
COPY . .

# Composer vendor artefacts
COPY vendor/ /app/vendor/

USER nonroot
ENTRYPOINT ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

### Debug Variantı

Distroless image-də shell olmadığı üçün debug üçün `debug` tag istifadə olunur:

```dockerfile
# Development üçün
FROM gcr.io/distroless/nodejs20-debian12:debug
# Bu variantda busybox shell var

# Production-da
FROM gcr.io/distroless/nodejs20-debian12
```

Debug:
```bash
# Debug image-də busybox-la giriş
docker run -it --entrypoint=sh myapp:debug
```

### Rootless Docker Quraşdırma

```bash
# Rootless Docker quraşdır (user altında)
curl -fsSL https://get.docker.com/rootless | sh

# Environment-i qur
export PATH=/home/$(whoami)/bin:$PATH
export DOCKER_HOST=unix:///run/user/$(id -u)/docker.sock

# Service başlat
systemctl --user start docker
systemctl --user enable docker

# Yoxla
docker info
# => "Context: rootless"
# => "Security Options: rootless"
```

### Rootful vs Rootless Müqayisə

```bash
# Rootful Docker (default)
docker run --rm alpine id
# => uid=0(root) gid=0(root)
ps aux | grep $PID # host-da: root

# Rootless Docker
docker run --rm alpine id
# => uid=0(root) gid=0(root) — container daxilində root
ps aux | grep $PID # host-da: istifadəçi (məs. orkhan)
```

### Namespace Remapping

```bash
# /etc/subuid
orkhan:100000:65536

# /etc/subgid
orkhan:100000:65536
```

Container-də `UID 0` → host-da `UID 100000`. Container escape olsa belə, hacker host-da root deyil.

## PHP/Laravel ilə İstifadə

### Minimal Alpine Laravel Image

Distroless-ə yaxın minimal Laravel image:

```dockerfile
# Build stage
FROM composer:2 AS build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# Production stage — minimal
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        libzip-dev icu-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql opcache intl bcmath zip \
    && apk del libzip-dev icu-dev

# Non-root user yarat
RUN addgroup -g 1000 laravel && adduser -u 1000 -G laravel -s /bin/sh -D laravel

WORKDIR /var/www/html
COPY --from=build --chown=laravel:laravel /app /var/www/html

USER laravel

EXPOSE 9000
CMD ["php-fpm"]
```

Ölçü: ~80MB vs standart Ubuntu image 500MB+.

### Chainguard PHP Image (Distroless alternativ)

Chainguard daha sıx təhlükəsiz, minimal image-lər təklif edir:

```dockerfile
FROM cgr.dev/chainguard/php:latest-dev AS build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader
COPY . .

FROM cgr.dev/chainguard/php:latest
COPY --from=build /app /app
WORKDIR /app
USER nonroot
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

### Rootless Docker-də Laravel Işlətmək

```bash
# Rootless Docker istifadəçi altında
export DOCKER_HOST=unix:///run/user/1000/docker.sock

# Laravel layihəsi
docker compose up -d

# Port 80 istifadə edə bilmirik (rootless <1024 portlara bind edə bilmir)
# Bu ümumi problemdir — həll: 8080, 8443 portlarından istifadə et
```

`docker-compose.yml` port adaptasiyası:
```yaml
services:
  app:
    image: laravel:latest
    ports:
      - "8080:80"  # 80 əvəzinə 8080
```

### Security Context ilə Kubernetes-də

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: laravel-secure
spec:
  securityContext:
    runAsNonRoot: true
    runAsUser: 1000
    runAsGroup: 1000
    fsGroup: 1000
  containers:
    - name: laravel
      image: myapp/laravel:latest
      securityContext:
        allowPrivilegeEscalation: false
        readOnlyRootFilesystem: true
        capabilities:
          drop:
            - ALL
      volumeMounts:
        - name: tmp
          mountPath: /tmp
        - name: cache
          mountPath: /var/www/html/bootstrap/cache
  volumes:
    - name: tmp
      emptyDir: {}
    - name: cache
      emptyDir: {}
```

## Interview Sualları

**1. Distroless image nədir və niyə istifadə olunur?**
Distroless — yalnız runtime-ı olan minimal image-dir. Shell, paket meneceri yoxdur. Nəticədə kiçik ölçü, az CVE, az attack surface.

**2. Distroless image-də necə debug edirik?**
Google `:debug` tag-li variant təqdim edir (busybox shell ilə). Production-da normal image, debug üçün `:debug` istifadə olunur. Yaxud `kubectl debug` ilə ephemeral container əlavə edilir.

**3. Rootless Docker-in əsas üstünlüyü nədir?**
Docker daemon və konteynerlər root olmayan istifadəçi altında işləyir. Container escape baş verərsə, hacker host-da root olmur — təhlükəsizlik çox yaxşıdır.

**4. Rootless Docker-də hansı məhdudiyyətlər var?**
- 1024-dən aşağı portlara bind edə bilmir (setcap ilə həll olur)
- Overlay network limited
- Bəzi storage driver-ləri işləmir
- cgroups v2 tələb olunur

**5. Distroless PHP üçün mümkündürmü?**
Google rəsmi PHP distroless image vermir (PHP-nin dinamik extension sistemi səbəbilə). Alternativ: Chainguard PHP image, yaxud minimal Alpine + non-root user.

**6. `readOnlyRootFilesystem` nədir?**
Kubernetes security context — container-in root filesystem-ini read-only edir. Attacker fayl yaza bilmir. `/tmp`, Laravel cache üçün emptyDir volume istifadə olunur.

**7. Distroless vs Alpine fərqi?**
- Alpine: minimal Linux distro, apk paket meneceri var, shell var (~5MB)
- Distroless: runtime-dan başqa heç nə yoxdur (~2MB)

Alpine-də musl libc istifadə olunur (glibc əvəzinə) — bəzi tətbiqlərdə uyğunsuzluq.

**8. Rootless Docker-də port 80/443 necə açılır?**
```bash
# setcap ilə binary-ə icazə verilir
sudo setcap cap_net_bind_service=ep $(which rootlesskit)
# Sonra rootless Docker `80` portuna bind edə bilir
```

Alternativ: reverse proxy (Nginx) host-da, konteyner 8080-də.

## Best Practices

1. **Production-da distroless istifadə et** — kiçik attack surface, kiçik ölçü
2. **Multi-stage build** — build alətləri final image-də olmasın
3. **Non-root user** — hətta rootful Docker-də də `USER nonroot` təyin et
4. **`readOnlyRootFilesystem`** — K8s security context-də quraşdır
5. **Capability drop** — `drop: ALL` və yalnız lazım olanları geri əlavə et
6. **Rootless Docker development-də** — dev maşınlarda istifadə et
7. **Image scanning** — Trivy, Snyk ilə image-ləri yoxla
8. **Chainguard images** — distroless alternativi kimi nəzərdən keçir
9. **`allowPrivilegeEscalation: false`** — K8s-də hər podda
10. **SBOM yarat** — image daxilindəki hər paket məlumatı (syft ilə)
