# API Testing (Middle)
## İcmal

API testing, proqramın Application Programming Interface-lərini birbaşa test etmək prosesidir.
REST API-lər üçün bu HTTP request göndərib response-u yoxlamaq deməkdir. UI-dan keçmədən
birbaşa backend endpoint-ləri test edilir, bu da testləri daha sürətli və etibarlı edir.

API testləri integration testlərin bir növüdür. Əsas məqsəd endpoint-lərin düzgün status code,
response body, headers və error handling qaytardığını yoxlamaqdır. Laravel-də bu testlər
`Tests\Feature` qovluğunda yazılır və framework-ün HTTP testing helper-lərindən istifadə edir.

## Niyə Vacibdir

1. **Frontend-dən asılı deyil** - API testləri UI olmadan işləyir
2. **Sürətli feedback** - Browser testlərindən 10-100x sürətlidir
3. **Contract validation** - API-nin gözlənilən formatda cavab verdiyini təmin edir
4. **Regression prevention** - Endpoint dəyişiklikləri mövcud client-ləri pozmasın
5. **Documentation** - Testlər API-nin necə işləməli olduğunu sənədləşdirir

## Əsas Anlayışlar

### HTTP Methods və Status Codes

```
GET     /api/users          → 200 OK (list)
GET     /api/users/1        → 200 OK (single) / 404 Not Found
POST    /api/users          → 201 Created / 422 Unprocessable Entity
PUT     /api/users/1        → 200 OK / 404 Not Found
PATCH   /api/users/1        → 200 OK
DELETE  /api/users/1        → 204 No Content / 404 Not Found
```

### Status Code Kateqoriyaları

| Range | Mənası | Nümunə |
|-------|--------|--------|
| 2xx | Success | 200, 201, 204 |
| 3xx | Redirect | 301, 302, 304 |
| 4xx | Client Error | 400, 401, 403, 404, 422 |
| 5xx | Server Error | 500, 502, 503 |

### Request/Response Validation

Hər API testində yoxlanmalıdır:
- **Status code** - Düzgün HTTP status qaytarılır?
- **Response body** - JSON strukturu gözlənilən formatdadır?
- **Headers** - Content-Type, Authorization düzgündür?
- **Response time** - Cavab vaxtı məqbul həddədir?
- **Error handling** - Yanlış input-da düzgün error qaytarılır?

### REST API Test Strategiyası

```
Positive Testing:
  ✓ Düzgün data ilə request → gözlənilən response
  ✓ Bütün required field-lər göndərilir
  ✓ Valid authentication ilə request

Negative Testing:
  ✗ Yanlış data ilə request → düzgün error
  ✗ Missing required field-lər → 422 validation error
  ✗ Invalid token ilə request → 401 unauthorized
  ✗ Mövcud olmayan resource → 404 not found

Edge Cases:
  ~ Boş body ilə request
  ~ Çox böyük payload
  ~ Special characters input-da
  ~ Concurrent requests
```

## Praktik Baxış

### Best Practices

1. **Hər endpoint üçün positive və negative testlər yazın** - Həm düzgün, həm yanlış input test edin
2. **Response strukturunu assertJsonStructure ilə yoxlayın** - Bütün gözlənilən field-ləri yoxlayın
3. **Authentication testlərini ayrı class-da saxlayın** - Auth və business logic testlərini qarışdırmayın
4. **Factory-lər istifadə edin** - Hər testdə manual data yaratmaq yerinə factory pattern istifadə edin
5. **Status code-ları dəqiq yoxlayın** - `assertOk()` əvəzinə `assertStatus(200)` daha aydındır
6. **API testlərini feature testlər qovluğunda saxlayın** - `Tests\Feature\Api\` namespace istifadə edin

### Anti-Patterns

1. **Testlər arası asılılıq** - Test A-nın yaratdığı data-ya Test B-nin etibar etməsi
2. **Hardcoded ID-lər** - `getJson('/api/users/1')` əvəzinə dynamic ID istifadə edin
3. **Response body-ni tam yoxlamaq** - Timestamp kimi dəyişən field-lər testi qıra bilər
4. **Authentication-ı hər testdə təkrarlamaq** - setUp() və ya helper method istifadə edin
5. **Yalnız happy path test etmək** - Error case-ləri, edge case-ləri mütləq test edin
6. **Production API-yə test göndərmək** - Həmişə test/staging environment istifadə edin

## Nümunələr

### Sadə API Test Nümunəsi (Raw PHP)

```php
<?php

class ApiTestHelper
{
    private string $baseUrl;
    private string $token;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function authenticate(string $email, string $password): self
    {
        $response = $this->post('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->token = json_decode($response['body'], true)['token'];
        return $this;
    }

    public function get(string $uri, array $headers = []): array
    {
        return $this->request('GET', $uri, [], $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    private function request(string $method, string $uri, array $data, array $headers): array
    {
        $ch = curl_init($this->baseUrl . $uri);

        $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        if (isset($this->token)) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $statusCode, 'body' => $body];
    }
}
```

## Praktik Tapşırıqlar

### Əsas API Test Strukturu

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_lists_all_posts(): void
    {
        Post::factory()->count(5)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'body', 'created_at'],
                ],
                'meta' => ['current_page', 'total'],
            ]);
    }

    /** @test */
    public function it_creates_a_post(): void
    {
        $postData = [
            'title' => 'Test Post',
            'body' => 'This is the body of the test post.',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/posts', $postData);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'title' => 'Test Post',
                    'body' => 'This is the body of the test post.',
                    'user_id' => $this->user->id,
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_shows_a_single_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $post->id,
                    'title' => $post->title,
                ],
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_post(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/posts/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Not found.',
            ]);
    }

    /** @test */
    public function it_updates_a_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/posts/{$post->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => ['title' => 'Updated Title'],
            ]);
    }

    /** @test */
    public function it_deletes_a_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
```

### Validation Testing

```php
<?php

/** @test */
public function it_validates_required_fields_on_create(): void
{
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/posts', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'body'])
        ->assertJson([
            'errors' => [
                'title' => ['The title field is required.'],
                'body' => ['The body field is required.'],
            ],
        ]);
}

/** @test */
public function it_validates_title_max_length(): void
{
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/posts', [
            'title' => str_repeat('a', 256),
            'body' => 'Valid body.',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
}
```

### Authentication və Authorization Testing

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Post;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unauthenticated_users_cannot_access_api(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function it_uses_sanctum_acting_as(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['posts:read']);

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
    }

    /** @test */
    public function token_without_required_ability_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['posts:read']);

        // posts:write ability tələb edir amma yoxdur
        $response = $this->postJson('/api/posts', [
            'title' => 'Test',
            'body' => 'Body',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_update_another_users_post(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->putJson("/api/posts/{$post->id}", [
                'title' => 'Hacked!',
            ]);

        $response->assertStatus(403);
    }
}
```

### Pagination və Filtering Test

```php
<?php

/** @test */
public function it_paginates_results(): void
{
    Post::factory()->count(25)->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?page=2&per_page=10');

    $response->assertStatus(200)
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 25);
}

/** @test */
public function it_filters_posts_by_status(): void
{
    Post::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'status' => 'published',
    ]);
    Post::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?status=published');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
}

/** @test */
public function it_sorts_posts_by_created_at(): void
{
    $old = Post::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(2),
    ]);
    $new = Post::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts?sort=-created_at');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id);
}
```

### Response Header Testing

```php
<?php

/** @test */
public function api_returns_correct_content_type(): void
{
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts');

    $response->assertHeader('Content-Type', 'application/json');
}

/** @test */
public function cors_headers_are_present(): void
{
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts');

    $response->assertHeader('Access-Control-Allow-Origin');
}

/** @test */
public function rate_limiting_headers_are_set(): void
{
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/posts');

    $response->assertHeader('X-RateLimit-Limit')
        ->assertHeader('X-RateLimit-Remaining');
}
```

## Ətraflı Qeydlər

### 1. API testing nədir və niyə vacibdir?
**Cavab:** API testing, application programming interface-ləri birbaşa test etmək prosesidir. UI-dan keçmədən endpoint-lərə HTTP request göndərib response-u yoxlayırıq. Vacibdir çünki: frontend-dən asılı olmadan backend-i test edir, browser testlərindən çox sürətlidir, API contract-ını validate edir və regression bug-ları tutur.

### 2. assertJson və assertExactJson arasındakı fərq nədir?
**Cavab:** `assertJson` response-un verilən JSON-u *ehtiva etdiyini* yoxlayır - əlavə field-lər ola bilər. `assertExactJson` isə response-un *tam olaraq* verilən JSON ilə eyni olduğunu yoxlayır, heç bir əlavə field olmamalıdır. Adətən `assertJson` daha çevik olduğu üçün üstünlük verilir.

### 3. Sanctum::actingAs və actingAs arasındakı fərq nədir?
**Cavab:** `Sanctum::actingAs($user, ['ability'])` Sanctum token authentication-ı simulyasiya edir və token ability-lərini təyin etməyə imkan verir. `actingAs($user, 'sanctum')` isə sadəcə guard təyin edir, ability yoxlaması etmir. API token ability testləri üçün `Sanctum::actingAs` istifadə edin.

### 4. API testlərində validation error-ları necə test edilir?
**Cavab:** Laravel-də `assertJsonValidationErrors(['field_name'])` istifadə edilir. Bu 422 status code və errors array-ində field-in mövcudluğunu yoxlayır. Həmçinin `assertJsonValidationErrorFor('field')` və `assertJsonMissingValidationErrors(['field'])` da mövcuddur.

### 5. API Rate Limiting necə test edilir?
**Cavab:** Bir loop-da eyni endpoint-ə limit sayıda request göndəririk. Limit-dən sonrakı request 429 Too Many Requests status qaytarmalıdır. Laravel-də `Limit::none()` ilə testlərdə rate limit-i söndürmək, ya da `withoutMiddleware(ThrottleRequests::class)` istifadə etmək olar.

### 6. API versioning testlərini necə yazarsınız?
**Cavab:** Hər API versiyası üçün ayrı test class yazılır. `/api/v1/users` və `/api/v2/users` üçün fərqli testlər olmalıdır. V1 testləri köhnə response format-ını, v2 testləri yeni format-ı yoxlayır. Deprecated endpoint-lərin warning header qaytardığını da test etmək lazımdır.

### 7. API testlərində authentication necə idarə edilir?
**Cavab:** Laravel-də `actingAs()`, `Sanctum::actingAs()` və ya `withHeaders(['Authorization' => 'Bearer ' . $token])` istifadə edilir. Hər test üçün yeni user yaradılır, token-lər test scope-unda qalır, və `RefreshDatabase` trait-i ilə hər testdən sonra təmizlənir.

### 8. Postman/Newman ilə API testing-in faydaları nədir?
**Cavab:** Postman collection-lar API-ni sənədləşdirir və manual test etməyə imkan verir. Newman CLI tool-u ilə bu testlər CI/CD pipeline-da avtomatik işlədilə bilər. Amma Laravel feature testləri daha güclüdür çünki database state-i idarə edir və application internals-a çıxışı var.

## Əlaqəli Mövzular

- [Integration Testing (Junior)](03-integration-testing.md)
- [Feature Testing (Junior)](04-feature-testing.md)
- [Mocking (Middle)](07-mocking.md)
- [Testing Authentication & Authorization (Middle)](18-testing-authentication.md)
- [Contract Testing (Senior)](24-contract-testing.md)
- [GraphQL Testing (Senior)](36-graphql-testing.md)
