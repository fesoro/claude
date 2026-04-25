# 07 — Şərtli İfadələr (if / else / switch)

> **Seviyye:** Beginner ⭐


## Mündəricat
1. [Şərtli ifadə nədir?](#serh-nedir)
2. [if ifadəsi](#if)
3. [if / else](#if-else)
4. [if / else if / else](#else-if)
5. [İç-içə (nested) if](#nested)
6. [Ternary — if-else-nin qısa forması](#ternary)
7. [Klassik switch (statement)](#klassik-switch)
8. [Fall-through və break](#fall-through)
9. [Switch expressions (Java 14+)](#switch-expr)
10. [Pattern matching for switch (Java 21+)](#pattern-switch)
11. [Real dünya nümunələri](#numuneler)
12. [Ümumi Səhvlər](#umumi-sehvler)
13. [İntervyu Sualları](#intervyu)

---

## 1. Şərtli ifadə nədir? {#serh-nedir}

**Şərtli ifadə** — proqrama fərqli yollarla getmək imkanı verir. "Əgər X olsa, Y et, olmasa Z et" məntiqi.

Real dünyada analogiya: "Yağış yağırsa, çətir götür. Yağmursa, günəş eynəyi götür."

Java-da 3 əsas üsul var:
1. `if` / `else if` / `else`
2. Ternary operator `?:`
3. `switch` (statement və expression)

---

## 2. if ifadəsi {#if}

**Şərt** `boolean` olmalıdır. True isə `{}` içindəki kod icra olunur, false isə atlanır.

```java
if (şərt) {
    // şərt true olanda işləyir
}
```

### Nümunə

```java
int yaş = 20;

if (yaş >= 18) {
    System.out.println("Yetkin");
}
```

### Tək sətir — mötərizəsiz (tövsiyə edilmir)

```java
if (yaş >= 18)
    System.out.println("Yetkin");  // işləyir, amma təhlükəli
```

Niyə təhlükəli?

```java
if (yaş >= 18)
    System.out.println("Yetkin");
    System.out.println("Səs verə bilər");  // HƏMİŞƏ işləyir — if-ə aid DEYİL!
```

Həmişə `{}` qoy, hətta tək sətir olsa belə.

---

## 3. if / else {#if-else}

```java
if (şərt) {
    // şərt true
} else {
    // şərt false
}
```

### Nümunə

```java
int yaş = 15;

if (yaş >= 18) {
    System.out.println("Yetkin");
} else {
    System.out.println("Uşaq");
}
```

---

## 4. if / else if / else {#else-if}

Çoxsaylı şərtləri yoxlamaq üçün.

```java
if (şərt1) {
    // şərt1 true
} else if (şərt2) {
    // şərt1 false, şərt2 true
} else if (şərt3) {
    // şərt1 false, şərt2 false, şərt3 true
} else {
    // heç biri true deyil
}
```

### Nümunə — qiymət dərəcəsi

```java
int xal = 78;
String dərəcə;

if (xal >= 90) {
    dərəcə = "A";
} else if (xal >= 80) {
    dərəcə = "B";
} else if (xal >= 70) {
    dərəcə = "C";
} else if (xal >= 60) {
    dərəcə = "D";
} else {
    dərəcə = "F";
}

System.out.println("Dərəcə: " + dərəcə);  // C
```

### HTTP status kategoriyası

```java
int status = 404;
String kategoriya;

if (status >= 100 && status < 200) {
    kategoriya = "Informational";
} else if (status >= 200 && status < 300) {
    kategoriya = "Success";
} else if (status >= 300 && status < 400) {
    kategoriya = "Redirect";
} else if (status >= 400 && status < 500) {
    kategoriya = "Client Error";
} else if (status >= 500 && status < 600) {
    kategoriya = "Server Error";
} else {
    kategoriya = "Naməlum";
}
```

---

## 5. İç-içə (nested) if {#nested}

`if` daxilində başqa `if`.

```java
int yaş = 25;
boolean sürücü = true;

if (yaş >= 18) {
    if (sürücü) {
        System.out.println("Avtomobil sürə bilər");
    } else {
        System.out.println("Əhliyyət almalıdır");
    }
} else {
    System.out.println("Çox uşaqdır");
}
```

### Daha yaxşı yazılışı — `&&` ilə birləşdir

```java
if (yaş >= 18 && sürücü) {
    System.out.println("Avtomobil sürə bilər");
} else if (yaş >= 18 && !sürücü) {
    System.out.println("Əhliyyət almalıdır");
} else {
    System.out.println("Çox uşaqdır");
}
```

### Erkən qayıtma (early return)

İç-içə if-dən qaçmaq üçün:

```java
// Pis — dərin yerləşmə
public String statusYoxla(User user) {
    if (user != null) {
        if (user.isActive()) {
            if (user.getBalance() > 0) {
                return "Tam aktiv";
            } else {
                return "Balansı yoxdur";
            }
        } else {
            return "Deaktivdir";
        }
    } else {
        return "Yoxdur";
    }
}

// Yaxşı — early return
public String statusYoxla(User user) {
    if (user == null) return "Yoxdur";
    if (!user.isActive()) return "Deaktivdir";
    if (user.getBalance() <= 0) return "Balansı yoxdur";
    return "Tam aktiv";
}
```

---

## 6. Ternary — if-else-nin qısa forması {#ternary}

```
şərt ? true-nəticə : false-nəticə
```

### Nümunə

```java
int a = 10, b = 20;

// if-else
int maks;
if (a > b) {
    maks = a;
} else {
    maks = b;
}

// Ternary — eyni iş
int maks = (a > b) ? a : b;  // 20
```

### Kompakt istifadə

```java
String statusu = (aktiv) ? "Aktiv" : "Deaktiv";

int mütləq = (n < 0) ? -n : n;

// Yerləşdirilmiş
int işarə = (n > 0) ? 1 : (n < 0) ? -1 : 0;
```

### Nə zaman istifadə?

- Tək dəyər təyin etmək üçün — əla.
- Çap etmək üçün — əla.
- Kompleks məntiq üçün — istifadə etmə, oxunaqlıq düşür.

---

## 7. Klassik switch (statement) {#klassik-switch}

Bir dəyişəni bir neçə variantla müqayisə edir.

```java
switch (dəyişən) {
    case dəyər1:
        // kod
        break;
    case dəyər2:
        // kod
        break;
    default:
        // heç biri uyğun gəlmədi
}
```

### Nümunə — gün adı

```java
int gün = 3;
String ad;

switch (gün) {
    case 1:
        ad = "Bazar ertəsi";
        break;
    case 2:
        ad = "Çərşənbə axşamı";
        break;
    case 3:
        ad = "Çərşənbə";
        break;
    case 4:
        ad = "Cümə axşamı";
        break;
    case 5:
        ad = "Cümə";
        break;
    case 6:
        ad = "Şənbə";
        break;
    case 7:
        ad = "Bazar";
        break;
    default:
        ad = "Naməlum";
}

System.out.println(ad);  // "Çərşənbə"
```

### Hansı tiplər dəstəklənir?

- `byte`, `short`, `int`, `char` və onların wrapper-ləri
- `String` (Java 7+)
- `enum`
- `Object` — pattern matching ilə (Java 21+)

`long`, `float`, `double`, `boolean` — dəstəklənmir.

### String ilə switch

```java
String rol = "admin";

switch (rol) {
    case "admin":
        System.out.println("Tam icazə");
        break;
    case "user":
        System.out.println("Məhdud icazə");
        break;
    case "guest":
        System.out.println("Oxu icazəsi");
        break;
    default:
        System.out.println("Naməlum rol");
}
```

### Enum ilə switch

```java
enum Rəng { QIRMIZI, YAŞIL, MAVİ }

Rəng r = Rəng.QIRMIZI;

switch (r) {
    case QIRMIZI:
        System.out.println("Dayan");
        break;
    case YAŞIL:
        System.out.println("Get");
        break;
    case MAVİ:
        System.out.println("Göy");
        break;
}
```

Qeyd: Enum case-də `Rəng.QIRMIZI` yazmaq lazım deyil — sadəcə `QIRMIZI`.

---

## 8. Fall-through və break {#fall-through}

`break` olmasa, növbəti case **də** işləyir — buna **fall-through** deyilir.

### Fall-through səhvi

```java
int gün = 2;

switch (gün) {
    case 1:
        System.out.println("Bazar ertəsi");
        // break yoxdur!
    case 2:
        System.out.println("Çərşənbə axşamı");
        // break yoxdur!
    case 3:
        System.out.println("Çərşənbə");
        break;
    default:
        System.out.println("Qalan günlər");
}

// Çıxış:
// Çərşənbə axşamı
// Çərşənbə
```

### Fall-through MƏQSƏDLİ istifadə

Bir neçə case eyni kod icra etsin:

```java
int ay = 4;

switch (ay) {
    case 12:
    case 1:
    case 2:
        System.out.println("Qış");
        break;
    case 3:
    case 4:
    case 5:
        System.out.println("Yaz");
        break;
    case 6:
    case 7:
    case 8:
        System.out.println("Yay");
        break;
    case 9:
    case 10:
    case 11:
        System.out.println("Payız");
        break;
}
// Çıxış: Yaz
```

---

## 9. Switch expressions (Java 14+) {#switch-expr}

Java 14-dən etibarən switch bir **dəyər qaytara** bilər. Ox `->` sintaksisi istifadə olunur və `break` lazım deyil.

### Köhnə üsul

```java
int gün = 3;
String ad;
switch (gün) {
    case 1: ad = "Bazar ertəsi"; break;
    case 2: ad = "Çərşənbə axşamı"; break;
    case 3: ad = "Çərşənbə"; break;
    default: ad = "Naməlum";
}
```

### Yeni üsul — arrow `->`

```java
int gün = 3;
String ad = switch (gün) {
    case 1 -> "Bazar ertəsi";
    case 2 -> "Çərşənbə axşamı";
    case 3 -> "Çərşənbə";
    case 4 -> "Cümə axşamı";
    case 5 -> "Cümə";
    case 6 -> "Şənbə";
    case 7 -> "Bazar";
    default -> "Naməlum";
};

System.out.println(ad);  // "Çərşənbə"
```

Üstünlüklər:
- `break` lazım deyil — fall-through olmur.
- Dəyər qaytarır — təyin etmək daha qısa.
- `default` (və ya bütün enum case-ləri) məcburidir — kompilyator tələb edir.

### Çoxsaylı case eyni sətirdə

```java
String fəsil = switch (ay) {
    case 12, 1, 2  -> "Qış";
    case 3, 4, 5   -> "Yaz";
    case 6, 7, 8   -> "Yay";
    case 9, 10, 11 -> "Payız";
    default -> "Naməlum";
};
```

### Bir neçə sətir — `yield`

Mürəkkəb hesablamalar üçün `{ }` və `yield`:

```java
int xal = 75;

String dərəcə = switch (xal / 10) {
    case 10, 9 -> "Əla";
    case 8     -> "Yaxşı";
    case 7     -> {
        System.out.println("Orta hesab");
        yield "Qənaətbəxş";
    }
    case 6     -> "Keçdi";
    default    -> "Kəsildi";
};
```

---

## 10. Pattern matching for switch (Java 21+) {#pattern-switch}

Java 21-dən switch **obyekt tiplərini** yoxlaya bilər.

```java
Object o = 42;

String təsvir = switch (o) {
    case Integer i when i < 0 -> "Mənfi tam";
    case Integer i -> "Tam: " + i;
    case String s  -> "Mətn: " + s;
    case Double d  -> "Həqiqi: " + d;
    case null      -> "Yoxdur";
    default        -> "Başqa tip";
};

System.out.println(təsvir);  // "Tam: 42"
```

### `when` qeydi — guard clause

Bir case-də əlavə şərt:

```java
String kategoriya = switch (xal) {
    case Integer x when x >= 90 -> "Əla";
    case Integer x when x >= 70 -> "Yaxşı";
    case Integer x when x >= 50 -> "Keçdi";
    case Integer x              -> "Kəsildi";
    default                      -> "Yanlış";
};
```

### Record ilə dekomposisiya

```java
record Nöqtə(int x, int y) {}

Object obj = new Nöqtə(3, 4);

String s = switch (obj) {
    case Nöqtə(int x, int y) -> "X=" + x + ", Y=" + y;
    default -> "Naməlum";
};
```

---

## 11. Real dünya nümunələri {#numuneler}

### 11.1 Qiymət dərəcəsi (klassik)

```java
public class Qiymet {
    public static String dərəcə(int xal) {
        if (xal < 0 || xal > 100) {
            return "Yanlış xal";
        }

        if (xal >= 90) return "A";
        if (xal >= 80) return "B";
        if (xal >= 70) return "C";
        if (xal >= 60) return "D";
        return "F";
    }

    public static void main(String[] args) {
        System.out.println(dərəcə(95));  // A
        System.out.println(dərəcə(72));  // C
        System.out.println(dərəcə(45));  // F
    }
}
```

### 11.2 HTTP status kategoriyası (switch expression)

```java
public static String httpKateqoriyası(int status) {
    return switch (status / 100) {
        case 1 -> "Informational";
        case 2 -> "Success";
        case 3 -> "Redirect";
        case 4 -> "Client Error";
        case 5 -> "Server Error";
        default -> "Naməlum";
    };
}

System.out.println(httpKateqoriyası(200));  // Success
System.out.println(httpKateqoriyası(404));  // Client Error
System.out.println(httpKateqoriyası(500));  // Server Error
```

### 11.3 Kalkulyator

```java
public static double hesabla(double a, double b, char op) {
    return switch (op) {
        case '+' -> a + b;
        case '-' -> a - b;
        case '*' -> a * b;
        case '/' -> {
            if (b == 0) throw new ArithmeticException("Sıfıra bölmək olmaz");
            yield a / b;
        }
        default -> throw new IllegalArgumentException("Naməlum operator: " + op);
    };
}
```

### 11.4 FizzBuzz

```java
for (int i = 1; i <= 20; i++) {
    if (i % 15 == 0) {
        System.out.println("FizzBuzz");
    } else if (i % 3 == 0) {
        System.out.println("Fizz");
    } else if (i % 5 == 0) {
        System.out.println("Buzz");
    } else {
        System.out.println(i);
    }
}
```

---

## 12. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: `=` və `==` qarışıqlığı

```java
int x = 5;
if (x = 10) { ... }    // XƏTA — təyin edir, şərt deyil
if (x == 10) { ... }   // DOĞRU
```

Boolean-da daha riskli:

```java
boolean aktiv = true;
if (aktiv = false) { ... }   // HƏMİŞƏ false, çünki təyin edir
if (aktiv == false) { ... }  // DOĞRU
if (!aktiv) { ... }          // ƏN YAXŞI
```

### Səhv 2: switch-də `break` unutmaq

```java
switch (rol) {
    case "admin":
        izinVer();
        // break yoxdur — aşağı keçir!
    case "user":
        məhdudla();
        break;
}
```

Yeni `->` sintaksisi bu səhvi mümkünsüz edir.

### Səhv 3: `if` şərti boolean deyil

```java
int x = 5;
if (x) { ... }           // XƏTA — Java-da int boolean deyil
if (x != 0) { ... }      // DOĞRU
if (x > 0) { ... }       // DOĞRU
```

### Səhv 4: switch-də `null`

```java
String rol = null;
switch (rol) {           // NullPointerException!
    case "admin": ...
}
```

Həll:
- Əvvəl `if (rol == null)` yoxla.
- Java 21+ pattern switch-də `case null ->` mümkündür.

### Səhv 5: Ternary-ni ağlamaq

```java
// Çox oxumaq olmaz
String s = (a > 0) ? (b > 0 ? "hər ikisi müsbət" : "a müsbət") : (b > 0 ? "b müsbət" : "heç biri");

// İf-else ilə daha aydın
String s;
if (a > 0 && b > 0) s = "hər ikisi müsbət";
else if (a > 0) s = "a müsbət";
else if (b > 0) s = "b müsbət";
else s = "heç biri";
```

### Səhv 6: `else` səhv if-ə bağlanır

```java
if (a > 0)
    if (b > 0)
        System.out.println("Hər ikisi müsbət");
else
    System.out.println("???");  // Kimə aiddir?
```

Java qaydası: `else` **ən yaxın** if-ə aiddir. Qarışıqlığın qarşısını almaq üçün həmişə `{}` istifadə et.

### Səhv 7: Fall-through təsadüfi istifadə

```java
switch (yas) {
    case 18:
        System.out.println("Yetkin oldu");
    case 21:
        System.out.println("İçki ala bilər");
        break;
}
// yas = 18 olsa, HƏR İKİ mesaj yazılır!
```

---

## 13. İntervyu Sualları {#intervyu}

**S1: `if` şərti hansı tipdə olmalıdır?**
> Yalnız `boolean`. Java-da C/C++-dakı kimi `if (x)` (int olaraq) yazılmır. `if (x != 0)` və ya `if (x > 0)` yazılmalıdır. Bu qayda təsadüfi təyinetmə səhvlərinin (`if (x = 5)`) qarşısını alır.

**S2: switch hansı tipləri dəstəkləyir?**
> `byte`, `short`, `int`, `char` və onların wrapper-ləri, `String` (Java 7+), `enum`, Java 21+-da pattern matching ilə istənilən obyekt. `long`, `float`, `double`, `boolean` dəstəklənmir.

**S3: Klassik switch ilə switch expression arasında fərq nədir?**
> Klassik switch bir **statement**-dir (dəyər qaytarmır), `break` lazımdır, fall-through var. Switch expression (Java 14+) `->` sintaksisi ilə **dəyər qaytarır**, fall-through yoxdur, daha qısa və təhlükəsizdir. Kompilyator bütün hallara cavab verməyi tələb edir (exhaustiveness).

**S4: Fall-through nədir və nə zaman istifadə olunur?**
> `break` olmayanda növbəti case-in icra olunmasıdır. Məqsədli istifadə hala - bir neçə case eyni kodu icra etsin (case 12, 1, 2: "Qış"). Təsadüfi fall-through isə ən çox görülən `switch` səhvidir.

**S5: `if-else if` ilə `switch` arasında nə vaxt hansını seçirsən?**
> Əgər bir dəyişəni **dəqiq dəyərlərlə** müqayisə edirsənsə (gün nömrəsi, rol, enum) — `switch`. Şərtlər **range**-dirsə (xal >= 90) və ya mürəkkəbdirsə (`a > 0 && b < 10`) — `if-else if`. Performans fərqi çox kiçikdir — oxunaqlıq əsasdır.

**S6: Ternary operatoru nə zaman istifadə etməməlidir?**
> Yerləşdirilmiş (nested) ternary, mürəkkəb məntiq, və ya side-effect olduqda (`x > 0 ? print(a) : print(b)` — bu pisdir, çünki oxumaq çətindir). Qayda: əgər oxucuya 5 saniyədən çox vaxt tələb edirsə — if-else-ə çevir.

**S7: Pattern matching for switch nə gətirir?**
> Java 21-də switch artıq **obyekt tipinə** görə da dallanır. `case Integer i -> ...`, `case String s -> ...`. Əlavə olaraq `when` qeydi ilə şərt əlavə etmək, `case null ->` ilə null-u idarə etmək, record dekomposisiyası. Visitor pattern-ə ehtiyac azalır.

**S8: Niyə switch expression-da `default` məcburidir?**
> Çünki switch expression bir dəyər **qaytarmalıdır** — əgər heç bir case uyğun gəlməsə, dəyər yoxdur. Kompilyator bu boşluğu tutmur deyə, istifadəçi `default` yazmağa məcbur olur. Enum switch-də bütün dəyərlər əhatə olunubsa, `default` lazım deyil (exhaustiveness).

**S9: `else` qarışıqlığına qarşı nə edək?**
> Həmişə `{}` istifadə et. "Dangling else" problemi: `if (a) if (b) x(); else y();` burada `else` yaxındakı `if (b)`-yə aiddir, amma oxucu səhv başa düşə bilər. Mötərizələr bu şübhəni aradan qaldırır.

**S10: Guard clause (erkən qayıtma) nə üçün yaxşıdır?**
> Dərin yerləşmiş if-lərdən qurtulmağa kömək edir. Əsas məqsəd — "xoşbəxt yol"u (happy path) kodun sonunda saxlamaq, səhvləri əvvəlcədən idarə etmək. Bu, cyclomatic complexity-ni azaldır və oxunaqlığı artırır.
