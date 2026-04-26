# Feature Testing (Junior)
## İcmal

Feature testing (və ya end-to-end testing) proqramın bütün request lifecycle-ını
istifadəçi perspektivindən test edir. HTTP request göndərilir, middleware-lər,
controller-lər, servislər, database hamısı real olaraq işləyir və response yoxlanır.

Laravel-də feature testlər `tests/Feature/` qovluğunda yerləşir və `Tests\TestCase`
sinifindən extend edir (PHPUnit\Framework\TestCase deyil). Bu, bütün Laravel
application-ın boot olmasını təmin edir.

Feature test bir istifadəçinin brauzerdə etdiyi əməliyyatı simulyasiya edir:
sayfaya gir, form doldur, submit et, nəticəni gör.

## Niyə Vacibdir

- **Tam stack yoxlama**: Routing, middleware, controller, service, database — hamısı bir arada sınaqdan keçirilir; ayrı-ayrılıqda doğru olan komponentlər birlikdə düzgün işləməyə bilər
- **Authentication və authorization**: Real middleware zənciri işlədiyindən, 401/403 ssenariləri düzgün sınaqdan keçirilir — unit testdə bu mümkün deyil
- **Form validation end-to-end**: Validation qaydalarının request-dən response-a qədər düzgün işlədiyini yoxlayır, session error-larını da əhatə edir
- **Regression prevention**: CRUD əməliyyatları üçün yazılan feature testlər refactoring zamanı bütün endpoint-lərin hələ də düzgün işlədiyini zəmanət edir
- **API contract**: Frontend komandası üçün API-nin davranışını sənədləşdirir — yeni developer actingAs, assertJson pattern-lərindən öyrənir

## Əsas Anlayışlar

### Feature Test vs Unit Test vs Integration Test

| Xüsusiyyət | Unit | Integration | Feature |
|-------------|------|-------------|---------|
| Scope | Bir metod | Bir neçə sinif | Bütün request |
| Sürət | Çox sürətli | Orta | Yavaş |
| Database | Yox | Bəli | Bəli |
| HTTP | Yox | Bəzən | Bəli |
| Middleware | Yox | Yox | Bəli |
| Reliability | Yüksək | Orta | Aşağı |

### HTTP Test Metodları

```php
$this->get('/url');
$this->post('/url', $data);
$this->put('/url', $data);
$this->patch('/url', $data);
$this->delete('/url');
$this->options('/url');

// JSON versiyaları
$this->getJson('/api/url');
$this->postJson('/api/url', $data);
$this->putJson('/api/url', $data);
$this->patchJson('/api/url', $data);
$this->deleteJson('/api/url', $data);
```

### Response Assertions

```php
$response->assertStatus(200);
$response->assertOk();             // 200
$response->assertCreated();        // 201
$response->assertNoContent();      // 204
$response->assertNotFound();       // 404
$response->assertForbidden();      // 403
$response->assertUnauthorized();   // 401
$response->assertUnprocessable();  // 422

$response->assertRedirect('/login');
$response->assertRedirectToRoute('login');

$response->assertSee('Welcome');
$response->assertDontSee('Error');
$response->assertSeeText('Welcome'); // HTML tag-lar daxil deyil

$response->assertViewIs('home');
$response->assertViewHas('users');
$response->assertViewHas('user', $expectedUser);

$response->assertSessionHas('message', 'Success');
$response->assertSessionHasErrors(['email']);
$response->assertSessionDoesntHaveErrors();

$response->assertCookie('token');
$response->assertCookieExpired('token');
```

## Praktik Baxış

### Best Practices
- Hər critical user flow üçün feature test yazın
- Factory-lər istifadə edin, manual data yaratmayın
- actingAs() ilə authentication-ı sadələşdirin
- Həm happy path, həm error case test edin
- assertDatabaseHas/Missing ilə side effect-ləri yoxlayın
- JSON API testlərində assertJsonStructure istifadə edin

### Anti-Patterns
- **Testing framework**: Laravel-in öz funksionallığını test etmək
- **Too many assertions**: Bir testdə 20 assert - testləri bölün
- **No edge cases**: Yalnız happy path test etmək
- **Ignoring validation**: Form validation testlərini yazmamaq
- **Hard-coded IDs**: `User::find(1)` əvəzinə factory istifadə edin
- **Testing views in detail**: View-un HTML strukturunu çox detallı test etmək

## Nümunələr

### User Registration Feature Test

```php
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_displayed(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
        $response->assertSee('Create Account');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
```

### CRUD Feature Tests

```php
class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_displays_all_posts(): void
    {
        $posts = Post::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get('/posts');

        $response->assertOk();
        foreach ($posts as $post) {
            $response->assertSee($post->title);
        }
    }

    public function test_create_form_is_displayed(): void
    {
        $response = $this->actingAs($this->user)->get('/posts/create');

        $response->assertOk();
        $response->assertViewIs('posts.create');
    }

    public function test_store_creates_new_post(): void
    {
        $response = $this->actingAs($this->user)->post('/posts', [
            'title' => 'My New Post',
            'body' => 'This is the body of my post.',
            'published' => true,
        ]);

        $response->assertRedirect('/posts');
        $response->assertSessionHas('success', 'Post created successfully.');

        $this->assertDatabaseHas('posts', [
            'title' => 'My New Post',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_show_displays_post(): void
    {
        $post = Post::factory()->create(['title' => 'Test Post']);

        $response = $this->actingAs($this->user)->get("/posts/{$post->id}");

        $response->assertOk();
        $response->assertSee('Test Post');
    }

    public function test_update_modifies_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->put("/posts/{$post->id}", [
            'title' => 'Updated Title',
            'body' => 'Updated body.',
        ]);

        $response->assertRedirect("/posts/{$post->id}");
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_destroy_deletes_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->delete("/posts/{$post->id}");

        $response->assertRedirect('/posts');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_user_cannot_update_others_post(): void
    {
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->put("/posts/{$post->id}", [
            'title' => 'Hacked Title',
            'body' => 'Hacked body.',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_create_posts(): void
    {
        $response = $this->post('/posts', [
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        $response->assertRedirect('/login');
    }
}
```

## Praktik Tapşırıqlar

### JSON API Feature Tests

```php
class ApiProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_products_with_filtering(): void
    {
        Product::factory()->create(['category' => 'electronics', 'price' => 500]);
        Product::factory()->create(['category' => 'electronics', 'price' => 100]);
        Product::factory()->create(['category' => 'books', 'price' => 20]);

        $response = $this->getJson('/api/products?category=electronics&min_price=200');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['price' => 500]);
    }

    public function test_create_product_returns_created_resource(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'name' => 'New Product',
                'price' => 29.99,
                'category' => 'electronics',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'price', 'category', 'created_at'],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'New Product',
                    'price' => 29.99,
                ],
            ]);
    }

    public function test_validation_errors_return_422(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/products', [
                'name' => '', // Required
                'price' => -10, // Must be positive
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);
    }
}
```

### File Upload Feature Test

```php
class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertRedirect('/profile');

        // File mövcuddur
        Storage::disk('public')->assertExists("avatars/{$file->hashName()}");

        // Database yenilənib
        $this->assertNotNull($user->fresh()->avatar_path);
    }

    public function test_avatar_must_be_an_image(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 1024);

        $response = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors(['avatar']);
    }
}
```

### Middleware Testing

```php
class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }
}
```

### Testing with Headers and Cookies

```php
class LocaleTest extends TestCase
{
    public function test_api_respects_accept_language_header(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'az',
        ])->getJson('/api/greeting');

        $response->assertJson(['message' => 'Salam']);
    }

    public function test_remember_me_sets_cookie(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertCookie('remember_web_' . sha1(get_class($this->app['auth']->guard())));
    }
}
```

### Testing Pagination

```php
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_paginated(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->getJson('/api/products?page=2&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 30);
    }
}
```

## Ətraflı Qeydlər

**S: Feature test nə vaxt yazmalıyıq?**
C: Kritik user flow-lar üçün: qeydiyyat, login, ödəniş, sifariş vermə. Feature
testlər bütün stack-in birlikdə işlədiyini təmin edir. Hər endpoint üçün ən azı
happy path və əsas error case-ləri əhatə etməlidir.

**S: actingAs() metodu nə edir?**
C: Test zamanı istifadəçini authenticate edir. Real login prosesindən keçmir,
session-a birbaşa user-i yazır. Bu, authentication-dan asılı olmayan testləri
sürətləndirir.

**S: assertJson() ilə assertExactJson() fərqi nədir?**
C: assertJson() response-da verilən JSON fraqmentinin olduğunu yoxlayır (partial match).
assertExactJson() isə response-un dəqiq verilən JSON ilə eyni olduğunu yoxlayır.

**S: Feature testlər niyə yavaşdır?**
C: Bütün application boot olur, middleware-lər işləyir, database əməliyyatları real
olur, routing resolve edilir. Bu, real istifadəçi təcrübəsinə yaxın amma yavaşdır.

**S: withoutMiddleware() nə vaxt istifadə etməlisiniz?**
C: Middleware-dən asılı olmayan funksionallığı test edərkən. Məsələn, CSRF
middleware-ni disable etmək API testlərində lazım ola bilər. Amma ehtiyatlı olun -
middleware-lər özləri də test edilməlidir.

## Əlaqəli Mövzular

- [Testing Fundamentals (Junior)](01-testing-fundamentals.md)
- [Integration Testing (Junior)](03-integration-testing.md)
- [API Testing (Middle)](09-api-testing.md)
- [Testing Authentication (Middle)](18-testing-authentication.md)
- [Test Patterns (Senior)](26-test-patterns.md)
- [Testing Best Practices (Senior)](30-testing-best-practices.md)
