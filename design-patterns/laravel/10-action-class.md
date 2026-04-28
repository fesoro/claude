# Action Class (Junior ⭐)

## İcmal

Action Class pattern — bir use case-i yerinə yetirən, bir məsuliyyəti olan class-dır. Laravel-in `--invokable` controller-i bunun ən tanınan formasıdır. `__invoke()` magic method-u sayəsində class-ı funksiya kimi çağırmaq mümkündür. "Fat controller" probleminə alternativ kimi həm controller, həm də service layer üçün istifadə olunur.

## Niyə Vacibdir

Ənənəvi `ResourceController` (`index`, `create`, `store`, `edit`, `update`, `destroy`) tez-tez şişir: hər metod öz business logic-ini içinə alır, controller 500+ sətir olur. Action Class hər endpoint-ə, hər use case-ə bir class verir. Hər class-ın məqsədi `StoreOrderAction` adından dərhal anlaşılır. Test yazmaq, dəyişiklik etmək izolə olunur.

## Əsas Anlayışlar

- **Single Action Controller**: `--invokable` flag ilə yaradılan, yalnız `__invoke()` metodu olan controller
- **`__invoke()`**: PHP-in "callable class" imkanı — `($action)($args)` kimi çağırıla bilər
- **Action vs Service**: Action bir use case; Service eyni domain-dəki bir neçə use case-i toplayan class
- **Route binding**: `Route::post('/orders', StoreOrderAction::class)` — class adı birbaşa route-a bağlanır
- **DI in actions**: Constructor injection action-larda da işləyir — service, repository inject olunur

## Praktik Baxış

### Real istifadə

- `StoreOrderAction` — yeni sifariş yaratmaq
- `MarkOrderAsPaidAction` — ödənişi qeydə almaq
- `ExportReportAction` — hesabat export etmək
- `ResendVerificationEmailAction` — email doğrulamasını yenidən göndərmək
- `SuspendUserAction` — istifadəçini bloklamaq

### Trade-off-lar

- **Müsbət**: class sayı artır, amma hər class-ın aydın məqsədi var; test etmək izolə olunur; route → class mappi ngi açıqdır
- **Mənfi**: kiçik layihədə çox fayl; action-lar arasında shared logic-i extract etmək tələb olunur (helper method ya da service)
- **Service vs Action**: Service `UserService.register()`, `UserService.deactivate()` kimi bir neçə use case toplayan zaman; Action tək use case üçün kifayət edir

### İstifadə etməmək

- 2-3 endpoint-li kiçik CRUD API-larda — `ResourceController` sadədir
- Action-lar bir-birini chain etməyə başlayırsa — service layer daha uyğundur
- Eyni logic-i bir neçə action paylaşırsa — ortak service extract edin, action-da çağırın

### Common mistakes

1. `__invoke()` içinə 100 sətir logic qoymaq — service-ə keçir, action yalnız orkestrə edir
2. Action-dan başqa action-ı `new` ilə çağırmaq — DI istifadə et, ya da service layer yaz
3. Action-da `Request` qaytarmaq — action HTTP-dən agnostik olmalıdır, service metodu kimi düşün
4. Hər CRUD operation üçün action yaratmaq — sadə `index`, `show` üçün ResourceController yaxşıdır

### Anti-Pattern Nə Zaman Olur?

**`__invoke()` içinə bütün logic qoymaq:**

```php
// BAD — Action şişir; service layer yoxdur
class StoreOrderAction
{
    public function __invoke(Request $request): JsonResponse
    {
        // 100 sətir: validate, check inventory, charge payment, create order, send email...
        $user = auth()->user();
        $items = $request->validated('items');

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if ($product->stock < $item['quantity']) {
                return response()->json(['error' => 'Out of stock'], 422);
            }
        }

        $total = collect($items)->sum(fn($i) => $i['price'] * $i['quantity']);
        $order = Order::create(['user_id' => $user->id, 'total' => $total]);

        foreach ($items as $item) {
            $order->items()->create($item);
            Product::find($item['product_id'])->decrement('stock', $item['quantity']);
        }

        Http::post('https://stripe.com/charge', ['amount' => $total]);
        Mail::to($user)->send(new OrderConfirmed($order));
        // ... davam edir
    }
}
```

```php
// GOOD — Action orkestrə edir; business logic service-dədir
class StoreOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder(
            PlaceOrderData::fromRequest($request)
        );

        return response()->json(new OrderResource($order), 201);
    }
}
```

**Action-ları bir-birindən chain etmək:**

```php
// BAD — action başqa action-ı çağırır; hidden coupling
class RegisterAndSubscribeAction
{
    public function __construct(
        private RegisterUserAction     $register,
        private CreateSubscriptionAction $subscribe,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Bu artıq Action deyil — Service olmalıdır
        $user = ($this->register)($request);
        ($this->subscribe)($user, 'free');
        return response()->json($user);
    }
}

// GOOD — service layer ayrı, action sadəcə çağırır
class RegisterUserAction
{
    public function __construct(private UserService $users) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->users->register(RegisterUserData::fromRequest($request));
        return response()->json(new UserResource($user), 201);
    }
}
// UserService.register() içindəki event-lər subscription-ı trigger edir
```

## Nümunələr

### Ümumi Nümunə

Bir çağrı mərkəzini düşünün. Hər agent öz üzərinə götürdüyü bir müraciəti həll edir. Agent eyni anda iki müraciəti həll etmir (single action). Müraciəti başqasına ötürsə (chain), bu artıq koordinator rolu — manager (service) edir bunu.

### PHP/Laravel Nümunəsi

**Artisan ilə yaratmaq:**

```bash
php artisan make:controller StoreOrderController --invokable
php artisan make:controller MarkOrderAsPaidController --invokable
php artisan make:controller ExportReportController --invokable
```

**Single Action Controller:**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use App\Data\PlaceOrderData;
use Illuminate\Http\JsonResponse;

class StoreOrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    // Route: Route::post('/orders', StoreOrderController::class)
    public function __invoke(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder(
            PlaceOrderData::fromRequest($request)
        );

        return response()->json(new OrderResource($order), 201);
    }
}
```

**Route qeydiyyatı — class adı birbaşa:**

```php
<?php
// routes/api.php

use App\Http\Controllers\StoreOrderController;
use App\Http\Controllers\MarkOrderAsPaidController;
use App\Http\Controllers\ExportReportController;
use App\Http\Controllers\SuspendUserController;
use App\Http\Controllers\ResendVerificationController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Hər route-un məqsədi dərhal aydındır
    Route::post('/orders',                     StoreOrderController::class)->name('orders.store');
    Route::post('/orders/{order}/pay',         MarkOrderAsPaidController::class)->name('orders.pay');
    Route::get('/reports/export',              ExportReportController::class)->name('reports.export');
    Route::post('/users/{user}/suspend',       SuspendUserController::class)->name('users.suspend');
    Route::post('/users/{user}/verify/resend', ResendVerificationController::class)->name('users.verify.resend');
});
```

**Daha ətraflı nümunə — MarkOrderAsPaidController:**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkOrderAsPaidRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class MarkOrderAsPaidController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function __invoke(MarkOrderAsPaidRequest $request, Order $order): JsonResponse
    {
        // Authorization
        $this->authorize('markAsPaid', $order);

        // Service çağır — business logic burada deyil
        $updatedOrder = $this->paymentService->markAsPaid(
            order:         $order,
            paymentMethod: $request->validated('payment_method'),
            reference:     $request->validated('reference'),
        );

        return response()->json(new OrderResource($updatedOrder));
    }
}
```

**Service layer — action-dan çağırılan real logic:**

```php
<?php

namespace App\Services;

use App\Events\OrderPaid;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function markAsPaid(Order $order, string $paymentMethod, string $reference): Order
    {
        if ($order->status !== 'pending') {
            throw new \DomainException("Only pending orders can be marked as paid");
        }

        return DB::transaction(function () use ($order, $paymentMethod, $reference): Order {
            $order->update([
                'status'           => 'paid',
                'payment_method'   => $paymentMethod,
                'payment_reference'=> $reference,
                'paid_at'          => now(),
            ]);

            event(new OrderPaid($order));

            return $order->fresh();
        });
    }
}
```

**Action as a standalone callable — service layer içindən:**

```php
<?php

namespace App\Actions;

use App\Data\CreateInvoiceData;
use App\Models\Invoice;
use App\Services\PdfService;
use App\Services\StorageService;

// Controller deyil, amma eyni "bir iş, bir class" prinsipi
class CreateInvoiceAction
{
    public function __construct(
        private readonly PdfService     $pdf,
        private readonly StorageService $storage,
    ) {}

    public function __invoke(CreateInvoiceData $data): Invoice
    {
        $content = $this->pdf->generate('invoices.template', $data->toArray());
        $path    = $this->storage->put("invoices/{$data->invoiceNumber}.pdf", $content);

        return Invoice::create([
            'order_id'       => $data->orderId,
            'invoice_number' => $data->invoiceNumber,
            'file_path'      => $path,
            'total'          => $data->total,
        ]);
    }
}

// Çağırış — DI container işlədir
class OrderService
{
    public function __construct(
        private readonly CreateInvoiceAction $createInvoice,
    ) {}

    public function finalizeOrder(Order $order): void
    {
        // Action callable kimi
        ($this->createInvoice)(CreateInvoiceData::fromOrder($order));
        $order->update(['status' => 'completed']);
    }
}
```

**Mövcud ResourceController-i Action class-lara parçalamaq:**

```php
// ƏVVƏL — şişmiş ResourceController
class OrderController extends Controller
{
    public function index(Request $request)   { /* filter, paginate, transform — 40 sətir */ }
    public function store(Request $request)   { /* validate, create, notify — 60 sətir */ }
    public function update(Request $request, Order $order) { /* validate, update — 40 sətir */ }
    public function destroy(Order $order)     { /* authorize, delete — 20 sətir */ }
    public function pay(Request $request, Order $order)    { /* payment logic — 80 sətir */ }
    public function export(Request $request)  { /* export logic — 50 sətir */ }
    // Toplam: 290+ sətir
}

// SONRA — ayrılmış action class-lar
// routes/api.php:
Route::get('/orders',            ListOrdersController::class);
Route::post('/orders',           StoreOrderController::class);
Route::put('/orders/{order}',    UpdateOrderController::class);
Route::delete('/orders/{order}', DeleteOrderController::class);
Route::post('/orders/{order}/pay', MarkOrderAsPaidController::class);
Route::get('/orders/export',     ExportOrdersController::class);
// Hər controller 15-25 sətir; məqsədi aydındır
```

## Praktik Tapşırıqlar

1. Mövcud bir `ResourceController`-i götürün; ən az 2 "qeyri-standart" action-u (pay, approve, export kimi) ayrı invokable controller-ə çıxarın; route-ları yeniləyin
2. `SuspendUserController` yazın: `SuspendUserRequest` (reason tələb edir) + `UserManagementService.suspend()` — service test edin, action test edin
3. `App\Actions` namespace-ində `GenerateMonthlyReportAction` yaradın; `ReportController` bu action-ı inject etsin; həm HTTP endpoint-dən, həm də Artisan command-dan eyni action-ı çağırın

## Əlaqəli Mövzular

- [02-service-layer.md](02-service-layer.md) — Action-ın çağırdığı business logic layer
- [11-form-object.md](11-form-object.md) — Action-a input ötürmək üçün Form Object
- [12-presenter-view-model.md](12-presenter-view-model.md) — Action-ın qaytardığı data-nı present etmək
- [../general/01-dto.md](../general/01-dto.md) — Action-a ötürülən data class-ı
- [../creational/02-factory-method.md](../creational/02-factory-method.md) — Action içindən factory ilə object yaratmaq
