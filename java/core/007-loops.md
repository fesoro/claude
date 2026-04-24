# 007 — Dövrlər (for / while / do-while / for-each)
**Səviyyə:** Başlanğıc


## Mündəricat
1. [Dövr nədir?](#dovr-nedir)
2. [for dövrü](#for)
3. [while dövrü](#while)
4. [do-while dövrü](#do-while)
5. [Enhanced for (for-each)](#for-each)
6. [break və continue](#break-continue)
7. [Labeled break/continue](#labeled)
8. [Sonsuz dövrlər](#sonsuz)
9. [Iterator — qısa giriş](#iterator)
10. [Hansı dövrü seçmək?](#hansi)
11. [Praktik nümunələr](#numuneler)
12. [Ümumi Səhvlər](#umumi-sehvler)
13. [İntervyu Sualları](#intervyu)

---

## 1. Dövr nədir? {#dovr-nedir}

**Dövr (loop)** — eyni kod blokunu müəyyən sayda və ya müəyyən şərt ödənildiyi müddətcə təkrarlayan strukturdur.

Real dünyada analogiya: "Kitabın bütün səhifələrini oxu" — hər səhifə üçün eyni əməliyyat (oxumaq). 100 səhifə varsa, 100 dəfə təkrarlayırsan.

Java-da 4 növ dövr var:
1. `for` — məlum sayda təkrar
2. `while` — şərt ödəndiyi müddətcə
3. `do-while` — heç olmasa bir dəfə işləsin
4. `for-each` — kolleksiya elementləri üzərində

---

## 2. for dövrü {#for}

Ən çox istifadə olunan dövr — **məlum sayda** təkrar üçün idealdır.

```java
for (initialization; şərt; update) {
    // təkrarlanan kod
}
```

3 hissədən ibarətdir:
1. **Initialization** — bir dəfə, başlanğıcda işləyir
2. **Şərt** — hər iterasiyadan əvvəl yoxlanır; true olarsa gövdə işləyir
3. **Update** — hər iterasiyadan sonra işləyir

### Nümunə — 1-dən 5-ə sayma

```java
for (int i = 1; i <= 5; i++) {
    System.out.println(i);
}

// Çıxış:
// 1
// 2
// 3
// 4
// 5
```

### Addım-addım izah

```
i = 1          // initialization
1 <= 5 ? true  // şərt, print 1
i++            // i = 2
2 <= 5 ? true  // print 2
i++            // i = 3
...
5 <= 5 ? true  // print 5
i++            // i = 6
6 <= 5 ? false // dayan
```

### Tərsinə sayma

```java
for (int i = 10; i >= 1; i--) {
    System.out.println(i);
}
// 10, 9, 8, ..., 1
```

### Addım 2 ilə

```java
for (int i = 0; i <= 20; i += 2) {
    System.out.print(i + " ");
}
// 0 2 4 6 8 10 12 14 16 18 20
```

### Hissələrdən bəziləri boş ola bilər

```java
// initialization kənarda
int i = 0;
for (; i < 5; i++) {
    System.out.println(i);
}

// sonsuz dövr
for (;;) {
    // break olmasa, əbədidir
}

// bir neçə dəyişən
for (int i = 0, j = 10; i < j; i++, j--) {
    System.out.println(i + " " + j);
}
```

---

## 3. while dövrü {#while}

**Şərt true olduğu müddətcə** işləyir. Nə qədər təkrar olacağı **öncədən məlum olmadıqda** istifadə olunur.

```java
while (şərt) {
    // kod
}
```

### Nümunə

```java
int i = 1;
while (i <= 5) {
    System.out.println(i);
    i++;
}
// 1 2 3 4 5
```

### Tipik istifadə — istifadəçi girişi

```java
Scanner scanner = new Scanner(System.in);
int xal = 0;

while (xal < 50) {
    System.out.print("Xalınızı daxil edin: ");
    xal = scanner.nextInt();
}

System.out.println("Keçdi!");
```

### Söz sayma

```java
String mətn = "salam necəsən yaxşı";
int sayi = 0;
int i = 0;

while (i < mətn.length()) {
    if (mətn.charAt(i) == ' ') {
        sayi++;
    }
    i++;
}

System.out.println("Boşluq sayı: " + sayi + ", söz sayı: " + (sayi + 1));
```

### for vs while

```java
// Eyni iş iki formada:

for (int i = 0; i < 5; i++) {
    System.out.println(i);
}

int i = 0;
while (i < 5) {
    System.out.println(i);
    i++;
}
```

---

## 4. do-while dövrü {#do-while}

Şərti **sonda** yoxlayır — yəni gövdə **ən az bir dəfə** işləyir.

```java
do {
    // kod
} while (şərt);      // ; VACİB
```

### Nümunə

```java
int i = 10;
do {
    System.out.println("i = " + i);
    i++;
} while (i < 5);

// Çıxış: i = 10
// Niyə? Çünki əvvəl kod işləyir, sonra şərt yoxlanır — 10 < 5 false, bitir.
```

### Real istifadə — menu

```java
Scanner scanner = new Scanner(System.in);
int seçim;

do {
    System.out.println("1. Əlavə et");
    System.out.println("2. Göstər");
    System.out.println("3. Çıx");
    System.out.print("Seçim: ");
    seçim = scanner.nextInt();

    switch (seçim) {
        case 1 -> System.out.println("Əlavə edilir...");
        case 2 -> System.out.println("Göstərilir...");
        case 3 -> System.out.println("Çıxış");
        default -> System.out.println("Yanlış seçim");
    }
} while (seçim != 3);
```

### Niyə do-while?

Menu kimi hallarda istifadəçiyə **ən az bir dəfə** seçim təklif etmək lazımdır — `while`-da isə şərt öncə yoxlanır.

---

## 5. Enhanced for (for-each) {#for-each}

Java 5-dən etibarən, massivlər və `Iterable` üzərində asan təkrarlama üçün.

```java
for (Tip dəyişən : kolleksiya) {
    // dəyişəni istifadə et
}
```

### Nümunə — massiv

```java
int[] nums = {10, 20, 30, 40, 50};

for (int n : nums) {
    System.out.println(n);
}
// 10 20 30 40 50
```

### Nümunə — List

```java
List<String> adlar = List.of("Anar", "Leyla", "Vüsal");

for (String ad : adlar) {
    System.out.println(ad);
}
```

### Nümunə — Map

```java
Map<String, Integer> yaşlar = Map.of("Anar", 25, "Leyla", 30);

for (Map.Entry<String, Integer> entry : yaşlar.entrySet()) {
    System.out.println(entry.getKey() + ": " + entry.getValue());
}
```

### Klassik for-a ehtiyac nə zaman var?

For-each **sadələşdirir**, amma bəzi şeyləri edə bilmir:

| Tələb | for-each | klassik for |
|---|---|---|
| Sadəcə oxu | ✓ | ✓ |
| İndeksə görə işləmək | ✗ | ✓ |
| Tərsinə getmək | ✗ | ✓ |
| Addım 2, 3 və s. | ✗ | ✓ |
| Kolleksiyadan silmək | ✗ | ✓ (Iterator ilə) |

```java
// for-each — indeks yoxdur
String[] adlar = {"A", "B", "C"};
for (String ad : adlar) {
    System.out.println(ad);
}

// Klassik for — indeks var
for (int i = 0; i < adlar.length; i++) {
    System.out.println(i + ": " + adlar[i]);
}
```

---

## 6. break və continue {#break-continue}

### break — dövrü DAYAN

```java
for (int i = 1; i <= 10; i++) {
    if (i == 5) {
        break;  // dövr dayanır, for çıxır
    }
    System.out.println(i);
}
// 1 2 3 4
```

### continue — növbəti iterasiyaya KEÇ

```java
for (int i = 1; i <= 10; i++) {
    if (i % 2 == 0) {
        continue;  // cüt rəqəmləri ötür
    }
    System.out.println(i);
}
// 1 3 5 7 9
```

### Real istifadə — axtarış

```java
int[] nums = {10, 20, 30, 40, 50};
int aranan = 30;
int index = -1;

for (int i = 0; i < nums.length; i++) {
    if (nums[i] == aranan) {
        index = i;
        break;  // tapdıq, axtarışa ehtiyac yoxdur
    }
}

System.out.println("Tapıldı: " + index);  // 2
```

### break və continue iç-içə dövrlərdə

```java
for (int i = 0; i < 3; i++) {
    for (int j = 0; j < 3; j++) {
        if (j == 2) break;   // yalnız DAXILI dövrü dayandırır
        System.out.println(i + "," + j);
    }
}
// 0,0 | 0,1 | 1,0 | 1,1 | 2,0 | 2,1
```

---

## 7. Labeled break/continue {#labeled}

İç-içə dövrlərdə xarici dövrü idarə etmək üçün.

### Etiket sintaksisi

```java
etiketAdı:
for (...) {
    for (...) {
        break etiketAdı;     // xarici dövrü dayandır
        continue etiketAdı;  // xarici dövrün növbəti iterasiyasına keç
    }
}
```

### Nümunə

```java
outer:
for (int i = 0; i < 5; i++) {
    for (int j = 0; j < 5; j++) {
        if (i * j > 6) {
            System.out.println("Dayanır: i=" + i + ", j=" + j);
            break outer;  // HƏR İKİ dövrdən çıx
        }
    }
}
```

### Matrix-də axtarış

```java
int[][] matrix = {
    {1, 2, 3},
    {4, 5, 6},
    {7, 8, 9}
};
int aranan = 5;
boolean tapildi = false;

search:
for (int i = 0; i < matrix.length; i++) {
    for (int j = 0; j < matrix[i].length; j++) {
        if (matrix[i][j] == aranan) {
            System.out.println("Tapıldı: [" + i + "][" + j + "]");
            tapildi = true;
            break search;
        }
    }
}
```

Qeyd: Labeled break tövsiyə edilən yanaşma deyil — çox dəfələrlə istifadə olunduqda kodun oxunaqlığı düşür. Əvəzinə metod çıxarıb `return` istifadə et.

---

## 8. Sonsuz dövrlər {#sonsuz}

Bəzən məqsədli olaraq sonsuz dövr yaradırıq.

### for(;;)

```java
for (;;) {
    // sonsuz
    if (şərt) break;
}
```

### while(true)

```java
while (true) {
    // sonsuz
    if (şərt) break;
}
```

### Server dövrü nümunəsi

```java
while (true) {
    Connection c = acceptClient();
    handleClient(c);
}
```

### Təsadüfi sonsuz dövr (səhv)

```java
int i = 0;
while (i < 10) {
    System.out.println(i);
    // i++; — UNUDULDU
}
// 0 0 0 0 ... əbədidir
```

---

## 9. Iterator — qısa giriş {#iterator}

Kolleksiyalar üçün standart təkrarlama interfeysidir.

```java
List<String> adlar = new ArrayList<>(List.of("Anar", "Leyla", "Vüsal"));

Iterator<String> it = adlar.iterator();
while (it.hasNext()) {
    String ad = it.next();
    System.out.println(ad);
}
```

### Iterator-ın üstünlüyü — silmək

For-each ilə dövrdə element silmək olmaz (ConcurrentModificationException atır):

```java
// XƏTA
for (String ad : adlar) {
    if (ad.startsWith("A")) {
        adlar.remove(ad);  // ConcurrentModificationException
    }
}

// DOĞRU — Iterator ilə
Iterator<String> it = adlar.iterator();
while (it.hasNext()) {
    String ad = it.next();
    if (ad.startsWith("A")) {
        it.remove();  // təhlükəsiz
    }
}

// Və ya daha qısa (Java 8+)
adlar.removeIf(ad -> ad.startsWith("A"));
```

---

## 10. Hansı dövrü seçmək? {#hansi}

| Vəziyyət | Tövsiyə |
|---|---|
| Dəqiq sayda təkrar (10 dəfə) | `for` |
| Şərt ödənənə qədər | `while` |
| Ən az bir dəfə işləsin (menu) | `do-while` |
| Kolleksiyanın bütün elementləri | `for-each` |
| İndeks lazımdır | Klassik `for` |
| Kolleksiyadan silmək | `Iterator` |
| Functional stil | Stream `.forEach()` |

---

## 11. Praktik nümunələr {#numuneler}

### 11.1 Massivin cəmi

```java
int[] nums = {10, 20, 30, 40, 50};
int cəm = 0;

for (int n : nums) {
    cəm += n;
}

System.out.println("Cəm: " + cəm);  // 150
```

### 11.2 Maksimum tap

```java
int[] nums = {5, 2, 8, 1, 9, 3};
int maks = nums[0];

for (int i = 1; i < nums.length; i++) {
    if (nums[i] > maks) {
        maks = nums[i];
    }
}

System.out.println("Maks: " + maks);  // 9
```

### 11.3 Factorial

```java
int n = 5;
long faktorial = 1;

for (int i = 1; i <= n; i++) {
    faktorial *= i;
}

System.out.println(n + "! = " + faktorial);  // 5! = 120
```

### 11.4 Fibonacci ardıcıllığı

```java
int n = 10;
int a = 0, b = 1;

System.out.print(a + " " + b);
for (int i = 2; i < n; i++) {
    int c = a + b;
    System.out.print(" " + c);
    a = b;
    b = c;
}
// 0 1 1 2 3 5 8 13 21 34
```

### 11.5 Vurma cədvəli

```java
for (int i = 1; i <= 9; i++) {
    for (int j = 1; j <= 9; j++) {
        System.out.printf("%2d x %d = %2d   ", i, j, i * j);
    }
    System.out.println();
}
```

### 11.6 Sadə ədədləri tap

```java
int n = 20;

for (int i = 2; i <= n; i++) {
    boolean sadə = true;
    for (int j = 2; j * j <= i; j++) {
        if (i % j == 0) {
            sadə = false;
            break;
        }
    }
    if (sadə) System.out.print(i + " ");
}
// 2 3 5 7 11 13 17 19
```

### 11.7 Reverse string

```java
String giriş = "salam";
StringBuilder tərs = new StringBuilder();

for (int i = giriş.length() - 1; i >= 0; i--) {
    tərs.append(giriş.charAt(i));
}

System.out.println(tərs);  // malas
```

### 11.8 Element axtarışı (linear search)

```java
public static int axtar(int[] nums, int hədəf) {
    for (int i = 0; i < nums.length; i++) {
        if (nums[i] == hədəf) {
            return i;
        }
    }
    return -1;  // tapılmadı
}

int[] arr = {10, 20, 30, 40, 50};
System.out.println(axtar(arr, 30));  // 2
System.out.println(axtar(arr, 100)); // -1
```

---

## 12. Ümumi Səhvlər {#umumi-sehvler}

### Səhv 1: Off-by-one

```java
int[] arr = new int[5];

// YANLIŞ — i=5 zamanı ArrayIndexOutOfBoundsException
for (int i = 0; i <= arr.length; i++) { ... }

// DOĞRU
for (int i = 0; i < arr.length; i++) { ... }
```

İndekslər 0-dan `length-1`-ə qədərdir.

### Səhv 2: Sonsuz dövr

```java
int i = 0;
while (i < 10) {
    System.out.println(i);
    // i++ UNUDULDU
}
// Əbədidir — Ctrl+C ilə öldür
```

### Səhv 3: Modifiye indeksi for-each-də

```java
List<Integer> nums = new ArrayList<>(List.of(1, 2, 3, 4, 5));

// YANLIŞ — ConcurrentModificationException
for (Integer n : nums) {
    if (n == 3) nums.remove(n);
}

// DOĞRU
nums.removeIf(n -> n == 3);
```

### Səhv 4: Tip uyğunluğu for-each-də

```java
int[] nums = {1, 2, 3};

// YANLIŞ — integer yox, int lazımdır
for (Integer n : nums) { ... }  // XƏTA — int[] Integer ilə uyğun deyil

// DOĞRU
for (int n : nums) { ... }

// VƏ YA auto-boxing ilə List-də
List<Integer> list = List.of(1, 2, 3);
for (Integer n : list) { ... }   // OK
```

### Səhv 5: Break uzaqdan işlədir kimi düşünmək

```java
for (int i = 0; i < 3; i++) {
    for (int j = 0; j < 3; j++) {
        if (j == 2) break;  // YALNIZ daxili dövrü dayandırır!
    }
}
```

Xarici dövrdən çıxmaq üçün labeled break istifadə et.

### Səhv 6: continue-ni if-else kimi düşünmək

```java
for (int i = 0; i < 10; i++) {
    if (i % 2 == 0) continue;
    if (i > 5) {
        System.out.println(i);
    }
}
// 7 9 — continue sonrakı kodu da ötürür
```

### Səhv 7: do-while-də `;` unutmaq

```java
do {
    ...
} while (x > 0)    // XƏTA — ; lazım
```

### Səhv 8: İnteger overflow dövrdə

```java
for (int i = Integer.MAX_VALUE - 5; i < Integer.MAX_VALUE + 5; i++) {
    // SONSUZ DÖVR!
    // Çünki MAX_VALUE + 1 = MIN_VALUE (wrap around)
    // i həmişə MAX_VALUE + 5-dən kiçik qalır
}
```

---

## 13. İntervyu Sualları {#intervyu}

**S1: for, while, do-while fərqi nədir?**
> **for** — initialization, şərt, update bir yerdədir; dəqiq sayda təkrar üçün. **while** — yalnız şərt; əvvəl yoxlayır, sonra gövdə işləyir. **do-while** — gövdə əvvəl işləyir, sonra şərt; ən az bir dəfə icra təminatlıdır.

**S2: for-each-ın məhdudiyyətləri nələrdir?**
> İndeks yoxdur (sıra nömrəsi lazımsa çətindir). Tərsinə getmək olmaz. Elementi dəyişmək olmaz (reference saxlanır, amma ilkin yerdəkinə yazmır). Kolleksiyadan silmək olmaz — ConcurrentModificationException atır.

**S3: `break` ilə `continue` arasında fərq nədir?**
> `break` — dövrü tamamilə **dayandırır**, dövrdən çıxır. `continue` — cari iterasiyanın qalan hissəsini **atlayır**, update/şərt yoxlamasına keçir.

**S4: Labeled break nədir və nə zaman istifadə olunur?**
> İç-içə dövrlərdə xarici dövrü dayandırmaq üçün. Etiket dövrdən əvvəl qoyulur: `outer: for(...) { for(...) { break outer; } }`. Təcrübədə nadir istifadə olunur — kodun oxunaqlığını azaldır. Əvəzinə metod çıxarıb `return` daha yaxşıdır.

**S5: `do-while` nə zaman istifadə etməlidir?**
> Gövdənin **ən az bir dəfə** icra olunması lazım olduqda. Ən klassik nümunə — istifadəçiyə menu göstərib seçim istəmək. Əvvəlcədən seçim yoxdur, ona görə əvvəl menu göstərilməlidir.

**S6: `for-each` arxada necə işləyir?**
> For-each kompilyator tərəfindən iki fərqli koda çevrilir. Massiv üçün — klassik `for` ilə indeks. `Iterable` üçün — `Iterator` çağırılır: `it.hasNext()` və `it.next()`. Ona görə özünüz class yaratdıqda `Iterable<T>` implement etsəniz, for-each-də istifadə etmək olar.

**S7: Kolleksiyadan element silmək dövr daxilində niyə təhlükəlidir?**
> Çünki bir çox kolleksiya (ArrayList, HashMap) daxili "modification counter" saxlayır. For-each daxilində `Iterator` istifadə olunur, silmə bu counter-i dəyişir, növbəti `next()` `ConcurrentModificationException` atır. Həll: `Iterator.remove()` və ya `collection.removeIf(predicate)`.

**S8: Sonsuz dövrdən necə çıxmaq olar?**
> Proqramlı: `break` ifadəsi ilə. Şərt dəyişəni true olanda. `return` ilə metoddan çıxmaq. İstisna atılanda (`throw`). Terminalda: `Ctrl+C` ilə prosesi dayandırmaq. Server koda xüsusi shutdown hook lazım.

**S9: Hansı dövr daha sürətlidir — for, while, for-each?**
> Praktikada fərq yoxdur — kompilyator və JIT optimallaşdırır. Mikrosaniyə fərqləri performans baxımından əhəmiyyətsizdir. Oxunaqlıq və niyyət daha vacibdir. Stream API isə bəzən yavaş ola bilər (amma oxunaqlıdır).

**S10: `for(;;) {}` qanunidirmi?**
> Bəli, tamamilə qanunidir. Bu sonsuz for dövrüdür — initialization, şərt, update boşdur. Adətən içində `break` şərti ilə istifadə olunur. Serverlərdə, oyunlarda, fasiləsiz proseslərdə istifadə olunur. `while (true)` ilə funksional olaraq eynidir.
