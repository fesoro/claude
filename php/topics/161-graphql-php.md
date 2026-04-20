# GraphQL in PHP

## Mündəricat
1. [GraphQL nədir?](#graphql-nədir)
2. [REST vs GraphQL](#rest-vs-graphql)
3. [Schema-first vs Code-first](#schema-first-vs-code-first)
4. [Lighthouse (Laravel)](#lighthouse-laravel)
5. [webonyx/graphql-php (framework-agnostic)](#webonyxgraphql-php)
6. [N+1 Problem & DataLoader](#n1-problem--dataloader)
7. [Auth & Authorization](#auth--authorization)
8. [Mutations, Subscriptions](#mutations-subscriptions)
9. [Performance & Caching](#performance--caching)
10. [Pitfalls & Best Practices](#pitfalls--best-practices)
11. [İntervyu Sualları](#intervyu-sualları)

---

## GraphQL nədir?

```
GraphQL — 2012-də Facebook tərəfindən yaradıldı, 2015-də open-source.
Query language + runtime.
Client "hansı sahələri istədiyini" təyin edir — server tam o qədər qaytarır.

REST:
  GET /users/42         → bütün user sahələri (bəzisi lazım olmaya bilər)
  GET /users/42/posts   → ayrı request
  GET /users/42/posts/1/comments → daha bir request

GraphQL:
  POST /graphql
  {
    user(id: 42) {
      name
      email
      posts {
        title
        comments {
          body
          author { name }
        }
      }
    }
  }
  → BİR request, yalnız istənən sahələr qaytarılır

Üç əməliyyat növü:
  query    → oxuma (REST GET)
  mutation → yazma (REST POST/PUT/DELETE)
  subscription → real-time (WebSocket üzərində)
```

---

## REST vs GraphQL

```
Aspekt                REST                        GraphQL
────────────────────────────────────────────────────────────────
Endpoint              Çoxlu (/users, /posts)      Tək (/graphql)
Over-fetching         Var (lazımsız sahələr)      Yox (client seçir)
Under-fetching        Var (əlavə request)         Yox (nested query)
Versioning            URL (/v1, /v2)              Schema deprecation
Caching               HTTP cache asan             Persisted queries lazım
File upload           Adi multipart               graphql-upload spec
Error handling        HTTP status code            200 OK + errors array
Learning curve        Aşağı                       Orta
Tooling               Böyük ekosistem             Apollo, Relay
Mobile performance    Pis (çox roundtrip)         Yaxşı (1 request)

GraphQL nə vaxt MƏNA verir:
  ✓ Çoxlu client (mobile, web, partner) — hər biri fərqli sahələr
  ✓ Mürəkkəb nested data (social graph, e-commerce catalog)
  ✓ Rapid iteration — backend schema dəyişmir, client query dəyişir
  ✓ BFF pattern əvəzinə universal API

GraphQL NƏ VAXT lazımsızdır:
  ✗ Sadə CRUD API, tək client
  ✗ File-heavy (video streaming, large downloads)
  ✗ Strict REST ekosistem (HATEOAS, OpenAPI toolchain)
  ✗ Small team — operational complexity artır
```

---

## Schema-first vs Code-first

```
Schema-first (SDL — Schema Definition Language):
  schema.graphql faylda yazılır, kod onu implement edir.

  # schema.graphql
  type User {
    id: ID!
    name: String!
    email: String!
    posts: [Post!]!
  }

  type Post {
    id: ID!
    title: String!
    author: User!
  }

  type Query {
    user(id: ID!): User
  }

  Üstünlük: Frontend və backend eyni schema-nı görür (contract-first).
  Çatışmaz: PHP type ilə schema arasında drift ola bilər.

Code-first:
  PHP kodundan schema generate olunur.

  $userType = new ObjectType([
      'name' => 'User',
      'fields' => [
          'id'    => Type::nonNull(Type::id()),
          'name'  => Type::nonNull(Type::string()),
          'posts' => Type::listOf($postType),
      ],
  ]);

  Üstünlük: Type-safety, refactoring asan.
  Çatışmaz: Schema "gizli" qalır, contract ayrıca export edilməlidir.

Lighthouse schema-first (SDL + @directive).
webonyx/graphql-php həm schema-first həm code-first dəstəkləyir.
```

---

## Lighthouse (Laravel)

```bash
# Quraşdırma
composer require nuwave/lighthouse
php artisan vendor:publish --tag=lighthouse-schema
# schema.graphql graphql/ folder-də yaranır
```

```graphql
# graphql/schema.graphql
type User {
    id: ID!
    name: String!
    email: String!
    posts: [Post!]! @hasMany
}

type Post {
    id: ID!
    title: String!
    body: String!
    author: User! @belongsTo
    comments: [Comment!]! @hasMany
}

type Comment {
    id: ID!
    body: String!
    post: Post! @belongsTo
    author: User! @belongsTo
}

type Query {
    user(id: ID! @eq): User @find
    users(
        name: String @where(operator: "like")
    ): [User!]! @paginate(defaultCount: 10)

    posts(
        # Nested filter
        authorId: ID @eq(key: "author_id")
    ): [Post!]! @paginate
}

type Mutation {
    createPost(
        title: String! @rules(apply: ["required", "min:3"])
        body: String!  @rules(apply: ["required"])
    ): Post @create @guard(with: "sanctum")

    deletePost(id: ID!): Post @delete @can(ability: "delete")
}
```

```php
<?php
// Lighthouse directive-ləri Eloquent-ə avtomatik bağlayır.
// @hasMany, @belongsTo, @find, @paginate — hazır.
// Custom logic üçün resolver yazılır.

// app/GraphQL/Queries/Stats.php
namespace App\GraphQL\Queries;

class Stats
{
    public function __invoke($_, array $args): array
    {
        return [
            'users_count' => User::count(),
            'posts_count' => Post::count(),
        ];
    }
}
```

```graphql
# schema.graphql-ə əlavə
type Stats {
    users_count: Int!
    posts_count: Int!
}

extend type Query {
    stats: Stats! @field(resolver: "App\\GraphQL\\Queries\\Stats")
}
```

---

## webonyx/graphql-php

```bash
composer require webonyx/graphql-php
```

```php
<?php
// Framework-agnostic GraphQL server.
// Slim, Symfony, vanilla PHP — hamısında işləyir.

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\GraphQL;

// User type
$userType = new ObjectType([
    'name' => 'User',
    'fields' => fn() => [
        'id'    => Type::nonNull(Type::id()),
        'name'  => Type::nonNull(Type::string()),
        'email' => Type::nonNull(Type::string()),
        'posts' => [
            'type'    => Type::listOf($postType),
            'resolve' => fn($user) => Post::where('user_id', $user['id'])->get()->toArray(),
        ],
    ],
]);

// Query root
$queryType = new ObjectType([
    'name' => 'Query',
    'fields' => [
        'user' => [
            'type' => $userType,
            'args' => [
                'id' => Type::nonNull(Type::id()),
            ],
            'resolve' => fn($_, $args) => User::find($args['id'])?->toArray(),
        ],
    ],
]);

$schema = new Schema([
    'query' => $queryType,
]);

// HTTP handler
$input = json_decode(file_get_contents('php://input'), true);
$result = GraphQL::executeQuery(
    $schema,
    $input['query'],
    null,
    null,   // context — auth, request info ötürülür
    $input['variables'] ?? null
);

header('Content-Type: application/json');
echo json_encode($result->toArray());
```

---

## N+1 Problem & DataLoader

```
GraphQL-in ən böyük performance tələsi — N+1.

Query:
  {
    users {       # 1 query: SELECT * FROM users (100 user)
      posts {     # 100 query: SELECT * FROM posts WHERE user_id = ?
        comments  # 10_000 query: SELECT * FROM comments WHERE post_id = ?
      }
    }
  }

Həll: DataLoader — batch + cache.
  Bir request içində eyni tipdə ID-ləri yığır, tək query ilə çəkir.
  
  Addım 1: user 1, 2, 3 ... 100 üçün posts istənir
  Addım 2: DataLoader queue-ya yığır
  Addım 3: Tick sonu: SELECT * FROM posts WHERE user_id IN (1,2,...,100)
  Addım 4: Hər user-ə öz posts-u paylanır
  
  Nəticə: 3 query (users + posts + comments), N+1 yoxdur.
```

```php
<?php
// Lighthouse built-in BatchLoader

// app/GraphQL/Loaders/PostsByUserLoader.php
namespace App\GraphQL\Loaders;

use Nuwave\Lighthouse\Execution\BatchLoader\RelationBatchLoader;
use Nuwave\Lighthouse\Execution\ModelsLoader\SimpleModelsLoader;

// schema.graphql-də:
//   posts: [Post!]! @hasMany
// Lighthouse @hasMany direktivi avtomatik batch loading edir.

// Manual DataLoader (webonyx):
use Overblog\DataLoader\DataLoader;

$postsLoader = new DataLoader(function (array $userIds) {
    // Batch: 100 user_id → 1 query
    $posts = Post::whereIn('user_id', $userIds)->get();
    
    // ID-yə görə qrupla
    $grouped = $posts->groupBy('user_id');
    
    // Sıra ilə qaytar (DataLoader sırayı qorumalıdır!)
    return array_map(
        fn($id) => $grouped->get($id, collect())->toArray(),
        $userIds
    );
});

// Resolver içində:
'posts' => [
    'type'    => Type::listOf($postType),
    'resolve' => fn($user) => $postsLoader->load($user['id']),
],
```

---

## Auth & Authorization

```php
<?php
// Lighthouse @guard və @can directive-ləri

// schema.graphql
// type Mutation {
//     deletePost(id: ID!): Post @delete @can(ability: "delete", find: "id")
// }

// app/Policies/PostPolicy.php
class PostPolicy
{
    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }
}

// Context (request scope) üzərindən current user
// Lighthouse request lifecycle-da auth()->user() çatır.

// Query-complexity limit — DoS qarşısı
// config/lighthouse.php
// 'security' => [
//     'max_query_complexity' => 200,
//     'max_query_depth' => 10,
//     'disable_introspection' => env('APP_ENV') === 'production',
// ],
```

---

## Mutations, Subscriptions

```graphql
type Mutation {
    createPost(input: CreatePostInput!): Post!
    updatePost(id: ID!, input: UpdatePostInput!): Post!
    deletePost(id: ID!): Post!
}

input CreatePostInput {
    title: String!
    body: String!
}

type Subscription {
    postCreated: Post!
    commentAdded(postId: ID!): Comment!
}
```

```php
<?php
// Lighthouse Subscription — Pusher/Reverb üzərində
// config/lighthouse.php
// 'subscriptions' => [
//     'broadcaster' => 'pusher',
// ],

// Subscription trigger:
use Nuwave\Lighthouse\Execution\Utils\Subscription;

class PostObserver
{
    public function created(Post $post): void
    {
        Subscription::broadcast('postCreated', $post);
    }
}
```

---

## Performance & Caching

```
GraphQL caching REST-dən çətin — URL dəyişməzdir (/graphql).
Həll yolları:

1. Persisted Queries (automatic persisted queries — APQ):
   Client query-ni hash-ləyir, server qoruyur.
   İkinci request: GET /graphql?hash=abc123 → CDN cache edə bilir.

2. Field-level caching (Lighthouse @cache):
   type Query {
       expensiveStats: Stats! @cache(maxAge: 300)
   }

3. Response caching (redis):
   Tam response cache — yalnız variable və query fərq edirsə miss.

4. Automatic Persisted Queries (APQ):
   Apollo Client → Laravel Lighthouse dəstəkləyir.

Query complexity analysis:
  Hər field-ə weight verilir, max threshold keçilməz:
  - scalar = 1
  - relation = 10
  - paginate = 50
  - Toplam > 1000 → query rədd edilir.

Depth limit:
  nested 20+ səviyyə query DoS vektoru — max_query_depth = 10.
```

---

## Pitfalls & Best Practices

```
❌ Pitfalls
  - N+1 (DataLoader istifadə et)
  - Over-fetching (limit query complexity)
  - Introspection production-da açıq (disable et)
  - File upload — multipart spec ayrıca lazım
  - Versioning — deprecation yavaş (field @deprecated istifadə et)
  - Error masking — field-səviyyəli errors array yanlış istifadə oluna bilər
  - Rate limiting — query complexity-ə görə, request sayına görə YOX

✓ Best Practices
  - DataLoader bütün N+1 olan field-lər üçün
  - Pagination cursor-based (Relay spec)
  - Input validation — @rules directive və ya resolver içində
  - Query whitelisting production-da (persisted queries)
  - Schema-first + ayrıca schema.graphql versiyonlaşdırma
  - Monitoring: Apollo Studio, Hive, Grafana ilə slow query tracking
  - Frontend schema update-ləri üçün CI-də schema diff check
```

---

## İntervyu Sualları

- GraphQL ilə REST arasında əsas fərqlər nədir? Nə vaxt hansını seçmək lazımdır?
- N+1 problemini GraphQL-də necə həll edirsiniz? DataLoader necə işləyir?
- Query complexity və depth limit nədir? Niyə lazımdır?
- Schema-first və code-first arasındakı fərq nədir?
- GraphQL versiyonlama — REST-də URL dəyişir, GraphQL-də necədir?
- Subscription necə işləyir? Protokol nədir?
- Lighthouse-də @hasMany directive arxada nə edir?
- GraphQL production-da introspection açıq saxlanılmalıdırmı? Niyə?
- BFF pattern ilə GraphQL arasındakı əlaqə nədir?
- Persisted query nədir, hansı problemi həll edir?
- Error handling — GraphQL 200 OK + errors array niyə seçib?
- Federation (Apollo) nədir, microservice mühitdə necə işləyir?
