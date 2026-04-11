# Spring GraphQL — Geniş İzah

## Mündəricat
1. [GraphQL nədir?](#graphql-nədir)
2. [Spring for GraphQL quraşdırma](#spring-for-graphql-quraşdırma)
3. [Schema — SDL tərifi](#schema--sdl-tərifi)
4. [Controller & DataFetcher](#controller--datafetcher)
5. [Mutations & Subscriptions](#mutations--subscriptions)
6. [DataLoader — N+1 həlli](#dataloader--n1-həlli)
7. [İntervyu Sualları](#intervyu-sualları)

---

## GraphQL nədir?

```
GraphQL — Facebook (2015), query language for APIs

REST problemləri:
  Over-fetching:
    GET /users/1 → {id, name, email, address, phone, avatar, ...}
    Client yalnız name lazımdır, amma hamısı gəlir

  Under-fetching:
    GET /users/1         → user məlumatı
    GET /users/1/orders  → sifarişlər
    GET /orders/5/items  → sifariş məhsulları
    → 3 sorğu! N+1 problem

GraphQL həlli:
  Bir endpoint: POST /graphql
  Client nə istəyini özü müəyyən edir:

  query {
    user(id: "1") {
      name
      orders {
        id
        total
        items {
          productName
          quantity
        }
      }
    }
  }

  → Bir sorğu, tam lazımlı data!

Operasiya növləri:
  query      → Oxuma (GET)
  mutation   → Yazma/dəyişdirmə (POST/PUT/DELETE)
  subscription → Real-time (WebSocket)

REST vs GraphQL:
  REST:
    ✅ Sadə, cache-friendly, HTTP semantika
    ❌ Over/under-fetching, versioning
  GraphQL:
    ✅ Precise data, bir endpoint, strongly typed
    ❌ Caching çətin, fayl upload mürəkkəb, N+1 riski
```

---

## Spring for GraphQL quraşdırma

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-graphql</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
    <!-- WebFlux üçün: spring-boot-starter-webflux -->
</dependency>
```

```yaml
# application.yml
spring:
  graphql:
    graphiql:
      enabled: true            # /graphiql — browser UI (dev üçün)
      path: /graphiql
    schema:
      locations: classpath:graphql/  # .graphqls faylları
      file-extensions: .graphqls,.gqls
    path: /graphql              # API endpoint
    websocket:
      path: /graphql-ws         # Subscription üçün

# GraphQL introspection (prod-da söndür!)
spring:
  graphql:
    schema:
      introspection:
        enabled: false
```

---

## Schema — SDL tərifi

```graphql
# src/main/resources/graphql/schema.graphqls

# ─── Scalar types ─────────────────────────────────────────
scalar DateTime
scalar BigDecimal
scalar Upload

# ─── Types ────────────────────────────────────────────────
type User {
    id: ID!                    # ! = non-null (məcburi)
    name: String!
    email: String!
    createdAt: DateTime!
    orders: [Order!]!          # Boş olmayan siyahı
}

type Order {
    id: ID!
    status: OrderStatus!
    total: BigDecimal!
    items: [OrderItem!]!
    customer: User!
    createdAt: DateTime!
}

type OrderItem {
    id: ID!
    product: Product!
    quantity: Int!
    unitPrice: BigDecimal!
    subtotal: BigDecimal!
}

type Product {
    id: ID!
    name: String!
    price: BigDecimal!
    stock: Int!
    category: String
}

# ─── Enum ─────────────────────────────────────────────────
enum OrderStatus {
    PENDING
    CONFIRMED
    SHIPPED
    DELIVERED
    CANCELLED
}

# ─── Input types ──────────────────────────────────────────
input CreateOrderInput {
    customerId: ID!
    items: [OrderItemInput!]!
}

input OrderItemInput {
    productId: ID!
    quantity: Int!
}

input UpdateProductInput {
    name: String
    price: BigDecimal
    stock: Int
}

# ─── Pagination ───────────────────────────────────────────
type OrderConnection {
    edges: [OrderEdge!]!
    pageInfo: PageInfo!
    totalCount: Int!
}

type OrderEdge {
    node: Order!
    cursor: String!
}

type PageInfo {
    hasNextPage: Boolean!
    hasPreviousPage: Boolean!
    startCursor: String
    endCursor: String
}

# ─── Query ────────────────────────────────────────────────
type Query {
    # User queries
    user(id: ID!): User
    users(limit: Int = 10, offset: Int = 0): [User!]!

    # Order queries
    order(id: ID!): Order
    orders(
        status: OrderStatus
        first: Int
        after: String
    ): OrderConnection!

    # Product queries
    product(id: ID!): Product
    products(
        category: String
        minPrice: BigDecimal
        maxPrice: BigDecimal
    ): [Product!]!

    # Search
    search(query: String!): SearchResult!
}

# ─── Mutation ─────────────────────────────────────────────
type Mutation {
    createOrder(input: CreateOrderInput!): Order!
    cancelOrder(id: ID!): Order!
    updateProduct(id: ID!, input: UpdateProductInput!): Product!
}

# ─── Subscription ─────────────────────────────────────────
type Subscription {
    orderStatusChanged(orderId: ID!): Order!
    newOrder: Order!
}

# ─── Union & Interface ────────────────────────────────────
interface Node {
    id: ID!
}

union SearchResult = User | Order | Product
```

---

## Controller & DataFetcher

```java
// ─── @QueryMapping — Query resolvers ──────────────────────
@Controller
public class UserGraphQLController {

    private final UserService userService;

    // query { user(id: "1") { ... } }
    @QueryMapping
    public User user(@Argument String id) {
        return userService.findById(id)
            .orElseThrow(() -> new GraphQlException("İstifadəçi tapılmadı: " + id));
    }

    // query { users(limit: 10, offset: 0) { ... } }
    @QueryMapping
    public List<User> users(
            @Argument int limit,
            @Argument int offset) {
        return userService.findAll(limit, offset);
    }

    // User.orders — nested resolver
    // user → orders (ayrı query etmək lazım deyil!)
    @SchemaMapping(typeName = "User", field = "orders")
    public List<Order> orders(User user) {
        return orderService.findByCustomerId(user.getId());
    }
}

// ─── @QueryMapping — Order controller ────────────────────
@Controller
public class OrderGraphQLController {

    private final OrderService orderService;

    @QueryMapping
    public Order order(@Argument String id) {
        return orderService.findById(id).orElseThrow();
    }

    // Cursor-based pagination
    @QueryMapping
    public Connection<Order> orders(
            @Argument OrderStatus status,
            ScrollSubrange subrange) {

        // Spring GraphQL Cursor Pagination dəstəyi
        PageRequest pageRequest = subrange.position()
            .map(pos -> PageRequest.of(0, subrange.count().orElse(10)))
            .orElse(PageRequest.of(0, subrange.count().orElse(10)));

        ScrollPosition position = subrange.position()
            .map(CursorScrollPosition::of)
            .orElse(ScrollPosition.offset());

        Window<Order> window = orderService.findAll(status, pageRequest, position);
        return ConnectionUtils.toConnection(window);
    }

    // Order.customer — nested resolver
    @SchemaMapping(typeName = "Order", field = "customer")
    public User customer(Order order) {
        return userService.findById(order.getCustomerId()).orElseThrow();
    }
}

// ─── Authentication Context ───────────────────────────────
@Controller
public class SecureOrderController {

    @QueryMapping
    @PreAuthorize("isAuthenticated()")
    public List<Order> myOrders(
            @AuthenticationPrincipal UserDetails userDetails) {
        return orderService.findByEmail(userDetails.getUsername());
    }

    @QueryMapping
    public User me(@AuthenticationPrincipal UserDetails userDetails) {
        return userService.findByEmail(userDetails.getUsername()).orElseThrow();
    }
}

// ─── Custom scalar ────────────────────────────────────────
@Configuration
public class GraphQLScalarConfig {

    @Bean
    public RuntimeWiringConfigurer runtimeWiringConfigurer() {
        return wiringBuilder -> wiringBuilder
            .scalar(ExtendedScalars.DateTime)      // DateTime scalar
            .scalar(ExtendedScalars.GraphQLBigDecimal); // BigDecimal scalar
    }
}

// ─── Error handling ───────────────────────────────────────
@Component
public class GraphQLExceptionResolver
        implements DataFetcherExceptionResolverAdapter {

    @Override
    protected GraphQLError resolveToSingleError(Throwable ex,
            DataFetchingEnvironment env) {
        if (ex instanceof NotFoundException e) {
            return GraphqlErrorBuilder.newError(env)
                .errorType(ErrorType.NOT_FOUND)
                .message(e.getMessage())
                .build();
        }
        if (ex instanceof ValidationException e) {
            return GraphqlErrorBuilder.newError(env)
                .errorType(ErrorType.BAD_REQUEST)
                .message(e.getMessage())
                .extensions(Map.of("fields", e.getViolations()))
                .build();
        }
        return null; // Digər xətalar default olaraq işlənir
    }
}
```

---

## Mutations & Subscriptions

```java
// ─── Mutations ────────────────────────────────────────────
@Controller
public class OrderMutationController {

    private final OrderService orderService;

    // mutation { createOrder(input: {...}) { id status total } }
    @MutationMapping
    @PreAuthorize("isAuthenticated()")
    public Order createOrder(
            @Argument CreateOrderInput input,
            @AuthenticationPrincipal UserDetails user) {
        return orderService.create(input, user.getUsername());
    }

    // mutation { cancelOrder(id: "42") { id status } }
    @MutationMapping
    public Order cancelOrder(@Argument String id) {
        return orderService.cancel(id);
    }

    // mutation { updateProduct(id: "1", input: { price: 25.99 }) { ... } }
    @MutationMapping
    @PreAuthorize("hasRole('ADMIN')")
    public Product updateProduct(
            @Argument String id,
            @Argument UpdateProductInput input) {
        return productService.update(id, input);
    }
}

// ─── Input record-ları ────────────────────────────────────
public record CreateOrderInput(
    String customerId,
    List<OrderItemInput> items
) {}

public record OrderItemInput(
    String productId,
    int quantity
) {}

public record UpdateProductInput(
    String name,
    BigDecimal price,
    Integer stock
) {}

// ─── Subscriptions — WebSocket ────────────────────────────
@Controller
public class OrderSubscriptionController {

    private final OrderEventService eventService;

    // subscription { orderStatusChanged(orderId: "42") { status } }
    @SubscriptionMapping
    public Flux<Order> orderStatusChanged(@Argument String orderId) {
        return eventService.watchOrder(orderId)
            .filter(order -> order.getId().equals(orderId));
    }

    // subscription { newOrder { id customer { name } total } }
    @SubscriptionMapping
    @PreAuthorize("hasRole('ADMIN')")
    public Flux<Order> newOrder() {
        return eventService.newOrderStream();
    }
}

// ─── Event service ────────────────────────────────────────
@Service
public class OrderEventService {

    private final Sinks.Many<Order> orderSink =
        Sinks.many().multicast().onBackpressureBuffer();

    public void publishOrderUpdate(Order order) {
        orderSink.tryEmitNext(order);
    }

    public Flux<Order> watchOrder(String orderId) {
        return orderSink.asFlux()
            .filter(o -> o.getId().equals(orderId));
    }

    public Flux<Order> newOrderStream() {
        return orderSink.asFlux();
    }
}
```

---

## DataLoader — N+1 həlli

```java
// ─── N+1 Problem ─────────────────────────────────────────
// query { orders { customer { name } } }
// → 1 query orders (N sifariş)
// → N query customer (hər sifariş üçün ayrı)
// = N+1 problem!

// ─── DataLoader həlli ─────────────────────────────────────
// Bütün ID-ləri topla → bir batch sorğusu

@Component
public class UserDataLoader
        implements BatchLoaderRegistry.BatchLoader<String, User> {

    @Override
    public Publisher<User> load(Collection<String> ids, BatchLoaderEnvironment env) {
        return Mono.fromCallable(() ->
            userService.findAllById(ids)  // Bir sorğuda N user
        ).flatMapMany(Flux::fromIterable);
    }
}

// ─── DataLoader-i qeydiyyat ──────────────────────────────
@Configuration
public class DataLoaderConfig {

    @Bean
    public BatchLoaderRegistry batchLoaderRegistry(
            UserDataLoader userLoader,
            ProductDataLoader productLoader) {
        BatchLoaderRegistry registry = new BatchLoaderRegistry();
        registry.forTypePair(String.class, User.class)
            .withName("userLoader")
            .registerBatchLoader(userLoader);
        registry.forTypePair(String.class, Product.class)
            .withName("productLoader")
            .registerBatchLoader(productLoader);
        return registry;
    }
}

// ─── Controller-də DataLoader istifadəsi ─────────────────
@Controller
public class OrderController {

    // Order.customer — N+1 yox, batch!
    @SchemaMapping(typeName = "Order", field = "customer")
    public CompletableFuture<User> customer(Order order,
            DataLoader<String, User> userLoader) {
        // DataLoader-ə customer ID-ni ver
        // Hamısı toplanır, bir batch sorğusu edilir
        return userLoader.load(order.getCustomerId());
    }

    // OrderItem.product — batch
    @SchemaMapping(typeName = "OrderItem", field = "product")
    public CompletableFuture<Product> product(OrderItem item,
            DataLoader<String, Product> productLoader) {
        return productLoader.load(item.getProductId());
    }
}

// ─── Test ─────────────────────────────────────────────────
@GraphQlTest(OrderController.class)
class OrderControllerTest {

    @Autowired
    private GraphQlTester graphQlTester;

    @MockBean
    private OrderService orderService;

    @Test
    void shouldReturnOrder() {
        when(orderService.findById("1")).thenReturn(Optional.of(testOrder()));

        graphQlTester.documentName("getOrder")  // src/test/resources/graphql/getOrder.graphql
            .variable("id", "1")
            .execute()
            .path("order.id").entity(String.class).isEqualTo("1")
            .path("order.status").entity(String.class).isEqualTo("PENDING");
    }

    @Test
    void shouldReturnErrorWhenNotFound() {
        when(orderService.findById("999")).thenReturn(Optional.empty());

        graphQlTester.document("{ order(id: \"999\") { id } }")
            .execute()
            .errors()
            .expect(error -> error.getErrorType() == ErrorType.NOT_FOUND);
    }
}
```

---

## İntervyu Sualları

### 1. GraphQL REST-dən nə ilə fərqlənir?
**Cavab:** **REST** — çoxlu endpoint (GET /users, GET /orders), server datanın strukturunu müəyyən edir, over-fetching (lazımsız field-lər) və under-fetching (çoxlu sorğu) problemi var. **GraphQL** — bir endpoint (POST /graphql), client nə istəyini özü seçir, over/under-fetching yoxdur. 3 operasiya: `query` (oxuma), `mutation` (yazma), `subscription` (real-time). Strongly-typed schema (SDL). N+1 problemi DataLoader ilə həll olunur.

### 2. N+1 problem nədir və DataLoader necə həll edir?
**Cavab:** `orders { customer { name } }` sorğusunda: 1 orders sorğusu, sonra hər order üçün ayrı customer sorğusu = N+1. DataLoader bu ID-ləri birləşdirir: hamısı toplandıqdan sonra `findAllById(ids)` ilə bir batch sorğusu edilir. Spring GraphQL-də `DataLoader<String, User>` metod parametri kimi inject olunur; `userLoader.load(customerId)` `CompletableFuture` qaytarır. GraphQL executor event loop-da DataLoader-i flush edir — tek request içindəki bütün `load()` çağırışları bir batch-ə toplanır.

### 3. Spring GraphQL-də @QueryMapping, @MutationMapping, @SchemaMapping fərqi?
**Cavab:** `@QueryMapping` — `Query` type-dəki field-ə resolver (method adı = field adı). `@MutationMapping` — `Mutation` type-dəki field-ə resolver. `@SubscriptionMapping` — `Subscription` type-dəki field-ə resolver (Flux qaytarır). `@SchemaMapping(typeName="Order", field="customer")` — `Order.customer` field-ə resolver (nested tip). `@Argument` — GraphQL argument-ini Java parametrinə bind edir. `@AuthenticationPrincipal` — Spring Security ilə inteqrasiya.

### 4. GraphQL Subscription necə işləyir?
**Cavab:** WebSocket üzərindən real-time data. Schema-da `Subscription` tipi müəyyən edilir. Server `Flux<T>` qaytarır. Client WebSocket bağlantısı açır, subscription sorğusu göndərir, server event-lər gəldikcə onları push edir. Spring GraphQL `spring-boot-starter-webflux` + WebSocket ilə işləyir. `Sinks.Many` ilə Publisher → Flux çevrilir. Production-da Redis Pub/Sub ilə çox instance arasında event paylaşmaq mümkündür.

### 5. GraphQL-də caching necə işləyir?
**Cavab:** REST-dən fərqli olaraq GraphQL standart HTTP caching-ə uyğun deyil — bütün sorğular POST /graphql-ə gedir. Həll yolları: (1) **Persisted Queries** — sorğu server-də saxlanır, client yalnız ID göndərir → GET mümkün → HTTP cache işləyir; (2) **Application-level cache** — resolver nəticəsini Redis/Caffeine-də cache; (3) **DataLoader cache** — eyni request içindəki eyni ID-lər bir dəfə yüklənir (request-scope cache); (4) **CDN** — Persisted Queries + GET ilə CDN-dən keçirmək mümkün. Subscription-lar cache olunmur.

*Son yenilənmə: 2026-04-10*
