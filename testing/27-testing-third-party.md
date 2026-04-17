# Testing Third-Party Integrations

## Nədir? (What is it?)

Third-party testing, application-ımızın xarici servis-lərlə (Stripe, SendGrid, Twilio, AWS,
Google APIs) olan inteqrasiyalarını yoxlamaq prosesidir. Real API-lara hər test-də çağırış
etmək — slow, flaky, rate-limited və pullu olduğu üçün — test mühitində onları fake və ya
mock edirik.

Laravel `Http::fake()`, `Http::preventStrayRequests()` kimi güclü helper-lər təqdim edir.
Daha geniş scenario-lar üçün VCR (record & replay), sandbox environments və contract
testing istifadə olunur.

### Niyə Real API-ı Test-də Çağırmırıq?

1. **Speed** - Network latency test-i 10x yavaşladır
2. **Cost** - Hər SMS, hər Stripe charge pul gedir
3. **Rate limits** - CI-də bütün test işləyərsə rate limit-ə dəyə bilərik
4. **Flakiness** - Third-party xidməti down olarsa test-lər qırılır
5. **Side effects** - Real customer-ə email, real card-dan ödəniş

## Əsas Konseptlər (Key Concepts)

### Testing Yanaşmaları

| Yanaşma | Nə vaxt |
|---------|--------|
| `Http::fake()` | Sadə mock response |
| `Http::preventStrayRequests()` | Unfaked request varsa fail |
| VCR (cassettes) | Real response qeyd et, sonra replay |
| Sandbox environments | Stripe test mode, PayPal sandbox |
| Mock SDK | SDK (AWS, Stripe PHP) birbaşa mock et |
| Contract testing | Provider-consumer müqaviləsi |

### Http::fake Patterns

```php
// Bütün HTTP call-lar fake 200 qaytarır
Http::fake();

// URL pattern-ə uyğun
Http::fake([
    'api.stripe.com/*' => Http::response(['id' => 'ch_1'], 200),
    'slack.com/*'      => Http::response('', 200),
    '*'                => Http::response('Fallback', 404),
]);

// Sequence - hər call fərqli response
Http::fake([
    'api.example.com/*' => Http::sequence()
        ->push(['status' => 'pending'])
        ->push(['status' => 'completed']),
]);
```

### preventStrayRequests

```php
// Test-də unexpected HTTP call varsa exception
Http::preventStrayRequests();
Http::fake(['known-api.com/*' => Http::response(...)]);

// Call to unknown-api.com → Exception
```

## Praktiki Nümunələr (Practical Examples)

### Simple Http Mock

```php
public function test_weather_service_returns_data(): void
{
    Http::fake([
        'api.weather.com/v1/*' => Http::response([
            'temp' => 25, 'city' => 'Baku',
        ], 200),
    ]);

    $weather = (new WeatherService)->forCity('Baku');

    $this->assertSame(25, $weather->temperature);
}
```

### Asserting HTTP Calls

```php
Http::assertSent(function (Request $request) {
    return $request->url() === 'https://api.stripe.com/v1/charges'
        && $request['amount'] === 5000
        && $request->hasHeader('Authorization');
});

Http::assertNotSent(fn ($r) => str_contains($r->url(), 'twilio'));
Http::assertSentCount(2);
Http::assertNothingSent();
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### 1. Stripe-like Payment Integration

```php
// app/Services/StripeService.php
class StripeService
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://api.stripe.com/v1',
    ) {}

    public function charge(int $amountCents, string $token): array
    {
        $response = Http::withToken($this->apiKey)
            ->asForm()
            ->post("{$this->baseUrl}/charges", [
                'amount'   => $amountCents,
                'currency' => 'usd',
                'source'   => $token,
            ]);

        if ($response->failed()) {
            throw new PaymentFailedException($response->json('error.message'));
        }

        return $response->json();
    }
}

// tests/Unit/Services/StripeServiceTest.php
namespace Tests\Unit\Services;

use App\Exceptions\PaymentFailedException;
use App\Services\StripeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeServiceTest extends TestCase
{
    public function test_successful_charge_returns_response(): void
    {
        Http::fake([
            'api.stripe.com/v1/charges' => Http::response([
                'id'     => 'ch_123',
                'amount' => 5000,
                'status' => 'succeeded',
            ], 200),
        ]);

        $service = new StripeService('sk_test_fake');
        $result  = $service->charge(5000, 'tok_visa');

        $this->assertSame('ch_123', $result['id']);
        $this->assertSame('succeeded', $result['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.stripe.com/v1/charges'
                && $request->hasHeader('Authorization', 'Bearer sk_test_fake')
                && $request['amount'] === 5000
                && $request['currency'] === 'usd';
        });
    }

    public function test_failed_charge_throws_exception(): void
    {
        Http::fake([
            'api.stripe.com/v1/charges' => Http::response([
                'error' => ['message' => 'Your card was declined.'],
            ], 402),
        ]);

        $this->expectException(PaymentFailedException::class);
        $this->expectExceptionMessage('Your card was declined.');

        (new StripeService('sk_test'))->charge(1000, 'tok_bad');
    }

    public function test_prevents_unexpected_requests(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.stripe.com/v1/charges' => Http::response(['id' => 'ch_1']),
        ]);

        (new StripeService('sk'))->charge(100, 'tok');

        // If code also called api.twilio.com etc., exception thrown
        Http::assertSentCount(1);
    }
}
```

### 2. HTTP Sequence - Retry / Polling

```php
// app/Services/JobPoller.php
class JobPoller
{
    public function waitForCompletion(string $jobId): array
    {
        for ($i = 0; $i < 10; $i++) {
            $response = Http::get("https://api.example.com/jobs/{$jobId}");
            $status   = $response->json('status');

            if ($status === 'completed') {
                return $response->json();
            }

            if ($status === 'failed') {
                throw new JobFailedException;
            }

            sleep(1);
        }

        throw new TimeoutException;
    }
}

// Test
public function test_poller_waits_until_completion(): void
{
    Http::fake([
        'api.example.com/jobs/*' => Http::sequence()
            ->push(['status' => 'pending'])
            ->push(['status' => 'processing'])
            ->push(['status' => 'completed', 'result' => 42]),
    ]);

    $result = (new JobPoller)->waitForCompletion('job_1');

    $this->assertSame(42, $result['result']);
    Http::assertSentCount(3);
}
```

### 3. Webhook Handler Test

```php
// app/Http/Controllers/WebhookController.php
public function stripe(Request $request)
{
    $signature = $request->header('Stripe-Signature');

    if (! $this->verifySignature($request->getContent(), $signature)) {
        abort(403);
    }

    $event = $request->json()->all();

    if ($event['type'] === 'charge.succeeded') {
        Order::where('stripe_id', $event['data']['object']['id'])
            ->update(['status' => 'paid']);
    }

    return response()->json(['received' => true]);
}

// tests/Feature/StripeWebhookTest.php
public function test_webhook_updates_order_on_charge_succeeded(): void
{
    $order = Order::factory()->create([
        'stripe_id' => 'ch_123',
        'status'    => 'pending',
    ]);

    $payload = json_encode([
        'type' => 'charge.succeeded',
        'data' => ['object' => ['id' => 'ch_123']],
    ]);

    $signature = $this->generateValidStripeSignature($payload);

    $this->withHeader('Stripe-Signature', $signature)
        ->postJson('/webhooks/stripe', json_decode($payload, true))
        ->assertOk();

    $this->assertSame('paid', $order->fresh()->status);
}

public function test_invalid_signature_is_rejected(): void
{
    $this->withHeader('Stripe-Signature', 'invalid')
        ->postJson('/webhooks/stripe', ['type' => 'charge.succeeded'])
        ->assertForbidden();
}
```

### 4. Mocking SDK Directly (Stripe PHP SDK)

```php
// Controller uses Stripe\Charge SDK
public function charge(Request $request)
{
    $charge = \Stripe\Charge::create([
        'amount' => 1000, 'currency' => 'usd', 'source' => $request->token,
    ]);

    return response()->json($charge->toArray());
}

// Test with Mockery
public function test_charge_endpoint_creates_stripe_charge(): void
{
    $mock = $this->mock('alias:Stripe\Charge');
    $mock->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg['amount'] === 1000))
        ->andReturn((object) ['id' => 'ch_1', 'toArray' => fn () => ['id' => 'ch_1']]);

    $this->postJson('/api/charge', ['token' => 'tok_visa'])
        ->assertOk()
        ->assertJson(['id' => 'ch_1']);
}
```

### 5. VCR (Record & Replay) Pattern

```php
// php-vcr/php-vcr package
use VCR\VCR;

public function test_github_user_fetched_correctly(): void
{
    VCR::turnOn();
    VCR::insertCassette('github-user-orkhan.yml');

    $user = (new GithubClient)->getUser('orkhan');

    $this->assertSame('Orkhan', $user->name);

    VCR::eject();
    VCR::turnOff();
}

// First run: real HTTP call saved to YAML
// Subsequent runs: response replayed from file
// Cassette location: tests/fixtures/cassettes/github-user-orkhan.yml
```

### 6. AWS S3 / SES via Mocking

```php
// Config uses aws/aws-sdk-php
public function test_ses_email_sent(): void
{
    $mock = Mockery::mock(SesClient::class);
    $mock->shouldReceive('sendEmail')
        ->once()
        ->with(Mockery::on(function ($args) {
            return $args['Destination']['ToAddresses'] === ['u@example.com'];
        }))
        ->andReturn(new Result(['MessageId' => 'msg-1']));

    app()->instance(SesClient::class, $mock);

    (new EmailService)->send('u@example.com', 'Hello');
}
```

### 7. Twilio SMS Mock

```php
public function test_sms_sent_on_order_confirmation(): void
{
    Http::fake([
        'api.twilio.com/*' => Http::response([
            'sid'    => 'SM123',
            'status' => 'queued',
        ], 201),
    ]);

    $order = Order::factory()->create();
    (new SmsNotifier)->orderConfirmation($order);

    Http::assertSent(function ($request) use ($order) {
        return str_contains($request->url(), 'Messages.json')
            && str_contains($request['Body'], "Order #{$order->id}");
    });
}
```

### 8. Sandbox Environment Tests

```php
// phpunit.xml
<env name="STRIPE_KEY" value="sk_test_sandbox_key"/>
<env name="STRIPE_MODE" value="sandbox"/>

// Integration test using real sandbox
/**
 * @group integration
 * @group slow
 */
public function test_real_sandbox_charge_completes(): void
{
    if (! env('STRIPE_SANDBOX_KEY')) {
        $this->markTestSkipped('No sandbox key');
    }

    $result = (new StripeService(env('STRIPE_SANDBOX_KEY')))
        ->charge(100, 'tok_visa'); // Stripe test card

    $this->assertSame('succeeded', $result['status']);
}
```

### 9. preventStrayRequests - Production Safety

```php
// tests/TestCase.php - global setup
public function setUp(): void
{
    parent::setUp();

    if ($this->shouldBlockHttp()) {
        Http::preventStrayRequests();
    }
}

// Test-də Http::fake çağırmasanız və HTTP call olsa → RuntimeException
```

## Interview Sualları

**Q1: `Http::fake()` çağırıldıqdan sonra real HTTP getmir — necə?**
A: Laravel `HttpClient` facade-ını fake handler ilə əvəz edir; Guzzle-ın alt səviyyə
transport-u intercept olunur.

**Q2: `Http::preventStrayRequests` nə edir?**
A: Fake olunmayan hər HTTP call-da `RuntimeException` atır — bu, test-də gizli real API
call-un qarşısını alır.

**Q3: SDK-nı (məs. Stripe PHP) necə mock edirik?**
A: SDK-nı service wrapper-ə keçirib, wrapper-i mock etmək ən təmiz yoldur. Birbaşa mock
üçün Mockery `alias:` və ya DI container-ə fake inject olunur.

**Q4: VCR nədir və nə vaxt istifadə olunur?**
A: VCR — real HTTP response-ları qeyd edib fayla yazır, növbəti test-də real call əvəzinə
replay edir. Mürəkkəb API-larda (çox field olan response) əlverişlidir.

**Q5: Sandbox environment nədir?**
A: Third-party xidmətin test mode-u (Stripe test keys, PayPal sandbox). Real API, amma
fake pul/dəyər. Slow olduğu üçün yalnız `@group integration` test-lərdə istifadə olunur.

**Q6: Contract testing ilə HTTP fake-in fərqi?**
A: HTTP fake — consumer tərəfdə mock. Contract testing — consumer və provider arasında
format razılaşması (Pact). Hər iki tərəf müqaviləyə uyğun olmalıdır.

**Q7: Webhook-u necə test edirik?**
A: Controller-ə POST göndərib database state-i assert edirik. Əlavə olaraq signature
verification logic-ni ayrı test edirik.

**Q8: Http fake-də sequence nə üçündür?**
A: Hər call-a fərqli response qaytarmaq üçün — polling, pagination, retry scenario-lar.

**Q9: Rate limit-i necə simulate edirik?**
A: `Http::response('', 429)` qaytarıb, retry logic-in düzgün işlədiyini test edirik.

**Q10: Third-party xəta zamanı bizim app nə edir?**
A: Timeout, 500, 503, network error test olunmalıdır. Retry, fallback, circuit breaker
logic-i var?

## Best Practices / Anti-Patterns

### Best Practices

- **`Http::preventStrayRequests()` CI-də aktiv** - Gizli real call qarşısını alır
- **Service wrapper layer** - SDK-nı wrap edib mock etmək asan olur
- **Hər error scenario test** - 4xx, 5xx, timeout, network error
- **Sandbox yalnız smoke test** - `@group integration` ilə ayrı suite
- **Webhook signature yoxlaması** - Security kritikdir
- **Response fixture-lərini saxlayın** - Realistik response struktur (VCR cassette)

### Anti-Patterns

- **Real API-ı unit test-də vurmaq** - Slow, flaky, costly
- **Happy path only** - 500, timeout test olunmur
- **Hardcoded production URL** - Config-də environment-ə görə dəyişməlidir
- **Signature verification atlamaq** - Webhook endpoint-i spoof oluna bilər
- **API key test-də hardcoded** - Leak riski, env-dən götürülsün
- **Mock SDK-nı hər test-də** - Service wrapper yaradın, wrapper-i mock edin
