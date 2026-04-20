# CDN & Edge Caching

## Mündəricat
1. [CDN nədir?](#cdn-nədir)
2. [Edge caching](#edge-caching)
3. [Cache key strategy](#cache-key-strategy)
4. [Invalidation](#invalidation)
5. [Stale-while-revalidate](#stale-while-revalidate)
6. [Cloudflare, Fastly, CloudFront](#cloudflare-fastly-cloudfront)
7. [Edge compute (Workers, Lambda@Edge)](#edge-compute-workers-lambdaedge)
8. [API caching at edge](#api-caching-at-edge)
9. [Image optimization](#image-optimization)
10. [Security (WAF, DDoS)](#security-waf-ddos)
11. [PHP app integration](#php-app-integration)
12. [İntervyu Sualları](#intervyu-sualları)

---

## CDN nədir?

```
CDN (Content Delivery Network) — global coğrafi edge server şəbəkəsi.
Məqsəd: content-i user-ə ən yaxın server-dən təqdim et.

Niyə?
  - Latency (user NY-də, server London-da → 120ms RTT)
  - Bandwidth (origin server load azalır)
  - Availability (multiple PoP — origin çökürsə edge cache cavab verir)
  - DDoS protection (traffic distributed)

Struktur:
                      ┌────── Edge (Frankfurt) ──── EU users
                      │
  Origin (London) ────┼────── Edge (Singapore) ─── Asia users
                      │
                      └────── Edge (Virginia) ──── US East users

İlk request (cache miss):
  User → Edge → Origin → Edge (cache) → User
  Sonrakı request-lər yalnız Edge-dən (cache hit, origin toxunulmur)
```

---

## Edge caching

```
HTTP caching əsasında:
  Cache-Control: max-age=3600    → 1 saat edge cache
  Cache-Control: public          → CDN cache edə bilər
  Cache-Control: private         → yalnız browser cache, CDN YOX
  Cache-Control: no-store        → heç yerdə cache yox
  Cache-Control: s-maxage=86400  → CDN üçün 1 gün, browser üçün ayrı

Hierarchy:
  1. Browser cache (user-ın öz cache-i)
  2. CDN edge (cache hit — fast)
  3. CDN shield (region cache — intermediate)
  4. Origin server (cache miss)

ETag / Last-Modified:
  Conditional request:
    If-None-Match: "etag123"   → server 304 Not Modified qaytarır (body yox)
  
  Edge bu yoxlamanı origin-a göndərir (validation)
  Revalidation — kiçik RTT, lakin məzmun böyükdürsə serverə gedir

Vary header:
  Vary: Accept-Language    → hər dil üçün ayrı cache
  Vary: Accept-Encoding    → gzip/br-ya görə ayrı cache
  Vary: Cookie             → hər cookie üçün ayrı (TƏHLÜKƏLİ — cache fragmentation)
```

---

## Cache key strategy

```
Cache key — edge hansı request-i eyni sayırsa.

Default cache key:
  URL + query string + hostname

Problem: cookies, headers — user-specific:
  Cookie: session=abc123 → hər user üçün ayrı cache
  → cache ratio aşağı düşür

Həll:
  1. Public content (blog post, product page):
     Cookie atılır, hamı eyni cache-dən oxuyur
  
  2. Private content (dashboard):
     Cache yox, HƏMİŞƏ origin-a get
     Cache-Control: private, no-cache
  
  3. Varied content (per language, per device):
     Vary: Accept-Language
     edge fərqli cache version saxlayır

Cloudflare "Cache Rules":
  URL pattern + cache TTL + Edge TTL
  /api/products/*  → 5 min
  /images/*         → 30 gün
  /admin/*          → NO CACHE
```

---

## Invalidation

```
Cache invalidation — cache-dəki köhnə content-i təmizləmək.
"There are only two hard things in CS: cache invalidation and naming things."

Strategiyalar:

1. TTL (Time-To-Live) based:
   Content-in cache müddəti bitəndə avtomatik silinir.
   Sadə, amma "stale content" kimi müddəti.
   
   Cache-Control: max-age=3600   # 1 saat sonra expire

2. Purge (manual):
   API çağırışı ilə cache-i silmək.
   
   # Cloudflare API
   curl -X POST https://api.cloudflare.com/client/v4/zones/{id}/purge_cache \
     -H "Authorization: Bearer $token" \
     -d '{"files":["https://example.com/page.html"]}'
   
   # Propagation 30s-2min çəkir (bütün edge-lər üzrə)

3. Soft Purge (stale-while-revalidate):
   Cache "stale" olaraq işarələnir, amma silinmir.
   User-ə stale content verilir, arxada origin-dan refresh.

4. Surrogate-Key / Cache Tag (Fastly, Cloudflare):
   Hər cache entry "tag"-lı:
     Surrogate-Key: product-42 category-phones
   Tag-a görə bulk invalidation:
     curl -X POST ... -H "Surrogate-Key: product-42"
   
   Use case: "Product 42 dəyişdi → cache-dəki bütün product-42 referansları sil"

5. Cache Versioning:
   URL-də versiya: /images/logo.v2.png
   Yeni versiya — yeni URL, köhnə URL köhnə cache-dən gəlir.
   Long TTL (1 il) mümkün, dəyişəndə URL update et.
```

---

## Stale-while-revalidate

```
Cache-Control: max-age=60, stale-while-revalidate=3600

  0s-60s:      Fresh — cache-dən verilir
  60s-3660s:   Stale — User-ə verilir AMMA arxa planda origin refresh
  3660s+:      Expired — origin-a gedir

User-ə fayda: hər zaman sürətli cavab (hətta stale olsa da)
Origin-a fayda: sinxron request azalır, async refresh

stale-if-error:
  Origin çökürsə → stale content verilsin (availability)
  Cache-Control: max-age=60, stale-if-error=86400

Bunlar user experience üçün game-changer:
  ✓ Zero user-facing latency during revalidation
  ✓ Origin outage protection
  ✓ Smooth traffic spikes
```

---

## Cloudflare, Fastly, CloudFront

```
Provider       | Güclü tərəfləri                         | Use case
──────────────────────────────────────────────────────────────────────
Cloudflare     | Free tier, DNS, Workers, WAF, DDoS      | General-purpose
Fastly         | VCL config, instant purge (150ms)       | Media, ecommerce
AWS CloudFront | Tight AWS integration, Lambda@Edge      | AWS-heavy stack
Akamai         | Enterprise, tight SLA                   | Large enterprise
Google Cloud CDN | Anycast global, tight GCP integration | GCP workloads
Bunny.net      | Cheap, simple                           | Indie developers
KeyCDN         | Small/mid, simple                       | Budget-conscious

Cloudflare tipik setup:
  1. DNS Cloudflare-dən idarə olunur (nameserver dəyişir)
  2. Proxy ON (narıncı bulud ikonu) — trafik CF-dən keçir
  3. Cache Rules + Page Rules
  4. SSL (Let's Encrypt auto)
  5. WAF / Bot management
  6. Workers (edge compute)
```

---

## Edge compute (Workers, Lambda@Edge)

```
Edge function — CDN edge-də kod işlətmək.
Use case: A/B test, rewrite, auth, personalization.

Cloudflare Workers (V8 isolates, ~5ms cold start):
  - JavaScript/TypeScript
  - Global deployment (200+ city)
  - Free tier: 100k req/day

Lambda@Edge (AWS):
  - Node.js/Python
  - CloudFront trigger (viewer request, origin request, etc.)
  - Slower cold start (ilk invocation)

Use case nümunələri:

1. A/B test routing:
   Request-in bir hissəsini v2-yə yönəlt (origin tərəfdə code yox)

2. Auth check (JWT validate):
   Token-i edge-də yoxla, invalid → 401 (origin toxunmur)

3. Image transformation:
   /image.jpg?w=300&h=200 → edge-də resize

4. Header manipulation:
   CORS, security headers — origin-dan asılı olmadan

5. Personalization:
   User country ISP-dən → fərqli homepage
```

```javascript
// Cloudflare Worker nümunəsi
export default {
    async fetch(request, env, ctx) {
        const url = new URL(request.url);
        
        // Country-based routing
        const country = request.cf.country;
        if (country === 'AZ') {
            return Response.redirect('https://az.example.com', 302);
        }
        
        // Auth check
        const token = request.headers.get('Authorization');
        if (!token || !await validateJWT(token, env.JWT_SECRET)) {
            return new Response('Unauthorized', { status: 401 });
        }
        
        // Fetch from origin
        const response = await fetch(request);
        
        // Add security headers
        const newResponse = new Response(response.body, response);
        newResponse.headers.set('X-Frame-Options', 'DENY');
        newResponse.headers.set('Strict-Transport-Security', 'max-age=31536000');
        
        return newResponse;
    }
};
```

---

## API caching at edge

```
API endpoint-lərini CDN-də cache etmək qeyri-adi amma güclüdür.

Public read-only API:
  GET /api/products — public catalog, 5 min cache OK
  GET /api/categories — rarely changes, 1 saat cache OK

Best practices:
  ✓ Include authorization header in cache key (per-user cache)
     YALNIZ: public endpoints (auth header atılır)
  ✓ Vary: Authorization (istifadəçiyə görə ayrı cache)
  ✓ Cache-Control: public, max-age=300, s-maxage=3600
  ✓ ETag ilə conditional request (304 Not Modified)
  
  ✗ POST/PUT/DELETE — cache etmə (side effect)
  ✗ Authenticated data — default olaraq public cache etmə
```

```php
<?php
// Laravel response cache headers
Route::get('/products', function () {
    $products = Product::all();
    
    return response()->json($products)
        ->header('Cache-Control', 'public, max-age=60, s-maxage=300, stale-while-revalidate=600')
        ->header('Vary', 'Accept-Encoding')
        ->setLastModified(Product::max('updated_at'))
        ->setEtag(md5($products->toJson()));
});

// Conditional response (ETag match)
Route::get('/products', function (Request $req) {
    $products = Product::all();
    $etag = md5($products->toJson());
    
    if ($req->header('If-None-Match') === $etag) {
        return response()->noContent(304);   // Not Modified
    }
    
    return response()->json($products)
        ->setEtag($etag)
        ->header('Cache-Control', 'public, max-age=60');
});
```

---

## Image optimization

```
CDN-lər image optimization avtomatlaşdırır:
  - Format dəyişmə (JPEG → WebP → AVIF)
  - Resize (responsive images)
  - Quality adjust
  - Lazy loading support

Cloudflare Images / Polish:
  /cdn-cgi/image/width=300,quality=80,format=auto/https://origin.com/img.jpg

Imgix, Cloudinary — specialized image CDN:
  /image.jpg?w=300&h=200&fit=crop&auto=format,compress

Savings:
  Original JPEG:  1.2 MB
  WebP 80%:       340 KB (-72%)
  AVIF 80%:       210 KB (-82%)

Browser Accept header:
  Accept: image/avif,image/webp,image/png
  CDN best format seçir, ayrı cache saxlayır (Vary: Accept)
```

---

## Security (WAF, DDoS)

```
CDN → təhlükəsizlik qatı:

1. WAF (Web Application Firewall)
   SQL injection, XSS, RCE qarşısı
   OWASP Top 10 rule set
   Cloudflare: Managed Rules, Custom Rules

2. DDoS protection
   Layer 3/4: Anycast şəbəkə volume-u absorbs
   Layer 7: rate limiting, bot detection
   Cloudflare "Under Attack Mode" — JavaScript challenge

3. Bot management
   Scraper, credential stuffing detect
   JA3 fingerprint, mouse movement

4. Rate limiting
   IP, API token, URL path əsasında
   10 req/s, 100 req/min threshold

5. TLS / SSL
   Free Let's Encrypt auto
   TLS 1.3 enforce
   Cert pinning, HSTS

6. Origin shielding
   Origin IP gizlədilir (CDN arxasında)
   Direct origin attack qarşısı

7. Geo-blocking
   Ölkə səviyyəsində blok
   Compliance (GDPR, embargoed countries)
```

---

## PHP app integration

```php
<?php
// Middleware — cache hint-lər
class CacheControlMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        // Auth edilmiş user response-larını private işarələ
        if (auth()->check()) {
            $response->header('Cache-Control', 'private, no-cache');
            return $response;
        }
        
        // Public resource-ları cacheable
        if ($request->isMethod('GET') && $response->getStatusCode() === 200) {
            $response->header('Cache-Control', 'public, max-age=300, s-maxage=3600');
        }
        
        return $response;
    }
}

// Surrogate-Key ilə granular invalidation (Fastly)
class SurrogateKeyMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        $keys = [];
        if ($request->is('products/*')) {
            $productId = $request->route('id');
            $keys[] = "product:$productId";
            $keys[] = "category:" . Product::find($productId)?->category_id;
        }
        
        if (!empty($keys)) {
            $response->header('Surrogate-Key', implode(' ', $keys));
        }
        
        return $response;
    }
}

// Purge on update
class Product extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Product $product) {
            app(CdnService::class)->purge([
                "product:{$product->id}",
                "category:{$product->category_id}",
            ]);
        });
    }
}

class CdnService
{
    public function purge(array $keys): void
    {
        // Fastly API
        foreach ($keys as $key) {
            Http::withHeaders(['Fastly-Key' => config('fastly.key')])
                ->post("https://api.fastly.com/service/{$serviceId}/purge/$key");
        }
    }
}
```

---

## İntervyu Sualları

- CDN-in əsas 3 faydası nədir?
- `max-age` və `s-maxage` arasında fərq nədir?
- `stale-while-revalidate` necə işləyir?
- Vary header nəyə xidmət edir? Nə vaxt təhlükəlidir?
- Cache invalidation strategiyaları nələrdir?
- Surrogate-Key / Cache Tag niyə güclüdür?
- Authenticated content CDN-də necə cache edilir?
- Cloudflare Worker nə üçün lazımdır? Lambda@Edge-dən fərqi?
- Image CDN-də AVIF və WebP format selection necə olur?
- DDoS protection CDN səviyyəsində necə işləyir?
- Origin IP niyə gizlənməlidir?
- PHP app-dən CDN cache purge necə edilir?
