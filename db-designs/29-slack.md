# Slack — DB Design & Technology Stack (Senior ⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                     Slack Database Stack                         │
├──────────────────────┬──────────────────────────────────────────┤
│ MySQL (Vitess)       │ Messages, channels, workspaces, users    │
│ Redis                │ Presence, sessions, pub/sub, cache       │
│ Solr / Elasticsearch │ Message search                           │
│ Amazon S3            │ File uploads, attachments                │
│ Apache Kafka         │ Event streaming, audit logs              │
│ Flannel              │ Slack's custom cache (pub/sub)           │
└──────────────────────┴──────────────────────────────────────────┘

Slack-ın fərqli seçimi:
  Cassandra-dan fərqli olaraq MySQL seçdi
  Vitess (2018): MySQL-i horizontally scale etdi
  "We chose MySQL because of strong consistency requirements"
```

---

## MySQL → Vitess

```
Slack 2015-2018: MySQL sharding problemi
  Workspace = shard unit
  "Tenant-based sharding"
  
  Problem:
  Böyük workspace (IBM: 50K users) → hot shard
  Workspace-ları manual redistribute etmək çətin
  
2018: Vitess adoption
  Same team that built Vitess at YouTube
  Vitess: transparent resharding
  VSchema: logical → physical shard mapping
  
Slack-ın Vitess konfigurasyonu:
  VKeyspace: workspaces sharded by workspace_id
  MoveTables: workspace-ı başqa sharda köçür (zero downtime)
  Resharding: shard-ları split et (2→4, 4→8)

Workspace isolation:
  Hər workspace-ın datası eyni shard-da
  Workspace-daxili query = single shard (fast)
  Cross-workspace query = rare (scatter-gather)
```

---

## Schema Design

```sql
-- ==================== WORKSPACES (Teams) ====================
CREATE TABLE workspaces (
    id          BIGINT UNSIGNED PRIMARY KEY,   -- Vitess shard key!
    subdomain   VARCHAR(63) UNIQUE NOT NULL,   -- company.slack.com
    name        VARCHAR(100) NOT NULL,
    
    plan        ENUM('free', 'pro', 'business', 'enterprise') DEFAULT 'free',
    
    -- Limits by plan
    message_retention_days INT DEFAULT 90,   -- free: 90 days
    
    is_verified BOOLEAN DEFAULT FALSE,
    
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== USERS ====================
CREATE TABLE users (
    id              BIGINT UNSIGNED PRIMARY KEY,
    workspace_id    BIGINT UNSIGNED NOT NULL,  -- shard key
    
    email           VARCHAR(255) NOT NULL,
    display_name    VARCHAR(80) NOT NULL,
    real_name       VARCHAR(80),
    
    status_text     VARCHAR(100),
    status_emoji    VARCHAR(50),
    status_expires  DATETIME,
    
    -- Appearance
    profile_image   TEXT,
    
    -- Role
    is_admin        BOOLEAN DEFAULT FALSE,
    is_owner        BOOLEAN DEFAULT FALSE,
    is_bot          BOOLEAN DEFAULT FALSE,
    
    -- Presence (stored in Redis, not here)
    timezone        VARCHAR(50),
    
    UNIQUE INDEX idx_workspace_email (workspace_id, email)
) ENGINE=InnoDB;

-- ==================== CHANNELS ====================
CREATE TABLE channels (
    id              BIGINT UNSIGNED PRIMARY KEY,
    workspace_id    BIGINT UNSIGNED NOT NULL,
    
    name            VARCHAR(80) NOT NULL,
    purpose         VARCHAR(250),
    topic           VARCHAR(250),
    
    type            ENUM('public', 'private', 'mpim', 'im') NOT NULL,
    -- mpim: multi-person IM, im: direct message
    
    is_archived     BOOLEAN DEFAULT FALSE,
    is_shared       BOOLEAN DEFAULT FALSE,  -- cross-workspace channel
    
    -- For DMs: which users
    dm_user_ids     JSON,
    
    -- Last message for sorting
    last_message_id BIGINT UNSIGNED,
    last_message_at DATETIME,
    
    created_by      BIGINT UNSIGNED,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_workspace_name (workspace_id, name)
) ENGINE=InnoDB;

-- ==================== CHANNEL MEMBERS ====================
CREATE TABLE channel_members (
    channel_id  BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    
    -- Notification settings per channel
    notification_level ENUM('default', 'all', 'mentions', 'nothing'),
    
    -- Mute
    is_muted    BOOLEAN DEFAULT FALSE,
    
    -- Last read
    last_read_id BIGINT UNSIGNED,
    
    joined_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (channel_id, user_id),
    INDEX idx_user (user_id, workspace_id)
) ENGINE=InnoDB;

-- ==================== MESSAGES ====================
CREATE TABLE messages (
    id          BIGINT UNSIGNED PRIMARY KEY,  -- flake ID (time-sortable)
    workspace_id BIGINT UNSIGNED NOT NULL,    -- shard key
    channel_id  BIGINT UNSIGNED NOT NULL,
    
    user_id     BIGINT UNSIGNED NOT NULL,
    
    -- Content
    text        MEDIUMTEXT,
    type        ENUM('message', 'bot_message', 'channel_join',
                     'channel_leave', 'file_share') DEFAULT 'message',
    
    -- Threading
    thread_ts   BIGINT UNSIGNED,   -- root message ID (NULL if not in thread)
    reply_count INT DEFAULT 0,
    reply_user_ids JSON,
    
    -- Attachments
    files       JSON,   -- [{id, name, url, ...}]
    attachments JSON,   -- link unfurls, bot attachments
    
    -- Reactions
    reactions   JSON,   -- [{name: "thumbsup", count: 3, users: [...]}]
    
    -- Edit
    edited_at   DATETIME,
    
    is_deleted  BOOLEAN DEFAULT FALSE,
    deleted_at  DATETIME,
    
    posted_at   DATETIME NOT NULL,
    
    INDEX idx_channel_time (channel_id, posted_at DESC),
    INDEX idx_thread       (thread_ts, posted_at ASC)
) ENGINE=InnoDB;

-- ==================== FILES ====================
CREATE TABLE files (
    id          BIGINT UNSIGNED PRIMARY KEY,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    channel_id  BIGINT UNSIGNED,
    
    name        VARCHAR(255) NOT NULL,
    title       VARCHAR(255),
    mimetype    VARCHAR(100),
    
    -- S3 storage
    s3_key      VARCHAR(500) NOT NULL,
    size_bytes  BIGINT,
    
    -- Thumbnails
    thumb_360_url TEXT,
    thumb_720_url TEXT,
    
    is_deleted  BOOLEAN DEFAULT FALSE,
    
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

---

## Flannel: Slack-ın Custom Cache

```
Flannel (2019):
  Slack-ın öz pub/sub + cache sistemi
  
Problem:
  Client WebSocket disconnect → reconnect
  "Catch-up": "Son 30 saniyədə nə oldu?"
  
  Redis pub/sub: good, amma Slack-ın xüsusi tələbləri:
  - Client reconnect → missed events replay
  - Fan-out per workspace
  - Large workspace fan-out (IBM: 50K concurrent users)
  
Flannel arxitekturası:
  Client connects → Flannel
  Flannel: recent events buffer (in-memory)
  
  New message → Kafka → Flannel → all connected clients in channel
  
  Client reconnect:
  "last_seen_event_id" göndərir
  Flannel: missed events-ları replay edir

Redis integration:
  Flannel + Redis pub/sub:
  PUBLISH channel:{channel_id} {message_json}
  
  Client connected to Slack server A:
  Server A subscribed to Redis
  Message → Redis → Server A → Client WebSocket
```

---

## Presence System

```
Online/Offline status per workspace:

Redis:
  SET presence:{workspace_id}:{user_id} {status} EX 60
  -- Heartbeat every 30 seconds from client
  -- Expires: auto offline after 60 seconds no heartbeat

Status types:
  active, away, dnd (do not disturb), offline

Workspace presence:
  SMEMBERS workspace:online:{workspace_id}   → all online users
  
Large workspace problem:
  IBM workspace: 50K concurrent users
  SMEMBERS → 50K user IDs → too large response
  
  Çözüm: Channel-level presence
  Only show presence for channel members visible on screen
  Lazy load: "Is user X online?" on demand
```

---

## Message Retention & Search

```
Free plan: 90 days message retention

Purge job:
  Daily batch job
  DELETE FROM messages
  WHERE workspace_id = ? AND posted_at < NOW() - INTERVAL 90 DAY
  AND workspace_plan = 'free';
  
  Vitess: scatter DELETE across all shards
  Partitioned tables: faster deletion

Search (Elasticsearch / Solr):
  Index: per workspace
  Indexed fields: text, user_id, channel_id, posted_at
  
  Query example:
  GET /search?query=deployment+pipeline&workspace=company&channel=devops
  
  Slack search features:
  from:username       → author filter
  in:#channel         → channel filter
  before/after:date   → date range
  has:file            → has attachment
  is:starred          → starred messages
```

---

## Scale Faktları

```
Numbers (2023):
  20M+ daily active users
  80M+ monthly active users
  Fortune 100: 65% use Slack
  
  Messages: billions per day
  Workspaces: millions
  
  Vitess: 
  Hundreds of MySQL shards
  Automatic resharding
  
  Files:
  Petabytes on S3
  
  Engineering:
  ~2,500 engineers (Salesforce acquisition 2021)
```

---

## Slack-dan Öyrəniləcəklər

```
1. Workspace = shard key:
   Tenant-based sharding
   Workspace data co-located
   Most queries: single shard

2. Vitess for MySQL:
   Transparent resharding
   Large workspace → move to new shard
   No application code change

3. Custom pub/sub (Flannel):
   Redis pub/sub not enough for their requirements
   Missed event replay
   Specialized > generic

4. Threading data model:
   thread_ts = root message ID
   Simple but effective
   Replies: WHERE thread_ts = :root_id

5. Message retention delete:
   Scheduled batch delete
   Table partitioning → fast bulk delete
   "Partition pruning" — drop old partition instantly
```
