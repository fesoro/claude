# Null Təhlükəsizliyi və Optional (Null Safety and Optionals)

## Giriş

`null` dəyəri proqramlaşdırmanın ən böyük xəta mənbələrindən biri hesab olunur - Tony Hoare onu "milyard dollarlıq səhv" adlandırıb. Java və PHP bu problemə fərqli yanaşmalar təqdim edir. Java `Optional` sinfi və `@Nullable` annotasiyaları ilə null-u idarə etməyə çalışır. PHP isə nullable tiplər, null coalescing operatoru (`??`) və nullsafe operatoru (`?->`) ilə daha pragmatik həllər təklif edir.

## Java-da istifadəsi

### NullPointerException problemi

Java-da ən çox rast gəlinən runtime xəta `NullPointerException` (NPE)-dir:

```java
// NullPointerException nümunələri
String ad = null;
int uzunluq = ad.length(); // NullPointerException!

List<String> siyahı = null;
siyahı.add("element"); // NullPointerException!

// Klassik null yoxlaması - çox verboz olur
String getİstifadəçiŞəhəri(İstifadəçi istifadəçi) {
    if (istifadəçi != null) {
        Ünvan ünvan = istifadəçi.getÜnvan();
        if (ünvan != null) {
            Şəhər şəhər = ünvan.getŞəhər();
            if (şəhər != null) {
                return şəhər.getAd();
            }
        }
    }
    return "Naməlum";
}
```

### Java 14+ - Helpful NullPointerExceptions

```java
// Java 14-dən əvvəl
// Exception: java.lang.NullPointerException

// Java 14+
// Exception: java.lang.NullPointerException:
// Cannot invoke "String.length()" because "ad" is null
// Hansı dəyişənin null olduğunu göstərir!

İstifadəçi istifadəçi = null;
istifadəçi.getÜnvan().getŞəhər().getAd();
// Cannot invoke "Ünvan.getŞəhər()" because the return value of
// "İstifadəçi.getÜnvan()" is null
```

### Optional sinfi (Java 8+)

`Optional` null-u explicit şəkildə ifadə etmək üçün bir konteyner sinfidir:

```java
import java.util.Optional;

// Optional yaratma
Optional<String> dolu = Optional.of("Orxan");           // null ola bilməz
Optional<String> boş = Optional.empty();                  // boş Optional
Optional<String> nullable = Optional.ofNullable(null);    // null ola bilər
Optional<String> nullable2 = Optional.ofNullable("Əli"); // dolu Optional

// of() null qəbul etmir!
Optional<String> xəta = Optional.of(null); // NullPointerException!
```

### Optional metodları

```java
// Dəyərə çatma
Optional<String> ad = Optional.of("Orxan");

// isPresent / isEmpty
if (ad.isPresent()) {
    System.out.println(ad.get()); // "Orxan"
}
if (ad.isEmpty()) { // Java 11+
    System.out.println("Boşdur");
}

// get() - dəyər varsa qaytarır, yoxsa NoSuchElementException
String dəyər = ad.get(); // Birbaşa istifadə etməyin!

// ifPresent - dəyər varsa əməliyyat icra et
ad.ifPresent(a -> System.out.println("Ad: " + a));

// ifPresentOrElse - Java 9+
ad.ifPresentOrElse(
    a -> System.out.println("Ad: " + a),
    () -> System.out.println("Ad tapılmadı")
);

// orElse - dəyər yoxdursa alternativ
String nəticə = Optional.<String>empty().orElse("Naməlum");
System.out.println(nəticə); // "Naməlum"

// orElseGet - lazy alternativ (yalnız lazım olduqda hesablanır)
String nəticə2 = Optional.<String>empty()
    .orElseGet(() -> verilənlərdənOxu());

// orElseThrow - dəyər yoxdursa exception at
String nəticə3 = Optional.<String>empty()
    .orElseThrow(() -> new RuntimeException("Tapılmadı"));

// orElseThrow() - Java 10+ (NoSuchElementException atır)
String nəticə4 = ad.orElseThrow();

// or() - Java 9+ - boş Optional yerinə başqa Optional
Optional<String> nəticə5 = Optional.<String>empty()
    .or(() -> Optional.of("alternativ"));
```

### map, flatMap və filter

```java
// map - dəyəri transformasiya et
Optional<String> ad = Optional.of("orxan");
Optional<String> böyükHərf = ad.map(String::toUpperCase);
System.out.println(böyükHərf); // Optional[ORXAN]

Optional<Integer> uzunluq = ad.map(String::length);
System.out.println(uzunluq); // Optional[5]

// Boş Optional-da map heç nə etmir
Optional<String> boş = Optional.<String>empty();
Optional<String> nəticə = boş.map(String::toUpperCase);
System.out.println(nəticə); // Optional.empty

// flatMap - Optional qaytaran funksiya ilə
Optional<String> flatNəticə = Optional.of("42")
    .flatMap(s -> {
        try {
            return Optional.of(Integer.parseInt(s).toString());
        } catch (NumberFormatException e) {
            return Optional.empty();
        }
    });

// filter - şərtə uyğun olmayanları süz
Optional<String> filtrlənmiş = Optional.of("Orxan")
    .filter(a -> a.length() > 3);
System.out.println(filtrlənmiş); // Optional[Orxan]

Optional<String> süzülmüş = Optional.of("Əli")
    .filter(a -> a.length() > 3);
System.out.println(süzülmüş); // Optional.empty

// Zəncir şəklində istifadə
String şəhər = Optional.ofNullable(istifadəçi)
    .map(İstifadəçi::getÜnvan)
    .map(Ünvan::getŞəhər)
    .map(Şəhər::getAd)
    .orElse("Naməlum");
```

### Optional ilə Stream

```java
// stream() - Java 9+
List<Optional<String>> optionalSiyahı = List.of(
    Optional.of("Orxan"),
    Optional.empty(),
    Optional.of("Əli"),
    Optional.empty(),
    Optional.of("Aysel")
);

List<String> adlar = optionalSiyahı.stream()
    .flatMap(Optional::stream) // boş Optional-ları çıxarır
    .toList();
System.out.println(adlar); // [Orxan, Əli, Aysel]

// Optional qaytaran metod ilə
Optional<İstifadəçi> tapılmış = istifadəçilər.stream()
    .filter(i -> i.getYaş() > 18)
    .findFirst(); // Optional<İstifadəçi> qaytarır
```

### @Nullable annotasiyaları

```java
import org.jetbrains.annotations.Nullable;
import org.jetbrains.annotations.NotNull;

// JetBrains annotasiyaları (IntelliJ IDEA dəstəkləyir)
public class İstifadəçiServisi {

    @Nullable
    public İstifadəçi tapById(int id) {
        // null qaytara bilər
        return verilənlər.get(id);
    }

    @NotNull
    public List<İstifadəçi> hamısınıTap() {
        // heç vaxt null qaytarmır
        return Collections.unmodifiableList(istifadəçilər);
    }

    public void yenilə(@NotNull İstifadəçi istifadəçi) {
        // istifadəçi null ola bilməz
        Objects.requireNonNull(istifadəçi, "İstifadəçi null ola bilməz");
        // ...
    }
}

// Objects.requireNonNull - null yoxlaması
public void metod(String parametr) {
    this.parametr = Objects.requireNonNull(parametr, "parametr null ola bilməz");
}

// Müxtəlif annotasiya kitabxanaları:
// javax.annotation.Nullable (JSR-305)
// org.jetbrains.annotations.Nullable
// org.springframework.lang.Nullable
// jakarta.annotation.Nullable
```

### Optional istifadə qaydaları

```java
// YAXŞI - metod qaytarma tipi kimi
public Optional<İstifadəçi> tapByEmail(String email) {
    İstifadəçi nəticə = repo.findByEmail(email);
    return Optional.ofNullable(nəticə);
}

// PİS - metod parametri kimi (istifadə etməyin)
// public void yenilə(Optional<String> ad) { } // Bunu etməyin!

// PİS - sinif sahəsi kimi (istifadə etməyin)
// private Optional<String> ad; // Bunu etməyin!

// PİS - Optional.get() birbaşa çağırmaq
// optional.get(); // NoSuchElementException riski!

// YAXŞI - orElse və ya ifPresent istifadə edin
optional.orElse("default");
optional.ifPresent(d -> işlə(d));
```

## PHP-də istifadəsi

### Null əsasları

```php
$dəyər = null;
var_dump($dəyər);       // NULL
var_dump(is_null($dəyər)); // true
var_dump($dəyər === null);  // true

// PHP-də null olan hallar
$təyinOlunmamış;           // Təyin olunmamış dəyişən (null + warning)
$massiv = ['a' => 1];
$yox = $massiv['b'] ?? null; // Mövcud olmayan açar

// Null tipi
function prosesData(?string $data): void {
    // $data string və ya null ola bilər
}
```

### Nullable tiplər (?Type)

PHP 7.1-dən etibarən nullable tip bəyanı mövcuddur:

```php
// Nullable parametr
function salamla(?string $ad): string {
    if ($ad === null) {
        return "Salam, qonaq!";
    }
    return "Salam, $ad!";
}

echo salamla("Orxan"); // Salam, Orxan!
echo salamla(null);     // Salam, qonaq!

// Nullable qaytarma tipi
function tapById(int $id): ?User {
    $user = $this->repo->find($id);
    return $user; // User və ya null qaytara bilər
}

// Union tip ilə (PHP 8.0+)
function işlə(string|null $data): string|int {
    return $data ?? 0;
}

// Nullable sinif xüsusiyyəti
class İstifadəçi {
    public ?string $email = null;
    public ?Ünvan $ünvan = null;

    public function __construct(
        public string $ad,
        public ?string $soyad = null, // constructor promotion ilə
    ) {}
}
```

### Null Coalescing operatoru (??)

```php
// ?? operatoru - null və ya təyin olunmamış dəyişən üçün alternativ
$ad = $_GET['ad'] ?? 'Qonaq';
// Əgər $_GET['ad'] mövcuddursa və null deyilsə, onu istifadə et
// Əks halda 'Qonaq' istifadə et

// Zəncirləmək mümkündür
$dəyər = $birinci ?? $ikinci ?? $üçüncü ?? 'default';

// isset() ilə müqayisə
// Köhnə yanaşma:
$ad = isset($_GET['ad']) ? $_GET['ad'] : 'Qonaq';
// Yeni yanaşma:
$ad = $_GET['ad'] ?? 'Qonaq';

// Massiv elementləri ilə
$konfiq = ['db' => ['host' => 'localhost']];
$host = $konfiq['db']['host'] ?? '127.0.0.1';
$port = $konfiq['db']['port'] ?? 3306; // açar yoxdur, 3306 istifadə olunur

// Null coalescing assignment (??=) - PHP 7.4+
$massiv = ['a' => 1];
$massiv['a'] ??= 10; // 'a' artıq var, dəyişmir: 1
$massiv['b'] ??= 20; // 'b' yoxdur, 20 təyin olunur

echo $massiv['a']; // 1
echo $massiv['b']; // 20

// Xüsusiyyət inisializasiyası
class Konfiqurasiya {
    private ?array $cache = null;

    public function getData(): array {
        return $this->cache ??= $this->yüklə(); // yalnız bir dəfə yüklənir
    }

    private function yüklə(): array {
        return ['key' => 'value'];
    }
}
```

### Nullsafe operatoru (?->) - PHP 8.0+

```php
// Nullsafe operator zəncir çağırışlarda null yoxlamasını asanlaşdırır

// Köhnə yanaşma (PHP 8.0-dan əvvəl)
$şəhər = null;
if ($istifadəçi !== null) {
    $ünvan = $istifadəçi->getÜnvan();
    if ($ünvan !== null) {
        $şəhər_obj = $ünvan->getŞəhər();
        if ($şəhər_obj !== null) {
            $şəhər = $şəhər_obj->getAd();
        }
    }
}

// Yeni yanaşma (PHP 8.0+)
$şəhər = $istifadəçi?->getÜnvan()?->getŞəhər()?->getAd();
// Zəncirdəki hər hansı bir nöqtədə null olsa, nəticə null olur

// Xüsusiyyətlərlə
$email = $istifadəçi?->profil?->email;

// Metod çağırışları ilə
$istifadəçi?->getLogger()?->log("Mesaj");

// Massiv əlçatımı ilə birgə
$birinci = $istifadəçi?->getSifarişlər()?->first();

// DİQQƏT: Nullsafe yazma üçün istifadə edilə bilməz!
// $istifadəçi?->ad = "Orxan"; // Xəta!

// ?? ilə birlikdə istifadə
$şəhər = $istifadəçi?->getÜnvan()?->getŞəhər()?->getAd() ?? 'Naməlum';
```

### isset(), empty() və digər yoxlamalar

```php
// isset() - dəyişən mövcuddur VƏ null deyil
$a = "salam";
$b = null;
var_dump(isset($a)); // true
var_dump(isset($b)); // false - null olduğu üçün
var_dump(isset($c)); // false - təyin olunmayıb

// Çoxlu dəyişən yoxlaması
if (isset($a, $b, $c)) {
    // Hamısı mövcud və null deyil
}

// empty() - "boş" hesab olunan dəyərlər
var_dump(empty(null));    // true
var_dump(empty(false));   // true
var_dump(empty(0));       // true
var_dump(empty(""));      // true
var_dump(empty("0"));     // true
var_dump(empty([]));      // true
var_dump(empty("salam")); // false
var_dump(empty(1));       // false

// is_null() - yalnız null yoxlayır
var_dump(is_null(null));  // true
var_dump(is_null(false)); // false
var_dump(is_null(0));     // false
var_dump(is_null(""));    // false

// Müqayisə cədvəli
//              isset()  empty()  is_null()  == null  === null
// null          false    true     true       true     true
// false         true     true     false      true     false
// 0             true     true     false      true     false
// ""            true     true     false      true     false
// "0"           true     true     false      false    false
// []            true     true     false      true     false
// "salam"       true     false    false      false    false
// 1             true     false    false      false    false

// unset() - dəyişəni silir
$x = 42;
unset($x);
var_dump(isset($x)); // false
```

### Tip yoxlaması və null idarəsi patternləri

```php
// Pattern 1: Guard clause
function işlə(?string $data): string {
    if ($data === null) {
        return 'boş';
    }
    // Burada $data mütləq string-dir
    return strtoupper($data);
}

// Pattern 2: Default dəyər
function salamla(string $ad = 'Qonaq'): string {
    return "Salam, $ad!";
}

// Pattern 3: Null Object pattern
interface Logger {
    public function log(string $mesaj): void;
}

class FileLogger implements Logger {
    public function log(string $mesaj): void {
        file_put_contents('app.log', $mesaj . "\n", FILE_APPEND);
    }
}

class NullLogger implements Logger {
    public function log(string $mesaj): void {
        // Heç nə etmir
    }
}

class Servis {
    public function __construct(
        private Logger $logger = new NullLogger()
    ) {}

    public function işlə(): void {
        $this->logger->log("İşləyir"); // Null yoxlaması lazım deyil
    }
}

// Pattern 4: Match expression ilə null idarəsi (PHP 8.0+)
function statusMesajı(?string $status): string {
    return match($status) {
        null => 'Status bilinmir',
        'aktiv' => 'İstifadəçi aktivdir',
        'passiv' => 'İstifadəçi passivdir',
        default => "Naməlum status: $status",
    };
}
```

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Null tip | `null` (referans yoxluğu) | `null` (xüsusi tip) |
| Null xətası | `NullPointerException` (runtime) | `TypeError` və ya warning |
| Optional sinfi | `Optional<T>` (Java 8+) | Yoxdur (daxili) |
| Null yoxlama | `if (x != null)`, `Optional` | `isset()`, `is_null()`, `=== null` |
| Null birləşmə | `Optional.orElse()` | `??` operatoru |
| Zəncir null yoxlama | `Optional.map().map()` | `?->` operatoru (PHP 8.0+) |
| Nullable tip | `@Nullable` annotasiya (3rd party) | `?string` (dil dəstəyi) |
| Boşluq yoxlama | `Optional.isEmpty()` | `empty()` (geniş) |
| Null təyinat | Yoxdur | `??=` operatoru (PHP 7.4+) |
| Compile-time yoxlama | Bəzi annotasiyalarla mümkün | Yoxdur |

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java statik tipli dildir və `null` onun tip sisteminin zəif nöqtəsidir. `Optional` bu problemi həll etmək üçün yaradılıb:

1. **Niyyəti ifadə etmə**: `Optional<İstifadəçi>` qaytaran metod açıq şəkildə deyir ki, "nəticə olmaya bilər". Bu, null-un "unutma riski"-ni azaldır.

2. **Funksional üslub**: `map()`, `flatMap()`, `filter()` metodları ilə null yoxlamaları zəncir şəklində yazıla bilər, bu da kodu daha oxunaqlı edir.

3. **Compile-time dəstəyi məhdudluğu**: Java-nın `Optional`-ı runtime konteynerdir, Kotlin-in `?` operatoru kimi compile-time yoxlama etmir. Bu, Java-nın geriyə uyğunluq prioritetinin nəticəsidir - mövcud null semantikasını dəyişdirmək milyonlarla mövcud kodu pozardı.

4. **Annotasiya həlləri**: `@Nullable`/`@NotNull` annotasiyaları IDE və statik analiz alətləri tərəfindən istifadə olunur, amma bu standartlaşdırılmayıb - müxtəlif kitabxanalar fərqli annotasiyalar təqdim edir.

### PHP-nin yanaşması

PHP dinamik tipli dil olaraq null-u daha pragmatik şəkildə idarə edir:

1. **Operator əsaslı**: PHP `Optional` sinfi əvəzinə dil operatorları (`??`, `?->`, `??=`) təqdim edir. Bu, daha qısa və oxunaqlı koddur, amma Java-nın funksional zəncirləmə gücü yoxdur.

2. **Nullable tiplər**: PHP 7.1 ilə gələn `?string` sintaksisi dilin tip sistemində null-u rəsmi olaraq ifadə edir. Bu, Java-nın annotasiyalarından daha güclüdür, çünki dil tərəfindən enforced olunur.

3. **Nullsafe operatoru**: `?->` operatoru Java-nın `Optional.map()` zəncirinin daha qısa alternativini təqdim edir. `$user?->getAddress()?->getCity()` yazmaq `Optional` zəncirindən daha oxunaqlıdır.

4. **Geriyə uyğunluq**: `isset()` və `empty()` PHP-nin ən köhnə funksiyalarındandır və hələ də geniş istifadə olunur. `empty()` funksiyasının `0`, `""`, `false`, `null` hamısını "boş" hesab etməsi çox mübahisəlidir, amma geriyə uyğunluq üçün dəyişdirilmir.

### Nəticə

Java `Optional` ilə null-u tip sistemi səviyyəsində idarə etməyə çalışır - bu, böyük, uzunmüddətli tətbiqlərdə faydalıdır. PHP isə operatorlar (`??`, `?->`) ilə daha qısa və praktik həllər təqdim edir. Hər iki yanaşma `NullPointerException`/null xətalarını azaltmağa xidmət edir, sadəcə fərqli fəlsəfələrlə.
