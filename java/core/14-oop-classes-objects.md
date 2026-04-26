# 14 — Java-da Siniflər və Obyektlər (Classes & Objects)

> **Seviyye:** Junior ⭐


## Mündəricat
1. [Class nədir?](#class-nədir)
2. [Class anatomiyası](#class-anatomiyası)
3. [Fields (Sahələr)](#fields-sahələr)
4. [Constructors (Konstruktorlar)](#constructors-konstruktorlar)
5. [Methods (Metodlar)](#methods-metodlar)
6. [static vs instance üzvlər](#static-vs-instance-üzvlər)
7. [this açar sözü](#this-açar-sözü)
8. [Obyekt yaradılması və lifecycle](#obyekt-yaradılması-və-lifecycle)
9. [equals, hashCode, toString override](#equals-hashcode-tostring-override)
10. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Class nədir?

**Class** — obyektlər üçün şablondur (blueprint). Real dünyada "İnsan" bir sinifsə, "Əli", "Vüsal" həmin sinifin obyektləridir.

```java
// Sinif — şablon
class İnsan {
    String ad;
    int yaş;
}

// Obyektlər — şablondan yaradılan nüsxələr
İnsan ali = new İnsan();
İnsan vusal = new İnsan();
```

---

## Class anatomiyası

Java-da bir class aşağıdakı komponentlərdən ibarət ola bilər:

```java
public class BankHesabi {

    // 1. Sabit (Constant) — dəyişməyən dəyər
    private static final double FAIZ_DERECE = 0.035;

    // 2. Statik sahə — bütün obyektlər üçün ortaq
    private static int umumiHesabSayi = 0;

    // 3. Instance sahəsi — hər obyektə məxsus
    private String hesabNomresi;
    private String sahibAdi;
    private double balans;

    // 4. Statik initializer bloku
    static {
        System.out.println("BankHesabi sinifi yükləndi");
    }

    // 5. Instance initializer bloku
    {
        umumiHesabSayi++;
    }

    // 6. Constructor
    public BankHesabi(String hesabNomresi, String sahibAdi, double ilkinBalans) {
        this.hesabNomresi = hesabNomresi;
        this.sahibAdi = sahibAdi;
        this.balans = ilkinBalans;
    }

    // 7. Instance metodlar
    public void pul_yatir(double mebleg) {
        if (mebleg > 0) {
            this.balans += mebleg;
        }
    }

    // 8. Statik metod
    public static int getUmumiHesabSayi() {
        return umumiHesabSayi;
    }

    // 9. toString override
    @Override
    public String toString() {
        return "BankHesabi{" +
               "hesabNomresi='" + hesabNomresi + '\'' +
               ", sahibAdi='" + sahibAdi + '\'' +
               ", balans=" + balans +
               '}';
    }
}
```

---

## Fields (Sahələr)

### Instance fields — hər obyektə ayrıca məxsusdur

```java
public class Avtomobil {
    // Instance fields — hər avtomobilin öz dəyərləri var
    private String marka;
    private String model;
    private int il;
    private double sürət; // default: 0.0

    // Field initializer ilə default dəyər
    private boolean mühərrikAktiv = false;
    private List<String> sürücülər = new ArrayList<>();
}
```

### Static fields — bütün obyektlər tərəfindən paylaşılır

```java
public class Sayac {
    // Bütün Sayac obyektlərinin sayı — static
    private static int cəmiSayac = 0;

    // Hər obyektin öz nömrəsi — instance
    private final int nömrə;

    public Sayac() {
        cəmiSayac++;             // bütün obyektlər üçün artır
        this.nömrə = cəmiSayac;  // bu obyektin nömrəsi
    }

    public static int getCəmiSayac() {
        return cəmiSayac;
    }

    public int getNömrə() {
        return nömrə;
    }
}

// İstifadə:
Sayac s1 = new Sayac(); // nömrə=1, cəmi=1
Sayac s2 = new Sayac(); // nömrə=2, cəmi=2
Sayac s3 = new Sayac(); // nömrə=3, cəmi=3
System.out.println(Sayac.getCəmiSayac()); // 3
```

---

## Constructors (Konstruktorlar)

Constructor — obyekt yaradıldıqda avtomatik çağırılan xüsusi metoddur. Sinif adı ilə eyni adı daşıyır, return tipi yoxdur.

### Default constructor

```java
public class Məhsul {
    private String ad;
    private double qiymət;

    // Constructor yazılmasa, Java default (parametrsiz) constructor yaradır:
    // public Məhsul() {}
}

Məhsul m = new Məhsul(); // default constructor çağırılır
```

### Constructor overloading (Həddən artıq yükləmə)

```java
public class Tələbə {
    private String ad;
    private String soyad;
    private int kurs;
    private double ortalama;

    // Tam parametrli constructor
    public Tələbə(String ad, String soyad, int kurs, double ortalama) {
        this.ad = ad;
        this.soyad = soyad;
        this.kurs = kurs;
        this.ortalama = ortalama;
    }

    // Ad və soyadla — kurs default 1
    public Tələbə(String ad, String soyad) {
        this(ad, soyad, 1, 0.0); // digər constructor-a yönləndir
    }

    // Yalnız adla
    public Tələbə(String ad) {
        this(ad, "Naməlum"); // yenə yönləndir
    }
}
```

### Copy constructor

```java
public class Nöqtə {
    private double x;
    private double y;

    public Nöqtə(double x, double y) {
        this.x = x;
        this.y = y;
    }

    // Copy constructor — başqa Nöqtədən kopyala
    public Nöqtə(Nöqtə digər) {
        this.x = digər.x;
        this.y = digər.y;
    }
}

Nöqtə original = new Nöqtə(3.0, 4.0);
Nöqtə kopya = new Nöqtə(original); // müstəqil kopya
```

---

## Methods (Metodlar)

### Instance metodlar

```java
public class Dairə {
    private double radius;

    public Dairə(double radius) {
        this.radius = radius;
    }

    // Instance metod — obyektə ehtiyac var
    public double sahə() {
        return Math.PI * radius * radius;
    }

    public double perimetr() {
        return 2 * Math.PI * radius;
    }

    // Metod həm oxuyur, həm dəyişdirir
    public void ikiQatBöyüt() {
        this.radius *= 2;
    }
}
```

### Static metodlar

```java
public class RiyaziYardımcı {

    // Static metod — obyekt yaratmaq lazım deyil
    public static double dairəSahəsi(double radius) {
        return Math.PI * radius * radius;
    }

    public static int maksimum(int a, int b) {
        return a > b ? a : b;
    }

    public static boolean cütSaydır(int n) {
        return n % 2 == 0;
    }
}

// İstifadə — sınıf adı ilə çağır:
double sahə = RiyaziYardımcı.dairəSahəsi(5.0);
int maks = RiyaziYardımcı.maksimum(10, 20);
```

---

## static vs instance üzvlər

| Xüsusiyyət | static | instance |
|---|---|---|
| Yaddaş | Bir dəfə (siniflə) | Hər obyekt üçün ayrı |
| Çağırış | `SinifAdı.üzv` | `obyekt.üzv` |
| `this` istifadəsi | Xeyr | Bəli |
| Digər üzvlərə giriş | Yalnız static | Həm static, həm instance |

```java
public class Nümunə {
    private static int staticDəyişən = 10;
    private int instanceDəyişən = 20;

    // YANLIŞ: static metod instance üzvə birbaşa müraciət edə bilməz
    public static void yanliş() {
        // System.out.println(instanceDəyişən); // XƏTA!
    }

    // DOĞRU: static metod yalnız static üzvlərə müraciət edə bilər
    public static void doğru() {
        System.out.println(staticDəyişən); // OK
    }

    // Instance metod hər ikisinə müraciət edə bilər
    public void instanceMetod() {
        System.out.println(staticDəyişən);    // OK
        System.out.println(instanceDəyişən);  // OK
    }
}
```

---

## this açar sözü

`this` — cari obyektə istinad edir. 4 istifadə yeri var:

```java
public class İşçi {
    private String ad;
    private double maaş;
    private String vəzifə;

    // 1. Field ilə parametr adı eyni olduqda disambiguate etmək üçün
    public İşçi(String ad, double maaş, String vəzifə) {
        this.ad = ad;         // this.ad = field, ad = parametr
        this.maaş = maaş;
        this.vəzifə = vəzifə;
    }

    // 2. Başqa constructor-u çağırmaq üçün (constructor chaining)
    public İşçi(String ad) {
        this(ad, 1000.0, "Ümumi işçi"); // tam constructor-u çağır
    }

    // 3. Metodu cari obyektə ötürmək üçün
    public void qeydiyyatEt(Qeydiyyatçı q) {
        q.qeydiyyatdan_keçir(this); // özünü ötür
    }

    // 4. Method chaining üçün — Builder pattern
    public İşçi adıDəyiş(String yeniAd) {
        this.ad = yeniAd;
        return this; // cari obyekti qaytar
    }

    public İşçi maaşıDəyiş(double yeniMaaş) {
        this.maaş = yeniMaaş;
        return this;
    }
}

// Method chaining nümunəsi:
İşçi işçi = new İşçi("Anar")
    .adıDəyiş("Anar Hüseynov")
    .maaşıDəyiş(2500.0);
```

---

## Obyekt yaradılması və lifecycle

### Obyekt necə yaranır?

```java
// 1. new operatoru ilə (ən çox istifadə olunan üsul)
Avtomobil bmw = new Avtomobil("BMW", "X5", 2023);

// 2. Reflection ilə (framework-lər istifadə edir)
Class<?> sinif = Class.forName("Avtomobil");
Avtomobil obj = (Avtomobil) sinif.getDeclaredConstructor().newInstance();

// 3. clone() ilə
Avtomobil kopya = (Avtomobil) bmw.clone(); // Cloneable implement edilməlidir

// 4. Deserialization ilə (ObjectInputStream)
```

### Obyekt lifecycle

```java
public class ResursYönetimi implements AutoCloseable {
    private final String ad;

    public ResursYönetimi(String ad) {
        this.ad = ad;
        System.out.println(ad + " yaradıldı"); // 1. Yaranma
    }

    public void istifadəEt() {
        System.out.println(ad + " istifadə edilir"); // 2. İstifadə
    }

    @Override
    public void close() {
        System.out.println(ad + " bağlandı"); // 3. Bağlanma
    }

    // finalize — tövsiyə edilmir (Java 9+ deprecated)
    // GC tərəfindən çağırılır, ancaq nə vaxt çağırılacağı bilinmir
    @Override
    @Deprecated
    protected void finalize() {
        System.out.println(ad + " GC tərəfindən təmizləndi");
    }
}

// try-with-resources ilə avtomatik bağlanma
try (ResursYönetimi r = new ResursYönetimi("Fayl")) {
    r.istifadəEt();
} // close() avtomatik çağırılır
```

---

## equals, hashCode, toString override

### toString — obyekti mətn kimi göstər

```java
public class Kitab {
    private String başlıq;
    private String müəllif;
    private int il;
    private double qiymət;

    public Kitab(String başlıq, String müəllif, int il, double qiymət) {
        this.başlıq = başlıq;
        this.müəllif = müəllif;
        this.il = il;
        this.qiymət = qiymət;
    }

    // YANLIŞ: toString-i override etməmək
    // System.out.println(kitab) → "Kitab@1a2b3c" (faydasız)

    // DOĞRU: toString-i override et
    @Override
    public String toString() {
        return "Kitab{" +
               "başlıq='" + başlıq + '\'' +
               ", müəllif='" + müəllif + '\'' +
               ", il=" + il +
               ", qiymət=" + qiymət +
               '}';
    }

    // equals — iki obyektin məzmunca bərabərliyini yoxla
    // YANLIŞ: == operatoru yalnız referansları müqayisə edir
    // DOĞRU: equals() metodunu override et

    @Override
    public boolean equals(Object o) {
        // 1. Özünə bərabərlik yoxlaması
        if (this == o) return true;
        // 2. null yoxlaması
        if (o == null) return false;
        // 3. Tip yoxlaması
        if (getClass() != o.getClass()) return false;
        // 4. Sahələri müqayisə et
        Kitab kitab = (Kitab) o;
        return il == kitab.il &&
               Double.compare(kitab.qiymət, qiymət) == 0 &&
               Objects.equals(başlıq, kitab.başlıq) &&
               Objects.equals(müəllif, kitab.müəllif);
    }

    // hashCode — equals ilə həmişə birlikdə override edilməlidir!
    // Qayda: equals true qaytarırsa, hashCode eyni olmalıdır
    @Override
    public int hashCode() {
        return Objects.hash(başlıq, müəllif, il, qiymət);
    }
}
```

### equals/hashCode kontraktı

```java
// Bu qaydalar mütləq gözlənilməlidir:
Kitab k1 = new Kitab("Java", "Gosling", 2020, 50.0);
Kitab k2 = new Kitab("Java", "Gosling", 2020, 50.0);
Kitab k3 = k1;

// 1. Refleksivlik: x.equals(x) == true
System.out.println(k1.equals(k1)); // true

// 2. Simmetriklik: x.equals(y) == y.equals(x)
System.out.println(k1.equals(k2)); // true
System.out.println(k2.equals(k1)); // true

// 3. Tranzitivlik: x.equals(y) && y.equals(z) → x.equals(z)

// 4. hashCode kontraktı:
System.out.println(k1.hashCode() == k2.hashCode()); // true (equals olduğu üçün)

// HashMap/HashSet düzgün işləməsi üçün bu kontrakt vacibdir!
Set<Kitab> kitablar = new HashSet<>();
kitablar.add(k1);
kitablar.add(k2); // k1 ilə equals olduğu üçün əlavə edilməyəcək
System.out.println(kitablar.size()); // 1
```

### Tam nümunə — İşçi sinifi

```java
public final class İşçi {
    private final int id;
    private final String ad;
    private final String soyad;
    private final String email;
    private double maaş;

    public İşçi(int id, String ad, String soyad, String email, double maaş) {
        if (id <= 0) throw new IllegalArgumentException("ID müsbət olmalıdır");
        if (ad == null || ad.isBlank()) throw new IllegalArgumentException("Ad boş ola bilməz");
        this.id = id;
        this.ad = ad;
        this.soyad = soyad;
        this.email = email;
        this.maaş = maaş;
    }

    // Getters
    public int getId() { return id; }
    public String getAd() { return ad; }
    public String getSoyad() { return soyad; }
    public String getEmail() { return email; }
    public double getMaaş() { return maaş; }
    public void setMaaş(double maaş) {
        if (maaş < 0) throw new IllegalArgumentException("Maaş mənfi ola bilməz");
        this.maaş = maaş;
    }

    public String tamAd() {
        return ad + " " + soyad;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof İşçi işçi)) return false; // Java 16+ pattern matching
        return id == işçi.id; // ID unikal olduğu üçün yalnız ID-yə baxırıq
    }

    @Override
    public int hashCode() {
        return Objects.hash(id);
    }

    @Override
    public String toString() {
        return "İşçi{id=%d, ad='%s %s', email='%s', maaş=%.2f}"
               .formatted(id, ad, soyad, email, maaş);
    }
}
```

---

## İntervyu Sualları

**S1: static metod instance metodunu çağıra bilərmi?**
> Birbaşa xeyr. Static metod instance dəyişənlərinə `this` olmadan müraciət edə bilməz. Ancaq obyekt yaradıb onun üzərindən çağıra bilər.

**S2: Constructor-un return tipi niyə yoxdur?**
> Constructor texniki olaraq metod deyil — o, obyektin yaddaşda yaradılması prosesinin bir hissəsidir. JVM `new` açar sözü ilə yaddaş ayırır, constructor yalnız sahələri başladır.

**S3: equals() override etdikdə niyə hashCode() da override etmək lazımdır?**
> Java-nın kontraktına görə: əgər iki obyekt `equals()` üzrə bərabərdirsə, onların `hashCode()` dəyərləri mütləq eyni olmalıdır. `HashMap` və `HashSet` əvvəlcə `hashCode()` ilə bucket tapır, sonra `equals()` ilə müqayisə edir. Yalnız `equals()` override etsək, `HashSet`-ə eyni obyekti iki dəfə əlavə etmək mümkün olar.

**S4: `this()` constructor çağırışı harada olmalıdır?**
> Constructor-un **ilk sətri** olmalıdır. Ondan əvvəl heç bir kod olmamalıdır.

**S5: Bir class-ın neçə constructor-u ola bilər?**
> İstənilən qədər — parametr sayı və ya tipləri fərqli olduğu müddətcə. Bu **constructor overloading** adlanır.

**S6: `==` ilə `equals()` arasındakı fərq nədir?**
> `==` — referans müqayisəsi edir (eyni yaddaş ünvanına işarə edirmi?). `equals()` — məzmun müqayisəsi edir (dəyərlər bərabərdirmi?). `String s1 = new String("a"); String s2 = new String("a");` — `s1 == s2` false, `s1.equals(s2)` true.

**S7: Object sinifinin hansı metodları var?**
> `equals()`, `hashCode()`, `toString()`, `clone()`, `finalize()`, `getClass()`, `wait()`, `notify()`, `notifyAll()` — hər Java sinifi dolayısı ilə bunları miras alır.

**S8: İmmutable class necə yaradılır?**
> 1. Sinfi `final` et, 2. Bütün sahələri `private final` et, 3. Setter yazma, 4. Mutable obyektlər varsa, copy-lar qaytar, 5. Constructor-da defensive copy al.
