# REST vs GraphQL vs gRPC (Middle ⭐⭐)

## İcmal
REST, GraphQL və gRPC — üç fərqli API dizayn yanaşmasıdır. Hər birinin fərqli use-case-ləri, trade-off-ları və məhdudiyyətləri var. 5+ il təcrübəsi olan backend developer bu üçü arasındakı fərqi nəzəriyyə səviyyəsindən deyil, real layihə təcrübəsindən izah etməlidir.

## Niyə Vacibdir
API seçimi uzun müddəti əhatə edən arxitektura qərarıdır — sonradan dəyişdirmək çox baha başa gəlir. Interviewer bu sualı verərkən sizi yoxlayır: "Niyə?" sualını cavablandıra bilirsinizmi? Over-fetching, under-fetching, N+1 problemi, contract-first development, IDL — bunları real scenario üzərindən izah edə bilmək sizi fərqləndirir.

## Əsas Anlayışlar

**REST (Representational State Transfer):**
- **Resource-centric:** Hər endpoint bir resursu təmsil edir (`GET /users/1`, `POST /orders`)
- **Stateless:** Hər request özündə bütün məlumatı daşıyır — server client state-i saxlamır
- **HTTP methodlar:** GET (read, idempotent), POST (create), PUT (full replace, idempotent), PATCH (partial update), DELETE (idempotent)
- **Over-fetching:** Client lazım olmayan field-ləri də alır (`GET /users/1` → 30 field, amma yalnız 3-ü lazımdır)
- **Under-fetching:** Bir əməliyyat üçün bir neçə endpoint lazım ola bilər — "chatty API"
- **HTTP caching native dəstək:** GET sorğuları CDN, browser cache-ə native uyğundur
- **Versioning:** URL (`/v1/users`), header, query param — breaking change-lər üçün
- **Geniş tooling:** OpenAPI/Swagger, Postman, her dildə client library

**GraphQL:**
- **Client-driven queries:** Client istədiyi field-ləri özü seçir — over/under fetching yoxdur
- **Tək endpoint:** `POST /graphql` — bütün sorğular bu endpoint-ə gedir
- **Strongly typed schema:** SDL (Schema Definition Language) ilə explicit contract
- **Query, Mutation, Subscription:** 3 əməliyyat növü — CRUD + real-time
- **N+1 problem:** `posts → author` kimi nested sorğular DataLoader olmadan N+1 DB query yaradır. Hər post üçün ayrı author query
- **DataLoader:** Request batching — 100 post üçün 100 ayrı user query əvəzinə 1 batched query (`WHERE id IN (...)`)
- **Introspection:** Schema özü-özünü izah edir — GraphiQL, type-safe codegen üçün əsas
- **Caching çətin:** POST sorğuları HTTP cache-i bypass edir. Persisted queries (hashed query string) ilə GET-ə çevirmək lazımdır
- **Query complexity limit:** Dərin nested query — `posts { author { posts { author { posts } } } }` — server-i boğa bilər. Complexity scoring + limit vacibdir
- **BFF (Backend for Frontend) pattern:** Mobile, web, 3rd party üçün ayrı API layer. GraphQL BFF üçün idealdir

**gRPC (Google Remote Procedure Call):**
- **Protocol Buffers (protobuf):** Binary serialization — JSON-dan 3-10x daha kiçik, çox sürətli parse
- **HTTP/2 üzərindən:** Multiplexing, bidirectional streaming, header compression
- **Contract-first (IDL):** `.proto` faylı əvvəlcə yazılır, sonra hər dildə kod generate edilir — type safety
- **4 communication pattern:** Unary (request-response), Server streaming, Client streaming, Bidirectional streaming
- **Browser native dəstəyi yoxdur:** grpc-web (Envoy proxy) lazımdır — browser WebRTC/HTTP/2 API-ları gRPC ilə uyğun deyil
- **Strongly typed + codegen:** Compile-time type checking, stub generation — runtime error-lar azalır
- **Deadline/Timeout propagation:** gRPC deadline chain-i dəstəkləyir — parent call-ın deadline-i child-lara keçir

**Müqayisə:**
- Performance: gRPC > REST ≈ GraphQL (binary vs text)
- Flexibility: GraphQL > REST > gRPC (client query freedom)
- Browser support: REST = GraphQL > gRPC
- Caching: REST > GraphQL > gRPC
- Learning curve: REST < GraphQL < gRPC
- Real-time streaming: gRPC ≈ GraphQL (subscription) > REST

## Praktik Baxış

**Interview-da yanaşma:**
"Hansı seçim daha yaxşıdır?" sualını "hansı problem üçün?" ilə cavablandırın. Heç birini "həmişə daha yaxşı" kimi təqdim etməyin. Real use-case ilə izah edin.

**Follow-up suallar:**
- "GraphQL N+1 problemini necə həll edərdiniz?" — DataLoader (batching + caching), eager loading, query complexity limit
- "gRPC-ni browser-dən birbaşa çağıra bilərsinizmi?" — Native deyil, grpc-web proxy (Envoy) lazımdır
- "REST API versioning strategiyasını seçin" — URL versioning ən açıq, header versioning daha clean, hər birinin trade-off-u var
- "GraphQL subscription nə zaman lazımdır?" — Real-time updates: stock price, chat, live notification
- "Hibrid arxitekturalar nə vaxt lazımdır?" — External API: REST/GraphQL; internal microservices: gRPC

**Ümumi səhvlər:**
- REST-i CRUD ilə eyniləşdirmək — REST daha geniş architectural style-dır
- GraphQL-i "better REST" kimi göstərmək — caching çətin, security complexity artır (query complexity limit lazım)
- gRPC-ni yalnız "sürətli" kimi göstərmək — streaming capability daha kritik fərq ola bilər
- "GraphQL N+1 problem yoxdur" demək — DataLoader olmadan var

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- DataLoader-in GraphQL N+1 problemi üçün necə işlədiyini izah etmək
- Protobuf binary format-ın JSON-dan niyə daha effektiv olduğunu bilmək
- BFF pattern-inin niyə GraphQL ilə yaxşı işlədiyini izah etmək
- "Internal microservices gRPC, external API REST/GraphQL" hibrid yanaşmasını bilmək

## Nümunələr

### Tipik Interview Sualı
"You're building an API for a mobile app and a web dashboard. The mobile app needs minimal data to save bandwidth. The web shows full details. You also have microservices talking to each other internally. How would you design the API layer?"

### Güclü Cavab
Bu üç API texnologiyasının hər birinin yerini müəyyən edəcək hibrid arxitekturaya ehtiyac var.

**Mobile + Web frontend → GraphQL BFF:**
Mobile query: `{ user(id:1) { name avatar } }` — yalnız 2 field
Web query: `{ user(id:1) { name email avatar bio joinedAt posts { title } } }` — tam məlumat
Eyni endpoint, client istədiyini alır, bandwidth optimallaşır.

**Internal microservices → gRPC:**
Payment service ↔ Order service: strongly typed contract, protobuf binary, bidirectional streaming, deadline propagation. HTTP/2 multiplexing ilə çox sorğu paralel işlənir.

**Public/Partner API → REST:**
3rd party developer-lər REST-i bilir, OpenAPI spec-i var, HTTP caching işləyir.

**N+1 həll:** GraphQL BFF-də DataLoader — 100 post üçün 100 ayrı user query əvəzinə 1 batched query.

### Kod Nümunəsi
```graphql
# GraphQL Schema (SDL)
type User {
  id: ID!
  name: String!
  email: String!
  avatar: String
  bio: String
  joinedAt: DateTime!
  posts(limit: Int = 10, offset: Int = 0): [Post!]!
}

type Post {
  id: ID!
  title: String!
  content: String!
  author: User!
  tags: [String!]!
  createdAt: DateTime!
  commentCount: Int!
}

type Query {
  user(id: ID!): User
  posts(filter: PostFilter, limit: Int = 20): [Post!]!
  me: User
}

type Mutation {
  createPost(input: CreatePostInput!): Post!
  updateProfile(input: UpdateProfileInput!): User!
}

type Subscription {
  postCreated: Post!          # Real-time yeni post bildirişi
  notificationReceived: Notification!
}

# Mobile — minimal query
query MobileUserCard($id: ID!) {
  user(id: $id) {
    name
    avatar
  }
}

# Web — tam məlumat
query WebUserProfile($id: ID!) {
  user(id: $id) {
    name
    email
    avatar
    bio
    joinedAt
    posts(limit: 5) {
      id
      title
      createdAt
    }
  }
}
```

```php
// Laravel Lighthouse ilə GraphQL — N+1 həll
// composer require nuwave/lighthouse

// N+1 problem — DataLoader olmadan hər post üçün ayrı user query
// 100 post → 100 separate User query!

// Schema:
// type Post { author: User! }

// Resolver — N+1 problem var:
class PostAuthorResolver
{
    public function resolve(Post $post): User
    {
        return User::find($post->user_id);  // N+1!
    }
}

// Lighthouse DataLoader ilə həll:
// graphql/schema.graphql
// type Post { author: User! @belongsTo }
// Lighthouse automatic N+1 optimization (HasMany, BelongsTo)

// Əl ilə DataLoader (batch loading)
class UserBatchLoader
{
    public function load(array $userIds): array
    {
        $users = User::whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return array_map(
            fn($id) => $users[$id] ?? null,
            $userIds
        );
    }
}

// Query complexity limit — deep nested query-dən qoruma
// config/lighthouse.php
'security' => [
    'max_query_complexity' => 100,   // Max complexity score
    'max_query_depth'      => 5,     // Max nesting depth
    'disable_introspection' => false, // Production-da true düşün
],
```

```protobuf
// gRPC — .proto file (User service)
syntax = "proto3";
package user.v1;

// Code generate: protoc --php_out=. user.proto

service UserService {
  rpc GetUser    (GetUserRequest)    returns (User);
  rpc ListUsers  (ListUsersRequest)  returns (stream User);  // Server streaming
  rpc CreateUser (CreateUserRequest) returns (User);
  rpc UpdateUser (UpdateUserRequest) returns (User);

  // Bidirectional streaming — real-time sync
  rpc SyncUsers  (stream UserEvent)  returns (stream UserEvent);
}

message User {
  int64       id         = 1;
  string      name       = 2;
  string      email      = 3;
  repeated string roles  = 4;
  google.protobuf.Timestamp created_at = 5;
}

message GetUserRequest {
  int64 id = 1;
}

message ListUsersRequest {
  int32 page_size = 1;
  string page_token = 2;
  string filter = 3;  // e.g. "role:admin"
}

message CreateUserRequest {
  string name  = 1;
  string email = 2;
}
```

```php
// PHP gRPC client (Google gRPC PHP extension lazımdır)
// composer require grpc/grpc google/protobuf

use User\V1\UserServiceClient;
use User\V1\GetUserRequest;

class UserGrpcClient
{
    private UserServiceClient $client;

    public function __construct(string $host)
    {
        $this->client = new UserServiceClient(
            $host,
            ['credentials' => \Grpc\ChannelCredentials::createSsl()]
        );
    }

    public function getUser(int $id): array
    {
        $request = new GetUserRequest();
        $request->setId($id);

        // Deadline: 5 saniyə
        [$response, $status] = $this->client->GetUser(
            $request,
            [],
            ['timeout' => 5_000_000]  // microseconds
        );

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException(
                "gRPC error: {$status->details} (code: {$status->code})"
            );
        }

        return [
            'id'    => $response->getId(),
            'name'  => $response->getName(),
            'email' => $response->getEmail(),
        ];
    }

    public function listUsers(int $pageSize = 50): \Generator
    {
        $request = new \User\V1\ListUsersRequest();
        $request->setPageSize($pageSize);

        // Server streaming — memory-efficient
        $stream = $this->client->ListUsers($request);

        foreach ($stream->responses() as $user) {
            yield [
                'id'   => $user->getId(),
                'name' => $user->getName(),
            ];
        }
    }
}
```

```
REST vs GraphQL vs gRPC vizual müqayisəsi:

REST:
GET /users/1            → { id:1, name:"Ali", email:"ali@.." }
GET /users/1/posts      → [{ id:10, title:"Post1" }, ...]
GET /posts/10/comments  → [{ text:"Good!" }, ...]
3 requests, hər biri müstəqil HTTP call

GraphQL:
POST /graphql           →  query {
  query {                    user(id: 1) {
    user(id: 1) {              name
      name                     posts {
      posts {                    title
        title                    comments {
        comments {                 text
          text                   }
        }                      }
      }                      }
    }                   }
  }
1 request, client istədiyi structure-u alır
N+1 risk: user.posts.comments üçün 3 nested loader

gRPC:
UserService.GetUser(id: 1) → User{id:1, name:"Ali"}
Binary protobuf, HTTP/2 stream, strongly typed
Bidirectional streaming mümkündür
```

```php
// REST API nümunəsi — Resources + Versioning
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('users',  Api\V1\UserController::class);
    Route::apiResource('orders', Api\V1\OrderController::class);
});

// Resource transformation — over-fetching azaldır
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            // Sensitive fields buraya əlavə edilmir
            // $this->when() ilə conditional fields
            'posts_count' => $this->when(
                $request->include === 'stats',
                fn() => $this->posts_count
            ),
        ];
    }
}

// OpenAPI/Swagger — REST documentation
// composer require darkaonline/l5-swagger
// php artisan l5-swagger:generate
```

### İkinci Nümunə — API Gateway Pattern

```
Production-da hibrid yanaşma:

External:
  Mobile App  → GraphQL BFF  → [multiple internal services]
  Web App     → GraphQL BFF  → [multiple internal services]
  Partner API → REST API     → [internal services]

Internal (microservices arası):
  Order Service    ←gRPC→ Payment Service
  Order Service    ←gRPC→ Inventory Service
  User Service     ←gRPC→ Notification Service

Event-driven (asynchronous):
  All services  → Kafka topics → Analytics, Search, Cache

Niyə bu şəkildə?:
  - External: GraphQL → client autonomy, bandwidth opt
  - Internal: gRPC → type safety, performance, streaming
  - REST (partner): universally understood, cached

API Gateway:
  client → API Gateway → route to GraphQL/gRPC/REST
  Rate limiting, auth, observability — gateway-də
  Service-lər sadəcə business logic-ə fokuslanır
```

## Praktik Tapşırıqlar

- REST, GraphQL, gRPC ilə eyni user + posts API implement edin — response size, latency, DX fərqlərini müşahidə edin
- GraphQL playground-da (Hasura ya da Laravel Lighthouse) N+1 problemini reproduce edin: `EXPLAIN` ilə neçə SQL query getdiyini görün, DataLoader-lə düzəldin
- protobuf vs JSON serialization performansını benchmark edin: `php-protobuf` ilə eyni struct-ı serialize/deserialize edin, ölçün
- Laravel Lighthouse ilə `@belongsTo`, `@hasMany` directive-lərinin N+1-i necə optimize etdiyini araşdırın
- gRPC server streaming: `ListUsers` server-dən 10K user-i stream edərək göndərsin, PHP client-dən lazımı lazımı oxusun

## Əlaqəli Mövzular
- [HTTP Versions](02-http-versions.md) — gRPC HTTP/2 tələb edir; GraphQL HTTP/1.1 üzərindən işləyir
- [API Versioning](08-api-versioning.md) — REST versioning strategiyaları
- [HTTP Caching](09-http-caching.md) — REST cache-able, GraphQL caching çətin
- [WebSockets](06-websockets.md) — GraphQL Subscription vs WebSocket real-time comparison
