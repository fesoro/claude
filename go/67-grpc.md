# gRPC — protobuf, server/client, streaming, interceptor-lar (Lead)

## İcmal

gRPC — Google tərəfindən yaradılmış, HTTP/2 üzərində işləyən, Protocol Buffers (protobuf) serialization-lı RPC framework-dür. Microservice-lər arası daxili əlaqə üçün REST-dən 5–10x sürətli, type-safe, streaming dəstəkli alternativdir.

PHP REST API ilə müqayisədə: JSON → Protobuf (binary, ~3-10x kiçik), HTTP/1.1 → HTTP/2 (multiplexing), manual validation → proto schema, manual client → generated client. Laravel-də gRPC üçün `spiral/roadrunner-grpc` istifadə olunur, amma Go bu sahədə birinci sinifdədir.

## Niyə Vacibdir

- **Binary protocol**: JSON-a nisbətən 3–10x kiçik payload, daha sürətli serialization
- **Bidirectional streaming**: WebSocket kimi, amma type-safe və protocol-level
- **Code generation**: client + server stub-ları avtomatik — type xətaları yoxdur
- **Deadlines/cancellation**: context-based, built-in timeout propagation
- **Interceptor**: middleware kimi — auth, logging, metrics hər RPC-yə tətbiq edilir

## Əsas Anlayışlar

### 4 növ RPC

```protobuf
service UserService {
    // 1. Unary: bir sorğu, bir cavab (ən sadə)
    rpc GetUser (UserRequest) returns (UserResponse);

    // 2. Server streaming: bir sorğu, çox cavab
    rpc ListUsers (ListRequest) returns (stream UserResponse);

    // 3. Client streaming: çox sorğu, bir cavab
    rpc CreateBulk (stream CreateRequest) returns (BulkResult);

    // 4. Bidirectional streaming: çox sorğu, çox cavab
    rpc Chat (stream ChatMessage) returns (stream ChatMessage);
}
```

### Protobuf field nömrələri

```protobuf
message User {
    int32  id    = 1;   // field number — dəyişdirilməməlidir!
    string name  = 2;   // proto wire format-da istifadə olunur
    string email = 3;   // silinmiş sahə → reserved edin
}
```

### Status codes

```go
codes.OK, codes.NotFound, codes.InvalidArgument,
codes.Internal, codes.Unauthenticated, codes.PermissionDenied,
codes.Unavailable, codes.DeadlineExceeded, ...
```

HTTP status code-lardan daha zəngin semantika — client xəta tipini dəqiq anlaya bilir.

## Praktik Baxış

### gRPC vs REST

| Xüsusiyyət | gRPC | REST |
|-----------|------|------|
| Protokol | HTTP/2 | HTTP/1.1 |
| Format | Protobuf (binary) | JSON (text) |
| Sürət | Yüksək | Orta |
| Streaming | 4 növ | Yoxdur (SSE istisna) |
| Kod generasiyası | Avtomatik | Manual |
| Brauzer dəstəyi | Məhdud (grpc-web) | Tam |
| Debugging | Çətin (binary) | Asan (curl) |

### Nə vaxt gRPC, nə vaxt REST?

```
gRPC:
  ✓ Microservice-lər arası daxili əlaqə
  ✓ Streaming lazımdırsa
  ✓ Performans kritikdirsə
  ✓ Polyglot team (Go + Python + Java)

REST:
  ✓ Public API (third-party client-lər)
  ✓ Brauzer birbaşa çağırış edirsə
  ✓ Simple CRUD
  ✓ Debug asanlığı vacibdirsə
```

### Trade-off-lar

- Proto schema dəyişikliyi — field silinməsi mümkün deyil (backward compat), `reserved` işlədin
- Debugging: `grpcurl`, Postman gRPC dəstəyi; adi `curl` işləmir
- Load balancing: gRPC long-lived connections — L7 aware LB lazım (Nginx, Envoy)
- Browser: `grpc-web` proxy tələb edir

### Anti-pattern-lər

```go
// YANLIŞ: xəta dəyərini gizlətmək
return nil, err // clients.Internal xəta alır, mesaj yoxdur

// DOĞRU: status ilə bağlamaq
if errors.Is(err, ErrNotFound) {
    return nil, status.Errorf(codes.NotFound, "istifadəçi tapılmadı: %d", req.Id)
}
return nil, status.Errorf(codes.Internal, "daxili xəta: %v", err)

// YANLIŞ: field number-i dəyişdirmək
message User {
    string name = 1; // 1-ci field
    int32  id   = 2; // əvvəl name idi → proto format pozulur!
}

// DOĞRU: reserved istifadə et
message User {
    reserved 1; // köhnə name field
    int32  id   = 2;
    string name = 3; // yeni field number
}
```

## Nümunələr

### Nümunə 1: Proto definition + generated kod strukturu

```protobuf
// fayl: proto/user/v1/user.proto
syntax = "proto3";

package user.v1;

option go_package = "myapp/gen/user/v1;userv1";

import "google/protobuf/timestamp.proto";

// ==================== Messages ====================

message User {
    int32  id         = 1;
    string name       = 2;
    string email      = 3;
    string role       = 4;
    google.protobuf.Timestamp created_at = 5;
}

message GetUserRequest {
    int32 id = 1;
}

message GetUserResponse {
    User user = 1;
}

message ListUsersRequest {
    int32  page      = 1;
    int32  page_size = 2;
    string role      = 3; // filter
}

message CreateUserRequest {
    string name  = 1;
    string email = 2;
    string role  = 3;
}

message DeleteUserRequest {
    int32 id = 1;
}

message Empty {}

// ==================== Service ====================

service UserService {
    rpc GetUser    (GetUserRequest)    returns (GetUserResponse);
    rpc ListUsers  (ListUsersRequest)  returns (stream GetUserResponse);
    rpc CreateUser (CreateUserRequest) returns (GetUserResponse);
    rpc DeleteUser (DeleteUserRequest) returns (Empty);
}
```

```bash
# Kod generasiyası
protoc \
    --go_out=. \
    --go_opt=paths=source_relative \
    --go-grpc_out=. \
    --go-grpc_opt=paths=source_relative \
    proto/user/v1/user.proto
```

### Nümunə 2: gRPC Server — tam implementasiya

```go
package main

import (
    "context"
    "fmt"
    "log"
    "net"
    "time"

    "google.golang.org/grpc"
    "google.golang.org/grpc/codes"
    "google.golang.org/grpc/status"
    "google.golang.org/protobuf/types/known/timestamppb"

    pb "myapp/gen/user/v1"
)

// UserStore — in-memory DB simulyasiyası
type UserStore struct {
    users  map[int32]*pb.User
    nextID int32
}

func NewUserStore() *UserStore {
    return &UserStore{
        users:  make(map[int32]*pb.User),
        nextID: 1,
    }
}

// UserServer — gRPC server implementasiyası
type UserServer struct {
    pb.UnimplementedUserServiceServer // backward compat üçün
    store *UserStore
}

func NewUserServer(store *UserStore) *UserServer {
    return &UserServer{store: store}
}

// Unary RPC
func (s *UserServer) GetUser(ctx context.Context, req *pb.GetUserRequest) (*pb.GetUserResponse, error) {
    if req.Id <= 0 {
        return nil, status.Errorf(codes.InvalidArgument, "ID müsbət olmalıdır: %d", req.Id)
    }

    user, ok := s.store.users[req.Id]
    if !ok {
        return nil, status.Errorf(codes.NotFound, "istifadəçi tapılmadı: %d", req.Id)
    }

    return &pb.GetUserResponse{User: user}, nil
}

// Server streaming RPC
func (s *UserServer) ListUsers(req *pb.ListUsersRequest, stream pb.UserService_ListUsersServer) error {
    pageSize := int(req.PageSize)
    if pageSize <= 0 {
        pageSize = 10
    }

    count := 0
    for _, user := range s.store.users {
        // Context cancel yoxla
        if err := stream.Context().Err(); err != nil {
            return status.Errorf(codes.Canceled, "client ləğv etdi")
        }

        // Role filter
        if req.Role != "" && user.Role != req.Role {
            continue
        }

        if err := stream.Send(&pb.GetUserResponse{User: user}); err != nil {
            return status.Errorf(codes.Internal, "göndərmə xətası: %v", err)
        }

        count++
        if count >= pageSize {
            break
        }

        // Real DB-də bu olmaz, amma demo üçün
        time.Sleep(10 * time.Millisecond)
    }

    return nil
}

// Unary RPC — create
func (s *UserServer) CreateUser(ctx context.Context, req *pb.CreateUserRequest) (*pb.GetUserResponse, error) {
    if req.Name == "" || req.Email == "" {
        return nil, status.Errorf(codes.InvalidArgument, "ad və email tələb olunur")
    }

    // Email unikallıq yoxlaması
    for _, u := range s.store.users {
        if u.Email == req.Email {
            return nil, status.Errorf(codes.AlreadyExists, "email artıq istifadə olunur: %s", req.Email)
        }
    }

    id := s.store.nextID
    s.store.nextID++

    user := &pb.User{
        Id:        id,
        Name:      req.Name,
        Email:     req.Email,
        Role:      req.Role,
        CreatedAt: timestamppb.Now(),
    }
    s.store.users[id] = user

    return &pb.GetUserResponse{User: user}, nil
}

func (s *UserServer) DeleteUser(ctx context.Context, req *pb.DeleteUserRequest) (*pb.Empty, error) {
    if _, ok := s.store.users[req.Id]; !ok {
        return nil, status.Errorf(codes.NotFound, "istifadəçi tapılmadı: %d", req.Id)
    }
    delete(s.store.users, req.Id)
    return &pb.Empty{}, nil
}

func main() {
    lis, err := net.Listen("tcp", ":50051")
    if err != nil {
        log.Fatalf("Dinləmə xətası: %v", err)
    }

    store := NewUserStore()

    // Seed data
    store.users[1] = &pb.User{Id: 1, Name: "Orkhan", Email: "orkhan@example.com", Role: "admin"}
    store.users[2] = &pb.User{Id: 2, Name: "Əli", Email: "ali@example.com", Role: "user"}
    store.nextID = 3

    srv := grpc.NewServer(
        grpc.ChainUnaryInterceptor(
            LoggingInterceptor,
            RecoveryInterceptor,
        ),
    )

    pb.RegisterUserServiceServer(srv, NewUserServer(store))

    log.Printf("gRPC server :50051 portunda başladı")
    if err := srv.Serve(lis); err != nil {
        log.Fatalf("Server xətası: %v", err)
    }
}
```

### Nümunə 3: Interceptor-lar (Middleware)

```go
package main

import (
    "context"
    "log"
    "runtime/debug"
    "time"

    "google.golang.org/grpc"
    "google.golang.org/grpc/codes"
    "google.golang.org/grpc/metadata"
    "google.golang.org/grpc/status"
)

// LoggingInterceptor — hər RPC-ni log edir
func LoggingInterceptor(
    ctx context.Context,
    req interface{},
    info *grpc.UnaryServerInfo,
    handler grpc.UnaryHandler,
) (interface{}, error) {
    start := time.Now()

    resp, err := handler(ctx, req)

    duration := time.Since(start)
    code := codes.OK
    if err != nil {
        code = status.Code(err)
    }

    log.Printf("[gRPC] %s | %v | %v", info.FullMethod, code, duration)
    return resp, err
}

// RecoveryInterceptor — panic-i xətaya çevirir
func RecoveryInterceptor(
    ctx context.Context,
    req interface{},
    info *grpc.UnaryServerInfo,
    handler grpc.UnaryHandler,
) (resp interface{}, err error) {
    defer func() {
        if r := recover(); r != nil {
            log.Printf("[gRPC PANIC] %s: %v\n%s", info.FullMethod, r, debug.Stack())
            err = status.Errorf(codes.Internal, "daxili server xətası")
        }
    }()
    return handler(ctx, req)
}

// AuthInterceptor — metadata-dan token yoxlayır
func AuthInterceptor(secretKey []byte) grpc.UnaryServerInterceptor {
    return func(
        ctx context.Context,
        req interface{},
        info *grpc.UnaryServerInfo,
        handler grpc.UnaryHandler,
    ) (interface{}, error) {
        // Auth tələb etməyən metodlar
        public := map[string]bool{
            "/user.v1.UserService/GetUser": true,
        }
        if public[info.FullMethod] {
            return handler(ctx, req)
        }

        md, ok := metadata.FromIncomingContext(ctx)
        if !ok {
            return nil, status.Errorf(codes.Unauthenticated, "metadata yoxdur")
        }

        tokens := md.Get("authorization")
        if len(tokens) == 0 {
            return nil, status.Errorf(codes.Unauthenticated, "token tələb olunur")
        }

        // Token doğrulama (JWT yoxlanır)
        token := tokens[0]
        if !isValidToken(token, secretKey) {
            return nil, status.Errorf(codes.Unauthenticated, "etibarsız token")
        }

        return handler(ctx, req)
    }
}

func isValidToken(token string, secret []byte) bool {
    // Real implementasiyada JWT doğrulama
    return len(token) > 0
}

// Stream interceptor (server streaming üçün)
func StreamLoggingInterceptor(
    srv interface{},
    ss grpc.ServerStream,
    info *grpc.StreamServerInfo,
    handler grpc.StreamHandler,
) error {
    start := time.Now()
    err := handler(srv, ss)
    log.Printf("[gRPC Stream] %s | %v | %v", info.FullMethod, status.Code(err), time.Since(start))
    return err
}
```

### Nümunə 4: gRPC Client

```go
package main

import (
    "context"
    "fmt"
    "io"
    "log"
    "time"

    "google.golang.org/grpc"
    "google.golang.org/grpc/credentials/insecure"
    "google.golang.org/grpc/metadata"

    pb "myapp/gen/user/v1"
)

type UserClient struct {
    client pb.UserServiceClient
    conn   *grpc.ClientConn
}

func NewUserClient(addr string, token string) (*UserClient, error) {
    // Client interceptor — auth token əlavə et
    authInterceptor := func(
        ctx context.Context,
        method string,
        req, reply interface{},
        cc *grpc.ClientConn,
        invoker grpc.UnaryInvoker,
        opts ...grpc.CallOption,
    ) error {
        ctx = metadata.AppendToOutgoingContext(ctx, "authorization", token)
        return invoker(ctx, method, req, reply, cc, opts...)
    }

    conn, err := grpc.NewClient(addr,
        grpc.WithTransportCredentials(insecure.NewCredentials()),
        grpc.WithUnaryInterceptor(authInterceptor),
        // Reconnect
        grpc.WithBlock(),
    )
    if err != nil {
        return nil, fmt.Errorf("server-ə qoşulma: %w", err)
    }

    return &UserClient{
        client: pb.NewUserServiceClient(conn),
        conn:   conn,
    }, nil
}

func (c *UserClient) Close() error {
    return c.conn.Close()
}

func (c *UserClient) GetUser(id int32) (*pb.User, error) {
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    resp, err := c.client.GetUser(ctx, &pb.GetUserRequest{Id: id})
    if err != nil {
        return nil, fmt.Errorf("GetUser: %w", err)
    }
    return resp.User, nil
}

func (c *UserClient) StreamUsers(role string) ([]*pb.User, error) {
    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()

    stream, err := c.client.ListUsers(ctx, &pb.ListUsersRequest{
        PageSize: 100,
        Role:     role,
    })
    if err != nil {
        return nil, fmt.Errorf("ListUsers: %w", err)
    }

    var users []*pb.User
    for {
        resp, err := stream.Recv()
        if err == io.EOF {
            break
        }
        if err != nil {
            return nil, fmt.Errorf("stream oxuma: %w", err)
        }
        users = append(users, resp.User)
    }

    return users, nil
}

func main() {
    client, err := NewUserClient("localhost:50051", "my-jwt-token")
    if err != nil {
        log.Fatal(err)
    }
    defer client.Close()

    // Unary call
    user, err := client.GetUser(1)
    if err != nil {
        log.Println("GetUser xəta:", err)
    } else {
        fmt.Printf("İstifadəçi: %+v\n", user)
    }

    // Stream call
    users, err := client.StreamUsers("")
    if err != nil {
        log.Println("StreamUsers xəta:", err)
    } else {
        fmt.Printf("Cəmi %d istifadəçi\n", len(users))
        for _, u := range users {
            fmt.Printf("  %d: %s (%s)\n", u.Id, u.Name, u.Role)
        }
    }
}
```

### Nümunə 5: gRPC + REST gateway (grpc-gateway)

```go
// grpc-gateway — gRPC servisi avtomatik REST API-ya çevirir
// Proto-da HTTP annotation əlavə et:

/*
import "google/api/annotations.proto";

service UserService {
    rpc GetUser (GetUserRequest) returns (GetUserResponse) {
        option (google.api.http) = {
            get: "/v1/users/{id}"
        };
    }
    rpc CreateUser (CreateUserRequest) returns (GetUserResponse) {
        option (google.api.http) = {
            post: "/v1/users"
            body: "*"
        };
    }
}
*/

// main.go — həm gRPC həm REST:
package main

import (
    "context"
    "log"
    "net"
    "net/http"

    "github.com/grpc-ecosystem/grpc-gateway/v2/runtime"
    "google.golang.org/grpc"
    "google.golang.org/grpc/credentials/insecure"

    pb "myapp/gen/user/v1"
)

func runGateway(grpcAddr, httpAddr string) error {
    ctx := context.Background()
    mux := runtime.NewServeMux()

    opts := []grpc.DialOption{grpc.WithTransportCredentials(insecure.NewCredentials())}
    if err := pb.RegisterUserServiceHandlerFromEndpoint(ctx, mux, grpcAddr, opts); err != nil {
        return err
    }

    return http.ListenAndServe(httpAddr, mux)
}

func main() {
    // gRPC server
    lis, _ := net.Listen("tcp", ":50051")
    srv := grpc.NewServer()
    pb.RegisterUserServiceServer(srv, &UserServer{store: NewUserStore()})
    go srv.Serve(lis)

    // REST gateway
    log.Println("REST gateway :8080 portunda")
    if err := runGateway(":50051", ":8080"); err != nil {
        log.Fatal(err)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Bidirectional streaming:**
`Chat` RPC yazın: hər iki tərəf eyni anda mesaj göndərə bilsin. Bir `ChatMessage {user, text, timestamp}` stream göndərin, server echo etsin + timestamp əlavə etsin.

**Tapşırıq 2 — TLS gRPC:**
Self-signed sertifikat ilə TLS gRPC server qurun. Client `credentials.NewClientTLSFromCert` ilə qoşulsun.

**Tapşırıq 3 — Health check:**
`google.golang.org/grpc/health/grpc_health_v1` istifadə edərək health check endpoint əlavə edin. Kubernetes readiness probe üçün konfiqurasiya edin.

**Tapşırıq 4 — grpcurl test:**
```bash
# grpcurl ilə test edin:
grpcurl -plaintext localhost:50051 list
grpcurl -plaintext -d '{"id":1}' localhost:50051 user.v1.UserService/GetUser
```

**Tapşırıq 5 — Retry interceptor:**
Client-side retry interceptor yazın: `codes.Unavailable`, `codes.ResourceExhausted` xətalarını exponential backoff ilə max 3 dəfə yenidən cəhd etsin.

## Əlaqəli Mövzular

- [33-http-server](33-http-server.md) — HTTP server ilə müqayisə
- [34-http-client](34-http-client.md) — REST client
- [65-jwt-and-auth](65-jwt-and-auth.md) — gRPC auth interceptor
- [56-advanced-concurrency](56-advanced-concurrency.md) — concurrent streaming
- [73-microservices](73-microservices.md) — microservice arxitekturası
