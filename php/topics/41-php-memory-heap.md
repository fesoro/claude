# PHP Memory Model — Heap, Stack, Garbage Collection, Memory Leaks

## Mündəricat
1. [Stack vs Heap PHP-də](#stack-vs-heap-phpdə)
2. [zval strukturu və Reference Counting](#zval-strukturu-və-reference-counting)
3. [Copy-on-Write (COW)](#copy-on-write-cow)
4. [Garbage Collector](#garbage-collector)
5. [memory_limit](#memory_limit)
6. [Yaddaş istifadəsini ölçmək](#yaddaş-istifadəsini-ölçmək)
7. [Ümumi Memory Leak-lər](#ümumi-memory-leak-lər)
8. [Uzun Müddətli Proseslərdə Memory Leak](#uzun-müddətli-proseslərdə-memory-leak)
9. [Profiling Alətləri](#profiling-alətləri)
10. [Praktiki Məsləhətlər](#praktiki-məsləhətlər)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Stack vs Heap PHP-də

```
┌─────────────────────────────────────────────┐
│                  PHP Prosesi                │
│                                             │
│  ┌──────────────┐    ┌────────────────────┐ │
│  │    STACK     │    │        HEAP        │ │
│  │              │    │                    │ │
│  │ - Funksiya   │    │ - Obyektlər        │ │
│  │   çağırış    │    │ - Array-lər        │ │
│  │   stack-i    │    │ - String-lər       │ │
│  │ - Local var  │    │ - Dinamik yaddaş   │ │
│  │   (zval ref) │    │                    │ │
│  │              │    │  Zend MM idarə     │ │
│  │  Sabit ölçü  │    │  edir              │ │
│  └──────────────┘    └────────────────────┘ │
└─────────────────────────────────────────────┘
```

PHP-də **hər şey heap-dədir** demək olar. Stack yalnız funksiya çağırış frames-lərini saxlayır. zval-lar (PHP dəyişənləri) heap-də yerləşir.

---

## zval strukturu və Reference Counting

PHP 7+ zval:

*zval strukturu və Reference Counting üçün kod nümunəsi:*
```c
struct _zval_struct {
    zend_value value;    // faktiki dəyər
    uint32_t   type_info; // tip + flags
};

// type_info flags:
// IS_TYPE_REFCOUNTED    — reference counting istifadə edir
// IS_TYPE_COLLECTABLE   — GC cycle collector-a daxildir
```

**Reference Counting:**

```php
$a = "salam";   // refcount=1
$b = $a;        // refcount=2 (kopyalanmır! COW)
unset($a);      // refcount=1
unset($b);      // refcount=0 → yaddaş azad edilir
```

```
$a = "salam"
    │
    ▼
zval {value: "salam", refcount: 1}

$b = $a
    │
$a──┤
    ├──► zval {value: "salam", refcount: 2}
$b──┘

unset($a), unset($b)
    └──► refcount: 0 → FREE
```

**Kiçik dəyərlər (interned strings, integers) reference counting-siz:**

```php
$x = 42;    // IS_LONG — stack-ə bənzər, kopyalanır
$y = $x;    // Tam kopya, refcount yoxdur
```

---

## Copy-on-Write (COW)

PHP dəyişənləri yalnız **dəyişdirildikdə** kopyalanır:

*PHP dəyişənləri yalnız **dəyişdirildikdə** kopyalanır üçün kod nümunəsi:*
```php
$original = range(1, 10000);  // Böyük array, 1 yaddaş bloku

$copy = $original;            // Kopyalanmır! Eyni yaddaş blokuna işarə edir
                              // refcount = 2

$copy[] = 99999;              // İNDİ kopyalanır! (COW triggered)
                              // original refcount = 1
                              // copy → yeni yaddaş bloku
```

**Nümunə:**

```php
function processData(array $data): void
{
    // $data burada kopyalanmır (COW)
    // yalnız oxunursa, eyni yaddaşı göstərir
    
    foreach ($data as $item) { /* oxu */ }
    
    // Əgər dəyişdirsək, o zaman kopyalanır:
    // $data[] = 'yeni'; // ← bura kopyalanma baş verir
}

$largeArray = range(1, 100000);
processData($largeArray);  // COW sayəsində kopyalanmır
```

**Referans ilə COW-u məcbur etmək:**

```php
function addItem(array &$data): void  // referans — COW olmur
{
    $data[] = 'yeni';  // Orijinal dəyişir
}
```

---

## Garbage Collector

**Reference counting tək kifayət etmir:** Dairəvi referanslar problem yaradır.

***Reference counting tək kifayət etmir:** Dairəvi referanslar problem  üçün kod nümunəsi:*
```php
// Dairəvi referans — refcount heç 0-a düşmür!
$a = new stdClass();
$b = new stdClass();

$a->ref = $b;  // $b refcount: 2
$b->ref = $a;  // $a refcount: 2

unset($a);     // $a refcount: 1 (hələ $b->ref saxlayır)
unset($b);     // $b refcount: 1 (hələ $a->ref saxlayır)

// İkisi də yaddaşda qalır! Memory leak!
```

**PHP Cycle Collector (GC):**

```
Zend GC — tri-color marking algoritmi

1. Root buffer-ə potensial cycle-lar toplanır
2. Buffer 10,000-ə çatanda (və ya gc_collect_cycles() çağırılanda):
   a. "Purple" — köklər işarələnir
   b. "Grey" → "White" — scan edilir, refcount-lar simulyasiya ilə azaldılır
   c. Refcount 0 olanlar "white" = garbage
   d. White obyektlər azad edilir
```

*d. White obyektlər azad edilir üçün kod nümunəsi:*
```php
// GC idarəetməsi
gc_enable();          // GC aktiv et (default aktiv)
gc_disable();         // GC deaktiv et (performans testlərində)
gc_collect_cycles();  // Manual GC çağır
gc_enabled();         // GC aktiv?

// GC statistikası
$stats = gc_status();
echo $stats['runs'];         // GC neçə dəfə işlədib
echo $stats['collected'];    // Neçə cycle toplanıb
echo $stats['roots'];        // Root buffer ölçüsü
```

---

## memory_limit

*memory_limit üçün kod nümunəsi:*
```ini
; php.ini
memory_limit = 256M   ; Tövsiyə: production-da 128M-512M
memory_limit = -1     ; Limitsiz (production-da TƏHLÜKƏLİ!)
```

**Limit keçildikdə:**

```
PHP Fatal error: Allowed memory size of 268435456 bytes exhausted
(tried to allocate 20480 bytes) in /path/to/file.php on line 42
```

**Dinamik artırmaq (ehtiyac olarsa):**

```php
// Script-in xüsusi hissəsi üçün
ini_set('memory_limit', '1G');

// Laravel queue worker üçün
// config/queue.php → 'memory' => 128 (MB)
// php artisan queue:work --memory=256
```

---

## Yaddaş istifadəsini ölçmək

*Yaddaş istifadəsini ölçmək üçün kod nümunəsi:*
```php
// Cari yaddaş istifadəsi
echo memory_get_usage();        // bytes, PHP yaddaşı (zval-lar daxil)
echo memory_get_usage(true);    // bytes, OS-dən ayrılmış real yaddaş (daha böyük)

// Peak yaddaş
echo memory_get_peak_usage();        // Script-in ən yüksək yaddaş istifadəsi
echo memory_get_peak_usage(true);    // Real peak

// Human-readable format
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Memory profiling
$before = memory_get_usage();
$data = range(1, 100000);
$after = memory_get_usage();
echo "İstifadə: " . formatBytes($after - $before) . "\n";  // ~4MB
```

---

## Ümumi Memory Leak-lər

**1. Dairəvi Referanslar:**

```php
// ❌ Dairəvi referans
class Node
{
    public ?Node $next = null;
    public ?Node $prev = null;  // ← bu dairəvi referans yaradır
    public string $data;
}

$node1 = new Node();
$node2 = new Node();
$node1->next = $node2;
$node2->prev = $node1;  // Dairəvi!

// ✅ Həll: WeakReference istifadə et
class Node
{
    public ?Node $next = null;
    public ?\WeakReference $prev = null;  // Weak reference — refcount artırmır
    
    public function setPrev(Node $node): void
    {
        $this->prev = \WeakReference::create($node);
    }
    
    public function getPrev(): ?Node
    {
        return $this->prev?->get();
    }
}
```

**2. Event Listener-lərin Qalması:**

```php
// ❌ Yanlış: Listener əlavə edilir, silinmir
class OrderService
{
    public function __construct(private EventEmitter $emitter)
    {
        $this->emitter->on('order.created', function(Order $order) {
            // $this burada closure-da tutulur!
            $this->processOrder($order);
        });
        // Listener heç silinmir → $this yaddaşda qalır
    }
}

// ✅ Düzgün: Listener saxla, destructor-da sil
class OrderService
{
    private Closure $listener;
    
    public function __construct(private EventEmitter $emitter)
    {
        $this->listener = function(Order $order) {
            $this->processOrder($order);
        };
        $this->emitter->on('order.created', $this->listener);
    }
    
    public function __destruct()
    {
        $this->emitter->removeListener('order.created', $this->listener);
    }
}
```

**3. Static Property-lərdə Yığılan Data:**

```php
// ❌ Yanlış: Static array sonsuz böyüyür
class QueryLogger
{
    private static array $queries = [];
    
    public static function log(string $query): void
    {
        static::$queries[] = $query;  // Hər sorğu əlavə edilir, silinmir!
    }
}

// ✅ Düzgün: Ölçü limitini saxla
class QueryLogger
{
    private static array $queries = [];
    private static int $maxQueries = 1000;
    
    public static function log(string $query): void
    {
        static::$queries[] = $query;
        
        if (count(static::$queries) > static::$maxQueries) {
            array_shift(static::$queries);  // Köhnəni sil
        }
    }
    
    public static function clear(): void
    {
        static::$queries = [];
    }
}
```

**4. Böyük Kolleksiyalar Yaddaşda:**

```php
// ❌ Yanlış: 1M record yaddaşa yüklənir
$users = User::all();  // 1M × 500 bytes = 500MB!
foreach ($users as $user) {
    sendEmail($user);
}

// ✅ Düzgün: cursor() ilə lazy yüklənmə
User::cursor()->each(function (User $user) {
    sendEmail($user);
    // Hər döngüdən sonra model GC-ə buraxılır
});

// Və ya chunk:
User::chunk(500, function ($users) {
    foreach ($users as $user) {
        sendEmail($user);
    }
    // $users chunk bittikdən sonra GC-ə buraxılır
});
```

---

## Uzun Müddətli Proseslərdə Memory Leak

**Queue Worker, Laravel Octane, CLI script-lər:**

```php
// ❌ Octane/Worker-də problem: Her sorğu cache-i böyüdür
class ProductService
{
    private array $cache = [];  // Instance property, yaxşı
    // amma singleton kimi qeydiyyatdadırsa, cache sıfırlanmır!
}

// Laravel Octane ilə:
// app/Providers/AppServiceProvider.php
public function register(): void
{
    // ❌ Shared (singleton) — bütün sorğular arasında paylaşılır
    $this->app->singleton(ProductService::class);
    
    // ✅ Scoped — hər sorğu üçün yeni instance
    $this->app->scoped(ProductService::class);
}
```

**Queue Worker-də yaddaş izlənməsi:**

```php
// php artisan queue:work --memory=128 --max-jobs=1000
// Worker 128MB-dan çox istifadə etsə, özünü restart edir
// max-jobs: 1000 işdən sonra worker restart olur

// Custom check:
class ProcessOrder implements ShouldQueue
{
    public function handle(): void
    {
        // İşi gör
        $this->processOrderLogic();
        
        // Yaddaşı manual azalt
        gc_collect_cycles();
    }
}
```

**Leak-i aşkarlamaq:**

```php
// Script-in başında və sonunda yaddaşı müqayisə et
$startMemory = memory_get_usage(true);

// ... işlər ...

$endMemory = memory_get_usage(true);
$leak = $endMemory - $startMemory;

if ($leak > 1024 * 1024) {  // 1MB-dan çox
    Log::warning("Potensial memory leak: " . formatBytes($leak));
}
```

---

## Profiling Alətləri

**1. Xdebug Memory Profiling:**

```bash
# php.ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug

# Profil faylını WebGrind ilə aç
```

**2. Blackfire:**

```bash
blackfire run php script.php
# Web UI-da yaddaş istifadəsini görürsən
```

**3. PHP-MemInfo extension:**

```php
// Bütün canlı zval-ları siyahıla
meminfo_dump(fopen('/tmp/meminfo.json', 'w'));
// Sonra analiz üçün: https://github.com/BitOne/php-meminfo
```

**4. Sadə yaddaş izləmə:**

```php
class MemoryTracker
{
    private array $snapshots = [];
    
    public function snapshot(string $label): void
    {
        $this->snapshots[$label] = memory_get_usage(true);
    }
    
    public function report(): void
    {
        $previous = null;
        foreach ($this->snapshots as $label => $memory) {
            $diff = $previous !== null ? $memory - $previous : 0;
            echo sprintf(
                "%-30s %10s  %+10s\n",
                $label,
                formatBytes($memory),
                formatBytes($diff)
            );
            $previous = $memory;
        }
    }
}

$tracker = new MemoryTracker();
$tracker->snapshot('başlanğıc');
$data = loadData();
$tracker->snapshot('data yüklendi');
processData($data);
$tracker->snapshot('data işlendi');
unset($data);
$tracker->snapshot('data silindi');
$tracker->report();
```

---

## Praktiki Məsləhətlər

*Praktiki Məsləhətlər üçün kod nümunəsi:*
```php
// 1. Böyük dəyişənləri unset et
$largeData = loadMillionRows();
processData($largeData);
unset($largeData);  // ← Dərhal azad edilir (refcount 0-a düşürsə)
gc_collect_cycles();  // Cycle-ları manual toplat

// 2. Böyük array-ləri null et (unset-dən fərqli — dəyişən qalır amma boşalır)
$data = null;  // unset($data) ilə eyni effekt

// 3. Generator istifadə et
function processRows(): Generator {
    foreach (DB::table('orders')->cursor() as $row) {
        yield $row;
    }
}

// 4. WeakReference — güclü tutmadan referans saxlamaq
$cache = new WeakMap();  // PHP 8.0+ — obyektlər GC-ə mane olmadan saxlanır
$cache[$user] = expensiveComputation($user);
// $user başqa yerdən silinərsə, $cache[$user] da silinir

// 5. Object pool pattern — çox istifadə olunan obyektləri yenidən istifadə et
class ConnectionPool
{
    private array $available = [];
    
    public function acquire(): PDO
    {
        return array_pop($this->available) ?? $this->createNew();
    }
    
    public function release(PDO $conn): void
    {
        $this->available[] = $conn;  // Silmə, geri qoy
    }
}
```

---

## İntervyu Sualları

**1. PHP-də stack və heap nədir?**
Stack funksiya çağırış frames-lərini saxlayır. Heap isə PHP-nin dinamik yaddaş sahəsidir — bütün obyektlər, array-lər, string-lər heap-dədir. Zend Memory Manager heap-i idarə edir.

**2. Reference counting nədir?**
Hər zval neçə dəyişənin ona işarə etdiyini sayan `refcount` saxlayır. refcount 0-a düşəndə yaddaş dərhal azad edilir. Bu garbage collection-ın əsas mexanizmidir.

**3. Dairəvi referanslar niyə problem yaradır?**
İki obyekt bir-birinə işarə edərsə, unset edilsə belə refcount-ları 0-a düşmür. PHP-nin cycle collector-u bu cür "adacıqları" ayrıca aşkarlayıb azad edir. WeakReference istifadəsi həll yoludur.

**4. Copy-on-Write nədir?**
Array və ya string başqa dəyişənə mənimsədiləndə kopyalanmır, refcount artır. Yalnız dəyişdirildikdə real kopya yaranır. Bu böyük array-ləri funksiyalara göndərərkən yaddaşı qənaət edir.

**5. `memory_get_usage(true)` ilə `memory_get_usage()` fərqi nədir?**
`false` (default): PHP zend allocator-unun idarə etdiyi yaddaş. `true`: OS-dən ayrılmış real yaddaş (daha böyük, həmişə page-aligned). Peak üçün `memory_get_peak_usage()` istifadə edilir.

**6. Queue worker-də memory leak necə aşkarlanır?**
`--memory=128` ilə limit qoyulur, worker özünü restart edir. Uzun müddətdə yaddaş artımı izlənir. `gc_collect_cycles()` manual çağırılır. Static property-lər, singleton cache-lər yoxlanılır.

**7. `gc_collect_cycles()` nə vaxt manual çağırılmalıdır?**
Uzun müddətli script-lərdə, cycle yaradıldığı bilinən əməliyyatlardan sonra, yaddaş kritik olarsa. Amma PHP GC avtomatik işlədiyindən adətən lazım olmur.

**11. PHP-də interned strings nədir?**
PHP sabit string literal-larını (`"hello"`, `"status"`, funksiya adları, class adları) yalnız bir dəfə heap-də saxlayır — interned string pool-da. Bu string-lər refcount-suz işləyir, kopyalanmır, bütün request boyunca mövcuddur. `opcache` aktiv olduqda interned stringlər shared memory-dədir (bütün worker-lər arasında paylaşılır).

**12. PHP 8.0+ `WeakMap` ilə `WeakReference` fərqi nədir?**
`WeakReference::create($obj)` — yalnız bir obyektə zəif referans. `WeakMap` isə `SplObjectStorage` kimia davranır amma key-i zəif tutur: key olan obyekt başqa yerdən silinərsə, həmin giriş avtomatik `WeakMap`-dən çıxır. Cache-lər üçün idealdır — obyekt GC-ə düşəndə cache-dəki entry özü silinir.

**8. `unset()` dəyişkəni dərhal yaddaşdan azad edirmi?**
`unset()` refcount-u azaldır. refcount 0-a düşərsə, dərhal azad edilir. Amma obyektlər arasında cycle varsa, GC cycle collector-una qədər qalır. `gc_collect_cycles()` ilə məcbur edilə bilər.

**9. WeakReference nə üçün istifadə edilir?**
Obyektə refcount artırmadan işarə etmək üçün. Cache, observer pattern-lərində faydalı. `WeakReference::create($obj)` — obyekt başqa yerdən silinərsə, `get()` null qaytarır. PHP 8.0+ `WeakMap` da var.

**10. Laravel Octane-də ən böyük yaddaş riski nədir?**
Singleton-larda state yığılması. Hər sorğuda böyüyən static property-lər. Service Container-ə düzgün scope verilməmiş (singleton əvəzinə scoped istifadə etməmək). `octane:install` zamanı state reset listener-ləri əlavə edilməlidir.

---

## Zend Memory Manager (ZMM) Detalları

PHP heap-i birbaşa OS-dən idarə etmir. Zend Memory Manager arada durur:

```
PHP Request
    │
    ▼
Zend Memory Manager (ZMM)
    │  mmap()/malloc() ilə böyük bloklar alır
    ▼
OS (Linux/macOS)

ZMM öz daxilindəki pool-lardan kiçik bloklar verir:
  - Chunks: 2MB bloklar (OS-dən alınır)
  - Pages: 4KB hissələr (chunk daxilindədir)
  - Bins: Müxtəlif ölçülü small allocations (8B–3KB)

Request bitdikdə ZMM bütün chunk-ları azad edir (sürətli!)
Bu səbəbdən PHP per-request modelə uyğundur — request sonu yaddaş sıfırlanır.
```

**`memory_get_usage()` vs OS görünüşü:**
```
PHP memory_get_usage(true)  → ZMM-in OS-dən aldığı yer
PHP memory_get_usage(false) → Faktiki istifadə olunan yer
OS free/top                 → PHP prosesinin tam ölçüsü (ZMM + PHP binary + ext-lər)
```

---

## Anti-patternlər

**1. Uzun müddətli proseslərdə static property-lərdə data yığmaq**
Queue worker, Octane, Swoole kimi uzun ömürlü proseslərdə `static $cache = []` kimi yığıcı strukturlar saxlamaq — hər request-lə array böyüyür, heç vaxt azalmır, proses zamanla `memory_limit`-i keçir. Static property-ləri aydın TTL strategiyası ilə yönetmə ya da `WeakMap` istifadə et.

**2. Böyük array-ləri funksiyalara dəyərlə (by value) ötürmək**
`function process(array $data)` ilə megabayt-lıq array ötürməyi adi hesab etmək — Copy-on-Write mexanizmi dəyişiklik zamanı tam kopya yaradır, peak memory iki qat artır. `&$data` reference ilə ya da Collection/Generator kimi lazy strukturlar işlət.

**3. `unset()` etmədən böyük müvəqqəti dəyişənlər buraxmaq**
Import etdiyin 100MB CSV-ni `$rows` dəyişəninde saxlayıb scope bitənə qədər gözləmək — PHP GC scope bitənədə yaddaşı azaltmır, aşağı yaddaşlı serverlarda `memory_limit` exceed xətası baş verir. Böyük dəyişənləri işin bitdikdən dərhal sonra `unset()` et, generator ilə əvəzlə.

**4. Circular reference-lı obyektlərdə `unset()` kifayətdir güman etmək**
`$a->child = $b; $b->parent = $a; unset($a, $b)` — refcount 0-a düşmür, cycle collector işə düşənə qədər yaddaş azalmır. Cycle yaranan yerlər üçün `WeakReference` işlət, uzun müddətli proseslərdə `gc_collect_cycles()` manual çağır.

**5. `memory_get_usage()` ilə real OS yaddaş istifadəsini ölçmək**
`memory_get_usage(false)` dəyərinə əsaslanıb monitoring qurmaq — bu dəyər PHP allocator-unun idarə etdiyi yaddaşdır, OS-dən ayrılmış real yaddaşı göstərmir. `memory_get_usage(true)` ya da external tool (Blackfire) ilə ölç, peak üçün `memory_get_peak_usage(true)` istifadə et.

**6. Laravel Octane-də Request-scoped servisi Singleton kimi bind etmək**
`$this->app->singleton(UserContext::class, ...)` — sorğuya xas məlumatlar (auth user, request data) digər istifadəçilərin sorğularına sıza bilər. `$this->app->scoped()` istifadə et ki, servis hər sorğu üçün yenidən yaradılsın, Octane sorğu bitdikdə onu sıfırlasın.
