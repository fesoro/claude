# Control Flow və Operatorlar

> **Seviyye:** Beginner ⭐

## Giriş

Control flow (axının idarəsi) proqramın hansı addımları, hansı sıra ilə və neçə dəfə işlədəcəyini təyin edir. Java və PHP demək olar ki eyni syntax-ı istifadə edir, çünki hər ikisi C dilindən ilham alıb. Amma bir neçə vacib fərq var: Java-də `elseif` ayrıca söz deyil, `else if` iki söz kimi yazılır; PHP-də isə həm `elseif`, həm də `else if` işləyir. Operatorlar da əsasən eynidir, amma PHP-də `==` (loose) və `===` (strict) fərqi var, Java-də isə `==` referans müqayisəsi, `.equals()` isə dəyər müqayisəsidir.

Bu faylda biz `if/else`, loop-lar, `switch`, `break`/`continue` və bütün əsas operatorları beginner (yeni başlayan) üçün addım-addım izah edirik.

---

## Java-da istifadəsi

### if / else / else if

```java
int yash = 25;

if (yash < 18) {
    System.out.println("Uşaq");
} else if (yash < 65) {
    System.out.println("Yetkin");
} else {
    System.out.println("Pensiyaçı");
}
```

**Vacib qaydalar:**
- Şərt `()` içində olmalıdır.
- Şərt `boolean` qaytarmalıdır (`true` və ya `false`). `int` şərt kimi işləmir: `if (1)` XƏTA verir!
- `{}` mötərizələri bir sətirlik blok üçün isteğe bağlıdır, amma **həmişə yazmaq** məsləhətdir (səhv qarşısını alır).

```java
// İşləyir, amma təhlükəli stil
if (yash > 18)
    System.out.println("Yetkin");
    System.out.println("Bu sətir HƏMİŞƏ işləyəcək!"); // Bloka daxil deyil!

// Düzgün stil
if (yash > 18) {
    System.out.println("Yetkin");
    System.out.println("Bu sətir yalnız şərt true olanda işləyir.");
}
```

### Nested if (daxili if)

```java
int bal = 85;
boolean davamiyyat = true;

if (bal >= 50) {
    if (davamiyyat) {
        System.out.println("Keçdi");
    } else {
        System.out.println("Davamiyyət azdır, keçmədi");
    }
} else {
    System.out.println("Bal azdır");
}
```

### Klassik for loop

```java
// for (başlanğıc; şərt; addım)
for (int i = 0; i < 5; i++) {
    System.out.println("i = " + i);
}
// Çıxış: 0, 1, 2, 3, 4

// Geriyə sayma
for (int i = 10; i >= 0; i--) {
    System.out.println(i);
}

// İki dəyişənlə
for (int i = 0, j = 10; i < j; i++, j--) {
    System.out.println(i + " " + j);
}
```

### Enhanced for (for-each)

```java
int[] ededler = {10, 20, 30, 40};

// Enhanced for -- daha qısa, daha oxunaqlı
for (int eded : ededler) {
    System.out.println(eded);
}

// List-lərlə də işləyir
List<String> adlar = List.of("Ali", "Vəli", "Nigar");
for (String ad : adlar) {
    System.out.println(ad);
}

// Diqqət: index yoxdur
// Əgər index lazımdırsa, klassik for istifadə edin
for (int i = 0; i < ededler.length; i++) {
    System.out.println(i + ": " + ededler[i]);
}
```

### while və do-while

```java
// while -- şərt əvvəl yoxlanılır
int i = 0;
while (i < 3) {
    System.out.println("while: " + i);
    i++;
}

// do-while -- şərt sonra yoxlanılır, blok HƏMİŞƏ ən azı 1 dəfə işləyir
int j = 10;
do {
    System.out.println("do-while: " + j);
    j++;
} while (j < 3);  // j artıq 10-dur, amma bir dəfə işlədi

// Praktik nümunə: istifadəçi düzgün input verənə qədər soruş
Scanner scanner = new Scanner(System.in);
int reqem;
do {
    System.out.print("1-10 arası rəqəm daxil et: ");
    reqem = scanner.nextInt();
} while (reqem < 1 || reqem > 10);
```

### switch (klassik statement form)

```java
int gun = 3;
String ad;

switch (gun) {
    case 1:
        ad = "Bazar ertəsi";
        break;
    case 2:
        ad = "Çərşənbə axşamı";
        break;
    case 3:
        ad = "Çərşənbə";
        break;
    case 4:
    case 5:
        // Fall-through -- həm 4, həm 5 üçün eyni blok
        ad = "Həftə sonuna yaxın";
        break;
    default:
        ad = "Naməlum";
}
System.out.println(ad);
```

**XƏBƏRDARLIQ:** `break` yazmasanız, növbəti `case` bloku da işləyəcək (fall-through). Bu ən çox unudulan səhvlərdən biridir.

```java
int x = 1;
switch (x) {
    case 1:
        System.out.println("bir");
        // break YOXDUR -- növbəti case də işləyəcək!
    case 2:
        System.out.println("iki");
        break;
    case 3:
        System.out.println("üç");
        break;
}
// Çıxış: "bir" və "iki" (ikisi də!)
```

**Qeyd:** Java 14-dən etibarən `switch` expression form da var (`case 1 -> ...`), amma bu ayrıca mövzudur və file 25-də detallı izah olunub.

### break, continue, labeled break

```java
// break -- loop-dan tamamilə çıxır
for (int i = 0; i < 10; i++) {
    if (i == 5) break;
    System.out.println(i);  // 0, 1, 2, 3, 4
}

// continue -- cari iterasiyanı atır, növbəti başlayır
for (int i = 0; i < 5; i++) {
    if (i == 2) continue;
    System.out.println(i);  // 0, 1, 3, 4 (2 atlandı)
}

// Labeled break -- iç-içə loop-da kənar loop-dan çıxır
xarici:
for (int i = 0; i < 3; i++) {
    for (int j = 0; j < 3; j++) {
        if (i == 1 && j == 1) {
            break xarici;  // HƏR İKİ loop-dan çıxır
        }
        System.out.println(i + "," + j);
    }
}
// Çıxış: 0,0  0,1  0,2  1,0 (və sonra tamamilə çıxır)

// Labeled continue də var
xarici:
for (int i = 0; i < 3; i++) {
    for (int j = 0; j < 3; j++) {
        if (j == 1) continue xarici;  // xarici loop-un növbəti iterasiyasına keç
        System.out.println(i + "," + j);
    }
}
// Çıxış: 0,0  1,0  2,0
```

### Arifmetik operatorlar

```java
int a = 10;
int b = 3;

System.out.println(a + b);   // 13  toplama
System.out.println(a - b);   // 7   çıxma
System.out.println(a * b);   // 30  vurma
System.out.println(a / b);   // 3   BÖLMƏ -- int/int = int! (kəsr atılır)
System.out.println(a % b);   // 1   modul (qalıq)

// Float bölməsi
double x = 10.0;
double y = 3.0;
System.out.println(x / y);   // 3.3333333333333335

// Qarışıq -- biri double olsa, nəticə double olur
System.out.println(10.0 / 3);  // 3.3333333333333335
System.out.println(10 / 3.0);  // 3.3333333333333335
System.out.println((double) 10 / 3);  // 3.333...
```

### Integer bölmə tələsi

```java
int ümumi = 10;
int say = 3;
double orta = ümumi / say;  // 3.0 (yanlış!) -- int/int əvvəl hesablanır

// Düzgün yol
double orta2 = (double) ümumi / say;  // 3.333...
double orta3 = ümumi / (double) say;  // 3.333...
double orta4 = 1.0 * ümumi / say;     // 3.333...
```

### Müqayisə operatorları

```java
int a = 5, b = 10;

System.out.println(a == b);  // false (bərabərdir?)
System.out.println(a != b);  // true  (bərabər deyil?)
System.out.println(a < b);   // true
System.out.println(a > b);   // false
System.out.println(a <= b);  // true
System.out.println(a >= b);  // false

// DİQQƏT: String-lərdə == istifadə etməyin!
String s1 = "salam";
String s2 = new String("salam");
System.out.println(s1 == s2);         // false (referans müqayisəsi)
System.out.println(s1.equals(s2));    // true  (dəyər müqayisəsi)
```

### Məntiqi (logical) operatorlar

```java
boolean a = true;
boolean b = false;

System.out.println(a && b);  // false  (AND -- hər ikisi true olmalıdır)
System.out.println(a || b);  // true   (OR  -- heç olmasa biri true)
System.out.println(!a);      // false  (NOT)

// Short-circuit evaluation
// && -- sol tərəf false olsa, sağ tərəf heç hesablanmır
// || -- sol tərəf true olsa, sağ tərəf heç hesablanmır

String ad = null;
if (ad != null && ad.length() > 0) {
    // Təhlükəsizdir: ad null olsa, .length() çağırılmır (NullPointerException olmur)
    System.out.println(ad);
}
```

### Bitwise operatorlar

Bitwise operatorlar ədədin bit-lərini (0 və 1) birbaşa dəyişdirir. Bunları RBAC (role-based access control), flag-lər və performance-critical kodda istifadə edirlər.

```java
int a = 12;   // binar: 1100
int b = 10;   // binar: 1010

System.out.println(a & b);   // 8   (1000) AND
System.out.println(a | b);   // 14  (1110) OR
System.out.println(a ^ b);   // 6   (0110) XOR
System.out.println(~a);      // -13       NOT (bütün bitləri çevirir)

// Shift operatorlar
System.out.println(a << 1);  // 24  (sola 1 sürüşdür = * 2)
System.out.println(a >> 1);  // 6   (sağa 1 sürüşdür = / 2)

// Unsigned right shift -- Java-yə xas, PHP-də YOXDUR
int mənfi = -8;
System.out.println(mənfi >> 1);   // -4  (işarəni saxlayır)
System.out.println(mənfi >>> 1);  // 2147483644  (işarə biti də sürüşür)
```

### Ternary operator

```java
int yash = 20;
String status = (yash >= 18) ? "Yetkin" : "Uşaq";
System.out.println(status);  // "Yetkin"

// Iç-içə (məsləhət görülmür, oxunması çətindir)
int bal = 75;
String qiymet = bal >= 90 ? "A" : bal >= 70 ? "B" : bal >= 50 ? "C" : "F";
```

### ++ və -- (prefix / postfix)

```java
int x = 5;

// Postfix: əvvəl istifadə et, sonra artır
int a = x++;
System.out.println(a);  // 5  (x istifadə olundu, SONRA artırıldı)
System.out.println(x);  // 6

// Prefix: əvvəl artır, sonra istifadə et
int y = 5;
int b = ++y;
System.out.println(b);  // 6  (y əvvəl artırıldı, SONRA istifadə olundu)
System.out.println(y);  // 6

// Tipik beginner tələ
int i = 0;
int[] arr = new int[3];
arr[i++] = 10;   // arr[0] = 10, i indi 1
arr[i++] = 20;   // arr[1] = 20, i indi 2
arr[i++] = 30;   // arr[2] = 30, i indi 3
```

---

## PHP-də istifadəsi

### if / elseif / else

```php
$yash = 25;

if ($yash < 18) {
    echo "Uşaq";
} elseif ($yash < 65) {     // BİR söz! Java-də "else if" (iki söz)
    echo "Yetkin";
} else {
    echo "Pensiyaçı";
}

// PHP-də "else if" (iki söz) də işləyir, amma "elseif" daha çox istifadə olunur
```

### for, foreach, while, do-while

```php
// Klassik for
for ($i = 0; $i < 5; $i++) {
    echo $i . "\n";
}

// foreach -- PHP-nin gücü buradadır
$ededler = [10, 20, 30];
foreach ($ededler as $eded) {
    echo $eded . "\n";
}

// key-value ilə
$mesafeler = ['ev' => 5, 'ofis' => 20];
foreach ($mesafeler as $yer => $km) {
    echo "$yer: $km km\n";
}

// while
$i = 0;
while ($i < 3) {
    echo $i . "\n";
    $i++;
}

// do-while
$j = 0;
do {
    echo $j . "\n";
    $j++;
} while ($j < 3);
```

### switch və match

```php
// Klassik switch -- Java ilə eynidir, break lazımdır
$gun = 3;
switch ($gun) {
    case 1:
        $ad = "Bazar ertəsi";
        break;
    case 2:
        $ad = "Çərşənbə axşamı";
        break;
    default:
        $ad = "Naməlum";
}

// PHP 8+ match expression -- Java 14+ switch expression kimi
$ad = match($gun) {
    1 => "Bazar ertəsi",
    2 => "Çərşənbə axşamı",
    3, 4 => "Həftənin ortası",
    default => "Naməlum",
};
```

### Operatorlar

```php
$a = 10;
$b = 3;

// Arifmetik -- Java ilə eyni
echo $a + $b;   // 13
echo $a - $b;   // 7
echo $a * $b;   // 30
echo $a / $b;   // 3.3333... (int/int DA float qaytarır! Java-dən fərq)
echo $a % $b;   // 1
echo $a ** $b;  // 1000 (üssə qaldırma -- Java-də YOXDUR, Math.pow() lazımdır)
echo intdiv($a, $b); // 3 (tam bölmə -- açıq şəkildə)

// Müqayisə
var_dump($a == $b);   // false
var_dump($a === $b);  // false (strict)
var_dump("5" == 5);   // true  (loose -- tip çevrilir!)
var_dump("5" === 5);  // false (strict -- tip də yoxlanılır)
var_dump("5" != 5);   // false
var_dump("5" !== 5);  // true

// Spaceship operator (PHP 7+) -- Java-də YOXDUR
echo 1 <=> 2;   // -1 (sol kiçik)
echo 1 <=> 1;   //  0 (bərabər)
echo 2 <=> 1;   //  1 (sol böyük)
// Sort-da istifadə olunur: usort($arr, fn($a, $b) => $a <=> $b);

// Null coalescing (PHP 7+)
$ad = $user['name'] ?? 'Naməlum';

// Məntiqi -- Java ilə eyni
var_dump(true && false);  // false
var_dump(true || false);  // true
var_dump(!true);          // false

// String birləşdirmə -- PHP-də . (nöqtə), Java-də +
echo "Salam " . "dünya";   // "Salam dünya"

// Ternary
$status = ($yash >= 18) ? "Yetkin" : "Uşaq";

// Qısa ternary (PHP)
$ad = $inputAd ?: "Qonaq";  // $inputAd "truthy"-dirsə onu, yoxsa "Qonaq"

// ++ və --  (Java ilə eyni)
$x = 5;
echo $x++;  // 5 (sonra 6 olur)
echo ++$x;  // 7 (əvvəl artır)
```

### break və continue

```php
// PHP-də labeled break YOXDUR, amma break N (ədəd) var
foreach ([1, 2, 3] as $i) {
    foreach ([10, 20, 30] as $j) {
        if ($i == 2 && $j == 20) {
            break 2;  // 2 səviyyə yuxarı çıx -- Java-də "break xarici"
        }
        echo "$i,$j\n";
    }
}

continue 2;  // eyni məntiq -- 2 səviyyə yuxarı continue
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| else if | `else if` (iki söz) | `elseif` və ya `else if` |
| Şərt tipi | Məcburi `boolean` | Truthy/falsy (istənilən dəyər) |
| String birləşdirmə | `+` operatoru | `.` (nöqtə) operatoru |
| `==` | Referans müqayisəsi (obyektlər üçün) | Loose müqayisə (tip çevrilir) |
| `===` | YOXDUR | Strict müqayisə (tip də yoxlanılır) |
| `<=>` spaceship | YOXDUR | Var (PHP 7+) |
| Üssə qaldırma | `Math.pow(a, b)` | `a ** b` operator |
| Int bölmə | `int / int = int` (kəsr atılır) | `int / int = float` avtomatik |
| `>>>` unsigned shift | Var | YOXDUR |
| Labeled break | `break xarici;` | `break 2;` (ədəd ilə) |
| Null coalescing | Java 8+: `Optional` və ya ternary | `??` operator |
| for-each | `for (var x : collection)` | `foreach ($arr as $x)` |
| switch default | case-lərdən sonra | istənilən yerdə |
| match/switch expression | Java 14+ switch expression | PHP 8+ match |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java **statik tipli dildir**, buna görə də şərt ifadələrinin `boolean` olması məcburidir. Bu, `if (x = 5)` kimi səhvin qarşısını alır (bu ifadə C/C++-da təyinetmədir, şərt deyil — amma yenə də int 5 qaytarır, C-də isə non-zero true sayılır və buqa səbəb olur). Java-də bu kompilyasiya xətası verir.

Java-də `int / int = int` qərarı performans üçündür. CPU üçün integer bölməsi floating bölmədən daha sürətlidir. Əgər float nəticə istəyirsiniz, açıq şəkildə cast etməlisiniz — bu, proqramçının niyyətini aydın edir.

`>>>` (unsigned right shift) Java-yə xasdır, çünki Java-də unsigned int tipi yoxdur (C-dən fərqli olaraq). Bu operator mənfi ədədlər üçün bit manipulyasiyası edərkən lazım olur.

### PHP-nin yanaşması

PHP **dinamik tipli dildir**, buna görə də truthy/falsy məntiq istifadə edir. `if ("")`, `if (0)`, `if (null)`, `if ([])` — hamısı false sayılır. Bu HTML formasından gələn string dəyərlərlə işləməyi asanlaşdırır.

`==` və `===` fərqi PHP-nin çox böyük problemi idi. `"0" == false` true-dur (ikisi də falsy), amma `"0" === false` false-dur. Bu tarixi səhvdir — PHP yaradılarkən veb-formalardan gələn string dəyərlərlə rahat işləmək üçün edilib, amma indi isə developerlər həmişə `===` istifadə etmək məsləhət görürlər.

Spaceship (`<=>`) PHP 7-də əlavə olundu, sort funksiyalarını sadələşdirmək üçün. Java-də `Integer.compare()` metodu bu rolu oynayır.

---

## Ümumi səhvlər (Beginner traps)

### 1. switch-də break unutmaq

```java
int x = 1;
switch (x) {
    case 1:
        System.out.println("bir");
        // break YOXDUR!
    case 2:
        System.out.println("iki");  // BU DA İŞLƏYİR
        break;
}
// Çıxış: "bir" və "iki"
```

### 2. `=` və `==` qarışdırmaq

```java
int x = 5;
if (x = 10) { ... }  // Java-də KOMPILYASIYA XƏTASI (int boolean deyil)
if (x == 10) { ... } // Düzgün

// PHP-də bu tələdir!
if ($x = 10) { ... } // Heç bir xəta yoxdur! $x-ə 10 təyin edir və true qaytarır
```

### 3. Integer overflow

```java
int max = Integer.MAX_VALUE;  // 2147483647
int overflow = max + 1;       // -2147483648! (səssizcə overflow)

// Həll: long istifadə edin və ya Math.addExact()
long safe = (long) max + 1;           // 2147483648
int exact = Math.addExact(max, 1);    // ArithmeticException atır
```

### 4. Integer bölməsi

```java
double nəticə = 5 / 2;     // 2.0 (YANLIŞ! int bölməsi əvvəl olur)
double düzgün = 5.0 / 2;   // 2.5
double düzgün2 = (double) 5 / 2;  // 2.5
```

### 5. `{}` olmadan if

```java
if (user != null)
    user.login();
    logger.info("Logged in");  // Bu if-ə aid DEYİL! Həmişə işləyir

// Həmişə {} istifadə edin
if (user != null) {
    user.login();
    logger.info("Logged in");
}
```

### 6. Java-də String-lərdə `==`

```java
String a = "salam";
String b = new String("salam");
if (a == b) { ... }         // false! Referans fərqlidir
if (a.equals(b)) { ... }    // true
```

### 7. PHP-də loose comparison

```php
"0" == false    // true  (tələ)
"abc" == 0      // PHP 7-də true, PHP 8+ false (nəhayət düzəldi)
[] == false     // true
null == ""      // true
```

---

## Mini müsahibə sualları

**S1: Java-də `switch` statement-ində `break` yazmasaq nə olar?**

C: "Fall-through" baş verir — növbəti `case` bloku da icra olunur, nəticə true olsa belə. Bu çox ümumi buq mənbəyidir. Qəsdən fall-through istədikdə bunu comment ilə qeyd etmək məsləhət görülür (`// fall through`). Java 14+ yeni `switch` expression form-unda (`->` ilə) bu problem yoxdur, çünki fall-through mümkün deyil.

**S2: `int a = 10; int b = 3; double c = a / b;` — `c` nə olacaq və niyə?**

C: `c = 3.0` olacaq (2.5 deyil, 3.3333 deyil). Səbəb: `a / b` əvvəl hesablanır, hər ikisi `int` olduğu üçün integer bölməsi baş verir və nəticə `3`. Sonra bu `3` dəyəri `double`-a çevrilir və `3.0` olur. Düzgün nəticə üçün: `double c = (double) a / b;` və ya `double c = 1.0 * a / b;`.

**S3: `i++` və `++i` arasında nə fərq var? Hansı daha sürətlidir?**

C: `i++` (postfix) əvvəl `i`-nin köhnə dəyərini qaytarır, sonra artırır. `++i` (prefix) əvvəl artırır, sonra yeni dəyəri qaytarır. Standalone statement kimi (məsələn, `i++;` tək sətir) heç bir fərq yoxdur — modern kompilyatorlar hər ikisini optimallaşdırır. Amma bir ifadə içində istifadə olunduqda (`arr[i++]`, `x = i++`) nəticə fərqli olur. C++-da obyekt operatorlarında `++i` daha sürətli ola bilər (copy yaratmır), amma Java-də primitiv tiplər üçün fərq YOXDUR.

**S4: PHP-də `==` və `===` arasında fərq nədir və nə vaxt hansını istifadə edək?**

C: `==` loose comparison-dır — tipləri avtomatik çevirir (`"5" == 5` true-dur). `===` strict comparison-dır — həm dəyəri, həm də tipi yoxlayır (`"5" === 5` false-dur). **Praktiki qayda:** həmişə `===` istifadə edin. `==` yalnız `"0" == false` kimi tələlərlə doludur. Java-də bu fərq yoxdur — primitive-lər üçün `==` dəyər müqayisəsidir, obyektlər üçün isə referans müqayisəsi; dəyər müqayisəsi üçün `.equals()` istifadə olunur.
