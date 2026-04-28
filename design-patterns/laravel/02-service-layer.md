# Service Layer (Middle ⭐⭐)

## İcmal

Service Layer pattern business logic-i controller-lardan ayırıb ayrı service class-larında saxlayır. Controller yalnız HTTP request-i qəbul edib response qaytarmaqdan məsuldur; bütün domain logic (validation rules, business rules, transactions, side effects) service-ə aiddir. "Thin controller, fat service" prinsipi.

## Niyə Vacibdir

Böyüyən Laravel layihələrində controller-lar şişir — 300-500 sətirlik metod oxunaqlılığı sıfıra endirir. Eyni logic artıq console command-dən, job-dan, API-dən çağırılması lazım gəlir — copy-paste başlayır. Service Layer bu problemi bir mərkəzdə həll edir.

## Əsas Anlayışlar

- **Service class**: bir domain-ə aid use case-ləri toplayan sinif (`UserService`, `OrderService`, `PaymentService`)
- **Thin controller**: sadəcə HTTP → DTO çevirmə, service çağırma, response qaytarma
- **Action class**: service-in daha kiçik variantı — bir use case, bir class (`RegisterUser`, `PlaceOrder`)
- **DTO (Data Transfer Object)**: controller-dan service-ə structured data ötürmək üçün immutable object
- **God Service anti-pattern**: yüzlərlə metodla şişmiş service — bölmək lazımdır
- **Service coupling**: bir service başqa service-i inject edir — mümkün qədər azaltmaq lazımdır

## Praktik Baxış

- **Real istifadə**: user registration (email check + create + welcome mail + log), order placement (inventory check + payment + shipment scheduling + notification), subscription management
- **Trade-off-lar**: service-lər böyüyür (God Service problemi); çox service → chain calls → implicit coupling; unit test üçün hər dependency mock-lanmalıdır
- **İstifadə etməmək**: çox sadə CRUD operasiyalarda service əlavə layer-dir; bir yerdən çağırılacaq single-use logic üçün overkill ola bilər

- **Common mistakes**:
  1. Service-ə `Request` və ya `Response` object ötürmək — service HTTP-dən bilməməlidir
  2. Service-dən başqa service-ə method chain call — event/command ilə əvəz et
  3. God Service (100+ metod) — domain-ə görə bölmək lazımdır
  4. `static` method-lar — test etmək mümkün olmur, dependency injection pozulur

### Anti-Pattern Nə Zaman Olur?

**God Service** — hər şey bir yerdə:
```php
// BAD — UserService 150+ metod, hər şeyi edir
class UserService
{
    public function register() { ... }
    public function login() { ... }
    public function resetPassword() { ... }
    public function uploadAvatar() { ... }
    public function subscribe() { ... }
    public function generateInvoice() { ... } // subscription işidir!
    public function sendNotification() { ... } // notification işidir!
    // davam edir — sonu yoxdur
}
```

**Service chain** — service-dən service çağırmaq:
```php
// BAD — UserService → SubscriptionService → BillingService → NotificationService
// Bu chain-i test etmək, debug etmək, dəyişmək çox çətindir
class UserService
{
    public function register(RegisterUserData $data): User
    {
        $user = $this->createUser($data);
        // Bunlar service-in öz işi deyil — event-lə decoupled olmalıdır
        $this->subscriptionService->createFree($user);  // BAD
        $this->billingService->setupProfile($user);      // BAD
        $this->notificationService->sendWelcome($user);  // BAD
        return $user;
    }
}

// GOOD — event fire et, hər service öz işini özü eşidir
class UserService
{
    public function register(RegisterUserData $data): User
    {
        $user = $this->createUser($data);
        // Event fire — service-lər arasında coupling yoxdur
        $this->events->dispatch(new UserRegistered($user));
        return $user;
    }
}
```

## Nümunələr

### Ümumi Nümunə

İstifadəçi qeydiyyatı düşünün. HTTP endpoint, Artisan command və ya 3rd party OAuth — üçündən eyni `register()` logic çağırıla bilməlidir. Service Layer bu logic-i bir yerdə saxladığı üçün hər entry point sadəcə service-i çağırır, logic-i duplicate etmir.

### PHP/Laravel Nümunəsi

**DTO — HTTP-dən service-ə məlumat ötürmək:**

```php
<?php

// Immutable DTO — service HTTP haqqında bilmir; sadəcə data alır
readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $referralCode = null,
    ) {}

    // fromRequest() — DTO-nu HTTP-dən kəsmək üçün
    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name:         $request->input('name'),
            email:        $request->input('email'),
            password:     $request->input('password'),
            referralCode: $request->input('referral_code'),
        );
    }
}
```

**Service class — business logic:**

```php
class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SubscriptionService     $subscriptions,
        private readonly EventDispatcher         $events,
    ) {}

    public function register(RegisterUserData $data): User
    {
        // Business rule 1: email unique olmalıdır
        if ($this->users->existsByEmail($data->email)) {
            throw new EmailAlreadyTakenException($data->email);
        }

        // Business rule 2: transaction içində bütün dəyişikliklər atomik
        return DB::transaction(function () use ($data): User {
            $user = $this->users->save(new User([
                'name'     => $data->name,
                'email'    => $data->email,
                // Hash burada — controller bilmir password-u necə hash edirik
                'password' => Hash::make($data->password),
            ]));

            // Business side effect: referral bonus
            if ($data->referralCode) {
                $this->handleReferral($user, $data->referralCode);
            }

            // Event fire — listener-lər halda edir; service-lər bir-birini çağırmır
            $this->events->dispatch(new UserRegistered($user));

            return $user;
        });
    }

    public function deactivate(User $user, string $reason): void
    {
        if (!$user->is_active) {
            // Business rule: artıq deaktiv user-i yenidən deaktiv etmə
            throw new UserAlreadyInactiveException();
        }

        DB::transaction(function () use ($user, $reason): void {
            $user->is_active = false;
            $user->deactivated_at = now();
            $this->users->save($user);

            // Side effect — event ilə; service direkt email göndərmir
            $this->events->dispatch(new UserDeactivated($user, $reason));
        });
    }

    private function handleReferral(User $newUser, string $code): void
    {
        $referrer = $this->users->findByReferralCode($code);
        if ($referrer) {
            // Başqa service çağırmaq — acceptable, amma event daha yaxşı olardı
            $this->subscriptions->grantTrialExtension($referrer, days: 14);
        }
    }
}
```

**Thin controller — sadəcə HTTP layer:**

```php
class AuthController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        // 1. HTTP → DTO
        $data = RegisterUserData::fromRequest($request);

        // 2. Service-i çağır — business logic burada deyil
        $user = $this->userService->register($data);

        // 3. Response — sadəcə format
        return response()->json(
            new UserResource($user),
            Response::HTTP_CREATED
        );
    }
}

// Artisan Command-dan eyni service çağırıla bilir — code duplication yoxdur
class DeactivateInactiveUsersCommand extends Command
{
    protected $signature = 'users:deactivate-inactive {--days=90}';

    public function handle(UserService $userService): int
    {
        $cutoff = now()->subDays($this->option('days'));

        User::where('last_login_at', '<', $cutoff)
            ->where('is_active', true)
            ->chunk(100, function ($users) use ($userService): void {
                foreach ($users as $user) {
                    // Controller-daki kodu copy-paste etmirik — service istifadə edirik
                    $userService->deactivate($user, 'inactive_for_90_days');
                }
            });

        return Command::SUCCESS;
    }
}
```

**Action classes — service-in alternativ yanaşması (daha kiçik granularity):**

```php
// Action: bir use case, bir class — God Service problemi yoxdur
class RegisterUserAction
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EventDispatcher         $events,
    ) {}

    public function execute(RegisterUserData $data): User
    {
        if ($this->users->existsByEmail($data->email)) {
            throw new EmailAlreadyTakenException($data->email);
        }

        return DB::transaction(function () use ($data): User {
            $user = $this->users->save(new User([
                'name'     => $data->name,
                'email'    => $data->email,
                'password' => Hash::make($data->password),
            ]));

            $this->events->dispatch(new UserRegistered($user));
            return $user;
        });
    }
}

// Ayrı action-lar — hər birini müstəqil test etmək, dəyişmək mümkündür
class UpdateUserProfileAction  { /* ... */ }
class DeactivateUserAction     { /* ... */ }
class SendVerificationEmailAction { /* ... */ }

// Controller — Action inject edir; həm DI, həm de thin controller
class AuthController
{
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $user = $action->execute(RegisterUserData::fromRequest($request));
        return response()->json(new UserResource($user), 201);
    }
}
```

**Service-də DB transaction doğru yeri:**

```php
class OrderService
{
    public function placeOrder(PlaceOrderData $data): Order
    {
        // Transaction service-ə aid — controller bilmir, job bilmir
        return DB::transaction(function () use ($data): Order {
            $order = Order::create([
                'user_id' => $data->userId,
                'total'   => $data->calculateTotal(),
            ]);

            foreach ($data->items as $item) {
                $order->items()->create($item);
                // Inventory azalt — eyni transaction-da
                Product::where('id', $item['product_id'])
                       ->decrement('stock', $item['quantity']);
            }

            // Payment charge et
            $this->payments->charge($order, $data->paymentMethod);

            event(new OrderPlaced($order));

            return $order;
            // Exception olsa DB::transaction avtomatik rollback edir
        });
    }
}
```

**God Service-dən qaçmaq:**

```php
// BAD — God Service: hər şey bir yerdə
class UserService
{
    public function register() { ... }
    public function login() { ... }
    public function subscribe() { ... }
    public function cancelSubscription() { ... }
    public function generateInvoice() { ... }
    // 150+ metod
}

// GOOD — domain-ə görə bölünmüş, hər biri 10-20 metod
class UserAuthService      { /* register, login, logout, resetPassword */ }
class UserProfileService   { /* updateProfile, uploadAvatar, deleteAccount */ }
class SubscriptionService  { /* subscribe, cancel, upgrade, generateInvoice */ }
class NotificationService  { /* send, markRead, getPreferences */ }
```

## Praktik Tapşırıqlar

1. Mövcud bir "fat controller" seçin, business logic-i `OrderService`-ə çıxarın, controller-ı "thin" edin; service test edin
2. `RegisterUserAction` yazın, controller-dan, console command-dan və `TestCase`-dən eyni action-ı çağırın — üçü eyni nəticəni versin
3. `OrderService.placeOrder()` üçün unit test yazın — `InMemoryOrderRepository` ilə, real DB istifadə etmədən

## Əlaqəli Mövzular

- [01-repository-pattern.md](01-repository-pattern.md) — Service-in data access layer-i
- [10-action-class.md](10-action-class.md) — Service-in daha kiçik, single-use class variantı
- [08-command-query-bus.md](08-command-query-bus.md) — Service Layer vs Command Bus: alternativ yanaşmalar
- [03-pipeline.md](03-pipeline.md) — Pipeline-based service logic
- [05-event-listener.md](05-event-listener.md) — Service-dən event fire etmək
- [../behavioral/03-command.md](../behavioral/03-command.md) — Command+Handler service layer-i tamamlayır
- [../general/01-dto.md](../general/01-dto.md) — Service-ə HTTP-dən data ötürmək
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Single Responsibility: hər service bir domain
