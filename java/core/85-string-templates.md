# 85 — Java String Templates — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [String Templates nədir?](#string-templates-nədir)
2. [STR template processor](#str-template-processor)
3. [FMT template processor](#fmt-template-processor)
4. [RAW template processor](#raw-template-processor)
5. [Custom Template Processor](#custom-template-processor)
6. [String Templates vs alternativlər](#string-templates-vs-alternativlər)
7. [İntervyu Sualları](#intervyu-sualları)

---

## String Templates nədir?

```
Tarix:
  Java 21 — Preview Feature (JEP 430)
  Java 22 — 2nd Preview (JEP 459)
  Java 23 — Withdrawn/Redesign
  Java 24+ — Yenidən hazırlanır (spec dəyişdi)

  NOT: Bu feature hələ finallaşmayıb!
  Hazırkı durum: String templates experimental vəziyyətdədir.
  Amma konsept Java developer üçün vacibdir.

Problem:
  String birləşdirmə üsulları:
    + operator:  "Salam " + name + "! Yaşın: " + age
    format():    String.format("Salam %s! Yaşın: %d", name, age)
    formatted(): "Salam %s! Yaşın: %d".formatted(name, age)
    StringBuilder: sb.append("Salam ").append(name)...
    MessageFormat: "{0}! Yaşın: {1}", name, age

  Problemlər:
    → Oxunaqlılıq aşağı
    → İndeks/tip xətaları kompile zamanı tutulmur
    → SQL injection riski (kontekst yoxlaması yoxdur)
    → Verbose

String Templates həlli:
  STR."Salam \{name}! Yaşın: \{age}"

  → Template expression: \{...}
  → Template processor: STR, FMT, RAW
  → Custom processor: validasiya, escaping, transformation
  → Type-safe, compile-time yoxlama mümkün
```

---

## STR template processor

```java
// ─── Əsas istifadə ───────────────────────────────────────
// JDK 21+ Preview — --enable-preview flag lazım!
// javac --enable-preview --release 21 ...
// java  --enable-preview ...

String name = "Əli";
int age = 28;

// Köhnə yanaşmalar:
String old1 = "Salam " + name + "! Yaşın: " + age;
String old2 = "Salam %s! Yaşın: %d".formatted(name, age);

// YENİ — String Template (Preview):
String result = STR."Salam \{name}! Yaşın: \{age}";
// → "Salam Əli! Yaşın: 28"

// ─── Expression dəstəyi ──────────────────────────────────
int x = 10, y = 20;
String calc = STR."\{x} + \{y} = \{x + y}";
// → "10 + 20 = 30"

// Metod çağırışı
String upper = STR."Böyük hərflə: \{name.toUpperCase()}";
// → "Böyük hərflə: ƏLİ"

// Conditional expression
boolean isAdult = age >= 18;
String status = STR."\{name} \{isAdult ? "yetkindir" : "yetkin deyil"}";

// ─── Multi-line (Text Block ilə) ─────────────────────────
String json = STR."""
    {
        "name": "\{name}",
        "age": \{age},
        "adult": \{isAdult}
    }
    """;

// ─── Nested template ─────────────────────────────────────
record Point(double x, double y) {}
Point p = new Point(3.14, 2.71);

String point = STR."Nöqtə: (\{p.x()}, \{p.y()})";

// Nested STR:
String nested = STR."Koordinat: \{STR."(\{p.x()}, \{p.y()})"}";

// ─── Collection/Array ────────────────────────────────────
List<String> fruits = List.of("alma", "armud", "gilas");
String list = STR."Meyvələr: \{fruits}";
// → "Meyvələr: [alma, armud, gilas]"

// ─── SQL sorğusu (context-aware) ─────────────────────────
String customerId = "c123";
int limit = 10;

// SQLS — hypothetical SQL-safe processor:
// PreparedStatement ps = SQLS."SELECT * FROM orders WHERE customer_id = \{customerId} LIMIT \{limit}";
// → Parametrized query, SQL injection yoxdur!

// Müqayisə — köhnə:
String unsafeSql = "SELECT * FROM orders WHERE customer_id = '" + customerId + "'";
// → SQL injection riski var! '\{customerId}' → DROP TABLE orders; --

// ─── HTML template (html-safe processor) ─────────────────
String userInput = "<script>alert('xss')</script>";

// HTMLS — hypothetical HTML-safe processor:
// String html = HTMLS."<div>\{userInput}</div>";
// → "<div>&lt;script&gt;alert('xss')&lt;/script&gt;</div>" — escaped!
```

---

## FMT template processor

```java
// ─── FMT — format specifier dəstəkləyir ─────────────────
// java.util.FormatProcessor.FMT

double price = 1234.567;
int count = 42;
LocalDate date = LocalDate.of(2024, 3, 15);

// STR — format yoxdur:
String raw = STR."Qiymət: \{price}";
// → "Qiymət: 1234.567"

// FMT — format specifier:
String formatted = FMT."Qiymət: %.2f\{price} AZN";
// → "Qiymət: 1234.57 AZN"

String countStr = FMT."Say: %05d\{count}";
// → "Say: 00042"

// Eyni format specifiers String.format() ilə eyni:
// %d  → integer
// %f  → float/double
// %.2f → 2 onluq rəqəm
// %s  → string
// %05d → 5 hərfli, sıfırla doldur
// %10s → sağa yastıqlama, 10 simvol

// ─── Multi-column report ──────────────────────────────────
record Product(String name, double price, int stock) {}

List<Product> products = List.of(
    new Product("Alma",   1.50,  100),
    new Product("Armud",  2.30,   50),
    new Product("Gilas",  5.00,   25)
);

String header = FMT."%-15s %10s %8s%n\{"Ad"}\{"Qiymət"}\{"Say"}";
System.out.print(header);

for (Product p : products) {
    String row = FMT."%-15s %10.2f %8d%n\{p.name()}\{p.price()}\{p.stock()}";
    System.out.print(row);
}
// Output:
// Ad                 Qiymət      Say
// Alma                 1.50      100
// Armud                2.30       50
// Gilas                5.00       25

// ─── Locale-aware formatting ─────────────────────────────
// FMT default Locale.ROOT istifadə edir
// Locale-specific üçün custom processor lazımdır
```

---

## RAW template processor

```java
// ─── RAW — işlənməmiş template qaytarır ─────────────────
// java.lang.StringTemplate.RAW

int x = 42;
StringTemplate template = RAW."Dəyər: \{x}";

// StringTemplate — template metadata:
System.out.println(template.fragments());  // ["Dəyər: ", ""]
System.out.println(template.values());    // [42]

// STR ilə eyni nəticə:
String result = STR.process(template);    // "Dəyər: 42"

// ─── RAW niyə lazımdır? ───────────────────────────────────
// Custom processor-ə template metadata lazımdır

StringTemplate sqlTemplate = RAW."SELECT * FROM \{tableName} WHERE id = \{id}";
List<String> parts = sqlTemplate.fragments();  // ["SELECT * FROM ", " WHERE id = ", ""]
List<Object> vals  = sqlTemplate.values();     // ["orders", 42]
// → Parametrized query yaratmaq üçün!

// ─── Template storage/caching ────────────────────────────
// RAW ilə template-i saxla, sonra işlə

Map<String, StringTemplate> templateCache = new HashMap<>();

// Template-i cache-ə al (values hələ evaluate olunmur!)
// NOT: values evaluate olunur amma interning edilmir
// Bu caching pattern tam düzgün deyil — illüstrasiya üçün
StringTemplate cached = RAW."Salam \{getUserName()}!";
```

---

## Custom Template Processor

```java
// ─── Custom Template Processor ───────────────────────────
// StringTemplate.Processor functional interface

@FunctionalInterface
public interface StringTemplate.Processor<R, E extends Throwable> {
    R process(StringTemplate template) throws E;
}

// ─── JSON-safe processor ─────────────────────────────────
// String dəyərləri JSON escape edir

public class JsonProcessor implements StringTemplate.Processor<String, RuntimeException> {

    public static final JsonProcessor JSON = new JsonProcessor();

    @Override
    public String process(StringTemplate template) {
        StringBuilder sb = new StringBuilder();
        Iterator<String> fragments = template.fragments().iterator();
        Iterator<Object> values = template.values().iterator();

        while (fragments.hasNext()) {
            sb.append(fragments.next());
            if (values.hasNext()) {
                Object value = values.next();
                sb.append(jsonEscape(value));
            }
        }
        return sb.toString();
    }

    private String jsonEscape(Object value) {
        if (value == null) return "null";
        if (value instanceof Number || value instanceof Boolean) {
            return value.toString();
        }
        // String — quote + escape
        String str = value.toString()
            .replace("\\", "\\\\")
            .replace("\"", "\\\"")
            .replace("\n", "\\n")
            .replace("\r", "\\r")
            .replace("\t", "\\t");
        return "\"" + str + "\"";
    }
}

// İstifadə:
var JSON = JsonProcessor.JSON;
String name = "John \"The Boss\" Doe";
int age = 30;
boolean active = true;

String json = JSON."""
    {
        "name": \{name},
        "age": \{age},
        "active": \{active}
    }
    """;
// → {"name": "John \"The Boss\" Doe", "age": 30, "active": true}

// ─── SQL-safe Processor ──────────────────────────────────
public class SqlProcessor
        implements StringTemplate.Processor<PreparedStatement, SQLException> {

    private final Connection connection;

    public SqlProcessor(Connection connection) {
        this.connection = connection;
    }

    @Override
    public PreparedStatement process(StringTemplate template) throws SQLException {
        // Fragment-ləri ? ilə birləşdir
        String sql = String.join("?", template.fragments());

        PreparedStatement ps = connection.prepareStatement(sql);

        // Dəyərləri set et (SQL injection yoxdur!)
        List<Object> values = template.values();
        for (int i = 0; i < values.size(); i++) {
            ps.setObject(i + 1, values.get(i));
        }
        return ps;
    }
}

// İstifadə:
SqlProcessor SQL = new SqlProcessor(connection);
String customerId = "c123'; DROP TABLE orders; --";  // SQL injection cəhdi
int minAmount = 100;

// ✅ Güvənli — parametrized query:
PreparedStatement ps = SQL."SELECT * FROM orders WHERE customer_id = \{customerId} AND amount > \{minAmount}";
// → SELECT * FROM orders WHERE customer_id = ? AND amount > ?
// → Parameters: ["c123'; DROP TABLE orders; --", 100]
// → SQL injection işləmir!

// ─── HTML-safe Processor ─────────────────────────────────
public class HtmlProcessor implements StringTemplate.Processor<String, RuntimeException> {

    public static final HtmlProcessor HTML = new HtmlProcessor();

    @Override
    public String process(StringTemplate template) {
        StringBuilder sb = new StringBuilder();
        Iterator<String> fragments = template.fragments().iterator();
        Iterator<Object> values = template.values().iterator();

        while (fragments.hasNext()) {
            sb.append(fragments.next());  // HTML-i escape etmə (trusted)
            if (values.hasNext()) {
                sb.append(htmlEscape(values.next())); // User data-nı escape et!
            }
        }
        return sb.toString();
    }

    private String htmlEscape(Object value) {
        return value.toString()
            .replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
            .replace("\"", "&quot;")
            .replace("'", "&#x27;");
    }
}

// İstifadə:
var HTML = HtmlProcessor.HTML;
String userComment = "<script>alert('XSS')</script> Salam!";
String title = "Order #42";

String html = HTML."""
    <div class="card">
        <h2>\{title}</h2>
        <p class="comment">\{userComment}</p>
    </div>
    """;
// → <div class="card">
//     <h2>Order #42</h2>  ← escaped deyil (trusted template)
//     <p class="comment">&lt;script&gt;alert('XSS')&lt;/script&gt; Salam!</p>
//   </div>
```

---

## String Templates vs alternativlər

```java
// ─── Müqayisə ────────────────────────────────────────────

// 1. String concatenation (+)
String v1 = "Ad: " + firstName + " " + lastName + ", yaş: " + age;
// Oxunaqlılıq: ❌  SQL injection: ❌  Format: ❌  Type-safe: ✅

// 2. String.format() / formatted()
String v2 = "Ad: %s %s, yaş: %d".formatted(firstName, lastName, age);
// Oxunaqlılıq: ✅  SQL injection: ❌  Format: ✅  Type-safe: ❌ (%s vs %d xəta runtime-da)

// 3. StringBuilder
StringBuilder sb = new StringBuilder();
sb.append("Ad: ").append(firstName).append(" ").append(lastName)
  .append(", yaş: ").append(age);
String v3 = sb.toString();
// Oxunaqlılıq: ❌  SQL injection: ❌  Format: ❌  Type-safe: ✅

// 4. MessageFormat
String v4 = MessageFormat.format("Ad: {0} {1}, yaş: {2}", firstName, lastName, age);
// Oxunaqlılıq: ✅  SQL injection: ❌  Format: ✅  Type-safe: ❌

// 5. String Templates (Preview)
String v5 = STR."Ad: \{firstName} \{lastName}, yaş: \{age}";
// Oxunaqlılıq: ✅✅  SQL injection: Custom processor ilə ✅  Format: FMT ilə ✅  Type-safe: ✅

// ─── Template Engines müqayisəsi ─────────────────────────
// Thymeleaf, Freemarker, Mustache — server-side HTML template
// String Templates — Java kod içi string interpolation
// Fərq: String Templates compile-time yoxlama verir

// ─── Kotlin ilə müqayisə ─────────────────────────────────
// Kotlin string interpolation (stable):
// "Salam $name! Yaşın: ${age + 1}"

// Java String Templates (preview):
// STR."Salam \{name}! Yaşın: \{age + 1}"
// → Kotlin-ə oxşar amma custom processor əlavəsi var

// ─── hazırki tövsiyə (2024) ──────────────────────────────
// String Templates hələ preview/redesign mərhələsindədir
// Production üçün: formatted() ya da StringBuilder
// SQL üçün: PreparedStatement (həmişə!)
// HTML üçün: Thymeleaf/template engine
// Gələcəkdə: String Templates finallaşanda istifadə
```

---

## İntervyu Sualları

### 1. Java String Templates nədir?
**Cavab:** Java 21-də Preview Feature kimi gəldi (JEP 430). `STR."Salam \{name}!"` sintaksisi ilə string interpolasiya. Üç built-in processor: `STR` (sadə birləşdirmə), `FMT` (format specifier dəstəyi), `RAW` (işlənməmiş template metadata). Custom processor yazmaq mümkündür — SQL injection, XSS kimi security problemlərini template-level-da həll edir. Hazırda hələ preview/redesign mərhələsindədir (Java 23-də geri çəkildi).

### 2. STR vs FMT fərqi nədir?
**Cavab:** `STR` — sadə string interpolasiya, `\{expression}` dəyərini `toString()` ilə string-ə çevirir. Format yoxdur. `FMT` — format specifier dəstəkləyir: `FMT."Qiymət: %.2f\{price}"` — `String.format()` kimi. `%d`, `%f`, `%.2f`, `%05d` kimi formatlar işləyir. `STR` daha sadə, `FMT` number/date formatting üçün. Hər ikisi `StringTemplate.Processor` interfeysi implement edir.

### 3. Custom Template Processor niyə vacibdir?
**Cavab:** Adi string interpolasiya SQL injection, XSS kimi security problemlərini həll etmir — yalnız string birləşdirir. Custom processor context-aware processing imkanı verir: SQL processor `\{value}` əvəzinə `?` qoyur, PreparedStatement parametrini set edir → SQL injection yoxdur. HTML processor `\{userInput}` HTML-i escape edir → XSS yoxdur. Bu, String Templates-in əsas üstünlüyüdür — sadə interpolasiyadan fərqli olaraq template engine semantikası verir.

### 4. String Templates-in cari statusu nədir?
**Cavab:** Java 21-də 1st Preview (JEP 430), Java 22-də 2nd Preview (JEP 459), Java 23-də **geri çəkildi** — spec yenidən hazırlanır. Hazırda (2024) stable feature deyil. `--enable-preview` flag-ı ilə compile edilməlidir. Production kodunda istifadə tövsiyə edilmir. Alternativ: `String.formatted()`, `StringBuilder`, ya da template engine (Thymeleaf). Java 25+ versiyalarda yenidən gəlməsi gözlənilir.

### 5. String Templates SQL injection-dan necə qoruyur?
**Cavab:** `SQL."SELECT * FROM orders WHERE id = \{userId}"` — custom SQL processor `\{userId}` yerinə `?` yazır, userId-ni PreparedStatement parametri kimi set edir. Nəticə: parametrized query. Hacker `userId = "1; DROP TABLE orders; --"` versə belə bu literal string kimi database-ə gedər, SQL kimi icra olunmaz. Köhnə string concatenation `"...WHERE id = '" + userId + "'"` — userId birbaşa SQL-ə gedir → injection riski. Template processor bu ayrımı syntax-level-da məcburi edir.

*Son yenilənmə: 2026-04-10*
