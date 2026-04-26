# 28 — Java-da Records (Java 16+)

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Record nədir?](#record-nədir)
2. [Record sintaksisi](#record-sintaksisi)
3. [Compact Constructor](#compact-constructor)
4. [Custom metodlar records-da](#custom-metodlar-records-da)
5. [Records vs Classes](#records-vs-classes)
6. [Immutability — dəyişməzlik](#immutability--dəyişməzlik)
7. [Records-un məhdudiyyətləri](#records-un-məhdudiyyətləri)
8. [İstifadə halları (Use Cases)](#i̇stifadə-halları-use-cases)
9. [Generik Records](#generik-records)
10. [Sealed Records](#sealed-records)
11. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Record nədir?

**Record** — Java 16-da (JEP 395) tam stabilləşən xüsusi sinif növüdür. Məqsədi: **məlumat daşıyıcı siniflərin (data carrier)** yazılmasını sadələşdirmək.

Record avtomatik yaradır:
- `private final` sahələr
- Constructor (bütün sahələrlə)
- Getter metodları (sahə adı ilə — `get` prefiksi yoxdur!)
- `equals()` və `hashCode()`
- `toString()`

```java
// YANLIŞ yol — köhnə üsul (verbose!)
public final class NöqtəKöhnə {
    private final double x;
    private final double y;

    public NöqtəKöhnə(double x, double y) {
        this.x = x;
        this.y = y;
    }

    public double x() { return x; }
    public double y() { return y; }

    @Override
    public boolean equals(Object o) {
        if (!(o instanceof NöqtəKöhnə n)) return false;
        return Double.compare(x, n.x) == 0 && Double.compare(y, n.y) == 0;
    }

    @Override
    public int hashCode() {
        return Objects.hash(x, y);
    }

    @Override
    public String toString() {
        return "NöqtəKöhnə[x=" + x + ", y=" + y + "]";
    }
}

// DOĞRU yol — Record ilə (eyni funksionallıq, 1 sətir!)
public record Nöqtə(double x, double y) { }

// Hər ikisi eyni davranışa malikdir:
Nöqtə n1 = new Nöqtə(3.0, 4.0);
Nöqtə n2 = new Nöqtə(3.0, 4.0);

System.out.println(n1.x());          // 3.0 — getter (get prefiksi yoxdur)
System.out.println(n1.y());          // 4.0
System.out.println(n1.equals(n2));   // true
System.out.println(n1.hashCode() == n2.hashCode()); // true
System.out.println(n1);              // "Nöqtə[x=3.0, y=4.0]"
```

---

## Record sintaksisi

```java
// Əsas sintaksis:
public record RecordAdı(Tip1 komponent1, Tip2 komponent2) {
    // İstəyə bağlı: compact constructor, metodlar, static üzvlər
}

// Nümunələr:

// Sadə record — coordinates
public record Koordinat(double en, double boy) {}

// Kompleks record
public record İstifadəçi(
    int id,
    String istifadəçiAdı,
    String email,
    java.time.LocalDate qeydiyyatTarixi
) {}

// Annotasiyalarla
public record Məhsul(
    @jakarta.validation.constraints.NotNull String ad,
    @jakarta.validation.constraints.Positive double qiymət,
    @jakarta.validation.constraints.Min(0) int stok
) {}

// İstifadə:
İstifadəçi user = new İstifadəçi(1, "anar_h", "anar@mail.com",
                                  java.time.LocalDate.now());
System.out.println(user.id());              // 1
System.out.println(user.istifadəçiAdı());  // "anar_h"
System.out.println(user.email());          // "anar@mail.com"
System.out.println(user);
// İstifadəçi[id=1, istifadəçiAdı=anar_h, email=anar@mail.com, ...]
```

---

## Compact Constructor

Record-un xüsusi constructor növüdür. Parametrləri yenidən yazmaq lazım deyil — avtomatik ötürülür.

```java
public record Tələbə(String ad, String soyad, double qiymətOrtalama, int kurs) {

    // Compact Constructor — { } içi yalnız doğrulama/normallaşdırma
    // Parametrlər avtomatik field-lərə mənimsədilir (constructor bitmişindən sonra)
    public Tələbə {
        // Doğrulama
        if (ad == null || ad.isBlank()) {
            throw new IllegalArgumentException("Ad boş ola bilməz");
        }
        if (qiymətOrtalama < 0 || qiymətOrtalama > 4.0) {
            throw new IllegalArgumentException("GPA 0-4 arasında olmalıdır: " + qiymətOrtalama);
        }
        if (kurs < 1 || kurs > 6) {
            throw new IllegalArgumentException("Kurs 1-6 arasında olmalıdır: " + kurs);
        }

        // Normallaşdırma — parametrlərin dəyərini dəyişmək olar
        ad = ad.trim();       // Boşluqları sil
        soyad = soyad.trim(); // Sahəyə avtomatik mənimsədilir
        // qiymətOrtalama, kurs dəyişdirilmədən saxlanır
    }
}

// Daha mürəkkəb nümunə:
public record EmailÜnvanı(String dəyər) {
    // Static regex — bir dəfə kompilyasiya edilir
    private static final java.util.regex.Pattern EMAIL_PATTERN =
        java.util.regex.Pattern.compile("^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$");

    // Compact Constructor
    public EmailÜnvanı {
        if (dəyər == null || dəyər.isBlank()) {
            throw new IllegalArgumentException("Email boş ola bilməz");
        }
        dəyər = dəyər.toLowerCase().trim(); // normallaşdır
        if (!EMAIL_PATTERN.matcher(dəyər).matches()) {
            throw new IllegalArgumentException("Email formatı düzgün deyil: " + dəyər);
        }
    }

    // Canonical constructor (tam parametrli) — nadirən istifadə edilir
    // Record-da istifadə etmək istəsəz, bütün sahələri əl ilə mənimsətməlisiniz
}

public record Ünvan(String küçə, String şəhər, String ölkə, String poçtKodu) {
    // Canonical constructor (standart) — əl ilə yazılmış versiya
    public Ünvan(String küçə, String şəhər, String ölkə, String poçtKodu) {
        // Hər sahəni əl ilə mənimsət
        this.küçə = küçə == null ? "" : küçə.trim();
        this.şəhər = şəhər == null ? "" : şəhər.trim();
        this.ölkə = ölkə == null ? "AZ" : ölkə.trim().toUpperCase();
        this.poçtKodu = poçtKodu == null ? "" : poçtKodu.replaceAll("\\s+", "");
    }
}
```

---

## Custom metodlar records-da

Record-larda öz metodlarınızı əlavə edə bilərsiniz.

```java
public record ParaVahidi(long sentlər, String valyuta) {

    // Compact Constructor
    public ParaVahidi {
        if (sentlər < 0) throw new IllegalArgumentException("Mənfi dəyər qəbul edilmir");
        valyuta = valyuta.toUpperCase();
    }

    // Instance metodlar
    public ParaVahidi əlavə_et(ParaVahidi digər) {
        if (!this.valyuta.equals(digər.valyuta)) {
            throw new IllegalArgumentException("Fərqli valyutaları toplamaq olmaz");
        }
        return new ParaVahidi(this.sentlər + digər.sentlər, this.valyuta);
    }

    public ParaVahidi çıxar(ParaVahidi digər) {
        if (!this.valyuta.equals(digər.valyuta)) {
            throw new IllegalArgumentException("Fərqli valyutalar");
        }
        return new ParaVahidi(this.sentlər - digər.sentlər, this.valyuta);
    }

    public ParaVahidi vur(double əmsal) {
        return new ParaVahidi((long)(this.sentlər * əmsal), this.valyuta);
    }

    public boolean daha_böyükdür(ParaVahidi digər) {
        return this.sentlər > digər.sentlər;
    }

    public double manatFormatı() {
        return sentlər / 100.0;
    }

    // Static factory metodlar
    public static ParaVahidi manatdan(double manat) {
        return new ParaVahidi((long)(manat * 100), "AZN");
    }

    public static ParaVahidi sıfır(String valyuta) {
        return new ParaVahidi(0, valyuta);
    }

    // Custom toString
    @Override
    public String toString() {
        return "%s %.2f".formatted(valyuta, manatFormatı());
    }
}

// İstifadə:
ParaVahidi qiymət = ParaVahidi.manatdan(99.99);
ParaVahidi vergi = qiymət.vur(0.18); // 18% ƏDV
ParaVahidi cəmi = qiymət.əlavə_et(vergi);

System.out.println(qiymət); // AZN 99.99
System.out.println(vergi);  // AZN 18.00
System.out.println(cəmi);   // AZN 117.99
```

### Interface ilə Records

```java
public interface Formatlanabilən {
    String formatla();
}

public interface Doğrulanabilən {
    boolean doğrula();
}

public record KredirKart(
    String nömrə,
    String sahibi,
    String son_istifadə_tarixi, // "MM/YY"
    String cvv
) implements Formatlanabilən, Doğrulanabilən {

    public KredirKart {
        nömrə = nömrə.replaceAll("\\s+", ""); // boşluqları sil
    }

    @Override
    public String formatla() {
        // Yalnız son 4 rəqəmi göstər
        return "**** **** **** " + nömrə.substring(nömrə.length() - 4);
    }

    @Override
    public boolean doğrula() {
        return nömrə.length() == 16 &&
               nömrə.chars().allMatch(Character::isDigit) &&
               cvv.length() >= 3;
    }

    @Override
    public String toString() {
        return "KreditKart{nömrə=%s, sahibi=%s}".formatted(formatla(), sahibi);
    }
}
```

---

## Records vs Classes

| Xüsusiyyət | Record | Class |
|---|---|---|
| Məqsəd | Data taşıyıcı | Ümumi məqsədli |
| Miras | Yalnız interface | Class + Interface |
| Sahələr | Yalnız `private final` | İstənilən |
| Setter | Yoxdur | Ola bilər |
| equals/hashCode/toString | Avtomatik | Manual/IDE |
| Kod həcmi | Minimal | Verbose |
| Mutability | Immutable | Seçimə bağlı |

```java
// RECORD — nə zaman istifadə et:
// ✓ DTO (Data Transfer Object)
// ✓ Value Object (pul, koordinat, email)
// ✓ API cavabları / sorğu parametrləri
// ✓ Map key-ləri
// ✓ Compound return değərləri

public record ApiCavab<T>(
    boolean uğurlu,
    T məlumat,
    String mesaj,
    int statusKodu
) {
    public static <T> ApiCavab<T> uğurlu(T məlumat) {
        return new ApiCavab<>(true, məlumat, "OK", 200);
    }

    public static <T> ApiCavab<T> xəta(String mesaj, int kod) {
        return new ApiCavab<>(false, null, mesaj, kod);
    }
}

// CLASS — nə zaman istifadə et:
// ✓ Mutable state lazımdır
// ✓ Miras/polimorfizm lazımdır
// ✓ Kompleks iş məntiqi var
// ✓ Lifecycle metodları lazımdır (open/close)
public class Hesab { // state dəyişir
    private double balans; // mutable!
    public void pul_yatır(double m) { balans += m; }
}
```

---

## Immutability — dəyişməzlik

Records tam immutable-dır. Amma mutable sahələr problemlər yarada bilər.

```java
// PROBLEM: Record mutable sahə saxlayır
public record Sinif(String ad, List<String> tələbələr) {
    // tələbələr List-i — kənar kod dəyişdirə bilər!
}

Sinif sinif = new Sinif("Java 101", new ArrayList<>(List.of("Anar", "Leyla")));
sinif.tələbələr().add("Kamil"); // Record "immutable", lakin List dəyişdi!
System.out.println(sinif.tələbələr()); // [Anar, Leyla, Kamil]

// DOĞRU: Defensive copy + unmodifiable
public record Sinif(String ad, List<String> tələbələr) {
    public Sinif {
        // Defensive copy + unmodifiable
        tələbələr = List.copyOf(tələbələr); // həm kopya, həm dəyişdirilməz
    }
}

Sinif sinif = new Sinif("Java 101", new ArrayList<>(List.of("Anar", "Leyla")));
// sinif.tələbələr().add("Kamil"); // UnsupportedOperationException!

// Yeni siyahı ilə yeni record:
List<String> yeniSiyahı = new ArrayList<>(sinif.tələbələr());
yeniSiyahı.add("Kamil");
Sinif yeniSinif = new Sinif(sinif.ad(), yeniSiyahı); // Yeni record!
```

### Wither metodlar — "dəyişdirilmiş kopya"

Record-lar immutable olduğu üçün, dəyişdirmək əvəzinə yeni record yaradılır:

```java
public record İstifadəçi(
    int id,
    String ad,
    String email,
    boolean aktiv
) {
    // "Wither" metodlar — dəyişdirilmiş kopya yarat
    public İstifadəçi adlaDəyiş(String yeniAd) {
        return new İstifadəçi(id, yeniAd, email, aktiv);
    }

    public İstifadəçi emailDəyiş(String yeniEmail) {
        return new İstifadəçi(id, ad, yeniEmail, aktiv);
    }

    public İstifadəçi deaktivEt() {
        return new İstifadəçi(id, ad, email, false);
    }

    public İstifadəçi aktivEt() {
        return new İstifadəçi(id, ad, email, true);
    }
}

// İstifadə — method chaining mümkündür:
İstifadəçi user = new İstifadəçi(1, "Anar", "anar@old.com", true);
İstifadəçi yenilənmiş = user
    .emailDəyiş("anar@new.com")
    .adlaDəyiş("Anar Hüseynov");

System.out.println(user);         // orijinal dəyişmədi!
System.out.println(yenilənmiş);   // yeni record
```

---

## Records-un məhdudiyyətləri

```java
// 1. Records başqa sinifi extend edə bilməz
// public record Məhsul(...) extends BaseEntity { } // XƏTA!

// 2. Records extend edilə bilməz (implicit final-dir)
// public class XüsuslMəhsul extends Məhsul { } // XƏTA!

// 3. Instance sahə əlavə etmək olmaz
public record Nöqtə(double x, double y) {
    // double z; // XƏTA! Yalnız record komponentləri var
    static int sayac = 0; // OK — static sahə ola bilər
}

// 4. Sahələr həmişə public görünür (getter-lər)
// Record komponentlərini private/protected etmək olmaz

// 5. native metodlar ola bilməz
public record Test(String dəyər) {
    // native String daxiliMetod(); // XƏTA!
}

// 6. Mutable state mümkün deyil (instance sahə)
public record Sayac(int dəyər) {
    // Bu yanlış — workaround mümkün deyil:
    // public void artır() { this.dəyər++; } // XƏTA!

    // Bunun əvəzinə:
    public Sayac artır() {
        return new Sayac(dəyər + 1); // Yeni record
    }
}
```

---

## İstifadə halları (Use Cases)

### 1. DTO — Data Transfer Object

```java
// API-dən gələn sorğu
public record İstifadəçiYaratmaSorğusu(
    @jakarta.validation.constraints.NotBlank String ad,
    @jakarta.validation.constraints.Email String email,
    @jakarta.validation.constraints.Size(min = 8) String şifrə
) {}

// API-yə göndərilən cavab
public record İstifadəçiCavabı(
    int id,
    String ad,
    String email,
    java.time.LocalDateTime yaradılmaTarixi
) {
    // Entity-dən DTO-ya çevirici
    public static İstifadəçiCavabı entitydən(İstifadəçiEntity entity) {
        return new İstifadəçiCavabı(
            entity.getId(),
            entity.getAd(),
            entity.getEmail(),
            entity.getYaradılmaTarixi()
        );
    }
}
```

### 2. Value Object

```java
// Domain-specific value objects
public record IBAN(String dəyər) {
    private static final java.util.regex.Pattern IBAN_PATTERN =
        java.util.regex.Pattern.compile("^[A-Z]{2}[0-9]{2}[A-Z0-9]{4}[0-9]{7}([A-Z0-9]?){0,16}$");

    public IBAN {
        dəyər = dəyər.replaceAll("\\s+", "").toUpperCase();
        if (!IBAN_PATTERN.matcher(dəyər).matches()) {
            throw new IllegalArgumentException("Düzgün IBAN deyil: " + dəyər);
        }
    }

    public String formatlanmış() {
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < dəyər.length(); i++) {
            if (i > 0 && i % 4 == 0) sb.append(' ');
            sb.append(dəyər.charAt(i));
        }
        return sb.toString();
    }
}

public record TelefonNömrəsi(String ölkəKodu, String nömrə) {
    public TelefonNömrəsi {
        nömrə = nömrə.replaceAll("[\\s\\-()]", "");
        if (!nömrə.matches("\\d{7,15}")) {
            throw new IllegalArgumentException("Düzgün nömrə deyil");
        }
    }

    public String tam_nömrə() {
        return ölkəKodu + nömrə;
    }

    @Override
    public String toString() {
        return tam_nömrə();
    }
}
```

### 3. Map key kimi istifadə

```java
public record GünSaatı(Gün gün, int saat) {
    public GünSaatı {
        if (saat < 0 || saat > 23) throw new IllegalArgumentException("Saat 0-23 arasında olmalıdır");
    }
}

// Record-un equals/hashCode avtomatik — Map key kimi mükəmməldir!
Map<GünSaatı, String> cədvəl = new HashMap<>();
cədvəl.put(new GünSaatı(Gün.BAZAR_ERTƏSİ, 9), "Java Dərsi");
cədvəl.put(new GünSaatı(Gün.ÇƏRŞƏNBƏ, 14), "Layihə Müzakirəsi");

String dərs = cədvəl.get(new GünSaatı(Gün.BAZAR_ERTƏSİ, 9));
System.out.println(dərs); // "Java Dərsi"
```

---

## Generik Records

```java
// Generik record — tip parametri ilə
public record Cüt<A, B>(A birinci, B ikinci) {
    // Factory metod
    public static <A, B> Cüt<A, B> of(A a, B b) {
        return new Cüt<>(a, b);
    }

    // Əvəzləmə
    public Cüt<B, A> çevir() {
        return new Cüt<>(ikinci, birinci);
    }

    public <C> Cüt<A, C> xəritəB(java.util.function.Function<B, C> f) {
        return new Cüt<>(birinci, f.apply(ikinci));
    }
}

// Nəticə record — uğur/xəta
public record Nəticə<T>(T dəyər, String xəta) {
    public static <T> Nəticə<T> uğurlu(T dəyər) {
        return new Nəticə<>(dəyər, null);
    }

    public static <T> Nəticə<T> xətalı(String xəta) {
        return new Nəticə<>(null, xəta);
    }

    public boolean uğurludur() { return xəta == null; }

    public T dəyəriAl() {
        if (!uğurludur()) throw new RuntimeException("Xəta: " + xəta);
        return dəyər;
    }

    public <U> Nəticə<U> xəritə(java.util.function.Function<T, U> f) {
        if (!uğurludur()) return xətalı(xəta);
        try {
            return uğurlu(f.apply(dəyər));
        } catch (Exception e) {
            return xətalı(e.getMessage());
        }
    }
}

// İstifadə:
Nəticə<Integer> nəticə = Nəticə.uğurlu(42);
Nəticə<String> mətn = nəticə.xəritə(n -> "Dəyər: " + n);
System.out.println(mətn.dəyəriAl()); // "Dəyər: 42"

Nəticə<Integer> xəta = Nəticə.xətalı("Bölmə xətası");
Nəticə<String> nəticə2 = xəta.xəritə(n -> "Dəyər: " + n); // xəta ötürülür
System.out.println(nəticə2.uğurludur()); // false
```

---

## Sealed Records

Java 17+ sealed interface ilə records birlikdə çox güclü algebraic data type-lar yaradır.

```java
// Sealed interface — yalnız bu record-lar implementasiya edə bilər
public sealed interface Forma
    permits Dairə, Düzbucaqlı, Üçbucaq {}

public record Dairə(double radius) implements Forma {
    public double sahə() { return Math.PI * radius * radius; }
}

public record Düzbucaqlı(double en, double hündürlük) implements Forma {
    public double sahə() { return en * hündürlük; }
}

public record Üçbucaq(double a, double b, double c) implements Forma {
    public double sahə() {
        double s = (a + b + c) / 2;
        return Math.sqrt(s * (s-a) * (s-b) * (s-c));
    }
}

// Pattern matching ilə istifadə (Java 21+):
public double sahəHesabla(Forma forma) {
    return switch (forma) {
        case Dairə d          -> Math.PI * d.radius() * d.radius();
        case Düzbucaqlı r     -> r.en() * r.hündürlük();
        case Üçbucaq t        -> {
            double s = (t.a() + t.b() + t.c()) / 2;
            yield Math.sqrt(s * (s-t.a()) * (s-t.b()) * (s-t.c()));
        }
        // default lazım deyil — sealed, bütün hallar əhatə edilib!
    };
}
```

---

## İntervyu Sualları

**S1: Record nədir və nə üçün istifadə edilir?**
> Record — Java 16-da stabilləşən, data taşıyıcı siniflərin yazılmasını sadələşdirən xüsusi sinif növüdür. Avtomatik `equals()`, `hashCode()`, `toString()`, getter-lər yaradır. DTO, Value Object, API request/response modelləri üçün idealdır.

**S2: Record ilə class arasındakı əsas fərqlər nədir?**
> Record: yalnız `private final` sahələr (komponetlər), setter yoxdur, başqa sinifi extend edə bilməz, kodu çox az. Class: istənilən sahə, setter ola bilər, miras zənciri qurula bilər, tam flexibel.

**S3: Compact Constructor nədir?**
> Record-un sahə adlarını parametr kimi yenidən yazmadan yazılan constructor-dur. `{}` daxilindəki kod icra olunur, sonra parametrlər avtomatik sahələrə mənimsədilir. Doğrulama/normallaşdırma üçün istifadə edilir.

**S4: Record-da mutable sahə ola bilərmi?**
> Texniki cəhətdən, mutable tip (List, Map) ola bilər. Lakin bu, record-un immutability konsepsiyasını pozur. Compact constructor-da `List.copyOf()` ilə defensive copy alınmalı, getter-dən `Collections.unmodifiableList()` qaytarılmalıdır.

**S5: Record başqa sinifi extend edə bilərmi?**
> Xeyr. Record `java.lang.Record`-dan dolayı yollarla miras alır — əlavə miras mümkün deyil. Lakin istənilən sayda interface implement edə bilər.

**S6: Record-da abstract metod ola bilərmi?**
> Xeyr. Record-lar `final`-dır (extend edilə bilməz), ona görə abstract metodun mənası yoxdur. Lakin sealed interface ilə birlikdə istifadə edildikdə, interface-in abstract metodlarını implement etməlidir.

**S7: equals() record-da necə işləyir?**
> Record-da `equals()` bütün komponentləri müqayisə edir. İki record yalnız bütün komponetləri equals olarsa, bərabər sayılır. Bu, class-dan fərqli olaraq identity deyil, structural equality-dir.

**S8: Record-u ne zaman istifadə etməmək lazımdır?**
> 1. Mutable state lazım olduqda (hesab balansı, siyahıya əlavə), 2. Miras lazım olduqda, 3. Kompleks iş məntiqi olan domain entity-lərdə (Bank Account, Order), 4. JPA/Hibernate entity-lərdə (default constructor tələb edir).
