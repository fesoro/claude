# 025 — Java-da Enum Tipi (Enumerations)
**Səviyyə:** Orta


## Mündəricat
1. [Enum nədir?](#enum-nədir)
2. [Enum əsas sintaksis](#enum-əsas-sintaksis)
3. [Enum ilə sahələr və metodlar](#enum-ilə-sahələr-və-metodlar)
4. [Abstract metodlar enum-da](#abstract-metodlar-enum-da)
5. [Enum interface implement edir](#enum-interface-implement-edir)
6. [EnumMap və EnumSet](#enummap-və-enumset)
7. [Enum switch expression-da](#enum-switch-expression-da)
8. [Singleton pattern ilə Enum](#singleton-pattern-ilə-enum)
9. [Praktiki nümunələr](#praktiki-nümunələr)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Enum nədir?

**Enum** — sabit dəyərlər toplusunu təmsil edən xüsusi sinif tipidir. Bir qrup sabitin məntiqi olaraq birlikdə olması lazım olduğu zaman istifadə edilir.

```java
// YANLIŞ: int sabitləri ilə (type-safe deyil!)
public class OrderStatus {
    public static final int PENDING   = 0;
    public static final int CONFIRMED = 1;
    public static final int SHIPPED   = 2;
    public static final int DELIVERED = 3;
    public static final int CANCELLED = 4;
}

void statusuEmal(int status) {
    // status = 999 ötürülə bilər — hata yoxdur!
}

// DOĞRU: Enum ilə (type-safe!)
public enum SifarişStatusu {
    GÖZLƏNILIR,
    TƏSDİQLƏNDİ,
    GÖNDƏRİLDİ,
    ÇATDIRILDI,
    LƏĞV_EDİLDİ
}

void statusuEmal(SifarişStatusu status) {
    // Yalnız SifarişStatusu dəyərləri ötürülə bilər — compile-time yoxlama!
}
```

---

## Enum əsas sintaksis

```java
public enum Gün {
    BAZAR_ERTƏSİ,
    ÇƏRŞƏNBƏ_AXŞAMİ,
    ÇƏRŞƏNBƏ,
    CÜMƏ_AXŞAMİ,
    CÜMƏ,
    ŞƏNBƏ,
    BAZAR;

    // Enum-un miras aldığı Enum<E> sinifinin metodları:
    // name()    — enum sabitinin adını qaytarır
    // ordinal() — enum sabitinin indeksini qaytarır (0-dan başlayır)
    // toString()— name() ilə eynidir (default)
    // values()  — bütün enum sabitlərini massiv kimi qaytarır
    // valueOf() — stringdən enum sabitinə çevirir
}

// İstifadə:
Gün bu_gün = Gün.CÜMƏ;
System.out.println(bu_gün.name());    // "CÜMƏ"
System.out.println(bu_gün.ordinal()); // 4
System.out.println(bu_gün);          // "CÜMƏ" (toString)

// Bütün dəyərləri dövrə
for (Gün g : Gün.values()) {
    System.out.println(g.ordinal() + ": " + g.name());
}

// Stringdən enum
Gün çərşənbə = Gün.valueOf("ÇƏRŞƏNBƏ"); // OK
// Gün.valueOf("çərşənbə"); // IllegalArgumentException — həssasdır!

// Müqayisə — == ilə (equals() yerinə):
Gün g = Gün.BAZAR;
if (g == Gün.BAZAR) { // == güvənlidir — enum singelton-lardır
    System.out.println("Bu gün bazardır!");
}
```

---

## Enum ilə sahələr və metodlar

Enum-lar sahələr, constructor-lar və metodlar saxlaya bilər.

```java
public enum Planet {
    // Hər sabit — constructor-u çağırır
    MERKURI  (3.303e+23, 2.4397e6),
    VENERA   (4.869e+24, 6.0518e6),
    YER      (5.976e+24, 6.37814e6),
    MARS     (6.421e+23, 3.3972e6),
    YUPİTER  (1.9e+27,   7.1492e7),
    SATURN   (5.688e+26, 6.0268e7),
    URAN     (8.686e+25, 2.5559e7),
    NEPTUN   (1.024e+26, 2.4746e7);

    private final double kütlə;  // kq
    private final double radius; // metr

    // Private constructor — yalnız enum sabitləri istifadə edir
    Planet(double kütlə, double radius) {
        this.kütlə = kütlə;
        this.radius = radius;
    }

    static final double G = 6.67300E-11; // Gravitasiya sabiti

    // Metod — enum sabitinə görə dəyişir (runtime)
    public double sürfacegravity() {
        return G * kütlə / (radius * radius);
    }

    // Ağırlığı hesabla (kq ilə verilmiş çəkiyə görə)
    public double weight(double otherMass) {
        return otherMass * sürfacegravity();
    }

    // Getter-lər
    public double getKütlə() { return kütlə; }
    public double getRadius() { return radius; }
}

// İstifadə:
double yerüzündəkiÇəki = 75.0; // kq
for (Planet p : Planet.values()) {
    System.out.printf("%-10s ağırlıq: %6.2f N%n",
                      p, p.weight(yerüzündəkiÇəki));
}
```

### Tam enum nümunəsi — HTTP Status kodları

```java
public enum HTTPStatus {
    // 2xx — Uğurlu
    OK(200, "OK"),
    CREATED(201, "Created"),
    NO_CONTENT(204, "No Content"),

    // 3xx — Yönləndirmə
    MOVED_PERMANENTLY(301, "Moved Permanently"),
    NOT_MODIFIED(304, "Not Modified"),

    // 4xx — Client xəta
    BAD_REQUEST(400, "Bad Request"),
    UNAUTHORIZED(401, "Unauthorized"),
    FORBIDDEN(403, "Forbidden"),
    NOT_FOUND(404, "Not Found"),
    CONFLICT(409, "Conflict"),

    // 5xx — Server xəta
    INTERNAL_SERVER_ERROR(500, "Internal Server Error"),
    SERVICE_UNAVAILABLE(503, "Service Unavailable");

    private final int kod;
    private final String mesaj;

    HTTPStatus(int kod, String mesaj) {
        this.kod = kod;
        this.mesaj = mesaj;
    }

    public int getKod() { return kod; }
    public String getMesaj() { return mesaj; }

    public boolean uğurludur() { return kod >= 200 && kod < 300; }
    public boolean yönləndirmədir() { return kod >= 300 && kod < 400; }
    public boolean clientXətasıdır() { return kod >= 400 && kod < 500; }
    public boolean serverXətasıdır() { return kod >= 500; }

    // Koddan enum tap
    public static HTTPStatus koddan_tap(int kod) {
        for (HTTPStatus s : values()) {
            if (s.kod == kod) return s;
        }
        throw new IllegalArgumentException("Naməlum HTTP status kodu: " + kod);
    }

    @Override
    public String toString() {
        return kod + " " + mesaj;
    }
}

// İstifadə:
HTTPStatus status = HTTPStatus.NOT_FOUND;
System.out.println(status);                  // "404 Not Found"
System.out.println(status.uğurludur());     // false
System.out.println(status.clientXətasıdır()); // true

HTTPStatus tapıldı = HTTPStatus.koddan_tap(200);
System.out.println(tapıldı); // "200 OK"
```

---

## Abstract metodlar enum-da

Hər enum sabiti abstract metodu özünəməxsus şəkildə implement edə bilər.

```java
public enum Əməliyyat {
    // Hər sabit abstract metodu implements edir — bilavasitə!
    TOPLAMA("+") {
        @Override
        public double tətbiq_et(double a, double b) {
            return a + b;
        }
    },
    ÇIXMA("-") {
        @Override
        public double tətbiq_et(double a, double b) {
            return a - b;
        }
    },
    VURMA("*") {
        @Override
        public double tətbiq_et(double a, double b) {
            return a * b;
        }
    },
    BÖLMƏ("/") {
        @Override
        public double tətbiq_et(double a, double b) {
            if (b == 0) throw new ArithmeticException("Sıfıra bölmək olmaz!");
            return a / b;
        }
    };

    private final String simvol;

    Əməliyyat(String simvol) {
        this.simvol = simvol;
    }

    // Abstract metod — hər sabit implement etməlidir
    public abstract double tətbiq_et(double a, double b);

    public String getSimvol() { return simvol; }

    @Override
    public String toString() { return simvol; }
}

// İstifadə:
double a = 10, b = 3;
for (Əməliyyat op : Əməliyyat.values()) {
    System.out.printf("%.1f %s %.1f = %.2f%n", a, op, b, op.tətbiq_et(a, b));
}
// 10.0 + 3.0 = 13.00
// 10.0 - 3.0 = 7.00
// 10.0 * 3.0 = 30.00
// 10.0 / 3.0 = 3.33
```

---

## Enum interface implement edir

```java
public interface Çevrilə_bilən<T> {
    T çevir(String dəyər);
}

public interface Doğrulana_bilən {
    boolean doğrula(String dəyər);
}

public enum VeriTipi implements Çevrilə_bilən<Object>, Doğrulana_bilən {
    TAM_ƏDƏD {
        @Override
        public Object çevir(String dəyər) {
            return Integer.parseInt(dəyər);
        }
        @Override
        public boolean doğrula(String dəyər) {
            try { Integer.parseInt(dəyər); return true; }
            catch (NumberFormatException e) { return false; }
        }
    },
    KƏSR_ƏDƏD {
        @Override
        public Object çevir(String dəyər) {
            return Double.parseDouble(dəyər);
        }
        @Override
        public boolean doğrula(String dəyər) {
            try { Double.parseDouble(dəyər); return true; }
            catch (NumberFormatException e) { return false; }
        }
    },
    MƏTİN {
        @Override
        public Object çevir(String dəyər) {
            return dəyər;
        }
        @Override
        public boolean doğrula(String dəyər) {
            return dəyər != null && !dəyər.isBlank();
        }
    },
    BOOLEAN {
        @Override
        public Object çevir(String dəyər) {
            return Boolean.parseBoolean(dəyər);
        }
        @Override
        public boolean doğrula(String dəyər) {
            return "true".equalsIgnoreCase(dəyər) || "false".equalsIgnoreCase(dəyər);
        }
    };
}

// İstifadə:
String giriş = "42";
VeriTipi tip = VeriTipi.TAM_ƏDƏD;

if (tip.doğrula(giriş)) {
    Object nəticə = tip.çevir(giriş);
    System.out.println("Çevrildi: " + nəticə + " (" + nəticə.getClass().getSimpleName() + ")");
}
```

---

## EnumMap və EnumSet

Enum-lar üçün optimallaşdırılmış Collection sinifləri.

```java
public enum Növbə {
    SƏHƏR, GÜN, AXŞAM, GECƏ
}

// EnumMap — HashMap-dən daha sürətli enum key-lər üçün
public class İşçiCədvəli {
    // Array-based implementation — çox sürətli
    private final EnumMap<Növbə, List<String>> cədvəl = new EnumMap<>(Növbə.class);

    public İşçiCədvəli() {
        for (Növbə n : Növbə.values()) {
            cədvəl.put(n, new ArrayList<>());
        }
    }

    public void işçi_əlavə_et(String işçi, Növbə növbə) {
        cədvəl.get(növbə).add(işçi);
    }

    public List<String> növbəninİşçiləri(Növbə növbə) {
        return List.copyOf(cədvəl.getOrDefault(növbə, List.of()));
    }

    public void cədvəliGöstər() {
        cədvəl.forEach((növbə, işçilər) -> {
            System.out.printf("%-10s: %s%n", növbə, işçilər);
        });
    }
}

// EnumSet — BitSet-based, yaddaş baxımından çox səmərəli
public class GünlərNümunəsi {
    public static void main(String[] args) {
        // İş günləri
        EnumSet<Gün> işGünləri = EnumSet.range(Gün.BAZAR_ERTƏSİ, Gün.CÜMƏ);
        // Həftə sonu
        EnumSet<Gün> həftəSonu = EnumSet.of(Gün.ŞƏNBƏ, Gün.BAZAR);
        // Bütün günlər
        EnumSet<Gün> bütünGünlər = EnumSet.allOf(Gün.class);
        // Heç bir gün
        EnumSet<Gün> heçBirGün = EnumSet.noneOf(Gün.class);

        // Set əməliyyatları:
        EnumSet<Gün> iş_deyil = EnumSet.complementOf(işGünləri); // həftə sonu

        System.out.println("İş günləri: " + işGünləri);
        System.out.println("Həftə sonu: " + həftəSonu);
        System.out.println("İş deyil: " + iş_deyil);

        // Üzvlük yoxlaması — O(1) mürəkkəbliyi
        Gün bu_gün = Gün.CÜMƏ;
        if (işGünləri.contains(bu_gün)) {
            System.out.println(bu_gün + " iş günüdür");
        }
    }
}
```

---

## Enum switch expression-da

```java
public class SwitchNümunəsi {

    // Java 14+ switch expression
    public String statusuTərcümə_et(SifarişStatusu status) {
        return switch (status) {
            case GÖZLƏNILIR    -> "Sifarişiniz qəbul edildi";
            case TƏSDİQLƏNDİ  -> "Sifarişiniz təsdiqləndi";
            case GÖNDƏRİLDİ   -> "Sifarişiniz yoldadır";
            case ÇATDIRILDI   -> "Sifarişiniz çatdırıldı";
            case LƏĞV_EDİLDİ  -> "Sifarişiniz ləğv edildi";
        };
        // QEYD: Bütün enum dəyərləri əhatə edildiyi üçün default lazım deyil!
        // Compiler yoxlayır — unutsaq xəta verir
    }

    // Enum ilə kompleks switch
    public void növbəyiEmal(Növbə növbə) {
        switch (növbə) {
            case SƏHƏR -> {
                System.out.println("Səhər növbəsi: 06:00 - 14:00");
                hazırlıqEt();
            }
            case GÜN -> {
                System.out.println("Gündüz növbəsi: 14:00 - 22:00");
            }
            case AXŞAM, GECƏ -> { // Birdən çox case
                System.out.println("Gecə növbəsi — əlavə ödəniş var");
                əlavəÖdənişHesabla();
            }
        }
    }

    private void hazırlıqEt() { System.out.println("Hazırlıq edilir..."); }
    private void əlavəÖdənişHesabla() { System.out.println("Əlavə ödəniş: +30%"); }

    // Enum ilə pattern matching (Java 21+)
    public String statusMəlumatı(Object obj) {
        return switch (obj) {
            case SifarişStatusu s when s == SifarişStatusu.ÇATDIRILDI ->
                "Tamamlandı — dəyərləndirmə bildirin";
            case SifarişStatusu s ->
                "Status: " + s;
            case String str ->
                "Mətn: " + str;
            default ->
                "Naməlum";
        };
    }
}
```

---

## Singleton pattern ilə Enum

Enum-la Singleton yaratmaq ən güvənli üsullardan biridir (Joshua Bloch tövsiyəsi).

```java
// Adi Singleton — thread-safety problemləri ola bilər
public class YanlışSingleton {
    private static YanlışSingleton nüsxə;

    private YanlışSingleton() { }

    public static YanlışSingleton getNüsxə() {
        if (nüsxə == null) { // Thread-safe deyil!
            nüsxə = new YanlışSingleton();
        }
        return nüsxə;
    }
}

// Enum Singleton — ən güvənli yol
// ✓ Thread-safe (JVM zəmanət verir)
// ✓ Serializasiyaya davamlı (reflection ilə birdən çox yaratmaq olmaz)
// ✓ Sadə

public enum Konfiqurasiya {
    NÜSXƏ; // Yalnız bir dəyər — Singleton!

    // Sahələr
    private String verilənlərBazasıUrl;
    private int serverPort;
    private boolean debugRejimi;
    private final Map<String, String> xüsusiParametrlər = new HashMap<>();

    // Enum-un "constructor"-u — yalnız bir dəfə çağırılır
    Konfiqurasiya() {
        // Sistem xüsusiyyətlərindən yüklə
        this.verilənlərBazasıUrl = System.getProperty("db.url", "localhost:5432");
        this.serverPort = Integer.parseInt(System.getProperty("server.port", "8080"));
        this.debugRejimi = Boolean.parseBoolean(System.getProperty("debug", "false"));
    }

    // Metodlar
    public String getVərilənlərBazasıUrl() { return verilənlərBazasıUrl; }
    public int getServerPort() { return serverPort; }
    public boolean isDebugRejimi() { return debugRejimi; }

    public void setVərilənlərBazasıUrl(String url) {
        this.verilənlərBazasıUrl = url;
    }

    public void parametr_əlavə_et(String açar, String dəyər) {
        xüsusiParametrlər.put(açar, dəyər);
    }

    public Optional<String> getParametr(String açar) {
        return Optional.ofNullable(xüsusiParametrlər.get(açar));
    }

    @Override
    public String toString() {
        return "Konfiqurasiya{url=%s, port=%d, debug=%b}"
               .formatted(verilənlərBazasıUrl, serverPort, debugRejimi);
    }
}

// İstifadə:
Konfiqurasiya konfiq = Konfiqurasiya.NÜSXƏ;
System.out.println(konfiq.getServerPort()); // 8080

Konfiqurasiya.NÜSXƏ.setVərilənlərBazasıUrl("prod.db.com:5432");
System.out.println(Konfiqurasiya.NÜSXƏ.getVərilənlərBazasıUrl()); // prod.db.com:5432

// Hər yerdən eyni nüsxəyə müraciət:
assert Konfiqurasiya.NÜSXƏ == Konfiqurasiya.NÜSXƏ; // həmişə true
```

---

## Praktiki nümunələr

### State Machine ilə Enum

```java
public enum SifarişStatusu {
    GÖZLƏNILIR {
        @Override
        public Set<SifarişStatusu> mümkünKeçidlər() {
            return EnumSet.of(TƏSDİQLƏNDİ, LƏĞV_EDİLDİ);
        }
    },
    TƏSDİQLƏNDİ {
        @Override
        public Set<SifarişStatusu> mümkünKeçidlər() {
            return EnumSet.of(GÖNDƏRİLDİ, LƏĞV_EDİLDİ);
        }
    },
    GÖNDƏRİLDİ {
        @Override
        public Set<SifarişStatusu> mümkünKeçidlər() {
            return EnumSet.of(ÇATDIRILDI);
        }
    },
    ÇATDIRILDI {
        @Override
        public Set<SifarişStatusu> mümkünKeçidlər() {
            return EnumSet.noneOf(SifarişStatusu.class); // Son status
        }
    },
    LƏĞV_EDİLDİ {
        @Override
        public Set<SifarişStatusu> mümkünKeçidlər() {
            return EnumSet.noneOf(SifarişStatusu.class); // Son status
        }
    };

    public abstract Set<SifarişStatusu> mümkünKeçidlər();

    public boolean keçidMümkündür(SifarişStatusu hədəf) {
        return mümkünKeçidlər().contains(hədəf);
    }

    public SifarişStatusu keç(SifarişStatusu hədəf) {
        if (!keçidMümkündür(hədəf)) {
            throw new IllegalStateException(
                this + " → " + hədəf + " keçidi mümkün deyil"
            );
        }
        return hədəf;
    }
}

// İstifadə:
SifarişStatusu status = SifarişStatusu.GÖZLƏNILIR;
status = status.keç(SifarişStatusu.TƏSDİQLƏNDİ);  // OK
status = status.keç(SifarişStatusu.GÖNDƏRİLDİ);   // OK
// status.keç(SifarişStatusu.GÖZLƏNILIR); // Exception!
```

---

## İntervyu Sualları

**S1: Enum-u Java-da niyə istifadə edirik?**
> 1. Type-safety — yanlış dəyər ötürmək mümkün deyil, 2. Compiler yoxlaması — switch-də bütün case-lər əhatə edildimi?, 3. Okunabilirlik — `Status.ACTIVE` vs `1`, 4. Metodlar və sahələr əlavə etmək imkanı, 5. Singleton pattern üçün ən güvənli yol.

**S2: Enum-u `new` açar sözü ilə yaratmaq olarmı?**
> Xeyr. Enum-un constructor-u `private`-dır, kənardan çağırıla bilməz. Enum sabitlərini yaratmaq yalnız enum deklarasiyasının içindədir.

**S3: `ordinal()` metodundan asılı olan kod niyə tövsiyə edilmir?**
> Enum-a yeni sabit əlavə etdikdə, mövcud sabitlərin `ordinal()` dəyərləri dəyişə bilər (sıra pozulur). Verilənlər bazasında `ordinal()` saxlamaq, sonradan yeni enum əlavə etdikdə uyğunsuzluğa gətirir. Bunun əvəzinə ayrıca `int kod` sahəsi istifadə et.

**S4: EnumMap və EnumSet niyə HashMap/HashSet-dən daha performanslıdır?**
> `EnumMap` daxili olaraq sadə array istifadə edir — indeks enum-un `ordinal()`-ıdır. `EnumSet` isə 64-ə qədər element üçün tək `long` bit-mask istifadə edir. Hashing əməliyyatı lazım deyil — O(1) insert/lookup.

**S5: Enum miras ala bilərmi?**
> Xeyr. Bütün enum-lar dolayısı ilə `java.lang.Enum<E>` sinfini miras alır. Java-da çoxlu miras olmadığı üçün, enum başqa sinifdən miras ala bilməz. Lakin enum **interface implement edə bilər**.

**S6: Enum ilə Singleton yaratmaq niyə ən yaxşı üsuldur?**
> 1. **Thread-safe**: JVM enum-ları yalnız bir dəfə yaradır, 2. **Serializasiyaya davamlı**: `readObject()` yeni nüsxə yaratmır, 3. **Reflection-a davamlı**: `Constructor.newInstance()` `IllegalArgumentException` atır, 4. **Sadəlik**: bir sətirdə Singleton.

**S7: Enum switch statement-dən nə üstünlüyü var?**
> Java 14+ switch expression-da enum istifadə edərkən, compiler bütün enum sabitlərinin əhatə edildiyini yoxlayır. `default` olmadan, unudulmuş case olsa **kompilyasiya xətası** verir. Bu, `int` sabiti ilə mümkün deyil.

**S8: Enum-un abstract metodu ola bilərmi?**
> Bəli. Abstract metod olan enum-da hər sabit həmin metodu implement etməlidir (anonim sinif kimi). Bu, Strategy pattern-in enum ilə realizasiyasıdır.
