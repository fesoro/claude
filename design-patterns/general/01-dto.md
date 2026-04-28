# Data Transfer Object — DTO (Middle ⭐⭐)

## Mündəricat
1. [DTO nədir?](#dto-nədir)
2. [DTO vs Value Object vs Array](#dto-vs-value-object-vs-array)
3. [Laravel-də Manual DTO Yaratma](#laraveldə-manual-dto-yaratma)
4. [Request-dən DTO Yaratma](#request-dən-dto-yaratma)
5. [DTO Validation](#dto-validation)
6. [Nested DTO-lar](#nested-dto-lar)
7. [DTO Collections](#dto-collections)
8. [DTO Serialization/Deserialization](#dto-serializationdeserialization)
9. [API Response-larda DTO](#api-response-larda-dto)
10. [DTO ilə Service Layer Integration](#dto-ilə-service-layer-integration)
11. [Spatie Laravel Data Paketi](#spatie-laravel-data-paketi)
12. [Real-World Nümunələr](#real-world-nümunələr)
13. [İntervyu Sualları](#intervyu-sualları)

---

## DTO nədir?

Data Transfer Object (DTO) — layerlər (təbəqələr) arasında data daşımaq üçün istifadə olunan sadə obyektdir. DTO-nun əsas məqsədi **strukturlaşdırılmış data** təmin etməkdir. DTO-da business logic **olmur**, yalnız data saxlanır.

**DTO-nun əsas xüsusiyyətləri:**
1. **Sadə data konteyner** — yalnız property-lər, business logic yoxdur
2. **Type safety** — hər property-nin tipi var
3. **IDE dəstəyi** — autocomplete, refactoring
4. **Dokumentasiya** — hansı data lazım olduğu aydındır
5. **Validation** — data strukturu validate oluna bilər

**Niyə lazımdır?**
- Controller-dən Service-ə data ötürmək üçün
- API request/response strukturunu təyin etmək üçün
- Array-lərdən qaçmaq üçün (typo, unutma, tip uyğunsuzluğu problemləri)

```php
// YANLIŞ - Array istifadə etmək
class UserService
{
    public function createUser(array $data): User
    {
        // $data['name'] var? Tip nədir? Null ola bilər?
        // $data['emial'] - typo!
        // IDE heç bir dəstək göstərə bilmir
        return User::create($data);
    }
}

// DOĞRU - DTO istifadə etmək
class UserService
{
    public function createUser(CreateUserDTO $dto): User
    {
        // $dto->name - IDE autocomplete
        // $dto->email - tip təyin olunub
        // Bütün property-lər aydındır
        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'role' => $dto->role->value,
        ]);
    }
}
```

---

## DTO vs Value Object vs Array

| Xüsusiyyət | DTO | Value Object | Array |
|---|---|---|---|
| Məqsəd | Data daşımaq | Domain konsepti | Ümumi məlumat |
| Business logic | Yoxdur | Var | Yoxdur |
| Immutability | Adətən bəli | Həmişə bəli | Yox |
| Equality | Reference ilə | Value ilə | Element-element |
| Validation | Constructor/factory | Constructor | Yoxdur |
| Type safety | Güclü | Güclü | Zəif |
| IDE dəstəyi | Tam | Tam | Yoxdur |

*həll yanaşmasını üçün kod nümunəsi:*
```php
// DTO - data daşıyır, logic yoxdur
readonly class CreateOrderDTO
{
    public function __construct(
        public int $userId,
        public array $items,
        public string $shippingMethod,
        public ?string $couponCode = null,
    ) {}
}

// Value Object - domain konsepti, business logic var
readonly class Money
{
    public function __construct(
        public int $amount,
        public Currency $currency,
    ) {}

    // Business logic!
    public function add(self $other): self
    {
        $this->ensureSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function format(): string { /* ... */ }
}

// Array - heç bir qarantiya yoxdur
$data = [
    'user_id' => 1,
    'items' => [...],
    // 'shipping_method' - unudulub? typo?
];
```

---

## Laravel-də Manual DTO Yaratma

### Əsas DTO strukturu

*Əsas DTO strukturu üçün kod nümunəsi:*
```php
// app/DTOs/CreateUserDTO.php
readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role = UserRole::Viewer,
        public ?string $phone = null,
        public ?string $avatar = null,
    ) {}

    // Request-dən yaratma
    public static function fromRequest(CreateUserRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            role: UserRole::from($request->validated('role', 'viewer')),
            phone: $request->validated('phone'),
            avatar: $request->validated('avatar'),
        );
    }

    // Array-dən yaratma
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            role: isset($data['role']) ? UserRole::from($data['role']) : UserRole::Viewer,
            phone: $data['phone'] ?? null,
            avatar: $data['avatar'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role->value,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
        ];
    }
}
```

### Update DTO (optional fields ilə)

*Update DTO (optional fields ilə) üçün kod nümunəsi:*
```php
// app/DTOs/UpdateUserDTO.php
readonly class UpdateUserDTO
{
    /**
     * NULL = dəyişmə yoxdur (field göndərilməyib)
     * Dəyər = yeni dəyər
     *
     * Bunu fərqləndirmək üçün special wrapper class istifadə edirik
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?UserRole $role = null,
        public readonly ?string $phone = null,
        private readonly array $dirtyFields = [],
    ) {}

    public static function fromRequest(UpdateUserRequest $request, int $userId): self
    {
        $validated = $request->validated();
        $dirtyFields = array_keys($validated);

        return new self(
            userId: $userId,
            name: $validated['name'] ?? null,
            email: $validated['email'] ?? null,
            role: isset($validated['role']) ? UserRole::from($validated['role']) : null,
            phone: $validated['phone'] ?? null,
            dirtyFields: $dirtyFields,
        );
    }

    public function hasField(string $field): bool
    {
        return in_array($field, $this->dirtyFields);
    }

    /**
     * Yalnız dəyişdirilən field-ləri qaytarır
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->hasField('name')) $data['name'] = $this->name;
        if ($this->hasField('email')) $data['email'] = $this->email;
        if ($this->hasField('role')) $data['role'] = $this->role?->value;
        if ($this->hasField('phone')) $data['phone'] = $this->phone;

        return $data;
    }
}

// Service-də istifadə
class UserService
{
    public function update(UpdateUserDTO $dto): User
    {
        $user = User::findOrFail($dto->userId);

        $changedData = $dto->toArray();

        if (empty($changedData)) {
            return $user; // Heç nə dəyişməyib
        }

        $user->update($changedData);

        return $user->fresh();
    }
}
```

---

## Request-dən DTO Yaratma

### Form Request + DTO birlikdə

*Form Request + DTO birlikdə üçün kod nümunəsi:*
```php
// app/Http/Requests/CreateOrderRequest.php
class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'shipping_address' => 'required|array',
            'shipping_address.street' => 'required|string|max:255',
            'shipping_address.city' => 'required|string|max:100',
            'shipping_address.state' => 'required|string|max:100',
            'shipping_address.zip_code' => 'required|string|max:20',
            'shipping_address.country' => 'required|string|max:100',
            'shipping_address.apartment' => 'nullable|string|max:50',
            'shipping_method' => 'required|string|in:standard,express,overnight',
            'payment_method' => 'required|string|in:card,bank_transfer,cash',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Ən azı bir məhsul seçilməlidir.',
            'items.*.product_id.exists' => 'Seçilmiş məhsul mövcud deyil.',
            'items.*.quantity.min' => 'Minimum 1 ədəd seçilməlidir.',
        ];
    }

    // DTO-ya çevirmə metodu request-in özündə
    public function toDTO(): CreateOrderDTO
    {
        return CreateOrderDTO::fromRequest($this);
    }
}

// app/DTOs/CreateOrderDTO.php
readonly class CreateOrderDTO
{
    /**
     * @param OrderItemDTO[] $items
     */
    public function __construct(
        public int $userId,
        public array $items,
        public Address $shippingAddress,
        public ShippingMethod $shippingMethod,
        public PaymentMethod $paymentMethod,
        public ?string $couponCode = null,
        public ?string $notes = null,
    ) {}

    public static function fromRequest(CreateOrderRequest $request): self
    {
        $items = array_map(
            fn (array $item) => new OrderItemDTO(
                productId: $item['product_id'],
                quantity: $item['quantity'],
            ),
            $request->validated('items'),
        );

        $addressData = $request->validated('shipping_address');

        return new self(
            userId: $request->user()->id,
            items: $items,
            shippingAddress: new Address(
                street: $addressData['street'],
                city: $addressData['city'],
                state: $addressData['state'],
                zipCode: $addressData['zip_code'],
                country: $addressData['country'],
                apartment: $addressData['apartment'] ?? null,
            ),
            shippingMethod: ShippingMethod::from($request->validated('shipping_method')),
            paymentMethod: PaymentMethod::from($request->validated('payment_method')),
            couponCode: $request->validated('coupon_code'),
            notes: $request->validated('notes'),
        );
    }
}

readonly class OrderItemDTO
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}

enum ShippingMethod: string
{
    case Standard = 'standard';
    case Express = 'express';
    case Overnight = 'overnight';

    public function estimatedDays(): int
    {
        return match($this) {
            self::Standard => 5,
            self::Express => 2,
            self::Overnight => 1,
        };
    }

    public function cost(): Money
    {
        return match($this) {
            self::Standard => Money::AZN(5.00),
            self::Express => Money::AZN(15.00),
            self::Overnight => Money::AZN(30.00),
        };
    }
}

enum PaymentMethod: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
}

// Controller-da istifadə
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $dto = $request->toDTO();
        $order = $this->orderService->placeOrder($dto);

        return response()->json(
            new OrderResource($order),
            201,
        );
    }
}
```

---

## DTO Validation

DTO-da validation iki yerdə ola bilər:
1. **Form Request-də** — HTTP layerdə, user input validation
2. **DTO constructor-da** — business rule validation

*2. **DTO constructor-da** — business rule validation üçün kod nümunəsi:*
```php
// DTO ilə business validation
readonly class TransferMoneyDTO
{
    public function __construct(
        public int $fromAccountId,
        public int $toAccountId,
        public Money $amount,
        public ?string $description = null,
    ) {
        // Business validation - DTO daxilində
        if ($this->fromAccountId === $this->toAccountId) {
            throw new InvalidArgumentException(
                'Göndərən və qəbul edən hesab eyni ola bilməz.'
            );
        }

        if ($this->amount->isZero()) {
            throw new InvalidArgumentException(
                'Transfer məbləği sıfır ola bilməz.'
            );
        }

        if ($this->description !== null && strlen($this->description) > 255) {
            throw new InvalidArgumentException(
                'Açıqlama 255 simvoldan çox ola bilməz.'
            );
        }
    }

    public static function fromRequest(TransferRequest $request): self
    {
        return new self(
            fromAccountId: $request->validated('from_account_id'),
            toAccountId: $request->validated('to_account_id'),
            amount: Money::AZN($request->validated('amount')),
            description: $request->validated('description'),
        );
    }
}

// Daha mürəkkəb validation üçün ayrıca Validator class
class CreateOrderDTOValidator
{
    public function __construct(
        private readonly ProductRepository $productRepo,
        private readonly CouponRepository $couponRepo,
    ) {}

    /**
     * @throws ValidationException
     */
    public function validate(CreateOrderDTO $dto): void
    {
        $errors = [];

        // Məhsulların mövcudluğunu yoxla
        foreach ($dto->items as $item) {
            $product = $this->productRepo->find($item->productId);

            if (!$product) {
                $errors[] = "Məhsul #{$item->productId} tapılmadı.";
                continue;
            }

            if ($product->stock < $item->quantity) {
                $errors[] = "'{$product->name}' üçün stokda kifayət qədər məhsul yoxdur. " .
                    "İstənilən: {$item->quantity}, Mövcud: {$product->stock}";
            }

            if (!$product->is_active) {
                $errors[] = "'{$product->name}' aktiv deyil.";
            }
        }

        // Kupon yoxla
        if ($dto->couponCode) {
            $coupon = $this->couponRepo->findByCode($dto->couponCode);

            if (!$coupon || $coupon->isExpired()) {
                $errors[] = 'Kupon kodu etibarsızdır və ya vaxtı bitib.';
            }

            if ($coupon && $coupon->isUsageLimitReached()) {
                $errors[] = 'Kupon istifadə limiti aşılıb.';
            }
        }

        if (!empty($errors)) {
            throw new DomainValidationException($errors);
        }
    }
}
```

---

## Nested DTO-lar

Mürəkkəb data strukturları üçün DTO-lar bir-birinin içində ola bilər.

*Mürəkkəb data strukturları üçün DTO-lar bir-birinin içində ola bilər üçün kod nümunəsi:*
```php
// Nested DTO nümunəsi - Sifariş yaratma
readonly class CreateOrderDTO
{
    /**
     * @param OrderItemDTO[] $items
     */
    public function __construct(
        public CustomerDTO $customer,
        public array $items,
        public AddressDTO $shippingAddress,
        public AddressDTO $billingAddress,
        public PaymentDTO $payment,
        public ?CouponDTO $coupon = null,
    ) {}

    public static function fromRequest(CreateOrderRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            customer: CustomerDTO::fromArray($validated['customer']),
            items: array_map(
                fn (array $item) => OrderItemDTO::fromArray($item),
                $validated['items'],
            ),
            shippingAddress: AddressDTO::fromArray($validated['shipping_address']),
            billingAddress: AddressDTO::fromArray(
                $validated['billing_address'] ?? $validated['shipping_address']
            ),
            payment: PaymentDTO::fromArray($validated['payment']),
            coupon: isset($validated['coupon'])
                ? CouponDTO::fromArray($validated['coupon'])
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'customer' => $this->customer->toArray(),
            'items' => array_map(fn (OrderItemDTO $item) => $item->toArray(), $this->items),
            'shipping_address' => $this->shippingAddress->toArray(),
            'billing_address' => $this->billingAddress->toArray(),
            'payment' => $this->payment->toArray(),
            'coupon' => $this->coupon?->toArray(),
        ];
    }
}

readonly class CustomerDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
        );
    }

    public static function fromUser(User $user): self
    {
        return new self(
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}

readonly class OrderItemDTO
{
    public function __construct(
        public int $productId,
        public int $quantity,
        public ?Money $unitPrice = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            quantity: $data['quantity'],
            unitPrice: isset($data['unit_price'])
                ? Money::AZN($data['unit_price'])
                : null,
            notes: $data['notes'] ?? null,
        );
    }

    public function lineTotal(): ?Money
    {
        return $this->unitPrice?->multiply($this->quantity);
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice?->toFloat(),
            'notes' => $this->notes,
        ];
    }
}

readonly class AddressDTO
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        public string $zipCode,
        public string $country,
        public ?string $apartment = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            state: $data['state'],
            zipCode: $data['zip_code'],
            country: $data['country'],
            apartment: $data['apartment'] ?? null,
        );
    }

    public function toValueObject(): Address
    {
        return new Address(
            street: $this->street,
            city: $this->city,
            state: $this->state,
            zipCode: $this->zipCode,
            country: $this->country,
            apartment: $this->apartment,
        );
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'country' => $this->country,
            'apartment' => $this->apartment,
        ];
    }
}

readonly class PaymentDTO
{
    public function __construct(
        public PaymentMethod $method,
        public ?string $cardToken = null,
        public ?string $bankAccount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            method: PaymentMethod::from($data['method']),
            cardToken: $data['card_token'] ?? null,
            bankAccount: $data['bank_account'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method->value,
            'card_token' => $this->cardToken,
            'bank_account' => $this->bankAccount,
        ];
    }
}

readonly class CouponDTO
{
    public function __construct(
        public string $code,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(code: $data['code']);
    }

    public function toArray(): array
    {
        return ['code' => $this->code];
    }
}
```

---

## DTO Collections

*DTO Collections üçün kod nümunəsi:*
```php
// DTO Collection - tipli collection
class OrderItemDTOCollection implements Countable, IteratorAggregate
{
    /** @var OrderItemDTO[] */
    private array $items;

    public function __construct(OrderItemDTO ...$items)
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Sifariş ən azı bir məhsul içərməlidir.');
        }
        $this->items = $items;
    }

    public static function fromArray(array $items): self
    {
        return new self(...array_map(
            fn (array $item) => OrderItemDTO::fromArray($item),
            $items,
        ));
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(
            fn (OrderItemDTO $item) => $item->quantity,
            $this->items,
        ));
    }

    public function totalAmount(): ?Money
    {
        $total = Money::zero(Currency::AZN());

        foreach ($this->items as $item) {
            $lineTotal = $item->lineTotal();
            if ($lineTotal) {
                $total = $total->add($lineTotal);
            }
        }

        return $total;
    }

    public function hasProduct(int $productId): bool
    {
        foreach ($this->items as $item) {
            if ($item->productId === $productId) {
                return true;
            }
        }
        return false;
    }

    public function getProductIds(): array
    {
        return array_unique(array_map(
            fn (OrderItemDTO $item) => $item->productId,
            $this->items,
        ));
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function toArray(): array
    {
        return array_map(
            fn (OrderItemDTO $item) => $item->toArray(),
            $this->items,
        );
    }
}

// İstifadə
$items = OrderItemDTOCollection::fromArray([
    ['product_id' => 1, 'quantity' => 2, 'unit_price' => 49.99],
    ['product_id' => 3, 'quantity' => 1, 'unit_price' => 99.99],
]);

echo $items->totalQuantity(); // 3
echo $items->totalAmount();   // 199.97 ₼
echo $items->count();         // 2
echo $items->hasProduct(1);   // true

// Iterate
foreach ($items as $item) {
    echo "{$item->productId}: {$item->quantity} ədəd";
}
```

---

## DTO Serialization/Deserialization

*DTO Serialization/Deserialization üçün kod nümunəsi:*
```php
// JSON-a çevirmə və JSON-dan yaratma
readonly class ProductDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public Money $price,
        public int $stock,
        public ?string $imageUrl = null,
        public array $tags = [],
        public ?CategoryDTO $category = null,
    ) {}

    // JSON-dan yaratma
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Yanlış JSON format.');
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            description: $data['description'],
            price: Money::fromFloat($data['price'], new Currency($data['currency'] ?? 'AZN')),
            stock: $data['stock'],
            imageUrl: $data['image_url'] ?? null,
            tags: $data['tags'] ?? [],
            category: isset($data['category'])
                ? CategoryDTO::fromArray($data['category'])
                : null,
        );
    }

    // Eloquent Model-dən yaratma
    public static function fromModel(Product $product): self
    {
        return new self(
            id: $product->id,
            name: $product->name,
            description: $product->description,
            price: $product->price, // Cast ilə artıq Money
            stock: $product->stock,
            imageUrl: $product->image_url,
            tags: $product->tags ?? [],
            category: $product->category
                ? CategoryDTO::fromModel($product->category)
                : null,
        );
    }

    // Collection-dan yaratma
    public static function collection(Collection $products): array
    {
        return $products->map(fn (Product $p) => self::fromModel($p))->all();
    }

    // JSON serialization
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price->toFloat(),
            'currency' => $this->price->currency->code,
            'price_formatted' => $this->price->format(),
            'stock' => $this->stock,
            'in_stock' => $this->stock > 0,
            'image_url' => $this->imageUrl,
            'tags' => $this->tags,
            'category' => $this->category,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}

readonly class CategoryDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $slug = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            slug: $data['slug'] ?? null,
        );
    }

    public static function fromModel(Category $category): self
    {
        return new self(
            id: $category->id,
            name: $category->name,
            slug: $category->slug,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}

// Cache ilə istifadə
class ProductService
{
    public function getProduct(int $id): ProductDTO
    {
        return Cache::remember("product.{$id}", 3600, function () use ($id) {
            $product = Product::with('category')->findOrFail($id);
            return ProductDTO::fromModel($product);
        });
    }

    public function getProducts(): array
    {
        return Cache::remember('products.all', 3600, function () {
            $products = Product::with('category')->active()->get();
            return ProductDTO::collection($products);
        });
    }
}
```

---

## API Response-larda DTO

*API Response-larda DTO üçün kod nümunəsi:*
```php
// Response DTO-lar
readonly class ApiResponseDTO implements JsonSerializable
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public array $errors = [],
        public ?PaginationDTO $pagination = null,
    ) {}

    public static function success(mixed $data, ?string $message = null): self
    {
        return new self(
            success: true,
            data: $data,
            message: $message,
        );
    }

    public static function error(string $message, array $errors = []): self
    {
        return new self(
            success: false,
            message: $message,
            errors: $errors,
        );
    }

    public static function paginated(
        array $data,
        LengthAwarePaginator $paginator,
    ): self {
        return new self(
            success: true,
            data: $data,
            pagination: PaginationDTO::fromPaginator($paginator),
        );
    }

    public function jsonSerialize(): array
    {
        $response = ['success' => $this->success];

        if ($this->data !== null) {
            $response['data'] = $this->data;
        }

        if ($this->message) {
            $response['message'] = $this->message;
        }

        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        if ($this->pagination) {
            $response['pagination'] = $this->pagination;
        }

        return $response;
    }
}

readonly class PaginationDTO implements JsonSerializable
{
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
        public bool $hasMorePages,
    ) {}

    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            perPage: $paginator->perPage(),
            total: $paginator->total(),
            hasMorePages: $paginator->hasMorePages(),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'per_page' => $this->perPage,
            'total' => $this->total,
            'has_more_pages' => $this->hasMorePages,
        ];
    }
}

// Controller-da istifadə
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = Product::with('category')
            ->active()
            ->paginate($request->integer('per_page', 20));

        $products = ProductDTO::collection($paginator->getCollection());

        $response = ApiResponseDTO::paginated($products, $paginator);

        return response()->json($response);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->productService->getProduct($id);
            return response()->json(ApiResponseDTO::success($product));
        } catch (ModelNotFoundException) {
            return response()->json(
                ApiResponseDTO::error('Məhsul tapılmadı.'),
                404,
            );
        }
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $dto = CreateProductDTO::fromRequest($request);
        $product = $this->productService->create($dto);

        return response()->json(
            ApiResponseDTO::success(
                ProductDTO::fromModel($product),
                'Məhsul uğurla yaradıldı.',
            ),
            201,
        );
    }
}
```

---

## DTO ilə Service Layer Integration

*DTO ilə Service Layer Integration üçün kod nümunəsi:*
```php
// Tam axış: Request -> DTO -> Service -> Response

// 1. Form Request
class PlaceOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'shipping_address.street' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.zip_code' => 'required|string',
            'shipping_address.country' => 'required|string',
            'payment_method' => 'required|in:card,bank_transfer',
            'card_token' => 'required_if:payment_method,card',
        ];
    }
}

// 2. Controller
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        // Request -> DTO
        $dto = PlaceOrderDTO::fromRequest($request);

        try {
            // DTO -> Service
            $result = $this->orderService->placeOrder($dto);

            // Service result -> Response DTO
            return response()->json(
                ApiResponseDTO::success(
                    data: OrderDTO::fromModel($result->order),
                    message: 'Sifariş uğurla yaradıldı.',
                ),
                201,
            );
        } catch (InsufficientStockException $e) {
            return response()->json(
                ApiResponseDTO::error("Stokda kifayət qədər məhsul yoxdur: {$e->getMessage()}"),
                422,
            );
        } catch (PaymentFailedException $e) {
            return response()->json(
                ApiResponseDTO::error("Ödəniş uğursuz oldu: {$e->getMessage()}"),
                422,
            );
        }
    }
}

// 3. Service
class OrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly ProductRepository $productRepo,
        private readonly PaymentService $paymentService,
        private readonly CreateOrderDTOValidator $validator,
    ) {}

    public function placeOrder(PlaceOrderDTO $dto): PlaceOrderResultDTO
    {
        // Business validation
        $this->validator->validate($dto);

        return DB::transaction(function () use ($dto) {
            // Order yarat
            $order = $this->orderRepo->createFromDTO($dto);

            // Stokdan düş
            foreach ($dto->items as $item) {
                $this->productRepo->decrementStock($item->productId, $item->quantity);
            }

            // Ödəniş al
            $paymentResult = $this->paymentService->charge(
                amount: $order->total,
                method: $dto->payment->method,
                token: $dto->payment->cardToken,
            );

            $order->markAsPaid($paymentResult->transactionId);

            return new PlaceOrderResultDTO(
                order: $order,
                paymentTransactionId: $paymentResult->transactionId,
            );
        });
    }
}

// 4. Result DTO
readonly class PlaceOrderResultDTO
{
    public function __construct(
        public Order $order,
        public string $paymentTransactionId,
    ) {}
}
```

---

## Spatie Laravel Data Paketi

[spatie/laravel-data](https://github.com/spatie/laravel-data) paketi DTO yaratmağı çox asanlaşdırır.

*[spatie/laravel-data](https://github.com/spatie/laravel-data) paketi D üçün kod nümunəsi:*
```bash
composer require spatie/laravel-data
```

*composer require spatie/laravel-data üçün kod nümunəsi:*
```php
// Bu kod Spatie Laravel Data paketi ilə DTO yaratmağı göstərir
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;

// Əsas istifadə
class UserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        #[Validation\Min(8)]
        public string $password,
        public UserRole $role = UserRole::Viewer,
        public ?string $phone = null,
    ) {}
}

// Avtomatik olaraq Request-dən yaradıla bilər
// Route::post('/users', function (UserData $data) { ... });

// Array-dən yaratma
$user = UserData::from([
    'name' => 'Orxan',
    'email' => 'orxan@example.com',
    'password' => 'secret123',
]);

// Model-dən yaratma
$user = UserData::from(User::find(1));

// Validation ilə birlikdə
class CreateProductData extends Data
{
    public function __construct(
        #[Validation\Required, Validation\StringType, Validation\Max(255)]
        public string $name,

        #[Validation\Required, Validation\StringType]
        public string $description,

        #[Validation\Required, Validation\Numeric, Validation\Min(0)]
        public float $price,

        #[Validation\Required, Validation\IntegerType, Validation\Min(0)]
        public int $stock,

        #[Validation\Nullable, Validation\StringType]
        public ?string $sku = null,

        #[Validation\Nullable, Validation\IntegerType, Validation\Exists('categories', 'id')]
        public ?int $categoryId = null,

        /** @var string[] */
        #[Validation\Nullable]
        public ?array $tags = null,
    ) {}
}

// Nested Data
class OrderData extends Data
{
    public function __construct(
        /** @var OrderItemData[] */
        public array $items,
        public AddressData $shippingAddress,
        public PaymentData $payment,
        public ?string $couponCode = null,
    ) {}
}

class OrderItemData extends Data
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {}
}

class AddressData extends Data
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        #[MapInputName('zip_code')]
        public string $zipCode,
        public string $country,
        public ?string $apartment = null,
    ) {}
}

class PaymentData extends Data
{
    public function __construct(
        public PaymentMethod $method,
        public ?string $cardToken = null,
    ) {}
}

// Collection
class ProductData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public ?CategoryData $category = null,
    ) {}
}

// Avtomatik collection yaratma
$products = ProductData::collect(Product::all());

// Lazy properties - yalnız lazım olanda yüklənir
class UserProfileData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public Lazy|array $orders,      // Yalnız istəniləndə yüklənir
        public Lazy|array $addresses,   // Yalnız istəniləndə yüklənir
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            name: $user->name,
            email: $user->email,
            orders: Lazy::create(fn () => OrderData::collect($user->orders)),
            addresses: Lazy::create(fn () => AddressData::collect($user->addresses)),
        );
    }
}

// Controller-da
public function show(User $user): UserProfileData
{
    return UserProfileData::fromModel($user)
        ->include('orders');  // Yalnız orders yüklənir, addresses yox
}
```

---

## Real-World Nümunələr

### 1. UserDTO - Tam nümunə

*1. UserDTO - Tam nümunə üçün kod nümunəsi:*
```php
// app/DTOs/User/UserDTO.php
readonly class UserDTO implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public UserRole $role,
        public ?string $phone,
        public ?string $avatarUrl,
        public bool $isActive,
        public bool $isEmailVerified,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastLoginAt,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            phone: $user->phone,
            avatarUrl: $user->avatar_url,
            isActive: $user->is_active,
            isEmailVerified: $user->email_verified_at !== null,
            createdAt: new DateTimeImmutable($user->created_at),
            lastLoginAt: $user->last_login_at
                ? new DateTimeImmutable($user->last_login_at)
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl,
            'is_active' => $this->isActive,
            'is_email_verified' => $this->isEmailVerified,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
        ];
    }
}
```

### 2. PaymentDTO - Ödəniş prosesi

*2. PaymentDTO - Ödəniş prosesi üçün kod nümunəsi:*
```php
// app/DTOs/Payment/ProcessPaymentDTO.php
readonly class ProcessPaymentDTO
{
    public function __construct(
        public int $orderId,
        public Money $amount,
        public PaymentMethod $method,
        public ?string $cardToken = null,
        public ?string $cardLast4 = null,
        public ?string $bankAccountIban = null,
        public ?string $returnUrl = null,
        public array $metadata = [],
    ) {
        $this->validatePaymentMethod();
    }

    private function validatePaymentMethod(): void
    {
        match($this->method) {
            PaymentMethod::Card => $this->cardToken
                ?? throw new InvalidArgumentException('Kart ödənişi üçün card_token tələb olunur.'),
            PaymentMethod::BankTransfer => $this->bankAccountIban
                ?? throw new InvalidArgumentException('Bank transferi üçün IBAN tələb olunur.'),
            PaymentMethod::Cash => null, // Əlavə məlumat tələb olunmur
        };
    }

    public static function fromRequest(ProcessPaymentRequest $request, Order $order): self
    {
        return new self(
            orderId: $order->id,
            amount: $order->total,
            method: PaymentMethod::from($request->validated('payment_method')),
            cardToken: $request->validated('card_token'),
            cardLast4: $request->validated('card_last4'),
            bankAccountIban: $request->validated('bank_account_iban'),
            returnUrl: $request->validated('return_url'),
            metadata: [
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );
    }
}

// app/DTOs/Payment/PaymentResultDTO.php
readonly class PaymentResultDTO
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public ?string $message = null,
        public PaymentStatus $status = PaymentStatus::Pending,
        public ?string $redirectUrl = null,
        public array $rawResponse = [],
    ) {}

    public static function successful(string $transactionId, array $rawResponse = []): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            message: 'Ödəniş uğurla həyata keçirildi.',
            status: PaymentStatus::Paid,
            rawResponse: $rawResponse,
        );
    }

    public static function failed(string $message, array $rawResponse = []): self
    {
        return new self(
            success: false,
            message: $message,
            status: PaymentStatus::Failed,
            rawResponse: $rawResponse,
        );
    }

    public static function requiresRedirect(string $redirectUrl, string $transactionId): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            message: 'Yönləndirmə tələb olunur.',
            status: PaymentStatus::Pending,
            redirectUrl: $redirectUrl,
        );
    }
}
```

### 3. Report DTO - Hesabat

*3. Report DTO - Hesabat üçün kod nümunəsi:*
```php
// app/DTOs/Report/SalesReportDTO.php
readonly class SalesReportDTO implements JsonSerializable
{
    /**
     * @param SalesReportItemDTO[] $items
     */
    public function __construct(
        public DateRange $period,
        public Money $totalRevenue,
        public Money $totalTax,
        public Money $totalDiscount,
        public Money $netRevenue,
        public int $totalOrders,
        public int $totalItemsSold,
        public float $averageOrderValue,
        public array $items,
        public DateTimeImmutable $generatedAt,
    ) {}

    public static function generate(DateRange $period, Collection $orders): self
    {
        $totalRevenue = Money::zero(Currency::AZN());
        $totalTax = Money::zero(Currency::AZN());
        $totalDiscount = Money::zero(Currency::AZN());
        $totalItemsSold = 0;

        $items = [];

        foreach ($orders as $order) {
            $totalRevenue = $totalRevenue->add($order->subtotal);
            $totalTax = $totalTax->add($order->tax_amount);
            $totalDiscount = $totalDiscount->add($order->discount_amount ?? Money::zero(Currency::AZN()));
            $totalItemsSold += $order->items->sum('quantity');

            $items[] = SalesReportItemDTO::fromOrder($order);
        }

        $netRevenue = $totalRevenue->subtract($totalDiscount);
        $orderCount = $orders->count();
        $averageOrderValue = $orderCount > 0
            ? $totalRevenue->toFloat() / $orderCount
            : 0;

        return new self(
            period: $period,
            totalRevenue: $totalRevenue,
            totalTax: $totalTax,
            totalDiscount: $totalDiscount,
            netRevenue: $netRevenue,
            totalOrders: $orderCount,
            totalItemsSold: $totalItemsSold,
            averageOrderValue: round($averageOrderValue, 2),
            items: $items,
            generatedAt: new DateTimeImmutable(),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'period' => [
                'start' => $this->period->start->format('Y-m-d'),
                'end' => $this->period->end->format('Y-m-d'),
                'days' => $this->period->lengthInDays(),
            ],
            'summary' => [
                'total_revenue' => $this->totalRevenue->format(),
                'total_tax' => $this->totalTax->format(),
                'total_discount' => $this->totalDiscount->format(),
                'net_revenue' => $this->netRevenue->format(),
                'total_orders' => $this->totalOrders,
                'total_items_sold' => $this->totalItemsSold,
                'average_order_value' => $this->averageOrderValue,
            ],
            'items' => $this->items,
            'generated_at' => $this->generatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
```

---

## İntervyu Sualları

### 1. DTO nədir? Niyə array əvəzinə DTO istifadə etməli?
**Cavab**: DTO (Data Transfer Object) layerlər arasında data daşımaq üçün sadə obyektdir. Array-dən üstünlükləri: type safety (IDE autocomplete, refactoring), validation imkanı, self-documenting code, typo-dan qorunma. `$data['emial']` kimi typo-lar DTO-da mümkün deyil.

### 2. DTO və Value Object arasında fərq nədir?
**Cavab**: DTO sadəcə data daşıyır, business logic yoxdur. Value Object domain konseptini təmsil edir və business logic ehtiva edir (məsələn, Money-nin add metodu). DTO mutable ola bilər, VO həmişə immutable-dır. DTO identity-ə sahib deyil, VO da identity-ə sahib deyil amma value equality var.

### 3. Laravel-də DTO-nu necə yaradırsınız?
**Cavab**: PHP 8+ readonly class ilə manual yaradırıq. Constructor promotion istifadə edirik. `fromRequest()`, `fromArray()`, `fromModel()` static factory method-ları əlavə edirik. Alternativ olaraq spatie/laravel-data paketi istifadə edilə bilər.

### 4. Nested DTO nədir?
**Cavab**: DTO-nun içində başqa DTO-ların olmasıdır. Məsələn, CreateOrderDTO-nun içində AddressDTO, PaymentDTO, OrderItemDTO ola bilər. Bu, mürəkkəb data strukturlarını type-safe şəkildə təmsil etməyə imkan verir.

### 5. Update əməliyyatında DTO-da optional field-ləri necə idarə edirsiniz?
**Cavab**: Bəzi yanaşmalar var: 1) `hasField()` metodu ilə hansı field-lərin göndərildiyini izləmək, 2) Nullable property-lər istifadə etmək (amma null = "dəyişmə" ilə null = "silin" fərqini itirirsiniz), 3) Ayrıca UpdateDTO class-ı yaratmaq. Ən yaxşı yanaşma dirty fields array saxlamaqdır.

### 6. DTO-da validation harada olmalıdır?
**Cavab**: İki yerdə: 1) HTTP validation Form Request-də (format, required, exists), 2) Business validation DTO constructor-da və ya ayrıca Validator class-da (business qaydaları, cross-field validation). Form Request user input-u, DTO/Validator business rule-ları yoxlayır.

### 7. DTO Collection nədir? Nə vaxt lazımdır?
**Cavab**: Tipli DTO array-idir. Countable, IteratorAggregate implement edir. Üstünlükləri: tip təhlükəsizliyi (yalnız müəyyən DTO qəbul edir), aggregate əməliyyatlar (totalAmount, totalQuantity), business validation (minimum 1 element). Kompleks domain logic üçün lazımdır.

### 8. Spatie Laravel Data paketinin üstünlükləri nədir?
**Cavab**: Avtomatik Request-dən DTO yaratma, built-in validation attributes, nested data dəstəyi, lazy properties, avtomatik serialization, TypeScript transformer. Boilerplate kodu çox azaldır. Amma dependency əlavə edir və öyrənmə əyrisi var.

### 9. PHP 8.2 `readonly class` DTO üçün hansı üstünlükləri verir?
**Cavab**: `readonly class` ilə bütün constructor property-lər avtomatik readonly olur — hər birini ayrıca yazmağa ehtiyac yoxdur. DTO üçün ideal: yaradıldıqdan sonra dəyişdirilə bilməz, immutability default-dur. PHP 8.1 `readonly` property-lərdən fərqi: class-level annotation bütün property-lərə tətbiq olunur. Qeyd: partial update DTO-larında "dirty tracking" `readonly class` ilə işləməz — bu hallar üçün plain readonly property-lər daha çevikdir.

### 10. DTO-dan DTO-ya mapping (dönüşüm) necə effektiv aparılır?
**Cavab**: Böyük layihələrdə bir DTO-nun digərinə çevrilməsi tez-tez lazım olur (məs: Command DTO → Domain DTO → Response DTO). Yanaşmalar: (1) Manual `fromOtherDto()` static factory method — açıq, amma boilerplate. (2) `spatie/laravel-data` - `->into(OtherDto::class)` ilə avtomatik mapping. (3) AutoMapper-ə bənzər reflection-əsaslı librarialar (`mark-gerarts/laravel-auto-mapper`). Ən yaxşı yanaşma: kiçik layihədə manual, böyük layihədə spatie/laravel-data paketi.

---

## Anti-Pattern Nə Zaman Olur?

**1. Anemic DTO-nu service kimi istifadə etmək**
DTO-ya business logic, external call, ya da side-effect əlavə etmək — DTO artıq service olur, amma adı DTO qalır. Başqa developer `fromRequest()` çağıranda DB query başladığını gözləmir. DTO-lar sadəcə data container olmalıdır: constructor, factory method-lar, `toArray()` — bundan artıq deyil.

```php
// YANLIŞ — DTO içərisində business logic
readonly class CreateOrderDTO
{
    public function __construct(public int $userId, public array $items) {}

    public function validate(): void
    {
        // DB query DTO-da? Xeyir!
        foreach ($this->items as $item) {
            $product = Product::find($item['product_id']); // side-effect!
            if ($product->stock < $item['quantity']) {
                throw new Exception('Stock yetərli deyil');
            }
        }
    }
}

// DOĞRU — validation service-də
class CreateOrderDTOValidator
{
    public function validate(CreateOrderDTO $dto): void
    {
        foreach ($dto->items as $item) {
            $product = $this->productRepo->find($item->productId);
            // ...
        }
    }
}
```

**2. 50+ nullable field ilə Mega DTO**
Bir DTO-ya hər şeyi yığmaq — `CreateOrUpdateUserDTO` kimi universal DTO-lar yarat, 30+ optional field əlavə et. Nəticə: hansı field-lərin göndərildiyini anlamaq üçün caller koduna baxmaq lazımdır, IDE heç bir məna vermir. Fərqli use-case üçün fərqli DTO yaz: `CreateUserDTO`, `UpdateUserDTO`, `UpdateUserPasswordDTO`.

```php
// YANLIŞ — mega DTO
readonly class UserDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?string $newPassword = null,
        public ?string $phone = null,
        public ?UserRole $role = null,
        public ?bool $isActive = null,
        public ?string $avatarUrl = null,
        public ?string $resetToken = null,
        // 40+ field daha...
    ) {}
}

// DOĞRU — use-case üçün ayrı DTO
readonly class CreateUserDTO { /* sadəcə create üçün lazım olan field-lər */ }
readonly class UpdateUserProfileDTO { /* sadəcə profile update field-ləri */ }
readonly class ResetPasswordDTO { /* token + newPassword */ }
```

---

## Anti-patternlər

**1. DTO Əvəzinə Array İstifadəsi**
Qatlar arasında `array` göndərmək — hansı key-lərin mövcud olduğu bilinmir, IDE avtomatik tamamlama vermir, tip xətaları runtime-a qədər aşkarlanmır. Dəqiq type-hint-li DTO class-ları yazın.

**2. DTO-ya Business Logic Yerləşdirmək**
DTO-nun içinə hesablama, qayda yoxlaması, formatlaşdırma məntiqi yazmaq — DTO data daşıyıcısıdır, logic sahibi deyil; bu, məsuliyyət qarışıqlığına gətirib çıxarır. Business logic Domain Service və ya Entity-yə aiddir, DTO yalnız strukturlaşdırılmış data saxlamalıdır.

**3. Controller-dən Birbaşa Eloquent Model Göndərmək**
Controller-dən layer-lar arasında `$user` Eloquent modelini birbaşa ötürmək — lazy loading N+1 problemləri gizlənir, domain layer infrastructure-a bağımlı olur, serialization kontrolsuz olur. DTO ilə yalnız lazımlı sahələri açıq ötürün.

**4. Form Request ilə DTO-nu Birləşdirmək**
`FormRequest` sinifini özü DTO kimi istifadə etmək — HTTP sloy infrastrukturunu domain-ə bağlayır, console command-lar və ya queue job-lardan çağıranda eyni DTO-dan istifadə edilə bilmir. Ayrıca `FormRequest` (validation) + ayrıca DTO (data transfer) yazın.

**5. Həddindən Artıq Çox DTO Yaratmaq**
Hər kiçik metod üçün ayrı DTO — kod bazası şişir, dəyişiklik prosesi mürəkkəbləşir. Oxşar data strukturları üçün eyni DTO-dan istifadə edin; yalnız həqiqətən fərqli kontekstlər üçün yeni DTO yazın.

**6. Optional Field-ləri Yanlış İdarə Etmək**
Partial update DTO-larında `null` dəyərini "dəyişmə" mənasında istifadə etmək — `null` = "sil" ilə `null` = "göndərilmədi" arasındakı fərq itirilir, yanlışlıqla data silinəbiləcək. Dirty fields izləmə yanaşması istifadə edin: hansı sahələrin göndərildiyini ayrıca qeyd edin.

---

## Əlaqəli Mövzular

- [Value Objects](../ddd/02-value-objects.md) — domain konseptini saxlayan, business logic olan immutable obyektlər
- [Repository Pattern](../laravel/01-repository-pattern.md) — DTO-nu DB-dən ayıran abstraction layer
- [Service Layer](../laravel/02-service-layer.md) — DTO qəbul edib business logic icra edən qat
- [Form Object](../laravel/11-form-object.md) — validation + DTO yaratma üçün Laravel yanaşması
- [Presenter / View Model](../laravel/12-presenter-view-model.md) — DTO-dan view layer üçün data formatlama
