# PHP Performance və Profiling (Middle)

## Mündəricat
1. [PHP Execution Lifecycle](#php-execution-lifecycle)
2. [OPcache Konfiqurasiya](#opcache-konfiqurasiya)
3. [PHP-FPM Tuning](#php-fpm-tuning)
4. [Laravel Bootstrap Optimization](#laravel-bootstrap-optimization)
5. [Memory Management və Garbage Collection](#memory-management-və-garbage-collection)
6. [Profiling Alətləri](#profiling-alətləri)
7. [Database Optimization](#database-optimization)
8. [Eager Loading vs Lazy Loading](#eager-loading-vs-lazy-loading)
9. [chunk, chunkById, lazy, lazyById](#chunk-chunkbyid-lazy-lazybyid)
10. [LazyCollection](#lazycollection)
11. [PHP Generator-lar](#php-generatorlar)
12. [Laravel Octane](#laravel-octane)
13. [Queue Worker Optimization](#queue-worker-optimization)
14. [Redis vs Database Cache/Session](#redis-vs-database-cachesession)
15. [Benchmarking Alətləri](#benchmarking-alətləri)
16. [İntervyu Sualları](#intervyu-sualları)

---

## PHP Execution Lifecycle

Hər PHP request-i aşağıdakı mərhələlərdən keçir:

### Ənənəvi PHP (FPM) lifecycle

```
1. Request gəlir (nginx/apache)
        ↓
2. PHP-FPM worker prosesi seçilir
        ↓
3. PHP faylı oxunur (disk I/O)
        ↓
4. Lexer — tokenization (PHP kodu token-lərə bölünür)
        ↓
5. Parser — Abstract Syntax Tree (AST) yaranır
        ↓
6. Compiler — Opcodes (bytecode) yaranır
        ↓
7. Zend Engine — Opcodes icra edilir (execute)
        ↓
8. Response göndərilir
        ↓
9. Worker prosesi sıfırlanır (shutdown)
```

### OPcache ilə lifecycle (3-6 addımlar keçilir)

```
1. Request gəlir
        ↓
2. PHP-FPM worker seçilir
        ↓
3. OPcache-də opcodes axtarılır
   ├── CACHE HIT  → birbaşa execute
   └── CACHE MISS → compile → cache-ə yaz → execute
        ↓
4. Response göndərilir
        ↓
5. Worker sıfırlanır
```

### Laravel bootstrap addımları

*Laravel bootstrap addımları üçün kod nümunəsi:*
```php
// public/index.php
define('LARAVEL_START', microtime(true));

// 1. Autoloader yüklə
require __DIR__.'/../vendor/autoload.php';

// 2. Application instance yarat
$app = require_once __DIR__.'/../bootstrap/app.php';

// 3. HTTP Kernel yarat
$kernel = $app->make(Kernel::class);

// 4. Request handle et (middleware + routing + controller)
$response = $kernel->handle(
    $request = Request::capture()
)->send();

// 5. Terminate (after-response işlər)
$kernel->terminate($request, $response);
```

Hər request-də Laravel yenidən bootstrap olur — bütün ServiceProvider-lar, config, route-lar yenidən yüklənir. **Octane** bunu aradan qaldırır.

---

## OPcache Konfiqurasiya

OPcache PHP-nin compiled bytecode-larını RAM-da cache-ləyir. Disk I/O və compile overhead-i aradan qaldıraraq performansı 2-10x artırır.

### php.ini / opcache.ini konfiqurasiya

*php.ini / opcache.ini konfiqurasiya üçün kod nümunəsi:*
```ini
; OPcache aktiv et
opcache.enable=1
opcache.enable_cli=0          ; CLI-də adətən lazım deyil

; Memory
opcache.memory_consumption=256        ; MB, compiled script-lər üçün
opcache.interned_strings_buffer=32    ; MB, string intern pool üçün
opcache.max_accelerated_files=20000   ; Max cached fayl sayı

; Revalidation (production)
opcache.validate_timestamps=0         ; Fayl dəyişikliyini yoxlama (prod üçün)
opcache.revalidate_freq=0             ; validate_timestamps=1 olduqda yoxlama intervalı (sn)

; Development üçün
; opcache.validate_timestamps=1
; opcache.revalidate_freq=2

; JIT (PHP 8.0+)
opcache.jit=tracing               ; tracing | function | on | off
opcache.jit_buffer_size=128M      ; JIT compiled kod üçün buffer

; Digər optimallaşdırmalar
opcache.fast_shutdown=1           ; Daha sürətli shutdown
opcache.save_comments=1           ; Annotasiyalar üçün lazım (Doctrine, PHPUnit)
opcache.load_comments=1

; Preloading (PHP 7.4+)
opcache.preload=/var/www/html/preload.php
opcache.preload_user=www-data
```

### Preloading (PHP 7.4+)

Server start-up zamanı müəyyən faylları OPcache-ə yükləyir. Hər request-də bu fayllar artıq compile edilmiş olur.

*Server start-up zamanı müəyyən faylları OPcache-ə yükləyir. Hər reques üçün kod nümunəsi:*
```php
// preload.php
<?php

// Laravel framework fayllarını preload et
$files = glob('/var/www/html/vendor/laravel/framework/src/**/*.php');

foreach ($files as $file) {
    // interface və abstract class-lar tələb oluna bilər
    require_once $file;
}

// Application core faylları
require_once '/var/www/html/app/Models/User.php';
require_once '/var/www/html/app/Http/Kernel.php';
```

### OPcache statusunu yoxlamaq

*OPcache statusunu yoxlamaq üçün kod nümunəsi:*
```php
// opcache_status
$status = opcache_get_status();

echo "Cache full: " . ($status['cache_full'] ? 'Yes' : 'No') . "\n";
echo "Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
echo "Cache hits: " . $status['opcache_statistics']['hits'] . "\n";
echo "Cache misses: " . $status['opcache_statistics']['misses'] . "\n";
echo "Hit rate: " . round($status['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
echo "Memory used: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
echo "Memory free: " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
```

### Deployment-də OPcache reset

*Deployment-də OPcache reset üçün kod nümunəsi:*
```bash
# PHP-FPM restart (OPcache tam sıfırlanır)
sudo systemctl restart php8.2-fpm

# Ya da artisan command ilə (Opcache::reset() çağırır)
php artisan opcache:clear

# Laravel Octane ilə
php artisan octane:reload
```

---

## PHP-FPM Tuning

PHP-FPM (FastCGI Process Manager) PHP prosesləri idarə edir.

### /etc/php/8.2/fpm/pool.d/www.conf

*/etc/php/8.2/fpm/pool.d/www.conf üçün kod nümunəsi:*
```ini
[www]
user = www-data
group = www-data

listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data

; Process Manager rejimi
; static   — pm.max_children daima aktiv
; dynamic  — tələbə görə artıb-azalır (tövsiyyə edilən)
; ondemand — yalnız request gəldikdə proses yaranır (az traffic üçün)
pm = dynamic

; ƏSAS PARAMETRLƏR
; Maksimum eyni anda aktiv ola bilən worker sayı
; Formula: pm.max_children = Available RAM / (Average PHP process RAM)
; Məsələn: 4GB RAM, 50MB/proses → 4096/50 = ~80
pm.max_children = 50

; FPM başladıqda yaranan ilkin proses sayı
pm.start_servers = 10

; Minimum idle worker sayı
pm.min_spare_servers = 5

; Maksimum idle worker sayı
pm.max_spare_servers = 20

; Bir worker neçə request-dən sonra restart olsun (memory leak qorunma)
pm.max_requests = 500

; Request timeout
request_terminate_timeout = 60s

; Slow request log (3 saniyədən uzun sorğular)
request_slowlog_timeout = 3s
slowlog = /var/log/php-fpm/slow.log

; Status endpoint
pm.status_path = /fpm-status
ping.path = /fpm-ping
```

### PHP-FPM statusunu izləmək

*PHP-FPM statusunu izləmək üçün kod nümunəsi:*
```bash
# Canlı status
curl http://localhost/fpm-status?full

# Çıxış nümunəsi:
# pool:                 www
# process manager:      dynamic
# start time:           01/Jan/2024:00:00:00 +0000
# start since:          86400
# accepted conn:        125000
# listen queue:         0           ← bu 0 olmalıdır!
# listen queue len:     128
# idle processes:       8
# active processes:     2
# total processes:      10
# max active processes: 25
# max children reached: 0           ← bu 0 olmalıdır!

# Canlı izləmə
watch -n 1 "curl -s http://localhost/fpm-status"
```

### PHP memory limitləri

*PHP memory limitləri üçün kod nümunəsi:*
```ini
; php.ini
memory_limit = 256M        ; Bir PHP prosesinin max RAM istifadəsi
max_execution_time = 30    ; Max icra müddəti (saniyə)
max_input_time = 60        ; Input oxuma müddəti
upload_max_filesize = 64M
post_max_size = 64M
```

---

## Laravel Bootstrap Optimization

### Artisan cache komandaları

*Artisan cache komandaları üçün kod nümunəsi:*
```bash
# Config cache — bütün config faylları bir PHP faylına birləşdirir
php artisan config:cache
# bootstrap/cache/config.php yaranır

# Route cache — bütün route-ları compile edir
php artisan route:cache
# bootstrap/cache/routes-v7.php yaranır

# View cache — Blade şablonlarını PHP-yə compile edir
php artisan view:cache
# storage/framework/views/ altında .php faylları yaranır

# Event cache — event listener mapping-lərini cache-ləyir
php artisan event:cache
# bootstrap/cache/events.php yaranır

# Hamısını bir anda
php artisan optimize
# = config:cache + route:cache + event:cache

# Cache-ləri təmizlə
php artisan optimize:clear
# = config:clear + route:clear + view:clear + event:clear + cache:clear
```

### Development-də xəbərdarlıq

*Development-də xəbərdarlıq üçün kod nümunəsi:*
```bash
# Config cache-ləndikdə .env dəyişiklikləri ÖZ-ÖZÜNƏ əks OLUNMUR!
php artisan config:clear  # config cache-i sil

# Route cache-ləndikdə closure route-lar işləmir
# Bu route route:cache ilə uyğun DEYİL:
Route::get('/test', function () {  # ← Closure
    return 'test';
});

# Bunun əvəzinə Controller istifadə edin:
Route::get('/test', [TestController::class, 'index']);
```

### Laravel-in bootstrap performansını ölçmək

*Laravel-in bootstrap performansını ölçmək üçün kod nümunəsi:*
```php
// app/Http/Middleware/MeasureBootstrapTime.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MeasureBootstrapTime
{
    public function handle(Request $request, Closure $next): mixed
    {
        $bootstrapTime = microtime(true) - LARAVEL_START;

        $response = $next($request);

        $response->headers->set('X-Bootstrap-Time', round($bootstrapTime * 1000, 2) . 'ms');

        return $response;
    }
}
```

---

## Memory Management və Garbage Collection

### PHP Memory Management

PHP referans sayma (reference counting) əsaslı memory management istifadə edir. Bir dəyişənin referans sayı 0-a düşdükdə avtomatik azad edilir.

*PHP referans sayma (reference counting) əsaslı memory management istif üçün kod nümunəsi:*
```php
// Bu kod PHP-nin Copy-on-Write mexanizminin yaddaşa təsirini göstərir
$a = ['large' => str_repeat('x', 1024 * 1024)]; // 1MB array
$b = $a;  // Copy-on-write: hələ ki eyni memory bölgəsi

$b['key'] = 'value';  // İndi $b kopyalanır (COW triggered)

unset($a);  // $a-nın referansı azalır
unset($b);  // Memory azad olunur
```

### Memory istifadəsini izləmək

*Memory istifadəsini izləmək üçün kod nümunəsi:*
```php
// Cari memory istifadəsi
echo memory_get_usage();                    // bytes
echo memory_get_usage(true);               // real allocation (OS-dən alınan)
echo memory_get_usage() / 1024 / 1024 . ' MB';

// Peak memory
echo memory_get_peak_usage() / 1024 / 1024 . ' MB';

// Laravel-də
Log::info('Memory: ' . round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB');
```

### Böyük dataset-lərdə memory optimallaşdırma

*Böyük dataset-lərdə memory optimallaşdırma üçün kod nümunəsi:*
```php
// Pis: bütün 100k record-u RAM-a yükləyir
$users = User::all();  // 100k * ~1KB = ~100MB RAM!

foreach ($users as $user) {
    processUser($user);
}

// Yaxşı: chunk ilə
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        processUser($user);
    }
});

// Daha yaxşı: lazy collection ilə
User::lazy()->each(function ($user) {
    processUser($user);
});
```

### Circular Reference və GC

PHP-nin standart referans sayma mexanizmi circular reference-ləri aşkar edə bilmir. Bunun üçün Cycle Collector (GC) mövcuddur.

*PHP-nin standart referans sayma mexanizmi circular reference-ləri aşka üçün kod nümunəsi:*
```php
// Bu kod dairəvi referansların memory leak yaratmasını göstərir
class Node
{
    public ?Node $parent = null;
    public array $children = [];
}

$parent = new Node();
$child = new Node();
$child->parent = $parent;     // circular reference
$parent->children[] = $child;

unset($parent, $child);
// Referans sayı 0-a düşmür! GC lazımdır.

// Manual GC
gc_collect_cycles();
echo gc_collect_cycles() . " cycles collected\n";
```

### PHP GC konfiqurasiya

*PHP GC konfiqurasiya üçün kod nümunəsi:*
```php
// GC statusu
var_dump(gc_enabled());  // bool(true)

// GC deaktiv et (qısa müddətli skriptlər üçün daha sürətli)
gc_disable();

// GC aktivləşdir
gc_enable();

// php.ini
// zend.enable_gc = 1  (default: aktiv)
```

---

## Profiling Alətləri

### Xdebug

Development mühitində ən geniş yayılmış profiler.

*Development mühitində ən geniş yayılmış profiler üçün kod nümunəsi:*
```ini
; php.ini / xdebug.ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=profile,debug,trace
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_output_name=cachegrind.out.%p.%r
xdebug.start_with_request=trigger   ; ?XDEBUG_PROFILE=1 ilə başlat
```

*xdebug.start_with_request=trigger   ; ?XDEBUG_PROFILE=1 ilə başlat üçün kod nümunəsi:*
```bash
# Profile trigger
curl "http://localhost/api/users?XDEBUG_PROFILE=1"

# KCacheGrind ilə vizualizasiya
kcachegrind /tmp/xdebug/cachegrind.out.12345
```

### Blackfire.io

Production-grade profiler. Overhead-i minimaldır (Xdebug-dan 1000x daha az).

*Production-grade profiler. Overhead-i minimaldır (Xdebug-dan 1000x dah üçün kod nümunəsi:*
```bash
# PHP extension quraşdır
pecl install blackfire

# php.ini
# extension=blackfire.so
# blackfire.agent_socket = unix:///var/run/blackfire/agent.sock

# CLI profiling
blackfire run php artisan your:command

# Curl ilə profiling
blackfire curl http://localhost/api/endpoint

# Continuous profiling (PHP 8.2+)
# Blackfire Continuous Profiler kodu
```

*Blackfire Continuous Profiler kodu üçün kod nümunəsi:*
```php
// Blackfire Probe ilə kod bloku profiling
$blackfire = new \Blackfire\Client();
$probe = $blackfire->createProbe();

// profil edilən kod
$result = heavyComputation();

$blackfire->endProbe($probe);
```

### Laravel Telescope

Development ortamında request, query, job, log, exception-ları izləyir.

*Development ortamında request, query, job, log, exception-ları izləyir üçün kod nümunəsi:*
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

*php artisan migrate üçün kod nümunəsi:*
```php
// Telescope-u production-da yalnız admin üçün aktivləşdirmək
// app/Providers/TelescopeServiceProvider.php

protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}

// Yalnız development-də quraşdır
public function register(): void
{
    if ($this->app->environment('local')) {
        $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }
}
```

### Laravel Debugbar

Request başlıq panelinə performance məlumatları əlavə edir.

*Request başlıq panelinə performance məlumatları əlavə edir üçün kod nümunəsi:*
```bash
composer require barryvdh/laravel-debugbar --dev
```

*composer require barryvdh/laravel-debugbar --dev üçün kod nümunəsi:*
```php
// Debugbar ilə manual ölçmə
Debugbar::startMeasure('heavy-operation', 'Heavy Operation');
performHeavyOperation();
Debugbar::stopMeasure('heavy-operation');

// Message log
Debugbar::info($data);
Debugbar::error('Something went wrong');

// Timeline
Debugbar::measure('Database Query', function () {
    return User::with('roles')->get();
});
```

---

## Database Optimization

### EXPLAIN ANALYZE

Query execution plan-ını görmək üçün.

*Query execution plan-ını görmək üçün üçün kod nümunəsi:*
```sql
-- PostgreSQL
EXPLAIN ANALYZE SELECT * FROM users WHERE email = 'test@example.com';

-- MySQL
EXPLAIN SELECT * FROM users WHERE email = 'test@example.com';
EXPLAIN FORMAT=JSON SELECT * FROM users WHERE email = 'test@example.com';
```

*EXPLAIN FORMAT=JSON SELECT * FROM users WHERE email = 'test@example.co üçün kod nümunəsi:*
```php
// Laravel-də query explain
$query = User::where('email', 'test@example.com');

// Raw SQL görüntülə
dd($query->toSql(), $query->getBindings());

// EXPLAIN çalışdır
$explain = DB::select('EXPLAIN ' . $query->toSql(), $query->getBindings());
dd($explain);
```

### N+1 Problem Detection

*N+1 Problem Detection üçün kod nümunəsi:*
```php
// N+1 Problem: 1 query posts + N query comments (hər post üçün)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments->count();  // Hər iterasiyada yeni query!
}

// Həll: Eager loading
$posts = Post::with('comments')->get();
foreach ($posts as $post) {
    echo $post->comments->count();  // Query yoxdur, artıq yüklənib
}
```

### N+1 detection avtomatlaşdırma

*N+1 detection avtomatlaşdırma üçün kod nümunəsi:*
```php
// AppServiceProvider::boot()
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Development-də N+1 aşkar et
    if (app()->environment('local')) {
        Model::preventLazyLoading();
    }

    // Production-da log et (exception verməsin)
    Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
        $class = get_class($model);
        info("Attempted to lazy load [{$relation}] on model [{$class}].");
    });
}
```

---

## Eager Loading vs Lazy Loading

### Lazy Loading (Default Eloquent)

İlişkili model ilk dəfə access ediləndə query işlədilir.

*İlişkili model ilk dəfə access ediləndə query işlədilir üçün kod nümunəsi:*
```php
// Bu kod Eloquent-in müxtəlif əlaqə yükləmə üsullarını göstərir
$user = User::find(1);
// SELECT * FROM users WHERE id = 1

$posts = $user->posts;
// SELECT * FROM posts WHERE user_id = 1  ← Ayrı query!

foreach ($posts as $post) {
    $comments = $post->comments;
    // SELECT * FROM comments WHERE post_id = ?  ← Hər post üçün ayrı query! N+1!
}
```

### Eager Loading

İlişkiləri öncədən yükləyir.

*İlişkiləri öncədən yükləyir üçün kod nümunəsi:*
```php
// with() — eager loading
$users = User::with('posts')->get();
// SELECT * FROM users
// SELECT * FROM posts WHERE user_id IN (1, 2, 3, ...)  ← 2 query, N+1 yoxdur

// Nested eager loading
$users = User::with('posts.comments')->get();
// 3 query: users, posts, comments

// Şərtli eager loading
$users = User::with(['posts' => function ($query) {
    $query->where('published', true)
          ->orderByDesc('created_at')
          ->limit(5);
}])->get();

// Select sütunları limitlə (yalnız lazım olan sütunlar)
$users = User::with(['posts:id,user_id,title,created_at'])->get();
```

### withCount, withSum, withMin, withMax, withAvg

*withCount, withSum, withMin, withMax, withAvg üçün kod nümunəsi:*
```php
// Ayrı query açmadan aggregate hesabla
$users = User::withCount('posts')
             ->withSum('orders', 'total')
             ->withMax('orders', 'created_at')
             ->get();

foreach ($users as $user) {
    echo $user->posts_count;
    echo $user->orders_sum_total;
    echo $user->orders_max_created_at;
}
```

### load() — Lazy Eager Loading

Modellər artıq yükləndikdən sonra əlavə eager load etmək.

*Modellər artıq yükləndikdən sonra əlavə eager load etmək üçün kod nümunəsi:*
```php
// Bu kod Eloquent chunk() metodunun böyük dataset-ləri yaddaş effektiv emal etməsini göstərir
$users = User::all();

// Şərtə görə sonradan yüklə
if (request('include_posts')) {
    $users->load('posts');
}
```

---

## chunk, chunkById, lazy, lazyById

Böyük dataset-ləri yaddaşa yükləmədən emal etmək üçün istifadə edilir.

### chunk()

*chunk() üçün kod nümunəsi:*
```php
// 1000-lik partiyalarla emal et
User::where('active', true)
    ->chunk(1000, function ($users) {
        foreach ($users as $user) {
            // emal
            $user->sendWeeklyReport();
        }

        // False qaytarılarsa loop dayanır
        // return false;
    });
```

**Xəbərdarlıq**: `chunk()` daxilindəki sıralama bazasındakı sıra ilə eyni olmalıdır. Chunk zamanı sıralama dəyişərsə (məsələn, status yeniləndikdən sonra), bəzi record-lar iki dəfə emal edilə bilər.

### chunkById()

`chunk()` ilə müqayisədə daha təhlükəsiz — `id`-yə görə cursor-based pagination istifadə edir.

*`chunk()` ilə müqayisədə daha təhlükəsiz — `id`-yə görə cursor-based p üçün kod nümunəsi:*
```php
// chunk() ilə problem: chunk əsnasında cərgə silinib/dəyişilərsə
// bəzi cərgələr buraxıla bilər.

// chunkById() bu problemi həll edir
User::where('active', true)
    ->chunkById(1000, function ($users) {
        foreach ($users as $user) {
            $user->update(['processed' => true]);
        }
    });

// Custom primary key ilə
User::chunkById(1000, function ($users) {
    // ...
}, 'uuid');
```

### lazy()

PHP Generator istifadə edərək `cursor()` kimi işləyir, lakin Collection interfeysi saxlayır.

*PHP Generator istifadə edərək `cursor()` kimi işləyir, lakin Collectio üçün kod nümunəsi:*
```php
// Generator əsaslı — eyni anda yalnız bir record RAM-da
User::where('active', true)
    ->lazy()   // LazyCollection qaytarır
    ->each(function ($user) {
        $user->sendWeeklyReport();
    });

// Pipe ilə filter chain qura bilərsiniz
User::lazy()
    ->filter(fn($user) => $user->isVerified())
    ->map(fn($user) => $user->email)
    ->each(fn($email) => Mail::to($email)->send(new WeeklyReport()));
```

### lazyById()

`lazy()` + `chunkById()` xüsusiyyətlərini birləşdirir.

*`lazy()` + `chunkById()` xüsusiyyətlərini birləşdirir üçün kod nümunəsi:*
```php
User::where('active', true)
    ->lazyById(1000)  // 1000-lik batch-larla lazily yüklə
    ->each(function ($user) {
        $user->processReport();
    });

// Custom column
User::lazyById(1000, 'uuid');
```

### cursor()

Bir query icra edib, hər cərgəni Generator ilə qaytarır. Ən az RAM istifadə edir.

*Bir query icra edib, hər cərgəni Generator ilə qaytarır. Ən az RAM ist üçün kod nümunəsi:*
```php
// Yalnız bir model eyni anda RAM-da
foreach (User::cursor() as $user) {
    $user->sendEmail();
}
```

### Müqayisə cədvəli

| Metod | RAM İstifadəsi | N+1 Riski | Emal əsnasında dəyişiklik |
|---|---|---|---|
| `all()` | Çox yüksək | Var | Təhlükəli deyil |
| `chunk()` | Orta | Yoxdur | Riskli |
| `chunkById()` | Orta | Yoxdur | Təhlükəsiz |
| `cursor()` | Çox az | Var | Riskli |
| `lazy()` | Çox az | Yoxdur | Riskli |
| `lazyById()` | Çox az | Yoxdur | Təhlükəsiz |

---

## LazyCollection

Laravel `LazyCollection` PHP Generator-larını Collection API ilə birləşdirir. Böyük faylları, sonsuz dataset-ləri emal etmək üçün idealdır.

*Laravel `LazyCollection` PHP Generator-larını Collection API ilə birlə üçün kod nümunəsi:*
```php
// Bu kod LazyCollection ilə böyük dataset-lərin lazy (tənbəl) emalını göstərir
use Illuminate\Support\LazyCollection;

// Böyük faylı emal etmək
$lazyCollection = LazyCollection::make(function () {
    $handle = fopen('/path/to/huge-log-file.csv', 'r');

    while (($line = fgets($handle)) !== false) {
        yield str_getcsv($line);  // Generator
    }

    fclose($handle);
});

// Yalnız filter edilən, ilk 100 element RAM-da
$lazyCollection
    ->skip(1)                           // header sətri keç
    ->filter(fn($row) => $row[2] > 100) // şərt
    ->take(100)                          // ilk 100
    ->each(function ($row) {
        ProcessCsvRow::dispatch($row);
    });
```

### LazyCollection ilə API pagination

*LazyCollection ilə API pagination üçün kod nümunəsi:*
```php
$allUsers = LazyCollection::make(function () {
    $page = 1;

    do {
        $response = Http::get("https://api.example.com/users", [
            'page' => $page++,
            'per_page' => 100,
        ]);

        $users = $response->json('data');

        foreach ($users as $user) {
            yield $user;
        }
    } while (!empty($users));
});

// Hamısını emal et — hər addımda yalnız 100 user RAM-da
$allUsers
    ->filter(fn($user) => $user['active'])
    ->each(fn($user) => importUser($user));
```

---

## PHP Generator-lar

Generator-lar dəyərləri hesablandıqca qaytaran, icrasını yarıda saxlaya bilən funksiyalardır. `yield` açar sözü istifadə edilir.

*Generator-lar dəyərləri hesablandıqca qaytaran, icrasını yarıda saxlay üçün kod nümunəsi:*
```php
// Adi funksiya — bütün nəticəni array-ə yükləyir
function getNumbers(int $from, int $to): array
{
    $numbers = [];
    for ($i = $from; $i <= $to; $i++) {
        $numbers[] = $i;
    }
    return $numbers;  // Böyük aralıqda çox RAM!
}

// Generator — hər dəfə bir dəyər qaytarır
function generateNumbers(int $from, int $to): \Generator
{
    for ($i = $from; $i <= $to; $i++) {
        yield $i;  // Pause et, dəyəri ver, davam et
    }
}

// Fərq
$numbers = getNumbers(1, 1_000_000);       // ~50MB RAM
$generator = generateNumbers(1, 1_000_000); // ~1KB RAM

foreach ($generator as $number) {
    echo $number . "\n";
}
```

### Key-value yield

*Key-value yield üçün kod nümunəsi:*
```php
// Bu kod Generator ilə böyük CSV faylını yaddaş effektiv oxumanı göstərir
function csvReader(string $file): \Generator
{
    $handle = fopen($file, 'r');
    $headers = str_getcsv(fgets($handle));

    while (($line = fgets($handle)) !== false) {
        $values = str_getcsv($line);
        yield array_combine($headers, $values);  // key => value
    }

    fclose($handle);
}

foreach (csvReader('users.csv') as $row) {
    echo $row['email'] . "\n";
}
```

### Generator send() — iki tərəfli kommunikasiya

*Generator send() — iki tərəfli kommunikasiya üçün kod nümunəsi:*
```php
// Bu kod Generator ilə iki tərəfli kommunikasiyanı (send ilə) göstərir
function logger(): \Generator
{
    $log = [];
    while (true) {
        $message = yield count($log);  // Gözlə, dəyər al, uzunluğu qaytar
        if ($message === null) break;
        $log[] = date('Y-m-d H:i:s') . ' - ' . $message;
    }
    return $log;  // final return
}

$gen = logger();
$gen->current();           // Generator-u başlat

$count = $gen->send('User logged in');    // 1
$count = $gen->send('Order created');     // 2
$count = $gen->send('Payment processed'); // 3
$gen->send(null);          // Bitir

$logs = $gen->getReturn(); // Final return dəyərini al
```

### Pipeline pattern ilə Generator

*Pipeline pattern ilə Generator üçün kod nümunəsi:*
```php
// Bu kod Generator pipeline-ı ilə məlumat emalının mərhələ-mərhələ həyata keçirilməsini göstərir
function pipeline(iterable $source, callable ...$stages): \Generator
{
    $data = $source;

    foreach ($stages as $stage) {
        $data = $stage($data);
    }

    yield from $data;
}

$result = pipeline(
    generateNumbers(1, 100),
    fn($nums) => (function() use ($nums) {
        foreach ($nums as $n) {
            if ($n % 2 === 0) yield $n;  // Filter: cüt ədədlər
        }
    })(),
    fn($nums) => (function() use ($nums) {
        foreach ($nums as $n) {
            yield $n * $n;  // Map: kvadrat
        }
    })()
);

foreach ($result as $value) {
    echo $value . "\n";  // 4, 16, 36, 64...
}
```

---

## Laravel Octane

Octane Laravel application-ı Swoole, RoadRunner, və ya FrankenPHP kimi yüksək performanslı server-lərdə işlədərək bootstrap overhead-ini aradan qaldırır.

### Adi PHP-FPM vs Octane

```
PHP-FPM:
Request → Bootstrap → Handle → Shutdown → (tekrar)
          (~50ms)    (~10ms)   (~5ms)

Octane (Swoole/RoadRunner):
Start → Bootstrap (1 dəfə)
Request → Handle → Request → Handle → ...
           (~1ms)             (~1ms)
```

### Quraşdırma

*Quraşdırma üçün kod nümunəsi:*
```bash
composer require laravel/octane
php artisan octane:install  # Swoole / RoadRunner seç

# Swoole
pecl install swoole

# RoadRunner (binary download edir)
./vendor/bin/rr get-binary

# FrankenPHP
# Docker image istifadə et: dunglas/frankenphp
```

### Başlatmaq

*Başlatmaq üçün kod nümunəsi:*
```bash
# Swoole
php artisan octane:start --server=swoole --workers=8 --task-workers=4

# RoadRunner
php artisan octane:start --server=roadrunner --workers=8

# FrankenPHP
php artisan octane:start --server=frankenphp --workers=8

# Reload (sıfırlamadan yenilə)
php artisan octane:reload

# Status
php artisan octane:status
```

### Memory Leak Qorunma

Octane-də application prosesi uzun müddət yaşadığı üçün memory leak-lər ciddi problemdir.

*Octane-də application prosesi uzun müddət yaşadığı üçün memory leak-lə üçün kod nümunəsi:*
```php
// PROBLEM: Static state hər request-də yığılır
class UserCache
{
    private static array $cache = [];  // ← Proses boyunca böyüyür!

    public static function get(int $id): ?User
    {
        if (!isset(self::$cache[$id])) {
            self::$cache[$id] = User::find($id);
        }
        return self::$cache[$id];
    }
}

// HƏLL: Request-ə bağlı state üçün
class UserCache
{
    private array $cache = [];  // Instance property, static deyil

    public function get(int $id): ?User
    {
        return $this->cache[$id] ??= User::find($id);
    }
}
// Container-da scoped bind et:
$this->app->scoped(UserCache::class);
// scoped = hər request üçün yeni instance
```

### Octane-da qorunmalı ümumi patternlər

*Octane-da qorunmalı ümumi patternlər üçün kod nümunəsi:*
```php
// config/octane.php
return [
    'listeners' => [
        // Hər request əvvəl
        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
            // Custom cleanup
            ClearStaticCaches::class,
        ],

        // Request bitmədən
        RequestHandled::class => [],

        // Task tamamlandıqda
        TaskReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
        ],
    ],
];
```

*...Octane::prepareApplicationForNextOperation(), üçün kod nümunəsi:*
```php
// AppServiceProvider-da Octane event listener-ləri
use Laravel\Octane\Facades\Octane;
use Laravel\Octane\Events\RequestReceived;

Octane::tick('every-minute', fn() => Cache::put('heartbeat', now()))
    ->seconds(60);

// Request arası global state təmizlə
app()->make('events')->listen(RequestReceived::class, function () {
    // Static cache-ləri sıfırla
    SomeStaticClass::reset();
});
```

### Octane Concurrent Tasks

*Octane Concurrent Tasks üçün kod nümunəsi:*
```php
// Paralel task-lar
[$users, $orders, $products] = Octane::concurrently([
    fn() => User::count(),
    fn() => Order::whereDate('created_at', today())->count(),
    fn() => Product::where('active', true)->count(),
]);

// Nəticə: üç sorğu eyni anda çalışır
```

---

## Queue Worker Optimization

### Worker konfiqurasiya

*Worker konfiqurasiya üçün kod nümunəsi:*
```bash
# Əsas worker
php artisan queue:work

# Spesifik connection və queue
php artisan queue:work redis --queue=high,default,low

# Memory limit (MB)
php artisan queue:work --memory=256

# Maksimum icra müddəti (saniyə)
php artisan queue:work --timeout=60

# Worker neçə job-dan sonra özünü restart etsin (memory leak qorunma)
php artisan queue:work --max-jobs=1000

# Neçə saniyə çalışsın
php artisan queue:work --max-time=3600

# Sleep müddəti (boş queue-da gözləmə)
php artisan queue:work --sleep=3
```

### Supervisor konfiqurasiya

*Supervisor konfiqurasiya üçün kod nümunəsi:*
```ini
; /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-jobs=1000 --memory=256
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600  ; Job tamamlanması üçün gözlə
```

*stopwaitsecs=3600  ; Job tamamlanması üçün gözlə üçün kod nümunəsi:*
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
sudo supervisorctl status
```

### Queue prioritetləri

*Queue prioritetləri üçün kod nümunəsi:*
```php
// Job göndərmək
SendWelcomeEmail::dispatch($user)->onQueue('high');
GenerateReport::dispatch()->onQueue('low');
ProcessPayment::dispatch($order)->onQueue('critical');

// Worker priority queue-larla
php artisan queue:work --queue=critical,high,default,low
```

### Job retry/backoff

*Job retry/backoff üçün kod nümunəsi:*
```php
// Bu kod Laravel queue job-da timeout, tries və memory parametrlərinin konfiqurasiyasını göstərir
class ProcessPayment implements ShouldQueue
{
    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;  // Saniyə, retry arası gözlə

    // Exponential backoff
    public function backoff(): array
    {
        return [1, 5, 10];  // 1sn, 5sn, 10sn
    }

    // Birdəfəlik: uğursuz olsa heç retry etmə
    public function uniqueId(): string
    {
        return $this->order->id;  // ShouldBeUnique ilə birlikdə
    }

    public function failed(\Throwable $exception): void
    {
        // Xəta halında notification göndər
        $this->user->notify(new PaymentFailedNotification($exception));
    }
}
```

---

## Redis vs Database Cache/Session

### Performans müqayisəsi

```
Database (MySQL):
- Cache lookup: ~5-20ms (disk I/O, SQL parse)
- Write: ~10-30ms
- Lock support: mövcud
- Persistence: default

Redis:
- Cache lookup: ~0.1-1ms (RAM-based)
- Write: ~0.1-0.5ms
- Advanced data structures: string, hash, list, set, sorted set
- Pub/Sub: mövcud
- Lua scripting: mövcud
```

### Laravel cache konfiqurasiya

*Laravel cache konfiqurasiya üçün kod nümunəsi:*
```php
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',  // Atomic lock-lar üçün
    ],
    'database' => [
        'driver' => 'database',
        'table' => 'cache',
        'connection' => null,
        'lock_table' => 'cache_locks',
    ],
    'memcached' => [
        'driver' => 'memcached',
        'servers' => [...],
    ],
],
```

### Session konfiqurasiya

*Session konfiqurasiya üçün kod nümunəsi:*
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'lifetime' => 120,  // Dəqiqə
'expire_on_close' => false,
'encrypt' => true,
'connection' => 'default',  // Redis connection

// .env
SESSION_DRIVER=redis
SESSION_LIFETIME=120
```

### Redis data structure optimizasiyaları

*Redis data structure optimizasiyaları üçün kod nümunəsi:*
```php
// Bu kod Redis pipeline ilə çoxlu əmrlərin toplu icrasını göstərir
use Illuminate\Support\Facades\Redis;

// String (sadə key-value)
Redis::set('user:1:name', 'Orkhan', 'EX', 3600);
Redis::get('user:1:name');

// Hash (müştəri məlumatları üçün ideal)
Redis::hset('user:1', 'name', 'Orkhan', 'email', 'orkhan@example.com');
Redis::hget('user:1', 'name');
Redis::hgetall('user:1');

// Sorted Set (leaderboard üçün)
Redis::zadd('leaderboard', 1500, 'player1');
Redis::zadd('leaderboard', 2000, 'player2');
Redis::zrevrange('leaderboard', 0, 9, 'WITHSCORES');  // Top 10

// Atomic counter
Redis::incr('api:requests:today');
Redis::incrby('user:1:points', 10);

// Pub/Sub
Redis::publish('notifications', json_encode(['user' => 1, 'msg' => 'Hello']));
```

### Cache tagging

*Cache tagging üçün kod nümunəsi:*
```php
// Tagged cache (Redis/Memcached tələb edir)
Cache::tags(['users', 'active'])->put('user:1', $user, 3600);
Cache::tags(['users', 'active'])->get('user:1');

// Tag-a görə silmək
Cache::tags(['users'])->flush();  // Bütün user cache-lərini sil
```

### Cache lock (Race condition qorunma)

*Cache lock (Race condition qorunma) üçün kod nümunəsi:*
```php
// Atomic lock
$lock = Cache::lock('process-payment:' . $orderId, 30); // 30sn timeout

if ($lock->get()) {
    try {
        processPayment($orderId);
    } finally {
        $lock->release();
    }
} else {
    // Lock alına bilmədi, başqa proses işləyir
    throw new \RuntimeException('Payment already being processed');
}

// Bloklanana qədər gözlə
$lock->block(10, function () use ($orderId) {
    processPayment($orderId);
});
```

---

## Benchmarking Alətləri

### Apache Bench (ab)

Sadə HTTP load testing.

*Sadə HTTP load testing üçün kod nümunəsi:*
```bash
# 1000 request, 50 eyni anda
ab -n 1000 -c 50 http://localhost/api/users

# Nəticə:
# Requests per second:    425.73 [#/sec]
# Time per request:       117.4 [ms] (mean)
# Transfer rate:          1847 KB/sec

# POST request
ab -n 100 -c 10 -T 'application/json' \
   -p /tmp/payload.json \
   http://localhost/api/users

# Keep-alive ilə
ab -n 1000 -c 50 -k http://localhost/api/users
```

### wrk

Apache Bench-dən daha güclü, multi-threaded.

*Apache Bench-dən daha güclü, multi-threaded üçün kod nümunəsi:*
```bash
# 30 saniyə, 4 thread, 100 eyni connection
wrk -t4 -c100 -d30s http://localhost/api/users

# Lua script ilə POST
wrk -t4 -c100 -d30s -s /path/to/post.lua http://localhost/api/users
```

*wrk -t4 -c100 -d30s -s /path/to/post.lua http://localhost/api/users üçün kod nümunəsi:*
```lua
-- post.lua
wrk.method = "POST"
wrk.body   = '{"name":"Test","email":"test@example.com"}'
wrk.headers["Content-Type"] = "application/json"
wrk.headers["Authorization"] = "Bearer token123"

-- Custom response validation
response = function(status, headers, body)
  if status ~= 200 then
    io.write("Error: " .. status .. "\n")
  end
end
```

### k6

Modern load testing, JavaScript ilə yazılır.

*Modern load testing, JavaScript ilə yazılır üçün kod nümunəsi:*
```bash
# Quraşdır
brew install k6  # macOS
# ya da Docker
docker run grafana/k6

# Test işlət
k6 run script.js
```

*k6 run script.js üçün kod nümunəsi:*
```javascript
// script.js
import http from 'k6/http';
import { check, sleep } from 'k6';

// Test konfiqurasiyası
export const options = {
  stages: [
    { duration: '30s', target: 20 },   // 30sn ərzində 20 istifadəçiyə qalx
    { duration: '1m', target: 20 },    // 1 dəq sabit 20 istifadəçi
    { duration: '30s', target: 0 },    // 30sn ərzində 0-a endir
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'],  // 95% request < 500ms
    http_req_failed: ['rate<0.1'],     // Xəta nisbəti < 10%
  },
};

export default function() {
  // GET request
  const response = http.get('http://localhost/api/users', {
    headers: { Authorization: 'Bearer token123' },
  });

  check(response, {
    'status is 200': (r) => r.status === 200,
    'response time < 500ms': (r) => r.timings.duration < 500,
    'has data': (r) => JSON.parse(r.body).data !== undefined,
  });

  sleep(1);
}

// Nəticə:
// http_req_duration......: avg=145ms min=12ms med=120ms max=3.2s p(90)=280ms p(95)=420ms
// http_reqs..............: 4523    150.7/s
```

### PHP-daxili Benchmarking

*PHP-daxili Benchmarking üçün kod nümunəsi:*
```php
// Sadə benchmark
function benchmark(callable $fn, int $iterations = 1000): array
{
    $start = microtime(true);
    $memStart = memory_get_usage();

    for ($i = 0; $i < $iterations; $i++) {
        $fn();
    }

    $end = microtime(true);
    $memEnd = memory_get_usage();

    return [
        'total_time'  => round(($end - $start) * 1000, 2) . 'ms',
        'avg_time'    => round(($end - $start) / $iterations * 1000, 4) . 'ms',
        'memory_used' => round(($memEnd - $memStart) / 1024, 2) . 'KB',
        'iterations'  => $iterations,
    ];
}

// İstifadə
$result = benchmark(function () {
    $collection = collect(range(1, 1000));
    return $collection->filter(fn($n) => $n % 2 === 0)->sum();
}, 100);

// array (
//   'total_time' => '45.23ms',
//   'avg_time' => '0.4523ms',
//   'memory_used' => '12.5KB',
//   'iterations' => 100,
// )
```

### Laravel Artisan komandası ilə benchmark

*Laravel Artisan komandası ilə benchmark üçün kod nümunəsi:*
```php
// app/Console/Commands/BenchmarkCommand.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BenchmarkCommand extends Command
{
    protected $signature = 'app:benchmark {--iterations=100}';

    public function handle(): void
    {
        $iterations = (int) $this->option('iterations');

        $this->info("Running {$iterations} iterations...");

        $tests = [
            'DB Query' => fn() => \DB::table('users')->count(),
            'Cache Get' => fn() => cache('test_key', 'default'),
            'Collection' => fn() => collect(range(1, 1000))->sum(),
        ];

        $headers = ['Test', 'Avg Time', 'Total Time', 'Memory'];
        $rows = [];

        foreach ($tests as $name => $fn) {
            $result = benchmark($fn, $iterations);
            $rows[] = [$name, $result['avg_time'], $result['total_time'], $result['memory_used']];
        }

        $this->table($headers, $rows);
    }
}
```

---

## İntervyu Sualları

### 1. OPcache nədir və niyə istifadə edilir?

**Cavab**: OPcache PHP-nin source kodunu compile etdiyi bytecode-u (opcodes) RAM-da cache-ləyir. Hər request-də PHP kodu yenidən parse edilmək və compile edilmək əvəzinə, artıq compile edilmiş bytecode-u RAM-dan oxuyur. Bu disk I/O-nu tamamilə aradan qaldırır və performansı 2-10x artırır. `opcache.validate_timestamps=0` production-da faylın dəyişib-dəyişmədiyinin yoxlanmasını da deaktiv edərək əlavə performans qazandırır.

### 2. N+1 problemi nədir? Necə aşkar edilir?

**Cavab**: N+1 problemi bir siyahı əldə etmək üçün 1 query, sonra isə hər element üçün ayrıca N query işlədilməsi vəziyyətidir. Məsələn, 100 post çəkilir, sonra hər postun müəllifi ayrıca sorğu ilə yüklənir — cəmi 101 query. Həll: `with()` ilə eager loading. Aşkarlama üsulları: `Model::preventLazyLoading()` (development-də exception verir), Laravel Telescope-un Queries bölməsi, Debugbar-ın query counter-i.

### 3. `chunk()` ilə `lazy()` arasındakı fərq nədir?

**Cavab**: `chunk()` hər iterasiyada yeni bir SQL query çalışdırır (`LIMIT/OFFSET` əsaslı) və closure-a Collection qaytarır. `lazy()` isə PHP Generator istifadə edərək bir query çalışdırır, ancaq nəticəni kursor vasitəsilə tək-tək oxuyur — RAM istifadəsi minimaldır. `lazy()` LazyCollection qaytarır, buna görə bütün Collection metodlarını zəncir şəklində istifadə etmək mümkündür. Emal zamanı ID-yə görə sorğu modifikasiyası üçün `chunkById()` / `lazyById()` tövsiyyə edilir.

### 4. PHP Generator nədir?

**Cavab**: Generator `yield` açar sözünü istifadə edən funksiyalardır. `yield`-ə çatdıqda funksiya icrasını pauzalayır, dəyəri qaytarır, növbəti `next()` çağırışında kaldığı yerdən davam edir. Bütün dəyərləri əvvəlcədən bir array-ə yükləmirsiniz — hər dəfə bir dəyər yaranır. Bu böyük fayl oxuma, sonsuz sequence-lər, pipeline-lar üçün ideal edir. RAM istifadəsi O(1)-dir, array-in O(n)-i ilə müqayisədə.

### 5. Laravel Octane nə edir?

**Cavab**: Octane Laravel application-ını Swoole, RoadRunner, və ya FrankenPHP kimi persistent server-lərdə işlədir. Adi PHP-FPM-də hər request-də Laravel-in tam bootstrap prosesi (ServiceProvider-ların yüklənməsi, config parse, route compile) təkrar edilir (~50ms). Octane ilə bu bootstrap yalnız bir dəfə — server başladıqda — olur, hər request yalnız handler-i çalışdırır (~1ms). Əsas risk: proses uzun yaşadığı üçün static state-lər, singleton-lar request-lərarası "sızmaya" bilər (memory leak).

### 6. PHP-FPM `pm.max_children` necə hesablanır?

**Cavab**: Formula: `pm.max_children = Available RAM / Average PHP process memory`. Əgər server 4GB RAM-a malikdir, PHP-FPM-ə 2GB ayrılıbsa və hər worker ortalama 50MB istifadə edirsə: `2048 / 50 = ~40 worker`. `pm.start_servers` ümuminin 25%-i, `pm.min_spare_servers` ~10%, `pm.max_spare_servers` ~75% tövsiyyə edilir. Əsl ölçülər üçün `php artisan queue:work` altında bir neçə request zamanı `ps aux` ilə real memory istifadəsini ölçmək lazımdır.

### 7. Redis-i database cache əvəzinə niyə istifadə etmək lazımdır?

**Cavab**: Redis in-memory (RAM-əsaslı) data structure server-dir. Database cache-i disk I/O, SQL parse, connection overhead tələb edir (~5-20ms). Redis isə RAM-dan oxuyur (~0.1-1ms) — 20-200x daha sürətli. Bundan əlavə Redis sorted set, hash, pub/sub, atomic operations kimi advanced xüsusiyyətlər təklif edir. Dezavantajı: əlavə infra xərci, RAM istifadəsi (böyük cache üçün baha ola bilər), persistence default deyil (RDB/AOF konfiqurasiya lazımdır).

### 8. Blackfire.io ilə Xdebug profiler arasındakı fərq nədir?

**Cavab**: Xdebug bütün function call-ları izləyir, buna görə overhead-i çox yüksəkdir (10-100x yavaşlama) — yalnız development-də istifadə edilir. Blackfire statistical sampling istifadə edir: random intervallarla call stack-ə baxır, overhead-i ~1-5% — production-da istifadə edilə bilər. Blackfire həmçinin comparison profiles (regression aşkarlama), assertions (performans testləri), CI/CD inteqrasiyası kimi xüsusiyyətlər təklif edir. Development üçün Xdebug, staging/production profiling üçün Blackfire tövsiyyə edilir.

### 9. `config:cache` istifadə edərkən hansı problemi yaşaya bilərsiniz?

**Cavab**: `config:cache` faylı yarandıqdan sonra `.env` dəyişiklikləri avtomatik əks olunmur — `config:clear` çalışdırmaq lazımdır. Bundan əlavə, `config()` helper-i config cache-ləndikdən sonra runtime-da set edilmiş dəyərləri qaytara bilmir. Route cache zamanı isə closure route-lar (`Route::get('/path', function(){})`) serialize edilə bilmədiyindən xəta verir — bütün route-lar controller-ə köçürülməlidir.

### 10. `preventLazyLoading()` production-da açmaq təhlükəlidirmi?

**Cavab**: Bəli, production-da `Model::preventLazyLoading()` çağırmaq `LazyLoadingViolationException` atacağından real istifadəçilərə xəta göstərər. Bunun əvəzinə `Model::handleLazyLoadingViolationUsing()` ilə violation-ları log-layıb development-də aşkar edib düzəltmək lazımdır. Lazy loading-i tam disable etmək istəyirsinizsə, production-da `handleLazyLoadingViolationUsing` callback-i ilə yalnız log+alert göndərin, exception atmayın.

### 11. OPcache `validate_timestamps=0` olduqda deployment-da nə etmək lazımdır?

**Cavab**: `validate_timestamps=0` ilə OPcache fayl dəyişikliklərini yoxlamır — yeni deploy edilmiş fayl dəyişiklikləri avtomatik görünmür. Deployment pipeline-da OPcache-i sıfırlamaq lazımdır. Yollar: (1) `php-fpm` restart — ən sadə, lakin qısa downtime; (2) `opcache_reset()` çağıran endpoint (authenticated olmalıdır!); (3) PHP CLI ilə `opcache_reset()` (`cli`-nin ayrı OPcache pool-u ola bilər, diqqət); (4) `opcache.enable_cli=1` + graceful reload. Laravel Octane ilə `php artisan octane:reload` istifadə edilir.

### 12. Generator `return` dəyəri necə alınır?

**Cavab**: Generator bitdikdə `return` ilə dəyər qaytara bilər, lakin bu dəyər birbaşa `foreach`-ə görünmür. `$generator->getReturn()` metodu ilə alınır. Bu metod yalnız generator bitdikdən (`valid()` false olduqdan) sonra çağırılmalıdır, əks halda `\LogicException` atar. Bu xüsusiyyət PHP 7.0-da əlavə edilmişdir.

---

## Anti-patternlər

**1. Profiling olmadan "intuisiya ilə" optimizasiya etmək**
Bottleneck harada olduğunu bilmədən kodun "ağır görünən" hissəsini optimize etmək — vaxt itirilir, əsl problem tapılmır, bəzən vəziyyət daha da pisləşir. Əvvəl Xdebug ya da Blackfire ilə profil çıxar, məlumatla hərəkət et.

**2. N+1 sorğu problemini production-da aşkar etmək**
Development-də `preventLazyLoading()` aktiv etməmək — lazy loading violation-ları ancaq production yükündə performans problemi kimi görünür. Development-də `Model::preventLazyLoading()` aktiv et, `with()` eager loading istifadə et.

**3. `config:cache` sonrası `.env` dəyişikliklərinin avtomatik əks olunacağını güman etmək**
`config:cache` çalışdırıldıqdan sonra `.env`-i dəyişib yenidən cache clear etməmək — köhnə konfiqurasiya dəyərləri istifadə olunur, baş verən bug-lar çox çətin izlənir. Deploy pipeline-a `config:clear && config:cache` ardıcıllığını əlavə et.

**4. Xdebug-u production-da aktiv buraxmaq**
`zend_extension=xdebug` production `php.ini`-ndə qalmaq — Xdebug 10-100x performans yavaşlaması verir, hər sorğu dramatik şəkildə ləngiyir. Production server-lərdə Xdebug-u deaktiv et, profiling üçün Blackfire istifadə et.

**5. OPcache `validate_timestamps=0` olduqda deploy sonrası sıfırlamamaq**
Yeni kodu deploy edib OPcache-i clear etməmək — PHP köhnə compile edilmiş versiyanı icra etməyə davam edir, dəyişikliklər görünmür. Deploy pipeline-a `opcache_reset()` çağrışı əlavə et ya da PHP-FPM graceful reload et.

**6. Yalnız response time-a baxıb memory consumption-u izləməmək**
Performans monitorinqini yalnız latency metrikaları ilə məhdudlaşdırmaq — yaddaş sızıntıları uzun müddətdə worker-ləri çökdürür, queue işçiləri restart tələb edir. `memory_get_peak_usage()` izlə, Horizon-da worker yaddaş limitlərini qur.
