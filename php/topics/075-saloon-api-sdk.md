# Saloon (Middle)

## Mündəricat
1. [Saloon nədir?](#saloon-nədir)
2. [Niyə Saloon — Guzzle əvəzinə?](#niyə-saloon--guzzle-əvəzinə)
3. [Quraşdırma](#quraşdırma)
4. [Connector & Request anatomy](#connector--request-anatomy)
5. [Authentication](#authentication)
6. [Request bodies](#request-bodies)
7. [Plugins (Auth, Throttle, Retry)](#plugins-auth-throttle-retry)
8. [Pagination](#pagination)
9. [Mock client (testing)](#mock-client-testing)
10. [OAuth2 flow](#oauth2-flow)
11. [Real SDK example](#real-sdk-example)
12. [İntervyu Sualları](#intervyu-sualları)

---

## Saloon nədir?

```
Saloon — modern PHP API integration framework.
"Build elegant, structured API SDKs."

Sam Carré tərəfindən, 2022-də.
Laravel ekosisteminin populyar tool-u, amma framework-agnostic.

Niyə yarandı?
  Hər API integration-da eyni boilerplate:
    - Base URL configure
    - Auth header (token, basic, oauth2)
    - JSON encode/decode
    - Retry logic
    - Pagination
    - Error handling
    - Mocking üçün test setup
  
  Saloon bunları "convention over configuration" ilə həll edir.
  Class-based SDK structure verir.
```

---

## Niyə Saloon — Guzzle əvəzinə?

```
GUZZLE:
  Sadəcə HTTP client.
  Hər request üçün manual base URL, headers, body, response parse.
  Retry middleware ayrıca config.
  Mock setup verbose.
  
  Çox kod təkrarlayıcı, "anonymous request"-lər.

SALOON:
  Connector (API base) + Request (specific endpoint) class-ları.
  Hər API "obyekt-orientli SDK" olur.
  Plugin sistem (caching, retry, throttle).
  Built-in mock client (test friendly).
  Resource pattern (REST-ful API üçün).
  
  Daha çox kod yazırsan, AMMA structured və reusable.

Ne vaxt Saloon?
  ✓ 3+ endpoint olan API integration
  ✓ İçəridə SDK paketi yaratmaq (composer package)
  ✓ Heavy mocking lazım olan test suite
  ✓ Multi-tenant API (per-customer config)

Ne vaxt Guzzle direct?
  ✓ Tək endpoint çağırışı (one-off webhook trigger)
  ✓ Saloon learning curve istəmirsən
```

---

## Quraşdırma

```bash
composer require saloonphp/saloon
# Laravel inteqrasiya:
composer require saloonphp/laravel-plugin
```

---

## Connector & Request anatomy

```php
<?php
// CONNECTOR — API "base configuration" — bir SDK üçün bir Connector
namespace App\Http\Integrations\Stripe;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class StripeConnector extends Connector
{
    use AcceptsJson;
    
    public function __construct(
        private string $secretKey,
    ) {}
    
    public function resolveBaseUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }
    
    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->secretKey}",
            'Stripe-Version' => '2023-10-16',
        ];
    }
    
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }
}
```

```php
<?php
// REQUEST — specific endpoint
namespace App\Http\Integrations\Stripe\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCustomerRequest extends Request
{
    protected Method $method = Method::GET;
    
    public function __construct(public string $customerId) {}
    
    public function resolveEndpoint(): string
    {
        return "/customers/{$this->customerId}";
    }
}

// İstifadə
$connector = new StripeConnector(secretKey: 'sk_test_...');
$response = $connector->send(new GetCustomerRequest('cus_xxx'));

$customer = $response->json();
echo $customer['email'];

// Status / fail handling
if ($response->failed()) {
    $error = $response->json('error.message');
    throw new StripeException($error);
}

// Helper methods on response
$response->status();         // 200
$response->json();           // array
$response->object();         // stdClass
$response->dto(CustomerDto::class);   // mapping
$response->collect();         // Laravel Collection
$response->headers();
```

---

## Authentication

```php
<?php
// Basic Auth
use Saloon\Http\Auth\BasicAuthenticator;

class MyConnector extends Connector
{
    protected function defaultAuth(): ?Authenticator
    {
        return new BasicAuthenticator(
            username: $this->user,
            password: $this->pass,
        );
    }
}

// Token Auth
use Saloon\Http\Auth\TokenAuthenticator;

protected function defaultAuth(): ?Authenticator
{
    return new TokenAuthenticator($this->token);   // "Bearer xxx"
}

// Header Auth (custom)
use Saloon\Http\Auth\HeaderAuthenticator;

protected function defaultAuth(): ?Authenticator
{
    return new HeaderAuthenticator($this->key, headerName: 'X-API-Key');
}

// Per-request override
$connector->withTokenAuth($differentToken)->send($request);
```

---

## Request bodies

```php
<?php
// JSON body
use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

class CreateCustomerRequest extends Request implements HasBody
{
    use HasJsonBody;
    
    protected Method $method = Method::POST;
    
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}
    
    public function resolveEndpoint(): string
    {
        return '/customers';
    }
    
    protected function defaultBody(): array
    {
        return [
            'email' => $this->email,
            'name'  => $this->name,
        ];
    }
}

// Form body (application/x-www-form-urlencoded)
use Saloon\Traits\Body\HasFormBody;

class CreateChargeRequest extends Request implements HasBody
{
    use HasFormBody;
    
    protected function defaultBody(): array
    {
        return ['amount' => $this->amount, 'currency' => 'usd'];
    }
}

// Multipart (file upload)
use Saloon\Traits\Body\HasMultipartBody;
use Saloon\MultipartBodyRepository;

class UploadFileRequest extends Request implements HasBody
{
    use HasMultipartBody;
    
    protected function defaultBody(): array
    {
        return [
            new MultipartValue('file', fopen($this->path, 'r'), 'upload.csv'),
            new MultipartValue('purpose', 'data'),
        ];
    }
}

// Raw body
use Saloon\Traits\Body\HasStringBody;

class XmlRequest extends Request implements HasBody
{
    use HasStringBody;
    
    protected function defaultBody(): string
    {
        return '<xml>...</xml>';
    }
}
```

---

## Plugins (Auth, Throttle, Retry)

```php
<?php
// CACHING — same response yenidən API-ya getmir
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\CachePlugin\Traits\HasCaching;

class StripeConnector extends Connector
{
    use HasCaching;
    
    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store('redis'));
    }
    
    public function cacheExpiryInSeconds(): int
    {
        return 3600;   // 1 hour
    }
}

// Per-request cache:
class GetCustomerRequest extends Request
{
    use HasCaching;   // GET-only cacheable
}

// THROTTLING — rate limit qarşı
use Saloon\RateLimitPlugin\Traits\HasRateLimits;
use Saloon\RateLimitPlugin\Limit;

class StripeConnector extends Connector
{
    use HasRateLimits;
    
    protected function resolveLimits(): array
    {
        return [
            Limit::allow(100)->everyMinute(),
            Limit::allow(1000)->everyHour(),
        ];
    }
    
    protected function resolveRateLimitStore(): RateLimitStore
    {
        return new RedisStore(Redis::connection());
    }
}

// RETRY — built-in
class GetCustomerRequest extends Request
{
    public ?int $tries = 3;
    public ?int $retryInterval = 1000;   // ms
    
    public function handleRetry(FatalRequestException|RequestException $e, Request $r): bool
    {
        return $e instanceof FatalRequestException
            || ($e instanceof RequestException && $e->getStatus() >= 500);
    }
}
```

---

## Pagination

```php
<?php
use Saloon\PaginationPlugin\PagedPaginator;
use Saloon\PaginationPlugin\Contracts\HasPagination;

class StripeConnector extends Connector implements HasPagination
{
    public function paginate(Request $request): PagedPaginator
    {
        return new class($this, $request) extends PagedPaginator {
            protected function isLastPage(Response $response): bool
            {
                return ! $response->json('has_more');
            }
            
            protected function getPageItems(Response $response, Request $request): array
            {
                return $response->json('data');
            }
        };
    }
}

// İstifadə
$paginator = $connector->paginate(new ListCustomersRequest());

foreach ($paginator as $page => $response) {
    foreach ($response->json('data') as $customer) {
        // Process each customer
    }
}

// Async paginator
$paginator->async(true)->concurrency(5);
```

---

## Mock client (testing)

```php
<?php
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// Mock global
MockClient::global([
    GetCustomerRequest::class => MockResponse::make([
        'id'    => 'cus_123',
        'email' => 'test@test.com',
    ], 200),
]);

// Test
public function test_can_fetch_customer(): void
{
    $mockClient = new MockClient([
        GetCustomerRequest::class => MockResponse::make(['id' => 'cus_test'], 200),
    ]);
    
    $connector = new StripeConnector('sk_test');
    $connector->withMockClient($mockClient);
    
    $response = $connector->send(new GetCustomerRequest('cus_test'));
    
    $this->assertSame('cus_test', $response->json('id'));
    
    // Assertions
    $mockClient->assertSent(GetCustomerRequest::class);
    $mockClient->assertSentCount(1);
    $mockClient->assertNothingSent(/* class */);
}

// Sequence
$mockClient = new MockClient([
    MockResponse::make(['attempt' => 1], 500),
    MockResponse::make(['attempt' => 2], 500),
    MockResponse::make(['attempt' => 3], 200),    // 3-cü cəhd uğurlu
]);

// Closure-based
$mockClient = new MockClient([
    GetCustomerRequest::class => function (Request $r) {
        return MockResponse::make(['id' => $r->customerId]);
    },
]);
```

---

## OAuth2 flow

```php
<?php
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Helpers\OAuth2\OAuthConfig;

class GoogleConnector extends Connector
{
    use AuthorizationCodeGrant;
    
    public function resolveBaseUrl(): string
    {
        return 'https://www.googleapis.com';
    }
    
    protected function defaultOauthConfig(): OAuthConfig
    {
        return OAuthConfig::make()
            ->setClientId(env('GOOGLE_CLIENT_ID'))
            ->setClientSecret(env('GOOGLE_CLIENT_SECRET'))
            ->setRedirectUri('https://app.example.com/auth/google/callback')
            ->setDefaultScopes(['openid', 'email', 'profile'])
            ->setAuthorizeEndpoint('https://accounts.google.com/o/oauth2/v2/auth')
            ->setTokenEndpoint('https://oauth2.googleapis.com/token');
    }
}

// Authorization
$connector = new GoogleConnector();
$url = $connector->getAuthorizationUrl(state: $state);
return redirect($url);

// Callback
$authToken = $connector->getAccessToken($request->code, state: $state);
session(['google_token' => $authToken]);

// Use token
$connector->authenticate(new TokenAuthenticator($authToken->getAccessToken()));
$response = $connector->send(new GetUserInfoRequest());

// Refresh
$newToken = $connector->refreshAccessToken($authToken);
```

---

## Real SDK example

```php
<?php
// SDK structure
src/
  Stripe/
    StripeConnector.php
    Resources/
      CustomerResource.php
      ChargeResource.php
    Requests/
      Customer/
        ListCustomers.php
        GetCustomer.php
        CreateCustomer.php
      Charge/
        CreateCharge.php
    DTOs/
      CustomerDto.php
      ChargeDto.php

// Resource pattern (REST-ful API üçün)
class CustomerResource
{
    public function __construct(private StripeConnector $connector) {}
    
    public function list(int $limit = 10): Response
    {
        return $this->connector->send(new ListCustomersRequest($limit));
    }
    
    public function get(string $id): Response
    {
        return $this->connector->send(new GetCustomerRequest($id));
    }
    
    public function create(string $email, ?string $name = null): Response
    {
        return $this->connector->send(new CreateCustomerRequest($email, $name));
    }
}

// Connector-də resource expose
class StripeConnector extends Connector
{
    public function customers(): CustomerResource
    {
        return new CustomerResource($this);
    }
    
    public function charges(): ChargeResource
    {
        return new ChargeResource($this);
    }
}

// İstifadə (Stripe SDK kimi)
$stripe = new StripeConnector('sk_test');
$customer = $stripe->customers()->get('cus_123')->dto(CustomerDto::class);
$stripe->charges()->create(amount: 1000, customer: $customer->id);
```

---

## İntervyu Sualları

- Saloon ilə Guzzle arasındakı fərq nədir?
- Connector və Request class-ı nə üçün ayrılır?
- Resource pattern niyə istifadə olunur?
- Mock client testing-də nə fayda verir?
- Caching plugin necə işləyir?
- Rate limit plugin distributed mühitdə (Redis) niyə vacibdir?
- OAuth2 Authorization Code grant Saloon-da necə implementasiya olunur?
- Pagination contract nə üçündür?
- DTO mapping nə vaxt edilməlidir?
- Connector-də `defaultHeaders` nə vaxt override olunur?
- Saloon SDK package olaraq Composer-də necə paylaşılır?
- HTTP middleware Saloon-da necə əlavə olunur?
