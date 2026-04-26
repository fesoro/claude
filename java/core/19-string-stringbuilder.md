# 19 — `String`, `StringBuilder` və `StringBuffer`

> **Seviyye:** Junior ⭐


## Mündəricat
1. [String nədir və niyə xüsusi?](#string-nedir)
2. [String immutability (dəyişilməzlik)](#immutability)
3. [String Pool (Literal Pool)](#string-pool)
4. [`new String("x")` vs `"x"`](#new-vs-literal)
5. [`intern()` metodu](#intern)
6. [String-in əsas metodları](#string-metodlari)
7. [String concatenation — O(n²) tələsi](#concat-trap)
8. [StringBuilder — sürətli alternativ](#stringbuilder)
9. [StringBuffer — thread-safe versiya](#stringbuffer)
10. [`String.format` və Text Blocks](#format-blocks)
11. [Benchmark — real fərq](#benchmark)
12. [Ümumi Səhvlər](#sehvler)
13. [İntervyu Sualları](#intervyu)

---

## 1. String nədir və niyə xüsusi? {#string-nedir}

`String` Java-da adi class-dır (`java.lang.String`), amma **çox xüsusi statusa** malikdir:

- **Final** — extend edilə bilmir
- **Immutable** — yaradıldıqdan sonra dəyişmir
- **Pool** — literal-lar yaddaşda paylaşılır
- **`+` operator dəstəyi** — bütün Java class-larından yalnız String-də var
- **Xüsusi literal sintaksisi** — `"salam"` avtomatik String

```java
String s = "salam";              // literal
String s2 = new String("salam"); // konstruktor
char[] chars = {'s', 'a', 'l'};
String s3 = new String(chars);   // char array-dən
String s4 = String.valueOf(42);  // "42"
```

---

## 2. String immutability (dəyişilməzlik) {#immutability}

**İmmutable** = yaradıldıqdan sonra dəyişmir. Hər "dəyişiklik" əslində yeni String yaradır.

```java
String s = "salam";
s.toUpperCase(); // "SALAM" yeni obyekt qaytarır, amma nəticə istifadə edilmir
System.out.println(s); // hələ də "salam"

s = s.toUpperCase(); // indi yeni obyekti s-ə təyin etdik
System.out.println(s); // "SALAM"
```

### Diaqram

```
Öncə:  s ────▶ ["salam"]
Sonra: s ────▶ ["SALAM"]  ← yeni obyekt
       └─── köhnə "salam" hələ də pool-dadır (pool-dadırsa silinmir)
```

### Niyə immutable?

| Səbəb | İzahı |
|---|---|
| Təhlükəsizlik | Fayl yolları, URL-lər, şifrələr — dəyişə bilməz |
| HashMap açarı | Hash dəyəri dəyişmir, `HashMap` işləyir |
| Thread-safety | Bir dəfə yaradıldı — thread-lər arasında təhlükəsiz paylaşıla bilir |
| String Pool | Paylaşma yalnız immutable üçün təhlükəsizdir |
| Caching | `hashCode()` bir dəfə hesablanıb saxlanır |

### İmmutable sübutu

```java
String s = "salam";
String t = s;
t = t + " dünya";

System.out.println(s); // "salam"        — dəyişmədi
System.out.println(t); // "salam dünya"  — yeni obyekt
```

---

## 3. String Pool (Literal Pool) {#string-pool}

Java String literal-larını **String Constant Pool** (SCP) adlanan xüsusi heap bölgəsində saxlayır.

```java
String a = "salam";
String b = "salam"; // pool-dakı EYNI obyektə işarə edir

System.out.println(a == b); // true
```

### Pool necə işləyir

```
Heap
────
┌──────────────────────┐
│ String Pool:         │
│   "salam"  ◀── a, b  │
│   "dünya"            │
│   "java"             │
└──────────────────────┘
```

Hər yeni literal üçün JVM əvvəlcə pool-a baxır:
- Varsa — mövcud referansı qaytarır
- Yoxdursa — pool-a əlavə edib qaytarır

### Compile-time concatenation

```java
String a = "ab";
String b = "a" + "b"; // compile zamanı "ab"-ə çevrilir
System.out.println(a == b); // true — hər ikisi pool-dan

final String x = "a";
String c = x + "b"; // x final — compile zamanı hesablanır
System.out.println(a == c); // true

String y = "a";
String d = y + "b"; // y final deyil — runtime-da birləşdirilir (yeni obyekt)
System.out.println(a == d); // false
```

---

## 4. `new String("x")` vs `"x"` {#new-vs-literal}

```java
String a = "salam";          // pool-a qoyulur (varsa, oradan alınır)
String b = "salam";          // a ilə eyni referans
String c = new String("salam"); // pool-dan KƏNAR yeni obyekt

System.out.println(a == b); // true
System.out.println(a == c); // false
System.out.println(a.equals(c)); // true
```

### Diaqram

```
Heap
┌───────────────────────────┐
│ Ümumi heap:               │
│   [String "salam"]  ◀── c │
│                           │
│ String Pool:              │
│   "salam"  ◀── a, b       │
└───────────────────────────┘
```

### `new String("x")` istifadə etmə!

Demək olar heç vaxt lazım deyil:
- Yaddaşa israf (əlavə obyekt)
- Performans zərəri
- Yeganə istisna: spesifik olaraq yeni referans lazım olsa

```java
// YANLIŞ — lazımsız
String s = new String("salam");

// DOĞRU
String s = "salam";
```

---

## 5. `intern()` metodu {#intern}

`intern()` — String-i pool-a qoyur və pool-dakı referansı qaytarır.

```java
String a = new String("salam"); // pool-dan kənar
String b = a.intern();          // pool-a qoy, pool referansını qaytar
String c = "salam";             // pool-dan

System.out.println(a == b); // false — a pool-dan kənardadır
System.out.println(b == c); // true  — hər ikisi pool-dan
```

### Nə vaxt intern() istifadə etmək?

- Çoxlu eyni String-lər var və yaddaşa qənaət lazımdır
- Məsələn: milyonlarla log sətiri, hər birində "ERROR", "INFO" və s. təkrarlanır

```java
// Yaddaş optimizasiyası
String level = readFromLog().intern(); // təkrarlanan dəyərlər paylaşılır
```

### Diqqət

Pool tərtib olunmuş yaddaş tutur. Hər təsadüfi String-i intern etmə — pool-u şişirdə bilərsən.

---

## 6. String-in əsas metodları {#string-metodlari}

### Uzunluq və indeks

```java
String s = "salam dünya";

s.length();              // 11
s.charAt(0);             // 's'
s.charAt(6);             // 'd'
s.isEmpty();             // false (uzunluq 0 ol saydı, true)
s.isBlank();             // false (yalnız boşluqdan ibarət ol saydı, true) — Java 11+
```

### Substring

```java
String s = "salam dünya";

s.substring(6);          // "dünya" (6-cı indeksdən sonunacan)
s.substring(0, 5);       // "salam" (0-dan 5-ə qədər, 5 daxil deyil)
```

### Axtarış

```java
String s = "salam dünya";

s.indexOf('m');          // 4
s.indexOf("dün");        // 6
s.indexOf("yox");        // -1 (tapılmadı)
s.lastIndexOf('a');      // 10

s.contains("dün");       // true
s.startsWith("sal");     // true
s.endsWith("ya");        // true
```

### Dəyişmə (yeni String qaytarır!)

```java
String s = "salam";

s.toUpperCase();         // "SALAM"
s.toLowerCase();         // "salam"
s.trim();                // başdan-sondan boşluq silir
s.strip();               // trim + Unicode boşluqları — Java 11+
s.replace('a', 'o');     // "solom"
s.replace("al", "ALT");  // "sALTam"
s.replaceAll("\\d+", "X"); // regex ilə
```

### Bölmək və birləşdirmək

```java
String s = "a,b,c,d";
String[] parts = s.split(",");         // ["a", "b", "c", "d"]
String[] parts2 = s.split(",", 2);     // ["a", "b,c,d"] — limit

String joined = String.join("-", "a", "b", "c"); // "a-b-c"
String joined2 = String.join(", ", List.of("x", "y"));
```

### Müqayisə

```java
String s1 = "salam";
String s2 = "SALAM";

s1.equals(s2);              // false
s1.equalsIgnoreCase(s2);    // true
s1.compareTo("salem");      // < 0 (alfabet sıralamasına görə)
```

### Çevirmə

```java
String.valueOf(42);          // "42"
String.valueOf(3.14);        // "3.14"
String.valueOf(true);        // "true"
Integer.toString(42);        // "42"

Integer.parseInt("42");      // 42
Double.parseDouble("3.14");  // 3.14
Boolean.parseBoolean("true"); // true
```

### Char operasiyaları

```java
String s = "salam";
char[] chars = s.toCharArray(); // ['s', 'a', 'l', 'a', 'm']

// String-in hər simvolu
for (char c : s.toCharArray()) {
    System.out.println(c);
}
```

---

## 7. String concatenation — O(n²) tələsi {#concat-trap}

**Bu çox ciddi bir performans problemidir.**

```java
// YANLIŞ — hər "+" yeni String yaradır
String s = "";
for (int i = 0; i < 10_000; i++) {
    s = s + "x"; // hər iterasiyada yeni String kopyalanır
}
// ~10_000 obyekt yaradılır, yaddaş kopyalanır
// O(n²) kompleksliyi!
```

### Niyə O(n²)?

```
Iteration 1: "" + "x" → "x" (1 char kopya)
Iteration 2: "x" + "x" → "xx" (2 char kopya)
Iteration 3: "xx" + "x" → "xxx" (3 char kopya)
...
Iteration n: n char kopya
Cəmi: 1 + 2 + 3 + ... + n = n(n+1)/2 ≈ O(n²)
```

### DOĞRU həll — StringBuilder

```java
StringBuilder sb = new StringBuilder();
for (int i = 0; i < 10_000; i++) {
    sb.append("x"); // O(1) amortized
}
String s = sb.toString();
// O(n) kompleksliyi — 100x+ sürətli
```

### `+` nə vaxt yaxşıdır?

```java
// OK — kompilyator bunu StringBuilder-ə çevirir
String mesaj = "Salam, " + ad + "! Yaşın: " + yaş;

// Yəni bu:
String mesaj = new StringBuilder()
    .append("Salam, ").append(ad).append("! Yaşın: ").append(yaş)
    .toString();
```

**Qayda:** Bir dəfəlik concatenation — `+`. Loop-da concatenation — StringBuilder.

---

## 8. StringBuilder — sürətli alternativ {#stringbuilder}

`StringBuilder` **mutable** (dəyişkən) string buferidir.

```java
StringBuilder sb = new StringBuilder();
sb.append("Salam ");
sb.append("dünya");
sb.append("!");

String s = sb.toString(); // "Salam dünya!"
```

### Əsas metodlar

```java
StringBuilder sb = new StringBuilder("salam");

sb.append(" dünya");       // "salam dünya"
sb.append(" ").append(42); // chainable

sb.insert(0, ">>> ");      // ">>> salam dünya 42"
sb.delete(0, 4);           // "salam dünya 42"
sb.deleteCharAt(0);        // "alam dünya 42"
sb.reverse();              // "24 aynüd mala"
sb.replace(0, 2, "XY");    // "XY aynüd mala"

sb.length();               // uzunluq
sb.charAt(0);
sb.setCharAt(0, 'Z');
sb.setLength(5);           // kəs və ya genişləndir

String result = sb.toString();
```

### Chained (flüent) API

```java
String url = new StringBuilder()
    .append("https://")
    .append("api.example.com")
    .append("/users/")
    .append(userId)
    .toString();
```

### Capacity (tutum)

```java
StringBuilder sb = new StringBuilder(1000); // ilkin tutum 1000
// 1000 char-a qədər yaddaş yenidən ayrılmır — performans qazancı
```

Əgər son ölçünü təxmini bilirsənsə, ilkin capacity ver — daxili char array yenidən yaradılmasın.

---

## 9. StringBuffer — thread-safe versiya {#stringbuffer}

`StringBuffer` `StringBuilder`-ə oxşardır, amma bütün metodları `synchronized`-dir.

```java
StringBuffer sb = new StringBuffer();
sb.append("salam"); // thread-safe
```

### Fərqlər

| Xüsusiyyət | StringBuilder | StringBuffer |
|---|---|---|
| Thread-safe | Xeyr | Bəli (synchronized) |
| Sürət | Sürətli | Yavaş (lock overhead) |
| API | Eyni | Eyni |
| Nə vaxt? | Single-thread (əksər hallar) | Multi-thread paylaşılan bufer |
| Java versiyası | Java 5+ | Java 1.0+ |

### Real dünyada

Praktikada **StringBuffer demək olar istifadə olunmur**. Əgər thread-safe concatenation lazımdırsa, adətən başqa üsullar (lock, message queue) daha yaxşıdır.

```java
// 99% hallarda istifadə etdiyin:
StringBuilder sb = new StringBuilder(); // single-thread — sürətli
```

---

## 10. `String.format` və Text Blocks {#format-blocks}

### `String.format` — printf stili

```java
String s = String.format("Ad: %s, Yaş: %d, Maaş: %.2f", "Əli", 25, 1500.5);
// "Ad: Əli, Yaş: 25, Maaş: 1500.50"

// Format spesifikatorları:
// %s — string
// %d — integer
// %f — float/double (%.2f — 2 onluq rəqəm)
// %b — boolean
// %c — char
// %n — platform-specific newline
// %%  — % simvolu
```

### `formatted` metodu (Java 15+)

```java
String s = "Ad: %s, Yaş: %d".formatted("Əli", 25);
// String.format ilə eyni, amma daha oxunaqlı
```

### Text Blocks (Java 15+)

Çox sətirli String-lər üçün.

```java
// Əvvəlki üsul — əziyyətli
String json = "{\n" +
              "  \"ad\": \"Əli\",\n" +
              "  \"yaş\": 25\n" +
              "}";

// Text block — təmiz
String json = """
        {
          "ad": "Əli",
          "yaş": 25
        }
        """;
```

### Xüsusiyyətləri

- `"""` ilə başlayır və bitir
- Açılışdan sonra yeni sətir məcburidir
- Solda ortaq indent avtomatik silinir
- `\s` — boşluq saxlamaq üçün
- `\` — sətir sonunda — yeni sətir olmasın deyə

```java
String query = """
        SELECT id, name
        FROM users
        WHERE active = true
        """;
```

---

## 11. Benchmark — real fərq {#benchmark}

```java
public class StringBenchmark {
    public static void main(String[] args) {
        int N = 100_000;

        // Test 1: String + concatenation
        long t1 = System.currentTimeMillis();
        String s = "";
        for (int i = 0; i < N; i++) {
            s = s + "x";
        }
        long d1 = System.currentTimeMillis() - t1;

        // Test 2: StringBuilder
        long t2 = System.currentTimeMillis();
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < N; i++) {
            sb.append("x");
        }
        String s2 = sb.toString();
        long d2 = System.currentTimeMillis() - t2;

        // Test 3: StringBuffer
        long t3 = System.currentTimeMillis();
        StringBuffer sbb = new StringBuffer();
        for (int i = 0; i < N; i++) {
            sbb.append("x");
        }
        String s3 = sbb.toString();
        long d3 = System.currentTimeMillis() - t3;

        System.out.printf("String +:       %d ms%n", d1); // ~3000-15000 ms
        System.out.printf("StringBuilder:  %d ms%n", d2); // ~5-20 ms
        System.out.printf("StringBuffer:   %d ms%n", d3); // ~10-30 ms
    }
}
```

### Nəticələr (ümumi rəqəmlər)

| Üsul | 100K char üçün |
|---|---|
| String `+` loop | ~5000 ms (O(n²)) |
| StringBuilder | ~10 ms (O(n)) |
| StringBuffer | ~15 ms (O(n) + lock) |

**Fərq: ~500 dəfə!**

---

## Ümumi Səhvlər {#sehvler}

### 1. Loop-da `+` istifadə etmək

```java
String result = "";
for (String s : list) {
    result += s; // O(n²) — pis
}
```

### 2. String-i `==` ilə müqayisə etmək

```java
if (name == "admin") { ... } // referans müqayisəsi!

// DOĞRU
if ("admin".equals(name)) { ... }
```

### 3. Method-un yeni String qaytardığını bilməmək

```java
String s = "salam";
s.toUpperCase(); // nəticə atılır!
System.out.println(s); // "salam" — dəyişmədi

// DOĞRU
s = s.toUpperCase();
```

### 4. Lazımsız `new String(...)`

```java
String s = new String("salam"); // niyə?
String s = "salam";              // daha yaxşı
```

### 5. `split("\\.")` — regex xüsusi simvolları

```java
"a.b.c".split(".");  // [""]! — . regex-də "hər şey" deməkdir
"a.b.c".split("\\."); // ["a", "b", "c"] — escape olunmuş
```

### 6. `substring()` sərhədlərini səhv götürmək

```java
"salam".substring(0, 5); // "salam" — 5 daxil deyil
"salam".substring(0, 6); // StringIndexOutOfBoundsException
```

---

## İntervyu Sualları {#intervyu}

**S1: String niyə immutable-dır?**
> Təhlükəsizlik (fayl yolları, URL-lər), HashMap açarı olaraq istifadə (hash dəyişmir), thread-safety (paylaşma təhlükəsiz), String Pool optimizasiyası, `hashCode()` cache-i. Bu dizayn qərarı Java-nın əsas daşlarındandır.

**S2: `String s = "a"; String s2 = new String("a"); s == s2` nə qaytarır?**
> `false`. İlki pool-dan, ikincisi heap-də yeni obyekt. `.equals()` isə true qaytarar.

**S3: String Pool nədir?**
> Heap-ın xüsusi bölgəsi — String literal-larını saxlayır. Eyni literal bir dəfə yaradılır, bütün istifadələr eyni obyektə işarə edir. Yaddaşa qənaət və performans üçündür.

**S4: StringBuilder və StringBuffer arasında fərq nədir?**
> Hər ikisi mutable string buferidir. `StringBuffer` synchronized — thread-safe, amma yavaş. `StringBuilder` synchronized deyil — sürətli, single-thread-də istifadə olunur. API eynidir.

**S5: Loop-da String concatenation niyə pisdir?**
> Hər `+` yeni String obyekti yaradır və məzmunu kopyalayır. N iterasiyada O(n²) kompleksliyə gətirir. Həll: `StringBuilder.append()` — O(n).

**S6: `str.intern()` nə edir?**
> String-i pool-a qoyur (əgər oradadırsa, sadəcə pool referansını qaytarır). Yaddaş optimizasiyası üçün çoxlu eyni String-lər olduqda istifadə olunur.

**S7: `String.format` və `+` arasında nə vaxt hansı istifadə olunur?**
> `+` — sadə birləşmələrdə (2-3 dəyişən). `String.format` — formatlama lazım olduqda (%.2f, %05d), daha oxunaqlıdır, template-lərdə.

**S8: String-in hashCode-u nə vaxt hesablanır?**
> İlk `hashCode()` çağırışında hesablanıb **cache-də** saxlanır. Sonrakı çağırışlarda yenidən hesablanmır — çünki String immutable-dır.

**S9: Text Block-un əsas faydası nədir?**
> Çox sətirli String-ləri təbii yazmaq imkanı. Escape-siz (`\n`, `\"` olmadan) SQL, JSON, HTML, XML yaza bilirsən. Ortaq indent avtomatik silinir.

**S10: İki String bərabərdirsə, hashCode-ları həmişə eyni olurmu?**
> Bəli — `String.equals()` və `String.hashCode()` Java-da düzgün override edilib və kontrakta əməl edir.
