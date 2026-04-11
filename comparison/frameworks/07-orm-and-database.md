# ORM və Database

## Giris

ORM (Object-Relational Mapping) verilənlər bazasindaki cedvelleri proqramlasdirma dilindeki obyektlere uygunlasdirir. Spring ekosistemin de JPA (Java Persistence API) ve onun en populyar implementasiyasi olan Hibernate istifade olunur. Laravel-de ise Eloquent ORM istifade edilir. Her iki yaklasim eyni problemi hell edir, amma ferqli felsefe ile.

## Spring-de istifadesi

### Entity sinifi yaratmaq

Spring Data JPA-da verilenbazasi cedveli bir Java sinifi ile temsil olunur. `@Entity`, `@Table`, `@Column` kimi annotasiyalar istifade edilir:

```java
import jakarta.persistence.*;
import java.time.LocalDateTime;
import java.util.List;

@Entity
@Table(name = "users")
public class User {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "name", nullable = false, length = 100)
    private String name;

    @Column(name = "email", unique = true, nullable = false)
    private String email;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    @OneToMany(mappedBy = "user", cascade = CascadeType.ALL, fetch = FetchType.LAZY)
    private List<Post> posts;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "department_id")
    private Department department;

    // Getters ve Setters
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }
    public List<Post> getPosts() { return posts; }
    public void setPosts(List<Post> posts) { this.posts = posts; }
    public Department getDepartment() { return department; }
    public void setDepartment(Department department) { this.department = department; }
}
```

```java
@Entity
@Table(name = "posts")
public class Post {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String title;

    @Column(columnDefinition = "TEXT")
    private String content;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "user_id", nullable = false)
    private User user;

    @OneToMany(mappedBy = "post", cascade = CascadeType.ALL)
    private List<Comment> comments;

    @ManyToMany
    @JoinTable(
        name = "post_tags",
        joinColumns = @JoinColumn(name = "post_id"),
        inverseJoinColumns = @JoinColumn(name = "tag_id")
    )
    private List<Tag> tags;

    // Getters ve Setters
}
```

### Spring Data Repository

Spring Data JPA-nin en guclu xususiyyetlerinden biri repository interfeyslerdir. Metod adina gore avtomatik sorgu yaradilir:

```java
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;
import java.util.List;
import java.util.Optional;

public interface UserRepository extends JpaRepository<User, Long> {

    // Metod adina gore avtomatik sorgu
    Optional<User> findByEmail(String email);

    List<User> findByNameContainingIgnoreCase(String name);

    List<User> findByDepartmentIdOrderByNameAsc(Long departmentId);

    boolean existsByEmail(String email);

    // JPQL ile xususi sorgu
    @Query("SELECT u FROM User u WHERE u.email LIKE %:domain")
    List<User> findByEmailDomain(@Param("domain") String domain);

    // Native SQL sorgu
    @Query(value = "SELECT * FROM users WHERE created_at > NOW() - INTERVAL '30 days'",
           nativeQuery = true)
    List<User> findRecentUsers();

    // JOIN FETCH ile eager loading
    @Query("SELECT u FROM User u JOIN FETCH u.posts WHERE u.id = :id")
    Optional<User> findByIdWithPosts(@Param("id") Long id);
}
```

```java
public interface PostRepository extends JpaRepository<Post, Long> {

    List<Post> findByUserIdOrderByCreatedAtDesc(Long userId);

    @Query("SELECT p FROM Post p WHERE p.title LIKE %:keyword% OR p.content LIKE %:keyword%")
    List<Post> search(@Param("keyword") String keyword);

    // Pagination desteklenir
    Page<Post> findByUserId(Long userId, Pageable pageable);
}
```

### Criteria API ile dinamik sorgular

Murakkeb ve dinamik sorgular ucun Criteria API istifade olunur:

```java
import jakarta.persistence.criteria.*;
import org.springframework.stereotype.Repository;
import jakarta.persistence.EntityManager;
import jakarta.persistence.PersistenceContext;
import java.util.ArrayList;
import java.util.List;

@Repository
public class UserSearchRepository {

    @PersistenceContext
    private EntityManager entityManager;

    public List<User> searchUsers(String name, String email, Long departmentId) {
        CriteriaBuilder cb = entityManager.getCriteriaBuilder();
        CriteriaQuery<User> query = cb.createQuery(User.class);
        Root<User> root = query.from(User.class);

        List<Predicate> predicates = new ArrayList<>();

        if (name != null && !name.isEmpty()) {
            predicates.add(cb.like(cb.lower(root.get("name")), 
                          "%" + name.toLowerCase() + "%"));
        }

        if (email != null && !email.isEmpty()) {
            predicates.add(cb.equal(root.get("email"), email));
        }

        if (departmentId != null) {
            predicates.add(cb.equal(root.get("department").get("id"), departmentId));
        }

        query.where(predicates.toArray(new Predicate[0]));
        query.orderBy(cb.asc(root.get("name")));

        return entityManager.createQuery(query).getResultList();
    }
}
```

### Service layerinde istifade

```java
@Service
@Transactional
public class UserService {

    private final UserRepository userRepository;

    public UserService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    public User createUser(String name, String email) {
        if (userRepository.existsByEmail(email)) {
            throw new RuntimeException("Bu email artiq movcuddur");
        }
        User user = new User();
        user.setName(name);
        user.setEmail(email);
        user.setCreatedAt(LocalDateTime.now());
        return userRepository.save(user);
    }

    @Transactional(readOnly = true)
    public Page<User> getAllUsers(int page, int size) {
        return userRepository.findAll(PageRequest.of(page, size, Sort.by("name")));
    }

    public void deleteUser(Long id) {
        userRepository.deleteById(id);
    }
}
```

## Laravel-de istifadesi

### Eloquent Model

Laravel-de model yaratmaq cox sadedir. Cedvel adi, primary key, timestamps kimi seyler convention ile avtomatik mueyyen olunur:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Model
{
    // Cedvel adi: convention ile "users" olur (sinif adinin plural forması)
    // protected $table = 'users'; // Ferqli ad lazim olsa

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Elaqeler (Relationships)
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Accessor - virtual atribut
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Mutator - deyeri yazarken deyisdirmek
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    // Scope - tekrar istifade olunan sorgu filtrleri
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecentlyCreated($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    protected $fillable = ['title', 'content', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }
}
```

### Eloquent ile sorgular

```php
// Butun istifadecileri al
$users = User::all();

// Tekli tapma
$user = User::find(1);
$user = User::findOrFail(1); // Tapilmasa 404 qaytarir

// Where sertleri
$users = User::where('is_active', true)
    ->where('created_at', '>=', now()->subDays(30))
    ->orderBy('name')
    ->get();

// Scope istifadesi
$activeUsers = User::active()->recentlyCreated(7)->get();

// Pagination
$users = User::active()->paginate(15);

// Eager Loading - N+1 problemini hell edir
$users = User::with(['posts', 'department'])->get();
$users = User::with(['posts.comments', 'posts.tags'])->get();

// Lazy Eager Loading
$user = User::find(1);
$user->load('posts');

// Aggregation
$count = User::where('is_active', true)->count();
$avgAge = User::avg('age');
```

### Query Builder

Eloquent-den ferqli olaraq, Query Builder birbaşa cedvelle isleyir (model olmadan):

```php
use Illuminate\Support\Facades\DB;

// Sade sorgu
$users = DB::table('users')
    ->where('is_active', true)
    ->orderBy('name')
    ->get();

// Join
$posts = DB::table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as author_name')
    ->get();

// Subquery
$latestPosts = DB::table('posts')
    ->whereIn('id', function ($query) {
        $query->select(DB::raw('MAX(id)'))
            ->from('posts')
            ->groupBy('user_id');
    })
    ->get();

// Insert, Update, Delete
DB::table('users')->insert([
    'name' => 'Orxan',
    'email' => 'orxan@test.com',
    'created_at' => now(),
]);

DB::table('users')->where('id', 1)->update(['name' => 'Yeni Ad']);

DB::table('users')->where('id', 1)->delete();
```

### Elaqe novleri

```php
// One to One
class User extends Model
{
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
}

// One to Many
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

// Many to Many
class Post extends Model
{
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withPivot('order')       // Pivot cedvelinden elave sutunlar
            ->withTimestamps();        // Pivot cedvelinde timestamps
    }
}

// Has Many Through
class Country extends Model
{
    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(Post::class, User::class);
    }
}

// Polymorphic Relations
class Comment extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

class Post extends Model
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
```

## Esas ferqler

| Xususiyyet | Spring Data JPA / Hibernate | Laravel Eloquent |
|---|---|---|
| **Pattern** | Data Mapper pattern | Active Record pattern |
| **Konfiqurasiya** | Annotasiya ile (`@Entity`, `@Column`) | Convention over Configuration |
| **Elaqeler** | `@OneToMany`, `@ManyToOne` annotasiyalari | `hasMany()`, `belongsTo()` metodlari |
| **Sorgu dili** | JPQL, Criteria API, Native SQL | Eloquent query builder, raw SQL |
| **Repository** | Interface yaradirsan, Spring implementasiya edir | Model ozunde CRUD metodlari daxildir |
| **Lazy Loading** | Default olaraq `@ManyToOne`-da EAGER, `@OneToMany`-da LAZY | Default olaraq lazy, `with()` ile eager |
| **Pagination** | `Pageable` parametri ile | `paginate()` metodu ile |
| **Tip tehlukesizliyi** | Guclu tip sistemi, compile-time yoxlama | Runtime yoxlama, daha az tip tehlukesizliyi |
| **Accessor/Mutator** | Getter/Setter metodlari (manual) | `getXAttribute`, `setXAttribute` (convention) |
| **Scope** | Repository metodlari ve ya Specification pattern | `scopeX()` metodlari |
| **Mass Assignment** | Yoxdur (setter ile tek-tek) | `$fillable` / `$guarded` |
| **Soft Delete** | Manual implementasiya lazimdir | `SoftDeletes` trait istifade edilir |

## Niye bele ferqler var?

### Data Mapper vs Active Record

Bu esas felsefe ferqidir. Spring/Hibernate **Data Mapper** pattern istifade edir - entity sinifi verilenbazasindan xeberdar deyil, repository ayrica bir layerdir. Bu separation of concerns prinsipine uygundir ve boyuk enterprise proyektlerde test yazmagi asanlasdirir.

Laravel Eloquent ise **Active Record** pattern istifade edir - model ozunde verilenbazasi emeliyyatlarini bilir (`User::find()`, `$user->save()`). Bu daha intuitiv ve suretle inkisaf etdirmeye imkan verir, amma model sinifi hem domain logic, hem de database logic dasiyir.

### Convention vs Configuration

Laravel "convention over configuration" felsefesine sadiqdir. Model adi `User`-dursa, cedvel `users` olur, primary key `id` olur, foreign key `user_id` olur. Hec bir konfiqurasiya yazmadan isleyir.

Spring/JPA-da ise her seyi aciq sekilde annotasiyalarla bildirmek lazimdir. Bu daha cox kod demel olsa da, davranisi tam nezaret altinda saxlayir ve surpriz olmur.

### Java vs PHP dil ferqleri

Java-nin guclu tip sistemi Hibernate-in compile-time-da sehvleri tutmasina imkan verir. PHP-nin dinamik tipleri Eloquent-e daha az kod ile daha cox is gormeye imkan verir, amma runtime sehvleri daha cox olur.

## Hansi framework-de var, hansinda yoxdur?

### Yalniz Laravel-de (ve ya daha asandir):
- **Soft Deletes** - `SoftDeletes` trait ile hazir gelir. Spring-de manual implementasiya lazimdir
- **Accessor/Mutator** - Convention ile asanliqla yaradilir
- **Scopes** - Tekrar istifade olunan sorgu filtrlerini sadece metod kimi yazmaq
- **Mass Assignment qorumasi** - `$fillable` / `$guarded` ile hazir mexanizm
- **Polymorphic Relations** - `MorphTo`, `MorphMany` kimi hazir elaqe novleri
- **`$casts`** - Atributlari avtomatik tipe cevirmek

### Yalniz Spring-de (ve ya daha asandir):
- **Criteria API** - Tip-tehlukesiz dinamik sorgu qurucusu
- **JPQL** - SQL-e benzer, amma entity uzerinde isleyen sorgu dili
- **Specification pattern** - Murakkeb filtrler ucun tekrar istifade olunan predicate-ler
- **Second Level Cache** - Hibernate-in daxili keshleme mexanizmi
- **Repository metodlarindan avtomatik sorgu generasiyasi** - Metod adi `findByEmailAndNameContaining` yazmaq kifayetdir
- **`@Transactional` annotasiyasi** - Tranzaksiya idareetmesi ucun deklarativ yaklasim (Laravel-de de `DB::transaction()` var, amma annotasiya yoxdur)
- **Entity lifecycle callback-leri** - `@PrePersist`, `@PostLoad` kimi eventler
