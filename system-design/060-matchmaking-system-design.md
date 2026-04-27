# Matchmaking System Design (Senior)

Skill-based, low-latency matchmaking sistemi — oyunçuları MMR, region, mode və party-yə görə qruplaşdırıb dedicated game server-ə yönləndirir. Bu sənəd interview üçün Elo/Glicko-2/TrueSkill müqayisəsi, adaptive bucketing alqoritmi və Laravel/Redis sketch ilə hazırlanıb.

---


## Niyə Vacibdir

Online game-lərdə fair match tapma — Elo/TrueSkill rating, skill-based bucketing, latency consideration — distributed queue üzərindən idarə olunan real-time prosesdir. Gaming şirkətlərindəki backend arxitekturasını başa düşmək üçün vacibdir.

## Tələblər

### Funksional (Functional)
- N oyunçudan ibarət match yaratmaq (məs: 5v5, 1v1, battle royale 100-lük)
- Skill-based: MMR / Elo / TrueSkill reytinqə görə balanslı komanda
- Region-aware: eyni region (EU, NA, Asia) — ping aşağı olsun
- Game mode: ranked, casual, arena, quickplay ayrı queue
- Party support: 2-5 nəfərlik group queue-ya birlikdə girir
- Fast: istifadəçi çox gözləməsin (p95 < 60s)
- Match tapıldıqda game server allocate et və sessionId qaytar

### Non-functional (Non-functional)
- p95 match time < 60 saniyə
- Matched player-lər arasında latency < 80ms (eyni region)
- Fair match: komandaların ortalama MMR fərqi ≤ 100
- Anti-smurf: yüksək skill-li yeni akkauntları aşkar et
- Leaver penalty: oyunu tərk edənlərə cəza (queue delay, MMR minus)
- Reliability: match finder crash olsa queue itməsin

### Kapasitet (Capacity)
- 100k concurrent player peak saatda
- 10k match/saniyə peak (10 nəfərlik match → 100k player cycle)
- Region başına ~25k concurrent (4 region)
- Queue-da orta 30s, yəni həmişə ~300k queue entry Redis-də

---

## Rating sistemləri (Rating systems)

### Elo
Klassik şahmat reytinqi (Arpad Elo, 1960). 2 oyunçu üçün:

```
new_rating = old_rating + K * (actual_score - expected_score)
expected_score = 1 / (1 + 10^((opponent - player) / 400))
```

- `K-factor` sürəti idarə edir: yeni oyunçu K=40, usta K=10
- Sadə, amma uncertainty-ni nəzərə almır
- Komanda oyunları üçün zəif (1 oyunçu üçün nəzərdə tutulub)

### Glicko-2
Mark Glickman (2012). Elo + rating deviation (RD) + volatility.
- RD yüksək → player haqqında az məlumat, reytinq tez dəyişir
- RD aşağı → stabil, reytinq yavaş dəyişir
- Yeni playerlər üçün daha ədalətli
- Chess.com, Lichess istifadə edir

### TrueSkill / TrueSkill2
Microsoft Research (Xbox Live, Halo 3). Bayesian model:
- Hər player üçün `(mu, sigma)` — skill + uncertainty
- Komandaları dəstəkləyir (2v2, 5v5, FFA)
- TrueSkill2 (2018) — partial play, draws, squad bonus
- Match quality formula = iki komandanın skill distribution overlap-ı

### MMR (ümumi termin)
"Matchmaking Rating" — ümumi ad, altında Elo/Glicko/TrueSkill ola bilər. Çox oyun iki dəyər saxlayır:
- `visible_rating` — istifadəçiyə göstərilən (rank, league)
- `hidden_mmr` — real matchmaking dəyəri (smurf detection üçün)

---

## Arxitektura

```
[Game client]
    |
    | (HTTPS join queue)
    v
[API Gateway] -- [Queue Service] -- [Redis sorted set]
                      |                    ^
                      v                    |
                [Match Finder] ------------+
                      |
                      v
                [Session Service] -- [Game Server Allocator]
                      |                         |
                      v                         v
                [WebSocket push]          [Agones / GameLift]
                      |                         |
                      v                         v
                [Game client]           [Dedicated game server]
```

### Əsas komponentlər (Main components)
- **Queue Service** — player queue-ya girir (skill, region, mode, partyId)
- **Match Finder** — hər 1-2 saniyədən queue-ları scan edib match formalaşdırır
- **Session Service** — match-ə sessionId, token verir
- **Game Server Allocator** — bare-metal server / Kubernetes pod / AWS GameLift fleet-dən server götürür
- **WebSocket Gateway** — client-lərə "match found" event push edir

---

## Matching alqoritmləri (Matching algorithms)

### 1. Adaptive bucketing (ən çox istifadə olunan)

```
window = ±100 MMR
if wait_time > 30s: window = ±200
if wait_time > 60s: window = ±500
if wait_time > 120s: window = ±1000 (cross-region too)
```

Vaxt keçdikcə constraint-ləri boşaldır. Valorant, LoL, Dota 2 bu yanaşmadan istifadə edir.

### 2. Skill + region + ping grid search
2D grid: (region, skill_bucket). Match Finder əvvəlcə eyni cell, sonra qonşu cell-lərə baxır.

### 3. Graph-based
Hər player node, compatible player-lər arasında edge (skill fərqi < 100). N nəfərlik match → graph-da clique tapmaq (NP-hard, amma kiçik window üçün heuristic işləyir).

### 4. Bipartite matching (role-based)
Overwatch, LoL: tank/dps/healer rollarına görə iki komanda balans. Hungarian algorithm və ya min-cost flow.

---

## Party handling (Party handling)

- Party 2-5 nəfərlik group bir queue entry kimi daxil olur
- `effective_mmr = avg(party_mmrs)` və ya `max(mmrs)` (skill disparity cəza)
- Komandanı tarazlamaq üçün qarşı tərəfdə oxşar ölçülü party axtarılır
- Solo player + 4-nəfərlik party = dezavantaj → ayrı queue (solo/duo vs party)

---

## Game server allocation (Game server allocation)

Match tapıldıqdan sonra:
1. Allocator lazımi region-da boş server axtarır (Agones Kubernetes, GameLift fleet, öz bare-metal pool)
2. Server reservation edilir, IP + port + sessionToken qaytarılır
3. Client-lərə WebSocket-la push olur: `{"event":"match_found","server":"1.2.3.4:7777","token":"..."}`
4. Client UDP/TCP ilə server-ə qoşulur, token-i verifikasiya edir

**Agones (K8s)** — game server-lər Pod kimi, CRD ilə lifecycle (Ready → Allocated → Shutdown).

---

## Session state və reconnect (Session state & reconnect)

- Authoritative server: state server-dədir (anti-cheat)
- Session token JWT formatında, 2 saat TTL
- Disconnect → 60-120 saniyə rejoin pəncərəsi
- Server crash olarsa session state snapshot-dan recovery (hər 30s snapshot Redis-ə)

---

## Anti-smurf və leaver prevention (Anti-smurf & leaver prevention)

### Smurf detection
- Yeni akkaunt + çox yüksək win rate → hidden MMR tez artır
- Hidden MMR visible rank-dan yüksəkdirsə, yüksək-rank queue-ya at
- Machine learning model: first 10 game statistics → predict real skill

### Leaver penalty
- İlk leave: xəbərdarlıq
- 3+ leave/gün: 30 dəqiqə queue ban
- Chronic leaver: MMR -50, priority queue deprioritize
- Ranked mode-da sərt cəza (LP/SR deduction)

---

## Backfill (Backfill)

Player mid-game çıxarsa:
- Queue-dan oxşar MMR-li player tap
- `backfill_window = ±150 MMR, same region, casual mode only`
- Ranked-də backfill YOXDU (integrity üçün) — bot və ya surrender

---

## Queue priorities (Queue priorities)

Priority score formulası:
```
priority = time_in_queue * 1.0
         + return_bonus (recently won: +5)
         + idle_bonus (long inactive: +3)
         + vip_bonus (premium: +10)
         - leaver_penalty (-20)
```

Uzun gözləyən player daha yüksək priority → match-ə daha tez daxil olur.

---

## Data model (Data model)

```sql
-- PostgreSQL (persistent)
CREATE TABLE players (
    id BIGINT PRIMARY KEY,
    username VARCHAR(50),
    mmr INT DEFAULT 1000,            -- visible
    hidden_mmr INT DEFAULT 1000,     -- real
    region VARCHAR(10),
    leaver_score INT DEFAULT 0,
    created_at TIMESTAMPTZ
);

CREATE TABLE matches (
    id UUID PRIMARY KEY,
    server_id VARCHAR(50),
    mode VARCHAR(20),
    region VARCHAR(10),
    started_at TIMESTAMPTZ,
    ended_at TIMESTAMPTZ,
    winner_team SMALLINT
);

CREATE TABLE match_players (
    match_id UUID,
    player_id BIGINT,
    team SMALLINT,
    mmr_before INT,
    mmr_after INT,
    kda VARCHAR(20),
    PRIMARY KEY (match_id, player_id)
);

-- Redis (ephemeral queue)
-- Key: queue:{mode}:{region}   (sorted set, score = MMR)
-- Member: player_id or party_id
-- TTL: 10 min (stale removal)
```

---

## Redis sorted set + Lua atomic match (Redis sorted set)

Queue Redis-də sorted set kimi saxlanılır, score = MMR:

```
ZADD queue:ranked:eu 1450 "player:123"
ZADD queue:ranked:eu 1478 "player:456"
ZRANGEBYSCORE queue:ranked:eu 1400 1500
```

**Lua script** (atomic scan + remove):
```lua
-- KEYS[1] = queue key, ARGV[1] = target MMR, ARGV[2] = window, ARGV[3] = N
local min = tonumber(ARGV[1]) - tonumber(ARGV[2])
local max = tonumber(ARGV[1]) + tonumber(ARGV[2])
local players = redis.call('ZRANGEBYSCORE', KEYS[1], min, max, 'LIMIT', 0, tonumber(ARGV[3]))
if #players >= tonumber(ARGV[3]) then
    for _, p in ipairs(players) do
        redis.call('ZREM', KEYS[1], p)
    end
    return players
end
return {}
```

Atomic-liyə görə iki match finder eyni player-i götürə bilmir.

---

## Laravel sketch (Laravel sketch)

### Queue join endpoint
```php
// routes/api.php
Route::post('/queue/join', [QueueController::class, 'join']);
Route::post('/queue/leave', [QueueController::class, 'leave']);

// app/Http/Controllers/QueueController.php
public function join(Request $request)
{
    $player = $request->user();
    $mode = $request->input('mode');     // 'ranked' | 'casual'
    $region = $player->region;
    $key = "queue:{$mode}:{$region}";

    Redis::zadd($key, $player->hidden_mmr, "player:{$player->id}");
    Redis::hset("queue_meta:{$player->id}", [
        'joined_at' => now()->timestamp,
        'mode' => $mode,
    ]);

    return response()->json(['status' => 'queued', 'eta' => 30]);
}
```

### Match Finder (Horizon job, recurring every 2s)
```php
// app/Jobs/FindMatchesJob.php
class FindMatchesJob implements ShouldQueue
{
    public function handle()
    {
        $regions = ['eu', 'na', 'asia'];
        foreach ($regions as $region) {
            $this->scanQueue("ranked", $region);
        }
    }

    private function scanQueue(string $mode, string $region)
    {
        $key = "queue:{$mode}:{$region}";
        $players = Redis::zrange($key, 0, -1, ['withscores' => true]);

        // Adaptive window per player
        foreach ($players as $id => $mmr) {
            $waitTime = $this->getWait($id);
            $window = $waitTime > 60 ? 500 : ($waitTime > 30 ? 200 : 100);
            $n = 10; // 5v5

            $match = Redis::eval(
                $this->luaScript(),
                1,
                $key, $mmr, $window, $n
            );

            if (count($match) === $n) {
                $this->createMatch($match, $mode, $region);
            }
        }
    }

    private function createMatch(array $playerIds, string $mode, string $region)
    {
        $server = GameServerAllocator::reserve($region);
        $matchId = Str::uuid();

        Match::create([
            'id' => $matchId,
            'server_id' => $server->id,
            'mode' => $mode,
            'region' => $region,
        ]);

        foreach ($playerIds as $pid) {
            broadcast(new MatchFoundEvent($pid, $matchId, $server->endpoint));
        }
    }
}
```

### WebSocket push (Laravel Reverb / Pusher)
```php
class MatchFoundEvent implements ShouldBroadcast
{
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("player.{$this->playerId}");
    }
}
```

---

## Trade-offs

| Yanaşma | Üstünlük | Çatışmazlıq |
|---------|----------|-------------|
| Strict skill window (±50) | Çox balanslı match | Uzun queue time |
| Loose window (±500) | Tez match | Pis təcrübə, skill mismatch |
| **Adaptive window** | Balans | Kompleks tuning |
| Cross-region fallback | Queue boşalır | Yüksək ping |
| Role-based (bipartite) | Adil komanda | Daha az adamda işləmir |

**Standart seçim:** Adaptive bucketing + region-first + cross-region fallback 120s sonra.

---

## Praktik Tapşırıqlar

**1. Niyə Redis sorted set queue üçün uyğundur?**
ZADD/ZRANGEBYSCORE O(log N), MMR score kimi ideal. Range scan ±window içində player tapmaq üçün çox sürətli.

**2. Elo ilə TrueSkill fərqi nədir?**
Elo — 1v1, tək dəyər. TrueSkill — komanda, Bayesian, `(mu, sigma)` uncertainty-ni nəzərə alır. Yeni oyunçu üçün TrueSkill daha ədalətli (böyük sigma → tez düzəlir).

**3. Match Finder horizontal scale necə olur?**
Region başına ayrı worker — EU worker yalnız `queue:*:eu` açarlarını scan edir. Mode başına da ayrıla bilər. Lua script atomic olduğu üçün race condition yoxdur.

**4. Player eyni anda 2 queue-ya girə bilərmi?**
Xeyr. Queue join zamanı lock: `SETNX player_queue:{id} "mode:region"`, TTL 10 min. Əgər mövcuddursa, əvvəlcə leave tələb olunur.

**5. Smurf detection necə işləyir?**
Hidden MMR + win rate analizi. Yeni akkaunt 70%+ win rate ilk 20 oyunda → hidden MMR aqressiv artır, visible rank-dan kənara çıxır. ML model də əlavə siqnallar istifadə edir (APM, headshot %, map knowledge).

**6. Game server allocation latency necə azaldılır?**
Pre-warmed server pool (Agones-da 10-20 Ready state pod hər region). Match tapıldığında `Allocate` CRD mutasiyası ~100ms — yeni pod yaratmaqdan (30s+) çox tez.

**7. Backfill niyə ranked-də yoxdur?**
Match integrity: oyun başlayandan sonra join etmək unfair advantage (score behind, məhdud vaxt). Ranked-də surrender və ya AFK bot. Casual mode-da backfill oyun təcrübəsini daha yaxşı saxlayır.

**8. Queue wait uzun olursa nə olur?**
Adaptive window genişlənir (±100 → ±500 → cross-region). 120s-dən sonra "expanded search" bildirişi göstərilir. 5 dəqiqə sonra bot backfill və ya queue leave təklif olunur.

---

## Praktik Baxış

- **Hidden MMR saxlayın** — visible rank ilə real skill ayrı; smurf və placement üçün lazım
- **Adaptive window** — strict başlayın, vaxt keçdikcə boşaldın
- **Region-first, cross-region fallback** — ping keyfiyyətə təsir edir
- **Atomic match formation** — Redis Lua və ya DB transaction; iki finder eyni player götürməsin
- **Pre-warmed server pool** — allocation latency üçün kritik (< 500ms)
- **Leaver penalty konsistent tətbiq** — qısa queue ban + MMR deduction
- **Party vs solo ayrı queue** — 5-stack vs 5-solo ədalətsizdir
- **Role-based matching** — team games üçün (tank/dps/healer)
- **Metrics tracking** — queue time p50/p95/p99, match quality, abandon rate, region imbalance
- **Graceful degradation** — match finder down olsa, queue entries itməsin (Redis persistence AOF)
- **A/B test rating formula** — K-factor və window tuning canary deployment ilə
- **Observability** — Prometheus metrics: `queue_depth`, `match_rate`, `avg_wait_time`, `mmr_skew`

---

## Əlaqəli Mövzular

- [Session token / JWT](../security/)
- [WebSocket real-time push](../case-studies/discord-architecture.md)
- [Redis sorted set patterns](../system-design/)
- [Kubernetes stateful workloads (Agones)](../devops/)
- [Queue priorities and fair scheduling](../system-design/)
