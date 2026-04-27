# Graph Databases (Lead ⭐⭐⭐⭐)

## İcmal
Graph database — məlumatları node-lar (varlıqlar) və edge-lər (əlaqələr) kimi saxlayan database növüdür. Sosial şəbəkə, tövsiyə sistemi, fraud detection, knowledge graph — bunlar üçün SQL recursive query-lər performans baxımından uyğun deyil. Bu mövzu Lead interview-larda ixtisaslaşmış data modelləmə biliyinizi yoxlayır.

## Niyə Vacibdir
"Friend-of-friend", "Ən qısa yol", "Bu tranzaksiyanın fraud olub-olmamasını əlaqəli şəbəkədən yoxla" — bunlar graph-native problemlərdir. SQL-də recursive CTE ilə həll mümkündür, lakin dərinlik artdıqca performans exponential pisləşir. İnterviewer bu sualla sizin "hansı problem üçün graph lazımdır?" qərarını verə bildiyinizi yoxlayır.

## Əsas Anlayışlar

- **Node (Vertex):** Varlıq — User, Product, Account, Device kimi; key-value properties saxlayır
- **Edge (Relationship):** Əlaqə — `FOLLOWS`, `PURCHASED`, `KNOWS`, `TRANSFERRED_TO` kimi; istiqamət + properties var (weight, timestamp, label)
- **Property Graph:** Neo4j modeli — node + edge-lər key-value properties daşıyır. Real-world modeling üçün ən çevik
- **RDF (Resource Description Framework):** Semantic web modeli — subject-predicate-object triple. `Alice → knows → Bob`
- **Cypher:** Neo4j-nin deklarativ sorğu dili — ASCII-art pattern syntax: `(alice)-[:KNOWS]->(bob)`. SQL-ə oxşar intuitivlik
- **SPARQL:** RDF store-ları üçün sorğu dili — W3C standart
- **Index-Free Adjacency:** Hər node öz qonşularına birbaşa pointer saxlayır → traversal O(1). SQL-də JOIN əvəzinə pointer-follow → depth artımı az təsir edir
- **K-hop Queries:** K addım uzaqdakı qonşular. SQL-də k JOIN → exponential, Graph DB-də k pointer-follow → near-linear
- **Shortest Path:** Dijkstra, BFS ilə iki node arasında minimum hop. SQL-də çox çətin
- **Centrality:** Ən çox bağlı node-lar — PageRank (Google-un əsas algoritmi), betweenness centrality
- **Community Detection:** Sıx bağlı qrupları tapmaq — Louvain algoritmi, Label Propagation
- **Neo4j:** Ən məşhur property graph DB — ACID, Causal Clustering (HA), Cypher
- **Amazon Neptune:** Managed graph DB — həm RDF (SPARQL) həm property graph (Gremlin) dəstəkləyir. AWS ekosistemi üçün
- **JanusGraph:** Apache open-source — distributed, HBase/Cassandra/ScyllaDB üzərindən, çox böyük graph-lar üçün
- **Graph vs Relational:** Relationship-heavy queries-də (4+ hop) graph 1000x-10000x sürətli ola bilər; sadə CRUD-da SQL üstündür
- **Fraud Detection use-case:** Eyni device-i, IP-ni, telefon nömrəsini paylaşan user cluster-ları → fraud ring detection
- **Knowledge Graph:** Entities arasındakı semantic əlaqələri modelləmək — Google Knowledge Panel, Wikidata

## Praktik Baxış

**Interview-da yanaşma:**
- "Bu problem graph-a layiqdir?" sorusundan başlayın — hər şey üçün Neo4j lazım deyil
- Use-case: fraud detection, social graph, recommendation — klassik graph problemlərini bilmək
- "SQL-də recursive CTE həllini məncə K-hop performance-la müqayisə edin"

**Follow-up suallar:**
- "SQL-də graph query necə yazılır? Limiti nədir?" — Recursive CTE işləyir, lakin depth artdıqca exponential yavaşlayır
- "Friend-of-friend query üçün Neo4j vs PostgreSQL benchmark edərdinizmi?" — 1K user: SQL OK; 1M user, 4+ hop: Neo4j vacib
- "Graph DB ACID dəstəkləyirmi?" — Neo4j: bəli, ACID; JanusGraph: eventual consistency (Cassandra backend-da)
- "Neo4j-nin scale limitləri nədir?" — Single node: 34B node + edge; Cluster: horizontal shard Fabric ilə
- "pgvector SQL-də graph kimi istifadə olunurmu?" — Semantic similarity graph, lakin semantic search-dir, graph traversal deyil

**Ümumi səhvlər:**
- "Graph DB hər şey üçün daha yaxşıdır" demək — CRUD, transactional data üçün SQL üstündür
- Index-free adjacency-nin niyə sürətli olduğunu izah edəməmək — pointer-follow vs hash lookup fərqi
- Use-case olmadan "Neo4j istifadə edərdim" demək
- "PostgreSQL recursive CTE kifayət edir" demək — user sayı artdıqca işləmir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- "SQL bu problemi həll edə bilər, amma N-hop-da exponential yavaşlayır" demək
- Fraud detection ring pattern-ini Cypher ilə izah etmək
- "Biz əvvəl PostgreSQL recursive CTE istifadə etdik, 100K user-dən sonra Neo4j-ə keçdik" — qərar nöqtəsini bilmək

## Nümunələr

### Tipik Interview Sualı
"LinkedIn kimi platforma üçün '2nd degree connections' (dostun dostu) feature-ini dizayn edin. SQL vs Graph DB seçiminizi əsaslandırın."

### Güclü Cavab
Bu graph-native problemdir. Analiz:

**SQL recursive CTE ilə:** 2nd degree = 2 JOIN. 3rd degree = 3 JOIN. LinkedIn-in "6 degrees of separation" üçün 6 recursive JOIN — exponential data. 100M user network-ündə bu sorğu saatlarla çəkər, ya da timeout olar.

**Neo4j ilə:** `MATCH (me:User {id: $userId})-[:KNOWS*2]->(connection)` — index-free adjacency ilə hər traversal O(1). Depth 2 → depth 6 keçidi relatif cüzi performans azalması ilə olur.

**Qərar nöqtəsi:**
- < 100K user + max 2 hop: PostgreSQL recursive CTE işləyir
- > 100K user ya da > 2 hop: Neo4j ya da graph DB vacib

LinkedIn-in özü öz daxili graph engine-i (Expander) istifadə edir — hər şey in-memory graph kimi saxlanır.

### Kod Nümunəsi
```cypher
// Neo4j Cypher nümunələri

// Node-lar yaratmaq
CREATE (alice:User {id: 1, name: 'Alice', email: 'alice@example.com', city: 'Bakı'})
CREATE (bob:User   {id: 2, name: 'Bob',   email: 'bob@example.com',   city: 'Bakı'})
CREATE (carol:User {id: 3, name: 'Carol', email: 'carol@example.com', city: 'Istanbul'})
CREATE (dave:User  {id: 4, name: 'Dave',  email: 'dave@example.com',  city: 'London'})

// Relationship-lər yaratmaq
CREATE (alice)-[:KNOWS {since: 2020, strength: 'strong'}]->(bob)
CREATE (bob)-[:KNOWS {since: 2021}]->(carol)
CREATE (carol)-[:KNOWS {since: 2022}]->(dave)

// 1st degree connections
MATCH (me:User {id: 1})-[:KNOWS]->(friend)
RETURN friend.name AS name, friend.city AS city

// 2nd degree connections (dostun dostu)
MATCH (me:User {id: 1})-[:KNOWS*2]->(connection)
WHERE NOT (me)-[:KNOWS]->(connection)
  AND me.id <> connection.id
RETURN DISTINCT connection.id, connection.name, connection.city
LIMIT 50

// Variable depth: 1-3 hop arası
MATCH path = (me:User {id: 1})-[:KNOWS*1..3]->(connection)
WHERE me.id <> connection.id
RETURN DISTINCT connection.name,
       min(length(path)) AS min_distance
ORDER BY min_distance

// Shortest path — iki user arasında
MATCH path = shortestPath(
    (alice:User {id: 1})-[:KNOWS*..10]-(target:User {id: 100})
)
RETURN
    length(path) AS degrees_of_separation,
    [n IN nodes(path) | n.name] AS connection_chain

// Mutual friends sayı ilə friend recommendation
MATCH (me:User {id: 1})-[:KNOWS]->(mutual)-[:KNOWS]->(suggestion)
WHERE NOT (me)-[:KNOWS]->(suggestion)
  AND me.id <> suggestion.id
WITH suggestion, COUNT(mutual) AS mutual_count
ORDER BY mutual_count DESC
RETURN suggestion.name, mutual_count
LIMIT 10
```

```cypher
// Fraud Detection patterns

// 1. Eyni device-i paylaşan user-lər
MATCH (u1:User)-[:USED_DEVICE]->(d:Device)<-[:USED_DEVICE]-(u2:User)
WHERE u1.id <> u2.id
RETURN u1.id, u2.id, d.fingerprint, count(*) AS shared_count
ORDER BY shared_count DESC
LIMIT 20

// 2. Fraud ring detection: Para transfer şəbəkəsi
MATCH path = (suspect:User {flagged: true})
    -[:TRANSFERRED_TO*1..4]->
    (target:User)
WHERE all(n IN nodes(path) WHERE n.account_age_days < 90)
RETURN
    [n IN nodes(path) | n.id]       AS user_ids,
    [n IN nodes(path) | n.name]     AS user_names,
    length(path)                    AS chain_length
ORDER BY chain_length

// 3. Clustering: Eyni IP-dən qeydiyyat
MATCH (ip:IPAddress)<-[:REGISTERED_FROM]-(u:User)
WITH ip, collect(u) AS users
WHERE size(users) > 5  -- 5-dən çox user eyni IP-dən
RETURN ip.address, size(users) AS user_count,
       [u IN users | u.id] AS user_ids

// 4. Community fraud cluster (şübhəli şəbəkə)
CALL gds.louvain.stream('fraud-graph')
YIELD nodeId, communityId
WITH communityId, collect(nodeId) AS members
WHERE size(members) > 3
MATCH (u:User) WHERE id(u) IN members AND u.flagged = true
WITH communityId, members, count(u) AS flagged_count
WHERE flagged_count > 0
RETURN communityId, size(members) AS total_members,
       flagged_count
ORDER BY flagged_count DESC
```

```sql
-- PostgreSQL recursive CTE ilə müqayisə
-- Bu yanaşma 10K user-ə qədər işləyir, sonra yavaşlayır

-- 2nd degree connections
WITH RECURSIVE connections AS (
    -- Base case: 1st degree
    SELECT
        target_user_id AS connected_id,
        1              AS depth,
        ARRAY[source_user_id, target_user_id] AS path
    FROM user_connections
    WHERE source_user_id = 1

    UNION ALL

    -- Recursive: növbəti hop
    SELECT
        uc.target_user_id,
        c.depth + 1,
        c.path || uc.target_user_id
    FROM connections c
    JOIN user_connections uc ON uc.source_user_id = c.connected_id
    WHERE c.depth < 2                          -- Max 2 hop
      AND uc.target_user_id <> ALL(c.path)     -- Cycle yoxla
)
SELECT DISTINCT connected_id
FROM connections
WHERE depth = 2
  AND connected_id NOT IN (
      SELECT target_user_id
      FROM user_connections WHERE source_user_id = 1
  )
  AND connected_id <> 1;

-- Benchmark müqayisəsi (approximate):
-- 10K user,   depth 2: PostgreSQL ~50ms,   Neo4j ~5ms
-- 100K user,  depth 2: PostgreSQL ~5s,     Neo4j ~50ms
-- 1M user,    depth 2: PostgreSQL timeout, Neo4j ~500ms
-- 10M user,   depth 3: PostgreSQL timeout, Neo4j ~5s
```

```python
# Neo4j Python driver
from neo4j import GraphDatabase
from typing import List, Dict, Any

class SocialGraphService:
    def __init__(self, uri: str, user: str, password: str):
        self.driver = GraphDatabase.driver(uri, auth=(user, password))

    def get_second_degree_connections(
        self, user_id: int, limit: int = 50
    ) -> List[Dict[str, Any]]:
        with self.driver.session() as session:
            result = session.run("""
                MATCH (me:User {id: $userId})-[:KNOWS*2]->(connection)
                WHERE NOT (me)-[:KNOWS]->(connection)
                  AND me.id <> connection.id
                WITH DISTINCT connection
                MATCH (me:User {id: $userId})-[:KNOWS]->(mutual)-[:KNOWS]->(connection)
                RETURN connection.id   AS id,
                       connection.name AS name,
                       count(mutual)   AS mutual_friends
                ORDER BY mutual_friends DESC
                LIMIT $limit
            """, userId=user_id, limit=limit)
            return [dict(r) for r in result]

    def detect_fraud_rings(
        self, min_chain_length: int = 2
    ) -> List[Dict[str, Any]]:
        with self.driver.session() as session:
            result = session.run("""
                MATCH path = (s:User {flagged: true})
                    -[:TRANSFERRED_TO*2..5]->
                    (t:User)
                WHERE s.id <> t.id
                  AND all(n IN nodes(path)
                          WHERE n.created_days_ago < 60)
                RETURN
                    [n IN nodes(path) | n.id]   AS chain,
                    length(path)                AS depth
                ORDER BY depth DESC
                LIMIT 20
            """)
            return [dict(r) for r in result]

    def recommend_friends(
        self, user_id: int
    ) -> List[Dict[str, Any]]:
        """Mutual friend sayına görə sıralanmış tövsiyələr"""
        with self.driver.session() as session:
            result = session.run("""
                MATCH (me:User {id: $userId})
                    -[:KNOWS]->(mutual)
                    -[:KNOWS]->(suggestion)
                WHERE NOT (me)-[:KNOWS]->(suggestion)
                  AND me.id <> suggestion.id
                  AND suggestion.city = me.city
                WITH suggestion, count(mutual) AS mutual_count
                ORDER BY mutual_count DESC
                RETURN suggestion.id   AS id,
                       suggestion.name AS name,
                       mutual_count
                LIMIT 10
            """, userId=user_id)
            return [dict(r) for r in result]

    def close(self):
        self.driver.close()
```

```php
// Laravel-də Neo4j inteqrasiyası
// composer require laudis/neo4j-php-client

use Laudis\Neo4j\ClientBuilder;

class GraphRepository
{
    private $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->withBoltSchematics('bolt://neo4j:password@localhost:7687')
            ->build();
    }

    public function getRecommendations(int $userId): array
    {
        $result = $this->client->run(
            <<<'CYPHER'
            MATCH (me:User {id: $userId})-[:KNOWS]->(mutual)-[:KNOWS]->(rec)
            WHERE NOT (me)-[:KNOWS]->(rec)
              AND me.id <> rec.id
            WITH rec, count(mutual) AS mutual_friends
            ORDER BY mutual_friends DESC
            RETURN rec.id          AS id,
                   rec.name        AS name,
                   mutual_friends
            LIMIT 10
            CYPHER,
            ['userId' => $userId]
        );

        return $result->map(fn($row) => [
            'id'             => $row->get('id'),
            'name'           => $row->get('name'),
            'mutual_friends' => $row->get('mutual_friends'),
        ])->toArray();
    }

    public function createFriendship(int $userId1, int $userId2): void
    {
        $this->client->run(
            'MATCH (a:User {id: $id1}), (b:User {id: $id2})
             MERGE (a)-[:KNOWS {since: date()}]->(b)
             MERGE (b)-[:KNOWS {since: date()}]->(a)',
            ['id1' => $userId1, 'id2' => $userId2]
        );
    }
}
```

### İkinci Nümunə — PostgreSQL + Neo4j Hybrid

```
Hybrid arxitektura: PostgreSQL + Neo4j

Məsələ: User məlumatları PostgreSQL-dədir, friendship graph Neo4j-dədir.
Hər iki DB-nin güclü tərəfini istifadə edirik.

PostgreSQL:
  - users table (id, email, name, created_at, ...)
  - Transaction-al data (orders, payments)
  - ACID tələbi olan hər şey

Neo4j:
  - User node-ları (yalnız id + display info)
  - KNOWS, FOLLOWS, BLOCKED relationships
  - Recommendation engine, fraud detection

Sync strategiyası:
  - User yarananda: PostgreSQL-ə yaz → Event → Neo4j-ə node yarat
  - Friendship created: Neo4j-ə relationship yarat, PostgreSQL-ə summary yaz
  - Neo4j query result + PostgreSQL join:
    - Neo4j-dən recommendation ID-ləri al
    - PostgreSQL-dən həmin ID-lər üçün full user data çək
    - Application layer-də in-memory join

Laravel nümunəsi:
  1. Neo4j-dən: [user_id: 42, mutual_friends: 7]
  2. PostgreSQL-dən: User::whereIn('id', [42])->get()
  3. Merge et, frontend-ə göndər
```

## Praktik Tapşırıqlar

- Neo4j Desktop qurun, 10,000 fake user + friendship graph yaradın (`apoc.generate.ba` ya da custom seed script), 2nd-degree query çalışdırın
- Eyni query-ni PostgreSQL recursive CTE ilə yazın, 10K vs 100K user-də benchmark edin: `EXPLAIN (ANALYZE, BUFFERS)`
- Fraud ring detection: eyni device-i paylaşan şübhəli user şəbəkəsini tapın, chain-i visualize edin
- Shortest path query-ni iki user arasında çalışdırın, "degrees of separation" tapın
- PageRank algoritmini Neo4j Graph Data Science (GDS) library-si ilə çalışdırın — ən "influential" node-ları tapın
- Hybrid arxitektura qurun: PostgreSQL user data + Neo4j friendship graph, application layer-da merge edin

## Əlaqəli Mövzular
- `17-polyglot-persistence.md` — Graph DB polyglot stack-inin bir hissəsidir
- `01-sql-vs-nosql.md` — Nə zaman xüsusi DB seçmək lazımdır
- `18-time-series-databases.md` — Başqa ixtisaslaşmış DB növü ilə müqayisə
- `20-document-stores.md` — Fərqli NoSQL növü ilə müqayisə
