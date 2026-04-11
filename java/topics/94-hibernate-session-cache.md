# Hibernate Session Cache (1st/2nd Level) — Geniş İzah

## Mündəricat
1. [First Level Cache (Session Cache)](#first-level-cache-session-cache)
2. [Second Level Cache](#second-level-cache)
3. [Query Cache](#query-cache)
4. [N+1 problemi](#n1-problemi)
5. [Lazy vs Eager Loading](#lazy-vs-eager-loading)
6. [İntervyu Sualları](#intervyu-sualları)

---

## First Level Cache (Session Cache)

**First Level Cache** — Hibernate Session-a bağlıdır. Avtomatik, söndürülə bilməz. Eyni transaction daxilində eyni entity-ni iki dəfə yükləsən, DB-yə yalnız bir sorğu gedir.

```java
@Service
public class UserService {

    @PersistenceContext
    private EntityManager em;

    @Transactional
    public void demonstrateFirstLevelCache() {
        // 1-ci sorğu — DB-dən gəlir
        User user1 = em.find(User.class, 1L);

        // 2-ci sorğu — Cache-dən gəlir! DB sorğusu yoxdur
        User user2 = em.find(User.class, 1L);

        System.out.println(user1 == user2); // true — eyni obyekt!
    }

    @Transactional
    public void cacheInvalidation() {
        User user = em.find(User.class, 1L);
        user.setName("Yeni ad");
        // save() çağırmaya da bilməzsən — dirty checking işləyir

        em.flush();  // Dəyişiklikləri DB-yə göndər (transaction bitməmiş)
        em.clear();  // First level cache-i təmizlə

        // İndi DB-dən yenidən yüklənir
        User freshUser = em.find(User.class, 1L);
    }

    @Transactional
    public void detachExample() {
        User user = em.find(User.class, 1L);

        em.detach(user); // Bu entity-ni cache-dən çıxar

        user.setName("Dəyişiklik");
        // Bu dəyişiklik DB-yə yazılmayacaq — entity detached-dir
    }
}
```

**Spring Data JPA ilə:**
```java
@Service
public class ProductService {

    private final ProductRepository repository;

    @Transactional
    public void firstLevelCacheWithRepository() {
        // Hər ikisi eyni query-dən istifadə edir (1st level cache)
        Product p1 = repository.findById(1L).orElseThrow();
        Product p2 = repository.findById(1L).orElseThrow();
        // p1 == p2 — eyni reference (transactional context daxilində)
    }
}
```

---

## Second Level Cache

**Second Level Cache** — Session-dan müstəqil, bütün Session-lar arasında paylaşılan cache. Əlavə konfiqurasiya tələb edir.

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-cache</artifactId>
</dependency>
<dependency>
    <groupId>org.hibernate.orm</groupId>
    <artifactId>hibernate-jcache</artifactId>
</dependency>
<dependency>
    <groupId>org.ehcache</groupId>
    <artifactId>ehcache</artifactId>
</dependency>
```

```yaml
# application.yml
spring:
  jpa:
    properties:
      hibernate:
        cache:
          use_second_level_cache: true
          use_query_cache: true
          region:
            factory_class: org.hibernate.cache.jcache.JCacheRegionFactory
        javax:
          cache:
            provider: org.ehcache.jsr107.EhcacheCachingProvider
```

```java
// Entity-də 2nd level cache aktivləşdirmək
@Entity
@Cache(usage = CacheConcurrencyStrategy.READ_WRITE) // ← Bunu əlavə et
public class Product {

    @Id
    @GeneratedValue
    private Long id;

    private String name;
    private BigDecimal price;

    @ManyToOne
    @Cache(usage = CacheConcurrencyStrategy.READ_ONLY) // Relasiya üçün
    private Category category;
}
```

**Cache Concurrency Strategy-lər:**
```java
// READ_ONLY — Dəyişməyən data üçün (country, currency)
// Ən sürətli, amma update edildikdə exception
@Cache(usage = CacheConcurrencyStrategy.READ_ONLY)

// READ_WRITE — Update dəstəklənir, soft lock mexanizmi
@Cache(usage = CacheConcurrencyStrategy.READ_WRITE)

// NONSTRICT_READ_WRITE — Eventual consistency (nadir update)
@Cache(usage = CacheConcurrencyStrategy.NONSTRICT_READ_WRITE)

// TRANSACTIONAL — JTA transaction ilə tam sinxron
@Cache(usage = CacheConcurrencyStrategy.TRANSACTIONAL)
```

**Cache-i manual idarə etmək:**
```java
@Service
public class ProductService {

    @PersistenceContext
    private EntityManager em;

    public void evictCache(Long productId) {
        // Spesifik entity-ni cache-dən çıxar
        em.getEntityManagerFactory()
          .getCache()
          .evict(Product.class, productId);
    }

    public void evictAllCache() {
        // Bütün cache-i təmizlə
        em.getEntityManagerFactory()
          .getCache()
          .evictAll();
    }
}
```

---

## Query Cache

```java
// Repository-də query cache
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    @QueryHints(@QueryHint(name = "org.hibernate.cacheable", value = "true"))
    @Query("SELECT p FROM Product p WHERE p.category.name = :category")
    List<Product> findByCategory(@Param("category") String category);
}

// Native query-də
@Query(value = "SELECT * FROM products WHERE category = :category",
       nativeQuery = true)
@QueryHints(@QueryHint(name = "org.hibernate.cacheable", value = "true"))
List<Product> findByCategoryNative(@Param("category") String category);
```

---

## N+1 problemi

```java
// YANLIŞ — N+1 problem
@Entity
public class Order {
    @OneToMany(fetch = FetchType.LAZY) // Lazy — default
    private List<OrderItem> items;
}

@Transactional
public void nPlusOneProblem() {
    List<Order> orders = orderRepository.findAll(); // 1 sorğu

    for (Order order : orders) {
        // Hər order üçün ayrıca sorğu! N sorğu = 1 + N = N+1 problem
        int itemCount = order.getItems().size();
    }
}

// DOĞRU 1 — JOIN FETCH
@Query("SELECT o FROM Order o LEFT JOIN FETCH o.items WHERE o.status = :status")
List<Order> findWithItems(@Param("status") OrderStatus status);

// DOĞRU 2 — @EntityGraph
@EntityGraph(attributePaths = {"items", "items.product"})
@Query("SELECT o FROM Order o WHERE o.status = :status")
List<Order> findWithItemsAndProducts(@Param("status") OrderStatus status);

// DOĞRU 3 — Batch fetch
@Entity
public class Order {
    @OneToMany(fetch = FetchType.LAZY)
    @BatchSize(size = 50) // 50-lik batch-lərlə yüklə
    private List<OrderItem> items;
}
```

---

## Lazy vs Eager Loading

```java
@Entity
public class User {

    @Id
    private Long id;

    private String name;

    // LAZY (default @OneToMany, @ManyToMany) — lazım olanda yüklə
    @OneToMany(fetch = FetchType.LAZY)
    private List<Order> orders;

    // EAGER (default @ManyToOne, @OneToOne) — həmişə yüklə
    @ManyToOne(fetch = FetchType.EAGER)
    private Department department;
}

// LazyInitializationException — transaction xaricində lazy loading
@Transactional(readOnly = true)
public UserDto getUser(Long id) {
    User user = userRepository.findById(id).orElseThrow();
    // Transaction daxilindəyik — OK
    int orderCount = user.getOrders().size(); // Lazy load işləyir
    return mapper.toDto(user);
}

// YANLIŞ — transaction xaricində
public UserDto getUserWrong(Long id) {
    User user = userRepository.findById(id).orElseThrow();
    // Transaction bitmişdir!
    int orderCount = user.getOrders().size(); // LazyInitializationException!
    return mapper.toDto(user);
}

// Həll — DTO projection
@Query("SELECT new com.example.dto.UserDto(u.id, u.name, SIZE(u.orders)) " +
       "FROM User u WHERE u.id = :id")
Optional<UserDto> findUserDtoById(@Param("id") Long id);
```

---

## İntervyu Sualları

### 1. First vs Second level cache fərqi?
**Cavab:** First level cache — Session (EntityManager) həyat dövrünə bağlıdır, transaction bitincə məhv olur, avtomatikdir. Second level cache — bütün Session-lar arasında paylaşılır, application scope-dadır, Ehcache/Redis kimi external provider tələb edir, `@Cache` ilə aktivləşdirilir.

### 2. N+1 problemi nədir?
**Cavab:** 1 sorğu ilə N entity yüklənir, sonra hər entity üçün ayrıca 1 sorğu atılır = 1+N sorğu. Həll: `JOIN FETCH` ilə bütün data bir sorğuda çəkilir, `@EntityGraph` ilə relasiyalar göstərilir, ya da `@BatchSize` ilə batch-lərlə yüklənir.

### 3. LazyInitializationException nə zaman baş verir?
**Cavab:** Transaction bitmişdən sonra lazy collection-a yaxud lazy field-ə müraciət edildikdə. Session artıq bağlı olduğundan Hibernate lazy load edə bilmir. Həll: `@Transactional` ilə metodu transaction daxinə almaq, `JOIN FETCH` istifadə etmək, ya da DTO projection istifadə etmək.

### 4. @EntityGraph nə üçündür?
**Cavab:** Repository sorğularında hansı lazy relasiyaların eager yüklənəcəyini göstərmək üçündür. N+1 problemini həll edir. Statik (`@NamedEntityGraph`) ya da dinamik (`@EntityGraph(attributePaths={...})`) formada istifadə edilə bilər.

### 5. READ_WRITE vs READ_ONLY cache strategy fərqi?
**Cavab:** `READ_ONLY` — entity heç vaxt update edilməyən (məsələn ölkə kodu) data üçün. Ən sürətli. Update cəhdi exception atır. `READ_WRITE` — update edilə bilən data üçün. Dəyişiklik zamanı soft lock mexanizmi istifadə edir, concurrent update-ləri idarə edir.

*Son yenilənmə: 2026-04-10*
