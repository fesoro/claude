# PHP Internals və Memory Management (Senior)

## 1. PHP necə işləyir? Zend Engine nədir?

PHP kodu icra prosesi:
```
PHP Code → Lexer (Tokenization) → Parser (AST) → Compiler (Opcodes) → Zend VM (Execution)
```

1. **Lexer/Tokenizer** — kodu token-lərə ayırır (`T_VARIABLE`, `T_STRING`, ...)
2. **Parser** — token-lərdən Abstract Syntax Tree (AST) yaradır
3. **Compiler** — AST-dən opcode-lar (bytecode) yaradır
4. **Zend VM** — opcode-ları icra edir

```php
// Token-ləri görmək
$tokens = token_get_all('<?php echo "hello"; ?>');
// [T_OPEN_TAG, T_ECHO, T_WHITESPACE, T_CONSTANT_ENCAPSED_STRING, ...]

// Opcode-ları görmək (phpdbg və ya VLD extension)
// phpdbg -p script.php
```

---

## 2. OPcache nədir və necə işləyir?

OPcache — compile olunmuş opcode-ları shared memory-də saxlayır, hər request-də yenidən compile etmir.

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256        ; MB
opcache.max_accelerated_files=20000   ; Max fayl sayı
opcache.validate_timestamps=0         ; Production-da 0 (manual reset)
opcache.revalidate_freq=0
opcache.interned_strings_buffer=16    ; MB
opcache.jit=1255                      ; JIT aktiv (PHP 8+)
opcache.jit_buffer_size=128M

; Preloading (PHP 7.4+) — boot zamanı sinifləri yaddaşa yüklə
opcache.preload=/var/www/app/preload.php
opcache.preload_user=www-data
```

```php
// preload.php
require __DIR__ . '/vendor/autoload.php';

// Tez-tez istifadə olunan sinifləri preload et
$files = glob(__DIR__ . '/app/Models/*.php');
foreach ($files as $file) {
    opcache_compile_file($file);
}
```

**OPcache reset:**
```php
opcache_reset();           // Bütün cache-i sil
opcache_invalidate($file); // Tək faylı sil

// Deploy sonrası:
php artisan opcache:clear  // və ya
// Nginx: kill -USR2 $(cat /run/php/php-fpm.pid)
```

---

## 3. PHP Memory Management və Garbage Collection

```php
// Yaddaş istifadəsi
echo memory_get_usage();       // Cari yaddaş (bytes)
echo memory_get_peak_usage();  // Pik yaddaş
echo ini_get('memory_limit');  // Limit (default: 128M)

// Memory limit dəyişmək
ini_set('memory_limit', '512M');
```

**PHP yaddaş idarəsi:**
- **Reference Counting** — hər dəyişənin reference sayı var
- Reference count 0 olanda dərhal azad edilir
- **Circular Reference** problemi — iki obyekt bir-birinə istinad edir

```php
// Reference counting
$a = "hello";  // refcount = 1
$b = $a;        // refcount = 2 (copy-on-write, əslində kopyalanmır)
$b = "world";   // $a refcount = 1, yeni string yaranır

// Circular reference — GC lazımdır
class Node {
    public ?Node $next = null;
}
$a = new Node();
$b = new Node();
$a->next = $b;
$b->next = $a;  // Dairəvi istinad
unset($a, $b);  // Reference count 0 olmur! GC lazımdır

// Garbage Collector
gc_enable();             // GC aktiv et (default: aktiv)
gc_collect_cycles();     // Manual GC çağır
gc_disable();            // Performans üçün (ehtiyatla)
echo gc_status()['runs']; // GC neçə dəfə işləyib
```

**Copy-on-Write (COW):**
```php
$a = str_repeat('x', 1000000); // 1MB
$b = $a;  // Kopyalanmır! Eyni yaddaşa işarə edir
// Yaddaş: ~1MB

$b .= 'y'; // İndi kopyalanır (write oldu)
// Yaddaş: ~2MB
```

---

## 4. PHP Streams nədir?

Stream — data oxuma/yazma üçün unified interfeys. File, HTTP, socket hamısı stream-dir.

```php
// Stream wrappers
file_get_contents('file:///tmp/data.txt');  // file://
file_get_contents('https://api.example.com'); // https://
file_get_contents('php://input');            // raw POST body
file_get_contents('php://stdin');            // CLI input

// php:// streams
php://input    — raw request body (PUT, PATCH üçün)
php://output   — output buffer-ə yaz
php://memory   — yaddaşda temp stream (kiçik data)
php://temp     — yaddaş/disk hybrid (böyük data)
php://stdin    — CLI standard input
php://stdout   — CLI standard output
php://stderr   — CLI error output

// Stream context
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode(['key' => 'value']),
        'timeout' => 30,
    ],
    'ssl' => [
        'verify_peer' => true,
    ],
]);
$response = file_get_contents('https://api.example.com', false, $context);

// Stream filters
$fp = fopen('file.txt', 'r');
stream_filter_append($fp, 'string.toupper');
echo fread($fp, 1024); // BÖYÜK HƏRFLƏRLƏ

// Böyük fayl kopyalama (stream-to-stream)
$source = fopen('large-file.zip', 'r');
$dest = fopen('s3://bucket/file.zip', 'w');
stream_copy_to_stream($source, $dest);
```

---

## 5. SPL (Standard PHP Library)

```php
// Data Structures
$stack = new SplStack();     // LIFO
$stack->push('a');
$stack->push('b');
echo $stack->pop(); // 'b'

$queue = new SplQueue();     // FIFO
$queue->enqueue('a');
$queue->enqueue('b');
echo $queue->dequeue(); // 'a'

$heap = new SplMinHeap();    // Priority queue
$heap->insert(5);
$heap->insert(1);
$heap->insert(3);
echo $heap->extract(); // 1 (ən kiçik)

$set = new SplObjectStorage(); // Object set
$obj1 = new stdClass();
$set->attach($obj1, 'metadata');
$set->contains($obj1); // true

// SplFixedArray — normal array-dən 30-50% az yaddaş
$arr = new SplFixedArray(1000000);
$arr[0] = 'value';

// Iterators
$dir = new RecursiveDirectoryIterator('/path');
$iterator = new RecursiveIteratorIterator($dir);
$phpFiles = new RegexIterator($iterator, '/\.php$/');
foreach ($phpFiles as $file) {
    echo $file->getPathname();
}

// SplAutoloader (əvvəl istifadə olunurdu, indi composer var)
spl_autoload_register(function (string $class) {
    $file = str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require $file;
});
```

---

## 6. PHP Fibers (PHP 8.1+)

Fiber — cooperative multitasking. Icranı dayandırıb sonra davam etdirmək.

```php
$fiber = new Fiber(function (): void {
    $value = Fiber::suspend('first');  // Dayandır, 'first' qaytar
    echo "Got: $value\n";
    Fiber::suspend('second');
});

$result1 = $fiber->start();     // 'first'
$result2 = $fiber->resume('hello'); // "Got: hello", 'second' qaytarır
$fiber->resume();               // Bitir

// Real-world: Async HTTP requests (amphp, ReactPHP əsasını Fibers təşkil edir)
// Laravel-də birbaşa istifadə etmirik, amma async driver-lar bunun üzərindədir
```

---

## 7. PHP 8 JIT (Just-In-Time Compilation) nədir?

JIT — opcode-ları native machine code-a çevirir.

```ini
; php.ini
opcache.jit=1255
opcache.jit_buffer_size=128M

; JIT modes (CRTO):
; C: CPU-specific optimization (0=off, 1=on)
; R: Register allocation (0=off, 1=on)
; T: Trigger (0=script load, 1=first execution, 2=hot, 3=always, 4=trace, 5=hot trace)
; O: Optimization level (0-5)
; 1255 = tracing JIT (ən yaxşı ümumi performans)
```

**JIT nə vaxt kömək edir?**
- CPU-intensive əməliyyatlar (math, image processing, ML)
- Uzun-müddətli proseslər (queue workers, Octane)

**JIT nə vaxt kömək ETMİR?**
- Tipik web request-lər (I/O bound — DB, HTTP)
- OPcache artıq əsas performansı təmin edir

---

## 8. PHP-nin weak typing ilə bağlı tələlər

```php
// == vs === tələləri
var_dump(0 == "foo");     // PHP 7: true, PHP 8: false
var_dump("" == null);     // true
var_dump("0" == false);   // true
var_dump(null == false);  // true
var_dump("" == false);    // true
var_dump("php" == 0);     // PHP 7: true, PHP 8: false

// in_array tələsi
in_array(0, ['a', 'b', 'c']);       // PHP 7: true! PHP 8: false
in_array(0, ['a', 'b'], true);      // false (strict mode)

// array_search
array_search(0, ['a', 'b']);         // PHP 7: 0 (index), PHP 8: false
array_search(0, ['a', 'b'], true);   // false (strict)

// switch vs match
switch (0) {
    case 'foo': echo "HIT"; break;   // PHP 7: HIT! PHP 8: yox
}
// match həmişə strict comparison edir — bu problemi yoxdur

// Numeric string
var_dump("123" + 0);       // int(123)
var_dump("123abc" + 0);    // PHP 8: Warning + int(123)

// Best practice:
declare(strict_types=1);  // Hər faylın əvvəlinə yaz
// Həmişə === istifadə et
// in_array-da 3-cü parametr true ver
```

---

## 9. PHP Process Model — PHP-FPM necə işləyir?

```
Web Server (Nginx) → FastCGI → PHP-FPM (master process)
                                 ├── Worker 1 (child process)
                                 ├── Worker 2
                                 ├── Worker 3
                                 └── ...
```

**Hər request:**
1. Nginx request-i PHP-FPM-ə göndərir
2. Boş worker prosesi request-i qəbul edir
3. PHP kodu icra edir (shared-nothing architecture)
4. Response qaytarır
5. Worker yaddaşı təmizlənir, növbəti request-i gözləyir

```ini
; /etc/php/8.3/fpm/pool.d/www.conf

; Process management
pm = dynamic               ; static, dynamic, ondemand
pm.max_children = 50        ; Maksimum worker sayı
pm.start_servers = 10       ; Başlanğıcda neçə worker
pm.min_spare_servers = 5    ; Minimum boş worker
pm.max_spare_servers = 20   ; Maksimum boş worker
pm.max_requests = 500       ; Worker neçə request-dən sonra restart olsun

; Memory leak-lərə qarşı
pm.max_requests = 1000      ; 1000 request-dən sonra worker restart

; Slow log
slowlog = /var/log/php-fpm/slow.log
request_slowlog_timeout = 5s  ; 5 saniyədən yavaş request-ləri logla

; Timeout
request_terminate_timeout = 60s
```

**Static vs Dynamic vs Ondemand:**
- **static** — sabit worker sayı (production, stabil traffic)
- **dynamic** — traffic-ə görə worker artır/azalır (ən çox istifadə olunan)
- **ondemand** — yalnız lazım olanda worker yaradır (aşağı traffic)

**Worker sayı hesablama:**
```
max_children = Available RAM / Average PHP process memory
Misal: 4GB RAM / 50MB per process ≈ 80 workers (digər servislərə yer saxla → ~50)
```
