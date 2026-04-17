# OpenAPI və Swagger

## Nədir? (What is it?)

**OpenAPI Specification** (OAS) — RESTful API-ları təsvir etmək üçün language-agnostic, machine-readable format. JSON və ya YAML sintaksisdə yazılır. Əvvəlki adı **Swagger Specification** idi (SmartBear tərəfindən). 2015-də Linux Foundation-a verildi və OpenAPI adı aldı. Hazırda **OpenAPI 3.1** aktualdır (JSON Schema Draft 2020-12 ilə uyğun).

**Swagger** — OpenAPI spec-i işləməyə kömək edən toolchain:
- **Swagger UI** — spec-dən interaktiv API dokumentasiyası generasiya edir.
- **Swagger Editor** — brauzer-də real-time validate olunan spec editoru.
- **Swagger Codegen / OpenAPI Generator** — spec-dən client SDK və server stub generasiya edir.

```
          Contract (spec)
          openapi.yaml
               |
      +--------+--------+
      |                 |
   Server          Client
   (validates       (SDK generated,
    requests        strongly typed)
    against spec)        |
      |                  |
   Swagger UI      Postman import
   /docs endpoint  Insomnia import
```

**Nə üçün lazımdır?**
1. **Human docs:** backend developer yazır, frontend/consumer oxuyur — tək həqiqət mənbəyi.
2. **Machine automation:** Postman collection, Insomnia, client SDK otomatik yaradılır.
3. **Contract-first development:** API dizaynı kod yazılmadan razılaşdırılır.
4. **Validation:** server spec ilə gələn request-ləri yoxlaya bilər.
5. **Mocking:** tam implement olmamış API spec-dən mock server qurula bilər (Prism, Stoplight).

## Necə İşləyir? (How does it work?)

### 1. OpenAPI 3 Document Structure

```yaml
openapi: 3.0.3               # spec version
info:                        # metadata
  title: E-commerce API
  description: Orders and products
  version: 1.2.0
  contact:
    email: api@example.com
servers:                     # base URLs
  - url: https://api.example.com/v1
    description: Production
  - url: https://staging-api.example.com/v1
    description: Staging
paths:                       # endpoints
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
components:                  # reusable definitions
  schemas:
    Order:
      type: object
      required: [id, total]
      properties:
        id: { type: integer, example: 42 }
        total: { type: number, format: float, example: 99.50 }
        status:
          type: string
          enum: [pending, paid, shipped, cancelled]
    CreateOrder:
      type: object
      required: [product_id, quantity]
      properties:
        product_id: { type: integer }
        quantity: { type: integer, minimum: 1 }
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

### 2. Swagger UI Rendering

```
openapi.yaml  -->  Swagger UI  -->  https://api.example.com/docs
                     (renders spec as interactive HTML)

Features in UI:
  - Expand each endpoint
  - View request/response schema
  - "Try it out" button -> executes real request
  - Authorize button for Bearer/API key input
  - Download spec as JSON/YAML
```

### 3. Code Generation Flow

```
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

Generated client usage:

```typescript
import { OrdersApi, Configuration } from './client-sdk';

const api = new OrdersApi(new Configuration({
  basePath: 'https://api.example.com/v1',
  accessToken: 'jwt-token',
}));

const response = await api.createOrder({
  product_id: 10,
  quantity: 2,
});
```

## Əsas Konseptlər (Key Concepts)

### Contract-First vs Code-First

```
CONTRACT-FIRST (spec-first):
  1. Write openapi.yaml FIRST
  2. Review with stakeholders (frontend, mobile, integrators)
  3. Generate server stub + client SDK from spec
  4. Implement business logic in server stub
  5. Spec is single source of truth

  Pros: design-led, no implementation bias, parallel work possible
  Cons: requires discipline, harder to sync if code drifts

CODE-FIRST (annotations / introspection):
  1. Write controller/route code first
  2. Add annotations (@OA\Get, @OpenApi\Route) or let framework introspect
  3. Spec is GENERATED from code
  4. Publish generated spec

  Pros: spec always matches code, less duplication
  Cons: spec quality = annotation discipline, often underspecified
```

### Components (Reusable Definitions)

```yaml
components:
  schemas:          # shared data models (Order, User)
  parameters:       # shared parameters (pagination, filters)
  requestBodies:    # shared request body shapes
  responses:        # shared response definitions (NotFound, Unauthorized)
  headers:          # response headers
  examples:         # example payloads
  links:            # HATEOAS-style links
  callbacks:        # webhook callback definitions
  securitySchemes:  # auth configs (apiKey, http bearer, oauth2, openIdConnect)
```

Example reusable 404:

```yaml
components:
  responses:
    NotFound:
      description: Resource not found
      content:
        application/problem+json:
          schema:
            $ref: '#/components/schemas/Problem'

paths:
  /orders/{id}:
    get:
      responses:
        '200': { description: OK }
        '404': { $ref: '#/components/responses/NotFound' }
```

### Security Schemes

```yaml
components:
  securitySchemes:
    # API key in header
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

security:
  - BearerAuth: []

paths:
  /orders:
    get:
      security:
        - OAuth2: [read:orders]
```

### Mocking and Prism

```bash
# Install Prism (Stoplight)
npm install -g @stoplight/prism-cli

# Start mock server from spec
prism mock openapi.yaml
# -> runs on http://localhost:4010

# Requests return example values from spec OR generated ones
curl http://localhost:4010/orders
# returns mock Order[] matching schema
```

### Validation with spectral

```bash
npm install -g @stoplight/spectral-cli
spectral lint openapi.yaml

# Custom ruleset
spectral lint openapi.yaml --ruleset=.spectral.yaml
```

### OpenAPI 3.1 vs 3.0

```
3.0 (2017):  JSON Schema draft-05 subset, some quirks
3.1 (2021):  Full JSON Schema 2020-12 compatibility
             Supports null type (`type: ["string", "null"]`)
             webhooks top-level keyword (for async APIs)
             $ref allows sibling keywords
```

## PHP/Laravel ilə İstifadə

### Option 1: L5-Swagger (annotations, code-first)

L5-Swagger `darkaonline/l5-swagger` paketi `swagger-php` istifadə edir (`zircote/swagger-php`), PHP attribute-lərindən spec yaradır.

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
```

Controller annotations (PHP 8 attributes):

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

    #[OA\Post(
        path: '/orders',
        summary: 'Create order',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateOrder'),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(\App\Http\Requests\StoreOrderRequest $request)
    {
        return \App\Models\Order::create($request->validated());
    }
}
```

Schema definition as separate class:

```php
// app/OpenApi/Schemas/Order.php
namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Order',
    required: ['id', 'total'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'total', type: 'number', format: 'float', example: 99.5),
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: ['pending', 'paid', 'shipped', 'cancelled'],
        ),
    ],
)]
class Order {}

#[OA\Schema(
    schema: 'CreateOrder',
    required: ['product_id', 'quantity'],
    properties: [
        new OA\Property(property: 'product_id', type: 'integer'),
        new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
    ],
)]
class CreateOrder {}
```

Generate + serve docs:

```bash
php artisan l5-swagger:generate
# -> storage/api-docs/api-docs.json

# Visit http://localhost:8000/api/documentation
```

### Option 2: Scramble (automatic, zero-annotation)

Scramble (`dedoc/scramble`) controller code-unu introspect edir — type-hint, return type, FormRequest rule-larından spec-i avtomatik yaradır. Annotations yazmağa ehtiyac qalmır.

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
     *
     * Returns paginated list of products.
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

// app/Http/Requests/StoreProductRequest.php
class StoreProductRequest extends \Illuminate\Foundation\Http\FormRequest
{
    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'sku'   => 'required|string|unique:products,sku',
        ];
    }
}
```

Scramble FormRequest rule-larını OpenAPI schema-ya çevirir, Eloquent resource-u response schema-sına çevirir. `/docs/api` ünvanında UI açır.

### Option 3: Contract-First (spec-first) in Laravel

`openapi.yaml` spec-i əvvəl yaz, sonra server-i spec-lə validate et.

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

        $psr17 = new Psr17Factory;
        $psrHttpFactory = new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);
        $psrRequest = $psrHttpFactory->createRequest($request);

        $validator->validate($psrRequest); // throws on violation

        return $next($request);
    }
}
```

### Generate TypeScript SDK for Frontend

```bash
npm install -g @openapitools/openapi-generator-cli

openapi-generator-cli generate \
  -i http://localhost:8000/docs/api.json \
  -g typescript-axios \
  -o ./frontend/src/api-client \
  --additional-properties=supportsES6=true,withInterfaces=true
```

Frontend usage:

```typescript
import { OrdersApi, Configuration } from '@/api-client';

const api = new OrdersApi(new Configuration({
  basePath: import.meta.env.VITE_API_URL,
  accessToken: () => localStorage.getItem('token') ?? '',
}));

const { data } = await api.listOrders({ page: 1 });
```

## Interview Sualları (Q&A)

### 1. OpenAPI və Swagger arasında fərq nədir?

**Cavab:** "Swagger" əvvəllər həm spec-in, həm də tool-ların adı idi (SmartBear). 2015-də spec Linux Foundation-a verildi və **OpenAPI Specification** adlandırıldı. İndi: **OpenAPI = spec format** (3.0, 3.1), **Swagger = tool ailəsi** (Swagger UI, Swagger Editor, Swagger Codegen). OpenAPI 3-un redaktəsi SmartBear-dən kənardadır.

### 2. Contract-first və code-first yanaşmalarının fərqi nədir?

**Cavab:**
- **Contract-first:** spec əvvəl yazılır, sonra kod generasiya edilir/yazılır. Üstünlük: paralel iş (frontend mock ilə başlayır), design-led. Mənfi: iki yerdə saxlamaq lazımdır.
- **Code-first:** kod yazılır (annotations ilə), spec avtomatik generasiya edilir. Üstünlük: tək mənbə. Mənfi: spec keyfiyyəti annotation keyfiyyətinə bağlıdır; design bəzən kod reallığına yenilir.
Hansı seçim? B2B/enterprise API-da contract-first, sürətli internal API-də code-first tipik seçimdir.

### 3. L5-Swagger və Scramble fərqləri?

**Cavab:** İkisi də code-first.
- **L5-Swagger (darkaonline/l5-swagger):** `zircote/swagger-php` üzərində qurulur. PHP attribute və ya DocBlock-lar ilə spec yazırsınız — hər endpoint üçün aydın amma təkrarlanan annotations.
- **Scramble (dedoc/scramble):** Laravel-specific introspection. FormRequest rule-ları, return type-ları, Eloquent resource-ları analiz edir. Sıfır annotation, yüksək DX. Amma mürəkkəb hallarda customization limitli.
Scramble kiçik/orta layihə üçün, L5-Swagger isə yüksək kontrollu enterprise üçün.

### 4. OpenAPI-dən client SDK necə generasiya edilir?

**Cavab:** `openapi-generator-cli` və ya `swagger-codegen` istifadə olunur. 50+ dil üçün generator var: TypeScript, Python, Java, Swift, Kotlin, Go, Ruby. Nümunə:
```bash
openapi-generator-cli generate -i openapi.yaml -g typescript-axios -o ./sdk
```
CI-da avtomatlaşdırılır: hər spec dəyişikliyində SDK yenilənir və npm paket kimi publish olunur. Bu sayədə frontend komandası type-safe API çağırışları yazır.

### 5. OpenAPI 3.0 və 3.1 arasında fərq nədir?

**Cavab:** 3.1 (2021 Feb) ən vacib dəyişiklik — **JSON Schema 2020-12 ilə tam uyğunluq**. Bunun nəticəsi:
- `nullable: true` əvəzinə `type: ["string", "null"]` dəstəklənir.
- `webhooks` top-level keyword əlavə olundu (pure event-driven API-lar).
- `$ref` yanında digər keyword-lar işlədilə bilər.
- `examples` çoxlu nümunəyə icazə verir.
Tool dəstəyi hələ də 3.0-a meyllidir (2026-da çox tool 3.1-i dəstəkləyir amma default 3.0 qalır).

### 6. Swagger UI "Try it out" xüsusiyyəti necə işləyir?

**Cavab:** Swagger UI tam JavaScript SPA-dır. User "Try it out" düyməsini basdıqda, UI real brauzer `fetch()` ilə API-ya sorğu göndərir (spec-dən götürülmüş `servers.url` istifadə edir). Response olduğu kimi göstərilir. Məhdudiyyətlər: (1) CORS-un açıq olması lazımdır (brauzerdən birbaşa sorğu), (2) Bearer/API key manual doldurulmalıdır ("Authorize" düyməsi ilə). Production-da bəzən "Try it out" söndürülür, yalnız doc kimi qalır.

### 7. Laravel-də contract-first approach-u necə tətbiq edərdiniz?

**Cavab:**
1. Repo-da `openapi.yaml` əsas həqiqət kimi saxla.
2. `league/openapi-psr7-validator` ilə middleware qur — hər request və response spec-ə uyğunluğunu yoxlasın (development/testing-də).
3. Stoplight Prism ilə mock server — frontend backend hazır olmadan çalışsın.
4. CI-da `spectral lint` — spec style və quality yoxla.
5. Client SDK-nı `openapi-generator` ilə avtomatik generasiya et.
Bu yanaşma əlavə iş tələb edir amma API-nin "nə olduğunu" "necə işlədiyindən" ayırır.

### 8. OpenAPI-də authentication necə təsvir olunur?

**Cavab:** `components.securitySchemes` altında təyin olunur və sonra global və ya endpoint səviyyəsində `security` açarı ilə istinad edilir. Dəstəklənən növlər: `apiKey` (header/query/cookie), `http` (basic/bearer), `oauth2` (bütün flow-lar), `openIdConnect`, `mutualTLS` (3.1+). Nümunə:
```yaml
components:
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
security:
  - BearerAuth: []
```

### 9. OpenAPI spec-inin kriterini necə ölçərsiniz?

**Cavab:**
- **Completeness:** hər endpoint üçün success + error response, hər parametr üçün schema.
- **Consistency:** eyni resource üçün eyni field adı, eyni pagination, eyni error format (məsələn RFC 7807 `application/problem+json`).
- **Realistic examples:** hər schema üçün `example` və ya `examples` dəyəri — mock server real-looking data qaytarsın.
- **Reusability:** `components.schemas` istifadəsi, təkrardan qaçınma.
- **Spectral linting:** `operation-operationId`, `no-unused-component`, `info-contact` rule-ları işləsin.
- **Versioning strategy:** semver ilə `info.version`, breaking change-də major bump.

### 10. Swagger UI-nı production-da publish etməyin riskləri nədir?

**Cavab:** (1) **Internal endpoint exposure:** admin, debug, internal service endpoint-ləri spec-də olsa, potensial attack surface. Həll: private spec internal docs üçün, public spec yalnız təsdiqlənmiş endpoint-lər üçün. (2) **Rate limit bypass:** "Try it out" düyməsi bot-ları stimullaşdıra bilər. Həll: anonymous-a rate limit qoy. (3) **Credential leak:** user-lər öz token-larını Swagger UI-da yaza bilər və brauzer cache-də qalar. Həll: ephemeral test tokens. (4) **Schema disclosure:** data model-iniz açılır. Həll: yalnız public-facing fields göstər; internal flag-lər `x-internal: true` ilə gizlədilir.

## Best Practices

1. **Tək spec mənbəyi qoru** — avtogenerasiya və manual yazılmış spec qarışdırmasın. CI-da drift detection işləsin.
2. **Reusable components yaz** — `Pagination`, `Problem`, `Timestamp` kimi ümumi struktur-lar `components/schemas` altında. DRY principle spec-ə də aiddir.
3. **RFC 7807 Problem Details istifadə et error format üçün** — `application/problem+json` media type, `type/title/status/detail/instance` sahələri. Standartlaşdırılmış error handling.
4. **`operationId` həmişə unikal qoy** — client SDK generator `operationId`-i method adı kimi istifadə edir. `orders_list`, `orders_create` kimi aydın adlar.
5. **Examples əlavə et** — hər schema və response üçün real-looking example. Mock server və documentation dramatic olaraq yaxşılaşır.
6. **Version-lama strategiyası seç** — URL path (`/v1/`), header (`Accept: application/vnd.api.v2+json`), və ya query param. Spec-də aydın göstər.
7. **Security həmişə təyin et** — `security: []` deməklə endpoint-in açıq olduğunu göstər (yoxsa Swagger UI authorize tələb etməyə çalışır).
8. **Spectral lint ilə CI qur** — `.spectral.yaml` custom rule-lar (məsələn, hər endpoint 4xx response olmalıdır, description məcburidir).
9. **Mock server deploy et** — staging-lə yanaşı Prism mock (`mock.api.example.com`). Frontend/integrator backend hazır olmadan inteqrasiyaya başlaya bilər.
10. **SDK-nı avtomatik publish et** — CI-da spec dəyişəndə `openapi-generator-cli` ilə SDK yenilə və npm/composer/pypi-yə publish et. Consumer-lər hər zaman ən son versiyanı ala bilər.
