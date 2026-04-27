# Metodlar və Funksiyalar — Java vs PHP (Junior)

## İcmal

PHP-də `function` keyword ilə class xaricində müstəqil funksiya yazmaq mümkündür. Java-da isə bu **mümkün deyil** — hər metod mütləq bir class-ın daxilində olmalıdır. Bu, PHP developerları üçün ən tez-tez rastlaşılan ilk maneədir.

---

## Niyə Vacibdir

PHP-dən Java-ya keçəndə developer-in yazdığı ilk şeylər adətən helper funksiyalar və utility metodlardır. Bu keçid zamanı:

- "Funksiya hara yazılır?" sualı dərhal meydana çıxır
- Method signature-ın hər hissəsi məcburidir — PHP-dəki kimi ixtiyari deyil
- Default parameter, multiple return, `&$ref` — hamısı fərqli işləyir
- Java-da overloading birinci sinif mexanizm kimi istifadə olunur

Bu fərqləri erkən mərhələdə başa düşmək, sonrakı OOP mövzularını çox asanlaşdırır.

---

## PHP-də Funksiyalar vs Java-da Metodlar

PHP-də funksiya class-dan **müstəqil** mövcud ola bilər:

```php
// PHP — class xaricindəki müstəqil funksiya
function add(int $a, int $b): int
{
    return $a + $b;
}

echo add(3, 5); // 8
```

Java-da eyni iş üçün mütləq bir class lazımdır:

```java
// Java — class olmadan funksiya yazılmır
public class MathUtils {
    public static int add(int a, int b) {
        return a + b;
    }
}

// İstifadə:
int result = MathUtils.add(3, 5); // 8
```

> **Qayda:** Java-da hər şey class-ın içindədir. Global funksiya yoxdur.

---

## Əsas Fərqlər

### 1. Method Signature Quruluşu

Java-da method signature-ın hər elementi öz mənasını daşıyır:

```
[access modifier] [static?] returnType methodName(paramType paramName, ...) { }
```

| Element | Nümunə | Açıqlama |
|---|---|---|
| access modifier | `public` / `private` / `protected` | Kim çağıra bilər |
| `static` | `static` (optional) | Instance tələb etmirmi |
| returnType | `int`, `String`, `void` | Qaytarılan tip — məcburi |
| methodName | `calculateTotal` | camelCase — məcburi |
| params | `int price, int qty` | Tip + ad — tip məcburi |

PHP-də returnType optional idi (PHP 7+ istifadə tövsiyə olunur). Java-da **həmişə məcburidir**.

---

### 2. Return Types

**`void`** — heç nə qaytarmır:

```java
public void printMessage(String msg) {
    System.out.println(msg);
    // return; — optional, boş return yazıla bilər
}
```

**Primitive və Object return:**

```java
public int getAge() { return 25; }
public String getName() { return "Alice"; }
public List<String> getTags() { return List.of("java", "backend"); }
```

**Multiple return yoxdur** — Java metod yalnız bir dəyər qaytarır. PHP-dəki kimi `return [$a, $b]` yazıb array unpack etmək olar, amma əsl yanaşma DTO istifadəsidir:

```java
// ❌ Əvəzinə PHP developer bunu yazmağa çalışır:
// return new int[]{x, y};  — texniki cəhətdən işləyir amma poor practice

// ✅ Doğru yanaşma — DTO istifadə et
public record Coordinates(double lat, double lng) {}

public Coordinates getLocation() {
    return new Coordinates(40.4093, 49.8671);
}
```

---

### 3. Method Overloading

PHP-də eyni adla iki metod **yazmaq olmaz**. Java-da isə bu birinci sinif xüsusiyyətdir:

```java
// Java — eyni ad, fərqli parametrlər → overloading
public class Formatter {

    public String format(int value) {
        return String.valueOf(value);
    }

    public String format(double value) {
        return String.format("%.2f", value);
    }

    public String format(String value, boolean uppercase) {
        return uppercase ? value.toUpperCase() : value;
    }
}

// Çağırış — compiler doğru metodu seçir
Formatter f = new Formatter();
f.format(42);           // → "42"
f.format(3.14);         // → "3.14"
f.format("hello", true); // → "HELLO"
```

PHP-də bu problemi `__call` magic method və ya default params ilə həll edirlər:

```php
// PHP workaround — native overloading yoxdur
public function format(mixed $value, bool $uppercase = false): string
{
    if (is_int($value)) { return (string) $value; }
    if (is_float($value)) { return number_format($value, 2); }
    return $uppercase ? strtoupper($value) : $value;
}
```

---

### 4. Default Parameter Values

PHP-də default parametr dəstəklənir:

```php
// PHP
function greet(string $name, string $greeting = 'Hello'): string
{
    return "$greeting, $name!";
}

greet('Alice');           // Hello, Alice!
greet('Alice', 'Hi');     // Hi, Alice!
```

Java-da default parametr **mövcud deyil**. Bunun əvəzinə overloading istifadə olunur:

```java
// Java — overloading ilə default simulyasiya
public String greet(String name) {
    return greet(name, "Hello");   // öz overload-ını çağırır
}

public String greet(String name, String greeting) {
    return greeting + ", " + name + "!";
}
```

> Bir çox Java kitabxanasında bu pattern geniş istifadə olunur.

---

### 5. Varargs

Hər iki dildə dəyişən sayda arqument qəbul etmək mümkündür:

```php
// PHP — ...$args (spread operator)
function sum(int ...$numbers): int
{
    return array_sum($numbers);
}

sum(1, 2, 3);       // 6
sum(10, 20);        // 30
```

```java
// Java — Type... args (varargs)
public int sum(int... numbers) {
    int total = 0;
    for (int n : numbers) total += n;
    return total;
}

sum(1, 2, 3);       // 6
sum(10, 20);        // 30
```

**Fərqlər:**

| | PHP | Java |
|---|---|---|
| Syntax | `...$args` | `Type... args` |
| Tip | Tip elan olunmaya bilər | Tip məcburidir |
| Array kimi işlənir | Bəli | Bəli (array kimi loop edilir) |
| Mövqeyi | İstənilən yerdə | Yalnız **son parametr** ola bilər |
| Overloading ilə birlikdə | Yoxdur | İşləyir |

---

### 6. Static vs Instance Metodlar

Hər iki dildə anlayış eynidir — yalnız syntax fərqlidir:

```php
// PHP
class Calculator {
    private int $memory = 0;

    // Instance metod — $this-ə çıxışı var
    public function add(int $a, int $b): int {
        $this->memory = $a + $b;
        return $this->memory;
    }

    // Static metod — instance tələb etmir
    public static function multiply(int $a, int $b): int {
        return $a * $b;
    }
}

$calc = new Calculator();
$calc->add(3, 5);          // instance method
Calculator::multiply(3, 5); // static method
```

```java
// Java
public class Calculator {
    private int memory = 0;

    // Instance metod — this-ə çıxışı var
    public int add(int a, int b) {
        this.memory = a + b;
        return this.memory;
    }

    // Static metod — instance tələb etmir
    public static int multiply(int a, int b) {
        return a * b;
    }
}

Calculator calc = new Calculator();
calc.add(3, 5);              // instance method
Calculator.multiply(3, 5);   // static method
```

---

### 7. Pass-by-Value — Java Həmişə

PHP-də primitive-lər value ilə, object-lər isə reference ilə ötürülür. Açıq `&$param` ilə isə hər tipin özü pass-by-reference edilə bilər:

```php
// PHP — explicit reference passing
function increment(int &$n): void {
    $n++;
}

$x = 5;
increment($x);
echo $x; // 6 — dəyişdi!
```

Java-da **həmişə pass-by-value** işləyir. Object üçün pass olunan dəyər isə **reference-ın kopyasıdır**:

```java
// Java — primitive: dəyərin kopyası
public static void increment(int n) {
    n++;  // yalnız lokal dəyişir
}

int x = 5;
increment(x);
System.out.println(x); // 5 — dəyişmədi!

// Java — object: reference-ın kopyası
public static void appendItem(List<String> list) {
    list.add("new");  // orijinal list dəyişir!
}

List<String> items = new ArrayList<>();
appendItem(items);
System.out.println(items); // [new] — dəyişdi!
```

> Java-da `&` operatoru yoxdur. Reference passing simulyasiyası üçün wrapper class (`int[]`, `AtomicInteger`) istifadə olunur.

---

### 8. First-Class Functions vs Method References

PHP-də funksiyalar string kimi ötürülə bilər və `array_map` kimi funksiyalara argument kimi verilir:

```php
// PHP — first-class functions
$lengths = array_map('strlen', ['hello', 'world']); // [5, 5]

// Closure (anonymous function)
$double = fn($x) => $x * 2;
$result = array_map($double, [1, 2, 3]); // [2, 4, 6]
```

Java-da bunun ekvivalenti **method reference** və **lambda**-dır:

```java
// Java — method reference
List<String> words = List.of("hello", "world");
List<Integer> lengths = words.stream()
    .map(String::length)  // method reference
    .collect(Collectors.toList()); // [5, 5]

// Lambda (anonymous function)
Function<Integer, Integer> doubler = x -> x * 2;
List<Integer> result = List.of(1, 2, 3).stream()
    .map(doubler)
    .collect(Collectors.toList()); // [2, 4, 6]
```

**Method reference sintaksisi:**

| Forma | Nümunə | Mənası |
|---|---|---|
| `ClassName::staticMethod` | `Math::abs` | Static metod |
| `instance::method` | `myObj::process` | Konkret instance metodu |
| `ClassName::instanceMethod` | `String::length` | İstənilən instance üzərindən |
| `ClassName::new` | `ArrayList::new` | Constructor reference |

---

### 9. Naming Qaydaları

| | PHP | Java |
|---|---|---|
| Metod adı | Müxtəlif konvensiyalar (camelCase tövsiyə) | **camelCase — məcburi** |
| Tip adı | PascalCase | **PascalCase — məcburi** |
| Nümunə | `get_user_name()` da işləyir | `getUserName()` — tək yol |

```java
// ✅ Java-da düzgün naming
public String getUserName() { ... }
public void setUserName(String name) { ... }
public boolean isActive() { ... }    // boolean üçün is/has prefix
public List<Order> findAllOrders() { ... }
```

---

## Nümunələr

### PHP-də Funksiya

```php
<?php

// Müstəqil funksiya
function calculateDiscount(float $price, float $percent = 10.0): float
{
    return $price * (1 - $percent / 100);
}

// Class metodu
class PriceService
{
    public function applyDiscount(float $price, float $percent = 10.0): float
    {
        return calculateDiscount($price, $percent);
    }

    public static function formatPrice(float $price, string $currency = 'USD'): string
    {
        return $currency . ' ' . number_format($price, 2);
    }
}

echo calculateDiscount(100.0);         // 90.0
echo PriceService::formatPrice(90.0);  // USD 90.00
```

### Java-da Metod

```java
public class PriceService {

    // Static — utility helper, instance tələb etmir
    public static double calculateDiscount(double price, double percent) {
        return price * (1 - percent / 100);
    }

    // Overloading ilə default percent = 10
    public static double calculateDiscount(double price) {
        return calculateDiscount(price, 10.0);
    }

    public static String formatPrice(double price) {
        return formatPrice(price, "USD");
    }

    public static String formatPrice(double price, String currency) {
        return String.format("%s %.2f", currency, price);
    }
}

// İstifadə
double discounted = PriceService.calculateDiscount(100.0);     // 90.0
String label = PriceService.formatPrice(discounted);           // USD 90.00
```

### Yan-yana Müqayisə

**Problem:** Bir siyahıdakı string-ləri böyük hərfə çevir, boşluqları kəs, boş olanları çıxar.

```php
// PHP
function cleanStrings(array $items): array
{
    return array_values(
        array_filter(
            array_map(fn($s) => strtoupper(trim($s)), $items),
            fn($s) => $s !== ''
        )
    );
}

cleanStrings(['  hello ', '', 'world ']); // ['HELLO', 'WORLD']
```

```java
// Java
import java.util.List;
import java.util.stream.Collectors;

public class StringUtils {

    public static List<String> cleanStrings(List<String> items) {
        return items.stream()
            .map(String::trim)
            .map(String::toUpperCase)
            .filter(s -> !s.isEmpty())
            .collect(Collectors.toList());
    }
}

StringUtils.cleanStrings(List.of("  hello ", "", "world ")); // [HELLO, WORLD]
```

---

## Praktik Tapşırıqlar

**Tapşırıq 1 — İlk Java metodu**

`MathHelper` class-ı yaz. İçinə aşağıdakıları əlavə et:
- `square(int n)` — ədədin kvadratını qaytarır
- `power(int base, int exp)` — base-i exp dəfə özünə vurur
- `max(int a, int b)` və `max(int a, int b, int c)` — overloading ilə

**Tapşırıq 2 — Default params simulyasiyası**

`EmailService` class-ı yaz:
- `send(String to, String subject, String body)` — tam imza
- `send(String to, String subject)` — body default `"(no content)"` olsun
- `send(String to)` — subject `"No Subject"`, body `"(no content)"` olsun
Overloading ilə kodun təkrarlanmaması üçün hər metod digərini çağırmalıdır.

**Tapşırıq 3 — Varargs**

`Logger` class-ı yaz:
- `log(String level, String... messages)` — hər message-i `[LEVEL] message` formatında print et
- `log("INFO", "Starting", "Loading config", "Ready")` kimi çağırışı test et

**Tapşırıq 4 — Method references**

Verilmiş `List<String> names` üçün:
- `String::toUpperCase` method reference ilə hamısını böyük hərfə çevir
- `String::length` ilə uzunluqları tap
- Lambda ilə 5 simvoldan qısa olanları filter et

**Tapşırıq 5 — Pass-by-value anlamaq**

Aşağıdakı kodu çalışdır və nəticəni izah et:
```java
public static void tryChange(int n, List<String> list) {
    n = 999;
    list.add("added");
}

int num = 1;
List<String> items = new ArrayList<>(List.of("a"));
tryChange(num, items);
// num nə oldu? items nə oldu? Niyə?
```

---

## Əlaqəli Mövzular

- `03-control-flow-and-operators.md` — metodların içindəki logic
- `07-static-vs-instance-members.md` — static metodların dərin izahı
- `09-this-and-super-keywords.md` — `this` ilə metod çağırışı
- `11-oop-classes-interfaces.md` — metodların class dizaynında rolu
- `46-pass-by-value-reference.md` — pass-by-value dərin izahı
- `47-method-overloading-vs-overriding.md` — overloading vs overriding fərqi
