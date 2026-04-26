# 69 — Concurrency: ThreadLocal

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [ThreadLocal Nədir?](#threadlocal-nedir)
2. [initialValue / get / set / remove](#initialvalue--get--set--remove)
3. [InheritableThreadLocal](#inheritablethreadlocal)
4. [İstifadə Halları](#istifade-hallari)
5. [Thread Pool-unda Memory Leak](#thread-pool-unda-memory-leak)
6. [remove() Vacibliyi](#remove-vacibliyi)
7. [Praktik Nümunələr](#praktik-numuneler)
8. [İntervyu Sualları](#intervyu-sualları)

---

## ThreadLocal Nədir?

**ThreadLocal** — hər thread üçün öz müstəqil kopyasını saxlayan dəyişən. Bir thread-in dəyəri digər thread-ə görünmür.

```
Adi dəyişən:                ThreadLocal dəyişən:
+----------+                +----------+
|  Thread-A|                |  Thread-A| → Öz kopyası: "Orkhan"
|  Thread-B| → counter=5   |  Thread-B| → Öz kopyası: "Leyla"
|  Thread-C|                |  Thread-C| → Öz kopyası: "Murad"
+----------+                +----------+
(Paylaşılır!)               (Hər biri müstəqildir)
```

```java
import java.util.concurrent.*;

public class ThreadLocalBasics {
    // Hər thread üçün müstəqil String
    private static final ThreadLocal<String> currentUser = new ThreadLocal<>();

    public static void main(String[] args) throws InterruptedException {
        Thread t1 = new Thread(() -> {
            currentUser.set("Orkhan"); // Yalnız bu thread üçün
            System.out.println("T1 istifadəçi: " + currentUser.get()); // Orkhan
            try { Thread.sleep(1000); } catch (InterruptedException e) {}
            System.out.println("T1 hələ: " + currentUser.get()); // Hələ Orkhan (T2-nin dəyişikliyi görünmür)
        });

        Thread t2 = new Thread(() -> {
            currentUser.set("Leyla"); // Yalnız bu thread üçün
            System.out.println("T2 istifadəçi: " + currentUser.get()); // Leyla
        });

        t1.start();
        t2.start();

        t1.join();
        t2.join();

        // Main thread heç set etmədisə
        System.out.println("Main istifadəçi: " + currentUser.get()); // null
    }
}
```

**Daxili mexanizm:**

```java
// Hər Thread-in daxilindəki map:
class Thread {
    ThreadLocal.ThreadLocalMap threadLocals; // Thread-ə məxsus map
}

// ThreadLocal.get() daxili işi:
public T get() {
    Thread t = Thread.currentThread();
    ThreadLocalMap map = t.threadLocals; // Cari thread-in map-i
    if (map != null) {
        Entry e = map.getEntry(this); // this = ThreadLocal instance
        if (e != null) return (T) e.value;
    }
    return setInitialValue();
}
```

---

## initialValue / get / set / remove

```java
public class ThreadLocalMethods {
    // Üsul 1: Override initialValue()
    private static final ThreadLocal<List<String>> logs = new ThreadLocal<>() {
        @Override
        protected List<String> initialValue() {
            return new ArrayList<>(); // Hər thread üçün yeni siyahı
        }
    };

    // Üsul 2: withInitial() — Java 8+ (lambda)
    private static final ThreadLocal<SimpleDateFormat> dateFormat =
        ThreadLocal.withInitial(() -> new SimpleDateFormat("yyyy-MM-dd"));

    // Üsul 3: Default null
    private static final ThreadLocal<String> userId = new ThreadLocal<>();

    public static void main(String[] args) {
        // get() — dəyəri al (null və ya initialValue)
        System.out.println(userId.get()); // null (set edilməyib)
        System.out.println(logs.get());   // [] (initialValue çağırıldı)

        // set() — dəyəri qur
        userId.set("user-123");
        System.out.println(userId.get()); // user-123

        // logs-a əlavə et
        logs.get().add("Giriş əməliyyatı");
        logs.get().add("Məlumat yükləndi");

        // remove() — dəyəri sil (MÜTLƏQ çağır!)
        userId.remove();
        System.out.println(userId.get()); // null (remove sonra initialValue)

        logs.remove(); // Siyahını sil

        // set(null) — remove() deyil! ThreadLocalMap-də null entry qalır
        // userId.set(null); ← YANLIŞ YANAŞMA
    }
}
```

---

## InheritableThreadLocal

Alt thread-lərin parent thread-in dəyərini miras alması üçün.

```java
import java.util.concurrent.*;

public class InheritableDemo {
    // Adi ThreadLocal — uşaq thread-lərinə keçmir
    private static final ThreadLocal<String> normal = new ThreadLocal<>();

    // InheritableThreadLocal — uşaq thread-lər dəyəri miras alır
    private static final InheritableThreadLocal<String> inheritable =
        new InheritableThreadLocal<>();

    public static void main(String[] args) throws InterruptedException {
        normal.set("Ana thread dəyəri");
        inheritable.set("Miras alınacaq dəyər");

        Thread child = new Thread(() -> {
            System.out.println("Normal: " + normal.get());      // null (miras yoxdur)
            System.out.println("Inheritable: " + inheritable.get()); // "Miras alınacaq dəyər"

            // Uşaq thread dəyəri dəyişdirə bilər — valideynə təsir etmir
            inheritable.set("Uşağın öz dəyəri");
            System.out.println("Uşaq dəyişdirdi: " + inheritable.get());
        });

        child.start();
        child.join();

        // Valideyn dəyəri dəyişmədi
        System.out.println("Valideyn hələ: " + inheritable.get()); // "Miras alınacaq dəyər"
    }
}
```

**InheritableThreadLocal-ı necə fərdiləşdirmək olar:**

```java
// childValue() — uşağa ötürüləcək dəyəri fərdiləşdir
private static final InheritableThreadLocal<Map<String, String>> context =
    new InheritableThreadLocal<>() {
        @Override
        protected Map<String, String> childValue(Map<String, String> parentValue) {
            // Valideynin kopyasını ver — uşaq müstəqil kopyaya sahib olur
            return parentValue != null ? new HashMap<>(parentValue) : new HashMap<>();
        }
    };
```

**Diqqət:** `ExecutorService` pool thread-ləri yenidən istifadə edir — pool thread-i parent thread deyil. Bu zaman `InheritableThreadLocal` doğru işləmir. Bunun üçün `TransmittableThreadLocal` (TTL) kimi kütüphanalar lazımdır.

---

## İstifadə Halları

### 1. SimpleDateFormat — Thread-Safe Etmək

```java
// PROBLEM — SimpleDateFormat thread-safe deyil
public class UnsafeDateFormatter {
    // YANLIŞ — paylaşılan SimpleDateFormat
    private static final SimpleDateFormat formatter = new SimpleDateFormat("yyyy-MM-dd");

    public String format(Date date) {
        return formatter.format(date); // Race condition! SimpleDateFormat state saxlayır
    }
}

// HƏLL 1 — ThreadLocal ilə hər thread-ə öz formatter-i
public class SafeDateFormatter {
    private static final ThreadLocal<SimpleDateFormat> threadLocalFormatter =
        ThreadLocal.withInitial(() -> new SimpleDateFormat("yyyy-MM-dd"));

    public String format(Date date) {
        return threadLocalFormatter.get().format(date); // Hər thread öz formatter-ə sahibdir
    }
}

// HƏLL 2 (Daha yaxşı — Java 8+) — DateTimeFormatter istifadə et (thread-safe!)
import java.time.format.*;
public class ModernDateFormatter {
    private static final DateTimeFormatter formatter =
        DateTimeFormatter.ofPattern("yyyy-MM-dd"); // Thread-safe, ThreadLocal lazım deyil

    public String format(LocalDate date) {
        return date.format(formatter); // Təhlükəsiz!
    }
}
```

### 2. Database Connection Konteksti

```java
public class ConnectionContext {
    private static final ThreadLocal<Connection> connectionHolder = new ThreadLocal<>();

    public static void beginTransaction() throws SQLException {
        Connection conn = dataSource.getConnection();
        conn.setAutoCommit(false);
        connectionHolder.set(conn); // Cari thread-in connection-ı
    }

    public static Connection getConnection() {
        Connection conn = connectionHolder.get();
        if (conn == null) {
            throw new IllegalStateException("Transaction başladılmayıb!");
        }
        return conn;
    }

    public static void commit() throws SQLException {
        Connection conn = connectionHolder.get();
        if (conn != null) {
            conn.commit();
        }
    }

    public static void rollback() {
        Connection conn = connectionHolder.get();
        if (conn != null) {
            try { conn.rollback(); } catch (SQLException e) { /* log */ }
        }
    }

    // MÜTLƏQi — connection-ı bağla və ThreadLocal-ı təmizlə
    public static void endTransaction() {
        Connection conn = connectionHolder.get();
        if (conn != null) {
            try { conn.close(); } catch (SQLException e) { /* log */ }
            finally { connectionHolder.remove(); } // REMOVE!
        }
    }
}

// İstifadəsi (Spring @Transactional-ın davranışına bənzər):
try {
    ConnectionContext.beginTransaction();
    userRepository.save(user);
    orderRepository.save(order);
    ConnectionContext.commit();
} catch (Exception e) {
    ConnectionContext.rollback();
} finally {
    ConnectionContext.endTransaction(); // HƏMİŞƏ çağır!
}
```

### 3. Web Tətbiqində İstifadəçi Konteksti

```java
// HTTP sorğu boyunca istifadəçi məlumatını daşı (Filter → Controller → Service → Repository)
public class UserContext {
    private static final ThreadLocal<UserPrincipal> currentUser = new ThreadLocal<>();

    public static void setCurrentUser(UserPrincipal user) {
        currentUser.set(user);
    }

    public static UserPrincipal getCurrentUser() {
        UserPrincipal user = currentUser.get();
        if (user == null) {
            throw new UnauthorizedException("İstifadəçi konteksti yoxdur");
        }
        return user;
    }

    public static void clear() {
        currentUser.remove(); // Sorğu bitdikdə MÜTLƏQİ sil!
    }
}

// Servlet Filter-i:
public class UserContextFilter implements Filter {
    @Override
    public void doFilter(ServletRequest req, ServletResponse res, FilterChain chain)
            throws IOException, ServletException {
        try {
            // JWT token-dən istifadəçini əldə et
            UserPrincipal user = extractUserFromToken((HttpServletRequest) req);
            UserContext.setCurrentUser(user);

            chain.doFilter(req, res); // Sorğunu icra et

        } finally {
            UserContext.clear(); // HƏMİŞƏ! Memory leak-ın qarşısını al
        }
    }
}

// Service-dən istifadəçiyə birbaşa çıxış (parametr olmadan):
public class OrderService {
    public Order createOrder(OrderRequest request) {
        UserPrincipal user = UserContext.getCurrentUser(); // Thread-safe!
        return new Order(user.getId(), request.getProduct());
    }
}
```

### 4. MDC — Mapped Diagnostic Context (Logging)

```java
// Log4j/Logback-ın MDC-i daxili olaraq ThreadLocal istifadə edir
import org.slf4j.MDC;

public class RequestLoggingFilter implements Filter {
    @Override
    public void doFilter(ServletRequest req, ServletResponse res, FilterChain chain)
            throws IOException, ServletException {
        String requestId = UUID.randomUUID().toString();
        String userId = extractUserId((HttpServletRequest) req);

        try {
            MDC.put("requestId", requestId); // ThreadLocal-a yazır
            MDC.put("userId", userId);

            // Log formatı: [requestId=xxx userId=yyy] Log mesajı
            chain.doFilter(req, res);

        } finally {
            MDC.clear(); // Thread pool-unda MÜTLƏQ!
        }
    }
}
```

---

## Thread Pool-unda Memory Leak

Bu ən kritik məsələdir!

```java
// YANLIŞ — Thread Pool-unda memory leak
public class MemoryLeakExample {
    private static final ThreadLocal<byte[]> largeData = new ThreadLocal<>();

    public void processRequest() {
        largeData.set(new byte[1024 * 1024]); // 1MB data

        // Request işlənir...
        doWork();

        // remove() ÇAĞIRILMADI! ← Memory leak!
    }
    // Thread pool-u thread-ləri yenidən istifadə edir.
    // Köhnə 1MB data thread-in ThreadLocalMap-ında qalır!
    // Minlərlə sorğudan sonra OutOfMemoryError!
}

// DOĞRU — remove() mütləq çağırılır
public class SafeThreadLocalUsage {
    private static final ThreadLocal<byte[]> largeData = new ThreadLocal<>();

    public void processRequest() {
        try {
            largeData.set(new byte[1024 * 1024]);
            doWork();
        } finally {
            largeData.remove(); // HƏMİŞƏ! Exception olsa belə!
        }
    }

    private void doWork() { /* iş */ }
}
```

**Memory leak-ın mexanizmi:**

```
Thread (pool thread, uzunömürlü)
└── ThreadLocalMap
    ├── WeakReference(ThreadLocal) → value: 1MB data   ← Sorğu 1-in data-sı
    ├── WeakReference(ThreadLocal) → value: 1MB data   ← Sorğu 2-in data-sı
    └── ... (ThreadLocal GC-yə uğrayarsa, key null olur, amma VALUE QALIR!)

ThreadLocal GC-yə uğrasa belə (WeakReference):
- KEY: null (GC tərəfindən silinib)
- VALUE: hələ əlçatmaz 1MB — LEAK!
```

**Niyə WeakReference?**

```java
// ThreadLocalMap entry-ləri WeakReference saxlayır ki:
// ThreadLocal obyekti başqa yerdən istinad olunmursa GC-yə uğrasın
// AMMa: Value hələ ThreadLocalMap-da qalır (strong reference!)
// Həll: Yalnız remove() key+value ikisini də silir
```

---

## remove() Vacibliyi

```java
// Thread pool-unda təhlükəsiz ThreadLocal istifadə şablonu
public class ThreadLocalBestPractice {
    private static final ThreadLocal<RequestContext> context = new ThreadLocal<>();

    // Şablon 1: try-finally
    public void handleRequest(Request request) {
        try {
            context.set(new RequestContext(request));
            processRequest();
        } finally {
            context.remove(); // Bütün hallarda icra olunur
        }
    }

    // Şablon 2: AutoCloseable ilə (Java 7+)
    static class ThreadLocalScope implements AutoCloseable {
        private final ThreadLocal<?> threadLocal;

        ThreadLocalScope(ThreadLocal<RequestContext> tl, RequestContext value) {
            this.threadLocal = tl;
            tl.set(value);
        }

        @Override
        public void close() {
            threadLocal.remove(); // try-with-resources bitdikdə avtomatik çağırılır
        }
    }

    public void handleRequestAutoClose(Request request) {
        try (var scope = new ThreadLocalScope(context, new RequestContext(request))) {
            processRequest();
        } // Avtomatik remove() çağırılır
    }

    private void processRequest() {
        RequestContext ctx = context.get();
        System.out.println("Sorğu işlənir: " + ctx);
    }

    record RequestContext(Request request) {}
    record Request(String id) {}
}
```

---

## Praktik Nümunələr

### Tranzaksiya İdarəetmə Sistemi

```java
import java.util.*;

public class TransactionManager {
    private static final ThreadLocal<Deque<String>> transactionStack =
        ThreadLocal.withInitial(ArrayDeque::new);

    private static final ThreadLocal<Map<String, Object>> transactionContext =
        ThreadLocal.withInitial(HashMap::new);

    public static String beginTransaction(String name) {
        String txId = UUID.randomUUID().toString().substring(0, 8);
        transactionStack.get().push(txId);
        transactionContext.get().put("name", name);
        transactionContext.get().put("startTime", System.currentTimeMillis());
        System.out.println("[TX:" + txId + "] Tranzaksiya başladı: " + name);
        return txId;
    }

    public static void commitTransaction() {
        Deque<String> stack = transactionStack.get();
        if (stack.isEmpty()) throw new IllegalStateException("Aktiv tranzaksiya yoxdur");

        String txId = stack.pop();
        long duration = System.currentTimeMillis() -
            (Long) transactionContext.get().get("startTime");
        System.out.println("[TX:" + txId + "] Commit - " + duration + "ms");

        if (stack.isEmpty()) {
            // Ən son tranzaksiya bitdi — context-i təmizlə
            transactionContext.remove();
            // stack özü boşdur amma ThreadLocalMap-da qalır
            // İstersək:
            transactionStack.remove();
        }
    }

    public static String getCurrentTransactionId() {
        Deque<String> stack = transactionStack.get();
        return stack.isEmpty() ? null : stack.peek();
    }

    public static void main(String[] args) throws InterruptedException {
        ExecutorService pool = Executors.newFixedThreadPool(2);

        for (int i = 1; i <= 4; i++) {
            final int reqId = i;
            pool.submit(() -> {
                try {
                    String txId = beginTransaction("Sorğu-" + reqId);
                    Thread.sleep(100);
                    System.out.println("Aktiv TX: " + getCurrentTransactionId());
                    commitTransaction();
                } catch (InterruptedException e) {
                    Thread.currentThread().interrupt();
                }
            });
        }

        pool.shutdown();
        pool.awaitTermination(5, TimeUnit.SECONDS);
    }
}
```

---

## İntervyu Sualları

**S: ThreadLocal nədir və nə üçün lazımdır?**
C: Hər thread üçün müstəqil dəyişən kopiyası saxlayan mexanizmdir. Thread-lər arasında paylaşılmır. SimpleDateFormat kimi thread-safe olmayan sinifləri hər thread-ə ayrı-ayrılıqda saxlamaq, web sorğusu boyunca kontekst daşımaq üçün istifadə olunur.

**S: ThreadLocal daxili olaraq necə işləyir?**
C: Hər Thread obyektinin `threadLocals` adlı `ThreadLocalMap` sahəsi var. `set()` bu map-ə ThreadLocal instance-ı key, dəyəri value olaraq yazır. `get()` cari thread-in map-indən oxuyur.

**S: ThreadLocal ilə thread pool-unda memory leak necə baş verir?**
C: Pool thread-ləri uzunömürlüdür. `remove()` çağırılmasa, ThreadLocalMap-da köhnə value qalır. ThreadLocal GC-yə uğrasa belə (WeakReference key), value strong reference ilə saxlanılır. Çözüm: HƏMİŞƏ finally-də `remove()` çağır.

**S: `set(null)` vs `remove()` fərqi?**
C: `set(null)` — ThreadLocalMap-da entry qalır, key var, value null. `remove()` — entry tamamilə silinir. `remove()` daha təmizdir və GC üçün daha yaxşıdır.

**S: InheritableThreadLocal nədir?**
C: Alt thread-in (child) parent thread-in ThreadLocal dəyərini miras almasını təmin edir. Thread yaradıldığı anda valideynin dəyəri kopyalanır. ExecutorService pool thread-lərində düzgün işləmir (thread valideyn deyil).

**S: ThreadLocal nə vaxt istifadə etmək lazım deyil?**
C: Thread pool-unda remove() çağırmaq çətin olduqda (memory leak riski). Əgər digər thread-lə data paylaşmaq lazımdırsa (ThreadLocal bunu etmir). Dəyər thread əvvəl yaradıldıqda məlum deyilsə InheritableThreadLocal ExecutorService ilə.

**S: ThreadLocal-ın alternativləri hansılardır?**
C: 1) Metod parametrləri kimi ötürmək (ən təmiz). 2) `ConcurrentHashMap<Thread, Value>` (amma GC problemi). 3) Structured concurrency ilə scoped values (Java 21+, `ScopedValue`). 4) Spring-in `RequestContextHolder` (özü ThreadLocal istifadə edir).

**S: ThreadLocal WeakReference istifadə edirmi?**
C: Bəli, ThreadLocalMap-da key (ThreadLocal instance) WeakReference ilə saxlanılır. Buna görə ThreadLocal obyekti başqa yerdən istinad olunmursa GC-yə uğraya bilər. Amma value strong reference olaraq qalır — odur ki remove() vacibdir.
