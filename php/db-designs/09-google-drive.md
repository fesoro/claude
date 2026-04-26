# Google Drive — DB Design

## Tövsiyə olunan DB Stack

```
┌─────────────────────────────────────────────────────────────────┐
│                   Google Drive DB Stack                          │
├──────────────────────┬──────────────────────────────────────────┤
│ PostgreSQL / MySQL   │ File metadata, folder hierarchy, ACL     │
│ Redis                │ Upload sessions, locks, cache            │
│ Object Storage (S3)  │ Actual file content (chunks)             │
│ Elasticsearch        │ Full-text file search                    │
│ Kafka                │ File events (upload, share, delete)      │
└──────────────────────┴──────────────────────────────────────────┘
```

---

## Niyə Bu Stack?

```
Əsas problemlər:
  1. File hierarchy (folder içində folder)
  2. Permissions/ACL (kim nəyi görə bilər)
  3. Large file upload (chunked, resumable)
  4. Deduplication (eyni faylı 2 user yükləsə, 1 kopy)
  5. Versioning (köhnə versiyaları saxla)
  6. Sync across devices

PostgreSQL:
  ✓ Recursive CTE → folder hierarchy
  ✓ JSONB → flexible ACL
  ✓ ACID → permission dəyişiklikləri atomik

Object Storage:
  ✓ Cheap: $/GB çox aşağı
  ✓ Scalable: petabytes
  ✓ Chunk-based upload mümkün

Redis:
  ✓ Upload session state (resumable upload)
  ✓ Distributed lock (concurrent edit)
```

---

## Schema Design

```sql
-- ==================== FILES & FOLDERS ====================
-- Unified table: file və folder eyni struktur
-- type ilə fərqləndirilir

CREATE TABLE nodes (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    owner_id      BIGINT NOT NULL,          -- sahibi
    parent_id     UUID REFERENCES nodes(id), -- NULL = root
    
    name          VARCHAR(255) NOT NULL,
    type          ENUM('file', 'folder') NOT NULL,
    
    -- File-specific
    size_bytes    BIGINT,
    mime_type     VARCHAR(100),
    
    -- Content addressing (deduplication!)
    content_hash  VARCHAR(64),   -- SHA-256 of file content
    storage_key   VARCHAR(500),  -- S3 object key
    -- Dedup: eyni hash → eyni storage_key → storage saxlanmır
    
    -- Versioning
    version       INT DEFAULT 1,
    
    -- Status
    is_trashed    BOOLEAN DEFAULT FALSE,
    trashed_at    TIMESTAMPTZ,
    
    -- Metadata
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    updated_at    TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE INDEX idx_parent_name (parent_id, name),  -- eyni folder-də eyni ad olmaz
    INDEX idx_owner      (owner_id),
    INDEX idx_hash       (content_hash),
    INDEX idx_parent     (parent_id)
);

-- ==================== VERSIONS ====================
CREATE TABLE file_versions (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    node_id       UUID NOT NULL REFERENCES nodes(id),
    version       INT NOT NULL,
    size_bytes    BIGINT NOT NULL,
    content_hash  VARCHAR(64) NOT NULL,
    storage_key   VARCHAR(500) NOT NULL,
    created_by    BIGINT NOT NULL,
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    
    UNIQUE (node_id, version)
);

-- ==================== ACL / PERMISSIONS ====================
CREATE TABLE permissions (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    node_id       UUID NOT NULL REFERENCES nodes(id),
    
    -- Grantee: user, group, ya da "anyone"
    grantee_type  ENUM('user', 'group', 'domain', 'anyone') NOT NULL,
    grantee_id    VARCHAR(100),   -- user_id, group_id, domain
    
    role          ENUM('viewer', 'commenter', 'editor', 'owner') NOT NULL,
    
    -- Link sharing
    share_link    VARCHAR(100) UNIQUE,  -- token for link-based access
    
    -- Expiry
    expires_at    TIMESTAMPTZ,
    
    -- Inheritance: folder permission → children
    inherit       BOOLEAN DEFAULT TRUE,
    
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    
    INDEX idx_node    (node_id),
    INDEX idx_grantee (grantee_type, grantee_id)
);

-- ==================== UPLOAD SESSIONS ====================
CREATE TABLE upload_sessions (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         BIGINT NOT NULL,
    parent_id       UUID REFERENCES nodes(id),
    filename        VARCHAR(255) NOT NULL,
    total_size      BIGINT NOT NULL,
    chunk_size      INT NOT NULL DEFAULT 5242880,  -- 5MB default
    total_chunks    INT NOT NULL,
    
    -- Progress
    uploaded_chunks INT[] DEFAULT '{}',  -- completed chunk indices
    
    status          ENUM('in_progress', 'completed', 'expired') DEFAULT 'in_progress',
    expires_at      TIMESTAMPTZ DEFAULT NOW() + INTERVAL '24 hours',
    created_at      TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Folder Hierarchy: Recursive CTE

```sql
-- Bütün alt qovluqlar + fayllar (recursive)
WITH RECURSIVE folder_tree AS (
    -- Base case: başlanğıc node
    SELECT id, parent_id, name, type, 0 AS depth
    FROM nodes
    WHERE id = :folder_id AND owner_id = :user_id
    
    UNION ALL
    
    -- Recursive: hər uşaq
    SELECT n.id, n.parent_id, n.name, n.type, ft.depth + 1
    FROM nodes n
    INNER JOIN folder_tree ft ON n.parent_id = ft.id
    WHERE n.is_trashed = FALSE
)
SELECT * FROM folder_tree ORDER BY depth, name;

-- Breadcrumb (parent path)
WITH RECURSIVE breadcrumb AS (
    SELECT id, parent_id, name, 0 AS level
    FROM nodes WHERE id = :current_id
    
    UNION ALL
    
    SELECT n.id, n.parent_id, n.name, b.level + 1
    FROM nodes n
    INNER JOIN breadcrumb b ON n.id = b.parent_id
)
SELECT * FROM breadcrumb ORDER BY level DESC;
```

---

## Permission Check (Inherited)

```sql
-- "Bu user bu faylı görə bilərmi?"
-- Fayl, atasının permission-larını miras alır

WITH RECURSIVE node_path AS (
    -- Fayldan kökə qədər bütün parent-lar
    SELECT id, parent_id FROM nodes WHERE id = :file_id
    UNION ALL
    SELECT n.id, n.parent_id FROM nodes n
    JOIN node_path np ON n.id = np.parent_id
)
SELECT p.role
FROM permissions p
WHERE p.node_id IN (SELECT id FROM node_path)
  AND (
      (p.grantee_type = 'user' AND p.grantee_id = :user_id::TEXT)
      OR p.grantee_type = 'anyone'
  )
  AND (p.expires_at IS NULL OR p.expires_at > NOW())
ORDER BY
    -- Ən spesifik permission qalib gəlir (file > folder > root)
    CASE WHEN p.node_id = :file_id THEN 0 ELSE 1 END
LIMIT 1;
```

---

## Deduplication: Content Addressing

```
Məntiq:

User A: "photo.jpg" yükləyir (hash: abc123)
  → S3-ə yüklə: objects/abc123
  → nodes.storage_key = 'objects/abc123'

User B: eyni "photo.jpg" yükləyir (hash: abc123)
  → Hash artıq S3-dədir!
  → S3-ə YENİDƏN YÜKLƏMƏ
  → nodes.storage_key = 'objects/abc123'  (eyni key)

Storage savings: storage_key deduplicate edildi
Amma hər user-in öz nodes record-u var (privacy)

Content-Addressed Storage:
  key = SHA-256(file_content)
  "Same content → same key → same storage"
  
Reference counting:
  storage_objects tablosunda ref_count
  ref_count = 0 → GC (S3-dən sil)
```

---

## Chunked Upload: Redis

```
Böyük fayl upload (1GB video):

1. Client: "1GB faylı yükləyəcəm"
   → POST /upload/initiate
   → upload_session yaradılır (UUID)
   → total_chunks = 1000 (1MB × 1000)

2. Client: chunk-ları göndərir (parallel)
   → POST /upload/{session_id}/chunk/{chunk_index}
   → S3-ə: upload-temp/{session_id}/{chunk_index}
   
3. Redis: progress tracking
   SADD upload:chunks:{session_id} {chunk_index}
   SCARD upload:chunks:{session_id}  → neçəsi tamamlandı?

4. Bütün chunk-lar tamamlandı?
   → S3 Multipart Complete API
   → Chunk-ları birləşdir
   → nodes record yarat
   → Temp chunk-ları sil

Resume (network kəsikdən sonra):
   → GET /upload/{session_id}/status
   → Redis-dən hansı chunk-ların tamamlandığını qaytar
   → Client yalnız çatışmayanları yenidən göndərir
```

---

## Kritik Dizayn Qərarları

```
1. Unified nodes table (file + folder):
   Pros: sadə hierarchy, recursive CTE
   Cons: NULL check (file fields folder üçün NULL)
   Alt: ayrı file/folder table + parent link

2. Content hash deduplication:
   Storage cost azalır
   Privacy: user A-nın hash-i = user B-nın hash-i
   → user A silirsə, B-nin fayl getməsin!
   → ref_count ilə idarə et

3. ACL inheritance:
   Folder permission → child inherit
   Override mümkün: specific file daha az permission
   "Most specific wins"

4. Chunked upload atomicity:
   S3 Multipart Upload: ya hamısı, ya heç biri
   Temp chunk-lar ayrı prefix-də
   Session expires → cleanup job

5. Trash vs Delete:
   is_trashed = true → 30 gün sonra hard delete
   Soft delete: recovery mümkün
```

---

## Best Practices

```
✓ Content-based addressing (SHA-256) → deduplication
✓ Chunked upload → large files, resume capability
✓ Recursive CTE → folder hierarchy (PostgreSQL)
✓ Permission inheritance → folder → children
✓ Soft delete (trash) → 30-day recovery
✓ Version history → file_versions table
✓ Upload session in Redis → progress tracking

Anti-patterns:
✗ Storing file content in DB (BLOB) → S3 daha uyğun
✗ Eager permission check (N+1) → single recursive query
✗ Sync upload → async + webhook
✗ Storing full path string → hierarchy sorğuları çətin
```
