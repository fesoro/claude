# CDN - Content Delivery Network (Middle)

## İcmal

CDN dünyanın müxtəlif yerlərində yerləşən edge server-lər şəbəkəsidir. Content-i istifadəçiyə ən yaxın serverdən serve edərək latency-ni azaldır və performansı artırır. Static fayllar (images, CSS, JS), video, API response-ları CDN vasitəsilə paylaşıla bilər.

```
CDN olmadan:
  User (Baku) ------- 200ms -------> Origin Server (Frankfurt)

CDN ilə:
  User (Baku) --- 20ms ---> CDN Edge (Istanbul) --> Cache HIT --> Response
                                                --> Cache MISS --> Origin (Frankfurt)
```

## Niyə Vacibdir

CDN olmadan bütün trafik origin serverə gəlir — həm yüksək latency, həm də yüksək yük yaranır. Static asset-ləri CDN-ə köçürmək origin serverə düşən yükü 70-90% azalda bilər. Bundan əlavə, CDN DDoS hücumlarına qarşı birinci müdafiə xəttini təşkil edir — traffic CDN edge-lərindən keçərək filtrlənir, origin heç vaxt birbaşa məruz qalmır.

## Əsas Anlayışlar

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
  1. User request göndərir: cdn.example.com/image.jpg
  2. Edge server cache-də yoxlayır
  3. Cache MISS -> Origin-dən çəkir (pull)
  4. Cache-ləyir + user-ə qaytarır
  5. Növbəti request: Cache HIT -> birbaşa qaytarır

  + Avtomatik, konfiqurasiya az
  + Yalnız lazım olan content cache olunur
  - İlk request yavaş (cold cache)

Push CDN:
  1. Developer content-i CDN-ə upload edir (push)
  2. CDN bütün edge server-lərə paylaşır
  3. User request edir -> həmişə cache HIT

  + İlk request də sürətli
  + Tam kontrol
  - Manual idarəetmə
  - Storage xərcinini ödəyirsiniz
```

### Cache Headers

```
Origin Server response headers:

Cache-Control: public, max-age=31536000    # 1 il cache (immutable assets)
Cache-Control: public, max-age=300          # 5 dəqiqə (API responses)
Cache-Control: private, no-store            # Cache etmə (user-specific data)
Cache-Control: public, s-maxage=3600        # CDN üçün 1 saat (s-maxage CDN-ə aiddir)
Cache-Control: public, max-age=60, stale-while-revalidate=300

ETag: "abc123"                              # Content fingerprint
Last-Modified: Wed, 15 Apr 2026 10:00:00 GMT

Vary: Accept-Encoding                       # Encoding-ə görə ayrı cache
Vary: Accept-Language                       # Dilə görə ayrı cache

CDN-Specific:
  Cloudflare-CDN-Cache-Control: max-age=86400
  Surrogate-Control: max-age=3600           # Fastly/Varnish
```

### Cache Invalidation

```
1. TTL-based (Time-based expiry):
   Cache-Control: max-age=300  (5 dəqiqədən sonra expire)
   Ən sadə, amma dəyişikliklər gec yayılır.

2. Purge (Manual invalidation):
   CDN API ilə konkret URL-in cache-ini silin.
   POST /purge {"url": "https://cdn.example.com/image.jpg"}

3. Cache Tags (Grouped invalidation):
   Response-a tag əlavə edin: Cache-Tag: product-42, category-5
   Bütün "product-42" tag-li cache-ləri silin.

4. Versioned URLs (ən yaxşı):
   /assets/style.v2.css  və ya  /assets/style.css?v=abc123
   Fayl dəyişəndə URL dəyişir -> cache avtomatik yenilənir.

5. Stale-While-Revalidate:
   Cache expire olunca köhnə versiyasını göndər, arxada yeniləndir.
```

### CDN Use Cases və Popular Providers

```
Use Cases:
  1. Static Assets:     CSS, JS, images, fonts
  2. Video Streaming:   Netflix, YouTube
  3. Software Updates:  OS, app updates
  4. API Acceleration:  Cacheable API responses
  5. DDoS Protection:   Traffic distribution
  6. Dynamic content:   Edge computing (Cloudflare Workers)

Providers:
  Cloudflare   - Free plan, DNS, WAF, Workers (edge computing)
  AWS CloudFront - AWS integration, Lambda@Edge
  Google Cloud CDN - Google infrastructure
  Fastly       - Real-time purge, VCL, edge computing
  Akamai       - Ən böyük CDN, enterprise
  Bunny CDN    - Ucuz, sadə
```

### Edge Computing

```
Traditional CDN:
  Edge caches static content only

Edge Computing (Cloudflare Workers, Lambda@Edge):
  Edge runs code — dynamic response yaxın serverdə yaranır

  Məsələn:
  - A/B testing edge-də
  - Auth check edge-də
  - Geolocation redirect
  - API response transformation
  - Image resizing at edge
```

## Praktik Baxış

**Üstünlüklər:**
- Latency dramatik azalır (200ms → 20ms)
- Origin serverə düşən yük azalır
- DDoS protection birinci xəttdə
- Bandwidth xərcləri azalır

**Trade-off-lar:**
- Cache invalidation çətin problem — dəyişiklik dərhal bütün edge-lərə çatmır
- User-specific data CDN-də cache edilə bilməz
- Əlavə xərc (kiçik layihələr üçün Cloudflare free plan bəs edir)
- Debug çətin olur — edge-dən gələn response-u origin ilə müqayisə etmək lazım gəlir

**Nə vaxt istifadə edilməməlidir:**
- Authenticated, user-specific endpoint response-ları (`private, no-store`)
- Real-time data (stock prices, live scores) — stale data göstərər

**Anti-pattern-lər:**
- Bütün endpoint-ləri eyni TTL ilə cache etmək
- `Cache-Control: private` əvəzinə `public` işlətmək user data üçün
- Cache invalidation olmadan uzun TTL qoymaq

## Nümunələr

### Ümumi Nümunə

Laravel-in `asset()` helper-i `ASSET_URL` env dəyişəninə əsasən URL-ləri avtomatik CDN URL-inə çevirir. Vite build zamanı fayl adlarına hash əlavə edir — bu cache busting-i avtomatik həll edir.

### Kod Nümunəsi

**Laravel Asset CDN:**

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

**Laravel Vite Versioning (cache busting):**

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
// Output: /assets/app-a1b2c3d4.css  (hash dəyişəndə CDN cache yenilənir)
```

**Cache Headers Middleware:**

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
            'api'    => $this->apiCache($response),
            'private'=> $this->privateCache($response),
            default  => $response,
        };
    }

    private function staticCache(Response $response): Response
    {
        return $response->setCache([
            'public'    => true,
            'max_age'   => 31536000,  // 1 il
            'immutable' => true,
        ]);
    }

    private function apiCache(Response $response): Response
    {
        return $response->setCache([
            'public'   => true,
            'max_age'  => 60,
            's_maxage' => 300,  // CDN üçün 5 dəqiqə
        ])->header('Surrogate-Control', 'max-age=300')
          ->header('Cache-Tag', 'api');
    }

    private function privateCache(Response $response): Response
    {
        return $response->setCache([
            'private'  => true,
            'no_store' => true,
        ]);
    }
}

// Route-da istifadə
Route::get('/api/products', [ProductController::class, 'index'])
    ->middleware('cdn.cache:api');

Route::get('/api/user/profile', [ProfileController::class, 'show'])
    ->middleware('cdn.cache:private');
```

**CDN Cache Purge Service (Cloudflare):**

```php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CdnPurgeService
{
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

**Model Observer ilə Auto-Purge:**

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

**File Upload to CDN (S3 + CloudFront):**

```php
// config/filesystems.php
's3' => [
    'driver' => 's3',
    'key'    => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url'    => env('AWS_URL'),  // CloudFront URL
],

// Upload
$path = $request->file('avatar')->store('avatars', 's3');
$url = Storage::disk('s3')->url($path);
// https://d1234.cloudfront.net/avatars/abc123.jpg
```

## Praktik Tapşırıqlar

1. **Cloudflare bağlantısı:** Domeninizi Cloudflare-ə əlavə edin. `curl -I https://example.com/css/app.css` ilə `CF-Cache-Status` headerini yoxlayın — ilk request MISS, növbəti HIT olmalıdır.

2. **Cache header middleware:** `CdnCacheHeaders` middleware-ini layihənizə əlavə edin. Public API endpoint-lərini `cdn.cache:api`, profil endpoint-lərini `cdn.cache:private` ilə işarələyin. `curl -I` ilə response headerlərini yoxlayın.

3. **Auto-purge qurmaq:** `ProductObserver` yaradın. Product update olduqda müvafiq CDN URL-lərini purge edin. `Product::find(1)->update(['name' => 'Test'])` ilə test edin — Cloudflare dashboard-da purge tarixçəsini yoxlayın.

4. **Vite + CDN:** `ASSET_URL=https://cdn.example.com` qoyun, `npm run build` edin. Blade-dəki asset URL-lərinin CDN domain-inə işarə etdiyini yoxlayın.

5. **Cache hit ratio monitoring:** Cloudflare Analytics-də cache hit ratio-nu izləyin. 80%-dən aşağıdırsa, hansı URL-lərin MISS olduğunu analiz edib TTL-ləri optimizasiya edin.

## Əlaqəli Mövzular

- [Reverse Proxy](19-reverse-proxy.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Network Security](26-network-security.md)
- [HTTPS / SSL / TLS](06-https-ssl-tls.md)
- [Load Balancing](18-load-balancing.md)
