# 028 — Exception Best Practices
**Səviyyə:** Orta


## Mündəricat
1. [try-with-resources](#try-with-resources)
2. [Multi-catch](#multi-catch)
3. [finally Bloku](#finally-bloku)
4. [Exception Chaining](#exception-chaining)
5. [Custom Exceptions](#custom-exceptions)
6. [Exception-ları Udmaq — Pis Praktika](#exception-ları-udmaq--pis-praktika)
7. [Throwable/Error-u Catch Etmək](#throwableerror-u-catch-etmək)
8. [Mənalı Mesajlar](#mənalı-mesajlar)
9. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## try-with-resources

**try-with-resources** — `AutoCloseable` interface-ini implement edən resursları avtomatik bağlamaq üçün (Java 7+).

### YANLIŞ — Manual close (yaddaş/resurs sızdırma riski)

```java
import java.io.*;

public class BadResourceHandling {

    // YANLIŞ — exception baş versə stream bağlanmır!
    public static void readFileBad(String path) throws IOException {
        FileInputStream fis = new FileInputStream(path);
        byte[] data = fis.read(); // Exception baş versə...
        fis.close(); // Bu sətir heç vaxt çağırılmaya bilər!
    }

    // YANLIŞ — finally-də də problem var (close özü exception atsa?)
    public static void readFileStillBad(String path) throws IOException {
        FileInputStream fis = null;
        try {
            fis = new FileInputStream(path);
            // fis.read() exception atsa → finally-ə keçər
        } finally {
            if (fis != null) {
                fis.close(); // Bu da exception ata bilər!
                // İki exception var: original + close exception
                // Original exception udulur!
            }
        }
    }
}
```

### DOĞRU — try-with-resources

```java
import java.io.*;
import java.nio.file.*;

public class GoodResourceHandling {

    // DOĞRU — resurs avtomatik bağlanır, exception olsa belə
    public static String readFile(String path) throws IOException {
        try (FileInputStream fis = new FileInputStream(path);
             BufferedReader reader = new BufferedReader(new InputStreamReader(fis))) {
            // try bloku bitdikdə (normal ya da exception) reader.close(), sonra fis.close() çağırılır
            // LIFO sırası ilə: əvvəl reader, sonra fis
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line).append('\n');
            }
            return sb.toString();
        }
        // close() zamanı exception baş versə → "suppressed exception" kimi saxlanır
    }

    // try-with-resources-da suppressed exception-ları görməq
    public static void suppressedExceptionDemo() {
        try {
            try (AutoCloseable resource = () -> {
                throw new Exception("Close zamanı exception!");
            }) {
                throw new RuntimeException("İş zamanı exception!"); // Bu birincidən gedir
            }
        } catch (Exception e) {
            System.out.println("Əsas: " + e.getMessage()); // İş zamanı exception!
            Throwable[] suppressed = e.getSuppressed();
            for (Throwable t : suppressed) {
                System.out.println("Suppressed: " + t.getMessage()); // Close zamanı exception!
            }
        }
    }

    // Custom AutoCloseable
    static class DatabaseConnection implements AutoCloseable {
        private final String url;
        private boolean closed = false;

        DatabaseConnection(String url) {
            this.url = url;
            System.out.println("Bağlantı açıldı: " + url);
        }

        public void query(String sql) {
            if (closed) throw new IllegalStateException("Bağlantı bağlıdır!");
            System.out.println("Sorğu: " + sql);
        }

        @Override
        public void close() {
            if (!closed) {
                closed = true;
                System.out.println("Bağlantı bağlandı: " + url);
            }
        }
    }

    public static void main(String[] args) throws IOException {
        // try-with-resources ilə custom resource
        try (DatabaseConnection conn = new DatabaseConnection("jdbc:postgresql://localhost/db")) {
            conn.query("SELECT * FROM users");
            // Bağlantı blok bitdikdə avtomatik bağlanır
        } // conn.close() burada çağırılır

        // Java 9+: Əvvəlcədən elan olunmuş dəyişənlər (effectively final)
        DatabaseConnection conn2 = new DatabaseConnection("jdbc:postgresql://localhost/db2");
        try (conn2) { // conn2-ni try-with-resources-da istifadə et
            conn2.query("SELECT * FROM products");
        }

        // Fayl kopyalama nümunəsi
        try (InputStream in = new FileInputStream("/tmp/input.txt");
             OutputStream out = new FileOutputStream("/tmp/output.txt")) {
            in.transferTo(out); // Java 9+
        }
    }
}
```

---

## Multi-catch

**Multi-catch** — bir catch blokunda bir neçə exception növü tutmaq (Java 7+).

```java
import java.io.*;
import java.sql.*;

public class MultiCatchDemo {

    // YANLIŞ — eyni kodu təkrarlayan catch blokları
    public static void badApproach(String path) {
        try {
            processFile(path);
        } catch (IOException e) {
            System.err.println("Xəta baş verdi: " + e.getMessage()); // Eyni kod
            logError(e);
        } catch (SQLException e) {
            System.err.println("Xəta baş verdi: " + e.getMessage()); // Eyni kod
            logError(e);
        }
    }

    // DOĞRU — multi-catch ilə (Java 7+)
    public static void goodApproach(String path) {
        try {
            processFile(path);
        } catch (IOException | SQLException e) {
            // e tipi: IOException | SQLException (effective union type)
            System.err.println("Xəta baş verdi: " + e.getMessage());
            logError(e);
            // e = new IOException(); // YANLIŞ — multi-catch dəyişəni effectively final-dır!
        }
    }

    // Multi-catch ilə fərqli davranış lazımdırsa ayrı catch-lər
    public static void mixedApproach(String path) {
        try {
            processFile(path);
        } catch (FileNotFoundException e) {
            System.err.println("Fayl tapılmadı: " + path);
            createDefaultFile(path); // Spesifik davranış
        } catch (IOException | SQLException e) {
            System.err.println("Xəta: " + e.getMessage());
            logError(e);
        }
    }

    // Qayda: Daha spesifik catch blokları əvvəl gəlməlidir!
    public static void orderMatters() {
        try {
            throw new FileNotFoundException("test.txt");
        } catch (FileNotFoundException e) {  // Spesifik — əvvəl
            System.out.println("FileNotFoundException tutuldu");
        } catch (IOException e) {            // Ümumi — sonra
            System.out.println("IOException tutuldu");
        }

        // YANLIŞ — kompilasiya xətası! IOException FileNotFoundException-ı əhatə edir
        // try { ... }
        // catch (IOException e) { ... }       // Əvvəl ümumi
        // catch (FileNotFoundException e) { } // Sonra spesifik — DEAD CODE, compile xətası!
    }

    static void processFile(String path) throws IOException, SQLException { }
    static void logError(Throwable t) { }
    static void createDefaultFile(String path) { }
}
```

---

## finally Bloku

```java
public class FinallyDemo {

    // finally həmişə icra olunur (return olsa belə!)
    public static int alwaysFinally() {
        try {
            System.out.println("try bloku");
            return 1; // return olsa da...
        } finally {
            System.out.println("finally bloku"); // Bu icra olunur!
            // return 2; // YANLIŞ — finally-dəki return try-dəkini əvəz edir!
        }
        // Çıxış:
        // try bloku
        // finally bloku
        // qaytarılan: 1
    }

    // finally içindən return — pis praktika!
    public static int badFinally() {
        try {
            throw new RuntimeException("Xəta!"); // Exception atılır
        } finally {
            return 42; // Exception udulur! (BAD!)
        }
        // Bu metod 42 qaytarır, exception görünmür!
    }

    // finally exception vs try exception
    public static void exceptionInFinally() {
        try {
            throw new RuntimeException("Try exception");
        } finally {
            throw new RuntimeException("Finally exception"); // Try exception udulur!
            // Caller yalnız "Finally exception" görür
        }
    }

    // DOĞRU finally istifadəsi — resurs azad etmə (try-with-resources yoxdursa)
    public static void correctFinally() {
        java.io.InputStream is = null;
        try {
            is = new java.io.FileInputStream("/tmp/test.txt");
            // işlər
        } catch (java.io.IOException e) {
            System.err.println("Xəta: " + e.getMessage());
        } finally {
            if (is != null) {
                try {
                    is.close();
                } catch (java.io.IOException closeEx) {
                    System.err.println("Bağlama xətası: " + closeEx.getMessage());
                }
            }
        }
        // NOT: try-with-resources bunu daha yaxşı edir!
    }

    // finally işləməyən hал: System.exit()
    public static void noFinally() {
        try {
            System.out.println("try");
            System.exit(0); // JVM dayandırılır — finally işləmir!
        } finally {
            System.out.println("Bu çıxmır!"); // Heç vaxt görünmür
        }
    }

    public static void main(String[] args) {
        System.out.println("Nəticə: " + alwaysFinally());
        System.out.println("Bad finally: " + badFinally());
    }
}
```

---

## Exception Chaining

**Exception Chaining** — bir exception-u digərinin içinə sarıb (wrap) ötürmək. Orijinal səbəb itmir.

```java
import java.io.*;
import java.sql.*;

public class ExceptionChainingDemo {

    // YANLIŞ — orijinal exception itir!
    public static void badWrapping(String path) throws RuntimeException {
        try {
            new FileInputStream(path);
        } catch (FileNotFoundException e) {
            throw new RuntimeException("Fayl emalı xətası"); // e itdi!
            // Stack trace-də FileNotFoundException görünmür
        }
    }

    // DOĞRU — cause parametri ilə
    public static void goodWrapping(String path) throws RuntimeException {
        try {
            new FileInputStream(path);
        } catch (FileNotFoundException e) {
            throw new RuntimeException("Fayl emalı xətası: " + path, e); // e saxlandı!
            // Stack trace-də həm RuntimeException, həm də FileNotFoundException görünür
        }
    }

    // initCause() metodu
    public static void initCauseMethod(String path) {
        try {
            new FileInputStream(path);
        } catch (FileNotFoundException e) {
            RuntimeException ex = new RuntimeException("Fayl tapılmadı");
            ex.initCause(e); // Cause-u sonradan set etmək
            throw ex;
        }
    }

    // Exception chain-i analiz etmək
    public static void analyzeChain(Throwable t) {
        System.out.println("Exception: " + t.getClass().getSimpleName() + ": " + t.getMessage());
        Throwable cause = t.getCause();
        while (cause != null) {
            System.out.println("  Caused by: " + cause.getClass().getSimpleName()
                + ": " + cause.getMessage());
            cause = cause.getCause();
        }
    }

    // Layered architecture nümunəsi
    static class DatabaseException extends RuntimeException {
        public DatabaseException(String message, Throwable cause) {
            super(message, cause);
        }
    }

    static class ServiceException extends RuntimeException {
        public ServiceException(String message, Throwable cause) {
            super(message, cause);
        }
    }

    static void repositoryMethod() throws SQLException {
        throw new SQLException("Verilənlər bazası bağlantısı itdi");
    }

    static void serviceMethod() {
        try {
            repositoryMethod();
        } catch (SQLException e) {
            // Repository exception-ını Service exception-ına çevir
            throw new ServiceException("İstifadəçi məlumatları alına bilmədi", e);
        }
    }

    static void controllerMethod() {
        try {
            serviceMethod();
        } catch (ServiceException e) {
            System.err.println("API xətası: " + e.getMessage());
            analyzeChain(e);
            // Stack trace-də tam zəncir görünür:
            // ServiceException → DatabaseException → SQLException
        }
    }

    public static void main(String[] args) {
        controllerMethod();
    }
}
```

---

## Custom Exceptions

```java
// Checked Custom Exception
public class InsufficientFundsException extends Exception {

    private final double amount;
    private final double balance;

    // 1. Mesajlı constructor
    public InsufficientFundsException(String message) {
        super(message);
        this.amount = 0;
        this.balance = 0;
    }

    // 2. Kontekstli constructor (tövsiyə olunan)
    public InsufficientFundsException(double amount, double balance) {
        super(String.format("Balans kifayətsizdir: tələb edilən %.2f, mövcud %.2f",
            amount, balance));
        this.amount = amount;
        this.balance = balance;
    }

    // 3. Cause ilə constructor (exception chaining üçün)
    public InsufficientFundsException(String message, Throwable cause) {
        super(message, cause);
        this.amount = 0;
        this.balance = 0;
    }

    // Kontekst məlumatlarını əldə etmək
    public double getAmount() { return amount; }
    public double getBalance() { return balance; }
}

// Unchecked Custom Exception
public class UserNotFoundException extends RuntimeException {

    private final Long userId;

    public UserNotFoundException(Long userId) {
        super("İstifadəçi tapılmadı: ID=" + userId);
        this.userId = userId;
    }

    public UserNotFoundException(Long userId, Throwable cause) {
        super("İstifadəçi tapılmadı: ID=" + userId, cause);
        this.userId = userId;
    }

    public Long getUserId() { return userId; }
}

// Exception hierarchy nümunəsi (əsas sinif)
public class AppException extends RuntimeException {
    private final String errorCode;

    public AppException(String errorCode, String message) {
        super(message);
        this.errorCode = errorCode;
    }

    public AppException(String errorCode, String message, Throwable cause) {
        super(message, cause);
        this.errorCode = errorCode;
    }

    public String getErrorCode() { return errorCode; }
}

// Xüsusi alt siniflər
class ValidationException extends AppException {
    private final String fieldName;

    public ValidationException(String field, String message) {
        super("VALIDATION_ERROR", message);
        this.fieldName = field;
    }

    public String getFieldName() { return fieldName; }
}

class BusinessException extends AppException {
    public BusinessException(String errorCode, String message) {
        super(errorCode, message);
    }
}

// İstifadə nümunəsi
class BankService {
    private double balance = 100.0;

    public void withdraw(double amount) throws InsufficientFundsException {
        if (amount <= 0) {
            throw new IllegalArgumentException("Məbləğ müsbət olmalıdır: " + amount);
        }
        if (amount > balance) {
            throw new InsufficientFundsException(amount, balance);
        }
        balance -= amount;
    }
}
```

---

## Exception-ları Udmaq — Pis Praktika

```java
public class ExceptionSwallowingExamples {

    // YANLIŞ 1 — Boş catch bloku (silent failure)
    public static int badParse(String s) {
        try {
            return Integer.parseInt(s);
        } catch (NumberFormatException e) {
            // HEÇNƏ YOX! Exception uduldu, nə baş verdiyini bilmirik
        }
        return 0; // Yanlış 0 qaytarılır — çağırıcı xəbər tutmur
    }

    // YANLIŞ 2 — Yalnız printStackTrace (production-da yetərsiz)
    public static void badLogging() {
        try {
            processData();
        } catch (Exception e) {
            e.printStackTrace(); // Pis — structured logging yoxdur, itə bilər
        }
    }

    // YANLIŞ 3 — Exception-u udub boolean qaytarmaq
    public static boolean isValid(String data) {
        try {
            processData(data);
            return true;
        } catch (Exception e) {
            return false; // Exception məlumatı itdi!
        }
    }

    // DOĞRU 1 — loq yaz, yenidən at
    public static int goodParse(String s) {
        try {
            return Integer.parseInt(s);
        } catch (NumberFormatException e) {
            // Logger ilə log yaz (SLF4J/Logback)
            // logger.warn("Say parse edilə bilmədi: {}", s, e);
            System.err.println("Parse xətası: " + s + " — " + e.getMessage());
            return 0; // Mənalı default dəyər
        }
    }

    // DOĞRU 2 — yenidən at (re-throw)
    public static void goodRethrow() {
        try {
            processData();
        } catch (Exception e) {
            // logger.error("Process xətası", e);
            throw new RuntimeException("Məlumat emalında xəta", e); // Chain!
        }
    }

    // DOĞRU 3 — Specific exception handle et, ümumi-ni at
    public static void specificHandle() throws Exception {
        try {
            processData();
        } catch (NumberFormatException e) {
            System.err.println("Format xətası: " + e.getMessage());
            // Bu xətanı handle edə bilərik
        }
        // Digər exception-lar (IOException, SQLException...) yuxarıya ötür
    }

    static void processData() throws Exception { }
    static void processData(String data) throws Exception { }
}
```

---

## Throwable/Error-u Catch Etmək

```java
public class DontCatchThrowableDemo {

    // YANLIŞ — Throwable catch etmək
    public static void badThrowableCatch() {
        try {
            processWork();
        } catch (Throwable t) {  // Error-ları da tutur! (OutOfMemoryError, StackOverflow...)
            System.err.println("Xəta: " + t);
            // JVM ciddi vəziyyətdədir, amma davam edirik — çox təhlükəli!
        }
    }

    // YANLIŞ — Exception catch etmək (çox geniş)
    public static void tooWideCatch() {
        try {
            processWork();
        } catch (Exception e) { // RuntimeException + checked hər şeyi tutur
            System.err.println("Xəta: " + e);
            // Hansı exception olduğunu bilmirik — spesifik handle etmirik
        }
    }

    // DOĞRU — spesifik exception-ları tut
    public static void specificCatch() {
        try {
            processWork();
        } catch (java.io.IOException e) {
            System.err.println("I/O xətası: " + e.getMessage());
        } catch (java.sql.SQLException e) {
            System.err.println("DB xətası: " + e.getErrorCode() + " - " + e.getMessage());
        }
        // Digər exception-lar (RuntimeException) yuxarıya ötür
    }

    // Yalnız müəyyən hallarda Error catch etmək (top-level handler)
    public static void topLevelErrorHandler() {
        try {
            startApplication();
        } catch (OutOfMemoryError e) {
            // Kritik log yaz, resursu burax, JVM-ə çıx
            System.err.println("FATAL: Yaddaş bitmişdir!");
            // logCritical(e);
            System.exit(1); // Dərhal çıx
        } catch (Error e) {
            System.err.println("FATAL JVM xətası: " + e);
            System.exit(2);
        }
    }

    static void processWork() throws java.io.IOException, java.sql.SQLException { }
    static void startApplication() { }
}
```

---

## Mənalı Mesajlar

```java
public class MeaningfulMessages {

    // YANLIŞ mesajlar
    public static void badMessages() {
        // Mesajsız
        throw new IllegalArgumentException(); // Nə yanlışdır?

        // Çox ümumi
        // throw new RuntimeException("Xəta baş verdi"); // Hansı xəta?

        // Stack trace olmadan
        // throw new RuntimeException("null dəyər"); // Hansı null, haradan?
    }

    // DOĞRU mesajlar
    public static void goodMessages(String username, int age) {
        if (username == null) {
            // Kontekst: hansı parametr, niyə yanlış
            throw new IllegalArgumentException(
                "username null ola bilməz — istifadəçi qeydiyyatı üçün tələb olunur");
        }

        if (username.isBlank()) {
            throw new IllegalArgumentException(
                "username boş ola bilməz, verilən: '" + username + "'");
        }

        if (age < 0 || age > 150) {
            throw new IllegalArgumentException(
                String.format("Yaş [0, 150] aralığında olmalıdır, verilən: %d", age));
        }

        if (username.length() < 3) {
            throw new IllegalArgumentException(
                String.format("username minimum 3 simvol olmalıdır, '%s' (%d simvol)",
                    username, username.length()));
        }
    }

    // Objects.requireNonNull — standart null check
    public static void nullChecks(String name, java.util.List<?> items) {
        java.util.Objects.requireNonNull(name, "name null ola bilməz");
        java.util.Objects.requireNonNull(items, "items null ola bilməz");

        if (items.isEmpty()) {
            throw new IllegalArgumentException("items boş ola bilməz");
        }

        System.out.println("Parametrlər etibarlıdır: " + name + ", " + items.size() + " element");
    }

    // Guava kimi kitabxanalar da yardımçı olur
    // Preconditions.checkNotNull(obj, "message %s", param);
    // Preconditions.checkArgument(condition, "message");
    // Preconditions.checkState(condition, "message");

    public static void main(String[] args) {
        try {
            goodMessages(null, 25);
        } catch (IllegalArgumentException e) {
            System.out.println("Tutuldu: " + e.getMessage());
        }

        try {
            goodMessages("Ab", 25);
        } catch (IllegalArgumentException e) {
            System.out.println("Tutuldu: " + e.getMessage());
        }

        try {
            nullChecks("test", null);
        } catch (NullPointerException e) {
            System.out.println("NPE: " + e.getMessage());
        }
    }
}
```

---

## Exception Best Practices Xülasəsi

```java
public class ExceptionBestPracticesSummary {

    /*
     * QIZIL QAYDALAR:
     *
     * 1. try-with-resources istifadə et (AutoCloseable resurslar üçün)
     *
     * 2. Exception-ları udma — ya loq yaz, ya yenidən at, ya handle et
     *
     * 3. Exception chaining — cause-u həmişə saxla
     *    ✓ throw new ServiceException("mesaj", cause);
     *    ✗ throw new ServiceException("mesaj"); // cause itdi!
     *
     * 4. Spesifik exception tut — `catch (Exception e)` pis praktikadır
     *
     * 5. `Throwable`/`Error` catch etmə — yalnız top-level handler-də
     *
     * 6. finally-dən return etmə — exception udulur
     *
     * 7. Mənalı mesajlar — "null deyər" yox, "userId null ola bilməz"
     *
     * 8. Custom exception-larına kontekst əlavə et (field name, value, id)
     *
     * 9. Checked vs Unchecked doğru seçim:
     *    - I/O, network, DB → Checked
     *    - Proqramçı xətası → Unchecked (RuntimeException)
     *
     * 10. Lambda-larda checked exception problem yaradır
     *     → UncheckedIOException ya da wrapper helper istifadə et
     */

    // Ən yaxşı praktika nümunəsi
    public static String loadConfig(String configPath) {
        java.util.Objects.requireNonNull(configPath, "configPath null ola bilməz");

        try (java.io.BufferedReader reader = java.nio.file.Files.newBufferedReader(
                java.nio.file.Path.of(configPath))) {

            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line).append('\n');
            }
            return sb.toString();

        } catch (java.io.FileNotFoundException e) {
            throw new RuntimeException(
                "Konfiqurasiya faylı tapılmadı: " + configPath, e);
        } catch (java.io.IOException e) {
            throw new RuntimeException(
                "Konfiqurasiya faylı oxuna bilmədi: " + configPath, e);
        }
    }
}
```

---

## İntervyu Sualları

**S: try-with-resources nədir və niyə lazımdır?**
C: `AutoCloseable` resurslarını avtomatik bağlamaq üçün Java 7-də əlavə edildi. `finally`-dəki manual `close()` çağırışlarının problemini həll edir: exception olsa belə resurs bağlanır, `close()` exception-ı "suppressed exception" kimi saxlanır, original exception itmir.

**S: finally bloku nə zaman işləmir?**
C: `System.exit()` çağırıldıqda JVM dərhal dayanır, finally işləmir. `Runtime.getRuntime().halt()` da eyni effekt verir. JVM-in crash-ı (SIGSEGV, kill -9) da finally-ni atlayır.

**S: Exception chaining niyə vacibdir?**
C: Layered architecture-da (Controller → Service → Repository) alt qat exception-larını üst qat exception-larına wrap etmək lazımdır. Əgər cause-u saxlamasaq, debug üçün vacib stack trace məlumatı itir. `throw new ServiceException("msg", originalCause)` pattern-i istifadə et.

**S: Multi-catch-ı nə zaman istifadə etmək lazımdır?**
C: Bir neçə exception növünü eyni şəkildə handle etdikdə: `catch (IOException | SQLException e)`. Bu kod təkrarını azaldır. Multi-catch dəyişəni effectively final-dır — yenidən assign etmək olmaz.

**S: Boş catch blokunun nəyinlə əvəz olunmalıdır?**
C: 1) Loq yaz: `logger.error("Açıqlama", e)`, 2) Yenidən at: `throw new RuntimeException("kontekst", e)`, 3) Default dəyər qaytar (yalnız bunu etdiyini sənəklə), 4) Spesifik recovery məntiqi. Heç vaxt boş buraxma!

**S: Custom exception-da hansı constructor-lar olmalıdır?**
C: Minimum 4: String message, String message + Throwable cause, Throwable cause, parametrsiz (serializasiya üçün). Kontekst saxlayan (field adı, ID, dəyər) əlavə constructorlar da əlavə etmək yaxşıdır.

**S: `catch (Exception e)` niyə pis praktikadır?**
C: Həm checked, həm unchecked exception-ları tutur — bizi bilmədən spesifik davranışı uduruz. Başqa bir metod yeni exception atsa belə kompilasiya xətası vermir. Spesifik exception-lar tutmaq daha aydın, daha təhlükəsiz kod yazır.
