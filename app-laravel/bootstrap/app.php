<?php

use App\Http\Resources\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Middleware\EnsureApiVersion;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Src\Payment\Infrastructure\CircuitBreaker\CircuitBreakerOpenException;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\EntityNotFoundException;
use Src\Shared\Domain\Exceptions\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * API MİDDLEWARE QRUPU KONFİQURASİYASI
         * ======================================
         * appendToGroup('api', [...]) — "api" middleware qrupuna əlavə middleware-lər daxil edir.
         *
         * MİDDLEWARE İCRA SIRASI:
         * Request → [ForceJsonResponse] → [EnsureApiVersion] → [throttle:api] → Controller
         *
         * 1. ForceJsonResponse — Bütün API cavablarını JSON formatına məcbur edir.
         *    Bu olmasa, xəta halında Laravel HTML cavab qaytara bilər.
         *
         * 2. EnsureApiVersion — X-API-Version header-ini oxuyur və idarə edir.
         *    Versiyalama sayəsində köhnə client-lər işləməyə davam edir.
         *
         * 3. throttle:api — Rate limiting (dəqiqədə 60 sorğu).
         *    API-ni suistifadədən qorumaq üçün sorğu sayı məhdudlaşdırılır.
         *    Limit aşılarsa → 429 Too Many Requests cavabı qaytarılır.
         *
         * NƏYƏ RATE LİMİTİNG LAZIMDIR?
         * - DDoS hücumlarından qorunma.
         * - Brute-force hücumlarının qarşısını almaq (login endpoint-i üçün).
         * - API resurslarının ədalətli bölüşdürülməsi.
         * - Serverin yüklənməsinin qarşısını almaq.
         */
        $middleware->appendToGroup('api', [
            ForceJsonResponse::class,
            EnsureApiVersion::class,
            'throttle:api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        /**
         * ═══════════════════════════════════════════════════════════════════════════════
         * QLOBAL XƏTALARİN İDARƏ EDİLMƏSİ (Global Exception Handling)
         * ═══════════════════════════════════════════════════════════════════════════════
         *
         * Exception Handler nədir?
         * ─────────────────────────
         * Exception Handler — tətbiqdə baş verən BÜTÜN xətaları mərkəzləşdirilmiş şəkildə
         * tutub işləyən mexanizmdir. Laravel-də bu mexanizm bootstrap/app.php faylında
         * withExceptions() callback-i vasitəsilə konfiqurasiya edilir.
         *
         * Qlobal xəta idarəetməsi nəyə görə vacibdir?
         * ─────────────────────────────────────────────
         * 1. KONSİSTENTLİK: Bütün API endpoint-lər eyni xəta formatında cavab qaytarır.
         *    Frontend developer heç vaxt gözlənilməz format almır.
         * 2. TƏHLÜKƏSİZLİK: Production-da texniki detallar (stack trace, SQL sorğuları)
         *    istifadəçiyə göstərilmir. Yalnız debug rejimində görünür.
         * 3. DRY PRİNSİPİ: Hər controller-da try-catch yazmaq əvəzinə, bütün xətalar
         *    bir yerdə idarə olunur. Kod təkrarı aradan qaldırılır.
         * 4. AYIRMA (Separation of Concerns): Biznes məntiqi xəta formatlaşdırmasından
         *    ayrılır. Domain layer HTTP haqqında heç nə bilmir.
         *
         * renderable() vs render() fərqi:
         * ────────────────────────────────
         * - render() metodu: Köhnə üsul. App\Exceptions\Handler sinfində override edilirdi.
         *   Laravel 11-dən əvvəl istifadə olunurdu. Böyük switch-case blokları yaranırdı.
         * - renderable() metodu: Yeni üsul. Hər exception tipi üçün ayrıca callback qeyd
         *   edilir. Daha oxunaqlı, modular və test edilə biləndir.
         *   renderable() closure null qaytardıqda, Laravel növbəti handler-ə keçir.
         *   Bu "chain of responsibility" pattern-dir — hər handler yalnız öz tipinə baxır.
         *
         * HTTP Status Kodları nəyə görə fərqlidir?
         * ─────────────────────────────────────────
         * Hər status kod fərqli məna daşıyır və frontend/client buna görə davranır:
         *   - 400 Bad Request: Sorğu formatı yanlışdır (validasiya xətaları)
         *   - 401 Unauthorized: İstifadəçi autentifikasiya olunmayıb (login tələb olunur)
         *   - 403 Forbidden: İstifadəçi login olub, amma bu əməliyyata icazəsi yoxdur
         *   - 404 Not Found: Resurs tapılmadı (yanlış URL və ya silinmiş məlumat)
         *   - 422 Unprocessable Entity: Sorğu düzgündür amma biznes qaydaları icazə vermir
         *   - 429 Too Many Requests: Rate limit aşılıb, bir müddət gözlə
         *   - 500 Internal Server Error: Server tərəfdə gözlənilməz texniki xəta
         *   - 503 Service Unavailable: Xarici xidmət əlçatmazdır (Circuit Breaker açıqdır)
         *
         * ApiResponse pattern-i ilə əlaqə:
         * ─────────────────────────────────
         * App\Http\Resources\ApiResponse sinfi standartlaşdırılmış cavab formatı təmin edir:
         *   { "success": false, "message": "...", "errors": { ... } }
         * Bu handler-də ApiResponse::error() istifadə edirik ki, xəta cavabları da
         * normal cavablarla eyni formatda olsun. Frontend developer həmişə eyni
         * strukturu gözləyə bilər — istər uğurlu, istərsə də xətalı cavab olsun.
         */

        // ─────────────────────────────────────────────────────────────────
        // 1. EntityNotFoundException — Entity ID ilə tapılmadı (404)
        // ─────────────────────────────────────────────────────────────────
        // Bu DomainException-dan əvvəl qeyd olunmalıdır çünki EntityNotFoundException
        // DomainException-un alt sinfidir. PHP-da renderable() sıra ilə yoxlanılır —
        // əgər DomainException əvvəl yazılsa, EntityNotFoundException heç vaxt tutulmaz.
        $exceptions->renderable(function (EntityNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    code: 404,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 2. ValidationException — Domain səviyyəsində validasiya xətası (400)
        // ─────────────────────────────────────────────────────────────────
        // Bu bizim öz ValidationException sinifimizdir (Src\Shared\Domain\Exceptions),
        // Laravel-in Illuminate\Validation\ValidationException-dan fərqlidir.
        // 400 Bad Request qaytarırıq çünki göndərilən məlumatlar yanlışdır.
        // errors() metodu vasitəsilə sahə-sahə xəta siyahısını qaytarırıq.
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Validation xətası',
                    errors: $e->errors(),
                    code: 400,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 3. DomainException — Biznes qaydası pozulması (422)
        // ─────────────────────────────────────────────────────────────────
        // 422 Unprocessable Entity — sorğu sintaktik olaraq düzgündür, amma
        // biznes məntiqi baxımından icra edilə bilməz.
        // Məsələn: "Stokda 3 məhsul var, 5 ədəd sifariş etmək olmaz"
        // DomainException bütün domain xətalarının əsas sinfidir. Alt siniflər
        // (EntityNotFound, Validation) yuxarıda artıq tutulub, bura çatmır.
        $exceptions->renderable(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    errors: [],
                    code: 422,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 4. AuthenticationException — Autentifikasiya olunmayıb (401)
        // ─────────────────────────────────────────────────────────────────
        // İstifadəçi login olmayıb və ya token-in vaxtı bitib.
        // 401 Unauthorized — "kim olduğunu bilmirəm, əvvəlcə login ol".
        // Frontend bu kodu aldıqda istifadəçini login səhifəsinə yönləndirir.
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Autentifikasiya tələb olunur',
                    code: 401,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 5. AuthorizationException — İcazə yoxdur (403)
        // ─────────────────────────────────────────────────────────────────
        // İstifadəçi login olub, amma bu əməliyyatı icra etmək hüququ yoxdur.
        // 403 Forbidden — "kim olduğunu bilirəm, amma buna icazən yoxdur".
        // Məsələn: Adi istifadəçi admin panelinə daxil olmaq istəyir.
        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Bu əməliyyat üçün icazəniz yoxdur',
                    code: 403,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 6. ModelNotFoundException — Eloquent model tapılmadı (404)
        // ─────────────────────────────────────────────────────────────────
        // findOrFail() və ya firstOrFail() çağırıldıqda və nəticə boş olduqda atılır.
        // Bu Infrastructure/Eloquent səviyyəsindəki xətadır (EntityNotFoundException
        // isə Domain səviyyəsindədir). İkisi də 404 qaytarır amma fərqli layerlərdəndir.
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Model sinfinin adından entity tipini çıxarırıq
                // "App\Models\Product" → "Product"
                $modelName = class_basename($e->getModel());

                return ApiResponse::error(
                    message: "{$modelName} tapılmadı",
                    code: 404,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 7. NotFoundHttpException — URL tapılmadı (404)
        // ─────────────────────────────────────────────────────────────────
        // Mövcud olmayan route-a sorğu göndərildikdə atılır.
        // ModelNotFoundException-dan fərqi: bu route səviyyəsində, o isə DB səviyyəsindədir.
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Resurs tapılmadı',
                    code: 404,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 8. TooManyRequestsHttpException — Rate Limit aşılıb (429)
        // ─────────────────────────────────────────────────────────────────
        // Throttle middleware sorğu limitini aşdıqda atılır.
        // 429 Too Many Requests — "çox tez-tez sorğu göndərirsən, bir az gözlə".
        // DDoS hücumlarının qarşısını almaq və API stabilliyini qorumaq üçün vacibdir.
        // Retry-After header avtomatik əlavə olunur.
        $exceptions->renderable(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Çox sayda sorğu göndərdiniz, zəhmət olmasa bir az gözləyin',
                    code: 429,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 9. CircuitBreakerOpenException — Xarici xidmət əlçatmaz (503)
        // ─────────────────────────────────────────────────────────────────
        // Circuit Breaker pattern "açıq" vəziyyətdə olduqda atılır.
        // Bu o deməkdir ki, xarici xidmət (məsələn ödəniş sistemi) əlçatmazdır
        // və sorğu göndərilmədən rədd edilir — xarici API-ni yükləməmək üçün.
        // 503 Service Unavailable — "xidmət müvəqqəti olaraq əlçatmazdır".
        $exceptions->renderable(function (CircuitBreakerOpenException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiResponse::error(
                    message: 'Xidmət müvəqqəti olaraq əlçatmazdır, zəhmət olmasa sonra yenidən cəhd edin',
                    code: 503,
                );
            }
        });

        // ─────────────────────────────────────────────────────────────────
        // 10. \Throwable — Gözlənilməz xətalar üçün "catch-all" handler (500)
        // ─────────────────────────────────────────────────────────────────
        // Yuxarıdakı handler-lərin heç biri tutmadıqda bu işə düşür.
        // Bu ən son müdafiə xəttidir — heç bir xəta formatlanmamış şəkildə
        // istifadəçiyə çatmamalıdır.
        //
        // PRODUCTION vs DEBUG rejimi fərqi:
        // ──────────────────────────────────
        // - Production (APP_DEBUG=false): İstifadəçiyə ümumi mesaj göstərilir.
        //   Texniki detallar (stack trace, xəta mesajı) GİZLƏDİLİR.
        //   Çünki bu məlumatlar təhlükəsizlik riski yaradır — hacker SQL strukturunu,
        //   fayl yollarını, istifadə olunan kitabxanaları öyrənə bilər.
        // - Debug (APP_DEBUG=true): Developer-ə tam xəta mesajı, stack trace,
        //   və exception sinfi göstərilir. Bu development zamanı xətanı tez tapmağa kömək edir.
        //
        // ÖNƏMLİ: Bu handler yalnız gözlənilməz texniki xətalar üçündür.
        // Biznes xətaları (DomainException) və infrastruktur xətaları yuxarıda tutulur.
        // Əgər bu handler tez-tez işə düşürsə, yuxarıda handler əlavə etmək lazımdır.
        $exceptions->renderable(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                // Debug rejimində developer üçün ətraflı məlumat
                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'Daxili server xətası baş verdi';

                $errors = config('app.debug')
                    ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray(),
                    ]
                    : null;

                return ApiResponse::error(
                    message: $message,
                    errors: $errors,
                    code: 500,
                );
            }
        });

    })->create();
