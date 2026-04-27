# Contract-First API Design (Senior)

## Mündəricat
1. [Spec-First vs Code-First](#spec-first-vs-code-first)
2. [OpenAPI 3.0 Strukturu](#openapi-30-strukturu)
3. [Consumer-Driven Contracts (Pact)](#consumer-driven-contracts-pact)
4. [API Mocking from Spec](#api-mocking-from-spec)
5. [PHP İmplementasiyası](#php-implementasiyası)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Spec-First vs Code-First

```
Code-First (ənənəvi):
  1. Kodu yaz
  2. Swagger annotasiyaları əlavə et
  3. Spec generate et

  ┌─────────┐  annotate  ┌──────────┐  generate  ┌──────────┐
  │  Code   │───────────►│ Swagger  │───────────►│   Spec   │
  └─────────┘            └──────────┘            └──────────┘

  Problemlər:
  - Spesifikasiya koda tabe → inconsistency
  - Frontend spec-i gözləyir → paralel iş mümkün deyil
  - Spec implementation detallarını əks etdirir
  - Breaking change-lər gec aşkarlanır

Spec-First (Contract-First):
  1. Əvvəlcə API spec yaz (OpenAPI)
  2. Spec-dən mock server qur (paralel işlə)
  3. Spec-ə uyğun kod yaz
  4. Spec-lə kodu validate et

  ┌──────────┐  mock      ┌────────────┐
  │   Spec   │───────────►│ Mock Server│ ← Frontend bunla işləyir
  │ (OpenAPI)│            └────────────┘
  │          │  generate  ┌────────────┐
  │          │───────────►│ Server SDK │ ← Backend bundan implementasiya
  └──────────┘            └────────────┘
  
  Faydaları:
  + Frontend + Backend paralel işləyir
  + API design review (implementasiyadan əvvəl)
  + Breaking change-lər erkən aşkarlanır
  + Consumer-lar spec-ə bağlıdır
```

---

## OpenAPI 3.0 Strukturu

```yaml
openapi: 3.0.3
info:
  title: Order API
  version: 1.0.0

paths:
  /orders:
    post:
      summary: Sifariş yarat
      operationId: createOrder
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateOrderRequest'
      responses:
        '201':
          description: Sifariş yaradıldı
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Order'
        '422':
          description: Validation xətası
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ValidationError'

  /orders/{id}:
    get:
      summary: Sifarişi gətir
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Order'
        '404':
          description: Tapılmadı

components:
  schemas:
    CreateOrderRequest:
      type: object
      required: [customer_id, items]
      properties:
        customer_id:
          type: integer
        items:
          type: array
          items:
            $ref: '#/components/schemas/OrderItem'

    Order:
      type: object
      properties:
        id:
          type: integer
        status:
          type: string
          enum: [pending, confirmed, shipped, delivered]
        total:
          type: number
          format: float
```

---

## Consumer-Driven Contracts (Pact)

```
Consumer-Driven Contracts:
  Consumer (Frontend/Mobile) yazır: "Mən belə cavab gözləyirəm"
  Provider (Backend) yoxlayır: "Mənim cavabım bu gözləntiləri qarşılayırmı?"

Pact framework:
  Consumer test → Pact file (JSON contract) generate
  Provider verification → Contract-a uyğun cavab verir?

  ┌──────────┐  test  ┌───────────┐  publish  ┌──────────────┐
  │Consumer  │───────►│Pact Mock  │──────────►│ Pact Broker  │
  │(Frontend)│        │  Server   │           └──────┬───────┘
  └──────────┘        └───────────┘                  │ verify
                                             ┌────────▼────────┐
                                             │  Provider Tests  │
                                             │  (Backend)       │
                                             └────────────────--┘

Pact contract nümunəsi:
  "GET /orders/42 gəldikdə:
   - status code: 200
   - body.id: 42
   - body.status: 'pending' (string olmalı)
   - body.total: number olmalı"

  Provider bu contractı CI-da verify edir.
  Contract fail → CI pipeline stops!

Şəbəkə mock-u:
  Consumer test-lərində real API çağırışı yoxdur.
  Pact mock server cavab verir.
  → Sürətli, deterministic, isolated test.
```

---

## API Mocking from Spec

```
OpenAPI spec → Mock server → Frontend paralel işləyir

Alətlər:
  Prism (Stoplight): spec-dən HTTP mock server
  WireMock: Java-based, güclü
  Mockoon: GUI ilə mock

Prism nümunəsi:
  npx @stoplight/prism-cli mock openapi.yaml

  GET http://localhost:4010/orders/1
  → Spec-dəki example-dan cavab qaytarır

  POST http://localhost:4010/orders
  body: {invalid}
  → Spec validation xətası qaytarır (real backend kimi!)

Spec-dən faydalar:
  Frontend öz işini mock ilə davam etdirir.
  Backend implementasiyası parallel.
  Deploy gününə qədər integration test mümkündür.
```

---

## PHP İmplementasiyası

```php
<?php
// OpenAPI spec-dən request validation middleware (league/openapi-psr7-validator)
use League\OpenAPIValidation\PSR7\ValidatorBuilder;

class OpenApiValidationMiddleware
{
    private \League\OpenAPIValidation\PSR7\RequestValidator $validator;

    public function __construct(string $specPath)
    {
        $this->validator = (new ValidatorBuilder)
            ->fromYamlFile($specPath)
            ->getRequestValidator();
    }

    public function handle(Request $request, callable $next): Response
    {
        try {
            $this->validator->validate($this->toPsr7($request));
        } catch (\League\OpenAPIValidation\PSR7\Exception\ValidationFailed $e) {
            return response()->json([
                'error'   => 'Request validation failed',
                'details' => $e->getMessage(),
            ], 422);
        }

        return $next($request);
    }
}
```

```php
<?php
// Response validation (development-da)
class OpenApiResponseValidationMiddleware
{
    private \League\OpenAPIValidation\PSR7\ResponseValidator $validator;

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (app()->environment('local', 'testing')) {
            try {
                $operation = new \League\OpenAPIValidation\PSR7\OperationAddress(
                    $request->path(),
                    strtolower($request->method())
                );
                $this->validator->validate($operation, $this->toPsr7($response));
            } catch (\Exception $e) {
                // Response spec-ə uyğun deyil — development-da xəbərdarlıq
                logger()->warning('Response does not match OpenAPI spec', [
                    'path'  => $request->path(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}
```

```php
<?php
// Contract test (Consumer side — Pact PHP)
// use PhpPact\Consumer\InteractionBuilder;

class OrderApiConsumerTest extends TestCase
{
    public function test_get_order_returns_expected_structure(): void
    {
        // Consumer "bu cavabı gözləyirəm" deyir
        $this->builder
            ->given('Order 42 mövcuddur')
            ->uponReceiving('GET /orders/42 sorğusu')
            ->with([
                'method' => 'GET',
                'path'   => '/orders/42',
            ])
            ->willRespondWith([
                'status'  => 200,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => [
                    'id'     => 42,
                    'status' => Matchers::like('pending'), // string olmalı
                    'total'  => Matchers::decimal(100.00), // decimal olmalı
                ],
            ]);

        // Real API call (Pact mock server-ə)
        $order = $this->orderClient->getOrder(42);

        $this->assertEquals(42, $order->getId());
        $this->assertIsString($order->getStatus());
    }
}
```

---

## İntervyu Sualları

- Spec-first vs code-first — hər birinin üstünlük/çatışmazlıqları?
- Consumer-driven contracts nədir? Kim contract-ı yazır?
- OpenAPI spec-dən mock server-in frontend komandaya faydası nədir?
- Pact broker nə rolu oynayır?
- API spec validation request-də yoxsa response-da daha vacibdir?
- Breaking change-i spec-first yanaşmada kod-first-dən niyə daha erkən aşkarlayırsınız?
- `$ref` OpenAPI-da nə üçün istifadə edilir?
