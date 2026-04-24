# 008 — Massivlər (1D, 2D, Arrays utility)
**Səviyyə:** Başlanğıc


## Mündəricat
1. [Massiv nədir?](#massiv-nedir)
2. [Massivin elan olunması](#elan)
3. [Default dəyərlər](#default)
4. [length xüsusiyyəti — metod DEYİL](#length)
5. [Üzərində iterate](#iterate)
6. [ArrayIndexOutOfBoundsException](#out-of-bounds)
7. [2D massivlər (matris)](#matrix)
8. [Jagged (düzənsiz) massivlər](#jagged)
9. [Arrays utility class](#arrays-utility)
10. [System.arraycopy](#arraycopy)
11. [Massiv vs ArrayList](#vs-arraylist)
12. [İntervyu məsələləri](#interview-problems)
13. [Ümumi Səhvlər](#umumi-sehvler)
14. [İntervyu Sualları](#intervyu)

---

## 1. Massiv nədir? {#massiv-nedir}

**Massiv (array)** — eyni tipli elementlərin sıralı toplusudur. Yaddaşda **ardıcıl** yerləşir, ölçüsü sabit (yaradıldıqdan sonra dəyişmir).

Real dünya analogiyası: Hər qutuda bir dəyər olan **şkaf**. Hər qutunun nömrəsi var (index), ilkisi 0-dan başlayır.

```
index:   0    1    2    3    4
       ┌────┬────┬────┬────┬────┐
       │ 10 │ 20 │ 30 │ 40 │ 50 │
       └────┴────┴────┴────┴────┘
                 bir int[]
```

### Xüsusiyyətləri

- Ölçüsü **sabit** (yaradıldıqdan sonra dəyişmir).
- İndekslər **0**-dan başlayır, sonuncu `length - 1`.
- Bütün elementlər **eyni tipdə** olmalıdır.
- Reference tipdir — obyekt kimi davranır.
- Yaddaşda ardıcıl yerləşir — sürətli access.

---

## 2. Massivin elan olunması {#elan}

### Üsul 1: Elan + ölçü

```java
int[] nums = new int[5];       // 5 elementli int massivi
String[] adlar = new String[10]; // 10 elementli String massivi
double[] qiymətlər = new double[3];
```

Bu halda bütün elementlər **default dəyər**lə başlayır.

### Üsul 2: Elan + init {dəyərlərlə}

```java
int[] nums = {10, 20, 30, 40, 50};
String[] adlar = {"Anar", "Leyla", "Vüsal"};
double[] qiymətlər = {1.5, 2.7, 3.14};

// Eyni, uzun formada:
int[] nums = new int[]{10, 20, 30, 40, 50};
```

Diqqət — **yalnız elan ilə birlikdə** qısa forma işləyir:

```java
int[] nums;
nums = {10, 20, 30};              // XƏTA
nums = new int[]{10, 20, 30};     // DOĞRU
```

### Üsul 3: Əvvəl elan, sonra instantiate

```java
int[] nums;
nums = new int[5];
nums[0] = 10;
nums[1] = 20;
```

### Elan sintaksisi variantları

```java
int[] a;           // üstünlük verilən Java stili
int a[];           // C stili — qanunidir, amma təcrübədə yoxdur
int[] a, b;        // a və b hər ikisi int[]
int a[], b;        // a int[], b yalnız int — qarışıq!
```

İlkin forma (`int[]`) daha aydındır, hamısını istifadə et.

### İndekslə dəyər vermək və oxumaq

```java
int[] nums = new int[3];
nums[0] = 10;     // write
nums[1] = 20;
nums[2] = 30;

int x = nums[1];  // read — 20
System.out.println(nums[0] + " " + nums[1] + " " + nums[2]);
```

---

## 3. Default dəyərlər {#default}

`new Tip[n]` ilə yaradıldıqda:

| Tip | Default |
|---|---|
| `byte`, `short`, `int`, `long` | 0 |
| `float`, `double` | 0.0 |
| `char` | `' '` (null char) |
| `boolean` | `false` |
| Reference (String, obyektlər) | `null` |

```java
int[] nums = new int[3];
System.out.println(nums[0]);    // 0
System.out.println(nums[1]);    // 0
System.out.println(nums[2]);    // 0

String[] adlar = new String[3];
System.out.println(adlar[0]);   // null
```

---

## 4. length xüsusiyyəti — metod DEYİL {#length}

Massivin uzunluğu `.length` **xüsusiyyəti** (field) ilə alınır. **Mötərizə yoxdur!**

```java
int[] nums = {10, 20, 30, 40, 50};

System.out.println(nums.length);    // 5 — DOĞRU
System.out.println(nums.length());  // XƏTA — metod deyil
```

Bu, `String` ilə müqayisədə **səhv salınan** nöqtədir:

```java
String s = "salam";
s.length();          // metod — mötərizə VAR

int[] a = {1, 2, 3};
a.length;            // xüsusiyyət — mötərizə YOX
```

### Boş massiv

```java
int[] empty = new int[0];
System.out.println(empty.length);  // 0

int[] nullArr = null;
System.out.println(nullArr.length); // NullPointerException!
```

---

## 5. Üzərində iterate {#iterate}

### Klassik for

İndeks lazımdırsa:

```java
int[] nums = {10, 20, 30, 40, 50};

for (int i = 0; i < nums.length; i++) {
    System.out.println(i + ": " + nums[i]);
}
// 0: 10
// 1: 20
// ...
```

### for-each (enhanced for)

Yalnız dəyər lazımdırsa — **daha təmiz**:

```java
for (int n : nums) {
    System.out.println(n);
}
```

### Tərsinə iterate

```java
for (int i = nums.length - 1; i >= 0; i--) {
    System.out.println(nums[i]);
}
```

### Stream ilə (Java 8+)

```java
Arrays.stream(nums).forEach(System.out::println);
```

---

## 6. ArrayIndexOutOfBoundsException {#out-of-bounds}

Etibarsız indeksdə runtime xətası atılır.

```java
int[] nums = {10, 20, 30};

System.out.println(nums[5]);   // ArrayIndexOutOfBoundsException
System.out.println(nums[-1]);  // ArrayIndexOutOfBoundsException
System.out.println(nums[3]);   // ArrayIndexOutOfBoundsException
                               // (indekslər 0..2)
```

### Müdafiə proqramlaşdırma

```java
public static int güvənliOxu(int[] arr, int index) {
    if (arr == null || index < 0 || index >= arr.length) {
        return -1;
    }
    return arr[index];
}
```

Və ya Java 9+-da:

```java
int value = Objects.checkIndex(index, arr.length);
```

---

## 7. 2D massivlər (matris) {#matrix}

2D massiv — **massivlərin massividir**.

```java
int[][] matris = new int[3][4];   // 3 sətir, 4 sütun

// Dəyər ver
matris[0][0] = 1;
matris[1][2] = 5;

// Oxu
int x = matris[1][2];   // 5
```

### Birlikdə init

```java
int[][] matris = {
    {1, 2, 3, 4},
    {5, 6, 7, 8},
    {9, 10, 11, 12}
};
```

### Vizual

```
           sütun: 0   1   2   3
sətir 0:         ┌───┬───┬───┬───┐
                 │ 1 │ 2 │ 3 │ 4 │
sətir 1:         ├───┼───┼───┼───┤
                 │ 5 │ 6 │ 7 │ 8 │
sətir 2:         ├───┼───┼───┼───┤
                 │ 9 │10 │11 │12 │
                 └───┴───┴───┴───┘
```

### İterate

```java
for (int i = 0; i < matris.length; i++) {
    for (int j = 0; j < matris[i].length; j++) {
        System.out.print(matris[i][j] + " ");
    }
    System.out.println();
}
```

### for-each 2D

```java
for (int[] sətir : matris) {
    for (int dəyər : sətir) {
        System.out.print(dəyər + " ");
    }
    System.out.println();
}
```

### `length` 2D-də

```java
int[][] m = new int[3][4];

System.out.println(m.length);        // 3 — sətir sayı
System.out.println(m[0].length);     // 4 — birinci sətirdəki sütun sayı
```

---

## 8. Jagged (düzənsiz) massivlər {#jagged}

Hər sətirin **fərqli uzunluğu** ola bilər.

```java
int[][] jagged = new int[3][];
jagged[0] = new int[]{1, 2};
jagged[1] = new int[]{3, 4, 5, 6};
jagged[2] = new int[]{7};

// Və ya birbaşa
int[][] jagged = {
    {1, 2},
    {3, 4, 5, 6},
    {7}
};
```

### Vizual

```
jagged[0]: [1, 2]
jagged[1]: [3, 4, 5, 6]
jagged[2]: [7]
```

### İterate

```java
for (int i = 0; i < jagged.length; i++) {
    for (int j = 0; j < jagged[i].length; j++) {
        System.out.print(jagged[i][j] + " ");
    }
    System.out.println();
}
```

---

## 9. Arrays utility class {#arrays-utility}

`java.util.Arrays` — massivlərlə işləmək üçün statik metodlar.

### `Arrays.toString` — massivi çap et

```java
int[] nums = {10, 20, 30, 40, 50};

System.out.println(nums);               // [I@1a2b3c — faydasız
System.out.println(Arrays.toString(nums)); // [10, 20, 30, 40, 50]
```

### `Arrays.sort` — sırala

```java
int[] nums = {5, 2, 8, 1, 9, 3};
Arrays.sort(nums);
System.out.println(Arrays.toString(nums)); // [1, 2, 3, 5, 8, 9]

// String sıralama
String[] adlar = {"Vüsal", "Anar", "Leyla"};
Arrays.sort(adlar);
System.out.println(Arrays.toString(adlar)); // [Anar, Leyla, Vüsal]

// Hissəvi sıralama
int[] arr = {5, 3, 1, 4, 2};
Arrays.sort(arr, 1, 4);  // index 1-dən 3-ə qədər
System.out.println(Arrays.toString(arr)); // [5, 1, 3, 4, 2]
```

### `Arrays.binarySearch` — sıralı massivdə axtar

```java
int[] nums = {1, 3, 5, 7, 9, 11};  // SIRALI olmalıdır!
int index = Arrays.binarySearch(nums, 7);
System.out.println(index);  // 3

// Tapılmadıqda mənfi qaytarır
int notFound = Arrays.binarySearch(nums, 4);
System.out.println(notFound);  // -3 (where it would go, negated)
```

### `Arrays.fill` — dəyər doldur

```java
int[] nums = new int[5];
Arrays.fill(nums, 7);
System.out.println(Arrays.toString(nums)); // [7, 7, 7, 7, 7]

// Hissəvi doldurma
int[] a = new int[10];
Arrays.fill(a, 2, 5, 99);  // index 2-dən 4-ə qədər 99
```

### `Arrays.copyOf` — kopya yarat

```java
int[] original = {1, 2, 3, 4, 5};
int[] kopya = Arrays.copyOf(original, 5);
// [1, 2, 3, 4, 5]

int[] genişl = Arrays.copyOf(original, 8);
// [1, 2, 3, 4, 5, 0, 0, 0] — sondakılar default

int[] kəsilmiş = Arrays.copyOf(original, 3);
// [1, 2, 3]
```

### `Arrays.copyOfRange` — hissəvi kopya

```java
int[] arr = {1, 2, 3, 4, 5};
int[] hissə = Arrays.copyOfRange(arr, 1, 4);
// [2, 3, 4] — from (inclusive), to (exclusive)
```

### `Arrays.equals` — bərabərlik

```java
int[] a = {1, 2, 3};
int[] b = {1, 2, 3};

System.out.println(a == b);                  // false (ünvan fərqli)
System.out.println(Arrays.equals(a, b));     // true (dəyər eyni)

// 2D üçün deepEquals
int[][] m1 = {{1, 2}, {3, 4}};
int[][] m2 = {{1, 2}, {3, 4}};
System.out.println(Arrays.deepEquals(m1, m2)); // true
```

### `Arrays.stream` — Stream-ə çevir

```java
int[] nums = {1, 2, 3, 4, 5};

int cəm = Arrays.stream(nums).sum();         // 15
int maks = Arrays.stream(nums).max().getAsInt(); // 5
double orta = Arrays.stream(nums).average().getAsDouble(); // 3.0
```

### `Arrays.asList` — List-ə çevir

```java
String[] arr = {"a", "b", "c"};
List<String> list = Arrays.asList(arr);
// sabit ölçülü List (elementləri dəyişmək olar, amma add/remove etmək olmaz)
```

---

## 10. System.arraycopy {#arraycopy}

Native səviyyədə massiv kopyalama — `Arrays.copyOf`-dan daha incə kontrol verir.

```java
System.arraycopy(
    mənbəMassiv, mənbəBaşlanğıc,
    hədəfMassiv, hədəfBaşlanğıc,
    uzunluq
);
```

### Nümunə

```java
int[] src = {1, 2, 3, 4, 5};
int[] dst = new int[10];

System.arraycopy(src, 0, dst, 2, 5);
// dst: [0, 0, 1, 2, 3, 4, 5, 0, 0, 0]
//            ↑ (index 2-dən başlayaraq 5 element)
```

### Öz-özünə kopya

```java
int[] arr = {1, 2, 3, 4, 5};
System.arraycopy(arr, 0, arr, 2, 3);
// arr: [1, 2, 1, 2, 3] — üst-üstə düşməni düzgün idarə edir
```

---

## 11. Massiv vs ArrayList {#vs-arraylist}

| Xüsusiyyət | Array (`int[]`) | `ArrayList<Integer>` |
|---|---|---|
| Ölçü | Sabit | Dinamik |
| Tip | Primitiv ola bilər | Yalnız obyekt |
| Performans | Daha sürətli | Bir az yavaş (wrapper) |
| Metodlar | Minimal | Zəngin (`add`, `remove`, `contains`) |
| Generic | Yox | Bəli (`List<String>`) |
| Syntax | `arr[0]` | `list.get(0)` |
| length | `arr.length` | `list.size()` |

### Nə zaman hansı?

- **Sabit ölçü, performans kritik** → Array.
- **Dinamik ölçü, əlavə metodlar** → ArrayList.

---

## 12. İntervyu məsələləri {#interview-problems}

### 12.1 Massivi reverse et

```java
public static void reverse(int[] arr) {
    int sol = 0;
    int sağ = arr.length - 1;
    while (sol < sağ) {
        int temp = arr[sol];
        arr[sol] = arr[sağ];
        arr[sağ] = temp;
        sol++;
        sağ--;
    }
}

int[] nums = {1, 2, 3, 4, 5};
reverse(nums);
System.out.println(Arrays.toString(nums));  // [5, 4, 3, 2, 1]
```

### 12.2 Maksimum və minimum tap

```java
public static int[] maksMin(int[] arr) {
    if (arr.length == 0) throw new IllegalArgumentException("Boş massiv");

    int maks = arr[0];
    int min = arr[0];

    for (int n : arr) {
        if (n > maks) maks = n;
        if (n < min) min = n;
    }

    return new int[]{maks, min};
}

int[] nums = {5, 2, 8, 1, 9, 3};
int[] result = maksMin(nums);
System.out.println("Maks: " + result[0] + ", Min: " + result[1]);
// Maks: 9, Min: 1
```

### 12.3 Təkrarlananları sil (sadə üsul)

```java
public static int[] təkrarsız(int[] arr) {
    Set<Integer> görülən = new LinkedHashSet<>();
    for (int n : arr) {
        görülən.add(n);
    }

    int[] result = new int[görülən.size()];
    int i = 0;
    for (int n : görülən) {
        result[i++] = n;
    }
    return result;
}

int[] nums = {1, 2, 2, 3, 1, 4, 3};
System.out.println(Arrays.toString(təkrarsız(nums)));
// [1, 2, 3, 4]
```

### 12.4 İki massivi birləşdir

```java
public static int[] birlesdir(int[] a, int[] b) {
    int[] result = new int[a.length + b.length];
    System.arraycopy(a, 0, result, 0, a.length);
    System.arraycopy(b, 0, result, a.length, b.length);
    return result;
}
```

### 12.5 İkinci ən böyük rəqəm

```java
public static int ikinciMaks(int[] arr) {
    if (arr.length < 2) throw new IllegalArgumentException("Ən az 2 element");

    int maks = Integer.MIN_VALUE;
    int ikinci = Integer.MIN_VALUE;

    for (int n : arr) {
        if (n > maks) {
            ikinci = maks;
            maks = n;
        } else if (n > ikinci && n != maks) {
            ikinci = n;
        }
    }
    return ikinci;
}
```

### 12.6 Rotate (sürüşdür)

```java
public static void rotate(int[] arr, int k) {
    k = k % arr.length;
    reverse(arr, 0, arr.length - 1);
    reverse(arr, 0, k - 1);
    reverse(arr, k, arr.length - 1);
}

private static void reverse(int[] arr, int sol, int sağ) {
    while (sol < sağ) {
        int t = arr[sol];
        arr[sol++] = arr[sağ];
        arr[sağ--] = t;
    }
}
```

---

## 13. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: `length` metod kimi

```java
int[] arr = {1, 2, 3};
int n = arr.length();   // XƏTA — metod deyil
int n = arr.length;     // DOĞRU
```

### Səhv 2: `length` və `length()` qarışıqlığı

```java
String s = "abc";
int[] a = {1, 2, 3};

s.length();    // String → metod (mötərizə var)
a.length;      // Array → field (mötərizə yoxdur)
```

### Səhv 3: İndeks `>= length`

```java
int[] arr = new int[5];
arr[5] = 10;   // ArrayIndexOutOfBoundsException (indekslər 0..4)
```

### Səhv 4: Elan ilə init-i ayırmaq

```java
int[] nums;
nums = {1, 2, 3};          // XƏTA
nums = new int[]{1, 2, 3}; // DOĞRU
```

### Səhv 5: `==` ilə müqayisə

```java
int[] a = {1, 2, 3};
int[] b = {1, 2, 3};

System.out.println(a == b);           // false — ünvanlar fərqli
System.out.println(Arrays.equals(a, b)); // true
```

### Səhv 6: `System.out.println(arr)` nəticəsiz

```java
int[] arr = {1, 2, 3};
System.out.println(arr);              // [I@1a2b3c — faydasız
System.out.println(Arrays.toString(arr)); // [1, 2, 3]
```

### Səhv 7: 2D massiv printinq

```java
int[][] m = {{1, 2}, {3, 4}};
System.out.println(Arrays.toString(m));     // [[I@..., [I@...] — səhv
System.out.println(Arrays.deepToString(m)); // [[1, 2], [3, 4]] — DOĞRU
```

### Səhv 8: Default null-ı unutmaq

```java
String[] adlar = new String[3];
System.out.println(adlar[0].length());  // NullPointerException
```

### Səhv 9: Jagged massivdə row-un ölçüsü

```java
int[][] m = new int[3][];
m[0] = new int[]{1, 2};
// m[1] və m[2] hələ null-dur
m[1][0] = 5;  // NullPointerException!
```

### Səhv 10: Ölçüsü sabitdir

```java
int[] arr = new int[3];
// arr.length-i dəyişə bilmərik — yeni massiv yaratmaq lazım
arr = Arrays.copyOf(arr, 10);  // yeni 10 elementli massiv
```

---

## 14. İntervyu Sualları {#intervyu}

**S1: Massiv yaradıldıqdan sonra ölçüsü dəyişə bilərmi?**
> Xeyr. Java massivləri **sabit ölçülü**dür. Yeni ölçü lazımdırsa, yeni massiv yaratmaq və elementləri kopyalamaq lazımdır (`Arrays.copyOf` və ya `System.arraycopy`). Dinamik ölçü üçün `ArrayList` istifadə et.

**S2: `length` niyə metod deyil?**
> Çünki massiv **xüsusi JVM obyektidir** — standart `Object`-dən bir qədər fərqli. `length` massivin daxili sahəsidir, əlavə metod çağırışına ehtiyac yoxdur. `String.length()` isə adi metoddur. Yeni başlayanlar tez-tez qarışdırır.

**S3: `int[]` və `Integer[]` arasında fərq nədir?**
> `int[]` — primitiv `int` massividir, daha yaddaş səmərəlidir, null ola bilməz. `Integer[]` — `Integer` (wrapper class) obyektlərinin massividir, hər element ayrıca obyektdir, null ola bilər, 4 dəfə daha çox yaddaş tutur. Generic-lər yalnız `Integer[]` ilə işləyir.

**S4: Jagged massivlər nədir və nə üçün istifadə olunur?**
> Hər sətiri fərqli uzunluqda olan 2D massivlər. Yaddaş qənaət üçün istifadə olunur — məsələn üçbucaq matris, Pascal üçbucağı, fərqli uzunluqlu sətirlər. Adi 2D-də hər sətir eyni ölçüdədir — bəzən bu israfçılıqdır.

**S5: `Arrays.equals` ilə `==` arasında fərq nədir?**
> `==` iki massivi müqayisə edəndə **ünvanları** (reference) müqayisə edir — eyni obyektə işarə edirlərmi? `Arrays.equals` isə elementləri bir-bir müqayisə edir. 2D massivlər üçün `Arrays.deepEquals` istifadə olunur (iç-içə dizilər).

**S6: `Arrays.asList(...)` ilə ArrayList fərqi nədir?**
> `Arrays.asList` **sabit ölçülü** List qaytarır — `add`/`remove` edə bilməzsən (UnsupportedOperationException). Bu fixed-size list-dir, yalnız elementi dəyişə bilərsən. İnteqrasiya edilə bilər: `new ArrayList<>(Arrays.asList(arr))` — amma primitiv massivlərdə autoboxing olmur (`int[]` → `List<int[]>`-dir, `List<Integer>` deyil!).

**S7: Binary search necə işləyir və nə tələb edir?**
> Logaritmik axtarış — hər addımda axtarış sahəsini yarıya bölür. O(log n) mürəkkəbliyi. **Massiv sıralı olmalıdır**. Sıralı deyilsə nəticə gözlənilməzdir. `Arrays.binarySearch` tapılmayanda `-(insertion_point + 1)` qaytarır — düzgün yerləşmə yerini mənfiyə çevirib bildirir.

**S8: Yeni başlayanların `length` ilə bağlı ən çox etdiyi səhv nədir?**
> 4 ümumi səhv: (1) massivdə `.length()` yazmaq (metod yox, field-dir); (2) String-də `.length` (mötərizəsiz) — kompilyasiya xətası; (3) `arr.length - 1` əvəzinə `arr.length` istifadə edərək ArrayIndexOutOfBounds; (4) null massivdə `.length` əldə etmək — NPE.

**S9: `System.arraycopy` vs `Arrays.copyOf` — fərq nədir?**
> `Arrays.copyOf(src, newLength)` — yeni massiv yaradıb ona kopyalayır, daha rahat. `System.arraycopy(src, sStart, dst, dStart, len)` — mövcud hədəf massivin müəyyən hissəsinə kopyalayır, daha **aşağı səviyyə** və **daha sürətli** (native metod). Performance kritik kodlarda `arraycopy` seçilir.

**S10: Massivlərin nə çatışmazlığı var?**
> (1) Sabit ölçü — runtime-da genişlənmir. (2) Zəngin metodlar yoxdur — `contains`, `indexOf`, `remove` yox. (3) Generics ilə yaxşı işləmir (`T[] arr = new T[n]` qadağandır). (4) Type safety `Object[]`-də zəifdir — ArrayStoreException ola bilər. Ona görə dövrü kollekiyalar (`List`, `Set`, `Map`) daha geniş istifadə olunur.
