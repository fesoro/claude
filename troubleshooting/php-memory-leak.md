# PHP Memory Leak

## Problem (nə görürsən)
Uzun müddət işləyən PHP prosesləri saatlar və ya günlər ərzində yaddaşı yeyir. Nəticədə OS OOM-kill edir və ya `Allowed memory size ... exhausted` xətası alırsan. Tipik olaraq görürsən:

- Horizon worker-ların RAM-ı 60MB → 512MB → killed
- Supervisor worker-ları hər bir neçə saatdan bir restart edir
- Job-lar `Fatal error: Allowed memory size of X bytes exhausted` ilə fail olur
- GC təzyiqi artdıqca tədrici p99 latency qalxması
- Memory chart: worker-lar restart olursa sawtooth; olmursa staircase

Vacibdir: standart PHP-FPM web request-ləri nadir hallarda "leak" edir. PHP request-scoped-dir — hər şey request-in sonunda azad olunur. Memory leak-lər **uzun müddət işləyən** PHP üçün vacibdir: queue worker-ları, Horizon, Octane, planlı daemon-lar, WebSocket server-ləri.

## Sürətli triage (ilk 5 dəqiqə)

### Bu həqiqətən leak-dirmi, yoxsa sadəcə böyük job?

```bash
# Per-process memory
ps aux --sort=-rss | grep "queue:work\|horizon" | head

# Memory over time for a specific PID
while true; do ps -o rss= -p $PID; sleep 10; done
```

Əgər memory fərqli tipli job-lar arasında davamlı artırsa, leak ehtimal olunur. Bir böyük job zamanı artır sonra düşürsə, sadəcə o bir job-un memory lazımdır.

### Worker restart ilə qanaxmanı dayandır

Laravel queue worker-larının memory guard-ı var:

```bash
php artisan queue:work --max-memory=512 --max-jobs=1000
```

Horizon `config/horizon.php`-də:
```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'maxProcesses' => 10,
            'memory' => 128,      // Restart worker if over 128MB
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

`memory` = MB. Aşıldıqda, worker təmiz çıxır, supervisor yenidən spawn edir.

## Diaqnoz

### Worker daxilində memory-ni ölç

Job handler-ə əlavə et:

```php
public function handle()
{
    $before = memory_get_usage(true);
    
    // ... job work ...
    
    $after = memory_get_usage(true);
    $delta = ($after - $before) / 1024 / 1024;
    $peak = memory_get_peak_usage(true) / 1024 / 1024;
    
    Log::info('Job memory', [
        'job' => static::class,
        'delta_mb' => round($delta, 2),
        'peak_mb' => round($peak, 2),
        'rss_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
    ]);
}
```

Bir neçə saat işlət. Sonra:
```bash
grep "Job memory" storage/logs/laravel.log \
  | jq -s 'group_by(.job) | map({job: .[0].job, avg_delta: (map(.delta_mb) | add / length)})'
```

Bir çox run boyu sıfırdan fərqli orta delta olan job-lar = leak namizədləri.

### PHP/Laravel-də ümumi səbəblər

1. **Singleton-larda static cache-lər** əbədi böyüyür
   ```php
   class Thing {
       protected static array $cache = [];
       public function lookup($k) {
           return self::$cache[$k] ??= ExpensiveLookup($k); // grows forever
       }
   }
   ```

2. **Event listener yığılması**: döngü daxilində listener qeydiyyatı
   ```php
   foreach ($users as $user) {
       Event::listen(UserThing::class, fn() => ...); // leak
   }
   ```

3. **Eloquent global scope-lar** reference saxlayır
4. **Cache-lənmiş query builder-lər**: `DB::listen()` bütün query-ləri saxlayır
5. **Guzzle connection-ları** azad olunmur
6. **Laravel Telescope** production-da açıq qalıb
7. **Circular reference-lər** GC həmişə tuta bilmir
8. **Prosesdə qalan PHPUnit Prophecy-style test double-lar**

### Memory profiling alətləri

- **Blackfire** — queue job çağırışını profile et:
  ```bash
  blackfire run php artisan queue:work --once
  ```
  Hər function üzrə memory allocation göstərir.

- **Tideways** — davamlı production profiler. Aşağı overhead.

- **XHProf** — open source, daha az cilalı amma pulsuz.
  ```bash
  pecl install xhprof
  ```
  Job handler-i sar:
  ```php
  xhprof_enable(XHPROF_FLAGS_MEMORY);
  // work
  $data = xhprof_disable();
  ```

- **spx** — CLI profiler, worker-lar üçün əla:
  ```bash
  SPX_ENABLED=1 php artisan queue:work
  ```

### Saxlanan obyektləri tap

Job sonunda `get_defined_vars()` təəccüblü dərəcədə faydalı işarələr verir. Daha dərin iş üçün:

```php
// Count reachable objects of each class
$classes = [];
foreach ($GLOBALS as $k => $v) {
    if (is_object($v)) {
        $classes[get_class($v)][] = $k;
    }
}
```

Laravel container üçün:
```php
// In a tinker or temporary route
$bindings = array_keys(app()->getBindings());
$resolved = array_keys(app()->getBindings()); // bindings vs resolved differs
```

## Fix (qanaxmanı dayandır)

Qısamüddətli (dəqiqələr):
1. Horizon config-də `memory`-ni aşağı sal ki, worker-lar daha tez-tez restart olsun
2. Churn-u ödəmək üçün worker sayını artır
3. Əgər bir job tipi günahkardırsa, düzələnə qədər həmin queue-nu pause et

Ortamüddətli (saatlar):
1. Leak edən kod yolunu tap
2. Patch və deploy et
3. RSS sabit olduğunu yoxla

Uzunmüddətli (günlər):
1. Dashboard kimi memory metriklər əlavə et
2. Worker memory > eşik üçün alert
3. Regression test

## Əsas səbəbin analizi

Incident sonrası:
- Hansı job tipi leak edirdi?
- Hansı kod pattern buna səbəb oldu?
- Yeni kod idi, yoxsa uzun müddət var idi amma yeni data aktivləşdirdi?
- Niyə staging tutmadı? (Adətən: yetərincə yük yoxdur, yetərincə vaxt yoxdur.)

## Qarşısının alınması

- Horizon-da `memory` config mühafizəkar (128-256 MB)
- Hər queue worker-ında `--max-memory`
- `--max-jobs=N` worker-ları dövri olaraq rotasiya etmək üçün (təzə proses yaddaşı)
- Metriklər: Prometheus-a export edilmiş `worker_rss_bytes`
- Alert: worker RSS > 400MB > 10 dəq
- Yeni queue job-ları 1000+ iterasiya ilə load test et
- Collection data saxlayan singleton-lardan qaç
- Static array əvəzinə TTL ilə `Cache::remember` istifadə et

## PHP/Laravel üçün qeydlər

### Laravel Octane xəbərdarlığı

Octane framework-i request-lər arasında boot olaraq saxlayır. Əvvəllər "request-scoped" olan hər şey indi leak edə bilər:
- Static propertiler
- Service container state
- Facade resolver-lər
- Event listener-lər

Octane request-lər arasında `flush()` ilə təmizləyir — amma yalnız bəzi şeyləri. Diqqətlə test et.

### Eloquent chunking

Böyük datasetləri iterasiya edirsən? `chunk` və ya `cursor` istifadə et:

```php
// Bad — loads everything
User::all()->each(fn($u) => $u->process());

// Good — chunks
User::chunk(500, fn($users) => $users->each->process());

// Best — lazy cursor, one record at a time
User::cursor()->each(fn($u) => $u->process());
```

### Worker-larda model event-ləri təmizlə

```php
// Between job iterations
\Illuminate\Database\Eloquent\Model::clearBootedModels();
```

### Query log-u sıfırla

Əgər `DB::listen()` aktivdirsə:
```php
DB::flushQueryLog();
```

## Yadda saxlanacaq komandalar

```bash
# Process memory (RSS in KB)
ps -o pid,rss,command -p $(pgrep -f "queue:work") | sort -k2 -rn

# Top 10 php processes by memory
ps aux --sort=-rss | grep php | head -10

# Horizon status
php artisan horizon:status

# Restart Horizon workers
php artisan horizon:terminate

# Force GC in a loop (temporary mitigation)
gc_collect_cycles();

# Run a single job for profiling
php artisan queue:work --once

# Blackfire profile
blackfire run --samples 1 php artisan queue:work --once
```

## Interview sualı

"Düzəltdiyin memory leak-i təsvir et."

Güclü cavab:
- "Horizon worker-ları 8 saat ərzində 60MB-dan 500MB-a çatırdı, sonra supervisor tərəfindən OOM-killed olunurdu."
- "Job başına memory logging əlavə etdim. Bir job class — `GenerateInvoicesBatch` — run başına ~5MB leak edirdi. Digərləri təmiz idi."
- "Həmin job-u Blackfire ilə profile etdim. `PricingCalculator` singleton-da unikal SKU kombinasiyaları ilə böyüyən static cache tapdım. Bir gündə ~100k SKU yığıldı."
- "Fix: cache-i 1 saat TTL-li Redis LRU-ya köçürdüm, 10k entry-lə məhdud."
- "Yoxlanıldı: deploy sonrası 24 saat worker RSS 80MB-da sabit."
- "Grafana-ya `worker_rss_bytes` metriki əlavə etdim, 400MB-da alert."

Müsahibə siqnalı: PHP-nin memory modelini (request-scoped vs long-running) başa düşürsən, doğru alətlər istifadə edirsən, fix-i metriklə yoxlayırsan.
