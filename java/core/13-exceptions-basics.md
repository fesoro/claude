# 13 — Exception-lar — Əsaslar

> **Seviyye:** Junior ⭐


## Mündəricat
1. [Throwable İerarxiyası](#throwable-i̇erarxiyası)
2. [Error vs Exception](#error-vs-exception)
3. [Checked Exceptions](#checked-exceptions)
4. [Unchecked Exceptions](#unchecked-exceptions)
5. [Ümumi Exception Növləri](#ümumi-exception-növləri)
6. [Nə Zaman Hansını İstifadə Etmək?](#nə-zaman-hansını-i̇stifadə-etmək)
7. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Throwable İerarxiyası

```
java.lang.Throwable
├── java.lang.Error                    (UNCHECKED)
│   ├── OutOfMemoryError
│   ├── StackOverflowError
│   ├── AssertionError
│   ├── VirtualMachineError
│   └── LinkageError
│       └── NoClassDefFoundError
│
└── java.lang.Exception                (ROOT)
    ├── IOException                    (CHECKED)
    │   ├── FileNotFoundException
    │   ├── SocketException
    │   └── EOFException
    ├── SQLException                   (CHECKED)
    ├── CloneNotSupportedException     (CHECKED)
    ├── InterruptedException           (CHECKED)
    │
    └── RuntimeException               (UNCHECKED)
        ├── NullPointerException
        ├── ArrayIndexOutOfBoundsException
        │   └── (IndexOutOfBoundsException)
        ├── ClassCastException
        ├── IllegalArgumentException
        │   └── NumberFormatException
        ├── IllegalStateException
        ├── UnsupportedOperationException
        ├── ArithmeticException
        ├── ConcurrentModificationException
        └── StackOverflowError (bəzən RuntimeException kimi görünür)
```

```java
public class ThrowableHierarchyDemo {
    public static void main(String[] args) {
        // Throwable-in hər şeyin əcdadı olduğunu sübut etmək
        Throwable t1 = new Exception("Adi exception");
        Throwable t2 = new Error("JVM xətası");
        Throwable t3 = new RuntimeException("Runtime xətası");
        Throwable t4 = new OutOfMemoryError("Yaddaş bitmişdir");

        System.out.println(t1 instanceof Throwable); // true
        System.out.println(t2 instanceof Throwable); // true
        System.out.println(t3 instanceof Exception); // true
        System.out.println(t4 instanceof Error);     // true
        System.out.println(t3 instanceof Exception); // true (RuntimeException extends Exception)
    }
}
```

---

## Error vs Exception

### Error

**Error** — JVM-in özünün ya da sistemin ciddi problemi. Proqramçı adətən bunları handle etməməlidir.

```java
public class ErrorExamples {

    // OutOfMemoryError — Heap doldu
    public static void causeOOM() {
        java.util.List<byte[]> list = new java.util.ArrayList<>();
        while (true) {
            list.add(new byte[1024 * 1024]); // 1MB hər dəfə
            // java.lang.OutOfMemoryError: Java heap space
        }
    }

    // StackOverflowError — Stack doldu
    public static void causeSOE() {
        causeSOE(); // Sonsuz rekursiya
        // java.lang.StackOverflowError
    }

    // AssertionError — assertion uğursuz oldu
    public static void causeAssertionError() {
        int x = 5;
        assert x > 10 : "x 10-dan böyük olmalıdır!";
        // java -ea flag-i ilə: java.lang.AssertionError: x 10-dan böyük olmalıdır!
    }

    // NoClassDefFoundError — sinif compile zamanı var idi, runtime-da yoxdur
    // Bu adətən build/deploy problemlərindən yaranır

    public static void main(String[] args) {
        // Error-ları catch etmək ümumiyyətlə yanlışdır
        // YANLIŞ:
        try {
            causeSOE();
        } catch (Error e) {
            // Bu pis praktikadır! JVM ciddi vəziyyətdədir
            System.out.println("Error tutuldu: " + e); // Etibarsız
        }

        // DOĞRU — yalnız çox xüsusi hallarda:
        try {
            byte[] bigArray = new byte[Integer.MAX_VALUE]; // OOM riski
        } catch (OutOfMemoryError e) {
            // Yalnız log yaz, resursu burax, sonra proqramı dayandır
            System.err.println("Kritik: Yaddaş bitmişdir!");
            // System.exit(1); // Adətən burada çıxmaq lazımdır
        }
    }
}
```

---

## Checked Exceptions

**Checked Exception** — compile zamanı yoxlanılan exception-lar. Ya `try-catch` ilə tutulmalı, ya da `throws` ilə elan edilməlidir.

```java
import java.io.*;
import java.sql.*;
import java.net.*;

public class CheckedExceptionExamples {

    // IOException — I/O əməliyyatları
    public static String readFile(String path) throws IOException {
        // throws IOException — çağırıcı bunu bilməlidir
        try (BufferedReader reader = new BufferedReader(new FileReader(path))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line).append('\n');
            }
            return sb.toString();
        }
        // FileNotFoundException (IOException alt sinfi) — fayl tapılmadı
        // IOException — ümumi I/O xətası
    }

    // SQLException — verilənlər bazası əməliyyatları
    public static void queryDatabase(Connection conn) throws SQLException {
        try (PreparedStatement stmt = conn.prepareStatement(
                "SELECT * FROM users WHERE id = ?")) {
            stmt.setInt(1, 1);
            try (ResultSet rs = stmt.executeQuery()) {
                while (rs.next()) {
                    System.out.println(rs.getString("name"));
                }
            }
        }
    }

    // InterruptedException — thread kəsilmə
    public static void waitForResult() throws InterruptedException {
        Thread.sleep(1000); // throws InterruptedException — checked!
        System.out.println("1 saniyə gözlənildi");
    }

    // Checked exception-u handle etmək
    public static void main(String[] args) {
        // Variant 1: try-catch
        try {
            String content = readFile("/tmp/test.txt");
            System.out.println(content);
        } catch (FileNotFoundException e) {
            System.err.println("Fayl tapılmadı: " + e.getMessage());
        } catch (IOException e) {
            System.err.println("Oxuma xətası: " + e.getMessage());
        }

        // Variant 2: throws ilə yuxarıya ötür (main-dən throws olmaz adətən)
        // public static void main(String[] args) throws IOException { ... }
    }
}
```

---

## Unchecked Exceptions

**Unchecked Exception** — `RuntimeException`-dan törəyən exception-lar. Compile zamanı yoxlanılmır.

```java
public class UncheckedExceptionExamples {

    // NullPointerException (NPE)
    public static void npeExample() {
        String s = null;

        // YANLIŞ — NPE!
        // int len = s.length();

        // DOĞRU — null yoxlama
        if (s != null) {
            int len = s.length();
        }

        // DOĞRU — Optional istifadə
        java.util.Optional<String> opt = java.util.Optional.ofNullable(s);
        int len = opt.map(String::length).orElse(0);

        // Java 14+: Helpful NullPointerException
        // "Cannot invoke "String.length()" because "s" is null"
        // Daha aydın mesaj!
    }

    // ArrayIndexOutOfBoundsException
    public static void arrayExample() {
        int[] arr = new int[5]; // 0-4 arası

        // YANLIŞ — index 5 yoxdur!
        // arr[5] = 10;

        // DOĞRU — ölçünü yoxla
        int index = 5;
        if (index >= 0 && index < arr.length) {
            arr[index] = 10;
        }
    }

    // ClassCastException
    public static void castExample() {
        Object obj = "Salam";

        // YANLIŞ — ClassCastException!
        // Integer num = (Integer) obj;

        // DOĞRU — instanceof yoxla
        if (obj instanceof Integer num) { // Java 16+ pattern matching
            System.out.println("Ədəddir: " + num);
        } else {
            System.out.println("Ədəd deyil");
        }
    }

    // IllegalArgumentException
    public static void setAge(int age) {
        if (age < 0 || age > 150) {
            throw new IllegalArgumentException(
                "Yaş 0-150 arasında olmalıdır, verilən: " + age);
        }
        System.out.println("Yaş təyin edildi: " + age);
    }

    // IllegalStateException
    static class Connection {
        private boolean open = false;

        public void open() { open = true; }

        public void query(String sql) {
            if (!open) {
                throw new IllegalStateException(
                    "Sorğu göndərmək üçün əvvəlcə bağlantı açılmalıdır");
            }
            System.out.println("Sorğu: " + sql);
        }
    }

    // NumberFormatException (IllegalArgumentException-dan törəyir)
    public static void parseExample() {
        String input = "abc";
        try {
            int num = Integer.parseInt(input); // NumberFormatException!
        } catch (NumberFormatException e) {
            System.err.println("Yanlış format: " + input);
        }
    }

    // ArithmeticException — Sıfıra bölmə
    public static void divisionExample() {
        int a = 10, b = 0;

        // YANLIŞ — ArithmeticException: / by zero
        // int result = a / b;

        // DOĞRU
        if (b != 0) {
            int result = a / b;
        } else {
            System.err.println("Sıfıra bölmə!");
        }

        // Double üçün sıfıra bölmə exception vermir!
        double d = 10.0 / 0.0;  // Infinity
        System.out.println(d);   // Infinity
        System.out.println(0.0 / 0.0); // NaN
    }

    // ConcurrentModificationException
    public static void concurrentModExample() {
        java.util.List<String> list = new java.util.ArrayList<>(
            java.util.List.of("a", "b", "c"));

        // YANLIŞ — iterasiya zamanı dəyişdirmək
        try {
            for (String s : list) {
                if (s.equals("b")) {
                    list.remove(s); // ConcurrentModificationException!
                }
            }
        } catch (java.util.ConcurrentModificationException e) {
            System.err.println("İterasiya zamanı dəyişdirmə!");
        }

        // DOĞRU — Iterator.remove() istifadə et
        java.util.Iterator<String> it = list.iterator();
        while (it.hasNext()) {
            if (it.next().equals("b")) {
                it.remove(); // Təhlükəsiz!
            }
        }

        // DOĞRU — removeIf
        list.removeIf(s -> s.equals("c"));

        System.out.println(list);
    }

    // UnsupportedOperationException
    public static void unsupportedExample() {
        java.util.List<String> immutable = java.util.List.of("a", "b", "c");

        try {
            immutable.add("d"); // UnsupportedOperationException!
        } catch (UnsupportedOperationException e) {
            System.err.println("Bu list dəyişdirilə bilməz!");
        }
    }
}
```

---

## Ümumi Exception Növləri

### Tam Cədvəl

| Exception | Tip | Səbəb | Həll |
|-----------|-----|-------|------|
| `NullPointerException` | Unchecked | null-a daxil olmaq | null yoxla, Optional |
| `ArrayIndexOutOfBoundsException` | Unchecked | Yanlış array index | Ölçünü yoxla |
| `ClassCastException` | Unchecked | Yanlış tip çevirməsi | instanceof yoxla |
| `IllegalArgumentException` | Unchecked | Yanlış parametr | Parametrləri validate et |
| `IllegalStateException` | Unchecked | Yanlış vəziyyət | State-i yoxla |
| `NumberFormatException` | Unchecked | Yanlış say formatı | try-catch ilə parse |
| `ArithmeticException` | Unchecked | Sıfıra bölmə | Sıfır yoxla |
| `ConcurrentModificationException` | Unchecked | İter. zamanı dəyişmə | Iterator.remove() |
| `UnsupportedOperationException` | Unchecked | Dəstəklənməyən əməliyyat | Tip yoxla |
| `StackOverflowError` | Error | Sonsuz rekursiya | Baza halı əlavə et |
| `OutOfMemoryError` | Error | Yaddaş bitdi | Heap artır, leak tap |
| `IOException` | Checked | I/O xətası | try-catch, throws |
| `FileNotFoundException` | Checked | Fayl yoxdur | Fayl yolunu yoxla |
| `SQLException` | Checked | DB xətası | try-catch, throws |
| `InterruptedException` | Checked | Thread kəsildi | Handle et, interrupt flag |

---

## Nə Zaman Hansını İstifadə Etmək?

### Checked Exception İstifadə Et

```java
// ✓ Xarici resurs əməliyyatları (I/O, network, DB)
// ✓ Çağırıcının mütləq handle etməsini istədiyin hallar
// ✓ Geri bərpa edilə bilən vəziyyətlər

public interface FileProcessor {
    // Checked — çağırıcı faylın olmaya biləcəyini bilməlidir
    String processFile(String path) throws IOException;
}

public interface DatabaseService {
    // Checked — çağırıcı DB xətasını handle etməlidir
    User findUser(int id) throws SQLException;
}
```

### Unchecked (RuntimeException) İstifadə Et

```java
// ✓ Proqramçı xətaları (yanlış arqument, null keçmək)
// ✓ API kontrakt pozuntuları
// ✓ Geri bərpa edilməyən vəziyyətlər

public class UserService {

    // Unchecked — bu proqramçı xətasıdır (null keçmək)
    public User findById(Long id) {
        if (id == null) {
            throw new IllegalArgumentException("id null ola bilməz");
        }
        if (id <= 0) {
            throw new IllegalArgumentException("id müsbət olmalıdır, verilən: " + id);
        }
        // ...
        return null;
    }

    // State pozuntusu
    public void processOrder(Order order) {
        if (!order.isValid()) {
            throw new IllegalStateException("Sifariş etibarsız vəziyyətdədir: " + order);
        }
        // ...
    }
}
```

### Müqayisə Cədvəli

```
Checked Exception:
✓ Fayl tapılmadı (proqram bunu gözləyə bilər)
✓ Şəbəkə kəsildi (yenidən cəhd etmək mümkündür)
✓ DB bağlantısı kəsildi
✗ null ötürmək (proqramçı xətası)
✗ Yanlış array index (proqramçı xətası)

Unchecked Exception:
✓ Null parametr (proqramçı etibarlı kod yazmalıdır)
✓ Yanlış state (implementasiya xətası)
✓ Konfiqurasiya xətası (başlanğıcda aşkarlanar)
✗ Fayl əməliyyatları (geri bərpa mümkündür)
✗ DB xətaları (retry lazım ola bilər)
```

```java
// Ən yaxşı praktika: Spring kimi framework-lər
// Checked exception-ları unchecked-ə çevirir

// Spring-in DataAccessException — bütün DB exception-ları unchecked-ə çevrilir
// IOException-lar → UncheckedIOException-a (Java 8+)

import java.io.*;

public class ModernExceptionHandling {

    // Java 8+ — UncheckedIOException
    public static String readFileUnchecked(String path) {
        try {
            return new String(java.nio.file.Files.readAllBytes(
                java.nio.file.Path.of(path)));
        } catch (IOException e) {
            throw new UncheckedIOException("Fayl oxuna bilmədi: " + path, e);
        }
    }

    // Checked-i unchecked-ə wrap etmək
    @FunctionalInterface
    interface ThrowingSupplier<T> {
        T get() throws Exception;
    }

    static <T> T unchecked(ThrowingSupplier<T> supplier) {
        try {
            return supplier.get();
        } catch (RuntimeException e) {
            throw e;
        } catch (Exception e) {
            throw new RuntimeException(e);
        }
    }

    public static void main(String[] args) {
        // Lambda-larda checked exception problemi
        java.util.List<String> paths = java.util.List.of("/tmp/a.txt", "/tmp/b.txt");

        // YANLIŞ — lambda içindən IOException atmaq olmaz!
        // paths.stream().map(p -> new String(Files.readAllBytes(Path.of(p)))).toList();

        // DOĞRU — wrapper istifadə
        paths.stream()
            .map(p -> unchecked(() -> new String(java.nio.file.Files.readAllBytes(
                java.nio.file.Path.of(p)))))
            .forEach(System.out::println);
    }
}
```

---

## İntervyu Sualları

**S: Checked və Unchecked exception fərqi nədir?**
C: Checked exception-lar compile zamanı yoxlanılır — ya `try-catch` ilə tutulmalı, ya da `throws` ilə elan edilməlidir (IOException, SQLException). Unchecked exception-lar `RuntimeException`-dan törəyir, compile yoxlanması tələb etmir (NPE, ClassCastException). Error-lar isə JVM problemidir (OutOfMemoryError).

**S: `Error`-u `catch` etmək lazımdırmı?**
C: Ümumiyyətlə xeyr. Error JVM-in ciddi problemidir (OOM, StackOverflow). Catch etmək JVM-in qeyri-sabit vəziyyətdə davam etməsinə səbəb ola bilər. Yalnız çox xüsusi hallarda (log yazıb exit etmək) OOM catch oluna bilər.

**S: `NullPointerException`-dan necə qaçmaq olar?**
C: 1) Daxil olmazdan əvvəl null yoxla, 2) `Optional<T>` istifadə et, 3) `Objects.requireNonNull()` metodun girişini yoxla, 4) Kotlin kimi null-safe dillər, 5) Java 14+ Helpful NPE mesajları diaqnozu asanlaşdırır.

**S: `ConcurrentModificationException` nə zaman yaranır?**
C: `for-each` döngüsü ilə iterasiya zamanı kolleksiyaya element əlavə etmək və ya silmək. Həll: `Iterator.remove()`, `removeIf()`, ya da `CopyOnWriteArrayList`.

**S: `RuntimeException` nə zaman throw etmək lazımdır?**
C: Proqramçı xətaları üçün: yanlış parametr (`IllegalArgumentException`), yanlış state (`IllegalStateException`), null keçmək (`NullPointerException`). API kontraktı pozuntularında unchecked daha münasibdir.

**S: `IllegalArgumentException` vs `NullPointerException` — null üçün hansını istifadə etmək?**
C: `Objects.requireNonNull(param, "param null ola bilməz")` — bu `NullPointerException` atır. Bəziləri `IllegalArgumentException` istifadə edir. JDK özü `NullPointerException` atır (Collections.requireNonNull kimi). İkisi də düzgündür, amma tutarlı olmaq vacibdir.

**S: `throws` elanı inheritance-da necə işləyir?**
C: Alt sinif metodu, üst sinif metodundan daha az ya da eyni exception ata bilər — daha çox yox. `Override` zamanı yeni checked exception əlavə etmək olmaz. Unchecked exception-lar bu qayda ilə bağlı deyil.
