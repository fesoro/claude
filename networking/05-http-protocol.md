# HTTP Protocol

## Nədir? (What is it?)

HTTP (HyperText Transfer Protocol) Application Layer-de isleyen request-response protokoldur. Web-in esasini teskil edir. Client (browser) request gonderir, server response qaytarir. HTTP stateless-dir - her request mustaqildir.

**Versiyalar:**
- HTTP/1.0 (1996) - Her request ucun yeni connection
- HTTP/1.1 (1997) - Persistent connections, pipelining
- HTTP/2 (2015) - Binary, multiplexing, server push
- HTTP/3 (2022) - QUIC (UDP-based), 0-RTT

## Necə İşləyir? (How does it work?)

### HTTP/1.0

```
Her request ucun ayri TCP connection:

Client          Server
  |-- TCP SYN ---->|
  |<- TCP SYN+ACK -|
  |-- TCP ACK ---->|
  |-- GET /a ----->|
  |<-- Response ---|
  |-- TCP FIN ---->|   Connection baglanir
  |                |
  |-- TCP SYN ---->|   Yeni connection
  |<- TCP SYN+ACK -|
  |-- TCP ACK ---->|
  |-- GET /b ----->|
  |<-- Response ---|
  |-- TCP FIN ---->|   Yene baglanir
```

**Problemler:** Her request ucun TCP handshake (1.5 RTT overhead). Slow.

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
  |-- GET /b ----->|   Request-ler siralama ile gonderilir
  |-- GET /c ----->|
  |<-- Response a -|   Amma response-lar SIRALI olmalidir
  |<-- Response b -|   (Head-of-line blocking!)
  |<-- Response c -|
```

**Key features:**
- `Connection: keep-alive` (default)
- `Host` header mecburidir (virtual hosting)
- Chunked transfer encoding
- Range requests (partial content)
- Cache control headers

**Head-of-line blocking problemi:** Response-lar sira ile gelmalidir. Eger /a yavas ise, /b ve /c hazir olsa da gozleyir.

**Workaround:** Browsers 6-8 parallel TCP connection acir eyni host-a.

### HTTP/2

```
Binary framing, multiplexing:

Client                Server
  |                     |
  |  Stream 1: GET /a   |
  |  Stream 3: GET /b   |   Eyni TCP connection uzerinde
  |  Stream 5: GET /c   |   multiple parallel streams
  |                     |
  |  <-- Stream 3: /b   |   Response-lar istenilen sirada
  |  <-- Stream 1: /a   |   gele biler!
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

**Key features:**
- **Binary protocol** (HTTP/1.x text-based idi)
- **Multiplexing:** Bir TCP connection uzerinde parallel request/response
- **Header compression (HPACK):** Tekrarlanan header-ler compress olunur
- **Server Push:** Server client sormamihs resource-lari proaktiv gondere biler
- **Stream prioritization:** Muhum resource-lara prioritet vermek

**Problem:** TCP level-de hala head-of-line blocking var. Bir TCP packet itse, butun stream-ler gozleyir.

### HTTP/3 (QUIC)

```
HTTP/3 stack:
+------------------+
|     HTTP/3       |
+------------------+
|      QUIC        |  <- UDP uzerinde, TLS 1.3 built-in
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

**Key features:**
- **No head-of-line blocking:** Her stream musqeqil. Bir stream-de packet loss digerlerine tesir etmir.
- **0-RTT reconnection:** Evvelki connection-dan cached credentials ile derhal data gondermek
- **Connection migration:** Wi-Fi-dan mobile-a kecende connection qorunur (Connection ID-ye esaslanir, IP-ye yox)
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

Idempotent: Eyni request-i N defe gondermek 1 defe gondermekle eyni neticeni verir
Safe: Server state-ini deyismir (read-only)
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
// Butun field-ler gonderilemlidir, gonderilmeyen field-ler silinir

PATCH: Qismən update
PATCH /users/1
{
    "age": 29
}
// Yalniz deyisen field gonderilir
```

### HTTP Status Codes

```
1xx - Informational
  100 Continue          - Body gonder, header OK-dur
  101 Switching Proto   - WebSocket upgrade
  103 Early Hints       - Preload hints

2xx - Success
  200 OK                - Ugurlu request
  201 Created           - Resource yaradildi (POST)
  204 No Content        - Ugurlu, amma body yoxdur (DELETE)
  206 Partial Content   - Range request cavabi

3xx - Redirection
  301 Moved Permanently - URL daimi deyisdi (GET-e cevrilir)
  302 Found             - Muveqqeti redirect (GET-e cevrile biler)
  304 Not Modified      - Cache istifade et
  307 Temporary Redirect- Method saxlanilir
  308 Permanent Redirect- Method saxlanilir (301-in duzgun versiyasi)

4xx - Client Error
  400 Bad Request       - Yanlis request format
  401 Unauthorized      - Authentication lazimdir
  403 Forbidden         - Authentication var amma icaze yoxdur
  404 Not Found         - Resource tapilmadi
  405 Method Not Allowed- Bu method desteklenmir
  409 Conflict          - Resource conflict (e.g., duplicate)
  413 Payload Too Large - Body cox boyukdur
  422 Unprocessable     - Validation error
  429 Too Many Requests - Rate limit asildi

5xx - Server Error
  500 Internal Server   - Server xetasi
  502 Bad Gateway       - Upstream server error
  503 Service Unavail   - Server muveqqeti mesgul
  504 Gateway Timeout   - Upstream timeout
```

### HTTP Headers

```
Request Headers:
  Host: example.com                    // Mecburi (HTTP/1.1)
  Accept: application/json             // İstenilen response format
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
  HttpOnly    - JavaScript ile elcatan deyil (XSS protection)
  Secure      - Yalniz HTTPS uzerinden gonderilir
  SameSite    - CSRF protection (Strict/Lax/None)
  Max-Age     - Cookie omru (seconds)
  Expires     - Cookie bitmə tarixi
  Domain      - Cookie hansi domain-e aiddir
  Path        - Cookie hansi path-da gonderilir
```

## Əsas Konseptlər (Key Concepts)

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
  public              - CDN/proxy cache ede biler
  private             - Yalniz browser cache eder
  no-cache            - Cache ede biler amma her defə revalidate et
  no-store            - Hec bir yerde cache etme
  max-age=3600        - 1 saat fresh qalir
  s-maxage=7200       - Shared cache (CDN) ucun 2 saat
  must-revalidate     - Stale oldugda mutleq revalidate et
  immutable           - Hec vaxt deyismir

Conditional requests:
  ETag:          If-None-Match: "abc123"   -> 304 Not Modified
  Last-Modified: If-Modified-Since: <date> -> 304 Not Modified
```

## PHP/Laravel ilə İstifadə

### Laravel Request/Response Lifecycle

```
1. public/index.php (entry point)
2. Bootstrap (autoload, app instance)
3. HTTP Kernel
   - Global middleware stack
4. Router -> Route matching
5. Route middleware
6. Controller method
7. Response creation
8. Middleware (terminate)
9. Response sent to client

index.php -> Kernel -> Router -> Middleware -> Controller -> Response
```

### Laravel HTTP Client

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

### Laravel Response

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

### Laravel Request Object

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

## Interview Sualları

### Q1: HTTP/1.1 ve HTTP/2 arasinda esas ferqler nelardir?
**A:** HTTP/2: 1) Binary protocol (text evezine). 2) Multiplexing - bir TCP connection-da parallel streams. 3) Header compression (HPACK). 4) Server push. 5) Stream prioritization. HTTP/1.1-de her request sirayla ve ya parallel connection-larla islenirdi.

### Q2: HTTP/3 niye UDP istifade edir?
**A:** TCP-de head-of-line blocking var - bir packet itse butun connection-da streams gozleyir. QUIC (UDP uzerinde) her stream-i musqeqil edir. Hemcinin 0-RTT connection, connection migration, built-in TLS 1.3 verir.

### Q3: PUT ve PATCH arasinda ferq nedir?
**A:** PUT butov resource-u replace edir (butun field-ler gonderilemlidir). PATCH qismən update edir (yalniz deyisen field-ler). PUT idempotent-dir (hemise), PATCH implementation-dan asili ola biler.

### Q4: 401 ve 403 arasinda ferq nedir?
**A:** 401 Unauthorized: Authentication yoxdur ve ya invalid-dir (login lazimdir). 403 Forbidden: Authentication var amma authorization yoxdur (login olub amma icaze yoxdur).

### Q5: Idempotency nedir? Hansi HTTP methodlar idempotent-dir?
**A:** Eyni request-i N defe gondermeyin neticesi 1 defe gondermekle eyni olmasi. GET, PUT, DELETE, HEAD, OPTIONS idempotent-dir. POST idempotent deyil (her defe yeni resource yaradir). PATCH da formalliq olaraq idempotent deyil.

### Q6: HTTP caching nece isleyir?
**A:** Cache-Control header-leri ile: max-age (fresh muddet), no-cache (revalidate), no-store (cache etme). ETag/Last-Modified ile conditional requests: 304 Not Modified ile bandwidth saxlanir. CDN-ler public cache, browser private cache edir.

### Q7: Cookie-lerin SameSite attribute-u nedir?
**A:** CSRF protection: Strict - yalniz eyni site-den; Lax - top-level navigation OK (link click), cross-site POST yox; None - her yerde gonderilir (Secure flag mecburi). Default deger browser-den asilıdır (Chrome Lax default).

## Best Practices

1. **Duzgun HTTP method istifade edin:** GET read, POST create, PUT replace, PATCH update, DELETE remove. GET-de side-effect olmamalidir.

2. **Duzgun status code qaytarin:** 201 create ucun, 204 delete ucun, 422 validation ucun, 409 conflict ucun. Her sey ucun 200 ve ya 500 qaytarmayin.

3. **Cache strategy planlayin:** Static assets ucun uzun max-age + immutable. API responses ucun ETag + must-revalidate. Sensitive data ucun no-store.

4. **HTTP/2 istifade edin:** Domain sharding ve image spriting kimi HTTP/1.1 workaround-lari artiq lazim deyil. Multiplexing bunlari evez edir.

5. **Content-Type hemise set edin:** API-larda `application/json`, content negotiation ucun `Accept` header-i yoxlayin.

6. **Compression aktiv edin:** gzip ve ya brotli (br) istifade edin. Nginx-de `gzip on;` Laravel-de middleware ile.

7. **Connection keep-alive:** HTTP/1.1-de default-dur. Timeout ve max requests duzgun configure edin.
