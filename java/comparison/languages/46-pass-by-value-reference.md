# Pass by Value vs Reference — Java-nın Fərqi

> **Seviyye:** Beginner ⭐

## Giriş

Java **həmişə pass-by-value** istifadə edir. Amma object pass etdikdə bu dəyər **reference-ın kopyasıdır**. Bu incəlik PHP developerları qarışdırır, çünki PHP-nin davranışı fərqlidir.

---

## PHP-də Necədir

PHP default olaraq **pass-by-value** edir, amma object-lər üçün xüsusi davranış var:

```php
// PHP — primitives: dəyər kopyalanır
function increment(int $n): void {
    $n++;
    // Orijinal dəyişmir
}

$x = 5;
increment($x);
echo $x; // 5 — dəyişmədi

// PHP — objects: reference kimi davranır (amma tam deyil)
function changeName(object $obj): void {
    $obj->name = "Changed"; // ← əsl objecti dəyişir
}

function replaceObj(object $obj): void {
    $obj = new stdClass(); // ← yalnız lokal dəyişkəni dəyişir
    $obj->name = "New";
}

$person = new stdClass();
$person->name = "Alice";

changeName($person);
echo $person->name; // "Changed" — dəyişdi!

replaceObj($person);
echo $person->name; // "Changed" — dəyişmədi (tam reference deyil)

// Tam reference: & operatoru
function replaceObjRef(object &$obj): void {
    $obj = new stdClass();
    $obj->name = "New";
}

replaceObjRef($person);
echo $person->name; // "New" — indi dəyişdi
```

---

## Java-da Necədir

Java-da **bütün parametrlər value-by-copy** ilə ötürülür:

- **Primitive** (`int`, `boolean`, `double`): dəyərin özü kopyalanır
- **Object**: reference-ın kopyası kopyalanır (object özü deyil!)

```java
// Primitive — dəyişmir
static void increment(int n) {
    n++;
    // Bu lokal n-dir, orijinal dəyişmir
}

int x = 5;
increment(x);
System.out.println(x); // 5 — dəyişmədi

// Object — içini dəyişmək işləyir
static void changeName(Person person) {
    person.setName("Changed"); // ✓ əsl object dəyişir (eyni reference)
}

// Object — yenidən assign etmək işləmir
static void replaceObj(Person person) {
    person = new Person("New"); // ✗ yalnız lokal kopyası dəyişir
}

Person alice = new Person("Alice");

changeName(alice);
System.out.println(alice.getName()); // "Changed" — içi dəyişdi

replaceObj(alice);
System.out.println(alice.getName()); // "Changed" — dəyişmədi
```

---

## Vizual Anlamaq

```
// changeName(alice) çağırışında nə baş verir:

Stack (metod çağırışı):
  person ──────────┐
                   ↓
alice ─────────→ [Person: name="Alice"]  (Heap-də)

// person.setName("Changed") çağırışı:
// Hər iki reference eyni object-ə baxır → object dəyişir ✓

// replaceObj(alice) çağırışında:
Stack:
  person ─────────→ [Person: name="New"]  (yeni object)
                     
alice ──────────→ [Person: name="Changed"]  (əvvəlki, dəyişmədi)

// person = new Person("New") yalnız lokal kopyası dəyişdirir
```

---

## String-lər Xüsusidir

`String` Java-da immutable-dir. Ona görə string "dəyişdirmək" yeni object yaradır:

```java
static void appendHello(String s) {
    s = s + " Hello"; // Yeni String yaradır, lokal kopyaya assign edir
}

String text = "World";
appendHello(text);
System.out.println(text); // "World" — dəyişmədi

// StringBuilder istifadə et:
static void appendHello(StringBuilder sb) {
    sb.append(" Hello"); // Eyni StringBuilder-ı dəyişir
}

StringBuilder sb = new StringBuilder("World");
appendHello(sb);
System.out.println(sb); // "World Hello" — dəyişdi
```

---

## Array-lər

Array-lər object-dir — içi dəyişmək işləyir, özünü replace etmək işləmir:

```java
static void fillArray(int[] arr) {
    arr[0] = 99; // ✓ əsl array-ı dəyişir
}

static void replaceArray(int[] arr) {
    arr = new int[]{1, 2, 3}; // ✗ yalnız lokal kopyası dəyişir
}

int[] nums = {0, 0, 0};
fillArray(nums);
System.out.println(nums[0]); // 99 — dəyişdi

replaceArray(nums);
System.out.println(nums[0]); // 99 — dəyişmədi
```

---

## Defensive Copy — Immutability üçün

Object-in içini xaricdən dəyişməkdən qorunmaq üçün **defensive copy** istifadə olunur:

```java
public class Config {
    private final List<String> servers;

    public Config(List<String> servers) {
        // ✓ Defensive copy — xarici list dəyişdirilərsə Config təsirlənmir
        this.servers = new ArrayList<>(servers);
    }

    public List<String> getServers() {
        // ✓ Unmodifiable kopy qaytar — caller dəyişdirə bilməsin
        return Collections.unmodifiableList(servers);
    }
}

List<String> list = new ArrayList<>(List.of("s1", "s2"));
Config config = new Config(list);
list.add("s3"); // Config-ə təsiri yoxdur
System.out.println(config.getServers()); // [s1, s2]
```

---

## PHP vs Java Müqayisəsi

| | PHP | Java |
|---|---|---|
| Primitive pass | value copy | value copy |
| Object pass | reference copy (& olmadan) | reference copy |
| Object içini dəyişmək | işləyir | işləyir |
| Object-i replace etmək | işləmir (& lazımdır) | işləmir |
| String mutability | mutable (`$s .= "x"` edir) | immutable (yeni object) |
| Array | value copy default | reference (object kimi) |

---

## Praktik Tapşırıq

Aşağıdakı kod nə print edəcək? İzah et:

```java
public class Main {
    static void swap(int a, int b) {
        int temp = a;
        a = b;
        b = temp;
    }

    static void swapContent(int[] arr, int i, int j) {
        int temp = arr[i];
        arr[i] = arr[j];
        arr[j] = temp;
    }

    public static void main(String[] args) {
        int x = 1, y = 2;
        swap(x, y);
        System.out.println(x + " " + y); // ?

        int[] arr = {1, 2};
        swapContent(arr, 0, 1);
        System.out.println(arr[0] + " " + arr[1]); // ?
    }
}
```

---

## Əlaqəli Mövzular
- [02 — Data Types & Variables](02-data-types-and-variables.md)
- [04 — Primitives, Wrappers, Autoboxing](04-primitives-wrappers-autoboxing-deep.md)
- [11 — OOP Classes & Interfaces](11-oop-classes-interfaces.md)
- [27 — Records & Data Classes](27-records-and-data-classes.md)
