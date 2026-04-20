# PHP Type Juggling — Gotchas & Comparison Deep Dive

## Mündəricat
1. [Type juggling nədir?](#type-juggling-nədir)
2. [== vs === (PHP 8 dəyişiklikləri)](#-vs--php-8-dəyişiklikləri)
3. [String → Number çevrilməsi](#string--number-çevrilməsi)
4. [Array comparison](#array-comparison)
5. [Spaceship operator (<=>)](#spaceship-operator-)
6. [Null, false, 0, '' — loose comparison](#null-false-0----loose-comparison)
7. [declare(strict_types=1)](#declarestrict_types1)
8. [Type coercion function parameters](#type-coercion-function-parameters)
9. [Real-world bugs](#real-world-bugs)
10. [Best practices](#best-practices)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Type juggling nədir?

```
PHP dinamik tiplidir — bir dəyişən hər tipdə dəyər saxlaya bilər.
Operator-lar kontekstə görə tipləri avtomatik çevirir.

$x = "5";      // string
$y = $x + 10;  // 15 (int) — string number-ə çevrilib
$z = $x . 10;  // "510" (string) — number string-ə çevrilib

Bu "tip jongliruyushu" (juggling) PHP-nin ənənəsidir.
Faydalı gələ bilər, amma çox subtle bug yaradır.
```

---

## == vs === (PHP 8 dəyişiklikləri)

```php
<?php
// == (loose equality) — tipləri "bərabərləşdirir" sonra müqayisə edir
// === (strict equality) — tip və dəyər EYNİ olmalıdır

var_dump(1 == "1");       // true  (string "1" int-ə çevrilir)
var_dump(1 === "1");      // false (tip fərqli)

var_dump(0 == "abc");     // PHP 7: true!  ←  BUG!!!
                          // PHP 8: false  (düzəldi)
// Səbəb: PHP 7-də "abc" → 0 çevrilirdi ("zero-like" string)
// PHP 8-də string ↔ number müqayisəsi daha ağıllıdır:
//   Əgər string numeric-dirsə → number kimi müqayisə
//   Əgər string numeric DEYİLSƏ → hər ikisi string-ə çevrilir

var_dump(100 == "1e2");   // true  (hər ikisi 100)
var_dump(100 === "1e2");  // false

// PHP 8 düzəltdi amma təhlükəli "gotcha"-lar qalır
var_dump("1" ==  "01");    // true  (ikisi də numeric → int müqayisə)
var_dump("1" === "01");    // false

var_dump("10" == "1e1");   // true (hər ikisi 10)

// Həmişə === istifadə et!
```

---

## String → Number çevrilməsi

```php
<?php
// PHP string-i number-ə çevirəndə:
// 1. Start-dan rəqəm oxuyur
// 2. Rəqəm olmayan ilk char-da dayanır
// 3. "Leading numeric string" qalır

(int) "123abc";      // 123
(int) "abc123";      // 0
(int) "12.34abc";    // 12
(int) "   42   ";    // 42 (whitespace atılır)
(int) "0x1A";        // 0  (hex prefix qəbul olunmur)
(int) "1e3";         // 1000 (scientific notation)

// Float-a
(float) "3.14abc";   // 3.14
(float) "1.2.3";     // 1.2

// PHP 7: "abc" → 0 (silent)
// PHP 8: "abc" + 1 = TypeError (strict)
//        "abc" == 0 → false (düzəldi)

// Numeric string check
is_numeric("123");     // true
is_numeric("1e3");     // true
is_numeric("123abc");  // false
is_numeric("  123  "); // true (leading whitespace OK)
is_numeric("123  ");   // PHP 8+: true, PHP 7: false
```

---

## Array comparison

```php
<?php
// Array müqayisəsi — tricky!
$a = [1, 2, 3];
$b = [1, 2, 3];
$c = [3, 2, 1];
$d = ['a' => 1, 'b' => 2];
$e = ['b' => 2, 'a' => 1];

var_dump($a == $b);   // true  (eyni key-value cütləri)
var_dump($a === $b);  // true  (eyni sıra, eyni tip)

var_dump($a == $c);   // false (value fərqlidir)
var_dump($a === $c);  // false

var_dump($d == $e);   // true  (== sıranı ignore edir)
var_dump($d === $e);  // false (=== sıranı yoxlayır)

// Array subset yoxlaması
$big = ['a' => 1, 'b' => 2, 'c' => 3];
$sub = ['a' => 1];
array_intersect_assoc($sub, $big) == $sub;  // true (subset)
```

---

## Spaceship operator (<=>)

```php
<?php
// PHP 7+: <=> üçlü müqayisə
// a <=> b:
//   -1 əgər a < b
//    0 əgər a == b
//    1 əgər a > b

1 <=> 2;        // -1
2 <=> 2;        // 0
3 <=> 2;        // 1

"a" <=> "b";    // -1
[1,2] <=> [1,3]; // -1

// Sort üçün ideal
usort($items, fn($a, $b) => $a->priority <=> $b->priority);

// Multi-field sort
usort($users, function($a, $b) {
    return [$a->age, $a->name] <=> [$b->age, $b->name];
});

// Reverse sort
usort($items, fn($a, $b) => $b->value <=> $a->value);
```

---

## Null, false, 0, '' — loose comparison

```
PHP 8+ loose comparison matrix:

         | true  | false | 0    | -1   | 1    | "1"  | "0"  | NULL | []   | ""   | "a"
---------|-------|-------|------|------|------|------|------|------|------|------|-----
true     | T     | F     | F    | T    | T    | T    | F    | F    | F    | F    | T
false    | F     | T     | T    | F    | F    | F    | T    | T    | T    | T    | F
0        | F     | T     | T    | F    | F    | F    | T    | T    | F    | T    | F
1        | T     | F     | F    | F    | T    | T    | F    | F    | F    | F    | F
"1"      | T     | F     | F    | F    | T    | T    | F    | F    | F    | F    | F
"0"      | F     | T     | T    | F    | F    | F    | T    | F    | F    | F    | F
"abc"    | T     | F     | F    | F    | F    | F    | F    | F    | F    | F    | F (== "a" → false)
NULL     | F     | T     | T    | F    | F    | F    | F    | T    | T    | T    | F
[]       | F     | T     | F    | F    | F    | F    | F    | T    | T    | F    | F
""       | F     | T     | T    | F    | F    | F    | F    | T    | F    | T    | F

Diqqət:
  - NULL == false == 0 == "" == [] → hamısı true
  - "0" == false → true, AMMA "0" == 0 → true, "0" == "" → false
  - [] == NULL → true
  - [] == false → true
```

```php
<?php
// Bug case: input validation
function setPage($page) {
    if ($page == null) {  // BUG: "0" da null kimi qiymətləndirilir!
        $page = 1;
    }
}

setPage("0");    // page 1 oldu (bug)
setPage(0);      // page 1 oldu
setPage(false);  // page 1 oldu

// Düzgün:
if ($page === null || $page === "") {
    $page = 1;
}
// və ya:
if (!isset($page)) {
    $page = 1;
}

// Daha təhlükəsiz:
$page = (int) ($page ?: 1);  // null coalescing / boolean

// Null coalescing yalnız null üçün işləyir
$x = null;
$y = $x ?? 'default';  // 'default'
$x = 0;
$y = $x ?? 'default';  // 0  (null deyil, dəyər qalır!)

$x = "";
$y = $x ?? 'default';  // ""

// İstisnalar: '0' problem yaradır
$pageSize = $_GET['per_page'] ?? 10;
// ?page=0 → $pageSize = "0" (string "0", null deyil)
// Burada ?: istifadə:
$pageSize = (int) ($_GET['per_page'] ?? 10) ?: 10;
```

---

## declare(strict_types=1)

```php
<?php
// YOXdur strict_types (default — weak mode)
function add(int $a, int $b): int {
    return $a + $b;
}
add("5", "10");  // 15 — "5" int-ə çevrilir (coercion)
add(1.5, 2.5);   // 3 — float int-ə (data loss!)

// Strict typing
declare(strict_types=1);

function add(int $a, int $b): int {
    return $a + $b;
}
add("5", "10");   // TypeError! string int deyil
add(1.5, 2.5);    // TypeError! float int deyil
add(1, 2);        // 3 — OK

// strict_types YALNIZ scalar tip coercion-a təsir edir:
//   int, float, string, bool
// Object, array, class tiplərində heç bir təsir yoxdur (onsuz da strict)

// Hər faylın ƏN BAŞINDA olmalıdır (declare ilk statement)
// <?php
// declare(strict_types=1);
// ...
```

---

## Type coercion function parameters

```php
<?php
// Non-strict mode — PHP nə edir?

function test(int $x) { var_dump($x); }

test(5);        // int(5)
test("5");      // int(5)           — string → int
test(5.7);      // int(5)           — float → int (truncation)
test("5abc");   // int(5) + notice  — partial convert
test(true);     // int(1)           — bool → int
test(null);     // TypeError (PHP 8+, deprecated PHP 7.4)

// String parameter
function stringify(string $s) { var_dump($s); }
stringify(5);       // "5"
stringify(5.5);     // "5.5"
stringify(true);    // "1"
stringify(null);    // TypeError PHP 8+

// Float parameter
function float(float $f) { var_dump($f); }
float(5);       // 5.0
float("5.5");   // 5.5
float(true);    // 1.0

// Bool parameter
function bool(bool $b) { var_dump($b); }
bool(1);        // true
bool(0);        // false
bool("0");      // false  (string "0" falsy)
bool("false");  // true   (non-empty string truthy!)
bool([]);       // false
bool([0]);      // true
```

---

## Real-world bugs

```php
<?php
// BUG 1: Password comparison (PHP 7)
if ($userInput == $dbHash) {  // BUG — string "0e123..." format
    // login allowed
}
// MD5 hash bəzən "0e" ilə başlayır, PHP 7-də numeric string kimi qiymətləndirirdi.
// "0e123" == "0e456" → true (hər ikisi 0 * 10^anything = 0)
// PHP 8-də bu düzəldi, amma:
// HƏMİŞƏ hash_equals() istifadə et
hash_equals($dbHash, $userInput);  // timing-safe + strict

// BUG 2: Form validation
if ($_POST['age']) {  // 0 falsy — bütün 0 yaşındakılar rədd olunur!
    // ...
}
// Düzgün:
if (isset($_POST['age']) && $_POST['age'] !== '') {
    // ...
}

// BUG 3: Array destructuring null
[$a, $b] = null;        // Notice: Trying to access array offset on null
[$a, $b] = [1];         // $b = null + notice
// Həll:
[$a, $b] = [1, 2] + [null, null];  // default-lar

// BUG 4: in_array loose
in_array(0, ['a', 'b', 'c']);       // PHP 7: true! (0 == "a" → true)
                                     // PHP 8: false (düzəldi)
in_array(0, ['a', 'b', 'c'], true);  // false (strict — hər versiyada)
// HƏMİŞƏ strict mode istifadə et (3-cü parametr)

// BUG 5: JSON decode bool
$val = json_decode('false');   // false
if (!$val) {
    // "invalid JSON" yoxlaması BUG — "false" valid JSON-dur
}
// Düzgün:
if (json_last_error() !== JSON_ERROR_NONE) {
    // parse error
}

// BUG 6: array_search
array_search('0', ['apple', 'banana']);  // PHP 7: 0 (apple!) — BUG
array_search('0', ['apple', 'banana'], true);  // false

// BUG 7: switch loose comparison
switch ($status) {  // switch == istifadə edir!
    case 0:
        // "abc" buna düşər PHP 7-də!
        break;
}
// match (PHP 8+) strict:
match ($status) {
    0 => 'zero',
    'abc' => 'text',
};
```

---

## Best practices

```
✓ HƏMİŞƏ === istifadə et (== yalnız exception case-lərdə)
✓ declare(strict_types=1) hər faylın başında
✓ in_array, array_search-də strict=true üçüncü parametr
✓ match statement switch yerinə (PHP 8+)
✓ hash_equals() şifrə/token müqayisəsi üçün
✓ !== null açıq yoxlama (null coalescing hər şey üçün deyil)
✓ isset() və empty() fərqini bil:
    isset:  null olmamaq + mövcud olmaq
    empty:  null OR false OR 0 OR "" OR [] → true
✓ PHPStan / Psalm level max — static analyzer bug-ları tapır
✓ Unit test edge case-ləri yoxla: null, false, 0, "", []
```

```php
<?php
// isset vs empty
$a = null;    isset($a); // false, empty($a); // true
$a = 0;       isset($a); // true,  empty($a); // true   ← dikkət!
$a = "0";     isset($a); // true,  empty($a); // true   ← dikkət!
$a = "";      isset($a); // true,  empty($a); // true
$a = [];      isset($a); // true,  empty($a); // true
$a = "false"; isset($a); // true,  empty($a); // false  ← truthy string!

// Form input:
// $_POST['remember'] = "0"  (checkbox unchecked, hidden field)
if (empty($_POST['remember'])) {
    // "0" bura düşür — bu istənmir!
}
if (!isset($_POST['remember']) || $_POST['remember'] === '') {
    // düzgün
}
```

---

## İntervyu Sualları

- `==` və `===` arasındakı əsas fərq nədir?
- `0 == "abc"` PHP 7-də və PHP 8-də nə qaytarır və niyə?
- `"0" == false` niyə `true`-dur?
- Null coalescing (`??`) nə vaxt sıfır qaytarır?
- `declare(strict_types=1)` nəyə təsir edir? Obyekt parametrlərinə təsir edirmi?
- `in_array` niyə default olaraq təhlükəlidir?
- `hash_equals` adi `===`-dan niyə fərqlidir (təhlükəsizlik)?
- `switch` və `match` arasındakı fərq (comparison baxımından)?
- `isset` və `empty` arasındakı fərqlər — 5 case ilə.
- Array `==` və `===` sıra baxımından necə fərqlənir?
- "0e123" formatlı hash-lar niyə problem idi PHP 7-də?
- Spaceship operator nə üçündür? `usort` ilə nümunə göstərin.
