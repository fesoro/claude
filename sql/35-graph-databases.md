# Graph Databases

## Graph Database Nedir?

Data-ni **node-lar** (vertislar) ve **edge-ler** (elaqeler) kimi saxlayir. Relational model JOIN ile elaqe qurur; graph model elaqeni birinci sinif vatandas kimi saxlayir.

**Ne vaxt lazimdir?**
- Social network (kim kimi taniyor, friend-of-friend)
- Recommendation engine (bunu alan bunlari da aldi)
- Fraud detection (supheli emeliyyat zenciri)
- Knowledge graph (Wikipedia kimi elaqeli melumat)
- Access control (kim neye icaze verilir)
- Route planning / shortest path

**Niye SQL yetmir?**

```sql
-- Sual: "Orkhanin dostlarinin dostlari kimdir?" (depth 2)
SELECT DISTINCT f2.name
FROM friendships f1
JOIN users u1 ON f1.user_id = u1.id
JOIN friendships f2_rel ON f1.friend_id = f2_rel.user_id
JOIN users f2 ON f2_rel.friend_id = f2.id
WHERE u1.name = 'Orkhan'
  AND f2.id != u1.id;

-- Depth 3? 4? 5? Her seviyyede yeni JOIN lazimdir
-- 6 seviyye = 6 JOIN = PERFORMANCE FELAKETI

-- Nece dost ardicilligi ile baglidir? (variable depth)
-- SQL-de mumkun deyil (recursive CTE ile cetin ve yavas)
```

```cypher
// Graph-da eyni sual (Neo4j Cypher)
MATCH (orkhan:Person {name: 'Orkhan'})-[:FRIEND*2]->(fof:Person)
WHERE fof <> orkhan
RETURN DISTINCT fof.name

// Depth 5? Sadece reqemi deyis:
MATCH (orkhan:Person {name: 'Orkhan'})-[:FRIEND*1..5]->(connection:Person)
RETURN DISTINCT connection.name

// En qisa yol?
MATCH path = shortestPath(
    (a:Person {name: 'Orkhan'})-[:FRIEND*]-(b:Person {name: 'Elon'})
)
RETURN path
```

## Esas Konseptler

```
Node (Vertex):  Bir entity (User, Product, City)
Edge (Relation): Iki node arasi elaqe (FOLLOWS, BOUGHT, LIVES_IN)
Property:       Node ve ya edge-in xususiyyeti (name, age, since)
Label:          Node-un tipi (Person, Product)

SQL muqayise:
Node   = Row
Edge   = Foreign Key / JOIN table
Label  = Table name
Property = Column
```

### Vizual Misal

```
(Orkhan:Person) ──FRIEND──> (Ali:Person)
      │                          │
      │ BOUGHT                   │ FRIEND
      ↓                          ↓
(iPhone:Product) <──BOUGHT── (Veli:Person)
      │
      │ BELONGS_TO
      ↓
(Electronics:Category)
```

---

## Neo4j

En populyar graph database. Cypher query dili istifade edir.

### Qurasdirilma

```bash
# Docker
docker run -d --name neo4j \
  -p 7474:7474 -p 7687:7687 \
  -e NEO4J_AUTH=neo4j/password123 \
  -v $(pwd)/neo4j-data:/data \
  neo4j:5
# Browser: http://localhost:7474
```

### Cypher Query Dili

```cypher
// NODE yaratma
CREATE (orkhan:Person {name: 'Orkhan', age: 28, city: 'Baku'})
CREATE (ali:Person {name: 'Ali', age: 30, city: 'Istanbul'})
CREATE (veli:Person {name: 'Veli', age: 25, city: 'Baku'})
CREATE (iphone:Product {name: 'iPhone 15', price: 999, category: 'electronics'})
CREATE (macbook:Product {name: 'MacBook Pro', price: 2499, category: 'electronics'})

// EDGE (relationship) yaratma
CREATE (orkhan)-[:FRIEND {since: '2020-01-01'}]->(ali)
CREATE (orkhan)-[:FRIEND {since: '2021-06-15'}]->(veli)
CREATE (ali)-[:FRIEND]->(veli)
CREATE (orkhan)-[:BOUGHT {date: '2024-01-15', quantity: 1}]->(iphone)
CREATE (ali)-[:BOUGHT {date: '2024-02-01', quantity: 1}]->(iphone)
CREATE (veli)-[:BOUGHT {date: '2024-03-10', quantity: 1}]->(macbook)

// MATCH (query) - Pattern matching
// Orkhanin dostlari
MATCH (orkhan:Person {name: 'Orkhan'})-[:FRIEND]->(friend:Person)
RETURN friend.name, friend.city

// Orkhanin aldigi mehsullar
MATCH (orkhan:Person {name: 'Orkhan'})-[:BOUGHT]->(product:Product)
RETURN product.name, product.price

// Dostlarin dostlari (depth 2)
MATCH (orkhan:Person {name: 'Orkhan'})-[:FRIEND*2]->(fof:Person)
WHERE fof.name <> 'Orkhan'
RETURN DISTINCT fof.name

// iPhone alan her kes
MATCH (person:Person)-[:BOUGHT]->(p:Product {name: 'iPhone 15'})
RETURN person.name

// "Bunu alanlar bunlari da aldi" (Recommendation)
MATCH (orkhan:Person {name: 'Orkhan'})-[:BOUGHT]->(p:Product)<-[:BOUGHT]-(other:Person)
MATCH (other)-[:BOUGHT]->(rec:Product)
WHERE NOT (orkhan)-[:BOUGHT]->(rec)
RETURN rec.name, COUNT(other) AS recommended_by
ORDER BY recommended_by DESC
LIMIT 5

// Shortest path
MATCH path = shortestPath(
    (a:Person {name: 'Orkhan'})-[:FRIEND*]-(b:Person {name: 'Veli'})
)
RETURN path, length(path) AS distance

// Aggregation
MATCH (p:Person)-[:BOUGHT]->(prod:Product)
RETURN p.name, COUNT(prod) AS products_bought, SUM(prod.price) AS total_spent
ORDER BY total_spent DESC

// UPDATE
MATCH (orkhan:Person {name: 'Orkhan'})
SET orkhan.city = 'Istanbul', orkhan.updated_at = datetime()

// DELETE (relationship ile birlikde)
MATCH (orkhan:Person {name: 'Orkhan'})-[r:FRIEND]->(ali:Person {name: 'Ali'})
DELETE r

// Node sil (evvelce butun relationship-leri sil)
MATCH (n:Person {name: 'Orkhan'}) DETACH DELETE n
```

### Index ve Constraint

```cypher
// Unique constraint (index avtomatik yaranir)
CREATE CONSTRAINT person_name_unique FOR (p:Person) REQUIRE p.email IS UNIQUE

// Index
CREATE INDEX person_name_idx FOR (p:Person) ON (p.name)
CREATE INDEX product_category_idx FOR (p:Product) ON (p.category)

// Full-text index
CREATE FULLTEXT INDEX product_search FOR (p:Product) ON EACH [p.name, p.description]
CALL db.index.fulltext.queryNodes('product_search', 'iphone pro') YIELD node, score
RETURN node.name, score
```

### PHP ile Neo4j

```php
// composer require laudis/neo4j-php-client

use Laudis\Neo4j\ClientBuilder;

$client = ClientBuilder::create()
    ->withDriver('default', 'bolt://neo4j:password123@localhost:7687')
    ->build();

// Query
$result = $client->run(
    'MATCH (p:Person {name: $name})-[:FRIEND]->(friend:Person)
     RETURN friend.name AS name, friend.city AS city',
    ['name' => 'Orkhan']
);

foreach ($result as $record) {
    echo $record->get('name') . ' - ' . $record->get('city') . "\n";
}

// Recommendation
$recommendations = $client->run('
    MATCH (user:Person {name: $name})-[:BOUGHT]->(p:Product)<-[:BOUGHT]-(other:Person)
    MATCH (other)-[:BOUGHT]->(rec:Product)
    WHERE NOT (user)-[:BOUGHT]->(rec)
    RETURN rec.name AS product, rec.price AS price, COUNT(other) AS score
    ORDER BY score DESC
    LIMIT 5
', ['name' => 'Orkhan']);
```

### Laravel ile Neo4j

```php
// composer require vinelab/neoeloquent (community package)

// config/database.php
'neo4j' => [
    'driver' => 'neo4j',
    'host' => 'localhost',
    'port' => 7687,
    'username' => 'neo4j',
    'password' => 'password123',
],

// Model
use Vinelab\NeoEloquent\Eloquent\Model as NeoEloquent;

class Person extends NeoEloquent
{
    protected $label = 'Person';
    protected $fillable = ['name', 'age', 'city'];

    public function friends()
    {
        return $this->hasMany('App\Person', 'FRIEND');
    }

    public function boughtProducts()
    {
        return $this->hasMany('App\Product', 'BOUGHT');
    }
}

// Istifade
$orkhan = Person::where('name', 'Orkhan')->first();
$friends = $orkhan->friends;

// Daha complex query ucun raw Cypher
$result = DB::connection('neo4j')->run('
    MATCH (p:Person)-[:FRIEND*1..3]->(connection:Person)
    WHERE p.name = $name
    RETURN DISTINCT connection
', ['name' => 'Orkhan']);
```

---

## Graph vs Relational: Ne Vaxt Hangisi?

| Sual | SQL | Graph |
|------|-----|-------|
| User-in sifarisleri? | `JOIN` ✅ Asan | Query ✅ Asan |
| Dostlarin dostlari? (depth 2) | 2 JOIN ✅ OK | `*2` ✅ Asan |
| 6 derece ayriliq? (depth 6) | 6 JOIN ❌ Yavas | `*6` ✅ Suretli |
| Deyisen depth? (1-den N-e) | Recursive CTE 😰 | `*1..N` ✅ |
| En qisa yol? | ❌ Cetin | `shortestPath` ✅ |
| Aggregation/reporting? | ✅ Guclu | ❌ Zeyif |
| ACID transactions? | ✅ | ✅ (Neo4j destekleyir) |
| JOIN 3+ table? | ✅ Asan | ❌ Pattern matching lazim |

### Hybrid Yanasma

```
PostgreSQL: Users, Orders, Products, Payments (OLTP, ACID)
Neo4j:      Social graph, Recommendations (relationship-heavy queries)

Sync: PostgreSQL → CDC → Neo4j (async)
```

## Graph Alqoritmleri (Bilmeli Olduqlariniz)

| Alqoritm | Ne edir | Misal |
|----------|---------|-------|
| **BFS/DFS** | Graph traversal | Dostlarin dostlari |
| **Shortest Path** | En qisa yol | Route planning |
| **PageRank** | Node ehmiyyeti | Google Search ranking |
| **Community Detection** | Qrup tapma | User segmentation |
| **Centrality** | En tesirli node | Influencer tapma |

```cypher
// PageRank (Neo4j GDS plugin)
CALL gds.pageRank.stream('social-graph')
YIELD nodeId, score
RETURN gds.util.asNode(nodeId).name AS name, score
ORDER BY score DESC
LIMIT 10

// Community Detection (Louvain)
CALL gds.louvain.stream('social-graph')
YIELD nodeId, communityId
RETURN gds.util.asNode(nodeId).name, communityId
ORDER BY communityId
```

## Interview Suallari

1. **Graph database ne vaxt lazimdir?**
   - Deeply connected data (social networks, recommendations). Variable-depth traversal. Shortest path. SQL-de 4+ JOIN lazim olan sorgularda.

2. **Graph database-in dezavantajlari?**
   - Aggregation/reporting zeyfdir, columnar scan yoxdur, ekosistem kicikdir, ops team tanimaya biler.

3. **Neo4j-de relationship-in property-si ola bilermi?**
   - Beli. `[:FRIEND {since: '2020-01-01'}]` - edge-lerin oz xususiyyetleri var.

4. **Graph database ACID destekleyirmi?**
   - Neo4j: Beli, full ACID. ArangoDB: Beli. Bezi diger graph DB-ler: yoxdur.

5. **Recommendation engine nece qurulur graph-da?**
   - Collaborative filtering: "Bu mehsulu alan user-lerin aldigi diger mehsullar" pattern-i. `MATCH (user)-[:BOUGHT]->(p)<-[:BOUGHT]-(other)-[:BOUGHT]->(rec)`.
