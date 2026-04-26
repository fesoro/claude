# 46 — Functional Interfaces (Funksional İnterfeyslər)

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [@FunctionalInterface nədir?](#functionalinterface)
2. [Function<T,R>](#function)
3. [Predicate<T>](#predicate)
4. [Consumer<T>](#consumer)
5. [Supplier<T>](#supplier)
6. [BiFunction, BiPredicate, BiConsumer](#bi-interfaces)
7. [UnaryOperator, BinaryOperator](#operator-interfaces)
8. [Method References — 4 Növ](#method-references)
9. [Funksional Interface-lərin Birləşdirilməsi](#birləşdirmə)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## @FunctionalInterface nədir?

Funksional interface — **yalnız bir abstract metodu** olan interface. Lambda ifadəsi ilə istifadə edilir.

```java
// Sadə funksional interface
@FunctionalInterface
public interface Selamlayan {
    String selam(String ad); // Yalnız bir abstract metod

    // default metodlar ola bilər
    default String rəsmiSelam(String ad) {
        return "Hörmətli " + selam(ad);
    }

    // static metodlar ola bilər
    static Selamlayan azərbaycanca() {
        return ad -> "Salam, " + ad + "!";
    }
}

// Lambda ilə istifadə
Selamlayan selamlayan = ad -> "Merhaba, " + ad + "!";
System.out.println(selamlayan.selam("Əli")); // Merhaba, Əli!
System.out.println(selamlayan.rəsmiSelam("Əli")); // Hörmətli Merhaba, Əli!

// Static factory
Selamlayan az = Selamlayan.azərbaycanca();
System.out.println(az.selam("Murad")); // Salam, Murad!
```

### @FunctionalInterface annotation-ının rolu

```java
// Annotation olmasa da funksional interface ola bilər
// Amma annotation əlavə edilsə compiler yoxlayır

@FunctionalInterface
interface DüzgünFI {
    void bir(); // OK — bir abstract metod
}

// @FunctionalInterface
// interface YanlışFI {
//     void bir();
//     void iki(); // COMPILE XƏTA — iki abstract metod
// }

// Object metodları sayılmır
@FunctionalInterface
interface OKdır {
    void iş(); // Abstract metod
    String toString(); // Object metodunu override — sayılmır
    boolean equals(Object o); // Object metodu — sayılmır
}
```

---

## Function<T,R>

`Function<T, R>` — T qəbul edir, R qaytarır. `R apply(T t)` metodu.

```java
import java.util.function.*;

// Sadə istifadə
Function<String, Integer> uzunluq = String::length;
System.out.println(uzunluq.apply("Salam")); // 5

Function<Integer, String> toString = n -> "Rəqəm: " + n;
System.out.println(toString.apply(42)); // Rəqəm: 42

// Mürəkkəb nümunə
Function<String, String> normalize = s -> s.trim().toLowerCase();
System.out.println(normalize.apply("  ALMA  ")); // alma
```

### andThen() — ardıcıl tətbiq

```java
// andThen: f sonra g → g(f(x))
Function<String, String> küçük = String::toLowerCase;
Function<String, String> kəs = String::trim;
Function<String, Integer> uzunluq = String::length;

// Birləşdir: trim → toLowerCase → length
Function<String, Integer> normalizeVəSay = kəs
    .andThen(küçük)
    .andThen(uzunluq);

System.out.println(normalizeVəSay.apply("  SALAM  ")); // 5
// "  SALAM  " → "SALAM" → "salam" → 5
```

### compose() — əks ardıcıllıq

```java
// compose: f.compose(g) = f(g(x)) — g əvvəl, sonra f
Function<Integer, Integer> ikiQat = n -> n * 2;
Function<Integer, Integer> birƏlavə = n -> n + 1;

// andThen: ikiQat sonra birƏlavə
Function<Integer, Integer> at = ikiQat.andThen(birƏlavə);
System.out.println(at.apply(5)); // (5*2) + 1 = 11

// compose: birƏlavə sonra ikiQat
Function<Integer, Integer> c = ikiQat.compose(birƏlavə);
System.out.println(c.apply(5)); // (5+1) * 2 = 12
```

### Function.identity()

```java
// Özünü qaytaran funksiya — x → x
Function<String, String> özü = Function.identity();
System.out.println(özü.apply("Salam")); // Salam

// toMap-da tez-tez istifadə olunur
record Məhsul(int id, String ad) {}
List<Məhsul> məhsullar = List.of(new Məhsul(1, "Alma"), new Məhsul(2, "Armud"));

Map<Integer, Məhsul> idMap = məhsullar.stream()
    .collect(Collectors.toMap(Məhsul::id, Function.identity()));
// Məhsul::id → açar, özü → dəyər
```

---

## Predicate<T>

`Predicate<T>` — T qəbul edir, `boolean` qaytarır. `boolean test(T t)` metodu.

```java
// Sadə Predicate
Predicate<String> boşdur = String::isEmpty;
Predicate<String> uzundur = s -> s.length() > 5;
Predicate<Integer> müsbətdir = n -> n > 0;

System.out.println(boşdur.test(""));    // true
System.out.println(boşdur.test("a"));   // false
System.out.println(uzundur.test("Salam")); // false (5 simvol)
System.out.println(uzundur.test("Salam!")); // true (6 simvol)
```

### Predicate birləşdirmə — and(), or(), negate()

```java
Predicate<String> boşDeyil = Predicate.not(String::isEmpty);
// Yaxud: s -> !s.isEmpty()
// Yaxud: boşdur.negate()

Predicate<String> uzun = s -> s.length() > 5;
Predicate<String> baş hərfBöyük = s -> Character.isUpperCase(s.charAt(0));

// AND — hər ikisi doğru olmalıdır
Predicate<String> uzunVəBöyük = uzun.and(baş hərfBöyük);
System.out.println(uzunVəBöyük.test("Salam!")); // true
System.out.println(uzunVəBöyük.test("salam!")); // false (kiçik hərflə başlayır)

// OR — ən azı biri doğru
Predicate<String> uzunYaxudBöyük = uzun.or(baş hərfBöyük);
System.out.println(uzunYaxudBöyük.test("Hi!"));    // false
System.out.println(uzunYaxudBöyük.test("Hello!")); // true (hər ikisi)
System.out.println(uzunYaxudBöyük.test("Hey"));    // true (Böyük hərflə başlayır)

// NEGATE — tərsi
Predicate<String> qısa = uzun.negate();
System.out.println(qısa.test("Hi")); // true (6-dan az)
```

### Predicate.not() — Java 11+

```java
List<String> sözlər = Arrays.asList("alma", "", "armud", null, "gilas", "");

// Java 11+ — Predicate.not()
List<String> doluSözlər = sözlər.stream()
    .filter(Objects::nonNull)
    .filter(Predicate.not(String::isEmpty))
    .collect(Collectors.toList());
System.out.println(doluSözlər); // [alma, armud, gilas]
```

---

## Consumer<T>

`Consumer<T>` — T qəbul edir, heç nə qaytarmır (`void`). Yan effektlər üçün. `void accept(T t)` metodu.

```java
// Sadə Consumer
Consumer<String> çap = System.out::println;
Consumer<String> logla = s -> System.out.println("[LOG] " + s);

çap.accept("Salam");   // Salam
logla.accept("Xəta!"); // [LOG] Xəta!

// Faydalı nümunə
record İstifadəçi(String ad, String email) {}

Consumer<İstifadəçi> emailGöndər = u -> {
    System.out.println("Email göndərilir: " + u.email());
    // SMTP əməliyyatları...
};

Consumer<İstifadəçi> logYaz = u -> {
    System.out.println("[LOG] İstifadəçi: " + u.ad());
};
```

### andThen() — ardıcıl Consumer-lər

```java
// andThen: birinci, sonra ikinci
Consumer<İstifadəçi> hamısınıEt = logYaz.andThen(emailGöndər);

İstifadəçi istifadəçi = new İstifadəçi("Əli", "ali@example.com");
hamısınıEt.accept(istifadəçi);
// [LOG] İstifadəçi: Əli
// Email göndərilir: ali@example.com

// Üç Consumer birlikdə
Consumer<String> böyüklə = s -> System.out.println(s.toUpperCase());
Consumer<String> uzunluğuCəhd = s -> System.out.println("Uzunluq: " + s.length());

Consumer<String> hamısı = çap
    .andThen(böyüklə)
    .andThen(uzunluğuCəhd);

hamısı.accept("salam");
// salam
// SALAM
// Uzunluq: 5
```

---

## Supplier<T>

`Supplier<T>` — heç nə qəbul etmir, T qaytarır. `T get()` metodu.

```java
// Sadə Supplier
Supplier<String> salamVer = () -> "Salam, Dünya!";
Supplier<Double> təsadüfi = Math::random;
Supplier<List<String>> boşSiyahı = ArrayList::new;

System.out.println(salamVer.get());  // Salam, Dünya!
System.out.println(təsadüfi.get());  // 0.12345... (hər dəfə fərqli)
System.out.println(boşSiyahı.get()); // []

// Lazy initialization
Supplier<ExpensiveObject> lazy = () -> new ExpensiveObject(); // Lazım olduqda yaradılır
// ExpensiveObject yalnız get() çağırılanda yaradılır!

// Optional.orElseGet() ilə
Optional<String> opt = Optional.empty();
String dəyər = opt.orElseGet(() -> "Default dəyər"); // Supplier — yalnız lazım olduqda
String yanlış = opt.orElse(pahalıHesabla()); // pahalıHesabla() həmişə çağırılır!
```

### Supplier praktiki istifadə

```java
// Lazy xəta mesajı
Optional<String> result = Optional.empty();

// YANLIŞ — xəta mesajı həmişə yaradılır
result.orElseThrow(() -> new RuntimeException("Tapılmadı: " + expensiveLookup()));

// Bu isə OK — lambda lazy-dir
result.orElseThrow(() -> new RuntimeException("Tapılmadı"));

// Cache ilə Supplier
public class LazyCache<T> {
    private T dəyər;
    private final Supplier<T> istehsalçı;

    public LazyCache(Supplier<T> istehsalçı) {
        this.istehsalçı = istehsalçı;
    }

    public T al() {
        if (dəyər == null) {
            dəyər = istehsalçı.get(); // İlk çağırışda yaradılır
        }
        return dəyər;
    }
}

LazyCache<List<String>> cache = new LazyCache<>(() -> {
    System.out.println("Məlumat yüklənir...");
    return loadFromDB(); // Yalnız ilk dəfə çağırılır
});
```

---

## Bi Interfaces

İki parametr qəbul edən versiyalar:

```java
// BiFunction<T, U, R> — T və U qəbul edir, R qaytarır
BiFunction<String, Integer, String> təkrar = (s, n) -> s.repeat(n);
System.out.println(təkrar.apply("Ha", 3)); // HaHaHa

BiFunction<Integer, Integer, Integer> cəm = Integer::sum;
System.out.println(cəm.apply(10, 20)); // 30

// BiPredicate<T, U> — T və U qəbul edir, boolean qaytarır
BiPredicate<String, String> ilkHərfEyni =
    (s1, s2) -> s1.charAt(0) == s2.charAt(0);
System.out.println(ilkHərfEyni.test("Alma", "Armud")); // true
System.out.println(ilkHərfEyni.test("Alma", "Gilas")); // false

// BiConsumer<T, U> — T və U qəbul edir, void
BiConsumer<String, Integer> çapEt =
    (ad, yaş) -> System.out.println(ad + " — " + yaş + " yaş");
çapEt.accept("Əli", 25); // Əli — 25 yaş

// Map.forEach ilə tez-tez istifadə
Map<String, Integer> yaşlar = Map.of("Əli", 25, "Aysel", 30);
yaşlar.forEach((ad, yaş) -> System.out.println(ad + ": " + yaş));
// forEach(BiConsumer<K,V>) qəbul edir
```

---

## Operator Interfaces

```java
// UnaryOperator<T> — T qəbul edir, T qaytarır (Function<T,T>-nin xüsusi halı)
UnaryOperator<String> böyüklə = String::toUpperCase;
UnaryOperator<Integer> kare = n -> n * n;
UnaryOperator<List<String>> sırala = siyahı -> {
    List<String> yeni = new ArrayList<>(siyahı);
    Collections.sort(yeni);
    return yeni;
};

System.out.println(böyüklə.apply("salam")); // SALAM
System.out.println(kare.apply(5)); // 25

// BinaryOperator<T> — iki T qəbul edir, T qaytarır (BiFunction<T,T,T>-nin xüsusi halı)
BinaryOperator<Integer> çox = Integer::max;
BinaryOperator<String> birləş = String::concat;
BinaryOperator<List<Integer>> birləşdir = (l1, l2) -> {
    List<Integer> yeni = new ArrayList<>(l1);
    yeni.addAll(l2);
    return yeni;
};

System.out.println(çox.apply(10, 20)); // 20
System.out.println(birləş.apply("Sal", "am")); // Salam

// reduce() ilə tez-tez istifadə
List<Integer> rəqəmlər = List.of(1, 2, 3, 4, 5);
Optional<Integer> hasılat = rəqəmlər.stream()
    .reduce(BinaryOperator.identity()); // 1 qaytarır — sadə nümunə

int cəm = rəqəmlər.stream()
    .reduce(0, Integer::sum); // BinaryOperator olaraq Integer::sum
```

### Primitiv versiyalar

```java
// Boxing/unboxing olmadan — daha effektiv
IntUnaryOperator ikiQat = n -> n * 2;
IntBinaryOperator toplama = Integer::sum;
IntSupplier sabit = () -> 42;
IntConsumer çapEt = n -> System.out.println("Rəqəm: " + n);
IntPredicate müsbət = n -> n > 0;

// İstifadə
System.out.println(ikiQat.applyAsInt(5));           // 10
System.out.println(toplama.applyAsInt(3, 4));        // 7
System.out.println(sabit.getAsInt());                 // 42
çapEt.accept(100);                                    // Rəqəm: 100
System.out.println(müsbət.test(-5));                  // false

// Həmçinin Long və Double versiyaları var:
LongUnaryOperator, LongBinaryOperator, LongSupplier, LongConsumer, LongPredicate
DoubleUnaryOperator, DoubleBinaryOperator, DoubleSupplier, DoubleConsumer, DoublePredicate
```

---

## Method References — 4 Növ

```java
// ============================================
// 1. STATİK METOD REFERANSİ: ClassName::staticMethod
// ============================================
// Lambda: (args) -> ClassName.staticMethod(args)

Function<String, Integer> parseInt = Integer::parseInt;
BiFunction<Integer, Integer> maks = Integer::max;
Consumer<String> çap = System.out::println; // System.out — statik sahə

System.out.println(parseInt.apply("42")); // 42
System.out.println(maks.apply(10, 20));   // 20

// ============================================
// 2. XÜSUSİ INSTANS METOD REFERANSİ: instance::instanceMethod
// ============================================
// Lambda: (args) -> instance.method(args)

String prefix = "Salam, ";
Function<String, String> salamla = prefix::concat; // prefix.concat(s)
UnaryOperator<String> böyüklə = "".toUpperCase()::concat; // (mənasız, nümunə)

Printer printer = new Printer();
Consumer<String> çapEt = printer::çap; // printer.çap(s)

çapEt.accept("Test"); // printer obyektinin çap metodunu çağırır

// ============================================
// 3. İXTİYARİ INSTANS METOD REFERANSİ: ClassName::instanceMethod
// ============================================
// Lambda: (instance, args) -> instance.method(args)

Function<String, String> toLower = String::toLowerCase; // s -> s.toLowerCase()
Function<String, Integer> length = String::length;       // s -> s.length()
BiPredicate<String, String> startsWith = String::startsWith; // (s, pref) -> s.startsWith(pref)

System.out.println(toLower.apply("SALAM")); // salam
System.out.println(length.apply("Salam"));  // 5
System.out.println(startsWith.test("Salam", "Sal")); // true

// ============================================
// 4. CONSTRUCTOR REFERANSİ: ClassName::new
// ============================================
// Lambda: (args) -> new ClassName(args)

Supplier<ArrayList<String>> boşSiyahı = ArrayList::new;     // () -> new ArrayList<>()
Function<String, StringBuilder> sbYarat = StringBuilder::new; // s -> new StringBuilder(s)
BiFunction<String, Integer, String> tekrar = String::new;     // (s, n) — bu olmaz, misal üçün

ArrayList<String> siyahı = boşSiyahı.get();
StringBuilder sb = sbYarat.apply("Başlangıc");
System.out.println(sb); // Başlangıc
```

### Method References praktiki nümunə

```java
record Tələbə(String ad, int bal) {
    static boolean keçdiMi(Tələbə t) { return t.bal() >= 50; }
    boolean müvəffəqdirMi() { return bal >= 50; }
    String adıVer() { return ad; }
}

List<Tələbə> tələbələr = List.of(
    new Tələbə("Əli", 75),
    new Tələbə("Aysel", 45),
    new Tələbə("Murad", 60),
    new Tələbə("Leyla", 35)
);

// 1. Statik metod referansı
List<Tələbə> keçənlər1 = tələbələr.stream()
    .filter(Tələbə::keçdiMi) // Static: t -> Tələbə.keçdiMi(t)
    .collect(Collectors.toList());

// 2. İxtiyari instans metod referansı
List<Tələbə> keçənlər2 = tələbələr.stream()
    .filter(Tələbə::müvəffəqdirMi) // Instance: t -> t.müvəffəqdirMi()
    .collect(Collectors.toList());

// 3. İxtiyari instans — ad almaq
List<String> adlar = tələbələr.stream()
    .map(Tələbə::adıVer) // t -> t.adıVer()
    .collect(Collectors.toList());

// 4. Constructor
Supplier<Tələbə> defaultTələbə = () -> new Tələbə("Naməlum", 0);
// BiFunction<String, Integer, Tələbə> yaratmaq olar belə:
BiFunction<String, Integer, Tələbə> tələbəYarat = Tələbə::new;
Tələbə yeni = tələbəYarat.apply("Orxan", 80);
```

---

## Funksional Interface-lərin Birləşdirilməsi

```java
// Çoxlu funksiyaları birləşdirmək — Function chaining

// Pipeline qurmaq
Function<String, String> qiymətlandırma = ((Function<String, String>) String::trim)
    .andThen(String::toLowerCase)
    .andThen(s -> s.replaceAll("[^a-zA-Z0-9]", ""))
    .andThen(s -> s.substring(0, Math.min(s.length(), 50)));

System.out.println(qiymətlandırma.apply("  Həllo, DÜNYA! 2024  "));
// həllo,dünya!2024

// Predicate-ləri birləşdirmək
Predicate<String> yoxlamalar = ((Predicate<String>) Objects::nonNull)
    .and(Predicate.not(String::isBlank))
    .and(s -> s.length() >= 3)
    .and(s -> s.length() <= 50);

System.out.println(yoxlamalar.test(null));    // false
System.out.println(yoxlamalar.test(""));      // false
System.out.println(yoxlamalar.test("ab"));    // false (çox qısa)
System.out.println(yoxlamalar.test("Salam")); // true

// Consumer-ləri birləşdirmək
Consumer<String> log = s -> System.out.println("[LOG] " + s);
Consumer<String> audit = s -> System.out.println("[AUDIT] " + s);
Consumer<String> email = s -> System.out.println("[EMAIL] " + s);

Consumer<String> hamısı = log.andThen(audit).andThen(email);
hamısı.accept("İstifadəçi giriş etdi");
// [LOG] İstifadəçi giriş etdi
// [AUDIT] İstifadəçi giriş etdi
// [EMAIL] İstifadəçi giriş etdi
```

---

## Funksional Interface Xülasəsi

| Interface | Giriş | Çıxış | Metod |
|-----------|-------|-------|-------|
| `Function<T,R>` | T | R | `apply(T)` |
| `Predicate<T>` | T | boolean | `test(T)` |
| `Consumer<T>` | T | void | `accept(T)` |
| `Supplier<T>` | yoxdur | T | `get()` |
| `UnaryOperator<T>` | T | T | `apply(T)` |
| `BiFunction<T,U,R>` | T, U | R | `apply(T,U)` |
| `BiPredicate<T,U>` | T, U | boolean | `test(T,U)` |
| `BiConsumer<T,U>` | T, U | void | `accept(T,U)` |
| `BinaryOperator<T>` | T, T | T | `apply(T,T)` |

---

## İntervyu Sualları

**S: Funksional interface nədir? @FunctionalInterface annotation-ı məcburidirmi?**
C: Yalnız bir abstract metodu olan interface-dir. Annotation məcburi deyil — olmasa da lambda ilə istifadə olunur. Lakin annotation olduqda compiler ikinci abstract metod əlavə edilsə xəta verir — dokumentasiya və qorunma üçün faydalıdır.

**S: `Function.andThen()` ilə `Function.compose()` fərqi nədir?**
C: `f.andThen(g)` = `g(f(x))` — f əvvəl, g sonra. `f.compose(g)` = `f(g(x))` — g əvvəl, f sonra. Sadə formul: `andThen` soldan sağa, `compose` sağdan sola.

**S: `Predicate.and()`, `or()`, `negate()` metodları nə edir?**
C: `and(p2)` — hər ikisi true olmalıdır (&&); `or(p2)` — ən azı biri true (||); `negate()` — tərsi (!). `Predicate.not(p)` — Java 11-dən negate-nin static versiyası. Short-circuit evaluation var: `and`-da birinci false-sa ikinci yoxlanmır.

**S: Method reference-ın 4 növünü fərqləndir.**
C: 1) Statik: `Integer::parseInt` — ClassName::staticMethod; 2) Xüsusi instans: `printer::print` — konkret obyekt::method; 3) İxtiyari instans: `String::length` — ClassName::instanceMethod, stream elementinə tətbiq olunur; 4) Constructor: `ArrayList::new` — ClassName::new.

**S: `Consumer` vs `Function` fərqi nədir?**
C: `Consumer` void qaytarır — yalnız yan effekt üçün (log, çap, saxla). `Function` dəyər qaytarır — çevirici üçün. Stream-də: `forEach(Consumer)`, `map(Function)`.

**S: `Supplier` nə vaxt lazımdır?**
C: 1) Lazy initialization — dəyər yalnız lazım olduqda hesablanır; 2) `Optional.orElseGet(Supplier)` — boş Optional-da; 3) Exception mesajı: `orElseThrow(Supplier<Exception>)`; 4) Factory method pattern; 5) Test-lərdə mock.
