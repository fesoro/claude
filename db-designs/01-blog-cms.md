# Blog / CMS App — DB Design

## Tövsiyə olunan DB Stack
```
Primary:    PostgreSQL    (məqalələr, istifadəçilər, şərhlər — ACID + FTS)
Cache:      Redis         (məqalə cache, session, view counter)
Search:     PostgreSQL    (tsvector — kiçik miqyas) / Elasticsearch (böyük)
Media:      S3 + CDN      (şəkillər, fayllar)
Analytics:  ClickHouse    (oxuma statistikası — isteğe bağlı)
```

---

## Niyə PostgreSQL Tək Başına Kifayətdir?

```
Blog/CMS üçün:
  ✓ Sadə data modeli: posts, users, tags, comments
  ✓ Full-text search: tsvector + trgm (trigram similarity)
  ✓ JSONB: flexible metadata, SEO settings
  ✓ Yüksək trafik olmadıqda scale problemi yoxdur
  ✓ WordPress, Ghost, Strapi → hamısı relational DB
  
Ne zaman Elasticsearch əlavə edilir?
  > 100K post VƏ complex search lazımdırsa
  Faceted search (kategoriya + tag + tarix filtrləri)
```

---

## Schema Design

```sql
-- ==================== USERS & ROLES ====================
CREATE TABLE users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    bio           TEXT,
    avatar_url    TEXT,
    social_links  JSONB DEFAULT '{}',  -- {twitter, linkedin, github}
    role          VARCHAR(20) DEFAULT 'author',  -- admin, editor, author, subscriber
    status        VARCHAR(20) DEFAULT 'active',
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== CONTENT ====================
CREATE TABLE posts (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    slug            VARCHAR(255) UNIQUE NOT NULL,
    title           VARCHAR(500) NOT NULL,
    excerpt         TEXT,              -- qısa xülasə
    content         TEXT NOT NULL,     -- Markdown / HTML
    content_html    TEXT,              -- rendered HTML (cache)
    featured_image  TEXT,              -- S3 URL
    author_id       UUID NOT NULL REFERENCES users(id),
    
    -- Status machine
    status          VARCHAR(20) DEFAULT 'draft',
    -- draft, review, scheduled, published, archived
    
    published_at    TIMESTAMPTZ,
    scheduled_at    TIMESTAMPTZ,       -- gələcək tarixdə yayımlamaq
    
    -- SEO
    seo_title       VARCHAR(255),
    seo_description TEXT,
    canonical_url   TEXT,
    
    -- Settings
    allow_comments  BOOLEAN DEFAULT TRUE,
    is_featured     BOOLEAN DEFAULT FALSE,
    is_premium      BOOLEAN DEFAULT FALSE,  -- ödənişli oxucular
    
    -- Metadata (flexible)
    metadata        JSONB DEFAULT '{}',
    
    -- Stats (denormalized)
    view_count      BIGINT DEFAULT 0,
    like_count      INT DEFAULT 0,
    comment_count   INT DEFAULT 0,
    share_count     INT DEFAULT 0,
    reading_time    SMALLINT,          -- dəqiqə (avtomatik hesablanır)
    
    -- Full-text search vector
    search_vector   TSVECTOR,
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

-- FTS index
CREATE INDEX idx_posts_search ON posts USING GIN (search_vector);
-- Sürətli yayımlanmış məqalələr
CREATE INDEX idx_posts_published ON posts(published_at DESC)
    WHERE status = 'published';
CREATE INDEX idx_posts_author ON posts(author_id, published_at DESC);
CREATE INDEX idx_posts_featured ON posts(is_featured, published_at DESC)
    WHERE status = 'published' AND is_featured = TRUE;

-- Trigger: search_vector avtomatik yenilənsin
CREATE OR REPLACE FUNCTION posts_search_trigger() RETURNS TRIGGER AS $$
BEGIN
    NEW.search_vector :=
        setweight(to_tsvector('simple', COALESCE(NEW.title, '')), 'A') ||
        setweight(to_tsvector('simple', COALESCE(NEW.excerpt, '')), 'B') ||
        setweight(to_tsvector('simple', COALESCE(NEW.content, '')), 'C');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER posts_search_update
    BEFORE INSERT OR UPDATE ON posts
    FOR EACH ROW EXECUTE FUNCTION posts_search_trigger();

-- Post revisions (version history)
CREATE TABLE post_revisions (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id    UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    author_id  UUID REFERENCES users(id),
    title      VARCHAR(500),
    content    TEXT,
    comment    VARCHAR(255),   -- "Minor fixes", "Added conclusion"
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== TAXONOMY ====================
CREATE TABLE categories (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id   UUID REFERENCES categories(id),
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    color       CHAR(7),       -- "#FF5733"
    image_url   TEXT,
    sort_order  INT DEFAULT 0,
    post_count  INT DEFAULT 0  -- denormalized
);

CREATE TABLE tags (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name       VARCHAR(100) UNIQUE NOT NULL,
    slug       VARCHAR(100) UNIQUE NOT NULL,
    post_count INT DEFAULT 0
);

CREATE TABLE post_categories (
    post_id     UUID REFERENCES posts(id) ON DELETE CASCADE,
    category_id UUID REFERENCES categories(id),
    is_primary  BOOLEAN DEFAULT FALSE,  -- əsas kateqoriya
    PRIMARY KEY (post_id, category_id)
);

CREATE TABLE post_tags (
    post_id UUID REFERENCES posts(id) ON DELETE CASCADE,
    tag_id  UUID REFERENCES tags(id),
    PRIMARY KEY (post_id, tag_id)
);

CREATE INDEX idx_post_tags_tag ON post_tags(tag_id);

-- ==================== COMMENTS ====================
CREATE TABLE comments (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id     UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    parent_id   UUID REFERENCES comments(id),    -- nested comments
    user_id     UUID REFERENCES users(id),        -- NULL = anonim
    guest_name  VARCHAR(100),  -- anonim şərh üçün
    guest_email VARCHAR(255),
    body        TEXT NOT NULL,
    status      VARCHAR(20) DEFAULT 'pending',  -- pending, approved, spam, trash
    ip_address  INET,
    user_agent  TEXT,
    like_count  INT DEFAULT 0,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_comments_post ON comments(post_id, created_at)
    WHERE status = 'approved' AND parent_id IS NULL;

-- ==================== MEDIA LIBRARY ====================
CREATE TABLE media (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    uploader_id UUID REFERENCES users(id),
    filename    VARCHAR(255) NOT NULL,
    storage_key TEXT NOT NULL,           -- S3 key
    url         TEXT NOT NULL,
    thumbnail_url TEXT,
    mime_type   VARCHAR(100) NOT NULL,
    size_bytes  BIGINT NOT NULL,
    width       INT,
    height      INT,
    alt_text    TEXT,
    caption     TEXT,
    metadata    JSONB DEFAULT '{}',      -- EXIF data
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== SUBSCRIBERS & NEWSLETTERS ====================
CREATE TABLE subscribers (
    id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email          VARCHAR(255) UNIQUE NOT NULL,
    name           VARCHAR(100),
    status         VARCHAR(20) DEFAULT 'pending',  -- pending, confirmed, unsubscribed
    confirmed_at   TIMESTAMPTZ,
    unsubscribed_at TIMESTAMPTZ,
    source         VARCHAR(50),   -- 'homepage', 'post_cta', 'import'
    metadata       JSONB DEFAULT '{}',
    created_at     TIMESTAMPTZ DEFAULT NOW()
);

-- ==================== ANALYTICS ====================
CREATE TABLE post_views (
    post_id    UUID REFERENCES posts(id),
    date       DATE NOT NULL,
    hour       SMALLINT,           -- 0-23
    view_count INT DEFAULT 0,
    unique_views INT DEFAULT 0,
    PRIMARY KEY (post_id, date, hour)
);
-- Hər saat aggregate edilir (Redis-dən batch write)
```

---

## Redis Dizaynı

```
# View counter (sürətli increment, DB-ə batch yazma)
INCR views:{post_id}:{date}:{hour}
-- Hər saat DB-yə yazılır, Redis sıfırlanır

# Trending posts (son 24 saatda ən çox oxunan)
ZINCRBY trending:posts:{date} 1 {post_id}
ZREVRANGE trending:posts:{today} 0 9   -- top 10

# Məqalə cache (rendered HTML)
SET post:rendered:{slug} {html} EX 3600  -- 1 saat
DEL post:rendered:{slug}                 -- publish/update zamanı

# Session
SET session:{token} {user_json} EX 86400

# Comment count cache
INCR comment_count:{post_id}
GET comment_count:{post_id}

# Rate limiting: comment spam
SET comment_rate:{ip} 1 EX 60  -- dəqiqədə max 5 şərh
INCR comment_rate:{ip}
```

---

## Kritik Dizayn Qərarları

```
1. content_html pre-rendered saxlamaq:
   Markdown → HTML çevirmə hər oxumada bahalıdır
   content (Markdown) saxlanılır (edit üçün)
   content_html (rendered) cache kimi saxlanılır
   Publish/update zamanı yenilənir

2. search_vector Trigger ilə avtomatik yenilənir:
   title (weight A) + excerpt (B) + content (C)
   WHERE search_vector @@ query — index istifadəsi
   Əl ilə update etmək lazım deyil

3. Post revisions:
   Hər save → revision yaradılır
   Geri qaytarma mümkündür
   Storage: yalnız content saxlanır (digər sahələr lazım deyil)

4. Scheduled posts:
   status = 'scheduled', scheduled_at = gələcək tarix
   CRON job: SELECT * FROM posts WHERE status = 'scheduled' AND scheduled_at <= NOW()
   → status = 'published', published_at = NOW()

5. Anonim şərhlər:
   user_id NULL, guest_name + guest_email
   Status = 'pending' (moderasiya lazım)
   IP + user_agent: spam detection
```

---

## Best Practices

```
✓ FTS üçün tsvector trigger (search column ayrı saxla)
✓ Partial index: WHERE status = 'published'
✓ View count Redis-də (batch sync DB-yə)
✓ Post content revision table-da saxla (undo mümkün)
✓ Slug unique + immutable (SEO link-ləri pozulmur)
✓ Scheduled publish üçün cron + partial index
✓ Comment moderation queue (status = 'pending')
✓ Canonical URL: duplicate content SEO problemi

Anti-patterns:
✗ Markdown-ı hər dəfə render etmək
✗ View count SELECT COUNT → Redis istifadə et
✗ Slug-ı sonradan dəyişmək (301 redirect lazım olur)
✗ Nestedsiz comments (parent_id olmadan) — limitləri var
```

---

## Tanınmış CMS Sistemlər

```
WordPress:
  MySQL/MariaDB     → məqalələr, meta, options
  wp_posts          → hər şey (post, page, attachment, menu)
  wp_postmeta       → EAV pattern (flexible attributes)
  wp_options        → site settings (böyük serialized PHP)
  
  Problem: wp_options table lock contentions
  Həll: Redis Object Cache, WP-CLI

Ghost (Node.js CMS):
  SQLite (development), MySQL/PostgreSQL (production)
  Clean schema, modern design

Medium:
  DynamoDB + MySQL   → posts, users
  Neo4j              → tags, recommendations (graph)
  Cassandra          → activity, notifications
```
