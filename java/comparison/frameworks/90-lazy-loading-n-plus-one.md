# Lazy Loading & N+1 Query Problemi

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Spring Data JPA/Hibernate-də **lazy loading** (tənbəl yükləmə) default davranışdır — əlaqəli entity-lər yalnız lazım olanda DB-dən çəkilir. Bu səmərəlidir, amma düzgün istifadə edilmədikdə ən çox görülən performans problemi olan **N+1 query**-ə yol açır.

Laravel-dən gələn developerlər Eloquent-in fərqli lazy loading modelinə öyrəşdikləri üçün bu problemi tez-tez görürlər.

---

## Laravel-da Necədir

Laravel Eloquent default olaraq lazy load edir, amma `LazyInitializationException` atmır — sadəcə əlavə query göndərir:

```php
// N+1 PROBLEM — PHP-də exception yox, sadəcə yavaşlıq
$orders = Order::all(); // 1 query

foreach ($orders as $order) {
    echo $order->user->name; // ← hər order üçün ayrı query! N query
}
// Cəmi: 1 + N query

// HƏLL: Eager loading
$orders = Order::with('user')->get(); // 2 query (orders + users)
```

Laravel-in üstünlüyü: heç bir exception atmır, sadəcə yavaş işləyir. Bu bəzən problemi gizlədə bilər.

---

## Java/Spring-də: LazyInitializationException

Spring-də lazy load edilmiş relationship-ə **transaction xaricində** müraciət etmək `LazyInitializationException` atır:

```java
@Entity
public class Order {
    @Id Long id;
    String status;

    @ManyToOne(fetch = FetchType.LAZY)  // default LAZY
    @JoinColumn(name = "user_id")
    User user;

    @OneToMany(mappedBy = "order", fetch = FetchType.LAZY)  // default LAZY
    List<OrderItem> items;
}
```

```java
@Service
public class OrderService {

    public Order getOrder(Long id) {
        return orderRepository.findById(id).orElseThrow();
        // Transaction bağlandı, Hibernate session da bağlandı
    }
}

@RestController
public class OrderController {

    public OrderResponse getOrder(Long id) {
        Order order = orderService.getOrder(id);

        // ❌ LazyInitializationException!
        // Transaction artıq yoxdur, session bağlıdır
        String userName = order.getUser().getName();

        return new OrderResponse(order, userName);
    }
}
```

---

## N+1 Query Problemi

Transaction daxilindəsənsə exception yoxdur, amma performans problemi var:

```java
@Transactional
public List<OrderResponse> getAllOrders() {
    List<Order> orders = orderRepository.findAll(); // 1 query: SELECT * FROM orders

    return orders.stream()
        .map(order -> {
            // ❌ Hər order üçün ayrı query atılır!
            // order.getUser() → SELECT * FROM users WHERE id = ?
            // 100 order varsa → 101 query!
            String userName = order.getUser().getName();
            return new OrderResponse(order.getId(), userName);
        })
        .collect(Collectors.toList());
}
```

---

## Həll 1: JOIN FETCH (JPQL)

```java
// Repository
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    // JOIN FETCH ilə eager yüklə — 1 query
    @Query("SELECT o FROM Order o JOIN FETCH o.user WHERE o.status = :status")
    List<Order> findByStatusWithUser(@Param("status") String status);

    // Birdən çox collection-u eyni anda fetch etmə (MultipleBagFetchException!)
    @Query("SELECT DISTINCT o FROM Order o JOIN FETCH o.user JOIN FETCH o.items")
    List<Order> findAllWithUserAndItems();
}
```

```java
@Transactional
public List<OrderResponse> getAllOrders() {
    // ✓ 1 query: SELECT o.*, u.* FROM orders o JOIN users u ON o.user_id = u.id
    List<Order> orders = orderRepository.findByStatusWithUser("PENDING");

    return orders.stream()
        .map(o -> new OrderResponse(o.getId(), o.getUser().getName()))
        .collect(Collectors.toList());
}
```

### Həll 2: @EntityGraph

```java
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    // @EntityGraph ilə eager yüklə — annotation-based, sorgu yazmadan
    @EntityGraph(attributePaths = {"user", "items"})
    List<Order> findByStatus(String status);
}
```

### Həll 3: DTO Projection (ən performanslı)

```java
// DTO — yalnız lazım olan sahələr
public record OrderSummary(Long orderId, String orderStatus, String userName) {}

@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    @Query("""
        SELECT new com.example.dto.OrderSummary(o.id, o.status, u.name)
        FROM Order o JOIN o.user u
        WHERE o.status = :status
        """)
    List<OrderSummary> findSummaryByStatus(@Param("status") String status);
}
```

DTO projection ilə entity-nin heç lazy field-ləri yüklənmir — ən sürətli həlldir.

---

## N+1-i Aşkarlamaq

```java
// application.properties — query-ləri görmək üçün
spring.jpa.show-sql=true
spring.jpa.properties.hibernate.format_sql=true

# Hibernate statistics (production-da söndür)
spring.jpa.properties.hibernate.generate_statistics=true
logging.level.org.hibernate.stat=DEBUG
```

Konsoldakı output-a bax: eyni table üçün ayrı-ayrı `SELECT` görürsənsə N+1 var.

Daha yaxşı həll — **Hypersistence Optimizer** (production-a yararlıdır) və ya test-lərdə:

```java
@Test
void getOrders_shouldNotCauseNPlusOne() {
    // Aidas tool: https://github.com/vladmihalcea/hypersistence-optimizer
    // Və ya sadəcə statistics-ə bax:
    SessionFactory sf = entityManager.getEntityManagerFactory()
        .unwrap(SessionFactory.class);
    Statistics stats = sf.getStatistics();
    stats.setStatisticsEnabled(true);
    stats.clear();

    orderService.getAllOrders();

    long queryCount = stats.getQueryExecutionCount();
    assertThat(queryCount).isLessThanOrEqualTo(3); // 1 orders + 1 users + 1 items max
}
```

---

## OSIV (Open Session In View) — Gizli Tələ

Spring Boot default olaraq **OSIV = true** qoyur. Bu o deməkdir ki, HTTP request boyu Hibernate session açıq qalır — view layer-da da lazy load işləyir:

```java
// OSIV = true ilə (default) — exception yoxdur
@GetMapping("/orders/{id}")
public OrderResponse getOrder(@PathVariable Long id) {
    Order order = orderService.getOrder(id); // transaction bağlansın

    // ✓ Exception yoxdur (OSIV session-ı açıq saxlayır)
    // ❌ Amma N+1 risk var — view-da istifadəsi tövsiyə olunmur
    return new OrderResponse(order.getUser().getName());
}
```

**OSIV-i söndürmək tövsiyə olunur:**

```properties
# application.properties
spring.jpa.open-in-view=false
```

OSIV söndürüldükdə `LazyInitializationException` görəcəksən — bu pislik deyil, problemi erkən aşkarlayırsın.

---

## Qısa Qərar Cədvəli

| Vəziyyət | Həll |
|----------|------|
| 1 entity + 1 relationship | `JOIN FETCH` sorgu |
| Repository method-da dynamic fetch | `@EntityGraph` |
| Yalnız bir neçə sahə lazımdır | DTO Projection (`new MyDto(...)`) |
| Çox collection lazy-dir | `@Transactional` service layer-ı genişləndir |
| OSIV söndürülüb, exception var | `@Transactional`-ı düzgün yerlə |

---

## Praktik Tapşırıq

```java
@Entity public class Blog {
    @OneToMany(mappedBy = "blog") List<Post> posts;
    @ManyToOne User author;
}

@Entity public class Post {
    @ManyToOne Blog blog;
    @OneToMany(mappedBy = "post") List<Comment> comments;
}
```

Bu strukturda "bütün blogları, hər blogun author adını və post sayını" qaytaran endpoint yaz. Yalnız 2 SQL query ilə. DTO projection istifadə et.

---

## Əlaqəli Mövzular
- [23 — ORM & Database (Basics)](23-orm-and-database.md)
- [24 — Migrations](24-migrations.md)
- [53 — Spring Data JPA Deep](53-spring-data-jpa-deep.md)
- [26 — Transactions](26-transactions.md)
- [89 — @Transactional Self-Invocation](89-transactional-self-invocation.md)
