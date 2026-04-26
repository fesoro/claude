# URL Shortener Design (Middle)

## İcmal

URL shortener uzun URL-ləri qısa, unikal linklərə çevirən sistemdir. bit.ly, tinyurl.com
kimi xidmətlər buna nümunədir. İstifadəçi qısa linki kliklədikdə orijinal URL-ə
redirect olunur. Analitika, custom alias, expiration kimi funksiyalar da əlavə edilir.

Sadə dillə: uzun ünvanı qısa abbreviatura ilə əvəz etmək kimi -
"https://example.com/very/long/path" → "https://short.ly/abc123"

```
Input:  https://example.com/articles/2024/system-design-interview-guide?ref=twitter
Output: https://short.ly/aB3x7K

User clicks short.ly/aB3x7K → 301 Redirect → original URL
```


## Niyə Vacibdir

URL shortener klassik system design interview sualıdır — Base62, hashing, redirect mexanizmi, analitika, cache strategiyası hamısını bir problem üzərindən öyrənmək mümkündür. Görünüşcə sadə, amma scale-da maraqlı distributed problem ortaya çıxır.

## Əsas Anlayışlar

### Requirements

**Functional:**
- URL qısaltma (long → short)
- URL redirect (short → long, 301/302)
- Custom alias (istəyə bağlı)
- Expiration (TTL)
- Analytics (click count, location, device)

**Non-Functional:**
- Low latency redirect (< 50ms)
- High availability (99.99%)
- 100M URLs/month write, 10B redirects/month read
- Short URL max 7 characters

### Capacity Estimation

```
Write: 100M URLs/month = ~40 URLs/second
Read:  10B redirects/month = ~4000 reads/second (100:1 read/write ratio)

Storage per URL: ~500 bytes (short + long URL + metadata)
5 years: 100M × 12 × 5 = 6B URLs × 500B = 3TB

Cache: 20% of daily traffic = 3.2B/30 × 0.2 × 500B = ~10GB cache
```

### Base62 Encoding

```
Characters: [a-z, A-Z, 0-9] = 62 characters

7 chars: 62^7 = 3.5 trillion unique URLs (kifayət qədər)
6 chars: 62^6 = 56.8 billion

ID: 12345678
Base62: "dnh8"

Encoding table:
0-9:   0-9
10-35: a-z
36-61: A-Z
```

```php
class Base62Encoder
{
    private const CHARS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function encode(int $number): string
    {
        if ($number === 0) return '0';

        $result = '';
        while ($number > 0) {
            $result = self::CHARS[$number % 62] . $result;
            $number = intdiv($number, 62);
        }

        return $result;
    }

    public function decode(string $encoded): int
    {
        $result = 0;
        $length = strlen($encoded);
        for ($i = 0; $i < $length; $i++) {
            $result = $result * 62 + strpos(self::CHARS, $encoded[$i]);
        }
        return $result;
    }
}
```

### Hash Collision Handling

```
Approach 1: Auto-increment ID + Base62
  ID: 1       → "1"
  ID: 1000    → "g8"
  ID: 1000000 → "4c92"
  No collision, predictable (security concern)

Approach 2: Hash (MD5/SHA256) + First 7 chars
  MD5("https://example.com") → "3e25960a..." → "3e25960"
  Collision possible → check DB, if exists add salt and rehash

Approach 3: Pre-generated keys (Key Generation Service)
  Batch generate unique keys in advance
  Assign from pool when needed
  No collision, no computation at write time
```

### Redirect: 301 vs 302

```
301 (Permanent Redirect):
  Browser caches → subsequent visits skip our server
  Better for SEO
  Less analytics data (cached visits not counted)

302 (Temporary Redirect):
  Browser always hits our server
  Better analytics (every visit counted)
  More server load

Recommendation: 302 for analytics, 301 for performance
```

## Arxitektura

### System Architecture

```
┌──────────┐     ┌───────────────┐     ┌──────────┐
│  Client  │────▶│  API Gateway  │────▶│ URL Svc  │
│          │     │  + Rate Limit │     │          │
└──────────┘     └───────────────┘     └────┬─────┘
                                            │
                                    ┌───────┼───────┐
                                    │       │       │
                              ┌─────┴──┐ ┌──┴───┐ ┌┴────────┐
                              │ Cache  │ │  DB  │ │Analytics│
                              │ (Redis)│ │(MySQL)│ │ (Kafka) │
                              └────────┘ └──────┘ └─────────┘

URL Creation Flow:
  1. Client POST /api/shorten {url: "..."}
  2. Validate URL
  3. Generate short code (Base62 or hash)
  4. Store in DB
  5. Return short URL

Redirect Flow:
  1. Client GET /{shortCode}
  2. Check cache (Redis)
  3. If miss → check DB → populate cache
  4. Log analytics event (async)
  5. Return 302 redirect
```

### Database Design

```sql
-- URLs table
CREATE TABLE urls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_code VARCHAR(10) UNIQUE NOT NULL,
    original_url TEXT NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    custom_alias BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    click_count BIGINT UNSIGNED DEFAULT 0,

    INDEX idx_short_code (short_code),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Analytics table (separate, high-write)
CREATE TABLE url_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_id BIGINT UNSIGNED NOT NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer TEXT,
    country VARCHAR(2),
    device_type ENUM('desktop', 'mobile', 'tablet'),

    INDEX idx_url_id_clicked (url_id, clicked_at)
);
```

## Nümunələr

### URL Shortener Service

```php
class UrlShortenerService
{
    public function __construct(
        private Base62Encoder $encoder,
        private UrlRepository $repository,
        private CacheService $cache
    ) {}

    public function shorten(string $originalUrl, ?int $userId = null, ?string $customAlias = null): ShortUrl
    {
        // Validate URL
        if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException('Invalid URL provided');
        }

        // Check for custom alias
        if ($customAlias) {
            if ($this->repository->shortCodeExists($customAlias)) {
                throw new AliasAlreadyExistsException();
            }
            $shortCode = $customAlias;
        } else {
            $shortCode = $this->generateShortCode();
        }

        $url = $this->repository->create([
            'short_code' => $shortCode,
            'original_url' => $originalUrl,
            'user_id' => $userId,
            'custom_alias' => (bool) $customAlias,
        ]);

        // Pre-warm cache
        $this->cache->set("url:{$shortCode}", $originalUrl, ttl: 86400);

        return $url;
    }

    public function resolve(string $shortCode): ?string
    {
        // Check cache first
        $cached = $this->cache->get("url:{$shortCode}");
        if ($cached) {
            return $cached;
        }

        // Check database
        $url = $this->repository->findByShortCode($shortCode);
        if (!$url) return null;

        // Check expiration
        if ($url->expires_at && $url->expires_at->isPast()) {
            return null;
        }

        // Cache for future requests
        $this->cache->set("url:{$shortCode}", $url->original_url, ttl: 86400);

        return $url->original_url;
    }

    private function generateShortCode(): string
    {
        // Use auto-increment ID + Base62
        $counter = $this->getNextId();
        return $this->encoder->encode($counter);
    }

    private function getNextId(): int
    {
        // Distributed counter using Redis
        return (int) Redis::incr('url_shortener:counter');
    }
}
```

### Controller

```php
class UrlController extends Controller
{
    public function __construct(
        private UrlShortenerService $shortener,
        private AnalyticsService $analytics
    ) {}

    public function shorten(ShortenUrlRequest $request): JsonResponse
    {
        $shortUrl = $this->shortener->shorten(
            originalUrl: $request->validated('url'),
            userId: auth()->id(),
            customAlias: $request->validated('custom_alias')
        );

        return response()->json([
            'short_url' => config('app.short_url') . '/' . $shortUrl->short_code,
            'original_url' => $shortUrl->original_url,
            'expires_at' => $shortUrl->expires_at,
        ], 201);
    }

    public function redirect(string $shortCode): RedirectResponse
    {
        $originalUrl = $this->shortener->resolve($shortCode);

        if (!$originalUrl) {
            abort(404, 'Short URL not found or expired');
        }

        // Track analytics asynchronously
        TrackUrlClick::dispatch($shortCode, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referer' => request()->header('Referer'),
        ]);

        return redirect()->away($originalUrl, 302);
    }

    public function stats(string $shortCode): JsonResponse
    {
        $this->authorize('view-stats');

        $stats = $this->analytics->getStats($shortCode);

        return response()->json($stats);
    }
}
```

### Analytics Service

```php
class AnalyticsService
{
    public function trackClick(string $shortCode, array $metadata): void
    {
        $urlId = $this->getUrlId($shortCode);

        // Increment counter (fast, atomic)
        DB::table('urls')->where('id', $urlId)->increment('click_count');

        // Detailed analytics (async via queue)
        DB::table('url_clicks')->insert([
            'url_id' => $urlId,
            'ip_address' => $metadata['ip'],
            'user_agent' => $metadata['user_agent'],
            'referer' => $metadata['referer'],
            'country' => $this->geolocate($metadata['ip']),
            'device_type' => $this->detectDevice($metadata['user_agent']),
            'clicked_at' => now(),
        ]);
    }

    public function getStats(string $shortCode): array
    {
        $url = ShortUrl::where('short_code', $shortCode)->firstOrFail();

        return [
            'total_clicks' => $url->click_count,
            'clicks_by_day' => DB::table('url_clicks')
                ->where('url_id', $url->id)
                ->where('clicked_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(clicked_at) as date, COUNT(*) as clicks')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'top_countries' => DB::table('url_clicks')
                ->where('url_id', $url->id)
                ->selectRaw('country, COUNT(*) as clicks')
                ->groupBy('country')
                ->orderByDesc('clicks')
                ->limit(10)
                ->get(),
            'device_breakdown' => DB::table('url_clicks')
                ->where('url_id', $url->id)
                ->selectRaw('device_type, COUNT(*) as clicks')
                ->groupBy('device_type')
                ->get(),
            'top_referers' => DB::table('url_clicks')
                ->where('url_id', $url->id)
                ->whereNotNull('referer')
                ->selectRaw('referer, COUNT(*) as clicks')
                ->groupBy('referer')
                ->orderByDesc('clicks')
                ->limit(10)
                ->get(),
        ];
    }
}
```

### Key Generation Service (Pre-generated keys)

```php
class KeyGenerationService
{
    private const BATCH_SIZE = 10000;

    // Pre-generate keys and store in Redis
    public function generateBatch(): void
    {
        $keys = [];
        for ($i = 0; $i < self::BATCH_SIZE; $i++) {
            $keys[] = $this->generateRandomKey(7);
        }

        // Remove duplicates and already used keys
        $keys = array_unique($keys);
        $existing = DB::table('urls')->whereIn('short_code', $keys)->pluck('short_code');
        $keys = array_diff($keys, $existing->toArray());

        Redis::sadd('available_keys', ...$keys);
    }

    public function getKey(): string
    {
        $key = Redis::spop('available_keys');

        if (!$key) {
            // Pool exhausted, generate more
            $this->generateBatch();
            $key = Redis::spop('available_keys');
        }

        return $key;
    }

    private function generateRandomKey(int $length): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $key = '';
        for ($i = 0; $i < $length; $i++) {
            $key .= $chars[random_int(0, 61)];
        }
        return $key;
    }
}
```

## Real-World Nümunələr

1. **bit.ly** - Ən populyar URL shortener, enterprise analytics
2. **TinyURL** - İlk URL shortener-lərdən biri, sadə istifadə
3. **t.co** - Twitter-in daxili shortener-i, hər link t.co-dan keçir
4. **goo.gl** - Google-un shortener-i (artıq bağlanıb, Firebase Dynamic Links)
5. **Rebrandly** - Custom domain branded short links

## Praktik Tapşırıqlar

**S1: Base62 encoding niyə istifadə olunur?**
C: URL-safe character set (a-z, A-Z, 0-9). 7 simvol ilə 3.5 trillion unikal URL
yaradıla bilər. Base64-dən fərqli olaraq + və / olmur, URL-lərdə problem yaratmır.

**S2: Hash collision necə həll olunur?**
C: Üç yanaşma: 1) Auto-increment ID + Base62 (collision yox amma predictable),
2) Hash + collision check + rehash with salt, 3) Pre-generated key pool (ən yaxşı -
collision yox, predictable deyil, sürətli).

**S3: 301 vs 302 redirect fərqi nədir?**
C: 301 permanent - browser cache edir, sonrakı ziyarətlər birbaşa gedir (analytics
itirir). 302 temporary - hər dəfə server-ə gəlir (tam analytics amma daha çox load).
Analytics vacibdirsə 302, performance vacibdirsə 301.

**S4: Read-heavy workload necə optimize olunur?**
C: Multi-layer caching - Redis (in-memory, microseconds), CDN edge caching.
Database read replicas. Cache miss ratio 1%-dən az olmalıdır.
Popular URL-ləri əvvəlcədən cache edin.

**S5: URL shortener-in bottleneck-ləri hansılardır?**
C: Write path - unique short code generation (distributed counter və ya key pool
ilə həll). Read path - database lookup (cache ilə həll). Analytics - high volume
click tracking (async queue + batch insert ilə həll).

**S6: Custom alias feature necə implement olunur?**
C: User istədiyi alias-ı daxil edir, availability check olunur (DB unique constraint),
reserved words listi ilə müqayisə (api, admin, login kimi), profanity filter,
minimum/maximum length validation.

**S7: Expired URL-lər necə təmizlənir?**
C: Lazy deletion - redirect zamanı expiry yoxla, expired isə 404 qaytar.
Active cleanup - scheduled job ilə expired URL-ləri batch delete et.
Cache TTL ilə expired URL-lər cache-dən avtomatik silinir.

## Praktik Baxış

1. **Cache Ağırlıqlı** - Redirect-lər cache-dən serve olunmalıdır
2. **Async Analytics** - Click tracking queue-da async edin
3. **Rate Limiting** - URL creation-a rate limit qoyun (abuse prevention)
4. **URL Validation** - Malicious URL-ləri yoxlayın (Google Safe Browsing API)
5. **Custom Alias** - Reserved words, profanity filter tətbiq edin
6. **Expiration** - Default TTL qoyun, expired URL-ləri təmizləyin
7. **HTTPS** - Bütün redirect-lər HTTPS olmalıdır
8. **Monitoring** - Redirect latency, error rate, cache hit ratio track edin
9. **Abuse Prevention** - Spam, phishing URL-ləri detect edin
10. **Backup** - Regular database backup, disaster recovery plan


## Əlaqəli Mövzular

- [Caching](03-caching-strategies.md) — redirect-i cache etmək
- [Data Partitioning](26-data-partitioning.md) — milyardlarla URL saxlamaq
- [Database Design](09-database-design.md) — index strategiyası
- [Probabilistic Data Structures](33-probabilistic-data-structures.md) — Bloom filter ilə collision
- [Distributed ID Generation](68-distributed-id-generation.md) — unikal short code yaratmaq
