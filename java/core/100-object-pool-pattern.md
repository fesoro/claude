# Object Pool Pattern (Lead)

## İcmal

Object Pool — yaratması bahalı olan obyektləri dəfələrlə yaratmaq/məhv etmək əvəzinə, bir dəfə yaradıb pool-da saxlayan və lazım olanda borc verən (borrow), istifadədən sonra pool-a qaytarılan (release) design pattern-dir.

Java artıq bir çox resursu pool-da saxlayır: HikariCP DB connection-ları, `ThreadPoolExecutor` thread-ləri, HTTP client connection pool-u. Lakin bəzən custom pool lazım olur.

---

## Niyə Vacibdir

Obyekt yaratmaq bəzən çox bahalıdır:
- **DB connection**: TCP connection, authentication, SSL handshake — yüzlərlə ms
- **HTTP client instance**: connection pool initialize, SSL context, timeout konfiqurasiyası
- **Thread**: stack allocation, OS system call — onlarla ms
- **Heavy parser (XML/JSON/PDF)**: schema loading, validation initialization

Bu obyektləri hər request üçün yenidən yaratmaq əvəzinə pool-da saxlamaq latency-ni əhəmiyyətli dərəcədə azaldır.

---

## Əsas Anlayışlar

**Pool lifecycle:**
```
Pool başladılır → N obyekt yaradılır (minIdle)
Sorğu gəlir → pool-dan borrow et (idle varsa dərhal, yoxsa yenisini yarat və ya gözlə)
İstifadə tamamlanır → pool-a release et (idle-ə qayıt)
maxTotal-a çatılıbsa → release olana qədər gözlə (maxWait timeout)
```

**Key parametrlər:**

| Parametr | Mənası |
|---|---|
| `maxTotal` | Pool-da maksimum obyekt sayı |
| `minIdle` | Minimum idle (hazır gözləyən) sayı |
| `maxIdle` | Maksimum idle (artığı məhv edilir) |
| `maxWaitMillis` | Borrow üçün maksimum gözləmə vaxtı |
| `testOnBorrow` | Borrow öncə obyektin valid olub olmadığını yoxla |

**Soft pool vs Hard pool:**
- **Soft pool** (Go `sync.Pool`): GC istənilən vaxt pool-daki obyektləri məhv edə bilər. Temporary buffer-lər üçün.
- **Hard pool** (Java HikariCP, Commons Pool): Obyektlər explicitly close/destroy edilənə qədər pool-da qalır.

---

## Praktik Baxış

**Ne vaxt custom pool lazımdır:**
- Multi-tenant arxitektura: hər tenant üçün ayrı DB connection pool
- Xarici API client-ləri: rate limit olan API üçün maksimum N connection
- Expensive parser-lər: XML schema validator, PDF renderer
- Native resource-lar: JNI handles, native libraries

**Common mistakes:**
- Pool-dan borrow edib release etməmək (resource leak) — `try-finally` şərtdir
- `testOnBorrow=true` olmadan stale connection istifadə etmək
- Pool exhaustion-u handle etməmək (gözləmə timeout)
- `maxTotal`-ı çox böyük seçmək — backend sistemin öhdəsinə gəlmir

---

## Nümunələr

### Ümumi Nümunə

Multi-tenant SaaS: hər tenant-ın ayrı DB-si var. Hər sorğuda yeni connection yaratmaq yerinə, tenant-başına pool saxlanılır. Request gəldikdə tenant-ın pool-undan connection borrow edilir, request bitdikdə release edilir.

### Kod Nümunəsi

#### 1. Apache Commons Pool2 — Production-ready pool

```xml
<dependency>
    <groupId>org.apache.commons</groupId>
    <artifactId>commons-pool2</artifactId>
    <version>2.12.0</version>
</dependency>
```

**Custom pool — Expensive HTTP Client üçün:**

```java
import org.apache.commons.pool2.BasePooledObjectFactory;
import org.apache.commons.pool2.PooledObject;
import org.apache.commons.pool2.impl.DefaultPooledObject;
import org.apache.commons.pool2.impl.GenericObjectPool;
import org.apache.commons.pool2.impl.GenericObjectPoolConfig;

/**
 * Factory: pool-un obyektlərini necə yaradacağını, yoxlayacağını və məhv edəcəyini bilir.
 */
public class HttpClientFactory extends BasePooledObjectFactory<CloseableHttpClient> {

    private final String targetHost;
    private final int connectTimeout;

    @Override
    public CloseableHttpClient create() throws Exception {
        // Bahalı əməliyyat — yalnız pool genişlənəndə çağırılır
        log.info("Creating new HTTP client for pool: {}", targetHost);
        return HttpClients.custom()
            .setDefaultRequestConfig(RequestConfig.custom()
                .setConnectTimeout(Timeout.ofSeconds(connectTimeout))
                .setResponseTimeout(Timeout.ofSeconds(30))
                .build())
            .setConnectionManager(PoolingHttpClientConnectionManagerBuilder.create()
                .setMaxConnPerRoute(5)
                .build())
            .build();
    }

    @Override
    public PooledObject<CloseableHttpClient> wrap(CloseableHttpClient client) {
        return new DefaultPooledObject<>(client);
    }

    @Override
    public boolean validateObject(PooledObject<CloseableHttpClient> pooledObject) {
        // testOnBorrow=true olduqda borrow öncə çağırılır
        CloseableHttpClient client = pooledObject.getObject();
        try {
            // Sadə health check — OPTIONS sorğusu göndər
            return client.execute(new HttpOptions(targetHost), response -> {
                return response.getCode() < 500;
            });
        } catch (Exception e) {
            return false; // Invalid — pool bu obyekti məhv edib yenisini yaradacaq
        }
    }

    @Override
    public void destroyObject(PooledObject<CloseableHttpClient> pooledObject) throws Exception {
        // Pool-dan çıxarılarkən resursu təmizlə
        pooledObject.getObject().close();
        log.info("HTTP client destroyed");
    }
}
```

**Pool konfiqurasiyası:**

```java
@Configuration
public class HttpClientPoolConfig {

    @Bean
    public GenericObjectPool<CloseableHttpClient> paymentApiClientPool() {
        HttpClientFactory factory = new HttpClientFactory("https://api.payment.com", 5);

        GenericObjectPoolConfig<CloseableHttpClient> config = new GenericObjectPoolConfig<>();
        config.setMaxTotal(20);              // Maksimum 20 client
        config.setMinIdle(5);               // Həmişə minimum 5 hazır olsun
        config.setMaxIdle(10);              // 10-dan çox idle varsa məhv et
        config.setMaxWait(Duration.ofSeconds(3)); // 3s gözlə, sonra exception
        config.setTestOnBorrow(true);       // Borrow öncə validate et
        config.setTestWhileIdle(true);      // Idle olanları arxa planda test et
        config.setTimeBetweenEvictionRuns(Duration.ofSeconds(30));

        return new GenericObjectPool<>(factory, config);
    }
}
```

**Pool istifadəsi:**

```java
@Service
@Slf4j
public class PaymentApiService {

    private final GenericObjectPool<CloseableHttpClient> clientPool;

    public PaymentResponse charge(PaymentRequest request) {
        CloseableHttpClient client = null;
        try {
            // Pool-dan borrow et — idle client yoxdursa gözlə (maxWait)
            client = clientPool.borrowObject();

            HttpPost post = new HttpPost("/v1/charges");
            post.setEntity(new StringEntity(toJson(request)));
            post.setHeader("Content-Type", "application/json");
            post.setHeader("Authorization", "Bearer " + apiKey);

            return client.execute(post, response -> {
                return parseResponse(response, PaymentResponse.class);
            });

        } catch (NoSuchElementException e) {
            // maxWait bitdi — pool exhausted
            throw new ServiceUnavailableException("Payment service overloaded", e);

        } catch (Exception e) {
            // Client problemlidir — pool-a qayıtmasın
            if (client != null) {
                clientPool.invalidateObject(client);
                client = null; // finally-də release etmə
            }
            throw new PaymentException("Payment API call failed", e);

        } finally {
            // Həmişə release et — try-finally şərtdir
            if (client != null) {
                try {
                    clientPool.returnObject(client);
                } catch (Exception e) {
                    log.error("Failed to return client to pool", e);
                }
            }
        }
    }
}
```

---

#### 2. DIY Pool — BlockingQueue ilə

```java
/**
 * Sadə, generic object pool.
 * Production üçün Commons Pool2 daha yaxşıdır,
 * lakin mexanizmi anlamaq üçün faydalıdır.
 */
public class SimpleObjectPool<T> implements AutoCloseable {

    private final ArrayBlockingQueue<T> pool;
    private final Supplier<T> factory;
    private final Consumer<T> destroyer;
    private final Duration borrowTimeout;
    private final AtomicInteger totalCreated = new AtomicInteger(0);

    public SimpleObjectPool(int size,
                            Supplier<T> factory,
                            Consumer<T> destroyer,
                            Duration borrowTimeout) {
        this.pool = new ArrayBlockingQueue<>(size);
        this.factory = factory;
        this.destroyer = destroyer;
        this.borrowTimeout = borrowTimeout;

        // Pool-u əvvəlcədən doldur
        for (int i = 0; i < size; i++) {
            pool.offer(factory.get());
            totalCreated.incrementAndGet();
        }
    }

    /**
     * Pool-dan bir obyekt al.
     * @throws PoolExhaustedException əgər timeout bitərsə
     */
    public T borrow() {
        try {
            T obj = pool.poll(borrowTimeout.toMillis(), TimeUnit.MILLISECONDS);
            if (obj == null) {
                throw new PoolExhaustedException(
                    "No objects available in pool after " + borrowTimeout);
            }
            return obj;
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new RuntimeException("Interrupted while waiting for pool object", e);
        }
    }

    /**
     * Obyekti pool-a qaytar.
     * @param valid false olduqda obyekt məhv edilir, pool genişlənmir
     */
    public void release(T obj, boolean valid) {
        if (valid) {
            if (!pool.offer(obj)) {
                // Pool dolu (bu nadir hallarda olur) — məhv et
                destroyer.accept(obj);
            }
        } else {
            // Xarab obyekt — məhv et
            destroyer.accept(obj);
        }
    }

    /**
     * try-with-resources dəstəyi üçün PooledObject wrapper.
     */
    public PooledObject<T> borrowAutoCloseable() {
        T obj = borrow();
        return new PooledObject<>(obj, this);
    }

    @Override
    public void close() {
        T obj;
        while ((obj = pool.poll()) != null) {
            destroyer.accept(obj);
        }
    }

    public int availableCount() {
        return pool.size();
    }

    public int totalCreated() {
        return totalCreated.get();
    }
}
```

**AutoCloseable wrapper — try-with-resources üçün:**

```java
public class PooledObject<T> implements AutoCloseable {

    private final T object;
    private final SimpleObjectPool<T> pool;
    private boolean valid = true;

    PooledObject(T object, SimpleObjectPool<T> pool) {
        this.object = object;
        this.pool = pool;
    }

    public T get() {
        return object;
    }

    public void invalidate() {
        this.valid = false;
    }

    @Override
    public void close() {
        pool.release(object, valid);
    }
}
```

**İstifadəsi (try-with-resources):**

```java
@Service
public class XmlValidationService {

    // XML Schema validator — yaratması bahalıdır
    private final SimpleObjectPool<Validator> validatorPool;

    public XmlValidationService() throws Exception {
        Schema schema = SchemaFactory.newDefaultInstance()
            .newSchema(getClass().getResource("/schema/order.xsd"));

        this.validatorPool = new SimpleObjectPool<>(
            10,                          // 10 validator
            schema::newValidator,        // factory: yeni validator yarat
            v -> {},                     // destroyer: heç nə etmə (thread-safe deyil)
            Duration.ofSeconds(5)        // 5s gözlə
        );
    }

    public ValidationResult validate(String xmlContent) throws Exception {
        // try-with-resources: validation bitdikdə avtomatik pool-a qayıt
        try (PooledObject<Validator> pooled = validatorPool.borrowAutoCloseable()) {
            Validator validator = pooled.get();
            try {
                validator.validate(new StreamSource(new StringReader(xmlContent)));
                return ValidationResult.valid();
            } catch (SAXException e) {
                return ValidationResult.invalid(e.getMessage());
            } catch (Exception e) {
                // Validator xarab ola bilər — invalidate et
                pooled.invalidate();
                throw e;
            } finally {
                validator.reset(); // Növbəti istifadəçi üçün sıfırla
            }
        }
    }
}
```

---

#### 3. HikariCP Internals — Anlayış

```yaml
# application.yml — HikariCP konfiqurasiyası
spring:
  datasource:
    hikari:
      maximum-pool-size: 20        # maxTotal — DB-nin qaldıra biləcəyi qədər
      minimum-idle: 5              # minIdle — həmişə hazır connection-lar
      connection-timeout: 3000     # 3s borrow timeout
      idle-timeout: 600000         # 10 dəq idle connection-ları bağla
      max-lifetime: 1800000        # 30 dəq — connection-u yenilə (DB-nin timeout-unu keç)
      keepalive-time: 30000        # 30s-də bir "SELECT 1" göndər (stale connection-ın qarşısını al)
      pool-name: "MainHikariPool"
```

**Pool metrics — Actuator ilə:**

```yaml
management:
  metrics:
    enable:
      hikari: true
```

Prometheus-da:
```
hikaricp_connections_active       # Hazırda istifadədə olan connection sayı
hikaricp_connections_idle         # Gözləyən connection sayı
hikaricp_connections_pending      # Pool-dan connection gözləyən thread sayı
hikaricp_connections_timeout_total # Timeout olan borrow cəhdləri
hikaricp_connection_acquisition_seconds # Borrow üçün gözləmə vaxtı
```

---

#### 4. Spring @Bean kimi Pool

```java
@Configuration
public class PoolConfiguration {

    @Bean(destroyMethod = "close")
    public SimpleObjectPool<PdfRenderer> pdfRendererPool() {
        return new SimpleObjectPool<>(
            5,
            PdfRenderer::new,
            PdfRenderer::close,
            Duration.ofSeconds(10)
        );
    }

    // Pool metrics Actuator-da görünsün
    @Bean
    public MeterBinder pdfPoolMetrics(SimpleObjectPool<PdfRenderer> pool) {
        return registry -> {
            Gauge.builder("pdf.pool.available", pool, SimpleObjectPool::availableCount)
                .description("Available PDF renderers in pool")
                .register(registry);

            Gauge.builder("pdf.pool.total", pool, SimpleObjectPool::totalCreated)
                .description("Total PDF renderers created")
                .register(registry);
        };
    }
}
```

---

#### 5. Go sync.Pool ilə müqayisə

```go
// Go sync.Pool — soft pool, GC istənilən vaxt təmizləyə bilər
var bufferPool = sync.Pool{
    New: func() any {
        return make([]byte, 0, 4096)
    },
}

func processRequest(data []byte) {
    buf := bufferPool.Get().([]byte)
    defer bufferPool.Put(buf[:0]) // Sıfırlayıb qaytar

    // buf istifadə et
}
```

Java ekvivalenti `ThreadLocal` ilə (fərqli semantika):

```java
// Java-da sync.Pool analoquna en yaxın: ThreadLocal buffer
private static final ThreadLocal<ByteArrayOutputStream> BUFFER_POOL =
    ThreadLocal.withInitial(() -> new ByteArrayOutputStream(4096));

public byte[] serialize(Object obj) throws IOException {
    ByteArrayOutputStream buf = BUFFER_POOL.get();
    buf.reset(); // Əvvəlki datanı sil
    try (ObjectOutputStream oos = new ObjectOutputStream(buf)) {
        oos.writeObject(obj);
    }
    return buf.toByteArray();
    // buf pool-a "qayıtmır" — ThreadLocal-da qalır
}
```

**Fərq:** Go `sync.Pool` GC-friendly (GC götürə bilər), Java pool-lar əksər hallarda hard (explicit lifecycle).

---

## Praktik Tapşırıqlar

1. **Benchmark:** JMH ilə iki versiya müqayisə et:
   - Hər sorğuda `new CloseableHttpClient()` yarat
   - Pool-dan borrow et
   1000 req/s altında latency fərqini ölç.

2. **Commons Pool2 ilə custom pool:** Expensive XML Schema validator üçün pool yarat. `maxTotal=5`, `maxWait=2s`. 10 parallel thread ilə test et — 6-cı thread 2s gözlədikdən sonra exception almalıdır.

3. **Pool exhaustion simulyasiyası:** Borrow edib release etməyən bir service yaz. Pool tükəndikdən sonra `PoolExhaustedException`-ı müşahidə et. `pool.getNumActive()` vs `pool.getNumIdle()` metric-lərini izlə.

4. **Multi-tenant DB pool:** Hər tenant üçün ayrı HikariCP pool saxlayan `TenantAwareDataSource` yarat. Tenant-ı thread-local-dan oxu, müvafiq pool-dan connection götür.

5. **Health indicator:** `/actuator/health`-ə custom pool health check əlavə et. Pool `maxTotal`-ın 80%-ni aşdıqda `DOWN` qaytar.

---

## Əlaqəli Mövzular

- `java/spring/108-singleflight-request-coalescing.md` — Resource contention azaltmaq üçün digər pattern
- `java/spring/106-background-jobs-patterns.md` — Thread pool konfiqurasiyası
- `java/comparison/frameworks/` — HikariCP vs DBCP2 müqayisəsi
- `java/core/` — AutoCloseable, try-with-resources
