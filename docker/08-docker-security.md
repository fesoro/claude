# Docker Security

## Nədir? (What is it?)

Docker təhlükəsizliyi — konteynerləşdirilmiş tətbiqləri, host sistemini və data-nı qorumaq üçün tətbiq edilən praktikalar və mexanizmlər toplusudur. Konteynerlər VM-lərdən daha az izolyasiya təmin edir (host kernel paylaşılır), ona görə təhlükəsizlik tədbirləri çox vacibdir.

## Əsas Konseptlər

### Non-Root İstifadəçilər

Default olaraq konteynerlər root kimi işləyir — bu böyük təhlükəsizlik riskidir.

```dockerfile
# PİS — root kimi işləyir
FROM php:8.3-fpm
COPY . /var/www/html
CMD ["php-fpm"]

# YAXŞI — non-root istifadəçi
FROM php:8.3-fpm-alpine

# İstifadəçi yaratmaq
RUN addgroup -g 1000 -S appgroup && \
    adduser -u 1000 -S appuser -G appgroup

# Faylların sahibliyini dəyişmək
COPY --chown=appuser:appgroup . /var/www/html

WORKDIR /var/www/html

# Non-root istifadəçiyə keçmək
USER appuser

CMD ["php-fpm"]
```

```bash
# Runtime-da user override
docker run --user 1000:1000 myapp
docker run --user nobody myapp

# Root-u tamamilə bloklamaq (Kubernetes)
# securityContext:
#   runAsNonRoot: true
#   runAsUser: 1000
```

### Read-Only Filesystem

```bash
# Read-only konteyner
docker run --read-only myapp

# Yazıla bilən qovluqlar əlavə etmək
docker run --read-only \
  --tmpfs /tmp \
  --tmpfs /var/run \
  -v app-data:/var/www/html/storage \
  myapp
```

```yaml
# Docker Compose-da
services:
  app:
    image: myapp
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
    volumes:
      - storage:/var/www/html/storage
```

### Secrets Management

```bash
# Docker Swarm secrets
echo "my_db_password" | docker secret create db_password -
docker service create --secret db_password myapp
# Konteyner daxilində: /run/secrets/db_password

# Docker Compose secrets
# docker-compose.yml
```

```yaml
services:
  app:
    image: myapp
    secrets:
      - db_password
      - api_key
    environment:
      DB_PASSWORD_FILE: /run/secrets/db_password

  mysql:
    image: mysql:8.0
    secrets:
      - db_password
      - db_root_password
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/db_root_password
      MYSQL_PASSWORD_FILE: /run/secrets/db_password

secrets:
  db_password:
    file: ./secrets/db_password.txt
  db_root_password:
    file: ./secrets/db_root_password.txt
  api_key:
    external: true    # Əvvəlcədən yaradılmış secret
```

```bash
# BuildKit secrets (build vaxtı)
docker build --secret id=composer_auth,src=auth.json .
```

```dockerfile
# Dockerfile-da build secret istifadə
RUN --mount=type=secret,id=composer_auth,target=/root/.composer/auth.json \
    composer install --no-dev
# Secret final image-ə daxil olmur!
```

### Image Scanning

```bash
# Docker Scout (rəsmi)
docker scout quickview myapp:latest
docker scout cves myapp:latest
docker scout recommendations myapp:latest

# Trivy (açıq mənbəli)
trivy image myapp:latest
trivy image --severity HIGH,CRITICAL myapp:latest

# Grype
grype myapp:latest

# Snyk
snyk container test myapp:latest

# CI/CD-də avtomatik scan
# GitHub Actions nümunəsi:
# - name: Scan image
#   uses: aquasecurity/trivy-action@master
#   with:
#     image-ref: myapp:latest
#     severity: HIGH,CRITICAL
#     exit-code: 1
```

### Resource Limits

```bash
# Memory limit
docker run --memory="512m" --memory-swap="1g" myapp

# CPU limit
docker run --cpus="1.5" myapp
docker run --cpu-shares=512 myapp  # Relative weight

# PID limit
docker run --pids-limit=100 myapp

# Disk I/O limit
docker run --device-read-bps=/dev/sda:10mb myapp
```

```yaml
# Docker Compose
services:
  app:
    image: myapp
    deploy:
      resources:
        limits:
          cpus: "2.0"
          memory: 512M
        reservations:
          cpus: "0.5"
          memory: 256M
    # Compose v2 əlavə limitlər
    mem_limit: 512m
    cpus: 2.0
    pids_limit: 100
```

### Linux Capabilities

```bash
# Bütün capability-ləri silmək, yalnız lazım olanları əlavə etmək
docker run --cap-drop=ALL --cap-add=NET_BIND_SERVICE myapp

# Default capability-ləri görmək
docker run --rm alpine cat /proc/1/status | grep Cap

# Heç bir yeni privilege əldə etməmək
docker run --security-opt=no-new-privileges myapp
```

```yaml
# Docker Compose
services:
  app:
    image: myapp
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE    # 1024-dən aşağı portlara bind
    security_opt:
      - no-new-privileges:true
```

**Ümumi Capabilities:**

| Capability | Açıqlama |
|-----------|----------|
| NET_BIND_SERVICE | 1024-dən aşağı portlara bind |
| CHOWN | Fayl sahibliyini dəyişmək |
| DAC_OVERRIDE | Fayl icazə yoxlamasını keçmək |
| SETUID/SETGID | İstifadəçi/qrup ID dəyişmək |
| SYS_PTRACE | Process trace (debug) |
| NET_RAW | Raw socket istifadə |

### Seccomp Profiles

Seccomp (Secure Computing Mode) — konteyner daxilindən edilə bilən system call-ları məhdudlaşdırır.

```bash
# Default seccomp profili (300+ syscall-dan ~44-ünü bloklar)
docker run myapp  # Default aktiv

# Xüsusi seccomp profili
docker run --security-opt seccomp=./my-seccomp.json myapp

# Seccomp-u deaktiv etmək (tövsiyə olunmur!)
docker run --security-opt seccomp=unconfined myapp
```

```json
{
    "defaultAction": "SCMP_ACT_ERRNO",
    "architectures": ["SCMP_ARCH_X86_64"],
    "syscalls": [
        {
            "names": ["read", "write", "open", "close", "stat", "fstat",
                      "mmap", "mprotect", "munmap", "brk", "ioctl",
                      "access", "pipe", "select", "clone", "execve",
                      "exit", "exit_group", "futex", "epoll_wait"],
            "action": "SCMP_ACT_ALLOW"
        }
    ]
}
```

### Trusted Images

```bash
# Yalnız rəsmi image-lərdən istifadə edin
docker pull php:8.3-fpm-alpine          # Rəsmi
docker pull bitnami/laravel             # Bitnami (etibarlı)
# docker pull random-user/php-app      # Riskli!

# Image digest ilə çəkmək (tam dəqiq versiya)
docker pull php:8.3-fpm-alpine@sha256:abc123...

# Docker Content Trust (imza yoxlama)
export DOCKER_CONTENT_TRUST=1
docker pull nginx  # Yalnız imzalanmış image-ləri çəkər

# Dockerfile-da pin etmək
FROM php:8.3-fpm-alpine@sha256:abc123def456...
```

### Network Security

```yaml
services:
  app:
    networks:
      - backend

  mysql:
    networks:
      - backend     # Yalnız internal network
    # ports yoxdur — xaricdən əlçatmaz!

  nginx:
    ports:
      - "80:80"     # Yalnız nginx xaricdən əlçatan
    networks:
      - frontend
      - backend

networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
    internal: true   # Xarici internet yoxdur
```

## Praktiki Nümunələr

### Hardened Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

# 1. Minimal paketlər (--no-cache)
RUN apk add --no-cache \
    libpng-dev libzip-dev \
    && docker-php-ext-install pdo_mysql zip gd \
    && apk del --no-cache autoconf g++ make

# 2. Non-root istifadəçi
RUN addgroup -g 1000 -S app && adduser -u 1000 -S app -G app

# 3. Lazımsız faylları silmək
RUN rm -rf /usr/src/php /tmp/* /var/tmp/*

# 4. Faylları kopyalamaq (ownership ilə)
COPY --chown=app:app . /var/www/html

# 5. İcazələri minimuma endirmək
RUN chmod -R 550 /var/www/html \
    && chmod -R 770 /var/www/html/storage /var/www/html/bootstrap/cache

# 6. Non-root istifadəçi
USER app

# 7. Health check
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

# 8. Read-only metadata
LABEL org.opencontainers.image.source="https://github.com/org/repo"
```

### Hardened Docker Compose

```yaml
services:
  app:
    build: .
    read_only: true
    tmpfs:
      - /tmp
    cap_drop:
      - ALL
    security_opt:
      - no-new-privileges:true
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: "1.0"
    healthcheck:
      test: ["CMD", "php-fpm-healthcheck"]
    user: "1000:1000"
    networks:
      - backend

  mysql:
    image: mysql:8.0
    cap_drop:
      - ALL
    cap_add:
      - DAC_OVERRIDE
      - SETUID
      - SETGID
      - CHOWN
    security_opt:
      - no-new-privileges:true
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: "2.0"
    networks:
      - backend

  nginx:
    image: nginx:alpine
    read_only: true
    tmpfs:
      - /var/cache/nginx
      - /var/run
    cap_drop:
      - ALL
    cap_add:
      - NET_BIND_SERVICE
    ports:
      - "80:80"
    networks:
      - frontend
      - backend

networks:
  frontend:
  backend:
    internal: true
```

## PHP/Laravel ilə İstifadə

### Laravel Təhlükəsiz Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

# Build-only secret (image-ə daxil olmur)
RUN --mount=type=secret,id=composer_auth,target=/tmp/auth.json \
    cp /tmp/auth.json /root/.composer/auth.json 2>/dev/null || true && \
    composer install --no-dev --optimize-autoloader && \
    rm -f /root/.composer/auth.json

# .env faylını image-ə daxil etməyin!
# Runtime-da mühit dəyişənləri ilə və ya secrets ilə ötürün
```

### Laravel .env Secrets ilə

```yaml
services:
  app:
    build: .
    secrets:
      - app_key
      - db_password
    environment:
      APP_KEY_FILE: /run/secrets/app_key
      DB_PASSWORD_FILE: /run/secrets/db_password

secrets:
  app_key:
    file: ./secrets/app_key.txt
  db_password:
    file: ./secrets/db_password.txt
```

```php
// config/database.php — secret file-dan oxumaq
'password' => env('DB_PASSWORD') ?:
    (file_exists(env('DB_PASSWORD_FILE', '')) ?
        trim(file_get_contents(env('DB_PASSWORD_FILE'))) : ''),
```

## İntervyu Sualları

### 1. Docker konteynerləri niyə non-root kimi işləməlidir?
**Cavab:** Root konteyner kompromis olduqda, host-un root hüquqlarına giriş riski var (kernel exploit vasitəsilə). Non-root istifadəçi ilə blast radius azalır. Kubernetes-də `runAsNonRoot: true` ilə məcbur edilə bilər.

### 2. Docker secrets və environment variables arasında fərq nədir?
**Cavab:** Env vars `docker inspect`-dən görünür, log-lara düşə bilər, child process-lərə ötürülür. Secrets `/run/secrets/` altında fayl kimi mount olunur, yalnız RAM-da saxlanır, inspect-dən görünmür. Həssas data üçün secrets tövsiyə olunur.

### 3. Image scanning niyə vacibdir?
**Cavab:** Base image-lərdə və dependency-lərdə məlum CVE (vulnerability) ola bilər. Trivy, Docker Scout, Grype kimi alətlər bunları aşkar edir. CI/CD pipeline-da avtomatik scan qoymaq lazımdır. HIGH/CRITICAL vulnerability-lər deploy-u bloklamalıdır.

### 4. Read-only filesystem nə üçün istifadə olunur?
**Cavab:** Konteyner daxilindən fayl sisteminin dəyişdirilməsinin qarşısını alır. Malware-in yazıla bilməsini, konfiqurasiya dəyişikliklərini əngəlləyir. Tmpfs ilə müvəqqəti qovluqlar (tmp, cache) yazıla bilən edilir.

### 5. Linux capabilities nədir?
**Cavab:** Root hüquqlarını kiçik parçalara ayıran mexanizm. `--cap-drop=ALL` ilə hamısını silib, yalnız lazım olanları əlavə etmək (NET_BIND_SERVICE, CHOWN) ən yaxşı praktikadır. Bu, "least privilege" prinsipini tətbiq edir.

### 6. Docker Content Trust nədir?
**Cavab:** Image-lərin imzalanması və yoxlanması mexanizmi. `DOCKER_CONTENT_TRUST=1` ilə aktiv olur. Yalnız imzalanmış image-lər pull və run oluna bilər. Supply chain attack-ların qarşısını alır.

### 7. Seccomp profile nədir?
**Cavab:** Konteyner daxilindən edilə bilən Linux system call-ları məhdudlaşdıran sandbox mexanizmi. Docker default olaraq seccomp profili tətbiq edir (~44 təhlükəli syscall-u bloklar). Xüsusi profil ilə daha da sıxlaşdırıla bilər.

## Best Practices

1. **Non-root istifadəçi istifadə edin** — `USER` instruksiyası ilə.
2. **Minimal base image seçin** — Alpine, distroless, scratch.
3. **Image-ləri mütəmadi scan edin** — CI/CD-də avtomatik.
4. **Secrets üçün env var istifadə etməyin** — Docker secrets, Vault istifadə edin.
5. **`--cap-drop=ALL`** — Yalnız lazım olan capability-ləri əlavə edin.
6. **Read-only filesystem** — `--read-only` + tmpfs.
7. **Resource limits qoyun** — Memory, CPU, PID limitləri.
8. **`no-new-privileges`** — Privilege escalation-u əngəlləyin.
9. **Network izolyasiyası** — Internal network, minimal port mapping.
10. **Image-ləri pin edin** — Digest ilə, `latest` tag-dan qaçın.
11. **Build secret-lər istifadə edin** — `--mount=type=secret` ilə.
12. **Docker Content Trust aktiv edin** — İmzalanmış image-lər.
