# Linux Shell Scripting (Middle)

## Nədir? (What is it?)

Shell scripting əmrləri avtomatlaşdırmaq üçün skript yazmaqdır. Bash (Bourne Again Shell) Linux-da ən çox istifadə olunan shell-dir. DevOps mühəndisi üçün deployment, monitoring, backup, server konfiqurasiyası kimi tapşırıqları avtomatlaşdırmaq üçün Bash scripting bilmək vacibdir.

## Əsas Konseptlər (Key Concepts)

### Bash Script Əsasları

```bash
#!/bin/bash
# Shebang - scriptın hansı interpreter ilə işləyəcəyini bildirir

# Script yaratmaq və icra etmək
echo '#!/bin/bash' > script.sh
echo 'echo "Hello World"' >> script.sh
chmod +x script.sh
./script.sh
# və ya
bash script.sh
```

### Dəyişənlər (Variables)

```bash
#!/bin/bash

# Dəyişən təyin etmək (= ətrafında boşluq OLMAMALIDIR)
NAME="Laravel"
VERSION=11
APP_DIR="/var/www/laravel"

# İstifadə etmək
echo "App: $NAME version $VERSION"
echo "Directory: ${APP_DIR}/public"    # {} mürəkkəb hallarda

# Xüsusi dəyişənlər
echo $0          # Script adı
echo $1          # Birinci argument
echo $2          # İkinci argument
echo $#          # Argument sayı
echo $@          # Bütün argumentlər (ayrı-ayrı)
echo $*          # Bütün argumentlər (bir string)
echo $?          # Son əmrin exit kodu (0=uğurlu)
echo $$          # Mövcud process ID
echo $!          # Son background process ID

# Command substitution
CURRENT_DATE=$(date +%Y-%m-%d)
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}')
PHP_VERSION=$(php -v | head -1 | cut -d' ' -f2)

# Environment dəyişənləri
export APP_ENV="production"
export DB_HOST="localhost"

# Read - istifadəçidən input
read -p "Enter server name: " SERVER_NAME
read -sp "Enter password: " PASSWORD    # -s: gizli input

# Arrays
SERVERS=("web1" "web2" "web3" "db1")
echo ${SERVERS[0]}           # İlk element: web1
echo ${SERVERS[@]}           # Bütün elementlər
echo ${#SERVERS[@]}          # Element sayı: 4
SERVERS+=("db2")             # Əlavə et
```

### Şərtlər (Conditions)

```bash
#!/bin/bash

# if-then-else
if [ "$APP_ENV" = "production" ]; then
    echo "Production mode"
elif [ "$APP_ENV" = "staging" ]; then
    echo "Staging mode"
else
    echo "Development mode"
fi

# [[ ]] - daha güclü test (Bash-a xas)
if [[ "$APP_ENV" == "prod"* ]]; then     # Pattern matching
    echo "Production-like environment"
fi

# Ədəd müqayisəsi
if [ $DISK_USAGE -gt 80 ]; then          # Greater than
    echo "Disk usage high!"
fi
# -eq (equal), -ne (not equal), -lt (less than)
# -le (less or equal), -gt (greater than), -ge (greater or equal)

# String müqayisəsi
if [ -z "$VAR" ]; then echo "Empty"; fi         # String boşdur
if [ -n "$VAR" ]; then echo "Not empty"; fi     # String boş deyil
if [ "$A" = "$B" ]; then echo "Equal"; fi       # Bərabərdir

# Fayl testləri
if [ -f "/etc/nginx/nginx.conf" ]; then   # Fayl mövcuddur
    echo "Nginx config exists"
fi
if [ -d "/var/www/laravel" ]; then        # Qovluq mövcuddur
    echo "Laravel directory exists"
fi
if [ -r "$FILE" ]; then echo "Readable"; fi     # Oxuna bilər
if [ -w "$FILE" ]; then echo "Writable"; fi     # Yazıla bilər
if [ -x "$FILE" ]; then echo "Executable"; fi   # İcra oluna bilər
if [ -s "$FILE" ]; then echo "Not empty"; fi    # Boş deyil

# AND / OR
if [ -f ".env" ] && [ -f "artisan" ]; then
    echo "This is a Laravel project"
fi

if [ "$ENV" = "dev" ] || [ "$ENV" = "local" ]; then
    echo "Development environment"
fi

# Case statement
case $1 in
    start)
        echo "Starting..."
        ;;
    stop)
        echo "Stopping..."
        ;;
    restart)
        echo "Restarting..."
        ;;
    *)
        echo "Usage: $0 {start|stop|restart}"
        exit 1
        ;;
esac
```

### Döngülər (Loops)

```bash
#!/bin/bash

# for loop
for SERVER in web1 web2 web3; do
    echo "Deploying to $SERVER"
    ssh $SERVER "cd /var/www/laravel && git pull"
done

# for loop - range
for i in {1..10}; do
    echo "Iteration $i"
done

# for loop - C style
for ((i=0; i<5; i++)); do
    echo "Count: $i"
done

# for loop - fayl üzərindən
for FILE in /var/www/laravel/storage/logs/*.log; do
    echo "Log file: $FILE ($(du -sh $FILE | cut -f1))"
done

# while loop
COUNT=0
while [ $COUNT -lt 5 ]; do
    echo "Count: $COUNT"
    COUNT=$((COUNT + 1))
done

# while - fayl oxumaq
while IFS= read -r LINE; do
    echo "Processing: $LINE"
done < servers.txt

# while - command output
df -h | while read LINE; do
    echo "$LINE"
done

# until loop
until ping -c 1 google.com &>/dev/null; do
    echo "Waiting for network..."
    sleep 2
done
echo "Network is up!"

# break / continue
for i in {1..10}; do
    if [ $i -eq 5 ]; then continue; fi    # 5-i keç
    if [ $i -eq 8 ]; then break; fi       # 8-dən sonra dayan
    echo $i
done
```

### Funksiyalar

```bash
#!/bin/bash

# Funksiya təyin etmək
deploy() {
    local SERVER=$1        # local - funksiya daxili dəyişən
    local BRANCH=${2:-main}  # Default dəyər

    echo "Deploying $BRANCH to $SERVER..."
    ssh $SERVER "cd /var/www/laravel && git fetch && git checkout $BRANCH && git pull"
    ssh $SERVER "cd /var/www/laravel && composer install --no-dev"
    ssh $SERVER "cd /var/www/laravel && php artisan migrate --force"
    ssh $SERVER "cd /var/www/laravel && php artisan config:cache"

    return 0    # Uğurlu
}

# Funksiyanı çağırmaq
deploy "web1" "main"
deploy "web2" "release/v2.1"

# Return dəyərini yoxlamaq
if deploy "web3"; then
    echo "Deploy successful"
else
    echo "Deploy failed"
fi

# Funksiyadan dəyər qaytarmaq (echo ilə)
get_php_version() {
    local VERSION=$(php -v | head -1 | cut -d' ' -f2)
    echo $VERSION
}
PHP_VER=$(get_php_version)
echo "PHP Version: $PHP_VER"

# Error handling funksiyası
die() {
    echo "ERROR: $1" >&2
    exit ${2:-1}
}

[ -f ".env" ] || die ".env file not found"
```

### sed (Stream Editor)

```bash
# Mətni əvəz etmək
sed 's/old/new/' file.txt            # İlk rast gəlinəni
sed 's/old/new/g' file.txt           # Hamısını (global)
sed -i 's/old/new/g' file.txt        # Faylı dəyiş (in-place)

# Laravel .env dəyişmək
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
sed -i "s|DB_HOST=.*|DB_HOST=${DB_HOST}|" .env    # Dəyişən ilə

# Sətir silmək
sed -i '/^#/d' file.txt              # Komment sətirlərini sil
sed -i '/^$/d' file.txt              # Boş sətirləri sil
sed -i '5d' file.txt                 # 5-ci sətiri sil

# Sətir əlavə etmək
sed -i '3a\New line after line 3' file.txt    # 3-cü sətirdən sonra
sed -i '3i\New line before line 3' file.txt   # 3-cü sətirdən əvvəl

# Çox əmr
sed -i -e 's/foo/bar/g' -e 's/baz/qux/g' file.txt
```

### awk (Pattern Processing)

```bash
# Sütunları çap etmək
awk '{print $1}' file.txt                    # 1-ci sütun
awk '{print $1, $3}' file.txt               # 1-ci və 3-cü sütun
awk -F: '{print $1}' /etc/passwd             # : separator ilə

# Nginx access log analizi
awk '{print $1}' access.log | sort | uniq -c | sort -rn | head -20
# Ən çox request edən IP-lər

awk '$9 == 500 {print $7}' access.log | sort | uniq -c | sort -rn
# 500 error verən URL-lər

awk '{sum += $10} END {print sum/1024/1024 " MB"}' access.log
# Ümumi traffic

# Şərt ilə
awk '$3 > 80 {print $1, $3"%"}' disk_usage.txt
# 80%-dən çox istifadə olunan disklar

# BEGIN / END
awk 'BEGIN {print "Report"} {total+=$1} END {print "Total:", total}' numbers.txt

# Laravel log analizi
awk '/ERROR/ {count++} END {print "Errors:", count}' laravel.log
awk '/\[.*\] production\.ERROR/' storage/logs/laravel.log | tail -20
```

### grep (Pattern Search)

```bash
# Əsas istifadə
grep "error" /var/log/syslog                 # "error" olan sətirlər
grep -i "error" /var/log/syslog              # Case-insensitive
grep -r "DB_HOST" /var/www/laravel/          # Rekursiv axtar
grep -rn "TODO" /var/www/laravel/app/        # Sətir nömrəsi ilə
grep -v "^#" /etc/nginx/nginx.conf           # Komment OLMAYAN sətirlər
grep -c "ERROR" laravel.log                  # Sayını göstər
grep -l "password" /var/www/laravel/.env*    # Fayl adlarını göstər
grep -A3 "error" log.txt                     # Error + sonrakı 3 sətir
grep -B2 "error" log.txt                     # Error + əvvəlki 2 sətir
grep -E "error|warning|critical" log.txt     # Regex (egrep)

# Laravel-də istifadə
grep -rn "dd(" app/ --include="*.php"        # dd() çağırışları tap
grep -rn "env(" app/ --include="*.php"       # env() istifadəsi
grep -rn "sleep(" app/ --include="*.php"     # sleep() çağırışları
```

### cron (Scheduled Tasks)

```bash
# cron format: dakika saat gün ay həftə_günü əmr
# *     *     *    *    *
# |     |     |    |    |
# |     |     |    |    +-- Həftə günü (0-7, 0 və 7 = bazar)
# |     |     |    +------- Ay (1-12)
# |     |     +------------ Gün (1-31)
# |     +------------------ Saat (0-23)
# +------------------------ Dəqiqə (0-59)

# crontab əmrləri
crontab -e                    # Crontab-ı redaktə et
crontab -l                    # Mövcud cron job-ları göstər
crontab -r                    # Bütün cron job-ları sil

# Nümunələr
* * * * *          # Hər dəqiqə
*/5 * * * *        # Hər 5 dəqiqə
0 * * * *          # Hər saat
0 0 * * *          # Hər gün gecə yarısı
0 0 * * 0          # Hər bazar günü
0 2 * * *          # Hər gün saat 02:00
30 1 1 * *         # Hər ayın 1-i saat 01:30

# Laravel scheduler üçün cron
* * * * * cd /var/www/laravel && php artisan schedule:run >> /dev/null 2>&1

# Backup cron
0 2 * * * /opt/scripts/backup.sh >> /var/log/backup.log 2>&1

# Log rotation
0 0 * * * find /var/www/laravel/storage/logs -name "*.log" -mtime +30 -delete
```

## Praktiki Nümunələr (Practical Examples)

### Laravel Deployment Script

```bash
#!/bin/bash
# deploy.sh - Laravel production deployment

set -euo pipefail    # e: error-da dayan, u: undefined var error, o pipefail

# Konfiqurasiya
APP_DIR="/var/www/laravel"
BRANCH="${1:-main}"
BACKUP_DIR="/var/backups/laravel"
LOG_FILE="/var/log/deploy.log"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Rəngli output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +%H:%M:%S)]${NC} $1" | tee -a $LOG_FILE
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a $LOG_FILE
    exit 1
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a $LOG_FILE
}

# Pre-flight checks
log "Starting deployment of branch: $BRANCH"

[ -d "$APP_DIR" ] || error "App directory not found: $APP_DIR"
[ -f "$APP_DIR/.env" ] || error ".env file not found"
command -v php >/dev/null || error "PHP not installed"
command -v composer >/dev/null || error "Composer not installed"

# Backup
log "Creating backup..."
mkdir -p "$BACKUP_DIR"
tar czf "$BACKUP_DIR/backup_$TIMESTAMP.tar.gz" \
    --exclude="$APP_DIR/vendor" \
    --exclude="$APP_DIR/node_modules" \
    "$APP_DIR" 2>/dev/null || warn "Backup warning (continuing)"

# Maintenance mode
log "Enabling maintenance mode..."
cd "$APP_DIR"
php artisan down --retry=60 --secret="deploy-$TIMESTAMP" || true

# Git pull
log "Pulling latest code..."
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

# Composer install
log "Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Database migration
log "Running migrations..."
php artisan migrate --force

# Cache
log "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Assets (əgər frontend varsa)
if [ -f "package.json" ]; then
    log "Building assets..."
    npm ci --production=false
    npm run build
fi

# Permissions
log "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# PHP-FPM restart
log "Restarting PHP-FPM..."
sudo systemctl reload php8.3-fpm

# Queue restart
log "Restarting queue workers..."
php artisan queue:restart

# Disable maintenance mode
log "Disabling maintenance mode..."
php artisan up

# Healthcheck
log "Running healthcheck..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)
if [ "$HTTP_CODE" -eq 200 ]; then
    log "Deployment successful! HTTP $HTTP_CODE"
else
    error "Healthcheck failed! HTTP $HTTP_CODE"
fi

# Köhnə backup-ları təmizlə (30 gündən köhnə)
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +30 -delete

log "Deployment completed in $SECONDS seconds"
```

### Server Health Check Script

```bash
#!/bin/bash
# health-check.sh - Server sağlamlıq yoxlaması

ALERT_EMAIL="devops@company.com"
HOSTNAME=$(hostname)

check_disk() {
    local THRESHOLD=80
    while IFS= read -r LINE; do
        local USAGE=$(echo "$LINE" | awk '{print $5}' | tr -d '%')
        local MOUNT=$(echo "$LINE" | awk '{print $6}')
        if [ "$USAGE" -gt "$THRESHOLD" ] 2>/dev/null; then
            echo "CRITICAL: Disk $MOUNT is ${USAGE}% full"
        fi
    done < <(df -h | grep "^/dev")
}

check_memory() {
    local MEM_USAGE=$(free | awk '/Mem:/ {printf "%.0f", $3/$2 * 100}')
    if [ "$MEM_USAGE" -gt 90 ]; then
        echo "CRITICAL: Memory usage is ${MEM_USAGE}%"
    fi
}

check_cpu() {
    local CPU_LOAD=$(uptime | awk -F'load average: ' '{print $2}' | cut -d, -f1 | xargs)
    local NUM_CPU=$(nproc)
    local THRESHOLD=$(echo "$NUM_CPU * 2" | bc)
    if (( $(echo "$CPU_LOAD > $THRESHOLD" | bc -l) )); then
        echo "CRITICAL: CPU load is $CPU_LOAD (CPUs: $NUM_CPU)"
    fi
}

check_services() {
    local SERVICES=("nginx" "php8.3-fpm" "mysql" "redis-server")
    for SERVICE in "${SERVICES[@]}"; do
        if ! systemctl is-active --quiet "$SERVICE"; then
            echo "CRITICAL: $SERVICE is not running"
        fi
    done
}

check_laravel() {
    local APP_DIR="/var/www/laravel"
    if [ -f "$APP_DIR/artisan" ]; then
        local HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -m 5 http://localhost)
        if [ "$HTTP_CODE" -ne 200 ]; then
            echo "CRITICAL: Laravel returning HTTP $HTTP_CODE"
        fi

        # Queue health
        local FAILED=$(cd "$APP_DIR" && php artisan queue:failed 2>/dev/null | wc -l)
        if [ "$FAILED" -gt 10 ]; then
            echo "WARNING: $FAILED failed jobs in queue"
        fi
    fi
}

# Ana hissə
ISSUES=""
ISSUES+=$(check_disk)
ISSUES+=$(check_memory)
ISSUES+=$(check_cpu)
ISSUES+=$(check_services)
ISSUES+=$(check_laravel)

if [ -n "$ISSUES" ]; then
    echo "=== Health Check FAILED on $HOSTNAME ==="
    echo "$ISSUES"
    echo "$ISSUES" | mail -s "Health Alert: $HOSTNAME" "$ALERT_EMAIL" 2>/dev/null || true
    exit 1
else
    echo "=== Health Check PASSED on $HOSTNAME ==="
    exit 0
fi
```

## PHP/Laravel ilə İstifadə

### Process əmrləri Laravel-dən çağırmaq

```php
<?php
// Symfony Process ilə shell əmrləri
use Symfony\Component\Process\Process;

// Sadə əmr
$process = new Process(['df', '-h']);
$process->run();
echo $process->getOutput();

// Deployment trigger
$process = new Process(['/opt/scripts/deploy.sh', 'main']);
$process->setTimeout(300);
$process->run(function ($type, $buffer) {
    if (Process::ERR === $type) {
        logger()->error('Deploy: ' . $buffer);
    } else {
        logger()->info('Deploy: ' . $buffer);
    }
});

if (!$process->isSuccessful()) {
    throw new \RuntimeException('Deployment failed');
}
```

### Laravel Scheduler (cron əvəzi)

```php
<?php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Hər dəqiqə
    $schedule->command('queue:work --stop-when-empty')
        ->everyMinute()
        ->withoutOverlapping();

    // Hər gün
    $schedule->command('backup:run')
        ->dailyAt('02:00')
        ->onOneServer();

    // Hər həftə
    $schedule->command('telescope:prune')
        ->weekly()
        ->sundays()
        ->at('03:00');

    // Shell əmri
    $schedule->exec('/opt/scripts/health-check.sh')
        ->everyFiveMinutes()
        ->emailOutputOnFailure('devops@company.com');
}
```

## Interview Sualları

### S1: set -euo pipefail nə edir?
**C:** `-e` hər hansı əmr uğursuz olduqda scripti dayandırır. `-u` undefined dəyişən istifadə edildikdə error verir. `-o pipefail` pipe-da istənilən əmr uğursuz olduqda bütün pipe-ın exit kodunu non-zero edir. Bu üçlük birlikdə scripti daha etibarlı edir - production scriptlərində mütləq istifadə olunmalıdır.

### S2: $@ və $* arasında fərq nədir?
**C:** Dırnaq içində fərqli davranırlar. `"$@"` hər argumenti ayrı string kimi verir: `"arg1" "arg2" "arg3"`. `"$*"` bütün argumentləri bir string kimi verir: `"arg1 arg2 arg3"`. Funksiyalara argument ötürəndə `"$@"` istifadə etmək daha doğrudur çünki boşluqlu argumentləri qoruyur.

### S3: sed ilə faylda dəyişiklik necə edilir?
**C:** `sed -i 's/köhnə/yeni/g' fayl.txt` - `-i` in-place redaktə edir, `s` substitute əmridir, `g` bütün rast gəlinənləri dəyişir. `/` əvəzinə `|` və ya başqa separator istifadə etmək olar: `sed -i 's|/old/path|/new/path|g'`. macOS-da `-i ''` lazımdır.

### S4: awk nə vaxt istifadə olunur?
**C:** awk sütun-əsaslı mətn emalında güclüdür. Log analizi, CSV emalı, report yaratma üçün istifadə olunur. `awk '{print $1}' file` birinci sütunu çap edir. `-F` ilə separator təyin olunur. BEGIN/END blokları, dəyişənlər, şərtlər, döngülər dəstəklənir. grep tapır, sed dəyişir, awk analiz edir.

### S5: Cron job işləmir, necə debug edərsiniz?
**C:** 1) `crontab -l` ilə job-un var olduğunu yoxla, 2) Cron log: `/var/log/cron` və ya `grep CRON /var/log/syslog`, 3) PATH problemi ola bilər - tam yol istifadə et, 4) Output redirect: `>> /tmp/cron.log 2>&1`, 5) Permission: script executable olmalıdır, 6) Environment: cron minimal env ilə işləyir - `env` dəyişənləri script daxilində set et, 7) Sətir sonunda newline olmalıdır.

### S6: Shell scriptdə error handling necə edilir?
**C:** 1) `set -e` ilə error-da dayan, 2) `trap 'cleanup' EXIT` ilə çıxışda təmizlik, 3) `command || { echo "Failed"; exit 1; }` ilə individual əmr error handling, 4) `if ! command; then handle_error; fi` pattern, 5) `$?` ilə son əmrin exit kodunu yoxla. Production scriptlərdə `set -euo pipefail` və `trap` birlikdə istifadə olunmalıdır.

## Best Practices

1. **set -euo pipefail** hər scriptin başında olsun
2. **Dəyişənləri dırnaq içinə alın** - `"$VAR"` - boşluqlu dəyərlərdə problem olmaz
3. **local** dəyişənlər funksiyalarda istifadə edin - global scope-u çirkləndirməyin
4. **Meaningful exit codes** istifadə edin - 0=uğurlu, 1=ümumi error, 2=istifadə xətası
5. **Log funksiyası** yazın - timestamp, rəng, fayla yazmaq
6. **Trap istifadə edin** - `trap cleanup EXIT` ilə təmizlik
7. **ShellCheck** istifadə edin - `shellcheck script.sh` ilə lint
8. **İnput validation** edin - argumentləri yoxlayın
9. **Idempotent** scriptlər yazın - təkrar icra eyni nəticəni versin
10. **README/usage** əlavə edin - `--help` flag dəstəkləyin
