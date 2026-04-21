# Spring GraphQL vs Laravel Lighthouse — Dərin Müqayisə

## Giriş

GraphQL — REST-in alternativi kimi Facebook tərəfindən 2015-də yayımlanıb. Əsas ideyası: client tam istədiyi sahələri query-də göstərir, server yalnız onları qaytarır. Bu over-fetching və under-fetching problemlərini həll edir. Konseptlər: **Schema** (types + Query + Mutation + Subscription), **Resolver** (field üçün data gətirən funksiya), **DataLoader** (N+1 həlli), **Subscription** (real-time update).

**Spring for GraphQL** (`spring-graphql`) — Pivotal/VMware və GraphQL Java komandasının birgə layihəsidir. Əvvəlki `graphql-java-spring`-i əvəzləyir. **Schema-first** yanaşma — `.graphqls` faylı əsasdır, `@Controller` annotasiyalı sinif resolver-lər verir. Spring Data inteqrasiyası (pagination, projection), DataLoader built-in, WebSocket subscription, GraphiQL UI.

**Laravel**-də iki əsas seçim var:
- **Nuwave Lighthouse** — ən populyar, schema-first, annotation-like directives (`@all`, `@find`, `@paginate`)
- **Rebing/graphql-laravel** — code-first (webonyx/graphql-php üzərində class-lar)

Lighthouse Laravel-in Eloquent/Relations/Auth/Cache sistemini dərindən istifadə edir — bu onu Laravel dünyasında de-facto GraphQL həlli edir.

Bu sənəddə `Author -> Book -> Review` model qrafını hər iki framework-də quracayıq: Query, Mutation, Subscription, N+1 həlli, auth.

---

## Spring-də istifadəsi

### 1) Dependency

```xml
<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-graphql</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-web</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-websocket</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-data-jpa</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-security</artifactId>
    </dependency>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-test</artifactId>
        <scope>test</scope>
    </dependency>
    <dependency>
        <groupId>org.springframework.graphql</groupId>
        <artifactId>spring-graphql-test</artifactId>
        <scope>test</scope>
    </dependency>
</dependencies>
```

### 2) Konfigurasiya

```yaml
# application.yml
spring:
  graphql:
    schema:
      printer:
        enabled: true
      introspection:
        enabled: true
    graphiql:
      enabled: true
      path: /graphiql
    websocket:
      path: /graphql
      connection-init-timeout: 60s
      keep-alive: 15s
    cors:
      allowed-origins: "https://example.com"
```

### 3) Schema — `.graphqls`

```graphql
# resources/graphql/schema.graphqls

scalar DateTime

type Query {
    author(id: ID!): Author
    authors(first: Int = 20, after: String, search: String): AuthorConnection!
    book(id: ID!): Book
    books(authorId: ID, page: Int = 0, size: Int = 20): BookPage!
}

type Mutation {
    createBook(input: CreateBookInput!): Book!
    updateBook(id: ID!, input: UpdateBookInput!): Book!
    deleteBook(id: ID!): Boolean!
    addReview(bookId: ID!, input: ReviewInput!): Review!
}

type Subscription {
    bookAdded(authorId: ID): Book!
    reviewAdded(bookId: ID!): Review!
}

type Author {
    id: ID!
    name: String!
    email: String!
    books: [Book!]!
    bookCount: Int!
    createdAt: DateTime!
}

type Book {
    id: ID!
    title: String!
    isbn: String!
    publishedAt: DateTime
    author: Author!
    reviews(first: Int = 10): [Review!]!
    averageRating: Float
}

type Review {
    id: ID!
    rating: Int!
    comment: String
    reviewer: String!
    book: Book!
    createdAt: DateTime!
}

type AuthorConnection {
    edges: [AuthorEdge!]!
    pageInfo: PageInfo!
    totalCount: Int!
}

type AuthorEdge {
    node: Author!
    cursor: String!
}

type PageInfo {
    hasNextPage: Boolean!
    hasPreviousPage: Boolean!
    startCursor: String
    endCursor: String
}

type BookPage {
    content: [Book!]!
    totalElements: Int!
    totalPages: Int!
    number: Int!
    size: Int!
}

input CreateBookInput {
    title: String!
    isbn: String!
    authorId: ID!
    publishedAt: DateTime
}

input UpdateBookInput {
    title: String
    publishedAt: DateTime
}

input ReviewInput {
    rating: Int!
    comment: String
    reviewer: String!
}
```

### 4) Query controller — `@QueryMapping`

```java
@Controller
public class AuthorQueryController {

    private final AuthorRepository authorRepository;
    private final BookRepository bookRepository;

    public AuthorQueryController(AuthorRepository authorRepository, BookRepository bookRepository) {
        this.authorRepository = authorRepository;
        this.bookRepository = bookRepository;
    }

    @QueryMapping
    public Optional<Author> author(@Argument Long id) {
        return authorRepository.findById(id);
    }

    @QueryMapping
    public Connection<Author> authors(
            @Argument Integer first,
            @Argument String after,
            @Argument String search) {
        // cursor pagination — Spring Data ilə
        int limit = first != null ? first : 20;
        Long afterId = after != null ? Long.valueOf(decodeCursor(after)) : null;

        List<Author> authors = (search != null)
            ? authorRepository.searchAfter(search, afterId, limit + 1)
            : authorRepository.findAllAfter(afterId, limit + 1);

        boolean hasNext = authors.size() > limit;
        if (hasNext) authors = authors.subList(0, limit);

        List<Edge<Author>> edges = authors.stream()
            .map(a -> new DefaultEdge<>(a, encodeCursor(a.getId())))
            .collect(Collectors.toList());

        PageInfo pageInfo = new DefaultPageInfo(
            edges.isEmpty() ? null : edges.get(0).getCursor(),
            edges.isEmpty() ? null : edges.get(edges.size() - 1).getCursor(),
            afterId != null,
            hasNext
        );

        return new DefaultConnection<>(edges, pageInfo);
    }

    @QueryMapping
    public Page<Book> books(@Argument Long authorId,
                            @Argument Integer page,
                            @Argument Integer size) {
        Pageable pageable = PageRequest.of(page != null ? page : 0, size != null ? size : 20);
        return authorId != null
            ? bookRepository.findByAuthorId(authorId, pageable)
            : bookRepository.findAll(pageable);
    }
}
```

### 5) Mutation controller — `@MutationMapping`

```java
@Controller
@PreAuthorize("isAuthenticated()")
public class BookMutationController {

    private final BookService bookService;
    private final ReviewService reviewService;
    private final Sinks.Many<Book> bookAddedSink;
    private final Sinks.Many<Review> reviewAddedSink;

    public BookMutationController(BookService bookService, ReviewService reviewService,
                                  Sinks.Many<Book> bookAddedSink,
                                  Sinks.Many<Review> reviewAddedSink) {
        this.bookService = bookService;
        this.reviewService = reviewService;
        this.bookAddedSink = bookAddedSink;
        this.reviewAddedSink = reviewAddedSink;
    }

    @MutationMapping
    @PreAuthorize("hasRole('EDITOR')")
    public Book createBook(@Argument @Valid CreateBookInput input) {
        Book book = bookService.create(input);
        bookAddedSink.tryEmitNext(book);
        return book;
    }

    @MutationMapping
    @PreAuthorize("hasRole('EDITOR') or @bookSecurity.isOwner(#id, principal)")
    public Book updateBook(@Argument Long id, @Argument UpdateBookInput input) {
        return bookService.update(id, input);
    }

    @MutationMapping
    @PreAuthorize("hasRole('ADMIN')")
    public Boolean deleteBook(@Argument Long id) {
        bookService.delete(id);
        return true;
    }

    @MutationMapping
    public Review addReview(@Argument Long bookId, @Argument @Valid ReviewInput input,
                            @AuthenticationPrincipal UserDetails user) {
        Review review = reviewService.create(bookId, input, user.getUsername());
        reviewAddedSink.tryEmitNext(review);
        return review;
    }
}
```

### 6) Schema mapping — nested fields

Hər `Book.author` üçün DB-yə ayrı sorğu olmasın deyə:

```java
@Controller
public class BookFieldController {

    private final AuthorRepository authorRepository;
    private final ReviewRepository reviewRepository;

    // N+1 problem: hər Book üçün author ayrıca oxumaq lazım deyil
    @SchemaMapping(typeName = "Book", field = "author")
    public Author author(Book book) {
        return authorRepository.findById(book.getAuthorId()).orElseThrow();
    }
}
```

### 7) Batch mapping — DataLoader (N+1 həlli)

```java
@Controller
public class BookBatchController {

    private final AuthorRepository authorRepository;
    private final ReviewRepository reviewRepository;

    // GraphQL response içində N kitab varsa, 1 sorğu ilə bütün author-ları oxu
    @BatchMapping(typeName = "Book", field = "author")
    public Map<Book, Author> authors(List<Book> books) {
        Set<Long> authorIds = books.stream().map(Book::getAuthorId).collect(Collectors.toSet());
        Map<Long, Author> authorById = authorRepository.findAllById(authorIds).stream()
            .collect(Collectors.toMap(Author::getId, a -> a));

        return books.stream()
            .collect(Collectors.toMap(b -> b, b -> authorById.get(b.getAuthorId())));
    }

    @BatchMapping(typeName = "Book", field = "reviews")
    public Map<Book, List<Review>> reviews(List<Book> books) {
        Set<Long> bookIds = books.stream().map(Book::getId).collect(Collectors.toSet());
        return reviewRepository.findByBookIdIn(bookIds).stream()
            .collect(Collectors.groupingBy(r -> books.stream()
                .filter(b -> b.getId().equals(r.getBookId())).findFirst().orElseThrow()));
    }

    @BatchMapping(typeName = "Book", field = "averageRating")
    public Map<Book, Double> averageRatings(List<Book> books) {
        Set<Long> bookIds = books.stream().map(Book::getId).collect(Collectors.toSet());
        Map<Long, Double> avgById = reviewRepository.averageRatingByBookIds(bookIds).stream()
            .collect(Collectors.toMap(r -> (Long) r[0], r -> (Double) r[1]));

        return books.stream()
            .collect(HashMap::new, (m, b) -> m.put(b, avgById.get(b.getId())), HashMap::putAll);
    }
}
```

Arxa planda Spring GraphQL `DataLoader`-dan istifadə edir — eyni type üçün bütün sorğular yığılır, 1 dəfə execute olunur.

### 8) Subscription — `@SubscriptionMapping`

```java
@Controller
public class BookSubscriptionController {

    private final Sinks.Many<Book> bookAddedSink;
    private final Sinks.Many<Review> reviewAddedSink;

    public BookSubscriptionController() {
        this.bookAddedSink = Sinks.many().multicast().onBackpressureBuffer();
        this.reviewAddedSink = Sinks.many().multicast().onBackpressureBuffer();
    }

    @Bean
    public Sinks.Many<Book> bookSink() { return bookAddedSink; }

    @Bean
    public Sinks.Many<Review> reviewSink() { return reviewAddedSink; }

    @SubscriptionMapping
    public Flux<Book> bookAdded(@Argument Long authorId) {
        Flux<Book> stream = bookAddedSink.asFlux();
        return authorId != null
            ? stream.filter(b -> b.getAuthorId().equals(authorId))
            : stream;
    }

    @SubscriptionMapping
    public Flux<Review> reviewAdded(@Argument Long bookId) {
        return reviewAddedSink.asFlux().filter(r -> r.getBookId().equals(bookId));
    }
}
```

### 9) Exception handling — `DataFetcherExceptionResolver`

```java
@Component
public class GlobalExceptionResolver extends DataFetcherExceptionResolverAdapter {

    @Override
    protected GraphQLError resolveToSingleError(Throwable ex, DataFetchingEnvironment env) {
        if (ex instanceof EntityNotFoundException e) {
            return GraphqlErrorBuilder.newError(env)
                .errorType(ErrorType.NOT_FOUND)
                .message(e.getMessage())
                .build();
        }
        if (ex instanceof AccessDeniedException) {
            return GraphqlErrorBuilder.newError(env)
                .errorType(ErrorType.FORBIDDEN)
                .message("İcazə yoxdur")
                .build();
        }
        if (ex instanceof ConstraintViolationException e) {
            return GraphqlErrorBuilder.newError(env)
                .errorType(ErrorType.BAD_REQUEST)
                .message(e.getMessage())
                .extensions(Map.of("violations", e.getConstraintViolations()))
                .build();
        }
        return null;   // default handler götür
    }
}
```

### 10) Input validation

```java
public record CreateBookInput(
    @NotBlank @Size(max = 200) String title,
    @NotBlank @Pattern(regexp = "^[0-9]{10,13}$") String isbn,
    @NotNull Long authorId,
    LocalDateTime publishedAt
) {}

@Controller
public class BookMutationController {

    @MutationMapping
    public Book createBook(@Argument @Valid CreateBookInput input) {
        // @Valid → ConstraintViolationException-a gətirir → resolver handle edir
        return bookService.create(input);
    }
}
```

### 11) Security

```java
@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        return http
            .csrf(AbstractHttpConfigurer::disable)
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/graphiql/**").permitAll()
                .requestMatchers("/graphql/schema").permitAll()
                .anyRequest().authenticated())
            .oauth2ResourceServer(o -> o.jwt(Customizer.withDefaults()))
            .build();
    }
}
```

### 12) Testing — `HttpGraphQlTester`

```java
@AutoConfigureHttpGraphQlTester
@SpringBootTest
class BookQueryTest {

    @Autowired
    private HttpGraphQlTester graphQlTester;

    @Test
    void shouldReturnBook() {
        // language=GraphQL
        String query = """
            query GetBook($id: ID!) {
              book(id: $id) {
                id
                title
                author { id name }
                reviews(first: 5) { rating comment }
              }
            }
            """;

        graphQlTester.document(query)
            .variable("id", "1")
            .execute()
            .path("book.title").entity(String.class).isEqualTo("Clean Code")
            .path("book.author.name").entity(String.class).isEqualTo("Robert Martin")
            .path("book.reviews").entityList(Map.class).hasSize(3);
    }

    @Test
    void shouldReturnErrorForMissingBook() {
        graphQlTester.document("{ book(id: \"999\") { id } }")
            .execute()
            .errors()
            .expect(err -> err.getErrorType() == ErrorType.NOT_FOUND);
    }
}
```

---

## Laravel-də istifadəsi

### 1) composer və konfigurasiya

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "nuwave/lighthouse": "^6.42",
        "mll-lab/laravel-graphql-playground": "^2.6"
    }
}
```

```bash
php artisan vendor:publish --tag=lighthouse-config
php artisan vendor:publish --tag=lighthouse-schema
```

```php
// config/lighthouse.php
return [
    'route' => [
        'uri' => '/graphql',
        'name' => 'graphql',
        'middleware' => [\Nuwave\Lighthouse\Http\Middleware\AcceptJson::class],
    ],
    'guards' => ['sanctum'],
    'namespaces' => [
        'models' => ['App\\Models'],
        'queries' => ['App\\GraphQL\\Queries'],
        'mutations' => ['App\\GraphQL\\Mutations'],
        'subscriptions' => ['App\\GraphQL\\Subscriptions'],
        'types' => ['App\\GraphQL\\Types'],
        'directives' => ['App\\GraphQL\\Directives'],
    ],
    'subscriptions' => [
        'queue_broadcasts' => env('LIGHTHOUSE_QUEUE_BROADCASTS', true),
        'broadcaster' => 'pusher',
    ],
    'batchload_relations' => true,
    'cache' => [
        'enable' => env('LIGHTHOUSE_CACHE_ENABLE', true),
        'version' => 2,
        'store' => env('LIGHTHOUSE_CACHE_STORE'),
        'ttl' => env('LIGHTHOUSE_CACHE_TTL', 60 * 60 * 24),
    ],
];
```

### 2) Schema — `graphql/schema.graphql`

Lighthouse-un güclü tərəfi: schema-da **directive**-lərlə resolver-ləri birbaşa göstərmək olur, Eloquent ilə əlaqə qurulur.

```graphql
"A datetime string in the format Y-m-d H:i:s"
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

type Query {
    author(id: ID! @eq): Author @find
    authors(
        search: String @search
        first: Int = 20
    ): [Author!]! @paginate(defaultCount: 20)

    book(id: ID! @eq): Book @find
    books(
        authorId: ID @eq(key: "author_id")
    ): [Book!]! @paginate(defaultCount: 20)
}

type Mutation {
    createBook(input: CreateBookInput! @spread): Book!
        @create
        @guard
        @can(ability: "create", model: "App\\Models\\Book")

    updateBook(
        id: ID!
        input: UpdateBookInput! @spread
    ): Book! @update @guard

    deleteBook(id: ID!): Book @delete @guard @can(ability: "delete", find: "id")

    addReview(
        bookId: ID!
        input: ReviewInput! @spread
    ): Review! @guard
}

type Subscription {
    bookAdded(authorId: ID): Book
    reviewAdded(bookId: ID!): Review
}

type Author {
    id: ID!
    name: String!
    email: String! @guard(with: ["sanctum"])
    books: [Book!]! @hasMany
    bookCount: Int! @count(relation: "books")
    createdAt: DateTime!
}

type Book {
    id: ID!
    title: String!
    isbn: String!
    publishedAt: DateTime
    author: Author! @belongsTo
    reviews(first: Int = 10 @rules(apply: ["integer", "min:1", "max:100"])): [Review!]! @hasMany
    averageRating: Float @field(resolver: "BookResolver@averageRating")
}

type Review {
    id: ID!
    rating: Int!
    comment: String
    reviewer: String!
    book: Book! @belongsTo
    createdAt: DateTime!
}

input CreateBookInput {
    title: String! @rules(apply: ["required", "string", "max:200"])
    isbn: String! @rules(apply: ["required", "regex:/^[0-9]{10,13}$/"])
    authorId: ID! @rules(apply: ["required", "exists:authors,id"]) @rename(attribute: "author_id")
    publishedAt: DateTime @rename(attribute: "published_at")
}

input UpdateBookInput {
    title: String @rules(apply: ["string", "max:200"])
    publishedAt: DateTime @rename(attribute: "published_at")
}

input ReviewInput {
    rating: Int! @rules(apply: ["required", "integer", "min:1", "max:5"])
    comment: String @rules(apply: ["nullable", "string", "max:1000"])
    reviewer: String! @rules(apply: ["required", "string", "max:100"])
}
```

Gördüyünüz kimi, `@all`, `@find`, `@create`, `@update`, `@delete`, `@paginate`, `@hasMany`, `@belongsTo`, `@guard`, `@can`, `@rules` directive-ləri resolver-i schema-da düzəldir — əksər hallarda PHP kodu yazmaq lazım deyil.

### 3) Custom resolver — Query

Directive kifayət etmədikdə:

```php
// app/GraphQL/Queries/TopRatedBooks.php
namespace App\GraphQL\Queries;

use App\Models\Book;
use Illuminate\Support\Facades\DB;

class TopRatedBooks
{
    public function __invoke($_, array $args)
    {
        return Book::select('books.*')
            ->join('reviews', 'books.id', '=', 'reviews.book_id')
            ->groupBy('books.id')
            ->orderByRaw('AVG(reviews.rating) DESC')
            ->limit($args['limit'] ?? 10)
            ->get();
    }
}
```

```graphql
type Query {
    topRatedBooks(limit: Int = 10): [Book!]!
        @field(resolver: "App\\GraphQL\\Queries\\TopRatedBooks")
}
```

### 4) Custom field resolver

```php
// app/GraphQL/BookResolver.php
namespace App\GraphQL;

use App\Models\Book;

class BookResolver
{
    public function averageRating(Book $book): ?float
    {
        // DataLoader ilə batch oxusun — @BatchMapping kimi
        return $book->reviews_avg_rating ?? $book->reviews()->avg('rating');
    }
}
```

### 5) N+1 həlli — Lighthouse-un DataLoader-i

Lighthouse avtomatik "batch-loader" istifadə edir. `@belongsTo`, `@hasMany` directive-ləri Eloquent's `with()` eager loading-dən istifadə edir — N+1 avtomatik həll olunur.

Manual control üçün `@batchLoader` directive:

```graphql
type Query {
    books: [Book!]! @all
}

type Book {
    # hər book üçün ayrıca DB sorğu olmasın — batch load et
    author: Author @belongsTo
    reviews: [Review!]! @hasMany
}
```

Custom batch loader yazmaq lazım olsa:

```php
// app/GraphQL/DataLoaders/AverageRatingLoader.php
namespace App\GraphQL\DataLoaders;

use App\Models\Review;
use GraphQL\Deferred;
use Nuwave\Lighthouse\Execution\DataLoader\BatchLoader;

class AverageRatingLoader extends BatchLoader
{
    public function resolve(): array
    {
        $bookIds = array_keys($this->keys);

        $avgs = Review::selectRaw('book_id, AVG(rating) AS avg_rating')
            ->whereIn('book_id', $bookIds)
            ->groupBy('book_id')
            ->pluck('avg_rating', 'book_id');

        return collect($this->keys)
            ->mapWithKeys(fn ($info, $bookId) => [
                $bookId => $avgs->get($bookId) ?? 0.0,
            ])
            ->all();
    }
}

// Field resolver
class BookResolver
{
    public function averageRating(Book $book, $_, $context, $info)
    {
        return BatchLoader::instance(AverageRatingLoader::class, ['bookId' => $book->id], $info)
            ->load($book->id);
    }
}
```

### 6) Mutation — custom

Complex mutation üçün directive kifayət etmədikdə:

```php
// app/GraphQL/Mutations/AddReview.php
namespace App\GraphQL\Mutations;

use App\Events\ReviewAdded;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class AddReview
{
    public function __invoke($_, array $args): Review
    {
        $book = Book::findOrFail($args['bookId']);

        $review = $book->reviews()->create([
            ...$args['input'],
            'user_id' => Auth::id(),
        ]);

        ReviewAdded::dispatch($review);

        return $review;
    }
}
```

```graphql
type Mutation {
    addReview(bookId: ID!, input: ReviewInput! @spread): Review!
        @field(resolver: "App\\GraphQL\\Mutations\\AddReview")
        @guard
}
```

### 7) Subscription

```php
// app/GraphQL/Subscriptions/BookAdded.php
namespace App\GraphQL\Subscriptions;

use App\Models\Book;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

class BookAdded extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        return $request->user() !== null;
    }

    public function filter(Subscriber $subscriber, $root): bool
    {
        $authorId = $subscriber->args['authorId'] ?? null;
        if (! $authorId) return true;
        return $root instanceof Book && $root->author_id === (int) $authorId;
    }

    public function resolve($root, array $args, $context, ResolveInfo $info): Book
    {
        return $root;
    }
}
```

```php
// Event listener — yeni book əlavə olunanda
Event::listen(BookCreated::class, function (BookCreated $event) {
    Subscription::broadcast('bookAdded', $event->book);
});
```

### 8) Auth və authorization

Schema directive-lərlə:

```graphql
type Query {
    me: User @auth
    secretData: String @guard(with: ["sanctum"])
}

type Mutation {
    deleteBook(id: ID!): Book @delete @guard @can(ability: "delete", find: "id")
}
```

Policy:

```php
// app/Policies/BookPolicy.php
public function delete(User $user, Book $book): bool
{
    return $user->hasRole('admin') || $book->user_id === $user->id;
}
```

### 9) Validation

Schema-daxili:

```graphql
input CreateBookInput {
    title: String! @rules(apply: ["required", "string", "max:200"])
    isbn: String! @rules(apply: ["required", "regex:/^[0-9]{10,13}$/"])
    authorId: ID! @rules(apply: ["required", "exists:authors,id"])
}
```

Validator class (kompleks hallarda):

```php
// app/GraphQL/Validators/CreateBookInputValidator.php
namespace App\GraphQL\Validators;

use Nuwave\Lighthouse\Validation\Validator;

class CreateBookInputValidator extends Validator
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'isbn' => ['required', 'regex:/^[0-9]{10,13}$/', 'unique:books,isbn'],
            'authorId' => ['required', 'exists:authors,id'],
            'publishedAt' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
```

```graphql
input CreateBookInput @validator {
    title: String!
    isbn: String!
    authorId: ID!
    publishedAt: DateTime
}
```

### 10) Testing

```php
// tests/Feature/GraphQL/BookQueryTest.php
namespace Tests\Feature\GraphQL;

use App\Models\Author;
use App\Models\Book;
use App\Models\Review;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_book_with_author_and_reviews(): void
    {
        $author = Author::factory()->create(['name' => 'Robert Martin']);
        $book = Book::factory()->for($author)->create(['title' => 'Clean Code']);
        Review::factory(3)->for($book)->create();

        $this->graphQL(/** @lang GraphQL */ '
            query GetBook($id: ID!) {
                book(id: $id) {
                    id
                    title
                    author { id name }
                    reviews(first: 5) { rating comment }
                }
            }
        ', ['id' => $book->id])
            ->assertJsonPath('data.book.title', 'Clean Code')
            ->assertJsonPath('data.book.author.name', 'Robert Martin')
            ->assertJsonCount(3, 'data.book.reviews');
    }

    public function test_unauthenticated_cannot_create_book(): void
    {
        $this->graphQL(/** @lang GraphQL */ '
            mutation {
                createBook(input: {
                    title: "Test"
                    isbn: "1234567890"
                    authorId: "1"
                }) { id }
            }
        ')->assertGraphQLErrorMessage('Unauthenticated.');
    }

    public function test_n_plus_one_prevention(): void
    {
        Book::factory(10)->create();

        DB::enableQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
            { books(first: 10) { data { id title author { name } } } }
        ')->assertJsonStructure(['data' => ['books' => ['data']]]);

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(3, $queryCount, 'N+1 detected');
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring GraphQL | Laravel Lighthouse |
|---|---|---|
| Yanaşma | Schema-first + `@Controller` | Schema-first + directives |
| Resolver yazma | Java metod + `@QueryMapping` | Directive (`@all`, `@find`) və ya PHP class |
| Boilerplate | Orta (hər field üçün metod lazım) | Az (directive kifayət edir) |
| N+1 həlli | `@BatchMapping` / DataLoader manual | `@belongsTo`, `@hasMany` avtomatik eager load |
| Input validation | `@Valid` + Jakarta Bean Validation | `@rules` directive + Validator class |
| Auth | Spring Security + `@PreAuthorize` | `@guard`, `@can` directive + Policy |
| Subscription | `Flux<T>` + WebSocket | Broadcast driver (Pusher/Redis) + Event |
| UI | GraphiQL built-in | GraphQL Playground paketi |
| Pagination | `Page<T>`, `Connection<T>` built-in | `@paginate` directive |
| Schema stitching | Manual | `Extending` type via directive |
| Testing | `HttpGraphQlTester`, `WebGraphQlTester` | `$this->graphQL()` helper |
| File upload | `Upload` scalar built-in | `@upload` directive |
| Caching | Manual (Spring Cache) | `@cache` directive |
| Schema printer | `/graphql/schema` endpoint | `php artisan lighthouse:print-schema` |
| Reactive | Project Reactor (Flux/Mono) | Sync (Promise manual) |

---

## Niyə belə fərqlər var?

**Laravel-in convention-over-configuration.** Eloquent relation (`hasMany`, `belongsTo`) + Policy + Validator + Auth guard — hamısı Laravel-in əsas primitive-ləridir. Lighthouse directive-lər bu sistemlərin üstündə işləyir — yəni `@belongsTo` Eloquent-in `belongsTo()` metodunu çağırır, `@can` Policy-i tətbiq edir. Nəticədə çox az boilerplate yazılır.

**Spring-in explicit kontrolu.** Spring GraphQL her field üçün `@QueryMapping` və ya `@SchemaMapping` tələb edir. Daha çox kod, amma daha aydın — hər resolver-in nə etdiyi görünür. Enterprise mühitində bu debugging və review baxımından üstündür.

**N+1 problem yanaşması.** Lighthouse Eloquent-in "eager loading" mexanizmindən avtomatik istifadə edir — əksər hallarda problem özü-özünə həll olunur. Spring GraphQL-də explicit `@BatchMapping` yazmaq lazımdır — amma tam kontrol verir.

**Subscription arxitekturası.** Spring GraphQL reactive stream (`Flux`) + WebSocket istifadə edir — tətbiq daxilində state. Laravel PHP-nin request-per-process modelinə görə external broadcaster (Pusher, Redis pubsub) tələb edir. Spring-də subscription sadə, Laravel-də ayrıca infrastruktur gərək.

**Validation.** Spring Jakarta Bean Validation (`@NotNull`, `@Size`) POJO-ya ann anadır. Laravel `@rules` schema-da string kimi yazılır. Spring daha type-safe, Lighthouse daha kompakt.

**Type system.** Java-da type-safety kompile-time, PHP-də runtime. Lighthouse-da yanlış field adı çalışarkən aşkar olur, Spring-də build-də.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring GraphQL-də:**
- Reactive `Flux`/`Mono` return tipləri
- `@BatchMapping` annotation — explicit batch loader
- `Connection<T>` Relay-style cursor pagination built-in
- WebSocket subscription Pusher-siz
- GraphiQL UI built-in
- Jakarta Bean Validation ilə type-safe validation
- `HttpGraphQlTester` declarative test API
- Spring Data inteqrasiyası (`Page`, `Sort` avtomatik)

**Yalnız Laravel Lighthouse-də:**
- Directive-driven schema (`@all`, `@find`, `@paginate`, `@hasMany`)
- Avtomatik eager loading (Eloquent əsaslı)
- `@can`, `@guard` directive-ləri Policy-lərlə inteqrasiya
- `@cache` directive
- `@rules` schema-da validation
- `@rename` field rename
- `@spread` input object → arguments
- `@search` scout inteqrasiyası
- `$this->graphQL()` Laravel test helper
- Schema-da field-level authorization çox sadə

---

## Best Practices

**Spring GraphQL üçün:**
- `@BatchMapping` istifadə et — `@SchemaMapping` yalnız bir field üçün
- Input-ları record kimi yaradıb `@Valid` əlavə et
- `HttpGraphQlTester` ilə hər query-ni test et
- `DataFetcherExceptionResolver` ilə mərkəzləşdirilmiş error
- Pagination üçün `Connection<T>` istifadə et (Relay uyğun)
- Schema-nı `.graphqls` faylında saxla, version control et
- Subscription üçün Redis pubsub əlavə et (scale üçün)

**Laravel Lighthouse üçün:**
- Directive istifadə et — PHP resolver yalnız lazım olduqda
- `batchload_relations=true` config — avtomatik batch
- `@guard` və `@can` ilə field-level auth
- `@rules` input-da — class validator yalnız mürəkkəb hallarda
- Subscription üçün queue driver qur — sync-də yavaş
- N+1-i testdə yoxla (`DB::enableQueryLog()`)
- Schema cache production-da açıq olsun

---

## Yekun

Spring GraphQL enterprise Java tətbiqləri üçün GraphQL-in rəsmi həllidir. Explicit `@QueryMapping`/`@MutationMapping`/`@BatchMapping` controller-ləri, reactive stream, Spring Security inteqrasiyası, built-in GraphiQL və pagination onu güclü framework edir. Daha çox boilerplate, amma daha çox type-safety və kontrol.

Laravel Lighthouse Laravel-in convention-ları üzərində GraphQL qurur — directive-lər Eloquent/Policy/Auth/Validator-u schema-ya bağlayır. Nəticədə çox az PHP kodu yazmaq kifayətdir. N+1 əksər hallarda avtomatik həll olunur. Subscription üçün external broadcaster lazımdır.

Qısa qayda: **mikroservis enterprise mühitində Spring GraphQL-in explicit kontrolu yaxşıdır; Laravel monolit SaaS-də Lighthouse-un directive-driven yanaşması çox sürətli inkişaf verir.**
