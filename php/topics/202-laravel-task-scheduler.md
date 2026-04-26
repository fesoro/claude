# Laravel Task Scheduler (Middle)

## İcmal
Laravel Task Scheduler, tətbiqdəki cron işlərini PHP kodu ilə idarə etməyə imkan verir. Əvvəllər server-də onlarla cron entry yazılırdı — Laravel isə bütün bu məntiqi bir yerə, `routes/console.php` (Laravel 9+) və ya `Kernel.php`-yə toplayır. Bir cron entry yetərlidir.

## Niyə Vacibdir
Production layihələrdə mütləq scheduled job-lar olur: gecəlik hesabatlar, köhnə session-ların silinməsi, subscription yenilənməsi, email digest, monitoring alertlər. Bu işləri server cron-larına yazmaq kod bazasından kənar olur, versioning olunmur, team-ə görünmür. Scheduler bütün bunu kodun içinə alır.

## Əsas Anlayışlar

### Qurulum — Server Cron
Server-də yalnız **bir** cron entry lazımdır:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```
Laravel hər dəqiqə bu əmri çalışdırır, hansı task-ların vaxtının çatdığını özü müəyyən edir.

### Task Növləri
```php
// routes/console.php (Laravel 9+)
use Illuminate\Support\Facades\Schedule;

// Artisan command
Schedule::command('emails:send --force')->daily();

// Job class
Schedule::job(new SendEmailDigest)->everyFourHours();

// Closure
Schedule::call(function () {
    DB::table('old_sessions')->where('updated_at', '<', now()->subDays(30))->delete();
})->daily();

// Shell command
Schedule::exec('node /home/forge/script.js')->daily();
```

### Tezlik Metodları
```php
->everyMinute()
->everyTwoMinutes()
->everyFiveMinutes()
->everyFifteenMinutes()
->everyThirtyMinutes()
->hourly()
->hourlyAt(17)            // hər saatın 17-ci dəqiqəsində
->everyTwoHours()
->daily()
->dailyAt('13:00')
->twiceDaily(1, 13)       // 01:00 və 13:00
->weekly()
->weeklyOn(1, '8:00')     // Hər bazar ertəsi 08:00
->monthly()
->monthlyOn(4, '15:00')   // Hər ayın 4-ü, 15:00
->quarterly()
->yearly()
->cron('0 9 * * 1-5')     // İş günləri 09:00
```

### Məhdudiyyətlər (Constraints)
```php
// Yalnız müəyyən şəraitdə çalışsın
Schedule::command('report:send')
    ->daily()
    ->when(fn() => User::count() > 0)          // şərt true olduqda
    ->skip(fn() => app()->isDownForMaintenance()) // şərt true olduqda atla
    ->environments(['production', 'staging']);    // yalnız bu mühitlərdə
```

## Praktik Baxış

### Overlap Önləmə
Uzun çalan task-lar bir sonrakı dəqiqə yenidən başlamasın deyə:
```php
Schedule::command('generate:report')
    ->everyFiveMinutes()
    ->withoutOverlapping()           // default 24 saat kilidlər
    ->withoutOverlapping(10);        // 10 dəqiqəlik kilidlə
```
`withoutOverlapping()` cache driver vasitəsilə atomic kilidlər istifadə edir. Production-da cache driver-in `Redis` ya da `Memcached` olmasına əmin ol — `file` driver klasterlərdə race condition yaradır.

### Tək Serverdə Çalışma
Load-balanced mühitdə eyni task bütün serverlərdə işləməsin:
```php
Schedule::command('send:newsletter')
    ->daily()
    ->onOneServer();  // Atomic lock ilə yalnız bir server çalışdırır
```
`onOneServer()` üçün `cache.default` mütləq `redis` ya da `memcached` olmalıdır.

### Background Çalışma
Task-ın bitməsini gözləmədən növbətiləri başlat:
```php
Schedule::command('expensive:task')
    ->everyMinute()
    ->runInBackground();  // Fork edir, parallel işləyir
```

### Output İdarəsi
```php
Schedule::command('generate:csv')
    ->daily()
    ->sendOutputTo(storage_path('logs/generate-csv.log'))     // hər dəfə üstünə yaz
    ->appendOutputTo(storage_path('logs/generate-csv.log'))   // əlavə et
    ->emailOutputTo('admin@example.com')                       // emailə göndər
    ->emailOutputOnFailure('admin@example.com');               // yalnız xəta olduqda
```

### Maintenance Mode
```php
// Maintenance mode-da belə çalışsın
Schedule::command('critical:cleanup')
    ->daily()
    ->evenInMaintenanceMode();
```

### Hooks
```php
Schedule::command('emails:send')
    ->daily()
    ->before(fn() => Log::info('Task başladı'))
    ->after(fn() => Log::info('Task bitdi'))
    ->onSuccess(fn() => Notification::route('slack', '#ops')->notify(new TaskSucceeded))
    ->onFailure(fn() => Notification::route('slack', '#alerts')->notify(new TaskFailed));
```

### Ping URL (Healthcheck)
Scheduled task-ların monitorinqi üçün:
```php
Schedule::command('backup:run')
    ->daily()
    ->pingBefore('https://hc-ping.com/uuid/start')   // başlamazdan əvvəl
    ->thenPing('https://hc-ping.com/uuid');            // bitdikdən sonra
    // healthchecks.io, Cronitor, OhDear ilə inteqrasiya
```

### Laravel 11+ — routes/console.php
```php
// Laravel 11-dən Kernel.php yoxdur
// bootstrap/app.php-də withSchedule(), ya da routes/console.php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:prune-stale-tokens')->daily();
Schedule::job(new ProcessMetrics)->everyFiveMinutes()->onOneServer();
```

### Trade-off-lar
- **Scheduler vs dedicated queue worker**: Kiçik recurring işlər üçün scheduler, uzun processing üçün queue worker + scheduled dispatch.
- **`withoutOverlapping` vs `onOneServer`**: `withoutOverlapping` eyni serverdəki paralel run-ları önləyir; `onOneServer` klasterdəki bütün serverlərdən biri işə götürsün deyə.
- **Scheduler vs cron**: Scheduler versioning, testability, visibility verir; nadir hallarda cron-un ms-level dəqiqliyi lazımdırsa cron daha münasibdir.

### Common Mistakes
- `onOneServer()` ilə file cache driver istifadə etmək → race condition
- Uzun task-ı queue-ya atmadan scheduler-də çalışdırmaq → memory leak, timeout
- Production-da `schedule:run` cron entry-ni unutmaq
- `withoutOverlapping()` kildinin 24 saat qalmasını planlamaq (proses kill olursa kilid qalır) → `withoutOverlapping(10)` kimi qısa timeout ver

## Nümunələr

### Real Layihə: E-Commerce Automated Tasks
```php
// routes/console.php

// Hər gecə 02:00 — süresi bitmiş sifarişlər ləğv edilsin
Schedule::command('orders:cancel-expired')
    ->dailyAt('02:00')
    ->onOneServer()
    ->emailOutputOnFailure('ops@example.com');

// Hər 5 dəqiqə — pending ödənişlər yoxlansın
Schedule::command('payments:check-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping(4)
    ->onOneServer();

// Hər bazar ertəsi 09:00 — həftəlik satış hesabatı
Schedule::job(new GenerateWeeklySalesReport)
    ->weeklyOn(1, '09:00')
    ->onOneServer();

// Hər gün gecəyarısı — köhnə log-lar təmizlənsin
Schedule::call(function () {
    DB::table('activity_logs')
        ->where('created_at', '<', now()->subMonths(3))
        ->delete();
})->daily()->name('prune-activity-logs');

// Hər saat — subscription statusları yenilənsin
Schedule::command('subscriptions:sync')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->pingBefore(config('healthcheck.subscriptions_url'));
```

### Test etmək
```php
// Feature test
it('expires overdue orders', function () {
    $expiredOrder = Order::factory()->create([
        'status'     => 'pending',
        'expires_at' => now()->subHour(),
    ]);
    
    $this->artisan('orders:cancel-expired');
    
    expect($expiredOrder->fresh()->status)->toBe('cancelled');
});
```

## Praktik Tapşırıqlar

1. `cleanup:expired-tokens` command yaz, hər gece yarısı çalışsın, `withoutOverlapping` və `onOneServer` ilə
2. Hər bazar ertəsi həftəlik hesabat maili göndərən task qur, output log-a yazılsın
3. `pingBefore()` + `thenPing()` ilə healthchecks.io inteqrasiyası qur
4. Multi-server mühitdə `onOneServer()` olmadan birdən çox task run etdikdə nə baş verdiyini test et

## Əlaqəli Mövzular
- [Queues & Jobs](057-queues.md)
- [Laravel Horizon](058-laravel-horizon-queue.md)
- [Artisan Commands](043-artisan-commands-deep.md)
- [Distributed Job Scheduler](173-distributed-job-scheduler.md)
