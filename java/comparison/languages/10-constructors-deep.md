# Konstruktorlar -- Dərin Bələdçi

> **Seviyye:** Beginner ⭐

## Giriş

**Konstruktor** (constructor) obyekt yaradılanda avtomatik çağırılan xüsusi metoddur. Onun işi obyektin sahələrini ilkin dəyərlərlə doldurmaq və obyekti "hazır" vəziyyətə gətirməkdir. Hər sinfin ən azı bir konstruktoru var -- yazmasanız, kompilyator default konstruktor yaradır.

Java və PHP-də konstruktorlar fərqli yazılır:
- Java-da konstruktor **sinif adı ilə eyni**-dir: `public İstifadəçi() { ... }`
- PHP-də isə **`__construct`** adlı magic metoddur: `public function __construct() { ... }`

Amma iş prinsipləri eynidir. Bu fayl konstruktorları sıfırdan izah edir: default, parametrli, overloading, chaining, factory metodlar, record-lar və daha çox.

---

## Java-da istifadəsi

### Default konstruktor (kompilyator yaratdığı)

Əgər siniflərdə heç bir konstruktor yazmasanız, Java kompilyatoru **parametrsiz default konstruktor** əlavə edir:

```java
public class İstifadəçi {
    String ad;
    int yaş;
}

// Kompilyator bunu əlavə edir:
// public İstifadəçi() { }  -- heç nə etmir

// İstifadə:
İstifadəçi u = new İstifadəçi();
u.ad = "Orxan";
u.yaş = 25;
```

**Vacib:** Əgər **özünüz** hər hansı konstruktor yazsanız (parametrli olsa belə), Java **default**-u silir:

```java
public class İstifadəçi {
    String ad;

    // Parametrli konstruktor yazdıq
    public İstifadəçi(String ad) {
        this.ad = ad;
    }
}

// İndi bu işləmir:
İstifadəçi u = new İstifadəçi();   // XƏTA! no-arg konstruktor yoxdur
```

Həll: Əl ilə parametrsiz konstruktor əlavə edin:

```java
public class İstifadəçi {
    String ad;

    public İstifadəçi() { }   // no-arg (parametrsiz)
    public İstifadəçi(String ad) {
        this.ad = ad;
    }
}
```

### No-arg konstruktor (özümüzün yazması)

"No-arg" = parametrsiz konstruktor. Əsasən JPA/Hibernate, Spring, Jackson kimi frameworks onu tələb edir -- obyekti yaradıb, sonra `setter`-lə doldururlar.

```java
public class Məhsul {
    private String ad;
    private double qiymət;

    public Məhsul() {
        // default dəyərlər
        this.ad = "Naməlum";
        this.qiymət = 0.0;
    }

    // setter/getter-lər ...
}

Məhsul m = new Məhsul();
m.setAd("Alma");
m.setQiymət(1.50);
```

### Parametrləşdirilmiş (Parameterized) konstruktor

```java
public class İstifadəçi {
    private String ad;
    private int yaş;
    private String email;

    public İstifadəçi(String ad, int yaş, String email) {
        this.ad = ad;
        this.yaş = yaş;
        this.email = email;
    }
}

İstifadəçi orxan = new İstifadəçi("Orxan", 25, "orxan@example.com");
```

Parametrli konstruktorla obyekt **dərhal hazır** olur -- setter çağırmaq lazım deyil.

### Constructor overloading (yüksək yüklənmə)

Bir sinifdə bir neçə konstruktor ola bilər -- fərqli parametr siyahıları ilə:

```java
public class İstifadəçi {
    String ad;
    int yaş;
    String rol;

    // 1. Parametrsiz
    public İstifadəçi() {
        this.ad = "Qonaq";
        this.yaş = 0;
        this.rol = "guest";
    }

    // 2. Yalnız ad
    public İstifadəçi(String ad) {
        this.ad = ad;
        this.yaş = 18;
        this.rol = "user";
    }

    // 3. Ad və yaş
    public İstifadəçi(String ad, int yaş) {
        this.ad = ad;
        this.yaş = yaş;
        this.rol = "user";
    }

    // 4. Hamısı
    public İstifadəçi(String ad, int yaş, String rol) {
        this.ad = ad;
        this.yaş = yaş;
        this.rol = rol;
    }
}

// İstifadə -- Java hansı konstruktoru çağıracağını parametrlərdən müəyyən edir
İstifadəçi a = new İstifadəçi();
İstifadəçi b = new İstifadəçi("Ali");
İstifadəçi c = new İstifadəçi("Vəli", 25);
İstifadəçi d = new İstifadəçi("Nigar", 30, "admin");
```

### Constructor chaining: this(...)

Yuxarıdakı kod təkrarla doludur. `this(...)` ilə daha təmiz:

```java
public class İstifadəçi {
    String ad;
    int yaş;
    String rol;

    // Əsas konstruktor (hamısını təyin edir)
    public İstifadəçi(String ad, int yaş, String rol) {
        this.ad = ad;
        this.yaş = yaş;
        this.rol = rol;
    }

    // Digərləri əsas konstruktoru çağırır
    public İstifadəçi(String ad, int yaş) {
        this(ad, yaş, "user");
    }

    public İstifadəçi(String ad) {
        this(ad, 18);     // bu, this(ad, 18, "user")-i çağırır
    }

    public İstifadəçi() {
        this("Qonaq");    // zəncir: this() -> this("Qonaq") -> this("Qonaq", 18) -> this("Qonaq", 18, "user")
    }
}
```

**Qayda:** `this(...)` konstruktorun **ilk sətri** olmalıdır.

### Constructor chaining: super(...)

İnheritance zamanı parent konstruktoru çağırmaq üçün:

```java
public class İşçi {
    String ad;
    double maaş;

    public İşçi(String ad, double maaş) {
        this.ad = ad;
        this.maaş = maaş;
    }
}

public class Menecer extends İşçi {
    int komandaÖlçüsü;

    public Menecer(String ad, double maaş, int komandaÖlçüsü) {
        super(ad, maaş);                  // parent konstruktoru
        this.komandaÖlçüsü = komandaÖlçüsü;
    }
}
```

### Copy konstruktor pattern

Bir obyektin kopyasını yaratmaq üçün:

```java
public class Nöqtə {
    double x;
    double y;

    // Adi konstruktor
    public Nöqtə(double x, double y) {
        this.x = x;
        this.y = y;
    }

    // Copy konstruktor
    public Nöqtə(Nöqtə başqa) {
        this.x = başqa.x;
        this.y = başqa.y;
    }
}

Nöqtə a = new Nöqtə(1.0, 2.0);
Nöqtə b = new Nöqtə(a);   // kopiya
// a və b eyni dəyərlərlə, amma fərqli obyektlərdir
```

Java-da **built-in copy konstruktor yoxdur** (C++-dakı kimi). Əl ilə yazılmalıdır.

### Static factory metodlar (alternativ)

Bəzən konstruktor əvəzinə statik metodlar daha yaxşıdır:

```java
public class Nöqtə {
    double x, y;

    private Nöqtə(double x, double y) {   // private! Xaricdən çağırıla bilməz
        this.x = x;
        this.y = y;
    }

    // Factory metodlar -- oxunaqlı adlar
    public static Nöqtə mərkəz() {
        return new Nöqtə(0, 0);
    }

    public static Nöqtə dekart(double x, double y) {
        return new Nöqtə(x, y);
    }

    public static Nöqtə qütb(double r, double θ) {
        return new Nöqtə(r * Math.cos(θ), r * Math.sin(θ));
    }
}

Nöqtə a = Nöqtə.mərkəz();
Nöqtə b = Nöqtə.dekart(3, 4);
Nöqtə c = Nöqtə.qütb(5, Math.PI / 4);
```

Üstünlüklər:
- **Adları** var -- `dekart()`, `qütb()` oxunaqlıdır
- **Kəşləmək** mümkündür -- həmişə yeni obyekt yaratmaq lazım deyil
- Subtype qaytara bilir

`List.of(...)`, `Optional.of(...)`, `Integer.valueOf(...)` hamısı factory metod nümunəsidir.

### Validasiya konstruktorda

Konstruktorda yoxlamalar edilə bilər:

```java
public class Yaş {
    private final int dəyər;

    public Yaş(int dəyər) {
        if (dəyər < 0) {
            throw new IllegalArgumentException("Yaş mənfi ola bilməz: " + dəyər);
        }
        if (dəyər > 150) {
            throw new IllegalArgumentException("Yaş 150-dən çox ola bilməz: " + dəyər);
        }
        this.dəyər = dəyər;
    }
}

Yaş y1 = new Yaş(25);      // OK
Yaş y2 = new Yaş(-5);      // IllegalArgumentException!
```

Bu patterni **fail-fast** deyirlər -- yanlış məlumat obyektə daxil olmaz.

### Record -- compact konstruktor (Java 14+)

Java 14-dən Record-lar gəldi. Bu qısa formadır:

```java
// Compact konstruktor -- yalnız validasiya
public record Yaş(int dəyər) {
    public Yaş {   // parametrlər yoxdur! (compact)
        if (dəyər < 0 || dəyər > 150) {
            throw new IllegalArgumentException("Yanlış yaş: " + dəyər);
        }
        // dəyər avtomatik təyin olunur, bizdən this.dəyər = dəyər gözləmir
    }
}

Yaş y = new Yaş(25);
System.out.println(y.dəyər());   // getter avtomatik yaradılır
```

Record haqqında ayrı fayl var.

### Builder pattern -- çox parametrli obyektlər üçün

Əgər 5+ parametr varsa, konstruktor oxunmaz olur:

```java
// Çox parametrli konstruktor -- pisdir
Ev e = new Ev("Bakı", 3, 2, true, false, "qırmızı", 2020, 150000.0);
// Hansı parametr hansıdır?
```

Builder pattern həllidir:

```java
public class Ev {
    private final String şəhər;
    private final int yataqOtağı;
    private final int hamam;
    private final boolean hovuz;
    private final boolean qaraj;

    private Ev(Builder b) {
        this.şəhər = b.şəhər;
        this.yataqOtağı = b.yataqOtağı;
        this.hamam = b.hamam;
        this.hovuz = b.hovuz;
        this.qaraj = b.qaraj;
    }

    public static class Builder {
        private String şəhər;
        private int yataqOtağı = 1;
        private int hamam = 1;
        private boolean hovuz = false;
        private boolean qaraj = false;

        public Builder şəhər(String ş) { this.şəhər = ş; return this; }
        public Builder yataqOtağı(int n) { this.yataqOtağı = n; return this; }
        public Builder hamam(int n) { this.hamam = n; return this; }
        public Builder hovuz(boolean h) { this.hovuz = h; return this; }
        public Builder qaraj(boolean q) { this.qaraj = q; return this; }

        public Ev build() { return new Ev(this); }
    }
}

// İstifadə -- oxunaqlı!
Ev e = new Ev.Builder()
    .şəhər("Bakı")
    .yataqOtağı(3)
    .hamam(2)
    .hovuz(true)
    .build();
```

Lombok `@Builder` ilə bu kodu avtomatik yazır.

---

## PHP-də istifadəsi

### __construct magic metod

```php
<?php
class İstifadəçi {
    public string $ad;
    public int $yaş;
    public string $rol;

    public function __construct(string $ad, int $yaş = 18, string $rol = 'user') {
        $this->ad = $ad;
        $this->yaş = $yaş;
        $this->rol = $rol;
    }
}

$u = new İstifadəçi('Orxan', 25, 'admin');
$g = new İstifadəçi('Qonaq');    // default yaş=18, rol='user'
```

**Fərq:** PHP default **parametr dəyərləri** dəstəkləyir:

```php
function __construct(string $ad, int $yaş = 18) { }
```

Java-da bu yoxdur -- bir neçə konstruktor yazmalısan (overloading).

### PHP-də overloading YOXDUR

Java-dan fərqli olaraq, PHP-də bir sinifdə **yalnız bir** `__construct` ola bilər:

```php
<?php
class İstifadəçi {
    public function __construct(string $ad) { }
    public function __construct(string $ad, int $yaş) { }  // XƏTA!
    // Cannot redeclare __construct()
}
```

Həll yolları:

### 1. Default parametrlər

```php
<?php
class İstifadəçi {
    public function __construct(
        string $ad,
        int $yaş = 18,
        string $rol = 'user'
    ) { }
}

new İstifadəçi('Ali');
new İstifadəçi('Vəli', 25);
new İstifadəçi('Nigar', 30, 'admin');
```

### 2. Statik factory metodlar

```php
<?php
class İstifadəçi {
    private function __construct(
        public string $ad,
        public int $yaş,
        public string $rol
    ) {}

    public static function qonaq(): self {
        return new self('Qonaq', 0, 'guest');
    }

    public static function admin(string $ad): self {
        return new self($ad, 30, 'admin');
    }

    public static function adi(string $ad, int $yaş = 18): self {
        return new self($ad, $yaş, 'user');
    }
}

$admin = İstifadəçi::admin('Orxan');
$qonaq = İstifadəçi::qonaq();
```

### 3. Constructor property promotion (PHP 8+)

PHP 8-də çox gözəl qısa sintaksis:

```php
<?php
class İstifadəçi {
    public function __construct(
        public string $ad,
        public int $yaş = 18,
        private string $rol = 'user'
    ) {
        // $this->ad = $ad;  avtomatik
        // $this->yaş = $yaş;  avtomatik
        // $this->rol = $rol;  avtomatik
    }
}

$u = new İstifadəçi('Orxan', 25);
```

Bu promotion Java-da **yalnız Record-larda** var:

```java
public record İstifadəçi(String ad, int yaş, String rol) { }
```

### 4. Parent::__construct()

PHP-də Java-dakı `super(...)` yerinə `parent::__construct(...)`:

```php
<?php
class Heyvan {
    public function __construct(public string $ad) {}
}

class It extends Heyvan {
    public function __construct(string $ad, public string $növ) {
        parent::__construct($ad);
    }
}

$it = new It('Rex', 'Alabay');
```

### 5. Validasiya konstruktorda

```php
<?php
class Yaş {
    public function __construct(public readonly int $dəyər) {
        if ($dəyər < 0 || $dəyər > 150) {
            throw new InvalidArgumentException("Yanlış yaş: $dəyər");
        }
    }
}

$y = new Yaş(25);  // OK
$z = new Yaş(-5);  // Exception
```

`readonly` (PHP 8.1+) Java-dakı `final`-a bənzəyir -- təyin ediləndən sonra dəyişmir.

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Konstruktor adı | Sinif adı ilə eyni | `__construct` |
| Default dəyərlər | Yoxdur (overloading lazım) | Var |
| Constructor overloading | Var (bir neçə konstruktor) | Yoxdur (bir __construct) |
| Constructor chaining | `this(...)` | Yoxdur (factory metod) |
| Property promotion | Yalnız Record | PHP 8+ hər sinifdə |
| Parent konstruktor | `super(...)` | `parent::__construct(...)` |
| Return tipi | Yoxdur (heç void deyil) | Yoxdur |
| `new` açar sözü | Məcburidir | Məcburidir |
| Default konstruktor | Kompilyator yaradır | Özü yaratmır (amma lazımdırsa PHP-də yoxdur, new Class() işləyir) |
| Validasiya | Adi if/throw | Adi if/throw |
| Builder pattern | Çox yaygın | Nadirdir |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java **statik tipli** və **overloading-i** dəstəkləyən dildir. Bir neçə konstruktor yazmaq təbiidir, çünki kompilyator parametr tiplərinə görə hansını çağıracağını müəyyən edə bilər:

```java
new İstifadəçi();
new İstifadəçi("Ali");
new İstifadəçi("Vəli", 25);
```

Hər üçü fərqli konstruktoru çağırır. Java default parametr dəstəkləmir, çünki overloading bu ehtiyacı ödəyir.

### PHP-nin yanaşması

PHP **dinamik tipli** dildir -- overloading-i təbii şəkildə dəstəkləmir (tiplər runtime-da olur). Ona görə default parametrlər əlavə olundu. `__construct` magic metod olması PHP-nin digər magic metodları (`__get`, `__set`, `__toString`) ilə ardıcıllıq təmin edir.

Constructor property promotion (PHP 8) Record və Kotlin data class-ın təsiri altında əlavə olundu -- boilerplate kodu azaltmaq üçün.

### Praktik tövsiyə

- **Laravel** və PHP-də konstruktor çox minimaldır -- DI container parametrləri həll edir.
- **Spring**-də isə konstruktor-əsaslı DI standartdır: parametrli konstruktor + `@Autowired` (bəzən opsiyaldır).
- Java-da sahələri `final` edin və konstruktorda təyin edin -- immutable obyekt yaradırsız.
- PHP-də `readonly` istifadə edin (PHP 8.1+).

---

## Ümumi səhvlər (Beginner traps)

### 1. Parametrli konstruktor yazdıqdan sonra default yox

```java
public class A {
    public A(int x) { }
}

A a = new A();   // XƏTA! no-arg konstruktor yoxdur
```

Həll: Əl ilə no-arg əlavə et.

### 2. this(...) və super() ardıcıllığı

```java
public A(int x) {
    this(x, 0);    // OK
    super();       // XƏTA -- this() artıq çağırılıb
}
```

### 3. Konstruktorda throw unutmaq

```java
public Yaş(int dəyər) {
    if (dəyər < 0) {
        System.out.println("Xəta");  // log yazır, amma obyekt yaradılır!
    }
    this.dəyər = dəyər;
}

Yaş y = new Yaş(-5);   // mənfi yaşlı obyekt yaradılır!
```

Həll:

```java
if (dəyər < 0) {
    throw new IllegalArgumentException("Yaş mənfi ola bilməz");
}
```

### 4. Obyekti konstruktorda kənarlıqa vermək (leak `this`)

```java
public class A {
    public A() {
        qlobalSiyahı.add(this);    // this hələ tam qurulmayıb!
        this.sahə = 5;
    }
}
```

Bu təhlükəlidir -- başqa thread-lar `this`-i yarımçıq vəziyyətdə görür.

### 5. Final sahəni konstruktorda initialize etməmək

```java
public class A {
    private final int x;
    // public A() { }   -- XƏTA! x heç vaxt təyin edilmir
}
```

Final sahələr ya elan olunarkən, ya da hər konstruktorda təyin olunmalıdır.

### 6. PHP-də `__construct` yazmağı unutmaq

```php
class A {
    public function construct() { }   // alt xətt YOXDUR!
    // Bu adi metoddur, konstruktor deyil.
}
```

Düzgün: `__construct` (iki alt xətt).

---

## Mini müsahibə sualları

**1. Java-da default konstruktor nə vaxt yaradılır?**

Cavab: Əgər sinifdə **heç bir** konstruktor yazılmayıbsa, kompilyator avtomatik parametrsiz (no-arg) default konstruktor yaradır. Bu konstruktor heç nə etmir -- sadəcə sahələri default dəyərlərlə (0, null, false) buraxır. Amma siz özünüz **hər hansı** konstruktor (parametrli olsa belə) yazsanız, kompilyator default-u yaratmır -- onda parametrsiz konstruktor lazımdırsa, əl ilə yazmalısınız.

**2. Constructor overloading və constructor chaining arasındakı fərq nədir?**

Cavab: **Overloading** bir sinifdə fərqli parametr siyahıları ilə bir neçə konstruktor yazmaqdır -- Java kompilyatoru arqumentlərə görə hansını çağıracağını seçir. **Chaining** isə bir konstruktorun digərini `this(...)` ilə çağırmasıdır -- kod təkrarını azaltmaq üçün. Overloading "neçə konstruktor var" sualıdır, chaining "bir-birini necə çağırır" sualıdır. İkisi birlikdə istifadə olunur: bir əsas konstruktor yazılır, digərləri `this(...)` ilə onu çağırır.

**3. Builder pattern nə vaxt istifadə olunur?**

Cavab: Əgər obyektin 4+ parametri varsa, xüsusən də onların çoxu opsiyaldırsa, konstruktor oxunmaz olur: `new Ev("Bakı", 3, 2, true, false, ...)` -- hansı parametr hansıdır? Builder pattern fluent API verir: `new Ev.Builder().şəhər("Bakı").hovuz(true).build()`. Hər metodun adı var, sıra fərq etmir, opsiyal sahələri yaza bilməzsən. Lombok `@Builder` annotation bu kodu avtomatik yaradır. PHP-də bu qədər istifadə olunmur, çünki default parametrlər və named arguments (PHP 8+) kifayət edir.

**4. PHP 8 constructor property promotion nədir?**

Cavab: PHP 8-dən əvvəl sahələri elan etmək və konstruktorda təyin etmək boilerplate idi: `public string $ad; function __construct(string $ad) { $this->ad = $ad; }`. Promotion bunu qısaldır: `function __construct(public string $ad) {}` -- PHP özü sahəni yaradır və təyin edir. Java-da oxşar xüsusiyyət **yalnız Record**-larda var: `record User(String name) {}`. Adi siniflərdə Java hələ də boilerplate tələb edir (Lombok `@AllArgsConstructor` istisna).
