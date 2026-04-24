# GraphQL Dəstəyi (Spring for GraphQL vs Lighthouse)

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

GraphQL, klientə lazım olan dəqiq sahələri tələb etməyə imkan verən sorğu dilidir. REST-dən fərqli olaraq, tək endpoint (`/graphql`) vardır və schema-first yanaşma istifadə olunur. Spring for GraphQL (rəsmi, 2022-də buraxılıb) və Laravel-in Lighthouse paketi hər ikisi schema-first işləyir — `.graphqls` faylında schema yazılır, sonra resolver-lər bağlanır.

Ən ümumi problem — N+1 sorğu problemi. Lighthouse `@with`/batch loader ilə, Spring isə `@BatchMapping` ilə bu problemi həll edir.

---

## Spring-də istifadəsi

### Dependency-lər

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-graphql</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
</dependency>
```

### Schema faylı

```graphql
# src/main/resources/graphql/schema.graphqls
type Query {
    book(id: ID!): Book
    books(first: Int = 10, after: String): BookConnection!
    searchBooks(query: String!): [Book!]!
}

type Mutation {
    createBook(input: CreateBookInput!): Book!
    updateBook(id: ID!, input: UpdateBookInput!): Book!
    deleteBook(id: ID!): Boolean!
}

type Subscription {
    bookCreated: Book!
    commentAdded(bookId: ID!): Comment!
}

type Book {
    id: ID!
    title: String!
    author: Author!            # N+1 riski
    reviews: [Review!]!        # N+1 riski
    createdAt: String!
}

type Author {
    id: ID!
    name: String!
    books: [Book!]!
}

type Review {
    id: ID!
    rating: Int!
    text: String!
    author: Author!
}

type BookConnection {
    edges: [BookEdge!]!
    pageInfo: PageInfo!
}

type BookEdge {
    node: Book!
    cursor: String!
}

type PageInfo {
    hasNextPage: Boolean!
    endCursor: String
}

input CreateBookInput {
    title: String!
    authorId: ID!
}
```

### Konfiqurasiya

```yaml
spring:
  graphql:
    graphiql:
      enabled: true             # /graphiql UI (dev-də)
    schema:
      printer:
        enabled: true           # /graphql/schema endpoint
      introspection:
        enabled: true
    cors:
      allowed-origins: "*"
    websocket:
      path: /graphql             # Subscription-lar üçün
```

### Controller — Query, Mutation

```java
@Controller
public class BookController {

    private final BookService bookService;
    private final AuthorService authorService;

    public BookController(BookService bookService, AuthorService authorService) {
        this.bookService = bookService;
        this.authorService = authorService;
    }

    // Query: book(id)
    @QueryMapping
    public Book book(@Argument String id) {
        return bookService.findById(id);
    }

    // Query: books(first, after) — Relay cursor pagination
    @QueryMapping
    public BookConnection books(@Argument int first, @Argument String after) {
        return bookService.findPaged(first, after);
    }

    // Query: searchBooks(query)
    @QueryMapping
    public List<Book> searchBooks(@Argument String query) {
        return bookService.search(query);
    }

    // Mutation: createBook(input)
    @MutationMapping
    @PreAuthorize("hasRole('USER')")
    public Book createBook(@Argument @Valid CreateBookInput input) {
        return bookService.create(input);
    }

    // Mutation: updateBook(id, input)
    @MutationMapping
    public Book updateBook(@Argument String id, @Argument UpdateBookInput input) {
        return bookService.update(id, input);
    }
}
```

### N+1 həlli — `@BatchMapping`

```java
@Controller
public class BookFieldResolver {

    private final AuthorService authorService;
    private final ReviewService reviewService;

    // @SchemaMapping — klassik yol (N+1 riski var)
    @SchemaMapping(typeName = "Book", field = "author")
    public Author authorSlow(Book book) {
        // Hər Book üçün ayrıca sorğu — N+1!
        return authorService.findById(book.getAuthorId());
    }

    // @BatchMapping — doğru yol
    @BatchMapping(typeName = "Book", field = "author")
    public Map<Book, Author> authors(List<Book> books) {
        // Bütün kitabların author-ları tək sorğuda
        Set<String> authorIds = books.stream()
            .map(Book::getAuthorId)
            .collect(Collectors.toSet());

        Map<String, Author> authorMap = authorService.findAllByIds(authorIds)
            .stream()
            .collect(Collectors.toMap(Author::getId, Function.identity()));

        return books.stream()
            .collect(Collectors.toMap(
                Function.identity(),
                book -> authorMap.get(book.getAuthorId())
            ));
    }

    // Review-lər üçün batch
    @BatchMapping(typeName = "Book", field = "reviews")
    public Map<Book, List<Review>> reviews(List<Book> books) {
        List<String> bookIds = books.stream().map(Book::getId).toList();

        Map<String, List<Review>> reviewsByBook = reviewService.findByBookIds(bookIds)
            .stream()
            .collect(Collectors.groupingBy(Review::getBookId));

        return books.stream()
            .collect(Collectors.toMap(
                Function.identity(),
                book -> reviewsByBook.getOrDefault(book.getId(), List.of())
            ));
    }
}
```

### Subscription — real-time

```java
@Controller
public class BookSubscriptionController {

    private final Sinks.Many<Book> bookCreatedSink =
        Sinks.many().multicast().onBackpressureBuffer();

    @SubscriptionMapping
    public Flux<Book> bookCreated() {
        return bookCreatedSink.asFlux();
    }

    @SubscriptionMapping
    public Flux<Comment> commentAdded(@Argument String bookId) {
        return commentService.streamByBookId(bookId);
    }

    // Yeni kitab yaradıldıqda sinkə push et
    @EventListener
    public void onBookCreated(BookCreatedEvent event) {
        bookCreatedSink.tryEmitNext(event.getBook());
    }
}
```

### Exception handling

```java
@Component
public class GraphQLExceptionResolver extends DataFetcherExceptionResolverAdapter {

    @Override
    protected GraphQLError resolveToSingleError(Throwable ex, DataFetchingEnvironment env) {
        if (ex instanceof BookNotFoundException bnf) {
            return GraphQLError.newError()
                .errorType(ErrorType.NOT_FOUND)
                .message(bnf.getMessage())
                .path(env.getExecutionStepInfo().getPath())
                .location(env.getField().getSourceLocation())
                .build();
        }
        if (ex instanceof AccessDeniedException) {
            return GraphQLError.newError()
                .errorType(ErrorType.FORBIDDEN)
                .message("Giriş qadağandır")
                .build();
        }
        return null;   // default handler
    }
}
```

### File upload (Multipart)

```java
@Controller
public class FileUploadController {

    @MutationMapping
    public String uploadAvatar(@Argument MultipartFile file) {
        return storageService.store(file);
    }
}
```

### Auth inteqrasiyası

```java
@QueryMapping
@PreAuthorize("hasRole('ADMIN')")
public List<User> allUsers() {
    return userService.findAll();
}

// @AuthenticationPrincipal ilə cari istifadəçi
@QueryMapping
public User me(@AuthenticationPrincipal UserDetails userDetails) {
    return userService.findByUsername(userDetails.getUsername());
}
```

---

## Laravel-də istifadəsi (Lighthouse)

### Quraşdırma

```bash
composer require nuwave/lighthouse
php artisan vendor:publish --tag=lighthouse-config
php artisan vendor:publish --tag=lighthouse-schema
```

### Schema faylı

```graphql
# graphql/schema.graphql
type Query {
    book(id: ID! @eq): Book @find
    books(first: Int = 10, after: String): BookConnection!
        @paginate(type: CONNECTION, model: "App\\Models\\Book")
    searchBooks(query: String! @where(operator: "like", key: "title")): [Book!]!
        @all(model: "App\\Models\\Book")
}

type Mutation {
    createBook(input: CreateBookInput! @spread): Book! @create @guard
    updateBook(id: ID!, input: UpdateBookInput! @spread): Book! @update @guard
    deleteBook(id: ID! @whereKey): Book @delete @guard
}

type Subscription {
    bookCreated: Book! @subscription(class: "App\\GraphQL\\Subscriptions\\BookCreated")
}

type Book {
    id: ID!
    title: String!
    author: Author! @belongsTo               # N+1 auto-batched
    reviews: [Review!]! @hasMany
    createdAt: DateTime! @rename(attribute: "created_at")
}

type Author {
    id: ID!
    name: String!
    books: [Book!]! @hasMany
}

type Review {
    id: ID!
    rating: Int!
    text: String!
    author: Author! @belongsTo
}

input CreateBookInput {
    title: String! @rules(apply: ["required", "min:3"])
    authorId: ID! @rename(attribute: "author_id")
}
```

### Konfiqurasiya

```php
// config/lighthouse.php
return [
    'route' => [
        'uri' => '/graphql',
        'middleware' => ['api'],
    ],

    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    'cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', false),
        'ttl' => 24 * 60 * 60,
    ],

    'batchload_relations' => true,    // N+1 avtomatik həll

    'security' => [
        'max_query_complexity' => 100,
        'max_query_depth' => 5,
        'disable_introspection' => env('APP_ENV') === 'production' ? 1 : 0,
    ],

    'subscriptions' => [
        'broadcaster' => 'pusher',
        'storage' => 'redis',
    ],
];
```

### Xüsusi resolver (`@field`)

```graphql
type Query {
    searchBooksAdvanced(query: String!): [Book!]!
        @field(resolver: "App\\GraphQL\\Queries\\SearchBooks")
}
```

```php
// app/GraphQL/Queries/SearchBooks.php
namespace App\GraphQL\Queries;

use App\Models\Book;

class SearchBooks
{
    public function __invoke($_, array $args): iterable
    {
        return Book::query()
            ->where('title', 'like', '%' . $args['query'] . '%')
            ->orWhereHas('author', fn ($q) => $q->where('name', 'like', '%' . $args['query'] . '%'))
            ->limit(50)
            ->get();
    }
}
```

### N+1 həlli — Batch loader (əl ilə)

Lighthouse-un `@belongsTo`/`@hasMany` direktivləri avtomatik batch edir. Amma xüsusi hallar üçün `@with` və ya DataLoader pattern:

```graphql
type Book {
    # Avtomatik eager load — @with direktivi
    author: Author! @belongsTo
    reviews: [Review!]! @hasMany @with(relation: "reviews.author")

    # Custom batch loader
    stats: BookStats! @field(resolver: "App\\GraphQL\\BookStatsBatch")
}
```

```php
// app/GraphQL/BookStatsBatch.php
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoaderRegistry;

class BookStatsBatch
{
    public function __invoke($book, array $args, $context, $resolveInfo): Deferred
    {
        $loader = BatchLoaderRegistry::instance(
            ['bookStats'],
            fn () => new BookStatsLoader(),
        );

        return $loader->load($book->id);
    }
}

// app/GraphQL/BookStatsLoader.php
class BookStatsLoader extends BatchLoader
{
    public function resolve(array $bookIds): array
    {
        // Tək sorğuda bütün kitabların statistikasını gətir
        $stats = DB::table('reviews')
            ->select('book_id', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as count'))
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->get()
            ->keyBy('book_id');

        $result = [];
        foreach ($bookIds as $id) {
            $result[$id] = $stats->get($id) ?? ['avg_rating' => 0, 'count' => 0];
        }
        return $result;
    }
}
```

### Subscription

```graphql
type Subscription {
    bookCreated: Book!
}
```

```php
// app/GraphQL/Subscriptions/BookCreated.php
namespace App\GraphQL\Subscriptions;

use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;

class BookCreated extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return $subscriber->context->user !== null;
    }

    public function filter(Subscriber $subscriber, $root): bool
    {
        // Yalnız public kitablar
        return $root->is_public;
    }
}

// Event tətikləmək
Subscription::broadcast('bookCreated', $book);
```

### File upload

```graphql
scalar Upload @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Upload")

type Mutation {
    uploadAvatar(file: Upload!): String!
        @field(resolver: "App\\GraphQL\\UploadAvatar")
}
```

```php
class UploadAvatar
{
    public function __invoke($_, array $args): string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $args['file'];
        return $file->store('avatars');
    }
}
```

### Auth — `@guard`, `@can`

```graphql
type Query {
    me: User! @auth
    myBooks: [Book!]! @auth @hasMany(relation: "books")
}

type Mutation {
    deleteBook(id: ID!): Book
        @can(ability: "delete", find: "id")
        @delete
}
```

### Exception handling

```php
// app/Exceptions/Handler.php
use Nuwave\Lighthouse\Exceptions\DefinitionException;

public function register(): void
{
    $this->reportable(function (BookNotFoundException $e) {
        Log::info('Book not found', ['id' => $e->id]);
    });
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring for GraphQL | Laravel Lighthouse |
|---|---|---|
| Yanaşma | Schema-first, kod əl ilə | Schema-first, direktivlər avtomatik resolver yaradır |
| Boilerplate | Orta (`@QueryMapping` + method) | Çox az (direktivlər) |
| N+1 həlli | `@BatchMapping` (əl ilə) | `@belongsTo`/`@hasMany` avtomatik, `@with` eager |
| Paginasiya | Manual `Connection` qur | `@paginate` direktivi |
| Validation | `@Valid` + Bean Validation | `@rules` direktivi schema-da |
| Subscription | Reactor `Flux`, WebSocket | Pusher/Redis broadcaster |
| File upload | `MultipartFile` parameter | `Upload` scalar |
| Auth | `@PreAuthorize`, `@AuthenticationPrincipal` | `@auth`, `@can`, `@guard` direktivləri |
| Exception | `DataFetcherExceptionResolver` | Laravel Handler |
| Schema cache | Prod-da default açıq | `LIGHTHOUSE_CACHE_ENABLE=true` |
| Introspection | `graphql.introspection.enabled` | `disable_introspection` |
| Query complexity | Manual | `max_query_complexity` config |
| Playground | GraphiQL daxili | GraphQL Playground paketi |
| Kod-first alternativ | Netflix DGS | Rebing/graphql-laravel |

---

## Niyə belə fərqlər var?

**Spring-in schema-first, kod-təmiz yanaşması.** Spring for GraphQL çox sadə prinsiplə işləyir: schema yazırsan, resolver metodları `@QueryMapping`/`@MutationMapping` ilə qeyd edirsən. Type safety tam Java tipləri ilə. Bu, böyük komandalarda oxunaqlı və test edilə bilən kod yaradır, amma hər field üçün kod yazmaq lazımdır.

**Lighthouse-un direktiv-əsaslı "convention over configuration".** Lighthouse `@belongsTo`, `@hasMany`, `@paginate`, `@create`, `@update`, `@delete` kimi direktivlərlə Eloquent-ə zəncirlənir. `@belongsTo` yazırsan — avtomatik Eloquent relation çağırılır, avtomatik batch edilir. Bu, kiçik komandalarda çox sürətli CRUD API qurmağa imkan verir.

**N+1 fərqi kritikdir.** Spring-də `@BatchMapping` əl ilə yazılır — developer bilməlidir ki, `Book.author` üçün batch lazımdır. Lighthouse isə Eloquent relation-lar üçün avtomatik batch edir (`batchload_relations: true`). Custom field-lər üçün hər iki tərəfdə DataLoader pattern lazımdır.

**Subscription modeli.** Spring Reactor `Flux` istifadə edir — bu, backpressure və composable stream-lər deməkdir. Lighthouse isə Laravel broadcasting (Pusher, Redis, Reverb) üzərinə qurulub — Laravel event sistemi ilə təbii inteqrasiya var.

**Validation yeri.** Spring-də `@Valid` + Jakarta Bean Validation istifadə olunur — Java bean-lərində `@NotBlank`, `@Size`. Lighthouse-da isə `@rules(apply: ["required"])` schema-nın özündə yazılır — rules schema-ya yapışır.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring for GraphQL-də:**
- Reactor `Flux`/`Mono` ilə reaktiv resolver-lər
- Federation (Apollo Federation dəstəyi `spring-graphql-federation`)
- RSocket GraphQL transport (WebSocket-dən başqa)
- Spring Security integration — `@PreAuthorize` GraphQL field-lərdə
- `@ContextValue` — GraphQL context-dən type-safe dəyər almaq
- GraphQL over HTTP, WebSocket, RSocket — eyni kod bazasında
- Netflix DGS ilə code-first alternativ (annotations ilə schema yaradılır)

**Yalnız Lighthouse-da:**
- Schema direktivləri ilə avtomatik CRUD (`@create`, `@update`, `@delete`)
- `@eq`, `@where`, `@orderBy`, `@whereBetween` — arqument direktivləri
- `@paginate(type: CONNECTION)` — Relay cursor pagination avtomatik
- `@spread` — input obyektini argument-lərə yaymaq
- `@rename(attribute: "created_at")` — DB sütun adını çevirmək
- Schema-dakı `@rules` direktivi ilə Laravel validation
- Artisan integration: `php artisan lighthouse:print-schema`
- `@cache` direktivi — resolver nəticəsini cache etmək
- `@complexity` direktivi — xüsusi field üçün complexity hesablamaq
