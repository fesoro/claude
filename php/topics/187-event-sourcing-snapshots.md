# Event Sourcing: Snapshots (Lead)

## Mündəricat
1. [Problem: Uzun Event Axını](#problem-uzun-event-axını)
2. [Snapshot nədir?](#snapshot-nədir)
3. [Snapshot Strategiyaları](#snapshot-strategiyaları)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Uzun Event Axını

```
Event Sourcing-də Aggregate vəziyyəti event-lərdən rebuild edilir:

  Order.load(orderId):
    events = eventStore.load(orderId)  // 1000 event!
    order  = new Order()
    for event in events:
      order.apply(event)               // 1000 dəfə apply
    return order

Problem:
  OrderService ilə işləyən müştərinin 3 il əvvəlindən
  10,000 event-i var.
  Hər sorğuda 10,000 event replay etmək → yavaş!

  Nümunə:
    Müştərinin bank hesabında 5 ildə 50,000 tranzaksiya.
    Balansı öyrənmək üçün 50,000 event apply etmək?
```

---

## Snapshot nədir?

```
Snapshot — müəyyən anda Aggregate-in tam vəziyyətinin
serialize edilmiş kopyası.

Event Store strukturu (snapshot ilə):

  [Snapshot @v5000: {balance: 1250, status: active, ...}]
  [Event @v5001: Deposit(100)]
  [Event @v5002: Withdrawal(50)]
  [Event @v5003: Deposit(200)]

Yükləmə:
  1. Son snapshot-u al  (v5000)
  2. Snapshot-dan sonrakı event-ləri al (v5001..v5003)
  3. Yalnız 3 event apply et
  → 50,000 əvəzinə 3 event!

Vizual:
  ──── events ────────────────────────────────────────────►
  e1 e2 e3 ... e999 [SNAP] e1001 e1002 ... e1999 [SNAP] e2001 ...
                              ↑
                       Buradan yüklə (son snapshot + sonrakı)
```

---

## Snapshot Strategiyaları

```
Ne vaxt snapshot çıxarmaq?

Strategiya 1 — Hər N event-dən sonra:
  if eventCount % 100 == 0:
    snapshot.save(aggregate)
  Sadə, amma həmişə lazım olmaya bilər

Strategiya 2 — Zaman əsaslı:
  Hər gecə snapshot çıxar
  CRON job

Strategiya 3 — Event sayı həddini keçdikdə:
  aggregate.load() zamanı event sayı > threshold:
    → Snapshot çıxar, növbəti dəfə daha az event

Strategiya 4 — Manual / on-demand:
  Admin triggerlər
  Migration zamanı

Snapshot storage:
  Eyni event store-da (ayrı cədvəl/collection)
  Ayrıca cache (Redis) — sürətli oxuma, amma volatile

Snapshot versioning:
  Aggregate schema dəyişsə snapshot köhnə format-dadır!
  Snapshot-un öz versiyası olmalıdır.
  Köhnə snapshot → upcaster → cari format
```

---

## PHP İmplementasiyası

```php
<?php
namespace App\EventSourcing;

// Snapshot DTO
class Snapshot
{
    public function __construct(
        public readonly string $aggregateId,
        public readonly string $aggregateType,
        public readonly int    $version,
        public readonly array  $state,
        public readonly int    $snapshotVersion, // Snapshot schema versiyası
        public readonly string $createdAt,
    ) {}
}

// Snapshot Store
interface SnapshotStore
{
    public function save(Snapshot $snapshot): void;
    public function findLatest(string $aggregateId): ?Snapshot;
}
```

```php
<?php
// Aggregate Repository — snapshot ilə
class BankAccountRepository
{
    private const SNAPSHOT_THRESHOLD = 50; // Hər 50 event-dən sonra

    public function __construct(
        private EventStore    $eventStore,
        private SnapshotStore $snapshots,
    ) {}

    public function findById(string $id): BankAccount
    {
        $snapshot = $this->snapshots->findLatest($id);

        if ($snapshot !== null) {
            // Snapshot-dan başla, yalnız sonrakı event-ləri al
            $account = BankAccount::fromSnapshot($snapshot);
            $events  = $this->eventStore->loadAfterVersion($id, $snapshot->version);
        } else {
            // Snapshot yoxdur — bütün event-ləri al
            $account = new BankAccount();
            $events  = $this->eventStore->load($id);
        }

        foreach ($events as $event) {
            $account->apply($event);
        }

        return $account;
    }

    public function save(BankAccount $account): void
    {
        $newEvents = $account->pullUncommittedEvents();
        $this->eventStore->append($account->getId(), $newEvents);

        // Snapshot threshold yoxla
        if ($account->getVersion() % self::SNAPSHOT_THRESHOLD === 0) {
            $this->takeSnapshot($account);
        }
    }

    private function takeSnapshot(BankAccount $account): void
    {
        $snapshot = new Snapshot(
            aggregateId:     $account->getId(),
            aggregateType:   BankAccount::class,
            version:         $account->getVersion(),
            state:           $account->toSnapshotState(),
            snapshotVersion: BankAccount::SNAPSHOT_VERSION,
            createdAt:       (new \DateTimeImmutable())->format(\DateTime::ATOM),
        );

        $this->snapshots->save($snapshot);
    }
}
```

```php
<?php
// BankAccount Aggregate — snapshot support
class BankAccount
{
    public const SNAPSHOT_VERSION = 2; // Schema versiyası

    private string $id;
    private float  $balance = 0.0;
    private string $status  = 'active';
    private int    $version = 0;
    private array  $uncommittedEvents = [];

    public static function fromSnapshot(Snapshot $snapshot): self
    {
        $account = new self();

        // Snapshot versiyasına görə upcasting
        $state = $snapshot->snapshotVersion < self::SNAPSHOT_VERSION
            ? self::upcastSnapshot($snapshot->state, $snapshot->snapshotVersion)
            : $snapshot->state;

        $account->id      = $state['id'];
        $account->balance = $state['balance'];
        $account->status  = $state['status'];
        $account->version = $snapshot->version;

        return $account;
    }

    public function toSnapshotState(): array
    {
        return [
            'id'      => $this->id,
            'balance' => $this->balance,
            'status'  => $this->status,
        ];
    }

    private static function upcastSnapshot(array $state, int $fromVersion): array
    {
        // v1 → v2: 'active' field əlavə edilib
        if ($fromVersion === 1) {
            $state['status'] = 'active'; // default
        }
        return $state;
    }
}
```

---

## İntervyu Sualları

- Event Sourcing-də Snapshot niyə lazımdır?
- Snapshot nə zaman çıxarmaq lazımdır?
- Snapshot-un öz versiyası olmalıdır — niyə?
- Snapshot çıxarmaq Aggregate state-ini dəyişirmi?
- Redis-də snapshot saxlamağın üstünlüyü və riski nədir?
- Snapshot threshold-u necə seçərsiniz?
