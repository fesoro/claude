# Data Tipləri və Dəyişənlər

## Giris

Java və PHP dəyişənlərə və data tiplərinə tamamilə fərqli yanaşırlar. Java **statik tipli** (statically typed) dildir -- hər dəyişənin tipi kompilyasiya zamanı məlum olmalıdır. PHP isə **dinamik tipli** (dynamically typed) dildir -- dəyişənin tipi icra zamanı (runtime) müəyyən olunur və istənilən vaxt dəyişə bilər. Bu fərq hər iki dilin dizayn fəlsəfəsindən irəli gəlir.

---

## Java-da istifadəsi

### Primitiv tiplər (Primitive Types)

Java-da 8 primitiv tip var. Bunlar obyekt deyil, birbaşa yaddaşda saxlanılır və çox sürətlidir:

```java
// Tam ədədlər (integer types)
byte  kicik  = 127;            // 8 bit,  -128 ... 127
short qisa   = 32000;          // 16 bit, -32768 ... 32767
int   eded   = 2_000_000;      // 32 bit, ~ +-2 milyard
long  boyuk  = 9_000_000_000L; // 64 bit

// Kəsr ədədlər (floating point)
float  kesr  = 3.14f;          // 32 bit
double dəqiq = 3.141592653589; // 64 bit

// Digərləri
boolean aktiv = true;          // true və ya false
char    herf  = 'A';           // 16 bit Unicode simvol
```

**Vacib:** Primitiv tiplər `null` ola bilməz. `int x = null;` kompilyasiya xətası verir.

### Referans tiplər (Reference Types)

Primitiv tiplərin wrapper (sarğı) sinifləri və bütün obyektlər referans tipdir:

```java
// Wrapper sinifləri -- null ola bilər
Integer eded = null;       // OK
Boolean aktiv = null;      // OK
Double kesr = 3.14;        // autoboxing: double -> Double

// String -- xüsusi referans tip
String ad = "Orxan";
String soyad = new String("Əliyev");

// Massivlər -- referans tipdir
int[] ededler = {1, 2, 3};
String[] adlar = {"Ali", "Vəli"};
```

### Autoboxing və Unboxing

Java primitiv ilə wrapper arasında avtomatik çevirmə edir:

```java
// Autoboxing: primitiv -> wrapper
Integer x = 42;         // int -> Integer (avtomatik)
List<Integer> list = new ArrayList<>();
list.add(10);            // int -> Integer (avtomatik)

// Unboxing: wrapper -> primitiv
int y = x;               // Integer -> int (avtomatik)
int z = list.get(0);     // Integer -> int (avtomatik)

// Diqqət: NullPointerException riski
Integer n = null;
int m = n;  // NullPointerException! Runtime xətası.
```

### Type Casting (Tip çevirmə)

```java
// Widening (genişləndirmə) -- avtomatik, məlumat itkisi yoxdur
int i = 100;
long l = i;        // int -> long (avtomatik)
double d = i;      // int -> double (avtomatik)

// Narrowing (daraltma) -- əl ilə, məlumat itkisi ola bilər
double pi = 3.99;
int tam = (int) pi;  // 3 -- kəsr hissə kəsilir!

long boyuk = 1_000_000_000_000L;
int kicik = (int) boyuk;  // overflow -- gözlənilməz nəticə!

// Referans tipləri arasında casting
Object obj = "Salam";
String str = (String) obj;  // OK, çünki obj əslində String-dir

Object num = 42;
String s = (String) num;    // ClassCastException! Runtime xətası.
```

### var (Java 10+)

Java 10-dan etibarən `var` ilə lokal dəyişən elan etmək olar. Tip kompilyator tərəfindən çıxarılır (type inference), amma dəyişənin tipi hələ də statikdir:

```java
var ad = "Orxan";         // String tipi çıxarılır
var eded = 42;            // int tipi çıxarılır
var list = new ArrayList<String>(); // ArrayList<String>

// ad = 123;  // XƏTA! ad artıq String-dir, int təyin edilə bilməz.

// var yalnız lokal dəyişənlərdə istifadə olunur:
// var sahə;          // XƏTA: başlanğıc dəyər lazımdır
// class Sinif { var x = 5; }  // XƏTA: sahələrdə istifadə olunmur
```

### Sabitlər (Constants)

```java
// final ilə sabit elan edilir
final int MAX_OLCU = 100;
final String APP_ADI = "MənimApp";
// MAX_OLCU = 200;  // XƏTA! final dəyişən dəyişdirilə bilməz.

// static final -- sinif səviyyəsində sabit
public class Konfiqurasiya {
    public static final String VERSIYA = "1.0.0";
    public static final int PORT = 8080;
}

// İstifadəsi:
System.out.println(Konfiqurasiya.VERSIYA);
```

---

## PHP-də istifadəsi

### Dəyişən elanı

PHP-də dəyişənlər `$` işarəsi ilə başlayır və tip elan etmək lazım deyil:

```php
$ad = "Orxan";      // string
$yash = 25;          // integer
$boy = 1.78;         // float
$aktiv = true;       // boolean
$bosh = null;        // null

// Eyni dəyişən fərqli tiplər ala bilər
$x = 42;            // integer
$x = "salam";       // indi string
$x = [1, 2, 3];     // indi array
// Heç bir xəta yoxdur!
```

### Skalyar tiplər

PHP-də 4 skalyar tip var:

```php
$tam = 42;                    // int
$kesr = 3.14;                 // float
$metn = "Salam, dünya!";     // string
$dogru = true;                // bool

// PHP-nin tam ədədləri platformadan asılı olaraq 32 və ya 64 bitdir
echo PHP_INT_MAX;  // 9223372036854775807 (64 bit sistemdə)
echo PHP_INT_SIZE; // 8 (bayt)

// Çox böyük ədədlər avtomatik float olur
$boyuk = PHP_INT_MAX + 1;  // float olur, tam dəqiqlik itə bilər
```

### Type Juggling (Avtomatik tip çevirmə)

PHP kontekstə görə tipləri avtomatik çevirir. Bu həm rahatlıq, həm də xəta mənbəyidir:

```php
// Riyazi əməliyyatlarda
$x = "10" + 5;       // 15 (int) -- string ədədə çevrildi
$y = "10.5" + 1;     // 11.5 (float)
$z = "salam" + 1;    // 1 -- "salam" 0-a çevrildi (PHP 8-də xəbərdarlıq)

// Müqayisədə (== loose comparison)
var_dump("0" == false);   // true -- ikisi də "boş" sayılır
var_dump("" == false);    // true
var_dump("0" == null);    // false (PHP 8+), əvvəl true idi
var_dump(0 == "abc");     // false (PHP 8+), əvvəl true idi

// String-ə çevirmə
$eded = 42;
$metn = "Yaş: " . $eded;  // "Yaş: 42" -- avtomatik çevrildi

// Boolean çevirmə -- bunlar false sayılır:
var_dump((bool) 0);        // false
var_dump((bool) "");       // false
var_dump((bool) "0");      // false
var_dump((bool) []);       // false
var_dump((bool) null);     // false
```

### Strict Typing (PHP 7+)

PHP 7-dən etibarən tip elanları və strict mode mövcuddur:

```php
// Faylın əvvəlinə yazılır -- yalnız həmin fayla aiddir
declare(strict_types=1);

// Funksiya parametrləri və qaytarma tipi
function topla(int $a, int $b): int {
    return $a + $b;
}

topla(5, 3);       // OK: 8
topla("5", "3");   // TypeError! strict_types=1 olduğu üçün

// strict_types=1 OLMADAN:
topla("5", "3");   // OK: 8 -- avtomatik çevrilir

// Nullable tiplər
function tap(?string $ad): ?string {
    if ($ad === null) return null;
    return strtoupper($ad);
}

// Union types (PHP 8.0+)
function isle(int|string $dəyər): string {
    return (string) $dəyər;
}

// Intersection types (PHP 8.1+)
function logla(Countable&Traversable $məlumat): void {
    echo count($məlumat);
}
```

### Tip yoxlama və casting

```php
// Tipi yoxlamaq
$x = 42;
echo gettype($x);     // "integer"
var_dump($x);          // int(42)
echo is_int($x);       // true
echo is_string($x);    // false

// Casting (əl ilə tip çevirmə)
$metn = "42";
$eded = (int) $metn;       // 42
$kesr = (float) $metn;     // 42.0
$bool = (bool) $metn;      // true
$massiv = (array) $metn;   // ["42"]

// intval, floatval, strval funksiyaları
$x = intval("42abc");      // 42
$y = intval("abc");        // 0
$z = intval("0xFF", 16);   // 255

// settype -- dəyişənin tipini dəyişir
$dəyər = "100";
settype($dəyər, "integer");
echo $dəyər;  // 100 (artıq int)
```

### Sabitlər (Constants)

```php
// define() ilə -- qlobal sabit
define('MAX_OLCU', 100);
define('APP_ADI', 'MənimApp');
echo MAX_OLCU;  // 100

// const ilə -- sinif daxilində və ya namespace-də
const VERSIYA = "1.0.0";

class Konfiqurasiya {
    public const VERSIYA = "1.0.0";
    public const PORT = 8080;
    
    // PHP 8.2+ -- sabitin tipini elan etmək olar
    public const string APP_ADI = "MənimApp";
}

echo Konfiqurasiya::VERSIYA;  // "1.0.0"

// Enum ilə sabitlər (PHP 8.1+)
enum Status: string {
    case Aktiv = 'aktiv';
    case Passiv = 'passiv';
    case Gözləmədə = 'gozlemede';
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Tip sistemi | Statik (compile-time) | Dinamik (runtime) |
| Primitiv tiplər | 8 ədəd (int, long, double...) | Yoxdur, hamısı daxili tip |
| Null | Yalnız referans tiplər null ola bilər | İstənilən dəyişən null ola bilər |
| Tip elanı | Məcburidir | İstəyə bağlıdır |
| Dəyişən simvolu | Yoxdur | `$` ilə başlayır |
| Tip dəyişmə | Dəyişənin tipi dəyişmir | Dəyişən istənilən tip ala bilər |
| Autoboxing | int <-> Integer avtomatik | Ehtiyac yoxdur |
| Type juggling | Yoxdur | Var (avtomatik çevirmə) |
| Strict mode | Həmişə strict | `declare(strict_types=1)` ilə |
| var keyword | Lokal tip çıxarma (Java 10+) | Yoxdur (lazım deyil) |
| Sabitlər | `final` keyword | `const` və `define()` |
| Ədəd overflow | Səssizcə overflow | Avtomatik float-a keçid |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java 1995-ci ildə Sun Microsystems tərəfindən **böyük, uzunmüddətli layihələr** üçün yaradılıb. Dizayn fəlsəfəsi belədir:

1. **Təhlükəsizlik:** Statik tip sistemi bir çox xətanı proqram işə düşməmişdən əvvəl tutur. `String x = 5;` yazsanız, kompilyator dərhal xəta verir.

2. **Performans:** Primitiv tiplər birbaşa yaddaşda saxlanılır, heap-də obyekt yaradılmır. Bu, milyonlarla ədədlə işləyərkən böyük fərq yaradır.

3. **Aydınlıq:** Kodu oxuyan hər kəs hər dəyişənin tipini görür. Böyük komandada bu çox vacibdir.

4. **IDE dəstəyi:** Statik tiplər sayəsində IDE-lər (IntelliJ, Eclipse) güclü avtotamamlama, refactoring və xəta aşkarlama təklif edə bilir.

### PHP-nin yanaşması

PHP 1994-cü ildə Rasmus Lerdorf tərəfindən **şəxsi veb-səhifə** üçün yaradılıb (Personal Home Page). Dizayn fəlsəfəsi belədir:

1. **Sadəlik:** Tip elan etmək lazım deyil, dərhal yazmağa başla. Veb-proqramlaşdırmada sürət vacibdir.

2. **Çeviklik:** Type juggling sayəsində `"10" + 5` kimi ifadələr işləyir. Bu, HTML formasından gələn string məlumatlarla işləməyi asanlaşdırır -- formdan gələn hər şey string-dir.

3. **Təkamül:** PHP zamanla daha ciddi dilə çevrilib. PHP 7-dən tip elanları, PHP 8-dən union/intersection types əlavə olunub. Bu, PHP-nin "böyüməsini" əks etdirir.

4. **Geriyə uyğunluq:** PHP hələ də köhnə kodu dəstəkləyir. `declare(strict_types=1)` istəyə bağlıdır ki, köhnə kodlar sınmasın.

### Praktik nəticə

- **Java-da** səhv tip istifadə etsəniz, proqram kompilyasiya olmaz. Xəta **inkişaf zamanı** tapılır.
- **PHP-də** səhv tip istifadə etsəniz, proqram işləyə bilər, amma gözlənilməz nəticə verə bilər. Xəta **istehsal mühitində** tapıla bilər.

Müasir PHP layihələrində `declare(strict_types=1)` istifadə etmək və PHPStan/Psalm kimi statik analiz alətlərindən istifadə etmək tövsiyə olunur -- bu, Java-nın verdiyi təhlükəsizliyin bir hissəsini PHP-yə gətirir.
