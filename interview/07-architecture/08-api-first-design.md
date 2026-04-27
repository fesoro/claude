# API-First Design (Senior ⭐⭐⭐)

## İcmal
API-First Design — tətbiqin implementasiyasından əvvəl API kontraktını müəyyən etmək yanaşmasıdır. OpenAPI Specification (Swagger) ilə API dizaynı yazılır, sonra bu spesifikasiyadan server stub, client SDK, dokumentasiya avtomatik generasiya edilir. Interview-da bu mövzu sizin team collaboration, contract-driven development, versioning anlayışınızı ölçür.

## Niyə Vacibdir
Böyük komandada backend developer API implement etdiyi anda frontend developer mock server-ə qarşı paralel inkişaf edə bilir. API kontrakt müştərilər üçün sabit öhdəlik yaradır — dəyişikliklər versioning ilə idarə olunur. Documentation-first yanaşma API-nin discovery-sini asanlaşdırır. Bu mövzu müasir API dizayn prinsiplərini başa düşdüyünüzü göstərir.

## Əsas Anlayışlar

- **OpenAPI Specification (OAS)**: API kontraktını YAML/JSON formatda təsvir edən standart (əvvəlki adı: Swagger)
- **Design-first vs Code-first**: Design-first — əvvəl spec yazılır, sonra implementasiya. Code-first — əvvəl kod, sonra spec generate
- **Contract-Driven Development**: API spec müştəri ilə müqavilədir — versioning olmadan dəyişmək olmaz
- **Versioning strategies**: URL versioning (`/v1/`), Header versioning (`Accept: application/vnd.api+json;version=2`), Query param versioning
- **Semantic Versioning**: MAJOR.MINOR.PATCH — MAJOR breaking change, MINOR yeni feature (backward compatible), PATCH bug fix
- **Breaking change**: Mövcud field silmək, type dəyişdirmək, required field əlavə etmək — backward compatibility pozulur
- **Non-breaking change**: Yeni optional field əlavə etmək, yeni endpoint əlavə etmək
- **Stub/Mock server**: OAS-dan avtomatik generate edilən mock — frontend parallel inkişaf edə bilər
- **Richardson Maturity Model**: REST API yetkinliyi 4 səviyyədə — Level 0 (HTTP tunnel), Level 1 (Resources), Level 2 (HTTP Verbs), Level 3 (HATEOAS)
- **HATEOAS**: Response-da növbəti hərəkətlər üçün link-lər — Level 3 REST. Praktikdə nadir istifadə olunur
- **Idempotency**: `PUT`, `DELETE`, `GET` — idempotent. `POST` — deyil. `PATCH` — bağlıdır
- **Status codes**: 200 OK, 201 Created, 204 No Content, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 409 Conflict, 422 Unprocessable Entity, 500 Internal Server Error
- **Pagination**: Cursor-based vs Offset-based — böyük dataset-lər üçün cursor daha sürətli
- **Rate limiting headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

## Praktik Baxış

**Interview-da necə yanaşmaq:**
API dizayn suallarında əvvəlcə resource modeling-i izah edin — hansi entity-lər var, onlar arasındakı əlaqə nədir. Sonra CRUD endpoint-lərini müəyyən edin, status code-ları əsaslandırın, versioning strategiyasını izah edin.

**Follow-up suallar:**
- "Breaking change-i necə idarə edirsiniz?"
- "Versioning strategy-lərdən hansını seçərdiniz?"
- "Pagination necə implement edərdiniz?"
- "Authentication vs Authorization — API-da necə idarə olunur?"

**Ümumi səhvlər:**
- Verb-based URL-lər: `/getUser`, `/createOrder` — REST-in URL-lər noun, HTTP method-lar verb prinsipini pozur
- Hər zaman 200 qaytarmaq, error-u body-də göstərmək
- Versioning olmadan breaking change etmək
- Pagination-ı unudub bütün records qaytarmaq — performance problem
- Response formatını standartlaşdırmamaq

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Əla cavab "API-ni müştəri perspektivindən dizayn et" prinsipini vurğulayır. Developer experience (DX) nədir, error message-lər başa düşüləndir, rate limiting var, pagination var — bunları birlikdə düşünmək.

## Nümunələr

### Tipik Interview Sualı
"RESTful API dizayn edin: istifadəçilərin sifarişlərini idarə edən sistem. URL strukturu, status code-lar, versioning izah edin."

### Güclü Cavab
"URL strukturu: `/api/v1/orders` — resource-based, noun. POST ilə order yaratmaq, GET ilə siyahı, GET `/orders/{id}` ilə tək order. Status code-lar: order yaradıldıqda 201 Created + `Location` header, tapılmadıqda 404, validation xətasında 422 + error details. Versioning üçün URL prefix seçərdim — /v1/, /v2/ — ən aydın yanaşmadır. Breaking change-dən əvvəl deprecation notice göndərər, köhnə versiyonu 6 ay parallel saxlayardım."

### Kod / Konfiqurasiya Nümunəsi

```yaml
# OpenAPI Specification 3.1
openapi: 3.1.0
info:
  title: Order Management API
  version: 1.0.0
  description: E-commerce order management service

servers:
  - url: https://api.example.com/v1
    description: Production

tags:
  - name: Orders
    description: Order management operations

paths:
  /orders:
    get:
      tags: [Orders]
      summary: List orders
      operationId: listOrders
      parameters:
        - name: cursor
          in: query
          schema:
            type: string
          description: Pagination cursor
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
        - name: status
          in: query
          schema:
            $ref: '#/components/schemas/OrderStatus'
      responses:
        '200':
          description: Success
          headers:
            X-RateLimit-Limit:
              schema:
                type: integer
            X-RateLimit-Remaining:
              schema:
                type: integer
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/OrderListResponse'
        '401':
          $ref: '#/components/responses/Unauthorized'

    post:
      tags: [Orders]
      summary: Place an order
      operationId: placeOrder
      parameters:
        - name: Idempotency-Key
          in: header
          required: true
          schema:
            type: string
            format: uuid
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/PlaceOrderRequest'
      responses:
        '201':
          description: Order placed
          headers:
            Location:
              schema:
                type: string
                example: /v1/orders/ord_123abc
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/OrderResponse'
        '422':
          $ref: '#/components/responses/ValidationError'

  /orders/{orderId}:
    get:
      tags: [Orders]
      summary: Get order details
      operationId: getOrder
      parameters:
        - name: orderId
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/OrderResponse'
        '404':
          $ref: '#/components/responses/NotFound'

components:
  schemas:
    PlaceOrderRequest:
      type: object
      required: [items, shipping_address]
      properties:
        items:
          type: array
          minItems: 1
          items:
            type: object
            required: [product_id, quantity]
            properties:
              product_id:
                type: string
              quantity:
                type: integer
                minimum: 1
        shipping_address:
          $ref: '#/components/schemas/Address'

    OrderStatus:
      type: string
      enum: [placed, paid, shipped, delivered, cancelled]

    OrderResponse:
      type: object
      properties:
        id:
          type: string
          example: ord_123abc
        status:
          $ref: '#/components/schemas/OrderStatus'
        total:
          $ref: '#/components/schemas/Money'
        placed_at:
          type: string
          format: date-time

    Money:
      type: object
      properties:
        amount:
          type: integer
          description: Amount in cents
          example: 9999
        currency:
          type: string
          example: USD

    OrderListResponse:
      type: object
      properties:
        data:
          type: array
          items:
            $ref: '#/components/schemas/OrderResponse'
        meta:
          type: object
          properties:
            next_cursor:
              type: string
              nullable: true
            has_more:
              type: boolean

  responses:
    Unauthorized:
      description: Unauthorized
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
    NotFound:
      description: Not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
    ValidationError:
      description: Validation error
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ValidationErrorResponse'

  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT

security:
  - bearerAuth: []
```

```php
// Laravel — API Resource ilə standardized response
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'status'    => $this->status,
            'total'     => [
                'amount'   => $this->total_cents,
                'currency' => $this->currency,
            ],
            'items'     => OrderItemResource::collection($this->items),
            'placed_at' => $this->placed_at->toIso8601String(),
            '_links'    => [  // HATEOAS - optional
                'self'   => route('orders.show', $this->id),
                'cancel' => $this->status === 'placed'
                    ? route('orders.cancel', $this->id)
                    : null,
            ],
        ];
    }
}

// Cursor Pagination
class OrderController extends Controller
{
    public function index(IndexOrdersRequest $request): OrderCollection
    {
        $orders = Order::query()
            ->where('customer_id', $request->user()->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->cursorPaginate($request->input('limit', 20))
            ->withQueryString();

        return new OrderCollection($orders);
    }
}
```

## Praktik Tapşırıqlar

- Blog API üçün OpenAPI spec yazın — Post, Comment, Tag resource-ları ilə
- `GET /users/1/orders` vs `GET /orders?user_id=1` — hansı daha RESTful? Niyə?
- Breaking change-i non-breaking etmək üçün 3 strategiya sıralayın
- Rate limiting header-larını Laravel middleware-i ilə implement edin
- Versioning strategiyalarını müqayisə edin — URL vs Header vs Query param

## Əlaqəli Mövzular

- `09-backend-for-frontend.md` — BFF + API-First
- `06-cqrs-architecture.md` — Command = POST, Query = GET
- `01-monolith-vs-microservices.md` — API as contract between services
- `12-feature-flags.md` — API-da feature flag istifadəsi
