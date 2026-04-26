# Testing Microservices (Lead)
## İcmal

Microservice testing, distributed system-lərdəki müstəqil service-lərin həm ayrı-ayrılıqda,
həm də birlikdə düzgün işlədiyini yoxlamaq prosesidir. Monolith-dən fərqli olaraq, microservice
arxitekturada hər service müstəqil deploy olunur, öz database-i var və network üzərindən
kommunikasiya edir. Bu, testing-i xeyli mürəkkəbləşdirir.

Əsas çətinliklər: network failure, latency, eventual consistency, service versioning və
distributed debugging. Testing strategiyası bu çətinlikləri nəzərə almalıdır.

### Niyə Microservice Testing Mürəkkəbdir?

1. **Distributed system** - Service-lər network üzərindən danışır, latency var
2. **Independent deployment** - Hər service müstəqil dəyişə bilər
3. **Data consistency** - Eventual consistency, distributed transactions
4. **Environment complexity** - Bütün service-ləri local-da qaldırmaq çətindir
5. **Failure modes** - Partial failure, cascade failure, timeout

## Niyə Vacibdir

- **Distributed system kompleksliyi**: Mikroservislərdə bug-lar service boundary-lərini keçir — traditional unit test kifayət etmir
- **Contract drift**: İki servis arasında API kontraktı gizli şəkildə dəyişə bilər — consumer-driven contract test bu problemi erkən tapır
- **Network failure**: Mikroservis mühitində partial failure normaldır — retry, circuit breaker davranışı test edilməlidir
- **Independent deployment**: Servislərin müstəqil deploy edilməsi üçün test suite müstəqilliyi vacibdir

## Əsas Anlayışlar

### Microservice Testing Strategiyası

```
Testing Honeycomb (əsas microservice strategiyası):

          /  E2E  \               ← Çox az (smoke testlər)
         /----------\
        / Integration \           ← Ən çox (service boundaries)
       /----------------\
      /   Unit Tests      \       ← Orta (business logic)
     /______________________\

Monolith Piramida:                Microservice Honeycomb:
  Çox Unit                         Çox Integration
  Orta Integration                 Orta Unit
  Az E2E                           Çox az E2E
```

### Service Virtualization

```
Service Virtualization: Real service əvəzinə virtual/mock service istifadəsi

User Service ← test edirik
  ↓
Payment Service (virtual) ← Mock/stub server
  ↓
Email Service (virtual) ← Mock/stub server

Alətlər:
  - WireMock (HTTP mock server)
  - Mountebank (multi-protocol)
  - Hoverfly (proxy-based)
  - Laravel Http::fake() (in-process)
```

### Test Types for Microservices

```
1. Unit Tests (service-daxili)
   → Business logic
   → Domain models
   → Helper functions

2. Component Tests (service level)
   → Single service + its database
   → External dependencies mocked
   → API endpoint testing

3. Contract Tests
   → Consumer-driven contracts
   → API compatibility
   → Schema validation

4. Integration Tests (service arası)
   → Real service-lər arası
   → Database + queue + cache
   → Staging environment-də

5. E2E Tests (minimal)
   → Critical user journeys
   → Smoke tests
   → Staging/production-da
```

## Praktik Baxış

### Best Practices

1. **Honeycomb strategiya** - Integration testlərə fokuslanın
2. **Contract testing istifadə edin** - Service arası compatibility
3. **Service virtualization** - Xarici service-ləri mock-layın
4. **Circuit breaker test edin** - Failure resilience yoxlayın
5. **Chaos testing tətbiq edin** - Random failure scenario-ları
6. **Docker-based test mühiti** - Reproducible test environment

### Anti-Patterns

1. **Hər şeyi E2E ilə test etmək** - Çox yavaş və flaky
2. **Service-ləri birlikdə deploy etmək** - Müstəqilliyi pozur
3. **Shared test database** - Hər service öz DB-sini istifadə etsin
4. **Network failure-ı ignore etmək** - Timeout, retry test edin
5. **Synchronous communication-a güvənmək** - Async pattern-ləri test edin
6. **Production-da test etməmək** - Smoke test / canary deploy lazımdır

## Nümunələr

### Component Test (Single Service)

```php
<?php

namespace Tests\Component;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderServiceComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Xarici service-ləri mock-la
        Http::fake([
            'payment-service.internal/api/*' => Http::response([
                'status' => 'success',
                'transaction_id' => 'txn_123',
            ], 200),

            'inventory-service.internal/api/*' => Http::response([
                'available' => true,
                'quantity' => 10,
            ], 200),

            'notification-service.internal/api/*' => Http::response([], 202),
        ]);
    }

    /** @test */
    public function it_creates_order_and_communicates_with_services(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [
                    ['product_id' => 'prod_1', 'quantity' => 2],
                ],
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'status' => 'confirmed',
                    'transaction_id' => 'txn_123',
                ],
            ]);

        // Verify service calls
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'payment-service');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory-service');
        });

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);
    }

    /** @test */
    public function it_handles_payment_service_failure(): void
    {
        Http::fake([
            'payment-service.internal/api/*' => Http::response([
                'error' => 'Insufficient funds',
            ], 402),
            'inventory-service.internal/api/*' => Http::response([
                'available' => true,
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => 'prod_1', 'quantity' => 1]],
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Payment failed']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'payment_failed',
        ]);
    }

    /** @test */
    public function it_handles_inventory_service_timeout(): void
    {
        Http::fake([
            'inventory-service.internal/api/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            },
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'items' => [['product_id' => 'prod_1', 'quantity' => 1]],
            ]);

        $response->assertStatus(503)
            ->assertJson(['message' => 'Service temporarily unavailable']);
    }
}
```

### Circuit Breaker Testing

```php
<?php

namespace Tests\Component;

use App\Services\CircuitBreaker;
use App\Services\PaymentClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    /** @test */
    public function circuit_opens_after_consecutive_failures(): void
    {
        Cache::flush();

        Http::fake([
            'payment-service.internal/*' => Http::response([], 500),
        ]);

        $client = app(PaymentClient::class);
        $breaker = app(CircuitBreaker::class);

        // 5 ardıcıl failure
        for ($i = 0; $i < 5; $i++) {
            try {
                $client->charge(100);
            } catch (\Exception $e) {
                // expected
            }
        }

        // Circuit açıq olmalıdır
        $this->assertTrue($breaker->isOpen('payment-service'));

        // Əlavə request göndərilməməlidir
        $this->expectException(\App\Exceptions\CircuitBreakerOpenException::class);
        $client->charge(100);
    }

    /** @test */
    public function circuit_resets_after_cooldown_period(): void
    {
        Cache::flush();
        $breaker = app(CircuitBreaker::class);

        // Circuit-i aç
        $breaker->recordFailure('payment-service');
        $breaker->recordFailure('payment-service');
        $breaker->recordFailure('payment-service');
        $breaker->recordFailure('payment-service');
        $breaker->recordFailure('payment-service');

        $this->assertTrue($breaker->isOpen('payment-service'));

        // Cooldown müddəti keçdikdən sonra
        $this->travel(61)->seconds();

        // Half-open state - bir request buraxır
        $this->assertFalse($breaker->isOpen('payment-service'));
    }
}
```

### Retry Pattern Testing

```php
<?php

namespace Tests\Component;

use App\Services\ExternalApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetryPatternTest extends TestCase
{
    /** @test */
    public function it_retries_on_temporary_failure(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return Http::response([], 503);
            }
            return Http::response(['data' => 'success'], 200);
        });

        $client = app(ExternalApiClient::class);
        $result = $client->fetchData();

        $this->assertEquals('success', $result['data']);
        $this->assertEquals(3, $callCount); // 2 retry + 1 success
    }

    /** @test */
    public function it_gives_up_after_max_retries(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        $client = app(ExternalApiClient::class);

        $this->expectException(\App\Exceptions\ServiceUnavailableException::class);
        $client->fetchData(); // 3 retry-dan sonra give up
    }
}
```

## Praktik Tapşırıqlar

### Saga Pattern Testing

```php
<?php

namespace Tests\Component;

use App\Sagas\OrderSaga;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderSagaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function successful_saga_completes_all_steps(): void
    {
        Http::fake([
            'inventory-service/*' => Http::response(['reserved' => true]),
            'payment-service/*' => Http::response(['charged' => true, 'txn' => 'tx_1']),
            'shipping-service/*' => Http::response(['tracking' => 'TRACK123']),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $saga = app(OrderSaga::class);
        $result = $saga->execute($order);

        $this->assertTrue($result->isSuccessful());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function failed_payment_triggers_compensation(): void
    {
        Http::fake([
            'inventory-service/api/reserve' => Http::response(['reserved' => true]),
            'payment-service/*' => Http::response(['error' => 'declined'], 402),
            // Compensation: inventory release
            'inventory-service/api/release' => Http::response(['released' => true]),
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $saga = app(OrderSaga::class);
        $result = $saga->execute($order);

        $this->assertFalse($result->isSuccessful());

        // Inventory release çağırılmalıdır (compensation)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'inventory-service/api/release');
        });

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'failed',
        ]);
    }
}
```

### Docker-based Integration Test

```yaml
# docker-compose.test.yml
version: '3.8'
services:
  order-service:
    build: ./order-service
    environment:
      - DB_HOST=order-db
      - PAYMENT_SERVICE_URL=http://payment-service:8000
    depends_on:
      - order-db
      - payment-service

  payment-service:
    build: ./payment-service
    environment:
      - DB_HOST=payment-db
    depends_on:
      - payment-db

  order-db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: orders_test

  payment-db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: payments_test
```

```bash
# Integration testləri Docker ilə işlət
docker-compose -f docker-compose.test.yml up -d
docker-compose -f docker-compose.test.yml exec order-service php artisan test --testsuite=Integration
docker-compose -f docker-compose.test.yml down
```

### Chaos Testing (Resilience Testing)

```php
<?php

namespace Tests\Chaos;

use App\Services\OrderService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResilienceTest extends TestCase
{
    /** @test */
    public function service_handles_random_latency(): void
    {
        Http::fake(function ($request) {
            // Random 100ms-3000ms latency
            usleep(random_int(100, 3000) * 1000);
            return Http::response(['ok' => true], 200);
        });

        $service = app(OrderService::class);

        // Timeout 5 saniyə, yəni keçməlidir
        $result = $service->checkInventory('prod_1');
        $this->assertTrue($result);
    }

    /** @test */
    public function service_handles_intermittent_failures(): void
    {
        $requestCount = 0;
        Http::fake(function () use (&$requestCount) {
            $requestCount++;
            // Hər 3-cü request fail olur
            if ($requestCount % 3 === 0) {
                return Http::response([], 500);
            }
            return Http::response(['ok' => true], 200);
        });

        $service = app(OrderService::class);

        // 10 request göndər, ən azı 7-si keçməlidir
        $successCount = 0;
        for ($i = 0; $i < 10; $i++) {
            try {
                $service->checkInventory('prod_1');
                $successCount++;
            } catch (\Exception $e) {
                // expected for some requests
            }
        }

        $this->assertGreaterThanOrEqual(7, $successCount);
    }
}
```

## Ətraflı Qeydlər

### 1. Microservice testing monolith testing-dən nə ilə fərqlənir?
**Cavab:** Monolith-də bütün kod bir process-dədir, function call ilə əlaqə qurulur. Microservice-lərdə network call var, latency, partial failure mümkündür. Microservice-lərdə contract testing, service virtualization, chaos testing lazımdır. Testing honeycomb (integration-focused) əvəzinə piramida (unit-focused) istifadə olunur.

### 2. Service virtualization nədir?
**Cavab:** Real service əvəzinə mock/stub server istifadəsidir. Test zamanı Payment Service real deyil, virtual service-dən cavab alır. Bu testləri sürətli, deterministic və izole edir. WireMock, Mountebank kimi alətlər, Laravel-da Http::fake() istifadə olunur. Fərqli response scenario-ları test edilə bilər.

### 3. Circuit breaker pattern nədir?
**Cavab:** Service failure-dan sonra müəyyən müddət request göndərməyi dayandıran pattern. Closed (normal) → Open (fail-dan sonra blokla) → Half-Open (bir request burax, yoxla). Cascade failure-ın qarşısını alır. Testdə: ardıcıl failure-dan sonra circuit-in açıldığını, cooldown-dan sonra bağlandığını yoxlayırıq.

### 4. Saga pattern testing necə həyata keçirilir?
**Cavab:** Saga distributed transaction-dır. Hər addım uğurlu olmalı, failure halında compensation (geri qaytarma) işləməlidir. Test: 1) Bütün addımlar uğurlu - saga tamamlanır, 2) Ortada failure - əvvəlki addımların compensation-ı işləyir. Http::fake ilə hər service-in cavabı kontrol edilir.

### 5. Chaos testing nədir?
**Cavab:** Distributed system-in xaos şəraitində necə davrandığını yoxlamaq. Random latency, intermittent failure, network partition simulyasiya edilir. Netflix-in Chaos Monkey-si ən məşhur nümunədir. Məqsəd: sistemin graceful degrade etdiyini, total crash olmadığını təmin etmək.

### 6. Microservice E2E testləri niyə minimum olmalıdır?
**Cavab:** Çox yavaşdır (bütün service-lər qalxmalı), flaky-dir (network, timing), expensive-dir (environment), bakımı çətindir. Əvəzinə contract testing + component testing daha effektivdir. E2E yalnız kritik user journey-lər üçün - login, checkout, payment kimi core flow-lar.

## Əlaqəli Mövzular

- [Contract Testing (Senior)](24-contract-testing.md)
- [Testing Third-Party Integrations (Senior)](28-testing-third-party.md)
- [Test Environment Management (Lead)](40-test-environment-management.md)
- [Property-Based Testing (Lead)](38-property-based-testing.md)
- [Continuous Testing (Senior)](23-continuous-testing.md)
