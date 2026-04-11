# SOLID Prinsipləri — Dərin İzah və Laravel Nümunələri

SOLID — object-oriented proqramlaşdırmanın 5 əsas prinsipinin baş hərflərindən ibarətdir. Bu prinsiplər Robert C. Martin (Uncle Bob) tərəfindən formalaşdırılmışdır. Məqsəd: daha davamlı, genişlənə bilən və test edilə bilən kod yazmaq.

---

## Mündəricat

1. [S — Single Responsibility Principle](#s--single-responsibility-principle)
2. [O — Open/Closed Principle](#o--openclosed-principle)
3. [L — Liskov Substitution Principle](#l--liskov-substitution-principle)
4. [I — Interface Segregation Principle](#i--interface-segregation-principle)
5. [D — Dependency Inversion Principle](#d--dependency-inversion-principle)
6. [SOLID-in bir-biri ilə əlaqəsi](#solid-in-bir-biri-ilə-əlaqəsi)
7. [Real-world Laravel layihəsində SOLID tətbiqi](#real-world-laravel-layihəsində-solid-tətbiqi)
8. [SOLID pozuntuları necə aşkar etmək](#solid-pozuntuları-necə-aşkar-etmək)
9. [İntervyu sualları və cavabları](#intervyu-sualları-və-cavabları)

---

## S — Single Responsibility Principle

### Nədir?

**"Bir class-ın yalnız bir dəyişmə səbəbi olmalıdır."**

Yəni bir class yalnız bir iş görməlidir. Əgər class-ı dəyişdirmək üçün bir neçə fərqli səbəb varsa, bu SRP pozuntusudur.

### Niyə lazımdır?

- Dəyişiklik lokalizasiya olunur — bir yerdə dəyişiklik digər hissəni sındırmır
- Test yazmaq asanlaşır — hər class izolə şəkildə test edilə bilər
- Kod oxunuşu yaxşılaşır
- Komanda işini asanlaşdırır — fərqli developerlər fərqli class-lara toxuna bilər

### BAD — SRP Pozuntusu

*BAD — SRP Pozuntusu üçün kod nümunəsi:*
```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use PDF;

class OrderController extends Controller
{
    // Bu controller çox şey edir:
    // 1. HTTP request idarəetməsi
    // 2. Business logic
    // 3. Database əməliyyatları
    // 4. Email göndərmə
    // 5. PDF yaratma
    // 6. Logging
    public function store(Request $request)
    {
        // Validation
        $request->validate([
            'user_id'  => 'required|exists:users,id',
            'products' => 'required|array',
            'products.*.id'       => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Stock yoxla
            foreach ($request->products as $item) {
                $product = DB::table('products')->find($item['id']);
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok kifayət deyil: {$product->name}");
                }
            }

            // Sifarişi yarat
            $order = Order::create([
                'user_id'    => $request->user_id,
                'status'     => 'pending',
                'total'      => 0,
            ]);

            $total = 0;
            foreach ($request->products as $item) {
                $product = DB::table('products')->find($item['id']);
                $lineTotal = $product->price * $item['quantity'];
                $total += $lineTotal;

                DB::table('order_items')->insert([
                    'order_id'   => $order->id,
                    'product_id' => $item['id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $product->price,
                ]);

                // Stoku azalt
                DB::table('products')
                    ->where('id', $item['id'])
                    ->decrement('stock', $item['quantity']);
            }

            $order->update(['total' => $total]);

            // Email göndər
            $user = User::find($request->user_id);
            Mail::send('emails.order_confirmation', ['order' => $order], function ($m) use ($user) {
                $m->to($user->email)->subject('Sifarişiniz qəbul edildi');
            });

            // PDF invoice yarat
            $pdf = PDF::loadView('invoices.order', ['order' => $order]);
            $pdf->save(storage_path("invoices/order_{$order->id}.pdf"));

            // Log
            Log::info("Yeni sifariş yaradıldı", ['order_id' => $order->id, 'user_id' => $request->user_id]);

            DB::commit();

            return response()->json(['order_id' => $order->id], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Sifariş xətası: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
```

**Problem:** Bu controller 6 fərqli məsuliyyət daşıyır. Əgər email göndərmə məntiqi dəyişsə, controller dəyişməlidir. Əgər PDF strukturu dəyişsə, yenə controller dəyişməlidir.

---

### GOOD — SRP tətbiqi ilə

**Addım 1: OrderService — business logic**

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private OrderRepository   $orderRepository,
        private ProductRepository $productRepository,
    ) {}

    public function createOrder(int $userId, array $products): Order
    {
        DB::beginTransaction();

        try {
            $this->productRepository->validateStock($products);

            $order = $this->orderRepository->create($userId);

            $total = $this->orderRepository->attachProducts($order, $products);

            $this->productRepository->decrementStock($products);

            $order->update(['total' => $total]);

            DB::commit();

            return $order->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

**Addım 2: OrderRepository — data access**

```php
<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function create(int $userId): Order
    {
        return Order::create([
            'user_id' => $userId,
            'status'  => 'pending',
            'total'   => 0,
        ]);
    }

    public function attachProducts(Order $order, array $products): float
    {
        $total = 0;

        foreach ($products as $item) {
            $product = DB::table('products')->find($item['id']);
            $lineTotal = $product->price * $item['quantity'];
            $total += $lineTotal;

            DB::table('order_items')->insert([
                'order_id'   => $order->id,
                'product_id' => $item['id'],
                'quantity'   => $item['quantity'],
                'price'      => $product->price,
            ]);
        }

        return $total;
    }
}
```

**Addım 3: OrderNotificationService — notification məsuliyyəti**

```php
<?php

namespace App\Services;

use App\Models\Order;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\GenerateOrderInvoice;

class OrderNotificationService
{
    public function notifyOrderCreated(Order $order): void
    {
        // Job-lar queue-ya göndərilir — controller bloklanmır
        SendOrderConfirmationEmail::dispatch($order);
        GenerateOrderInvoice::dispatch($order);
    }
}
```

**Addım 4: SendOrderConfirmationEmail Job — email məsuliyyəti**

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Order $order) {}

    public function handle(): void
    {
        Mail::to($this->order->user->email)
            ->send(new \App\Mail\OrderConfirmation($this->order));
    }
}
```

**Addım 5: Yığcam Controller**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use App\Services\OrderNotificationService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private OrderService             $orderService,
        private OrderNotificationService $notificationService,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            $request->user_id,
            $request->products
        );

        $this->notificationService->notifyOrderCreated($order);

        return response()->json(['order_id' => $order->id], 201);
    }
}
```

**Nəticə:** Hər class-ın bir məsuliyyəti var. Email məntiqi dəyişsə — yalnız `SendOrderConfirmationEmail` dəyişir. Controller yalnız HTTP request/response idarə edir.

---

## O — Open/Closed Principle

### Nədir?

**"Bir class genişlənməyə açıq, dəyişdirilməyə bağlı olmalıdır."**

Mövcud kodu dəyişdirmədən yeni funksionallıq əlavə edə bilməliyik. Bu adətən abstraction, interface-lər və polymorphism vasitəsilə əldə edilir.

### BAD — OCP Pozuntusu

*BAD — OCP Pozuntusu üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

class DiscountService
{
    // Hər yeni müştəri tipi üçün bu metod dəyişdirilməlidir!
    // Bu OCP pozuntusudur.
    public function calculate(string $customerType, float $price): float
    {
        if ($customerType === 'regular') {
            return $price * 0.95; // 5% endirim
        }

        if ($customerType === 'premium') {
            return $price * 0.85; // 15% endirim
        }

        if ($customerType === 'vip') {
            return $price * 0.70; // 30% endirim
        }

        // Yeni tip: 'corporate' — bütün metodu dəyişdirməliyik!
        if ($customerType === 'corporate') {
            return $price * 0.60;
        }

        return $price;
    }
}
```

**Problem:** Hər yeni müştəri tipi əlavə etdikdə mövcud kodu dəyişdiririk. Bu xəta riskini artırır.

---

### GOOD — Strategy Pattern ilə OCP

**Interface:**

```php
<?php

namespace App\Contracts;

interface DiscountStrategy
{
    public function apply(float $price): float;
    public function getLabel(): string;
}
```

**Concrete Strategy-lər:**

```php
<?php

namespace App\Discounts;

use App\Contracts\DiscountStrategy;

class RegularDiscount implements DiscountStrategy
{
    public function apply(float $price): float
    {
        return $price * 0.95;
    }

    public function getLabel(): string
    {
        return '5% Regular Endirim';
    }
}

class PremiumDiscount implements DiscountStrategy
{
    public function apply(float $price): float
    {
        return $price * 0.85;
    }

    public function getLabel(): string
    {
        return '15% Premium Endirim';
    }
}

class VipDiscount implements DiscountStrategy
{
    public function apply(float $price): float
    {
        return $price * 0.70;
    }

    public function getLabel(): string
    {
        return '30% VIP Endirim';
    }
}

// YENİ TİP ƏLAVƏ EDİRİK — mövcud koda toxunmuruq!
class CorporateDiscount implements DiscountStrategy
{
    public function apply(float $price): float
    {
        return $price * 0.60;
    }

    public function getLabel(): string
    {
        return '40% Corporate Endirim';
    }
}
```

**DiscountService — artıq dəyişdirilmir, yalnız genişlənir:**

```php
<?php

namespace App\Services;

use App\Contracts\DiscountStrategy;

class DiscountService
{
    // Yeni strategy əlavə etmək üçün bu class-a toxunmaq lazım deyil
    public function calculate(DiscountStrategy $strategy, float $price): float
    {
        return $strategy->apply($price);
    }
}
```

**Laravel Service Container ilə binding:**

```php
<?php

namespace App\Providers;

use App\Contracts\DiscountStrategy;
use App\Discounts\RegularDiscount;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default strategy
        $this->app->bind(DiscountStrategy::class, RegularDiscount::class);
    }
}
```

**Factory ilə istifadə:**

```php
<?php

namespace App\Factories;

use App\Contracts\DiscountStrategy;
use App\Discounts\{RegularDiscount, PremiumDiscount, VipDiscount, CorporateDiscount};

class DiscountFactory
{
    private static array $strategies = [
        'regular'    => RegularDiscount::class,
        'premium'    => PremiumDiscount::class,
        'vip'        => VipDiscount::class,
        'corporate'  => CorporateDiscount::class,
    ];

    public static function make(string $type): DiscountStrategy
    {
        $class = self::$strategies[$type]
            ?? throw new \InvalidArgumentException("Naməlum endirim tipi: {$type}");

        return new $class();
    }

    // Yeni strategy əlavə etmək üçün yalnız bu array-ə əlavə edirik
    public static function register(string $type, string $class): void
    {
        self::$strategies[$type] = $class;
    }
}
```

**Controller-də istifadə:**

```php
<?php

use App\Factories\DiscountFactory;
use App\Services\DiscountService;

class PriceController extends Controller
{
    public function __construct(private DiscountService $discountService) {}

    public function calculate(Request $request)
    {
        $strategy = DiscountFactory::make($request->user()->customer_type);
        $finalPrice = $this->discountService->calculate($strategy, $request->price);

        return response()->json([
            'original_price' => $request->price,
            'final_price'    => $finalPrice,
            'discount_label' => $strategy->getLabel(),
        ]);
    }
}
```

---

## L — Liskov Substitution Principle

### Nədir?

**"Alt-class-lar üst-class-larının yerinə keçə bilməlidir, proqramın davranışını pozmadan."**

Yəni əgər `Bird` class-ından `Penguin` class-ı törəyirsə, hər yerdə `Bird` işlədilən yerdə `Penguin`-i istifadə edə bilməliyik.

### BAD — LSP Pozuntusu

*BAD — LSP Pozuntusu üçün kod nümunəsi:*
```php
<?php

namespace App\Payments;

abstract class PaymentGateway
{
    abstract public function charge(float $amount): bool;
    abstract public function refund(float $amount): bool;
    abstract public function getBalance(): float; // Problem burada!
}

class StripeGateway extends PaymentGateway
{
    public function charge(float $amount): bool
    {
        // Stripe ilə ödəniş
        return true;
    }

    public function refund(float $amount): bool
    {
        // Stripe ilə geri qaytarma
        return true;
    }

    public function getBalance(): float
    {
        return 5000.00;
    }
}

// PayPal hesab balansını dəstəkləmir!
class PayPalGateway extends PaymentGateway
{
    public function charge(float $amount): bool
    {
        return true;
    }

    public function refund(float $amount): bool
    {
        return true;
    }

    public function getBalance(): float
    {
        // LSP pozuntusu: exception atırıq — üst-class contract-ını pozuruq
        throw new \RuntimeException("PayPal hesab balansını dəstəkləmir");
    }
}

// İstifadəçi kodu qırılır:
function printBalance(PaymentGateway $gateway): void
{
    // PayPalGateway göndərildikdə exception!
    echo $gateway->getBalance();
}
```

---

### GOOD — LSP-yə uyğun dizayn

*GOOD — LSP-yə uyğun dizayn üçün kod nümunəsi:*
```php
<?php

namespace App\Contracts;

// Əsas interface — hamı implement edə bilir
interface PaymentGateway
{
    public function charge(float $amount, string $currency = 'USD'): PaymentResult;
    public function refund(string $transactionId, float $amount): RefundResult;
}

// Əlavə qabiliyyət — yalnız dəstəkləyənlər implement edir
interface SupportsBalanceCheck
{
    public function getBalance(): float;
}

// Value Object-lər
class PaymentResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly string $transactionId,
        public readonly string $message = '',
    ) {}
}

class RefundResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly string $refundId,
        public readonly string $message = '',
    ) {}
}
```

**Concrete implementasiyalar:**

```php
<?php

namespace App\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\SupportsBalanceCheck;
use App\DTOs\PaymentResult;
use App\DTOs\RefundResult;

class StripeGateway implements PaymentGateway, SupportsBalanceCheck
{
    public function charge(float $amount, string $currency = 'USD'): PaymentResult
    {
        // Stripe API çağırışı
        $response = $this->stripeClient->charges->create([
            'amount'   => (int)($amount * 100),
            'currency' => strtolower($currency),
        ]);

        return new PaymentResult(
            success:       $response->status === 'succeeded',
            transactionId: $response->id,
        );
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        $response = $this->stripeClient->refunds->create([
            'charge' => $transactionId,
            'amount' => (int)($amount * 100),
        ]);

        return new RefundResult(
            success:  $response->status === 'succeeded',
            refundId: $response->id,
        );
    }

    public function getBalance(): float
    {
        return $this->stripeClient->balance->retrieve()->available[0]->amount / 100;
    }
}

class PayPalGateway implements PaymentGateway
// SupportsBalanceCheck implement etmir — dürüst!
{
    public function charge(float $amount, string $currency = 'USD'): PaymentResult
    {
        // PayPal API çağırışı
        return new PaymentResult(success: true, transactionId: 'paypal_' . uniqid());
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        return new RefundResult(success: true, refundId: 'refund_' . uniqid());
    }
}

class BankTransferGateway implements PaymentGateway
{
    public function charge(float $amount, string $currency = 'USD'): PaymentResult
    {
        return new PaymentResult(success: true, transactionId: 'bank_' . uniqid());
    }

    public function refund(string $transactionId, float $amount): RefundResult
    {
        return new RefundResult(success: true, refundId: 'bank_refund_' . uniqid());
    }
}
```

**İstifadəçi kodu — heç bir pozuntu olmadan işləyir:**

```php
<?php

class PaymentService
{
    // PaymentGateway interface-ini qəbul edir
    // StripeGateway, PayPalGateway, BankTransferGateway — hamısı işləyir
    public function processPayment(PaymentGateway $gateway, float $amount): PaymentResult
    {
        $result = $gateway->charge($amount);

        if ($result->success) {
            // Log, notification, vs.
        }

        return $result;
    }

    // Yalnız balance dəstəkləyənlər üçün
    public function checkBalance(SupportsBalanceCheck $gateway): float
    {
        return $gateway->getBalance();
    }
}
```

**Notification kanalları ilə LSP nümunəsi:**

```php
<?php

namespace App\Contracts;

interface NotificationChannel
{
    public function send(string $recipient, string $message): bool;
}

namespace App\Notifications;

use App\Contracts\NotificationChannel;

class EmailChannel implements NotificationChannel
{
    public function send(string $recipient, string $message): bool
    {
        // Email göndər
        return Mail::to($recipient)->send(new \App\Mail\GenericNotification($message)) !== null;
    }
}

class SmsChannel implements NotificationChannel
{
    public function send(string $recipient, string $message): bool
    {
        // SMS göndər
        return $this->twilioClient->messages->create($recipient, [
            'from' => config('services.twilio.from'),
            'body' => $message,
        ])->status !== 'failed';
    }
}

class PushNotificationChannel implements NotificationChannel
{
    public function send(string $recipient, string $message): bool
    {
        // Firebase push notification
        return $this->firebaseClient->send($recipient, $message);
    }
}

// İstifadəçi kodu — hər kanalda eyni şəkildə işləyir
class NotificationDispatcher
{
    /** @param NotificationChannel[] $channels */
    public function dispatch(array $channels, string $recipient, string $message): void
    {
        foreach ($channels as $channel) {
            // LSP: hər implementasiya eyni şəkildə işləyir
            $channel->send($recipient, $message);
        }
    }
}
```

---

## I — Interface Segregation Principle

### Nədir?

**"Client-lər istifadə etmədikləri metodlara bağlı olmamalıdır."**

Bir "yağlı" (fat) interface əvəzinə, bir neçə kiçik, məqsədli interface daha yaxşıdır.

### BAD — Fat Interface

*BAD — Fat Interface üçün kod nümunəsi:*
```php
<?php

namespace App\Contracts;

// Bu interface çox şey tələb edir!
interface UserRepository
{
    // CRUD əməliyyatları
    public function find(int $id): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;

    // Search əməliyyatları
    public function search(string $query): Collection;
    public function findByEmail(string $email): ?User;
    public function findActive(): Collection;

    // Report əməliyyatları — bütün repository-lər buna ehtiyac duymur!
    public function getMonthlyRegistrations(): array;
    public function getTopSpenders(int $limit): Collection;
    public function exportToCsv(): string;

    // Cache əməliyyatları
    public function clearCache(): void;
    public function warmCache(): void;
}

// Read-only cache repository bütün metodları implement etməli!
class CachedUserRepository implements UserRepository
{
    public function find(int $id): ?User { /* cache-dən oxu */ }
    public function create(array $data): User
    {
        // Cache repository create etməməlidir! Amma mecburdur!
        throw new \RuntimeException("Cache repository write əməliyyatlarını dəstəkləmir");
    }
    // ... digər metodlar da əziyyətlə implement edilir
}
```

---

### GOOD — Parçalanmış Interface-lər

*GOOD — Parçalanmış Interface-lər üçün kod nümunəsi:*
```php
<?php

namespace App\Contracts\Repositories;

// Hər interface bir məqsəd daşıyır

interface CanFindUser
{
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findOrFail(int $id): User;
}

interface CanWriteUser
{
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
}

interface CanSearchUser
{
    public function search(string $query, array $filters = []): Collection;
    public function findActive(): Collection;
    public function findByRole(string $role): Collection;
}

interface CanReportUser
{
    public function getMonthlyRegistrations(): array;
    public function getTopSpenders(int $limit): Collection;
    public function exportToCsv(): string;
}

interface CanCacheUser
{
    public function clearCache(): void;
    public function warmCache(): void;
}
```

**Concrete implementasiyalar yalnız lazımlı interface-ləri implement edir:**

```php
<?php

namespace App\Repositories;

use App\Contracts\Repositories\{CanFindUser, CanWriteUser, CanSearchUser, CanReportUser};

// Tam repository — hər şeyi bacarır
class EloquentUserRepository implements CanFindUser, CanWriteUser, CanSearchUser, CanReportUser
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::whereEmail($email)->first();
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);
        $user->update($data);
        return $user->fresh();
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }

    public function search(string $query, array $filters = []): Collection
    {
        return User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->get();
    }

    public function findActive(): Collection
    {
        return User::whereActive(true)->get();
    }

    public function findByRole(string $role): Collection
    {
        return User::role($role)->get();
    }

    public function getMonthlyRegistrations(): array
    {
        return User::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupByRaw('MONTH(created_at)')
            ->pluck('count', 'month')
            ->toArray();
    }

    public function getTopSpenders(int $limit): Collection
    {
        return User::withSum('orders', 'total')
            ->orderByDesc('orders_sum_total')
            ->limit($limit)
            ->get();
    }

    public function exportToCsv(): string
    {
        // CSV export logic
        return storage_path('exports/users.csv');
    }
}

// Read-only cache repository — yalnız lazımlı interface-ləri implement edir
class CachedUserRepository implements CanFindUser, CanSearchUser
{
    public function __construct(
        private EloquentUserRepository $repository,
        private \Illuminate\Cache\Repository $cache,
    ) {}

    public function find(int $id): ?User
    {
        return $this->cache->remember("user:{$id}", 3600, fn() =>
            $this->repository->find($id)
        );
    }

    public function findByEmail(string $email): ?User
    {
        return $this->cache->remember("user:email:{$email}", 3600, fn() =>
            $this->repository->findByEmail($email)
        );
    }

    public function findOrFail(int $id): User
    {
        return $this->find($id) ?? throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    public function search(string $query, array $filters = []): Collection
    {
        return $this->repository->search($query, $filters);
    }

    public function findActive(): Collection
    {
        return $this->cache->remember('users:active', 1800, fn() =>
            $this->repository->findActive()
        );
    }

    public function findByRole(string $role): Collection
    {
        return $this->cache->remember("users:role:{$role}", 1800, fn() =>
            $this->repository->findByRole($role)
        );
    }
}
```

**Service-lərdə istifadə — yalnız lazımlı interface-i qəbul edir:**

```php
<?php

// Authentication service yalnız find qabiliyyətinə ehtiyac duyur
class AuthService
{
    public function __construct(private CanFindUser $userRepo) {}

    public function attempt(string $email, string $password): ?User
    {
        $user = $this->userRepo->findByEmail($email);
        // ...
    }
}

// Report service yalnız report qabiliyyətinə ehtiyac duyur
class UserReportService
{
    public function __construct(private CanReportUser $userRepo) {}

    public function generateMonthlyReport(): array
    {
        return $this->userRepo->getMonthlyRegistrations();
    }
}

// Registration service yalnız write qabiliyyətinə ehtiyac duyur
class UserRegistrationService
{
    public function __construct(private CanWriteUser $userRepo) {}

    public function register(array $data): User
    {
        return $this->userRepo->create($data);
    }
}
```

---

## D — Dependency Inversion Principle

### Nədir?

**"Yüksək səviyyəli modullar aşağı səviyyəli modullara bağlı olmamalıdır. Hər ikisi abstraction-lara bağlı olmalıdır."**

Yəni konkret implementasiyadan asılılıq yaratmaq əvəzinə, interface-dən asılılıq yaratmalıyıq.

### BAD — DIP Pozuntusu

*BAD — DIP Pozuntusu üçün kod nümunəsi:*
```php
<?php

namespace App\Services;

use App\Repositories\MySqlUserRepository; // Konkret implementasiyadan asılılıq!
use App\Notifications\SendGridEmailService; // Konkret class!
use App\Storage\LocalFileStorage; // Konkret class!

class UserService
{
    // Konkret class-lara birbaşa bağlı
    private MySqlUserRepository    $userRepository;
    private SendGridEmailService   $emailService;
    private LocalFileStorage       $fileStorage;

    public function __construct()
    {
        // Birbaşa yaradırıq — test etmək mümkünsüz!
        $this->userRepository = new MySqlUserRepository();
        $this->emailService   = new SendGridEmailService(config('services.sendgrid.key'));
        $this->fileStorage    = new LocalFileStorage(storage_path('users'));
    }

    public function register(array $data): User
    {
        $user = $this->userRepository->create($data);
        $this->emailService->send($user->email, 'Xoş gəldiniz!');
        $this->fileStorage->store("avatar_{$user->id}.png", $data['avatar']);
        return $user;
    }
}
// Problem: MySQL-i PostgreSQL ilə əvəz etmək istəsək — UserService dəyişməlidir!
// Problem: Test zamanı real SendGrid API çağırılır!
// Problem: Local storage-i S3 ilə əvəz etmək çətindir!
```

---

### GOOD — DIP + Laravel Service Container

**Interface-lər:**

```php
<?php

namespace App\Contracts;

interface UserRepository
{
    public function create(array $data): User;
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
}

interface EmailService
{
    public function send(string $to, string $subject, string $body): bool;
}

interface FileStorage
{
    public function store(string $filename, $content): string;
    public function get(string $filename): string;
    public function delete(string $filename): bool;
}
```

**Concrete implementasiyalar:**

```php
<?php

namespace App\Repositories;

use App\Contracts\UserRepository;

class EloquentUserRepository implements UserRepository
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::whereEmail($email)->first();
    }
}

// Test üçün in-memory implementasiya
class InMemoryUserRepository implements UserRepository
{
    private array $users = [];
    private int $nextId = 1;

    public function create(array $data): User
    {
        $user = new User(array_merge($data, ['id' => $this->nextId++]));
        $this->users[$user->id] = $user;
        return $user;
    }

    public function find(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        return collect($this->users)->firstWhere('email', $email);
    }
}
```

*return collect($this->users)->firstWhere('email', $email); üçün kod nümunəsi:*
```php
<?php

namespace App\Services\Email;

use App\Contracts\EmailService;

class SendGridEmailService implements EmailService
{
    public function __construct(private string $apiKey) {}

    public function send(string $to, string $subject, string $body): bool
    {
        // SendGrid API
        return true;
    }
}

class MailgunEmailService implements EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        // Mailgun API
        return true;
    }
}

// Test üçün fake implementasiya
class FakeEmailService implements EmailService
{
    public array $sentEmails = [];

    public function send(string $to, string $subject, string $body): bool
    {
        $this->sentEmails[] = compact('to', 'subject', 'body');
        return true;
    }
}
```

*$this->sentEmails[] = compact('to', 'subject', 'body'); üçün kod nümunəsi:*
```php
<?php

namespace App\Storage;

use App\Contracts\FileStorage;

class S3FileStorage implements FileStorage
{
    public function store(string $filename, $content): string
    {
        return \Storage::disk('s3')->put($filename, $content);
    }

    public function get(string $filename): string
    {
        return \Storage::disk('s3')->get($filename);
    }

    public function delete(string $filename): bool
    {
        return \Storage::disk('s3')->delete($filename);
    }
}

class LocalFileStorage implements FileStorage
{
    public function store(string $filename, $content): string
    {
        return \Storage::disk('local')->put($filename, $content);
    }

    public function get(string $filename): string
    {
        return \Storage::disk('local')->get($filename);
    }

    public function delete(string $filename): bool
    {
        return \Storage::disk('local')->delete($filename);
    }
}
```

**UserService — abstraction-lara bağlı:**

```php
<?php

namespace App\Services;

use App\Contracts\{UserRepository, EmailService, FileStorage};

class UserService
{
    // Interface-lərə bağlıyıq, konkret class-lara yox!
    public function __construct(
        private UserRepository $userRepository,
        private EmailService   $emailService,
        private FileStorage    $fileStorage,
    ) {}

    public function register(array $data): User
    {
        $user = $this->userRepository->create($data);

        $this->emailService->send(
            $user->email,
            'Xoş gəldiniz!',
            "Hörmətli {$user->name}, qeydiyyatınız uğurla tamamlandı."
        );

        if (isset($data['avatar'])) {
            $this->fileStorage->store("avatars/user_{$user->id}.png", $data['avatar']);
        }

        return $user;
    }
}
```

**Laravel Service Container ilə binding:**

```php
<?php

namespace App\Providers;

use App\Contracts\{UserRepository, EmailService, FileStorage};
use App\Repositories\EloquentUserRepository;
use App\Services\Email\SendGridEmailService;
use App\Storage\S3FileStorage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface → Concrete implementasiya binding
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);

        $this->app->bind(EmailService::class, function ($app) {
            return new SendGridEmailService(
                config('services.sendgrid.key')
            );
        });

        $this->app->bind(FileStorage::class, function ($app) {
            // Environment-ə görə fərqli implementasiya
            return app()->environment('production')
                ? new S3FileStorage()
                : new LocalFileStorage();
        });
    }
}
```

**Test zamanı — fake implementasiyaları inject et:**

```php
<?php

namespace Tests\Unit;

use App\Services\UserService;
use App\Repositories\InMemoryUserRepository;
use App\Services\Email\FakeEmailService;
use App\Storage\LocalFileStorage;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService   $userService;
    private FakeEmailService $fakeEmailService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeEmailService = new FakeEmailService();

        // Real database, real email, real S3 olmadan test!
        $this->userService = new UserService(
            userRepository: new InMemoryUserRepository(),
            emailService:   $this->fakeEmailService,
            fileStorage:    new LocalFileStorage(),
        );
    }

    public function test_register_sends_welcome_email(): void
    {
        $this->userService->register([
            'name'     => 'Əli Həsənov',
            'email'    => 'ali@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->assertCount(1, $this->fakeEmailService->sentEmails);
        $this->assertEquals('ali@example.com', $this->fakeEmailService->sentEmails[0]['to']);
    }
}
```

---

## SOLID-in bir-biri ilə əlaqəsi

SOLID prinsipləri bir-birini tamamlayır:

```
SRP  →  Hər class bir məsuliyyət daşıyır
          ↓
OCP  →  Mövcud class-ları dəyişdirmək əvəzinə yeni class-lar əlavə edirik
          ↓
LSP  →  Alt-class-lar üst-class-larının yerinə keçə bilir
          ↓
ISP  →  Interface-lər kiçik və məqsədli olur
          ↓
DIP  →  Yüksək səviyyəli modullar interface-lərə bağlanır
```

**Praktiki əlaqə — Payment sistemi nümunəsi:**

```php
<?php

// SRP: Hər class bir iş görür
// ISP: Interface parçalanmışdır
interface Chargeable
{
    public function charge(float $amount): PaymentResult;
}

interface Refundable
{
    public function refund(string $txId, float $amount): RefundResult;
}

interface Webhookable
{
    public function handleWebhook(array $payload): void;
}

// OCP: Yeni gateway əlavə etmək mövcud kodu dəyişdirmir
// LSP: Hər gateway interface contract-ını yerinə yetirir
class StripeGateway implements Chargeable, Refundable, Webhookable
{
    public function charge(float $amount): PaymentResult { /* ... */ }
    public function refund(string $txId, float $amount): RefundResult { /* ... */ }
    public function handleWebhook(array $payload): void { /* ... */ }
}

class PayPalGateway implements Chargeable, Refundable
// Webhook-u dəstəkləmir — Webhookable implement etmir (ISP, LSP uyğun)
{
    public function charge(float $amount): PaymentResult { /* ... */ }
    public function refund(string $txId, float $amount): RefundResult { /* ... */ }
}

// DIP: PaymentProcessor interface-ə bağlıdır, konkret class-a yox
class PaymentProcessor
{
    public function __construct(private Chargeable $gateway) {}

    public function process(float $amount): PaymentResult
    {
        return $this->gateway->charge($amount);
    }
}
```

---

## Real-world Laravel Layihəsində SOLID Tətbiqi

Tam e-commerce modulu strukturu:

```
app/
├── Contracts/
│   ├── Repositories/
│   │   ├── CanFindProduct.php
│   │   ├── CanWriteProduct.php
│   │   └── CanSearchProduct.php
│   ├── Payment/
│   │   ├── Chargeable.php
│   │   ├── Refundable.php
│   │   └── SupportsInstallments.php
│   └── Notification/
│       └── NotificationChannel.php
├── Services/
│   ├── OrderService.php          (SRP: yalnız order business logic)
│   ├── PaymentService.php        (SRP: yalnız payment logic)
│   └── InventoryService.php      (SRP: yalnız stok idarəetməsi)
├── Repositories/
│   ├── EloquentOrderRepository.php
│   └── CachedProductRepository.php
├── Payments/
│   ├── StripeGateway.php
│   ├── PayPalGateway.php
│   └── BankTransferGateway.php
├── Factories/
│   └── PaymentGatewayFactory.php  (OCP: yeni gateway əlavə etmək asandır)
└── Providers/
    └── PaymentServiceProvider.php (DIP: binding-lər)
```

**PaymentServiceProvider:**

```php
<?php

namespace App\Providers;

use App\Contracts\Payment\Chargeable;
use App\Payments\StripeGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Chargeable::class, function () {
            return match(config('payment.default')) {
                'stripe' => new StripeGateway(config('services.stripe.secret')),
                'paypal' => new PayPalGateway(
                    config('services.paypal.client_id'),
                    config('services.paypal.secret')
                ),
                default  => throw new \RuntimeException('Naməlum payment gateway'),
            };
        });
    }
}
```

---

## SOLID Pozuntuları Necə Aşkar Etmək

### Code Smells — SOLID pozuntularının əlamətləri

*Code Smells — SOLID pozuntularının əlamətləri üçün kod nümunəsi:*
```php
<?php

// ❌ SRP pozuntusu əlamətləri:
// 1. Class 200+ sətirdən çoxdur
// 2. Metod adlarında "And" var: createUserAndSendEmail()
// 3. Class bir neçə fərqli dependency qrupuna malikdir
class GodService // "God class" — hər şeyi bilir
{
    public function __construct(
        private UserRepo    $userRepo,     // user işi
        private OrderRepo   $orderRepo,    // order işi
        private MailService $mailService,  // email işi
        private PdfService  $pdfService,   // PDF işi
        private CacheService $cacheService, // cache işi
        private LogService  $logService,   // log işi
    ) {}
    // Problem: 6 fərqli məsuliyyət!
}

// ❌ OCP pozuntusu əlamətləri:
// 1. Çox if/switch type-checking var
class ReportGenerator
{
    public function generate(string $type): string
    {
        // Hər yeni report tipi üçün bu metod dəyişdirilir
        if ($type === 'pdf')   { return $this->generatePdf(); }
        if ($type === 'excel') { return $this->generateExcel(); }
        if ($type === 'csv')   { return $this->generateCsv(); }
        // Yeni tip: JSON — kodu dəyişdirməliyik!
    }
}

// ❌ LSP pozuntusu əlamətləri:
// 1. Alt-class metodlarda exception atılır
// 2. instanceof yoxlaması lazım olur
class ReadOnlyRepository extends WritableRepository
{
    public function save(Entity $entity): void
    {
        throw new \LogicException("Read-only repository-yə yaza bilməzsiniz!");
        // LSP pozuntusu: alt-class üst-class contract-ını pozur
    }
}

// ❌ ISP pozuntusu əlamətləri:
// 1. Interface metodları NotImplementedException ilə implement edilir
interface WorkerInterface
{
    public function work(): void;
    public function eat(): void;  // Robot işçi yeyə bilməz!
    public function sleep(): void; // Robot işçi yatmaz!
}

class RobotWorker implements WorkerInterface
{
    public function work(): void { /* ... */ }
    public function eat(): void  { throw new \Exception("Robot yeyə bilməz"); }
    public function sleep(): void { throw new \Exception("Robot yatmaz"); }
}

// ❌ DIP pozuntusu əlamətləri:
// 1. Constructor-da "new" açar sözü
// 2. Static method çağırışları (Facade misuse)
class UserRegistration
{
    public function register(array $data): void
    {
        $repo = new MySqlUserRepository(); // "new" — tight coupling!
        $mail = new SendGridMailer();      // "new" — test etmək çətin!
        // ...
    }
}
```

### Static Analysis ilə SOLID yoxlama

*Static Analysis ilə SOLID yoxlama üçün kod nümunəsi:*
```bash
# PHPStan — type safety yoxla
./vendor/bin/phpstan analyse app --level=8

# PHP Mess Detector — code smells tap
./vendor/bin/phpmd app text cleancode,design,naming

# PHP CS Fixer — kod standartları
./vendor/bin/php-cs-fixer fix app
```

---

## İntervyu Sualları və Cavabları

---

**S: SOLID nədir və niyə önəmlidir?**

C: SOLID — 5 object-oriented dizayn prinsipinin baş hərflərindən ibarət akronimdir: Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion. Bu prinsiplər kodu daha davamlı, test edilə bilən və genişlənə bilən edir. Pozuntuları isə "God class-lar", tight coupling, test çətinliyi kimi problemlərə gətirib çıxarır.

---

**S: SRP-ni real Laravel nümunəsi ilə izah edin.**

C: Controller-də business logic olmamalıdır. Məsələn, `OrderController` yalnız request-i qəbul edib, response qaytarmalıdır. Business logic `OrderService`-ə, database əməliyyatları `OrderRepository`-yə, email göndərmə isə `SendOrderEmailJob`-a verilməlidir. Bu şəkildə hər class-ın yalnız bir dəyişmə səbəbi olur.

---

**S: OCP-ni Strategy pattern ilə necə tətbiq edərdiniz?**

C: Payment metodları nümunəsi: `PaymentStrategy` interface-i yaradıb, `StripeStrategy`, `PayPalStrategy` kimi concrete class-lar implement edərik. Yeni payment metodu əlavə etmək üçün mövcud kodu dəyişdirmirik — yalnız yeni class yazıb, Factory-yə əlavə edirik. `PaymentService` isə heç dəyişmir.

---

**S: LSP pozuntusunu necə aşkar edirsiniz?**

C: Əsas əlamət: kod bazasında `instanceof` yoxlamaları görürəm. Bu o deməkdir ki, alt-class üst-class kimi davranmır və type-ı ayrıca yoxlamaq lazım gəlir. Həmçinin alt-class metodlarında `throw new NotImplementedException()` varsa — bu birbaşa LSP pozuntusudur.

---

**S: ISP ilə niyə fat interface problemli sayılır?**

C: Əgər interface 15 metod ehtiva edirsə və bir class bu interface-i implement edib 5 metodu `throw new Exception` ilə boş saxlayırsa — bu ISP pozuntusudur. Həmin class-ı implement edən developer, heç ehtiyacı olmayan metodlarla da işləməyə məcbur olur. Həll: interface-i məqsədli kiçik hissələrə parçalamaq.

---

**S: DIP Laravel Service Container ilə necə işləyir?**

C: `AppServiceProvider`-da interface-dən konkret class-a binding yazılır:
*C: `AppServiceProvider`-da interface-dən konkret class-a binding yazıl üçün kod nümunəsi:*
```php
$this->app->bind(UserRepository::class, EloquentUserRepository::class);
```
Bundan sonra hər yerdə constructor-da `UserRepository` interface-i type-hint etsək, Laravel avtomatik olaraq `EloquentUserRepository` inject edir. Test zamanı isə:
*Bundan sonra hər yerdə constructor-da `UserRepository` interface-i typ üçün kod nümunəsi:*
```php
$this->app->bind(UserRepository::class, InMemoryUserRepository::class);
```
yazaraq real database olmadan test edə bilirik.

---

**S: SOLID prinsiplərini hər zaman tətbiq etmək lazımdırmı?**

C: SOLID — tool-dur, qayda deyil. Kiçik, sadə layihələrdə bəzən over-engineering-ə səbəb ola bilər. Vacib olan tarazlıqdır: əgər kod genişlənmə tələb edəcəksə, SOLID tətbiq etmək gələcək xərci azaldır. YAGNI (You Aren't Gonna Need It) prinsipini də nəzərə almaq lazımdır.

---

**S: "Composition over Inheritance" SOLID ilə necə bağlıdır?**

C: LSP interface vasitəsilə inheritance-i düzgün istifadəyə yönləndirir. Lakin ümumiyyətlə, dərin inheritance iyerarxiyaları əvəzinə composition (Dependency Injection vasitəsilə) tövsiyə olunur. Bu həm LSP problemlərini azaldır, həm də DIP tətbiqini asanlaşdırır.

---

**S: SOLID olmadan yazan bir Laravel developerin kodunda nə görərdiniz?**

C: Tipik əlamətlər:
1. 500+ sətirlik Controller-lər — SRP yoxdur
2. Bütün if/switch-lər `type` yoxlayır — OCP yoxdur
3. `new ClassName()` constructor-larda — DIP yoxdur
4. Test yazmaq çox çətindir — çünki mock etmək mümkünsüzdür
5. Bir yerdə dəyişiklik başqa yerləri sındırır — tight coupling
6. Interface-lər yoxdur — abstraction yoxdur

---

## Anti-patternlər

**1. SRP Pozuntusuna Yol Açan "Utility" Sinifləri**
`Helper`, `Utils`, `Manager` adlı siniflərdə bir-birindən fərqli onlarca static metod toplamaq — sinif niyə dəyişəcəyini bilmək mümkünsüzləşir, test etmək çətin olur. Hər məsuliyyəti ayrı sinifə köçürün: `PriceFormatter`, `TaxCalculator`, `CurrencyConverter`.

**2. OCP-ni Pozaraq if/switch Zəncirlərini Böyütmək**
Yeni ödəniş metodu, yeni bildirim kanalı, yeni hesabat növü əlavə etmək üçün mövcud sinifin içinə yeni `if/else` bloku yazmaq — hər dəyişiklik mövcud koda toxunur, regression riski artır. Strategy pattern ilə yeni növü yeni sinif kimi əlavə edin, mövcud kodu dəyişdirməyin.

**3. LSP-ni Pozaraq Alt Sinifdə Exception Atmaq**
`Bird.fly()` üçün `Penguin` alt sinifi `throw new Exception("Penguins can't fly")` qaytaranda — parent type ilə işləyən kod child type-la qırılır. Davranışı paylaşmayan alt siniflər hierarchy-dən çıxarılmalı, ya da hierarchy yenidən modellənməlidir.

**4. Şişman Interface (Fat Interface) Yaratmaq**
`UserServiceInterface`-ə authentication, profil yeniləmə, bildiriş, hesabat kimi fərqli məsuliyyətlər yığmaq — bu interface-i implement edən hər sinif lazımsız metodları boş buraxmalı ya da exception atmalıdır. ISP-yə uyğun olaraq kiçik, fokuslanmış interface-lərə bölün.

**5. DIP Əvəzinə Concrete Class-a Bağlılıq**
Constructor-da `__construct(private StripeGateway $gateway)` kimi concrete sinfə bağlı olmaq — PayPal-a keçmək ya da test zamanı mock etmək mümkünsüzləşir. `PaymentGatewayInterface` yaradın, concrete sinfi Service Container-da bind edin, constructor interface type-hint qəbul etsin.

**6. SOLID-i Dogmatik Tətbiq Etmək**
Hər sadə metod üçün interface yaratmaq, ən sadə sinfi belə onlarca kiçik sinifə bölmək — "over-engineering" yaranır, kod anlaşılmazlaşır, mürəkkəblik artır. SOLID prinsipləri real mürəkkəbliyi azaltmaq üçün vasitədir; bürokratik qaydaya çevrilməməlidir. Hər zaman pragmatizm ilə balanslaşdırın.

---

*Son yenilənmə: 2026-04-08*
