# OOP: Siniflər və İnterfeyslar

> **Seviyye:** Beginner ⭐

## Giris

Java və PHP hər ikisi obyekt yönümlü proqramlaşdırmanı (OOP) dəstəkləyir, lakin yanaşmaları fərqlidir. Java sıfırdan OOP dili kimi yaradılıb -- Java-da hər şey sinif daxilindədir. PHP isə prosedural dil kimi başlayıb, OOP dəstəyini sonradan əlavə edib (əsasən PHP 5-dən). Bu tarixçə hər iki dilin OOP həllərini formalaşdırıb.

---

## Java-da istifadəsi

### Siniflər (Classes)

```java
// Java-da hər sinif ayrı faylda olmalıdır (public sinif üçün)
// Fayl adı: Istifadeci.java

public class Istifadeci {
    // Sahələr (fields) -- giriş modifikatorları ilə
    private String ad;
    private String email;
    private int yash;

    // Konstruktor
    public Istifadeci(String ad, String email, int yash) {
        this.ad = ad;
        this.email = email;
        this.yash = yash;
    }

    // Overloaded konstruktor (bir neçə konstruktor ola bilər)
    public Istifadeci(String ad, String email) {
        this(ad, email, 0);  // Digər konstruktoru çağırır
    }

    // Getter və Setter metodları
    public String getAd() {
        return ad;
    }

    public void setAd(String ad) {
        this.ad = ad;
    }

    public String getEmail() {
        return email;
    }

    // toString -- obyekti string kimi təmsil edir
    @Override
    public String toString() {
        return "Istifadeci{ad='%s', email='%s', yash=%d}"
            .formatted(ad, email, yash);
    }
}
```

### Record sinifləri (Java 16+)

```java
// Sadə data daşıyıcı siniflər üçün record istifadə olunur
// Konstruktor, getter, equals, hashCode, toString avtomatik yaranır
public record Istifadeci(String ad, String email, int yash) {
    // Compact constructor -- validasiya üçün
    public Istifadeci {
        if (ad == null || ad.isBlank()) {
            throw new IllegalArgumentException("Ad boş ola bilməz");
        }
    }
}

// İstifadəsi:
var user = new Istifadeci("Orxan", "orxan@mail.com", 25);
System.out.println(user.ad());    // "Orxan" -- getter, amma get prefiksi yoxdur
System.out.println(user);         // Istifadeci[ad=Orxan, email=orxan@mail.com, yash=25]
```

### İnterfeyslar (Interfaces)

```java
public interface Oxuna_bilen {
    // Abstract metod -- gövdəsi yoxdur, sinif tərəfindən həyata keçirilməlidir
    String oxu();

    // Default metod (Java 8+) -- gövdəsi var, override etmək isteğe bağlıdır
    default String formatlaOxu() {
        return "[OXUNDU] " + oxu();
    }

    // Static metod (Java 8+)
    static Oxuna_bilen bosh() {
        return () -> "";  // Lambda ilə
    }

    // Private metod (Java 9+) -- yalnız interfeys daxilində istifadə
    private String daxiliFormat(String metn) {
        return metn.trim().toLowerCase();
    }
}

public interface Yazila_bilen {
    void yaz(String mezmun);
}

// Bir sinif bir neçə interfeys həyata keçirə bilər
public class Fayl implements Oxuna_bilen, Yazila_bilen {
    private String mezmun = "";

    @Override
    public String oxu() {
        return mezmun;
    }

    @Override
    public void yaz(String mezmun) {
        this.mezmun = mezmun;
    }
}
```

### Abstrakt siniflər (Abstract Classes)

```java
public abstract class Fiqur {
    // Adi sahə
    protected String reng;

    // Konstruktor -- abstrakt sinifin konstruktoru ola bilər
    public Fiqur(String reng) {
        this.reng = reng;
    }

    // Abstrakt metod -- alt siniflər həyata keçirməlidir
    public abstract double sahe();
    public abstract double perimetr();

    // Adi metod -- miras qalır
    public String melumat() {
        return "%s fiqur, sahə: %.2f".formatted(reng, sahe());
    }
}

public class Dairə extends Fiqur {
    private double radius;

    public Dairə(String reng, double radius) {
        super(reng);  // Üst sinif konstruktorunu çağırır
        this.radius = radius;
    }

    @Override
    public double sahe() {
        return Math.PI * radius * radius;
    }

    @Override
    public double perimetr() {
        return 2 * Math.PI * radius;
    }
}
```

### Giriş modifikatorları (Access Modifiers)

```java
public class NümunəSinif {
    public String herkesUcun;        // Hər yerdən əlçatandır
    protected String mirasUcun;      // Eyni paket + alt siniflər
    String paketDaxili;              // Yalnız eyni paket (default/package-private)
    private String gizli;            // Yalnız bu sinif daxilində
}
```

Java-da 4 səviyyə var:

| Modifikator | Sinif daxili | Eyni paket | Alt sinif | Hər yerdən |
|---|---|---|---|---|
| `private` | Bəli | Xeyr | Xeyr | Xeyr |
| _(default)_ | Bəli | Bəli | Xeyr | Xeyr |
| `protected` | Bəli | Bəli | Bəli | Xeyr |
| `public` | Bəli | Bəli | Bəli | Bəli |

### Statik üzvlər (Static Members)

```java
public class Sayac {
    // Statik sahə -- bütün obyektlər üçün ortaq
    private static int umumi = 0;

    // Statik blok -- sinif yüklənərkən bir dəfə çalışır
    static {
        System.out.println("Sayac sinifi yükləndi");
    }

    // Statik metod
    public static int getUmumi() {
        return umumi;
    }

    // Statik fabrik metod
    public static Sayac yarat() {
        umumi++;
        return new Sayac();
    }
}

// İstifadəsi:
Sayac s1 = Sayac.yarat();
Sayac s2 = Sayac.yarat();
System.out.println(Sayac.getUmumi()); // 2
```

### final keyword

```java
// final sinif -- miras alına bilməz
public final class Dəyişməz {
    // final sahə -- dəyişdirilə bilməz
    private final String dəyər;

    public Dəyişməz(String dəyər) {
        this.dəyər = dəyər;
    }

    // final metod -- override edilə bilməz
    public final String getDəyər() {
        return dəyər;
    }
}

// public class AltSinif extends Dəyişməz {} // XƏTA! final sinif miras alınmaz
```

### Sealed siniflər (Java 17+)

Sealed siniflər hansı siniflərin miras ala biləcəyini məhdudlaşdırır:

```java
// Yalnız permits-də göstərilən siniflər miras ala bilər
public sealed class Ödəniş permits KartÖdənişi, NağdÖdəniş, TransferÖdəniş {
    protected double məbləğ;

    public Ödəniş(double məbləğ) {
        this.məbləğ = məbləğ;
    }
}

// final -- daha da miras alına bilməz
public final class KartÖdənişi extends Ödəniş {
    private String kartNömrəsi;

    public KartÖdənişi(double məbləğ, String kartNömrəsi) {
        super(məbləğ);
        this.kartNömrəsi = kartNömrəsi;
    }
}

// sealed -- öz alt siniflərini məhdudlaşdıra bilər
public sealed class NağdÖdəniş extends Ödəniş permits ManatÖdənişi, DollarÖdənişi {
    public NağdÖdəniş(double məbləğ) {
        super(məbləğ);
    }
}

// non-sealed -- məhdudiyyətsiz miras
public non-sealed class TransferÖdəniş extends Ödəniş {
    public TransferÖdəniş(double məbləğ) {
        super(məbləğ);
    }
}

// Sealed + pattern matching ilə güclü switch
public String ödənişTipi(Ödəniş ö) {
    return switch (ö) {
        case KartÖdənişi k -> "Kart: " + k.getKartNömrəsi();
        case NağdÖdəniş n -> "Nağd";
        case TransferÖdəniş t -> "Transfer";
    };
    // Bütün hallar örtüldüyü üçün default lazım deyil!
}
```

**PHP-də sealed siniflərin ekvivalenti yoxdur.** PHP-də istənilən sinif istənilən sinfi miras ala bilər (əgər `final` deyilsə).

---

## PHP-də istifadəsi

### Siniflər (Classes)

```php
class Istifadeci
{
    // PHP 8.0+ constructor property promotion
    // Konstruktor parametrləri avtomatik sahəyə çevrilir
    public function __construct(
        private string $ad,
        private string $email,
        private int $yash = 0,
    ) {}

    // Getter
    public function getAd(): string
    {
        return $this->ad;
    }

    // Setter
    public function setAd(string $ad): void
    {
        $this->ad = $ad;
    }

    // PHP-nin toString ekvivalenti
    public function __toString(): string
    {
        return "Istifadeci{{$this->ad}, {$this->email}, {$this->yash}}";
    }
}

// İstifadəsi:
$user = new Istifadeci('Orxan', 'orxan@mail.com', 25);
echo $user->getAd();     // "Orxan"
echo $user;              // Istifadeci{Orxan, orxan@mail.com, 25}
```

### Readonly Properties (PHP 8.1+) və Readonly Classes (PHP 8.2+)

```php
// Readonly property -- yalnız bir dəfə təyin edilə bilər
class Nöqtə
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
    ) {}
}

$n = new Nöqtə(3.0, 4.0);
echo $n->x;    // 3.0
// $n->x = 5.0;  // XƏTA! readonly property dəyişdirilə bilməz

// Readonly class -- bütün sahələr avtomatik readonly olur
readonly class Koordinat
{
    public function __construct(
        public float $en,
        public float $uzun,
    ) {}
}
```

### İnterfeyslar (Interfaces)

```php
interface OxunaBilen
{
    // Yalnız abstrakt metodlar (gövdəsiz)
    public function oxu(): string;

    // PHP interfeyslarında default metod YOXDUR (Java-dan fərqli!)
    // PHP interfeyslarında sabit ola bilər
    public const MAX_OLCU = 1024;
}

interface YazilaBilen
{
    public function yaz(string $mezmun): void;
}

// Bir sinif bir neçə interfeys həyata keçirə bilər
class Fayl implements OxunaBilen, YazilaBilen
{
    private string $mezmun = '';

    public function oxu(): string
    {
        return $this->mezmun;
    }

    public function yaz(string $mezmun): void
    {
        $this->mezmun = $mezmun;
    }
}

// İnterfeys interfeysı genişləndirə bilər
interface OxuYazBilen extends OxunaBilen, YazilaBilen
{
    public function sil(): void;
}
```

### Abstrakt siniflər (Abstract Classes)

```php
abstract class Fiqur
{
    public function __construct(
        protected string $reng,
    ) {}

    // Abstrakt metodlar
    abstract public function sahe(): float;
    abstract public function perimetr(): float;

    // Adi metod
    public function melumat(): string
    {
        return sprintf('%s fiqur, sahə: %.2f', $this->reng, $this->sahe());
    }
}

class Dairə extends Fiqur
{
    public function __construct(
        string $reng,
        private float $radius,
    ) {
        parent::__construct($reng);
    }

    public function sahe(): float
    {
        return M_PI * $this->radius ** 2;
    }

    public function perimetr(): float
    {
        return 2 * M_PI * $this->radius;
    }
}
```

### Giriş modifikatorları (Access Modifiers)

PHP-də 3 giriş modifikatoru var (Java-dan fərqli olaraq "package-private" yoxdur):

```php
class NümunəSinif
{
    public string $herkesUcun;       // Hər yerdən əlçatandır
    protected string $mirasUcun;     // Bu sinif + alt siniflər
    private string $gizli;          // Yalnız bu sinif daxilində
}
```

| Modifikator | Sinif daxili | Alt sinif | Hər yerdən |
|---|---|---|---|
| `private` | Bəli | Xeyr | Xeyr |
| `protected` | Bəli | Bəli | Xeyr |
| `public` | Bəli | Bəli | Bəli |

### Statik üzvlər

```php
class Sayac
{
    private static int $umumi = 0;

    public static function getUmumi(): int
    {
        return self::$umumi;
        // və ya static::$umumi (late static binding üçün)
    }

    public static function yarat(): self
    {
        self::$umumi++;
        return new self();
    }
}

// İstifadəsi:
$s1 = Sayac::yarat();
$s2 = Sayac::yarat();
echo Sayac::getUmumi(); // 2
```

**self:: vs static:: fərqi (Late Static Binding):**

```php
class Valideyn
{
    public static function yarat(): static  // static qaytarma tipi
    {
        return new static();  // Çağıran sinfi yaradır
    }

    public function sinifAdi(): string
    {
        return static::class;  // Əsl sinif adını qaytarır
    }
}

class Uşaq extends Valideyn {}

$obj = Uşaq::yarat();           // Uşaq obyekti qaytarır (static sayəsində)
echo $obj->sinifAdi();          // "Uşaq"
// self istifadə etsəydik, "Valideyn" qaytarardı
```

### final keyword

```php
// final sinif
final class Dəyişməz
{
    public function __construct(
        private readonly string $dəyər,
    ) {}

    // final metod
    final public function getDəyər(): string
    {
        return $this->dəyər;
    }
}

// class AltSinif extends Dəyişməz {} // XƏTA! final sinif miras alınmaz
```

### Traits (PHP-yə xas, Java-da ekvivalenti yoxdur)

Trait -- kodun bir neçə sinif arasında paylaşılması mexanizmidir. PHP tək miras (single inheritance) dəstəklədiyinə görə, trait-lər çoxlu miras ehtiyacını ödəyir:

```php
trait Vaxtİzləyici
{
    private ?DateTimeImmutable $yaradilmaVaxti = null;
    private ?DateTimeImmutable $yenilenmVaxti = null;

    public function yaradilmaVaxtiniQur(): void
    {
        $this->yaradilmaVaxti = new DateTimeImmutable();
    }

    public function yenilenmVaxtiniQur(): void
    {
        $this->yenilenmVaxti = new DateTimeImmutable();
    }

    public function getYaradilmaVaxti(): ?DateTimeImmutable
    {
        return $this->yaradilmaVaxti;
    }
}

trait YumshaqSilme
{
    private ?DateTimeImmutable $silinmVaxti = null;

    public function sil(): void
    {
        $this->silinmVaxti = new DateTimeImmutable();
    }

    public function silinibmi(): bool
    {
        return $this->silinmVaxti !== null;
    }
}

// Bir sinif bir neçə trait istifadə edə bilər
class Məqalə
{
    use Vaxtİzləyici;
    use YumshaqSilme;

    public function __construct(
        private string $başlıq,
        private string $məzmun,
    ) {
        $this->yaradilmaVaxtiniQur();
    }
}

$m = new Məqalə('Salam', 'Məzmun');
echo $m->getYaradilmaVaxti()->format('Y-m-d'); // "2026-04-11"
$m->sil();
echo $m->silinibmi(); // true
```

**Trait konfliktlərinin həlli:**

```php
trait A
{
    public function salam(): string
    {
        return 'Salam A-dan';
    }
}

trait B
{
    public function salam(): string
    {
        return 'Salam B-dən';
    }
}

class Sinif
{
    use A, B {
        A::salam insteadof B;    // A-nın metodunu istifadə et
        B::salam as salamB;      // B-nin metodunu başqa adla saxla
    }
}

$obj = new Sinif();
echo $obj->salam();    // "Salam A-dan"
echo $obj->salamB();   // "Salam B-dən"
```

### Sihirli metodlar (Magic Methods)

PHP-nin Java-da birbaşa ekvivalenti olmayan xüsusi metodları var:

```php
class SihirliSinif
{
    private array $məlumat = [];

    // Mövcud olmayan sahəyə dəyər təyin edildikdə
    public function __set(string $ad, mixed $dəyər): void
    {
        $this->məlumat[$ad] = $dəyər;
    }

    // Mövcud olmayan sahə oxunduqda
    public function __get(string $ad): mixed
    {
        return $this->məlumat[$ad] ?? null;
    }

    // isset() və empty() ilə yoxlanılanda
    public function __isset(string $ad): bool
    {
        return isset($this->məlumat[$ad]);
    }

    // Obyekt funksiya kimi çağırılanda
    public function __invoke(string $arg): string
    {
        return "Çağırıldı: $arg";
    }

    // Obyekt klonlananda
    public function __clone(): void
    {
        // Dərin kopyalama məntiqi
    }
}

$obj = new SihirliSinif();
$obj->ad = "Test";           // __set çağırılır
echo $obj->ad;               // __get çağırılır: "Test"
echo $obj("salam");          // __invoke çağırılır: "Çağırıldı: salam"
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| OOP məcburiyyəti | Hər şey sinif daxilindədir | Prosedural + OOP birlikdə ola bilər |
| Fayl strukturu | Hər public sinif ayrı faylda | Bir faylda bir neçə sinif ola bilər |
| Konstruktor overloading | Bəli (bir neçə konstruktor) | Xeyr (bir konstruktor, default dəyərlərlə) |
| Property promotion | Yoxdur (record-lar istisna) | Bəli (PHP 8.0+) |
| İnterfeys default metod | Bəli (Java 8+) | Xeyr |
| Traits | Yoxdur | Bəli |
| Sealed classes | Bəli (Java 17+) | Xeyr |
| Record/Data class | Bəli (Java 16+) | Readonly class (PHP 8.2+) |
| Giriş modifikatorları | 4 səviyyə | 3 səviyyə |
| Late Static Binding | Yoxdur (ehtiyac yoxdur) | `static::` keyword |
| Magic methods | Yoxdur | `__get`, `__set`, `__call` və s. |
| Named arguments | Yoxdur | Bəli (PHP 8.0+) |
| self vs static | Yoxdur | Bəli (LSB) |
| Enum | Bəli (Java 5+, tam sinif) | Bəli (PHP 8.1+, daha sadə) |

---

## Niyə belə fərqlər var?

### Niyə Java-da traits yoxdur?

Java dizayn fəlsəfəsi "composition over inheritance" (miras yerinə kompozisiya) prinsipinə əsaslanır. Java-da kod paylaşmaq lazım olduqda:

1. **İnterfeyslərin default metodları** (Java 8+) -- davranış paylaşmaq üçün
2. **Kompozisiya** -- sahə kimi başqa sinif istifadə etmək
3. **Delegation** -- metodları başqa obyektə yönləndirmək

Java yaradıcıları C++-ın çoxlu miras problemlərini (diamond problem) görmüşdülər və bilərəkdən tək mirasla məhdudlaşdırdılar. Traits də çoxlu mirasın bir formasıdır və eyni problemləri gətirə bilər.

### Niyə PHP-də sealed classes yoxdur?

PHP dinamik dil olaraq "açıq genişlənmə" fəlsəfəsinə malikdir. Siniflərin kim tərəfindən miras alınacağını məhdudlaşdırmaq PHP-nin ruhuna uyğun gəlmir. Əlavə olaraq, PHP-də pattern matching ilə exhaustive checking (Java-nın switch ilə etdiyi kimi) yoxdur, ona görə sealed class-ın əsas faydası itir.

### Constructor fərqləri

Java-da constructor overloading var çünki Java statik tipli dildir -- hər overload fərqli tip imzasına malikdir və kompilyator hansının çağırılacağını compile-time-da bilir.

PHP-də bu lazım deyil çünki:
- Default parametr dəyərləri istifadə edilir
- Named arguments (PHP 8.0+) ilə istənilən parametr atlanıla bilər
- Static factory metodlar yaradıla bilər

```php
class Tarix
{
    private function __construct(
        private int $il,
        private int $ay,
        private int $gun,
    ) {}

    // Statik fabrik metodlar -- constructor overloading əvəzinə
    public static function bugun(): self
    {
        return new self(
            (int) date('Y'),
            (int) date('m'),
            (int) date('d'),
        );
    }

    public static function stringdən(string $tarix): self
    {
        $hisseler = explode('-', $tarix);
        return new self((int) $hisseler[0], (int) $hisseler[1], (int) $hisseler[2]);
    }
}

$t1 = Tarix::bugun();
$t2 = Tarix::stringdən('2026-04-11');
```

### Magic methods niyə yalnız PHP-də var?

PHP-nin magic methods-u dilin dinamik təbiətindən irəli gəlir. `__get` və `__set` kimi metodlar runtime-da mövcud olmayan sahələrə müraciəti tutur -- bu, Java-da mümkün deyil çünki Java kompilyasiya zamanı hər sahənin mövcud olduğunu yoxlayır.

Bu mexanizm ORM-lərdə (Eloquent kimi), proxy obyektlərdə və aktiv yazma (Active Record) pattern-ində çox istifadə olunur. Java-da eyni nəticə üçün Reflection API və ya bytecode manipulation (Hibernate kimi) istifadə olunur -- daha mürəkkəb, amma daha təhlükəsiz.
