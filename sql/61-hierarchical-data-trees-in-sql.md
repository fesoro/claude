# Hierarchical Data / Trees in SQL

> **Seviyye:** Intermediate ⭐⭐

## Problem

"Kateqoriyaların ağacı", "şirkət iyerarxiyası (manager → employee)", "thread comment-lər (reply-lər)", "file system folder-ləri" — bunların hamısı **tree (ağac) strukturudur**.

Relational database-lər **table** üçün dizayn edilib, ağac üçün yox. Ona görə **4 əsas pattern** inkişaf edib:

| Pattern | Insert | Read sub-tree | Read ancestors | Move | Mürəkkəblik |
|---------|--------|---------------|----------------|------|-------------|
| **Adjacency List** | Asan | Yavaş (N query) | Yavaş | Asan | ⭐ |
| **Path Enumeration** | Asan | Asan (LIKE) | Asan (split) | Orta | ⭐⭐ |
| **Nested Set** | Yavaş (bütün ağac) | Asan (range) | Asan | Yavaş | ⭐⭐⭐ |
| **Closure Table** | Orta (N insert) | Asan | Asan | Orta | ⭐⭐⭐ |
| **Recursive CTE** | Adjacency-a tətbiq edilir | Yaxşı | Yaxşı | Asan | ⭐⭐ |

---

## 1. Adjacency List (ən sadə, ən geniş istifadə olunan)

Hər row öz parent-ini saxlayır.

```sql
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    parent_id INTEGER REFERENCES categories(id)
);

INSERT INTO categories (id, name, parent_id) VALUES
    (1, 'Electronics', NULL),
    (2, 'Laptops', 1),
    (3, 'Gaming Laptops', 2),
    (4, 'Office Laptops', 2),
    (5, 'Phones', 1),
    (6, 'iPhones', 5);
```

### Problem: sub-tree oxumaq N+1 query

```sql
-- Electronics və bütün alt kateqoriyalar?
SELECT * FROM categories WHERE id = 1;          -- Electronics
SELECT * FROM categories WHERE parent_id = 1;   -- Laptops, Phones
SELECT * FROM categories WHERE parent_id = 2;   -- Gaming, Office
-- ... hər səviyyə üçün 1 query
```

### Həll: Recursive CTE (modern yol)

```sql
-- 1 query ilə bütün sub-tree
WITH RECURSIVE descendants AS (
    SELECT id, name, parent_id, 0 AS depth, name::TEXT AS path
    FROM categories WHERE id = 1       -- kök nöqtəsi
    
    UNION ALL
    
    SELECT c.id, c.name, c.parent_id, d.depth + 1, d.path || ' > ' || c.name
    FROM categories c
    JOIN descendants d ON c.parent_id = d.id
)
SELECT * FROM descendants ORDER BY path;

-- id | name            | depth | path
-- 1  | Electronics     | 0     | Electronics
-- 2  | Laptops         | 1     | Electronics > Laptops
-- 3  | Gaming Laptops  | 2     | Electronics > Laptops > Gaming Laptops
-- 4  | Office Laptops  | 2     | Electronics > Laptops > Office Laptops
-- 5  | Phones          | 1     | Electronics > Phones
-- 6  | iPhones         | 2     | Electronics > Phones > iPhones
```

### Ancestors (yuxarı gedən yol)

```sql
-- iPhones-un bütün ancestors-ı (ata, baba, ...)
WITH RECURSIVE ancestors AS (
    SELECT id, name, parent_id, 0 AS up
    FROM categories WHERE id = 6
    
    UNION ALL
    
    SELECT c.id, c.name, c.parent_id, a.up + 1
    FROM categories c
    JOIN ancestors a ON a.parent_id = c.id
)
SELECT * FROM ancestors;

-- 6 | iPhones     | 5    | 0
-- 5 | Phones      | 1    | 1
-- 1 | Electronics | NULL | 2
```

### Laravel-də Adjacency List

```php
class Category extends Model
{
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Recursive children (N+1!)
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }
}

// N+1 təhlükəsi:
$root = Category::find(1);
foreach ($root->children as $child) {
    foreach ($child->children as $grandchild) {
        // ...
    }
}
// Həll: Closure table və ya material path
```

**Adjacency List nə zaman istifadə et:** 2-3 səviyyəli kiçik ağaclar, seyrək dəyişiklik, yalnız birbaşa parent/child lazım olanda.

---

## 2. Path Enumeration (Materialized Path)

Hər row öz yolunu string kimi saxlayır: `/1/2/3`.

```sql
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    path VARCHAR(500)   -- /1/2/3 şəklində
);

INSERT INTO categories VALUES
    (1, 'Electronics',    '/1'),
    (2, 'Laptops',        '/1/2'),
    (3, 'Gaming Laptops', '/1/2/3'),
    (4, 'Office Laptops', '/1/2/4'),
    (5, 'Phones',         '/1/5'),
    (6, 'iPhones',        '/1/5/6');
```

### Sorğular

```sql
-- Electronics-in bütün descendants-ı (alt elementləri)
SELECT * FROM categories WHERE path LIKE '/1/%';

-- iPhones-un ancestors-ı
-- path = '/1/5/6' → parse et
SELECT * FROM categories WHERE '/1/5/6' LIKE path || '%';

-- Depth
SELECT *, LENGTH(path) - LENGTH(REPLACE(path, '/', '')) AS depth 
FROM categories;

-- 2-ci səviyyəli element-lər
SELECT * FROM categories 
WHERE LENGTH(path) - LENGTH(REPLACE(path, '/', '')) = 2;
```

### İndex

```sql
-- LIKE 'prefix%' index istifadə edir
CREATE INDEX idx_path ON categories (path);

-- PostgreSQL ltree extension (daha güclü):
CREATE EXTENSION ltree;
ALTER TABLE categories ADD COLUMN tpath LTREE;
UPDATE categories SET tpath = '1.2.3' WHERE id = 3;
CREATE INDEX idx_tpath ON categories USING GIST (tpath);

-- Operator-lar
SELECT * FROM categories WHERE tpath <@ '1.2';      -- Descendants
SELECT * FROM categories WHERE tpath @> '1.2.3';    -- Ancestors
SELECT * FROM categories WHERE tpath ~ '1.*.3';     -- Pattern match
```

### Move operation

```sql
-- Laptops-u Electronics-dan Computers-ə köçür
-- Köhnə: /1/2  Yeni: /7/2
UPDATE categories 
SET path = REPLACE(path, '/1/2', '/7/2')
WHERE path LIKE '/1/2%';
```

**Path Enumeration nə zaman istifadə et:** Oxuma-heavy, FS-vari strukturlar, breadcrumb lazım olanda. PostgreSQL-də `ltree` extension istifadə et.

---

## 3. Nested Set Model

Hər node-un `lft` (left) və `rgt` (right) qiyməti var. Sub-tree = `WHERE lft BETWEEN p.lft AND p.rgt`.

```
Electronics (1, 12)
├── Laptops (2, 7)
│   ├── Gaming (3, 4)
│   └── Office (5, 6)
└── Phones (8, 11)
    └── iPhones (9, 10)
```

```sql
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    lft INTEGER NOT NULL,
    rgt INTEGER NOT NULL,
    CHECK (lft < rgt)
);

INSERT INTO categories (id, name, lft, rgt) VALUES
    (1, 'Electronics',    1, 12),
    (2, 'Laptops',        2, 7),
    (3, 'Gaming Laptops', 3, 4),
    (4, 'Office Laptops', 5, 6),
    (5, 'Phones',         8, 11),
    (6, 'iPhones',        9, 10);
```

### Sorğular (çox sürətli!)

```sql
-- Electronics-in bütün descendants-ı
SELECT * FROM categories 
WHERE lft BETWEEN 1 AND 12 AND id != 1;

-- iPhones-un ancestors-ı
-- Hər node çərçivəsi iPhones-un çərçivəsini (9, 10) əhatə edir
SELECT * FROM categories 
WHERE lft < 9 AND rgt > 10;

-- Depth
SELECT c1.name, COUNT(c2.id) - 1 AS depth
FROM categories c1
JOIN categories c2 ON c1.lft BETWEEN c2.lft AND c2.rgt
GROUP BY c1.id, c1.name
ORDER BY c1.lft;

-- Birbaşa children (depth = 1)
-- Kompleks — nested set-də birbaşa children-i tapmaq asan deyil
```

### Problem: Insert/Delete çox bahalıdır

```sql
-- Yeni kateqoriya Electronics altına əlavə et:
-- Bütün right qiymətləri yenilənməlidir (çox row)
BEGIN;
UPDATE categories SET rgt = rgt + 2 WHERE rgt >= 12;
UPDATE categories SET lft = lft + 2 WHERE lft > 12;
INSERT INTO categories (name, lft, rgt) VALUES ('Tablets', 12, 13);
COMMIT;
```

**Problem:** Təzə bir kateqoriya = **bütün sağdaki node-lar update**. Çox dəyişən ağac üçün çox pisdir.

**Nested Set nə zaman istifadə et:** **Static taxonomies** (nadir dəyişən kateqoriya ağacı), oxu sürəti kritikdirsə, menu, website navigation.

---

## 4. Closure Table (ən təmiz, ən çevik)

Hər ancestor-descendant cütü ayrı table-da saxlanılır.

```sql
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100)
);

CREATE TABLE category_closure (
    ancestor_id   INTEGER REFERENCES categories(id),
    descendant_id INTEGER REFERENCES categories(id),
    depth         INTEGER NOT NULL,
    PRIMARY KEY (ancestor_id, descendant_id)
);

-- Self-reference də daxil (depth = 0)
INSERT INTO category_closure VALUES
    -- Electronics (1)
    (1, 1, 0),
    -- Laptops (2) -- child of Electronics
    (2, 2, 0), (1, 2, 1),
    -- Gaming Laptops (3) -- child of Laptops, grandchild of Electronics
    (3, 3, 0), (2, 3, 1), (1, 3, 2);
```

### Sorğular

```sql
-- Bütün descendants (Electronics = 1-in altındakılar)
SELECT c.* FROM categories c
JOIN category_closure cc ON cc.descendant_id = c.id
WHERE cc.ancestor_id = 1;

-- Bütün ancestors (iPhones = 6-nın yuxarısı)
SELECT c.* FROM categories c
JOIN category_closure cc ON cc.ancestor_id = c.id
WHERE cc.descendant_id = 6
ORDER BY cc.depth;

-- Birbaşa children (depth = 1)
SELECT c.* FROM categories c
JOIN category_closure cc ON cc.descendant_id = c.id
WHERE cc.ancestor_id = 1 AND cc.depth = 1;

-- Belirli bir depth-də
SELECT c.* FROM categories c
JOIN category_closure cc ON cc.descendant_id = c.id
WHERE cc.ancestor_id = 1 AND cc.depth BETWEEN 2 AND 3;
```

### Insert — closure entry-ləri generate et

```sql
-- Yeni child əlavə et (parent_id, child_id)
INSERT INTO categories (id, name) VALUES (7, 'Tablets');

-- Closure: bütün parent-in ancestors-ı + yeni node
INSERT INTO category_closure (ancestor_id, descendant_id, depth)
SELECT ancestor_id, 7, depth + 1
FROM category_closure
WHERE descendant_id = 1      -- yeni parent
UNION ALL
SELECT 7, 7, 0;              -- self-reference
```

### Delete

```sql
-- Sub-tree-ni tamamilə sil
DELETE FROM categories
WHERE id IN (
    SELECT descendant_id FROM category_closure WHERE ancestor_id = 2
);
-- CASCADE FK-lar avtomatik closure-ı təmizləyir
```

### Move sub-tree

```sql
-- Laptops (2) və altındakıları Computers (10) altına köçür
BEGIN;

-- 1. Köhnə ancestor əlaqələrini sil (yalnız xarici olanlar)
DELETE FROM category_closure
WHERE descendant_id IN (
    SELECT descendant_id FROM category_closure WHERE ancestor_id = 2
)
AND ancestor_id IN (
    SELECT ancestor_id FROM category_closure WHERE descendant_id = 2 AND depth > 0
);

-- 2. Yeni ancestor əlaqələri əlavə et
INSERT INTO category_closure (ancestor_id, descendant_id, depth)
SELECT supertree.ancestor_id, subtree.descendant_id,
       supertree.depth + subtree.depth + 1
FROM category_closure supertree
CROSS JOIN category_closure subtree
WHERE supertree.descendant_id = 10   -- yeni parent
  AND subtree.ancestor_id = 2;       -- köçürüləcək node

COMMIT;
```

### Laravel: staudenmeir/laravel-adjacency-list

```php
// composer require staudenmeir/laravel-adjacency-list

use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class Category extends Model
{
    use HasRecursiveRelationships;
}

// Bütün descendants (recursive CTE istifadə edir)
$descendants = Category::find(1)->descendants;

// Ancestors
$ancestors = Category::find(6)->ancestors;

// Tree with depth
$tree = Category::tree()->get()->toTree();
```

**Closure Table nə zaman istifadə et:** Mürəkkəb tree operations (move, ancestor queries, depth queries). Ən çevik, amma 2 table + əlavə storage.

---

## PostgreSQL: `WITH RECURSIVE` deep

```sql
-- Dərinlik limiti ilə (infinite loop qorunması)
WITH RECURSIVE tree AS (
    SELECT id, name, parent_id, 0 AS depth
    FROM categories WHERE id = 1
    
    UNION ALL
    
    SELECT c.id, c.name, c.parent_id, t.depth + 1
    FROM categories c
    JOIN tree t ON c.parent_id = t.id
    WHERE t.depth < 10   -- max 10 səviyyə
)
SELECT * FROM tree;

-- Dövr (cycle) aşkarlama
WITH RECURSIVE tree AS (
    SELECT id, name, parent_id, ARRAY[id] AS path, FALSE AS cycle
    FROM categories WHERE parent_id IS NULL
    
    UNION ALL
    
    SELECT c.id, c.name, c.parent_id, path || c.id, c.id = ANY(path)
    FROM categories c
    JOIN tree t ON c.parent_id = t.id
    WHERE NOT cycle
)
SELECT * FROM tree WHERE cycle = TRUE;  -- dövrlü node-lar
```

MySQL 8.0+ də `WITH RECURSIVE` dəstəkləyir.

---

## Performance müqayisəsi (1M node ağacı)

| Əməliyyat | Adjacency + CTE | Path Enum | Nested Set | Closure Table |
|-----------|-----------------|-----------|------------|---------------|
| Insert | O(1) | O(1) | O(n) ! | O(depth) |
| Read sub-tree | O(depth) CTE | O(k) LIKE | O(k) range | O(k) JOIN |
| Read ancestors | O(depth) | O(1) | O(depth) | O(depth) |
| Move sub-tree | O(k) | O(k) | O(n) ! | O(k × depth) |
| Storage overhead | 0 | ~50 byte/node | 16 byte | O(n × avg_depth) |

**Ümumi qayda:**
- Kiçik / orta ağac (< 100k node), çox dəyişir → **Adjacency + recursive CTE**
- Tez-tez tree-operations, statik → **Closure Table**
- URL/file path simulasiyası → **Path Enumeration (ltree)**
- Static menu/catalog, oxu-only → **Nested Set**

---

## Real-world misalları

### Comment thread (Reddit style)

```sql
-- Closure table + ranking
CREATE TABLE comments (
    id BIGINT PRIMARY KEY,
    post_id BIGINT,
    author_id BIGINT,
    body TEXT,
    score INT DEFAULT 0,
    created_at TIMESTAMPTZ
);

CREATE TABLE comment_paths (
    ancestor_id BIGINT,
    descendant_id BIGINT,
    depth INT,
    PRIMARY KEY (ancestor_id, descendant_id)
);

-- Thread-i depth-limitli al
SELECT c.*, cp.depth
FROM comments c
JOIN comment_paths cp ON cp.descendant_id = c.id
WHERE cp.ancestor_id = 123   -- root comment
  AND cp.depth <= 5          -- nə qədər dərin göstər
ORDER BY cp.depth, c.score DESC;
```

### Organization hierarchy

```sql
-- "Mənim rəhbərim kimdir?" "Mənim altımda kim işləyir?"
CREATE TABLE employees (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100),
    manager_id INTEGER REFERENCES employees(id)
);

-- John-un altındakı bütün işçilər (recursive)
WITH RECURSIVE subordinates AS (
    SELECT id, name, manager_id, 0 AS level
    FROM employees WHERE name = 'John'
    
    UNION ALL
    
    SELECT e.id, e.name, e.manager_id, s.level + 1
    FROM employees e
    JOIN subordinates s ON e.manager_id = s.id
)
SELECT * FROM subordinates;
```

### File system (nested folder-lər)

```sql
-- ltree ilə
CREATE TABLE files (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    path LTREE
);

-- Bütün .pdf-lər documents-də və alt folder-lərdə
SELECT * FROM files 
WHERE path <@ 'root.documents' 
  AND name LIKE '%.pdf';
```

---

## Interview sualları

**Q: Kateqoriya ağacı üçün hansı pattern istifadə edərdin?**
A: Default olaraq **adjacency list + recursive CTE** — sadə schema, asan CRUD, modern DB-lərdə yaxşı performans. Əgər **tez-tez sub-tree operations** (move, batch query) lazımdırsa, closure table-a keç. **Static menu** üçün nested set sürətli. URL-vari path üçün PG-də `ltree`.

**Q: Niyə nested set model müasir layihələrdə çox işlənmir?**
A: **Write amplification** — yeni bir node əlavə etmək üçün ağacın ortasında bütün `lft/rgt` qiymətlərini yeniləmək lazımdır. 100k node-lu ağacda bir insert 50k row-luq UPDATE edə bilər. Həmçinin concurrent insert-lər lock contention yaradır.

**Q: Closure table-ın əsas dezavantajı?**
A: **Storage overhead** — N node və orta depth D-də, closure table-da təxminən **N × D row** olur. Balanced ağacda (N=100k, D=10) bu 1M row deməkdir. Yalnız tree-heavy layihələrdə dəyərli.

**Q: Recursive CTE-də infinite loop problemi necə həll olunur?**
A: 1) **Depth limit**: `WHERE depth < 20`. 2) **Cycle detection** (PG `CYCLE` clause, MySQL ARRAY path tracking): `node_id = ANY(path)`. 3) Application layer-də `max_recursion_depth` parametr. PostgreSQL-də `RECURSIVE ... SEARCH DEPTH FIRST BY id SET depth` və `CYCLE id SET is_cycle` sintaksisi var.

**Q: Laravel Eloquent-də tree göstərmək üçün nə istifadə edərdin?**
A: Kiçik ağaclar üçün **`with('children.children.children')`** (sabit depth) kifayətdir. Dinamik depth üçün **`staudenmeir/laravel-adjacency-list`** paketi recursive CTE generate edir (1 query). Closure table üçün **`kalnoy/nestedset`** (əslində nested set implement edir). Böyük thread-lər üçün **comment_paths** table manual yanaşma ilə.
