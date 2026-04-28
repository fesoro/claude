# Form Object (Middle ⭐⭐)

## İcmal

Form Object — HTTP input-unu validation-dan sonra strongly-typed, immutable object-ə çevirmək üçün istifadə olunan Data Transfer Object-dir. Laravel-in `FormRequest`-ini genişləndirir: `FormRequest` yalnız validation edir, Form Object isə bu validated data-nı domain object-ə, command-a, ya da DTO-ya çevirən metodlar da əlavə edir. Controller-i daha thin edir, input-output transformasiyasını mərkəzləşdirir.

## Niyə Vacibdir

`$request->validated()` array qaytarır — controller bu array-ı parse etməli, field-lərə əl atmalıdır. Bu controller-i şişirir. Form Object bu transformasiyanı öz daxilinə alır: `toCommand()`, `toDomainObject()`, `toData()` metodları ilə controller yalnız `$form->toCommand()` yazır. Input structure dəyişəndə yalnız Form Object dəyişir, controller toxunulmaz qalır.

## Əsas Anlayışlar

- **Form Object**: `FormRequest`-dən extend edir + transformation metodları əlavə edir
- **`toCommand()`**: input-u Command Bus command-ına çevirir; CQRS ilə işlədikdə
- **`toDomainObject()`**: input-u domain entity-yə (Value Object, Aggregate) çevirir
- **`toData()`**: input-u plain DTO-ya çevirir; service layer üçün
- **Immutability**: validated data dəyişdirilməməlidir; Form Object `readonly` property-lər istifadə edir
- **FormRequest vs Form Object**: FormRequest standart Laravel; Form Object FormRequest-in transformation qatı əlavə edilmiş formasıdır

## Praktik Baxış

### Real istifadə

- `RegisterUserForm` → `RegisterUserCommand` — user registration
- `CheckoutForm` → `PlaceOrderData` — e-commerce checkout
- `CreateProductForm` → `Product` value object — product yaratmaq
- `UpdateProfileForm` → `UpdateProfileData` — profil yeniləmək
- `ImportCsvForm` → `ImportConfig` — CSV import parametrləri

### Trade-off-lar

- **Müsbət**: input structure izolə olunur; controller dəyişikliyi minimaldır; transformation test edilə bilər; type-safety artır
- **Mənfi**: əlavə class — sadə CRUD üçün overkill; `FormRequest` çox hallarda kifayətdir
- **Ne zaman FormRequest kifayət edir**: input-u olduğu kimi service-ə ötürürsünüzsə; transformation olmadan `$request->validated()` işlirsə

### İstifadə etməmək

- Sadə 2-3 field-li form üçün — `FormRequest.validated()` kifayətdir
- Input birbaşa Eloquent `create($request->validated())` olduqda — Form Object dəyər yaratmır
- Prototip mərhələsindəki layihələrdə — əvvəlcə FormRequest, sonra refactor

### Common mistakes

1. Form Object-dən Eloquent model birbaşa qaytarmaq — domain/HTTP layer arasında sızma (domain leak)
2. Transformation metod-larında business logic etmək — Form Object yalnız mapping edir, qərar vermir
3. Form Object-i `new` ilə yaratmaq controller-də — `FormRequest::createFrom()` ya da route binding istifadə et
4. Validation qaydalarını Form Object ilə dublikat etmək — Form Object `FormRequest`-dən extend edir, qaydalar orada

### Anti-Pattern Nə Zaman Olur?

**Form Object birbaşa Eloquent model qaytarır:**

```php
// BAD — domain leak: HTTP layer Eloquent-i bilir; controller DB structure-a bağımlıdır
class RegisterUserForm extends FormRequest
{
    public function toUser(): User
    {
        // Eloquent model birbaşa — controller artıq User model bilir
        return new User([
            'name'     => $this->name,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
        ]);
    }
}

// Controller
$user = $form->toUser(); // Controller Eloquent User bilir — bad
$user->save();           // Persistence controller-dədir — bad
```

```php
// GOOD — Form Object DTO qaytarır; controller Eloquent bilmir
class RegisterUserForm extends FormRequest
{
    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name:         $this->validated('name'),
            email:        $this->validated('email'),
            password:     $this->validated('password'),
            referralCode: $this->validated('referral_code'),
        );
    }
}

// Controller
$user = $this->userService->register($form->toData()); // Service Eloquent bilir, controller bilmir
```

**Business logic Form Object-ə girir:**

```php
// BAD — Form Object qərar verir; bu service-in işidir
class CheckoutForm extends FormRequest
{
    public function toOrder(): array
    {
        $items = $this->validated('items');
        $total = 0;

        foreach ($items as $item) {
            $product = Product::find($item['product_id']); // DB query burada!
            if ($product->stock < $item['quantity']) {     // Business rule burada!
                throw new \Exception('Out of stock');
            }
            $total += $product->price * $item['quantity']; // Calculation burada!
        }

        return ['items' => $items, 'total' => $total]; // Business result burada!
    }
}

// GOOD — Form Object yalnız mapping edir
class CheckoutForm extends FormRequest
{
    public function toData(): PlaceOrderData
    {
        return new PlaceOrderData(
            userId:        $this->user()->id,
            items:         $this->validated('items'),
            shippingAddress: AddressData::from($this->validated('shipping')),
            paymentMethod: $this->validated('payment_method'),
        );
        // Heç bir DB query, heç bir business logic — yalnız mapping
    }
}
// Business logic → OrderService.placeOrder(PlaceOrderData)
```

## Nümunələr

### Ümumi Nümunə

Bank-ın müştərisi kredit forması doldurur. Bank işçisi (Form Object) formanın düzgün doldurulduğunu yoxlayır (validation) və standart kredit müraciəti formatına çevirir (toCommand). Kredit qərarını işçi deyil, kredit departamenti (service) verir.

### PHP/Laravel Nümunəsi

**CheckoutForm — e-commerce checkout üçün:**

```php
<?php

namespace App\Http\Requests;

use App\Data\PlaceOrderData;
use App\Data\AddressData;
use App\Application\Order\PlaceOrderCommand;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutForm extends FormRequest
{
    public function authorize(): bool
    {
        // Yalnız authenticated user checkout edə bilər
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.product_id'         => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'           => ['required', 'integer', 'min:1', 'max:100'],
            'shipping.name'              => ['required', 'string', 'max:100'],
            'shipping.street'            => ['required', 'string', 'max:255'],
            'shipping.city'              => ['required', 'string', 'max:100'],
            'shipping.postal_code'       => ['required', 'string', 'max:20'],
            'shipping.country'           => ['required', 'string', 'size:2'],
            'payment_method'             => ['required', 'in:stripe,paypal,bank_transfer'],
            'coupon_code'                => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'     => 'Səbət boşdur',
            'shipping.name.required' => 'Çatdırılma adı tələb olunur',
        ];
    }

    // Service layer üçün DTO
    public function toData(): PlaceOrderData
    {
        return new PlaceOrderData(
            userId:          $this->user()->id,
            items:           $this->validated('items'),
            shippingAddress: AddressData::from($this->validated('shipping')),
            paymentMethod:   $this->validated('payment_method'),
            couponCode:      $this->validated('coupon_code'),
        );
    }

    // CQRS Command Bus üçün Command
    public function toCommand(): PlaceOrderCommand
    {
        return new PlaceOrderCommand(
            userId:          $this->user()->id,
            items:           $this->validated('items'),
            shippingAddress: $this->validated('shipping'),
            paymentMethod:   $this->validated('payment_method'),
            couponCode:      $this->validated('coupon_code'),
        );
    }
}
```

**Controller — thin; form object transformation edir:**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutForm;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function __invoke(CheckoutForm $form): JsonResponse
    {
        // Controller validation bilmir — form edir
        // Controller transformation bilmir — form edir
        // Controller yalnız orkestrə edir
        $order = $this->orderService->placeOrder($form->toData());

        return response()->json(new OrderResource($order), 201);
    }
}
```

**PlaceOrderData — immutable DTO:**

```php
<?php

namespace App\Data;

readonly class PlaceOrderData
{
    public function __construct(
        public int         $userId,
        public array       $items,
        public AddressData $shippingAddress,
        public string      $paymentMethod,
        public ?string     $couponCode = null,
    ) {}
}

readonly class AddressData
{
    public function __construct(
        public string $name,
        public string $street,
        public string $city,
        public string $postalCode,
        public string $country,
    ) {}

    public static function from(array $data): self
    {
        return new self(
            name:       $data['name'],
            street:     $data['street'],
            city:       $data['city'],
            postalCode: $data['postal_code'],
            country:    $data['country'],
        );
    }
}
```

**RegisterUserForm — daha mürəkkəb transformasiya:**

```php
<?php

namespace App\Http\Requests;

use App\Data\RegisterUserData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserForm extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'      => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'referral_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function toData(): RegisterUserData
    {
        return new RegisterUserData(
            name:         $this->validated('name'),
            email:        $this->validated('email'),
            password:     $this->validated('password'),
            referralCode: $this->validated('referral_code'),
        );
    }
}
```

**Form Object test etmək:**

```php
<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\CheckoutForm;
use App\Data\PlaceOrderData;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CheckoutFormTest extends TestCase
{
    public function test_to_data_maps_correctly(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $form = CheckoutForm::create()->replace([
            'items'          => [['product_id' => 1, 'quantity' => 2]],
            'shipping'       => [
                'name'        => 'Ali Əliyev',
                'street'      => 'Nizami 42',
                'city'        => 'Bakı',
                'postal_code' => 'AZ1000',
                'country'     => 'AZ',
            ],
            'payment_method' => 'stripe',
        ]);

        $data = $form->toData();

        $this->assertInstanceOf(PlaceOrderData::class, $data);
        $this->assertEquals($user->id, $data->userId);
        $this->assertEquals('stripe', $data->paymentMethod);
        $this->assertEquals('Bakı', $data->shippingAddress->city);
        $this->assertNull($data->couponCode);
    }

    public function test_validation_fails_without_items(): void
    {
        $form = CheckoutForm::create()->replace([
            'payment_method' => 'stripe',
            // items yoxdur
        ]);

        $this->assertFalse($form->isValid());
        $this->assertTrue($form->validator()->fails());
    }
}
```

**FormRequest vs Form Object müqayisəsi:**

```php
// Sadə FormRequest — validation + request data access
class UpdateProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:100'],
            'bio'   => ['nullable', 'string', 'max:500'],
            'email' => ['required', 'email', 'unique:users,email,' . $this->user()->id],
        ];
    }
}

// Controller-da:
$user->update($request->validated()); // FormRequest kifayətdir
// Bu halda Form Object əlavə complexity yaradır

// Form Object — transformation lazım olduqda
class UpdateProfileForm extends FormRequest
{
    public function rules(): array { /* eyni */ }

    // Domain object-ə çevirmə lazımdırsa
    public function toProfileData(): UpdateProfileData
    {
        return new UpdateProfileData(
            name:  $this->validated('name'),
            bio:   $this->validated('bio'),
            email: new EmailAddress($this->validated('email')), // Value Object!
        );
    }
}
// Value Object, Command, ya da complex transformation lazımdırsa Form Object seçin
```

## Praktik Tapşırıqlar

1. Mövcud bir controller-da `$request->validated()` ilə işləyən kodu götürün; `Form Object` yaradın; `toData()` metodu əlavə edin; controller-ı sadələşdirin
2. `ImportCsvForm` yazın: `file` upload validation + `toConfig(): ImportConfig` metodu; `ImportConfig` delimiter, encoding, column mappings-i ehtiva etsin
3. `CreateProductForm` yazın: `toData()` → `CreateProductData`; `toCommand()` → `CreateProductCommand`; controller-da ikisini alternativ istifadə edin; fərqi müşahidə edin

## Əlaqəli Mövzular

- [10-action-class.md](10-action-class.md) — Form Object-i action-a ötürmək
- [02-service-layer.md](02-service-layer.md) — `toData()` service-ə ötürülür
- [08-command-query-bus.md](08-command-query-bus.md) — `toCommand()` command bus-a göndərilir
- [../general/01-dto.md](../general/01-dto.md) — Form Object-in qaytardığı DTO-lar
- [../ddd/02-value-objects.md](../ddd/02-value-objects.md) — Transformation zamanı Value Object yaratmaq
- [../ddd/08-domain-service-vs-app-service.md](../ddd/08-domain-service-vs-app-service.md) — Form Object application layer-ə aiddir
