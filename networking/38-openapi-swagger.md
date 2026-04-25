# OpenAPI & Swagger (Middle)

## İcmal

**OpenAPI Specification** (OAS) — RESTful API-ları təsvir etmək üçün language-agnostic, machine-readable format. JSON və ya YAML sintaksisdə yazılır. Əvvəlki adı **Swagger Specification** idi. 2015-də Linux Foundation-a verildi və OpenAPI adı aldı. Hazırda **OpenAPI 3.1** aktualdır (JSON Schema Draft 2020-12 ilə uyğun).

**Swagger** — OpenAPI spec-i işləməyə kömək edən toolchain:
- **Swagger UI** — spec-dən interaktiv API sənədləşdirməsi yaradır.
- **Swagger Editor** — brauzerdə real-time validate olunan spec editoru.
- **Swagger Codegen / OpenAPI Generator** — spec-dən client SDK və server stub yaradır.

```
          Contract (spec)
          openapi.yaml
               |
      +--------+--------+
      |                 |
   Server          Client
   (request-ləri   (SDK generasiya olunur,
    spec-ə görə    strongly typed)
    yoxlayır)           |
      |                  |
   Swagger UI      Postman import
   /docs endpoint  Insomnia import
```

## Niyə Vacibdir

OpenAPI tək həqiqət mənbəyidir — backend developer yazır, frontend, mobile, 3rd party consumer oxuyur. Manual sync olmadan parallel iş mümkün olur. Client SDK avtomatik generasiya, mock server, request validation — bunların hamısı spec-dən törənir.

## Əsas Anlayışlar

### OpenAPI 3 Document Strukturu

```yaml
openapi: 3.0.3               # spec versiyası
info:                        # metadata
  title: E-commerce API
  description: Orders and products
  version: 1.2.0
  contact:
    email: api@example.com
servers:                     # base URL-lər
  - url: https://api.example.com/v1
    description: Production
  - url: https://staging-api.example.com/v1
    description: Staging
paths:                       # endpoint-lər
  /orders:
    get:
      summary: List orders
      operationId: listOrders
      parameters:
        - $ref: '#/components/parameters/PageParam'
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/OrderList'
    post:
      summary: Create order
      operationId: createOrder
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateOrder'
      responses:
        '201':
          description: Created
components:                  # yenidən istifadə olunan təriflər
  schemas:
    Order:
      type: object
      required: [id, total]
      properties:
        id:     { type: integer, example: 42 }
        total:  { type: number, format: float, example: 99.50 }
        status:
          type: string
          enum: [pending, paid, shipped, cancelled]
    CreateOrder:
      type: object
      required: [product_id, quantity]
      properties:
        product_id: { type: integer }
        quantity:   { type: integer, minimum: 1 }
  parameters:
    PageParam:
      name: page
      in: query
      schema: { type: integer, default: 1 }
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
security:
  - BearerAuth: []
```

### Contract-First vs Code-First

```
CONTRACT-FIRST (spec-first):
  1. openapi.yaml əvvəl yazılır
  2. Stakeholder-lərlə (frontend, mobile) review
  3. Spec-dən server stub + client SDK generasiya
  4. Server stub-da business logic yazılır
  5. Spec tək həqiqət mənbəyidir

  Üstünlüklər: design-led, parallel iş mümkün, implementation bias yoxdur
  Çatışmazlıqlar: kod spec-dən ayrılırsa sync çətin

CODE-FIRST (annotations / introspection):
  1. Controller/route kodu əvvəl yazılır
  2. Annotation (@OA\Get) və ya framework introspection əlavə olunur
  3. Spec KODDAN generasiya olunur
  4. Generasiya olunmuş spec publish edilir

  Üstünlüklər: spec həmişə koda uyğundur, dublikasiya yoxdur
  Çatışmazlıqlar: spec keyfiyyəti annotation keyfiyyətinə bağlıdır
```

### Security Schemes

```yaml
components:
  securitySchemes:
    # Header-də API key
    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-API-Key

    # JWT bearer
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT

    # OAuth2 authorization code flow
    OAuth2:
      type: oauth2
      flows:
        authorizationCode:
          authorizationUrl: https://auth.example.com/oauth/authorize
          tokenUrl: https://auth.example.com/oauth/token
          scopes:
            read:orders: Read orders
            write:orders: Create/update orders
```

### OpenAPI 3.1 vs 3.0

```
3.0 (2017):  JSON Schema draft-05 subset, bəzi fərqliliklər
3.1 (2021):  Full JSON Schema 2020-12 uyğunluğu
             null type dəstəki: type: ["string", "null"]
             webhooks top-level keyword (async API-lar üçün)
             $ref yanında sibling keyword-lar işləyir
```

### Mocking (Prism)

```bash
# Prism (Stoplight) quraşdır
npm install -g @stoplight/prism-cli

# Spec-dən mock server başlat
prism mock openapi.yaml
# -> http://localhost:4010-da çalışır

curl http://localhost:4010/orders
# Schema-ya uyğun mock Order[] qaytarır
```

### Linting (spectral)

```bash
npm install -g @stoplight/spectral-cli
spectral lint openapi.yaml

# Custom ruleset
spectral lint openapi.yaml --ruleset=.spectral.yaml
```

## Praktik Baxış

- **Tək spec mənbəyi qoru:** Avtogenerasiya və manual yazılmış spec qarışdırma. CI-da drift detection qur.
- **RFC 7807 Problem Details:** Error format üçün `application/problem+json` istifadə et — standardlaşdırılmış, client-friendly.
- **Examples əlavə et:** Hər schema üçün real-looking example — mock server və sənədlər dramatik yaxşılaşır.
- **Swagger UI production-da risk:** Admin, debug endpoint-lərin spec-də görünməsi attack surface yaradır. Public spec yalnız public endpoint-ləri ehtiva etsin.
- **operationId həmişə unikal:** SDK generator bunu method adı kimi istifadə edir.

### Anti-patterns

- `operationId` olmadan spec — SDK generasiya məntiqsiz ad verir
- Error response-ları spec-dən çıxarmaq — consumer-lər nə gözlədiklərini bilmirlər
- Examples-sız spec — mock server random data qaytarır, real-looking deyil
- Hər dəfə spec-i manual yeniləmək — CI-da `spectral lint` + drift detection avtomatlaşdır

## Nümunələr

### Ümumi Nümunə

```
Code generation flow:

openapi.yaml
     |
     +--> openapi-generator-cli generate
            -i openapi.yaml
            -g typescript-axios
            -o ./client-sdk
     |
     +--> ./client-sdk/
              api/
                OrdersApi.ts
                ProductsApi.ts
              models/
                Order.ts
                CreateOrder.ts
              index.ts
```

### Kod Nümunəsi

**Option 1: L5-Swagger (annotations, code-first):**
```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

```php
// app/Http/Controllers/Api/OrderController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.2.0', title: 'E-commerce API')]
#[OA\Server(url: 'https://api.example.com/v1')]
#[OA\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
class OrderController extends Controller
{
    #[OA\Get(
        path: '/orders',
        summary: 'List orders',
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 1),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Order')
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ],
    )]
    public function index()
    {
        return \App\Models\Order::paginate(20);
    }
}
```

```bash
php artisan l5-swagger:generate
# -> storage/api-docs/api-docs.json
# http://localhost:8000/api/documentation
```

**Option 2: Scramble (annotation-sız, zero-config):**
```bash
composer require dedoc/scramble
```

```php
// app/Http/Controllers/Api/ProductController.php
namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * List products with pagination.
     */
    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return ProductResource::collection(Product::paginate(20));
    }

    /**
     * Create a new product.
     */
    public function store(StoreProductRequest $request): ProductResource
    {
        return new ProductResource(Product::create($request->validated()));
    }
}
```

Scramble FormRequest rule-larını OpenAPI schema-ya, Eloquent resource-u response schema-sına çevirir. `/docs/api` ünvanında UI açır. Annotation yazmağa ehtiyac yoxdur.

**Option 3: Contract-First (spec ilə validate):**
```bash
composer require league/openapi-psr7-validator
```

```php
// app/Http/Middleware/ValidateAgainstOpenApi.php
namespace App\Http\Middleware;

use Closure;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class ValidateAgainstOpenApi
{
    public function handle($request, Closure $next)
    {
        $validator = (new ValidatorBuilder)
            ->fromYamlFile(base_path('openapi.yaml'))
            ->getServerRequestValidator();

        $psr17           = new Psr17Factory;
        $psrHttpFactory  = new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);
        $psrRequest      = $psrHttpFactory->createRequest($request);

        $validator->validate($psrRequest); // violation-da exception atır

        return $next($request);
    }
}
```

**TypeScript SDK Generasiyası:**
```bash
npm install -g @openapitools/openapi-generator-cli

openapi-generator-cli generate \
  -i http://localhost:8000/docs/api.json \
  -g typescript-axios \
  -o ./frontend/src/api-client \
  --additional-properties=supportsES6=true,withInterfaces=true
```

```typescript
import { OrdersApi, Configuration } from '@/api-client';

const api = new OrdersApi(new Configuration({
  basePath: import.meta.env.VITE_API_URL,
  accessToken: () => localStorage.getItem('token') ?? '',
}));

const { data } = await api.listOrders({ page: 1 });
```

## Praktik Tapşırıqlar

1. **Scramble qur:** Mövcud Laravel proyektinə `dedoc/scramble` əlavə et, `/docs/api`-yə keç, avtomatik generasiya olunan spec-i nəzərdən keçir.

2. **Schema annotasiya et:** L5-Swagger ilə bir controller-i tam annotasiya et — request body, response, 401, 422 error response-ları.

3. **TypeScript SDK yarat:** `openapi-generator-cli` ilə spec-dən TypeScript SDK generasiya et, frontend proyektdə istifadə et.

4. **Prism mock server:** `prism mock openapi.yaml` ilə mock server başlat, backend olmadan frontend develop et.

5. **Spectral lint:** `.spectral.yaml` ruleset yaz — "hər endpoint-in `operationId`-i olmalıdır", "hər endpoint 4xx response olmalıdır" qaydasını əlavə et. CI-da çalışdır.

6. **Contract-first API dizayn et:** Yeni endpoint üçün əvvəl `openapi.yaml`-a yaz, stakeholder-lərlə review et, sonra kodu yaz. Frontend Prism mock-undan istifadə etsin.

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [gRPC](10-grpc.md)
- [Protocol Buffers](39-protocol-buffers.md)
- [API Versioning](22-api-versioning.md)
- [API Testing Tools](40-api-testing-tools.md)
