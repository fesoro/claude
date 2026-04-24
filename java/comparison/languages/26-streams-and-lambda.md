# Streams ve Lambda — Java vs PHP

> **Seviyye:** Intermediate ⭐⭐

## Giris

Funksional proqramlashdirma (FP) paradiqmasi son illerda her iki dilde genis yayilib. Java 8 (2014) ile **Streams API** ve **lambda ifadeleri** elave olunub. PHP ise en bashdan `array_map`, `array_filter` kimi funksiyalara sahib olub, PHP 7.4 ile **arrow functions**, PHP 8 ile daha guclu closures teqdim edib. Laravel framework-u ise oz **Collection** sinifi ile Java Streams-e benzer interfeys yaradib.

---

## Java-da istifadesi

### Lambda ifadeleri

Lambda — anonim funksiya yaratmanin qisa yoludur. Yalniz **functional interface** (tek abstract metodlu interfeys) ile istifade oluna biler:

```java
import java.util.function.*;

public class LambdaExamples {

    public static void main(String[] args) {

        // Evvelki yol — anonim sinif
        Runnable oldWay = new Runnable() {
            @Override
            public void run() {
                System.out.println("Kohne yol");
            }
        };

        // Lambda ile
        Runnable newWay = () -> System.out.println("Yeni yol");

        // Parametrli lambda
        Function<String, Integer> stringLength = s -> s.length();
        // ve ya method reference ile
        Function<String, Integer> stringLength2 = String::length;

        System.out.println(stringLength.apply("Salam")); // 5

        // Iki parametrli
        BiFunction<Integer, Integer, Integer> add = (a, b) -> a + b;
        System.out.println(add.apply(3, 5)); // 8

        // Predicate — boolean qaytaran funksiya
        Predicate<Integer> isPositive = n -> n > 0;
        System.out.println(isPositive.test(5));   // true
        System.out.println(isPositive.test(-3));  // false

        // Consumer — neticesi olmayan funksiya (void)
        Consumer<String> printer = msg -> System.out.println(">> " + msg);
        printer.accept("Salam dunya");

        // Supplier — parametrsiz deyer qaytaran funksiya
        Supplier<Double> random = () -> Math.random();
        System.out.println(random.get());

        // Predicate birleshme
        Predicate<Integer> isEven = n -> n % 2 == 0;
        Predicate<Integer> isPositiveEven = isPositive.and(isEven);
        System.out.println(isPositiveEven.test(4));   // true
        System.out.println(isPositiveEven.test(-4));  // false
        System.out.println(isPositiveEven.test(3));   // false
    }
}
```

### Functional Interfaces

```java
// Java-da movcud olan esas functional interface-ler:

// Function<T, R>     — T alir, R qaytarir
// BiFunction<T, U, R> — T ve U alir, R qaytarir
// Predicate<T>       — T alir, boolean qaytarir
// Consumer<T>        — T alir, hechne qaytarmir
// Supplier<T>        — hechne almir, T qaytarir
// UnaryOperator<T>   — T alir, T qaytarir (Function<T,T>)
// BinaryOperator<T>  — T,T alir, T qaytarir (BiFunction<T,T,T>)

// Xususi functional interface
@FunctionalInterface
interface Validator<T> {
    boolean isValid(T value);

    // Default metod ola biler (tek abstract metod qaydasi pozulmur)
    default Validator<T> and(Validator<T> other) {
        return value -> this.isValid(value) && other.isValid(value);
    }

    default Validator<T> negate() {
        return value -> !this.isValid(value);
    }
}

// Istifadesi
Validator<String> notEmpty = s -> s != null && !s.isEmpty();
Validator<String> maxLength = s -> s.length() <= 100;
Validator<String> combined = notEmpty.and(maxLength);

System.out.println(combined.isValid("Salam")); // true
System.out.println(combined.isValid(""));       // false
```

### Streams API

Streams API kolleksiyalar uzerinde funksional emeliyyatlar zenciri yaratmaga imkan verir:

```java
import java.util.*;
import java.util.stream.*;

public class StreamExamples {

    record Product(String name, String category, double price, int stock) {}

    public static void main(String[] args) {

        List<Product> products = List.of(
            new Product("Telefon", "Elektronika", 1500, 50),
            new Product("Laptop", "Elektronika", 3000, 20),
            new Product("Kitab", "Tehsil", 25, 200),
            new Product("Defter", "Tehsil", 5, 500),
            new Product("Qulaqliq", "Elektronika", 200, 100),
            new Product("Qelem", "Tehsil", 2, 1000),
            new Product("Planset", "Elektronika", 800, 30)
        );

        // filter — sherte uygun elementleri sec
        List<Product> expensive = products.stream()
            .filter(p -> p.price() > 500)
            .toList();
        // [Telefon, Laptop, Planset]

        // map — her elementi cevir
        List<String> names = products.stream()
            .map(Product::name)
            .toList();
        // [Telefon, Laptop, Kitab, Defter, Qulaqliq, Qelem, Planset]

        // filter + map + sorted
        List<String> expensiveNames = products.stream()
            .filter(p -> p.price() > 100)
            .sorted(Comparator.comparingDouble(Product::price).reversed())
            .map(Product::name)
            .toList();
        // [Laptop, Telefon, Planset, Qulaqliq]

        // reduce — butun elementleri tek deyere endir
        double totalValue = products.stream()
            .mapToDouble(p -> p.price() * p.stock())
            .reduce(0, Double::sum);
        System.out.println("Umumi deyer: " + totalValue);

        // Daha qisa yol
        double totalValue2 = products.stream()
            .mapToDouble(p -> p.price() * p.stock())
            .sum();

        // collect — neticeleri topla
        // Kateqoriyaya gore qruplasdirma
        Map<String, List<Product>> byCategory = products.stream()
            .collect(Collectors.groupingBy(Product::category));
        // {Elektronika=[Telefon, Laptop, ...], Tehsil=[Kitab, Defter, ...]}

        // Kateqoriya adi → ortalama qiymet
        Map<String, Double> avgPriceByCategory = products.stream()
            .collect(Collectors.groupingBy(
                Product::category,
                Collectors.averagingDouble(Product::price)
            ));

        // Kateqoriya adi → mehsul adlarinin siyahisi
        Map<String, List<String>> namesByCategory = products.stream()
            .collect(Collectors.groupingBy(
                Product::category,
                Collectors.mapping(Product::name, Collectors.toList())
            ));

        // String birlesdirme
        String allNames = products.stream()
            .map(Product::name)
            .collect(Collectors.joining(", "));
        // "Telefon, Laptop, Kitab, Defter, Qulaqliq, Qelem, Planset"

        // Statistika
        DoubleSummaryStatistics stats = products.stream()
            .mapToDouble(Product::price)
            .summaryStatistics();
        System.out.println("Min: " + stats.getMin());
        System.out.println("Max: " + stats.getMax());
        System.out.println("Ortalama: " + stats.getAverage());
        System.out.println("Say: " + stats.getCount());
        System.out.println("Cem: " + stats.getSum());

        // anyMatch, allMatch, noneMatch
        boolean hasExpensive = products.stream()
            .anyMatch(p -> p.price() > 2000);  // true

        boolean allInStock = products.stream()
            .allMatch(p -> p.stock() > 0);      // true

        boolean noneFree = products.stream()
            .noneMatch(p -> p.price() == 0);    // true

        // findFirst, findAny
        Optional<Product> cheapest = products.stream()
            .min(Comparator.comparingDouble(Product::price));
        cheapest.ifPresent(p -> System.out.println("En ucuz: " + p.name()));

        // distinct, limit, skip
        List<String> categories = products.stream()
            .map(Product::category)
            .distinct()
            .toList();
        // [Elektronika, Tehsil]

        // flatMap — ic-ice kolleksiyalari "duzleshdir"
        List<List<Integer>> nested = List.of(
            List.of(1, 2, 3),
            List.of(4, 5),
            List.of(6, 7, 8, 9)
        );
        List<Integer> flat = nested.stream()
            .flatMap(Collection::stream)
            .toList();
        // [1, 2, 3, 4, 5, 6, 7, 8, 9]
    }
}
```

### Parallel Streams

```java
// Boyuk melumat setlerinde paralel ishlemek ucun
long count = products.parallelStream()
    .filter(p -> p.price() > 100)
    .count();

// ve ya
long count2 = products.stream()
    .parallel()
    .filter(p -> p.price() > 100)
    .count();

// DIQQET: Paralel stream her zaman surətli deyil!
// Kichik kolleksiyalarda overhead artir.
// Side-effect olan emeliyyatlarda istifade etmeyin.
```

### Method References

```java
// Lambda evezine method reference — daha qisa ve oxunaqli

// Static method reference
Function<String, Integer> parse = Integer::parseInt;

// Instance method reference (arbitrary object)
Function<String, String> upper = String::toUpperCase;

// Instance method reference (specific object)
String prefix = "Salam, ";
Function<String, String> greet = prefix::concat;

// Constructor reference
Supplier<ArrayList<String>> listFactory = ArrayList::new;
Function<String, StringBuilder> sbFactory = StringBuilder::new;
```

---

## PHP-de istifadesi

### Closures (anonim funksiyalar)

```php
<?php

// Anonim funksiya
$greet = function (string $name): string {
    return "Salam, $name!";
};
echo $greet('Orxan'); // "Salam, Orxan!"

// Xarici deyisheni tutma — use()
$prefix = 'Salam';
$greetWithPrefix = function (string $name) use ($prefix): string {
    return "$prefix, $name!";
};
echo $greetWithPrefix('Ali'); // "Salam, Ali!"

// Deyisheni reference ile tutma
$counter = 0;
$increment = function () use (&$counter): void {
    $counter++;
};
$increment();
$increment();
echo $counter; // 2

// Closure::bind ve Closure::call
class Wallet {
    private float $balance = 100.0;
}

$getBalance = Closure::bind(function () {
    return $this->balance;
}, new Wallet(), Wallet::class);

echo $getBalance(); // 100.0
```

### Arrow Functions (PHP 7.4+)

```php
<?php

// Arrow function — tek ifade, avtomatik olaraq xarici deyishenleri tutur
$multiply = fn(int $a, int $b): int => $a * $b;
echo $multiply(3, 5); // 15

// use() yazmaga ehtiyac yoxdur — avtomatik tutur
$factor = 3;
$triple = fn(int $n): int => $n * $factor;
echo $triple(10); // 30

// Lakin arrow function tek ifadeli olmalidir — blok yaza bilmezsiniz
// Birdən çox əməliyyat lazımdırsa, adi closure istifadə edin
```

### Array funksiyalari — PHP-nin "stream"-leri

```php
<?php

$products = [
    ['name' => 'Telefon', 'category' => 'Elektronika', 'price' => 1500, 'stock' => 50],
    ['name' => 'Laptop', 'category' => 'Elektronika', 'price' => 3000, 'stock' => 20],
    ['name' => 'Kitab', 'category' => 'Tehsil', 'price' => 25, 'stock' => 200],
    ['name' => 'Defter', 'category' => 'Tehsil', 'price' => 5, 'stock' => 500],
    ['name' => 'Qulaqliq', 'category' => 'Elektronika', 'price' => 200, 'stock' => 100],
    ['name' => 'Qelem', 'category' => 'Tehsil', 'price' => 2, 'stock' => 1000],
    ['name' => 'Planset', 'category' => 'Elektronika', 'price' => 800, 'stock' => 30],
];

// array_filter — shertle filter
$expensive = array_filter($products, fn($p) => $p['price'] > 500);
// [Telefon, Laptop, Planset]

// array_map — her elementi cevir
$names = array_map(fn($p) => $p['name'], $products);
// ['Telefon', 'Laptop', 'Kitab', 'Defter', 'Qulaqliq', 'Qelem', 'Planset']

// array_reduce — tek deyere endir
$totalValue = array_reduce($products, function ($carry, $product) {
    return $carry + ($product['price'] * $product['stock']);
}, 0);
echo "Umumi deyer: $totalValue";

// Zencirleme — PHP-de asanlikla mumkun DEYIL
// Her addimda yeni deyishen yaratmaq lazimdir:
$filtered = array_filter($products, fn($p) => $p['price'] > 100);
$sorted = $filtered;
usort($sorted, fn($a, $b) => $b['price'] <=> $a['price']);
$expensiveNames = array_map(fn($p) => $p['name'], $sorted);
// ['Laptop', 'Telefon', 'Planset', 'Qulaqliq']

// array_column — tek sutun cixar
$allNames = array_column($products, 'name');

// array_unique
$categories = array_unique(array_column($products, 'category'));
// ['Elektronika', 'Tehsil']

// array_key_exists, in_array
$hasLaptop = in_array('Laptop', array_column($products, 'name')); // true

// array_walk — her element uzerinde emeliyyat (array-i deyishir)
array_walk($products, function (&$product) {
    $product['total_value'] = $product['price'] * $product['stock'];
});

// array_combine — iki array-den key=>value yaratma
$nameToPrice = array_combine(
    array_column($products, 'name'),
    array_column($products, 'price')
);
// ['Telefon' => 1500, 'Laptop' => 3000, ...]

// Qruplashdirma — PHP-de manual etmek lazimdir
$byCategory = [];
foreach ($products as $product) {
    $byCategory[$product['category']][] = $product;
}

// usort — siralama
usort($products, fn($a, $b) => $a['price'] <=> $b['price']);  // artan
usort($products, fn($a, $b) => $b['price'] <=> $a['price']);  // azalan

// array_sum
$totalPrice = array_sum(array_column($products, 'price'));

// array_count_values
$categoryCounts = array_count_values(array_column($products, 'category'));
// ['Elektronika' => 4, 'Tehsil' => 3]

// array_slice — limit ve offset
$firstThree = array_slice($products, 0, 3);
$skipTwo = array_slice($products, 2);

// compact ferq: PHP array_any / array_all (PHP 8.1+)
// Yoxdur — manual yazmaq lazimdir:
function array_any(array $arr, callable $fn): bool {
    foreach ($arr as $item) {
        if ($fn($item)) return true;
    }
    return false;
}

$hasExpensive = array_any($products, fn($p) => $p['price'] > 2000); // true
```

### Laravel Collection — PHP-nin Streams cavabi

Laravel `Collection` sinifi Java Streams-e en yaxin PHP aletnatividir. Zencirleme (chaining), lazy evaluation ve zengin metodlar teqdim edir:

```php
<?php

use Illuminate\Support\Collection;

$products = collect([
    ['name' => 'Telefon', 'category' => 'Elektronika', 'price' => 1500, 'stock' => 50],
    ['name' => 'Laptop', 'category' => 'Elektronika', 'price' => 3000, 'stock' => 20],
    ['name' => 'Kitab', 'category' => 'Tehsil', 'price' => 25, 'stock' => 200],
    ['name' => 'Defter', 'category' => 'Tehsil', 'price' => 5, 'stock' => 500],
    ['name' => 'Qulaqliq', 'category' => 'Elektronika', 'price' => 200, 'stock' => 100],
    ['name' => 'Qelem', 'category' => 'Tehsil', 'price' => 2, 'stock' => 1000],
    ['name' => 'Planset', 'category' => 'Elektronika', 'price' => 800, 'stock' => 30],
]);

// filter + sort + map — Java Streams kimi zencirlenir!
$expensiveNames = $products
    ->filter(fn($p) => $p['price'] > 100)
    ->sortByDesc('price')
    ->map(fn($p) => $p['name'])
    ->values()
    ->all();
// ['Laptop', 'Telefon', 'Planset', 'Qulaqliq']

// groupBy — Java-nin Collectors.groupingBy()-na benzer
$byCategory = $products->groupBy('category');
// Collection {
//   'Elektronika' => Collection [...],
//   'Tehsil' => Collection [...]
// }

// Kateqoriya uezre ortalama qiymet
$avgByCategory = $products
    ->groupBy('category')
    ->map(fn($group) => $group->avg('price'));
// ['Elektronika' => 1375, 'Tehsil' => 10.67]

// reduce
$totalValue = $products->reduce(
    fn($carry, $p) => $carry + ($p['price'] * $p['stock']),
    0
);

// sum
$totalPrice = $products->sum('price');

// pluck — Java-nin map(Product::name)-na benzer
$names = $products->pluck('name');
// ['Telefon', 'Laptop', 'Kitab', ...]

// contains, every, first, last
$hasExpensive = $products->contains(fn($p) => $p['price'] > 2000); // true
$allInStock = $products->every(fn($p) => $p['stock'] > 0);        // true
$cheapest = $products->sortBy('price')->first();                    // Qelem

// flatMap
$nested = collect([[1, 2, 3], [4, 5], [6, 7, 8, 9]]);
$flat = $nested->flatMap(fn($arr) => $arr);
// [1, 2, 3, 4, 5, 6, 7, 8, 9]

// chunk — partiyalara bol
$chunks = $products->chunk(3);
// [[Telefon, Laptop, Kitab], [Defter, Qulaqliq, Qelem], [Planset]]

// zip, combine, merge
$keys = collect(['a', 'b', 'c']);
$values = collect([1, 2, 3]);
$combined = $keys->combine($values); // ['a'=>1, 'b'=>2, 'c'=>3]

// when — shertli zencirleme
$results = $products
    ->when($minPrice > 0, fn($c) => $c->filter(fn($p) => $p['price'] >= $minPrice))
    ->when($category, fn($c, $cat) => $c->filter(fn($p) => $p['category'] === $cat))
    ->sortBy('name');

// pipe — butun collection-u bashqa funksiyaya otur
$summary = $products->pipe(function ($collection) {
    return [
        'count' => $collection->count(),
        'total' => $collection->sum('price'),
        'avg' => $collection->avg('price'),
        'categories' => $collection->pluck('category')->unique()->values(),
    ];
});

// tap — debug ucun (collection-u deyishmeden yan effekt)
$result = $products
    ->filter(fn($p) => $p['price'] > 100)
    ->tap(fn($c) => dump("Filterlenmish say: " . $c->count()))
    ->sortByDesc('price')
    ->pluck('name');

// Lazy Collection — boyuk data setleri ucun (generator-based)
use Illuminate\Support\LazyCollection;

LazyCollection::make(function () {
    $handle = fopen('huge-file.csv', 'r');
    while ($line = fgetcsv($handle)) {
        yield $line;
    }
})
->filter(fn($row) => $row[2] > 1000)
->take(100)
->each(fn($row) => processRow($row));
```

### Higher-order messaging (Laravel)

```php
<?php

// Adi yol
$activeUsers = $users->filter(fn($u) => $u->isActive());
$names = $users->map(fn($u) => $u->name);

// Higher-order messaging ile (daha qisa)
$activeUsers = $users->filter->isActive();
$names = $users->map->name;

// Zincirleme
$result = $users
    ->filter->isActive()
    ->sortBy->name
    ->map->email
    ->values();
```

---

## Esas ferqler

| Xususiyyet | Java | PHP |
|---|---|---|
| **Lambda sintaksis** | `(params) -> expression` | `fn(params) => expression` (arrow fn) |
| **Closure** | Lambda avtomatik tutur (effectively final) | `use()` ile aciq tutma lazimdir |
| **Arrow function** | Yoxdur (lambda var) | PHP 7.4+ (tek ifade) |
| **Functional interface** | Mecburi — lambda ucun lazimdir | Lazim deyil — callable tip var |
| **Stream/Collection** | `Stream<T>` API | `array_*` funksiyalar + Laravel Collection |
| **Zencirleme** | Native — `stream().filter().map()...` | Array funksiyalarinda yoxdur, Collection-da var |
| **Lazy evaluation** | Stream default olaraq lazy | Array funksiyalari eager, LazyCollection lazy |
| **Parallel ishleme** | `parallelStream()` | Yoxdur |
| **Method reference** | `String::toUpperCase` | `'strtoupper'` (string kimi), `[$obj, 'method']` |
| **Type safety** | Generics ile tip-tehlukesiz | Runtime tip yoxlamasi |
| **groupBy** | `Collectors.groupingBy()` | Manual loop ve ya Collection `groupBy()` |
| **flatMap** | Var | `array_merge(...array_map())` ve ya Collection |

---

## Niye bele ferqler var?

### Java niye Streams ve lambda-ni gec elave etdi?

Java uzun muddət yalniz OOP paradiqmasina sadiq qaldi. Java 8-de (2014) lambda ve Streams elave olunmasininn sebebi:
1. Diger dillerin (Scala, C#, Python) funksional xususiyyetlerinin populyarlashmasi
2. Paralel ishleme ehtiyacinin artmasi (multi-core CPU)
3. Koleksiya emeliyyatlarinin daha oxunaqli yazilmasi ehtiyaci

Java-nin Streams API-si **lazy evaluation** prinsipi ile isleyir — terminal emeliyyat (`collect`, `forEach`, `count`) caghrilana qeder hech bir ishleme bash vermiri. Bu boyuk data setlerinde performans ustuunluyu verir.

### PHP niye ferqli yanasdirma secdi?

PHP en bashdan funksional proqramlashdirma elementlerine sahib idi (`array_map`, `array_filter` 2000-ci ilden movcuddur). Lakin bu funksiyalar **zencirlenmir** — her birinin neticesi yeni array-dir ve novbeti funksiyaya otur olunur.

Bu catishmazligi Laravel Collection sinifi hel edir. Collection, Java-nin Streams API-sine cox benzer interfeys yaradir ve PHP dunya-sinda de-facto standart olub.

PHP-de `callable` tipi sayesinde funksional interface kimi ayri bir mexanizma ehtiyac yoxdur — isteyen funksiya, closure, arrow function, string ad ve ya `[$object, 'method']` formati otura biler.

### Esas dizayn ferqi

- **Java**: Tip-tehlukesiz, kompilyator yoxlamali, lazy, paralel ishleme destekli. Funksional interface mecburi — bu, kompilyatorun lambda-ni duzgun yoxlamasini temin edir.
- **PHP**: Praktik, sade, tip yoxlamasi az. `callable` tip ile istenilen funksiya kecirile biler. Zencirleme ucun Laravel Collection lazimdir.
