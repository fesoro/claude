# CDN (Content Delivery Network)

## Nədir? (What is it?)

CDN dunyanin muxtelif yerlerinde yerlesen edge server-ler sebekesidir. Content-i istifadeciye en yaxin serveden gore biler, bununla latency azalir ve performance artır. Static fayllar (images, CSS, JS), video, API response-lari CDN vasitesile paylashila biler.

```
CDN olmadan:
  User (Baku) ------- 200ms -------> Origin Server (Frankfurt)

CDN ile:
  User (Baku) --- 20ms ---> CDN Edge (Istanbul) --> Cache HIT --> Response
                                                --> Cache MISS --> Origin (Frankfurt)
```

## Necə İşləyir? (How does it work?)

### CDN Architecture

```
                         ┌──────────────────┐
                         │   Origin Server   │
                         │  (Frankfurt, DE)  │
                         └────────┬─────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    │             │             │
              ┌─────┴─────┐ ┌────┴────┐ ┌─────┴─────┐
              │ Edge Node │ │  Edge   │ │ Edge Node │
              │ Istanbul  │ │  Dubai  │ │  Mumbai   │
              └─────┬─────┘ └────┬────┘ └─────┬─────┘
                    │            │             │
              Users (AZ)   Users (UAE)   Users (IN)
```

### Pull vs Push CDN

```
Pull CDN (daha populyar):
  1. User request gonderir: cdn.example.com/image.jpg
  2. Edge server cache-de yoxlayir
  3. Cache MISS -> Origin-den cekir (pull)
  4. Cache-leyir + user-e qaytarir
  5. Novbeti request: Cache HIT -> birbase qaytarir

  + Avtomatik, konfiqurasiya az
  + Yalniz lazim olan content cache olunur
  - Ilk request yavas (cold cache)

Push CDN:
  1. Developer content-i CDN-e upload edir (push)
  2. CDN butun edge server-lere paylayir
  3. User request edir -> hemise cache HIT

  + Ilk request de suretli
  + Tam kontrol
  - Manual idareetme
  - Storage xercini odeyirsiniz
```

### Cache Headers

```
Origin Server response headers:

Cache-Control: public, max-age=31536000    # 1 il cache (immutable assets)
Cache-Control: public, max-age=300          # 5 deqiqe (API responses)
Cache-Control: private, no-store            # Cache etme (user-specific data)
Cache-Control: public, s-maxage=3600        # CDN ucun 1 saat (s-maxage CDN-e aiddir)
Cache-Control: public, max-age=60, stale-while-revalidate=300

ETag: "abc123"                              # Content fingerprint
Last-Modified: Wed, 15 Apr 2026 10:00:00 GMT

Vary: Accept-Encoding                       # Encoding-e gore ayri cache
Vary: Accept-Language                       # Dile gore ayri cache

CDN-Specific:
  Cloudflare-CDN-Cache-Control: max-age=86400
  Surrogate-Control: max-age=3600           # Fastly/Varnish
```

### Cache Invalidation

```
1. TTL-based (Time-based expiry):
   Cache-Control: max-age=300  (5 deqiqeden sonra expire)
   En sade amma deyisiklikler gec yayilir.

2. Purge (Manual invalidation):
   CDN API ile konkret URL-in cache-ini silin.
   POST /purge {"url": "https://cdn.example.com/image.jpg"}

3. Cache Tags (Grouped invalidation):
   Response-a tag elave edin: Cache-Tag: product-42, category-5
   Butun "product-42" tag-li cache-leri silin.

4. Versioned URLs (en yaxsi):
   /assets/style.v2.css  ve ya  /assets/style.css?v=abc123
   Fayl deyisende URL deyisir -> cache avtomatik yenilenir.

5. Stale-While-Revalidate:
   Cache expire olunca kohne versiyani gonder, arxada yenilesdir.
```

## Əsas Konseptlər (Key Concepts)

### CDN Use Cases

```
1. Static Assets:     CSS, JS, images, fonts
2. Video Streaming:   Netflix, YouTube
3. Software Updates:  OS, app updates
4. API Acceleration:  Cacheable API responses
5. DDoS Protection:   Traffic distribution
6. Dynamic content:   Edge computing (Cloudflare Workers)
```

### Popular CDN Providers

```
Cloudflare  - Free plan, DNS, WAF, Workers (edge computing)
AWS CloudFront - AWS integration, Lambda@Edge
Google Cloud CDN - Google infrastructure
Fastly  - Real-time purge, VCL, edge computing
Akamai  - En boyuk CDN, enterprise
Bunny CDN - Ucuz, sade
```

### Edge Computing

```
Traditional CDN:
  Edge caches static content only

Edge Computing (Cloudflare Workers, Lambda@Edge):
  Edge runs code - dynamic response yaxin serverde yaranir

  Meselen:
  - A/B testing edge-de
  - Auth check edge-de
  - Geolocation redirect
  - API response transformation
  - Image resizing at edge
```

## PHP/Laravel ilə İstifadə

### Laravel Asset CDN

```php
// .env
ASSET_URL=https://cdn.example.com

// config/app.php
'asset_url' => env('ASSET_URL'),

// Blade template
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
// Output: https://cdn.example.com/css/app.css

<img src="{{ asset('images/logo.png') }}">
// Output: https://cdn.example.com/images/logo.png
```

### Laravel Mix / Vite Versioning

```javascript
// vite.config.js
export default defineConfig({
    build: {
        manifest: true,
        rollupOptions: {
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
            },
        },
    },
});

// Blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
// Output: /assets/app-a1b2c3d4.css  (hash deyisende CDN cache yenilenir)
```

### Cache Headers Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CdnCacheHeaders
{
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || !$response->isSuccessful()) {
            return $response;
        }

        return match ($type) {
            'static' => $this->staticCache($response),
            'api' => $this->apiCache($response),
            'private' => $this->privateCache($response),
            default => $response,
        };
    }

    private function staticCache(Response $response): Response
    {
        return $response->setCache([
            'public' => true,
            'max_age' => 31536000,  // 1 il
            'immutable' => true,
        ]);
    }

    private function apiCache(Response $response): Response
    {
        return $response->setCache([
            'public' => true,
            'max_age' => 60,
            's_maxage' => 300,  // CDN ucun 5 deqiqe
        ])->header('Surrogate-Control', 'max-age=300')
          ->header('Cache-Tag', 'api');
    }

    private function privateCache(Response $response): Response
    {
        return $response->setCache([
            'private' => true,
            'no_store' => true,
        ]);
    }
}

// Route-da istifade
Route::get('/api/products', [ProductController::class, 'index'])
    ->middleware('cdn.cache:api');

Route::get('/api/user/profile', [ProfileController::class, 'show'])
    ->middleware('cdn.cache:private');
```

### CDN Cache Purge Service

```php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CdnPurgeService
{
    /**
     * Cloudflare cache purge
     */
    public function purgeUrls(array $urls): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.cloudflare.api_token'),
        ])->post(
            "https://api.cloudflare.com/client/v4/zones/" .
            config('services.cloudflare.zone_id') . "/purge_cache",
            ['files' => $urls]
        );

        if ($response->successful()) {
            Log::info('CDN cache purged', ['urls' => $urls]);
            return true;
        }

        Log::error('CDN purge failed', ['response' => $response->json()]);
        return false;
    }

    /**
     * Butun cache-i temizle
     */
    public function purgeAll(): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.cloudflare.api_token'),
        ])->post(
            "https://api.cloudflare.com/client/v4/zones/" .
            config('services.cloudflare.zone_id') . "/purge_cache",
            ['purge_everything' => true]
        );

        return $response->successful();
    }

    /**
     * Tag ile purge (Cloudflare Enterprise)
     */
    public function purgeByTags(array $tags): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.cloudflare.api_token'),
        ])->post(
            "https://api.cloudflare.com/client/v4/zones/" .
            config('services.cloudflare.zone_id') . "/purge_cache",
            ['tags' => $tags]
        );

        return $response->successful();
    }
}
```

### Model Observer ile Auto-Purge

```php
namespace App\Observers;

use App\Models\Product;
use App\Services\CdnPurgeService;

class ProductObserver
{
    public function __construct(
        private CdnPurgeService $cdn
    ) {}

    public function updated(Product $product): void
    {
        $this->cdn->purgeUrls([
            config('app.url') . "/api/products/{$product->id}",
            config('app.url') . "/api/products",
        ]);
    }

    public function deleted(Product $product): void
    {
        $this->cdn->purgeUrls([
            config('app.url') . "/api/products/{$product->id}",
            config('app.url') . "/api/products",
        ]);
    }
}
```

### File Upload to CDN (S3 + CloudFront)

```php
// config/filesystems.php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),  // CloudFront URL
    ],
],

// Upload
$path = $request->file('avatar')->store('avatars', 's3');
$url = Storage::disk('s3')->url($path);
// https://d1234.cloudfront.net/avatars/abc123.jpg
```

## Interview Sualları

### 1. CDN nedir ve nece isleyir?
**Cavab:** CDN dunyanin muxtelif yerlerinde edge serverler sebekesidir. Content-i user-e en yaxin serverden serve edir. Pull model ile ilk request origin-den cekilir ve cache-lenir, sonraki request-ler edge-den qaytarilir.

### 2. Pull CDN ve Push CDN arasinda ferq nedir?
**Cavab:** Pull CDN-de content ilk request zamani origin-den avtomatik cekilir (Cloudflare, CloudFront). Push CDN-de developer content-i manual upload edir. Pull daha populyar ve avtomatikdir, Push daha cox kontrol verir.

### 3. Cache invalidation nece edilir?
**Cavab:** 1) TTL ile avtomatik expire, 2) API ile manual purge, 3) Cache tags ile qrup halinda, 4) Versioned URLs (file hash) - en effektiv. "Cache invalidation" CS-in en cetin problemlerinden biridir.

### 4. `Cache-Control` headerlari neler var?
**Cavab:** `public` (CDN cache ede biler), `private` (yalniz brauzer), `max-age` (brauzer TTL), `s-maxage` (CDN TTL), `no-store` (cache etme), `no-cache` (hemise revalidate), `immutable` (deyismez content), `stale-while-revalidate`.

### 5. CDN hansi content-i cache etmelidir?
**Cavab:** Static assets (CSS, JS, images, fonts) - uzun muddet. Public API responses - qisa muddet. User-specific content cache OLMAMALIDIR (private, no-store). HTML-de ehtiyatli olun - dynamic content ola biler.

### 6. Stale-while-revalidate nedir?
**Cavab:** Cache expire olanda derhâl kohne (stale) versiyani qaytarir, arxada origin-den yeni versiyani alir. User gozlemirlir, amma kicik gecikmeli data gore biler. Performance ve freshness arasinda balansdir.

### 7. Edge computing nedir?
**Cavab:** CDN edge serverlerde kod icra etmek (Cloudflare Workers, Lambda@Edge). Static cache yerine dynamic logic edge-de isleyir: A/B testing, auth, geolocation redirect, image optimization. Origin-e getmeden cavab yaradilir.

## Best Practices

1. **Versioned URLs** - Asset fayllarinda hash istifade edin (cache busting)
2. **Uzun max-age + immutable** - Static assets ucun 1 il cache
3. **s-maxage istifade edin** - CDN ve brauzer ucun ferqli TTL
4. **Vary header** - Encoding, language-e gore cache ayirin
5. **Cache-Tag** - Content qruplari ucun tag elave edin
6. **Auto-purge** - Model deyisende avtomatik cache silin
7. **Stale-while-revalidate** - Performance + freshness balansi
8. **Private data cache etmeyin** - User-specific data: no-store
9. **CDN monitoring** - Hit ratio, bandwidth, latency izleyin
10. **Fallback** - CDN down olanda origin-e direct erisim temin edin
