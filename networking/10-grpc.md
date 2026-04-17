# gRPC (gRPC Remote Procedure Call)

## Nədir? (What is it?)

gRPC Google terefinden yaradilmis, yuksek performansli, open-source RPC (Remote Procedure Call) framework-dur. HTTP/2 uzerinde isleyir ve data serializasiyasi ucun Protocol Buffers (protobuf) istifade edir. Microservices arasi kommunikasiya ucun idealdir.

```
REST:
  Client --> JSON over HTTP/1.1 --> Server
  Yavash, text-based, schema yoxdur

gRPC:
  Client --> Protobuf over HTTP/2 --> Server
  Suretli, binary, strongly typed schema
```

gRPC "g" her release-de ferqli meaning dasiyir (good, great, green...).

## Necə İşləyir? (How does it work?)

### Protocol Buffers (Protobuf)

```protobuf
// user.proto - Service definition
syntax = "proto3";

package user;

option php_namespace = "App\\Grpc\\User";
option php_metadata_namespace = "App\\Grpc\\GPBMetadata";

// Service definition
service UserService {
  // Unary RPC - tek request, tek response
  rpc GetUser (GetUserRequest) returns (UserResponse);
  rpc CreateUser (CreateUserRequest) returns (UserResponse);
  rpc UpdateUser (UpdateUserRequest) returns (UserResponse);
  rpc DeleteUser (DeleteUserRequest) returns (DeleteResponse);
  rpc ListUsers (ListUsersRequest) returns (ListUsersResponse);

  // Server streaming - tek request, coxlu response
  rpc WatchUser (WatchUserRequest) returns (stream UserEvent);

  // Client streaming - coxlu request, tek response
  rpc UploadAvatar (stream AvatarChunk) returns (UploadResponse);

  // Bidirectional streaming - coxlu request, coxlu response
  rpc Chat (stream ChatMessage) returns (stream ChatMessage);
}

// Messages
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

// Streaming messages
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

### 4 RPC Novleri

```
1. UNARY RPC (en cox istifade olunan)
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
   (her iki teref mustaqil gondere biler)
```

### Protobuf Binary Encoding

```
JSON (91 bytes):
{"id":42,"name":"Orkhan","email":"orkhan@example.com","role":"admin"}

Protobuf (~45 bytes):
08 2A 12 06 4F 72 6B 68 61 6E 1A 13 6F 72 6B...

~50% daha kicik, ~5-10x daha suretli serialize/deserialize
```

## Əsas Konseptlər (Key Concepts)

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
| Browser support   | gRPC-Web lazimdir | Native            |
| Performance       | Yuksek            | Orta              |
| Human readable    | Xeyr              | Beli              |
| Tooling           | Mehdud            | Bol (Postman etc) |
| Use case          | Microservices     | Public APIs       |
+-------------------+-------------------+-------------------+
```

### gRPC Status Codes

```
OK                  = 0   (Ugurlu)
CANCELLED           = 1   (Client legv etdi)
UNKNOWN             = 2   (Namelum xeta)
INVALID_ARGUMENT    = 3   (Yanlis argument)
DEADLINE_EXCEEDED   = 4   (Timeout)
NOT_FOUND           = 5   (Tapilmadi)
ALREADY_EXISTS      = 6   (Artiq movcuddur)
PERMISSION_DENIED   = 7   (Icaze yoxdur)
UNAUTHENTICATED     = 16  (Authentication lazimdir)
RESOURCE_EXHAUSTED  = 8   (Rate limit)
UNIMPLEMENTED       = 12  (Method implement olunmayib)
INTERNAL            = 13   (Server xetasi)
UNAVAILABLE         = 14   (Service muvqqeti elcatmaz)
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
Client deadline: "Bu request 5 saniyede cavab almalidır"
Eger timeout olursa -> DEADLINE_EXCEEDED status

Deadline propagation (microservices chain):
  Client (5s deadline) -> Service A (4.5s remaining) -> Service B (4s remaining)
```

## PHP/Laravel ilə İstifadə

### PHP gRPC Setup

```bash
# gRPC PHP extension install
pecl install grpc
pecl install protobuf

# php.ini-ye elave edin
# extension=grpc.so
# extension=protobuf.so

# Protobuf compiler
# protoc ve grpc_php_plugin install edin

# Proto-dan PHP code generate edin
protoc --php_out=./generated \
       --grpc_out=./generated \
       --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
       user.proto
```

### gRPC Client in Laravel

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
                // Production-da SSL istifade edin:
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

### Laravel Service Provider

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

### gRPC Server with RoadRunner/Spiral

```php
// Spiral Framework ile gRPC server (PHP-de en populyar yol)
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

### REST-to-gRPC Bridge in Laravel

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
     * Public REST API saxlayiriq, amma backend gRPC istifade edir
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

## Interview Sualları

### 1. gRPC nedir ve ne vaxt istifade olunur?
**Cavab:** gRPC Google-un high-performance RPC framework-udur. HTTP/2 + Protobuf istifade edir. Microservices arasi kommunikasiya, low-latency lazim olan yerler, polyglot sistemler (muxtelif diller arasi) ucun idealdir. Public API ucun REST, internal API ucun gRPC istifade olunur.

### 2. Protocol Buffers nedir ve JSON-dan nece ferqlenir?
**Cavab:** Protobuf Google-un binary serialization formatıdır. JSON-dan ferqi: schema required (.proto file), binary format (daha kicik, daha suretli), strong typing, backward/forward compatibility. Dezavantajı: human-readable deyil.

### 3. gRPC-nin 4 RPC tipi hansilardir?
**Cavab:** 1) **Unary** - tek request/response (standart), 2) **Server Streaming** - tek request, coxlu response (meselen, live updates), 3) **Client Streaming** - coxlu request, tek response (meselen, file upload), 4) **Bidirectional Streaming** - her iki terefden coxlu mesaj (meselen, chat).

### 4. gRPC-de deadline/timeout nece isleyir?
**Cavab:** Client request gonderende maximum gozleme muddeti teyin edir. Bu deadline microservices chain boyunca propagate olur ve her service qalan vakti bilir. Timeout olunca DEADLINE_EXCEEDED status qaytarilir. Bu cascading failure-in qarsisini alir.

### 5. gRPC niye browser-de birbaşa islemir?
**Cavab:** Browserler HTTP/2 frame-lerine low-level erisim vermir ve trailer-leri duzgun desteklemirlir. gRPC-Web proxy (Envoy) istifade olunur - browser HTTP/1.1 ile proxy-ye, proxy gRPC ile backend-e danisir.

### 6. gRPC vs REST - hansi daha yaxsidir?
**Cavab:** Asilidir: gRPC internal microservices, low-latency, streaming ucun. REST public APIs, browser compatibility, simplicity ucun. Cox vaxt ikisi birlikde istifade olunur - public REST API + internal gRPC.

### 7. Protobuf-da backward compatibility nece saxlanir?
**Cavab:** Field number-leri deyismeyin, kohne field-leri silmeyin (reserved edin), yeni field-ler optional olmalidir, enum-da default 0 value olmalidir. Bu kohne client-lerin yeni server ile islemesini temin edir.

## Best Practices

1. **Proto file-lari version control-da saxlayin** - Ayri repo ve ya monorepo
2. **Field numberlari deyismeyin** - Backward compatibility ucun
3. **Deadline/timeout hemise teyin edin** - Cascading failure qarsisini alin
4. **Health checking implement edin** - gRPC Health Checking Protocol
5. **Interceptors istifade edin** - Logging, auth, metrics, retry
6. **Load balancing** - Client-side ve ya proxy-based (Envoy)
7. **TLS hemise istifade edin** - Production-da mutual TLS
8. **Error details qaytarin** - Status code + error message + details
9. **Streaming lazim olmayanda Unary istifade edin** - Basitlik ucun
10. **gRPC-Web proxy** - Browser client-ler ucun Envoy proxy qurun
