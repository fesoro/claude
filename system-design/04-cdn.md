# CDN (Content Delivery Network)

## Nədir? (What is it?)

CDN dünya üzərində paylanmış server şəbəkəsidir. Static content-i (şəkillər, CSS, JS, video)
istifadəçiyə ən yaxın serverdən (edge location) təqdim edir. Bu latency-ni azaldır və
origin server yükünü yüngülləşdirir.

```
İstifadəçi Bakıda:
  CDN olmadan:  Bakı -> ABŞ (origin server) -> Bakı  (~200ms)
  CDN ilə:     Bakı -> İstanbul (edge) -> Bakı       (~20ms)
```

## Əsas Konseptlər (Key Concepts)

### Push vs Pull CDN

**Pull CDN (Origin Pull)**
İlk request origin-dən çəkilir, sonra edge-də cache olunur. Sonrakı request-lər cache-dən.

```
1. User -> Edge (cache miss) -> Origin Server
2. Origin -> Edge (cache edir) -> User
3. User -> Edge (cache hit) -> User (origin-ə getmir)
```

Üstünlük: Sadə setup, yalnız istənilən content cache olunur
Mənfi: İlk request yavaş (cold miss)
Nə vaxt: Dinamik content, yüksək trafik

**Push CDN**
Content əvvəlcədən bütün edge-lərə yüklənir.

```
Admin -> Origin -> Push to all edge locations
User -> Edge (cache hit) -> User
```

Üstünlük: İlk request də sürətli
Mənfi: Storage cost, content management çətin
Nə vaxt: Statik content (video, software downloads)

### Edge Location və PoP

```
PoP (Point of Presence): Edge server-lərin fiziki yeri

Global CDN topology:
  North America: 50+ PoPs
  Europe: 40+ PoPs
  Asia: 30+ PoPs
  ...

Hər PoP-da:
  - Edge servers (cache + serve)
  - DNS resolvers
  - DDoS protection
```

### Cache Headers

```
# Origin server response headers:

Cache-Control: public, max-age=31536000
  -> Hər kəs cache edə bilər, 1 il valid

Cache-Control: private, no-cache
  -> Yalnız browser cache, hər dəfə revalidate

Cache-Control: no-store
  -> Heç yerdə cache olunmasın

ETag: "abc123"
  -> Content dəyişibsə yeni version göndər

Last-Modified: Wed, 15 Jan 2025 10:00:00 GMT
  -> Conditional request üçün

Vary: Accept-Encoding, Accept-Language
  -> Fərqli encoding/dil üçün ayrı cache
```

### Cache Invalidation

```
1. TTL-based: max-age bitdikdə avtomatik expire
2. Purge: Manual olaraq specific URL-i cache-dən silmək
3. Purge all: Bütün cache-i silmək
4. Soft purge: Stale content göstər, background-da yenilə (stale-while-revalidate)
5. Tag-based: Cache tag ilə qrup halında invalidate
```

### CDN Providers

**CloudFront (AWS)**
- AWS ekosistemi ilə dərin inteqrasiya
- Lambda@Edge ilə edge computing
- S3, ALB, EC2 origin dəstəyi
- 400+ PoP worldwide

**Cloudflare**
- Free tier mövcud
- DDoS protection daxildir
- Workers (serverless edge computing)
- DNS + CDN birlikdə
- 300+ şəhərdə PoP

**Akamai**
- Enterprise-level, ən böyük CDN
- 4000+ PoP
- Video streaming üçün güclü

## Arxitektura (Architecture)

### CDN Request Flow

```
1. User types example.com
2. DNS resolves to CDN edge IP (anycast routing)
3. User request -> Nearest Edge Location
4. Edge checks cache:
   HIT  -> Return cached content
   MISS -> Request from origin
5. Origin responds -> Edge caches -> User receives content
6. Subsequent users in same region -> Cache HIT
```

### CloudFront + S3 Arxitekturası

```
Users worldwide
      |
[Route 53 DNS] -> CloudFront distribution
      |
[CloudFront Edge Locations]
  |           |
[S3 Bucket]  [ALB -> Laravel App]
(static)     (dynamic API)

Behaviors:
  /static/*  -> S3 Origin (cache 1 year)
  /api/*     -> ALB Origin (cache 0, passthrough)
  /images/*  -> S3 Origin (cache 30 days)
  /*         -> ALB Origin (cache 5 min)
```

### Cloudflare Setup

```
DNS: example.com -> Cloudflare (orange cloud ON)

Page Rules:
  example.com/static/* -> Cache Level: Cache Everything, Edge TTL: 1 month
  example.com/api/*    -> Cache Level: Bypass
  example.com/admin/*  -> Cache Level: Bypass, Security Level: High

Cache Rules:
  If URI path starts with /assets -> Cache, Edge TTL 1 year
  If URI path starts with /wp-content/uploads -> Cache, Edge TTL 7 days
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Mix/Vite Asset Versioning

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Content hash in filename for cache busting
        rollupOptions: {
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]',
            },
        },
    },
});
```

### CDN URL Configuration

```php
// .env
ASSET_URL=https://cdn.example.com
AWS_CLOUDFRONT_URL=https://d1234.cloudfront.net

// config/app.php
'asset_url' => env('ASSET_URL', null),

// Blade template-lərdə
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
{{-- Output: https://cdn.example.com/css/app-abc123.css --}}

<img src="{{ asset('images/logo.png') }}">
```

### S3 + CloudFront Integration

```php
// config/filesystems.php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-1'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_CLOUDFRONT_URL'), // CDN URL
    ],
],
```

```php
// app/Services/AssetService.php
namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Aws\CloudFront\CloudFrontClient;

class AssetService
{
    public function uploadWithCDN(string $path, $file): string
    {
        // S3-ə yüklə
        $storedPath = Storage::disk('s3')->put($path, $file, [
            'CacheControl' => 'public, max-age=31536000', // 1 year
            'ContentType' => $file->getMimeType(),
        ]);

        // CDN URL qaytarır
        return Storage::disk('s3')->url($storedPath);
    }

    public function invalidateCache(array $paths): void
    {
        $client = new CloudFrontClient([
            'version' => 'latest',
            'region' => config('services.cloudfront.region'),
        ]);

        $client->createInvalidation([
            'DistributionId' => config('services.cloudfront.distribution_id'),
            'InvalidationBatch' => [
                'Paths' => [
                    'Quantity' => count($paths),
                    'Items' => $paths, // ['/images/logo.png', '/css/*']
                ],
                'CallerReference' => uniqid(),
            ],
        ]);
    }
}
```

### Cache Headers Middleware

```php
// app/Http/Middleware/SetCacheHeaders.php
namespace App\Http\Middleware;

use Closure;

class SetCacheHeaders
{
    public function handle($request, Closure $next, string $type = 'public')
    {
        $response = $next($request);

        if (!$request->isMethod('GET')) {
            return $response;
        }

        $headers = match ($type) {
            'static' => [
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ],
            'public' => [
                'Cache-Control' => 'public, max-age=300, s-maxage=600',
            ],
            'private' => [
                'Cache-Control' => 'private, no-cache',
            ],
            'none' => [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        };

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}

// routes/web.php
Route::middleware('cache.headers:public')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});
```

## Real-World Nümunələr

**Netflix:** Open Connect - öz CDN şəbəkəsi. ISP-lərin data center-lərinə
server quraşdırır. Video content gecə saatlarında pre-positioned olunur.
Dünya internet trafikinin ~15%-ni təşkil edir.

**Spotify:** Google Cloud CDN + öz edge caching. Audio faylları regionlara görə
cache edir. Popular mahnılar hər edge-də, nadir mahnılar origin-dən.

**Shopify:** Cloudflare ilə bütün merchant mağazalarını qoruyur. Static asset-lər
aggressive cache, API-lər bypass. Black Friday trafikini CDN absorb edir.

## Interview Sualları

**S: CDN necə işləyir?**
C: DNS anycast routing ilə istifadəçini ən yaxın edge server-ə yönləndirir.
Edge server cache-ində content varsa qaytarır (HIT), yoxdursa origin-dən
alıb cache edir (MISS). TTL və cache headers ilə freshness idarə olunur.

**S: Pull vs Push CDN fərqi?**
C: Pull-da content yalnız ilk request zamanı origin-dən çəkilir, avtomatikdir.
Push-da content əvvəlcədən bütün edge-lərə yüklənir, manual idarə olunur.
Pull dinamik content üçün, Push böyük static fayllar üçün yaxşıdır.

**S: CDN cache invalidation necə edilir?**
C: 1) TTL expire - ən sadə, 2) API ilə purge - specific URL, 3) Versioned URLs -
filename-ə hash əlavə (app-abc123.js), 4) Cache tags - qrup halında invalidate.
Versioned URL ən etibarlı yoldur çünki browser cache-i də keçir.

**S: CDN security risklər?**
C: Sensitive data cache oluna bilər (private content). Origin IP expose ola bilər.
SSL/TLS düzgün konfiqurasiya olunmalı. Cache poisoning attack mümkündür. Həll:
Cache-Control headers, signed URLs, WAF integration.

## Best Practices

1. **Versioned file names** - Cache busting üçün hash istifadə edin, manual purge lazım olmur
2. **Long TTL for static** - JS, CSS, images üçün 1 il TTL, filename hash ilə
3. **Short TTL for HTML** - HTML/API üçün qısa TTL (5-60 dəqiqə)
4. **Compression aktiv edin** - gzip/brotli CDN-dən serve olunsun
5. **HTTPS everywhere** - Origin ilə CDN arasında da HTTPS
6. **Custom error pages** - Origin down olduqda CDN stale content və ya custom 503 göstərsin
7. **Origin shield** - Bir neçə edge-dən origin-ə gələn traffic-i azaltmaq üçün mid-tier cache
8. **Monitor CDN metrics** - Hit ratio, bandwidth, latency, error rate izləyin
