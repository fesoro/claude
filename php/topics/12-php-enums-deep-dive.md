# PHP Enums (Junior)

## Mündəricat
1. [Enum nədir?](#enum-nədir)
2. [Pure vs Backed Enum](#pure-vs-backed-enum)
3. [Enum methods & interfaces](#enum-methods--interfaces)
4. [Traits və const](#traits-və-const)
5. [Real-world patterns](#real-world-patterns)
6. [Laravel Eloquent ilə istifadə](#laravel-eloquent-ilə-istifadə)
7. [Serialization, JSON, validation](#serialization-json-validation)
8. [Migration: string const → enum](#migration-string-const--enum)
9. [Pitfalls](#pitfalls)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Enum nədir?

```
Enum — sabit (enumerated) qiymətlər toplusu.
PHP 8.1-də əlavə edildi. Əvvəl `const` + validation ilə simulasiya olunurdu.

Əvvəl (PHP 8.0):
  class Status
  {
      public const DRAFT     = 'draft';
      public const PUBLISHED = 'published';
      public const ARCHIVED  = 'archived';
  }
  
  // Problem: Status::DRAFT bir string-dir, type safety yoxdur
  function setStatus(string $status) { ... }
  setStatus('invalid');  // compile error YOX — runtime-da tutulur
  setStatus(Status::DRAFT);  // OK

PHP 8.1+:
  enum Status: string
  {
      case Draft     = 'draft';
      case Published = 'published';
      case Archived  = 'archived';
  }
  
  function setStatus(Status $status) { ... }
  setStatus(Status::Draft);  // OK
  setStatus('draft');  // TypeError! compile-ə yaxın səviyyədə tutulur
```

---

## Pure vs Backed Enum

```php
<?php
// PURE ENUM — qiymətə bağlanmayıb, yalnız case adı
enum Direction
{
    case North;
    case South;
    case East;
    case West;
}

$d = Direction::North;
echo $d->name;     // "North"
// $d->value       // ERROR — pure enum-da value yoxdur
```

```php
<?php
// BACKED ENUM — hər case bir skalar dəyərə bağlıdır (string və ya int)
enum Status: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';
}

$s = Status::Draft;
echo $s->name;     // "Draft"
echo $s->value;    // "draft"

// Dəyərdən enum-a çevirmə
$s = Status::from('draft');       // Status::Draft
$s = Status::from('invalid');     // ValueError throw edilir
$s = Status::tryFrom('invalid');  // null qaytarır — throw yoxdur

// Bütün case-lər
Status::cases();  // array: [Status::Draft, Status::Published, Status::Archived]
```

```php
<?php
// INT BACKED
enum HttpStatus: int
{
    case OK              = 200;
    case Created         = 201;
    case BadRequest      = 400;
    case Unauthorized    = 401;
    case InternalError   = 500;
}

HttpStatus::from(200);           // HttpStatus::OK
HttpStatus::tryFrom(999);        // null

// DB-dən gələn integer → enum
$status = HttpStatus::from((int) $row['status_code']);

// Pure vs Backed nə vaxt?
// Pure:    yalnız daxili istifadə, DB-də saxlanmır
// Backed:  DB, JSON, API serializable
```

---

## Enum methods & interfaces

```php
<?php
enum Priority: int
{
    case Low    = 1;
    case Medium = 2;
    case High   = 3;
    case Urgent = 4;
    
    // Method — her enum case özü $this kimi çıxış edir
    public function label(): string
    {
        return match($this) {
            Priority::Low    => 'Aşağı',
            Priority::Medium => 'Orta',
            Priority::High   => 'Yüksək',
            Priority::Urgent => 'Təcili',
        };
    }
    
    public function color(): string
    {
        return match($this) {
            Priority::Low    => 'gray',
            Priority::Medium => 'blue',
            Priority::High   => 'orange',
            Priority::Urgent => 'red',
        };
    }
    
    public function isUrgent(): bool
    {
        return $this === Priority::Urgent;
    }
    
    // Static factory
    public static function default(): self
    {
        return self::Medium;
    }
    
    // Bütün labels — dropdown üçün
    public static function options(): array
    {
        return array_map(
            fn($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}

// İstifadə
$p = Priority::High;
echo $p->label();    // "Yüksək"
echo $p->color();    // "orange"
var_dump($p->isUrgent());  // false

Priority::default();  // Priority::Medium
Priority::options();
// [
//   ['value' => 1, 'label' => 'Aşağı'],
//   ['value' => 2, 'label' => 'Orta'],
//   ...
// ]
```

```php
<?php
// Interface implementation
interface HasLabel
{
    public function label(): string;
}

interface HasIcon
{
    public function icon(): string;
}

enum OrderStatus: string implements HasLabel, HasIcon
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    
    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Gözləyir',
            self::Paid      => 'Ödənilib',
            self::Shipped   => 'Göndərilib',
            self::Delivered => 'Çatdırılıb',
            self::Cancelled => 'Ləğv edilib',
        };
    }
    
    public function icon(): string
    {
        return match($this) {
            self::Pending   => '⏳',
            self::Paid      => '💳',
            self::Shipped   => '📦',
            self::Delivered => '✅',
            self::Cancelled => '❌',
        };
    }
    
    // State machine — valid transitions
    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::Pending   => in_array($next, [self::Paid, self::Cancelled]),
            self::Paid      => in_array($next, [self::Shipped, self::Cancelled]),
            self::Shipped   => $next === self::Delivered,
            self::Delivered,
            self::Cancelled => false,  // final state
        };
    }
}

// Usage
$order = OrderStatus::Pending;
if ($order->canTransitionTo(OrderStatus::Paid)) {
    // DB update
}
```

---

## Traits və const

```php
<?php
// Trait istifadə etmək olar (method əlavə etmək üçün)
trait HasLabelTrait
{
    public function pretty(): string
    {
        return ucfirst(strtolower($this->name));
    }
}

enum Role: string
{
    use HasLabelTrait;
    
    case Admin = 'admin';
    case User  = 'user';
}

Role::Admin->pretty();  // "Admin"

// Const — enum daxilində
enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case AZN = 'AZN';
    
    const DEFAULT = self::USD;   // const içində enum
    const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'AZN' => '₼',
    ];
    
    public function symbol(): string
    {
        return self::SYMBOLS[$this->value];
    }
}

Currency::DEFAULT;          // Currency::USD
Currency::EUR->symbol();    // "€"
```

---

## Real-world patterns

```php
<?php
// PATTERN 1: Feature flags
enum Feature: string
{
    case NewCheckout  = 'new_checkout';
    case DarkMode     = 'dark_mode';
    case AiAssistant  = 'ai_assistant';
    
    public function isEnabled(): bool
    {
        return match($this) {
            self::NewCheckout => config('features.new_checkout', false),
            self::DarkMode    => auth()->user()?->settings['dark_mode'] ?? false,
            self::AiAssistant => auth()->user()?->hasSubscription('pro'),
        };
    }
}

if (Feature::AiAssistant->isEnabled()) {
    // render AI widget
}
```

```php
<?php
// PATTERN 2: Permission / Role hierarchy
enum Role: int
{
    case Guest     = 0;
    case User      = 10;
    case Editor    = 50;
    case Admin     = 90;
    case SuperUser = 100;
    
    public function has(self $required): bool
    {
        return $this->value >= $required->value;
    }
    
    public function can(string $action): bool
    {
        return match(true) {
            $action === 'view'          => $this->has(self::User),
            $action === 'edit'          => $this->has(self::Editor),
            $action === 'delete'        => $this->has(self::Admin),
            $action === 'manage_users'  => $this->has(self::SuperUser),
            default                     => false,
        };
    }
}

Role::Editor->can('edit');   // true
Role::Editor->can('delete'); // false
```

```php
<?php
// PATTERN 3: Error codes
enum ErrorCode: string
{
    case UserNotFound       = 'USER_NOT_FOUND';
    case InvalidCredentials = 'INVALID_CREDENTIALS';
    case PaymentFailed      = 'PAYMENT_FAILED';
    case RateLimitExceeded  = 'RATE_LIMIT_EXCEEDED';
    
    public function httpStatus(): int
    {
        return match($this) {
            self::UserNotFound       => 404,
            self::InvalidCredentials => 401,
            self::PaymentFailed      => 402,
            self::RateLimitExceeded  => 429,
        };
    }
    
    public function message(): string
    {
        return match($this) {
            self::UserNotFound       => 'İstifadəçi tapılmadı',
            self::InvalidCredentials => 'Yanlış istifadəçi adı/şifrə',
            self::PaymentFailed      => 'Ödəniş uğursuz oldu',
            self::RateLimitExceeded  => 'Çox sorğu. Bir az sonra yenidən cəhd edin',
        };
    }
}

// Exception istifadəsi
throw new DomainException(
    ErrorCode::UserNotFound->message(),
    ErrorCode::UserNotFound->httpStatus()
);
```

---

## Laravel Eloquent ilə istifadə

```php
<?php
// Laravel 9+: casts property-də native enum dəstəyi
namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $casts = [
        'status' => OrderStatus::class,  // DB string → enum auto
    ];
}

$order = Order::find(1);
$order->status;  // OrderStatus::Pending (enum, not string)

// Query — where clause-də enum string value qəbul edir
Order::where('status', OrderStatus::Paid)->get();
Order::where('status', OrderStatus::Paid->value)->get();  // eyni şey

// Create
$order = Order::create([
    'status' => OrderStatus::Pending,
]);

// Migration
Schema::create('orders', function (Blueprint $t) {
    $t->id();
    $t->string('status');  // enum string → DB-də varchar
    // və ya native DB enum:
    // $t->enum('status', array_column(OrderStatus::cases(), 'value'));
});

// Form Request validation
public function rules(): array
{
    return [
        'status' => ['required', Rule::enum(OrderStatus::class)],
    ];
}
```

---

## Serialization, JSON, validation

```php
<?php
// Backed enum JSON-a avtomatik serializable deyil — value-dan istifadə et
$status = Status::Draft;
json_encode($status);       // "draft" (PHP 8.1+ Backed avtomatik value-ə çevrilir)
json_encode($status->value); // "draft"  (explicit)

// Pure enum JSON-da problemlidir:
enum Direction
{
    case North;
    case South;
}

json_encode(Direction::North);  // error! Pure enum serializable deyil

// JsonSerializable implement et
enum Direction implements JsonSerializable
{
    case North;
    case South;
    
    public function jsonSerialize(): string
    {
        return $this->name;
    }
}
json_encode(Direction::North);  // "North"

// API request body-dən enum parse
$data = json_decode($request->getContent(), true);
$status = OrderStatus::tryFrom($data['status'] ?? '');
if ($status === null) {
    throw new ValidationException('Invalid status');
}
```

---

## Migration: string const → enum

```php
<?php
// ƏVVƏL (köhnə stil)
class Status
{
    public const DRAFT     = 'draft';
    public const PUBLISHED = 'published';
    
    public static function all(): array
    {
        return [self::DRAFT, self::PUBLISHED];
    }
    
    public static function valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }
}

// SONRA (PHP 8.1+)
enum Status: string
{
    case Draft     = 'draft';
    case Published = 'published';
}

// Köhnə kodu qorumaq üçün — backward compatibility
class LegacyStatus
{
    public const DRAFT = Status::Draft;  // enum const
    
    public static function all(): array
    {
        return array_map(fn($s) => $s->value, Status::cases());
    }
}

// Database-də köhnə string-lər qalır — value eyni olmalıdır
// enum case Draft → 'draft' (case name və value fərqli ola bilər!)
```

---

## Pitfalls

```
❌ Pure enum DB-də saxlanmasın — string cast problem olacaq
❌ Enum extend etmək OLMAZ — `enum Foo extends Bar` — syntax error
❌ Enum instance yeni yaratmaq OLMAZ — `new Status()` — fatal error
❌ Enum-da property OLMAZ — yalnız const, method, constant
❌ Enum case-lər immutable — dəyişdirilə bilməz
❌ `switch` ilə istifadə — match daha yaxşı (strict comparison)

✓ Pure enum — daxili state, DB-də olmayacaq
✓ Backed enum — DB, API, config-dən gələn dəyərlər üçün
✓ `from()` vs `tryFrom()` — tryFrom user input üçün (throw yoxdur)
✓ cases() ilə dropdown/validation list
✓ match expression — exhaustive check (compiler səhv verir əgər case qalırsa)
✓ Interface implement olunur — polymorphic davranış
✓ Trait istifadə olunur — method reuse
```

```php
<?php
// Match exhaustiveness — PHPStan səviyyə 5+ tutur
enum Color: string
{
    case Red   = 'red';
    case Green = 'green';
    case Blue  = 'blue';
}

function toHex(Color $c): string
{
    return match($c) {
        Color::Red   => '#f00',
        Color::Green => '#0f0',
        // Color::Blue → UnhandledMatchError runtime-da!
    };
}
// PHPStan xəbərdar edir: "Match arm is missing"
```

---

## İntervyu Sualları

- Pure enum ilə backed enum arasındakı fərq nədir?
- `from()` və `tryFrom()` arasındakı fərq nədir? Hansı nə vaxt istifadə olunur?
- Enum-da method və const ola bilərmi? Property ola bilərmi?
- Enum interface və trait istifadə edə bilərmi?
- Enum JSON-a necə serialize olunur?
- Pure enum DB-də niyə problemlidir?
- `match($this)` niyə switch-dən daha yaxşıdır enum-da?
- Laravel-də enum cast necə işləyir?
- State machine enum-da necə modelləşdirilir?
- PHP 8.0-da enum olmadığında necə simulyasiya olunurdu?
- Enum inheritance niyə yoxdur PHP-də?
- Enum cases() sırası necə təyin olunur və nə vaxt vacibdir?
