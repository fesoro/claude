# Linux Proses İdarəetmə (Middle)

## Nədir? (What is it?)

Linux-da proses (process) işləyən proqramdır. Hər prosesin unikal PID (Process ID) var. Proseslər ağac strukturunda yaranır - hər prosesin parent PID (PPID) var. İlk proses PID 1 (systemd/init) bütün digər proseslərin atasıdır.

Proses idarəetməsi DevOps üçün kritikdir - serverlərin sağlamlığını, PHP-FPM worker-larını, queue worker-ları, cron job-ları idarə etmək lazımdır.

## Əsas Konseptlər (Key Concepts)

### Proses Əsasları

```bash
# Proses states:
# R - Running (işləyir)
# S - Sleeping (gözləyir, interrupt oluna bilər)
# D - Disk sleep (gözləyir, interrupt oluna bilməz - IO wait)
# Z - Zombie (bitib amma parent wait() etməyib)
# T - Stopped (dayandırılıb, SIGSTOP/Ctrl+Z)

# Foreground vs Background
php artisan serve              # Foreground (terminal bloklayır)
php artisan serve &            # Background (& ilə)
php artisan serve > /dev/null 2>&1 &  # Background, output yox

# Job control
jobs                           # Background jobs siyahısı
fg %1                         # Job 1-i foreground-a gətir
bg %1                         # Job 1-i background-da davam etdir
Ctrl+Z                        # Foreground prosesi dayandır (SIGTSTP)
Ctrl+C                        # Foreground prosesi öldür (SIGINT)
```

### ps - Prosesləri Görmək

```bash
# Əsas istifadə
ps                             # Cari terminal prosesləri
ps aux                         # Bütün proseslər (BSD format)
ps -ef                         # Bütün proseslər (UNIX format)

# ps aux output:
# USER  PID %CPU %MEM  VSZ   RSS TTY STAT START  TIME COMMAND
# root    1  0.0  0.1 16960 10240 ?  Ss   Jan01  0:05 /sbin/init
# www-data 1234 2.5 1.2 256000 48000 ? S  10:30  0:15 php-fpm: pool www

# Filtr etmək
ps aux | grep php-fpm          # PHP-FPM prosesləri
ps aux | grep -c php-fpm       # PHP-FPM proses sayı
ps -u www-data                 # www-data user-in prosesləri
ps --forest                    # Ağac formatında göstər
ps -eo pid,ppid,cmd,%mem,%cpu --sort=-%mem | head -20  # Ən çox RAM istifadə edən

# Proses tree
pstree                         # Proses ağacı
pstree -p                      # PID ilə
pstree -p 1234                 # Bir prosesin ağacı
```

### top / htop - Real-time Monitoring

```bash
# top
top                            # Real-time proses monitoring
# top içində:
# P - CPU-ya görə sırala
# M - Memory-yə görə sırala
# k - Proses öldür
# q - Çıx
# 1 - CPU core-ları ayrı göstər
# c - Full command göstər

# top output başlığı:
# top - 10:30:00 up 45 days, load average: 0.15, 0.10, 0.05
#        ↑ uptime              ↑ 1min  5min  15min load
# Tasks: 150 total, 1 running, 149 sleeping, 0 stopped, 0 zombie
# %Cpu(s): 2.5 us, 1.0 sy, 0.0 ni, 96.0 id, 0.5 wa
#          ↑user   ↑system  ↑nice  ↑idle   ↑IO wait
# MiB Mem: 8192.0 total, 2048.0 free, 4096.0 used, 2048.0 buff/cache

# htop (daha yaxşı UI)
htop                           # Interactive proses manager
# htop xüsusiyyətləri:
# - Rəngli, scrollable
# - Mouse dəstəyi
# - Tree view (F5)
# - Filter (F4)
# - Search (F3)
# - Sort (F6)
# - Kill (F9)

# Batch mode (script-lərdə)
top -bn1 | head -20            # Bir dəfə çıxar, ilk 20 sətir
```

### kill - Prosesi Öldürmək

```bash
# Signal göndərmək
kill PID                       # SIGTERM (15) - graceful shutdown
kill -9 PID                    # SIGKILL (9) - force kill
kill -HUP PID                  # SIGHUP (1) - reload config
kill -USR1 PID                 # User-defined signal
kill -USR2 PID                 # User-defined signal
kill -STOP PID                 # Dayandır (pause)
kill -CONT PID                 # Davam et (resume)

# Vacib signal-lar:
# SIGTERM (15) - Graceful shutdown, proses cleanup edə bilər
# SIGKILL (9)  - Force kill, proses heç nə edə bilməz
# SIGHUP (1)   - Hang up, config reload üçün istifadə olunur
# SIGINT (2)   - Interrupt (Ctrl+C)
# SIGUSR1 (10) - User-defined (PHP-FPM log reopen)
# SIGUSR2 (12) - User-defined (PHP-FPM graceful restart)

# killall - ad ilə öldür
killall php-fpm                # Bütün php-fpm proseslərini öldür
killall -9 php-fpm             # Force kill

# pkill - pattern ilə öldür
pkill -f "queue:work"          # queue:work olan prosesləri öldür
pkill -u www-data              # www-data user-in proseslərini öldür

# PHP-FPM signal-ları:
kill -USR2 $(cat /run/php/php8.3-fpm.pid)  # Graceful restart
kill -USR1 $(cat /run/php/php8.3-fpm.pid)  # Reopen logs
kill -QUIT $(cat /run/php/php8.3-fpm.pid)  # Graceful stop
```

### systemd - Service Manager

```bash
# systemctl - service idarəetmə
sudo systemctl start nginx            # Başlat
sudo systemctl stop nginx             # Dayandır
sudo systemctl restart nginx          # Yenidən başlat
sudo systemctl reload nginx           # Config yenilə (downtime yox)
sudo systemctl status nginx           # Status göstər
sudo systemctl enable nginx           # Boot-da avtomatik başlasın
sudo systemctl disable nginx          # Avtomatik başlamasın
sudo systemctl is-active nginx        # Aktiv-mi?
sudo systemctl is-enabled nginx       # Enable-mi?
sudo systemctl list-units --type=service  # Bütün servislər
sudo systemctl list-units --failed    # Fail olan servislər

# journalctl - systemd logs
sudo journalctl -u nginx              # Nginx logları
sudo journalctl -u nginx -f           # Real-time follow
sudo journalctl -u nginx --since "1 hour ago"
sudo journalctl -u nginx --since "2024-01-15" --until "2024-01-16"
sudo journalctl -u php8.3-fpm -n 100  # Son 100 sətir
sudo journalctl -p err                 # Yalnız error-lar
sudo journalctl --disk-usage          # Log disk istifadəsi
```

### Custom systemd Service

```ini
# /etc/systemd/system/laravel-worker.service
[Unit]
Description=Laravel Queue Worker
After=network.target mysql.service redis.service
Wants=mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/laravel
ExecStart=/usr/bin/php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
ExecReload=/bin/kill -USR2 $MAINPID
Restart=always
RestartSec=5
StartLimitInterval=60
StartLimitBurst=3
StandardOutput=append:/var/log/laravel-worker.log
StandardError=append:/var/log/laravel-worker-error.log

[Install]
WantedBy=multi-user.target
```

```bash
# Service-i aktiv et
sudo systemctl daemon-reload
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
sudo systemctl status laravel-worker
```

### Laravel Scheduler Service

```ini
# /etc/systemd/system/laravel-scheduler.service
[Unit]
Description=Laravel Task Scheduler
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/laravel
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Cron Jobs

```bash
# crontab format:
# ┌───────────── minute (0 - 59)
# │ ┌───────────── hour (0 - 23)
# │ │ ┌───────────── day of month (1 - 31)
# │ │ │ ┌───────────── month (1 - 12)
# │ │ │ │ ┌───────────── day of week (0 - 7, 0 and 7 = Sunday)
# │ │ │ │ │
# * * * * * command

# Nümunələr:
# Hər dəqiqə
* * * * * /usr/bin/php /var/www/laravel/artisan schedule:run >> /dev/null 2>&1

# Hər gün saat 02:00
0 2 * * * /usr/local/bin/backup.sh

# Hər bazar ertəsi saat 06:00
0 6 * * 1 /usr/local/bin/weekly-report.sh

# Hər 5 dəqiqə
*/5 * * * * /usr/local/bin/healthcheck.sh

# Hər ayın 1-i saat 00:00
0 0 1 * * /usr/local/bin/monthly-cleanup.sh

# crontab idarəetmə
crontab -e                     # Redaktə et
crontab -l                     # Siyahı göstər
crontab -r                     # Bütün cron-ları sil
sudo crontab -u www-data -e   # Başqa user-in crontab-ı

# Laravel scheduler cron
* * * * * cd /var/www/laravel && php artisan schedule:run >> /dev/null 2>&1
```

### nohup, screen, tmux

```bash
# nohup - Terminal bağlansa da proses davam etsin
nohup php artisan queue:work &
nohup php artisan queue:work > /var/log/worker.log 2>&1 &

# screen - Virtual terminal
screen -S worker               # Yeni session yarat
# (əmr işlət)
# Ctrl+A, D                   # Detach (session davam edir)
screen -ls                     # Session-ları göstər
screen -r worker               # Session-a qoşul
screen -X -S worker quit       # Session-ı sil

# tmux - Terminal multiplexer (screen-dən yaxşı)
tmux new -s deploy             # Yeni session
# (əmr işlət)
# Ctrl+B, D                   # Detach
tmux ls                        # Session-lar
tmux attach -t deploy          # Session-a qoşul
tmux kill-session -t deploy    # Session-ı sil

# tmux panes
# Ctrl+B, %     # Vertical split
# Ctrl+B, "     # Horizontal split
# Ctrl+B, →     # Pane dəyiş
# Ctrl+B, x     # Pane sil
```

### Daemon Processes

```bash
# Daemon - background-da işləyən, terminal-a bağlı olmayan proses
# PID 1 (systemd) əsas daemon-dur

# Daemon xüsusiyyətləri:
# - Terminal-dan ayrılıb (detached)
# - Session leader deyil
# - Root directory / olaraq dəyişir (chdir)
# - stdin/stdout/stderr /dev/null-a yönlənir
# - PID file yaradır (/run/nginx.pid)

# PID faylları
cat /run/php/php8.3-fpm.pid    # PHP-FPM master PID
cat /run/nginx.pid              # Nginx master PID

# Daemon-un işlədiyini yoxla
if kill -0 $(cat /run/php/php8.3-fpm.pid) 2>/dev/null; then
    echo "PHP-FPM is running"
else
    echo "PHP-FPM is NOT running"
fi
```

## Praktiki Nümunələr (Practical Examples)

### PHP-FPM Proses İdarəetməsi

```bash
# PHP-FPM pool config: /etc/php/8.3/fpm/pool.d/www.conf
#
# pm = dynamic                    # Proses management method
# pm.max_children = 50            # Maksimum worker proses
# pm.start_servers = 5            # Başlanğıc worker sayı
# pm.min_spare_servers = 5        # Minimum boş worker
# pm.max_spare_servers = 35       # Maksimum boş worker
# pm.max_requests = 500           # Worker-in emal edəcəyi request sayı

# PHP-FPM proseslerini gör
ps aux | grep php-fpm
# root   1234  0.0  0.1  master process (/etc/php/8.3/fpm/php-fpm.conf)
# www-data 1235  0.1  0.5  pool www     (worker)
# www-data 1236  0.1  0.5  pool www     (worker)

# Worker sayını yoxla
ps aux | grep "php-fpm: pool" | grep -v grep | wc -l

# PHP-FPM status (pool.d config-da pm.status_path = /status)
curl http://localhost/php-fpm-status
# pool:                 www
# process manager:      dynamic
# start time:           15/Jan/2024:10:00:00
# accepted conn:        125000
# listen queue:         0          ← 0 olmalıdır, yüksəksə worker az
# active processes:     5
# idle processes:       15
# total processes:      20
# max active processes: 48

# PHP-FPM memory istifadəsi
ps -eo pid,rss,cmd | grep php-fpm | awk '{total+=$2} END {printf "Total: %.0f MB\n", total/1024}'

# Service management
sudo systemctl restart php8.3-fpm    # Cold restart (downtime)
sudo systemctl reload php8.3-fpm     # Graceful restart (no downtime)
sudo kill -USR2 $(cat /run/php/php8.3-fpm.pid)  # Graceful restart manual

# PHP-FPM slow log
# /etc/php/8.3/fpm/pool.d/www.conf:
# slowlog = /var/log/php-fpm-slow.log
# request_slowlog_timeout = 5s
tail -f /var/log/php-fpm-slow.log
```

### Resource Monitoring Script

```bash
#!/bin/bash
# monitor.sh - System resource monitoring

echo "=== System Overview ==="
echo "Hostname: $(hostname)"
echo "Uptime: $(uptime -p)"
echo "Load Average: $(cat /proc/loadavg | awk '{print $1, $2, $3}')"
echo ""

echo "=== CPU ==="
top -bn1 | head -3
echo ""

echo "=== Memory ==="
free -h
echo ""

echo "=== Disk ==="
df -h | grep -E "^/dev/"
echo ""

echo "=== PHP-FPM ==="
echo "Workers: $(ps aux | grep 'php-fpm: pool' | grep -v grep | wc -l)"
echo "Memory: $(ps -eo rss,cmd | grep php-fpm | awk '{sum+=$1} END {printf "%.0f MB\n", sum/1024}')"
echo ""

echo "=== MySQL ==="
echo "Connections: $(mysqladmin -u root status 2>/dev/null | awk '{print $4}')"
echo ""

echo "=== Nginx ==="
echo "Connections: $(curl -s http://localhost/nginx_status 2>/dev/null | head -1)"
```

### Queue Worker Supervisor Script

```bash
#!/bin/bash
# watch-workers.sh - Queue worker monitoring

WORKER_COUNT=$(ps aux | grep "artisan queue:work" | grep -v grep | wc -l)
EXPECTED=3

if [ "$WORKER_COUNT" -lt "$EXPECTED" ]; then
    echo "WARNING: Only $WORKER_COUNT/$EXPECTED queue workers running!"

    # Restart workers
    sudo systemctl restart laravel-worker

    # Notify
    curl -X POST "$SLACK_WEBHOOK" \
        -H "Content-Type: application/json" \
        -d "{\"text\": \"Queue workers restarted: $WORKER_COUNT/$EXPECTED were running\"}"
fi
```

## PHP/Laravel ilə İstifadə

### Laravel Horizon systemd Service

```ini
# /etc/systemd/system/laravel-horizon.service
[Unit]
Description=Laravel Horizon
After=network.target redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/laravel
ExecStart=/usr/bin/php artisan horizon
ExecStop=/usr/bin/php artisan horizon:terminate
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Laravel Reverb (WebSocket) Service

```ini
# /etc/systemd/system/laravel-reverb.service
[Unit]
Description=Laravel Reverb WebSocket Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/laravel
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Deployment-də Proses Yenidən Başlatma

```bash
#!/bin/bash
# post-deploy.sh

cd /var/www/laravel

# Graceful restart - mövcud request-lər tamamlansın
sudo systemctl reload php8.3-fpm

# Queue worker-ları graceful restart
php artisan queue:restart

# Horizon restart
php artisan horizon:terminate
sudo systemctl restart laravel-horizon

# Scheduler yenidən başlat
sudo systemctl restart laravel-scheduler

# Cache təmizlə
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "All services restarted"
```

## Interview Sualları

### Q1: Zombie proses nədir və necə həll olunur?
**Cavab:** Zombie proses bitmiş amma parent-i wait() system call etmədiyi üçün process table-da qalan prosesdir. `ps aux | grep Z` ilə tapılır. Həll: parent prosesi SIGCHLD ilə xəbərdar etmək, parent-i öldürmək (zombie init-ə keçir və təmizlənir). Zombie resursu istifadə etmir amma PID slot tutur.

### Q2: SIGTERM və SIGKILL fərqi nədir?
**Cavab:** SIGTERM (15) prosesə graceful shutdown imkanı verir - proses signal-ı tutub cleanup edə bilər (faylları bağla, connection-ları kəs). SIGKILL (9) prosesi dərhal öldürür, tutula bilməz, cleanup baş vermir. Həmişə əvvəl SIGTERM, cavab verməsə SIGKILL göndərmək lazımdır.

### Q3: Load average nədir?
**Cavab:** Load average son 1, 5, 15 dəqiqədə CPU-da və ya I/O-da gözləyən proseslərin orta sayıdır. Məsələn 4 CPU-lu serverdə load average 4.0 = 100% CPU istifadəsi. 8.0 = CPU-dan 2x çox proses var, gözləmə var. Normal: load average <= CPU core sayı.

### Q4: PHP-FPM process management mode-ları hansılardır?
**Cavab:** `static` - sabit worker sayı, RAM predictable; `dynamic` - min/max aralığında worker yaradır/öldürür, ən çox istifadə olunan; `ondemand` - request gələndə worker yaradır, az traffic üçün yaxşı, response time bir az artır. Production-da `dynamic` tövsiyə olunur.

### Q5: systemd nədir?
**Cavab:** Linux-un init system-idir (PID 1). Servislərin başladılması, dayandırılması, monitoring-i, dependency management, logging (journald) işlərini görür. Unit file-lar (.service, .timer, .socket) ilə konfiqurasiya olunur. `systemctl` ilə idarə olunur.

### Q6: Proses background-da necə işlədilir?
**Cavab:** Üç yol: 1) `command &` - amma terminal bağlansa dayandığı olur. 2) `nohup command &` - terminal bağlansa da davam edir. 3) systemd service - ən yaxşı yol, avtomatik restart, logging, dependency management verir. Production-da systemd istifadə olunmalıdır.

## Best Practices

1. **systemd istifadə edin** - nohup/screen yerinə systemd service yaradın
2. **Graceful shutdown** - Həmişə əvvəl SIGTERM, sonra SIGKILL
3. **Resource limits** - ulimit və systemd LimitNOFILE ilə limitlər qoyun
4. **Monitoring** - top/htop əvvzinə Prometheus + Grafana ilə monitoring qurun
5. **PHP-FPM tuning** - Worker sayını RAM-a görə hesablayın
6. **Log rotation** - journalctl və logrotate ilə log-ları idarə edin
7. **Cron vs systemd timer** - Yeni setup-larda systemd timer istifadə edin
8. **Health checks** - Servislərin sağlamlığını avtomatik yoxlayın
9. **Restart policies** - systemd Restart=always ilə servisləri avtomatik bərpa edin
10. **OOM Killer** - Critical servislərdə OOMScoreAdjust=-1000 ilə qoruyun

---

## Praktik Tapşırıqlar

1. Laravel üçün systemd service unit yazın: `laravel-worker.service` faylı yaradın, `User=www-data`, `Restart=always`, `RestartSec=3`, `StandardOutput=journal`, `StandardError=journal` konfiqurasiya edin; enable + start edin, `journalctl -u laravel-worker -f` ilə log izləyin
2. PHP-FPM pool konfiqurasiyasını RAM-a görə hesablayın: mövcud RAM-ı, hər FPM worker-in yaddaş istifadəsini tapın; `pm = static`, `pm.max_children` dəyərini hesablayın; `php-fpm -tt` ilə konfiqurasiya yoxlayın
3. `top`/`htop` ilə yüksək CPU/RAM istifadəsi edən prosesləri tapın; `strace -p <pid>` ilə prosesin nə etdiyini izləyin; `lsof -p <pid>` ilə açıq fayl/socket-ləri görün
4. Cron idarəetməsi: `crontab -e` ilə Laravel scheduler üçün `* * * * * php /var/www/artisan schedule:run` əlavə edin; `cron.log`-a çıxışı redirect edin; `systemd timer` ilə eyni işi görün və müqayisə edin
5. Zombie proses senariyosunu simulyasiya edin: bash script-lə zombie yaradın, `ps aux | grep Z` ilə tapın, parent proses-i kill edərək zombie-ni təmizləyin
6. OOM Killer davranışını test edin: `stress --vm 1 --vm-bytes 90%` ilə RAM doldurun; `dmesg | grep -i "oom"` ilə hansı prosesin kill edildiyini görün; PHP-FPM üçün `OOMScoreAdjust=-500` ilə qoruma əlavə edin

## Əlaqəli Mövzular

- [Linux Əsasları](01-linux-basics.md) — fayl sistemi, əsas komandalar
- [Linux Shell Scripting](10-linux-shell-scripting.md) — bash skriptləri, deployment avtomatlaşması
- [Performance Tuning](30-performance-tuning.md) — PHP-FPM tuning, OPcache, sysctl
- [Nginx](11-nginx.md) — Nginx worker_processes, PHP-FPM socket
- [CI/CD Deployment](39-cicd-deployment.md) — deployment pipeline-da proses idarəsi
