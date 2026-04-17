# CORS (Cross-Origin Resource Sharing)

## Nədir? (What is it?)

CORS brauzerin tehlukesizlik mexanizmidir. Default olaraq brauzer bir origin-den (meselen, `app.com`) basqa origin-e (meselen, `api.com`) AJAX request gondermesine icaze vermir. Bu **Same-Origin Policy (SOP)** adlanir. CORS bu qadagani kontrol olunan sekilde aradan qaldirmaga imkan verir.

```
Same-Origin Policy:
  https://app.com  --->  https://app.com/api/users     ✓ (eyni origin)
  https://app.com  --->  https://api.com/users          ✗ (ferqli origin!)
  https://app.com  --->  http://app.com/api/users       ✗ (ferqli scheme!)
  https://app.com  --->  https://app.com:8080/users     ✗ (ferqli port!)

Origin = scheme + host + port
  https://app.com:443  (scheme=https, host=app.com, port=443)
```

## Necə İşləyir? (How does it work?)

### Simple Request (Preflight yoxdur)

```
Conditions (hamisinin olmalıdır):
  - Method: GET, HEAD, POST
  - Headers: yalniz "safe" headerlar
  - Content-Type: application/x-www-form-urlencoded, multipart/form-data, text/plain

Browser (app.com)                    API Server (api.com)
    |                                      |
    |--- GET /api/users ------------------>|
    |    Origin: https://app.com           |
    |                                      |
    |<-- 200 OK ----------------------------|
    |    Access-Control-Allow-Origin:       |
    |    https://app.com                   |
    |                                      |
    Browser: Origin icaze verilmis ✓, response-u goster
```

### Preflight Request (OPTIONS)

```
Ne vaxt preflight olur?
  - PUT, PATCH, DELETE method
  - Custom headerlar (Authorization, X-Custom-Header)
  - Content-Type: application/json

Browser (app.com)                    API Server (api.com)
    |                                      |
    |--- OPTIONS /api/users -------------->|  (Preflight)
    |    Origin: https://app.com           |
    |    Access-Control-Request-Method:     |
    |      DELETE                           |
    |    Access-Control-Request-Headers:    |
    |      Authorization, Content-Type     |
    |                                      |
    |<-- 204 No Content -------------------|
    |    Access-Control-Allow-Origin:       |
    |      https://app.com                 |
    |    Access-Control-Allow-Methods:      |
    |      GET, POST, PUT, DELETE          |
    |    Access-Control-Allow-Headers:      |
    |      Authorization, Content-Type     |
    |    Access-Control-Max-Age: 86400     |
    |                                      |
    Browser: Preflight OK ✓                |
    |                                      |
    |--- DELETE /api/users/42 ------------>|  (Real request)
    |    Origin: https://app.com           |
    |    Authorization: Bearer token       |
    |                                      |
    |<-- 200 OK ----------------------------|
    |    Access-Control-Allow-Origin:       |
    |      https://app.com                 |
```

### CORS Headers

```
Response Headers (Server gonderir):

Access-Control-Allow-Origin: https://app.com    # Hansi origin-lere icaze
Access-Control-Allow-Origin: *                   # Butun origin-ler (credentials ile islemir)

Access-Control-Allow-Methods: GET, POST, PUT, DELETE   # Hansi methodlara icaze
Access-Control-Allow-Headers: Authorization, Content-Type  # Hansi headerlara icaze
Access-Control-Expose-Headers: X-Total-Count           # Client-in gore bileceyi headerlar
Access-Control-Allow-Credentials: true                 # Cookies gondermek ucun
Access-Control-Max-Age: 86400                          # Preflight cache muddeti (saniye)

Request Headers (Browser avtomatik gonderir):

Origin: https://app.com                                # Client-in origini
Access-Control-Request-Method: DELETE                  # Istifade edecek method
Access-Control-Request-Headers: Authorization          # Istifade edecek headerlar
```

### Credentials (Cookies, Auth Headers)

```
# Cookie gondermek ucun her iki teref razi olmalidir:

Client (JavaScript):
  fetch('https://api.com/data', {
    credentials: 'include'    // Cookie-leri gonder
  });

Server Response:
  Access-Control-Allow-Origin: https://app.com   // * OLMAZ!
  Access-Control-Allow-Credentials: true         // lazimdir

Qayda: credentials=true olanda Allow-Origin: * ola bilmez!
       Konkret origin gosterilmelidir.
```

## Əsas Konseptlər (Key Concepts)

### Same-Origin Policy (SOP)

```
SOP nece isleyir:
  ✓ <img src="https://other.com/photo.jpg">      (Load olunur)
  ✓ <script src="https://cdn.com/lib.js">         (Load olunur)
  ✓ <link href="https://cdn.com/style.css">       (Load olunur)
  ✗ fetch('https://api.com/data')                  (CORS lazimdir!)
  ✗ XMLHttpRequest to different origin             (CORS lazimdir!)

SOP yalniz JavaScript-den olan request-lere aiddir.
HTML tag-lar (img, script, link, form) cross-origin isleyir.
```

### Common CORS Errors

```
1. "No 'Access-Control-Allow-Origin' header"
   -> Server CORS headerlari gondermiyir

2. "Origin 'X' is not allowed"
   -> Server bu origin-e icaze vermir

3. "Credentials flag is true, but Allow-Origin is '*'"
   -> Credentials ile * istifade ede bilmezsiniz

4. "Method DELETE is not allowed"
   -> Server Allow-Methods-de DELETE yoxdur

5. "Header 'Authorization' is not allowed"
   -> Server Allow-Headers-de Authorization yoxdur
```

## PHP/Laravel ilə İstifadə

### Laravel CORS Configuration

```php
// config/cors.php (Laravel 7+ built-in)
return [
    /*
     * Hansi path-lara CORS tetbiq olunacaq
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
     * Hansi HTTP methodlara icaze
     */
    'allowed_methods' => ['*'],
    // ve ya: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * Hansi origin-lere icaze
     */
    'allowed_origins' => [
        'https://app.example.com',
        'https://admin.example.com',
    ],
    // Development ucun: ['*'] ve ya ['http://localhost:3000']

    /*
     * Pattern ile origin matching
     */
    'allowed_origins_patterns' => [
        '#^https://.*\.example\.com$#',  // butun subdomain-ler
    ],

    /*
     * Hansi request headerlara icaze
     */
    'allowed_headers' => ['*'],
    // ve ya: ['Content-Type', 'Authorization', 'X-Requested-With'],

    /*
     * Client-in gore bileceyi response headerlar
     */
    'exposed_headers' => ['X-Total-Count', 'X-Page-Count'],

    /*
     * Preflight cache muddeti (saniye)
     */
    'max_age' => 86400, // 24 saat

    /*
     * Credentials (cookies) gondermek ucun
     */
    'supports_credentials' => true, // SPA ucun true
];
```

### Custom CORS Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomCors
{
    private array $allowedOrigins = [
        'https://app.example.com',
        'https://admin.example.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        // Origin icaze verilib mi yoxla
        if (!$origin || !in_array($origin, $this->allowedOrigins)) {
            if ($request->isMethod('OPTIONS')) {
                return response('', 403);
            }
            return $next($request);
        }

        // Preflight request
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        // Normal request
        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Expose-Headers', 'X-Total-Count');
    }
}
```

### Dynamic Origin (Multi-tenant)

```php
namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class TenantCors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        if ($origin) {
            // DB-den icaze verilmis origin-leri yoxla
            $allowed = Tenant::where('allowed_origin', $origin)
                ->where('is_active', true)
                ->exists();

            if ($allowed) {
                $response = $request->isMethod('OPTIONS')
                    ? response('', 204)
                    : $next($request);

                return $response
                    ->header('Access-Control-Allow-Origin', $origin)
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                    ->header('Access-Control-Allow-Credentials', 'true')
                    ->header('Vary', 'Origin');
            }
        }

        return $next($request);
    }
}
```

### Sanctum SPA Authentication (CORS + CSRF)

```php
// SPA authentication ucun Sanctum CORS setup:

// .env
SANCTUM_STATEFUL_DOMAINS=localhost:3000,app.example.com
SESSION_DOMAIN=.example.com

// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'supports_credentials' => true,
'allowed_origins' => ['https://app.example.com'],

// JavaScript (SPA)
// 1. CSRF cookie al
await fetch('https://api.example.com/sanctum/csrf-cookie', {
    credentials: 'include',
});

// 2. Login
await fetch('https://api.example.com/login', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
});

// 3. API calls (cookie avtomatik gonderilir)
const response = await fetch('https://api.example.com/api/users', {
    credentials: 'include',
});
```

## Interview Sualları

### 1. CORS nedir ve niye lazimdir?
**Cavab:** CORS brauzerin tehlukesizlik mexanizmidir. Same-Origin Policy bir origin-den basqa origin-e AJAX request-i bloklayir. CORS server-in hansi origin-lere icaze verdiyini bildiren HTTP headerlari ile bu qadagani kontrol olunan sekilde aradan qaldirir.

### 2. Preflight request nedir?
**Cavab:** OPTIONS methodu ile gonderilen avtomatik brauzer request-idir. "Simple" olmayan request-lerden evvel (DELETE, custom headers, JSON content-type) brauzer servere sorusur: "Bu methoda icaze verirsen?" Server icaze verirse real request gonderilir.

### 3. Same-Origin Policy nedir?
**Cavab:** Brauzerin tehlukesizlik siyasetidir. Bir origin-den (scheme+host+port) basqa origin-e JavaScript ile request gondermek qadagandir. Bu XSS hucumlarinin tesirini azaldir. HTML taglari (img, script) bu qadagaya uygun deyil.

### 4. `Access-Control-Allow-Origin: *` ne vaxt istifade etmek olar?
**Cavab:** Public API-lar ucun (her kes erise biler) ve credentials lazim olmayanda. `Allow-Credentials: true` ile `*` istifade etmek OLMAZ - konkret origin lazimdir.

### 5. CORS yalniz brauzer tehlukesizliyi mi dir?
**Cavab:** Beli! CORS yalniz brauzerde tetbiq olunur. Postman, curl, server-to-server request-ler CORS-a tabedir deyil. Server yene de oz tehlukesizlik yoxlamasini etmelidir (auth, rate limiting).

### 6. `Access-Control-Max-Age` nedir?
**Cavab:** Preflight response-un nece muddet cache olunacagini bildirir (saniye). Meselen, 86400 = 24 saat. Bu muddet erzinde brauzer eyni request ucun yeni preflight gondermez.

### 7. Credentials ile CORS nece isleyir?
**Cavab:** Client `credentials: 'include'` teyin etmeli, server `Allow-Credentials: true` gondermeli ve `Allow-Origin` konkret origin olmali (`*` olmaz). Bu cookies, authorization headers gondermek ucun lazimdir.

## Best Practices

1. **`*` istifade etmeyin** - Production-da konkret origin-ler teyin edin
2. **Credentials - konkret origin** - `Allow-Credentials: true` ile `*` olmaz
3. **Max-Age istifade edin** - Preflight request sayini azaldin
4. **Vary: Origin** - CDN/proxy ucun Origin-e gore cache
5. **Minimum method/header** - Yalniz lazim olan method ve headerlara icaze
6. **OPTIONS handler** - Preflight ucun 204 qaytarin (controller-e dusmesin)
7. **Expose-Headers** - Client-in oxumali oldugu custom headerlari expose edin
8. **Development vs Production** - Dev-de `localhost:*`, prod-da konkret domain
9. **Server-side validation** - CORS-a guvenmeyin, server-de de origin yoxlayin
10. **Nginx/Apache level** - Mumkunse web server seviyyesinde CORS qurun
