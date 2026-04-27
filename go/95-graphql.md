# GraphQL — gqlgen ilə (Lead)

## İcmal

GraphQL — Facebook tərəfindən yaradılmış, müştərinin ehtiyac duyduğu sahələri özünün seçdiyi API sorğu dilidir. REST-dən fərqli olaraq bir endpoint (`/graphql`) var, client istədiyi field-ları query-də göstərir.

Go-da ən geniş yayılmış kitabxana **gqlgen**-dir. **Schema-first** yanaşması: əvvəl `.graphqls` faylında schema yazılır, sonra gqlgen Go interface-lər + boilerplate kod generasiya edir, developer yalnız resolver-ləri implement edir.

## Niyə Vacibdir

- Mobil client-lər fərqli field dəstəyi istəyir — REST-də overfetch/underfetch problemi
- Dashboard-lar mürəkkəb nested data istəyir — REST-də N HTTP call lazım olar
- Strongly typed schema — frontend developerləri type-safe client generasiya edə bilər
- Subscriptions — real-time WebSocket üzərindən

## Əsas Anlayışlar

### GraphQL tip sistemi

```graphql
# schema.graphqls

type Query {
    user(id: ID!): User
    users(filter: UserFilter, page: Int, perPage: Int): UserConnection!
}

type Mutation {
    createUser(input: CreateUserInput!): User!
    updateUser(id: ID!, input: UpdateUserInput!): User!
    deleteUser(id: ID!): Boolean!
}

type Subscription {
    orderStatusChanged(orderID: ID!): Order!
}

type User {
    id: ID!
    name: String!
    email: String!
    orders: [Order!]!
    createdAt: Time!
}

type Order {
    id: ID!
    status: OrderStatus!
    total: Float!
    items: [OrderItem!]!
}

enum OrderStatus {
    PENDING
    SHIPPED
    DELIVERED
    CANCELLED
}

input CreateUserInput {
    name: String!
    email: String!
    password: String!
}

type UserConnection {
    nodes: [User!]!
    totalCount: Int!
    pageInfo: PageInfo!
}

type PageInfo {
    hasNextPage: Boolean!
    endCursor: String
}

scalar Time
```

### gqlgen quraşdırma

```bash
go get github.com/99designs/gqlgen
go run github.com/99designs/gqlgen init
```

`gqlgen.yml` konfiqurasiyası:

```yaml
# gqlgen.yml
schema:
  - graph/schema.graphqls

exec:
  filename: graph/generated/generated.go
  package: generated

model:
  filename: graph/model/models_gen.go
  package: model

resolver:
  layout: follow-schema
  dir: graph
  package: graph

models:
  Time:
    model: github.com/99designs/gqlgen/graphql/introspection.Time
```

```bash
# Kod generasiya et
go run github.com/99designs/gqlgen generate
```

Bu komanda aşağıdakıları yaradır:
- `graph/generated/generated.go` — server implementation (toxunulmamalıdır)
- `graph/model/models_gen.go` — Go struct-ları
- `graph/resolver.go` — Resolver struct
- `graph/schema.resolvers.go` — implement ediləcək metodlar

### Resolver implementation

```go
// graph/schema.resolvers.go

func (r *queryResolver) User(ctx context.Context, id string) (*model.User, error) {
    userID, err := strconv.Atoi(id)
    if err != nil {
        return nil, fmt.Errorf("invalid id: %w", err)
    }

    user, err := r.userRepo.FindByID(ctx, userID)
    if err != nil {
        if errors.Is(err, ErrNotFound) {
            return nil, nil // GraphQL null qaytarır (nullable field üçün)
        }
        return nil, err
    }

    return mapUserToModel(user), nil
}

func (r *mutationResolver) CreateUser(ctx context.Context, input model.CreateUserInput) (*model.User, error) {
    // Auth check
    viewer := auth.FromContext(ctx)
    if viewer == nil {
        return nil, ErrUnauthenticated
    }

    user, err := r.userSvc.Create(ctx, input)
    if err != nil {
        var valErr *ValidationError
        if errors.As(err, &valErr) {
            return nil, &gqlerror.Error{
                Message: valErr.Error(),
                Extensions: map[string]any{"code": "VALIDATION_ERROR", "fields": valErr.Fields},
            }
        }
        return nil, err
    }

    return mapUserToModel(user), nil
}
```

### N+1 Problemi — DataLoader

GraphQL-in ən ciddi problemi: `users` sorğusunda hər user-in `orders`-ı ayrıca DB sorğusu ilə gətirilir.

```
100 user → 100 ayrı ORDER sorğusu = N+1
```

**Həll: DataLoader (batch loading)**

```bash
go get github.com/vikstrous/dataloadgen
```

```go
// DataLoader — eyni request daxilindəki bütün user ID-lərini toplayır,
// bir SQL IN sorğusu ilə gətirir, nəticəni paylaşır

type Loaders struct {
    UserOrders *dataloadgen.Loader[int, []*model.Order]
}

func NewLoaders(orderRepo OrderRepository) *Loaders {
    return &Loaders{
        UserOrders: dataloadgen.NewLoader(
            func(ctx context.Context, userIDs []int) ([][]*model.Order, []error) {
                // Bir sorğu: SELECT * FROM orders WHERE user_id IN (...)
                allOrders, err := orderRepo.FindByUserIDs(ctx, userIDs)
                if err != nil {
                    errs := make([]error, len(userIDs))
                    for i := range errs { errs[i] = err }
                    return nil, errs
                }

                // userID → orders map
                orderMap := make(map[int][]*model.Order)
                for _, o := range allOrders {
                    orderMap[o.UserID] = append(orderMap[o.UserID], o)
                }

                results := make([][]*model.Order, len(userIDs))
                for i, id := range userIDs {
                    results[i] = orderMap[id]
                }
                return results, nil
            },
            dataloadgen.WithWait(time.Millisecond),
        ),
    }
}

// Context-ə loaders yüklə:
func LoadersMiddleware(loaders *Loaders, next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        ctx := context.WithValue(r.Context(), loadersKey, loaders)
        next.ServeHTTP(w, r.WithContext(ctx))
    })
}

// User.Orders resolver-ində DataLoader istifadəsi:
func (r *userResolver) Orders(ctx context.Context, obj *model.User) ([]*model.Order, error) {
    loaders := ctx.Value(loadersKey).(*Loaders)
    userID, _ := strconv.Atoi(obj.ID)
    return loaders.UserOrders.Load(ctx, userID) // batch ediləcək
}
```

### Authentication

```go
// Middleware — JWT-ni context-ə qoy
func authMiddleware(next http.Handler) http.Handler {
    return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
        token := r.Header.Get("Authorization")
        if token != "" {
            user, err := verifyJWT(strings.TrimPrefix(token, "Bearer "))
            if err == nil {
                ctx := context.WithValue(r.Context(), userCtxKey, user)
                r = r.WithContext(ctx)
            }
        }
        next.ServeHTTP(w, r)
    })
}

// Resolver-lərdə auth yoxla:
func auth.FromContext(ctx context.Context) *User {
    u, _ := ctx.Value(userCtxKey).(*User)
    return u
}

// Directive ilə authorization:
// schema.graphqls:
// directive @auth on FIELD_DEFINITION
// directive @hasRole(role: String!) on FIELD_DEFINITION
//
// type Query {
//   adminStats: Stats @auth @hasRole(role: "admin")
// }
```

### Subscription

```go
// schema.graphqls:
// type Subscription { orderStatusChanged(orderID: ID!): Order! }

func (r *subscriptionResolver) OrderStatusChanged(ctx context.Context, orderID string) (<-chan *model.Order, error) {
    viewer := auth.FromContext(ctx)
    if viewer == nil {
        return nil, ErrUnauthenticated
    }

    ch := make(chan *model.Order, 1)

    go func() {
        sub := r.pubsub.Subscribe(fmt.Sprintf("order:%s", orderID))
        defer r.pubsub.Unsubscribe(fmt.Sprintf("order:%s", orderID), sub)

        for {
            select {
            case <-ctx.Done():
                close(ch)
                return
            case event := <-sub:
                ch <- mapOrderToModel(event)
            }
        }
    }()

    return ch, nil
}
```

### Server qurma

```go
import (
    "github.com/99designs/gqlgen/graphql/handler"
    "github.com/99designs/gqlgen/graphql/handler/transport"
    "github.com/99designs/gqlgen/graphql/playground"
    "myapp/graph"
    "myapp/graph/generated"
)

func main() {
    resolver := &graph.Resolver{
        userRepo:  repos.NewUserRepo(db),
        orderRepo: repos.NewOrderRepo(db),
        userSvc:   services.NewUserService(),
    }

    srv := handler.New(generated.NewExecutableSchema(generated.Config{Resolvers: resolver}))

    // Transport-lar
    srv.AddTransport(transport.POST{})
    srv.AddTransport(transport.Websocket{
        KeepAlivePingInterval: 10 * time.Second,
        Upgrader: websocket.Upgrader{
            CheckOrigin: func(r *http.Request) bool { return true },
        },
    })

    // Query complexity limiti (DOS qarşısı)
    srv.Use(extension.FixedComplexityLimit(100))

    // Introspection (production-da söndür):
    srv.Use(extension.Introspection{})

    mux := http.NewServeMux()
    mux.Handle("/graphql", authMiddleware(LoadersMiddleware(loaders, srv)))
    mux.Handle("/playground", playground.Handler("GraphQL Playground", "/graphql"))
}
```

### Error handling

```go
// gqlerror.Error — extensions ilə zəngin xəta
import "github.com/vektah/gqlparser/v2/gqlerror"

return nil, &gqlerror.Error{
    Message: "email already exists",
    Extensions: map[string]any{
        "code":  "DUPLICATE_EMAIL",
        "field": "email",
    },
}

// Response:
// { "errors": [{ "message": "email already exists", "extensions": { "code": "DUPLICATE_EMAIL" } }] }
```

## Trade-off-lar

| | GraphQL | REST |
|--|---------|------|
| Overfetch/Underfetch | Yox (client seçir) | Bəli |
| Caching | Mürəkkəb (HTTP cache yoxdur) | HTTP cache asan |
| Type safety | Schema-first | OpenAPI ilə |
| Learning curve | Yüksək | Az |
| File upload | Multipart spec | Standart |
| Real-time | Subscription | SSE/WebSocket əl ilə |
| Tooling | Playground, GraphiQL | Swagger UI |

**Nə vaxt GraphQL:**
- Çox client tipi (web, mobil, 3rd party) fərqli data ehtiyacları
- Mürəkkəb, nested data strukturları
- Rapid iteration — yeni field əlavə etmək backend dəyişikliyi tələb etmir

**Nə vaxt REST:**
- Sadə CRUD API
- Public API — caching kritikdir
- File upload ağır istifadə ediləcəkdir

## Praktik Tapşırıqlar

1. **Schema yaz:** User + Post + Comment schema-sı, query + mutation + subscription
2. **DataLoader:** Post-ların author-larını batch-lə gətir, N+1-in yox olduğunu `sqlx` log-la yoxla
3. **Auth directive:** `@auth` directive implement et, unauthenticated sorğuları rədd et
4. **Complexity limit:** `extension.FixedComplexityLimit(50)`, dərin nested sorğunu test et
5. **Subscription:** Order status dəyişdikdə WebSocket üzərindən client-ə göndər

## PHP ilə Müqayisə

```
Laravel                          Go
────────────────────────────────────────────────────────────
rebing/graphql-laravel      →   gqlgen (schema-first)
lighthouse-php               →   gqlgen
Eloquent N+1 → eager loading →   DataLoader
GraphQL::type()              →   .graphqls schema file
resolve()                    →   Resolver method
```

**Fərqlər:**
- gqlgen schema-first: schema `.graphqls`-da yazılır, Go kodu generasiya edilir; Lighthouse directive-based (PHP annotation)
- Go-da DataLoader əl ilə implement edilir; Lighthouse-da `@paginate`, `@hasMany` directive-ları N+1-i avtomatik həll edir
- gqlgen compile-time type safety — resolver interface-ə uyğun gəlmədikdə build fail olur
- PHP-də GraphQL çox vaxt REST-dən yavaş (ORM overhead), Go-da raw SQL + DataLoader çox sürətli

## Əlaqəli Mövzular

- [17-interfaces.md](17-interfaces.md) — Go interface-lər
- [27-goroutines-and-channels.md](27-goroutines-and-channels.md) — subscription channel-lar
- [33-http-server.md](33-http-server.md) — HTTP server
- [37-database.md](37-database.md) — database sorğuları
- [61-websocket.md](61-websocket.md) — subscription transport
- [65-jwt-and-auth.md](65-jwt-and-auth.md) — authentication middleware
- [94-pagination.md](94-pagination.md) — cursor-based pagination pattern
