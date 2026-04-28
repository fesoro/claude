# WhatsApp — DB Design & Technology Stack (Lead ⭐⭐⭐⭐)

## Actual DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                    WhatsApp Database Stack                       │
├──────────────────────┬──────────────────────────────────────────┤
│ Erlang + Mnesia      │ User sessions, routing, online status   │
│ MySQL                │ User accounts, contacts, group metadata │
│ Custom (FreeBSD+KV)  │ Message storage (WhatsApp's own)        │
│ RocksDB (on-device)  │ Local message store (client-side)       │
│ Amazon S3            │ Media files (photos, videos, documents) │
│ Redis                │ Presence, delivery state                │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Niyə Erlang/Mnesia?

```
WhatsApp-ın arxitektura seçimi qeyri-adi:
  Erlang/OTP → actor model → concurrency
  Mnesia → Erlang-ın built-in distributed database

2014 (Facebook acquisition):
  50 engineers, 450M users
  Hər server: 2M+ eş-zamanlı bağlantı
  
Erlang-ın üstünlükləri:
  ✓ Actor model (process-based concurrency)
  ✓ Lightweight processes (millions on one server)
  ✓ Fault tolerance ("let it crash" philosophy)
  ✓ Hot code loading (zero downtime update)
  ✓ Built-in distribution (nodes arası kommunikasiya)

Mnesia:
  ✓ RAM-based (ultra-fast)
  ✓ Distributed (multiple nodes)
  ✓ ACID transactions
  ✓ Erlang nativedir
  
İstifadə sahəsi:
  User session state (online/offline)
  Message routing tables
  Server → server routing
  
Mnesia məhdudiyyəti:
  ✗ Böyük data saxlamır (RAM-based)
  ✗ Complex queries yoxdur
  → Buna görə MySQL əlavə edildi
```

---

## XMPP → Custom Protocol

```
WhatsApp 2009-2014: XMPP əsaslı
  Jabber/XMPP: instant messaging protokolu
  XML-based messages
  
Problem:
  XMPP overhead çox (XML verbose)
  WhatsApp: custom optimizations əlavə etdi
  
Yeni protokol (Noise Protocol):
  Binary format (Protobuf-based)
  E2E encryption (Signal Protocol, 2016)
  WebSocket transport
  
WhatsApp Signal Protocol:
  Double Ratchet Algorithm
  X3DH (Extended Triple Diffie-Hellman)
  Keys cihazda saxlanılır, serverda yoxdur
  WhatsApp belə oxuya bilmir
```

---

## MySQL: User Data

```sql
-- WhatsApp-ın sadə user model
-- (Inferred from public information)

CREATE TABLE accounts (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    phone_number  VARCHAR(20) UNIQUE NOT NULL,
    -- E.164 format: +994501234567
    country_code  VARCHAR(5) NOT NULL,
    status        ENUM('active', 'banned', 'deleted') DEFAULT 'active',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen     DATETIME,
    
    -- Profile
    name          VARCHAR(100),
    about         VARCHAR(139),  -- WhatsApp "About" (139 char limit)
    profile_pic_id BIGINT,
    
    -- Privacy settings
    privacy_last_seen  ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
    privacy_profile_pic ENUM('everyone', 'contacts', 'nobody') DEFAULT 'everyone',
    privacy_about      ENUM('everyone', 'contacts', 'nobody') DEFAULT 'contacts'
);

-- Contacts (Server-side contact matching)
-- WhatsApp contacts telefon nömrəsinə görə resolve edilir
CREATE TABLE contact_hashes (
    account_id   BIGINT UNSIGNED NOT NULL,
    phone_hash   VARCHAR(64) NOT NULL,  -- SHA-256(phone)
    -- Privacy: tam nömrə saxlanmır, yalnız hash
    PRIMARY KEY (account_id, phone_hash),
    INDEX idx_hash (phone_hash)
);

-- Group chats metadata
CREATE TABLE groups (
    id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    jid           VARCHAR(100) UNIQUE NOT NULL,  -- group@g.us
    subject       VARCHAR(25) NOT NULL,           -- Qrup adı (25 char)
    creator_id    BIGINT UNSIGNED NOT NULL,
    description   VARCHAR(512),
    invite_link   VARCHAR(100) UNIQUE,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    member_count  SMALLINT DEFAULT 0
);

CREATE TABLE group_members (
    group_id   BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    role       ENUM('admin', 'member') DEFAULT 'member',
    joined_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, account_id),
    INDEX idx_account (account_id)
);
```

---

## Message Storage (WhatsApp Custom)

```
WhatsApp mesaj saxlama arxitekturası:

Əsas prinsip:
  E2E encrypted → WhatsApp SERVER mesajları oxuya bilmir
  Server yalnız envelope (zarf) saxlayır
  
Delivery semantics:
  Store-and-forward model
  Receiver offline → server saxlayır
  Receiver online → tez çatdırır → serverdən silir
  
  WhatsApp: "Messages deleted from server after delivered"

Server-side store:
  Müvəqqəti (delivery pending): encrypted blob
  Delivered: silindi
  
Client-side store:
  RocksDB (on-device): bütün mesaj tarixi
  Platform: Android (SQLite → RocksDB), iOS (CoreData)

Qrup mesajları:
  1 mesaj → N delivery (hər üzv üçün ayrı)
  Server: per-member delivery tracking
  
Multi-device (2021+):
  Bir hesab → 4 cihaz
  Signal Protocol extension: multi-device keys
  Primary device → secondary devices-ə key forward
```

---

## Scale Faktları

```
Numbers:
  2 billion+ monthly active users
  100 billion+ messages per day
  7 billion+ voice/video calls per day
  1.5 TB data shared per minute

  2014 (acquisition):
  450M users, 50 engineers
  $19 billion — tarixdəki ən böyük tech acquisition

Infrastructure:
  FreeBSD (operating system) - Linux əvəzinə
  Erlang (application server)
  Multiple data centers

Server efficiency:
  2014: 1 server → 2M concurrent connections
  Erlang lightweight processes: 10M+ processes per server
  "WhatsApp: one engineer per 14M users"

Engineering blog:
  "Million Users Pushes" post (2011)
  Erlang-ın necə yüksək concurrency-i idarə etdiyini izah edir
```

---

## Mnesia Schema (Erlang)

```erlang
%% Mnesia tables (inferred from WhatsApp architecture papers)

%% Session tracking
-record(session, {
    jid,           %% user@s.whatsapp.net
    server_node,   %% bu sessiya hansı server-dədir
    resource,      %% cihaz ID
    priority,
    last_seen
}).

%% Message queue (offline delivery)
-record(offline_msg, {
    to_jid,
    from_jid,
    packet,        %% encrypted message envelope
    timestamp
}).

%% Routing table (server-to-server)
-record(route, {
    server_name,
    node,
    pid
}).

%% Mnesia table creation:
mnesia:create_table(session, [
    {attributes, record_info(fields, session)},
    {type, set},
    {ram_copies, [node()]}  %% RAM-only for speed
]).
```

---

## Delivery Status Tracking

```
WhatsApp mesaj statusları:
  Sent:      Server aldı (✓)
  Delivered: Alıcının cihazına çatdı (✓✓)
  Read:      Alıcı açdı (✓✓ mavi)

Server-side tracking (Redis/Mnesia):
  HSET msg_status:{msg_id} {recipient_id} "delivered"
  HGETALL msg_status:{msg_id}

Group read receipts:
  Hər üzv üçün ayrı delivery/read tracking
  Bütün üzv oxuyanda sender-ə bildiriş

Privacy setting:
  "Read receipts" söndürülsə:
  Server hələ track edir (delivery)
  Amma sender-ə read status göndərilmir
```

---

## WhatsApp-dan Öyrəniləcəklər

```
1. Erlang/OTP seçimi:
   2009-da qeyri-standart seçim
   2024-də hələ də Erlang
   "If it ain't broke, don't fix it"
   
   Erlang: telecom üçün yaradılmış
   "9 nines" availability
   WhatsApp messaging = telecom use case

2. E2E Encryption first:
   2016-da bütün mesajlar encrypted
   Server mesajları oxuya bilmir
   Bu arxitektura dizayn qərarıdır

3. Minimal footprint:
   50 engineers, 450M users
   Mümkün olan ən az mürəkkəblik
   KISS principle

4. Store-and-forward:
   Server yalnız delivery broker-dir
   Long-term storage clientdə (RocksDB)

5. Phone number as identity:
   Username yoxdur, email yoxdur
   Telefon nömrəsi = account
   Contact discovery = server-side hash matching
```
