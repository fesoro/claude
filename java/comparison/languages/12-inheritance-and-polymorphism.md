# Varislik və Polimorfizm

> **Seviyye:** Beginner ⭐

## Giris

Varislik (inheritance) və polimorfizm OOP-nin iki əsas sütunudur. Hər iki dil bunları dəstəkləyir, lakin Java-nın statik tip sistemi daha çox imkan (method overloading) və daha çox məhdudiyyət (tək miras) gətirir. PHP isə daha çevik yanaşır, amma bəzi xüsusiyyətlər (overloading kimi) yoxdur.

---

## Java-da istifadəsi

### Varislik (Inheritance)

```java
// Baza sinif (superclass / parent class)
public class Heyvan {
    protected String ad;
    protected int yash;

    public Heyvan(String ad, int yash) {
        this.ad = ad;
        this.yash = yash;
    }

    public String səsÇıxar() {
        return "...";
    }

    public String melumat() {
        return "%s, %d yaş".formatted(ad, yash);
    }
}

// Alt sinif (subclass / child class)
public class Pişik extends Heyvan {
    private boolean evPişiyi;

    public Pişik(String ad, int yash, boolean evPişiyi) {
        super(ad, yash);  // Üst sinif konstruktorunu MÜTLƏQ çağırmalıdır
        this.evPişiyi = evPişiyi;
    }

    @Override  // Annotasiya -- kompilyator yoxlayır ki, doğrudan override edir
    public String səsÇıxar() {
        return "Miyav!";
    }
}

public class İt extends Heyvan {
    private String cins;

    public İt(String ad, int yash, String cins) {
        super(ad, yash);
        this.cins = cins;
    }

    @Override
    public String səsÇıxar() {
        return "Hav-hav!";
    }

    // Yeni metod -- yalnız İt sinfinə aiddir
    public String gətir(String əşya) {
        return ad + " " + əşya + " gətirdi!";
    }
}
```

### Metod Override (Üstünlük alma)

```java
public class Nümunə {
    public void göstər() {
        Heyvan h = new Heyvan("Heyvan", 5);
        Pişik p = new Pişik("Mestan", 3, true);
        İt i = new İt("Bobik", 2, "Labrador");

        System.out.println(h.səsÇıxar()); // "..."
        System.out.println(p.səsÇıxar()); // "Miyav!"
        System.out.println(i.səsÇıxar()); // "Hav-hav!"
    }
}
```

**Override qaydaları Java-da:**

```java
public class Valideyn {
    // 1. Qaytarma tipi eyni və ya daha spesifik (covariant) ola bilər
    public Number hesabla() { return 42; }

    // 2. Giriş modifikatoru eyni və ya daha geniş ola bilər
    protected String mesaj() { return "valideyn"; }

    // 3. Exception daha geniş ola bilməz
    public void risk() throws IOException { }
}

public class Uşaq extends Valideyn {
    @Override
    public Integer hesabla() { return 42; }  // OK: Integer extends Number

    @Override
    public String mesaj() { return "uşaq"; }  // OK: public >= protected

    @Override
    public void risk() throws FileNotFoundException { }  // OK: daha dar
    // public void risk() throws Exception {} // XƏTA: daha geniş exception
}
```

### Metod Overloading (Yalnız Java-da)

Overloading -- eyni adda, lakin fərqli parametrlərlə bir neçə metod yaratmaqdır. PHP-də bu mövcud deyil.

```java
public class Kalkulyator {
    // Eyni ad, fərqli parametr tipləri
    public int topla(int a, int b) {
        return a + b;
    }

    public double topla(double a, double b) {
        return a + b;
    }

    public int topla(int a, int b, int c) {
        return a + b + c;
    }

    public String topla(String a, String b) {
        return a + b;  // String birləşdirmə
    }
}

// İstifadəsi:
Kalkulyator k = new Kalkulyator();
k.topla(1, 2);           // int versiya: 3
k.topla(1.5, 2.5);       // double versiya: 4.0
k.topla(1, 2, 3);        // üç parametrli versiya: 6
k.topla("Sa", "lam");    // String versiya: "Salam"
```

**Overloading kompilyasiya zamanı həll olunur** (static dispatch), override isə icra zamanı (dynamic dispatch):

```java
public class OverloadNümunə {
    public void göstər(Heyvan h) {
        System.out.println("Heyvan göstərildi");
    }

    public void göstər(Pişik p) {
        System.out.println("Pişik göstərildi");
    }

    public static void main(String[] args) {
        OverloadNümunə n = new OverloadNümunə();

        Heyvan h = new Pişik("Mestan", 3, true);  // Dəyişən tipi Heyvan
        n.göstər(h);  // "Heyvan göstərildi" -- dəyişənin tipinə görə seçilir!

        Pişik p = new Pişik("Mestan", 3, true);
        n.göstər(p);  // "Pişik göstərildi"
    }
}
```

### Polimorfizm

```java
public class HeyvanKlinikası {
    // Polimorfizm -- üst sinif tipi ilə alt sinifləri qəbul edir
    public void müayinə(Heyvan heyvan) {
        System.out.println("Müayinə: " + heyvan.melumat());
        System.out.println("Səsi: " + heyvan.səsÇıxar());
    }

    public static void main(String[] args) {
        HeyvanKlinikası klinika = new HeyvanKlinikası();

        // Fərqli tiplər eyni metoda göndərilir
        klinika.müayinə(new Pişik("Mestan", 3, true));
        klinika.müayinə(new İt("Bobik", 2, "Labrador"));

        // List ilə polimorfizm
        List<Heyvan> heyvanlar = List.of(
            new Pişik("Mestan", 3, true),
            new İt("Bobik", 2, "Labrador"),
            new Pişik("Bənövşə", 5, false)
        );

        for (Heyvan h : heyvanlar) {
            System.out.println(h.səsÇıxar());  // Hər biri öz səsini çıxarır
        }
    }
}
```

### instanceof və Pattern Matching (Java 16+)

```java
// Klassik yol
if (heyvan instanceof Pişik) {
    Pişik p = (Pişik) heyvan;  // Əl ilə casting
    System.out.println(p.isEvPişiyi());
}

// Pattern Matching (Java 16+) -- daha qısa
if (heyvan instanceof Pişik p) {
    // p artıq Pişik tipindədir, casting lazım deyil
    System.out.println(p.isEvPişiyi());
}

// switch ilə pattern matching (Java 21+)
String nəticə = switch (heyvan) {
    case Pişik p when p.isEvPişiyi() -> "Ev pişiyi: " + p.getAd();
    case Pişik p -> "Küçə pişiyi: " + p.getAd();
    case İt i -> "İt: " + i.getAd() + " (" + i.getCins() + ")";
    default -> "Naməlum heyvan";
};
```

---

## PHP-də istifadəsi

### Varislik (Inheritance)

```php
class Heyvan
{
    public function __construct(
        protected string $ad,
        protected int $yash,
    ) {}

    public function səsÇıxar(): string
    {
        return '...';
    }

    public function melumat(): string
    {
        return "{$this->ad}, {$this->yash} yaş";
    }
}

class Pişik extends Heyvan
{
    public function __construct(
        string $ad,
        int $yash,
        private bool $evPişiyi = true,
    ) {
        parent::__construct($ad, $yash);
    }

    // Override -- PHP-də @Override atributu yoxdur (PHP 8.3-ə qədər)
    public function səsÇıxar(): string
    {
        return 'Miyav!';
    }
}

// PHP 8.3+ Override atributu
class İt extends Heyvan
{
    public function __construct(
        string $ad,
        int $yash,
        private string $cins = '',
    ) {
        parent::__construct($ad, $yash);
    }

    #[\Override]  // PHP 8.3+ -- yanlış yazılsa runtime xəta verir
    public function səsÇıxar(): string
    {
        return 'Hav-hav!';
    }

    public function gətir(string $əşya): string
    {
        return "{$this->ad} {$əşya} gətirdi!";
    }
}
```

### Metod Override qaydaları

```php
class Valideyn
{
    public function hesabla(): int
    {
        return 42;
    }

    protected function mesaj(): string
    {
        return 'valideyn';
    }
}

class Uşaq extends Valideyn
{
    // PHP-də qaytarma tipi uyğun olmalıdır (covariant PHP 7.4+)
    public function hesabla(): int  // eyni tip
    {
        return 100;
    }

    // Giriş daha geniş ola bilər
    public function mesaj(): string  // protected -> public: OK
    {
        return 'uşaq';
    }
}
```

### Overloading yoxdur -- alternativlər

PHP-də Java mənasında method overloading yoxdur. Bunun əvəzinə:

```php
class Kalkulyator
{
    // 1. Default parametrlər
    public function topla(
        int|float $a,
        int|float $b,
        int|float $c = 0,
    ): int|float {
        return $a + $b + $c;
    }

    // 2. Union types (PHP 8.0+)
    public function formatla(int|float|string $dəyər): string
    {
        return match (true) {
            is_int($dəyər) => "Tam: $dəyər",
            is_float($dəyər) => sprintf("Kəsr: %.2f", $dəyər),
            is_string($dəyər) => "Mətn: $dəyər",
        };
    }

    // 3. Variadic parametrlər
    public function topla_hamısını(int|float ...$ededler): int|float
    {
        return array_sum($ededler);
    }

    // 4. __call magic method (tövsiyə olunmur, amma mümkündür)
    public function __call(string $ad, array $args): mixed
    {
        if ($ad === 'hesabla') {
            return match (count($args)) {
                1 => $args[0] * 2,
                2 => $args[0] + $args[1],
                3 => $args[0] + $args[1] + $args[2],
                default => throw new \BadMethodCallException("Yanlış parametr sayı"),
            };
        }
        throw new \BadMethodCallException("Metod tapılmadı: $ad");
    }
}

$k = new Kalkulyator();
echo $k->topla(1, 2);         // 3
echo $k->topla(1, 2, 3);      // 6
echo $k->topla_hamısını(1, 2, 3, 4, 5); // 15
echo $k->formatla(42);        // "Tam: 42"
echo $k->formatla(3.14);      // "Kəsr: 3.14"
```

### Polimorfizm

```php
class HeyvanKlinikası
{
    // Tip elanı ilə polimorfizm
    public function müayinə(Heyvan $heyvan): void
    {
        echo "Müayinə: " . $heyvan->melumat() . "\n";
        echo "Səsi: " . $heyvan->səsÇıxar() . "\n";
    }

    // Interface ilə polimorfizm (daha yaxşı yanaşma)
    public function müayinəEt(SəsÇıxaranInterface $heyvan): void
    {
        echo $heyvan->səsÇıxar();
    }
}

// İstifadəsi:
$klinika = new HeyvanKlinikası();
$klinika->müayinə(new Pişik('Mestan', 3));
$klinika->müayinə(new İt('Bobik', 2));

// Massiv ilə polimorfizm
$heyvanlar = [
    new Pişik('Mestan', 3),
    new İt('Bobik', 2, 'Labrador'),
    new Pişik('Bənövşə', 5, false),
];

foreach ($heyvanlar as $h) {
    echo $h->səsÇıxar() . "\n";
}
```

### instanceof və tip yoxlama

```php
// instanceof operatoru
if ($heyvan instanceof Pişik) {
    echo $heyvan->isEvPişiyi();
}

// Mənfi yoxlama
if (!$heyvan instanceof İt) {
    echo "Bu it deyil";
}

// get_class() və ::class
echo get_class($heyvan);        // "Pişik"
echo $heyvan::class;            // "Pişik" (PHP 8.0+)

// is_a() funksiyası
if (is_a($heyvan, Heyvan::class)) {
    echo "Bu bir heyvandır";
}

// match ilə tip yoxlama (pattern matching yoxdur, amma match ifadəsi var)
$nəticə = match (true) {
    $heyvan instanceof Pişik => 'Pişik: ' . $heyvan->getAd(),
    $heyvan instanceof İt => 'İt: ' . $heyvan->getAd(),
    default => 'Naməlum heyvan',
};
```

### Constructor-da varislik

```php
// PHP-də vacib fərq: valideyn konstruktoru avtomatik çağırılmır!
class Baza
{
    public function __construct()
    {
        echo "Baza konstruktoru\n";
    }
}

class Alt extends Baza
{
    public function __construct()
    {
        // parent::__construct() çağırılmasa, Baza konstruktoru işləməz!
        parent::__construct();
        echo "Alt konstruktor\n";
    }
}

// Java-da isə super() çağırılmazsa, kompilyator
// avtomatik parametrsiz super() əlavə edir.
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Miras mexanizmi | `extends` (siniflər), `implements` (interfeyslar) | Eyni |
| Çoxlu miras | Xeyr (interface ilə mümkün) | Xeyr (trait ilə mümkün) |
| Method Overloading | Bəli (compile-time) | Xeyr |
| Method Overriding | Bəli, `@Override` ilə | Bəli, `#[\Override]` (PHP 8.3+) |
| Covariant return | Bəli | Bəli (PHP 7.4+) |
| Pattern matching | Bəli (Java 16+, switch Java 21+) | Xeyr |
| instanceof | Bəli | Bəli |
| super() çağırma | Avtomatik (parametrsiz) | Əl ilə (parent::__construct()) |
| Abstract sinif | Bəli | Bəli |
| final class/method | Bəli | Bəli |
| Anonymous sinif | Bəli | Bəli (PHP 7+) |

---

## Niyə belə fərqlər var?

### Niyə PHP-də method overloading yoxdur?

Method overloading **kompilyasiya zamanı** tip məlumatına əsaslanır. Java kompilyatoru `topla(int, int)` və `topla(double, double)` arasında fərqi tipdən bilir. PHP isə **interpretasiya olunan** dildir -- kompilyasiya mərhələsi yoxdur. Dəyişənlərin tipi runtime-da məlum olur, ona görə kompilyator hansı overload-u seçəcəyini bilə bilməz.

PHP bunun əvəzinə bu mexanizmləri təklif edir:
- **Union types:** `int|float|string` -- bir parametr bir neçə tip qəbul edir
- **Default dəyərlər:** parametrləri isteğe bağlı edir
- **Variadic parametrlər:** `...$args` ilə istənilən sayda parametr
- **`__call` magic method:** runtime-da metod çağırışlarını tutma

### Niyə Java-da super() avtomatikdir?

Java obyekt yaratma prosesini tam nəzarət altında saxlayır. Hər obyekt əvvəlcə `Object` sinifinin, sonra hər üst sinifin konstruktorundan keçməlidir. Bu zəncir qırılmamalıdır. Əgər proqramçı `super()` yazmazsa, Java kompilyatoru avtomatik olaraq parametrsiz `super()` əlavə edir.

PHP isə bu qərarı proqramçıya buraxır. Bəzən valideyn konstruktoru çağırmamaq lazım olur -- məsələn, alt sinif tamamilə fərqli initiallaşdırma məntiqi istifadə edirsə. Bu çeviklik verir, amma valideyn konstruktorunun çağırılmadığı xətaların mənbəyi ola bilər.

### Pattern Matching -- gələcək trendi

Java 16-dan etibarən pattern matching inkişaf edir və Java 21-də switch ilə tam dəstəklənir. Bu xüsusiyyət sealed classes ilə birlikdə çox güclüdür -- kompilyator bütün halların örtülüb-örtülmədiyini yoxlaya bilir.

PHP-də pattern matching hələ yoxdur. `match` ifadəsi (PHP 8.0+) dəyər müqayisəsi edir, amma tip əsaslı pattern matching deyil. Gələcəkdə PHP-yə bu xüsusiyyətin əlavə olunması müzakirə edilir.
