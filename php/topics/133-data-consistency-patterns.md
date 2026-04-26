# Data Consistency Patterns (Senior)

## Mündəricat
1. Read-your-writes consistency
2. Monotonic reads
3. Causal consistency
4. Eventual consistency patterns
5. PHP İmplementasiyası
6. İntervyu Sualları

---

## Read-your-writes Consistency

İstifadəçi öz yazdığı datanı həmişə oxuya bilməlidir. Replica lag buna mane ola bilər:

```
Problem:
  t=0: User POST /profile { name: "Ali" }  → Primary
  t=1: User GET  /profile                  → Replica (lag: 2s)
  t=1: Response: { name: "Əli" }  ← Köhnə data!

İstifadəçi: "Mən az öncə adımı dəyişdim, niyə köhnə görünür?"
```

**Həll strategiyaları:**

```
1. Primary-dən oxu (write sonrası):
   Write → Primary
   Sonrakı read (N saniyə) → Primary
   N saniyə keçəndən sonra → Replica

2. Monotonic timestamp:
   Write zamanı timestamp al
   Read: bu timestamp-dən yeni replica-dan oxu

3. Session-based routing:
   User öz məlumatını dəyişdirdisə → Primary
   Başqasının məlumatını oxuyursa → Replica
```

---

## Monotonic Reads

Əgər istifadəçi bir dəfə datanı gördüsə, sonrakı oxumalar daha köhnə data göstərməməlidir:

```
Problem (Non-monotonic):
  t=0: Read from Replica 1 → post={likes: 150}
  t=1: Read from Replica 2 → post={likes: 120}  ← geriyə getdi!

İstifadəçi: "Az əvvəl 150 like var idi, indi 120?"
```

```
Monotonic read zəmanəti:
  t=0: Read → Replica 1 → {likes: 150, version: 42}
  t=1: Read → Replica 2 → "version 42 görürsünüzmü?" → "YOX"
            → Replica 1-ə yönləndir (version var)
            → {likes: 155, version: 45}  ✅ (yenidən azalmadı)
```

**Həll:** İstifadəçini həmişə eyni replica-ya yönləndir (sticky session / consistent hashing).

---

## Causal Consistency

Əlaqəli əməliyyatlar doğru sırada görünməlidir:

```
Problem:
  Alice: "Bu şəkil çox gözəldir!" (comment)  → Replica A
  Bob (0.1s sonra): şəkili sil               → Primary
  
  Charlie reads from Replica B:
    → şəkil yoxdur
    → Alice-nin kommentini görür: "Bu şəkil çox gözəldir!"
    → Kontekst yoxdur, comment mənasızdır

Causal consistency ilə:
  Charlie-yə ya hər ikisi göstərilir, ya da heç biri
```

**Vector clocks ilə causal tracking:**

```
Initial: A={}, B={}, C={}

Alice writes (comment): A={A:1}
Bob writes (delete):    B={A:1, B:1}  ← Alice-nin yazısını gördü

Charlie reads: 
  Replica müqayisəsi: {A:1} vs {A:1, B:1}
  B → A-dan sonra gəlir → əvvəlcə A (comment), sonra B (delete) göstər
```

---

## Eventual Consistency Patterns

### Read Repair

Oxuma zamanı replica-lar arasındakı fərqlər düzəldilir:

```
Client → Read from Replica 1 → {version: 5}
Client → Read from Replica 2 → {version: 3}  ← stale

Coordinator:
  "Replica 2-ni yenilə: version 5 göndər"
  Replica 2 yenilənir

Client → {version: 5}  ✅
```

### Hinted Handoff

Node müvəqqəti əlçatmaz olduqda yazı başqa node-da saxlanır:

```
Write → Node A (əlçatmaz!)
      → Node B: "A əlçatmaz, bu yazını saxlayıram"
                "A qayıdanda göndərəcəyəm" (hint)

Node A qayıdır:
Node B → Node A: "Sənin üçün saxladığım yazılar var"
```

### Anti-Entropy (Background Sync)

Arxa planda node-lar məlumatlarını müqayisə edib sinxronizasiya edir:

```
Cron / background job:
  Node A ←→ Node B: Merkle tree müqayisəsi
  Fərq tapılır → sinxronizasiya
  
Cassandra bunu repair əmri ilə edir:
  nodetool repair
```

---

## PHP İmplementasiyası

```php
<?php

/**
 * Read-your-writes: Yazıdan sonra primary-dən oxu
 */
class ReadYourWritesRepository
{
    private PDO $primary;
    private PDO $replica;
    private Redis $redis;

    private const PRIMARY_READ_TTL = 10; // saniyə

    public function update(int $userId, array $data): void
    {
        $stmt = $this->primary->prepare(
            'UPDATE users SET name = :name, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':name' => $data['name'], ':id' => $userId]);

        // Bu user üçün növbəti N saniyə primary-dən oxunacaq
        $this->redis->setex(
            "read_primary:{$userId}",
            self::PRIMARY_READ_TTL,
            '1'
        );
    }

    public function find(int $userId): ?array
    {
        // Son yazmadan bəri N saniyə keçməyibsə primary-dən oxu
        if ($this->redis->exists("read_primary:{$userId}")) {
            $db = $this->primary;
            $source = 'primary';
        } else {
            $db = $this->replica;
            $source = 'replica';
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? ($result + ['_source' => $source]) : null;
    }
}

/**
 * Monotonic reads: İstifadəçini eyni replica-ya yönləndir
 */
class MonotonicReadRouter
{
    private array $replicas; // PDO array
    private Redis $redis;
    private int $stickyTtl = 300; // 5 dəqiqə eyni replica

    public function getConnectionForUser(int $userId): PDO
    {
        $stickyKey = "sticky_replica:{$userId}";
        $replicaId = $this->redis->get($stickyKey);

        if ($replicaId !== false && isset($this->replicas[(int) $replicaId])) {
            return $this->replicas[(int) $replicaId];
        }

        // Replica seç (consistent hashing)
        $selectedId = $userId % count($this->replicas);
        $this->redis->setex($stickyKey, $this->stickyTtl, (string) $selectedId);

        return $this->replicas[$selectedId];
    }
}

/**
 * Causal consistency: Vector clock ilə
 */
class VectorClockDocument
{
    private Redis $redis;
    private PDO $db;

    public function write(string $docId, array $data, string $nodeId): array
    {
        $clockKey = "vclock:{$docId}";

        // Mövcud vector clock-u al
        $current = $this->getVectorClock($docId);
        $current[$nodeId] = ($current[$nodeId] ?? 0) + 1;

        // Data + clock birlikdə yaz
        $this->db->prepare(
            'INSERT INTO documents (doc_id, payload, vector_clock, node_id, written_at)
             VALUES (:doc, :payload, :clock, :node, NOW())
             ON CONFLICT (doc_id) DO UPDATE
               SET payload = :payload, vector_clock = :clock, written_at = NOW()'
        )->execute([
            ':doc'     => $docId,
            ':payload' => json_encode($data),
            ':clock'   => json_encode($current),
            ':node'    => $nodeId,
        ]);

        $this->redis->set($clockKey, json_encode($current), ['EX' => 3600]);

        return $current;
    }

    public function read(string $docId, ?array $clientClock = null): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE doc_id = ?');
        $stmt->execute([$docId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            return null;
        }

        $serverClock = json_decode($doc['vector_clock'], true);

        // Causal check: client gözlənilən versiyonu gördümü?
        if ($clientClock && !$this->happensBefore($clientClock, $serverClock)) {
            // Server client-in gördüyündən geridədir
            throw new \RuntimeException('Causal consistency violation: replica is behind');
        }

        return [
            'data'         => json_decode($doc['payload'], true),
            'vector_clock' => $serverClock,
        ];
    }

    /**
     * A → B: A, B-dən əvvəl baş verib (happens-before)
     * B bütün A-nın görmüş olduğunu görmüşdür
     */
    private function happensBefore(array $clockA, array $clockB): bool
    {
        foreach ($clockA as $node => $time) {
            if (($clockB[$node] ?? 0) < $time) {
                return false; // B, A-nın bəzi yazılarını görməyib
            }
        }
        return true;
    }

    private function getVectorClock(string $docId): array
    {
        $key    = "vclock:{$docId}";
        $cached = $this->redis->get($key);

        if ($cached !== false) {
            return json_decode($cached, true);
        }

        $stmt = $this->db->prepare('SELECT vector_clock FROM documents WHERE doc_id = ?');
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? json_decode($row['vector_clock'], true) : [];
    }
}

/**
 * Eventual consistency: Read Repair pattern
 */
class ReadRepairRepository
{
    /** @var PDO[] */
    private array $nodes;

    public function read(string $key): mixed
    {
        $results = [];

        foreach ($this->nodes as $nodeId => $db) {
            $stmt = $db->prepare('SELECT value, version, updated_at FROM kv_store WHERE key = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $results[$nodeId] = $row;
            }
        }

        if (empty($results)) {
            return null;
        }

        // Ən yeni versiyonu tap
        $latest = collect($results)->sortByDesc('version')->first();

        // Geridə qalan node-ları arxa planda yenilə (Read Repair)
        foreach ($results as $nodeId => $row) {
            if ($row['version'] < $latest['version']) {
                $this->repairNode($nodeId, $key, $latest);
            }
        }

        return $latest['value'];
    }

    private function repairNode(int $nodeId, string $key, array $latest): void
    {
        // Arxa planda async olaraq icra olunur
        $this->nodes[$nodeId]->prepare(
            'INSERT INTO kv_store (key, value, version, updated_at)
             VALUES (:k, :v, :ver, :ts)
             ON CONFLICT (key) DO UPDATE
               SET value = :v, version = :ver, updated_at = :ts
               WHERE kv_store.version < :ver'
        )->execute([
            ':k'   => $key,
            ':v'   => $latest['value'],
            ':ver' => $latest['version'],
            ':ts'  => $latest['updated_at'],
        ]);
    }
}
```

---

## İntervyu Sualları

- Read-your-writes consistency nədir? Replica lag bu problemi necə yaradır?
- Monotonic reads olmadığında istifadəçi hansı problemlə üzləşə bilər?
- Causal consistency eventual consistency-dən nə ilə fərqlənir?
- Vector clocks nədir? Hansı sıra problemi həll edir?
- Read Repair pattern-i nə vaxt işə salınır? Dezavantajı nədir?
- Sticky session (session affinity) monotonic reads üçün kifayətlidirmi?
- Cassandra-da `CONSISTENCY QUORUM` ilə `CONSISTENCY ONE` arasındakı fərq nədir?
- "Session guarantees" modelindəki 4 zəmanəti sadalayın.
