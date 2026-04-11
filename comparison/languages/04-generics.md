# Generics (Ümumiləşdirilmiş Tiplər)

## Giris

Generics -- kodun müxtəlif data tipləri ilə təhlükəsiz işləməsini təmin edən mexanizmdir. Java-da generics dilin əsas hissəsidir və geniş istifadə olunur. PHP-də isə dil səviyyəsində generics yoxdur, amma statik analiz alətləri (PHPStan, Psalm) annotasiyalar vasitəsilə bunu təqlid edir.

---

## Java-da istifadəsi

### Əsas sintaksis

```java
// Generic sinif -- T tip parametridir
public class Qutu<T> {
    private T dəyər;

    public Qutu(T dəyər) {
        this.dəyər = dəyər;
    }

    public T getDəyər() {
        return dəyər;
    }

    public void setDəyər(T dəyər) {
        this.dəyər = dəyər;
    }
}

// İstifadəsi:
Qutu<String> mətnQutusu = new Qutu<>("Salam");
String mətn = mətnQutusu.getDəyər();  // Casting lazım deyil!

Qutu<Integer> ədədQutusu = new Qutu<>(42);
int ədəd = ədədQutusu.getDəyər();

// Yanlış tip təyin etməyə imkan vermir:
// mətnQutusu.setDəyər(123);  // XƏTA! String gözlənilir, int verildi
```

### Generics-dən əvvəl (Java 5-dən əvvəl)

```java
// Generics olmadan -- hər şey Object idi
List list = new ArrayList();
list.add("Salam");
list.add(42);           // Heç bir xəta -- hər şey əlavə oluna bilər

String s = (String) list.get(0);  // Casting lazımdır
String x = (String) list.get(1);  // ClassCastException! Runtime xətası

// Generics ilə -- kompilyasiya zamanı yoxlanılır
List<String> list2 = new ArrayList<>();
list2.add("Salam");
// list2.add(42);  // XƏTA! Kompilyasiya zamanı tutulur
String s2 = list2.get(0);  // Casting lazım deyil
```

### Bir neçə tip parametri

```java
// İki tip parametri olan sinif
public class Cüt<A, B> {
    private final A birinci;
    private final B ikinci;

    public Cüt(A birinci, B ikinci) {
        this.birinci = birinci;
        this.ikinci = ikinci;
    }

    public A getBirinci() { return birinci; }
    public B getİkinci() { return ikinci; }
}

// İstifadəsi:
Cüt<String, Integer> adVəYaş = new Cüt<>("Orxan", 25);
String ad = adVəYaş.getBirinci();   // "Orxan"
int yaş = adVəYaş.getİkinci();     // 25

// Map.Entry buna bənzəyir
Map<String, List<Integer>> xəritə = new HashMap<>();
```

### Generic metodlar

```java
public class Alətlər {
    // Generic metod -- sinif generic olmasa da, metod ola bilər
    public static <T> List<T> siyahıYarat(T... elementlər) {
        return List.of(elementlər);
    }

    // İki fərqli tip parametri
    public static <T, R> R çevir(T dəyər, Function<T, R> çevirici) {
        return çevirici.apply(dəyər);
    }

    // Tip çıxarma (type inference) sayəsində tipləri yazmaq lazım deyil
    public static void main(String[] args) {
        List<String> adlar = siyahıYarat("Ali", "Vəli", "Orxan");
        // Java tip parametrlərini avtomatik çıxarır

        String nəticə = çevir(42, ədəd -> "Ədəd: " + ədəd);
        // T = Integer, R = String -- avtomatik çıxarılır
    }
}
```

### Bounded Types (Məhdudlaşdırılmış tiplər)

```java
// Upper bound -- T Comparable-ı implement etməlidir
public static <T extends Comparable<T>> T ənBöyük(List<T> siyahı) {
    T max = siyahı.get(0);
    for (T element : siyahı) {
        if (element.compareTo(max) > 0) {
            max = element;
        }
    }
    return max;
}

// İstifadəsi:
int max1 = ənBöyük(List.of(3, 1, 4, 1, 5, 9));    // 9
String max2 = ənBöyük(List.of("alma", "armud", "banana")); // "banana"
// ənBöyük(List.of(new Object())); // XƏTA! Object Comparable deyil

// Bir neçə bound (& ilə)
public static <T extends Serializable & Comparable<T>> void işlə(T dəyər) {
    // T həm Serializable, həm Comparable olmalıdır
}

// Sinif bound -- yalnız bir sinif ola bilər (birinci yerdə)
public static <T extends Number & Comparable<T>> double toplaHamısını(List<T> list) {
    double cəm = 0;
    for (T n : list) {
        cəm += n.doubleValue();  // Number-in metodu
    }
    return cəm;
}
```

### Wildcards (Joker tiplər)

```java
// ? -- naməlum tip
// Wildcard-lar dəyişənlərdə və parametrlərdə istifadə olunur (sinif elanında yox)

// Upper bounded wildcard: ? extends T -- T və ya T-nin alt tipləri
public static double cəm(List<? extends Number> siyahı) {
    double cəm = 0;
    for (Number n : siyahı) {
        cəm += n.doubleValue();
    }
    return cəm;
}
// OK: cəm(List.of(1, 2, 3))           -- List<Integer>
// OK: cəm(List.of(1.1, 2.2))          -- List<Double>
// XƏTA: siyahı.add(42)                -- əlavə etmək olmaz!

// Lower bounded wildcard: ? super T -- T və ya T-nin üst tipləri
public static void əlavəEt(List<? super Integer> siyahı) {
    siyahı.add(42);     // OK: Integer əlavə etmək olar
    siyahı.add(100);    // OK
    // Integer x = siyahı.get(0);  // XƏTA: oxumaq çətindir
}
// OK: əlavəEt(new ArrayList<Number>())
// OK: əlavəEt(new ArrayList<Object>())

// Unbounded wildcard: ? -- istənilən tip
public static void çapEt(List<?> siyahı) {
    for (Object element : siyahı) {
        System.out.println(element);
    }
    // siyahı.add("test");  // XƏTA: null-dan başqa heç nə əlavə edilə bilməz
}
```

**PECS prensipi:** Producer Extends, Consumer Super.
- Siyahıdan **oxuyursunuzsa** (produce) -- `? extends T`
- Siyahıya **yazırsınızsa** (consume) -- `? super T`

### Type Erasure (Tip silinməsi)

Java-da generics yalnız **kompilyasiya zamanı** mövcuddur. Runtime-da tip məlumatı silinir:

```java
// Kompilyasiya zamanı:
List<String> adlar = new ArrayList<>();
List<Integer> ededler = new ArrayList<>();

// Runtime-da ikisi də eyni tipdir:
System.out.println(adlar.getClass() == ededler.getClass()); // true!
// İkisi də sadəcə ArrayList-dir

// Bu səbəbdən bəzi şeylər mümkün deyil:
// new T()                    -- tip runtime-da bilinmir
// new T[10]                  -- generic massiv yaradıla bilməz
// instanceof List<String>    -- runtime-da String məlumatı yoxdur

// Workaround: Class<T> parametri
public static <T> T yarat(Class<T> tip) throws Exception {
    return tip.getDeclaredConstructor().newInstance();
}

String s = yarat(String.class);  // OK
```

### Generic interfeys nümunəsi

```java
// Repository pattern -- generics ilə
public interface Repository<T, ID> {
    T tapId(ID id);
    List<T> hamısı();
    T saxla(T entity);
    void sil(ID id);
}

public class IstifadeciRepository implements Repository<Istifadeci, Long> {
    @Override
    public Istifadeci tapId(Long id) {
        // Verilənlər bazasından tap
        return null;
    }

    @Override
    public List<Istifadeci> hamısı() {
        return List.of();
    }

    @Override
    public Istifadeci saxla(Istifadeci entity) {
        return entity;
    }

    @Override
    public void sil(Long id) {
        // Sil
    }
}
```

---

## PHP-də istifadəsi

### PHP-də generics YOXDUR (dil səviyyəsində)

PHP-də dil səviyyəsində generics dəstəklənmir. Bunun nəticəsi:

```php
// PHP-də massivlər hər şeyi qəbul edir
$siyahı = [];
$siyahı[] = "Salam";
$siyahı[] = 42;
$siyahı[] = new stdClass();
// Heç bir xəta yoxdur -- tip təhlükəsizliyi yoxdur

// Tip elanı yalnız "array" səviyyəsindədir
function işlə(array $siyahı): void {
    // $siyahı nə tipli elementlər saxlayır? Bilmirik!
    foreach ($siyahı as $element) {
        // $element hansı tipdir? Runtime-a qədər bilinmir.
    }
}
```

### PHPStan/Psalm annotasiyaları

Statik analiz alətləri generics-i doc-comment annotasiyaları ilə dəstəkləyir:

```php
/**
 * Generic sinif annotasiyası
 *
 * @template T
 */
class Qutu
{
    /** @var T */
    private mixed $dəyər;

    /** @param T $dəyər */
    public function __construct(mixed $dəyər)
    {
        $this->dəyər = $dəyər;
    }

    /** @return T */
    public function getDəyər(): mixed
    {
        return $this->dəyər;
    }

    /** @param T $dəyər */
    public function setDəyər(mixed $dəyər): void
    {
        $this->dəyər = $dəyər;
    }
}

// İstifadəsi:
/** @var Qutu<string> */
$mətnQutusu = new Qutu('Salam');

// PHPStan bunu xəta kimi göstərər:
// $mətnQutusu->setDəyər(123);  // PHPStan: string gözlənilir
// Amma PHP runtime-da heç bir xəta vermir!
```

### Collection sinifləri ilə tip təhlükəsizliyi

```php
/**
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
class TipliSiyahı implements \IteratorAggregate, \Countable
{
    /** @var array<int, T> */
    private array $elementlər = [];

    /**
     * @param class-string<T> $tip
     */
    public function __construct(
        private string $tip,
    ) {}

    /**
     * @param T $element
     */
    public function əlavəEt(mixed $element): void
    {
        if (!$element instanceof $this->tip) {
            throw new \InvalidArgumentException(
                sprintf('%s gözlənilir, %s verildi', $this->tip, get_debug_type($element))
            );
        }
        $this->elementlər[] = $element;
    }

    /**
     * @return T|null
     */
    public function birinci(): mixed
    {
        return $this->elementlər[0] ?? null;
    }

    public function count(): int
    {
        return count($this->elementlər);
    }

    /**
     * @return \ArrayIterator<int, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->elementlər);
    }
}

// İstifadəsi:
/** @var TipliSiyahı<Istifadeci> */
$istifadecilər = new TipliSiyahı(Istifadeci::class);
$istifadecilər->əlavəEt(new Istifadeci('Orxan', 'orxan@mail.com'));

// Runtime xəta:
// $istifadecilər->əlavəEt("yanlış tip");  // InvalidArgumentException!
```

### PHPStan ilə generic interfeys

```php
/**
 * @template T
 * @template ID
 */
interface Repository
{
    /**
     * @param ID $id
     * @return T|null
     */
    public function tapId(mixed $id): mixed;

    /**
     * @return array<T>
     */
    public function hamısı(): array;

    /**
     * @param T $entity
     * @return T
     */
    public function saxla(mixed $entity): mixed;

    /**
     * @param ID $id
     */
    public function sil(mixed $id): void;
}

/**
 * @implements Repository<Istifadeci, int>
 */
class IstifadeciRepository implements Repository
{
    public function tapId(mixed $id): ?Istifadeci
    {
        // ...
        return null;
    }

    /** @return array<Istifadeci> */
    public function hamısı(): array
    {
        return [];
    }

    public function saxla(mixed $entity): Istifadeci
    {
        // ...
        return $entity;
    }

    public function sil(mixed $id): void
    {
        // ...
    }
}
```

### Template covariance və contravariance

```php
/**
 * Oxumaq üçün -- covariant (Java-da ? extends T)
 *
 * @template-covariant T
 */
interface OxunaBilenKolleksiya
{
    /**
     * @return T|null
     */
    public function birinci(): mixed;

    /**
     * @return array<T>
     */
    public function hamısı(): array;
}

/**
 * Yazmaq üçün -- contravariant (Java-da ? super T)
 *
 * @template-contravariant T
 */
interface YazılaBilenKolleksiya
{
    /**
     * @param T $element
     */
    public function əlavəEt(mixed $element): void;
}
```

### Laravel-də generics istifadəsi

Laravel 11+ Collection sinfi PHPStan annotasiyaları ilə generic dəstəyi verir:

```php
use Illuminate\Support\Collection;

// Laravel Collection -- həm runtime, həm PHPStan ilə
/** @var Collection<int, Istifadeci> */
$istifadecilər = collect([
    new Istifadeci('Orxan', 'orxan@mail.com'),
    new Istifadeci('Ali', 'ali@mail.com'),
]);

// PHPStan bilir ki, $birinci Istifadeci tipindədir
$birinci = $istifadecilər->first();

// PHPStan bilir ki, $adlar Collection<int, string> tipindədir
$adlar = $istifadecilər->map(fn (Istifadeci $u) => $u->getAd());
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Generics dəstəyi | Dil səviyyəsində (Java 5+) | Yoxdur (yalnız annotasiya ilə) |
| Tip yoxlama | Kompilyasiya zamanı | Yalnız statik analiz aləti ilə |
| Runtime tip məlumatı | Yox (type erasure) | Yox (annotasiya runtime-da yox) |
| Bounded types | `extends`, `super` ilə | `@template T of SomeClass` ilə |
| Wildcards | `?`, `? extends`, `? super` | `@template-covariant`, `@template-contravariant` |
| Generic massiv | Yox (`new T[]` olmaz) | PHP massivləri həmişə generic-dir |
| Məcburilik | Bəli, kompilyator zorla edir | Xeyr, isteğe bağlıdır |

---

## Niyə belə fərqlər var?

### Niyə Java-da generics lazımdır?

Java **statik tipli** dildir. Generics olmadan, kolleksiyalar yalnız `Object` tipi ilə işləyə bilirdi. Bu isə hər yerdə casting tələb edirdi və `ClassCastException` riski yaradırdı. Generics bu problemi **kompilyasiya zamanı** həll edir -- səhv tip təyin edildikdə proqram heç kompilyasiya olmur.

```java
// Generics olmadan -- təhlükəli
List list = new ArrayList();
list.add("Salam");
Integer x = (Integer) list.get(0);  // Runtime xəta!

// Generics ilə -- təhlükəsiz
List<String> list = new ArrayList<>();
// Integer x = list.get(0);  // Kompilyasiya xətası -- xəta vaxtında tutulur
```

### Niyə PHP-də generics yoxdur?

1. **Dinamik tip sistemi:** PHP dəyişənlərinin tipi runtime-da müəyyən olur. `$list[] = "salam"` yazdıqda PHP tipi bilir, amma kompilyator yoxlama aparmır. Generics-in əsas faydası **kompilyasiya zamanı** yoxlamadır -- PHP-də belə mərhələ yoxdur.

2. **Performans narahatlığı:** Generics əlavə etmək PHP-nin runtime performansını azalda bilər. PHP hər sorğuda kodu interpretasiya edir (OPcache ilə belə), generics yoxlama əlavə yük gətirər.

3. **Geriyə uyğunluq:** PHP milyon mövcud layihəni qırmadan generics əlavə etmək texniki cəhətdən çox çətindir.

4. **Alternativ yanaşma:** PHPStan və Psalm kimi alətlər annotasiyalar vasitəsilə generics-i **development zamanı** təmin edir. Bu yanaşma runtime yükü yaratmır və isteğe bağlıdır.

### Type Erasure niyə var?

Java 5-də generics əlavə olunanda, milyard-larla sətir Java kodu artıq mövcud idi. Generic-siz `List` ilə generic `List<String>` eyni runtime tipə malik olmalıydı ki, köhnə kod yeni kodla işləsin. Bu "geriyə uyğunluq" qərarı idi. Nəticədə Java runtime-da generic tip məlumatını bilmir -- bu, Java generics-inin ən böyük məhdudiyyətidir.

Əks yanaşma C#-dadır: C# generics-i **reified** (runtime-da saxlanılır). Bu daha güclüdür, amma C# generics-i Java-dan sonra əlavə olunub və geriyə uyğunluq problemi yox idi.

### Gələcəyə baxış

PHP RFC-lərində generics müzakirə olunur, amma bu çox mürəkkəb texniki məsələdir. Əsas suallar:
- Generics yalnız kompilyasiya zamanı olsunmu (Java kimi, type erasure ilə)?
- Runtime-da da yoxlansınmı (performans itkisi)?
- Mövcud annotasiya sintaksisi dəstəklənsinmi?

Hələlik, **PHPStan/Psalm + annotasiyalar** PHP dünyasında generics-in ən yaxşı həllidir. Bu yanaşma runtime yükü olmadan tip təhlükəsizliyi təmin edir.
