# 17 — `static` və `final` Açar Sözləri

> **Seviyye:** Junior ⭐


## Mündəricat
1. [`static` nədir?](#static-nedir)
2. [Static field-lər (class-level)](#static-fields)
3. [Static metodlar](#static-methods)
4. [Static blok (static initializer)](#static-block)
5. [Nə vaxt `static` istifadə etmək lazımdır?](#ne-vaxt-static)
6. [Yaddaşda static: Method Area / Metaspace](#yaddas)
7. [`final` dəyişənlər](#final-variables)
8. [`final` parametrlər](#final-params)
9. [`final` metodlar](#final-methods)
10. [`final` class-lar](#final-classes)
11. [`static final` — sabit (constant) pattern](#static-final)
12. [Effectively final (Java 8+)](#effectively-final)
13. [Ümumi Səhvlər](#sehvler)
14. [İntervyu Sualları](#intervyu)

---

## 1. `static` nədir? {#static-nedir}

**`static`** — üzvün (field və ya metodun) **class-a** aid olduğunu bildirir, obyektə yox.

Yəni: `static` sahə/metod üçün obyekt yaratmağa ehtiyac yoxdur.

```java
public class Sayac {
    // Instance field — hər obyektin öz nüsxəsi
    private int özAdedi = 0;

    // Static field — BÜTÜN obyektlər bir dəyəri paylaşır
    private static int ümumiAded = 0;

    public Sayac() {
        özAdedi++;
        ümumiAded++;
    }
}

Sayac a = new Sayac(); // özAdedi=1, ümumiAded=1
Sayac b = new Sayac(); // özAdedi=1, ümumiAded=2
Sayac c = new Sayac(); // özAdedi=1, ümumiAded=3
```

### Real analogiya

Təsəvvür et: hər tələbənin öz **jurnal nömrəsi** (instance), amma bütün tələbələrin oxuduğu **məktəbin adı** bir olur (static).

| Aspekt | instance | static |
|---|---|---|
| Kimə aiddir? | Obyektə | Class-a |
| Kopya sayı | Hər obyektə bir | Bütün obyektlərə bir |
| Giriş | `obj.field` | `ClassAdı.field` |
| Obyekt yaratmaq lazımdır? | Bəli | Xeyr |
| `this` istifadə edə bilirmi? | Bəli | Xeyr |

---

## 2. Static field-lər (class-level) {#static-fields}

```java
public class Şirkət {
    // Bütün işçilər üçün eyni şirkət adı
    public static String şirkətAdı = "TechCo";

    // Hər işçinin fərdi məlumatı
    private String ad;
    private double maaş;

    public Şirkət(String ad, double maaş) {
        this.ad = ad;
        this.maaş = maaş;
    }

    public void məlumatÇap() {
        // static field-ə birbaşa müraciət olur (class-ın hissəsidir)
        System.out.println(ad + " — " + şirkətAdı + " — " + maaş);
    }
}

public class Main {
    public static void main(String[] args) {
        Şirkət.şirkətAdı = "NewTech"; // class adı ilə dəyişdir
        new Şirkət("Əli", 1000).məlumatÇap();   // Əli — NewTech — 1000
        new Şirkət("Vüsal", 1500).məlumatÇap(); // Vüsal — NewTech — 1500
    }
}
```

### Static counter pattern

```java
public class İstifadəçi {
    private static int növbətiId = 1; // hər yeni obyekt üçün avtomatik id
    private final int id;
    private final String ad;

    public İstifadəçi(String ad) {
        this.id = növbətiId++;
        this.ad = ad;
    }

    public int getId() { return id; }

    public static void main(String[] args) {
        System.out.println(new İstifadəçi("A").getId()); // 1
        System.out.println(new İstifadəçi("B").getId()); // 2
        System.out.println(new İstifadəçi("C").getId()); // 3
    }
}
```

---

## 3. Static metodlar {#static-methods}

```java
public class RiyaziYardımcı {
    // Static metod — obyekt olmadan çağırılır
    public static int kvadrat(int x) {
        return x * x;
    }

    public static int maks(int a, int b) {
        return a > b ? a : b;
    }

    public static double dairəSahəsi(double r) {
        return Math.PI * r * r;
    }
}

// İstifadə
int kv = RiyaziYardımcı.kvadrat(5);      // 25
int m = RiyaziYardımcı.maks(10, 20);     // 20
double s = RiyaziYardımcı.dairəSahəsi(3); // 28.27...
```

### Static metodların məhdudiyyətləri

```java
public class Nümunə {
    private static int staticField = 10;
    private int instanceField = 20;

    public static void staticMetod() {
        System.out.println(staticField); // OK

        // System.out.println(instanceField); // COMPILE ERROR
        // System.out.println(this.field);    // COMPILE ERROR — this yoxdur!

        // instance metodu çağıra bilmir (obyekt olmadan):
        // instanceMetod(); // COMPILE ERROR

        // Amma obyekt yaradaraq çağırmaq olar:
        Nümunə n = new Nümunə();
        n.instanceMetod(); // OK
    }

    public void instanceMetod() {
        System.out.println(staticField);   // OK
        System.out.println(instanceField); // OK
        staticMetod();                      // OK
    }
}
```

### Klassik Java nümunələri

```java
Math.sqrt(16.0)              // 4.0
Math.max(10, 20)             // 20
Integer.parseInt("42")       // 42
String.valueOf(3.14)         // "3.14"
Arrays.asList(1, 2, 3)       // [1, 2, 3]
Collections.sort(list)       // sort edir
```

Hamısı static — çünki heç bir obyekt vəziyyətindən asılı deyillər.

---

## 4. Static blok (static initializer) {#static-block}

**Static blok** — class ilk dəfə yükləndikdə bir dəfə işə düşür.

```java
public class Konfiq {
    public static final Map<String, String> parametrlər;

    static {
        // Mürəkkəb initialize — bir dəfə class yüklənəndə işləyir
        parametrlər = new HashMap<>();
        parametrlər.put("dbUrl", "jdbc:postgresql://localhost:5432/mydb");
        parametrlər.put("dbUser", "admin");
        System.out.println("Konfiq yükləndi");
    }
}
```

### Execution order (icra sırası)

```java
public class Sıra {
    static int x = 10;

    static {
        System.out.println("static blok 1: x=" + x);
        x = 20;
    }

    static int y = hesabla();

    static {
        System.out.println("static blok 2: y=" + y);
    }

    static int hesabla() {
        System.out.println("hesabla() çağırıldı");
        return 100;
    }

    public static void main(String[] args) {
        System.out.println("main: x=" + x + ", y=" + y);
    }
}

// Çıxış:
// static blok 1: x=10
// hesabla() çağırıldı
// static blok 2: y=100
// main: x=20, y=100
```

Qayda: static field-lər və static bloklar **fayılda yazılış sırası ilə** işə düşür.

---

## 5. Nə vaxt `static` istifadə etmək lazımdır? {#ne-vaxt-static}

### DOĞRU istifadə halları

```java
// 1) Utility class-ları (stateless helper-lər)
public class StringUtils {
    public static boolean isEmpty(String s) {
        return s == null || s.isEmpty();
    }
    public static String reverse(String s) { ... }
}

// 2) Sabitlər (constants)
public class Config {
    public static final int MAX_SIZE = 100;
    public static final String API_URL = "https://api.example.com";
}

// 3) Factory metodları
public class Əlaqə {
    private Əlaqə() {}

    public static Əlaqə yarat(String host) {
        return new Əlaqə(host);
    }
}

// 4) Singleton instance
public class Logger {
    private static Logger instance = new Logger();
    public static Logger getInstance() { return instance; }
}

// 5) main metodu
public static void main(String[] args) { ... }
```

### YANLIŞ istifadə halları

```java
// YANLIŞ — obyekt vəziyyəti (mutable state) static olmamalıdır
public class İstifadəçi {
    private static String ad; // bütün istifadəçilər eyni ad paylaşacaq!
}

// YANLIŞ — thread-safety problemi
public class Sayac {
    private static int i = 0;
    public static void artır() { i++; } // yarış şəraiti
}
```

---

## 6. Yaddaşda static: Method Area / Metaspace {#yaddas}

JVM-də yaddaş bölmələri:

```
┌─────────────────────────────────────────┐
│        METASPACE (Java 8+)              │
│  ───────────────────────────             │
│  - Class metadata                        │
│  - Static field-lər                      │
│  - Method bytecode                       │
│  - Constant pool                         │
├─────────────────────────────────────────┤
│         HEAP                             │
│  ───────────────────────────             │
│  - new ilə yaradılan obyektlər           │
│  - Instance field-lər (obyekt daxilində) │
├─────────────────────────────────────────┤
│         STACK (thread başına bir)        │
│  ───────────────────────────             │
│  - Metod çağırışları                     │
│  - Local dəyişənlər                      │
└─────────────────────────────────────────┘
```

**Əhəmiyyətli:** Static field-lər `Metaspace`-də saxlanır (Java 7-ə qədər PermGen adlanırdı). Obyekt silinsə də static field-lər qalır (class unload edilənə qədər).

---

## 7. `final` dəyişənlər {#final-variables}

**`final`** — "bir dəfə təyin et, sonra dəyişdirmə" deməkdir.

```java
public class Misal {
    public static void main(String[] args) {
        final int yaş = 25;
        // yaş = 30; // COMPILE ERROR — final dəyişənə yenidən təyin etmək olmur

        final List<String> siyahı = new ArrayList<>();
        siyahı.add("a"); // OK — referans dəyişmir, siyahının DAXİLİ dəyişir
        // siyahı = new ArrayList<>(); // COMPILE ERROR — referansı dəyişmək olmur
    }
}
```

**Vacib!** `final` yalnız **referansı** kilidləyir, obyektin **daxilini** yox. `final List<String>` varsa, içinə əlavə edə bilərsən, amma başqa siyahıya işarə edə bilməzsən.

### Final field — constructor-da təyin edilə bilər

```java
public class Koordinat {
    private final double x;
    private final double y;

    public Koordinat(double x, double y) {
        this.x = x; // OK — constructor-da ilk təyin
        this.y = y; // OK
    }

    // Amma artıq dəyişmək olmaz
    // public void setX(double x) { this.x = x; } // COMPILE ERROR
}
```

---

## 8. `final` parametrlər {#final-params}

```java
public void emalEt(final String input) {
    // input = "dəyişir"; // COMPILE ERROR — parametr final-dır

    // Yalnız oxumaq olur
    System.out.println(input.toUpperCase());
}
```

### Niyə final parametr?

1. **Oxunaqlılıq** — oxuyanın bu parametrin dəyişməyəcəyini görməsinə imkan verir
2. **Lambda/anonymous class-da capture** — Java 8-ə qədər məcburi idi

```java
public Runnable yaratRunnable(final String mesaj) {
    return () -> System.out.println(mesaj); // lambda istifadə edə bilir
}
```

Java 8+ effectively final kifayətdir (aşağıda).

---

## 9. `final` metodlar {#final-methods}

**`final` metod** — alt-class-da override edilə bilməz.

```java
public class Valideyn {
    public final String identifikator() {
        return "V-" + hashCode(); // alt-class bunu dəyişə bilməz
    }

    public String salam() {
        return "Salam valideyndən";
    }
}

public class Övlad extends Valideyn {
    // @Override
    // public String identifikator() { ... } // COMPILE ERROR — final metoddur!

    @Override
    public String salam() { // OK — salam final deyil
        return "Salam övladdan";
    }
}
```

### Nə vaxt `final` metod?

- Kritik davranış dəyişdirilməməlidir (security, correctness)
- Template Method pattern-də "fix" addımlar
- Performans (JIT bəzən inline edə bilir)

---

## 10. `final` class-lar {#final-classes}

**`final` class** — heç kim bunu extend edə bilməz.

```java
public final class Deyişilməz {
    // ...
}

// COMPILE ERROR
// public class Alt extends Deyişilməz { }
```

### Klassik nümunələr

| Class | Niyə final? |
|---|---|
| `String` | İmmutable olmalıdır (hash-ləri, pool-u pozmamaq üçün) |
| `Integer`, `Long`, `Double`, ... | İmmutable wrapper-lər |
| `LocalDate`, `LocalTime` (java.time) | İmmutable dizaynın hissəsi |

### Niyə class final olsun?

- İmmutability qorumaq
- Təhlükəsizlik — alt-class kritik davranışı dəyişə bilməsin
- API dizaynı — class-ın davranışı tam kontrol altında

---

## 11. `static final` — sabit (constant) pattern {#static-final}

Java-da **sabit (constant)** belə yazılır:

```java
public class Konfiq {
    // public static final = class-səviyyəli, dəyişilməz, hər kəsin görə bildiyi
    public static final int MAKS_UZUNLUQ = 100;
    public static final String APP_ADI = "MyApp";
    public static final double PI = 3.14159;

    // Collection-lar — "dayaz sabit" — içini dəyişmək olur!
    public static final List<String> RENGLƏR = List.of("qırmızı", "yaşıl", "mavi");
    // List.of() immutable qaytarır, əlavə etmək olmur.

    // DİQQƏT: `new ArrayList<>()` yazsan, içinə hələ də əlavə etmək olar!
    public static final List<String> YANLIS = new ArrayList<>();
    // Sonradan: Konfiq.YANLIS.add("..."); — icazə var (referans sabitdir, içi yox)
}
```

### Naming convention: UPPER_SNAKE_CASE

```java
public static final int MAX_RETRY_COUNT = 3;
public static final String DEFAULT_ENCODING = "UTF-8";
public static final long MILLIS_IN_DAY = 24 * 60 * 60 * 1000L;
```

### `Math` class-ından nümunə

```java
public final class Math {
    public static final double PI = 3.141592653589793;
    public static final double E = 2.718281828459045;

    private Math() {} // instansiya qadağandır

    public static double sqrt(double a) { ... }
}
```

---

## 12. Effectively final (Java 8+) {#effectively-final}

**Effectively final** — bir dəyişən `final` açar sözü ilə yazılmasa da, hər dəfə yalnız bir dəfə təyin edilirsə, kompilyator onu `final` kimi qəbul edir.

```java
public void nümunə() {
    String mesaj = "Salam"; // final yazılmayıb, amma bir dəfə təyin olunur
    // mesaj = "dəyiş";      // bu olsa idi — effectively final olmazdı

    // Lambda/anonymous class yalnız final və ya effectively final capture edə bilir
    Runnable r = () -> System.out.println(mesaj); // OK
    r.run(); // "Salam"
}
```

### Niyə bu məhdudiyyət?

Lambda/anonymous class ayrı thread-də icra ola bilər. Əgər `mesaj` dəyişsə, lambda hansı dəyəri görməlidir? — Qarışıqlıq olmasın deyə Java yalnız dəyişilməz dəyişənləri icazə verir.

```java
// COMPILE ERROR nümunəsi
public Runnable x() {
    String s = "a";
    s = "b"; // artıq effectively final deyil
    return () -> System.out.println(s); // COMPILE ERROR
}
```

---

## Ümumi Səhvlər {#sehvler}

### 1. Static-i "qlobal dəyişən" kimi görmək

```java
// YANLIŞ — mutable static state = gizli əlaqələr, test çətinliyi
public class Ses {
    public static int cari = 0;
}

Ses.cari++; // hər yerdən dəyişir — izləmək mümkünsüzdür
```

### 2. Static metoddan instance field-ə müraciət

```java
public class N {
    int x = 10;

    public static void pis() {
        // System.out.println(x); // COMPILE ERROR
    }
}
```

### 3. final obyektin "dəyişilməz" olmasını gözləmək

```java
final List<Integer> siyahı = new ArrayList<>();
siyahı.add(1); // olur! final yalnız referansı qoruyur
```

### 4. Static metodu override etməyə cəhd

```java
public class A {
    public static void f() { System.out.println("A.f"); }
}
public class B extends A {
    public static void f() { System.out.println("B.f"); } // bu HIDING-dir, override deyil!
}

A obj = new B();
obj.f(); // "A.f" — çünki static metodlar hidden olur, override olmur
```

### 5. Static field-i thread-safe bilmək

```java
public class Sayac {
    private static int i = 0;
    public static void artır() { i++; } // i++ atomic deyil — yarış şəraiti
    // Həll: synchronized və ya AtomicInteger
}
```

---

## İntervyu Sualları {#intervyu}

**S1: Static metod override edilə bilərmi?**
> Xeyr. Static metod **hide** oluna bilər (alt-class-da eyni imzalı static metod yazmaq), amma override yox. Çünki static metodlar class-a aiddir, virtual dispatch-a düşmürlər. `A obj = new B(); obj.staticF();` çağırsan, B-dəki yox, A-dakı işləyər.

**S2: Static blok nə vaxt işə düşür?**
> Class **ilk dəfə yükləndikdə** (class loading zamanı), yalnız **bir dəfə**. Bu, ilk obyekt yaradılmazdan, ilk static metod çağırılmazdan və ya ilk static field-ə müraciət etməmişdən əvvəl ola bilər.

**S3: `final` field nə vaxt initialize edilməlidir?**
> Ya elan edildiyi yerdə (`private final int x = 10;`), ya da **bütün** constructor-larda. Əks halda kompilyator səhv verir.

**S4: `static final` və yalnız `final` arasında fərq nədir?**
> `final` — dəyər bir dəfə təyin edilir, amma hər obyektin öz nüsxəsi var. `static final` — class-a aid bir nüsxə, bütün obyektlər üçün eyni. Sabitlər adətən `static final` olur.

**S5: `main` metodu niyə `static`-dır?**
> Çünki JVM `main`-i çağırmağa obyekt yarada bilmir — hələ heç nə yoxdur. Static metod isə obyekt olmadan class adı ilə çağırıla bilər: `MyApp.main(args)`.

**S6: `final` class-a niyə ehtiyac var?**
> İmmutability və təhlükəsizlik üçün. Məsələn, `String` final-dır ki, alt-class-lar `equals()`/`hashCode()`-u pozmasın və String pool işləməsini xarab etməsin.

**S7: `static` metod obyektin field-inə müraciət edə bilərmi?**
> Birbaşa yox (çünki `this` yoxdur). Amma obyekt referansı parametr kimi alsa, onun field-inə baxa bilər: `static void f(MyObj o) { System.out.println(o.field); }`.

**S8: Effectively final nə deməkdir və nə üçün lazımdır?**
> Bir local dəyişən `final` yazılmasa da, yalnız bir dəfə təyin edilirsə, "effectively final" sayılır. Lambda və anonymous class-lar yalnız effectively final dəyişənləri capture edə bilir — bu da concurrency problemlərindən qoruyur.

**S9: `private static final` sabit vs `public static final` sabit — fərq nədir?**
> Görünürlük baxımından: `private` — yalnız class daxilində, `public` — hər yerdən. Davranış eynidir. Gizlətmək lazım olanda `private`, ümumi konfiqurasiya üçün `public` istifadə edilir.

**S10: Bir static metod daxilində yeni obyekt yaratmaq olarmı?**
> Əlbəttə. Static metod yalnız `this` və instance üzvlərə birbaşa girişə məhduddur. İçəridə `new MyClass()` yazıb tam obyekt yarada və onun instance metodlarını çağıra bilərsən. Factory metodları məhz belə işləyir.
