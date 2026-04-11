# Java Text Blocks — Geniş İzah

## Mündəricat
1. [Text Blocks nədir?](#text-blocks-nədir)
2. [Əsas sintaksis](#əsas-sintaksis)
3. [Indentation idarəsi](#indentation-idarəsi)
4. [Escape sequences](#escape-sequences)
5. [String metodları ilə](#string-metodları-ilə)
6. [Praktik istifadə sahələri](#praktik-istifadə-sahələri)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Text Blocks nədir?

**Text Blocks** (Java 15, JEP 378) — çoxsətirli string literal-lar üçün daha oxunaqlı sintaksis. Escape karakterlər minimuma endirilir.

```java
// ƏVVƏL — ənənəvi string:
String json = "{\n" +
    "  \"id\": 1,\n" +
    "  \"name\": \"Ali\",\n" +
    "  \"status\": \"ACTIVE\"\n" +
    "}";

// SONRA — text block:
String json = """
    {
      "id": 1,
      "name": "Ali",
      "status": "ACTIVE"
    }
    """;

// ─── Fərqlər ──────────────────────────────────────────
// ✅ Dırnaqları escape etmək lazım deyil
// ✅ \n yazılmır — real yeni sətir istifadə olunur
// ✅ + concatenation yoxdur
// ✅ Kodun indentasiyası qorunur
```

---

## Əsas sintaksis

```java
// ─── Açılış: """ + yeni sətir ─────────────────────────
// Bağlanış: """ (ixtiyari yerə)

String empty = """
    """; // Boş string (trailing newline ilə)

String single = """
    Bir sətir
    """; // "Bir sətir\n"

String multi = """
    Birinci sətir
    İkinci sətir
    Üçüncü sətir
    """;

// ─── Bağlanış mövqeyinin əhəmiyyəti ──────────────────
// """ bağlanışı sıfırdan başlayırsa:
String noIndent = """
Hello
World
"""; // "Hello\nWorld\n"

// """ bağlanışı girintililiyə görə:
String withIndent = """
    Hello
    World
    """; // "Hello\nWorld\n" (ön boşluqlar çıxarılır)

// ─── Trailing newline ──────────────────────────────────
// """ yeni sətirdə → trailing \n var
String withNewline = """
    content
    """; // "content\n"

// """ content ilə eyni sətirdə → trailing \n YOX
String noNewline = """
    content"""; // "content" — newline yoxdur

// ─── XML ─────────────────────────────────────────────
String xml = """
    <?xml version="1.0" encoding="UTF-8"?>
    <orders>
      <order id="1">
        <customerId>customer-1</customerId>
        <status>PENDING</status>
      </order>
    </orders>
    """;

// ─── SQL ──────────────────────────────────────────────
String sql = """
    SELECT o.id, o.customer_id, o.status, o.total_amount
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.status = 'PENDING'
      AND o.created_at > NOW() - INTERVAL '7 days'
    ORDER BY o.created_at DESC
    LIMIT 100
    """;

// ─── HTML ─────────────────────────────────────────────
String html = """
    <!DOCTYPE html>
    <html>
      <head>
        <title>Sifariş Təsdiqi</title>
      </head>
      <body>
        <h1>Sifarişiniz yaradıldı</h1>
        <p>Sifariş nömrəsi: #001</p>
      </body>
    </html>
    """;
```

---

## Indentation idarəsi

```java
// ─── Incidental whitespace — avtomatik silinir ────────
// JVM ən az girintilənmiş sətiri tapır, hamısından çıxarır

class IndentationExample {

    void method() {
        String text = """
            Birinci sətir
            İkinci sətir
              Üçüncü sətir (əlavə girinti)
            """;
        // Nəticə:
        // "Birinci sətir\nİkinci sətir\n  Üçüncü sətir (əlavə girinti)\n"
        // Ön boşluqlar çıxarıldı, relative indentasiya qaldı
    }
}

// ─── String.stripIndent() ─────────────────────────────
// Text block kimi davranış — proqramatik
String indented = "    Birinci\n    İkinci\n    Üçüncü\n";
String stripped = indented.stripIndent();
// Nəticə: "Birinci\nİkinci\nÜçüncü\n"

// ─── indent() — girinti əlavə et ─────────────────────
String noIndent = "Salam\nDünya";
String indentedBack = noIndent.indent(4);
// Nəticə: "    Salam\n    Dünya\n"
```

---

## Escape sequences

```java
// ─── \s — trailing whitespace saxla ──────────────────
String table = """
    Ad      \s
    Ali     \s
    Vəli    \s
    """;
// \s → boşluq (trailing whitespace silinməsin deyə)

// ─── \<line-terminator> — sətir birləşdirmə ───────────
String longLine = """
    Bu çox uzun bir \
    sətirdir, amma \
    bir sətir kimi göstərilir.
    """;
// Nəticə: "Bu çox uzun bir sətirdir, amma bir sətir kimi göstərilir.\n"

// ─── Adi escape-lər istifadə olunur ───────────────────
String withSpecials = """
    Tab:\t
    Unicode: \u00C7
    Backslash: \\
    """;

// ─── Triple quote necə daxil etmək ───────────────────
String withQuotes = """
    Bu "dırnaq" içindədir.
    Bu \"escaped\" dırnaq.
    Bu \\\"üçlü escaped\\\".
    """;

// Üç dırnaq daxil etmək üçün:
String tripleQuote = """
    \"\"\"Bu üç dırnaq\"\"\"
    """;
```

---

## String metodları ilə

```java
// ─── formatted() — text block ilə ────────────────────
String template = """
    Hörmətli %s,
    
    %s tarixli sifarişiniz (#%d) %s statusuna keçdi.
    
    Ümumi məbləğ: %.2f AZN
    
    Hörmətlə,
    Komanda
    """;

String email = template.formatted(
    "Əli Məmmədov",
    "2026-01-15",
    12345,
    "TƏSDİQLƏNDİ",
    149.99
);

// ─── String.format() ilə eyni ─────────────────────────
String sqlQuery = """
    SELECT *
    FROM orders
    WHERE customer_id = '%s'
      AND status IN ('%s', '%s')
      AND total_amount > %d
    """.formatted(
    customerId,
    "PENDING", "CONFIRMED",
    100
);

// ─── translateEscapes() ───────────────────────────────
// Raw string-dəki escape sequencesləri işlət
String raw = "Salam\\nDünya"; // Literal \n (iki simvol)
String translated = raw.translateEscapes();
// Nəticə: "Salam\nDünya" (real newline)

// ─── stripIndent() ───────────────────────────────────
String withIndent = "    Birinci\n    İkinci\n";
String stripped = withIndent.stripIndent();
// Nəticə: "Birinci\nİkinci\n"
```

---

## Praktik istifadə sahələri

```java
// ─── JSON test data ────────────────────────────────────
@Test
void shouldDeserializeOrderFromJson() throws Exception {
    String orderJson = """
        {
          "customerId": "customer-1",
          "items": [
            {
              "productId": "product-1",
              "quantity": 2,
              "unitPrice": 49.99
            }
          ],
          "deliveryAddress": "Bakı, Nizami küç. 10"
        }
        """;

    OrderRequest request = objectMapper.readValue(orderJson, OrderRequest.class);

    assertEquals("customer-1", request.customerId());
    assertEquals(1, request.items().size());
    assertEquals(2, request.items().get(0).quantity());
}

// ─── Email template ───────────────────────────────────
@Service
public class EmailTemplateService {

    public String buildOrderConfirmationEmail(Order order) {
        return """
            Hörmətli %s,
            
            Sifarişiniz #%d uğurla yaradıldı.
            
            📦 Sifariş məlumatları:
            ─────────────────────
            %s
            ─────────────────────
            
            💰 Ümumi məbləğ: %.2f AZN
            📅 Tarix: %s
            
            Suallarınız üçün: support@example.com
            
            Hörmətlə,
            Example Team
            """.formatted(
            order.getCustomerName(),
            order.getId(),
            formatItems(order.getItems()),
            order.getTotalAmount(),
            order.getCreatedAt().toString()
        );
    }

    private String formatItems(List<OrderItem> items) {
        return items.stream()
            .map(item -> "  • %s × %d = %.2f AZN".formatted(
                item.productName(),
                item.quantity(),
                item.totalPrice()
            ))
            .collect(Collectors.joining("\n"));
    }
}

// ─── SQL query builder ────────────────────────────────
@Repository
public class OrderQueryRepository {

    @PersistenceContext
    private EntityManager em;

    public List<OrderSummary> findOrderSummaries(OrderFilter filter) {
        String jpql = """
            SELECT new com.example.dto.OrderSummary(
                o.id, o.customerId, o.status, o.totalAmount, o.createdAt
            )
            FROM Order o
            WHERE (:customerId IS NULL OR o.customerId = :customerId)
              AND (:status IS NULL OR o.status = :status)
              AND o.createdAt BETWEEN :from AND :to
            ORDER BY o.createdAt DESC
            """;

        return em.createQuery(jpql, OrderSummary.class)
            .setParameter("customerId", filter.customerId())
            .setParameter("status", filter.status())
            .setParameter("from", filter.from())
            .setParameter("to", filter.to())
            .getResultList();
    }
}

// ─── WireMock / test stub ─────────────────────────────
@Test
void shouldHandleExternalApiResponse() {
    wireMock.stubFor(get("/api/products/1")
        .willReturn(okJson("""
            {
              "id": 1,
              "name": "Laptop",
              "price": 1299.99,
              "inStock": true,
              "categories": ["Electronics", "Computers"]
            }
            """)));

    Product product = productApiClient.getProduct(1L);

    assertEquals("Laptop", product.getName());
    assertTrue(product.isInStock());
}

// ─── Spring @Value ilə YANLIŞ ─────────────────────────
// Text block @Value-da işləmir (compile-time string literal lazımdır)
// Bunun əvəzinə @ConfigurationProperties istifadə edin

// ─── Log mesajları ────────────────────────────────────
log.error("""
    Sifariş yaradıla bilmədi.
    MüştəriId: {}
    Məbləğ: {}
    Xəta: {}
    """,
    customerId, amount, errorMessage);
```

---

## İntervyu Sualları

### 1. Text Block nədir?
**Cavab:** Java 15-də (JEP 378) final oldu. `"""` üçlü dırnaq ilə açılır (yeni sətirdən sonra), `"""` ilə bağlanır. Çoxsətirli string-ləri daha oxunaqlı formatda yazmağa imkan verir — əl ilə `\n`, `\"`, `+` concatenation lazım deyil. SQL, JSON, HTML, XML, email template-lər üçün idealdır.

### 2. Text Block-da incidental whitespace nədir?
**Cavab:** Bağlanış `"""`-nin mövqeyi ən az girintini müəyyən edir. JVM hamı sətirdən bu qədər ön boşluq çıxarır (incidental whitespace). Nəticədə kod girinti ilə yazılsa da, string-də yalnız məzmunun öz girintisi qalır. Bu, metod içərisindəki text block-un metodun girinti səviyyəsinə uyğun yazılmasına imkan verir.

### 3. `\s` və `\<newline>` escape-ləri nəyə görədir?
**Cavab:** `\s` — trailing whitespace saxlayır (text block-da trailing boşluqlar adətən silinir — `\s` son boşluğu qoruyanır). `\<newline>` — fiziki sətir sonunu görmürsən (string-də həmin yerdə newline olmur) — uzun sətirləri birləşdirmək üçün faydalıdır, vizual olaraq oxunaqlıq artır.

### 4. formatted() vs String.format() fərqi?
**Cavab:** `String.format("%s %d", a, b)` — statik metod. `"template".formatted(a, b)` — Java 15-dən instance metod, daha oxunaqlı zəncir çağırış. Text block ilə `"""...""".formatted(...)` birlikdə çox təmiz görünür. Funksional ekvivalentdir.

### 5. Text Block-un məhdudiyyətləri?
**Cavab:** Açılış `"""` ilə eyni sətirdə məzmun ola bilməz (yeni sətir mütləqdir). Runtime-da string literal — performans fərqi yoxdur. `@Value("...")` Spring annotasiyasında işləmir (compile-time annotation processor literal gözləyir). Multi-line regex-lərdə girintiyə diqqət — `Pattern.compile("""...""")` avtomatik girintini silindiyi üçün gözlənilməz nəticə verə bilər.

*Son yenilənmə: 2026-04-10*
