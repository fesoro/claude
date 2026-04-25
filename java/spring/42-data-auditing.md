# 42 — Spring Data Auditing — Geniş İzah

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [Auditing nədir?](#auditing-nədir)
2. [@CreatedDate və @LastModifiedDate](#createddate-və-lastmodifieddate)
3. [@CreatedBy və @LastModifiedBy](#createdby-və-lastmodifiedby)
4. [AuditingEntityListener](#auditingentitylistener)
5. [Auditable base sinif](#auditable-base-sinif)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Auditing nədir?

**Auditing** — entity-lərin nə zaman, kim tərəfindən yaradıldığını/dəyişdirildiyini avtomatik izləmək mexanizmidir. Spring Data JPA bunu minimal konfiqurasiya ilə həyata keçirir.

```java
// Aktivləşdirmək
@SpringBootApplication
@EnableJpaAuditing
public class App {
    public static void main(String[] args) {
        SpringApplication.run(App.class, args);
    }
}
```

---

## @CreatedDate və @LastModifiedDate

```java
@Entity
@EntityListeners(AuditingEntityListener.class) // ← Mütləq əlavə et
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;
    private BigDecimal price;

    @CreatedDate
    @Column(updatable = false) // Yalnız bir dəfə set edilir
    private LocalDateTime createdAt;

    @LastModifiedDate
    private LocalDateTime updatedAt;
}
```

**DB schema:**
```sql
CREATE TABLE product (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    price DECIMAL(10,2),
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP
);
```

---

## @CreatedBy və @LastModifiedBy

Kim tərəfindən yaradıldığını/dəyişdirildiyini izləmək üçün `AuditorAware` bean lazımdır:

```java
// AuditorAware — cari istifadəçini qaytarır
@Configuration
@EnableJpaAuditing(auditorAwareRef = "auditorProvider")
public class AuditConfig {

    @Bean
    public AuditorAware<String> auditorProvider() {
        return () -> {
            // SecurityContext-dən cari istifadəçini al
            Authentication auth = SecurityContextHolder
                .getContext()
                .getAuthentication();

            if (auth == null || !auth.isAuthenticated()
                    || auth instanceof AnonymousAuthenticationToken) {
                return Optional.of("system");
            }

            return Optional.of(auth.getName());
        };
    }
}

// Entity-də
@Entity
@EntityListeners(AuditingEntityListener.class)
public class Order {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @CreatedDate
    @Column(updatable = false)
    private LocalDateTime createdAt;

    @LastModifiedDate
    private LocalDateTime updatedAt;

    @CreatedBy
    @Column(updatable = false)
    private String createdBy; // Username

    @LastModifiedBy
    private String updatedBy; // Username
}
```

**Daha güclü AuditorAware — User entity ilə:**
```java
@Configuration
@EnableJpaAuditing(auditorAwareRef = "userAuditorProvider")
public class AuditConfig {

    @Bean
    public AuditorAware<User> userAuditorProvider(UserRepository userRepository) {
        return () -> {
            Authentication auth = SecurityContextHolder
                .getContext()
                .getAuthentication();

            if (auth == null || !auth.isAuthenticated()) {
                return Optional.empty();
            }

            String username = auth.getName();
            return userRepository.findByUsername(username);
        };
    }
}

// Entity-də
@Entity
@EntityListeners(AuditingEntityListener.class)
public class Post {

    @CreatedBy
    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(updatable = false)
    private User createdBy;

    @LastModifiedBy
    @ManyToOne(fetch = FetchType.LAZY)
    private User updatedBy;
}
```

---

## AuditingEntityListener

```java
// Custom entity listener — daha çox nəzarət üçün
@Component
public class CustomAuditingListener {

    @PrePersist
    public void prePersist(Object entity) {
        if (entity instanceof Auditable auditable) {
            auditable.setCreatedAt(LocalDateTime.now());
            auditable.setCreatedBy(getCurrentUser());
        }
    }

    @PreUpdate
    public void preUpdate(Object entity) {
        if (entity instanceof Auditable auditable) {
            auditable.setUpdatedAt(LocalDateTime.now());
            auditable.setUpdatedBy(getCurrentUser());
        }
    }

    private String getCurrentUser() {
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        return auth != null ? auth.getName() : "system";
    }
}
```

---

## Auditable base sinif

Bütün entity-lər üçün ümumi audit field-lərini paylaşmaq:

```java
// MappedSuperclass — cədvəl yaratmır, field-ləri paylaşır
@MappedSuperclass
@EntityListeners(AuditingEntityListener.class)
public abstract class BaseAuditableEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @CreatedDate
    @Column(nullable = false, updatable = false)
    private LocalDateTime createdAt;

    @LastModifiedDate
    private LocalDateTime updatedAt;

    @CreatedBy
    @Column(updatable = false)
    private String createdBy;

    @LastModifiedBy
    private String updatedBy;

    @Version
    private Long version; // Optimistic locking
}

// İstifadəsi — hər entity extend edir
@Entity
@Table(name = "products")
public class Product extends BaseAuditableEntity {

    private String name;
    private BigDecimal price;

    @ManyToOne
    private Category category;
}

@Entity
@Table(name = "orders")
public class Order extends BaseAuditableEntity {

    @Enumerated(EnumType.STRING)
    private OrderStatus status;

    private BigDecimal totalAmount;
}
```

**Soft Delete ilə birləşdirmək:**
```java
@MappedSuperclass
@EntityListeners(AuditingEntityListener.class)
public abstract class BaseEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @CreatedDate
    @Column(updatable = false)
    private LocalDateTime createdAt;

    @LastModifiedDate
    private LocalDateTime updatedAt;

    @CreatedBy
    @Column(updatable = false)
    private String createdBy;

    @LastModifiedBy
    private String updatedBy;

    // Soft delete
    @Column(nullable = false)
    private boolean deleted = false;

    private LocalDateTime deletedAt;
    private String deletedBy;

    public void softDelete(String deletedBy) {
        this.deleted = true;
        this.deletedAt = LocalDateTime.now();
        this.deletedBy = deletedBy;
    }
}

// Repository-də soft delete
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {

    // Silinməyənlər
    @Query("SELECT p FROM Product p WHERE p.deleted = false")
    List<Product> findAllActive();

    // @Where annotasiyası ilə avtomatik filter
}

// Hibernate @Where ilə avtomatik filter
@Entity
@Where(clause = "deleted = false") // Bütün sorğulara əlavə olunur
public class Product extends BaseEntity {
    // ...
}
```

---

## İntervyu Sualları

### 1. Spring Data Auditing üçün nə lazımdır?
**Cavab:** (1) `@EnableJpaAuditing` — konfiqurasiyaya əlavə edilir. (2) Entity-ə `@EntityListeners(AuditingEntityListener.class)`. (3) Field-lərə `@CreatedDate`, `@LastModifiedDate`, `@CreatedBy`, `@LastModifiedBy`. `@CreatedBy`/`@LastModifiedBy` üçün `AuditorAware` bean tələb olunur.

### 2. @MappedSuperclass nədir?
**Cavab:** Öz cədvəli olmayan, field-lərini child entity-lərə ötürən abstract sinif. `@Inheritance`-dan fərqli olaraq, hər child öz ayrı cədvəlini yaradır, superclass-ın field-ləri isə həmin cədvələ əlavə olunur. Audit field-lərini bütün entity-lər arasında paylaşmaq üçün idealdır.

### 3. AuditorAware nə üçündür?
**Cavab:** `@CreatedBy`/`@LastModifiedBy` üçün cari istifadəçini qaytaran interfeys. Spring Security istifadə edildikdə `SecurityContextHolder`-dan authentication məlumatı əldə edilir. `AuditorAware<String>` yaxud `AuditorAware<User>` ola bilər.

### 4. @Column(updatable = false) nə üçün istifadə edilir?
**Cavab:** `createdAt` və `createdBy` field-ləri yalnız bir dəfə — ilk persist zamanı — set edilməlidir. `updatable = false` olmadan entity update edildikdə bu dəyərlər sıfırlanabilər.

### 5. Soft delete nədir?
**Cavab:** Record-u DB-dən silmək əvəzinə, `deleted = true` flag-i set etmək. Bu sayədə data tarixçəsi saxlanılır. Hibernate `@Where(clause = "deleted = false")` ilə bütün sorğulara avtomatik filter əlavə edilə bilər.

*Son yenilənmə: 2026-04-10*
