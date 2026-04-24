# Access Modifiers (Giriş Modifikatorları) Tam Bələdçi

> **Seviyye:** Beginner ⭐

## Giriş

**Access modifier** -- bir sinfin, sahənin (field) və ya metodun **haradan görünə biləcəyini** müəyyənləşdirən açar sözdür. Bu, obyekt-yönümlü proqramlaşdırmada **encapsulation** (kapsullama) prinsipinin əsasıdır. Java-da 4 səviyyə var: `public`, `private`, `protected` və **package-private** (default, heç nə yazmırsan). PHP-də 3 səviyyə var: `public`, `private`, `protected`. Paket konsepti PHP-də fərqli işlədiyi üçün `package-private` müqabili yoxdur. Bu fayl sıfırdan başlayanlar üçün hər səviyyəni real kodla izah edir.

---

## Java-də istifadəsi

### 4 access modifier

```java
public    // hər yerdən görünür
protected // eyni paketdən + subclass-lardan
          // (heç nə yazılmayıb = package-private) eyni paketdən
private   // yalnız sinfin özündən
```

### Visibility matrix (Görünmə cədvəli)

| Modifier | Sinif daxilində | Eyni paket | Subclass (başqa paket) | Hər yerdən |
|---|---|---|---|---|
| `public` | Bəli | Bəli | Bəli | Bəli |
| `protected` | Bəli | Bəli | Bəli | Xeyr |
| package-private (default) | Bəli | Bəli | Xeyr | Xeyr |
| `private` | Bəli | Xeyr | Xeyr | Xeyr |

### Real nümunə: bütün 4 səviyyə bir yerdə

```java
package com.example.bank;

public class Hesab {
    public    String hesabNomresi;   // hər yerdən görünür
    protected double balans;         // subclass-larda görünür
              String filial;         // (package-private) yalnız bu paketdə
    private   String pin;            // yalnız bu sinif daxilində

    public Hesab(String nomre, String pin) {
        this.hesabNomresi = nomre;
        this.pin = pin;
    }

    public double balansiGoster() {
        return balans;        // private deyil, subclass-da işləyir
    }

    private boolean pinDogrula(String giris) {
        return this.pin.equals(giris);   // yalnız burada
    }

    public boolean pulCixar(double mebleg, String giris) {
        if (pinDogrula(giris) && balans >= mebleg) {
            balans -= mebleg;
            return true;
        }
        return false;
    }
}
```

Başqa paket:

```java
package com.example.app;

import com.example.bank.Hesab;

public class Istifadeci {
    void sinaq() {
        Hesab h = new Hesab("AZ12...", "1234");
        h.hesabNomresi;          // OK (public)
        // h.balans;              // XƏTA (protected, fərqli paket, subclass deyil)
        // h.filial;              // XƏTA (package-private, fərqli paket)
        // h.pin;                 // XƏTA (private)
        h.balansiGoster();       // OK (public)
    }
}
```

### Class-level (sinif səviyyəsində) modifikatorlar

**Top-level sinif** (yəni faylda əsas sinif) YALNIZ `public` və ya package-private ola bilər. `private` və `protected` top-level sinifdə XƏTA verir.

```java
// Hesab.java
public class Hesab { }            // OK
class YardimciSinif { }           // OK, package-private

// private class Gizli { }        // XƏTA: top-level sinif private ola bilməz
// protected class Qorunan { }    // XƏTA
```

**Nested class** (sinif içində sinif) isə BÜTÜN 4 modifikatoru dəstəkləyir:

```java
public class Bank {
    public    static class HesabBuilder { }
    protected static class DaxiliUtil { }
              static class PaketYardimcisi { }
    private   static class Implementasiya { }
}
```

### Field / Method / Constructor üçün

**Field:**

```java
public class Mehsul {
    public String ad;            // hər yerdən oxunur/yazılır -- təhlükəli
    private double qiymet;       // yalnız daxildən
    protected int stok;          // subclass-lar dəyişə bilər

    // Getter / Setter
    public double getQiymet() { return qiymet; }
    public void setQiymet(double q) {
        if (q < 0) throw new IllegalArgumentException("mənfi ola bilməz");
        this.qiymet = q;
    }
}
```

**Constructor:**

```java
public class Konfiqurasiya {
    private Konfiqurasiya() { }   // dışarıdan new edilə bilməz (Singleton)

    public static Konfiqurasiya getInstance() {
        return new Konfiqurasiya();
    }
}
```

### Encapsulation niyə vacibdir

```java
// PİS: field public, invariant qoruya bilmirik
public class HesabBad {
    public double balans;   // istənilən yerdə dəyişilə bilər
}
HesabBad h = new HesabBad();
h.balans = -1_000_000;     // mənfi balans! heç bir yoxlama yoxdur

// YAXSI: private + setter ilə validation
public class HesabGood {
    private double balans;

    public double getBalans() { return balans; }

    public void depozit(double m) {
        if (m <= 0) throw new IllegalArgumentException();
        balans += m;
    }

    public void cixar(double m) {
        if (m <= 0 || m > balans) throw new IllegalArgumentException();
        balans -= m;
    }
}
```

### `sealed` class qısa qeyd

Java 17-də `sealed` gəldi -- hansı sinifin hansı siniflərdən inherit oluna biləcəyini məhdudlaşdırır. Access modifier deyil, amma eynilə **görünmə nəzarəti** ilə bağlıdır. Detallı izah faylı 24-də.

```java
public sealed class Sekil permits Dairə, Kvadrat, Uçbucaq { }
public final class Dairə extends Sekil { }
public final class Kvadrat extends Sekil { }
public non-sealed class Uçbucaq extends Sekil { }
```

### Java Module System (Java 9+)

Modul səviyyəsində `exports` və `opens` ilə paketləri gizlətmək olar. Yəni `public` sinif də modul xaricinə çıxmaya bilər:

```java
// module-info.java
module com.example.bank {
    exports com.example.bank.api;   // bu paket görünür
    // com.example.bank.internal  --> görünmür, gizli qalır
}
```

**Fərq:**
- **Paket** -- compile-time görünmə (access modifier ilə).
- **Modul** -- runtime səviyyəsində də gizlətmə.

Bu böyük enterprise layihələrdə internal implementation detallarını gizli saxlamaq üçün istifadə olunur.

### Private method inheritance-də

`private` metod subclass-da **görünmür** və override olunmur:

```java
public class Ana {
    private void gizli() { System.out.println("Ana gizli"); }
    public void aciq()   { gizli(); }
}

public class Ogul extends Ana {
    private void gizli() { System.out.println("Ogul gizli"); }
    // Bu override DEYİL -- yalnız eyni adda yeni metod
}

new Ogul().aciq();   // "Ana gizli" çap edir, "Ogul gizli" yox!
```

---

## PHP-də istifadəsi

### 3 access modifier

```php
public     // hər yerdən görünür
protected  // bu sinif + subclass-lardan
private    // yalnız bu sinifdən
```

PHP-də `package-private` YOXDUR. PHP namespace istifadə edir, amma namespace access nəzarətinə TƏSİR ETMİR. Yəni namespace yalnız ad toqquşmasının qarşısını alır, visibility yox.

### Real nümunə

```php
<?php
namespace App\Bank;

class Hesab
{
    public    string $hesabNomresi;
    protected float  $balans = 0.0;
    private   string $pin;

    public function __construct(string $nomre, string $pin)
    {
        $this->hesabNomresi = $nomre;
        $this->pin = $pin;
    }

    public function balansiGoster(): float
    {
        return $this->balans;
    }

    private function pinDogrula(string $giris): bool
    {
        return $this->pin === $giris;
    }

    public function pulCixar(float $mebleg, string $giris): bool
    {
        if ($this->pinDogrula($giris) && $this->balans >= $mebleg) {
            $this->balans -= $mebleg;
            return true;
        }
        return false;
    }
}
```

Dışarıdan istifadə:

```php
use App\Bank\Hesab;

$h = new Hesab("AZ12...", "1234");
echo $h->hesabNomresi;      // OK
// echo $h->balans;          // XƏTA (protected)
// echo $h->pin;             // XƏTA (private)
echo $h->balansiGoster();   // OK
```

### PHP 8.1+ readonly

PHP-də `readonly` modifikator property-lərə təyin olunur -- constructor-dan sonra dəyişdirilə bilməz:

```php
class Mehsul
{
    public function __construct(
        public readonly string $ad,
        public readonly float $qiymet,
    ) {}
}

$m = new Mehsul("Alma", 2.0);
echo $m->ad;           // OK
// $m->ad = "Armud";    // XƏTA
```

Java-də bunun müqabili `final` sahə + `public` getter kombinasiyasıdır (və ya `record`).

### PHP 8.4 asymmetric visibility

PHP 8.4-də yeni `public(set)`, `protected(set)`, `private(set)` qaydası var:

```php
class Hesab
{
    public private(set) float $balans = 0.0;
    // public olaraq oxunur, amma yalnız private olaraq dəyişilə bilər
}
```

Java-də bunun müqabili: public getter + private setter kombinasiyası.

### PHP-də private inheritance davranışı

```php
class Ana
{
    private function gizli(): void { echo "Ana"; }
    public function aciq(): void { $this->gizli(); }
}

class Ogul extends Ana
{
    private function gizli(): void { echo "Ogul"; }
}

(new Ogul())->aciq();  // "Ana" çap edir (Java ilə eyni)
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| `public` | Var | Var |
| `protected` | Var | Var |
| `private` | Var | Var |
| `package-private` | Var (default) | **Yoxdur** |
| Default modifier | package-private | `public` (çox vaxt) |
| Top-level class modifier | Yalnız `public` / package-private | Yalnız `public` (nested yoxdur əsasən) |
| Nested class | BÜTÜN 4-ü | Anonymous class var, named nested yoxdur |
| `readonly` property | `final` sahə | `readonly` keyword (8.1+) |
| Modul/paket səviyyəli gizlətmə | Modul sistemi (9+) | Yoxdur |
| Asymmetric visibility | Getter/setter ilə əl ilə | `public(set)` qaydası (8.4+) |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

1. **Paket fundamental konsepsiyadır:** Java-də `package` ilk gündən var idi. Package-private cözümsüz real bir ehtiyacdı: "bu sinif eyni modulun daxili detalıdır, amma modul daxilində paylaşılmalıdır."

2. **Enterprise fokusu:** Java böyük komandalar üçün dizayn olunub. 4 səviyyə daha incə nəzarət verir. Məsələn: util metodu paketdə paylaş, amma xaricdən gizlə.

3. **Modul sistemi (9+):** Paket yetərli olmadı. Böyük libraries internal paketləri `public` etməli idi (çünki package-private yetmirdi). Modul sistemi bu boşluğu doldurdu.

4. **Default `package-private` məntiqi:** Kompilyator səni "açıq şəkildə `public` yaz" sözünü məcbur edir. Yəni default olaraq daha məhdud -- təhlükəsizlik üçün.

### PHP-nin yanaşması

1. **Namespace ad toqquşmasının həllidir, access deyil:** PHP namespace-i daha gec əlavə etdi (5.3) və məqsədi yalnız ad bərabərliyidir. Access semantikası ilə birləşdirilmədi.

2. **Library müəllifi üçün bahalı deyil:** PHP-də hər `public` sinif çap oluna bilər. Proqramçılar `@internal` annotation və ya sənədləşdirmə ilə "xaricdən istifadə etmə" deyir.

3. **PSR standartları:** PHP icması PSR-1/PSR-12 standartları ilə `public/protected/private` istifadəsini standardlaşdırıb. Bu ehtiyacı əsasən ödəyir.

4. **PHP 8.4 yeniliklərinə baxış:** `readonly`, `public(set)` kimi xüsusiyyətlər göstərir ki, PHP Java-nın yolunu izləyir -- daha incə encapsulation.

---

## Ümumi səhvlər (Beginner traps)

### 1. Hər şeyi public etmək

```java
// PİS
public class Istifadeci {
    public String ad;
    public int yas;
    public String parol;   // TƏHLÜKƏLİ!
}

// YAXSI
public class Istifadeci {
    private String ad;
    private int yas;
    private String parol;

    public String getAd() { return ad; }
    public void setAd(String a) {
        if (a == null || a.isBlank()) throw new IllegalArgumentException();
        this.ad = a;
    }
    // parol üçün setter yoxdur -- yalnız constructor-da təyin olunur
}
```

Niyə pis? Çünki:
- Invariant qoruya bilmirik (yaş mənfi ola bilər)
- Refactoring çətinləşir
- Test zamanı mocking çətinləşir
- Gələcəkdə validation əlavə etmək mümkün olmur

### 2. Top-level sinifi private elan etməyə çalışmaq

```java
// private class X { }   // XƏTA: top-level sinif private ola bilməz
```

### 3. `protected` ilə `package-private` qarışdırmaq

```java
class Ana {
    protected int a;   // subclass-larda GÖRÜNÜR (fərqli paket də olsa)
              int b;   // yalnız eyni paketdə görünür
}
```

### 4. `private` metod override olunur zənn etmək

```java
class Ana {
    private void m() { }
}
class Ogul extends Ana {
    // @Override
    // private void m() { }   // Override DEYİL, sadəcə yeni metod
}
```

### 5. PHP-dən gələnlər: "default public" zənni

PHP-də field default visibility yoxdur (açıqca yazılmalıdır). Java-də default **package-private**. Hər birini açıq yazmaq daha aydındır.

---

## Mini müsahibə sualları

**1. Java-də `package-private` nə deməkdir və nə vaxt istifadə olunur?**

Heç bir modifier yazılmadıqda aktiv olur. Yalnız eyni paketdəki siniflər görə bilər. Library daxili helper sinifləri və ya eyni paketdə birlikdə işləyən siniflər üçün istifadə olunur. Xaricə çıxmamalı, amma daxildə paylaşılmalı olan kod üçün idealdır.

**2. `public class` və `class` arasında fərq nədir?**

`public class` bütün paketlərdən görünür. Açar söz olmayan `class` (yəni package-private) yalnız öz paketindən görünür. Amma bir faylda yalnız bir `public` top-level sinif ola bilər və faylın adı həmin sinfin adı ilə eyni olmalıdır.

**3. `protected` bir field-ə eyni paketdən giriş mümkündürmü?**

Bəli. `protected` həm subclass-lara (istənilən paketdə), həm də eyni paketdəki bütün siniflərə giriş verir. Yəni `protected` = package-private + subclass.

**4. `private` metodu override etmək mümkündürmü?**

Xeyr. Private metod subclass-da görünmür, ona görə override oluna bilməz. Subclass eyni ad və imza ilə yeni metod yarada bilər, amma bu override deyil -- sadəcə ayrı metoddur. Super klasın metodundan çağırılmayacaq.

**5. PHP-də `package-private` niyə yoxdur?**

PHP namespace-i access nəzarəti üçün yox, ad toqquşmasının qarşısını almaq üçün dizayn olunub. PHP icması `@internal` annotation və sənədləşdirmə ilə bu ehtiyacı ödəyir. Java isə ilk gündən paket konsepsiyasını access modifier sistemi ilə birləşdirib.
