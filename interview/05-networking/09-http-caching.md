# HTTP Caching (ETags, Cache-Control) (Senior ⭐⭐⭐)

## İcmal

HTTP caching — server-ə sorğu göndərmədən və ya minimal sorğu ilə öncəki response-u təkrar istifadə etmə mexanizmidir. Düzgün konfiqurasiya edilmiş HTTP caching backend yükünü dramatik şəkildə azaldır, latency-ni aşağı salır, bandwidth qənaəti edir. Yanlış konfigurasiya isə stale data göstərməyə, ya da cache olunmamalı olan məlumatların leakage-ına səbəb olur. Senior developer kimi HTTP cache header-larını detallarla bilmək production sistemlərinin performansı üçün vacibdir.

## Niyə Vacibdir

HTTP caching — CDN-dən browser cache-ə qədər web stack-in hər səviyyəsini əhatə edir. Interviewer bu mövzunu soruşduqda yoxlayır: "Cache-Control direktiflərini bilirsizmi? ETag-ın conditional request-dən fərqi nədir? Cache invalidation-ı necə idarə edirsiniz?" Bu bilgi CDN konfiqurasiyası, API performance optimization, mobile app data sync arxitekturalarında birbaşa istifadə olunur. Yanlış `private` vs `public` seçimi user datanı CDN-də expose edə bilər — security implication var.

## Əsas Anlayışlar

### Cache-Control Header Direktivləri:
- **`max-age=3600`**: Resource 3600 saniyə (1 saat) fresh sayılır. Bu müddət keçəndən sonra "stale" olur — server-dən yenidən yoxlanmalıdır.
- **`s-maxage=86400`**: Shared cache-lər (CDN, proxy) üçün ayrı TTL — browser cache-ə tətbiq olunmur. `max-age`-dən üstün tutulur. E-commerce: CDN-də 24 saat, browser-də 5 dəqiqə.
- **`no-cache`**: Cache-ə al, amma hər istifadədən öncə server-dən validation al (conditional request — ETag/If-None-Match). Content dəyişməyibsə 304 Not Modified qaytarır. **Cache edir, amma hər dəfə yoxlayır.** Adı yanıltıcıdır!
- **`no-store`**: Heç cache-ə alma — sensitive data (banking, personal info, health records) üçün. Disk-ə, RAM-a, heç yerə yazılmır.
- **`public`**: İstənilən cache (browser, CDN, proxy, shared cache) saxlaya bilər. Bütün user-lər üçün eyni response olduqda istifadə et.
- **`private`**: Yalnız browser cache saxlaya bilər — CDN-də saxlanmamalı (user-specific data). Auth cookie, user profile.
- **`must-revalidate`**: TTL bitdikdə mütləq server-dən yoxla, stale istifadə etmə — hətta server offline olsa belə (503 qaytarır). `stale-while-revalidate`-in əksi.
- **`stale-while-revalidate=60`**: TTL bitəndən sonra 60s ərzində köhnə data göstər, arxa planda yenilə. User sıfır latency görür, background-da yeni data yüklənir. E-commerce product list üçün ideal.
- **`stale-if-error=3600`**: Server error verəndə (5xx) 1 saat ərzində köhnə data göstər. Availability-ni artırır — server down olanda da content göstərilir.
- **`immutable`**: Resource heç vaxt dəyişməyəcək — TTL bitməzdən əvvəl server-i sorğulamaq lazım deyil. Content-hash fingerprinted assets üçün (`app.a3f2c.css`). Browser fresh yoxlaması etmir.
- **`no-transform`**: Proxy-lər, CDN-lər content-i compress ya da format dəyişdirməsin.

### Conditional Requests (Şərti Sorğular):

**Last-Modified + If-Modified-Since**:
- Server response-da `Last-Modified: Wed, 21 Oct 2015 07:28:00 GMT` göndərir.
- Client növbəti sorğuda `If-Modified-Since: Wed, 21 Oct 2015 07:28:00 GMT` əlavə edir.
- Server dəyişməyibsə `304 Not Modified` qaytarır — body göndərilmir, bandwidth sıfır.
- Zəiflik: 1 saniyə daxilindəki dəyişiklikləri tutmur (timestamp precision). Birdən çox server olduqda timestamp sync problemi.

**ETag + If-None-Match**:
- Server resource üçün unikal identifier göndərir: `ETag: "33a64df551425fcc55e4d42a148795d9f25f89d4"` — məzmunun hash-i.
- Client: `If-None-Match: "33a64df551425fcc55e4d42a148795d9f25f89d4"`. Server dəyişməyibsə 304.
- Strong ETag: `"abc123"` — byte-by-byte eyni olmalıdır.
- Weak ETag: `W/"abc123"` — semantik olaraq eyni, amma byte fərqli ola bilər (timestamps, whitespace).
- ETag vs Last-Modified: ETag daha dəqiq (sub-second dəyişiklikləri tutur), amma hesablamaq xərci var.

**Conditional PUT/DELETE — Optimistic Locking**:
- `If-Match: "etag-value"` — yalnız ETag uyğunsa yenilə. Konflikt olduqda 412 Precondition Failed.
- Lost update problem-in HTTP-levelda həlli. Database row locking-ə alternativ.

### Cache Hierarchy (Katmanlar):
```
Browser Cache (private, fastest)
    ↑
Service Worker Cache (optional, offline support)
    ↑
CDN Edge Cache (public, geographic distribution)
    ↑
Reverse Proxy Cache / Varnish (datacenter, shared)
    ↑
Application Cache / Redis (business logic layer)
    ↑
Database Query Cache (SQL level)
```
Hər katmanın fərqli TTL, invalidation strategiyası, scope-u var.

### Cache Invalidation — "The Hardest Problem":
Phil Karlton: *"There are only two hard things in Computer Science: cache invalidation and naming things."*

- **URL-based invalidation**: URL dəyişdikdə cache özü köhnəlir. Content-hash fingerprinting bu prinsipə əsaslanır.
- **Tag-based invalidation** (Cloudflare Surrogate-Key, Fastly): `Cache-Tag: product-123,category-electronics`. Məhsul yenilənəndə tagi ilə bütün related cache-ləri purge et.
- **Manual purge**: CDN API-sına purge call. Cloudflare: `POST /zones/{zone_id}/purge_cache`. Bulk purge vs individual URL.
- **TTL-based**: Cache-i expire olmağa burax. Sadə, amma stale window var.
- **Event-driven invalidation**: Kafka/SQS event-i ilə cache invalidation trigger. CDC (Change Data Capture) ilə DB dəyişikliyi → cache sil.

### Cache Busting Strategiyaları:
- **Content hash fingerprinting**: `app.a3f2c.css` — məzmun dəyişəndə URL dəyişir, browser yeni versiya yükləyir. `immutable` ilə kombinasiya: sonsuz TTL. Webpack, Vite, Laravel Mix bu formatı avtomatik yaradır.
- **Versioned URL**: `/api/v2/resource` — versiya dəyişəndə URL dəyişir.
- **Query param**: `/logo.png?v=2` — az tövsiyə olunur: bəzi CDN-lər query string-i ignore edir (Cloudflare default olaraq ignore edir, konfiqurasiya tələb olunur).

### Vary Header:
- Cache key-ə hansı request header-larının daxil ediləcəyini bildirir.
- `Vary: Accept-Language` → `en` ilə `az` gələn sorğular ayrı cache entry.
- `Vary: Accept-Encoding` → `gzip` ilə `br` (brotli) versiyaları ayrı cache.
- `Vary: Cookie` → praktik olaraq cache-i deaktiv edir (hər user fərqli cookie).
- `Vary: Authorization` → authenticated response-ları cache etmə.
- CDN-lər `Vary` header-ı bəzən düzgün dəstəkləmir — diqqətlə istifadə et.

### RFC Referanslar:
- RFC 9111: HTTP/1.1 Caching — `Cache-Control`, `ETag`, conditional request-lərin tam spesifikasiyası.
- RFC 8594: `Sunset` header.
- RFC 5861: `stale-while-revalidate`, `stale-if-error` direktivləri.

### Service Worker Caching vs HTTP Caching:
- **HTTP cache**: Browser tərəfindən avtomatik idarə olunur, header-lara əsaslanır. JavaScript kodu tələb olunmur.
- **Service Worker**: JavaScript-ə əsaslanır, offline support üçün, custom cache strategy (cache-first, network-first, stale-while-revalidate). PWA-lar üçün kritik.
- Hər ikisi birlikdə işləyə bilər — SW öncə, sonra HTTP cache check.

### Pragma: no-cache:
- HTTP/1.0 üçün köhnə header — artıq istifadə edilmir. `Cache-Control: no-cache` eyni şeydir.
- Bəzən geriyə dönük uyğunluq üçün hər ikisi birlikdə göndərilir: `Cache-Control: no-cache` + `Pragma: no-cache`.

### Cache Poisoning Attack:
- Attacker cache-ə zərərli məzmun yerləşdirə bilir — xüsusilə shared cache-lər risklidir.
- HTTP header injection ilə mümkün: `\r\n` injection, Host header manipulation.
- Mitigation: Input validation, strict header parsing, CDN-in host header yoxlaması.
- `X-Forwarded-For`, `X-Host` kimi header-ları cache key-ə əlavə etməkdən qaçın.

## Praktik Baxış

### Interview-da Yanaşma:
Cache-Control directive-lərini sadəcə sadalamaq əvəzinə, real scenario üzərindən izah edin:
- E-commerce product page: `public, max-age=300, stale-while-revalidate=60` — 5 dəqiqə fresh, arxa planda yenilə.
- User avatar: `private, max-age=3600` — CDN-dən keçməməlidir.
- Auth endpoint response: `no-store` — heç bir cache.
- CSS/JS assets (fingerprinted): `public, max-age=31536000, immutable` — 1 il, dəyişməyəcək.

### Follow-up Suallar:
- "`no-cache` ilə `no-store` fərqi nədir?" → no-cache cache edir amma yoxlayır; no-store heç cache etmir.
- "Cache poisoning nədir?" → Attacker shared cache-ə zərərli response yerləşdirir — bütün user-lər görür.
- "Service Worker caching HTTP caching-dən fərqli nədir?" → SW JavaScript, offline support; HTTP cache avtomatik.
- "CDN cache invalidation necə işləyir?" → API purge, tag-based purge, TTL expire.
- "`s-maxage` nə zaman lazımdır?" → CDN TTL-ini browser TTL-dən ayırmaq üçün.
- "ETag-ı necə hesablayırsınız?" → md5(content), ya da `last_modified_timestamp`, ya da version number.
- "Stale-while-revalidate UX-a necə kömək edir?" → User sıfır latency görür — background yeniləmə.
- "Mobile app-da offline caching üçün HTTP cache yetərlidirmi?" → Yox, Service Worker + IndexedDB lazımdır.
- "`Vary: Accept-Encoding` olmadan CDN nə baş verir?" → gzip ilə compress edilmiş response brotli gözləyən client-ə göndərilə bilər.

### Ümumi Səhvlər:
- `no-cache` ilə `no-store`-u qarışdırmaq — ən çox görülən xəta. "no-cache heç cache etmir" düşüncəsi yanlışdır.
- Private data üçün `public` cache istifadə etmək — user-specific data CDN-də hər user üçün görünür.
- `Cache-Control` əvəzinə yalnız `Expires` header istifadə etmək — `Expires` HTTP/1.0-dan köhnə, dəqiq deyil.
- API response-larında heç Cache-Control göndərməmək — browser/CDN default davranışı müxtəlifdir, bəzi CDN-lər öz TTL tətbiq edir.
- `max-age` ilə `s-maxage`-i eyni tutmaq — CDN TTL-i browser-lə eyni olmaq məcburiyyəti yoxdur.
- ETag-ı `"abc"` yerinə `abc` (qoşasız) yazmaq — strong ETag mütləq `"..."` içərisindədir.
- `Last-Modified` header-ını UTC olmayan timezone ilə göndərmək.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən:
`stale-while-revalidate` pattern-inin user experience-a necə yaxşılaşdırdığını, Vary header-ın cache key-ə necə daxil olduğunu, CDN-in tag-based purge mexanizmini, cache poisoning attack-ın necə qarşılandığını izah edə bilmək. ETag-ın optimistic locking üçün istifadəsini (If-Match) bilmək.

## Nümunələr

### Tipik Interview Sualı

"You have a product listing page that queries the database heavily. It's updated every 5 minutes by admin. How would you use HTTP caching to reduce load without serving stale data too long?"

### Güclü Cavab

Məhsul listesi üçün multi-layer caching strategiyası:

**CDN Layer**: `Cache-Control: public, max-age=300, stale-while-revalidate=60`. 5 dəqiqə tamamilə cached. Sonrakı 60 saniyə köhnə data göstərilir, arxa planda yenilənir — user heç loading görmir.

**Browser Layer**: `private, max-age=60` — user personal data olmadığı üçün qısa, CDN cache istifadə edilir.

**ETag**: Product list-in content hash-ini ETag kimi göndəririk. Mobile app-lar `If-None-Match` ilə 304 Not Modified alır — bandwidth sıfır.

**Cache Invalidation**: Admin məhsul yenilədikdə CDN-ə tag-based purge: `Cache-Tag: products,product-123`. Yalnız dəyişən məhsulun cache-ini sil — bütün cache-i deyil.

Bu yanaşma DB yükünü 90%+ azaldır, user latency-ni 300ms-dən 10ms-ə çatdırır.

### Kod Nümunəsi

```php
// Laravel Cache-Control headers — çox istifadə olunan pattern-lər
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Cache::remember('products.list', 300, fn() =>
            Product::with('category')->paginate(20)
        );

        // ETag — məzmun dəyişibsə yeni hash
        $etag = '"' . md5(serialize($products->toArray())) . '"';

        // Conditional request — məzmun dəyişməyibsə 304 qaytar
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        return response()->json($products)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=60')
            ->header('Vary', 'Accept-Language, Accept-Encoding')
            ->header('Cache-Tag', 'products');  // Cloudflare/Fastly tag-based purge üçün
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        // Last-Modified + ETag hər ikisi
        $etag = '"' . md5((string) $product->updated_at->timestamp) . '"';
        $lastModified = $product->updated_at->toRfc7231String();

        // Strong ETag check
        if ($request->header('If-None-Match') === $etag) {
            return response()->noContent(304);
        }

        // Weak validator: If-Modified-Since
        if ($ifModifiedSince = $request->header('If-Modified-Since')) {
            $since = Carbon::parse($ifModifiedSince);
            if ($product->updated_at->lte($since)) {
                return response()->noContent(304);
            }
        }

        return response()->json($product)
            ->header('ETag', $etag)
            ->header('Last-Modified', $lastModified)
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Cache-Tag', "products,product-{$product->id}");
    }

    // Optimistic locking — If-Match ilə conflict detection
    public function update(Request $request, Product $product): JsonResponse
    {
        $clientEtag = $request->header('If-Match');
        $serverEtag = '"' . md5((string) $product->updated_at->timestamp) . '"';

        if ($clientEtag && $clientEtag !== $serverEtag) {
            // Başqa biri eyni vaxtda yeniləyib — conflict!
            return response()->json(['error' => 'Conflict — resource modified'], 412);
        }

        $product->update($request->validated());

        // Cache-i purge et
        Cache::forget('products.list');
        $this->purgeCdnCache("product-{$product->id}");

        $newEtag = '"' . md5((string) $product->fresh()->updated_at->timestamp) . '"';
        return response()->json($product)
            ->header('ETag', $newEtag);
    }

    private function purgeCdnCache(string $tag): void
    {
        // Cloudflare tag-based purge
        Http::withHeaders(['X-Auth-Email' => config('cdn.email')])
            ->post("https://api.cloudflare.com/zones/{$zone}/purge_cache", [
                'tags' => [$tag]
            ]);
    }
}
```

```php
// Middleware — sensitive data üçün no-cache / no-store
class CacheControlMiddleware
{
    public function handle(Request $request, Closure $next, string $policy = 'default'): Response
    {
        $response = $next($request);

        match($policy) {
            'no-store' => $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            ),
            'private' => $response->headers->set(
                'Cache-Control',
                'private, no-cache, must-revalidate'
            ),
            'public-short' => $response->headers->set(
                'Cache-Control',
                'public, max-age=300, stale-while-revalidate=60'
            ),
            'public-long' => $response->headers->set(
                'Cache-Control',
                'public, max-age=86400, s-maxage=604800'
            ),
            'immutable' => $response->headers->set(
                'Cache-Control',
                'public, max-age=31536000, immutable'
            ),
            default => null
        };

        return $response;
    }
}

// routes/api.php
Route::middleware(['auth', 'cache:no-store'])->group(function () {
    Route::get('/profile', ProfileController::class);      // heç cache etmə
    Route::get('/orders', OrderController::class);         // heç cache etmə
});

Route::middleware(['cache:public-short'])->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});

// Static assets — fingerprinted, immutable
Route::get('/assets/{file}', function ($file) {
    return response()->file(public_path("assets/{$file}"))
        ->header('Cache-Control', 'public, max-age=31536000, immutable');
})->where('file', '.*\.(css|js|png|jpg|woff2)$');
```

```
Cache Decision Tree:
─────────────────────────────────────────────────────────
Response hazır?
    │
    ├── Sensitive data (auth token, personal info, health)?
    │       └── Cache-Control: no-store
    │
    ├── User-specific data (profile, cart, orders)?
    │       └── Cache-Control: private, no-cache
    │               (browser cache, həmişə validate)
    │
    ├── Static assets ilə content hash?
    │       └── Cache-Control: public, max-age=31536000, immutable
    │               (1 il, heç vaxt dəyişməyəcək)
    │
    ├── Nadir dəyişən public data (homepage, product list)?
    │       └── Cache-Control: public, max-age=300,
    │               stale-while-revalidate=60
    │               + ETag
    │               + Cache-Tag: <tag> (CDN invalidation)
    │
    └── Real-time data (live scores, stock price)?
            └── Cache-Control: no-cache, max-age=0
                    + ETag (conditional request)
                    (Server push / WebSocket daha uyğun)

HTTP Status Code Reference:
    200 OK          → Full response, cache-ə al
    304 Not Modified → Body yoxdur, cached versiyadan istifadə et
    412 Precondition Failed → If-Match uyğun gəlmədi (conflict)
```

### İkinci Nümunə — CDN Caching Strategy

**Sual**: "Large e-commerce platformu üçün CDN caching strategiyası necə qurarsınız? Homepage, product page, cart page üçün fərqli davranış lazımdır."

**Cavab**:
```
Homepage:
  Cache-Control: public, max-age=3600, stale-while-revalidate=300
  Cache-Tag: homepage
  → CDN-də 1 saat. Marketing hər gün dəyişdirir — tag purge ilə anlıq invalidation.

Product page (public, SEO critical):
  Cache-Control: public, max-age=300, stale-while-revalidate=60
  Cache-Tag: product-{id}, category-{id}
  → 5 dəqiqə fresh. Qiymət dəyişdikdə product tag purge.

Product API (mobile app üçün):
  Cache-Control: public, s-maxage=60, max-age=0
  ETag: "{content-hash}"
  Vary: Accept-Encoding
  → CDN-də 60s, browser-də 0s. Mobile app ETag ilə conditional request.

Cart/Checkout (user-specific):
  Cache-Control: private, no-cache, no-store
  → Heç CDN cache etmir. Browser-da da saxlama.

User Auth endpoints:
  Cache-Control: no-store
  → Heç bir yerdə saxlanmır. Token leakage risk sıfıra endirilir.

Static assets (fingerprinted):
  Cache-Control: public, max-age=31536000, immutable
  → 1 il CDN cache. Yeni deploy → yeni URL (hash dəyişir) → avtomatik cache bust.
```

## Praktik Tapşırıqlar

1. Laravel API-nizdə bütün route-ların Cache-Control header-larını audit edin — hansı private, hansı public olmalı?
2. Sensitive API endpoint-də `no-store` ilə `no-cache`-in fərqini browser DevTools-da test edin.
3. ETag-lı endpoint qurun, `If-None-Match` ilə 304 Not Modified testi edin. Response size-ını müqayisə edin.
4. Cloudflare ya da AWS CloudFront-da Cache-Control header-larının necə interpret olunduğunu test edin — s-maxage vs max-age.
5. `stale-while-revalidate` user experience-a necə təsir edir — A/B müqayisə qurun: 200ms delay simülyasiya edin.
6. Cache-Tag ilə selective purge implementasiya edin: məhsul qiyməti dəyişdikdə yalnız həmin məhsulun cache-ini sil.
7. `Vary: Accept-Encoding` header-ını əlavə edin, gzip vs brotli response-ların ayrı cache entry aldığını doğrulayın.
8. Optimistic locking: `If-Match` + `412 Precondition Failed` ilə lost update problem-inin həllini implement edin.

## Əlaqəli Mövzular

- [HTTP Versions](02-http-versions.md) — HTTP/2 push, HTTP/3 QUIC-in caching davranışı
- [API Versioning](08-api-versioning.md) — Versioned URL-lərin caching üstünlüyü; Vary header ilə header versioning
- [Proxy vs Reverse Proxy](13-proxy-reverse-proxy.md) — Reverse proxy caching (Nginx, Varnish); surrogate-key purge
- [DNS Resolution](04-dns-resolution.md) — CDN-in DNS ilə işləməsi; edge location routing
- [DDoS Protection](15-ddos-protection.md) — CDN cache DDoS yükünü azaldır; origin shield
