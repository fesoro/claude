<?php

/**
 * API ROUTES
 * ==========
 * Bütün API endpoint-ləri burada təyin olunur.
 *
 * ROUTE STRUKTURU:
 * /api/auth      → Autentifikasiya (register, login, logout, me)
 * /api/users     → User Bounded Context
 * /api/products  → Product Bounded Context
 * /api/orders    → Order Bounded Context
 * /api/payments  → Payment Bounded Context
 *
 * Hər route Controller-ə yönləndirir.
 * Controller → CommandBus/QueryBus → Handler → Domain
 *
 * QEYD: Laravel 13-də api routes avtomatik yüklənmir.
 * bootstrap/app.php-də qeydiyyat lazımdır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * MİDDLEWARE QRUPLARI:
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. PUBLİK ROUTE-LAR (middleware yoxdur):
 *    - Hər kəs daxil ola bilər, token lazım deyil.
 *    - Məsələn: Məhsul siyahısı, istifadəçi profili, login, register.
 *
 * 2. QORUNAN ROUTE-LAR (auth:sanctum middleware):
 *    - Yalnız autentifikasiya olunmuş istifadəçilər daxil ola bilər.
 *    - Hər sorğuda "Authorization: Bearer <token>" header-i tələb olunur.
 *    - Token etibarsızdırsa → 401 Unauthorized cavabı qaytarılır.
 *
 * auth:sanctum NECƏ İŞLƏYİR?
 * - Sanctum middleware "Authorization" header-indəki token-i oxuyur.
 * - Token-i personal_access_tokens cədvəlində axtarır (hash müqayisəsi).
 * - Tapılarsa → istifadəçini $request->user()-a əlavə edir.
 * - Tapılmazsa → 401 cavabı qaytarır, Controller-ə çatmır.
 *
 * ═══════════════════════════════════════════════════════════════════
 * ROUTE QRUPLAMA PRİNSİPLƏRİ:
 * ═══════════════════════════════════════════════════════════════════
 *
 * Route::prefix('auth') → URL-ə /api/auth prefiksi əlavə edir.
 * Route::middleware('auth:sanctum') → Bu qrupdakı bütün route-lar qorunur.
 * Route::group(callback) → Route-ları qrup daxilində birləşdirir.
 *
 * Qruplama üstünlükləri:
 * - DRY (Don't Repeat Yourself) — hər route-a ayrı-ayrı middleware yazmaq lazım deyil.
 * - Oxunaqlılıq — hansı route-ların qorunduğu aydın görünür.
 * - Dəyişiklik asanlığı — middleware bir yerdə dəyişilir, bütün route-lara tətbiq olunur.
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\FailedJobController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| Auth Routes — Autentifikasiya əməliyyatları
|--------------------------------------------------------------------------
| PUBLİK: register və login — token olmadan çağırıla bilər.
| QORUNAN: logout və me — yalnız etibarlı token ilə.
*/
Route::prefix('auth')->group(function () {
    /**
     * PUBLİK AUTH ROUTE-LARI:
     * Bu route-lara token lazım deyil, çünki:
     * - register: İstifadəçi hələ mövcud deyil, token-i yoxdur.
     * - login: İstifadəçi token almaq üçün daxil olur.
     */

    // POST /api/auth/register → Yeni istifadəçi qeydiyyatı + token yaradılması
    // throttle:register → dəqiqədə 3 sorğu (spam qoruması)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');

    // POST /api/auth/login → Giriş + token yaradılması
    // throttle:login → dəqiqədə 5 sorğu (brute force qoruması)
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // POST /api/auth/forgot-password → Şifrə sıfırlama emaili göndər
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    // POST /api/auth/reset-password → Yeni şifrə təyin et (token ilə)
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    /**
     * QORUNAN AUTH ROUTE-LARI:
     * auth:sanctum middleware tətbiq olunur.
     * Bu route-ları çağırmaq üçün etibarlı Bearer token lazımdır.
     */
    Route::middleware('auth:sanctum')->group(function () {
        // POST /api/auth/logout → Çıxış (cari token-i ləğv edir)
        Route::post('/logout', [AuthController::class, 'logout']);

        // GET /api/auth/me → Cari istifadəçi məlumatları
        Route::get('/me', [AuthController::class, 'me']);

        // 2FA route-ları (auth lazımdır)
        Route::prefix('2fa')->group(function () {
            Route::post('/enable', [TwoFactorController::class, 'enable']);
            Route::post('/confirm', [TwoFactorController::class, 'confirm']);
            Route::post('/disable', [TwoFactorController::class, 'disable']);
        });
    });

    // 2FA doğrulama (auth lazım deyil — login prosesinin hissəsidir)
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);
    Route::post('/2fa/verify-backup', [TwoFactorController::class, 'verifyBackup']);
});

/*
|--------------------------------------------------------------------------
| User Routes — İstifadəçi əməliyyatları (PUBLİK)
|--------------------------------------------------------------------------
| GET route-ları publik saxlanılır ki, istifadəçi profilləri
| autentifikasiya olmadan da baxıla bilsin.
| Qeydiyyat AuthController-ə köçürülüb.
*/
/*
|--------------------------------------------------------------------------
| Global Search — Bütün entity-lərdə axtarış (PUBLİK)
|--------------------------------------------------------------------------
*/
Route::get('/search', [SearchController::class, 'search']);

Route::prefix('users')->group(function () {
    // GET /api/users/{id} → İstifadəçi məlumatlarını al (Query)
    Route::get('/{id}', [UserController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Product Routes — Məhsul əməliyyatları
|--------------------------------------------------------------------------
| GET route-ları PUBLİKdir — hər kəs məhsulları görə bilər.
| POST/PATCH route-ları QORUNANdır — yalnız admin/seller yarada bilər.
|
| Bu, real e-commerce saytlarında da belədir:
| - Müştəri: Məhsulları görür (GET) — login lazım deyil.
| - Satıcı: Məhsul əlavə edir (POST) — login lazımdır.
*/
Route::prefix('products')->middleware('throttle:products')->group(function () {
    /**
     * PUBLİK MƏHSUL ROUTE-LARI:
     * Məhsul kataloqu hər kəs üçün açıqdır.
     */

    // GET /api/products → Bütün məhsulların siyahısı (Query)
    Route::get('/', [ProductController::class, 'index']);

    // GET /api/products/{id} → Tək məhsul (Query)
    Route::get('/{id}', [ProductController::class, 'show']);

    // GET /api/products/{id}/images → Məhsulun şəkilləri (PUBLİK)
    Route::get('/{id}/images', [ProductImageController::class, 'index']);

    /**
     * QORUNAN MƏHSUL ROUTE-LARI:
     * Yalnız autentifikasiya olunmuş istifadəçilər məhsul yarada/yeniləyə bilər.
     * Gələcəkdə burada rol yoxlaması da əlavə oluna bilər (admin, seller).
     */
    Route::middleware('auth:sanctum')->group(function () {
        // POST /api/products → Yeni məhsul yarat (Command)
        Route::post('/', [ProductController::class, 'store']);

        // PATCH /api/products/{id}/stock → Stoku yenilə (Command)
        Route::patch('/{id}/stock', [ProductController::class, 'updateStock']);

        // Product Image routes (QORUNAN)
        Route::post('/{id}/images', [ProductImageController::class, 'store']);
        Route::delete('/{id}/images/{imageId}', [ProductImageController::class, 'destroy']);
        Route::patch('/{id}/images/{imageId}/primary', [ProductImageController::class, 'setPrimary']);
    });
});

/*
|--------------------------------------------------------------------------
| Order Routes — Sifariş əməliyyatları (QORUNAN)
|--------------------------------------------------------------------------
| BÜTÜN sifariş route-ları auth:sanctum ilə qorunur.
| Çünki sifariş yaratmaq, görmək, ləğv etmək — hamısı
| autentifikasiya olunmuş istifadəçi tələb edir.
|
| CQRS burada görünür:
| POST (Command) və GET (Query) fərqli handler-lərə gedir.
| Bu CQRS-in əsas mahiyyətidir — yazma və oxuma ayrıdır.
*/
Route::prefix('orders')->middleware(['auth:sanctum', 'throttle:orders'])->group(function () {
    // POST /api/orders → Yeni sifariş yarat (Command → CreateOrderHandler)
    Route::post('/', [OrderController::class, 'store']);

    // GET /api/orders/{id} → Sifariş detalları (Query → GetOrderHandler)
    Route::get('/{id}', [OrderController::class, 'show']);

    // GET /api/orders/user/{userId} → İstifadəçinin sifarişləri (Query)
    Route::get('/user/{userId}', [OrderController::class, 'listByUser']);

    // POST /api/orders/{id}/cancel → Sifarişi ləğv et (Command → CancelOrderHandler)
    Route::post('/{id}/cancel', [OrderController::class, 'cancel']);

    // PATCH /api/orders/{id}/status → Status yenilə (Command)
    Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
});

/*
|--------------------------------------------------------------------------
| Payment Routes — Ödəniş əməliyyatları (QORUNAN)
|--------------------------------------------------------------------------
| Ödəniş əməliyyatları da qorunur — anonim ödəniş mümkün deyil.
| Strategy Pattern burada işləyir — payment method-a görə gateway seçilir.
*/
Route::prefix('payments')->middleware(['auth:sanctum', 'throttle:payment'])->group(function () {
    // POST /api/payments/process → Ödənişi emal et (Command → ProcessPaymentHandler)
    Route::post('/process', [PaymentController::class, 'process']);

    // GET /api/payments/{id} → Ödəniş detalları
    Route::get('/{id}', [PaymentController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Webhook Routes — Webhook idarəetməsi (QORUNAN)
|--------------------------------------------------------------------------
| İstifadəçilər webhook yaradıb xarici sistemlərə event bildirişi ala bilər.
*/
/*
|--------------------------------------------------------------------------
| Notification Preferences — Bildiriş seçimləri (QORUNAN)
|--------------------------------------------------------------------------
*/
Route::prefix('notifications/preferences')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationPreferenceController::class, 'index']);
    Route::put('/{eventType}', [NotificationPreferenceController::class, 'update']);
});

Route::prefix('webhooks')->middleware('auth:sanctum')->group(function () {
    Route::post('/', [WebhookController::class, 'store']);
    Route::get('/', [WebhookController::class, 'index']);
    Route::delete('/{id}', [WebhookController::class, 'destroy']);
    Route::patch('/{id}', [WebhookController::class, 'toggle']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes — Uğursuz Job İdarəetməsi (QORUNAN)
|--------------------------------------------------------------------------
| Yalnız admin istifadəçilər failed job-ları idarə edə bilər.
*/
Route::prefix('admin/failed-jobs')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [FailedJobController::class, 'index']);
    Route::get('/{id}', [FailedJobController::class, 'show']);
    Route::post('/{id}/retry', [FailedJobController::class, 'retry']);
    Route::post('/retry-all', [FailedJobController::class, 'retryAll']);
    Route::delete('/{id}', [FailedJobController::class, 'destroy']);
    Route::delete('/', [FailedJobController::class, 'flush']);
});
