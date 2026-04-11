# Write-heavy Leaderboard

## Problem necə yaranır?

Online oyunda hər saniyə minlərlə score update baş verir. MySQL-də hər update üçün `UPDATE users SET score = score + delta WHERE id = ?` — row lock, index update, disk I/O. 10,000 req/s → DB bottleneck.

Redis Sorted Set optimal data strukturdur, lakin hər event üçün ayrı `ZINCRBY` də yüksək write yükü yaradır. Əgər Redis-ə hər saniyə 10,000 ayrı command göndərilirsə — network overhead və Redis connection pool tükənir.

---

## Redis Sorted Set — Niyə İdealdır?

```
ZADD leaderboard 1500 "user:42"     → Insert/update (O(log N))
ZINCRBY leaderboard 50 "user:42"    → Atomic increment (O(log N))
ZREVRANK leaderboard "user:42"      → Rank (O(log N))
ZREVRANGE leaderboard 0 99          → Top 100 (O(log N + 100))
ZSCORE leaderboard "user:42"        → Score (O(1))
ZCARD leaderboard                   → Total member sayı (O(1))
```

Tie-breaking: eyni score-da üzv adına görə (lexicographic) sort — deterministic sıralama. 10M member: ~800MB RAM.

---

## İmplementasiya

*Bu kod Redis Sorted Set ilə liderboard servisini, write batching üçün score buffer-ı və periodik DB snapshot job-unu göstərir:*

```php
class LeaderboardService
{
    private const KEY = 'leaderboard:global';

    // Score set etmək (absolut)
    public function updateScore(int $userId, int $score): void
    {
        Redis::zadd(self::KEY, $score, "user:{$userId}");
    }

    // Delta artırmaq (oyunda hər kill, hər coin)
    public function incrementScore(int $userId, int $delta): void
    {
        Redis::zincrby(self::KEY, $delta, "user:{$userId}");
    }

    // Top N — batch DB query ilə user adlarını əlavə edir
    public function getTopN(int $n = 100): array
    {
        $raw   = Redis::zrevrange(self::KEY, 0, $n - 1, 'WITHSCORES');
        $items = [];

        foreach (array_chunk($raw, 2) as [$member, $score]) {
            $items[] = ['user_id' => (int) str_replace('user:', '', $member), 'score' => (int) $score];
        }

        // N+1 önlənir: user adlarını bir sorğuda alırıq
        $users = User::whereIn('id', array_column($items, 'user_id'))->get()->keyBy('id');

        return array_map(function ($item, $rank) use ($users) {
            return [
                'rank'     => $rank + 1,
                'user_id'  => $item['user_id'],
                'username' => $users[$item['user_id']]?->name ?? 'Unknown',
                'score'    => $item['score'],
            ];
        }, $items, array_keys($items));
    }

    public function getUserRank(int $userId): ?array
    {
        $member = "user:{$userId}";
        $rank   = Redis::zrevrank(self::KEY, $member); // 0-based
        $score  = Redis::zscore(self::KEY, $member);

        return $rank !== null ? ['rank' => $rank + 1, 'score' => (int) $score] : null;
    }

    // User-in ±N ətrafındakı oyunçular ("around me" feature)
    public function getAroundUser(int $userId, int $range = 5): array
    {
        $rank  = Redis::zrevrank(self::KEY, "user:{$userId}");
        if ($rank === null) return [];

        $start = max(0, $rank - $range);
        $end   = $rank + $range;

        $raw = Redis::zrevrange(self::KEY, $start, $end, 'WITHSCORES');
        $pos = $start;
        $result = [];

        foreach (array_chunk($raw, 2) as [$member, $score]) {
            $result[] = [
                'rank'    => $pos + 1,
                'user_id' => (int) str_replace('user:', '', $member),
                'score'   => (int) $score,
                'is_me'   => $member === "user:{$userId}",
            ];
            $pos++;
        }

        return $result;
    }
}

// Score Buffer — write batching ilə Redis yükünü azaldır
class ScoreBuffer
{
    private const BUFFER_KEY = 'score:buffer';

    // Hər event üçün Redis ZINCRBY deyil, HINCRBY buffer-a yaz
    public function add(int $userId, int $delta): void
    {
        Redis::hincrby(self::BUFFER_KEY, $userId, $delta);
    }

    // Hər 5 saniyədə bir flush — N ayrı command yerinə pipeline ilə batch
    public function flush(): void
    {
        $buffer = Redis::hgetall(self::BUFFER_KEY);
        if (empty($buffer)) return;

        Redis::del(self::BUFFER_KEY);

        // Pipeline: 1 round trip ilə N ZINCRBY əvəzinə
        Redis::pipeline(function ($pipe) use ($buffer) {
            foreach ($buffer as $userId => $delta) {
                $pipe->zincrby(LeaderboardService::KEY, (int) $delta, "user:{$userId}");
            }
        });
    }
}

// Persistence — Redis data volatile-dir, DB-yə periodic snapshot
class SnapshotLeaderboardJob implements ShouldQueue
{
    public function handle(): void
    {
        $top1000 = app(LeaderboardService::class)->getTopN(1000);

        $data = array_map(fn($item) => [
            'user_id'        => $item['user_id'],
            'score'          => $item['score'],
            'rank'           => $item['rank'],
            'snapshotted_at' => now(),
        ], $top1000);

        LeaderboardSnapshot::upsert($data, ['user_id'], ['score', 'rank', 'snapshotted_at']);
    }
}
```

---

## Weekly/Monthly Leaderboard

*Bu kod həftəlik/aylıq liderboard üçün ayrı Redis açarları ilə tarix-əsaslı liderboard sinifini göstərir:*

```php
// Ayrı key-lər ilə tarix-based leaderboard
class TimedLeaderboard
{
    public function getKey(string $period): string
    {
        return match($period) {
            'weekly'  => 'leaderboard:weekly:' . now()->format('Y-W'),
            'monthly' => 'leaderboard:monthly:' . now()->format('Y-m'),
            default   => 'leaderboard:global',
        };
    }

    public function increment(int $userId, int $delta, string $period = 'weekly'): void
    {
        $key = $this->getKey($period);
        Redis::zincrby($key, $delta, "user:{$userId}");

        // TTL set et — period bitdikdə key expire olur (manual cleanup lazım deyil)
        if (!Redis::ttl($key) || Redis::ttl($key) < 0) {
            Redis::expire($key, $period === 'weekly' ? 8 * 86400 : 35 * 86400);
        }
    }
}
```

---

## Redis Persistence Konfiqurasiyası

Redis default olaraq volatile-dir — restart-da data itirilir. İki persistence seçimi var:

```
RDB (snapshot): Hər N dəqiqədə bir binary snapshot
  save 900 1      → 900s içində 1+ dəyişiklik olsa snapshot al
  save 300 10     → 300s içində 10+ dəyişiklik olsa snapshot al
  Tradeoff: Sürətli restart, lakin son snapshot-dan bəri data itirilə bilər

AOF (append-only file): Hər write əməliyyatını log-a yazır
  appendonly yes
  appendfsync everysec   → hər saniyə sync (performans + durability balansı)
  Tradeoff: Daha az data itkisi, lakin daha böyük fayl, yavaş restart

Leaderboard üçün tövsiyə: RDB + AOF birlikdə
  Restart: AOF-dən rebuild (daha yeni), RDB fallback
```

---

## ZUNIONSTORE — Segmentli Leaderboard

Müxtəlif period-ların birləşdirilmiş leaderboard-u:

*Bu kod müxtəlif period liderboardlarını çəkilərlə birləşdirən ZUNIONSTORE əməliyyatını göstərir:*

```php
// Bu həftənin + bu ay-ın combined leaderboard-u (weight-li)
Redis::zunionstore(
    'leaderboard:combined',
    ['leaderboard:weekly:2024-03', 'leaderboard:monthly:2024-03'],
    ['weights' => [2, 1]] // Həftəlik daha çox ağırlıq
);
Redis::expire('leaderboard:combined', 300); // 5 dəq cache
```

---

## Anti-patterns

- **DB-də leaderboard:** `ORDER BY score DESC` full table scan — milyonlarla user-də 1-2 saniyə. Index yardım edir amma Sorted Set hələ də çox sürətlidir.
- **Hər event üçün ayrı Redis command:** 10,000 req/s → 10,000 ZINCRBY — connection overhead. Buffer + pipeline ilə batch.
- **Redis-i yeganə mənbə saymaq:** Redis volatile — restart-da data itirilir. Periodic DB snapshot mütləqdir.
- **Leaderboard-u hər request-də rebuild etmək:** `getTopN` cache-lənə bilər (5-10s TTL) — 1000 paralel request üçün 1 Redis call.

---

## İntervyu Sualları

**1. Redis Sorted Set niyə leaderboard üçün idealdır?**
O(log N) insert, rank, range sorğuları. Atomic ZINCRBY — concurrent updates thread-safe. Range queries (`ZREVRANGE 0 99`) birbaşa top 100-ü verir. MySQL-də ekvivalent sorğu index scan tələb edir.

**2. Write-heavy yükü necə idarə edirik?**
Score buffer + pipeline: hər event üçün ayrı Redis command əvəzinə buffer-a yaz, 5s-də bir batch pipeline. 10,000 ayrı command → 1 pipeline call — 100x az round trip.

**3. Redis restart-da data itirilsə?**
Redis AOF/RDB ilə persistence konfiqurasiya edilə bilər. Əlavə olaraq periodic DB snapshot: leaderboard yenidən Redis-ə yüklənə bilər. Write-behind: hər score update həm Redis həm DB — consistency, lakin latency artır.

**4. "Around me" feature necə işləyir?**
`ZREVRANK` ilə user-in rank-ını al (0-based). `max(0, rank - 5)` ilə `rank + 5` arasındakı range-i `ZREVRANGE` ilə gətir. Hər birini sırala — user özünü `is_me: true` ilə görür. O(log N + range) — çox sürətli.

**5. Eyni score-da tie-breaking necə edilir?**
Redis Sorted Set eyni score-da lexicographic order istifadə edir. Əgər member `"user:42"` kimi saxlanırsa, eyni score-da user ID-yə görə sıralanır — deterministic, amma lazımsız. Daha yaxşı: score-a timestamp daxil etmək: `score = points * 1e10 + (MAX_TIMESTAMP - timestamp)` — əvvəl nail olan yuxarıda.

**6. 50M user-li global leaderboard-un RAM tələbi nədir?**
Redis Sorted Set hər member üçün təxminən 16 bytes (pointer + score) + member string uzunluğu. `"user:12345678"` = 14 byte → hər member ~60-80 byte. 50M × 80 byte ≈ 4GB RAM. Yalnız top N-i saxlamaq daha az yük: `ZREMRANGEBYRANK leaderboard 0 -(N+1)` ilə alt səviyyəni sil.

---

## Anti-patternlər

**1. Leaderboard-u hər score update-də DB-dən rebuild etmək**
Score dəyişdikdə `SELECT user_id, SUM(score) ... ORDER BY score DESC` sorğusu ilə leaderboard-u yenidən hesablamaq — milyonlarla user-də hər saniyə bu sorğu DB-ni iflic edir. Leaderboard Redis Sorted Set-də saxlanmalı, yalnız dəyişən score ZINCRBY ilə yenilənməlidir.

**2. Redis Sorted Set-i backup olmadan yeganə mənbə kimi istifadə etmək**
Bütün score-ları yalnız Redis-də saxlamaq, DB-yə yazmamaq — Redis restart ya da eviction zamanı bütün leaderboard itirilir. Canonical source DB-də olmalı, Redis yalnız read cache kimi istifadə edilməli, periodik snapshot Redis-i yenilədə bilməlidir.

**3. Real-time event-ləri birbaşa Redis-ə tək-tək yazmaq**
Hər oyun eventi üçün ayrıca `ZINCRBY leaderboard user_id 1` çağırışı etmək — 50,000 req/s-də Redis-ə 50,000 ayrı command gedir, connection overhead böyüyür. Score-lar in-memory buffer-da toplanmalı, batch pipeline ilə Redis-ə yazılmalıdır.

**4. Top N sorğusunu hər request-də Redis-dən etmək**
`ZREVRANGE leaderboard 0 99` sorğusunu hər `/leaderboard` request-ində birbaşa Redis-ə etmək — 1000 eyni anda gələn request 1000 Redis call edir. Top N nəticəsi 5-10s TTL ilə ayrıca cache layer-ə alınmalı, çox request üçün tək Redis call kifayət etməlidir.

**5. Leaderboard-u global saxlayıb segment yoxlamamaq**
Bütün istifadəçiləri tək global leaderboard-da saxlamaq — 50 milyon user-də "öz rank-ımı bilmək" `ZREVRANK` çağırışı O(log N) olsa da, context-specific leaderboard (aylıq, regional, yoldaşlar arası) daha mənalıdır. Hər zaman/region/cohort üçün ayrı Sorted Set saxlanmalıdır.

**6. Score azalmasını ZINCRBY ilə mənfi dəyərlə idarə etmək**
Penalti tətbiq etmək üçün `ZINCRBY leaderboard user_id -50` etmək, lakin score-un mənfi ola biləcəyini nəzərə almamaq — Redis mənfi score-lara icazə verir, amma UI "−30 xal" göstərsə istifadəçi çaşır. Score floor (minimum 0) application layer-də tətbiq edilməli, mənfi score-a düşməmək üçün ZINCRBY-dan əvvəl yoxlama aparılmalıdır.

**7. Redis persistence olmadan production leaderboard saxlamaq**
`appendonly no` ilə Redis işlədib leaderboard-u yalnız memory-də tutmaq — server restart-da (deploy, crash) bütün score-lar itirilir. Production-da AOF (`appendonly yes`, `appendfsync everysec`) aktiv edilməli, həmçinin periodic DB snapshot paralel işlədilməlidir.
