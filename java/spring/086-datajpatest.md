# 086 — Spring @DataJpaTest — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [@DataJpaTest nədir?](#datajpatest-nədir)
2. [TestEntityManager](#testentitymanager)
3. [Repository testləri](#repository-testləri)
4. [Custom query testləri](#custom-query-testləri)
5. [Pagination və Sorting testləri](#pagination-və-sorting-testləri)
6. [Real database ilə test](#real-database-ilə-test)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @DataJpaTest nədir?

`@DataJpaTest` — yalnız JPA layer-i yükləyir: Entity-lər, Repository-lər, JPA konfiqurasiyası. Embedded H2 database istifadə edir. Hər test `@Transactional` — rollback olunur.

```java
// ─── Yüklənənlər ──────────────────────────────────────
// @Entity siniflər
// @Repository interfeyslər (JpaRepository)
// EntityManager, TestEntityManager
// DataSource (H2 embedded)
// JPA/Hibernate konfigurasiyası

// ─── Yüklənməyənlər ──────────────────────────────────
// @Service, @Component
// @Controller
// Spring Security
// Kafka, Redis, Mail

@DataJpaTest
class BasicJpaTest {

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private TestEntityManager entityManager;

    @Test
    void shouldSaveAndRetrieveOrder() {
        // Yarat
        Order order = Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .totalAmount(new BigDecimal("149.99"))
            .build();

        // Saxla
        Order saved = orderRepository.save(order);
        entityManager.flush(); // DB-yə yaz
        entityManager.clear(); // L1 cache-i təmizlə

        // Tap
        Optional<Order> found = orderRepository.findById(saved.getId());

        assertTrue(found.isPresent());
        assertEquals("customer-1", found.get().getCustomerId());
        assertEquals(OrderStatus.PENDING, found.get().getStatus());
    }
}
```

---

## TestEntityManager

```java
@DataJpaTest
class TestEntityManagerTest {

    @Autowired
    private TestEntityManager entityManager;

    @Autowired
    private OrderRepository orderRepository;

    // ─── persist() — ID qaytarmır (JPA persist semantikası) ─
    @Test
    void persistExample() {
        Order order = Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build();

        entityManager.persist(order); // Persist et (ID generasiya)
        entityManager.flush();        // SQL yaz

        assertNotNull(order.getId()); // ID set oldu
    }

    // ─── persistAndFlush() — persist + flush birlikdə ─
    @Test
    void persistAndFlushExample() {
        Order order = Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build();

        Order persisted = entityManager.persistAndFlush(order);

        assertNotNull(persisted.getId());
    }

    // ─── persistFlushFind() — persist + flush + find ──
    @Test
    void persistFlushFindExample() {
        Order order = Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build();

        // DB-yə yaz, cache-i keç, DB-dən oxu
        Order found = entityManager.persistFlushFind(order);

        assertNotNull(found.getId());
        assertEquals("customer-1", found.getCustomerId());
    }

    // ─── find() — ID ilə tap ──────────────────────────
    @Test
    void findExample() {
        Order persisted = entityManager.persistAndFlush(
            Order.builder().customerId("c1").status(OrderStatus.PENDING).build()
        );
        entityManager.clear(); // Cache-i temizlə

        Order found = entityManager.find(Order.class, persisted.getId());

        assertNotNull(found);
        assertEquals("c1", found.getCustomerId());
    }

    // ─── flush() və clear() ───────────────────────────
    @Test
    void flushAndClearExample() {
        Order order = entityManager.persist(
            Order.builder().customerId("c1").status(OrderStatus.PENDING).build()
        );

        entityManager.flush(); // Pending SQL-leri icra et
        entityManager.clear(); // First-level cache-i boşalt

        // Yenidən yükləndikdə DB-dən gəlir (cache yox)
        Optional<Order> reloaded = orderRepository.findById(order.getId());
        assertTrue(reloaded.isPresent());
    }
}
```

---

## Repository testləri

```java
@DataJpaTest
class OrderRepositoryTest {

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private TestEntityManager entityManager;

    // ─── CRUD ─────────────────────────────────────────
    @Test
    void shouldSaveOrder() {
        Order order = buildOrder("customer-1", OrderStatus.PENDING);
        Order saved = orderRepository.save(order);

        assertNotNull(saved.getId());
        assertTrue(saved.getId() > 0);
    }

    @Test
    void shouldFindById() {
        Order persisted = entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));

        Optional<Order> found = orderRepository.findById(persisted.getId());

        assertTrue(found.isPresent());
    }

    @Test
    void shouldReturnEmptyForNonExistingId() {
        Optional<Order> found = orderRepository.findById(9999L);
        assertTrue(found.isEmpty());
    }

    @Test
    void shouldUpdateOrder() {
        Order order = entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));

        order.setStatus(OrderStatus.CONFIRMED);
        orderRepository.save(order);
        entityManager.flush();
        entityManager.clear();

        Order updated = orderRepository.findById(order.getId()).orElseThrow();
        assertEquals(OrderStatus.CONFIRMED, updated.getStatus());
    }

    @Test
    void shouldDeleteOrder() {
        Order order = entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));
        Long id = order.getId();

        orderRepository.deleteById(id);
        entityManager.flush();

        assertFalse(orderRepository.existsById(id));
    }

    // ─── findAll ──────────────────────────────────────
    @Test
    void shouldFindAllOrders() {
        entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));
        entityManager.persistAndFlush(buildOrder("c2", OrderStatus.CONFIRMED));
        entityManager.persistAndFlush(buildOrder("c3", OrderStatus.SHIPPED));

        List<Order> all = orderRepository.findAll();

        assertEquals(3, all.size());
    }

    // ─── count / exists ───────────────────────────────
    @Test
    void shouldCountAndCheckExistence() {
        entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));

        assertEquals(1, orderRepository.count());
        assertTrue(orderRepository.existsById(1L));
    }

    // ─── Derived query method-lar ─────────────────────
    @Test
    void shouldFindByCustomerId() {
        entityManager.persistAndFlush(buildOrder("customer-1", OrderStatus.PENDING));
        entityManager.persistAndFlush(buildOrder("customer-1", OrderStatus.CONFIRMED));
        entityManager.persistAndFlush(buildOrder("customer-2", OrderStatus.PENDING));

        List<Order> orders = orderRepository.findByCustomerId("customer-1");

        assertEquals(2, orders.size());
        assertTrue(orders.stream().allMatch(o -> "customer-1".equals(o.getCustomerId())));
    }

    @Test
    void shouldFindByStatus() {
        entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));
        entityManager.persistAndFlush(buildOrder("c2", OrderStatus.PENDING));
        entityManager.persistAndFlush(buildOrder("c3", OrderStatus.SHIPPED));

        List<Order> pending = orderRepository.findByStatus(OrderStatus.PENDING);

        assertEquals(2, pending.size());
    }

    // Helper
    private Order buildOrder(String customerId, OrderStatus status) {
        return Order.builder()
            .customerId(customerId)
            .status(status)
            .totalAmount(new BigDecimal("99.99"))
            .createdAt(Instant.now())
            .build();
    }
}
```

---

## Custom query testləri

```java
@DataJpaTest
class CustomQueryTest {

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private TestEntityManager entityManager;

    // ─── @Query (JPQL) ────────────────────────────────
    @Test
    void shouldFindOrdersByDateRange() {
        Instant start = Instant.parse("2026-01-01T00:00:00Z");
        Instant end = Instant.parse("2026-12-31T23:59:59Z");

        // Test data
        Order jan = buildOrderAt("c1", Instant.parse("2026-01-15T10:00:00Z"));
        Order mar = buildOrderAt("c2", Instant.parse("2026-03-20T10:00:00Z"));
        Order prev = buildOrderAt("c3", Instant.parse("2025-12-31T23:59:59Z"));

        entityManager.persistAndFlush(jan);
        entityManager.persistAndFlush(mar);
        entityManager.persistAndFlush(prev);

        List<Order> orders = orderRepository.findByCreatedAtBetween(start, end);

        assertEquals(2, orders.size());
    }

    // ─── @Query (Native SQL) ──────────────────────────
    @Test
    void shouldGetOrderCountByStatus() {
        entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));
        entityManager.persistAndFlush(buildOrder("c2", OrderStatus.PENDING));
        entityManager.persistAndFlush(buildOrder("c3", OrderStatus.CONFIRMED));

        List<Object[]> counts = orderRepository.countGroupByStatus();

        assertFalse(counts.isEmpty());
        // Verify PENDING count = 2
        Optional<Object[]> pendingRow = counts.stream()
            .filter(row -> "PENDING".equals(row[0].toString()))
            .findFirst();
        assertTrue(pendingRow.isPresent());
        assertEquals(2L, ((Number) pendingRow.get()[1]).longValue());
    }

    // ─── Projection ───────────────────────────────────
    @Test
    void shouldReturnProjection() {
        entityManager.persistAndFlush(Order.builder()
            .customerId("c1")
            .status(OrderStatus.PENDING)
            .totalAmount(new BigDecimal("149.99"))
            .build());

        List<OrderSummary> summaries = orderRepository.findOrderSummaries();

        assertFalse(summaries.isEmpty());
        assertNotNull(summaries.get(0).getId());
        assertNotNull(summaries.get(0).getStatus());
        // totalAmount yoxdur — projection-da deyil
    }

    // ─── Modifying query ──────────────────────────────
    @Test
    @Commit  // Rollback etmə — modifying query test edir
    void shouldBulkUpdateStatus() {
        Order o1 = entityManager.persistAndFlush(buildOrder("c1", OrderStatus.PENDING));
        Order o2 = entityManager.persistAndFlush(buildOrder("c2", OrderStatus.PENDING));
        Order o3 = entityManager.persistAndFlush(buildOrder("c3", OrderStatus.CONFIRMED));
        entityManager.clear();

        int updated = orderRepository.updateStatusByPreviousStatus(
            OrderStatus.CONFIRMED, OrderStatus.PENDING);

        assertEquals(2, updated); // Yalnız PENDING-lər

        assertEquals(OrderStatus.CONFIRMED,
            orderRepository.findById(o1.getId()).get().getStatus());
        assertEquals(OrderStatus.CONFIRMED,
            orderRepository.findById(o2.getId()).get().getStatus());
        assertEquals(OrderStatus.CONFIRMED,
            orderRepository.findById(o3.getId()).get().getStatus()); // Əvvəlcədən CONFIRMED
    }

    // ─── Specification ────────────────────────────────
    @Test
    void shouldFilterWithSpecifications() {
        entityManager.persistAndFlush(buildOrderWithAmount("c1", OrderStatus.PENDING, "50"));
        entityManager.persistAndFlush(buildOrderWithAmount("c1", OrderStatus.PENDING, "150"));
        entityManager.persistAndFlush(buildOrderWithAmount("c2", OrderStatus.CONFIRMED, "200"));

        Specification<Order> spec = OrderSpecifications.byCustomer("c1")
            .and(OrderSpecifications.amountGreaterThan(new BigDecimal("100")));

        List<Order> results = orderRepository.findAll(spec);

        assertEquals(1, results.size());
        assertEquals(new BigDecimal("150.00"), results.get(0).getTotalAmount());
    }
}
```

---

## Pagination və Sorting testləri

```java
@DataJpaTest
class PaginationTest {

    @Autowired
    private OrderRepository orderRepository;

    @Autowired
    private TestEntityManager entityManager;

    @BeforeEach
    void setUp() {
        // 10 order yarat
        for (int i = 1; i <= 10; i++) {
            entityManager.persist(Order.builder()
                .customerId("customer-" + i)
                .status(i % 2 == 0 ? OrderStatus.CONFIRMED : OrderStatus.PENDING)
                .totalAmount(new BigDecimal(i * 100))
                .createdAt(Instant.now().minusSeconds(i * 60))
                .build());
        }
        entityManager.flush();
    }

    @Test
    void shouldReturnFirstPage() {
        Pageable pageable = PageRequest.of(0, 3);

        Page<Order> page = orderRepository.findAll(pageable);

        assertEquals(3, page.getContent().size());
        assertEquals(10, page.getTotalElements());
        assertEquals(4, page.getTotalPages());
        assertEquals(0, page.getNumber());
        assertTrue(page.isFirst());
        assertFalse(page.isLast());
    }

    @Test
    void shouldReturnLastPage() {
        Pageable pageable = PageRequest.of(3, 3);

        Page<Order> page = orderRepository.findAll(pageable);

        assertEquals(1, page.getContent().size()); // 10 % 3 = 1
        assertTrue(page.isLast());
    }

    @Test
    void shouldSortByAmount() {
        Pageable pageable = PageRequest.of(0, 5,
            Sort.by(Sort.Direction.DESC, "totalAmount"));

        Page<Order> page = orderRepository.findAll(pageable);

        List<BigDecimal> amounts = page.getContent().stream()
            .map(Order::getTotalAmount)
            .collect(Collectors.toList());

        // Azalan sıra
        for (int i = 0; i < amounts.size() - 1; i++) {
            assertTrue(amounts.get(i).compareTo(amounts.get(i + 1)) >= 0);
        }
    }

    @Test
    void shouldFilterAndPage() {
        Pageable pageable = PageRequest.of(0, 3);

        Page<Order> page = orderRepository.findByStatus(OrderStatus.PENDING, pageable);

        assertEquals(5, page.getTotalElements()); // 10 sifarişin 5-i PENDING
        assertTrue(page.getContent().stream()
            .allMatch(o -> o.getStatus() == OrderStatus.PENDING));
    }

    @Test
    void shouldReturnSlice() {
        // Slice — total count olmadan (count query yoxdur — daha sürətli)
        Pageable pageable = PageRequest.of(0, 3);

        Slice<Order> slice = orderRepository.findSliceByStatus(OrderStatus.PENDING, pageable);

        assertEquals(3, slice.getContent().size());
        assertTrue(slice.hasNext()); // Növbəti var
        // slice.getTotalElements() — mövcud deyil!
    }
}
```

---

## Real database ilə test

```java
// ─── H2 əvəzinə real PostgreSQL ───────────────────────
// Seçim 1: @AutoConfigureTestDatabase(replace = NONE)
@DataJpaTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
@TestPropertySource(properties = {
    "spring.datasource.url=jdbc:postgresql://localhost:5432/testdb",
    "spring.datasource.username=test",
    "spring.datasource.password=test"
})
class RealDatabaseTest {

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldWorkWithRealPostgres() {
        // PostgreSQL-specific funksionallıq test edilə bilər
        // (JSON operators, full-text search, vb.)
    }
}

// ─── TestContainers ilə PostgreSQL ────────────────────
@DataJpaTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
class TestContainersJpaTest {

    @Container
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15")
            .withDatabaseName("testdb")
            .withUsername("test")
            .withPassword("test");

    @DynamicPropertySource
    static void configureProperties(DynamicPropertyRegistry registry) {
        registry.add("spring.datasource.url", postgres::getJdbcUrl);
        registry.add("spring.datasource.username", postgres::getUsername);
        registry.add("spring.datasource.password", postgres::getPassword);
    }

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldUseRealPostgresContainer() {
        Order order = orderRepository.save(Order.builder()
            .customerId("customer-1")
            .status(OrderStatus.PENDING)
            .build());

        assertNotNull(order.getId());
        // PostgreSQL sequence ID-si
        assertTrue(order.getId() > 0);
    }
}

// ─── @Sql — test data yükləmə ─────────────────────────
@DataJpaTest
@Sql("/test-data/orders.sql") // Sinif səviyyəsindəki SQL
class SqlAnnotationTest {

    @Autowired
    private OrderRepository orderRepository;

    @Test
    @Sql("/test-data/extra-orders.sql") // Metod səviyyəsindəki SQL
    void shouldHavePreloadedData() {
        long count = orderRepository.count();
        assertTrue(count > 0);
    }

    @Test
    @Sql(scripts = "/test-data/cleanup.sql",
         executionPhase = Sql.ExecutionPhase.AFTER_TEST_METHOD)
    void shouldCleanupAfterTest() {
        // Test sonunda cleanup.sql icra edilir
    }
}
```

---

## İntervyu Sualları

### 1. @DataJpaTest nə yükləyir?
**Cavab:** Yalnız JPA layer: `@Entity` siniflər, `@Repository` interfeyslər, Spring Data JPA konfigurasiyası, `TestEntityManager`, embedded H2 database. `@Service`, `@Controller`, security, Kafka yüklənmir. H2 default istifadə edilir — `@AutoConfigureTestDatabase(replace=NONE)` ilə real DB istifadə oluna bilər. Hər test `@Transactional` — rollback.

### 2. TestEntityManager-in rolu nədir?
**Cavab:** `EntityManager`-ın test üçün wrapper-ı. `persistAndFlush()`, `persistFlushFind()` kimi birləşik metodlar var. `flush()` — pending SQL-i icra edir; `clear()` — L1 cache-i boşaldır (DB-dən fresh oxumaq üçün lazımdır). Repository test etdikdə test data-sı `entityManager.persistAndFlush()` ilə hazırlanır, sonra repository metodu çağırılır.

### 3. @DataJpaTest-də @Transactional-ın rolu?
**Cavab:** Hər test metodu `@Transactional` — test bitdikdə rollback olunur. DB-də kalıcı dəyişiklik olmur, test izolyasiyası sağlanır. `@Modifying` query-ləri test edərkən rollback problemi ola bilər — `@Commit` ilə override edilə bilər. Həmçinin `@Rollback(false)` ilə rollback dayandırıla bilər.

### 4. Derived query method-ların testinin mənası varmı?
**Cavab:** Bəli — Spring Data-nın query generation-ını deyil, bizim method adımızın düzgünlüyünü test edirik. `findByCustomerIdAndStatusOrderByCreatedAtDesc()` adı doğru mu? Entity field adları düzgün mü? İlişki joinləri düzgün mu? H2 ilə sürətli test — query doğru generasiya olunur.

### 5. @DataJpaTest ilə H2-nin məhdudiyyətləri?
**Cavab:** H2, PostgreSQL/MySQL-in bütün funksionallığını dəstəkləmir: JSON operators (`->`, `->>`), `ARRAY` tipi, `JSONB` indeks, PostgreSQL-specific funksiyalar, full-text search. Bu xüsusiyyətlər test edilmişsə `@AutoConfigureTestDatabase(replace=NONE)` + TestContainers real PostgreSQL konteyneri lazımdır.

*Son yenilənmə: 2026-04-10*
