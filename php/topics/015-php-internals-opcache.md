# PHP Internals (Junior)

## Mündəricat
1. [PHP kodunun icra prosesi](#php-kodunun-icra-prosesi)
2. [Zend Engine](#zend-engine)
3. [OPcache](#opcache)
4. [OPcache Konfiqurasiyası](#opcache-konfiqurasiyası)
5. [OPcache İnvalidasiya Strategiyaları](#opcache-invalidasiya-strategiyaları)
6. [PHP 7.4 Preloading](#php-74-preloading)
7. [JIT Compiler (PHP 8.0+)](#jit-compiler-php-80)
8. [Performance Müqayisəsi](#performance-müqayisəsi)
9. [Praktiki Pitfall-lar](#praktiki-pitfall-lar)
10. [İntervyu Sualları](#intervyu-sualları)

---

## PHP kodunun icra prosesi

PHP skripti hər HTTP sorğusunda aşağıdakı mərhələlərdən keçir:

```
.php faylı
    │
    ▼
┌─────────┐
│  Lexer  │  Mətni token-lərə parçalayır
│(Tokenizer)  T_ECHO, T_STRING, T_WHITESPACE...
└────┬────┘
     │
     ▼
┌─────────┐
│ Parser  │  Token-ləri AST-ə çevirir
│         │  (Abstract Syntax Tree)
└────┬────┘
     │
     ▼
┌──────────────┐
│  AST → IR   │  AST-i opcodes-a çevirir
│  Compiler   │  (Intermediate Representation)
└─────┬────────┘
      │
      ▼
┌──────────────┐
│   Opcodes   │  Zend VM tərəfindən icra edilir
│  (Bytecode) │  ZEND_ECHO, ZEND_ADD, ZEND_RETURN...
└─────┬────────┘
      │
      ▼
┌──────────────┐
│   Zend VM   │  Opcodes-ı icra edir
│  (Executor) │
└──────────────┘
```

**OPcache olmadan problem:** Hər sorğuda eyni fayl üçün Lexer→Parser→Compiler mərhələləri təkrarlanır. Bu CPU vaxtının israfıdır.

**OPcache ilə:** Opcodes ilk dəfə compile edilib yaddaşda saxlanır. Növbəti sorğular birbaşa Zend VM-ə verilir.

---

## Zend Engine

Zend Engine PHP-nin nüvəsidir. PHP 8.x Zend Engine 4 istifadə edir.

**Əsas komponentlər:**
- **Zend MM (Memory Manager)** — yaddaş idarəetməsi, zval-lar üçün pool
- **Zend VM** — opcode executor, register-based virtual machine
- **Zend GC** — garbage collector, cycle detection
- **Zend API** — extension yazmaq üçün C API

**zval strukturu** (PHP-nin hər dəyişəni bu strukturda saxlanır):

***zval strukturu** (PHP-nin hər dəyişəni bu strukturda saxlanır) üçün kod nümunəsi:*
```c
// PHP 7+ zval (sadələşdirilmiş)
struct _zval_struct {
    zend_value value;    // faktiki dəyər (union)
    uint32_t type_info;  // tip + GC flags
};

union _zend_value {
    zend_long    lval;   // integer
    double       dval;   // float
    zend_string *str;    // string
    zend_array  *arr;    // array
    zend_object *obj;    // object
    zend_ref    *ref;    // reference
};
```

PHP 7-də zval ölçüsü 16 byte-a endirildi (PHP 5-də 48 byte idi) — bu böyük performans artımı verdi.

---

## OPcache

OPcache (Optimizer Plus Cache) — compile edilmiş PHP opcode-larını shared memory-də saxlayan PHP extension-ıdır.

```
İlk sorğu:
.php fayl → Lexer → Parser → Compiler → Opcodes → [OPcache-ə yaz] → Zend VM

Sonrakı sorğular:
.php fayl → [OPcache-dən oxu] → Zend VM
           (Lexer/Parser/Compiler atlanır!)
```

**Shared Memory:**

```
┌─────────────────────────────────────────────┐
│              Shared Memory                  │
│  ┌──────────────────────────────────────┐   │
│  │  Opcache (PHP prosesləri arasında    │   │
│  │  paylaşılır)                         │   │
│  │                                      │   │
│  │  /var/www/html/index.php → opcodes   │   │
│  │  /var/www/html/User.php  → opcodes   │   │
│  │  /var/www/html/...       → opcodes   │   │
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
       ↑              ↑              ↑
   Worker 1       Worker 2       Worker 3
  (PHP-FPM)      (PHP-FPM)      (PHP-FPM)
```

Bütün PHP-FPM worker-ləri eyni opcache yaddaşından istifadə edir. Bu çox effektivdir.

---

## OPcache Konfiqurasiyası

*OPcache Konfiqurasiyası üçün kod nümunəsi:*
```ini
; php.ini / conf.d/opcache.ini

; OPcache aktivləşdirmək
opcache.enable=1
opcache.enable_cli=0          ; CLI-də deaktiv (development üçün faydalı)

; Yaddaş
opcache.memory_consumption=256     ; MB, shared memory ölçüsü
opcache.interned_strings_buffer=16 ; String interning üçün MB
opcache.max_accelerated_files=20000 ; Cache-də maksimum fayl sayı

; Yenilənmə siyasəti
opcache.validate_timestamps=1    ; Fayl dəyişiklikləri yoxlanılsın (production-da 0)
opcache.revalidate_freq=2        ; Neçə saniyədə bir yoxlansın (validate_timestamps=1 olduqda)

; Performans
opcache.save_comments=1          ; PHPDoc şərhləri saxla (reflection üçün lazım)
opcache.fast_shutdown=1          ; Sürətli shutdown
opcache.max_wasted_percentage=5  ; Bu % israfdan sonra restart

; JIT (PHP 8+)
opcache.jit_buffer_size=100M     ; JIT üçün yaddaş
opcache.jit=tracing              ; JIT rejimi: disable, function, tracing
```

**Production tövsiyəsi:**

```ini
opcache.validate_timestamps=0   ; Fayl dəyişikliyi yoxlanmır (sürət üçün)
opcache.memory_consumption=512
opcache.max_accelerated_files=50000
opcache.jit_buffer_size=256M
opcache.jit=tracing
```

---

## OPcache İnvalidasiya Strategiyaları

**1. Manual sıfırlama:**

```php
// Bütün cache-i sıfırla
opcache_reset();

// Tək faylı sıfırla
opcache_invalidate('/var/www/html/User.php', $force = true);

// Cache status-u
$status = opcache_get_status();
echo $status['opcache_statistics']['hits'];   // cache hit sayı
echo $status['opcache_statistics']['misses']; // cache miss sayı
```

**2. Deploy zamanı:**

```bash
# Deploy skriptinə əlavə et
php artisan opcache:clear
# və ya
php -r "opcache_reset();"
# və ya PHP-FPM-i restart et
systemctl reload php8.2-fpm
```

**3. Laravel paketi:**

```bash
composer require appstract/laravel-opcache

php artisan opcache:clear   # Cache-i sıfırla
php artisan opcache:status  # Status
php artisan opcache:preload  # Preload
```

---

## PHP 7.4 Preloading

Preloading — server başladığında müəyyən PHP fayllarını shared memory-ə öncədən yükləmək. Bu fayllar hər sorğuda yenidən parse edilmir, hətta opcache miss zamanı belə.

*Preloading — server başladığında müəyyən PHP fayllarını shared memory- üçün kod nümunəsi:*
```ini
; php.ini
opcache.preload=/var/www/html/preload.php
opcache.preload_user=www-data   ; Təhlükəsizlik üçün
```

**preload.php nümunəsi:**

```php
<?php
// preload.php — server start zamanı bir dəfə icra edilir

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/src')
);

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        opcache_compile_file($file->getPathname());
    }
}

// Və ya spesifik sinifləri yüklə:
require_once __DIR__ . '/vendor/laravel/framework/src/Illuminate/Support/helpers.php';
require_once __DIR__ . '/src/Domain/Order/Order.php';
```

**Preloading nəticəsi:**

```
Server start zamanı:
preload.php icra edilir → 500+ sinif shared memory-ə yüklənir

Hər sorğu:
Bu sinifləri istifadə edərkən opcache-dən belə oxumur,
birbaşa yaddaşda hazır!
```

**Laravel üçün Preloading:**

```php
// bootstrap/preload.php
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

// Tez-tez istifadə olunan sinifləri preload et
$preloads = [
    \Illuminate\Support\Str::class,
    \Illuminate\Support\Arr::class,
    \Illuminate\Database\Eloquent\Model::class,
    // ...
];

foreach ($preloads as $class) {
    opcache_compile_file((new ReflectionClass($class))->getFileName());
}
```

---

## JIT Compiler (PHP 8.0+)

JIT (Just-In-Time) — opcodes-ı real vaxtda native machine code-a çevirir.

```
Ənənəvi (Interpreter):
Opcodes → [Zend VM interpreter] → CPU tərəfindən icra

JIT ilə:
Opcodes → [JIT Compiler] → Machine Code → birbaşa CPU tərəfindən icra
          (Zend VM bypass edilir)
```

**JIT rejimləri:**

```
opcache.jit=disable   → JIT deaktiv
opcache.jit=function  → Funksiya JIT: bütün funksiyaları compile edir
opcache.jit=tracing   → Tracing JIT: ən çox icra olunan "hot" kod yollarını compile edir
                        (tövsiyə edilən rejim)
```

**JIT nə vaxt faydalıdır:**

```
✅ CPU-bound tapşırıqlar:
   - Şəkil emalı
   - Matematiki hesablamalar
   - Data transformasiyası
   - ML/AI hesablamalar

❌ I/O-bound tapşırıqlar (JIT AZ fayda verir):
   - Database sorğuları (I/O gözləmə)
   - HTTP API çağırışları
   - Fayl oxuma/yazma
   - Tipik Laravel web tətbiqi
```

**Praktiki nümunə:**

```php
// CPU-bound: Fibonacci (JIT faydalıdır)
function fibonacci(int $n): int {
    if ($n <= 1) return $n;
    return fibonacci($n - 1) + fibonacci($n - 2);
}

// Benchmark: fibonacci(35)
// PHP 8.0 JIT=disable: ~0.8s
// PHP 8.0 JIT=tracing:  ~0.3s  (2.5x sürətli)

// I/O-bound: DB sorğuları (JIT AZ fayda verir)
$users = DB::table('users')->where('active', 1)->get();
// JIT bu sorğunu sürətləndirə bilmir çünki bottleneck DB-dir
```

---

## PHP-FPM Prosess Modeli

PHP-FPM (FastCGI Process Manager) PHP proseslərini idarə edir. `pm` (process manager) konfiqurasiyası performansa birbaşa təsir edir:

*PHP-FPM (FastCGI Process Manager) PHP proseslərini idarə edir. `pm` (p üçün kod nümunəsi:*
```ini
; php-fpm pool konfiqurasiyası (/etc/php/8.2/fpm/pool.d/www.conf)

pm = dynamic          ; static, dynamic, ondemand

; dynamic mode (tövsiyə edilən):
pm.max_children = 50        ; eyni anda maksimum worker sayı
pm.start_servers = 10       ; FPM başladıqda yaranan worker sayı
pm.min_spare_servers = 5    ; minimum gözləyən worker
pm.max_spare_servers = 20   ; maksimum gözləyən worker
pm.max_requests = 500       ; worker bu qədər request emal etdikdən sonra restart
                             ; memory leak-ləri önləmək üçün vacibdir

; ondemand mode (aşağı yüklü serverlər üçün):
pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s  ; 10 saniyə idle olan worker öldürülür
```

**`pm.max_requests` niyə vacibdir:**

```
Worker 1 sorğu 1 emal edir → yaddaşda bəzi data qalır (memory leak simulyasiyası)
Worker 1 sorğu 2 emal edir → yaddaş artır
...
Worker 1 sorğu 500-ü emal edir → restart → yaddaş sıfırlanır

Bu mexanizm olmadan worker-lər zamanla çox yaddaş istifadə edər.
Laravel + Octane-dən fərqli olaraq, PHP-FPM-də hər request yeni
bootstrap edir, yaddaş leak-i ciddi problem deyil.
```

**Optimal `pm.max_children` hesablanması:**

```bash
# Server RAM: 4GB, PHP-FPM üçün ayrılan: 2GB
# Bir PHP worker-in ortalama ölçüsü öyrənmək üçün:
ps --no-headers -o "rss,cmd" -C php-fpm | awk '{sum+=$1} END {print sum/NR/1024 " MB per worker"}'

# Nəticə: ~50MB/worker
# max_children = 2048MB / 50MB = 40 worker
```

---

## PHP Garbage Collector

PHP iki mexanizmdən istifadə edir:

**1. Reference Counting:**
Hər zval-da `refcount` var. `refcount` 0 olduqda yaddaş dərhal azad edilir. Bu, çox hallarda GC-nin işinə ehtiyac olmur.

**2. Cycle Collector (GC):**
Dairəvi referanslar reference counting ilə idarə edilə bilmir. PHP-nin GC bunları aşkar edir.

*Dairəvi referanslar reference counting ilə idarə edilə bilmir. PHP-nin üçün kod nümunəsi:*
```php
// Dairəvi referans nümunəsi
class Node
{
    public ?Node $next = null;
}

$a = new Node();
$b = new Node();
$a->next = $b;
$b->next = $a; // Dairəvi referans!
unset($a, $b); // refcount hər ikisi üçün 1-ə düşür (0-a deyil!)
               // Yaddaş GC tərəfindən azad edilməlidir

// CLI skriptlərdə uzun müddətli işlər üçün
gc_collect_cycles(); // Dairəvi referansları dərhal topla
gc_disable();        // GC-ni deaktiv et (performans üçün, diqqətlə)
gc_enable();         // Yenidən aktiv et

// Memory leak aşkarlamaq üçün
$before = memory_get_usage();
// ... kod ...
$after = memory_get_usage();
echo ($after - $before) . " bytes\n";
```

**Uzun müddətli proseslər (Queue worker, Octane) üçün:**
- GC avtomatik işləyir, amma dairəvi referanslar worker-i yavaşlatır
- `memory_limit` həddinə çatmamaq üçün worker-i müəyyən job sayından sonra restart et
- Laravel Horizon-da `maxMemory` konfiqurasiyası bunu idarə edir

---

## Performance Müqayisəsi

```
PHP 5.6 (opcache yox):     baseline
PHP 7.0 (opcache var):     ~2x sürətli (zval refactor)
PHP 7.4 (preload var):     ~3x sürətli
PHP 8.0 (JIT=tracing):     ~3.5x sürətli (web tətbiqləri üçün)
PHP 8.0 (JIT=tracing):     ~5x+ sürətli (CPU-bound üçün)

Opcache HIT RATE tövsiyəsi: 99%+ olmalıdır
opcache_get_status()['opcache_statistics']['opcache_hit_rate']
```

**Opcache monitoring:**

```php
$status = opcache_get_status(false);

echo "Hit rate: " . $status['opcache_statistics']['opcache_hit_rate'] . "%\n";
echo "Cached scripts: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
echo "Memory used: " . round($status['memory_usage']['used_memory'] / 1024 / 1024) . "MB\n";
echo "Memory free: " . round($status['memory_usage']['free_memory'] / 1024 / 1024) . "MB\n";

// Yaddaş dolubsa:
if ($status['memory_usage']['free_memory'] < 1024 * 1024 * 10) {
    // opcache.memory_consumption-ı artır!
}
```

---

## Praktiki Pitfall-lar

**1. Production-da stale kod problemi:**

```bash
# Kod deploy etdin, amma opcache köhnə kodu saxlayır
# validate_timestamps=0 olduqda bu baş verir

# Həll: Deploy skriptinə əlavə et:
php artisan opcache:clear
# və ya
sudo systemctl reload php8.2-fpm
# və ya
kill -USR2 $(cat /var/run/php-fpm.pid)
```

**2. Docker container-də OPcache problemi:**

```dockerfile
# Problem: hər container-də ayrı shared memory var
# Çözüm gerekmez — container-lər arasında paylaşma olmur,
# amma container içindəki worker-lər paylaşır (bu normaldır)

# php.ini for Docker:
FROM php:8.2-fpm
RUN docker-php-ext-enable opcache
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini
```

**3. Preloading sinif konfliktləri:**

```php
// Problem: Preload zamanı autoload işləmir
// Həll: Preload skriptində manual require et

// Yanlış:
opcache_compile_file('/path/to/MyClass.php');
// MyClass parent-i yüklənməyibsə bu fail olur

// Düzgün:
require_once '/path/to/ParentClass.php';
require_once '/path/to/MyClass.php';
```

**4. CLI-də OPcache:**

```bash
# Development-də CLI skriptlər opcache-dən faydalanmır
# (opcache.enable_cli=0 default)

# Uzun müddətli CLI skriptlər üçün aktiv etmək olar:
opcache.enable_cli=1

# Amma diqqət: CLI-də hər proses ayrı OPcache yaddaşı yaradır
```

---

## İntervyu Sualları

**1. OPcache nədir və necə işləyir?**
OPcache PHP-nin compile edilmiş opcodes-larını shared memory-də saxlayan extension-dır. PHP hər sorğuda .php faylını yenidən parse etmək əvəzinə, öncədən compile edilmiş opcodes-ı shared memory-dən oxuyur. Bu Lexer, Parser və Compiler mərhələlərini atlayır.

**2. `opcache.validate_timestamps=0` nə deməkdir, nə vaxt istifadə edilir?**
Bu ayar aktiv olduqda OPcache fayl sistemindəki dəyişiklikləri yoxlamır. Production-da sürəti artırır çünki hər sorğuda `stat()` sistem çağırışı edilmir. Deploy zamanı manual `opcache_reset()` lazımdır.

**3. JIT PHP-nin hansı növ tətbiqlərinə fayda verir?**
CPU-bound tətbiqlərə (şəkil emalı, riyazi hesablamalar) böyük fayda verir. Tipik I/O-bound web tətbiqlərinə (database sorğuları, API çağırışları) minimal fayda verir çünki bottleneck CPU deyil, I/O-dur.

**4. Preloading ilə Opcache arasındakı fərq nədir?**
Opcache faylları ilk sorğuda compile edib cache-ə saxlayır. Preloading isə server start zamanı faylları shared memory-ə yükləyir — hər sorğuda hətta opcache lookup belə olmur. Preloading daha sürətlidir amma server restart lazımdır.

**5. OPcache yaddaşı dolduqda nə baş verir?**
`opcache.max_wasted_percentage` həddini keçdikdə OPcache özünü restart edir. Əvvəlcə yeni fayllar cache-ə alınmır (performance düşür). `opcache.memory_consumption`-ı artırmaq lazımdır.

**6. Deploy zamanı OPcache-i necə sıfırlayırsınız?**
`opcache_reset()` funksiyası çağırılır, `php artisan opcache:clear` (Laravel paketi), PHP-FPM graceful restart (`systemctl reload php-fpm`) və ya `kill -USR2` siqnalı göndərilir.

**7. `opcache.interned_strings_buffer` nədir?**
PHP-də eyni string dəyəri olan bütün dəyişənlər bu buffer-dan eyni yaddaş ünvanını istifadə edir. Class adları, method adları, string literal-lar burada saxlanır. Böyük tətbiqlərdə bu buffer-ı artırmaq yaddaş sərfiyyatını azaldır.

**8. JIT tracing və function rejimləri arasındakı fərq nədir?**
`function` JIT bütün funksiyaları compile edir. `tracing` JIT yalnız tez-tez icra olunan "hot path"-ləri compile edir — daha ağıllı seçim edir və adətən daha yaxşı nəticə verir.

**9. Opcache hit rate neçə faiz olmalıdır?**
99%+ olmalıdır. 95%-dən aşağıdırsa `opcache.max_accelerated_files`-ı artırmaq lazımdır. `opcache_get_status()['opcache_statistics']['opcache_hit_rate']` ilə yoxlanılır.

**10. PHP 8.x-də Zend Engine neçənci versiyadır?**
PHP 8.x Zend Engine 4 istifadə edir. PHP 7.x Zend Engine 3, PHP 5.x isə Zend Engine 2 istifadə edirdi.

**11. `opcache.file_cache` nədir, nə vaxt faydalıdır?**
`opcache.file_cache=/tmp/opcache` konfiqurasiyası ilə compile edilmiş opcodes fayl sisteminə yazılır. Server restart-dan sonra shared memory yenidən qurulmadan disk cache-dən oxunur. Bu xüsusilə Docker container restart zamanı faydalıdır — hər başlangıcda yenidən compile edilmir. `opcache.file_cache_only=1` ilə yalnız disk cache istifadə edilə bilər (shared memory olmadan).

**12. PHP-FPM worker prosesinin ölçüsü OPcache-ə necə təsir edir?**
Hər PHP-FPM worker shared memory-dəki OPcache-i paylaşır, lakin hər worker-in özünün execution stack-i var. Nə qədər çox worker olsa, paylaşılan OPcache-dən daha çox faydalanılır. Worker başlatılarkən (`onSpawn`) OPcache artıq dolu olduğundan əlavə compile xərci olmur. Bu PHP-nin thread-based modellərlə müqayisədə əsas üstünlüklərindən biridir.

**13. Copy-on-Write (COW) PHP-nin yaddaş idarəetməsində nə rolu oynayır?**
PHP dəyişkən assignment zamanı dərin kopyalamır — hər iki dəyişkən eyni zval-a işarə edir, `refcount` artır. Yalnız biri dəyişdirildikdə həqiqi kopyalanma (COW) baş verir. Bu böyük array-lərin ötürülməsini sürətləndirir. OPcache shared memory-dəki string interning də eyni prinsiplə işləyir — eyni string literal yalnız bir dəfə yaddaşda saxlanır.

---

## Anti-patternlər

**1. OPcache-i production-da deaktiv saxlamaq**
`opcache.enable=0` konfiqurasiyası ilə OPcache-i söndürmək — hər sorğuda PHP faylları yenidən parse və compile edilir, performans 5-10x aşağı düşür. Production-da `opcache.enable=1` mütləq aktiv olmalıdır, `memory_consumption` tətbiqin ölçüsünə görə konfiqurasiya edilməlidir.

**2. OPcache yaddaşını kifayət qədər konfiqurasiya etməmək**
`opcache.memory_consumption=64` (default) böyük Laravel tətbiqləri üçün azdır — yaddaş dolduqda OPcache özünü restart edir, performans qeyri-sabit olur. `opcache_get_status()` ilə istifadəni izlə, böyük tətbiqlər üçün 256-512MB ayrı.

**3. Development-də `validate_timestamps=0` işlətmək**
Sürət üçün development mühitində timestamp yoxlamasını deaktiv etmək — fayl dəyişiklikləri avtomatik görünmür, hər dəfə OPcache manual sıfırlamaq lazım gəlir. `validate_timestamps=1` development-də saxla, yalnız production-da `0` et.

**4. JIT-i bütün növ PHP tətbiqləri üçün aktiv etmək**
I/O-bound Laravel tətbiqində CPU optimizasiyası gözləmək — JIT faydası yalnız CPU-intensive hesablamalar üçündür; HTTP request/response dövrüsü DB, cache, network I/O-ya bağlıdır, JIT az fayda verir. JIT-i əvvəl benchmark et, real fayda görmürsənsə aktivləşdirməyə dəyməz.

**5. Deploy sonrası OPcache-i sıfırlamamaq**
Yeni kodu yerləşdirib köhnə cache-in avtomatik yenilənəcəyini güman etmək — `validate_timestamps=0` mühitlərində köhnə compile edilmiş kod icra edilməyə davam edir. Deploy pipeline-a `opcache_reset()` çağrışı ya da PHP-FPM graceful reload əlavə et.

**6. `opcache.max_accelerated_files`-ı kiçik saxlamaq**
Default dəyər (10000) böyük Composer dependency-ləri olan tətbiqlər üçün azdır — bəzi fayllar cache-ə alınmır, hit rate aşağı düşür. `find . -name "*.php" | wc -l` ilə fayl sayını öyrən, `max_accelerated_files`-ı ondan böyük prime rəqəm ilə konfiqurasiya et.

**7. `opcache.save_comments=0` ilə PHPDoc şərhlərini silmək**
`opcache.save_comments=0` edib performans qazanmağa çalışmaq — Laravel, Doctrine, Symfony kimi framework-lər Reflection API vasitəsilə PHPDoc annotasiyaları oxuyur (`@var`, `@param`, route annotasiyaları). Şərhlər silinərsə framework-lər düzgün işləmir, DI container binding-ləri pozulur. `save_comments=1` həmişə saxla.

**8. Immutable Docker image-ə OPcache file cache yazmağa çalışmaq**
`opcache.file_cache`-i container-in read-only filesystem-inə yönləndirmək — container runtime-da yazma edilə bilmir, OPcache file cache fail olur, silent performance degradation. File cache-i writable volume-ə (`/tmp/opcache`) yönləndir, ya da `opcache.file_cache_only=0` ilə yalnız shared memory istifadə et.
