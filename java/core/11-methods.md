# 11 — Java Metodları (Methods)

> **Seviyye:** Junior ⭐


## Mündəricat
1. [Metod nədir?](#metod-nedir)
2. [Metodun anatomiyası](#metod-anatomiyasi)
3. [Return tipləri və `void`](#return-tipleri)
4. [Parametr ötürülməsi — pass-by-value](#pass-by-value)
5. [Method overloading](#overloading)
6. [Varargs (dəyişən sayda arqument)](#varargs)
7. [Rekursiya (Recursion)](#rekursiya)
8. [Local dəyişənlərin scope-u](#scope)
9. [`static` metodlar (qısa baxış)](#static-metodlar)
10. [Adlandırma qaydaları](#adlandirma)
11. [Ümumi Səhvlər](#umumi-sehvler)
12. [İntervyu Sualları](#intervyu-suallari)

---

## 1. Metod nədir? {#metod-nedir}

**Metod** — müəyyən işi yerinə yetirən kod blokudur. Real dünyada nümunə:

- **İnsan** obyektinin metodları: `gez()`, `danış()`, `yemeəkYe()`
- **Televizor** obyektinin metodları: `aç()`, `söndür()`, `kanalDəyiş()`

Metodlar kodumuzu təkrarlamamağa kömək edir — **DRY (Don't Repeat Yourself)** prinsipi.

```java
public class Salamlama {
    // Metod olmadan — təkrar kod
    public static void main(String[] args) {
        System.out.println("Salam Əli!");
        System.out.println("Salam Vüsal!");
        System.out.println("Salam Aysel!");
    }
}
```

```java
public class Salamlama {
    // Metod ilə — təkrar yoxdur
    public static void salamla(String ad) {
        System.out.println("Salam " + ad + "!");
    }

    public static void main(String[] args) {
        salamla("Əli");
        salamla("Vüsal");
        salamla("Aysel");
    }
}
```

---

## 2. Metodun anatomiyası {#metod-anatomiyasi}

Bir metod 5 əsas hissədən ibarətdir:

```java
public    static    int      topla      (int a, int b)     {
//  1        2        3         4              5              6
//  |        |        |         |              |              |
// access modifier             return name     parameters     body
//          static     type
    return a + b;
}
```

| Nömrə | Hissə | Məna |
|---|---|---|
| 1 | Access modifier | `public` / `private` / `protected` / default |
| 2 | `static` | İsteğe bağlı — class-a məxsusdur |
| 3 | Return tipi | `int`, `String`, `void` və s. |
| 4 | Metod adı | camelCase |
| 5 | Parametrlər | Giriş dəyərləri |
| 6 | Body | Metodun məntiqi (`{ ... }`) |

### Tam nümunə

```java
public class Kalkulyator {

    // Public, instance metod, iki int qəbul edir, int qaytarır
    public int topla(int a, int b) {
        return a + b;
    }

    // Private köməkçi metod
    private boolean müsbətdir(int x) {
        return x > 0;
    }

    // void — heç nə qaytarmır
    public void çap(String mesaj) {
        System.out.println(mesaj);
    }
}
```

---

## 3. Return tipləri və `void` {#return-tipleri}

### `void` — heç nə qaytarma

`void` metodu sadəcə bir iş görür, nəticə qaytarmır:

```java
public void salamlaIstifadəçi(String ad) {
    System.out.println("Xoş gəldin, " + ad);
    // return ifadəsi yoxdur — lazım deyil
}
```

`void` metoddan erkən çıxmaq üçün `return;` yaza bilərik:

```java
public void bölmə(int a, int b) {
    if (b == 0) {
        System.out.println("Sıfra bölmək olmaz!");
        return; // erkən çıx — dəyər qaytarma
    }
    System.out.println("Nəticə: " + (a / b));
}
```

### Dəyər qaytaran metodlar

```java
public int yaşıHesabla(int doğumİli) {
    int cariİl = 2026;
    return cariİl - doğumİli; // mütləq return olmalıdır
}

public boolean cütdür(int n) {
    return n % 2 == 0;
}

public String tamAd(String ad, String soyad) {
    return ad + " " + soyad;
}
```

### Bütün yollar return etməlidir

```java
// YANLIŞ — kompilyasiya xətası
public int yoxla(int x) {
    if (x > 0) {
        return 1;
    }
    // else hissəsində return yoxdur → XƏTA!
}

// DOĞRU
public int yoxla(int x) {
    if (x > 0) {
        return 1;
    } else {
        return -1;
    }
}

// DAHA YAXŞI
public int yoxla(int x) {
    return x > 0 ? 1 : -1;
}
```

---

## 4. Parametr ötürülməsi — pass-by-value {#pass-by-value}

Bu Java-da ən vacib mövzulardan biridir!

**Java həmişə pass-by-value istifadə edir.** Yəni metoda ötürdüyümüz dəyərin **kopyası** gedir.

### Primitive tiplər üçün

```java
public class PassByValueDemo {

    public static void ikiQatEt(int x) {
        x = x * 2;  // yalnız lokal kopyanı dəyişir
        System.out.println("Metod daxili: " + x);
    }

    public static void main(String[] args) {
        int ədəd = 5;
        ikiQatEt(ədəd);
        System.out.println("Metoddan sonra: " + ədəd);
    }
}
```

**Çıxış:**
```
Metod daxili: 10
Metoddan sonra: 5
```

Orijinal `ədəd` dəyişmədi — çünki metoda kopyası getdi.

### Reference tiplər üçün (tricky!)

Obyekt ötürüldükdə, **referansın kopyası** gedir. Lakin o kopya hələ də **eyni obyektə** işarə edir:

```java
public class İstifadəçi {
    public String ad;

    public İstifadəçi(String ad) {
        this.ad = ad;
    }
}

public class ReferansDemo {

    public static void adıDəyiş(İstifadəçi u) {
        u.ad = "Dəyişdirildi"; // obyektin sahəsini dəyişir
    }

    public static void yeniObyektYarat(İstifadəçi u) {
        u = new İstifadəçi("Yeni"); // yalnız lokal kopyanı yeni obyektə yönəldir
    }

    public static void main(String[] args) {
        İstifadəçi user = new İstifadəçi("Əli");

        adıDəyiş(user);
        System.out.println(user.ad); // "Dəyişdirildi"

        yeniObyektYarat(user);
        System.out.println(user.ad); // hələ də "Dəyişdirildi" — yeni obyekt yaradılmadı
    }
}
```

### Vacib nəticə

| Əməliyyat | Orijinala təsir edir? |
|---|---|
| Primitive parametri dəyişdirmək | Xeyr |
| Obyektin sahəsini dəyişdirmək (`u.ad = ...`) | Bəli |
| Parametrə yeni obyekt mənimsətmək (`u = new ...`) | Xeyr |

---

## 5. Method overloading {#overloading}

**Overloading** — eyni adlı, lakin fərqli parametrli metodlar yaratmaq.

### Qaydalar

Metodlar fərqlənə bilər:
- Parametr **sayı** ilə
- Parametr **tipi** ilə
- Parametr **sırası** ilə

Lakin fərqlənə **bilməz**:
- Yalnız return tipi ilə
- Yalnız parametr adı ilə

```java
public class RiyaziHesab {

    // Parametr sayı fərqlidir
    public int topla(int a, int b) {
        return a + b;
    }

    public int topla(int a, int b, int c) {
        return a + b + c;
    }

    // Parametr tipi fərqlidir
    public double topla(double a, double b) {
        return a + b;
    }

    // Parametr sırası fərqlidir
    public String birləşdir(int n, String s) {
        return n + s;
    }

    public String birləşdir(String s, int n) {
        return s + n;
    }
}
```

### Niyə overloading lazımdır?

`System.out.println()` özü overloaded metoddur:

```java
System.out.println(42);        // println(int)
System.out.println("salam");   // println(String)
System.out.println(3.14);      // println(double)
System.out.println(true);      // println(boolean)
```

### YANLIŞ — yalnız return tipi ilə overload

```java
public int işləyir(int a) { return a; }
public double işləyir(int a) { return a; } // XƏTA! eyni imza
```

---

## 6. Varargs (dəyişən sayda arqument) {#varargs}

`...` sintaksisi ilə istənilən sayda parametr qəbul edə bilərik:

```java
public class VarargsDemo {

    // 0, 1, 2, 3... nə qədər istəsə int qəbul edir
    public static int cəmi(int... ədədlər) {
        int toplam = 0;
        for (int n : ədədlər) {
            toplam += n;
        }
        return toplam;
    }

    public static void main(String[] args) {
        System.out.println(cəmi());              // 0
        System.out.println(cəmi(5));             // 5
        System.out.println(cəmi(1, 2, 3));       // 6
        System.out.println(cəmi(1, 2, 3, 4, 5)); // 15

        // Array də ötürə bilərik
        int[] array = {10, 20, 30};
        System.out.println(cəmi(array));         // 60
    }
}
```

### Qaydalar

- Varargs mütləq **sonuncu parametr** olmalıdır
- Bir metodun yalnız **bir** varargs parametri ola bilər

```java
// DOĞRU
public void yaz(String prefix, int... ədədlər) { ... }

// YANLIŞ — varargs ortadadır
public void yaz(int... ədədlər, String suffix) { ... }
```

### Real nümunə — `String.format`

```java
String mesaj = String.format("Ad: %s, Yaş: %d, Maaş: %.2f",
                              "Əli", 30, 1500.50);
// String.format imzası: public static String format(String fmt, Object... args)
```

---

## 7. Rekursiya (Recursion) {#rekursiya}

**Rekursiya** — metodun özünü çağırmasıdır.

Hər rekursiv metodun 2 hissəsi olmalıdır:
1. **Base case** — dayanma şərti
2. **Recursive case** — özünü çağırma

### Nümunə: Faktorial

```java
public class Rekursiya {

    public static int faktorial(int n) {
        // Base case — 0! = 1
        if (n <= 1) {
            return 1;
        }
        // Recursive case
        return n * faktorial(n - 1);
    }

    public static void main(String[] args) {
        System.out.println(faktorial(5)); // 120
        // 5 * faktorial(4)
        // 5 * 4 * faktorial(3)
        // 5 * 4 * 3 * faktorial(2)
        // 5 * 4 * 3 * 2 * faktorial(1)
        // 5 * 4 * 3 * 2 * 1 = 120
    }
}
```

### StackOverflowError — base case olmadıqda

```java
public static void sonsuzRekursiya(int n) {
    System.out.println(n);
    sonsuzRekursiya(n + 1); // dayanmır!
}

// Nəticə: java.lang.StackOverflowError
// Stack dolur, çünki hər çağırış yaddaşda frame yaradır
```

### Nümunə: Fibonacci

```java
public static int fibonacci(int n) {
    if (n <= 1) return n;           // base case
    return fibonacci(n - 1) + fibonacci(n - 2); // recursive case
}

// fibonacci(5) = 5
// fibonacci(10) = 55
```

### Rekursiya vs loop

| Aspekt | Rekursiya | Loop |
|---|---|---|
| Oxunaqlıq | Bəzi məsələlərdə daha aydın | Sadə məsələlərdə daha sürətli |
| Yaddaş | Stack istifadə edir | Stack istifadə etmir |
| StackOverflow riski | Var | Yoxdur |
| Performans | Adətən yavaş | Adətən sürətli |

---

## 8. Local dəyişənlərin scope-u {#scope}

**Scope** — dəyişənin "görünmə sahəsi".

Local dəyişən yalnız təyin olunduğu bloq `{ ... }` daxilində görünür:

```java
public class ScopeDemo {

    public static void metod(int parametr) {
        int local = 10; // metod scope-u

        if (parametr > 0) {
            int blokDaxili = 20; // yalnız if bloku
            System.out.println(local);        // OK
            System.out.println(blokDaxili);   // OK
        }

        // System.out.println(blokDaxili); // XƏTA! burada görünmür

        for (int i = 0; i < 3; i++) {
            int dövri = i * 2; // yalnız for bloku
        }
        // System.out.println(i); // XƏTA!
    }
}
```

### Shadowing — kölgələmə

```java
public class Shadowing {
    private int x = 100; // instance field

    public void metod() {
        int x = 50; // local dəyişən field-i kölgələyir
        System.out.println(x);       // 50 (local)
        System.out.println(this.x);  // 100 (field)
    }
}
```

### Local dəyişən başlanğıc dəyər almalıdır

```java
public void misal() {
    int a;
    // System.out.println(a); // XƏTA! a başlanğıc dəyər almayıb

    int b = 0; // indi OK
    System.out.println(b);
}
```

---

## 9. `static` metodlar (qısa baxış) {#static-metodlar}

`static` metodlar **sinifə** məxsusdur, obyektə yox:

```java
public class RiyaziYardımçı {

    // Static metod — obyekt yaratmaq lazım deyil
    public static int kvadrat(int x) {
        return x * x;
    }

    public static int maksimum(int a, int b) {
        return a > b ? a : b;
    }
}

// İstifadə:
int nəticə = RiyaziYardımçı.kvadrat(5); // sinif adı ilə
int maks = RiyaziYardımçı.maksimum(10, 20);
```

Detallı `static` mövzusu **189-java-static-final.md** faylında izah olunur.

### `main` metodu static-dir

```java
public class Proqram {
    // main həmişə static olmalıdır — JVM obyekt yaratmadan çağırır
    public static void main(String[] args) {
        System.out.println("Salam, Dünya!");
    }
}
```

---

## 10. Adlandırma qaydaları {#adlandirma}

### Konvensiyalar

| Element | Qayda | Nümunə |
|---|---|---|
| Metod adı | camelCase | `hesabla`, `maaşıArtır` |
| Boolean qaytaran | `is`, `has`, `can` | `isActive`, `hasAccess`, `canEdit` |
| Dəyər qaytaran | Feil | `getName`, `calculateTotal` |
| Void metod | Feil | `print`, `save`, `update` |
| Constant | UPPER_SNAKE | `MAX_VALUE` (field üçün) |

### Yaxşı adlandırma

```java
// YANLIŞ — qeyri-aydın
public int m(int a, int b) { return a + b; }
public void x() { ... }
public boolean check(String s) { ... }

// DOĞRU — məqsəd aydındır
public int topla(int a, int b) { return a + b; }
public void fayliSaxla() { ... }
public boolean emailDüzgündür(String email) { ... }
```

### Metod bir işi görməlidir (Single Responsibility)

```java
// YANLIŞ — çox iş görür
public void userYaratVəEmailGöndərVəLogYaz(User u) { ... }

// DOĞRU — hər metod bir iş
public User userYarat(String ad, String email) { ... }
public void emailGöndər(String email, String mesaj) { ... }
public void logYaz(String mesaj) { ... }
```

---

## Ümumi Səhvlər

### 1. Return unutmaq

```java
// YANLIŞ
public int cəm(int a, int b) {
    a + b; // XƏTA! nəticə itir
}

// DOĞRU
public int cəm(int a, int b) {
    return a + b;
}
```

### 2. `void` metoddan dəyər qaytarmaq

```java
// YANLIŞ
public void çap(String s) {
    return s; // XƏTA! void-dən dəyər qaytarmaq olmaz
}
```

### 3. Primitive dəyişdiyini güman etmək

```java
public void artır(int x) {
    x++; // orijinal dəyişmir!
}

int sayı = 5;
artır(sayı);
System.out.println(sayı); // 5 — dəyişmədi
```

### 4. Rekursiyada base case unutmaq

```java
// YANLIŞ — sonsuz dövr
public int hesabla(int n) {
    return n + hesabla(n - 1); // base case yoxdur → StackOverflowError
}
```

### 5. Overloading vs overriding qarışıqlığı

```java
// Overloading — eyni class, fərqli parametrlər
public void çap(int n) { }
public void çap(String s) { }

// Overriding — sub-class ana metodu dəyişir (irsiyətdə)
```

### 6. Local dəyişəni başlamamaq

```java
public int problem() {
    int x;
    return x; // XƏTA! x başlanğıc dəyər almayıb
}
```

### 7. Varargs-ı orta yerə qoymaq

```java
// YANLIŞ
public void yaz(int... n, String s) { } // compile XƏTA

// DOĞRU
public void yaz(String s, int... n) { }
```

---

## İntervyu Sualları

**S1: Java pass-by-value yoxsa pass-by-reference istifadə edir?**
> Java həmişə **pass-by-value** istifadə edir. Primitive tiplər üçün dəyərin kopyası, obyektlər üçün isə referansın kopyası ötürülür. Referansın kopyası eyni obyektə işarə etdiyi üçün obyektin sahələrini dəyişmək mümkündür, lakin parametrə yeni obyekt təyin etmək orijinal referansı dəyişmir.

**S2: Method overloading nədir və necə işləyir?**
> Overloading — eyni adlı, lakin fərqli parametr siyahısı olan metodlar yaratmaqdır. Kompilyator arqumentlərin tiplərinə və sayına baxaraq hansı metodu çağırmalı olduğunu müəyyən edir. Yalnız return tipini dəyişərək overload etmək olmaz.

**S3: `void` metoddan `return` istifadə etmək olarmı?**
> Bəli — lakin dəyər qaytarmadan. `return;` yazaraq metoddan erkən çıxa bilərik. Dəyər qaytarsaq, kompilyasiya xətası olar.

**S4: Varargs-ın qaydaları nələrdir?**
> Varargs (`Type...`) metodun **sonuncu** parametri olmalıdır və bir metodun yalnız **bir** varargs parametri ola bilər. Daxildə array kimi işlənir. Çağırış zamanı həm fərdi arqumentlər, həm də array ötürə bilərik.

**S5: StackOverflowError nə vaxt baş verir?**
> Ən çox rekursiv metodda base case düzgün qurulmadıqda. Hər metod çağırışı stack-də yeni frame yaradır; çox dərinliyə getdikdə stack dolur və bu error atılır. Hədd JVM-ə görə dəyişir, adətən 10,000-100,000 dərinliyə qədərdir.

**S6: Bir metod neçə dəyər qaytara bilər?**
> Java-da metod yalnız **bir** dəyər qaytara bilər. Birdən çox dəyər qaytarmaq üçün array, `List`, `Map`, custom class və ya Java 16+ `record` istifadə olunur.

**S7: Static metoddan instance metodu birbaşa çağırmaq olarmı?**
> Xeyr. Static metodda `this` yoxdur, deməli instance üzvlərə birbaşa müraciət olmur. Əvvəlcə obyekt yaratmaq və onun üzərindən çağırmaq lazımdır.

**S8: `ikiQatEt(int x) { x *= 2; }` metodu orijinal dəyişəni niyə dəyişdirmir?**
> Çünki Java pass-by-value-dur. `x` parametri orijinal dəyişənin kopyasıdır. Kopyanı dəyişdirmək orijinala təsir etmir. Bunu dəyişdirmək üçün ya dəyəri return etmək, ya da mutable wrapper (məs., array) istifadə etmək lazımdır.

**S9: Iki metod yalnız return tipi ilə fərqlənə bilərmi?**
> Xeyr. Overloading üçün parametr siyahısı fərqli olmalıdır. `int f(int x)` və `double f(int x)` birgə yaşaya bilməz — kompilyator hansını seçəcəyini bilməz.

**S10: Private metod overload oluna bilərmi?**
> Bəli. Overloading access modifier-dən asılı deyil — `private`, `protected`, `public` bütün metodları overload etmək olar. Qayda yalnız imza (ad + parametrlər) ilə bağlıdır.
