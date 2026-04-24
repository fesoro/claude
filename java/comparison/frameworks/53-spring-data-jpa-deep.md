# Spring Data JPA vs Laravel Eloquent — Dərin Müqayisə

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Hər iki framework DB ilə işləmək üçün öz "yuxarı səviyyəli" abstraksiyasını verir. Spring tərəfində bu **Spring Data JPA** + **Hibernate** (JPA provider) kombinasiyasıdır. Laravel tərəfində isə **Eloquent ORM**-dır.

Spring Data JPA çox layered-dır: JPA standartı (specification), Hibernate (implementation), Spring Data JPA (Repository abstraksiyası). Repository interface yazırsan, Spring proxy yaradır və JPQL və ya Criteria API ilə query işlədir. Auditing, Specifications, Projections, Entity Graphs kimi güclü feature-lər var.

Laravel Eloquent "active record" pattern-idir — model həm data, həm DB əməliyyatları saxlayır. `User::find(1)->orders()->where('total', '>', 100)->get()` kimi sadə fluent API. Relationships (`hasMany`, `belongsTo`, `morphTo`), scopes, accessors/mutators, observers hamısı model-də yazılır. Daha sadə, amma daha az "formal" — JPA qədər standart deyil.

---

## Spring-də istifadəsi

### 1) Repository hierarchy

Spring Data JPA repository interface hierarchy verir:

```
Repository<T, ID>                    (marker interface)
   └── CrudRepository<T, ID>         (save, findById, findAll, delete)
        └── PagingAndSortingRepository (Page, Sort)
             └── JpaRepository        (JPA-specific: flush, saveAndFlush, deleteAllInBatch)
```

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-jpa</artifactId>
</dependency>
<dependency>
    <groupId>org.postgresql</groupId>
    <artifactId>postgresql</artifactId>
</dependency>
```

```yaml
# application.yml
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/shop
    username: shop
    password: secret
    hikari:
      maximum-pool-size: 20
      connection-timeout: 5000
  jpa:
    hibernate:
      ddl-auto: validate
    properties:
      hibernate:
        jdbc.batch_size: 50
        order_inserts: true
        order_updates: true
        default_batch_fetch_size: 20
    open-in-view: false       # çox vacib — OSIV-ni söndür
    show-sql: false
```

```java
@Entity
@Table(name = "users")
public class User {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, unique = true)
    private String email;

    @Column(name = "full_name")
    private String fullName;

    private boolean active;

    @OneToMany(mappedBy = "user", fetch = FetchType.LAZY)
    private List<Order> orders = new ArrayList<>();

    // getters, setters, equals, hashCode...
}

public interface UserRepository extends JpaRepository<User, Long> {
    // CRUD metodları avtomatik gəlir
}
```

`JpaRepository` ilə `save`, `findById`, `findAll`, `delete`, `count`, `existsById`, `findAllById` avtomatik işləyir.

### 2) Derived queries — metod adından query

Spring metod adını parse edir və query yaradır:

```java
public interface UserRepository extends JpaRepository<User, Long> {

    Optional<User> findByEmail(String email);

    List<User> findByActiveTrue();

    List<User> findByFullNameContainingIgnoreCase(String query);

    List<User> findByCreatedAtAfterAndActiveTrue(LocalDateTime since);

    Page<User> findByCountry(String country, Pageable pageable);

    // Count, delete də işləyir
    long countByActiveTrue();
    long deleteByLastLoginBefore(LocalDateTime cutoff);

    // Top/First
    Optional<User> findFirstByOrderByCreatedAtDesc();
    List<User> findTop10ByOrderByScoreDesc();

    // Nested property
    List<Order> findByUser_Email(String email);

    // Boolean
    boolean existsByEmail(String email);
}
```

Spring parsing qaydaları:

- `findBy`, `readBy`, `queryBy`, `getBy` — SELECT
- `existsBy` — boolean
- `countBy` — long
- `deleteBy` / `removeBy` — delete
- Operators: `And`, `Or`, `Between`, `LessThan`, `GreaterThan`, `Like`, `Containing`, `StartingWith`, `IgnoreCase`, `Is`, `IsNull`, `In`, `True`, `False`, `OrderBy`

Məhdudiyyət: metod adı uzanır. 3-4 kriterdən sonra `@Query` və ya Specification daha aydındır.

### 3) `@Query` — JPQL və native SQL

```java
public interface UserRepository extends JpaRepository<User, Long> {

    // JPQL
    @Query("select u from User u where u.email = :email and u.active = true")
    Optional<User> findActiveByEmail(@Param("email") String email);

    @Query("select new com.example.UserSummary(u.id, u.fullName, count(o)) " +
           "from User u left join u.orders o " +
           "where u.active = true " +
           "group by u.id, u.fullName")
    List<UserSummary> activeUserSummaries();

    // Native SQL
    @Query(value = """
        SELECT u.* FROM users u
        WHERE u.tsv @@ plainto_tsquery('english', :query)
        ORDER BY ts_rank(u.tsv, plainto_tsquery('english', :query)) DESC
        LIMIT 20
        """, nativeQuery = true)
    List<User> fullTextSearch(@Param("query") String query);

    // Modifying
    @Modifying(clearAutomatically = true)
    @Query("update User u set u.active = false where u.lastLogin < :cutoff")
    int deactivateInactive(@Param("cutoff") LocalDateTime cutoff);

    // Pagination ilə JPQL
    @Query("select u from User u where u.country = :country")
    Page<User> findByCountry(@Param("country") String country, Pageable pageable);
}
```

JPQL entity names istifadə edir (`User`, `u.orders`), SQL deyil. Native SQL-də table names (`users`, `orders`) və DB-specific feature-lər (tsvector, jsonb, CTE) istifadə oluna bilər.

### 4) Projections — DTO vs interface

Bütün entity-ni yükləmək əvəzinə yalnız lazım olan sahələri çəkmək üçün projection:

**Interface-based (read-only, sadə):**

```java
public interface UserSummary {
    Long getId();
    String getFullName();
    String getEmail();

    // Computed (SpEL)
    @Value("#{target.fullName + ' (' + target.email + ')'}")
    String getDisplayName();
}

public interface UserRepository extends JpaRepository<User, Long> {
    List<UserSummary> findByActiveTrue();

    <T> List<T> findByCountry(String country, Class<T> type);   // dinamik projection
}

// İstifadə
List<UserSummary> summaries = repo.findByActiveTrue();
List<UserFull> fulls = repo.findByCountry("AZ", UserFull.class);
```

**Class-based (DTO, mutable, daha çevik):**

```java
public record UserDto(Long id, String fullName, String email, long orderCount) {}

@Query("select new com.example.UserDto(u.id, u.fullName, u.email, count(o)) " +
       "from User u left join u.orders o group by u.id")
List<UserDto> userDtos();
```

Interface projection daha sadədir — Hibernate proxy yaradır. Class-based DTO isə explicit-dir, amma constructor-da bütün sahələr sadalanmalıdır.

**Performance**: projection tam entity-dən sürətlidir çünki yalnız seçilən sütunlar SELECT-ə daxil olur. `@OneToMany` kimi əlaqələr yüklənmir. Read-only səhifələr üçün idealdır.

### 5) Specifications — dinamik queries (Criteria API)

Çoxkriterli axtarış üçün metod adı və `@Query` kifayət etmir. Specification helper edir:

```java
public interface UserRepository extends JpaRepository<User, Long>, JpaSpecificationExecutor<User> {
}

public class UserSpecs {

    public static Specification<User> hasCountry(String country) {
        return (root, query, cb) -> country == null
            ? cb.conjunction()
            : cb.equal(root.get("country"), country);
    }

    public static Specification<User> nameLike(String q) {
        return (root, query, cb) -> q == null
            ? cb.conjunction()
            : cb.like(cb.lower(root.get("fullName")), "%" + q.toLowerCase() + "%");
    }

    public static Specification<User> isActive() {
        return (root, query, cb) -> cb.isTrue(root.get("active"));
    }

    public static Specification<User> createdAfter(LocalDateTime date) {
        return (root, query, cb) -> date == null
            ? cb.conjunction()
            : cb.greaterThan(root.get("createdAt"), date);
    }
}

// İstifadə — kompozisiya
Specification<User> spec = Specification
    .where(UserSpecs.hasCountry(filter.country()))
    .and(UserSpecs.nameLike(filter.q()))
    .and(UserSpecs.isActive())
    .and(UserSpecs.createdAfter(filter.since()));

Page<User> result = repo.findAll(spec, PageRequest.of(0, 20, Sort.by("createdAt").descending()));
```

Spring Data 3-də `Specification` funksional interface-dir. `and`, `or`, `not`, `where` ilə kombinə olunur. Dinamik filter (search form, admin panel) üçün ən çevik yanaşmadır.

### 6) Auditing — yaradıcı, yeniləyən, tarixlər

```java
@Configuration
@EnableJpaAuditing(auditorAwareRef = "auditorProvider")
public class JpaAuditConfig {

    @Bean
    public AuditorAware<String> auditorProvider() {
        return () -> Optional.ofNullable(SecurityContextHolder.getContext())
            .map(ctx -> ctx.getAuthentication())
            .filter(Authentication::isAuthenticated)
            .map(Authentication::getName);
    }
}

@MappedSuperclass
@EntityListeners(AuditingEntityListener.class)
public abstract class Auditable {
    @CreatedBy
    private String createdBy;

    @CreatedDate
    private LocalDateTime createdAt;

    @LastModifiedBy
    private String updatedBy;

    @LastModifiedDate
    private LocalDateTime updatedAt;

    @Version
    private Long version;
}

@Entity
public class Order extends Auditable {
    @Id @GeneratedValue
    private Long id;
    private BigDecimal total;
}
```

Save edəndə Hibernate avtomatik `createdBy`, `createdAt` doldurur. Update edəndə `updatedBy`, `updatedAt` yenilənir. `@Version` optimistic lock üçündür.

### 7) Entity Graphs — N+1 problemi

**N+1 problem**: `findAll` ilə 100 user gətirsən, sonra hər birinin `getOrders()` çağırsan — 1 + 100 = 101 query. Həll: eager fetch.

```java
@Entity
public class User {
    @OneToMany(mappedBy = "user", fetch = FetchType.LAZY)
    private List<Order> orders;
}

public interface UserRepository extends JpaRepository<User, Long> {

    // 1) JPQL fetch join
    @Query("select u from User u left join fetch u.orders where u.active = true")
    List<User> findActiveWithOrders();

    // 2) EntityGraph — dinamik
    @EntityGraph(attributePaths = {"orders", "orders.items"})
    List<User> findByActiveTrue();

    // 3) Named EntityGraph
    @EntityGraph(value = "User.withOrders")
    Optional<User> findById(Long id);
}

@Entity
@NamedEntityGraph(
    name = "User.withOrders",
    attributeNodes = {
        @NamedAttributeNode(value = "orders", subgraph = "orderItems")
    },
    subgraphs = {
        @NamedSubgraph(name = "orderItems", attributeNodes = @NamedAttributeNode("items"))
    }
)
public class User { ... }
```

Entity Graph ilə Hibernate LEFT JOIN FETCH istifadə edir. N+1 aradan qalxır. Amma çox eager graph cartesian product yarada bilər — iki `@OneToMany` eyni anda fetch etmə.

`spring.jpa.properties.hibernate.default_batch_fetch_size: 20` opsiyası default lazy yükləmədə də batch gətirir — 100 user üçün `SELECT ... WHERE user_id IN (?, ?, ...)` ilə 5 query. Mükəmməl deyil, amma N+1-dən yaxşıdır.

### 8) Transactions — `@Transactional`

```java
@Service
public class OrderService {

    private final OrderRepository orderRepo;
    private final PaymentService payment;

    @Transactional
    public Order placeOrder(PlaceOrderRequest req) {
        Order order = new Order(req);
        orderRepo.save(order);                // SQL INSERT

        PaymentResult result = payment.charge(order);

        if (! result.isSuccessful()) {
            throw new PaymentFailedException(); // transaction rollback
        }

        order.markPaid();
        return order;                         // auto-flush on commit
    }

    @Transactional(readOnly = true)
    public List<Order> userOrders(Long userId) {
        return orderRepo.findByUserId(userId);
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void auditAction(String action) {
        // Yeni transaction — outer rollback bunu rollback etmir
    }

    @Transactional(noRollbackFor = BusinessWarningException.class)
    public void doSomething() { ... }
}
```

Propagation tipləri:

- `REQUIRED` (default) — mövcud tx-ə qoşulur, yoxdursa yaradır
- `REQUIRES_NEW` — həmişə yeni tx (ayrıca commit/rollback)
- `SUPPORTS` — varsa istifadə et, yoxsa tx-siz işlə
- `MANDATORY` — tx olmalıdır, yoxdursa exception
- `NEVER` — tx OLMAMALIDIR
- `NESTED` — savepoint (yalnız JDBC)

Default-da **RuntimeException** rollback tetikleyir, checked exception yox. Bunu dəyişmək üçün `@Transactional(rollbackFor = Exception.class)`.

### 9) Locking — Optimistic və Pessimistic

**Optimistic lock** (`@Version`):

```java
@Entity
public class Product {
    @Id @GeneratedValue
    private Long id;

    private int stock;

    @Version
    private Long version;
}

@Transactional
public void decrementStock(Long productId, int qty) {
    Product p = repo.findById(productId).orElseThrow();
    if (p.getStock() < qty) throw new InsufficientStockException();
    p.setStock(p.getStock() - qty);
    // commit-də version match olmasa OptimisticLockException
}
```

SQL səviyyəsində `UPDATE products SET stock=?, version=? WHERE id=? AND version=?`. Əgər rowcount 0 olsa — exception.

**Pessimistic lock**:

```java
public interface ProductRepository extends JpaRepository<Product, Long> {
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("select p from Product p where p.id = :id")
    Optional<Product> findByIdForUpdate(@Param("id") Long id);
}
```

SQL: `SELECT ... FOR UPDATE`. Digər tx update etməyə çalışsa — gözləyir.

Optimistic daha perf-friendly-dir (lock yox), amma conflict olsa retry lazımdır. Pessimistic contention çox olanda daha sadədir, amma deadlock riski var.

### 10) Second-level cache

Hibernate birinci səviyyə cache (session level) default açıqdır. İkinci səviyyə (cross-session) açmaq lazımdır:

```xml
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
spring:
  jpa:
    properties:
      hibernate:
        cache:
          use_second_level_cache: true
          region.factory_class: jcache
        javax.cache.provider: org.ehcache.jsr107.EhcacheCachingProvider
```

```java
@Entity
@Cache(usage = CacheConcurrencyStrategy.READ_WRITE, region = "products")
public class Product {
    @Id @GeneratedValue
    private Long id;

    @Cache(usage = CacheConcurrencyStrategy.READ_WRITE)
    @OneToMany(mappedBy = "product")
    private List<Review> reviews;
}
```

`@Cache` entity və kolleksiyaları cache-ə yazır. Ən çox oxunan, az dəyişən data üçün faydalıdır (reference data, lookup tables). Lazım olmayanda istifadə etmə — stale data problemi yaranır.

### 11) Kompleks müqayisə — JPA vs Eloquent nümunəsi

Tapşırıq: "Sonuncu 30 gündə ən az 100 dollarlıq 3 və artıq order vermiş, aktiv olan user-lər, ad və email-ə görə axtarış".

**Spring Data JPA — 3 variant:**

```java
// 1) Derived query — imkansız (çox mürəkkəb)

// 2) JPQL
@Query("""
    select distinct u from User u
    join u.orders o
    where u.active = true
      and o.createdAt > :since
      and o.total >= 100
      and (:q is null or lower(u.fullName) like lower(concat('%', :q, '%')))
    group by u
    having count(o) >= 3
    """)
Page<User> powerUsers(@Param("since") LocalDateTime since,
                      @Param("q") String q,
                      Pageable pageable);

// 3) Specification
public static Specification<User> powerUser(LocalDateTime since, String q) {
    return (root, query, cb) -> {
        query.distinct(true);
        Join<User, Order> orders = root.join("orders");

        List<Predicate> predicates = new ArrayList<>();
        predicates.add(cb.isTrue(root.get("active")));
        predicates.add(cb.greaterThan(orders.get("createdAt"), since));
        predicates.add(cb.ge(orders.get("total"), 100));

        if (q != null) {
            predicates.add(cb.like(cb.lower(root.get("fullName")),
                "%" + q.toLowerCase() + "%"));
        }

        query.groupBy(root.get("id"));
        query.having(cb.ge(cb.count(orders), 3L));

        return cb.and(predicates.toArray(new Predicate[0]));
    };
}
```

### 12) Extra — QueryDSL

Kompleks dinamik query-lər üçün QueryDSL də istifadə oluna bilər:

```java
QUser u = QUser.user;
QOrder o = QOrder.order;

List<User> result = queryFactory
    .selectFrom(u)
    .join(u.orders, o)
    .where(u.active.isTrue()
        .and(o.createdAt.after(since))
        .and(o.total.goe(100)))
    .groupBy(u.id)
    .having(o.count().goe(3))
    .fetch();
```

Type-safe, auto-complete — Criteria API-dan rahatdır. Amma code generation (Maven plugin) lazım və əlavə dependency-dir.

---

## Laravel-də istifadəsi

### 1) Eloquent model

```php
// app/Models/User.php
class User extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['email', 'full_name', 'country', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'created_at' => 'datetime',
        'metadata'   => 'array',              // JSON sütun
    ];

    protected $hidden = ['password', 'remember_token'];

    // Relationships
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->using(RoleUser::class)        // pivot model
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
```

Eloquent model **Active Record** pattern-idir. DB cədvəl adı model adının snake_case + plural versiyasıdır (`User` → `users`). Bunu override etmək üçün `protected $table`.

### 2) Query Builder — Eloquent üzərində

```php
// Sadə get
$users = User::where('active', true)->get();

// Multi-condition
$users = User::where('country', 'AZ')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// Paginate
$users = User::where('active', true)->paginate(20);
// response: { data: [...], current_page, per_page, total, ... }

// Chunk (bulk processing)
User::where('active', true)->chunk(500, function ($users) {
    foreach ($users as $user) {
        dispatch(new SyncUser($user));
    }
});

// Cursor (generator, ən az yaddaş)
foreach (User::where('active', true)->cursor() as $user) {
    dispatch(new SyncUser($user));
}

// Aggregation
$count = User::where('country', 'AZ')->count();
$avgScore = User::where('active', true)->avg('score');

// Exists
$exists = User::where('email', $email)->exists();

// First or null
$user = User::where('email', $email)->first();

// First or throw
$user = User::where('email', $email)->firstOrFail();

// findOrFail
$user = User::findOrFail(1);

// pluck — tək sütun
$emails = User::where('active', true)->pluck('email')->toArray();

// selectRaw
$stats = User::selectRaw('country, count(*) as cnt')
    ->groupBy('country')
    ->get();
```

### 3) Eager loading — `with()` N+1-dən qoruyur

```php
// Pis — N+1
$users = User::all();
foreach ($users as $user) {
    echo $user->orders->count();   // hər dəfə SELECT
}

// Yaxşı — eager load
$users = User::with('orders')->get();
// 2 query: SELECT * FROM users; SELECT * FROM orders WHERE user_id IN (...)

// Nested
$users = User::with('orders.items')->get();

// Constrained eager load
$users = User::with(['orders' => function ($q) {
    $q->where('total', '>', 100)->latest();
}])->get();

// Eager counts
$users = User::withCount('orders')->get();
foreach ($users as $user) {
    echo $user->orders_count;
}

// Eager aggregates (Laravel 9+)
$users = User::withSum('orders', 'total')
    ->withAvg('orders', 'total')
    ->get();
```

**`preventLazyLoading()` in non-prod** — lazy load aşkar etmək üçün:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());

    Model::handleLazyLoadingViolationUsing(function ($model, $relation) {
        $class = $model::class;
        logger()->warning("Lazy loading $relation on $class");
    });
}
```

Local/staging-də lazy load olunca exception atır. Production-da davranışı dəyişmir — log-a yazır.

### 4) Relationships — hasOne, hasMany, belongsToMany, morphTo

```php
class Post extends Model
{
    // One-to-many
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Inverse
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Many-to-many with pivot model
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withPivot('added_by')
            ->withTimestamps();
    }

    // Polymorphic — bir model çoxlu tipə aid ola bilər
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Comment extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo();           // Post, Video, Product...
    }
}

// Miqrasiya
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->text('body');
    $table->morphs('commentable');        // commentable_id + commentable_type
    $table->timestamps();
});
```

### 5) Scopes — reusable query logic

**Local scope** (model-in metod):

```php
class User extends Model
{
    public function scopeActive(Builder $q): void
    {
        $q->where('active', true);
    }

    public function scopeFromCountry(Builder $q, string $country): void
    {
        $q->where('country', $country);
    }

    public function scopeCreatedSince(Builder $q, Carbon $date): void
    {
        $q->where('created_at', '>', $date);
    }
}

// İstifadə
User::active()->fromCountry('AZ')->get();
User::active()->createdSince(now()->subDays(30))->count();
```

**Global scope** (həmişə tətbiq olunur):

```php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    }
}

class Order extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}

// İndi Order::all() avtomatik tenant-a filter
// Scope-u ignore etmək: Order::withoutGlobalScope(TenantScope::class)->all();
```

### 6) Accessors/Mutators — `Attribute` cast (Laravel 9+)

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Model
{
    // full_name = first_name + ' ' + last_name
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attrs) => "{$attrs['first_name']} {$attrs['last_name']}",
        );
    }

    // password automatic hash
    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => bcrypt($value),
        );
    }

    // JSON cast əvəzinə custom
    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true) ?? [],
            set: fn ($value) => json_encode($value),
        );
    }
}

$user->full_name;    // Getter
$user->password = 'secret';   // Setter auto-bcrypt
```

### 7) Observers — JPA `EntityListener` ekvivalenti

```php
class UserObserver
{
    public function creating(User $user): void
    {
        $user->uuid = Str::uuid();
    }

    public function created(User $user): void
    {
        dispatch(new SendWelcomeEmail($user));
    }

    public function updating(User $user): void
    {
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    }

    public function deleting(User $user): void
    {
        $user->orders()->delete();
    }
}

// app/Providers/AppServiceProvider.php
public function boot(): void
{
    User::observe(UserObserver::class);
}
```

Event lifecycle: `retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`, `restoring`, `restored`, `forceDeleted`.

### 8) Transactions — `DB::transaction()`

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($req) {
    $order = Order::create([...]);
    $order->items()->createMany($req->items);
    $paymentResult = app(PaymentService::class)->charge($order);

    if (! $paymentResult->success) {
        throw new PaymentException;   // auto-rollback
    }

    $order->update(['status' => 'paid']);

    return $order;
});

// Manual
DB::beginTransaction();
try {
    // ...
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}

// Retry on deadlock
DB::transaction(function () { ... }, 3);   // 3 dəfə retry
```

Laravel transaction-ı **per request** deyil, explicit-dir. JPA `@Transactional` kimi annotation yoxdur — funksiya closure içində olur.

### 9) Soft deletes

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Model
{
    use SoftDeletes;
}

// Miqrasiya: softDeletes() → deleted_at sütunu
Schema::table('users', fn ($t) => $t->softDeletes());

// Delete
$user->delete();                 // UPDATE SET deleted_at = NOW()

// Query auto-filter
User::all();                     // only non-deleted
User::withTrashed()->get();     // hamısı
User::onlyTrashed()->get();     // yalnız deleted
User::find($id)->restore();
User::find($id)->forceDelete(); // HARD delete
```

### 10) Pivot models

```php
class Membership extends Pivot
{
    protected $table = 'memberships';

    public $timestamps = true;

    protected $casts = [
        'joined_at' => 'datetime',
        'role'      => 'string',
    ];

    public function scopeAdmins(Builder $q): void
    {
        $q->where('role', 'admin');
    }
}

class User extends Model
{
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->using(Membership::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps()
            ->as('membership');
    }
}

// İstifadə
$user->teams->each(function ($team) {
    echo $team->membership->role;
    echo $team->membership->joined_at->diffForHumans();
});
```

### 11) Kompleks müqayisə — eyni tapşırıq Eloquent-də

Tapşırıq: "Sonuncu 30 gündə ən az 100 dollarlıq 3 və artıq order vermiş, aktiv olan user-lər".

```php
// 1) whereHas
$users = User::active()
    ->whereHas('orders', function ($q) {
        $q->where('total', '>=', 100)
          ->where('created_at', '>', now()->subDays(30));
    }, '>=', 3)
    ->when($q, fn ($query) => $query->where('full_name', 'like', "%$q%"))
    ->withCount(['orders as recent_order_count' => function ($q) {
        $q->where('total', '>=', 100)
          ->where('created_at', '>', now()->subDays(30));
    }])
    ->paginate(20);

// 2) Raw SQL
$users = User::select('users.*', DB::raw('count(orders.id) as order_count'))
    ->join('orders', 'orders.user_id', '=', 'users.id')
    ->where('users.active', true)
    ->where('orders.total', '>=', 100)
    ->where('orders.created_at', '>', now()->subDays(30))
    ->groupBy('users.id')
    ->havingRaw('count(orders.id) >= 3')
    ->paginate(20);
```

`whereHas` subquery yaradır: `WHERE EXISTS (SELECT ... FROM orders WHERE ...)`. 3-cü parametr count müqayisəsidir: `>= 3`.

### 12) Raw query — qaçış yolu

```php
// Raw where
User::whereRaw('lower(full_name) like ?', ['%' . strtolower($q) . '%'])->get();

// DB::select
$results = DB::select('select * from users where email = ?', [$email]);

// DB::statement (DDL)
DB::statement('REFRESH MATERIALIZED VIEW user_stats');
```

### 13) composer.json snippet

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0"
    },
    "require-dev": {
        "barryvdh/laravel-ide-helper": "^3.0",
        "beyondcode/laravel-query-detector": "^1.7"
    }
}
```

`query-detector` lazy loading N+1 problemlərini local-da tapır — `preventLazyLoading` ilə birlikdə istifadə olunur.

```php
// config/database.php — PostgreSQL
'pgsql' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'shop'),
    'username' => env('DB_USERNAME', 'shop'),
    'password' => env('DB_PASSWORD', ''),
    'charset'  => 'utf8',
    'prefix'   => '',
    'schema'   => 'public',
    'sslmode'  => 'prefer',
    'options'  => [
        PDO::ATTR_PERSISTENT => true,    // persistent connection
    ],
],
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring Data JPA | Laravel Eloquent |
|---|---|---|
| Pattern | Repository + Entity (Data Mapper) | Active Record |
| Entity | `@Entity` + annotations | Model class extends Model |
| DB əməliyyatları | Repository-də | Model üzərində (`User::find`, `$user->save()`) |
| Derived query | `findByEmailAndActiveTrue` | `where('email', ...)->where('active', true)` |
| Raw SQL | `@Query(nativeQuery=true)` | `DB::select()`, `whereRaw` |
| Dinamik query | Specification, Criteria API, QueryDSL | `when()`, scope, Query Builder chain |
| Projection | Interface + class-based DTO | `select()`, `pluck()`, explicit DTO |
| N+1 həlli | Entity Graph, `join fetch`, batch_size | `with()`, `withCount()`, `preventLazyLoading` |
| Transaction | `@Transactional` annotation | `DB::transaction(closure)` |
| Optimistic lock | `@Version` | Manual (`where('version', $v)`) |
| Pessimistic lock | `@Lock(PESSIMISTIC_WRITE)` | `lockForUpdate()`, `sharedLock()` |
| Cascade | `@OneToMany(cascade = CascadeType.ALL)` | Observer + `deleting` event |
| Soft delete | Manual və ya Hibernate filter | Built-in `SoftDeletes` trait |
| Audit | `@CreatedBy`, `@LastModifiedDate` | Timestamps + observer, manual user |
| 2nd level cache | Hibernate + JCache | Yoxdur (Redis manual) |
| Polymorphic | Manual `@Any` | Built-in `morphMany`, `morphTo` |
| Global filter | Hibernate filter | Global scope |
| Pivot | `@ManyToMany` + join table | `BelongsToMany` + Pivot model |
| Type safety | Güclü (JPQL compile check yoxdur, amma Criteria var) | Zəif (stringly-typed query) |
| Öyrənmə | Yüksək (JPA + Hibernate + Spring Data) | Aşağı |

---

## Niyə belə fərqlər var?

**Java ekosisteminin "formalizm" ənənəsi.** JPA bir standartdır (JSR 338), Hibernate ondan əvvəl yaranıb. Enterprise mühitdə müxtəlif DB-lərdə eyni kodun işləməsi mühümdür. Spring Data JPA bu standart üzərinə repository abstraksiyası qurur. Data Mapper pattern-i domain model-i DB-dən ayırır — DDD-ə uyğundur.

**PHP/Laravel-in "pragmatic" yanaşması.** Active Record pattern kod yazmağı sadələşdirir — bir sinif həm biznes data, həm DB əməliyyatları. Model başına "what you see is what you get". Abstraksiya lay-ları azdır, debug sadədir, prototip sürətlidir. Mənfisi: DDD-nin "pure domain model" prinsipinə uyğun deyil.

**JPQL vs Eloquent Query Builder.** JPQL entity-oriented — `SELECT u FROM User u`. Obyektlə işləyir, DB-agnostic. Eloquent Query Builder isə SQL-ə daha yaxındır — `whereHas`, `join`, `select`. Hər ikisi güclüdür, amma JPQL-də daha çox compile-time yoxlama mümkündür (JPA metamodel ilə). Laravel stringly-typed (sütun adı string).

**Specifications vs whereHas.** Spring Specification + Criteria API dinamik kompleks axtarışlar üçün ideal — proqram boyu filter birləşdirilir. Laravel `when()` + scopes ilə eyni işi daha "fluent" edir. Hər ikisi praktikadır — fərq syntax elegansında.

**Lazy loading davranışı.** JPA default `LAZY` olsa da, `@Transactional` xaricində accees etsən `LazyInitializationException` atır (proxy detach olur). OSIV (Open Session In View) bunu "ört-basdır edir" amma performans üçün zərərlidir. Eloquent default lazy-dir və həmişə əlçatandır — controller, Blade template, hətta sərialisation zamanı. Nəticə: Laravel-də lazy loading "accidental N+1"-ə səbəb olur. `preventLazyLoading()` bu problemi local-da tapır.

**Second-level cache.** Hibernate entity-cache konseptləri Java-da 20 ildir mövcud — güclü amma kompleks. Cache invalidation, distributed cache (Hazelcast, Infinispan) hamısı Hibernate ilə inteqrasiyada. Laravel belə built-in cache yoxdur; bunu Redis + manual cache key strategiyası ilə həll edir. Daha sadədir, amma automatic invalidation yoxdur.

**Audit.** JPA-da `@CreatedDate`, `@LastModifiedDate`, `@CreatedBy`, `@LastModifiedBy` standartlaşıb. Laravel `created_at`, `updated_at` avtomatik doldurur, amma `created_by`/`updated_by` manuel və ya observer ilə. Laravel "audit log" plugin-ləri (`laravel-auditing`) bu boşluğu doldurur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Specification + Criteria API — compile-time type-safe dinamik query
- QueryDSL — type-safe query DSL
- `@EntityGraph` — eager fetch dinamik
- `@Version` optimistic lock built-in
- Hibernate filters (tenant, soft delete) — declarative
- Second-level cache (EhCache, Hazelcast, Infinispan)
- Named queries — `@NamedQuery`, `@NamedNativeQuery`
- JPQL — entity-oriented query language
- Interface projection (Hibernate proxy)
- Auditing annotations (`@CreatedBy`, `@LastModifiedDate`) standartlaşıb
- `@Lock(PESSIMISTIC_READ/WRITE)` annotation
- Hibernate Envers — full history audit (versiyalama)
- `@NamedEntityGraph` — reusable fetch plan
- Custom repository fragments (Spring Data)

**Yalnız Laravel-də (və ya daha sadə):**
- Built-in `SoftDeletes` trait
- Polymorphic relations (`morphTo`, `morphMany`) — konfiqsuz
- Accessors/Mutators — `Attribute` cast class
- Observer-lər — lifecycle hook-lar asan
- Global scopes — tenant isolation sadə
- `with()` və `withCount()` — eager load fluent API
- Pivot models — `Pivot` sinifi extend
- Cursor/lazy collections — böyük data iteration
- `preventLazyLoading()` — dev-də N+1 aşkar
- `firstOrCreate`, `updateOrCreate`, `firstOrNew` — upsert tək sətirdə
- `touch()` — relation-in `updated_at` yenilə
- `$casts` array — tipləri sadə çevirmə
- Chunk/cursor pagination (`chunkById`, `cursor`)
- Query Builder macros — reusable query primitiv
- Query log — `DB::listen` ilə bütün query-lər

---

## Best Practices

**Spring Data JPA:**
- `spring.jpa.open-in-view: false` — ALWAYS. OSIV lazy loading maskalayır
- Controller-də entity-ni birbaşa cavab kimi qaytarmayın — DTO/projection istifadə edin
- N+1 aşkar etmək üçün SQL log və ya `hibernate.generate_statistics=true`
- `@Transactional` service layer-də, repository-də deyil
- `readOnly = true` GET endpoint-lər üçün
- Batch insert/update üçün `hibernate.jdbc.batch_size` + `saveAll`
- `@Modifying` sorğularından sonra `clearAutomatically = true` əlavə edin
- Mürəkkəb query üçün Specification/QueryDSL — `@Query`-də 20 sətirlik JPQL yazma
- Entity Graph birdən çox `@OneToMany` fetch etsə cartesian product — DTO projection seçin
- `@Version` optimistic lock ilə retry logic — concurrent update üçün
- Native query-də sütun adlarını `CONSTRUCTOR` map-lə (Projection)
- `LAZY` default-dur — əsəbləşmə, amma `LazyInitializationException` ol maq üçün eager fetch planla

**Laravel Eloquent:**
- `preventLazyLoading(! app()->isProduction())` — bootstrap-da
- Bütün controller endpoint-ləri üçün `with()` və ya `load()` istifadə edin
- `chunk()` və ya `cursor()` — `get()` əvəzinə böyük data üçün
- Global scope-ları diqqətli istifadə edin — test-də `withoutGlobalScopes()` çox vaxt unudulur
- Eloquent Event və Observer — hər modelin yalnız bir Observer-i olsun
- `SoftDeletes` ilə birlikdə unique index `deleted_at` sütununu nəzərə alsın
- `DB::transaction` closure və retry limiti ilə — `DB::transaction(fn() => ..., 3)`
- `updateOrCreate` + `firstOrCreate` race condition yaradır — `DB::lock` lazım ola bilər
- `$fillable` ya `$guarded` istifadə edin — mass assignment təhlükəsi
- `query-detector` və `laravel-debugbar` local-da, prod-da deyil
- Query scope-ları "verb" (active, published) kimi adlandırın
- Faktoridə real random data — `fake()->name()`, `fake()->email()`

---

## Yekun

Spring Data JPA güclü, standart, enterprise-ready platformadır. JPA + Hibernate + Spring abstraction-ları bir yerdə çox layer-dir — öyrənmə əyrisi yüksəkdir. Amma Specification, Entity Graph, Auditing, Optimistic lock, Second-level cache kimi feature-lər mürəkkəb sistemlərdə çox faydalıdır. Repository pattern domain model-i DB-dən ayırır, bu DDD layihəsinə uyğundur.

Laravel Eloquent Active Record pattern-lə sürətli development verir. Kod oxunaqlı, relation API (hasMany, morphMany) çox sadədir, SoftDeletes, Observer, Scope built-in gəlir. Mənfisi: lazy loading asan N+1 problemi yaradır, güclü tipləmə yoxdur, kompleks query-lərdə `whereHas` çox nested olur. `preventLazyLoading()` və Specification-ekvivalenti (query scope) yanaşması bu problemləri həll edir.

Seçim qaydası: **kompleks domain, DDD, çoxsaylı DB backend, enterprise audit/cache tələbləri** — Spring Data JPA. **CRUD-heavy app, sürətli MVP, simple multi-tenancy, kiçik-orta komanda** — Laravel Eloquent. Hər ikisi production-da stabil işləyir, amma hər birinin öz güclü və zəif tərəfləri var. Əsas nöqtə: N+1-ni hər ikisində də planla (eager fetch/with), transaction sərhədini aydın müəyyən et, lazy loading davranışını başa düş.
