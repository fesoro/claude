# Value Objects (Middle)

## Mündəricat
1. [Value Object nədir?](#value-object-nədir)
2. [Value Object vs Entity](#value-object-vs-entity)
3. [Immutability konsepti](#immutability-konsepti)
4. [Equality by Value](#equality-by-value)
5. [Laravel-də Value Object nümunələri](#laraveldə-value-object-nümunələri)
6. [Value Object Validation](#value-object-validation)
7. [Eloquent ilə Integration (Custom Casts)](#eloquent-ilə-integration)
8. [Value Object-lərin Test Edilməsi](#value-object-lərin-test-edilməsi)
9. [Real-World Use Cases](#real-world-use-cases)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Value Object nədir?

Value Object — Domain-Driven Design-ın əsas konseptlərindən biridir. O, **identity-si olmayan**, yalnız **dəyərinə görə müəyyən olunan** obyektdir. İki Value Object eyni dəyərlərə malikdirsə, onlar **bərabər** hesab olunur.

Real həyatdan misallar:
- **Pul (Money)**: 100 AZN = 100 AZN (hansı əskinasdan olması fərq etmir)
- **Rəng (Color)**: #FF0000 = #FF0000
- **Ünvan (Address)**: eyni küçə, şəhər, poçt kodu = eyni ünvan
- **Tarix aralığı (DateRange)**: 01.01 - 31.01 = 01.01 - 31.01
- **Email**: orxan@mail.com = orxan@mail.com
- **Koordinat**: (40.4093, 49.8671) = (40.4093, 49.8671)

**Niyə lazımdır?**
1. **Primitive Obsession anti-pattern-dən qaçmaq** — email-i string kimi yox, Email obyekti kimi saxlamaq daha təhlükəsizdir
2. **Validation bir yerdə** — email validation hər yerdə deyil, yalnız Email class-ında
3. **Type safety** — compiler/IDE səhvləri tez tapır
4. **Self-documenting code** — `function send(Email $to)` vs `function send(string $to)`
5. **Business logic encapsulation** — pul hesablamaları Money class-ında

*5. **Business logic encapsulation** — pul hesablamaları Money class-ın üçün kod nümunəsi:*
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

---

## Value Object vs Entity

| Xüsusiyyət | Value Object | Entity |
|---|---|---|
| Identity | Yoxdur - yalnız dəyərinə görə | Var - unikal ID-si var |
| Equality | Dəyərə görə müqayisə | ID-yə görə müqayisə |
| Mutability | Immutable (dəyişməz) | Mutable (dəyişə bilər) |
| Lifecycle | Əlaqəli entity ilə birlikdə | Müstəqil lifecycle |
| Misal | Money, Email, Address | User, Order, Product |

*həll yanaşmasını üçün kod nümunəsi:*
```php
// Entity - Identity-yə görə müqayisə olunur
class User
{
    public function __construct(
        private readonly int $id,    // Identity!
        private string $name,        // Dəyişə bilər
        private string $email,       // Dəyişə bilər
    ) {}

    public function equals(self $other): bool
    {
        // İki user eyni ID-yə malikdirsə - eyni user-dir
        return $this->id === $other->id;
    }

    // Mutable - dəyişə bilər
    public function changeName(string $name): void
    {
        $this->name = $name;
    }
}

$user1 = new User(1, 'Orxan', 'orxan@mail.com');
$user2 = new User(1, 'Orxan Məmmədov', 'orxan@mail.com');
$user1->equals($user2); // true - eyni ID, eyni user

// Value Object - Dəyərə görə müqayisə olunur
class Money
{
    public function __construct(
        private readonly int $amount,       // cent/qəpik
        private readonly Currency $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Məbləğ mənfi ola bilməz.');
        }
    }

    public function equals(self $other): bool
    {
        // ID yoxdur - dəyərlər müqayisə olunur
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    // Immutable - yeni obyekt qaytarır
    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }
}

$money1 = new Money(1000, Currency::AZN());
$money2 = new Money(1000, Currency::AZN());
$money1->equals($money2); // true - eyni dəyər
```

---

## Immutability konsepti

Immutability — obyektin yaradıldıqdan sonra **dəyişdirilə bilməməsi** prinsipidir. Value Object-lər **həmişə immutable** olmalıdır. Dəyişiklik lazımdırsa, **yeni obyekt** yaradılır.

**Niyə immutability vacibdir?**
1. **Thread safety** — paylaşılan state yoxdur
2. **Predictability** — obyekt yaradıldıqdan sonra dəyişmir, gözlənilməz davranış yoxdur
3. **Debugging asanlığı** — state dəyişmir, bug tapmaq asandır
4. **Caching** — immutable obyektlər təhlükəsiz şəkildə cache oluna bilər
5. **Side effect yoxdur** — bir method çağırışı digər yerdə state dəyişdirmir

*5. **Side effect yoxdur** — bir method çağırışı digər yerdə state dəyi üçün kod nümunəsi:*
```php
// YANLIŞ - Mutable Value Object
class DateRange
{
    public function __construct(
        private DateTimeImmutable $start,
        private DateTimeImmutable $end,
    ) {}

    // Mutable method - obyekti dəyişdirir!
    public function setStart(DateTimeImmutable $start): void
    {
        $this->start = $start; // Xətərli! Digər yerdə istifadə olunursa problem yaradır
    }
}

$range = new DateRange(
    new DateTimeImmutable('2024-01-01'),
    new DateTimeImmutable('2024-12-31'),
);

// Bu kodu başqa developer yazır, $range-in dəyişdiyindən xəbəri olmur
$range->setStart(new DateTimeImmutable('2025-01-01')); // Side effect!

// DOĞRU - Immutable Value Object
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

    // Yeni obyekt qaytarır - original dəyişmir
    public function withStart(DateTimeImmutable $start): self
    {
        return new self($start, $this->end);
    }

    public function withEnd(DateTimeImmutable $end): self
    {
        return new self($this->start, $end);
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

    public function __toString(): string
    {
        return $this->start->format('d.m.Y') . ' - ' . $this->end->format('d.m.Y');
    }
}

// İstifadə
$q1 = new DateRange(
    new DateTimeImmutable('2024-01-01'),
    new DateTimeImmutable('2024-03-31'),
);

// Original dəyişmir, yeni obyekt yaranır
$q2 = $q1->withStart(new DateTimeImmutable('2024-04-01'))
         ->withEnd(new DateTimeImmutable('2024-06-30'));

echo $q1; // 01.01.2024 - 31.03.2024 (dəyişməyib!)
echo $q2; // 01.04.2024 - 30.06.2024 (yeni obyekt)
```

---

## Equality by Value

Value Object-lər **reference (istinad) ilə deyil, dəyər ilə müqayisə** olunur. PHP-də `===` operator referansı müqayisə edir, buna görə xüsusi `equals()` metodu lazımdır.

*Value Object-lər **reference (istinad) ilə deyil, dəyər ilə müqayisə** üçün kod nümunəsi:*
```php
// Bu kod dəyərə görə müqayisə edilən Color value object-ini göstərir
readonly class Color
{
    public function __construct(
        public int $red,
        public int $green,
        public int $blue,
    ) {
        $this->validate($red, 'Red');
        $this->validate($green, 'Green');
        $this->validate($blue, 'Blue');
    }

    private function validate(int $value, string $name): void
    {
        if ($value < 0 || $value > 255) {
            throw new InvalidArgumentException("{$name} 0-255 arasında olmalıdır.");
        }
    }

    public function equals(self $other): bool
    {
        return $this->red === $other->red
            && $this->green === $other->green
            && $this->blue === $other->blue;
    }

    public function toHex(): string
    {
        return sprintf('#%02X%02X%02X', $this->red, $this->green, $this->blue);
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            throw new InvalidArgumentException('Yanlış hex format.');
        }

        return new self(
            red: hexdec(substr($hex, 0, 2)),
            green: hexdec(substr($hex, 2, 2)),
            blue: hexdec(substr($hex, 4, 2)),
        );
    }

    public function __toString(): string
    {
        return $this->toHex();
    }
}

$red1 = new Color(255, 0, 0);
$red2 = Color::fromHex('#FF0000');

$red1 === $red2;        // false (fərqli referans!)
$red1->equals($red2);   // true (eyni dəyər!)
```

---

## Laravel-də Value Object nümunələri

### 1. Email Value Object

*1. Email Value Object üçün kod nümunəsi:*
```php
// Bu kod email ünvanını doğrulayan Email value object-ini göstərir
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

    public function getLocalPart(): string
    {
        return substr($this->value, 0, strpos($this->value, '@'));
    }

    public function isCorporate(): bool
    {
        $freeProviders = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'mail.ru'];
        return !in_array($this->getDomain(), $freeProviders);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

// İstifadə
$email = new Email('ORXAN@Example.COM');
echo $email;             // orxan@example.com (normalized)
echo $email->getDomain();    // example.com
echo $email->isCorporate();  // true

// Type safety
function sendInvitation(Email $to, string $message): void
{
    Mail::raw($message, fn ($mail) => $mail->to((string) $to));
}

sendInvitation(new Email('user@example.com'), 'Dəvət!');
// sendInvitation('not-an-email', 'Dəvət!'); // TypeError!
```

### 2. Money Value Object

*2. Money Value Object üçün kod nümunəsi:*
```php
// Bu kod valyuta kodunu təmsil edən Currency value object-ini göstərir
readonly class Currency
{
    private const VALID_CURRENCIES = [
        'AZN' => ['name' => 'Azərbaycan Manatı', 'symbol' => '₼', 'decimals' => 2],
        'USD' => ['name' => 'ABŞ Dolları', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['name' => 'Avro', 'symbol' => '€', 'decimals' => 2],
        'TRY' => ['name' => 'Türk Lirəsi', 'symbol' => '₺', 'decimals' => 2],
        'GBP' => ['name' => 'Britaniya Funtu', 'symbol' => '£', 'decimals' => 2],
    ];

    public function __construct(
        public string $code,
    ) {
        if (!isset(self::VALID_CURRENCIES[$code])) {
            throw new InvalidArgumentException("Yanlış valyuta kodu: {$code}");
        }
    }

    public function name(): string
    {
        return self::VALID_CURRENCIES[$this->code]['name'];
    }

    public function symbol(): string
    {
        return self::VALID_CURRENCIES[$this->code]['symbol'];
    }

    public function decimals(): int
    {
        return self::VALID_CURRENCIES[$this->code]['decimals'];
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    public static function AZN(): self { return new self('AZN'); }
    public static function USD(): self { return new self('USD'); }
    public static function EUR(): self { return new self('EUR'); }

    public function __toString(): string
    {
        return $this->code;
    }
}

readonly class Money
{
    /**
     * @param int $amount Ən kiçik vahiddə (qəpik/cent)
     */
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {}

    // Factory methods
    public static function fromFloat(float $value, Currency $currency): self
    {
        $multiplier = 10 ** $currency->decimals();
        return new self((int) round($value * $multiplier), $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public static function AZN(float $amount): self
    {
        return self::fromFloat($amount, Currency::AZN());
    }

    public static function USD(float $amount): self
    {
        return self::fromFloat($amount, Currency::USD());
    }

    // Riyazi əməliyyatlar - hamısı yeni Money qaytarır (immutable)
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

    public function multiply(int|float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function divide(int|float $divisor): self
    {
        if ($divisor == 0) {
            throw new DivisionByZeroError('Sıfıra bölmə mümkün deyil.');
        }
        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    public function percentage(float $percent): self
    {
        return $this->multiply($percent / 100);
    }

    // Müqayisə
    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    public function greaterThan(self $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amount > $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->ensureSameCurrency($other);
        return $this->amount < $other->amount;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->greaterThan($other) || $this->equals($other);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    // Format
    public function toFloat(): float
    {
        return $this->amount / (10 ** $this->currency->decimals());
    }

    public function format(): string
    {
        return number_format(
            $this->toFloat(),
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

    public function __toString(): string
    {
        return $this->format();
    }
}

// İstifadə nümunələri
$price = Money::AZN(49.99);
$tax = $price->percentage(18);         // 9.00 ₼
$total = $price->add($tax);            // 58.99 ₼
$discount = $total->percentage(10);    // 5.90 ₼
$finalPrice = $total->subtract($discount); // 53.09 ₼

echo $finalPrice->format(); // "53.09 ₼"

// Siyahı üçün cəmləmə
$items = [
    Money::AZN(29.99),
    Money::AZN(49.99),
    Money::AZN(9.99),
];

$total = array_reduce(
    $items,
    fn (Money $carry, Money $item) => $carry->add($item),
    Money::zero(Currency::AZN()),
);

echo $total; // "89.97 ₼"
```

### 3. Address Value Object

*3. Address Value Object üçün kod nümunəsi:*
```php
// Bu kod çatdırılma ünvanını saxlayan Address value object-ini göstərir
readonly class Address
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        public string $zipCode,
        public string $country,
        public ?string $apartment = null,
    ) {
        if (empty(trim($street))) {
            throw new InvalidArgumentException('Küçə adı boş ola bilməz.');
        }
        if (empty(trim($city))) {
            throw new InvalidArgumentException('Şəhər adı boş ola bilməz.');
        }
        if (empty(trim($country))) {
            throw new InvalidArgumentException('Ölkə adı boş ola bilməz.');
        }
    }

    public function withApartment(string $apartment): self
    {
        return new self(
            street: $this->street,
            city: $this->city,
            state: $this->state,
            zipCode: $this->zipCode,
            country: $this->country,
            apartment: $apartment,
        );
    }

    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->street,
            $this->apartment ? "mənzil {$this->apartment}" : null,
            $this->city,
            $this->state,
            $this->zipCode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    public function equals(self $other): bool
    {
        return $this->street === $other->street
            && $this->city === $other->city
            && $this->state === $other->state
            && $this->zipCode === $other->zipCode
            && $this->country === $other->country
            && $this->apartment === $other->apartment;
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'country' => $this->country,
            'apartment' => $this->apartment,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            state: $data['state'],
            zipCode: $data['zip_code'],
            country: $data['country'],
            apartment: $data['apartment'] ?? null,
        );
    }

    public function __toString(): string
    {
        return $this->fullAddress();
    }
}
```

### 4. PhoneNumber Value Object

*4. PhoneNumber Value Object üçün kod nümunəsi:*
```php
// Bu kod telefon nömrəsini doğrulayan PhoneNumber value object-ini göstərir
readonly class PhoneNumber
{
    public readonly string $value;

    public function __construct(string $number)
    {
        // Yalnız rəqəmləri saxla
        $cleaned = preg_replace('/[^0-9+]/', '', $number);

        if (!preg_match('/^\+?[0-9]{10,15}$/', $cleaned)) {
            throw new InvalidArgumentException("Yanlış telefon nömrəsi: {$number}");
        }

        $this->value = $cleaned;
    }

    public function getCountryCode(): string
    {
        if (str_starts_with($this->value, '+994')) {
            return '+994';
        }
        if (str_starts_with($this->value, '+1')) {
            return '+1';
        }
        if (str_starts_with($this->value, '+90')) {
            return '+90';
        }
        return '';
    }

    public function format(): string
    {
        if (str_starts_with($this->value, '+994')) {
            // Azərbaycan formatı: +994 XX XXX XX XX
            return preg_replace(
                '/^\+994(\d{2})(\d{3})(\d{2})(\d{2})$/',
                '+994 $1 $2 $3 $4',
                $this->value,
            );
        }

        return $this->value;
    }

    public function isAzerbaijani(): bool
    {
        return str_starts_with($this->value, '+994');
    }

    public function isMobile(): bool
    {
        if (!$this->isAzerbaijani()) {
            return false;
        }

        $mobilePrefix = substr($this->value, 4, 2);
        return in_array($mobilePrefix, ['50', '51', '55', '70', '77', '99']);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}

// İstifadə
$phone = new PhoneNumber('+994 50 123 45 67');
echo $phone->format();      // +994 50 123 45 67
echo $phone->isMobile();    // true
echo $phone->isAzerbaijani(); // true
```

### 5. Coordinate Value Object

*5. Coordinate Value Object üçün kod nümunəsi:*
```php
// Bu kod GPS koordinatını saxlayan Coordinate value object-ini göstərir
readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Latitude -90 ilə 90 arasında olmalıdır.');
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Longitude -180 ilə 180 arasında olmalıdır.');
        }
    }

    /**
     * Haversine formula ilə iki nöqtə arasındakı məsafəni hesabla (km)
     */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6371; // km

        $latDiff = deg2rad($other->latitude - $this->latitude);
        $lonDiff = deg2rad($other->longitude - $this->longitude);

        $a = sin($latDiff / 2) * sin($latDiff / 2)
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude))
            * sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    public function isWithinRadius(self $center, float $radiusKm): bool
    {
        return $this->distanceTo($center) <= $radiusKm;
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude
            && $this->longitude === $other->longitude;
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function __toString(): string
    {
        return "{$this->latitude}, {$this->longitude}";
    }
}

// Bakı və İstanbul arasındakı məsafə
$baku = new Coordinate(40.4093, 49.8671);
$istanbul = new Coordinate(41.0082, 28.9784);

echo $baku->distanceTo($istanbul); // ~1750.xx km
```

---

## Value Object Validation

Value Object-lər **yaradıldığı anda** validate olunmalıdır. "Always valid" prinsipi - əgər obyekt yaradılıbsa, o düzgündür.

*Value Object-lər **yaradıldığı anda** validate olunmalıdır. "Always va üçün kod nümunəsi:*
```php
// Bu kod faiz dəyərini doğrulayan Percentage value object-ini göstərir
readonly class Percentage
{
    public function __construct(
        public float $value,
    ) {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException(
                "Faiz 0 ilə 100 arasında olmalıdır. Verilən: {$value}"
            );
        }
    }

    public function of(Money $money): Money
    {
        return $money->percentage($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return "{$this->value}%";
    }
}

readonly class Iban
{
    public readonly string $value;

    public function __construct(string $iban)
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (!$this->isValidFormat($iban)) {
            throw new InvalidArgumentException("Yanlış IBAN format: {$iban}");
        }

        if (!$this->passesChecksum($iban)) {
            throw new InvalidArgumentException("IBAN checksum yanlışdır: {$iban}");
        }

        $this->value = $iban;
    }

    private function isValidFormat(string $iban): bool
    {
        return (bool) preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4,30}$/', $iban);
    }

    private function passesChecksum(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';

        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            $numeric .= ctype_alpha($char) ? (ord($char) - 55) : $char;
        }

        return bcmod($numeric, '97') === '1';
    }

    public function getCountryCode(): string
    {
        return substr($this->value, 0, 2);
    }

    public function getBankCode(): string
    {
        return substr($this->value, 4, 4);
    }

    public function format(): string
    {
        return implode(' ', str_split($this->value, 4));
    }

    public function isAzerbaijani(): bool
    {
        return $this->getCountryCode() === 'AZ';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}

// İstifadə
$iban = new Iban('AZ21 NABZ 0000 0000 1370 1000 1944');
echo $iban->format();           // AZ21 NABZ 0000 0000 1370 1000 1944
echo $iban->getCountryCode();   // AZ
echo $iban->isAzerbaijani();    // true
```

---

## Eloquent ilə Integration

### Custom Cast yaratma

Laravel-in Cast sistemi ilə Value Object-ləri Eloquent model-lərdə birbaşa istifadə edə bilərik.

*Laravel-in Cast sistemi ilə Value Object-ləri Eloquent model-lərdə bir üçün kod nümunəsi:*
```php
// app/Casts/MoneyCast.php
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $currencyField = 'currency',
    ) {}

    /**
     * DB-dən oxuyanda: int -> Money
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currencyCode = $attributes[$this->currencyField] ?? 'AZN';

        return new Money(
            amount: (int) $value,
            currency: new Currency($currencyCode),
        );
    }

    /**
     * DB-yə yazanda: Money -> int
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (!$value instanceof Money) {
            throw new InvalidArgumentException('Dəyər Money tipində olmalıdır.');
        }

        return [
            $key => $value->amount,
            $this->currencyField => $value->currency->code,
        ];
    }
}

// app/Casts/EmailCast.php
class EmailCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Email
    {
        if ($value === null) {
            return null;
        }

        return new Email($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Email) {
            return $value->value;
        }

        // String gəlirsə, Email obyekti yaradıb validate edirik
        return (new Email($value))->value;
    }
}

// app/Casts/AddressCast.php
class AddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Address
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode($value, true);
        return Address::fromArray($data);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Address) {
            throw new InvalidArgumentException('Dəyər Address tipində olmalıdır.');
        }

        return json_encode($value->toArray());
    }
}

// app/Casts/PhoneNumberCast.php
class PhoneNumberCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?PhoneNumber
    {
        if ($value === null) {
            return null;
        }

        return new PhoneNumber($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PhoneNumber) {
            return $value->value;
        }

        return (new PhoneNumber($value))->value;
    }
}

// Model-də istifadə
class Order extends Model
{
    protected $casts = [
        'total' => MoneyCast::class . ':currency',
        'shipping_address' => AddressCast::class,
    ];
}

class User extends Authenticatable
{
    protected $casts = [
        'email' => EmailCast::class,
        'phone' => PhoneNumberCast::class,
    ];
}

// İstifadə
$order = Order::find(1);

// Artıq Money obyektidir
echo $order->total->format();           // "149.99 ₼"
echo $order->total->currency->name();   // "Azərbaycan Manatı"

// Ünvan obyektidir
echo $order->shipping_address->city;    // "Bakı"
echo $order->shipping_address->fullAddress();

// Yeni order yaratma
$order = Order::create([
    'total' => Money::AZN(299.99),
    'currency' => 'AZN',
    'shipping_address' => new Address(
        street: 'Neftçilər prospekti 45',
        city: 'Bakı',
        state: 'Bakı',
        zipCode: 'AZ1000',
        country: 'Azərbaycan',
        apartment: '12',
    ),
]);

// User-da email artıq Email obyektidir
$user = User::find(1);
echo $user->email->getDomain();      // "example.com"
echo $user->email->isCorporate();    // true/false
echo $user->phone->format();         // "+994 50 123 45 67"
echo $user->phone->isMobile();       // true
```

---

## Value Object-lərin Test Edilməsi

*Value Object-lərin Test Edilməsi üçün kod nümunəsi:*
```php
// tests/Unit/ValueObjects/MoneyTest.php
class MoneyTest extends TestCase
{
    // Yaradılma testləri
    public function test_can_create_money_from_float(): void
    {
        $money = Money::AZN(49.99);

        $this->assertEquals(4999, $money->amount);
        $this->assertEquals('AZN', $money->currency->code);
    }

    public function test_zero_money(): void
    {
        $money = Money::zero(Currency::AZN());

        $this->assertEquals(0, $money->amount);
        $this->assertTrue($money->isZero());
    }

    // Riyazi əməliyyat testləri
    public function test_can_add_same_currency(): void
    {
        $a = Money::AZN(10.00);
        $b = Money::AZN(20.00);

        $result = $a->add($b);

        $this->assertEquals(3000, $result->amount);
        $this->assertEquals('AZN', $result->currency->code);
    }

    public function test_cannot_add_different_currencies(): void
    {
        $azn = Money::AZN(10.00);
        $usd = Money::USD(10.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valyutalar uyğun deyil');

        $azn->add($usd);
    }

    public function test_can_subtract(): void
    {
        $a = Money::AZN(50.00);
        $b = Money::AZN(20.00);

        $result = $a->subtract($b);

        $this->assertEquals(3000, $result->amount);
    }

    public function test_cannot_subtract_more_than_balance(): void
    {
        $a = Money::AZN(10.00);
        $b = Money::AZN(20.00);

        $this->expectException(InvalidArgumentException::class);

        $a->subtract($b);
    }

    public function test_can_multiply(): void
    {
        $money = Money::AZN(10.00);
        $result = $money->multiply(3);

        $this->assertEquals(3000, $result->amount);
    }

    public function test_can_calculate_percentage(): void
    {
        $money = Money::AZN(100.00);
        $tax = $money->percentage(18);

        $this->assertEquals(1800, $tax->amount);
    }

    // Immutability testləri
    public function test_operations_do_not_mutate_original(): void
    {
        $original = Money::AZN(100.00);
        $original->add(Money::AZN(50.00));

        $this->assertEquals(10000, $original->amount); // Original dəyişməyib!
    }

    // Equality testləri
    public function test_equal_money_objects_are_equal(): void
    {
        $a = Money::AZN(49.99);
        $b = Money::AZN(49.99);

        $this->assertTrue($a->equals($b));
    }

    public function test_different_amount_not_equal(): void
    {
        $a = Money::AZN(49.99);
        $b = Money::AZN(59.99);

        $this->assertFalse($a->equals($b));
    }

    public function test_different_currency_not_equal(): void
    {
        $a = Money::AZN(49.99);
        $b = Money::USD(49.99);

        $this->assertFalse($a->equals($b));
    }

    // Format testləri
    public function test_format(): void
    {
        $money = Money::AZN(1234.56);

        $this->assertEquals('1,234.56 ₼', $money->format());
    }

    // Comparison testləri
    public function test_greater_than(): void
    {
        $a = Money::AZN(100.00);
        $b = Money::AZN(50.00);

        $this->assertTrue($a->greaterThan($b));
        $this->assertFalse($b->greaterThan($a));
    }
}

// tests/Unit/ValueObjects/EmailTest.php
class EmailTest extends TestCase
{
    public function test_creates_valid_email(): void
    {
        $email = new Email('user@example.com');
        $this->assertEquals('user@example.com', $email->value);
    }

    public function test_normalizes_email(): void
    {
        $email = new Email('  USER@EXAMPLE.COM  ');
        $this->assertEquals('user@example.com', $email->value);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('not-an-email');
    }

    public function test_gets_domain(): void
    {
        $email = new Email('user@example.com');
        $this->assertEquals('example.com', $email->getDomain());
    }

    public function test_detects_corporate_email(): void
    {
        $corporate = new Email('user@company.com');
        $free = new Email('user@gmail.com');

        $this->assertTrue($corporate->isCorporate());
        $this->assertFalse($free->isCorporate());
    }

    /** @dataProvider invalidEmails */
    public function test_rejects_various_invalid_emails(string $invalidEmail): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email($invalidEmail);
    }

    public static function invalidEmails(): array
    {
        return [
            'boş string' => [''],
            '@ yoxdur' => ['userexample.com'],
            'domain yoxdur' => ['user@'],
            'user yoxdur' => ['@example.com'],
            'boşluq var' => ['us er@example.com'],
        ];
    }
}

// tests/Unit/ValueObjects/DateRangeTest.php
class DateRangeTest extends TestCase
{
    public function test_creates_valid_date_range(): void
    {
        $range = new DateRange(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-12-31'),
        );

        $this->assertEquals(365, $range->lengthInDays());
    }

    public function test_rejects_invalid_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DateRange(
            new DateTimeImmutable('2024-12-31'),
            new DateTimeImmutable('2024-01-01'), // end < start!
        );
    }

    public function test_contains_date(): void
    {
        $range = new DateRange(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-12-31'),
        );

        $this->assertTrue($range->contains(new DateTimeImmutable('2024-06-15')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2025-01-01')));
    }

    public function test_detects_overlap(): void
    {
        $range1 = new DateRange(
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-06-30'),
        );

        $range2 = new DateRange(
            new DateTimeImmutable('2024-06-01'),
            new DateTimeImmutable('2024-12-31'),
        );

        $this->assertTrue($range1->overlaps($range2));
    }
}
```

---

## Real-World Use Cases

### 1. E-commerce qiymət hesablama

*1. E-commerce qiymət hesablama üçün kod nümunəsi:*
```php
// Bu kod vergi dərəcəsini hesablayan TaxRate value object-ini göstərir
readonly class TaxRate
{
    public function __construct(
        public float $rate,
        public string $name,
    ) {
        if ($rate < 0 || $rate > 100) {
            throw new InvalidArgumentException("Vergi dərəcəsi 0-100 arasında olmalıdır.");
        }
    }

    public function calculateTax(Money $amount): Money
    {
        return $amount->percentage($this->rate);
    }

    public static function VAT(): self
    {
        return new self(18.0, 'ƏDV');
    }

    public function equals(self $other): bool
    {
        return $this->rate === $other->rate;
    }
}

readonly class Weight
{
    public function __construct(
        public float $value,
        public string $unit = 'kg',
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Çəki mənfi ola bilməz.');
        }
    }

    public function toKg(): float
    {
        return match($this->unit) {
            'kg' => $this->value,
            'g' => $this->value / 1000,
            'lb' => $this->value * 0.453592,
            default => throw new InvalidArgumentException("Bilinməyən vahid: {$this->unit}"),
        };
    }

    public function add(self $other): self
    {
        return new self($this->toKg() + $other->toKg(), 'kg');
    }

    public function equals(self $other): bool
    {
        return abs($this->toKg() - $other->toKg()) < 0.001;
    }
}

// Service-də istifadə
class PriceCalculator
{
    public function __construct(
        private readonly TaxRate $defaultTaxRate,
    ) {}

    public function calculateOrderTotal(array $items): OrderPriceSummary
    {
        $subtotal = Money::zero(Currency::AZN());
        $totalWeight = new Weight(0);

        foreach ($items as $item) {
            $lineTotal = $item->price->multiply($item->quantity);
            $subtotal = $subtotal->add($lineTotal);
            $totalWeight = $totalWeight->add($item->weight);
        }

        $taxAmount = $this->defaultTaxRate->calculateTax($subtotal);
        $shippingCost = $this->calculateShipping($totalWeight);
        $grandTotal = $subtotal->add($taxAmount)->add($shippingCost);

        return new OrderPriceSummary(
            subtotal: $subtotal,
            taxAmount: $taxAmount,
            taxRate: $this->defaultTaxRate,
            shippingCost: $shippingCost,
            grandTotal: $grandTotal,
        );
    }

    private function calculateShipping(Weight $weight): Money
    {
        $kg = $weight->toKg();

        return match(true) {
            $kg <= 1 => Money::AZN(5.00),
            $kg <= 5 => Money::AZN(10.00),
            $kg <= 15 => Money::AZN(20.00),
            default => Money::AZN(35.00),
        };
    }
}

readonly class OrderPriceSummary
{
    public function __construct(
        public Money $subtotal,
        public Money $taxAmount,
        public TaxRate $taxRate,
        public Money $shippingCost,
        public Money $grandTotal,
    ) {}
}
```

### 2. Coğrafi axtarış

*2. Coğrafi axtarış üçün kod nümunəsi:*
```php
// Bu kod Coordinate value object istifadə edərək yaxın mağazaları tapan service-i göstərir
class StoreLocatorService
{
    public function __construct(
        private readonly StoreRepository $storeRepo,
    ) {}

    /**
     * Verilən koordinatdan müəyyən radiusda mağazaları tap
     */
    public function findNearbyStores(
        Coordinate $location,
        float $radiusKm = 10,
        int $limit = 10,
    ): Collection {
        $stores = $this->storeRepo->all();

        return $stores
            ->filter(fn (Store $store) =>
                $store->coordinate->isWithinRadius($location, $radiusKm)
            )
            ->sortBy(fn (Store $store) =>
                $store->coordinate->distanceTo($location)
            )
            ->take($limit)
            ->values();
    }
}

// Controller-da
class StoreController extends Controller
{
    public function nearby(Request $request, StoreLocatorService $locator): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:100',
        ]);

        $location = new Coordinate(
            latitude: $request->float('latitude'),
            longitude: $request->float('longitude'),
        );

        $stores = $locator->findNearbyStores(
            location: $location,
            radiusKm: $request->float('radius', 10),
        );

        return response()->json(StoreResource::collection($stores));
    }
}
```

---

## İntervyu Sualları

### 1. Value Object nədir? Entity-dən fərqi nədir?
**Cavab**: Value Object identity-si olmayan, yalnız dəyərinə görə müəyyən olunan immutable obyektdir. Entity-nin unikal ID-si var və ID-yə görə müqayisə olunur. Məsələn, 100 AZN Value Object-dir (hansı əskinasdır fərq etmir), amma User entity-dir (hər user-in unikal ID-si var).

### 2. Niyə Value Object immutable olmalıdır?
**Cavab**: Thread safety, predictability, side effect-lərin olmaması, caching imkanı. Əgər bir method Money obyektini alırsa və həmin Money başqa yerdə dəyişirsə, bug tapılması çox çətinləşir. Immutability bunu tamamilə aradan qaldırır.

### 3. Primitive Obsession nədir? Value Object bunu necə həll edir?
**Cavab**: Primitive Obsession — domain konseptlərini primitive tiplərlə (string, int, float) təmsil etmə anti-pattern-idir. Məsələn, email-i string kimi saxlamaq. Value Object bu problemi həll edir: Email class-ı yaratmaqla validation, formatting, business logic bir yerdə olur.

### 4. Laravel-də Value Object-ləri necə istifadə edirsiniz?
**Cavab**: Custom Cast yaradaraq Eloquent model-lərdə istifadə edirik. CastsAttributes interface-ini implement edirik. `get()` metodu DB-dən oxuyanda primitive-i VO-ya çevirir, `set()` metodu VO-nu primitive-ə çevirib DB-yə yazır.

### 5. Value Object-də equals() methodu niyə lazımdır?
**Cavab**: PHP-də `===` operator obyektlərin referansını müqayisə edir. İki fərqli Money obyektinin eyni dəyəri olsa da `===` false qaytaracaq. `equals()` metodu dəyər müqayisəsi aparır ki, bu Value Object-in əsas xüsusiyyətidir.

### 6. Money hesablamalarında float istifadə etmənin problemi nədir?
**Cavab**: Floating point precision problemi. `0.1 + 0.2 !== 0.3` PHP-də. Buna görə Money VO-da məbləğ ən kiçik vahiddə (cent/qəpik) integer kimi saxlanır. 49.99 AZN = 4999 qəpik. Bu, dəqiq hesablama təmin edir.

### 7. Value Object-i necə test edirsiniz?
**Cavab**: Unit test-lərlə. Yaradılma (valid/invalid input), equality (eyni/fərqli dəyərlər), immutability (əməliyyatdan sonra original dəyişmir), business logic (hesablamalar, format), edge cases (sıfır, mənfi, max dəyərlər). Data providers ilə çoxlu ssenari test edilir.

### 8. PHP 8.2 `readonly class` Value Object üçün nə verir?
**Cavab**: `readonly class` bütün property-ləri avtomatik `readonly` edir — hər birini ayrıca `readonly` yazmağa ehtiyac yoxdur. Bu, Value Object pattern üçün idealdır: bir dəfə yaradılır, dəyişdirilə bilməz. Mənfi tərəfi: `readonly class`-ın heç bir property-si məcburi `readonly` olur, mixin (trait) property-ləri `readonly` olmamalıdır — buna görə trait-lərə ehtiyatlı olmaq lazımdır.
***Cavab**: `readonly class` bütün property-ləri avtomatik `readonly` e üçün kod nümunəsi:*
```php
// Bu kod readonly class ilə yaradılmış Money value object-ini göstərir
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}
    public function add(self $other): self
    {
        // Yeni VO qaytarır — immutability qorunur
        return new self($this->amount + $other->amount, $this->currency);
    }
}
```

### 9. Value Object-ləri JSON-a serialize edərkən hansı problemlər yaranır?
**Cavab**: VO-lar PHP `JsonSerializable` implement etməzsə, JSON encode zamanı property-lər düz serialize olunur — amma bu kontekstsizdir (Money `{"amount":100,"currency":"AZN"}` olur, amma kim alacaq bilmir). Problem: deserializasiya zamanı hansı class yaradılacağını Laravel/PHP bilmir. Həll: `JsonSerializable` implement et, Eloquent custom cast yazaraq `get()`-də VO-ya, `set()`-də primitive-ə çevir. API response-larda VO-nu DTO-ya çevirib qaytarmaq daha təmizdir.

### 10. Value Object-lər hashable olmalıdırmı?
**Cavab**: PHP-də native hashCode yoxdur, amma array key kimi istifadə etmək lazım olarsa (məsələn, Money → amount kimi map), VO-ya `toHash()` / `toKey()` metodu əlavə etmək faydalıdır. `spl_object_id()` hər instance üçün fərqlidir — bu, dəyər bərabərliyi deyil, referans bərabərliyidir. İki eyni dəyərli VO üçün `toKey()` eyni string qaytarmalıdır: `"AZN:100"`.

---

## Anti-patternlər

**1. Primitive Obsession — Dəyərləri Primitive Tiplərlə Saxlamaq**
Email, pul məbləği, telefon nömrəsi kimi domain konseptlərini `string` və ya `float` kimi saxlamaq — validation dağılır, bir string-in nə olduğu bilinmir, eyni validasiya kodu hər yerdə təkrarllanır. Hər domain konsepti üçün Value Object yazın.

**2. Mutable Value Object**
VO-nun property-lərini `public` edib və ya setter yazıb dəyərini dəyişməyə icazə vermək — eyni VO-ya iki yerə referans olduqda gözlənilməz yan effektlər yaranır. VO-lar tam immutable olmalıdır; dəyişiklik yeni VO qaytarmalıdır.

**3. `===` ilə Equality Müqayisəsi**
İki VO-nu `===` ilə müqayisə etmək — PHP referans müqayisəsi edir, eyni dəyəri daşıyan iki ayrı VO `false` qaytarır. Hər VO-da `equals()` metodu yazın və dəyər bazalı müqayisə aparın.

**4. Float ilə Pul Hesablamaları**
`Money` VO-sunda məbləği `float` kimi saxlamaq — `0.1 + 0.2 !== 0.3` kimi floating point dəqiqlik problemləri yaranır, maliyyə hesablarında fərqlər ortaya çıxır. Məbləği ən kiçik vahiddə (qəpik) `int` kimi saxlayın.

**5. Bütün Validation-u VO-dan Kənarda Etmək**
Email formatını VO constructor-da yox, Controller və ya Form Request-də yoxlamaq — eyni qaydanı hər yerə yazmaq lazım olur, bir yerdə buraxılan yoxlama bütün sistemi təhlükəyə atar. Validation VO constructor-un içinə yerləşdirilməlidir.

**6. VO-nu Entity kimi Davranmaq**
Value Object-ə `id` vermək, database-də ayrıca cədvəldə saxlamaq, yeniləmək — VO-nun əsas xüsusiyyəti identitysizlikdir. Əgər bir konseptin həyat dövrü varsa, o Entity-dir, VO deyil; fərqi düzgün müəyyən edin.
