<?php

declare(strict_types=1);

namespace Src\Order\Domain\Entities;

use Src\Order\Domain\Events\OrderCancelledEvent;
use Src\Order\Domain\Events\OrderConfirmedEvent;
use Src\Order\Domain\Events\OrderCreatedEvent;
use Src\Order\Domain\Events\OrderItemAddedEvent;
use Src\Order\Domain\Events\OrderPaidEvent;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Order\Domain\ValueObjects\OrderItem;
use Src\Order\Domain\ValueObjects\OrderStatus;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Domain\DomainEvent;
use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Infrastructure\EventSourcing\EventSourcedAggregateRoot;

/**
 * EVENT SOURCED ORDER — Event Sourcing İlə İşləyən Sifariş Aggregate-i
 * =====================================================================
 *
 * Bu class, adi Order entity-nin Event Sourcing versiyasıdır.
 * Eyni biznes qaydalarını tətbiq edir, amma VƏZİYYƏT İDARƏ EDİLMƏ yanaşması tamamilə fərqlidir.
 *
 * ADİ ORDER (src/Order/Domain/Entities/Order.php):
 * ------------------------------------------------
 * - Vəziyyəti birbaşa dəyişir: $this->status = OrderStatus::confirmed();
 * - DB-dən oxuyanda: SELECT * FROM orders WHERE id = ?
 * - Event yalnız bildiriş üçündür.
 *
 * EVENT SOURCED ORDER (bu class):
 * --------------------------------
 * - Vəziyyəti HEÇ VAXT birbaşa dəyişmir!
 * - Hər əməliyyat event yaradır → event vəziyyəti dəyişir.
 * - DB-dən oxuyanda: Event Store-dan bütün event-lər oxunur → sırayla apply edilir.
 * - Event həm saxlama, həm bildiriş üçündür.
 *
 * ANALOGİYA — BANK HESABI:
 * ========================
 * Adi yanaşma: balansınız 4750 AZN-dir. Vəssalam.
 * Event Sourcing: +5000 maaş, -200 market, -50 taksi... Hər əməliyyat qeydə alınıb.
 *   Balans = bütün əməliyyatların nəticəsidir, birbaşa saxlanmır.
 *   İstənilən tarixə qayıdıb balanı hesablaya bilərsiniz.
 *
 * NƏYƏ İKİ FƏRQLI ORDER CLASS VAR?
 * =================================
 * Bu layihə öyrənmə məqsədlidir. Real layihədə adətən birini seçirsiniz:
 * - Sadə domenlər üçün → adi Order (state-based)
 * - Mürəkkəb, audit-critical domenlər üçün → EventSourcedOrder
 * İkisini birlikdə göstəririk ki, fərqi aydın görəsiniz.
 *
 * METOD STRUKTURU:
 * ================
 * Hər biznes əməliyyatı iki hissədən ibarətdir:
 *
 * 1. BİZNES METODU (create, addItem, confirm, cancel, markAsPaid):
 *    - Biznes qaydalarını yoxlayır (validation).
 *    - recordThat() ilə event yaradır.
 *    - Heç vaxt birbaşa state dəyişmir!
 *
 * 2. APPLY METODU (applyOrderCreatedEvent, applyOrderConfirmedEvent, ...):
 *    - Event-ə əsasən vəziyyəti dəyişir.
 *    - Heç bir validation ETMİR — çünki biznes metodu artıq yoxlayıb.
 *    - Həm yeni event yaradılanda, həm tarixdən replay edəndə çağırılır.
 *
 * NƏYƏ APPLY METODLARI VALİDASİYA ETMİR?
 * Çünki tarixdən replay edəndə keçmişdə baş vermiş hadisəni "rədd etmək" olmaz.
 * O zaman validasiya keçib, event yaranıb. İndi sadəcə vəziyyəti bərpa edirik.
 */
class EventSourcedOrder extends EventSourcedAggregateRoot
{
    /**
     * Sifarişin unikal identifikatoru.
     * OrderCreatedEvent tətbiq edildikdə təyin olunur.
     */
    private ?OrderId $orderId = null;

    /**
     * Sifarişi verən istifadəçinin ID-si.
     */
    private ?string $userId = null;

    /**
     * Sifarişin cari statusu.
     * Hər status dəyişikliyi event vasitəsilə baş verir.
     */
    private ?OrderStatus $status = null;

    /**
     * Sifarişin cəmi məbləği (qəpiklərlə).
     * Hər məhsul əlavə edildikdə yenidən hesablanır.
     */
    private int $totalAmount = 0;

    /**
     * Valyuta kodu (məs: 'AZN').
     */
    private string $currency = 'AZN';

    /**
     * Sifarişdəki məhsulların siyahısı.
     * Hər OrderItemAddedEvent tətbiq edildikdə böyüyür.
     *
     * @var array<int, array{product_id: string, quantity: int, price_amount: int, price_currency: string}>
     */
    private array $items = [];

    /**
     * Sifarişin yaranma vaxtı.
     */
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * YENİ SİFARİŞ YARAT (Factory Method)
     * ====================================
     * Adi Order::create() ilə eyni biznes məntiqinə malikdir,
     * amma fərq ondadır ki, state birbaşa dəyişmir — event yaradılır.
     *
     * AXIN:
     * 1. Boş EventSourcedOrder yaradılır (heç bir sahəsi təyin olunmayıb).
     * 2. recordThat(OrderCreatedEvent) çağırılır.
     * 3. recordThat() daxilində applyOrderCreatedEvent() çağırılır → sahələr təyin olunur.
     * 4. Event uncommittedEvents siyahısına əlavə olunur → sonra Event Store-a yazılacaq.
     *
     * ANALOGİYA:
     * Bank hesabı açanda: "Hesab açıldı" əməliyyatı qeydə alınır.
     * Balans 0-dır, amma bu "əməliyyat"dır, "vəziyyət" deyil.
     *
     * @param string $userId Sifarişi verən istifadəçi
     * @return self Yeni yaradılmış Event Sourced sifariş
     */
    public static function create(string $userId): self
    {
        $order = new self();

        /**
         * recordThat() — EventSourcedAggregateRoot-dan gəlir.
         * Bu metod:
         * 1. applyOrderCreatedEvent()-i çağırır → state dəyişir.
         * 2. Event-i uncommittedEvents-ə əlavə edir → DB-yə yazılacaq.
         * 3. Event-i domainEvents-ə əlavə edir → dispatch olunacaq.
         */
        $order->recordThat(new OrderCreatedEvent(
            orderId: OrderId::generate()->value(),
            userId: $userId,
        ));

        return $order;
    }

    /**
     * TARİXDƏN YENİDƏN QUR (Reconstitution from Event History)
     * ==========================================================
     * Event Store-dan oxunan event-lərlə aggregate-in vəziyyətini bərpa edir.
     *
     * BU PROSES:
     * 1. Boş EventSourcedOrder yaradılır.
     * 2. Bütün keçmiş event-lər sırayla tətbiq olunur (replay).
     * 3. Nəticədə aggregate cari vəziyyətə gəlir.
     *
     * VACİB FƏRQ:
     * - create() → recordThat() → uncommittedEvents-ə əlavə edir (yeni event-lər).
     * - fromHistory() → replayEvents() → uncommittedEvents-ə əlavə ETMİR (köhnə event-lər).
     *
     * ANALOGİYA:
     * Git repo-nu clone edəndə: bütün commit-lər sırayla tətbiq olunur.
     * Amma bu commit-lər "yeni" sayılmır — onlar artıq remote-da var.
     *
     * @param DomainEvent[] $events Tarixdəki event-lər (versiya sırasıyla)
     * @return self Bərpa olunmuş aggregate
     */
    public static function fromHistory(array $events): self
    {
        $order = new self();

        /**
         * replayEvents() — EventSourcedAggregateRoot-dan gəlir.
         * Hər event üçün applyEvent() çağırır, amma uncommittedEvents-ə əlavə ETMİR.
         * Çünki bu event-lər artıq Event Store-dadır.
         */
        $order->replayEvents($events);

        return $order;
    }

    /**
     * SİFARİŞƏ MƏHSUL ƏLAVƏ ET
     * ==========================
     * Adi Order-dan fərq: birbaşa items array-ə push etmirik.
     * Əvvəlcə event yaradırıq, event vəziyyəti dəyişir.
     *
     * VALİDASİYA:
     * - Yalnız PENDING statusunda məhsul əlavə etmək olar.
     * - Bu qayda adi Order-da da eynidir — biznes qaydası dəyişmir.
     *
     * @param string $productId Məhsulun ID-si
     * @param int    $quantity  Miqdar
     * @param Money  $price     Vahid qiyməti
     *
     * @throws DomainException Sifariş PENDING statusunda deyilsə
     */
    public function addItem(string $productId, int $quantity, Money $price): void
    {
        /**
         * Biznes qaydası yoxlaması — bu yalnız biznes metodunda olur.
         * apply metodunda OLMAZ çünki tarixdən replay edəndə keçmiş event-i rədd etmək olmaz.
         */
        if ($this->status === null || !$this->status->isPending()) {
            throw new DomainException(
                "Yalnız gözləyən (PENDING) sifarişə məhsul əlavə etmək olar. " .
                "Cari status: " . ($this->status?->value() ?? 'təyin olunmayıb')
            );
        }

        $this->recordThat(new OrderItemAddedEvent(
            orderId: $this->orderId->value(),
            productId: $productId,
            quantity: $quantity,
            priceAmount: $price->amount(),
            priceCurrency: $price->currency(),
        ));
    }

    /**
     * SİFARİŞİ TƏSDİQLƏ (PENDING -> CONFIRMED)
     * ==========================================
     * Admin sifarişi yoxlayıb təsdiqləyəndə çağırılır.
     *
     * State Machine qaydası: yalnız PENDING-dən CONFIRMED-ə keçmək olar.
     * Bu qaydanı OrderStatus::canTransitionTo() metodu yoxlayır.
     *
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function confirm(): void
    {
        $newStatus = OrderStatus::confirmed();

        if ($this->status === null || !$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status?->value()}' statusundan 'confirmed' statusuna keçə bilməz."
            );
        }

        $this->recordThat(new OrderConfirmedEvent(
            orderId: $this->orderId->value(),
        ));
    }

    /**
     * SİFARİŞİ LƏĞV ET (PENDING/CONFIRMED -> CANCELLED)
     * ==================================================
     * Müştəri və ya sistem sifarişi ləğv edəndə çağırılır.
     *
     * COMPENSATING TRANSACTION:
     * Saga pattern-də ödəniş uğursuz olanda bu metod çağırılır.
     * Bu, "geri al" əməliyyatıdır — keçmişi silmək yox, yeni event əlavə etmək.
     *
     * ANALOGİYA:
     * Mühasibatlıqda səhv yazılış tapdınız. Onu silmirsiniz — əks yazılış (storno) edirsiniz.
     * Event Sourcing-də də eynidir: OrderCancelled event-i əlavə olunur, keçmiş event-lər qalır.
     *
     * @param string $reason Ləğv etmə səbəbi
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function cancel(string $reason = ''): void
    {
        $newStatus = OrderStatus::cancelled();

        if ($this->status === null || !$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status?->value()}' statusundan ləğv edilə bilməz. " .
                "Yalnız 'pending' və ya 'confirmed' statusunda ləğv etmək olar."
            );
        }

        $this->recordThat(new OrderCancelledEvent(
            orderId: $this->orderId->value(),
            reason: $reason,
        ));
    }

    /**
     * ÖDƏNİŞ TAMAMLANDI (CONFIRMED -> PAID)
     * =======================================
     * Payment bounded context ödənişi uğurla emal edəndə çağırılır.
     *
     * Bu event-dən sonra sifariş göndərilməyə hazırdır.
     * Saga pattern-in növbəti addımı: anbar modulu məhsulu hazırlayır.
     *
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function markAsPaid(): void
    {
        $newStatus = OrderStatus::paid();

        if ($this->status === null || !$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status?->value()}' statusundan 'paid' statusuna keçə bilməz."
            );
        }

        $this->recordThat(new OrderPaidEvent(
            orderId: $this->orderId->value(),
            totalAmount: $this->totalAmount,
        ));
    }

    // =========================================================================
    // APPLY METODLARI — Event-ləri Vəziyyətə Çevirmə
    // =========================================================================
    //
    // Bu metodlar EventSourcedAggregateRoot::applyEvent() tərəfindən avtomatik çağırılır.
    // Konvensiya: "apply" + EventClassName = metod adı.
    //
    // QAYDA: Bu metodlarda HEÇ BİR VALİDASİYA OLMAZ!
    // Çünki:
    // 1. Yeni event yaradılanda — validasiya artıq biznes metodunda olub.
    // 2. Tarixdən replay edəndə — keçmişdə baş vermiş hadisəni rədd etmək olmaz.
    //
    // ANALOGİYA:
    // Redux reducer-lər kimi düşünün:
    //   action → reducer → new state
    //   event  → apply   → new state
    // Reducer-də heç vaxt validasiya olmamalıdır — action yaradılanda olmalıdır.
    // =========================================================================

    /**
     * OrderCreatedEvent TƏTBİQ ET
     * ============================
     * Sifarişin bütün əsas sahələrini ilkin dəyərlərlə təyin edir.
     * Bu, aggregate-in "doğulma" anıdır — bütün sahələr burada başlayır.
     *
     * ANALOGİYA:
     * Bank hesabı açılanda: hesab nömrəsi, sahibi, valyuta təyin olunur, balans 0-dır.
     */
    protected function applyOrderCreatedEvent(OrderCreatedEvent $event): void
    {
        $this->orderId = new OrderId($event->orderId());
        $this->userId = $event->userId();
        $this->status = OrderStatus::pending();
        $this->totalAmount = 0;
        $this->currency = 'AZN';
        $this->items = [];
        $this->createdAt = new \DateTimeImmutable();

        /** Entity base class-ın id sahəsini təyin et */
        $this->id = $event->orderId();
    }

    /**
     * OrderItemAddedEvent TƏTBİQ ET
     * ==============================
     * Yeni məhsulu items siyahısına əlavə edir və cəmi məbləği yenidən hesablayır.
     *
     * DİQQƏT: Burada OrderItem Value Object istifadə etmirik — sadəcə array saxlayırıq.
     * Çünki Event Sourcing-də aggregate vəziyyəti mümkün qədər sadə olmalıdır.
     * Replay zamanı Value Object yaratmaq əlavə yük ola bilər.
     *
     * ANALOGİYA:
     * Alış-veriş səbətinə yeni məhsul qoymaq və kassiyer ekranındakı cəmi yeniləmək.
     */
    protected function applyOrderItemAddedEvent(OrderItemAddedEvent $event): void
    {
        $this->items[] = [
            'product_id'     => $event->productId(),
            'quantity'        => $event->quantity(),
            'price_amount'   => $event->priceAmount(),
            'price_currency' => $event->priceCurrency(),
        ];

        $this->currency = $event->priceCurrency();

        /** Cəmi məbləği yenidən hesabla — bütün item-lərin lineTotal cəmi */
        $this->recalculateTotal();
    }

    /**
     * OrderConfirmedEvent TƏTBİQ ET
     * ==============================
     * Statusu CONFIRMED-ə dəyişir. Vəssalam — başqa heç nə dəyişmir.
     *
     * ANALOGİYA: Müqaviləyə imza atılması — sənədin statusu "təsdiqləndi" olur.
     */
    protected function applyOrderConfirmedEvent(OrderConfirmedEvent $event): void
    {
        $this->status = OrderStatus::confirmed();
    }

    /**
     * OrderCancelledEvent TƏTBİQ ET
     * ==============================
     * Statusu CANCELLED-ə dəyişir.
     *
     * DİQQƏT: Ləğv etmə səbəbi (reason) event-dədir, amma aggregate state-də saxlamırıq.
     * Çünki səbəbi bilmək lazım olanda event-in özünə baxarıq (Event Store-dan).
     * Aggregate yalnız cari vəziyyəti saxlamalıdır — tarix Event Store-dadır.
     *
     * ANALOGİYA: Uçuş ləğv edildi. Bilet statusu "ləğv" olur.
     * Səbəb (hava şəraiti, texniki nasazlıq) ayrıca qeyddə saxlanılır.
     */
    protected function applyOrderCancelledEvent(OrderCancelledEvent $event): void
    {
        $this->status = OrderStatus::cancelled();
    }

    /**
     * OrderPaidEvent TƏTBİQ ET
     * =========================
     * Statusu PAID-ə dəyişir.
     *
     * ANALOGİYA: Kassada ödəniş qəbul edildikdə qəbz verilir — sifariş "ödənildi" statusuna keçir.
     */
    protected function applyOrderPaidEvent(OrderPaidEvent $event): void
    {
        $this->status = OrderStatus::paid();
    }

    // =========================================================================
    // DAXILI YARDIMÇI METODLAR
    // =========================================================================

    /**
     * CƏMİ MƏBLƏĞİ YENİDƏN HESABLA
     * ================================
     * Bütün item-lərin (qiymət x miqdar) cəmini hesablayır.
     *
     * Bu metod hər OrderItemAddedEvent tətbiq edildikdə çağırılır.
     * Adi Order-dakı calculateTotal() ilə eyni məntiqdir.
     */
    private function recalculateTotal(): void
    {
        $total = 0;

        foreach ($this->items as $item) {
            /** Hər item-in xətt cəmi: qiymət x miqdar */
            $total += $item['price_amount'] * $item['quantity'];
        }

        $this->totalAmount = $total;
    }

    // =========================================================================
    // GETTER METODLARI
    // =========================================================================
    // Aggregate-in cari vəziyyətini oxumaq üçün.
    // Setter YOXDUR — vəziyyət yalnız event-lər vasitəsilə dəyişir.

    public function orderId(): ?OrderId
    {
        return $this->orderId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function status(): ?OrderStatus
    {
        return $this->status;
    }

    public function totalAmount(): int
    {
        return $this->totalAmount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * @return array<int, array{product_id: string, quantity: int, price_amount: int, price_currency: string}>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function itemCount(): int
    {
        return count($this->items);
    }

    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
