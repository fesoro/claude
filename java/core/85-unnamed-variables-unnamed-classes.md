# Unnamed Variables, Classes və Instance Main (Java 21-22+) (Senior)

## İcmal

Java 21-22-də üç əlaqəli "noise reduction" feature gəldi: **unnamed variables** (`_` ilə atılan dəyər), **unnamed classes** (class keyword-süz script faylları) və **instance main method** (static olmayan `void main()`). Bunlar ayrı JEP-lər olsa da eyni məqsədi paylaşır — boilerplate azaltmaq.

| Feature | JEP | Status |
|---------|-----|--------|
| Unnamed Variables and Patterns | JEP 443 (preview) → **JEP 456 (Java 22, final)** | Production-ready |
| Unnamed Classes and Instance Main | JEP 463 (preview) → **JEP 477 (Java 23, final)** | Production-ready |

---

## Niyə Vacibdir

- `catch (SomeException e)` blokunda `e` istifadə edilmədikdə linter xəbərdarlıq verir; `_` bunu standart şəkildə ifadə edir.
- Pattern matching-də komponent adları lazım olmayan yerlər get-gedə artır — `_` verbose yükü azaldır.
- Unnamed classes Java-nı müsahibə hazırlığı / prototyping üçün Python/PHP kimi rahat edir — 5 sətir boilerplate yazmadan `println` çağırmaq mümkündür.
- Kod **niyyəti** daha aydın ifadə edir: `_` deyir ki "bu dəyər qəsdən atılır", adsız bir dəyişən deyil.

---

## Əsas Anlayışlar

### Unnamed Variables — `_` Semantikası

Java 22-dən əvvəl `_` identifier qadağan idi (Java 9-da deprecated, Java 16-da compile xətası). Java 22-də yenidən aktivləşdi — amma yalnız **atılacaq dəyər** üçün.

**Əsas fərq digər dillərdən:** Java-da eyni scope-da bir neçə `_` ola bilər — hər biri müstəqil yerdir, paylaşılmır. Python-da `_` yenidən yazılır, Java-da hər biri ayrı slot.

```java
// Eyni scope-da çoxlu _
var _ = list.remove(0);
var _ = cache.put("key", value);  // OK — eyni scope-da ikinci _
```

### Unnamed Classes — Faylın Özü Class Kimi

Java 23+ bir faylda class elanı olmadan birbaşa metod yazmaq imkanı verir. Compiler faylı anonim bir top-level class kimi qəbul edir.

**Qaydalar:**
- Tək fayl, tək unnamed class
- `package` elanı yoxdur
- `public`, `private` kimi access modifier yoxdur
- `void main()` giriş nöqtəsidir (instance metod da ola bilər)
- Import-lar mümkündür

### Instance Main Method

Unnamed class içindəki `main` **static olmayana bilər**. Compiler `new UnnamedClass().main()` kimi çağırır. Prioritet sırası (birinci tapılan istifadə olunur):

1. `static void main(String[] args)` — klassik
2. `static void main()` — args-sız static
3. `void main(String[] args)` — instance, args ilə
4. `void main()` — instance, args-sız

---

## Praktik Baxış

### Unnamed Variables — Real İstifadə

**1. Side-effect çağırışlarının return dəyərini atmaq:**
```java
// Əvvəl — oxunmayan dəyişən adı
var ignored = futureRef.getAndSet(null);
var prev    = map.put("key", value);

// Sonra — niyyət aydındır
var _ = futureRef.getAndSet(null);
var _ = map.put("key", value);
```

**2. Catch blokunda exception dəyişənini istifadə etməmək:**
```java
// Əvvəl — linter xəbərdarlıq verir
try {
    Integer.parseInt(input);
} catch (NumberFormatException e) {
    return Optional.empty();   // e istifadə edilmir
}

// Sonra — niyyət açıqdır
try {
    Integer.parseInt(input);
} catch (NumberFormatException _) {
    return Optional.empty();
}
```

**3. Pattern matching-də lazımsız komponentləri atma:**
```java
sealed interface Shape permits Circle, Rectangle, Triangle {}
record Circle(double radius) implements Shape {}
record Rectangle(double width, double height) implements Shape {}
record Triangle(double a, double b, double c) implements Shape {}

// Yalnız Circle radius-u lazımdır, qalan component-lər atılır
double area = switch (shape) {
    case Circle(double r)          -> Math.PI * r * r;
    case Rectangle(double w, double h) -> w * h;
    case Triangle(_, _, _)         -> computeHeron(shape); // komponentlər lazım deyil
};
```

**4. Nested unnamed patterns:**
```java
record Point(int x, int y) {}
record Segment(Point start, Point end) {}

// Yalnız start.x lazımdır
if (seg instanceof Segment(Point(int x, _), _)) {
    System.out.println("Başlanğıc x: " + x);
}
```

**5. Loop sayacı — iterasiya sayını hesablamaq:**
```java
int count = 0;
for (var _ : eventStream) {
    count++;
}
// Deyir ki: element content-i lazım deyil, yalnız say
```

**6. Multi-catch ilə kombinasiya:**
```java
try {
    riskyOperation();
} catch (IOException | SQLException _) {
    log.warn("Operation failed, using fallback");
    return fallback();
}
```

### Unnamed Classes — Real İstifadə

```java
// HelloWorld.java — class elanı yoxdur
void main() {
    System.out.println("Hello, Java 23!");
}
```

```bash
java HelloWorld.java   # birbaşa çalıştır
```

**Script-style utility:**
```java
// DbMigrationCheck.java
import java.sql.*;

void main() throws Exception {
    var url = System.getenv("DATABASE_URL");
    try (var conn = DriverManager.getConnection(url)) {
        var meta = conn.getMetaData();
        System.out.println("DB: " + meta.getDatabaseProductName());
        System.out.println("Version: " + meta.getDatabaseProductVersion());
    }
}
```

### Unnamed Variables-ın Yanlış İstifadəsi

```java
// Pis — xəta mesajını atırıq, problem izlənilmir
} catch (Exception _) {    // çox geniş catch + niyyətsiz atma
    // silent failure — production-da bug mənbəyi
}

// Yaxşı — yalnız gözlənilən, recovery mümkün hallarda
} catch (NumberFormatException _) {
    return defaultValue;   // bu case üçün məntiqlidir
}
```

### PHP Müqayisəsi

| Feature | PHP | Java |
|---------|-----|------|
| Dəyər atmaq | `list(, $second) = $arr;` — comma ilə atılır | `var _ = expr;` — `_` ilə explicit atma |
| Istifadəsiz catch var | `catch (Exception $e)` — linter xəbərdarlıq | `catch (Exception _)` — standart, niyyətli |
| Script yazma | `<?php echo "Hello";` — birbaşa | Unnamed class: `void main() {}` — Java 23+ |
| Pattern atma | `match` əllə ignore edir | `case Foo(_, int x)` — syntax dəstəyi var |
| Boilerplate | `<?php` başlığı lazımdır | Unnamed class ilə class bloku lazım deyil |

PHP-də `list(, $second) = [1, 2]` ilk elementi atır — amma bu `_` deyil, sadəcə boş yer. Java-nın `_` bir addım irəlidir: explicit, aydın, bir neçə yerdə eyni scope-da istifadə olunur.

---

## Nümunələr

### Ümumi Nümunə

Unnamed variable-lar "danışıqlı" kodu qısaldır: `e` dəyişən adı yaratmaq əvəzinə `_` deyilir ki "bu yerə baxma, önəmli deyil". Unnamed class-lar isə Java-nı quick script dili kimi işlədərkən `public class Foo { public static void main(String[] args) { ... } }` boilerplate-ini aradan qaldırır.

### Kod Nümunəsi

```java
// ── Fayl 1: UnnamedVarsDemo.java (normal named class) ──────

import java.util.*;
import java.util.concurrent.atomic.AtomicReference;

public class UnnamedVarsDemo {

    sealed interface Expr permits Num, Add, Mul, Neg {}
    record Num(int value)              implements Expr {}
    record Add(Expr left, Expr right)  implements Expr {}
    record Mul(Expr left, Expr right)  implements Expr {}
    record Neg(Expr operand)           implements Expr {}

    // ── 1. Pattern matching-də unnamed komponentlər ─────────
    static String describe(Expr expr) {
        return switch (expr) {
            case Num(int v)             -> "ədəd: " + v;
            case Add(Num(int v), _)     -> "sola Num əlavəsi: " + v;     // sağ tərəf atılır
            case Add(_, Num(int v))     -> "sağa Num əlavəsi: " + v;     // sol tərəf atılır
            case Add(_, _)              -> "iki ifadənin cəmi";           // hər ikisi atılır
            case Mul(Num(int v), _)     -> "sol " + v + " ilə vurma";
            case Mul(_, _)              -> "vurma";
            case Neg(_)                -> "mənfi ifadə";                  // operand atılır
        };
    }

    // ── 2. Side-effect call-larda return atma ────────────────
    static void cacheOperations() {
        var cache = new HashMap<String, String>();

        // put() köhnə dəyəri qaytarır — bizi maraqlandırmır
        var _ = cache.put("user:1", "Əli");
        var _ = cache.put("user:2", "Aynur");
        var _ = cache.put("user:1", "Əliagə");  // override — köhnəsi atılır

        // AtomicReference swap — köhnə dəyər lazım deyil
        var ref = new AtomicReference<>("v1");
        var _ = ref.getAndSet("v2");

        System.out.println(cache);
        System.out.println(ref.get());
    }

    // ── 3. Catch-də exception dəyişəni atma ─────────────────
    static Optional<Integer> parseIntSafe(String s) {
        try {
            return Optional.of(Integer.parseInt(s));
        } catch (NumberFormatException _) {
            // format xətasında default qaytar — mesaj lazım deyil
            return Optional.empty();
        }
    }

    // ── 4. Multi-catch + unnamed ─────────────────────────────
    static String readConfig(String key) {
        try {
            return System.getenv(key);
        } catch (SecurityException | NullPointerException _) {
            return "default";
        }
    }

    // ── 5. Loop sayacı — element content-i lazım deyil ───────
    static int countNonNulls(List<?> list) {
        int count = 0;
        for (var _ : list) {  // element-in özü lazım deyil, sayırıq
            count++;
        }
        return count;
    }

    // ── 6. Nested unnamed pattern ────────────────────────────
    record Point(int x, int y) {}
    record Line(Point start, Point end) {}

    static int startX(Object obj) {
        if (obj instanceof Line(Point(int x, _), _)) {
            return x;  // yalnız start.x — qalan 3 int atılır
        }
        return -1;
    }

    // ── 7. Try-with-resources — resource adı lazım deyil ─────
    static long fileLineCount(String path) throws Exception {
        long lines = 0;
        try (var reader = new java.io.BufferedReader(new java.io.FileReader(path))) {
            while (reader.readLine() != null) {
                lines++;
            }
        }
        return lines;
        // Əgər reader-ı istifadə etməsəydik: try (var _ = ...) — amma resource-un
        // adı lazım olmayan hal nadirdir, bu nümunə yalnız syntax üçündür
    }

    public static void main(String[] args) {
        // Pattern matching nümunəsi
        Expr e1 = new Add(new Num(3), new Mul(new Num(4), new Num(5)));
        Expr e2 = new Add(new Num(10), new Add(new Num(1), new Num(2)));
        System.out.println(describe(e1));  // sola Num əlavəsi: 3
        System.out.println(describe(e2));  // sola Num əlavəsi: 10

        // Cache ops
        cacheOperations();

        // Parse
        System.out.println(parseIntSafe("42"));      // Optional[42]
        System.out.println(parseIntSafe("abc"));     // Optional.empty

        // Line startX
        var line = new Line(new Point(7, 3), new Point(10, 15));
        System.out.println(startX(line));             // 7
    }
}
```

```java
// ── Fayl 2: QuickScript.java — Unnamed Class (Java 23+) ────
// Heç bir class bloku yoxdur — birbaşa çalışır

import java.net.URI;
import java.net.http.*;
import java.time.Instant;

void main() throws Exception {
    System.out.println("Başlama vaxtı: " + Instant.now());

    var client = HttpClient.newHttpClient();
    var request = HttpRequest.newBuilder()
        .uri(URI.create("https://httpbin.org/get"))
        .build();

    // Response body-ni atırıq — yalnız status kodu lazımdır
    var response = client.send(request, HttpResponse.BodyHandlers.discarding());
    System.out.println("Status: " + response.statusCode());
}
```

```java
// ── Fayl 3: InstanceMain.java — Instance main (Java 23+) ───

import java.util.List;

// Class adı var, amma main static deyil
class DataProcessor {
    private final List<Integer> data;

    DataProcessor() {
        this.data = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
    }

    // Instance main — static olmayan giriş nöqtəsi
    void main() {
        var evens = data.stream()
            .filter(n -> n % 2 == 0)
            .toList();
        System.out.println("Cüt ədədlər: " + evens);

        long sum = data.stream().mapToLong(Integer::longValue).sum();
        System.out.println("Cəm: " + sum);
    }
}
```

---

## Praktik Tapşırıqlar

**1. Unnamed catch-ləri aşkar et**
Mövcud bir Java layihəsini aç, `catch (Exception e)` axtarışı et. `e` istifadə edilmədən yalnız log.warn() çağrılan yerləri `catch (Exception _)` ilə yenilə.

**2. Pattern matching refactor**
Aşağıdakı switch-i unnamed pattern-lərlə yenidən yaz:
```java
switch (shape) {
    case Circle c    -> "dairə, radius=" + c.radius();
    case Rect r      -> "düzbucaqlı, area=" + (r.width() * r.height());
    case Triangle t  -> "üçbucaq";
}
```
`Triangle` case-ında komponent yoxdur; `Rect` area hesabında width və height lazımdır amma `r` adı lazım deyil.

**3. Unnamed class ilə utility script**
`SystemInfo.java` adlı unnamed class yaz: JVM versiyasını, OS adını, available processor sayını, max heap-i çap etsin. `java SystemInfo.java` ilə çalışsın.

**4. Loop sayacı vs stream count**
1000 elementli `List<String>`-in tərsinə for-each-dən null olmayan elementlərini `_` ilə say. Eyni nəticəni `stream().filter(Objects::nonNull).count()` ilə müqayisə et.

**5. Instance main pattern**
`HttpHealthChecker` unnamed class yaz: `void main()` daxilindən `java.net.http.HttpClient` ilə `https://httpbin.org/get`-ə GET at, status-u yoxla. Static boilerplate olmadan çalışsın.

---

## Əlaqəli Mövzular

- [82-sealed-classes.md](82-sealed-classes.md) — Sealed class-larla pattern matching-in əsası
- [83-pattern-matching.md](83-pattern-matching.md) — `switch` pattern matching, record patterns — `_` ilə birlikdə ən çox istifadə olunur
- [44-switch-expressions.md](44-switch-expressions.md) — Arrow syntax switch — unnamed pattern-lərin yerləşdiyi kontekst
- [84-sequenced-collections.md](84-sequenced-collections.md) — Java 21 modern API — eyni era features
- [86-gatherers.md](86-gatherers.md) — Java 22+ Stream Gatherers — eyni release dövrü
