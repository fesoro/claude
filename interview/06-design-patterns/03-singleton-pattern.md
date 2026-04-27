# Singleton Pattern (and its problems) (Middle ⭐⭐)

## İcmal
Singleton — bir class-ın yalnız bir instance-ının mövcud olmasını zəmanət edən creational pattern-dir. Database connection pool, configuration manager, logger — bunlar Singleton-un klassik istifadə nümunələridir. Lakin Singleton eyni zamanda ən çox tənqid edilən design pattern-lərdən biridir — global state, hidden dependency, test çətinliyi kimi ciddi problemlər gətirir. Interview-larda bu mövzu həm pattern-i bilmənizi, həm də onun anti-pattern tərəflərini başa düşdüyünüzü yoxlayır.

## Niyə Vacibdir
"Singleton-un problemi" sualı interview-larda çox verilir. Düzgün cavab sizin test-driven development düşüncənizi, global state haqqında mövqeyinizi, DI Container-in Singleton-u necə daha yaxşı həll etdiyini bilməyinizi göstərir. PHP-nin request lifecycle-ında Singleton-un davranışı Java-dan fərqlidir — bu fərqi bilmək Senior PHP developer üçün vacibdir.

---

## Əsas Anlayışlar

- **Classic Singleton:** Private constructor, static `getInstance()` method, static `$instance` property
- **Thread Safety:** Java/Go-da çox thread-li mühitdə lazy initialization race condition — double-checked locking + `volatile`, ya `sync.Once` (Go)
- **PHP Singleton xüsusiyyəti:** Her HTTP request yeni process başladır — Singleton yalnız o request-in ərzində yaşayır (FPM)
- **PHP Octane/Swoole/RoadRunner:** Process request-lər arasında yaşayır → Singleton state request-lərdən sıza bilər → kritik bug
- **Clone qadağası:** `__clone()` private — `clone $singleton` mümkün olmasın
- **Serialize qadağası:** `__wakeup()` exception — `unserialize()` ilə yeni instance yaranmasın
- **Hidden Dependency:** `Database::getInstance()` daxili çağırış — class-ın signature-ında görünmür; refactoring çətin
- **Test Çətinliyi:** Static call mock edilə bilmir; Test A singleton-u dəyişdirir, Test B bu state-i görür — test isolation pozulur
- **Tight Coupling:** `::getInstance()` konkret implementasiya-ya lock edir; interface ilə əvəz etmək mümkün deyil
- **Global State Problem:** Singleton global mutable state-dir; "action at a distance" bug-ları baş verə bilər
- **DI Container + Singleton Scope:** `app()->singleton()` — eyni effect, amma explicit dependency, injectable, mockable
- **Multiton Pattern:** Singleton-un genişlənməsi — named instance-lar: `ConnectionManager::get('read')`, `get('write')`
- **Monostate Pattern:** Singleton alternativ — bütün instance-lar eyni static state paylaşır; public constructor
- **Registry Pattern:** Named singleton-lar — `Registry::set('db', $db)`, `Registry::get('db')`; global amma centralized
- **Lazy vs Eager Initialization:** Lazy: ilk `getInstance()` çağırışında yaradılır; Eager: class yükləndikdə yaradılır (thread-safe, amma always created)
- **Initialization-on-Demand Holder:** Java-nın ən elegant thread-safe lazy singleton-u — inner static class
- **Octane Memory Leak:** Static property-yə request-specific data yazıldıqda növbəti request-ə sıza bilər

---

## Praktik Baxış

**Interview-da yanaşma:**
- Singleton-u izah edib dərhal problemlərini qeyd edin — "bu pattern-i heç istifadə etmərəm" əvəzinə "DI Container-də singleton scope daha yaxşıdır" deyin
- PHP lifecycle fərqini qeyd edin — Octane/Swoole risk-i
- "Singleton-un müasir alternativi nədir?" sualı çox gəlir — DI Container singleton scope

**Follow-up suallar:**
1. "Laravel-də Singleton-u harada görürsünüz?" — `App::singleton()`, `ServiceProvider`-larda; `app()->singleton(Database::class, ...)`
2. "Singleton-un thread-safe implementasiyası?" — PHP single-threaded-dir (Octane istisna); Java-da double-checked locking + volatile; Go-da `sync.Once`
3. "Service Locator ilə Singleton arasındakı fərq?" — Service Locator hidden dependency-lər saxlayır amma inject edilə bilər; Singleton hər ikisini edir
4. "Singleton-u nə zaman istifadə etmək məqbuldur?" — Side-effect-siz, stateless utility-lər; read-only config; logger (state yox, I/O var)
5. "Octane-da Singleton risk-ini necə azaldırsınız?" — `octane.flush` array-nə əlavə etmək; scoped binding; `$app->scoped()` istifadə etmək
6. "`new static()` vs `new self()` fərqi Singleton-da?" — `new self()` həmişə defining class-ı yaradır; `new static()` late static binding ilə child class-ı yaradır — Singleton-u extend etdikdə fərqlənir

**Code review red flags:**
- `static::$instance` property-yə request-specific data yazılması (Octane-da)
- Test class-larında `static::$instance = null` reset — design problemi olduğunu göstərir
- `Singleton::getInstance()` daxili, konstruktorda deyil — hidden dependency
- `clone $obj` → exception — test isolation üçün clone lazım ola bilər

**Production debugging ssenariləri:**
- Octane-da user A-nın data-sı user B-nin response-unda görünür — static property request leak
- Singleton-u test etmək: test-lər ardıcıl işlədikdə birinci test ikincisini etkiləyir — test isolation yoxdur
- Memory leak: Singleton böyük object-ləri saxlayır, Laravel Octane-da process restart olmadan birikirlər
- Logger-in Singleton-da saxlanması OK, amma Logger-in user context-ini static-də saxlaması YANLIŞ

---

## Nümunələr

### Tipik Interview Sualı
"Explain the Singleton pattern, then tell me why some people consider it an anti-pattern. How do you handle situations where you need a single shared instance?"

### Güclü Cavab
Singleton yalnız bir instance olmasını zəmanət edir — klassik use-case: database connection, config, logger.

Problem 1 — test çətinliyi: `Database::getInstance()` çağıranda həmin instance-a test-də başqa "fake" database inject etmək mümkün deyil. Test izolasiyası pozulur.

Problem 2 — hidden dependency: Function signature-a baxanda hansı dependency-lərin istifadə olunduğu görünmür; refactoring-də nəyi etkilədiyini anlamaq çətin olur.

Problem 3 — PHP Octane/Swoole: Normal request lifecycle-ında hər request prosesi sıfırlanır — Singleton "yalnız həmin request üçün" yaşayır. Lakin Octane/Swoole ilə process request-lər arasında paylaşılır — static state request-lərdən sızmaq riski yaranır.

Müasir həll: DI Container-da singleton scope. Laravel-də `app()->singleton(Database::class, ...)`. Eyni nəticə: bir instance. Lakin explicit dependency, injectable, test-də mock oluna bilər.

### Kod Nümunəsi

```php
// ── Classic Singleton (problematik) ─────────────────────────────
class DatabaseConnection
{
    private static ?self $instance = null;
    private \PDO $pdo;

    private function __construct()
    {
        $this->pdo = new \PDO(
            'mysql:host=localhost;dbname=mydb',
            'root',
            'secret',
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    // Clone mümkün deyil
    private function __clone() {}

    // Serialize ilə yeni instance yaranmasın
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton.');
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

// İstifadə — hidden dependency (konstruktorda görünmür)
class UserRepository
{
    public function find(int $id): array
    {
        // ↓ Bu dependency function signature-ında görünmür
        return DatabaseConnection::getInstance()
            ->query('SELECT * FROM users WHERE id = ?', [$id]);
    }
}

// Test etmək çətindir:
class UserRepositoryTest extends TestCase
{
    public function test_find_user(): void
    {
        // DatabaseConnection mock etmək mümkün deyil!
        // Həqiqi DB connection lazımdır — slow, fragile test
        $repo = new UserRepository();
        $user = $repo->find(1); // Real DB query
    }
}
```

```php
// ── DI Container ilə daha yaxşı yanaşma ─────────────────────────
// AppServiceProvider-da:
public function register(): void
{
    $this->app->singleton(DatabaseConnection::class, function ($app) {
        return new DatabaseConnection(
            $app['config']['database.connections.mysql']
        );
    });
}

// Repository — explicit, injectable, mockable
class UserRepository
{
    public function __construct(
        private readonly DatabaseConnection $db // Açıq dependency
    ) {}

    public function find(int $id): ?array
    {
        return $this->db->query(
            'SELECT * FROM users WHERE id = ?', [$id]
        )[0] ?? null;
    }
}

// Test — mock inject etmək asandır
class UserRepositoryTest extends TestCase
{
    public function test_find_user(): void
    {
        $mockDb = $this->createMock(DatabaseConnection::class);
        $mockDb->expects($this->once())
            ->method('query')
            ->willReturn([['id' => 1, 'name' => 'Alice']]);

        $repo = new UserRepository($mockDb);
        $user = $repo->find(1);

        $this->assertEquals('Alice', $user['name']);
    }
}
```

```php
// ── Octane-da Singleton risk və həlli ───────────────────────────

// ❌ YANLIŞ: Static property-yə request-specific data
class RequestContext
{
    private static ?User $currentUser = null; // OCTANE-DA LEAK!

    public static function setUser(User $user): void
    {
        self::$currentUser = $user; // Request 1: Alice, Request 2: hələ Alice!
    }

    public static function getUser(): ?User
    {
        return self::$currentUser;
    }
}

// ✅ DÜZGÜN 1: Scoped binding — hər request yeni instance
// AppServiceProvider-da:
$this->app->scoped(RequestContext::class, fn() => new RequestContext());
// 'scoped' = request bitdikdə sıfırlanır

// ✅ DÜZGÜN 2: Request object-ə bind et
class RequestContextService
{
    private array $data = [];

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}

// Octane-da flush konfiqurasiyası (config/octane.php):
// 'flush' => [
//     RequestContextService::class, // Hər request-dən sonra reset
// ],

// ✅ DÜZGÜN 3: Middleware-də request scope
class AuthenticateUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // User-i request-ə attach et — static deyil
        $request->merge(['_current_user' => auth()->user()]);
        return $next($request);
    }
}
```

```java
// ── Java: Thread-safe Singleton variantları ───────────────────────

// 1) synchronized — sadə, lakin hər call lock alır
class SynchronizedSingleton {
    private static SynchronizedSingleton instance;
    private SynchronizedSingleton() {}

    public static synchronized SynchronizedSingleton getInstance() {
        if (instance == null) {
            instance = new SynchronizedSingleton();
        }
        return instance;
    }
}

// 2) Double-checked locking + volatile (Java 5+) — performanslı
class DoubleCheckedSingleton {
    private static volatile DoubleCheckedSingleton instance; // volatile: visibility

    private DoubleCheckedSingleton() {}

    public static DoubleCheckedSingleton getInstance() {
        if (instance == null) {              // Birinci check: lock almadan (fast path)
            synchronized (DoubleCheckedSingleton.class) {
                if (instance == null) {      // İkinci check: lock içərisində
                    instance = new DoubleCheckedSingleton();
                }
            }
        }
        return instance;
    }
}

// 3) Initialization-on-Demand Holder (ən elegant, thread-safe, lazy)
class HolderSingleton {
    private HolderSingleton() {}

    // Inner static class yalnız `getInstance()` çağırıldıqda yüklənir
    // JVM class loading thread-safe-dir — lock olmadan
    private static class Holder {
        static final HolderSingleton INSTANCE = new HolderSingleton();
    }

    public static HolderSingleton getInstance() {
        return Holder.INSTANCE; // Həmişə thread-safe, lock yoxdur
    }
}

// 4) Enum Singleton (Joshua Bloch tövsiyyəsi)
enum EnumSingleton {
    INSTANCE;

    private int connectionCount = 0;

    public void connect() { connectionCount++; }
    public int getCount() { return connectionCount; }
}
// İstifadə: EnumSingleton.INSTANCE.connect()
// Serialize + reflection qarşısında da qorunur
```

```go
// ── Go: sync.Once ilə thread-safe Singleton ─────────────────────
package main

import (
    "database/sql"
    "sync"
    _ "github.com/go-sql-driver/mysql"
)

type Database struct {
    db *sql.DB
}

var (
    dbInstance *Database
    once       sync.Once
)

// sync.Once — bütün goroutine-lər üçün bir dəfə icra olunur
func GetDatabase() *Database {
    once.Do(func() {
        db, err := sql.Open("mysql", "user:pass@/dbname")
        if err != nil {
            panic(err)
        }
        db.SetMaxOpenConns(25)
        db.SetMaxIdleConns(5)
        dbInstance = &Database{db: db}
    })
    return dbInstance
}

func (d *Database) Query(query string, args ...any) (*sql.Rows, error) {
    return d.db.Query(query, args...)
}

// DI Container ilə daha yaxşı:
// var db *Database // wire.go-da inject edilir
// Test-də: mockDatabase inject etmək olar
```

### Yanlış Kod + Düzgün Kod

```php
// ❌ YANLIŞ: Test-i çətinləşdirən Singleton
class Config
{
    private static ?self $instance = null;
    private array $data = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->data = require __DIR__ . '/../config/app.php';
        }
        return self::$instance;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}

// Test-də:
// Config::getInstance()->get('api_key') — həmişə production config-i qaytarır
// Test config inject etmək mümkün deyil

// ✅ DÜZGÜN: Constructor injection
class Config
{
    public function __construct(private readonly array $data) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}

// ServiceProvider: singleton scope
$this->app->singleton(Config::class, fn() => new Config(require base_path('config/app.php')));

// Test:
$testConfig = new Config(['api_key' => 'test-key-123']);
$service    = new ApiService($testConfig); // Asanlıqla inject
```

---

## Praktik Tapşırıqlar

1. Laravel-də `App::singleton()` ilə register edilmiş service-ləri tapın — `app()->make()` ilə eyni instance qaytardığını yoxlayın
2. Singleton olan bir class-ı DI-yə refactor edin: constructor injection + ServiceProvider; test yazın
3. Octane-da Singleton leak-ini simulate edin: static property-yə request data yazıb ikinci request-də görünüb-görünmədiyini yoxlayın
4. Java-da 3 thread-safe Singleton variant-ı implement edin: synchronized, double-checked, holder; benchmark edin
5. Go-da `sync.Once` ilə database connection singleton implement edin; race detector ilə test edin
6. `static::getInstance()` vs DI Container singleton scope fərqini test yazaraq göstərin: mock inject testability
7. Multiton pattern implement edin: `DatabaseManager::connection('read')`, `connection('write')` — iki ayrı instance
8. Singleton-un `__clone()` qadağasını aradan qaldırın (reflection ilə mümkündür) — niyə bu problem olduğunu izah edin

## Əlaqəli Mövzular
- [Dependency Injection](11-dependency-injection.md) — DI Singleton-un daha yaxşı alternativi
- [Factory Patterns](02-factory-patterns.md) — Factory Singleton-u yaradıb idarə edə bilər
- [SOLID Principles](01-solid-principles.md) — Singleton DIP-i pozur
- [Service Locator anti-pattern](11-dependency-injection.md) — Singleton-a bənzər problem
