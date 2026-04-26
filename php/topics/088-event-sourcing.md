# Event Sourcing (Senior)

## Mündəricat
1. [Event Sourcing Nədir?](#event-sourcing-nədir)
2. [Traditional CRUD vs Event Sourcing](#traditional-crud-vs-event-sourcing)
3. [Event Store](#event-store)
4. [Aggregate və Aggregate Root](#aggregate-və-aggregate-root)
5. [Domain Events](#domain-events)
6. [Projections / Read Models](#projections--read-models)
7. [Event Replay](#event-replay)
8. [Snapshots](#snapshots)
9. [Event Versioning / Upcasting](#event-versioning--upcasting)
10. [Eventual Consistency](#eventual-consistency)
11. [Event Sourcing + CQRS](#event-sourcing--cqrs)
12. [spatie/laravel-event-sourcing](#spatielaravel-event-sourcing)
13. [Real-World Nümunə 1: Bank Account](#real-world-nümunə-1-bank-account)
14. [Real-World Nümunə 2: Order Lifecycle](#real-world-nümunə-2-order-lifecycle)
15. [Üstünlüklər və Mənfi Cəhətlər](#üstünlüklər-və-mənfi-cəhətlər)
16. [Nə Vaxt İstifadə Etməli / Etməməli](#nə-vaxt-i̇stifadə-etməli--etməməli)
17. [İntervyu Sualları](#intervyu-sualları)

---

## Event Sourcing Nədir?

Event Sourcing — tətbiqin state-ini saxlamaq üçün yalnız son vəziyyəti deyil, **baş vermiş bütün hadisələrin (events) sıralanmış log-unu** saxlamaq prinsipidir.

Ənənəvi sistemdə sual: **"Cari vəziyyət nədir?"** → Verilənlər bazasındakı son row.

Event Sourcing-də sual: **"Bu vəziyyətə necə gəlib çatdıq?"** → Bütün event-lərin replay-i.

```
Ənənəvi CRUD:           Event Sourcing:
┌──────────────┐        ┌─────────────────────────────────────────┐
│ accounts     │        │ account_events (append-only log)         │
├──────────────┤        ├─────────────────────────────────────────┤
│ id: 1        │        │ AccountOpened    { amount: 0 }          │
│ balance: 350 │   ←    │ MoneyDeposited   { amount: 500 }        │
│ status: active│       │ MoneyWithdrawn   { amount: 200 }        │
└──────────────┘        │ MoneyDeposited   { amount: 100 }        │
                        │ InterestAdded    { amount: -50 }        │
                        └─────────────────────────────────────────┘
                        Replay: 0 + 500 - 200 + 100 - 50 = 350 ✓
```

---

## Traditional CRUD vs Event Sourcing

| Xüsusiyyət | Traditional CRUD | Event Sourcing |
|------------|-----------------|----------------|
| Nə saxlanılır | Son vəziyyət (current state) | Bütün dəyişiklik tarixçəsi (event log) |
| Write əməliyyatı | UPDATE accounts SET balance=350 | INSERT INTO events (AccountDeposited) |
| Read əməliyyatı | SELECT * FROM accounts WHERE id=1 | Event-ləri replay et, state hesabla |
| Tarixçə | Yoxdur (üzərinə yazıldı) | Tam audit trail |
| Geri qayıtmaq (undo) | Çətin, manual backup lazım | Event-ləri yenidən replay et |
| Debugging | Yalnız cari state görünür | Xronoloji event axınına bax |
| Scalability | Write/Read eyni model | CQRS ilə tamamilə ayrıla bilər |
| Mürəkkəblik | Aşağı | Yüksək |
| GDPR uyğunluq | Asan (UPDATE/DELETE) | Çətin (event-lər immutable-dır) |
| Schema dəyişikliyi | Migration | Event versioning / upcasting |
| Eventual consistency | Anlıq (strong consistency) | Gecikmə ola bilər |

---

## Event Store

Event Store — event-lərin saxlandığı **append-only** (yalnız əlavə olunur, silinmir, dəyişdirilmir) verilənlər bazası cədvəlidir.

### Niyə Sadəcə Son State Deyil?

```
Sual: "5 may tarixində hesabda nə qədər pul vardı?"
CRUD cavabı: Bilmirik. Yalnız indiki vəziyyəti saxlayırıq.
Event Sourcing cavabı: Event-ləri 5 mayadək replay et → dəqiq cavab.

Sual: "Kim bu transferi etdi? Niyə?"
CRUD cavabı: Bilmirik. Nə vaxt dəyişdiyini belə bilmirik.
Event Sourcing cavabı: Event-in metadata-sında user_id, reason, timestamp var.

Sual: "Yeni bir 'loyalty points' feature əlavə etsək, köhnə dataya necə tətbiq edəcəyik?"
CRUD cavabı: Complex data migration.
Event Sourcing cavabı: Bütün köhnə event-ləri yeni Projector ilə replay et.

Sual: "Sistemdə bug var idi, bir sıra hesablamalar yanlış aparıldı — necə düzəldirik?"
CRUD cavabı: Hansı record-ların zərər çəkdiyini bilmirik, manual düzəliş lazım.
Event Sourcing cavabı: Bug-ı fix et, bütün event-ləri replay et, read model özü düzəlir.
```

### Event Store Strukturu

*Event Store Strukturu üçün kod nümunəsi:*
```sql
CREATE TABLE stored_events (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aggregate_uuid   CHAR(36)     NOT NULL,       -- Hansı aggregate-ə aid
    aggregate_version INT UNSIGNED NOT NULL,       -- Optimistic locking üçün
    event_class      VARCHAR(255) NOT NULL,        -- PHP class adı (tam namespace)
    event_properties JSON         NOT NULL,        -- Event data (serialized)
    meta_data        JSON         NOT NULL,        -- user_id, ip, timestamp, etc.
    created_at       DATETIME     NOT NULL,

    INDEX idx_aggregate_uuid (aggregate_uuid),
    INDEX idx_created_at (created_at),
    INDEX idx_event_class (event_class),

    -- Optimistic locking: eyni aggregate-ə eyni version ilə iki event yazıla bilməz
    UNIQUE KEY uq_aggregate_version (aggregate_uuid, aggregate_version)
);
```

### Optimistic Locking Necə İşləyir?

```
Thread A: Hesab yüklənir (version=5)
Thread B: Hesab yüklənir (version=5)
Thread A: Pul çəkir → version=6 yazır → uğurlu
Thread B: Pul çəkir → version=6 yazmaq istəyir → DUPLICATE KEY ERROR!
Thread B: Yenidən yükləyir (version=6-ya baxır), yenidən cəhd edir
Nəticə: Race condition-lar database-level-da qarşısı alınır
```

---

## Aggregate və Aggregate Root

**Aggregate** — bir "iş vahidi" kimi birlikdə dəyişdirilən, ardıcıl (consistent) olması lazım olan obyektlər qrupu.

**Aggregate Root** — Aggregate-in xarici dünya ilə əlaqə nöqtəsi. Yalnız root vasitəsilə daxilə girilir.

```
┌─────────────────────────────────────────┐
│           Order Aggregate               │
│  ┌──────────────────────────────────┐   │
│  │        Order (Root)              │   │
│  │  id, status, total, created_at   │   │
│  └─────────────┬────────────────────┘   │
│                │                         │
│       ┌────────┴────────┐               │
│       │                 │               │
│  ┌────▼────┐      ┌─────▼──────┐       │
│  │OrderItem│      │  Shipping  │       │
│  │quantity │      │  address   │       │
│  │price    │      │  method    │       │
│  └─────────┘      └────────────┘       │
└─────────────────────────────────────────┘

Kənardan yalnız Order root-una müraciət edilir.
OrderItem-ə birbaşa access yoxdur — yalnız Order.addItem() vasitəsilə.
```

### Event Sourcing-də Aggregate Necə İşləyir?

```
1. Command gəlir: $account->withdrawMoney(500)
2. Aggregate yoxlayır: Balans kifayətdirmi? Hesab açıqdırmı? (guard conditions)
3. Event yaradılır: new MoneyWithdrawn(['amount' => 500])
4. recordThat() çağırılır: event qeyd olunur (hələ persist edilmir)
5. applyMoneyWithdrawn() çağırılır: $this->balance -= 500
6. ->persist() çağırılanda: event store-a yazılır
7. Projector-lar və Reactor-lar dispatch edilir
```

---

## Domain Events

Domain Events — biznesdə baş vermiş faktı təsvir edən immutable (dəyişməz) obyektlər. **Keçmiş zaman** ilə adlandırılır: `OrderPlaced`, `PaymentFailed`, `UserRegistered`.

*Domain Events — biznesdə baş vermiş faktı təsvir edən immutable (dəyiş üçün kod nümunəsi:*
```php
// Bu kod Event Sourcing-də immutable domain event-in strukturunu göstərir
<?php

namespace App\Domain\Account\Events;

use Spatie\EventSourcing\StorableEvents\ShouldBeStored;

// StorableEvent — event store-a yazılacaq event
final class MoneyDeposited implements ShouldBeStored
{
    public function __construct(
        public readonly string $accountUuid,
        public readonly int    $amountInCents,  // Pul dəyərlərini integer saxla (float deyil!)
        public readonly string $description,
        public readonly string $performedByUserId,
    ) {}

    // Metadata — event ilə birlikdə saxlanacaq əlavə məlumat
    public function metaData(): array
    {
        return [
            'user_id'    => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }
}

// Event adlandırma konvensiyası:
// ✓ MoneyDeposited      (keçmiş zaman, konkret biznes hadisəsi)
// ✓ OrderShipped        (keçmiş zaman)
// ✓ UserEmailVerified   (keçmiş zaman)
// ❌ DepositMoney        (əmr/command kimi oxunur)
// ❌ MoneyDepositEvent   (artıq "Event" sözü gərəksizdir)
// ❌ UpdateBalance       (CRUD əməliyyatına bənzər)
```

### Event Versioning

Event-lərin schema-sı zamanla dəyişə bilər:

*Event-lərin schema-sı zamanla dəyişə bilər üçün kod nümunəsi:*
```php
// v1 (köhnə) — float istifadə etmişdik
final class MoneyDeposited implements ShouldBeStored
{
    public function __construct(
        public readonly float $amount,          // ❌ float: dəqiqlik problemi
    ) {}
}

// v2 (yeni) — cents-ə keçdik, currency əlavə etdik
final class MoneyDeposited implements ShouldBeStored
{
    public function __construct(
        public readonly int    $amountInCents,  // ✓ integer: dəqiq
        public readonly string $currency = 'AZN', // yeni sahə
    ) {}
}
// Köhnə event-lər event store-da v1 formatında qalır.
// Upcaster v1-i oxuyanda v2-yə çevirir.
```

---

## Projections / Read Models

Projection — event-ləri dinləyən və onlardan **oxuma üçün optimallaşdırılmış** cədvəl (read model) quran komponentdir.

```
Event Store (Write Side)          Read Models (Query Side)
┌─────────────────────┐           ┌──────────────────────────┐
│ AccountOpened       │           │ account_summaries         │
│ MoneyDeposited x100 │  ──────►  │ id | balance | tx_count  │
│ MoneyWithdrawn x50  │           │ 1  | 350.00  | 150       │
│ AccountClosed       │           └──────────────────────────┘
└─────────────────────┘           ┌──────────────────────────┐
                                   │ monthly_reports           │
                           ──────► │ month   | total_in        │
                                   │ 2024-01 | 50000.00       │
                                   │ 2024-02 | 62000.00       │
                                   └──────────────────────────┘
                                   ┌──────────────────────────┐
                                   │ Elasticsearch index       │
                           ──────► │ Full-text search          │
                                   │ Faceted filtering         │
                                   └──────────────────────────┘
```

### Projection Rebuild

*Projection Rebuild üçün kod nümunəsi:*
```bash
# Event store-da bütün data var, read model-i istənilən vaxt yenidən qura bilərik.
# Bu Event Sourcing-in ən güclü xüsusiyyətlərindən biridir.

# Bütün projector-ları replay et
php artisan event-sourcing:replay

# Yalnız müəyyən projector-ı rebuild et
php artisan event-sourcing:replay --projector=AccountSummaryProjector

# Nümunə istifadə: Yeni "loyalty_points" sütunu əlavə etdik
# 1. Migration yaz: accounts cədvəlinə loyalty_points sütunu əlavə et
# 2. AccountSummaryProjector-a onMoneyDeposited handler-ında points hesablamasını əlavə et
# 3. php artisan event-sourcing:replay --projector=AccountSummaryProjector
# 4. Bütün köhnə purchase-lər üçün points hesablandı — retroaktiv!
```

---

## Event Replay

Event Replay — event store-dakı bütün event-ləri yenidən oxuyaraq state-i yenidən qurmaq prosesi.

### Faydaları

```
1. Tam Audit Trail
   Hər zaman "Kim nə etdi, nə vaxt?" sualına cavab ver.
   Compliance (SOX, HIPAA, PCI DSS) tələblərini ödə.
   Bank əməliyyatlarının tam tarixçəsi.

2. Time Travel (Temporal Queries)
   "3 ay əvvəl bu sifarişin statusu nə idi?"
   Event-ləri həmin tarixə qədər replay et → dəqiq cavab.
   "Keçən ay sonundakı inventory səviyyəsi nə idi?" → Replay et.

3. Bug Fix + Retroaktiv Düzəliş
   Kodda hesablama səhvi tapıldı → fix et.
   Bütün köhnə event-ləri yenidən işlə → read model düzəlir.
   Data itirilmədi, manual fix lazım deyil.

4. Yeni Feature Retroaktiv Tətbiqi
   "Loyalty points" feature əlavə edildi.
   Köhnə bütün purchase event-lərini yeni Projector ilə replay et
   → Bütün müştərilər öz köhnə alışlarına görə point əldə edir.

5. Production Bug Reproduce
   İstifadəçinin bütün event-lərini export edib development-da replay et
   → Dəqiq eyni vəziyyəti yarat → bug asanlıqla tapılır.
```

---

## Snapshots

### Niyə Lazımdır?

```
Snapshot olmadan:
Hesab 5 il fəaliyyətdədir → 10,000 event var.
Hər sorğuda 10,000 event-i replay etmək lazımdır → çox yavaş!
RAM istifadəsi: hər event deserialization olunur.

Snapshot ilə:
Hər 50 event-dən bir snapshot çəkilirsə:
→ Ən son snapshot (event 9,950) yüklənir
→ Yalnız son 50 event replay edilir
→ 200x daha sürətli!
```

### Snapshot İmplementasiyası

*Snapshot İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod Event Sourcing-də Snapshot ilə aggregate-in sürətli yüklənməsini göstərir
<?php

namespace App\Domain\Account;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\Snapshot;

class BankAccountAggregate extends AggregateRoot
{
    private int    $balanceInCents = 0;
    private string $status = 'active';
    private int    $transactionCount = 0;
    private string $ownerName = '';
    private string $currency = 'AZN';

    // ─── Snapshot Support ──────────────────────────────────────

    // Snapshot-a yazılacaq state — bütün mühüm properties
    public function getState(): array
    {
        return [
            'balance_in_cents'  => $this->balanceInCents,
            'status'            => $this->status,
            'transaction_count' => $this->transactionCount,
            'owner_name'        => $this->ownerName,
            'currency'          => $this->currency,
        ];
    }

    // Snapshot-dan state-i restore et
    public function restoreSnapshot(Snapshot $snapshot): void
    {
        $state = $snapshot->state;
        $this->balanceInCents   = $state['balance_in_cents'];
        $this->status           = $state['status'];
        $this->transactionCount = $state['transaction_count'];
        $this->ownerName        = $state['owner_name'];
        $this->currency         = $state['currency'] ?? 'AZN'; // backward compat
    }

    // Neçə event-dən bir snapshot çəkilsin
    public function snapshotEvery(): ?int
    {
        return 50; // Hər 50 event-dən bir snapshot
        // null qaytarılsa snapshot mexanizmi deaktiv olur
    }
}
```

### Snapshot Cədvəli

*Snapshot Cədvəli üçün kod nümunəsi:*
```sql
CREATE TABLE snapshots (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aggregate_uuid   CHAR(36)    NOT NULL,
    aggregate_version INT UNSIGNED NOT NULL,
    state            JSON        NOT NULL,
    created_at       DATETIME    NOT NULL,

    INDEX idx_aggregate_uuid (aggregate_uuid),
    INDEX idx_aggregate_version (aggregate_uuid, aggregate_version DESC)
);
-- retrieve() zamanı: ən son snapshot tapılır, sonra yalnız ondan sonrakı event-lər replay edilir
```

---

## Event Versioning / Upcasting

Upcasting — köhnə format event-ləri yeni formata çevirmək prosesi. Event store-dakı data **dəyişdirilmir**, oxunarkən dönüşüm edilir.

*Upcasting — köhnə format event-ləri yeni formata çevirmək prosesi. Eve üçün kod nümunəsi:*
```php
// Bu kod köhnə event formatını yeni formata çevirən Upcaster-i göstərir
<?php

namespace App\Domain\Account\Upcasters;

// Köhnə event: { "amount": 150.50 }
// Yeni event:  { "amountInCents": 15050, "currency": "USD" }

class MoneyDepositedV1ToV2Upcaster
{
    public function upcast(array $eventData): array
    {
        // Köhnə format: float dollar
        if (isset($eventData['amount']) && !isset($eventData['amountInCents'])) {
            // float dollar → int cents (dəqiqlik itkisi olmadan)
            $eventData['amountInCents'] = (int) round($eventData['amount'] * 100);
            $eventData['currency'] = 'USD'; // köhnə sistem USD istifadə edirdi
            unset($eventData['amount']);
        }

        return $eventData;
    }
}

// config/event-sourcing.php
return [
    'upcasters' => [
        \App\Domain\Account\Events\MoneyDeposited::class => [
            \App\Domain\Account\Upcasters\MoneyDepositedV1ToV2Upcaster::class,
        ],
    ],
];
```

### Upcasting Strategiyaları

```
Strategiya 1: In-place upcasting (yuxarıdakı kimi)
→ Event class eyni adda qalır
→ Upcaster köhnə format yeniyə çevirir
→ Sadə, amma version history itirilir

Strategiya 2: Event adını dəyişmək
→ MoneyDeposited → MoneyDepositedV2 yaradılır
→ Event store-da köhnə event-lər MoneyDeposited olaraq qalır
→ Yeni event-lər MoneyDepositedV2 kimi yazılır
→ Hər iki tipi handle etmək lazımdır

Strategiya 3: Explicit version field
→ final class MoneyDeposited { public string $version = '2'; ... }
→ apply() methodunda version-a görə fərqli davranış
```

---

## Eventual Consistency

Event Sourcing-də write (əmr icra etmək) və read (query etmək) arasında **gecikMə** ola bilər.

```
Müştəri: "Pul köçürdüm, balansım niyə hələ dəyişmədi?"
Cavab: "Projection hələ event-i işləməyib — bir neçə millisaniyə lazımdır."

Timeline (sinxron projector):
T+0ms:   HTTP POST /transfer → Command handler işləyir
T+1ms:   TransferInitiated event → Event Store-a yazıldı
T+2ms:   Projector sinxron olaraq read model-i yeniləyir
T+3ms:   HTTP 200 OK → Müştəriyə cavab göndərildi
         (Balans artıq yenilənib — user dərhal görür)

Timeline (asinxron projector ilə):
T+0ms:   HTTP POST /transfer → Command handler işləyir
T+1ms:   TransferInitiated event → Event Store-a yazıldı
T+2ms:   HTTP 202 Accepted → Müştəriyə cavab göndərildi
T+5ms:   Queue worker event-i eşidir
T+8ms:   Projector read model-i yeniləyir
T+10ms:  Müştəri GET /balance → Artıq yeni balansı görür
```

### Eventual Consistency ilə UI Problem Həlli

*Eventual Consistency ilə UI Problem Həlli üçün kod nümunəsi:*
```php
// POST /api/accounts/transfer
public function transfer(Request $request, string $fromUuid): JsonResponse
{
    // ... validation, command handling ...

    return response()->json([
        'message'    => 'Transfer initiated successfully.',
        'transfer_id' => $transferUuid,
        'status'     => 'processing',     // "completed" deyil!
        // Client polling edə bilər:
        'poll_url'   => route('transfers.status', $transferUuid),
    ], 202); // 202 Accepted — işlənir, hələ tamamlanmayıb
}

// GET /api/transfers/{id}/status
public function status(string $transferId): JsonResponse
{
    $transfer = TransferReadModel::find($transferId);

    return response()->json([
        'transfer_id' => $transferId,
        'status'      => $transfer?->status ?? 'processing',
        'completed_at' => $transfer?->completed_at,
        'balance'     => $transfer?->new_balance_in_cents,
    ]);
}
```

---

## Event Sourcing + CQRS

Event Sourcing və CQRS (Command Query Responsibility Segregation) birlikdə istifadə edilir. CQRS write/read-i ayırır, Event Sourcing write side üçün mükəmməl mexanizmdir.

```
                    ┌─────────────────────────────────────────────────────────┐
                    │                 Laravel Application                      │
                    │                                                          │
HTTP Request ──────►│  ┌──────────────────┐    ┌──────────────────────────┐  │
                    │  │   WRITE SIDE      │    │      READ SIDE           │  │
                    │  │                  │    │                          │  │
                    │  │  CommandBus      │    │  QueryBus                │  │
                    │  │       │          │    │       │                  │  │
                    │  │  CommandHandler  │    │  QueryHandler            │  │
                    │  │       │          │    │       │                  │  │
                    │  │  AggregateRoot   │    │  ReadModel (Eloquent)    │  │
                    │  │       │          │    │  (account_summaries)     │  │
                    │  │  Domain Events   │    │                          │  │
                    │  │       │          │    └──────────────────────────┘  │
                    │  │  Event Store ────┼──────────────►                   │
                    │  │  (stored_events) │    Projectors read model yeniləyir│
                    │  └──────────────────┘                                  │
                    └─────────────────────────────────────────────────────────┘

Write DB: MySQL (stored_events) — append only, normalized
Read DB:  MySQL/Redis/Elasticsearch — denormalized, query-optimized
```

### CQRS İmplementasiyası

*CQRS İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod Event Sourcing ilə CQRS-in write (command) və read (query) tərəflərini göstərir
<?php

// ─── WRITE SIDE ────────────────────────────────────────────────

// Command — niyyəti ifadə edir, data transfer objecti
final class DepositMoneyCommand
{
    public function __construct(
        public readonly string $accountUuid,
        public readonly int    $amountInCents,
        public readonly string $description,
    ) {}
}

// Command Handler — command-i qəbul edir, aggregate-i işə salır
class DepositMoneyCommandHandler
{
    public function handle(DepositMoneyCommand $command): void
    {
        BankAccountAggregate::retrieve($command->accountUuid)
            ->deposit(
                amountInCents: $command->amountInCents,
                description: $command->description,
                ref: uniqid('DEP-'),
            )
            ->persist();
    }
}

// ─── READ SIDE ─────────────────────────────────────────────────

// Query — oxuma istəyini ifadə edir
final class GetAccountBalanceQuery
{
    public function __construct(
        public readonly string $accountUuid,
    ) {}
}

// DTO — response strukturu
final class AccountBalanceDTO
{
    public function __construct(
        public readonly string $uuid,
        public readonly int    $balanceInCents,
        public readonly string $currency,
        public readonly int    $transactionCount,
    ) {}
}

// Query Handler — yalnız read model-dən oxuyur, aggregate-ə toxunmur!
class GetAccountBalanceQueryHandler
{
    public function handle(GetAccountBalanceQuery $query): AccountBalanceDTO
    {
        // Read model-dən oxu — event replay yoxdur, sadə SQL
        $account = AccountSummary::where('uuid', $query->accountUuid)
            ->firstOrFail();

        return new AccountBalanceDTO(
            uuid: $account->uuid,
            balanceInCents: $account->balance_in_cents,
            currency: $account->currency,
            transactionCount: $account->transaction_count,
        );
    }
}

// ─── Controller ────────────────────────────────────────────────

class BankController extends Controller
{
    public function deposit(Request $request, string $uuid): JsonResponse
    {
        // WRITE: Command dispatch
        $this->dispatch(new DepositMoneyCommand(
            accountUuid: $uuid,
            amountInCents: $request->integer('amount_in_cents'),
            description: $request->string('description'),
        ));

        return response()->json(['message' => 'Deposit processed.'], 202);
    }

    public function balance(string $uuid): JsonResponse
    {
        // READ: Query dispatch — read model-dən oxu
        $dto = $this->dispatch(new GetAccountBalanceQuery($uuid));

        return response()->json([
            'balance_in_cents' => $dto->balanceInCents,
            'currency'         => $dto->currency,
        ]);
    }
}
```

---

## spatie/laravel-event-sourcing

`spatie/laravel-event-sourcing` paketi Laravel-də Event Sourcing implementasiyasını asanlaşdıran ən populyar paketdir.

*`spatie/laravel-event-sourcing` paketi Laravel-də Event Sourcing imple üçün kod nümunəsi:*
```bash
composer require spatie/laravel-event-sourcing

php artisan vendor:publish \
    --provider="Spatie\EventSourcing\EventSourcingServiceProvider" \
    --tag="event-sourcing-migrations"

php artisan migrate
```

### AggregateRoot Yaratma

*AggregateRoot Yaratma üçün kod nümunəsi:*
```php
// Bu kod spatie/laravel-event-sourcing ilə AggregateRoot-un yaradılmasını göstərir
<?php

namespace App\Domain\Account;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Events\AccountClosed;
use App\Domain\Account\Exceptions\InsufficientFunds;
use App\Domain\Account\Exceptions\AccountIsClosedException;

class BankAccountAggregate extends AggregateRoot
{
    private int    $balanceInCents = 0;
    private bool   $isClosed = false;
    private bool   $isFrozen = false;
    private string $ownerName = '';

    // ──────────────────────────────────────────
    // Command Methods (iş məntiqi + guard)
    // ──────────────────────────────────────────

    public function openAccount(string $ownerName, int $initialDepositInCents = 0): static
    {
        if ($this->ownerName !== '') {
            throw new \LogicException('Account is already opened.');
        }

        $this->recordThat(new AccountOpened(
            ownerName: $ownerName,
            initialDepositInCents: $initialDepositInCents,
        ));

        return $this; // Fluent interface — zəncir çağırışlar üçün
    }

    public function depositMoney(int $amountInCents, string $description = ''): static
    {
        $this->ensureAccountIsOpen();

        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('Deposit amount must be positive.');
        }

        $this->recordThat(new MoneyDeposited(
            amountInCents: $amountInCents,
            description: $description,
        ));

        return $this;
    }

    public function withdrawMoney(int $amountInCents, string $description = ''): static
    {
        $this->ensureAccountIsOpen();

        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('Withdrawal amount must be positive.');
        }

        if ($this->balanceInCents < $amountInCents) {
            throw new InsufficientFunds(
                "Insufficient funds. Balance: {$this->balanceInCents}, Requested: {$amountInCents}"
            );
        }

        $this->recordThat(new MoneyWithdrawn(
            amountInCents: $amountInCents,
            description: $description,
        ));

        return $this;
    }

    public function closeAccount(): static
    {
        $this->ensureAccountIsOpen();

        if ($this->balanceInCents > 0) {
            throw new \LogicException(
                "Cannot close account with remaining balance: {$this->balanceInCents} cents."
            );
        }

        $this->recordThat(new AccountClosed());

        return $this;
    }

    // ──────────────────────────────────────────
    // Apply Methods (state dəyişiklikləri)
    // Apply metodları YALNIZ state dəyişdirir.
    // Heç bir side effect etmir, guard yoxdur.
    // Həm yeni event-lər üçün, həm replay üçün çağırılır.
    // ──────────────────────────────────────────

    protected function applyAccountOpened(AccountOpened $event): void
    {
        $this->ownerName = $event->ownerName;
        $this->balanceInCents = $event->initialDepositInCents;
        $this->isClosed = false;
    }

    protected function applyMoneyDeposited(MoneyDeposited $event): void
    {
        $this->balanceInCents += $event->amountInCents;
    }

    protected function applyMoneyWithdrawn(MoneyWithdrawn $event): void
    {
        $this->balanceInCents -= $event->amountInCents;
    }

    protected function applyAccountClosed(AccountClosed $event): void
    {
        $this->isClosed = true;
    }

    // ──────────────────────────────────────────
    // Guard Helpers
    // ──────────────────────────────────────────

    private function ensureAccountIsOpen(): void
    {
        if ($this->isClosed) {
            throw new AccountIsClosedException('Account is closed.');
        }
    }

    // Test üçün getter-lər
    public function getBalance(): int  { return $this->balanceInCents; }
    public function isClosed(): bool   { return $this->isClosed; }
    public function getOwner(): string { return $this->ownerName; }
}
```

### StorableEvent

*StorableEvent üçün kod nümunəsi:*
```php
// Bu kod event store-a yazılacaq StorableEvent-in tərifi və serialization-ını göstərir
<?php

namespace App\Domain\Account\Events;

use Spatie\EventSourcing\StorableEvents\ShouldBeStored;

final class AccountOpened implements ShouldBeStored
{
    public function __construct(
        public readonly string $ownerName,
        public readonly int    $initialDepositInCents = 0,
    ) {}
}

final class MoneyDeposited implements ShouldBeStored
{
    public function __construct(
        public readonly int    $amountInCents,
        public readonly string $description = '',
    ) {}
}

final class MoneyWithdrawn implements ShouldBeStored
{
    public function __construct(
        public readonly int    $amountInCents,
        public readonly string $description = '',
    ) {}
}

final class AccountClosed implements ShouldBeStored
{
    public function __construct(
        public readonly string $reason = '',
    ) {}
}
```

### Projector (Tam Nümunə)

*Projector (Tam Nümunə) üçün kod nümunəsi:*
```php
// Bu kod event-lərdən read model quran Projector-un tam implementasiyasını göstərir
<?php

namespace App\Domain\Account\Projectors;

use Spatie\EventSourcing\EventHandlers\Projectors\Projector;
use App\Domain\Account\Events\AccountOpened;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Events\AccountClosed;
use App\Models\AccountSummary;
use App\Models\Transaction;

class AccountSummaryProjector extends Projector
{
    // Metod adları konvensiyası: on + EventClassName
    public function onAccountOpened(AccountOpened $event, string $aggregateUuid): void
    {
        AccountSummary::create([
            'uuid'              => $aggregateUuid,
            'owner_name'        => $event->ownerName,
            'balance_in_cents'  => $event->initialDepositInCents,
            'status'            => 'active',
            'transaction_count' => $event->initialDepositInCents > 0 ? 1 : 0,
            'opened_at'         => now(),
        ]);
    }

    public function onMoneyDeposited(MoneyDeposited $event, string $aggregateUuid): void
    {
        AccountSummary::where('uuid', $aggregateUuid)
            ->increment('balance_in_cents', $event->amountInCents);

        AccountSummary::where('uuid', $aggregateUuid)
            ->increment('transaction_count');

        Transaction::create([
            'account_uuid'  => $aggregateUuid,
            'type'          => 'deposit',
            'amount_cents'  => $event->amountInCents,
            'description'   => $event->description,
            'occurred_at'   => $event->createdAt(),
        ]);
    }

    public function onMoneyWithdrawn(MoneyWithdrawn $event, string $aggregateUuid): void
    {
        AccountSummary::where('uuid', $aggregateUuid)
            ->decrement('balance_in_cents', $event->amountInCents);

        AccountSummary::where('uuid', $aggregateUuid)
            ->increment('transaction_count');

        Transaction::create([
            'account_uuid'  => $aggregateUuid,
            'type'          => 'withdrawal',
            'amount_cents'  => $event->amountInCents,
            'description'   => $event->description,
            'occurred_at'   => $event->createdAt(),
        ]);
    }

    public function onAccountClosed(AccountClosed $event, string $aggregateUuid): void
    {
        AccountSummary::where('uuid', $aggregateUuid)
            ->update(['status' => 'closed', 'closed_at' => now()]);
    }
}
```

### Reactor

Reactor — event-i eşidir və **side effect** icra edir (email göndərmək, notification, başqa sistem çağırmaq). Projector-dan fərqli olaraq state saxlamır.

*Reactor — event-i eşidir və **side effect** icra edir (email göndərmək üçün kod nümunəsi:*
```php
// Bu kod event-ə reaksiya olaraq yan effekt (email, notification) yaradan Reactor-u göstərir
<?php

namespace App\Domain\Account\Reactors;

use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;
use App\Domain\Account\Events\MoneyDeposited;
use App\Domain\Account\Events\MoneyWithdrawn;
use App\Domain\Account\Events\AccountClosed;
use App\Mail\DepositConfirmation;
use App\Mail\LowBalanceWarning;
use App\Models\AccountSummary;
use Illuminate\Support\Facades\Mail;

class AccountNotificationReactor extends Reactor
{
    public function onMoneyDeposited(MoneyDeposited $event, string $aggregateUuid): void
    {
        $account = AccountSummary::where('uuid', $aggregateUuid)->first();
        if (!$account) return;

        Mail::to($account->owner_email)
            ->queue(new DepositConfirmation(
                ownerName: $account->owner_name,
                amountInCents: $event->amountInCents,
                newBalanceInCents: $account->balance_in_cents,
            ));
    }

    public function onMoneyWithdrawn(MoneyWithdrawn $event, string $aggregateUuid): void
    {
        $account = AccountSummary::where('uuid', $aggregateUuid)->first();
        if (!$account) return;

        // Aşağı balans xəbərdarlığı (100.00 AZN = 10000 cents)
        $lowBalanceThreshold = 10_000;
        if ($account->balance_in_cents < $lowBalanceThreshold) {
            Mail::to($account->owner_email)
                ->queue(new LowBalanceWarning(
                    ownerName: $account->owner_name,
                    balanceInCents: $account->balance_in_cents,
                ));
        }
    }

    public function onAccountClosed(AccountClosed $event, string $aggregateUuid): void
    {
        // CRM sisteminə xəbər ver, hesabı deaktiv et
        // ExternalCrmService::notifyAccountClosed($aggregateUuid);
    }
}
```

### Service Provider-da Qeydiyyat

*Service Provider-da Qeydiyyat üçün kod nümunəsi:*
```php
// Bu kod Projector və Reactor-ların EventSourcingServiceProvider-da qeydiyyatını göstərir
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\EventSourcing\Facades\Projectionist;
use App\Domain\Account\Projectors\AccountSummaryProjector;
use App\Domain\Account\Reactors\AccountNotificationReactor;
use App\Domain\Order\Projectors\OrderProjector;

class EventSourcingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Projectionist::addProjectors([
            AccountSummaryProjector::class,
            OrderProjector::class,
        ]);

        Projectionist::addReactors([
            AccountNotificationReactor::class,
        ]);
    }
}
```

---

## Real-World Nümunə 1: Bank Account

### Tam Events

*Tam Events üçün kod nümunəsi:*
```php
// Bu kod bank hesabı üçün bütün domain event-lərin strukturunu göstərir
<?php

namespace App\Domain\Account\Events;

use Spatie\EventSourcing\StorableEvents\ShouldBeStored;

final class AccountOpened implements ShouldBeStored
{
    public function __construct(
        public readonly string $ownerName,
        public readonly string $ownerEmail,
        public readonly string $currency,
        public readonly int    $initialDepositInCents = 0,
    ) {}
}

final class MoneyDeposited implements ShouldBeStored
{
    public function __construct(
        public readonly int    $amountInCents,
        public readonly string $description,
        public readonly string $referenceNumber,
    ) {}
}

final class MoneyWithdrawn implements ShouldBeStored
{
    public function __construct(
        public readonly int    $amountInCents,
        public readonly string $description,
        public readonly string $referenceNumber,
    ) {}
}

final class TransferInitiated implements ShouldBeStored
{
    public function __construct(
        public readonly string $toAccountUuid,
        public readonly int    $amountInCents,
        public readonly string $description,
        public readonly string $transferUuid,
    ) {}
}

final class TransferReceived implements ShouldBeStored
{
    public function __construct(
        public readonly string $fromAccountUuid,
        public readonly int    $amountInCents,
        public readonly string $description,
        public readonly string $transferUuid,
    ) {}
}

final class AccountFrozen implements ShouldBeStored
{
    public function __construct(
        public readonly string $reason,
        public readonly string $frozenByAdminId,
    ) {}
}

final class AccountUnfrozen implements ShouldBeStored
{
    public function __construct(
        public readonly string $unfrozenByAdminId,
    ) {}
}

final class AccountClosed implements ShouldBeStored
{
    public function __construct(
        public readonly string $reason = '',
    ) {}
}
```

### BankAccountAggregate — Tam İmplementasiya

*BankAccountAggregate — Tam İmplementasiya üçün kod nümunəsi:*
```php
// Bu kod BankAccount aggregate-nin tam implementasiyasını göstərir
<?php

namespace App\Domain\Account;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use App\Domain\Account\Events\{
    AccountOpened, MoneyDeposited, MoneyWithdrawn,
    TransferInitiated, TransferReceived,
    AccountFrozen, AccountUnfrozen, AccountClosed
};

class BankAccountAggregate extends AggregateRoot
{
    private int    $balanceInCents = 0;
    private bool   $isClosed = false;
    private bool   $isFrozen = false;
    private string $currency = 'AZN';
    private string $ownerName = '';

    // ─── Commands ──────────────────────────────────────────────

    public function openAccount(
        string $ownerName,
        string $ownerEmail,
        string $currency = 'AZN',
        int    $initialDepositInCents = 0
    ): static {
        $this->recordThat(new AccountOpened(
            ownerName: $ownerName,
            ownerEmail: $ownerEmail,
            currency: $currency,
            initialDepositInCents: $initialDepositInCents,
        ));
        return $this;
    }

    public function deposit(int $amountInCents, string $description, string $ref): static
    {
        $this->ensureNotClosed();
        $this->ensureNotFrozen();

        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('Deposit amount must be positive.');
        }

        $this->recordThat(new MoneyDeposited(
            amountInCents: $amountInCents,
            description: $description,
            referenceNumber: $ref,
        ));
        return $this;
    }

    public function withdraw(int $amountInCents, string $description, string $ref): static
    {
        $this->ensureNotClosed();
        $this->ensureNotFrozen();

        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('Withdrawal amount must be positive.');
        }

        if ($this->balanceInCents < $amountInCents) {
            throw new \DomainException(
                "Insufficient funds. Balance: {$this->balanceInCents}, Requested: {$amountInCents}"
            );
        }

        $this->recordThat(new MoneyWithdrawn(
            amountInCents: $amountInCents,
            description: $description,
            referenceNumber: $ref,
        ));
        return $this;
    }

    public function initiateTransfer(
        string $toAccountUuid,
        int    $amountInCents,
        string $description,
        string $transferUuid
    ): static {
        $this->ensureNotClosed();
        $this->ensureNotFrozen();

        if ($this->balanceInCents < $amountInCents) {
            throw new \DomainException('Insufficient funds for transfer.');
        }

        $this->recordThat(new TransferInitiated(
            toAccountUuid: $toAccountUuid,
            amountInCents: $amountInCents,
            description: $description,
            transferUuid: $transferUuid,
        ));
        return $this;
    }

    public function receiveTransfer(
        string $fromAccountUuid,
        int    $amountInCents,
        string $description,
        string $transferUuid
    ): static {
        $this->ensureNotClosed();
        // Dondurulmuş hesab pul qəbul edə bilər (bəzi banklarda bu qayda var)

        $this->recordThat(new TransferReceived(
            fromAccountUuid: $fromAccountUuid,
            amountInCents: $amountInCents,
            description: $description,
            transferUuid: $transferUuid,
        ));
        return $this;
    }

    public function freeze(string $reason, string $adminId): static
    {
        $this->ensureNotClosed();

        if ($this->isFrozen) {
            throw new \LogicException('Account is already frozen.');
        }

        $this->recordThat(new AccountFrozen(
            reason: $reason,
            frozenByAdminId: $adminId,
        ));
        return $this;
    }

    public function unfreeze(string $adminId): static
    {
        $this->ensureNotClosed();

        if (!$this->isFrozen) {
            throw new \LogicException('Account is not frozen.');
        }

        $this->recordThat(new AccountUnfrozen(
            unfrozenByAdminId: $adminId,
        ));
        return $this;
    }

    public function close(string $reason = ''): static
    {
        $this->ensureNotClosed();

        if ($this->balanceInCents > 0) {
            throw new \DomainException(
                "Cannot close account with positive balance: {$this->balanceInCents} cents."
            );
        }

        $this->recordThat(new AccountClosed(reason: $reason));
        return $this;
    }

    // ─── Apply Methods ─────────────────────────────────────────

    protected function applyAccountOpened(AccountOpened $event): void
    {
        $this->ownerName      = $event->ownerName;
        $this->currency       = $event->currency;
        $this->balanceInCents = $event->initialDepositInCents;
    }

    protected function applyMoneyDeposited(MoneyDeposited $event): void
    {
        $this->balanceInCents += $event->amountInCents;
    }

    protected function applyMoneyWithdrawn(MoneyWithdrawn $event): void
    {
        $this->balanceInCents -= $event->amountInCents;
    }

    protected function applyTransferInitiated(TransferInitiated $event): void
    {
        $this->balanceInCents -= $event->amountInCents;
    }

    protected function applyTransferReceived(TransferReceived $event): void
    {
        $this->balanceInCents += $event->amountInCents;
    }

    protected function applyAccountFrozen(AccountFrozen $event): void
    {
        $this->isFrozen = true;
    }

    protected function applyAccountUnfrozen(AccountUnfrozen $event): void
    {
        $this->isFrozen = false;
    }

    protected function applyAccountClosed(AccountClosed $event): void
    {
        $this->isClosed = true;
    }

    // ─── Guard Helpers ─────────────────────────────────────────

    private function ensureNotClosed(): void
    {
        if ($this->isClosed) {
            throw new \DomainException('Account is closed.');
        }
    }

    private function ensureNotFrozen(): void
    {
        if ($this->isFrozen) {
            throw new \DomainException('Account is frozen.');
        }
    }

    // ─── Getters (test üçün) ───────────────────────────────────
    public function getBalance(): int  { return $this->balanceInCents; }
    public function isClosed(): bool   { return $this->isClosed; }
    public function isFrozen(): bool   { return $this->isFrozen; }
}
```

### BankAccount Controller

*BankAccount Controller üçün kod nümunəsi:*
```php
// Bu kod BankAccount aggregate-ni HTTP API vasitəsilə idarə edən controller-i göstərir
<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Domain\Account\BankAccountAggregate;
use Illuminate\Support\Str;

class BankAccountController
{
    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'owner_name'       => 'required|string|max:255',
            'owner_email'      => 'required|email',
            'currency'         => 'required|in:AZN,USD,EUR',
            'initial_deposit'  => 'nullable|integer|min:0',
        ]);

        $uuid = Str::uuid()->toString();

        BankAccountAggregate::retrieve($uuid)
            ->openAccount(
                ownerName: $request->string('owner_name'),
                ownerEmail: $request->string('owner_email'),
                currency: $request->string('currency'),
                initialDepositInCents: $request->integer('initial_deposit', 0),
            )
            ->persist();

        return response()->json([
            'account_uuid' => $uuid,
            'message'      => 'Account opened successfully.',
        ], 201);
    }

    public function deposit(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'amount_in_cents' => 'required|integer|min:1',
            'description'     => 'required|string|max:500',
        ]);

        $ref = 'DEP-' . strtoupper(Str::random(10));

        BankAccountAggregate::retrieve($uuid)
            ->deposit(
                amountInCents: $request->integer('amount_in_cents'),
                description: $request->string('description'),
                ref: $ref,
            )
            ->persist();

        return response()->json([
            'reference' => $ref,
            'message'   => 'Deposit processed successfully.',
        ]);
    }

    public function transfer(Request $request, string $fromUuid): JsonResponse
    {
        $request->validate([
            'to_account_uuid' => 'required|uuid',
            'amount_in_cents' => 'required|integer|min:1',
            'description'     => 'required|string|max:500',
        ]);

        $transferUuid = Str::uuid()->toString();

        BankAccountAggregate::retrieve($fromUuid)
            ->initiateTransfer(
                toAccountUuid: $request->string('to_account_uuid'),
                amountInCents: $request->integer('amount_in_cents'),
                description: $request->string('description'),
                transferUuid: $transferUuid,
            )
            ->persist();

        BankAccountAggregate::retrieve($request->string('to_account_uuid'))
            ->receiveTransfer(
                fromAccountUuid: $fromUuid,
                amountInCents: $request->integer('amount_in_cents'),
                description: $request->string('description'),
                transferUuid: $transferUuid,
            )
            ->persist();

        return response()->json([
            'transfer_uuid' => $transferUuid,
            'message'       => 'Transfer completed successfully.',
        ]);
    }
}
```

---

## Real-World Nümunə 2: Order Lifecycle

### Order Events

*Order Events üçün kod nümunəsi:*
```php
// Bu kod e-ticarət sifariş dövrəsi üçün domain event-ləri göstərir
<?php

namespace App\Domain\Order\Events;

use Spatie\EventSourcing\StorableEvents\ShouldBeStored;

final class OrderCreated implements ShouldBeStored
{
    public function __construct(
        public readonly string $customerUuid,
        public readonly array  $items,         // [['product_id' => ..., 'qty' => ..., 'price_cents' => ...]]
        public readonly int    $totalInCents,
        public readonly array  $shippingAddress,
    ) {}
}

final class OrderConfirmed implements ShouldBeStored
{
    public function __construct(
        public readonly string $confirmedByUserId,
        public readonly string $paymentReference,
    ) {}
}

final class OrderPicked implements ShouldBeStored
{
    public function __construct(
        public readonly string $warehouseId,
        public readonly string $pickedByStaffId,
    ) {}
}

final class OrderShipped implements ShouldBeStored
{
    public function __construct(
        public readonly string             $trackingNumber,
        public readonly string             $courierCode,
        public readonly \DateTimeImmutable $estimatedDelivery,
    ) {}
}

final class OrderDelivered implements ShouldBeStored
{
    public function __construct(
        public readonly \DateTimeImmutable $deliveredAt,
        public readonly string             $receivedBy = '',
    ) {}
}

final class OrderRefundRequested implements ShouldBeStored
{
    public function __construct(
        public readonly string $reason,
        public readonly int    $refundAmountInCents,
        public readonly string $requestedByUserId,
    ) {}
}

final class OrderRefunded implements ShouldBeStored
{
    public function __construct(
        public readonly int    $refundedAmountInCents,
        public readonly string $refundTransactionId,
        public readonly string $processedByAdminId,
    ) {}
}

final class OrderCancelled implements ShouldBeStored
{
    public function __construct(
        public readonly string $reason,
        public readonly string $cancelledByUserId,
        public readonly bool   $refundRequired = false,
    ) {}
}
```

### OrderAggregate — State Machine

*OrderAggregate — State Machine üçün kod nümunəsi:*
```php
// Bu kod sifariş aggregate-nin state machine kimi işlədiyini göstərir
<?php

namespace App\Domain\Order;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use App\Domain\Order\Events\{
    OrderCreated, OrderConfirmed, OrderPicked, OrderShipped,
    OrderDelivered, OrderRefundRequested, OrderRefunded, OrderCancelled
};

class OrderAggregate extends AggregateRoot
{
    const STATUS_PENDING          = 'pending';
    const STATUS_CONFIRMED        = 'confirmed';
    const STATUS_PICKED           = 'picked';
    const STATUS_SHIPPED          = 'shipped';
    const STATUS_DELIVERED        = 'delivered';
    const STATUS_REFUND_REQUESTED = 'refund_requested';
    const STATUS_REFUNDED         = 'refunded';
    const STATUS_CANCELLED        = 'cancelled';

    private string $status = '';
    private int    $totalInCents = 0;
    private string $customerUuid = '';

    // Status keçid qrafı (State Machine)
    private const VALID_TRANSITIONS = [
        ''                            => [self::STATUS_PENDING],
        self::STATUS_PENDING          => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED        => [self::STATUS_PICKED, self::STATUS_CANCELLED],
        self::STATUS_PICKED           => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED          => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED        => [self::STATUS_REFUND_REQUESTED],
        self::STATUS_REFUND_REQUESTED => [self::STATUS_REFUNDED],
        // CANCELLED, REFUNDED — terminal state-lər: heç bir keçid yoxdur
    ];

    // ─── Commands ──────────────────────────────────────────────

    public function createOrder(
        string $customerUuid,
        array  $items,
        int    $totalInCents,
        array  $shippingAddress
    ): static {
        $this->ensureValidTransition(self::STATUS_PENDING);

        $this->recordThat(new OrderCreated(
            customerUuid: $customerUuid,
            items: $items,
            totalInCents: $totalInCents,
            shippingAddress: $shippingAddress,
        ));
        return $this;
    }

    public function confirm(string $userId, string $paymentRef): static
    {
        $this->ensureValidTransition(self::STATUS_CONFIRMED);

        $this->recordThat(new OrderConfirmed(
            confirmedByUserId: $userId,
            paymentReference: $paymentRef,
        ));
        return $this;
    }

    public function markAsPicked(string $warehouseId, string $staffId): static
    {
        $this->ensureValidTransition(self::STATUS_PICKED);

        $this->recordThat(new OrderPicked(
            warehouseId: $warehouseId,
            pickedByStaffId: $staffId,
        ));
        return $this;
    }

    public function ship(
        string             $trackingNumber,
        string             $courierCode,
        \DateTimeImmutable $estimatedDelivery
    ): static {
        $this->ensureValidTransition(self::STATUS_SHIPPED);

        $this->recordThat(new OrderShipped(
            trackingNumber: $trackingNumber,
            courierCode: $courierCode,
            estimatedDelivery: $estimatedDelivery,
        ));
        return $this;
    }

    public function markAsDelivered(
        \DateTimeImmutable $deliveredAt,
        string             $receivedBy = ''
    ): static {
        $this->ensureValidTransition(self::STATUS_DELIVERED);

        $this->recordThat(new OrderDelivered(
            deliveredAt: $deliveredAt,
            receivedBy: $receivedBy,
        ));
        return $this;
    }

    public function requestRefund(
        string $reason,
        int    $refundAmountInCents,
        string $requestedByUserId
    ): static {
        $this->ensureValidTransition(self::STATUS_REFUND_REQUESTED);

        if ($refundAmountInCents > $this->totalInCents) {
            throw new \DomainException('Refund amount exceeds order total.');
        }

        $this->recordThat(new OrderRefundRequested(
            reason: $reason,
            refundAmountInCents: $refundAmountInCents,
            requestedByUserId: $requestedByUserId,
        ));
        return $this;
    }

    public function processRefund(
        int    $refundedAmountInCents,
        string $refundTransactionId,
        string $processedByAdminId
    ): static {
        $this->ensureValidTransition(self::STATUS_REFUNDED);

        $this->recordThat(new OrderRefunded(
            refundedAmountInCents: $refundedAmountInCents,
            refundTransactionId: $refundTransactionId,
            processedByAdminId: $processedByAdminId,
        ));
        return $this;
    }

    public function cancel(string $reason, string $userId, bool $refundRequired = false): static
    {
        $this->ensureValidTransition(self::STATUS_CANCELLED);

        $this->recordThat(new OrderCancelled(
            reason: $reason,
            cancelledByUserId: $userId,
            refundRequired: $refundRequired,
        ));
        return $this;
    }

    // ─── Apply Methods ─────────────────────────────────────────

    protected function applyOrderCreated(OrderCreated $event): void
    {
        $this->status        = self::STATUS_PENDING;
        $this->customerUuid  = $event->customerUuid;
        $this->totalInCents  = $event->totalInCents;
    }

    protected function applyOrderConfirmed(OrderConfirmed $event): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    protected function applyOrderPicked(OrderPicked $event): void
    {
        $this->status = self::STATUS_PICKED;
    }

    protected function applyOrderShipped(OrderShipped $event): void
    {
        $this->status = self::STATUS_SHIPPED;
    }

    protected function applyOrderDelivered(OrderDelivered $event): void
    {
        $this->status = self::STATUS_DELIVERED;
    }

    protected function applyOrderRefundRequested(OrderRefundRequested $event): void
    {
        $this->status = self::STATUS_REFUND_REQUESTED;
    }

    protected function applyOrderRefunded(OrderRefunded $event): void
    {
        $this->status = self::STATUS_REFUNDED;
    }

    protected function applyOrderCancelled(OrderCancelled $event): void
    {
        $this->status = self::STATUS_CANCELLED;
    }

    // ─── Guard Helper ──────────────────────────────────────────

    private function ensureValidTransition(string $toStatus): void
    {
        $allowed = self::VALID_TRANSITIONS[$this->status] ?? [];

        if (!in_array($toStatus, $allowed, true)) {
            throw new \DomainException(
                "Invalid status transition from '{$this->status}' to '{$toStatus}'."
            );
        }
    }

    public function getStatus(): string { return $this->status; }
    public function getTotal(): int     { return $this->totalInCents; }
}
```

### Order Projector

*Order Projector üçün kod nümunəsi:*
```php
// Bu kod sifariş event-lərindən oxuma modelini quran Order Projector-u göstərir
<?php

namespace App\Domain\Order\Projectors;

use Spatie\EventSourcing\EventHandlers\Projectors\Projector;
use App\Domain\Order\Events\{
    OrderCreated, OrderConfirmed, OrderShipped,
    OrderDelivered, OrderRefunded, OrderCancelled
};
use App\Models\OrderReadModel;

class OrderProjector extends Projector
{
    public function onOrderCreated(OrderCreated $event, string $aggregateUuid): void
    {
        OrderReadModel::create([
            'uuid'             => $aggregateUuid,
            'customer_uuid'    => $event->customerUuid,
            'status'           => 'pending',
            'total_cents'      => $event->totalInCents,
            'items'            => json_encode($event->items),
            'shipping_address' => json_encode($event->shippingAddress),
            'created_at'       => now(),
        ]);
    }

    public function onOrderConfirmed(OrderConfirmed $event, string $aggregateUuid): void
    {
        OrderReadModel::where('uuid', $aggregateUuid)->update([
            'status'            => 'confirmed',
            'payment_reference' => $event->paymentReference,
            'confirmed_at'      => now(),
        ]);
    }

    public function onOrderShipped(OrderShipped $event, string $aggregateUuid): void
    {
        OrderReadModel::where('uuid', $aggregateUuid)->update([
            'status'             => 'shipped',
            'tracking_number'    => $event->trackingNumber,
            'courier_code'       => $event->courierCode,
            'estimated_delivery' => $event->estimatedDelivery->format('Y-m-d'),
            'shipped_at'         => now(),
        ]);
    }

    public function onOrderDelivered(OrderDelivered $event, string $aggregateUuid): void
    {
        OrderReadModel::where('uuid', $aggregateUuid)->update([
            'status'       => 'delivered',
            'delivered_at' => $event->deliveredAt->format('Y-m-d H:i:s'),
            'received_by'  => $event->receivedBy,
        ]);
    }

    public function onOrderRefunded(OrderRefunded $event, string $aggregateUuid): void
    {
        OrderReadModel::where('uuid', $aggregateUuid)->update([
            'status'                => 'refunded',
            'refunded_amount_cents' => $event->refundedAmountInCents,
            'refund_transaction_id' => $event->refundTransactionId,
            'refunded_at'           => now(),
        ]);
    }

    public function onOrderCancelled(OrderCancelled $event, string $aggregateUuid): void
    {
        OrderReadModel::where('uuid', $aggregateUuid)->update([
            'status'        => 'cancelled',
            'cancel_reason' => $event->reason,
            'cancelled_at'  => now(),
        ]);
    }
}
```

### Unit Test Nümunəsi

*Unit Test Nümunəsi üçün kod nümunəsi:*
```php
// Bu kod Event Sourcing aggregate-nin unit test ilə yoxlanmasını göstərir
<?php

namespace Tests\Unit\Domain\Account;

use Tests\TestCase;
use App\Domain\Account\BankAccountAggregate;

class BankAccountAggregateTest extends TestCase
{
    /** @test */
    public function it_can_open_an_account(): void
    {
        $uuid = 'test-uuid-123';

        BankAccountAggregate::fake($uuid)
            ->when(function (BankAccountAggregate $agg) {
                $agg->openAccount('John Doe', 'john@example.com', 'AZN', 50000);
            })
            ->assertRecorded([
                new \App\Domain\Account\Events\AccountOpened(
                    ownerName: 'John Doe',
                    ownerEmail: 'john@example.com',
                    currency: 'AZN',
                    initialDepositInCents: 50000,
                ),
            ]);
    }

    /** @test */
    public function it_throws_exception_when_withdrawing_more_than_balance(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Insufficient funds/');

        BankAccountAggregate::fake('test-uuid')
            ->given([
                new \App\Domain\Account\Events\AccountOpened(
                    ownerName: 'John',
                    ownerEmail: 'j@x.com',
                    currency: 'AZN',
                    initialDepositInCents: 10000, // 100.00 AZN
                ),
            ])
            ->when(function (BankAccountAggregate $agg) {
                $agg->withdraw(50000, 'Too much', 'REF-001'); // 500.00 AZN
            });
    }

    /** @test */
    public function it_correctly_calculates_balance_after_multiple_operations(): void
    {
        $agg = BankAccountAggregate::fake('uuid-1')
            ->given([
                new \App\Domain\Account\Events\AccountOpened('Ali', 'ali@x.com', 'AZN', 0),
                new \App\Domain\Account\Events\MoneyDeposited(100_00, 'Salary', 'DEP-1'),
                new \App\Domain\Account\Events\MoneyDeposited(50_00, 'Bonus', 'DEP-2'),
                new \App\Domain\Account\Events\MoneyWithdrawn(30_00, 'Rent', 'WIT-1'),
            ])
            ->aggregateRoot();

        $this->assertEquals(120_00, $agg->getBalance()); // 100 + 50 - 30 = 120
    }
}
```

---

## Üstünlüklər və Mənfi Cəhətlər

### Üstünlüklər

```
1. Tam Audit Trail
   Hər dəyişiklik — kim tərəfindən, nə vaxt, nə səbəbdən.
   Maliyyə, sağlamlıq, hüquqi tətbiqlər üçün compliance.
   SOX, HIPAA, PCI DSS tələbləri kolayca ödənilir.

2. Time Travel (Temporal Queries)
   "3 ay əvvəl bu sifariş hansı vəziyyətdə idi?" — dəqiq cavab.
   Mühasibat hesabatları: "Ay sonundakı balans" retroaktiv hesablanır.

3. Bug Fix + Retroaktiv Düzəliş
   Hesablama xətası tapıldı → fix et → replay et.
   Read model özü düzəlir. Manual SQL update lazım deyil.

4. Yeni Feature Retroaktiv Tətbiqi
   "Loyalty points" feature: köhnə alışlara da tətbiq etmək istəyirsiniz?
   Yeni Projector yazın + replay edin → hazırdır, bir gündə.

5. Decoupling (Gevşəmə)
   Yeni use-case üçün mövcud kodu dəyişdirmirsiniz.
   Yeni Projector əlavə edirsiniz, replay edirsiniz.
   Open/Closed Principle-ə tam uyğun.

6. Production Bug Reproduce
   İstifadəçinin event-lərini export edib local-da replay edin.
   Dəqiq eyni vəziyyət yaranır, bug tapılır.

7. CQRS ilə Scalability
   Read caching, denormalized view-lar, Elasticsearch integration.
   Write və read ayrı serverlərdə.
```

### Mənfi Cəhətlər

```
1. Mürəkkəblik
   Ənənəvi CRUD-a nisbətən çox daha mürəkkəb arxitektura.
   Team-in öyrənmə əyrisi uzundur (1-3 ay).
   Overengineering riski — sadə CRUD tətbiqlər üçün lazımsızdır.

2. Eventual Consistency
   Write → Read arasında gecikMə var.
   UI-da "processing" state göstərmək lazım ola bilər.
   Strong consistency tələb edən use-case-lər üçün problem.

3. GDPR — "Right to Erasure"
   Event-lər immutable-dır — silmək çox çətindir.
   Həll: Crypto shredding (PII-ni şifrəli saxla, açarı sil).
   Ya da: PII-ni event-lərdə saxlama, yalnız reference ID saxla.

4. Query Mürəkkəbliyi
   Aggregate-in state-ini görmək üçün event-ləri replay etmək lazımdır.
   Snapshot olmadan böyük aggregate-lər yavaş yüklənir.

5. Event Schema Dəyişiklikləri
   Köhnə format event-lər event store-da var.
   Upcaster yazmaq lazımdır — əlavə iş, test lazımdır.

6. Database Böyüklüyü
   Event store zamanla çox böyüyür (append-only).
   Archiving, partitioning, cold storage strategiyası lazımdır.

7. Tooling Çatışmazlığı
   Ənənəvi ORM toolları birbaşa işləmir.
   Debugging, monitoring xüsusi toollar tələb edir.
   Köhnə verilənlər bazası admin toolları (phpMyAdmin, etc.) az faydalıdır.
```

---

## Nə Vaxt İstifadə Etməli / Etməməli

### İstifadə Edin

```
Maliyyə sistemləri (bank, ödəniş, mühasibat)
→ Hər tranzaksiya izlənməlidir, audit vacibdir.
→ "5 may tarixindəki balans" kimi temporal query-lər lazımdır.

E-commerce sifariş idarəetməsi
→ Sifariş lifecycle-ı mürəkkəbdir (Created→Confirmed→Shipped→Delivered→Refunded).
→ Hər status dəyişikliyi kimin tərəfindən olduğu lazımdır.

Sağlamlıq/tibbi sistemlər
→ Həkim qeydlərinin dəyişiklik tarixçəsi tələb olunur.
→ "Dərman dozu nə vaxt dəyişdirildi?" vacibdir.

Hüquqi sənəd sistemləri
→ Kimin nə vaxt nə imzaladığı/dəyişdirdiyi.

Oyun/gamification
→ Point hesablamaları, leaderboard, replay, anti-cheat.

Inventory idarəetməsi
→ Stok dəyişiklik tarixçəsi, hansı warehouse-dan gəldi.

Domain-i mürəkkəb olan sistemlər
→ DDD + CQRS + Event Sourcing birlikdə ən çox dəyər verir.
```

### İstifadə Etməyin

```
Sadə CRUD tətbiqlər (blog, portfolio, news site)
→ Overkill. Mürəkkəblik əlavə edir, heç bir üstünlük vermir.

Audit trail tələb olunmayan sistemlər
→ Əsas üstünlük istifadə olunmur.

Kiçik team + sürətli delivery tələbi
→ Öyrənmə əyrisi çox vaxt aparır.
→ "Move fast" mühitlərində Event Sourcing yavaşladır.

Çox yüksək yazma yükü (IoT, telemetri, log aggregation)
→ Event store çox böyüyər.
→ Time-series DB (InfluxDB, TimescaleDB) daha uyğundur.

Strong consistency mütləq lazım olan real-time sistemlər
→ Eventual consistency problem yaradır.
→ Distributed transaction tələb olunan yerlər.
```

---

## İntervyu Sualları

**1. Event Sourcing nədir? Traditional CRUD-dan əsas fərqi nədir?**

Event Sourcing tətbiqin state-ini deyil, state-ə gətirib çıxaran bütün hadisələrin sıralı log-unu saxlayır. CRUD-da yalnız son vəziyyət var, tarixçə yoxdur. Event Sourcing-də append-only event log var, cari vəziyyət event-lərin replay-i ilə əldə edilir. Bu tam audit trail, time travel, event replay, retroaktiv feature əlavəsi kimi üstünlüklər verir.

**2. Aggregate Root nədir? Event Sourcing-də rolu nədir?**

Aggregate Root — biznesdə vahid ardıcıllıq (consistency) tələb edən obyektlər qrupunun xarici giriş nöqtəsidir. Kənardan yalnız root vasitəsilə daxilə girilir. Event Sourcing-də AggregateRoot business logic-i və guard condition-ları saxlayır, event-ləri qeyd edir (`recordThat()`), apply metodları ilə state-i yeniləyir. `persist()` çağırıldıqda event-lər event store-a yazılır.

**3. Projector ilə Reactor arasındakı fərq nədir?**

Projector event-ləri dinləyir və read model (denormalized cədvəl) yaradır/yeniləyir. State saxlayır. Idempotent olmalıdır — rebuild mümkün olsun deyə. Reactor isə event-i eşidir və side effect icra edir: email, notification, webhook. State saxlamır. İdempotent olmaq mütləq deyil. Hər ikisi `on{EventClassName}()` metodları ilə event-ləri handle edir.

**4. Snapshot nədir? Nə zaman lazımdır?**

Aggregate-in müəyyən bir andakı vəziyyətinin tam dump-ıdır. `spatie/laravel-event-sourcing`-də `snapshotEvery()` metodu ilə hər N event-dən bir avtomatik çəkilir. Növbəti `retrieve()`-də bütün event-ləri deyil, ən son snapshot-dan bəri olan event-ləri replay etmək kifayət edir. Aggregate-in event sayı çox böyüyəndə (yüzlərlə/minlərlə) performance üçün vacibdir.

**5. Event versioning / upcasting problemi nə vaxt yaranır?**

Event schema zamanla dəyişəndə problem yaranır. Event store-da köhnə format event-lər var, yeni kod yeni format gözləyir. Upcaster — köhnə event-i oxuyarkən yeni formata çevirir. Event store-dakı data dəyişdirilmir, yalnız deserialization zamanı transform edilir. Məsələn: `float amount` → `int amountInCents` dəyişikliyi.

**6. GDPR "right to erasure" Event Sourcing-də necə həll edilir?**

Event-lər immutable olduğundan silmək çətindir. Ən populyar həll — **crypto shredding**: PII məlumatları event-lərdə şifrəli saxlanır, şifrələmə açarı ayrı key store-da (AWS KMS, HashiCorp Vault) saxlanır. GDPR silmə tələbi gəldikdə yalnız şifrələmə açarı silinir. Event-lər texniki olaraq qalır amma deşifrə edilə bilmir — "effectively erased" sayılır. Alternativ: event-lərdə PII saxlama, yalnız `user_id` kimi reference ID saxla.

**7. Eventual consistency nədir? Laravel-də necə idarə edilir?**

Write tamamlandıqdan sonra read model-in yenilənməsi bir neçə millisaniyə gec ola bilər. Sinxron projector-larla bu fərq minimaldır (~2-5ms). Asinxron projector-larla (queue ilə) daha böyük gecikmə olur. UI-da HTTP 202 Accepted + polling endpoint pattern istifadə edilir. Frontend optimistic update edə bilər.

**8. spatie/laravel-event-sourcing-də `retrieve()` vs `persist()` nə edir?**

`BankAccountAggregate::retrieve($uuid)` — event store-dan həmin aggregate-ə aid bütün event-ləri oxuyur, `apply*()` metodlarını çağıraraq cari state-i yenidən qurur (snapshot varsa oradan başlayır). `persist()` — `recordThat()` ilə qeyd edilmiş yeni event-ləri event store-a yazır, sonra Projector-lar və Reactor-lara dispatch edir.

**9. Optimistic locking Event Sourcing-də necə işləyir?**

`stored_events` cədvəlində `(aggregate_uuid, aggregate_version)` üzərindən UNIQUE constraint var. Hər event yazılarkən `aggregate_version` bir artırılır. İki thread eyni anda eyni aggregate-ə yazırsa biri `DUPLICATE KEY` xətası alır, yenidən yükləyib cəhd edir. Bu şəkildə race condition-lar database-level-da qarşısı alınır, ayrıca lock mexanizmi lazım deyil.

**10. Event Sourcing + CQRS arxitekturasını izah edin.**

CQRS write (Command) və read (Query) side-larını ayırır. Write: `Command → CommandHandler → AggregateRoot → Domain Events → Event Store`. Read: `Query → QueryHandler → Read Model (denormalized SQL/Elasticsearch)`. Event Store-dan Projector-lar read model-i yeniləyir. Write DB yalnız event-ləri saxlayır (append-only). Read DB istənilən formada ola bilər. Bu ayrılıq hər side-ı müstəqil scale etməyə, fərqli storage texnologiyalarından istifadəyə imkan verir.

**11. Event Sourcing-in ən böyük dezavantajı nədir?**

Mürəkkəblik: ənənəvi CRUD-a nisbətən çox daha mürəkkəb arxitektura, uzun öyrənmə əyrisi. GDPR compliance çətinliyi: immutable event-lər PII-ni silməyi çətinləşdirir (crypto shredding tələb edir). Eventual consistency: bəzi use-case-lər üçün bu problem yaradır. Event schema dəyişikliklərini idarə etmək (upcasting) əlavə iş tələb edir.

**12. `fake()` metodu nə üçün istifadə edilir?**

`BankAccountAggregate::fake($uuid)` — test mühitindədir, event store-a yazmır. `given([...])` — başlanğıc event-ləri verir (pre-conditions). `when(fn($agg) => ...)` — aggregate-ə əmr verir. `assertRecorded([...])` — hansı event-lərin qeyd edildiyini yoxlayır. Bu şəkildə database olmadan, yalnız domain logic-i test etmək mümkündür. Spatie paketinin built-in test helpers-ı BDD (Behavior-Driven Development) üslubunda test yazmağı asanlaşdırır.

---

## Anti-patternlər

**1. Event-ləri mutable etmək və ya mövcud event-i yeniləmək**
Event store-dakı event-in məlumatını update etmək — event sourcing-in əsas invariantı pozulur, audit trail itirilir, proyeksiyaları yenidən qurmaq mümkünsüzləşir. Event-lər immutable olmalıdır; dəyişiklik üçün yeni corrective event yaz.

**2. Aggregate-in hər property dəyişikliyini ayrı event kimi modelləşdirmək**
`UserFirstNameChanged`, `UserLastNameChanged` kimi çox granular event-lər yaratmaq — event stream idarəolunmaz böyüyür, snapshot tezliyi artmaq məcburiyyətindədir. Domain-in mənalı hərəkətlərini (`UserProfileUpdated`) event kimi modelləşdir.

**3. Proyeksiyaları event store ilə eyni transaction-da yazmaq**
Read model-i event store-a yazdıqda eyni anda güncəlləmək — CQRS-in write/read ayrılığı prinsipini pozur, projector-lar async işləyə bilmir. Proyeksiyaları ayrı prosesdə, event-ə subscribe olaraq yenilə.

**4. Domain event-lərini infrastructure event-ləri ilə qarışdırmaq**
`EmailSent`, `SMSDelivered` kimi texniki event-ləri domain event store-a yazmaq — domain modeli infrastructure detallarını bilməməlidir, event stream kirləşir. Domain event-ləri yalnız biznes mənası daşıyan hadisələri (`OrderPlaced`, `PaymentProcessed`) modelləşdirsin.

**5. Snapshot mexanizmi olmadan böyüyən event stream-i yükləmək**
Yüzlərlə event olan aggregate-i hər dəfə sıfırdan rebuild etmək — aggregate yüklənmə vaxtı artan event sayı ilə mütənasib böyüyür, performans dramatik aşağı düşür. Müəyyən event say həddinə çatdıqda snapshot yaz, yükləmə zamanı snapshot-dan başla.

**6. Event schema versioning-i planlamadan implementasiya başlamaq**
Event structure-nu gələcəkdə dəyişə biləcəyini nəzərə almamaq — köhnə event-lər yeni kod tərəfindən oxuna bilmir, event store migration kabusa çevrilir. Hər event-ə `version` sahəsi əlavə et, upcasting/migration strategy-ni əvvəlcədən müəyyən et.
