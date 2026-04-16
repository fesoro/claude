# Normalization & Denormalization

## Normalization nedir?

Data tekrarlanmasini (redundancy) azaltmaq ve data integrity-ni artirmaq ucun table-lari kiçik, munasib hisselere bolmek prosesidir.

---

## Normal Formalar

### 1NF (First Normal Form)

**Qayda:** Her sutunda yalniz **bir** deyer olmalidir (atomic values). Tekrarlanan qruplar yoxdur.

```
-- 1NF POZULUR:
| id | name  | phones                    |
|----|-------|---------------------------|
| 1  | John  | 055-111-1111, 070-222-2222|

-- 1NF-e uygun:
-- Users table
| id | name |
|----|------|
| 1  | John |

-- Phones table
| id | user_id | phone        |
|----|---------|--------------|
| 1  | 1       | 055-111-1111 |
| 2  | 1       | 070-222-2222 |
```

### 2NF (Second Normal Form)

**Qayda:** 1NF + butun non-key sutunlar **tam** primary key-den asili olmalidir (partial dependency yoxdur).

Yalniz **composite primary key** olan table-larda aktualdır.

```
-- 2NF POZULUR:
-- PK: (student_id, course_id)
| student_id | course_id | student_name | grade |
|------------|-----------|-------------|-------|
| 1          | 101       | John        | A     |
| 1          | 102       | John        | B     |

-- student_name yalniz student_id-den asilidir, course_id-den yox!
-- Bu partial dependency-dir.

-- 2NF-e uygun:
-- Students table
| student_id | student_name |
|------------|-------------|
| 1          | John        |

-- Enrollments table
| student_id | course_id | grade |
|------------|-----------|-------|
| 1          | 101       | A     |
| 1          | 102       | B     |
```

### 3NF (Third Normal Form)

**Qayda:** 2NF + non-key sutunlar diger non-key sutunlardan **asili olmamalidir** (transitive dependency yoxdur).

```
-- 3NF POZULUR:
| order_id | customer_id | customer_city | customer_country |
|----------|-------------|--------------|-----------------|
| 1        | 10          | Baku         | Azerbaijan      |

-- customer_city ve customer_country customer_id-den asilidir (transitiv!)
-- order_id -> customer_id -> customer_city (zencir)

-- 3NF-e uygun:
-- Orders table
| order_id | customer_id |
|----------|-------------|
| 1        | 10          |

-- Customers table
| customer_id | city | country    |
|-------------|------|------------|
| 10          | Baku | Azerbaijan |
```

### BCNF (Boyce-Codd Normal Form)

**Qayda:** 3NF + her determinant candidate key olmalidir.

```
-- BCNF POZULUR:
-- Bir student bir course-da bir professor-dan ders alir
-- Bir professor yalniz bir course tedris edir
| student_id | course | professor |
|------------|--------|-----------|
| 1          | Math   | Dr. Ali   |
| 2          | Math   | Dr. Ali   |
| 3          | Math   | Dr. Veli  |

-- professor -> course (professor course-u mueyyenlesdirir)
-- Amma professor candidate key deyil!

-- BCNF-e uygun:
-- Professors table
| professor | course |
|-----------|--------|
| Dr. Ali   | Math   |
| Dr. Veli  | Math   |

-- Enrollments table
| student_id | professor |
|------------|-----------|
| 1          | Dr. Ali   |
| 2          | Dr. Ali   |
| 3          | Dr. Veli  |
```

---

## Praktikada Normalization

Cogu production database **3NF** seviyyesindedir. BCNF ve daha yuxari formalar nadir hallarda lazim olur.

**Laravel Migration misali (3NF):**

```php
// Users table
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});

// Addresses table (ayri - cunki user-in bir nece unvani ola biler)
Schema::create('addresses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('street');
    $table->string('city');
    $table->foreignId('country_id')->constrained();
    $table->timestamps();
});

// Countries table (ayri - cunki country_name city-den asili olardi)
Schema::create('countries', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code', 2)->unique();
});
```

---

## Denormalization

Normalization-in **terskisi**. Performance ucun **bilincli** olaraq data tekrarlanir.

### Niye lazimdir?

Normalization cox JOIN teleb edir. Yuksek traffic-de JOIN-lar yavas ola biler.

```sql
-- Normallasdirilmis: 4 JOIN lazimdir
SELECT 
    o.id, u.name, p.title, c.name AS category
FROM orders o
JOIN users u ON u.id = o.user_id
JOIN products p ON p.id = o.product_id
JOIN categories c ON c.id = p.category_id
WHERE o.status = 'pending';

-- Denormalize: Butun lazimi data bir table-da
SELECT id, user_name, product_title, category_name
FROM orders_denormalized
WHERE status = 'pending';
-- JOIN yoxdur, tek table scan - cox suretli!
```

### Denormalization Texnikalari

#### 1. Computed/Cached Columns

```php
// orders table-da total_amount saxlamaq (her defe hesablamaq yerine)
Schema::table('orders', function (Blueprint $table) {
    $table->decimal('total_amount', 10, 2)->default(0);
    $table->integer('items_count')->default(0);
});

// Order item elave olunanda yenile
class OrderItem extends Model
{
    protected static function booted()
    {
        static::created(function (OrderItem $item) {
            $item->order->update([
                'total_amount' => $item->order->items()->sum('price'),
                'items_count' => $item->order->items()->count(),
            ]);
        });
    }
}
```

#### 2. Summary/Aggregate Tables

```sql
-- Her defe COUNT etmek yerine, ayri table saxla
CREATE TABLE daily_order_stats (
    date DATE PRIMARY KEY,
    total_orders INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    avg_order_value DECIMAL(10,2) DEFAULT 0
);

-- Cron job ve ya trigger ile yenile
INSERT INTO daily_order_stats (date, total_orders, total_revenue, avg_order_value)
SELECT 
    DATE(created_at),
    COUNT(*),
    SUM(total_amount),
    AVG(total_amount)
FROM orders
WHERE DATE(created_at) = CURDATE()
ON DUPLICATE KEY UPDATE
    total_orders = VALUES(total_orders),
    total_revenue = VALUES(total_revenue),
    avg_order_value = VALUES(avg_order_value);
```

#### 3. Duplicated Foreign Key Data

```sql
-- Normallasdirilmis
-- orders -> products -> categories
-- Category adini almaq ucun 2 JOIN lazimdir

-- Denormalize: category_name-i birbaşa orders-a elave et
ALTER TABLE orders ADD COLUMN category_name VARCHAR(100);

-- Amma: category adi deyiserse, butun orders-lari da yenilemek lazimdir!
```

#### 4. Materialized Views (PostgreSQL)

```sql
-- PostgreSQL
CREATE MATERIALIZED VIEW order_summary AS
SELECT 
    u.id AS user_id,
    u.name AS user_name,
    COUNT(o.id) AS total_orders,
    SUM(o.total_amount) AS total_spent
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id, u.name;

-- Refresh (manual)
REFRESH MATERIALIZED VIEW order_summary;

-- Refresh (concurrently - read-lari bloklamadan)
REFRESH MATERIALIZED VIEW CONCURRENTLY order_summary;
-- Bunun ucun UNIQUE index lazimdir:
CREATE UNIQUE INDEX idx_order_summary_user ON order_summary (user_id);
```

MySQL-de materialized view yoxdur. Emulasiya:

```php
// Scheduled job ile
class RefreshOrderSummary implements ShouldQueue
{
    public function handle()
    {
        DB::statement('TRUNCATE TABLE order_summary');
        DB::statement('
            INSERT INTO order_summary (user_id, user_name, total_orders, total_spent)
            SELECT u.id, u.name, COUNT(o.id), COALESCE(SUM(o.total_amount), 0)
            FROM users u
            LEFT JOIN orders o ON o.user_id = u.id
            GROUP BY u.id, u.name
        ');
    }
}
```

---

## Ne vaxt Normalize, ne vaxt Denormalize?

| Ssenari | Tovsiye |
|---------|---------|
| OLTP (transactional system) | Normalize (3NF) |
| OLAP (analytical/reporting) | Denormalize |
| Write-heavy | Normalize |
| Read-heavy | Denormalize ola biler |
| Data integrity kritikdir | Normalize |
| Millisaniye response lazimdir | Denormalize |

**Qizil qayda:** Evvelce normalize et. Yalniz performance problem olduqda, olcub (EXPLAIN ile) denormalize et.

---

## Interview suallari

**Q: 3NF-i sade dille izah et.**
A: Her non-key sutun yalniz primary key-den asili olmalidir, basqa hec neyden yox. Yeni, key-den birbaşa asili olmalidi - ne hissevi (partial), ne de dolayi (transitive) asililik olmamalidir.

**Q: Denormalization-in riskleri neDir?**
A: 1) Data inconsistency - eyni data bir nece yerde oldugundan, birini yenilemeyi yaddan cikarsan uygunsuzluq yaranir. 2) Storage artir. 3) Write performance azalir (bir nece yeri yenilemek lazim). 4) Application logic murakkeblesir.

**Q: Real proyektde nece qerar verirsen?**
A: Query pattern-lere bax. Eger mueyyyen JOIN-lar cox tez-tez isleyir ve yavasdir (EXPLAIN ile yoxla), o JOIN-in neticesini denormalize et. Amma evvelce index, query optimization ve caching yoxla.
