# Method Overloading vs Overriding

> **Seviyye:** Beginner ⭐

## Giriş

Java-da eyni adlı metodun **fərqli parametrlərlə** təyin olunması **overloading**, parent class metodunun child class-da yenidən yazılması isə **overriding**-dir. PHP bu fərqi Java ilə eyni şəkildə tətbiq etmir — bu Laravel developerları üçün ümumi bir qarışıqlıq nöqtəsidir.

---

## PHP-də Necədir

PHP true method overloading-i dəstəkləmir — eyni adlı iki metod yazıb, parametr sayına görə ayrılmaq olmaz:

```php
class Calculator
{
    // ❌ PHP-də bu mümkün DEYİL — syntax error
    // public function add(int $a, int $b): int { ... }
    // public function add(int $a, int $b, int $c): int { ... }

    // PHP-nin həlli: default parametrlər
    public function add(int $a, int $b, int $c = 0): int
    {
        return $a + $b + $c;
    }

    // Və ya variadic args
    public function sum(int ...$numbers): int
    {
        return array_sum($numbers);
    }
}
```

PHP-nin "overloading" sözü fərqli mənada işlənir — `__get`, `__set`, `__call` magic metodları vasitəsilə dinamik property/metod əlavə etmək deməkdir.

---

## Java-da Method Overloading

Java-da eyni ad, fərqli parametr imzası ilə metodlar **compile time**-da həll olunur:

```java
public class Calculator {

    // Overloading — eyni ad, fərqli parametrlər
    public int add(int a, int b) {
        return a + b;
    }

    public double add(double a, double b) {  // ✓ fərqli tip
        return a + b;
    }

    public int add(int a, int b, int c) {    // ✓ fərqli say
        return a + b + c;
    }

    public String add(String a, String b) {  // ✓ tamam fərqli tip
        return a + b;
    }
}
```

```java
Calculator calc = new Calculator();
calc.add(1, 2);         // → int add(int, int) çağırılır
calc.add(1.0, 2.0);     // → double add(double, double) çağırılır
calc.add(1, 2, 3);      // → int add(int, int, int) çağırılır
calc.add("a", "b");     // → String add(String, String) çağırılır
```

### Overloading Qaydaları

```java
public class Example {

    // ✓ Return type FƏRQLİ ola bilər (parametrlər fərqlisə)
    public int process(int x) { return x; }
    public double process(double x) { return x; }

    // ❌ YALNIZ return type fərqlisə overloading olmur — compile error
    // public double process(int x) { return x; } // Error!

    // ✓ Parametr sırası fərqlidirsə overloading sayılır
    public void print(String s, int n) { ... }
    public void print(int n, String s) { ... }
}
```

---

## Java-da Method Overriding

Parent class metodunun child class-da **eyni imza** ilə yenidən yazılması:

```java
class Animal {
    public String speak() {
        return "...";
    }

    public String describe() {
        return "I am an animal";
    }
}

class Dog extends Animal {

    @Override  // ← annotation vacibdir: typo-ları compiler tutacaq
    public String speak() {
        return "Woof!";
    }

    // describe() override edilmədi — Animal-ın versiyası istifadə olunur
}

class Cat extends Animal {

    @Override
    public String speak() {
        return "Meow!";
    }
}
```

```java
Animal dog = new Dog();
Animal cat = new Cat();

System.out.println(dog.speak()); // "Woof!" — Dog-ın override-ı çalışır
System.out.println(cat.speak()); // "Meow!" — Cat-in override-ı çalışır
System.out.println(dog.describe()); // "I am an animal" — miras alındı
```

### Runtime vs Compile Time

```
Overloading → Compile time həll olunur (static dispatch)
Overriding  → Runtime həll olunur   (dynamic dispatch / polymorphism)
```

---

## PHP-də Overriding

PHP-də method overriding Java ilə eyni işləyir:

```php
class Animal
{
    public function speak(): string
    {
        return '...';
    }
}

class Dog extends Animal
{
    public function speak(): string  // ✓ override
    {
        return 'Woof!';
    }
}

$dog = new Dog();
echo $dog->speak(); // "Woof!"
```

Fərq: PHP-də `@Override` annotasiyası yoxdur — yanlış override etsən PHP susur.

---

## @Override Annotasiyasının Əhəmiyyəti

```java
class Parent {
    public void doWork() { ... }
}

class Child extends Parent {

    // ❌ @Override YOX — typo var, yeni metod yaradır, override deyil
    public void dowork() { ... }  // 'd' lowercase — fərqli metod!
    // Compiler xəta vermir, amma doWork() override edilmir

    // ✓ @Override VAR — compiler yoxlayır
    @Override
    public void dowork() { ... }  // Compile ERROR: method not found in Parent
}
```

**Qayda:** Her zaman `@Override` yaz — compiler köməkçin olsun.

---

## Overloading + Overriding Birlikdə

```java
class Printer {
    public void print(String text) {
        System.out.println("[Printer] " + text);
    }

    public void print(String text, int copies) {  // overloading
        for (int i = 0; i < copies; i++) {
            print(text);
        }
    }
}

class ColorPrinter extends Printer {

    @Override
    public void print(String text) {               // overriding
        System.out.println("[COLOR] " + text);
    }

    // print(String, int) miras alındı — override edilmədi
}
```

```java
Printer cp = new ColorPrinter();
cp.print("Hello");        // "[COLOR] Hello" — override çalışır
cp.print("Hello", 2);     // "[COLOR] Hello" x2 — miras + override kombinasiya
```

---

## Spring-də Praktik İstifadə

Spring-in JPA Repository-lərində overloading çox işlənir:

```java
@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    // Overloading: fərqli parametrlərlə eyni adlı metodlar
    Optional<User> findByEmail(String email);
    Optional<User> findByEmail(String email, boolean active);  // custom overload?
    
    // Spring Data JPA bunu query generation ilə edir
    List<User> findByEmailAndStatus(String email, String status);
    List<User> findByEmailOrPhone(String email, String phone);
}
```

---

## Müqayisə Cədvəli

| | Overloading | Overriding |
|---|---|---|
| Nədir? | Eyni ad, fərqli parametr | Parent metodu child-da yenidən yaz |
| Nə zaman həll olunur? | Compile time | Runtime |
| Inheritance lazımdır? | Xeyr | Bəli |
| Return type fərqli ola bilər? | Bəli (parametr də fərqlisə) | Covariant (alt tip) ola bilər |
| PHP-də? | Yoxdur (default param istifadə et) | Bəli, eyni şəkildə işləyir |
| Java annotasiyası? | — | `@Override` (vacibdir!) |

---

## Praktik Tapşırıq

```java
class Shape {
    public double area() { return 0; }
    public String describe() { return "Shape"; }
}
```

Bu class-dan `Circle` və `Rectangle` yarat. Hər biri:
1. `area()` override etsin (düsturla)
2. `describe()` override etsin (adını qaytarsın)
3. `Circle`-da `scale(double factor)` və `scale(int factor)` metodları olsun (overloading)

---

## Əlaqəli Mövzular
- [11 — OOP Classes & Interfaces](11-oop-classes-interfaces.md)
- [12 — Inheritance & Polymorphism](12-inheritance-and-polymorphism.md)
- [25 — Functional Interfaces](25-functional-interfaces-method-references.md)
