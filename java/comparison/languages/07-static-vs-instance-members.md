# Static və Instance Üzvlər (Members) Tam Bələdçi

> **Seviyye:** Beginner ⭐

## Giriş

Java-də hər sinfin **iki növ üzvü** (member) var: **instance** (obyektə aid) və **static** (sinfə aid). Instance üzv hər yeni `new` obyekti ilə yeni kopiya alır. Static üzv isə sinifin özünə aiddir -- yalnız bir kopiya var, bütün obyektlər paylaşır. Bu fərqi düzgün başa düşmək utility siniflər (Math, Collections), Singleton pattern və memory idarəetməsi üçün vacibdir. PHP-də də `static` keyword var, amma bir neçə incə fərq var (`self::` vs `static::` kimi). Bu fayl sıfırdan başlayanlar üçün hər iki dünyanı real kodla izah edir.

---

## Java-də istifadəsi

### Instance variable vs static variable

```java
public class Sayqac {
    private int instansSayi = 0;          // hər obyektdə ayrı kopiya
    private static int umumiObyektSayi;   // sinifə aid, bir dənə

    public Sayqac() {
        umumiObyektSayi++;   // hər yeni obyekt yaradılanda artır
    }

    public void art() {
        instansSayi++;
    }

    public int getInstansSayi() {
        return instansSayi;
    }

    public static int getUmumiObyektSayi() {
        return umumiObyektSayi;
    }
}
```

İstifadə:

```java
Sayqac a = new Sayqac();
Sayqac b = new Sayqac();
Sayqac c = new Sayqac();

a.art();
a.art();
b.art();

System.out.println(a.getInstansSayi());       // 2
System.out.println(b.getInstansSayi());       // 1
System.out.println(c.getInstansSayi());       // 0
System.out.println(Sayqac.getUmumiObyektSayi()); // 3
```

Yaddaşda:
- `a`, `b`, `c` üç fərqli obyekt. Hər birinin öz `instansSayi`-si var.
- `umumiObyektSayi` sinif yaddaşında bir yerdədir, hamı paylaşır.

### Instance method vs static method

```java
public class Riyazi {
    private double dəyər;

    public Riyazi(double d) { this.dəyər = d; }

    // Instance method -- `this` var, obyektə bağlıdır
    public double yarısı() {
        return this.dəyər / 2;
    }

    // Static method -- `this` YOXDUR, sinfə aid
    public static double topla(double a, double b) {
        return a + b;
    }
}

// İstifadə:
Riyazi r = new Riyazi(10);
r.yarısı();                 // 5.0 -- obyekt üzərindən
Riyazi.topla(3, 4);         // 7.0 -- sinif üzərindən
```

### `this` niyə static method-da işləmir

`this` -- cari obyektə işarədir. Static method heç bir obyektə bağlı deyil, sinifə aiddir. Obyekt olmayanda `this` nəyə işarə edəcək?

```java
public class Nümunə {
    private int x = 10;

    public static void sinaq() {
        // System.out.println(this.x);   // XƏTA!
        // System.out.println(x);        // XƏTA -- x instance field
    }

    public static void yaxsi(Nümunə obj) {
        System.out.println(obj.x);     // OK -- obyekt parametr kimi verildi
    }
}
```

### `static` initializer block

Sinif ilk yükləndikdə işə düşən blok. Mürəkkəb static initializatsiya üçün:

```java
public class Konfiqurasiya {
    public static final Map<String, String> PARAMETRLER;

    static {
        // Class loader sinfi JVM-ə yüklədikdə bir dəfə işləyir
        Map<String, String> map = new HashMap<>();
        map.put("port", "8080");
        map.put("host", "localhost");
        PARAMETRLER = Collections.unmodifiableMap(map);
        System.out.println("Konfiqurasiya yükləndi");
    }
}
```

Instance initializer block-u da var (`{...}` `static` olmadan) -- constructor-dan əvvəl hər obyekt yaradılanda işə düşür.

### Static nested class vs inner (non-static) class

```java
public class Xaric {
    private int x = 10;

    // Static nested class -- xaric instance-ə aid deyil
    public static class StatikDaxil {
        public void method() {
            // System.out.println(x);  // XƏTA -- instance field
        }
    }

    // Non-static inner class -- xaric instance-ə bağlıdır
    public class Daxil {
        public void method() {
            System.out.println(x);     // OK -- xaric obyektin x-inə girir
        }
    }
}

// İstifadə:
Xaric.StatikDaxil s = new Xaric.StatikDaxil();

Xaric x = new Xaric();
Xaric.Daxil d = x.new Daxil();   // xaric obyekt lazımdır
```

Detallı izah faylı 44-də.

### Utility sinifləri -- niyə hamısı static

JDK-nin bir çox sinfi static metodlardan ibarətdir, çünki state saxlamır:

```java
Math.max(3, 5);                       // static
Math.sqrt(16);                        // static
Collections.sort(list);               // static
Arrays.asList(1, 2, 3);               // static
Objects.requireNonNull(obj);          // static
Files.readString(Path.of("a.txt"));   // static (Java 11+)
```

Utility sinifini düzgün yazmaq qaydası:

```java
public final class Yardimci {      // final -- inherit olunmur
    private Yardimci() { }          // private constructor -- new oluna bilməz
    
    public static String böyükHərf(String s) {
        return s == null ? null : s.toUpperCase();
    }
}
```

### Singleton pattern və private constructor + static getInstance

```java
public class Konfiqurasiya {
    private static Konfiqurasiya instance;   // yeganə obyekt

    private Konfiqurasiya() { }              // xaricdən new mümkün deyil

    public static synchronized Konfiqurasiya getInstance() {
        if (instance == null) {
            instance = new Konfiqurasiya();
        }
        return instance;
    }

    public void xidmət() { }
}

// İstifadə:
Konfiqurasiya k = Konfiqurasiya.getInstance();
```

Modern Java-də enum singleton daha təhlükəsizdir:

```java
public enum KonfiqurasiyaEnum {
    INSTANCE;
    public void xidmət() { }
}

KonfiqurasiyaEnum.INSTANCE.xidmət();
```

### Static import

```java
import static java.lang.Math.PI;
import static java.lang.Math.sqrt;
import static java.util.Collections.sort;

public class Nümunə {
    public void method() {
        double r = sqrt(16);          // Math.sqrt əvəzinə
        System.out.println(PI);        // Math.PI əvəzinə
        sort(list);                    // Collections.sort əvəzinə
    }
}
```

Tövsiyə: çox istifadə etsəniz sinif adı itir, kod oxunaqlılığı pozulur. Ehtiyatla istifadə et.

### Memory implications

Static sahə **class loader scope-unda** qalır. Yəni sinif JVM-ə yükləndikdə yaddaşa düşür və classloader boşaldılana qədər qalır. Bu o deməkdir ki:

```java
public class Cache {
    // TƏHLÜKƏ! bu cache heç vaxt boşalmır
    private static final Map<String, byte[]> CACHE = new HashMap<>();

    public static void add(String key, byte[] data) {
        CACHE.put(key, data);
    }
}
```

Cache-ə nə qədər əlavə etsən, yaddaş şişir. Heç vaxt garbage collect olunmur. **Memory leak** mənbəyi!

Düzgün yanaşma: `WeakHashMap`, `SoftReference` və ya Caffeine kimi library istifadə et.

### Static state və test çətinliyi

Static variable global state yaradır -- unit test-lər arasında paylaşılır:

```java
public class UserService {
    private static int hasabSayi = 0;

    public static void loginEdildi() {
        hasabSayi++;
    }
}

// Test 1
@Test void t1() {
    UserService.loginEdildi();
    assertEquals(1, UserService.hasabSayi);   // OK
}

// Test 2
@Test void t2() {
    UserService.loginEdildi();
    assertEquals(1, UserService.hasabSayi);   // XƏTA: 2 oldu, test sırasından asılıdır
}
```

Static state test-i **təsadüfi** edir (flaky). Alternativ: dependency injection ilə instance state istifadə et.

### When to use static vs instance (qərar ağacı)

```
Metod obyekt state-inə (field-lərə) müraciət edirmi?
├── Bəli  -> Instance metod
└── Xeyr -> Static metod ola bilər
            │
            └── Obyekt polymorphism lazımdır? (override olunacaq)
                ├── Bəli  -> Instance metod (subclass override edə bilsin)
                └── Xeyr -> Static metod
```

**Nümunə:**

```java
// Static -- saf funksiya, state yoxdur
public static double radiansaÇevir(double dərəcə) {
    return dərəcə * Math.PI / 180;
}

// Instance -- obyektin state-inə bağlıdır
public class Dairə {
    private double radius;
    public double sahə() {
        return Math.PI * radius * radius;   // obyekt radius-una bağlıdır
    }
}
```

---

## PHP-də istifadəsi

### Instance və static property

```php
class Sayqac
{
    private int $instansSayi = 0;
    private static int $umumiObyektSayi = 0;

    public function __construct()
    {
        self::$umumiObyektSayi++;
    }

    public function art(): void
    {
        $this->instansSayi++;
    }

    public function getInstansSayi(): int
    {
        return $this->instansSayi;
    }

    public static function getUmumiObyektSayi(): int
    {
        return self::$umumiObyektSayi;
    }
}

$a = new Sayqac();
$b = new Sayqac();
$a->art();
echo $a->getInstansSayi();               // 1
echo Sayqac::getUmumiObyektSayi();       // 2
```

### PHP-də `::` sintaksisi

Java-də static üzv `.` ilə çağırılır, PHP-də `::` (double colon, "Paamayim Nekudotayim"):

```php
Sayqac::getUmumiObyektSayi();   // static metod
Sayqac::$umumiObyektSayi;       // static property
Sayqac::MEHDUD;                 // class sabit (const)
```

Java-də:

```java
Sayqac.getUmumiObyektSayi();
Sayqac.umumiObyektSayi;
Sayqac.MEHDUD;
```

### `self::` vs `static::` (late static binding)

PHP-nin Java-də olmayan xüsusiyyəti: `self::` kompilyasiya zamanı, `static::` runtime-da sinifi həll edir.

```php
class Ana
{
    public static function yarad(): static
    {
        return new static();    // hansı sinif new-olunacaq?
    }

    public static function yarad2(): self
    {
        return new self();      // həmişə Ana
    }
}

class Ogul extends Ana { }

$a = Ogul::yarad();    // Ogul obyekt (static:: subclass-a yönəlir)
$b = Ogul::yarad2();   // Ana obyekt (self:: hər zaman Ana)
```

Java-də bunun müqabili polymorphism ilə olur -- amma Java static metodları override oluna bilmir, ona görə bu pattern əsasən instance metodlarla həyata keçirilir.

### PHP-də static constructor olmur

```php
// PHP-də bu mümkün deyil:
// public static function __construct() { }

// Amma static initialization üçün factory metod istifadə olunur:
class Konfiqurasiya
{
    private static ?self $instance = null;

    private function __construct() { }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### Static və test çətinliyi (PHP-də də eyni problem)

```php
class UserService
{
    private static int $hasabSayi = 0;

    public static function loginEdildi(): void
    {
        self::$hasabSayi++;
    }
}
```

PHPUnit test-ləri arasında static state saxlanılır. PHPUnit-in `@backupStaticAttributes` annotation-u bu problemi həll edə bilər, amma ən yaxşı yol dependency injection-dur.

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Static field sintaksisi | `static int x` | `public static int $x` |
| Static metod çağırışı | `Sinif.metod()` | `Sinif::metod()` |
| `this` static metodda | Yoxdur | Yoxdur |
| Class daxilində static property | `this.x` (instance), `ClassName.x` (static) | `$this->x`, `self::$x`, `static::$x` |
| `self::` vs `static::` | Yoxdur (sabitdir) | Var (late static binding) |
| Static initializer block | Var (`static { ... }`) | Yoxdur (factory metod istifadə olunur) |
| Static nested class | Var | PHP-də "static class" konsepsiyası yoxdur |
| Static import | Var | `use function` ilə bənzər |
| Override static metod | Mümkün deyil (gizlədilir) | Override olunur (virtual kimi) |
| Memory scope | Classloader scope | PHP request scope (hər request sonunda təmizlənir) |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

1. **JVM class loading modeli:** Java siniflərini classloader yükləyir. Hər sinif yaddaşda bir dəfə saxlanılır. Static field bu yaddaşın hissəsidir. Bu, long-running process-lər üçün məntiqlidir.

2. **`.` notation C-dən gəlir:** C++/C#-də də `.` istifadə olunur. Java tanış sintaksis saxladı.

3. **Static metod override olmur:** Java-də static metod "virtual" deyil -- runtime-da sinif tipi qərar vermir, compile-time-da bilinən tip qərar verir. Bu, Java-nın dizayn seçimidir və polymorphism-in yalnız instance-da işləməsini nəzərdə tutur.

4. **Heavy enterprise state:** Spring kimi framework-lər static yerinə dependency injection ilə singleton yaradır -- test edilə bilən və konfiqurasiya oluna bilən.

### PHP-nin yanaşması

1. **Request scope yaddaşı:** PHP-nin ənənəvi modeli hər HTTP sorğusu üçün yeni process yaradır. Static field yalnız bir request ərzində qalır. Bu, memory leak-in qarşısını alır, amma uzunmüddətli cache üçün problem yaradır (bunun üçün APCu, Redis və s. istifadə olunur).

2. **`::` sintaksisi Perl-dən:** PHP-nin ilk versiyaları Perl-dən ilhamlanıb. `::` Perl-də class metod çağırışıdır.

3. **Late static binding ehtiyacı:** PHP-də static metod subclass-da override oluna bilər. Ona görə "hansı sinifin versiyası" sualı vacibdir. `static::` bu problemi həll edir.

4. **Laravel/Symfony Facade pattern:** Laravel-də `Route::get(...)`, `Cache::remember(...)` kimi çağırışlar var. Bunlar əslində arxa planda service container obyektini çağırır. Static görünüş, amma instance mexanizmi.

---

## Ümumi səhvlər (Beginner traps)

### 1. Static metoddan instance field-ə giriş cəhd etmək

```java
public class X {
    int a = 5;
    public static void m() {
        // System.out.println(a);   // XƏTA
    }
}
```

Səbəb: static metod çağırılanda obyekt olmaya bilər.

### 2. Static field-i hər obyekt üçün fərqli hesab etmək

```java
public class Say {
    static int total = 0;
    void art() { total++; }
}

Say a = new Say(); a.art();
Say b = new Say(); b.art();
System.out.println(a.total);  // 2 (ortaq)
System.out.println(b.total);  // 2 (ortaq)
```

### 3. Singleton-da thread safety unutmaq

```java
// PİS: double-check pattern yoxdur, race condition
public static Konfiqurasiya getInstance() {
    if (instance == null) {
        instance = new Konfiqurasiya();   // iki thread eyni vaxtda yarada bilər
    }
    return instance;
}

// YAXSI: synchronized və ya lazy holder pattern
private static class Holder {
    static final Konfiqurasiya INSTANCE = new Konfiqurasiya();
}
public static Konfiqurasiya getInstance() {
    return Holder.INSTANCE;
}
```

### 4. Static metodu "override" etməyə çalışmaq

```java
class Ana { public static void m() { System.out.println("Ana"); } }
class Ogul extends Ana { public static void m() { System.out.println("Ogul"); } }

Ana a = new Ogul();
a.m();   // "Ana" çap edir, çünki static metod runtime-da sinifə baxmır
```

### 5. Mutable static state -- test flaky

Yuxarıda izah edildi. Dependency injection istifadə et.

### 6. PHP-dən gələnlər: `::` yerinə `.` yazmaq

```java
// Sayqac.umumi()   // Java-də OK, PHP-də deyil
// Sayqac::umumi()  // PHP-də OK, Java-də deyil
```

---

## Mini müsahibə sualları

**1. `this` niyə static metodda işləmir?**

`this` cari obyektə işarə edir. Static metod sinfə aiddir, heç bir obyekt olmaya bilər (hətta `new` olmadan da çağırıla bilər). Obyekt olmadığı üçün `this` bağlana bilmir. Kompilyator xətası verir.

**2. Utility sinif niyə həmişə `final` və private constructor ilə yazılır?**

`final` -- inherit olunmasın, çünki utility metodları polymorphism tələb etmir. Private constructor -- xaricdən `new` edilə bilməsin, çünki state olmayan sinfin obyektini yaratmaq mənasızdır. Bu iki qayda sinfin yalnız static metod collection-u olduğunu təmin edir (məs. `Math`, `Collections`).

**3. Static state niyə unit test-lər üçün problemdir?**

Static dəyişənlər test-lər arasında paylaşılır. Birinci test dəyəri dəyişsə, ikinci test onu "təmiz" gözləyir, amma köhnə dəyər qalır. Nəticə: test nəticəsi sıradan asılıdır (flaky tests). Həll: dependency injection ilə instance state istifadə et, setUp/tearDown metodlarında state sıfırla.

**4. Java-də static metod override oluna bilərmi?**

Xeyr. Static metodlar virtual (dynamic dispatch) deyil. Subclass-da eyni imza ilə metod yazsan, bu **method hiding** adlanır, override deyil. Kompilyator hansı metodu çağıracağına baxdığı dəyişən tipinə görə qərar verir, runtime obyekt tipinə görə yox.

**5. PHP-nin `self::` və `static::` arasında fərqi nədir və bu Java-də varmı?**

`self::` kompilyasiya zamanı həll olunur -- həmişə yazılan sinifə işarə edir. `static::` runtime-da həll olunur (late static binding) -- çağırış edilən sinifə işarə edir. Java-də bu ayırım yoxdur çünki Java-də static metod override olunmur və `new static()` kimi sintaksis mövcud deyil.
