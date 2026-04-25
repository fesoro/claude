# 10 — Java-da İstifadəçi Girişi (Scanner, BufferedReader, Console)

> **Seviyye:** Beginner ⭐


## Mündəricat
1. [İstifadəçi girişi nədir?](#istifadəçi-girişi-nədir)
2. [Scanner sinfi — əsas istifadə](#scanner-sinfi-əsas-istifadə)
3. [Scanner metodları — nextInt, nextLine, next, nextDouble](#scanner-metodları)
4. [Məşhur nextLine() tələsi (nextInt-dən sonra)](#nextline-tələsi)
5. [hasNext* metodları ilə giriş yoxlaması](#hasnext-metodları)
6. [BufferedReader — daha sürətli giriş](#bufferedreader)
7. [Fayldan oxumaq (Scanner + File)](#fayldan-oxumaq)
8. [Console.readPassword() ilə şifrə oxumaq](#console-readpassword)
9. [Giriş doğrulama pattern-ləri](#giriş-doğrulama)
10. [Scanner-in bağlanması və System.in xəbərdarlığı](#scanner-bağlanması)
11. [Tam nümunə — Kalkulyator](#kalkulyator-nümunəsi)
12. [Ümumi Səhvlər](#ümumi-səhvlər)
13. [İntervyu Sualları](#intervyu-sualları)

---

## 1. İstifadəçi girişi nədir? {#istifadəçi-girişi-nədir}

Proqram işləyərkən istifadəçidən klaviatura vasitəsilə məlumat almaq **istifadəçi girişi** adlanır. Java-da bunun üçün əsasən üç yol var:

| Sinif | Nə vaxt? | Sürət |
|---|---|---|
| `Scanner` | Sadə giriş, tip çevirməsi lazım olanda | Orta |
| `BufferedReader` | Çoxlu sətir, performance vacib olanda | Sürətli |
| `Console` | Şifrə oxumaq, terminal inputu | Orta |

Real həyat analogiyası: `Scanner` — kafedəki ofisiant (sənin əvəzinə menyunu oxuyur, sifarişi tərcümə edir). `BufferedReader` — fast-food sistemi (daha sürətli, amma özün sifariş yazırsan). `Console` — bank terminalı (PIN kodu görünmür).

---

## 2. Scanner sinfi — əsas istifadə {#scanner-sinfi-əsas-istifadə)

`Scanner` — `java.util` paketindədir. `System.in` (standart giriş) axınını oxuyur.

```java
import java.util.Scanner;

public class İlkNümunə {
    public static void main(String[] args) {
        // Scanner yaradırıq — System.in-dən oxuyacaq
        Scanner scanner = new Scanner(System.in);

        System.out.print("Adınızı daxil edin: ");
        String ad = scanner.nextLine();   // bir sətir oxu

        System.out.print("Yaşınızı daxil edin: ");
        int yaş = scanner.nextInt();      // tam ədəd oxu

        System.out.println("Salam, " + ad + "! " + yaş + " yaşınız var.");

        scanner.close(); // resursu bağla
    }
}
```

### Çıxış:

```
Adınızı daxil edin: Orxan
Yaşınızı daxil edin: 25
Salam, Orxan! 25 yaşınız var.
```

---

## 3. Scanner metodları — nextInt, nextLine, next, nextDouble {#scanner-metodları}

### Əsas oxuma metodları:

| Metod | Nə oxuyur? | Nümunə |
|---|---|---|
| `nextLine()` | Tam bir sətir (boşluqlarla) | `"Orxan Hüseynov"` |
| `next()` | Bir söz (ilk boşluğa qədər) | `"Orxan"` |
| `nextInt()` | Tam ədəd (`int`) | `42` |
| `nextLong()` | Uzun tam ədəd (`long`) | `9999999999L` |
| `nextDouble()` | Onluq ədəd (`double`) | `3.14` |
| `nextFloat()` | `float` | `3.14f` |
| `nextBoolean()` | `boolean` | `true` |
| `nextByte()` | `byte` | `127` |
| `nextShort()` | `short` | `32000` |

### Praktik nümunə:

```java
import java.util.Scanner;

public class MetodNümunəsi {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);

        System.out.print("Bir söz yaz: ");
        String söz = sc.next();              // yalnız bir söz

        System.out.print("Bir sətir yaz: ");
        sc.nextLine(); // buferi təmizlə (aşağıda izah edilir)
        String sətir = sc.nextLine();        // tam sətir

        System.out.print("Tam ədəd: ");
        int tam = sc.nextInt();

        System.out.print("Onluq ədəd: ");
        double onluq = sc.nextDouble();

        System.out.print("true/false: ");
        boolean məntiqi = sc.nextBoolean();

        System.out.println("---");
        System.out.println("Söz: " + söz);
        System.out.println("Sətir: " + sətir);
        System.out.println("Tam: " + tam);
        System.out.println("Onluq: " + onluq);
        System.out.println("Məntiqi: " + məntiqi);

        sc.close();
    }
}
```

### Delimiter (ayırıcı) təyin etmək:

```java
Scanner sc = new Scanner("alma,armud,portağal");
sc.useDelimiter(","); // vergülə görə ayır

while (sc.hasNext()) {
    System.out.println(sc.next());
}
// Çıxış:
// alma
// armud
// portağal
```

---

## 4. Məşhur nextLine() tələsi (nextInt-dən sonra) {#nextline-tələsi}

**Bu Java yeni başlayanların ən çox qarşılaşdığı problemdir!**

### Problem:

```java
Scanner sc = new Scanner(System.in);

System.out.print("Yaş: ");
int yaş = sc.nextInt();         // istifadəçi "25\n" yazır

System.out.print("Ad: ");
String ad = sc.nextLine();      // BOŞ STRING qaytarır! Niyə?!
```

### Səbəb:

- İstifadəçi `25` yazıb Enter basanda bufferdə `25\n` olur.
- `nextInt()` yalnız `25`-i oxuyur, `\n` (newline) bufferdə qalır.
- `nextLine()` həmin `\n`-ı görüb dərhal boş sətir qaytarır.

### Həll — 3 üsul:

**Üsul 1: nextInt()-dən sonra boş nextLine() çağır**

```java
int yaş = sc.nextInt();
sc.nextLine();                  // buferdəki \n-ı oxu, at
String ad = sc.nextLine();      // indi düzgün oxuyur
```

**Üsul 2: Hər şeyi nextLine() ilə oxu, sonra çevir**

```java
System.out.print("Yaş: ");
int yaş = Integer.parseInt(sc.nextLine().trim());

System.out.print("Ad: ");
String ad = sc.nextLine();       // problem yoxdur
```

**Üsul 3: Ayrı Scanner istifadə etmə** — problemə səbəb olur, tövsiyə edilmir.

### Vizual izahat:

```
İstifadəçi yazır: 25[Enter]Orxan[Enter]
Buffer:           "25\nOrxan\n"

nextInt()    →    "25" alır, buffer: "\nOrxan\n"
nextLine()   →    "" alır (ilk \n-a qədər boşdur), buffer: "Orxan\n"
nextLine()   →    "Orxan" alır, buffer: ""
```

---

## 5. hasNext* metodları ilə giriş yoxlaması {#hasnext-metodları}

`hasNext*` metodları — **oxumadan əvvəl** növbəti token-in mövcud olduğunu və tipə uyğun olduğunu yoxlayır.

| Metod | Yoxlayır |
|---|---|
| `hasNext()` | Növbəti token var? |
| `hasNextLine()` | Növbəti sətir var? |
| `hasNextInt()` | Növbəti token int-dir? |
| `hasNextDouble()` | Növbəti token double-dır? |
| `hasNextBoolean()` | Növbəti token boolean-dır? |

### Sadə yoxlama:

```java
Scanner sc = new Scanner(System.in);
System.out.print("Ədəd daxil edin: ");

if (sc.hasNextInt()) {
    int n = sc.nextInt();
    System.out.println("Kvadratı: " + (n * n));
} else {
    System.out.println("Bu ədəd deyil!");
}
```

### Loop ilə oxuma (EOF-a qədər):

```java
Scanner sc = new Scanner(System.in);
System.out.println("Ədədlər daxil edin (Ctrl+D ilə bitir):");

int cəm = 0;
while (sc.hasNextInt()) {
    cəm += sc.nextInt();
}
System.out.println("Cəm: " + cəm);
```

### Düzgün ədəd oxuma (loop ilə yoxlama):

```java
Scanner sc = new Scanner(System.in);
int yaş;

while (true) {
    System.out.print("Yaşınızı daxil edin: ");
    if (sc.hasNextInt()) {
        yaş = sc.nextInt();
        if (yaş > 0 && yaş < 150) break;
        System.out.println("Düzgün yaş daxil edin!");
    } else {
        System.out.println("Bu rəqəm deyil!");
        sc.next(); // yanlış tokeni at
    }
}
System.out.println("Yaşınız: " + yaş);
```

---

## 6. BufferedReader — daha sürətli giriş {#bufferedreader}

`BufferedReader` — `Scanner`-dən **5-10 dəfə sürətlidir**. Daxili buffer istifadə edir. Böyük həcmli giriş üçün (məs., rəqabət proqramlaşdırmasında) daha uyğundur.

### Əsas istifadə:

```java
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.IOException;

public class BufferedReaderNümunə {
    public static void main(String[] args) throws IOException {
        // System.in-i InputStreamReader ilə sarı
        BufferedReader br = new BufferedReader(new InputStreamReader(System.in));

        System.out.print("Ad: ");
        String ad = br.readLine();       // sətir oxu

        System.out.print("Yaş: ");
        int yaş = Integer.parseInt(br.readLine().trim());  // özün çevir

        System.out.println("Salam " + ad + ", " + yaş + " yaş.");

        br.close();
    }
}
```

### Birdən çox ədədin bir sətirdə oxunması:

```java
// Giriş: "10 20 30 40 50"
BufferedReader br = new BufferedReader(new InputStreamReader(System.in));
String sətir = br.readLine();
String[] hissələr = sətir.split("\\s+");  // boşluqla ayır

int[] ədədlər = new int[hissələr.length];
for (int i = 0; i < hissələr.length; i++) {
    ədədlər[i] = Integer.parseInt(hissələr[i]);
}

for (int n : ədədlər) {
    System.out.println(n);
}
```

### Scanner vs BufferedReader müqayisəsi:

| Xüsusiyyət | Scanner | BufferedReader |
|---|---|---|
| Sürət | Yavaş | Sürətli |
| Tip çevirməsi | Avtomatik (nextInt, nextDouble) | Əllə (Integer.parseInt) |
| Exception | Runtime (`InputMismatchException`) | Checked (`IOException`) |
| Sintaksis | Sadə | Bir az daha mürəkkəb |
| Regex dəstəyi | Bəli | Xeyr |
| Thread-safe | Xeyr | Bəli (synchronized) |

---

## 7. Fayldan oxumaq (Scanner + File) {#fayldan-oxumaq}

`Scanner` yalnız `System.in` deyil, fayldan və ya sətirdən də oxuya bilər.

### Fayldan oxumaq:

```java
import java.util.Scanner;
import java.io.File;
import java.io.FileNotFoundException;

public class FayldanOxu {
    public static void main(String[] args) throws FileNotFoundException {
        File fayl = new File("mətn.txt");
        Scanner sc = new Scanner(fayl);

        int sətirSayı = 0;
        while (sc.hasNextLine()) {
            String sətir = sc.nextLine();
            sətirSayı++;
            System.out.println(sətirSayı + ": " + sətir);
        }
        sc.close();
    }
}
```

### try-with-resources ilə (tövsiyə olunur):

```java
try (Scanner sc = new Scanner(new File("mətn.txt"))) {
    while (sc.hasNextLine()) {
        System.out.println(sc.nextLine());
    }
} catch (FileNotFoundException e) {
    System.err.println("Fayl tapılmadı: " + e.getMessage());
}
```

### String-dən oxumaq (test üçün faydalı):

```java
Scanner sc = new Scanner("10 20 30");
while (sc.hasNextInt()) {
    System.out.println(sc.nextInt());
}
// Çıxış: 10, 20, 30
```

---

## 8. Console.readPassword() ilə şifrə oxumaq {#console-readpassword}

`System.console()` — terminal ilə işləmək üçün istifadə olunur. Ən faydalı xüsusiyyəti — **şifrəni ekrana göstərmədən** oxumaqdır.

```java
import java.io.Console;

public class ŞifrəOxuma {
    public static void main(String[] args) {
        Console console = System.console();

        if (console == null) {
            System.err.println("Console əlçatan deyil (IDE-də işləyirik?)");
            System.exit(1);
        }

        String istifadəçi = console.readLine("İstifadəçi adı: ");
        char[] şifrə = console.readPassword("Şifrə: "); // görünmür

        // Şifrəni istifadə et
        if ("admin".equals(istifadəçi) && "12345".equals(new String(şifrə))) {
            console.printf("Xoş gəldin, %s!%n", istifadəçi);
        } else {
            console.printf("Giriş uğursuz!%n");
        }

        // Təhlükəsizlik: array-i təmizlə
        java.util.Arrays.fill(şifrə, ' ');
    }
}
```

### Niyə `char[]`, `String` deyil?

- `String` immutable-dır — yaddaşda qalır, GC-yə qədər təmizlənə bilməz.
- `char[]` təmizlənə bilər (`Arrays.fill(şifrə, ' ')`).
- Memory dump edilsə, `String` şifrə görünər, `char[]` təmizlənibsə görünməz.

### Xəbərdarlıq:

`System.console()` — yalnız **terminal-dan işə salındıqda** null olmayacaq. IntelliJ IDEA-da birbaşa Run etsən, `null` qaytarır. Terminal-dan `java MyClass` kimi işə sal.

---

## 9. Giriş doğrulama pattern-ləri {#giriş-doğrulama}

### Pattern 1: Döngü ilə yoxlama

```java
Scanner sc = new Scanner(System.in);
int yaş = 0;
boolean düzgün = false;

while (!düzgün) {
    System.out.print("Yaş (1-120): ");
    try {
        yaş = Integer.parseInt(sc.nextLine().trim());
        if (yaş >= 1 && yaş <= 120) {
            düzgün = true;
        } else {
            System.out.println("Yaş 1-120 arasında olmalıdır!");
        }
    } catch (NumberFormatException e) {
        System.out.println("Bu ədəd deyil! Yenidən cəhd edin.");
    }
}
```

### Pattern 2: Köməkçi metod

```java
public static int oxuTamƏdəd(Scanner sc, String sual, int min, int max) {
    while (true) {
        System.out.print(sual);
        String giriş = sc.nextLine().trim();
        try {
            int n = Integer.parseInt(giriş);
            if (n >= min && n <= max) return n;
            System.out.printf("Diapazon: [%d, %d]%n", min, max);
        } catch (NumberFormatException e) {
            System.out.println("Yanlış format!");
        }
    }
}

// İstifadə:
int yaş = oxuTamƏdəd(sc, "Yaş: ", 1, 120);
int səviyyə = oxuTamƏdəd(sc, "Səviyyə: ", 1, 10);
```

### Pattern 3: Regex ilə e-poçt yoxlaması

```java
public static String oxuEmail(Scanner sc) {
    String regex = "^[\\w.-]+@[\\w.-]+\\.\\w+$";
    while (true) {
        System.out.print("Email: ");
        String email = sc.nextLine().trim();
        if (email.matches(regex)) return email;
        System.out.println("Yanlış email formatı!");
    }
}
```

---

## 10. Scanner-in bağlanması və System.in xəbərdarlığı {#scanner-bağlanması}

### Qayda: Açdığını bağla!

```java
Scanner sc = new Scanner(System.in);
try {
    // istifadə
} finally {
    sc.close(); // resursları azad et
}

// və ya try-with-resources (daha yaxşı):
try (Scanner sc = new Scanner(System.in)) {
    // istifadə
}
```

### XƏBƏRDARLIQ: System.in-i bağlama!

```java
// PROBLEM:
Scanner sc1 = new Scanner(System.in);
sc1.nextLine();
sc1.close();  // ← System.in DƏ bağlanır!

Scanner sc2 = new Scanner(System.in);
sc2.nextLine();  // XƏTA: Stream closed
```

**Səbəb:** `Scanner.close()` altındakı streami də bağlayır. `System.in` bir dəfə bağlananda, yenidən açıla bilməz.

### Həll:

```java
// Bütün proqram boyu YALNIZ bir Scanner saxla
public class BirScanner {
    private static final Scanner sc = new Scanner(System.in);

    public static void main(String[] args) {
        ad();
        yaş();
        // close() çağırma — JVM təmizləyəcək
    }

    static void ad() { System.out.println(sc.nextLine()); }
    static void yaş() { System.out.println(sc.nextInt()); }
}
```

---

## 11. Tam nümunə — Kalkulyator {#kalkulyator-nümunəsi}

```java
import java.util.Scanner;

public class Kalkulyator {

    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);

        System.out.println("=== Sadə Kalkulyator ===");
        System.out.println("Əməliyyatlar: +, -, *, /, q (çıxış)");

        while (true) {
            System.out.print("\n1-ci ədəd: ");
            if (!sc.hasNextDouble()) {
                String söz = sc.next();
                if (söz.equalsIgnoreCase("q")) {
                    System.out.println("Çıxılır...");
                    break;
                }
                System.out.println("Yanlış ədəd!");
                continue;
            }
            double a = sc.nextDouble();

            System.out.print("Əməliyyat (+, -, *, /): ");
            String op = sc.next();

            System.out.print("2-ci ədəd: ");
            if (!sc.hasNextDouble()) {
                System.out.println("Yanlış ədəd!");
                sc.next();
                continue;
            }
            double b = sc.nextDouble();

            double nəticə;
            switch (op) {
                case "+": nəticə = a + b; break;
                case "-": nəticə = a - b; break;
                case "*": nəticə = a * b; break;
                case "/":
                    if (b == 0) {
                        System.out.println("Sıfıra bölmək olmaz!");
                        continue;
                    }
                    nəticə = a / b;
                    break;
                default:
                    System.out.println("Naməlum əməliyyat: " + op);
                    continue;
            }

            System.out.printf("Nəticə: %.2f %s %.2f = %.4f%n", a, op, b, nəticə);
        }

        sc.close();
    }
}
```

### İşləmə nümunəsi:

```
=== Sadə Kalkulyator ===
Əməliyyatlar: +, -, *, /, q (çıxış)

1-ci ədəd: 10
Əməliyyat (+, -, *, /): *
2-ci ədəd: 5
Nəticə: 10.00 * 5.00 = 50.0000

1-ci ədəd: q
Çıxılır...
```

---

## Ümumi Səhvlər

### 1. nextLine() tələsini unutmaq

```java
int a = sc.nextInt();
String s = sc.nextLine();  // BOŞ sətir alır!
```

**Həll:** `nextInt()`-dən sonra `sc.nextLine()` çağır, sonra əsl `nextLine()`-i et.

### 2. Scanner-i bağlamamaq

```java
// YANLIŞ:
Scanner sc = new Scanner(System.in);
String ad = sc.nextLine();
// sc.close() yoxdur — resource leak
```

**Həll:** try-with-resources istifadə et.

### 3. System.in-i bağlayıb yenidən açmağa çalışmaq

```java
Scanner sc1 = new Scanner(System.in);
sc1.close();  // System.in bağlandı!
Scanner sc2 = new Scanner(System.in);  // PROBLEM
```

**Həll:** Tək Scanner obyekti istifadə et.

### 4. nextInt() yerinə next() istifadə edib parseInt etməmək

```java
// Yanlış format gəlsə NumberFormatException atır — həll etməmisən
String s = sc.next();
int n = Integer.parseInt(s);  // "abc" gəlsə crash
```

**Həll:** `try-catch` və ya `hasNextInt()` ilə əvvəlcə yoxla.

### 5. Locale problemi (onluq nöqtəsi)

```java
Scanner sc = new Scanner(System.in);
double d = sc.nextDouble();
// Azərbaycan locale-da "3,14" gözləyir, "3.14" XƏTA verir!
```

**Həll:**

```java
Scanner sc = new Scanner(System.in);
sc.useLocale(java.util.Locale.US);  // nöqtə istifadə et
double d = sc.nextDouble();
```

### 6. IDE-də System.console() null olur

```java
Console c = System.console();
c.readLine();  // NullPointerException IDE-də!
```

**Həll:** Yoxla — `if (c != null)`. Terminal-dan işə sal.

### 7. Şifrəni String kimi saxlamaq

```java
// YANLIŞ: şifrə yaddaşda qalır
String şifrə = new String(console.readPassword());
```

**Həll:** `char[]` istifadə et, istifadədən sonra `Arrays.fill(şifrə, ' ')`.

---

## İntervyu Sualları

**S1: Scanner və BufferedReader arasında əsas fərqlər nədir?**
> `Scanner` sadədir — tipləri avtomatik çevirir (`nextInt`, `nextDouble`), regex dəstəkləyir, amma yavaşdır. `BufferedReader` sürətlidir (bufferləşmiş), amma yalnız `readLine()` verir — tipləri özün çevirməlisən (`Integer.parseInt`). Böyük giriş və ya rəqabət proqramlaşdırmasında `BufferedReader` üstündür.

**S2: nextInt()-dən sonra nextLine() niyə boş qayıdır?**
> `nextInt()` ədədi oxuyur, amma sonrakı `\n` (Enter) simvolunu buferdə saxlayır. Sonra çağırılan `nextLine()` həmin `\n`-a qədər olanı (yəni boş sətiri) alır və dərhal qayıdır. Həll: `nextInt()`-dən sonra əlavə `sc.nextLine()` çağırıb buferi təmizləmək.

**S3: Scanner.close() System.in-ə necə təsir edir?**
> `Scanner.close()` altında qurulmuş streami də bağlayır. Əgər Scanner `System.in`-dən yaradılıbsa, onu bağlamaq `System.in`-i də bağlayır. Bundan sonra yeni Scanner yaratmaq `Stream closed` xətası verir. Ona görə `System.in`-dən yaradılmış Scanner-i adətən bağlamırıq və ya tək nüsxə saxlayırıq.

**S4: hasNextInt() nəyə görə faydalıdır?**
> Giriş düzgünlüyünü **oxumadan əvvəl** yoxlamağa imkan verir. Əgər növbəti token `int`-ə çevrilə bilməzsə, `nextInt()` `InputMismatchException` atır və proqram crash olur. `hasNextInt()` ilə əvvəlcə yoxlayıb yanlış giriş halında emal edə bilərik.

**S5: Console.readPassword() niyə `char[]` qaytarır, `String` yox?**
> `String` immutable-dır və Java-nın string pool-unda saxlanılır — GC-yə qədər yaddaşda qalır. Əgər proqram memory dump edilsə, şifrə aşkar görünər. `char[]` isə istifadədən sonra `Arrays.fill(pw, ' ')` ilə təmizlənə bilər. Bu təhlükəsizlik best practice-idir.

**S6: Fərqli locale-larda Scanner.nextDouble() niyə xəta verir?**
> `Scanner` default olaraq sistemin locale-indən ayırıcı (vergül və ya nöqtə) istifadə edir. Azərbaycan locale-ında onluq ayırıcı vergüldür (`3,14`), ingilis locale-ında nöqtədir (`3.14`). Həll: `sc.useLocale(Locale.US)` — həmişə nöqtə ilə oxusun.

**S7: BufferedReader-in checked exception atması niyə vacibdir?**
> `BufferedReader.readLine()` `IOException` atır (checked). Bu, istifadəçini səhv emal etməyə məcbur edir — fayl açıq deyilsə, şəbəkə qırılıbsa və s. `Scanner` isə runtime exception atır, asanlıqla unudula bilər. Checked exception — API dizaynı cəhətdən daha məsuliyyətlidir.

**S8: Bir sətirdə çoxlu ədədi necə oxuyursan?**
> İki yol: (1) `Scanner.nextInt()`-i döngədə çağır — `10 20 30` üçün üç dəfə işləyər. (2) `BufferedReader.readLine()` ilə bütün sətri al, `split("\\s+")` ilə ayır, hər birini `Integer.parseInt()` ilə çevir. Böyük həcmdə (2) daha sürətlidir.

**S9: try-with-resources Scanner üçün nə üçün tövsiyə olunur?**
> `Scanner` `AutoCloseable` interfeysini implement edir. try-with-resources istifadəsi `close()`-un həmişə çağırılmasına zəmanət verir — hətta exception olsa belə. Bu resource leak-ın qarşısını alır. Amma `System.in`-dən Scanner üçün diqqətli ol — `System.in`-i də bağlayacaq.

**S10: Scanner.next() ilə nextLine() fərqi nədir?**
> `next()` yalnız növbəti **tokeni** (boşluqla ayrılan bir sözü) oxuyur. `nextLine()` isə **tam sətri** (boşluqlarla birlikdə) oxuyur — `\n`-a qədər. Məsələn, `"Orxan Hüseynov\n"` girişində `next()` → `"Orxan"`, `nextLine()` → `"Orxan Hüseynov"`.
