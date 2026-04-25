# Adapter (Middle ⭐⭐)

## İcmal
Adapter pattern iki uyğunsuz interface arasında körpü qurur. Bir class-ın interface-ini client-in gözləntisinə uyğun başqa interface-ə çevirir. Elektrik adapterinin analoqudur: AB rozeti ilə Avropa fişini birləşdirir.

## Niyə Vacibdir
Üçüncü tərəf kitabxanalarını (Stripe SDK, PayPal SDK, Twilio) birbaşa istifadə etmək application-ı həmin kitabxanaya bağlayır (vendor lock-in). Kitabxana dəyişdikdə onlarca fayl düzəltmək lazım olur. Adapter bu asılılığı bir yerdə — adapter class-ında — mərkəzləşdirir; qalan kod öz interface-i ilə işləyir.

## Əsas Anlayışlar
- **Target interface**: Client-in gözləntisinə uyğun interface — kodun geri qalan hissəsi yalnız bunu bilir
- **Adaptee**: Uyğunlaşdırılması lazım olan mövcud class (məs: Stripe SDK)
- **Adapter**: Target interface-i implement edir, Adaptee-ni içəridə çağırır
- **Object Adapter**: Adaptee-ni injection ilə alır (PHP-də tövsiyə olunan)
- **Class Adapter**: Adaptee-dən miras alır (PHP-də nadir, çünki multiple inheritance məhduddur)
- **Fərq Adapter vs Facade**: Facade mürəkkəbliyi gizlədərək sadələşdirir; Adapter interface dəyişdirir (bir interface-i başqasına çevirir)

## Praktik Baxış
- **Real istifadə**: Payment gateway-lər (Stripe/PayPal/local bank), SMS provider-lar (Twilio/Vonage/local), storage driver-lar (S3/local/FTP), email provider-lar (Mailgun/SendGrid/Postmark)
- **Trade-off-lar**: Hər yeni Adaptee üçün yeni Adapter class lazımdır; Adapter-lər arasında performans fərqi gizlənir; Adaptee-nin bütün imkanları Target interface-də olmaya bilər (feature gap)
- **İstifadə etməmək**: Adaptee artıq Target interface-ni implement edirsə; sadəcə bir yerdə istifadə olunacaqsa (wrapper function kifayətdir); Adaptee-nin API-si çox tez-tez dəyişirsə (adapter da tez-tez dəyişəcək)
- **Common mistakes**: Adapter-ə biznes məntiqini qoymaq — Adapter yalnız çevrilmə edir, iş məntiqini Service layer-ə qoy; bir Adapter-də birdən çox Adaptee birləşdirmək (bu artıq Facade olur)

## Nümunələr

### Ümumi Nümunə
E-commerce layihəndə Stripe ilə başladın, sonra PayPal da lazım oldu. Hər ikisinin SDK-sı fərqli metodlara malikdir. `PaymentGatewayInterface` target interface yaratsan, `StripeAdapter` və `PayPalAdapter` hər birini bu interface-ə uyğunlaşdırır. Checkout kodu yalnız `PaymentGatewayInterface` ilə işləyir — hansı provider-ın istifadə edildiyi barədə heç nə bilmir.

### PHP/Laravel Nümunəsi

```php
// ===== Target Interface — application-ın öz interface-i =====
interface PaymentGatewayInterface
{
    public function charge(int $amountInCents, string $currency, string $token): PaymentResult;
    public function refund(string $transactionId, int $amountInCents): RefundResult;
    public function getTransaction(string $transactionId): TransactionDetails;
}

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
class StripePaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly \Stripe\StripeClient $stripe
    ) {}

    public function charge(int $amountInCents, string $currency, string $token): PaymentResult
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount'             => $amountInCents,
                'currency'           => strtolower($currency),
                'payment_method'     => $token,
                'confirm'            => true,
                'return_url'         => config('app.url'),
            ]);

            return new PaymentResult(
                success:       $intent->status === 'succeeded',
                transactionId: $intent->id,
                status:        $intent->status
            );
        } catch (\Stripe\Exception\CardException $e) {
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

        return new TransactionDetails(
            id:        $intent->id,
            amount:    $intent->amount,
            status:    $intent->status,
            createdAt: new \DateTimeImmutable('@' . $intent->created)
        );
    }
}


// ===== PayPal Adapter =====
class PayPalPaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly \PayPalCheckoutSdk\Core\PayPalHttpClient $client
    ) {}

    public function charge(int $amountInCents, string $currency, string $token): PaymentResult
    {
        // PayPal API çağırışları — Stripe-dan tamamilə fərqli
        $request = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($token);

        try {
            $response = $this->client->execute($request);

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
        // PayPal refund logic...
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
            $order->update(['payment_status' => 'paid', 'transaction_id' => $result->transactionId]);
        }

        return $result;
    }
}


// ===== SMS Provider Adapter nümunəsi (qısa) =====
interface SmsGatewayInterface
{
    public function send(string $to, string $message): bool;
}

class TwilioSmsAdapter implements SmsGatewayInterface
{
    public function __construct(private readonly \Twilio\Rest\Client $twilio) {}

    public function send(string $to, string $message): bool
    {
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
        $this->vonage->sms()->send(
            new \Vonage\SMS\Message\SMS($to, config('services.vonage.from'), $message)
        );
        return true;
    }
}
```

## Praktik Tapşırıqlar
1. `StorageAdapterInterface` yaz: `put(string $path, string $content): bool`, `get(string $path): string`, `delete(string $path): bool` — `LocalStorageAdapter` (PHP file_* funksiyaları) və `S3StorageAdapter` (Laravel S3 driver) implement et
2. Mövcud layihədə üçüncü tərəf SDK-sını birbaşa istifadə edən kod tap — Adapter ilə sarmal; köhnə kod sıfır dəyişiklik olmadan işləsin
3. Test-də `MockPaymentAdapter implements PaymentGatewayInterface` yaz — həmişə uğurlu nəticə qaytarır; `CheckoutService`-i bu mock ilə test et; real Stripe SDK-ya ehtiyac olmadan

## Əlaqəli Mövzular
- [02-facade.md](02-facade.md) — Facade sadələşdirir, Adapter interface çevirir
- [08-decorator.md](08-decorator.md) — Decorator funksionallıq əlavə edir, eyni interface saxlayır
- [10-strategy.md](10-strategy.md) — Adapter-lər runtime-da Strategy kimi seçilə bilər
