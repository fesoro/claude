# Testing Authentication & Authorization (Middle)
## İcmal

Authentication (kimsən?) və authorization (nə edə bilərsən?) testing, application-ın
giriş, qeydiyyat, şifrə sıfırlama, role və permission mexanizmlərinin düzgün işlədiyini
yoxlayır. Bu, security-kritik komponentdir — xəta burada bütün sistemi yarardı.

Laravel təqdim etdiyi helper-lər: `actingAs($user)`, `assertAuthenticated()`,
`assertGuest()`, `assertAuthenticatedAs()`, `$this->be($user)`, və middleware-lər üçün
route assertion-lar.

### Niyə Auth Testing Kritik?

1. **Security boundary** - Unauthorized user admin panel-ə daxil ola bilməz
2. **Compliance** - GDPR, HIPAA password policy-lər
3. **Session management** - Logout düzgün session-u silir?
4. **Multi-tenant** - User A, User B-nin məlumatını görə bilməz
5. **Token lifecycle** - Expire, revoke, refresh

## Niyə Vacibdir

- **Güvenlik qarantisi**: Authentication/authorization test edilməzsə, xəta production-da müştəri məlumatlarının sızmasına səbəb olur
- **Regression prevention**: Role dəyişikliyi, yeni middleware əlavəsi köhnə endpoint-lərin icazə siyasətini poza bilər
- **Compliance**: GDPR, PCI-DSS kimi standartlar məlumat qorunmasını tələb edir — testlər audit sübutu verir
- **Policy-based access**: RBAC/ABAC siyasətlərinin mürəkkəb kombinasiyaları yalnız automated test ilə tam əhatə oluna bilər

## Əsas Anlayışlar

### Auth Helper-lər

```php
// Authentication
$this->actingAs($user);                     // session guard
$this->actingAs($user, 'api');              // specific guard
$this->be($user);                            // alternative syntax

// Assertions
$this->assertAuthenticated();
$this->assertAuthenticatedAs($user);
$this->assertGuest();
$this->assertGuest('api');
```

### Auth Flow Tests

```
Registration → Email verification → Login → 2FA → Authenticated session
                                       ↓
                                   Logout → Session destroyed
                                       ↓
                                   Password reset → New password
```

### Authorization Layers

| Layer | Laravel Tool |
|-------|--------------|
| Route middleware | `auth`, `can`, `role` |
| Controller authorization | `$this->authorize('update', $post)` |
| Policy | `class PostPolicy` |
| Gate | `Gate::define('edit-settings', ...)` |
| Blade | `@can('update', $post)` |

## Praktik Baxış

### Best Practices

- **Policy-ləri ayrı unit test edin** - Feature test yalnız integration yoxlasın
- **Negative path-ları prioritet verin** - "İcazəsiz user giriş edə bilməz" daha kritikdir
- **Cross-user access test** - User A, User B-nin resource-una girməməlidir
- **Rate limiting test** - Brute force protection həqiqətən işləyir?
- **Token revocation test** - Logout sonra token işləməməlidir
- **Password complexity rules** - Bütün qaydalar ayrıca test olunsun

### Anti-Patterns

- **Yalnız happy path** - "Admin girir" var, "non-admin girmir" yoxdur
- **Password plain-text müqayisəsi** - `Hash::check` lazımdır
- **Global `actingAs($admin)`** - Hər test-in öz auth state-i olmalıdır
- **Hardcoded credentials** - `'password123'` test-də mövcuddursa production-da da riskdir
- **Middleware test-sizliyi** - Route middleware-i olmasa belə yaxşı policy işləmir
- **Session data yoxlamamaq** - Logout sonra session tam təmizlənir?

## Nümunələr

### Login Test

```php
public function test_user_can_login_with_correct_credentials(): void
{
    $user = User::factory()->create([
        'email'    => 'user@example.com',
        'password' => Hash::make('secret123'),
    ]);

    $this->postJson('/api/login', [
        'email'    => 'user@example.com',
        'password' => 'secret123',
    ])->assertOk()
      ->assertJsonStructure(['token']);

    $this->assertAuthenticatedAs($user);
}
```

### Authorization Test

```php
public function test_user_cannot_delete_others_post(): void
{
    $owner   = User::factory()->create();
    $intruder = User::factory()->create();
    $post    = Post::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->deleteJson("/api/posts/{$post->id}")
        ->assertForbidden();
}
```

## Praktik Tapşırıqlar

### 1. Registration Tests

```php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Orkhan',
            'email'                 => 'orkhan@example.com',
            'password'              => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertCreated()->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'orkhan@example.com',
            'name'  => 'Orkhan',
        ]);

        $user = User::where('email', 'orkhan@example.com')->first();
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'exist@example.com']);

        $this->postJson('/api/register', [
            'name'                  => 'X',
            'email'                 => 'exist@example.com',
            'password'              => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors('email');
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'X',
            'email'                 => 'x@example.com',
            'password'              => 'StrongPass123!',
            'password_confirmation' => 'different',
        ])->assertJsonValidationErrors('password');
    }

    public function test_password_must_meet_complexity(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'X',
            'email'                 => 'x@example.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ])->assertJsonValidationErrors('password');
    }
}
```

### 2. Login / Logout

```php
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_logs_in_with_correct_password(): void
    {
        $user = User::factory()->create([
            'email'    => 'u@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'u@example.com',
            'password' => 'secret',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'u@example.com',
            'password' => Hash::make('secret'),
        ]);

        $this->postJson('/api/login', [
            'email'    => 'u@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized();

        $this->assertGuest();
    }

    public function test_login_locks_after_five_failures(): void
    {
        User::factory()->create(['email' => 'u@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => 'u@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/login', [
            'email'    => 'u@example.com',
            'password' => 'wrong',
        ])->assertStatus(429); // Too Many Requests
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest();
    }
}
```

### 3. Password Reset

```php
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_is_sent(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'u@example.com']);

        $this->postJson('/api/password/email', ['email' => 'u@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user  = User::factory()->create();
        $token = Password::createToken($user);

        $this->postJson('/api/password/reset', [
            'email'                 => $user->email,
            'token'                 => $token,
            'password'              => 'NewStrongPass1!',
            'password_confirmation' => 'NewStrongPass1!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewStrongPass1!', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/password/reset', [
            'email'                 => $user->email,
            'token'                 => 'invalid-token',
            'password'              => 'NewStrongPass1!',
            'password_confirmation' => 'NewStrongPass1!',
        ])->assertStatus(422);
    }
}
```

### 4. Email Verification

```php
public function test_unverified_user_cannot_access_protected_routes(): void
{
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/api/dashboard')
        ->assertForbidden();
}

public function test_verification_link_marks_email_as_verified(): void
{
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($url)->assertRedirect();

    $this->assertNotNull($user->fresh()->email_verified_at);
}
```

### 5. Role & Permission (Spatie Permission)

```php
// Setup
public function setUp(): void
{
    parent::setUp();

    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'editor']);
    Permission::firstOrCreate(['name' => 'posts.delete']);
}

public function test_admin_can_delete_any_post(): void
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $post = Post::factory()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/posts/{$post->id}")
        ->assertNoContent();
}

public function test_editor_cannot_delete_post(): void
{
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $post = Post::factory()->create();

    $this->actingAs($editor)
        ->deleteJson("/api/posts/{$post->id}")
        ->assertForbidden();
}

public function test_user_with_direct_permission_can_delete(): void
{
    $user = User::factory()->create();
    $user->givePermissionTo('posts.delete');

    $this->actingAs($user)
        ->deleteJson("/api/posts/" . Post::factory()->create()->id)
        ->assertNoContent();
}
```

### 6. Policy Test

```php
// app/Policies/PostPolicy.php
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->hasRole('admin');
    }
}

// tests/Unit/Policies/PostPolicyTest.php
class PostPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PostPolicy $policy;

    public function setUp(): void
    {
        parent::setUp();
        $this->policy = new PostPolicy;
    }

    public function test_owner_can_update(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->assertTrue($this->policy->update($user, $post));
    }

    public function test_non_owner_cannot_update(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(); // different owner

        $this->assertFalse($this->policy->update($user, $post));
    }

    public function test_admin_can_update_any_post(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($this->policy->update($admin, Post::factory()->create()));
    }
}
```

### 7. API Token (Sanctum) Test

```php
public function test_api_request_requires_valid_token(): void
{
    $this->getJson('/api/user')->assertUnauthorized();
}

public function test_api_request_with_valid_token_succeeds(): void
{
    $user  = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user')
        ->assertOk()
        ->assertJson(['email' => $user->email]);
}

public function test_revoked_token_cannot_be_used(): void
{
    $user  = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    $user->tokens()->delete();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/user')
        ->assertUnauthorized();
}

public function test_token_with_ability_check(): void
{
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['posts:read']);

    $this->getJson('/api/posts')->assertOk();
    $this->postJson('/api/posts', [])->assertForbidden(); // no posts:write
}
```

### 8. Middleware Test

```php
public function test_admin_middleware_blocks_non_admins(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertForbidden();
}

public function test_verified_middleware_redirects_unverified(): void
{
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect('/verify-email');
}
```

## Ətraflı Qeydlər

**Q1: `actingAs` və real login (via POST) fərqi?**
A: `actingAs` bypass edir; yalnız session-a user qoyur. Real login flow-u test etmək
üçün `POST /login` istifadə olunur.

**Q2: `assertAuthenticated` və `assertAuthenticatedAs` fərqi?**
A: İlki — "kimsə authenticated-dir". İkincisi — "konkret `$user` authenticated-dir".

**Q3: Guest user üçün assertion?**
A: `$this->assertGuest()` - default guard üçün, `$this->assertGuest('api')` - specific.

**Q4: Policy test-i feature test-dən niyə ayrı yazmalıyıq?**
A: Policy pure business logic-dir — HTTP layer olmadan sürətli test olunur. Feature test
inteqrasiya səviyyəsini yoxlayır (controller + middleware + policy birgə).

**Q5: Rate limiting / brute force protection-u necə test edirik?**
A: Loop ilə bir neçə dəfə login cəhdi, sonra 429 assert edirik. Laravel `ThrottleRequests`
middleware-i istifadə edir.

**Q6: Password hash-i test-də necə yoxlayırıq?**
A: `Hash::check('plain', $user->password)`. Heç vaxt `$user->password === 'plain'`.

**Q7: Sanctum vs Passport test fərqi?**
A: Sanctum: `Sanctum::actingAs($user, $abilities)`. Passport: `Passport::actingAs($user, $scopes)`.
İkisi də real token create etməyi bypass edir.

**Q8: Multi-tenant auth-u necə test edirik?**
A: User A-nın tenant-ında, User B-nin məlumatına GET edir → 403/404 gəlməlidir.
Hər resource üçün bu "cross-tenant" test olmalıdır.

**Q9: Email verification middleware-in test-i?**
A: Unverified user yarat → protected route-a get → redirect / 403 assert et. Signed URL
ilə verify et → verified_at null deyil.

**Q10: 2FA (two-factor authentication) testing?**
A: Login → 2FA code step → kod daxil et → final session. Test-də `google2fa` library
ilə valid code generate olunur.

## Əlaqəli Mövzular

- [Feature Testing (Junior)](04-feature-testing.md)
- [API Testing (Middle)](09-api-testing.md)
- [Mocking (Middle)](07-mocking.md)
- [Security Testing (Senior)](21-security-testing.md)
- [Testing Anti-Patterns (Senior)](27-testing-anti-patterns.md)
