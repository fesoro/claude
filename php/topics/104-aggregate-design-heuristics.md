# Aggregate Design Heuristics (Senior)

## Mündəricat
1. [Aggregate nədir?](#aggregate-nədir)
2. [Dizayn Qaydaları](#dizayn-qaydaları)
3. [Ölçü Problemi](#ölçü-problemi)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Aggregate nədir?

```
DDD-də Aggregate — bir unit kimi treat edilən entity qrupu.
Aggregate Root xaricdən giriş nöqtəsidir.

Qaydalar:
  1. Xaricdən yalnız Aggregate Root-a müraciət edilir
  2. Aggregate daxili consistency-ni özü qoruyur
  3. Aggregate-lər arası istinad yalnız ID vasitəsilə
  4. Bir transaksiyada — bir Aggregate

Order Aggregate:
  ┌─────────────────────────────────┐
  │ Order (Aggregate Root)          │
  │   - orderId                     │
  │   - status                      │
  │   - ┌─────────────────────┐     │
  │     │ OrderItem (Entity)  │     │
  │     │   - productId       │     │
  │     │   - quantity        │     │
  │     │   - price           │     │
  │     └─────────────────────┘     │
  │   - ┌─────────────────────┐     │
  │     │ ShippingAddress (VO)│     │
  │     └─────────────────────┘     │
  └─────────────────────────────────┘
```

---

## Dizayn Qaydaları

```
Heuristic 1 — Real invariant-ları qoruyun:
  "Order total $10,000-dən çox ola bilməz" → Order Aggregate
  Bu qaydanı qorumaq üçün OrderItem-lər bir Aggregate-də olmalıdır.

Heuristic 2 — Kiçik Aggregate-lər:
  Böyük Aggregate = performance problemi + lock contention
  Lazım olmayan entity-ləri xaricə çıxarın

  YANLIŞDIR:
  Customer Aggregate → [Profile, Orders[], Invoices[], Reviews[]]

  DÜZGÜNDÜR:
  Customer Aggregate → [Profile]
  Order Aggregate    → [Items[], customerId (ref)]
  Invoice Aggregate  → [Lines[], customerId (ref)]

Heuristic 3 — Digər Aggregate-lərə ID ilə istinad:
  Order { customerId: CustomerId } // CustomerId, Customer deyil
  Bu lazy loading-i mümkün edir, coupling azaldır

Heuristic 4 — Eventual consistency qəbul edin:
  Bir transaksiyada bir Aggregate dəyişir.
  Digər Aggregate-lər event vasitəsilə sonradan yenilənir.

  Order.place() → OrderPlacedEvent
  InventoryService → event qulaq asır → stok azaldır (ayrı transaksiya)
```

---

## Ölçü Problemi

```
Aggregate çox böyükdür əlamətləri:
  ✗ Yükləmək uzun çəkir
  ✗ Çox entity-ni lock edir
  ✗ Transaction timeout-ları
  ✗ Çoxlu invariant (hamısını qorumaq çətindir)

Aggregate çox kiçikdir əlamətləri:
  ✗ Invariant-lar qorunamır
  ✗ Consistency üçün distributed transaction lazımdır
  ✗ Business rule-lar servisə "sızdı"

Test sualı:
  "Bu iki entity həmişə birlikdə dəyişirmi?"
  Bəli → eyni Aggregate
  Xeyr → ayrı Aggregate + Event
```

---

## PHP İmplementasiyası

```php
<?php
namespace App\Domain\Order;

class Order
{
    private OrderId $id;
    private CustomerId $customerId;
    private OrderStatus $status;
    /** @var OrderItem[] */
    private array $items = [];
    private Money $maxAllowedTotal;

    private function __construct(
        OrderId $id,
        CustomerId $customerId,
        Money $maxAllowedTotal,
    ) {
        $this->id              = $id;
        $this->customerId      = $customerId;
        $this->status          = OrderStatus::DRAFT;
        $this->maxAllowedTotal = $maxAllowedTotal;
    }

    public static function create(CustomerId $customerId): self
    {
        return new self(
            OrderId::generate(),
            $customerId,
            Money::of(10000, 'USD'),
        );
    }

    public function addItem(ProductId $productId, int $qty, Money $unitPrice): void
    {
        $this->guardDraft();

        $newItem  = new OrderItem($productId, $qty, $unitPrice);
        $newTotal = $this->calculateTotal()->add($newItem->subtotal());

        // Invariant: max $10,000
        if ($newTotal->greaterThan($this->maxAllowedTotal)) {
            throw new OrderLimitExceededException(
                "Sifariş limiti: {$this->maxAllowedTotal}"
            );
        }

        $this->items[] = $newItem;
    }

    public function place(): void
    {
        $this->guardDraft();

        if (empty($this->items)) {
            throw new EmptyOrderException("Ən az bir məhsul lazımdır");
        }

        $this->status = OrderStatus::PLACED;
        // Domain event — InventoryService bu event-ə subscribe olub stoku azaldacaq
        $this->recordEvent(new OrderPlacedEvent($this->id, $this->customerId, $this->items));
    }

    private function guardDraft(): void
    {
        if (!$this->status->isDraft()) {
            throw new InvalidOrderStateException("Sifariş artıq yerləşdirilib");
        }
    }

    private function calculateTotal(): Money
    {
        return array_reduce(
            $this->items,
            fn(Money $carry, OrderItem $item) => $carry->add($item->subtotal()),
            Money::zero('USD'),
        );
    }
}
```

---

## İntervyu Sualları

- Aggregate boundary-ni necə müəyyən edirsiniz?
- "Bir transaksiyada bir Aggregate" — bunu necə tətbiq edirsiniz?
- Aggregate-lər arası istinad ID ilə niyə daha yaxşıdır?
- Böyük Aggregate-nin performance-a təsiri nədir?
- Eventual consistency Aggregate dizaynında necə rol oynayır?
- Order-Customer əlaqəsini bir Aggregate-də saxlamaq doğrudurmu?
