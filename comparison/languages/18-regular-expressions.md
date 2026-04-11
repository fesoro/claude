# Regulyar İfadələr (Regular Expressions)

## Giriş

Regulyar ifadələr (regex) mətn axtarışı, validasiya və transformasiya üçün güclü bir alətdir. Hər iki dil regex-i dəstəkləyir, amma fərqli API-lər vasitəsilə. Java `Pattern` və `Matcher` sinifləri ilə OOP yanaşma istifadə edir, PHP isə `preg_*` funksiyaları ilə PCRE (Perl Compatible Regular Expressions) mühərrikini təqdim edir.

## Java-da istifadəsi

### Pattern və Matcher əsasları

```java
import java.util.regex.Pattern;
import java.util.regex.Matcher;

// Pattern derleme (compile)
Pattern nümunə = Pattern.compile("\\d+"); // rəqəm ardıcıllığı
Matcher uyğunlaşdırıcı = nümunə.matcher("Mən 25 yaşındayam, qardaşım 30");

// find() - növbəti uyğunluğu tapır
while (uyğunlaşdırıcı.find()) {
    System.out.println("Tapıldı: " + uyğunlaşdırıcı.group());
    System.out.println("Mövqe: " + uyğunlaşdırıcı.start() + "-" + uyğunlaşdırıcı.end());
}
// Tapıldı: 25, Mövqe: 4-6
// Tapıldı: 30, Mövqe: 27-29

// matches() - bütün string uyğun gəlməlidir
boolean tam = Pattern.matches("\\d+", "12345");   // true
boolean yarım = Pattern.matches("\\d+", "abc123"); // false

// Qısa yol (pattern-i derleme etmədən)
boolean nəticə = "test@email.com".matches("[\\w.]+@[\\w.]+\\.[a-z]{2,}");

// Pattern.compile() ilə flag-lar
Pattern hərfHəssas = Pattern.compile("salam", Pattern.CASE_INSENSITIVE);
Matcher m = hərfHəssas.matcher("SALAM dünya");
System.out.println(m.find()); // true

// Çoxlu flag
Pattern çoxlu = Pattern.compile(
    "^salam.*dünya$",
    Pattern.CASE_INSENSITIVE | Pattern.MULTILINE | Pattern.DOTALL
);
```

### Qruplar (Groups)

```java
// Nömrəli qruplar
Pattern tarixPattern = Pattern.compile("(\\d{2})/(\\d{2})/(\\d{4})");
Matcher m = tarixPattern.matcher("Tarix: 11/04/2026");

if (m.find()) {
    System.out.println("Tam uyğunluq: " + m.group(0)); // 11/04/2026
    System.out.println("Gün: " + m.group(1));           // 11
    System.out.println("Ay: " + m.group(2));             // 04
    System.out.println("İl: " + m.group(3));             // 2026
    System.out.println("Qrup sayı: " + m.groupCount());  // 3
}

// Adlı qruplar (named groups)
Pattern adlıPattern = Pattern.compile(
    "(?<gun>\\d{2})/(?<ay>\\d{2})/(?<il>\\d{4})"
);
Matcher m2 = adlıPattern.matcher("11/04/2026");

if (m2.find()) {
    System.out.println("Gün: " + m2.group("gun")); // 11
    System.out.println("Ay: " + m2.group("ay"));    // 04
    System.out.println("İl: " + m2.group("il"));    // 2026
}

// Optional qrup
Pattern optPattern = Pattern.compile("(\\w+)\\s*(\\(.*?\\))?");
Matcher m3 = optPattern.matcher("funksiya (parametr)");
if (m3.find()) {
    System.out.println(m3.group(1)); // funksiya
    System.out.println(m3.group(2)); // (parametr)
}

// Non-capturing qrup
Pattern ncPattern = Pattern.compile("(?:https?://)?(\\w+\\.\\w+)");
Matcher m4 = ncPattern.matcher("https://example.com");
if (m4.find()) {
    System.out.println(m4.group(1)); // example.com
}
```

### Dəyişdirmə (Replace)

```java
// replaceAll - bütün uyğunluqları dəyişdir
String nəticə = "abc 123 def 456".replaceAll("\\d+", "***");
System.out.println(nəticə); // "abc *** def ***"

// replaceFirst - yalnız ilkini dəyişdir
String birinci = "abc 123 def 456".replaceFirst("\\d+", "***");
System.out.println(birinci); // "abc *** def 456"

// Qrup referansları ilə dəyişdirmə
String tarix = "2026-04-11";
String çevrilmiş = tarix.replaceAll(
    "(\\d{4})-(\\d{2})-(\\d{2})",
    "$3/$2/$1"
);
System.out.println(çevrilmiş); // 11/04/2026

// Adlı qrup referansı
String adlıÇevrilmiş = tarix.replaceAll(
    "(?<il>\\d{4})-(?<ay>\\d{2})-(?<gun>\\d{2})",
    "${gun}.${ay}.${il}"
);
System.out.println(adlıÇevrilmiş); // 11.04.2026

// Matcher ilə dinamik dəyişdirmə
Pattern p = Pattern.compile("\\b(\\w)");
Matcher m = p.matcher("salam dünya necəsən");
StringBuilder sb = new StringBuilder();
while (m.find()) {
    m.appendReplacement(sb, m.group(1).toUpperCase());
}
m.appendTail(sb);
System.out.println(sb.toString()); // "Salam Dünya Necəsən"

// Java 9+ - replaceAll ilə lambda
String nəticə2 = Pattern.compile("\\d+")
    .matcher("qiymət: 100 manat, endirim: 20 manat")
    .replaceAll(mr -> String.valueOf(Integer.parseInt(mr.group()) * 2));
System.out.println(nəticə2); // "qiymət: 200 manat, endirim: 40 manat"
```

### Bölmə (Split)

```java
// String.split()
String[] hissələr = "alma,armud,,portağal".split(",");
// ["alma", "armud", "", "portağal"]

// Limit ilə
String[] məhdud = "a:b:c:d:e".split(":", 3);
// ["a", "b", "c:d:e"]

// Pattern.split()
Pattern p = Pattern.compile("\\s*,\\s*");
String[] təmiz = p.split("alma , armud , portağal");
// ["alma", "armud", "portağal"]

// Boş string-ləri sondan çıxarır (default davranış)
String[] sonBoş = "a,,b,,".split(",");
// ["a", "", "b"] - sondakı boş elementlər çıxarılır

// -1 limit ilə sondakı boş elementlər saxlanılır
String[] hamısı = "a,,b,,".split(",", -1);
// ["a", "", "b", "", ""]
```

### Regex flag-ları və xüsusi konstruksiyalar

```java
// Flag-lar
// Pattern.CASE_INSENSITIVE (i) - böyük/kiçik hərf fərqi yoxdur
// Pattern.MULTILINE (m) - ^ və $ hər sətirin əvvəli/sonuna uyğun
// Pattern.DOTALL (s) - . simvolu \n-ə də uyğun gəlir
// Pattern.UNICODE_CHARACTER_CLASS (U) - Unicode sinifləri
// Pattern.COMMENTS (x) - boşluqlar və şərhlər icazəli

// Inline flag-lar
Pattern p = Pattern.compile("(?i)salam"); // case insensitive

// COMMENTS flag ilə oxunaqlı regex
Pattern email = Pattern.compile("""
    ^                          # sətirin əvvəli
    [\\w.%+-]+                 # istifadəçi adı
    @                          # @ simvolu
    [\\w.-]+                   # domen adı
    \\.                        # nöqtə
    [a-zA-Z]{2,}               # üst səviyyə domen
    $                          # sətirin sonu
    """, Pattern.COMMENTS);

// Lookahead və Lookbehind
// Positive lookahead: (?=...)
Pattern p1 = Pattern.compile("\\w+(?=@)"); // @-dan əvvəlki söz
Matcher m1 = p1.matcher("user@email.com");
if (m1.find()) System.out.println(m1.group()); // "user"

// Negative lookahead: (?!...)
Pattern p2 = Pattern.compile("\\d+(?!\\.)"); // nöqtə ilə bitməyən rəqəm

// Positive lookbehind: (?<=...)
Pattern p3 = Pattern.compile("(?<=\\$)\\d+"); // $-dan sonrakı rəqəm
Matcher m3 = p3.matcher("Qiymət: $100");
if (m3.find()) System.out.println(m3.group()); // "100"

// Negative lookbehind: (?<!...)
Pattern p4 = Pattern.compile("(?<!\\d)\\w+"); // rəqəmlə başlamayan söz
```

### Praktik nümunələr

```java
// Email validasiyası
public static boolean emailDüzgündür(String email) {
    Pattern pattern = Pattern.compile(
        "^[\\w.%+-]+@[\\w.-]+\\.[a-zA-Z]{2,}$"
    );
    return pattern.matcher(email).matches();
}

// Telefon nömrəsi çıxarma
public static List<String> telefonlarıTap(String mətn) {
    Pattern pattern = Pattern.compile(
        "(?:\\+994|0)(\\d{2})[\\s-]?(\\d{3})[\\s-]?(\\d{2})[\\s-]?(\\d{2})"
    );
    Matcher matcher = pattern.matcher(mətn);
    List<String> nəticələr = new ArrayList<>();
    while (matcher.find()) {
        nəticələr.add(matcher.group());
    }
    return nəticələr;
}

// URL parse etmə
Pattern urlPattern = Pattern.compile(
    "(?<protokol>https?)://(?<domen>[\\w.-]+)(?::(?<port>\\d+))?(?<yol>/[\\w/.-]*)?"
);
Matcher um = urlPattern.matcher("https://example.com:8080/api/users");
if (um.find()) {
    System.out.println("Protokol: " + um.group("protokol")); // https
    System.out.println("Domen: " + um.group("domen"));       // example.com
    System.out.println("Port: " + um.group("port"));         // 8080
    System.out.println("Yol: " + um.group("yol"));           // /api/users
}

// Compile edilmiş pattern-in performans üstünlüyü
// Çox dəfə istifadə olunan pattern-i saxlayın
private static final Pattern RƏQƏM = Pattern.compile("\\d+");

public static List<Integer> rəqəmləriTap(String mətn) {
    return RƏQƏM.matcher(mətn)
        .results() // Java 9+
        .map(mr -> Integer.parseInt(mr.group()))
        .toList();
}
```

## PHP-də istifadəsi

### preg_match - ilk uyğunluğu tapmaq

```php
// Əsas istifadə
$nəticə = preg_match('/\d+/', 'Mən 25 yaşındayam', $uyğunluqlar);

if ($nəticə) {
    echo "Tapıldı: " . $uyğunluqlar[0]; // 25
}

// Qruplar ilə
preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', '11/04/2026', $m);
echo "Gün: " . $m[1]; // 11
echo "Ay: " . $m[2];  // 04
echo "İl: " . $m[3];  // 2026
echo "Tam: " . $m[0]; // 11/04/2026

// Adlı qruplar
preg_match(
    '/(?P<gun>\d{2})\/(?P<ay>\d{2})\/(?P<il>\d{4})/',
    '11/04/2026',
    $m
);
echo "Gün: " . $m['gun']; // 11
echo "Ay: " . $m['ay'];   // 04
echo "İl: " . $m['il'];   // 2026

// PREG_OFFSET_CAPTURE - mövqe məlumatı ilə
preg_match('/\d+/', 'abc 123 def', $m, PREG_OFFSET_CAPTURE);
echo "Dəyər: " . $m[0][0]; // 123
echo "Mövqe: " . $m[0][1]; // 4

// Qaytarma dəyərləri
// 1 - uyğunluq tapıldı
// 0 - tapılmadı
// false - xəta baş verdi
```

### preg_match_all - bütün uyğunluqları tapmaq

```php
// Bütün rəqəmləri tapmaq
$say = preg_match_all('/\d+/', 'Mən 25 yaşındayam, qardaşım 30', $m);
echo "Tapılan: $say\n"; // 2
print_r($m[0]); // ['25', '30']

// Qruplar ilə - PREG_PATTERN_ORDER (default)
preg_match_all(
    '/(\w+)@(\w+\.\w+)/',
    'user@mail.com və admin@site.org',
    $m
);
print_r($m[0]); // ['user@mail.com', 'admin@site.org'] - tam uyğunluqlar
print_r($m[1]); // ['user', 'admin'] - 1-ci qrup
print_r($m[2]); // ['mail.com', 'site.org'] - 2-ci qrup

// PREG_SET_ORDER - hər uyğunluq ayrı massiv
preg_match_all(
    '/(\w+)@(\w+\.\w+)/',
    'user@mail.com və admin@site.org',
    $m,
    PREG_SET_ORDER
);
foreach ($m as $uyğunluq) {
    echo "Tam: {$uyğunluq[0]}, İstifadəçi: {$uyğunluq[1]}, Domen: {$uyğunluq[2]}\n";
}
// Tam: user@mail.com, İstifadəçi: user, Domen: mail.com
// Tam: admin@site.org, İstifadəçi: admin, Domen: site.org

// Adlı qruplar ilə
preg_match_all(
    '/(?P<ad>\w+):(?P<dəyər>\d+)/',
    'yaş:25 boy:180 çəki:75',
    $m,
    PREG_SET_ORDER
);
foreach ($m as $cüt) {
    echo "{$cüt['ad']} = {$cüt['dəyər']}\n";
}
// yaş = 25
// boy = 180
// çəki = 75
```

### preg_replace - dəyişdirmə

```php
// Sadə dəyişdirmə
$nəticə = preg_replace('/\d+/', '***', 'Tel: 050-123-45-67');
echo $nəticə; // Tel: ***-***-***-***

// Qrup referansları ilə
$tarix = '2026-04-11';
$çevrilmiş = preg_replace(
    '/(\d{4})-(\d{2})-(\d{2})/',
    '$3.$2.$1',
    $tarix
);
echo $çevrilmiş; // 11.04.2026

// Adlı qrup referansları
$çevrilmiş2 = preg_replace(
    '/(?P<il>\d{4})-(?P<ay>\d{2})-(?P<gun>\d{2})/',
    '${gun}/${ay}/${il}',
    $tarix
);

// Massiv ilə çoxlu dəyişdirmə
$təmiz = preg_replace(
    ['/\s+/', '/[^\w\s]/', '/^\s+|\s+$/'],
    [' ', '', ''],
    '  Salam,  dünya!  '
);
echo $təmiz; // "Salam dünya"

// Limit ilə
$birinci_dəyiş = preg_replace('/\d+/', '***', 'a1 b2 c3', 1);
echo $birinci_dəyiş; // "a*** b2 c3"

// preg_replace_callback - callback ilə dəyişdirmə
$böyükHərf = preg_replace_callback(
    '/\b(\w)/',
    function ($m) {
        return mb_strtoupper($m[1]);
    },
    'salam dünya necəsən'
);
echo $böyükHərf; // "Salam Dünya Necəsən"

// Rəqəmləri iki qat artırmaq
$artırılmış = preg_replace_callback(
    '/\d+/',
    fn($m) => intval($m[0]) * 2,
    'qiymət: 100 manat, endirim: 20 manat'
);
echo $artırılmış; // "qiymət: 200 manat, endirim: 40 manat"

// preg_replace_callback_array - PHP 7.0+
$nəticə = preg_replace_callback_array(
    [
        '/\d+/' => fn($m) => '[rəqəm]',
        '/[a-z]+/i' => fn($m) => '[söz]',
    ],
    'abc 123 def 456'
);
```

### preg_split - bölmə

```php
// Sadə bölmə
$hissələr = preg_split('/[\s,;]+/', 'alma, armud; portağal banan');
print_r($hissələr); // ['alma', 'armud', 'portağal', 'banan']

// Limit ilə
$məhdud = preg_split('/:/', 'a:b:c:d:e', 3);
print_r($məhdud); // ['a', 'b', 'c:d:e']

// Boş string-ləri çıxar
$təmiz = preg_split('/,/', 'a,,b,,c', -1, PREG_SPLIT_NO_EMPTY);
print_r($təmiz); // ['a', 'b', 'c']

// Ayırıcını da daxil et
$ilə_ayırıcı = preg_split('/(,)/', 'a,b,c', -1, PREG_SPLIT_DELIM_CAPTURE);
print_r($ilə_ayırıcı); // ['a', ',', 'b', ',', 'c']

// Hər simvolu ayırmaq (Unicode-a uyğun)
$simvollar = preg_split('//u', 'Salam', -1, PREG_SPLIT_NO_EMPTY);
print_r($simvollar); // ['S', 'a', 'l', 'a', 'm']

// CamelCase-i ayırmaq
$sözlər = preg_split('/(?=[A-Z])/', 'camelCaseString', -1, PREG_SPLIT_NO_EMPTY);
print_r($sözlər); // ['camel', 'Case', 'String']
```

### PCRE modifikatorları

```php
// i - Case insensitive
preg_match('/salam/i', 'SALAM');  // uyğun gəlir

// m - Multiline (^ və $ hər sətirə tətbiq olunur)
preg_match_all('/^\w+/m', "birinci\nikinci\nüçüncü", $m);
print_r($m[0]); // ['birinci', 'ikinci', 'üçüncü']

// s - Dotall (. simvolu \n-ə də uyğun gəlir)
preg_match('/başla.*bitir/s', "başla\nnəsə\nbitir", $m);
echo $m[0]; // "başla\nnəsə\nbitir"

// u - UTF-8 (Unicode dəstəyi)
preg_match('/\w+/u', 'Azərbaycan', $m);
echo $m[0]; // düzgün unicode nəticə

// x - Extended (boşluqlar və şərhlər icazəli)
$email_pattern = '/
    ^                   # sətirin əvvəli
    [\w.%+-]+           # istifadəçi adı
    @                   # at simvolu
    [\w.-]+             # domen
    \.                  # nöqtə
    [a-zA-Z]{2,}        # TLD
    $                   # sətirin sonu
/x';
preg_match($email_pattern, 'test@email.com', $m);

// U - Ungreedy (default olaraq lazy edir)
// Greedy (default): .* mümkün qədər çox tutur
preg_match('/<.*>/', '<a>text</a>', $m);
echo $m[0]; // "<a>text</a>" - hamısını tutur

// Lazy: .*? mümkün qədər az tutur
preg_match('/<.*?>/', '<a>text</a>', $m);
echo $m[0]; // "<a>" - yalnız ilk tag

// U modifikatoru ilə default-u dəyişir
preg_match('/<.*>/U', '<a>text</a>', $m);
echo $m[0]; // "<a>" - U ilə default lazy olur

// Birlikdə istifadə
preg_match('/pattern/imsu', $string, $matches);
```

### Praktik nümunələr

```php
// Email validasiyası
function emailDüzgündür(string $email): bool {
    return (bool) preg_match(
        '/^[\w.%+-]+@[\w.-]+\.[a-zA-Z]{2,}$/',
        $email
    );
}

// Telefon nömrəsi formatlama
function telefonFormatla(string $nömrə): string {
    $təmiz = preg_replace('/[^\d+]/', '', $nömrə);
    return preg_replace(
        '/^\+?994?(\d{2})(\d{3})(\d{2})(\d{2})$/',
        '+994 ($1) $2-$3-$4',
        $təmiz
    );
}
echo telefonFormatla('+994501234567'); // +994 (50) 123-45-67

// HTML tag-larını çıxarmaq (sadə)
function taglarıÇıxar(string $html): string {
    return preg_replace('/<[^>]+>/', '', $html);
}

// Slug yaratmaq
function slugYarat(string $mətn): string {
    $slug = mb_strtolower($mətn);
    $slug = preg_replace('/[^\w\s-]/u', '', $slug);
    $slug = preg_replace('/[\s_]+/', '-', $slug);
    $slug = preg_replace('/^-+|-+$/', '', $slug);
    return $slug;
}
echo slugYarat("Salam Dünya! Bu test-dir."); // "salam-dünya-bu-test-dir"

// Güclü şifrə yoxlaması
function şifrəGüclüdür(string $şifrə): array {
    $xətalar = [];

    if (strlen($şifrə) < 8) {
        $xətalar[] = 'Minimum 8 simvol olmalıdır';
    }
    if (!preg_match('/[A-Z]/', $şifrə)) {
        $xətalar[] = 'Ən azı bir böyük hərf olmalıdır';
    }
    if (!preg_match('/[a-z]/', $şifrə)) {
        $xətalar[] = 'Ən azı bir kiçik hərf olmalıdır';
    }
    if (!preg_match('/\d/', $şifrə)) {
        $xətalar[] = 'Ən azı bir rəqəm olmalıdır';
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:,.<>?]/', $şifrə)) {
        $xətalar[] = 'Ən azı bir xüsusi simvol olmalıdır';
    }

    return $xətalar;
}

// Markdown başlıqlarını parse etmək
function başlıqlarıTap(string $markdown): array {
    preg_match_all(
        '/^(#{1,6})\s+(.+)$/m',
        $markdown,
        $m,
        PREG_SET_ORDER
    );

    return array_map(fn($match) => [
        'səviyyə' => strlen($match[1]),
        'başlıq' => $match[2],
    ], $m);
}
```

### Xəta idarəsi

```php
// preg_last_error() - son xətanı yoxla
$nəticə = @preg_match('/(?:\D+|<\d+>)*[!?]/', str_repeat('a', 10000));
if ($nəticə === false) {
    $xəta = preg_last_error();
    match ($xəta) {
        PREG_NO_ERROR => 'Xəta yoxdur',
        PREG_INTERNAL_ERROR => 'Daxili xəta',
        PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limiti aşıldı',
        PREG_RECURSION_LIMIT_ERROR => 'Rekursiya limiti aşıldı',
        PREG_BAD_UTF8_ERROR => 'Xətalı UTF-8',
        PREG_BAD_UTF8_OFFSET_ERROR => 'Xətalı UTF-8 offset',
        PREG_JIT_STACKLIMIT_ERROR => 'JIT stack limiti aşıldı',
    };
}

// preg_last_error_msg() - PHP 8.0+
if (preg_match('/invalid[/', 'test') === false) {
    echo preg_last_error_msg(); // xəta mesajı
}

// preg_quote() - xüsusi simvolları escape et
$axtarış = 'user@email.com';
$escaped = preg_quote($axtarış, '/');
echo $escaped; // user\@email\.com
preg_match("/$escaped/", 'user@email.com', $m);
```

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Mühərrik | java.util.regex (NFA) | PCRE (Perl Compatible) |
| Pattern yaratma | `Pattern.compile("\\d+")` | `'/\d+/'` (delimiter ilə) |
| Escape | İkiqat: `\\d`, `\\w` | Tək: `\d`, `\w` (tək dırnaqlı string-də) |
| İlk uyğunluq | `Matcher.find()` | `preg_match()` |
| Bütün uyğunluqlar | `Matcher.find()` dövrü, `results()` | `preg_match_all()` |
| Dəyişdirmə | `String.replaceAll()`, `Matcher.replaceAll()` | `preg_replace()` |
| Callback dəyişdirmə | `Matcher.replaceAll(mr -> ...)` (Java 9+) | `preg_replace_callback()` |
| Bölmə | `String.split()`, `Pattern.split()` | `preg_split()` |
| Adlı qruplar | `(?<ad>...)`, `group("ad")` | `(?P<ad>...)`, `$m['ad']` |
| Flag-lar | `Pattern.CASE_INSENSITIVE` və s. | `/pattern/i` (inline) |
| Pattern cache | Manuel (`Pattern.compile()`) | Avtomatik (PCRE cache) |
| Unicode | `Pattern.UNICODE_CHARACTER_CLASS` | `/u` modifikatoru |

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java regex üçün OOP API təqdim edir:

1. **Pattern derleme**: `Pattern.compile()` regex-i bir dəfə derləyir və təkrar istifadə üçün obyekt yaradır. Bu, performans üçün çox vacibdir - eyni pattern-i dəfələrlə istifadə edərkən hər dəfə yenidən derleme baş vermir.

2. **İkiqat escape**: Java-da regex string literal daxilində yazıldığı üçün `\d` yerinə `\\d` yazmaq lazımdır. Bu, çox qarışıq ola bilər: `\\\\` yalnız bir literal `\` ilə uyğun gəlir. Text block-lar (Java 15+) bunu bir az asanlaşdırır.

3. **OOP API**: `Pattern` və `Matcher` sinifləri vəziyyəti (state) saxlayır - `find()` çağırıldıqca növbəti uyğunluğa keçir. Bu, böyük mətnlərdə yaddaş effektivdir.

### PHP-nin yanaşması

PHP Perl-dən ilhamlanmış PCRE mühərrikini istifadə edir:

1. **Delimiter sistemi**: PHP-də pattern `/.../` kimi delimiter ilə yazılır. Bu, flag-ları sonuna əlavə etməyi asanlaşdırır: `/pattern/imsu`. Delimiter kimi istənilən simvol istifadə oluna bilər: `#pattern#`, `~pattern~`.

2. **Tək escape**: PHP-nin tək dırnaqlı string-lərində `\d` birbaşa yazıla bilər, ikiqat escape lazım deyil. Bu, regex-ləri daha oxunaqlı edir.

3. **Prosedural API**: `preg_match()`, `preg_replace()` kimi funksiyalar sadə və birbaşadır. Pattern avtomatik olaraq PCRE tərəfindən cache olunur, ona görə manual `compile()` lazım deyil.

4. **PCRE gücü**: PHP-nin PCRE mühərriki Perl-in regex funksionallığının böyük hissəsini dəstəkləyir - rekursiv pattern-lər, conditional subpattern-lər, atomic grouping kimi qabaqcıl xüsusiyyətlər daxildir.

### Nəticə

Java və PHP-nin regex imkanları funksional olaraq çox oxşardır - eyni pattern-lər hər ikisində işləyir. Əsas fərq API dizaynındadır: Java OOP yanaşma ilə daha çox nəzarət verir, PHP isə prosedural funksiyalarla daha qısa kod yazmağa imkan verir. PHP-nin PCRE mühərriki daha zəngin xüsusiyyətlər dəstinə malikdir, amma praktikada əksər ehtiyaclar hər iki dildə eyni şəkildə ödənir.
