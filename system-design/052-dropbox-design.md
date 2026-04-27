# Dropbox / Google Drive Design (Senior)

## İcmal

Dropbox / Google Drive istifadəçinin fayllarını cloud-da saxlayan, bütün cihazlar
arasında sync edən və başqaları ilə share etməyə imkan verən sistemdir. İstifadəçi
lokal qovluğa fayl atır - fayl avtomatik cloud-a upload olunur və bütün cihazlarda
görünür.

Sadə dillə: lokal qovluq + cloud backup + real-time sync + sharing. Fayl dəyişəndə
yalnız dəyişən hissə (delta) upload olunur, bandwidth və vaxta qənaət edilir.

```
Laptop (macOS)                  Cloud                    Phone (iOS)
  │                               │                          │
  ├─ ~/Dropbox/report.pdf         │                          │
  │        (edit)                 │                          │
  │──── upload delta ───────────▶ │                          │
  │                               │── push notification ────▶│
  │                               │                          │
  │                               │◀──── download delta ─────│
  │                               │                          │
  └─ synced                       │                          ├─ synced
```


## Niyə Vacibdir

File sync sistemi chunking, deduplication, delta sync kimi mürəkkəb problemləri həll edir. Content-addressable storage ilə yalnız dəyişən hissəni transfer etmək bandwidth-i kəskin azaldır. Dropbox, Google Drive, OneDrive — hamısı bu prinsiplər üzərindədir.

## Əsas Anlayışlar

### Requirements

**Functional:**
- Hər ölçüdə fayl upload/download (KB-dən GB-lərə qədər)
- Multiple device arasında sync (laptop, phone, tablet, web)
- Collaborators ilə share (public link, specific user, team folder)
- Version history (keçmiş versiyaya qayıtmaq)
- Offline access (offline işlə, online olanda sync)
- Cross-platform (Windows, macOS, Linux, iOS, Android, Web)

**Non-Functional:**
- Durability: 99.999999999% (11 nines) - fayl itməməlidir
- Availability: 99.99% uptime
- Low latency: kiçik fayllar (<1MB) üçün <1s sync
- Efficient large file sync: delta sync ilə yalnız dəyişikliklər
- Security: encryption at rest və in transit, E2E optional

### Capacity Estimation

```
Users: 500M total, 100M daily active
Files per user: 200 avg → 100B total files
Avg file size: 100KB (mix of docs, photos, videos)

Total storage:
  500M × 200 × 100KB = 10 PB (raw)
  × 3 replicas = 30 PB
  - 30% dedup savings = ~21 PB effective

Upload bandwidth (peak):
  10M concurrent uploads × 500KB/s avg = 5 TB/s peak
  Daily: 100M users × 50MB upload/day = 5 PB/day = ~60 GB/s avg

Metadata:
  100B files × 500 bytes metadata = 50 TB metadata DB
  Shard by user_id
```

### High-Level Architecture

```
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│   Client    │  │   Client    │  │   Client    │
│  (Laptop)   │  │  (Phone)    │  │   (Web)     │
└──────┬──────┘  └──────┬──────┘  └──────┬──────┘
       │                │                │
       └────────────────┼────────────────┘
                        │
                  ┌─────▼─────┐
                  │    LB     │
                  └─────┬─────┘
                        │
      ┌─────────────────┼─────────────────┐
      │                 │                 │
┌─────▼─────┐   ┌──────▼──────┐   ┌──────▼──────┐
│  Upload   │   │  Metadata   │   │Notification │
│  Service  │   │   Service   │   │   Service   │
└─────┬─────┘   └──────┬──────┘   └──────┬──────┘
      │                │                 │
┌─────▼─────┐   ┌──────▼──────┐   ┌──────▼──────┐
│   Block   │   │   SQL DB    │   │  WebSocket  │
│  Storage  │   │  (sharded)  │   │    / SSE    │
│   (S3)    │   └─────────────┘   └─────────────┘
└───────────┘
```

## Client Architecture

```
┌────────────────────────────────────────────┐
│              Dropbox Client                │
├────────────────────────────────────────────┤
│  Watcher  ──► fs events (inotify/fsevents)│
│  Chunker  ──► split into 4MB blocks       │
│  Hasher   ──► SHA-256 per chunk           │
│  Indexer  ──► local SQLite metadata       │
│  Sync Eng ──► upload/download deltas      │
└────────────────────────────────────────────┘
```

- **Watcher:** fayl sistemi dəyişikliklərini real-time tutur - Linux-da inotify,
  macOS-də FSEvents, Windows-da ReadDirectoryChangesW.
- **Chunker:** faylı 4MB block-lara bölür (kiçik = çox metadata, böyük = zəif delta).
- **Hasher:** hər block üçün SHA-256 (content-addressable - eyni content, eyni hash).
- **Indexer:** lokal SQLite DB, server-ə getmədən state-i bilir.
- **Sync Engine:** manifest diff edir, yalnız fərqli block-ları upload/download edir.

## Server Architecture

### Metadata Service

File tree, versions, ACL saxlayır. Sharded SQL DB (user_id ilə partition).

```sql
CREATE TABLE files (
    id BIGINT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    parent_id BIGINT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('file', 'folder'),
    current_version_id BIGINT,
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_user_parent (user_id, parent_id),
    UNIQUE KEY uk_user_parent_name (user_id, parent_id, name)
);

CREATE TABLE file_versions (
    id BIGINT PRIMARY KEY,
    file_id BIGINT NOT NULL,
    manifest_id VARCHAR(64) NOT NULL,
    size BIGINT NOT NULL,
    created_by BIGINT,
    created_at TIMESTAMP,
    INDEX idx_file_created (file_id, created_at DESC)
);

CREATE TABLE chunks (
    hash CHAR(64) PRIMARY KEY,           -- SHA-256
    size INT NOT NULL,
    storage_location VARCHAR(255),        -- S3 path
    ref_count INT DEFAULT 1,              -- for GC
    created_at TIMESTAMP
);

CREATE TABLE file_version_chunks (
    version_id BIGINT NOT NULL,
    chunk_hash CHAR(64) NOT NULL,
    offset BIGINT NOT NULL,
    PRIMARY KEY (version_id, offset),
    INDEX idx_chunk (chunk_hash)
);

CREATE TABLE acl (
    id BIGINT PRIMARY KEY,
    file_id BIGINT NOT NULL,
    principal_type ENUM('user', 'link', 'public'),
    principal_id BIGINT NULL,
    permission ENUM('read', 'write', 'owner'),
    expires_at TIMESTAMP NULL
);
```

### Block Storage

Content-addressed blob store (S3). Key = SHA-256 hash, value = block content. Eyni hash
varsa upload skip (dedup). İki user eyni PDF atsa, tək nüsxə saxlanılır.

```
S3 key format:  /blocks/ab/cd/abcd1234...ef56   (4MB block)
```

### Notification Service

Client-ə sync event push edir. Long polling (sadə, firewall-friendly) və ya WebSocket
(low latency, Laravel Reverb / Socket.IO).

## Delta Sync (rsync-style)

Yalnız dəyişmiş block-lar upload olunur. 1GB fayl-ın yalnız 1 sətri dəyişibsə,
yalnız həmin sətri əhatə edən 4MB block təkrar göndərilir.

```
Scenario: report.pdf 100MB = 25 chunks
  v1: [h1, h2, h3, ..., h25]    (manifest)
  User edits page 3 → chunk h3 dəyişir → h3'
  v2: [h1, h2, h3', h4, ..., h25]

Client client-server diff:
  Local manifest:  [h1, h2, h3', h4, ..., h25]
  Server has:      [h1, h2, h3, h4, ..., h25]
  Upload only:     h3'  (4MB, not 100MB)
  Server builds v2 manifest referencing h1, h2, h3', h4...h25
```

## Laravel Chunked Upload

```php
// routes/api.php
Route::post('/upload/init', [UploadController::class, 'init']);
Route::post('/upload/chunk', [UploadController::class, 'chunk']);
Route::post('/upload/complete', [UploadController::class, 'complete']);

class UploadController extends Controller
{
    public function __construct(private readonly UploadService $uploads) {}

    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file_name' => 'required|string|max:255',
            'total_size' => 'required|integer|min:1',
            'parent_id' => 'nullable|integer',
            'chunk_hashes' => 'required|array',      // SHA-256 per chunk
            'chunk_hashes.*' => 'string|size:64',
        ]);

        $session = $this->uploads->initSession($request->user()->id, $data);

        // Server tells client which chunks are missing (dedup savings)
        return response()->json([
            'session_id' => $session->id,
            'missing_chunks' => $session->missing_chunks,
        ]);
    }

    public function chunk(Request $request): JsonResponse
    {
        $content = $request->file('chunk')->get();
        $hash = $request->input('chunk_hash');

        if (hash('sha256', $content) !== $hash) {
            return response()->json(['error' => 'hash_mismatch'], 422);
        }

        $this->uploads->storeChunk($hash, $content);
        return response()->json(['ok' => true]);
    }

    public function complete(Request $request): JsonResponse
    {
        $session = UploadSession::findOrFail($request->input('session_id'));
        $version = $this->uploads->assembleVersion($session);

        return response()->json(['file_id' => $version->file_id, 'version_id' => $version->id]);
    }
}

class UploadService
{
    public function __construct(private readonly FilesystemAdapter $s3) {}

    public function initSession(int $userId, array $data): UploadSession
    {
        $existing = Chunk::whereIn('hash', $data['chunk_hashes'])->pluck('hash')->toArray();
        $missing = array_values(array_diff($data['chunk_hashes'], $existing));

        return UploadSession::create([
            'user_id' => $userId,
            'file_name' => $data['file_name'],
            'parent_id' => $data['parent_id'] ?? null,
            'chunk_hashes' => $data['chunk_hashes'],
            'total_size' => $data['total_size'],
            'missing_chunks' => $missing,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function storeChunk(string $hash, string $content): void
    {
        if (Chunk::where('hash', $hash)->exists()) {
            return;     // idempotent, dedup
        }

        $path = sprintf('blocks/%s/%s/%s', substr($hash, 0, 2), substr($hash, 2, 2), $hash);
        $this->s3->put($path, gzencode($content, 6));       // compression

        Chunk::create([
            'hash' => $hash,
            'size' => strlen($content),
            'storage_location' => $path,
            'ref_count' => 1,
        ]);
    }

    public function assembleVersion(UploadSession $session): FileVersion
    {
        return DB::transaction(function () use ($session) {
            $file = File::firstOrCreate([
                'user_id' => $session->user_id,
                'parent_id' => $session->parent_id,
                'name' => $session->file_name,
            ], ['type' => 'file']);

            $version = FileVersion::create([
                'file_id' => $file->id,
                'manifest_id' => hash('sha256', implode(',', $session->chunk_hashes)),
                'size' => $session->total_size,
                'created_by' => $session->user_id,
            ]);

            foreach ($session->chunk_hashes as $offset => $chunkHash) {
                FileVersionChunk::create([
                    'version_id' => $version->id,
                    'chunk_hash' => $chunkHash,
                    'offset' => $offset * 4 * 1024 * 1024,
                ]);
                Chunk::where('hash', $chunkHash)->increment('ref_count');
            }

            $file->update(['current_version_id' => $version->id]);
            $session->update(['status' => 'completed']);
            event(new FileUpdated($file, $version));

            return $version;
        });
    }
}
```

## Sync Notification (Laravel Reverb / SSE)

```php
class FileUpdated implements ShouldBroadcast
{
    public function __construct(
        public readonly File $file,
        public readonly FileVersion $version,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("user.{$this->file->user_id}.sync");
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->file->id,
            'path' => $this->file->path,
            'version_id' => $this->version->id,
            'manifest_id' => $this->version->manifest_id,
            'action' => 'updated',
        ];
    }
}
```

Client WebSocket-a subscribe olur, event gəldikdə manifest-i fetch edir, lokal ilə
diff edir, yalnız puçuk block-ları download edir.

## Conflict Resolution

İki cihaz offline olanda eyni faylı dəyişərsə - last-write-wins + "conflicted copy":

```
User edits report.pdf on Laptop (offline)  → v2-laptop
User edits report.pdf on Phone  (offline)  → v2-phone

Both go online → server detects divergence from common ancestor v1
Resolution:
  - Winner: later timestamp (say laptop) → becomes v2
  - Loser: saved as "report (conflicted copy 2026-04-18).pdf" → v1 manifest
User manually merges
```

## Version History

Immutable chunks + per-version manifest. Keçmiş versiya = köhnə manifest-i render etmək.
Retention: Free plan 30 gün, Pro plan 180 gün, Business unlimited.

GC: `chunks.ref_count = 0` olan block-lar async təmizlənir (soft delete + 7 gün grace).

## Security

```
In transit:  TLS 1.3 (bütün client-server trafik)
At rest:     AES-256 server-side encryption (S3 SSE)
E2E option:  client-side encryption before chunking
             - server yalnız ciphertext block-ları görür
             - dedup per-user olur (global dedup itirilir)
             - key client-side saxlanılır (user password-dan derive)
```

E2E ilə trade-off: privacy artır, amma global dedup itirilir - storage 3-4x artır.

## Sharing

```
Share link:
  POST /files/{id}/share
  → creates signed URL with ACL entry
  → https://dl.example.com/s/aB3x7K
  → signed URL: HMAC(secret, file_id|expires|permissions)

ACL enforcement:
  Download request → validate signature → check ACL.expires_at
                  → check permission (read/write)
                  → stream chunks from S3

Team folder:
  Parent folder-ə ACL qoyulur, child file-lar inherit edir.
```

## Praktik Tapşırıqlar

**S1: Niyə 4MB chunk ölçüsü seçilib?**
C: Trade-off: kiçik chunk → yaxşı dedup və delta sync, amma metadata partlayır (1TB =
1M chunk). Böyük chunk → az metadata, amma kiçik dəyişiklik üçün çox data təkrar upload.
4MB praktikada optimal - Dropbox da bunu istifadə edir.

**S2: Content-addressable storage nədir və nə fayda verir?**
C: Block adı onun məzmununun hash-idir (SHA-256). Üç fayda: 1) Dedup - eyni content =
eyni hash = tək nüsxə. 2) Integrity - download-da hash yoxlanılır, corruption detect
olunur. 3) Immutability - content dəyişsə hash dəyişir, version history təbii gəlir.

**S3: Delta sync necə işləyir?**
C: Client lokal manifest (chunk hash siyahısı) saxlayır. Server-dən remote manifest
alır, diff edir. Lokal-da olub remote-da olmayan hash-lər → upload. Əksi → download.
Eyni hash-lər skip. Network traffic yalnız dəyişən hissə ilə məhdudlaşır.

**S4: İki cihaz offline eyni faylı dəyişsə nə olur?**
C: Last-write-wins + "conflicted copy". Server common ancestor (v1) tapır, sonrakı
timestamp winner olur (v2), loser versiya "filename (conflicted copy DATE).ext" kimi
ayrı fayl saxlanılır. User manual merge edir. Real-time collab-da CRDT, sync-də LWW.

**S5: Large file upload (10GB) necə resumable edilir?**
C: Init-də chunk hash siyahısı göndərilir, server session yaradır və hansı chunk-ların
uploaded olduğunu saxlayır. Client hər chunk-ı ayrıca POST edir. Network kəsilsə session
state-i GET edir, qaldığı yerdən davam edir. Complete-də bütün chunk-lar var isə version
assembled olur.

**S6: Chunk-level dedup privacy ilə necə toqquşur?**
C: Server-side dedup = server hash-ləri müqayisə edir. E2E encryption-da hər user öz
key-i ilə encrypt edir → eyni plaintext fərqli ciphertext → dedup işləmir. Həll: no E2E
(Dropbox default), per-user E2E dedup, və ya convergent encryption (dedup qalır, amma
confirmation attack riski).

**S7: Metadata DB niyə sharded SQL, NoSQL yox?**
C: Strong consistency, transactions, foreign keys lazımdır (file tree integrity, ACL).
NoSQL eventual consistency rename-də race condition yarada bilər. user_id ilə shard -
bir user-in faylları eyni shard-da, cross-user query nadirdir.

**S8: Notification service niyə ayrı service-dir?**
C: WebSocket connection-lar uzunmüddətli və stateful-dir (hər cihaz = 1 connection).
Upload/metadata stateless horizontal scale olunur, notification ayrı optimized runtime
(Go, Elixir) + pub/sub backbone (Redis, Kafka) ilə fan-out edir.


## Əlaqəli Mövzular

- [File Storage](15-file-storage.md) — S3 object storage əsasları
- [Distributed File System](65-distributed-file-system.md) — petabyte miqyaslı saxlama
- [Database Replication](43-database-replication.md) — metadata replication
- [Idempotency](28-idempotency.md) — upload resume idempotency
- [Collaborative Editing](51-collaborative-editing-design.md) — shared document sync
