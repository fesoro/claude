# Value Objects (Middle ⭐⭐)

## İcmal

Value Object — Domain-Driven Design-ın əsas konseptlərindən biridir. O, **identity-si olmayan**, yalnız **dəyərinə görə müəyyən olunan** immutable obyektdir. İki Value Object eyni dəyərlərə malikdirsə, onlar **bərabər** hesab olunur.

Real həyatdan misallar:
- **Pul (Money)**: 100 AZN = 100 AZN (hansı əskinasdan olması fərq etmir)
- **Rəng (Color)**: #FF0000 = #FF0000
- **Ünvan (Address)**: eyni küçə, şəhər, poçt kodu = eyni ünvan
- **Tarix aralığı (DateRange)**: 01.01 - 31.01 = 01.01 - 31.01
- **Email**: orxan@mail.com = orxan@mail.com
- **Koordinat**: (40.4093, 49.8671) = (40.4093, 49.8671)

## Niyə Vacibdir

1. **Primitive Obsession anti-pattern-dən qaçmaq** — email-i string kimi yox, Email obyekti kimi saxlamaq daha təhlükəsizdir
2. **Validation bir yerdə** — email validation hər yerdə deyil, yalnız Email class-ında
3. **Type safety** — compiler/IDE səhvləri tez tapır
4. **Self-documenting code** — `function send(Email $to)` vs `function send(string $to)`
5. **Business logic encapsulation** — pul hesablamaları Money class-ında

## Əsas Anlayışlar

**Value Object vs Entity:**

| Xüsusiyyət | Value Object | Entity |
|---|---|---|
| Identity | Yoxdur — yalnız dəyərinə görə | Var — unikal ID-si var |
| Equality | Dəyərə görə müqayisə | ID-yə görə müqayisə |
| Mutability | Immutable (dəyişməz) | Mutable (dəyişə bilər) |
| Lifecycle | Əlaqəli entity ilə birlikdə | Müstəqil lifecycle |
| Misal | Money, Email, Address | User, Order, Product |

**Immutability:** Dəyişiklik lazımdırsa yeni obyekt yaradılır, mövcud dəyişdirilmir.

**Equality by Value:** PHP-də `===` operator referansı müqayisə edir, buna görə xüsusi `equals()` metodu lazımdır.

**Self-validation:** VO constructor-da validate olunur — "Always Valid Object" prinsipi. Əgər yaranıbsa, düzgündür.

## Praktik Baxış

**Real istifadə:**
- `Money` — pul əməliyyatları, tax hesablaması, currency conversion
- `Email` — user registration, notification routing
- `Address` — shipping, billing, geolocation
- `DateRange` — subscription periods, booking availability
- `PhoneNumber` — SMS sending, contact validation

**Trade-off-lar:**
- Validation bir yerdə, type-safe, self-documenting — bunlar güclü tərəflər
- Lakin: hər domain konsepti üçün ayrı class — daha çox fayl; Eloquent cast yazmaq lazımdır; deserialization mürəkkəbləşir

**İstifadə etməmək:**
- Çox sadə `boolean` və ya `int` flag-lər üçün VO overkill-dir
- Yalnız bir sahəli, heç bir domain logic-i olmayan wrapper-lar bəzən lazımsızdır

**Common mistakes:**
- `float` ilə pul saxlamaq — floating point precision problemi; `0.1 + 0.2 !== 0.3`; integer (cent/qəpik) istifadə edin
- VO-nu mutable etmək — `$money->amount = 200` yazıb state dəyişdirmək; yeni VO qaytarın
- `===` ilə VO müqayisəsi — PHP referans müqayisəsi edir; `equals()` metodu yazın
- Validation-u VO-dan kənarda etmək — Controller-da email validate etmək VO-nun məqsədini pozur

**Anti-Pattern Nə Zaman Olur?**

- **Hər şeyi VO etmək** — `UserId(int)`, `UserName(string)`, `UserAge(int)` kimi trivial wrapper-lar yazmaq overkill-dir. Validation/business logic olmayan primitiv wrapper-lar dəyər yaratmır, yalnız kod həcmini artırır. VO yalnız domain anlayışı olduqda — validasiya, format, behavior ehtiyacı olduqda yaradın.
- **VO-nu mutable etmək** — `setter` metodu əlavə etmək, property-ni `public` buraxmaq; VO-nun immutability-si onun əsas xüsusiyyətidir. Dəyişiklik lazımdırsa `withX()` metodları — yeni instance qaytarır.
- **Validation-suz VO** — constructor-da yoxlama etməmək; VO yarandıqdan sonra invalid state-də ola bilər. "Always Valid" — əgər yaranıbsa, düzgündür.
- **VO-nu entity kimi persistence etmək** — ayrı cədvəldə `id` ilə saxlamaq. VO identity-sizdir; aggregate-in içinə embedded column-lar kimi saxlanılmalıdır (JSON column, separate columns).

## Nümunələr

### Ümumi Nümunə

```php
// Primitive Obsession - YANLIŞ
function createOrder(
    string $customerEmail,  // Düzgün email olduğunu kim yoxlayır?
    float $amount,          // Valyuta nədir? Mənfi ola bilərmi?
    string $currency,       // Düzgün valyuta kodudurmu?
    string $shippingStreet, // Ünvan parçalanıb
    string $shippingCity,
    string $shippingZip,
) { /* ... */ }

// Value Objects ilə - DOĞRU
function createOrder(
    Email $customerEmail,       // Artıq validate olunub
    Money $amount,              // Məbləğ + valyuta bir yerdə
    Address $shippingAddress,   // Ünvan bir obyekt
) { /* ... */ }
```

### PHP/Laravel Nümunəsi

**Email Value Object:**

```php
readonly class Email
{
    public readonly string $value;

    public function __construct(string $email)
    {
        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Yanlış email format: {$email}");
        }

        $this->value = $email;
    }

    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    public function isCorporate(): bool
    {
        $freeProviders = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        return !in_array($this->getDomain(), $freeProviders);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string { return $this->value; }
}
```

**Money Value Object (integer amount):**

```php
readonly class Money
{
    /**
     * @param int $amount Ən kiçik vahiddə (qəpik/cent) — float deyil!
     */
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {}

    public static function fromFloat(float $value, Currency $currency): self
    {
        $multiplier = 10 ** $currency->decimals();
        return new self((int) round($value * $multiplier), $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    // Riyazi əməliyyatlar — hamısı yeni Money qaytarır (immutable)
    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->ensureSameCurrency($other);
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException('Nəticə mənfi ola bilməz.');
        }
        return new self($this->amount - $other->amount, $this->currency);
    }

    public function percentage(float $percent): self
    {
        return new self((int) round($this->amount * $percent / 100), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    public function format(): string
    {
        return number_format(
            $this->amount / (10 ** $this->currency->decimals()),
            $this->currency->decimals(),
            '.',
            ',',
        ) . ' ' . $this->currency->symbol();
    }

    private function ensureSameCurrency(self $other): void
    {
        if (!$this->currency->equals($other->currency)) {
            throw new InvalidArgumentException(
                "Valyutalar uyğun deyil: {$this->currency} vs {$other->currency}"
            );
        }
    }

    public function __toString(): string { return $this->format(); }
}
```

**DateRange — immutable with wither methods:**

```php
readonly class DateRange
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($start > $end) {
            throw new InvalidArgumentException(
                'Başlanğıc tarixi bitiş tarixindən böyük ola bilməz.'
            );
        }
    }

    // Yeni obyekt qaytarır — original dəyişmir
    public function withStart(DateTimeImmutable $start): self
    {
        return new self($start, $this->end);
    }

    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    public function overlaps(self $other): bool
    {
        return $this->start <= $other->end && $this->end >= $other->start;
    }

    public function lengthInDays(): int
    {
        return (int) $this->start->diff($this->end)->days;
    }

    public function equals(self $other): bool
    {
        return $this->start == $other->start && $this->end == $other->end;
    }
}
```

**Eloquent Custom Cast:**

```php
// app/Casts/MoneyCast.php
class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $currencyField = 'currency',
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) return null;
        $currencyCode = $attributes[$this->currencyField] ?? 'AZN';
        return new Money((int) $value, new Currency($currencyCode));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (!$value instanceof Money) {
            throw new InvalidArgumentException('Dəyər Money tipində olmalıdır.');
        }
        return [
            $key => $value->amount,
            $this->currencyField => $value->currency->code,
        ];
    }
}

// Model-də
class Order extends Model
{
    protected $casts = [
        'total'            => MoneyCast::class . ':currency',
        'shipping_address' => AddressCast::class,
    ];
}

// İstifadə
$order = Order::find(1);
echo $order->total->format();        // "149.99 ₼"
echo $order->total->currency->name(); // "Azərbaycan Manatı"
```

**Unit Test:**

```php
class MoneyTest extends TestCase
{
    public function test_operations_do_not_mutate_original(): void
    {
        $original = Money::fromFloat(100.00, Currency::AZN());
        $original->add(Money::fromFloat(50.00, Currency::AZN()));

        $this->assertEquals(10000, $original->amount); // Original dəyişməyib!
    }

    public function test_cannot_add_different_currencies(): void
    {
        $azn = Money::fromFloat(10.00, Currency::AZN());
        $usd = Money::fromFloat(10.00, Currency::USD());

        $this->expectException(InvalidArgumentException::class);
        $azn->add($usd);
    }

    public function test_equal_money_objects_are_equal(): void
    {
        $a = Money::fromFloat(49.99, Currency::AZN());
        $b = Money::fromFloat(49.99, Currency::AZN());

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a === $b); // PHP reference — false!
    }
}
```

## Praktik Tapşırıqlar

1. **Primitive Obsession audit** — mövcud layihənizdə `string $email`, `float $price`, `string $phone` parametrlərini tapın; hər biri üçün VO yaradın; custom cast yazın.
2. **Money VO tam implementasiya** — `Money` class-ı yaradın: `fromFloat()`, `add()`, `subtract()`, `percentage()`, `format()`; float istifadə etməyin (integer cents); tam unit test suite yazın.
3. **DateRange VO** — `contains()`, `overlaps()`, `lengthInDays()` metodları; validator `start < end`; subscription availability üçün istifadə edin.
4. **Eloquent Cast chain** — `Money`, `Email`, `Address` üçün cast-lər yazın; Eloquent model-ə inteqrasiya edin; CRUD test edin.

## Əlaqəli Mövzular

- [DDD Overview](01-ddd.md) — VO-nun DDD-dəki rolu
- [Aggregates](04-ddd-aggregates.md) — VO-lar aggregate içindədir
- [DDD Patterns](03-ddd-patterns.md) — tactical pattern-lərin tam seti
- [DTO](../general/01-dto.md) — VO vs DTO fərqi
- [Code Smells](../general/02-code-smells-refactoring.md) — Primitive Obsession anti-pattern
