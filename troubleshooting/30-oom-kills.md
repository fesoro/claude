# OOM Kills (Senior)

## Problem (nə görürsən)

Linux OOM (Out of Memory) killer prosesləri öldürüb. PHP-FPM worker-lar, queue worker-lar, ya da bütün server prosesi gözlənilmədən crash olub. Log-da xəta mesajı yoxdur — proses sadəcə yox olur. Ya da Kubernetes pod `OOMKilled` statusu ilə restart olub.

Simptomlar:
- PHP-FPM worker-lər gözlənilmədən crash olur, `502 Bad Gateway` spike
- Queue worker-lər işi yarımçıq buraxır, `Horizon worker is missing` alert
- Kubernetes pod-lar `OOMKilled` statusu: `kubectl describe pod | grep OOMKill`
- `dmesg | grep -i "out of memory"` kernel mesajı göstərir
- `/var/log/syslog` içərisində: `oom-kill event` ya da `Killed process [PID]`
- Server cavab vermir, `ssh` ilə bağlantı yavaşlayır

## Sürətli triage (ilk 5 dəqiqə)

### OOM kill baş veribmi?

```bash
# Kernel OOM log-ları
dmesg | grep -i "out of memory\|oom-kill\|Killed process" | tail -20

# Systemd journal (daha ətraflı)
journalctl -k | grep -i "oom\|killed" | tail -20

# Syslog
grep -i "oom\|killed process" /var/log/syslog | tail -20
```

### Kubernetes-də

```bash
# Pod statusu
kubectl get pods -n production | grep -v Running

# OOMKilled pod-lar
kubectl describe pod <pod-name> -n production | grep -A5 "OOMKilled\|Last State"

# Son restart sayı
kubectl get pods -n production -o wide | awk '{print $5}' | sort -rn | head
```

### Hazırda memory vəziyyəti

```bash
free -h
vmstat -s | head -20

# Ən çox memory tutan proseslər
ps aux --sort=-%mem | head -20

# PHP-FPM worker-ların memory istifadəsi
ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB total"}'
```

## Diaqnoz

### PHP-FPM worker OOM kill

```bash
# Bir FPM worker neçə memory tutur?
ps aux | grep "php-fpm: pool" | awk '{print $6/1024 " MB", $11, $12}' | sort -rn | head -20

# php.ini memory limit
php -i | grep memory_limit
# php-fpm pool config memory limit
grep memory_limit /etc/php/8.3/fpm/php.ini /etc/php/8.3/fpm/pool.d/*.conf 2>/dev/null
```

**Typical PHP-FPM worker memory:** 30-50MB normal Laravel. 100MB+ memory leak ya da böyük dataset göstərir.

Əgər worker-lar 300MB+ tuturdusa → ya memory limit yüksəkdir, ya da leak var.

### Queue worker OOM kill

```bash
# Queue worker-ın memory istifadəsi
ps aux | grep "artisan queue:work" | awk '{print $6/1024 " MB"}'

# Horizon worker memory
php artisan horizon:status

# Worker çox uzun işləyibmi?
ps aux | grep "artisan queue:work" | awk '{print $10}'  # TIME column
```

Laravel queue worker-lar uzun işlədikdə memory böyüyür. `--max-memory` parametri var:
```bash
# /etc/supervisor/conf.d/laravel-worker.conf
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --memory=128
#                                                                 ^^^
# 128MB keçəndə worker özünü restart edər
```

### Memory leak diaqnozu

```bash
# Worker zamanla böyüyürmü?
watch -n 5 'ps aux | grep "queue:work" | awk "{print \$6/1024 \" MB\"}"'

# PHP memory profiling
php -d memory_limit=256M artisan tinker
>>> $start = memory_get_usage();
>>> App\Models\User::with('orders')->get();
>>> echo (memory_get_usage() - $start) / 1024 / 1024 . " MB";
```

### Hansı proses öldürüldü?

```bash
# Kernel OOM killer hansı prosesi seçdi?
dmesg | grep "Killed process" | tail -5
# Örnek: Killed process 12345 (php-fpm) total-vm:524288kB, anon-rss:256000kB

# OOM score — yüksək score = öldürülmə ehtimalı çox
cat /proc/$(pgrep -f "php-fpm" | head -1)/oom_score
```

## Fix (qanaxmanı dayandır)

### Anlıq: PHP-FPM-i yenidən başlat

```bash
systemctl restart php8.3-fpm
# Ya da graceful
systemctl reload php8.3-fpm
```

### PHP memory_limit-i düzəlt

```bash
# php.ini
; /etc/php/8.3/fpm/php.ini
memory_limit = 256M   # Default 128M çox aşağıdır böyük app üçün

# Pool-level override (FPM pool)
; /etc/php/8.3/fpm/pool.d/www.conf
php_admin_value[memory_limit] = 256M
```

**Limit artırmaq müvəqqəti fix-dir.** Kök səbəb memory leak ya da çox böyük dataset-i eyni anda yükləməkdir.

### Queue worker memory limit-i qur

```ini
; /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
command=php /var/www/app/artisan queue:work redis \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --memory=128
autostart=true
autorestart=true
```

`--memory=128`: 128MB keçəndə worker özünü graceful olaraq restart edər (OOM kill yox, graceful!).

### Kubernetes memory limit

```yaml
# deployment.yaml
resources:
  requests:
    memory: "256Mi"
  limits:
    memory: "512Mi"
```

Limit çox aşağıdırsa artır. Amma əvvəl **niyə bu qədər memory istifadə edir** sualını cavablandır.

### OOM killer-ı yönləndir (prod-da diqqətli)

```bash
# MySQL-i OOM kill-dən qoru
echo -17 > /proc/$(pgrep mysqld | head -1)/oom_adj

# Daha müasir
echo -1000 > /proc/$(pgrep mysqld | head -1)/oom_score_adj
# -1000 = asla öldürmə, 1000 = birinci öldür
```

## PHP/Laravel memory optimallaşdırması

```php
// Böyük dataset-lərdə chunk istifadə et
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        ProcessUser::dispatch($user->id);  // id göndər, model deyil
    }
});

// cursor() — Eloquent memory-efficient iterator
foreach (User::where('active', true)->cursor() as $user) {
    // Hər anda yalnız 1 model memory-dədir
}

// Queue job-larında memory idarəsi
class ProcessLargeReport implements ShouldQueue
{
    public int $timeout = 300;

    public function handle(): void
    {
        // Model-i job-da saxlama, ID saxla
        $users = User::select('id')->where('segment', $this->segmentId)->pluck('id');

        foreach ($users->chunk(100) as $chunk) {
            $chunk->each(fn($id) => ProcessUser::dispatch($id));
        }

        // Böyük dəyişənləri boşalt
        unset($users);
        gc_collect_cycles();
    }
}
```

## Əsas səbəbin analizi

- Hansı proses öldürüldü? Niyə bu proses seçildi (OOM score)?
- Memory usage trend: zaman keçdikcə artırdımı (memory leak)?
- Bir anda çox büyük sorğu işlədilirdimi (tek seferde 1M satır)?
- `pm.max_children` düzgün hesablanıb? (Total RAM / Per-worker memory)
- Kubernetes limit düzgün qurulub? `requests` = `limits`?

## Qarşısının alınması

```bash
# PHP-FPM worker sayını RAM-a görə hesabla
# pm.max_children = (total_RAM - OS_overhead) / per_worker_memory
# Nümunə: (4GB - 1GB) / 50MB = 60 worker

# /etc/php/8.3/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 60
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 30
pm.max_requests = 500    # 500 request sonra worker restart → memory leak qarşısı
```

```bash
# Monitoring
# Prometheus: node_memory_MemAvailable_bytes
# Alert: available memory < 10% → warning
# Alert: OOMKill event count > 0 → critical (dərhal page)
```

## Yadda saxlanacaq komandalar

```bash
# OOM kill baş veribmi?
dmesg | grep -i "out of memory\|killed process" | tail -20
journalctl -k | grep -i oom | tail -20

# PHP-FPM worker memory istifadəsi
ps aux | grep "php-fpm: pool" | awk '{sum+=$6} END {print sum/1024 " MB total"}'

# Kubernetes OOMKilled
kubectl describe pod <name> | grep -A5 "OOMKilled\|Last State"
kubectl get events --field-selector=reason=OOMKilling -n production

# Free memory
free -h

# OOM score (yüksək = öldürülə bilər)
cat /proc/$(pgrep -f "php-fpm" | head -1)/oom_score

# Per-worker memory limit (queue)
php artisan queue:work --memory=128
```

## Interview sualı

"Queue worker-lar gözlənilmədən crash olur, log-da heç nə yoxdur — nə araşdırırsan?"

Güclü cavab:
- "İlk `dmesg | grep oom` — OOM kill izləri varmı? Log-da görünmür çünki kernel prosesi birbaşa öldürür."
- "Əgər OOM kill-dirsə: worker neçə memory tutur? `ps aux | grep queue:work` — zamanla böyüyürmü? Böyüyürsə memory leak."
- "Anlıq fix: `--memory=128` flag-i queue worker-a əlavə et, graceful restart qurur."
- "Kök səbəb araşdırması: hansı job-da leak var? `chunk()` ya da `cursor()` istifadə edirikmi? Model-i tam load edirikmisə, yoxsa sadəcə id-lər?"
- "Post-incident: `pm.max_requests=500` FPM-ə əlavə etdim ki, worker 500 request sonra restart etsin. Kubernetes memory limit-ni actual istifadəyə görə `requests=limits` etdim."
