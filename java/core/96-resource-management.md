# 96 — Resource Management — try-with-resources və AutoCloseable

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Resource Management nədir?](#resource-management-nədir)
2. [try-with-resources](#try-with-resources)
3. [AutoCloseable interface](#autocloseable-interface)
4. [Suppressed exceptions](#suppressed-exceptions)
5. [Praktik nümunələr](#praktik-nümunələr)
6. [Laravel ilə müqayisə](#laravel-ilə-müqayisə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Resource Management nədir?

Java-da bəzi obyektlər sistem resurslarını tutur: fayl, database connection, network socket. Bu resursları işiniz bitdikdə **mütləq bağlamalısınız** — Java garbage collector bunu etmir.

```
PHP-da:
  $file = fopen('data.txt', 'r');
  // PHP proses bitdikdə OS bütün resursları azad edir
  // Hər request yeni proses — leak riski azdır

Java-da:
  FileReader reader = new FileReader("data.txt");
  // JVM uzun müddət işləyir — resurslar açıq qalır
  // reader.close() çağırılmazsa → resource leak!
  // Database connection pool tükənər, file handle limitə çatar
```

---

## try-with-resources

Java 7-də gəldi. `AutoCloseable` interface-ini implement edən resursları avtomatik bağlayır.

```java
// Köhnə yanaşma — try-finally ilə:
FileReader reader = null;
try {
    reader = new FileReader("data.txt");
    // ... oxu
} catch (IOException e) {
    e.printStackTrace();
} finally {
    if (reader != null) {
        try {
            reader.close(); // finally-də də exception ola bilər
        } catch (IOException e) {
            e.printStackTrace();
        }
    }
}

// try-with-resources ilə — eyni şey, təmiz:
try (FileReader reader = new FileReader("data.txt")) {
    // ... oxu
    // reader.close() avtomatik çağrılır — exception olsa belə
} catch (IOException e) {
    e.printStackTrace();
}
```

### Bir neçə resurs:

```java
// Sıralı bağlanır (sondan əvvələ):
try (
    FileInputStream fis = new FileInputStream("input.txt");
    BufferedReader reader = new BufferedReader(new InputStreamReader(fis));
    PrintWriter writer = new PrintWriter(new FileWriter("output.txt"))
) {
    String line;
    while ((line = reader.readLine()) != null) {
        writer.println(line.toUpperCase());
    }
    // writer.close() → reader.close() → fis.close() (əks sırayla)
}
```

### Java 9+ — effectively final:

```java
// Java 9+: dəyişkən xaricdə elan edilə bilər:
FileReader reader = new FileReader("data.txt");
try (reader) { // ← Java 9+ syntax
    // ...
}
```

---

## AutoCloseable interface

```java
public interface AutoCloseable {
    void close() throws Exception;
}

// Closeable (IO üçün, IOException atar):
public interface Closeable extends AutoCloseable {
    void close() throws IOException;
}
```

### Custom AutoCloseable:

```java
// Database connection wrapper:
public class DatabaseConnection implements AutoCloseable {

    private final Connection connection;

    public DatabaseConnection(String url) throws SQLException {
        this.connection = DriverManager.getConnection(url);
    }

    public ResultSet query(String sql) throws SQLException {
        return connection.createStatement().executeQuery(sql);
    }

    @Override
    public void close() throws SQLException {
        if (connection != null && !connection.isClosed()) {
            connection.close();
            System.out.println("Connection closed");
        }
    }
}

// İstifadə:
try (DatabaseConnection db = new DatabaseConnection("jdbc:postgresql://...")) {
    ResultSet rs = db.query("SELECT * FROM users");
    // ...
} // ← db.close() avtomatik

// Custom resource — timer:
public class Timer implements AutoCloseable {
    private final String name;
    private final long start = System.currentTimeMillis();

    public Timer(String name) {
        this.name = name;
    }

    @Override
    public void close() {
        long duration = System.currentTimeMillis() - start;
        System.out.printf("%s: %dms%n", name, duration);
    }
}

// İstifadə:
try (Timer t = new Timer("DB query")) {
    // ... query icra et
} // ← "DB query: 45ms" çap edir
```

### Spring-də custom resource:

```java
// Distributed lock wrapper:
public class DistributedLock implements AutoCloseable {

    private final RedissonClient redisson;
    private final RLock lock;

    public DistributedLock(RedissonClient redisson, String lockKey) {
        this.redisson = redisson;
        this.lock = redisson.getLock(lockKey);
        lock.lock(30, TimeUnit.SECONDS);
    }

    @Override
    public void close() {
        if (lock.isHeldByCurrentThread()) {
            lock.unlock();
        }
    }
}

// Service-də:
@Service
@RequiredArgsConstructor
public class OrderService {

    private final RedissonClient redisson;

    public void processOrder(Long orderId) {
        String lockKey = "order:lock:" + orderId;
        try (DistributedLock lock = new DistributedLock(redisson, lockKey)) {
            // Lock tutulub, təhlükəsiz işlə
            Order order = orderRepo.findById(orderId).orElseThrow();
            order.process();
            orderRepo.save(order);
        } // ← lock avtomatik azad olunur
    }
}
```

---

## Suppressed exceptions

try-with-resources-da həm resource body-dən, həm `close()`-dan exception gəlsə nə olur?

```java
public class BrokenResource implements AutoCloseable {

    @Override
    public void close() {
        throw new RuntimeException("Close exception");
    }
}

try (BrokenResource r = new BrokenResource()) {
    throw new RuntimeException("Body exception"); // ← bu "primary" exception
}
// close() xətası "suppressed" olur — primary exception-a əlavə edilir

catch (RuntimeException e) {
    System.out.println("Primary: " + e.getMessage()); // Body exception
    for (Throwable suppressed : e.getSuppressed()) {
        System.out.println("Suppressed: " + suppressed.getMessage()); // Close exception
    }
}
```

Köhnə try-finally-də `close()` xətası ana xətanı **udurdu** — itirilirdi. try-with-resources suppressed exception-larla hər ikisini saxlayır.

---

## Praktik nümunələr

### File oxumaq:

```java
public List<String> readLines(String filePath) throws IOException {
    List<String> lines = new ArrayList<>();
    try (BufferedReader reader = Files.newBufferedReader(Path.of(filePath))) {
        String line;
        while ((line = reader.readLine()) != null) {
            lines.add(line);
        }
    }
    return lines;
}

// Modern Java (Files utility ilə daha qısa):
public List<String> readLinesModern(String filePath) throws IOException {
    return Files.readAllLines(Path.of(filePath));
}
```

### HTTP connection:

```java
public String fetchData(String url) throws IOException {
    URL endpoint = new URL(url);
    try (
        InputStream is = endpoint.openStream();
        BufferedReader reader = new BufferedReader(new InputStreamReader(is))
    ) {
        StringBuilder sb = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) {
            sb.append(line);
        }
        return sb.toString();
    }
}
```

### JDBC manual:

```java
public Optional<User> findUser(Long id) throws SQLException {
    String sql = "SELECT id, name, email FROM users WHERE id = ?";

    try (
        Connection conn = dataSource.getConnection();
        PreparedStatement stmt = conn.prepareStatement(sql)
    ) {
        stmt.setLong(1, id);

        try (ResultSet rs = stmt.executeQuery()) {
            if (rs.next()) {
                return Optional.of(new User(
                    rs.getLong("id"),
                    rs.getString("name"),
                    rs.getString("email")
                ));
            }
        }
        return Optional.empty();
    }
}
```

### ExecutorService:

```java
// ExecutorService Closeable implementasiya etmir (Java 21-dən əvvəl)
// Java 21-dən Closeable oldu:
try (ExecutorService exec = Executors.newVirtualThreadPerTaskExecutor()) {
    Future<String> f1 = exec.submit(() -> fetchFromService1());
    Future<String> f2 = exec.submit(() -> fetchFromService2());
    String result1 = f1.get();
    String result2 = f2.get();
} // ← executor.shutdown() + awaitTermination avtomatik
```

---

## Laravel ilə müqayisə

```php
// PHP — resource context manager yoxdur, amma PHP GC-si gözə görünməz işləyir:
$handle = fopen('data.txt', 'r');
// Fayl açıqdır...
fclose($handle); // Əl ilə bağlamaq lazımdır

// PHP-də destructor:
class DatabaseConnection {
    public function __destruct() {
        $this->connection->close(); // Object destroy olanda
    }
}
// Hər request üçün yeni PHP process → object-lər request sonunda destroy olur

// Laravel DB connection-lar connection pool idarə edir
// Siz connection.close() etmirsiniz — framework edir
```

```java
// Java — uzun işləyən JVM, try-with-resources şərtdir:
try (Connection conn = dataSource.getConnection()) {
    // ...
} // conn.close() pool-a qaytarır

// Spring/JPA bunu sizin üçün edir (transaction scope-da)
// Amma JDBC-ni özünüz istifadə etsəniz — try-with-resources lazımdır
```

---

## İntervyu Sualları

**S: try-with-resources nədir, niyə lazımdır?**
C: Java 7+ feature. `AutoCloseable` implements edən resursları block bitdikdə (exception olsa belə) avtomatik bağlayır. `finally` blokunda əl ilə `close()` yazmaqdan qurtarır; suppressed exception-larla hər iki xətanı saxlayır.

**S: AutoCloseable vs Closeable fərqi?**
C: `AutoCloseable.close()` — `Exception` atar (generic). `Closeable.close()` — `IOException` atar (spesifik, IO resurslar üçün). try-with-resources ikisini də dəstəkləyir.

**S: Bir neçə resurs bağlanma sırası?**
C: Əks sırayla — son açılan birinci bağlanır. Elan sırası: `A`, `B`, `C` → bağlanma: `C.close()`, `B.close()`, `A.close()`.

**S: close() exception atarsa nə olur?**
C: Body-dən exception varsa, `close()` xətası "suppressed" olur — `e.getSuppressed()` ilə əlçatan. Body exception yoxdursa, `close()` xətası əsas exception kimi propagate olur. Köhnə try-finally-dən fərqli: body exception `close()` xətası tərəfindən udulmurdu.
