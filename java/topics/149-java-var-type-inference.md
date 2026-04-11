# Java `var` — Type Inference — Geniş İzah

## Mündəricat
1. [var nədir?](#var-nədir)
2. [var-ın istifadə qaydaları](#var-ın-istifadə-qaydaları)
3. [Doğru istifadə nümunələri](#doğru-istifadə-nümunələri)
4. [Yanlış istifadə nümunələri](#yanlış-istifadə-nümunələri)
5. [Lambda ilə var (Java 11)](#lambda-ilə-var-java-11)
6. [İntervyu Sualları](#intervyu-sualları)

---

## var nədir?

**`var`** (Java 10, JEP 286) — lokal dəyişkən tip çıxarımı. Compiler tipi sağ tərəfdən çıxarır.

```java
// ƏVVƏL:
ArrayList<String> list = new ArrayList<String>();
Map<String, List<OrderItem>> orderMap = new HashMap<String, List<OrderItem>>();

// SONRA (var ilə):
var list = new ArrayList<String>();
var orderMap = new HashMap<String, List<OrderItem>>();

// var — açar söz deyil, Java identifikatoru
// var adında dəyişkən, sinif, metod adı ola bilər (amma tövsiyə edilmir)
// var adında tip yaratmaq olmaz

// Compile-time tipi — runtime deyil
// var x = 42; → x-in tipi int (compile-time-da müəyyən edilir)
```

---

## var-ın istifadə qaydaları

```java
// ─── İstifadə edilə bilən yerlər ─────────────────────

// 1. Lokal dəyişkən — initializer ilə
var name = "Ali";             // String
var age = 25;                 // int
var pi = 3.14;                // double
var list = new ArrayList<>(); // ArrayList (raw type diqqət!)

// 2. For-each dövrü
var orders = List.of("1", "2", "3");
for (var order : orders) {
    System.out.println(order.toUpperCase()); // order → String
}

// 3. Traditional for dövrü
for (var i = 0; i < 10; i++) {
    System.out.println(i);
}

// 4. Try-with-resources
try (var connection = dataSource.getConnection()) {
    // connection → Connection
}

// ─── İstifadə edilə bilməyən yerlər ─────────────────

// COMPILE ERROR — sinif sahəsi
// var field = "hello"; // ❌

// COMPILE ERROR — metod parametri
// void method(var param) { } // ❌

// COMPILE ERROR — metod qaytarma tipi
// var getOrder() { return new Order(); } // ❌

// COMPILE ERROR — initializer olmadan
// var x; // ❌

// COMPILE ERROR — null ilə
// var x = null; // ❌ — null-dan tip çıxarıla bilməz

// COMPILE ERROR — lambda
// var fn = () -> "hello"; // ❌

// COMPILE ERROR — array initializer
// var arr = {1, 2, 3}; // ❌

// DOĞRU — array ilə type açıq göstərilmişsə
var arr = new int[]{1, 2, 3}; // ✅
```

---

## Doğru istifadə nümunələri

```java
// ─── Uzun generic tip-lər ─────────────────────────────
class GoodVarUsage {

    void complexGenericTypes() {
        // BEFORE — çox verbose
        Map<String, List<Map<String, OrderItem>>> complexMap =
            new HashMap<String, List<Map<String, OrderItem>>>();

        // AFTER — var ilə
        var complexMap = new HashMap<String, List<Map<String, OrderItem>>>();
        // Sağ tərəf tipini aydın göstərir — var oxunaqlığı artırır
    }

    void iteratorExample() {
        List<OrderItem> items = getItems();

        // BEFORE
        Iterator<OrderItem> iterator = items.iterator();

        // AFTER
        var iterator = items.iterator(); // ✅ tip aydındır
        while (iterator.hasNext()) {
            var item = iterator.next(); // OrderItem
            processItem(item);
        }
    }

    void streamPipeline() {
        // Uzun stream pipeline-larda ara nəticə saxlamaq
        var orders = orderRepository.findAll();  // List<Order>

        var pendingOrders = orders.stream()
            .filter(o -> o.getStatus() == OrderStatus.PENDING)
            .collect(Collectors.toList());  // List<Order>

        var totalAmount = pendingOrders.stream()
            .map(Order::getTotalAmount)
            .reduce(BigDecimal.ZERO, BigDecimal::add);  // BigDecimal
    }

    void tryWithResources() throws SQLException {
        // Try-with-resources — var əla işləyir
        try (var conn = dataSource.getConnection();
             var stmt = conn.prepareStatement("SELECT * FROM orders WHERE id = ?")) {
            stmt.setLong(1, 1L);
            try (var rs = stmt.executeQuery()) {
                while (rs.next()) {
                    var id = rs.getLong("id");
                    var status = rs.getString("status");
                    System.out.println(id + ": " + status);
                }
            }
        }
    }

    void localTemporaryVariable() {
        // Müvəqqəti dəyişkən — tip aydındır kontekstdən
        var customerName = order.getCustomer().getFullName();
        var lastFourDigits = card.getNumber().substring(card.getNumber().length() - 4);

        System.out.println(customerName + " (" + lastFourDigits + ")");
    }

    void patternMatching() {
        // instanceof pattern ilə birlikdə
        Object obj = getObject();
        if (obj instanceof OrderItem item) {
            var price = item.getUnitPrice();  // BigDecimal — aydındır
            var qty = item.getQuantity();      // int
            System.out.println(price.multiply(BigDecimal.valueOf(qty)));
        }
    }
}
```

---

## Yanlış istifadə nümunələri

```java
// ─── Oxunaqlığı azaldır ───────────────────────────────
class BadVarUsage {

    void poorReadability() {
        // YANLIŞ — tip aydın deyil
        var x = process(); // process() nə qaytarır? Bilinmir!
        var result = calculate(x); // Yenə bilinmir

        // DOĞRU
        Order order = process(); // ✅ aydındır
        BigDecimal total = calculate(order);
    }

    void primitiveUsage() {
        // Primitives üçün var nüans yaradır

        // YANLIŞ — oxuyanın şübhəsi var
        var number = 42;     // int? Integer? long?
        var flag = true;     // boolean? Boolean?
        var pi = 3.14;       // double? float?

        // DOĞRU — primitivlər üçün explicit tip daha aydın
        int number2 = 42;
        boolean flag2 = true;
        double pi2 = 3.14;

        // Literal suffix istifadəsi ilə var da məqsədəuyğundur:
        var longNum = 42L;    // açıq long
        var floatNum = 3.14f; // açıq float
    }

    void rawTypeAntiPattern() {
        // YANLIŞ — raw type problem
        var list = new ArrayList<>(); // ArrayList (raw!) — nəyin listsidir?

        list.add("string");
        list.add(42); // Compile xəta yoxdur — raw type!

        // DOĞRU
        var list2 = new ArrayList<String>();   // ✅ aydındır
        var list3 = new ArrayList<Order>();    // ✅
    }

    void methodReturnAmbiguity() {
        // Metodun qaytardığı tip aydın deyilsə var YANLIŞ
        var data = service.getData(); // getData() nə qaytarır?

        // getOrders() adı aydındırsa var məqbuldur:
        var orders = orderService.getOrders(); // List<Order> — gözlənilir
    }

    void diamondProblem() {
        // var + diamond problemi
        // YANLIŞ — raw type çıxarılır
        var pair = new Pair<>(1, "hello");
        // pair → Pair<Integer, String> ✅ — bu əslində işləyir

        // Amma bu problem yaradır:
        // var list = Collections.emptyList();
        // list → List<Object> — gözlənilən deyil!

        // DOĞRU:
        List<Order> list = Collections.emptyList(); // ✅
    }
}
```

---

## Lambda ilə var (Java 11)

```java
// ─── Lambda parametrlərində var ───────────────────────
// Java 11 — JEP 323

// ƏVVƏL (Java 10):
List<String> names = List.of("Ali", "Vəli", "Rəhim");

names.stream()
    .filter((String name) -> name.startsWith("A")) // explicit tip
    .forEach(System.out::println);

// Java 11-dən var lambda-da:
names.stream()
    .filter((var name) -> name.startsWith("A")) // var ilə
    .forEach(System.out::println);

// Bəs niyə var lambda-da? — Annotasiyalar!
// Adi lambda parametrinə annotasiya qoymaq olmur
// var ilə annotasiya mümkündür:

names.stream()
    .filter((@NonNull var name) -> name.startsWith("A")) // ✅
    .forEach(System.out::println);

// Qaydalar:
// 1. Ya bütün parametrlər var, ya heç biri
// (var name, String age) → ❌ COMPILE ERROR
// (var name, var age) → ✅

// 2. Tip çıxarım inferreddir — lambda kontekstindən
List<String> filtered = names.stream()
    .filter((var n) -> n.length() > 3) // n → String
    .collect(Collectors.toList());
```

---

## İntervyu Sualları

### 1. `var` nədir?
**Cavab:** Java 10-da (JEP 286) gəldi. Lokal dəyişkən tip çıxarımı — compiler tipi initializer ifadəsindən çıxarır. Açar söz (keyword) deyil, reserved type name-dir — var adında metod/sinif ola bilər (amma tövsiyə edilmir). Yalnız lokal dəyişkənlər, for-each, try-with-resources-da istifadə olunur. Sinif sahəsi, metod parametri, qaytarma tipi üçün deyil.

### 2. `var` statik yoxsa dinamik tipləşdirmədir?
**Cavab:** Statik tipləşdirmə — compile-time-da tip müəyyən edilir. `var x = 42;` → x-in tipi compile-time-da `int`-dir. Runtime-da `Object`-ə dönmür, tip silinmir. JavaScript-dəki `var`-dan fərqlidir — Java-da hər dəyişkənin tipi compile-time-da sabitdir. `var` sadəcə tipi açıq yazmağı aradan qaldırır, dinamikləşdirmir.

### 3. `var` nə vaxt istifadə etmək lazımdır, nə vaxt yox?
**Cavab:** **İstifadə et**: uzun generic tip-lər (`Map<String, List<Order>>`), try-with-resources, for-each, kontekstdən tip aydın olduqda (`var orders = orderRepository.findAll()`). **İstifadə etmə**: tip aydın deyilsə (factory metod qaytarması), primitivlər üçün explicit daha aydıqdır, API səthi dəyişkənlər (public field), raw type çıxarımı riski olduqda.

### 4. `var` ilə null niyə işləmir?
**Cavab:** `var x = null;` — compiler-ın null-dan heç bir tip çıxarması mümkün deyil. Null hər tipin dəyəri ola bilər — tip məlum olmadan `x`-ə tip verilə bilməz. Buna görə `var` həmişə initializer tələb edir və initializer-in tipi məlum olmalıdır. `var x = (String) null;` ✅ — cast ilə tip müəyyəndir.

### 5. Lambda-da `var`-ın faydası nədir?
**Cavab:** Java 11 (JEP 323). Annotation qoymaq imkanı — adi lambda parametrlərinə tip annotasiyası (`@NonNull`, `@NotNull`) qoymaq olmur: `(name) -> ...`. `var` ilə isə mümkündür: `(@NonNull var name) -> ...`. Funksional fayda azdır — çünki lambda-da tip inferreddır. Amma annotasiya lazım olduqda yeganə yoldur.

*Son yenilənmə: 2026-04-10*
