# 35 — Spring Data Relationships (Əlaqələr)

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [@OneToOne](#onetoone)
2. [@OneToMany və @ManyToOne](#onetomany-və-manytoone)
3. [@ManyToMany](#manytomany)
4. [Cascade Types](#cascade-types)
5. [orphanRemoval](#orphanremoval)
6. [mappedBy Konvensiyası](#mappedby-konvensiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @OneToOne

Bir entity ilə digər entity arasında bire-bir əlaqə.

### Unidirectional (birtərəfli):

```java
@Entity
@Table(name = "users")
public class User {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String username;

    // Foreign key users cədvəlindədir (profile_id sütunu)
    @OneToOne(
        cascade = CascadeType.ALL, // User silinəndə Profile də silinsin
        fetch = FetchType.LAZY,    // Lazım olanda yüklənsin (tövsiyə)
        optional = false           // User-in mütləq Profile-i olmalıdır
    )
    @JoinColumn(
        name = "profile_id",       // Foreign key sütun adı
        unique = true              // OneToOne üçün unique
    )
    private UserProfile profile;
}

@Entity
@Table(name = "user_profiles")
public class UserProfile {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String bio;
    private String avatarUrl;
    // User-ə istinad yoxdur (unidirectional)
}
```

### Bidirectional (ikiistiqamətli):

```java
@Entity
@Table(name = "users")
public class User {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String username;
    private String email;

    // Owning side - foreign key buradadır
    @OneToOne(
        cascade = CascadeType.ALL,
        fetch = FetchType.LAZY,
        optional = true // Profile olmaya bilər
    )
    @JoinColumn(name = "profile_id")
    private UserProfile profile;

    // Helper metodları - bidirectional consistency üçün
    public void setProfile(UserProfile profile) {
        this.profile = profile;
        if (profile != null && profile.getUser() != this) {
            profile.setUser(this);
        }
    }
}

@Entity
@Table(name = "user_profiles")
public class UserProfile {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String bio;

    // Non-owning side - mappedBy istifadə et
    // "profile" - User sinifindəki field adıdır
    @OneToOne(mappedBy = "profile", fetch = FetchType.LAZY)
    private User user;

    public void setUser(User user) {
        this.user = user;
        if (user != null && user.getProfile() != this) {
            user.setProfile(this);
        }
    }
}

// İstifadəsi:
@Service
@Transactional
public class UserService {

    public User createUser(String username, String bio) {
        UserProfile profile = new UserProfile();
        profile.setBio(bio);

        User user = new User();
        user.setUsername(username);
        user.setProfile(profile); // Helper metod - iki tərəfi birləşdirir

        return userRepository.save(user); // Cascade ile profile da saxlanır
    }
}
```

### optional=false nümunəsi:

```java
@Entity
public class Employee {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // optional=false - JOIN əvəzinə INNER JOIN generasiya edir
    // DB səviyyəsindəki NOT NULL constraint ilə uyğun
    @OneToOne(optional = false, fetch = FetchType.LAZY)
    @JoinColumn(name = "department_id", nullable = false)
    private Department department;
    // Hər Employee mütləq Department-a məxsus olmalıdır
}
```

---

## @OneToMany və @ManyToOne

Ən çox istifadə olunan əlaqə tipi.

### Owning side (ManyToOne):

```java
// ManyToOne - owning side, foreign key BU cədvəldədir
@Entity
@Table(name = "products")
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;
    private Double price;

    // Foreign key: products.category_id
    @ManyToOne(
        fetch = FetchType.LAZY, // LAZY tövsiyə olunur
        optional = false        // Hər product-ın category-si olmalıdır
    )
    @JoinColumn(
        name = "category_id",   // Sütun adı
        nullable = false,
        foreignKey = @ForeignKey(name = "fk_product_category") // FK adı
    )
    private Category category;
}

// OneToMany - non-owning side
@Entity
@Table(name = "categories")
public class Category {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;

    // mappedBy - Product sinifindəki "category" field adı
    // Bu cədvəldə sütun yaratmır
    @OneToMany(
        mappedBy = "category",
        cascade = CascadeType.ALL,
        orphanRemoval = true,
        fetch = FetchType.LAZY  // DEFAULT LAZY - yaxşıdır
    )
    private List<Product> products = new ArrayList<>(); // null deyil, boş list

    // Bidirectional consistency üçün helper metodlar
    public void addProduct(Product product) {
        products.add(product);
        product.setCategory(this); // Owning side-ı da set et
    }

    public void removeProduct(Product product) {
        products.remove(product);
        product.setCategory(null);
    }
}
```

### Yanlış istifadə nümunəsi:

```java
// YANLIŞ - @JoinColumn olmadan @OneToMany
@Entity
public class BadCategory {
    @Id private Long id;

    @OneToMany // mappedBy yoxdur, @JoinColumn yoxdur
    private List<Product> products; // Join cədvəl yaranır! (category_products)
    // İstəmədən ManyToMany kimi davranır
}

// DOĞRU - ya mappedBy, ya da @JoinColumn
@Entity
public class GoodCategory {
    @Id private Long id;

    // Seçim 1: Bidirectional (tövsiyə)
    @OneToMany(mappedBy = "category")
    private List<Product> products;

    // Seçim 2: Unidirectional @JoinColumn ilə (FK Product cədvəlindədir)
    // @OneToMany
    // @JoinColumn(name = "category_id") // products.category_id
    // private List<Product> products;
}
```

---

## @ManyToMany

```java
// Owning side - @JoinTable buradadır
@Entity
@Table(name = "students")
public class Student {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;

    @ManyToMany(
        cascade = {CascadeType.PERSIST, CascadeType.MERGE},
        // DIQQƏT: CascadeType.ALL istifadə etmə!
        // REMOVE cascade - student silinəndə course da silinər - YANLIŞ!
        fetch = FetchType.LAZY
    )
    @JoinTable(
        name = "student_courses",          // Birləşdirici cədvəl
        joinColumns = @JoinColumn(
            name = "student_id",           // Bu entity-nin FK-i
            referencedColumnName = "id"
        ),
        inverseJoinColumns = @JoinColumn(
            name = "course_id",            // Digər entity-nin FK-i
            referencedColumnName = "id"
        )
    )
    private Set<Course> courses = new HashSet<>(); // Set - dublikat yoxdur

    // Helper metodlar
    public void enrollCourse(Course course) {
        courses.add(course);
        course.getStudents().add(this);
    }

    public void dropCourse(Course course) {
        courses.remove(course);
        course.getStudents().remove(this);
    }
}

// Non-owning side
@Entity
@Table(name = "courses")
public class Course {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String title;

    // mappedBy - Student sinifindəki "courses" field adı
    @ManyToMany(mappedBy = "courses", fetch = FetchType.LAZY)
    private Set<Student> students = new HashSet<>();
}
```

### ManyToMany-ni əl ilə idarə et (Intermediate Entity):

```java
// Əlaqəyə əlavə atribut lazımdırsa - ayrı entity yarat
@Entity
@Table(name = "enrollments")
public class Enrollment {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "student_id")
    private Student student;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "course_id")
    private Course course;

    // Əlaqəyə aid əlavə məlumat
    @Column(name = "enrolled_at")
    private LocalDateTime enrolledAt;

    @Column(name = "grade")
    private String grade;

    @Column(name = "is_completed")
    private boolean completed;
}

// Student sadələşdirilir
@Entity
public class Student {
    @Id private Long id;
    private String name;

    @OneToMany(mappedBy = "student", cascade = CascadeType.ALL, orphanRemoval = true)
    private List<Enrollment> enrollments = new ArrayList<>();
}

@Entity
public class Course {
    @Id private Long id;
    private String title;

    @OneToMany(mappedBy = "course")
    private List<Enrollment> enrollments = new ArrayList<>();
}
```

---

## Cascade Types

Cascade — ana entity üzərindəki əməliyyatın bağlı entity-lərə yayılması.

```java
@Entity
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // CascadeType.PERSIST - Order saxlananda OrderItem-lar da saxlanır
    @OneToMany(mappedBy = "order", cascade = CascadeType.PERSIST)
    private List<OrderItem> items;
}

// Cascade növləri:
```

| Cascade Tipi | Effekt |
|-------------|--------|
| `PERSIST` | `save()` çağırılanda bağlı entity-lər də saxlanır |
| `MERGE` | `merge()` çağırılanda bağlı entity-lər də yenilənir |
| `REMOVE` | `delete()` çağırılanda bağlı entity-lər də silinir |
| `REFRESH` | `refresh()` çağırılanda bağlı entity-lər də yenilənir |
| `DETACH` | `detach()` çağırılanda bağlı entity-lər də ayrılır |
| `ALL` | Hamısı |

```java
// Nümunələr:

// 1. CascadeType.PERSIST
@Entity
public class Author {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;

    @OneToMany(mappedBy = "author", cascade = CascadeType.PERSIST)
    private List<Book> books = new ArrayList<>();
}

// Author saxlananda books də saxlanır
Author author = new Author();
author.setName("Nizami");
Book book1 = new Book("Leyli və Məcnun");
book1.setAuthor(author);
author.getBooks().add(book1);
authorRepository.save(author); // Book da saxlanır - PERSIST cascade

// 2. CascadeType.REMOVE - diqqətli ol!
@Entity
public class BlogPost {

    @Id private Long id;
    private String title;

    // Post silinəndə bütün comments da silinir
    @OneToMany(mappedBy = "post", cascade = {CascadeType.PERSIST, CascadeType.REMOVE})
    private List<Comment> comments;
}

// 3. ManyToMany-də CascadeType.REMOVE YANLIŞ
@Entity
public class Student {

    @ManyToMany(cascade = CascadeType.ALL) // YANLIŞ! REMOVE var
    private Set<Course> courses;
    // Student silinəndə Course da silinər - digər studentlər itirər!
}

// DOĞRU - ManyToMany-də yalnız PERSIST və MERGE
@Entity
public class Student {

    @ManyToMany(cascade = {CascadeType.PERSIST, CascadeType.MERGE})
    private Set<Course> courses;
}
```

---

## orphanRemoval

`orphanRemoval=true` — parent-dən ayrılan (collection-dan çıxarılan) entity-lər avtomatik silinir.

```java
@Entity
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @OneToMany(
        mappedBy = "order",
        cascade = CascadeType.ALL,
        orphanRemoval = true // Parent-siz qalan silinsin
    )
    private List<OrderItem> items = new ArrayList<>();

    public void removeItem(OrderItem item) {
        items.remove(item);
        item.setOrder(null);
        // orphanRemoval=true olduğuna görə item DB-dən silinir
    }
}

// orphanRemoval vs CascadeType.REMOVE fərqi:
/*
 CascadeType.REMOVE:
   - Yalnız Order.delete() çağırılanda OrderItem-lar silinir
   - items.remove(item) çağırılanda silinmir

 orphanRemoval=true:
   - items.remove(item) çağırılanda item silinir (parent-siz qaldı)
   - Order.delete() çağırılanda da silinir (CascadeType.REMOVE kimi)
*/

@Service
@Transactional
public class OrderService {

    public void removeFirstItem(Long orderId) {
        Order order = orderRepository.findById(orderId).orElseThrow();

        // orphanRemoval=true - bu item DB-dən silinir
        OrderItem removed = order.getItems().remove(0);
        removed.setOrder(null);
        // save() çağırmaya gərək yoxdur - dirty checking işləyir
    }
}
```

---

## mappedBy Konvensiyası

`mappedBy` — bidirectional əlaqədə hangi entity-nin owner olduğunu göstərir.

```java
/*
 QAYDALAR:
 1. mappedBy yalnız non-owning side-da istifadə olunur
 2. mappedBy dəyəri - owning side-dakı field adıdır (sinif adı deyil!)
 3. mappedBy olan tərəfdə @JoinColumn yazılmır
 4. Foreign key cədvəli - owning side-ın cədvəlidir
*/

@Entity
public class Department {
    @Id private Long id;
    private String name;

    // mappedBy = "department" - Employee.department field adı
    @OneToMany(mappedBy = "department")
    private List<Employee> employees;
}

@Entity
public class Employee {
    @Id private Long id;
    private String name;

    // Owning side - foreign key employees cədvəlindədir (department_id)
    @ManyToOne
    @JoinColumn(name = "department_id")
    private Department department; // Bu field adı = mappedBy dəyəri
}

// YANLIŞ - hər iki tərəfdə @JoinColumn
@Entity
public class BadDepartment {
    @Id private Long id;

    @OneToMany
    @JoinColumn(name = "department_id") // YANLIŞ - mappedBy olmalı idi
    private List<Employee> employees;
}

// YANLIŞ - @OneToMany tərəfdə @JoinColumn, amma mappedBy yoxdur
// Bu unidirectional one-to-many yaradır (ayrı join table olmadan)
// Məqsəd bidirectional isə mappedBy istifadə etmək lazımdır
```

### Tam bidirectional nümunəsi - doğru yol:

```java
@Entity
@Table(name = "orders")
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String status;

    // Non-owning side
    @OneToMany(mappedBy = "order", cascade = CascadeType.ALL, orphanRemoval = true)
    private List<OrderItem> items = new ArrayList<>();

    // Bidirectional consistency helper
    public void addItem(OrderItem item) {
        items.add(item);
        item.setOrder(this); // Owning side-ı da set et
    }

    public void removeItem(OrderItem item) {
        items.remove(item);
        item.setOrder(null);
    }
}

@Entity
@Table(name = "order_items")
public class OrderItem {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String productName;
    private Integer quantity;
    private Double price;

    // Owning side - foreign key order_items.order_id
    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "order_id", nullable = false)
    private Order order;
}

// İstifadəsi:
@Service
@Transactional
public class OrderService {

    public Order createOrder(List<OrderItemDto> itemDtos) {
        Order order = new Order();
        order.setStatus("PENDING");

        // Helper metod ilə əlavə et - iki tərəfi sinxron saxlayır
        for (OrderItemDto dto : itemDtos) {
            OrderItem item = new OrderItem();
            item.setProductName(dto.getProductName());
            item.setQuantity(dto.getQuantity());
            item.setPrice(dto.getPrice());
            order.addItem(item); // iki tərəfi set edir
        }

        return orderRepository.save(order); // Cascade ile items da saxlanır
    }
}
```

---

## İntervyu Sualları

**S: @OneToMany əlaqəsinin owning side-ı hansıdır?**

C: `@ManyToOne` tərəfi owning side-dır — foreign key onun cədvəlindədir. `@OneToMany` tərəfi non-owning-dir və `mappedBy` atributunu istifadə edir. Hibernate yalnız owning side-a baxaraq JOIN əlaqəsini idarə edir.

**S: CascadeType.ALL vs PERSIST+MERGE fərqi nədir, hansını seçməli?**

C: `ALL` = PERSIST + MERGE + REMOVE + REFRESH + DETACH. `@OneToMany` üçün `ALL` istifadə etmək uyğundur (parent silinəndə children da silinsin). Amma `@ManyToMany` üçün REMOVE cascade YANLIŞ-dır — bir entity silinəndə shared entity-lər digər tərəfdən də silinir. `@ManyToMany` üçün yalnız `{PERSIST, MERGE}`.

**S: orphanRemoval vs CascadeType.REMOVE fərqi nədir?**

C: `CascadeType.REMOVE` — parent entity silinəndə children da silinir. `orphanRemoval=true` — parent collection-undan çıxarılan entity DB-dən silinir (parent-siz qalır). `orphanRemoval=true` həm də CascadeType.REMOVE-un funksionallığını əhatə edir.

**S: Bidirectional əlaqədə niyə helper metodlar lazımdır?**

C: Hibernate yalnız owning side-ı izləyir. Əgər yalnız non-owning side-ı set etsən, Hibernate dəyişikliyi görməz, DB-yə yazılmaz. Helper metodlar hər iki tərəfi sinxron saxlayır — in-memory consistency üçün vacibdir.

**S: @ManyToMany üçün niyə intermediate entity daha yaxşıdır?**

C: `@ManyToMany` əlaqəyə atribut əlavə etmək olmur (məs: enrollment tarixi, qiymət). Intermediate entity (Enrollment) bu problemini həll edir, həmçinin join cədvəlinə daha çox kontrol verir, sorğuları asanlaşdırır.

**S: @OneToMany-ni List ilə mi, Set ilə mi elan etməli?**

C: `Set` — dublikatları rədd edir, `@ManyToMany` üçün tövsiyə olunur. `List` — sıra mühümdürsə, `@OrderColumn` ilə istifadə edilir. `@ManyToMany`-də `List` istifadəsi Hibernate-in collection-u sil+yenidən-əlavə etməsinə səbəb ola bilər — `Set` daha effektivdir.
