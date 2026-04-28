# Leaderboard / Ranking System — DB Design (Middle ⭐⭐)

## İcmal

Leaderboard sistemlər oyun, fitness, coding platform (LeetCode), e-commerce (top sellers) kimi bir çox sahədə istifadə olunur. Əsas tələb: real-time ranking, verimli top-N sorğusu, istifadəçinin öz mövqeyini tapmaq. Redis Sorted Set bu problemi üçün yaradılmış kimi görünür.

---

## Tövsiyə olunan DB Stack

```
Rankings:   Redis Sorted Sets  (real-time, O(log N) update)
Metadata:   PostgreSQL         (user profiles, detailed stats)
History:    PostgreSQL         (keçmiş season-lar, arxiv)
```

---

## Niyə Redis Sorted Set?

```
Redis Sorted Set: hər member-in score-u var → avtomatik sıralanır

ZADD:   O(log N) — score + member əlavə et / update et
ZRANK:  O(log N) — member-in rank-ını tap
ZRANGE: O(log N + M) — top-N əldə et (M = nəticə sayı)
ZSCORE: O(1) — member-in score-unu tap

PostgreSQL alternativ:
  SELECT RANK() OVER (ORDER BY score DESC)
  FROM scores WHERE user_id = ?
  100M+ row-da: 500ms+

Redis:
  ZRANK leaderboard 100M+ user-də: < 1ms
  Dedicate data structure — custom index lazım deyil
```

---

## Redis Schema

```
# Global all-time leaderboard
ZADD leaderboard:global {score} {user_id}
ZREVRANK leaderboard:global {user_id}        → rank (0 = 1st, high score)
ZSCORE leaderboard:global {user_id}          → user's score
ZREVRANGE leaderboard:global 0 9             → top 10 (highest first)
ZREVRANGE leaderboard:global 0 9 WITHSCORES  → top 10 with scores
ZCARD leaderboard:global                     → total players

# Score artırma (atomic)
ZINCRBY leaderboard:global 250 {user_id}     → +250 points

# Country leaderboard
ZADD leaderboard:country:AZ {score} {user_id}

# Seasonal
ZADD leaderboard:season:2026-04 {score} {user_id}
ZADD leaderboard:week:2026-W17  {score} {user_id}

# User-in rank-ı (1-indexed)
rank = ZREVRANK(leaderboard:global, user_id) + 1

# "Around me" — ±5 nəfər
my_rank = ZREVRANK leaderboard:global {user_id}   → 0-indexed
ZREVRANGE leaderboard:global (my_rank-5) (my_rank+5) WITHSCORES
-- 11 nəticə: 5 yuxarı + özü + 5 aşağı

# Friends leaderboard (intersection)
# friend_ids = [456, 789, 321, ...]
ZINTERSTORE friends_lb:user123 N {key1} {key2} ...  -- N = count
ZREVRANGE friends_lb:user123 0 9 WITHSCORES
```

---

## PostgreSQL Schema

```sql
-- ==================== SCORES ====================
CREATE TABLE user_scores (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id),

    total_score BIGINT NOT NULL DEFAULT 0,
    level       INT DEFAULT 1,

    -- Season key: '2026-04' (monthly), '2026-W17' (weekly), NULL = all-time
    season      VARCHAR(20),

    -- Detailed stats
    wins        INT DEFAULT 0,
    losses      INT DEFAULT 0,
    streak      INT DEFAULT 0,       -- current win streak
    best_streak INT DEFAULT 0,

    last_activity TIMESTAMPTZ DEFAULT NOW(),
    created_at    TIMESTAMPTZ DEFAULT NOW(),

    UNIQUE (user_id, season)
);

CREATE INDEX idx_scores_season_score ON user_scores(season, total_score DESC);
CREATE INDEX idx_scores_user         ON user_scores(user_id);

-- ==================== SCORE EVENTS ====================
-- Audit: kim nə zaman neçə point qazandı
CREATE TABLE score_events (
    id           BIGSERIAL,
    user_id      BIGINT NOT NULL,

    points       INT NOT NULL,           -- müsbət: qazanc, mənfi: itki
    reason       VARCHAR(100) NOT NULL,  -- 'match_win', 'daily_quest', 'penalty'
    reference_id BIGINT,                 -- match_id, quest_id, etc.

    season       VARCHAR(20),

    created_at   TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- ==================== SEASONS ====================
CREATE TABLE seasons (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(50) NOT NULL,        -- 'Season 1', 'April 2026'
    season_key  VARCHAR(20) UNIQUE NOT NULL, -- '2026-04'

    starts_at   TIMESTAMPTZ NOT NULL,
    ends_at     TIMESTAMPTZ NOT NULL,

    -- Top player mükafatları
    rewards     JSONB DEFAULT '[]',
    -- [{rank: 1, prize: "Gold Trophy", badge: "gold"}, ...]

    is_active   BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== SEASON FINAL RANKINGS ====================
-- Season bitdikdən sonra arxiv
CREATE TABLE season_rankings (
    id          BIGSERIAL PRIMARY KEY,
    season_id   INT NOT NULL REFERENCES seasons(id),
    user_id     BIGINT NOT NULL,

    final_rank  INT NOT NULL,
    final_score BIGINT NOT NULL,

    reward_tier VARCHAR(20),    -- 'diamond', 'gold', 'silver', 'bronze'

    created_at  TIMESTAMPTZ DEFAULT NOW(),

    UNIQUE (season_id, user_id)
);
```

---

## Leaderboard Növləri

```
1. Global All-Time:
   ZADD leaderboard:all-time {score} {user_id}
   Redis-də daim saxlanır, reset edilmir

2. Seasonal (aylıq / həftəlik):
   ZADD leaderboard:season:2026-04 {score} {user_id}
   Season bitmişdən sonra → PostgreSQL-ə arxiv → Redis-dən sil

3. Friends Leaderboard:
   PostgreSQL: user-in friend-lərini tap
   Redis pipeline: N × ZSCORE leaderboard:global {friend_id}
   Nəticəni Python/PHP-da sort et
   
   Alt: ZINTERSTORE (server-side) — böyük friend list üçün

4. Country/Regional:
   ZADD leaderboard:country:AZ {score} {user_id}
   User ölkəsini dəyişdikdə: köhnə country-dən ZREM, yeniyə ZADD

5. Guild / Team Leaderboard:
   ZADD leaderboard:guild:{guild_id} {score} {user_id}
   Guild aggregate: ZSCORE × members → toplam

6. Percentile Ranking:
   my_rank = ZREVRANK + 1
   total = ZCARD
   percentile = (1 - my_rank / total) × 100
   "Sən top %5-dəsən"
```

---

## Score Update Pipeline

```
Match/event bitdi → Score yenilənməlidir:

1. Game server: match nəticəsini hesabla (+250 points)

2. Atomic DB transaction:
   BEGIN;
     -- Audit log
     INSERT INTO score_events (user_id, points, reason, reference_id, season)
     VALUES (:user_id, 250, 'match_win', :match_id, :current_season);
     
     -- Aggregated score
     INSERT INTO user_scores (user_id, total_score, wins, season)
     VALUES (:user_id, 250, 1, :season)
     ON CONFLICT (user_id, season)
     DO UPDATE SET
       total_score   = user_scores.total_score + 250,
       wins          = user_scores.wins + 1,
       last_activity = NOW();
   COMMIT;

3. Redis update:
   ZINCRBY leaderboard:global 250 {user_id}
   ZINCRBY leaderboard:season:2026-04 250 {user_id}
   ZINCRBY leaderboard:country:AZ 250 {user_id}

Sync vs Async:
  Sync: DB commit → Redis update (simple, consistent)
  Async: DB commit → Kafka → consumer updates Redis (decoupled, lag ~100ms)
  
  Gaming/real-time: sync (user dərhal sıralama görür)
  High-traffic (10K+ events/s): Kafka (Redis bottleneck-i aradan qaldırır)
```

---

## Season Reset

```
Season sonu (cron job):

1. Final ranking-ləri PostgreSQL-ə yaz:
   INSERT INTO season_rankings (season_id, user_id, final_rank, final_score)
   SELECT
     :season_id,
     user_id,
     RANK() OVER (ORDER BY total_score DESC),
     total_score
   FROM user_scores
   WHERE season = :season_key;

2. Reward-ları pay et:
   Rank 1:       Diamond trophy
   Rank 2-10:    Gold trophy
   Rank 11-100:  Silver trophy
   Top 10%:      Bronze trophy

3. Redis season key-ini sil:
   DEL leaderboard:season:2026-04

4. Yeni season başla:
   UPDATE seasons SET is_active = FALSE WHERE is_active = TRUE;
   INSERT INTO seasons (name, season_key, starts_at, ends_at, is_active)
   VALUES ('May 2026', '2026-05', '2026-05-01', '2026-05-31', TRUE);
   
   -- user_scores reset (yeni season):
   INSERT INTO user_scores (user_id, season, total_score)
   SELECT id, '2026-05', 0 FROM users WHERE is_active = TRUE
   ON CONFLICT DO NOTHING;

5. All-time leaderboard reset edilmir.
```

---

## "Around Me" Feature

```
"Mənim mövqeyim nədir? ±5 nəfər kim?"

Redis (O(log N)):
  my_rank = ZREVRANK leaderboard:global user_id  -- 0-indexed
  
  start = max(0, my_rank - 5)
  end   = my_rank + 5
  
  results = ZREVRANGE leaderboard:global start end WITHSCORES
  → [{user_id: "456", score: "9500"}, ..., {user_id: "789", score: "8100"}]

User metadata batch fetch (PostgreSQL):
  SELECT id, username, avatar_url, country
  FROM users
  WHERE id IN (456, 789, 321, ...)  -- extracted from Redis results
  
Pipeline ilə birləşdir → JSON response

Percentile:
  total = ZCARD leaderboard:global
  my_rank_1indexed = my_rank + 1
  pct = ((total - my_rank_1indexed) / total) × 100
  → "Sən top 3.2%-dəsən (7,450 oyunçudan 241-ci)"
```

---

## Anti-Patterns

```
✗ PostgreSQL-də real-time rank hesablamaq:
  SELECT RANK() OVER (ORDER BY score DESC) FROM user_scores
  100M row → full scan + sort → 500ms+
  Redis ZREVRANK: O(log N) → < 1ms

✗ Hər score update-də tam leaderboard rebuild:
  Batch job: "hər 5 dəqiqə recalculate" → stale data
  Redis ZINCRBY: atomic, instant, O(log N)

✗ Friends leaderboard-u N ayrı Redis call-u ilə:
  for friend in friends: ZSCORE(friend)  → N round-trips
  Pipeline: batch ZSCORE → 1 round-trip

✗ Season data-nı Redis-də həmişəlik saxlamaq:
  Season bitdi → Redis-dən sil (memory waste)
  PostgreSQL-ə arxiv et

✗ Server-side score hesablama yoxdur:
  Client "men 99999 point qazan" göndərir → DB-yə yazılır
  Score mütləq server-side hesablanmalıdır
  score_events audit trail: manipulation-ı detect etməyə imkan verir

✗ Tie-breaking mexanizmi yoxdur:
  Eyni score → kim daha yüksəkdir?
  Tie-break: score * 1M + (MAX_TIMESTAMP - achieved_at)
  Redis float score-u encode etmək mümkündür
```

---

## Tie-Breaking Trick

```
Problem: 2 user eyni score (9500) → kim 1st?

Həll: Composite score (earlier achievement wins)
  score_key = points × 10^6 + (MAX_EPOCH - achieved_at_epoch)
  
  User A: 9500 points, achieved at 1714316100 → 9500000000 - 1714316100 = 7785683900
  User B: 9500 points, achieved at 1714300000 → 9500000000 - 1714300000 = 7785700000
  
  B score > A score → B ranks higher (achieved earlier)

Redis float precision:
  Redis sorted set score: 64-bit IEEE 754 float
  Safe integer range: ±2^53 ≈ 9 quadrillion
  Bu trick safe integer range-dədir
```

---

## Tanınmış Sistemlər

```
LeetCode:
  Global contest ranking: accepted problems + contest rating
  Weekly contest: real-time leaderboard (Redis)
  Rating: Elo-like algorithm
  PostgreSQL + Redis

Duolingo:
  Weekly leagues: user-lər 30-nəfərlik qruplara bölünür
  Group-specific Redis sorted set
  Week sonunda promotion / demotion (tier system)
  Gamification: streak + XP

PUBG / Clash of Clans:
  Season-based (aylıq reset)
  Clan leaderboard: guild top teams
  Redis Cluster: global scale (millions of concurrent)

LeetCode Ranking:
  Contest: top-N real-time display
  After contest: final rank saved to PostgreSQL
  Historical rank progression tracked

Strava:
  Segment leaderboard (activity-specific)
  KOM: King of the Mountain = #1 on segment
  Friends-only filter
  PostgreSQL + Redis hybrid
```
