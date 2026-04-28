# URL Shortener — DB Design (Middle ⭐⭐)

## İcmal

URL shortener (bit.ly, TinyURL) görünüşcə sadə, amma maraqlı DB dizayn qərarları tələb edir. Əsas problemlər: unikal short code generasiyası, redirect sürəti, click analytics, spam/abuse qoruma.

---

## Tövsiyə olunan DB Stack

```
Primary:    PostgreSQL    (URLs, users, metadata)
Cache:      Redis         (hot URL-lər → O(1) redirect)
Analytics:  ClickHouse    (click events, zaman seriyası)
```

---

## Niyə Bu Stack?

```
Redirect sürəti kritikdir:
  Məqsəd: < 10ms redirect
  
  Redis cache: hot URLs → memory → < 1ms
  PostgreSQL: cold URLs, metadata, user data
  
Read-heavy: 100:1 (read:write)
  Hər short link dəfələrlə istifadə edilir
  Redis bu ratio üçün idealdır

Analytics ayrılmalıdır:
  Click event → async queue → ClickHouse
  Redirect request-i yavaşlatmamalı
```

---

## Core Schema

```sql
-- ==================== URLS ====================
CREATE TABLE urls (
    id              BIGSERIAL PRIMARY KEY,
    
    -- Short code (e.g., "abc123", "my-brand")
    code            VARCHAR(20) UNIQUE NOT NULL,
    
    -- Original long URL
    original_url    TEXT NOT NULL,
    
    -- Owner (NULL = anonymous)
    user_id         BIGINT REFERENCES users(id),
    
    -- Custom alias vs generated
    is_custom       BOOLEAN DEFAULT FALSE,
    
    -- Expiry (NULL = never)
    expires_at      TIMESTAMPTZ,
    
    -- Status
    is_active       BOOLEAN DEFAULT TRUE,
    
    -- Denormalized counter (approximate — exact data ClickHouse-da)
    click_count     BIGINT DEFAULT 0,
    
    -- Page title (crawl edilir)
    title           VARCHAR(500),
    
    created_at      TIMESTAMPTZ DEFAULT NOW(),
    updated_at      TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_urls_code    ON urls(code);
CREATE INDEX idx_urls_user    ON urls(user_id, created_at DESC);
CREATE INDEX idx_urls_expires ON urls(expires_at) WHERE expires_at IS NOT NULL;

-- ==================== CLICK EVENTS (PostgreSQL — partitioned) ====================
-- Real analytics ClickHouse-dadır; bu audit/qısa-müddət üçündür
CREATE TABLE click_events (
    id              BIGSERIAL,
    url_id          BIGINT NOT NULL REFERENCES urls(id),
    
    -- Geo (GeoIP lookup)
    ip_address      INET,
    country         CHAR(2),
    city            VARCHAR(100),
    
    -- Device
    device_type     VARCHAR(20),   -- 'desktop', 'mobile', 'tablet'
    browser         VARCHAR(50),
    os              VARCHAR(50),
    
    referrer        TEXT,
    
    clicked_at      TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (clicked_at);

CREATE TABLE click_events_2026_04 PARTITION OF click_events
    FOR VALUES FROM ('2026-04-01') TO ('2026-05-01');

-- ==================== LINK GROUPS / CAMPAIGNS ====================
CREATE TABLE link_groups (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id),
    name        VARCHAR(100) NOT NULL,
    utm_source  VARCHAR(100),   -- UTM tracking
    utm_medium  VARCHAR(100),
    utm_campaign VARCHAR(100),
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE url_group_members (
    url_id      BIGINT NOT NULL REFERENCES urls(id),
    group_id    BIGINT NOT NULL REFERENCES link_groups(id),
    PRIMARY KEY (url_id, group_id)
);

-- ==================== CUSTOM DOMAINS ====================
-- Hər user öz domainini əlavə edə bilər (brand.link/abc)
CREATE TABLE custom_domains (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users(id),
    domain      VARCHAR(255) UNIQUE NOT NULL,   -- 'go.acme.com'
    is_verified BOOLEAN DEFAULT FALSE,
    ssl_enabled BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

---

## Short Code Generation

```
Strategiyalar:

1. Random Base62:
   Characters: [a-z A-Z 0-9] = 62 chars
   6 chars → 62^6 = 56 billion potential codes
   7 chars → 62^7 = 3.5 trillion potential codes
   
   Collision: random → INSERT → conflict → retry (nadir)

2. Counter-based Base62 (tövsiyə):
   BIGSERIAL ID → Base62 encode
   ID 1       → "1"
   ID 12345   → "3d7" (deterministic, sequential)
   
   Pro: collision yoxdur
   Con: predictable (sequential IDs guess edilə bilər)
   Fix: ID + random salt XOR

3. Hash-based:
   SHA256(original_url) → first 7 chars
   Eyni URL → eyni code (natural dedup)
   Problem: fərqli user eyni URL → eyni code conflict

4. Custom alias:
   User "my-brand" seçir (alphanumeric + dash)
   DB UNIQUE constraint enforce edir

PHP Base62 encode:
  function toBase62(int $id): string {
      $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $result = '';
      while ($id > 0) {
          $result = $chars[$id % 62] . $result;
          $id = intdiv($id, 62);
      }
      return $result ?: '0';
  }
```

---

## Redirect Pipeline

```
Request: GET https://short.ly/abc123

1. DNS → Load Balancer → App server

2. Redis lookup (< 1ms):
   GET url:code:abc123
   Hit → 302 redirect → done

3. Cache miss → PostgreSQL:
   SELECT original_url, expires_at, is_active
   FROM urls WHERE code = 'abc123'
   LIMIT 1

4. Not found → 404

5. Expired (expires_at < NOW()) → 410 Gone

6. is_active = false → 404

7. Cache in Redis:
   SETEX url:code:abc123 86400 "https://example.com/very-long-url"
   (TTL: 24 saat)

8. Async click event:
   LPUSH click_queue '{"url_id":123,"ip":"...","ua":"...","ts":...}'
   Consumer: ClickHouse + counter update

Redis key design:
  url:code:{code}    → original URL string
  url:meta:{code}    → JSON (user_id, expires_at, is_active)

301 vs 302:
  301 Permanent: browser cache edir → analytics miss!
  302 Temporary: hər redirect server-ə gəlir → analytics düzgün
  Seçim: analytics lazımdırsa 302 (bit.ly hybrid: 301 + JS pixel)
```

---

## Click Analytics (ClickHouse)

```sql
CREATE TABLE url_clicks (
    click_time      DateTime,
    click_date      Date MATERIALIZED toDate(click_time),

    url_id          Int64,
    code            String,

    country         FixedString(2),
    city            String,

    device_type     LowCardinality(String),   -- 'mobile', 'desktop'
    browser         LowCardinality(String),
    os              LowCardinality(String),

    referrer_domain LowCardinality(String)    -- 'google.com', 'twitter.com'

) ENGINE = MergeTree()
  PARTITION BY click_date
  ORDER BY (url_id, click_time)
  TTL click_date + INTERVAL 2 YEAR;

-- Son 30 gündə ölkə üzrə kliklər
SELECT country, count() AS clicks
FROM url_clicks
WHERE url_id = 12345 AND click_date >= today() - 30
GROUP BY country ORDER BY clicks DESC;

-- Saatlik trafik (son 24 saat)
SELECT toStartOfHour(click_time) AS hour, count() AS clicks
FROM url_clicks
WHERE url_id = 12345 AND click_time >= now() - INTERVAL 24 HOUR
GROUP BY hour ORDER BY hour;

-- Zero-click links (heç istifadə edilməyib)
SELECT code, original_url, created_at
FROM url_clicks RIGHT JOIN urls ON url_id = urls.id
WHERE url_id IS NULL AND urls.created_at < now() - INTERVAL 7 DAY;
```

---

## Rate Limiting (Spam Qoruma)

```
Limitlər:
  Anonymous: 5 URL / saat
  Free user:  100 URL / saat
  Pro user:   1000 URL / saat

Redis:
  INCR  ratelimit:create:ip:{ip}:{hour}
  EXPIRE ratelimit:create:ip:{ip}:{hour} 3600
  → count > limit → 429 Too Many Requests

Custom alias abuse:
  Blacklist: 'admin', 'api', 'login', 'help', 'www'
  Reserved words: şirkət adları
  Obscene words: filtration

Phishing qoruma:
  Google Safe Browsing API ilə URL yoxla
  Virus Total API
  Suspicious domains blacklist
```

---

## Kritik Dizayn Qərarları

```
1. 301 vs 302:
   301 (Permanent): browser cache → server yükü az, analytics miss
   302 (Temporary): hər redirect tracked, server yükü çox
   Tövsiyə: analytics lazımdırsa 302

2. Deduplication:
   Eyni URL → eyni code? (TinyURL edir, bit.ly etmir)
   Bit.ly: hər user öz unique code-unu alır
   TinyURL: hash-based → eyni URL → eyni short link

3. Click counter sync strategy:
   urls.click_count → approximate (Redis INCR, batch flush to DB)
   ClickHouse → exact, analytical
   Hər redirect-də sync DB write etmə → yavaşlar

4. Expiry handling:
   expires_at nullable → NULL = never expires
   Expired-ı sil? → Soft delete (is_active = false)
   Hard delete: 30 gün sonra cron job

5. Custom domains:
   https://go.acme.com/promo vs https://short.ly/xyz
   Reverse proxy: DNS → custom domain → platform
```

---

## Tanınmış Sistemlər

```
bit.ly:
  Stack: MySQL + Redis + ClickHouse
  Feature: custom domains, team analytics, QR codes
  301 redirect + JavaScript analytics pixel
  600M+ links, 8B+ clicks/month

TinyURL (2002):
  MySQL, hash-based dedup
  Eyni URL → eyni short link
  Ən qısa kod: tinyurl.com/xyz

Rebrandly:
  Custom domains focus
  MySQL + Redis

Short.io:
  Multi-tenant with custom domains
  PostgreSQL + Redis

Bitly Enterprise:
  Custom domain per account
  Team permissions, link groups
  Campaign UTM builder

Ölçü:
  bit.ly: 300ms redirect (CDN-dən) hədəf
  7 char Base62 = 3.5 trillion potential links
  Storage: 1 URL ≈ 500 bytes → 1B URL ≈ 500 GB
```

---

## Best Practices

```
✓ Base62 (7 chars): URL-safe, compact, 3.5T capacity
✓ Redis caching: hot URLs memory-də → < 1ms redirect
✓ 302 redirect: analytics tracking üçün
✓ Async click tracking: redirect sürətini qoruyur
✓ Expires_at: TTL link support (kampaniyalar üçün)
✓ Rate limiting: per-IP + per-user
✓ Blacklist: reserved words + phishing check

Anti-patterns:
✗ Sequential IDs as codes: predictable, enumerate edilə bilər
✗ Sync click write: redirect-i yavaşladır
✗ 301 + analytics: browser cache analytics-i bypass edir
✗ DB-only redirect: cache yoxsa her redirect-də DB query
✗ No rate limiting: spam/DDoS vector
```
