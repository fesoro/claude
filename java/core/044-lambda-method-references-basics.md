# 044 — Lambda və Method Reference Əsasları (Başlanğıc)
**Səviyyə:** Orta


## Mündəricat
1. [Lambda niyə meydana gəldi?](#niye)
2. [Anonymous class problemi](#anonymous)
3. [Lambda sintaksisi](#sintaksis)
4. [Target type və functional interface](#target-type)
5. [Dəyişən "capture" — effectively final](#capture)
6. [Hazır functional interface-lər](#hazir)
7. [Method reference — 4 növ](#method-ref)
8. [this::method vs ClassName::method](#this-vs-class)
9. [5 real nümunə](#numuneler)
10. [Ümumi Səhvlər](#umumi)
11. [İntervyu Sualları](#intervyu)

---

## 1. Lambda niyə meydana gəldi? {#niye}

Lambda — Java 8 (2014) ilə gəldi. Məqsəd: **funksiyanı parametr kimi ötürmək**. Əvvəllər Java-da "funksiya" sinifin daxilində metod kimi qalırdı, onu başqa metoda ötürmək çətin idi.

### Lambda-sız dünya — "Anonymous class" problemi

```java
// Button basılanda nə baş verməlidir? Bir metodu ötürmək istəyirik.
button.addActionListener(new ActionListener() {
    @Override
    public void actionPerformed(ActionEvent e) {
        System.out.println("Basıldı!");
    }
});
```

6 sətir — amma əslində bir sətirlik məntiq var: `sout("Basıldı!")`.

### Lambda ilə

```java
button.addActionListener(e -> System.out.println("Basıldı!"));
```

1 sətir. Bu, lambda-nın verdiyi ən böyük faydadır — **kod verbose-luğunu azaltmaq**.

### Real dünya analogiyası

Analogiya: "Yaxın gələn adama kömək et" tapşırığı verirsən. Əvvəllər bütün tapşırığı kağıza yazıb verirdin. İndi isə sadəcə "gələndə salam ver" deyirsən — qısa, dəqiq.

---

## 2. Anonymous class problemi {#anonymous}

Lambda mövcud olmadan siyahını sort etmək:

```java
List<String> adlar = new ArrayList<>(List.of("Vüsal", "Anar", "Elşən"));

// Anonymous class — çox uzun
Collections.sort(adlar, new Comparator<String>() {
    @Override
    public int compare(String a, String b) {
        return a.length() - b.length();
    }
});
```

Lambda ilə:

```java
Collections.sort(adlar, (a, b) -> a.length() - b.length());
```

### Verbose kodun problemləri

| Problem | İzah |
|---|---|
| Kod oxuma çətinliyi | 5-6 sətir boş syntax |
| `this` məna dəyişir | Anonymous class-da `this` → anonim obyekt |
| Boilerplate | `new Comparator<String>()` hər dəfə təkrarlanır |
| `final` tələbi | Çöl dəyişənlər `final` olmalı idi (Java 7-yə qədər) |

---

## 3. Lambda sintaksisi {#sintaksis}

### Ümumi forma

```
(parametrlər) -> {gövdə}
```

### Bütün formalar

```java
// 1. Heç parametr yoxdur
Runnable r = () -> System.out.println("İşə düşdü");

// 2. Bir parametr — mötərizə məcburi deyil
Consumer<String> c = ad -> System.out.println("Salam " + ad);

// 3. Bir parametr — mötərizəli də olar
Consumer<String> c2 = (ad) -> System.out.println("Salam " + ad);

// 4. Bir parametr — tiplə (az istifadə olunur)
Consumer<String> c3 = (String ad) -> System.out.println("Salam " + ad);

// 5. Çoxlu parametr — mötərizə məcburidir
BinaryOperator<Integer> toplama = (a, b) -> a + b;

// 6. Tək ifadə — return avtomatik
Function<Integer, Integer> kvadrat = x -> x * x;

// 7. Çoxlu ifadə — blok və return lazımdır
Function<Integer, String> analiz = x -> {
    if (x > 0) return "müsbət";
    if (x < 0) return "mənfi";
    return "sıfır";
};
```

### Single expression vs block body

```java
// Single expression — return yazılmır
Function<Integer, Integer> kvadrat1 = x -> x * x;

// Block body — return aşkar yazılmalıdır
Function<Integer, Integer> kvadrat2 = x -> {
    int netice = x * x;
    return netice;
};

// YANLIŞ — block body-də return yoxdur
Function<Integer, Integer> seh = x -> {
    x * x;  // compile error
};
```

---

## 4. Target type və functional interface {#target-type}

Lambda-nın **tipi yoxdur** — kompilyator onu hara yerləşdiyinə görə təyin edir. Bu "target type inference" adlanır.

### Functional interface nədir?

Yalnız **bir abstract metodu** olan interface. Lambda yalnız belə interface-ə verilə bilər.

```java
@FunctionalInterface
interface Salamlayici {
    void salamla(String ad);
    // yalnız bir abstract metod
}

Salamlayici s = ad -> System.out.println("Salam " + ad);
s.salamla("Vüsal"); // Salam Vüsal
```

### @FunctionalInterface annotasiyası

İstəyə bağlıdır, amma təhlükəsizlik üçün tövsiyə olunur. Əgər kimsə interface-ə ikinci abstract metod əlavə etsə, **compile error** verir.

```java
@FunctionalInterface
interface İkiParam {
    int hesabla(int a, int b);
    // int başqa();  // kompilyator xətası — bir abstract metod olmalıdır
}
```

### Target type misalı

```java
// Eyni lambda, fərqli target type-lar
Runnable r         = () -> System.out.println("tst");
Callable<String> c = () -> "nəticə"; // Runnable-dan fərqli interface

Comparator<Integer> cmp = (a, b) -> a - b;
BiFunction<Integer, Integer, Integer> bi = (a, b) -> a - b; // eyni lambda
```

Kompilyator dəyişənin tipinə baxaraq lambda-nın target type-ını müəyyən edir.

---

## 5. Dəyişən "capture" — effectively final {#capture}

Lambda daxilində çöldən gələn dəyişən istifadə edilə bilər, amma **effectively final** olmalıdır.

### Effectively final nədir?

Dəyişən `final` açar sözü olmadan yazılıb, amma dəyəri **bir dəfədən çox dəyişməyib**.

```java
int x = 10;
// x dəyişdirilmir — effectively final-dır
Runnable r = () -> System.out.println(x);
r.run(); // 10
```

```java
int y = 10;
y = 20; // dəyişdi
Runnable r = () -> System.out.println(y); // COMPILE ERROR
```

### Niyə bu məhdudiyyət?

Lambda çox zaman başqa thread-də işləyə bilər. Əgər dəyişən dəyişsə, lambda hansı dəyəri görəcəkdi? Java bu "race condition"-un qarşısını bu qayda ilə alır.

### Workaround — obyektin içindəki sahə

```java
int[] sayac = {0};  // massiv obyektinin sahəsi dəyişə bilər
Runnable r = () -> {
    sayac[0]++;  // OK — dəyişən özü (massiv referansı) dəyişmir
    System.out.println(sayac[0]);
};
r.run(); r.run(); r.run();
// 1, 2, 3
```

Və ya `AtomicInteger`:

```java
AtomicInteger sayac = new AtomicInteger(0);
Runnable r = () -> System.out.println(sayac.incrementAndGet());
```

---

## 6. Hazır functional interface-lər {#hazir}

Java `java.util.function` paketində **40+** hazır interface var. Ən vaciblər:

| Interface | Metodu | Nə edir? | Nümunə |
|---|---|---|---|
| `Runnable` | `void run()` | Heç nə qəbul etmir, heç nə qaytarmır | `() -> log("başladı")` |
| `Supplier<T>` | `T get()` | Heç nə qəbul etmir, bir dəyər qaytarır | `() -> new Date()` |
| `Consumer<T>` | `void accept(T)` | T qəbul edir, heç nə qaytarmır | `s -> System.out.println(s)` |
| `Function<T,R>` | `R apply(T)` | T qəbul edir, R qaytarır | `s -> s.length()` |
| `Predicate<T>` | `boolean test(T)` | T qəbul edir, boolean qaytarır | `n -> n > 0` |
| `BiFunction<T,U,R>` | `R apply(T,U)` | İki arg, bir nəticə | `(a,b) -> a+b` |
| `UnaryOperator<T>` | `T apply(T)` | T → T | `s -> s.trim()` |
| `BinaryOperator<T>` | `T apply(T,T)` | (T,T) → T | `(a,b) -> a+b` |

### Runnable — thread-də iş gör

```java
Runnable iş = () -> System.out.println("Thread işləyir");
new Thread(iş).start();
```

### Supplier — data yarat

```java
Supplier<List<Integer>> yeniSiyahi = () -> new ArrayList<>();
List<Integer> a = yeniSiyahi.get();
List<Integer> b = yeniSiyahi.get(); // hər dəfə yeni list
```

### Consumer — data istehlak et

```java
Consumer<String> yaz = mesaj -> System.out.println(">> " + mesaj);
yaz.accept("salam"); // >> salam
```

### Function — data transforma et

```java
Function<String, Integer> uzunluq = s -> s.length();
System.out.println(uzunluq.apply("Azərbaycan")); // 10
```

### Predicate — yoxla

```java
Predicate<Integer> cutdur = n -> n % 2 == 0;
System.out.println(cutdur.test(4)); // true
System.out.println(cutdur.test(5)); // false
```

### Comparator — müqayisə et

```java
Comparator<String> cmp = (a, b) -> a.length() - b.length();
List<String> list = new ArrayList<>(List.of("uzun", "qısa", "orta"));
list.sort(cmp);
```

---

## 7. Method reference — 4 növ {#method-ref}

Method reference — **mövcud metoda qısayoldur**. Lambda əvəzinə `::` istifadə edilir.

```java
// Lambda
Consumer<String> c1 = s -> System.out.println(s);

// Method reference — daha qısa
Consumer<String> c2 = System.out::println;
```

### 4 növ method reference

| Növ | Sintaksis | Lambda ekvivalenti | Nümunə |
|---|---|---|---|
| 1. Static metod | `ClassName::staticMethod` | `(x) -> ClassName.staticMethod(x)` | `Integer::parseInt` |
| 2. Konkret obyekt | `instance::method` | `(x) -> instance.method(x)` | `System.out::println` |
| 3. Tipin instance metodu | `ClassName::instanceMethod` | `(obj, args) -> obj.instanceMethod(args)` | `String::length` |
| 4. Constructor | `ClassName::new` | `(x) -> new ClassName(x)` | `ArrayList::new` |

### Növ 1 — Static metoda reference

```java
// Lambda:
Function<String, Integer> f1 = s -> Integer.parseInt(s);
// Method reference:
Function<String, Integer> f2 = Integer::parseInt;

System.out.println(f2.apply("42")); // 42
```

### Növ 2 — Konkret obyektin metoduna reference

```java
List<String> list = List.of("a", "b", "c");
Consumer<String> printer = System.out::println;
list.forEach(printer);
// Lambda ekvivalenti: list.forEach(s -> System.out.println(s));
```

### Növ 3 — Tipin instance metoduna reference

Ən maraqlı növdür. Metod çağırıldıqda, **birinci parametr** həmin metodun sahibi olur.

```java
// Lambda:
Function<String, Integer> f1 = s -> s.length();
// Method reference — length() metodunu sahibi "s" olur:
Function<String, Integer> f2 = String::length;

System.out.println(f2.apply("salam")); // 5
```

```java
// Comparator
Comparator<String> c1 = (a, b) -> a.compareTo(b);
Comparator<String> c2 = String::compareTo;  // "a" compareTo "b" edir
```

### Növ 4 — Constructor reference

```java
Supplier<ArrayList<Integer>> yeni1 = () -> new ArrayList<>();
Supplier<ArrayList<Integer>> yeni2 = ArrayList::new;

ArrayList<Integer> siyahi = yeni2.get();
```

```java
// Parametrli constructor
Function<String, StringBuilder> sbYarat = StringBuilder::new;
StringBuilder sb = sbYarat.apply("ilkin mətn");
```

---

## 8. this::method vs ClassName::method {#this-vs-class}

Bu fərq başlanğıcda qarışdırıcıdır.

### this::method — cari obyektin metodunu işarə edir

```java
class Servis {
    private final String prefix = "LOG: ";

    void log(String msg) {
        System.out.println(prefix + msg);
    }

    void işlət(List<String> siyahi) {
        // this::log — cari Servis obyektinin log metodu
        siyahi.forEach(this::log);
    }
}
```

### ClassName::method — ümumi sinif metodunu işarə edir

```java
class StringUtil {
    // Burada metod siyahının hər bir stringi üzərində çağırılacaq
    siyahi.forEach(System.out::println); // System.out OBYEKTI — konkret instance
}

List<String> adlar = List.of("Anar", "Vüsal");
adlar.stream().map(String::toUpperCase).forEach(System.out::println);
// ANAR
// VÜSAL
// Burada String::toUpperCase — "hər hansı bir String obyekti"ni nəzərdə tutur
// stream-dəki hər stringin toUpperCase() metodunu çağırır
```

### Əməli qayda

```java
// "Bu obyektin metodu" — this::
forEach(this::save);

// "Hər element üçün elementin öz metodu" — ClassName::
map(String::toUpperCase)

// "Static metod" — ClassName::
map(Integer::parseInt)
```

---

## 9. 5 real nümunə {#numuneler}

### Nümunə 1 — List sort

```java
List<String> adlar = new ArrayList<>(List.of("Anar", "Kamala", "Vüsal", "Elşən", "Nigar"));

// Lambda ilə — əlifba sırası
adlar.sort((a, b) -> a.compareTo(b));

// Method reference ilə (daha qısa)
adlar.sort(String::compareTo);

// Uzunluğa görə
adlar.sort((a, b) -> a.length() - b.length());

// Comparator.comparing ilə ən təmiz variant
adlar.sort(Comparator.comparingInt(String::length));

System.out.println(adlar);
// [Anar, Vüsal, Nigar, Elşən, Kamala]
```

### Nümunə 2 — Filter (Stream)

```java
List<Integer> ədədlər = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

// Cüt ədədləri ayır
List<Integer> cütlər = ədədlər.stream()
    .filter(n -> n % 2 == 0)
    .toList();

System.out.println(cütlər); // [2, 4, 6, 8, 10]

// 5-dən böyüklər
List<Integer> böyüklər = ədədlər.stream()
    .filter(n -> n > 5)
    .toList();
```

### Nümunə 3 — Button event handler

```java
// Swing/JavaFX nümunəsi
JButton button = new JButton("Bas məni");
button.addActionListener(e -> {
    System.out.println("Klik!");
    counter++;
});
```

### Nümunə 4 — Thread başlat

```java
// Lambda ilə — bir sətirdə
new Thread(() -> {
    System.out.println("Arxa fonda işləyir");
    try { Thread.sleep(1000); } catch (Exception e) {}
    System.out.println("Bitdi");
}).start();
```

### Nümunə 5 — Stream map (çevrilmə)

```java
List<String> adlar = List.of("anar", "vüsal", "elşən");

// Böyük hərfə çevir
List<String> böyük = adlar.stream()
    .map(String::toUpperCase)
    .toList();

System.out.println(böyük); // [ANAR, VÜSAL, ELŞƏN]

// Uzunluqlar
List<Integer> uzunluqlar = adlar.stream()
    .map(String::length)
    .toList();

System.out.println(uzunluqlar); // [4, 5, 5]
```

---

## 10. Ümumi Səhvlər {#umumi}

### Səhv 1 — Block body-də `return` unudulması

```java
// YANLIŞ
Function<Integer, Integer> f = x -> { x * x; };

// DOĞRU
Function<Integer, Integer> f = x -> { return x * x; };

// Daha yaxşı
Function<Integer, Integer> f = x -> x * x;
```

### Səhv 2 — Dəyişəni dəyişməyə çalışmaq

```java
int sayac = 0;
Runnable r = () -> { sayac++; }; // compile error
```

Həll: `AtomicInteger` və ya massiv (`int[] sayac = {0}`).

### Səhv 3 — `this` mənasını qarışdırmaq

Anonymous class-da `this` → anonim obyekt. Lambda-da `this` → **əhatə edən sinfin obyekti**.

```java
class MyClass {
    String name = "Outer";

    void test() {
        Runnable anon = new Runnable() {
            @Override
            public void run() {
                // this.name — "Outer" sahəsi? Xeyr!
                // this burada anonymous class-ın özüdür (name sahəsi yoxdur)
            }
        };

        Runnable lam = () -> {
            System.out.println(this.name); // "Outer" — əhatə edən sinif
        };
    }
}
```

### Səhv 4 — Lambda tipi olmadan `var`

```java
// YANLIŞ — kompilyator target type-ı bilməz
var f = (x) -> x * x; // compile error

// DOĞRU
Function<Integer, Integer> f = x -> x * x;
// və ya:
var f = (Function<Integer, Integer>) (x -> x * x);
```

### Səhv 5 — `null` qaytaran `Supplier` və `Optional`

```java
// Supplier null qaytara bilər, sonra NPE olur
Supplier<String> s = () -> null;
s.get().toUpperCase(); // NullPointerException

// Daha təhlükəsiz
Optional<String> opt = Optional.ofNullable(s.get());
opt.map(String::toUpperCase).ifPresent(System.out::println);
```

### Səhv 6 — Lambda içində mutable state

```java
// Lambda içində çöldəki siyahını dəyişdirmək — thread üçün təhlükəli
List<Integer> list = new ArrayList<>();
IntStream.range(0, 1000).parallel().forEach(i -> list.add(i)); // race!

// Həll: Collectors.toList()
List<Integer> safe = IntStream.range(0, 1000).parallel().boxed().toList();
```

### Səhv 7 — Method reference-in birinci parametri qarışdırmaq

```java
List<String> list = List.of("a", "b");
// String::length — (s) -> s.length() deməkdir
list.stream().map(String::length).forEach(System.out::println);
// 1, 1

// YANLIŞ — Consumer<String>-ə Function uyğun gəlmir:
// list.forEach(String::length); // compile error
```

---

## İntervyu Sualları {#intervyu}

**S1: Lambda nədir və niyə Java 8-də gəldi?**
> Lambda — anonim funksiyadır. Məqsəd: funksional proqramlaşdırma dəstəyi vermək, anonymous class boilerplate-ini azaltmaq, Stream API-yə imkan yaratmaq. Sintaksis: `(parametrlər) -> ifadə`.

**S2: Functional interface nədir?**
> Yalnız **bir abstract metodu** olan interface-dir. Default və static metodlar ola bilər, amma abstract metod yalnız bir olmalıdır. `@FunctionalInterface` annotasiyası kompilyatora bunu yoxlatdırır. Lambda yalnız belə interface-lərə təyin edilə bilər.

**S3: Effectively final nədir?**
> `final` yazılmasa da **bir dəfədən çox dəyişdirilməyən** dəyişən. Lambda daxilində istifadə üçün xarici dəyişənlər effectively final olmalıdır — əks halda compile error verir. Səbəb: lambda başqa thread-də işləyə bilər və state dəyişməsi race condition yaradar.

**S4: Method reference nədir və növləri hansılardır?**
> Mövcud metoda qısayol. 4 növü var: (1) static metod `ClassName::method`, (2) konkret obyektin metodu `instance::method`, (3) tipin instance metodu `ClassName::method` (birinci parametr sahibi olur), (4) constructor `ClassName::new`.

**S5: `System.out::println` nə tipli method reference-dir?**
> Növ 2 — konkret obyektin metodu. `System.out` — konkret `PrintStream` instance-ıdır. `::println` isə onun `println(Object)` metoduna referans deməkdir. Lambda ekvivalenti: `x -> System.out.println(x)`.

**S6: `String::length` necə işləyir?**
> Növ 3 — tipin instance metodu. `Function<String, Integer>` kimi istifadə edilir. `s.length()` çağırışına ekvivalentdir — birinci parametr "sahib" rolunu oynayır. `Function<String, Integer> f = String::length; f.apply("salam")` → 5.

**S7: Lambda-nın `this` mənası anonymous class-dan necə fərqlənir?**
> Anonymous class-da `this` → yaradılan anonim obyekt. Lambda-da `this` → **əhatə edən sinfin instance-ı** (enclosing class). Lambda-nın öz `this`-i yoxdur — buna görə lambda daxilində outer class field-lərini `this.field` ilə əlçatan etmək olur.

**S8: Lambda və anonymous class bytecode səviyyəsində eynidirmi?**
> Xeyr. Anonymous class hər dəfə yeni `.class` fayl yaradır. Lambda isə `invokedynamic` instruction və `LambdaMetafactory` istifadə edir — runtime-da class synthesize edilir. Bu daha yaddaş-effektivdir və yükləmə sürətini artırır.

**S9: Stream-də lambda niyə vacibdir?**
> Stream API deklarativ filter/map/reduce operasiyaları üçündür. Hər operasiya bir funksiya qəbul edir (`filter(Predicate)`, `map(Function)`). Lambda olmasa hər operasiyada anonymous class yazmaq lazım gələrdi — oxunmaz olardı.

**S10: Lambda içində exception handling necə edilir?**
> Unchecked exception — birbaşa atmaq olur. Checked exception üçün isə ya lambda daxilində try-catch etmək, ya da wrapper interface yaratmaq lazımdır. Məsələn, `Function` `IOException` atmır — `s -> Files.readString(s)` compile error. Həll: lambda içində `try/catch` və `RuntimeException`-a çevirmək.
