<?php

/**
 * API VERSİYA 1 ROUTE-LARI (V1)
 * ================================
 * Bu fayl /api/v1/ prefiksi altında işləyən bütün endpoint-ləri təyin edir.
 *
 * ═══════════════════════════════════════════════════════════════════
 * ROUTE VERSİYALAMA İZAHI
 * ═══════════════════════════════════════════════════════════════════
 *
 * Route versiyalama — API-nin müxtəlif versiyalarını ayrı fayllarda
 * saxlamaq strategiyasıdır. Üstünlükləri:
 *
 * 1. KOD TƏMİZLİYİ:
 *    Hər versiya öz faylındadır (api_v1.php, api_v2.php).
 *    V2 yaradanda V1-ə toxunmuruq — köhnə klientlər təsirlənmir.
 *
 * 2. GERİYƏ UYĞUNLUQ (Backward Compatibility):
 *    V1 klientləri (köhnə mobil app-lər) öz endpoint-lərini istifadə etməyə
 *    davam edir. V2-yə keçid tədricən olur.
 *
 * 3. DEPRECATION (Köhnəltmə):
 *    V1 köhnəldikdə, sadəcə route faylını deprecation middleware-dən keçiririk.
 *    Klientlərə "V1 tezliklə bağlanacaq" xəbərdarlığı göndəririk.
 *
 * NECƏ İŞLƏYİR?
 *   routes/api.php → Route::prefix('v1')->group(fn => require 'api_v1.php')
 *   Bu fayl /api/v1/ prefiksi ilə yüklənir.
 *   Nəticə: /api/v1/products, /api/v1/orders, /api/v1/users ...
 *
 * YENİ VERSİYA ƏLAVƏ ETMƏK:
 *   1. routes/api_v2.php yaradın
 *   2. routes/api.php-də Route::prefix('v2')->group(...) əlavə edin
 *   3. Dəyişiklikləri api_v2.php-də edin
 *   4. api_v1.php-yə toxunmayın!
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\HealthCheckController;

/*
|--------------------------------------------------------------------------
| User Routes — İstifadəçi əməliyyatları (V1)
|--------------------------------------------------------------------------
*/
Route::prefix('users')->group(function () {
    // POST /api/v1/users/register → Yeni istifadəçi qeydiyyatı
    Route::post('/register', [UserController::class, 'register']);

    // GET /api/v1/users/{id} → İstifadəçi məlumatlarını al (Query)
    Route::get('/{id}', [UserController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Product Routes — Məhsul əməliyyatları (V1)
|--------------------------------------------------------------------------
*/
Route::prefix('products')->group(function () {
    // GET /api/v1/products → Bütün məhsulların siyahısı (Pagination ilə)
    Route::get('/', [ProductController::class, 'index']);

    // GET /api/v1/products/{id} → Tək məhsul (Query)
    Route::get('/{id}', [ProductController::class, 'show']);

    // POST /api/v1/products → Yeni məhsul yarat (Command)
    Route::post('/', [ProductController::class, 'store']);

    // PATCH /api/v1/products/{id}/stock → Stoku yenilə (Command)
    Route::patch('/{id}/stock', [ProductController::class, 'updateStock']);
});

/*
|--------------------------------------------------------------------------
| Order Routes — Sifariş əməliyyatları (V1 — CQRS)
|--------------------------------------------------------------------------
*/
Route::prefix('orders')->group(function () {
    // POST /api/v1/orders → Yeni sifariş yarat (Command)
    Route::post('/', [OrderController::class, 'store']);

    // GET /api/v1/orders/{id} → Sifariş detalları (Query)
    Route::get('/{id}', [OrderController::class, 'show']);

    // GET /api/v1/orders/user/{userId} → İstifadəçinin sifarişləri (Query, Pagination ilə)
    Route::get('/user/{userId}', [OrderController::class, 'listByUser']);

    // POST /api/v1/orders/{id}/cancel → Sifarişi ləğv et (Command)
    Route::post('/{id}/cancel', [OrderController::class, 'cancel']);

    // PATCH /api/v1/orders/{id}/status → Status yenilə (Command)
    Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
});

/*
|--------------------------------------------------------------------------
| Payment Routes — Ödəniş əməliyyatları (V1)
|--------------------------------------------------------------------------
*/
Route::prefix('payments')->group(function () {
    // POST /api/v1/payments/process → Ödənişi emal et (Command)
    Route::post('/process', [PaymentController::class, 'process']);

    // GET /api/v1/payments/{id} → Ödəniş detalları
    Route::get('/{id}', [PaymentController::class, 'show']);
});
