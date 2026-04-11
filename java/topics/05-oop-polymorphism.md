# Java-da Polimorfizm (Polymorphism)

## Mündəricat
1. [Polimorfizm nədir?](#polimorfizm-nədir)
2. [Compile-time polimorfizmi — Method Overloading](#compile-time-polimorfizmi--method-overloading)
3. [Runtime polimorfizmi — Method Overriding](#runtime-polimorfizmi--method-overriding)
4. [Dynamic dispatch](#dynamic-dispatch)
5. [Upcasting və Downcasting](#upcasting-və-downcasting)
6. [ClassCastException](#classcastexception)
7. [Polimorfizm kolleksiyalarda](#polimorfizm-kolleksiyalarda)
8. [Real dünya nümunələri](#real-dünya-nümunələri)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Polimorfizm nədir?

**Polimorfizm** (yunanca: poly=çox, morph=forma) — eyni interfeysə sahib obyektlərin fərqli davranışlar göstərə bilməsidir. "Bir forma, çox davranış."

```
Forma (interface/abstract class)
  ├── Dairə → sahə() = π*r²
  ├── Kvadrat → sahə() = a²
  └── Üçbucaq → sahə() = (a*h)/2

Forma f; // eyni tip
f = new Dairə(5);   → f.sahə() = 78.54
f = new Kvadrat(4); → f.sahə() = 16.0
f = new Üçbucaq(3, 4); → f.sahə() = 6.0
```

Java-da polimorfizmin iki növü:
1. **Compile-time polimorfizmi** — Method Overloading (statik)
2. **Runtime polimorfizmi** — Method Overriding (dinamik)

---

## Compile-time polimorfizmi — Method Overloading

**Overloading** — eyni sinifdə eyni adlı, amma fərqli parametrli metodlar.

```java
public class Hesablayıcı {

    // Tam ədədlər topla
    public int topla(int a, int b) {
        return a + b;
    }

    // Üç tam ədəd topla
    public int topla(int a, int b, int c) {
        return a + b + c;
    }

    // Kəsr ədədlər topla
    public double topla(double a, double b) {
        return a + b;
    }

    // Mətnlər birləşdir (overloading — eyni ad, fərqli tip)
    public String topla(String a, String b) {
        return a + b;
    }

    // Array topla
    public int topla(int... ədədlər) { // varargs
        int cəm = 0;
        for (int n : ədədlər) cəm += n;
        return cəm;
    }
}

// Kompilyator hansı metodu çağıracağını compile-time-da bilir:
Hesablayıcı h = new Hesablayıcı();
h.topla(1, 2);          // topla(int, int)
h.topla(1, 2, 3);       // topla(int, int, int)
h.topla(1.5, 2.5);      // topla(double, double)
h.topla("Salam", "!");  // topla(String, String)
h.topla(1, 2, 3, 4, 5); // topla(int...)
```

### Overloading — incə məqamlar

```java
public class OverloadingNüansları {

    public void metod(int x) {
        System.out.println("int: " + x);
    }

    public void metod(long x) {
        System.out.println("long: " + x);
    }

    public void metod(double x) {
        System.out.println("double: " + x);
    }

    public void metod(Integer x) { // Autoboxing
        System.out.println("Integer: " + x);
    }

    public void metod(Object x) {
        System.out.println("Object: " + x);
    }
}

OverloadingNüansları obj = new OverloadingNüansları();

// Widening (genişlənmə) — kiçik tip böyük tipə avtomatik çevrilir
byte b = 10;
obj.metod(b);    // int çağırılır (byte → int widening)

obj.metod(10);   // int çağırılır (dəqiq uyğunluq)
obj.metod(10L);  // long çağırılır

// YANLIŞ — aşağıdakı overloading olur, override deyil!
// Parametr sayı/tipi fərqlidir, bu overriding deyil:
```

### Overloading qaydaları

```java
// YANLIŞ: yalnız return tipi ilə overload etmək olmaz
public class XetaliSinif {
    public int hesabla(int x) { return x; }
    // public double hesabla(int x) { return x; } // XƏTA! Eyni parametrlər
}

// YANLIŞ: yalnız access modifier ilə overload etmək olmaz
public class XetaliSinif2 {
    public void metod(int x) { }
    // private void metod(int x) { } // XƏTA! Eyni imza
}

// DOĞRU: parametr tipi və ya sayı fərqli olmalıdır
public class DüzgünSinif {
    public void metod(int x) { }
    public void metod(int x, int y) { }    // ✓ parametr sayı
    public void metod(String x) { }        // ✓ parametr tipi
    public void metod(int x, String y) { } // ✓ sıra fərqi
}
```

---

## Runtime polimorfizmi — Method Overriding

**Overriding** — alt sinifin baza sinifin metodunu yenidən implementasiya etməsi. Hansı metodun çağırılacağı **runtime**-da müəyyən edilir.

```java
public abstract class Ödəniş {
    protected double məbləğ;

    public Ödəniş(double məbləğ) {
        this.məbləğ = məbləğ;
    }

    // Bu metod runtime-da override edilmiş versiya ilə əvəz olunacaq
    public abstract boolean emal_et();

    public double getMəbləğ() { return məbləğ; }
}

public class NağdÖdəniş extends Ödəniş {
    public NağdÖdəniş(double məbləğ) {
        super(məbləğ);
    }

    @Override
    public boolean emal_et() {
        System.out.printf("Nağd ödəniş: %.2f AZN%n", məbləğ);
        return true; // həmişə uğurlu
    }
}

public class KartÖdənişi extends Ödəniş {
    private String kartNömrəsi;

    public KartÖdənişi(double məbləğ, String kartNömrəsi) {
        super(məbləğ);
        this.kartNömrəsi = kartNömrəsi;
    }

    @Override
    public boolean emal_et() {
        System.out.printf("Kart ödənişi: %.2f AZN (kart: %s)%n",
                          məbləğ, maskala(kartNömrəsi));
        return kartNömrəsi.length() == 16; // sadəlik üçün
    }

    private String maskala(String nömrə) {
        return "****-****-****-" + nömrə.substring(nömrə.length() - 4);
    }
}

public class KriptоÖdəniş extends Ödəniş {
    private String cüzdan;

    public KriptоÖdəniş(double məbləğ, String cüzdan) {
        super(məbləğ);
        this.cüzdan = cüzdan;
    }

    @Override
    public boolean emal_et() {
        System.out.printf("Kripto ödəniş: %.2f AZN (cüzdan: %s)%n",
                          məbləğ, cüzdan.substring(0, 8) + "...");
        return Math.random() > 0.1; // 90% uğurlu
    }
}

// Runtime polimorfizmi:
public class ÖdənişSistemi {
    public void ödənişiEmal(Ödəniş ödəniş) {
        // Hansı sinif olduğunu bilmirik — runtime-da müəyyən olur!
        boolean uğurlu = ödəniş.emal_et(); // Dynamic dispatch!
        if (uğurlu) {
            System.out.println("✓ Ödəniş qəbul edildi");
        } else {
            System.out.println("✗ Ödəniş rədd edildi");
        }
    }
}
```

---

## Dynamic dispatch

**Dynamic dispatch** (dinamik göndərmə) — runtime-da obyektin **əsl tipinə** görə metodun seçilməsi.

```java
public class DynamicDispatch_Demo {
    public static void main(String[] args) {
        // İstinad tipi: Heyvan
        // Obyektin əsl tipi: İt, Pişik, Quş
        Heyvan[] heyvanlar = {
            new İt("Rex"),
            new Pişik("Mışka"),
            new Quş("Twitti"),
            new İt("Bars"),
        };

        for (Heyvan h : heyvanlar) {
            // Kompilyator yalnız Heyvan.səsVer() görür
            // Amma runtime-da əsl sinifin metodu çağırılır
            h.səsVer(); // Dynamic dispatch!
        }
    }
}

abstract class Heyvan {
    protected String ad;
    public Heyvan(String ad) { this.ad = ad; }
    public abstract void səsVer();
}

class İt extends Heyvan {
    public İt(String ad) { super(ad); }
    @Override public void səsVer() { System.out.println(ad + ": Hav!"); }
}

class Pişik extends Heyvan {
    public Pişik(String ad) { super(ad); }
    @Override public void səsVer() { System.out.println(ad + ": Miyav!"); }
}

class Quş extends Heyvan {
    public Quş(String ad) { super(ad); }
    @Override public void səsVer() { System.out.println(ad + ": Civilt!"); }
}

// Nəticə:
// Rex: Hav!
// Mışka: Miyav!
// Twitti: Civilt!
// Bars: Hav!
```

### Static metodlarda Dynamic dispatch işləmir

```java
public class Baza {
    public static String statik() { return "Baza.statik"; }
    public String dinamik()       { return "Baza.dinamik"; }
}

public class Törəmə extends Baza {
    public static String statik() { return "Törəmə.statik"; } // hiding!
    @Override
    public String dinamik()       { return "Törəmə.dinamik"; } // override
}

// Test:
Baza obj = new Törəmə(); // reference tipi: Baza, əsl tip: Törəmə

System.out.println(obj.statik());  // "Baza.statik" — reference tipinə görə!
System.out.println(obj.dinamik()); // "Törəmə.dinamik" — əsl tipinə görə!
```

---

## Upcasting və Downcasting

```java
// Class hierarchy:
// Object → Heyvan → Məməli → İt

// UPCASTING — alt tipdən üst tipə (implicit, avtomatik)
İt rex = new İt("Rex", 3, "Labrador");
Heyvan h = rex;        // Upcasting: İt → Heyvan (avtomatik)
Object obj = rex;      // Upcasting: İt → Object (avtomatik)
Məməli m = rex;        // Upcasting: İt → Məməli (avtomatik)

// Üst tip istinadı ilə yalnız üst tipinin metodları görünür:
h.ye();        // OK — Heyvan-da var
// h.hürə();   // XƏTA — Heyvan-da yoxdur, compile-time xəta

// DOWNCASTING — üst tipdən alt tipə (explicit, açıq)
Heyvan heyvan = new İt("Bars", 5, "Alsasyen"); // upcasting
İt it = (İt) heyvan; // Downcasting: açıq cast lazımdır
it.hürə(); // artıq İt metodları görünür

// Təhlükəsiz Downcasting — instanceof ilə yoxla
public void emal_et(Heyvan h) {
    if (h instanceof İt it) { // Java 16+ pattern matching
        it.hürə();
    } else if (h instanceof Pişik pişik) {
        pişik.miyavla();
    }
}
```

---

## ClassCastException

```java
// YANLIŞ: uyğun olmayan cast
Heyvan h = new Pişik("Mışka"); // Pişik yaradıldı
İt it = (İt) h; // Runtime xəta: ClassCastException!
// "Pişik" İt-ə cast edilə bilməz

// DOĞRU: əvvəl yoxla
if (h instanceof İt) {
    İt it2 = (İt) h; // təhlükəsiz
}

// Daha yaxşı — Java 16+ Pattern Matching:
if (h instanceof İt it2) {
    it2.hürə(); // cast + yoxlama birlikdə
}

// Real nümunə — Collection-dan çıxarma:
List<Object> qarışıq = new ArrayList<>();
qarışıq.add("Salam");
qarışıq.add(42);
qarışıq.add(new İt("Rex", 3, "Labrador"));

for (Object o : qarışıq) {
    switch (o) {
        case String s    -> System.out.println("Mətn: " + s);
        case Integer i   -> System.out.println("Rəqəm: " + i);
        case İt it       -> System.out.println("İt: " + it.getAd());
        default          -> System.out.println("Digər: " + o);
    }
}
```

---

## Polimorfizm kolleksiyalarda

```java
public interface Forma {
    double sahə();
    double perimetr();
    String ad();
}

public record Dairə(double radius) implements Forma {
    @Override public double sahə() { return Math.PI * radius * radius; }
    @Override public double perimetr() { return 2 * Math.PI * radius; }
    @Override public String ad() { return "Dairə(r=" + radius + ")"; }
}

public record Kvadrat(double tərəf) implements Forma {
    @Override public double sahə() { return tərəf * tərəf; }
    @Override public double perimetr() { return 4 * tərəf; }
    @Override public String ad() { return "Kvadrat(a=" + tərəf + ")"; }
}

public record Üçbucaq(double a, double b, double c) implements Forma {
    @Override
    public double sahə() {
        // Heron düsturu
        double s = (a + b + c) / 2;
        return Math.sqrt(s * (s-a) * (s-b) * (s-c));
    }
    @Override public double perimetr() { return a + b + c; }
    @Override public String ad() { return "Üçbucaq(%s,%s,%s)".formatted(a, b, c); }
}

// Polimorfizm ilə kolleksiya:
public class FormaAnalizatoru {
    public static void analiz_et(List<Forma> formalar) {
        // Ümumi sahə
        double ümumiSahə = formalar.stream()
                                   .mapToDouble(Forma::sahə)
                                   .sum();

        // Ən böyük sahəli forma
        formalar.stream()
                .max(Comparator.comparingDouble(Forma::sahə))
                .ifPresent(f -> System.out.println("Ən böyük: " + f.ad()));

        // Sahəyə görə sırala
        formalar.stream()
                .sorted(Comparator.comparingDouble(Forma::sahə))
                .forEach(f -> System.out.printf("%-20s Sahə: %6.2f%n",
                                                f.ad(), f.sahə()));

        System.out.printf("Ümumi sahə: %.2f%n", ümumiSahə);
    }

    public static void main(String[] args) {
        List<Forma> formalar = List.of(
            new Dairə(5),
            new Kvadrat(4),
            new Üçbucaq(3, 4, 5),
            new Dairə(2),
            new Kvadrat(7)
        );

        analiz_et(formalar); // Hər formalar.sahə() öz implementasiyasını çağırır
    }
}
```

---

## Real dünya nümunələri

### Notification sistemi

```java
public interface Bildiriş {
    void göndər(String alıcı, String başlıq, String məzmun);
    default String formatla(String başlıq, String məzmun) {
        return "[" + başlıq + "]: " + məzmun;
    }
}

public class EmailBildirişi implements Bildiriş {
    @Override
    public void göndər(String alıcı, String başlıq, String məzmun) {
        System.out.printf("📧 Email → %s%n   Mövzu: %s%n   Məzmun: %s%n",
                          alıcı, başlıq, məzmun);
    }
}

public class SMSBildirişi implements Bildiriş {
    @Override
    public void göndər(String alıcı, String başlıq, String məzmun) {
        // SMS-də başlıq yoxdur, qısa məzmun
        String qısaMəzmun = məzmun.length() > 160
                            ? məzmun.substring(0, 157) + "..."
                            : məzmun;
        System.out.printf("📱 SMS → %s: %s%n", alıcı, qısaMəzmun);
    }
}

public class PushBildirişi implements Bildiriş {
    @Override
    public void göndər(String alıcı, String başlıq, String məzmun) {
        System.out.printf("🔔 Push → %s%n   %s%n", alıcı, formatla(başlıq, məzmun));
    }
}

// BildirişMərkəzi — polimorfizmdən istifadə edir
public class BildirişMərkəzi {
    private final List<Bildiriş> kanallar = new ArrayList<>();

    public void kanal_əlavə_et(Bildiriş kanal) {
        kanallar.add(kanal);
    }

    // Bütün kanallara göndər — hansı kanal olduğunu bilmir!
    public void hamısına_göndər(String alıcı, String başlıq, String məzmun) {
        kanallar.forEach(k -> k.göndər(alıcı, başlıq, məzmun));
    }
}

// İstifadə:
BildirişMərkəzi mərkəz = new BildirişMərkəzi();
mərkəz.kanal_əlavə_et(new EmailBildirişi());
mərkəz.kanal_əlavə_et(new SMSBildirişi());
mərkəz.kanal_əlavə_et(new PushBildirişi());

mərkəz.hamısına_göndər("anar@email.com",
                        "Sifariş Təsdiqləndi",
                        "Sifarişiniz #12345 göndərildi");
```

### Strategy pattern ilə polimorfizm

```java
// Sıralama strategiyaları
@FunctionalInterface
public interface SıralamaStrategiyası<T> {
    List<T> sırala(List<T> siyahı, Comparator<T> müqayisəedici);
}

public class BubbleSort<T> implements SıralamaStrategiyası<T> {
    @Override
    public List<T> sırala(List<T> siyahı, Comparator<T> cmp) {
        List<T> nəticə = new ArrayList<>(siyahı);
        int n = nəticə.size();
        for (int i = 0; i < n - 1; i++) {
            for (int j = 0; j < n - i - 1; j++) {
                if (cmp.compare(nəticə.get(j), nəticə.get(j + 1)) > 0) {
                    T temp = nəticə.get(j);
                    nəticə.set(j, nəticə.get(j + 1));
                    nəticə.set(j + 1, temp);
                }
            }
        }
        return nəticə;
    }
}

public class MergeSort<T> implements SıralamaStrategiyası<T> {
    @Override
    public List<T> sırala(List<T> siyahı, Comparator<T> cmp) {
        List<T> kopyalanmış = new ArrayList<>(siyahı);
        kopyalanmış.sort(cmp); // Java-nın daxili merge sort
        return kopyalanmış;
    }
}

public class Sıralayıcı<T> {
    private SıralamaStrategiyası<T> strategiya;

    public Sıralayıcı(SıralamaStrategiyası<T> strategiya) {
        this.strategiya = strategiya;
    }

    public void strategiyanıDəyiş(SıralamaStrategiyası<T> yeni) {
        this.strategiya = yeni; // runtime-da strategiya dəyişdirilə bilər
    }

    public List<T> sırala(List<T> siyahı, Comparator<T> cmp) {
        return strategiya.sırala(siyahı, cmp); // polimorfizm!
    }
}

// İstifadə:
List<Integer> ədədlər = List.of(5, 2, 8, 1, 9, 3);

Sıralayıcı<Integer> sıralayıcı = new Sıralayıcı<>(new BubbleSort<>());
System.out.println(sıralayıcı.sırala(ədədlər, Integer::compare));

// Strategiyanı dəyiş — polimorfizm!
sıralayıcı.strategiyanıDəyiş(new MergeSort<>());
System.out.println(sıralayıcı.sırala(ədədlər, Integer::compare));

// Lambda ilə strategiya:
sıralayıcı.strategiyanıDəyiş((list, cmp) -> {
    return list.stream().sorted(cmp).toList();
});
```

---

## İntervyu Sualları

**S1: Overloading ilə Overriding arasındakı əsas fərq nədir?**
> **Overloading** — eyni sinifdə, eyni adlı, fərqli parametrli metodlar. Compile-time-da həll edilir. **Overriding** — alt sinifdə baza sinifin metodunun yenidən yazılması. Runtime-da həll edilir (dynamic dispatch).

**S2: Static metodlar override oluna bilərmi?**
> Xeyr. Static metodlar sinifə məxsusdur, obyektə yox. Static metodu alt sinifdə eyni adla yazsan "method hiding" baş verir, override deyil. Dynamic dispatch işləmir.

**S3: Dynamic dispatch nədir?**
> JVM-in runtime-da istinad tipinə deyil, **əsl obyekt tipinə** görə metodu seçməsidir. `Heyvan h = new İt();` olduqda `h.səsVer()` çağırılanda `İt.səsVer()` icra olunur.

**S4: Polimorfizmin üstünlükləri nəlardır?**
> 1. **Genişlənə bilən kod** — yeni tip əlavə etdikdə mövcud kodu dəyişmək lazım deyil, 2. **Kod sadəliyi** — müxtəlif tiplərə eyni interfeyslə müraciət, 3. **Loosley coupled** — asılılıqlar azalır, 4. **Test ediləbilirlik** — mock obyektlər üçün ideal.

**S5: Upcasting niyə güvənlidir?**
> Upcasting zamanı alt sinifin obyekti üst tipə çevrilir — alt sinif üst sinifin bütün metodlarına malikdir (miras). Heç nə itirilmir. Ona görə avtomatik, implicit şəkildə baş verir.

**S6: Downcasting niyə risk daşıyır?**
> Üst tipin istinadı hər hansı alt sinfin obyektinə işarə edə bilər. Yanlış alt tipə cast etməyə cəhd etsən, JVM `ClassCastException` atır. Həmişə `instanceof` ilə yoxlamaq lazımdır.

**S7: `List<Object>` ilə `List<?>` fərqi nədir?**
> `List<Object>` yalnız `Object` tipli elementlər qəbul edir — `String` əlavə etmək üçün `List<String>`-dən `List<Object>`-ə çevirmək olmaz. `List<?>` isə wildcard-dır — istənilən tipli list qəbul edir, amma oxuma məqsədi ilə.

**S8: Overloading-da hansı metodun çağırılacağı necə müəyyən edilir?**
> Compiler 3 mərhələdə seçir: 1. Dəqiq uyğunluq (exact match), 2. Widening (kiçik tipin böyüyə çevrilməsi), 3. Autoboxing/varargs. Ən dəqiq uyğun olan seçilir.
