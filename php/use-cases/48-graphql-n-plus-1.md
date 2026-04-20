# Use Case: GraphQL N+1 Problem & DataLoader

## Problem
Frontend GraphQL query yazır:
```graphql
{
  posts(first: 10) {
    title
    author { name }
    comments {
      body
      author { name }
    }
  }
}
```

Backend bu query üçün:
- 1 SQL: posts (10 row)
- 10 SQL: hər post üçün author
- 10 SQL: hər post üçün comments
- ~50 SQL: hər comment üçün author

Toplam ~71 SQL request. P99 latency 3000ms+.

---

## Həll: DataLoader pattern (per-request batch + cache)

```bash
composer require nuwave/lighthouse
# Lighthouse @hasMany, @belongsTo direktivləri built-in batch loader ilə işləyir
```

### 1. Schema (Lighthouse)

```graphql
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
    author: User! @belongsTo
}

type User {
    id: ID!
    name: String!
}

type Query {
    posts(first: Int = 10): [Post!]! @paginate
}
```

### 2. Manual DataLoader (webonyx ilə)

```php
<?php
use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;

$promiseAdapter = new WebonyxGraphQLSyncPromiseAdapter(new SyncPromiseAdapter());

// User batch loader — eyni request-də duplicate ID-ləri tut
$userLoader = new DataLoader(function (array $userIds) {
    $users = User::whereIn('id', $userIds)->get()->keyBy('id');
    return array_map(fn($id) => $users[$id] ?? null, $userIds);
}, $promiseAdapter);

// Comments by post_id
$commentsByPostLoader = new DataLoader(function (array $postIds) {
    $comments = Comment::whereIn('post_id', $postIds)->get()->groupBy('post_id');
    return array_map(fn($id) => $comments[$id] ?? collect(), $postIds);
}, $promiseAdapter);

// Resolver
$postType = new ObjectType([
    'name' => 'Post',
    'fields' => [
        'author' => [
            'type' => $userType,
            'resolve' => fn($post) => $userLoader->load($post->author_id),
        ],
        'comments' => [
            'type' => Type::listOf($commentType),
            'resolve' => fn($post) => $commentsByPostLoader->load($post->id),
        ],
    ],
]);
```

### 3. Lighthouse-də avtomatik

```graphql
# @hasMany, @belongsTo Lighthouse-də built-in batch loading edir
type Post {
    author: User! @belongsTo      # Eloquent eager-load batch
    comments: [Comment!]! @hasMany  # Eloquent has-many batch
}
```

Lighthouse arxa planda Eloquent `with(['author', 'comments.author'])` istifadə edir. 71 query → 4 query.

---

## Test (artıq query sayı yoxlanışı)

```php
<?php
test('posts query produces no N+1', function () {
    User::factory(5)->create();
    Post::factory(10)->create();
    Comment::factory(50)->create();
    
    DB::enableQueryLog();
    
    $response = $this->postJson('/graphql', [
        'query' => '
            { posts(first: 10) { 
                title 
                author { name } 
                comments { body, author { name } }
            }}
        '
    ]);
    
    $queryCount = count(DB::getQueryLog());
    
    expect($queryCount)->toBeLessThan(10);   // 71 → ~5
});
```

---

## Production query complexity guard

```php
<?php
// config/lighthouse.php
'security' => [
    'max_query_complexity' => 200,
    'max_query_depth' => 10,
    'disable_introspection' => env('APP_ENV') === 'production',
],
```

```graphql
# Hər field-ə complexity verilir
type Query {
    posts(first: Int!): [Post!]! @complexity(value: 10, multiplier: "first")
}

# first=100 → complexity = 1000 → max keçildi → query rədd
```

---

## Real-dünya nəticə

```
                 | Query count | P50 latency | P99 latency
─────────────────────────────────────────────────────────────
Naïve resolver  | 71          | 850ms       | 3200ms
DataLoader      | 5           | 80ms        | 280ms

11× sürət, 35× P99 yaxşılaşma.
```

---

## Pitfalls

```
❌ DataLoader cross-request cache — yox! Hər request scope.
❌ Mutation sonra DataLoader cache stale — clear lazımdır.
❌ Too many DataLoader-lər — coupling. Resolver-də manual N+1 yenidən yaranar.
❌ Async DataLoader ilə sync resolver qarışıq — promise leak.

✓ Bütün belongsTo/hasMany üçün loader
✓ Mutation handler-də cache clear
✓ Test query count assertion
✓ Production-da complexity limit
```
