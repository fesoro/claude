# PHP Traits — Deep Dive

## Mündəricat
1. [Trait nədir?](#trait-nədir)
2. [Trait vs Interface vs Abstract class](#trait-vs-interface-vs-abstract-class)
3. [Conflict resolution (insteadof, as)](#conflict-resolution-insteadof-as)
4. [Abstract trait methods](#abstract-trait-methods)
5. [Static trait methods](#static-trait-methods)
6. [Trait composition (trait extends trait)](#trait-composition)
7. [Properties in traits](#properties-in-traits)
8. [Laravel-də trait istifadəsi](#laravel-də-trait-istifadəsi)
9. [When NOT to use traits](#when-not-to-use-traits)
10. [Real-world patterns](#real-world-patterns)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Trait nədir?

```
Trait — PHP 5.4+ feature, "horizontal code reuse" mexanizmi.
Class multiple inheritance OLMUR, amma trait MULTIPLE istifadə OLUNUR.

Niyə lazımdır?
  "Bir class həm Loggable, həm Cacheable, həm SoftDeletable olmalıdır."
  Inheritance tək-tərəfli (yalnız 1 extends).
  Interface kontraktdır (implementation yoxdur).
  Trait = kod paylaşımı.

Trait kompilyasiya zamanı class-a "copy-paste" edilir.
Yəni trait-dəki method class-ın öz method-u olur (runtime-da).
```

```php
<?php
trait Loggable
{
    public function log(string $message): void
    {
        echo "[" . static::class . "] $message\n";
    }
}

trait Cacheable
{
    public function cacheKey(): string
    {
        return static::class . ':' . $this->id;
    }
}

class User
{
    use Loggable, Cacheable;  // çoxlu trait
    
    public int $id = 1;
}

$u = new User();
$u->log('created');           // "[User] created"
echo $u->cacheKey();          // "User:1"
```

---

## Trait vs Interface vs Abstract class

```
                    | Interface  | Abstract class | Trait
────────────────────────────────────────────────────────────
Multiple inherit    | Yes (many) | No (1 extend)  | Yes (many)
Implementation      | No (contract) | Yes (partial) | Yes (full)
Properties          | No (const+)| Yes            | Yes
Abstract method     | Yes        | Yes            | Yes
Constructor         | No         | Yes            | No (PHP < 8.2 abstract) / PHP 8.2+: no
Constants           | Yes (PHP 8+) | Yes         | Yes (PHP 8.2+)
instanceof yoxlama  | Yes        | Yes            | NO (trait type deyil!)
Type hint           | Yes        | Yes            | No (use olunan class-lar arasında əlaqə yox)

Trait NE EDƏ BİLMƏZ:
  - instanceof Trait — syntax error
  - function foo(MyTrait $x) — syntax error
  - Trait-i birbaşa instantiate et — `new MyTrait()` — fatal error
  - Trait "contract" kimi istifadə et — interface istifadə et

Trait NƏ VAXT?
  "Eyni kod 3+ yerdə copy olur" → trait
  "Bu davranış kontraktdır" → interface
  "Common base + templating" → abstract class
```

---

## Conflict resolution (insteadof, as)

```php
<?php
trait Logger
{
    public function log(string $msg): void
    {
        echo "LOG: $msg\n";
    }
}

trait Monitor
{
    public function log(string $msg): void
    {
        echo "METRIC: $msg\n";
    }
}

// İki trait eyni method adı — CONFLICT
class Service
{
    use Logger, Monitor;   // Fatal error: method conflict
}

// HƏLL 1: insteadof — hansını istifadə et
class Service
{
    use Logger, Monitor {
        Logger::log insteadof Monitor;   // Logger-in log-unu istifadə et
    }
}

// HƏLL 2: as — alias yarat
class Service
{
    use Logger, Monitor {
        Logger::log insteadof Monitor;
        Monitor::log as metricLog;       // Monitor::log → metricLog() kimi çağır
    }
}

$s = new Service();
$s->log('action');        // "LOG: action"      (Logger)
$s->metricLog('action');  // "METRIC: action"   (Monitor, alias)
```

```php
<?php
// Visibility dəyişdirmək (as ilə)
trait Helper
{
    public function doStuff(): void { /* ... */ }
}

class Service
{
    use Helper {
        doStuff as protected;    // public → protected
        doStuff as private _doStuff;  // alias + private
    }
}

// İctimai doStuff-a kənardan çağırış rədd olunur:
$s = new Service();
// $s->doStuff();  // Error: visibility protected
```

---

## Abstract trait methods

```php
<?php
// Trait abstract method tələb edə bilər — "concrete class bunu implement etməlidir"
trait Cacheable
{
    abstract public function cacheKey(): string;
    
    public function remember(int $ttl): mixed
    {
        $key = $this->cacheKey();  // subclass təmin etməlidir
        return Cache::remember($key, $ttl, fn() => $this->load());
    }
    
    abstract protected function load(): mixed;
}

class Product
{
    use Cacheable;
    
    public int $id;
    
    public function cacheKey(): string
    {
        return "product:{$this->id}";
    }
    
    protected function load(): array
    {
        return DB::select('SELECT * FROM products WHERE id = ?', [$this->id]);
    }
}
```

---

## Static trait methods

```php
<?php
trait HasFactory
{
    public static function create(array $data): static
    {
        $instance = new static();
        foreach ($data as $key => $value) {
            $instance->$key = $value;
        }
        return $instance;
    }
}

class User
{
    use HasFactory;
    public string $name;
    public string $email;
}

$u = User::create(['name' => 'Ali', 'email' => 'a@b.com']);
// static::class → User (late static binding)
```

---

## Trait composition

```php
<?php
// Trait başqa trait istifadə edə bilər
trait Timestamps
{
    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;
    
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

trait SoftDelete
{
    use Timestamps;   // composition
    
    public ?\DateTime $deletedAt = null;
    
    public function delete(): void
    {
        $this->deletedAt = new \DateTime();
        $this->touch();  // Timestamps-dən
    }
    
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}

class Post
{
    use SoftDelete;   // Timestamps də avtomatik gəlir
}

$p = new Post();
$p->touch();                // Timestamps
$p->delete();               // SoftDelete
$p->isDeleted();            // true
```

---

## Properties in traits

```php
<?php
trait HasCounter
{
    private int $count = 0;    // property
    
    public function increment(): void { $this->count++; }
    public function getCount(): int   { return $this->count; }
}

class Visitor
{
    use HasCounter;
}

// Property conflict — trait və class eyni ada malik property
trait HasName
{
    public string $name = 'trait default';
}

class User
{
    use HasName;
    public string $name = 'class default';  // Fatal error PHP 7.4+
}
// Eyni tip + eyni default → compiler icazə verir
// Fərqli → fatal error
```

---

## Laravel-də trait istifadəsi

```php
<?php
// Laravel Model trait-lərdən geniş istifadə edir
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Model
{
    use HasFactory;      // Model::factory() 
    use SoftDeletes;     // deleted_at column
    use Notifiable;      // notify() method
    use HasApiTokens;    // personal access token
    
    protected $fillable = ['name', 'email'];
}

// Custom trait nümunəsi — Uuid key
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        // Laravel model lifecycle hook — "bootTraitName"
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
    
    public function getKeyType(): string    { return 'string'; }
    public function getIncrementing(): bool { return false; }
}

class Payment extends Model
{
    use HasUuid;
    // ID avtomatik UUID olacaq
}
```

---

## When NOT to use traits

```
❌ Trait-dən NƏ VAXT QAÇMAQ lazımdır?

1. Trait "contract" kimi istifadə:
   interface daha yaxşıdır (instanceof, type hint).
   
2. "Is-a" əlaqəsi (inheritance):
   Admin is a User → extends User, trait yox.

3. State-heavy behavior:
   Trait property-ləri "daxili state" saxlayır.
   Method chain subtle bug yaradır — kim nə dəyişdi?

4. Shared dependency (DI lazım):
   Trait constructor yoxdur. Injection çətinləşir.
   Composition (has-a) + DI istifadə et.

5. "Fat trait" — 10+ method:
   Trait bir behavior olmalıdır. Çox method → əslində class.

6. Ümumi method name conflict riski:
   `save()`, `update()`, `log()` kimi ada malik trait-lər
   birdən çox yerdə konflikt yaradır.

Tips:
  ✓ Trait 1 fokuslanmış behavior olmalıdır (max 2-3 method)
  ✓ Trait adlandır: "Has...", "Is...", "Can..." (Loggable, Cacheable, HasUuid)
  ✓ Trait-in öz daxili state-i olmamalıdır (property minimize et)
  ✓ Abstract method ilə contract təmin et
```

---

## Real-world patterns

```php
<?php
// PATTERN 1: Observer hook (Laravel boot)
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn($m) => AuditLog::log('created', $m));
        static::updated(fn($m) => AuditLog::log('updated', $m));
        static::deleted(fn($m) => AuditLog::log('deleted', $m));
    }
}

// PATTERN 2: Fluent API mixin
trait Filterable
{
    public function filter(array $filters): self
    {
        foreach ($filters as $field => $value) {
            $this->where($field, $value);
        }
        return $this;
    }
}

// PATTERN 3: Value object reuse
trait HasMoney
{
    public function total(): Money
    {
        return new Money($this->amount, $this->currency);
    }
}

// PATTERN 4: Test helper (PHPUnit)
trait RefreshDatabase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }
    
    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }
}

class UserServiceTest extends TestCase
{
    use RefreshDatabase;
    // hər test avtomatik transaction içində
}

// PATTERN 5: DTO → Array serialization
trait Arrayable
{
    public function toArray(): array
    {
        $reflection = new \ReflectionClass(static::class);
        $data = [];
        foreach ($reflection->getProperties() as $prop) {
            if ($prop->isPublic()) {
                $data[$prop->getName()] = $prop->getValue($this);
            }
        }
        return $data;
    }
}
```

---

## Trait diamond problem

```php
<?php
// "Diamond" — iki trait eyni 3-cü trait istifadə edir
trait A
{
    public function hello(): void { echo "A\n"; }
}

trait B { use A; }   // Diamond olmaq üçün 4-cü trait lazım
trait C { use A; }

class D
{
    use B, C;   // A iki dəfə gəlir (B-dən və C-dən)
}

$d = new D();
$d->hello();   // Conflict? 
// PHP-da YOX — PHP trait-i yalnız bir dəfə include edir
// (compile-time detection)

// AMMA əgər B A-nı override edibsə:
trait B
{
    use A;
    public function hello(): void { echo "B\n"; }
}
trait C { use A; }

class D { use B, C; }
// Conflict! insteadof lazım:
class D
{
    use B, C {
        B::hello insteadof C;
    }
}
```

---

## İntervyu Sualları

- Trait ilə abstract class arasındakı fərq nədir?
- `instanceof Trait` niyə işləmir?
- `insteadof` və `as` conflict resolution necə işləyir?
- Trait static method və property saxlaya bilərmi?
- Abstract method trait-də nə mənası var?
- Trait composition — trait başqa trait-dən necə istifadə edir?
- Laravel Eloquent-də `bootTraitName` niyə xüsusi işarələnir?
- Trait nə vaxt anti-pattern olur?
- Property conflict trait və class arasında nə vaxt baş verir?
- Diamond problem traits-də necə həll olunur?
- 4 trait istifadə edən class-da method conflict-lərini necə həll edərsiniz?
- Trait-də constructor niyə qəbul olunmur?
