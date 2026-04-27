# Multi-tenancy Patterns (Lead)

> **Seviyye:** Lead ⭐⭐⭐⭐

## İcmal

**Multi-tenancy** — tək tətbiq instance-ının bir neçə müştərini (tenant) izolə edilmiş şəkildə xidmət etməsi. SaaS məhsulların əsas arxitektura qərarlarından biri.

---

## Niyə Vacibdir

```
Ssenariy: B2B SaaS CRM tətbiqi
  → Müştəri A: 500 işçi, öz data-sı
  → Müştəri B: 2000 işçi, tamamilə fərqli data
  → Müştəri C: EU-da (GDPR tələbləri)

Tələblər:
  ✓ Müştəri A digərinin data-sını görə bilməməlidir
  ✓ Bir müştərinin yük artımı digərinə təsir etməməlidir (ideally)
  ✓ Schema dəyişikliyi deployment-sız olmalıdır (yaxud idarə olunan)
  ✓ Hər müştəri üçün ayrıca instance deploy etmək mümkün olmamalıdır (cost)
```

---

## 3 Əsas Pattern

### 1. Row-Level Multi-tenancy (Single Database)

```
Bir DB, bir schema, hər cədvəldə `tenant_id` sütunu

users table:
  id | tenant_id | email           | name
  1  | tenant-A  | ali@companyA.com | Ali
  2  | tenant-B  | sara@companyB.com| Sara
  3  | tenant-A  | veli@companyA.com| Vəli

↑ Ən sadə, ən ucuz, amma izolasiya ən zəif
```

### 2. Schema-per-Tenant

```
Bir DB, hər tenant üçün ayrı PostgreSQL schema

Database: myapp_db
  Schema: tenant_company_a → users, orders, products
  Schema: tenant_company_b → users, orders, products
  Schema: tenant_company_c → users, orders, products
  Schema: shared           → plans, billing

↑ Orta izolasiya, orta xərc, bir DB-dən idarə
```

### 3. Database-per-Tenant

```
Hər tenant üçün tam ayrı database/server

  tenant-a.db.example.com → PostgreSQL instance A
  tenant-b.db.example.com → PostgreSQL instance B

↑ Ən güclü izolasiya, ən yüksək xərc, enterprise/regulated müştərilər üçün
```

---

## Row-Level: Spring + Hibernate Implementasiyası

### Tenant Konteksti

```java
// Cari tenant-i thread-local saxla
public class TenantContext {

    private static final ThreadLocal<String> currentTenant = new InheritableThreadLocal<>();

    public static void setTenant(String tenantId) {
        currentTenant.set(tenantId);
    }

    public static String getTenant() {
        String tenant = currentTenant.get();
        if (tenant == null) throw new IllegalStateException("Tenant ayarlanmayıb");
        return tenant;
    }

    public static void clear() {
        currentTenant.remove();
    }
}
```

### Filter — Request-dən Tenant Çıxarma

```java
@Component
@Order(1)
public class TenantFilter implements Filter {

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
                         FilterChain chain) throws IOException, ServletException {

        HttpServletRequest httpRequest = (HttpServletRequest) request;

        // Tenant-i müxtəlif yollarla müəyyən etmək:
        // 1. JWT claim-dən
        // 2. Subdomain-dən (company-a.myapp.com)
        // 3. Custom header-dan

        String tenantId = resolveTenantId(httpRequest);
        TenantContext.setTenant(tenantId);

        try {
            chain.doFilter(request, response);
        } finally {
            TenantContext.clear(); // Mütləq təmizlə!
        }
    }

    private String resolveTenantId(HttpServletRequest request) {
        // Subdomain-dən: company-a.myapp.com → "company-a"
        String host = request.getServerName();
        String[] parts = host.split("\\.");
        if (parts.length >= 3) return parts[0];

        // JWT-dən (Spring Security ilə)
        String authHeader = request.getHeader("Authorization");
        if (authHeader != null && authHeader.startsWith("Bearer ")) {
            return jwtService.extractTenantId(authHeader.substring(7));
        }

        // Header-dan
        String tenantHeader = request.getHeader("X-Tenant-Id");
        if (tenantHeader != null) return tenantHeader;

        throw new MissingTenantException("Tenant müəyyən edilə bilmədi");
    }
}
```

### Hibernate Filter ilə Row-Level Filterleme

```java
// Entity-də filter annotation
@Entity
@Table(name = "orders")
@FilterDef(name = "tenantFilter",
           parameters = @ParamDef(name = "tenantId", type = String.class))
@Filter(name = "tenantFilter", condition = "tenant_id = :tenantId")
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "tenant_id", nullable = false)
    private String tenantId;

    private String product;
    private BigDecimal amount;
}
```

```java
// Filter-i aktivləşdir
@Repository
public class OrderRepository {

    @PersistenceContext
    private EntityManager em;

    public List<Order> findAll() {
        // Filter aktiv olduqda WHERE tenant_id = ? avtomatik əlavə olunur
        return em.createQuery("SELECT o FROM Order o", Order.class)
                 .getResultList();
    }
}

// AOP ilə filter aktivləşdirmə
@Aspect
@Component
public class TenantFilterAspect {

    @PersistenceContext
    private EntityManager em;

    @Around("@annotation(org.springframework.transaction.annotation.Transactional)")
    public Object applyTenantFilter(ProceedingJoinPoint pjp) throws Throwable {
        Session session = em.unwrap(Session.class);
        session.enableFilter("tenantFilter")
               .setParameter("tenantId", TenantContext.getTenant());

        try {
            return pjp.proceed();
        } finally {
            session.disableFilter("tenantFilter");
        }
    }
}
```

---

## Schema-per-Tenant: Spring + HikariCP

```java
// AbstractRoutingDataSource — request-ə görə DataSource seç
@Component
public class TenantRoutingDataSource extends AbstractRoutingDataSource {

    @Override
    protected Object determineCurrentLookupKey() {
        return TenantContext.getTenant(); // "tenant-a", "tenant-b"
    }
}
```

```java
// DataSource konfiqurasiyası
@Configuration
public class DataSourceConfig {

    @Autowired
    private TenantProperties tenantProperties;

    @Bean
    @Primary
    public DataSource dataSource() {
        TenantRoutingDataSource routingDataSource = new TenantRoutingDataSource();

        Map<Object, Object> targetDataSources = new HashMap<>();

        // Hər tenant üçün ayrı connection pool
        tenantProperties.getTenants().forEach(tenantId -> {
            HikariDataSource ds = buildDataSource(tenantId);
            targetDataSources.put(tenantId, ds);
        });

        // Default (naməlum tenant üçün)
        HikariDataSource defaultDs = buildDataSource("default");
        routingDataSource.setDefaultTargetDataSource(defaultDs);
        routingDataSource.setTargetDataSources(targetDataSources);
        routingDataSource.afterPropertiesSet();

        return routingDataSource;
    }

    private HikariDataSource buildDataSource(String tenantId) {
        HikariConfig config = new HikariConfig();

        // Schema-per-tenant: hər tenant üçün fərqli schema
        config.setJdbcUrl("jdbc:postgresql://localhost:5432/myapp");
        config.setUsername("app_user");
        config.setPassword("password");
        config.setSchema("tenant_" + tenantId);  // PostgreSQL schema-sı

        // Connection pool per-tenant
        config.setMaximumPoolSize(10);
        config.setMinimumIdle(2);
        config.setPoolName("HikariPool-" + tenantId);

        return new HikariDataSource(config);
    }
}
```

### Flyway — Tenant Schema Migration

```java
// Yeni tenant yaradıldıqda schema migration
@Service
public class TenantProvisioningService {

    private final DataSource adminDataSource;
    private final DataSourceConfig dataSourceConfig;

    @Transactional
    public void provisionTenant(String tenantId) {
        // 1. Schema yarat
        createSchema(tenantId);

        // 2. Migration tətbiq et
        Flyway flyway = Flyway.configure()
            .dataSource(adminDataSource)
            .schemas("tenant_" + tenantId)
            .locations("classpath:db/migration/tenant")
            .load();
        flyway.migrate();

        // 3. DataSource-a əlavə et (runtime-da)
        dataSourceConfig.addTenant(tenantId);

        log.info("Tenant {} uğurla yaradıldı", tenantId);
    }

    private void createSchema(String tenantId) {
        String schemaDdl = "CREATE SCHEMA IF NOT EXISTS tenant_" + tenantId.replaceAll("[^a-z0-9_]", "");
        jdbcTemplate.execute(schemaDdl);
    }
}
```

---

## Tenant Resolution Strategiyaları

```java
// Subdomain: company-a.myapp.com
String subdomain = host.split("\\.")[0]; // "company-a"

// JWT claim
Claims claims = jwtParser.parseClaimsJws(token).getBody();
String tenantId = claims.get("tenant_id", String.class);

// Custom header
String tenantId = request.getHeader("X-Tenant-Id");

// Path prefix: /api/tenant-a/orders
String tenantId = request.getRequestURI().split("/")[2]; // güvənli deyil

// API key (B2B API üçün)
ApiKey apiKey = apiKeyRepository.findByKey(request.getHeader("X-Api-Key"));
String tenantId = apiKey.getTenantId();
```

---

## Trade-off Cədvəli

| Xüsusiyyət | Row-Level | Schema-per-Tenant | DB-per-Tenant |
|-----------|-----------|-------------------|---------------|
| İzolasiya | Zəif | Orta | Güclü |
| Xərc | Ucuz | Orta | Bahalı |
| Performans | Tenant index lazımdır | Yaxşı | Mükəmməl |
| Miqrasiya | Asan | Orta | Çətin |
| Compliance | Çətin (GDPR) | Mümkün | Tam |
| Tenant sayı | Minlərlə | Yüzlərlə | Onlarla |

---

## Praktik Baxış

**Ümumi xətalar:**
```java
// ❌ TenantContext.clear() unutmaq — bir sonrakı request polluted context alır
chain.doFilter(request, response);
TenantContext.clear(); // finally blokda olmalıdır!

// ❌ Row-level-da WHERE tenant_id=? unutmaq
// Bir developer tenant filter-i bypass edir → başqa tenantın data-sı görünür
// Həll: Hibernate @Filter, yaxud Spring Data Specification default-u

// ❌ Async metodlarda TenantContext itir
@Async
public void sendEmail(String email) {
    String tenant = TenantContext.getTenant(); // NULL! — async thread-local deyil
    // Həll: TaskDecorator ilə context-i ötür
}
```

```java
// Async üçün TaskDecorator
@Configuration
public class AsyncConfig implements AsyncConfigurer {

    @Override
    public Executor getAsyncExecutor() {
        ThreadPoolTaskExecutor executor = new ThreadPoolTaskExecutor();
        executor.setTaskDecorator(runnable -> {
            String tenantId = TenantContext.getTenant(); // Əsas thread-dən al
            return () -> {
                TenantContext.setTenant(tenantId);       // Yeni thread-ə ötür
                try {
                    runnable.run();
                } finally {
                    TenantContext.clear();
                }
            };
        });
        executor.initialize();
        return executor;
    }
}
```

---

## İntervyu Sualları

### 1. Hansı multi-tenancy pattern-i seçərsiniz?
**Cavab:** Tələbdən asılıdır. Startup/MVP — row-level (sürətli, ucuz). B2B SaaS, data izolasiyası vacibdirsə — schema-per-tenant. Regulated industries (healthcare, banking), compliance (SOC2, GDPR data residency) — database-per-tenant. Hibrid: enterprise müştərilər üçün DB-per-tenant, SMB üçün schema-per-tenant.

### 2. Row-level-da index necə qurulmalıdır?
**Cavab:** `tenant_id` bütün query-lərin başında olacaq — compound index lazımdır. Məsələn: `CREATE INDEX idx_orders_tenant_status ON orders(tenant_id, status)`. Yalnız `tenant_id` indeksi yetər deyil — `status = 'PENDING'` query-si `tenant_id + status` composite index-dən istifadə edər. Partial index də ola bilər: `WHERE tenant_id = 'company-a'`.

### 3. TenantContext-i async əməliyyatlarda necə ötürürsünüz?
**Cavab:** `ThreadLocal` yalnız cari thread-ə aiddir — `@Async`, `CompletableFuture`, virtual threads ilə itir. Həll: (1) Spring `TaskDecorator` — executor-a decorator əlavə et, əsas thread-dən dəyəri yeni thread-ə kopyala. (2) `ScopedValue` (Java 21) — `StructuredTaskScope.fork()` ilə child task-lara avtomatik ötürülür. (3) `InheritableThreadLocal` — child thread-ə kopyalanır, amma thread pool-da çalışmır.

*Son yenilənmə: 2026-04-27*
