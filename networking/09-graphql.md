# GraphQL

## Nədir? (What is it?)

GraphQL Facebook terefinden 2012-ci ilde yaradilib ve 2015-ci ilde open-source edilib. Client-in tam olaraq hansi data-ni istediyi sorusha bileceyi query language ve runtime-dir. REST-den ferqli olaraq, bir endpoint uzerinden isleyir ve over-fetching/under-fetching problemlerini hell edir.

```
REST:
  GET /api/users/42           -> {id, name, email, phone, address, ...}  (over-fetching)
  GET /api/users/42/posts     -> ayri request lazimdir (under-fetching)
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
  -> Tam istediyin data, bir request-de
```

## Necə İşləyir? (How does it work?)

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

# Input types (mutation ucun)
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

# MUTATION - data deyismek
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

# Variables istifade etmek
query GetUser($id: ID!) {
  user(id: $id) {
    name
    email
  }
}
# Variables: {"id": "42"}

# Fragments - tekrarlanan fieldleri birlesdirmek
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

## Əsas Konseptlər (Key Concepts)

### N+1 Problem

```
# Bu query icra olunanda:
{
  users(first: 10) {
    name
    posts { title }   # Her user ucun ayri SQL query!
  }
}

# N+1 problem:
SELECT * FROM users LIMIT 10;           -- 1 query
SELECT * FROM posts WHERE user_id = 1;  -- +1
SELECT * FROM posts WHERE user_id = 2;  -- +1
...                                      -- +N
# Cemi: 11 query (1 + 10)

# Hell yolu: DataLoader (batching)
SELECT * FROM users LIMIT 10;                              -- 1 query
SELECT * FROM posts WHERE user_id IN (1,2,3,...,10);      -- 1 query
# Cemi: 2 query
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
| Endpoints        | Coxlu            | Tek (/graphql)   |
| Data fetching    | Over/under       | Tam lazim olan   |
| Versioning       | /v1, /v2         | Schema evolution |
| Caching          | HTTP cache asan  | Daha cetin       |
| File upload      | Native           | Ayri spec lazim  |
| Error handling   | HTTP status      | 200 + errors[]   |
| Learning curve   | Asagi            | Yuksek           |
| Real-time        | WebSocket/SSE    | Subscription     |
+------------------+------------------+------------------+
```

## PHP/Laravel ilə İstifadə

### Laravel Lighthouse (GraphQL Server)

```bash
composer require nuwave/lighthouse
php artisan vendor:publish --tag=lighthouse-schema
```

### Schema Definition

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

### Custom Resolver

```php
namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

### Custom Query with N+1 Prevention

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

### Middleware & Authorization

```graphql
type Query {
    # Yalniz authenticated users
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

### Testing GraphQL

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

## Interview Sualları

### 1. GraphQL nedir ve REST-den nece ferqlenir?
**Cavab:** GraphQL client-in lazim olan data-ni deqiq sorusha bileceyi query language-dir. REST-den ferqi: tek endpoint, over/under-fetching yoxdur, strongly typed schema, client data strukturunu ozü teyin edir.

### 2. N+1 problem nedir ve GraphQL-de nece hell olunur?
**Cavab:** Her parent entity ucun ayri query atilmasidir. 10 user-in posts-unu cekende 1+10=11 query olur. Hell yolu: DataLoader pattern - butun ID-leri toplayib tek batch query atir. Laravel Lighthouse-da `@hasMany` directive avtomatik eager loading edir.

### 3. Query, Mutation ve Subscription arasinda ferq nedir?
**Cavab:** **Query** - data oxumaq (GET). **Mutation** - data yazmaq/deyismek (POST/PUT/DELETE). **Subscription** - real-time updates almaq (WebSocket uzerinden). Subscription server push edir, client subscribe olur.

### 4. GraphQL schema nedir?
**Cavab:** API-nin type system-idir. Butun type-lari, query/mutation-lari, input-lari teyin edir. Strongly typed-dir - her field-in tipi melumdur. Schema client ve server arasinda contract rolunu oynayir.

### 5. GraphQL-de caching nece edilir?
**Cavab:** HTTP caching cetindir (her sey POST /graphql). Hell yollari: persisted queries (query hash ile GET request), Apollo Client cache (normalized client-side cache), server-side caching (Redis ile response cache), CDN caching (persisted queries ile).

### 6. GraphQL-in dezavantajlari nelerdir?
**Cavab:** File upload cetin, HTTP caching cetin, complexity control lazimdir (deeply nested query-ler serveri yuka biler), error handling ferqlidir (hemise 200 qaytarir), learning curve yuksek, simple API-lar ucun overkill ola biler.

### 7. Fragments nedir ve niye istifade olunur?
**Cavab:** Tekrarlanan field secimlerini bir yerde teyin etmek ucun istifade olunur. `fragment UserBasic on User { id, name }` yaradirsiniz ve `...UserBasic` ile her yerde istifade edirsiniz. DRY prinsipi ucun vacibdir.

## Best Practices

1. **N+1 helli** - DataLoader ve ya Lighthouse directive-leri ile eager loading
2. **Query complexity limiti** - Deeply nested query-leri mehdudlashdirin
3. **Depth limiting** - Max query depth teyin edin (meselen, 7)
4. **Persisted queries** - Production-da yalniz evvelceden teyin olunmus query-lere icaze verin
5. **Input validation** - Schema level + custom validation
6. **Error handling** - Structured error messages qaytarin
7. **Pagination** - Cursor-based pagination (Relay spec) istifade edin
8. **Schema design** - Domain-driven, REST resource naming conventions
9. **Monitoring** - Query performance ve error rate izleyin
10. **Versioning yerine evolution** - Schema-ni deprecate edin, deyismeyin
