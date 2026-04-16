<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

/**
 * CONCRETE EVENT UPCASTER — Real Event Versiya Migration-ları
 * =============================================================
 *
 * EventUpcaster abstract mexanizmi artıq var. Bu class onu REAL istifadəyə qoyur.
 *
 * SSENARI:
 * ========
 * Layihə 6 ay əvvəl başlayıb. O vaxt OrderCreatedEvent belə idi:
 *
 * v1 (6 ay əvvəl):
 *   { "order_id": "abc", "user_id": "123" }
 *
 * Sonra müştərilər valyuta tələb etdi:
 * v2 (3 ay əvvəl):
 *   { "order_id": "abc", "user_id": "123", "currency": "AZN" }
 *
 * Sonra məbləğ əlavə olundu:
 * v3 (indi):
 *   { "order_id": "abc", "user_id": "123", "currency": "AZN", "total_amount": 0 }
 *
 * Event Store-da hər üç versiyada event-lər var!
 * Replay edəndə v1 event gəlir — "currency" sahəsi yoxdur → CRASH!
 *
 * Upcaster v1-i v3-ə çevirir: əvvəl currency əlavə edir, sonra total_amount.
 *
 * DB MİGRATİON İLƏ MÜQAYİSƏ:
 * ============================
 * DB migration: ALTER TABLE orders ADD COLUMN currency VARCHAR(3) DEFAULT 'AZN'
 *   → Bazadakı bütün sətirləri DƏYİŞİR. In-place.
 *
 * Event Upcaster: v1 payload-a 'currency' => 'AZN' əlavə edir
 *   → Event Store-dakı datanı DƏYİŞMİR. On-the-fly (oxuyanda).
 *   → Event Store append-only qalır — keçmiş dəyişmir.
 *
 * Bu fərq çox vacibdir: Event Sourcing-in əsas prinsipi "keçmişi dəyişmə".
 *
 * QEYDİYYAT:
 * ==========
 * DomainServiceProvider-da EventUpcaster yaradılanda bu class-ın register() metodu
 * çağırılır. Bütün versiya migration-ları bir yerdə toplanır.
 */
class ConcreteEventUpcaster
{
    /**
     * BÜTÜN EVENT VERSİYA MİGRATİON-LARINI QEYDİYYATDAN KEÇİR
     * ===========================================================
     * Hər register() çağırışı bir versiya keçidini təyin edir.
     * Upcaster zənciri: v1 → v2 → v3 ardıcıl tətbiq olunur.
     */
    public static function register(EventUpcaster $upcaster): void
    {
        // ═══════════════════════════════════════════════════════
        // OrderCreatedEvent versiya migration-ları
        // ═══════════════════════════════════════════════════════

        /**
         * v1 → v2: 'currency' sahəsi əlavə olundu.
         *
         * v1 event-lər layihənin ilk versiyasındandır.
         * O vaxt yalnız AZN istifadə olunurdu, valyuta sahəsi lazım deyildi.
         * Sonra xarici müştərilər gəldi — USD, EUR lazım oldu.
         *
         * Default: 'AZN' çünki ilk versiyada yalnız AZN var idi.
         */
        $upcaster->register(
            'Src\\Order\\Domain\\Events\\OrderCreatedEvent',
            1,
            function (array $payload): array {
                $payload['currency'] = $payload['currency'] ?? 'AZN';
                return $payload;
            },
        );

        /**
         * v2 → v3: 'total_amount' sahəsi əlavə olundu.
         *
         * Əvvəl total_amount Order aggregate-də hesablanırdı, event-də yox idi.
         * Amma Read Model Projector üçün total_amount event-də lazım oldu —
         * Projector aggregate-i yenidən qurmadan read model-i yeniləmək istəyir.
         *
         * Default: 0 çünki sifariş yaradılanda hələ item əlavə olunmayıb.
         * Total yalnız OrderItemAddedEvent-lərdən hesablanır.
         */
        $upcaster->register(
            'Src\\Order\\Domain\\Events\\OrderCreatedEvent',
            2,
            function (array $payload): array {
                $payload['total_amount'] = $payload['total_amount'] ?? 0;
                return $payload;
            },
        );

        // ═══════════════════════════════════════════════════════
        // PaymentCompletedEvent versiya migration-ları
        // ═══════════════════════════════════════════════════════

        /**
         * v1 → v2: 'currency' sahəsi əlavə olundu.
         *
         * İlk versiyada ödəniş yalnız AZN-də idi.
         * Multi-currency dəstəyi əlavə olunanda currency sahəsi lazım oldu.
         */
        $upcaster->register(
            'Src\\Payment\\Domain\\Events\\PaymentCompletedEvent',
            1,
            function (array $payload): array {
                $payload['currency'] = $payload['currency'] ?? 'AZN';
                return $payload;
            },
        );

        // ═══════════════════════════════════════════════════════
        // ProductCreatedEvent versiya migration-ları
        // ═══════════════════════════════════════════════════════

        /**
         * v1 → v2: 'description' sahəsi əlavə olundu.
         *
         * İlk versiyada məhsul yalnız ad və qiymətdən ibarət idi.
         * SEO üçün description lazım oldu.
         */
        $upcaster->register(
            'Src\\Product\\Domain\\Events\\ProductCreatedEvent',
            1,
            function (array $payload): array {
                $payload['description'] = $payload['description'] ?? '';
                return $payload;
            },
        );
    }
}
