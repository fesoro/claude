# Consistency Patterns (Ardıcıllıq Modelləri)

## Nədir? (What is it?)

**Consistency** — distributed sistemdə data-nın müxtəlif node-larda eyni görünmə qaydalarıdır. CAP teoremi göstərir: Consistency, Availability və Partition tolerance-dən ancaq 2-ni seçmək olar. Müxtəlif consistency model-ləri fərqli trade-off-lar təklif edir.

## Əsas Konseptlər (Key Concepts)

### 1. Consistency Spectrum

```
Daha güclü                                                    Daha zəif
    ↓                                                              ↓
Strong → Sequential → Causal → Read-your-writes → Eventual
(SQL master)                                        (DNS, S3)
```

### 2. Strong Consistency

Bütün oxu əməliyyatları ən son yazılmış dəyəri qaytarır. Yazıldıqdan dərhal sonra oxu görür.

**Necə əldə olunur:**
- Synchronous replication (bütün replica-lar yazılmayınca response verilmir)
- Consensus protocols (Raft, Paxos)
- Distributed locks

**Üstünlüklər:** intuitiv, sadə proqramlaşdırma
**Çatışmazlıqlar:** yüksək latency, availability azalır (partition zamanı yazıla bilməz)

### 3. Eventual Consistency

Yazı müvəqqəti olaraq yalnız bəzi replica-larda olur. Zamanla (milisaniyədən dəqiqələrə qədər) bütün replica-lar eyniləşir.

**Necə əldə olunur:**
- Asynchronous replication
- Gossip protocols
- Read-repair

**Üstünlüklər:** yüksək availability, aşağı latency
**Çatışmazlıqlar:** stale data görmək mümkündür, konflikt həlli lazımdır

### 4. Read-Your-Writes Consistency

İstifadəçi öz yazdığını dərhal oxuya bilir. Digər istifadəçilər gecikməli görə bilər.

**Necə əldə olunur:**
- Session affinity (eyni replica-ya yönləndirmə)
- Client-side timestamp tracking
- Read-from-master for own data

### 5. Monotonic Read Consistency

İstifadəçi oxuduqdan sonra yalnız irəli data görür, heç vaxt geriyə düşmür.

**Problem nümunəsi:**
```
t=1: User reads comment → ["Hello", "World"]
t=2: User reads from different replica → ["Hello"]  // ❌
     (Second replica hələ "World"-ü almayıb)
```

### 6. Causal Consistency

Səbəb-nəticə əlaqəli yazılar düzgün sırada görünür.

**Nümunə:** 
- User A post yazır → "I'm engaged!"
- User B comment yazır → "Congrats!"

Hamı əvvəl post-u, sonra comment-i görməlidir.

## Praktiki Nümunələr (Practical Examples)

### Strong Consistency — Banking

```php
<?php
// Laravel database transaction
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($fromId, $toId, $amount) {
    // Hər iki hesab lock edilir — strong consistency
    $from = Account::lockForUpdate()->find($fromId);
    $to = Account::lockForUpdate()->find($toId);
    
    if ($from->balance < $amount) {
        throw new InsufficientFundsException();
    }
    
    $from->decrement('balance', $amount);
    $to->increment('balance', $amount);
});
```

### Eventual Consistency — Social Media Like Count

```php
// Redis-də counter, MySQL-ə asinxron sync
class PostLikeService
{
    public function like(int $postId, int $userId): void
    {
        // 1. Redis-də like counter artır (instant, fast)
        Redis::incr("post:{$postId}:likes");
        Redis::sadd("post:{$postId}:liked_by", $userId);
        
        // 2. MySQL-ə asynchronously sync
        SyncLikeToDatabase::dispatch($postId, $userId)->afterCommit();
    }
    
    public function getLikeCount(int $postId): int
    {
        // Redis-dən oxu (eventual consistent)
        return (int) Redis::get("post:{$postId}:likes") ?? 0;
    }
}
```

### Read-Your-Writes — Laravel Session

```php
// Config: database/connections.php
'mysql' => [
    'write' => [
        'host' => 'mysql-master.example.com',
    ],
    'read' => [
        'host' => ['mysql-replica-1.example.com', 'mysql-replica-2.example.com'],
    ],
    'sticky' => true,  // ⭐ Əsas nöqtə
    'driver' => 'mysql',
],
```

`sticky => true` — request daxilində yazı varsa, sonrakı oxular master-dən gəlir (öz yazdığını oxuyur).

```php
// İş axını
User::create(['name' => 'Ali']);           // Write → master
$user = User::find($id);                     // Read → master (sticky)
                                              // (başqa request-də read replica-dan)
```

### Causal Consistency — Comment Thread

```php
class Comment extends Model
{
    public function scopeOrderByCausal($query)
    {
        // Parent comment əvvəl görünür, sonra uşaq
        return $query->orderBy('parent_id', 'asc')
                     ->orderBy('created_at', 'asc');
    }
}

// Vector clock ilə causal tracking
class CausalOrder
{
    public function __construct(
        public array $vectorClock = []
    ) {}
    
    public function increment(string $nodeId): void
    {
        $this->vectorClock[$nodeId] = ($this->vectorClock[$nodeId] ?? 0) + 1;
    }
    
    public function happenedBefore(CausalOrder $other): bool
    {
        $allLess = true;
        $anyLess = false;
        foreach ($this->vectorClock as $node => $val) {
            $otherVal = $other->vectorClock[$node] ?? 0;
            if ($val > $otherVal) $allLess = false;
            if ($val < $otherVal) $anyLess = true;
        }
        return $allLess && $anyLess;
    }
}
```

## Arxitektura (Architecture)

### Multi-Master vs Single-Master

```
Single-Master (Strong):
┌──────────┐
│  Master  │ ← All writes
└────┬─────┘
     │ async replication
     ↓
┌─────────┐ ┌─────────┐
│Replica 1│ │Replica 2│ ← Reads (stale-able)
└─────────┘ └─────────┘

Multi-Master (Eventual):
┌────────┐ ←──→ ┌────────┐
│Master A│      │Master B│
└────────┘ ←──→ └────────┘
  Writes          Writes
  (conflict həll olmalı)
```

### Quorum-based (Dynamo Style)

`N = 3, W = 2, R = 2` — strong consistency əldə olunur.

```
W + R > N  →  Strong consistency

Write Quorum (W=2):
Client → [Node 1 ✓] [Node 2 ✓] [Node 3 ✗] → ACK

Read Quorum (R=2):
Client → [Node 1] [Node 3] → ən son timestamp qalibdir
```

## PHP/Laravel ilə Tətbiq

### MySQL Replication Lag Handling

```php
// Oxu sonra yaz problemi
User::where('id', $id)->update(['status' => 'active']);

// Read replica hələ update görməyib
$user = User::on('read-replica')->find($id);
$user->status; // ❌ Hələ "inactive" görür

// Həll 1: sticky connection (yuxarıda)
// Həll 2: Cache invalidation
Cache::forget("user:{$id}");
$user = User::on('mysql')->find($id); // Master-dən zəmanət
```

### Redis Cluster Consistency

```php
// Redis cluster-də eventual consistency
$client = Redis::connection('cluster');

// WAIT komandası — sync replication simulyasiya
$client->set('key', 'value');
$client->command('WAIT', [1, 100]); // 1 replica, 100ms timeout
```

### Laravel Cache Invalidation Pattern

```php
class CachedUserRepository
{
    public function find(int $id): ?User
    {
        return Cache::remember("user:{$id}", 3600, function() use ($id) {
            return User::find($id); // DB-dən oxu
        });
    }
    
    public function update(int $id, array $data): User
    {
        $user = User::find($id);
        $user->update($data);
        
        // Cache invalidation — eventual consistency-dən qoru
        Cache::forget("user:{$id}");
        
        // Yeni data-nı əvvəlcədən qoy (cache stampede qarşısı)
        Cache::put("user:{$id}", $user->fresh(), 3600);
        
        return $user;
    }
}
```

## Real-World Nümunələr

- **Amazon DynamoDB**: Tunable consistency (eventual default, strong optional)
- **Apache Cassandra**: Tunable quorum (ONE, QUORUM, ALL)
- **MongoDB**: Replica set primary → strong reads, secondary → eventual
- **Redis**: Master → strong, Replica → eventual
- **DNS**: Eventual consistency (propagation ~24 saat)
- **Git**: Eventual consistency arası repository

## Interview Sualları

**1. Strong vs Eventual consistency arasında necə seçirik?**
- Strong: financial (bank, inventory), data correctness kritik
- Eventual: social (like, comment count), high availability tələb olunur

**2. Read-your-writes necə təmin olunur?**
Sticky session (eyni user master-ə yönlənir), client-side timestamp, read-from-master for own data.

**3. CAP teoremi nədir?**
Consistency, Availability, Partition tolerance-dən yalnız 2-si bir anda mümkündür. Real sistemlərdə partition qaçınılmaz olduğu üçün CP vs AP seçilir.

**4. Eventual consistency-də konflikt necə həll olunur?**
- Last-Write-Wins (LWW) — timestamp əsasında
- Multi-value — hər iki dəyər saxlanır, client həll edir
- CRDTs — konflikt-free replicated data types
- Vector clocks — causal əlaqə bərpası

**5. Laravel sticky connection nə edir?**
Request daxilində yazı baş verərsə, həmin request-də sonrakı oxular master-dən gedir. `sticky => true` config-də.

**6. Monotonic read nə vaxt qırılır?**
Fərqli replica-lara yönləndirilərkən — replica 1 yeni data görür, replica 2 hələ görmür. User replica 2-yə yönlənərsə "zaman geri gedir".

**7. Session consistency nə deməkdir?**
Bir session daxilində read-your-writes + monotonic read təmin olunur. Əsasən single-user əməliyyatları üçün.

**8. Quorum-based consistency formulası?**
`W + R > N` strong consistency verir. N=3, W=2, R=2 klassik seçimdir. Hər yazı ən azı 2 node-a gedir, hər oxu 2 node-dan.

## Best Practices

1. **Business requirement-ə baxaraq seç** — hamı üçün strong lazım deyil
2. **Sticky session** istifadə et — RYW üçün
3. **Cache invalidation** agressiv ol — stale data problem
4. **Version vector/timestamp** — konflikt həlli üçün
5. **Monotonic read** üçün — client-side timestamp tracking
6. **DB transactions** — strong lazım olanda mütləq
7. **Idempotent operations** — retry zamanı duplicate qorxusu olmasın
8. **CQRS pattern** — read və write ayrı consistency model-də
9. **Event sourcing** — causal consistency təmin edir
10. **Distributed lock** — Redis Redlock, ZooKeeper critical section üçün
