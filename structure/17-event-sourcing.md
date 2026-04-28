# Event Sourcing (Lead)

Event Sourcing application state-inə edilən bütün dəyişiklikləri event ardıcıllığı kimi saxlayır.
Cari state-i saxlamaq əvəzinə sistem state-i event-ləri replay edərək yenidən qurur.

**Əsas anlayışlar:**
- **Event Store** — Bütün domain event-lərinin append-only log-u
- **Aggregate** — State-ini event-lərdən yenidən qurur
- **Projection/Read Model** — Event-lərdən qurulmuş materialized view-lar
- **Projector** — Event-ləri emal edərək read model-ləri yeniləyir
- **Snapshot** — Bütün event-ləri replay etməmək üçün vaxtaşırı state kəsikləri
- **Event Stream** — Konkret aggregate üçün event ardıcıllığı

---

## Laravel

```
app/
├── Domain/
│   ├── Account/
│   │   ├── Account.php                     # Aggregate (rebuilds from events)
│   │   ├── AccountId.php
│   │   └── Events/
│   │       ├── AccountOpened.php
│   │       ├── MoneyDeposited.php
│   │       ├── MoneyWithdrawn.php
│   │       └── AccountClosed.php
│   ├── Order/
│   │   ├── Order.php
│   │   ├── OrderId.php
│   │   └── Events/
│   │       ├── OrderPlaced.php
│   │       ├── OrderItemAdded.php
│   │       ├── OrderConfirmed.php
│   │       ├── OrderShipped.php
│   │       └── OrderCancelled.php
│   └── Shared/
│       ├── AggregateRoot.php               # Base: apply/record events
│       ├── DomainEvent.php
│       └── AggregateId.php
│
├── EventStore/                             # Event persistence
│   ├── EventStoreInterface.php
│   ├── DatabaseEventStore.php
│   ├── EventStream.php
│   ├── StoredEvent.php
│   ├── EventSerializer.php
│   ├── Snapshot/
│   │   ├── SnapshotStoreInterface.php
│   │   ├── DatabaseSnapshotStore.php
│   │   └── Snapshot.php
│   └── Migration/
│       ├── create_event_store_table.php
│       └── create_snapshots_table.php
│
├── Projection/                             # Read models
│   ├── Account/
│   │   ├── AccountBalanceProjection.php    # Read model
│   │   ├── AccountBalanceProjector.php     # Event handler -> updates read model
│   │   └── AccountBalanceRepository.php
│   ├── Order/
│   │   ├── OrderSummaryProjection.php
│   │   ├── OrderSummaryProjector.php
│   │   └── OrderSummaryRepository.php
│   └── ProjectorRegistry.php
│
├── Application/
│   ├── Account/
│   │   ├── Commands/
│   │   │   ├── OpenAccount/
│   │   │   │   ├── OpenAccountCommand.php
│   │   │   │   └── OpenAccountHandler.php
│   │   │   ├── DepositMoney/
│   │   │   │   ├── DepositMoneyCommand.php
│   │   │   │   └── DepositMoneyHandler.php
│   │   │   └── WithdrawMoney/
│   │   │       ├── WithdrawMoneyCommand.php
│   │   │       └── WithdrawMoneyHandler.php
│   │   └── Queries/
│   │       └── GetAccountBalance/
│   │           ├── GetAccountBalanceQuery.php
│   │           └── GetAccountBalanceHandler.php
│   └── Order/
│       ├── Commands/
│       │   └── PlaceOrder/
│       │       ├── PlaceOrderCommand.php
│       │       └── PlaceOrderHandler.php
│       └── Queries/
│           └── GetOrderSummary/
│
├── Infrastructure/
│   ├── Repository/
│   │   ├── EventSourcedAccountRepository.php
│   │   └── EventSourcedOrderRepository.php
│   ├── Messaging/
│   │   └── EventPublisher.php
│   └── Providers/
│       └── EventSourcingServiceProvider.php
│
└── Http/
    └── Controllers/
        ├── AccountController.php
        └── OrderController.php
```

---

## Symfony

```
src/
├── Domain/
│   ├── Account/
│   │   ├── Aggregate/
│   │   │   └── Account.php
│   │   ├── ValueObject/
│   │   │   └── AccountId.php
│   │   └── Event/
│   │       ├── AccountOpenedEvent.php
│   │       ├── MoneyDepositedEvent.php
│   │       ├── MoneyWithdrawnEvent.php
│   │       └── AccountClosedEvent.php
│   ├── Order/
│   │   ├── Aggregate/
│   │   │   └── Order.php
│   │   └── Event/
│   │       ├── OrderPlacedEvent.php
│   │       ├── OrderConfirmedEvent.php
│   │       └── OrderCancelledEvent.php
│   └── Shared/
│       ├── AggregateRoot.php
│       ├── DomainEvent.php
│       └── AggregateId.php
│
├── EventStore/
│   ├── EventStoreInterface.php
│   ├── DoctrineEventStore.php
│   ├── EventStream.php
│   ├── StoredEvent.php
│   ├── Serializer/
│   │   └── EventSerializer.php
│   └── Snapshot/
│       ├── SnapshotStoreInterface.php
│       ├── DoctrineSnapshotStore.php
│       └── Snapshot.php
│
├── Projection/
│   ├── Account/
│   │   ├── AccountBalanceProjection.php
│   │   ├── AccountBalanceProjector.php
│   │   └── AccountBalanceReadRepository.php
│   ├── Order/
│   │   ├── OrderSummaryProjection.php
│   │   ├── OrderSummaryProjector.php
│   │   └── OrderSummaryReadRepository.php
│   └── ProjectorRegistry.php
│
├── Application/
│   ├── Account/
│   │   ├── Command/
│   │   │   ├── OpenAccount/
│   │   │   ├── DepositMoney/
│   │   │   └── WithdrawMoney/
│   │   └── Query/
│   │       └── GetAccountBalance/
│   └── Order/
│       ├── Command/
│       └── Query/
│
├── Infrastructure/
│   ├── Repository/
│   │   ├── EventSourcedAccountRepository.php
│   │   └── EventSourcedOrderRepository.php
│   └── Messaging/
│       └── MessengerEventPublisher.php
│
└── UI/
    └── Http/
        └── Controller/
            ├── AccountController.php
            └── OrderController.php

config/
└── services.yaml
migrations/
```

---

## Spring Boot (Java)

```
src/main/java/com/example/app/
├── domain/
│   ├── account/
│   │   ├── Account.java                    # Event-sourced aggregate
│   │   ├── AccountId.java
│   │   └── event/
│   │       ├── AccountOpenedEvent.java
│   │       ├── MoneyDepositedEvent.java
│   │       ├── MoneyWithdrawnEvent.java
│   │       └── AccountClosedEvent.java
│   ├── order/
│   │   ├── Order.java
│   │   ├── OrderId.java
│   │   └── event/
│   │       ├── OrderPlacedEvent.java
│   │       ├── OrderConfirmedEvent.java
│   │       └── OrderCancelledEvent.java
│   └── shared/
│       ├── AggregateRoot.java
│       ├── DomainEvent.java
│       └── AggregateId.java
│
├── eventstore/
│   ├── EventStore.java                     # Interface
│   ├── JpaEventStore.java
│   ├── EventStream.java
│   ├── StoredEvent.java
│   ├── entity/
│   │   └── EventStoreEntry.java
│   ├── serializer/
│   │   ├── EventSerializer.java
│   │   └── JacksonEventSerializer.java
│   └── snapshot/
│       ├── SnapshotStore.java
│       ├── JpaSnapshotStore.java
│       ├── Snapshot.java
│       └── entity/
│           └── SnapshotEntry.java
│
├── projection/
│   ├── account/
│   │   ├── AccountBalanceProjection.java
│   │   ├── AccountBalanceProjector.java
│   │   └── AccountBalanceReadRepository.java
│   ├── order/
│   │   ├── OrderSummaryProjection.java
│   │   ├── OrderSummaryProjector.java
│   │   └── OrderSummaryReadRepository.java
│   └── ProjectorRegistry.java
│
├── application/
│   ├── account/
│   │   ├── command/
│   │   │   ├── OpenAccountCommand.java
│   │   │   ├── OpenAccountCommandHandler.java
│   │   │   ├── DepositMoneyCommand.java
│   │   │   └── DepositMoneyCommandHandler.java
│   │   └── query/
│   │       ├── GetAccountBalanceQuery.java
│   │       └── GetAccountBalanceQueryHandler.java
│   └── order/
│       ├── command/
│       └── query/
│
├── infrastructure/
│   ├── repository/
│   │   ├── EventSourcedAccountRepository.java
│   │   └── EventSourcedOrderRepository.java
│   ├── messaging/
│   │   └── KafkaEventPublisher.java
│   └── config/
│       └── EventSourcingConfig.java
│
└── interfaces/
    └── rest/
        ├── AccountController.java
        └── OrderController.java
```

---

## Golang

```
project/
├── cmd/
│   ├── api/
│   │   └── main.go
│   └── projector/
│       └── main.go                         # Separate process for projections
│
├── internal/
│   ├── domain/
│   │   ├── account/
│   │   │   ├── account.go                 # Event-sourced aggregate
│   │   │   ├── account_id.go
│   │   │   └── event/
│   │   │       ├── account_opened.go
│   │   │       ├── money_deposited.go
│   │   │       ├── money_withdrawn.go
│   │   │       └── account_closed.go
│   │   ├── order/
│   │   │   ├── order.go
│   │   │   ├── order_id.go
│   │   │   └── event/
│   │   │       ├── order_placed.go
│   │   │       ├── order_confirmed.go
│   │   │       └── order_cancelled.go
│   │   └── shared/
│   │       ├── aggregate.go               # Base aggregate with event tracking
│   │       ├── domain_event.go
│   │       └── aggregate_id.go
│   │
│   ├── eventstore/
│   │   ├── store.go                       # Interface
│   │   ├── postgres_store.go
│   │   ├── event_stream.go
│   │   ├── stored_event.go
│   │   ├── serializer/
│   │   │   ├── serializer.go
│   │   │   └── json_serializer.go
│   │   └── snapshot/
│   │       ├── store.go                   # Interface
│   │       ├── postgres_snapshot_store.go
│   │       └── snapshot.go
│   │
│   ├── projection/
│   │   ├── account/
│   │   │   ├── balance_projection.go
│   │   │   ├── balance_projector.go
│   │   │   └── balance_read_repo.go
│   │   ├── order/
│   │   │   ├── summary_projection.go
│   │   │   ├── summary_projector.go
│   │   │   └── summary_read_repo.go
│   │   └── registry.go
│   │
│   ├── application/
│   │   ├── account/
│   │   │   ├── command/
│   │   │   │   ├── open_account.go
│   │   │   │   ├── deposit_money.go
│   │   │   │   └── withdraw_money.go
│   │   │   └── query/
│   │   │       └── get_balance.go
│   │   └── order/
│   │       ├── command/
│   │       └── query/
│   │
│   ├── infrastructure/
│   │   ├── repository/
│   │   │   ├── event_sourced_account_repo.go
│   │   │   └── event_sourced_order_repo.go
│   │   ├── messaging/
│   │   │   └── nats_publisher.go
│   │   └── config/
│   │       └── config.go
│   │
│   └── interfaces/
│       └── http/
│           ├── handler/
│           │   ├── account_handler.go
│           │   └── order_handler.go
│           └── router/
│               └── router.go
│
├── migrations/
│   ├── 001_create_event_store.up.sql
│   ├── 001_create_event_store.down.sql
│   ├── 002_create_snapshots.up.sql
│   └── 002_create_snapshots.down.sql
│
├── pkg/
│   └── eventsourcing/
│       ├── aggregate.go
│       ├── event.go
│       └── store.go
├── go.mod
└── Makefile
```

---

## Snapshot Strategy

```
Problem: 10.000 event olan aggregate-i rebuild etmək üçün 10.000 event replay lazımdır.
Həll: N eventdən bir snapshot saxla → replay yalnız snapshot-dan sonrakı event-lərdən.

Threshold-based snapshot (hər 50 eventdə bir):

  // SnapshotStore (Laravel / PHP)
  class EventSourcedAccountRepository
  {
      private const SNAPSHOT_THRESHOLD = 50;

      public function save(Account $account): void
      {
          $events = $account->pullDomainEvents();
          $this->eventStore->append($account->id(), $events);

          if ($account->version() % self::SNAPSHOT_THRESHOLD === 0) {
              $this->snapshotStore->save(
                  aggregateId: $account->id(),
                  version: $account->version(),
                  state: $account->toSnapshot(),
              );
          }
      }

      public function findById(AccountId $id): Account
      {
          $snapshot = $this->snapshotStore->findLatest($id);

          $fromVersion = $snapshot?->version ?? 0;
          $events = $this->eventStore->loadFrom($id, $fromVersion);

          $account = $snapshot
              ? Account::fromSnapshot($snapshot)
              : Account::empty($id);

          return $account->replay($events);
      }
  }

DB schema (snapshots table):
  CREATE TABLE snapshots (
      aggregate_id  UUID    NOT NULL,
      version       INT     NOT NULL,
      state         JSONB   NOT NULL,
      created_at    TIMESTAMPTZ DEFAULT now(),
      PRIMARY KEY (aggregate_id, version)
  );

Time-based alternative: hər gecə saat 02:00-da bütün active aggregate-lər üçün snapshot.
  → Sabit replay window (max 24 saat event)
  → Threshold-based-dən daha predictable
```

---

## Event Versioning / Upcasting

```
Problem: 6 ay sonra event schema dəyişir. Köhnə event-lər hələ event store-dadır.
Həll: Upcaster — köhnə event-i yeni versiyaya çevirir (read path-də, store-u dəyişmədən).

Versiya 1 → Versiya 2 migration nümunəsi:

  // V1 event (köhnə):
  {
    "type": "MoneyDeposited",
    "version": 1,
    "amount": 100.00          // float — precision problem!
  }

  // V2 event (yeni):
  {
    "type": "MoneyDeposited",
    "version": 2,
    "amount_cents": 10000,    // integer cents
    "currency": "USD"         // yeni sahə
  }

  // Upcaster (PHP):
  class MoneyDepositedUpcaster
  {
      public function upcast(array $rawEvent): array
      {
          if ($rawEvent['version'] === 1) {
              return [
                  ...$rawEvent,
                  'version'      => 2,
                  'amount_cents' => (int) round($rawEvent['amount'] * 100),
                  'currency'     => 'USD',  // default
              ];
          }
          return $rawEvent;
      }
  }

  // Event Store-dan oxuyanda upcaster pipeline-dan keçir:
  class EventStore
  {
      public function load(string $aggregateId): array
      {
          $rawEvents = $this->db->query(...);
          return array_map(
              fn($e) => $this->upcasterChain->upcast($e),
              $rawEvents,
          );
      }
  }

Qaydalar:
  - Event store-u heç vaxt UPDATE etmə (immutable log)
  - Upcaster yalnız read path-də işləyir
  - Hər versiya üçün ayrı upcaster class
  - Upcaster-lar chain şəklində: v1→v2→v3
```

---

## Projection Rebuild

```
Problem: Yeni feature üçün yeni projection lazımdır, ya mövcud projection pozuldu.
Həll: Event store-dan sıfırdan replay edərək projection yenidən qur.

Rebuild prosesi:

  Addım 1 — Projector reset:
    DELETE FROM order_summaries;  -- read model cədvəlini təmizlə
    UPDATE projector_checkpoints
    SET last_processed_position = 0
    WHERE projector = 'OrderSummaryProjector';

  Addım 2 — Replay:
    // OrderSummaryProjector.php
    class OrderSummaryProjector
    {
        public function rebuild(): void
        {
            $position = 0;
            $batchSize = 1000;

            do {
                $events = $this->eventStore->loadFrom(
                    position: $position,
                    limit: $batchSize,
                );

                foreach ($events as $event) {
                    $this->project($event);
                    $position = $event->position;
                }
            } while (count($events) === $batchSize);
        }

        private function project(StoredEvent $event): void
        {
            match ($event->type) {
                'OrderPlaced'    => $this->onOrderPlaced($event),
                'OrderConfirmed' => $this->onOrderConfirmed($event),
                'OrderShipped'   => $this->onOrderShipped($event),
                default          => null,
            };
        }
    }

  Addım 3 — Blue/Green projection rebuild (zero downtime):
    1. Yeni cədvəl yarat: order_summaries_v2
    2. Yeni projector-u buraya replay et (background)
    3. Replay bitdikdən sonra: app yeni cədvələ keçir
    4. Köhnə cədvəli sil

  Addım 4 — Checkpoint saxla (bölünmüş rebuild üçün):
    UPDATE projector_checkpoints
    SET last_processed_position = :position
    WHERE projector = 'OrderSummaryProjector';
    -- Rebuild crash olsa, checkpoint-dən davam et
```
