<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Illuminate\Support\Facades\DB;
use Src\Shared\Domain\DomainEvent;

/**
 * ELOQUENT EVENT STORE — Event Store-un Laravel/DB implementasiyası
 * ==================================================================
 *
 * Bu class Event Store interfeyasını Laravel-in DB fasadı ilə həyata keçirir.
 * Event-ləri 'event_store' cədvəlinə yazır və oradan oxuyur.
 *
 * NƏYƏ ELoquent MODEL deyil, DB FASADI?
 * - Event Store sadə append-only (yalnız əlavə etmə) cədvəldir.
 * - Eloquent model-in relationship, mutator, accessor kimi xüsusiyyətləri lazım deyil.
 * - DB fasadı daha sürətli və sadədir bu hal üçün.
 * - Amma Eloquent model ilə də eyni şeyi etmək olar — fərq yoxdur.
 *
 * APPEND-ONLY PRİNSİPİ:
 * Event Store-da event YALNIZ əlavə edilir, HEÇ VAXT:
 * - Dəyişdirilmir (UPDATE yoxdur)
 * - Silinmir (DELETE yoxdur)
 * Keçmişi dəyişmək olmaz — bank hesabında əməliyyatı silmək olmadığı kimi.
 * Əgər "düzəliş" lazımdırsa, yeni compensating event əlavə edilir.
 *
 * CONNECTION:
 * 'order_db' connection istifadə edirik — database-per-service pattern.
 * Hər bounded context öz DB-sinə yazır.
 */
class EloquentEventStore implements EventStore
{
    /**
     * Hansı DB connection istifadə olunacaq.
     * Config/database.php-də 'order_db' kimi təyin olunmalıdır.
     */
    private const CONNECTION = 'order_db';

    /**
     * Event Store cədvəlinin adı.
     */
    private const TABLE = 'event_store';

    /**
     * EVENT-LƏRİ EVENT STORE-A YAZAR (Append)
     * ========================================
     *
     * Bu metod aşağıdakı addımları icra edir:
     *
     * 1. OPTİMİSTİC LOCKİNG YOXLAMASI:
     *    Bazadakı son versiya ilə gözlənilən versiyanı müqayisə edir.
     *    Əgər fərqlidirsə — başqa proses artıq dəyişiklik edib, conflict var!
     *
     * 2. TRANSACTİON DAXİLİNDƏ YAZMA:
     *    Bütün event-lər bir transaction-da yazılır.
     *    Ya hamısı yazılır, ya heç biri — atomicity (bölünməzlik).
     *
     * 3. VERSİYA ARTIRILMASI:
     *    Hər event gözlənilən versiya + sıra nömrəsi ilə yazılır.
     *    Məsələn: expectedVersion=3, 2 event varsa → version 4 və 5 yazılır.
     *
     * XƏTA HALLARı:
     * - Versiya uyğunsuzluğu → RuntimeException (optimistic locking failure)
     * - DB xətası → Transaction rollback olur, heç bir event yazılmır
     *
     * @param string        $aggregateId     Aggregate UUID-si
     * @param DomainEvent[] $events          Yazılacaq event-lər
     * @param int           $expectedVersion Gözlənilən cari versiya (0 = yeni aggregate)
     *
     * @throws \RuntimeException Versiya konflikti zamanı
     */
    public function append(string $aggregateId, array $events, int $expectedVersion): void
    {
        DB::connection(self::CONNECTION)->transaction(function () use ($aggregateId, $events, $expectedVersion) {

            /**
             * ADDIM 1: Bazadakı son versiyanı yoxla.
             *
             * lockForUpdate() — SELECT ... FOR UPDATE sorğusu yaradır.
             * Bu, pessimistic lock-dur və transaction bitənə qədər digər proses-ləri gözlədir.
             * Beləliklə, iki proses eyni anda yoxlama edə bilməz.
             *
             * NƏYƏ HƏM OPTİMİSTİC, HƏM PESSİMİSTİC?
             * - Optimistic locking (version yoxlaması): application səviyyəsində
             * - Pessimistic locking (lockForUpdate): DB səviyyəsində, transaction daxilində
             * İkisi birlikdə ən etibarlı nəticəni verir.
             * Real layihədə yalnız biri kifayət edə bilər, amma öyrənmək üçün ikisini göstəririk.
             */
            $currentVersion = DB::connection(self::CONNECTION)
                ->table(self::TABLE)
                ->where('aggregate_id', $aggregateId)
                ->lockForUpdate()
                ->max('version') ?? 0;

            /**
             * ADDIM 2: Versiya yoxlaması — optimistic locking-in əsas mexanizmi.
             *
             * Əgər bazadakı son versiya gözlənilən versiyadan fərqlidirsə,
             * bu o deməkdir ki, biz aggregate-i oxuyandan bəri başqa kimsə onu dəyişib.
             *
             * Məsələn:
             *   Biz oxuduq: version=3
             *   Biz yeni event yazmaq istəyirik: expectedVersion=3
             *   Amma bazada artıq version=4 var → KONFLİKT!
             */
            if ($currentVersion !== $expectedVersion) {
                throw new \RuntimeException(
                    "Optimistic locking uğursuz oldu! " .
                    "Aggregate '{$aggregateId}' üçün gözlənilən versiya: {$expectedVersion}, " .
                    "bazadakı versiya: {$currentVersion}. " .
                    "Bu o deməkdir ki, başqa proses bu aggregate-i sizdən əvvəl dəyişib. " .
                    "Yenidən oxuyub cəhd edin (retry)."
                );
            }

            /**
             * ADDIM 3: Event-ləri cədvələ yaz.
             *
             * Hər event üçün versiya artırılır.
             * Event-lər sırayla yazılır — ardıcıllıq vacibdir!
             * Məsələn: OrderCreated (v1) → ItemAdded (v2) → OrderConfirmed (v3)
             * Bu sıranı dəyişmək olmaz.
             */
            foreach ($events as $index => $event) {
                /** @var DomainEvent $event */

                $version = $expectedVersion + $index + 1;

                DB::connection(self::CONNECTION)->table(self::TABLE)->insert([
                    /**
                     * Event-in öz unikal ID-si.
                     * uuid_create() PHP-nin UUID generatorudur.
                     */
                    'id' => uuid_create(),

                    /**
                     * Bu event hansı Aggregate-ə aiddir.
                     * Bir sifarişin bütün event-ləri eyni aggregate_id-ə malikdir.
                     */
                    'aggregate_id' => $aggregateId,

                    /**
                     * Aggregate-in tipi — class adını saxlayırıq.
                     * Gələcəkdə event-ləri oxuyanda hansı Aggregate class-ını
                     * istifadə edəcəyimizi bilmək üçün.
                     */
                    'aggregate_type' => 'Order',

                    /**
                     * Event-in tipi — DomainEvent class-ının tam adı (FQCN).
                     * Deserializasiya zamanı bu class-ı yaradacağıq.
                     * get_class() tam namespace ilə qaytarır:
                     *   "Src\Order\Domain\Events\OrderCreatedEvent"
                     */
                    'event_type' => get_class($event),

                    /**
                     * Event-in data-sı — JSON formatında.
                     * toArray() metodu event-in bütün lazımi sahələrini qaytarır.
                     */
                    'payload' => json_encode($event->toArray()),

                    /**
                     * Metadata — əlavə kontekst.
                     * Event-in ID-si və baş vermə vaxtı metadata-da saxlanılır.
                     * Real layihədə: user_id, ip_address, correlation_id də əlavə olunardı.
                     */
                    'metadata' => json_encode([
                        'event_id'    => $event->eventId(),
                        'occurred_at' => $event->occurredAt()->format('Y-m-d H:i:s.u'),
                    ]),

                    /**
                     * Versiya nömrəsi — ardıcıl artır.
                     * Bu nömrə Aggregate-in "yaşını" göstərir.
                     */
                    'version' => $version,

                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * BİR AGGREGATE-İN BÜTÜN EVENT-LƏRİNİ AL
     * ========================================
     *
     * Bu metod Aggregate-i "yenidən qurmaq" (reconstitution) üçün istifadə olunur.
     *
     * PROSES (Event Replay):
     * 1. Aggregate-in bütün event-lərini versiya sırasıyla oxu.
     * 2. Boş bir Aggregate yarat.
     * 3. Hər event-i sırayla "apply" et — Aggregate öz vəziyyətini qurur.
     * 4. Bütün event-lər tətbiq edildikdən sonra Aggregate cari vəziyyətdə olur.
     *
     * ANALOGİYA:
     * Git repo-nu clone edəndə: bütün commit-lər sırayla tətbiq olunur → son vəziyyət əldə olunur.
     *
     * PERFORMANS QEYDI:
     * Çox event olan Aggregate-lər üçün bu yavaş ola bilər.
     * Həlli: Snapshot pattern — müəyyən versiyada Aggregate-in vəziyyətini saxlayıb,
     * yalnız ondan sonrakı event-ləri replay etmək.
     * Bu layihədə snapshot implementasiya etmirik, amma real layihədə lazım ola bilər.
     *
     * @param string $aggregateId Aggregate UUID-si
     * @return array Event sətirləri (hər biri: id, aggregate_id, event_type, payload, version, ...)
     */
    public function getEventsForAggregate(string $aggregateId): array
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->where('aggregate_id', $aggregateId)
            ->orderBy('version', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * BÜTÜN EVENT-LƏRİ AL — Proyeksiya yenidən qurma (rebuild) üçün
     * ================================================================
     *
     * Bu metod Event Store-dakı BÜTÜN event-ləri qaytarır.
     * Əsasən proyeksiya (Read Model) yenidən qurmaq üçün istifadə olunur.
     *
     * NƏ VAXT LAZIMDIR?
     * 1. Proyeksiyada xəta tapıldı → düzəlib yenidən qurulur.
     * 2. Yeni proyeksiya əlavə olunur → keçmiş event-lər emal edilir.
     * 3. Proyeksiya strukturu dəyişdi → köhnəsini silib yenidən qurulur.
     *
     * XƏBƏRDARLIQ:
     * Böyük sistemlərdə bu metod milyonlarla sətir qaytara bilər!
     * Real layihədə bunun əvəzinə:
     * - cursor() ilə streaming — yaddaşda hamısını saxlamır
     * - chunk() ilə parçalama — 1000-1000 oxuyur
     * - event_type filteri — yalnız lazımi event-lər
     *
     * @return array Bütün event-lər (created_at sırasıyla)
     */
    public function getAllEvents(): array
    {
        return DB::connection(self::CONNECTION)
            ->table(self::TABLE)
            ->orderBy('created_at', 'asc')
            ->orderBy('version', 'asc')
            ->get()
            ->toArray();
    }
}
