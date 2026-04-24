# try-with-resources və AutoCloseable (Resursların Təhlükəsiz Bağlanması)

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Proqramlar tez-tez **xarici resurslar**la işləyir: fayllar, verilənlər bazası bağlantıları, şəbəkə socket-ləri, axınlar (stream). Bu resursların **hər biri açıldıqdan sonra bağlanmalıdır**, yoxsa yaddaş sızıntısı (memory leak), fayl kilidlənməsi və ya bağlantı hovuzu (connection pool) tükənməsi baş verir.

Java 7-dən əvvəl bunun üçün `try-catch-finally` istifadə olunurdu, amma bu çox uzun və səhvə meylli idi. Java 7-də **try-with-resources** sintaksisi gəldi -- resursları avtomatik bağlayır. Bu iş üçün sinif `AutoCloseable` (və ya `Closeable`) interfeysini implement etməlidir.

PHP-də belə bir mexanizm **yoxdur**. PHP-də resurslar `__destruct` metodu ilə avtomatik təmizlənir və ya `fclose()`, `mysqli_close()` kimi funksiyaları əl ilə çağırmaq lazımdır.

---

## Java-də istifadəsi

### Klassik try-catch-finally (Java 7-dən əvvəl)

Resursu düzgün bağlamaq üçün `finally` bloku istifadə olunurdu:

```java
import java.io.*;

public class KlassikOxumaq {
    public static String oxu(String yol) throws IOException {
        FileInputStream fis = null;
        BufferedReader br = null;
        try {
            fis = new FileInputStream(yol);
            br = new BufferedReader(new InputStreamReader(fis));
            return br.readLine();
        } finally {
            // Hər resursu ayrı-ayrı bağlamalıyıq
            if (br != null) {
                try {
                    br.close();
                } catch (IOException e) {
                    // Susdur və ya logla
                }
            }
            if (fis != null) {
                try {
                    fis.close();
                } catch (IOException e) {
                    // Susdur və ya logla
                }
            }
        }
    }
}
```

**Problemlər:**

1. Çox kod -- hər resurs üçün `null` yoxlama, try-catch, close.
2. `br.close()` xəta atsa, `fis.close()` çağırılmaya bilər (resurs sızıntısı).
3. `br` və `fis` yenidən istifadə oluna bilər -- hətta `null` olsa belə.
4. Əsas xəta `finally`-dəki xəta ilə örtülə bilər.

### try-with-resources (Java 7+)

Java 7 bunu həll etdi. Resursları `try` blokunun mötərizələrində elan edin -- avtomatik bağlanır:

```java
import java.io.*;

public class YeniOxumaq {
    public static String oxu(String yol) throws IOException {
        try (FileInputStream fis = new FileInputStream(yol);
             BufferedReader br = new BufferedReader(new InputStreamReader(fis))) {
            return br.readLine();
        }
        // Burada br və fis avtomatik close() olunub, hətta xəta olsa belə.
    }
}
```

Kompilyator bunu arxa planda klassik variantın təkmilləşdirilmiş versiyasına çevirir. Kod 3 dəfə qısaldı və daha təhlükəsizdir.

### AutoCloseable interfeysi

`try-with-resources` yalnız `AutoCloseable` interfeysini implement edən siniflərlə işləyir:

```java
public interface AutoCloseable {
    void close() throws Exception;
}
```

`Closeable` isə `AutoCloseable`-dan miras alır və yalnız `IOException` atır:

```java
public interface Closeable extends AutoCloseable {
    void close() throws IOException;
}
```

**Qayda:** Standart kitabxanada fayl, socket, stream və DB ilə bağlı olan bütün siniflər bu interfeyslərdən birini implement edir. Məsələn:

| Sinif | Interfeys |
|-------|-----------|
| FileInputStream / FileOutputStream | Closeable |
| BufferedReader / PrintWriter | Closeable |
| Socket | Closeable |
| java.sql.Connection | AutoCloseable |
| java.sql.Statement | AutoCloseable |
| java.sql.ResultSet | AutoCloseable |
| Scanner | Closeable |

### Çoxlu resurs -- nöqtəli vergül ilə

Bir dənə `try`-da birdən çox resurs elan edə bilərsiniz, onlar nöqtəli vergüllə ayrılır. **Bağlanma tərs qaydada** baş verir -- yəni son açılan ilk bağlanır:

```java
import java.sql.*;

public class DBSorgu {
    public static void sorgu(String url) throws SQLException {
        try (Connection conn = DriverManager.getConnection(url);
             PreparedStatement ps = conn.prepareStatement("SELECT * FROM users WHERE id = ?");
             ResultSet rs = ps.executeQuery()) {

            while (rs.next()) {
                System.out.println(rs.getString("name"));
            }
        }
        // Bağlanma qaydası: 1) rs, 2) ps, 3) conn
    }
}
```

Bu "LIFO" (last-in, first-out) qaydası vacibdir -- çünki asılılıqları vardır (rs `ps`-siz yaşaya bilməz).

### Suppressed exceptions (Yatırdılmış xətalar)

Çətin məsələ: əgər `try` bloku xəta atsa, **sonra** `close()` də xəta atsa, hansı xəta aktiv olur?

Java-nın cavabı: əsas xəta (try bloku-nun atdığı) qalır, `close()` xətası isə **suppressed** (yatırılmış) siyahısına əlavə olunur. Belə görə bilərsiniz:

```java
public class XətaTəhlili {
    public static void main(String[] args) {
        try {
            test();
        } catch (Exception e) {
            System.out.println("Əsas xəta: " + e.getMessage());
            for (Throwable t : e.getSuppressed()) {
                System.out.println("Yatırılmış: " + t.getMessage());
            }
        }
    }

    static void test() throws Exception {
        try (Resurs r = new Resurs()) {
            throw new RuntimeException("try daxilində xəta");
        }
    }

    static class Resurs implements AutoCloseable {
        public void close() {
            throw new RuntimeException("close zamanı xəta");
        }
    }
}
// Çıxış:
// Əsas xəta: try daxilində xəta
// Yatırılmış: close zamanı xəta
```

Klassik try-finally-də `close()` xətası əsas xətanı **udardı** (swallow). Yeni sintaksis hər iki xətanı saxlayır.

### Effectively final resurs (Java 9+)

Java 7 və 8-də resurs məcburi şəkildə `try`-ın içində elan olunmalıydı. Java 9-dan etibarən, əgər resurs **effectively final**-dirsə (yəni təyin edildikdən sonra dəyişdirilmirsə), onu xaricdə elan edib `try` içində istifadə edə bilərsiniz:

```java
// Java 7/8 -- məcburi şəkildə try içində
try (BufferedReader br = new BufferedReader(new FileReader("a.txt"))) {
    // ...
}

// Java 9+ -- effectively final olarsa, xaricdə elan oluna bilər
BufferedReader br = new BufferedReader(new FileReader("a.txt"));
try (br) {   // br sadəcə qeyd olunur, yenidən elan olunmur
    // ...
}
```

### Real nümunələr

**Fayl oxumaq və yazmaq:**

```java
import java.io.*;
import java.nio.file.*;

public class FaylNümunə {
    // Oxumaq
    static String oxu(String yol) throws IOException {
        try (BufferedReader br = Files.newBufferedReader(Paths.get(yol))) {
            StringBuilder sb = new StringBuilder();
            String sətir;
            while ((sətir = br.readLine()) != null) {
                sb.append(sətir).append('\n');
            }
            return sb.toString();
        }
    }

    // Yazmaq
    static void yaz(String yol, String məzmun) throws IOException {
        try (PrintWriter pw = new PrintWriter(Files.newBufferedWriter(Paths.get(yol)))) {
            pw.println(məzmun);
        }
    }
}
```

**Verilənlər bazası:**

```java
import java.sql.*;

public class DBNümunə {
    static void istifadəçiTap(String url, int id) throws SQLException {
        String sql = "SELECT ad, yas FROM istifadeciler WHERE id = ?";
        try (Connection conn = DriverManager.getConnection(url);
             PreparedStatement ps = conn.prepareStatement(sql)) {

            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    System.out.println(rs.getString("ad") + " " + rs.getInt("yas"));
                }
            }
        }
    }
}
```

**Socket:**

```java
import java.net.*;
import java.io.*;

public class ServerNümunə {
    static void qəbul(int port) throws IOException {
        try (ServerSocket server = new ServerSocket(port);
             Socket client = server.accept();
             BufferedReader in = new BufferedReader(new InputStreamReader(client.getInputStream()));
             PrintWriter out = new PrintWriter(client.getOutputStream(), true)) {

            String mesaj = in.readLine();
            out.println("Echo: " + mesaj);
        }
    }
}
```

### Custom AutoCloseable

Öz resursunuzu da yarada bilərsiniz. Bu məsələn, tətbiqdə "transaction" (tranzaksiya) və ya "timer" ola bilər:

```java
public class Timer implements AutoCloseable {
    private final String ad;
    private final long başlanğıc;

    public Timer(String ad) {
        this.ad = ad;
        this.başlanğıc = System.currentTimeMillis();
        System.out.println("[" + ad + "] başladı");
    }

    @Override
    public void close() {
        long müddət = System.currentTimeMillis() - başlanğıc;
        System.out.println("[" + ad + "] bitdi: " + müddət + "ms");
    }
}

// İstifadəsi:
public class Demo {
    public static void main(String[] args) {
        try (Timer t = new Timer("DB sorğu")) {
            // ağır iş
            Thread.sleep(500);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
        // Çıxış: [DB sorğu] başladı ... [DB sorğu] bitdi: 500ms
    }
}
```

`close()`-un yalnız resursları bağlamaq üçün istifadə edilməsi şərt deyil -- istənilən "təmizlik" işi üçün yaxşıdır.

### Exception qaydaları -- close() metodunda

`AutoCloseable.close()` `Exception` atır, amma bunu praktikada daha dar bir xəta tipinə dəyişmək olar:

```java
public class Resurs implements AutoCloseable {
    @Override
    public void close() throws IOException {   // daha dar tip OK
        // ...
    }
}

public class Resurs2 implements AutoCloseable {
    @Override
    public void close() {   // heç bir xəta atmır -- OK
        // ...
    }
}
```

---

## PHP-də istifadəsi

PHP-də **try-with-resources yoxdur**. Resursları `finally` blokunda əl ilə bağlamaq, yaxud `__destruct` metoduna güvənmək lazımdır.

### finally ilə əl ilə bağlama

```php
<?php
function oxu(string $yol): string {
    $fh = fopen($yol, 'r');
    if (!$fh) {
        throw new RuntimeException("Fayl açılmadı");
    }
    try {
        return fread($fh, 1024);
    } finally {
        fclose($fh);  // Hər halda bağla
    }
}
```

### __destruct ilə avtomatik təmizləmə

PHP obyekti referans sayı sıfır olanda yaddaşdan silinir və `__destruct` metodu çağırılır:

```php
<?php
class FaylOxuyucu {
    private $fh;

    public function __construct(string $yol) {
        $this->fh = fopen($yol, 'r');
        if (!$this->fh) {
            throw new RuntimeException("Fayl açılmadı");
        }
    }

    public function oxu(int $uzunluq): string {
        return fread($this->fh, $uzunluq);
    }

    public function __destruct() {
        if ($this->fh) {
            fclose($this->fh);
            echo "Fayl bağlandı\n";
        }
    }
}

function istifadə(): void {
    $oxuyucu = new FaylOxuyucu("data.txt");
    echo $oxuyucu->oxu(100);
    // $oxuyucu scope-dan çıxır, __destruct avtomatik çağırılır
}
```

**Diqqət:** `__destruct`-un nə vaxt çağırılacağı **dəqiq məlum deyil**. Referans sayı sıfır olmalıdır. Əgər obyekt başqa yerdə tutulursa, təmizlik gecikir.

### PDO nümunəsi

```php
<?php
function istifadəçiTap(string $dsn, int $id): ?array {
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $stmt = $pdo->prepare("SELECT ad, yas FROM istifadeciler WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } finally {
        $stmt = null;   // statement-i "bağla"
        $pdo = null;    // bağlantını "bağla"
    }
}
```

PDO obyektini `null` etmək "bağlama"-nı tetikləyir (əgər başqa yerdə tutulmursa).

### Laravel -- Facade ilə

Laravel-də DB bağlantısı çərçivə tərəfindən idarə olunur, siz açıb-bağlamırsınız:

```php
<?php
use Illuminate\Support\Facades\DB;

$istifadəçi = DB::table('users')->where('id', $id)->first();
// Laravel connection pool ilə arxa planda işləyir, siz close() çağırmırsınız.
```

---

## Əsas fərqlər

| Xüsusiyyət | Java | PHP |
|---|---|---|
| Sintaksis | `try (...) { }` -- qısa | `try { } finally { }` -- uzun |
| Avtomatik bağlama | Var (try-with-resources) | Yox (əl ilə və ya __destruct) |
| Interfeys | AutoCloseable / Closeable | Yoxdur |
| Çoxlu resurs | `;` ilə ayrılır | Hər birini əl ilə bağlayırsınız |
| Bağlama qaydası | LIFO (tərs qayda) | __destruct sırası qeyri-müəyyən |
| Suppressed exceptions | Var, getSuppressed() | Yoxdur |
| Effectively final | Java 9+ | Əsas yoxdur |
| Yaddaş idarəetməsi | GC -- qeyri-müəyyən vaxt | Reference counting -- daha dəqiq |
| DB pool | Əl ilə conn.close() | Framework idarə edir (Laravel) |
| Fayl bağlama | br.close() / try-with | fclose() əl ilə |

---

## Niyə belə fərqlər var?

### Java: determinizm və təhlükəsizlik

Java-da **Garbage Collector (GC)** obyektləri avtomatik silir, amma **nə vaxt** -- bilinmir. GC obyektin `finalize()` metodunu çağıra bilər, amma bu gecikə və ya heç baş verməyə bilər. Buna görə Java fayl/socket kimi "deterministic" bağlama tələb edən resurslar üçün **əl ilə close()** istəyir.

Java 7-dən əvvəl bu əllə bağlama çox boilerplate kod yaradırdı. `try-with-resources` həm qısa sintaksis, həm də suppressed exceptions kimi əlavə təhlükəsizlik verir. Bu, böyük layihələrdə resurs sızıntılarını dramatik azaldıb.

`AutoCloseable` interfeysinin olması isə **standartlaşdırma** deməkdir -- hər hansı üçüncü tərəf sinif də bu interfeysi implement etsə, `try-with-resources` ilə işləyə bilər.

### PHP: reference counting və scripting modeli

PHP **reference counting** GC istifadə edir. Obyektin referans sayı sıfıra düşən kimi `__destruct` çağırılır. Bu deterministikdir və çox hallarda bağlama avtomatik baş verir.

Həm də PHP-nin klassik iş modeli **qısa ömürlü skript**-dir: hər HTTP sorğu üçün PHP başlayır, işini görür, hər şey təmizlənir və ölür. Uzunmüddətli prosesdə yaşayan Java-dan fərqli olaraq, PHP-də resurs sızıntısı böyük problem deyildi.

Amma müasir PHP (Swoole, RoadRunner, Octane, FrankenPHP) uzunmüddətli prosesdə işləyir və burada sızıntılar ciddiləşir. Buna görə explicit `finally` və ya obyekt-yönümlü resurs idarəetməsi getdikcə vacibləşir.

PHP-də `try-with-resources` kimi sintaksis təklif olunub (RFC), amma hələ dilə əlavə edilməyib.

### Praktik nəticə

- Java-da fayl/DB ilə işləyərkən **həmişə** `try-with-resources` istifadə edin. Klassik try-finally artıq köhnədir.
- PHP-də PDO, curl handle, fopen resursları üçün `finally` blokunda bağlama və ya obyekt daxilində `__destruct` istifadə edin.
- Framework istifadə etsəniz (Laravel, Symfony), pool idarəetməsi çərçivə tərəfindən aparılır.

---

## Ümumi səhvlər (Beginner traps)

### 1. try-with-resources unutmaq

```java
// PIS -- resurs leak
BufferedReader br = new BufferedReader(new FileReader("a.txt"));
String line = br.readLine();
br.close();  // əgər readLine() xəta atsa, bu çağırılmır!

// DOGRU
try (BufferedReader br = new BufferedReader(new FileReader("a.txt"))) {
    String line = br.readLine();
}
```

### 2. Nested resurslar -- səhv sıra

```java
// PIS -- əgər conn yaradıla bilərsə, amma ps yaradıla bilməzsə,
// conn-ı bağlamaq üçün ayrı-ayrı try lazım idi
Connection conn = DriverManager.getConnection(url);
PreparedStatement ps = conn.prepareStatement(sql);

// DOGRU -- hamısı try-with-resources-də
try (Connection conn = DriverManager.getConnection(url);
     PreparedStatement ps = conn.prepareStatement(sql)) {
    // ...
}
```

### 3. close() metodunda xəta udmaq

```java
// PIS
@Override
public void close() {
    try {
        faylBagla();
    } catch (IOException e) {
        // heç nə etməmək -- susdurma!
    }
}

// DOGRU
@Override
public void close() throws IOException {
    faylBagla();
}
```

### 4. `null` resursu try-with-də istifadə

```java
// PIS -- `resurs` null olarsa NullPointerException atmadan close çağırılır, amma dəyər null ola bilər
Resurs resurs = null;
if (şərt) resurs = new Resurs();
try (resurs) { ... }  // əgər null olarsa, NPE olmadan keçir, amma içəridə istifadə etsək NPE olar

// DOGRU -- şərti ayrı yoxla
if (şərt) {
    try (Resurs r = new Resurs()) { ... }
}
```

### 5. __destruct-a həddindən çox güvənmək (PHP)

```php
// PIS -- exception atılsa, __destruct gecikir
$r = new Resurs();
$r->iş();
// əgər istisna atılırsa, obyekt hələ də stack-də qala bilər

// DOGRU
$r = new Resurs();
try {
    $r->iş();
} finally {
    $r->close();
}
```

### 6. Çoxlu fayl açıb heç birini bağlamamaq

```java
// PIS
for (String yol : yollar) {
    BufferedReader br = new BufferedReader(new FileReader(yol));
    // oxu, amma heç vaxt bağlanmır
}

// DOGRU
for (String yol : yollar) {
    try (BufferedReader br = new BufferedReader(new FileReader(yol))) {
        // oxu
    }
}
```

---

## Mini müsahibə sualları

**Sual 1:** try-with-resources istifadə etmək üçün resurs sinfi hansı interfeysi implement etməlidir? Fərqi nədir?

*Cavab:* `AutoCloseable` və ya `Closeable` interfeysi. `AutoCloseable` (Java 7+) daha ümumidir və `close()` metodu `Exception` atır. `Closeable` (Java 5+, I/O üçün) `AutoCloseable`-dan miras alır və yalnız `IOException` atır. Standart I/O sinifləri (FileInputStream, BufferedReader) `Closeable`-dir; DB sinifləri (Connection, Statement) `AutoCloseable`-dir.

**Sual 2:** try-with-resources-də birdən çox resurs olanda hansı sıra ilə bağlanır? Niyə?

*Cavab:* Açılmanın **tərs sırası** ilə (LIFO -- last-in, first-out). Yəni son açılan ilk bağlanır. Səbəb: resurslar çox vaxt bir-birindən asılı olur. Məsələn, `ResultSet` `PreparedStatement`-ə, o da `Connection`-a bağlıdır. Əvvəlcə `ResultSet`-i bağlamaq lazımdır, sonra `PreparedStatement`, axırda `Connection`.

**Sual 3:** "Suppressed exception" nədir? `try` bloku və `close()` eyni anda xəta atsa nə olur?

*Cavab:* try bloku-nun atdığı xəta **əsas** sayılır və üstə qalxır. `close()`-un atdığı xəta isə bu əsas xətanın `getSuppressed()` siyahısına **yatırılmış xəta** kimi əlavə olunur. Klassik try-finally-də `close()`-un xətası əsas xətanı "udardı" -- yeni sintaksis hər ikisini saxlayır, debug üçün çox faydalıdır. `throwable.getSuppressed()` Throwable[] qaytarır.
