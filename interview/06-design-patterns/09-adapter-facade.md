# Adapter and Facade Patterns (Senior ⭐⭐⭐)

## İcmal

Adapter və Facade — hər ikisi structural pattern-dir, lakin fərqli problemləri həll edir. **Adapter**: Uyğunsuz interface-ləri uyğunlaşdırır. "Convert the interface of a class into another interface clients expect." Köhnə library-ni yeni sistemə qoşmaq, third-party API-ni öz domain interface-inə uyğunlaşdırmaq — Adapter-in işidir. **Facade**: Mürəkkəb subsystem-ə sadə interface təmin edir. "Provide a unified interface to a set of interfaces in a subsystem." Laravel-in `Cache::get()`, `Mail::send()`, `Storage::put()` — bunların hamısı Facade-dir. Interview-larda bu iki pattern birlikdə soruşulur: "Adapter nə zaman, Facade nə zaman istifadə edersiniz?"

## Niyə Vacibdir

Adapter pattern — third-party dependency-ləri domain-dən izole edir. SMS göndərən servis Twilio-dan Nexmo-ya keçsə, yalnız Adapter dəyişir — service layer toxunulmur. Facade pattern — Laravel-in driver-based sisteminin üzündür. `Cache::get()` arxasında Redis, Memcached, Database, Array driver ola bilər — siz fərqinə varmırsınız. Interviewer bu mövzuda yoxlayır: "Vendor lock-in-dən necə qorunarsınız?" "Adapter vs Wrapper fərqi nədir?" "Laravel Facade-ləri real Facade pattern-dirmi?" Bu suallar dependency management anlayışınızı ölçür.

## Əsas Anlayışlar

**Adapter pattern komponentləri:**
- **Target interface**: Client-in gözlədiyi interface
- **Adaptee**: Uyğunsuz interface olan mövcud class (adətən third-party)
- **Adapter**: Target interface-i implement edir, daxilində Adaptee-ni çağırır

**Object Adapter vs Class Adapter:**
- **Object Adapter**: Adaptee-ni composition ilə saxlayır (PHP-də daha çox)
- **Class Adapter**: Adaptee-ni extend edir (multiple inheritance dəstəkləyən dillər — Java interface + abstract class kombinasiyası)
- PHP-də Object Adapter tövsiyə olunur — loose coupling

**Two-way Adapter:**
- Hər iki tərəfin interface-ini implement edir. Nadir, lakin iki köhnə sistem arasında körpü lazım olduqda istifadə olunur

**Facade pattern komponentləri:**
- **Facade**: Sadə, yüksək səviyyəli interface
- **Subsystems**: Mürəkkəb daxili class-lar — Facade bunları orchestrate edir

**Facade vs God Object:**
- Facade: Daxili logic-i delegasiya edir subsystem-lərə. Özündə business logic yoxdur
- God Object: Hər şeyi özündə edir. Facade-dən fərqli, anti-pattern-dir

**Laravel Facade-ləri:**
- `Cache::get()` — Real GoF Facade deyil, Service Locator + Magic Method-dur
- `Illuminate\Support\Facades\Cache` → `__callStatic()` → Container-dən real object resolve → method forward edir
- Laravel bu buna özü "Facades" deyir, lakin GoF Facade pattern-dən fərqlidir
- Real GoF Facade olsaydı: `new CacheFacade($redisStore, $serializer, $keyPrefix)` — constructor dependency

**Facade + Adapter kombinasiyası:**
- Ümumi pattern: Facade öz daxilindəki Adapter-ları orchestrate edir
- `NotificationFacade::send()` → içəridə `SmsAdapter`, `EmailAdapter`, `PushAdapter`

**Anti-pattern: Adapter as façade confusion:**
- Adapter: Interface translation (köhnəni yeniyə çevirmək)
- Facade: Simplification (mürəkkəbi sadələşdirmək)
- Fərqi: Adapter uyğunsuzluq problemi həll edir. Facade complexity problemi həll edir

**Port-and-Adapter (Hexagonal Architecture):**
- Domain "Port" interface-lərini müəyyən edir
- Infrastructure "Adapter"-ları bu port-ları implement edir
- Bu Adapter pattern-in architectural tətbiqidir — Laravel-in repository pattern ilə eyni ruhda

## Praktik Baxış

**Interview-da yanaşma:**
Adapter-i third-party izolasiyası üzərindən izah edin: "Stripe SDK-nı birbaşa service-ə inject etmirəm — əvvəlcə `PaymentGatewayInterface` yaradıram, sonra `StripeAdapter` implement edirəm." Facade-i Laravel nümunəsi ilə izah edin, lakin Laravel Facade-inin GoF Facade-dən fərqini qeyd edin — bu interviewer-ı təsir edir.

**"Nə vaxt Adapter seçərdiniz?" sualına cavab:**
- Third-party library-ni interface arxasında gizlətmək lazım olduqda
- Köhnə sistemi yeni interface-ə uyğunlaşdırmaq lazım olduqda
- Vendor migration riskini azaltmaq lazım olduqda
- Test-lərdə fake/mock inject etmək lazım olduqda

**"Nə vaxt Facade seçərdiniz?" sualına cavab:**
- Subsystem-i işlətmək üçün müştəri çox şey bilməli olduqda
- Subsystem-in API-si çox geniş, lakin client yalnız az sayda metod istifadə edəndə
- Layered architecture-da — hər layer yalnız öz üstündəki layer-ın facade-ini görür

**Anti-pattern-lər:**
- Adapter-ı Adaptee-nin bütün metodlarını expose etmək üçün istifadə etmək — seçici olun
- Facade-ə business logic əlavə etmək — Facade delegasiya etməlidir
- Facade-in Facade-ini yaratmaq — nested facade-lər confusion yaradır
- Laravel Facade-ini real GoF Facade ilə qarışdırmaq

**Follow-up suallar:**
- "Adapter + Strategy necə birlikdə istifadə olunur?" → Strategy interface özü Port kimi işləyir, hər external provider Adapter kimi
- "Facade-i unit test etmək çətin olurmu?" → Facade arxasındakı subsystem-ləri mock etmək lazımdır. Laravel-də `Cache::shouldReceive()` bunun üçündür
- "Hexagonal Architecture-da Adapter-in rolu nədir?" → Domain port-larını implement edir, dış dünya ilə domain arasında körpüdür

## Nümunələr

### Tipik Interview Sualı

"You're using Stripe for payments. Marketing wants to A/B test with PayPal. In 6 months you might move entirely to PayPal. How do you architect the payment integration to avoid vendor lock-in?"

### Güclü Cavab

Bu Port-and-Adapter arxitekturasının klassik use-case-idir.

`PaymentGatewayInterface` — domain port: `charge(Money $amount, PaymentMethod $method): PaymentResult`, `refund(string $transactionId, Money $amount): RefundResult`, `getTransaction(string $id): TransactionDetails`.

`StripePaymentAdapter` — Stripe SDK-nı wrap edir. Interface-i implement edir, daxilində `\Stripe\Charge::create()` çağırır. Stripe-specific exception-larını domain exception-larına çevirir.

`PayPalPaymentAdapter` — eyni interface-i implement edir, PayPal SDK istifadə edir.

A/B test üçün: ServiceProvider-da feature flag-a görə `StripePaymentAdapter` ya da `PayPalPaymentAdapter` bind edilir.

`PaymentService` `PaymentGatewayInterface` istifadə edir — Stripe-dan xəbərsizdir. Migration zamanı yalnız Adapter dəyişir.

### Kod Nümunəsi

```php
// ===== ADAPTER PATTERN =====

// Target Interface — domain port
interface PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethod $method): PaymentResult;
    public function refund(string $transactionId, Money $amount): RefundResult;
    public function getTransaction(string $id): TransactionDetails;
}

// Adaptee — Stripe SDK (third-party, biz dəyişə bilmirik)
// \Stripe\PaymentIntentService, \Stripe\RefundService — bunlar Stripe-ın öz API-sidir

// Object Adapter — Stripe SDK-nı domain interface-inə uyğunlaşdırır
class StripePaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly \Stripe\StripeClient $stripe,
        private readonly string $currency = 'usd',
    ) {}

    public function charge(Money $amount, PaymentMethod $method): PaymentResult
    {
        try {
            $intent = $this->stripe->paymentIntents->create([
                'amount'               => $amount->toMinorUnits(), // Stripe sentdə işləyir
                'currency'             => $this->currency,
                'payment_method'       => $method->token(),
                'confirm'              => true,
                'return_url'           => config('app.url') . '/payment/return',
            ]);

            return PaymentResult::success(
                transactionId: $intent->id,
                amount: $amount,
                status: $this->mapStripeStatus($intent->status),
            );
        } catch (\Stripe\Exception\CardException $e) {
            // Stripe exception → domain exception
            throw new PaymentDeclinedException($e->getMessage(), $e->getCode());
        } catch (\Stripe\Exception\ApiException $e) {
            throw new PaymentGatewayException('Stripe API error: ' . $e->getMessage());
        }
    }

    public function refund(string $transactionId, Money $amount): RefundResult
    {
        $refund = $this->stripe->refunds->create([
            'payment_intent' => $transactionId,
            'amount'         => $amount->toMinorUnits(),
        ]);

        return RefundResult::fromStripe($refund);
    }

    public function getTransaction(string $id): TransactionDetails
    {
        $intent = $this->stripe->paymentIntents->retrieve($id);
        return TransactionDetails::fromStripe($intent);
    }

    private function mapStripeStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'succeeded'               => PaymentStatus::SUCCESS,
            'requires_payment_method' => PaymentStatus::FAILED,
            'processing'              => PaymentStatus::PENDING,
            default                   => PaymentStatus::UNKNOWN,
        };
    }
}

// PayPal Adapter — eyni interface, fərqli Adaptee
class PayPalPaymentAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private readonly PayPalHttpClient $client
    ) {}

    public function charge(Money $amount, PaymentMethod $method): PaymentResult
    {
        // PayPal-ın öz API call formatı
        $request = new OrdersCreateRequest();
        $request->body = [
            'intent'        => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $amount->currency(),
                    'value'         => $amount->toDecimal(),
                ],
            ]],
        ];

        $response = $this->client->execute($request);

        return PaymentResult::success(
            transactionId: $response->result->id,
            amount: $amount,
            status: PaymentStatus::PENDING, // PayPal capture ayrıdır
        );
    }

    public function refund(string $transactionId, Money $amount): RefundResult
    {
        // PayPal refund logic...
        $request = new CapturesRefundRequest($transactionId);
        $request->body = ['amount' => ['value' => $amount->toDecimal()]];
        $response = $this->client->execute($request);

        return RefundResult::fromPayPal($response->result);
    }

    public function getTransaction(string $id): TransactionDetails
    {
        $request = new OrdersGetRequest($id);
        $response = $this->client->execute($request);
        return TransactionDetails::fromPayPal($response->result);
    }
}

// Service — adapter-dan xəbərsiz
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway  // Interface!
    ) {}

    public function processPayment(Order $order, PaymentMethod $method): void
    {
        $result = $this->gateway->charge($order->total(), $method);

        if ($result->isFailed()) {
            throw new OrderPaymentFailedException($order->id, $result->message());
        }

        $order->markAsPaid($result->transactionId());
    }
}

// ServiceProvider — A/B test üçün
$this->app->bind(PaymentGatewayInterface::class, function ($app) {
    if (feature_flag('use_paypal_gateway')) {
        return new PayPalPaymentAdapter($app->make(PayPalHttpClient::class));
    }
    return new StripePaymentAdapter($app->make(\Stripe\StripeClient::class));
});
```

```php
// ===== FACADE PATTERN =====

// Mürəkkəb notification subsystem
class NotificationFacade
{
    public function __construct(
        private readonly SmsAdapter $sms,
        private readonly EmailAdapter $email,
        private readonly PushAdapter $push,
        private readonly UserPreferenceService $prefs,
        private readonly NotificationLogger $logger,
    ) {}

    // Sadə API — client-in subsystem-i bilməsinə ehtiyac yoxdur
    public function notifyUser(User $user, Notification $notification): void
    {
        $channels = $this->prefs->getPreferredChannels($user);

        foreach ($channels as $channel) {
            match ($channel) {
                'sms'   => $this->sms->send($user->phone, $notification->smsText()),
                'email' => $this->email->send($user->email, $notification->emailHtml()),
                'push'  => $this->push->send($user->deviceToken, $notification->pushPayload()),
            };
        }

        $this->logger->log($user->id, $notification->type(), $channels);
    }
}

// Client sadə API istifadə edir:
$facade->notifyUser($user, new OrderShippedNotification($order));
// SmsAdapter, EmailAdapter, PushAdapter, preferences — görünmür
```

## Praktik Tapşırıqlar

- `SmsGatewayInterface` yazın, `TwilioAdapter` və `NexmoAdapter` implement edin — xidmət provayderi dəyişə bilsin
- `StorageFacade` yazın — local disk, S3, FTP-ni eyni interface arxasında gizlət
- Legacy kod Adapter: Köhnə `UserDataService`-in metodlarını yeni `UserRepositoryInterface`-ə uyğunlaşdırın
- Laravel Facade sinif yazın (`getFacadeAccessor()` ilə) — real Laravel Facade-inin service locator davranışını anlayın
- A/B test framework: Feature flag-a görə iki Adapter arasında keçid

## Əlaqəli Mövzular

- [Strategy Pattern](05-strategy-pattern.md) — Strategy interface özü bir Port-dur, Adapter-lər Concrete Strategy-lər
- [Decorator Pattern](08-decorator-pattern.md) — Oxşar struktur, lakin Adapter interface çevirir, Decorator davranış əlavə edir
- [Factory Patterns](02-factory-patterns.md) — Adapter instance-larını yaratmaq üçün factory
- [Repository Pattern](07-repository-pattern.md) — Repository — Adapter pattern-in data access tətbiqi
- [Dependency Injection](11-dependency-injection.md) — Adapter-lər DI container ilə inject edilir
