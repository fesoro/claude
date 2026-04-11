# Kolleksiyalar və Massivlər

## Giris

Java və PHP data toplusuna tamamilə fərqli yanaşır. Java-da mürəkkəb, çoxsəviyyəli **Collections Framework** var -- hər data strukturu üçün ayrı sinif mövcuddur (List, Set, Map, Queue). PHP isə bütün bu ehtiyacları tək bir data strukturu ilə həll edir: **array**. PHP-nin array-i həm massiv, həm əlaqəli massiv (map), həm yığın (stack), həm növbə (queue) rolunu oynayır.

---

## Java-da istifadəsi

### Collections Framework iyerarxiyası

```
Iterable
  └── Collection
        ├── List (sıralı, təkrar elementlər ola bilər)
        │     ├── ArrayList
        │     ├── LinkedList
        │     └── Vector (köhnə, istifadə olunmur)
        ├── Set (sırasız, təkrar element yoxdur)
        │     ├── HashSet
        │     ├── LinkedHashSet
        │     └── TreeSet (sıralı)
        └── Queue (FIFO növbə)
              ├── LinkedList
              ├── PriorityQueue
              └── Deque
                    └── ArrayDeque

Map (Collection-dan deyil, ayrı iyerarxiya)
  ├── HashMap
  ├── LinkedHashMap
  ├── TreeMap (sıralı açarlar)
  └── Hashtable (köhnə)
```

### List -- sıralı kolleksiya

```java
// ArrayList -- ən çox istifadə olunan List
List<String> adlar = new ArrayList<>();
adlar.add("Orxan");
adlar.add("Ali");
adlar.add("Vəli");
adlar.add("Ali");       // Təkrar element olar bilər

// İndeks ilə giriş
String birinci = adlar.get(0);     // "Orxan" -- O(1)
adlar.set(1, "Əli");               // 1-ci indeksi dəyiş

// Elementləri axtarma
boolean var = adlar.contains("Orxan");  // true
int indeks = adlar.indexOf("Vəli");     // 2

// Silmə
adlar.remove("Ali");           // İlk "Ali"-ni silir
adlar.remove(0);               // 0-cı indeksdəki elementi silir

// Dəyişməz siyahı yaratma
List<String> sabit = List.of("a", "b", "c");    // Dəyişməz (Java 9+)
// sabit.add("d");  // UnsupportedOperationException!

List<String> sabit2 = Collections.unmodifiableList(adlar);  // Köhnə yol

// LinkedList -- əlavə/silmə sürətli, random giriş yavaş
List<String> əlaqəli = new LinkedList<>();
əlaqəli.add("A");
əlaqəli.addFirst("İlk");  // Başa əlavə: O(1)
əlaqəli.addLast("Son");   // Sona əlavə: O(1)
```

### Set -- unikal elementlər

```java
// HashSet -- sırasız, ən sürətli
Set<String> meyveler = new HashSet<>();
meyveler.add("alma");
meyveler.add("armud");
meyveler.add("alma");     // Əlavə olunmaz -- artıq mövcuddur
System.out.println(meyveler.size()); // 2

// LinkedHashSet -- əlavə sırasını saxlayır
Set<String> sıralı = new LinkedHashSet<>();
sıralı.add("c");
sıralı.add("a");
sıralı.add("b");
System.out.println(sıralı); // [c, a, b] -- əlavə sırası

// TreeSet -- natural sıraya görə sıralayır
Set<String> ağac = new TreeSet<>();
ağac.add("c");
ağac.add("a");
ağac.add("b");
System.out.println(ağac); // [a, b, c] -- əlifba sırası

// Set əməliyyatları
Set<Integer> a = Set.of(1, 2, 3, 4);
Set<Integer> b = Set.of(3, 4, 5, 6);

// Kəsişmə (intersection)
Set<Integer> kəsişmə = new HashSet<>(a);
kəsişmə.retainAll(b);  // {3, 4}

// Birləşmə (union)
Set<Integer> birləşmə = new HashSet<>(a);
birləşmə.addAll(b);    // {1, 2, 3, 4, 5, 6}

// Fərq (difference)
Set<Integer> fərq = new HashSet<>(a);
fərq.removeAll(b);     // {1, 2}
```

### Map -- açar-dəyər cütləri

```java
// HashMap -- ən çox istifadə olunan
Map<String, Integer> yaşlar = new HashMap<>();
yaşlar.put("Orxan", 25);
yaşlar.put("Ali", 30);
yaşlar.put("Vəli", 28);

// Dəyəri almaq
int yaş = yaşlar.get("Orxan");           // 25
Integer yox = yaşlar.get("Naməlum");     // null

// getOrDefault -- mövcud olmayan açar üçün
int default_yaş = yaşlar.getOrDefault("Naməlum", 0);  // 0

// putIfAbsent -- yalnız yoxdursa əlavə et
yaşlar.putIfAbsent("Orxan", 100);  // Dəyişmir, artıq mövcuddur

// Yoxlama
boolean varmi = yaşlar.containsKey("Ali");         // true
boolean dəyərVarmi = yaşlar.containsValue(30);     // true

// Iterasiya
for (Map.Entry<String, Integer> entry : yaşlar.entrySet()) {
    System.out.println(entry.getKey() + ": " + entry.getValue());
}

// Dəyişməz Map
Map<String, Integer> sabit = Map.of("a", 1, "b", 2, "c", 3);

// compute -- dəyəri hesablama ilə dəyişdirmək
yaşlar.compute("Orxan", (açar, dəyər) -> dəyər + 1);  // 26

// merge
yaşlar.merge("Orxan", 1, Integer::sum);  // Mövcud dəyərə 1 əlavə et
```

### Queue və Deque

```java
// Queue -- FIFO (First In, First Out)
Queue<String> növbə = new LinkedList<>();
növbə.offer("Birinci");   // Sona əlavə et
növbə.offer("İkinci");
növbə.offer("Üçüncü");

String ilk = növbə.poll();   // "Birinci" -- çıxarır
String bax = növbə.peek();   // "İkinci" -- çıxarmır, yalnız baxır

// PriorityQueue -- prioritetə görə sıralayır
Queue<Integer> pq = new PriorityQueue<>();
pq.offer(30);
pq.offer(10);
pq.offer(20);
System.out.println(pq.poll());  // 10 -- ən kiçik birinci

// Deque -- hər iki uçdan əlavə/silmə
Deque<String> deque = new ArrayDeque<>();
deque.addFirst("A");
deque.addLast("B");
deque.addFirst("C");
// [C, A, B]
System.out.println(deque.pollFirst()); // "C"
System.out.println(deque.pollLast());  // "B"

// Stack kimi istifadə (LIFO)
Deque<String> stack = new ArrayDeque<>();
stack.push("Birinci");
stack.push("İkinci");
stack.push("Üçüncü");
System.out.println(stack.pop());  // "Üçüncü" -- sonuncu birinci çıxır
```

### Stream API ilə filtering və sorting (Java 8+)

```java
List<String> adlar = List.of("Orxan", "Ali", "Vəli", "Aygün", "Arzu");

// Filtrleme
List<String> aIleBaslayan = adlar.stream()
    .filter(ad -> ad.startsWith("A"))
    .toList();  // [Ali, Aygün, Arzu]

// Sıralama
List<String> sıralı = adlar.stream()
    .sorted()
    .toList();  // [Ali, Arzu, Aygün, Orxan, Vəli]

// Map (çevirmə)
List<String> böyükHərf = adlar.stream()
    .map(String::toUpperCase)
    .toList();  // [ORXAN, ALI, VƏLİ, AYGÜN, ARZU]

// Reduce (yığma)
int ümumUzunluq = adlar.stream()
    .mapToInt(String::length)
    .sum();  // Bütün adların uzunluq cəmi

// Qruplaşdırma
Map<Character, List<String>> qruplar = adlar.stream()
    .collect(Collectors.groupingBy(ad -> ad.charAt(0)));
// {O=[Orxan], A=[Ali, Aygün, Arzu], V=[Vəli]}

// Mürəkkəb nümunə: 25-dən böyük yaşdakıların adları, sıralı
record İstifadeci(String ad, int yaş) {}

List<İstifadeci> users = List.of(
    new İstifadeci("Orxan", 25),
    new İstifadeci("Ali", 30),
    new İstifadeci("Vəli", 22),
    new İstifadeci("Aygün", 28)
);

List<String> nəticə = users.stream()
    .filter(u -> u.yaş() > 25)
    .sorted(Comparator.comparing(İstifadeci::ad))
    .map(İstifadeci::ad)
    .toList();  // [Ali, Aygün]
```

### Massivlər (Arrays)

Java-da massivlər sabit ölçülüdür və kolleksiyalardan fərqlənir:

```java
// Massiv elanı
int[] ededler = new int[5];           // 5 elementlik, hamısı 0
String[] adlar = {"Ali", "Vəli"};    // İlkin dəyərlərlə

// Ölçüsü dəyişmir!
// ededler[5] = 10;  // ArrayIndexOutOfBoundsException!

// Massivdən List-ə çevirmə
List<String> list = Arrays.asList(adlar);    // Sabit ölçülü List
List<String> list2 = new ArrayList<>(List.of(adlar));  // Dəyişə bilən List

// Massiv sıralama
int[] arr = {3, 1, 4, 1, 5};
Arrays.sort(arr);  // [1, 1, 3, 4, 5] -- yerində sıralayır

// Massiv axtarma
int indeks = Arrays.binarySearch(arr, 4);  // 3 (sıralı massivdə)

// Çoxölçülü massiv
int[][] matris = {
    {1, 2, 3},
    {4, 5, 6},
    {7, 8, 9}
};
```

---

## PHP-də istifadəsi

### PHP array -- universal data strukturu

PHP-nin array-i Java-dakı ArrayList, HashMap, LinkedList, Stack və Queue-nun hamısını əvəz edir:

```php
// İndeksli massiv (Java-nın ArrayList ekvivalenti)
$adlar = ['Orxan', 'Ali', 'Vəli'];
$adlar[] = 'Aygün';          // Sona əlavə et
echo $adlar[0];               // "Orxan"
echo count($adlar);           // 4

// Əlaqəli massiv (Java-nın HashMap ekvivalenti)
$yaşlar = [
    'Orxan' => 25,
    'Ali' => 30,
    'Vəli' => 28,
];
echo $yaşlar['Orxan'];       // 25

// Qarışıq -- eyni anda həm indeksli, həm açarlı
$qarışıq = [
    'ad' => 'Orxan',
    'yaş' => 25,
    0 => 'sıfır indeks',
    'hobblər' => ['oxumaq', 'idman'],
];

// Çoxölçülü massiv
$matris = [
    [1, 2, 3],
    [4, 5, 6],
    [7, 8, 9],
];
echo $matris[1][2]; // 6
```

### Massiv əməliyyatları

```php
// Əlavə etmə
$list = [1, 2, 3];
$list[] = 4;                      // Sona: [1, 2, 3, 4]
array_push($list, 5, 6);         // Sona: [1, 2, 3, 4, 5, 6]
array_unshift($list, 0);         // Başa: [0, 1, 2, 3, 4, 5, 6]

// Silmə
array_pop($list);                // Sondan çıxar: 6
array_shift($list);              // Başdan çıxar: 0
unset($list[2]);                 // İndeks 2-ni sil (indekslər dəyişmir!)

// unset-dən sonra indekslər sınır
$list = [1, 2, 3, 4, 5];
unset($list[2]);
// $list artıq: [0 => 1, 1 => 2, 3 => 4, 4 => 5] -- indeks 2 yoxdur!
$list = array_values($list);  // Yenidən indekslə: [0 => 1, 1 => 2, 2 => 4, 3 => 5]

// Axtarma
$mövcuddur = in_array('Orxan', $adlar);          // true
$indeks = array_search('Ali', $adlar);            // 1
$açarMövcud = array_key_exists('Orxan', $yaşlar); // true
$dəyərMövcud = isset($yaşlar['Orxan']);            // true (null deyilsə)

// Birləşdirmə
$a = [1, 2, 3];
$b = [4, 5, 6];
$birləşmiş = array_merge($a, $b);    // [1, 2, 3, 4, 5, 6]
$birləşmiş2 = [...$a, ...$b];        // [1, 2, 3, 4, 5, 6] (spread operatoru)

// Əlaqəli massiv birləşdirmə (eyni açarlar üst yazılır)
$əsas = ['ad' => 'Orxan', 'yaş' => 25];
$əlavə = ['yaş' => 26, 'şəhər' => 'Bakı'];
$birləşmiş = array_merge($əsas, $əlavə);
// ['ad' => 'Orxan', 'yaş' => 26, 'şəhər' => 'Bakı']

// + operatoru (fərqli davranır -- birinci dəyər qalır)
$nəticə = $əsas + $əlavə;
// ['ad' => 'Orxan', 'yaş' => 25, 'şəhər' => 'Bakı'] -- yaş 25 qaldı!
```

### Sıralama

```php
// Dəyərə görə sıralama
$ededler = [3, 1, 4, 1, 5, 9];
sort($ededler);          // [1, 1, 3, 4, 5, 9] -- açarlar yenilənir
rsort($ededler);         // Əksinə

// Açarı saxlayaraq sıralama
$yaşlar = ['Vəli' => 28, 'Orxan' => 25, 'Ali' => 30];
asort($yaşlar);          // Dəyərə görə: ['Orxan' => 25, 'Vəli' => 28, 'Ali' => 30]
arsort($yaşlar);         // Əksinə

// Açara görə sıralama
ksort($yaşlar);          // Açara görə: ['Ali' => 30, 'Orxan' => 25, 'Vəli' => 28]
krsort($yaşlar);         // Əksinə

// Xüsusi sıralama (Java-nın Comparator ekvivalenti)
$istifadeciler = [
    ['ad' => 'Orxan', 'yaş' => 25],
    ['ad' => 'Ali', 'yaş' => 30],
    ['ad' => 'Vəli', 'yaş' => 22],
];

usort($istifadeciler, function (array $a, array $b): int {
    return $a['yaş'] <=> $b['yaş'];  // Spaceship operatoru
});
// Yaşa görə: Vəli(22), Orxan(25), Ali(30)

// Arrow function ilə (PHP 7.4+)
usort($istifadeciler, fn($a, $b) => $a['yaş'] <=> $b['yaş']);
```

### Filtrleme və çevirmə

```php
$ededler = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

// array_filter -- Java-nın stream().filter() ekvivalenti
$cütlər = array_filter($ededler, fn($n) => $n % 2 === 0);
// [2, 4, 6, 8, 10] -- Diqqət: açarlar saxlanılır!

// array_map -- Java-nın stream().map() ekvivalenti
$kvadratlar = array_map(fn($n) => $n ** 2, $ededler);
// [1, 4, 9, 16, 25, 36, 49, 64, 81, 100]

// array_reduce -- Java-nın stream().reduce() ekvivalenti
$cəm = array_reduce($ededler, fn($carry, $n) => $carry + $n, 0);
// 55

// Bir neçə əməliyyatı birləşdirmək (chaining yoxdur!)
$nəticə = array_map(
    fn($n) => $n ** 2,
    array_filter($ededler, fn($n) => $n % 2 === 0)
);
// [4, 16, 36, 64, 100]

// array_column -- obyekt massivindən bir sahəni çıxarmaq
$istifadeciler = [
    ['ad' => 'Orxan', 'yaş' => 25, 'şəhər' => 'Bakı'],
    ['ad' => 'Ali', 'yaş' => 30, 'şəhər' => 'Gəncə'],
    ['ad' => 'Vəli', 'yaş' => 28, 'şəhər' => 'Bakı'],
];

$adlar = array_column($istifadeciler, 'ad');
// ['Orxan', 'Ali', 'Vəli']

$adlarŞəhərə_görə = array_column($istifadeciler, 'ad', 'şəhər');
// ['Bakı' => 'Vəli', 'Gəncə' => 'Ali'] -- eyni açar üst yazılır!
```

### Set ekvivalenti

PHP-də ayrı Set sinfi yoxdur, amma array ilə simulyasiya olunur:

```php
// Unikal dəyərlər
$massiv = [1, 2, 2, 3, 3, 3];
$unikal = array_unique($massiv);  // [1, 2, 3]

// Set əməliyyatları
$a = [1, 2, 3, 4];
$b = [3, 4, 5, 6];

$kəsişmə = array_intersect($a, $b);        // [3, 4]
$birləşmə = array_unique([...$a, ...$b]);   // [1, 2, 3, 4, 5, 6]
$fərq = array_diff($a, $b);                 // [1, 2]
```

### Stack və Queue ekvivalenti

```php
// Stack (LIFO) -- array_push / array_pop
$stack = [];
$stack[] = 'Birinci';      // push
$stack[] = 'İkinci';
$stack[] = 'Üçüncü';
$son = array_pop($stack);  // "Üçüncü" -- pop

// Queue (FIFO) -- array_push / array_shift
$queue = [];
$queue[] = 'Birinci';       // enqueue
$queue[] = 'İkinci';
$queue[] = 'Üçüncü';
$ilk = array_shift($queue); // "Birinci" -- dequeue
// Diqqət: array_shift O(n) əməliyyatdır -- böyük massivlərdə yavaşdır!
```

### SplFixedArray və SPL data strukturları

PHP-nin SPL (Standard PHP Library) kitabxanası xüsusi data strukturları təklif edir:

```php
// SplFixedArray -- sabit ölçülü, yalnız integer açarlı
// Adi array-dən daha az yaddaş istifadə edir
$sabit = new SplFixedArray(1000);
$sabit[0] = 'birinci';
$sabit[999] = 'sonuncu';
// $sabit[1000] = 'xəta';  // RuntimeException: Index invalid

// Ölçünü dəyişmək mümkündür
$sabit->setSize(2000);

// SplDoublyLinkedList
$list = new SplDoublyLinkedList();
$list->push('A');
$list->push('B');
$list->unshift('Z');  // Başa əlavə
echo $list->pop();    // "B"
echo $list->shift();  // "Z"

// SplPriorityQueue (Java-nın PriorityQueue ekvivalenti)
$pq = new SplPriorityQueue();
$pq->insert('az vacib', 1);
$pq->insert('çox vacib', 10);
$pq->insert('orta', 5);

echo $pq->extract();  // "çox vacib" -- ən yüksək prioritet

// SplStack
$stack = new SplStack();
$stack->push('A');
$stack->push('B');
echo $stack->pop();  // "B"

// SplQueue
$queue = new SplQueue();
$queue->enqueue('birinci');
$queue->enqueue('ikinci');
echo $queue->dequeue();  // "birinci"
```

### İteratorlar

```php
// Generator -- yaddaş effektiv iterasiya (Java-nın Stream-inə bənzəyir)
function fibonacçi(int $limit): Generator
{
    $a = 0;
    $b = 1;
    for ($i = 0; $i < $limit; $i++) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
}

foreach (fibonacçi(10) as $ədəd) {
    echo $ədəd . ' ';  // 0 1 1 2 3 5 8 13 21 34
}

// Xüsusi iterator sinfi
class ƏdədAralığı implements Iterator
{
    private int $cari;

    public function __construct(
        private int $başlanğıc,
        private int $son,
    ) {
        $this->cari = $başlanğıc;
    }

    public function current(): int { return $this->cari; }
    public function key(): int { return $this->cari - $this->başlanğıc; }
    public function next(): void { $this->cari++; }
    public function rewind(): void { $this->cari = $this->başlanğıc; }
    public function valid(): bool { return $this->cari <= $this->son; }
}

foreach (new ƏdədAralığı(1, 5) as $indeks => $dəyər) {
    echo "$indeks: $dəyər\n";  // 0: 1, 1: 2, 2: 3, 3: 4, 4: 5
}
```

### Laravel Collection (bonus)

Laravel-in Collection sinfi Java Stream API-nin PHP ekvivalentidir:

```php
use Illuminate\Support\Collection;

$istifadeciler = collect([
    ['ad' => 'Orxan', 'yaş' => 25],
    ['ad' => 'Ali', 'yaş' => 30],
    ['ad' => 'Vəli', 'yaş' => 22],
    ['ad' => 'Aygün', 'yaş' => 28],
]);

// Chaining -- Java Stream kimi!
$nəticə = $istifadeciler
    ->filter(fn($u) => $u['yaş'] > 25)
    ->sortBy('ad')
    ->pluck('ad')
    ->values()
    ->all();
// ['Ali', 'Aygün']

// Qruplaşdırma
$şəhərlərə_görə = $istifadeciler->groupBy('şəhər');

// Reduce
$cəmYaş = $istifadeciler->sum('yaş');  // 105

// Lazy Collection -- böyük data üçün
use Illuminate\Support\LazyCollection;

LazyCollection::make(function () {
    $fayl = fopen('böyük_fayl.csv', 'r');
    while ($sətir = fgets($fayl)) {
        yield str_getcsv($sətir);
    }
})
->filter(fn($sətir) => $sətir[2] > 25)
->each(fn($sətir) => işlə($sətir));
// Bütün fayl yaddaşa yüklənmir!
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Əsas data strukturu | Collections Framework (20+ sinif) | array (tək struktur) |
| Tip təhlükəsizliyi | Generics ilə (List\<String\>) | Yoxdur (array hər şeyi saxlayır) |
| List | ArrayList, LinkedList | array (indeksli) |
| Map | HashMap, TreeMap | array (əlaqəli) |
| Set | HashSet, TreeSet | array_unique() |
| Queue | LinkedList, ArrayDeque | array_push/array_shift və ya SplQueue |
| Stack | Deque (push/pop) | array_push/array_pop və ya SplStack |
| Dəyişməzlik | List.of(), Map.of() (Java 9+) | Yoxdur (const ilə yalnız dəyişən, massiv dəyişə bilər) |
| Stream/Pipeline | Stream API (Java 8+) | array_filter/array_map (chaining yoxdur) |
| Chaining | Stream API ilə | Laravel Collection ilə |
| Sorting | Collections.sort(), stream().sorted() | sort(), usort(), asort() |
| Yaddaş effektivliyi | Hər struktur optimal | array universal, amma optimal deyil |
| Sabit ölçü massiv | `int[]`, `String[]` | SplFixedArray |
| Generator/Lazy | Stream (lazy by default) | yield (Generator) |

---

## Niyə belə fərqlər var?

### Niyə Java-da bu qədər çox kolleksiya sinfi var?

Java-nın dizayn fəlsəfəsi **"doğru alət, doğru iş"** prinsipinə əsaslanır:

1. **Performans:** ArrayList random giriş üçün O(1), LinkedList əlavə/silmə üçün O(1). Düzgün seçim performansa birbaşa təsir edir.

2. **Semantik aydınlıq:** Kod `Set<String>` istifadə edirsə, oxuyan hər kəs bilir ki, burada unikal elementlər var. `List` isə təkrar elementlərə icazə verir. Bu, kodun niyyətini aydınlaşdırır.

3. **Tip təhlükəsizliyi:** Generics ilə `List<Istifadeci>` yazdıqda, kompilyator yanlış tipin əlavə olunmasını qadağan edir.

4. **İmmutability:** `List.of()` və `Map.of()` dəyişməz kolleksiyalar yaradır. Bu, çox thread-li proqramlarda çox vacibdir.

### Niyə PHP-də tək array hər şeyi edir?

PHP-nin yanaşması **sadəlik və praktikliyə** əsaslanır:

1. **Veb-proqramlaşdırma konteksti:** Veb-proqramlar adətən kiçik data topluları ilə işləyir (bir verilənlər bazası sorğusunun nəticəsi). Milyonlarla element olan kolleksiyalar nadir haldır.

2. **Öyrənmə asanlığı:** Bir data strukturu öyrənmək 20 sinif öyrənməkdən asandır. PHP-nin hədəf auditoriyası tez başlamaq istəyən veb-inkişafçılardır.

3. **PHP array-in arxitekturası:** PHP-nin array-i əslində ordered hash map-dir (sıralı hash xəritəsi). Bu dizayn əksər istifadə hallarını əhatə edir -- indeksli giriş, açar-dəyər cütləri, sıralama, iterasiya.

4. **Performans trade-off:** PHP array universal olduğundan, heç bir xüsusi halda optimal deyil. Amma veb-proqramların əksəriyyəti üçün yetərlidir. Performans kritik olduqda SplFixedArray kimi SPL sinifləri istifadə oluna bilər.

### Stream API vs array funksiyaları

Java-nın Stream API-si **lazy evaluation** (tənbəl hesablama) istifadə edir -- `.filter().map().sorted()` zəncirində əməliyyatlar `toList()` çağırılana qədər icra olunmur. Bu, böyük data topluları üçün effektivdir.

PHP-nin `array_filter()` və `array_map()` isə **eager evaluation** (həvəskar hesablama) istifadə edir -- hər funksiya dərhal yeni massiv yaradır. Üç əməliyyat zəncirləsəniz, üç aralıq massiv yaradılır. Generator-lar (`yield`) bu problemi qismən həll edir.

Laravel-in Collection sinfi Java Stream API-nin yanaşmasını PHP-yə gətirir -- chaining, lazy evaluation (LazyCollection) və zəngin əməliyyat dəsti ilə. Bu, PHP-nin nativ array funksiyalarından daha rahat, oxunaqlı və bəzən daha effektiv koddur.
