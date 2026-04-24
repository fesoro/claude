# 046 — Optional
**Səviyyə:** Orta


## Mündəricat
1. [Optional nədir?](#optional-nədir)
2. [Optional yaratmaq](#optional-yaratmaq)
3. [Dəyər almaq — get, orElse, orElseGet, orElseThrow](#dəyər-almaq)
4. [map(), flatMap(), filter()](#transformasiya)
5. [ifPresent() və ifPresentOrElse()](#ifpresent)
6. [isPresent() vs isEmpty()](#ispresent-isempty)
7. [Anti-patternlər](#anti-patternlər)
8. [Optional nə vaxt istifadə edilir?](#nə-vaxt-istifadə)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Optional nədir?

`Optional<T>` — dəyərin mövcud ola biləcəyini və ya olmaya biləcəyini bildirən konteyner sinifdir. Java 8-də `NullPointerException`-ı azaltmaq üçün təqdim edilib.

```java
// Köhnə yanaşma — null yoxlaması
public String tərsinə(String s) {
    if (s == null) {
        return null; // null qaytarırıq
    }
    return new StringBuilder(s).reverse().toString();
}

// İstifadəçi null yoxlamasını unutsa:
String nəticə = tərsinə(null);
System.out.println(nəticə.length()); // NullPointerException!

// Optional ilə — null qaytarmaq əvəzinə Optional
public Optional<String> tərsinəOptional(String s) {
    if (s == null) {
        return Optional.empty();
    }
    return Optional.of(new StringBuilder(s).reverse().toString());
}

// İstifadə — compiler null yoxlamasına məcbur edir
Optional<String> nəticə2 = tərsinəOptional(null);
nəticə2.ifPresent(r -> System.out.println(r.length())); // NPE yoxdur
```

### Optional-ın fəlsəfəsi

```
null qaytarmaq:                Optional qaytarmaq:
→ İstifadəçi unutsa NPE       → Explicit olaraq boşluğu göstərir
→ Sənədləşdirmə yoxdur        → Metod imzası "boş ola bilər" deyir
→ if-null yoxlaması zorla     → API istifadəçisi məcburdur bunu idarə etməyə
```

---

## Optional Yaratmaq

```java
import java.util.Optional;

// 1. Optional.of() — null olmayan dəyər üçün
Optional<String> dolu = Optional.of("Salam");
System.out.println(dolu); // Optional[Salam]

// Optional.of(null) — NullPointerException!
// Optional<String> xəta = Optional.of(null); // NPE!

// 2. Optional.ofNullable() — null ola bilən dəyər üçün
Optional<String> belkə = Optional.ofNullable("Salam");
Optional<String> boş = Optional.ofNullable(null); // Optional.empty()

System.out.println(belkə); // Optional[Salam]
System.out.println(boş);   // Optional.empty

// 3. Optional.empty() — boş Optional
Optional<String> heçNə = Optional.empty();
System.out.println(heçNə.isPresent()); // false

// Praktiki nümunə
public Optional<String> tapAdı(int id) {
    // DB-dən gəlir — null ola bilər
    String ad = dbdənAl(id); // null ola bilər
    return Optional.ofNullable(ad); // null → empty, dəyər → Optional[dəyər]
}
```

---

## Dəyər almaq

### get() — YANLIŞ istifadə

```java
Optional<String> opt = Optional.of("Salam");
Optional<String> boş = Optional.empty();

// get() — dəyər varsa qaytarır, yoxsa NoSuchElementException!
String dəyər = opt.get(); // OK — "Salam"

// YANLIŞ — yoxlamadan get()
String xəta = boş.get(); // NoSuchElementException: No value present!
```

### orElse() — default dəyər

```java
Optional<String> opt = Optional.empty();

// orElse() — boş olduqda default dəyər qaytarır
String nəticə = opt.orElse("Default");
System.out.println(nəticə); // Default

Optional<String> dolu = Optional.of("Real dəyər");
System.out.println(dolu.orElse("Default")); // Real dəyər

// Diqqət: orElse HƏMİŞƏ default dəyəri hesablayır!
String s = opt.orElse(pahalıHesabla()); // pahalıHesabla() həmişə çağırılır!
```

### orElseGet() — lazy default

```java
// orElseGet() — Supplier ilə, yalnız lazım olduqda çağırılır
Optional<String> opt = Optional.empty();

String nəticə = opt.orElseGet(() -> {
    System.out.println("Hesablanır...");
    return "Hesablanmış dəyər";
});
// "Hesablanır..." çap edilir
System.out.println(nəticə); // Hesablanmış dəyər

Optional<String> dolu = Optional.of("Mövcud");
String effektiv = dolu.orElseGet(() -> {
    System.out.println("Bu çap edilməyəcək!"); // Çağırılmır!
    return "Lazımsız dəyər";
});
System.out.println(effektiv); // Mövcud
```

### orElse vs orElseGet fərqi

```java
// YANLIŞ — baha hesablama həmişə çağırılır
public Optional<İstifadəçi> tapİstifadəçi(int id) {
    return Optional.ofNullable(db.tapId(id));
}

// Baha hesablama — Optional dolulu olsa da çağırılır!
İstifadəçi ist = tapİstifadəçi(1)
    .orElse(bahalıHesaplamaIlıDefault()); // HƏMİŞƏ çağırılır!

// DOĞRU — yalnız boş olduqda çağırılır
İstifadəçi ist2 = tapİstifadəçi(1)
    .orElseGet(() -> bahalıHesaplamaIlıDefault()); // Yalnız boş olduqda
```

### orElseThrow()

```java
Optional<String> opt = Optional.empty();

// Java 10 — boş olduqda xəta at
String dəyər = opt.orElseThrow(); // NoSuchElementException

// Xüsusi xəta
String dəyər2 = opt.orElseThrow(() ->
    new IllegalStateException("Dəyər tapılmadı!"));

// Repository metodları üçün tipik istifadə
public İstifadəçi tapYaxudXəta(int id) {
    return db.tapId(id) // Optional<İstifadəçi>
             .orElseThrow(() ->
                 new EntityNotFoundException("İstifadəçi tapılmadı: " + id));
}
```

---

## Transformasiya

### map()

```java
// Optional içindəki dəyəri çevir
Optional<String> söz = Optional.of("salam");

Optional<Integer> uzunluq = söz.map(String::length);
System.out.println(uzunluq); // Optional[5]

Optional<String> böyük = söz.map(String::toUpperCase);
System.out.println(böyük); // Optional[SALAM]

// Boş Optional-da map — boş qalır
Optional<String> boş = Optional.empty();
Optional<Integer> boşUzunluq = boş.map(String::length);
System.out.println(boşUzunluq); // Optional.empty
// NPE yoxdur!
```

### flatMap()

```java
// map() Optional qaytaran funksiya ilə istifadə edilsə:
// Optional<Optional<T>> yaranır — flatMap bunu düzləndirir

record İstifadəçi(String ad, Optional<String> email) {}
record Şirkət(String ad, Optional<İstifadəçi> direktor) {}

Şirkət şirkət = new Şirkət("TechCo",
    Optional.of(new İstifadəçi("Əli",
        Optional.of("ali@techco.com"))));

// map() ilə — Optional<Optional<String>> alınar
Optional<Optional<String>> xəta = şirkət.direktor()
    .map(d -> d.email()); // İki qatlı Optional!

// flatMap() ilə — Optional<String> alınır
Optional<String> email = şirkət.direktor()
    .flatMap(İstifadəçi::email); // Düzləndirir
System.out.println(email); // Optional[ali@techco.com]

// Zəncir
Şirkət boşŞirkət = new Şirkət("EmptyCo", Optional.empty());
Optional<String> boşEmail = boşŞirkət.direktor()
    .flatMap(İstifadəçi::email);
System.out.println(boşEmail); // Optional.empty — NPE yoxdur!
```

### filter()

```java
// Optional içindəki dəyəri şərtə görə filtrə et
Optional<String> söz = Optional.of("salam");

// Şərt doğrusa — Optional saxlanır
Optional<String> uzun = söz.filter(s -> s.length() > 3);
System.out.println(uzun); // Optional[salam]

// Şərt yanlışsa — empty olur
Optional<String> çoxUzun = söz.filter(s -> s.length() > 10);
System.out.println(çoxUzun); // Optional.empty

// Zəncir
Optional<String> yaş = Optional.of("  25  ");
Optional<Integer> tamYaş = yaş
    .map(String::trim)
    .filter(s -> s.matches("\\d+"))    // Yalnız rəqəm
    .map(Integer::parseInt)
    .filter(n -> n >= 18);             // 18+

System.out.println(tamYaş); // Optional[25]
```

---

## ifPresent() və ifPresentOrElse()

```java
Optional<String> opt = Optional.of("Salam");

// ifPresent() — dəyər varsa Consumer-i çağırır
opt.ifPresent(System.out::println); // Salam

Optional<String> boş = Optional.empty();
boş.ifPresent(System.out::println); // Heç nə çap edilmir

// ifPresentOrElse() — Java 9+ (if-else kimi)
opt.ifPresentOrElse(
    s -> System.out.println("Tapıldı: " + s),  // Dəyər varsa
    () -> System.out.println("Tapılmadı")        // Boşdursa
);
// Tapıldı: Salam

boş.ifPresentOrElse(
    s -> System.out.println("Tapıldı: " + s),
    () -> System.out.println("Tapılmadı")
);
// Tapılmadı
```

### or() — Java 9+

```java
// or() — boş olduqda alternatif Optional qaytarır
Optional<String> əsas = Optional.empty();
Optional<String> ehtiyat = Optional.of("Ehtiyat dəyər");

Optional<String> nəticə = əsas.or(() -> ehtiyat);
System.out.println(nəticə); // Optional[Ehtiyat dəyər]

// orElse ilə fərq — or() Optional qaytarır, orElse() dəyər
// Zəncirə davam etmək üçün or() istifadə edilir
```

### stream() — Java 9+

```java
// Optional-ı 0 və ya 1 elementli stream-ə çevir
Optional<String> opt = Optional.of("Salam");
Optional<String> boş = Optional.empty();

// flatMap ilə çox faydalı
List<Optional<String>> optionals = List.of(
    Optional.of("alma"),
    Optional.empty(),
    Optional.of("armud"),
    Optional.empty(),
    Optional.of("gilas")
);

// flatMap(Optional::stream) — boşları çıxar
List<String> dəyərlər = optionals.stream()
    .flatMap(Optional::stream) // Java 9+
    .collect(Collectors.toList());
System.out.println(dəyərlər); // [alma, armud, gilas]
```

---

## isPresent() vs isEmpty()

```java
Optional<String> opt = Optional.of("Salam");
Optional<String> boş = Optional.empty();

// isPresent() — dəyər varsa true
System.out.println(opt.isPresent());  // true
System.out.println(boş.isPresent()); // false

// isEmpty() — Java 11+ — dəyər yoxsa true (isPresent-in tərsi)
System.out.println(opt.isEmpty());  // false
System.out.println(boş.isEmpty()); // true

// isEmpty() daha oxunaqlı
if (opt.isEmpty()) {
    System.out.println("Boşdur");
}
// Yaxşı: if (!opt.isPresent()) → if (opt.isEmpty())
```

---

## Anti-patternlər

### Anti-pattern 1: Optional.get() yoxlamadan

```java
// YANLIŞ — isPresent yoxlaması olmadan get()
Optional<String> opt = getFromSomewhere(); // Boş ola bilər

// YANLIŞ — ifPresent yoxlamadan
String dəyər = opt.get(); // NoSuchElementException riski!

// DOĞRU
String dəyər2 = opt.orElse("Default");
String dəyər3 = opt.orElseThrow(() -> new RuntimeException("Yoxdur"));

// DOĞRU amma çirkin
if (opt.isPresent()) {
    String dəyər4 = opt.get(); // Belə OK
}

// DOĞRU — ən yaxşı yanaşma
opt.ifPresent(v -> işlə(v));
```

### Anti-pattern 2: Optional-ı field kimi istifadə

```java
// YANLIŞ — Optional field kimi
public class İstifadəçi {
    private Optional<String> email; // PİS! Serializable deyil, field üçün nəzərdə tutulmayıb

    // YANLIŞ constructor
    public İstifadəçi(Optional<String> email) {
        this.email = email;
    }
}

// DOĞRU — null ola bilən field, Optional-la göstər
public class İstifadəçi {
    private String email; // null ola bilər

    // DOĞRU getter — Optional qaytarır
    public Optional<String> email() {
        return Optional.ofNullable(email);
    }

    // DOĞRU setter — null qəbul edir
    public void setEmail(String email) {
        this.email = email;
    }
}
```

### Anti-pattern 3: Parametr kimi Optional

```java
// YANLIŞ — metod parametri kimi Optional
public void işlə(Optional<String> ad) { // PİS!
    ad.ifPresent(this::log);
}

// İstifadəsi çirkin:
işlə(Optional.of("Salam"));
işlə(Optional.empty());

// DOĞRU — adi null ala bilər parametr
public void işlə(String ad) {
    if (ad != null) log(ad);
}
// İstifadəsi:
işlə("Salam");
işlə(null);

// Yaxud @Nullable annotation
public void işlə(@Nullable String ad) { }
```

### Anti-pattern 4: isPresent() + get() yanaşması

```java
// YANLIŞ — isPresent() + get() — Optional-dan istifadənin məqsədini pozur
Optional<String> opt = getOptional();

if (opt.isPresent()) {
    String s = opt.get();
    System.out.println(s.toUpperCase());
}

// DOĞRU — ifPresent()
opt.ifPresent(s -> System.out.println(s.toUpperCase()));

// DOĞRU — map ilə
opt.map(String::toUpperCase)
   .ifPresent(System.out::println);
```

### Anti-pattern 5: Optional-ı primitiv tipin sarğısı kimi istifadə

```java
// YANLIŞ — Optional<Integer> əvəzinə OptionalInt istifadə et
Optional<Integer> count = Optional.of(42); // Boxing lazımdır

// DOĞRU — primitiv versiyalar var
OptionalInt count2 = OptionalInt.of(42); // Boxing yoxdur
OptionalLong longVal = OptionalLong.of(100L);
OptionalDouble doubleVal = OptionalDouble.of(3.14);

// Metodlar eynidir
count2.ifPresent(n -> System.out.println(n));
int val = count2.orElse(0);
boolean var = count2.isPresent();
```

---

## Optional nə vaxt istifadə edilir?

### İstifadə edilməli:

```java
// 1. Metod nəticəsi null ola bilərsə — return tipini Optional et
public Optional<İstifadəçi> tapEmail(String email) {
    return db.find("email = ?", email); // Tapılmaya bilər
}

// 2. Stream terminal əməliyyatları
Optional<String> ilk = siyahı.stream()
    .filter(s -> s.startsWith("A"))
    .findFirst();

// 3. Getter — null ola bilən sahə
public Optional<String> getAddress() {
    return Optional.ofNullable(address);
}
```

### İstifadə edilməməli:

```java
// 1. Field kimi — Serializable deyil, Java Beans ilə uyğunsuz
// private Optional<String> field; // YANLIŞ

// 2. Parametr kimi — API-ni çirkin edir
// public void process(Optional<String> s) // YANLIŞ

// 3. Collection-ın içi — Optional<String> əvəzinə null süzgəci istifadə et
// List<Optional<String>> // YANLIŞ — boş dəyərləri Optional.empty saxlamaq əvəzinə çıxar

// 4. Primitiv tiplər üçün — OptionalInt, OptionalLong, OptionalDouble istifadə et
// Optional<Integer> // YANLIŞ — OptionalInt
```

---

## Real Dünya Nümunəsi

```java
import java.util.*;

// Şirkət → Şöbə → Menecer zənciri
record Menecer(String ad, String email) {}
record Şöbə(String ad, Optional<Menecer> menecer) {}
record Şirkət(String ad, Map<String, Şöbə> şöbələr) {}

public class ŞirkətXidməti {

    // IT şöbəsinin menecerinin emailini tap
    public Optional<String> ITMenecerEmail(Şirkət şirkət) {
        return Optional.ofNullable(şirkət.şöbələr().get("IT")) // Optional<Şöbə>
            .flatMap(Şöbə::menecer)    // Optional<Menecer>
            .map(Menecer::email)        // Optional<String>
            .filter(e -> e.contains("@")); // Keçərli email?
    }

    // Null yoxlamaları olmadan zəncirli çağırışlar!
}

// İstifadə
Şirkət şirkət = new Şirkət("TechCo", Map.of(
    "IT", new Şöbə("IT", Optional.of(new Menecer("Murad", "murad@techco.com"))),
    "HR", new Şöbə("HR", Optional.empty()) // Menecer yoxdur
));

ŞirkətXidməti xidmət = new ŞirkətXidməti();

xidmət.ITMenecerEmail(şirkət)
    .ifPresentOrElse(
        email -> System.out.println("IT Menecer email: " + email),
        () -> System.out.println("IT meneceri tapılmadı")
    );
// IT Menecer email: murad@techco.com

// HR-in meneceri yoxdur
Şirkət şirkət2 = new Şirkət("TechCo", Map.of(
    "IT", new Şöbə("IT", Optional.empty())
));

xidmət.ITMenecerEmail(şirkət2)
    .ifPresentOrElse(
        email -> System.out.println("IT Menecer email: " + email),
        () -> System.out.println("IT meneceri tapılmadı")
    );
// IT meneceri tapılmadı
```

---

## İntervyu Sualları

**S: Optional nə üçün yaradılıb?**
C: `NullPointerException`-ı azaltmaq və "nəticə mövcud olmaya bilər" semantikasını API-də açıq göstərmək üçün. Metod imzasında `Optional<T>` görəndə null ola biləcəyini bilirik.

**S: `orElse()` ilə `orElseGet()` fərqi nədir?**
C: `orElse(val)` — val həmişə hesablanır/qiymətləndirilir, Optional dolu olsa da. `orElseGet(supplier)` — supplier yalnız Optional boş olduqda çağırılır (lazy). Baha hesablama varsa `orElseGet` istifadə et.

**S: Optional-ı nə vaxt istifadə etməməliyik?**
C: 1) Field olaraq — serializable deyil; 2) Metod parametri olaraq — API çirkindir, overloading daha yaxşıdır; 3) Collection-ın elementi olaraq; 4) Primitiv tiplər üçün — `OptionalInt/Long/Double` var.

**S: `map()` ilə `flatMap()` fərqi Optional-da nədir?**
C: `map(f)` — f `Optional` qaytarsa `Optional<Optional<T>>` alınar. `flatMap(f)` — f mütləq `Optional` qaytarmalıdır, nəticəni düzləndirir → `Optional<T>`. Nested Optional-dan qaçmaq üçün flatMap.

**S: `isPresent()` + `get()` anti-pattern niyədir?**
C: Bu null yoxlamasını Optional-la əvəz edir — məqsəd pozulur. `ifPresent()`, `map()`, `orElse()` kimi funksional metodlar daha ifadəlidir, xəta riskini azaldır. `get()` yalnız `orElseThrow()` əvəzinə son çarə kimi.

**S: Optional-ın performansı necədir?**
C: Hər Optional — heap-da bir obyektdir. Çox istifadəsə GC yükü arta bilər. Performans kritik dövrələrdə (inner loops, collection-lar) null yoxlaması daha effektiv ola bilər. Amma əksər hallarda oxunaqlıq üstündür.
