# Strategy (Middle ⭐⭐)

## İcmal
Strategy pattern eyni problemi həll edən bir neçə müxtəlif alqoritmi ayrı class-lara yerləşdirir və onları bir-birinə dəyişdirilebilən (interchangeable) edir. Context class hansı alqoritmin istifadə edildiyi barədə məlumat vermir — yalnız Strategy interface-i vasitəsilə çağırır.

## Niyə Vacibdir
Ödəniş sistemi düşün: Stripe, PayPal, bank köçürməsi. Hər birinin fərqli məntiqi var. Bu fərqliyi `if/else` ilə eyni `processPayment()` metoduna qoymaq həmin metodu böyüdür, test etməyi çətinləşdirir, yeni ödəniş növü əlavə etmək mövcud kodu dəyişdirir. Strategy hər ödəniş növünü ayrı class-a qoyur — açmaq üçün genişlənir, dəyişiklik üçün qapalıdır (OCP).

## Əsas Anlayışlar
- **Strategy interface**: Bütün alqoritmlər bu interface-i implement edir — vahid method imzası
- **Concrete Strategy**: Bir alqoritmi (ödəniş üsulunu, göndərmə növünü) implement edir
- **Context**: Strategy interface-i saxlayır, `setStrategy()` ilə dəyişdirilir, `execute()` ilə Strategy-ni çağırır
- **Runtime dəyişikliyi**: `$context->setStrategy(new AnotherStrategy())` — işləyərkən alqoritm dəyişir
- **Laravel driver sistem**: `config/mail.php`-də `driver: 'smtp'`, `driver: 'mailgun'` — bu Strategy pattern-in framework-dəki nümunəsidir; hər driver ayrı Strategy

## Praktik Baxış
- **Real istifadə**: Ödəniş üsulları, göndərmə hesablayıcısı, export formatı (PDF/Excel/CSV), endirimlər (sabit məbləğ, faiz, pulsuz göndərmə), autentifikasiya metodları (JWT, session, API key)
- **Trade-off-lar**: Hər yeni alqoritm yeni class deməkdir — class sayı artır; Context-ın Strategy haqqında çox məlumat bilməsi (məs: parametrlər birindən fərqlidirsə) interface-i mürəkkəbləşdirir; sadə hallarda `if/else` daha oxunaqlı ola bilər
- **İstifadə etməmək**: Yalnız iki-üç alqoritm varsa və dəyişmiyəcəksə; alqoritm sadə bir `if` bloğundan ibarətdirsə; alqoritm context-in private data-sına çıxış tələb edirsə
- **Common mistakes**: Strategy-ni Context-dən ayırmamaq — `if ($strategy === 'stripe')` yazmaq, Strategy-dən keçmək əvəzinə; Context-ə Strategy-nin detallarını vermək (məs: `stripe_api_key` parametri — bu Context-in işi deyil)
- **Anti-Pattern Nə Zaman Olur?**: Yalnız 2 strategy var və heç vaxt genişlənməyəcək — `isPremiumUser ? new PremiumPricing() : new StandardPricing()` üçün ayrı interface, factory, context yazmaq over-engineering-dir; sadə `if/else` daha oxunaqlıdır. Digər problem: strategy-lər fərqli interface imzaları tələb edirsə — `StripeStrategy::pay(int $cents, string $methodId)` vs `BankTransferStrategy::pay(int $cents, string $iban, string $bic)` — vahid `PaymentStrategyInterface` saxlamaq süni wrapper-lara gətirib çıxarır; bu halda Strategy yox, sadə service injection daha düzgündür.

## Nümunələr

### Ümumi Nümunə
Çatdırılma dəyəri hesablayan sistemi düşün: standart (3 gün, 3 AZN), ekspress (1 gün, 10 AZN), pulsuz (sifarişin məbləği 100 AZN-dən çoxdursa). Hər biri fərqli məntiqlə `calculate()` edir. `ShippingCalculator` Context class `ShippingStrategy` interface-ni saxlayır — hansı strategiya olduğunu bilmir, sadəcə `calculate($order)` çağırır.

### PHP/Laravel Nümunəsi

```php
// ===== Strategy Interface =====
interface PaymentStrategyInterface
{
    public function pay(int $amountInCents, array $context): PaymentResult;
    public function getName(): string;
    public function isAvailableFor(User $user): bool;
}

class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly ?string $errorMessage = null
    ) {}
}


// ===== Concrete Strategies =====
class StripePaymentStrategy implements PaymentStrategyInterface
{
    public function __construct(
        private readonly \Stripe\StripeClient $stripe
    ) {}

    public function pay(int $amountInCents, array $context): PaymentResult
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount'         => $amountInCents,
                'currency'       => 'azn',
                'payment_method' => $context['payment_method_id'],
                'confirm'        => true,
                'return_url'     => config('app.url'),
            ]);

            return new PaymentResult(
                success:       $intent->status === 'succeeded',
                transactionId: $intent->id
            );
        } catch (\Stripe\Exception\CardException $e) {
            return new PaymentResult(false, '', $e->getMessage());
        }
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function isAvailableFor(User $user): bool
    {
        return true; // hamı üçün mövcud
    }
}

class PayPalPaymentStrategy implements PaymentStrategyInterface
{
    public function pay(int $amountInCents, array $context): PaymentResult
    {
        // PayPal-spesifik API çağırışı
        $orderId = $context['paypal_order_id'];
        // ... capture logic
        return new PaymentResult(true, 'PP-' . uniqid());
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function isAvailableFor(User $user): bool
    {
        return $user->country !== 'IR'; // bəzi ölkələrdə mövcud deyil
    }
}

class BankTransferPaymentStrategy implements PaymentStrategyInterface
{
    public function pay(int $amountInCents, array $context): PaymentResult
    {
        // Bank köçürməsi — manual prosess, pending state
        $reference = 'BT-' . strtoupper(uniqid());
        DB::table('bank_transfer_requests')->insert([
            'reference'   => $reference,
            'amount'      => $amountInCents,
            'user_id'     => $context['user_id'],
            'created_at'  => now(),
        ]);

        // Müştəriyə bank hesab nömrəsini email et
        Mail::to($context['email'])->send(new BankTransferInstructions($reference, $amountInCents));

        return new PaymentResult(true, $reference);
    }

    public function getName(): string
    {
        return 'bank_transfer';
    }

    public function isAvailableFor(User $user): bool
    {
        return $user->is_verified; // yalnız verify olunmuş istifadəçilər
    }
}


// ===== Context =====
class PaymentContext
{
    private PaymentStrategyInterface $strategy;

    public function __construct(PaymentStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    public function setStrategy(PaymentStrategyInterface $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function processPayment(int $amountInCents, array $context): PaymentResult
    {
        Log::info("Processing payment via {$this->strategy->getName()}", [
            'amount' => $amountInCents,
        ]);

        $result = $this->strategy->pay($amountInCents, $context);

        if (!$result->success) {
            Log::warning("Payment failed via {$this->strategy->getName()}", [
                'error' => $result->errorMessage,
            ]);
        }

        return $result;
    }
}


// ===== Strategy Factory (hansı strategy seçiləcəyini mərkəzləşdirmək) =====
class PaymentStrategyFactory
{
    /** @param PaymentStrategyInterface[] $strategies */
    public function __construct(private readonly array $strategies) {}

    public function make(string $method, User $user): PaymentStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->getName() === $method && $strategy->isAvailableFor($user)) {
                return $strategy;
            }
        }

        throw new \InvalidArgumentException("Payment method '{$method}' not available.");
    }
}


// ===== ServiceProvider =====
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentStrategyFactory::class, function ($app) {
            return new PaymentStrategyFactory([
                $app->make(StripePaymentStrategy::class),
                $app->make(PayPalPaymentStrategy::class),
                $app->make(BankTransferPaymentStrategy::class),
            ]);
        });
    }
}


// ===== Controller =====
class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentStrategyFactory $factory
    ) {}

    public function pay(PayRequest $request, Order $order): JsonResponse
    {
        $strategy = $this->factory->make($request->payment_method, $request->user());
        $context  = new PaymentContext($strategy);

        $result = $context->processPayment(
            amountInCents: $order->total_in_cents,
            context: [
                'payment_method_id' => $request->payment_method_id,
                'paypal_order_id'   => $request->paypal_order_id,
                'user_id'           => $request->user()->id,
                'email'             => $request->user()->email,
            ]
        );

        if (!$result->success) {
            return response()->json(['error' => $result->errorMessage], 422);
        }

        $order->update(['status' => 'paid', 'transaction_id' => $result->transactionId]);

        return response()->json(['transaction_id' => $result->transactionId]);
    }
}


// ===== Shipping Calculator nümunəsi (qısa) =====
interface ShippingStrategyInterface
{
    public function calculate(Order $order): int; // qəpikdə
    public function getEstimatedDays(): int;
}

class StandardShipping implements ShippingStrategyInterface
{
    public function calculate(Order $order): int { return 300; } // 3 AZN
    public function getEstimatedDays(): int { return 3; }
}

class ExpressShipping implements ShippingStrategyInterface
{
    public function calculate(Order $order): int { return 1000; } // 10 AZN
    public function getEstimatedDays(): int { return 1; }
}

class FreeShipping implements ShippingStrategyInterface
{
    public function calculate(Order $order): int { return 0; }
    public function getEstimatedDays(): int { return 5; }
}

// Laravel-də driver pattern ilə müqayisə:
// config('mail.default') = 'smtp' / 'mailgun' / 'ses' — hər driver bir Strategy
```

## Praktik Tapşırıqlar
1. `DiscountStrategyInterface` yaz: `apply(int $priceInCents, Order $order): int` — `PercentageDiscount`, `FixedAmountDiscount`, `BuyOneGetOneFree`, `LoyaltyDiscount` (user-in sifariş sayına görə) concrete strategy-ləri yaz; test et
2. Laravel driver pattern-ini analiz et: `config/filesystems.php`-də `disks` — hər disk bir Strategy. `Storage::disk('s3')->put()` çağırışını trace et: `FilesystemManager::driver()` → `createS3Driver()` → `S3Adapter`
3. Mövcud layihədə `if ($type === 'a') { ... } elseif ($type === 'b') { ... }` tipli switch/if bloğu tap — Strategy ilə refactor et; test-abilityyi müqayisə et

## Əlaqəli Mövzular
- [../creational/02-factory-method.md](../creational/02-factory-method.md) — Strategy seçimi üçün Factory istifadə olunur
- [../structural/02-adapter.md](../structural/02-adapter.md) — Adapter interface uyğunlaşdırır, Strategy alqoritm dəyişdirir
- [03-command.md](03-command.md) — Command əməliyyatı encapsulate edir, Strategy alqoritmi dəyişdirir
- [04-template-method.md](04-template-method.md) — Template Method inheritance ilə edir, Strategy composition ilə
