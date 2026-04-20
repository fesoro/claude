# Xdebug Deep Dive — Step Debugging & Profiling

## Mündəricat
1. [Xdebug nədir?](#xdebug-nədir)
2. [Modes (xdebug 3+)](#modes-xdebug-3)
3. [Quraşdırma](#quraşdırma)
4. [Step Debugging](#step-debugging)
5. [Profiling (Cachegrind)](#profiling-cachegrind)
6. [Code Coverage](#code-coverage)
7. [Trace](#trace)
8. [Develop mode](#develop-mode)
9. [GC Stats](#gc-stats)
10. [IDE inteqrasiya (PhpStorm, VS Code)](#ide-inteqrasiya)
11. [Production-da niyə YOX](#production-da-niyə-yox)
12. [Alternativlər (Blackfire, Tideways)](#alternativlər)
13. [İntervyu Sualları](#intervyu-sualları)

---

## Xdebug nədir?

```
Xdebug — PHP üçün debugging + profiling extension.
2002-də Derick Rethans (PHP core dev) tərəfindən yaradılıb.

Xüsusiyyətlər:
  - Step debugger (breakpoint, watch, step over/in/out)
  - Profiler (CPU time, call graph)
  - Trace (function call log)
  - Code coverage (PHPUnit/Pest üçün)
  - Var dump enhancement (rəngli, structured)
  - Stack trace on error
  - GC statistics

Versiyalar:
  Xdebug 2 (köhnə): toggle-lar mürəkkəbdir
  Xdebug 3 (modern): "modes" konsepti — yalnız lazımı funksiyanı aç
```

---

## Modes (xdebug 3+)

```ini
; php.ini
[xdebug]
zend_extension=xdebug.so

; Birdən çox mode comma ilə ayrılır
xdebug.mode=debug,develop

; Mövcud modes:
;   off         — söndürülüb
;   develop     — "var_dump" enhanced, stack trace
;   debug       — step debugger (DBGp protocol)
;   profile     — Cachegrind output
;   trace       — function call trace
;   coverage    — PHPUnit code coverage
;   gcstats     — Garbage collector stats
```

```
Mode-ların performance impact:

mode=off       — 0% overhead (extension load olunsa da)
mode=develop   — minimal (~5%)
mode=debug     — yüksək (10-30%) — yalnız lazım olduqda
mode=profile   — ÇOX yüksək (50-200%) — request başına ~10MB Cachegrind file
mode=trace     — yüksək, disk yazma (request başına MB-larca)
mode=coverage  — orta (PHPUnit-də)

Production: mode=off MƏCBURI!
Dev: mode=develop,debug
CI: mode=coverage (test suite üçün)
```

---

## Quraşdırma

```bash
# Linux (Debian/Ubuntu)
sudo apt-get install php8.3-xdebug

# Pecl ilə
pecl install xdebug

# Docker
RUN pecl install xdebug \
 && docker-php-ext-enable xdebug

# Verify
php -v
# PHP 8.3.0 (cli) (built: ...)
# Copyright (c) The PHP Group
# Zend Engine v4.3.0, Copyright (c) Zend Technologies
#     with Zend OPcache v8.3.0, Copyright (c), by Zend Technologies
#     with Xdebug v3.3.0, Copyright (c) 2002-2024, by Derick Rethans

php --ri xdebug
# Mode-ları görmək üçün
```

```ini
; /etc/php/8.3/cli/conf.d/20-xdebug.ini
zend_extension=xdebug

xdebug.mode=develop,debug
xdebug.start_with_request=yes      ; hər request avtomatik debugger başlasın
                                    ; "trigger" — yalnız XDEBUG_TRIGGER cookie/get/env varsa
                                    ; "default" — yalnız xdebug_break() chağırışı

xdebug.client_host=host.docker.internal   ; IDE-nin IP-si
xdebug.client_port=9003                    ; Xdebug 3 default 9003 (köhnə 9000 idi!)
xdebug.idekey=PHPSTORM
xdebug.log=/tmp/xdebug.log
xdebug.log_level=3
```

---

## Step Debugging

```
DBGp Protocol — Xdebug ↔ IDE arasında TCP-üzərində.

Workflow:
  1. PHP request başlayır
  2. Xdebug IDE-yə connect olur (port 9003)
  3. IDE breakpoint-ləri göndərir
  4. PHP icra zamanı breakpoint-ə çatanda dayanır
  5. IDE variable-ları, stack-i göstərir
  6. User: step over (F10), step in (F11), step out (Shift+F11), continue (F9)
```

```php
<?php
// Manual breakpoint
function calculate(int $x): int
{
    xdebug_break();   // burada IDE dayanacaq
    
    $result = $x * 2;
    return $result;
}

// Conditional breakpoint — PhpStorm-da right-click → "Add Conditional Breakpoint"
// $x > 100 olanda dayan
```

```bash
# Trigger debugger:
# 1. Cookie: XDEBUG_SESSION=PHPSTORM
# 2. Query: ?XDEBUG_SESSION_START=PHPSTORM
# 3. Browser extension: "Xdebug Helper"

# CLI:
XDEBUG_TRIGGER=1 php artisan tinker
XDEBUG_TRIGGER=1 vendor/bin/phpunit

# Docker → host
# docker.internal → Linux Docker
xdebug.client_host=host.docker.internal
```

---

## Profiling (Cachegrind)

```bash
# Profile mode aktivlə
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_output_name=cachegrind.out.%p
xdebug.start_with_request=trigger    ; hər request profile etmə!

# Trigger:
XDEBUG_TRIGGER=PROFILE php script.php
# /tmp/xdebug/cachegrind.out.12345 yaranır

# Curl ilə web request
curl 'https://app.test/heavy' \
    -H 'Cookie: XDEBUG_TRIGGER=PROFILE'
```

```
Cachegrind format:
  Hər function call üçün:
    - Self time (yalnız bu function-da keçən vaxt)
    - Inclusive time (call-edilən function-larla birgə)
    - Call count

Vizualizə:
  KCachegrind (Linux)        — köhnə, ən güclü
  QCachegrind (Mac, Win)
  PhpStorm built-in viewer
  Webgrind (browser)
  Speedscope (online tool)
```

```
Tipik analiz:
  1. "Self time"-ə görə sırala
  2. Top 5-10 function-a bax
  3. Hot spot identify et (DB query, JSON encode, regex)
  4. Optimize → re-profile

Real example:
  Top 5 self time:
    1. PDOStatement::execute       1.2s    (35%)
    2. json_encode                 0.4s    (12%)
    3. preg_replace                0.3s    (9%)
    4. eloquent::__construct       0.3s    (9%)   ← N+1?
    5. file_get_contents           0.2s    (6%)
  
  Action: 4-cü Eloquent constructor — N+1 fix lazım
```

---

## Code Coverage

```ini
; phpunit / pest üçün
xdebug.mode=coverage
```

```bash
# PHPUnit
vendor/bin/phpunit --coverage-html coverage/

# Pest
vendor/bin/pest --coverage
vendor/bin/pest --coverage --min=80      # minimum 80% coverage tələb et

# Faster alternative (PHP 8+): PCOV
pecl install pcov
# pcov daha sürətdir, amma yalnız coverage edir, debug yox
```

---

## Trace

```ini
xdebug.mode=trace
xdebug.trace_output_dir=/tmp/xdebug
xdebug.trace_format=1    ; 0=human, 1=computer, 2=html
xdebug.trace_options=1   ; append to existing
xdebug.collect_params=4
xdebug.collect_return=1
```

```
Trace output (text format):

TRACE START [2026-04-19 10:00:00]
    0.0001       320  -> {main}() ../index.php:0
    0.0010      1024    -> require('bootstrap.php') ../index.php:5
    0.0050      8192      -> Application->__construct() ../bootstrap.php:10
    0.0100     12288    -> Router->dispatch() ../index.php:15
    0.0150     16384      -> UserController->show($id = 42) ../Router.php:50
    0.0200     20480        -> User::find(42) ../UserController.php:20
    0.0300     24576          -> PDOStatement->execute() ../Builder.php:100
    0.0500     20480          <- User#1
    0.0510     20480        <- User#1
    0.0520     20480      <- {array}
    0.0530     16384    <- 200
TRACE END   [2026-04-19 10:00:01]   0.0530s   18MB peak
```

```
Use case:
  - "Bu request niyə yavaşdır?"
  - "Hansı function ən çox çağırılır?"
  - Hidden N+1 detection
  - Library internal call analysis
```

---

## Develop mode

```php
<?php
// xdebug.mode=develop
// var_dump enhanced
$user = User::find(1);
var_dump($user);

// Output (pretty, colored, structured):
// object(App\Models\User)#42 (5) {
//   ["id":protected]=> int(1)
//   ["name":protected]=> string(3) "Ali"
//   ["email":protected]=> string(15) "ali@example.com"
//   ...
// }

// Stack trace on error
throw new Exception('test');
// Xdebug-style colored stack trace ilə browser-də göstərilir
// Var-larla zənginləşmiş

// xdebug functions:
xdebug_call_class();          // hansı class-dan çağırılıb
xdebug_call_function();       // hansı function-dan
xdebug_call_file();
xdebug_call_line();

xdebug_print_function_stack(); // current stack
xdebug_get_function_stack();   // array kimi

xdebug_time_index();           // request başlangıcından keçən vaxt
xdebug_memory_usage();
xdebug_peak_memory_usage();

xdebug_info();                 // bütün config + status (web)
```

---

## GC Stats

```ini
xdebug.mode=gcstats
xdebug.gc_stats_output_dir=/tmp/xdebug
```

```
Output:
  Cycle Collection Statistics
  Runs:               42
  Collected:          1234
  Efficiency:         29.4%
  Duration:           0.052s

İstifadə:
  - Memory leak debug
  - Long-running CLI script-də (queue worker)
  - Symfony/Doctrine UnitOfWork analizi
```

---

## IDE inteqrasiya

```
PhpStorm:
  1. Settings → PHP → Debug
  2. Xdebug port: 9003
  3. "Start Listening for PHP Debug Connections" (telefon ikonu)
  4. Breakpoint qoy → reload page → debugger pause

VS Code:
  Extension: PHP Debug (xdebug.php-debug)
  
  .vscode/launch.json
  {
    "version": "0.2.0",
    "configurations": [
      {
        "name": "Listen for Xdebug",
        "type": "php",
        "request": "launch",
        "port": 9003,
        "pathMappings": {
          "/var/www/app": "${workspaceFolder}"
        }
      }
    ]
  }
```

---

## Production-da niyə YOX

```
❌ NEVER load Xdebug in production:

  Performance:
    - 5-30% slowdown (mode=off olsa belə extension load overhead)
    - Profile/trace 50-200%
  
  Security:
    - Step debugger TCP port açıq → uzaqdan kod inject riski
    - Stack trace sensitive data leak (DB password)
  
  Disk:
    - Trace/profile MB-lar yazır request başına
    - /tmp doldurma riski
  
  Memory:
    - Hər request başına Xdebug data structures

Production-da:
  - opcache aç (xdebug ilə birgə işləməz, OPcache disable olunur trace zamanı)
  - APM tool istifadə et (Tideways, Blackfire)
  - Sentry / OpenTelemetry — observability

Doğru CI/dev setup:
  Dev:  Xdebug ON (debug,develop)
  CI:   Xdebug ON (coverage only) və ya pcov
  Prod: Xdebug yox (alternativ APM)
```

---

## Alternativlər

```
Blackfire (Sensiolabs / Blackfire.io):
  - Production-safe profiling
  - Probe minimal overhead (~1%)
  - Continuous profiling
  - Recommendation engine
  - Paid (free tier limited)

Tideways:
  - Production APM
  - PHP-specific
  - Trace, profile, monitoring
  - Daha ucuz Blackfire-dən

Datadog APM:
  - Multi-language APM
  - PHP tracer (Xdebug-dan fərqli, low overhead)
  - Distributed tracing built-in

New Relic PHP:
  - Klassik APM
  - Browser monitoring inteqrasiya

PCOV:
  - YALNIZ code coverage (debug yox, profile yox)
  - Çox sürətli (Xdebug-dan 5-10× sürətli)
  - CI üçün ideal

PHP-Spy:
  - Sampling profiler (Rust ilə yazılıb)
  - Production-safe
  - Process attach (kodu dəyişmir)
```

---

## İntervyu Sualları

- Xdebug 3-də mode konsepti necə dəyişdi?
- `xdebug.mode=debug` production-da niyə təhlükəlidir?
- DBGp protocol nədir, hansı port-da işləyir?
- Cachegrind faylı necə oxunur?
- "Self time" və "inclusive time" arasındakı fərq?
- Code coverage üçün PCOV niyə Xdebug-dan üstündür?
- Step debugger üçün Docker-də `client_host` necə qurulur?
- Trace mode-un production-da niyə qadağandır?
- Conditional breakpoint nə üçün lazımdır?
- Blackfire vs Xdebug — hansı production üçün uyğundur?
- N+1 problemini Xdebug ilə necə aşkarlayırsınız?
- `xdebug.start_with_request=trigger` niyə təhlükəsiz seçimdir?
