<?php

/**
 * CORS KONFİQURASİYASI (Cross-Origin Resource Sharing)
 * ======================================================
 *
 * ═══════════════════════════════════════════════════════════════════
 * CORS NƏDİR?
 * ═══════════════════════════════════════════════════════════════════
 *
 * CORS — brauzerin təhlükəsizlik mexanizmidir. Bir domain-dən (origin)
 * başqa domain-ə HTTP sorğu göndərməyi idarə edir.
 *
 * PROBLEM:
 *   Frontend:  https://myapp.com (port 3000)
 *   Backend:   https://api.myapp.com (port 8000)
 *   Bunlar fərqli "origin"-dir. Brauzer defolt olaraq bunu BLOKLAYIR.
 *
 * NİYƏ BRAUZER BLOKLAYIR? (Same-Origin Policy)
 *   Təsəvvür edin: siz bank.az saytına daxil olmusunuz (session cookie var).
 *   Sonra zərərli sayt açırsınız. Əgər CORS olmasaydı, zərərli sayt
 *   JavaScript ilə bank.az API-sinə sorğu göndərə bilərdi və cookie
 *   avtomatik göndəriləcəkdi. Bu, CSRF (Cross-Site Request Forgery) hücumudur.
 *   CORS bunu qarşısını alır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * NECƏ İŞLƏYİR? — PREFLIGHT (ÖN YOXLAMA) SORĞULARİ
 * ═══════════════════════════════════════════════════════════════════
 *
 * Brauzer cross-origin sorğu göndərməzdən əvvəl avtomatik bir "ön yoxlama"
 * (preflight) sorğusu göndərir:
 *
 * ADDIM 1 — Brauzer OPTIONS sorğusu göndərir:
 *   OPTIONS /api/v1/products HTTP/1.1
 *   Origin: https://myapp.com
 *   Access-Control-Request-Method: POST
 *   Access-Control-Request-Headers: Content-Type, Authorization
 *
 * ADDIM 2 — Server CORS header-ləri ilə cavab verir:
 *   HTTP/1.1 204 No Content
 *   Access-Control-Allow-Origin: https://myapp.com
 *   Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE
 *   Access-Control-Allow-Headers: Content-Type, Authorization
 *   Access-Control-Max-Age: 3600
 *
 * ADDIM 3 — Brauzer yoxlayır:
 *   - Origin icazəli siyahıdadırmı? ✓
 *   - Method icazəlidirmi? ✓
 *   - Header-lər icazəlidirmi? ✓
 *   Hamısı uyğundursa → əsl sorğunu göndərir
 *
 * ADDIM 4 — Əsl sorğu göndərilir:
 *   POST /api/v1/products HTTP/1.1
 *   Origin: https://myapp.com
 *   Content-Type: application/json
 *   Authorization: Bearer token123
 *
 * SADƏ SORĞULAR (Simple Requests):
 *   GET, HEAD, POST sorğuları (sadə header-lərlə) preflight TƏLƏBETMİR.
 *   Amma Authorization header olduqda preflight MÜTLƏQDİR.
 *
 * ═══════════════════════════════════════════════════════════════════
 * TƏHLÜKƏSİZLİK QEYDLƏRI
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. allowed_origins-da '*' İSTİFADƏ ETMƏYİN (production-da):
 *    '*' bütün domain-lərdən sorğuya icazə verir — bu TƏHLÜKƏLİDİR.
 *    Yalnız etibar etdiyiniz domain-ləri yazın.
 *
 * 2. supports_credentials: true olduqda '*' işləmir:
 *    Brauzer təhlükəsizlik səbəbiylə buna icazə vermir.
 *    Cookie göndərmək üçün konkret origin lazımdır.
 *
 * 3. max_age — preflight sorğunu cache edir:
 *    3600 = 1 saat. Bu müddət ərzində brauzer təkrar OPTIONS göndərmir.
 *    Performans üçün vacibdir — hər sorğudan əvvəl OPTIONS göndərmək yavaşdır.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Paths — Hansı URL-lərə CORS tətbiq olunsun?
    |--------------------------------------------------------------------------
    |
    | 'api/*' — /api/ ilə başlayan bütün endpoint-lərə CORS tətbiq olunur.
    | 'health' — health check endpoint-inə də tətbiq olunur.
    |
    | Nəyə görə hər yerə yox, yalnız API-yə?
    | - Web route-lar (Blade view-lar) eyni origin-dən gəlir, CORS lazım deyil
    | - Yalnız API endpoint-ləri cross-origin sorğu alır
    */
    'paths' => ['api/*', 'health'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods — İcazəli HTTP metodları
    |--------------------------------------------------------------------------
    |
    | Bu metodlar Access-Control-Allow-Methods header-ində qaytarılır.
    |
    | GET     → Məlumat oxumaq
    | POST    → Yeni resurs yaratmaq
    | PUT     → Resursu tamamilə yeniləmək
    | PATCH   → Resursu qismən yeniləmək
    | DELETE  → Resursu silmək
    | OPTIONS → Preflight sorğusu (brauzer avtomatik göndərir)
    */
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins — İcazəli mənbə domain-ləri
    |--------------------------------------------------------------------------
    |
    | Yalnız bu domain-lərdən gələn sorğulara icazə verilir.
    |
    | PRODUCTION-DA:
    |   Yalnız öz frontend domain-lərinizi yazın.
    |   Məsələn: ['https://myapp.com', 'https://admin.myapp.com']
    |
    | DEVELOPMENT-DƏ:
    |   localhost əlavə edin: ['http://localhost:3000', 'http://localhost:5173']
    |   (Vite default: 5173, React CRA default: 3000)
    |
    | .env-dən oxumaq:
    |   env('CORS_ALLOWED_ORIGINS') ilə konfiqurasiya edilə bilər.
    |   Bu sayədə hər mühitdə (dev, staging, prod) fərqli origin-lər olar.
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns — Regex ilə origin uyğunlaşdırma
    |--------------------------------------------------------------------------
    |
    | Regex pattern ilə dinamik origin-lərə icazə vermək üçün.
    | Məsələn: Vercel preview deployment-ləri hər dəfə fərqli subdomain istifadə edir.
    |
    | 'https://*.vercel.app' → myapp-abc123.vercel.app, myapp-def456.vercel.app
    |
    | DİQQƏT: Regex çox geniş yazılmamalıdır!
    | Yanlış: 'https://*' → bütün HTTPS saytlarına icazə verir
    | Düzgün: 'https://*.myapp.vercel.app' → yalnız sizin preview-lar
    */
    'allowed_origins_patterns' => [
        // Vercel preview deployment-ləri üçün nümunə (lazım olduqda aktivləşdirin):
        // 'https://.*\.vercel\.app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers — İcazəli sorğu header-ləri
    |--------------------------------------------------------------------------
    |
    | Klient bu header-ləri göndərə bilər:
    |
    | Content-Type    → Sorğunun formatı (application/json)
    | Authorization   → JWT token (Bearer token123)
    | Accept          → Gözlənilən cavab formatı
    | X-Requested-With→ AJAX sorğu göstəricisi (jQuery istifadə edir)
    | X-API-Version   → API versiya header-i (bu proyektdə istifadə olunur)
    | X-Request-ID    → Sorğu izləmə ID-si (distributed tracing üçün)
    */
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'Accept',
        'X-Requested-With',
        'X-API-Version',
        'X-Request-ID',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers — Klientə görünən cavab header-ləri
    |--------------------------------------------------------------------------
    |
    | Brauzer defolt olaraq yalnız "sadə" cavab header-lərini JavaScript-ə göstərir.
    | Əlavə header-ləri görmək üçün burada sadalamaq lazımdır.
    |
    | X-API-Version   → Klient hansı API versiyasından cavab aldığını bilsin
    | X-Request-ID    → Debug üçün sorğu ID-si
    | X-RateLimit-*   → Rate limiting məlumatları
    */
    'exposed_headers' => [
        'X-API-Version',
        'X-Request-ID',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Age — Preflight cavabını cache etmə müddəti (saniyə)
    |--------------------------------------------------------------------------
    |
    | 3600 = 1 saat. Bu müddət ərzində brauzer eyni endpoint üçün
    | təkrar OPTIONS (preflight) sorğusu göndərmir.
    |
    | PERFORMANS TƏSİRİ:
    | Hər API çağırışından əvvəl OPTIONS göndərmək = 2x sorğu.
    | max_age ilə cache edəndə yalnız 1x sorğu göndərilir.
    |
    | Fərqli brauzerlər fərqli maksimum dəyərlər dəstəkləyir:
    | - Chrome: max 7200 (2 saat)
    | - Firefox: max 86400 (24 saat)
    | - Safari: max 604800 (7 gün)
    */
    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials — Cookie/Auth header göndərməyə icazə
    |--------------------------------------------------------------------------
    |
    | true olduqda:
    |   - Brauzer cross-origin sorğularda cookie göndərə bilər
    |   - Access-Control-Allow-Credentials: true header-i əlavə olunur
    |   - allowed_origins-da '*' istifadə etmək OLMAZ (brauzer bloklayır)
    |
    | Bu proyektdə true-dur çünki:
    |   - JWT token Authorization header-ində göndərilir
    |   - Session-based auth istifadə olunursa cookie lazımdır
    */
    'supports_credentials' => true,
];
