# Laravel Telescope & Clockwork — Debug & Profiling

## Mündəricat
1. [Niyə Telescope/Clockwork?](#niyə-telescopeclockwork)
2. [Laravel Telescope](#laravel-telescope)
3. [Clockwork](#clockwork)
4. [Müqayisə](#müqayisə)
5. [Production-da Telescope](#production-da-telescope)
6. [Pulse (Laravel 11+)](#pulse-laravel-11)
7. [Debug bar alternativləri](#debug-bar-alternativləri)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə Telescope/Clockwork?

```
Local development-də developer "nə baş verdi?" sualına cavab axtarır:
  - Hansı request gəldi?
  - Neçə DB query işləndi (N+1 var?)
  - Cache hit / miss?
  - Hansı job dispatch edildi?
  - Hansı email göndərildi?
  - Hansı exception?
  - Hansı log entry?

Bu məlumatları:
  - Log fayllarından oxumaq → çətin, distributed
  - var_dump/dd → request-i pozur, artıq deploy etmiş ola bilər
  - APM tool — bahalıdır local üçün

Telescope/Clockwork — Laravel-native debug dashboard.
```

---

## Laravel Telescope

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

```
Telescope dashboard: /telescope

Tracked items (watcher-lər):
  - Requests (HTTP, payload, response, duration)
  - Commands (Artisan)
  - Schedule (cron tasks)
  - Jobs (queue dispatch + processing)
  - Exceptions (stack trace ilə)
  - Logs (Log facade-dən)
  - Notifications
  - Database queries (full SQL, bindings, time)
  - Mail (rendered, sent)
  - Models (eloquent events)
  - Events
  - Cache (hit/miss/forget)
  - Redis commands
  - Gates (authorization decisions)
  - Views (rendered, data)
  - Dumps (`dump()` to dashboard, screen-i pozmadan)
  - HTTP Client (Guzzle calls)
  - Batches (job batches)
```

```php
<?php
// config/telescope.php
return [
    'enabled' => env('TELESCOPE_ENABLED', true),
    
    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],
    
    // Watcher-ları seçmə
    'watchers' => [
        Watchers\BatchWatcher::class    => true,
        Watchers\CacheWatcher::class    => true,
        Watchers\CommandWatcher::class  => true,
        Watchers\DumpWatcher::class     => true,
        Watchers\EventWatcher::class    => env('TELESCOPE_EVENT_WATCHER', true),
        Watchers\ExceptionWatcher::class=> true,
        Watchers\GateWatcher::class     => true,
        Watchers\HttpClientWatcher::class=> true,
        Watchers\JobWatcher::class      => true,
        Watchers\LogWatcher::class      => true,
        Watchers\MailWatcher::class     => true,
        Watchers\ModelWatcher::class    => env('TELESCOPE_MODEL_WATCHER', true),
        Watchers\NotificationWatcher::class=> true,
        Watchers\QueryWatcher::class    => [
            'enabled' => true,
            'ignore_packages' => true,
            'slow' => 100,    // 100ms-dən yavaş query "slow" işarələnir
        ],
        Watchers\RedisWatcher::class    => true,
        Watchers\RequestWatcher::class  => [
            'enabled' => true,
            'size_limit' => 64,    // KB
            'ignore_http_methods' => ['OPTIONS'],
            'ignore_status_codes' => [],
        ],
        Watchers\ScheduleWatcher::class => true,
        Watchers\ViewWatcher::class     => true,
    ],
];

// Filter — yalnız bəzi entry-lər saxla (production üçün)
Telescope::filter(function (IncomingEntry $entry) {
    if (app()->isLocal()) {
        return true;   // local-da hər şey
    }
    
    return $entry->isReportableException()
        || $entry->isFailedJob()
        || $entry->isScheduledTask()
        || $entry->hasMonitoredTag();
});

// Pruning — köhnə entry-ləri silmə
// app/Console/Kernel.php
$schedule->command('telescope:prune --hours=48')->daily();
```

---

## Clockwork

```bash
composer require itsgoingd/clockwork --dev
```

```
Clockwork niyə əlçatandır?
  - Browser DevTools əlavəsi (Chrome/Firefox extension)
  - Toolbar (sayt altında, Symfony Profiler kimi)
  - Server-side data (response header-də metadata göndərir)
  - Real-time (request bitdiyi an görünür)
  - CLI command profiling

Toplanan data:
  - Performance metric (timeline, milestone)
  - Database queries (slow highlight, EXPLAIN)
  - Cache (hit/miss/store)
  - Models (created/updated/deleted)
  - Events
  - Routes
  - Notifications
  - Emails
  - Log
  - Session
  - Headers
  - Request/response
  - Subrequests (HTTP client)
  - Commands
  - Queue jobs (dispatch + processing)
```

```php
<?php
// Custom timeline event
clock()->event('processing-large-data')
    ->color('purple')
    ->begin();

// ... heavy work ...

clock()->event('processing-large-data')->end();

// User data
clock($user);   // dump to Clockwork (var_dump kimi, amma response-i pozmaz)

// Custom metric
clock()->info('processed items', ['count' => 1000]);
```

```
Clockwork extension features:
  Chrome DevTools panel: "Clockwork" tab
  - Request list
  - Performance timeline
  - SQL panel (slow query highlight)
  - Cache panel (hit ratio)
  - Events
```

---

## Müqayisə

```
Feature                | Telescope            | Clockwork
─────────────────────────────────────────────────────────────────
Dashboard             | Built-in (/telescope) | Browser extension
Storage               | Database (DB writes!) | File / Redis (low overhead)
Production safe       | Caution (filter ilə)  | Yes (low overhead)
Setup complexity      | Medium                | Low
Multi-app             | Per-app               | Per-app
Real-time             | Refresh lazım         | Live
HTTP request panel    | Yes                   | Yes
Database queries      | Yes (slow tag)        | Yes (timeline)
Job tracking          | Yes (dispatch+process)| Yes
Exception             | Yes (stack)           | Yes
Mail preview          | Yes (HTML)            | Limited
Models                | Created/updated event | Limited
Custom events         | Limited               | Yes (timeline)
DD replacement        | dump() / dd()         | clock($var)
CLI profiling         | Limited               | Yes (commands)
Disk usage            | Database grows fast   | Files (rotate)
Performance overhead  | 5-15%                 | 1-3%
Best for              | Deep debug, single app| Lightweight, daily dev
```

---

## Production-da Telescope

```php
<?php
// app/Providers/TelescopeServiceProvider.php

protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
            'devops@example.com',
        ]);
    });
}

// Filter — yalnız xəta və slow request
Telescope::filter(function (IncomingEntry $entry) {
    return $entry->isReportableException()
        || $entry->isFailedJob()
        || $entry->isSlowQuery()
        || $entry->hasMonitoredTag();
});

// Tag-ed monitoring
Telescope::tag(function (IncomingEntry $entry) {
    return $entry->type === 'request' && $entry->content['response_status'] >= 500
        ? ['error']
        : [];
});

// Storage drive seç (ayrı DB!)
'storage' => [
    'database' => [
        'connection' => 'telescope',   // dedicated DB
        'chunk' => 1000,
    ],
],

// Aggressive pruning — disk dolmasın
$schedule->command('telescope:prune --hours=24')->everyTenMinutes();
```

```
Production qaydaları:
  ✓ Auth gate (yalnız adminlər)
  ✓ Filter aggressive (yalnız error, slow query)
  ✓ Dedicated DB connection (production DB-yə yük olmasın)
  ✓ Pruning hourly
  ✓ HTTPS only
  ✓ TELESCOPE_ENABLED=false default, lazım olanda aç
```

---

## Pulse (Laravel 11+)

```bash
composer require laravel/pulse
php artisan pulse:install
php artisan migrate
```

```
Pulse — Laravel 11-də yeni built-in monitoring dashboard.
Telescope-dən FƏRQLİ:
  - Real-time aggregated metrics (Telescope per-request log)
  - Production-first dizayn (low overhead)
  - Beautiful dashboard

Default cards:
  - Servers (CPU, memory, disk per server)
  - Application usage (top routes, top users)
  - Slow requests (P95/P99)
  - Slow queries
  - Slow jobs
  - Slow outgoing requests
  - Exceptions (top by frequency)
  - Cache (hit ratio)
  - Queue (size, throughput, runtime)

Dashboard: /pulse
```

```php
<?php
// config/pulse.php
return [
    'enabled' => env('PULSE_ENABLED', true),
    
    'recorders' => [
        Recorders\Servers::class       => ['server_name' => env('PULSE_SERVER_NAME', gethostname())],
        Recorders\SlowRequests::class  => ['threshold' => 1000],   // ms
        Recorders\SlowQueries::class   => ['threshold' => 1000],
        Recorders\SlowJobs::class      => ['threshold' => 5000],
        Recorders\Exceptions::class    => ['enabled' => true],
        Recorders\UserRequests::class  => ['enabled' => true],
        Recorders\CacheInteractions::class=> [],
    ],
    
    // Sample (1.0 = 100%, 0.1 = 10%)
    'recorders' => [
        Recorders\Requests::class => [
            'sample_rate' => 0.1,    // production-da %10 sample
        ],
    ],
];

// Custom card / metric
use Laravel\Pulse\Facades\Pulse;

Pulse::record('user_login', $user->id)
    ->count();

// Card view
@livewire('pulse.servers')
@livewire('pulse.exceptions')
@livewire('pulse.slow-queries')
```

---

## Debug bar alternativləri

```
Debugbar (barryvdh/laravel-debugbar):
  - Toolbar UI (page-də render)
  - Veteran (2014-dən)
  - Sadə setup
  - Performance overhead
  
Ray (Spatie):
  - Standalone desktop app
  - "ray($var)" — desktop-a göndər
  - Beautiful UI
  - Paid app (~$30 one-time)

Clockwork — yuxarıda
Telescope — yuxarıda
Pulse — yuxarıda

Tinkerwell:
  - Tinker GUI (web app)
  - Eloquent query oynamaq üçün
  - Live database connect

Termwind:
  - CLI styling (debugger deyil, amma CLI app üçün)
```

---

## İntervyu Sualları

- Telescope və Clockwork arasındakı fərqlər?
- Telescope production-da niyə diqqətlə istifadə olunmalıdır?
- Pulse Telescope-dən nə ilə fərqlənir?
- N+1 problemini bu tool-larla necə aşkarlayırsınız?
- `dump()` və `clock()` — hansı request flow-u pozmaz?
- Telescope storage-i niyə ayrı DB-yə qoyulur?
- Sample rate Pulse-də niyə vacibdir?
- Telescope-i hansı watcher-larla başlatardınız production-da?
- Clockwork-un browser extension üstünlüyü nədir?
- Pruning niyə vacibdir Telescope üçün?
- Job-ların failure tracking — hansı tool seçərdiniz?
- Real-time dashboard üçün Pulse vs Grafana — fərqlər?
