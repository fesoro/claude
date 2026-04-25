# GraphQL (Middle)

## İcmal

GraphQL Facebook tərəfindən 2012-ci ildə yaradılıb və 2015-ci ildə open-source edilib. Client-in tam olaraq hansı data-nı istədiyi soruşa biləcəyi query language və runtime-dir. REST-dən fərqli olaraq, bir endpoint üzərindən işləyir və over-fetching/under-fetching problemlərini həll edir.

```
REST:
  GET /api/users/42           -> {id, name, email, phone, address, ...}  (over-fetching)
  GET /api/users/42/posts     -> ayrı request lazımdır (under-fetching)
  GET /api/users/42/followers -> daha bir request (under-fetching)

GraphQL:
  POST /graphql
  {
    user(id: 42) {
      name
      email
      posts { title }
      followers { name }
    }
  }
  -> Tam istədiyin data, bir request-də
```

## Niyə Vacibdir

GraphQL xüsusilə mobile client-ləri olan layihələrdə güclüdür: mobile yavaş internet şəraitindədir, lazımsız field-ləri çəkmək bandwidth xərcini artırır. Çoxsaylı frontend-lər (web, iOS, Android) eyni backend-i paylaşdıqda REST-in hər client üçün ayrı endpoint-i problem yaradır; GraphQL bir endpoint-lə bütün client-lərin müxtəlif ehtiyaclarını həll edir. Schema strongly typed olduğundan documentation avtomatik generasiya olunur.

## Əsas Anlayışlar

### Schema Definition Language (SDL)

```graphql
# Type definitions
type User {
  id: ID!
  name: String!
  email: String!
  age: Int
  posts: [Post!]!
  profile: Profile
  createdAt: DateTime!
}

type Post {
  id: ID!
  title: String!
  body: String!
  author: User!
  comments: [Comment!]!
  tags: [Tag!]!
  publishedAt: DateTime
}

type Comment {
  id: ID!
  body: String!
  author: User!
  post: Post!
}

# Input types (mutation üçün)
input CreateUserInput {
  name: String!
  email: String!
  password: String!
}

input UpdateUserInput {
  name: String
  email: String
}

# Enum
enum PostStatus {
  DRAFT
  PUBLISHED
  ARCHIVED
}

# Query (read operations)
type Query {
  users(page: Int, limit: Int): UserPaginator!
  user(id: ID!): User
  posts(status: PostStatus): [Post!]!
  post(id: ID!): Post
}

# Mutation (write operations)
type Mutation {
  createUser(input: CreateUserInput!): User!
  updateUser(id: ID!, input: UpdateUserInput!): User!
  deleteUser(id: ID!): Boolean!
  createPost(title: String!, body: String!): Post!
}

# Subscription (real-time)
type Subscription {
  postCreated: Post!
  commentAdded(postId: ID!): Comment!
}
```

### Query, Mutation, Subscription

```graphql
# QUERY - data oxumaq
query GetUser {
  user(id: 42) {
    name
    email
    posts(first: 5) {
      title
      publishedAt
    }
  }
}

# MUTATION - data dəyişmək
mutation CreateUser {
  createUser(input: {
    name: "Orkhan"
    email: "orkhan@example.com"
    password: "secret123"
  }) {
    id
    name
  }
}

# SUBSCRIPTION - real-time updates
subscription OnPostCreated {
  postCreated {
    id
    title
    author {
      name
    }
  }
}

# Variables istifadə etmək
query GetUser($id: ID!) {
  user(id: $id) {
    name
    email
  }
}
# Variables: {"id": "42"}

# Fragments - təkrarlanan field-ləri birləşdirmək
fragment UserBasic on User {
  id
  name
  email
}

query {
  user(id: 42) {
    ...UserBasic
    posts { title }
  }
  currentUser: user(id: 1) {
    ...UserBasic
  }
}
```

### N+1 Problem

```
# Bu query icra olunanda:
{
  users(first: 10) {
    name
    posts { title }   # Hər user üçün ayrı SQL query!
  }
}

# N+1 problem:
SELECT * FROM users LIMIT 10;           -- 1 query
SELECT * FROM posts WHERE user_id = 1;  -- +1
SELECT * FROM posts WHERE user_id = 2;  -- +1
...                                      -- +N
# Cəmi: 11 query (1 + 10)

# Həll yolu: DataLoader (batching)
SELECT * FROM users LIMIT 10;                              -- 1 query
SELECT * FROM posts WHERE user_id IN (1,2,3,...,10);      -- 1 query
# Cəmi: 2 query
```

### Resolvers

```
Query {
  user(id: 42) {          -> userResolver(root, {id: 42})
    name                   -> default resolver (user.name)
    posts {                -> postsResolver(user, args)
      title                -> default resolver (post.title)
      comments {           -> commentsResolver(post, args)
        body               -> default resolver (comment.body)
      }
    }
  }
}
```

### Pagination (Relay Cursor Style)

```graphql
type Query {
  users(first: Int, after: String, last: Int, before: String): UserConnection!
}

type UserConnection {
  edges: [UserEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}

type UserEdge {
  node: User!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}
```

### GraphQL vs REST

```
+------------------+------------------+------------------+
| Feature          | REST             | GraphQL          |
+------------------+------------------+------------------+
| Endpoints        | Çoxlu            | Tək (/graphql)   |
| Data fetching    | Over/under       | Tam lazım olan   |
| Versioning       | /v1, /v2         | Schema evolution |
| Caching          | HTTP cache asan  | Daha çətin       |
| File upload      | Native           | Ayrı spec lazım  |
| Error handling   | HTTP status      | 200 + errors[]   |
| Learning curve   | Aşağı            | Yüksək           |
| Real-time        | WebSocket/SSE    | Subscription     |
+------------------+------------------+------------------+
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Mobile BFF (Backend for Frontend) ssenarisi: iOS, Android, web — hər biri lazım olan field-ləri özü seçir
- Çox cür dashboard widget-lər olan admin panel — hər widget fərqli data seçimi tələb edir
- Laravel Lighthouse ilə schema-first approach: `@hasMany`, `@belongsTo`, `@paginate` directive-ləri Eloquent ilə avtomatik işləyir

**Trade-off-lar:**
- HTTP caching çətindir — hər şey `POST /graphql`-ə gedir; persisted queries ilə `GET` mümkündür
- Deeply nested query-lər serveri yükləyə bilər — complexity limit və depth limit tətbiq edin
- File upload üçün ayrı spec (multipart) lazımdır — REST-dəki kimi sadə deyil
- Error handling fərqlidir: xəta baş versə belə HTTP 200 qaytarır, `errors[]` field-ındə xəta olur

**Ne zaman istifadə olunmamalı:**
- Sadə CRUD API — overhead çox, fayda az
- Public API (3rd party developer-lər üçün) — REST daha tanınmış, tooling daha güclü
- File-heavy API — upload/download üçün REST daha uyğun
- Team GraphQL-ə yeni başlayırsa — learning curve ilkin produktivliyi azaldır

**Common mistakes:**
- N+1 problemini həll etməmək — ən çox görülən performance problemi
- Schema-nı REST endpoint-lər kimi dizayn etmək (`createUser` mutation əvəzinə `userCreate`)
- Bütün field-ləri nullable etmək — `!` (non-null) istifadə edin; client əmin olmalıdır ki, field mövcuddur
- Subscription-ları lazımsız yerdə istifadə etmək — polling daha sadədir az hallarda

## Nümunələr

### Ümumi Nümunə

Mobile app ssenarisi:
- İstifadəçi profili ekranı: `{ user(id: 42) { name, avatar, bio } }` — 3 field
- Admin panel: `{ user(id: 42) { name, email, role, createdAt, lastLogin, posts { count } } }` — 7+ field

Eyni endpoint, eyni server, fərqli client ehtiyacları.

### Kod Nümunəsi

Laravel Lighthouse quraşdırması:

```bash
composer require nuwave/lighthouse
php artisan vendor:publish --tag=lighthouse-schema
```

Schema Definition:

```graphql
# graphql/schema.graphql

type Query {
    users(
        name: String @where(operator: "like")
        status: String @eq
        orderBy: _ @orderBy(columns: ["created_at", "name"])
    ): [User!]! @paginate(defaultCount: 15)

    user(id: ID! @eq): User @find

    me: User @auth
}

type Mutation {
    createUser(input: CreateUserInput! @spread): User!
        @create

    updateUser(id: ID!, input: UpdateUserInput! @spread): User!
        @update

    deleteUser(id: ID!): User!
        @delete

    login(email: String!, password: String!): AuthPayload!
        @field(resolver: "App\\GraphQL\\Mutations\\Login")
}

type User {
    id: ID!
    name: String!
    email: String!
    posts: [Post!]! @hasMany
    profile: Profile @hasOne
    postsCount: Int! @count(relation: "posts")
    created_at: DateTime!
    updated_at: DateTime!
}

type Post {
    id: ID!
    title: String!
    body: String!
    author: User! @belongsTo(relation: "user")
    comments: [Comment!]! @hasMany
    tags: [Tag!]! @belongsToMany
    published_at: DateTime
}

input CreateUserInput {
    name: String! @rules(apply: ["required", "string", "max:255"])
    email: String! @rules(apply: ["required", "email", "unique:users"])
    password: String! @rules(apply: ["required", "min:8"])
}

input UpdateUserInput {
    name: String @rules(apply: ["string", "max:255"])
    email: String @rules(apply: ["email", "unique:users"])
}
```

Custom Resolver (Login mutation):

```php
namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use GraphQL\Error\Error;

class Login
{
    public function __invoke(mixed $root, array $args): array
    {
        $user = User::where('email', $args['email'])->first();

        if (!$user || !Hash::check($args['password'], $user->password)) {
            throw new Error('Invalid credentials');
        }

        $token = $user->createToken('api')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
```

N+1 Prevention:

```php
namespace App\GraphQL\Queries;

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class PopularPosts
{
    public function __invoke($root, array $args, GraphQLContext $context): Collection
    {
        return Post::query()
            ->with(['author', 'tags'])  // Eager loading - N+1 prevention
            ->withCount('comments')
            ->where('published_at', '<=', now())
            ->orderByDesc('comments_count')
            ->limit($args['limit'] ?? 10)
            ->get();
    }
}
```

Middleware və Authorization:

```graphql
type Query {
    # Yalnız authenticated users
    me: User @auth @guard(with: ["sanctum"])

    # Admin only
    adminDashboard: DashboardData!
        @guard(with: ["sanctum"])
        @can(ability: "viewDashboard")
}

type Mutation {
    # Throttle
    login(email: String!, password: String!): AuthPayload!
        @throttle(maxAttempts: 5, decayMinutes: 1)

    # Validation + Auth
    updateProfile(input: UpdateProfileInput! @spread): User!
        @guard(with: ["sanctum"])
        @update
        @inject(context: "user.id", name: "id")
}
```

Testing GraphQL:

```php
namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;

class UserGraphQLTest extends TestCase
{
    use MakesGraphQLRequests;

    public function test_can_query_users(): void
    {
        User::factory()->count(3)->create();

        $this->graphQL('
            {
                users(first: 10) {
                    data {
                        id
                        name
                        email
                    }
                    paginatorInfo {
                        total
                        currentPage
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'users' => [
                    'paginatorInfo' => [
                        'total' => 3,
                    ],
                ],
            ],
        ]);
    }

    public function test_can_create_user(): void
    {
        $this->graphQL('
            mutation {
                createUser(input: {
                    name: "Test User"
                    email: "test@example.com"
                    password: "password123"
                }) {
                    id
                    name
                    email
                }
            }
        ')->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ],
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Schema dizayn edin**

Blog application üçün GraphQL schema yazın. Tələblər:
- `Post`, `Comment`, `Tag`, `User` type-ları
- Postları `status`, `tag`, `author` ilə filtrləmək
- Cursor-based pagination
- `publishPost` mutation

**Tapşırıq 2: N+1-i müəyyən edin və həll edin**

Aşağıdakı query-nin N+1 problemini tapın və Lighthouse directive-ləri ilə həll edin:

```graphql
{
  posts(first: 20) {
    title
    author {        # N+1!
      name
    }
    tags {          # N+1!
      name
    }
  }
}
```

Həll: Schema-da `@belongsTo` və `@belongsToMany` directive-lərini istifadə edin.

**Tapşırıq 3: Query complexity limiti qurun**

```php
// config/lighthouse.php
'security' => [
    'max_query_complexity' => 200,
    'max_query_depth' => 7,
    'disable_introspection' => env('GRAPHQL_DISABLE_INTROSPECTION', false),
],
```

Sonra bu query-nin complexity-sini hesablayın:
```graphql
{ users { posts { comments { author { posts { title } } } } } }
```

Niyə depth limit 7 ağlabatandır? Layihəniz üçün doğru hədd nədir?

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [HTTP Protocol](05-http-protocol.md)
- [WebSocket](11-websocket.md)
- [API Security](17-api-security.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [gRPC](10-grpc.md)
