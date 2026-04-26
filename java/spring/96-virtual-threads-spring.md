# 96 — Virtual Threads — Spring Boot-da Praktik İstifadə

> **Seviyye:** Senior ⭐⭐⭐

## Mündəricat
1. [Virtual Threads nədir?](#virtual-threads-nədir)
2. [Spring Boot 3.2+ konfiqurasiyası](#spring-boot-32-konfiqurasiyası)
3. [HTTP Request handling](#http-request-handling)
4. [Async / @Async ilə fərq](#async--async-ilə-fərq)
5. [Pinning problemi](#pinning-problemi)
6. [Monitoring](#monitoring)
7. [Nə zaman istifadə etməli](#nə-zaman-istifadə-etməli)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Virtual Threads nədir?

Java 21 (Project Loom) ilə gəldi. OS thread-dən **çox daha yüngül** JVM thread-i.

```
Platform Thread (köhnə):
  - OS thread = ~1MB stack
  - 10k concurrent request = 10k OS thread = 10GB RAM
  - Thread pool limit var (Tomcat default: 200)
  - IO gözlədikdə thread blok olur, CPU-nu israf edir

Virtual Thread (yeni):
  - JVM thread = ~1KB stack
  - 1 milyon virtual thread eyni anda?  ← mümkündür
  - IO gözlədikdə carrier thread (OS thread) başqa virtual thread-ə keçir
  - Blok olmur, CPU israfı yoxdur
```

```
IO-bound workload:
  Platform Thread:  [Request]→[DB query wait...]→[Response]
                    Thread blokdadır, başqa iş etmir

  Virtual Thread:   [Request]→[DB query start]→[switch to other VT]
                              ↑ DB bitdi, geri qayıt, Response
  Carrier thread boş qalmır — başqa virtual thread-ə xidmət edir
```

**Xülasə:** Virtual threads reactive programming (WebFlux) olmadan, **sıradan blocking kod** yazaraq high concurrency əldə etməyə imkan verir.

---

## Spring Boot 3.2+ konfiqurasiyası

```properties
# application.properties — bu qədər kifayətdir:
spring.threads.virtual.enabled=true
```

Bu bir setting ilə:
- Tomcat virtual thread executor istifadə edir
- `@Async` metodlar virtual thread-də işləyir
- `@Scheduled` virtual thread-də işləyir
- Spring MVC hər request üçün virtual thread açır

### Manual konfiqurasiya (daha çox nəzarət):

```java
@Configuration
@EnableAsync
public class ThreadConfig {

    // Tomcat virtual thread executor:
    @Bean
    public TomcatProtocolHandlerCustomizer<?> protocolHandlerVirtualThreadExecutorCustomizer() {
        return protocolHandler -> {
            protocolHandler.setExecutor(Executors.newVirtualThreadPerTaskExecutor());
        };
    }

    // @Async üçün virtual thread executor:
    @Bean(name = "virtualThreadExecutor")
    public Executor virtualThreadExecutor() {
        return Executors.newVirtualThreadPerTaskExecutor();
    }

    // Scheduled tasks üçün:
    @Bean
    public SimpleAsyncTaskScheduler taskScheduler() {
        SimpleAsyncTaskScheduler scheduler = new SimpleAsyncTaskScheduler();
        scheduler.setVirtualThreads(true);
        return scheduler;
    }
}
```

---

## HTTP Request handling

Virtual threads ilə hər HTTP request öz virtual thread-ini alır. Blocking IO olsa belə, carrier thread bloklanmır.

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public UserDto getUser(@PathVariable Long id) {
        // Bu kod normal blocking-dir, amma virtual thread-də işləyir.
        // DB sorğusu gözlədikdə, carrier thread başqa virtual thread-ə keçir.
        User user = userRepo.findById(id).orElseThrow();    // blocking DB call
        String role = roleService.getRole(user.getId());     // blocking HTTP call
        return UserDto.fromEntity(user, role);
    }

    @PostMapping
    public ResponseEntity<UserDto> createUser(@RequestBody @Valid CreateUserRequest req) {
        // Heç nə dəyişmir — eyni blocking kod
        // Amma 10,000 concurrent request → 10,000 virtual thread, problemsiz
        User user = userService.create(req);
        return ResponseEntity.status(HttpStatus.CREATED).body(UserDto.fromEntity(user));
    }
}
```

### WebFlux ilə müqayisə:

```java
// WebFlux (reactive) — mürəkkəb, amma eyni concurrency:
@GetMapping("/reactive/{id}")
public Mono<UserDto> getUserReactive(@PathVariable Long id) {
    return userRepo.findById(id)
        .flatMap(user -> roleService.getRoleMono(user.getId())
            .map(role -> UserDto.fromEntity(user, role)));
}

// Virtual Threads (blocking, sadə) — eyni performans:
@GetMapping("/virtual/{id}")
public UserDto getUserVirtual(@PathVariable Long id) {
    User user = userRepo.findById(id).orElseThrow();
    String role = roleService.getRole(user.getId());
    return UserDto.fromEntity(user, role);
}
```

**Nəticə:** IO-bound workload-da virtual threads WebFlux ilə demək olar ki, eyni throughput göstərir — amma kod çox sadədir.

---

## Async / @Async ilə fərq

```java
// @Async — virtual threads aktiv olduqda virtual thread-də işləyir:
@Service
public class EmailService {

    @Async
    public CompletableFuture<Void> sendAsync(String to, String body) {
        emailProvider.send(to, body); // blocking call → virtual thread-də OK
        return CompletableFuture.completedFuture(null);
    }
}

@Service
public class OrderService {

    @Autowired
    private EmailService emailService;

    @Transactional
    public Order createOrder(CreateOrderRequest req) {
        Order order = orderRepo.save(new Order(req));

        // Transaction commit-dən sonra email gönder:
        @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
        emailService.sendAsync(order.getUserEmail(), "Order confirmed");

        return order;
    }
}

// Bir neçə asinxron əməliyyat:
public List<UserDto> enrichUsers(List<Long> ids) {
    List<CompletableFuture<UserDto>> futures = ids.stream()
        .map(id -> CompletableFuture.supplyAsync(
            () -> enrichSingleUser(id),
            Executors.newVirtualThreadPerTaskExecutor()
        ))
        .toList();

    return futures.stream()
        .map(CompletableFuture::join)
        .toList();
}
```

---

## Pinning problemi

Virtual thread "pinned" olduqda OS thread-ə bağlanır — carrier thread release olmur. Bu performansı azaldır.

**Pinning səbəbləri:**
1. `synchronized` block içində IO
2. Native method çağırışı (JNI)

```java
// Pinning yaradan kod:
public class DataStore {
    private synchronized void save(Data data) {
        database.insert(data); // IO gözlədikdə pinning!
    }
}

// Həll — synchronized → ReentrantLock:
public class DataStore {
    private final ReentrantLock lock = new ReentrantLock();

    private void save(Data data) {
        lock.lock();
        try {
            database.insert(data); // Artıq pinning yoxdur
        } finally {
            lock.unlock();
        }
    }
}
```

### Pinning-i aşkar etmək:

```bash
# JVM flag ilə pinning log:
-Djdk.tracePinnedThreads=full

# Nəticə:
# Thread[#42,ForkJoinPool-1-worker-1,5,CarrierThreads]
#     java.lang.VirtualThread[#100]/runnable@ForkJoinPool-1-worker-1
#         com.example.DataStore.save(DataStore.java:15) <== monitors:1
```

**Diqqət:** Spring/Hibernate bəzən internally `synchronized` istifadə edir. Java 21+ bu problemin bir hissəsini fix etdi. Java 24-də `synchronized` virtual thread-ə uyğun olacaq.

---

## Monitoring

```java
// Virtual thread sayını izləmək:
@Component
@Slf4j
public class VirtualThreadMonitor {

    @Scheduled(fixedRate = 5000)
    public void reportThreadStats() {
        ThreadMXBean mxBean = ManagementFactory.getThreadMXBean();
        log.info("Total threads: {}, Peak: {}",
            mxBean.getThreadCount(),
            mxBean.getPeakThreadCount());
    }
}

// application.properties ilə actuator:
management.endpoints.web.exposure.include=health,metrics,threads
# GET /actuator/threads → bütün thread məlumatları
```

```yaml
# Docker/K8s-də JVM flags:
JAVA_OPTS: >
  -XX:+UseZGC
  -Djdk.tracePinnedThreads=short
  -Xmx512m
```

---

## Nə zaman istifadə etməli

### Virtual threads ideal:

```
✅ IO-bound: DB sorğuları, HTTP calls, file I/O
✅ High concurrent request count (1000+)
✅ Microservice-lər arası çox HTTP call
✅ Sıradan Spring MVC app-lar
✅ Legacy blocking kod refactor etmədən scale etmək
```

### Virtual threads uyğun deyil:

```
❌ CPU-bound work (sorğu hesablama, image processing)
   → Platform thread pool daha effektivdir
❌ synchronized blokları çox olan libraries (Pinning problemi)
❌ Java 21-dən əvvəl
```

### WebFlux nə zaman seçilsin:

```
Virtual Threads seçin:
  → Sadəlik istəyirsinizsə
  → Team reactive programming bilmirsə
  → Legacy codebase-i qorumaq istəyirsinizsə

WebFlux seçin:
  → Backpressure lazımdırsa (stream throttling)
  → Real-time streaming (SSE, WebSocket)
  → Non-blocking DB (R2DBC)
  → Max performance, max control
```

---

## İntervyu Sualları

**S: Virtual thread vs platform thread əsas fərqi?**
C: Platform thread = 1 OS thread (~1MB). Virtual thread = JVM-managed lightweight thread (~1KB). IO gözlədikdə virtual thread carrier thread-i release edir; platform thread blok olur. 1M virtual thread mümkündür, 10K platform thread yox.

**S: Spring Boot-da virtual threads necə aktivdir?**
C: `spring.threads.virtual.enabled=true` bir property ilə. Bu Tomcat, @Async, @Scheduled-u virtual thread-ə keçirir.

**S: Pinning nədir, necə həll olunur?**
C: Virtual thread `synchronized` block içində IO gözlədikdə carrier OS thread-ə "pin" olur — release etmir. `synchronized` → `ReentrantLock` dəyişdirməklə aradan qaldırılır.

**S: Virtual threads WebFlux-u əvəz edirmi?**
C: IO-bound workload üçün demək olar ki, eyni performans. Amma WebFlux backpressure, streaming, non-blocking DB (R2DBC) üçün daha üstündür. Virtual threads sadəliyə imkan verir — reactive kod yazmadan.

**S: Virtual threads thread-safe-dirmi?**
C: Adi threading qaydaları eyni tətbiq olunur. `ThreadLocal` işləyir amma ScopedValue (Java 21+) daha effektivdir. `synchronized` pinning yaradır → `ReentrantLock` tövsiyə olunur.
