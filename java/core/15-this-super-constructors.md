# 15 — `this`, `super` və Konstruktorlar

> **Seviyye:** Beginner ⭐


## Mündəricat
1. [Konstruktor nədir?](#konstruktor-nedir)
2. [Default constructor](#default-constructor)
3. [No-arg vs parametric constructor](#no-arg-parametric)
4. [Constructor overloading](#overloading)
5. [`this()` — constructor chaining](#this-chaining)
6. [`this.field` — disambiguate](#this-field)
7. [`super()` — valideyn constructor](#super-call)
8. [Implicit super() — Java avtomatik əlavə edir](#implicit-super)
9. [Miras zəncirində execution order](#execution-order)
10. [Konstruktor override edilə bilərmi?](#override)
11. [Ümumi Səhvlər](#sehvler)
12. [İntervyu Sualları](#intervyu)

---

## 1. Konstruktor nədir? {#konstruktor-nedir}

**Konstruktor** — obyekt yaradılarkən bir dəfə avtomatik çağırılan xüsusi metoddur.

### Adi metod ilə fərqləri

| Xüsusiyyət | Metod | Konstruktor |
|---|---|---|
| Adı | İstənilən | Class adı ilə eyni |
| Return tipi | Lazımdır (`void` da olur) | **Yoxdur** |
| `new` ilə çağırılır | Xeyr | Bəli |
| Bir dəfə işləyir | Xeyr | Bəli (hər `new` üçün bir) |
| `@Override` ola bilir | Bəli | Xeyr |

### Minimal nümunə

```java
public class İnsan {
    String ad;
    int yaş;

    // Konstruktor — class adı ilə eyni, return tipi yoxdur
    public İnsan(String ad, int yaş) {
        this.ad = ad;
        this.yaş = yaş;
        System.out.println("İnsan yaradıldı: " + ad);
    }
}

public class Main {
    public static void main(String[] args) {
        İnsan i = new İnsan("Əli", 25);
        // Çıxış: İnsan yaradıldı: Əli
    }
}
```

### Konstruktorun işi

1. Yaddaşda obyekt üçün yer tutulur (`new` operatoru)
2. Field-lər default dəyərlərlə initialize olunur (`0`, `null`, `false`)
3. Field initializer-lər icra olunur (`int x = 10;`)
4. **Konstruktor bədəni** işə düşür
5. Obyekt referansı qaytarılır

---

## 2. Default constructor {#default-constructor}

Əgər sən hec bir konstruktor yazmasan, Java sənə bir **default (boş) konstruktor** verir.

```java
public class Boş {
    // Heç konstruktor yazılmayıb — Java avtomatik əlavə edir:
    // public Boş() { super(); }
}

Boş b = new Boş(); // işləyir
```

### DİQQƏT — bir konstruktor yazsan, default getmir

```java
public class İnsan {
    String ad;

    public İnsan(String ad) {
        this.ad = ad;
    }
}

// İnsan i = new İnsan(); // COMPILE ERROR — no-arg konstruktor YOXDUR!
İnsan i = new İnsan("Əli"); // OK
```

**Qayda:** Sən özün hər hansı bir konstruktor yazsan, Java artıq sənə default verməz. Lazım olsa əlavə et.

```java
public class İnsan {
    String ad;

    public İnsan() { } // indi default da var

    public İnsan(String ad) {
        this.ad = ad;
    }
}
```

---

## 3. No-arg vs parametric constructor {#no-arg-parametric}

### No-arg (parametrsiz)

```java
public class Avtomobil {
    String marka = "Naməlum";
    int sürət = 0;

    public Avtomobil() {
        // default dəyərlər istifadə olunur
    }
}

Avtomobil a = new Avtomobil(); // marka="Naməlum", sürət=0
```

### Parametric (parametrli)

```java
public class Avtomobil {
    String marka;
    int sürət;

    public Avtomobil(String marka, int sürət) {
        this.marka = marka;
        this.sürət = sürət;
    }
}

Avtomobil a = new Avtomobil("BMW", 120);
```

### Niyə no-arg da olsun?

- Framework-lər (Spring, Hibernate, Jackson) tez-tez no-arg constructor tələb edir — reflection ilə obyekt yaradıb sonra field-lərə dəyər qoyur.
- Serialization (JavaBean specification) — no-arg lazımdır.

---

## 4. Constructor overloading {#overloading}

Eyni class-da fərqli parametrli bir neçə konstruktor ola bilər.

```java
public class Kitab {
    private String başlıq;
    private String müəllif;
    private int il;
    private double qiymət;

    // 1) Tam konstruktor
    public Kitab(String başlıq, String müəllif, int il, double qiymət) {
        this.başlıq = başlıq;
        this.müəllif = müəllif;
        this.il = il;
        this.qiymət = qiymət;
    }

    // 2) Qiymətsiz (default 0)
    public Kitab(String başlıq, String müəllif, int il) {
        this.başlıq = başlıq;
        this.müəllif = müəllif;
        this.il = il;
        this.qiymət = 0.0;
    }

    // 3) Yalnız başlıq
    public Kitab(String başlıq) {
        this.başlıq = başlıq;
        this.müəllif = "Naməlum";
        this.il = 0;
        this.qiymət = 0.0;
    }
}

// İstifadə
Kitab k1 = new Kitab("Java", "Gosling", 2020, 35.0);
Kitab k2 = new Kitab("Java", "Gosling", 2020);
Kitab k3 = new Kitab("Java");
```

Problem: təkrar kod çoxdur (`this.başlıq = ...` 3 yerdə). Bunu `this()` ilə həll edirik.

---

## 5. `this()` — constructor chaining {#this-chaining}

`this()` — **başqa konstruktoru çağırır**. Beləliklə, təkrar kod yaza bilmirik.

```java
public class Kitab {
    private String başlıq;
    private String müəllif;
    private int il;
    private double qiymət;

    // 1) Əsas konstruktor — bütün sahələri təyin edir
    public Kitab(String başlıq, String müəllif, int il, double qiymət) {
        this.başlıq = başlıq;
        this.müəllif = müəllif;
        this.il = il;
        this.qiymət = qiymət;
    }

    // 2) Qiymətsiz — 1-ci-ni çağırır
    public Kitab(String başlıq, String müəllif, int il) {
        this(başlıq, müəllif, il, 0.0);
    }

    // 3) Yalnız başlıq — 2-ci-ni çağırır (o da 1-ci-ni çağırır)
    public Kitab(String başlıq) {
        this(başlıq, "Naməlum", 0);
    }

    // 4) No-arg — 3-cü-nü çağırır
    public Kitab() {
        this("Adsız");
    }
}
```

### `this()` qaydaları

| Qayda | İzahı |
|---|---|
| İlk sətirdə | `this()` konstruktorun **ilk** ifadəsi olmalıdır |
| Yalnız bir dəfə | Bir konstruktorda yalnız bir `this()` ola bilər |
| `super()` ilə yanaşı olmaz | İkisindən yalnız biri |
| Cycle olmamalıdır | A → B → A sonsuz dövr — kompilyator səhv verir |

```java
public class Pis {
    public Pis() {
        System.out.println("salam");
        // this(10); // COMPILE ERROR — ilk sətir olmalıdır!
    }

    public Pis(int x) {}
}
```

---

## 6. `this.field` — disambiguate {#this-field}

Parametr adı field adı ilə eyni olanda, `this.` istifadə edilir.

```java
public class İşçi {
    private String ad;
    private double maaş;

    // ❌ this yoxdur — parametr field-i gölgələyir (shadowing)
    public İşçi(String ad, double maaş) {
        ad = ad;       // parametri özünə təyin edir — heç nə etmir!
        maaş = maaş;   // eyni problem
    }

    // ✅ this.field = parametr — field-i parametrdən təyin edir
    public İşçi(String ad, double maaş, boolean x) {
        this.ad = ad;
        this.maaş = maaş;
    }
}
```

### `this`-in 4 istifadə yeri

```java
public class Nümunə {
    private String ad;

    // 1) Field ilə parametr adlarını ayırmaq
    public Nümunə(String ad) {
        this.ad = ad;
    }

    // 2) Başqa konstruktoru çağırmaq (this())
    public Nümunə() {
        this("default");
    }

    // 3) Cari obyekti başqa metoda ötürmək
    public void qeydiyyat(Qeydiyyatçı q) {
        q.qeydiyyat(this);
    }

    // 4) Method chaining üçün cari obyekti qaytarmaq
    public Nümunə adıDəyiş(String yeni) {
        this.ad = yeni;
        return this;
    }
}
```

---

## 7. `super()` — valideyn constructor {#super-call}

`super()` — **valideyn class-ın konstruktorunu** çağırır.

```java
public class Heyvan {
    String ad;

    public Heyvan(String ad) {
        this.ad = ad;
        System.out.println("Heyvan yaradıldı: " + ad);
    }
}

public class İt extends Heyvan {
    String cins;

    public İt(String ad, String cins) {
        super(ad); // valideyn Heyvan(String) konstruktoru çağırılır
        this.cins = cins;
        System.out.println("İt yaradıldı: " + cins);
    }
}

public class Main {
    public static void main(String[] args) {
        İt i = new İt("Rex", "Ovçarka");
    }
}

// Çıxış:
// Heyvan yaradıldı: Rex
// İt yaradıldı: Ovçarka
```

### `super()` qaydaları

| Qayda | İzahı |
|---|---|
| İlk sətirdə | Konstruktorun **ilk** ifadəsi olmalıdır (`this()` ilə birlikdə olmaz) |
| Yalnız bir dəfə | Bir konstruktorda yalnız bir `super()` |
| Tip uyğun gəlməlidir | Valideyndə uyğun imzalı konstruktor olmalıdır |

---

## 8. Implicit super() — Java avtomatik əlavə edir {#implicit-super}

Əgər sən `super()` yazmasan, Java bunu avtomatik əlavə edir — parametrsiz formada.

```java
public class A {
    public A() {
        System.out.println("A()");
    }
}

public class B extends A {
    public B() {
        // Java gizli olaraq super(); əlavə edir
        System.out.println("B()");
    }
}

new B();
// Çıxış:
// A()
// B()
```

### TƏHLÜKƏ: Valideyndə no-arg konstruktor yoxdursa

```java
public class A {
    public A(int x) { } // yalnız parametrli var — default yoxdur
}

public class B extends A {
    public B() {
        // COMPILE ERROR — gizli super() işləmir, çünki A-da A() yoxdur
    }
}

// DOĞRU:
public class B extends A {
    public B() {
        super(10); // açıq şəkildə çağır
    }
}
```

---

## 9. Miras zəncirində execution order {#execution-order}

```java
public class A {
    int a = initA();

    { System.out.println("A: instance blok"); }

    public A() {
        System.out.println("A: konstruktor");
    }

    int initA() {
        System.out.println("A: field init");
        return 1;
    }
}

public class B extends A {
    int b = initB();

    { System.out.println("B: instance blok"); }

    public B() {
        System.out.println("B: konstruktor");
    }

    int initB() {
        System.out.println("B: field init");
        return 2;
    }
}

public class C extends B {
    int c = initC();

    public C() {
        System.out.println("C: konstruktor");
    }

    int initC() {
        System.out.println("C: field init");
        return 3;
    }
}

new C();

// Çıxış:
// A: field init
// A: instance blok
// A: konstruktor
// B: field init
// B: instance blok
// B: konstruktor
// C: field init
// C: konstruktor
```

### Qayda

```
1. Object → Valideyn → ... → Ən alt class
2. Hər səviyyədə:
   a) Field initializer-lər və instance bloklar (yazılış sırası)
   b) Konstruktorun bədəni
3. Konstruktor yuxarıdan aşağı "bitir"
```

### Vizual diaqram

```
      Object konstr.
            │
            ▼
       A konstr. başlayır
       │
       ├── super() (Object-ə)
       ├── A-nın field-ləri
       ├── A-nın instance bloku
       └── A konstr. bədəni bitir
            │
            ▼
       B konstr. başlayır
       │
       ├── super() (A-ya) ← yuxarıda icra oldu
       ├── B-nin field-ləri
       ├── B-nin instance bloku
       └── B konstr. bədəni bitir
            │
            ▼
       C konstr. başlayır (eyni qayda)
```

---

## 10. Konstruktor override edilə bilərmi? {#override}

**Xeyr.** Konstruktorlar miras alınmır və override edilmir.

```java
public class A {
    public A(int x) { }
}

public class B extends A {
    // B-də A-nın konstruktorları YOXDUR
    // B-nin öz konstruktorları olmalıdır
    public B(int x) {
        super(x);
    }
}

// A-nın konstruktoru ilə B yaratmaq olmur:
// B b = new B(10); // OK — B-nin öz konstruktoru var
// new B() ← yalnız B(int) olduğuna görə, bu COMPILE ERROR (əgər B() yazılmayıb)
```

### Niyə?

Çünki konstruktor **o class-a məxsus** obyekt yaratmaq üçündür. A-nın konstruktoru A obyekti yaradır, B-nin B obyekti. Bunları qarışdırmaq məntiqsizdir.

---

## Ümumi Səhvlər {#sehvler}

### 1. `this()` və `super()`-i birlikdə işlətmək

```java
public class B extends A {
    public B() {
        super();      // bunlardan yalnız birini yaz!
        this(10);     // COMPILE ERROR
    }
}
```

### 2. `this()` ilk sətirdə olmamaq

```java
public class A {
    public A() {
        System.out.println("salam");
        // this(10); // COMPILE ERROR — ilk sətir olmalıdır
    }
}
```

### 3. `this`-i `super()`-dən əvvəl istifadə etmək

```java
public class B extends A {
    private int x;

    public B() {
        this.x = 10;   // super()-dən əvvəl this istifadə — XƏTA!
        super();       // artıq olmaz
    }
}

// DOĞRU
public class B extends A {
    private int x;

    public B() {
        super();
        this.x = 10;
    }
}
```

### 4. `this.field = field` əvəzinə `field = field` yazmaq

```java
public class İnsan {
    String ad;

    public İnsan(String ad) {
        ad = ad; // ❌ heç nə etmir — field null qalır
        this.ad = ad; // ✅ doğru
    }
}
```

### 5. Valideyndə no-arg yox, alt-class-da super() unudulur

```java
public class A {
    public A(String s) { } // yalnız parametrli
}

public class B extends A {
    public B() {
        // COMPILE ERROR — A-nın A() yoxdur, gizli super() işləmir
    }
}
```

### 6. Konstruktorda uzun iş görmək

```java
// YANLIŞ — konstruktor DB-yə gedir, network sorğusu atır
public class Müştəri {
    public Müştəri(String id) {
        this.məlumat = api.getData(id); // slow, error-prone, test çətinliyi
    }
}

// DOĞRU — konstruktor yalnız field-ləri təyin etsin
public class Müştəri {
    private final String id;

    public Müştəri(String id) {
        this.id = id;
    }

    public void yüklə(Api api) {
        this.məlumat = api.getData(id);
    }
}
```

---

## İntervyu Sualları {#intervyu}

**S1: Konstruktor override edilə bilərmi?**
> Xeyr. Konstruktorlar miras alınmır. Alt-class öz konstruktorlarını yazmalıdır və `super(...)` ilə valideyn konstruktorunu çağırmalıdır.

**S2: Default konstruktor nə vaxt verilir?**
> Yalnız sən heç bir konstruktor yazmasan. Bir dənə belə konstruktor yazsan (parametrli də olsa), default verilmir.

**S3: `this()` və `super()` eyni konstruktorda ola bilərmi?**
> Xeyr. Hər ikisi də **ilk sətir** olmalıdır, ona görə yalnız birini işlətmək olar. Əgər `this()` yazsan, o konstruktor öz növbəsində `super()`-i işə salacaq.

**S4: Konstruktor gövdəsində `super()` olmasa nə olar?**
> Java avtomatik olaraq `super();` (parametrsiz) əlavə edir. Valideyn class-da no-arg konstruktor yoxdursa — COMPILE ERROR.

**S5: Bir class-da neçə konstruktor ola bilər?**
> İstənilən qədər, parametr siyahıları (say və/və ya tip) fərqli olduğu müddətcə — buna **constructor overloading** deyilir.

**S6: Konstruktor return tipi niyə yoxdur?**
> Çünki konstruktor metod deyil — obyekt yaradılma prosesinin bir hissəsidir. `new` operatoru yaddaş ayırır, konstruktor isə yalnız field-ləri initialize edir.

**S7: Abstract class-da konstruktor ola bilərmi?**
> Bəli. Abstract class instantiate oluna bilməsə də, alt-class-ları `super()` vasitəsilə onun konstruktorunu çağırır. Beləliklə abstract class öz field-lərini initialize edə bilir.

**S8: Final class-da konstruktor necə olur?**
> Adi konstruktor kimi. `final` class yalnız extend edilə bilmir, amma obyektləri yaradılır. Məsələn, `String` final-dır, amma onun konstruktorları var.

**S9: Miras zəncirində hansı konstruktor ilk işə düşür?**
> Ən üstdəki (Object class-ının), sonra aşağıya doğru. Execution öncə `super()` çağırışı sayəsində yuxarı gedir, orada Object-ə çatıb, oradan aşağıya doğru hər konstruktorun gövdəsi sırayla icra olur.

**S10: Private konstruktor olar?**
> Bəli və faydalıdır. Nümunələr: Singleton (class daxilindən yeganə obyekt yaratmaq), utility class-lar (instansiyanı qadağan etmək), factory method pattern (yalnız static metoddan keçib yaratmaq).
