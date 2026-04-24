# Sequenced Collections, Stream Gatherers və Modern Java Sintaksisi (Java 21-25 vs PHP)

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Son illər Java sintaksis və API baxımından köhnə imicindən uzaqlaşır. **Java 21** ilə gələn `SequencedCollection` siyahı və map-lərdə "birinci/sonuncu element" problemini həll etdi. **Java 24** (JEP 485) ilə `Stream.gather()` stabil oldu — bu, stream-lərə pəncərə, batching, scan kimi əməliyyatlar əlavə edir. **Java 22** `_` (unnamed variable) gətirdi. **Java 25** isə main metodunu sadələşdirdi.

**PHP** isə başqa yoldan gedir. PHP array-ləri başlanğıcdan insertion-order saxlayır (LinkedHashMap kimi), amma numeric və string key-lərin qarışığı çətinlik yaradır. Stream-lərə analoji Generator (`yield`), Laravel Collection və LazyCollection var. `_` PHP-də sadəcə adi dəyişən adıdır.

Bu fayl Java-nın yeni collection və stream API-lərini PHP ekosistemi ilə müqayisə edir.

---

## Java-da istifadəsi

### 1) Sequenced Collections (JEP 431, Java 21)

Java 21-ə qədər "siyahının birinci və sonuncu elementini almaq" vahid API-siz idi.

```java
// Java 20-də:
List<String> list = List.of("a", "b", "c");
String first = list.get(0);                 // List
String last  = list.get(list.size() - 1);

LinkedHashSet<String> set = new LinkedHashSet<>(List.of("a", "b", "c"));
String firstOfSet = set.iterator().next();  // Set-də ümumiyyətlə yox idi

Deque<String> deque = new ArrayDeque<>();
String firstOfDeque = deque.peekFirst();    // Deque-də var, amma ayrı API
```

Hər kolleksiyanın öz API-si var idi. Java 21-də vahid interface gəldi:

```java
public interface SequencedCollection<E> extends Collection<E> {
    E getFirst();
    E getLast();
    void addFirst(E e);
    void addLast(E e);
    E removeFirst();
    E removeLast();
    SequencedCollection<E> reversed();
}
```

### 2) Praktikada SequencedCollection

```java
import java.util.*;

public class SequencedDemo {

    public static void main(String[] args) {
        // List-də
        List<String> list = new ArrayList<>(List.of("a", "b", "c"));
        System.out.println(list.getFirst());        // "a"
        System.out.println(list.getLast());         // "c"

        list.addFirst("zero");
        list.addLast("end");
        // [zero, a, b, c, end]

        // reversed() — view, yeni liste yaratmır
        List<String> reversed = list.reversed();
        System.out.println(reversed.getFirst());    // "end"

        // LinkedHashSet də SequencedSet-dir indi
        LinkedHashSet<Integer> set = new LinkedHashSet<>();
        set.add(10);
        set.add(20);
        set.add(30);
        System.out.println(set.getFirst());         // 10
        System.out.println(set.getLast());          // 30
        set.addFirst(5);
        // [5, 10, 20, 30]

        // LinkedHashMap da SequencedMap-dir
        LinkedHashMap<String, Integer> map = new LinkedHashMap<>();
        map.put("a", 1);
        map.put("b", 2);
        map.put("c", 3);
        System.out.println(map.firstEntry());       // a=1
        System.out.println(map.lastEntry());        // c=3

        // TreeSet də SequencedSet-dir (təbii order-ə görə)
        TreeSet<Integer> tree = new TreeSet<>(Set.of(30, 10, 20));
        System.out.println(tree.getFirst());        // 10 (ən kiçik)
        System.out.println(tree.getLast());         // 30
    }
}
```

### 3) SequencedMap

```java
SequencedMap<String, User> users = new LinkedHashMap<>();
users.put("admin", new User("admin"));
users.put("guest", new User("guest"));
users.put("root", new User("root"));

Map.Entry<String, User> firstEntry = users.firstEntry();
Map.Entry<String, User> lastEntry = users.lastEntry();

users.putFirst("super", new User("super"));   // başa əlavə
users.putLast("bot", new User("bot"));        // sona əlavə

// Əks sıra ilə iterasiya
SequencedMap<String, User> reversedView = users.reversed();
for (var entry : reversedView.entrySet()) {
    System.out.println(entry.getKey());
}

// sequencedKeySet() / sequencedValues() / sequencedEntrySet()
SequencedSet<String> keys = users.sequencedKeySet();
```

`reversed()` metodu **view** qaytarır — yeni yaddaş allocate etmir. Əsas kolleksiya dəyişəndə view də dəyişir.

### 4) Stream Gatherers (JEP 485, Java 24 stable)

Stream API Java 8-dən bəri güclü idi, amma "sliding window", "batching", "running total" kimi əməliyyatlar çətin idi. **Gatherers** bu boşluğu doldurdu.

```java
import java.util.stream.*;

// Built-in gatherers
List<Integer> numbers = List.of(1, 2, 3, 4, 5, 6, 7, 8, 9, 10);

// 1) windowFixed — bərabər hissələrə böl
List<List<Integer>> batches = numbers.stream()
    .gather(Gatherers.windowFixed(3))
    .toList();
// [[1,2,3], [4,5,6], [7,8,9], [10]]

// 2) windowSliding — sliding window
List<List<Integer>> windows = numbers.stream()
    .gather(Gatherers.windowSliding(3))
    .toList();
// [[1,2,3], [2,3,4], [3,4,5], ..., [8,9,10]]

// 3) fold — left fold (tək dəyər qaytarır)
Integer sum = numbers.stream()
    .gather(Gatherers.fold(() -> 0, Integer::sum))
    .findFirst()
    .orElse(0);
// 55

// 4) scan — running total (hər addımın dəyərini saxlayır)
List<Integer> runningSum = numbers.stream()
    .gather(Gatherers.scan(() -> 0, Integer::sum))
    .toList();
// [1, 3, 6, 10, 15, 21, 28, 36, 45, 55]

// 5) mapConcurrent — paralel map (virtual thread-lə)
List<String> urls = List.of("url1", "url2", "url3");
List<Response> responses = urls.stream()
    .gather(Gatherers.mapConcurrent(4, url -> httpClient.get(url)))
    .toList();
// 4 virtual thread-də paralel fetch
```

### 5) Custom Gatherer — distinctBy

Stream API-də `distinct()` var, amma `distinctBy(keyExtractor)` yoxdur. Gatherer ilə özümüz yaza bilərik:

```java
import java.util.stream.Gatherer;
import java.util.HashSet;
import java.util.function.Function;

public final class CustomGatherers {

    public static <T, K> Gatherer<T, ?, T> distinctBy(Function<T, K> keyExtractor) {
        return Gatherer.ofSequential(
            HashSet<K>::new,                     // initializer — state
            (state, element, downstream) -> {    // integrator
                K key = keyExtractor.apply(element);
                if (state.add(key)) {
                    return downstream.push(element);
                }
                return true;                     // davam et
            }
        );
    }
}

record User(int id, String email) {}

List<User> users = List.of(
    new User(1, "a@x.com"),
    new User(2, "a@x.com"),     // duplicate email
    new User(3, "b@x.com")
);

List<User> unique = users.stream()
    .gather(CustomGatherers.distinctBy(User::email))
    .toList();
// [User(1,a@x.com), User(3,b@x.com)]
```

### 6) Custom Gatherer — batching with timeout

Production senariosu: stream-dən gələn event-ləri 100 ədəd və ya 1 saniyədən bir batch-a yığ:

```java
public static <T> Gatherer<T, ?, List<T>> batchSize(int size) {
    return Gatherer.ofSequential(
        ArrayList<T>::new,
        (buffer, element, downstream) -> {
            buffer.add(element);
            if (buffer.size() >= size) {
                List<T> batch = List.copyOf(buffer);
                buffer.clear();
                return downstream.push(batch);
            }
            return true;
        },
        (buffer, downstream) -> {                // finisher — qalanları göndər
            if (!buffer.isEmpty()) {
                downstream.push(List.copyOf(buffer));
            }
        }
    );
}

events.stream()
    .gather(batchSize(100))
    .forEach(batch -> repository.saveAll(batch));
```

### 7) Unnamed Variables (JEP 456, Java 22)

Underscore `_` artıq "istifadə etmirəm" deməkdir — compiler warning verməz.

```java
// Switch pattern-də
sealed interface Event permits Click, Scroll, KeyPress {}
record Click(int x, int y) {}
record Scroll(int delta) {}
record KeyPress(char key) {}

String describe(Event e) {
    return switch (e) {
        case Click(int x, int y)  -> "click at " + x + "," + y;
        case Scroll(int _)        -> "scrolled";     // delta lazım deyil
        case KeyPress(char _)     -> "key pressed";  // key lazım deyil
    };
}

// Catch-də
try {
    parseJson(input);
} catch (JsonException _) {           // exception-a toxunmuruq
    return Map.of();
}

// for-də
for (int _ = 0; _ < 5; _++) {
    System.out.println("Salam");
}

// Lambda-da
map.forEach((_, value) -> process(value));    // key lazım deyil

// Multiple underscores bir scope-da olmaq olar
var (_, _, y) = tripleCoord();
```

### 8) String Templates (JEP 465 — preview)

String Templates Java 21/22-də preview idi, Java 23-də də preview olaraq qalır (sintaksis dəyişdirilir), Java 24-də hələ final deyil. Yəni production-da istifadə etmək tövsiyə olunmur. Amma sintaksisi görmək yaxşıdır:

```java
// Hələ preview (bayraq ilə: --enable-preview)
String name = "Aysel";
int age = 25;

String greeting = STR."Salam \{name}, yaşın \{age}";
// "Salam Aysel, yaşın 25"

// SQL template (təhlükəsiz — injection qarşısı)
String id = "42";
// Custom template processor SQL inject-dən qoruyur

// Çox sətirli
String json = STR."""
    {
      "name": "\{name}",
      "age": \{age}
    }
    """;
```

Hələlik `String.format()`, `MessageFormat`, `+` operatoru istifadə olunur.

### 9) Flexible Main Methods (JEP 512, Java 25)

Java 25-də sadə başlanğıc üçün main dəyişdi:

```java
// Köhnə tərz (indi də işləyir)
public class Hello {
    public static void main(String[] args) {
        System.out.println("Salam");
    }
}

// Yeni tərz (Java 25 final)
void main() {
    System.out.println("Salam");
}

// İnstance main — class body bile lazım deyil (kompiler "unnamed class" yaradır)
// Unnamed class + instance main = sadə scripting
IO.println("Salam");                // yeni IO API də
```

Bu beginner-lərə Java öyrənməyi asanlaşdırır — "boilerplate" az.

### 10) Real pipeline nümunəsi

```java
record Trade(String symbol, long timestamp, double price, int volume) {}

// Son 1000 trade-i alıb:
// 1) 10-luq batch-ə böl
// 2) Hər batch üçün ortalama hesabla
// 3) Running window-da 5 batch-lik trend

public List<Double> analyzeTrades(List<Trade> trades) {
    return trades.stream()
        .gather(Gatherers.windowFixed(10))                              // 10-luq batch
        .map(batch -> batch.stream().mapToDouble(Trade::price).average().orElse(0))
        .gather(Gatherers.windowSliding(5))                             // 5 batch window
        .map(window -> window.stream().mapToDouble(Double::doubleValue).average().orElse(0))
        .toList();
}
```

---

## PHP-də istifadəsi

### 1) PHP array — insertion order by default

PHP array-ləri tarix boyu ordered-dir. `array_keys`, `array_values`, `foreach` daima insertion order-ə görə gedir:

```php
$users = [
    'admin' => 'Ali',
    'guest' => 'Veli',
    'root'  => 'Sara',
];

// İlk və sonuncu açar (PHP 7.3+)
$first = array_key_first($users);    // 'admin'
$last  = array_key_last($users);     // 'root'

$firstValue = $users[$first];        // 'Ali'
$lastValue  = $users[$last];         // 'Sara'

// Mövqe-əsaslı əlavə — native metod yoxdur, trick lazımdır
$users = ['super' => 'Root'] + $users;           // başa
$users['bot'] = 'Bot';                            // sona

// Əksinə çevir — yeni array yaradır (view DEYİL)
$reversed = array_reverse($users, preserve_keys: true);
```

**Diqqət:** PHP-də numeric və string key qarışığı gözləniləndən fərqli davrana bilər:

```php
$arr = ['a' => 1, 0 => 2, 'b' => 3, 1 => 4];
// 0 və 1 numeric key-lərdir, amma order qorunur
foreach ($arr as $k => $v) {
    echo "$k => $v\n";    // a,0,b,1 sırası
}

$merged = ['x' => 1] + ['x' => 2];    // 'x' => 1 (+ operatoru ilk variantı saxlayır)
$merged2 = array_merge(['x' => 1], ['x' => 2]);   // 'x' => 2 (sonuncu qazanır)
```

### 2) SplDoublyLinkedList və SplQueue

PHP standart kitabxanasında (SPL) deque var:

```php
$deque = new SplDoublyLinkedList();
$deque->push('a');           // sona
$deque->push('b');
$deque->unshift('zero');     // başa
// [zero, a, b]

$first = $deque[0];          // 'zero'
$last  = $deque[$deque->count() - 1];    // 'b'

$deque->shift();              // başdan sil, 'zero' qayıdır
$deque->pop();                // sondan sil, 'b' qayıdır

// Queue (FIFO)
$queue = new SplQueue();
$queue->enqueue('first');
$queue->enqueue('second');
$first = $queue->dequeue();  // 'first'
```

Amma SPL nadir hallarda istifadə olunur — PHP-çilər array-ə adət etməyiblər.

### 3) Generator — lazy stream

Generator PHP-nin Java Stream-ə ən yaxın qohumudur. `yield` ilə lazy iteration:

```php
function readLines(string $file): Generator
{
    $handle = fopen($file, 'r');
    try {
        while (($line = fgets($handle)) !== false) {
            yield rtrim($line, "\n");
        }
    } finally {
        fclose($handle);
    }
}

// 10GB faylı 10KB yaddaşla oxu
foreach (readLines('huge.log') as $line) {
    if (str_contains($line, 'ERROR')) {
        echo $line, "\n";
    }
}

// Pipeline
function filter(iterable $source, callable $predicate): Generator
{
    foreach ($source as $item) {
        if ($predicate($item)) {
            yield $item;
        }
    }
}

function map(iterable $source, callable $mapper): Generator
{
    foreach ($source as $item) {
        yield $mapper($item);
    }
}

$errors = map(
    filter(readLines('huge.log'), fn($l) => str_contains($l, 'ERROR')),
    fn($l) => json_decode($l, true)
);
```

### 4) Laravel Collection — fluent API

Laravel `Illuminate\Support\Collection` Stream-ə bənzər API verir (amma eager, hər metod yeni array yaradır):

```php
use Illuminate\Support\Collection;

$trades = collect([
    ['symbol' => 'AAPL', 'price' => 180],
    ['symbol' => 'AAPL', 'price' => 182],
    ['symbol' => 'GOOG', 'price' => 140],
    // ...
]);

// chunk — Gatherers.windowFixed kimi
$batches = $trades->chunk(10);
// Collection of Collections, hər biri 10 element

// sliding — Gatherers.windowSliding kimi (Laravel 9+)
$windows = $trades->sliding(size: 5, step: 1);
// [0-4], [1-5], [2-6], ...

// partition — ikiyə böl
[$apple, $other] = $trades->partition(fn($t) => $t['symbol'] === 'AAPL');

// pipe — böyük blok transform
$result = $trades
    ->filter(fn($t) => $t['price'] > 100)
    ->map(fn($t) => ['symbol' => $t['symbol'], 'price' => $t['price'] * 1.1])
    ->groupBy('symbol')
    ->map(fn($group) => $group->avg('price'))
    ->pipe(fn($avgs) => $avgs->sortDesc());

// reduce — fold
$total = $trades->reduce(fn($sum, $t) => $sum + $t['price'], 0);
```

### 5) LazyCollection — generator əsaslı

Laravel `LazyCollection` generator üstündə qurulub — böyük dataset-lər üçün:

```php
use Illuminate\Support\LazyCollection;

LazyCollection::make(function () {
    $handle = fopen('huge.csv', 'r');
    while (($row = fgetcsv($handle)) !== false) {
        yield $row;
    }
    fclose($handle);
})
    ->filter(fn($row) => $row[2] === 'active')
    ->map(fn($row) => ['id' => $row[0], 'name' => $row[1]])
    ->chunk(500)
    ->each(function ($chunk) {
        User::insert($chunk->toArray());
    });
// Hər zaman yaddaşda 500 element var, bütün fayl yox
```

### 6) ramsey/collection — tipli kolleksiya

```bash
composer require ramsey/collection
```

```php
use Ramsey\Collection\Collection;
use Ramsey\Collection\Map\TypedMap;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
    ) {}
}

$users = new Collection(User::class);
$users->add(new User(1, 'a@x.com'));
$users->add(new User(2, 'b@x.com'));

// Type-safe
$users->add('not a user');       // TypeError
```

### 7) `_` PHP-də xüsusi deyil

PHP-də `_` adi dəyişən adıdır. İstifadə edilməyən parametr üçün konvensiya var, amma compiler bir xəbərdarlıq vermir:

```php
// İstifadə edilməyən parametrlər
array_walk($array, function ($value, $_) {     // $_ — istifadə etməyəcəm
    echo $value;
});

// Switch-də pattern matching yoxdur (Java-dakı kimi)
// PHP 8.0 match istifadə olunur
$message = match ($event::class) {
    Click::class    => 'clicked',
    Scroll::class   => 'scrolled',
    KeyPress::class => 'key pressed',
};
```

PHPStan və Psalm kimi static analyzer-lər `$unused` prefix ilə oxşar effekt verir.

### 8) Real pipeline — Laravel Collection ilə

```php
use Illuminate\Support\Collection;

final readonly class Trade
{
    public function __construct(
        public string $symbol,
        public int $timestamp,
        public float $price,
        public int $volume,
    ) {}
}

/**
 * @param  Collection<int, Trade>  $trades
 * @return Collection<int, float>
 */
function analyzeTrades(Collection $trades): Collection
{
    return $trades
        ->chunk(10)                                          // windowFixed(10)
        ->map(fn($batch) => $batch->avg('price'))            // ortalama
        ->pipe(fn($avgs) => $avgs->sliding(5))               // sliding window
        ->map(fn($window) => $window->avg());                // ortalama
}
```

### 9) Symfony String və Array helper

Symfony `symfony/string` və `symfony/polyfill` bəzi modern helper verir:

```php
use Symfony\Component\String\UnicodeString;

$s = new UnicodeString('Salam dünya');
$s->words();           // ['Salam', 'dünya']
$s->truncate(10);
```

### 10) Spatie/Laravel-query-builder və Collection pipeline

```php
$result = QueryBuilder::for(Order::class)
    ->allowedFilters(['status', 'user_id'])
    ->get()
    ->pipe(fn($orders) => $orders->groupBy('status'))
    ->map->avg('amount');
```

`->map->avg('amount')` higher-order message syntax — hər qrup üçün `avg('amount')` çağırır.

---

## Əsas fərqlər

| Xüsusiyyət | Java (21-25) | PHP (8.1-8.4) |
|---|---|---|
| Collection order | SequencedCollection (Java 21) | Array daim ordered |
| `getFirst()` / `getLast()` | Interface metodu | `array_key_first/last` |
| `addFirst()` / `addLast()` | Mutable collection metodu | Array birləşdirmə/trick |
| `reversed()` view | Yeni alloc yox (view) | `array_reverse` yeni array |
| Stream sliding window | `Gatherers.windowSliding()` (Java 24) | Laravel `sliding()`, əl ilə |
| Stream fixed batching | `Gatherers.windowFixed()` | Laravel `chunk()` |
| Scan/running total | `Gatherers.scan()` | Manual foreach + reduce |
| Concurrent map | `Gatherers.mapConcurrent(n, fn)` | Manual amphp/Swoole |
| Custom stream operator | `Gatherer.ofSequential()` | Custom generator function |
| Lazy evaluation | Stream default lazy | `LazyCollection`, generator |
| Unnamed variable `_` | JEP 456 (Java 22) | Adi dəyişən, xüsusi deyil |
| String interpolation | String Templates (preview) | `"Salam {$name}"` daim var |
| Minimal main | Flexible Main Methods (Java 25) | PHP-də main yoxdur |
| Polimorfik kolleksiyalar | Generic + Sequenced | `ramsey/collection` |
| Ordered Set | LinkedHashSet SequencedSet-dir | `array_unique` + order |
| Ordered Map | LinkedHashMap SequencedMap-dir | Array elə orderli |

---

## Niyə belə fərqlər var?

**Java-nın "interface-first" dizaynı.** Java collection hierarchy strict interface-lərlə qurulub (`Collection`, `List`, `Set`, `Map`). Yeni `SequencedCollection` interface-ni əlavə etmək uyğun gələn bütün sinifləri (List, LinkedHashSet, ArrayDeque) avtomatik dəstəklədi. PHP-də `array` tek primitive tip-dir — yeni "metod" əlavə etmək dili dəyişdirməkdir.

**PHP array-in hibrid təbiəti.** PHP array həm list, həm map, həm queue, həm stack-dir. Bu ona "hər şeyə uyğun" görünüş verir, amma type safety və interface konsepsiyası yoxdur. Nəticədə `array_key_first` kimi global funksiyalar var, Java-nın unified API-si yox.

**Stream vs Generator fəlsəfəsi.** Java Stream-lər "push" modelini dəstəkləyir — parallel, lazy, fork-join istifadə edir. PHP generator "pull" modelindədir (hər `foreach` iteration-da növbəti dəyəri çəkir). Gatherers paralel map kimi operatorlara yol açır, PHP-də bu hələ amphp/Swoole kimi xarici runtime tələb edir.

**Laravel Collection eager vs Java Stream lazy.** Laravel `Collection` default-olaraq eager — hər method yeni array qaytarır. Bu böyük data-da bahalıdır. Java Stream lazy — `terminal` operator (collect, forEach, toList) çağırılana qədər heç nə icra olunmur. PHP-də lazy olmaq istəsən `LazyCollection` və ya raw generator lazımdır.

**`_` — dil səviyyəsi fərqi.** Java compiler `_` üçün xüsusi qayda bilir — duplicate `_` eyni scope-da olmağa icazə verir (digər dəyişənlər olmur). PHP-də hər dəyişən unikal olmalıdır.

**String interpolation tarixi.** PHP başlanğıcdan `"$var"` dəstəkləyib (template dili kimi doğulub). Java isə "safety first" — string template hələ preview-dadır, çünki SQL injection, XSS kimi məsələlərə cavab axtarılır (template processor API).

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**
- `SequencedCollection`, `SequencedSet`, `SequencedMap` (JEP 431)
- `reversed()` view (copy-siz)
- `Gatherers.windowFixed`, `windowSliding`, `scan`, `fold`, `mapConcurrent`
- `Gatherer` — custom stream operator API
- Unnamed variable `_` compiler dəstəyi (JEP 456)
- String Templates (preview) — SQL inject-safe template processor
- Flexible Main Methods (Java 25)
- Pattern matching switch (`case Record(var x, var _)`)
- Parallel stream native (ForkJoinPool)
- JEP proses — yeni API üçün ictimai debat

**Yalnız PHP-də:**
- Array — həm list, həm map tək primitive
- String interpolation 30 ildən çoxdur (`"Salam $name"`)
- `array_merge` / `+` operator fərqi
- Laravel Collection higher-order message (`->map->avg('col')`)
- `LazyCollection` — generator üstündə fluent API
- `yield from` — generator delegation
- `ramsey/collection`, `doctrine/collections` typed collection paketləri
- PHPStan/Psalm ilə array shape type-ləri (`array{id: int, name: string}`)
- `compact()`, `extract()` — array ↔ variable çevrilmə

---

## Best Practices

**Java:**
- Yeni kod yazanda `SequencedCollection` metodlarından istifadə et — `list.get(0)` əvəzinə `list.getFirst()`
- `reversed()` view-dir, orijinalı dəyişmir — amma `addFirst` orijinalı dəyişir
- Gatherer yazanda `ofSequential` ilə başla, lazım gələndə `of` (parallel) istifadə et
- `mapConcurrent(n, fn)` virtual thread istifadə edir — I/O-bound üçün ideal, CPU-bound üçün parallel stream daha yaxşı
- Unnamed `_` ilə kod daha oxunaqlı olur — pattern match-də destructure edib istifadə etmədiyin field-ləri `_` ilə göstər
- String Templates hələ preview — production-da `MessageFormat`, `String.format`, builder istifadə et
- Flexible main yalnız sadə script/öyrənmək üçün, böyük layihələrdə klassik sintaksis

**PHP:**
- Böyük dataset üçün `Collection` deyil, `LazyCollection` seç
- Array-lərdə `array_key_first/last` istifadə et — `array_keys($arr)[0]` anti-pattern
- `array_merge` ilə `+` operatoru fərqini bil — key collision davranışı fərqlidir
- `yield` istifadə edərək custom stream pipeline-ları yaz — yaddaşa qənaət
- Type-safe kolleksiya istəyirsənsə `ramsey/collection` və ya PHPStan array shape
- `$unused` və ya `$_` konvensiyasını istifadə et — amma bu dil qaydası deyil, koman­danın razılaşması
- Laravel `pipe()` ilə böyük transform zəncirlərini modullaş­dır

---

## Yekun

Java 21-25 yeni API-lər gətirdi: `SequencedCollection` siyahı/map-də "birinci və sonuncu" vahid API-si, `Stream.gather()` sliding/batching/scan/paralel map, `_` unnamed variable, String Templates (preview), Flexible Main Methods. Bu dəyişikliklər Java-nı "boilerplate-heavy" imicindən uzaqlaşdırır.

PHP fərqli yolu tutur — array insertion order-ə görə daim ordered, Generator ilə lazy stream, Laravel Collection/LazyCollection ilə fluent API, ramsey/collection ilə typed kolleksiya. Hər iki dildə eyni nəticə alınır, amma yazı tərzi və performans xarakteristikaları fərqlidir.

Seçimdə prinsip sadədir: Java böyük-scale, type-safe və parallel əməliyyat üçün daha güclü alətlər verir; PHP isə rapid prototyping, kiçik-orta data üçün son dərəcə oxunaqlı API təklif edir. Mühüm olanı hər ikisini də məqsədəuyğun istifadə etməkdir.
