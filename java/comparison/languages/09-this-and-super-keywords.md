# `this` və `super` Açar Sözləri

> **Seviyye:** Beginner ⭐

## Giriş

`this` və `super` OOP-un ən çox istifadə olunan açar sözləridir. `this` "cari obyekt"ə işarə edir — yəni metod içində hansı obyektin üzərində işlədiyimizi. `super` isə parent (ata) sinifə müraciət üçün istifadə olunur — override olunmuş metod-u çağırmaq, parent constructor-u işə salmaq və s.

Java və PHP bu konseptdə oxşardır, amma syntax fərqlidir:

- Java-də `this`, `super`, `this()`, `super()` istifadə olunur.
- PHP-də `$this` (dollar işarəsi ilə!), `parent::`, `self::`, `static::` istifadə olunur.

Bu faylda biz hər istifadə halını nümunələrlə izah edirik və PHP developer-lərin çox etdiyi səhvləri göstəririk.

---

## Java-da istifadəsi

### `this` — cari obyektə referans

`this` bir obyektin öz özünə işarəsi deməkdir. Hər instance metodu içində Java avtomatik olaraq `this`-i təmin edir.

```java
public class Person {
    String name;
    int age;
    
    public void showInfo() {
        System.out.println(this.name);  // cari obyektin name-i
        System.out.println(this.age);   // cari obyektin age-i
        
        // this. olmadan da işləyir, amma aydınlıq üçün yazılır:
        System.out.println(name);
        System.out.println(age);
    }
}

Person p1 = new Person();
p1.name = "Orxan";
p1.showInfo();  // "Orxan" -- this = p1
```

### `this.fieldName` vs parameter eyni adda olduqda

Əgər constructor və ya metod parametri field ilə eyni ada malikdirsə, Java "shadowing" edir — parametr field-ı gizlədir. Bu halda `this.` məcburidir:

```java
public class Person {
    String name;
    int age;
    
    public Person(String name, int age) {
        // name = name;   // BU İŞLƏMİR! parametri özünə təyin edir, field dəyişmir
        
        this.name = name;  // sol this.name = field, sağ name = parametr
        this.age = age;    // Düzgün: field parametrdən dəyər alır
    }
}
```

**Tövsiyə:** IDE auto-generate edəndə həmişə `this.` istifadə edir. Həmişə parametr adını field adı ilə eyni saxlamaq daha aydındır.

### `this()` — constructor chaining

Bir constructor başqa constructor-u çağırmaq üçün `this(...)` istifadə edir. Bu, təkrar kodu azaldır.

```java
public class User {
    String name;
    int age;
    String email;
    
    // Tam constructor
    public User(String name, int age, String email) {
        this.name = name;
        this.age = age;
        this.email = email;
    }
    
    // Qısa constructor -- başqa constructor-u çağırır
    public User(String name) {
        this(name, 0, "unknown@example.com");  // tam constructor-a delegation
    }
    
    public User(String name, int age) {
        this(name, age, "unknown@example.com");
    }
    
    public User() {
        this("Anonymous");  // yuxarıdakı User(String)-ə çağırış
    }
}
```

**Mühüm qaydalar:**
1. `this(...)` **ilk statement** olmalıdır — üstündə heç nə yaza bilməzsiniz.
2. Bir constructor-da yalnız bir `this(...)` ola bilər.
3. Sonsuz loop yaratmaq olmur: `this()` öz özünü çağıra bilməz.

```java
public User(String name) {
    System.out.println("Başlanğıc");    // XƏTA! 
    this(name, 0, "email");             // Bu ilk statement olmalıdır
}
```

### `super()` — parent constructor çağırmaq

Hər sinif (Object-dən başqa) bir parent sinifi extend edir. Child constructor həmişə parent constructor-u çağırmalıdır.

```java
public class Animal {
    String name;
    int age;
    
    public Animal(String name, int age) {
        this.name = name;
        this.age = age;
        System.out.println("Animal yaradıldı: " + name);
    }
}

public class Dog extends Animal {
    String breed;
    
    public Dog(String name, int age, String breed) {
        super(name, age);   // parent constructor-u çağır -- MƏCBURİDİR
        this.breed = breed;
        System.out.println("Dog yaradıldı: " + breed);
    }
}

Dog d = new Dog("Rex", 3, "Labrador");
// Çıxış:
// Animal yaradıldı: Rex
// Dog yaradıldı: Labrador
```

### Implicit vs explicit `super()`

Əgər `super(...)` yazmasanız, Java avtomatik olaraq `super()` (arqumentsiz) çağırır:

```java
public class Animal {
    public Animal() {   // no-arg constructor
        System.out.println("Animal()");
    }
}

public class Dog extends Animal {
    public Dog() {
        // super();   -- Java avtomatik əlavə edir
        System.out.println("Dog()");
    }
}

new Dog();
// Çıxış:
// Animal()  -- implicit super() işlədi
// Dog()
```

**Problem:** Əgər parent-də no-arg constructor yoxdursa, explicit `super(...)` yazmaq məcburidir:

```java
public class Animal {
    public Animal(String name) {  // YALNIZ parameterli constructor
        this.name = name;
    }
}

public class Dog extends Animal {
    public Dog() {
        // Java avtomatik super() əlavə etməyə çalışır, amma Animal-da no-arg yoxdur
        // KOMPILYASIYA XƏTASI!
    }
}

// DÜZGÜN:
public class Dog extends Animal {
    public Dog() {
        super("Unnamed Dog");  // explicit super() ilə parameter ötür
    }
}
```

### `super.method()` — parent-in metoduna çağırış

Override zamanı parent-in orijinal metodunu çağırmaq üçün `super.methodName()` istifadə olunur.

```java
public class Animal {
    public void makeSound() {
        System.out.println("Generic animal sound");
    }
}

public class Dog extends Animal {
    @Override
    public void makeSound() {
        super.makeSound();   // parent metodu çağır
        System.out.println("Woof!");
    }
}

Dog d = new Dog();
d.makeSound();
// Çıxış:
// Generic animal sound
// Woof!
```

**Praktik nümunə:** Spring və ya Laravel kimi framework-lərdə bu çox istifadə olunur:

```java
@Override
public User save(User user) {
    user.setUpdatedAt(LocalDateTime.now());
    return super.save(user);   // parent repository-nin save-ini çağır
}
```

### `super.field` — parent field-ə çatmaq (shadowing)

Əgər child sinifdə parent ilə eyni adlı field varsa (shadowing), parent field-ə `super.` ilə çatmaq olar. Amma bu nadir və ümumiyyətlə pis praktikadır.

```java
public class Parent {
    String name = "Parent";
}

public class Child extends Parent {
    String name = "Child";  // shadowing! parent-in name-i gizlənir
    
    public void print() {
        System.out.println(this.name);    // "Child"
        System.out.println(super.name);   // "Parent" -- parent-in field-i
        System.out.println(name);         // "Child" (this.-siz)
    }
}
```

**Vacib:** Field-lər override olunmur — shadowing olur. Metodlar override olunur. Bu, Java-nin vacib incəliyidir.

### Tam nümunə: `this` və `super` birlikdə

```java
public class Vehicle {
    protected String brand;
    protected int year;
    
    public Vehicle(String brand, int year) {
        this.brand = brand;
        this.year = year;
    }
    
    public void describe() {
        System.out.println(year + " " + brand);
    }
}

public class Car extends Vehicle {
    private int doors;
    
    public Car(String brand, int year, int doors) {
        super(brand, year);  // parent constructor
        this.doors = doors;
    }
    
    // Constructor chaining ilə default doors = 4
    public Car(String brand, int year) {
        this(brand, year, 4);  // digər constructor-a delegation
    }
    
    @Override
    public void describe() {
        super.describe();        // "2023 Toyota"
        System.out.println("Doors: " + this.doors);
    }
}

Car c = new Car("Toyota", 2023);  // doors = 4 (default)
c.describe();
// Çıxış:
// 2023 Toyota
// Doors: 4
```

---

## PHP-də istifadəsi

### `$this` — dollar işarəsi ilə

PHP-də `this` açar söz deyil, xüsusi dəyişəndir — buna görə `$this` yazılır.

```php
class Person {
    public string $name;
    public int $age;
    
    public function showInfo(): void {
        echo $this->name . "\n";   // -> operator ilə
        echo $this->age . "\n";
    }
}

$p = new Person();
$p->name = "Orxan";
$p->showInfo();  // "Orxan"
```

**Syntax fərqi:**
- Java: `this.name`
- PHP: `$this->name`  (dollar + arrow)

### Constructor və parameter eyni adda

```php
class Person {
    public string $name;
    public int $age;
    
    public function __construct(string $name, int $age) {
        $this->name = $name;   // Java-nin this.name = name; ekvivalenti
        $this->age = $age;
    }
}

// PHP 8+ Constructor Property Promotion -- Java-də YOXDUR
class PersonV2 {
    public function __construct(
        public string $name,
        public int $age
    ) {
        // avtomatik: $this->name = $name; $this->age = $age;
    }
}
```

### `parent::` — parent metoduna çağırış

PHP-də `super` yoxdur — əvəzində `parent::` istifadə olunur.

```php
class Animal {
    public function __construct(public string $name) {}
    
    public function makeSound(): void {
        echo "Generic sound\n";
    }
}

class Dog extends Animal {
    public function __construct(string $name, public string $breed) {
        parent::__construct($name);  // Java-nin super(name) ekvivalenti
    }
    
    public function makeSound(): void {
        parent::makeSound();   // Java-nin super.makeSound() ekvivalenti
        echo "Woof!\n";
    }
}

$d = new Dog("Rex", "Labrador");
$d->makeSound();
// Çıxış:
// Generic sound
// Woof!
```

### `self::` — static context-də

```php
class Counter {
    public static int $count = 0;
    
    public static function increment(): void {
        self::$count++;   // cari sinifin static field-i
    }
    
    public function getName(): string {
        return self::class;  // "Counter" -- sinif adı
    }
}

Counter::increment();
echo Counter::$count;  // 1
```

### `static::` — late static binding

`self::` və `static::` arasında incəlik var:

```php
class ParentClass {
    public static function create(): static {
        return new self();   // həmişə ParentClass yaradır
    }
    
    public static function createLate(): static {
        return new static();  // çağırılan sinifdən asılı olaraq yaradır
    }
}

class ChildClass extends ParentClass {}

var_dump(ParentClass::create());      // ParentClass
var_dump(ChildClass::create());       // HƏLƏ DƏ ParentClass (self)
var_dump(ChildClass::createLate());   // ChildClass (static -- late binding)
```

Java-də `static` metodlar override oluna bilmir, buna görə bu konsept tam olaraq yoxdur.

### Constructor chaining PHP-də?

PHP-də Java-nin `this(...)` ekvivalenti YOXDUR — bir constructor başqasını birbaşa çağıra bilməz. Workaround: default parametrlər istifadə edin.

```php
// PHP-də constructor chaining YOXDUR
class User {
    public function __construct(
        public string $name,
        public int $age = 0,
        public string $email = "unknown@example.com"
    ) {
        // default parametrlər çoxlu constructor əvəzinə
    }
}

new User("Orxan");                        // age=0, email=default
new User("Orxan", 25);                    // email=default
new User("Orxan", 25, "orxan@mail.com");  // hamı verilmiş
```

Java-də isə:

```java
// Java-də constructor overloading ilə
public class User {
    public User(String name) { this(name, 0, "unknown@example.com"); }
    public User(String name, int age) { this(name, age, "unknown@example.com"); }
    public User(String name, int age, String email) { /* ... */ }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Cari obyekt | `this` | `$this` (dollar) |
| Field-ə çatmaq | `this.field` | `$this->field` (arrow) |
| Parent metodu | `super.method()` | `parent::method()` |
| Parent constructor | `super(args)` | `parent::__construct(args)` |
| Parent field | `super.field` | `parent::$field` (static) |
| Static cari sinif | `ClassName.method()` | `self::method()` |
| Static late binding | YOXDUR (static override yoxdur) | `static::method()` |
| Constructor chaining | `this(args)` | YOXDUR (default parametr ilə həll) |
| Implicit super() | Var (default no-arg) | Yoxdur (əl ilə çağırılır) |
| Constructor promotion | YOXDUR (record-lərdə var) | `public` keyword parametr üçün |
| `super` birinci statement olmalı | Bəli | Xeyr (istənilən yerdə) |

---

## Niyə belə fərqlər var?

### Java-nın yanaşması

Java **strict OOP** dilidir — hər şey sinifin içindədir və OOP qaydaları çox ciddidir.

**Niyə `super()` implicit əlavə olunur?** Java-nin fəlsəfəsi: hər obyekt yaradıldıqda, parent-dən başlayan bütün inheritance zənciri initialize olmalıdır. Əgər parent constructor çağırılmasa, parent-in field-ləri düzgün set olunmaz. Java kompilyatoru bu qaydanı məcburi edir.

**Niyə `this()` və `super()` birinci statement olmalıdır?** Çünki Java-də obyekt yaradılma qaydası belədir: əvvəl parent hissəsi initialize olunur, sonra child. Bu qaydanı sındırmamaq üçün chaining yalnız başlanğıcda ola bilər.

**Niyə constructor overloading var?** Java statik tiplidir — default parametrlər olmadığı üçün fərqli parameter siyahıları ilə fərqli constructor-lar yazırıq. Bu həm də type-safety verir.

### PHP-nin yanaşması

PHP OOP-u sonradan əlavə edib (PHP 3/4-də). Buna görə də syntax klassik C++ / Java-dan fərqlidir:

**Niyə `$this`?** PHP-də bütün dəyişənlər `$` ilə başlayır, `$this` də dəyişən kimi davranır — bu dilin ümumi qaydasına uyğundur.

**Niyə `parent::`?** PHP **scope resolution operator** (`::`) static və parent kontekstini göstərmək üçün istifadə edir. Bu keyword deyil, operator-dur. Daha çevikdir — istənilən yerdə yazıla bilər.

**Niyə constructor overloading yoxdur?** PHP dinamik tiplidir — function signature yalnız function adından asılıdır. Buna görə də overloading texniki olaraq mümkün deyil. Default parametrlər və variadic (`...$args`) bu boşluğu doldurur.

**Niyə `static::` (late binding) var?** PHP-də static metodlar "override" ola bilir (technically — child sinifdə eyni adlı yeni metod elan edilir). `self::` həmişə elan olunduğu sinifə işarə edir, `static::` isə çağırılan sinifə. Java-də bu problem yoxdur, çünki static metodlar override olunmur.

---

## Ümumi səhvlər (Beginner traps)

### 1. `this()` və `super()` birlikdə

```java
public class Child extends Parent {
    public Child() {
        super();
        this(42);  // XƏTA! Hər ikisi birinci statement olmalıdır
    }
}
```

Bir constructor-da yalnız `this(...)` **və ya** `super(...)` ola bilər, ikisi birdən YOX.

### 2. `super()` statement-dən əvvəl digər kod

```java
public class Child extends Parent {
    public Child() {
        int x = 5;
        super();   // XƏTA! super() ilk statement olmalıdır
    }
}
```

### 3. PHP developer-in `this.` yazması Java-də

```java
// PHP-də $this->name idi
System.out.println(this->name);  // XƏTA! Java-də -> yoxdur
System.out.println(this.name);   // Düzgün
```

### 4. `this.` unutmaq parameter shadowing olduqda

```java
public class User {
    private String name;
    
    public User(String name) {
        name = name;   // Heç bir iş görmür! parametri özünə təyin edir
        // field hələ də null-dır
    }
}

// Düzgün:
public User(String name) {
    this.name = name;
}
```

### 5. `super()` olmadan parent no-arg constructor yoxdur

```java
public class Animal {
    public Animal(String name) { ... }  // YALNIZ parameterli
}

public class Dog extends Animal {
    public Dog() {
        // Java implicit super() əlavə edir, amma Animal() yoxdur
        // KOMPILYASIYA XƏTASI!
    }
}
```

### 6. Static metoddan `this` istifadəsi

```java
public class Calculator {
    public static int add(int a, int b) {
        return this.a + this.b;  // XƏTA! static metodda this yoxdur
    }
}
```

### 7. PHP-də `$` unutmaq

```php
class Person {
    public string $name;
    
    public function greet(): void {
        echo $this->name;   // Düzgün
        echo this->name;    // XƏTA! $ lazımdır
    }
}
```

### 8. PHP-də `parent::` ilə `->` istifadə etmək

```php
class Dog extends Animal {
    public function bark(): void {
        parent->makeSound();   // XƏTA! parent statik kontekstdir
        parent::makeSound();   // Düzgün -- :: istifadə olunur
    }
}
```

---

## Mini müsahibə sualları

**S1: Java-də `this()` və `super()` arasında fərq nədir? Nə vaxt hansını istifadə edək?**

C: `this(...)` **eyni sinifdəki başqa constructor-u** çağırır — kod təkrarını azaltmaq üçün istifadə olunur (constructor chaining). `super(...)` isə **parent sinifin constructor-unu** çağırır — parent-in field-lərini initialize etmək üçün. Hər ikisi constructor-un ilk statement-i olmalıdır, buna görə bir constructor-da yalnız birini istifadə etmək mümkündür. Əgər heç biri yazılmasa, Java avtomatik olaraq `super()` (arqumentsiz) əlavə edir.

**S2: Əgər parent sinifdə yalnız parametrli constructor varsa və child sinifdə explicit `super(...)` yazmasanız, nə baş verir?**

C: Kompilyasiya xətası alınır: `There is no default constructor available in 'ParentClass'`. Səbəb: Java avtomatik olaraq `super()` (arqumentsiz) əlavə etməyə çalışır, amma parent-də no-arg constructor yoxdur. Həll: 
1. Parent-də no-arg constructor əlavə edin, və ya
2. Child constructor-da explicit `super(args)` yazın.

**S3: PHP-də `self::` və `static::` arasında fərq nədir?**

C: `self::` **elan olunduğu** sinifə işarə edir (kompilyasiya zamanı bağlanır). `static::` isə **çağırılan** sinifə işarə edir (runtime-da bağlanır — "late static binding"). Məsələn:

```php
class A {
    public static function create(): void {
        var_dump(new self());    // həmişə A yaradır
        var_dump(new static());  // kontekstə görə A və ya B yaradır
    }
}
class B extends A {}
B::create();  // self: A obyekti, static: B obyekti
```

Bu, factory pattern və fluent interface-lərdə çox istifadə olunur. Java-də bu konsept yoxdur, çünki Java-də static metodlar override olunmur — sadəcə shadow olunur.

**S4: `super.method()` çağırışında metodun hansı versiyası icra olunur — parent-in, yoxsa child-in?**

C: `super.method()` həmişə **parent-in** metodunu çağırır — dynamic dispatch burada işləmir. Bu qaydanın vacib istifadə halı: child method parent davranışını genişləndirəndə. Məsələn:

```java
@Override
public void save() {
    validate();         // bu child və ya parent ola bilər
    super.save();       // bu HƏMİŞƏ parent-in save-i
    logSave();
}
```

Qeyd: child sinifin metodunun içindən yenə `this.method()` dinamik dispatch edir — yəni override olunmuş versiya çağırılır. Yalnız `super.` ilə parent versiyası birbaşa çağırılır.
