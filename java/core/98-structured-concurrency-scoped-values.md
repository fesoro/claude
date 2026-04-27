# Structured Concurrency və Scoped Values (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐

## İcmal

**Structured Concurrency** (Java 21, JEP 453) — paralel tapşırıqları bir "scope" daxilində idarə etmək üçün framework. **Scoped Values** (Java 21, JEP 446) — `ThreadLocal`-ın virtual threads üçün yenidən dizayn edilmiş alternativi.

---

## Niyə Vacibdir

Virtual threads ilə minlərlə paralel tapşırıq başlatmaq asan olur — amma idarə etmək çətin:

```
Klassik problem:
  ExecutorService-dən 3 task başlat
  → Biri uğursuz olur
  → Digər 2 task hələ işləyir
  → Cancel etməyi yaddan çıxarsan → resource leak
  → Exception əl ilə gətirilməlidir

Structured Concurrency:
  StructuredTaskScope ilə 3 task başlat
  → scope.join() → hamısı bitənə qədər gözlə
  → Biri uğursuz → scope avtomatik digərləri ləğv edir
  → try-with-resources → scope avtomatik bağlanır
```

---

## Əsas Anlayışlar

### StructuredTaskScope

```java
// ShutdownOnFailure — biri xəta versə hamısı ləğv
try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {

    Subtask<User> userTask = scope.fork(() -> userService.findById(userId));
    Subtask<List<Order>> ordersTask = scope.fork(() -> orderService.findByUser(userId));
    Subtask<List<Product>> favoritesTask = scope.fork(() -> productService.getFavorites(userId));

    scope.join()            // Bütün tapşırıqlar bitənə qədər gözlə
         .throwIfFailed();  // İstənilən xəta varsa throw et

    // Nəticələri al — bu nöqtəyə yalnız hamısı uğurlu olarsa çatırıq
    User user = userTask.get();
    List<Order> orders = ordersTask.get();
    List<Product> favorites = favoritesTask.get();

    return new UserDashboard(user, orders, favorites);
}
// scope.close() → try blokdan çıxanda avtomatik çağrılır
```

```java
// ShutdownOnSuccess — biri uğurlu olsa hamısı ləğv
// Race: hansı cache server əvvəl cavab verir?
try (var scope = new StructuredTaskScope.ShutdownOnSuccess<String>()) {

    scope.fork(() -> primaryCache.get(key));
    scope.fork(() -> replicaCache.get(key));
    scope.fork(() -> remoteCache.get(key));

    scope.join();

    return scope.result(); // İlk uğurlu nəticə
}
```

### Timeout ilə

```java
try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {

    Subtask<Product> productTask = scope.fork(() -> productService.find(id));
    Subtask<Inventory> inventoryTask = scope.fork(() -> inventoryService.check(id));

    // 5 saniyə gözlə — vaxt keçsə ləğv et
    scope.joinUntil(Instant.now().plusSeconds(5))
         .throwIfFailed();

    return new ProductDetails(productTask.get(), inventoryTask.get());

} catch (TimeoutException e) {
    throw new ServiceUnavailableException("Servis cavab vermir");
}
```

### Custom Scope

```java
// Öz politikasını yazmaq
class CollectAllScope<T> extends StructuredTaskScope<T> {

    private final List<T> results = new CopyOnWriteArrayList<>();
    private final List<Throwable> errors = new CopyOnWriteArrayList<>();

    @Override
    protected void handleComplete(Subtask<? extends T> subtask) {
        switch (subtask.state()) {
            case SUCCESS -> results.add(subtask.get());
            case FAILED  -> errors.add(subtask.exception());
            case UNAVAILABLE -> {} // Ləğv edilmiş — ignore
        }
    }

    public List<T> results() {
        ensureOwnerAndJoined(); // Thread-safety yoxla
        return Collections.unmodifiableList(results);
    }

    public List<Throwable> errors() {
        ensureOwnerAndJoined();
        return Collections.unmodifiableList(errors);
    }
}

// İstifadəsi
try (var scope = new CollectAllScope<ProductDto>()) {
    productIds.forEach(id -> scope.fork(() -> productService.fetchDto(id)));
    scope.join();

    log.info("Uğurlu: {}, Xətalı: {}", scope.results().size(), scope.errors().size());
    return scope.results();
}
```

---

## Scoped Values

`ThreadLocal`-ın virtual threads üçün problemi:

```
ThreadLocal problemi:
  Thread pool-da 1 thread → task-1 → ThreadLocal.set("user-A")
  → task-1 qurtarır
  → Eyni thread → task-2 → ThreadLocal.get() → hələ "user-A" !!!
  (cleanup unutulsa leak)

Virtual threads ilə:
  Milyonlarla virtual thread → ThreadLocal per-thread → yaddaş problemi
```

**Scoped Values** — immutable, scope-bound, virtual threads-friendly:

```java
// 1. ScopedValue təyin et — class/application səviyyəsində
public class RequestContext {
    public static final ScopedValue<String> CURRENT_USER =
        ScopedValue.newInstance();
    public static final ScopedValue<String> TENANT_ID =
        ScopedValue.newInstance();
}

// 2. Dəyər yaz — yalnız scope daxilindədir
ScopedValue.where(RequestContext.CURRENT_USER, "ali@example.com")
           .where(RequestContext.TENANT_ID, "tenant-42")
           .run(() -> {
               // Bu lambda daxilindəki bütün metodlar dəyərə çata bilər
               orderService.processOrder(request);
               auditService.log("Order created");
           });

// 3. Dəyər oxu
@Service
public class OrderService {

    public Order processOrder(CreateOrderRequest request) {
        String user = RequestContext.CURRENT_USER.get();   // "ali@example.com"
        String tenant = RequestContext.TENANT_ID.get();    // "tenant-42"

        log.info("User {} tenant {}-dən sifariş verir", user, tenant);
        // ...
    }
}

// Scope xaricindədir → isSBound() false
boolean hasTenant = RequestContext.TENANT_ID.isBound(); // false
```

### Spring ilə inteqrasiya

```java
// Spring Filter-dən ScopedValue set et
@Component
public class TenantScopedValueFilter implements Filter {

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
                         FilterChain chain) throws IOException, ServletException {

        String tenantId = ((HttpServletRequest) request).getHeader("X-Tenant-Id");
        String userId = SecurityContextHolder.getContext()
            .getAuthentication().getName();

        ScopedValue.where(RequestContext.TENANT_ID, tenantId)
                   .where(RequestContext.CURRENT_USER, userId)
                   .run(() -> {
                       try {
                           chain.doFilter(request, response);
                       } catch (Exception e) {
                           throw new RuntimeException(e);
                       }
                   });
    }
}
```

### Structured Concurrency + Scoped Values

Scoped Values child task-lara **avtomatik** ötürülür:

```java
ScopedValue.where(RequestContext.CURRENT_USER, "ali@example.com")
           .run(() -> {
               try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {

                   Subtask<Order> orderTask = scope.fork(() -> {
                       // CURRENT_USER-a burada da çatmaq olur!
                       String user = RequestContext.CURRENT_USER.get(); // "ali@example.com"
                       return orderService.create(user, request);
                   });

                   Subtask<Void> auditTask = scope.fork(() -> {
                       String user = RequestContext.CURRENT_USER.get(); // eyni dəyər
                       auditService.log(user, "order_create");
                       return null;
                   });

                   scope.join().throwIfFailed();
               }
           });
```

---

## Praktik Baxış

**Structured Concurrency nə zaman:**
- Bir neçə müstəqil async tapşırıqdan nəticə toplamaq (dashboard, aggregation)
- "Ya hamısı, ya heç biri" tələbi — bir xəta varsa digərləri ləğv
- Race pattern — ilk cavab verən qazan

**Nə zaman istifadə etmə:**
- Sequential (bir-birinin ardınca) tapşırıqlar — sadə `try-catch` yetər
- Sonsuz işləyən background task — `ExecutorService` uyğundur

**Scoped Values nə zaman:**
- Request-scoped məlumat — tenant, user, correlation ID
- ThreadLocal istifadə etdiyin hər yer (virtual threads ilə daha performanslı)

**Anti-pattern:**
```java
// ❌ Yanlış: Subtask nəticəsini scope.join() öncə almaq
Subtask<User> task = scope.fork(() -> findUser(id));
User user = task.get(); // IllegalStateException! Hələ bitməyib

// ✅ Doğru: join() sonra al
scope.join().throwIfFailed();
User user = task.get(); // Təhlükəsizdir
```

---

## Nümunə: API Aggregation Service

```java
@Service
public class ProductAggregationService {

    // Məhsul haqqında 3 müstəqil servisə paralel sorğu
    public ProductAggregation getProductDetails(Long productId) {

        try (var scope = new StructuredTaskScope.ShutdownOnFailure()) {

            Subtask<Product> productTask =
                scope.fork(() -> productClient.findById(productId));

            Subtask<List<Review>> reviewsTask =
                scope.fork(() -> reviewClient.findByProduct(productId));

            Subtask<Integer> stockTask =
                scope.fork(() -> inventoryClient.getStock(productId));

            scope.joinUntil(Instant.now().plusSeconds(3))
                 .throwIfFailed();

            return new ProductAggregation(
                productTask.get(),
                reviewsTask.get(),
                stockTask.get()
            );

        } catch (TimeoutException e) {
            throw new GatewayTimeoutException("Aggregation timeout");
        } catch (ExecutionException e) {
            throw new ServiceException("Downstream servis xətası", e.getCause());
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new ServiceException("İnterrupt edildi");
        }
    }
}
```

---

## İntervyu Sualları

### 1. Structured Concurrency niyə əhəmiyyətlidir?
**Cavab:** `ExecutorService`-dən fərqli olaraq, tapşırıqların lifecycle-ı scope-a bağlıdır. Scope bağlananda tamamlanmamış tapşırıqlar avtomatik ləğv edilir — resource leak imkansız. Valideyn task xəta versə child task-lar avtomatik ləğv olur, child xəta versə `ShutdownOnFailure` ilə valideyn dayandırılır. Bu "tree structure" concurrency-ni daha proqnozlaşdırıla bilən edir.

### 2. ScopedValue vs ThreadLocal fərqi nədir?
**Cavab:** `ThreadLocal` mutable, cleanup tələb edir, thread pool-da pollute edə bilər. Virtual threads ilə per-thread yaddaş artır. `ScopedValue` immutable (rebind mümkün amma scope-a bağlı), cleanup avtomatik (scope bağlananda), Structured Concurrency ilə child task-lara avtomatik ötürülür. Virtual threads üçün tövsiyə edilir.

### 3. ShutdownOnFailure vs ShutdownOnSuccess nə vaxt?
**Cavab:** `ShutdownOnFailure` — bütün tapşırıqların uğurlu olması lazımdır (AND məntiqi). Məsələn: user + orders + analytics hamısı lazımdır. `ShutdownOnSuccess` — ilk uğurlu nəticə yetər (OR məntiqi). Məsələn: primary DB, replica, cache — hangisi əvvəl cavab versə al.

### 4. Structured Concurrency TaskLocal ilə necə işləyir?
**Cavab:** `ScopedValue` dəyərləri `scope.fork()` ilə yaranan child task-lara **avtomatik** ötürülür — əl ilə heç nə etmək lazım deyil. `ThreadLocal`-dan fərqli olaraq, dəyər üçüncü bir task tərəfindən dəyişdirilə bilməz (immutable) — thread-safety problemi yoxdur.

*Son yenilənmə: 2026-04-27*
