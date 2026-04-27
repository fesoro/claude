# SQL vs NoSQL Decision (Middle ⭐⭐)

## İcmal

Bu sual demək olar ki, hər backend interview-da çıxır. Interviewer yalnız fərqləri bilmək istəmir — siz hansı şəraitdə hansı texnologiyanı seçərdiniz, bunu əsaslandırıb izah edə bilirsinizmi? Bu sualın cavabı sizin sistem düşüncənizi ortaya qoyur. "SQL həmişə daha güvənlidir", "NoSQL həmişə daha sürətlidir" kimi ümumiləşdirmələr yanlışdır — cavab həmişə use case-dən asılıdır.

## Niyə Vacibdir

Yanlış database seçimi bir proyektin gələcəyini ciddi şəkildə təsirləyə bilər. İnterviewer bu sualla sizin trade-off düşüncənizi, real layihə təcrübənizi və "default olaraq SQL istifadə edirəm" yanaşmasının əksinə "problem əsasında seçim edirəm" yanaşmanızı yoxlayır. Senior səviyyədə bu sualın cavabı mütləq konkret real-world nümunə ilə dəstəklənməlidir.

## Əsas Anlayışlar

### SQL (Relational Database) Xüsusiyyətləri

- **Structured data**: Əvvəlcədən müəyyən edilmiş schema. Column type-lar, constraint-lər.
- **ACID transactions**: Atomicity, Consistency, Isolation, Durability — data integrity.
- **Fixed schema (schema-on-write)**: Data yazılmadan əvvəl schema uyğunluğu yoxlanır.
- **Powerful JOIN operations**: Multi-table query-lər native dəstəklənir.
- **Strong consistency**: Hər write-dan sonra oxuma ən son datanı verir.
- **SQL query language**: 50+ illik ekosystem, ad-hoc query, reporting imkanı.
- **Vertical scaling**: Güclü server almaq. Horizontal sharding çətindir.
- **Foreign keys**: Referential integrity database səviyyəsindədir.
- **Mature tooling**: pgAdmin, DataGrip, ORM-lər, migration framework-lər.

### NoSQL Növləri

| Tip | Nümunə | Ən yaxşı use case |
|-----|--------|-------------------|
| Document | MongoDB, CouchDB | Flexible schema, nested data, JSON |
| Key-Value | Redis, DynamoDB | Ultra-fast lookup, sessions, caching |
| Column-family | Cassandra, HBase | Time-series, write-heavy, large scale |
| Graph | Neo4j, Amazon Neptune | Relationship-heavy, social networks |
| Search | Elasticsearch | Full-text search, log analytics |

### NoSQL Ümumi Xüsusiyyətlər

- **Schema flexibility (schema-on-read)**: Data istənilən formatda yazılır, oxuyanda parse olunur.
- **Horizontal scaling**: Daha çox server əlavə etmək asandır (sharding native).
- **High write throughput**: Cassandra, DynamoDB — milyonlarca write/second.
- **BASE**: Basically Available, Soft state, Eventually consistent.
- **Eventual consistency**: Write-dan sonra oxuma köhnə data verə bilər (configurable).
- **Limited JOIN**: Əvəzinə denormalization/embedding istifadə olunur.
- **Query limitations**: SQL-ın çevikliyindən azdır.

### ACID vs BASE

| ACID (SQL) | BASE (NoSQL) |
|-----------|-------------|
| Strong consistency | Eventually consistent |
| Write-ləri bloklar | Write-lər dərhal |
| Bank transfer üçün | Analytics üçün |
| PostgreSQL, MySQL | Cassandra, DynamoDB |

### CAP Theorem bağlantısı

- SQL (single node): CA — Consistency + Availability.
- Cassandra: AP — Availability + Partition tolerance.
- HBase, ZooKeeper: CP — Consistency + Partition tolerance.
- Heç bir distributed sistem eyni anda C+A+P vermir.

### Vertical vs Horizontal Scaling

- **Vertical**: Daha böyük server (16GB → 64GB RAM). Limit var, baha.
- **Horizontal**: Daha çox server əlavə et. NoSQL native, SQL çətindir.
- PostgreSQL horizontal: Read replica + sharding (Citus, Vitess). Mümkün amma əlavə mürəkkəblik.

### JOIN Performance

- SQL-da JOIN-lər optimizer tərəfindən idarə olunur. Multi-table query rahat.
- NoSQL-da "JOIN" yoxdur. Alternativlər:
  - **Embedding** (denormalization): Post-un içinə author məlumatını göm.
  - **Application-level JOIN**: 2 ayrı sorğu, application-da birləşdir.
  - Lookup table / reference by ID.

### Index Fərqləri

- SQL: Primary key (cluster), secondary index, composite, partial, full-text.
- NoSQL: Secondary index əlavə overhead (Cassandra-da yazma yavaşlayır).
- DynamoDB: Partition key + sort key; secondary index əlavə xərc.
- MongoDB: Compound index, text index, geospatial.

### Transactions

- SQL: Multi-row, multi-table transaction natural.
- NoSQL: MongoDB 4.0+ multi-document transaction (overhead var). DynamoDB transaction-ı var amma pahalı. Cassandra-da single-partition transaction-ı dəstəklənir.

### Write Throughput

- Cassandra: LSM-tree (Log-Structured Merge Tree) — yazma çox sürətli.
- PostgreSQL: B-tree index — yazma yavaş (index güncəlləmə).
- DynamoDB: Unlimited write-scale (avtomatik sharding).

## Praktik Baxış

### Interview-a Yanaşma

1. **Use case-i soruş**: "Data necə görünür? Read/write ratio nədir? Scale planı nədir?"
2. **SQL-u default seç**, NoSQL-u xüsusi səbəb olduqda əsaslandır.
3. **"NoSQL daha sürətlidir"** kimi ümumiləşdirmədən qaçın.
4. **Polyglot persistence** düşün: Hər component üçün uyğun DB.
5. **Access pattern-ə bax**: NoSQL-da data model access pattern-ə görə qurulur.

### Follow-up Suallar (İnterviewerlər soruşur)

- "PostgreSQL-i scale etmək üçün nə edərdiniz?" — Read replica, connection pool, partitioning, Citus.
- "MongoDB-ni nə vaxt seçərdiniz?" — Flexible schema, document-oriented, embedded data.
- "Hər ikisini birlikdə istifadə etmisizmi?" — Polyglot persistence mövzusuna açılır.
- "Cassandra-nın write-heavy-dəki üstünlüyü nədir?" — LSM-tree vs B-tree izah et.
- "Eventual consistency-ni real layihədə necə idarə etdiniz?"
- "Redis-i primary database kimi istifadə etmək olar mı?" — Persistence, data loss risk.
- "Schema migration NoSQL-da SQL-dan niyə asandır / çətin ola bilər?"

### Common Mistakes

- Yalnız fərqləri saymaq — niyə seçildiyini izah etməmək.
- NoSQL-u "daha yaxşı" kimi təqdim etmək.
- Real layihə nümunəsi verməmək.
- Consistency/availability trade-off-larını qeyd etməmək.
- "Schema yoxdur" demək — schema var, sadəcə application-da manage olunur.
- "NoSQL JOIN-ı dəstəkləmir" — MongoDB-nin `$lookup`-u var, amma tövsiyyə olunmur.

### Yaxşı → Əla Cavab

- **Yaxşı**: Fərqləri sadalayır, nümunə verir.
- **Əla**: Polyglot persistence izah edir, CAP theorem bağlar, real layihə nümunəsi verir, schema migration problemini qeyd edir, access pattern-ə görə data model dizaynını izah edir, trade-off-ları açıqca qeyd edir.

### Real Production Ssenariləri

- Uber: PostgreSQL (trips, drivers) + Redis (geolocation cache) + Cassandra (analytics).
- Netflix: Cassandra (user activity), MySQL (billing), Elasticsearch (search).
- Instagram: PostgreSQL (users, posts), Redis (timeline cache), Cassandra (activity feed).
- Twitter: PostgreSQL (users), Redis (timeline), Manhattan (custom key-value).
- Shopify: MySQL (transactions), Redis (sessions), Elasticsearch (search).

## Nümunələr

### Tipik Interview Sualı

"E-commerce platforması dizayn edirsiniz. User orders, product catalog, user sessions və real-time inventory üçün hansı database-ləri seçərdiniz? Niyə?"

### Güclü Cavab

Bu layihədə mən **polyglot persistence** yanaşması istifadə edərdim:

**Orders** → PostgreSQL: Transaction integrity kritikdir. ACID tam lazımdır. ORDER, ORDER_ITEMS, CUSTOMERS — relational data. Audit trail, reporting üçün SQL.

**Product catalog** → PostgreSQL + Elasticsearch: Catalog structured (price, stock, categories). Full-text search üçün Elasticsearch ilə sinxronizasiya. "Laptop AND price < 500" kimi sorğular.

**User sessions** → Redis: Sessions key-value structure-dur. TTL dəstəyi lazımdır. Ultra-fast read (1ms). Disk-persistent deyil — session itirilsə re-login.

**Real-time inventory** → PostgreSQL (SERIALIZABLE ilə): Overselling problemi üçün strong consistency lazımdır. `SELECT ... FOR UPDATE` ilə row-level lock. Əgər eventual consistency qəbul edilibsə, Redis incr/decr + async sync da düşünülər.

Trade-off: Çoxlu DB = operational complexity, cross-DB consistency problem. Amma hər component üçün optimal tool istifadə olunur.

### Kod Nümunəsi

```sql
-- SQL: Strong consistency, multi-table transaction (Order placement)
BEGIN;
  -- Inventory yoxla və azalt (atomic)
  UPDATE inventory
  SET quantity = quantity - 1
  WHERE product_id = 42 AND quantity > 0
  RETURNING quantity;
  -- Əgər 0 row update → rollback

  INSERT INTO orders (user_id, total_amount, status, created_at)
  VALUES (1, 99.99, 'confirmed', NOW())
  RETURNING id;

  INSERT INTO order_items (order_id, product_id, quantity, unit_price)
  VALUES (currval('orders_id_seq'), 42, 1, 99.99);
COMMIT;
-- Hər şey ya commit, ya rollback — Atomicity

-- Read replica-da analytics sorğusu (eventual consistency OK)
SELECT
    DATE_TRUNC('day', created_at) AS day,
    COUNT(*) AS orders,
    SUM(total_amount) AS revenue
FROM orders
WHERE created_at >= NOW() - INTERVAL '30 days'
GROUP BY 1
ORDER BY 1;
```

```python
# NoSQL (Redis): Session management — O(1) lookup
import redis
import json

r = redis.Redis(host='localhost', port=6379, decode_responses=True)

def create_session(session_id: str, user_data: dict, ttl: int = 3600):
    r.setex(
        f"session:{session_id}",
        ttl,
        json.dumps(user_data)
    )

def get_session(session_id: str) -> dict | None:
    data = r.get(f"session:{session_id}")
    return json.loads(data) if data else None

def refresh_session(session_id: str, ttl: int = 3600):
    r.expire(f"session:{session_id}", ttl)

# Real-time inventory counter
def decrement_inventory(product_id: int, qty: int = 1) -> int:
    """Returns remaining quantity. -1 if out of stock."""
    key = f"inventory:{product_id}"
    result = r.decrby(key, qty)
    if result < 0:
        r.incrby(key, qty)   # rollback
        return -1
    return result
```

```javascript
// NoSQL (MongoDB): Flexible document — product catalog
// Schema-free: fərqli məhsulların fərqli atributları olur
db.products.insertMany([
  {
    _id: "laptop-001",
    name: "Gaming Laptop",
    category: "electronics",
    price: 999,
    specs: { ram: "16GB", cpu: "Intel i7", gpu: "RTX 3060" },
    tags: ["gaming", "laptop"],
    inventory: 50
  },
  {
    _id: "shirt-001",
    name: "Cotton T-Shirt",
    category: "clothing",
    price: 29,
    variants: [
      { size: "S", color: "black", sku: "SHIRT-S-BLK", stock: 100 },
      { size: "M", color: "white", sku: "SHIRT-M-WHT", stock: 75 }
    ]
    // specs yoxdur — schema flexible!
  }
]);

// Nested field query
db.products.find({
  "specs.ram": "16GB",
  price: { $lt: 1000 }
});

// Full-text search (MongoDB text index)
db.products.createIndex({ name: "text", description: "text" });
db.products.find({ $text: { $search: "gaming laptop" } });
```

```yaml
# Cassandra: Time-series data (user activity log)
# Write-heavy, horizontal scale
# Data model: partition key = user_id, sort key = event_time (DESC)
CREATE TABLE user_events (
  user_id    UUID,
  event_time TIMESTAMP,
  event_type TEXT,
  event_data TEXT,
  PRIMARY KEY (user_id, event_time)
) WITH CLUSTERING ORDER BY (event_time DESC);

# Son 100 event — efficient (same partition)
SELECT * FROM user_events
WHERE user_id = ? AND event_time > ?
LIMIT 100;
```

## Praktik Tapşırıqlar

1. Social media platforması üçün database seç: Posts, followers, likes, notifications — hansı data store-u niyə seçərdiniz?
2. PostgreSQL-i horizontal scale etmək üçün 3 üsul sadalayın: Read replica, partitioning, Citus.
3. "SQL vs NoSQL" seçimini yalnız data structure-a görə deyil, **access pattern**-ə görə izah edin.
4. Cassandra-nın write-heavy workload-da niyə üstün olduğunu **LSM-tree vs B-tree** ilə izah edin.
5. Bir layihədə NoSQL seçib sonradan SQL-ə keçmək məcburiyyətindəki scenario-nu simulasiya edin. Hansı problemlər olardı?
6. Redis-i primary database kimi istifadə etmək olarmı? Nə vaxt, nə vaxt olmaz?
7. MongoDB şema migration-u: Collection-da yeni field əlavə et. Köhnə document-lər necə davranır?
8. DynamoDB-nin hot partition problemi nədir? Necə həll olunur?

## Əlaqəli Mövzular

- `03-normalization-denormalization.md` — NoSQL embedding vs SQL normalization.
- `10-database-replication.md` — Hər iki növdə replication strategiyaları.
- `02-acid-properties.md` — ACID vs BASE fərqi.
- `06-transaction-isolation.md` — SQL transaction isolation level-ları.
