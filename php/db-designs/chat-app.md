# Chat App — DB Design

## Tövsiyə olunan DB Stack
```
Primary:     Apache Cassandra  (mesajlar — write-heavy, time-series)
Sessions:    Redis             (online status, typing indicators)
Accounts:    PostgreSQL        (user accounts, contacts)
Media:       S3 + CDN          (şəkillər, videolar)
Search:      Elasticsearch     (mesaj axtarışı)
```

---

## Niyə Cassandra?

```
Problem:
  WhatsApp: gündə 100 milyard mesaj
  Her mesaj yazılır, çox az yenilənir
  Query pattern: "User A ilə User B arasındakı son 50 mesaj"
  
Cassandra-nın üstünlükləri:
  ✓ Write-optimized: LSM tree → writes çox sürətli
  ✓ Time-series friendly: CLUSTERING ORDER BY time DESC
  ✓ Horizontal scale: node əlavə etmək asandır
  ✓ Tunable consistency: AP (availability > consistency)
  ✓ TTL: köhnə mesajları avtomatik sil
  
Relational DB-nin problemi:
  ✗ Hər mesaj INSERT → milyard row → yavaşlar
  ✗ JOIN-lər bahalı
  ✗ Vertical scale limiti var
```

---

## Schema Design

```sql
-- PostgreSQL: User hesabları
CREATE TABLE users (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    phone        VARCHAR(20) UNIQUE NOT NULL,
    username     VARCHAR(50) UNIQUE,
    display_name VARCHAR(100),
    avatar_url   TEXT,
    last_seen    TIMESTAMPTZ,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE contacts (
    user_id    UUID REFERENCES users(id),
    contact_id UUID REFERENCES users(id),
    nickname   VARCHAR(100),
    added_at   TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (user_id, contact_id)
);

-- Conversation (1-1 və ya group)
CREATE TABLE conversations (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type         VARCHAR(10) NOT NULL, -- 'direct', 'group'
    name         VARCHAR(100),        -- group adı
    avatar_url   TEXT,
    created_by   UUID REFERENCES users(id),
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE conversation_members (
    conversation_id UUID REFERENCES conversations(id),
    user_id         UUID REFERENCES users(id),
    role            VARCHAR(10) DEFAULT 'member', -- 'admin', 'member'
    joined_at       TIMESTAMPTZ DEFAULT NOW(),
    left_at         TIMESTAMPTZ,
    PRIMARY KEY (conversation_id, user_id)
);
```

```cql
-- Cassandra: Mesajlar (write-heavy, time-series)

-- Query pattern: "conversation_id üzrə son N mesaj"
-- Cassandra-da query-first modeling: əvvəl query, sonra schema

CREATE TABLE messages (
    conversation_id UUID,
    message_id      TIMEUUID,          -- CQL UUID v1: timestamp-embedded
    sender_id       UUID,
    message_type    TEXT,              -- 'text', 'image', 'video', 'audio'
    content         TEXT,
    media_url       TEXT,
    reply_to_id     TIMEUUID,
    status          TEXT,              -- 'sent', 'delivered', 'read'
    deleted_at      TIMESTAMP,         -- soft delete
    PRIMARY KEY (conversation_id, message_id)
) WITH CLUSTERING ORDER BY (message_id DESC)  -- ən yeni əvvəl
  AND default_time_to_live = 31536000          -- 1 il TTL
  AND gc_grace_seconds = 864000;

-- Message delivery status (per-recipient)
CREATE TABLE message_receipts (
    conversation_id UUID,
    message_id      TIMEUUID,
    user_id         UUID,
    status          TEXT,     -- 'delivered', 'read'
    updated_at      TIMESTAMP,
    PRIMARY KEY ((conversation_id, message_id), user_id)
);

-- User-ın bütün söhbətləri (son mesaj ilə)
CREATE TABLE user_conversations (
    user_id         UUID,
    last_message_at TIMESTAMP,
    conversation_id UUID,
    last_message    TEXT,
    unread_count    INT,
    PRIMARY KEY (user_id, last_message_at, conversation_id)
) WITH CLUSTERING ORDER BY (last_message_at DESC);
```

```
-- Redis: Online status, typing

-- Online status (TTL ilə)
SET online:{user_id} 1 EX 300          -- 5 dəqiqə

-- Typing indicator
SET typing:{conv_id}:{user_id} 1 EX 5  -- 5 saniyə

-- Unread count (fast counter)
HINCRBY unread:{user_id} {conv_id} 1
HGET    unread:{user_id} {conv_id}

-- User-ın active conversation-ları
ZADD user:convs:{user_id} {timestamp} {conv_id}
ZREVRANGE user:convs:{user_id} 0 19    -- son 20 söhbət
```

---

## Query Pattern Nümunələri

```sql
-- 1. Conversation tarix (Cassandra)
SELECT * FROM messages
WHERE conversation_id = ?
AND message_id < ? -- cursor-based pagination
LIMIT 50;

-- 2. Unread count (Redis)
HGETALL unread:{user_id}

-- 3. Online istifadəçilər (Redis)
MGET online:{user1} online:{user2} online:{user3}

-- 4. User axtarışı (PostgreSQL)
SELECT id, display_name, avatar_url
FROM users
WHERE phone = ? OR username ILIKE ?
LIMIT 10;

-- 5. Qrup üzvləri (PostgreSQL)
SELECT u.id, u.display_name, u.avatar_url, cm.role
FROM conversation_members cm
JOIN users u ON u.id = cm.user_id
WHERE cm.conversation_id = ?
  AND cm.left_at IS NULL;
```

---

## Dizayn Qərarları və Niyə

```
1. TIMEUUID mesaj ID kimi:
   ✓ Timestamp-embedded (sıralama pulsuz)
   ✓ Unique (collision yoxdur)
   ✓ Cassandra CLUSTERING ORDER ilə uyğun
   ✗ UUIDv4 istifadə etsəydik: ayrıca created_at lazım olardı

2. Cassandra Partition Key = conversation_id:
   ✓ Bir söhbətin bütün mesajları eyni node-da
   ✓ Range query (message_id < ?) sürətli
   ✗ Çox böyük söhbət → hot partition
   Həll: conversation_id + bucket (aylıq bölgü)
   PRIMARY KEY ((conversation_id, bucket), message_id)

3. user_conversations ayrı cədvəl (denormalization):
   Cassandra JOIN bilmir
   "İstifadəçinin söhbətlərini göstər" → ayrı query table
   Yazanda hər iki cədvəl yenilənir (eventual consistency)

4. PostgreSQL user accounts üçün:
   Contacts = many-to-many → JOIN lazım
   User search = ILIKE, phone lookup
   ACID lazım (account creation)
```

---

## Best Practices

```
✓ Message ID üçün TIMEUUID (CQL) istifadə et
✓ Cassandra-da TTL ilə köhnə mesajları avtomatik sil
✓ Read receipts ayrı cədvəldə (mesajı yeniləmə → Cassandra-da bahalı)
✓ Media S3-də saxla, DB-də yalnız URL
✓ Typing indicator Redis-də qısa TTL ilə
✓ Fan-out (group message) async/queue vasitəsilə
✓ Unread count Redis-də saxla (DB-ə count() sorğusu bahalı)
✓ Message search üçün Elasticsearch-ə async index et

Anti-patterns:
✗ Cassandra-da UPDATE status (yazma bahalı) → ayrı cədvəl
✗ PostgreSQL-də mesajları saxlamaq (scale olmaz)
✗ Realtime status DB-dən sorğulamaq
```

---

## Tanınmış Sistemlər

```
WhatsApp:
  Erlang + Mnesia   → session, routing (in-memory, distributed)
  MySQL             → user accounts
  Custom KV Store   → mesaj saxlama
  
  Niyə Erlang/Mnesia?
  Erlang actor model → milyon concurrent connection
  Mnesia → distributed in-memory DB

Telegram:
  Custom DB (MTProto) → mesajlar
  PostgreSQL          → user data
  
Facebook Messenger:
  MySQL (HBase) → mesajlar
  Memcached     → cache
  Haystack      → media saxlama
```

---

## Potensial Bottleneck-lər və Həllər

```
Bottleneck 1: Group chat fan-out (1000 üzv)
  Problem: 1 mesaj → 1000 delivery
  Həll: Kafka + async workers

Bottleneck 2: Online status çox sorğu
  Problem: Hər saniyə binlərlə status sorğusu
  Həll: Redis + WebSocket push (poll əvəzinə)

Bottleneck 3: Media yükləmə
  Problem: Fayllar DB-dən keçsə bandwidth tükənər
  Həll: Presigned S3 URL (birbaşa S3-ə upload)

Bottleneck 4: Message search
  Problem: Cassandra full-text search bilmir
  Həll: Elasticsearch-ə async index (CDC ilə)
```

---

## WebRTC Signaling DB Design

```
Video/Audio call (WebRTC) üçün signaling lazımdır:
  Peers arasında SDP (Session Description Protocol) mübadilə

Signaling server:
  Peer A → SDP offer → Signaling server → Peer B
  Peer B → SDP answer → Signaling server → Peer A
  ICE candidates exchange (NAT traversal)
  
Redis (temporary, TTL-based):
  SET call:offer:{call_id} {sdp_offer_json} EX 60
  SET call:answer:{call_id} {sdp_answer_json} EX 60
  SADD call:candidates:{call_id}:{peer_id} {ice_candidate}
  EXPIRE call:candidates:{call_id}:{peer_id} 120

PostgreSQL (persistent call log):
CREATE TABLE calls (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    room_id     BIGINT NOT NULL,
    initiator_id BIGINT NOT NULL,
    type        ENUM('audio', 'video', 'screen_share'),
    status      ENUM('ringing', 'active', 'ended', 'missed', 'declined'),
    started_at  TIMESTAMPTZ,
    ended_at    TIMESTAMPTZ,
    duration_sec INT,
    participants JSONB  -- [user_id, joined_at, left_at]
);

TURN server credentials (temporary):
  TURN = relay server (NAT traversal)
  SET turn:cred:{user_id} {username}:{password} EX 86400
  HMAC-based time-limited credentials
```

---

## Group Size Limits & Design Decisions

```
Qrup ölçüsünə görə fərqli davranış:

Kiçik qrup (≤ 100 üzv):
  Fan-out: hər üzv üçün ayrı delivery
  Cassandra: per-member message row
  WebSocket: direct delivery

Orta qrup (100 - 1000 üzv):
  Fan-out: Kafka worker pool
  Batch delivery (50 messages at once)
  Read receipt: per member tracking

Böyük qrup (1000+ üzv): "Broadcast channel"
  Fan-out çox bahalı → pull model
  Members poll for new messages
  No individual read receipts
  WhatsApp: max 1024, Telegram: 200K (broadcast channels unlimited)

DB fərqi:
  Small group: delivery_status per member tracked
  Large group: only sender-side delivery_count
  
  -- Small group delivery tracking
  CREATE TABLE message_delivery (
      message_id  UUID,
      recipient_id BIGINT,
      delivered_at TIMESTAMPTZ,
      read_at      TIMESTAMPTZ,
      PRIMARY KEY (message_id, recipient_id)
  );
  -- 1000 members × 1000 messages/day = 1M rows/day per group
  -- At 10K groups = 10B rows/day → unsustainable!
```
