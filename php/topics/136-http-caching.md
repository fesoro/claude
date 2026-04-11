# HTTP Caching

## Mündəricat
1. [HTTP Cache Mexanizması](#http-cache-mexanizması)
2. [Cache-Control Direktivləri](#cache-control-direktivləri)
3. [Conditional Requests](#conditional-requests)
4. [CDN Caching](#cdn-caching)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## HTTP Cache Mexanizması

```
Cache Növləri:
  Browser Cache:   İstifadəçinin brauzerində
  Proxy Cache:     Şirkət şəbəkəsi (Squid)
  CDN:             Edge serverlərdə (Cloudflare, Fastly)
  Reverse Proxy:   Serverinizdə (Nginx, Varnish)

Cache Flow:
  Client → CDN (miss) → Origin Server → CDN (store) → Client
  Client → CDN (hit)  → Client (origin server-ə sorğu yoxdur!)

Fresh vs Stale:
  Fresh:  Cache hələ etibarlıdır (max-age keçməyib)
  Stale:  Cache köhnəlib, yenilənməlidir

Heuristic Caching:
  Server Cache-Control göndərməsə:
  Browser/CDN özü qərar verir (Last-Modified əsasında)
  Tövsiyə edilmir — həmişə explicit direktivlər verin
```

---

## Cache-Control Direktivləri

```
Response direktivləri:

max-age=3600:
  Bu cavabı 3600 saniyə (1 saat) cache et
  
s-maxage=86400:
  CDN/shared cache üçün max-age
  Browser max-age istifadə edir
  CDN s-maxage istifadə edir

no-cache:
  Cache-ə al, amma istifadə etməzdən əvvəl server ilə yoxla
  (ETag/If-None-Match ilə)
  Adı yanıltıcıdır — "cache etmə" deyil!

no-store:
  Heç bir yerdə cache etmə (həssas data: bank, şəxsi məlumat)

private:
  Yalnız browser cache edə bilər (CDN cache etməsin)
  İstifadəçiyə xas cavablar üçün

public:
  CDN daxil hamı cache edə bilər

must-revalidate:
  Stale olduqda istifadə etməzdən əvvəl server ilə yoxla
  
immutable:
  Resurs heç vaxt dəyişmir (versioned assets)
  Cache-Control: public, max-age=31536000, immutable
  → Brauzer yenidən yükləmə zamanı belə server-ə sorğu atmır

Nümunə Siyasətlər:
  Static assets (hash-ləməli): max-age=31536000, immutable
  HTML page:                   no-cache (yoxla, amma cache et)
  API response:                max-age=60, s-maxage=300
  User dashboard:              private, no-cache
  Sensitive data:              no-store
```

---

## Conditional Requests

```
ETag (Entity Tag):
  Resursun "barmaq izi" — məzmun dəyişsə ETag dəyişir
  
  Server → Response: ETag: "abc123"
  Client → Request:  If-None-Match: "abc123"
  Server → 304 Not Modified (body yoxdur, bandwidth sıfır)
         → 200 OK + yeni body (dəyişibsə)

Last-Modified:
  Son dəyişiklik tarixi
  
  Server → Response: Last-Modified: Thu, 01 Jan 2026 00:00:00 GMT
  Client → Request:  If-Modified-Since: Thu, 01 Jan 2026 00:00:00 GMT
  Server → 304 Not Modified (dəyişməyibsə)

ETag vs Last-Modified:
  ETag daha dəqiqdir (sub-second dəyişikliklər)
  ETag hesablamaq bahalı ola bilər
  Hər ikisini istifadə etmək ən yaxşı praktikadır

Weak vs Strong ETag:
  Strong: "abc123"   → byte-for-byte eyni
  Weak:   W/"abc123" → semantik olaraq eyni (fərq əhəmiyyətsizdir)
```

---

## CDN Caching

```
Cache Invalidation (Purge):
  CDN-dən köhnə cache-i silmək
  
  Strategiyalar:
  1. URL-based purge: /products/123 → purge
  2. Tag-based purge: "product:123" tagi olan hamısı → purge
  3. Wildcard:        /products/* → purge
  4. TTL gözlə:      Cache öz-özünə bitəcək

Surrogate Keys / Cache Tags:
  Cloudflare Cache-Tag, Fastly Surrogate-Key
  Response header: Cache-Tag: product-123, category-electronics
  Purge: bütün "product-123" tag-li URL-lər

Stale-While-Revalidate:
  Cache-Control: max-age=60, stale-while-revalidate=600
  60s: fresh → direkt qaytar
  60-660s: stale → köhnəni qaytar, arxada yenilə
  660s+: həm stale həm revalidation keçdi → wait for fresh

Stale-If-Error:
  Cache-Control: max-age=60, stale-if-error=86400
  Origin server down olsa 24 saat köhnə data qaytar
```

---

## PHP İmplementasiyası

```php
<?php
// Symfony HTTP Cache
use Symfony\Component\HttpFoundation\Response;

class ProductController
{
    public function show(string $id): Response
    {
        $product = $this->productService->findById($id);

        $response = new Response();

        // ETag hesabla
        $etag = md5(serialize($product) . $product->getUpdatedAt()->getTimestamp());
        $response->setETag($etag);

        // Last-Modified
        $response->setLastModified($product->getUpdatedAt());

        // Cache-Control
        $response->setPublic();
        $response->setMaxAge(3600);           // browser: 1 saat
        $response->setSharedMaxAge(86400);    // CDN: 24 saat

        // Conditional request yoxla — dəyişməyibsə 304 qaytar
        if ($response->isNotModified($this->request)) {
            return $response; // 304, body yoxdur
        }

        $response->setContent(json_encode($product->toArray()));
        $response->headers->set('Content-Type', 'application/json');

        // CDN tag-ları (Cloudflare/Fastly)
        $response->headers->set(
            'Cache-Tag',
            implode(',', ["product:{$id}", "category:{$product->getCategoryId()}"])
        );

        return $response;
    }

    public function userDashboard(): Response
    {
        // Şəxsi data — yalnız browser cache
        $response = new Response();
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'private, no-cache');
        // ...
        return $response;
    }
}
```

```php
<?php
// Cache invalidation — məhsul yenilənəndə CDN purge
class ProductUpdatedHandler
{
    public function __construct(
        private CloudflarePurger $cloudflare,
    ) {}

    public function __invoke(ProductUpdatedEvent $event): void
    {
        // URL-based purge
        $this->cloudflare->purgeUrls([
            "/products/{$event->productId}",
            "/products/{$event->productId}.json",
        ]);

        // Tag-based purge (daha güclü)
        $this->cloudflare->purgeTags([
            "product:{$event->productId}",
        ]);
    }
}

// Varnish VCL əvəzinə PHP Symfony HttpCache
// kernel.php:
// return new CachingKernel(new AppKernel($env, $debug));
```

```php
<?php
// Stale-While-Revalidate Symfony ilə
class ApiController
{
    public function products(): Response
    {
        $response = new Response(json_encode($this->getProducts()));
        $response->headers->set('Content-Type', 'application/json');
        $response->setPublic();

        // 60s fresh, 600s stale-while-revalidate
        $response->headers->set(
            'Cache-Control',
            'public, max-age=60, stale-while-revalidate=600, stale-if-error=86400'
        );

        return $response;
    }
}
```

---

## İntervyu Sualları

- `no-cache` ilə `no-store` fərqi nədir?
- ETag nədir? 304 Not Modified nə zaman qaytarılır?
- `private` ilə `public` Cache-Control fərqi nədir?
- CDN cache invalidation strategiyalarını izah edin.
- `stale-while-revalidate` nəyə lazımdır?
- `s-maxage` nə zaman `max-age`-dən fərqlənir?
