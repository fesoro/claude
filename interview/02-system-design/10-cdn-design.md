# CDN Design (Lead ⭐⭐⭐⭐)

## İcmal
Content Delivery Network (CDN), statik və dinamik kontenti istifadəçilərə coğrafi baxımdan yaxın edge server-lərdən çatdırmaq üçün qlobal şəbəkədir. CDN yalnız şəkilləri sürətli vermək üçün deyil — DDoS mitigation, SSL termination, edge computing, API caching kimi funksiyaları da yerinə yetirir. Interview-larda CDN dizaynı media-heavy sistem (YouTube, Netflix, Instagram) suallarında mütləq gəlir.

## Niyə Vacibdir
Netflix trafikin 37%-ni Amazon Prime Video-dan daha az latency ilə çatdırır, çünki OpenConnect CDN-i dünyaya yayılmışdır. Cloudflare, Akamai, Fastly — hər böyük CDN provider öz arxitekturasını fərqliləşdirir. Lead mühəndis CDN-in yalnız "static files üçündür" olmadığını, edge caching, origin pull, cache invalidation, anycast DNS mexanizmlərini izah edə bilir.

## Əsas Anlayışlar

### 1. CDN Necə İşləyir

**Origin Pull (Lazy Loading)**
```
İlk request:
  User (Baku) → CDN Edge (Frankfurt) → miss → Origin (US-East)
  Origin → Content → CDN Edge caches → User
  Latency: 200ms (origin-ə gedib gəldi)

Sonrakı request:
  User (Baku) → CDN Edge (Frankfurt) → hit → User
  Latency: 10ms (edge-dən birbaşa)
```

**Origin Push (Proactive)**
```
Content hazırlandıqda → bütün edge-lərə push et
  - Video yükləndi → bütün 200 PoP-a göndər
  - Pros: İlk request belə fast
  - Cons: Storage baha, az populyar content waste
  - Use case: Software releases, major content launches
```

### 2. CDN Komponentləri

**Point of Presence (PoP)**
- Hər şəhərdə/regionda edge datacenter
- Cloudflare: 300+ PoP, 200+ ülkə
- Akamai: 4000+ PoP
- PoP: multiple edge servers + local storage

**Edge Server**
- User sorğusunu birbaşa alan server
- Cache: SSD/NVMe, yüzlərlə GB
- L4/L7 termination
- DDoS scrubbing

**Anycast Routing**
```
CDN IP: 104.16.0.0 (hər PoP eyni IP-ni elan edir)
User's DNS: router-lər ən yaxın PoP-u seçir
Frankfurt PoP: 104.16.0.0
Tokyo PoP: 104.16.0.0
New York PoP: 104.16.0.0

User Istanbul → BGP route → Frankfurt PoP
User Seoul → BGP route → Tokyo PoP
```

### 3. Cache-Control Headers
```http
# Static assets (1 il cache)
Cache-Control: public, max-age=31536000, immutable

# API responses (5 dəq cache)
Cache-Control: public, max-age=300, stale-while-revalidate=60

# User-specific (cache etmə)
Cache-Control: private, no-store

# HTML (1 dəq, stale ok)
Cache-Control: public, max-age=60, stale-while-revalidate=300

# Conditional caching
ETag: "abc123"
Last-Modified: Wed, 26 Apr 2026 10:00:00 GMT
```

**stale-while-revalidate:**
- Cache expire olub, amma köhnə versiyası var
- Köhnəni qaytar, arxa planda yenilə
- User latency görmür

### 4. Cache Invalidation / Purge
```
Yanlış yanaşma: Cache expire-i gözlə (max-age bitsin)
→ Update olunmuş content saatlarla köhnə versiyada qalır

Düzgün yanaşma:
1. Explicit Purge:
   POST https://api.cloudflare.com/purge
   {"files": ["https://example.com/image.jpg"]}

2. Versioned URLs (Immutable caching):
   image.jpg?v=1.0.5  → image.jpg?v=1.0.6
   Old URL expire olana kimi keçərlidir
   New URL ilk dəfə origin-dən gəlir
   → Deployment ilə versiyalar dəyişir (content hash)

3. Tag-based purge:
   Hər asset-ə tag: Cache-Tag: product-123
   Product yeniləndikdə: purge all with tag product-123
```

### 5. CDN Cache Key
Default: URL + query string
Customizable: Header, cookie daxil/xaric etmək

```
Problem: Eyni URL, fərqli language:
GET /api/products → Accept-Language: az
GET /api/products → Accept-Language: en
→ Default: eyni cache key → yanlış content!

Həll: Vary header:
Vary: Accept-Language
→ CDN hər dil üçün ayrı cache saxlayır

Problem: Query string:
/search?q=phone&sort=price&page=1&utm_source=google
→ utm_source cache key-ə daxildir → cache inefficient

Həll: CDN-də query string normalizing:
utm_* parametrlərini cache key-dən xaric et
```

### 6. Dynamic Content Caching
CDN yalnız static deyil, dynamic content-i də cache edə bilir:

```
API response caching:
GET /api/v1/products/popular
Cache-Control: public, max-age=300
→ CDN 5 dəq caches → 1000 user eyni response alır

Fastly VCL (Varnish Configuration Language):
sub vcl_fetch {
    if (req.url ~ "^/api/public/") {
        set beresp.ttl = 300s;
    }
}

Pros: Origin-ə geden sorğu 99% azalır
Cons: Data stale ola bilər (TTL bitənə kimi)
```

### 7. Edge Computing
Modern CDN-lər computation-u edge-ə aparır:

**Cloudflare Workers:**
```javascript
// Edge-də çalışan kod (PoP serverında)
addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
    // A/B testing at edge
    // Auth token validation at edge
    // Request transformation
    // Geo-based routing
}
```

**Use cases:**
- A/B testing (user-i edge-də ayır)
- Authentication (JWT validation at edge)
- Geo-based content (dil, para vahidi)
- Image optimization (resize, format conversion)
- Rate limiting at edge (DDoS protection)

### 8. Video Streaming CDN
```
Video upload → Transcoding (480p, 720p, 1080p, 4K)
             → S3 (origin storage)
             → CDN

Streaming protokolları:
- HLS (HTTP Live Streaming): Chunks (2-10s), M3U8 playlist
- DASH (Dynamic Adaptive Streaming): Adaptive bitrate
- CMAF: Yeni standard, HLS+DASH combined

CDN-də:
- Manifest file (.m3u8): short TTL (5-10s)
- Video segments (.ts): long TTL (24h+)
- Adaptive bitrate: CDN client bandwidth-ına görə keyfiyyəti dəyişir
```

### 9. CDN Architecture for Large Platform
```
Global Platform CDN:
                            ┌─ [PoP: US-West]
[Origin: US-East] ──────── ┼─ [PoP: US-East]
(S3 + App servers)          ├─ [PoP: EU-Frankfurt]
                            ├─ [PoP: EU-London]
                            ├─ [PoP: APAC-Singapore]
                            ├─ [PoP: APAC-Tokyo]
                            └─ [PoP: ME-Dubai]

Regional Origin Shield:
  PoP-lar cache miss olduqda origin-ə getmir
  → Regional "shield" cache-ə gedir (1 hop)
  → Origin load azalır (shield 95% absorb edir)
```

### 10. CDN Security
- **DDoS mitigation**: L3/L4 volumetric attacks, L7 application attacks
- **WAF (Web Application Firewall)**: SQL injection, XSS, OWASP Top 10
- **Bot management**: CAPTCHA, JavaScript challenge, fingerprinting
- **TLS**: SNI-based virtual hosting, TLS 1.3, HTTP/2, HTTP/3 (QUIC)
- **Certificate management**: Auto-renewal, wildcard certs
- **Geo-blocking**: IP-to-country database, block/allow by country

## Praktik Baxış

### Interview-da Necə Yanaşmaq
1. "CDN əlavə edirəm" deyəndə static deyil, trafik növünü izah et
2. Cache-Control strategiyasını müzakirə et
3. Cache invalidation üçün versioned URLs istifadəsini qeyd et
4. Origin Shield-i izah et (CDN-in özü origin-ə cache layer)
5. Edge computing imkanlarına toxun

### Ümumi Namizəd Səhvləri
- CDN-i yalnız "static files üçün" hesab etmək
- Cache invalidation problemini nəzərə almamaq
- Vary header-i unutmaq (language, device-based caching)
- Origin Shield-i bilməmək
- CDN cache key customization-ı qeyd etməmək

### Senior vs Architect Fərqi
**Senior**: CDN konfigurasiya edir, cache-control headers düzəldir, purge strategy müəyyən edir.

**Architect**: Multi-CDN strategy (Cloudflare + Fastly, failover), cost optimization (cache hit ratio vs storage), edge computing arxitekturası, CDN-in network topology üzərindəki təsiri, CDN provider seçimi (latency SLA, price, features), CDN analytics ilə business decision-lar.

## Nümunələr

### Tipik Interview Sualı
"Design the CDN and media delivery layer for a platform like YouTube serving 1B users globally."

### Güclü Cavab
```
YouTube-like media CDN:

Scale:
- 1B users, 80% outside US
- 500K video uploads/day
- 1B video views/day = 12K view/sec
- Average video: 100MB (multiple qualities)

Upload Flow:
Client → Upload Service → S3 (raw)
→ Transcoding Service (FFmpeg cluster)
→ Multiple qualities: 360p, 480p, 720p, 1080p, 4K
→ HLS segments (.ts, 4-6 sec each)
→ S3 (processed) → CDN Origin

CDN Layer (Netflix OpenConnect-style):
- Self-hosted CDN for cost control at scale
- OR: Multi-CDN: Cloudflare + Akamai + Fastly
  (failover, geo-specific performance)

PoP Strategy:
- Major cities: Full PoP (large SSD cache)
- Small cities: Micro PoP (smaller cache, faster failover)

Cache Strategy:
- Popular videos (top 1%): Proactively pushed to all PoPs
- Long-tail videos: Origin pull on demand
- Manifest files: TTL 10 seconds (live streaming)
- Video segments: TTL 24 hours
- Video thumbnails: TTL 7 days, immutable versioning

Adaptive Bitrate:
- Client reports bandwidth
- CDN serves appropriate quality segment
- DASH manifest lists all quality URLs
- Seamless quality switch without rebuffering

Origin Shield:
- 3 regional origin shields (US, EU, APAC)
- PoP → Regional shield → Origin
- Origin handles <5% of total traffic
- 99B / 5% = 50M direct origin hits still heavy, but manageable

DDoS:
- Anycast absorption: attack distributed across 300 PoPs
- Cloudflare Magic Transit for volumetric
- Rate limiting at edge
```

### CDN Flow Diaqramı
```
[User - Tokyo]
      │
      ▼ DNS Anycast → Tokyo PoP
[Tokyo CDN PoP]
  Cache hit? → serve
  Cache miss? ↓
[APAC Origin Shield]  (Singapore)
  Cache hit? → serve + cache Tokyo PoP
  Cache miss? ↓
[Origin: S3 + App] (US-East)
  Fetch → cache Shield → cache PoP → serve user
```

## Praktik Tapşırıqlar
- Nginx-i CDN kimi konfigurasiya edin (proxy_cache)
- Cloudflare free tier ilə domain qurun, cache analytics izləyin
- Cache-Control headers-i S3 static site üçün optimallaşdırın
- Versioned URL deployment skripti yazın (content hash in filename)
- Origin Shield konseptini Varnish ilə test edin

## Əlaqəli Mövzular
- [05-caching-strategies.md] — Caching patterns
- [04-load-balancing.md] — Global load balancing
- [03-scalability-fundamentals.md] — Scale fundamentals
- [09-rate-limiting.md] — Edge rate limiting
- [20-monitoring-observability.md] — CDN metrics
