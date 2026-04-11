# Database Normalization

## Mündəricat
1. [Normalization Nədir](#normalization-nədir)
2. [Functional Dependency](#functional-dependency)
3. [1NF - First Normal Form](#1nf---first-normal-form)
4. [2NF - Second Normal Form](#2nf---second-normal-form)
5. [3NF - Third Normal Form](#3nf---third-normal-form)
6. [BCNF - Boyce-Codd Normal Form](#bcnf---boyce-codd-normal-form)
7. [4NF və 5NF](#4nf-və-5nf)
8. [Denormalization](#denormalization)
9. [Normalization vs Denormalization Trade-offs](#normalization-vs-denormalization-trade-offs)
10. [Laravel Migration-larla Normalization](#laravel-migration-larla-normalization)
11. [Real-world: E-commerce Database Design](#real-world-e-commerce-database-design)
12. [Real-world: Reporting Database (Denormalized)](#real-world-reporting-database-denormalized)
13. [Indexing Strategiyaları](#indexing-strategiyaları)
14. [N+1 Query Problemi](#n1-query-problemi-və-həlli)
15. [Database Relationships Laravel-də](#database-relationships-laravel-də)
16. [Pivot Tables](#pivot-tables)
17. [Polymorphic Relationships](#polymorphic-relationships)
18. [İntervyu Sualları](#intervyu-sualları-və-cavabları)

---

## Normalization Nədir

Database normalization, relational database-in strukturunu optimallaşdırmaq üçün istifadə olunan sistematik yanaşmadır. Məqsəd:

1. **Data redundancy-ni (təkrarlanmanı) azaltmaq** - Eyni data bir yerdə saxlanılır
2. **Data anomaly-lərin qarşısını almaq** - Insert, Update, Delete anomaliyaları
3. **Data integrity-ni təmin etmək** - Data-nın düzgünlüyünü qorumaq
4. **Logical data model yaratmaq** - Təmiz, başa düşülən struktur

### Data Anomaliyaları

**Normalizasiya edilməmiş cədvəl:**

```
orders tablo:
| order_id | customer_name | customer_email     | customer_city | product_name | product_price | quantity |
|----------|---------------|--------------------|---------------|--------------|---------------|----------|
| 1        | Orxan         | orxan@mail.com     | Bakı          | Laptop       | 2000          | 1        |
| 2        | Orxan         | orxan@mail.com     | Bakı          | Mouse        | 50            | 2        |
| 3        | Aygün         | aygun@mail.com     | Gəncə         | Laptop       | 2000          | 1        |
| 4        | Orxan         | orxan_new@mail.com | Bakı          | Keyboard     | 100           | 1        |
```

**Problemlər:**

1. **Update Anomaly:** Orxan-ın emaili dəyişəndə bütün row-larda yeniləmək lazımdır. Row 4-də artıq fərqli email var - hansı doğrudur?
2. **Insert Anomaly:** Yeni müştəri əlavə etmək istəyiriksə, sifarişi olmasa, əlavə edə bilmirik (order_id NULL ola bilməz).
3. **Delete Anomaly:** Aygün-ün yeganə sifarişini silsək, Aygün-ün bütün məlumatını itiririk.

---

## Functional Dependency

Functional Dependency (FD) normalization-ın əsas konseptidir. `A → B` deməkdir ki, A dəyəri B dəyərini müəyyən edir (A bilinsə, B unikaldır).

```
Nümunə: student_id → student_name
  student_id=1 həmişə "Orxan" qaytarır, başqa ad qaytara bilməz.

Tam göstəriş:
  order_id → customer_name, customer_email    (order_id hər şeyi müəyyən edir)
  customer_email → customer_name, customer_city (email müştərini müəyyən edir)
  product_name → product_price                  (məhsul adı qiyməti müəyyən edir)
```

**FD növləri:**
- **Full Functional Dependency:** `{A, B} → C` -- C yalnız A və B birlikdə olduqda müəyyən olunur, yalnız A ilə müəyyən olunmur.
- **Partial Dependency:** `{A, B} → C` amma həm də `A → C` -- C yalnız A ilə müəyyən olunur, B lazım deyil. (2NF pozulur)
- **Transitive Dependency:** `A → B → C` -- A birbaşa C-ni deyil, B vasitəsilə müəyyən edir. (3NF pozulur)

---

## 1NF - First Normal Form

**Qaydalar:**
1. Hər sütundakı dəyər **atomic** (bölünməz) olmalıdır
2. Hər sütunda eyni tipli data olmalıdır
3. Hər row unikal olmalıdır (Primary Key)
4. Sütun sırası əhəmiyyətsizdir

### 1NF-yə Uyğun Olmayan Nümunə

*1NF-yə Uyğun Olmayan Nümunə üçün kod nümunəsi:*
```sql
-- PİS: 1NF pozulur (multi-valued, non-atomic)
CREATE TABLE students (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    phone_numbers VARCHAR(255),   -- "055-111-22-33, 070-444-55-66" (atomic deyil!)
    courses VARCHAR(255)          -- "Math, Physics, Chemistry" (atomic deyil!)
);

INSERT INTO students VALUES 
(1, 'Orxan', '055-111-22-33, 070-444-55-66', 'Math, Physics'),
(2, 'Aygün', '050-222-33-44', 'Chemistry, Physics, Math');
```

### 1NF-yə Keçid

*1NF-yə Keçid üçün kod nümunəsi:*
```sql
-- YAXŞI: 1NF-ə uyğun
CREATE TABLE students (
    id INT PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE student_phones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    phone_number VARCHAR(20),
    phone_type ENUM('mobile', 'home', 'work'),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100)
);

CREATE TABLE student_courses (
    student_id INT,
    course_id INT,
    PRIMARY KEY (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);

INSERT INTO students VALUES (1, 'Orxan'), (2, 'Aygün');
INSERT INTO student_phones VALUES (1, 1, '055-111-22-33', 'mobile'), (2, 1, '070-444-55-66', 'mobile');
INSERT INTO courses VALUES (1, 'Math'), (2, 'Physics'), (3, 'Chemistry');
INSERT INTO student_courses VALUES (1, 1), (1, 2), (2, 1), (2, 2), (2, 3);
```

---

## 2NF - Second Normal Form

**Qaydalar:**
1. 1NF-ə uyğun olmalıdır
2. **Partial dependency olmamalıdır** - Non-key sütunlar composite primary key-in **bütün** hissəsinə bağlı olmalıdır, bir hissəsinə deyil

> Qeyd: Əgər primary key tək sütundan ibarətdirsə, 1NF olan cədvəl avtomatik 2NF-dir.

### 2NF-yə Uyğun Olmayan Nümunə

*2NF-yə Uyğun Olmayan Nümunə üçün kod nümunəsi:*
```sql
-- PİS: 2NF pozulur
-- Composite PK: (student_id, course_id)
CREATE TABLE student_courses (
    student_id INT,
    course_id INT,
    student_name VARCHAR(100),    -- student_id → student_name (Partial dependency!)
    course_name VARCHAR(100),     -- course_id → course_name (Partial dependency!)
    grade CHAR(1),                -- (student_id, course_id) → grade (Full dependency - OK)
    PRIMARY KEY (student_id, course_id)
);
```

**Problem:** `student_name` yalnız `student_id`-dən asılıdır, `course_id`-dən deyil. Bu partial dependency-dir.

```
student_id → student_name           (Partial: yalnız PK-nın bir hissəsi)
course_id → course_name             (Partial: yalnız PK-nın bir hissəsi)
(student_id, course_id) → grade     (Full: bütün PK lazımdır) ✓
```

### 2NF-yə Keçid

*2NF-yə Keçid üçün kod nümunəsi:*
```sql
-- YAXŞI: 2NF-ə uyğun - partial dependency-lər ayrı cədvələ çıxarılıb
CREATE TABLE students (
    id INT PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE courses (
    id INT PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE enrollments (
    student_id INT,
    course_id INT,
    grade CHAR(1),
    PRIMARY KEY (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (course_id) REFERENCES courses(id)
);
```

---

## 3NF - Third Normal Form

**Qaydalar:**
1. 2NF-ə uyğun olmalıdır
2. **Transitive dependency olmamalıdır** - Non-key sütunlar yalnız primary key-dən birbaşa asılı olmalıdır, başqa non-key sütundan deyil

### 3NF-yə Uyğun Olmayan Nümunə

*3NF-yə Uyğun Olmayan Nümunə üçün kod nümunəsi:*
```sql
-- PİS: 3NF pozulur
CREATE TABLE employees (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    department_id INT,
    department_name VARCHAR(100),  -- department_id → department_name (Transitive!)
    department_head VARCHAR(100)   -- department_id → department_head (Transitive!)
);
```

**Transitive dependency zənciri:**
```
id → department_id → department_name    (id birbaşa department_name-i müəyyən etmir)
id → department_id → department_head    (department_id vasitəsilə gedir)
```

**Problem:** Department adını dəyişsək, həmin department-dəki bütün employee-lərdə yeniləmək lazımdır.

### 3NF-yə Keçid

*3NF-yə Keçid üçün kod nümunəsi:*
```sql
-- YAXŞI: 3NF-ə uyğun
CREATE TABLE departments (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    head VARCHAR(100)
);

CREATE TABLE employees (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    department_id INT,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);
```

### Daha Mürəkkəb 3NF Nümunəsi

*Daha Mürəkkəb 3NF Nümunəsi üçün kod nümunəsi:*
```sql
-- PİS: 3NF pozulur
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_id INT,
    customer_name VARCHAR(100),     -- customer_id → customer_name (Transitive)
    customer_email VARCHAR(255),    -- customer_id → customer_email (Transitive)
    product_id INT,
    product_name VARCHAR(100),      -- product_id → product_name (Transitive)
    product_price DECIMAL(10,2),    -- product_id → product_price (Transitive)
    quantity INT,
    total DECIMAL(10,2),            -- product_price * quantity ilə hesablanır (Derived)
    order_date DATE
);

-- YAXŞI: 3NF-ə uyğun
CREATE TABLE customers (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(255)
);

CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(100),
    price DECIMAL(10,2)
);

CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_id INT,
    order_date DATE,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE order_items (
    id INT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT,
    unit_price DECIMAL(10,2),  -- Sifariş anındakı qiyməti saxlayırıq!
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
-- Qeyd: unit_price products.price-dən fərqli ola bilər (qiymət dəyişə bilər).
-- Bu denormalization deyil, business tələbidir - tarixi qiyməti saxlamalıyıq.
```

---

## BCNF - Boyce-Codd Normal Form

**Qaydalar:**
1. 3NF-ə uyğun olmalıdır
2. Hər determinant (functional dependency-nin sol tərəfi) **candidate key** olmalıdır

> BCNF 3NF-in gücləndirilmiş versiyasıdır. Fərq yalnız nadir hallarda ortaya çıxır - adətən birdən çox candidate key olduqda.

### BCNF-yə Uyğun Olmayan Nümunə

*BCNF-yə Uyğun Olmayan Nümunə üçün kod nümunəsi:*
```sql
-- 3NF-ə uyğun, amma BCNF-ə uyğun DEYİL
CREATE TABLE course_teachers (
    student_id INT,
    course VARCHAR(50),
    teacher VARCHAR(50),
    PRIMARY KEY (student_id, course)
);

-- Functional Dependencies:
-- (student_id, course) → teacher   (PK müəyyən edir - OK)
-- teacher → course                  (Hər müəllim yalnız bir kurs tədris edir)
--                                   teacher candidate key DEYİL, amma determinant-dır!
```

```
| student_id | course   | teacher  |
|------------|----------|----------|
| 1          | Math     | Dr. Əli  |
| 2          | Math     | Dr. Əli  |
| 3          | Physics  | Dr. Vəfa |
| 1          | Physics  | Dr. Vəfa |
```

**Problem:** Dr. Əli-nin kursunu dəyişsək, bütün row-larda dəyişmək lazımdır. `teacher → course` dependency-si var, amma `teacher` candidate key deyil.

### BCNF-yə Keçid

*BCNF-yə Keçid üçün kod nümunəsi:*
```sql
-- BCNF-ə uyğun
CREATE TABLE teacher_courses (
    teacher VARCHAR(50) PRIMARY KEY,
    course VARCHAR(50)
);

CREATE TABLE student_teachers (
    student_id INT,
    teacher VARCHAR(50),
    PRIMARY KEY (student_id, teacher),
    FOREIGN KEY (teacher) REFERENCES teacher_courses(teacher)
);

INSERT INTO teacher_courses VALUES ('Dr. Əli', 'Math'), ('Dr. Vəfa', 'Physics');
INSERT INTO student_teachers VALUES (1, 'Dr. Əli'), (2, 'Dr. Əli'), (3, 'Dr. Vəfa'), (1, 'Dr. Vəfa');
```

---

## 4NF və 5NF

### 4NF (Fourth Normal Form)

**Multi-valued dependency olmamalıdır.** Bir key-dən asılı olan iki müstəqil multi-valued sütun varsa, ayrılmalıdır.

***Multi-valued dependency olmamalıdır.** Bir key-dən asılı olan iki mü üçün kod nümunəsi:*
```sql
-- PİS: 4NF pozulur
-- Müəllim həm müxtəlif kurslar, həm müxtəlif dillər tədris edə bilər
-- (bunlar bir-birindən asılı deyil)
CREATE TABLE teacher_skills (
    teacher_id INT,
    course VARCHAR(50),
    language VARCHAR(50)
);

-- Data:
-- (1, 'Math', 'Azerbaijani')
-- (1, 'Math', 'English')
-- (1, 'Physics', 'Azerbaijani')
-- (1, 'Physics', 'English')
-- Cartesian product - çox redundancy!

-- YAXŞI: 4NF-ə uyğun
CREATE TABLE teacher_courses (
    teacher_id INT,
    course VARCHAR(50),
    PRIMARY KEY (teacher_id, course)
);

CREATE TABLE teacher_languages (
    teacher_id INT,
    language VARCHAR(50),
    PRIMARY KEY (teacher_id, language)
);
```

### 5NF (Fifth Normal Form)

**Join dependency olmamalıdır.** Cədvəl heç bir məlumat itirmədən daha kiçik cədvəllərə bölünə bilmirsə, 5NF-dədir. Praktikada çox nadir hallarda lazım olur.

***Join dependency olmamalıdır.** Cədvəl heç bir məlumat itirmədən daha üçün kod nümunəsi:*
```sql
-- 5NF nümunəsi: Supplier-Part-Project
-- Supplier X Part Y təmin edir, Supplier X Project Z-də işləyir, 
-- Project Z Part Y istifadə edir -- Bu üçü arasında müstəqil əlaqə var

-- 5NF: üç ayrı binary relationship cədvəli
CREATE TABLE supplier_parts (supplier_id INT, part_id INT, PRIMARY KEY(supplier_id, part_id));
CREATE TABLE supplier_projects (supplier_id INT, project_id INT, PRIMARY KEY(supplier_id, project_id));
CREATE TABLE project_parts (project_id INT, part_id INT, PRIMARY KEY(project_id, part_id));
```

**Praktik qeyd:** Real layihələrdə adətən 3NF/BCNF kifayət edir. 4NF və 5NF akademik kontekstdə vacibdir, amma praktikada nadir rast gəlinir.

---

## Denormalization

Denormalization, normalizasiya edilmiş database-ə **bilərəkdən redundancy əlavə etmək** prosesidir. Məqsəd: **read performansını artırmaq**, JOIN sayını azaltmaq.

### Nə Vaxt Denormalization Lazımdır?

1. **Çox yavaş JOIN-lar** - Hesabat cədvəlləri, dashboard-lar
2. **Yüksək read/write nisbəti** - 90%+ read olan data
3. **Caching alternativ deyilsə** - Real-time data lazımdırsa
4. **Aggregation tez-tez lazımdırsa** - COUNT, SUM, AVG əvvəlcədən hesablanır
5. **Microservices** - Hər service öz database-inə sahibdir, JOIN mümkün deyil

### Denormalization Texnikaları

*Denormalization Texnikaları üçün kod nümunəsi:*
```sql
-- 1. Redundant Column (Computed/Cached Column)
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_id INT,
    customer_name VARCHAR(100),    -- customers cədvəlindən kopyalanıb
    items_count INT DEFAULT 0,     -- COUNT əvvəlcədən hesablanıb
    total_amount DECIMAL(10,2),    -- SUM əvvəlcədən hesablanıb
    created_at TIMESTAMP
);

-- Trigger ilə sync saxlamaq
-- (PostgreSQL)
CREATE OR REPLACE FUNCTION sync_order_totals()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE orders SET
        items_count = (SELECT COUNT(*) FROM order_items WHERE order_id = NEW.order_id),
        total_amount = (SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = NEW.order_id)
    WHERE id = NEW.order_id;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_order_totals
    AFTER INSERT OR UPDATE OR DELETE ON order_items
    FOR EACH ROW EXECUTE FUNCTION sync_order_totals();

-- 2. Summary Table
CREATE TABLE daily_sales (
    date DATE PRIMARY KEY,
    total_orders INT,
    total_revenue DECIMAL(12,2),
    avg_order_value DECIMAL(10,2),
    new_customers INT
);

-- 3. Materialized View (PostgreSQL)
CREATE MATERIALIZED VIEW product_stats AS
SELECT 
    p.id,
    p.name,
    COUNT(oi.id) AS total_sold,
    SUM(oi.quantity * oi.unit_price) AS total_revenue,
    AVG(oi.unit_price) AS avg_selling_price,
    MAX(o.created_at) AS last_sold_at
FROM products p
LEFT JOIN order_items oi ON oi.product_id = p.id
LEFT JOIN orders o ON o.id = oi.order_id
GROUP BY p.id, p.name;

CREATE UNIQUE INDEX idx_product_stats_id ON product_stats (id);
REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats;
```

---

## Normalization vs Denormalization Trade-offs

```
┌───────────────────────┬──────────────────────────┬───────────────────────────┐
│ Aspekt                │ Normalization            │ Denormalization           │
├───────────────────────┼──────────────────────────┼───────────────────────────┤
│ Data redundancy       │ Minimum                  │ Yüksək                    │
│ Data integrity        │ Güclü                    │ Riskli (sync lazım)       │
│ Read performansı      │ Çox JOIN = yavaş ola bilər│ Sürətli (JOIN az)        │
│ Write performansı     │ Sürətli (bir yerdə yaz)  │ Yavaş (çox yerdə yaz)   │
│ Disk istifadəsi       │ Optimal                  │ Daha çox                  │
│ Query mürəkkəbliyi    │ JOIN-lar mürəkkəb        │ Sadə SELECT-lər          │
│ Schema dəyişikliyi    │ Asan                     │ Çətin (çox yerdə dəyiş)  │
│ Anomaly riski         │ Yox                      │ Bəli                      │
│ Uyğun ssenari         │ OLTP (transactional)     │ OLAP (analytical)        │
│ Nümunə                │ E-commerce order system   │ Reporting dashboard      │
└───────────────────────┴──────────────────────────┴───────────────────────────┘
```

**Qızıl qayda:** Əvvəlcə normalize et, sonra performans problemi yaranarsa, ölçülmüş şəkildə denormalize et.

---

## Laravel Migration-larla Normalization

### Normalized E-commerce Migration-lar

*Normalized E-commerce Migration-lar üçün kod nümunəsi:*
```php
// 1. Customers migration
class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('email');
            $table->index(['last_name', 'first_name']);
        });
    }
}

// 2. Addresses migration (ayrı cədvəl - 1NF tələbi, bir müştərinin çox ünvanı ola bilər)
class CreateAddressesTable extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['billing', 'shipping']);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20);
            $table->string('country', 2); // ISO 3166-1 alpha-2
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            
            $table->index(['customer_id', 'type']);
        });
    }
}

// 3. Categories migration (self-referencing - ağac strukturu)
class CreateCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('parent_id');
        });
    }
}

// 4. Products migration
class CreateProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('brand_id')->nullable()->constrained();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['category_id', 'is_active']);
            $table->index('sku');
        });
    }
}

// 5. Product Variants (rəng, ölçü və s. - 1NF normalized)
class CreateProductVariantsTable extends Migration
{
    public function up(): void
    {
        // Atribut tipləri: rəng, ölçü, material və s.
        Schema::create('attribute_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Color, Size, Material
            $table->string('slug')->unique();
            $table->timestamps();
        });
        
        // Atribut dəyərləri
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_type_id')->constrained()->cascadeOnDelete();
            $table->string('value'); // Red, Blue, XL, Cotton
            $table->timestamps();
            
            $table->unique(['attribute_type_id', 'value']);
        });
        
        // Product variants
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->decimal('price_adjustment', 10, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->timestamps();
        });
        
        // Variant-Attribute əlaqəsi (N:N)
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_variant_id', 'attribute_value_id']);
        });
    }
}

// 6. Orders migration
class CreateOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])
                  ->default('pending');
            
            // Shipping address: sifariş anındakı ünvan snapshot-ı saxlanılır
            // Bu denormalization deyil - business tələbidir
            $table->string('shipping_name');
            $table->string('shipping_line1');
            $table->string('shipping_line2')->nullable();
            $table->string('shipping_city');
            $table->string('shipping_postal_code');
            $table->string('shipping_country', 2);
            
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('order_number');
            $table->index('created_at');
        });
        
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained();
            
            // Sifariş anındakı məlumatlar (tarixi data saxlanılır)
            $table->string('product_name');
            $table->string('product_sku');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total', 10, 2);
            
            $table->timestamps();
            
            $table->index('order_id');
        });
    }
}

// 7. Tags (N:N polymorphic - əlavə çeviklik)
class CreateTagsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        
        // Polymorphic pivot - products, articles, categories və s. üçün
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable'); // taggable_id, taggable_type
            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
        });
    }
}
```

---

## Real-world: E-commerce Database Design

### Normalized Schema (OLTP - Online Transaction Processing)

*Normalized Schema (OLTP - Online Transaction Processing) üçün kod nümunəsi:*
```php
// Laravel Models - Normalized E-commerce

// Customer Model
class Customer extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['first_name', 'last_name', 'email', 'phone'];
    
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
    
    public function defaultShippingAddress(): HasOne
    {
        return $this->hasOne(Address::class)
            ->where('type', 'shipping')
            ->where('is_default', true);
    }
    
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
    
    // Accessor: tam ad
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

// Product Model
class Product extends Model
{
    use SoftDeletes;
    
    protected $fillable = ['name', 'slug', 'sku', 'description', 'category_id', 'brand_id', 'price', 'stock_quantity', 'is_active'];
    
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
    
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
    
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
    
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }
    
    // Scope: aktiv məhsullar
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('stock_quantity', '>', 0);
    }
    
    // Scope: kateqoriyaya görə (nested categories daxil)
    public function scopeInCategory($query, int $categoryId)
    {
        $categoryIds = Category::where('id', $categoryId)
            ->orWhere('parent_id', $categoryId)
            ->pluck('id');
        
        return $query->whereIn('category_id', $categoryIds);
    }
}

// Category Model (self-referencing)
class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id', 'sort_order'];
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    
    // Recursive children (bütün alt kateqoriyalar)
    public function allChildren(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->with('allChildren');
    }
    
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

// Order yaratma servisi (normalized data ilə işləmə)
class OrderService
{
    public function createOrder(Customer $customer, array $items, Address $shippingAddress): Order
    {
        return DB::transaction(function () use ($customer, $items, $shippingAddress) {
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'status' => 'pending',
                'shipping_name' => $customer->full_name,
                'shipping_line1' => $shippingAddress->line1,
                'shipping_line2' => $shippingAddress->line2,
                'shipping_city' => $shippingAddress->city,
                'shipping_postal_code' => $shippingAddress->postal_code,
                'shipping_country' => $shippingAddress->country,
                'subtotal' => 0,
                'total' => 0,
            ]);
            
            $subtotal = 0;
            
            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);
                
                if ($product->stock_quantity < $item['quantity']) {
                    throw new InsufficientStockException($product);
                }
                
                $itemTotal = $product->price * $item['quantity'];
                
                // Order item yaradırıq - sifariş anındakı qiyməti saxlayırıq
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $product->name,      // Snapshot!
                    'product_sku' => $product->sku,         // Snapshot!
                    'unit_price' => $product->price,        // Snapshot!
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal,
                ]);
                
                // Stock azalt
                $product->decrement('stock_quantity', $item['quantity']);
                $subtotal += $itemTotal;
            }
            
            // Order total-ını yenilə
            $tax = $subtotal * 0.18; // 18% ƏDV
            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $subtotal + $tax,
            ]);
            
            return $order->load('items');
        });
    }
    
    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
```

### Mürəkkəb Normalizasiya Sorğuları

*Mürəkkəb Normalizasiya Sorğuları üçün kod nümunəsi:*
```php
// Ən çox satılan məhsullar (normalized data-dan hesablama)
$topProducts = Product::select([
        'products.id',
        'products.name',
        'products.price',
        DB::raw('SUM(order_items.quantity) as total_sold'),
        DB::raw('SUM(order_items.total) as total_revenue'),
    ])
    ->join('order_items', 'order_items.product_id', '=', 'products.id')
    ->join('orders', 'orders.id', '=', 'order_items.order_id')
    ->where('orders.status', '!=', 'cancelled')
    ->groupBy('products.id', 'products.name', 'products.price')
    ->orderByDesc('total_sold')
    ->limit(10)
    ->get();

// Müştəri xülasəsi (çox JOIN)
$customerSummary = Customer::select([
        'customers.id',
        'customers.first_name',
        'customers.last_name',
        DB::raw('COUNT(DISTINCT orders.id) as order_count'),
        DB::raw('COALESCE(SUM(orders.total), 0) as lifetime_value'),
        DB::raw('MAX(orders.created_at) as last_order_date'),
    ])
    ->leftJoin('orders', 'orders.customer_id', '=', 'customers.id')
    ->groupBy('customers.id', 'customers.first_name', 'customers.last_name')
    ->havingRaw('COUNT(DISTINCT orders.id) > 0')
    ->orderByDesc('lifetime_value')
    ->get();
```

---

## Real-world: Reporting Database (Denormalized)

*Real-world: Reporting Database (Denormalized) üçün kod nümunəsi:*
```php
// Denormalized reporting cədvəli
class CreateSalesReportTable extends Migration
{
    public function up(): void
    {
        Schema::create('sales_report', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('order_number');
            
            // Denormalized customer data
            $table->unsignedBigInteger('customer_id');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_city');
            
            // Denormalized product data
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->string('product_sku');
            $table->string('product_category');
            $table->string('product_brand')->nullable();
            
            // Metrics
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            
            // Dimension data
            $table->string('order_status');
            $table->string('payment_method')->nullable();
            $table->string('shipping_country');
            $table->string('shipping_city');
            
            $table->timestamps();
            
            // Reporting index-ləri
            $table->index('date');
            $table->index(['date', 'product_category']);
            $table->index(['date', 'customer_city']);
            $table->index('customer_id');
            $table->index('product_id');
            $table->index('order_status');
        });
    }
}

// Report cədvəlini dolduran Job
class PopulateSalesReport implements ShouldQueue
{
    public function handle(): void
    {
        // Əvvəlki günün datası
        $date = Carbon::yesterday();
        
        $orders = Order::with(['items.product.category', 'items.product.brand', 'customer'])
            ->whereDate('created_at', $date)
            ->get();
        
        $reportData = [];
        
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $reportData[] = [
                    'date' => $date->toDateString(),
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer->full_name,
                    'customer_email' => $order->customer->email,
                    'customer_city' => $order->shipping_city,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'product_category' => $item->product->category->name ?? 'Unknown',
                    'product_brand' => $item->product->brand->name ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total,
                    'tax_amount' => $item->total * 0.18,
                    'discount_amount' => 0,
                    'net_amount' => $item->total * 1.18,
                    'order_status' => $order->status,
                    'payment_method' => $order->payment_method ?? null,
                    'shipping_country' => $order->shipping_country,
                    'shipping_city' => $order->shipping_city,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        
        // Batch insert
        collect($reportData)->chunk(1000)->each(function ($chunk) {
            DB::table('sales_report')->insert($chunk->toArray());
        });
    }
}

// Sürətli reporting sorğuları (JOIN lazım deyil!)
class ReportController extends Controller
{
    // Kateqoriyaya görə gündəlik satış
    public function dailySalesByCategory(Request $request)
    {
        $results = DB::table('sales_report')
            ->select([
                'date',
                'product_category',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_number) as order_count'),
            ])
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->where('order_status', '!=', 'cancelled')
            ->groupBy('date', 'product_category')
            ->orderBy('date')
            ->get();
        
        return response()->json($results);
    }
    
    // Şəhərə görə müştəri analizi (JOIN-sız sürətli)
    public function customersByCity()
    {
        return DB::table('sales_report')
            ->select([
                'customer_city',
                DB::raw('COUNT(DISTINCT customer_id) as unique_customers'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('AVG(total_amount) as avg_order_value'),
            ])
            ->groupBy('customer_city')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get();
    }
}
```

---

## Indexing Strategiyaları

### Normalizasiya Edilmiş Database-də İndekslər

*Normalizasiya Edilmiş Database-də İndekslər üçün kod nümunəsi:*
```php
// 1. Foreign Key indexləri (JOIN performansı üçün kritik)
Schema::table('orders', function (Blueprint $table) {
    // Laravel avtomatik index yaradır foreignId() üçün
    $table->foreignId('customer_id')->constrained();
    // Amma composite index manual əlavə etmək lazımdır
    $table->index(['customer_id', 'status', 'created_at']);
});

// 2. Covering Index (index-only scan)
// Tez-tez istifadə olunan sorğu:
// SELECT id, name, price FROM products WHERE category_id = ? AND is_active = 1 ORDER BY price
Schema::table('products', function (Blueprint $table) {
    $table->index(['category_id', 'is_active', 'price']); // Covering: category filter + sort
});

// 3. Partial/Conditional Index (PostgreSQL)
if (DB::getDriverName() === 'pgsql') {
    // Yalnız aktiv sifarişlər üçün index (daha kiçik, daha sürətli)
    DB::statement("CREATE INDEX idx_pending_orders ON orders (customer_id, created_at) WHERE status = 'pending'");
    
    // Soft-deleted olmayan records üçün
    DB::statement("CREATE INDEX idx_active_products ON products (category_id, price) WHERE deleted_at IS NULL");
}

// 4. Expression/Functional Index
// LOWER(email) ilə axtarış
if (DB::getDriverName() === 'pgsql') {
    DB::statement("CREATE INDEX idx_customers_email_lower ON customers (LOWER(email))");
} else {
    // MySQL 8.0+ functional index
    DB::statement("CREATE INDEX idx_customers_email_lower ON customers ((LOWER(email)))");
}

// 5. Composite Index sıralaması əhəmiyyətlidir!
// Qayda: Equality first, then range, then sort
// WHERE status = 'active' AND price > 100 ORDER BY created_at
$table->index(['status', 'price', 'created_at']);
// status = equality, price = range, created_at = sort

// 6. Indexləri monitoring et
// MySQL:
// SHOW INDEX FROM products;
// SELECT * FROM sys.schema_unused_indexes;     -- İstifadə olunmayan index-lər
// SELECT * FROM sys.schema_redundant_indexes;  -- Redundant index-lər

// PostgreSQL:
// SELECT * FROM pg_stat_user_indexes WHERE idx_scan = 0;  -- İstifadə olunmayan
```

---

## N+1 Query Problemi və Həlli

### Problem

*Problem üçün kod nümunəsi:*
```php
// PİS: N+1 Query
// 1 query: SELECT * FROM orders
// + N query: SELECT * FROM customers WHERE id = ? (hər order üçün)
$orders = Order::all();

foreach ($orders as $order) {
    echo $order->customer->name; // Hər dəfə ayrı query!
}
// 100 sifariş = 101 query!
```

### Həll Yolları

*Həll Yolları üçün kod nümunəsi:*
```php
// 1. Eager Loading: with()
// 2 query: SELECT * FROM orders + SELECT * FROM customers WHERE id IN (1,2,3...)
$orders = Order::with('customer')->get();

foreach ($orders as $order) {
    echo $order->customer->name; // Artıq yüklənib, əlavə query yox
}

// 2. Nested Eager Loading
$orders = Order::with([
    'customer',
    'items.product.category',
    'items.product.brand',
])->get();

// 3. Conditional Eager Loading (constraints)
$orders = Order::with([
    'items' => function ($query) {
        $query->where('quantity', '>', 1)
              ->orderBy('total', 'desc');
    },
    'items.product:id,name,price', // Yalnız lazımi sütunlar
])->get();

// 4. Lazy Eager Loading (artıq yüklənmiş collection üçün)
$orders = Order::all();
$orders->load('customer', 'items'); // Sonradan eager load

// 5. withCount() - sayma üçün
$customers = Customer::withCount('orders')
    ->withSum('orders', 'total')
    ->withAvg('orders', 'total')
    ->withMax('orders', 'created_at')
    ->get();

foreach ($customers as $customer) {
    echo $customer->orders_count;       // Əlavə query yox
    echo $customer->orders_sum_total;   // Əlavə query yox
}

// 6. Subquery Select
$customers = Customer::addSelect([
    'last_order_date' => Order::select('created_at')
        ->whereColumn('customer_id', 'customers.id')
        ->latest()
        ->limit(1),
])->get();

// 7. N+1 Problemi Aşkarlanması
// AppServiceProvider.php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Development-da N+1-i aşkar et (exception atır)
    Model::preventLazyLoading(!app()->isProduction());
    
    // Və ya yalnız log et
    Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
        logger()->warning("N+1 detected: {$model::class}::{$relation}");
    });
}

// 8. Eager Loading defaults (Model-də)
class Order extends Model
{
    // Həmişə bu relationships yüklənsin
    protected $with = ['customer', 'items'];
    
    // withCount default
    protected $withCount = ['items'];
}
```

### N+1 Aşkarlama Alətləri

*N+1 Aşkarlama Alətləri üçün kod nümunəsi:*
```php
// Laravel Debugbar ilə
// composer require barryvdh/laravel-debugbar --dev
// Queries tabında duplicate sorğuları görə bilərsiniz

// Laravel Telescope ilə
// composer require laravel/telescope --dev
// Queries bölməsində N+1 pattern-ləri aşkar edə bilərsiniz

// Clockwork
// composer require itsgoingd/clockwork --dev
```

---

## Database Relationships Laravel-də

### One-to-One (1:1)

*One-to-One (1:1) üçün kod nümunəsi:*
```php
// Hər müştərinin bir profili var
class Customer extends Model
{
    public function profile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }
}

class CustomerProfile extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

// Migration
Schema::create('customer_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
    $table->date('birth_date')->nullable();
    $table->string('avatar')->nullable();
    $table->text('bio')->nullable();
    $table->timestamps();
});

// İstifadə
$customer = Customer::with('profile')->find(1);
echo $customer->profile->birth_date;

// 1:1 yaratma
$customer->profile()->create(['birth_date' => '1990-05-15']);
```

### One-to-Many (1:N)

*One-to-Many (1:N) üçün kod nümunəsi:*
```php
// Bir kateqoriyada çox məhsul
class Category extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

class Product extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

// İstifadə
$category = Category::with('products')->find(1);
$category->products->count(); // Collection metodu

// Yeni məhsul əlavə et
$category->products()->create([
    'name' => 'New Product',
    'price' => 99.99,
]);

// Has Many Through (keçid əlaqəsi)
// Country -> Users -> Orders
class Country extends Model
{
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, User::class);
    }
}
```

### Many-to-Many (N:N)

*Many-to-Many (N:N) üçün kod nümunəsi:*
```php
// Products <-> Tags
class Product extends Model
{
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)
            ->withTimestamps()
            ->withPivot('sort_order');
    }
}

class Tag extends Model
{
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withTimestamps();
    }
}

// Migration (pivot table)
Schema::create('product_tag', function (Blueprint $table) {
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->primary(['product_id', 'tag_id']);
});

// İstifadə
$product->tags()->attach([1, 2, 3]);                    // Əlavə et
$product->tags()->detach([2]);                           // Sil
$product->tags()->sync([1, 3, 5]);                       // Sync (lazımsızları sil, yenilərini əlavə et)
$product->tags()->syncWithoutDetaching([1, 3, 5]);       // Yalnız əlavə et, silmə
$product->tags()->toggle([1, 2, 3]);                     // Varsa sil, yoxdursa əlavə et

// Pivot data ilə
$product->tags()->attach([
    1 => ['sort_order' => 1],
    2 => ['sort_order' => 2],
]);

// Pivot-a əsasən filter
$product->tags()->wherePivot('sort_order', '>', 0)->get();
```

---

## Pivot Tables

### Əlavə Data ilə Pivot Table

*Əlavə Data ilə Pivot Table üçün kod nümunəsi:*
```php
// Enrollment pivot: tələbə-kurs əlaqəsi + qiymət, tarix və s.
Schema::create('enrollments', function (Blueprint $table) {
    $table->id(); // Öz primary key-i olsun
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->foreignId('course_id')->constrained()->cascadeOnDelete();
    $table->enum('status', ['enrolled', 'completed', 'dropped'])->default('enrolled');
    $table->decimal('grade', 4, 2)->nullable();
    $table->date('enrolled_at');
    $table->date('completed_at')->nullable();
    $table->timestamps();
    
    $table->unique(['student_id', 'course_id']);
});

// Model-lərdə
class Student extends Model
{
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->using(Enrollment::class)  // Custom pivot model
            ->withPivot(['status', 'grade', 'enrolled_at', 'completed_at'])
            ->withTimestamps();
    }
    
    // Scope: yalnız tamamlanmış kurslar
    public function completedCourses(): BelongsToMany
    {
        return $this->courses()->wherePivot('status', 'completed');
    }
}

// Custom Pivot Model
class Enrollment extends Pivot
{
    protected $table = 'enrollments';
    public $incrementing = true; // Öz ID-si var
    
    protected $casts = [
        'enrolled_at' => 'date',
        'completed_at' => 'date',
        'grade' => 'decimal:2',
    ];
    
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
    
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
    
    // Accessor
    public function getIsPassedAttribute(): bool
    {
        return $this->grade !== null && $this->grade >= 50;
    }
}

// İstifadə
$student = Student::with('courses')->find(1);

foreach ($student->courses as $course) {
    echo $course->name;
    echo $course->pivot->grade;
    echo $course->pivot->status;
    echo $course->pivot->is_passed ? 'Keçdi' : 'Kəsildi';
}

// Enrollment yaratma
$student->courses()->attach($courseId, [
    'status' => 'enrolled',
    'enrolled_at' => now(),
]);

// Enrollment yeniləmə
$student->courses()->updateExistingPivot($courseId, [
    'status' => 'completed',
    'grade' => 85.50,
    'completed_at' => now(),
]);
```

---

## Polymorphic Relationships

### Polymorphic One-to-Many

*Polymorphic One-to-Many üçün kod nümunəsi:*
```php
// Comments: həm products, həm articles, həm videos üçün
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->text('body');
    $table->foreignId('user_id')->constrained();
    $table->morphs('commentable'); // commentable_id, commentable_type + index
    $table->timestamps();
});

class Comment extends Model
{
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

class Product extends Model
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Article extends Model
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

// İstifadə
$product->comments()->create(['body' => 'Əla məhsul!', 'user_id' => 1]);
$article->comments()->create(['body' => 'Faydalı məqalə', 'user_id' => 2]);

// Bütün comment-ləri almaq (həm product, həm article)
$comment = Comment::with('commentable')->find(1);
echo get_class($comment->commentable); // App\Models\Product və ya App\Models\Article
```

### Polymorphic Many-to-Many

*Polymorphic Many-to-Many üçün kod nümunəsi:*
```php
// Tags: products, articles, videos üçün eyni tag sistemi
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});

Schema::create('taggables', function (Blueprint $table) {
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->morphs('taggable');
    $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
});

class Tag extends Model
{
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'taggable');
    }
    
    public function articles(): MorphToMany
    {
        return $this->morphedByMany(Article::class, 'taggable');
    }
}

class Product extends Model
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

class Article extends Model
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

// İstifadə
$product->tags()->attach([1, 2, 3]);
$article->tags()->sync([2, 4, 5]);

// Tag-a görə məhsul tapmaq
$tag = Tag::with('products', 'articles')->where('slug', 'php')->first();
$tag->products; // Bu tag-ı olan bütün məhsullar
$tag->articles; // Bu tag-ı olan bütün məqalələr
```

### Polymorphic One-to-One

*Polymorphic One-to-One üçün kod nümunəsi:*
```php
// Image: product, user, category - hər biri üçün bir əsas şəkil
Schema::create('images', function (Blueprint $table) {
    $table->id();
    $table->string('path');
    $table->string('alt_text')->nullable();
    $table->morphs('imageable');
    $table->timestamps();
});

class Image extends Model
{
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}

class User extends Model
{
    public function avatar(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}

// Morph Map (best practice - class adı əvəzinə qısa ad)
// AppServiceProvider.php
use Illuminate\Database\Eloquent\Relations\Relation;

public function boot(): void
{
    Relation::enforceMorphMap([
        'product' => Product::class,
        'article' => Article::class,
        'user' => User::class,
        'category' => Category::class,
    ]);
    // Bu sayədə database-də 'App\Models\Product' əvəzinə 'product' saxlanılır
    // Refactoring zamanı class adı dəyişsə, problem yaranmır
}
```

### Polymorphic Münasibətlərin Normalizasiya Aspekti

```
Polymorphic relationships normalizasiya qaydalarını formal olaraq pozur, çünki:
1. Foreign key constraint yaratmaq mümkün deyil (commentable_type + commentable_id)
2. Referential integrity database səviyyəsində təmin olunmur

Alternativlər:
1. Ayrı cədvəllər: product_comments, article_comments (full normalization)
   - Üstünlük: FK constraint, data integrity
   - Mənfi: Kod təkrarı, çox cədvəl

2. Polymorphic (Laravel yanaşması):
   - Üstünlük: Sadəlik, DRY, çevik
   - Mənfi: FK constraint yox, application-level integrity

3. Shared parent table (abstract table):
   - commentable_entities (id, type) + products FK → commentable_entities
   - Üstünlük: FK + polymorphism
   - Mənfi: Mürəkkəblik

Praktik tövsiyə: Laravel-in polymorphic relationships çox yaxşı işləyir.
morph map istifadə edin, application-level validation ilə integrity-ni qoruyun.
```

---

## İntervyu Sualları və Cavabları

### S1: Normalization nədir və niyə lazımdır?

**Cavab:** Normalization database-in strukturunu optimallaşdırmaq üçün sistematik prosesdir. Əsas məqsədlər: data redundancy-ni azaltmaq (eyni data bir yerdə saxlanılsın), data anomaly-lərini (insert, update, delete anomaliyaları) aradan qaldırmaq və data integrity-ni təmin etmək. Normal form-lar (1NF-5NF) ardıcıl qaydalar toplusudur. Praktikada əksər hallarda 3NF və ya BCNF kifayət edir.

### S2: 1NF, 2NF, 3NF arasındakı fərqləri izah edin.

**Cavab:**
- **1NF:** Hər sütundakı dəyər atomic (bölünməz) olmalıdır. Yəni bir sütunda "Math, Physics" kimi çox dəyər olmamalıdır.
- **2NF:** 1NF + partial dependency olmamalıdır. Composite primary key-in yalnız bir hissəsindən asılı olan sütunlar ayrı cədvələ çıxarılmalıdır.
- **3NF:** 2NF + transitive dependency olmamalıdır. Non-key sütunlar yalnız primary key-dən birbaşa asılı olmalıdır, başqa non-key sütun vasitəsilə deyil. Məsələn, employee cədvəlində department_name saxlamaq 3NF-i pozur - ayrı departments cədvəli lazımdır.

### S3: Denormalization nədir və nə vaxt istifadə olunur?

**Cavab:** Denormalization normalizasiya edilmiş database-ə bilərəkdən redundancy əlavə etməkdir. Məqsəd read performansını artırmaqdır. İstifadə halları: reporting/analytics cədvəlləri (JOIN-sız sürətli sorğular), cache column-ları (COUNT, SUM əvvəlcədən hesablanır), microservices (service-lər arası JOIN mümkün deyil), yüksək yüklü read əməliyyatları. Qayda: əvvəlcə normalize et, performans problemi ölçüldükdə denormalize et.

### S4: N+1 query problemi nədir və necə həll olunur?

**Cavab:** N+1 problemi bir query ilə N record alıb, sonra hər record üçün ayrıca query icra etməkdir. Məsələn, 100 sifariş + hər birinin müştərisini ayrı query ilə almaq = 101 query. Həlli: Laravel-də `with()` (Eager Loading) istifadə etmək - bu cəmi 2 query icra edir (1 sifariş sorğusu + 1 WHERE IN ilə müştəri sorğusu). `Model::preventLazyLoading()` ilə development-da N+1-i aşkar etmək olar.

### S5: Polymorphic relationship nədir və nə vaxt istifadə olunur?

**Cavab:** Polymorphic relationship bir modelin birdən çox digər model tipinə aid ola bilməsi deməkdir. Məsələn, Comment modeli həm Product-a, həm Article-a, həm Video-ya aid ola bilər. Database-də `commentable_type` və `commentable_id` sütunları ilə həyata keçirilir. İstifadə halları: comments, images, tags, likes, activities sistemi - eyni funksionallığı müxtəlif modellər üçün təkrarlamaq əvəzinə bir struktur ilə həll edir. Mənfi tərəfi: FK constraint yaratmaq mümkün deyil, `Relation::enforceMorphMap()` istifadə edilməlidir.

### S6: Pivot table nədir?

**Cavab:** Pivot table (junction/bridge table) many-to-many (N:N) əlaqəni həyata keçirmək üçün istifadə olunan ara cədvəldir. Məsələn, products və tags cədvəlləri arasında product_tag pivot cədvəli olur. Pivot cədvəldə əlavə sütunlar da ola bilər (məsələn, enrollments cədvəlində grade, status). Laravel-də `belongsToMany()`, `attach()`, `detach()`, `sync()`, `withPivot()` ilə idarə olunur. Custom Pivot model yaratmaq üçün `Pivot` class-ından extend etmək olar.

### S7: E-commerce database-ində order_items cədvəlində product_name və unit_price saxlamaq denormalization-dır?

**Cavab:** Xeyr! Bu denormalization deyil, business tələbidir. Sifariş yaradıldığı andakı məhsul adı və qiyməti saxlanılmalıdır, çünki sonradan məhsulun adı və ya qiyməti dəyişə bilər. Tarixi data-nın düzgünlüyünü qorumaq üçün bu vacibdir. Əgər yalnız product_id saxlasaq və məhsulun qiyməti dəyişsə, köhnə sifarişlərin məbləği yanlış görünər. Bu "point-in-time snapshot" pattern-idir.

### S8: Database relationship tipləri arasındakı fərqlər nədir?

**Cavab:**
- **1:1 (One-to-One):** Bir user-in bir profili var. FK unique olmalıdır. İstifadə: nadir istifadə olunan sütunları ayırmaq, sensitive data-nı ayırmaq.
- **1:N (One-to-Many):** Bir kateqoriyada çox məhsul. FK "many" tərəfdədir. Ən çox istifadə olunan əlaqə tipi.
- **N:N (Many-to-Many):** Bir məhsulun çox tag-ı, bir tag-ın çox məhsulu. Pivot table lazımdır. Laravel-də `belongsToMany()`.
- **Has Many Through:** Keçid əlaqəsi. Country → Users → Orders. Birbaşa FK olmadan dolayı əlaqə.
- **Polymorphic:** Bir comment həm product-a, həm article-a aid ola bilər. `morphable_type` + `morphable_id` ilə.

### S9: Functional Dependency nədir?

**Cavab:** Functional Dependency (A → B) deməkdir ki, A-nın dəyəri B-nin dəyərini unikal olaraq müəyyən edir. Məsələn, `email → customer_name` (bir email həmişə eyni adı qaytarır). Normalization bu dependency-ləri analiz edərək cədvəlləri bölür: Partial dependency 2NF-i, transitive dependency 3NF-i, non-candidate-key determinant BCNF-i pozur.

### S10: Real layihədə normalization strategiyanız necə olardı?

**Cavab:** OLTP (transactional) database üçün 3NF/BCNF-ə qədər normalize edirəm. E-commerce nümunəsində: ayrı customers, products, orders, order_items, categories, tags cədvəlləri. Reporting/Analytics üçün denormalized cədvəllər və ya Materialized Views yaradıram. Performans ölçüb, lazım olan yerdə cache column-lar əlavə edirəm (məsələn, orders.items_count). N+1 probleminə qarşı Eager Loading, indekslər üçün EXPLAIN ANALYZE istifadə edirəm. Polymorphic relationships-i morph map ilə istifadə edirəm.

---

## Anti-patternlər

**1. Həddindən Artıq Normalization (Over-normalization)**
Hər şeyi 5NF-ə qədər bölmək — sadə sorğular onlarca JOIN tələb edir, performans aşağı düşür, kod anlaşılmaz olur. OLTP sistemlər üçün 3NF adətən kifayətdir; daha yüksək normal formalar yalnız xüsusi data anormallıq problemləri üçün nəzərdən keçirilsin.

**2. Ölçüm Etmədən Denormalization**
"Performans üçün" fərz edərək cədvəlləri birləşdirmək — data anormallıqları yaranır, update anomaliyaları ortaya çıxır, data bütövlüyü pozulur. Əvvəlcə normalize edin, `EXPLAIN ANALYZE` ilə real darboğazı müəyyən edin, yalnız sübut olunan problem üçün denormalize edin.

**3. Pivot Cədvəl Əvəzinə Vergüllə Ayrılmış Dəyərlər**
Many-to-many əlaqəni `tags: "php,laravel,mysql"` kimi tək sütunda saxlamaq — 1NF pozulur, filtrləmə, sayma, birləştirmə mümkünsüzləşir, indekslər işləmir. Həmişə ayrı pivot cədvəl yaradın.

**4. NULL-ları Yanlış İstifadə Etmək**
Məcburi sahələri `NULL` olaraq qoymaq, yaxud NULL-u "0" və ya "boş sətir" mənasında işlətmək — sorğu məntiqi mürəkkəbləşir, `IS NULL` vs `= ''` qarışıqlığı yaranır. `NOT NULL` məhdudiyyəti default dəyərlə birlikdə istifadə edin; NULL yalnız "məlumat yoxdur" mənasını daşımalıdır.

**5. Surogat Key Əvəzinə Mürəkkəb Natural Key İstifadəsi**
`email + created_at` kimi kompozit natural key-ləri primary key seçmək — foreign key-lər mürəkkəbləşir, dəyişən business key-lər bütün əlaqəli cədvəlləri pozur. Adətən `id` autoincrement və ya UUID surogat key istifadə edin, natural key-lərə unique constraint əlavə edin.

**6. Foreign Key Constraint-ləri Deaktiv Etmək**
Performans bəhanəsiylə FK constraint-ləri silmək — referential integrity database tərəfindən qorunmur, "orfan" data yığılır, data anormallıqları baş verir. FK constraint-ləri aktiv saxlayın; əgər performans problemidirsə, indeksləri yoxlayın və sorğuları optimallaşdırın.

**7. OLAP Üçün OLTP Sxemasını İstifadə Etmək**
Reporting və analytics üçün normalize olunmuş OLTP cədvəllərini birbaşa istifadə etmək — onlarca JOIN tələb olunur, hesabat sorğuları production DB-ni yükləyir. Analytics üçün ayrıca denormalize edilmiş data warehouse (Star Schema / Snowflake Schema) yaradın; ETL pipeline-ı ilə OLTP-dən data köçürün.

**8. UUID vs BIGINT Primary Key Seçimini Düşünməmək**
Hər cədvəl üçün avtomatik UUID istifadə etmək — UUID random olduğu üçün B-tree index sequential deyil, page splits artır, index ölçüsü böyüyür. Performans kritikdirsə BIGINT AUTOINCREMENT (sequential) daha yaxşıdır. UUID lazım olduqda `ULID` (sortable UUID) ya da `UUID v7` (time-ordered) istifadə edin — index performance BIGINT-ə yaxın olur.
