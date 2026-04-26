# 40 — Spring Data Specifications

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Specification Interfeysi](#specification-interfeysi)
2. [JpaSpecificationExecutor](#jpaspecificationexecutor)
3. [CriteriaBuilder, CriteriaQuery, Root](#criteriabuilder-criteriaquery-root)
4. [Dinamik Sorğu Qurma](#dinamik-sorğu-qurma)
5. [Specification-ları Birləşdirmək (and/or/not)](#specification-ları-birləşdirmək-andornot)
6. [Real Nümunə: Məhsul Filter](#real-nümunə-məhsul-filter)
7. [YANLIŞ vs DOĞRU Patterns](#yanliş-vs-doğru-patterns)
8. [İntervyu Sualları](#i̇ntervyu-sualları)

---

## Specification Interfeysi

`Specification<T>` interfeysi JPA Criteria API-nin üstündə işləyən type-safe dinamik sorğu qurmağa imkan verir. Ən çox istifadəsi: istifadəçinin seçdiyi filtr parametrlərinə əsasən dinamik WHERE şərtləri qurmaq.

```java
// Spring Data Specification interfeysi
@FunctionalInterface
public interface Specification<T> {
    Predicate toPredicate(
        Root<T> root,           // FROM Product p → entity-nin root-u
        CriteriaQuery<?> query, // Sorğunun özü (ORDER BY, DISTINCT, vs. üçün)
        CriteriaBuilder cb      // Predicate-ləri qurmaq üçün factory
    );

    // Default metodlar - Specification-ları birləşdirmək üçün
    default Specification<T> and(Specification<T> other) { ... }
    default Specification<T> or(Specification<T> other) { ... }
    static <T> Specification<T> not(Specification<T> spec) { ... }
    static <T> Specification<T> where(Specification<T> spec) { ... }
}
```

```java
// Sadə Specification nümunəsi
public class ProductSpecs {

    // Statik factory metodlar - hər şərt üçün ayrı Specification
    public static Specification<Product> hasName(String name) {
        return (root, query, cb) -> cb.equal(root.get("name"), name);
        // SQL: WHERE name = ?
    }

    public static Specification<Product> priceGreaterThan(BigDecimal price) {
        return (root, query, cb) -> cb.greaterThan(root.get("price"), price);
        // SQL: WHERE price > ?
    }

    public static Specification<Product> isActive() {
        return (root, query, cb) -> cb.isTrue(root.get("active"));
        // SQL: WHERE active = true
    }
}
```

---

## JpaSpecificationExecutor

Repository interfeysini `JpaSpecificationExecutor<T>` ilə extend etmək lazımdır.

```java
@Repository
public interface ProductRepository
        extends JpaRepository<Product, Long>,
                JpaSpecificationExecutor<Product> {  // Bu əlavə edilməlidir
    // Specification metodları artıq mövcuddur
}
```

```java
// JpaSpecificationExecutor interfeysi
public interface JpaSpecificationExecutor<T> {
    Optional<T> findOne(Specification<T> spec);
    List<T> findAll(Specification<T> spec);
    Page<T> findAll(Specification<T> spec, Pageable pageable);
    List<T> findAll(Specification<T> spec, Sort sort);
    long count(Specification<T> spec);
    boolean exists(Specification<T> spec);
    // Spring Data 3+
    <S extends T, R> R findBy(Specification<T> spec,
        Function<FluentQuery.FetchableFluentQuery<S>, R> queryFunction);
}
```

```java
// İstifadə
@Service
@RequiredArgsConstructor
public class ProductQueryService {

    private final ProductRepository productRepository;

    public List<Product> getActiveProducts() {
        Specification<Product> spec = ProductSpecs.isActive();
        return productRepository.findAll(spec);
    }

    public Optional<Product> findByExactName(String name) {
        return productRepository.findOne(ProductSpecs.hasName(name));
    }

    public long countExpensiveProducts(BigDecimal threshold) {
        return productRepository.count(ProductSpecs.priceGreaterThan(threshold));
    }
}
```

---

## CriteriaBuilder, CriteriaQuery, Root

Specification-ın `toPredicate` metodundakı 3 parametri ətraflı izah:

```java
public class ProductSpecs {

    // ROOT - entity-nin field-lərinə müraciət
    public static Specification<Product> examples() {
        return (root, query, cb) -> {

            // root.get("name") - field adı ilə
            Path<String> namePath = root.get("name");

            // root.get("category").get("name") - nested field
            Path<String> categoryName = root.get("category").get("name");

            // root.<String>get("name") - tip parametrli (daha tipli)
            Path<String> typedName = root.<String>get("name");

            // JOIN üçün
            Join<Product, Category> categoryJoin = root.join("category");
            Join<Product, Tag> tagJoin = root.join("tags", JoinType.LEFT);

            // Placeholder - real predicate
            return cb.conjunction(); // true (bütün nəticələr)
        };
    }

    // CRITERIABUILDER - Predicate-ləri qurmaq
    public static Specification<Product> criteriaBuilderExamples(
            String name, BigDecimal minPrice, BigDecimal maxPrice) {
        return (root, query, cb) -> {

            // Bərabərlik
            cb.equal(root.get("name"), name);
            cb.notEqual(root.get("status"), "DELETED");

            // Müqayisə
            cb.greaterThan(root.get("price"), minPrice);
            cb.greaterThanOrEqualTo(root.get("price"), minPrice);
            cb.lessThan(root.get("price"), maxPrice);
            cb.lessThanOrEqualTo(root.get("price"), maxPrice);
            cb.between(root.get("price"), minPrice, maxPrice);

            // Boolean
            cb.isTrue(root.get("active"));
            cb.isFalse(root.get("deleted"));
            cb.isNull(root.get("deletedAt"));
            cb.isNotNull(root.get("createdAt"));

            // String
            cb.like(root.get("name"), "%" + name + "%");
            cb.like(cb.lower(root.get("name")), "%" + name.toLowerCase() + "%");

            // IN
            cb.in(root.get("status")).value("ACTIVE").value("PENDING");

            // NOT
            cb.not(cb.equal(root.get("name"), "Test"));

            // AND / OR
            cb.and(
                cb.equal(root.get("active"), true),
                cb.greaterThan(root.get("price"), minPrice)
            );
            cb.or(
                cb.equal(root.get("status"), "ACTIVE"),
                cb.equal(root.get("status"), "PENDING")
            );

            return cb.conjunction(); // true
        };
    }

    // CRITERIAQUERY - sorğu səviyyəsindəki əməliyyatlar
    public static Specification<Product> withDistinct() {
        return (root, query, cb) -> {
            query.distinct(true); // SELECT DISTINCT
            return cb.conjunction();
        };
    }

    // Subquery
    public static Specification<Product> hasOrderedByCustomer(Long customerId) {
        return (root, query, cb) -> {
            Subquery<Long> subquery = query.subquery(Long.class);
            Root<OrderItem> itemRoot = subquery.from(OrderItem.class);
            subquery.select(itemRoot.get("product").get("id"))
                    .where(cb.equal(
                        itemRoot.get("order").get("customer").get("id"),
                        customerId
                    ));
            return root.get("id").in(subquery);
            // SQL: WHERE id IN (SELECT product_id FROM order_items
            //                   JOIN orders ON ... WHERE customer_id = ?)
        };
    }
}
```

---

## Dinamik Sorğu Qurma

Specification-ın əsas gücu: null olan parametrləri avtomatik atlamaq.

```java
public class ProductSpecifications {

    // Null-safe Specification factory
    public static Specification<Product> hasName(String name) {
        if (name == null || name.isBlank()) {
            return null; // null → Specification.where() tərəfindən atlanır
        }
        return (root, query, cb) ->
            cb.like(cb.lower(root.get("name")), "%" + name.toLowerCase() + "%");
    }

    public static Specification<Product> hasCategory(Long categoryId) {
        if (categoryId == null) return null;
        return (root, query, cb) ->
            cb.equal(root.get("category").get("id"), categoryId);
    }

    public static Specification<Product> hasPriceBetween(BigDecimal min, BigDecimal max) {
        if (min == null && max == null) return null;
        return (root, query, cb) -> {
            if (min != null && max != null) {
                return cb.between(root.get("price"), min, max);
            } else if (min != null) {
                return cb.greaterThanOrEqualTo(root.get("price"), min);
            } else {
                return cb.lessThanOrEqualTo(root.get("price"), max);
            }
        };
    }

    public static Specification<Product> hasStatus(List<String> statuses) {
        if (statuses == null || statuses.isEmpty()) return null;
        return (root, query, cb) ->
            root.get("status").in(statuses);
    }

    public static Specification<Product> createdAfter(LocalDateTime date) {
        if (date == null) return null;
        return (root, query, cb) ->
            cb.greaterThanOrEqualTo(root.get("createdAt"), date);
    }

    public static Specification<Product> isInStock() {
        return (root, query, cb) ->
            cb.greaterThan(root.get("stock"), 0);
    }
}
```

---

## Specification-ları Birləşdirmək (and/or/not)

```java
// Specification-ları and/or/not ilə birləşdirmək
@Service
@RequiredArgsConstructor
public class ProductSearchService {

    private final ProductRepository productRepository;

    // And ilə birləşdirmək
    public List<Product> searchProducts(ProductFilter filter) {
        Specification<Product> spec = Specification
            .where(ProductSpecifications.hasName(filter.getName()))
            .and(ProductSpecifications.hasCategory(filter.getCategoryId()))
            .and(ProductSpecifications.hasPriceBetween(filter.getMinPrice(), filter.getMaxPrice()))
            .and(ProductSpecifications.hasStatus(filter.getStatuses()))
            .and(ProductSpecifications.createdAfter(filter.getCreatedAfter()));

        // Null spec-lər avtomatik atlanır!
        return productRepository.findAll(spec);
    }

    // Or ilə birləşdirmək
    public List<Product> findByNameOrDescription(String keyword) {
        Specification<Product> byName = (root, q, cb) ->
            cb.like(cb.lower(root.get("name")), "%" + keyword.toLowerCase() + "%");

        Specification<Product> byDescription = (root, q, cb) ->
            cb.like(cb.lower(root.get("description")), "%" + keyword.toLowerCase() + "%");

        return productRepository.findAll(byName.or(byDescription));
    }

    // Not ilə
    public List<Product> findNonDeletedProducts() {
        Specification<Product> isDeleted = (root, q, cb) ->
            cb.isNotNull(root.get("deletedAt"));

        return productRepository.findAll(Specification.not(isDeleted));
    }

    // Mürəkkəb birləşmə: (A AND B) OR (C AND D)
    public List<Product> complexFilter(boolean premiumCategory, boolean luxury) {
        Specification<Product> premiumSpec =
            ProductSpecifications.hasCategory(1L)
                .and((root, q, cb) -> cb.greaterThan(root.get("price"), new BigDecimal("500")));

        Specification<Product> luxurySpec =
            ProductSpecifications.hasCategory(2L)
                .and((root, q, cb) -> cb.greaterThan(root.get("price"), new BigDecimal("1000")));

        return productRepository.findAll(premiumSpec.or(luxurySpec));
    }

    // Sayma
    public long countByFilter(ProductFilter filter) {
        Specification<Product> spec = buildSpec(filter);
        return productRepository.count(spec);
    }

    // Pageable ilə
    public Page<Product> searchProductsPaged(ProductFilter filter, Pageable pageable) {
        Specification<Product> spec = buildSpec(filter);
        return productRepository.findAll(spec, pageable);
    }

    private Specification<Product> buildSpec(ProductFilter filter) {
        return Specification
            .where(ProductSpecifications.hasName(filter.getName()))
            .and(ProductSpecifications.hasCategory(filter.getCategoryId()))
            .and(ProductSpecifications.hasPriceBetween(filter.getMinPrice(), filter.getMaxPrice()));
    }
}
```

---

## Real Nümunə: Məhsul Filter

Tam işlək məhsul axtarış sistemi:

```java
// Filter DTO
@Data
public class ProductFilter {
    private String name;           // null olarsa axtarılmır
    private Long categoryId;       // null olarsa axtarılmır
    private BigDecimal minPrice;   // null olarsa axtarılmır
    private BigDecimal maxPrice;   // null olarsa axtarılmır
    private List<String> tags;     // null/boş olarsa axtarılmır
    private Boolean inStock;       // null olarsa axtarılmır
    private Boolean active;        // null olarsa axtarılmır
    private LocalDateTime createdAfter; // null olarsa axtarılmır
    private String sortBy;         // name, price, createdAt
    private String sortDir;        // asc, desc
    private int page = 0;
    private int size = 20;
}
```

```java
// Bütün Specification-lar
public final class ProductSpecifications {

    private ProductSpecifications() {} // Utility class

    public static Specification<Product> nameLike(String name) {
        if (!StringUtils.hasText(name)) return null;
        String pattern = "%" + name.toLowerCase().trim() + "%";
        return (root, query, cb) ->
            cb.like(cb.lower(root.get("name")), pattern);
    }

    public static Specification<Product> inCategory(Long categoryId) {
        if (categoryId == null) return null;
        return (root, query, cb) ->
            cb.equal(root.get("category").get("id"), categoryId);
    }

    public static Specification<Product> priceBetween(BigDecimal min, BigDecimal max) {
        if (min == null && max == null) return null;
        return (root, query, cb) -> {
            List<Predicate> predicates = new ArrayList<>();
            if (min != null) predicates.add(cb.ge(root.get("price"), min));
            if (max != null) predicates.add(cb.le(root.get("price"), max));
            return cb.and(predicates.toArray(new Predicate[0]));
        };
    }

    public static Specification<Product> hasTags(List<String> tags) {
        if (tags == null || tags.isEmpty()) return null;
        return (root, query, cb) -> {
            query.distinct(true); // Tag join-i çoxalma yaradır
            Join<Product, Tag> tagJoin = root.join("tags", JoinType.INNER);
            return tagJoin.get("name").in(tags);
        };
    }

    public static Specification<Product> stockStatus(Boolean inStock) {
        if (inStock == null) return null;
        return (root, query, cb) -> inStock
            ? cb.greaterThan(root.get("stock"), 0)
            : cb.equal(root.get("stock"), 0);
    }

    public static Specification<Product> isActive(Boolean active) {
        if (active == null) return null;
        return (root, query, cb) ->
            cb.equal(root.get("active"), active);
    }

    public static Specification<Product> createdAfter(LocalDateTime date) {
        if (date == null) return null;
        return (root, query, cb) ->
            cb.greaterThanOrEqualTo(root.get("createdAt"), date);
    }
}
```

```java
// Specification Builder helper sinfi
@Component
public class ProductSpecificationBuilder {

    public Specification<Product> build(ProductFilter filter) {
        return Specification
            .where(ProductSpecifications.nameLike(filter.getName()))
            .and(ProductSpecifications.inCategory(filter.getCategoryId()))
            .and(ProductSpecifications.priceBetween(filter.getMinPrice(), filter.getMaxPrice()))
            .and(ProductSpecifications.hasTags(filter.getTags()))
            .and(ProductSpecifications.stockStatus(filter.getInStock()))
            .and(ProductSpecifications.isActive(filter.getActive()))
            .and(ProductSpecifications.createdAfter(filter.getCreatedAfter()));
    }
}
```

```java
// Service
@Service
@RequiredArgsConstructor
@Transactional(readOnly = true)
public class ProductSearchService {

    private final ProductRepository productRepository;
    private final ProductSpecificationBuilder specBuilder;

    public Page<Product> search(ProductFilter filter) {
        Specification<Product> spec = specBuilder.build(filter);

        // Sort qurma
        Sort sort = buildSort(filter.getSortBy(), filter.getSortDir());
        Pageable pageable = PageRequest.of(filter.getPage(), filter.getSize(), sort);

        return productRepository.findAll(spec, pageable);
    }

    public long count(ProductFilter filter) {
        return productRepository.count(specBuilder.build(filter));
    }

    private Sort buildSort(String sortBy, String sortDir) {
        if (!StringUtils.hasText(sortBy)) {
            return Sort.by("createdAt").descending(); // Default sort
        }

        Sort.Direction direction = "desc".equalsIgnoreCase(sortDir)
            ? Sort.Direction.DESC
            : Sort.Direction.ASC;

        // Yalnız icazəli field-lərdə sort
        String field = switch (sortBy) {
            case "name" -> "name";
            case "price" -> "price";
            case "createdAt" -> "createdAt";
            default -> "createdAt";
        };

        return Sort.by(direction, field);
    }
}
```

```java
// Controller
@RestController
@RequiredArgsConstructor
@RequestMapping("/api/products")
public class ProductController {

    private final ProductSearchService searchService;

    @GetMapping("/search")
    public Page<ProductDTO> search(
            @RequestParam(required = false) String name,
            @RequestParam(required = false) Long categoryId,
            @RequestParam(required = false) BigDecimal minPrice,
            @RequestParam(required = false) BigDecimal maxPrice,
            @RequestParam(required = false) List<String> tags,
            @RequestParam(required = false) Boolean inStock,
            @RequestParam(required = false) Boolean active,
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "20") int size,
            @RequestParam(defaultValue = "createdAt") String sortBy,
            @RequestParam(defaultValue = "desc") String sortDir) {

        ProductFilter filter = new ProductFilter();
        filter.setName(name);
        filter.setCategoryId(categoryId);
        filter.setMinPrice(minPrice);
        filter.setMaxPrice(maxPrice);
        filter.setTags(tags);
        filter.setInStock(inStock);
        filter.setActive(active);
        filter.setPage(page);
        filter.setSize(size);
        filter.setSortBy(sortBy);
        filter.setSortDir(sortDir);

        return searchService.search(filter).map(ProductDTO::from);
    }
}
```

---

## YANLIŞ vs DOĞRU Patterns

### 1. Null yoxlamasız Specification

```java
// YANLIŞ - null parametr NullPointerException verir
public static Specification<Product> hasName(String name) {
    return (root, query, cb) ->
        cb.like(root.get("name"), "%" + name + "%"); // name null olarsa NPE!
}

// DOĞRU - null yoxlama
public static Specification<Product> hasName(String name) {
    if (!StringUtils.hasText(name)) return null; // null qaytarılırsa atlanır
    return (root, query, cb) ->
        cb.like(cb.lower(root.get("name")), "%" + name.toLowerCase() + "%");
}
```

### 2. JpaSpecificationExecutor-suz Specification

```java
// YANLIŞ - JpaSpecificationExecutor extend edilməyib
public interface ProductRepository extends JpaRepository<Product, Long> {
    // findAll(Specification) metodu YOX - compile xəta
}

// DOĞRU
public interface ProductRepository
        extends JpaRepository<Product, Long>,
                JpaSpecificationExecutor<Product> {
    // findAll(Specification), count(Specification), vs. mövcuddur
}
```

### 3. Specification içində fetch - Pageable ilə problem

```java
// YANLIŞ - fetch + Pageable kombinasiyası problem yaradır
public static Specification<Product> withCategoryFetch() {
    return (root, query, cb) -> {
        root.fetch("category", JoinType.LEFT); // fetch + page = HibernateException
        return cb.conjunction();
    };
}

// DOĞRU - count sorğusunda fetch etmə
public static Specification<Product> withCategoryFetch() {
    return (root, query, cb) -> {
        // Count sorğusu üçün fetch etmə (performans)
        if (query.getResultType() != Long.class && query.getResultType() != long.class) {
            root.fetch("category", JoinType.LEFT);
        }
        return cb.conjunction();
    };
}
```

### 4. Field adı string əvəzinə metamodel

```java
// YANLIŞ - string ilə field adı - typo mümkündür
public static Specification<Product> hasName(String name) {
    return (root, query, cb) ->
        cb.equal(root.get("namee"), name); // Yazım xətası - runtime-da aşkarlanır
}

// DOĞRU - JPA Metamodel (apt plugin ilə yaradılır)
// Product_ sinfi annotation processor tərəfindən yaradılır
public static Specification<Product> hasName(String name) {
    return (root, query, cb) ->
        cb.equal(root.get(Product_.name), name); // Compile time yoxlaması
}
```

---

## İntervyu Sualları

**S: Specification pattern nə üçün istifadə edilir?**

C: Dinamik sorğular qurmaq üçün — istifadəçinin seçdiyi filtr parametrlərinə əsasən WHERE şərtlərini runtime-da birləşdirmək. Method name query-ləri statikdir (compile time-da müəyyən edilir), Specification isə runtime-da istənilən kombinasiyanı ifadə edə bilər.

**S: Specification-ı JpaRepository ilə işlətmək üçün nə lazımdır?**

C: Repository interfeysi həm `JpaRepository<T, ID>`, həm də `JpaSpecificationExecutor<T>` extend etməlidir. `JpaSpecificationExecutor` `findAll(Specification)`, `count(Specification)`, `findOne(Specification)` metodlarını təmin edir.

**S: Specification.where() niyə null kabul edir?**

C: `Specification.where(null)` boş şərt (conjunction — həmişə true) qaytarır. Bu null-safe zincirləməyə imkan verir: `where(null).and(null).and(spec)` yalnız `spec`-i tətbiq edir. Filtr metodlarından `null` qaytarsaq, həmin filtr avtomatik atlanır.

**S: Specification vs @Query — hansı nə vaxt istifadə edilməlidir?**

C: `@Query` — sorğu sabitdirsə, parametrlər həmişə eyni struktura sahib isə. `Specification` — ixtiyari kombinasiyalar mümkündürsə, opsional filtrlər varsa (məhsul axtarışı, hesabat filtri). Specification daha çevik, @Query daha sadə və oxunaqlıdır.

**S: `and()`, `or()`, `not()` metodları necə işləyir?**

C: Bu metodlar Specification-ları compose edir. `spec1.and(spec2)` hər ikisini birləşdirir — hər ikisi null deyilsə `WHERE spec1 AND spec2`. Əgər biri null-dırsa, digəri tək işləyir. `or()` eyni şəkildə işləyir. `not()` statik metoddur — `Specification.not(spec)`.
