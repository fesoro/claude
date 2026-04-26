# 21 — Java-da Miras (Inheritance)

> **Seviyye:** Junior ⭐


## Mündəricat
1. [Miras nədir?](#miras-nədir)
2. [extends açar sözü](#extends-açar-sözü)
3. [super() çağırışı](#super-çağırışı)
4. [Method overriding (@Override)](#method-overriding-override)
5. [Covariant return types](#covariant-return-types)
6. [Constructor chaining](#constructor-chaining)
7. [final class və final method](#final-class-və-final-method)
8. [Object sinfi — kök](#object-sinfi--kök)
9. [instanceof operatoru](#instanceof-operatoru)
10. [Mirasın tələləri](#mirasın-tələləri)
11. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Miras nədir?

**Miras (Inheritance)** — bir sinifin başqa sinifin sahələrini (fields) və metodlarını öz üzərinə götürməsi mexanizmidir. "is-a" münasibətini ifadə edir.

```
Object
  └── Heyvan
        ├── Məməli
        │     ├── İt
        │     └── Pişik
        └── Quş
              ├── Qartal
              └── Pinqvin
```

```java
// Baza (parent/super) sinif
public class Heyvan {
    private String ad;
    private int yaş;

    public Heyvan(String ad, int yaş) {
        this.ad = ad;
        this.yaş = yaş;
    }

    public void ye() {
        System.out.println(ad + " yeyir");
    }

    public String getAd() { return ad; }
    public int getYaş() { return yaş; }
}

// Törəmə (child/sub) sinif
public class İt extends Heyvan {
    private String cins;

    public İt(String ad, int yaş, String cins) {
        super(ad, yaş); // Baza sinifin constructor-u
        this.cins = cins;
    }

    public void hürə() {
        System.out.println(getAd() + ": Hav-hav!");
    }
}

// İstifadə:
İt it = new İt("Rex", 3, "Alman Çoban İti");
it.ye();    // Heyvan sinifindən miras alındı
it.hürə();  // İt sinifinin öz metodu
```

---

## extends açar sözü

Java-da yalnız **bir** sinifin `extends` edilməsinə icazə verilir (single inheritance):

```java
// YANLIŞ: çoxlu miras (multiple inheritance) olmaz
// public class Körpük extends Maşın, Gəmi { } // XƏTA!

// DOĞRU: yalnız bir sinif
public class Körpük extends Nəqliyyat { }

// Lakin interface-ləri çoxaltmaq olar:
public class Amfibiya extends Nəqliyyat implements Üzən, Uçan { }
```

### Miras zənciri (Inheritance chain)

```java
public class A {
    public void metodA() { System.out.println("A"); }
}

public class B extends A {
    public void metodB() { System.out.println("B"); }
}

public class C extends B {
    public void metodC() { System.out.println("C"); }
}

// C sinifi A və B-nin bütün public/protected metodlarına malikdir
C obj = new C();
obj.metodA(); // A-dan miras
obj.metodB(); // B-dən miras
obj.metodC(); // Özünün
```

---

## super() çağırışı

`super` açar sözü baza sinifin üzvlərinə (constructor, metodlar, sahələr) müraciət etmək üçün istifadə edilir.

```java
public class Nəqliyyat {
    protected String marka;
    protected int il;
    protected int sürət;

    public Nəqliyyat(String marka, int il) {
        this.marka = marka;
        this.il = il;
        this.sürət = 0;
        System.out.println("Nəqliyyat yaradıldı: " + marka);
    }

    public void sürətiArtır(int qədər) {
        sürət += qədər;
        System.out.println(marka + " sürəti: " + sürət + " km/h");
    }

    public String məlumat() {
        return marka + " (" + il + "), Sürət: " + sürət;
    }
}

public class Elektrik_Avtomobil extends Nəqliyyat {
    private int batareyaSəviyyəsi;
    private int menzil; // km

    public Elektrik_Avtomobil(String marka, int il, int batareyaSəviyyəsi, int menzil) {
        super(marka, il); // MÜTLƏQ ilk sətir olmalıdır!
        this.batareyaSəviyyəsi = batareyaSəviyyəsi;
        this.menzil = menzil;
        System.out.println("Elektrik avtomobil yaradıldı");
    }

    @Override
    public void sürətiArtır(int qədər) {
        // Elektrik avtomobil — ani sürətlənmə
        super.sürətiArtır(qədər * 2); // baza metodunu çağırıb dəyişdir
        System.out.println("  (Ani elektrik sürətlənməsi!)");
    }

    @Override
    public String məlumat() {
        // Baza sinifin məlumatını genişləndir
        return super.məlumat() +
               ", Batareya: " + batareyaSəviyyəsi + "%" +
               ", Mənzil: " + menzil + " km";
    }
}

// Test:
Elektrik_Avtomobil tesla = new Elektrik_Avtomobil("Tesla Model 3", 2023, 90, 450);
// Çap: "Nəqliyyat yaradıldı: Tesla Model 3"
// Çap: "Elektrik avtomobil yaradıldı"

tesla.sürətiArtır(50);
// Çap: "Tesla Model 3 sürəti: 100 km/h"  (50*2=100)
// Çap: "  (Ani elektrik sürətlənməsi!)"

System.out.println(tesla.məlumat());
// "Tesla Model 3 (2023), Sürət: 100, Batareya: 90%, Mənzil: 450 km"
```

---

## Method overriding (@Override)

**Overriding** — alt sinifin baza sinifin metodunun eyni imzalı metodunu yenidən yazması.

```java
public class Forma {
    public double sahə() {
        return 0.0; // default implementasiya
    }

    public String rəng() {
        return "Rəngsiz";
    }
}

public class Dairə extends Forma {

    private double radius;

    public Dairə(double radius) {
        this.radius = radius;
    }

    // @Override annotasiyası — kompilyatora deyir: "bu override-dır"
    // Baza sinifdə bu metod mövcud deyilsə XƏTA verir
    @Override
    public double sahə() {
        return Math.PI * radius * radius;
    }

    // rəng() override etmədik — baza sinifinkini istifadə edir
}

// YANLIŞ vs DOĞRU nümunəsi:
public class Düzbucaqlı extends Forma {

    private double en, hündürlük;

    public Düzbucaqlı(double en, double hündürlük) {
        this.en = en;
        this.hündürlük = hündürlük;
    }

    // YANLIŞ: @Override olmadan — typo varsa kompilyator tutmur
    public double sahe() { // "sahə" əvəzinə "sahe" — yeni metod yaranır!
        return en * hündürlük;
    }

    // DOĞRU: @Override ilə — səhv varsa kompilyator xəbər verir
    @Override
    public double sahə() {
        return en * hündürlük;
    }
}
```

### Overriding qaydaları

```java
public class Baza {
    public Number dəyər() { return 42; }
    protected void metod(String s) throws IOException { }
    public static void statikMetod() { } // static metodlar override olunmur!
}

public class Törəmə extends Baza {
    // 1. Return tipi eyni və ya covariant (alt tip) olmalıdır
    @Override
    public Integer dəyər() { return 42; } // Integer, Number-in alt tipidir — OK

    // 2. Access modifier eyni və ya daha geniş olmalıdır
    @Override
    public void metod(String s) throws IOException { } // protected → public — OK

    // 3. Daha məhdud access modifier OLMAZ
    // @Override
    // private void metod(String s) { } // XƏTA! public → private olmaz

    // 4. Daha geniş istisna atıla bilməz
    // @Override
    // public void metod(String s) throws Exception { } // XƏTA! IOException-dan geniş

    // 5. Static metodlar override olunmur — "hiding" baş verir
    public static void statikMetod() { } // Override deyil, hiding!
}
```

---

## Covariant return types

Java 5-dən etibarən override edilmiş metodun return tipi baza metodun return tipinin **alt tipi** (subtype) ola bilər.

```java
public abstract class Heyvanlar {
    // Baza sinif - ümumi tip qaytarır
    public abstract Heyvan yavru_doğur();
}

public class İtler extends Heyvanlar {
    // Covariant return — İt, Heyvan-ın alt tipidir
    @Override
    public İt yavru_doğur() { // Heyvan yerinə İt qaytarırıq
        return new İt("Kiçik Rex", 0, "Qarışıq");
    }
}

// Praktiki fayda — cast etmək lazım deyil:
İtler itler = new İtler();
İt yavru = itler.yavru_doğur(); // Cast lazım deyil!

// Builder pattern-də çox istifadə edilir:
public class BaseBuilder<T extends BaseBuilder<T>> {
    protected String ad;

    @SuppressWarnings("unchecked")
    public T adı(String ad) {
        this.ad = ad;
        return (T) this; // covariant
    }
}

public class İşçiBuilder extends BaseBuilder<İşçiBuilder> {
    private double maaş;

    public İşçiBuilder maaş(double maaş) {
        this.maaş = maaş;
        return this;
    }
}

İşçiBuilder builder = new İşçiBuilder()
    .adı("Anar")    // BaseBuilder-dən, İşçiBuilder qaytarır
    .maaş(3000);    // İşçiBuilder-dən
```

---

## Constructor chaining

```java
public class Şəxs {
    private final String ad;
    private final String soyad;
    private final int yaş;
    private final String email;

    // Tam constructor
    public Şəxs(String ad, String soyad, int yaş, String email) {
        this.ad = ad;
        this.soyad = soyad;
        this.yaş = yaş;
        this.email = email;
        System.out.println("Şəxs yaradıldı: " + ad + " " + soyad);
    }

    // Digər constructor-lara yönləndir
    public Şəxs(String ad, String soyad, int yaş) {
        this(ad, soyad, yaş, "namalum@email.com");
    }

    public Şəxs(String ad, String soyad) {
        this(ad, soyad, 0);
    }
}

public class İşçi extends Şəxs {
    private final String şirkət;
    private final double maaş;

    public İşçi(String ad, String soyad, int yaş, String email,
                String şirkət, double maaş) {
        super(ad, soyad, yaş, email); // baza sinifin constructor-u — İLK SƏTIR
        this.şirkət = şirkət;
        this.maaş = maaş;
    }

    public İşçi(String ad, String soyad, String şirkət) {
        super(ad, soyad); // baza sinifin 2-parametrli constructor-u
        this.şirkət = şirkət;
        this.maaş = 1000.0;
    }
}
```

---

## final class və final method

```java
// final class — extend edilə bilməz
public final class String { ... } // Java-nın String sinifi final-dır

public final class SSNDoğrulayıcı {
    // Bu sinfin alt sinifi yaradıla bilməz
    // Bəzən təhlükəsizlik üçün final istifadə edilir
    public boolean doğrula(String ssn) {
        return ssn.matches("\\d{3}-\\d{2}-\\d{4}");
    }
}

// YANLIŞ: final sinifi extend etmək
// public class XususiSSN extends SSNDoğrulayıcı { } // XƏTA!

// final method — override edilə bilməz
public class Hesab {
    private double balans;

    // final metod — təhlükəsizlik üçün override olunmasın
    public final void pul_yatır(double məbləğ) {
        if (məbləğ <= 0) throw new IllegalArgumentException();
        this.balans += məbləğ;
        audit_yaz(məbləğ); // bu audit dəyişdirilə bilməz
    }

    private void audit_yaz(double məbləğ) {
        // Audit jurnalı — saxtalaşdırılmasın
        System.out.println("Audit: " + məbləğ + " AZN yatırıldı");
    }

    // final deyil — override edilə bilər
    public double faiz_hesabla() {
        return balans * 0.03;
    }
}

public class PremiumHesab extends Hesab {
    // pul_yatır() override edə bilmirik — final-dır

    @Override
    public double faiz_hesabla() { // OK — final deyil
        return super.faiz_hesabla() * 1.5; // 50% əlavə faiz
    }
}
```

---

## Object sinfi — kök

Java-da hər sinif dolayısı ilə `java.lang.Object` sinfini extend edir.

```java
// Bu iki sətir ekvivalentdir:
public class Məhsul { }
public class Məhsul extends Object { }

// Object sinifinin əsas metodları:
public class Object {
    public boolean equals(Object obj) { return this == obj; }
    public int hashCode() { ... }
    public String toString() { return getClass().getName() + "@" + hashCode(); }
    public Class<?> getClass() { ... }
    protected Object clone() throws CloneNotSupportedException { ... }
    protected void finalize() throws Throwable { } // deprecated
    public void wait() throws InterruptedException { ... }
    public void notify() { ... }
    public void notifyAll() { ... }
}

// Object tipi ilə istənilən obyekt saxlamaq olar:
Object obj1 = "Salam";
Object obj2 = 42;
Object obj3 = new ArrayList<>();
Object obj4 = new int[]{1, 2, 3};

// Collections API-nin əsasını Object təşkil edir:
List<Object> hər_şey = new ArrayList<>();
hər_şey.add("mətn");
hər_şey.add(3.14);
hər_şey.add(true);
```

---

## instanceof operatoru

```java
// Klassik istifadə (Java 16-dan əvvəl)
public void növü_göstər(Heyvan h) {
    if (h instanceof İt) {
        İt it = (İt) h; // cast lazımdır
        it.hürə();
    } else if (h instanceof Pişik) {
        Pişik pişik = (Pişik) h;
        pişik.miyavla();
    }
}

// Java 16+ Pattern Matching — daha qısa!
public void növü_göstər(Heyvan h) {
    if (h instanceof İt it) { // avtomatik cast + ad
        it.hürə(); // it artıq İt tipindədir
    } else if (h instanceof Pişik pişik && pişik.yaşı() > 2) {
        pişik.miyavla(); // şərtlə birlikdə
    }
}

// Java 21+ Switch ilə Pattern Matching
public String növü_təsviri(Object obj) {
    return switch (obj) {
        case Integer i   -> "Tam ədəd: " + i;
        case Double d    -> "Kəsr ədəd: " + d;
        case String s    -> "Mətn (uzunluq=%d): %s".formatted(s.length(), s);
        case İt it       -> "İt: " + it.getAd();
        case null        -> "Null dəyər";
        default          -> "Naməlum tip: " + obj.getClass().getSimpleName();
    };
}

// instanceof false qaytaranda:
Object obj = null;
System.out.println(obj instanceof String); // false (null üçün)
System.out.println(obj instanceof Object); // false (null üçün)
```

---

## Mirasın tələləri

### 1. Fragile Base Class problemi

```java
public class Sayac {
    private int sayı = 0;

    public void artır() {
        sayı++;
    }

    public void artırN(int n) {
        for (int i = 0; i < n; i++) {
            artır(); // öz metoduna çağırış
        }
    }

    public int getSayı() { return sayı; }
}

// PROBLEM: alt sinif artır()-ı override edir
public class IzlənənSayac extends Sayac {
    private int ümumicəm = 0;

    @Override
    public void artır() {
        super.artır();
        ümumicəm++;
    }
}

IzlənənSayac s = new IzlənənSayac();
s.artırN(3); // artır() 3 dəfə çağırılır
// ümumicəm = 3 OLACAQ — GÖZLƏNILIR
// Amma baza sinifin artırN() implementasiyası dəyişsə,
// davranış gözlenilmədən dəyişə bilər
```

### 2. Constructor-da override edilmiş metodun çağırılması

```java
public class Valideyn {
    private String ad;

    public Valideyn() {
        başlat(); // PROBLEM: bu override edilmiş metodu çağıra bilər
    }

    public void başlat() {
        this.ad = "Valideyn";
        System.out.println("Valideyn başladı: " + ad);
    }
}

public class Uşaq extends Valideyn {
    private String məlumat;

    public Uşaq() {
        super(); // Valideyn() çağırılır, orada başlat() çağırılır
        this.məlumat = "Uşaq məlumatı";
    }

    @Override
    public void başlat() {
        // XƏTA: məlumat hələ null-dur! Constructor-u tamamlanmayıb!
        System.out.println("Uşaq başladı: " + məlumat); // NullPointerException!
    }
}

// Constructor-da virtual metodları çağırma — ANTİ-PATTERN!
```

### 3. Miras əvəzinə kompozisiya (Favor Composition over Inheritance)

```java
// YANLIŞ: Stack-i ArrayList-dən extend etmək
public class YanlışStack<T> extends ArrayList<T> {
    public void push(T element) { add(element); }
    public T pop() { return remove(size() - 1); }

    // PROBLEM: ArrayList-in bütün metodları açıqdır!
    // stack.add(0, element); // indeksdən əlavə etmək — Stack semantikası pozulur!
    // stack.remove(2); // ortadan silmək — Stack deyil bu!
}

// DOĞRU: Kompozisiya istifadə et
public class DüzgünStack<T> {
    private final List<T> daxili = new ArrayList<>(); // Composition!

    public void push(T element) { daxili.add(element); }

    public T pop() {
        if (isEmpty()) throw new EmptyStackException();
        return daxili.remove(daxili.size() - 1);
    }

    public T peek() {
        if (isEmpty()) throw new EmptyStackException();
        return daxili.get(daxili.size() - 1);
    }

    public boolean isEmpty() { return daxili.isEmpty(); }
    public int ölçü() { return daxili.size(); }
    // Yalnız Stack əməliyyatları açıqdır!
}
```

---

## İntervyu Sualları

**S1: Java-da çoxlu miras (multiple inheritance) niyə yoxdur?**
> Diamond problemi olur: A→B, A→C, D extends B,C — D-nin A-dan hansı versiyası istifadə ediləcək? Java bu problemi interface-lərlə həll edir (interface-lərdə yalnız default metodlarda konflikt yarana bilər, onun da həlli var).

**S2: `super()` constructor çağırışı harada olmalıdır?**
> Constructor-un **mütləq ilk sətri** olmalıdır. Əgər yazılmazsa, Java avtomatik olaraq `super()` (parametrsiz) əlavə edir. Baza sinifdə parametrsiz constructor yoxdursa, kompilyasiya xətası olur.

**S3: Method overriding ilə method hiding fərqi nədir?**
> Instance metodlar override olunur — runtime polimorfizmi işləyir. Static metodlar isə "hide" olunur — hansi metodun çağırılacağı **compile-time**-da müəyyən edilir.

**S4: Constructor-lar miras alınırmı?**
> Xeyr. Constructor-lar miras alınmır. Ancaq `super()` ilə baza sinifin constructor-u çağırıla bilər.

**S5: `@Override` annotasiyası vacibdirmi?**
> Məcburi deyil, amma **tövsiyə edilir**. Bu annotasiya olmadan, metod adında typo olsa (məsələn `equals` əvəzinə `equal`), kompilyator bunu yeni metod kimi qəbul edər, xəta verməz. `@Override` isə kompilyatoru yoxlamağa məcbur edir.

**S6: final sinifi extend etmək üçün alternativ nədir?**
> Kompozisiya (Composition) istifadə et: final sinifin obyektini öz sinifinin sahəsi kimi saxla, metodlarını öz metodlarından çağır (delegation/wrapping).

**S7: Şəxsin özünü `instanceof` ilə yoxlaması nə qaytarır?**
> `this instanceof SinifAdı` həmişə `true` qaytarır (null olmadığı müddətcə). `instanceof` həmçinin miras zəncirini yoxlayır: `İt instanceof Heyvan` — `true`.

**S8: Constructor-da virtual metodları çağırmaq niyə təhlükəlidir?**
> Constructor icra olunarkən, alt sinifin constructor-u hələ tamamlanmayıb. Əgər baza sinifin constructor-u virtual (override edilmiş) metod çağırsa, həmin metod alt sinifin tam başlanmamış vəziyyətinə müraciət edə bilər — NullPointerException və ya gözlənilməz davranışa səbəb olur.
