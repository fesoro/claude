# GraphQL Testing

## Nədir? (What is it?)

**GraphQL** — API query dilidir. REST-dən fərqli olaraq, klient **tam lazım olan
sahələri** seçir və bir endpoint (`/graphql`) üzərindən bütün əməliyyatlar aparılır.

**GraphQL testing** — schema, query, mutation, subscription və permission-ların
yoxlanmasıdır.

**GraphQL spesifik test problemləri:**
- **Schema evolution** — breaking change-lər
- **N+1 query problemi** — nested resolverlər
- **Over-fetching (field-level)** — icazə yoxlaması hər sahədə
- **Subscription (WebSocket)** testi
- **Query complexity** — DoS qarşısı
- **Fragment və alias** istifadəsi

**Laravel ekosistemi:** Lighthouse — populyar GraphQL paketi, test helper-ləri ilə.

## Əsas Konseptlər (Key Concepts)

### 1. GraphQL Əməliyyat Növləri

| Növ | Təsvir | HTTP |
|-----|--------|------|
| **Query** | Data oxumaq (GET-ə bənzər) | POST |
| **Mutation** | Data dəyişmək (POST/PUT) | POST |
| **Subscription** | Real-time stream | WebSocket |

### 2. Schema Testing

Schema — GraphQL API-nin "contract"-ıdır. Schema dəyişmə breaking change-lər yarada bilər.

**Yoxlanılır:**
- Type-lar (User, Post) dəyişməyib
- Required sahələr yoxa çıxmayıb
- Deprecated field-lər düzgün işarələnib
- Enum dəyərləri sabitdir

### 3. Query Testing

- Məlumat düzgün qaytarılır?
- **Authorization** — yalnız icazəsi olanlar görür
- **Field-level permissions** — bəzi sahələr gizli
- **Errors** — nice error handling

### 4. Mutation Testing

- Validation işləyir?
- DB-də dəyişiklik baş verir?
- Authorization doğrudur?
- Side effects (email, event) düzgündür?

### 5. N+1 Problem

```graphql
query {
  posts {       # 1 query: SELECT * FROM posts
    title
    author {    # N query: SELECT * FROM users WHERE id = ?
      name
    }
  }
}
```

**Həll:** DataLoader pattern, eager loading, Lighthouse `@with` directive.

### 6. Subscription Testing

WebSocket üzərindən real-time updates. Test etmək çətindir — mock broadcaster lazım.

### 7. Field-Level Permissions

```graphql
type User {
  id: ID!
  name: String!
  email: String! @can(ability: "viewEmail")  # Yalnız özü və ya admin
  ssn: String @can(ability: "viewSSN")       # Yalnız admin
}
```

## Praktiki Nümunələr

### Nümunə 1: Over-fetching (REST problemi)
```
REST: GET /users/1 → hər şeyi qaytarır (50 field)
GraphQL: yalnız {id, name} — dəqiq lazım olanı
```

### Nümunə 2: N+1
```
GraphQL query: 100 post + hər post-un author-u
→ Naive: 1 + 100 = 101 query
→ DataLoader: 2 query (batching)
```

### Nümunə 3: Breaking change
```
Schema v1: user { email: String! }
Schema v2: user { email: String } (nullable → breaking!)
→ Mövcud klientlər qırılır
```

## PHP/Laravel ilə Tətbiq

### 1. Lighthouse GraphQL Schema

```graphql
# graphql/schema.graphql
type Query {
    users: [User!]! @all
    user(id: ID! @eq): User @find
    posts(published: Boolean @eq): [Post!]! @all
}

type Mutation {
    createUser(input: CreateUserInput! @spread): User @create
    updatePost(id: ID!, input: UpdatePostInput! @spread): Post
        @update @can(ability: "update", find: "id")
    deletePost(id: ID!): Post @delete @can(ability: "delete", find: "id")
}

type Subscription {
    postCreated: Post
}

type User {
    id: ID!
    name: String!
    email: String! @can(ability: "viewEmail")
    posts: [Post!]! @hasMany
    createdAt: DateTime!
}

type Post {
    id: ID!
    title: String!
    content: String!
    author: User! @belongsTo
    publishedAt: DateTime
}

input CreateUserInput {
    name: String! @rules(apply: ["required", "max:255"])
    email: String! @rules(apply: ["required", "email", "unique:users"])
    password: String! @rules(apply: ["required", "min:8"])
}

input UpdatePostInput {
    title: String
    content: String
}
```

### 2. Lighthouse MakesGraphQLRequests Trait

```php
// tests/TestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;
}
```

### 3. Query Testing

```php
// tests/Feature/GraphQL/UserQueryTest.php
namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserQueryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_can_query_public_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                users {
                    id
                    name
                }
            }
        ');

        $response->assertJsonStructure([
            'data' => [
                'users' => [
                    '*' => ['id', 'name']
                ]
            ]
        ]);

        $this->assertCount(3, $response->json('data.users'));
    }

    /** @test */
    public function user_can_query_specific_user(): void
    {
        $user = User::factory()->create(['name' => 'John']);

        $response = $this->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                user(id: $id) {
                    id
                    name
                }
            }
        ', ['id' => $user->id]);

        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => (string) $user->id,
                    'name' => 'John',
                ]
            ]
        ]);
    }

    /** @test */
    public function returns_null_for_non_existent_user(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                user(id: 99999) {
                    id
                }
            }
        ');

        $this->assertNull($response->json('data.user'));
    }

    /** @test */
    public function validates_id_argument(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                user {
                    id
                }
            }
        ');

        $response->assertGraphQLValidationError('id', 'required');
    }
}
```

### 4. Mutation Testing

```php
// tests/Feature/GraphQL/CreateUserMutationTest.php
namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreateUserMutationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creates_user_with_valid_data(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: CreateUserInput!) {
                createUser(input: $input) {
                    id
                    name
                    email
                }
            }
        ', [
            'input' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'secret123',
            ]
        ]);

        $response->assertJson([
            'data' => [
                'createUser' => [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ]
            ]
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    /** @test */
    public function validates_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: CreateUserInput!) {
                createUser(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'name' => 'Test',
                'email' => 'taken@example.com',
                'password' => 'secret123',
            ]
        ]);

        $response->assertGraphQLValidationError('input.email', 'unique');
    }

    /** @test */
    public function rejects_short_password(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation ($input: CreateUserInput!) {
                createUser(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'name' => 'Test',
                'email' => 'test@example.com',
                'password' => '123',
            ]
        ]);

        $response->assertGraphQLValidationError('input.password', 'min');
    }
}
```

### 5. Authorization Testing

```php
// tests/Feature/GraphQL/PostAuthorizationTest.php
class PostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function author_can_update_own_post(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $response = $this->actingAs($author)->graphQL(/** @lang GraphQL */ '
            mutation ($id: ID!, $input: UpdatePostInput!) {
                updatePost(id: $id, input: $input) {
                    id
                    title
                }
            }
        ', [
            'id' => $post->id,
            'input' => ['title' => 'Updated Title'],
        ]);

        $response->assertJson([
            'data' => ['updatePost' => ['title' => 'Updated Title']]
        ]);
    }

    /** @test */
    public function non_author_cannot_update_post(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $response = $this->actingAs($other)->graphQL(/** @lang GraphQL */ '
            mutation ($id: ID!, $input: UpdatePostInput!) {
                updatePost(id: $id, input: $input) {
                    id
                }
            }
        ', [
            'id' => $post->id,
            'input' => ['title' => 'Hack'],
        ]);

        $response->assertGraphQLErrorMessage('This action is unauthorized.');
    }
}
```

### 6. Field-Level Permission Testing

```php
// tests/Feature/GraphQL/FieldPermissionTest.php
class FieldPermissionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function non_admin_cannot_see_email(): void
    {
        $otherUser = User::factory()->create(['email' => 'hidden@example.com']);
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                user(id: $id) {
                    id
                    name
                    email
                }
            }
        ', ['id' => $otherUser->id]);

        $response->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    /** @test */
    public function admin_can_see_email(): void
    {
        $user = User::factory()->create(['email' => 'visible@example.com']);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->graphQL(/** @lang GraphQL */ '
            query ($id: ID!) {
                user(id: $id) {
                    id
                    email
                }
            }
        ', ['id' => $user->id]);

        $response->assertJson([
            'data' => [
                'user' => ['email' => 'visible@example.com']
            ]
        ]);
    }

    /** @test */
    public function user_can_see_own_email(): void
    {
        $user = User::factory()->create(['email' => 'self@example.com']);

        $response = $this->actingAs($user)->graphQL(/** @lang GraphQL */ '
            query {
                me {
                    email
                }
            }
        ');

        $response->assertJson([
            'data' => ['me' => ['email' => 'self@example.com']]
        ]);
    }
}
```

### 7. N+1 Detection Test

```php
// tests/Feature/GraphQL/NPlusOneTest.php
use Illuminate\Support\Facades\DB;

class NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function posts_with_author_does_not_cause_n_plus_one(): void
    {
        User::factory()
            ->count(10)
            ->has(Post::factory()->count(5), 'posts')
            ->create();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->graphQL(/** @lang GraphQL */ '
            query {
                posts {
                    id
                    title
                    author {
                        id
                        name
                    }
                }
            }
        ')->assertOk();

        $queryCount = count(DB::getQueryLog());

        // N+1 olmasa: 2 query (posts + users eager loaded)
        // N+1 olsa: 1 + 50 = 51 query
        $this->assertLessThanOrEqual(
            3,
            $queryCount,
            "Too many queries ($queryCount), possible N+1 problem"
        );
    }
}
```

### 8. Schema Introspection Testing

```php
// tests/Feature/GraphQL/SchemaTest.php
class SchemaTest extends TestCase
{
    /** @test */
    public function schema_has_required_types(): void
    {
        $response = $this->introspect();

        $types = collect($response->json('data.__schema.types'))->pluck('name');

        $this->assertContains('User', $types);
        $this->assertContains('Post', $types);
        $this->assertContains('Query', $types);
        $this->assertContains('Mutation', $types);
    }

    /** @test */
    public function user_type_has_required_fields(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                __type(name: "User") {
                    fields {
                        name
                        type {
                            kind
                            name
                        }
                    }
                }
            }
        ');

        $fields = collect($response->json('data.__type.fields'))->pluck('name');

        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
    }

    /** @test */
    public function schema_matches_snapshot(): void
    {
        $response = $this->introspect();
        $schema = $response->json('data.__schema');

        $this->assertMatchesJsonSnapshot($schema);
    }
}
```

### 9. Subscription Testing

```php
// tests/Feature/GraphQL/SubscriptionTest.php
use Nuwave\Lighthouse\Subscriptions\Contracts\BroadcastsSubscriptions;
use Illuminate\Support\Facades\Event;
use Nuwave\Lighthouse\Subscriptions\Events\BroadcastSubscriptionEvent;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function post_created_subscription_broadcasts(): void
    {
        Event::fake([BroadcastSubscriptionEvent::class]);

        // 1. Subscribe
        $subscription = $this->graphQL(/** @lang GraphQL */ '
            subscription {
                postCreated {
                    id
                    title
                }
            }
        ');

        $channel = $subscription->json('extensions.lighthouse_subscriptions.channel');
        $this->assertNotEmpty($channel);

        // 2. Mutation trigger subscription
        $this->graphQL(/** @lang GraphQL */ '
            mutation {
                createPost(input: { title: "New Post", content: "..." }) {
                    id
                }
            }
        ');

        // 3. Broadcast event dispatched
        Event::assertDispatched(BroadcastSubscriptionEvent::class);
    }
}
```

### 10. Query Complexity Testing

```php
// lighthouse.php config
'security' => [
    'max_query_complexity' => 100,
    'max_query_depth' => 7,
],

// Test
class QueryComplexityTest extends TestCase
{
    /** @test */
    public function rejects_overly_deep_query(): void
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                users {
                    posts {
                        author {
                            posts {
                                author {
                                    posts {
                                        author {
                                            posts { id }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ');

        $response->assertGraphQLErrorMessage('Max query depth should be 7 but got');
    }
}
```

## Interview Sualları (Q&A)

### S1: GraphQL və REST test fərqləri?
**C:**
- **REST**: Hər endpoint ayrı test (`GET /users`, `POST /users`)
- **GraphQL**: Bir endpoint (`/graphql`), lakin query/mutation adları ilə fərqli testlər

REST-də URL və HTTP method mühümdür. GraphQL-də query string və response structure
mühümdür. Hər ikisi üçün validation, auth, data correctness test olunur.

### S2: N+1 problemini necə test edirsiniz?
**C:** `DB::enableQueryLog()` aktivləşdirirəm, query işlədirəm, sonra
`count(DB::getQueryLog())` ilə query sayını yoxlayıram. Əgər gözləniləndən çoxdursa,
N+1 problemi var. Həll: DataLoader pattern, Lighthouse `@with` directive, eager loading.

### S3: Lighthouse-da `@can` directive nədir?
**C:** Authorization üçün directive-dir. Resolver çalışmadan əvvəl Laravel Policy
yoxlanılır:
```graphql
deletePost(id: ID!): Post @delete @can(ability: "delete", find: "id")
```
İstifadəçinin `PostPolicy::delete()` metoduna icazəsi yoxdursa, xəta qaytarılır.

### S4: GraphQL schema breaking change nədir?
**C:** Mövcud klientləri pozan dəyişiklik:
- Required field silmək
- Nullable → non-nullable dəyişmək
- Enum dəyərini silmək
- Type adını dəyişmək

**Non-breaking:** Yeni optional field əlavə etmək, deprecated olaraq işarələmək.
**Schema snapshot test** ilə breaking change-ləri aşkar etmək olar.

### S5: Subscription-ı necə test edirsiniz?
**C:** İki mərhələli test:
1. Subscribe → channel qaytarılır, gözlənilən format-da
2. Mutation işlət → `BroadcastSubscriptionEvent` dispatch olunur (Event::fake ilə yoxla)

Real WebSocket connection yaratmaq mürəkkəbdir — çox vaxt event dispatch-ini yoxlamaq
kifayətdir.

### S6: Query complexity niyə limitlənir?
**C:** DoS qarşısı üçün. Kötü niyyətli klient çox dərin query göndərə bilər:
```graphql
user { posts { author { posts { author { posts { ... } } } } } }
```
Bu server-i mahv edər. `max_query_depth` və `max_query_complexity` limitləri ilə
qarşısını alırıq.

### S7: Field-level permission test necə edilir?
**C:** Müxtəlif rollarla test işlədib hansı field-lərin qaytarıldığını yoxlayıram:
- **Admin**: bütün field-lər
- **Own user**: public + bəzi private
- **Other user**: yalnız public

Lighthouse `@can` directive field level icazəni yoxlayır.

### S8: GraphQL mutation-da validation necə test olunur?
**C:** Lighthouse `@rules` directive ilə validation təyin olunur:
```graphql
email: String! @rules(apply: ["required", "email", "unique:users"])
```

Test:
```php
$response->assertGraphQLValidationError('input.email', 'unique');
```

### S9: Fragment və alias test edilməlidir?
**C:** Əsasən yox — bunlar client-side kompozisiya alətləridir. Schema və resolver-lər
düzgün işləyirsə, fragment/alias avtomatik işləyir. Yalnız xüsusi directive-lər varsa
(məs. `@include`, `@skip`) test etmək lazım ola bilər.

### S10: Lighthouse-un `@with` directive-i nə edir?
**C:** Eager loading üçün:
```graphql
type Post {
    author: User! @belongsTo @with(relation: "author")
}
```
Lighthouse Eloquent `with('author')` əlavə edir — N+1-in qarşısını alır.

## Best Practices / Anti-Patterns

### Best Practices
1. **MakesGraphQLRequests trait** — Lighthouse test helper-ləri
2. **Schema snapshot** — breaking change aşkarlama
3. **N+1 query count test** — performans regresiyası
4. **Field-level auth test** — hər rol üçün
5. **Validation testləri** — `assertGraphQLValidationError`
6. **Query complexity limit** — DoS qarşısı
7. **Deprecated field işarələ** — breaking change-dən qaç
8. **DataLoader/`@with`** — eager loading
9. **Subscription event-ini yoxla** — WebSocket yerine
10. **Introspection test** — schema integrity

### Anti-Patterns
- **Her field üçün ayrı REST endpoint** — GraphQL-ın mənasını itirir
- **No N+1 detection** — production-da yavaşlayır
- **No query complexity limit** — DoS zəifliyi
- **Authorization resolver-də** — `@can` directive istifadə et
- **Breaking change schema-da** — klientlər qırılır
- **Test yalnız happy path** — auth və validation yoxlanmır
- **Full introspection production-da** — məlumat leak
- **No field deprecation** — birdən silmək
- **Mock GraphQL client** — real Lighthouse istifadə et
- **Subscription real WebSocket ilə test** — çox mürəkkəb, event fake kifayətdir

### GraphQL Testing Checklist
- [ ] Bütün query-lər üçün test
- [ ] Bütün mutation-lar üçün test (happy + validation + auth)
- [ ] Field-level permission-lar yoxlanılıb
- [ ] N+1 detection test
- [ ] Schema snapshot/introspection test
- [ ] Query complexity limitlənib və test olunur
- [ ] Subscription broadcast testi
- [ ] Error handling (null, invalid input)
- [ ] Deprecated field-lər doğru işarələnib
- [ ] Authentication-sız query-lər rədd olunur (qorunan sahələrdə)
