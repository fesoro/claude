# Collaborative Editing Design (Senior)

## İcmal

**Collaborative editing sistem** — eyni sənəd üzərində bir neçə istifadəçinin eyni anda redaktə edə biləcəyi, real-time olaraq bir-birinin dəyişikliklərini, kursorunu və seçimini görə biləcəyi platformadır. Google Docs, Microsoft 365, Figma, Notion, Quip belə işləyir.

**Əsas çətinlik:** iki nəfər eyni paragraph-ı eyni millisaniyədə dəyişsə, hansı versiya qalır? Heç kim itki hiss etməməlidir, amma nəticədə hamı eyni sənədi görməlidir.

```
User A (browser)                 User B (browser)
  │                                 │
  │  insert("X", pos=5)             │
  │────────────┐                    │
  │            ▼                    │
  │      ┌──────────┐   insert("Y", pos=5)
  │      │  Server  │◀───────────────│
  │      │  (OT/    │                │
  │      │   CRDT)  │                │
  │      └────┬─────┘                │
  │           │ transform/merge      │
  │◀──────────┤                      │
  │           └─────────────────────▶│
  │  final doc: "HelloXY world"      │
  │  final doc: "HelloXY world"      │
```


## Niyə Vacibdir

Google Docs kimi real-time collaboration eyni sənədi eyni anda redaktə etməyə imkan verir. OT (Operational Transformation) vs CRDT seçimi konsistentlik, latency, offline support arasındakı fundamental trade-off-u göstərir. Modern collaboration tool-ların hamısı bu arxitektura üzərindədir.

## Əsas Tələblər (Requirements)

### Funksional

1. **Real-time multi-user editing** — eyni document-i paralel redaktə
2. **Presence & cursors** — başqalarının harada olduğunu göstər
3. **Consistency** — bütün clients eyni nəticəyə gəlsin (strong eventual consistency)
4. **Offline support** — internet kəsiləndə işləməyə davam, reconnect zamanı sinxronizasiya
5. **Undo/redo** — hər user özünün dəyişikliklərini geri götürə bilsin (intention-preserving)
6. **Rich text** — bold, italic, heading, list, image, table, comment
7. **Permissions** — view / comment / edit rolları, link sharing, organizasiya ACL

### Non-funksional

- **Latency**: key press → peer ekranında <100ms (p95)
- **Availability**: 99.95%+
- **Scale**: yüz milyonlarla document, hər document-də onlarca aktiv user
- **Durability**: heç bir edit itməsin, crash-dən sonra tam bərpa

## Ölçü Hesabları (Capacity Estimation)

```
Total documents:      100M
Daily Active Users:   10M
Average active per doc: 3 user
Concurrent active docs: ~1M (peak saatda)
Ops per user:         2 keystroke/s
Peak total ops:       3M users × 2 ops = 6M ops/s

Doc size (avg):       50 KB (rich text JSON)
Delta log (24 saat):  10 KB/doc × 1M aktiv = 10 GB/gün
Snapshot storage:     100M × 50 KB = 5 TB (kompresiya ilə ~1.5 TB)
Operation payload:    50-200 bytes per op (OT) / 100-500 bytes (CRDT)

Presence updates:     cursor move 5/s × 3M = 15M events/s (yalnız ephemeral)
WebSocket connections: 10M concurrent peak
```

## Əsas Problem (Core Problem)

İki user eyni positional offset-ə paralel yazanda naïve `insert(pos, char)` işləmir:

```
Doc: "ABCDE"
User A: insert("X", pos=2) → "ABXCDE"
User B: insert("Y", pos=2) → "ABYCDE"

Server-ə hər ikisi eyni pos=2 ilə gəlir.
Naïve apply → "ABXYCDE" yoxsa "ABYXCDE"? pos=2 artıq köhnədir.
```

Həll üçün iki yol var: **Operational Transformation (OT)** və **CRDT**.

## Approach A — Operational Transformation (OT)

Central server hər gələn op-u, ondan əvvəl gəlmiş lakin client görməmiş op-lara qarşı **transform** edir.

```
Initial:  "ABCDE"
A: Op1 = insert("X", 2)  rev=0
B: Op2 = insert("Y", 2)  rev=0

Server Op1 apply → "ABXCDE" (rev=1)
Op2 rev=0-dan gəlir → Op1-ə qarşı transform:
  Op2' = insert("Y", 3)       (X artıq 2-də)
Server Op2' apply → "ABXYCDE" (rev=2)
B özündə Op1-i transform edir. Sonda hamı: "ABXYCDE"
```

**İstifadə:** Google Docs, Etherpad, ShareJS. **Üstünlük:** op payload kiçik, sənəd model sadə. **Çatışmazlıq:** central server məcburidir, correctness çətin (20+ edge case), rich tree üçün transform funksiyası mürəkkəb.

## Approach B — CRDT

Operation-lar **commutative** olur — sıradan asılı olmayaraq eyni nəticə. Ətraflı: [34-crdt.md](34-crdt.md).

```
Sequence CRDT (RGA / Yjs / Automerge):
  Hər char-a unique ID: (clientId, lamportClock)
  "insert X after ID-7, new ID=(A,5)"
Offset yox, ID göndərilir — konflikt yoxdur.
```

**İstifadə:** Figma, Apple Notes, Automerge, Yjs, Fluid Framework. **Üstünlük:** P2P merge, offline-first asan, riyazi sübutlu. **Çatışmazlıq:** böyük metadata (ID + tombstone), GC lazım.

## OT vs CRDT Müqayisə

| Aspekt              | OT                          | CRDT                        |
|---------------------|-----------------------------|-----------------------------|
| Mərkəzi server      | Lazımdır (transform)        | Lazım deyil (P2P da işləyir)|
| Op payload          | Kiçik (offset bəs)          | Böyük (ID + ctx)            |
| Metadata            | Az (rev, author)            | Çox (tombstones)            |
| Correctness         | Əl ilə, çətin              | Riyazi isbat                |
| Offline merge       | Çətin (rebase)              | Asan (native)               |
| Rich tree           | Transform × N-çətin         | Yjs XML / Automerge rich    |
| Real tətbiq         | Google Docs, Etherpad       | Figma, Notion, Linear       |

Seçim çox vaxt pragmatikdir: offline-first və P2P → CRDT; server-centric, max network efficiency → OT.

## Sistem Arxitekturası (Architecture)

```
    ┌────────────┐   WSS   ┌──────────────────┐
    │  Browser   │◀───────▶│   Edge / LB      │
    │ (Yjs/OT)   │          │ (NGINX + sticky) │
    └─────┬──────┘          └────────┬─────────┘
          │                          │
          │                          ▼
          │              ┌────────────────────────┐
          │              │  Collab Server (N)     │
          │              │  - in-memory doc       │
          │              │  - OT/CRDT engine      │
          │              │  - session per doc     │
          │              └──┬───────┬────────┬────┘
          │                 │       │        │
          │     consistent  │       │        │
          │     hash(doc_id)│       │        │
          │                 ▼       ▼        ▼
          │          ┌─────────┐ ┌──────┐ ┌──────────┐
          │          │ Redis   │ │Kafka │ │Object    │
          │          │Presence │ │Op log│ │Store S3  │
          │          │Pub/Sub  │ │      │ │Snapshots │
          │          └─────────┘ └──┬───┘ └──────────┘
          │                         │
          ▼                         ▼
    ┌─────────┐             ┌──────────────┐
    │  Auth   │             │ Snapshot Job │
    │ Service │             │ (every 5 min)│
    └─────────┘             └──────────────┘
```

**Komponentlərin rolu:**
- **WebSocket Gateway** — persistent connection, auth, sticky routing
- **Collab Server** — doc session saxlayır, OT transform / CRDT merge
- **Consistent hashing** — `doc_id → server`, eyni doc hər zaman eyni node
- **Kafka / DB op log** — append-only durability (crash → log replay)
- **Object Store (S3)** — hər 5 dəq / 1000 op-dan sonra full snapshot
- **Redis Presence** — cursor, selection, user list; TTL 30s, ephemeral
- **Auth Service** — JWT, ACL permission check
- **Snapshot Worker** — async periodic compaction

## Document Storage Model

Yalnız snapshot saxlamaq bahadır (hər edit yazsaq); yalnız log saxlamaq isə load zamanı çox yavaşdır.

**Hibrid yanaşma:**

```
snapshot_v3 (t=12:00:00) + ops [12:00:00..12:04:59]
                         ↓
                  load: snapshot + replay N op (bir neçə 100)

Snapshot cadence: 5 min OR 1000 op, hansı birinci
Old snapshots GC sonra 7 gün (point-in-time recovery üçün)
```

```sql
CREATE TABLE documents (
    id UUID PRIMARY KEY,
    owner_id BIGINT,
    latest_snapshot_key TEXT,        -- S3 path
    latest_rev BIGINT,
    updated_at TIMESTAMP
);

CREATE TABLE doc_ops (
    doc_id UUID,
    rev BIGINT,
    author_id BIGINT,
    op JSONB,                        -- serialized delta
    client_ts TIMESTAMP,
    server_ts TIMESTAMP,
    PRIMARY KEY (doc_id, rev)
) PARTITION BY HASH (doc_id);
```

## Rich Text Representation

Düz string deyil — nested tree (ProseMirror, Slate.js, Tiptap node model):

```
doc
 ├── paragraph
 │    ├── text("Hello ", marks=[bold])
 │    └── text("world")
 ├── heading(level=2)
 │    └── text("Section")
 └── bullet_list
      ├── list_item → paragraph → text("One")
      └── list_item → paragraph → text("Two")
```

OT/CRDT tree node üzərində işləyir — `insertNode`, `splitBlock`, `setMark` kimi op-lar. Yjs-də `Y.XmlFragment`, Automerge-də `A.Text`.

## Presence & Cursors

Cursor position, selection range, user color — tamamilə ephemeral. Itsə fərq etməz.

```
Client → WS → Collab Server → Redis PUBLISH "doc:{id}:presence"
                ↓
        Bütün peer-lər Redis SUBSCRIBE ilə alır

Store format (Redis Hash):
  doc:{id}:users → {userId: {name, color, cursor, sel, last_seen}}
  TTL 30s, hər heartbeat yenilənir
```

Bu data op log-a yazılmır (istehsal çoxdur), consistency tələbi də aşağıdır.

## Offline Support

Client browser IndexedDB-də Yjs doc saxlayır. Disconnect olsa:

```
1. Local op-lar doc-a apply olur, UI davam edir
2. "Outbox queue" op-ları saxlayır
3. Reconnect: server-dən (snapshot + missing ops) gəlir
4. CRDT merge automatic — conflict yoxdur
5. Outbox op-ları flush olur
```

OT-də bu çətindir — client rebase etməlidir; CRDT-də "sadəcə merge".

## Undo / Redo

**Intention-preserving undo** — user öz son edit-ini geri götürür, başqasının edit-ini yox.

```
A yazdı: "Hello"
B yazdı: " world"
Doc: "Hello world"

A undo basır → "world" (A-nın 'Hello'-su getdi, B-nin ' world'-u qaldı)
Yox isə "Hello" qaytarsaq B-nin işini silmiş olarıq
```

Hər user ayrı undo stack saxlayır, OT-də inverse op hesablanır və transform olunur. Yjs-də `Y.UndoManager` hazırdır (trackedOrigins ilə öz scope-un).

## Permissions

```
WebSocket connect:
  JWT yoxla → user_id
  documents.get(doc_id) → role (owner / editor / commenter / viewer)
  Role uyğun deyilsə → disconnect

Per-op:
  viewer → heç bir mutation qəbul etmə
  commenter → yalnız comment op-ları
  editor → hamısı

ACL cache: Redis, TTL 60s, invalidation events
```

## Scale & Sticky Routing

Bir doc üçün bütün connection-lar eyni server-ə gəlməlidir (yoxsa OT transform state parçalanır).

```
1. Client HTTP GET /doc/{id}/ws-url
2. Gateway consistent hash edər → collab-server-17.internal
3. Client WSS://collab-server-17/...
4. Server doc-u memory-də açar (əgər yoxdursa snapshot + log load)

Failover:
  Server crash → Zookeeper/etcd hash ring yenilənir
  Yeni server doc_id üçün snapshot load → op log replay → hazırdır
  Clients reconnect edir, qısa pause (1-3s)
```

**Hot doc problemi:** bir doc-da 500 user olsa server yüklənə bilər. Həll:
- Read-only replicas (presence + op broadcast)
- Rate limit per user
- "Too many editors" fallback to locked mode

## Laravel Nümunəsi (Laravel Reverb WebSocket)

Reverb — Laravel-in first-party WebSocket server-idir (Pusher protokolu). Alternativ: **Soketi**.

```php
// routes/channels.php — ACL at channel authorization
Broadcast::channel('doc.{docId}', function (User $user, string $docId) {
    $role = app(AclService::class)->roleFor($user, $docId);
    return in_array($role, ['owner', 'editor', 'commenter', 'viewer'])
        ? ['id' => $user->id, 'name' => $user->name, 'role' => $role]
        : false;
});

// app/Events/DocOpApplied.php
class DocOpApplied implements ShouldBroadcastNow
{
    public function __construct(
        public string $docId,
        public int $rev,
        public array $op,     // CRDT update or OT delta
        public int $authorId,
    ) {}

    public function broadcastOn(): array { return [new PrivateChannel("doc.{$this->docId}")]; }
    public function broadcastAs(): string { return 'op'; }
}

// app/Http/Controllers/DocOpController.php
class DocOpController
{
    public function store(Request $r, string $docId): JsonResponse
    {
        Gate::authorize('edit', [Document::class, $docId]);

        $payload = $r->validate([
            'base_rev' => 'required|integer',
            'op'       => 'required|array',
        ]);

        $result = app(CollabEngine::class)->applyOp(
            docId: $docId, baseRev: $payload['base_rev'],
            op: $payload['op'], author: $r->user()->id,
        );

        app(OpLog::class)->append($docId, $result['rev'], $result['op'], $r->user()->id);

        broadcast(new DocOpApplied(
            $docId, $result['rev'], $result['op'], $r->user()->id
        ))->toOthers();

        return response()->json(['rev' => $result['rev']]);
    }
}

// Presence — cursor / selection ephemeral
class PresenceService
{
    public function heartbeat(string $docId, int $userId, array $state): void
    {
        Redis::hset("doc:{$docId}:users", $userId, json_encode($state + ['ts' => time()]));
        Redis::expire("doc:{$docId}:users", 30);
        Redis::publish("doc:{$docId}:presence", json_encode(['user_id' => $userId] + $state));
    }
}
```

Client-side Yjs + Laravel Echo:

```js
import * as Y from 'yjs'
const ydoc = new Y.Doc()

window.Echo.private(`doc.${docId}`)
    .listen('.op', ({ op }) => Y.applyUpdate(ydoc, Uint8Array.from(op)))

ydoc.on('update', update => {
    fetch(`/api/doc/${docId}/op`, {
        method: 'POST',
        body: JSON.stringify({ base_rev: rev, op: Array.from(update) }),
    })
})
```

## CRDT Library Seçimi

- **Yjs** (TypeScript) — ən populyar, kiçik wire format, garbage collection, Monaco / ProseMirror / Tiptap binding
- **Automerge** (TypeScript/Rust) — JSON-like data, rich history, branch/merge
- **Fluid Framework** (Microsoft) — Office-in arxasında
- **Diamond Types** (Rust, Seph Gentle) — ən sürətli sequence CRDT (2024)

Kiçik nümunə (LWW-Register konsepti) üçün [34-crdt.md](34-crdt.md)-ə bax — orada G-Counter, PN-Counter, LWW-Register, OR-Set detallı izah olunur.

## Praktik Baxış

1. **Snapshot + log hibrid** — saf log load 10s+ vuracaq
2. **Sticky routing doc_id ilə** — OT correctness ondan asılıdır
3. **Op rate limit** (max 30 op/s) — abuse/runaway client qarşısı
4. **Compression** — WebSocket permessage-deflate (50-70% saving)
5. **Batching** — client 30-50ms pəncərədə op-ları qruplaşdır
6. **Presence ≠ persistence** — cursor DB-ə yazma
7. **GC tombstones** — CRDT-də köhnə tombstone yığılması
8. **Monitor p99** — keypress → peer < 150ms
9. **Versioned op schema** — format dəyişəndə migration asan
10. **Feature flag new engine** — OT → CRDT keçidi risklidir, canary et

## Praktik Tapşırıqlar

**1. OT və CRDT arasında seçim necə edərdin?**
Server-centric, bandwidth kritikdirsə (mobile, low-end network) → OT. Offline-first, P2P, local-first (Linear, Obsidian Sync kimi) → CRDT. Rich collaborative tree (nested list, table) implementasiyası CRDT-də daha asan; məsələn Yjs XML fragment təbii dəstəkləyir. Google Docs 2006-da başlayanda CRDT yetkin deyildi, bu gün yeni məhsul başlasa çoxu CRDT seçir (Notion, Linear, Figma).

**2. Eyni doc-da 500 user yazsa sistem necə davranar?**
Praktikada 50+ aktiv editor olduqda UX onsuz da pisləşir. Texniki həll: read-only replicas (presence broadcast çıxarış), op rate limit per connection, "session mode" (hamı view, yalnız host edit), Kafka partition per doc. Google Docs-da sərt limit ~100 concurrent editor.

**3. Server crash olsa real-time session necə bərpa olunur?**
1) Health check fail → LB yeni connection-ları başqa node-a göndərir. 2) Consistent hash ring yenilənir (Zookeeper notification). 3) Yeni collab server doc-u open edəndə: latest snapshot (S3) yüklə + Kafka op log-dan rev > snapshot_rev-i replay et. 4) Clients WebSocket disconnect hiss edir, exponential backoff reconnect edir. Tipik recovery 2-5 saniyə.

**4. Offline edit sonra reconnect nə olur?**
CRDT-də client local doc-a op-ları apply etmişdir, reconnect zamanı Yjs `sync protocol` iki tərəf state vector dəyişər, yalnız missing update-lər transfer olunur. Konflikt yoxdur çünki CRDT merge commutative-dir. OT-də isə client server rev-indən köhnədirsə, local op-ları server-ə göndərir, server transform edir və geri nəticəni yollayır — rebase əməliyyatı.

**5. Presence data-nı niyə Kafka/DB-yə yazmırsan?**
Cursor move saniyədə 5-10 event × milyonlarla user = onlarla milyon event/s. Durability lazım deyil — disconnect olsa cursor itməlidir onsuz da. Redis pub/sub və TTL-li hash kifayətdir. Yalnız final saved cursor (user logout olanda) persist oluna bilər.

**6. Undo necə işləyir, hər user öz stack-ını necə saxlayır?**
Hər edit-in author_id var. Undo stack local — client-də saxlanılır, inverse op hesablanır (insert → delete, delete → insert). Inverse op server-ə göndərilir, orada digər concurrent op-lara qarşı transform olunur (yoxsa başqasının edit-ini silə bilər). Yjs-də `Y.UndoManager(origin: localClientId)` — yalnız öz origin-ini izləyir, başqalarının op-ları undo stack-a düşmür.

**7. Document 1 GB olsa necə handle edərsən?**
Praktikada Google Docs 1M char limit qoyur (~10 MB). Böyük üçün: 1) Lazy loading — yalnız viewport-dəki section-ları CRDT-yə yüklə. 2) Sub-document sharding — hər böyük section öz CRDT doc-u, ana doc sadəcə referans. 3) Snapshot compression (zstd ~70% kiçildir). 4) Garbage collect köhnə tombstone-ları (Yjs snapshot ilə "tombstone squash"). Figma bunu layer tree sharding ilə edir.

**8. Security — malicious client server state-i poza bilərmi?**
Hə, bir sıra sahədə:
- **ACL per-op yoxla**, sadəcə connect zamanı yox (role dəyişə bilər)
- **Op schema validation** — format düz, sərhəd içində
- **Op rate limit** — DoS qarşısı
- **Payload size cap** (max 64 KB/op)
- **Origin check** — CSRF qarşısı WebSocket-də
- **Audit log** — hər op kim, haçan, nə etdi (compliance)
- OT-də transform correctness — malicious rev göndərib transform exception-a gətirə bilməsin


## Əlaqəli Mövzular

- [CRDT](34-crdt.md) — conflict-free məlumat strukturları
- [Real-Time Systems](17-real-time-systems.md) — WebSocket sync mexanizmi
- [Distributed Locks](83-distributed-locks-deep-dive.md) — presence lock
- [Consistency](32-consistency-patterns.md) — collaborative editing consistency
- [Chat System](19-chat-system-design.md) — real-time kommunikasiya arxitekturası
