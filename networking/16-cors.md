# CORS - Cross-Origin Resource Sharing (Junior)

## İcmal

CORS brauzerin təhlükəsizlik mexanizmidir. Default olaraq brauzer bir origin-dən (məsələn, `app.com`) başqa origin-ə (məsələn, `api.com`) AJAX request göndərməsinə icazə vermir. Bu **Same-Origin Policy (SOP)** adlanır. CORS bu qadağanı kontrol olunan şəkildə aradan qaldırmağa imkan verir.

```
Same-Origin Policy:
  https://app.com  --->  https://app.com/api/users     ✓ (eyni origin)
  https://app.com  --->  https://api.com/users          ✗ (fərqli origin!)
  https://app.com  --->  http://app.com/api/users       ✗ (fərqli scheme!)
  https://app.com  --->  https://app.com:8080/users     ✗ (fərqli port!)

Origin = scheme + host + port
  https://app.com:443  (scheme=https, host=app.com, port=443)
```

## Niyə Vacibdir

SPA tətbiqləri (React, Vue) adətən fərqli bir origin-dən API-ya müraciət edir (`app.com` → `api.com`). CORS olmadan bu mümkün deyil. Yanlış CORS konfiqurasiyası ya bütün domenləri açıq qoyur (təhlükəsizlik riski) ya da frontend-in işləməməsinə səbəb olur. Laravel-in daxili CORS middleware-i bu prosesi idarə edir.

## Əsas Anlayışlar

### Simple Request (Preflight yoxdur)

```
Conditions (hamısının olmalıdır):
  - Method: GET, HEAD, POST
  - Headers: yalnız "safe" headerlar
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
    Browser: Origin icazə verilmiş ✓, response-u göstər
```

### Preflight Request (OPTIONS)

```
Nə vaxt preflight olur?
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
Response Headers (Server göndərir):

Access-Control-Allow-Origin: https://app.com    # Hansı origin-lərə icazə
Access-Control-Allow-Origin: *                   # Bütün origin-lər (credentials ilə işləmir)

Access-Control-Allow-Methods: GET, POST, PUT, DELETE   # Hansı methodlara icazə
Access-Control-Allow-Headers: Authorization, Content-Type  # Hansı headerlara icazə
Access-Control-Expose-Headers: X-Total-Count           # Client-in görə biləcəyi headerlar
Access-Control-Allow-Credentials: true                 # Cookies göndərmək üçün
Access-Control-Max-Age: 86400                          # Preflight cache müddəti (saniyə)

Request Headers (Browser avtomatik göndərir):

Origin: https://app.com                                # Client-in origini
Access-Control-Request-Method: DELETE                  # İstifadə edəcəyi method
Access-Control-Request-Headers: Authorization          # İstifadə edəcəyi headerlar
```

### Credentials (Cookies, Auth Headers)

```
# Cookie göndərmək üçün hər iki tərəf razı olmalıdır:

Client (JavaScript):
  fetch('https://api.com/data', {
    credentials: 'include'    // Cookie-ləri göndər
  });

Server Response:
  Access-Control-Allow-Origin: https://app.com   // * OLMAZ!
  Access-Control-Allow-Credentials: true         // lazımdır

Qayda: credentials=true olanda Allow-Origin: * ola bilməz!
       Konkret origin göstərilməlidir.
```

### Same-Origin Policy (SOP)

```
SOP necə işləyir:
  ✓ <img src="https://other.com/photo.jpg">      (Load olunur)
  ✓ <script src="https://cdn.com/lib.js">         (Load olunur)
  ✓ <link href="https://cdn.com/style.css">       (Load olunur)
  ✗ fetch('https://api.com/data')                  (CORS lazımdır!)
  ✗ XMLHttpRequest to different origin             (CORS lazımdır!)

SOP yalnız JavaScript-dən olan request-lərə aiddir.
HTML tag-lar (img, script, link, form) cross-origin işləyir.
```

### Common CORS Errors

```
1. "No 'Access-Control-Allow-Origin' header"
   -> Server CORS headerları göndərmir

2. "Origin 'X' is not allowed"
   -> Server bu origin-ə icazə vermir

3. "Credentials flag is true, but Allow-Origin is '*'"
   -> Credentials ilə * istifadə edə bilməzsiniz

4. "Method DELETE is not allowed"
   -> Server Allow-Methods-də DELETE yoxdur

5. "Header 'Authorization' is not allowed"
   -> Server Allow-Headers-də Authorization yoxdur
```

## Praktik Baxış

**Nə vaxt `*` istifadə etmək olar:**
- Public API-lar üçün (hər kəs ərisə bilər)
- Credentials lazım olmayanda
- Development mühitində

**Nə vaxt konkret origin lazımdır:**
- Credentials (cookie, authorization header) göndərəndə
- Production mühitində həmişə

**Trade-off-lar:**
- `Max-Age` yüksək olsa preflight request-lər azalır, amma konfiqurasiya dəyişikliyi gec aktiv olur
- Wildcard subdomain match (`*.example.com`) Laravel built-in-də pattern ilə edilir
- Multi-tenant sistemdə DB-dən origin yoxlamaq əlavə latency yaradır

**Anti-pattern-lər:**
- Production-da `*` istifadə etmək
- `Access-Control-Allow-Credentials: true` ilə `*` kombinasiyası (işləmir, amma cəhd edilir)
- CORS-u server-side təhlükəsizliyin əvəzi kimi görmək (CORS yalnız browserdədir)
- Preflight üçün `Access-Control-Max-Age` təyin etməmək (hər request-dən əvvəl preflight gedir)

## Nümunələr

### Ümumi Nümunə

SPA + API ayrı origin-dədir:

```
[React app: app.example.com] --fetch /api/users--> [API: api.example.com]
                                                         |
                               <--CORS headers + data ---|
Browser: Allow-Origin header-a baxır, origin uyğundursa response-u göstərir
```

### Kod Nümunəsi

**Laravel CORS Configuration:**

```php
// config/cors.php (Laravel 7+ built-in)
return [
    /*
     * Hansı path-lara CORS tətbiq olunacaq
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
     * Hansı HTTP methodlara icazə
     */
    'allowed_methods' => ['*'],
    // və ya: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * Hansı origin-lərə icazə
     */
    'allowed_origins' => [
        'https://app.example.com',
        'https://admin.example.com',
    ],
    // Development üçün: ['*'] və ya ['http://localhost:3000']

    /*
     * Pattern ilə origin matching
     */
    'allowed_origins_patterns' => [
        '#^https://.*\.example\.com$#',  // bütün subdomain-lər
    ],

    /*
     * Hansı request headerlara icazə
     */
    'allowed_headers' => ['*'],
    // və ya: ['Content-Type', 'Authorization', 'X-Requested-With'],

    /*
     * Client-in görə biləcəyi response headerlar
     */
    'exposed_headers' => ['X-Total-Count', 'X-Page-Count'],

    /*
     * Preflight cache müddəti (saniyə)
     */
    'max_age' => 86400, // 24 saat

    /*
     * Credentials (cookies) göndərmək üçün
     */
    'supports_credentials' => true, // SPA üçün true
];
```

**Custom CORS Middleware:**

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

        // Origin icazə verilib mi yoxla
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

**Dynamic Origin (Multi-tenant):**

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
            // DB-dən icazə verilmiş origin-ləri yoxla
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

**Sanctum SPA Authentication (CORS + CSRF):**

```php
// SPA authentication üçün Sanctum CORS setup:

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

// 3. API calls (cookie avtomatik göndərilir)
const response = await fetch('https://api.example.com/api/users', {
    credentials: 'include',
});
```

## Praktik Tapşırıqlar

1. **CORS error reproduce:** React (localhost:3000) → Laravel API (localhost:8000) müraciəti edin. Browser console-da CORS error-u göstərin. `config/cors.php`-ə `localhost:3000` əlavə edərək həll edin.

2. **Preflight test:** `DELETE /api/users/1` request-i göndərin. Browser DevTools Network tabında OPTIONS request-i tapın. Preflight response header-larını yoxlayın.

3. **Credentials + CORS:** Sanctum cookie auth qurun. `credentials: 'include'` ilə fetch edin. `*` yerinə konkret origin yazın — fərqi görün.

4. **Custom middleware:** `TenantCors` middleware-ini implement edin. DB-dən tenant-in allowed_origin-ini oxuyun. `Vary: Origin` header-ini əlavə edin (CDN caching üçün vacibdir).

5. **Max-Age test:** `max_age: 0` ilə preflight-ın hər request-dən əvvəl getdiyini göstərin. Sonra `max_age: 86400` qoyun — eyni endpoint üçün ikinci request-dən preflight gəlmədiyini browser DevTools-da yoxlayın.

6. **Pattern matching:** `allowed_origins_patterns` ilə `*.example.com` pattern-i konfiqurasiya edin. `sub1.example.com` və `sub2.example.com`-dan request-lərin işlədiyini yoxlayın.

## Əlaqəli Mövzular

- [HTTPS/SSL/TLS](06-https-ssl-tls.md)
- [API Security](17-api-security.md)
- [OAuth 2.0](14-oauth2.md)
- [JWT - JSON Web Token](15-jwt.md)
- [REST API](08-rest-api.md)
