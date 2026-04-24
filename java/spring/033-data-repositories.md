# 033 — Spring Data Repositories
**Səviyyə:** Orta


## Mündəricat
1. [Repository Hierarchy](#repository-hierarchy)
2. [CrudRepository](#crudrepository)
3. [PagingAndSortingRepository](#pagingandsortingrepository)
4. [JpaRepository](#jparepository)
5. [ListCrudRepository (Spring Data 3+)](#listcrudrepository)
6. [Method Count Müqayisəsi](#method-count-muqayisesi)
7. [Custom Base Repository](#custom-base-repository)
8. [@NoRepositoryBean](#norepositorybean)
9. [İntervyu Sualları](#intervyu-sualları)

---

## Repository Hierarchy

Spring Data, verilənlər bazası ilə işi asanlaşdırmaq üçün bir neçə interfeys təqdim edir. Bu interfeyslər iyerarxik şəkildə qurulub:

```
Repository (marker interface)
    └── CrudRepository<T, ID>
            └── PagingAndSortingRepository<T, ID>
                    └── JpaRepository<T, ID>
```

Spring Data 3.x-dən etibarən yeni interfeyslər əlavə edildi:

```
Repository
    ├── CrudRepository<T, ID>
    │       └── ListCrudRepository<T, ID>  (Spring Data 3+)
    ├── PagingAndSortingRepository<T, ID>
    │       └── ListPagingAndSortingRepository<T, ID>  (Spring Data 3+)
    └── JpaRepository<T, ID>  (extends ListCrudRepository + ListPagingAndSortingRepository)
```

---

## CrudRepository

`CrudRepository` əsas CRUD əməliyyatlarını təmin edir. Qaytarma tipi `Iterable<T>`-dir.

```java
// CrudRepository interfeysi - əsas metodlar
public interface CrudRepository<T, ID> extends Repository<T, ID> {

    // Bir entity saxla (yarat və ya yenilə)
    <S extends T> S save(S entity);

    // Bir neçə entity saxla
    <S extends T> Iterable<S> saveAll(Iterable<S> entities);

    // ID ilə tap
    Optional<T> findById(ID id);

    // ID mövcuddurmu?
    boolean existsById(ID id);

    // Hamısını tap
    Iterable<T> findAll();

    // ID-lər siyahısı ilə tap
    Iterable<T> findAllById(Iterable<ID> ids);

    // Cəmi sayı qaytar
    long count();

    // ID ilə sil
    void deleteById(ID id);

    // Entity sil
    void delete(T entity);

    // ID-lər siyahısı ilə sil
    void deleteAllById(Iterable<? extends ID> ids);

    // Hamısını sil
    void deleteAll();
}
```

### İstifadə nümunəsi:

```java
// Entity sinfi
@Entity
@Table(name = "products")
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;
    private Double price;

    // getter/setter-lər
}

// CrudRepository istifadəsi
@Repository
public interface ProductCrudRepository extends CrudRepository<Product, Long> {
    // Əlavə metodlar yazılmaya bilər - CrudRepository-dəkilər kifayətdir
}

// Service
@Service
@RequiredArgsConstructor
public class ProductService {

    private final ProductCrudRepository repository;

    public Product save(Product product) {
        // Yeni yaradır və ya mövcudu yeniləyir
        return repository.save(product);
    }

    public List<Product> saveAll(List<Product> products) {
        // Iterable qaytarır - List-ə çevirmək lazımdır
        List<Product> result = new ArrayList<>();
        repository.saveAll(products).forEach(result::add);
        return result;
    }

    public Optional<Product> findById(Long id) {
        return repository.findById(id);
    }

    public boolean exists(Long id) {
        return repository.existsById(id);
    }

    public void delete(Long id) {
        repository.deleteById(id);
    }
}
```

---

## PagingAndSortingRepository

Səhifələmə (pagination) və sıralama (sorting) əməliyyatlarını əlavə edir.

```java
public interface PagingAndSortingRepository<T, ID> extends Repository<T, ID> {

    // Sıralama ilə hamısını tap
    Iterable<T> findAll(Sort sort);

    // Səhifələmə ilə tap
    Page<T> findAll(Pageable pageable);
}
```

```java
@Repository
public interface ProductPagingRepository
        extends PagingAndSortingRepository<Product, Long> {
    // CrudRepository metodları yoxdur! Ayrıca extend etmək lazımdır
}

// Service istifadəsi
@Service
public class ProductPagingService {

    private final ProductPagingRepository repository;

    public Page<Product> getProducts(int page, int size) {
        // Səhifə 0-dan başlayır
        Pageable pageable = PageRequest.of(page, size, Sort.by("name").ascending());
        return repository.findAll(pageable);
    }

    public Iterable<Product> getSorted() {
        // Yalnız sıralama, səhifələmə yox
        return repository.findAll(Sort.by("price").descending());
    }
}
```

---

## JpaRepository

Ən çox istifadə olunan interfeys. Bütün yuxarıdakıları birləşdirir + JPA-ya xas əlavə metodlar.

```java
public interface JpaRepository<T, ID>
        extends ListCrudRepository<T, ID>, ListPagingAndSortingRepository<T, ID>,
                QueryByExampleExecutor<T> {

    // flush - persistence context-i dərhal DB-ə yazır
    void flush();

    // save + flush birlikdə
    <S extends T> S saveAndFlush(S entity);

    // saveAll + flush
    <S extends T> List<S> saveAllAndFlush(Iterable<S> entities);

    // deleteAll - əvvəlcə hər birini yükləyir (lifecycle metodları üçün)
    void deleteAllInBatch(Iterable<T> entities);

    // SQL DELETE ilə hamısını sil (lifecycle metodları çağırılmır)
    void deleteAllInBatch();

    // ID-lər ilə sil (batch)
    void deleteAllByIdInBatch(Iterable<ID> ids);

    // Reference əldə et (lazy)
    T getReferenceById(ID id);

    // Example ilə axtarış
    <S extends T> List<S> findAll(Example<S> example);

    // List qaytarır (Iterable yox)
    List<T> findAll();
    List<T> findAllById(Iterable<ID> ids);
    List<T> findAll(Sort sort);
}
```

```java
@Repository
public interface ProductJpaRepository extends JpaRepository<Product, Long> {

    // Spring Data method naming convention ilə custom metodlar
    List<Product> findByNameContainingIgnoreCase(String name);

    List<Product> findByPriceBetween(Double min, Double max);

    Optional<Product> findByName(String name);

    boolean existsByName(String name);

    long countByPriceGreaterThan(Double price);
}

// Service
@Service
@RequiredArgsConstructor
public class ProductJpaService {

    private final ProductJpaRepository repository;

    public List<Product> searchByName(String keyword) {
        return repository.findByNameContainingIgnoreCase(keyword);
    }

    public Product saveAndSync(Product product) {
        // Dərhal DB-ə yazır, transaction gözləmir
        return repository.saveAndFlush(product);
    }

    public void bulkDelete(List<Long> ids) {
        // SQL DELETE ilə - əvvəlcə entity yükləmir, daha sürətli
        repository.deleteAllByIdInBatch(ids);
    }

    public Product getReference(Long id) {
        // Proxy qaytarır - DB-ə getmir (lazy)
        // Yalnız əlaqəli entity üçün istifadə et
        return repository.getReferenceById(id);
    }
}
```

---

## ListCrudRepository (Spring Data 3+)

Spring Data 3.0-dan etibarən `CrudRepository`-nin metodları `Iterable` əvəzinə `List` qaytarır.

```java
// Spring Data 3+ - ListCrudRepository
public interface ListCrudRepository<T, ID> extends CrudRepository<T, ID> {

    // Iterable əvəzinə List qaytarır
    <S extends T> List<S> saveAll(Iterable<S> entities);

    List<T> findAll();

    List<T> findAllById(Iterable<ID> ids);
}
```

```java
// YANLIŞ - Spring Data 2.x köhnə yol
@Repository
public interface OldProductRepository extends CrudRepository<Product, Long> {
    // saveAll Iterable qaytarır - List-ə çevirmək lazımdır
}

// DOĞRU - Spring Data 3.x yeni yol
@Repository
public interface NewProductRepository extends ListCrudRepository<Product, Long> {
    // saveAll List qaytarır - birbaşa istifadə et
}

// Praktiki fərq:
@Service
public class ComparisonService {

    // Köhnə yol - çevirmək lazımdır
    public List<Product> saveAllOld(
            CrudRepository<Product, Long> repo,
            List<Product> products) {
        List<Product> result = new ArrayList<>();
        // Iterable-ı List-ə çevir
        repo.saveAll(products).forEach(result::add);
        return result;
    }

    // Yeni yol - birbaşa
    public List<Product> saveAllNew(
            ListCrudRepository<Product, Long> repo,
            List<Product> products) {
        // Birbaşa List qaytarır
        return repo.saveAll(products);
    }
}
```

---

## Method Count Müqayisəsi

| Interfeys | Metodlar | Qaytarma tipi (findAll) |
|-----------|----------|------------------------|
| `Repository` | 0 (marker) | - |
| `CrudRepository` | ~10 | `Iterable<T>` |
| `ListCrudRepository` | ~10 | `List<T>` |
| `PagingAndSortingRepository` | 2 | `Iterable<T>` / `Page<T>` |
| `JpaRepository` | Hamısı + flush, batch ops | `List<T>` |

```java
// Hansını seçmək lazımdır?

// 1. Minimal CRUD lazımdırsa
public interface MinimalRepo extends CrudRepository<Product, Long> {}

// 2. Pagination lazımdırsa amma JPA-ya xas şey lazım deyilsə
public interface PagingRepo extends PagingAndSortingRepository<Product, Long> {}

// 3. Tam funksionallıq lazımdırsa (əksər hallarda)
public interface FullRepo extends JpaRepository<Product, Long> {}

// 4. Yalnız bir neçə metod expose etmək istəyirsənsə
public interface SelectiveRepo extends Repository<Product, Long> {
    // Yalnız istədiyin metodları əlavə et
    Optional<Product> findById(Long id);
    Product save(Product product);
    // deleteById yoxdur - qəsdən
}
```

---

## Custom Base Repository

Bütün repository-lər üçün ümumi davranış əlavə etmək üçün custom base repository yaradılır.

```java
// 1. Custom interfeys - @NoRepositoryBean ilə işarələ
@NoRepositoryBean
public interface SoftDeleteRepository<T, ID> extends JpaRepository<T, ID> {

    // Soft delete - DB-dən silmir, active=false qoyur
    void softDelete(ID id);

    // Yalnız aktiv olanları tap
    List<T> findAllActive();
}

// 2. İmplementasiya
@Repository
public class SoftDeleteRepositoryImpl<T extends SoftDeletable, ID>
        extends SimpleJpaRepository<T, ID>
        implements SoftDeleteRepository<T, ID> {

    private final EntityManager entityManager;

    // Mütləq bu constructor olmalıdır
    public SoftDeleteRepositoryImpl(
            JpaEntityInformation<T, ?> entityInformation,
            EntityManager entityManager) {
        super(entityInformation, entityManager);
        this.entityManager = entityManager;
    }

    @Override
    @Transactional
    public void softDelete(ID id) {
        // DB-dən silmir - yalnız aktiv=false edir
        T entity = findById(id)
            .orElseThrow(() -> new EntityNotFoundException("Entity tapılmadı: " + id));
        entity.setActive(false);
        save(entity);
    }

    @Override
    public List<T> findAllActive() {
        // Yalnız aktiv olanları qaytar
        return findAll()
            .stream()
            .filter(SoftDeletable::isActive)
            .collect(Collectors.toList());
    }
}

// 3. SoftDeletable interface
public interface SoftDeletable {
    boolean isActive();
    void setActive(boolean active);
}

// 4. Entity
@Entity
public class Product implements SoftDeletable {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;

    private boolean active = true; // default aktiv

    @Override
    public boolean isActive() { return active; }

    @Override
    public void setActive(boolean active) { this.active = active; }
}

// 5. Repository istifadəsi
@Repository
public interface ProductRepository
        extends SoftDeleteRepository<Product, Long> {
    // Soft delete funksionallığını avtomatik alır
}

// 6. Spring-ə xəbər ver - @EnableJpaRepositories konfiqurasiyasında
@SpringBootApplication
@EnableJpaRepositories(repositoryBaseClass = SoftDeleteRepositoryImpl.class)
public class Application {
    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}
```

---

## @NoRepositoryBean

`@NoRepositoryBean` annotasiyası Spring-ə bu interfeysin özü üçün bean yaratmamasını bildirir — yalnız ondan extend edən interfeyslər üçün bean yaradılır.

```java
// YANLIŞ - @NoRepositoryBean olmadan
// Spring bu interfeys üçün bean yaratmağa çalışacaq və xəta verəcək
public interface BaseRepository<T, ID> extends JpaRepository<T, ID> {
    List<T> findByCreatedAtAfter(LocalDateTime date);
}

// DOĞRU - @NoRepositoryBean ilə
@NoRepositoryBean // Bu interfeys üçün bean yaratma!
public interface BaseRepository<T, ID> extends JpaRepository<T, ID> {
    // Bütün entity-lər üçün ümumi metodlar
    List<T> findByCreatedAtAfter(LocalDateTime date);
}

// Bu interfeysdən extend edənlər üçün bean yaradılır
@Repository
public interface ProductRepository extends BaseRepository<Product, Long> {
    // Product-a xas metodlar
    List<Product> findByName(String name);
}

@Repository
public interface OrderRepository extends BaseRepository<Order, Long> {
    // Order-a xas metodlar
    List<Order> findByStatus(OrderStatus status);
}
```

```java
// Daha mürəkkəb nümunə - Audit metodları olan base
@NoRepositoryBean
public interface AuditableRepository<T, ID> extends JpaRepository<T, ID> {

    // Son N gündə yaradılanlar
    default List<T> findRecentlyCreated(int days) {
        LocalDateTime since = LocalDateTime.now().minusDays(days);
        return findByCreatedAtAfter(since);
    }

    List<T> findByCreatedAtAfter(LocalDateTime date);
    List<T> findByCreatedBy(String username);
}

// İmplementasiya - konkret entity üçün
@Repository
public interface UserRepository extends AuditableRepository<User, Long> {
    // User-a xas metodlar
    Optional<User> findByEmail(String email);
    boolean existsByEmail(String email);
}

// Service istifadəsi
@Service
@RequiredArgsConstructor
public class UserService {

    private final UserRepository userRepository;

    public List<User> getNewUsers() {
        // BaseRepository-dən gələn metod
        return userRepository.findRecentlyCreated(7);
    }

    public Optional<User> findByEmail(String email) {
        // UserRepository-yə xas metod
        return userRepository.findByEmail(email);
    }
}
```

---

## save() vs saveAndFlush() vs saveAllAndFlush()

```java
@Service
@RequiredArgsConstructor
@Transactional
public class SaveDemoService {

    private final ProductJpaRepository repository;

    public void demonstrateSave() {

        Product p = new Product("Laptop", 1500.0);

        // save() - persistence context-ə əlavə edir
        // Transaction bitənədək DB-ə yazmır (lazım olana qədər)
        Product saved = repository.save(p);
        // Bu nöqtədə DB-də olmaya bilər hələ

        // saveAndFlush() - dərhal DB-ə yazır
        Product flushed = repository.saveAndFlush(p);
        // DB-də yazıldı, amma transaction hələ açıqdır

        // Nə vaxt saveAndFlush lazımdır?
        // - Həmin transaction içində native query icra edəcəksənsə
        // - Trigger-ləri activate etmək lazımdırsa
        // - DB tərəfli default dəyərləri oxumaq lazımdırsa
    }

    public void demonstrateBatchSave() {
        List<Product> products = createProducts(1000);

        // Hamısını saxla + flush
        // Daha az DB round-trip
        List<Product> saved = repository.saveAllAndFlush(products);
    }

    // deleteAllInBatch vs deleteAll fərqi
    public void demonstrateDelete() {

        // deleteAll() - əvvəlcə SELECT edir, sonra hər birini ayrı DELETE
        // Lifecycle metodları (PreRemove) çağırılır
        repository.deleteAll();
        // N+1 delete problemi yarana bilər

        // deleteAllInBatch() - tək SQL DELETE
        // Lifecycle metodları ÇAĞIRILMIR
        repository.deleteAllInBatch();
        // Çox daha sürətli amma lifecycle yoxdur
    }
}
```

---

## İntervyu Sualları

**S: Repository, CrudRepository, JpaRepository fərqləri nədir?**

C: `Repository` yalnız marker interfeysidir, metod yoxdur. `CrudRepository` ~10 CRUD metod təqdim edir, `Iterable` qaytarır. `PagingAndSortingRepository` pagination/sorting əlavə edir. `JpaRepository` hamısını birləşdirir + flush, batch operations, `List` qaytarır. Spring Data 3+-da `ListCrudRepository` `CrudRepository`-nin `List` qaytaran versiyasıdır.

**S: @NoRepositoryBean nə üçün lazımdır?**

C: Spring Data hər `Repository` extend edən interfeys üçün avtomatik bean yaradır. Əgər bir base interfeys yaratmaq istəyirsənsə amma onun özü üçün bean istəmirsənsə `@NoRepositoryBean` işarəsini qoyursan. Məsələn, bütün repository-lər üçün ümumi metodları olan bir base interfeys.

**S: save() ilə saveAndFlush() arasındakı fərq nədir?**

C: `save()` entity-ni persistence context-ə əlavə edir, amma DB-ə yazma transaction sonuna qədər təxirə salına bilər. `saveAndFlush()` dərhal DB-ə yazır (flush edir) amma transaction hələ açıq qalır. Native query işlədəcəksənsə, trigger aktivləşdirəcəksənsə `saveAndFlush()` lazımdır.

**S: deleteAll() vs deleteAllInBatch() fərqi nədir?**

C: `deleteAll()` əvvəlcə bütün entity-ləri yükləyir (SELECT), sonra hər birini ayrı-ayrı silir — lifecycle metodları (@PreRemove) çağırılır. `deleteAllInBatch()` tək SQL DELETE icra edir, lifecycle metodları çağırılmır, çox daha sürətlidir amma @PreRemove kimi annotasiyalar işləmir.

**S: Custom base repository necə yaradılır?**

C: 3 addım: 1) `@NoRepositoryBean` annotasiyalı interfeys yarat, 2) `SimpleJpaRepository`-ni extend edən implementasiya yaz (doğru constructor mütləq), 3) `@EnableJpaRepositories(repositoryBaseClass = ...)` ilə Spring-ə xəbər ver.

**S: getReferenceById() vs findById() fərqi nədir?**

C: `findById()` dərhal DB-ə gedir və Optional qaytarır. `getReferenceById()` proxy (lazy reference) qaytarır, DB-ə getmir. Entity yalnız property-sinə ilk dəfə müraciət edildikdə yüklənir. Foreign key əlaqəsi qurmaq üçün entity yükləmədən istifadə etmək olar.
