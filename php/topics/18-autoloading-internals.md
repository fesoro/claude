# PHP Autoloading Internals (Middle)

## Mündəricat
1. [Autoloading nədir?](#autoloading-nədir)
2. [Tarix: __autoload → SPL → PSR-4](#tarix)
3. [spl_autoload_register](#spl_autoload_register)
4. [PSR-0 (köhnə)](#psr-0-köhnə)
5. [PSR-4 spec deep](#psr-4-spec-deep)
6. [Composer autoloader internals](#composer-autoloader-internals)
7. [Classmap və classmap-authoritative](#classmap-və-classmap-authoritative)
8. [APCu cache](#apcu-cache)
9. [Performance tuning](#performance-tuning)
10. [Custom autoloader yazma](#custom-autoloader-yazma)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Autoloading nədir?

```
Autoloading — class adından file path-ə avtomatik resolve.

ƏVVƏL (PHP 4):
  require_once 'classes/User.php';
  require_once 'classes/Post.php';
  $user = new User();
  // Hər class üçün manual require — 100 class = 100 require sətri

İNDI:
  $user = new User();
  // PHP autoloader-i çağırır → class file-ı tapır → include edir
  // Lazy: yalnız istifadə olunan class-lar yüklənir

Niyə lazımdır?
  ✓ Boilerplate azalır
  ✓ Performance (lazy load)
  ✓ Refactoring asan (file rename → namespace dəyiş)
  ✓ PSR-4 standart — package-lar arası uyğunluq
```

---

## Tarix

```
PHP 5.0 (2004) — __autoload() global function (köhnə, deprecated)
  function __autoload($class) {
      include "classes/$class.php";
  }

PHP 5.1 (2005) — SPL autoload (multiple registered loaders)
  spl_autoload_register(function ($class) {
      include "classes/$class.php";
  });

PSR-0 (2010) — namespace + underscore convention
  My_Class → My/Class.php
  Namespace\My_Class → Namespace/My/Class.php

PSR-4 (2014) — modern autoloading standard
  Namespace prefix → base directory
  Underscore artıq "namespace separator" deyil

PHP 8.0 (2020) — __autoload() removed (yalnız spl_autoload_register)
```

---

## spl_autoload_register

```php
<?php
// Register single autoloader
spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Multiple loaders (chain)
spl_autoload_register([$myCustomLoader, 'load'], throw: true, prepend: false);

// Throw — autoloader register fail edirsə Exception
// Prepend — true → stack-in əvvəlinə (yüksək priority)

// Get registered
$loaders = spl_autoload_functions();
print_r($loaders);

// Unregister
spl_autoload_unregister([$loader, 'load']);

// Manual trigger (rare)
spl_autoload_call('My\Namespace\Class');
```

```
PHP class lookup workflow:
  1. new MyClass()
  2. PHP daxili class table-da yoxlayır → tapsa istifadə et
  3. Yoxsa: spl_autoload-ları sıra ilə çağır
  4. Hər loader: file include etməlidir, class-ı təyin etməlidir
  5. Loader uğurla class yarada bildi → istifadə et
  6. Hamısı fail etdi → Fatal error: Class not found

Autoloader stack — sıra ilə sınanır:
  Composer autoloader (default), classmap, PSR-4, files
```

---

## PSR-0 (köhnə)

```
PSR-0 (deprecated 2014):
  Namespace prefix    File path
  ───────────────────────────────────────
  Doctrine\\          /src/Doctrine/...
  My_Class            /src/My/Class.php   (underscore = separator!)
  My\Foo_Bar          /src/My/Foo/Bar.php

Problem:
  Underscore convention legacy code-dan gəlir (PEAR)
  Modern PHP-də artıq lazımsızdır

PSR-4 (2014) bunu həll etdi:
  - Underscore SADƏCƏ class adı (separator deyil)
  - Daha qısa file path-lər
```

---

## PSR-4 spec deep

```
PSR-4 spec (specifically):
  
  fully qualified class name:   \App\Service\UserService
  ↓ namespace prefix:           \App\
  ↓ base directory:             src/
  ↓ subdirectory:               Service/
  ↓ file:                       UserService.php
  
  Result file path:             src/Service/UserService.php

composer.json:
{
  "autoload": {
    "psr-4": {
      "App\\":          "src/",
      "App\\Tests\\":   "tests/",
      "Vendor\\Pkg\\":  "vendor-dir/pkg/lib/"
    }
  }
}

Multiple base directories:
  "App\\": ["src/", "src-extra/"]
  // İlk base-dir-də tap → istifadə et, yoxsa ikinci

Qaydalar:
  1. Tam class adı namespace prefix ilə başlamalıdır
  2. Prefix-dən sonrakı separator-lar slash-a çevrilir
  3. Sonuncu hissə + ".php" file adıdır
  4. Case sensitive (Linux production-da problem!)
```

```php
<?php
// PSR-4 manual implementation (Composer-siz)
spl_autoload_register(function (string $class) {
    $prefixes = [
        'App\\'        => __DIR__ . '/src/',
        'App\\Tests\\' => __DIR__ . '/tests/',
    ];
    
    foreach ($prefixes as $prefix => $base) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        $relative = substr($class, $len);
        $file = $base . str_replace('\\', '/', $relative) . '.php';
        
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
```

---

## Composer autoloader internals

```
composer install / update zamanı:
  vendor/composer/autoload_*.php fayl-lar generate olunur:
    autoload_classmap.php   — explicit class → file map
    autoload_psr4.php       — namespace → directory map
    autoload_namespaces.php — PSR-0 (legacy)
    autoload_files.php      — globally loaded (helpers)
    autoload_real.php       — actual autoloader registration
    autoload_static.php     — optimized cache
  
  vendor/autoload.php       — entry point (require this)

Workflow class lookup:
  1. ClassLoader::loadClass('App\Service\UserService')
  2. Authoritative classmap-da yoxla
     - tapdı → require, finish
  3. Standard classmap-da yoxla
  4. PSR-4 prefix-lərə baxırı
     - "App\\" prefix var → src/Service/UserService.php
  5. PSR-0 fallback (legacy)
  6. Tapılmadı → false return → növbəti autoloader-ə
```

```bash
# Optimization commands
composer dump-autoload                          # standard
composer dump-autoload --optimize -o            # classmap əlavə et (production)
composer dump-autoload --classmap-authoritative # YALNIZ classmap (PSR-4 axtarmaz)
composer dump-autoload --apcu                   # APCu cache integration
```

---

## Classmap və classmap-authoritative

```
CLASSMAP — explicit class → file mapping:
  composer.json:
    "autoload": {
      "classmap": ["src/", "tests/"]
    }
  
  Composer scan edir, hər class üçün entry yaradır:
    'App\Service\UserService' => __DIR__ . '/src/Service/UserService.php',
    'App\Service\OrderService' => __DIR__ . '/src/Service/OrderService.php',
  
  Lookup O(1) — array key search.

PSR-4 lookup:
  Hər prefix yoxla → file_exists() çağır → disk I/O.
  Slow ish, classmap-dan yavaş.

OPTIMIZED autoloader (composer dump-autoload -o):
  PSR-4 + classmap birgə.
  Hər PSR-4 prefix-dəki bütün class-lar classmap-a generate olunur.
  Lookup tam O(1).

AUTHORITATIVE classmap (--classmap-authoritative):
  YALNIZ classmap istifadə olunur, PSR-4 fallback yoxdur.
  Daha sürətli, AMMA:
  - Yeni class əlavə → manual dump lazım
  - Production-da DEPLOY zamanı dump etməlisiniz
  - Dynamic class generation (e.g., proxy) işləməz

Performance:
  Default PSR-4:        ~50µs lookup
  -o (optimized):       ~5µs
  --classmap-auth:      ~3µs (faster, no fallback)
  -o + --apcu:          ~1µs (with APCu cache)
```

---

## APCu cache

```bash
# APCu extension (in-memory cache PHP)
sudo apt install php-apcu
echo "extension=apcu" >> /etc/php/8.3/fpm/conf.d/20-apcu.ini
echo "apcu.enable_cli=1" >> /etc/php/8.3/cli/conf.d/20-apcu.ini

# Composer dump with APCu
composer dump-autoload -o --apcu
```

```php
<?php
// Composer ApcuClassLoader istifadə edir
// İlk request: classmap-dan oxunur, APCu-ya yazılır
// Sonrakı request: APCu-dan birbaşa (ən sürətli)

// FPM-də faydalıdır (worker-lər APCu shared memory-ni paylaşır)
// CLI-də fayda yoxdur (process bitir, APCu sıfırlanır)
```

---

## Performance tuning

```
1. Production deployment-də HƏMİŞƏ:
   composer install --no-dev --optimize-autoloader
   
   --no-dev:      dev dependencies skip
   --optimize:    classmap generate (-o)

2. Authoritative (advanced):
   composer install --no-dev --classmap-authoritative
   
   ⚠ Hər deploy-da composer dump-autoload lazımdır
   ⚠ Dynamic class loading söndürülür (rare)

3. APCu (fastest):
   composer install --no-dev --optimize-autoloader --apcu-autoloader

4. OPcache + autoloader:
   OPcache hər file-ı bytecode cache edir.
   Autoloader-in include() sürətlənir.
   opcache.enable=1, opcache.memory_consumption=256

5. Preload (PHP 7.4+):
   opcache.preload=/var/www/preload.php
   // preload.php — opcache_compile_file() ilə core class-lar
   Worker boot-da preload, sonra hər request "free" istifadə edir.
   Laravel Octane bunu daxili istifadə edir.
```

---

## Custom autoloader yazma

```php
<?php
// Use case: dynamic class generation
class DynamicAutoloader
{
    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }
    
    public function load(string $class): bool
    {
        // Dynamic proxy class generation
        if (str_starts_with($class, 'Proxy\\')) {
            $original = substr($class, 6);
            
            $code = "<?php
            class \\$class extends \\$original {
                public function __call(\$method, \$args) {
                    // Logging, caching, etc.
                    return parent::\$method(...\$args);
                }
            }";
            
            eval($code);
            return true;
        }
        
        return false;
    }
}

(new DynamicAutoloader())->register();

// $proxy = new \Proxy\App\Service\UserService();   // dynamic class
```

---

## İntervyu Sualları

- `spl_autoload_register` ilə `__autoload` arasındakı fərq?
- PSR-4 və PSR-0 arasındakı əsas fərqlər?
- `composer dump-autoload -o` nə edir? Niyə production-da lazımdır?
- `--classmap-authoritative` nə vaxt təhlükəlidir?
- APCu autoloader-də necə kömək edir? CLI-də niyə fayda yoxdur?
- Class lookup-də Composer hansı sıra ilə axtarır?
- OPcache preload nədir? Performance-a necə təsir edir?
- Linux production-da PSR-4 case-sensitivity niyə bug yaradır?
- Custom autoloader nə vaxt yazılır?
- Multiple PSR-4 base directory necə işləyir?
- `composer install --no-dev` nə vaxt istifadə olunur?
- Autoloader-lər niyə "stack" şəklində işləyir?
