# PHP JIT Compiler (Junior)

## Mündəricat
1. [JIT nədir?](#jit-nədir)
2. [Tracing JIT vs Function JIT](#tracing-jit-vs-function-jit)
3. [JIT nə vaxt fayda verir?](#jit-nə-vaxt-fayda-verir)
4. [Konfiqurasiya](#konfiqurasiya)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## JIT nədir?

```
JIT (Just-In-Time) — PHP 8.0-da əlavə edildi.
OPcache üzərində işləyir.

Normal PHP icra:
  PHP kod → Parse → AST → Opcode (OPcache) → Zend VM icra

JIT ilə:
  PHP kod → Parse → AST → Opcode (OPcache) → Native machine code → CPU birbaşa icra

  Zend VM aradan çıxır → daha sürətli icra

OPcache vs JIT:
  OPcache: Parsing + compilation nəticəsini cache-ləyir (opcode)
  JIT:     Opcode-u native machine code-a çevirir (runtime-da)

Niyə PHP-də fayda azdır (web üçün):
  Web request-ləri I/O ağırlıqlıdır (DB, cache, network)
  JIT yalnız CPU-bound kodu sürətləndirir
  I/O gözləmə zamanı JIT heç nə edə bilmir
```

---

## Tracing JIT vs Function JIT

```
Function JIT (jit=1):
  Hər funksiya ayrıca compile edilir.
  "Bu funksiya çağırıldı → compile et"
  Sadə, az overhead.

Tracing JIT (jit=4, default recommend):
  İcra yollarını (trace) izləyir.
  Ən çox işlədilən hot path-ləri compile edir.
  Loop-lar, rekursiya üçün daha effektiv.

  PHP_JIT_TRACE:
    if ($x > 0) {         ← bu branch tez-tez keçilirsə
        compute($x);      ← bu trace compile olunur
    }

php.ini:
  opcache.jit=tracing   → tracing JIT
  opcache.jit=function  → function JIT
  opcache.jit=off       → JIT söndür
  opcache.jit_buffer_size=128M  → native code üçün RAM
```

---

## JIT nə vaxt fayda verir?

```
Faydalı:
  ✓ CPU-intensive hesablamalar
  ✓ Image processing (GD, Imagick)
  ✓ Math operations (scientific computing)
  ✓ String manipulation at scale
  ✓ ML/AI inference in PHP
  ✓ Game logic, simulation

Faydasız (web API):
  ✗ DB sorğuları gözlənilir → I/O bound
  ✗ Redis/cache çağırışları → network I/O
  ✗ File read/write → disk I/O
  ✗ HTTP request-lər → network I/O

Benchmark (PHP 8.0 JIT):
  Fibonacci (recursive): +40% sürət
  Mandelbrot:           +300% sürət
  WordPress benchmark:  +2% sürət (əhəmiyyətsiz)
  Laravel request:      +3% sürət (əhəmiyyətsiz)
```

---

## Konfiqurasiya

```ini
; php.ini
opcache.enable=1
opcache.enable_cli=1

; JIT aktivasiya
opcache.jit=tracing           ; tracing mode (tövsiyə)
opcache.jit_buffer_size=128M  ; JIT code buffer (worker başına deyil, shared)

; JIT debug (development)
opcache.jit_debug=0           ; 1: asm output, production-da 0

; Optimal web app konfiqurasiyası (JIT minimal fayda):
opcache.jit=off  ; ya da tracing, fərqi az

; Optimal CPU-intensive konfiqurasiya:
opcache.jit=tracing
opcache.jit_buffer_size=256M
```

---

## PHP İmplementasiyası

```php
<?php
// JIT faydasını ölçmək — Fibonacci benchmark
function fibonacci(int $n): int
{
    if ($n <= 1) return $n;
    return fibonacci($n - 1) + fibonacci($n - 2);
}

$start = hrtime(true);
$result = fibonacci(35);
$elapsed = (hrtime(true) - $start) / 1e6;

echo "Result: {$result}, Time: {$elapsed}ms\n";
// JIT off: ~800ms
// JIT on:  ~350ms (tracing)

// JIT status yoxlamaq
$status = opcache_get_status();
if (isset($status['jit'])) {
    echo "JIT enabled: " . ($status['jit']['enabled'] ? 'yes' : 'no') . "\n";
    echo "JIT buffer size: " . $status['jit']['buffer_size'] . "\n";
    echo "JIT buffer free: " . $status['jit']['buffer_free'] . "\n";
}
```

---

## İntervyu Sualları

- PHP JIT OPcache-dən nəylə fərqlənir?
- Tracing JIT vs Function JIT — fərqi nədir?
- Typical Laravel/Symfony API üçün JIT enable etmək məntiqlidirmi?
- JIT hansı tip PHP kodu üçün ən çox fayda verir?
- `opcache.jit_buffer_size` çox kiçik seçilsə nə baş verər?
