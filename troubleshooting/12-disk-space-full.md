# Disk Space Full (Middle)

## Problem (nə görürsən)

Server disk sahəsi tükənib. Yeni fayllar yazıla bilmir, veritabanı operasiyaları uğursuz olur, log-lar kəsilir, deployment-lər pozulur. `df -h` 100% göstərir, ya da yeni faylı yazmağa çalışarkən "No space left on device" xətası alırsan.

Simptomlar:
- Laravel log-ları kəsilir, yeni log yaza bilmir
- `php artisan migrate` xəta verir
- MySQL/PostgreSQL: "ERROR: could not write to file" — WAL tükənib
- Docker: yeni image pull edə bilmir
- Upload-lar uğursuz olur ("failed to write", "disk full")
- Deployment freeze: `composer install` yazıla bilmir
- App cavab vermir amma proseslər işləyir (silent failure)

## Sürətli triage (ilk 5 dəqiqə)

### Disk istifadəsini yoxla

```bash
# Mountpoint-lər üzrə disk dolduluğu
df -h

# İnode istifadəsi (fayl sayı limiti!)
df -i

# Hansı partition dolub?
df -h | grep -v tmpfs | sort -k5 -rn | head -5
```

**Vacib:** `df -h` 100% göstərmir amma `df -i` 100% göstərirsə → inode tükənməsi (fayllar çoxdur, yer yox). Yer boşaltmaq kömək etməz — kiçik faylları sil.

### Ən çox yer tutan direktoriyaları tap

```bash
# Root-dan başla
du -sh /* 2>/dev/null | sort -rh | head -20

# /var direktoriyasına get (log, docker, db)
du -sh /var/* 2>/dev/null | sort -rh | head -10

# Spesifik app folder
du -sh /var/www/app/* 2>/dev/null | sort -rh | head -10
```

### Silinen amma açıq olan fayllar

```bash
# Proses tərəfindən istifadə edilən amma silinmiş fayllar
# (df göstərir, ls göstərmir — ciddi tuzaq)
lsof +L1 | awk '{print $7, $1, $2}' | sort -rn | head -20
```

Bu ən çox unutulan problemdir. Fayl silinib amma proses hələ saxlayır. Yer **yalnız proses restart olduqda** geri qayıdır.

## Diaqnoz

### Hal A: Log fayllar

```bash
du -sh /var/log/* 2>/dev/null | sort -rh | head -20
du -sh /var/www/*/storage/logs/ 2>/dev/null | sort -rh | head
ls -lh /var/log/nginx/ | sort -k5 -rn
```

Laravel app log-larının böyüdüyü:
```bash
ls -lh /var/www/app/storage/logs/
# laravel-2024-01-01.log → 50GB — logrotate işləməyib
```

### Hal B: Docker

```bash
docker system df          # Docker nə qədər yer tutur
docker system df -v       # Verbose: image, container, volume
```

### Hal C: Veritabanı faylları

```bash
du -sh /var/lib/mysql/ 2>/dev/null
du -sh /var/lib/postgresql/ 2>/dev/null

# MySQL binary log-lar
ls -lh /var/lib/mysql/mysql-bin.* 2>/dev/null | sort -k5 -rn | head -10

# PostgreSQL WAL faylları
ls -lh /var/lib/postgresql/*/main/pg_wal/ | tail -5
```

### Hal D: Upload/media faylları

```bash
du -sh /var/www/app/storage/app/public/
du -sh /tmp/
find /tmp -name "*.tmp" -mtime +1 -size +100M | head -10
```

### Hal E: Core dump-lar

```bash
find / -name "core.*" -size +100M 2>/dev/null
ls -lh /var/crash/ 2>/dev/null
```

## Fix (qanaxmanı dayandır)

### Sürətli yer boşaltma

**1. Log faylları (ən təhlükəsiz)**
```bash
# Köhnə rotated log-ları sil
find /var/log -name "*.gz" -mtime +7 -delete
find /var/log -name "*.log.*" -mtime +7 -delete

# Laravel log-ları (həftədən köhnə)
find /var/www/app/storage/logs -name "*.log" -mtime +7 -delete

# Nginx access log-u kəs (sonra logrotate qur)
> /var/log/nginx/access.log   # Truncate — prosesi öldürmür
```

**2. Docker**
```bash
# Unused image, container, network, build cache
docker system prune -f

# Volume da daxil (diqqətli ol!)
docker system prune -af --volumes

# Yalnız unused image-lar
docker image prune -af
```

**3. Temp fayllar**
```bash
find /tmp -mtime +1 -delete 2>/dev/null
find /var/tmp -mtime +7 -delete 2>/dev/null
```

**4. Açıq tutulan silinmiş fayllar**
```bash
# Hər hansı bir process log-u saxlayırsa restart et
systemctl restart nginx
systemctl restart php8.3-fpm

# Yoxsa proses-i yenidən başlat
kill -HUP $(cat /var/run/nginx.pid)
```

**5. Package manager cache**
```bash
apt-get clean
composer clear-cache
npm cache clean --force
```

### Kritik: Veritabanı running vəziyyətdədirsə

MySQL binary log-ları:
```bash
# Mövcud binary log-ları göstər
mysql -e "SHOW BINARY LOGS;"

# 7 gündən köhnəni sil
mysql -e "PURGE BINARY LOGS BEFORE DATE_SUB(NOW(), INTERVAL 7 DAY);"

# Konfiqrasiya
# /etc/mysql/mysql.conf.d/mysqld.cnf
# expire_logs_days=7
# max_binlog_size=100M
```

PostgreSQL WAL:
```bash
# WAL arxivini yoxla
# VACUUM FULL böyük cədvəllərdə bloat-ı azaldır amma diqqət → lock
vacuumdb --analyze --verbose mydb
```

## Əsas səbəbin analizi

- Hansı direktoriya böyüdü? Nə vaxtdan?
- Log rotation konfiqurasiya edilmişdimi?
- Monitoring var idi (disk 80%+ olanda alert)?
- Köhnə log faylları nə vaxtdan silinmir?
- Docker image-lar nə qədər tez-tez build olur, köhnələri silinirsə?

## Qarşısının alınması

### Logrotate konfiqurasiyası

```bash
# /etc/logrotate.d/laravel
/var/www/app/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
    sharedscripts
    postrotate
        [ -f /var/run/php8.3-fpm.pid ] && kill -USR1 $(cat /var/run/php8.3-fpm.pid)
    endscript
}
```

### Monitoring

```bash
# Prometheus node_exporter: node_filesystem_avail_bytes
# Alert: disk < 15% qaldı → warning, disk < 5% → critical

# Sadə cron check
# /etc/cron.d/disk-check
*/15 * * * * root df -h / | awk 'NR==2 {gsub(/%/,""); if($5>85) print "DISK WARNING: "$5"% full"}' | mail -s "Disk Alert" ops@company.com
```

### Laravel-də

```php
// config/logging.php — log faylını sonsuz böyütmə
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
        'days' => 14,   // 14 gündən köhnəni sil
    ],
],
```

## Yadda saxlanacaq komandalar

```bash
# Disk istifadəsi
df -h && df -i

# Ən böyük direktoriyalar
du -sh /* 2>/dev/null | sort -rh | head -20

# Silinib amma proses tutan fayllar
lsof +L1 | sort -k7 -rn | head -20

# Log-ları sürətli sil
find /var/log -name "*.gz" -mtime +7 -delete
find storage/logs -name "*.log" -mtime +14 -delete

# Docker cleanup
docker system prune -af

# MySQL binary log-lar
mysql -e "SHOW BINARY LOGS; PURGE BINARY LOGS BEFORE DATE_SUB(NOW(), INTERVAL 7 DAY);"

# Truncate log (proses öldürmədən)
> /var/log/nginx/access.log
```

## Interview sualı

"Production-da disk doldu, service çöküb — nə edirsin?"

Güclü cavab:
- "Əvvəlcə `df -h` və `df -i` — ikisini birlikdə baxıram, inode problemi fərqli fix tələb edir."
- "Sonra `du -sh /* | sort -rh` ilə hansı direktoriya böyüdüyünü müəyyənləşdirirəm."
- "Çox vaxt ya loglar böyüyüb, ya docker image-lar toplanıb, ya da silinen amma açıq fayl var. `lsof +L1` son halı tapır."
- "İlk iş: təhlükəsiz yer boşalt (köhnə rotated log-lar), service-i geri qaytar. Sonra logrotate-i düzgün konfiqurasiya edib disk > 80% alertini qururam."
