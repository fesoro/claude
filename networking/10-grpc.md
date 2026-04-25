# gRPC (Middle)

## İcmal

gRPC Google tərəfindən yaradılmış, yüksək performanslı, open-source RPC (Remote Procedure Call) framework-dür. HTTP/2 üzərində işləyir və data serializasiyası üçün Protocol Buffers (protobuf) istifadə edir. Microservices arası kommunikasiya üçün idealdır.

```
REST:
  Client --> JSON over HTTP/1.1 --> Server
  Yavaş, text-based, schema yoxdur

gRPC:
  Client --> Protobuf over HTTP/2 --> Server
  Sürətli, binary, strongly typed schema
```

## Niyə Vacibdir

Microservices arxitekturasında servislər arası kommunikasiya performansı kritikdir. REST/JSON ilə müqayisədə gRPC ~50% daha az bandwidth istifadə edir, 5-10x daha sürətli serializasiya edir və güclü typing ilə compile-time xəta aşkarlaması verir. Polyglot sistemlərdə (müxtəlif dillər arasında) vahid `.proto` contract sayəsində API uyğunluğu avtomatik təmin olunur.

## Əsas Anlayışlar

### Protocol Buffers (Protobuf)

```protobuf
// user.proto - Service definition
syntax = "proto3";

package user;

option php_namespace = "App\\Grpc\\User";
option php_metadata_namespace = "App\\Grpc\\GPBMetadata";

// Service definition
service UserService {
  // Unary RPC - tək request, tək response
  rpc GetUser (GetUserRequest) returns (UserResponse);
  rpc CreateUser (CreateUserRequest) returns (UserResponse);
  rpc UpdateUser (UpdateUserRequest) returns (UserResponse);
  rpc DeleteUser (DeleteUserRequest) returns (DeleteResponse);
  rpc ListUsers (ListUsersRequest) returns (ListUsersResponse);

  // Server streaming - tək request, çoxlu response
  rpc WatchUser (WatchUserRequest) returns (stream UserEvent);

  // Client streaming - çoxlu request, tək response
  rpc UploadAvatar (stream AvatarChunk) returns (UploadResponse);

  // Bidirectional streaming - çoxlu request, çoxlu response
  rpc Chat (stream ChatMessage) returns (stream ChatMessage);
}

message GetUserRequest {
  int64 id = 1;
}

message CreateUserRequest {
  string name = 1;
  string email = 2;
  string password = 3;
  UserRole role = 4;
}

message UpdateUserRequest {
  int64 id = 1;
  optional string name = 2;
  optional string email = 3;
}

message DeleteUserRequest {
  int64 id = 1;
}

message UserResponse {
  int64 id = 1;
  string name = 2;
  string email = 3;
  UserRole role = 4;
  google.protobuf.Timestamp created_at = 5;
}

message ListUsersRequest {
  int32 page = 1;
  int32 per_page = 2;
  string search = 3;
}

message ListUsersResponse {
  repeated UserResponse users = 1;
  int32 total = 2;
  int32 page = 3;
}

message DeleteResponse {
  bool success = 1;
}

enum UserRole {
  USER_ROLE_UNSPECIFIED = 0;
  USER_ROLE_USER = 1;
  USER_ROLE_ADMIN = 2;
  USER_ROLE_EDITOR = 3;
}

message WatchUserRequest {
  int64 user_id = 1;
}

message UserEvent {
  string event_type = 1;  // "updated", "deleted"
  UserResponse user = 2;
  google.protobuf.Timestamp timestamp = 3;
}

message AvatarChunk {
  int64 user_id = 1;
  bytes data = 2;
}

message UploadResponse {
  string url = 1;
  int64 size = 2;
}

message ChatMessage {
  string sender = 1;
  string content = 2;
  google.protobuf.Timestamp timestamp = 3;
}
```

### 4 RPC Növü

```
1. UNARY RPC (ən çox istifadə olunan)
   Client ----> Request ----> Server
   Client <---- Response <--- Server

2. SERVER STREAMING
   Client ----> Request -----------> Server
   Client <---- Response 1 <-------- Server
   Client <---- Response 2 <-------- Server
   Client <---- Response N <-------- Server

3. CLIENT STREAMING
   Client ----> Request 1 ---------> Server
   Client ----> Request 2 ---------> Server
   Client ----> Request N ---------> Server
   Client <---- Response <---------- Server

4. BIDIRECTIONAL STREAMING
   Client ----> Request 1 ---------> Server
   Client <---- Response 1 <-------- Server
   Client ----> Request 2 ---------> Server
   Client <---- Response 2 <-------- Server
   (hər iki tərəf müstəqil göndərə bilər)
```

### Protobuf Binary Encoding

```
JSON (91 bytes):
{"id":42,"name":"Orkhan","email":"orkhan@example.com","role":"admin"}

Protobuf (~45 bytes):
08 2A 12 06 4F 72 6B 68 61 6E 1A 13 6F 72 6B...

~50% daha kiçik, ~5-10x daha sürətli serialize/deserialize
```

### gRPC vs REST

```
+-------------------+-------------------+-------------------+
| Feature           | gRPC              | REST              |
+-------------------+-------------------+-------------------+
| Protocol          | HTTP/2            | HTTP/1.1 (mostly) |
| Data format       | Protobuf (binary) | JSON (text)       |
| Schema            | .proto file       | OpenAPI (optional)|
| Streaming         | Bidirectional     | Limited           |
| Code generation   | Automatic         | Manual/tools      |
| Browser support   | gRPC-Web lazımdır | Native            |
| Performance       | Yüksək            | Orta              |
| Human readable    | Xeyr              | Bəli              |
| Tooling           | Məhdud            | Bol (Postman etc) |
| Use case          | Microservices     | Public APIs       |
+-------------------+-------------------+-------------------+
```

### gRPC Status Codes

```
OK                  = 0   (Uğurlu)
CANCELLED           = 1   (Client ləğv etdi)
UNKNOWN             = 2   (Naməlum xəta)
INVALID_ARGUMENT    = 3   (Yanlış argument)
DEADLINE_EXCEEDED   = 4   (Timeout)
NOT_FOUND           = 5   (Tapılmadı)
ALREADY_EXISTS      = 6   (Artıq mövcuddur)
PERMISSION_DENIED   = 7   (İcazə yoxdur)
UNAUTHENTICATED     = 16  (Authentication lazımdır)
RESOURCE_EXHAUSTED  = 8   (Rate limit)
UNIMPLEMENTED       = 12  (Method implement olunmayıb)
INTERNAL            = 13  (Server xətası)
UNAVAILABLE         = 14  (Service müvəqqəti əlçatmaz)
```

### Interceptors (Middleware)

```
Client Interceptor:
  [Auth Token] -> [Logging] -> [Retry] -> gRPC Call

Server Interceptor:
  gRPC Request -> [Auth Check] -> [Logging] -> [Rate Limit] -> Handler
```

### Deadlines & Timeouts

```
Client deadline: "Bu request 5 saniyədə cavab almalıdır"
Əgər timeout olursa -> DEADLINE_EXCEEDED status

Deadline propagation (microservices chain):
  Client (5s deadline) -> Service A (4.5s remaining) -> Service B (4s remaining)
```

## Praktik Baxış

**Nə vaxt gRPC istifadə etmək lazımdır:**
- Internal microservices kommunikasiyası (public API deyil)
- Low-latency, yüksək throughput tələb olunan yerlər
- Polyglot sistemlər (müxtəlif proqramlaşdırma dilləri)
- Bidirectional streaming lazım olan hallarda

**Nə vaxt REST seçmək lazımdır:**
- Public API (browser, mobil, third-party)
- Simple CRUD əməliyyatları
- Tooling (Postman, Swagger) vacib olanda
- Human-readable format lazım olanda

**Trade-off-lar:**
- gRPC browser-də birbaşa işləmir — gRPC-Web proxy (Envoy) lazımdır
- Proto file-ların versiyalanması əlavə iş yaradır
- Debugging JSON-dan çətindir (binary format)
- PHP-də gRPC server üçün RoadRunner/Spiral lazımdır

**Anti-pattern-lər:**
- Public REST API-ni gRPC ilə əvəz etmək (browser uyğunluğu pozulur)
- Field number-lərini dəyişmək (backward compatibility pozulur)
- Streaming lazım olmayanda bidirectional streaming seçmək

## Nümunələr

### Ümumi Nümunə

Tipik real layihə sxemi: PHP/Laravel frontend public REST API təqdim edir, daxildə isə gRPC ilə microserviceslərə müraciət edir:

```
[Browser] --REST/JSON--> [Laravel API Gateway] --gRPC/Protobuf--> [User Service]
                                                               --> [Order Service]
                                                               --> [Payment Service]
```

### Kod Nümunəsi

**PHP gRPC Setup:**

```bash
# gRPC PHP extension install
pecl install grpc
pecl install protobuf

# php.ini-yə əlavə edin
# extension=grpc.so
# extension=protobuf.so

# Proto-dan PHP code generate edin
protoc --php_out=./generated \
       --grpc_out=./generated \
       --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
       user.proto
```

**gRPC Client in Laravel:**

```php
namespace App\Services;

use App\Grpc\User\UserServiceClient;
use App\Grpc\User\GetUserRequest;
use App\Grpc\User\CreateUserRequest;
use App\Grpc\User\ListUsersRequest;
use App\Grpc\User\UserResponse;
use Grpc\ChannelCredentials;

class UserGrpcClient
{
    private UserServiceClient $client;

    public function __construct()
    {
        $this->client = new UserServiceClient(
            config('grpc.user_service_host', 'localhost:50051'),
            [
                'credentials' => ChannelCredentials::createInsecure(),
                // Production-da SSL istifadə edin:
                // 'credentials' => ChannelCredentials::createSsl(
                //     file_get_contents('/path/to/ca.pem')
                // ),
            ]
        );
    }

    public function getUser(int $id): array
    {
        $request = new GetUserRequest();
        $request->setId($id);

        /** @var UserResponse $response */
        [$response, $status] = $this->client->GetUser($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException(
                "gRPC error: {$status->details}",
                $status->code
            );
        }

        return [
            'id' => $response->getId(),
            'name' => $response->getName(),
            'email' => $response->getEmail(),
            'role' => $response->getRole(),
        ];
    }

    public function createUser(string $name, string $email, string $password): array
    {
        $request = new CreateUserRequest();
        $request->setName($name);
        $request->setEmail($email);
        $request->setPassword($password);

        [$response, $status] = $this->client->CreateUser($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException("gRPC error: {$status->details}");
        }

        return [
            'id' => $response->getId(),
            'name' => $response->getName(),
            'email' => $response->getEmail(),
        ];
    }

    public function listUsers(int $page = 1, int $perPage = 15): array
    {
        $request = new ListUsersRequest();
        $request->setPage($page);
        $request->setPerPage($perPage);

        [$response, $status] = $this->client->ListUsers($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException("gRPC error: {$status->details}");
        }

        $users = [];
        foreach ($response->getUsers() as $user) {
            $users[] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ];
        }

        return [
            'users' => $users,
            'total' => $response->getTotal(),
            'page' => $response->getPage(),
        ];
    }

    public function __destruct()
    {
        $this->client->close();
    }
}
```

**Laravel Service Provider:**

```php
namespace App\Providers;

use App\Services\UserGrpcClient;
use Illuminate\Support\ServiceProvider;

class GrpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserGrpcClient::class, function () {
            return new UserGrpcClient();
        });
    }
}
```

**gRPC Server with RoadRunner/Spiral:**

```php
// Spiral Framework ilə gRPC server (PHP-də ən populyar yol)
namespace App\Grpc\Service;

use App\Grpc\User\GetUserRequest;
use App\Grpc\User\UserResponse;
use App\Grpc\User\UserServiceInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;

class UserService implements UserServiceInterface
{
    public function GetUser(
        ContextInterface $ctx,
        GetUserRequest $request
    ): UserResponse {
        $user = \App\Models\User::findOrFail($request->getId());

        $response = new UserResponse();
        $response->setId($user->id);
        $response->setName($user->name);
        $response->setEmail($user->email);

        return $response;
    }
}
```

**REST-to-gRPC Bridge in Laravel:**

```php
namespace App\Http\Controllers\Api;

use App\Services\UserGrpcClient;
use Illuminate\Http\JsonResponse;

class UserBridgeController extends Controller
{
    public function __construct(
        private UserGrpcClient $grpcClient
    ) {}

    /**
     * REST API -> gRPC microservice
     * Public REST API saxlayırıq, amma backend gRPC istifadə edir
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = $this->grpcClient->getUser($id);
            return response()->json(['data' => $user]);
        } catch (\RuntimeException $e) {
            return response()->json(
                ['error' => $e->getMessage()],
                $this->grpcToHttpStatus($e->getCode())
            );
        }
    }

    private function grpcToHttpStatus(int $grpcCode): int
    {
        return match ($grpcCode) {
            0 => 200,   // OK
            3 => 400,   // INVALID_ARGUMENT
            5 => 404,   // NOT_FOUND
            7 => 403,   // PERMISSION_DENIED
            16 => 401,  // UNAUTHENTICATED
            default => 500,
        };
    }
}
```

## Praktik Tapşırıqlar

1. **Proto file yaradın:** `user.proto` faylı yazın, `UserService` servisini `GetUser`, `ListUsers`, `CreateUser` methodları ilə təyin edin. Uyğun message tiplərini əlavə edin.

2. **PHP client implement edin:** `UserGrpcClient` class-ı yazın, `GrpcServiceProvider` ilə Laravel-ə register edin. `getUser()` methodunu test edin.

3. **REST-to-gRPC bridge:** Laravel controller yazın — gelen REST request-i qəbul etsin, gRPC client ilə microservice-ə sorğu göndərsin, HTTP status-u gRPC status-dan düzgün map etsin.

4. **Error handling əlavə edin:** `DEADLINE_EXCEEDED`, `NOT_FOUND`, `UNAVAILABLE` status kodlarını ayrı-ayrı exception-lara çevirin. Retry logic əlavə edin (`UNAVAILABLE` üçün 3 cəhd).

5. **Interceptor yazın:** Server tərəfdə authentication interceptor implement edin — `Authorization` metadata-sından JWT token oxusun, verify etsin.

6. **End-to-end test:** PHP-də gRPC server (RoadRunner ilə) qaldırın, Laravel client ilə `CreateUser` → `GetUser` → `ListUsers` axışını test edin.

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [GraphQL](09-graphql.md)
- [Protocol Buffers](39-protocol-buffers.md)
- [WebSocket](11-websocket.md)
- [API Gateway](21-api-gateway.md)
