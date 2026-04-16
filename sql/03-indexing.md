# Indexing (Indeksleme)

## Index nedir?

Index - database-de axtarisi suretlendiren data strukturudur. Kitabin arxasindaki index kimi - butun kitabi oxumaq yerine, sehife nomresine baxirsan.

Index **olmadan**: Database butun table-i scan edir (Full Table Scan) - O(n)
Index **ile**: Index strukturunda axtaris - O(log n) ve ya O(1)

---

## Index Novleri

### 1. B-Tree Index (Default)

En cox istifade olunan index novu. MySQL ve PostgreSQL-de default.

```sql
CREATE INDEX idx_users_email ON users (email);
```

**B-Tree strukturu:**

```
                    [M]
                   /   \
              [D, H]    [R, X]
             / |  \    / |  \
          [A-C][E-G][I-L][N-Q][S-W][Y-Z]  -- Leaf nodes (actual data pointers)
```

**Ne vaxt isleyir:**
- `=` beraber: `WHERE email = 'john@mail.com'`
- `>`, `<`, `>=`, `<=` muqayise: `WHERE age > 25`
- `BETWEEN`: `WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'`
- `LIKE 'prefix%'`: `WHERE name LIKE 'John%'`
- `ORDER BY`: `ORDER BY created_at DESC`
- `IS NULL` / `IS NOT NULL`

**Ne vaxt ISLEMIR:**
- `LIKE '%suffix'` - baslangic yoxdur, full scan lazimdir
- Function istifade edende: `WHERE YEAR(created_at) = 2024` - index istifade olunmur!

```sql
-- YANLIS (index istifade olunmur)
SELECT * FROM users WHERE YEAR(created_at) = 2024;

-- DOGRU (index istifade olunur)
SELECT * FROM users WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01';
```

### 2. Hash Index

Yalniz `=` muqayise ucun. Range query desteklmir.

```sql
-- MySQL MEMORY engine-de
CREATE INDEX idx_email ON users (email) USING HASH;

-- PostgreSQL
CREATE INDEX idx_email ON users USING HASH (email);
```

**Nece isleyir:**
```
hash('john@mail.com') = 42  -->  Bucket 42  -->  Row pointer
hash('jane@mail.com') = 17  -->  Bucket 17  -->  Row pointer
```

O(1) lookup - amma yalniz exact match ucun.

### 3. Composite (Multi-Column) Index

Bir nece sutun uzerinde index.

```sql
CREATE INDEX idx_orders_status_date ON orders (status, created_at);
```

**Leftmost Prefix Rule** - en muhum qayda!

Index `(A, B, C)` olarsa, bu query-ler index istifade ede biler:
- `WHERE A = ?` ✅
- `WHERE A = ? AND B = ?` ✅
- `WHERE A = ? AND B = ? AND C = ?` ✅
- `WHERE A = ? AND C = ?` ✅ (yalniz A hissesi ucun)
- `WHERE B = ?` ❌ (A yoxdur, index istifade olunmur)
- `WHERE B = ? AND C = ?` ❌
- `WHERE C = ?` ❌

```sql
-- Index: (status, created_at)

-- Isleyir (her iki sutun istifade olunur)
SELECT * FROM orders WHERE status = 'pending' AND created_at > '2024-01-01';

-- Isleyir (yalniz status hissesi)
SELECT * FROM orders WHERE status = 'pending';

-- INDEX ISTIFADE OLUNMUR
SELECT * FROM orders WHERE created_at > '2024-01-01';
```

**Sutun sirasi muhimdur!** Selectivity (nece unique value var) yuksek olan sutunu birinci qoy.

### 4. Covering Index

Query-nin ehtiyac duydugu butun sutunlar index-dedir. Table-a qayidib baxmaga ehtiyac yoxdur.

```sql
-- Index yaradiris
CREATE INDEX idx_orders_cover ON orders (status, created_at, total_amount);

-- Bu query yalniz index-den cavab ala biler (table-a baxmir)
SELECT status, created_at, total_amount 
FROM orders 
WHERE status = 'pending';

-- EXPLAIN-de "Using index" gorursen
```

**MySQL-de INCLUDE (PostgreSQL-de daha guclu):**

```sql
-- PostgreSQL: INCLUDE - index-e elave sutunlar elave edir (sort/search ucun istifade olunmur)
CREATE INDEX idx_orders_cover ON orders (status) INCLUDE (created_at, total_amount);
```

### 5. Partial (Conditional) Index

Yalniz mueyyen condition-a uygun row-lar ucun index.

```sql
-- PostgreSQL
CREATE INDEX idx_active_users ON users (email) WHERE status = 'active';

-- Yalniz active user-lerin email-ini index-leyir
-- Daha kicik index = daha suretli, daha az disk
```

MySQL-de partial index yoxdur, amma workaround var:

```sql
-- MySQL: Generated column + index
ALTER TABLE users ADD COLUMN is_active_email VARCHAR(255) 
    GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN email ELSE NULL END);
CREATE INDEX idx_active_email ON users (is_active_email);
```

### 6. Full-Text Index

Metn axtarisi ucun.

```sql
-- MySQL
CREATE FULLTEXT INDEX idx_ft_articles ON articles (title, body);

SELECT * FROM articles 
WHERE MATCH(title, body) AGAINST('database optimization' IN BOOLEAN MODE);

-- PostgreSQL
CREATE INDEX idx_ft_articles ON articles USING GIN (to_tsvector('english', title || ' ' || body));

SELECT * FROM articles 
WHERE to_tsvector('english', title || ' ' || body) @@ to_tsquery('database & optimization');
```

### 7. Unique Index

Tekrarlanan deyerlere icaze vermir.

```sql
CREATE UNIQUE INDEX idx_users_email ON users (email);

-- Iki user eyni email-le INSERT edilse, error alir
-- PRIMARY KEY de unique index-dir
```

---

## Clustered vs Non-Clustered Index

### Clustered Index
- Data-nin fiziki sirasini mueyyenlesdirir
- Her table-da yalniz **1 eded** ola biler
- MySQL InnoDB-de PRIMARY KEY avtomatik clustered index-dir
- Data leaf node-larda saxlanilir

```
Clustered Index (PRIMARY KEY = id):

        [50]
       /    \
    [20,30]  [70,90]
    /  |  \   / |  \
[10,20][30,40][50,60][70,80][90,100]  -- Actual row data burada!
```

### Non-Clustered (Secondary) Index
- Ayri struktur, data-ya pointer saxlayir
- Her table-da **bir nece** ola biler
- Leaf node-larda PRIMARY KEY deyeri saxlanilir (InnoDB-de)

```
Secondary Index (email):

        [john@]
       /      \
  [anna@,bob@] [mike@,zara@]
  -- Leaf: anna@ -> PK=3, bob@ -> PK=7, ...
  -- Sonra PK ile clustered index-den actual data tapilir (bookmark lookup)
```

**Muhum:** Boyuk PRIMARY KEY (meselen UUID) butun secondary index-leri boyudur!

```sql
-- YANLIS: UUID primary key (36 byte, her secondary index-de tekrarlanir)
CREATE TABLE orders (
    id CHAR(36) PRIMARY KEY,  -- Her secondary index +36 byte per row
    ...
);

-- DOGRU: Auto-increment PK + UUID unique key
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- 8 byte
    uuid CHAR(36) UNIQUE,
    ...
);
```

---

## Index Design Prinsipleri

### 1. WHERE, JOIN, ORDER BY sutunlarini index-le

```sql
-- Bu query ucun:
SELECT o.*, u.name 
FROM orders o
JOIN users u ON u.id = o.user_id
WHERE o.status = 'pending'
ORDER BY o.created_at DESC;

-- Bu index-ler lazimdir:
CREATE INDEX idx_orders_status_date ON orders (status, created_at);
-- users.id artiq PRIMARY KEY-dir
-- orders.user_id foreign key index-i olmalidir
```

### 2. Index-i az saxla

Her index:
- INSERT/UPDATE/DELETE-i yavaslasdirir (index yenilenmeli)
- Disk yeri tutur
- Write-heavy table-larda cox index = ciddi yavaslama

### 3. Cardinality (unique value sayi) muhimdur

```sql
-- YAXSI: email (herkesde ferqli) - yuksek cardinality
CREATE INDEX idx_email ON users (email);

-- PIS: gender (M/F/Other) - asagi cardinality
CREATE INDEX idx_gender ON users (gender);  -- Index-den fayda yoxdur
-- Amma composite index-de ola biler: (gender, created_at)
```

### 4. Index size-i kicik saxla

```sql
-- MySQL: Prefix index (sutunun yalniz ilk N simvolunu index-le)
CREATE INDEX idx_name ON users (name(20));  -- Yalniz ilk 20 simvol

-- Tam metn yerine, daha kicik ve suretli
-- Amma ORDER BY ve GROUP BY-da islemir
```

---

## PHP/Laravel-de Index Yaratma

```php
// Migration-da
Schema::table('orders', function (Blueprint $table) {
    // Sade index
    $table->index('status');
    
    // Composite index
    $table->index(['status', 'created_at']);
    
    // Unique index
    $table->unique('email');
    
    // Index adi ile
    $table->index(['user_id', 'status'], 'idx_orders_user_status');
});
```

---

## Index istifade olunub-olunmadigini yoxla

```sql
-- EXPLAIN ile
EXPLAIN SELECT * FROM orders WHERE status = 'pending';

-- Istifade olunmayan index-leri tap (MySQL)
SELECT * FROM sys.schema_unused_indexes;

-- Index size-lari
SELECT 
    table_name,
    index_name,
    ROUND(stat_value * @@innodb_page_size / 1024 / 1024, 2) AS size_mb
FROM mysql.innodb_index_stats
WHERE stat_name = 'size';
```

---

## Index Alqoritmleri: B-Tree vs LSM-Tree (Derinlemesine)

Senior developer olaraq, index-lerin daxilde **nece islediyini** bilmek lazimdir. Iki esas data strukturu var:

### B-Tree (Balanced Tree) - MySQL, PostgreSQL Default

```
Disk-de saxlanma: Fixed-size PAGE-ler (adeten 4KB-16KB)

              ┌──────────────┐
              │  [30 | 70]   │  ← Root page
              └──┬──┬──┬─────┘
          ┌──────┘  │  └──────┐
     ┌────▼───┐ ┌───▼───┐ ┌──▼─────┐
     │[10|20] │ │[40|50] │ │[80|90] │  ← Internal pages
     └┬──┬──┬┘ └┬──┬──┬┘ └┬──┬──┬─┘
      │  │  │   │  │  │   │  │  │
     Leaf pages (actual row pointers, sorted, linked list)
      ◄──►◄──►◄──►◄──►◄──►◄──►
      (doubly-linked leaf pages → range scan suretli)
```

**Nece isleyir (Search: key=50):**
1. Root page oxu: 50 > 30, 50 < 70 → orta child-a get
2. Internal page oxu: 50 = 50 → tapildi, leaf page-e get
3. Leaf page: row pointer-i al, actual data-ni oxu
4. **Neticə: 3 disk I/O** (agacin hundurluyune beraber)

**Insert zamani ne olur:**
```
1. Dogru leaf page-i tap (search kimi)
2. Page-de yer varsa → daxil et, sorted saxla
3. Page dolubsa → PAGE SPLIT:
   - Page ikiye bolunur
   - Orta key parent-e qalxir
   - Parent de dolubsa → cascade split (nadir)
```

**B-Tree xususiyyetleri:**
- **Read-optimized**: O(log N) - her seviyye 1 disk I/O
- **Write cost**: O(log N) + potential page splits
- **Space**: ~67% page fill factor (splits sebebile)
- **Range scan**: Leaf pages linked list → ardicil oxuma suretli
- **In-place update**: Movcud page-i deyisir (random I/O)

### LSM-Tree (Log-Structured Merge-Tree) - Cassandra, RocksDB, LevelDB

**Write-optimized** struktur. Insert-leri evvelce RAM-da yigir, sonra disk-e yazir.

```
Write path:
                                          Disk
RAM                                       (sorted, immutable)
┌──────────┐    flush     ┌──────────┐
│ MemTable │ ──────────►  │ SSTable  │  Level 0 (kicik)
│ (sorted) │              │  L0-1    │
│          │              ├──────────┤
│  ~64MB   │              │ SSTable  │
└──────────┘              │  L0-2    │
                          └────┬─────┘
                    compact    │
                          ┌────▼─────┐
                          │ SSTable  │  Level 1 (boyuk)
                          │  L1      │
                          └────┬─────┘
                    compact    │
                          ┌────▼─────┐
                          │ SSTable  │  Level 2 (daha boyuk)
                          │  L2      │
                          └──────────┘

SSTable = Sorted String Table (siralanan, degismeyen fayl)
```

**Write (INSERT key=50, value=data):**
1. RAM-daki MemTable-a yaz (Red-Black Tree ve ya Skip List)
2. MemTable dolduqda → disk-e SSTable kimi flush et
3. Arxa planda: SSTable-lari merge et (compaction)
4. **Neticə: 1 RAM write** → Cox suretli!

**Read (Search: key=50):**
1. MemTable-da axtar (RAM, suretli)
2. Tapilmadisa → Level 0 SSTable-larda axtar
3. Tapilmadisa → Level 1, Level 2... (disk)
4. **Worst case: cox disk I/O** (her level-da axtaris)
5. **Optimization: Bloom Filter** - "bu SSTable-da key ola bilermi?" O(1) yoxlama

**LSM-Tree xususiyyetleri:**
- **Write-optimized**: O(1) amortized (RAM-a yaz, batch flush)
- **Read cost**: O(N) worst case (cox SSTable scan) → Bloom Filter ile O(log N)
- **Space**: Cox effektiv (sequential write, yaxsi compression)
- **Range scan**: Her SSTable sorted → merge amma cox SSTable yavasdir
- **Append-only**: Random I/O yoxdur, sequential write → SSD/HDD ucun ideal
- **Write amplification**: Compaction zamani data bir nece defe yeniden yazilir

### B-Tree vs LSM-Tree Muqayise

| Xususiyyet | B-Tree | LSM-Tree |
|------------|--------|----------|
| **Read speed** | Suretli O(log N) | Yavas (cox level) |
| **Write speed** | Orta (page split) | Cox suretli (sequential) |
| **Space** | ~67% fill | Daha effektiv |
| **Range scan** | Suretli (linked pages) | Orta (merge lazim) |
| **Write amplification** | Asagi | Yuksek (compaction) |
| **Istifade** | MySQL, PostgreSQL | Cassandra, RocksDB, LevelDB |
| **Best for** | Read-heavy, OLTP | Write-heavy, IoT, logs |

> **Qayda:** Eger write >> read → LSM-Tree (Cassandra). Eger read >> write → B-Tree (PostgreSQL).

### Diger Index Strukturlari

**GIN (Generalized Inverted Index) - PostgreSQL:**
- Full-text search, JSONB, array ucun
- Inverted index: value → row list
- Yavas write, suretli search

**GiST (Generalized Search Tree) - PostgreSQL:**
- Geometric/spatial data ucun
- R-Tree olaraq istifade olunur (PostGIS)

**BRIN (Block Range Index) - PostgreSQL:**
- Cox boyuk table-lar ucun kicik index
- Her block range ucun min/max saxlayir
- Sorted/append-only data ucun ideal (timestamp)

```sql
-- BRIN: 1 milyar row, yalniz 1MB index!
CREATE INDEX idx_events_time ON events USING BRIN (created_at);
-- Sert: data fiziki olaraq sorted olmalidir (append-only table)
```

---

## Interview suallari

**Q: Niye her sutuna index qoymaq olmaz?**
A: Her index INSERT/UPDATE/DELETE zamani yenilenmeli olur. Coxlu index write performance-i ciddi azaldir. Hemcinin disk space tutur. Yalniz query pattern-lerine uygun index-ler yaradilmalidir.

**Q: Composite index (A, B) ile iki ayri index (A) ve (B) arasinda ferq nedir?**
A: Composite index `WHERE A = ? AND B = ?` query-sini tek bir index scan ile hell edir. Iki ayri index-de database ya Index Merge (yavas) edir, ya da yalniz birini secir. Composite index demek olar ki hemishe daha suretlidir.

**Q: Primary Key ucun INT vs UUID?**
A: INT/BIGINT: kicik (4-8 byte), ardicil, clustered index ucun ideal, amma distributed system-de conflict ola biler.
UUID: unique, distributed-safe, amma boyuk (36 byte), random oldugundan page split yaradir, butun secondary index-leri boyudur.
Kompromis: UUID v7 (zamana gore sirali) ve ya auto-increment PK + UUID unique key.
