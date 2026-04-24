# 032 — Spring Data Entity Annotasiyaları
**Səviyyə:** Orta


## Mündəricat
1. [@Entity və @Table](#entity-və-table)
2. [@Id və @GeneratedValue](#id-və-generatedvalue)
3. [GenerationType Strategiyaları](#generationtype-strategiyaları)
4. [@Column Annotasiyası](#column-annotasiyası)
5. [@Transient](#transient)
6. [@Embedded və @Embeddable](#embedded-və-embeddable)
7. [equals() və hashCode()](#equals-və-hashcode)
8. [İntervyu Sualları](#intervyu-sualları)

---

## @Entity və @Table

`@Entity` annotasiyası bir Java sinifinin JPA entity olduğunu bildirir — yəni verilənlər bazasındakı bir cədvələ uyğun gəlir.

```java
// Ən sadə entity - cədvəl adı sinif adından götürülür (product)
@Entity
public class Product {
    @Id
    private Long id;
}

// @Table ilə tam konfiqurasiya
@Entity
@Table(
    name = "products",           // Cədvəl adı
    schema = "inventory",        // Schema adı (PostgreSQL, Oracle üçün)
    catalog = "mydb",            // Katalog (MySQL üçün)
    uniqueConstraints = {
        // Tək sütun unique constraint
        @UniqueConstraint(
            name = "uk_product_sku",
            columnNames = {"sku"}
        ),
        // Kompozit unique constraint
        @UniqueConstraint(
            name = "uk_product_name_category",
            columnNames = {"name", "category_id"}
        )
    },
    indexes = {
        // Index əlavə et
        @Index(name = "idx_product_price", columnList = "price"),
        @Index(name = "idx_product_name", columnList = "name", unique = false)
    }
)
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String sku;
    private String name;
    private Double price;
    private Long categoryId;
}
```

### Schema ilə istifadə:

```java
// PostgreSQL-də fərqli schema-larda cədvəllər
@Entity
@Table(name = "users", schema = "auth")
public class User {
    @Id
    private Long id;
    // auth.users cədvəlinə uyğun gəlir
}

@Entity
@Table(name = "orders", schema = "sales")
public class Order {
    @Id
    private Long id;
    // sales.orders cədvəlinə uyğun gəlir
}
```

---

## @Id və @GeneratedValue

Hər entity-nin primary key-i olmalıdır. `@Id` annotasiyası field və ya metod üzərində istifadə edilə bilər.

```java
@Entity
public class Product {

    // Field access - @Id field üzərindədir
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // Getter/setter
}

// Property access - @Id getter üzərindədir (tövsiyə edilmir)
@Entity
public class Category {

    private Long id;
    private String name;

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    public Long getId() { return id; }

    // QEYD: Field access daha aydındır, tövsiyə olunur
}
```

### @GeneratedValue olmadan (manual ID):

```java
@Entity
public class Country {

    // Manual ID - özün təyin edirsən
    @Id
    private String code; // "AZ", "TR", "US" kimi

    private String name;

    // save() çağırarkən ID mütləq set edilməlidir
}

// İstifadəsi:
Country country = new Country();
country.setCode("AZ");
country.setName("Azərbaycan");
countryRepository.save(country);
```

---

## GenerationType Strategiyaları

### 1. IDENTITY

```java
@Entity
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    // MySQL AUTO_INCREMENT, PostgreSQL SERIAL istifadə edir
    // DB tərəfindən idarə olunur
    // Batch insert üçün uyğun DEYİL (her insert üçün DB-yə gedib ID alır)
}
```

### 2. SEQUENCE

```java
@Entity
@SequenceGenerator(
    name = "product_seq",        // Generator adı
    sequenceName = "product_id_seq", // DB-dəki sequence adı
    allocationSize = 50          // Hər dəfə neçə ID rezerv edir (performance)
)
public class Product {

    @Id
    @GeneratedValue(
        strategy = GenerationType.SEQUENCE,
        generator = "product_seq"  // Yuxarıdakı generator-u istifadə et
    )
    private Long id;
    // PostgreSQL, Oracle üçün ideal
    // allocationSize=50 - 50 ID-ni bir dəfəyə rezerv edir
    // Batch insert üçün uyğundur
}

// Qlobal sequence generator
@Entity
public class Order {

    @Id
    @GeneratedValue(
        strategy = GenerationType.SEQUENCE,
        generator = "default_seq"
    )
    @SequenceGenerator(
        name = "default_seq",
        sequenceName = "hibernate_sequence",
        allocationSize = 1
    )
    private Long id;
}
```

### 3. TABLE

```java
@Entity
@TableGenerator(
    name = "product_table_gen",
    table = "id_generator",       // ID saxlayan cədvəl
    pkColumnName = "gen_name",    // Entity adı sütunu
    valueColumnName = "gen_val",  // Cari dəyər sütunu
    pkColumnValue = "product_id", // Bu entity-nin adı
    allocationSize = 10
)
public class Product {

    @Id
    @GeneratedValue(
        strategy = GenerationType.TABLE,
        generator = "product_table_gen"
    )
    private Long id;
    // Bütün DB-lərə uyğundur
    // Amma ləngdir - hər ID üçün cədvəl lock lazımdır
    // Əsasən köhnə sistemlərə uyğunluk üçün
}
```

### 4. AUTO

```java
@Entity
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.AUTO)
    // Hibernate DB-ə görə özü seçir:
    // PostgreSQL → SEQUENCE
    // MySQL → TABLE (ama IDENTITY daha yaxşıdır)
    // Oracle → SEQUENCE
    // Tövsiyə: AUTO əvəzinə açıq şəkildə IDENTITY və ya SEQUENCE seç
    private Long id;
}
```

### UUID Primary Key:

```java
@Entity
public class Token {

    @Id
    @GeneratedValue(strategy = GenerationType.UUID) // Spring Boot 3+ / Hibernate 6+
    private UUID id;

    // Köhnə yol (Hibernate 5):
    // @GeneratedValue(generator = "uuid2")
    // @GenericGenerator(name = "uuid2", strategy = "uuid2")
    // private String id;

    private String value;
}

// Composite (birləşik) primary key
@Embeddable
public class OrderItemId implements Serializable {
    private Long orderId;
    private Long productId;
    // equals + hashCode mütləq lazımdır
}

@Entity
public class OrderItem {

    @EmbeddedId
    private OrderItemId id;

    private Integer quantity;
}
```

---

## @Column Annotasiyası

```java
@Entity
@Table(name = "products")
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(
        name = "product_name",  // DB-dəki sütun adı (default: field adı)
        nullable = false,        // NOT NULL constraint
        length = 255,            // VARCHAR uzunluğu (default: 255)
        unique = false           // Unique constraint (default: false)
    )
    private String name;

    @Column(
        name = "description",
        length = 1000,           // Uzun text üçün
        nullable = true          // NULL icazə var
    )
    private String description;

    @Column(
        name = "price",
        nullable = false,
        precision = 10,          // Ümumi rəqəm sayı
        scale = 2                // Ondalıq hissə (10.99 kimi)
    )
    private BigDecimal price;

    @Column(
        name = "sku",
        unique = true,           // Unikal dəyər
        nullable = false,
        length = 50,
        updatable = false        // UPDATE-də dəyişdirilməsin (immutable)
    )
    private String sku;

    @Column(
        name = "created_at",
        insertable = true,       // INSERT-ə daxil et (default: true)
        updatable = false        // UPDATE-ə daxil etmə (immutable timestamp)
    )
    private LocalDateTime createdAt;

    @Column(
        name = "is_active",
        nullable = false,
        columnDefinition = "BOOLEAN DEFAULT TRUE" // Birbaşa SQL definition
    )
    private boolean active;

    // LOB tipi - böyük mətn/binary üçün
    @Column(name = "image_data")
    @Lob
    private byte[] imageData;

    @Column(name = "notes")
    @Lob
    private String notes; // TEXT/CLOB tipi üçün
}
```

### @Column updatable=false nümunəsi:

```java
@Entity
public class AuditableEntity {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, updatable = false)
    private LocalDateTime createdAt; // Heç vaxt dəyişməməli

    @Column(nullable = false)
    private LocalDateTime updatedAt; // Hər dəfə update olunur

    @PrePersist
    protected void onCreate() {
        // Entity ilk dəfə saxlananda
        createdAt = LocalDateTime.now();
        updatedAt = LocalDateTime.now();
    }

    @PreUpdate
    protected void onUpdate() {
        // Entity update olunanda
        updatedAt = LocalDateTime.now();
    }
}
```

---

## @Transient

`@Transient` annotasiyası field-in DB-yə saxlanmamasını bildirir.

```java
@Entity
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private Double price;

    @Column(nullable = false)
    private Double taxRate;

    // DB-yə saxlanmır - hesablanmış dəyər
    @Transient
    private Double totalPrice; // price * (1 + taxRate)

    // DB-yə saxlanmır - runtime-da istifadə üçün
    @Transient
    private boolean loaded = false;

    // Java transient keyword-u da işləyir, amma @Transient daha aydındır
    private transient String temporaryPassword;

    // Hesablanmış dəyəri qaytarır
    public Double getTotalPrice() {
        return price * (1 + taxRate);
    }
}

// YANLIŞ - @Transient olmadan lazımsız sütun yaranır
@Entity
public class BadProduct {
    @Id private Long id;
    private Double price;
    private Double taxRate;
    private Double totalPrice; // DB-də sütun yaranır - YANLIŞ!
}

// DOĞRU
@Entity
public class GoodProduct {
    @Id private Long id;
    private Double price;
    private Double taxRate;

    @Transient
    private Double totalPrice; // DB-yə saxlanmır - DOĞRU
}
```

---

## @Embedded və @Embeddable

`@Embeddable` — ayrı cədvəl olmadan başqa entity-nin içinə embed olunan value object.

```java
// @Embeddable - öz cədvəli yoxdur
@Embeddable
public class Address {

    @Column(name = "street", length = 200)
    private String street;

    @Column(name = "city", length = 100)
    private String city;

    @Column(name = "zip_code", length = 10)
    private String zipCode;

    @Column(name = "country", length = 50)
    private String country;

    // equals + hashCode
    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof Address)) return false;
        Address address = (Address) o;
        return Objects.equals(street, address.street) &&
               Objects.equals(city, address.city) &&
               Objects.equals(zipCode, address.zipCode);
    }

    @Override
    public int hashCode() {
        return Objects.hash(street, city, zipCode);
    }
}

// @Embedded - Address-i entity içinə yerləşdir
@Entity
@Table(name = "customers")
public class Customer {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;

    // Address field-ləri customers cədvəlinə əlavə edilir
    @Embedded
    private Address homeAddress;

    // Eyni @Embeddable-ı iki dəfə istifadə et - sütun adlarını dəyiş
    @Embedded
    @AttributeOverrides({
        @AttributeOverride(name = "street", column = @Column(name = "work_street")),
        @AttributeOverride(name = "city", column = @Column(name = "work_city")),
        @AttributeOverride(name = "zipCode", column = @Column(name = "work_zip")),
        @AttributeOverride(name = "country", column = @Column(name = "work_country"))
    })
    private Address workAddress;
}

// DB cədvəli belə görünür:
// customers (id, name, street, city, zip_code, country, work_street, work_city, work_zip, work_country)
```

### Nested @Embeddable:

```java
// İç-içə Embeddable
@Embeddable
public class GeoLocation {

    @Column(name = "latitude")
    private Double latitude;

    @Column(name = "longitude")
    private Double longitude;
}

@Embeddable
public class Address {

    private String street;
    private String city;

    // Address içinə GeoLocation embed et
    @Embedded
    private GeoLocation location;
}

@Entity
public class Store {

    @Id
    private Long id;

    private String name;

    // store cədvəlində: id, name, street, city, latitude, longitude
    @Embedded
    private Address address;
}
```

---

## equals() və hashCode()

Entity-lər üçün `equals()` və `hashCode()` düzgün implement edilməsə ciddi problemlər yaranır.

### Niyə vacibdir?

```java
// PROBLEM - default equals/hashCode (Object-dən)
@Entity
public class BadProduct {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    private String name;
    // equals/hashCode yoxdur
}

// Problem nümunəsi:
Product p1 = new Product(); // id=null hələ
p1.setName("Laptop");
repository.save(p1);        // id=1 oldu

Product p2 = repository.findById(1L).get();

// Object.equals() referans müqayisəsi edir
System.out.println(p1.equals(p2)); // FALSE! - eyni entity amma false
Set<Product> set = new HashSet<>();
set.add(p1);
// save()-dən sonra hash dəyişdi - Set pozuldu!
```

### DOĞRU yollar:

#### Yol 1: Business key (tövsiyə olunur)

```java
@Entity
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // Natural/business key - null olmur
    @Column(nullable = false, unique = true, updatable = false)
    private String sku; // "LAP-001" kimi

    private String name;

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof Product)) return false;
        Product product = (Product) o;
        // Yalnız business key ilə müqayisə et
        return Objects.equals(sku, product.sku);
    }

    @Override
    public int hashCode() {
        // Business key sabit olduğundan hash dəyişmir
        return Objects.hash(sku);
    }
}
```

#### Yol 2: UUID surrogate key

```java
@Entity
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    // Entity yaranarkən UUID təyin edilir - DB-dən asılı deyil
    @Column(nullable = false, unique = true, updatable = false)
    private UUID uuid = UUID.randomUUID();

    private String name;

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof Product)) return false;
        Product product = (Product) o;
        return Objects.equals(uuid, product.uuid);
    }

    @Override
    public int hashCode() {
        return Objects.hash(uuid);
    }
}
```

#### Yol 3: ID-yə əsaslanan (ehtiyatlı olun)

```java
@Entity
public class Product {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String name;

    @Override
    public boolean equals(Object o) {
        if (this == o) return true;
        if (!(o instanceof Product)) return false;
        Product product = (Product) o;
        // id null ola bilər (persist olmamış entity)
        return id != null && Objects.equals(id, product.id);
    }

    @Override
    public int hashCode() {
        // Sabit hashCode - id null olsa belə Set düzgün işləyir
        // Performans az olur amma düzgündür
        return getClass().hashCode();
    }
}
```

### Lombok ilə ehtiyatlı ol:

```java
// YANLIŞ - Lombok @Data istifadəsi
@Entity
@Data // equals/hashCode bütün field-ləri istifadə edir - PROBLEM!
public class BadProduct {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    private String name;
    // Lombok id daxil hamısını equals-a daxil edir
    // id null ikən hash dəyişir - Set/Map-da problem!
}

// DOĞRU - @EqualsAndHashCode-u konfiqurasiya et
@Entity
@Getter
@Setter
@EqualsAndHashCode(of = "sku") // Yalnız sku istifadə et
public class GoodProduct {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, unique = true)
    private String sku;

    private String name;
}
```

---

## İntervyu Sualları

**S: @Entity annotasiyasının mütləq şərtləri nədir?**

C: 1) `@Entity` annotasiyası olmalıdır, 2) `@Id` annotasiyalı primary key olmalıdır, 3) default (no-arg) constructor olmalıdır (public və ya protected), 4) sinif `final` olmamalıdır, 5) metodlar `final` olmamalıdır.

**S: GenerationType.IDENTITY vs SEQUENCE fərqi nədir?**

C: `IDENTITY` — DB-nin auto-increment xüsusiyyətini istifadə edir. Hibernate batch insert edə bilmir çünki hər insert üçün DB-yə gedib ID almalıdır. `SEQUENCE` — DB sequence-indən əvvəlcədən ID-lər rezerv edir (`allocationSize`). Batch insert dəstəkləyir, PostgreSQL/Oracle üçün ideal.

**S: @Transient annotasiyası nədir?**

C: `@Transient` field-in persistence context tərəfindən ignore edilməsini bildirir — DB-yə saxlanmır, DB-dən oxunmur. Hesablanmış dəyərlər, runtime məlumatları, cache üçün istifadə edilir. Java `transient` keyword-u da eyni effekti verir amma annotation daha aydındır.

**S: Entity-lər üçün equals/hashCode necə implement edilməlidir?**

C: Business key (natural key, UUID) istifadə etmək ən yaxşı yoldur. ID-yə əsaslanmaq problemlidir çünki persist olmamış entity-nin ID-si null olur — Set-ə əlavə etdikdən sonra save() etdikdə hash dəyişir. `getClass().hashCode()` sabit hashCode verir amma performance azalır.

**S: @Embedded vs @OneToOne fərqi nədir?**

C: `@Embedded` — ayrı cədvəl yoxdur, field-lər ana cədvəldə saxlanır. Value object konsepti — öz lifecycle-ı yoxdur. `@OneToOne` — ayrı cədvəl, öz primary key-i var, foreign key əlaqəsi. Performance üçün `@Embedded` daha yaxşıdır (JOIN lazım deyil).

**S: @Column(updatable=false) nə vaxt istifadə olunur?**

C: Heç vaxt dəyişməməli sütunlar üçün — yaranma tarixi (`createdAt`), SKU kodu, UUID kimi. Hibernate UPDATE SQL generasiya edərkən bu sütunları daxil etmir. Təsadüfən dəyişdirilməsinin qarşısını alır.
