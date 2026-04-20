# API Design Patterns (REST, gRPC, GraphQL, WebSocket)

## Nədir? (What is it?)

API Design Patterns - iki sistem arasında data mübadiləsi üçün istifadə olunan protokollar və konvensiyalardır. Düzgün seçim scale, evolvability (dəyişikliyə davam gətirmə) və developer experience (DX) üçün kritikdir.

**Niyə API dizaynı önəmlidir?**
- **Contract** (kontrakt) sistemin necə scale olacağını müəyyən edir - breaking change istehsaldakı 100 client-i sındıra bilər
- **Evolvability** - API versiyalarını necə yeniləyəcəyin başlanğıcda qərarlaşdırılır
- **Performance** - payload size, latency, connection model seçiminə bağlıdır
- **Developer Experience** - SDK, documentation, error handling asanlığı
- **Ecosystem** - REST curl ilə test olunur, gRPC isə protobuf alət tələb edir

**Seçim dilemması:** Public API REST, internal microservice gRPC, mobile aggregator GraphQL, real-time notification WebSocket. Bir həll bütün problemlərə uyğun deyil.

## Əsas Protokollar (Main Protocols)

### 1. REST (Representational State Transfer)

REST resurs-yönümlü (resource-oriented) paradiqma-dır. HTTP-nin öz verb-ləri (GET, POST, PUT, PATCH, DELETE) CRUD əməliyyatlarına map olunur.

**Əsas prinsiplər:**
- **Resource-oriented** - URL bir resurs-u təmsil edir (`/users/42`), əməliyyatı yox
- **Stateless** - hər request tam məlumat daşıyır, server session saxlamır
- **Uniform interface** - eyni HTTP verb-ləri hər yerdə eyni mənanı verir
- **Cacheability** - GET response Cache-Control header ilə CDN-də saxlanıla bilər
- **Layered system** - proxy, gateway, CDN arada ola bilər

**URI dizaynı:**
```
GET    /api/users              # list
GET    /api/users/42           # single
POST   /api/users              # create
PUT    /api/users/42           # full update
PATCH  /api/users/42           # partial update
DELETE /api/users/42           # delete
GET    /api/users/42/orders    # nested resource
```

**Richardson Maturity Model** - REST-in 4 səviyyəsi:
- **Level 0** - HTTP sadəcə transport (SOAP bu səviyyədədir)
- **Level 1** - Resource-lar var (`/users/42`), amma bütün sorğular POST
- **Level 2** - HTTP verb-ləri düzgün istifadə olunur, status kodlar mənalıdır
- **Level 3** - HATEOAS - response `_links` ilə növbəti addımları göstərir (nadir istifadə olunur)

Praktikada HATEOAS az istifadə olunur - client-lər bilərəkdən URL-ləri hardcode edir, tooling (Postman, Swagger) link-ləri generate etmir. Level 2 bazar standartıdır.

### 2. gRPC (Google Remote Procedure Call)

gRPC - binary Protobuf mesajlarını HTTP/2 üzərindən göndərən yüksək-performanslı RPC framework-üdür. Google tərəfindən 2016-cı ildə açıq mənbə edilib.

**Üstünlüklər:**
- **Binary protobuf** - JSON-dan 3-10x kiçik payload
- **HTTP/2 multiplexing** - bir connection üzərindən paralel stream-lər
- **Bi-directional streaming** - client və server eyni vaxtda data göndərə bilər
- **Code generation** - `.proto` fayldan PHP, Java, Go, Python klass-ları avtomatik
- **Strict contract** - schema olmadan request göndərə bilməzsən
- **Deadline propagation** - timeout zəncir boyunca ötürülür

**Dezavantajlar:**
- **Browser dəstəyi zəifdir** - birbaşa browser-dən çağıra bilməzsən, `grpc-web` proxy lazımdır
- **Debugging çətindir** - binary payload-u `curl`-la oxuya bilməzsən, `grpcurl` tələb olunur
- **Learning curve** - protobuf sintaksisini, codegen workflow-unu öyrənmək lazımdır
- **Caching zəifdir** - HTTP caching semantikası işləmir

```protobuf
service UserService {
  rpc GetUser (GetUserRequest) returns (User);
  rpc ListUsers (ListUsersRequest) returns (stream User);
}
message GetUserRequest { int64 id = 1; }
message User { int64 id = 1; string email = 2; string name = 3; }
```

### 3. GraphQL

GraphQL - Facebook tərəfindən 2015-də açıq mənbə edilmiş query dilidir. Client **nə istədiyini** dəqiq göstərir, server dəqiq həmin field-ləri qaytarır.

**Həll etdiyi problemlər:**
- **Over-fetching** - REST `/users/42` 20 field qaytarır, client 3-ə ehtiyacı var
- **Under-fetching** - REST-də bir səhifə üçün 5 endpoint çağırmaq lazım gəlir
- **Version management** - GraphQL field deprecate etməklə işləyir, `/v2/` lazım deyil
- **Strongly-typed schema** - schema introspection ilə client SDK avtomatik yaranır

```graphql
query { user(id: 42) { name email orders(last: 5) { id total } } }
```

**Çətinliklər:**
- **N+1 problem** - hər `user.orders` ayrı SQL sorğusu - DataLoader pattern lazımdır
- **Caching çətindir** - HTTP-level cache işləmir (hər sorğu POST)
- **Rate limiting** - endpoint əsaslı deyil, query complexity score hesablamaq lazımdır
- **Authorization** - hər field üçün ayrı yoxlama
- **File upload** - multipart spec əlavə tələbdir

### 4. WebSocket / Server-Sent Events (SSE)

**WebSocket** - TCP üzərində full-duplex (iki istiqamətli) persistent connection. HTTP upgrade handshake ilə başlayır, sonra `ws://` frame-lərilə davam edir.

İstifadə halları: real-time chat (Discord), live dashboard, multiplayer game, collaborative editor (Figma).

**Server-Sent Events (SSE)** - server-dən client-ə tək istiqamətli push, `text/event-stream` content-type ilə sadə HTTP. WebSocket-dən fərqli: avtomatik reconnect, yalnız text, standart HTTP proxy dostdur. Notification feed, stock ticker üçün ideal.

### 5. HTTP Long Polling (Legacy Fallback)

Client request göndərir, server yeni data çıxana qədər response-u tutur (timeout 30-60s). Data gələn kimi qaytarır, client dərhal yeni request göndərir.

**İstifadə nə vaxt?** WebSocket blok edən köhnə corporate proxy-lərdə fallback kimi (məsələn Socket.IO avtomatik long polling-ə düşür).

### 6. Webhooks

Webhook - inverted push modelidir. Müştəri sistemə URL qeydiyyatdan keçirir, hadisə baş verəndə sistem POST göndərir. Server→server async event bildirişi.

**Nümunə:** Stripe payment success → `POST https://myapp.com/webhooks/stripe`

**Best practices:**
- **HMAC signature** - `X-Signature` header ilə payload imzala
- **Retry + exponential backoff** - 5xx alanda 1m, 5m, 30m, 2h, 24h təkrarla
- **Idempotency** - event ID ilə dublikat emal et (bax fayl 28)
- **Ack quickly** - webhook handler 200 qaytarıb işi queue-ya ötürsün

## Müqayisə Cədvəli (Comparison Table)

| Xüsusiyyət | REST | gRPC | GraphQL | WebSocket |
|---|---|---|---|---|
| Transport | HTTP/1.1 or HTTP/2 | HTTP/2 | HTTP (POST) | TCP (WS frame) |
| Payload | JSON (text) | Protobuf (binary) | JSON | JSON/Binary |
| Schema | OpenAPI (optional) | .proto (strict) | SDL (strict) | Custom |
| Streaming | Limited (SSE) | Bi-directional | Subscriptions | Bi-directional |
| Browser | Native fetch | grpc-web proxy | Native fetch | Native WS |
| Caching | HTTP cache  | Zəif | Çətin | N/A |
| Latency | Orta | Çox aşağı | Orta | Çox aşağı |
| Tooling | Postman, curl | grpcurl, BloomRPC | Apollo, GraphiQL | wscat, Postman |
| Learning | Aşağı | Yüksək | Orta | Orta |
| İdeal | Public API | Internal RPC | Aggregator | Real-time |

## Nə Vaxt Hansını Seç? (When to Choose What)

- **REST** - public API, broad client, caching önəmli (GitHub REST, Stripe)
- **gRPC** - internal microservice, yüksək performans, strict contract (Uber, Kubernetes API)
- **GraphQL** - mobile/SPA aggregator, BFF layer, diverse client data (Facebook, GitHub v4)
- **WebSocket** - real-time bi-directional, < 100ms latency (Discord, trading)
- **SSE** - server-dən tək istiqamət push (notification feed, live metrics)
- **Webhook** - server-to-server async event (Stripe callback, GitHub events)

## Versioning Strategies (Versiyalanma)

**1. URI versioning** - `/api/v1/users`, `/api/v2/users`
- Üstünlük: Sadə, CDN cache asan
- Dezavantaj: URL çirklidir, multiple version server

**2. Header versioning** - `Accept: application/vnd.myapi.v2+json`
- Üstünlük: URL təmiz
- Dezavantaj: Browser-də test etmək çətin

**3. Query param** - `/api/users?version=2`
- Nadir istifadə olunur, caching çətinləşdirir

**Semantic versioning:** MAJOR.MINOR.PATCH - MAJOR breaking change-də artır. API-lərdə breaking change mümkün qədər qaçıla bilməlidir. Yeni field əlavə etmək backward-compatible-dir, field silmək isə breaking-dir.

**Deprecation headers:**
```
Deprecation: true
Sunset: Wed, 01 Jan 2027 00:00:00 GMT
Link: <https://api.example.com/v2/users>; rel="successor-version"
```

## Pagination (Səhifələmə)

**1. Offset pagination:**
```
GET /users?offset=100&limit=20
```
- Üstünlük: sadə, jump-to-page mümkün
- Dezavantaj: yüksək offset-də SQL `OFFSET 1000000` yavaşdır, data dəyişəndə duplicate/missing

**2. Cursor-based (keyset) pagination:**
```
GET /users?cursor=eyJpZCI6MTAwfQ&limit=20
```
- Cursor son görünən ID-ni (yaxud composite key) base64 encode edir
- Üstünlük: stable, performans sabit (`WHERE id > 100 ORDER BY id LIMIT 20`)
- Dezavantaj: jump-to-page yoxdur, yalnız next/prev

**3. Page token** - server opaque token qaytarır (GraphQL Relay standardı, Google Cloud API)

**Tövsiyə:** Böyük dataset-lərdə cursor-based seç.

## Filtering, Sorting, Field Selection

**REST:**
```
GET /users?filter[status]=active&sort=-created_at&fields=id,name,email
```

**GraphQL:** dildə öz daxilində (`users(filter: {status: ACTIVE}, first: 20) { id name }`)

**gRPC:** `FieldMask` istifadə olunur:
```protobuf
message GetUserRequest {
  int64 id = 1;
  google.protobuf.FieldMask field_mask = 2; // "id,email,name"
}
```

## Error Responses - RFC 7807 Problem Details

Consistent error envelope developer experience-i artırır:
```json
{
  "type": "https://example.com/probs/validation",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The email field must be a valid email.",
  "instance": "/users/create",
  "errors": {
    "email": ["Invalid format"]
  }
}
```

**HTTP status seçimi:**
- `400` Bad Request - malformed
- `401` Unauthorized - token yoxdur
- `403` Forbidden - icazə yoxdur
- `404` Not Found
- `409` Conflict - idempotency, version mismatch
- `422` Unprocessable Entity - validation xətası
- `429` Too Many Requests - rate limit
- `500` Internal Server Error

## Idempotency, Rate Limiting, Authentication

**Idempotency** - `Idempotency-Key` header POST üçün (detallar fayl 28-də). Retry təhlükəsizdir.

**Rate limiting headers:**
```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1714608000
Retry-After: 60
```
429 status kodu ilə bağlı detallar fayl 06-da.

**Authentication:**
- **Bearer token** - `Authorization: Bearer eyJ...` (JWT)
- **OAuth2** - 3rd party integration üçün authorization code flow
- **mTLS** - service-to-service (detallar fayl 14-də)
- **API Key** - server-to-server, `X-API-Key` header

## PHP/Laravel ilə Tətbiq (Implementation)

### REST - Laravel API Resource

```php
// routes/api.php
Route::apiResource('users', UserController::class);

class UserController extends Controller
{
    public function index() {
        return UserResource::collection(User::cursorPaginate(20));
    }

    public function store(StoreUserRequest $request) {
        $user = User::create($request->validated());
        return (new UserResource($user))->response()
            ->setStatusCode(201)
            ->header('Location', "/api/users/{$user->id}");
    }
}

class UserResource extends JsonResource {
    public function toArray($request): array {
        return [
            'id'    => $this->id,
            'email' => $this->email,
            'name'  => $this->name,
            'links' => ['self' => route('users.show', $this->id)],
        ];
    }
}
```

### gRPC - spiral/roadrunner + google/protobuf

```bash
composer require google/protobuf spiral/roadrunner-grpc
protoc --php_out=./generated --grpc_out=./generated \
       --plugin=protoc-gen-grpc=/usr/local/bin/grpc_php_plugin user.proto
```

```php
use User\GetUserRequest;
use User\User as UserMessage;
use Spiral\GRPC\ContextInterface;

class UserService implements UserServiceInterface
{
    public function GetUser(ContextInterface $ctx, GetUserRequest $in): UserMessage
    {
        $user = \App\Models\User::findOrFail($in->getId());
        $message = new UserMessage();
        $message->setId($user->id);
        $message->setEmail($user->email);
        $message->setName($user->name);
        return $message;
    }
}
```

RoadRunner `.rr.yaml`-da `grpc.listen: tcp://0.0.0.0:9001` və `proto` fayl yolu təyin edilir.

### GraphQL - Nuwave Lighthouse

```bash
composer require nuwave/lighthouse
```

```graphql
# graphql/schema.graphql
type Query {
  user(id: ID! @eq): User @find
  users(
    status: String @where(operator: "=")
  ): [User!]! @paginate(defaultCount: 20, type: CONNECTION)
}

type Mutation {
  createUser(
    email: String! @rules(apply: ["email", "unique:users"])
    name: String!
  ): User! @create
}

type User {
  id: ID!
  email: String!
  name: String!
  orders: [Order!]! @hasMany
}

type Order {
  id: ID!
  total: Float!
  items: [OrderItem!]! @hasMany
}
```

N+1 problem-i `@hasMany` directive-i avtomatik DataLoader ilə həll edir. Query complexity üçün `max_query_complexity` config-i təyin olunur.

### WebSocket - Laravel Reverb

```bash
composer require laravel/reverb
php artisan reverb:install
```

```php
// app/Events/OrderStatusChanged.php
class OrderStatusChanged implements ShouldBroadcast
{
    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("orders.{$this->order->user_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'id'     => $this->order->id,
            'status' => $this->order->status,
            'total'  => $this->order->total,
        ];
    }
}

// Client-də (JS)
// Echo.private(`orders.${userId}`)
//     .listen('OrderStatusChanged', (e) => updateUI(e));

// Status dəyişəndə event firla
OrderStatusChanged::dispatch($order);
```

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Müsahibə Sualları (Interview Questions)

### 1. REST vs gRPC - hansını nə vaxt seçərsən?

REST public API üçün: curl ilə debug, CDN caching, broad client support, OpenAPI generator ekosistemi. gRPC internal microservice-lər üçün: binary protobuf payload 3-10x kiçik, HTTP/2 multiplexing, bi-directional streaming, strict contract codegen. Uber, Netflix internal-də gRPC, external-də REST/GraphQL istifadə edir.

### 2. GraphQL N+1 problemini necə həll edərsən?

DataLoader pattern-i: event loop tick-ində qrup sorğuları batch-ləyir. `users` sorğusunda 100 user gəlirsə, hər user üçün ayrı `SELECT * FROM orders WHERE user_id = ?` əvəzinə bir `WHERE user_id IN (...)` sorğusu icra olunur. Laravel Lighthouse-da `@hasMany` directive-i bunu avtomatik edir. Əlavə olaraq `query complexity analysis` ilə çox ağır sorğular rədd edilməlidir.

### 3. Pagination üçün offset vs cursor - fərq nədir?

Offset (`LIMIT 20 OFFSET 1000000`) böyük offset-lərdə yavaşdır - DB 1M row oxumalıdır. Həmçinin data dəyişərsə duplicate/missing yaranır. Cursor-based isə `WHERE id > last_seen_id ORDER BY id LIMIT 20` - indexed və sabit performans. Dezavantaj: jump-to-page yoxdur. Böyük dataset-lərdə (feed, timeline) cursor seç. Admin panel-də offset yetərlidir.

### 4. API versiyalanma - URI vs header hansı daha yaxşıdır?

URI versioning (`/v1/`) sadədir, CDN cache problem yoxdur, browser-də test etmək asan. Header versioning URL-i təmiz saxlayır amma curl-la test çətindir və proxy-lərin bəziləri custom header-ləri drop edə bilər. Praktikada GitHub v3 URI, v4 GraphQL endpoint-i ayırır. Tövsiyəm: URI seç, amma əsas məqsəd breaking change-lərdən qaçmaqdır - yeni field əlavə etmək versiya tələb etmir.

### 5. Real-time notification üçün WebSocket, SSE və long polling arasında seçim necə edilir?

**WebSocket** - bi-directional, chat/game/collaborative edit. **SSE** - server→client tək istiqamət, stock ticker, notification feed, sadə reconnect. **Long polling** - köhnə infra fallback. Əgər client-dən server-ə az məlumat göndərilirsə SSE seç - HTTP üzərindəndir, auth asan, proxy dostdur. Bi-directional lazımdırsa WebSocket. Scale üçün Reverb/Pusher kimi dedicated server və Redis pub/sub istifadə et.

### 6. gRPC-də browser dəstəyi problemi necə həll olunur?

Browser birbaşa HTTP/2 trailer-ləri oxuya bilmir. **grpc-web** həlli: Envoy yaxud custom proxy gRPC-Web-i grpc-ə translate edir. Client `grpc-web-client` istifadə edir, proxy HTTP/1.1 və ya HTTP/2 üzərində simplified gRPC-Web alır və backend-ə əsl gRPC göndərir. Alternativ: browser-də GraphQL/REST, server-to-server gRPC.

### 7. Idempotency-Key header necə işləyir?

Client unique UUID göndərir (`Idempotency-Key: abc-123`). Server Redis-də key-i yoxlayır. Yoxdursa əməliyyatı icra edir, response-u key ilə cache edir (24h TTL). Təkrar gələndə cache-dən qaytarır. Eyni key + fərqli body üçün 409 qaytarır. Stripe, PayPal, AWS bu pattern-i istifadə edir. Detallar fayl 28-də. Retry safe-dir - network timeout zamanı client eyni key ilə təkrar göndərir, duplicate charge olmur.

### 8. REST-də HATEOAS niyə az istifadə olunur?

HATEOAS - server response-da link-ləri qaytarır ki, client sonrakı addımları dynamically tapsın. Nəzəri olaraq API evolve olanda client-lər sındırmır. Praktikada isə: client-lər URL-ləri hardcode edir (developer convenience), tooling (Swagger, Postman) HATEOAS generate etmir, mobile app-lər sabit URL schema gözləyir. GraphQL schema introspection HATEOAS-un yerinə keçib - tip güclü və tooling əla. Level 2 REST bazarda standart sayılır.

## Best Practices

- **Schema-first dizayn** — OpenAPI/Protobuf/GraphQL SDL əvvəl, kod sonra. Contract client və server komandaları arasında paralel iş imkanı verir.
- **Backward compatibility qoru** — field əlavə et, heç vaxt field silmə və ya tipini dəyişmə. Deprecated `Sunset` header ilə elan et, minimum 6 ay yaşat.
- **Cursor pagination seç** — offset/limit böyük datasetdə yavaşdır və duplicate/miss yaradır. Opaque cursor stable sıralama təmin edir.
- **Consistent error envelope** — RFC 7807 Problem Details istifadə et, bütün servis üçün eyni struktur.
- **Rate limit + idempotency header-ləri** — hər public POST endpoint-i `Idempotency-Key` qəbul etsin; `X-RateLimit-*` göstər.
- **Field selection / sparse fieldsets** — REST-də `?fields=id,name`, GraphQL-də təbii. Bandwidth və serializasiya vaxtını azaldır.
- **Versioning strategy seç və sadiq ol** — URI versioning (`/v1/`) sadədir, header versioning daha təmiz amma debug çətin. Bir strategiyanı standartlaşdır.
- **gRPC internal, REST/GraphQL external** — perf sensitiv mikroservis çağırışlarında gRPC; xarici third-party üçün REST və ya GraphQL.
- **WebSocket üçün heartbeat + reconnect logic** — client 30s ping/pong, server connection timeout; reconnect-də state resync protokolu.
- **OpenAPI/SDL CI-də validate et** — breaking change detection (openapi-diff, graphql-inspector); PR-da fail et.
- **Tələbat rat alma** — GraphQL-də query cost analysis və depth limit; REST-də per-endpoint limit; gRPC-də `max_receive_message_length`.
- **Observability hər interface üçün eyni** — request-id propagation, latency histogram, error rate; OpenTelemetry bütün 4 protokolu dəstəkləyir.
- **Auth layer protokoldan asılı olmasın** — JWT/OAuth2 middleware REST, GraphQL və WebSocket üçün eyni olsun.
- **Generate, handwrite etmə** — protobuf `protoc`, OpenAPI `openapi-generator`, GraphQL codegen client kodu avtomatik yarat.
