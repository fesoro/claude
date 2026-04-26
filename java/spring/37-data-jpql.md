# 37 — Spring Data JPQL

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [@Query Annotasiyası](#query-annotasiyası)
2. [JPQL vs SQL Fərqi](#jpql-vs-sql-fərqi)
3. [JPQL Əsas Sintaksis](#jpql-əsas-sintaksis)
4. [Named Parameters vs Positional Parameters](#named-parameters-vs-positional-parameters)
5. [JPQL Aqreqat Funksiyaları](#jpql-aqreqat-funksiyaları)
6. [@Modifying + @Transactional](#modifying--transactional)
7. [YANLIŞ vs DOĞRU Patterns](#yanliş-vs-doğru-patterns)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## @Query Annotasiyası

`@Query` annotasiyası repository metodlarına özel JPQL (və ya native SQL) sorğuları yazmağa imkan verir.

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Sadə @Query - method adından sorğu yaratmır, birbaşa JPQL istifadə edir
    @Query("SELECT p FROM Product p WHERE p.name = :name")
    Optional<Product> findByNameJpql(@Param("name") String name);

    // Method name query da eyni işi görür (sadə hallarda)
    Optional<Product> findByName(String name); // Spring Data avtomatik yaradır
}
```

### @Query-nin Üstünlükləri

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Mürəkkəb şərt - method name ilə ifadə etmək çətindir
    @Query("SELECT p FROM Product p " +
           "WHERE p.price BETWEEN :minPrice AND :maxPrice " +
           "AND p.category.name = :categoryName " +
           "AND p.stock > 0 " +
           "ORDER BY p.price ASC")
    List<Product> findAvailableInPriceRange(
        @Param("minPrice") BigDecimal minPrice,
        @Param("maxPrice") BigDecimal maxPrice,
        @Param("categoryName") String categoryName
    );

    // JOIN sorğusu
    @Query("SELECT p FROM Product p " +
           "JOIN p.category c " +
           "JOIN p.tags t " +
           "WHERE c.name = :category AND t.name IN :tags")
    List<Product> findByCategoryAndTags(
        @Param("category") String category,
        @Param("tags") List<String> tags
    );

    // Pagination dəstəyi - @Query + Pageable
    @Query(value = "SELECT p FROM Product p WHERE p.active = true",
           countQuery = "SELECT COUNT(p) FROM Product p WHERE p.active = true")
    Page<Product> findAllActive(Pageable pageable);
}
```

---

## JPQL vs SQL Fərqi

JPQL (Java Persistence Query Language) SQL-ə oxşardır, lakin **cədvəl adları əvəzinə entity sinif adları**, **sütun adları əvəzinə field adları** istifadə edir.

```java
// Entity sinfi
@Entity
@Table(name = "tbl_products") // Cədvəl adı tbl_products
public class Product {
    @Id
    private Long id;

    @Column(name = "product_name") // Sütun adı product_name
    private String name;

    @Column(name = "unit_price")
    private BigDecimal price;

    @ManyToOne
    @JoinColumn(name = "cat_id")
    private Category category;
}

@Entity
@Table(name = "tbl_categories")
public class Category {
    @Id
    private Long id;

    @Column(name = "cat_name")
    private String name;
}
```

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // YANLIŞ - SQL sintaksisi (cədvəl/sütun adları)
    @Query("SELECT * FROM tbl_products WHERE product_name = :name") // XƏTA!

    // DOĞRU - JPQL sintaksisi (entity/field adları)
    @Query("SELECT p FROM Product p WHERE p.name = :name") // Düzgün

    // SQL JOIN - cədvəl adları ilə
    // SELECT p.*, c.* FROM tbl_products p JOIN tbl_categories c ON p.cat_id = c.id

    // JPQL JOIN - entity adları ilə
    @Query("SELECT p FROM Product p JOIN p.category c WHERE c.name = :catName")
    List<Product> findByCategory(@Param("catName") String catName);

    // JPQL-də alias məcburidir (p, c)
    // SQL-də alias optional-dır
}
```

### JPQL-ə Xas Xüsusiyyətlər

```java
// 1. Entity müqayisəsi - SQL-dən fərqli
@Query("SELECT p FROM Product p WHERE p.category = :category")
List<Product> findByCategory(@Param("category") Category category);
// SQL: WHERE cat_id = ?
// JPQL: Bütün entity-ni müqayisə edə bilərik

// 2. Collection-a üzv yoxlaması
@Query("SELECT o FROM Order o WHERE :product MEMBER OF o.items")
List<Order> findOrdersContainingProduct(@Param("product") Product product);

// 3. IS EMPTY / IS NOT EMPTY
@Query("SELECT c FROM Customer c WHERE c.orders IS NOT EMPTY")
List<Customer> findCustomersWithOrders();

// 4. SIZE funksiyası
@Query("SELECT c FROM Customer c WHERE SIZE(c.orders) > :count")
List<Customer> findCustomersWithMoreThanNOrders(@Param("count") int count);

// 5. TYPE funksiyası - inheritance sorğuları
@Query("SELECT e FROM Employee e WHERE TYPE(e) = Manager")
List<Employee> findAllManagers();
```

---

## JPQL Əsas Sintaksis

### SELECT

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Bütün entity - tam obyekt
    @Query("SELECT p FROM Product p")
    List<Product> findAllProducts();

    // Xüsusi fieldlər - Object[] array qaytarır
    @Query("SELECT p.id, p.name, p.price FROM Product p WHERE p.active = true")
    List<Object[]> findIdNamePrice();

    // Constructor expression - DTO birbaşa
    @Query("SELECT new com.example.dto.ProductDTO(p.id, p.name, p.price) " +
           "FROM Product p WHERE p.active = true")
    List<ProductDTO> findProductDTOs();

    // DISTINCT
    @Query("SELECT DISTINCT p.category FROM Product p WHERE p.price > :price")
    List<Category> findCategoriesWithExpensiveProducts(@Param("price") BigDecimal price);
}
```

### WHERE, JOIN, ORDER BY, GROUP BY

```java
@Repository
public interface OrderRepository extends JpaRepository<Order, Long> {

    // WHERE ilə mürəkkəb şərt
    @Query("SELECT o FROM Order o " +
           "WHERE o.status IN :statuses " +
           "AND o.createdAt BETWEEN :start AND :end")
    List<Order> findByStatusesAndDateRange(
        @Param("statuses") List<String> statuses,
        @Param("start") LocalDateTime start,
        @Param("end") LocalDateTime end
    );

    // INNER JOIN (sadəcə JOIN)
    @Query("SELECT o FROM Order o JOIN o.customer c WHERE c.email = :email")
    List<Order> findByCustomerEmail(@Param("email") String email);

    // LEFT JOIN - customer-siz order-lar da gəlsin
    @Query("SELECT o FROM Order o LEFT JOIN o.customer c")
    List<Order> findAllWithOptionalCustomer();

    // ORDER BY - çoxlu sütun
    @Query("SELECT o FROM Order o ORDER BY o.status ASC, o.createdAt DESC")
    List<Order> findAllOrdered();

    // GROUP BY + HAVING
    @Query("SELECT o.customer.id, COUNT(o), SUM(o.total) " +
           "FROM Order o " +
           "GROUP BY o.customer.id " +
           "HAVING COUNT(o) > :minOrders")
    List<Object[]> findTopCustomers(@Param("minOrders") long minOrders);

    // Subquery
    @Query("SELECT p FROM Product p " +
           "WHERE p.price > (SELECT AVG(p2.price) FROM Product p2)")
    List<Product> findAboveAveragePrice();
}
```

### CASE Expression

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // CASE WHEN - şərtli dəyər
    @Query("SELECT p.name, " +
           "CASE WHEN p.stock = 0 THEN 'OUT_OF_STOCK' " +
           "     WHEN p.stock < 10 THEN 'LOW_STOCK' " +
           "     ELSE 'IN_STOCK' END " +
           "FROM Product p")
    List<Object[]> findProductsWithStockStatus();

    // COALESCE - null dəyəri replace et
    @Query("SELECT p.name, COALESCE(p.discountPrice, p.price) FROM Product p")
    List<Object[]> findProductsWithEffectivePrice();

    // NULLIF
    @Query("SELECT p FROM Product p WHERE NULLIF(p.description, '') IS NULL")
    List<Product> findProductsWithEmptyDescription();
}
```

---

## Named Parameters vs Positional Parameters

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Named parameters - :paramName sintaksisi (TÖVSIYƏ EDİLƏN)
    @Query("SELECT p FROM Product p WHERE p.name = :name AND p.price < :maxPrice")
    List<Product> findByNameAndPrice(
        @Param("name") String name,          // :name → @Param("name")
        @Param("maxPrice") BigDecimal maxPrice // :maxPrice → @Param("maxPrice")
    );

    // Positional parameters - ?1, ?2 sintaksisi (köhnə üsul)
    @Query("SELECT p FROM Product p WHERE p.name = ?1 AND p.price < ?2")
    List<Product> findByNameAndPricePositional(String name, BigDecimal maxPrice);
    // ?1 → birinci parametr (name)
    // ?2 → ikinci parametr (maxPrice)

    // Named parameters üstünlüyü: sıra dəyişsə belə işləyir
    @Query("SELECT p FROM Product p WHERE p.price < :maxPrice AND p.name = :name")
    List<Product> findByPriceAndName(
        @Param("name") String name,      // Parametr sırası fərqli ola bilər
        @Param("maxPrice") BigDecimal maxPrice
    );

    // LIKE ilə named parameter
    @Query("SELECT p FROM Product p WHERE LOWER(p.name) LIKE LOWER(CONCAT('%', :keyword, '%'))")
    List<Product> searchByKeyword(@Param("keyword") String keyword);

    // IN clause ilə named parameter
    @Query("SELECT p FROM Product p WHERE p.id IN :ids")
    List<Product> findByIds(@Param("ids") List<Long> ids);
}
```

---

## JPQL Aqreqat Funksiyaları

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // COUNT
    @Query("SELECT COUNT(p) FROM Product p WHERE p.active = true")
    long countActiveProducts();

    // SUM
    @Query("SELECT SUM(o.total) FROM Order o WHERE o.customer.id = :customerId")
    BigDecimal getTotalSpentByCustomer(@Param("customerId") Long customerId);

    // AVG
    @Query("SELECT AVG(p.price) FROM Product p WHERE p.category.name = :category")
    Double getAveragePriceByCategory(@Param("category") String category);

    // MIN / MAX
    @Query("SELECT MIN(p.price), MAX(p.price) FROM Product p WHERE p.active = true")
    Object[] getPriceRange();

    // Aqreqat + GROUP BY - çox istifadəli pattern
    @Query("SELECT p.category.name, COUNT(p), AVG(p.price), SUM(p.stock) " +
           "FROM Product p " +
           "WHERE p.active = true " +
           "GROUP BY p.category.name " +
           "ORDER BY COUNT(p) DESC")
    List<Object[]> getCategoryStats();

    // DTO ilə aqreqat - daha tipli nəticə
    @Query("SELECT new com.example.dto.CategoryStats(" +
           "p.category.name, COUNT(p), AVG(p.price)) " +
           "FROM Product p GROUP BY p.category.name")
    List<CategoryStats> getCategoryStatsDTOs();
}
```

```java
// CategoryStats DTO
public class CategoryStats {
    private final String categoryName;
    private final Long productCount;
    private final Double averagePrice;

    // JPQL constructor expression üçün bu constructor lazımdır
    public CategoryStats(String categoryName, Long productCount, Double averagePrice) {
        this.categoryName = categoryName;
        this.productCount = productCount;
        this.averagePrice = averagePrice;
    }
    // getter-lər...
}
```

---

## @Modifying + @Transactional

JPQL ilə UPDATE və DELETE əməliyyatları üçün `@Modifying` annotasiyası lazımdır.

```java
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // UPDATE sorğusu
    @Modifying
    @Transactional // Repository metodunun öz transaction-ı olmalıdır
    @Query("UPDATE Product p SET p.price = p.price * :multiplier WHERE p.category.id = :categoryId")
    int updatePricesByCategory(
        @Param("multiplier") BigDecimal multiplier,
        @Param("categoryId") Long categoryId
    );
    // Qaytarma tipi int/Integer - təsirlənmiş sətir sayı

    // DELETE sorğusu
    @Modifying
    @Transactional
    @Query("DELETE FROM Product p WHERE p.active = false AND p.updatedAt < :cutoffDate")
    int deleteInactiveProducts(@Param("cutoffDate") LocalDateTime cutoffDate);

    // Soft delete - UPDATE ilə
    @Modifying
    @Transactional
    @Query("UPDATE Product p SET p.deletedAt = CURRENT_TIMESTAMP WHERE p.id = :id")
    void softDeleteById(@Param("id") Long id);

    // clearAutomatically = true - persistence context-i avtomatik təmizlə
    // Əgər eyni transaction-da həm UPDATE həm də entity oxuyursunuzsa lazımdır
    @Modifying(clearAutomatically = true, flushAutomatically = true)
    @Transactional
    @Query("UPDATE Product p SET p.stock = 0 WHERE p.id IN :ids")
    int resetStockForProducts(@Param("ids") List<Long> ids);
}
```

### @Modifying-in flushAutomatically və clearAutomatically

```java
@Service
@RequiredArgsConstructor
public class ProductService {

    private final ProductRepository productRepository;

    @Transactional
    public void updateAndReadBack() {
        // Əvvəlcə entity-ni yüklə - persistence context-də var
        Product product = productRepository.findById(1L).get();
        System.out.println("Əvvəl: " + product.getPrice()); // 100.00

        // @Modifying sorğusu - birbaşa database-ə yazır, persistence context-i bilmir
        productRepository.updatePricesByCategory(new BigDecimal("1.1"), 1L);

        // Problem: persistence context köhnə datanı cache-ləyib
        // Həll: clearAutomatically = true - persistence context təmizlənir
        product = productRepository.findById(1L).get();
        System.out.println("Sonra: " + product.getPrice()); // 110.00 (clearAutomatically ilə)
    }

    // @Transactional REPOSITORY metodunda yoxsa SERVICE metodunda?
    @Transactional // SERVICE-də @Transactional
    public int deactivateOldProducts(LocalDateTime cutoff) {
        // Bu yaxşıdır: transaction service-dən başlayır
        int count = productRepository.deleteInactiveProducts(cutoff);
        // Başqa əməliyyatlar da eyni transaction-da
        log.info("Silindi: {}", count);
        return count;
    }
}
```

### JPQL vs Native SQL nə vaxt

```java
@Repository
public interface ReportRepository extends JpaRepository<Order, Long> {

    // JPQL - əksər hallarda
    @Query("SELECT o FROM Order o WHERE o.customer.city = :city")
    List<Order> findByCustCity(@Param("city") String city);

    // Native SQL - database-specific funksiyalar lazım olduqda
    // məsələn: PostgreSQL-in JSON funksiyaları, window functions, vs.
    @Query(value = "SELECT o.*, " +
                   "ROW_NUMBER() OVER (PARTITION BY o.customer_id ORDER BY o.created_at DESC) as rn " +
                   "FROM orders o",
           nativeQuery = true)
    List<Object[]> findLatestOrdersPerCustomer();

    // JPQL-də FUNCTION() ilə database funksiyası çağırmaq
    @Query("SELECT p FROM Product p WHERE FUNCTION('JSON_CONTAINS', p.tags, :tag) = 1")
    List<Product> findByTag(@Param("tag") String tag);
}
```

---

## YANLIŞ vs DOĞRU Patterns

### 1. Positional vs Named Parameters

```java
// YANLIŞ - positional parameters oxumaq çətindir
@Query("SELECT p FROM Product p WHERE p.name = ?1 AND p.price < ?2 AND p.category.id = ?3")
List<Product> find(String name, BigDecimal price, Long catId);
// ?1 hansıdır? ?3 hansıdır? - anlamaq çətindir

// DOĞRU - named parameters aydın göstərir
@Query("SELECT p FROM Product p " +
       "WHERE p.name = :name " +
       "AND p.price < :maxPrice " +
       "AND p.category.id = :categoryId")
List<Product> find(
    @Param("name") String name,
    @Param("maxPrice") BigDecimal maxPrice,
    @Param("categoryId") Long categoryId
);
```

### 2. @Modifying olmadan UPDATE/DELETE

```java
// YANLIŞ - @Modifying olmadan UPDATE atar: QueryExecutionRequestException
@Transactional
@Query("UPDATE Product p SET p.active = false WHERE p.id = :id")
void deactivate(@Param("id") Long id); // XƏTA!

// DOĞRU
@Modifying
@Transactional
@Query("UPDATE Product p SET p.active = false WHERE p.id = :id")
void deactivate(@Param("id") Long id);
```

### 3. Entity adı əvəzinə cədvəl adı

```java
// YANLIŞ - SQL sintaksisi, JPQL-də çalışmaz
@Query("SELECT * FROM tbl_products WHERE product_name LIKE %:name%")
List<Product> findByName(@Param("name") String name);

// DOĞRU - JPQL sintaksisi
@Query("SELECT p FROM Product p WHERE p.name LIKE %:name%")
List<Product> findByName(@Param("name") String name);
```

### 4. countQuery olmadan Pageable istifadəsi

```java
// YANLIŞ - COUNT sorğusu çox mürəkkəb olar (JOIN-li sorğular üçün)
@Query("SELECT DISTINCT p FROM Product p JOIN p.tags t WHERE t.name = :tag")
Page<Product> findByTag(@Param("tag") String tag, Pageable pageable);
// Spring Data COUNT-u avtomatik yaradır amma JOIN-lər səbəbindən səhv ola bilər

// DOĞRU - ayrıca countQuery təyin et
@Query(
    value = "SELECT DISTINCT p FROM Product p JOIN p.tags t WHERE t.name = :tag",
    countQuery = "SELECT COUNT(DISTINCT p) FROM Product p JOIN p.tags t WHERE t.name = :tag"
)
Page<Product> findByTag(@Param("tag") String tag, Pageable pageable);
```

---

## İntervyu Sualları

**S: JPQL ilə SQL arasındakı əsas fərqlər nələrdir?**

C: JPQL entity sinif adları və Java field adları ilə işləyir, SQL isə cədvəl və sütun adları ilə. JPQL platformdan asılı deyil — Hibernate bunu hər database üçün uyğun SQL-ə çevirir. JPQL-də navigation path-lar istifadə edilir (`p.category.name`), SQL-də isə JOIN lazımdır. JPQL-də cədvəl adları bilinmir — yalnız @Entity sinfinin adı.

**S: @Modifying annotasiyası nə üçün lazımdır?**

C: Spring Data default olaraq hər @Query metodunun SELECT əməliyyatı icra etdiyini fərz edir. UPDATE/DELETE sorğuları icra etmək üçün Spring Data-ya bu sorğunun data dəyişdirdiyini bildirən `@Modifying` lazımdır. `@Modifying` olmadan QueryExecutionRequestException atılır.

**S: @Modifying-in clearAutomatically parametri nə üçündür?**

C: `@Modifying` sorğusu birbaşa database-ə yazır, lakin JPA persistence context (1st level cache) köhnə datanı saxlaya bilər. `clearAutomatically = true` ilə sorğu icra edildikdən sonra persistence context avtomatik təmizlənir. Bu, eyni transaction-da həm bulk UPDATE, həm də entity oxuma olduqda köhnə datanın qaytarılmasının qarşısını alır.

**S: Named parameters vs Positional parameters — hansı tövsiyə edilir?**

C: Named parameters (`:name` + `@Param("name")`) tövsiyə edilir, çünki: 1) kod oxunaqlıdır — hər parametrin nə olduğu aydındır, 2) parametrlərin sırası sorğu ilə metod arasında fərqli ola bilər, 3) daha az xəta ehtimalı. Positional parameters (`?1`, `?2`) köhnə Java EE üslubundandır, Spring Data ilə nadir istifadə edilir.

**S: JPQL-də Pageable istifadə etmək üçün nə lazımdır?**

C: `@Query` metoduna Pageable parametri əlavə etmək kifayətdir — Spring Data avtomatik olaraq LIMIT/OFFSET və COUNT sorğuları yaradır. Lakin mürəkkəb sorğularda (xüsusilə DISTINCT, JOIN ilə) avtomatik COUNT yanlış ola bilər. Bu hallarda `@Query`-nin `countQuery` parametrini açıq şəkildə təyin etmək lazımdır.
