<?php

declare(strict_types=1);

namespace Src\Order\Infrastructure\ReadModel;

use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\EventSourcing\EventStore;

/**
 * PROJECTİON REBUİLDER — Proyeksiyanı Sıfırdan Yenidən Quran Mexanizm
 * =====================================================================
 *
 * REBUİLD (YENİDƏN QURMA) NƏDİR?
 * ================================
 * Rebuild — Read Model-i tamamilə silib, Event Store-dakı BÜTÜN event-ləri
 * yenidən emal edərək Read Model-i sıfırdan yaratma prosesidir.
 *
 * ANALOGİYA — EVİN YENİDƏN RƏNGLƏNMƏSİ:
 * ========================================
 * Evin divarlarında çatlar, ləkələr var (Read Model-də xəta var).
 * Həll: köhnə rəngi təmizlə (TRUNCATE), yenidən rənglə (replay events).
 * Divarın özü (Event Store) sağlamdır — yalnız üst qatı (Read Model) yeniləyirik.
 *
 * Daha texniki analogiya — Git:
 * 1. git reset --hard origin/main (köhnə Read Model-i sil)
 * 2. Bütün commit-lər yenidən tətbiq olunur (event replay)
 * 3. Son vəziyyət əldə olunur (yeni Read Model hazır)
 *
 * NƏ VAXT REBUİLD LAZIMDIR?
 * =========================
 * 1. PROJEKSİYADA BUG TAPILDI:
 *    Projeksiyon-da xəta var idi → bəzi sifarişlərin statusu yanlış yazılıb.
 *    Xətanı düzəldirik, rebuild edirik → bütün Read Model düzgün olur.
 *
 * 2. YENİ SAHƏ ƏLAVƏ EDİLDİ:
 *    Read Model-ə 'cancellation_reason' sahəsi əlavə etdik.
 *    Amma keçmiş sifarişlərdə bu sahə boşdur.
 *    Rebuild edirik → keçmiş OrderCancelled event-lərindən səbəb doldurulur.
 *
 * 3. YENİ PROJEKSİYA YARADILDI:
 *    Yeni bir Read Model (məs: order_statistics) yaratdıq.
 *    Amma keçmiş data yoxdur — rebuild ilə keçmiş event-ləri emal edirik.
 *
 * 4. DATA İTKİSİ:
 *    Read Model DB-si xərab oldu. Event Store sağlamdır.
 *    Rebuild ilə Read Model-i tamamilə bərpa edirik.
 *
 * REBUİLD PROSESİ:
 * =================
 *
 *   ┌─────────────┐     ┌──────────┐     ┌─────────────┐     ┌──────────────┐
 *   │  TRUNCATE   │────→│ GET ALL  │────→│   REPLAY    │────→│  READ MODEL  │
 *   │ Read Model  │     │  Events  │     │  Each Event │     │   HAZIRDIR   │
 *   └─────────────┘     └──────────┘     └─────────────┘     └──────────────┘
 *
 * PERFORMANS XƏBƏRDARLIGI:
 * ========================
 * Böyük sistemlərdə milyonlarla event ola bilər.
 * Rebuild prosesi çox vaxt ala bilər (dəqiqələr, hətta saatlar).
 *
 * OPTİMİZASİYA YOLLARI (bu layihədə implementasiya edilmir):
 * 1. CHUNK: Event-ləri 1000-1000 oxumaq (yaddaş qənaəti).
 * 2. SNAPSHOT: Müəyyən nöqtədən sonra rebuild etmək.
 * 3. PARALLEL: Fərqli aggregate-ləri paralel emal etmək.
 * 4. QUEUE: Rebuild prosesini background job kimi icra etmək.
 *
 * Bu layihədə sadəlik üçün bütün event-ləri bir dəfəyə oxuyuruq.
 */
class ProjectionRebuilder
{
    /**
     * Dependency Injection vasitəsilə asılılıqlar verilir.
     *
     * @param EventStore      $eventStore  Event Store — bütün event-ləri oxumaq üçün
     * @param OrderProjection $projection  Projeksiyon — event-ləri emal etmək üçün
     */
    public function __construct(
        private readonly EventStore $eventStore,
        private readonly OrderProjection $projection,
    ) {
    }

    /**
     * PROJEKSİYANI SIFIRDAN YENİDƏN QUR
     * ====================================
     *
     * Bu metod aşağıdakı addımları icra edir:
     *
     * ADDIM 1: Mövcud Read Model-i tamamilə təmizlə (TRUNCATE).
     *   TRUNCATE vs DELETE: TRUNCATE daha sürətlidir çünki:
     *   - Sətirləri tək-tək silmir, cədvəli tamamilə boşaldır.
     *   - Auto-increment sayğacını sıfırlayır (bizdə olmasa da).
     *   - Transaction log-a yazmır (daha az disk I/O).
     *   Amma TRUNCATE rollback edilə bilməz — buna görə diqqətli olun!
     *
     * ADDIM 2: Event Store-dan BÜTÜN event-ləri oxu.
     *   getAllEvents() metodu bütün event-ləri created_at sırasıyla qaytarır.
     *   Bu ardıcıllıq vacibdir — event-lər düzgün sırada emal olunmalıdır.
     *
     * ADDIM 3: Hər event-i Projeksiyon-a göndər.
     *   handleRawEvent() metodu event tipinə görə müvafiq on*() metodunu çağırır.
     *   Nəticədə Read Model sıfırdan yenidən qurulur.
     *
     * @return int Emal olunan event-lərin sayı (monitoring/logging üçün)
     */
    public function rebuild(): int
    {
        /**
         * ADDIM 1: Read Model-i təmizlə.
         *
         * ANALOGİYA: Təbaşir taxtasını silmək — köhnə yazıları təmizlə, boş taxtaya yenidən yaz.
         *
         * DİQQƏT: Bu əməliyyat GERİ QAYTARILA BİLMƏZ!
         * Amma problem yoxdur çünki Event Store-dan yenidən yaradacağıq.
         */
        DB::connection('order_db')->table('order_read_model')->truncate();

        /**
         * ADDIM 2: Bütün event-ləri Event Store-dan oxu.
         *
         * getAllEvents() — EloquentEventStore-da implementasiya olunub.
         * Event-lər created_at + version sırasıyla qaytarılır.
         * Bu ardıcıllıq VACİBDİR:
         *   OrderCreated → ItemAdded → OrderConfirmed → OrderPaid
         *   Əgər OrderPaid OrderCreated-dən əvvəl emal olunsa — Read Model-də order_id tapılmaz!
         */
        $allEvents = $this->eventStore->getAllEvents();

        /** Emal olunan event sayını izləyirik — monitoring üçün */
        $processedCount = 0;

        /**
         * ADDIM 3: Hər event-i sırayla emal et.
         *
         * ANALOGİYA: Gündəlik dəftərini başdan oxumaq.
         * Hər qeyd (event) oxunur, bu günün xülasəsi (Read Model) yenidən yazılır.
         */
        foreach ($allEvents as $eventRow) {
            /**
             * Event Store-da event data-sı JSON formatında saxlanılır.
             * Burada JSON-u array-ə çeviririk.
             *
             * $eventRow stdClass obyektidir (DB::table() qaytarır):
             * - event_type: "Src\Order\Domain\Events\OrderCreatedEvent"
             * - payload: '{"order_id":"uuid","user_id":"user123"}'
             */
            $payload = json_decode($eventRow->payload, true);

            /**
             * handleRawEvent() event tipinə görə müvafiq on*() metodunu çağırır.
             * Məsələn:
             *   event_type = OrderCreatedEvent::class → onOrderCreated() çağırılır
             *   event_type = OrderPaidEvent::class → onOrderPaid() çağırılır
             */
            $this->projection->handleRawEvent($eventRow->event_type, $payload);

            $processedCount++;
        }

        return $processedCount;
    }
}
