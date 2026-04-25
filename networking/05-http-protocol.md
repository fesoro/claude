# HTTP Protocol (Junior)

## İcmal

HTTP (HyperText Transfer Protocol) Application Layer-də işləyən request-response protokoldur. Web-in əsasını təşkil edir. Client (browser) request göndərir, server response qaytarır. HTTP stateless-dir — hər request müstəqildir.

Versiyalar:
- HTTP/1.0 (1996) — Hər request üçün yeni connection
- HTTP/1.1 (1997) — Persistent connections, pipelining
- HTTP/2 (2015) — Binary, multiplexing, server push
- HTTP/3 (2022) — QUIC (UDP-based), 0-RTT

## Niyə Vacibdir

Backend developer-in gündəlik işi HTTP üzərindədir: status kodunu düzgün seçmək, caching header-lərini konfiqurasiya etmək, HTTP/2 üstünlüklərindən faydalanmaq, PUT ilə PATCH-in fərqini bilmək. Laravel-in request/response sikli, API design-ı, auth middleware-lər — hamısı HTTP semantikasına söykənir. Performance optimizasiyasında HTTP versiyasının rolu var: HTTP/2 multiplexing, header compression production fərqi yaradır.

## Əsas Anlayışlar

### HTTP/1.0

```
Hər request üçün ayrı TCP connection:

Client          Server
  |-- TCP SYN ---->|
  |<- TCP SYN+ACK -|
  |-- TCP ACK ---->|
  |-- GET /a ----->|
  |<-- Response ---|
  |-- TCP FIN ---->|   Connection bağlanır
  |                |
  |-- TCP SYN ---->|   Yeni connection
  |<- TCP SYN+ACK -|
  |-- TCP ACK ---->|
  |-- GET /b ----->|
  |<-- Response ---|
  |-- TCP FIN ---->|   Yenə bağlanır
```

Problem: Hər request üçün TCP handshake (1.5 RTT overhead).

### HTTP/1.1

```
Persistent connection (keep-alive):

Client          Server
  |-- TCP SYN ---->|
  |<- TCP SYN+ACK -|
  |-- TCP ACK ---->|
  |-- GET /a ----->|
  |<-- Response ---|
  |-- GET /b ----->|   Eyni connection
  |<-- Response ---|
  |-- GET /c ----->|
  |<-- Response ---|
  |-- TCP FIN ---->|

Pipelining (theoretically):
  |-- GET /a ----->|
  |-- GET /b ----->|   Request-lər sıralama ilə göndərilir
  |-- GET /c ----->|
  |<-- Response a -|   Amma response-lar SIRALI olmalıdır
  |<-- Response b -|   (Head-of-line blocking!)
  |<-- Response c -|
```

Key features:
- `Connection: keep-alive` (default)
- `Host` header məcburidir (virtual hosting)
- Chunked transfer encoding
- Range requests (partial content)
- Cache control headers

Head-of-line blocking problemi: Response-lar sıra ilə gəlməlidir. Əgər /a yavaşsa, /b və /c hazır olsa da gözləyir.

Workaround: Browsers 6-8 parallel TCP connection açır eyni host-a.

### HTTP/2

```
Binary framing, multiplexing:

Client                Server
  |                     |
  |  Stream 1: GET /a   |
  |  Stream 3: GET /b   |   Eyni TCP connection üzərində
  |  Stream 5: GET /c   |   multiple parallel streams
  |                     |
  |  <-- Stream 3: /b   |   Response-lar istənilən sırada
  |  <-- Stream 1: /a   |   gələ bilər!
  |  <-- Stream 5: /c   |

Binary frames:
+-----------------------------------------------+
|                 Length (24)                     |
+---------------+---------------+---------------+
|   Type (8)    |   Flags (8)   |
+-+-------------+---------------+
|R|                 Stream ID (31)               |
+=+=============================================+
|                   Frame Payload                |
+-----------------------------------------------+
```

Key features:
- **Binary protocol** (HTTP/1.x text-based idi)
- **Multiplexing:** Bir TCP connection üzərində parallel request/response
- **Header compression (HPACK):** Təkrarlanan header-lər compress olunur
- **Server Push:** Server client soramamış resource-ları proaktiv göndərə bilər
- **Stream prioritization:** Mühüm resource-lara prioritet vermək

Problem: TCP level-də hala head-of-line blocking var. Bir TCP packet itsə, bütün stream-lər gözləyir.

### HTTP/3 (QUIC)

```
HTTP/3 stack:
+------------------+
|     HTTP/3       |
+------------------+
|      QUIC        |  <- UDP üzərində, TLS 1.3 built-in
+------------------+
|      UDP         |
+------------------+
|      IP          |
+------------------+

Connection establishment:
HTTP/1.1 + TLS 1.2:  3 RTT (TCP + TLS)
HTTP/2 + TLS 1.3:    2 RTT (TCP + TLS)
HTTP/3 (QUIC):       1 RTT (new) / 0-RTT (reconnection)
```

Key features:
- **No head-of-line blocking:** Hər stream müstəqil. Bir stream-də packet loss digərlərinə təsir etmir.
- **0-RTT reconnection:** Əvvəlki connection-dan cached credentials ilə dərhal data göndərmək
- **Connection migration:** Wi-Fi-dan mobile-a keçəndə connection qorunur (Connection ID-yə əsaslanır, IP-yə yox)
- **Built-in TLS 1.3**

### HTTP Methods

```
+--------+-------------+------------+------------+----------+
| Method | Purpose     | Idempotent | Safe       | Body     |
+--------+-------------+------------+------------+----------+
| GET    | Read        | Yes        | Yes        | No*      |
| POST   | Create      | No         | No         | Yes      |
| PUT    | Replace     | Yes        | No         | Yes      |
| PATCH  | Partial upd | No*        | No         | Yes      |
| DELETE | Delete      | Yes        | No         | No*      |
| HEAD   | Headers only| Yes        | Yes        | No       |
| OPTIONS| Capabilities| Yes        | Yes        | No       |
+--------+-------------+------------+------------+----------+

* GET technically can have body but usually doesn't
* PATCH can be idempotent depending on implementation
* DELETE can have body but usually doesn't

Idempotent: Eyni request-i N dəfə göndərmək 1 dəfə göndərməklə eyni nəticəni verir
Safe: Server state-ini dəyişmir (read-only)
```

### PUT vs PATCH

```
PUT: Tam resource replacement
PUT /users/1
{
    "name": "Orkhan",
    "email": "orkhan@example.com",
    "age": 28
}
// Bütün field-lər göndərilməlidir, göndərilməyən field-lər silinir

PATCH: Qismən update
PATCH /users/1
{
    "age": 29
}
// Yalnız dəyişən field göndərilir
```

### HTTP Status Codes

```
1xx - Informational
  100 Continue          - Body göndər, header OK-dur
  101 Switching Proto   - WebSocket upgrade
  103 Early Hints       - Preload hints

2xx - Success
  200 OK                - Uğurlu request
  201 Created           - Resource yaradıldı (POST)
  204 No Content        - Uğurlu, amma body yoxdur (DELETE)
  206 Partial Content   - Range request cavabı

3xx - Redirection
  301 Moved Permanently - URL daimi dəyişdi (GET-ə çevrilir)
  302 Found             - Müvəqqəti redirect (GET-ə çevrilə bilər)
  304 Not Modified      - Cache istifadə et
  307 Temporary Redirect- Method saxlanılır
  308 Permanent Redirect- Method saxlanılır (301-in düzgün versiyası)

4xx - Client Error
  400 Bad Request       - Yanlış request format
  401 Unauthorized      - Authentication lazımdır
  403 Forbidden         - Authentication var amma icazə yoxdur
  404 Not Found         - Resource tapılmadı
  405 Method Not Allowed- Bu method dəstəklənmir
  409 Conflict          - Resource conflict (e.g., duplicate)
  413 Payload Too Large - Body çox böyükdür
  422 Unprocessable     - Validation error
  429 Too Many Requests - Rate limit aşıldı

5xx - Server Error
  500 Internal Server   - Server xətası
  502 Bad Gateway       - Upstream server error
  503 Service Unavail   - Server müvəqqəti məşğul
  504 Gateway Timeout   - Upstream timeout
```

### HTTP Headers

```
Request Headers:
  Host: example.com                    // Məcburi (HTTP/1.1)
  Accept: application/json             // İstənilən response format
  Content-Type: application/json       // Body formatı
  Authorization: Bearer <token>        // Auth credentials
  User-Agent: Mozilla/5.0...           // Client info
  Accept-Encoding: gzip, deflate, br   // Compression
  Cookie: session=abc123               // Cookies
  If-None-Match: "etag123"            // Conditional request
  Cache-Control: no-cache              // Cache directive

Response Headers:
  Content-Type: application/json       // Response format
  Content-Length: 1234                 // Body size
  Set-Cookie: session=abc123; Path=/  // Set cookie
  Cache-Control: max-age=3600         // Cache 1 saat
  ETag: "etag123"                     // Resource version
  Location: /users/1                  // Redirect URL
  X-RateLimit-Remaining: 99          // Rate limit info
  Access-Control-Allow-Origin: *      // CORS
```

### Cookies

```
Server sets cookie:
HTTP/1.1 200 OK
Set-Cookie: session=abc123; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age=3600

Cookie attributes:
  HttpOnly    - JavaScript ilə əlçatan deyil (XSS protection)
  Secure      - Yalnız HTTPS üzərindən göndərilir
  SameSite    - CSRF protection (Strict/Lax/None)
  Max-Age     - Cookie ömrü (seconds)
  Expires     - Cookie bitmə tarixi
  Domain      - Cookie hansı domain-ə aiddir
  Path        - Cookie hansı path-da göndərilir
```

### HTTP Request/Response Lifecycle

```
1. DNS Resolution:     example.com -> 93.184.216.34
2. TCP Connection:     3-way handshake
3. TLS Handshake:      Certificate exchange, key agreement
4. HTTP Request:       GET /api/users HTTP/1.1
5. Server Processing:  Route -> Middleware -> Controller -> Response
6. HTTP Response:      200 OK + body
7. Connection:         Keep-alive or close
```

### Content Negotiation

```
Client:
  Accept: application/json, text/html;q=0.9, */*;q=0.8
  Accept-Language: az, en;q=0.9, tr;q=0.8
  Accept-Encoding: gzip, br

Server:
  Content-Type: application/json; charset=utf-8
  Content-Language: en
  Content-Encoding: gzip
```

### Caching

```
Cache-Control directives:
  public              - CDN/proxy cache edə bilər
  private             - Yalnız browser cache edir
  no-cache            - Cache edə bilər amma hər dəfə revalidate et
  no-store            - Heç bir yerdə cache etmə
  max-age=3600        - 1 saat fresh qalır
  s-maxage=7200       - Shared cache (CDN) üçün 2 saat
  must-revalidate     - Stale olduqda mütləq revalidate et
  immutable           - Heç vaxt dəyişmir

Conditional requests:
  ETag:          If-None-Match: "abc123"   -> 304 Not Modified
  Last-Modified: If-Modified-Since: <date> -> 304 Not Modified
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- API dizaynında düzgün HTTP method + status code seçimi client developer-ə aydın semantika verir
- `Cache-Control: public, max-age=86400, immutable` static assets üçün CDN-in keşləməsini təmin edir
- `ETag` + `If-None-Match` API response-larını keşləyib bandwidth xərclərini azaldır

**Trade-off-lar:**
- HTTP/1.1 birdə-bir, sadədir amma parallel request-lər üçün domain sharding lazımdır
- HTTP/2 multiplexing domain sharding-i lazımsız edir; amma server push praktikada az istifadə olunur
- HTTP/3 (QUIC) yüksək latency şəraitdə (mobil, WiFi switching) daha sürətlidir amma middleware support hələ tam deyil

**Common mistakes:**
- GET-də side effect yaratmaq (GET cache olunur, CDN-lər tərəfindən proxy olunur)
- 200 + `{"error": "not found"}` qaytarmaq — status code semantikasını pozur
- POST-u update üçün istifadə etmək — idempotency itirilir
- DELETE-dən sonra 200 əvəzinə 204 qaytarmamaq

**Anti-pattern:** Hər şeyi 500 ilə cavablandırmaq — client 4xx ilə 5xx-i ayırd edə bilmir, retry strategiyası düzgün işləmir.

## Nümunələr

### Ümumi Nümunə

Browser `https://api.example.com/users/1` üçün request edir:
1. DNS: `api.example.com` → IP alır
2. TCP 3-way handshake
3. TLS handshake (TLS 1.3: 1 RTT)
4. `GET /users/1 HTTP/2` göndərilir — eyni connection üzərindən
5. Server `200 OK` + JSON body qaytarır
6. Connection `keep-alive`-da saxlanır (HTTP/2 multiplexing)

### Kod Nümunəsi

Laravel HTTP Client:

```php
use Illuminate\Support\Facades\Http;

// GET request
$response = Http::get('https://api.example.com/users');
$response->json();        // Parse JSON
$response->status();      // 200
$response->successful();  // true (2xx)
$response->ok();          // true (200)

// POST with JSON body
$response = Http::post('https://api.example.com/users', [
    'name' => 'Orkhan',
    'email' => 'orkhan@example.com',
]);

// PUT
$response = Http::put('https://api.example.com/users/1', [
    'name' => 'Orkhan Updated',
]);

// PATCH
$response = Http::patch('https://api.example.com/users/1', [
    'name' => 'Orkhan Patched',
]);

// DELETE
$response = Http::delete('https://api.example.com/users/1');

// With headers and authentication
$response = Http::withHeaders([
        'X-Custom-Header' => 'value',
    ])
    ->withToken('my-api-token')          // Bearer token
    ->timeout(30)                         // 30 second timeout
    ->retry(3, 100)                       // 3 retries, 100ms delay
    ->get('https://api.example.com/data');

// Concurrent requests
$responses = Http::pool(fn (Pool $pool) => [
    $pool->get('https://api.example.com/users'),
    $pool->get('https://api.example.com/posts'),
    $pool->get('https://api.example.com/comments'),
]);

$users = $responses[0]->json();
$posts = $responses[1]->json();
```

Laravel Response:

```php
// Various response types
return response()->json(['name' => 'Orkhan'], 200);
return response()->json(['error' => 'Not Found'], 404);
return response('', 204);  // No Content
return response()->download($pathToFile);
return response()->stream(function () { /* ... */ }, 200, $headers);
return redirect('/dashboard', 302);
return redirect()->route('users.show', ['user' => 1]);

// Setting headers
return response('Hello')
    ->header('Content-Type', 'text/plain')
    ->header('X-Custom', 'value')
    ->cookie('name', 'value', 60);  // 60 minutes

// Cache headers
return response()->json($data)
    ->header('Cache-Control', 'public, max-age=3600')
    ->header('ETag', md5(json_encode($data)));
```

Laravel Request Object:

```php
public function store(Request $request)
{
    $request->method();           // 'POST'
    $request->url();              // 'https://example.com/users'
    $request->fullUrl();          // includes query string
    $request->ip();               // Client IP
    $request->userAgent();        // Browser info
    $request->header('Accept');   // Request header
    $request->bearerToken();      // Bearer token
    $request->cookie('session');  // Cookie value
    $request->isJson();           // Content-Type check
    $request->expectsJson();      // Accept header check
    $request->input('name');      // Any input source
    $request->query('page');      // Query parameter
    $request->all();              // All input
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: HTTP versiyasını müəyyən edin**

```bash
# HTTP/2 support yoxlayın
curl -I --http2 https://example.com

# HTTP/3 support yoxlayın
curl -I --http3 https://example.com

# Header-ləri ətraflı görün
curl -v https://api.example.com/users 2>&1 | head -50
```

**Tapşırıq 2: Cache strategiyası tətbiq edin**

Laravel-də aşağıdakı cache strategiyasını implement edin:
- `/api/products` — 5 dəqiqə cache (public, CDN-lə)
- `/api/users/{id}` — ETag-lə conditional caching
- `/api/orders` — heç cache olunmasın (user-specific)

```php
// Products - CDN caching
return response()->json($products)
    ->header('Cache-Control', 'public, max-age=300, s-maxage=300');

// Users - ETag conditional
$etag = md5($user->updated_at->timestamp);
if ($request->header('If-None-Match') === $etag) {
    return response('', 304);
}
return response()->json($user)
    ->header('ETag', $etag)
    ->header('Cache-Control', 'private, must-revalidate');

// Orders - no cache
return response()->json($orders)
    ->header('Cache-Control', 'no-store');
```

**Tapşırıq 3: Status code audit**

Mövcud Laravel API-nızı yoxlayın:
- `store()` → 201 qaytarırmı? `Location` header-i varmı?
- `destroy()` → 204 qaytarırmı?
- Validation xətası → 422 qaytarırmı?
- Tapılmayan resource → 404 qaytarırmı?

## Əlaqəli Mövzular

- [HTTPS, SSL/TLS](06-https-ssl-tls.md)
- [REST API](08-rest-api.md)
- [HTTP/3 & QUIC](31-http3-quic.md)
- [CORS](16-cors.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Network Timeouts](42-network-timeouts.md)
