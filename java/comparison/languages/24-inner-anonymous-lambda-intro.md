# Inner Class, Anonymous Class və Lambda-ya Giriş

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Java tarix boyu **sinifi sinif içində** yazmaq üçün bir neçə mexanizm təklif edib. Sıradan ən qədimi: **inner class**. Sonra **anonymous class** -- ad vermədən on-the-fly implementation. Ən yeni və ən qısası: **lambda** (Java 8+). Bu üç konsepsiya bir-birinin davamıdır -- Java zamanla daha qısa və daha rahat sintaksis yaradıb. PHP-də buna bənzər `anonymous class` (PHP 7+) və `closure` (anonymous function) var, amma Java-də `inner class` ayrı-ayrılıqda daha geniş və mürəkkəb qurulub. Bu fayl hər üç konsepti sıfırdan izah edir.

---

## Java-də istifadəsi

### Nested class-ın 4 növü

```java
public class Xaric {
    // 1. Static nested class
    public static class StatikNested { }

    // 2. Inner class (non-static)
    public class Inner { }

    // 3. Local class (metodun daxilində)
    public void m() {
        class Local { }
    }

    // 4. Anonymous class (adsız)
    Runnable r = new Runnable() {
        public void run() { }
    };
}
```

### 1. Static nested class

Xaric obyektinin instance-ına ehtiyacı yoxdur. Tamamilə müstəqil siniftir, sadəcə daxildədir (namespace təmizliyi üçün):

```java
public class Bank {
    public static class HesabBuilder {
        private String ad;
        public HesabBuilder setAd(String a) { this.ad = a; return this; }
        public Hesab build() { return new Hesab(ad); }
    }
}

// Yaratma:
Bank.HesabBuilder b = new Bank.HesabBuilder();
```

Tez-tez builder və utility sinifləri üçün istifadə olunur.

### 2. Inner (non-static) class -- xaric instance-ə bağlı

```java
public class Xaric {
    private int x = 10;

    public class Inner {
        public void goster() {
            System.out.println(x);             // xaric field-ə birbaşa çıxış
            System.out.println(Xaric.this.x);  // açıq forma
        }
    }
}

// Yaratma -- xaric obyekt LAZIMDIR:
Xaric xaric = new Xaric();
Xaric.Inner inner = xaric.new Inner();
inner.goster();  // 10
```

**Vacib:** inner class instance hər zaman xaric obyektə gizli reference saxlayır. Bu, memory leak-lərə səbəb ola bilər.

### 3. Local class -- metodun daxilində

```java
public class Nümunə {
    public void sinaq() {
        class Yardimci {
            void et() { System.out.println("lokal"); }
        }
        Yardimci y = new Yardimci();
        y.et();
    }
}
// Yardimci yalnız sinaq() daxilində görünür
```

### 4. Anonymous class -- on-the-fly implementation

Adsız -- yalnız bir dəfə istifadə olunan sinif. Sintaksis `new Interface() { ... }`:

```java
// Interface
interface Xoşgəldin {
    void salam(String ad);
}

// Anonymous class ilə implementation
Xoşgəldin x = new Xoşgəldin() {
    @Override
    public void salam(String ad) {
        System.out.println("Salam " + ad);
    }
};

x.salam("Orxan");  // "Salam Orxan"
```

Klassik nümunə: Swing GUI button handler (Java 8-dən əvvəl):

```java
button.addActionListener(new ActionListener() {
    @Override
    public void actionPerformed(ActionEvent e) {
        System.out.println("kliklendi");
    }
});
```

Eyni şeyi `Runnable` ilə thread üçün:

```java
Thread t = new Thread(new Runnable() {
    @Override
    public void run() {
        System.out.println("thread işləyir");
    }
});
t.start();
```

### Anonymous class-dan lambda-ya keçid (Java 8+)

Anonymous class çox ətraflıdır, yalnız bir metod üçün çox kod. Java 8 lambda-nı gətirdi:

```java
// Java 7 və əvvəl -- anonymous class
Runnable r1 = new Runnable() {
    @Override
    public void run() {
        System.out.println("salam");
    }
};

// Java 8+ -- lambda (eyni məna)
Runnable r2 = () -> System.out.println("salam");
```

Hər iki sintaksis **eyni** işi görür, amma lambda çox qısadır.

### Functional interface nədir?

Lambda yalnız bir metoddan ibarət interface-lərə təyin edilə bilər. Bu cür interface-lər **functional interface** adlanır:

```java
@FunctionalInterface
interface Mesaj {
    void goster(String s);
    // yalnız bir abstract metod olmalıdır
}

Mesaj m = s -> System.out.println(s);
m.goster("salam");
```

`@FunctionalInterface` annotation məcburi deyil, amma kompilyatora "bu interface yalnız bir metod saxlamalıdır" deyir.

Java-nın hazır functional interface-ləri (`java.util.function`):
- `Function<T, R>` -- T qəbul edir, R qaytarır
- `Consumer<T>` -- T qəbul edir, heç nə qaytarmır
- `Supplier<T>` -- T qaytarır
- `Predicate<T>` -- T qəbul edir, boolean qaytarır
- `BiFunction<T, U, R>` -- iki arqument

Detallı izah faylı 35-də.

### Lambda sintaksisi

```java
// 1. Heç bir parametr
Runnable r = () -> System.out.println("salam");

// 2. Bir parametr (mötərizə ola da bilər, olmaya da)
Consumer<String> c = s -> System.out.println(s);
Consumer<String> c2 = (s) -> System.out.println(s);

// 3. Bir neçə parametr -- mötərizə məcburi
BiFunction<Integer, Integer, Integer> cem = (a, b) -> a + b;

// 4. Tipli parametr
BiFunction<Integer, Integer, Integer> cem2 = (Integer a, Integer b) -> a + b;

// 5. Blok sintaksisi -- bir neçə sətir
Function<Integer, String> f = x -> {
    String nətc;
    if (x > 0) nətc = "müsbət";
    else if (x < 0) nətc = "mənfi";
    else nətc = "sıfır";
    return nətc;
};
```

Qısa vs blok sintaksis:
- `x -> x * 2` -- tək ifadə, return olmadan
- `x -> { return x * 2; }` -- blok, return məcburi

### Method reference `::` sintaksisi

Lambda bir metodu birbaşa çağırırsa, method reference ilə daha qısa:

```java
List<String> list = List.of("orxan", "elvin", "aynur");

// Lambda
list.forEach(s -> System.out.println(s));

// Method reference (static)
list.forEach(System.out::println);
```

4 növü var:

```java
// 1. Static metoda reference -- ClassName::staticMethod
Function<String, Integer> f1 = Integer::parseInt;
int x = f1.apply("42");   // 42

// 2. Instance metoda reference (konkret obyekt) -- instance::method
List<String> list = new ArrayList<>();
Consumer<String> c = list::add;
c.accept("salam");

// 3. Instance metoda reference (tip) -- ClassName::instanceMethod
Function<String, Integer> f2 = String::length;
int len = f2.apply("salam");   // 5

// 4. Constructor reference -- ClassName::new
Supplier<ArrayList<String>> s = ArrayList::new;
ArrayList<String> newList = s.get();
```

### Hansı vəziyyətdə hansı?

```
Metod gövdəsi nə qədər böyükdür?
├── Bir ifadə, mövcud metodu çağırır   -> method reference (::)
├── Bir ifadə, sadə ifadə              -> qısa lambda
├── Bir neçə sətir                     -> blok lambda
├── Bir neçə metod override lazımdır   -> anonymous class
├── Nested class-ı başqa yerdə də istifadə edəcəksən -> static nested class
└── Xaric instance field-ə giriş lazımdır -> inner (non-static) class
```

### Captured variables və "effectively final"

Lambda və anonymous class daxildə xaricdən olan lokal dəyişənlərə istinad edə bilər. Amma bu dəyişənlər **effectively final** olmalıdır (yəni dəyəri dəyişməməlidir):

```java
public void m() {
    int x = 10;
    Runnable r = () -> System.out.println(x);   // OK, x dəyişmir

    // int y = 10;
    // Runnable r2 = () -> System.out.println(y);
    // y = 20;   // XƏTA: y effectively final deyil

    String ad = "Orxan";
    // ad = "Elvin";   // olsaydı lambda pozulardı
    Runnable r3 = () -> System.out.println(ad);  // OK
}
```

Niyə? Çünki lambda dəyəri **kopiya** şəklində tutur. Əgər dəyişə bilsəydi, lambda-nın gördüyü dəyər ilə xaricdəki dəyər sinxron olmazdı.

Instance field bunu qayda ilə məhdudlaşdırmır:

```java
public class X {
    int y = 10;

    public void m() {
        Runnable r = () -> System.out.println(y);
        y = 20;
        r.run();   // 20 çap edir -- instance field-dir, effectively final qaydası yoxdur
    }
}
```

### Lambda-da `this` -- mühüm tələ

Anonymous class-da `this` anonymous class-ın özünə aiddir. Lambda-da isə `this` **xaric sinfə** aiddir:

```java
public class Test {
    private String ad = "Xaric";

    public void sinaq() {
        // Anonymous class -- this anonymous class-a aiddir
        Runnable r1 = new Runnable() {
            private String ad = "Anonim";
            @Override
            public void run() {
                System.out.println(this.ad);  // "Anonim"
            }
        };

        // Lambda -- this xaric sinfə aiddir
        Runnable r2 = () -> System.out.println(this.ad);  // "Xaric"

        r1.run();  // Anonim
        r2.run();  // Xaric
    }
}
```

Bu, lambda-nı thread-də və ya callback-də istifadə edərkən diqqətli olmaq lazım olan bir fərqdir.

---

## PHP-də istifadəsi

### PHP-də inner class konsepti yoxdur

PHP-də Java-dəki "inner class" yoxdur. Ancaq namespace və nested namespace var:

```php
namespace App\Bank;

class Hesab { }
class HesabBuilder { }  // Ayrı sinif, amma eyni namespace
```

### PHP anonymous class (PHP 7+)

Java-nın anonymous class-ına bənzəyir:

```php
interface Mesaj
{
    public function goster(string $s): void;
}

$m = new class implements Mesaj {
    public function goster(string $s): void
    {
        echo $s;
    }
};

$m->goster("salam");
```

Argumentlər ötürmək olar:

```php
$ad = "Orxan";
$obj = new class($ad) {
    public function __construct(public string $ad) {}
    public function goster(): void {
        echo $this->ad;
    }
};
$obj->goster();  // "Orxan"
```

### PHP Closure (Anonymous function)

PHP-də "closure" Java-nın lambda-sının müqabilidir:

```php
// Anonymous function
$salam = function(string $ad) {
    return "Salam, $ad";
};
echo $salam("Orxan");  // Salam, Orxan

// Xaricdən dəyişən tutmaq üçün `use` keyword
$prefix = "Cənab";
$formal = function(string $ad) use ($prefix) {
    return "$prefix $ad";
};
echo $formal("Əliyev");   // Cənab Əliyev
```

### Arrow function (PHP 7.4+)

Daha qısa closure. Java-nın lambda-sına daha çox oxşayır:

```php
$cem = fn($a, $b) => $a + $b;
echo $cem(3, 5);   // 8

// Xaricdən dəyişəni avtomatik tutur (use lazım deyil)
$vergi = 0.18;
$ümumi = fn($qiymet) => $qiymet * (1 + $vergi);
echo $ümumi(100);   // 118
```

Amma arrow function yalnız **bir ifadə** ola bilər, blok yoxdur.

### first-class callable (PHP 8.1+)

Java-nın method reference (`::`) müqabili:

```php
class Hesablayici
{
    public function topla(int $a, int $b): int { return $a + $b; }
    public static function cixar(int $a, int $b): int { return $a - $b; }
}

$h = new Hesablayici();
$topla = $h->topla(...);         // callable
$cixar = Hesablayici::cixar(...); // callable
$parse = strtoupper(...);         // built-in funksiya

echo $topla(3, 5);   // 8
echo $parse("abc");  // "ABC"
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Static nested class | Var | Yoxdur (namespace istifadə olunur) |
| Inner (non-static) class | Var | Yoxdur |
| Local class | Var | Yoxdur |
| Anonymous class | Var | Var (PHP 7+) |
| Lambda sintaksisi | `() -> {}` (Java 8+) | `function() {}`, `fn() =>` |
| Capturing outside variable | Effectively final avtomatik | `use (...)` ilə, arrow-da avtomatik |
| `this` lambda daxilində | Xaric sinif | Closure obyekti (bind olunmuş) |
| Method reference | `::` | `$obj->metod(...)`, `Class::metod(...)` |
| Constructor reference | `Class::new` | Yoxdur (factory function yazılır) |
| Blok ifadə lambda-da | `{ return ...; }` | `function() { return ...; }` |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

1. **Ad məkanı məhdudluqları:** Java-nın ilk versiyalarında namespace yoxdu, yalnız paket var idi. Ona görə nested class zəruri idi -- bir konsepsiyaya aid siniflərin birlikdə saxlanması.

2. **Swing və GUI dövrü:** Java 1.1-də inner class əlavə edildi. Məqsəd: GUI event handler-ləri sadələşdirmək. Sonradan anonymous class bunu daha da qısaltdı.

3. **Lambda və functional paradigm (Java 8):** Scala, Clojure, Python functional paradigma göstərdikdə Java da bu yolu tutdu. Lambda + Stream API Java-nı yenidən populyar etdi.

4. **Method reference performansı:** `String::length` kimi yazılış yalnız qısa olmaq üçün deyil -- JIT daha yaxşı optimize edə bilir.

### PHP-nin yanaşması

1. **Namespace sadəliyi:** PHP 5.3-də namespace gəldi və inner class ehtiyacını sərtə etmədi. Hər sinif öz faylında, namespace-də.

2. **Closure PHP 5.3-də əlavə edildi:** Java 8-dən 5 il əvvəl. Amma `use` keyword ilə manual capture məhdudlayıcı idi.

3. **Arrow function gecikdi:** PHP 7.4-də 2019-cu ildə gəldi. JavaScript-in `()=>` sintaksisindən təsirləndi.

4. **PHP web template mirası:** PHP-nin əsas istifadəsi dinamik HTML şablonları idi. GUI event handler kimi inner class ehtiyacı yox idi.

---

## Ümumi səhvlər (Beginner traps)

### 1. Lambda-da effectively final qaydasını unutmaq

```java
int sayi = 0;
List.of(1,2,3).forEach(x -> sayi += x);  // XƏTA: sayi effectively final deyil

// Həll: AtomicInteger və ya stream.reduce
AtomicInteger sayi2 = new AtomicInteger(0);
List.of(1,2,3).forEach(x -> sayi2.addAndGet(x));

int cem = List.of(1,2,3).stream().mapToInt(Integer::intValue).sum();
```

### 2. Lambda-da `this` qarışdırmaq

```java
public class Test {
    String ad = "Test";
    public void m() {
        Runnable r = () -> System.out.println(this.ad);  // "Test" -- xaric sinif
    }
}
```

Anonymous class-da `this` ayrı olduğu üçün yeni başlayanlar səhv salırlar.

### 3. Anonymous class-ı memory leak yaratması

```java
public class Xaric {
    private byte[] bigData = new byte[100_000_000];

    public Runnable yarad() {
        return new Runnable() {
            public void run() {
                System.out.println("salam");
            }
        };
    }
    // Qaytarılan Runnable Xaric obyektinə gizli reference saxlayır
    // bigData heç vaxt GC olunmur, əgər Runnable uzun yaşasa
}
```

Həll: static nested class və ya lambda (əgər field-ə ehtiyac yoxdursa).

### 4. Method reference-də qarışıqlıq

```java
List<String> list = List.of("a", "b");

// String::toUpperCase -- hər element üçün s.toUpperCase() çağırılır
list.stream().map(String::toUpperCase).forEach(System.out::println);

// "abc"::concat -- bütün elementlərə "abc".concat(s) çağırılır (fərqli!)
```

### 5. Inner class-ı xaric obyekt olmadan yaratmağa çalışmaq

```java
public class Xaric {
    public class Inner { }
}

// new Xaric.Inner();      // XƏTA: xaric instance lazımdır
Xaric x = new Xaric();
Xaric.Inner i = x.new Inner();  // DÜZGÜN
```

### 6. PHP-dən gələnlər: lambda-da `use` axtarmaq

```java
// Java-də `use` yoxdur
int x = 10;
Runnable r = () -> System.out.println(x);   // avtomatik capture
```

PHP-də:
```php
$x = 10;
$r = function() use ($x) { echo $x; };   // `use` məcburi (arrow-da yox)
```

---

## Mini müsahibə sualları

**1. Anonymous class ilə lambda arasında əsas fərqlər nədir?**

Anonymous class yeni sinif yaradır (hər instance üçün yeni class file), istənilən sayda metod ola bilər, `this` özünə aiddir, öz field-ləri ola bilər. Lambda yalnız functional interface (bir metodlu) üçün işləyir, `this` xaric sinfə aiddir, field yaratmaq olmur, JVM tərəfindən invokedynamic ilə daha effektiv icra olunur.

**2. "Effectively final" nə deməkdir?**

Dəyişən `final` kimi elan edilməyib, amma dəyəri heç bir yerdə dəyişdirilmir. Lambda və anonymous class yalnız belə dəyişənləri capture edə bilər. Kompilyator avtomatik yoxlayır. Əgər dəyər dəyişilərsə, kompilyasiya xətası verilir.

**3. `List<String>::forEach` ilə `System.out::println` method reference necə işləyir?**

`System.out::println` bir `Consumer<String>` functional interface-ə çevrilir. `forEach` hər element üçün `println(element)` çağırır. Beləliklə, `list.forEach(System.out::println)` == `list.forEach(s -> System.out.println(s))`. Method reference daha qısadır və JIT tərəfindən daha yaxşı optimize olunur.

**4. Lambda daxilində `this` niyə anonymous class-dakıdan fərqlidir?**

Lambda kompilyator tərəfindən yeni sinif kimi yox, functional interface-ə bağlanmış bir metod kimi generate olunur. `this` keyword kontekstini dəyişmir -- lambda yazılan metodun sinifinə aid qalır. Anonymous class isə əslində yeni sinifdir, `this` də yeni sinifin obyektinə aiddir.

**5. PHP-də inner class niyə yoxdur və alternativi nədir?**

PHP dizaynı namespace-ə əsaslanır, ona görə "sinif içində sinif" semantik baxımdan lazım sayılmır. Eyni namespace-də əlaqəli siniflər saxlanılır. PHP 7-dən anonymous class əlavə olundu -- on-the-fly implementation üçün. Java-nın static nested class-ına ən yaxın PHP analoqu: eyni namespace-də iki ayrı sinif yazmaqdır.
