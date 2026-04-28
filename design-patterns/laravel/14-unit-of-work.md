# Unit of Work (Senior ⭐⭐⭐)

## İcmal

Unit of Work pattern — bir "iş vahidi" ərzindəki bütün dəyişiklikləri izləyir və sonda hamısını bir transaction-da commit edir. "Ya hamısı, ya heç biri" — atomicity təminatı. PHP-də Doctrine ORM-in `EntityManager::flush()` klassik implementasiyadır. Laravel/Eloquent-də `DB::transaction()` + `$model->afterCommit` hook-ları ilə əldə edilir.

## Niyə Vacibdir

`Order` yaradılarkən: `OrderItems` əlavə edilir, `Inventory` azaldılır, `Payment` charge olunur, `Wallet` balansı dəyişir. Biri uğursuz olsa digərləri rollback edilməlidir. `DB::transaction()` olmadan: Order yarandı, OrderItems yarandı, Inventory uğursuz oldu — DB inconsistent state-ə düşdü. Unit of Work bu dəyişikliklərin boundary-sini müəyyən edir.

## Əsas Anlayışlar

- **Unit of Work**: bir iş vahidi üçün transaction başlanğıcını, bitişini, rollback-ı idarə edən koordinator
- **Dirty tracking**: hansı entity-lərin dəyişdiyini izləmək — Doctrine bunu avtomatik edir
- **`flush()`**: Doctrine-in Unit of Work commit metodu — izlənən bütün dəyişiklikləri DB-yə yazar
- **Transaction boundary**: nə zaman başlanır, nə zaman bitir, nə zaman rollback olunur
- **`afterCommit` hook**: transaction uğurla commit olduqdan sonra çalışan callback — event dispatch üçün
- **Nested transaction**: `SAVEPOINT` — Eloquent-in `DB::transaction()` nested-ı dəstəkləyir (MySQL-də partial)

## Praktik Baxış

### Real istifadə

- Order placement: Order + OrderItems + Inventory + Payment — atomik
- User registration: User + Profile + FreeSubscription + ReferralCredit — atomik
- Transfer: Sender wallet azalır + Receiver wallet artır — atomik (ya ikisi, ya heç biri)
- Invoice generation: Invoice + InvoiceLines + AccountEntry — atomik

### Trade-off-lar

- **`DB::transaction()` (Eloquent)**: sadə, Laravel-native, transaction-ı anında commit edir — partial commit mümkün deyil
- **Doctrine EntityManager**: dirty tracking, lazy flush, complex aggregate — amma Laravel-ə inteqrasiya tələb edir
- **Long transaction riski**: uzun transaction — DB lock-ları, deadlock riski; transaction-ı mümkün qədər qısa saxla
- **External call riski**: transaction içindən HTTP call, email — rollback olsa external call geri alınmaz

### İstifadə etməmək

- Bir model-in sadə update-i üçün — `$model->save()` kifayətdir
- Read-only əməliyyatlar üçün — transaction lazım deyil
- Long-running process-lər üçün — transaction-ı qısa saxla; process-i kiçik chunk-lara böl

### Common mistakes

1. Transaction içindən email/SMS göndərmək — rollback olsa message göndərilmiş olar
2. Transaction içindən external HTTP call — rollback olsa charge etmiş olarıq
3. `DB::transaction()` nested etmək MySQL-də — `SAVEPOINT` deyil, eyni transaction; inner rollback outer-i da rollback edir
4. `afterCommit`-i unutmaq — event dispatch transaction-dan əvvəl; listener rollback olmuş data ilə işləyər

### Anti-Pattern Nə Zaman Olur?

**Transaction olmadan partial update:**

```php
// BAD — atomic deyil; Inventory uğursuz olsa Order yaranmış olar
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        $order = Order::create([
            'user_id' => $data->userId,
            'total'   => $data->total,
        ]);

        foreach ($data->items as $item) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
            ]);
        }

        // Bu FAIL olsa — Order və OrderItems DB-dədir, amma Inventory azalmayıb!
        Inventory::where('product_id', $item['product_id'])
                 ->decrement('stock', $item['quantity']);

        return $order;
    }
}
```

```php
// GOOD — transaction içində; hər şey ya olur, ya heç biri
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = Order::create([
                'user_id' => $data->userId,
                'total'   => $data->total,
            ]);

            foreach ($data->items as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                ]);

                // Fail olsa — ORDER + ORDERITEMS da rollback olur
                Inventory::where('product_id', $item['product_id'])
                         ->decrement('stock', $item['quantity']);
            }

            return $order;
        });
    }
}
```

**Transaction içindən external call:**

```php
// BAD — transaction içindən Stripe charge; rollback olsa pul alınmış olar
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = Order::create([...]);

            // HTTP call transaction içindədir — ROLLBACK OLSA PULU GERİ ALALMIIRIQ!
            $this->stripe->charge($order->total, $data->paymentMethod);

            $order->update(['status' => 'paid']);
            return $order;
        });
    }
}

// GOOD — payment transaction-dan xaricdədir; DB dəyişikliyi payment sonrasındadır
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        // 1. Payment transaction-dan ƏVVƏL — DB lock yoxdur
        $paymentResult = $this->stripe->charge($data->total, $data->paymentMethod);

        if (!$paymentResult->successful()) {
            throw new PaymentFailedException($paymentResult->error);
        }

        // 2. Payment uğurlu — DB-ni transaction içindəkiləri commit et
        return DB::transaction(function () use ($data, $paymentResult): Order {
            $order = Order::create([
                'user_id'            => $data->userId,
                'total'              => $data->total,
                'status'             => 'paid',
                'payment_reference'  => $paymentResult->id,
            ]);

            foreach ($data->items as $item) {
                OrderItem::create([...]);
                Inventory::where('product_id', $item['product_id'])
                         ->decrement('stock', $item['quantity']);
            }

            return $order;
        });
    }
}
```

## Nümunələr

### Ümumi Nümunə

Bank transfer-i düşünün. Ali-nin hesabından 500 AZN Vüsal-a keçirilir. "Ali-dən 500 azal" uğurlu olur, amma "Vüsal-a 500 əlavə et" uğursuz olur. Transaction olmadan: 500 AZN yoxa çıxdı. Transaction ilə: ikisi birlikdə ya olur, ya heç biri olmur. Unit of Work bu iki əməliyyatın bir "iş vahidi" olduğunu bildirir.

### PHP/Laravel Nümunəsi

**`DB::transaction()` — əsas istifadə:**

```php
<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Models\{Order, OrderItem, Inventory};
use Illuminate\Support\Facades\DB;

class PlaceOrderService
{
    public function execute(PlaceOrderData $data): Order
    {
        // Transaction boundary — bir "iş vahidi"
        return DB::transaction(function () use ($data): Order {
            // 1. Order yaratmaq
            $order = Order::create([
                'user_id'  => $data->userId,
                'status'   => 'pending',
                'currency' => $data->currency,
                'total'    => 0, // Sonradan hesablanacaq
            ]);

            $total = 0;

            // 2. OrderItems + Inventory — atomik
            foreach ($data->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);
                // lockForUpdate() — concurrent order-ları bloklaır; stock race condition yoxdur

                if ($product->stock < $item['quantity']) {
                    // Exception → transaction rollback → heç nə persist olmur
                    throw new InsufficientStockException($product->name, $item['quantity'], $product->stock);
                }

                $lineTotal = $product->price * $item['quantity'];
                $total    += $lineTotal;

                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $product->price,
                    'total'      => $lineTotal,
                ]);

                // Inventory azalt — eyni transaction; fail olsa hamısı rollback
                $product->decrement('stock', $item['quantity']);
            }

            // 3. Total-ı yenilə
            $order->update(['total' => $total]);

            // EVENT — transaction commit OLDUQDAN SONRA çalışsın
            // afterCommit: transaction rollback olsa event ÇALIŞMAZ
            $order->dispatchAfterCommit(new OrderPlaced($order));

            return $order;
        });
    }
}
```

**`afterCommit` ilə event dispatch:**

```php
<?php

// Model-də afterCommit
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // Transaction commit olduqdan sonra event fire et
    public function dispatchAfterCommit(object $event): void
    {
        // afterCommit: transaction rollback olsa bu callback çalışmaz
        DB::afterCommit(function () use ($event) {
            event($event);
        });
    }
}

// Service-da istifadə:
class PlaceOrderService
{
    public function execute(PlaceOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = $this->createOrder($data);

            // Transaction bitdikdən SONRA event — listener rollback olmuş data görməz
            DB::afterCommit(fn() => event(new OrderPlaced($order)));
            DB::afterCommit(fn() => event(new InventoryUpdated($data->items)));

            return $order;
        });
        // Burada: transaction commit oldu → afterCommit callback-lar çalışır → event-lər fire olur
    }
}
```

**`TransactionManager` service — Unit of Work abstraction:**

```php
<?php

namespace App\Infrastructure;

use Illuminate\Database\DatabaseManager;
use Closure;

class TransactionManager
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    /**
     * Verilen işi transaction içindəkilərdə icra et.
     * Exception olsa rollback, uğurlu olsa commit + afterCommit callback-lar.
     */
    public function run(Closure $work): mixed
    {
        return $this->db->transaction($work);
    }

    /**
     * Transaction commit olduqdan sonra callback çalışdır.
     * Rollback olsa callback çalışmır.
     */
    public function afterCommit(Closure $callback): void
    {
        $this->db->afterCommit($callback);
    }
}

// Service DI ilə:
class TransferService
{
    public function __construct(
        private readonly WalletRepository    $wallets,
        private readonly TransactionManager  $txManager,
    ) {}

    public function transfer(int $fromId, int $toId, float $amount): void
    {
        $this->txManager->run(function () use ($fromId, $toId, $amount): void {
            $sender   = $this->wallets->lockAndFind($fromId); // SELECT ... FOR UPDATE
            $receiver = $this->wallets->lockAndFind($toId);

            if ($sender->balance < $amount) {
                throw new InsufficientBalanceException();
            }

            $sender->decrement('balance', $amount);
            $receiver->increment('balance', $amount);

            // Audit log — eyni transaction-da
            TransferLog::create([
                'from_wallet_id' => $fromId,
                'to_wallet_id'   => $toId,
                'amount'         => $amount,
                'transferred_at' => now(),
            ]);

            // Commit sonrası event — rollback olsa göndərilməz
            $this->txManager->afterCommit(
                fn() => event(new MoneyTransferred($fromId, $toId, $amount))
            );
        });
    }
}
```

**Doctrine EntityManager — explicit Unit of Work:**

```php
<?php

// Doctrine ORM istifadə edən layihələr üçün (Laravel-ə inteqrasiya mümkündür)
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function placeOrder(PlaceOrderData $data): Order
    {
        $this->em->beginTransaction();

        try {
            $order = new Order();
            $order->setUserId($data->userId);
            $order->setStatus('pending');

            // Doctrine: persist() — "bunları izlə, flush-da DB-yə yaz"
            $this->em->persist($order);

            foreach ($data->items as $itemData) {
                $item = new OrderItem();
                $item->setOrder($order);
                $item->setProductId($itemData['product_id']);
                $item->setQuantity($itemData['quantity']);

                $this->em->persist($item);  // Track et
                // Heç bir SQL hələ atılmamışdır!
            }

            // flush(): track olunan bütün dəyişiklikləri DB-yə bir dəfəyə yaz
            $this->em->flush();

            $this->em->commit();
            return $order;
        } catch (\Throwable $e) {
            $this->em->rollback();
            $this->em->close(); // EM-i sıfırla
            throw $e;
        }
    }
}
```

**Nested transaction — `DB::transaction()` davranışı:**

```php
<?php

// Laravel-in nested transaction davranışı:
DB::transaction(function () {
    Order::create([...]); // Outer transaction

    DB::transaction(function () {
        // Bu ayrı SAVEPOINT deyil — eyni transaction!
        // Buradakı exception OUTER-i da rollback edir
        OrderItem::create([...]);
    });

    // Hər ikisi ya commit, ya rollback
});

// SAVEPOINT istəyirsinizsə explicit:
DB::transaction(function () {
    $outerOrder = Order::create([...]);

    try {
        DB::transaction(function () use ($outerOrder) {
            // Bu throw etsə — yalnız bu inner iş rollback olar... lakin
            // MySQL-də nested transaction real SAVEPOINT deyil
            // PostgreSQL-də isə dəstəklənir
        });
    } catch (\Exception $e) {
        // Inner fail oldu, outer davam edir
        Log::warning('Inner transaction failed', ['error' => $e->getMessage()]);
    }
    // outerOrder hələ də commit olacaq
});
```

**Test etmək:**

```php
<?php

class PlaceOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_places_order_atomically(): void
    {
        $user     = User::factory()->create();
        $product  = Product::factory()->create(['price' => 100.00, 'stock' => 10]);
        $service  = app(PlaceOrderService::class);

        $order = $service->execute(new PlaceOrderData(
            userId:   $user->id,
            items:    [['product_id' => $product->id, 'quantity' => 3]],
            currency: 'AZN',
        ));

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total' => 300.00]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'quantity' => 3]);
        $this->assertEquals(7, $product->fresh()->stock); // 10 - 3 = 7
    }

    public function test_rolls_back_on_insufficient_stock(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 100.00, 'stock' => 2]);
        $service = app(PlaceOrderService::class);

        $this->expectException(InsufficientStockException::class);

        $service->execute(new PlaceOrderData(
            userId:   $user->id,
            items:    [['product_id' => $product->id, 'quantity' => 5]], // Stock: 2, request: 5
            currency: 'AZN',
        ));

        // Rollback — heç bir order yaranmamalıdır
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertEquals(2, $product->fresh()->stock); // Dəyişmədi
    }

    public function test_dispatches_event_after_commit(): void
    {
        Event::fake([OrderPlaced::class]);

        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 50.00, 'stock' => 5]);

        app(PlaceOrderService::class)->execute(new PlaceOrderData(
            userId:   $user->id,
            items:    [['product_id' => $product->id, 'quantity' => 1]],
            currency: 'AZN',
        ));

        Event::assertDispatched(OrderPlaced::class);
    }
}
```

## Praktik Tapşırıqlar

1. `WalletTransferService` yazın: sender balance azal + receiver balance artır + `TransferLog` yaz — hamısı atomik; insufficient balance test edin; rollback-ı yoxlayın
2. Mövcud bir service metodunda transaction olmadan bir neçə model-in update edildiyi kodu tapın; `DB::transaction()` əlavə edin; `DB::afterCommit()` ilə event dispatch-i transaction-dan sonraya keçirin
3. `PlaceOrderService` üçün unit test yazın: uğurlu scenario, insufficient stock (rollback yoxlaması), concurrent order (lock test — 2 parallel process eyni stock-u tükəndirə bilməsin)

## Əlaqəli Mövzular

- [01-repository-pattern.md](01-repository-pattern.md) — Repository UoW içindədir; bir neçə repo bir transaction-da
- [09-state-machine-workflow.md](09-state-machine-workflow.md) — State transition + yan effektlər atomik transaction tələb edir
- [05-event-listener.md](05-event-listener.md) — `afterCommit` ilə event dispatch; transaction-safe event
- [../ddd/04-ddd-aggregates.md](../ddd/04-ddd-aggregates.md) — Aggregate bir UoW boundary-dir; ya hamısı, ya heç biri
- [../integration/04-outbox-pattern.md](../integration/04-outbox-pattern.md) — Transaction + event-i birlikdə saxlamaq; at-least-once delivery
- [../general/02-code-smells-refactoring.md](../general/02-code-smells-refactoring.md) — Transaction boundary-siz service: code smell
