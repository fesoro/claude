# 023 — Java-da İnterfeyslər (Interfaces)
**Səviyyə:** Orta


## Mündəricat
1. [Interface nədir?](#interface-nədir)
2. [Interface əsas sintaksis](#interface-əsas-sintaksis)
3. [Default metodlar (Java 8+)](#default-metodlar-java-8)
4. [Static metodlar (Java 8+)](#static-metodlar-java-8)
5. [Private metodlar (Java 9+)](#private-metodlar-java-9)
6. [Functional Interface (@FunctionalInterface)](#functional-interface-functionalinterface)
7. [Marker Interface](#marker-interface)
8. [Multiple Interface implementation](#multiple-interface-implementation)
9. [Interface vs Abstract Class](#interface-vs-abstract-class)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Interface nədir?

**Interface** — bir müqavilə (contract) müəyyən edir: "Bu interface-i implement edən hər sinif bu metodları təmin etməlidir."

Real dünya analoqu: Elektrik prizası — müəyyən standart interface-dir. İstənilən cihaz (telefon, kompüter, lampa) bu standartı "implement" edərək şəbəkəyə qoşula bilər.

```java
// Interface — yalnız "nə etmək lazımdır" deyir, "necə" deyil
public interface Ödəniş {
    boolean ödəniş_et(double mebleg);
    String statusu_al();
}

// Sinif interface-i "implement" edir
public class KreditKart implements Ödəniş {
    @Override
    public boolean ödəniş_et(double mebleg) {
        // Kredit kartı ilə ödəniş məntiqi
        return true;
    }

    @Override
    public String statusu_al() {
        return "Kredit kartı aktiv";
    }
}

public class PayPal implements Ödəniş {
    @Override
    public boolean ödəniş_et(double mebleg) {
        // PayPal ödəniş məntiqi
        return true;
    }

    @Override
    public String statusu_al() {
        return "PayPal hesabı aktiv";
    }
}
```

---

## Interface əsas sintaksis

```java
public interface Nəqliyyat {

    // 1. Sabitlər — interface-də bütün dəyişənlər public static final-dir
    int MAX_SÜRƏT = 300; // avtomatik: public static final int MAX_SÜRƏT = 300;

    // 2. Abstract metodlar — public abstract (yazmasaq da belədir)
    void sür(int sürət);
    void dayan();
    String markasını_al();

    // 3. Default metod (Java 8+)
    default String məlumat() {
        return "Nəqliyyat vasitəsi, maks sürət: " + MAX_SÜRƏT;
    }

    // 4. Static metod (Java 8+)
    static Nəqliyyat boşNəqliyyat() {
        return new Nəqliyyat() {
            @Override public void sür(int sürət) {}
            @Override public void dayan() {}
            @Override public String markasını_al() { return "Naməlum"; }
        };
    }
}
```

### Interface-in xüsusiyyətləri

```java
// YANLIŞ: interface-in obyektini birbaşa yaratmaq olmaz
// Nəqliyyat n = new Nəqliyyat(); // XƏTA!

// DOĞRU: interface tipi ilə istinad, amma konkret sinifin obyekti
Nəqliyyat n = new Avtomobil("BMW"); // Polimorfizm!

// YANLIŞ: interface-də state (vəziyyət) saxlamaq olmaz
// private int sürət = 0; // XƏTA!

// Interface üzvlərinin avtomatik modifikatorları:
// - sahələr: public static final
// - metodlar: public abstract (default/static/private xaricindəkilər)
```

---

## Default metodlar (Java 8+)

Default metodlar interface-ə "geri uyğun" (backward-compatible) funksionallıq əlavə etməyə imkan verir.

```java
public interface Kolleksiya<T> {
    void əlavə_et(T element);
    boolean mövcuddur(T element);
    int ölçü();

    // Default metod — implementasiya lazım deyil, amma override edilə bilər
    default boolean boşdur() {
        return ölçü() == 0; // digər abstract metoddan istifadə edir
    }

    default void əlavə_et_əgər_yoxdursa(T element) {
        if (!mövcuddur(element)) {
            əlavə_et(element);
        }
    }
}

// İmplementasiya sinifinin seçimləri:
public class MənimlKolleksiya<T> implements Kolleksiya<T> {
    private List<T> daxili = new ArrayList<>();

    @Override
    public void əlavə_et(T element) { daxili.add(element); }

    @Override
    public boolean mövcuddur(T element) { return daxili.contains(element); }

    @Override
    public int ölçü() { return daxili.size(); }

    // boşdur() override etmədik — default implementasiya işlədilir
    // əlavə_et_əgər_yoxdursa() da default olaraq qalır
}
```

### Diamond problem — default metodlarda konflikt

```java
public interface A {
    default String salam() {
        return "Salam A-dan";
    }
}

public interface B {
    default String salam() {
        return "Salam B-dən";
    }
}

// YANLIŞ: iki interface-in eyni default metodu — konflikt!
public class C implements A, B {
    // Bu şəkildə buraxsaq XƏTA olacaq: "C inherits unrelated defaults"
}

// DOĞRU: açıq şəkildə override et
public class C implements A, B {
    @Override
    public String salam() {
        return A.super.salam() + " və " + B.super.salam(); // hər ikisini çağır
    }
}
```

---

## Static metodlar (Java 8+)

Interface-dəki static metodlar miras alınmır — yalnız interface adı ilə çağırılır.

```java
public interface Doğrulayıcı<T> {
    boolean doğrula(T dəyər);

    // Factory metod — static
    static Doğrulayıcı<String> emailDoğrulayıcısı() {
        return email -> email != null && email.contains("@") && email.contains(".");
    }

    static Doğrulayıcı<Integer> yaşDoğrulayıcısı() {
        return yaş -> yaş >= 0 && yaş <= 150;
    }

    // Bir neçə doğrulayıcını birləşdir
    static <T> Doğrulayıcı<T> hamısı(Doğrulayıcı<T>... doğrulayıcılar) {
        return dəyər -> {
            for (Doğrulayıcı<T> d : doğrulayıcılar) {
                if (!d.doğrula(dəyər)) return false;
            }
            return true;
        };
    }
}

// İstifadə:
Doğrulayıcı<String> emailD = Doğrulayıcı.emailDoğrulayıcısı();
System.out.println(emailD.doğrula("user@example.com")); // true
System.out.println(emailD.doğrula("invalid-email"));    // false
```

---

## Private metodlar (Java 9+)

Interface-dəki default metodlar arasında kod təkrarını azaltmaq üçün private metodlardan istifadə edilir.

```java
public interface Jurnal {
    void məlumat_yaz(String mesaj);
    void xəta_yaz(String mesaj);

    default void əməliyyat_yaz(String ad, Runnable əməliyyat) {
        jurnal_başlat(ad);       // private metoddan istifadə
        try {
            əməliyyat.run();
            jurnal_bitir(ad, true);
        } catch (Exception e) {
            jurnal_bitir(ad, false);
            xəta_yaz("Xəta: " + e.getMessage());
        }
    }

    // Private metod — yalnız bu interface daxilindən çağırılır
    private void jurnal_başlat(String əməliyyatAdı) {
        məlumat_yaz("[BAŞLADI] " + əməliyyatAdı + " @ " + System.currentTimeMillis());
    }

    private void jurnal_bitir(String əməliyyatAdı, boolean uğurlu) {
        String status = uğurlu ? "[UĞURLU]" : "[UĞURSUZ]";
        məlumat_yaz(status + " " + əməliyyatAdı);
    }

    // Private static metod (Java 9+)
    private static String zaman_damğası() {
        return java.time.LocalDateTime.now().toString();
    }
}
```

---

## Functional Interface (@FunctionalInterface)

**Functional interface** — yalnız **bir** abstract metodlu interface-dir. Lambda ifadələri ilə istifadə edilir.

```java
// @FunctionalInterface annotasiyası — kompilyator yoxlayır
@FunctionalInterface
public interface Hesablama {
    double hesabla(double a, double b);

    // Default və static metodlar olsa da, yalnız 1 abstract metod
    default Hesablama andanSonra(Hesablama digər) {
        return (a, b) -> digər.hesabla(this.hesabla(a, b), 0);
    }
}

// İstifadə — lambda ilə:
Hesablama cəm = (a, b) -> a + b;
Hesablama fərq = (a, b) -> a - b;
Hesablama vurma = (a, b) -> a * b;
Hesablama bölmə = (a, b) -> b != 0 ? a / b : 0;

System.out.println(cəm.hesabla(10, 5));   // 15.0
System.out.println(fərq.hesabla(10, 5));  // 5.0
System.out.println(vurma.hesabla(10, 5)); // 50.0
```

### Java-nın daxili Functional Interface-ləri

```java
import java.util.function.*;

// Function<T, R> — T alır, R qaytarır
Function<String, Integer> uzunluq = String::length;
Function<String, String> böyük = String::toUpperCase;
Function<String, String> birləşik = uzunluq.andThen(n -> "Uzunluq: " + n);

// Predicate<T> — T alır, boolean qaytarır
Predicate<String> boşdur = String::isEmpty;
Predicate<String> uzundur = s -> s.length() > 10;
Predicate<String> boşDeylUzundur = boşdur.negate().and(uzundur);

// Consumer<T> — T alır, heç nə qaytarmır
Consumer<String> çap = System.out::println;
Consumer<String> logla = s -> System.err.println("LOG: " + s);
Consumer<String> ikisi = çap.andThen(logla);

// Supplier<T> — heç nə almır, T qaytarır
Supplier<List<String>> yeniSiyahı = ArrayList::new;
Supplier<String> uuidVer = () -> java.util.UUID.randomUUID().toString();

// BiFunction<T, U, R> — T və U alır, R qaytarır
BiFunction<String, Integer, String> təkrar = String::repeat;
System.out.println(təkrar.apply("Salam ", 3)); // "Salam Salam Salam "

// UnaryOperator<T> — Function<T,T> — eyni tip
UnaryOperator<String> trimle = String::trim;
UnaryOperator<Integer> ikiqat = n -> n * 2;

// BinaryOperator<T> — BiFunction<T,T,T>
BinaryOperator<Integer> topla = Integer::sum;
BinaryOperator<String> birləşdir = String::concat;
```

### Kompozisiya (Function composition)

```java
Function<String, String> addra_gəl = s -> s.trim();
Function<String, String> böyüklüyə = String::toUpperCase;
Function<String, String> nida_əlavə = s -> s + "!";

// andThen: soldan sağa icra
Function<String, String> emal = addra_gəl
    .andThen(böyüklüyə)
    .andThen(nida_əlavə);

System.out.println(emal.apply("  salam dünya  ")); // "SALAM DÜNYA!"

// compose: sağdan sola icra (əksi)
Function<String, String> emal2 = nida_əlavə.compose(böyüklüyə);
System.out.println(emal2.apply("salam")); // "SALAM!"
```

---

## Marker Interface

**Marker interface** — heç bir metodu olmayan interface. Yalnız "etiket" kimi istifadə edilir.

```java
// Java-nın daxili marker interface-ləri:
// java.io.Serializable — obyekti serializasiya etmək olur
// java.lang.Cloneable — clone() metodunu çağırmaq olur
// java.util.RandomAccess — List elementinə sürətli müraciət mümkündür

// Serializable nümunəsi:
import java.io.*;

public class İstifadəçi implements Serializable {
    // serialVersionUID — versiya uyğunluğu üçün
    private static final long serialVersionUID = 1L;

    private String ad;
    private String email;
    // transient — serializasiyadan xaric
    private transient String şifrə;

    // ...
}

// Öz marker interface-imiz:
public interface Auditable {
    // Heç bir metod yoxdur
    // Yalnız "bu sinif audit olunmalıdır" deyir
}

public class SilinmisBankEmeliyyati implements Auditable {
    private double məbləğ;
    private String səbəb;
    // ...
}

// İstifadə:
void emal_et(Object obj) {
    if (obj instanceof Auditable) {
        // Audit jurnalına yaz
        System.out.println("Audit: " + obj.getClass().getSimpleName());
    }
}
```

> **Qeyd:** Müasir Java-da marker interface-lərin əvəzinə **annotasiyalar** daha çox istifadə edilir (`@Auditable`, `@JsonSerializable` və s.).

---

## Multiple Interface implementation

Java-da sinif yalnız bir sinifdən miras ala bilər, amma bir neçə interface implement edə bilər.

```java
public interface Uçan {
    void uç(int hündürlük);
    default String növü() { return "Uçan obyekt"; }
}

public interface Üzən {
    void üz(int dərinlik);
    default String növü() { return "Üzən obyekt"; }
}

public interface Qaçan {
    void qaç(int sürət);
}

// Ördək həm uçur, həm üzür, həm qaçır
public class Ördək implements Uçan, Üzən, Qaçan {

    @Override
    public void uç(int hündürlük) {
        System.out.println("Ördək " + hündürlük + "m yüksəklikdə uçur");
    }

    @Override
    public void üz(int dərinlik) {
        System.out.println("Ördək " + dərinlik + "m dərinlikdə üzür");
    }

    @Override
    public void qaç(int sürət) {
        System.out.println("Ördək " + sürət + "km/s qaçır");
    }

    // İki interface-dən eyni default metod — MƏCBURI override et!
    @Override
    public String növü() {
        return Uçan.super.növü() + " + " + Üzən.super.növü();
    }
}

// İstifadə — polimorfizm:
Uçan uçan = new Ördək();
uçan.uç(100);

Üzən üzən = new Ördək();
üzən.üz(5);

// Interface siyahısı ilə işlə
List<Uçan> uçanlar = List.of(new Ördək(), new Qartal());
uçanlar.forEach(u -> u.uç(500));
```

---

## Interface vs Abstract Class

| Meyar | Interface | Abstract Class |
|---|---|---|
| **Çoxlu miras** | Bəli (implements A, B, C) | Xeyr (yalnız bir extends) |
| **Constructor** | Yoxdur | Var |
| **State (sahə)** | Yalnız `public static final` | İstənilən |
| **Access modifiers** | Yalnız `public` (metodlar) | İstənilən |
| **Default implementasiya** | `default` ilə (Java 8+) | Hər zaman mümkün |
| **İstifadə məqsədi** | Müqavilə (contract) | Ümumi baza |
| **"is-a" vs "can-do"** | "can-do" (bacarıq) | "is-a" (növ) |

```java
// Abstract class — "is-a" münasibəti
// Hər Heyvan bir canlıdır
abstract class Heyvan {
    protected String ad;
    protected int yaş;

    public Heyvan(String ad, int yaş) {
        this.ad = ad;
        this.yaş = yaş;
    }

    public abstract void səsVer(); // alt sinif implement etməlidir

    public void ye() { // ümumi implementasiya
        System.out.println(ad + " yeyir");
    }
}

// Interface — "can-do" münasibəti
// Hər şey uça, üzə, qaça bilər — Heyvan olması şərt deyil
interface Uçabilən {
    void uç();
}

// Heyvan olan VƏ uça bilən
class Qartal extends Heyvan implements Uçabilən {
    public Qartal(String ad) {
        super(ad, 5);
    }

    @Override
    public void səsVer() {
        System.out.println(ad + ": Qarr!");
    }

    @Override
    public void uç() {
        System.out.println(ad + " göyə qalxır");
    }
}

// Heyvan olmayan, amma uça bilən
class Təyyarə implements Uçabilən {
    @Override
    public void uç() {
        System.out.println("Təyyarə uçuşa keçdi");
    }
}
```

### Nə zaman hansını seçmək lazımdır?

```
Interface seç əgər:
✓ Əlaqəsiz siniflər üçün ümumi davranış lazımdır
✓ Çoxlu miras lazımdır
✓ API müqaviləsi müəyyən etmək istəyirsən
✓ Funksional proqramlaşdırma (lambda) istifadə edəcəksən

Abstract class seç əgər:
✓ Sinif hierarxiyasında ümumi kod paylaşmaq lazımdır
✓ Constructor məntiqi lazımdır
✓ protected/package-private üzvlər lazımdır
✓ State (mutable sahələr) lazımdır
✓ Alt siniflərin hamısı üçün ortaq implementasiya var
```

---

## İntervyu Sualları

**S1: Interface-də constructor ola bilərmi?**
> Xeyr. Interface-in öz obyekti yaradılmır, ona görə constructor-a ehtiyac yoxdur.

**S2: Java 8-dən əvvəl interface-ə yeni metod əlavə etmək nə üçün çətin idi?**
> Əgər mövcud interface-ə yeni abstract metod əlavə etsən, onu implement edən bütün sinifləri də dəyişmək lazım gəlirdi. Java 8-dəki `default` metodlar bu problemi həll etdi.

**S3: Functional interface-in neçə abstract metodu ola bilər?**
> Yalnız **bir**. `Object` sinfindən miras alınan metodlar (`equals`, `hashCode`, `toString`) sayılmır. Default və static metodlar da sayılmır.

**S4: `Serializable` interface-i niyə metodsuz interface-dir?**
> O, yalnız JVM-ə "bu sinifin obyektlərini serializasiya et" siqnalı verir. Serializasiya məntiqi JVM-in özündədir, sinifdə deyil.

**S5: Interface-dəki sabit (constant) hansı modifikatorlara malikdir?**
> Avtomatik olaraq `public static final`. Siz yalnız `int X = 5;` yazsanız belə, compiler `public static final int X = 5;` kimi qəbul edir.

**S6: İki interface-in eyni imzalı default metodu varsa nə baş verir?**
> Kompilyasiya xətası. Implement edən sinif mütləq həmin metodu override etməlidir. Hər interface-in versiyasına `InterfaceAdı.super.metod()` ilə müraciət edilə bilər.

**S7: `@FunctionalInterface` annotasiyası nədir?**
> Bu annotasiya kompilyatora deyir: "Bu interface yalnız bir abstract metoda malik olmalıdır." Əgər birindən çox olsa, kompilyasiya xətası verir. Bu annotasiya olmasa da, bir abstract metodlu hər interface funksional interface kimi işləyə bilər.

**S8: Interface-in static metodu miras alınırmı?**
> Xeyr. Interface-in static metodları yalnız həmin interface adı ilə çağırılır: `InterfaceAdı.staticMetod()`. Alt sinif onu `override` edə bilməz.
