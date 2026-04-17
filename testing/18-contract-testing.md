# Contract Testing

## Nədir? (What is it?)

Contract testing, iki service arasındakı API "müqaviləsinin" (contract) düzgün saxlanıldığını
yoxlayan test növüdür. Microservice arxitekturada Service A (consumer) Service B-dən (provider)
data gözləyir - contract testing bu gözləntilərin hər iki tərəf üçün ödənildiyini təmin edir.

Traditional integration testing-dən fərqli olaraq, contract testing hər service-i ayrıca
test edir. Consumer "mən bu formatda data gözləyirəm" deyir (contract), provider isə
"mən bu contract-ı ödəyirəm" deyə yoxlayır. Bu yanaşma microservice-lərin müstəqil
deploy olunmasını təhlükəsiz edir.

### Niyə Contract Testing Vacibdir?

1. **Müstəqil deployment** - Service-lər bir-birindən asılı olmadan deploy oluna bilər
2. **Sürətli feedback** - Tam E2E test lazım deyil, hər service ayrıca test olunur
3. **Breaking change detection** - API dəyişikliyi consumer-ləri pozmadan əvvəl tutulur
4. **Documentation** - Contract-lar API-nin living documentation-ıdır
5. **Güvən** - Provider team consumer-lərin nə gözlədiyini bilir

## Əsas Konseptlər (Key Concepts)

### Consumer-Driven Contracts (CDC)

```
Consumer-Driven Contract prosesi:

1. Consumer test yazır:
   "Mən /api/users/1 çağıranda, bu formatda cavab gözləyirəm:
    { id: integer, name: string, email: string }"

2. Contract faylı yaranır (Pact JSON):
   Consumer gözləntiləri fayla yazılır

3. Provider contract-ı yoxlayır:
   Provider öz API-sini contract-a qarşı test edir
   "Bəli, mən bu formatda cavab verirəm"

4. Contract Broker (Pactflow):
   Contract-ları mərkəzi yerdə saxlayır
   Versiyalaşdırır
   Can-I-Deploy yoxlaması edir

Consumer → Contract → Provider
  (nə gözləyir?)   (nə verir?)
```

### Contract Testing vs Integration Testing

| Xüsusiyyət | Contract Testing | Integration Testing |
|------------|-----------------|-------------------|
| Scope | İki service arası interface | Bütün service-lər birlikdə |
| Sürət | Sürətli (mock-lanır) | Yavaş (real service-lər) |
| İzolyasiya | Hər service ayrıca | Bütün stack lazımdır |
| Maintenance | Contract faylları | Test environment |
| Failure | Hansı contract pozulduğu aydın | Kökü tapmaq çətin |
| Deploy | Müstəqil deploy mümkün | Koordinasiyalı deploy |

### Pact Workflow

```
Consumer Side:
  1. Test yazılır (interaction təyin edilir)
  2. Pact mock server istifadə olunur
  3. Test pass edirsə, Pact JSON yaranır
  4. Pact faylı Broker-ə yüklənir

Provider Side:
  1. Broker-dən Pact faylını alır
  2. Real API-ni Pact-a qarşı test edir
  3. Bütün interaction-lar yoxlanır
  4. Nəticə Broker-ə göndərilir

Can-I-Deploy:
  "Bu versiyanı deploy edə bilərəm?"
  Broker bütün consumer/provider versiyalarını yoxlayır
```

## Praktiki Nümunələr (Practical Examples)

### Pact Consumer Test (PHP)

```php
<?php

namespace Tests\Contract\Consumer;

use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerConfig;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class UserServiceConsumerTest extends TestCase
{
    private InteractionBuilder $builder;
    private MockServerConfig $config;

    protected function setUp(): void
    {
        $this->config = new MockServerConfig();
        $this->config->setHost('localhost');
        $this->config->setPort(7200);
        $this->config->setConsumer('OrderService');
        $this->config->setProvider('UserService');
        $this->config->setPactDir(__DIR__ . '/../../pacts');

        $this->builder = new InteractionBuilder($this->config);
    }

    /** @test */
    public function it_gets_user_by_id(): void
    {
        // Arrange: Gözlənən interaction təyin et
        $request = new ConsumerRequest();
        $request->setMethod('GET')
            ->setPath('/api/users/1')
            ->addHeader('Accept', 'application/json');

        $response = new ProviderResponse();
        $response->setStatus(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'is_active' => true,
            ]);

        $this->builder
            ->given('user with id 1 exists')
            ->uponReceiving('a request for user 1')
            ->with($request)
            ->willRespondWith($response);

        // Act: Real HTTP request göndər (mock server-ə)
        $client = new Client(['base_uri' => $this->config->getBaseUri()]);
        $result = $client->get('/api/users/1', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $body = json_decode($result->getBody()->getContents(), true);

        // Assert
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(1, $body['id']);
        $this->assertEquals('John Doe', $body['name']);
        $this->assertArrayHasKey('email', $body);

        // Verify: Pact faylını yarat
        $this->builder->verify();
    }

    /** @test */
    public function it_handles_user_not_found(): void
    {
        $request = new ConsumerRequest();
        $request->setMethod('GET')
            ->setPath('/api/users/999')
            ->addHeader('Accept', 'application/json');

        $response = new ProviderResponse();
        $response->setStatus(404)
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'error' => 'User not found',
            ]);

        $this->builder
            ->given('user with id 999 does not exist')
            ->uponReceiving('a request for non-existent user')
            ->with($request)
            ->willRespondWith($response);

        $client = new Client([
            'base_uri' => $this->config->getBaseUri(),
            'http_errors' => false,
        ]);
        $result = $client->get('/api/users/999', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertEquals(404, $result->getStatusCode());
        $this->builder->verify();
    }

    /** @test */
    public function it_creates_a_new_user(): void
    {
        $request = new ConsumerRequest();
        $request->setMethod('POST')
            ->setPath('/api/users')
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
            ]);

        $response = new ProviderResponse();
        $response->setStatus(201)
            ->addHeader('Content-Type', 'application/json')
            ->setBody([
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'is_active' => true,
            ]);

        $this->builder
            ->uponReceiving('a request to create a user')
            ->with($request)
            ->willRespondWith($response);

        $client = new Client(['base_uri' => $this->config->getBaseUri()]);
        $result = $client->post('/api/users', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
            ],
        ]);

        $this->assertEquals(201, $result->getStatusCode());
        $this->builder->verify();
    }
}
```

### Pact Provider Verification

```php
<?php

namespace Tests\Contract\Provider;

use PhpPact\Standalone\ProviderVerifier\Model\VerifierConfig;
use PhpPact\Standalone\ProviderVerifier\Verifier;
use PHPUnit\Framework\TestCase;

class UserServiceProviderTest extends TestCase
{
    /** @test */
    public function it_fulfills_order_service_contract(): void
    {
        $config = new VerifierConfig();
        $config->setProviderName('UserService')
            ->setProviderBaseUrl('http://localhost:8000')
            ->setProviderStatesSetupUrl('http://localhost:8000/pact/setup')
            ->setPactBrokerUri('https://pactbroker.example.com')
            ->setBrokerToken(env('PACT_BROKER_TOKEN'))
            ->setPublishResults(true)
            ->setProviderVersion(exec('git rev-parse HEAD'));

        $verifier = new Verifier($config);

        // Broker-dən consumer pact-ları alıb verify edir
        $verifier->verifyFromBroker();
    }
}
```

### Provider State Setup

```php
<?php

// routes/api.php (yalnız test mühitində)
if (app()->environment('testing')) {
    Route::post('/pact/setup', function (Request $request) {
        $state = $request->input('state');

        match ($state) {
            'user with id 1 exists' => User::factory()->create([
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]),
            'user with id 999 does not exist' => null,
            default => throw new \Exception("Unknown state: {$state}"),
        };

        return response()->json(['status' => 'ok']);
    });
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Sadə Contract Test (Pact-sız)

```php
<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserApiContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Contract: GET /api/users/{id} response format
     */
    /** @test */
    public function user_endpoint_returns_expected_contract(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'created_at',
                ],
            ]);

        // Type validation
        $data = $response->json('data');
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['name']);
        $this->assertIsString($data['email']);
        $this->assertIsBool($data['is_active']);

        // Sensitive data olmamalıdır
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    /**
     * Contract: POST /api/users request/response format
     */
    /** @test */
    public function create_user_endpoint_contract(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'Password123!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'is_active', 'created_at'],
            ]);

        // Response-da password olmamalıdır
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    /**
     * Contract: Error response format
     */
    /** @test */
    public function error_response_follows_contract(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/users/99999');

        $response->assertStatus(404)
            ->assertJsonStructure([
                'message',
            ]);

        $this->assertIsString($response->json('message'));
    }

    /**
     * Contract: Validation error response format
     */
    /** @test */
    public function validation_error_follows_contract(): void
    {
        $admin = \App\Models\User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => [],
            ]);
    }
}
```

### OpenAPI/Swagger Contract Validation

```php
<?php

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    private array $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = json_decode(
            file_get_contents(base_path('docs/openapi.json')),
            true
        );
    }

    /** @test */
    public function response_matches_openapi_schema(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/users');

        $response->assertStatus(200);

        // Schema-dakı user definition ilə müqayisə
        $expectedProperties = array_keys(
            $this->schema['components']['schemas']['User']['properties']
        );

        $actualProperties = array_keys($response->json('data.0'));

        foreach ($expectedProperties as $property) {
            $this->assertContains($property, $actualProperties,
                "Missing property: {$property}");
        }
    }
}
```

## Interview Sualları

### 1. Contract testing nədir və nə üçün lazımdır?
**Cavab:** Contract testing iki service arasındakı API müqaviləsinin düzgün saxlanıldığını yoxlayır. Consumer "mən bu formatda data gözləyirəm" deyir, provider "mən bu formatı ödəyirəm" deyə verify edir. Microservice-lərin müstəqil deploy olunmasını təhlükəsiz edir. E2E testdən sürətlidir, breaking change-ləri tez tapır.

### 2. Consumer-driven contracts nə deməkdir?
**Cavab:** Consumer tərəf contract-ı yaradır - "mən bu endpoint-dən bu formatda data gözləyirəm". Provider bu contract-ı ödəməlidir. Bu yanaşma consumer-in ehtiyaclarını ön plana çıxarır. Provider yalnız consumer-lərin istifadə etdiyi field-ləri dəstəkləməlidir, bütün API-ni deyil.

### 3. Pact nədir?
**Cavab:** Pact consumer-driven contract testing framework-üdür. Consumer tərəfdə mock server ilə test yazılır, Pact JSON faylı yaranır. Provider tərəfdə bu JSON-a qarşı real API test edilir. Pact Broker contract-ları mərkəzi yerdə saxlayır, versiyalayır və can-i-deploy yoxlaması edir.

### 4. Contract testing, integration testing-i əvəz edirmi?
**Cavab:** Xeyr, tamamlayır. Contract testing yalnız interface-i yoxlayır (request/response format), business logic-i yox. Integration testing real service-lər arası data flow-u test edir. Contract testing sürətli və izole, integration testing daha əhatəli amma yavaşdır. İkisi birlikdə istifadə olunmalıdır.

### 5. Provider state nədir?
**Cavab:** Provider state, provider-in test üçün müəyyən vəziyyətə gətirilməsidir. Məsələn "user with id 1 exists" - provider bu state-i qurmalıdır (factory ilə user yaratmaq). Hər interaction-ın precondition-ıdır. Pact-da `given()` method ilə təyin edilir.

### 6. Breaking change nədir və contract testing bunu necə tutur?
**Cavab:** Breaking change API-nin geriyə uyğun olmayan dəyişikliyidir: field silinir, type dəyişir, endpoint URL dəyişir. Contract testing: consumer field gözləyir → provider field silir → provider verification fail olur → deploy bloklanır. Field əlavə etmək breaking deyil.

## Best Practices / Anti-Patterns

### Best Practices

1. **Consumer-first approach** - Consumer-in ehtiyaclarından başlayın
2. **Minimal contract** - Yalnız istifadə olunan field-ləri contract-a əlavə edin
3. **Pact Broker istifadə edin** - Mərkəzi contract idarəetməsi
4. **CI/CD-yə inteqrasiya** - Can-I-Deploy deploy-dan əvvəl
5. **Provider state-ləri sadə saxlayın** - Minimal test data
6. **Versiyalama** - Consumer/provider versiyalarını izləyin

### Anti-Patterns

1. **Bütün response-u contract-a əlavə etmək** - Yalnız istifadə olunan field-lər
2. **Contract testing-i E2E əvəzinə istifadə etmək** - Fərqli məqsədlərdir
3. **Contract-ları manual idarə etmək** - Broker istifadə edin
4. **Provider state-də mürəkkəb setup** - Sadə factory-lər istifadə edin
5. **Contract-ları nadir yeniləmək** - Hər API dəyişikliyində yeniləyin
6. **Yalnız bir tərəfi test etmək** - Həm consumer, həm provider test olunmalıdır
