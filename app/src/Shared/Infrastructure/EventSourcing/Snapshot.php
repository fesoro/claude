<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

/**
 * SNAPSHOT — Event Sourcing Snapshot (Anlıq Görüntü)
 * =====================================================
 * Aggregate-in müəyyən versiyasındakı vəziyyətinin "fotoşəkli".
 *
 * ═══════════════════════════════════════════════════════════════
 * SNAPSHOT NƏDİR VƏ NİYƏ LAZIMDIR?
 * ═══════════════════════════════════════════════════════════════
 *
 * PROBLEM:
 * ────────
 * Event Sourcing-də aggregate-i yükləmək üçün BÜTÜN event-ləri replay etmək lazımdır.
 *
 * Sifariş #42 → 500 event var (yaradıldı, ödənildi, göndərildi, status dəyişdi...)
 * Hər yükləmədə 500 event-i replay edirik → YAVAŞ!
 *
 * 10,000 event olan aggregate-i düşünün:
 *   - 10,000 event-i DB-dən oxu → disk I/O
 *   - 10,000 event-i deserialize et → CPU
 *   - 10,000 apply() çağır → CPU
 *   - Hər HTTP sorğuda bu baş verir → İSTİFADƏÇİ GÖZLƏYİR!
 *
 * HƏLL: SNAPSHOT
 * ──────────────
 * Hər 100 event-dən sonra aggregate-in vəziyyətinin "fotoşəklini" çəkirik.
 *
 * Yükləmə prosesi (snapshot ilə):
 *   1. Sonuncu snapshot-u yüklə (version: 400)
 *   2. Yalnız 401-500 arasındakı event-ləri replay et (100 event)
 *   3. Cəmi: 1 snapshot oxu + 100 event replay (500 əvəzinə)
 *
 * NÜMUNƏ:
 * ═══════
 *
 * Event-lər: [1, 2, 3, ..., 100, 101, ..., 200, 201, ..., 250]
 *
 * Snapshot yoxdur:
 *   Yüklə: 250 event replay et → yavaş
 *
 * Snapshot var (version: 200):
 *   Yüklə: snapshot (version 200) + 50 event (201-250) replay et → sürətli!
 *
 * ANALOGİYA:
 * ──────────
 * Video oyununda "save point" kimi düşünün:
 * - Save point olmasa → oyunu əvvəldən oynamalısan
 * - Save point varsa → son save-dən davam edirsən
 * - Snapshot = save point
 *
 * Və ya Git-də branch yaratma:
 * - Branch = sonuncu commit-in snapshot-u
 * - Branch-dan sonra yalnız yeni commit-lər apply olunur
 *
 * ═══════════════════════════════════════════════════════════════
 * SNAPSHOT NƏ VAXT ÇƏKİLMƏLİDİR?
 * ═══════════════════════════════════════════════════════════════
 *
 * 1. EVENT SAYI ƏSASLI (ən populyar):
 *    Hər N event-dən sonra snapshot çək.
 *    N = 100 ümumi olaraq yaxşı hədddir.
 *    Çox kiçik N → çox snapshot yazılır (disk israfı)
 *    Çox böyük N → replay yavaş olur
 *
 * 2. VAXT ƏSASLI:
 *    Hər gün/saat snapshot çək.
 *    Cron job ilə işləyir.
 *
 * 3. MANUAL:
 *    Admin tərəfindən tələb olunanda.
 *    Migration və ya böyük data import-dan sonra.
 */
final class Snapshot
{
    /**
     * @param string $aggregateId Aggregate-in ID-si (hansı aggregate?)
     * @param string $aggregateType Aggregate class-ı (hansı tip?)
     * @param int $version Bu snapshot-un yaradıldığı versiya nömrəsi
     * @param array $state Aggregate-in serialized vəziyyəti (bütün sahələr)
     * @param \DateTimeImmutable $createdAt Snapshot-un yaradılma vaxtı
     */
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $aggregateType,
        public readonly int $version,
        public readonly array $state,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
