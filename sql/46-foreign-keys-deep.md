# Foreign Keys Deep Dive

> **Seviyye:** Intermediate ⭐⭐

## FK nedir?

Foreign Key (FK) - bir table-in column-u (ve ya column qrupu) baska table-in primary key (ve ya UNIQUE) sutununa **referans** verir. Database referential integrity-ni qoruyur.

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    total DECIMAL(10,2),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO orders (user_id, total) VALUES (999, 50.00);
-- ERROR: Cannot add or update a child row: a foreign key constraint fails
```

**Niye lazimdir?**
- **Referential integrity** - orphan row qarsisi (user silindi, amma onun order-leri qaldi)
- **Self-documenting schema** - FK gorerek developer relation-i anlayir
- **ORM-leri avtomatik tanir** (Eloquent relationships)
- **Migration safety** - column adi deyiserse FK xeber verir

---

## ON DELETE / ON UPDATE Davranislari

```sql
FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE RESTRICT
```

| Action | Davranis | Use case |
|--------|----------|----------|
| **CASCADE** | Parent silinir/yenilenir -> child da silinir/yenilenir | order_items (order silinende item-ler de silinsin) |
| **RESTRICT** | Parent silinmir eger child varsa (default MySQL) | users (eger order-leri varsa silmek olmaz) |
| **NO ACTION** | RESTRICT-e oxsar, lakin DEFERRABLE ola biler (PG) | Strict default |
| **SET NULL** | Parent silinir -> child column NULL olur | posts (author silinende post anonymous qalsin) |
| **SET DEFAULT** | Parent silinir -> child column DEFAULT deyere qayidir | Az istifade olunur |

**Praktiki misallar:**

```sql
-- Order silinende item-ler avtomatik silinsin
CREATE TABLE order_items (
    id BIGINT PRIMARY KEY,
    order_id BIGINT,
    product_id BIGINT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Post yazani silinende post qalsin (author = NULL)
CREATE TABLE posts (
    id BIGINT PRIMARY KEY,
    author_id BIGINT NULL,
    title VARCHAR(255),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Category-ni silmek qadagandir eger product-i varsa
ALTER TABLE products
    ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT;
```

---

## RESTRICT vs NO ACTION

| Aspekt | RESTRICT | NO ACTION |
|--------|----------|-----------|
| Yoxlama vaxti | Statement basinda (immediate) | Statement sonunda (default) |
| DEFERRABLE | Yox | Beli (PG) |
| MySQL davranisi | Eyni (NO ACTION = RESTRICT) | Eyni |
| PostgreSQL ferq | Statement evvelinde fail | Statement sonunda fail (transaction-da defer ola biler) |

```sql
-- PostgreSQL: deferrable constraint
CREATE TABLE orders (
    user_id BIGINT,
    FOREIGN KEY (user_id) REFERENCES users(id)
        DEFERRABLE INITIALLY DEFERRED
);

BEGIN;
DELETE FROM users WHERE id = 1;       -- order-ler hele user_id=1
DELETE FROM orders WHERE user_id = 1; -- indi order-leri sil
COMMIT;  -- ENDI yoxlanir, hamisi OK
-- Olmasaydi DEFERRED, ilk DELETE FAIL olardi
```

Use case: data migration, bulk reordering, circular FK.

---

## FK Index Zerureti

**Vacib bilgi:** Cox developer bilmir - **MySQL InnoDB FK column-a avtomatik index yaradir** (parent table-da index yoxdursa). PostgreSQL ve baskalari **AVTOMATIK index YARATMIR**.

```sql
-- PostgreSQL - bunu yaratdiqda:
CREATE TABLE orders (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id)
);

-- user_id-de INDEX YOXDUR!
-- Beleliklerle:
DELETE FROM users WHERE id = 1;
-- PostgreSQL FULL TABLE SCAN edir orders-de check etmek ucun!
```

**Hell yolu - hemise index elave et:**

```sql
CREATE INDEX idx_orders_user_id ON orders(user_id);
```

```php
// Laravel migration - constrained() index avtomatik elave ETMIR
Schema::table('orders', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained();
    $table->index('user_id'); // ELAVE ETMEK LAZIM PG-de
});

// MySQL-de InnoDB avtomatik elave edir, amma yenede yazmaq best practice
```

**Index olmadiginda problem:**
- Parent DELETE/UPDATE = full scan child table-de
- JOIN performance fail
- Lock cox uzun saxlanir

---

## FK Performance Impact

FK constraint-lerin **qiymeti** var:

| Operation | Cost |
|-----------|------|
| INSERT child | +1 lookup parent index-de |
| UPDATE child FK column | +1 lookup parent index-de |
| DELETE parent | Child table-de lookup (index lazim!) |
| UPDATE parent PK | Child table-de cascade (index lazim!) |
| Bulk INSERT (millions) | Cox yavaslayir |

**Bulk insert optimization:**

```sql
-- MySQL
SET FOREIGN_KEY_CHECKS = 0;
LOAD DATA INFILE '...' INTO TABLE orders;
SET FOREIGN_KEY_CHECKS = 1;

-- PostgreSQL
ALTER TABLE orders DISABLE TRIGGER ALL;  -- FK trigger-leri de sondurur
COPY orders FROM '...';
ALTER TABLE orders ENABLE TRIGGER ALL;
-- ALTER TABLE ... VALIDATE CONSTRAINT lazim ola biler
```

> **Diqqet:** Constraint sondururken yeni invalid data daxil ola biler. Mütleq sonra VALIDATE et.

**Lock impact:**
- Parent UPDATE -> child rows-da S-lock (shared)
- Child INSERT -> parent row-da S-lock
- Yuksek concurrency-de FK lock contention yaranir

---

## FK in Sharded/Distributed DB

**Problem:** FK yalniz **eyni database** daxilinde isleyir.

```sql
-- Cross-shard FK MUMKUN DEYIL
-- shard_1.users <-> shard_2.orders -> NO

-- Cross-database FK (eyni server) MUMKUN DEYIL (cox DB-de)
-- db_users.users <-> db_orders.orders -> NO
```

**Hell yollari:**

1. **Co-locate related data** (ayni shard-da)
   ```php
   // user_id ile shard
   $shardId = $userId % 16;
   ```

2. **Soft FK** (app-level enforcement)
   ```php
   // App layer-de yoxla
   if (!User::find($userId)) {
       throw new InvalidArgumentException("User not found");
   }
   ```

3. **Eventual consistency** (saga, CDC)
4. **Reference data replication** (kicik table-leri her shard-a kopyala)

---

## Soft FK Debate (No FK in DB)

Bezi command (cox iri) layihelerde FK silinir:
- **Shopify** - cox FK yoxdur
- **GitHub** - cox table-de FK yoxdur

**Pro Soft FK (no DB FK):**
- Migration asan (column rename, table split)
- Sharding asan
- Bulk operations suretli
- ORM zaten enforce edir

**Anti Soft FK (use DB FK):**
- Orphan data yaranir (bug-li code)
- Schema documentation itir
- Junior developer tezleyebilirler
- Manual cleanup script lazim olur

**Praktiki tovsiye:** Layihe orta olculudedirse FK saxla. Hyperscale (Shopify-Facebook seviyye) olanda app-level enforcement-a kec.

---

## Laravel Migration FK Syntax

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    
    // En sade
    $table->foreignId('user_id')->constrained();
    // = unsignedBigInteger + FK to users(id)
    
    // Ozel table/column
    $table->foreignId('author_id')
        ->constrained('users', 'id');
    
    // Cascade actions
    $table->foreignId('order_id')
        ->constrained()
        ->cascadeOnDelete()  // ON DELETE CASCADE
        ->cascadeOnUpdate(); // ON UPDATE CASCADE
    
    // Set null
    $table->foreignId('manager_id')
        ->nullable()
        ->constrained('users')
        ->nullOnDelete();    // ON DELETE SET NULL
    
    // Restrict
    $table->foreignId('category_id')
        ->constrained()
        ->restrictOnDelete(); // ON DELETE RESTRICT
});
```

**Manual control:**

```php
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')
    ->references('id')->on('users')
    ->onDelete('cascade')
    ->onUpdate('cascade');
$table->index('user_id'); // PG ucun lazim!
```

**FK silmek:**

```php
$table->dropForeign(['user_id']);
// Ve ya tam FK adi:
$table->dropForeign('orders_user_id_foreign');
```

---

## Circular FK Problem

```sql
-- A -> B -> A
CREATE TABLE departments (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    head_employee_id BIGINT,
    FOREIGN KEY (head_employee_id) REFERENCES employees(id)
);

CREATE TABLE employees (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    department_id BIGINT,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Problem: insert necə basla?
-- Department insert -> head_employee_id NULL
-- Employee insert -> department_id ile
-- Department UPDATE -> head_employee_id set
```

**Hell yollari:**

1. **Nullable kol + iki addim**
   ```sql
   INSERT INTO departments (name) VALUES ('Engineering'); -- head NULL
   INSERT INTO employees (name, department_id) VALUES ('Alice', 1);
   UPDATE departments SET head_employee_id = 1 WHERE id = 1;
   ```

2. **Deferred constraint (PostgreSQL)**
   ```sql
   ALTER TABLE departments ADD FOREIGN KEY (head_employee_id) 
       REFERENCES employees(id) DEFERRABLE INITIALLY DEFERRED;
   
   BEGIN;
   INSERT INTO departments VALUES (1, 'Eng', 100);
   INSERT INTO employees VALUES (100, 'Alice', 1);
   COMMIT; -- yoxlanis indi
   ```

3. **Ayri junction table** (department_heads)

---

## Self-Referencing FK

Tree/hierarchy ucun:

```sql
CREATE TABLE categories (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    parent_id BIGINT NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Org chart
CREATE TABLE employees (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    manager_id BIGINT NULL,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Comment tree (nested)
CREATE TABLE comments (
    id BIGINT PRIMARY KEY,
    post_id BIGINT,
    parent_id BIGINT NULL,
    body TEXT,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);
```

```php
// Laravel
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('parent_id')->nullable()
        ->constrained('categories')
        ->cascadeOnDelete();
});

// Eloquent self-relation
class Category extends Model
{
    public function parent() { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id'); }
}
```

---

## Composite FK

```sql
-- Composite PK
CREATE TABLE order_items (
    order_id BIGINT,
    line_no INT,
    product_id BIGINT,
    PRIMARY KEY (order_id, line_no)
);

-- Composite FK (refundable_lines refer order_items)
CREATE TABLE refunds (
    id BIGINT PRIMARY KEY,
    order_id BIGINT,
    line_no INT,
    amount DECIMAL(10,2),
    FOREIGN KEY (order_id, line_no) REFERENCES order_items(order_id, line_no)
);
```

```php
// Laravel-de bunu manual yazirsan
Schema::create('refunds', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('order_id');
    $table->integer('line_no');
    $table->decimal('amount', 10, 2);
    
    $table->foreign(['order_id', 'line_no'])
        ->references(['order_id', 'line_no'])
        ->on('order_items');
});
```

Composite FK nadir hallarda lazimdir - adeten tek surrogate ID istifade olunur.

---

## FK Validation on Existing Data

Production DB-de yeni FK elave etmek = mevcud datanin uygunlugunu yoxlama:

```sql
-- PostgreSQL: constraint NOT VALID kimi elave et
ALTER TABLE orders ADD CONSTRAINT fk_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    NOT VALID;  -- mevcud data yoxlanmir
-- Yeni INSERT/UPDATE-ler yoxlanir, kohne data hele invalid ola biler

-- Sonra validate et (background-da, lock yox)
ALTER TABLE orders VALIDATE CONSTRAINT fk_user;
```

**MySQL-de NOT VALID yoxdur** - butun table validate olunur (lock!). Hell:

```sql
-- 1. Orphan data sil/temizle
DELETE FROM orders WHERE user_id NOT IN (SELECT id FROM users);

-- 2. Online schema change (pt-osc, gh-ost) ile FK elave et
pt-online-schema-change --alter "ADD CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES users(id)" \
    D=mydb,t=orders --execute
```

---

## Partitioned Tables (PostgreSQL)

PG-de partitioned table-da FK mehdudiyyetler:

```sql
CREATE TABLE orders (
    id BIGINT,
    user_id BIGINT REFERENCES users(id), -- OK (incoming FK)
    created_at TIMESTAMP
) PARTITION BY RANGE (created_at);

-- Amma: partitioned table-a FK YOX (PG 11-de)
-- PG 12+ : partitioned table-a FK MUMKUN
CREATE TABLE order_items (
    id BIGINT,
    order_id BIGINT,
    FOREIGN KEY (order_id) REFERENCES orders(id) -- PG 12+ OK
);
```

**Mehdudiyyetler:**
- ON UPDATE CASCADE bezi versiyalarda destek edilmir
- Partition silinende child-da action yox (manual cleanup)

---

## FK vs Application-Level Validation

| Aspekt | DB FK | App-Level Check |
|--------|-------|-----------------|
| Reliability | 100% (ne app, ne developer atlamir) | App bug-da fail |
| Performance | Yavasdir (bulk) | Suretli (eger lazimsiz query yoxdursa) |
| Sharding | Limited | Free |
| Multi-tenant | OK | OK |
| Race conditions | DB ele alir | App-de lock lazim |

**Hibrid yanasma (best practice):**
- Critical relations -> DB FK
- Async/eventual data -> app-level check
- Cross-shard -> app-level check + cleanup job

---

## Interview suallari

**Q: ON DELETE CASCADE ne vaxt teh tehlukelidir?**
A: User table-de CASCADE qoyub, sonra `DELETE FROM users WHERE id = 1` etmek butun user-in order, payment, audit log-larini silir. Audit/financial data-da CASCADE qadagandir - **SET NULL** ve ya **soft delete** istifade et. CASCADE yalniz uses tehlikesiz: order_items (order ucun anlamlidir), tag pivot tables.

**Q: PostgreSQL-de FK column-a niye index lazimdir, MySQL-de yoxdur?**
A: MySQL InnoDB hemise FK column-a avtomatik index yaradir (yoxdursa). PostgreSQL avtomatik yaratmir - dizayn qerari (sometimes index istemirsen). Index olmasa parent DELETE/UPDATE child table-de **full scan** edir - boyuk table-de olum. Hemise CREATE INDEX et.

**Q: Soft FK (DB-de FK yoxdur) yanasmasi ne vaxt mequldur?**
A: 1) Hyperscale (Shopify, Discord) - migration cevikliyi vacibdir. 2) Sharded DB - cross-shard FK qeyri-mumkundur. 3) Eventual consistency mikroservislari. Amma orta layihede DB FK saxla - app bug-da orphan data ozune problemdir, debug etmek cetindir.

**Q: Circular FK nece hell olunur?**
A: Uc usul: 1) **Nullable column + iki addim INSERT** - sade, hemise isleyir. 2) **PostgreSQL DEFERRABLE INITIALLY DEFERRED** - transaction sonunda yoxlanir. 3) **Junction table** - circular relation-i ayri table-e cixar (department_heads).

**Q: ON DELETE NO ACTION vs RESTRICT?**
A: MySQL-de eynidir. PostgreSQL-de NO ACTION DEFERRABLE ola biler (statement sonunda yoxlanir), RESTRICT yox (hemise immediate). Praktikada: standard ucun NO ACTION istifade et (SQL standard), legacy/MySQL-only ucun RESTRICT.
