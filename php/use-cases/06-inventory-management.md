# Race Condition (Senior)

## Problem

E-commerce platformasında bir məhsuldan yalnız 1 ədəd qalıb. Eyni anda 100 nəfər "Al" düyməsini basır. Əgər ehtiyatsız kod yazılsa, 100 nəfərin hamısına sifariş təsdiqlənəcək, amma əslində yalnız 1 ədəd var. Bu klassik **race condition** problemidir.

**Nəticəsi:**
- Overselling (olmayan məhsulu satma)
- Müştəri narazılığı
- Maliyyə zərəri
- Sifarişləri ləğv etmək lazım gəlir

**Real-world ssenari:**
- Flash sale (məhdud sayda məhsul, çox sayda alıcı)
- Bilet satışı (konsert, təyyarə)
- Hotel otaq rezervasiyası
- Kupon/promo code istifadəsi

### Problem niyə yaranır?

Developerın yazacağı ən təbii kod: "stock oxu → kifayətdirmi yoxla → azalt → sifariş yarat". Bu kod tək process üçün düzgün işləyir. Problem çoxlu concurrent request-lərdə: iki request eyni anda `stock = 1` oxuyur, hər ikisi `1 >= 1` şərtini keçir, hər ikisi azaldır. Nəticə: `stock = -1` (oversell). Buna **TOCTOU (Time-of-Check to Time-of-Use)** race condition deyilir — yoxlama anı ilə istifadə anı arasında başqa process dəyişiklik etdi.

---

## 1. Problemin Nümayişi — Yanlış Kod

Bu kod race condition-a məruz qalır:

*Bu kod race condition-a məruz qalan yanlış sifarişetmə kodunu göstərir:*

```php
// ❌ YANLIŞ — Race condition var!
class OrderService
{
    public function placeOrder(User $user, int $productId, int $quantity): Order
    {
        $product = Product::find($productId);

        // Yoxlama anı ilə yazma anı arasında vaxt keçir
        // Bu vaxt ərzində başqa request eyni yoxlamanı keçə bilər
        if ($product->stock < $quantity) {
            throw new InsufficientStockException('Stokda kifayət qədər məhsul yoxdur');
        }

        // Bu iki əməliyyat arasında başqa request stock-u dəyişə bilər
        $product->stock -= $quantity;
        $product->save();

        return Order::create([
            'user_id'    => $user->id,
            'product_id' => $productId,
            'quantity'   => $quantity,
            'total'      => $product->price * $quantity,
            'status'     => 'confirmed',
        ]);
    }
}
```

**Problem nədir?**
1. Thread A: `stock = 1` oxuyur, `1 >= 1` — keçir
2. Thread B: `stock = 1` oxuyur (hələ dəyişməyib!), `1 >= 1` — keçir
3. Thread A: `stock = 0` yazır
4. Thread B: `stock = -1` yazır (OVERSOLD!)

---

## 2. Həll 1: Pessimistic Locking (SELECT FOR UPDATE)

Database səviyyəsində sətri kilidləyirik. Digər transaction-lar kilit açılana qədər gözləyir.

*Bu kod `lockForUpdate()` ilə DB səviyyəsində sətri kilidləyən pessimistic locking həllini göstərir:*

```php
// app/Services/OrderServicePessimistic.php
<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderServicePessimistic
{
    /**
     * Pessimistic locking ilə sifariş yaratma.
     * SELECT ... FOR UPDATE database sətirini kilidləyir.
     * Başqa transaction-lar bu sətri oxuya bilər, amma dəyişə bilməz.
     */
    public function placeOrder(User $user, int $productId, int $quantity): Order
    {
        return DB::transaction(function () use ($user, $productId, $quantity) {

            // FOR UPDATE — bu sətir transaction bitənə qədər kilidlidir
            // Başqa transaction bu sətri dəyişməyə çalışsa, gözləyəcək
            $product = Product::where('id', $productId)
                ->lockForUpdate()  // SELECT ... FOR UPDATE
                ->first();

            if (!$product) {
                throw new \RuntimeException('Məhsul tapılmadı');
            }

            if ($product->stock < $quantity) {
                throw new InsufficientStockException(
                    "Stokda kifayət qədər məhsul yoxdur. Mövcud: {$product->stock}, Tələb: {$quantity}"
                );
            }

            // Stock-u azaldırıq
            $product->stock -= $quantity;
            $product->save();

            // Sifarişi yaradırıq
            $order = Order::create([
                'user_id'    => $user->id,
                'product_id' => $productId,
                'quantity'   => $quantity,
                'total'      => $product->price * $quantity,
                'status'     => 'confirmed',
            ]);

            Log::info('Sifariş yaradıldı (pessimistic lock)', [
                'order_id'   => $order->id,
                'product_id' => $productId,
                'quantity'   => $quantity,
                'remaining'  => $product->stock,
            ]);

            return $order;
        });
    }
}
```

**Üstünlükləri:**
- Sadədir, anlamaq asandır
- Məlumat bütövlüyünə 100% zəmanət verir
- Retry məntiqinə ehtiyac yoxdur

**Çatışmazlıqları:**
- Yüksək trafik zamanı əməliyyatlar bir-birini gözləyir (blocking)
- Deadlock riski var (müxtəlif sırada kilidləmə)
- Throughput aşağı düşür
- Database bağlantıları tükənə bilər

---

## 3. Həll 2: Optimistic Locking

Sətri kilidləmirik, amma yeniləmə zamanı versiyasını yoxlayırıq. Əgər başqası əvvəl yeniləyibsə, xəta alırıq və yenidən cəhd edirik.

*Bu kod versiya yoxlaması ilə stoku azaldan optimistic locking metodunu göstərir:*

```php
// app/Models/Product.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'price', 'stock', 'version',
    ];

    /**
     * Stoku azaldır — versiya yoxlaması ilə (optimistic lock).
     * Əgər başqa transaction əvvəl yeniləyibsə, false qaytarır.
     */
    public function decrementStockOptimistic(int $quantity): bool
    {
        $affected = static::where('id', $this->id)
            ->where('version', $this->version)   // Versiya yoxlaması
            ->where('stock', '>=', $quantity)     // Kifayət qədər stock var?
            ->update([
                'stock'   => DB::raw("stock - {$quantity}"),
                'version' => DB::raw('version + 1'),
            ]);

        // Əgər 0 sətir yeniləndisə — başqası əvvəl yeniləyib
        return $affected > 0;
    }
}
```

*Bu kod products cədvəlinə optimistic locking üçün version sütunu əlavə edən migration-u göstərir:*

```php
// database/migrations/add_version_to_products_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(0)->after('stock');
        });
    }
};
```

*Bu kod versiya uyğunsuzluğunda exponential backoff ilə retry edən optimistic locking service-ini göstərir:*

```php
// app/Services/OrderServiceOptimistic.php
<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderServiceOptimistic
{
    private const MAX_RETRIES = 5;

    /**
     * Optimistic locking ilə sifariş yaratma.
     * Lock yoxdur — əvəzinə versiya yoxlaması var.
     * Uğursuz olsa, retry edir.
     */
    public function placeOrder(User $user, int $productId, int $quantity): Order
    {
        $retries = 0;

        while ($retries < self::MAX_RETRIES) {
            $product = Product::find($productId);

            if (!$product || $product->stock < $quantity) {
                throw new InsufficientStockException('Stokda kifayət qədər məhsul yoxdur');
            }

            // Optimistic lock ilə stock azaltma
            $success = $product->decrementStockOptimistic($quantity);

            if ($success) {
                // Stock uğurla azaldıldı — sifarişi yaradırıq
                $order = Order::create([
                    'user_id'    => $user->id,
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'total'      => $product->price * $quantity,
                    'status'     => 'confirmed',
                ]);

                Log::info('Sifariş yaradıldı (optimistic lock)', [
                    'order_id' => $order->id,
                    'retries'  => $retries,
                ]);

                return $order;
            }

            // Uğursuz — başqası əvvəl yeniləyib
            $retries++;
            Log::warning('Optimistic lock conflict, retrying', [
                'product_id' => $productId,
                'retry'      => $retries,
            ]);

            // Exponential backoff — kiçik gecikmə
            usleep($retries * 50_000); // 50ms, 100ms, 150ms...
        }

        throw new InsufficientStockException(
            'Sifariş yaradıla bilmədi — çox sayda paralel sorğu. Zəhmət olmasa yenidən cəhd edin.'
        );
    }
}
```

**Üstünlükləri:**
- Blocking yoxdur — yüksək throughput
- Deadlock riski yoxdur
- Əksər hallarda ilk cəhddə uğurlu olur

**Çatışmazlıqları:**
- Retry məntiqini yazmalısan
- Çox yüksək contention zamanı retry sayı artır
- Starvation riski (bəzi request-lər heç vaxt uğurlu olmaya bilər)

---

## 4. Həll 3: Atomic Database Operation (Ən Sadə)

Versiya sütununa belə ehtiyac yoxdur — conditional update ilə:

*Bu kod `WHERE stock >= quantity` şərtli update ilə həm yoxlamaq həm azaltmağı tək atomik SQL-də birləşdirən həlli göstərir:*

```php
// app/Services/OrderServiceAtomic.php
<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderServiceAtomic
{
    /**
     * Atomic conditional update — ən sadə və effektiv həll.
     * Tək SQL sorğusu ilə həm yoxlama, həm yeniləmə edir.
     */
    public function placeOrder(User $user, int $productId, int $quantity): Order
    {
        return DB::transaction(function () use ($user, $productId, $quantity) {

            // WHERE stock >= quantity — yoxlama və yeniləmə eyni anda
            // Bu tək atomic SQL əməliyyatıdır
            $affected = DB::table('products')
                ->where('id', $productId)
                ->where('stock', '>=', $quantity)
                ->update([
                    'stock'      => DB::raw("stock - {$quantity}"),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                // Ya məhsul yoxdur, ya da stock kifayət deyil
                $product = Product::find($productId);
                if (!$product) {
                    throw new \RuntimeException('Məhsul tapılmadı');
                }
                throw new InsufficientStockException(
                    "Stokda kifayət qədər məhsul yoxdur. Mövcud: {$product->stock}"
                );
            }

            $product = Product::find($productId);

            return Order::create([
                'user_id'    => $user->id,
                'product_id' => $productId,
                'quantity'   => $quantity,
                'total'      => $product->price * $quantity,
                'status'     => 'confirmed',
            ]);
        });
    }
}
```

**Niyə işləyir?**
```sql
-- Bu SQL atomic-dir. Database-in öz lock mexanizmi bunu təmin edir.
UPDATE products
SET stock = stock - 1, updated_at = NOW()
WHERE id = 123 AND stock >= 1;
-- affected_rows = 1 (uğurlu) və ya 0 (stock kifayət deyil)
```

---

## 5. Həll 4: Redis Atomic Operations

Çox yüksək trafik (flash sale) zamanı database-ə müraciət belə yavaş ola bilər. Redis in-memory olduğu üçün daha sürətlidir.

*Bu kod Redis Lua script ilə atomik stock yoxlaması və azaltması edən, sifarişi isə asinxron job-a göndərən service-i göstərir:*

```php
// app/Services/OrderServiceRedis.php
<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Jobs\ProcessConfirmedOrder;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class OrderServiceRedis
{
    /**
     * Redis DECR ilə stock azaltma.
     * DECR atomic əməliyyatdır — race condition olmur.
     */
    public function placeOrder(User $user, int $productId, int $quantity): string
    {
        $stockKey = "product:{$productId}:stock";

        // Lua script ilə yoxlama və azaltma atomic olaraq baş verir
        // Redis-in tək thread-li olması bunu mümkün edir
        $script = <<<'LUA'
            local stock = tonumber(redis.call('GET', KEYS[1]))
            if stock == nil then
                return -2  -- Açar mövcud deyil
            end
            if stock < tonumber(ARGV[1]) then
                return -1  -- Kifayət qədər stock yoxdur
            end
            redis.call('DECRBY', KEYS[1], ARGV[1])
            return stock - tonumber(ARGV[1])
        LUA;

        $result = Redis::eval($script, 1, $stockKey, $quantity);

        if ($result === -2) {
            throw new \RuntimeException('Məhsul stock məlumatı Redis-də tapılmadı');
        }

        if ($result === -1) {
            throw new InsufficientStockException('Stokda kifayət qədər məhsul yoxdur');
        }

        // Redis-dən keçdi — sifariş yaratmağı queue-a qoyuruq
        // Çünki database-ə yazma asinxron ola bilər
        $orderId = uniqid('order_', true);

        ProcessConfirmedOrder::dispatch([
            'order_id'   => $orderId,
            'user_id'    => $user->id,
            'product_id' => $productId,
            'quantity'   => $quantity,
        ]);

        Log::info('Sifariş Redis-dən təsdiqləndi', [
            'order_id'        => $orderId,
            'remaining_stock' => $result,
        ]);

        return $orderId;
    }

    /**
     * Redis stock-u database ilə sinxronlaşdırmaq.
     * Tətbiq başlayanda və ya cron ilə çağırılır.
     */
    public function syncStockToRedis(int $productId): void
    {
        $product = \App\Models\Product::find($productId);
        if ($product) {
            Redis::set("product:{$productId}:stock", $product->stock);
        }
    }

    /**
     * Bütün məhsulların stock-unu Redis-ə yükləyir.
     */
    public function warmUpAllStocks(): void
    {
        \App\Models\Product::where('stock', '>', 0)
            ->chunk(500, function ($products) {
                $pipeline = Redis::pipeline();
                foreach ($products as $product) {
                    $pipeline->set("product:{$product->id}:stock", $product->stock);
                }
                $pipeline->execute();
            });
    }
}
```

### Queue Job — Sifarişi Database-ə Yazmaq

*Bu kod Redis-də təsdiqlənmiş sifarişi DB-yə yazaraq Redis ilə sinxronizasiyanı saxlayan job-u göstərir:*

```php
// app/Jobs/ProcessConfirmedOrder.php
<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessConfirmedOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private array $orderData
    ) {}

    public function handle(): void
    {
        DB::transaction(function () {
            // Database-də də stock-u azaldırıq (eventual consistency)
            $affected = DB::table('products')
                ->where('id', $this->orderData['product_id'])
                ->where('stock', '>=', $this->orderData['quantity'])
                ->update([
                    'stock'      => DB::raw("stock - {$this->orderData['quantity']}"),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                // Redis ilə DB arasında uyğunsuzluq — kompensasiya
                Log::error('Redis-DB stock uyğunsuzluğu', $this->orderData);

                // Redis-dəki stock-u geri qaytarırıq
                Redis::incrby(
                    "product:{$this->orderData['product_id']}:stock",
                    $this->orderData['quantity']
                );

                // Sifarişi ləğv edirik
                // İstifadəçiyə bildiriş göndəririk
                return;
            }

            $product = Product::find($this->orderData['product_id']);

            Order::create([
                'user_id'    => $this->orderData['user_id'],
                'product_id' => $this->orderData['product_id'],
                'quantity'   => $this->orderData['quantity'],
                'total'      => $product->price * $this->orderData['quantity'],
                'status'     => 'confirmed',
            ]);
        });
    }

    /**
     * Job uğursuz olduqda stock-u Redis-ə qaytarırıq.
     */
    public function failed(\Throwable $exception): void
    {
        Redis::incrby(
            "product:{$this->orderData['product_id']}:stock",
            $this->orderData['quantity']
        );

        Log::error('ProcessConfirmedOrder failed, stock restored to Redis', [
            'order_data' => $this->orderData,
            'error'      => $exception->getMessage(),
        ]);
    }
}
```

---

## 6. Həll 5: Reservation Pattern (Müvəqqəti Saxlama)

Bilet satışı və hotel rezervasiyası üçün idealdır. Məhsul "müvəqqəti saxlanılır" (hold), müştəri ödəniş etdikdən sonra təsdiqlənir.

*Bilet satışı və hotel rezervasiyası üçün idealdır. Məhsul "müvəqqəti s üçün kod nümunəsi:*
```sql
-- Reservation table
CREATE TABLE stock_reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    status ENUM('reserved', 'confirmed', 'expired', 'cancelled') DEFAULT 'reserved',
    expires_at TIMESTAMP NOT NULL,
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_status (product_id, status),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

*FOREIGN KEY (user_id) REFERENCES users(id) üçün kod nümunəsi:*
```php
// app/Services/StockReservationService.php
<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockReservationService
{
    /**
     * Məhsulu müvəqqəti saxlayır (15 dəqiqə).
     * Bu müddət ərzində başqa alıcı ala bilməz.
     */
    public function reserve(User $user, int $productId, int $quantity, int $minutesToHold = 15): StockReservation
    {
        return DB::transaction(function () use ($user, $productId, $quantity, $minutesToHold) {

            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new \RuntimeException('Məhsul tapılmadı');
            }

            // Available stock = actual stock - active reservations
            $reservedQuantity = StockReservation::where('product_id', $productId)
                ->where('status', 'reserved')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            $availableStock = $product->stock - $reservedQuantity;

            if ($availableStock < $quantity) {
                throw new InsufficientStockException(
                    "Kifayət qədər məhsul yoxdur. Mövcud: {$availableStock}"
                );
            }

            return StockReservation::create([
                'product_id' => $productId,
                'user_id'    => $user->id,
                'quantity'   => $quantity,
                'status'     => 'reserved',
                'expires_at' => now()->addMinutes($minutesToHold),
            ]);
        });
    }

    /**
     * Ödəniş tamamlandıqda reservation-u təsdiqləyir.
     * Stock database-dən azaldılır.
     */
    public function confirm(int $reservationId): StockReservation
    {
        return DB::transaction(function () use ($reservationId) {

            $reservation = StockReservation::where('id', $reservationId)
                ->where('status', 'reserved')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                throw new \RuntimeException(
                    'Reservation tapılmadı və ya müddəti bitib. Yenidən cəhd edin.'
                );
            }

            // Stock-u azaldırıq
            $affected = DB::table('products')
                ->where('id', $reservation->product_id)
                ->where('stock', '>=', $reservation->quantity)
                ->update([
                    'stock'      => DB::raw("stock - {$reservation->quantity}"),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw new InsufficientStockException('Stock azaldıla bilmədi');
            }

            $reservation->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
            ]);

            return $reservation;
        });
    }

    /**
     * Reservation-u ləğv edir (istifadəçi fikrini dəyişdi).
     */
    public function cancel(int $reservationId): void
    {
        StockReservation::where('id', $reservationId)
            ->where('status', 'reserved')
            ->update(['status' => 'cancelled']);
    }
}
```

### Vaxtı Keçmiş Reservation-ları Təmizləyən Cron Job

*Vaxtı Keçmiş Reservation-ları Təmizləyən Cron Job üçün kod nümunəsi:*
```php
// app/Console/Commands/ExpireStockReservations.php
<?php

namespace App\Console\Commands;

use App\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireStockReservations extends Command
{
    protected $signature = 'reservations:expire';
    protected $description = 'Vaxtı keçmiş stock reservation-ları expire edir';

    public function handle(): void
    {
        $expired = StockReservation::where('status', 'reserved')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        if ($expired > 0) {
            Log::info("{$expired} reservation expire edildi");
            $this->info("{$expired} reservation expire edildi");
        }
    }
}

// Schedule: $schedule->command('reservations:expire')->everyMinute();
```

---

## 7. Həll 6: Event Sourcing for Inventory

Event Sourcing ilə stock-un hər dəyişikliyi bir event kimi saxlanılır. Bu ən mürəkkəb, amma ən güclü yanaşmadır.

*Event Sourcing ilə stock-un hər dəyişikliyi bir event kimi saxlanılır üçün kod nümunəsi:*
```sql
CREATE TABLE inventory_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(50) NOT NULL,     -- 'stock_added', 'stock_sold', 'stock_returned', 'stock_adjusted'
    quantity INT NOT NULL,               -- Müsbət: əlavə, Mənfi: azalma
    reference_type VARCHAR(100) NULL,    -- 'order', 'return', 'adjustment'
    reference_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_product_created (product_id, created_at),
    INDEX idx_event_type (event_type)
);

-- Materialized view: cari stock (event-lərdən hesablanır)
CREATE TABLE inventory_snapshots (
    product_id BIGINT UNSIGNED PRIMARY KEY,
    current_stock INT NOT NULL DEFAULT 0,
    reserved_stock INT NOT NULL DEFAULT 0,
    last_event_id BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);
```

*FOREIGN KEY (product_id) REFERENCES products(id) üçün kod nümunəsi:*
```php
// app/Services/EventSourcedInventoryService.php
<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryEvent;
use App\Models\InventorySnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventSourcedInventoryService
{
    /**
     * Stock əlavə edir (anbardan gəldi, return oldu, etc.)
     */
    public function addStock(int $productId, int $quantity, string $reason, ?int $userId = null): InventoryEvent
    {
        return DB::transaction(function () use ($productId, $quantity, $reason, $userId) {

            $event = InventoryEvent::create([
                'product_id'     => $productId,
                'event_type'     => 'stock_added',
                'quantity'       => $quantity,  // Müsbət
                'reference_type' => $reason,
                'created_by'     => $userId,
            ]);

            // Snapshot-u yeniləyirik
            $this->updateSnapshot($productId, $quantity);

            return $event;
        });
    }

    /**
     * Stock satılır (sifariş təsdiqləndi).
     * Pessimistic lock ilə snapshot-u yoxlayırıq.
     */
    public function sellStock(int $productId, int $quantity, int $orderId): InventoryEvent
    {
        return DB::transaction(function () use ($productId, $quantity, $orderId) {

            $snapshot = InventorySnapshot::where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$snapshot || $snapshot->current_stock < $quantity) {
                $available = $snapshot?->current_stock ?? 0;
                throw new InsufficientStockException(
                    "Stokda kifayət qədər məhsul yoxdur. Mövcud: {$available}, Tələb: {$quantity}"
                );
            }

            $event = InventoryEvent::create([
                'product_id'     => $productId,
                'event_type'     => 'stock_sold',
                'quantity'       => -$quantity,  // Mənfi
                'reference_type' => 'order',
                'reference_id'   => $orderId,
            ]);

            // Snapshot-u yeniləyirik
            $this->updateSnapshot($productId, -$quantity);

            return $event;
        });
    }

    /**
     * Stock qaytarılır (return/refund).
     */
    public function returnStock(int $productId, int $quantity, int $orderId): InventoryEvent
    {
        return DB::transaction(function () use ($productId, $quantity, $orderId) {

            $event = InventoryEvent::create([
                'product_id'     => $productId,
                'event_type'     => 'stock_returned',
                'quantity'       => $quantity,  // Müsbət — geri gəlir
                'reference_type' => 'return',
                'reference_id'   => $orderId,
            ]);

            $this->updateSnapshot($productId, $quantity);

            return $event;
        });
    }

    /**
     * Snapshot-u yeniləyir (materialized view).
     */
    private function updateSnapshot(int $productId, int $quantityChange): void
    {
        InventorySnapshot::updateOrCreate(
            ['product_id' => $productId],
            [
                'current_stock' => DB::raw("current_stock + ({$quantityChange})"),
            ]
        );
    }

    /**
     * Event-lərdən stock-u yenidən hesablayır (rebuild snapshot).
     * Əgər snapshot-da xəta aşkarlanarsa, bu metod ilə düzəldilir.
     */
    public function rebuildSnapshot(int $productId): int
    {
        $totalStock = InventoryEvent::where('product_id', $productId)
            ->sum('quantity');

        $lastEvent = InventoryEvent::where('product_id', $productId)
            ->latest()
            ->first();

        InventorySnapshot::updateOrCreate(
            ['product_id' => $productId],
            [
                'current_stock' => $totalStock,
                'last_event_id' => $lastEvent?->id,
            ]
        );

        return $totalStock;
    }

    /**
     * Məhsulun stock tarixçəsini qaytarır (audit trail).
     */
    public function getHistory(int $productId, int $limit = 50): array
    {
        return InventoryEvent::where('product_id', $productId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
```

**Event Sourcing üstünlükləri:**
- Tam audit trail — hər dəyişiklik qeydə alınır
- İstənilən zamanda stock-u yenidən hesablamaq mümkündür
- "Bu məhsuldan nə qədər satıldı?" kimi suallara cavab vermək asandır
- Bug tapılarsa, event-lərdən yenidən hesablanır

---

## 8. Controller Implementation

*8. Controller Implementation üçün kod nümunəsi:*
```php
// app/Http/Controllers/CheckoutController.php
<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Services\StockReservationService;
use App\Services\OrderServiceAtomic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private StockReservationService $reservationService,
        private OrderServiceAtomic $orderService
    ) {}

    /**
     * Addım 1: Səbətə əlavə edərkən reservation yaradır.
     */
    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:10',
        ]);

        try {
            $reservation = $this->reservationService->reserve(
                $request->user(),
                $validated['product_id'],
                $validated['quantity']
            );

            return response()->json([
                'message'        => 'Məhsul səbətə əlavə edildi',
                'reservation_id' => $reservation->id,
                'expires_at'     => $reservation->expires_at->toISOString(),
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * Addım 2: Ödəniş tamamlandıqda sifarişi təsdiqləyir.
     */
    public function confirmOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:stock_reservations,id',
        ]);

        try {
            $reservation = $this->reservationService->confirm($validated['reservation_id']);

            return response()->json([
                'message'  => 'Sifariş təsdiqləndi',
                'order_id' => $reservation->id,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }

    /**
     * Birbaşa satış (reservation olmadan) — sadə məhsullar üçün.
     */
    public function quickBuy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1|max:10',
        ]);

        try {
            $order = $this->orderService->placeOrder(
                $request->user(),
                $validated['product_id'],
                $validated['quantity']
            );

            return response()->json([
                'message'  => 'Sifariş yaradıldı',
                'order_id' => $order->id,
                'total'    => $order->total,
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }
    }
}
```

---

## 9. Yanaşmaların Müqayisəsi

| Yanaşma | Throughput | Mürəkkəblik | Race-safe | İstifadə sahəsi |
|---------|-----------|-------------|-----------|-----------------|
| Pessimistic Lock | Aşağı | Sadə | Bəli | Az trafik, kritik əməliyyatlar |
| Optimistic Lock | Orta | Orta | Bəli | Orta trafik, nadir conflict |
| Atomic Update | Yüksək | Sadə | Bəli | Ümumi e-commerce |
| Redis DECR | Çox Yüksək | Yüksək | Bəli | Flash sale, bilet satışı |
| Reservation | Orta | Yüksək | Bəli | Hotel, bilet, əvvəlcə saxla |
| Event Sourcing | Orta | Çox Yüksək | Bəli | Audit tələbi, mürəkkəb inventory |

---

## 10. Test Nümunələri

*10. Test Nümunələri üçün kod nümunəsi:*
```php
// tests/Feature/InventoryRaceConditionTest.php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\OrderServiceAtomic;
use App\Exceptions\InsufficientStockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_atomic_update_prevents_overselling(): void
    {
        $product = Product::factory()->create(['stock' => 1, 'price' => 100]);
        $service = app(OrderServiceAtomic::class);

        $successCount = 0;
        $failCount = 0;

        // 10 paralel sifariş simulyasiyası
        // Real-da bu paralel process-lərdə olur, burada ardıcıl test edirik
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            try {
                $service->placeOrder($user, $product->id, 1);
                $successCount++;
            } catch (InsufficientStockException) {
                $failCount++;
            }
        }

        // Yalnız 1 sifariş uğurlu olmalıdır
        $this->assertEquals(1, $successCount);
        $this->assertEquals(9, $failCount);

        // Stock 0 olmalıdır, mənfi olmamalıdır
        $this->assertEquals(0, $product->fresh()->stock);
    }

    public function test_reservation_holds_stock(): void
    {
        $product = Product::factory()->create(['stock' => 2]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $reservationService = app(\App\Services\StockReservationService::class);

        // İlk 2 reservation uğurlu olmalıdır
        $res1 = $reservationService->reserve($user1, $product->id, 1);
        $res2 = $reservationService->reserve($user2, $product->id, 1);

        // 3-cü reservation uğursuz olmalıdır (stock tükənib — reserved)
        $this->expectException(InsufficientStockException::class);
        $reservationService->reserve($user3, $product->id, 1);
    }

    public function test_expired_reservation_frees_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $reservationService = app(\App\Services\StockReservationService::class);

        // Reservation yaradırıq, amma vaxtını keçmiş edirik
        $res1 = $reservationService->reserve($user1, $product->id, 1, minutesToHold: 0);

        // Expire command işlədirik
        $this->artisan('reservations:expire');

        // İndi başqa istifadəçi reserve edə bilməlidir
        $res2 = $reservationService->reserve($user2, $product->id, 1);
        $this->assertNotNull($res2);
    }
}
```

---

## 11. Concurrency Test (Parallel Requests)

*11. Concurrency Test (Parallel Requests) üçün kod nümunəsi:*
```php
// tests/Feature/ConcurrencyTest.php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bu test real paralel sorğuları simulyasiya edir.
     * `pcntl_fork()` ilə child process-lər yaradılır.
     */
    public function test_concurrent_purchases_with_fork(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension tələb olunur');
        }

        $product = Product::factory()->create(['stock' => 5, 'price' => 50]);
        $concurrentRequests = 20;
        $pids = [];

        for ($i = 0; $i < $concurrentRequests; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // Child process
                $user = User::factory()->create();
                try {
                    app(\App\Services\OrderServiceAtomic::class)
                        ->placeOrder($user, $product->id, 1);
                    exit(0); // Uğurlu
                } catch (\Throwable) {
                    exit(1); // Uğursuz
                }
            }

            $pids[] = $pid;
        }

        // Bütün child process-lərin bitmənisini gözləyirik
        $successCount = 0;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) === 0) {
                $successCount++;
            }
        }

        // Yalnız 5 sifariş uğurlu olmalıdır (stock = 5)
        $this->assertEquals(5, $successCount);
        $this->assertEquals(0, $product->fresh()->stock);
    }
}
```

---

## Interview Sualları və Cavablar

**S: Pessimistic vs Optimistic locking — hansını seçərdiniz?**
C: Asılıdır. Əgər conflict nadir baş verirsə (məs. adi e-commerce), optimistic locking daha yaxşıdır — blocking yoxdur, throughput yüksəkdir. Əgər conflict tez-tez baş verirsə (məs. flash sale), pessimistic locking daha etibarlıdır — retry overhead olmur. Ən yaxşı həll isə atomic conditional update-dir (`WHERE stock >= quantity`) — sadədir və effektivdir.

**S: Redis ilə database arasında stock uyğunsuzluğu olarsa nə edərsiniz?**
C: Bu eventual consistency problemidir. Redis source of truth olmalıdır (flash sale zamanı). Database-ə asinxron yazılır (queue vasitəsilə). Əgər DB yazma uğursuz olarsa, Redis-dəki stock geri qaytarılır (compensation). Periodic reconciliation job da işlədilməlidir.

**S: Reservation pattern-də vaxt bitərsə nə olur?**
C: Müştərinin reservation-u expire olur, stock yenidən available olur. Cron job hər dəqiqə expired reservation-ları təmizləyir. Müştəriyə "Vaxtınız bitdi, yenidən cəhd edin" mesajı göstərilir. Bu bilet satışı və hotel sistemlərində standart yanaşmadır.

**S: Event Sourcing inventory üçün niyə yaxşıdır?**
C: Tam audit trail verir — hər stock dəyişikliyini, kim etdiyini, nə vaxt etdiyini bilirsiniz. Bug tapılarsa event-lərdən yenidən hesablana bilər. "Bu ay nə qədər satıldı?" kimi suallara asanlıqla cavab verir. Lakin mürəkkəbdir və kiçik layihələr üçün overkill ola bilər.

---

## Anti-patternlər

**1. Race condition — stock-u transaction olmadan azaltmaq**
`$product->stock -= 1; $product->save()` — iki eyni vaxtda gələn request eyni stock-u oxuyur, hər ikisi azaldır, birinin azaltması itirir. `DB::transaction()` + `lockForUpdate()` istifadə edin.

**2. Stock-u mənfi etməyə icazə vermək**
DB constraint olmadan kod-da yoxlamaq — race condition zamanı keçir. `CHECK (stock >= 0)` DB constraint + optimistic locking.

**3. Reservation TTL olmadan**
User cart-a əlavə edir, stock rezerv olunur, amma heç vaxt expire olmur — stock tükənmiş görünür, satış itirilir. Rezervlərə TTL qoyun, expired-ları cron ilə azad edin.

**4. Real-time inventory sinxronizasiyası**
Hər stock dəyişikliyini anında bütün sistemlərə push etmək — tight coupling. Event-driven (StockUpdated event) + eventual consistency.

**5. Audit trail olmadan stock silmək**
Kim nə vaxt nə qədər stock azaltdı bilmirsiniz — inventory discrepancy-nin kökünü tapa bilmirsiniz. Hər stock dəyişikliyi üçün `inventory_transactions` table-a yazın.

**6. Flash sale-də DB-ni birbaşa istifadə etmək**
Saniyədə minlərlə concurrent request DB-yə birbaşa `lockForUpdate()` ilə gedirsə — DB connection exhaustion, deadlock. Flash sale üçün Redis-də pre-allocation: `DECRBY flash_stock_product_123 1` atomikdir, ultra-fast-dir. DB-yə asinxron yazılır.

**7. Stock reservation-ı confirm olmadan açıq saxlamaq**
User ödəniş başladı, 3DS-ə yönləndirildi, browser-i bağladı — stock 15 dəqiqə reserved qalır, başqa user ala bilmir. Reservation timeout (15 dəq) + scheduled expiry + webhook/polling ilə ödəniş uğursuz olduqda dərhal release.
