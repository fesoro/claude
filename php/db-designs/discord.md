# Discord — DB Design & Technology Stack

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    Discord Database Stack                        │
├──────────────────────┬──────────────────────────────────────────┤
│ Cassandra → ScyllaDB │ Messages (primary message store)         │
│ PostgreSQL           │ Users, guilds, channels, relationships   │
│ Redis                │ Sessions, presence, rate limiting        │
│ Elasticsearch        │ Message search                           │
│ Amazon S3            │ Media files, attachments                 │
│ Apache Kafka         │ Event streaming, audit logs              │
│ Rust (voice infra)   │ Voice/video servers (custom)             │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Cassandra → ScyllaDB Migrasyonu

```
Discord 2017: Cassandra seçildi
  Reason: Write-heavy, time-series messages
  "Messages ordered by time per channel"
  
2017-2022: Cassandra problemləri
  Latency spikes: GC pauses (JVM-based)
  Hot partitions: populyar serverlər → single node overload
  Compaction storms: random latency spikes
  
2022: ScyllaDB-ya keçid
  "How Discord Stores Trillions of Messages" (blog post)
  
ScyllaDB nədir?
  Cassandra-compatible (eyni CQL syntax)
  C++ ilə yazılmış (JVM yox)
  No GC pauses → predictable latency
  Shard-per-core: CPU core-lardan max istifadə
  
Nəticə:
  P99 latency: 40ms → 15ms
  Hot partition problemi azaldı
  Cost reduction (fewer nodes needed)
  
Discord mesaj statistikası:
  4B+ messages/day
  Trillions of messages total
  ScyllaDB: 177 nodes handling this
```

---

## ScyllaDB Message Schema

```cql
-- Discord-un əsl mesaj saxlama sxemi (inferred from blog post)

CREATE TABLE messages (
    channel_id  BIGINT,
    message_id  BIGINT,    -- Snowflake ID (time-sortable)
    
    author_id   BIGINT NOT NULL,
    content     TEXT,
    
    -- Embeds, attachments
    embeds      LIST<FROZEN<MAP<TEXT, TEXT>>>,
    attachments LIST<FROZEN<MAP<TEXT, TEXT>>>,
    
    -- Reactions
    reactions   LIST<FROZEN<MAP<TEXT, BIGINT>>>,
    
    -- Edit history
    edited_at   TIMESTAMP,
    
    -- Flags
    is_pinned   BOOLEAN,
    mention_everyone BOOLEAN DEFAULT FALSE,
    
    PRIMARY KEY (channel_id, message_id)
) WITH CLUSTERING ORDER BY (message_id DESC);

-- Niyə channel_id partition key?
-- "Get messages for channel X" = ən ümumi sorğu
-- Bütün kanal mesajları eyni partition-da → fast range scan

-- Problem: Populyar kanal → hot partition
-- Həll: "Bucket" strategy

-- Bucketed approach (Discord 2022 blog):
CREATE TABLE messages_v2 (
    channel_id  BIGINT,
    bucket      INT,        -- time-based bucket
    message_id  BIGINT,
    
    author_id   BIGINT NOT NULL,
    content     TEXT,
    
    PRIMARY KEY ((channel_id, bucket), message_id)
) WITH CLUSTERING ORDER BY (message_id DESC);

-- bucket = message_timestamp / BUCKET_SIZE_MS
-- BUCKET_SIZE = 10 days worth of messages
-- Popular channel → many buckets → distributed load
```

---

## Hot Partition Problem & Solution

```
Problem:
  #general channel → 1M+ messages/day
  All routed to one ScyllaDB node
  → Overload

Discord 2022 həlli: Data Services

Before (direct Cassandra):
  Discord API → Cassandra
  Hot channel = hot node

After (Data Service + ScyllaDB):
  Discord API → Data Service (Go) → ScyllaDB
  
  Data Service:
  ✓ In-memory cache for hot channels
  ✓ Request coalescing (N requests → 1 DB query)
  ✓ Consistent hashing per channel_id
  
  Additionally: Super Disks
  Old messages (>14 days) → UNLOADED from Cassandra → object storage
  Recent messages → ScyllaDB (hot tier)
  Historical → S3-based cold storage
```

---

## PostgreSQL Schema

```sql
-- ==================== USERS ====================
CREATE TABLE users (
    id          BIGINT PRIMARY KEY,  -- Snowflake ID
    username    VARCHAR(32) NOT NULL,
    discriminator CHAR(4),           -- Legacy: username#1234
    global_name VARCHAR(32),         -- New display name (2023)
    email       VARCHAR(255) UNIQUE NOT NULL,
    
    avatar_hash VARCHAR(64),
    banner_hash VARCHAR(64),
    
    is_bot      BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE,
    
    -- Nitro subscription
    premium_type SMALLINT DEFAULT 0,  -- 0: none, 1: classic, 2: nitro
    
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== GUILDS (Servers) ====================
CREATE TABLE guilds (
    id              BIGINT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    owner_id        BIGINT NOT NULL REFERENCES users(id),
    
    -- Appearance
    icon_hash       VARCHAR(64),
    banner_hash     VARCHAR(64),
    splash_hash     VARCHAR(64),
    
    -- Settings
    verification_level SMALLINT DEFAULT 0,
    -- 0: none, 1: email, 2: 5min, 3: 10min, 4: phone
    
    explicit_content_filter SMALLINT DEFAULT 0,
    default_message_notifications SMALLINT DEFAULT 0,
    
    -- Boost (Nitro)
    premium_tier    SMALLINT DEFAULT 0,  -- 0-3
    boost_count     INT DEFAULT 0,
    
    member_count    INT DEFAULT 0,
    
    created_at      TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CHANNELS ====================
CREATE TABLE channels (
    id              BIGINT PRIMARY KEY,
    guild_id        BIGINT REFERENCES guilds(id),
    parent_id       BIGINT REFERENCES channels(id),  -- category
    
    type            SMALLINT NOT NULL,
    -- 0: text, 1: dm, 2: voice, 3: group_dm, 4: category,
    -- 5: news, 10/11/12: thread types, 13: stage, 15: forum
    
    name            VARCHAR(100),
    topic           VARCHAR(1024),
    
    position        SMALLINT,
    
    -- Rate limiting
    rate_limit_per_user INT DEFAULT 0,  -- slow mode (seconds)
    
    -- Voice
    bitrate         INT,
    user_limit      SMALLINT,
    
    -- Thread specific
    message_count   INT,
    thread_metadata JSONB,
    
    is_nsfw         BOOLEAN DEFAULT FALSE,
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    INDEX idx_guild (guild_id, position)
);

-- ==================== GUILD MEMBERS ====================
CREATE TABLE guild_members (
    guild_id    BIGINT NOT NULL REFERENCES guilds(id),
    user_id     BIGINT NOT NULL REFERENCES users(id),
    
    nickname    VARCHAR(32),
    avatar_hash VARCHAR(64),
    
    joined_at   TIMESTAMPTZ DEFAULT NOW(),
    is_pending  BOOLEAN DEFAULT FALSE,  -- membership screening
    
    PRIMARY KEY (guild_id, user_id),
    INDEX idx_user (user_id)
);

-- ==================== ROLES ====================
CREATE TABLE roles (
    id          BIGINT PRIMARY KEY,
    guild_id    BIGINT NOT NULL REFERENCES guilds(id),
    name        VARCHAR(100) NOT NULL,
    
    color       INT DEFAULT 0,       -- RGB integer
    permissions BIGINT DEFAULT 0,    -- bitmask
    position    SMALLINT DEFAULT 0,
    
    is_hoisted  BOOLEAN DEFAULT FALSE,  -- show separately in member list
    is_mentionable BOOLEAN DEFAULT FALSE,
    
    INDEX idx_guild (guild_id, position)
);

CREATE TABLE member_roles (
    guild_id    BIGINT NOT NULL,
    user_id     BIGINT NOT NULL,
    role_id     BIGINT NOT NULL REFERENCES roles(id),
    PRIMARY KEY (guild_id, user_id, role_id)
);
```

---

## Read States: "Unread Messages" Problem

```
Discord-un ən çətin problemi:
  User hər kanalda son oxunmuş mesajı bilməlidir
  500M+ users × 19M servers × ortalama channels...
  
  Read State = "Bu kanaldakı son oxunmuş mesaj ID"

Naive approach:
  Hər user × hər kanal üçün row saxla
  500M users × 1000 channels = 500B rows!  → imkansız

Discord-un həlli:
  Yalnız "acknowledged" olanları saxla
  Default: yeni mesajlar unread (last_message_id tracking)

Redis (hot):
  HSET read_state:{user_id} {channel_id} {last_read_message_id}
  
ScyllaDB (persistent):
  CREATE TABLE read_states (
      user_id         BIGINT,
      channel_id      BIGINT,
      last_message_id BIGINT,  -- last acknowledged
      mention_count   INT DEFAULT 0,
      PRIMARY KEY (user_id, channel_id)
  );

Unread check:
  channel.last_message_id > read_state.last_message_id
  → Unread messages exist!
  
  Count: SELECT COUNT(*) FROM messages
         WHERE channel_id = ? AND message_id > {last_read}
  → Approximate (Discord shows "99+" not exact)
```

---

## Presence System

```
Online/Offline status:

Redis Pub/Sub:
  User connects → publish presence:online:{user_id}
  User disconnects → publish presence:offline:{user_id}
  
  SET user:presence:{user_id} {status_json} EX 60
  -- Heartbeat every 30 seconds
  -- Expires in 60 seconds → auto offline

Status types:
  online, idle, dnd (do not disturb), invisible, offline

Guild presence:
  User X online → notify all guild members where X is
  Challenge: Large guilds (millions of members)
  
  Discord-un həlli:
  Very large guilds (100K+ members): presence disabled by default
  "Large Guild" threshold: special handling
  Pub/Sub → fan-out to connected gateways only
```

---

## Scale Faktları

```
Numbers (2023):
  500M+ registered users
  150M+ monthly active users
  19M+ active servers daily
  4B+ messages/day
  
  ScyllaDB: 177 nodes, trillions of messages
  Voice: peak 8M+ concurrent voice users
  
Message storage:
  Hot (recent): ScyllaDB
  Cold (old): Object storage (S3-like)
  
Infrastructure:
  Multiple data centers
  WebSocket connections: tens of millions concurrent
  Voice/Video: Rust-based media servers
  
Engineering:
  ~600 engineers (2023)
  "We store more messages than any other platform"
```

---

## Discord-dan Öyrəniləcəklər

```
1. Cassandra → ScyllaDB:
   Same API, better performance
   GC pauses = latency enemy
   C++ > JVM for predictable latency

2. Hot partition → bucketing:
   Time-based buckets per channel
   Distributes load across nodes
   
3. Read states approximate:
   Exact unread count: too expensive
   "99+" is good enough UX
   Trade precision for scalability

4. Snowflake IDs everywhere:
   message_id time-sortable
   No separate timestamp needed for ordering

5. Data Service pattern:
   Middleware between API and DB
   Caching, coalescing, routing
   Decouples scaling independently

6. Cold storage tiering:
   Hot: ScyllaDB (fast, expensive)
   Cold: Object storage (slow, cheap)
   Age-based migration
```
