# equals(), hashCode() və Bərabərlik

> **Seviyye:** Beginner ⭐

## Giriş

Java-da iki obyektin "bərabər olması" sualı göründüyündən daha mürəkkəbdir. Çünki **iki fərqli bərabərlik** var:

1. **Referans bərabərliyi** (`==`): İki dəyişən **eyni yaddaş ünvanına** işarə edirmi?
2. **Məzmun bərabərliyi** (`equals()`): İki obyektin **dəyərləri** bərabərdirmi?

Məsələn: iki nəfərin adı "Orxan"-dırsa, onlar eyni adamdır? Xeyr! Ad eynidir, amma fərqli insanlardır. Java-da `==` "eyni adamdırmı?", `equals()` isə "eyni ada sahibdirmi?" sualını verir.

PHP-də isə bu fərq `==` (loose) və `===` (strict) ilə verilir, amma məna fərqlidir. Bu fayl hər iki dildə bərabərliyi sıfırdan izah edir, `hashCode`-un nə üçün lazım olduğunu göstərir və məşhur trapləri aydınlaşdırır.

---

## Java-da istifadəsi

### == vs equals() -- əsas fərq

```java
String a = new String("Salam");
String b = new String("Salam");

System.out.println(a == b);        // false -- fərqli obyektlər!
System.out.println(a.equals(b));   // true  -- məzmun eynidir
```

- `a == b` -- `a` və `b` **eyni yaddaş xanasına** işarə edirmi?
- `a.equals(b)` -- `a` və `b`-nin məzmunu bərabərdirmi?

### Primitive tiplər üçün == işləyir

```java
int x = 5;
int y = 5;
System.out.println(x == y);   // true -- primitive tiplər üçün == dəyər müqayisəsi

double a = 3.14;
double b = 3.14;
System.out.println(a == b);   // true
```

Primitive tiplər üçün `equals()` **yoxdur** -- çünki onlar obyekt deyil.

### Object.equals() default davranışı

Bütün siniflər `Object`-dən miras alır. `Object.equals()` default olaraq `==` ilə eyni işləyir:

```java
// Object sinfinin default equals:
public boolean equals(Object obj) {
    return this == obj;   // yalnız referans müqayisəsi
}
```

Ona görə, əgər siniflərdə `equals()` override etməsəniz:

```java
public class Nöqtə {
    int x, y;
    public Nöqtə(int x, int y) { this.x = x; this.y = y; }
}

Nöqtə a = new Nöqtə(1, 2);
Nöqtə b = new Nöqtə(1, 2);

System.out.println(a.equals(b));   // false -- default == davranışı
```

Bu əksər hallarda istədiyiniz kimi deyil. Ona görə `equals()`-i override etmək lazımdır.

### equals() düzgün override etmək

```java
public class Nöqtə {
    private final int x, y;

    public Nöqtə(int x, int y) {
        this.x = x;
        this.y = y;
    }

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;                   // 1. eyni obyekt?
        if (o == null || getClass() != o.getClass()) return false;  // 2. null və ya fərqli sinif?
        Nöqtə nöqtə = (Nöqtə) o;                      // 3. cast
        return x == nöqtə.x && y == nöqtə.y;          // 4. sahələri müqayisə et
    }

    @Override
    public int hashCode() {
        return Objects.hash(x, y);
    }
}

Nöqtə a = new Nöqtə(1, 2);
Nöqtə b = new Nöqtə(1, 2);
System.out.println(a.equals(b));   // indi true!
```

### equals() kontraktı

Override edərkən bu qaydalara riayət etmək lazımdır:

1. **Reflexive:** `x.equals(x)` həmişə `true` olmalıdır.
2. **Symmetric:** `x.equals(y) == y.equals(x)`.
3. **Transitive:** `x.equals(y)` və `y.equals(z)` isə `x.equals(z)`.
4. **Consistent:** Eyni məlumatla, `equals()` həmişə eyni nəticə qaytarmalıdır.
5. **Null-safe:** `x.equals(null)` həmişə `false` qaytarmalıdır.

```java
// Reflexive
a.equals(a);   // true

// Symmetric
a.equals(b);   // true
b.equals(a);   // true (eyni olmalıdır)

// Transitive
a.equals(b);   // true
b.equals(c);   // true
a.equals(c);   // true (olmalıdır)

// Null-safe
a.equals(null);  // false (NullPointerException yox!)
```

### hashCode() -- niyə lazımdır?

`HashMap`, `HashSet` kimi kolleksiyalar **hash** əsaslı işləyir:

```java
Map<Nöqtə, String> xəritə = new HashMap<>();
xəritə.put(new Nöqtə(1, 2), "Bakı");

String nəticə = xəritə.get(new Nöqtə(1, 2));
System.out.println(nəticə);   // null! (əgər hashCode override olunmayıbsa)
```

Niyə `null`? Çünki `HashMap` belə işləyir:

1. `put` zamanı: `key.hashCode()` hesablanır -> bucket müəyyən olunur -> içərisində `equals()` ilə axtarılır
2. `get` zamanı: yenə `key.hashCode()` hesablanır -> bucket axtarılır -> `equals()` ilə tapılır

Əgər `equals()` override olunub, amma `hashCode()` yox -- iki **bərabər obyekt fərqli bucket**-larda ola bilər, tapılmazlar.

### hashCode() kontraktı

1. **Consistent:** Eyni obyekt üçün `hashCode()` həmişə eyni nəticə qaytarmalıdır (obyekt dəyişmirsə).
2. **equals -> hashCode:** Əgər `a.equals(b)` `true`-dursa, `a.hashCode() == b.hashCode()` **olmalıdır**.
3. **Tərs qaydada vacib deyil:** İki fərqli obyektin eyni hashCode-u ola bilər (collision).

**Qızıl qayda:** `equals` override edirsinizsə, `hashCode` da override edin. Həmişə.

### IDE avtomatik yaradır

IntelliJ-də: `Alt + Insert` -> `equals() and hashCode()` seçin. IDE sizin üçün düzgün kod yazır:

```java
@Override
public boolean equals(Object o) {
    if (this == o) return true;
    if (o == null || getClass() != o.getClass()) return false;
    Nöqtə nöqtə = (Nöqtə) o;
    return x == nöqtə.x && y == nöqtə.y;
}

@Override
public int hashCode() {
    return Objects.hash(x, y);
}
```

### Record avtomatik yaradır

Java 14+ Record-larda `equals` və `hashCode` **avtomatik** yaradılır:

```java
public record Nöqtə(int x, int y) { }

Nöqtə a = new Nöqtə(1, 2);
Nöqtə b = new Nöqtə(1, 2);
System.out.println(a.equals(b));   // true (avtomatik!)
System.out.println(a.hashCode() == b.hashCode());   // true
```

### Lombok @EqualsAndHashCode

Lombok istifadə etsək:

```java
@EqualsAndHashCode
public class Nöqtə {
    private int x;
    private int y;
}
```

Build zamanı Lombok avtomatik `equals` və `hashCode` yaradır.

Xüsusi seçim:

```java
@EqualsAndHashCode(of = {"id"})   // yalnız id üzrə
public class İstifadəçi {
    private Long id;
    private String ad;
    private String email;
}
```

### String pool trap: "abc" == "abc"

```java
String a = "Salam";
String b = "Salam";
System.out.println(a == b);   // true?! Xeyr, gözləmə...
```

Cavab: **true**. Niyə? Çünki Java **string pool**-u var: literal sətirlər (`"Salam"` kimi) yaddaşda **təkrarlanmır**. Eyni literal həmişə eyni obyektə işarə edir.

Amma:

```java
String a = "Salam";
String b = new String("Salam");   // yeni obyekt yaradır
System.out.println(a == b);        // false!
System.out.println(a.equals(b));   // true
```

**Qayda:** String müqayisəsində **həmişə** `equals()` istifadə edin. `==` yanlış nəticələrə gətirə bilər.

### Integer cache trap: -128..127

```java
Integer a = 100;
Integer b = 100;
System.out.println(a == b);   // true?!

Integer c = 200;
Integer d = 200;
System.out.println(c == d);   // false?!
```

Niyə fərq? Çünki Java `Integer.valueOf()` -128..127 aralığında olan dəyərləri **keşləyir**:

```java
Integer.valueOf(100);  // keşdən qaytarır (həmişə eyni obyekt)
Integer.valueOf(200);  // yeni obyekt yaradır (hər dəfə)
```

**Qayda:** `Integer`, `Long`, `Boolean` kimi wrapper tiplərində **həmişə** `equals()` istifadə edin. `==` Integer cache tələsinə düşürə bilər.

### Əgər equals override, hashCode yox olsa?

```java
public class İstifadəçi {
    String ad;

    public İstifadəçi(String ad) { this.ad = ad; }

    @Override
    public boolean equals(Object o) {
        if (!(o instanceof İstifadəçi)) return false;
        return this.ad.equals(((İstifadəçi) o).ad);
    }
    // hashCode override olunmayıb!
}

Set<İstifadəçi> kolleksiya = new HashSet<>();
kolleksiya.add(new İstifadəçi("Orxan"));
System.out.println(kolleksiya.contains(new İstifadəçi("Orxan")));   // false!?
```

Bəli, `false` qaytarır. Çünki `HashSet` əvvəl `hashCode()` ilə bucket tapır -- iki fərqli obyektin fərqli hashCode-u var, ona görə `equals()` heç çağırılmır.

---

## PHP-də istifadəsi

### == (loose) və === (strict)

```php
<?php
var_dump(5 == "5");    // true -- dəyərlər bərabər (tip çevrilir)
var_dump(5 === "5");   // false -- tip də yoxlanılır

var_dump(0 == "abc");  // false (PHP 8+), true (PHP 7)
var_dump(null == false);   // true
var_dump(null === false);  // false

var_dump("1" == "01");  // true
var_dump("1" === "01"); // false
```

### Obyekt müqayisəsi

PHP-də `==` və `===` obyektlər üçün fərqli mənalar daşıyır:

```php
<?php
class İstifadəçi {
    public function __construct(public string $ad, public int $yaş) {}
}

$a = new İstifadəçi("Orxan", 25);
$b = new İstifadəçi("Orxan", 25);
$c = $a;

// == loose comparison for objects
var_dump($a == $b);   // true -- eyni sinif, eyni sahələr
var_dump($a == $c);   // true

// === strict comparison for objects
var_dump($a === $b);  // false -- fərqli obyektlər!
var_dump($a === $c);  // true  -- $c, $a-nın referansıdır
```

**Fərq Java ilə:**
- PHP `==`: sahələri avtomatik müqayisə edir (Java-da `equals()`-ə bənzər, amma override etmədən)
- PHP `===`: referans müqayisəsi (Java-da `==`-ə bənzər)

### PHP-də equals override yoxdur

PHP-də Java-dakı kimi `equals()` override yoxdur. Amma custom müqayisə metodu yazmaq olar:

```php
<?php
class İstifadəçi {
    public function __construct(public string $ad, public int $yaş) {}

    public function equals(İstifadəçi $other): bool {
        return $this->ad === $other->ad && $this->yaş === $other->yaş;
    }
}

$a = new İstifadəçi("Orxan", 25);
$b = new İstifadəçi("Orxan", 25);
var_dump($a->equals($b));   // true
```

Amma PHP-nin daxili funksiyaları (`in_array`, `array_unique` və s.) bu custom metodu **bilmir** -- ona görə Java-dakı kimi universal işləmir.

### hashCode PHP-də yoxdur

PHP-də `hashCode` konsepti yoxdur. `array_key_exists` və `SplObjectStorage` kimi strukturlar fərqli işləyir:

```php
<?php
$map = new SplObjectStorage();
$a = new İstifadəçi("Orxan", 25);
$map[$a] = "data";

$b = new İstifadəçi("Orxan", 25);
var_dump($map->contains($b));   // false -- çünki referans müqayisəsi edir
```

PHP `SplObjectStorage` referans əsaslıdır, məzmun əsaslı deyil. Əgər məzmun əsaslı lazımdırsa, əl ilə yazmaq lazımdır.

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Referans müqayisəsi | `==` | `===` |
| Məzmun müqayisəsi | `.equals()` | `==` (obyekt üçün avtomatik) |
| Custom equals | Override `equals()` | Custom metod (built-in yoxdur) |
| hashCode | Var (obyekt kontraktı) | Yoxdur |
| String `==` | Dəyişkən (pool) | Loose comparison |
| Integer cache | -128..127 (== tələsi) | Yoxdur |
| Auto-equals | Record / Lombok | Obyekt üçün `==` avtomatik |
| Null safety | `x.equals(null)` OK | Yaxşı dəstəklənir |
| Kolleksiyada bərabərlik | `equals + hashCode` | Referans əsaslı |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java-da `==` **primitivlər üçün dəyər, obyektlər üçün referans** müqayisəsi edir. Bu performanslıdır -- yalnız ünvan müqayisəsi sürətlidir. Amma proqramçılar çox vaxt məzmun müqayisəsi istəyir, ona görə `equals()` metod kimi əlavə olundu -- bu isə override oluna bilər.

`hashCode()` isə HashMap və HashSet üçün lazımdır. Hash cədvəlləri O(1) axtarış vaxtı təmin edir, amma bu yalnız hashCode düzgün olduqda. Ona görə Java `equals/hashCode` kontraktını ciddi təsbit edib.

### PHP-nin yanaşması

PHP dinamik tipli dildir -- `"5" == 5` bərabərliyini dəstəkləmək lazım idi (veb formalarında gələn string rəqəmlər üçün). Amma bu çox vaxt səhvlərə gətirdi, ona görə PHP 4-də `===` əlavə olundu -- "strict" müqayisə.

Obyektlər üçün isə fərqli qərar verildi: `==` avtomatik sahə-sahə müqayisə edir. Bu Java-dakı `equals()`-in avtomatik versiyası kimidir, amma override edilə bilmir.

PHP-də `hashCode` yoxdur, çünki PHP array-ləri onsuz da hash cədvəlidir -- amma key kimi yalnız string və int istifadə olunur.

### Praktik tövsiyə

- Java-da **her domain obyektində** `equals/hashCode` override edin (Record istifadə edərək, yaxud IDE ilə yaradın).
- PHP-də domain obyekt müqayisəsində diqqətli olun -- default `==` işləyir, amma custom məntiq üçün `equals()` metodu yazın.
- Java-da `Objects.equals(a, b)` null-safe müqayisədir.
- String müqayisəsində Java-da həmişə `.equals()` istifadə edin.

---

## Ümumi səhvlər (Beginner traps)

### 1. String müqayisəsində ==

```java
String a = getAdınızı();
if (a == "Orxan") { /* yanlış! */ }
if (a.equals("Orxan")) { /* düzgün */ }
if ("Orxan".equals(a)) { /* null-safe! */ }
```

### 2. equals override, hashCode yox

```java
// HashSet-də tapılmayacaq
Set<İstifadəçi> s = new HashSet<>();
s.add(new İstifadəçi("Ali"));
s.contains(new İstifadəçi("Ali"));   // false?!
```

Hər zaman ikisini birlikdə override edin.

### 3. Integer cache tələsi

```java
Integer a = 127;
Integer b = 127;
System.out.println(a == b);   // true (keşdə)

Integer c = 128;
Integer d = 128;
System.out.println(c == d);   // false (yeni obyekt)
```

Həll: `.equals()` istifadə edin.

### 4. Mutable sahələr üçün hashCode

```java
public class İstifadəçi {
    public int id;

    @Override
    public int hashCode() { return id; }
}

Set<İstifadəçi> s = new HashSet<>();
İstifadəçi u = new İstifadəçi();
u.id = 1;
s.add(u);

u.id = 2;   // id dəyişdi!
s.contains(u);   // false! Çünki hashCode dəyişdi
```

**Qayda:** hashCode-da istifadə olunan sahələr **dəyişməz** olmalıdır (final).

### 5. getClass() vs instanceof

```java
// Option 1: getClass
if (o == null || getClass() != o.getClass()) return false;

// Option 2: instanceof
if (!(o instanceof İstifadəçi)) return false;
```

`getClass()` **ciddi tip uyğunluğu** yoxlayır -- subclass-lar bərabər olmaz. `instanceof` isə subclass-a icazə verir. Record-lar və Effective Java `getClass()` tövsiyə edir (symmetric olmaq üçün).

### 6. PHP === ilə obyekt müqayisəsi

```php
$a = new User("Ali");
$b = new User("Ali");
if ($a === $b) { /* false! Fərqli obyektlər */ }
if ($a == $b)  { /* true, sahələr eyni */ }
```

---

## Mini müsahibə sualları

**1. `==` və `.equals()` arasındakı fərq nədir və nə vaxt hansı istifadə olunur?**

Cavab: `==` primitivlər üçün dəyər müqayisəsi, obyektlər üçün **referans** (yaddaş ünvanı) müqayisəsi edir. `.equals()` isə obyektlərin **məzmununu** müqayisə edir (əgər override edilibsə). Qayda: primitivlər üçün `==`, obyektlər üçün `.equals()` istifadə edin. String, Integer, Double kimi wrapper və obyekt tipləri üçün `==` tələ yarada bilər (string pool, Integer cache). Həmişə `.equals()` yazın, yaxud null-safe üçün `Objects.equals(a, b)`.

**2. Niyə `equals()` override edirsinizsə, `hashCode()` də override etməlisiniz?**

Cavab: `HashMap` və `HashSet` kimi hash əsaslı kolleksiyalar obyekti tapmaq üçün əvvəlcə `hashCode()` ilə bucket müəyyən edir, sonra bucket daxilində `equals()` ilə axtarır. Əgər iki **bərabər obyektin** (`equals` true) fərqli hashCode-u varsa, onlar fərqli bucket-larda olacaq -- kolleksiya onları tapmayacaq. Bu `equals/hashCode` kontraktının pozulmasıdır. Qısa qayda: `a.equals(b) == true` isə `a.hashCode() == b.hashCode()` olmalıdır.

**3. `String a = "abc"; String b = "abc"; a == b` nə qaytarır? Niyə?**

Cavab: `true`. Çünki Java-da **string pool** var: literal string-lər (`"abc"` kimi) yaddaşda təkrarlanmır. Eyni literal həmişə eyni obyektə işarə edir. Amma `new String("abc")` istifadə etsəniz, yeni obyekt yaranır və `==` `false` qaytarar. Ona görə **heç vaxt** string-də `==` istifadə etməyin -- `.equals()` yazın.

**4. Record-lar `equals` və `hashCode`-u necə idarə edir?**

Cavab: Java 14+ Record-ları **avtomatik** düzgün `equals`, `hashCode` və `toString` yaradır. Record deklarasiyasında olan bütün sahələrə (component-lərə) əsaslanaraq. `public record Nöqtə(int x, int y) {}` yazsanız, kompilyator özü `equals` və `hashCode` yazır -- boilerplate yoxdur. Bu Record-ların "data class" məqsədini dəstəkləyir. Lombok `@EqualsAndHashCode` adi siniflərdə oxşar iş görür.

**5. Java-da equals override edib hashCode override etməyən sinif HashSet-də necə davranır?**

Cavab: Pis davranır! `HashSet` əvvəl `hashCode()` ilə bucket tapır. Default `Object.hashCode()` hər obyekt üçün unikal rəqəm qaytarır (referans əsaslı). Bərabər obyektlər fərqli hashCode-lara düşür -- fərqli bucket-larda olurlar. `contains()` çağırılanda, hashCode fərqli olduğu üçün `equals()` heç çağırılmır, `false` qaytarır. Nəticədə `HashSet`-də "duplicates" görünə bilər -- baxmayaraq ki, `.equals()` ilə bərabərdirlər. Bu, interview-da klassik "bug tapma" sualıdır.
