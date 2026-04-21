<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\EventSourcing;

use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\DomainEvent;

/**
 * EVENT SOURCED AGGREGATE ROOT — Event Sourcing ilə İşləyən Aggregate Bazası
 * ===========================================================================
 *
 * Bu abstract class, Event Sourcing pattern-ini istifadə edən Aggregate-lər üçün əsas class-dır.
 * Adi AggregateRoot-dan fərqi:
 *
 * ADİ AGGREGATE ROOT (src/Shared/Domain/AggregateRoot.php):
 * - Vəziyyəti (state) birbaşa dəyişir: $this->status = OrderStatus::confirmed();
 * - Event yalnız "xəbərdarlıq" üçündür — başqa modullara bildirmək üçün.
 * - DB-dən oxuyanda: SELECT * FROM orders WHERE id = ?
 *
 * EVENT SOURCED AGGREGATE ROOT (bu class):
 * - Vəziyyəti HEÇ VAXT birbaşa dəyişmir!
 * - Əvvəlcə event yaradılır, sonra event aggregate-ə "apply" edilir.
 * - apply() metodu event-ə əsasən vəziyyəti dəyişir.
 * - DB-dən oxuyanda: event_store-dan bütün event-lər oxunur → sırayla apply edilir.
 *
 * AGGREGATE-İN VƏZİYYƏTİ NECƏ QURULUR?
 * ======================================
 *
 * 1. YENİ ƏMƏLIYYAT (yeni event yaratma):
 *    $order->confirm()
 *      → recordThat(new OrderConfirmedEvent(...))
 *        → applyEvent(event)  // vəziyyəti dəyişir
 *        → uncommittedEvents-ə əlavə edir  // sonra Event Store-a yazılacaq
 *
 * 2. TARİXDƏN YENİDƏN QURMA (reconstitution from history):
 *    EventSourcedOrder::fromHistory($events)
 *      → hər event üçün applyEvent(event)
 *      → aggregate cari vəziyyətə gəlir
 *    Bu zaman uncommittedEvents-ə heç nə əlavə edilmir — çünki bu event-lər artıq Event Store-dadır.
 *
 * ANALOGİYA:
 * ----------
 * Şahmat oyununu düşünün:
 * - ADİ YANAŞMA: Yalnız lövhənin son vəziyyətini saxlayırsınız. Oyunu geri qaytara bilməzsiniz.
 * - EVENT SOURCİNG: Hər gedişi qeyd edirsiniz (e2-e4, d7-d5, ...).
 *   İstənilən vaxt oyunu başdan oynaya bilərsiniz (replay).
 *   İstənilən gedişə qayıda bilərsiniz (temporal query).
 *   Lövhənin son vəziyyəti — gedişləri sırayla tətbiq etməklə əldə olunur.
 *
 * VERSİYA İZLƏMƏ:
 * ---------------
 * $version sahəsi aggregate-in neçə event-dən keçdiyini göstərir.
 * Event Store-a yazanda optimistic locking yoxlaması üçün istifadə olunur.
 * fromHistory()-də hər apply-da version artır.
 */
abstract class EventSourcedAggregateRoot extends AggregateRoot
{
    /**
     * Aggregate-in cari versiyası.
     * Hər apply olunan event bu nömrəni 1 artırır.
     * Event Store-a yazanda expectedVersion kimi istifadə olunur.
     *
     * version = 0 → heç bir event yoxdur (yeni aggregate)
     * version = 5 → 5 event tətbiq olunub
     */
    protected int $version = 0;

    /**
     * Hələ Event Store-a yazılmamış event-lər.
     * recordThat() metodu ilə əlavə olunur.
     * EventStore::append() çağırıldıqdan sonra təmizlənir.
     *
     * Fərq:
     * - AggregateRoot::$domainEvents → dispatch üçün (Laravel event dispatcher)
     * - Bu array → Event Store-a yazmaq üçün
     *
     * @var DomainEvent[]
     */
    private array $uncommittedEvents = [];

    /**
     * YENİ EVENT QEYDƏ AL
     * ====================
     * Bu metod hər biznes əməliyyatında çağırılır.
     *
     * Məsələn: confirm() metodu daxilində:
     *   $this->recordThat(new OrderConfirmedEvent($this->orderId));
     *
     * PROSES:
     * 1. Event-i apply et — vəziyyəti dəyişdir (applyEvent çağırılır)
     * 2. Event-i uncommittedEvents siyahısına əlavə et — sonra DB-yə yazılacaq
     * 3. Event-i domainEvents siyahısına da əlavə et — dispatch üçün
     *
     * NƏYƏ "recordThat" adlandırılır?
     * Çünki "bu hadisənin baş verdiyini qeyd et" deməkdir.
     * "record" = qeydə almaq, "that" = bu hadisəni.
     * Bəzi layihələrdə "raise", "emit", "apply" kimi adlar da istifadə olunur.
     */
    protected function recordThat(DomainEvent $event): void
    {
        /**
         * Əvvəlcə event-i tətbiq et — aggregate-in vəziyyəti dəyişsin.
         * Bu, "event → state change" prinsipini təmin edir.
         * Əgər apply uğursuz olarsa, event qeydə alınmayacaq.
         */
        $this->applyEvent($event);

        /**
         * Event-i "yazılmamış" siyahıya əlavə et.
         * Bu event-lər EventStore::append() ilə DB-yə yazılacaq.
         */
        $this->uncommittedEvents[] = $event;

        /**
         * Eyni event-i AggregateRoot-un domainEvents siyahısına da əlavə et.
         * Bu, Laravel event dispatcher ilə dispatch etmək üçündür.
         * Beləliklə, həm Event Store-a yazılır, həm də dinləyicilərə bildirilir.
         */
        $this->recordEvent($event);
    }

    /**
     * EVENT-İ AGGREGATE-Ə TƏTBİQ ET
     * ==============================
     * Bu metod event-ə əsasən aggregate-in vəziyyətini dəyişir.
     *
     * PROSES:
     * 1. Event class-ının adını alır: "OrderCreatedEvent"
     * 2. "apply" prefix əlavə edir: "applyOrderCreatedEvent"
     * 3. Bu adda metodu çağırır: $this->applyOrderCreatedEvent($event)
     *
     * Bu konvensiya (naming convention) sayəsində hər event üçün
     * ayrı "applyXxxEvent" metodu yazmaq lazımdır.
     * Bu metodlar aggregate-in vəziyyətini dəyişir.
     *
     * NƏYƏİSƏ BƏNZƏYIR?
     * Laravel-in event listener-ləri: handle() metodu.
     * Redux-un reducer-ləri: action-a əsasən state dəyişir.
     * Bu da eyni prinsipdir: event-ə əsasən state dəyişir.
     *
     * @param DomainEvent $event Tətbiq ediləcək event
     * @throws \BadMethodCallException apply metodu tapılmadıqda
     */
    private function applyEvent(DomainEvent $event): void
    {
        /**
         * Event class-ının qısa adını al (namespace olmadan).
         * Məsələn: "Src\Order\Domain\Events\OrderCreatedEvent" → "OrderCreatedEvent"
         */
        $className = (new \ReflectionClass($event))->getShortName();

        /**
         * apply + class adı = metod adı.
         * Məsələn: "apply" + "OrderCreatedEvent" = "applyOrderCreatedEvent"
         */
        $method = 'apply' . $className;

        /**
         * Bu metod child class-da mövcud olmalıdır.
         * Məsələn: EventSourcedOrder class-ında applyOrderCreatedEvent() olmalıdır.
         * Əgər yoxdursa — developer unudub, xəta atırıq.
         */
        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException(
                "'{$method}' metodu " . static::class . " class-ında tapılmadı. " .
                "Hər event tipi üçün müvafiq apply metodu yazılmalıdır. " .
                "Məsələn: {$className} üçün {$method}(DomainEvent \$event) metodu əlavə edin."
            );
        }

        /**
         * Metodu çağır — aggregate-in vəziyyəti dəyişəcək.
         * Bu metod yalnız sahələri dəyişir, heç bir validation etmir.
         * Validation biznes metodlarında (confirm(), cancel() və s.) olur.
         */
        $this->$method($event);

        /**
         * Versiya nömrəsini artır.
         * Hər tətbiq olunan event versiya +1 edir.
         */
        $this->version++;
    }

    /**
     * TARİXDƏN YENİDƏN QUR (Reconstitution from History)
     * ====================================================
     * Event Store-dan oxunan event-lərdən aggregate-i yenidən qurur.
     *
     * BU PROSES:
     * 1. Boş aggregate yaradılır (child class-ın static fromHistory()-si çağırır)
     * 2. Hər event sırayla applyEvent() ilə tətbiq olunur
     * 3. Aggregate cari vəziyyətə gəlir
     *
     * VACİB: Bu metod uncommittedEvents-ə heç nə əlavə ETMİR!
     * Çünki bu event-lər artıq Event Store-dadır — yenidən yazmaq lazım deyil.
     * Yalnız aggregate-in yaddaşdakı (in-memory) vəziyyətini bərpa edirik.
     *
     * ANALOGİYA:
     * Git repo-nu clone edəndə bütün commit-lər tətbiq olunur → son vəziyyət əldə olunur.
     * Amma bu commit-lər "yeni commit" kimi qeydə alınmır — onlar artıq tarixdədir.
     *
     * @param DomainEvent[] $events Tarixdəki event-lər (versiya sırasıyla)
     */
    protected function replayEvents(array $events): void
    {
        foreach ($events as $event) {
            /**
             * applyEvent() çağırılır — vəziyyət dəyişir, version artır.
             * Amma recordThat() çağırılMIR — çünki bu event artıq DB-dədir.
             */
            $this->applyEvent($event);
        }
    }

    /**
     * Hələ Event Store-a yazılmamış event-ləri al.
     * EventStore::append() çağırıldıqdan sonra clearUncommittedEvents() çağırılmalıdır.
     *
     * @return DomainEvent[]
     */
    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }

    /**
     * Yazılmamış event-lər siyahısını təmizlə.
     * Event Store-a uğurla yazıldıqdan sonra çağırılır.
     */
    public function clearUncommittedEvents(): void
    {
        $this->uncommittedEvents = [];
    }

    /**
     * Aggregate-in cari versiya nömrəsi.
     * Event Store-a yazanda expectedVersion kimi göndərilir.
     */
    public function version(): int
    {
        return $this->version;
    }
}
