# 16 — Paketlər, Import və Access Modifier-lər

> **Seviyye:** Beginner ⭐


## Mündəricat
1. [Paket (package) nədir?](#paket-nedir)
2. [Qovluq quruluşu və paket adı](#qovluq-paket)
3. [`package` elanı](#package-elani)
4. [`import` direktivi](#import)
5. [Fully Qualified Name (FQN)](#fqn)
6. [Static import](#static-import)
7. [Default (adsız) paket — anti-pattern](#default-paket)
8. [Access modifier-lər — 4 səviyyə](#access-modifiers)
9. [Görünmə matrisi (visibility matrix)](#visibility)
10. [Paket adlandırma konvensiyası](#naming)
11. [Ümumi Səhvlər](#umumi-sehvler)
12. [İntervyu Sualları](#intervyu-suallari)

---

## 1. Paket (package) nədir? {#paket-nedir}

**Paket** — bir-biriylə əlaqəli sinifləri qruplaşdıran "qovluq"dur. Real dünya analogiyası:

- Kitabxana bir **paketdir**
- "Fantastika", "Tarix", "Texnologiya" rəfləri **alt paketlərdir**
- Hər kitab bir **sinifdir**

### Niyə paketlərə ehtiyac var?

| Problem | Paketin həlli |
|---|---|
| Eyni adlı siniflər toqquşa bilər | `java.util.Date` vs `java.sql.Date` |
| Böyük layihədə qarışıqlıq | Məntiqi qruplaşma |
| Görünmə nəzarəti | package-private access |
| Kod təşkilatı | Təbəqələr arası ayrılıq |

### Real nümunə

```
com.example.shop/
├── model/           ← paket: data class-lar
│   ├── User.java
│   └── Product.java
├── service/         ← paket: biznes məntiqi
│   ├── UserService.java
│   └── ProductService.java
└── controller/      ← paket: HTTP handler-lər
    └── UserController.java
```

---

## 2. Qovluq quruluşu və paket adı {#qovluq-paket}

**Qayda:** paket adı qovluq strukturu ilə **dəqiq üst-üstə düşməlidir**.

```
src/main/java/
└── com/
    └── example/
        └── shop/
            └── model/
                └── User.java    ← package com.example.shop.model;
```

```java
// User.java faylının birinci sətri
package com.example.shop.model;

public class User {
    private String name;
}
```

### YANLIŞ — uyğunsuzluq

```
src/com/example/shop/model/User.java

// User.java içində:
package com.different.pkg; // XƏTA! qovluqla uyğun deyil
```

---

## 3. `package` elanı {#package-elani}

Hər Java faylının **ilk qeyri-şərh sətri** `package` elanı olmalıdır (əgər default paketdə deyilsə):

```java
package com.example.shop.service; // 1. Paket elanı (BİRİNCİ)

import com.example.shop.model.User; // 2. Import-lar

public class UserService {            // 3. Sinif
    public void createUser(User u) { }
}
```

### Qaydalar

- Hər fayl yalnız **bir** `package` elanı ola bilər
- `package` ifadəsindən əvvəl yalnız şərh (comment) ola bilər
- Paket adları hamısı **kiçik hərf** yazılır
- Ayıraç olaraq `.` (nöqtə) istifadə olunur

---

## 4. `import` direktivi {#import}

`import` — başqa paketdəki sinifləri cari faylda istifadə etmək üçündür.

### Tək sinif import (tövsiyə olunur)

```java
package com.example.shop;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class Kataloq {
    private List<String> məhsullar = new ArrayList<>();
    private Map<String, Integer> stok;
}
```

### Wildcard import (ehtiyatlı olun!)

```java
import java.util.*; // bütün java.util siniflərini idxal edir
```

**Wildcard-ın problemi:**

```java
import java.util.*;   // Date var
import java.sql.*;    // Date də var!

Date d = new Date(); // XƏTA! ambiguous — hansı Date?
```

### Tövsiyə

| Üsul | Nə vaxt |
|---|---|
| Tək-tək import | **Həmişə** (aydınlıq üçün) |
| Wildcard `*` | Qısa script/prototip üçün |

### `java.lang` avtomatik import olur

```java
// import java.lang.String;  // lazım deyil
// import java.lang.Integer; // lazım deyil
// import java.lang.System;  // lazım deyil

String s = "Salam"; // java.lang.* avtomatik idxal olur
```

---

## 5. Fully Qualified Name (FQN) {#fqn}

FQN — sinfin **tam adı**: `paket.SinifAdı`. Import olmadan da istifadə edə bilərik:

```java
package com.example;

public class FQNDemo {
    public static void main(String[] args) {
        // Import-sız — fully qualified name
        java.util.List<String> list = new java.util.ArrayList<>();

        // Eyni adlı siniflər qarışmasın deyə
        java.util.Date utilDate = new java.util.Date();
        java.sql.Date sqlDate = new java.sql.Date(0);
    }
}
```

### Nə vaxt FQN istifadə edək?

- İki eyni adlı sinif eyni faylda lazım olanda
- Kod bir dəfəlikdir və import ekstra yük gətirir
- Generated kod (framework çıxışı)

---

## 6. Static import {#static-import}

`static import` — class-ın static üzvlərini sinif adı olmadan istifadə etməyə imkan verir:

### Static import olmadan

```java
import java.lang.Math;

public class Nümunə {
    public static void main(String[] args) {
        double kök = Math.sqrt(25);
        double pi = Math.PI;
        int maks = Math.max(10, 20);
    }
}
```

### Static import ilə

```java
import static java.lang.Math.sqrt;
import static java.lang.Math.PI;
import static java.lang.Math.max;
// Və ya hamısını: import static java.lang.Math.*;

public class Nümunə {
    public static void main(String[] args) {
        double kök = sqrt(25);     // Math. yazmağa ehtiyac yox
        double pi = PI;
        int maks = max(10, 20);
    }
}
```

### Çox tipik istifadə — test-lərdə

```java
import static org.junit.jupiter.api.Assertions.*;

class UserTest {
    @Test
    void testUser() {
        User u = new User("Əli");
        assertEquals("Əli", u.getName()); // Assertions. yazmadan
        assertNotNull(u);
        assertTrue(u.isActive());
    }
}
```

### Ehtiyatlı ol — hədsiz istifadə zərərlidir

```java
import static java.lang.Math.*;
import static java.lang.Integer.*;

// Sonra oxuyanlar `MAX_VALUE`-nun haradan gəldiyini bilməzlər
int x = MAX_VALUE; // Integer.MAX_VALUE? Long.MAX_VALUE?
```

---

## 7. Default (adsız) paket — anti-pattern {#default-paket}

Əgər `package` elanı yazmasaq, sinif **default (adsız) pakete** düşür:

```java
// Heç bir package ifadəsi yoxdur
public class Proqram {
    public static void main(String[] args) {
        System.out.println("Salam");
    }
}
```

### Niyə default paket pisdir?

1. **Default paketdəki sinifləri import etmək olmaz** — başqa paketlərdən istifadə edilə bilməz
2. **Build tool-lar (Maven/Gradle) problem yaşayır**
3. **JPMS (Java 9+) dəstəkləmir** — modul sistemi tanımır
4. **Kollaborasiyada qarışıqlıq** — hamı eyni "heç paket" yerinə sinif atır

### Tövsiyə

**Həmişə** hər class-ı bir paketə qoyun:

```java
package com.example.demo;

public class Proqram {
    public static void main(String[] args) {
        System.out.println("Salam");
    }
}
```

---

## 8. Access modifier-lər — 4 səviyyə {#access-modifiers}

Java-da **4** səviyyəli görünmə var:

| Modifier | Açar söz | Niyə istifadə olunur |
|---|---|---|
| Public | `public` | Hər yerdən görünür |
| Protected | `protected` | Eyni paket + subclass-lar |
| Package-private | (heç bir açar söz) | Yalnız eyni paket |
| Private | `private` | Yalnız eyni class |

### Public

```java
package com.example.api;

public class PublicAPI {
    public int sayi = 42;

    public void çap() {
        System.out.println(sayi);
    }
}

// Hər yerdən əlçatandır:
// import com.example.api.PublicAPI;
// PublicAPI p = new PublicAPI();
// p.sayi; p.çap();
```

### Private

```java
public class BankHesabi {
    private double balans = 0; // heç kim birbaşa müraciət edə bilməz

    // Kontrollü giriş — getter/setter
    public double getBalans() {
        return balans;
    }

    public void yatır(double mebleg) {
        if (mebleg > 0) {
            balans += mebleg;
        }
    }
}

// İstifadə:
BankHesabi h = new BankHesabi();
// h.balans = 1000000; // XƏTA! private
h.yatır(500); // OK
```

### Protected

```java
// file: com/example/base/Heyvan.java
package com.example.base;

public class Heyvan {
    protected String növ;

    protected void yat() {
        System.out.println(növ + " yatır");
    }
}

// file: com/example/base/It.java (eyni paket)
package com.example.base;

public class It {
    public void test() {
        Heyvan h = new Heyvan();
        h.növ = "Pişik"; // OK — eyni paket
        h.yat();         // OK
    }
}

// file: com/example/special/Pələng.java (fərqli paket, subclass)
package com.example.special;

import com.example.base.Heyvan;

public class Pələng extends Heyvan {
    public void yırtıcı() {
        this.növ = "Pələng"; // OK — subclass
        this.yat();          // OK
    }
}
```

### Package-private (default)

```java
// file: com/example/utils/DaxiliYardımçı.java
package com.example.utils;

class DaxiliYardımçı { // heç bir modifier → package-private
    void köməkçi() {
        System.out.println("Yalnız com.example.utils içində istifadə olunur");
    }
}

// Eyni paketdə:
package com.example.utils;

public class İstifadəçi {
    public void test() {
        DaxiliYardımçı d = new DaxiliYardımçı(); // OK
        d.köməkçi();
    }
}

// Fərqli paketdə:
package com.example.main;

import com.example.utils.DaxiliYardımçı; // XƏTA! görünmür
```

---

## 9. Görünmə matrisi (visibility matrix) {#visibility}

```
            +----------------- HƏR YER ------------------+
            |                                            |
            |   +----------- PAKET DIŞI ----------+      |
            |   |                                 |      |
            |   |   +-- SUBCLASS (FƏRQLİ PKT) -+  |      |
            |   |   |                          |  |      |
            |   |   |   +-- EYNİ PAKET --+     |  |      |
            |   |   |   |                |     |  |      |
            |   |   |   |  +-- CLASS -+  |     |  |      |
            |   |   |   |  |          |  |     |  |      |
            |   |   |   |  | private  |  |     |  |      |
            |   |   |   |  | (default)|  |     |  |      |
            |   |   |   |  | protected|  |     |  |      |
            |   |   |   |  | public   |  |     |  |      |
            |   |   |   |  +----------+  |     |  |      |
            |   |   |   +----------------+     |  |      |
            |   |   +--------------------------+  |      |
            |   +---------------------------------+      |
            +--------------------------------------------+
```

### Əlçatanlıq cədvəli

| Modifier | Eyni class | Eyni paket | Fərqli paket, subclass | Fərqli paket, bağlı olmayan |
|---|---|---|---|---|
| `public` | ✓ | ✓ | ✓ | ✓ |
| `protected` | ✓ | ✓ | ✓ | ✗ |
| default (package-private) | ✓ | ✓ | ✗ | ✗ |
| `private` | ✓ | ✗ | ✗ | ✗ |

### Class səviyyəsində yalnız 2 seçim

Top-level (ən üst) class üçün:
- `public` — hər yerdən görünür (fayl adı class adı ilə eyni olmalıdır)
- default — yalnız eyni paketdən görünür

```java
// file: MyClass.java
public class MyClass { }     // OK
// private class X { }       // XƏTA — top-level private olmur
// protected class Y { }     // XƏTA — top-level protected olmur

class Helper { } // default — eyni paketdə istifadə oluna bilər
```

### Member səviyyəsində hamısı işləyir

```java
public class Tam {
    public int a;              // hər yerdən
    protected int b;           // eyni paket + subclass
    int c;                     // eyni paket (package-private)
    private int d;             // yalnız bu class

    public void pm() { }
    protected void prm() { }
    void dm() { }
    private void prvm() { }
}
```

---

## 10. Paket adlandırma konvensiyası {#naming}

### Reverse domain name

Şirkətinizin domen adını **tərsinə** yazın:

| Domain | Paket |
|---|---|
| google.com | `com.google.*` |
| apache.org | `org.apache.*` |
| netflix.com | `com.netflix.*` |
| example.com | `com.example.*` |

### Tipik struktur

```
com.example.myapp/
├── config/          ← konfiqurasiya
├── controller/      ← web handler-lər
├── service/         ← biznes məntiqi
├── repository/      ← DB giriş
├── model/           ← data class-lar (və ya entity)
├── dto/             ← Data Transfer Object
├── exception/       ← custom exception-lar
└── util/            ← köməkçi metodlar
```

### Qaydalar

- Hamısı **kiçik hərf**
- Rəqəmlə başlamır
- Java açar sözlərindən istifadə etmir (`int`, `class` və s.)
- Nöqtə ilə ayrılır, alt xətt ilə yox

```java
// DOĞRU
package com.example.shop.user;

// YANLIŞ
package Com.Example.Shop.User;       // böyük hərf
package com.example.1shop;           // rəqəmlə başlayır
package com.example.class.util;      // "class" açar sözüdür
package com.example.shop_user;       // alt xətt (tövsiyə olunmur)
```

---

## Ümumi Səhvlər

### 1. Qovluq və paket uyğunsuzluğu

```java
// fayl: src/com/foo/Bar.java
package com.wrong.path; // XƏTA
```

### 2. `package` ifadəsindən əvvəl kod

```java
import java.util.*;         // YANLIŞ — package-dən əvvəl
package com.example;        // package hər zaman birinci
```

### 3. Default paketdə yazmaq

```java
// Heç package ifadəsi yox → default paket → anti-pattern
public class Demo { }
```

### 4. Field-ləri `public` etmək

```java
public class User {
    public String password; // TƏHLÜKƏSİZLİK RİSKİ!
}

// DOĞRU — private + getter/setter
public class User {
    private String password;
    public String getPassword() { return password; }
}
```

### 5. Wildcard import-dan asılı olmaq

```java
import java.util.*;
import java.sql.*;

Date d; // hansı Date? — ambiguous
```

### 6. `protected` vs package-private qarışıqlığı

```java
// "protected" package-private-dan daha açıqdır!
// Default (heç nə yazma) yalnız eyni paketi buraxır
// Protected həm eyni paketi, həm də subclass-ları buraxır
```

### 7. Top-level class üçün `private` yazmaq

```java
private class X { } // XƏTA — top-level private ola bilməz
```

---

## İntervyu Sualları

**S1: Package nədir və niyə istifadə olunur?**
> Paket — əlaqəli sinifləri qruplaşdıran namespace-dir. Ad toqquşmalarını həll edir, kodu məntiqi bölmələrə ayırır və access control təmin edir. Qovluq strukturu ilə 1:1 uyğun olmalıdır.

**S2: 4 access modifier və onların fərqləri nələrdir?**
> `public` (hər yer), `protected` (eyni paket + subclass-lar), default/package-private (yalnız eyni paket), `private` (yalnız eyni class). Class səviyyəsində yalnız `public` və default işləyir; member səviyyəsində hər 4-ü.

**S3: `import` olmadan başqa paketdəki sinfi istifadə etmək olar?**
> Bəli — fully qualified name (FQN) ilə: `java.util.List list = new java.util.ArrayList();`. Lakin praktikada bu yol yalnız ad toqquşmalarında istifadə olunur.

**S4: Wildcard import (`*`) tövsiyə olunurmu?**
> Adətən yox. Kod oxunuşunu çətinləşdirir, ad toqquşmalarını gizlədir və IDE-lər ayrı import-ları avtomatik idarə edir. Konkret import hər zaman daha yaxşı praktikadır.

**S5: Default (package-private) access `protected`-dən nə ilə fərqlənir?**
> `protected` həm eyni paket, həm də **başqa paketdəki subclass-lar** üçün əlçatandır. Default isə **yalnız eyni paket** üçün. Yəni protected package-private-dan daha geniş əhatəlidir.

**S6: Static import nədir və nə vaxt istifadə olunur?**
> Class-ın static üzvlərini sinif adı olmadan çağırmaq üçündür: `Math.sqrt(x)` əvəzinə `sqrt(x)`. Ən tipik istifadə — unit test-lərdə (`assertEquals`, `assertTrue`). Hədsiz istifadə oxunuşu pisləşdirir.

**S7: Default paketdə class yazmaq niyə anti-pattern-dir?**
> Default paketdəki sinifləri başqa paketlərdən import etmək mümkün deyil. Maven/Gradle kimi build tool-larla problem yaradır, JPMS (Java 9+ modullar) tanımır və layihədə təşkilatsızlığa səbəb olur.

**S8: `java.lang.*` niyə avtomatik idxal olunur?**
> Çünki `String`, `Integer`, `System`, `Object`, `Math` və s. əsas siniflər hər Java proqramında istifadə olunur. JVM onları həmişə avtomatik idxal edir — `import` yazmağa ehtiyac yoxdur.

**S9: Paket adı niyə reverse domain (məs., `com.example`) formasındadır?**
> Qlobal unikalıq üçün. Hər təşkilatın öz domen adı var (məs., `example.com`), onu tərsinə yazmaqla bütün dünyada unikal paket namespace əldə olunur. Belə ki iki fərqli kitabxana eyni class adına malik olsa belə, paket səviyyəsində toqquşma baş verməz.

**S10: Package-private class nə vaxt istifadə edilməlidir?**
> İmplementation detail-ləri gizlətmək üçün. Məsələn, sizin kitabxanada yalnız paket daxilində istifadə olunan köməkçi class var — onu package-private etsəniz, xarici kod ondan asılı olmaz və gələcəkdə rahat dəyişə bilərsiniz. Bu encapsulation prinsipidir.
