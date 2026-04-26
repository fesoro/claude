# CRDT (Conflict-free Replicated Data Types) (Lead)

## İcmal

**CRDT (Conflict-free Replicated Data Type)** — paylanmış sistemlərdə bir neçə replika eyni dataya paralel dəyişiklik edəndə, heç bir koordinasiya (lock, consensus) olmadan avtomatik olaraq eyni nəticəyə gəlməsini təmin edən data strukturlarıdır.

**Əsas xüsusiyyətlər:**
- **Strong Eventual Consistency (SEC)** — bütün replikalar eyni mesajları aldıqda eyni vəziyyətə gəlir
- **Heç bir mərkəzi koordinator yoxdur** — peer-to-peer mərge olunur
- **Commutative, associative, idempotent** operations — sıralama vacib deyil

CRDT-lər kollaborativ redaktə (Google Docs, Figma), distributed counter, offline-first mobil tətbiqlərdə istifadə olunur.


## Niyə Vacibdir

Distributed sistemdə offline edit sonra merge etmək üçün conflict-free məlumat strukturları lazımdır. CRDT network partition zamanı availability saxlayır; collaborative editing, shopping cart, distributed counter-da istifadə olunur. OT ilə müqayisə real seçim üçün vacibdir.

## Əsas Anlayışlar

### 1. İki əsas CRDT növü

**State-based CRDT (CvRDT — Convergent Replicated Data Type):**
- Bütün state göndərilir
- Merge funksiyası **commutative, associative, idempotent** olmalıdır
- Simpler, amma böyük state üçün bandwidth bahalıdır

**Operation-based CRDT (CmRDT — Commutative Replicated Data Type):**
- Yalnız əməliyyatlar göndərilir
- Reliable causal broadcast tələb olunur
- Daha effektiv, amma mürəkkəb

### 2. G-Counter (Grow-only Counter)

Yalnız artırıla bilər. Hər node öz sayğacını saxlayır.

```
Node A: [A:3, B:0, C:0]
Node B: [A:0, B:5, C:0]
Node C: [A:0, B:0, C:2]

Merge: [A:3, B:5, C:2]
Total = 3+5+2 = 10
```

**İstifadə:** View count, like count (yalnız artan).

### 3. PN-Counter (Positive-Negative Counter)

İki G-Counter ilə qurulur — biri artımlar, biri azalmalar üçün.

```
P = G-Counter (increments)
N = G-Counter (decrements)
Value = sum(P) - sum(N)
```

**İstifadə:** Shopping cart item count, balance tracking.

### 4. G-Set (Grow-only Set)

Element yalnız əlavə edilə bilər, silinə bilməz.

```
A: {apple, banana}
B: {banana, cherry}
Merge: {apple, banana, cherry}
```

### 5. 2P-Set (Two-Phase Set)

Tombstone (silinənlər) set-i saxlayır. Bir dəfə silinmiş element yenidən əlavə edilə bilməz.

```
A_added = {x, y, z}
A_removed = {y}      // tombstone
Elements = A_added - A_removed = {x, z}
```

### 6. LWW-Register (Last-Write-Wins Register)

Ən son timestamp qalib gəlir. Conflict resolution çox sadədir.

```
A: (value="hello", timestamp=100)
B: (value="world", timestamp=105)
Merge: (value="world", timestamp=105)
```

**Problem:** Clock skew, concurrent writes arasında məlumat itə bilər.

### 7. OR-Set (Observed-Remove Set)

Hər add əməliyyatına unikal tag verir. Remove yalnız observed tag-ləri silir — yenidən əlavə edilmiş element qala bilər.

```
A: {(apple, tag1), (banana, tag2)}
B silir apple-i observed tag1 ilə
A paralel olaraq yenidən apple əlavə edir: {(apple, tag3)}
Merge: {(apple, tag3), (banana, tag2)}  // apple qalır
```

**İstifadə:** Kollaborativ task list, tag set.

## Arxitektura

```
┌───────────┐     sync     ┌───────────┐
│  Replica A│◄────────────►│  Replica B│
└─────┬─────┘              └─────┬─────┘
      │                          │
      │ gossip                   │ gossip
      │                          │
      └────────┬─────────────────┘
               │
         ┌─────▼─────┐
         │  Replica C│
         └───────────┘

Hər replika lokal yazmaları qəbul edir.
Arxa planda gossip protokolu ilə state/operations mübadiləsi.
Merge funksiyası avtomatik conflict resolution həyata keçirir.
```

**Komponentlər:**
- **Local replica** — lokal state, CRUD əməliyyatları sürətli
- **Sync protocol** — gossip, anti-entropy, delta-sync
- **Merge function** — mathematically proven convergence
- **Vector clock / Lamport timestamp** — causality tracking

## Nümunələr

### G-Counter İmplementasiya

```php
<?php

class GCounter
{
    private array $counts = [];
    private string $nodeId;

    public function __construct(string $nodeId)
    {
        $this->nodeId = $nodeId;
        $this->counts[$nodeId] = 0;
    }

    public function increment(int $amount = 1): void
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('G-Counter yalnız artırıla bilər');
        }
        $this->counts[$this->nodeId] = ($this->counts[$this->nodeId] ?? 0) + $amount;
    }

    public function value(): int
    {
        return array_sum($this->counts);
    }

    public function merge(GCounter $other): void
    {
        foreach ($other->counts as $node => $count) {
            $this->counts[$node] = max($this->counts[$node] ?? 0, $count);
        }
    }

    public function getState(): array
    {
        return $this->counts;
    }

    public function setState(array $state): void
    {
        $this->counts = $state;
    }
}

// İstifadə
$a = new GCounter('node-a');
$b = new GCounter('node-b');

$a->increment(3);
$b->increment(5);

$a->merge($b);
$b->merge($a);

echo $a->value(); // 8
echo $b->value(); // 8
```

### PN-Counter İmplementasiya

```php
class PNCounter
{
    private GCounter $positive;
    private GCounter $negative;

    public function __construct(string $nodeId)
    {
        $this->positive = new GCounter($nodeId);
        $this->negative = new GCounter($nodeId);
    }

    public function increment(int $amount = 1): void
    {
        $this->positive->increment($amount);
    }

    public function decrement(int $amount = 1): void
    {
        $this->negative->increment($amount);
    }

    public function value(): int
    {
        return $this->positive->value() - $this->negative->value();
    }

    public function merge(PNCounter $other): void
    {
        $this->positive->merge($other->positive);
        $this->negative->merge($other->negative);
    }
}
```

### OR-Set İmplementasiya

```php
class ORSet
{
    private array $elements = []; // [element => [tag1, tag2, ...]]
    private array $tombstones = []; // silinmiş taglar

    public function add(string $element): string
    {
        $tag = bin2hex(random_bytes(8));
        $this->elements[$element][] = $tag;
        return $tag;
    }

    public function remove(string $element): void
    {
        if (!isset($this->elements[$element])) {
            return;
        }
        foreach ($this->elements[$element] as $tag) {
            $this->tombstones[$element][] = $tag;
        }
        $this->elements[$element] = [];
    }

    public function contains(string $element): bool
    {
        return !empty($this->elements[$element] ?? []);
    }

    public function values(): array
    {
        return array_keys(array_filter($this->elements, fn($tags) => !empty($tags)));
    }

    public function merge(ORSet $other): void
    {
        foreach ($other->elements as $element => $tags) {
            $this->elements[$element] = array_unique(
                array_merge($this->elements[$element] ?? [], $tags)
            );
        }
        foreach ($other->tombstones as $element => $tags) {
            $this->tombstones[$element] = array_unique(
                array_merge($this->tombstones[$element] ?? [], $tags)
            );
        }
        // Apply tombstones
        foreach ($this->tombstones as $element => $tags) {
            $this->elements[$element] = array_diff(
                $this->elements[$element] ?? [],
                $tags
            );
        }
    }
}
```

### Laravel ilə Kollaborativ Counter (Redis + CRDT)

```php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class DistributedCounter
{
    private string $nodeId;

    public function __construct()
    {
        $this->nodeId = config('app.node_id') ?? gethostname();
    }

    public function increment(string $key, int $amount = 1): void
    {
        // Hər node öz sayğacını artırır
        Redis::hincrby("crdt:gcounter:{$key}", $this->nodeId, $amount);

        // Pub/sub ilə digər node-lara bildir
        Redis::publish("crdt:sync:{$key}", json_encode([
            'node' => $this->nodeId,
            'value' => Redis::hget("crdt:gcounter:{$key}", $this->nodeId),
        ]));
    }

    public function value(string $key): int
    {
        $counts = Redis::hgetall("crdt:gcounter:{$key}");
        return array_sum(array_map('intval', $counts));
    }

    public function merge(string $key, string $remoteNode, int $remoteValue): void
    {
        $current = (int) Redis::hget("crdt:gcounter:{$key}", $remoteNode);
        Redis::hset("crdt:gcounter:{$key}", $remoteNode, max($current, $remoteValue));
    }
}

// İstifadə — page view counter
$counter = new DistributedCounter();
$counter->increment("page:home:views");
echo $counter->value("page:home:views"); // Total across all nodes
```

### Kollaborativ Document (LWW-Register)

```php
class LWWRegister
{
    private mixed $value;
    private int $timestamp;
    private string $nodeId;

    public function __construct(string $nodeId)
    {
        $this->nodeId = $nodeId;
        $this->timestamp = 0;
        $this->value = null;
    }

    public function set(mixed $value): void
    {
        $this->value = $value;
        $this->timestamp = (int) (microtime(true) * 1000000);
    }

    public function merge(LWWRegister $other): void
    {
        if ($other->timestamp > $this->timestamp ||
            ($other->timestamp === $this->timestamp && strcmp($other->nodeId, $this->nodeId) > 0)) {
            $this->value = $other->value;
            $this->timestamp = $other->timestamp;
        }
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
```

## Real-World Nümunələr

- **Redis Enterprise CRDB** — geo-distributed Redis, CRDT-əsaslı
- **Riak** — eventual consistency, built-in CRDT (counters, sets, maps)
- **Figma** — kollaborativ design, OT + CRDT hibrid
- **Automerge (JavaScript)** — JSON CRDT library
- **Yjs** — kollaborativ editor framework (Notion, Jupyter kullanıyor)
- **Google Docs** — Operational Transformation (OT), CRDT alternativi
- **Apple Notes** — offline sync, CRDT konsepti
- **Azure Cosmos DB** — multi-region writes üçün CRDT

## Praktik Tapşırıqlar

**1. CRDT-nin əsas məqsədi nədir?**
Paylanmış sistemlərdə koordinasiya (lock, consensus) olmadan eventual consistency təmin etmək. Hər replika müstəqil yaza bilir, sonra avtomatik merge edilir.

**2. State-based vs Operation-based CRDT fərqi?**
State-based: bütün state göndərilir, merge idempotent olmalıdır. Op-based: yalnız əməliyyatlar göndərilir, reliable causal delivery lazımdır. State-based sadə, op-based effektiv bandwidth baxımından.

**3. G-Counter niyə yalnız artır, PN-Counter azaltma necə həll edir?**
G-Counter-in merge funksiyası `max()` əsaslıdır. Azaltma olsaydı `max` yanlış nəticə verərdi. PN-Counter iki G-Counter istifadə edir — biri artımlar (P), biri azalmalar (N). `value = P - N`.

**4. LWW-Register-də clock skew problemini necə həll edirsən?**
- NTP ilə saat sinxronizasiyası
- Logical clock (Lamport, vector clock) istifadə et
- HLC (Hybrid Logical Clock) — physical + logical
- Tie-breaker kimi node ID istifadə et

**5. 2P-Set vs OR-Set fərqi?**
2P-Set: bir dəfə silinən element yenidən əlavə edilə bilməz (permanent tombstone). OR-Set: hər add unikal tag-lı — silindikdən sonra yenidən əlavə edilə bilər. OR-Set praktiki istifadədə daha fleksibldir.

**6. CRDT-nin əsas dezavantajları?**
- Metadata overhead (tags, tombstones)
- Strong consistency mümkün deyil
- Tombstones böyüyür — garbage collection lazımdır
- Hər data növü CRDT variantı tələb edir

**7. Figma/Google Docs CRDT istifadə edirmi?**
Figma: hibrid approach — multi-player editing üçün özəl CRDT. Google Docs: Operational Transformation (OT) istifadə edir — CRDT-yə bənzər, amma mərkəzi server tələb edir. CRDT-lər tamamilə peer-to-peer işləyə bilir.

**8. CRDT-də tombstone garbage collection necə edilir?**
Bütün replikaların tombstone-nu görəcəyinə əmin olandan sonra silə bilərsən. Causal stability (bütün node-ların müəyyən timestamp-dan sonra yazdıqları) lazımdır. Riak `bitcask` və Cassandra `tombstone TTL` istifadə edir.

**9. CRDT vs Consensus (Raft, Paxos) nə vaxt seçilməlidir?**
- CRDT: availability > consistency, offline yazmalar, çox sayda replika
- Consensus: strong consistency, leader election, config management
- CRDT `AP` (CAP), consensus `CP`.

**10. Vector clock CRDT-də niyə istifadə olunur?**
Causality-ni müəyyən etmək üçün — hansı əməliyyat başqasından əvvəldir, concurrent-dir, yoxsa causal baxımdan sonradır. OR-Set, multi-value register kimi CRDT-lərdə bu mühümdür.

## Praktik Baxış

1. **Problem CRDT-yə uyğundursa istifadə et** — hər yerdə istifadə etmə, strong consistency lazım olanda Raft/Paxos seç
2. **Tombstone GC strategiyası planla** — uzun müddət yığılarsa performance problemi olar
3. **Delta-sync istifadə et** — full state əvəzinə yalnız dəyişikliyi göndər
4. **Hybrid Logical Clock (HLC)** — LWW üçün physical + logical kombinasiya et
5. **Idempotent network layer** — eyni mesaj dəfələrlə gələ bilər
6. **Redis CRDB və ya Riak istifadə et** — əl ilə yazmaqdan çəkin istehsalatda
7. **Vector clock-ları metadata-da saxla** — causal dependency üçün
8. **Monitoring** — convergence time, tombstone size, sync lag
9. **Schema evolution** — CRDT növünü sonradan dəyişmək çətindir — düzgün seç
10. **Test edilməsi üçün property-based testing** — convergence xüsusiyyətlərini yoxla (commutativity, associativity, idempotency)


## Əlaqəli Mövzular

- [Collaborative Editing](51-collaborative-editing-design.md) — CRDT-nin əsas use-case-i
- [Consistency Patterns](32-consistency-patterns.md) — eventual consistency ilə CRDT
- [Distributed Systems](25-distributed-systems.md) — partition tolerance
- [Anti-Entropy](92-anti-entropy-merkle-trees.md) — replica sinxronizasiyası
- [Multi-Region Active-Active](85-multi-region-active-active.md) — conflict resolution
