# Adapter (Middle ⭐⭐)

## İcmal
Adapter pattern iki uyğunsuz interface arasında körpü qurur. Bir class-ın interface-ini client-in gözləntisinə uyğun başqa interface-ə çevirir. Elektrik adapterinin analoqudur: AB rozeti ilə Avropa fişini birləşdirir — heç birini dəyişmədən.

## Niyə Vacibdir
Üçüncü tərəf kitabxanalarını (Stripe SDK, PayPal SDK, Twilio) birbaşa istifadə etmək application-ı həmin kitabxanaya bağlayır (vendor lock-in). Kitabxana dəyişdikdə onlarla fayl düzəltmək lazım olur. Adapter bu asılılığı bir yerdə — adapter class-ında — mərkəzləşdirir; qalan kod öz interface-i ilə işləyir.

## Əsas Anlayışlar
- **Target interface**: Client-in gözləntisinə uyğun interface — kodun geri qalan hissəsi yalnız bunu bilir
- **Adaptee**: Uyğunlaşdırılması lazım olan mövcud class (məs: Stripe SDK) — biz onu dəyişə bilmərik
- **Adapter**: Target interface-i implement edir, Adaptee-ni içəridə saxlayır və çağırır
- **Object Adapter**: Adaptee-ni injection ilə alır (PHP-də tövsiyə olunan üsul)
- **Class Adapter**: Adaptee-dən miras alır (PHP-də nadir, çünki multiple inheritance məhduddur)
- **Fərq Adapter vs Facade**: Facade mürəkkəbliyi gizlədərək sadələşdirir; Adapter interface dəyişdirir (bir interface-i başqasına çevirir) — məqsəd fərqlidir

## Praktik Baxış
- **Real istifadə**: Payment gateway-lər (Stripe/PayPal/local bank), SMS provider-lar (Twilio/Vonage/local), storage driver-lar (S3/local/FTP), email provider-lar (Mailgun/SendGrid/Postmark)
- **Trade-off-lar**: Hər yeni Adaptee üçün yeni Adapter class lazımdır; Adapter-lər arasında performans fərqi gizlənir; Adaptee-nin bütün imkanları Target interface-də olmaya bilər (feature gap — məs: Stripe-ın xüsusi feature-ları PayPal-da yoxdur)
- **İstifadə etməmək**: Adaptee artıq Target interface-ni implement edirsə (adapter lazımsızdır); sadəcə bir yerdə istifadə olunacaqsa (wrapper function kifayətdir); Adaptee-nin API-si çox tez-tez dəyişirsə (adapter da tez-tez dəyişəcək)
- **Common mistakes**: Adapter-ə biznes məntiqini qoymaq — Adapter yalnız çevrilmə edir, iş məntiqini Service layer-ə qoy; bir Adapter-də birdən çox Adaptee birləşdirmək (bu artıq Facade olur)

### Anti-Pattern Nə Zaman Olur?
Adapter **impedance mismatch** yaratdıqda — uyğunsuzluğu həll etmək əvəzinə dərinləşdirdikdə — anti-pattern olur:

- **Leaky abstraction**: `StripeAdapter::charge()` içindən Stripe-specific exception (`\Stripe\Exception\CardException`) xaricə sızır. Client "adapter arxasındakını" bilməməlidir — amma bilir. Target interface-in öz exception tipləri olmalıdır.
- **Feature gap-i gizlətmək**: Stripe `idempotency_key` dəstəkləyir, PayPal dəstəkləmir. Interface-dən çıxarırsınız — amma kritik feature itirilir. Bu uyğunsuzluq Adapter-i qoymaqla həll olmur.
- **Adapter içinə iş məntiqini doldurmaq**: `StripeAdapter::charge()` içindən email göndərmək, DB-yə yazmaq, event fire etmək — bu artıq Adapter deyil, Service-dir.
- **Çox dərindən uyğunlaşdırmaq**: Adaptee-nin data model-i ilə Target interface-in data model-i tamamilə fərqlidirsə, Adapter-in özü 300 sətir olur. Bu vəziyyətdə Anti-Corruption Layer (DDD termini) düşünmək lazımdır.

## Nümunələr

### Ümumi Nümunə
E-commerce layihəndə Stripe ilə başladın, sonra PayPal da lazım oldu. Hər ikisinin SDK-sı fərqli metodlara malikdir. `PaymentGatewayInterface` target interface yaratsan, `StripeAdapter` və `PayPalAdapter` hər birini bu interface-ə uyğunlaşdırır. Checkout kodu yalnız `PaymentGatewayInterface` ilə işləyir — hansı provider-ın istifadə edildiyi barədə heç nə bilmir.

### PHP/Laravel Nümunəsi

```php
// ===== Target Interface — application-ın öz interface-i =====
// Bu interface provider-dan asılı deyil; biz nəzarət edirik
interface PaymentGatewayInterface
{
    public function charge(int $amountInCents, string $currency, string $token): PaymentResult;
    public function refund(string $transactionId, int $amountInCents): RefundResult;
    public function getTransaction(string $transactionId): TransactionDetails;
}

// Interface-ə xas tipli nəticə class-ları — provider-dan asılı deyil
class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $transactionId,
        public readonly string $status,
        public readonly ?string $errorMessage = null
    ) {}
}

class RefundResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $refundId,
        public readonly ?string $errorMessage = null
    ) {}
}

class TransactionDetails
{
    public function __construct(
        public readonly string $id,
        public readonly int $amount,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt
    ) {}
}


// ===== Stripe Adapter =====
// StripeClient-in özünü dəyişmirik — sadəcə "çeviririk"
class StripePaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly \Stripe\StripeClient $stripe  // Adaptee — injection ilə alınır
    ) {}

    public function charge(int $amountInCents, string $currency, string $token): PaymentResult
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount'         => $amountInCents,       // Stripe cents qəbul edir
                'currency'       => strtolower($currency), // Stripe lowercase tələb edir
                'payment_method' => $token,
                'confirm'        => true,
                'return_url'     => config('app.url'),
            ]);

            // Stripe-ın 'succeeded' → bizim bool success-ə çevrilir
            return new PaymentResult(
                success:       $intent->status === 'succeeded',
                transactionId: $intent->id,
                status:        $intent->status
            );
        } catch (\Stripe\Exception\CardException $e) {
            // Stripe exception → bizim domain exception-a çevrilir, sızmır
            return new PaymentResult(
                success:       false,
                transactionId: '',
                status:        'failed',
                errorMessage:  $e->getMessage()
            );
        }
    }

    public function refund(string $transactionId, int $amountInCents): RefundResult
    {
        try {
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $transactionId,
                'amount'         => $amountInCents,
            ]);

            return new RefundResult(success: true, refundId: $refund->id);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return new RefundResult(
                success:      false,
                refundId:     '',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function getTransaction(string $transactionId): TransactionDetails
    {
        $intent = $this->stripe->paymentIntents->retrieve($transactionId);

        // Stripe Unix timestamp → PHP DateTimeImmutable
        return new TransactionDetails(
            id:        $intent->id,
            amount:    $intent->amount,
            status:    $intent->status,
            createdAt: new \DateTimeImmutable('@' . $intent->created)
        );
    }
}


// ===== PayPal Adapter =====
// PayPal API Stripe-dan tamamilə fərqlidir — Adapter bunu gizlədır
class PayPalPaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly \PayPalCheckoutSdk\Core\PayPalHttpClient $client  // Adaptee
    ) {}

    public function charge(int $amountInCents, string $currency, string $token): PaymentResult
    {
        // PayPal: Order capture — Stripe-ın paymentIntents.create ilə uyğun deyil
        $request = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($token);

        try {
            $response = $this->client->execute($request);

            // PayPal 'COMPLETED' → bizim success-ə çevrilir
            return new PaymentResult(
                success:       $response->result->status === 'COMPLETED',
                transactionId: $response->result->id,
                status:        strtolower($response->result->status)
            );
        } catch (\PayPalHttp\HttpException $e) {
            return new PaymentResult(
                success:       false,
                transactionId: '',
                status:        'failed',
                errorMessage:  $e->getMessage()
            );
        }
    }

    public function refund(string $transactionId, int $amountInCents): RefundResult
    {
        // PayPal refund logic — Stripe-dakından tamamilə fərqli API
        return new RefundResult(success: true, refundId: 'PP-' . uniqid());
    }

    public function getTransaction(string $transactionId): TransactionDetails
    {
        // PayPal transaction details fetch...
        return new TransactionDetails(
            id:        $transactionId,
            amount:    0,
            status:    'completed',
            createdAt: new \DateTimeImmutable()
        );
    }
}


// ===== ServiceProvider-də bind et =====
// Config-ə əsasən hansı Adapter istifadə olunacağı qərarlaşdırılır
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayInterface::class, function () {
            return match (config('payment.driver')) {
                'paypal' => new PayPalPaymentAdapter(
                    $this->buildPayPalClient()
                ),
                default  => new StripePaymentAdapter(
                    new \Stripe\StripeClient(config('services.stripe.secret'))
                ),
            };
        });
    }
}


// ===== Service — yalnız interface ilə işləyir, Adapter bilmir =====
class CheckoutService
{
    public function __construct(
        // PaymentGatewayInterface inject olunur — Stripe-mı, PayPal-mı? bilmir
        private readonly PaymentGatewayInterface $gateway
    ) {}

    public function processPayment(Order $order, string $paymentToken): PaymentResult
    {
        $result = $this->gateway->charge(
            amountInCents: $order->total_in_cents,
            currency:      'AZN',
            token:         $paymentToken
        );

        if ($result->success) {
            $order->update([
                'payment_status' => 'paid',
                'transaction_id' => $result->transactionId
            ]);
        }

        return $result;
    }
}


// ===== SMS Provider Adapter nümunəsi =====
// Eyni pattern, başqa domain — Twilio vs Vonage
interface SmsGatewayInterface
{
    public function send(string $to, string $message): bool;
}

class TwilioSmsAdapter implements SmsGatewayInterface
{
    public function __construct(private readonly \Twilio\Rest\Client $twilio) {}

    public function send(string $to, string $message): bool
    {
        // Twilio-nun özünəməxsus API-si — Adapter gizlədır
        $this->twilio->messages->create($to, [
            'from' => config('services.twilio.from'),
            'body' => $message,
        ]);
        return true;
    }
}

class VonageSmsAdapter implements SmsGatewayInterface
{
    public function __construct(private readonly \Vonage\Client $vonage) {}

    public function send(string $to, string $message): bool
    {
        // Vonage API tamamilə fərqli — amma client bilmir
        $this->vonage->sms()->send(
            new \Vonage\SMS\Message\SMS($to, config('services.vonage.from'), $message)
        );
        return true;
    }
}


// ===== Test Adapter — real SDK olmadan test =====
// Bu, Adapter pattern-in testability üçün dəyərini göstərir
class MockPaymentAdapter implements PaymentGatewayInterface
{
    private array $calls = [];

    public function charge(int $amountInCents, string $currency, string $token): PaymentResult
    {
        $this->calls[] = ['method' => 'charge', 'amount' => $amountInCents];
        // Həmişə uğurlu nəticə qaytarır — real Stripe/PayPal yoxdur
        return new PaymentResult(success: true, transactionId: 'mock-txn-' . uniqid(), status: 'succeeded');
    }

    public function refund(string $transactionId, int $amountInCents): RefundResult
    {
        return new RefundResult(success: true, refundId: 'mock-refund-' . uniqid());
    }

    public function getTransaction(string $transactionId): TransactionDetails
    {
        return new TransactionDetails($transactionId, 1000, 'succeeded', new \DateTimeImmutable());
    }

    public function getCalls(): array { return $this->calls; }
}

// Test-də istifadə
class CheckoutServiceTest extends TestCase
{
    public function test_successful_payment_updates_order_status(): void
    {
        $mockGateway = new MockPaymentAdapter();
        $service = new CheckoutService($mockGateway);

        $order = Order::factory()->create(['total_in_cents' => 5000]);
        $result = $service->processPayment($order, 'test-token');

        $this->assertTrue($result->success);
        $this->assertEquals('paid', $order->fresh()->payment_status);
    }
}
```

## Praktik Tapşırıqlar
1. `StorageAdapterInterface` yaz: `put(string $path, string $content): bool`, `get(string $path): string`, `delete(string $path): bool` — `LocalStorageAdapter` (PHP file_* funksiyaları) və `S3StorageAdapter` (Laravel S3 driver) implement et. Storage driver-ı `.env`-dən seçilsin.
2. Mövcud layihədə üçüncü tərəf SDK-sını birbaşa istifadə edən kod tap — Adapter ilə sarmal; köhnə kod sıfır dəyişiklik olmadan işləsin. SDK-nın exception-larının xaricə sızmamasını yoxla.
3. Test-də `MockPaymentAdapter implements PaymentGatewayInterface` yaz — həmişə uğurlu nəticə qaytarır; `CheckoutService`-i bu mock ilə test et; real Stripe SDK-ya ehtiyac olmadan işləsin.

## Əlaqəli Mövzular
- [01-facade.md](01-facade.md) — Facade sadələşdirir, Adapter interface çevirir; məqsəd fərqlidir
- [03-decorator.md](03-decorator.md) — Decorator funksionallıq əlavə edir, eyni interface saxlayır; Adapter isə interface-i dəyişdirir
- [04-proxy.md](04-proxy.md) — Proxy eyni interface saxlayır; Adapter isə interface-i çevirir — struktur bənzər, niyyət fərqli
- [07-bridge.md](07-bridge.md) — Bridge proactive (əvvəldən planlanır), Adapter reactive (sonradan düzəltmə)
- [../behavioral/02-strategy.md](../behavioral/02-strategy.md) — Adapter-lər runtime-da Strategy kimi seçilə bilər (provider swap)
- [../laravel/01-repository-pattern.md](../laravel/01-repository-pattern.md) — Repository özü bir növ Adapter-dir (Eloquent → domain interface)
- [../laravel/06-di-vs-service-locator.md](../laravel/06-di-vs-service-locator.md) — DI container adapter-ləri avtomatik inject edir
- [../integration/08-anti-corruption-layer.md](../integration/08-anti-corruption-layer.md) — Anti-Corruption Layer daha güclü Adapter növüdür; DDD kontekstlərini ayırır
- [../architecture/05-hexagonal-architecture.md](../architecture/05-hexagonal-architecture.md) — Hexagonal arxitekturada port-adapter cütü birbaşa bu pattern-dir
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Open/Closed: yeni provider üçün mövcud kodu dəyişmədən Adapter əlavə etmək
