# 38 — Spring Data Native Queries

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [nativeQuery=true](#nativequerytrue)
2. [Native Query ilə Pagination](#native-query-ilə-pagination)
3. [@SqlResultSetMapping](#sqlresultsetmapping)
4. [NamedNativeQuery](#namednativequery)
5. [Tuple Nəticəsi](#tuple-nəticəsi)
6. [Native Query nə vaxt istifadə edilməlidir](#native-query-nə-vaxt-istifadə-edilməlidir)
7. [YANLIŞ vs DOĞRU Patterns](#yanliş-vs-doğru-patterns)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## nativeQuery=true

`@Query` annotasiyasında `nativeQuery=true` parametri ilə birbaşa SQL sorğuları icra etmək mümkündür.

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Sadə native SQL sorğusu
    @Query(
        value = "SELECT * FROM tbl_products WHERE product_name = :name",
        nativeQuery = true
    )
    Optional<Product> findByNameNative(@Param("name") String name);

    // Cədvəl adları (entity adları yox!) istifadə edilir
    @Query(
        value = "SELECT p.* FROM tbl_products p " +
                "JOIN tbl_categories c ON p.cat_id = c.id " +
                "WHERE c.cat_name = :categoryName " +
                "AND p.unit_price BETWEEN :minPrice AND :maxPrice " +
                "ORDER BY p.unit_price ASC",
        nativeQuery = true
    )
    List<Product> findByCategoryAndPriceRange(
        @Param("categoryName") String categoryName,
        @Param("minPrice") BigDecimal minPrice,
        @Param("maxPrice") BigDecimal maxPrice
    );

    // PostgreSQL-in xüsusi funksiyaları
    @Query(
        value = "SELECT * FROM products " +
                "WHERE to_tsvector('english', name || ' ' || description) " +
                "@@ plainto_tsquery('english', :keyword)",
        nativeQuery = true
    )
    List<Product> fullTextSearch(@Param("keyword") String keyword);

    // Window funksiyası (JPQL-də yoxdur)
    @Query(
        value = "SELECT id, name, price, category_id, " +
                "RANK() OVER (PARTITION BY category_id ORDER BY price DESC) as price_rank " +
                "FROM products " +
                "WHERE active = true",
        nativeQuery = true
    )
    List<Object[]> findProductsWithPriceRank();
}
```

### Native Query ilə Object[] Nəticəsi

```java
@Service
@RequiredArgsConstructor
public class ProductService {

    private final ProductRepository productRepository;

    // Object[] nəticəsini emal etmək
    public List<ProductRankDTO> getProductsWithRank() {
        List<Object[]> results = productRepository.findProductsWithPriceRank();

        return results.stream()
            .map(row -> ProductRankDTO.builder()
                .id(((Number) row[0]).longValue())   // Sütun 0: id
                .name((String) row[1])                // Sütun 1: name
                .price((BigDecimal) row[2])            // Sütun 2: price
                .categoryId(((Number) row[3]).longValue()) // Sütun 3: category_id
                .rank(((Number) row[4]).intValue())   // Sütun 4: price_rank
                .build())
            .collect(Collectors.toList());
    }
}
```

---

## Native Query ilə Pagination

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Native query + Pageable - countQuery MECBURİDİR
    @Query(
        value = "SELECT p.* FROM tbl_products p " +
                "JOIN tbl_categories c ON p.cat_id = c.id " +
                "WHERE c.cat_name = :category " +
                "AND p.active = 1",
        countQuery = "SELECT COUNT(p.id) FROM tbl_products p " +
                     "JOIN tbl_categories c ON p.cat_id = c.id " +
                     "WHERE c.cat_name = :category " +
                     "AND p.active = 1",
        nativeQuery = true
    )
    Page<Product> findActiveByCategoryPaged(
        @Param("category") String category,
        Pageable pageable
    );

    // Slice ilə - COUNT sorğusu olmur
    @Query(
        value = "SELECT * FROM products WHERE active = true ORDER BY created_at DESC",
        nativeQuery = true
    )
    Slice<Product> findActiveProductsSlice(Pageable pageable);
}
```

```java
// Pagination istifadəsi
@Service
@RequiredArgsConstructor
public class ProductCatalogService {

    private final ProductRepository productRepository;

    @Transactional(readOnly = true)
    public Page<Product> getCatalogPage(String category, int page, int size) {
        Pageable pageable = PageRequest.of(page, size,
            Sort.by("price").ascending());

        return productRepository.findActiveByCategoryPaged(category, pageable);
    }
}
```

---

## @SqlResultSetMapping

Mürəkkəb native sorğu nəticələrini entity-lərə və ya custom class-lara map etmək üçün istifadə edilir.

### EntityResult — Entity-yə Map

```java
// Entity üzərində @SqlResultSetMapping təyin etmək
@Entity
@Table(name = "products")
@SqlResultSetMapping(
    name = "ProductMapping",
    entities = @EntityResult(
        entityClass = Product.class,
        fields = {
            @FieldResult(name = "id", column = "product_id"),
            @FieldResult(name = "name", column = "product_name"),
            @FieldResult(name = "price", column = "unit_price"),
            @FieldResult(name = "active", column = "is_active")
        }
    )
)
public class Product {
    @Id
    @Column(name = "product_id")
    private Long id;

    @Column(name = "product_name")
    private String name;

    @Column(name = "unit_price")
    private BigDecimal price;

    @Column(name = "is_active")
    private boolean active;
}
```

### ColumnResult — Xüsusi Sütunları Map

```java
@Entity
@Table(name = "orders")
@SqlResultSetMappings({
    // Çoxlu entity qaytaran mapping
    @SqlResultSetMapping(
        name = "OrderWithCustomerMapping",
        entities = {
            @EntityResult(entityClass = Order.class),
            @EntityResult(entityClass = Customer.class)
        }
    ),
    // Qarışıq: entity + əlavə sütun
    @SqlResultSetMapping(
        name = "OrderWithCountMapping",
        entities = @EntityResult(entityClass = Order.class),
        columns = @ColumnResult(name = "item_count", type = Long.class)
    )
})
public class Order {
    @Id
    private Long id;
    // digər field-lər
}
```

### ConstructorResult — DTO-ya Birbaşa Map

```java
// DTO sinfi
public class OrderSummaryDTO {
    private final Long orderId;
    private final String customerName;
    private final BigDecimal total;
    private final Long itemCount;

    // Bu constructor @ConstructorResult tərəfindən çağırılır
    public OrderSummaryDTO(Long orderId, String customerName,
                            BigDecimal total, Long itemCount) {
        this.orderId = orderId;
        this.customerName = customerName;
        this.total = total;
        this.itemCount = itemCount;
    }
}

// Entity-də mapping təyin et
@Entity
@Table(name = "orders")
@SqlResultSetMapping(
    name = "OrderSummaryDTOMapping",
    classes = @ConstructorResult(
        targetClass = OrderSummaryDTO.class,
        columns = {
            @ColumnResult(name = "order_id", type = Long.class),
            @ColumnResult(name = "customer_name", type = String.class),
            @ColumnResult(name = "total", type = BigDecimal.class),
            @ColumnResult(name = "item_count", type = Long.class)
        }
    )
)
public class Order {
    @Id
    private Long id;
}
```

### EntityManager ilə @SqlResultSetMapping istifadəsi

```java
@Repository
@RequiredArgsConstructor
public class OrderCustomRepository {

    private final EntityManager em;

    @SuppressWarnings("unchecked")
    public List<OrderSummaryDTO> findOrderSummaries() {
        // NamedNativeQuery + SqlResultSetMapping birlikdə
        return em.createNativeQuery(
            "SELECT o.id as order_id, " +
            "       CONCAT(c.first_name, ' ', c.last_name) as customer_name, " +
            "       o.total_amount as total, " +
            "       COUNT(oi.id) as item_count " +
            "FROM orders o " +
            "JOIN customers c ON o.customer_id = c.id " +
            "JOIN order_items oi ON oi.order_id = o.id " +
            "GROUP BY o.id, c.first_name, c.last_name, o.total_amount",
            "OrderSummaryDTOMapping" // Mapping adı
        ).getResultList();
    }

    public List<OrderSummaryDTO> findOrderSummariesByCustomer(Long customerId) {
        return em.createNativeQuery(
            "SELECT o.id, c.name, o.total, COUNT(oi.id) " +
            "FROM orders o " +
            "JOIN customers c ON o.customer_id = c.id " +
            "JOIN order_items oi ON oi.order_id = o.id " +
            "WHERE o.customer_id = :customerId " +
            "GROUP BY o.id, c.name, o.total",
            "OrderSummaryDTOMapping"
        )
        .setParameter("customerId", customerId)
        .getResultList();
    }
}
```

---

## NamedNativeQuery

Entity üzərində əvvəlcədən təyin edilmiş native sorğular.

```java
@Entity
@Table(name = "products")
@NamedNativeQueries({
    @NamedNativeQuery(
        name = "Product.findByCategory",
        query = "SELECT * FROM tbl_products WHERE cat_id = :categoryId AND active = 1",
        resultClass = Product.class
    ),
    @NamedNativeQuery(
        name = "Product.findSummaries",
        query = "SELECT p.id as product_id, p.product_name, " +
                "       p.unit_price, c.cat_name as category_name " +
                "FROM tbl_products p JOIN tbl_categories c ON p.cat_id = c.id",
        resultSetMapping = "ProductSummaryMapping"
    )
})
@SqlResultSetMapping(
    name = "ProductSummaryMapping",
    classes = @ConstructorResult(
        targetClass = ProductSummaryDTO.class,
        columns = {
            @ColumnResult(name = "product_id", type = Long.class),
            @ColumnResult(name = "product_name", type = String.class),
            @ColumnResult(name = "unit_price", type = BigDecimal.class),
            @ColumnResult(name = "category_name", type = String.class)
        }
    )
)
public class Product {
    @Id
    private Long id;
    // digər field-lər
}
```

```java
// NamedNativeQuery-ni EntityManager ilə çağır
@Repository
@RequiredArgsConstructor
public class ProductCustomRepository {

    private final EntityManager em;

    @SuppressWarnings("unchecked")
    public List<Product> findByCategory(Long categoryId) {
        return em.createNamedQuery("Product.findByCategory")
            .setParameter("categoryId", categoryId)
            .getResultList();
    }

    @SuppressWarnings("unchecked")
    public List<ProductSummaryDTO> findSummaries() {
        return em.createNamedQuery("Product.findSummaries")
            .getResultList();
    }
}
```

---

## Tuple Nəticəsi

`Tuple` interfeysi native sorğu nəticələrini daha tipli şəkildə almağa imkan verir.

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Interface projection ilə native query - ən rahat üsul
    @Query(
        value = "SELECT p.id, p.product_name as name, p.unit_price as price " +
                "FROM tbl_products p WHERE p.active = 1",
        nativeQuery = true
    )
    List<ProductProjection> findActiveProductProjections();
}

// Interface projection
public interface ProductProjection {
    Long getId();
    String getName();
    BigDecimal getPrice();
}
```

```java
// EntityManager ilə Tuple istifadəsi
@Repository
@RequiredArgsConstructor
public class ReportRepository {

    private final EntityManager em;

    public List<ProductReportItem> generateProductReport() {
        // TypedQuery<Tuple> - sütun adı ilə əlçatma
        TypedQuery<Tuple> query = em.createNativeQuery(
            "SELECT p.id, p.name, p.price, c.name as category_name " +
            "FROM products p JOIN categories c ON p.category_id = c.id",
            Tuple.class
        );

        List<Tuple> tuples = query.getResultList();

        return tuples.stream()
            .map(tuple -> ProductReportItem.builder()
                .id(tuple.get("id", Long.class))              // Sütun adı ilə
                .name(tuple.get("name", String.class))
                .price(tuple.get("price", BigDecimal.class))
                .categoryName(tuple.get("category_name", String.class))
                .build())
            .collect(Collectors.toList());
    }

    // JPQL ilə Tuple (native yox)
    public List<Tuple> findProductTuples() {
        return em.createQuery(
            "SELECT p.id, p.name, p.price FROM Product p",
            Tuple.class
        ).getResultList();
    }
}
```

---

## Native Query nə vaxt istifadə edilməlidir

```java
// 1. Database-specific funksiyalar
@Query(
    value = "SELECT * FROM products " +
            "WHERE ST_Distance_Sphere(location, ST_MakePoint(:lon, :lat)) < :radius",
    nativeQuery = true
)
List<Product> findNearby(
    @Param("lon") double longitude,
    @Param("lat") double latitude,
    @Param("radius") double radiusMeters
);

// 2. Window funksiyaları
@Query(
    value = "SELECT customer_id, order_date, total, " +
            "SUM(total) OVER (PARTITION BY customer_id ORDER BY order_date) as running_total " +
            "FROM orders",
    nativeQuery = true
)
List<Object[]> findOrdersWithRunningTotal();

// 3. CTE (Common Table Expressions)
@Query(
    value = "WITH RECURSIVE category_tree AS ( " +
            "  SELECT id, name, parent_id, 0 as level " +
            "  FROM categories WHERE parent_id IS NULL " +
            "  UNION ALL " +
            "  SELECT c.id, c.name, c.parent_id, ct.level + 1 " +
            "  FROM categories c " +
            "  JOIN category_tree ct ON c.parent_id = ct.id " +
            ") " +
            "SELECT * FROM category_tree ORDER BY level, name",
    nativeQuery = true
)
List<Object[]> findCategoryTree();

// 4. Performans kritik sorğular - JPQL-dən daha optimal SQL
@Query(
    value = "SELECT p.id, p.name " +
            "FROM products p " +
            "FORCE INDEX (idx_category_price) " + // MySQL hint
            "WHERE p.category_id = :catId AND p.price < :maxPrice",
    nativeQuery = true
)
List<Object[]> findOptimizedByCategory(
    @Param("catId") Long catId,
    @Param("maxPrice") BigDecimal maxPrice
);

// 5. Stored Procedure çağırmaq
@Query(
    value = "CALL calculate_order_statistics(:customerId)",
    nativeQuery = true
)
List<Object[]> callOrderStatsProcedure(@Param("customerId") Long customerId);
```

### Native vs JPQL qərar cədvəli

| Vəziyyət | Tövsiyə |
|----------|---------|
| Sadə CRUD | JPQL / Method name |
| JOIN, WHERE, ORDER BY | JPQL |
| Window funksiyalar | Native SQL |
| Full-text search | Native SQL |
| CTE / Recursive queries | Native SQL |
| Database hints | Native SQL |
| Stored procedures | Native SQL / @StoredProcedure |
| Portabiliti vacibdir | JPQL |
| Maksimum performans | Native SQL |

---

## YANLIŞ vs DOĞRU Patterns

### 1. Native query-də countQuery-siz Pageable

```java
// YANLIŞ - countQuery olmadan native query + Pageable
@Query(
    value = "SELECT * FROM products WHERE category_id = :catId",
    nativeQuery = true
)
Page<Product> findByCategory(@Param("catId") Long catId, Pageable pageable);
// Spring Data COUNT-u avtomatik yaratmağa çalışır amma yanlış ola bilər

// DOĞRU - açıq countQuery
@Query(
    value = "SELECT * FROM products WHERE category_id = :catId",
    countQuery = "SELECT COUNT(*) FROM products WHERE category_id = :catId",
    nativeQuery = true
)
Page<Product> findByCategory(@Param("catId") Long catId, Pageable pageable);
```

### 2. Object[] əvəzinə Interface Projection

```java
// YANLIŞ - Object[] ilə tip güvənsizliyi
@Query(
    value = "SELECT id, name, price FROM products",
    nativeQuery = true
)
List<Object[]> findProducts();
// Nəticəni emal etmək çətindir, cast xətalara yol aça bilər

// DOĞRU - interface projection ilə tip güvənliyi
public interface ProductSummary {
    Long getId();
    String getName();
    BigDecimal getPrice();
}

@Query(
    value = "SELECT id, name, price FROM products",
    nativeQuery = true
)
List<ProductSummary> findProducts();
// Getter adları SQL alias-ları ilə uyğun olmalıdır
```

### 3. SQL Injection riski

```java
// YANLIŞ - string concatenation ilə dinamik sorğu - SQL Injection!
@Repository
public class UnsafeProductRepository {
    @PersistenceContext
    private EntityManager em;

    public List<Object[]> findUnsafe(String userInput) {
        // XƏTƏRLİ! SQL Injection riski var
        String sql = "SELECT * FROM products WHERE name = '" + userInput + "'";
        return em.createNativeQuery(sql).getResultList();
    }
}

// DOĞRU - parametrli sorğu
@Repository
public class SafeProductRepository {
    @PersistenceContext
    private EntityManager em;

    public List<Product> findSafe(String name) {
        return em.createNativeQuery(
            "SELECT * FROM products WHERE name = :name",
            Product.class
        )
        .setParameter("name", name) // Parametr ilə - SQL Injection yoxdur
        .getResultList();
    }
}
```

### 4. Database portabiliti

```java
// YANLIŞ - MySQL-specific syntax
@Query(
    value = "SELECT * FROM products LIMIT :size OFFSET :offset",
    nativeQuery = true
)
List<Product> findWithLimit(@Param("size") int size, @Param("offset") int offset);
// PostgreSQL, Oracle-da fərqli ola bilər

// DOĞRU - Pageable istifadə et (database portabil)
@Query(
    value = "SELECT * FROM products WHERE active = 1",
    countQuery = "SELECT COUNT(*) FROM products WHERE active = 1",
    nativeQuery = true
)
Page<Product> findActive(Pageable pageable);
// Pageable → Spring Data hər database üçün düzgün LIMIT/OFFSET sintaksisinə çevirir
```

---

## İntervyu Sualları

**S: Native query nə vaxt istifadə etmək lazımdır?**

C: Database-specific funksiyalar (PostGIS, full-text search, JSON operators), window funksiyaları (ROW_NUMBER, RANK, SUM OVER), rekursiv CTE-lər, query hint-ləri, stored procedure-lər lazım olduqda. Adi hallarda JPQL tövsiyə edilir, çünki database-dən asılı deyil.

**S: Native query ilə Pageable istifadə etmək üçün nə lazımdır?**

C: `@Query` annotasiyasının `countQuery` parametrini mütləq təyin etmək lazımdır. Native sorğularda Spring Data COUNT-u avtomatik düzgün yarada bilmir (xüsusilə JOIN-li sorğularda). `Slice` istifadə etsəniz, COUNT sorğusu olmur.

**S: @SqlResultSetMapping nə üçün lazımdır?**

C: Mürəkkəb native sorğu nəticələrini entity-ləə, DTO-lara (ConstructorResult) və ya ayrı sütunlara (ColumnResult) map etmək üçün. EntityManager ilə `createNativeQuery(sql, "mappingName")` şəklində istifadə edilir. Spring Data repository-lərinin `@Query` annotation-unda birbaşa istifadə dəstəklənmir.

**S: Native query ilə SQL Injection-dan necə qorunmaq olar?**

C: Hər zaman parametrli sorğular istifadə edin: `@Param("name")` annotation-u ilə ya da `setParameter("name", value)`. Heç vaxt user input-unu string concatenation ilə sorğuya əlavə etməyin.

**S: Interface projection ilə native query necə işləyir?**

C: SQL alias adları interface-dəki getter adları ilə uyğun olmalıdır (camelCase → snake_case avtomatik çevrilir). Məsələn: `String getName()` → SQL-də `name` alias-ı. Spring Data rezultatları interface proxy-lərinə avtomatik map edir. Bu Object[] əvəzinə tipli müraciəti mümkün edir.
