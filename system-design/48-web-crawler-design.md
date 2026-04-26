# Web Crawler Design (Senior)

## İcmal

Web crawler internetdəki səhifələri avtomatik ziyarət edən, məzmunu yükləyən və linkləri
çıxararaq yeni səhifələrə keçən sistemdir. Google, Bing kimi axtarış motorlarının
əsasıdır — indexing-dən əvvəl crawler trilyonlarla URL-i gəzir, HTML yükləyir, mətn və
linkləri çıxarır. Google miqyasında sadə BFS deyil — politeness, deduplication,
freshness, distributed işləmə hər biri ayrı problemə çevrilir.

```
Seed URLs → Frontier → Fetcher → Parser → Storage
              ↑                    │
              └────── Extract links
```


## Niyə Vacibdir

Search engine indexing, price monitoring, AI training data toplama — hamısı web crawler-ə əsaslanır. URL frontier idarəetməsi, politeness, deduplication — large-scale distributed sistemin real nümunəsidir. Scrapy, Colly — real tool-larla bu mövzunu əlaqələndirmək vacibdir.

## Tələblər

### Funksional Tələblər (Functional)

- Milyardlarla URL-i crawl etmək (seed-dən başlayıb qraf üzrə keçid)
- HTML məzmunu yükləmək və parse etmək
- Səhifədən bütün linkləri çıxarmaq və frontier-ə əlavə etmək
- robots.txt fayllarını oxumaq və onlara hörmət etmək
- Məzmunu indexing üçün saxlamaq (raw HTML + extracted text)
- Dəyişiklikləri tutmaq üçün periodic re-crawl (freshness)
- Müxtəlif content type-larını dəstəkləmək (HTML, PDF, images metadata)

### Qeyri-Funksional Tələblər (Non-Functional)

- **Scalability** - horizontal scaling, minlərlə worker paralel işləsin
- **Politeness** - bir host-a çox sürətlə müraciət etmə (DOS etməmək)
- **Extensibility** - yeni parser-lər, yeni content type-ları əlavə etmək asan olsun
- **Robustness** - malformed HTML, timeout, 5xx error, redirect loop-lara tab gətirsin
- **Freshness** - yenilənən səhifələri tez tut, statikləri nadir re-crawl et
- **Deduplication** - eyni URL-i iki dəfə crawl etmə, eyni məzmunu iki dəfə saxlama

## Capacity Estimation

```
URL sayı:            1 milyard (1B) səhifə
Ortalama ölçü:       500 KB (HTML)
Ümumi storage:       1B × 500KB = 500 TB (gzip ~150 TB)

Crawl müddəti:       30 gün
URL/saniyə (avg):    1B / (30 × 86400) ≈ 385 URL/s
Peak (2x):           ~800 URL/s
Bandwidth:           385 × 500KB = ~192 MB/s ≈ 1.5 Gbps

Metadata per URL:    ~200 bytes → 1B × 200B = 200 GB
Content hash set:    1B × 32B (SHA-256) = 32 GB
Bloom filter:        1B × 10 bits, 1% FP ≈ 1.2 GB RAM
Worker count:        800 / (10 URL/s per worker) = 80 worker
```

## Arxitektura

```
┌──────────┐
│  Seed    │
│  URLs    │
└────┬─────┘
     │
     ▼
┌─────────────────────────────────────────────────────────┐
│              URL Frontier (Mercator-style)              │
│  ┌────────────┐                 ┌───────────────────┐   │
│  │Front Queues│──── Router ────▶│ Back Queues       │   │
│  │(priority)  │                 │ (per-host)        │   │
│  └────────────┘                 └─────────┬─────────┘   │
└─────────────────────────────────────────── │ ───────────┘
                                             ▼
                         ┌──────────────────────────┐
                         │   DNS Resolver (cached)  │
                         └────────────┬─────────────┘
                                      ▼
                         ┌──────────────────────────┐
                         │  HTML Fetcher Pool       │
                         │  (Guzzle async / pool)   │
                         └────────────┬─────────────┘
                                      ▼
                         ┌──────────────────────────┐
                         │  Content Parser          │
                         │  (DOM, text, links)      │
                         └────┬─────────────────┬───┘
                              │                 │
                              ▼                 ▼
              ┌──────────────────────┐  ┌──────────────────┐
              │ Dup Detection        │  │ URL Filter       │
              │ (simhash + SHA)      │  │ (robots, seen?)  │
              └──────────┬───────────┘  └────────┬─────────┘
                         ▼                       ▼
              ┌──────────────────────┐   ┌───────────────────┐
              │ Content Store (S3)   │   │ Back into Frontier│
              └──────────────────────┘   └───────────────────┘
```

## Əsas Anlayışlar

**1. Seed URL Set** — başlanğıc nöqtəsi. Manually curated top domains (Wikipedia, news,
DMOZ archive), sitemap.xml, user-submitted URL-lər.

**2. URL Frontier** — ən vacib komponent. Hansı URL növbəti olacağını seçir. İki məqsəd:
*Priority* (PageRank yüksək, tez dəyişən səhifələr önə) + *Politeness* (bir host-a 1s-də
1 request-dən çox yox). **Mercator dizaynı**: Front queues (priority bucket) + Back
queues (per-host). Router front-dan seçib URL-i host-un back queue-suna yerləşdirir.

**3. DNS Resolver** — crawl bottleneck-i ola bilər. In-memory cache (TTL 1 saat) + local
resolver. Per-host connection pooling DNS miss-ləri azaldır.

**4. HTML Fetcher** — HTTP client pool. Async I/O: 1 worker 100+ concurrent fetch.
Timeout 30s, max body 10MB, redirect limit 5, User-Agent məcburi.

**5. Content Parser** — HTML-dən `<a href>`, `<title>`, meta, visible text çıxarır.
Malformed HTML üçün tolerant parser (DOMDocument + libxml warnings suppress). Canonical
URL check (`<link rel="canonical">`).

**6. Duplicate Detection** — iki səviyyə: *URL-level* (normalize: lowercase host, strip
tracking params, sort query) + *Content-level* (SHA-256 exact + simhash near-dup).

**7. URL Filter** — robots.txt (Disallow), extension filter (.exe, .zip skip), Bloom
filter seen-check → confirmed disk check.

**8. Storage** — Content store: S3 + gzip HTML. URL metadata: RocksDB / Cassandra. Seen
set: Redis Bloom filter.

## Data Model

```sql
CREATE TABLE crawled_urls (
    url_hash CHAR(64) PRIMARY KEY,       -- SHA-256 of normalized URL
    url TEXT NOT NULL,
    host VARCHAR(255) NOT NULL,
    content_hash CHAR(64),               -- SHA-256 of content
    simhash BIGINT UNSIGNED,             -- 64-bit simhash
    priority TINYINT DEFAULT 5,
    last_crawled_at TIMESTAMP,
    next_crawl_at TIMESTAMP,
    change_frequency INT DEFAULT 86400,
    http_status SMALLINT,
    INDEX idx_host (host),
    INDEX idx_next_crawl (next_crawl_at),
    INDEX idx_content_hash (content_hash)
);

CREATE TABLE host_politeness (
    host VARCHAR(255) PRIMARY KEY,
    last_fetched_at TIMESTAMP,
    crawl_delay_ms INT DEFAULT 1000,     -- robots.txt Crawl-Delay
    robots_rules TEXT,                   -- cached robots.txt
    error_count INT DEFAULT 0,
    backoff_until TIMESTAMP NULL
);
```

## Alqoritmlər (Algorithms)

### Bloom Filter for URL Dedup

```
1B URL × 200B hashmap = 200 GB (çox). Bloom 1B × 10 bits = 1.2 GB, 1% FP.
In Bloom → probably seen → DB confirm.  Not in Bloom → definitely new.
```

### Simhash for Near-Duplicate

```
1. Tokenize text (shingles) → hash each → 64-bit fingerprint
2. Weighted bit sum → final 64-bit simhash
3. Two pages similar if Hamming distance < 3

"Laravel is a PHP framework" vs "Laravel is a PHP framework used..."
simhash(A) XOR simhash(B) → popcount < 3 → duplicate
```

### Adaptive Re-Crawl

```
News homepage: 1 saat.  Blog: 1 gün.  Static doc: 30 gün.
Content unchanged → next_interval *= 1.5
Content changed   → next_interval *= 0.5
```

### Consistent Hashing

```
hash(domain) % N → worker ID
Bir host hər zaman eyni worker-də → politeness, DNS cache, robots cache lokal.
Node əlavə/çıxar → yalnız 1/N URL rebalance.
```

## Trade-offs

| Qərar | Seçim A | Seçim B | Trade-off |
|-------|---------|---------|-----------|
| Frontier | Single priority queue | Mercator (front+back) | Sadə vs. politeness düzgün |
| Dedup | SHA-256 (exact) | Simhash (near-dup) | Az yaddaş vs. keyfiyyət |
| Storage | HDFS | S3 | Lokal vs. managed, cost |
| Parser | Regex | DOMDocument | Sürət vs. doğruluq |
| Fetch | Sync pool | Async (ReactPHP) | Sadə vs. 10x throughput |
| Re-crawl | Fixed interval | Adaptive | Sadə vs. freshness/cost |

## Laravel/PHP Mini Crawler (Guzzle + Queue)

### Crawler Job

```php
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CrawlUrlJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $url) {}

    public function handle(
        UrlFrontier $frontier,
        ContentStore $store,
        UrlSeenSet $seen,
        RobotsChecker $robots
    ): void {
        $url = $this->normalizeUrl($this->url);

        // Already seen?
        if ($seen->contains($url)) return;

        // robots.txt check
        if (!$robots->allowed($url)) return;

        // Politeness: per-host delay
        $host = parse_url($url, PHP_URL_HOST);
        if (!$frontier->canFetch($host)) {
            self::dispatch($url)->delay(now()->addSeconds(5));
            return;
        }

        $client = new Client(['timeout' => 30, 'allow_redirects' => ['max' => 5]]);

        try {
            $response = $client->get($url, [
                'headers' => ['User-Agent' => 'MyCrawler/1.0 (+https://mycrawler.com/bot)'],
            ]);

            $html = (string) $response->getBody();
            $contentHash = hash('sha256', $html);

            // Content-level dedup
            if ($store->hashExists($contentHash)) {
                $seen->add($url);
                $frontier->markFetched($host);
                return;
            }

            // Store raw HTML
            $store->put($url, $html, $contentHash);
            $seen->add($url);
            $frontier->markFetched($host);

            // Extract and enqueue links
            $links = $this->extractLinks($html, $url);
            foreach ($links as $link) {
                if (!$seen->contains($link)) {
                    self::dispatch($link)->onQueue('crawler');
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Crawl failed: {$url} — {$e->getMessage()}");
            $frontier->recordError($host);
        }
    }

    private function extractLinks(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $links = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = $a->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#')) continue;
            $absolute = str_starts_with($href, 'http')
                ? $href
                : rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
            if (filter_var($absolute, FILTER_VALIDATE_URL)) $links[] = $absolute;
        }
        return array_unique($links);
    }

    private function normalizeUrl(string $url): string
    {
        $p = parse_url(strtolower($url));
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . ($p['path'] ?? '/');
    }
}
```

### URL Seen Set (Redis Bloom)

```php
class UrlSeenSet
{
    public function __construct(private \Redis $redis) {}

    public function add(string $url): void
    {
        // Redis Bloom module: BF.ADD
        $this->redis->rawCommand('BF.ADD', 'crawler:seen', $url);
    }

    public function contains(string $url): bool
    {
        return (bool) $this->redis->rawCommand('BF.EXISTS', 'crawler:seen', $url);
    }
}
```

### Frontier Politeness

```php
class UrlFrontier
{
    private const MIN_DELAY_MS = 1000;

    public function __construct(private \Redis $redis) {}

    public function canFetch(string $host): bool
    {
        $last = (float) $this->redis->get("crawler:host:{$host}:last");
        return !$last || (microtime(true) * 1000 - $last) >= self::MIN_DELAY_MS;
    }

    public function markFetched(string $host): void
    {
        $this->redis->set("crawler:host:{$host}:last", microtime(true) * 1000);
    }

    public function recordError(string $host): void
    {
        $count = $this->redis->incr("crawler:host:{$host}:errors");
        if ($count > 5) {
            $this->redis->set("crawler:host:{$host}:backoff", time() + min(3600, 60 * $count));
        }
    }
}
```

### Async Pool (Mass Fetch)

```php
public function crawlBatch(array $urls): void
{
    $client = new Client();
    $requests = fn() => array_map(fn($u) => new Request('GET', $u), $urls);
    $pool = new Pool($client, $requests(), [
        'concurrency' => 50,
        'fulfilled' => fn($res, $i) => ProcessCrawledPage::dispatch($urls[$i], (string) $res->getBody()),
        'rejected' => fn($reason, $i) => Log::warning("Failed: {$urls[$i]}"),
    ]);
    $pool->promise()->wait();
}
```

## Trap və Adversarial Hallar (Crawler Traps)

- **Calendar traps** (`/calendar/2099/jan/01` kimi sonsuz URL) → depth limit 20, pattern detection
- **Session IDs** (`?sessionid=abc123` hər ziyarətdə dəyişir) → normalize-də `utm_*`, `sessionid` strip
- **Spider traps** (intentional infinite loop) → per-host URL limit (max 100k per domain)
- **Honeypots** (gizli bot-detect link-lər) → robots.txt, `rel=nofollow`, CSS `display:none` atla

## Praktik Tapşırıqlar

**S1: URL frontier niyə priority + politeness ikisini birləşdirməlidir?**
C: Tək priority queue news-portal-ı 1000 dəfə ardıcıl fetch edər, DOS olar. Tək
politeness queue isə vacib URL-ləri yavaş crawl edər. Mercator: front queue-lar
priority, back queue-lar per-host politeness.

**S2: Bloom filter niyə bu problem üçün idealdır?**
C: 1B URL hash map ilə ~200GB RAM. Bloom 1.2GB-a sığır, 1% FP qəbul olunandır —
FP halında səhifə crawl olunmur (az itki), FN yoxdur.

**S3: Simhash nə üçün SHA-dən yaxşıdır?**
C: SHA yalnız exact match tapır. "foo.com/page" və "foo.com/page?ref=twitter" eyni
məzmun, fərqli byte → SHA miss. Simhash Hamming distance < 3 near-dup göstərir.

**S4: Distributed crawler-də consistent hashing nə üçün lazımdır?**
C: Bir domain həmişə eyni worker-də olmalıdır ki, politeness delay, robots.txt və
DNS cache lokal qala. Random shard → eyni host paralel vurulur, ban risk.

**S5: JavaScript-rendered SPA-ları necə crawl edirsən?**
C: Default fetcher JS icra etmir. Headless Chrome (Puppeteer, Playwright) lazımdır,
amma 10-50x bahadır — yalnız "needs JS" flag-lı URL-lər üçün istifadə olunur.

**S6: Freshness üçün adaptive re-crawl necə işləyir?**
C: Hər URL üçün change_frequency saxla. Content dəyişmədisə interval 1.5x artır,
dəyişdisə 0.5x azalt. News 1 saat, static doc 30 gün — bandwidth optimize olunur.

**S7: robots.txt-ə hörmət etməsən nə olar?**
C: 1) IP ban, Cloudflare challenge, 2) hüquqi risk (ToS violation), 3) User-Agent
blacklist, 4) honeypot-a düşmək. Həmişə robots.txt + Crawl-Delay.

**S8: Storage 500TB-dir — necə cost-optimize edərsən?**
C: 1) gzip (3-5x azalma), 2) yalnız extracted text saxla, raw HTML 90 gündən sonra
sil, 3) S3 Intelligent-Tiering (nadir → Glacier), 4) content dedup (eyni məzmun bir
dəfə, URL-lər content hash-a reference).

## Praktik Baxış

1. **User-Agent açıq yaz** — "MyCrawler/1.0 (+contact-url)"
2. **robots.txt həmişə** — fetch-dən əvvəl yoxla, Crawl-Delay-ə əməl et
3. **Async fetching** — Guzzle Pool / ReactPHP, 10-50x throughput
4. **Bloom filter + disk check** — RAM optimizasiyası, FP-ni DB ilə təsdiq
5. **Exponential back-off** — 5xx error-larda host-a fasilə ver
6. **URL normalization** — lowercase host, strip fragments, sort query params
7. **Content-level dedup** — simhash near-duplicate-lər
8. **Consistent hashing** — domain → worker mapping stabil
9. **Depth limit** — calendar və spider trap-dan qoruyur
10. **Monitoring** — pages/sec, error rate, queue depth, unique hosts


## Əlaqəli Mövzular

- [Probabilistic Data Structures](33-probabilistic-data-structures.md) — URL Bloom filter
- [Message Queues](05-message-queues.md) — URL frontier queue
- [Document Search](76-document-search-design.md) — crawl edilmiş kontentin indexlənməsi
- [Caching](03-caching-strategies.md) — robots.txt, crawl delay cache
- [Distributed Systems](25-distributed-systems.md) — distributed crawler koordinasiyası
