# 06 — Java Operatorları

> **Seviyye:** Beginner ⭐


## Mündəricat
1. [Operator nədir?](#operator)
2. [Arifmetik operatorlar (+, -, *, /, %)](#arifmetik)
3. [Tam ədəd vs həqiqi ədəd bölməsi](#bolme)
4. [Artırma/azaltma (++, --)](#artirma)
5. [Təyinetmə operatorları (=, +=, -=, ...)](#teyinetme)
6. [Müqayisə operatorları (==, !=, <, >, <=, >=)](#muqayise)
7. [Məntiq operatorları (&&, ||, !) və short-circuit](#mentiq)
8. [Bit operatorları (&, |, ^, ~, <<, >>, >>>)](#bit)
9. [Üçlü operator (?:)](#ternary)
10. [instanceof operatoru](#instanceof)
11. [Operator precedence (üstünlük) cədvəli](#precedence)
12. [Ümumi Səhvlər](#umumi-sehvler)
13. [İntervyu Sualları](#intervyu)

---

## 1. Operator nədir? {#operator}

**Operator** — bir və ya bir neçə dəyər üzərində əməliyyat icra edən xüsusi simvoldur.

```java
int a = 5 + 3;      // + operatoru
int b = 10 - 2;     // - operatoru
boolean c = a > b;  // > operatoru
```

Operandlara görə növləri:
- **Unary** (bir operand): `-x`, `!flag`, `++i`
- **Binary** (iki operand): `a + b`, `x == y`
- **Ternary** (üç operand): `a > b ? a : b`

---

## 2. Arifmetik operatorlar (+, -, *, /, %) {#arifmetik}

| Operator | Ad | Nümunə | Nəticə |
|---|---|---|---|
| `+` | Toplama | `5 + 3` | `8` |
| `-` | Çıxma | `10 - 4` | `6` |
| `*` | Vurma | `6 * 7` | `42` |
| `/` | Bölmə | `20 / 4` | `5` |
| `%` | Qalıq (modulo) | `10 % 3` | `1` |
| `+` | String birləşmə | `"a" + "b"` | `"ab"` |
| `-` (unary) | Mənfi | `-5` | `-5` |

### Nümunələr

```java
int a = 10;
int b = 3;

System.out.println(a + b);   // 13
System.out.println(a - b);   // 7
System.out.println(a * b);   // 30
System.out.println(a / b);   // 3  (tam ədəd bölməsi!)
System.out.println(a % b);   // 1  (10 / 3 = 3 qalıq 1)

// Mənfi
int c = -a;                  // -10
```

### `+` operatorunun ikili məqsədi

`+` həm toplayır, həm də string birləşdirir:

```java
System.out.println(5 + 3);           // 8 (ədədi toplama)
System.out.println("5" + "3");       // "53" (string birləşmə)
System.out.println("Cəm: " + 5 + 3); // "Cəm: 53" — soldan sağa
System.out.println("Cəm: " + (5 + 3)); // "Cəm: 8" — mötərizə ilə
System.out.println(5 + 3 + " gün");  // "8 gün" — əvvəl toplayır
```

Qayda: Əgər operandlardan biri `String`-dirsə, `+` string birləşmə edir.

### Modulo (%) istifadəsi

```java
// Cüt/tək rəqəm
boolean isEven = (n % 2 == 0);

// Saat dövri
int saat = (saat + 25) % 24;   // 25 saat sonra

// Rəqəmin son basamağı
int son = 1234 % 10;           // 4

// İki ədədin bölünməsini yoxla
boolean bolunur = (a % b == 0);
```

### Həqiqi ədədlərdə də modulo

```java
System.out.println(7.5 % 2.5);  // 0.0
System.out.println(5.5 % 2);    // 1.5
```

---

## 3. Tam ədəd vs həqiqi ədəd bölməsi {#bolme}

Java-nın **ən bilinən tələsi** budur. Yeni başlayanlar tez-tez bu tələyə düşür.

### Tam ədəd bölməsi — kəsr atılır

```java
int a = 5 / 2;      // 2 — 2.5 DEYİL
int b = 9 / 4;      // 2 — 2.25 DEYİL
int c = 1 / 3;      // 0 — 0.333 DEYİL

System.out.println(5 / 2);   // 2
```

### Həqiqi ədəd bölməsi

```java
double a = 5.0 / 2;         // 2.5
double b = 5 / 2.0;         // 2.5
double c = (double)5 / 2;   // 2.5
double d = 5 / 2;           // 2.0  !!! (əvvəl int bölünür: 2, sonra 2.0)
```

Qayda: Əgər ən az bir operand `double`/`float`-dırsa, bölmə həqiqi ədəd bölməsidir. Hər ikisi tam ədəd isə, nəticə də tam ədəd — kəsr atılır.

### Orta hesabla (average) hesablamaq — klassik tələ

```java
// YANLIŞ
int toplam = 10;
int say = 3;
double orta = toplam / say;          // 3.0 (3 tam ədəd ola-ola double-a qoyulur)

// DOĞRU
double orta = (double) toplam / say; // 3.333...
// və ya
double orta = toplam / (double) say;
// və ya
double orta = 1.0 * toplam / say;
```

### Sıfıra bölmə

```java
int a = 5 / 0;       // ArithmeticException atır!
double b = 5.0 / 0;  // Infinity (xəta atmır)
double c = 0.0 / 0;  // NaN (Not a Number)
```

---

## 4. Artırma/azaltma (++, --) {#artirma}

`++` dəyəri 1 artırır, `--` isə 1 azaldır.

| Operator | Mənası |
|---|---|
| `i++` | post-increment — əvvəl istifadə et, sonra artır |
| `++i` | pre-increment — əvvəl artır, sonra istifadə et |
| `i--` | post-decrement |
| `--i` | pre-decrement |

### Əsas nümunə

```java
int a = 5;
a++;              // a = 6
++a;              // a = 7
a--;              // a = 6
--a;              // a = 5
```

### Post vs Pre — fərq haradadır?

Təyinetmə və ya ifadədə istifadə edəndə:

```java
int a = 5;
int b = a++;     // b = 5 (əvvəlki dəyər), sonra a = 6

int x = 5;
int y = ++x;     // x = 6, y = 6 (yeni dəyər)
```

### İfadələrdə

```java
int i = 0;
System.out.println(i++);  // 0 — sonra i = 1
System.out.println(++i);  // 2 — əvvəl artır
System.out.println(i);    // 2
```

### Dövrdə adət

```java
for (int i = 0; i < 10; i++) {  // post-increment adətdir
    System.out.println(i);
}
```

Qeyd: Dövrdə `i++` və `++i` arasında funksional fərq yoxdur, çünki nəticə istifadə olunmur. İkisi də eyni işləyir.

---

## 5. Təyinetmə operatorları (=, +=, -=, ...) {#teyinetme}

### Sadə təyinetmə

```java
int a;
a = 10;
```

### Birləşdirilmiş (compound) təyinetmə

| Operator | Ekvivalenti |
|---|---|
| `a += b` | `a = a + b` |
| `a -= b` | `a = a - b` |
| `a *= b` | `a = a * b` |
| `a /= b` | `a = a / b` |
| `a %= b` | `a = a % b` |
| `a &= b` | `a = a & b` |
| `a \|= b` | `a = a \| b` |
| `a ^= b` | `a = a ^ b` |
| `a <<= b` | `a = a << b` |
| `a >>= b` | `a = a >> b` |

### Nümunələr

```java
int x = 10;
x += 5;           // x = 15
x -= 3;           // x = 12
x *= 2;           // x = 24
x /= 4;           // x = 6
x %= 4;           // x = 2

String s = "Salam";
s += ", Dünya!";  // "Salam, Dünya!"
```

### Çox vacib fərq — implicit cast

`a = a + b` və `a += b` tam olaraq eyni deyil:

```java
int a = 10;
a = a + 1.5;      // XƏTA — double-u int-ə təyin edə bilmərik
a += 1.5;         // OK — avtomatik (int) cast var, a = 11
```

Qısa operatorlar **gizli cast** edir — diqqətli ol.

---

## 6. Müqayisə operatorları (==, !=, <, >, <=, >=) {#muqayise}

Nəticə həmişə `boolean`-dır.

| Operator | Mənası |
|---|---|
| `==` | Bərabərdir |
| `!=` | Bərabər deyil |
| `<` | Kiçik |
| `>` | Böyük |
| `<=` | Kiçik və ya bərabər |
| `>=` | Böyük və ya bərabər |

### Nümunələr

```java
int a = 5, b = 10;
System.out.println(a == b);   // false
System.out.println(a != b);   // true
System.out.println(a < b);    // true
System.out.println(a <= 5);   // true
```

### `==` TƏHLÜKƏSİ — Reference tiplər

```java
// Primitivlərdə dəyər müqayisəsi
int x = 5;
int y = 5;
System.out.println(x == y);   // true

// Reference tiplərdə — ünvan müqayisəsi!
String s1 = new String("salam");
String s2 = new String("salam");
System.out.println(s1 == s2);           // false !!
System.out.println(s1.equals(s2));      // true — məzmun müqayisəsi
```

Qayda: Obyektləri müqayisə etmək üçün həmişə `.equals()` istifadə et, `==` yox.

### String literallarının tələsi

```java
String a = "salam";
String b = "salam";
System.out.println(a == b);   // true — eyni String pool-dan

String c = new String("salam");
System.out.println(a == c);   // false — c yeni obyektdir
```

---

## 7. Məntiq operatorları (&&, ||, !) və short-circuit {#mentiq}

| Operator | Ad | Qayda |
|---|---|---|
| `&&` | Məntiqi VƏ (AND) | İkisi də true olmalıdır |
| `\|\|` | Məntiqi VƏ YA (OR) | Ən az biri true olmalıdır |
| `!` | NOT (unary) | True-nu false, false-u true |

### Doğruluq cədvəli

| a | b | `a && b` | `a \|\| b` | `!a` |
|---|---|---|---|---|
| T | T | T | T | F |
| T | F | F | T | F |
| F | T | F | T | T |
| F | F | F | F | T |

### Nümunələr

```java
int yas = 25;
boolean vətəndaş = true;

if (yas >= 18 && vətəndaş) {
    System.out.println("Səs verə bilər");
}

if (yas < 18 || !vətəndaş) {
    System.out.println("Səs verə bilməz");
}
```

### Short-circuit evaluation

Java `&&` və `||` nəticəyə çatan kimi **qalanını hesablamır**. Bu performans və təhlükəsizlik üçün əla alətdir.

#### && ilə

```java
// Əgər obj null-dırsa, obj.getName() çağırılmır — NullPointerException olmur
if (obj != null && obj.getName().equals("Anar")) {
    // ...
}
```

#### || ilə

```java
int a = 0;
// (10 / a) heç vaxt hesablanmır — a == 0 artıq true-dur
if (a == 0 || 10 / a > 5) {
    System.out.println("a sıfırdır və ya 10/a > 5");
}
```

### & və | — bitwise, AMMA boolean-da da işləyir

```java
boolean a = true, b = false;
boolean c = a & b;   // false — amma həm a həm b yoxlanır
boolean d = a | b;   // true — amma həm a həm b yoxlanır
```

Fərq: `&` short-circuit etmir — hər iki tərəfi hesablayır. Performans baxımından `&&` daha sərfəlidir və `null` yoxlamalarında təhlükəsizdir.

```java
// XƏTA riskli — obj null olsa belə getName çağırılır
if (obj != null & obj.getName().equals("X")) { ... }  // NPE!

// DOĞRU
if (obj != null && obj.getName().equals("X")) { ... } // short-circuit
```

---

## 8. Bit operatorları (&, |, ^, ~, <<, >>, >>>) {#bit}

Bit operatorları ədədin **binary** göstərişi üzərində işləyir.

| Operator | Ad |
|---|---|
| `&` | AND |
| `\|` | OR |
| `^` | XOR |
| `~` | NOT (unary) |
| `<<` | Sola sürüşdürmə |
| `>>` | Sağa sürüşdürmə (işarə saxlanılır) |
| `>>>` | Sağa sürüşdürmə (işarəsiz, sıfır doldurur) |

### Nümunə — AND, OR, XOR

```java
int a = 0b1100;   // 12
int b = 0b1010;   // 10

System.out.println(Integer.toBinaryString(a & b));  // 1000 (8)
System.out.println(Integer.toBinaryString(a | b));  // 1110 (14)
System.out.println(Integer.toBinaryString(a ^ b));  // 0110 (6)
System.out.println(Integer.toBinaryString(~a));     // ...11110011 (-13)
```

### Sürüşdürmə

```java
int x = 5;                    // 0101
System.out.println(x << 2);   // 10100 = 20 (2 ilə vurmaq kimi)
System.out.println(x >> 1);   // 0010 = 2  (2-ə bölmək kimi)

int y = -8;
System.out.println(y >> 1);   // -4 (signed)
System.out.println(y >>> 1);  // 2147483644 (unsigned — böyük müsbət ədəd)
```

### Real istifadə — bayraqlar (flags)

```java
public class İzinlər {
    public static final int OXU    = 0b001;  // 1
    public static final int YAZ    = 0b010;  // 2
    public static final int ICRA   = 0b100;  // 4
}

int izin = İzinlər.OXU | İzinlər.YAZ;   // 0b011 = 3

if ((izin & İzinlər.OXU) != 0) {
    System.out.println("Oxu icazəsi var");
}
```

---

## 9. Üçlü operator (?:) {#ternary}

İf-else-nin qısaldılmış forması.

```java
Syntax:  şərt ? dəyər1 : dəyər2
```

Şərt true isə `dəyər1`, false isə `dəyər2`.

### Nümunələr

```java
int a = 10, b = 20;
int maks = (a > b) ? a : b;      // 20

String mesaj = (yas >= 18) ? "böyükdür" : "uşaqdır";

// yerləşdirmək olar
int n = -5;
String tip = n > 0 ? "müsbət" : n < 0 ? "mənfi" : "sıfır";
```

### Əvvəli əvvəli if-else ilə müqayisə

```java
// if-else
String status;
if (xal >= 50) {
    status = "Keçdi";
} else {
    status = "Keçə bilmədi";
}

// Ternary — qısa
String status = (xal >= 50) ? "Keçdi" : "Keçə bilmədi";
```

Qeyd: Hər yerdə ternary istifadə etmə. Kompleks şərtlərdə oxunaqlıq düşür.

---

## 10. instanceof operatoru {#instanceof}

Bir obyektin müəyyən sinif və ya interfeysin nümunəsi olub-olmadığını yoxlayır.

```java
Object o = "Salam";
System.out.println(o instanceof String);   // true
System.out.println(o instanceof Integer);  // false
```

### Pattern matching (Java 16+)

Java 16-dan başlayaraq `instanceof` nəticəni birbaşa dəyişənə yaza bilər:

```java
// Köhnə üsul
if (o instanceof String) {
    String s = (String) o;          // cast lazımdır
    System.out.println(s.length());
}

// Yeni üsul (Java 16+)
if (o instanceof String s) {
    System.out.println(s.length()); // s artıq String-dir
}
```

### Real nümunə

```java
public void prosesEt(Object obj) {
    if (obj instanceof Integer i) {
        System.out.println("Ədəd: " + (i * 2));
    } else if (obj instanceof String s) {
        System.out.println("Mətn: " + s.toUpperCase());
    } else if (obj instanceof List<?> list) {
        System.out.println("Siyahı, ölçü: " + list.size());
    } else {
        System.out.println("Naməlum tip");
    }
}
```

---

## 11. Operator precedence (üstünlük) cədvəli {#precedence}

Yuxarıdan aşağı — yüksəkdən alçağa:

| Kateqoriya | Operatorlar | Assosiativlik |
|---|---|---|
| Postfix | `expr++`, `expr--` | Soldan sağa |
| Unary | `++expr`, `--expr`, `+expr`, `-expr`, `!`, `~` | Sağdan sola |
| Multiplicative | `*`, `/`, `%` | Soldan sağa |
| Additive | `+`, `-` | Soldan sağa |
| Shift | `<<`, `>>`, `>>>` | Soldan sağa |
| Relational | `<`, `>`, `<=`, `>=`, `instanceof` | Soldan sağa |
| Equality | `==`, `!=` | Soldan sağa |
| Bitwise AND | `&` | Soldan sağa |
| Bitwise XOR | `^` | Soldan sağa |
| Bitwise OR | `\|` | Soldan sağa |
| Logical AND | `&&` | Soldan sağa |
| Logical OR | `\|\|` | Soldan sağa |
| Ternary | `? :` | Sağdan sola |
| Assignment | `=`, `+=`, `-=`, `*=`, `/=`, `%=`, ... | Sağdan sola |

### Nümunələr — hansı ardıcıllıqla?

```java
int a = 2 + 3 * 4;       // 14 (əvvəl *, sonra +)
int b = (2 + 3) * 4;     // 20 (mötərizə dəyişir)

boolean c = 5 > 3 && 2 < 1;   // false (&&, a < b-dən sonra)
boolean d = true || false && false;  // true (&& daha yüksək)

int e = 10 - 3 - 2;      // 5 (soldan sağa: (10-3)-2)
int f = 10 - (3 - 2);    // 9 (mötərizə ilə)
```

Qayda: Şübhə olsa **mötərizə qoy**. Kodun oxunaqlığı artır.

---

## 12. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: Tam ədəd bölməsi

```java
double yuzde = 3 / 10 * 100;   // 0.0 (!) — 3/10 = 0, 0*100 = 0
double yuzde = 3.0 / 10 * 100; // 30.0
```

### Səhv 2: == ilə String müqayisəsi

```java
String adi = scanner.nextLine();
if (adi == "Anar") { ... }         // SƏHVDİR
if (adi.equals("Anar")) { ... }    // DOĞRU
```

### Səhv 3: Short-circuit ilə NPE

```java
// & istifadə edilirsə NPE riskdə
if (user != null & user.isActive()) { ... }  // user null olsa NPE

// && ilə təhlükəsizdir
if (user != null && user.isActive()) { ... }
```

### Səhv 4: `==` ilə `=` qarışıqlığı

```java
int x = 5;
if (x = 10) { ... }    // KOMPİLYASIYA XƏTASI — şərt boolean deyil
if (x == 10) { ... }   // DOĞRU
```

C/C++-dan fərqli olaraq Java-da `if` yalnız `boolean` qəbul edir, bu səhvin qarşısı kompilyasiya zamanı alınır. Ancaq `boolean` dəyişənlə olanda:

```java
boolean aktiv = false;
if (aktiv = true) { ... }    // həmişə true — təyin edir!
if (aktiv == true) { ... }   // müqayisə edir
if (aktiv) { ... }           // ən yaxşısı
```

### Səhv 5: Post-increment məntiq səhvi

```java
int i = 0;
while (i++ < 5) {
    System.out.println(i);
}
// 1 2 3 4 5 yazır (0 deyil) — çünki i++ əvvəl yoxlayır, sonra artırır
```

### Səhv 6: Operator precedence

```java
if (bal > 50 & < 100) { ... }      // SINTAKS XƏTASI
if (bal > 50 && bal < 100) { ... } // DOĞRU
```

### Səhv 7: Integer overflow

```java
int günlük = 86400;
int ilLik = 365 * 24 * 60 * 60 * 1000;  // OVERFLOW — int-ə sığmır
long ilLik = 365L * 24 * 60 * 60 * 1000;  // DOĞRU
```

### Səhv 8: `%` float-da dəqiq deyil

```java
System.out.println(0.1 + 0.2);       // 0.30000000000000004 — float xətası
System.out.println(0.1 + 0.2 == 0.3); // false !!
```

Pul ilə işləyəndə `double` yox, `BigDecimal` istifadə et.

---

## 13. İntervyu Sualları {#intervyu}

**S1: `5 / 2` niyə 2 qaytarır, 2.5 yox?**
> Çünki hər iki operand `int`-dir, Java nəticəni də `int` hesab edir — kəsr hissəsi atılır. `2.5` almaq üçün ən az biri `double` olmalıdır: `5.0 / 2` və ya `(double) 5 / 2`.

**S2: `a++` ilə `++a` arasında fərq nədir?**
> Dəyər dəyişikliyi eynidir — hər ikisi 1 artırır. Fərq **ifadədə qaytarılan dəyər**dədir. `a++` əvvəlki dəyəri qaytarır (sonra artırır), `++a` yeni dəyəri qaytarır (əvvəl artırır). Məsələn `a = 5; b = a++` — b = 5, a = 6. `a = 5; b = ++a` — a = 6, b = 6.

**S3: `&&` ilə `&` arasında fərq nədir?**
> `&&` short-circuit edir — sol operand nəticəni müəyyən edirsə, sağ operand hesablanmır. `&` isə həmişə hər iki operandı hesablayır. Boolean-da ikisi də eyni nəticə verir, amma `&&` `null`-a qarşı təhlükəsizdir: `if (x != null && x.getName() != null)`.

**S4: Niyə `String` müqayisə üçün `==` istifadə etmək olmaz?**
> Çünki `==` reference tiplərdə **ünvan** müqayisə edir, **məzmun** yox. `new String("a") == new String("a")` false qaytarır, çünki iki ayrı obyektdir. `.equals()` məzmunu yoxlayır. İstisna: String literalları ("a" == "a") — string pool sayəsində true qaytara bilər, amma buna güvənmək olmaz.

**S5: `a += 1.5` və `a = a + 1.5` eynidirmi (a int olduqda)?**
> Xeyr! `a = a + 1.5` kompilyasiya xətası verir, çünki `int + double = double`, `double`-u `int`-ə təyin etmək olmaz. Amma `a += 1.5` işləyir — çünki compound operator **gizli cast** edir: `a = (int)(a + 1.5)`. Bu həm üstünlük, həm də gözlənməz nəticələrə səbəb olur.

**S6: `instanceof` pattern matching nə verir?**
> Java 16-dan əvvəl `instanceof` yoxlayıb sonra cast etmək lazım idi: `if (o instanceof String) { String s = (String) o; }`. İndi birlikdə: `if (o instanceof String s)`. Dəyişən `s` avtomatik yaranır və `if` bloku içində istifadə olunur. Bu daha qısa və daha təhlükəsizdir.

**S7: `>>` ilə `>>>` arasında fərq nədir?**
> `>>` signed right shift — işarə bitini (MSB) saxlayır, mənfi ədəd mənfi qalır. `>>>` unsigned right shift — MSB-yə sıfır qoyur, nəticə həmişə müsbət olur. Məsələn `-8 >> 1 = -4`, amma `-8 >>> 1 = 2147483644` (böyük müsbət).

**S8: Ternary operator (?:) nə zaman istifadə edilməlidir?**
> Sadə if-else əvəzinə, xüsusən **bir dəyər təyinetməsi** üçün: `int maks = a > b ? a : b`. Mürəkkəb məntiqdə, çoxaddımlı əməliyyatlarda istifadə etmə — oxunaqlıq düşür. Qayda: əgər ternary 1 sətrə sığmırsa və ya yerləşdirilmişsə — if-else yaz.

**S9: Bit operatorları harada istifadə olunur?**
> **Bayraqlar (flags)** — bir int-də bir neçə boolean təmsil etmək üçün (`READ | WRITE | EXECUTE`). **Performans** — `n * 2` əvəzinə `n << 1`, `n % 2 == 0` əvəzinə `(n & 1) == 0`. **Hashing və şifrələmə** — XOR geri alına bilən çox. **Embedded** və protokollarda bitlərlə işləmək üçün.

**S10: Java-da niyə `if (5)` yazmaq olmur, C/C++-da ki olur?**
> Çünki Java ciddi tipli dildir — `if` yalnız `boolean` qəbul edir, `int` deyil. Bu "truthy/falsy" anlayışı yoxdur. Bu qaydanın səbəbi: `if (x = 5)` kimi təsadüfi təyinetmə səhvlərinin qarşısını almaqdır (C-də bu həmişə true-dur). Java bu səhvi kompilyasiya zamanı tutur.
