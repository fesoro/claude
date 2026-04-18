# Queue Backlog

## Problem (nə görürsən)
Laravel queue-nda minlərlə pending job var və boşalmır. İstifadəçilər fərq edir: email gec gəlir, bildirişlər itir, webhook deliveries gecikir. Horizon dashboard qırmızıdır. Redis memory qalxır, çünki job-lar yığılır.

Simptomlar:
- Horizon dashboard: "pending jobs" sayı böyüyür
- `LLEN queues:default` böyük rəqəmlər qaytarır
- İstifadəçi bildirişləri: "mən email-imi almadım"
- Redis memory qrafiki qalxır
- Dəqiqədə işlənən job-lar azalır

## Sürətli triage (ilk 5 dəqiqə)

### Backlog-a bax

```bash
# Horizon status
php artisan horizon:status

# Job counts per queue
redis-cli LLEN queues:default
redis-cli LLEN queues:emails
redis-cli LLEN queues:high
redis-cli LLEN queues:low

# Failed jobs count
php artisan queue:failed | wc -l
```

Və ya Horizon dashboard-u `/horizon/dashboard` vasitəsilə:
- Pending Jobs
- Past Hour Throughput
- Failed Jobs
- Job class başına Runtime

### Heç bir worker həqiqətən işləyir?

```bash
# Horizon process check
ps aux | grep "horizon" | grep -v grep

# Or via supervisor
supervisorctl status | grep horizon
```

Horizon işləmir → işə sal:
```bash
php artisan horizon
# Or via supervisor
supervisorctl start horizon
```

## Diaqnoz

### Backlog-u təsnif et

Üç mümkün səbəb:
1. **Yavaş job-lar** — worker-lar sağdır amma hər job çox uzun çəkir
2. **Az worker** — capacity-dən çox gəlir
3. **İlişib qalmış/crash olan worker-lar** — worker job qəbul etdi, ack etmədən öldü

Throughput-u yoxla:
```bash
# Horizon metric
php artisan horizon:snapshot      # takes a snapshot
# Then check Horizon dashboard "Throughput" chart
```

Əgər throughput = 0-dırsa, worker-lar işləmir. Throughput aşağı amma sabitdirsə, capacity məhdudlaşıb.

### Yavaş job class-ı tap

Horizon dashboard → `Metrics` → `Jobs` class başına runtime göstərir.

Və ya Redis vasitəsilə query et:
```bash
# Horizon stores runtime metrics
redis-cli HGETALL horizon:metrics:snapshot
```

### İp-ucları üçün failed job-ları yoxla

```bash
php artisan queue:failed
```

Çıxış:
```
+----+-------------+---------+------------------------------+---------------------+
| ID | Connection  | Queue   | Job                          | Failed At           |
+----+-------------+---------+------------------------------+---------------------+
| 1  | redis       | default | App\Jobs\SendInvoiceEmail    | 2026-04-17 14:35:12 |
```

Birini nəzərdən keçir:
```bash
php artisan queue:failed:show 1
```

Əgər bütün failure-lar eyni class-dandırsa, o class sınıqdır — düzəlt.

### Worker crash döngüsü

```bash
# Recent Horizon supervisor logs
tail -f storage/logs/horizon.log

# Supervisor logs
tail -f /var/log/supervisor/horizon-stderr.log
```

Ümumi crash səbəbləri:
- OOM (memory limiti çatıb)
- PHP extension-da segfault
- Database connection drop (Horizon yenidən qoşulur, amma job itir)
- Redis reconnect uğursuzluqları

## Fix (qanaxmanı dayandır)

### Variant 1: Dərhal worker-ları scale et

```php
// config/horizon.php
'production' => [
    'supervisor-1' => [
        'maxProcesses' => 20,   // up from 10
        // ...
    ],
],
```

```bash
php artisan horizon:terminate
# Supervisor respawns with new config
```

Horizon-un auto-scaling-i ilə:
```php
'balance' => 'auto',
'minProcesses' => 5,
'maxProcesses' => 50,
```

### Variant 2: Aşağı prioritetli queue-ları pause et

```bash
php artisan horizon:pause-supervisor supervisor-low
```

Yüksək prioritet üçün capacity azad edir.

### Variant 3: Backlog-u atla (drastik)

Əgər backlog köhnədirsə və kritik deyilsə:
```bash
# CAUTION: deletes all pending jobs in a queue
redis-cli DEL queues:default
redis-cli DEL queues:default:notify

# Laravel-native
php artisan queue:clear redis --queue=default
```

Yalnız istifadəçilər fərq etməyəcəksə (məs., vaxtı keçmiş bildirişlər) bunu et.

### Variant 4: Failed job-ları retry et

```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job IDs
php artisan queue:retry 1 2 3

# Retry by class
php artisan queue:retry --queue=default
```

Əgər root cause düzəldilməyibsə, yenə fail olacaqlar. Əvvəlcə düzəlt.

### Variant 5: Queue daxilində prioritetləşdir

Laravel queue-ları default olaraq FIFO-dur. Təcili job-lar üçün:
```php
dispatch(new UrgentJob())->onQueue('high');
// workers config
'queue' => ['high', 'default', 'low'],
```

Worker default-dan əvvəl high, low-dan əvvəl default işləyir.

## Əsas səbəbin analizi

Incident sonrası:
- Hansı job class backlog-a səbəb oldu?
- Niyə yavaşladı? (Xarici API yavaş? DB query regressed? Data həcmi böyüdü?)
- Worker-lar bu yük üçün əvvəldən az ölçülü idi?
- Niyə daha tez alert almadıq?

## Qarşısının alınması

- Alert: pending job > 1000 > 5 dəq
- Alert: failed job sayı spike (yeni failure-lar, yalnız kumulyativ yox)
- Dashboard: job class başına throughput
- Yeni job-ları real həcm ilə load test et
- Job daxilində hər xarici call-da timeout
- Daimi failure-lar üçün dead letter queue pattern
- Horizon auto-scaling aktivləşdirilib
- Prioritetə görə çoxsaylı queue (high/default/low)

## PHP/Laravel üçün qeydlər

### Horizon config əsasları

```php
// config/horizon.php
'defaults' => [
    'supervisor-1' => [
        'connection' => 'redis',
        'queue' => ['high', 'default'],
        'balance' => 'auto',
        'autoScalingStrategy' => 'time', // or 'size'
        'minProcesses' => 1,
        'maxProcesses' => 10,
        'maxTime' => 0,
        'maxJobs' => 0,
        'memory' => 128,
        'tries' => 3,
        'timeout' => 60,
        'nice' => 0,
    ],
],
```

- `balance: auto` — queue-lar arasında worker-ları dinamik dəyişdir
- `autoScalingStrategy: time` — wait time-i aşağı saxlamaq üçün scale
- `memory: 128` — > 128MB olsa worker-i restart et
- `tries: 3` — fail olmazdan əvvəl job-u 3 dəfə retry et

### Dead letter queue pattern

```php
class SendEmail implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;
    
    public function failed(Throwable $e): void
    {
        // Move to DLQ for human review
        DB::table('dead_letter_jobs')->insert([
            'job_class' => static::class,
            'payload' => json_encode($this),
            'error' => $e->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
```

### Job timeout

```php
class LongRunningJob implements ShouldQueue
{
    public int $timeout = 120;   // 2 min
    
    // Required for job timeout to work on long-running queries:
    public int $retryAfter = 130;
}
```

Həmçinin `config/horizon.php`-də supervisor səviyyəsində təyin et.

### Horizon pause/resume

```bash
# Pause all workers
php artisan horizon:pause

# Resume
php artisan horizon:continue

# Pause specific supervisor
php artisan horizon:pause-supervisor supervisor-1

# Terminate (supervisor respawns fresh)
php artisan horizon:terminate
```

## Yadda saxlanacaq komandalar

```bash
# Horizon
php artisan horizon
php artisan horizon:status
php artisan horizon:terminate
php artisan horizon:pause
php artisan horizon:continue
php artisan horizon:snapshot

# Queue
php artisan queue:work --queue=high,default --tries=3 --timeout=60
php artisan queue:listen redis
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all
php artisan queue:clear redis --queue=default
php artisan queue:forget 5       # delete one failed job
php artisan queue:flush          # delete all failed jobs

# Redis queue depth
redis-cli LLEN queues:default
redis-cli LLEN queues:default:notify

# List all queues
redis-cli --scan --pattern 'queues:*'
```

## Interview sualı

"Runaway queue backlog-u necə idarə edirsən?"

Güclü cavab:
- "Əvvəlcə: worker-ların həqiqətən işlədiyini təsdiqləyirəm. `horizon:status`, `supervisorctl`."
- "Throughput-u yoxlayıram. Sıfır — worker-lar ölüdür. Aşağı amma sabit — capacity problemi. Çox dəyişkən — job-ların bir hissəsi çox yavaşdır."
- "Horizon dashboard job class başına runtime göstərir — yavaşı tez tapıram."
- "Qısamüddət: Horizon max processes vasitəsilə worker-ları scale et, və ya aşağı prioritetli queue-ları pause edərək capacity azad et."
- "Əgər backlog köhnədirsə və kritik deyilsə: təmizlə. Əvvəlcə stakeholder-lərlə kommunikasiya."
- "Root cause adətən: xarici API yavaş, DB regresiya və ya data həcmi worker scale-ə uyğun olmadan böyüdü."
- "Qarşısının alınması: pending jobs üçün dashboard, eşikdə alert, auto-scaling Horizon."

Bonus hekayə: "SendGrid inteqrasiyamız zirvədə rate limit-ə çatdı. Job-lar retry etdi, queue-da 50k yığıldı. Email-ləri batch edərək (1-in yerinə 100/req), ayrıca adanmış-throughput queue əlavə edərək və rate-limit səhvlərində backoff qoyaraq düzəltdik."
