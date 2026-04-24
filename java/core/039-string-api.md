# 039 — Java String API — Geniş İzah
**Səviyyə:** Orta


## Mündəricat
1. [String əsasları](#string-əsasları)
2. [Yeni String metodları (Java 11–21)](#yeni-string-metodları-java-1121)
3. [String müqayisəsi](#string-müqayisəsi)
4. [StringBuilder və StringJoiner](#stringbuilder-və-stringjoiner)
5. [Regex ilə işləmək](#regex-ilə-işləmək)
6. [Performans məsələləri](#performans-məsələləri)
7. [İntervyu Sualları](#intervyu-sualları)

---

## String əsasları

```java
// ─── String immutable-dır ─────────────────────────────
String s = "Salam";
s.toUpperCase(); // Yeni String yaradır — s dəyişmir!
s = s.toUpperCase(); // İndi s dəyişdi

// ─── String Pool ──────────────────────────────────────
String a = "hello";          // String pool-a əlavə edilir
String b = "hello";          // Pool-dan götürülür
String c = new String("hello"); // Heap-də yeni obyekt

System.out.println(a == b);  // true (eyni pool reference)
System.out.println(a == c);  // false (fərqli reference)
System.out.println(a.equals(c)); // true (məzmun eyni)

// ─── Əsas metodlar ────────────────────────────────────
String str = "  Salam Dünya  ";

str.length();          // 15
str.trim();            // "Salam Dünya" (ön/arxa boşluq)
str.strip();           // "Salam Dünya" (Unicode-aware, Java 11)
str.toLowerCase();     // "  salam dünya  "
str.toUpperCase();     // "  SALAM DÜNYA  "
str.charAt(2);         // 'S'
str.substring(2, 7);   // "Salam"
str.indexOf("Dünya");  // 8
str.contains("Salam"); // true
str.startsWith("  S"); // true
str.endsWith("  ");    // true
str.replace("Salam", "Hello"); // "  Hello Dünya  "
str.split(" ");        // ["", "", "Salam", "Dünya", "", ""]
str.isEmpty();         // false (sadəcə boşluq var)
str.isBlank();         // false (Java 11 — strip sonrası boşmu?)

String empty = "";
empty.isEmpty();  // true
empty.isBlank();  // true

String blank = "   ";
blank.isEmpty();  // false
blank.isBlank();  // true (Java 11)
```

---

## Yeni String metodları (Java 11–21)

```java
// ─── Java 11 ─────────────────────────────────────────
class Java11StringMethods {

    @Test
    void stripMethods() {
        String s = "\u2000 Salam \u2000"; // Unicode whitespace

        // trim() — yalnız ASCII boşluqlar
        System.out.println(s.trim().length()); // 7 (Unicode boşluqlar qalır)

        // strip() — Unicode-aware
        System.out.println(s.strip().length()); // 5 ("Salam")

        System.out.println(s.stripLeading());  // Ön boşluqlar
        System.out.println(s.stripTrailing()); // Arxa boşluqlar
    }

    @Test
    void isBlankMethod() {
        System.out.println("".isBlank());       // true
        System.out.println("  ".isBlank());     // true
        System.out.println("\t\n".isBlank());   // true
        System.out.println(" a ".isBlank());    // false
    }

    @Test
    void repeatMethod() {
        String dash = "-".repeat(10);   // "----------"
        String hello = "Ha".repeat(3);  // "HaHaHa"
        String zero = "x".repeat(0);    // ""
    }

    @Test
    void linesMethod() {
        String multiLine = "Birinci\nİkinci\nÜçüncü";

        multiLine.lines()  // Stream<String>
            .forEach(System.out::println);

        long count = multiLine.lines().count(); // 3

        List<String> lineList = multiLine.lines()
            .collect(Collectors.toList());
    }
}

// ─── Java 12 ─────────────────────────────────────────
class Java12StringMethods {

    @Test
    void indentMethod() {
        String text = "Salam\nDünya";

        String indented = text.indent(4);
        // "    Salam\n    Dünya\n"

        String unindented = indented.indent(-2);
        // "  Salam\n  Dünya\n"
    }

    @Test
    void transformMethod() {
        String result = "  salam dünya  "
            .transform(s -> s.strip())
            .transform(s -> s.toUpperCase())
            .transform(s -> s.replace(" ", "_"));
        // "SALAM_DÜNYA"

        // Alternativ (zəncir):
        String result2 = "  salam dünya  "
            .strip()
            .toUpperCase()
            .replace(" ", "_");
    }
}

// ─── Java 15 ─────────────────────────────────────────
class Java15StringMethods {
    // Text Blocks — ayrı mövzuda (146)
}

// ─── Java 21 — String Templates (preview) ─────────────
class Java21StringTemplates {
    // String Templates — STR processor (preview feature)
    // Hələ preview-da — production-da istifadə tövsiyə edilmir

    // String name = "Ali";
    // String greeting = STR."Salam, \{name}!"; // "Salam, Ali!"
    // String math = STR."2 + 2 = \{2 + 2}";   // "2 + 2 = 4"
}
```

---

## String müqayisəsi

```java
class StringComparisonExamples {

    // ─── equals vs == ─────────────────────────────────
    @Test
    void equalsVsReferenceEquality() {
        String a = new String("hello");
        String b = new String("hello");

        System.out.println(a == b);      // false — fərqli reference
        System.out.println(a.equals(b)); // true — eyni məzmun

        // NullPointerException-dan qaçmaq:
        String nullable = null;

        // YANLIŞ: nullable.equals("hello") → NullPointerException
        // DOĞRU: "hello".equals(nullable) → false
        // DOĞRU: Objects.equals(nullable, "hello") → false
    }

    // ─── equalsIgnoreCase ─────────────────────────────
    @Test
    void caseInsensitiveComparison() {
        String email1 = "ALI@EXAMPLE.COM";
        String email2 = "ali@example.com";

        System.out.println(email1.equalsIgnoreCase(email2)); // true
    }

    // ─── compareTo ────────────────────────────────────
    @Test
    void lexicographicComparison() {
        // Negative → s1 < s2
        // Zero → s1 == s2
        // Positive → s1 > s2
        System.out.println("apple".compareTo("banana")); // negative
        System.out.println("banana".compareTo("apple")); // positive
        System.out.println("apple".compareTo("apple"));  // 0

        // Sıralama:
        List<String> names = Arrays.asList("Vəli", "Ali", "Rəhim");
        names.sort(String::compareTo);
        // Ali, Rəhim, Vəli

        names.sort(String.CASE_INSENSITIVE_ORDER);
    }

    // ─── intern() ────────────────────────────────────
    @Test
    void internExample() {
        String a = new String("hello");
        String b = new String("hello");

        System.out.println(a == b); // false

        String aInterned = a.intern(); // Pool-a əlavə et / pooldan götür
        String bInterned = b.intern();

        System.out.println(aInterned == bInterned); // true (eyni pool reference)
    }
}
```

---

## StringBuilder və StringJoiner

```java
class BuilderExamples {

    // ─── StringBuilder — mutable string ───────────────
    @Test
    void stringBuilderExample() {
        StringBuilder sb = new StringBuilder();

        sb.append("Salam");
        sb.append(", ");
        sb.append("Dünya");
        sb.append("!");

        // Digər metodlar
        sb.insert(5, " qəhrəman");       // "Salam qəhrəman, Dünya!"
        sb.delete(5, 14);                 // "Salam, Dünya!"
        sb.replace(7, 12, "Universe");    // "Salam, Universe!"
        sb.reverse();                     // Tərsinə
        sb.setCharAt(0, 'H');             // Konkret simvol dəyiştir

        String result = sb.toString();

        // Başlanğıc kapasitə
        StringBuilder large = new StringBuilder(1024);
    }

    // ─── Loop-da string birləşdirmə ───────────────────
    @Test
    void loopConcatenation() {
        List<String> names = List.of("Ali", "Vəli", "Rəhim");

        // YANLIŞ — hər iterasiyada yeni String yaradılır
        String wrong = "";
        for (String name : names) {
            wrong += name + ", "; // N yeni String!
        }

        // DOĞRU — StringBuilder
        StringBuilder sb = new StringBuilder();
        for (String name : names) {
            sb.append(name).append(", ");
        }
        if (sb.length() > 2) {
            sb.setLength(sb.length() - 2); // Son ", " sil
        }
        String result = sb.toString();

        // ƏN YAXŞI — Streams ilə
        String streamed = names.stream()
            .collect(Collectors.joining(", "));
    }

    // ─── StringJoiner ─────────────────────────────────
    @Test
    void stringJoinerExample() {
        StringJoiner joiner = new StringJoiner(", ", "[", "]");
        // delimiter, prefix, suffix

        joiner.add("Ali");
        joiner.add("Vəli");
        joiner.add("Rəhim");

        System.out.println(joiner); // "[Ali, Vəli, Rəhim]"

        // Boş olduqda:
        StringJoiner empty = new StringJoiner(", ", "[", "]");
        empty.setEmptyValue("Boş");
        System.out.println(empty); // "Boş"

        // Collectors.joining() → StringJoiner istifadə edir
        String joined = List.of("a", "b", "c").stream()
            .collect(Collectors.joining(", ", "[", "]"));
        // "[a, b, c]"
    }
}
```

---

## Regex ilə işləmək

```java
class RegexExamples {

    // ─── matches — bütün string ────────────────────────
    @Test
    void matchesExamples() {
        // Email validation
        String email = "ali@example.com";
        boolean isValidEmail = email.matches("[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}");

        // Telefon nömrəsi
        String phone = "+994501234567";
        boolean isPhone = phone.matches("\\+994[0-9]{9}");

        // Yalnız rəqəm
        boolean isDigits = "12345".matches("\\d+");
    }

    // ─── Pattern + Matcher ────────────────────────────
    @Test
    void patternMatcherExample() {
        String text = "Sifariş #ORD-001 yaradıldı. Sifariş #ORD-002 göndərildi.";

        Pattern pattern = Pattern.compile("ORD-\\d{3}");
        Matcher matcher = pattern.matcher(text);

        List<String> orderIds = new ArrayList<>();
        while (matcher.find()) {
            orderIds.add(matcher.group()); // "ORD-001", "ORD-002"
        }

        // Groups
        Pattern groupPattern = Pattern.compile("(\\w+)@(\\w+\\.\\w+)");
        Matcher groupMatcher = groupPattern.matcher("ali@example.com");

        if (groupMatcher.matches()) {
            String username = groupMatcher.group(1); // "ali"
            String domain = groupMatcher.group(2);   // "example.com"
        }
    }

    // ─── replaceAll, replaceFirst ─────────────────────
    @Test
    void regexReplaceExamples() {
        String text = "2026-01-15 tarixdə 3 sifariş yaradıldı";

        // Bütün rəqəmlər → X
        String replaced = text.replaceAll("\\d", "X");
        // "XXXX-XX-XX tarixdə X sifariş yaradıldı"

        // İlk rəqəm qrupu
        String firstOnly = text.replaceFirst("\\d+", "N");
        // "N-01-15 tarixdə 3 sifariş yaradıldı"

        // Whitespace normalizasiya
        String normalized = "  çox    boşluqlu    mətn  ".strip().replaceAll("\\s+", " ");
        // "çox boşluqlu mətn"
    }

    // ─── split ────────────────────────────────────────
    @Test
    void splitExamples() {
        String csv = "Ali,Vəli,,Rəhim,";

        // Default — boş sonuncu element silinir
        String[] parts = csv.split(",");
        // ["Ali", "Vəli", "", "Rəhim"]  — son boşluq silinir

        // Limit -1 — bütün elementlər (boşluqlar da)
        String[] allParts = csv.split(",", -1);
        // ["Ali", "Vəli", "", "Rəhim", ""]

        // Limit 3 — maksimum 3 hissə
        String[] threeParts = csv.split(",", 3);
        // ["Ali", "Vəli", ",Rəhim,"]

        // Regex ilə split
        String text = "bir iki\tüç\dörd";
        String[] words = text.split("\\s+");
        // ["bir", "iki", "üç", "dörd"]
    }
}
```

---

## Performans məsələləri

```java
// ─── String concatenation həqiqəti ───────────────────
class StringPerformance {

    // Compile time-da sabit + sabit → String Pool
    void compileTimeConstant() {
        String a = "hello" + " " + "world"; // Compile-time: "hello world"
        String b = "hello world";
        System.out.println(a == b); // true (same pool entry)
    }

    // Runtime-da dəyişən + dəyişən → StringBuilder
    void runtimeConcatenation() {
        String s1 = "hello";
        String s2 = " world";
        String s3 = s1 + s2; // Compiler → new StringBuilder().append(s1).append(s2).toString()
    }

    // Loop-da + operatoru → Hər iterasiyada yeni StringBuilder!
    void loopWithPlus() {
        // YANLIŞ (O(n²)):
        String result = "";
        for (int i = 0; i < 1000; i++) {
            result += "item" + i + ", "; // Her dəfə yeni StringBuilder!
        }

        // DOĞRU (O(n)):
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < 1000; i++) {
            sb.append("item").append(i).append(", ");
        }
        String good = sb.toString();
    }

    // ─── String.format vs + ───────────────────────────
    void formatPerformance() {
        String name = "Ali";
        int age = 25;

        // Sadə birləşmə üçün + yaxşıdır:
        String s1 = name + " is " + age; // Sürətli

        // Mürəkkəb format üçün formatted():
        String s2 = "%s is %d years old".formatted(name, age); // Oxunaqlı

        // Çox parametrli log üçün SLF4J {} istifadə edin:
        // log.info("User {} created order {}", userId, orderId);
        // (String birləşdirilmir — log disabled olduqda sıfır xərc)
    }

    // ─── intern() istifadəsi ──────────────────────────
    void internUsage() {
        // Memory optimization: çox sayda eyni string varsa
        // Məs: DB-dən gələn status field-ləri
        String status = dbResult.getString("status").intern();
        // Bütün "PENDING" string-ləri eyni pool reference
        // Müqayisədə == işlənə bilər (amma equals tövsiyə edilir)
    }
}
```

---

## İntervyu Sualları

### 1. String niyə immutable-dır?
**Cavab:** (1) **Security** — şifrə, URL, network connection kimi həssas məlumatlar dəyişdirilə bilməz. (2) **Thread-safety** — paylaşılan String-lər sync olmadan istifadə edilə bilər. (3) **String Pool** — eyni literal bir dəfə yaradılır, dəfələrlə paylaşılır; mutable olsaydı, bir yerdə dəyişiklik hamısını etkilərdi. (4) **HashMap key** — hash code sabitdir, cache-lənə bilər.

### 2. String Pool necə işləyir?
**Cavab:** JVM-in `PermGen` (Java 7-dən `Heap`)-ında xüsusi region. Literal string (`"hello"`) Pool-da yaradılır — eyni literal bir dəfə mövcuddur. `new String("hello")` Heap-də ayrı obyekt. `intern()` — Heap-dəki stringi Pool-a yerləşdirir/Pool-dan mövcudunu götürür. String Pool Java 7-dən Heap-ə köçürüldü — GC tərəfindən idarə olunur.

### 3. == vs equals() String müqayisəsindəki fərq?
**Cavab:** `==` — reference equality (eyni memory address). `equals()` — value equality (məzmun). İki fərqli `new String("hello")` eyni məzmuna sahib amma `==` false qaytarır. String literal-lar (`"hello"`) Pool-da bir dəfə yaradıldığı üçün `==` true ola bilər — amma bu implementasiya detalıdır, etibarlı deyil. Həmişə `equals()` istifadə edin; null-safety üçün `Objects.equals()` ya da `"literal".equals(var)`.

### 4. StringBuilder vs StringBuffer fərqi?
**Cavab:** Hər ikisi mutable string. `StringBuffer` — synchronized, thread-safe, yavaş. `StringBuilder` — synchronized deyil, thread-safe deyil, sürətli. Single-thread kodda həmişə `StringBuilder`. Multi-thread paylaşılan string build etmək lazımdırsa `StringBuffer` ya da external sync. Praktikada `StringBuffer` nadir istifadə olunur.

### 5. Java 11-dən hansı String metodları gəldi?
**Cavab:** `isBlank()` — trim sonrası boşmu (Unicode-aware). `strip()`/`stripLeading()`/`stripTrailing()` — Unicode whitespace dəstəkli `trim()`. `repeat(n)` — stringi n dəfə təkrar. `lines()` — `Stream<String>` sətir axışı. Java 12-dən: `indent(n)`, `transform(Function)`. Java 15: Text Blocks. Bu metodlar köhnə `trim()`/`isEmpty()` API-ni müasirləşdirir.

*Son yenilənmə: 2026-04-10*
