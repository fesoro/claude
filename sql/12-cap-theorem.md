# CAP Theorem & Consistency Models

## CAP Theorem

Distributed system-de **eyni anda** yalniz 3-den 2-sini garanti ede bilersen:

- **C - Consistency:** Butun node-lar eyni anda eyni data-ni gorur
- **A - Availability:** Her request cavab alir (error olmadan)
- **P - Partition Tolerance:** Network partition olsa bele sistem isleyir

```
        C (Consistency)
       / \
      /   \
    CP     CA
    /       \
   P ---AP--- A
(Partition    (Availability)
 Tolerance)
```

**Muhum:** Network partition **hemishe** bas vere biler, ona gore real-da secim **CP** ve ya **AP** arasindadir.

### CP Systems (Consistency + Partition Tolerance)

Partition olduqda, consistency ucun availability-den imtina edir (cavab vermir/error qaytarir).

**Misallar:**
- MySQL (single-node), PostgreSQL
- MongoDB (default config)
- Redis Cluster (partial)
- HBase, Zookeeper

```
Network partition bas verir:
[Node A] ---X--- [Node B]

CP system:
Node B cavab vermir (error qaytarir) cunki Node A ile sync ola bilmir.
Data consistency qorunur!
```

### AP Systems (Availability + Partition Tolerance)

Partition olduqda, availability ucun consistency-den imtina edir (kohne data vere biler).

**Misallar:**
- Cassandra
- DynamoDB
- CouchDB
- DNS

```
Network partition bas verir:
[Node A] ---X--- [Node B]

AP system:
Node B kohne data ile cavab verir.
Her kes cavab alir, amma data kohne ola biler!
```

---

## Consistency Models

### Strong Consistency

Write olunandan sonra, butun oxumalar yeni deyeri gorur. Sanki tek bir database var.

```
Client 1: WRITE x = 5
Client 2: READ x --> 5 (hemishe!)
```

**MySQL single-node, PostgreSQL** - strong consistency verir.

### Eventual Consistency

Write olunandan sonra, **neyese** butun node-lar yeni deyeri gorecek. Arada kohne deyer gorune biler.

```
Client 1: WRITE x = 5
Client 2: READ x --> 3 (henuz propagate olmayib)
... bir az sonra ...
Client 2: READ x --> 5 (indi gorur)
```

**Misal:** DNS propagation - domain deyisdirsen, butun dunyaya yayilmasi saatlar ceke biler.

**PHP/Laravel-de bu nece gorsenur:**

```php
// Master-e yazirsan
Order::create(['status' => 'paid']);

// Replica-dan oxuyursan (replication lag!)
$order = Order::find(1); // status henuz 'pending' ola biler!

// Hell: sticky connection
// config/database.php: 'sticky' => true
```

### Read-Your-Writes Consistency

Oz yazdiqini gorursen, amma basqalari henuz gormeyebiler.

```php
// Oz yazdiqini gorememe problemi:
$user->update(['name' => 'New Name']); // Master-e yazir
return redirect('/profile'); // Replica-dan oxuyur - kohne ad!

// Hell:
$user->update(['name' => 'New Name']);
return redirect('/profile')->with('flash', 'Updated!');
// Ve ya master-den oxu (sticky)
```

### Monotonic Reads

Bir defe yeni deyeri gordukden sonra, bir daha kohne deyeri gormursen.

```
YANLIS (monotonic read pozulur):
READ x --> 5 (replica A-dan, yeni)
READ x --> 3 (replica B-dan, kohne!)

DOGRU (monotonic reads):
READ x --> 5
READ x --> 5 (ve ya daha yeni, amma 3-e geri donmez)
```

**Hell:** Session affinity - eyni user hemishe eyni replica-dan oxuyur.

---

## PACELC Theorem

CAP-in genislendirilmis versiyasi:

**Partition olduqda:** Availability ve ya Consistency sec (PAC)
**Partition OLMADIQDA:** Latency ve ya Consistency sec (ELC)

| System | P+A/C | E+L/C |
|--------|-------|-------|
| MySQL/PostgreSQL (single) | PC | EC |
| Cassandra | PA | EL |
| MongoDB | PC | EC |
| DynamoDB | PA | EL |

---

## PHP Developer ucun praktik menasi

### 1. Read/Write Splitting ile Eventual Consistency

```php
// config/database.php
'mysql' => [
    'read' => ['host' => 'replica.db.com'],
    'write' => ['host' => 'master.db.com'],
    'sticky' => true, // Read-your-writes consistency
],
```

### 2. Cache Consistency

```php
// Cache write-back problemi
$product = Cache::remember('product:1', 3600, function () {
    return Product::find(1);
});

// Product update olunur
$product->update(['price' => 99.99]);
Cache::forget('product:1'); // Cache temizle

// Race condition: Baskasi cache temizlenmezden evvel oxuya biler
// ve ya cache temizlendikden sonra kohne DB data-ni cache-leye biler (replication lag)
```

### 3. Distributed Lock

```php
// Consistency ucun distributed lock
$lock = Cache::lock('process-order-123', 10); // 10 saniye

if ($lock->get()) {
    try {
        // Yalniz bir process bu kodu icra ede biler
        processOrder(123);
    } finally {
        $lock->release();
    }
}
```

---

## Interview suallari

**Q: CAP theorem-i sade dille izah et.**
A: Distributed system-de network partition (P) qacinilmazdir. Partition olduqda secim etmelisen: ya consistency (butun node-lar eyni data - CP), ya availability (her kes cavab alir, amma kohne data ola biler - AP). Her ikisini eyni anda garanti etmek mumkun deyil.

**Q: MySQL CP-dir yoxsa AP?**
A: Single-node MySQL strong consistency verir (CP anlayisi yoxdur - partition yoxdur). Replication ile CP-dir: master crash olsa, replica promote olunana kimi system unavailable ola biler. Eger availability criticaldirsa, multi-master setup (AP) ola biler, amma conflict hell etmek lazimdir.

**Q: Eventual consistency-ni nece idare edersin?**
A: 1) Sticky sessions (read-your-writes). 2) Critical read-lar ucun master-den oxu. 3) Optimistic locking (version check). 4) Idempotent operations. 5) Conflict resolution strategiyasi (last-write-wins, merge, custom).
