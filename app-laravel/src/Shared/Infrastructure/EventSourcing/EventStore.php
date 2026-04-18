<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Src\Shared\Domain\DomainEvent;

/**
 * EVENT STORE İNTERFEYSİ
 * ======================
 * Event Store — bütün domain event-lərin saxlanıldığı yerin abstraksiyadır.
 *
 * Bu interfeys Event Store-un NƏ etdiyini müəyyən edir, NECƏ etdiyini yox.
 * İmplementasiya fərqli ola bilər:
 *   - EloquentEventStore: Laravel DB ilə (bu layihədə istifadə edirik)
 *   - EventStoreDB: Xüsusi Event Sourcing verilənlər bazası (EventStoreDB.com)
 *   - InMemoryEventStore: Test üçün yaddaşda saxlayan versiya
 *
 * DEPENDENCY INVERSION PRİNSİPİ (SOLID-in "D"-si):
 * Domain və Application layer-lər bu interfeysi tanıyır,
 * konkret implementasiyanı (Eloquent) bilmirlər.
 * Bu, test yazmağı və implementasiyanı dəyişməyi asanlaşdırır.
 *
 * OPTİMİSTİC LOCKİNG (expectedVersion parametri):
 * ===============================================
 * Bu, concurrent (eyni vaxtda) yazma problemini həll edən mexanizmdir.
 *
 * PROBLEM:
 *   İki proses eyni anda eyni sifarişi dəyişmək istəyir:
 *   Proses A: Sifarişi oxuyur (version=3), təsdiqləyir, version=4 yazmaq istəyir
 *   Proses B: Sifarişi oxuyur (version=3), ləğv edir, version=4 yazmaq istəyir
 *   Əgər heç bir yoxlama olmazsa, hər ikisi yazar və data pozular!
 *
 * HƏLLİ (Optimistic Locking):
 *   append() metoduna "expectedVersion" parametri verilir.
 *   Bu, "mən oxuyanda version 3 idi, yeni event-i version 4 kimi yaz" deməkdir.
 *
 *   Proses A: append(orderId, events, expectedVersion=3) → version 4 yazılır ✅
 *   Proses B: append(orderId, events, expectedVersion=3) → XƏTA! version 4 artıq var ❌
 *
 *   Proses B xəta alır və yenidən cəhd edə bilər (retry).
 *   Bu yanaşma "optimistic" adlanır çünki "çox güman ki, conflict olmayacaq" fərziyyəsinə əsaslanır.
 *   Pessimistic locking-dən fərqi: DB lock istifadə etmir, yalnız version yoxlayır.
 *
 * ANALOGİYA:
 *   Git-də push edəndə:
 *   "git push" uğurlu olur əgər remote-da yeni commit yoxdursa.
 *   Əgər kimsə sizdən əvvəl push edibsə — conflict! Pull edib yenidən push etməlisiniz.
 *   Bu elə optimistic locking-dir.
 */
interface EventStore
{
    /**
     * Event-ləri Event Store-a əlavə et.
     *
     * @param string        $aggregateId     Aggregate-in UUID-si (məs: sifariş ID-si)
     * @param DomainEvent[] $events          Əlavə ediləcək event-lər (bir əməliyyatda birdən çox ola bilər)
     * @param int           $expectedVersion Optimistic locking — aggregate-in gözlənilən cari versiyası.
     *                                       Əgər bazadakı son versiya bundan fərqlidirsə,
     *                                       ConcurrencyException atılmalıdır.
     *
     * @throws \RuntimeException Versiya uyğunsuzluğu (concurrency conflict) zamanı
     */
    public function append(string $aggregateId, array $events, int $expectedVersion): void;

    /**
     * Müəyyən bir Aggregate-in BÜTÜN event-lərini al (versiya sırasıyla).
     *
     * Bu metod Aggregate-i yenidən qurmaq (reconstitution) üçün istifadə olunur:
     * 1. Event-ləri oxu (version sırasıyla)
     * 2. Hər event-i Aggregate-ə "apply" et
     * 3. Nəticədə Aggregate-in cari vəziyyətini əldə et
     *
     * ANALOGİYA: Git-də bir repo-nun bütün commit-lərini oxumaq.
     *
     * @param string $aggregateId Aggregate-in UUID-si
     * @return array Event-lərin siyahısı (hər element: ['event_type', 'payload', 'version', ...])
     */
    public function getEventsForAggregate(string $aggregateId): array;

    /**
     * BÜTÜN event-ləri al — proyeksiya (Read Model) yenidən qurmaq üçün.
     *
     * DİQQƏT: Bu metod böyük sistemlərdə milyonlarla sətir qaytara bilər!
     * Real layihədə pagination, streaming və ya cursor əlavə edilməlidir.
     * Burada sadəlik üçün hamısını qaytarırıq.
     *
     * İSTİFADƏ HALLARı:
     * - Proyeksiyanı sıfırdan yenidən qurmaq (rebuild)
     * - Yeni bir Read Model yaratmaq (keçmiş event-ləri də emal etmək)
     * - Debug / analiz üçün bütün event-ləri görmək
     *
     * @return array Bütün event-lər (created_at sırasıyla)
     */
    public function getAllEvents(): array;
}
