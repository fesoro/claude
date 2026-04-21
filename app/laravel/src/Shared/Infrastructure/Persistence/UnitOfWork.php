<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Infrastructure\Bus\EventDispatcher;

/**
 * UNIT OF WORK — Atomik Aggregate Saxlama + Event Dispatch
 * ==========================================================
 *
 * PROBLEMİ ANLAYAQ:
 * =================
 * Bir use case-də birdən çox aggregate dəyişə bilər. Məsələn:
 *   1. Order yaradılır (Order aggregate)
 *   2. Product stoku azalır (Product aggregate)
 *   3. Hər ikisinin domain event-ləri dispatch olunur
 *
 * Əgər Order yazılır amma Product yazılmayanda nə olur?
 *   → Sifariş var amma stok azalmayıb → data uyğunsuzluğu (inconsistency)!
 *
 * Əgər hər ikisi yazılır amma event-lər dispatch olunmayanda?
 *   → Notification göndərilmir, read model yenilənmir → sistem bilmir ki, sifariş var!
 *
 * HƏLLİ — UNIT OF WORK:
 * ======================
 * Bütün dəyişiklikləri toplayır və BİR transaction-da yazır.
 * Ya hamısı uğurlu olur, ya heç biri → atomicity (bölünməzlik).
 * Event-lər yalnız transaction uğurlu olandan sonra dispatch olunur.
 *
 * ANALOGİYA:
 * Bank transferi: hesabdan çıxar + hesaba əlavə et → iki əməliyyat, amma atomik.
 * Ya ikisi də olur, ya heç biri. Birinin olub digərinin olmaması qəbuledilməzdir.
 *
 * MARTIN FOWLER-İN TƏRİFİ:
 * ========================
 * "Bir biznes əməliyyatından təsirlənən object-lərin siyahısını saxlayır
 * və dəyişikliklərin yazılmasını, concurrency problemlərinin həllini koordinasiya edir."
 *
 * UNIT OF WORK vs TRANSACTION:
 * ============================
 * Transaction: DB səviyyəsində atomiklik (BEGIN, COMMIT, ROLLBACK)
 * Unit of Work: Application səviyyəsində atomiklik + event dispatch koordinasiyası
 * Unit of Work transaction-u İSTİFADƏ EDİR, amma əlavə olaraq event-ləri idarə edir.
 *
 * UNIT OF WORK vs REPOSITORY:
 * ============================
 * Repository: Tək aggregate-i persist/retrieve edir.
 * Unit of Work: Birdən çox aggregate-in persist-ini koordinasiya edir + event dispatch.
 *
 * Repository tək aggregate-i tanıyır. Unit of Work bütün mənzərəni görür.
 */
class UnitOfWork
{
    /**
     * Bu əməliyyatda dəyişən aggregate-lərin siyahısı.
     * commit() çağırılana qədər burada toplanır.
     *
     * @var array<string, AggregateRoot>
     */
    private array $trackedAggregates = [];

    /**
     * Hər aggregate üçün persist (saxlama) funksiyası.
     * Bu callback repository-nin save metoduna bağlanır.
     *
     * NƏYƏ CALLBACK?
     * UnitOfWork konkret repository-ləri bilmir (tanımır).
     * O, sadəcə "bu aggregate-i saxla" deməyinə ehtiyac duyur.
     * Callback vasitəsilə hər aggregate-in öz save məntiqini təmin edirik.
     * Bu, Dependency Inversion prinsipinə uyğundur.
     *
     * @var array<string, callable>
     */
    private array $persistCallbacks = [];

    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly string $connection = 'sqlite',
    ) {}

    /**
     * AGGREGATE-İ İZLƏMƏYƏ AL (Track)
     * ==================================
     * Dəyişən aggregate-i Unit of Work-ə bildir ki, commit zamanı saxlasın.
     *
     * @param AggregateRoot $aggregate       Dəyişən aggregate
     * @param callable      $persistCallback Saxlama funksiyası: fn(AggregateRoot): void
     *
     * İSTİFADƏ NÜMUNƏSİ:
     * $unitOfWork->track($order, fn(AggregateRoot $agg) => $orderRepo->save($agg));
     * $unitOfWork->track($product, fn(AggregateRoot $agg) => $productRepo->save($agg));
     * $unitOfWork->commit();
     */
    public function track(AggregateRoot $aggregate, callable $persistCallback): void
    {
        /**
         * spl_object_id() — PHP-nin object üçün unikal ID verən funksiyası.
         * Eyni aggregate-i iki dəfə track etməmək üçün key kimi istifadə edirik.
         * Əgər eyni aggregate yenidən track olunsa, persist callback yenilənir.
         */
        $key = (string) spl_object_id($aggregate);
        $this->trackedAggregates[$key] = $aggregate;
        $this->persistCallbacks[$key] = $persistCallback;
    }

    /**
     * BÜTÜN DƏYİŞİKLİKLƏRİ YAZI + EVENT-LƏRİ DİSPATCH ET
     * ======================================================
     * Bu, Unit of Work-ün əsas metodudur. İki addım:
     *
     * ADDIM 1: Transaction daxilində bütün aggregate-ləri persist et.
     *   - Transaction uğursuzdursa → heç bir dəyişiklik yazılmır.
     *   - Bütün aggregate-lər ya birlikdə yazılır, ya heç biri.
     *
     * ADDIM 2: Transaction uğurlu oldusa → event-ləri dispatch et.
     *   - Event-lər yalnız data yazıldıqdan sonra göndərilir.
     *   - Bu vacibdir: event göndərilib data yazılmazsa → uyğunsuzluq!
     *
     * NƏYƏ EVENT-LƏR TRANSACTION DAXİLİNDƏ DİSPATCH OLUNMUR?
     * Event listener-lər başqa service-lərə sorğu göndərə bilər (HTTP, RabbitMQ).
     * Əgər transaction rollback olsa, xarici servisə göndərilmiş mesajı geri ala bilmərik.
     * Buna "dual-write problem" deyilir. Outbox pattern bunu daha da yaxşı həll edir.
     *
     * @throws \Throwable Transaction xətası zamanı
     */
    public function commit(): void
    {
        $allEvents = [];

        /**
         * ADDIM 1: Bütün aggregate-ləri bir transaction-da saxla.
         */
        DB::connection($this->connection)->transaction(function () use (&$allEvents) {
            foreach ($this->trackedAggregates as $key => $aggregate) {
                // Aggregate-in domain event-lərini topla (pull — event-ləri çıxarıb götürür)
                $events = $aggregate->pullDomainEvents();
                $allEvents = array_merge($allEvents, $events);

                // Persist callback-i çağır — repository save metodunu icra edir
                ($this->persistCallbacks[$key])($aggregate);
            }
        });

        /**
         * ADDIM 2: Transaction uğurludursa — event-ləri dispatch et.
         * Bu nöqtəyə çatdısa, data artıq DB-dədir.
         * Event-lər artıq təhlükəsiz şəkildə göndərilə bilər.
         */
        foreach ($allEvents as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        // Track siyahısını təmizlə — yeni use case üçün hazır
        $this->clear();
    }

    /**
     * DƏYİŞİKLİKLƏRİ LƏĞV ET (Rollback)
     * =====================================
     * Heç bir şey yazmadan track olunan aggregate-ləri unut.
     * Exception handling zamanı istifadə olunur.
     */
    public function rollback(): void
    {
        $this->clear();
    }

    /**
     * İzlənilən aggregate sayı.
     */
    public function trackedCount(): int
    {
        return count($this->trackedAggregates);
    }

    private function clear(): void
    {
        $this->trackedAggregates = [];
        $this->persistCallbacks = [];
    }
}
