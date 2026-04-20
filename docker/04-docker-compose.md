# Docker Compose

## Nədir? (What is it?)

Docker Compose — çox konteynerli Docker tətbiqlərini təyin etmək və idarə etmək üçün alətdir. YAML faylında (docker-compose.yml) service-lər, şəbəkələr və volume-lar təyin edilir, sonra bir əmrlə bütün infrastruktur qaldırılır.

```bash
# Bütün service-ləri başlatmaq
docker compose up -d

# Dayandırmaq
docker compose down

# Logları görmək
docker compose logs -f
```

## Əsas Konseptlər

### docker-compose.yml Strukturu

```yaml
# docker-compose.yml əsas strukturu
version: "3.8"  # Artıq optional-dır (compose v2+)

services:       # Konteyner tərifləri
  app:
    image: nginx
    ports:
      - "80:80"

networks:       # Xüsusi şəbəkələr
  frontend:
    driver: bridge

volumes:        # Adlı volume-lar
  db-data:
    driver: local

configs:        # Konfiqurasiya faylları
  nginx-conf:
    file: ./nginx.conf

secrets:        # Həssas məlumatlar
  db-password:
    file: ./secrets/db_password.txt
```

### Services — Əsas Bölmə

```yaml
services:
  app:
    # Image və ya build
    image: php:8.3-fpm                    # Hazır image
    build:                                 # Və ya Dockerfile-dan build
      context: .
      dockerfile: docker/app/Dockerfile
      args:
        PHP_VERSION: "8.3"
      target: production                   # Multi-stage target

    # Konteyner adı
    container_name: laravel-app

    # Restart siyasəti
    restart: unless-stopped                # always | on-failure | no

    # Port mapping
    ports:
      - "9000:9000"                        # host:container
      - "127.0.0.1:9001:9001"             # yalnız localhost

    # Volume-lar
    volumes:
      - .:/var/www/html                    # Bind mount
      - vendor:/var/www/html/vendor        # Named volume
      - type: tmpfs                        # tmpfs mount
        target: /tmp

    # Mühit dəyişənləri
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      DB_HOST: mysql

    # Fayl-dan mühit dəyişənləri
    env_file:
      - .env
      - .env.docker

    # Asılılıqlar
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started

    # Şəbəkələr
    networks:
      - backend
      - frontend

    # Health check
    healthcheck:
      test: ["CMD", "php-fpm-healthcheck"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 40s

    # Resurs limitləri
    deploy:
      resources:
        limits:
          cpus: "2.0"
          memory: 512M
        reservations:
          cpus: "0.5"
          memory: 256M

    # Əlavə host-lar
    extra_hosts:
      - "host.docker.internal:host-gateway"

    # İstifadəçi
    user: "1000:1000"

    # İş qovluğu
    working_dir: /var/www/html

    # Əmr override
    command: php artisan serve --host=0.0.0.0 --port=8000
    # Və ya entrypoint override
    entrypoint: /usr/local/bin/entrypoint.sh

    # DNS
    dns:
      - 8.8.8.8
      - 8.8.4.4

    # Logging
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
```

### Networks — Şəbəkələr

```yaml
networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge
    internal: true          # Xarici əlaqə yoxdur
  custom:
    driver: bridge
    ipam:
      config:
        - subnet: 172.28.0.0/16
```

### Volumes — Data Saxlama

```yaml
volumes:
  db-data:
    driver: local
  redis-data:
    driver: local
  upload-data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /data/uploads
```

### depends_on — Asılılıq Sırası

```yaml
services:
  app:
    depends_on:
      mysql:
        condition: service_healthy    # Health check keçənə qədər gözlə
      redis:
        condition: service_started    # Başlaması kifayətdir
      migration:
        condition: service_completed_successfully  # Bitməsini gözlə
```

### Environment Variables

```yaml
services:
  app:
    # Birbaşa
    environment:
      DB_HOST: mysql
      DB_PORT: 3306

    # Fayldan
    env_file:
      - .env

    # Host mühit dəyişənindən
    environment:
      DB_PASSWORD: ${DB_PASSWORD}        # Host-dan oxuyur
      APP_PORT: ${APP_PORT:-8000}        # Default dəyər
```

### Profiles

```yaml
services:
  app:
    # Həmişə başlayır (profil yoxdur)
    image: php:8.3-fpm

  mysql:
    image: mysql:8.0

  mailhog:
    image: mailhog/mailhog
    profiles: ["debug"]                  # Yalnız debug profili ilə

  phpmyadmin:
    image: phpmyadmin
    profiles: ["debug"]

  selenium:
    image: selenium/standalone-chrome
    profiles: ["testing"]
```

```bash
# Normal başlatma (app + mysql)
docker compose up -d

# Debug profili ilə (app + mysql + mailhog + phpmyadmin)
docker compose --profile debug up -d

# Test profili ilə
docker compose --profile testing up -d
```

### Extends

```yaml
# docker-compose.base.yml
services:
  php-base:
    image: php:8.3-fpm-alpine
    volumes:
      - .:/var/www/html
    environment:
      APP_ENV: production

# docker-compose.yml
services:
  app:
    extends:
      file: docker-compose.base.yml
      service: php-base
    ports:
      - "9000:9000"
```

## Praktiki Nümunələr

### Əsas Docker Compose Əmrləri

```bash
# Başlatmaq
docker compose up -d

# Yenidən build etmək
docker compose up -d --build

# Dayandırmaq (volume-lar qalır)
docker compose down

# Dayandırmaq + volume-ları silmək
docker compose down -v

# Loglar
docker compose logs -f app
docker compose logs --tail=100 app mysql

# Bir service-i yenidən başlatmaq
docker compose restart app

# Service-lərin statusu
docker compose ps

# Bir service-də əmr icra etmək
docker compose exec app php artisan migrate
docker compose exec app bash

# Bir dəfəlik əmr (yeni konteyner yaradır)
docker compose run --rm app php artisan tinker

# Scale etmək
docker compose up -d --scale app=3

# Konfiqurasiya yoxlamaq
docker compose config

# Image-ləri çəkmək
docker compose pull
```

## PHP/Laravel ilə İstifadə

### Tam Laravel Stack: PHP-FPM + Nginx + MySQL + Redis

```yaml
# docker-compose.yml

services:
  # ===================
  # Nginx Web Server
  # ===================
  nginx:
    image: nginx:1.25-alpine
    container_name: laravel-nginx
    restart: unless-stopped
    ports:
      - "${APP_PORT:-80}:80"
      - "${APP_SSL_PORT:-443}:443"
    volumes:
      - .:/var/www/html:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
    depends_on:
      app:
        condition: service_healthy
    networks:
      - frontend
      - backend
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  # ===================
  # PHP-FPM Application
  # ===================
  app:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
      args:
        PHP_VERSION: "8.3"
        WWWUSER: "${WWWUSER:-1000}"
        WWWGROUP: "${WWWGROUP:-1000}"
    container_name: laravel-app
    restart: unless-stopped
    volumes:
      - .:/var/www/html
      - vendor:/var/www/html/vendor
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini:ro
    environment:
      APP_ENV: "${APP_ENV:-local}"
      APP_DEBUG: "${APP_DEBUG:-true}"
      DB_CONNECTION: mysql
      DB_HOST: mysql
      DB_PORT: 3306
      DB_DATABASE: "${DB_DATABASE:-laravel}"
      DB_USERNAME: "${DB_USERNAME:-laravel}"
      DB_PASSWORD: "${DB_PASSWORD:-secret}"
      REDIS_HOST: redis
      REDIS_PORT: 6379
      CACHE_DRIVER: redis
      SESSION_DRIVER: redis
      QUEUE_CONNECTION: redis
      MAIL_MAILER: smtp
      MAIL_HOST: mailhog
      MAIL_PORT: 1025
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - backend
    healthcheck:
      test: ["CMD-SHELL", "php-fpm-healthcheck || exit 1"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 30s

  # ===================
  # Queue Worker
  # ===================
  queue:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    container_name: laravel-queue
    restart: unless-stopped
    command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
    volumes:
      - .:/var/www/html
      - vendor:/var/www/html/vendor
    environment:
      APP_ENV: "${APP_ENV:-local}"
      DB_HOST: mysql
      DB_DATABASE: "${DB_DATABASE:-laravel}"
      DB_USERNAME: "${DB_USERNAME:-laravel}"
      DB_PASSWORD: "${DB_PASSWORD:-secret}"
      REDIS_HOST: redis
    depends_on:
      app:
        condition: service_healthy
    networks:
      - backend

  # ===================
  # Task Scheduler (Cron)
  # ===================
  scheduler:
    build:
      context: .
      dockerfile: docker/app/Dockerfile
    container_name: laravel-scheduler
    restart: unless-stopped
    command: >
      sh -c "while true; do
        php artisan schedule:run --verbose --no-interaction;
        sleep 60;
      done"
    volumes:
      - .:/var/www/html
      - vendor:/var/www/html/vendor
    environment:
      APP_ENV: "${APP_ENV:-local}"
      DB_HOST: mysql
      REDIS_HOST: redis
    depends_on:
      app:
        condition: service_healthy
    networks:
      - backend

  # ===================
  # MySQL Database
  # ===================
  mysql:
    image: mysql:8.0
    container_name: laravel-mysql
    restart: unless-stopped
    ports:
      - "${DB_EXTERNAL_PORT:-3306}:3306"
    volumes:
      - mysql-data:/var/lib/mysql
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/custom.cnf:ro
      - ./docker/mysql/init:/docker-entrypoint-initdb.d:ro
    environment:
      MYSQL_ROOT_PASSWORD: "${DB_ROOT_PASSWORD:-rootsecret}"
      MYSQL_DATABASE: "${DB_DATABASE:-laravel}"
      MYSQL_USER: "${DB_USERNAME:-laravel}"
      MYSQL_PASSWORD: "${DB_PASSWORD:-secret}"
    networks:
      - backend
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${DB_ROOT_PASSWORD:-rootsecret}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  # ===================
  # Redis Cache
  # ===================
  redis:
    image: redis:7-alpine
    container_name: laravel-redis
    restart: unless-stopped
    ports:
      - "${REDIS_EXTERNAL_PORT:-6379}:6379"
    volumes:
      - redis-data:/data
    command: redis-server --appendonly yes --requirepass "${REDIS_PASSWORD:-}"
    networks:
      - backend
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3

  # ===================
  # Debug Tools (Profile)
  # ===================
  mailhog:
    image: mailhog/mailhog
    container_name: laravel-mailhog
    restart: unless-stopped
    ports:
      - "1025:1025"
      - "8025:8025"
    profiles: ["debug"]
    networks:
      - backend

  phpmyadmin:
    image: phpmyadmin:latest
    container_name: laravel-phpmyadmin
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD: "${DB_ROOT_PASSWORD:-rootsecret}"
    profiles: ["debug"]
    depends_on:
      - mysql
    networks:
      - backend

# ===================
# Networks
# ===================
networks:
  frontend:
    driver: bridge
  backend:
    driver: bridge

# ===================
# Volumes
# ===================
volumes:
  mysql-data:
    driver: local
  redis-data:
    driver: local
  vendor:
    driver: local
```

### Nginx Konfiqurasiyası (default.conf)

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    charset utf-8;
    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /health {
        access_log off;
        return 200 "healthy\n";
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### .env nümunəsi

```env
# .env (docker compose üçün)
APP_PORT=80
APP_SSL_PORT=443
APP_ENV=local
APP_DEBUG=true

DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
DB_ROOT_PASSWORD=rootsecret
DB_EXTERNAL_PORT=3306

REDIS_EXTERNAL_PORT=6379
REDIS_PASSWORD=

WWWUSER=1000
WWWGROUP=1000
```

## İntervyu Sualları

### 1. Docker Compose nədir?
**Cavab:** Çox konteynerli tətbiqləri YAML faylında təyin edib, bir əmrlə idarə etmək üçün alətdir. Service-lər, şəbəkələr, volume-lar bir yerdə təyin olunur. Development mühitində çox istifadə olunur.

### 2. `docker compose up` və `docker compose run` arasında fərq nədir?
**Cavab:** `up` bütün service-ləri (və ya göstərilən service-i) başladır, depends_on-a uyğun sırada. `run` yalnız bir service üçün yeni konteyner yaradır, bir dəfəlik əmr icra etmək üçündür (məs. migration, seed).

### 3. `depends_on` service-in hazır olmasını gözləyirmi?
**Cavab:** Default olaraq yalnız konteynerin başlamasını gözləyir, service-in hazır olmasını yox. `condition: service_healthy` ilə health check keçənə qədər gözləmək mümkündür. Bu, MySQL-in tam yüklənməsini gözləmək üçün vacibdir.

### 4. `docker compose down` və `docker compose stop` arasında fərq nədir?
**Cavab:** `stop` konteynerləri dayandırır amma silmir. `down` konteynerləri və yaradılmış şəbəkələri silir. `-v` flaqı ilə volume-ları da silir. `down` daha təmiz sıfırlama verir.

### 5. Docker Compose-da şəbəkə necə işləyir?
**Cavab:** Compose avtomatik bir bridge şəbəkə yaradır. Service-lər bir-birinə service adı ilə müraciət edir (DNS). Məsələn, `app` service-i `mysql` adı ilə MySQL-ə qoşula bilər. Xüsusi şəbəkələr ilə izolyasiya yaradıla bilər.

### 6. Compose-da mühit dəyişənlərini necə idarə edirsiniz?
**Cavab:** `environment` bölməsində birbaşa, `env_file` ilə fayldan, və ya `${VAR:-default}` sintaksisi ilə host mühit dəyişənlərindən oxumaq olar. Compose avtomatik `.env` faylını oxuyur.

### 7. Profiles nə üçün istifadə olunur?
**Cavab:** Service-ləri qruplara ayırmaq üçün. Profil olmayan service-lər həmişə başlayır. Profil olan service-lər yalnız `--profile` flaqı ilə başlayır. Debug alətlərini (phpMyAdmin, MailHog) development-də istifadə etmək üçün idealdır.

### 8. Compose-da volume-lar niyə vacibdir?
**Cavab:** Konteyner silinəndə data itmir (named volumes). Development-də bind mount ilə kodu real-time dəyişmək olur. Database data-sı, Redis data-sı, vendor qovluğu üçün named volumes istifadə olunur.

## Best Practices

1. **Həmişə `depends_on` ilə `condition` istifadə edin** — service_healthy ilə hazırlığı gözləyin.
2. **Mühit dəyişənlərini `.env`-dən oxuyun** — Hardcode etməyin.
3. **Named volumes istifadə edin** — Database data-sı üçün bind mount yox, named volume.
4. **Health check əlavə edin** — Hər service üçün health check yazın.
5. **Restart policy təyin edin** — `unless-stopped` və ya `on-failure` istifadə edin.
6. **Şəbəkələri ayırın** — Frontend və backend şəbəkələrini ayırın.
7. **Profile-lardan istifadə edin** — Debug alətlərini profile-a qoyun.
8. **Resource limit-ləri qoyun** — `deploy.resources` ilə CPU/memory limitləyin.
9. **Read-only mount-lar istifadə edin** — Konfiqurasiya fayllarını `:ro` ilə mount edin.
10. **`docker compose config`** ilə YAML-ı yoxlayın — Xətaları erkən tapın.
