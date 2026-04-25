# JOIN Types & Practical Usage (Junior)

**Qeyd:** Bu fayl JOIN **yazma** və praktik istifadəni əhatə edir. JOIN **alqoritmləri** (Nested Loop, Hash Join, Merge Join) və query planner detalları üçün: `31-join-algorithms.md`.

## JOIN nədir?

İki (və daha çox) table-dan row-ları **əlaqələndirir**. SQL-in əsas gücüdür.

```
users (1)  ────→  orders (N)
       one-to-many əlaqə
```

## INNER JOIN - Ən Çox İstifadə Olunan

Yalnız **hər iki table-da** uyğun row olan nəticəni qaytarır.

```sql
-- Sifarişi olan user-lər və sifarişləri
SELECT u.id, u.name, o.order_number, o.total
FROM users u
INNER JOIN orders o ON u.id = o.user_id;

-- users:  [1-Ali, 2-Veli, 3-Orkhan]
-- orders: [user_id=1, user_id=1, user_id=3]
-- Netice: Ali(2 row), Orkhan(1 row). Veli GORUNMEZ (sifarişi yox)

-- `INNER` keyword optional-dir
SELECT u.name, o.total FROM users u JOIN orders o ON u.id = o.user_id;
```

### Niyə alias istifadə et?

```sql
-- BAD - uzun və səhv meydana gətirmə ehtimalı
SELECT users.name, orders.total 
FROM users 
INNER JOIN orders ON users.id = orders.user_id;

-- GOOD - qisa alias-lar
SELECT u.name, o.total 
FROM users u 
INNER JOIN orders o ON u.id = o.user_id;
```

## LEFT JOIN (LEFT OUTER JOIN)

**Sol** table-dan BÜTÜN row-ları qaytarir. Sağda uyğun yoxdursa - NULL.

```sql
-- Bütün user-lər + sifarişləri (sifarişi yoxdursa NULL)
SELECT u.id, u.name, o.order_number
FROM users u
LEFT JOIN orders o ON u.id = o.user_id;

-- users:  [1-Ali, 2-Veli, 3-Orkhan]
-- orders: [user_id=1, user_id=3]
-- Netice: Ali + order, Veli + NULL, Orkhan + order
```

### LEFT JOIN + WHERE pitfall

```sql
-- BAD - WHERE-də sağ table şərtləri LEFT JOIN-u INNER-ə çevirir
SELECT u.id, u.name, o.order_number
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE o.status = 'paid';
-- Sifariş yoxdursa o.status NULL-dir, NULL = 'paid' FALSE - row silinir

-- GOOD - şərti ON-a köçür
SELECT u.id, u.name, o.order_number
FROM users u
LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'paid';
-- İndi user-in paid sifarişi yoxdursa NULL göstərir
```

**Qayda:** LEFT JOIN-da sağ table-in şərtlərini `ON`-a, **sol** table-in şərtlərini `WHERE`-ə yaz.

## RIGHT JOIN (RIGHT OUTER JOIN)

LEFT-in əksi - sağ table-dan bütün row-ları qaytarir.

```sql
SELECT u.name, o.order_number
FROM users u
RIGHT JOIN orders o ON u.id = o.user_id;
-- Bütün sifarişlər + user (user silinmişsə NULL)
```

**Praktika:** RIGHT JOIN az istifadə olunur. Əvəzinə table-ların yerini dəyişib LEFT JOIN yazmaq daha oxunaqlıdır:

```sql
-- Eynidir
SELECT u.name, o.order_number FROM orders o LEFT JOIN users u ON u.id = o.user_id;
```

## FULL OUTER JOIN

Hər iki table-dan **bütün** row-lar. Uyğun yoxdursa qarşı tərəf NULL.

```sql
-- PostgreSQL dəstəkləyir:
SELECT u.name, o.order_number
FROM users u
FULL OUTER JOIN orders o ON u.id = o.user_id;

-- MySQL-də FULL JOIN YOXDUR!
-- Alternativi: UNION of LEFT and RIGHT
SELECT u.name, o.order_number FROM users u LEFT JOIN orders o ON u.id = o.user_id
UNION
SELECT u.name, o.order_number FROM users u RIGHT JOIN orders o ON u.id = o.user_id;
```

**İstifadə:** Rare - hadi iki mənbədən olan məlumatları müqayisə edərkən (məs, data reconciliation).

## CROSS JOIN - Dekart Çarpımı

`N × M` row qaytarir. Şərt **YOX**.

```sql
-- Hər user × hər product kombinasiyası
SELECT u.name, p.name AS product
FROM users u
CROSS JOIN products p;
-- 100 user × 500 product = 50,000 row!

-- Olmadan yazsan da eyni şeydir (implicit cross join - TƏHLÜKƏLİ):
SELECT u.name, p.name FROM users u, products p;
```

**İstifadə:** Test data generation, matrix generation. Reallıqda az istifadə olunur.

## SELF JOIN - Eyni Table-a JOIN

Table özü özü ilə JOIN olunur.

```sql
-- Employees və onların manager-ləri (eyni table-dadır)
SELECT 
    e.name AS employee,
    m.name AS manager
FROM employees e
LEFT JOIN employees m ON e.manager_id = m.id;

-- Friend relationship (hər iki istiqamətdə)
SELECT u1.name AS user, u2.name AS friend
FROM users u1
INNER JOIN friendships f ON f.user_id = u1.id
INNER JOIN users u2 ON u2.id = f.friend_id;
```

**Qayda:** Self join-da alias mutləqdir.

## JOIN Condition (ON vs USING)

```sql
-- ON - ən güclü və flexible
SELECT u.name, o.total
FROM users u JOIN orders o ON u.id = o.user_id;

-- USING - sütun adı hər iki table-da eyni olduqda qisa syntax
SELECT u.name, o.total
FROM users u JOIN orders o USING (user_id);
-- Amma users-də sütun `id`-dir, `user_id` deyil - USING işləməz
-- USING yalnız sütun adı eyni olduqda istifade olunur
```

## Birdən Çox JOIN

```sql
-- Sifariş + user + product + category
SELECT 
    o.id AS order_id,
    u.name AS customer,
    p.name AS product,
    c.name AS category,
    oi.quantity,
    oi.price
FROM orders o
INNER JOIN users u ON u.id = o.user_id
INNER JOIN order_items oi ON oi.order_id = o.id
INNER JOIN products p ON p.id = oi.product_id
INNER JOIN categories c ON c.id = p.category_id
WHERE o.status = 'paid'
  AND o.created_at >= '2026-01-01';
```

**Qayda:** Hər JOIN-u yeni sətirdə yaz. Oxunmağı asanlaşdırır.

## JOIN və N+1 Problem

```php
// BAD - N+1 (ORM-in klassik problemi)
$users = User::all();                          // 1 query
foreach ($users as $user) {
    echo $user->orders->count();              // N query (hər user üçün 1)
}
// Ümumi: 1 + N query

// GOOD - eager loading (JOIN + GROUP BY)
$users = User::withCount('orders')->get();    // 1 query
foreach ($users as $user) {
    echo $user->orders_count;                 // already loaded
}
```

Daha ətraflı: `24-n-plus-one-problem.md`, `57-eloquent-orm-internals.md`.

## Laravel Nümunəsi

```php
// Inner join
$orders = DB::table('orders')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->select('orders.*', 'users.name AS customer')
    ->get();

// Left join
$usersWithOrders = DB::table('users')
    ->leftJoin('orders', 'orders.user_id', '=', 'users.id')
    ->select('users.*', 'orders.order_number')
    ->get();

// Multiple joins
$orderDetails = DB::table('orders')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->join('order_items', 'order_items.order_id', '=', 'orders.id')
    ->join('products', 'products.id', '=', 'order_items.product_id')
    ->select('orders.id', 'users.name', 'products.name AS product')
    ->get();

// Eloquent relationship - ORM JOIN-u eager loading edir
$users = User::with('orders')->get();
$users = User::with(['orders' => function ($q) {
    $q->where('status', 'paid');
}])->get();
```

## Interview Sualları

**Q: `INNER JOIN` və `LEFT JOIN` fərqi?**
A: INNER - yalnız hər iki tərəfdə match olan row-lar. LEFT - sol tərəfdən bütün row-lar, sağda match yoxdursa NULL.

**Q: `LEFT JOIN ... WHERE sağ.status = 'paid'` nə problem yaradır?**
A: WHERE LEFT JOIN-dan sonra işlədiyi üçün NULL-ləri filter edir və LEFT JOIN INNER JOIN-a çevrilir. Həll: şərti ON-a köçür.

**Q: `FULL OUTER JOIN` MySQL-də necə simulate olunur?**
A: `LEFT JOIN UNION RIGHT JOIN` - iki nəticəni birləşdir, UNION duplicate-ləri silir.

**Q: `SELF JOIN` nə vaxt lazım olur?**
A: Hierarchical data (manager-employee), graph-like relations (friend-of-friend), eyni table-daki row-lar arasında müqayisə (iki tarix arasındakı fərq).

**Q: 5 table-li JOIN yazmaq qorxunc deyilmi?**
A: Xeyir, normaldır. Amma: (1) EXPLAIN ilə plan-ı yoxla, (2) JOIN sütunlarında index olduğundan əmin ol, (3) kardinaliteyə diqqət yetir - böyük table-dan filter olunmalıdır ki, kiçik set qala.
