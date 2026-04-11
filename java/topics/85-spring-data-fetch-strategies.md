# 85. Spring Data Fetch Strategies

## Mündəricat
1. [FetchType.LAZY vs EAGER](#fetchtypelazy-vs-eager)
2. [Hər Əlaqə Tipi üçün Default Fetch](#hər-əlaqə-tipi-üçün-default-fetch)
3. [LazyInitializationException](#lazyinitializationexception)
4. [@EntityGraph](#entitygraph)
5. [JOIN FETCH JPQL ilə](#join-fetch-jpql-ilə)
6. [N+1 Problemi](#n1-problemi)
7. [YANLIŞ vs DOĞRU Patterns](#yanliş-vs-doğru-patterns)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## FetchType.LAZY vs EAGER

Hibernate entity-lər arasındakı əlaqəli datanı iki üsulla yükləyə bilər:

- **EAGER**: Ana entity yüklənəndə əlaqəli entity-lər də **dərhal** yüklənir (JOIN sorğusu)
- **LAZY**: Əlaqəli entity-lər yalnız **bilavasitə müraciət edildikdə** yüklənir (ayrı SELECT)

```java
@Entity
public class Order {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // EAGER - Order yüklənəndə Customer da yüklənir
    // Bir sorğu: SELECT o.*, c.* FROM orders o JOIN customers c ON o.customer_id = c.id
    @ManyToOne(fetch = FetchType.EAGER)
    @JoinColumn(name = "customer_id")
    private Customer customer;

    // LAZY - OrderItem-lər yalnız getItems() çağırılanda yüklənir
    // İkinci sorğu: SELECT * FROM order_items WHERE order_id = ?
    @OneToMany(mappedBy = "order", fetch = FetchType.LAZY)
    private List<OrderItem> items;
}
```

```java
// EAGER davranışı
@Transactional
public void eagerExample() {
    Order order = orderRepository.findById(1L).get();
    // Bu nöqtədə artıq customer da yüklənib (JOIN ilə)
    // Ayrıca sorğu olmadan:
    String customerName = order.getCustomer().getName(); // Proxy yox, real data
}

// LAZY davranışı
@Transactional
public void lazyExample() {
    Order order = orderRepository.findById(1L).get();
    // Bu nöqtədə items yüklənməyib - sadəcə proxy var

    // Yalnız aşağıdakı sətirdə SELECT icra edilir:
    List<OrderItem> items = order.getItems(); // Burada SELECT
    items.size(); // Əgər items boş collection-dursa, SELECT əvvəllər icra edilib
}
```

---

## Hər Əlaqə Tipi üçün Default Fetch

| Annotation | Default FetchType |
|------------|-------------------|
| `@ManyToOne` | **EAGER** |
| `@OneToOne` | **EAGER** |
| `@OneToMany` | **LAZY** |
| `@ManyToMany` | **LAZY** |

```java
@Entity
public class Employee {
    @Id
    private Long id;

    // Default: EAGER (tövsiyə edilmir!)
    @ManyToOne
    private Department department;

    // Default: EAGER (tövsiyə edilmir!)
    @OneToOne
    private EmployeeProfile profile;

    // Default: LAZY (düzgün)
    @OneToMany(mappedBy = "employee")
    private List<Task> tasks;

    // Default: LAZY (düzgün)
    @ManyToMany
    private Set<Project> projects;
}
```

**Tövsiyə:** Bütün əlaqələri **LAZY** edin, sonra lazım olduğu hallarda EAGER yükləmə üçün `@EntityGraph` və ya `JOIN FETCH` istifadə edin.

```java
// DOĞRU - hamısını LAZY et
@Entity
public class Employee {
    @ManyToOne(fetch = FetchType.LAZY) // Default EAGER-i override et
    private Department department;

    @OneToOne(fetch = FetchType.LAZY)
    private EmployeeProfile profile;

    @OneToMany(mappedBy = "employee") // Artıq default LAZY
    private List<Task> tasks;
}
```

---

## LazyInitializationException

Bu exception ən çox görülən Hibernate xətalarından biridir. Session (persistence context) bağlandıqdan sonra LAZY əlaqəyə müraciət etdikdə baş verir.

```java
// YANLIŞ - LazyInitializationException baş verər
@Service
public class OrderService {
    public Order getOrder(Long id) {
        // Transaction burada açılır
        Order order = orderRepository.findById(id).get();
        // Transaction burada bağlanır
        return order;
        // Order-ın items field-i hələ yüklənməyib!
    }
}

// Controller-də
@RestController
public class OrderController {
    @GetMapping("/orders/{id}")
    public OrderResponse getOrder(@PathVariable Long id) {
        Order order = orderService.getOrder(id);
        // XƏTA! Session bağlıdır, items LAZY-dir
        // org.hibernate.LazyInitializationException:
        // failed to lazily initialize a collection of role: Order.items
        order.getItems().size(); // EXCEPTION!
        return mapToResponse(order);
    }
}
```

### LazyInitializationException Həlləri

```java
// Həll 1: @Transactional ilə session-u açıq saxla
@Service
public class OrderService {
    @Transactional // Transaction controller-a qayıdana qədər açıq qalır
    public Order getOrderWithItems(Long id) {
        Order order = orderRepository.findById(id).get();
        order.getItems().size(); // Session açıqdır - işləyir
        return order;
    }
}

// Həll 2: JOIN FETCH ilə əlaqəni əvvəlcədən yüklə
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {
    @Query("SELECT o FROM Order o JOIN FETCH o.items WHERE o.id = :id")
    Optional<Order> findByIdWithItems(@Param("id") Long id);
}

// Həll 3: @EntityGraph
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {
    @EntityGraph(attributePaths = {"items", "customer"})
    Optional<Order> findById(Long id);
}

// Həll 4: DTO proyeksiyası - entity-nin özünü qaytarma
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {
    @Query("SELECT new com.example.dto.OrderDTO(o.id, o.total, c.name) " +
           "FROM Order o JOIN o.customer c WHERE o.id = :id")
    Optional<OrderDTO> findOrderDTO(@Param("id") Long id);
}
```

---

## @EntityGraph

`@EntityGraph` annotation-u müəyyən əlaqələri həmin sorğu üçün EAGER yükləmək imkanı verir. Bütün entity-ləri global EAGER etmədən, yalnız lazım olduğu hallarda yükləyir.

```java
// Entity üzərində Named EntityGraph
@Entity
@NamedEntityGraph(
    name = "Order.withItemsAndCustomer",
    attributeNodes = {
        @NamedAttributeNode("items"),          // items yüklənsin
        @NamedAttributeNode("customer"),        // customer yüklənsin
        @NamedAttributeNode(value = "items",   // items-in product-ı da yüklənsin
            subgraph = "items.product")
    },
    subgraphs = {
        @NamedSubgraph(
            name = "items.product",
            attributeNodes = @NamedAttributeNode("product")
        )
    }
)
@NamedEntityGraph(
    name = "Order.withCustomerOnly",
    attributeNodes = @NamedAttributeNode("customer")
)
public class Order {
    @Id
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    private Customer customer;

    @OneToMany(mappedBy = "order", fetch = FetchType.LAZY)
    private List<OrderItem> items;
}
```

```java
// Repository-də @EntityGraph istifadəsi
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    // Named EntityGraph istifadəsi
    @EntityGraph("Order.withItemsAndCustomer")
    Optional<Order> findById(Long id);

    // Inline attributePaths - daha sadə sintaksis
    @EntityGraph(attributePaths = {"customer"})
    List<Order> findByStatus(String status);

    // Çoxlu attribute
    @EntityGraph(attributePaths = {"customer", "items", "items.product"})
    List<Order> findByCustomerId(Long customerId);

    // Named EntityGraph + custom query
    @EntityGraph("Order.withCustomerOnly")
    @Query("SELECT o FROM Order o WHERE o.total > :minAmount")
    List<Order> findLargeOrders(@Param("minAmount") BigDecimal minAmount);
}
```

```java
// Service-də EntityGraph istifadəsi
@Service
@RequiredArgsConstructor
public class OrderService {

    private final OrderRepository orderRepository;

    // Müştəri və sifariş elementlərini eyni anda yüklə
    @Transactional(readOnly = true)
    public OrderDetailDTO getOrderDetail(Long id) {
        Order order = orderRepository.findById(id) // @EntityGraph tətbiq edilir
            .orElseThrow(() -> new OrderNotFoundException(id));

        // Heç bir LazyInitializationException olmadan işləyir
        return OrderDetailDTO.builder()
            .id(order.getId())
            .customerName(order.getCustomer().getName()) // LAZY → EAGER (EntityGraph)
            .itemCount(order.getItems().size())           // LAZY → EAGER (EntityGraph)
            .build();
    }
}
```

---

## JOIN FETCH JPQL ilə

JPQL-də `JOIN FETCH` açar sözü əlaqəli entity-ləri bir sorğuda yükləmək üçün istifadə edilir.

```java
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    // Sadə JOIN FETCH
    @Query("SELECT o FROM Order o JOIN FETCH o.customer WHERE o.id = :id")
    Optional<Order> findWithCustomer(@Param("id") Long id);

    // Bir neçə əlaqə - dikkat: cartesian product problemi
    @Query("SELECT DISTINCT o FROM Order o " +
           "JOIN FETCH o.customer " +
           "JOIN FETCH o.items")
    List<Order> findAllWithCustomerAndItems();
    // DISTINCT lazımdır, çünki items-in hər biri üçün ayrı sətir gəlir

    // WHERE şərti ilə
    @Query("SELECT o FROM Order o " +
           "JOIN FETCH o.customer c " +
           "JOIN FETCH o.items i " +
           "WHERE c.id = :customerId AND o.status = :status")
    List<Order> findByCustomerAndStatus(
        @Param("customerId") Long customerId,
        @Param("status") String status
    );

    // LEFT JOIN FETCH - items olmayan order-lar da gəlsin
    @Query("SELECT o FROM Order o " +
           "LEFT JOIN FETCH o.items " +
           "WHERE o.createdAt > :date")
    List<Order> findRecentOrders(@Param("date") LocalDateTime date);
}
```

**Məhdudiyyət:** `JOIN FETCH` ilə `Pageable` birlikdə istifadə edilə bilməz (HibernateJpaDialect xətası).

```java
// YANLIŞ - JOIN FETCH + Pageable = xəta
@Query("SELECT DISTINCT o FROM Order o JOIN FETCH o.items")
Page<Order> findAllWithItems(Pageable pageable); // HibernateException!

// DOĞRU - iki ayrı sorğu strategiyası
// 1. Əvvəlcə ID-ləri al (paging ilə)
@Query(value = "SELECT o.id FROM Order o",
       countQuery = "SELECT COUNT(o) FROM Order o")
Page<Long> findAllIds(Pageable pageable);

// 2. Sonra ID-lərə görə entity-ləri JOIN FETCH ilə yüklə
@Query("SELECT DISTINCT o FROM Order o JOIN FETCH o.items WHERE o.id IN :ids")
List<Order> findByIdsWithItems(@Param("ids") List<Long> ids);
```

---

## N+1 Problemi

N+1 problemi: 1 sorğu ana entity-ləri gətirir, sonra hər bir entity üçün əlaqəli data üçün N ayrı sorğu icra edilir.

```java
// N+1 problemi YANLIŞ nümunə
@Transactional
public void demonstrateNPlusOne() {
    // 1 sorğu: SELECT * FROM orders
    List<Order> orders = orderRepository.findAll();

    // orders.size() = 100 olarsa, 100 əlavə sorğu:
    // SELECT * FROM customers WHERE id = ? (hər order üçün)
    for (Order order : orders) {
        System.out.println(order.getCustomer().getName()); // N sorğu!
    }
    // Cəmi: 1 + 100 = 101 sorğu
}
```

### N+1 Probleminin Həlləri

```java
// Həll 1: JOIN FETCH
@Query("SELECT DISTINCT o FROM Order o JOIN FETCH o.customer")
List<Order> findAllWithCustomer();
// 1 sorğu: SELECT o.*, c.* FROM orders o JOIN customers c ON o.customer_id = c.id

// Həll 2: @EntityGraph
@EntityGraph(attributePaths = "customer")
List<Order> findAll();
// 1 sorğu (JOIN ilə)

// Həll 3: @BatchSize - N sorğu əvəzinə N/batchSize sorğu
@Entity
public class Order {
    @ManyToOne(fetch = FetchType.LAZY)
    @BatchSize(size = 30) // 30-luq batch-lərlə yüklə
    private Customer customer;
}
// 100 order üçün: 1 + ceil(100/30) = 1 + 4 = 5 sorğu

// Həll 4: Hibernate @Fetch(FetchMode.SUBSELECT) - kolleksiyalar üçün
@Entity
public class Customer {
    @OneToMany(mappedBy = "customer")
    @Fetch(FetchMode.SUBSELECT)
    private List<Order> orders;
    // 2 sorğu: SELECT customers, sonra SELECT orders WHERE customer_id IN (SELECT id FROM customers WHERE ...)
}

// Həll 5: DTO proyeksiyası - yalnız lazım olan data
@Query("SELECT new com.example.dto.OrderSummary(o.id, c.name, o.total) " +
       "FROM Order o JOIN o.customer c")
List<OrderSummary> findOrderSummaries();
// 1 sorğu, yalnız 3 sütun
```

### N+1 Problemini Aşkar Etmək

```yaml
# application.yml - SQL sorğularını log et
spring:
  jpa:
    show-sql: true
    properties:
      hibernate:
        format_sql: true
        generate_statistics: true
        # Slow query log
        session:
          events:
            log:
              LOG_QUERIES_SLOWER_THAN_MS: 25

logging:
  level:
    org.hibernate.SQL: DEBUG
    org.hibernate.orm.jdbc.bind: TRACE
    org.hibernate.stat: DEBUG
```

```java
// Test-də N+1 yoxlamaq üçün
@DataJpaTest
class OrderRepositoryTest {

    @Autowired
    private OrderRepository orderRepository;

    @PersistenceContext
    private EntityManager em;

    @Test
    void shouldNotHaveNPlusOneProblem() {
        // Statistikaları sıfırla
        SessionFactory sf = em.getEntityManagerFactory().unwrap(SessionFactory.class);
        Statistics stats = sf.getStatistics();
        stats.setStatisticsEnabled(true);
        stats.clear();

        // Test
        List<Order> orders = orderRepository.findAllWithCustomer();
        orders.forEach(o -> o.getCustomer().getName());

        // Yalnız 1 sorğu olmalıdır
        assertThat(stats.getPrepareStatementCount()).isEqualTo(1L);
    }
}
```

---

## YANLIŞ vs DOĞRU Patterns

### 1. EAGER fetch-i global olaraq istifadə etmək

```java
// YANLIŞ - EAGER global HƏR ZAMAN join edir
@Entity
public class Product {
    @ManyToOne(fetch = FetchType.EAGER) // Hər zaman category yüklənir
    private Category category;

    @ManyToMany(fetch = FetchType.EAGER) // Hər zaman tags yüklənir
    private Set<Tag> tags;
}
// findAll() - həm category, həm tags yüklənir - lazım olmasa belə!

// DOĞRU - LAZY + lazım olduğunda EntityGraph
@Entity
public class Product {
    @ManyToOne(fetch = FetchType.LAZY)
    private Category category;

    @ManyToMany(fetch = FetchType.LAZY)
    private Set<Tag> tags;
}

// Lazım olduğu yerdə:
@EntityGraph(attributePaths = {"category", "tags"})
List<Product> findByNameContaining(String name);
```

### 2. Transaction xaricində LAZY yükləmə

```java
// YANLIŞ
@Service
public class ProductService {
    public Product getProduct(Long id) {
        return productRepository.findById(id).get(); // Transaction bağlandı
    }
}
@RestController
public class ProductController {
    public ProductResponse get(@PathVariable Long id) {
        Product p = productService.getProduct(id);
        return new ProductResponse(p.getCategory().getName()); // LazyInitializationException!
    }
}

// DOĞRU - DTO qaytarmaq
@Service
public class ProductService {
    @Transactional(readOnly = true)
    public ProductDTO getProduct(Long id) {
        Product p = productRepository.findByIdWithCategory(id)
            .orElseThrow(...);
        return ProductDTO.from(p); // Entity-ni transaction içində DTO-ya çevir
    }
}
```

### 3. JOIN FETCH ilə Pageable

```java
// YANLIŞ - HibernateException atar
@Query("SELECT o FROM Order o JOIN FETCH o.items")
Page<Order> findAll(Pageable pageable);

// DOĞRU - iki mərhələli yanaşma
@Transactional(readOnly = true)
public Page<OrderDTO> getOrders(Pageable pageable) {
    // Mərhələ 1: ID-ləri paging ilə al
    Page<Long> idPage = orderRepository.findAllIds(pageable);

    // Mərhələ 2: ID-lərə görə JOIN FETCH ilə yüklə
    List<Order> orders = orderRepository.findByIdsWithItems(idPage.getContent());

    // Page-i yenidən qur
    return new PageImpl<>(
        orders.stream().map(OrderDTO::from).collect(toList()),
        pageable,
        idPage.getTotalElements()
    );
}
```

---

## İntervyu Sualları

**S: FetchType.LAZY və FetchType.EAGER arasındakı fərq nədir?**

C: EAGER - ana entity yüklənəndə əlaqəli entity-lər də dərhal eyni sorğuda (JOIN ilə) yüklənir. LAZY - əlaqəli entity-lər yalnız bilavasitə müraciət edildikdə ayrı SELECT sorğusu ilə yüklənir. Default olaraq `@ManyToOne` və `@OneToOne` EAGER, `@OneToMany` və `@ManyToMany` isə LAZY-dir. Tövsiyə: hamısını LAZY edin, lazım olduğu hallarda EntityGraph və ya JOIN FETCH istifadə edin.

**S: LazyInitializationException niyə baş verir və necə həll edilir?**

C: Hibernate session (persistence context) bağlandıqdan sonra LAZY əlaqəyə müraciət etdikdə baş verir. Həll yolları: 1) `@Transactional` ilə session-u açıq saxlamaq, 2) `JOIN FETCH` ilə əvvəlcədən yükləmək, 3) `@EntityGraph` istifadə etmək, 4) Entity əvəzinə DTO proyeksiyası qaytarmaq.

**S: N+1 problemi nədir?**

C: 1 sorğu N entity gətirir, sonra hər entity üçün əlaqəli datanı yükləmək üçün N əlavə sorğu icra edilir. Cəmi N+1 sorğu. Həll: JOIN FETCH, @EntityGraph, @BatchSize, SUBSELECT fetch mode, ya da DTO proyeksiyası.

**S: @EntityGraph ilə JOIN FETCH arasındakı fərq nədir?**

C: Hər ikisi əlaqəli entity-ləri bir sorğuda yükləyir. Fərq: `JOIN FETCH` JPQL sorğusunun bir hissəsidir, `@EntityGraph` isə mövcud repository metodlarına (findById, findAll, custom method) tətbiq edilə bilər, JPQL yazmaq lazım deyil. `@EntityGraph` daha çevikdir — eyni metoda müxtəlif graph-lar tətbiq etmək mümkündür.

**S: JOIN FETCH ilə Pageable birlikdə niyə işləmir?**

C: JOIN FETCH database səviyyəsində LIMIT/OFFSET tətbiq edə bilmir, çünki JOIN nəticəsində hər bir ana entity üçün bir neçə sətir ola bilər. Hibernate bunu yaddaşda həll etməyə çalışır (HibernateJpaDialect warning) — bu memory problemi yarada bilər. Həll: əvvəlcə paging ilə ID-ləri al, sonra həmin ID-lər üçün JOIN FETCH et.
