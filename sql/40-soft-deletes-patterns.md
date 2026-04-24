# Soft Deletes Patterns & Pitfalls

> **Seviyye:** Intermediate ⭐⭐

## Soft Delete nedir?

Row-u DB-den **fiziki silmek yerine**, "silinmis" kimi qeyd edirsen. Adeten `deleted_at` timestamp column ile.

```sql
-- Hard delete
DELETE FROM users WHERE id = 1;

-- Soft delete
UPDATE users SET deleted_at = NOW() WHERE id = 1;

-- "Active" user-leri tap
SELECT * FROM users WHERE deleted_at IS NULL;
```

**Niye?**
- Audit trail - kim, ne vaxt sildi gorunur
- Restore mumkun - "Trash" / "Recycle Bin" kimi
- FK reference qirilmaz - cascade problem-leri yoxdur
- Compliance - data retention policy

---

## Laravel SoftDeletes Trait

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
}

// Migration
Schema::table('posts', function (Blueprint $table) {
    $table->softDeletes();  // deleted_at TIMESTAMP NULL DEFAULT NULL
});
```

### Auto Behaviors

```php
// SELECT - avtomatik WHERE deleted_at IS NULL
Post::all();
// SELECT * FROM posts WHERE deleted_at IS NULL;

// DELETE - hard delete deyil, UPDATE
$post->delete();
// UPDATE posts SET deleted_at = NOW() WHERE id = ?;

// Trash-da olan-lari da gor
Post::withTrashed()->get();
// SELECT * FROM posts;

// Yalniz silinmislere bax
Post::onlyTrashed()->get();
// SELECT * FROM posts WHERE deleted_at IS NOT NULL;

// Restore
$post->restore();
// UPDATE posts SET deleted_at = NULL WHERE id = ?;

// Heqiqi silmek
$post->forceDelete();
// DELETE FROM posts WHERE id = ?;

// Yoxlama
$post->trashed();  // bool
```

---

## Pitfall 1: UNIQUE Constraint + Soft Delete

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    deleted_at TIMESTAMP NULL
);

-- Anna yaradilir
INSERT INTO users (email) VALUES ('anna@x.com');

-- Soft delete
UPDATE users SET deleted_at = NOW() WHERE email = 'anna@x.com';

-- Anna yeniden qeydiyyatdan kecmek isteyir
INSERT INTO users (email) VALUES ('anna@x.com');
-- ERROR: Duplicate entry 'anna@x.com'
-- UNIQUE deleted row-u da nezere alir!
```

### Hell 1: Composite Unique (email + deleted_at)

```sql
ALTER TABLE users DROP INDEX users_email_unique;
ALTER TABLE users ADD UNIQUE KEY (email, deleted_at);

-- Indi:
-- (anna@x.com, NULL) - aktiv
-- (anna@x.com, '2026-04-24 ...') - silinmis
-- (anna@x.com, NULL) - yeniden aktiv (ERROR! 2 NULL)
```

**Problem:** MySQL-de NULL-lar UNIQUE-de **ferqli** sayilir, amma 2 ayni `(email, NULL)` ola biler.
PostgreSQL-de hemcinin NULL distinct (default) - amma `NULLS NOT DISTINCT` opsiya var (PG 15+).

### Hell 2: Partial Unique Index (PostgreSQL)

```sql
-- PostgreSQL - en temiz hell
CREATE UNIQUE INDEX users_email_active 
ON users (email) WHERE deleted_at IS NULL;

-- Yalniz active row-larda email unique
-- Silinmis row-lar (deleted_at IS NOT NULL) UNIQUE-e daxil deyil
```

### Hell 3: MySQL Generated Column

```sql
-- MySQL partial index desteklemir
ALTER TABLE users ADD COLUMN email_active VARCHAR(255) 
    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email END) STORED;
ALTER TABLE users ADD UNIQUE INDEX (email_active);
```

### Hell 4: Soft delete sira nomresi

```sql
-- Email-e suffix elave et soft delete zamani
UPDATE users 
SET email = CONCAT(email, '_deleted_', UNIX_TIMESTAMP()),
    deleted_at = NOW() 
WHERE id = 1;

-- 'anna@x.com' → 'anna@x.com_deleted_1714060815'
-- Indi yeni 'anna@x.com' yarana biler
```

> **Pis tereflər:** Email pozulur (email gondersen YANLIS gedir), restore zamani manual fix lazim.

---

## Pitfall 2: FK Cascade

```sql
CREATE TABLE posts (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User soft delete
UPDATE users SET deleted_at = NOW() WHERE id = 1;
-- Posts cascade OLMUR - cunki UPDATE-dir, DELETE deyil!
-- Posts hele de bu user-e baglidir

-- Hard delete
DELETE FROM users WHERE id = 1;
-- Indi posts cascade silinir
```

**Senaryo:** User soft-deleted-dir, amma posts-lari hele relation-da. User-i query-leyende `WHERE deleted_at IS NULL` yoxdur posts-da:

```php
$post = Post::find(1);
$post->user;  // null! (cunki global scope deleted user-i hide edir)
```

### Hell: Cascading Soft Delete

Manual cascade in observer:

```php
class UserObserver
{
    public function deleting(User $user)
    {
        $user->posts()->delete();      // soft delete cascade
        $user->comments()->delete();
    }

    public function restoring(User $user)
    {
        $user->posts()->restore();     // FK uzerinden restore
    }
}
```

Ya da package: `iatstuti/laravel-cascade-soft-deletes`:

```php
class User extends Model
{
    use SoftDeletes, CascadeSoftDeletes;
    
    protected $cascadeDeletes = ['posts', 'comments'];
}
```

---

## Pitfall 3: Query Performance

```sql
SELECT * FROM posts WHERE deleted_at IS NULL;
```

`deleted_at` index olmasa - full scan! Index ile:

```sql
CREATE INDEX idx_posts_deleted ON posts (deleted_at);
-- Amma cardinality asagi (NULL or NOT NULL)
```

**Daha yaxsi:** Composite index:

```sql
-- En cox query-lere uygun
CREATE INDEX idx_posts_user_deleted ON posts (user_id, deleted_at);
CREATE INDEX idx_posts_status_deleted ON posts (status, deleted_at);
```

**PostgreSQL partial index:**

```sql
-- Yalniz active row-lar ucun index (cox kicik, suretli)
CREATE INDEX idx_posts_user_active ON posts (user_id) WHERE deleted_at IS NULL;
```

---

## Pitfall 4: Forgotten WHERE deleted_at IS NULL

Raw query-lerde `SoftDeletes` global scope iseleminir:

```php
// ELOQUENT - avtomatik filter
Post::where('user_id', 1)->get();
// SELECT * FROM posts WHERE user_id = 1 AND deleted_at IS NULL;

// RAW QUERY - filter YOXDUR
DB::select('SELECT * FROM posts WHERE user_id = ?', [1]);
// SELECT * FROM posts WHERE user_id = 1; ← silinmisleri de qaytarir!

// JOIN-da unutmaq
DB::table('users')
    ->join('posts', 'posts.user_id', '=', 'users.id')
    ->get();
// posts.deleted_at filter YOXDUR

// DOGRU
DB::table('users')
    ->join('posts', function ($join) {
        $join->on('posts.user_id', '=', 'users.id')
             ->whereNull('posts.deleted_at');
    })
    ->get();
```

> **Qayda:** Raw SQL ve join-larda hemise `WHERE deleted_at IS NULL` elave et.

---

## Pitfall 5: Aggregation Tutarsizligi

```php
$user = User::find(1);
$user->posts_count;   // counter cache - 10
$user->posts()->count();  // 7 (3 silinmis)

// Counter cache-i update etmek lazim
```

```sql
-- Real count vs counter
SELECT 
    u.id,
    u.posts_count,                                          -- denormalized
    (SELECT COUNT(*) FROM posts p 
     WHERE p.user_id = u.id AND p.deleted_at IS NULL)       -- real
FROM users u;
```

**Hell:** Soft delete observer-ide counter-i update et:

```php
class PostObserver
{
    public function deleted(Post $post)
    {
        $post->user()->decrement('posts_count');
    }

    public function restored(Post $post)
    {
        $post->user()->increment('posts_count');
    }
}
```

---

## Restore Patterns

### Sade restore

```php
$post = Post::onlyTrashed()->find(1);
$post->restore();
```

### Bulk restore

```php
Post::onlyTrashed()
    ->where('deleted_at', '>', now()->subDays(7))
    ->restore();
```

### Restore with checks

```php
public function restore(Post $post): void
{
    // Owner hele de aktivdirmi?
    if (!$post->user || $post->user->trashed()) {
        throw new \Exception('Cannot restore post: owner is deleted');
    }

    // Eyni slug aktiv post varsa?
    if (Post::where('slug', $post->slug)->exists()) {
        throw new \Exception('Active post with same slug exists');
    }

    $post->restore();
}
```

---

## Soft Delete Alternatives

### 1. Archive Table

```sql
-- Active table - kicik, suretli
CREATE TABLE posts (id, title, ...);

-- Archive table - silinmis row-lar
CREATE TABLE posts_archive (id, title, ..., deleted_at, deleted_by);

-- Delete pattern
START TRANSACTION;
INSERT INTO posts_archive SELECT *, NOW(), 'user_id' FROM posts WHERE id = 1;
DELETE FROM posts WHERE id = 1;
COMMIT;
```

**Plus:**
- Active table kicik qalir, performance yuksek
- UNIQUE constraint problem-i yoxdur
- Index size kicik

**Minus:**
- Restore murekkebdir (INSERT BACK)
- Cross-table query lazim ola biler (UNION)
- Audit/history table ile birlesir adeten

### 2. Archived Boolean Flag

```sql
ALTER TABLE posts ADD COLUMN is_archived BOOLEAN DEFAULT FALSE;
CREATE INDEX idx_posts_archived ON posts (is_archived);

-- Filter
SELECT * FROM posts WHERE is_archived = FALSE;
```

**Soft deletes-den ferq:** "Ne vaxt silindi" yox - sadece "silinib ya yox". Audit ucun ayri timestamp lazimdir.

### 3. Status Enum

```sql
CREATE TABLE posts (
    status ENUM('draft', 'published', 'archived', 'deleted') DEFAULT 'draft'
);

SELECT * FROM posts WHERE status IN ('draft', 'published');
```

**Plus:** Multiple state-ler (draft, archived, deleted ferqlidir).
**Minus:** Index design diqqet teleb edir.

### 4. Event Sourcing

Hec ne fiziki silinmir - butun deyisiklik event kimi saxlanilir:

```sql
-- Events table
INSERT INTO post_events (post_id, event_type, payload, created_at)
VALUES (1, 'deleted', '{"by": "user_5"}', NOW());

-- Current state = events-den compute
```

Murekkebdir, amma audit ve undo cox guclu.

---

## GDPR / Right to Erasure Conflict

GDPR-e gore user data-sini **fiziki silmek** mecburidir, soft delete kifayet etmir:

```
"User has the right to obtain from the controller the erasure of personal data"
```

```php
// Soft delete YETERLI DEYIL
$user->delete();

// Hard delete + anonymize lazim
public function gdprErase(User $user): void
{
    DB::transaction(function () use ($user) {
        // 1. Personally identifiable info-nu temizle
        $user->update([
            'name' => 'Deleted User',
            'email' => "deleted_{$user->id}@deleted.local",
            'phone' => null,
            'address' => null,
        ]);

        // 2. Posts - anonymize edib saxla (audit ucun)
        $user->posts()->update(['author_name_snapshot' => 'Anonymous']);

        // 3. Personal-only data-ni hard delete
        $user->messages()->forceDelete();
        $user->files()->each(fn($f) => Storage::delete($f->path));
        $user->files()->forceDelete();

        // 4. User-i hard delete
        $user->forceDelete();

        // 5. Audit log
        Log::channel('gdpr')->info("User {$user->id} erased per GDPR request");
    });
}
```

> **Diqqet:** Backup-larda data hele de var. Backup retention policy ile koordinasiya lazim.

---

## Cleanup Jobs

Soft-deleted row-lar zamanla yigilir. Periyodik temizleme:

```php
// app/Console/Commands/PurgeSoftDeleted.php
class PurgeSoftDeleted extends Command
{
    protected $signature = 'cleanup:soft-deleted {--days=90}';

    public function handle(): void
    {
        $days = $this->option('days');
        $cutoff = now()->subDays($days);

        // Batched hard delete
        do {
            $deleted = DB::table('posts')
                ->whereNotNull('deleted_at')
                ->where('deleted_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $this->info("Deleted {$deleted} posts");
            sleep(1);  // DB-yə nefes
        } while ($deleted > 0);
    }
}

// Kernel.php
$schedule->command('cleanup:soft-deleted --days=90')->daily();
```

---

## When NOT to Use Soft Deletes

| Senaryo | Soft Delete? | Alternativ |
|---------|--------------|-----------|
| GDPR sensitive data | YOX | Hard delete + anonymize |
| Hot table (logs, events) | YOX | Partition + drop old |
| Aggregation cox vacib | OLMAZ | Archive table |
| Storage cost critical | YOX | Hard delete |
| Audit / undo lazimdir | BELI | Soft delete |
| User UX "trash" lazimdir | BELI | Soft delete |
| Multi-step workflow | BELI ya status enum | Status |

---

## Real Case: Multi-tenant SaaS

```php
// Tenant-in butun data-sini silmek
public function deleteTenant(int $tenantId): void
{
    // Soft delete - 30 gun gozle (recovery period)
    Tenant::find($tenantId)->update([
        'deleted_at' => now(),
        'scheduled_purge_at' => now()->addDays(30),
    ]);
    
    // Email user: "Acount deleted, 30 days to restore"
}

// Cron: scheduled_purge_at gelende hard delete
class PurgeScheduledTenants extends Command
{
    public function handle(): void
    {
        Tenant::onlyTrashed()
            ->where('scheduled_purge_at', '<', now())
            ->each(fn($t) => $this->hardDeleteTenant($t));
    }
}
```

---

## Interview suallari

**Q: Soft delete-in en boyuk pitfall-i hansidir?**
A: UNIQUE constraint problem-i. Email UNIQUE olan table-da user soft-deleted-dirse, eyni email-le yeniden qeydiyyat keceziye bilmez (UNIQUE pozulur). Hell: PostgreSQL partial unique index (`WHERE deleted_at IS NULL`), MySQL-de generated column ve ya soft delete zamani email-i deyisdirmek.

**Q: SoftDeletes ile hard delete arasinda ne vaxt secim?**
A: Soft delete - audit, undo, recovery lazimdir. Hard delete - GDPR compliance, storage cost, hot table (loglar), aggregation deqiqliyi. Bezi case-de hibrid: 30 gun soft delete (recovery period), sonra hard delete (cleanup job).

**Q: Soft delete query performance-e nece tesir edir?**
A: Her query-de `WHERE deleted_at IS NULL` filter elave olur - index olmasa full scan. Hell: composite index `(user_id, deleted_at)`, ya da PostgreSQL partial index `WHERE deleted_at IS NULL` - cox kicik ve suretli. Cox boyuk table-larda archive table alternative-i daha yaxsi performance verir.

**Q: GDPR ile soft delete-in konflikti necedir?**
A: GDPR "right to erasure" fiziki silmek teleb edir, soft delete kifayet etmir. Hell: PII (personally identifiable info) sahelerini hard delete et ya anonymize, audit lazim olan referenslar (orders, posts) saxla amma name/email-i "Deleted User"-e deyisdir. Backup-larda data hele var - retention policy lazimdir.

**Q: Cascading soft delete nece implement olunur?**
A: Laravel-de FK cascade yalniz hard delete-de iseleyir (DELETE statement). Soft delete UPDATE-dir, FK trigger olunmur. Hell: Observer-de `deleting` event-de related model-lari `delete()` cagir. Package: `iatstuti/laravel-cascade-soft-deletes` `$cascadeDeletes` array-i ile avtomatik edir. Restore-da hem reverse cascade lazim ola biler.
