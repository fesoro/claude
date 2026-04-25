# Volumes və Storage

> **Səviyyə (Level):** ⭐⭐ Middle

## Nədir? (What is it?)

Docker konteynerləri ephemeral-dır — konteyner silinəndə içindəki data itirilir. Volume-lar data-nı konteynerin lifecycle-ından kənarda saxlamağa imkan verir. Docker üç əsas data saxlama mexanizmi təqdim edir: named volumes, bind mounts və tmpfs mounts.

```
┌────────────────────────────────────────────────┐
│                Docker Host                      │
│                                                 │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐     │
│  │Container │  │Container │  │Container │     │
│  │  /data   │  │  /app    │  │  /tmp    │     │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘     │
│       │              │              │           │
│  Named Volume   Bind Mount      tmpfs          │
│       │              │              │           │
│  /var/lib/docker/  /home/user/   (memory)      │
│  volumes/mydata/   project/                     │
│                                                 │
└────────────────────────────────────────────────┘
```

## Əsas Konseptlər

### Named Volumes

Docker tərəfindən idarə olunan volume-lar. Data `/var/lib/docker/volumes/` altında saxlanır.

```bash
# Volume yaratmaq
docker volume create mydata

# Volume-ları siyahılamaq
docker volume ls

# Volume məlumatı
docker volume inspect mydata

# Volume silmək
docker volume rm mydata

# İstifadə olunmayan volume-ları silmək
docker volume prune
```

```bash
# Konteyner ilə istifadə
docker run -d --name mysql \
  -v mysql-data:/var/lib/mysql \
  mysql:8.0

# Eyni volume-u başqa konteynerlə paylaşmaq
docker run -d --name mysql-backup \
  -v mysql-data:/var/lib/mysql:ro \
  mysql:8.0
```

**Xüsusiyyətləri:**
- Docker idarə edir, portativdir
- Host OS-dən asılı deyil
- Backup və migration asandır
- Konteyner silinəndə data qalır
- Driver-lər ilə genişləndirilə bilər (NFS, cloud storage)

### Bind Mounts

Host filesystem-in müəyyən qovluğunu konteynerə mount edir.

```bash
# Bind mount
docker run -d --name nginx \
  -v /home/user/html:/usr/share/nginx/html \
  nginx

# Read-only bind mount
docker run -d --name nginx \
  -v /home/user/html:/usr/share/nginx/html:ro \
  nginx

# --mount sintaksisi (daha açıq)
docker run -d --name nginx \
  --mount type=bind,source=/home/user/html,target=/usr/share/nginx/html,readonly \
  nginx
```

**Xüsusiyyətləri:**
- Host-un tam yolunu göstərmək lazımdır
- Development-də kodu real-time dəyişmək üçün idealdır
- Host OS-dən asılıdır
- Daha az portativdir
- Host fayllarına birbaşa giriş verir (təhlükəsizlik riski)

### tmpfs Mounts

Data yalnız yaddaşda saxlanır, disk-ə yazılmır.

```bash
# tmpfs mount
docker run -d --name app \
  --tmpfs /tmp:rw,size=100m,mode=1777 \
  php:8.3-fpm

# --mount sintaksisi
docker run -d --name app \
  --mount type=tmpfs,target=/tmp,tmpfs-size=100m \
  php:8.3-fpm
```

**Xüsusiyyətləri:**
- Yalnız Linux-da mövcuddur
- Data konteyner dayandıqda itirilir
- Həssas data üçün (şifrələr, token-lər) idealdır
- Sürətli I/O (yaddaşda)
- Müvəqqəti fayllar üçün

### -v vs --mount Sintaksisi

```bash
# -v (qısa, amma mürəkkəb hallarda çaşdırıcı ola bilər)
docker run -v myvolume:/app                    # Named volume
docker run -v /host/path:/container/path       # Bind mount
docker run -v /host/path:/container/path:ro    # Read-only

# --mount (uzun, amma daha açıq)
docker run --mount type=volume,source=myvolume,target=/app
docker run --mount type=bind,source=/host/path,target=/container/path,readonly
docker run --mount type=tmpfs,target=/tmp

# Əsas fərq: -v olmayan yol yaradır, --mount xəta verir
docker run -v /nonexistent:/app     # /nonexistent yaradılır
docker run --mount type=bind,source=/nonexistent,target=/app  # XƏTA!
```

### Volume Drivers

```bash
# Local driver (default)
docker volume create --driver local myvolume

# NFS volume
docker volume create --driver local \
  --opt type=nfs \
  --opt o=addr=192.168.1.100,rw \
  --opt device=:/path/to/share \
  nfs-volume

# Docker compose-da
# volumes:
#   nfs-data:
#     driver: local
#     driver_opts:
#       type: nfs
#       o: addr=192.168.1.100,rw
#       device: ":/data"
```

### Data Persistence — Niyə Vacibdir?

```bash
# Volume olmadan — data itirilir!
docker run -d --name mysql-temp mysql:8.0 -e MYSQL_ROOT_PASSWORD=secret
docker rm -f mysql-temp
# Bütün database data-sı itdi!

# Volume ilə — data qalır
docker run -d --name mysql-persistent \
  -v mysql-data:/var/lib/mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  mysql:8.0

docker rm -f mysql-persistent
# Data hələ də mysql-data volume-undadır

docker run -d --name mysql-new \
  -v mysql-data:/var/lib/mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  mysql:8.0
# Əvvəlki data ilə işləyir!
```

### Backup Strategiyaları

```bash
# Volume-u backup etmək (tar)
docker run --rm \
  -v mysql-data:/source:ro \
  -v $(pwd)/backup:/backup \
  alpine tar czf /backup/mysql-backup-$(date +%Y%m%d).tar.gz -C /source .

# Backup-dan restore etmək
docker run --rm \
  -v mysql-data:/target \
  -v $(pwd)/backup:/backup:ro \
  alpine sh -c "cd /target && tar xzf /backup/mysql-backup-20240101.tar.gz"

# MySQL dump ilə backup
docker exec mysql-container mysqldump -u root -psecret --all-databases > backup.sql

# Restore
docker exec -i mysql-container mysql -u root -psecret < backup.sql

# Volume-u başqa host-a köçürmək
docker run --rm -v myvolume:/data -v $(pwd):/backup alpine \
  tar czf /backup/volume-export.tar.gz -C /data .
# Yeni host-da:
docker volume create myvolume
docker run --rm -v myvolume:/data -v $(pwd):/backup alpine \
  tar xzf /backup/volume-export.tar.gz -C /data
```

### Konteyner Arasında Data Paylaşma

```bash
# Eyni named volume-u paylaşmaq
docker run -d --name writer \
  -v shared-data:/data \
  alpine sh -c "while true; do date >> /data/log.txt; sleep 5; done"

docker run -d --name reader \
  -v shared-data:/data:ro \
  alpine tail -f /data/log.txt

# volumes_from (köhnə üsul)
docker run -d --name data-container \
  -v /data \
  alpine tail -f /dev/null

docker run -d --name app \
  --volumes-from data-container \
  alpine cat /data/somefile
```

## Praktiki Nümunələr

### Docker Compose ilə Volume-lar

```yaml
services:
  mysql:
    image: mysql:8.0
    volumes:
      - mysql-data:/var/lib/mysql           # Named volume
      - ./docker/mysql/init:/docker-entrypoint-initdb.d:ro  # Init scriptlər
      - ./docker/mysql/my.cnf:/etc/mysql/conf.d/custom.cnf:ro

  redis:
    image: redis:7-alpine
    volumes:
      - redis-data:/data

  app:
    build: .
    volumes:
      - .:/var/www/html                     # Dev bind mount
      - vendor:/var/www/html/vendor         # Vendor isolation
      - node_modules:/var/www/html/node_modules

volumes:
  mysql-data:
  redis-data:
  vendor:
  node_modules:
```

## PHP/Laravel ilə İstifadə

### Laravel Storage Volume Strategiyası

```yaml
# docker-compose.yml
services:
  app:
    build: .
    volumes:
      # Development: bind mount (live code changes)
      - .:/var/www/html

      # Performance: vendor və node_modules named volume-da
      - vendor:/var/www/html/vendor
      - node_modules:/var/www/html/node_modules

      # Storage: named volume (persist across rebuilds)
      - storage-app:/var/www/html/storage/app
      - storage-logs:/var/www/html/storage/logs

      # Framework cache: tmpfs (sürətli, müvəqqəti)
      - type: tmpfs
        target: /var/www/html/storage/framework/cache
      - type: tmpfs
        target: /var/www/html/storage/framework/sessions
      - type: tmpfs
        target: /var/www/html/storage/framework/views

  nginx:
    image: nginx:alpine
    volumes:
      - .:/var/www/html:ro                  # Read-only app code
      - storage-app:/var/www/html/storage/app:ro  # Serve uploads

volumes:
  vendor:
  node_modules:
  storage-app:
  storage-logs:
```

### Laravel Fayl Yükləmə ilə Volume

```yaml
services:
  app:
    build: .
    volumes:
      - uploads:/var/www/html/storage/app/public
    environment:
      FILESYSTEM_DISK: local

  nginx:
    image: nginx:alpine
    volumes:
      - uploads:/var/www/html/storage/app/public:ro

volumes:
  uploads:
    driver: local
```

### Production Volume Strategiyası

```yaml
# Production-da bind mount istifadə etməyin!
services:
  app:
    image: myregistry.com/laravel-app:v1.2.3
    volumes:
      # Yalnız persistent data üçün volume
      - storage-app:/var/www/html/storage/app
      - storage-logs:/var/www/html/storage/logs
    # Kod image-in içindədir, bind mount yoxdur

  mysql:
    image: mysql:8.0
    volumes:
      - mysql-data:/var/lib/mysql

volumes:
  storage-app:
  storage-logs:
  mysql-data:
    # Production-da backup etməyi unutmayın!
```

### Laravel Storage İcazələri

```dockerfile
# Dockerfile-da icazələri düzəltmək
RUN mkdir -p storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
```

```bash
# Əgər icazə problemi varsa
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

## İntervyu Sualları

### 1. Docker volume-ların üç tipi hansılardır?
**Cavab:** Named volumes (Docker idarə edir, /var/lib/docker/volumes-da saxlanır), bind mounts (host filesystem-dən müəyyən qovluq mount olunur), tmpfs mounts (yalnız yaddaşda saxlanır, disk-ə yazılmır). Hər birinin öz istifadə sahəsi var.

### 2. Named volume və bind mount arasında fərq nədir?
**Cavab:** Named volume Docker tərəfindən idarə olunur, portativdir, backup/migrate asandır. Bind mount host-un konkret qovluğunu map edir, development-də live code changes üçün istifadə olunur. Production-da named volume tövsiyə olunur.

### 3. Konteyner silinəndə volume-dakı data nə olur?
**Cavab:** Named volume-dakı data qalır. Volume yalnız `docker volume rm` və ya `docker compose down -v` ilə silinir. Bind mount-da data host-dadır, təsir olunmur. tmpfs data-sı konteyner dayandıqda itirilir.

### 4. Volume backup-ını necə edirsiniz?
**Cavab:** Alpine konteyner ilə volume-u mount edib tar arxivi yaratmaq, database-lər üçün dump əmrləri (mysqldump, pg_dump), xarici backup alətləri (restic, borgbackup) istifadə etmək mümkündür. Production-da mütəmadi backup cron job-u qurulmalıdır.

### 5. tmpfs nə vaxt istifadə olunur?
**Cavab:** Həssas data (token, şifrə) müvəqqəti saxlamaq, sürətli I/O lazım olduqda, cache faylları, session faylları üçün. Data konteyner dayandıqda itirilir. Laravel framework cache, sessions üçün idealdır.

### 6. `-v` və `--mount` arasında fərq nədir?
**Cavab:** `-v` qısa sintaksisdir. `--mount` daha açıq key-value sintaksisi istifadə edir. Əsas fərq: `-v` olmayan host yolunu yaradır, `--mount` xəta verir. Production-da `--mount` tövsiyə olunur çünki daha açıqdır.

### 7. Konteynerlər arasında data necə paylaşılır?
**Cavab:** Eyni named volume-u bir neçə konteynerə mount etməklə. Məsələn, PHP-FPM konteyneri fayl yükləyir, Nginx konteyneri eyni volume-u read-only mount edərək faylları serve edir.

### 8. Production-da niyə bind mount istifadə edilmir?
**Cavab:** Bind mount host filesystem-dən asılıdır, portativ deyil, icazə problemləri yarada bilər, host-a birbaşa giriş verir (təhlükəsizlik riski). Production-da kod image-in içində olmalıdır, yalnız persistent data üçün named volume istifadə olunmalıdır.

## Best Practices

1. **Production-da bind mount istifadə etməyin** — Kod image-in içində olmalıdır.
2. **Database data-sı üçün həmişə named volume istifadə edin** — Data itirilməsinin qarşısını alın.
3. **Read-only mount istifadə edin** — Lazım olmayan yazma icazəsi verməyin (`:ro`).
4. **Mütəmadi backup qurun** — Volume backup-ını avtomatlaşdırın.
5. **tmpfs istifadə edin** — Müvəqqəti və həssas data üçün.
6. **Vendor/node_modules üçün named volume** — Performance üçün bind mount-dan ayırın.
7. **Volume prune-u unutmayın** — İstifadə olunmayan volume-ları təmizləyin.
8. **Docker Compose-da volume-ları declare edin** — Top-level `volumes:` bölməsində.
9. **İcazələrə diqqət edin** — Container istifadəçisi ilə host istifadəçisinin UID/GID uyğunluğu.
10. **Volume driver-ləri araşdırın** — NFS, cloud storage üçün uyğun driver seçin.
