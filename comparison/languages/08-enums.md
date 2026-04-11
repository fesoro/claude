# Enum-lar — Java vs PHP

## Giris

Enum (enumeration — saymali tip) mehdud sayda sabit deyerler teyyin etmek ucun istifade olunur. Meselen, heftening gunleri, sifarishin statusu, istifadecinin rolu ve s. Java enum-lari 2004-cu ilde (Java 5) elave olunub ve cox guclu xususiyyetlere malikdir — metodlar, saheler, interfeys implementasiyasi. PHP ise enum desteyini yalniz 2021-ci ilde (PHP 8.1) elave edib, lakin olduqca muasir ve funksional dizayn yaradib.

---

## Java-da istifadesi

### Sade enum

```java
public enum Season {
    WINTER, SPRING, SUMMER, AUTUMN
}

// Istifadesi
Season current = Season.SUMMER;
System.out.println(current);         // SUMMER
System.out.println(current.name());  // SUMMER
System.out.println(current.ordinal()); // 2 (sira nomresi, 0-dan bashlayir)

// String-den enum-a cevirmek
Season s = Season.valueOf("WINTER"); // WINTER
// Season.valueOf("winter") → IllegalArgumentException (herfler uyushmur)

// Butun deyerleri almaq
Season[] all = Season.values();
for (Season season : all) {
    System.out.println(season);
}
```

### Sahe ve metodlu enum

Java enum-larinin en guclu xususiyyeti — onlarin **tam sinifler** olmasıdir:

```java
public enum OrderStatus {
    PENDING("Gozleyir", false),
    PROCESSING("Emal olunur", false),
    SHIPPED("Gonderildi", false),
    DELIVERED("Catdirildi", true),
    CANCELLED("Legv edildi", true);

    private final String label;
    private final boolean isFinal;

    // Konstruktor — private olmalidir (avtomatik olaraq)
    OrderStatus(String label, boolean isFinal) {
        this.label = label;
        this.isFinal = isFinal;
    }

    public String getLabel() {
        return label;
    }

    public boolean isFinal() {
        return isFinal;
    }

    // Custom metod
    public boolean canTransitionTo(OrderStatus next) {
        return switch (this) {
            case PENDING -> next == PROCESSING || next == CANCELLED;
            case PROCESSING -> next == SHIPPED || next == CANCELLED;
            case SHIPPED -> next == DELIVERED;
            case DELIVERED, CANCELLED -> false; // Son statuslardan kecid yoxdur
        };
    }
}

// Istifadesi
OrderStatus status = OrderStatus.PENDING;
System.out.println(status.getLabel());       // "Gozleyir"
System.out.println(status.isFinal());        // false
System.out.println(status.canTransitionTo(OrderStatus.PROCESSING)); // true
System.out.println(status.canTransitionTo(OrderStatus.DELIVERED));  // false
```

### Interfeys implementasiyasi

```java
// Enum interfeys implement ede biler
public interface Displayable {
    String getDisplayName();
    String getIcon();
}

public enum PaymentMethod implements Displayable {
    CASH("Naghd", "💵"),
    CARD("Kart", "💳"),
    BANK_TRANSFER("Bank kocurmesi", "🏦"),
    CRYPTO("Kriptovalyuta", "₿");

    private final String displayName;
    private final String icon;

    PaymentMethod(String displayName, String icon) {
        this.displayName = displayName;
        this.icon = icon;
    }

    @Override
    public String getDisplayName() {
        return displayName;
    }

    @Override
    public String getIcon() {
        return icon;
    }

    // Static metod
    public static PaymentMethod fromString(String value) {
        for (PaymentMethod method : values()) {
            if (method.name().equalsIgnoreCase(value)) {
                return method;
            }
        }
        throw new IllegalArgumentException("Bilinmeyen odeniş usulu: " + value);
    }
}
```

### Abstract metod ile enum — her deyerin ozu implement edir

```java
public enum Operation {
    ADD {
        @Override
        public double apply(double a, double b) { return a + b; }
    },
    SUBTRACT {
        @Override
        public double apply(double a, double b) { return a - b; }
    },
    MULTIPLY {
        @Override
        public double apply(double a, double b) { return a * b; }
    },
    DIVIDE {
        @Override
        public double apply(double a, double b) {
            if (b == 0) throw new ArithmeticException("Sifira bolme");
            return a / b;
        }
    };

    public abstract double apply(double a, double b);
}

// Istifadesi
double result = Operation.ADD.apply(10, 5); // 15.0
```

### EnumSet ve EnumMap

```java
import java.util.EnumSet;
import java.util.EnumMap;

// EnumSet — enum ucun optimize olunmush Set
EnumSet<Season> warmSeasons = EnumSet.of(Season.SPRING, Season.SUMMER);
EnumSet<Season> allSeasons = EnumSet.allOf(Season.class);
EnumSet<Season> noSeasons = EnumSet.noneOf(Season.class);

// EnumMap — enum key ucun optimize olunmush Map
EnumMap<Season, String> activities = new EnumMap<>(Season.class);
activities.put(Season.WINTER, "Xizek");
activities.put(Season.SUMMER, "Uzme");
```

### Switch ile istifade (Java 14+ enhanced switch)

```java
String description = switch (status) {
    case PENDING -> "Sifarish gozleyir";
    case PROCESSING -> "Sifarish emal olunur";
    case SHIPPED -> "Sifarish yoldadir";
    case DELIVERED -> "Sifarish catdirildi";
    case CANCELLED -> "Sifarish legv edildi";
};
```

---

## PHP-de istifadesi

### Sade (Pure) Enum

```php
<?php

enum Season
{
    case Winter;
    case Spring;
    case Summer;
    case Autumn;
}

// Istifadesi
$current = Season::Summer;
echo $current->name; // "Summer"

// Type hint kimi istifade
function getActivity(Season $season): string
{
    return match ($season) {
        Season::Winter => 'Xizek',
        Season::Spring => 'Gəzinti',
        Season::Summer => 'Uzme',
        Season::Autumn => 'Kitab oxuma',
    };
}

echo getActivity(Season::Summer); // "Uzme"

// Butun deyerler
$all = Season::cases(); // SplFixedArray of Season
```

### Backed Enum — deyer ile

PHP-de enum-a string ve ya int deyer baglamaq mumkundur:

```php
<?php

// String backed enum
enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    // Metodlar elave etmek mumkundur
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Gozleyir',
            self::Processing => 'Emal olunur',
            self::Shipped => 'Gonderildi',
            self::Delivered => 'Catdirildi',
            self::Cancelled => 'Legv edildi',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Delivered, self::Cancelled => true,
            default => false,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Cancelled]),
            self::Processing => in_array($next, [self::Shipped, self::Cancelled]),
            self::Shipped => $next === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }
}

// Istifadesi
$status = OrderStatus::Pending;
echo $status->value;  // "pending" (backed deyer)
echo $status->name;   // "Pending" (case adi)
echo $status->label(); // "Gozleyir"

// Deyerden enum-a cevirmek
$fromDb = OrderStatus::from('pending');        // OrderStatus::Pending
$safe = OrderStatus::tryFrom('invalid');       // null (xeta atmaz)

// JSON serialization ucun ideal
echo json_encode(['status' => $status->value]); // {"status":"pending"}
```

```php
<?php

// Integer backed enum
enum HttpStatus: int
{
    case Ok = 200;
    case Created = 201;
    case BadRequest = 400;
    case NotFound = 404;
    case InternalServerError = 500;

    public function isSuccess(): bool
    {
        return $this->value >= 200 && $this->value < 300;
    }

    public function isError(): bool
    {
        return $this->value >= 400;
    }
}

$status = HttpStatus::from(404);
echo $status->name;        // "NotFound"
echo $status->isError();   // true
```

### Interfeys implementasiyasi

```php
<?php

interface Displayable
{
    public function getDisplayName(): string;
    public function getIcon(): string;
}

enum PaymentMethod: string implements Displayable
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Crypto = 'crypto';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::Cash => 'Naghd',
            self::Card => 'Kart',
            self::BankTransfer => 'Bank kocurmesi',
            self::Crypto => 'Kriptovalyuta',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Cash => '💵',
            self::Card => '💳',
            self::BankTransfer => '🏦',
            self::Crypto => '₿',
        };
    }
}

// Interfeys tipi kimi istifade
function renderPaymentOption(Displayable $option): string
{
    return $option->getIcon() . ' ' . $option->getDisplayName();
}

echo renderPaymentOption(PaymentMethod::Card); // "💳 Kart"
```

### Enum icinde const ve static metodlar

```php
<?php

enum Color: string
{
    case Red = '#FF0000';
    case Green = '#00FF00';
    case Blue = '#0000FF';
    case White = '#FFFFFF';
    case Black = '#000000';

    // Constant
    const PRIMARY = [self::Red, self::Green, self::Blue];

    // Static metod
    public static function fromHex(string $hex): self
    {
        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $hex) === 0) {
                return $case;
            }
        }
        throw new ValueError("Bilinmeyen reng: $hex");
    }

    // Instance metod
    public function isDark(): bool
    {
        return match ($this) {
            self::Black, self::Red, self::Blue => true,
            default => false,
        };
    }
}

$color = Color::fromHex('#FF0000'); // Color::Red
echo $color->isDark(); // true
```

### Enum ve Trait

```php
<?php

trait HasDescription
{
    public function description(): string
    {
        return match ($this) {
            ...static::DESCRIPTIONS
        };
    }
}

// Qeyd: Enum-lar trait istifade ede biler,
// amma trait-de property olmamalidir (enum-larda property yoxdur)
```

---

## Esas ferqler

| Xususiyyet | Java Enum | PHP Enum |
|---|---|---|
| **Movcud versiya** | Java 5 (2004) | PHP 8.1 (2021) |
| **Mahiyyeti** | Tam sinifdir | Xususi tip (obyet kimi, lakin sinif deyil) |
| **Saheler (fields)** | Var — istənilən sayda | Yoxdur (yalniz `value` backed enum-da) |
| **Konstruktor** | Var (private) | Yoxdur |
| **Metodlar** | Var | Var |
| **Abstract metodlar** | Var — her case oz-ozu implement edir | Yoxdur |
| **Interfeys** | Implement ede biler | Implement ede biler |
| **Backed deyer** | Saheler vasitesi ile | `string` ve ya `int` ola biler |
| **from/tryFrom** | `valueOf()` | `from()` / `tryFrom()` |
| **Butun deyerler** | `values()` | `cases()` |
| **Switch/match** | `switch` (Java 14+ enhanced) | `match` |
| **Serialization** | Avtomatik `Serializable` | Backed enum `value` ile |
| **EnumSet/EnumMap** | Var | Yoxdur (adi array istifade olunur) |
| **Inheritance** | Basha enum-dan extends mumkun deyil | Extends mumkun deyil |
| **Property** | Var | Yoxdur |

---

## Niye bele ferqler var?

### Java enum-lari niye bu qeder gucludur?

Java enum-lari eslinde xususi siniflerdir. Kompilyator arxa planda enum-un her deyerini sinfin `public static final` instansiyasina cevirir. Buna gore enum-lar saheler, konstruktorlar, metodlar ve hetta abstract metodlar saxlaya biler. Bu dizayn State pattern, Strategy pattern kimi dizayn qeliblerini enum ile sade shekilde yazmaga imkan verir.

```java
// Kompilyatorun arxa planda yaratdigi:
public final class Season extends Enum<Season> {
    public static final Season WINTER = new Season("WINTER", 0);
    public static final Season SPRING = new Season("SPRING", 1);
    // ...
}
```

### PHP enum-lari niye ferqlidir?

PHP enum-lari 17 il sonra dizayn olunub ve butun dunya tejrubesinden ders alib. PHP komandasi bilincli olaraq enum-lari siniflerden ferqli bir tip kimi yaratdi:

1. **Property yoxdur** — enum deyerleri deyishilmez (immutable) olmalidir, property elave etmek bu prinsipi pozar
2. **Konstruktor yoxdur** — backed enum artiq `value`-nu ozunde saxlayir, elave saheler lazim deyil
3. **`from()`/`tryFrom()` static metodlari** — Java-nin `valueOf()` metodundan daha istifadeciye uygun, chunki `tryFrom()` xeta atmaq evezine `null` qaytarir

PHP-nin yanasdirmasi daha minimaldir, lakin gundelik ishtlerin boyuk ekseriyyeti ucun kifayetdir. Elave melumat lazim olduqda, enum-a metod elave etmek ve ya enum ile birlikde ayri sinif istifade etmek mumkundur.

### Umumi meqsed

Her iki dilde enum-lar eyni meqsede xidmet edir: **magic string/number evezine tip-tehlukesiz sabit deyerler yaratmaq**. Java bunu daha zengin alətlerle, PHP ise daha sade ve muasir yanasdirma ile hel edir.
