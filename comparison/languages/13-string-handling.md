# String Emalı (String Handling)

## Giriş

String (sətir) proqramlaşdırmanın ən çox istifadə olunan data tiplərindən biridir. Java və PHP string-lərlə işləmə baxımından kökündən fərqli yanaşmalar təqdim edir. Java-da string-lər immutable (dəyişilməz) obyektlərdir və xüsusi yaddaş optimallaşdırması (String Pool) tətbiq olunur. PHP-də isə string-lər daha sadə və çevik şəkildə idarə olunur, zəngin daxili funksiyalar dəsti ilə birlikdə gəlir.

## Java-da istifadəsi

### String immutability (dəyişilməzlik)

Java-da `String` obyekti yaradıldıqdan sonra onun dəyəri heç vaxt dəyişmir. Hər hansı "dəyişiklik" əməliyyatı yeni bir `String` obyekti yaradır:

```java
String ad = "Orxan";
String boyukHerf = ad.toUpperCase(); // yeni obyekt yaranır

System.out.println(ad);          // "Orxan" - orijinal dəyişməyib
System.out.println(boyukHerf);   // "ORXAN" - yeni obyekt
```

Bu, çox vacib bir dizayn qərarıdır. Immutability-nin üstünlükləri:

```java
// 1. Thread safety - eyni string-i bir neçə thread paylaşa bilər
String ortaqString = "paylaşılan məlumat";
// Heç bir thread bunu dəyişə bilməz, ona görə sinxronizasiya lazım deyil

// 2. Hash kodunun cache olunması - HashMap-lərdə performans artırır
Map<String, Integer> xəritə = new HashMap<>();
xəritə.put("açar", 42); // hash kodu hesablanır və cache olunur

// 3. Təhlükəsizlik - string dəyəri ötürüldükdən sonra dəyişdirilə bilməz
void fayla_yaz(String fayl_adi) {
    // Təhlükəsizlik yoxlaması
    if (fayl_adi.endsWith(".txt")) {
        // fayl_adi burada dəyişdirilə bilməz, çünki immutable-dır
        Files.write(Path.of(fayl_adi), data);
    }
}
```

### String Pool

Java eyni literal string-lər üçün yaddaşda yalnız bir nüsxə saxlayır:

```java
String a = "salam";
String b = "salam";
System.out.println(a == b); // true - eyni obyektə istinad edir (pool-dan)

String c = new String("salam");
System.out.println(a == c); // false - new ilə yaradılan pool-a düşmür

String d = c.intern(); // intern() ilə pool-a əlavə edə bilərik
System.out.println(a == d); // true

// equals() həmişə dəyərə görə müqayisə edir
System.out.println(a.equals(c)); // true
```

String Pool JVM-in heap yaddaşında xüsusi bir sahədir. Literal string-lər avtomatik olaraq buraya yerləşdirilir.

### StringBuilder və StringBuffer

Çoxlu string birləşdirmə əməliyyatları lazım olanda `StringBuilder` istifadə olunur, çünki hər birləşdirmədə yeni obyekt yaranmır:

```java
// Pis yanaşma - hər += yeni obyekt yaradır
String nəticə = "";
for (int i = 0; i < 1000; i++) {
    nəticə += i + ", "; // 1000 yeni String obyekti yaranır!
}

// Yaxşı yanaşma - StringBuilder istifadə edin
StringBuilder sb = new StringBuilder();
for (int i = 0; i < 1000; i++) {
    sb.append(i).append(", ");
}
String nəticə = sb.toString();

// StringBuffer - StringBuilder ilə eynidir, amma thread-safe
StringBuffer sbf = new StringBuffer();
sbf.append("multi-thread");
sbf.append(" mühitdə");
sbf.append(" təhlükəsiz");
```

`StringBuilder` və `StringBuffer` arasındakı fərq:

```java
// StringBuilder - sinxronizasiya yoxdur, daha sürətli
// Tək thread-li mühitdə istifadə edin
StringBuilder sb = new StringBuilder(100); // başlanğıc tutumu
sb.append("Java");
sb.insert(0, "Salam, ");
sb.replace(0, 5, "Hələ");
sb.delete(0, 4);
sb.reverse();
System.out.println(sb.toString());

// StringBuffer - sinxronizasiya var, daha yavaş amma thread-safe
// Çox thread-li mühitdə istifadə edin
StringBuffer sbf = new StringBuffer();
// Eyni metodlar mövcuddur
```

### String birləşdirmə və format

```java
// concat() metodu
String tam = "ad".concat(" ").concat("soyad");

// String.format() - C-nin printf-inə bənzər
String formatli = String.format("Ad: %s, Yaş: %d, Orta: %.2f", "Orxan", 25, 4.85);
System.out.println(formatli); // Ad: Orxan, Yaş: 25, Orta: 4.85

// formatted() metodu - Java 15+
String mesaj = "Salam, %s! Sənin %d balın var.".formatted("Əli", 95);

// String.join() - elementləri birləşdirir
String birləşmiş = String.join(", ", "alma", "armud", "portağal");
System.out.println(birləşmiş); // alma, armud, portağal

// Java 11+ metodları
String boşluqlu = "  salam dünya  ";
System.out.println(boşluqlu.strip());        // "salam dünya"
System.out.println(boşluqlu.stripLeading());  // "salam dünya  "
System.out.println(boşluqlu.stripTrailing()); // "  salam dünya"
System.out.println("ab".repeat(3));           // "ababab"
System.out.println("".isBlank());             // true
System.out.println(" ".isBlank());            // true
```

### Java 15 Text Blocks (mətn blokları)

Çoxsətirli string-ləri yazmaq üçün text block-lar təqdim edildi:

```java
// Köhnə yanaşma
String json = "{\n" +
    "    \"ad\": \"Orxan\",\n" +
    "    \"yaş\": 25\n" +
    "}";

// Text block - Java 15+
String jsonBlok = """
        {
            "ad": "Orxan",
            "yaş": 25
        }
        """;

// SQL sorğusu
String sql = """
        SELECT u.ad, u.soyad
        FROM istifadəçilər u
        WHERE u.yaş > 18
        ORDER BY u.ad
        """;

// HTML şablonu
String html = """
        <html>
            <body>
                <h1>Salam, %s!</h1>
                <p>Xoş gəldiniz</p>
            </body>
        </html>
        """.formatted("Orxan");
```

### Faydalı String metodları

```java
String mətn = "Java Proqramlaşdırma Dili";

// Əsas metodlar
System.out.println(mətn.length());            // 25
System.out.println(mətn.charAt(0));           // 'J'
System.out.println(mətn.indexOf("Proqram"));  // 5
System.out.println(mətn.substring(5, 20));    // "Proqramlaşdırma"
System.out.println(mətn.contains("Java"));    // true
System.out.println(mətn.startsWith("Java"));  // true
System.out.println(mətn.endsWith("Dili"));    // true

// Bölmə və birləşdirmə
String[] sözlər = mətn.split(" ");
// ["Java", "Proqramlaşdırma", "Dili"]

// Dəyişdirmə
String yeni = mətn.replace("Java", "PHP");
System.out.println(yeni); // "PHP Proqramlaşdırma Dili"

// Regex ilə dəyişdirmə
String rəqəmsiz = "abc123def456".replaceAll("[0-9]", "");
System.out.println(rəqəmsiz); // "abcdef"

// char[] ilə işləmə
char[] simvollar = mətn.toCharArray();
```

## PHP-də istifadəsi

### String əsasları və interpolasiya

PHP-də string-lər mutable-dır (dəyişdirilə bilir) və iki növ tırnak işarəsi ilə yazılır:

```php
// Tək dırnaq - dəyişən interpolasiyası yoxdur
$ad = 'Orxan';
$sətir = 'Salam, $ad'; // "Salam, $ad" - olduğu kimi qalır

// Cüt dırnaq - dəyişən interpolasiyası var
$sətir = "Salam, $ad"; // "Salam, Orxan"

// Mürəkkəb interpolasiya
$istifadəçi = ['ad' => 'Orxan', 'yaş' => 25];
echo "Ad: {$istifadəçi['ad']}, Yaş: {$istifadəçi['yaş']}";

// Obyekt xüsusiyyətləri
$obj = new stdClass();
$obj->ad = "Əli";
echo "Salam, {$obj->ad}!";

// Metod çağırışı interpolasiyada mümkün deyil
// echo "Nəticə: {$obj->metod()}"; // İşləmir
// Bunun əvəzinə:
echo "Nəticə: " . $obj->metod();
```

### Heredoc və Nowdoc

```php
// Heredoc - cüt dırnaq kimi işləyir, interpolasiya var
$ad = "Orxan";
$yaş = 25;

$heredoc = <<<EOT
Salam, $ad!
Sənin yaşın $yaş-dır.
Bu çoxsətirli string-dir.
EOT;

// Nowdoc - tək dırnaq kimi işləyir, interpolasiya yoxdur
$nowdoc = <<<'EOT'
Bu $ad interpolasiya olunmur.
Hər şey olduğu kimi qalır.
EOT;

// PHP 7.3+ - bağlayan identifikator indentasiya edilə bilər
$json = <<<JSON
    {
        "ad": "$ad",
        "yaş": $yaş
    }
    JSON;
```

### Əsas string funksiyaları

```php
$mətn = "PHP Proqramlaşdırma Dili";

// Uzunluq
echo strlen($mətn);    // 24 (bayt sayı)
echo mb_strlen($mətn); // 24 (simvol sayı - multibyte üçün vacib)

// Axtarış
echo strpos($mətn, "Proqram");  // 4 - ilk tapılan mövqe
echo strrpos($mətn, "a");       // son tapılan mövqe
echo str_contains($mətn, "PHP"); // true (PHP 8.0+)
echo str_starts_with($mətn, "PHP"); // true (PHP 8.0+)
echo str_ends_with($mətn, "Dili"); // true (PHP 8.0+)

// Kəsmə (substring)
echo substr($mətn, 4, 15);  // "Proqramlaşdırma"
echo substr($mətn, -4);     // "Dili" (sondan)

// Dəyişdirmə
echo str_replace("PHP", "Java", $mətn);        // "Java Proqramlaşdırma Dili"
echo str_ireplace("php", "Java", $mətn);        // Böyük/kiçik hərfə həssas deyil
echo str_replace(
    ["PHP", "Dili"],
    ["Java", "Language"],
    $mətn
); // "Java Proqramlaşdırma Language"

// Böyük/kiçik hərf
echo strtoupper($mətn);  // "PHP PROQRAMLAŞDIRMA DİLİ"
echo strtolower($mətn);  // "php proqramlaşdırma dili"
echo ucfirst("salam");   // "Salam"
echo ucwords("salam dünya"); // "Salam Dünya"
```

### explode/implode və bölmə

```php
// explode - string-i massivə bölür
$csv = "alma,armud,portağal,banan";
$meyvələr = explode(",", $csv);
// ['alma', 'armud', 'portağal', 'banan']

// implode (join) - massivi string-ə birləşdirir
$birləşmiş = implode(", ", $meyvələr);
// "alma, armud, portağal, banan"

// str_split - string-i hissələrə bölür
$hərflər = str_split("Salam", 2);
// ['Sa', 'la', 'm']

// strtok - token-lərə bölür
$token = strtok("Bu bir cümlə", " ");
while ($token !== false) {
    echo $token . "\n";
    $token = strtok(" ");
}

// wordwrap - sözləri bükür
$uzun_mətn = "Bu çox uzun bir cümlə olduğu üçün bükülməlidir";
echo wordwrap($uzun_mətn, 20, "\n", true);
```

### String formatlama

```php
// sprintf - formatlanmış string qaytarır
$formatli = sprintf("Ad: %s, Yaş: %d, Orta: %.2f", "Orxan", 25, 4.85);
echo $formatli; // Ad: Orxan, Yaş: 25, Orta: 4.85

// printf - birbaşa çıxış verir
printf("Qiymət: $%.2f\n", 19.99);

// number_format - rəqəm formatlama
echo number_format(1234567.891, 2, ',', '.'); // 1.234.567,89

// str_pad - string-i doldurur
echo str_pad("42", 5, "0", STR_PAD_LEFT);  // "00042"
echo str_pad("Salam", 10, ".", STR_PAD_RIGHT); // "Salam....."
echo str_pad("Salam", 11, "-", STR_PAD_BOTH);  // "---Salam---"
```

### mb_string - Unicode dəstəyi

PHP-də çoxbaytlı (multibyte) simvollarla düzgün işləmək üçün `mb_string` genişlənməsi istifadə olunur:

```php
$mətn = "Azərbaycan dili";

// Adi funksiyalar baytlarla işləyir
echo strlen($mətn);     // bayt sayı (UTF-8-də hər Azərbaycan hərfi fərqli bayt sayı ola bilər)

// mb_ funksiyaları simvollarla işləyir
echo mb_strlen($mətn);         // düzgün simvol sayı
echo mb_substr($mətn, 0, 10);  // düzgün kəsmə
echo mb_strtoupper($mətn);     // düzgün böyük hərf çevrilməsi
echo mb_strpos($mətn, "dili"); // düzgün mövqe

// Kodlaşdırma çevrilməsi
$utf8 = mb_convert_encoding($mətn, 'UTF-8', 'ISO-8859-1');

// Daxili kodlaşdırmanı təyin etmək
mb_internal_encoding('UTF-8');

// Regex ilə multibyte
echo mb_ereg_replace('[a-z]', '*', $mətn);

// mb_detect_encoding - kodlaşdırmanı aşkarlama
$kodlaşdırma = mb_detect_encoding($mətn, ['UTF-8', 'ISO-8859-1', 'Windows-1252']);
echo $kodlaşdırma; // UTF-8
```

### Təmizləmə və yoxlama

```php
// Boşluqları təmizləmə
echo trim("  salam  ");       // "salam"
echo ltrim("  salam  ");      // "salam  "
echo rtrim("  salam  ");      // "  salam"

// HTML təmizləmə
echo htmlspecialchars('<script>alert("xss")</script>');
// &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;

echo strip_tags("<p>Salam <b>dünya</b></p>", "<b>");
// "Salam <b>dünya</b>"

// URL kodlama
echo urlencode("salam dünya");  // salam+d%C3%BCnya
echo rawurlencode("salam dünya"); // salam%20d%C3%BCnya

// Base64
echo base64_encode("gizli məlumat");
echo base64_decode("Z2l6bGkgbcmZbHVtYXQ=");

// Hash
echo md5("şifrə");
echo sha1("şifrə");
echo hash('sha256', "şifrə");
```

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| String tipi | Obyekt (immutable) | Primitiv tip (mutable) |
| Yaddaş modeli | String Pool optimallaşdırması | Copy-on-write mexanizmi |
| Birləşdirmə | `+` operatoru, `StringBuilder` | `.` operatoru, interpolasiya |
| Interpolasiya | Yoxdur (text block-larda `formatted()`) | Cüt dırnaqlarda dəyişən interpolasiyası |
| Çoxsətirli | Text blocks (Java 15+) | Heredoc/Nowdoc |
| Unicode | Daxili UTF-16 dəstəyi | `mb_string` genişlənməsi lazımdır |
| Müqayisə | `equals()` metodu, `==` referans müqayisəsi | `==` dəyər müqayisəsi, `===` tip+dəyər |
| Format | `String.format()`, `formatted()` | `sprintf()`, `printf()` |
| Bölmə | `split()` metodu | `explode()` funksiyası |
| Birləşdirmə | `String.join()` | `implode()` funksiyası |
| Trim | `strip()` (Java 11+) | `trim()`, `ltrim()`, `rtrim()` |
| Thread safety | `String` immutable, `StringBuffer` synced | Tək thread mühit, problem yoxdur |

## Niyə belə fərqlər var?

### Java niyə String-i immutable etdi?

Java-nın String-i immutable etməsinin bir neçə əsas səbəbi var:

1. **Thread təhlükəsizliyi**: Java çox thread-li proqramlaşdırma üçün nəzərdə tutulub. Immutable string-lər sinxronizasiya olmadan thread-lər arasında təhlükəsiz paylaşıla bilər.

2. **String Pool optimallaşdırması**: Eyni dəyərə malik string-lər yaddaşda bir dəfə saxlanıla bilər, çünki heç kim onları dəyişdirə bilməz. Bu, böyük tətbiqlərdə əhəmiyyətli yaddaş qənaəti təmin edir.

3. **Hash kodunun cache olunması**: `HashMap` və `HashSet` kimi kolleksiyalarda string açarlar çox istifadə olunur. Hash kodu yalnız bir dəfə hesablanıb cache oluna bilər.

4. **Təhlükəsizlik**: Verilənlər bazası bağlantı string-ləri, fayl yolları və s. ötürüldükdən sonra dəyişdirilə bilməz.

### PHP niyə fərqli yanaşır?

1. **Web odaqlı dizayn**: PHP hər sorğu üçün yeni proses başladığından, thread safety narahatlığı yoxdur. String-lərin mutable olması daha sadə və sürətli API təmin edir.

2. **Praktiklik**: PHP-nin fəlsəfəsi "işi tez görmək"-dir. Dəyişən interpolasiyası, zəngin string funksiyaları və heredoc/nowdoc kimi xüsusiyyətlər web development-də çox lazımlı olan string emalını asanlaşdırır.

3. **Copy-on-write**: PHP daxildə copy-on-write mexanizmi istifadə edir. Yəni string dəyişənə qədər kopyalanmır, bu da yaddaş istifadəsini optimallaşdırır.

4. **Unicode problemi**: PHP əvvəlcə ASCII dünyası üçün yazılıb. Unicode dəstəyi sonradan `mb_string` genişlənməsi ilə əlavə edilib. Java isə başlanğıcdan Unicode (UTF-16) üzərində qurulub.

### Nəticə

Java string emalında təhlükəsizlik və performans optimallaşdırmasını ön plana çıxarır - bu, onun güclü tipli, çox thread-li tətbiqlər üçün nəzərdə tutulmuş dil olmasının nəticəsidir. PHP isə rahatlıq və sürətli inkişafı prioritet edir - web development-də ən çox lazım olan şey string-lərlə tez və rahat işləməkdir.
