<?php

declare(strict_types=1);

namespace Src\Order\Domain\Entities;

use Src\Order\Domain\Events\OrderCancelledEvent;
use Src\Order\Domain\Events\OrderConfirmedEvent;
use Src\Order\Domain\Events\OrderCreatedEvent;
use Src\Order\Domain\Events\OrderPaidEvent;
use Src\Order\Domain\ValueObjects\Address;
use Src\Order\Domain\ValueObjects\OrderId;
use Src\Order\Domain\ValueObjects\OrderItem;
use Src\Order\Domain\ValueObjects\OrderStatus;
use Src\Product\Domain\ValueObjects\Money;
use Src\Shared\Domain\AggregateRoot;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * ORDER AGGREGATE ROOT
 * ====================
 * Sifariş — bu bounded context-in əsas Aggregate Root-udur.
 *
 * AGGREGATE ROOT QAYDALARI (xatırlatma):
 * 1. Bütün dəyişikliklər YALNIZ bu class vasitəsilə edilir.
 * 2. Xaricdən OrderItem-ə birbaşa müraciət OLMAZ — yalnız Order.addItem() ilə.
 * 3. Hər dəyişiklik biznes qaydalarını yoxlayır (invariant-lar).
 * 4. Hər vacib dəyişiklikdə Domain Event qeydə alınır.
 *
 * BU CLASS-DA İSTİFADƏ OLUNAN PATTERN-LƏR:
 *
 * 1. AGGREGATE ROOT: Əlaqəli obyektləri (OrderItem, Address) bir yerdə idarə edir.
 *
 * 2. FACTORY METHOD: Order::create() — static factory method, constructor əvəzinə.
 *    Nəyə? Constructor-da biznes logikası olmamalıdır, create() metodunda ola bilər.
 *
 * 3. STATE MACHINE: OrderStatus keçidləri yoxlanılır (canTransitionTo).
 *    Nəyə? Yanlış keçidlərin (məs: DELIVERED → PENDING) qarşısını alır.
 *
 * 4. DOMAIN EVENTS: Hər əməliyyat event qeydə alır (recordEvent).
 *    Nəyə? Digər modullara (Payment, Notification) xəbər vermək üçün.
 *
 * 5. ENCAPSULATION: Bütün sahələr private-dir, yalnız method-larla dəyişdirilir.
 *    Nəyə? Biznes qaydalarını bypass etmək mümkün olmasın.
 */
class Order extends AggregateRoot
{
    /**
     * Sifarişdəki məhsul sətirləri.
     * Bu array-ə birbaşa əlavə etmək olmaz — yalnız addItem() metodu ilə.
     *
     * @var OrderItem[]
     */
    private array $items = [];

    /**
     * Private constructor — xaricdən "new Order()" yazmaq olmaz.
     * Yalnız Order::create() factory method-u ilə sifariş yaradıla bilər.
     *
     * NƏYƏ PRİVATE?
     * - create() metodu biznes qaydalarını yoxlayır və event qeydə alır.
     * - Əgər constructor public olsa, bu qaydaları bypass etmək olar.
     * - Bu "encapsulation" prinsipinin nümunəsidir.
     */
    private function __construct(
        private OrderId $orderId,
        private string $userId,
        private Address $address,
        private OrderStatus $status,
        private Money $totalAmount,
        private \DateTimeImmutable $createdAt,
    ) {
        // Entity base class-ın id sahəsini təyin et
        $this->id = $orderId->value();
    }

    /**
     * YENİ SİFARİŞ YARAT (Factory Method)
     * ====================================
     * Bu static method yeni Order yaratmağın YEGANƏ yoludur.
     *
     * FACTORY METHOD NƏDİR?
     * - Obyekt yaratma məntiqini bir yerə toplayır.
     * - Constructor-dan fərqli olaraq, burada biznes logikası ola bilər.
     * - Event qeydə almaq, default dəyərlər təyin etmək və s.
     *
     * AXIN:
     * 1. Yeni OrderId yaradılır (UUID).
     * 2. Status PENDING (gözləyir) olaraq təyin olunur.
     * 3. Boş cəmi məbləğ ilə başlayır (item əlavə olunduqca hesablanacaq).
     * 4. OrderCreatedEvent qeydə alınır.
     *
     * @param string  $userId  Sifarişi verən istifadəçinin ID-si
     * @param Address $address Çatdırılma ünvanı
     * @return self Yeni yaradılmış sifariş
     */
    public static function create(string $userId, Address $address): self
    {
        $order = new self(
            orderId: OrderId::generate(),
            userId: $userId,
            address: $address,
            status: OrderStatus::pending(),
            totalAmount: new Money(0, 'AZN'),
            createdAt: new \DateTimeImmutable(),
        );

        // Domain Event qeydə al — "Sifariş yaradıldı" hadisəsi
        $order->recordEvent(new OrderCreatedEvent(
            orderId: $order->orderId->value(),
            userId: $userId,
        ));

        return $order;
    }

    /**
     * Mövcud datadan Order yarat — DB-dən oxuyanda istifadə olunur (reconstitution).
     * Bu metod event qeydə ALMAZ çünki keçmişdə baş vermiş əməliyyatdır.
     */
    public static function reconstitute(
        OrderId $orderId,
        string $userId,
        Address $address,
        OrderStatus $status,
        Money $totalAmount,
        array $items,
        \DateTimeImmutable $createdAt,
    ): self {
        $order = new self($orderId, $userId, $address, $status, $totalAmount, $createdAt);
        $order->items = $items;

        return $order;
    }

    /**
     * SİFARİŞƏ MƏHSUL ƏLAVƏ ET
     * ==========================
     * OrderItem-i birbaşa array-ə push etmirik — bu metod vasitəsilə əlavə edirik.
     *
     * NƏYƏ METOD VASİTƏSİLƏ?
     * 1. Statusu yoxlayırıq — yalnız PENDING sifarişə məhsul əlavə oluna bilər.
     * 2. Cəmi məbləği yenidən hesablayırıq.
     * 3. Gələcəkdə: stok yoxlaması, endirim hesablaması əlavə edə bilərik.
     *
     * @throws DomainException Sifariş PENDING statusunda deyilsə
     */
    public function addItem(OrderItem $item): void
    {
        if (!$this->status->isPending()) {
            throw new DomainException(
                "Yalnız gözləyən (PENDING) sifarişə məhsul əlavə etmək olar. " .
                "Cari status: {$this->status->value()}"
            );
        }

        $this->items[] = $item;
        $this->calculateTotal();
    }

    /**
     * SİFARİŞİ TƏSDİQLƏ (PENDING → CONFIRMED)
     * ==========================================
     * Admin sifarişi yoxlayıb təsdiqləyəndə çağırılır.
     *
     * STATE MACHINE YOXLAMASI:
     * - canTransitionTo() metodu PENDING → CONFIRMED keçidinin mümkün olduğunu yoxlayır.
     * - Əgər sifariş artıq CANCELLED və ya DELIVERED statusundadırsa, xəta atacaq.
     *
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function confirm(): void
    {
        $newStatus = OrderStatus::confirmed();

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status->value()}' statusundan 'confirmed' statusuna keçə bilməz."
            );
        }

        $this->status = $newStatus;

        $this->recordEvent(new OrderConfirmedEvent(
            orderId: $this->orderId->value(),
        ));
    }

    /**
     * ÖDƏNİŞ TAMAMLANDI (CONFIRMED → PAID)
     * ======================================
     * Payment bounded context ödənişi uğurla emal edəndə çağırılır.
     *
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function markAsPaid(): void
    {
        $newStatus = OrderStatus::paid();

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status->value()}' statusundan 'paid' statusuna keçə bilməz."
            );
        }

        $this->status = $newStatus;

        $this->recordEvent(new OrderPaidEvent(
            orderId: $this->orderId->value(),
            totalAmount: $this->totalAmount->amount(),
        ));
    }

    /**
     * SİFARİŞ GÖNDƏRİLDİ (PAID → SHIPPED)
     * =====================================
     * Karqo şirkəti sifarişi qəbul edəndə çağırılır.
     *
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function ship(): void
    {
        $newStatus = OrderStatus::shipped();

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status->value()}' statusundan 'shipped' statusuna keçə bilməz."
            );
        }

        $this->status = $newStatus;
    }

    /**
     * SİFARİŞ ÇATDIRILDI (SHIPPED → DELIVERED)
     * =========================================
     * Müştəri sifarişi qəbul edəndə çağırılır.
     *
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function deliver(): void
    {
        $newStatus = OrderStatus::delivered();

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status->value()}' statusundan 'delivered' statusuna keçə bilməz."
            );
        }

        $this->status = $newStatus;
    }

    /**
     * SİFARİŞİ LƏĞV ET (PENDING/CONFIRMED → CANCELLED)
     * ==================================================
     * Müştəri və ya sistem sifarişi ləğv edəndə çağırılır.
     *
     * DİQQƏT: Yalnız PENDING və ya CONFIRMED statusunda ləğv etmək olar.
     * Ödəniş edilibsə (PAID), artıq ləğv etmək olmaz — refund lazımdır.
     *
     * SAGA PATTERN ilə əlaqə:
     * - Əgər ödəniş uğursuz olarsa, Saga bu metodu çağırır (compensating transaction).
     * - Bu "geri al" əməliyyatıdır — Saga pattern-in vacib hissəsidir.
     *
     * @param string $reason Ləğv etmə səbəbi
     * @throws DomainException Status keçidi mümkün deyilsə
     */
    public function cancel(string $reason = ''): void
    {
        $newStatus = OrderStatus::cancelled();

        if (!$this->status->canTransitionTo($newStatus)) {
            throw new DomainException(
                "Sifariş '{$this->status->value()}' statusundan ləğv edilə bilməz. " .
                "Yalnız 'pending' və ya 'confirmed' statusunda ləğv etmək olar."
            );
        }

        $this->status = $newStatus;

        $this->recordEvent(new OrderCancelledEvent(
            orderId: $this->orderId->value(),
            reason: $reason,
        ));
    }

    /**
     * CƏMİ MƏBLƏĞİ HESABLA (private method)
     * ========================================
     * Bütün OrderItem-lərin lineTotal() cəmini hesablayır.
     *
     * NƏYƏ PRİVATE?
     * - Cəmi məbləğ yalnız sifariş daxilində hesablanmalıdır.
     * - Xaricdən çağırılmamalıdır — addItem() avtomatik çağırır.
     * - Bu "encapsulation" prinsipinə uyğundur.
     *
     * HESABLAMA:
     * Hər OrderItem: qiymət x miqdar = lineTotal
     * Cəmi: bütün lineTotal-ların cəmi
     *
     * Məsələn:
     *   Item 1: 10 AZN x 2 = 20 AZN
     *   Item 2: 25 AZN x 1 = 25 AZN
     *   Cəmi: 45 AZN
     */
    private function calculateTotal(): void
    {
        $total = new Money(0, 'AZN');

        foreach ($this->items as $item) {
            $total = $total->add($item->lineTotal());
        }

        $this->totalAmount = $total;
    }

    // ===== GETTER METODLARI =====
    // Bu metodlar Aggregate-in vəziyyətini oxumaq üçündür.
    // Setter metodu YOXDUR — dəyişikliklər yalnız biznes metodları ilə olur.

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function totalAmount(): Money
    {
        return $this->totalAmount;
    }

    /**
     * @return OrderItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
