<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency Keys cədvəli üçün migration.
 *
 * ================================================================
 * İDEMPOTENTLİK (Idempotency) NƏDİR?
 * ================================================================
 *
 * İdempotentlik — eyni əməliyyatın bir neçə dəfə icra edilməsinin
 * NƏTİCƏNİ DƏYİŞMƏMƏSİ deməkdir.
 *
 * Sadə misal:
 * - "İşığı yandır" düyməsini 5 dəfə bassanız, işıq 1 dəfə yanır (idempotent).
 * - "Hesaba 100 AZN əlavə et" 5 dəfə icra olunsa, 500 AZN əlavə olunur (İDEMPOTENT DEYİL!).
 *
 * Niyə idempotentlik vacibdir?
 * ───────────────────────────
 * 1. ŞƏBƏKƏ XƏTAları: İstifadəçi "Ödə" düyməsinə basır → sorğu serverə çatır →
 *    cavab geri qayıdarkən internet kəsilir → istifadəçi yenidən basır →
 *    İDEMPOTENTLİK OLMADAN: 2 dəfə ödəniş alınır!
 *
 * 2. İKİ DƏFƏ KLIK (Double-click): İstifadəçi səhvən "Sifariş ver" düyməsinə
 *    2 dəfə basır → 2 sifariş yaranır (istənməyən nəticə).
 *
 * 3. YENİDƏN CƏHD (Retry): Microservice-lər arasında timeout olduqda,
 *    sistem avtomatik yenidən sorğu göndərir → əməliyyat təkrarlanmamalıdır.
 *
 * Bu cədvəl necə işləyir?
 * ───────────────────────
 * 1. Klient hər sorğu ilə unikal "idempotency key" göndərir (HTTP header-da).
 * 2. Server bu açarı cədvəldə axtarır:
 *    - TAPILDI: Əvvəlki cavabı qaytarır (əməliyyat təkrarlanmır).
 *    - TAPILMADI: Əməliyyatı icra edir, cavabı bu cədvələ yazır.
 * 3. Növbəti eyni açarla gələn sorğu cache-dəki cavabı alır.
 *
 * Ödəniş sistemlərində idempotentlik:
 * ────────────────────────────────────
 * Stripe, PayPal kimi sistemlər idempotency key TƏLƏBedir:
 * - Stripe: Idempotency-Key header-i ilə.
 * - Hər ödəniş sorğusuna unikal key əlavə edilir.
 * - Eyni key ilə 2-ci sorğu gəldikdə, ödəniş TƏKRARLANMIR.
 * - Bu, milyonlarla dollarlıq dublikat ödənişlərin qarşısını alır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            /**
             * key — İdempotentlik açarı (Primary Key).
             *
             * Klient tərəfindən yaradılır. Populyar strategiyalar:
             * 1. UUID v4: "550e8400-e29b-41d4-a716-446655440000" (ən çox istifadə edilən)
             * 2. Əməliyyat + timestamp: "payment_order123_1704067200"
             * 3. Hash: md5("user_5_order_123_100AZN") — eyni parametrlər = eyni key
             *
             * String istifadə edirik çünki UUID formatındadır.
             * Primary key — hər key unikal olmalıdır.
             */
            $table->string('key')->primary();

            /**
             * response — Əvvəlki sorğunun cavabı (JSON formatında).
             *
             * Niyə cavabı saxlayırıq?
             * - Eyni sorğu yenidən gəldikdə, əməliyyatı yenidən icra etməyə ehtiyac yoxdur.
             * - Əvvəlki cavabı olduğu kimi qaytarırıq.
             * - HTTP status kodu, body, headers — hamısı burada saxlanılır.
             *
             * Format nümunəsi:
             * {"status": 200, "body": {"order_id": "123", "message": "Uğurlu"}}
             */
            $table->json('response');

            /**
             * created_at — Açarın yaradılma tarixi.
             * Nə vaxt bu sorğu ilk dəfə gəldi?
             */
            $table->timestamp('created_at')->useCurrent();

            /**
             * expires_at — Açarın bitmə tarixi.
             *
             * Niyə bitmə tarixi lazımdır?
             * - Cədvəl sonsuz böyüyər (hər sorğu yeni sətir).
             * - Köhnə açarları silmək lazımdır (cron job ilə).
             * - Adətən 24 saat saxlanılır — bu müddətdən sonra retry ehtimalı yoxdur.
             * - Stripe açarları 24 saat saxlayır.
             */
            $table->timestamp('expires_at');

            /**
             * expires_at üzrə index — köhnə açarları sürətlə silmək üçün.
             * Cron job: DELETE FROM idempotency_keys WHERE expires_at < NOW()
             */
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
