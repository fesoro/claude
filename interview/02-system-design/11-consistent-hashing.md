# Consistent Hashing (Lead ⭐⭐⭐⭐)

## İcmal
Consistent hashing, distributed sistemlərdə data-nı node-lar arasında bölmək üçün istifadə olunan alqoritmdir. Adi modular hashing-dən fərqli olaraq, node sayı dəyişdikdə minimal data köçü baş verir. Amazon DynamoDB, Apache Cassandra, Memcached, Akamai CDN — hamısı consistent hashing istifadə edir. Bu alqoritmi interview-da "whiteboard-da çəkə bilmək" Senior+ namizəd üçün mütləq bacarıqdır.

## Niyə Vacibdir
Distributed cache, distributed DB, load balancing — hər birinin altında consistent hashing dayanır. Adi `hash(key) % N` node dəyişdikdə bütün key-ləri yenidən map etmək tələb edir (cache stampede, data migration). Consistent hashing yalnız 1/N key-i köçürür. Google, Amazon, Meta bu mövzunu distributed systems interview-larında mütləq soruşur.

## Əsas Anlayışlar

### 1. Klassik Hashing Problemi
```
4 server var: S0, S1, S2, S3
hash(key) % 4 → server index

key "apple"  → hash=100 → 100%4=0 → S0
key "banana" → hash=101 → 101%4=1 → S1
key "cherry" → hash=200 → 200%4=0 → S0

5-ci server əlavə olunur (S4):
hash(key) % 5 → tamamilə fərqli mapping!
"apple"  → 100%5=0 → S0 (OK, şans)
"banana" → 101%5=1 → S1 (OK, şans)
"cherry" → 200%5=0 → S0 (dəyişdi! əvvəl S0 idi... bəli S0)

Amma əksər key-lər dəyişir → cache miss → DB flood → stampede
```

### 2. Consistent Hashing Prinsipi
```
Hash ring: 0 to 2^32 - 1 (dairəvi)

Server-lər ring üzərindəki nöqtələrdir:
S0 → hash("S0") = 100
S1 → hash("S1") = 200
S2 → hash("S2") = 300

Key → hash(key) → ring-də saat yönünde ən yaxın server

"apple"  → hash=50  → S0 (50-dən sonra gələn ilk: S0 at 100)
"banana" → hash=150 → S1 (150-dən sonra gələn ilk: S1 at 200)
"cherry" → hash=250 → S2 (250-dən sonra gələn ilk: S2 at 300)
"date"   → hash=350 → S0 (350-dən sonra... ring-in başına qayıt: S0 at 100)
```

### 3. Server Əlavə Edilməsi
```
Yeni S3 əlavə: hash("S3") = 175

"banana" → 150 → S1 at 200 (dəyişmir, S3=175 > 150, S1 hələ yaxın)

Aaa, wait:
150 → saat yönüne → 175 (S3) → bu daha yaxın!
"banana" → indi S3-ə gedir (dəyişdi)

Yalnız S1-in 100-175 arasındakı key-ləri S3-ə köçür
Digər key-lər dəyişmir!

Köçürülən data: ~1/N (N = server sayı)
```

### 4. Server Silinməsi
```
S1 (at 200) silinir:
S1-in key-ləri → saat yönüne növbəti server: S2 (at 300)
Yalnız S1-in key-ləri dəyişir, digərləri eyni qalır
```

### 5. Virtual Nodes (vNodes)
**Problem:** Az server = qeyri-bərabər paylanma
```
S0 → 100
S1 → 500
S2 → 800

Ring: 0-100: S0 qabagı
      100-500: S1 qabagı  ← çox region
      500-800: S2 qabagı
      800-1000+0-100: S0 qabagı

S1 digerlərindən 4x çox data saxlayır!
```

**Həll: Virtual Nodes**
```
Hər fiziki node bir neçə virtual node-a çevrilir:
S0 → S0_v1 (hash=100), S0_v2 (hash=400), S0_v3 (hash=700)
S1 → S1_v1 (hash=200), S1_v2 (hash=500), S1_v3 (hash=900)
S2 → S2_v1 (hash=300), S2_v2 (hash=600), S2_v3 (hash=1000)

Ring daha bərabər paylanır
Cassandra: 256 vNodes per physical node (default)
Amazon DynamoDB: 100+ vNodes
```

### 6. Consistent Hashing İmplementasiyası
```python
import hashlib
import bisect

class ConsistentHash:
    def __init__(self, nodes=None, replicas=150):
        self.replicas = replicas
        self.ring = {}
        self.sorted_keys = []
        
        for node in (nodes or []):
            self.add_node(node)
    
    def add_node(self, node):
        for i in range(self.replicas):
            virtual_node = f"{node}:vn{i}"
            key = self._hash(virtual_node)
            self.ring[key] = node
            bisect.insort(self.sorted_keys, key)
    
    def remove_node(self, node):
        for i in range(self.replicas):
            virtual_node = f"{node}:vn{i}"
            key = self._hash(virtual_node)
            del self.ring[key]
            self.sorted_keys.remove(key)
    
    def get_node(self, key):
        if not self.ring:
            return None
        hash_key = self._hash(key)
        idx = bisect.bisect(self.sorted_keys, hash_key)
        if idx == len(self.sorted_keys):
            idx = 0
        return self.ring[self.sorted_keys[idx]]
    
    def _hash(self, key):
        return int(hashlib.md5(key.encode()).hexdigest(), 16)

# İstifadə:
ch = ConsistentHash(["node1", "node2", "node3"], replicas=150)
print(ch.get_node("user:1234"))  # → "node2"
ch.add_node("node4")
print(ch.get_node("user:1234"))  # → "node4" (köçdü) ya da "node2" (eyni)
```

### 7. Replication with Consistent Hashing
```
Hər key bir primary + N replica-da saxlanır:
key → primary node
     → saat yönüne növbəti 2 node = replicas

"apple" → S0 (primary), S1 (replica1), S2 (replica2)
```

Cassandra bu strategiyadan istifadə edir:
- replication_factor = 3
- Key → hash → ring → ilk 3 unikal node

### 8. Real-World Usage

**Memcached (distributed cache):**
```
Client-side consistent hashing:
- Client library ring saxlayır
- cache.get("key") → hash → node
- Node down → yalnız o node-un key-ləri miss olur
```

**Cassandra:**
- Partition key → hash → ring → shard
- 256 vNodes per node
- Token range per node

**DynamoDB:**
- Partition key hash → ring → physical partition
- Auto-rebalancing

**Nginx upstream balancing:**
```nginx
upstream backend {
    consistent_hash $request_uri;
    server backend1:8080;
    server backend2:8080;
    server backend3:8080;
}
```

**CDN (Akamai, Cloudflare):**
- URL hash → closest PoP that has the content
- Consistent across all edge servers globally

### 9. Weighted Consistent Hashing
Fərqli kapasiteli server-lər:
```
S0 (16GB RAM): 200 virtual nodes
S1 (32GB RAM): 400 virtual nodes  ← 2x kapasitə → 2x vNode
S2 (64GB RAM): 800 virtual nodes  ← 4x kapasitə → 4x vNode

S2 total data-nın ~57%-ni saxlayır
S1 ~29%-ni
S0 ~14%-ni
```

### 10. Bounded Load Consistent Hashing (Google, 2017)
Problem: Hotspot node (bir node-a çox request gəlir)
```
Normal consistent hashing:
Populyar key → həmişə eyni node

Bounded Load:
- Hər node max load = avg_load × (1 + ε)
- ε = 0.25 → max 25% yüksək load
- Node dolu isə → saat yönüne növbəti node
- Google Maglev load balancer istifadə edir
```

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. Əvvəlcə klassik hashing problemini izah et
2. Consistent hashing ring konseptini whiteboard-da çək
3. Server əlavə/silmə zamanı minimal data köçü nümunəsi göstər
4. Virtual node-un niyə lazım olduğunu izah et
5. Real-world usage-ı qeyd et (Cassandra, Memcached)

### Ümumi Namizəd Səhvləri
- Virtual node-u bilməmək (bərabər paylanma problemi)
- Ring vizualizasiyası olmadan izah etməyə çalışmaq
- Server silinməsinin yalnız 1/N data-ya təsir etdiyini izah etməmək
- Real implementation-ı (sorted array + binary search) bilməmək
- Replication ilə consistent hashing əlaqəsini qeyd etməmək

### Senior vs Architect Fərqi
**Senior**: Alqoritmi whiteboard-da çəkir, virtual node izah edir, Cassandra/DynamoDB ilə əlaqəsini bilir.

**Architect**: Consistent hashing-in operational overhead-ini qiymətləndirir (ring management, rebalancing), weighed vs unweighted seçimini aparır, bounded load hashing kimi advanced variantları bilir, consistent hashing-in failure scenarios-unu analiz edir (split brain, ring maintenance), alternative (Rendezvous hashing, Jump hashing) ilə müqayisə edir.

## Nümunələr

### Tipik Interview Sualı
"Design the sharding mechanism for a distributed cache with 10 nodes. Nodes can be added or removed at runtime."

### Güclü Cavab
```
Distributed cache sharding:

Problem:
- 10 nodes, dynamic (add/remove olur)
- hash(key) % 10 → node dəyişdikdə bütün cache miss

Həll: Consistent Hashing with Virtual Nodes

Ring dizaynı:
- Hash space: 0 to 2^32
- Hər node: 150 virtual nodes (bərabər paylanma)
- Total ring points: 10 × 150 = 1500

Lookup:
- Client: sorted array of ring points
- get("user:123") → hash(key) → binary search → nearest node
- Lookup: O(log n) - binary search

Node əlavə olunması:
- Yeni node 150 virtual point → ring-ə əlavə et
- Yalnız yeni node-un predecessor-larından bir hissə köçür
- ~1/11 = 9% data migration
- Qalan 91% dəyişmir

Node silinməsi:
- Node-un 150 virtual point silir
- O node-un key-ləri → saat yönüne növbəti node
- 9% data moves

Replication (fault tolerance):
- Hər key: primary + 2 replicas
- Replicas = saat yönüne növbəti 2 unikal fiziki node
- Node fail olsa replica serve edir

Client library:
- Consistent hash ring in-memory saxlayır
- Node changes: gossip protocol ilə yayılır
- Stale ring: 1-2s window (acceptable)

Monitoring:
- Ring balance: max_load / avg_load per node
- Imbalance > 30% → vNode rebalancing
- Node health: heartbeat hər 1s
```

### Ring Vizualizasiyası
```
        0
       / \
    S0_v3  S2_v1
   /           \
S2_v3         S0_v1
   \           /
    S1_v2  S1_v1
       \ /
       S2_v2 -- S0_v2 -- S1_v3
```

## Praktik Tapşırıqlar
- Python-da consistent hashing implement edin (virtual nodes ilə)
- 1000 key-i 3 node arasında paylayın, node əlavə edib neçə key köçdüyünü ölçün
- Cassandra ring visualization tool istifadə edin
- Weighted consistent hashing implement edin (fərqli kapasitə)
- Rendezvous hashing-i consistent hashing ilə müqayisə edin

## Əlaqəli Mövzular
- [07-database-sharding.md] — Sharding strategies
- [22-data-partitioning.md] — Partitioning patterns
- [05-caching-strategies.md] — Distributed cache design
- [06-database-selection.md] — Cassandra, DynamoDB
- [03-scalability-fundamentals.md] — Scale fundamentals
