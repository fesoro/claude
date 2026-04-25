# 18 — `==` vs `.equals()` və Wrapper Classes

> **Seviyye:** Beginner ⭐


## Mündəricat
1. [Klassik başlayan çaşqınlığı](#chashqinliq)
2. [`==` operatoru — necə işləyir](#double-equals)
3. [`.equals()` — Object-dən gələn default](#equals-default)
4. [`equals()` override etmək](#equals-override)
5. [`hashCode()` kontraktı](#hashcode-contract)
6. [String pool və intern](#string-pool)
7. [`String` müqayisəsi — `==` nə vaxt işləyir?](#string-compare)
8. [Wrapper class-lar (8 ədəd)](#wrapper-classes)
9. [Autoboxing və Unboxing](#autoboxing)
10. [Integer cache (-128..127)](#integer-cache)
11. [Unboxing NullPointerException](#npe-risk)
12. [Ümumi Səhvlər](#sehvler)
13. [İntervyu Sualları](#intervyu)

---

## 1. Klassik başlayan çaşqınlığı {#chashqinliq}

Java-da yeni olan hər kəs bu problemlə üzləşir:

```java
String a = "salam";
String b = "salam";
System.out.println(a == b);        // true  — niyə?

String c = new String("salam");
String d = new String("salam");
System.out.println(c == d);        // false — niyə?

System.out.println(c.equals(d));   // true  — normal

Integer x = 100;
Integer y = 100;
System.out.println(x == y);        // true  — niyə?

Integer m = 200;
Integer n = 200;
System.out.println(m == n);        // false — NİYƏ?
```

Bu sualların cavabı: Java-da iki cür müqayisə var və onlar tamamilə fərqli işləyir.

---

## 2. `==` operatoru — necə işləyir {#double-equals}

`==` iki cür davranır — tipdən asılı olaraq:

### Primitive tiplərdə — dəyər müqayisəsi

```java
int a = 5;
int b = 5;
System.out.println(a == b); // true — dəyərlər eynidir

double x = 3.14;
double y = 3.14;
System.out.println(x == y); // true

char c1 = 'A';
char c2 = 'A';
System.out.println(c1 == c2); // true
```

### Reference (obyekt) tiplərində — referans müqayisəsi

`==` obyektlər arasında **yaddaş ünvanlarını** müqayisə edir. Yəni: "bu iki dəyişən **eyni obyektə** işarə edirmi?"

```java
public class Nöqtə {
    int x, y;
    Nöqtə(int x, int y) { this.x = x; this.y = y; }
}

Nöqtə p1 = new Nöqtə(1, 2);
Nöqtə p2 = new Nöqtə(1, 2);
Nöqtə p3 = p1;

System.out.println(p1 == p2); // false — iki AYRI obyekt
System.out.println(p1 == p3); // true  — eyni obyekt
```

### Diaqram

```
Stack                Heap
─────                ────

p1 ────────────▶ [Nöqtə(1, 2)]  ◀──── p3
p2 ────────────▶ [Nöqtə(1, 2)]

p1 == p2 → iki fərqli yaddaş ünvanı → false
p1 == p3 → eyni yaddaş ünvanı       → true
```

---

## 3. `.equals()` — Object-dən gələn default {#equals-default}

Bütün class-lar `Object`-dən miras alır. `Object.equals()` belə yazılıb:

```java
// Object class-ında
public boolean equals(Object obj) {
    return (this == obj); // yalnız referans müqayisəsi!
}
```

Yəni əgər sən `equals()` override etməsən, davranış `==` ilə eyni olacaq!

```java
public class Nöqtə {
    int x, y;
    Nöqtə(int x, int y) { this.x = x; this.y = y; }
    // equals override edilməyib
}

Nöqtə p1 = new Nöqtə(1, 2);
Nöqtə p2 = new Nöqtə(1, 2);

System.out.println(p1.equals(p2)); // false! — default == edir
```

---

## 4. `equals()` override etmək {#equals-override}

**Məqsəd**: iki obyektin məzmunca eyni olduğunu yoxlamaq.

```java
import java.util.Objects;

public class Nöqtə {
    private final int x;
    private final int y;

    public Nöqtə(int x, int y) {
        this.x = x;
        this.y = y;
    }

    @Override
    public boolean equals(Object o) {
        // 1) Özünə bərabərlik (performans optimization)
        if (this == o) return true;
        // 2) null və tip yoxlaması
        if (!(o instanceof Nöqtə)) return false;
        // 3) Məzmun müqayisəsi
        Nöqtə digər = (Nöqtə) o;
        return x == digər.x && y == digər.y;
    }

    @Override
    public int hashCode() {
        return Objects.hash(x, y);
    }
}

Nöqtə p1 = new Nöqtə(1, 2);
Nöqtə p2 = new Nöqtə(1, 2);
System.out.println(p1.equals(p2)); // true — indi məzmun müqayisəsi
System.out.println(p1 == p2);      // false — hələ də referans müqayisəsi
```

### equals() kontraktının 5 qaydası

| Qayda | İzahı |
|---|---|
| Refleksivlik | `x.equals(x)` həmişə `true` olmalıdır |
| Simmetriklik | `x.equals(y)` → `y.equals(x)` |
| Tranzitivlik | `x.equals(y) && y.equals(z)` → `x.equals(z)` |
| Ardıcıllıq | Obyektlər dəyişmirsə, `equals()` həmişə eyni nəticə verməlidir |
| null | `x.equals(null)` həmişə `false` olmalıdır |

---

## 5. `hashCode()` kontraktı {#hashcode-contract}

**Ən vacib qayda:** `equals()` override edirsənsə, `hashCode()` də override etməlisən.

```
x.equals(y) == true  ⟹  x.hashCode() == y.hashCode()
```

Əks istiqamət məcburi deyil: eyni hashCode fərqli obyektlərdə ola bilər (collision).

### Niyə vacibdir?

`HashMap`, `HashSet` və s. əvvəlcə `hashCode()`-u istifadə edib obyekt üçün bucket tapır, sonra həmin bucket daxilində `equals()` ilə müqayisə edir.

```java
public class Pis {
    int id;
    Pis(int id) { this.id = id; }

    @Override
    public boolean equals(Object o) {
        if (!(o instanceof Pis)) return false;
        return id == ((Pis) o).id;
    }
    // hashCode yazılmayıb! — Object.hashCode istifadə olunur — referans əsaslı
}

Set<Pis> set = new HashSet<>();
set.add(new Pis(1));
set.add(new Pis(1)); // bərabər obyekt — amma...
System.out.println(set.size()); // 2 — çünki hashCode fərqlidir, fərqli bucket!
```

### DOĞRU

```java
@Override
public int hashCode() {
    return Objects.hash(id);
}

// İndi set.size() == 1 olacaq.
```

---

## 6. String pool və intern {#string-pool}

Java performans üçün String literal-larını xüsusi yerdə — **String Constant Pool**-da saxlayır (heap-ın bir hissəsi).

```java
String a = "salam";  // "salam" pool-a qoyulur
String b = "salam";  // pool-da artıq var — təkrar YARATMIR, eyni referans qaytarılır

System.out.println(a == b); // true — eyni obyekt
```

### `new String(...)` pool-u bypass edir

```java
String c = new String("salam"); // yeni obyekt yaradılır, pool-dan KƏNAR
String d = "salam";             // pool-dan

System.out.println(c == d);        // false — fərqli obyektlər
System.out.println(c.equals(d));   // true  — eyni məzmun
```

### Diaqram

```
Heap
────
┌─────────────────────┐
│ Ümumi heap          │
│   [String "salam"]  ◀── c (new ilə)
│                     │
│   String Pool:      │
│     "salam" ◀────── a, b, d
└─────────────────────┘
```

### `intern()` — əl ilə pool-a qoymaq

```java
String c = new String("salam");
String d = c.intern(); // pool-dakı "salam" referansını qaytarır
String e = "salam";

System.out.println(d == e); // true — hər ikisi pool-dandır
```

---

## 7. `String` müqayisəsi — `==` nə vaxt işləyir? {#string-compare}

### `==` işləyən hallar (yanıltıcıdır)

```java
String a = "abc";
String b = "abc";
System.out.println(a == b); // true — pool

String c = "ab" + "c"; // compile zamanı "abc"-ə çevrilir (hər ikisi literal)
System.out.println(a == c); // true

final String p = "ab";
String d = p + "c"; // p final — compile zamanı hesablanır
System.out.println(a == d); // true
```

### `==` işləməyən hallar

```java
String a = "abc";
String b = new String("abc");
System.out.println(a == b); // false

String x = "ab";
String y = x + "c"; // x final DEYİL — runtime-da birləşdirilir
System.out.println(a == y); // false

String s = "a";
s += "bc";
System.out.println(a == s); // false
```

### QAYDA: Həmişə `.equals()` istifadə et!

```java
// YANLIŞ
if (istifadəçiAdı == "admin") { ... }

// DOĞRU
if ("admin".equals(istifadəçiAdı)) { ... }
// İstifadəçi tərəfi sabit qoyulur — null-safe olur!
```

### null-safe müqayisə

```java
String name = null;

// name.equals("admin") — NullPointerException
// "admin".equals(name) — false (NPE yox)
// Objects.equals(name, "admin") — false (NPE yox)
```

---

## 8. Wrapper class-lar (8 ədəd) {#wrapper-classes}

Java-da primitive tiplərlə obyekt versiyaları (wrapper) mövcuddur.

| Primitive | Wrapper | Razmer |
|---|---|---|
| `byte` | `Byte` | 8 bit |
| `short` | `Short` | 16 bit |
| `int` | `Integer` | 32 bit |
| `long` | `Long` | 64 bit |
| `float` | `Float` | 32 bit |
| `double` | `Double` | 64 bit |
| `char` | `Character` | 16 bit |
| `boolean` | `Boolean` | 1 bit |

### Niyə wrapper?

```java
// 1) Collection-lar yalnız obyekt tuta bilir
List<int> list = ...;        // YANLIŞ — primitive
List<Integer> list = ...;    // DOĞRU — wrapper

// 2) null dəyər saxlanıla bilir
int x = null;        // YANLIŞ
Integer x = null;    // DOĞRU

// 3) Utility metodlar
Integer.parseInt("42");          // 42
Integer.MAX_VALUE;               // 2147483647
Integer.toBinaryString(10);      // "1010"
Integer.toHexString(255);        // "ff"
```

### Wrapper class yaratma

```java
// Köhnə üsul (Java 9+ deprecated)
// Integer x = new Integer(42);

// Müasir üsul
Integer x = Integer.valueOf(42); // cache istifadə edir
Integer y = 42;                  // autoboxing — yuxarıdakı ilə eynidir
```

---

## 9. Autoboxing və Unboxing {#autoboxing}

Java kompilyatoru primitive ↔ wrapper arasındakı çevrilmələri avtomatik edir.

```java
// Autoboxing — primitive → wrapper
Integer a = 5;        // avtomatik: Integer.valueOf(5)
Double b = 3.14;      // avtomatik: Double.valueOf(3.14)

// Unboxing — wrapper → primitive
Integer x = 10;
int y = x;            // avtomatik: x.intValue()

// Arifmetikdə də avtomatik
Integer sum = a + x;  // unbox → 5 + 10 = 15 → autobox → Integer

// Collection-larda
List<Integer> list = new ArrayList<>();
list.add(1);          // 1 → Integer.valueOf(1)
int first = list.get(0); // list.get(0).intValue()
```

### Performance diqqəti

```java
// YANLIŞ — hər iterasiyada boxing
Long sum = 0L;
for (long i = 0; i < 1_000_000; i++) {
    sum += i; // sum unbox → topla → autobox
}
// ~10x yavaş!

// DOĞRU
long sum = 0L;
for (long i = 0; i < 1_000_000; i++) {
    sum += i;
}
```

---

## 10. Integer cache (-128..127) {#integer-cache}

**Buradadır klassik tələ.** `Integer.valueOf(n)` `-128` ilə `127` aralığında əvvəlcədən yaradılmış obyektləri cache-dən qaytarır.

```java
Integer a = 100;  // cache-dən
Integer b = 100;  // eyni cache obyektinə
System.out.println(a == b); // true

Integer c = 200;  // 127-dən böyük — yeni obyekt
Integer d = 200;  // başqa yeni obyekt
System.out.println(c == d); // false!

System.out.println(c.equals(d)); // true — məzmun eynidir
```

### Niyə belə?

Kiçik ədədlər çox istifadə olunur (loop sayğacları, true/false, 0/1). Hər dəfə yeni obyekt yaratmaq israf olardı. Ona görə JVM onları bir dəfə yaradıb saxlayır.

### Qayda — heç vaxt wrapper-ləri `==` ilə müqayisə etmə!

```java
Integer a = 200, b = 200;

// YANLIŞ
if (a == b) { ... }

// DOĞRU
if (a.equals(b)) { ... }

// və ya null-safe
if (Objects.equals(a, b)) { ... }

// və ya unbox edib primitive müqayisə
if (a.intValue() == b.intValue()) { ... }
```

### Digər cache-lər

| Wrapper | Cache |
|---|---|
| `Byte` | bütün dəyərlər (-128..127) |
| `Short` | -128..127 |
| `Integer` | -128..127 (`-XX:AutoBoxCacheMax` ilə artırmaq olar) |
| `Long` | -128..127 |
| `Character` | 0..127 |
| `Boolean` | true, false |
| `Float`, `Double` | cache **yoxdur** |

---

## 11. Unboxing NullPointerException {#npe-risk}

`null` wrapper-i unbox etməyə cəhd etsən — NPE.

```java
Integer x = null;
int y = x; // NullPointerException! (x.intValue() çağırılır)

// Daha incə nümunə
Map<String, Integer> ballar = new HashMap<>();
int b = ballar.get("Əli"); // NPE — "Əli" yoxdur, null qayıdır, unbox → NPE
```

### Təhlükəsiz üsul

```java
Integer x = null;

// 1) null yoxla
int y = (x != null) ? x : 0;

// 2) Optional
int y = Optional.ofNullable(x).orElse(0);

// 3) Map-da getOrDefault
int b = ballar.getOrDefault("Əli", 0);
```

### Klassik tələ — ternary ifadələr

```java
Integer x = null;
boolean şərt = true;

// int sonra Integer-ə çevrilir — Integer null-un unbox-u = NPE!
Integer n = şərt ? x : 0; // NullPointerException

// Düzəliş
Integer n = şərt ? x : Integer.valueOf(0);
```

---

## Ümumi Səhvlər {#sehvler}

### 1. String-i `==` ilə müqayisə etmək

```java
// YANLIŞ
if (ad == "admin") { ... }

// DOĞRU
if ("admin".equals(ad)) { ... }
```

### 2. `equals()` override, `hashCode()` yox

HashMap/HashSet düzgün işləməyəcək — obyektlər "yox olacaq".

### 3. Wrapper-ləri `==` ilə müqayisə etmək

```java
Integer a = 200, b = 200;
if (a == b) { ... } // işləmir!
```

### 4. `equals()` parametrini düzgün tip deyil yazmaq

```java
// YANLIŞ — Object yerinə konkret tip — bu override deyil, overload!
public boolean equals(Nöqtə o) { ... }

// DOĞRU
@Override
public boolean equals(Object o) { ... }
```

### 5. NPE potensialı

```java
int x = map.get("açar"); // null qaytarırsa — NPE!
```

---

## İntervyu Sualları {#intervyu}

**S1: `==` və `.equals()` arasında fərq nədir?**
> `==` — primitive-lərdə dəyər, obyektlərdə referans müqayisəsi edir. `.equals()` — məzmun müqayisəsi edir (əgər override edilibsə). Default `Object.equals()` `==` ilə eynidir.

**S2: Niyə `equals()` override etdikdə `hashCode()` də override edilməlidir?**
> Java kontraktına görə: `x.equals(y)` true-dursa, `x.hashCode() == y.hashCode()` olmalıdır. `HashMap`, `HashSet` əvvəlcə hashCode-a baxıb bucket tapır, sonra equals ilə müqayisə edir. hashCode override olunmasa, eyni obyekt fərqli bucket-lərdə qalar.

**S3: `String s1 = "hi"; String s2 = "hi"; s1 == s2` nə qaytarır və niyə?**
> `true`. Çünki hər iki literal String Constant Pool-dakı eyni obyektə işarə edir. `new String("hi")` istifadə etsəydik, fərqli obyekt yaradılardı.

**S4: `Integer a = 127; Integer b = 127; a == b` — nə olur? 128 üçün?**
> 127-də `true` (cache), 128-də `false`. Integer cache -128..127 arasında olan dəyərləri paylaşır; kənar dəyərlər hər dəfə yeni obyekt yaradır.

**S5: `null.equals(obj)` nə qaytarır?**
> NullPointerException — `null` üzərindən metod çağırmaq olmur. Həll: `"sabit".equals(nullable)` və ya `Objects.equals(a, b)`.

**S6: Autoboxing nədir?**
> Primitive-in avtomatik wrapper-ə çevrilməsi. `Integer x = 5;` kompilyator `Integer.valueOf(5)`-ə çevirir. Unboxing əks istiqamətdir — `int y = x;` `x.intValue()` olur.

**S7: `intern()` nə edir?**
> String-i pool-a qoyur və pool-dakı referansı qaytarır. `new String("a").intern() == "a"` → true. Yaddaşa qənaət etmək üçün çoxlu eyni String-lər olduqda istifadə olunur (amma pool özü də bəzən böyüyür — ehtiyatlı ol).

**S8: `Integer.valueOf(100)` və `new Integer(100)` arasında fərq?**
> `valueOf(100)` cache-dən qayıdır (tək obyekt), `new Integer(100)` həmişə yeni obyekt. `new Integer()` Java 9-da deprecated oldu. Müasir kod `valueOf` və ya autoboxing istifadə etməlidir.

**S9: `String.equals()` case-sensitive-dirmi?**
> Bəli. `"abc".equals("ABC")` → false. Case-insensitive üçün `.equalsIgnoreCase()` istifadə et.

**S10: `Objects.equals(a, b)` nə edir və niyə faydalıdır?**
> Null-safe equals: `a == null ? b == null : a.equals(b)`. Hər iki tərəf null ola bilən yerlərdə NPE-siz müqayisə təmin edir.
