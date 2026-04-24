# main Metodu və Proqramın İcrası

> **Seviyye:** Beginner ⭐

## Giriş

Java və PHP proqramı işə salma yolu tamamilə fərqlidir. Java-də hər proqramın bir "giriş nöqtəsi" (entry point) lazımdır — xüsusi bir `main` metodu. Bu metod olmasa, proqram işə düşə bilməz. PHP-də isə belə bir şey yoxdur: PHP faylı yuxarıdan aşağı sətir-sətir icra olunur.

Bu fərq hər iki dilin dizayn fəlsəfəsindən gəlir: Java **kompilyasiya olunan** (compiled) dildir — əvvəl `.java` faylı `.class` faylına çevrilməlidir, sonra JVM (Java Virtual Machine) onu işlədir. PHP isə **interpreted** dildir — faylı birbaşa icra edir. Bu faylda biz hər addımı sifirdan izah edirik: `javac`, `java`, `System.out.println`, command-line arguments, exit codes və daha çoxunu.

---

## Java-da istifadəsi

### Hello World — ən sadə Java proqramı

```java
// Fayl adı: Hello.java  (fayl adı sinif adı ilə eyni OLMALIDIR)
public class Hello {
    public static void main(String[] args) {
        System.out.println("Salam, dünya!");
    }
}
```

**Bu proqramı necə işə salırıq?**

```bash
# Terminal-da:
javac Hello.java    # Compile et -- Hello.class yaradır
java Hello          # İşə sal (.class uzantısını YAZMA)
```

Çıxış:
```
Salam, dünya!
```

### `public static void main(String[] args)` — hər söz nə deməkdir?

Bu ən çox qorxulu sətirdir yeni başlayanlar üçün. Gəl hər sözü ayrı-ayrı izah edək.

**`public`** — access modifier. JVM bu metodu **sinifdən kənardan** çağıracaq, buna görə də `public` olmalıdır. `private` olsa, JVM onu görə bilməz və xəta verir.

**`static`** — sinif səviyyəsində metod (instance deyil). JVM `main`-i çağırmaq üçün `new Hello()` yaratmır — birbaşa `Hello.main()` formasında çağırır. Static olmasa, JVM necə obyekt yaratacağını bilməz.

**`void`** — heç nə qaytarmır. Java-də `main` dəyər qaytara bilməz. Exit code vermək üçün `System.exit(kod)` istifadə olunur.

**`main`** — metodun adı. JVM məhz bu adı axtarır. `Main`, `MAIN`, `başla` — heç biri işləməz.

**`String[] args`** — command-line arguments massivi. Proqrama terminal-dan ötürülən sözləri saxlayır.

```java
// Bütün variantları birlikdə görək -- bunların hamısı eynidir:
public static void main(String[] args)      // klassik
public static void main(String args[])      // C-stili, işləyir amma stil yaxşı deyil
public static void main(String... args)     // varargs -- bu da işləyir!
static public void main(String[] args)      // söz sırası fərq etmir
```

### Compile + Run prosesi addım-addım

```
[Hello.java]  --- javac (kompilyator) --->  [Hello.class]  --- java (JVM) --->  Terminal çıxışı
 mətn fayl                                    bytecode                            "Salam, dünya!"
```

**1. `javac Hello.java`** edəndə nə olur?
- Java Compiler (`javac`) `.java` faylını oxuyur.
- Syntax və tipləri yoxlayır.
- `Hello.class` adlı bytecode fayl yaradır.
- Bytecode insan tərəfindən oxuna bilən formatda deyil — JVM üçündür.

**2. `java Hello` edəndə nə olur?**
- JVM (`java` komandası) işə düşür.
- `Hello.class` faylını oxuyur.
- `main` metodunu tapır və çağırır.
- Bytecode-u JIT (Just-In-Time) kompilyator native makina koduna çevirir.
- Proqram işləyir.

### `.class` faylı və bytecode — qısa izah

```bash
# .class faylına baxmaq -- binar olduğu üçün oxunmaz
cat Hello.class    # Qarışıq simvollar

# Lakin javap ilə disassembly edə bilərik
javap -c Hello
# Çıxış:
# Compiled from "Hello.java"
# public class Hello {
#   public Hello();
#     Code:
#        0: aload_0
#        1: invokespecial #1  // Method java/lang/Object."<init>":()V
#        4: return
#
#   public static void main(java.lang.String[]);
#     Code:
#        0: getstatic     #7  // Field System.out
#        3: ldc           #13 // String Salam, dunya!
#        5: invokevirtual #15 // Method println
#        8: return
# }
```

Bytecode platformadan asılı deyil — eyni `.class` faylı Windows, Linux və Mac-də işləyir. Bu Java-nın "Write Once, Run Anywhere" sloqanının mənasıdır.

### Command-line arguments (`args[]`)

```java
public class Salamla {
    public static void main(String[] args) {
        // args -- istifadəçinin terminal-da yazdığı sözlərdir
        
        if (args.length == 0) {
            System.out.println("İstifadə: java Salamla <ad>");
            return;
        }
        
        System.out.println("Argument sayı: " + args.length);
        
        // Bütün argumentləri çap et
        for (int i = 0; i < args.length; i++) {
            System.out.println("args[" + i + "] = " + args[i]);
        }
        
        // İlk argumenti istifadə et
        String ad = args[0];
        System.out.println("Salam, " + ad + "!");
    }
}
```

**İşə salma:**
```bash
javac Salamla.java
java Salamla Orxan Ali Vəli
```

**Çıxış:**
```
Argument sayı: 3
args[0] = Orxan
args[1] = Ali
args[2] = Vəli
Salam, Orxan!
```

**Vacib:** Bütün argumentlər `String`-dir. Əgər ədəd kimi istifadə etmək istəsəniz, əl ilə çevirməlisiniz:

```java
public static void main(String[] args) {
    int yash = Integer.parseInt(args[0]);  // "25" -> 25
    double boy = Double.parseDouble(args[1]);  // "1.78" -> 1.78
    
    System.out.println("Yaş: " + yash);
    System.out.println("Boy: " + boy);
}
```

### `System.exit(kod)` və exit codes

```java
public class Exit {
    public static void main(String[] args) {
        if (args.length == 0) {
            System.err.println("XƏTA: Argument lazımdır!");
            System.exit(1);  // Xəta ilə çıx (non-zero kod)
        }
        
        System.out.println("Hər şey yaxşıdır");
        System.exit(0);  // Uğurla çıx (default)
    }
}
```

**Unix/Linux konvensiyası:**
- `0` = uğurlu (success)
- `1` = ümumi xəta
- `2` = istifadə xətası (yanlış argumentlər)
- `127` = komanda tapılmadı
- `130` = Ctrl+C ilə dayandırıldı

**Yoxlama:**
```bash
java Exit
# Çıxış: XƏTA: Argument lazımdır!
echo $?   # 1

java Exit filename
# Çıxış: Hər şey yaxşıdır
echo $?   # 0
```

### Standart output və standart error

```java
public class Output {
    public static void main(String[] args) {
        System.out.println("Bu standart output-dur (stdout)");
        System.err.println("Bu standart error-dur (stderr)");
        
        // println vs print
        System.out.println("Sətir sonu ilə");  // \n əlavə edir
        System.out.print("Sətir sonu");
        System.out.print(" yoxdur");
        System.out.println();  // Yalnız yeni sətir
        
        // printf -- formatlaşdırılmış çıxış
        System.out.printf("Ad: %s, Yaş: %d%n", "Orxan", 25);
        System.out.printf("Qiymət: %.2f%n", 3.14159);  // 3.14
        
        // String.format -- printf kimidir, amma string qaytarır
        String mesaj = String.format("Bal: %d/100", 85);
        System.out.println(mesaj);
    }
}
```

**Stream-ləri yönləndirmə:**
```bash
java Output > output.txt      # stdout faylına, stderr terminalda qalır
java Output 2> errors.txt     # stderr faylına
java Output > out.txt 2>&1    # ikisi də eyni fayla
```

### `Scanner` ilə input oxumaq (System.in)

```java
import java.util.Scanner;

public class Input {
    public static void main(String[] args) {
        Scanner scanner = new Scanner(System.in);
        
        System.out.print("Adınız: ");
        String ad = scanner.nextLine();
        
        System.out.print("Yaşınız: ");
        int yash = scanner.nextInt();
        
        System.out.print("Boy (m): ");
        double boy = scanner.nextDouble();
        
        System.out.println("Salam, " + ad + "!");
        System.out.println("Yaş: " + yash + ", Boy: " + boy);
        
        scanner.close();  // Resource-ları azad et
    }
}
```

**İşə salma:**
```bash
java Input
Adınız: Orxan
Yaşınız: 25
Boy (m): 1.78
Salam, Orxan!
Yaş: 25, Boy: 1.78
```

### IntelliJ IDEA-da Run button necə işləyir?

IntelliJ-də yaşıl "Run" düyməsini basanda fonda bu baş verir:

1. **Compile:** IntelliJ daxili kompilyatoru (`javac`-in ekvivalenti) bütün dəyişmiş faylları kompilyasiya edir. Nəticələr `out/` və ya `target/` qovluğunda saxlanılır.
2. **Classpath qurulur:** Bütün library-lər (Maven/Gradle dependencies) və öz `.class` faylların bir siyahıya əlavə olunur.
3. **JVM işə salınır:** `java -cp <classpath> <MainClass>` kimi bir əmr göndərilir.
4. **Output panel:** stdout və stderr IntelliJ-in "Run" panelinə yönləndirilir.

`Run` konfiqurasiyasında əlavə command-line arguments və VM options təyin edə bilərsiniz.

### JAR faylı və `java -jar app.jar`

Böyük proqramlarda onlarla `.class` faylı olur. Bunları bir yerdə saxlamaq üçün JAR (Java Archive) faylı istifadə olunur — bu əslində ZIP faylıdır.

```bash
# JAR yaratmaq
jar cfe app.jar Hello Hello.class
#    c = create
#    f = fayl adı verilir
#    e = entry point (main sinif)

# İşə salmaq
java -jar app.jar Orxan Ali
```

JAR içində `META-INF/MANIFEST.MF` faylı `Main-Class` xüsusiyyətini saxlayır — JVM bu sayədə hansı sinifin `main`-ini çağıracağını bilir.

Maven/Gradle avtomatik olaraq JAR yaradır (`mvn package`, `./gradlew build`). Spring Boot isə "fat JAR" (uber JAR) yaradır — bütün dependency-lər daxildədir.

---

## PHP-də istifadəsi

### Sadə PHP skripti

```php
<?php
// Fayl adı: hello.php
echo "Salam, dünya!\n";
```

**İşə salma:**
```bash
php hello.php
```

**Çıxış:**
```
Salam, dünya!
```

**Diqqət:** Heç bir `main` yoxdur! PHP faylı yuxarıdan aşağı icra olunur. Heç bir class, metod lazım deyil.

### CLI argumentləri — `$argv` və `$argc`

```php
<?php
// salamla.php

if ($argc < 2) {
    echo "İstifadə: php salamla.php <ad>\n";
    exit(1);
}

echo "Argument sayı: $argc\n";   // əsas faylın adı DA sayılır!

foreach ($argv as $i => $arg) {
    echo "argv[$i] = $arg\n";
}

$ad = $argv[1];  // argv[0] faylın adıdır
echo "Salam, $ad!\n";
```

**İşə salma:**
```bash
php salamla.php Orxan Ali Vəli
```

**Çıxış:**
```
Argument sayı: 4
argv[0] = salamla.php
argv[1] = Orxan
argv[2] = Ali
argv[3] = Vəli
Salam, Orxan!
```

**Vacib fərq:** PHP-də `$argv[0]` faylın adıdır (Java-də isə `args[0]` ilk real argumentdir).

### `exit()` və `die()`

```php
<?php
if ($argc < 2) {
    fwrite(STDERR, "XƏTA: Argument lazımdır!\n");
    exit(1);  // və ya die(1)
}

echo "İşləyir\n";
exit(0);  // Bu sətir çatmaya bilər
```

`exit()` və `die()` funksiyaları tamamilə eynidir — sadəcə fərqli adları var. PHP-nin unikallığı!

### stdin oxumaq

```php
<?php
echo "Adınız: ";
$ad = trim(fgets(STDIN));

echo "Yaşınız: ";
$yash = (int) trim(fgets(STDIN));

echo "Salam, $ad! Yaşınız: $yash\n";
```

### Laravel `php artisan` nümunəsi

Laravel-də `artisan` əmr əslində bir PHP skriptidir:

```php
#!/usr/bin/env php
<?php
// artisan faylı
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->handle(
    $input = new Symfony\Component\Console\Input\ArgvInput,
    new Symfony\Component\Console\Output\ConsoleOutput
);
exit($status);
```

```bash
php artisan migrate
php artisan make:controller UserController
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Giriş nöqtəsi | `public static void main` metodu | Faylın birinci sətri |
| Kompilyasiya | `javac` ilə `.class` faylına | Yoxdur (interpreted) |
| İşə salma | `java ClassName` | `php file.php` |
| Sinif lazımdır? | Bəli | Xeyr |
| Fayl adı = sinif adı? | Bəli (public sinif üçün) | Fərq etmir |
| Argument massivi | `String[] args` | `$argv` |
| Argument sayı | `args.length` | `$argc` |
| İlk real argument | `args[0]` | `$argv[1]` (`$argv[0]` = fayl adı) |
| Exit funksiyası | `System.exit(kod)` | `exit(kod)` və ya `die()` |
| Default exit code | 0 (uğur) | 0 (uğur) |
| stdout | `System.out.println()` | `echo` və ya `print` |
| stderr | `System.err.println()` | `fwrite(STDERR, ...)` |
| Input | `Scanner(System.in)` | `fgets(STDIN)` |
| Formatlaşdırılmış çıxış | `printf` | `printf` |
| JAR analogu | JAR faylı | PHAR faylı (nadir) |
| Varargs | `String... args` | Variadic `...$args` |

---

## Niyə belə fərqlər var?

### Java: kompilyasiya və JVM

Java 1995-ci ildə **böyük, uzunmüddətli enterprise proqramlar** üçün yaradılıb. `main` metodu bu dizayn fəlsəfəsini əks etdirir:

1. **Aydın giriş nöqtəsi:** Bir sinifdə neçə metod olursa olsun, proqramın haradan başladığı şübhəsizdir — `main`.
2. **Bytecode və platformadan asıllıqsızlıq:** `.class` faylı istənilən JVM-də işləyir. "Write once, run anywhere" sloqanı.
3. **Kompilyasiya zamanı yoxlama:** Syntax və tip xətaları proqram işə düşməzdən əvvəl tapılır.
4. **JIT optimallaşdırma:** JVM kod işlədikcə "hot spots" tapır və native makina koduna çevirir — bu PHP-dən daha sürətlidir.

### PHP: interpreted və webə yönəlmiş

PHP 1994-cü ildə **HTML səhifəsinə dinamik məzmun əlavə etmək** üçün yaradılıb. Buna görə:

1. **Sadəlik:** Fayl yaradıb `echo "Salam";` yazıb işə salmaq mümkündür. Heç bir class, heç bir method.
2. **Web server integrasiyası:** Apache/Nginx bir HTTP sorğu gələndə PHP faylını yuxarıdan aşağı işlədir və nəticəni brauzerə göndərir. `main` konsepsiyası bura uyğun gəlmir.
3. **Request-based:** Hər sorğu üçün yeni PHP prosesi başlayır (klassik mode). JVM isə uzunmüddətli proses kimi qalır.
4. **CLI sonradan əlavə olundu:** PHP CLI (command-line interface) əsas xüsusiyyət deyildi — veb üçün yaradılmış dil sonradan CLI dəstəyi aldı.

### Praktik nəticə

- **Java:** İlk dəfə kod işə salmaq üçün 2 addım (`javac` + `java`). Amma sonra güclü performans və type safety.
- **PHP:** Bir əmr (`php file.php`) — dərhal nəticə. Amma runtime xətaları yalnız kod işlədikdə tapılır.

Müasir Java development-da Maven/Gradle və IntelliJ IDEA kompilyasiyanı "görünməz" edir. Spring Boot "fat JAR" yaradır — bir JAR faylı deploy edirsiniz, işləyir. PHP Laravel-da isə `php artisan serve` ilə development server işə salırıq — proses davamlıdır, istifadəçi sorğularına cavab verir.

---

## Ümumi səhvlər (Beginner traps)

### 1. Fayl adı sinif adı ilə uyğun gəlmir

```java
// Fayl: hello.java  (kiçik h)
public class Hello { ... }
// XƏTA: class Hello is public, should be declared in a file named Hello.java
```

### 2. `String[] args` əvəzinə `String args`

```java
public static void main(String args) { ... }  // XƏTA:
// JVM main tapa bilmir -- imza "String[] args" olmalıdır
```

### 3. `args[0]` əvəzinə index 1 istifadə etmək (PHP-dən gələnlər üçün)

```java
public static void main(String[] args) {
    String ad = args[1];  // YANLIŞ! İlk argument args[0]-dır (Java-də)
}
```

```php
// PHP-də əksinə
$ad = $argv[0];  // YANLIŞ! $argv[0] fayl adıdır
$ad = $argv[1];  // Düzgün
```

### 4. `Integer.parseInt` yoxlamasız

```java
int yash = Integer.parseInt(args[0]);
// Əgər args[0] = "abc" olsa, NumberFormatException atılır

// Düzgün yol:
try {
    int yash = Integer.parseInt(args[0]);
} catch (NumberFormatException e) {
    System.err.println("Yaş ədəd olmalıdır!");
    System.exit(2);
}
```

### 5. `static` unutmaq

```java
public class Hello {
    public void main(String[] args) {  // static YOXDUR!
        System.out.println("Salam");
    }
}
// XƏTA: Error: Main method not found in class Hello
// JVM sinif yaratmır, ona görə də static lazımdır
```

### 6. `Scanner.close()` və System.in bağlamaq

```java
Scanner s = new Scanner(System.in);
s.close();  // System.in də bağlanır!

Scanner s2 = new Scanner(System.in);
s2.nextLine();  // NoSuchElementException! System.in artıq bağlıdır
```

### 7. Exit code yanlış yaddaşda saxlamaq

```java
// YANLIŞ: Java-də main void qaytarır
public static int main(String[] args) {
    return 0;  // KOMPILYASIYA XƏTASI
}

// DÜZGÜN: System.exit() istifadə edin
public static void main(String[] args) {
    System.exit(0);
}
```

---

## Mini müsahibə sualları

**S1: `public static void main(String[] args)` — niyə hər söz lazımdır?**

C: 
- `public` — JVM başqa paketdən bu metodu çağırmalıdır, ona görə görünən olmalıdır.
- `static` — JVM obyekt yaratmadan birbaşa metodu çağırır, instance lazım deyil.
- `void` — Java-də `main` dəyər qaytarmır; exit code üçün `System.exit()` istifadə olunur.
- `String[] args` — command-line argumentlər. JVM bu imzanı axtarır.

Əgər bu imzanın hər hansı hissəsi fərqli olsa, `Error: Main method not found` xətası alınır.

**S2: `java Hello` və `java Hello.class` — hansı düzgündür?**

C: `java Hello` (uzantısız) düzgündür. `java` komandası sinif adı gözləyir, fayl adı yox. Daxili olaraq `Hello.class` faylını axtarır və tapır. `java Hello.class` yazsanız, "Error: Could not find or load main class Hello.class" alarsınız — çünki "Hello.class" adlı sinif axtarır.

**S3: Java-də `main` metodunu overload etmək mümkündürmü?**

C: Bəli, overload etmək mümkündür — Java overloading qaydalarına görə. Amma JVM yalnız `public static void main(String[] args)` imzalı metodu axtarır. Başqa imzalı `main` metodlar sadəcə adi metod kimi qalır və JVM tərəfindən çağırılmır. Məsələn:

```java
public class Test {
    public static void main(String[] args) {  // JVM bunu çağırır
        main(42);  // başqa main-i biz özümüz çağırırıq
    }
    
    public static void main(int x) {          // overload
        System.out.println("int main: " + x);
    }
}
```

**S4: PHP-də `main` niyə yoxdur, Java-də niyə var?**

C: PHP **interpreted** və **web-centric** dildir — ilkin məqsədi HTML faylının içinə dinamik kod yazmaq idi (`<?php echo $ad; ?>`). Web server PHP faylını oxuyur və yuxarıdan aşağı icra edir — bir giriş nöqtəsinə ehtiyac yoxdur. Java isə **compiled** və **standalone application** üçün yaradılıb. Bir JAR faylında yüzlərlə sinif ola bilər — JVM hansından başlayacağını bilməlidir, buna görə də `main` metodu konvensiyadır. PHP-də bu rolu əslində fayl özü oynayır — `php index.php` işə salınca `index.php` faylının ilk sətri "main" sayılır.
