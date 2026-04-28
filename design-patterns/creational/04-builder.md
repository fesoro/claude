# Builder (Middle ⭐⭐)

## İcmal
Builder pattern mürəkkəb bir obyekti addım-addım (step-by-step) yaratmağa imkan verir. Eyni construction prosesi müxtəlif "representation"-lar (variantlar) yarada bilir. Fluent interface (method chaining) ilə oxunaqlı, idarə olunan obyekt yaratma prosesi təmin edir.

## Niyə Vacibdir
Laravel-in Query Builder (`DB::table()->select()->where()->orderBy()->get()`) bu pattern-in ən yaxşı real nümunəsidir — developer-a addım-addım sorğu qurma imkanı verir. Öz layihəndə də 5+ parametrli constructor-lar yazdıqda (Telescope Anti-pattern), müştəri sifarişi, report konfiqurasiyası, API request qurma kimi hallarda Builder mürəkkəbliyi idarə edir.

## Əsas Anlayışlar
- **Builder interface**: Qurma addımlarını (set* metodları) elan edir
- **Concrete Builder**: Addımları implement edir, nəticəni `build()` ilə qaytarır
- **Director** (optional): Concrete Builder-i alır, müəyyən bir düzümdə addımları çağırır — standart konfiqurasiyaları mərkəzləşdirir
- **Fluent interface**: Hər `set*()` metodu `$this` qaytarır — method chaining mümkün olur
- **Telescope Anti-pattern**: Çox parametrli constructor — `new Order($customer, $item1, $item2, null, 'standard', 'credit_card', true)` — oxumaq çətindir, Builder bunu həll edir

## Praktik Baxış
- **Real istifadə**: Query/HTTP request builder-lər, email/notification qurma, sipariş obyekti, PDF/report konfiqurasiyası, test fixture qurma
- **Trade-off-lar**: Sadə obyektlər üçün over-engineering; hər yeni sahə Builder-ə method əlavə etməyi tələb edir; `build()` çağırılmadan yarımçıq obyekt yaranmış ola bilər
- **İstifadə etməmək**: 3-dən az parametrli sadə class-lar üçün; immutable value object-lər üçün PHP 8 named arguments daha aydındır (`new Order(customer: $c, total: 100)`)
- **Common mistakes**: `build()` çağırılmadan obyekti istifadə etmək; required field-ləri optional kimi göstərmək; Builder state-ini paylaşmaq (singleton Builder anti-pattern)

### Anti-Pattern Nə Zaman Olur?

**1. Singleton Builder — state-i təmizlənmədən yenidən istifadə:**
```php
// Pis: eyni builder instance-ı bir neçə dəfə istifadə
$builder = app(OrderBuilder::class); // singleton kimi bind edilib

$order1 = $builder->forCustomer(1)->addItem(10, 1, 500)->build();
// builder-in items array-i hələ [item10]-dadır!
$order2 = $builder->forCustomer(2)->addItem(20, 1, 300)->build();
// order2.items = [item10, item20] — YANLIŞ

// Həll: Builder-i her dəfə new ilə yarat, ya da build() sonunda reset() çağır
public function build(): Order
{
    $order = new Order(/* ... */);
    $this->reset(); // state-i sıfırla
    return $order;
}
```

**2. Builder validation-ı atlamaq:**
```php
// build() çağırılmadan yarımçıq obyekti passlamaq
function processOrder(OrderBuilder $builder): void
{
    // build() yoxdur — Order əvəzinə Builder geçirilir
    $this->repository->save($builder); // TYPE ERROR, amma PHP 7-də yaşaya bilər
}
```

**3. Builder-i DTO əvəzinə istifadə etmək:**
```php
// Əgər bütün parametrlər həmişə məcburidəsə, named arguments daha sadədir:
// Builder (lazımsız mürəkkəblik):
$order = (new OrderBuilder())->forCustomer(1)->addItem(5)->withAddress($addr)->build();

// PHP 8 named args (daha aydın):
$order = new Order(customerId: 1, itemId: 5, address: $addr);
// Builder yalnız optional/conditional step-lər çox olduqda dəyər verir
```

**4. Director-u məcburi etmək:** Director optional-dır — əgər bütün istifadə yerlərini standartlaşdırmaq istəmirsənsə, Director əlavə complexity-dir. Yalnız bir neçə standard configuration varsa Director-dan istifadə et.

## Nümunələr

### Ümumi Nümunə
E-commerce sifarişini düşün: müştəri, çatdırılma ünvanı, bir neçə məhsul, endirim kuponu, ödəniş üsulu, həmçinin hər birinin optional olub-olmaması. Constructor ilə hamısını bir yerdə keçirmək həm çaşdırıcı, həm error-prone-dur. Builder hər addımı ayrıca metodla idarə edir — hansı addım atıldı, hansı atılmadı aydın görünür.

### PHP/Laravel Nümunəsi

```php
// ===== Value Objects =====
class Money
{
    public function __construct(
        public readonly int $amount,   // qəpik cinsindən
        public readonly string $currency
    ) {}
}

class Address
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
        public readonly string $zip
    ) {}
}

// ===== Product result class =====
class Order
{
    public function __construct(
        public readonly int $customerId,
        public readonly array $items,          // [['product_id' => 1, 'qty' => 2, 'price' => 500]]
        public readonly Address $shippingAddress,
        public readonly string $paymentMethod,  // 'card', 'cash', 'bank_transfer'
        public readonly string $shippingType,   // 'standard', 'express', 'pickup'
        public readonly ?string $couponCode,
        public readonly ?string $notes,
        public readonly Money $total
    ) {}
}

// ===== Builder =====
class OrderBuilder
{
    private int $customerId;
    private array $items = [];
    private ?Address $shippingAddress = null;
    private string $paymentMethod = 'card';
    private string $shippingType = 'standard';
    private ?string $couponCode = null;
    private ?string $notes = null;

    public function forCustomer(int $customerId): static
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function addItem(int $productId, int $qty, int $priceInCents): static
    {
        $this->items[] = [
            'product_id' => $productId,
            'qty'        => $qty,
            'price'      => $priceInCents,
        ];
        return $this;
    }

    public function shippingTo(Address $address): static
    {
        $this->shippingAddress = $address;
        return $this;
    }

    public function payWith(string $method): static
    {
        $this->paymentMethod = $method;
        return $this;
    }

    public function useExpressShipping(): static
    {
        $this->shippingType = 'express';
        return $this;
    }

    public function applyCoupon(string $code): static
    {
        $this->couponCode = $code;
        return $this;
    }

    public function withNote(string $note): static
    {
        $this->notes = $note;
        return $this;
    }

    public function build(): Order
    {
        if (empty($this->items)) {
            throw new \InvalidArgumentException('Order must have at least one item.');
        }

        if ($this->shippingAddress === null && $this->shippingType !== 'pickup') {
            throw new \InvalidArgumentException('Shipping address is required for non-pickup orders.');
        }

        $total = $this->calculateTotal();

        return new Order(
            customerId:      $this->customerId,
            items:           $this->items,
            shippingAddress: $this->shippingAddress,
            paymentMethod:   $this->paymentMethod,
            shippingType:    $this->shippingType,
            couponCode:      $this->couponCode,
            notes:           $this->notes,
            total:           $total
        );
    }

    private function calculateTotal(): Money
    {
        $subtotal = array_sum(array_map(
            fn($item) => $item['price'] * $item['qty'],
            $this->items
        ));

        $shipping = match ($this->shippingType) {
            'express' => 1000,
            'pickup'  => 0,
            default   => 300,
        };

        return new Money($subtotal + $shipping, 'AZN');
    }
}

// ===== Director (standart konfiqurasiyalar) =====
class OrderDirector
{
    public function buildGuestPickupOrder(OrderBuilder $builder, int $guestId, int $productId): Order
    {
        return $builder
            ->forCustomer($guestId)
            ->addItem($productId, 1, 2000)
            ->payWith('cash')
            ->build();
    }

    public function buildPremiumExpressOrder(
        OrderBuilder $builder,
        int $customerId,
        Address $address
    ): Order {
        return $builder
            ->forCustomer($customerId)
            ->shippingTo($address)
            ->payWith('card')
            ->useExpressShipping()
            ->build();
    }
}

// ===== Controller-də istifadə =====
class OrderController extends Controller
{
    public function store(OrderRequest $request): JsonResponse
    {
        $address = new Address(
            street:  $request->street,
            city:    $request->city,
            country: $request->country,
            zip:     $request->zip
        );

        $order = (new OrderBuilder())
            ->forCustomer(auth()->id())
            ->addItem($request->product_id, $request->qty, $request->price)
            ->shippingTo($address)
            ->payWith($request->payment_method)
            ->applyCoupon($request->coupon ?? '')
            ->build();

        // $order Eloquent model-ə dönüştür və saxla
        $model = OrderRepository::create($order);

        return response()->json(['id' => $model->id], 201);
    }
}

// ===== Laravel Query Builder (artıq bildiyimiz real nümunə) =====
$users = DB::table('users')
    ->select(['id', 'name', 'email'])
    ->where('active', true)
    ->where('role', 'customer')
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

// Hər metod Builder instance-ını qaytarır, get() build() rolunu oynayır
```

## Praktik Tapşırıqlar
1. `HttpRequestBuilder` yaz: `withMethod()`, `withUrl()`, `withHeader()`, `withBody()`, `withTimeout()`, `withRetry()`, `send()` — Laravel HTTP Client-i sarmalayan fluent builder
2. Test fixture builder yaz: `UserBuilder` class — `asAdmin()`, `suspended()`, `withVerifiedEmail()`, `withOrders(int $count)`, `create()` — `User::factory()` əvəzinə öz builder-ini istifadə et
3. Mövcud layihədə 5+ parametrli constructor tap (Model, Service, DTO) — Builder ilə refactor et; PHP 8 named arguments ilə müqayisə et — hansı daha oxunaqlıdır?

## Əlaqəli Mövzular
- [Factory Method](02-factory-method.md) — Factory Method tək addımda yaradır, Builder addım-addım
- [Template Method](../behavioral/04-template-method.md) — Director, Builder addımlarını template kimi idarə edir
- [Specification](../laravel/04-specification.md) — Specification pattern query building üçün Builder ilə birgə istifadə olunur
- [Pipeline](../laravel/03-pipeline.md) — Pipeline pattern Builder-in addım-addım yanaşmasını middleware-ə tətbiq edir
