<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdempotencyMiddleware - HTTP sorğularının idempotentliyini təmin edən middleware.
 *
 * ================================================================
 * İDEMPOTENTLİK (Idempotency) — ƏTRAFLII İZAH
 * ================================================================
 *
 * TƏRİF: Eyni əməliyyatın N dəfə icra edilməsinin 1 dəfə icra ilə
 * EYNİ NƏTİCƏ verməsidir.
 *
 * Riyazi ifadə: f(x) = f(f(x)) = f(f(f(x)))
 * Yəni funksiyanı neçə dəfə çağırsan da, nəticə eynidir.
 *
 * ================================================================
 * REAL HƏYATDAN MİSALLAR
 * ================================================================
 *
 * 1. ŞƏBƏKƏ XƏTAları (ən ümumi səbəb):
 *    ────────────────────────────────────
 *    İstifadəçi "100 AZN ödə" düyməsinə basır.
 *    Sorğu serverə çatır, ödəniş alınır.
 *    Cavab geri qayıdarkən internet kəsilir.
 *    İstifadəçi "Xəta baş verdi" görür, yenidən basır.
 *
 *    İDEMPOTENTLİK OLMADAN: 200 AZN alınır (istifadəçi narazı, şikayət yazır).
 *    İDEMPOTENTLİK İLƏ: İlk ödənişin cavabı qaytarılır, 2-ci ödəniş ALINMIR.
 *
 * 2. İKİ DƏFƏ KLIK (Double-click):
 *    ───────────────────────────────
 *    İstifadəçi tez-tez "Sifariş ver" düyməsinə 2 dəfə basır.
 *    2 sorğu eyni anda serverə çatır.
 *
 *    İDEMPOTENTLİK OLMADAN: 2 sifariş yaranır.
 *    İDEMPOTENTLİK İLƏ: 1 sifariş yaranır, 2-ci sorğuya 1-ci cavab qaytarılır.
 *
 * 3. MİCROSERVİCE RETRY:
 *    ────────────────────
 *    Service A → Service B-yə sorğu göndərir.
 *    Timeout olur (cavab 5 saniyədə gəlmir).
 *    Service A avtomatik yenidən cəhd edir (retry).
 *    Amma Service B əslində əməliyyatı uğurla icra etmişdi!
 *
 *    İDEMPOTENTLİK OLMADAN: Əməliyyat 2 dəfə icra olunur.
 *    İDEMPOTENTLİK İLƏ: 1-ci icranın cavabı qaytarılır.
 *
 * ================================================================
 * ÖDƏNİŞ SİSTEMLƏRİNDƏ İDEMPOTENTLİK
 * ================================================================
 *
 * Stripe (dünyanın ən böyük ödəniş sistemi):
 * - Hər API sorğusuna "Idempotency-Key" header-i əlavə etməyi TÖVSİYƏ edir.
 * - Açarı 24 saat saxlayır.
 * - Eyni açarla 2-ci sorğu gəldikdə, ödəniş təkrarlanmır.
 * - Bu, milyardlarla dollarlıq tranzaksiyaları qoruyur.
 *
 * PayPal:
 * - "PayPal-Request-Id" header-i istifadə edir.
 * - Eyni prinsip: eyni ID = eyni nəticə.
 *
 * Bank sistemləri:
 * - Hər köçürmənin unikal referans nömrəsi var.
 * - Eyni referans nömrəsi ilə 2-ci köçürmə rədd edilir.
 *
 * ================================================================
 * AÇAR YARATMA STRATEGİYALARI (Key Generation)
 * ================================================================
 *
 * 1. UUID v4 (ən sadə və ən çox istifadə edilən):
 *    "550e8400-e29b-41d4-a716-446655440000"
 *    + Unikaldır, toqquşma ehtimalı yox dərəcəsində azdır.
 *    - Mənasızdır, debug etmək çətindir.
 *
 * 2. Əməliyyat-əsaslı (Semantic key):
 *    "payment_user5_order123_100AZN"
 *    + Oxunaqlıdır, debug üçün əlverişlidir.
 *    + Eyni əməliyyat avtomatik eyni key yaradır.
 *    - Düzgün formatlamaq lazımdır.
 *
 * 3. Hash-əsaslı:
 *    md5(user_id + order_id + amount + timestamp_bucket)
 *    + Eyni parametrlər = eyni key (avtomatik idempotentlik).
 *    - Timestamp bucket-i düzgün seçmək lazımdır.
 *
 * 4. Klient-tərəfdən UUID + Server-tərəfdən yoxlama:
 *    Frontend: const key = crypto.randomUUID();
 *    Backend: Header-dən oxuyur və DB-də yoxlayır.
 *    Bu ən çox istifadə edilən yanaşmadır (bu middleware belə işləyir).
 *
 * ================================================================
 * BU MIDDLEWARE NECƏ İŞLƏYİR (addım-addım)
 * ================================================================
 *
 * 1. Klient sorğu göndərir: POST /api/orders, Header: X-Idempotency-Key: abc123
 * 2. Middleware "abc123" açarını DB-də axtarır.
 * 3a. TAPILDI və müddəti bitməyib → Saxlanmış cavabı qaytarır (əməliyyat icra edilmir).
 * 3b. TAPILMADI → Sorğunu controller-ə ötürür (normal iş axını).
 * 4. Controller cavab qaytarır.
 * 5. Middleware cavabı "abc123" açarı ilə DB-yə yazır.
 * 6. Növbəti eyni sorğuda addım 3a icra olunur.
 */
class IdempotencyMiddleware
{
    /**
     * İdempotentlik açarının yaşama müddəti (saat ilə).
     * 24 saat — Stripe ilə eyni strategiya.
     * Bu müddətdən sonra açar silinə bilər (cron job ilə).
     */
    private const KEY_LIFETIME_HOURS = 24;

    /**
     * HTTP sorğusunu idempotentlik yoxlaması ilə emal edir.
     *
     * @param Request $request Gələn HTTP sorğusu
     * @param Closure $next Növbəti middleware və ya controller
     * @return Response HTTP cavabı
     */
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * X-Idempotency-Key header-ini oxuyuruq.
         *
         * Niyə "X-" prefiksi?
         * - "X-" prefiksi custom (standart olmayan) header-lər üçün istifadə olunur.
         * - Stripe: "Idempotency-Key", PayPal: "PayPal-Request-Id" istifadə edir.
         * - Biz "X-Idempotency-Key" istifadə edirik.
         *
         * Header yoxdursa, idempotentlik yoxlamasını keçirik.
         * Bu, geriyə uyğunluğu (backward compatibility) təmin edir.
         */
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if ($idempotencyKey === null) {
            /**
             * Header yoxdursa, normal iş axını davam edir.
             * İdempotentlik isteğe bağlıdır (optional).
             * Amma ödəniş endpoint-lərində MƏCBUR etmək olar.
             */
            return $next($request);
        }

        /**
         * DB-dən əvvəlki cavabı axtarırıq.
         *
         * WHERE şərtləri:
         * 1. key = idempotency açarı (unikal identifikator)
         * 2. expires_at > now() — müddəti bitməmiş açar
         *
         * Niyə expires_at yoxlayırıq?
         * - Köhnə açarlar yenidən istifadə edilə bilsin.
         * - 24 saatdan sonra eyni açarla yeni sorğu normal icra olunacaq.
         */
        $existing = DB::table('idempotency_keys')
            ->where('key', $idempotencyKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            /**
             * TAPILDI — əvvəlki cavabı qaytarırıq.
             *
             * Bu deməkdir ki:
             * 1. Bu sorğu əvvəl uğurla icra olunub.
             * 2. Əməliyyat TƏKRARLANMIR.
             * 3. Klient əvvəlki cavabın eynisini alır.
             *
             * JSON-dan array-ə çeviririk və Response yaradırıq.
             */
            $cachedResponse = json_decode($existing->response, true);

            return response()->json(
                $cachedResponse['body'],
                $cachedResponse['status']
            );
        }

        /**
         * TAPILMADI — sorğunu normal icra edirik.
         * $next($request) controller-ə yönləndirir.
         * Controller əməliyyatı icra edir və cavab qaytarır.
         */
        $response = $next($request);

        /**
         * Cavabı DB-yə yazırıq — növbəti eyni sorğu üçün.
         *
         * Saxladığımız məlumatlar:
         * - status: HTTP status kodu (200, 201, 422 və s.)
         * - body: Cavab gövdəsi (JSON)
         *
         * Niyə yalnız uğurlu cavabları yox, hamısını saxlayırıq?
         * - Əgər validation xətası (422) olubsa, təkrar sorğuda da eyni xəta olmalıdır.
         * - Əgər yalnız 2xx saxlasaq və 422 saxlamasaq, klient xətalı sorğunu
         *   təkrarlaya bilər — bu isə gözlənilməz davranışdır.
         *
         * Bəzi sistemlər yalnız 2xx cavabları saxlayır (Stripe kimi).
         * Bu, dizayn qərarıdır — hər iki yanaşmanın üstünlüyü var.
         */
        DB::table('idempotency_keys')->insert([
            'key' => $idempotencyKey,
            'response' => json_encode([
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true),
            ]),
            'created_at' => now(),
            'expires_at' => now()->addHours(self::KEY_LIFETIME_HOURS),
        ]);

        return $response;
    }
}
