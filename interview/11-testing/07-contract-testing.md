# Contract Testing (Lead ⭐⭐⭐⭐)

## İcmal
Contract testing — iki servis arasındakı interfeysin (contract-ın) hər iki tərəf üçün uyğun olduğunu yoxlayan test metodologiyasıdır. Microservice arxitekturalarında E2E testlərin effektiv alternatividir: xidmətlər bir-birinə birbaşa bağlı olmadan, contract vasitəsilə razılığı verify edirlər. Pact framework bu alanda standart hala gəlmişdir. Lead engineer kimi bu arxitektura seçimini başa düşmək microservice maturitysinin göstəricisidir.

## Niyə Vacibdir
Microservice-lər çoxaldıqca integration test etmək getdikcə çətinləşir: 15 service üçün tam E2E test, bütün service-ləri eyni anda ayağa qaldırmaq, bir service dəyişdikdə 14 başqasının testlərini yenidən run etmək — böyük overhead. Contract testing bu problemi həll edir: hər service öz contract-ını verify edir, Pact Broker isə uyğunluğu mərkəzi olaraq izləyir. "Can I deploy?" sualına deployment-dan əvvəl cavab verir. Breaking change-ləri production-dan əvvəl tutur.

## Əsas Anlayışlar

- **Consumer-Driven Contract Testing**: Consumer (çağıran servis) provider-dən nə gözlədiyini contract-a yazır. Provider consumer-in contract-ını öz API-nə qarşı verify edir. Consumer tərəfindən idarə edilir — provider consumer-in ehtiyaclarından xəbərdar olur.

- **Consumer (Çağıran servis)**: API-dən istifadə edən servis — frontend, başqa microservice. "Mən Provider-dən bu response format-ı gözləyirəm."

- **Provider (Cavab verən servis)**: API təqdim edən servis. Consumer-in gözləntisinə uyğun cavab verirmi?

- **Pact (Contract faylı)**: Consumer-Provider arasındakı razılaşma. JSON formatında saxlanır. "GET /users/1 sorğusuna {id: 1, name: string, email: string} qaytarılmalıdır."

- **Pact Broker**: Contract-ları saxlayan və paylaşan mərkəzi server. Consumer pact-ı publish edir, Provider CI-da broker-dən alır. Version management, compatibility matrix, webhook support.

- **Verification**: Provider-in consumer contract-ını real API-sinə qarşı yoxlaması. Nəticə: Pass ya da Fail. Fail olarsa provider bu versiyanı deploy edə bilməz (can-i-deploy check ilə).

- **can-i-deploy CLI**: "Bu consumer version bu provider version ilə birlikdə deploy edilə bilərmi?" Pact Broker-dən cavab alır. CI pipeline-ın son addımı olaraq.

- **Consumer test workflow**: Consumer → Mock Provider (Pact mock server) → real cavab gəlir kimi → Pact file yaradılır → Broker-ə publish.

- **Provider verification workflow**: Pact Broker → provider real API-si → her consumer pact-ı bir-bir verify edilir → Pass/Fail.

- **Provider State (Fixture management)**: Consumer test "given a user with ID 1 exists" deyir. Provider-in bu state-i qurmaq üçün state handler lazımdır — test fixture qurulur, yaxud test DB hazırlanır.

- **OpenAPI Contract Testing**: Pact-ın alternativi. OpenAPI spec-i real API-yə qarşı verify edilir. Dredd, Schemathesis — spec-ə uyğunluğu yoxlayır. Fərq: OpenAPI schema-based, Pact consumer-driven.

- **Contract testing vs E2E testing fərqi**: E2E — bütün service-lər canlı, tam real flow. Contract — hər service ayrıca, mock/real API. E2E daha yüksək confidence, contract daha sürətli və maintainable.

- **Breaking change detection**: Provider API response-unu dəyişdirdikdə — consumer pact-ının verification-ı fail olur. Deploy etmədən əvvəl xəbər tutulur. Production incident əvəzinə CI failure.

- **Consumer-First API Design**: Provider API-ni dəyişdirmədən əvvəl consumer pact-larını yoxlayır. Bu "consumer-first" düşüncəni stimullaşdırır — provider consumer-in nə istədiyini bilmədən API dizayn etmir.

- **Contract testing limitləri**: Business logic test etmir (bu integration/unit test-in işidir). Yalnız interface uyğunluğunu yoxlayır. E2E-ni tamamilə əvəz etmir — kritik end-to-end flow-lar hələ lazımdır.

- **Versioning**: Consumer v1 contract-ı, consumer v2 contract-ı — provider hər ikisi ilə uyğun olmalıdır. Pact Broker versioning-i idarə edir. Mobile app-lar tez güncəllənmir — köhnə contract-lar uzun müddət aktiv qalır.

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Microservice-lər arasında integration-ı necə test edirsiniz?" sualına "E2E test yazırıq" deyilsə sadədir. Contract testing-i bilmək sizi fərqləndirər. "E2E testlər çox bahalı, biz consumer-driven contract testing tətbiq edirik" — bu cümləni söyləmək Lead-lik göstərir.

**Junior-dan fərqlənən senior cavabı:**
Junior: "Integration test yazırıq, bütün service-ləri ayağa qaldırıb test edirik."
Senior: "Pact framework ilə consumer-driven contract testing edirik. Consumer mock server-ə qarşı test edir, provider CI-da pact-ı verify edir."
Lead: "can-i-deploy CLI deploy pipeline-ın son addımıdır. Breaking change-ı production-a keçmədən catch etmişik — consumer pact fail oldu, provider teamini xəbərdar etdik, koordinasiya etdik."

**Follow-up suallar:**
- "Pact Broker nədir?"
- "Consumer-Driven Contract nə deməkdir? Niyə consumer-driven?"
- "can-i-deploy CLI nədir?"
- "E2E test ilə contract test — nə vaxt hansını seçirsiniz?"
- "Provider State nədir? Necə idarə olunur?"

**Ümumi səhvlər:**
- Provider-driven contract yazmaq — consumer-in ehtiyaclarını görməz
- Pact-ı integration test kimi yazmaq — semantika fərqlidir
- Provider state-ləri düzgün qurmamaq — verify yanıltıcı nəticə verir
- Contract testing-i E2E-nin tam əvəzi hesab etmək — kritik flow-lar hələ E2E tələb edir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Consumer-Driven-ın niyə "consumer-driven" olduğunu başa düşmək. "Provider API-ni dəyişdikdə consumer-in pact-ı verification-da fail olur — breaking change-dən əvvəl xəbər tutulur" — bu anlayış əla cavabdır.

## Nümunələr

### Tipik Interview Sualı
"Microservice architecture-da service-lər arasındakı integration-ı test etmək üçün nə istifadə edirsiniz?"

### Güclü Cavab
"Microservice sayı artdıqca E2E testlər mümkün olmur — hər service canlı olmalıdır, test yavaşdır, CI overhead böyüyür. Biz consumer-driven contract testing istifadə edirik. Order service user-service-dən nə gözlədiyini pact file-a yazır, mock server qarşısında test edir. User service CI-da bu pact-ı real API-sinə qarşı verify edir. Pact Broker bütün contract-ları saxlayır. Deploy etmədən əvvəl can-i-deploy CLI bu consumer-provider cütünün uyğun olub-olmadığını yoxlayır. Bir dəfə user-service response-a yeni required field əlavə etdi — order service pact-ı fail oldu. Deployment dayandırıldı, koordinasiya edildi, consumer contract güncəlləndi, sonra deploy."

### Workflow Diaqramı

```
CONSUMER SIDE (Order Service):
──────────────────────────────
OrderService CI:
  1. Test run → Mock Pact Server ayağa qalxır
  2. OrderService test → Mock Server-ə sorğu
  3. Mock Server → Pact file yaradır
  4. Pact file → Pact Broker-ə publish

  Pact File (JSON):
  {
    "consumer": "order-service",
    "provider": "user-service",
    "interactions": [{
      "description": "get user 1",
      "request":  { "method": "GET", "path": "/users/1" },
      "response": { "status": 200, "body": {"id": 1, "name": "..."} }
    }]
  }

PROVIDER SIDE (User Service):
──────────────────────────────
UserService CI:
  1. Pact Broker-dən consumer pact-larını al
  2. Provider state-lər qurul (fixtures)
  3. Real UserService API-si ayağa qalxır
  4. Pact-dakı hər interaction real API-yə göndərilir
  5. Cavab gözləniləndən fərqlidirsə: FAIL

DEPLOYMENT:
──────────────────────────────
CI → can-i-deploy check:
  pact-broker can-i-deploy \
    --pacticipant order-service --version 2.1.0 \
    --pacticipant user-service  --version 3.4.0 \
    --to production
  → YES: deploy icazəsi var
  → NO:  deploy bloklanır
```

### Kod Nümunəsi (PHP/Laravel)

```php
// ═══════════════════════════════════════════════════════
// CONSUMER SIDE — Order Service test
// PhpPact library istifadəsi
// ═══════════════════════════════════════════════════════
use PhpPact\Consumer\InteractionBuilder;
use PhpPact\Consumer\Model\ConsumerRequest;
use PhpPact\Consumer\Model\ProviderResponse;
use PhpPact\Standalone\MockService\MockServerEnvConfig;
use PhpPact\Standalone\MockService\MockServer;

class UserServiceClientConsumerTest extends TestCase
{
    private static MockServer $mockServer;
    private static InteractionBuilder $builder;

    public static function setUpBeforeClass(): void
    {
        $config = new MockServerEnvConfig();
        // Env vars: PACT_MOCK_SERVER_HOST, PACT_MOCK_SERVER_PORT
        // PACT_CONSUMER_NAME, PACT_PROVIDER_NAME
        // PACT_OUTPUT_DIR

        self::$mockServer = new MockServer($config);
        self::$mockServer->start();
        self::$builder = new InteractionBuilder($config);
    }

    public static function tearDownAfterClass(): void
    {
        self::$mockServer->stop();
    }

    public function test_get_user_returns_expected_format(): void
    {
        // Consumer: "mən provider-dən bu formatı gözləyirəm"
        self::$builder
            ->given('a user with ID 1 exists')
            ->uponReceiving('a request to get user 1')
            ->with(
                (new ConsumerRequest())
                    ->setMethod('GET')
                    ->setPath('/api/users/1')
                    ->setHeader('Accept', 'application/json')
                    ->setHeader('Authorization', 'Bearer test-token')
            )
            ->willRespondWith(
                (new ProviderResponse())
                    ->setStatus(200)
                    ->setHeader('Content-Type', 'application/json')
                    ->setBody([
                        'id'         => 1,
                        'first_name' => 'John',
                        'last_name'  => 'Doe',
                        'email'      => 'john.doe@example.com',
                        'status'     => 'active',
                    ])
            );

        // Real consumer kodu mock server-ə qarşı test edilir
        $client = new UserServiceClient(
            baseUrl: self::$mockServer->getBaseUri(),
            token:   'test-token'
        );

        $user = $client->getUser(1);

        $this->assertEquals(1, $user->id);
        $this->assertEquals('John', $user->firstName);
        $this->assertEquals('john.doe@example.com', $user->email);

        // Pact file yaradılır: pacts/order-service-user-service.json
        self::$builder->verify();
    }

    public function test_get_nonexistent_user_returns_404(): void
    {
        self::$builder
            ->given('no user with ID 999 exists')
            ->uponReceiving('a request to get user 999')
            ->with(
                (new ConsumerRequest())
                    ->setMethod('GET')
                    ->setPath('/api/users/999')
            )
            ->willRespondWith(
                (new ProviderResponse())
                    ->setStatus(404)
                    ->setBody(['error' => 'User not found', 'code' => 'USER_NOT_FOUND'])
            );

        $client = new UserServiceClient(baseUrl: self::$mockServer->getBaseUri());

        $this->expectException(UserNotFoundException::class);
        $client->getUser(999);

        self::$builder->verify();
    }
}

// ═══════════════════════════════════════════════════════
// PROVIDER SIDE — User Service verification
// ═══════════════════════════════════════════════════════
use PhpPact\Standalone\ProviderVerifier\ProviderVerifier;
use PhpPact\Standalone\ProviderVerifier\Model\VerifierConfig;

class UserServiceProviderPactTest extends TestCase
{
    public function test_verify_all_consumer_pacts(): void
    {
        // Provider verification konfiqurasiyası
        $config = (new VerifierConfig())
            ->setProviderName('user-service')
            ->setProviderVersion(getenv('APP_VERSION') ?: '1.0.0')
            ->setPactBrokerUri(getenv('PACT_BROKER_BASE_URL'))
            ->setPactBrokerToken(getenv('PACT_BROKER_TOKEN'))
            ->setProviderBaseUrl('http://localhost:' . getenv('PROVIDER_PORT', '8080'))
            ->setStateChangeUrl('http://localhost:' . getenv('PROVIDER_PORT') . '/pact/state')
            ->setPublishResults(getenv('CI') === 'true');  // CI-da nəticəni publish et

        $verifier = new ProviderVerifier();
        $result   = $verifier->verifyAll($config);

        // true = bütün pact-lar verify edildi
        $this->assertTrue($result, 'Some consumer pact verifications failed');
    }
}

// Provider State Handler — Laravel route
// Hər "given" clause-u üçün state handler
Route::post('/pact/state', function (Request $request): JsonResponse {
    $state = $request->input('state');
    $params = $request->input('params', []);

    match ($state) {
        'a user with ID 1 exists' => (function () use ($params) {
            User::factory()->create([
                'id'         => 1,
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'john.doe@example.com',
                'status'     => 'active',
            ]);
        })(),

        'no user with ID 999 exists' => (function () {
            User::where('id', 999)->delete();
        })(),

        'a user with status suspended exists' => (function () use ($params) {
            User::factory()->create([
                'id'     => $params['id'] ?? 5,
                'status' => 'suspended',
            ]);
        })(),

        default => throw new \UnexpectedValueException(
            "Unknown provider state: {$state}"
        ),
    };

    return response()->json(['result' => 'state set']);
})->middleware('pact.only');  // Yalnız test mühitdə mövcuddur!
```

```yaml
# CI/CD — Pact workflow GitHub Actions
# .github/workflows/pact.yml

# Consumer workflow
name: Consumer Pact Tests
on: [push, pull_request]

jobs:
  consumer-pact:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run consumer pact tests (pact file yaratmaq)
        run: |
          php artisan test --filter=ConsumerTest
        env:
          PACT_MOCK_SERVER_HOST: localhost
          PACT_MOCK_SERVER_PORT: 7200
          PACT_CONSUMER_NAME: order-service
          PACT_PROVIDER_NAME: user-service
          PACT_OUTPUT_DIR: pacts/

      - name: Publish pact to broker
        run: |
          docker run --rm \
            -v $(pwd)/pacts:/pacts \
            pactfoundation/pact-cli:latest \
            publish /pacts \
            --consumer-app-version=${{ github.sha }} \
            --broker-base-url=${{ secrets.PACT_BROKER_URL }} \
            --broker-token=${{ secrets.PACT_BROKER_TOKEN }} \
            --tag=${{ github.ref_name }}

      - name: can-i-deploy check
        run: |
          docker run --rm pactfoundation/pact-cli:latest \
            can-i-deploy \
            --pacticipant order-service \
            --version ${{ github.sha }} \
            --to production \
            --broker-base-url=${{ secrets.PACT_BROKER_URL }} \
            --broker-token=${{ secrets.PACT_BROKER_TOKEN }}
```

```yaml
# OpenAPI Contract Testing — Dredd ilə
# OpenAPI spec verify etmək

# dredd.yml
dry-run: false
hookfiles: tests/hooks/dredd_hooks.php
language: php
server: php artisan serve --port=8080
server-wait: 3
reporter: cli
output: []
header: []
user: null
inline-errors: false
details: false
method: []
only: []
color: true
level: info
timestamp: false
silent: false
path:
  - openapi.yaml

# Test run:
# npx dredd openapi.yaml http://localhost:8080
```

### Müqayisə Cədvəli — Contract Testing vs E2E Testing

| Xüsusiyyət | E2E Testing | Contract Testing | OpenAPI Testing |
|-----------|-------------|-----------------|-----------------|
| Service-lər canlı olmalı | Hamısı | Hər biri ayrıca | Provider |
| Test sürəti | Yavaş (dəqiqələr) | Sürətli (saniyələr) | Orta |
| Debug etmək | Çətin | Asandır | Orta |
| Service sayı artdıqca | Çox çətin | Eyni | Eyni |
| Breaking change detection | Production-a yaxın | CI-da erkən | CI-da erkən |
| Business logic test | Bəli | Xeyr (yalnız interface) | Xeyr |
| Consumer-driven | Yox | Bəli | Yox (spec-driven) |
| Ən uyğun olduğu | Full system verify | Interface uyğunluğu | Spec compliance |

## Praktik Tapşırıqlar

1. Pact PHP library install edib kiçik consumer test yaz — mock server qarşısında real client kodu test et.
2. Pact Broker lokal qur (Docker ilə): `docker run pactfoundation/pact-broker`. Pact-ı publish et.
3. Provider state handler yaz — "given user exists", "given user deleted" state-lərini implement et.
4. `can-i-deploy` CLI-ni CI pipeline-a inteqrasiya et — deploy etmədən əvvəl check.
5. OpenAPI spec yaz, Dredd ilə real API-yə qarşı verify et.
6. Mövcud bir E2E test-i contract test ilə əvəz et — nə qazandın? (sürət, maintenance).
7. Provider API-nə breaking change et — consumer pact-ının CI-da fail olduğunu müşahidə et.
8. Pact Broker-in compatibility matrix-ini izlə — hansı version kombinasiyaları uyğundur?

## Əlaqəli Mövzular

- [02-unit-integration-e2e.md](02-unit-integration-e2e.md) — Contract testing vs E2E testing müqayisəsi
- [09-testing-in-cicd.md](09-testing-in-cicd.md) — CI/CD-də contract verification
- [01-cicd-pipeline-design.md](../12-devops/01-cicd-pipeline-design.md) — Pipeline-da contract testing addımı
