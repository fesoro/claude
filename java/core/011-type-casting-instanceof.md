# 011 — Type Casting və `instanceof`
**Səviyyə:** Başlanğıc


## Mündəricat
1. [Type Casting nədir?](#nədir)
2. [Primitive Casting — Widening (Genişləndirici)](#widening)
3. [Primitive Casting — Narrowing (Daraldıcı)](#narrowing)
4. [char və int çevrilməsi](#char-int)
5. [Reference Casting — Upcast və Downcast](#reference)
6. [`ClassCastException`](#classcastexception)
7. [`instanceof` operatoru](#instanceof)
8. [Pattern Matching for `instanceof` (Java 16+)](#pattern-matching)
9. [Autoboxing/Unboxing — gizli cast](#autobox)
10. [Ümumi Səhvlər](#səhvlər)
11. [İntervyu Sualları](#intervyu)

---

## 1. Type Casting nədir? {#nədir}

**Type casting** — bir dəyişənin tipini başqa tipə çevirməkdir. Java *statik tipli* dildir, yəni hər dəyişənin tipi məlumdur. Tipi dəyişmək üçün kompilyatora açıq şəkildə "bu dəyəri o biri tipə çevir" deyirsən.

İki əsas növ:
- **Primitive casting** — `int` ↔ `double`, `long` ↔ `short` və s.
- **Reference casting** — obyektlər arasında (`Object` → `String` kimi)

Hər ikisi iki istiqamətdə olur:
- **Widening (genişləndirici)** — kiçik tip → böyük tip (avtomatik, təhlükəsiz)
- **Narrowing (daraldıcı)** — böyük tip → kiçik tip (açıq cast, məlumat itkisi riski)

```
Kiçik ────────> Böyük   = Widening (implicit, avtomatik)
Böyük ────────> Kiçik   = Narrowing (explicit, "(tip)" lazımdır)
```

---

## 2. Primitive Casting — Widening (Genişləndirici) {#widening}

Kiçik tipdən böyük tipə çevrildikdə kompilyator avtomatik cast edir. **Məlumat itkisi olmur**.

### Primitive tiplərin çevrilmə yolu:

```
byte ─> short ─> int ─> long ─> float ─> double
                  │
                  └─> char (xüsusi)
```

### Nümunə:

```java
int eded = 100;
long boyuk = eded;        // int → long, avtomatik
double onluk = eded;      // int → double, avtomatik
float qisa = 25L;         // long → float, avtomatik

// Cast açar sözü lazım deyil
System.out.println(boyuk);   // 100
System.out.println(onluk);   // 100.0
```

### Niyə təhlükəsizdir?

Böyük tip həmişə kiçik tipin bütün dəyərlərini saxlaya bilir:
- `int` — 4 bayt (−2³¹ ... 2³¹−1)
- `long` — 8 bayt (−2⁶³ ... 2⁶³−1)

Bütün `int` dəyərləri `long` içində problemsiz yerləşir.

### Amma dəqiqlik itkisi ola bilər:

```java
int boyukEded = 123_456_789;
float f = boyukEded;           // avtomatik widening
System.out.println(f);          // 1.23456792E8 — son rəqəmlər dəyişdi!
```

`float` yalnız ~7 rəqəmlik dəqiqlik verir, ona görə böyük `int` dəyəri itə bilər. Bu Java-nın icazə verdiyi "səssiz" dəyişikliklərdən biridir.

---

## 3. Primitive Casting — Narrowing (Daraldıcı) {#narrowing}

Böyük tipdən kiçik tipə çevirməkdə **açıq cast** lazımdır. Çünki məlumat itə bilər.

### Sintaksis:

```java
(hədəfTip) dəyər
```

### Nümunə:

```java
double onluk = 9.78;
int eded = (int) onluk;       // double → int, açıq cast
System.out.println(eded);      // 9 — kəsr hissəsi atılır

long boyukEded = 100_000_000_000L;
int kiçikEded = (int) boyukEded;   // diqqət: overflow
System.out.println(kiçikEded);      // 1215752192 — yalanış dəyər!
```

### Narrowing Cast-lar cədvəli:

| Mənbə | Hədəf | Nümunə | Nəticə |
|-------|-------|--------|--------|
| `double` | `int` | `(int) 3.99` | `3` (kəsr atılır) |
| `double` | `float` | `(float) 1.7976931348623157E308` | `Infinity` |
| `long` | `int` | `(int) 3_000_000_000L` | overflow |
| `int` | `byte` | `(byte) 130` | `-126` (daşqın) |
| `int` | `short` | `(short) 40000` | `-25536` |

### Overflow necə işləyir?

Java ikinin tamamlayıcısını (two's complement) istifadə edir. `(byte) 130` dəyərində yuxarı bitlər kəsilir və işarə biti dəyişir:

```java
System.out.println((byte) 127);   //  127   (maks byte)
System.out.println((byte) 128);   // -128   (daşdı)
System.out.println((byte) 255);   //  -1
System.out.println((byte) 256);   //   0    (yenidən başladı)
```

---

## 4. `char` və `int` çevrilməsi {#char-int}

`char` Java-da 16-bitlik işarəsiz ədəd kimi saxlanılır (Unicode kod nöqtəsi).

```java
char herf = 'A';
int kod = herf;             // char → int, widening (avtomatik)
System.out.println(kod);     // 65 (ASCII 'A')

int sayi = 66;
char yeniHerf = (char) sayi; // int → char, narrowing (açıq cast)
System.out.println(yeniHerf); // 'B'
```

### Praktik nümunə: ASCII A–Z yazmaq

```java
for (int i = 0; i < 26; i++) {
    char h = (char) ('A' + i);
    System.out.print(h);      // ABCDEFGHIJKLMNOPQRSTUVWXYZ
}
```

### Kiçik-böyük hərf çevirmə (ASCII hiyləsi)

```java
char kiçik = 'a';
char böyük = (char) (kiçik - 32);   // 'A'
System.out.println(böyük);
```

---

## 5. Reference Casting — Upcast və Downcast {#reference}

Obyektlər arasında cast iki istiqamətli olur. Burada da `extends`/`implements` iyerarxiyası mövcuddur.

### Upcast (yuxarı cast) — avtomatik

Alt sinifin obyektini baza sinifə cast etmək. **Həmişə təhlükəsizdir**, çünki hər `Köpək` bir `Heyvan`dır.

```java
class Heyvan {
    void sesCixar() { System.out.println("səs"); }
}

class Kopek extends Heyvan {
    void hurur() { System.out.println("hav-hav"); }
}

Kopek kopek = new Kopek();
Heyvan heyvan = kopek;    // Upcast, avtomatik — cast operatoru lazım deyil
heyvan.sesCixar();         // OK

// heyvan.hurur();         // KOMPILYASIYA XƏTASI — Heyvan tipində hurur() metodu yoxdur
```

### Downcast (aşağı cast) — açıq cast

Baza sinif tipli referansı alt sinifə çevirmək. **Kompilyator icazə verir, amma runtime-da patlaya bilər**.

```java
Heyvan h = new Kopek();       // əslində Kopek obyektidir
Kopek k = (Kopek) h;           // downcast, açıq
k.hurur();                     // OK — hav-hav
```

Amma:

```java
Heyvan h2 = new Heyvan();      // sadəcə Heyvan obyekti
Kopek k2 = (Kopek) h2;         // RUNTIME XƏTASI: ClassCastException
```

### Niyə lazımdır?

Polimorfizm vaxtı kolleksiyalar `List<Heyvan>` kimi yazılır, amma bəzən konkret alt sinifin metodlarına müraciət lazım ola bilər:

```java
List<Heyvan> sürü = List.of(new Kopek(), new Heyvan());
for (Heyvan h : sürü) {
    if (h instanceof Kopek) {
        Kopek k = (Kopek) h;   // təhlükəsiz downcast
        k.hurur();
    }
}
```

---

## 6. `ClassCastException` {#classcastexception}

Səhv downcast JVM tərəfindən runtime-da tutulur və `ClassCastException` atılır.

```java
Object obj = "Salam";
Integer num = (Integer) obj;   // ClassCastException: class String cannot be cast to class Integer
```

Çıxış mesajı (Java 16+):

```
Exception in thread "main" java.lang.ClassCastException:
class java.lang.String cannot be cast to class java.lang.Integer
(java.lang.String and java.lang.Integer are in module java.base of loader 'bootstrap')
```

### Yaygın səbəb: kolleksiyada qarışıq tiplər

```java
List<Object> list = new ArrayList<>();
list.add("Salam");
list.add(42);

for (Object item : list) {
    String s = (String) item;  // ikinci elementdə ClassCastException
    System.out.println(s.length());
}
```

**Həll yolu:** `instanceof` ilə yoxla.

---

## 7. `instanceof` operatoru {#instanceof}

`instanceof` — obyektin müəyyən tipdə olub-olmadığını yoxlayır. Nəticə `boolean`-dur.

### Sintaksis:

```java
obyekt instanceof Tip
```

### Nümunə:

```java
Object obj = "Salam";
if (obj instanceof String) {
    String s = (String) obj;   // təhlükəsiz downcast
    System.out.println(s.length());   // 5
}
```

### Null və `instanceof`:

```java
Object nullObj = null;
System.out.println(nullObj instanceof String);  // false
```

`null` heç bir tipin instance-ı deyil. Bu NPE yaratmır, təhlükəsizdir.

### Interface ilə:

```java
List<Integer> list = new ArrayList<>();
System.out.println(list instanceof Collection);   // true
System.out.println(list instanceof Iterable);     // true
System.out.println(list instanceof Comparable);   // false
```

### Pattern seçimi — `switch` tipli:

```java
static String təsvir(Object obj) {
    if (obj instanceof Integer) return "tam ədəd";
    if (obj instanceof Double)  return "onluq";
    if (obj instanceof String)  return "mətn";
    return "naməlum";
}
```

Bu pattern `Pattern Matching for instanceof` ilə sadələşir (Java 16+, növbəti bölmə).

---

## 8. Pattern Matching for `instanceof` (Java 16+) {#pattern-matching}

Java 16-dan etibarən `instanceof` yoxlaması ilə yanaşı dəyişən elan etmək olur. Downcast ehtiyacını aradan qaldırır.

### Əvvəl (köhnə üsul):

```java
if (obj instanceof String) {
    String s = (String) obj;
    if (s.length() > 5) {
        System.out.println("uzun mətn: " + s);
    }
}
```

### İndi (Pattern Matching):

```java
if (obj instanceof String s && s.length() > 5) {
    System.out.println("uzun mətn: " + s);
}
```

`s` dəyişəni yalnız `obj instanceof String` doğru olanda mövcuddur. Bu **binding variable** (bağlı dəyişən) adlanır.

### Scope (əhatə dairəsi):

```java
if (!(obj instanceof String s)) {
    return;
}
// Buradan sonra "s" istifadə edilə bilər — çünki false olsaydı, funksiya bitirdi
System.out.println(s.toUpperCase());
```

### Negasiya ilə:

```java
if (obj instanceof String s) {
    // s məlumdur
} else {
    // s burada məlum deyil
}
```

### Pattern matching for `switch` (Java 21 stabil):

```java
String nəticə = switch (obj) {
    case Integer i -> "int: " + i;
    case String  s -> "string (uzunluq: " + s.length() + ")";
    case null     -> "null";
    default       -> "naməlum";
};
```

Çox güclü! Downcast yox, null yoxlaması da switch-dədir. Sealed interface ilə birlikdə exhaustive (bütün halları əhatə edən) switch olur.

---

## 9. Autoboxing/Unboxing — gizli cast {#autobox}

Java primitiv ilə wrapper sinif arasında avtomatik çevirmə edir. Bu bir növ implicit castdır.

```java
Integer wrapper = 42;      // autoboxing: int → Integer
int primitive = wrapper;   // unboxing: Integer → int (implicit)
```

### NPE riski:

```java
Integer wrapper = null;
int p = wrapper;           // NullPointerException — unboxing null üzərində
```

Belə bir sətir çox yayılmış beginnər səhvidir. Əgər wrapper `null` gələ bilərsə, əvvəlcə yoxla:

```java
int p = (wrapper != null) ? wrapper : 0;
```

### Integer cache və `==` tələsi:

```java
Integer a = 127;
Integer b = 127;
System.out.println(a == b);    // true — cache-dəndir

Integer c = 200;
Integer d = 200;
System.out.println(c == d);    // false — yeni obyektlərdir

System.out.println(c.equals(d)); // true — həmişə equals() istifadə et
```

Bu barədə ətraflı: [191-java-equals-vs-double-equals.md](191-java-equals-vs-double-equals.md).

---

## 10. Ümumi Səhvlər {#səhvlər}

### 1. Kəsr hissəsinin itməsi

```java
int a = 5, b = 2;
double nəticə = a / b;           // 2.0 — int bölmə əvvəl olur, sonra cast
double düzgün = (double) a / b;  // 2.5 — əvvəlcə a-nı double et
```

### 2. Overflow səssizdir

```java
int max = Integer.MAX_VALUE;
int daşma = max + 1;
System.out.println(daşma);   // -2147483648 — JVM xəbərdarlıq etmir
```

Həll: `Math.addExact(a, b)` overflow-da `ArithmeticException` atır.

### 3. Downcast `instanceof`-suz

```java
Object obj = getObyekt();
String s = (String) obj;     // risk: ClassCastException
```

Düzgün:

```java
if (obj instanceof String s) {
    // s ilə işlə
}
```

### 4. `instanceof` null ilə səhv yoxlama

```java
if (obj != null && obj instanceof String) { ... }   // null yoxlaması lazım deyil
if (obj instanceof String) { ... }                   // bu kifayətdir
```

`instanceof` null üçün `false` qaytarır, NPE atmır.

### 5. `float` dəqiqlik problemi

```java
float f = 0.1f + 0.2f;
System.out.println(f);         // 0.3 görünür, amma...
System.out.println(f == 0.3f); // false — float-lar dəqiq deyil
```

Pul hesablamaları üçün `BigDecimal` istifadə et.

### 6. `char` arifmetikasını unutmaq

```java
char a = 'A';
char b = 'B';
System.out.println(a + b);         // 131 (int çıxdı, char yox)
System.out.println("" + a + b);    // "AB"
```

`char + char` nəticəsi `int`-dir.

---

## 11. İntervyu Sualları {#intervyu}

**S1: Widening və narrowing cast arasında fərq nədir?**

Widening — kiçik tip böyük tipə çevrilir (`int` → `long`), avtomatik olur, məlumat itkisi yoxdur. Narrowing — böyük tip kiçik tipə çevrilir (`long` → `int`), açıq cast operatoru lazımdır, məlumat itə bilər.

**S2: `(int) 3.7` nəyi qaytarır?**

`3`. Java kəsr hissəsini yuvarlaqlaşdırmaz, sadəcə atır (truncation). Yuvarlaqlaşdırma üçün `Math.round()` istifadə edilir.

**S3: Upcast və downcast arasında fərq nədir?**

Upcast — alt sinif obyekti üst sinif referansına (avtomatik, təhlükəsiz). Downcast — üst sinif referansı alt sinif tipinə (açıq cast, `ClassCastException` riski).

**S4: `instanceof` null üçün nə qaytarır?**

`false`. Null heç bir tipin instance-ı deyil. Bu NPE atmır, təhlükəsiz yoxlamadır.

**S5: Pattern matching for `instanceof` nə vaxt əlavə olundu və nə üçün?**

Java 16-da stabil oldu (Java 14-də preview). Məqsəd: `instanceof` + downcast + dəyişən elanı boilerplate-ini sadələşdirmək. `if (obj instanceof String s)` s-i dərhal elan edir.

**S6: `ClassCastException` nə vaxt atılır?**

Downcast zamanı obyektin faktiki tipi hədəf tipdən törəmədirsə. Məs., `Object obj = "text"; Integer i = (Integer) obj;` — String Integer-ə cast edilə bilməz.

**S7: `(byte) 200` nə qaytarır? Niyə?**

`-56`. `byte` diapazonu −128...127-dir. 200 byte-a yerləşmir, yuxarı bitlər kəsilir, ikinin tamamlayıcısı ilə -56 alınır.

**S8: `int` → `char` çevirməsi mümkündürmü?**

Bəli, `(char) 65` → `'A'`. Amma narrowing-dir çünki `int` 32-bit, `char` 16-bit. Böyük Unicode kod nöqtələri üçün `int`-dən `char`-a cast məlumat itirə bilər.

**S9: Autoboxing NPE-yə necə səbəb olur?**

`Integer wrapper = null; int primitive = wrapper;` — unboxing `null.intValue()` çağırır → NPE. Xüsusilə `Map.get()` `Integer` qaytaranda, primitive tipə birbaşa assign etmək təhlükəlidir.

**S10: `double` → `int` cast-ı nə vaxt təhlükəlidir?**

Ən azı üç hal: (1) kəsr hissəsi itir — `(int) 3.99` = 3; (2) `Infinity` / `NaN` — qeyri-müəyyən nəticə; (3) `int` diapazonundan kənar dəyərlər — `(int) 1e20` = `Integer.MAX_VALUE` saturation.

**S11: Java-da `String` → `int` çevirməsi cast-la edilir?**

Yox! `String` və `int` fərqli iyerarxiyalardadır, cast kompilyasiya xətası verir. Bunun üçün `Integer.parseInt("42")` və ya `Integer.valueOf("42")` istifadə edilir.

**S12: `var` ilə cast necə işləyir?**

`var x = (int) 3.5;` — kompilyator `x`-i `int` kimi göstərir. Cast `var`-a təsir etmir, sadəcə nəticənin tipini təyin edir.
