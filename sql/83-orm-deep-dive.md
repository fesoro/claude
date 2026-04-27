# ORM Dərin Analiz (Doctrine) (Middle)

## Mündəricat
1. [Unit of Work Pattern](#unit-of-work-pattern)
2. [Identity Map Pattern](#identity-map-pattern)
3. [Entity Lifecycle](#entity-lifecycle)
4. [Lazy vs Eager Loading](#lazy-vs-eager-loading)
5. [flush() Daxili İşleyişi](#flush-daxili-işleyişi)
6. [N+1 Problem və ORM Həlləri](#n1-problem-və-orm-həlləri)
7. [PHP İmplementasiyası](#php-implementasiyası)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Unit of Work Pattern

```
Unit of Work — bir "iş vahidi" ərzində bütün dəyişiklikləri izləyir.
flush() çağırıldıqda hamısını bir transaction-da verir.

Ənənəvi yanaşma:              Unit of Work:
$user->setName('Ali');         $user->setName('Ali');
$db->update($user); // ←SQL   $order->setStatus('paid');
$order->setStatus('paid');     $product->setStock(5);
$db->update($order); // ←SQL  $em->flush(); // ← 1 transaction, 3 SQL
$product->setStock(5);
$db->update($product);// ←SQL

UoW faydaları:
  + Az DB round-trip
  + Atomik dəyişikliklər (bir transaction)
  + Performans: eyni entity-ni 10 dəfə dəyişsən 1 UPDATE
  + Order-of-operations idarəsi
```

---

## Identity Map Pattern

```
Identity Map — yüklənmiş entity-ləri ID-ə görə cache-ləyir.
Eyni ID ilə iki sorğu olsa DB-yə getmir, cache-dən qaytarır.

$user1 = $em->find(User::class, 42);  // SQL: SELECT ... WHERE id=42
$user2 = $em->find(User::class, 42);  // SQL yoxdur! Cache-dən

var_dump($user1 === $user2); // true — eyni obyekt!

┌──────────────────────────────────────────────┐
│              Identity Map                    │
│                                              │
│  User::42 → $user (object reference)        │
│  User::17 → $user (object reference)        │
│  Order::5 → $order (object reference)       │
│                                              │
└──────────────────────────────────────────────┘

Əhəmiyyəti:
  - Bir request ərzindəki cache
  - clear() çağırıldıqda silinir
  - Böyük import-larda batch clear lazımdır (memory leak!)
```

---

## Entity Lifecycle

```
Doctrine entity-lərinin 4 halı:

NEW (yeni):
  $user = new User();           // DB-dən xəbərsiz
  // UoW bilmir bu entity-dən

MANAGED (idarə olunan):
  $em->persist($user);          // UoW izləməyə başladı
  $user2 = $em->find(User::class, 1); // find ilə yüklənmiş də managed

DETACHED (ayrılmış):
  $em->detach($user);           // UoW artıq izləmir
  $em->clear();                 // hamısı detach olur

REMOVED (silinəcək):
  $em->remove($user);           // flush()-da DELETE göndəriləcək

                NEW
                 │
         persist()│
                 ▼
             MANAGED ◄──── find()/getReference()
                 │    │
          remove()│    └─── detach()/clear()
                 ▼              │
             REMOVED        DETACHED
                 │              │
          flush()│        merge()│ (deprecated)
                 ▼              ▼
              (deleted)      MANAGED
```

---

## Lazy vs Eager Loading

```
Lazy Loading (default):
  $order = $em->find(Order::class, 1);
  // SQL: SELECT * FROM orders WHERE id=1
  // items hələ yüklənməyib (Proxy object)

  $order->getItems(); // ← BU ANDA SQL atılır!
  // SQL: SELECT * FROM order_items WHERE order_id=1

Eager Loading (JOIN FETCH):
  $orders = $em->createQuery('
      SELECT o, i FROM Order o JOIN FETCH o.items i
  ')->getResult();
  // Bir SQL: SELECT o.*, i.* FROM orders o JOIN order_items i ...

Lazy Loading problemləri:
  foreach ($orders as $order) {
      echo $order->getCustomer()->getName(); // hər order üçün SQL!
  }
  // N+1 problem!

Proxy object:
  Lazy loading üçün Doctrine proxy class yaradır.
  $order->getItems() → Proxy::getItems() → SQL → real data
```

---

## flush() Daxili İşleyişi

```
flush() çağırıldıqda:

1. UoW bütün MANAGED entity-ləri yoxlayır
2. Hər entity üçün "change set" hesablayır (original vs current)
3. Dəyişiklikləri sıralayır (FK asılılıqlarına görə)
4. Transaction başladır
5. INSERT → UPDATE → DELETE qaydası ilə SQL göndərir
6. Transaction commit edir
7. Entity-lərin "original data"-nı yeniləyir

┌─────────────────────────────────────────────┐
│              flush() internals              │
│                                             │
│  computeChangeSets()                        │
│    → hər managed entity-ni yoxla           │
│    → original_data vs current_data          │
│    → fərqli sahələri qeyd et               │
│                                             │
│  executeInserts()  → INSERT sorğuları       │
│  executeUpdates()  → UPDATE sorğuları       │
│  executeDeletes()  → DELETE sorğuları       │
│                                             │
└─────────────────────────────────────────────┘

flush() tez-tez çağırmaq:
  - Hər flush() transaction deməkdir
  - Böyük import-larda hər 100 entity-dən bir flush → batch
```

---

## N+1 Problem və ORM Həlləri

```
Problem:
  $orders = $em->findAll(Order::class);  // 1 SQL (100 order)
  foreach ($orders as $order) {
      echo $order->getCustomer()->getName(); // 100 SQL!
  }
  Cəmi: 101 SQL

Həll 1 — JOIN FETCH:
  SELECT o, c FROM Order o JOIN FETCH o.customer c
  // 1 SQL, bütün məlumat

Həll 2 — addSelect (partial join):
  $qb->select('o', 'c')
     ->join('o.customer', 'c');
  // JOIN FETCH ilə eyni nəticə

Həll 3 — Batch fetch (IN query):
  fetch="EXTRA_LAZY" + batch_size=10
  // 10-lu qruplarla yükləyir: WHERE id IN (1,2,...,10)
  // 100 order → 10 SQL (N/batch_size + 1)

Həll 4 — Separate query ilə preload:
  $customerIds = array_map(fn($o) => $o->getCustomerId(), $orders);
  $customers = $em->findBy(['id' => $customerIds]); // 1 SQL
```

---

## PHP İmplementasiyası

```php
<?php
// Unit of Work — change tracking nümunəsi

/** @Entity */
class Product
{
    /** @Id @GeneratedValue @Column(type="integer") */
    private int $id;

    /** @Column(type="string") */
    private string $name;

    /** @Column(type="integer") */
    private int $stock;
}

// UoW davranışı:
$product = $em->find(Product::class, 1);
// SQL: SELECT * FROM products WHERE id=1
// UoW: original_data = ['name' => 'Laptop', 'stock' => 10]

$product->setName('Gaming Laptop');
$product->setStock(8);
// SQL yoxdur! UoW yalnız izləyir.

$product->setName('Laptop'); // geri qayıtdıq!
// UoW: yalnız stock dəyişdi

$em->flush();
// SQL: UPDATE products SET stock=8 WHERE id=1
// name dəyişmədiyi üçün UPDATE-ə daxil edilmir!
```

```php
<?php
// Identity Map nümunəsi
$user1 = $em->find(User::class, 42);
$user2 = $em->find(User::class, 42);

assert($user1 === $user2); // true — eyni PHP obyekti

// Böyük import-da Identity Map memory problemi:
foreach ($csvRows as $i => $row) {
    $product = new Product($row['name'], $row['price']);
    $em->persist($product);

    if ($i % 100 === 0) {
        $em->flush();
        $em->clear(); // Identity Map-i boşalt! Memory azalır.
    }
}
$em->flush();
```

```php
<?php
// N+1 həlli — JOIN FETCH

// ❌ N+1 problemi
$orders = $em->findAll(Order::class);
foreach ($orders as $order) {
    echo $order->getCustomer()->getName(); // hər dəfə SQL!
}

// ✅ JOIN FETCH həlli
$orders = $em->createQueryBuilder()
    ->select('o', 'c', 'i')
    ->from(Order::class, 'o')
    ->join('o.customer', 'c')
    ->leftJoin('o.items', 'i')
    ->where('o.status = :status')
    ->setParameter('status', 'pending')
    ->getQuery()
    ->getResult();

foreach ($orders as $order) {
    // Artıq SQL atılmır — hamısı yüklənib
    echo $order->getCustomer()->getName();
    echo count($order->getItems());
}
```

```php
<?php
// Lifecycle callbacks
/** @Entity @HasLifecycleCallbacks */
class Order
{
    /** @Column(type="datetime") */
    private \DateTimeImmutable $createdAt;

    /** @Column(type="datetime") */
    private \DateTimeImmutable $updatedAt;

    /** @PrePersist */
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @PreUpdate */
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

---

## İntervyu Sualları

- Unit of Work pattern-i nə üçün lazımdır? `flush()` olmadan nə baş verər?
- Identity Map eyni request-də neçə dəfə DB-yə getməyi necə azaldır?
- Entity `DETACHED` hala düşəndə nə baş verir? Bunu nə zaman istifadə edərsiniz?
- Lazy loading vs Eager loading — hər birini nə vaxt seçərsiniz?
- 100,000 entity import edirsiniz — `flush()` və `clear()` harada çağırarsınız?
- `computeChangeSets()` optimallaşdırması nədir — niyə hər sahəni UPDATE etmir?
- Doctrine proxy class nədir? Lazy loading ilə əlaqəsi?
