# Security Testing

## Nədir? (What is it?)

Security testing, proqram təminatının təhlükəsizlik zəifliklərini tapmaq və aradan qaldırmaq
üçün aparılan test prosesidir. Məqsəd sistemin icazəsiz giriş, data sızması, injection
hücumları və digər təhlükəsizlik risklərinə qarşı müdafiə olunduğunu yoxlamaqdır.

Security testing funksional testlərdən fərqlidir - "nə edir?" deyil, "nə etməməlidir?"
sualına cavab verir. Hacker düşüncəsi ilə tətbiqin zəif nöqtələri axtarılır. Laravel
built-in security feature-ləri güclüdür, amma düzgün konfiqurasiya və test tələb edir.

### Niyə Security Testing Vacibdir?

1. **Data qorunması** - İstifadəçi məlumatlarının sızmasının qarşısını alır
2. **Hüquqi tələblər** - GDPR, PCI-DSS kimi standartlara uyğunluq
3. **Reputasiya** - Təhlükəsizlik pozuntusu şirkətin nüfuzunu məhv edir
4. **Maliyyə itkisi** - Data breach-in orta dəyəri milyonlarla dollardır
5. **Müştəri güvəni** - İstifadəçilər təhlükəsiz platforma istəyir

## Əsas Konseptlər (Key Concepts)

### OWASP Top 10 (2021)

```
1. Broken Access Control
   → İstifadəçi icazəsi olmayan resurslara çata bilir

2. Cryptographic Failures
   → Şifrələmə zəiflikləri, plain text password

3. Injection (SQL, XSS, Command)
   → Zərərli input-un kod kimi icra edilməsi

4. Insecure Design
   → Arxitektura səviyyəsində təhlükəsizlik boşluqları

5. Security Misconfiguration
   → Default parollar, açıq debug mode, lazımsız portlar

6. Vulnerable Components
   → Köhnəlmiş və ya zəif third-party kitabxanalar

7. Authentication Failures
   → Zəif parol policy, brute force imkanı

8. Software Integrity Failures
   → CI/CD pipeline-da integrity yoxlamasının olmaması

9. Logging Failures
   → Təhlükəsizlik hadisələrinin qeydə alınmaması

10. SSRF (Server-Side Request Forgery)
    → Serverin daxili resurslara icazəsiz request göndərməsi
```

### Hücum Növləri və Müdafiə

| Hücum | Təsvir | Müdafiə |
|-------|--------|---------|
| SQL Injection | SQL query-yə zərərli kod daxil etmək | Parameterized queries, ORM |
| XSS | Səhifəyə JavaScript inject etmək | Output escaping, CSP |
| CSRF | İstifadəçi adından icazəsiz request | CSRF token |
| IDOR | Başqa user-in data-sına birbaşa giriş | Authorization checks |
| Brute Force | Parolu təxmin etmə cəhdləri | Rate limiting, account lockout |
| Path Traversal | Fayl sistemində icazəsiz gəzmə | Input validation, chroot |

## Praktiki Nümunələr (Practical Examples)

### SQL Injection Testing

```php
<?php

namespace Tests\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function search_is_safe_from_sql_injection(): void
    {
        User::factory()->create(['name' => 'Normal User']);

        $maliciousInputs = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "1' UNION SELECT * FROM users --",
            "admin'--",
            "1; DELETE FROM users",
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->getJson("/api/users?search=" . urlencode($input));

            $response->assertStatus(200);

            // Bütün users qaytarılmamalıdır
            $this->assertNotEquals(
                User::count(),
                count($response->json('data')),
                "SQL Injection vulnerability detected with input: {$input}"
            );
        }
    }

    /** @test */
    public function login_is_safe_from_sql_injection(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => "admin@example.com' OR '1'='1",
            'password' => "' OR '1'='1",
        ]);

        $response->assertStatus(422);
    }
}
```

### XSS Testing

```php
<?php

namespace Tests\Security;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XssTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function post_title_is_escaped_in_response(): void
    {
        $user = User::factory()->create();
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '"><script>alert(document.cookie)</script>',
            "javascript:alert('XSS')",
            '<svg onload=alert("XSS")>',
        ];

        foreach ($xssPayloads as $payload) {
            $post = Post::factory()->create([
                'user_id' => $user->id,
                'title' => $payload,
            ]);

            $response = $this->actingAs($user)->get("/posts/{$post->id}");

            // Raw script tag response-da olmamalıdır
            $response->assertDontSee($payload, false);

            // Escaped versiyası olmalıdır
            $content = $response->getContent();
            $this->assertStringNotContainsString(
                '<script>',
                strip_tags($content, '<script>'),
                "XSS vulnerability found with payload: {$payload}"
            );
        }
    }

    /** @test */
    public function api_response_does_not_contain_unescaped_html(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => '<script>alert("hack")</script>',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/posts/{$post->id}");

        $json = $response->json();
        $this->assertStringNotContainsString('<script>', $json['data']['title']);
    }
}
```

### CSRF Testing

```php
<?php

namespace Tests\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function post_request_without_csrf_token_is_rejected(): void
    {
        $user = User::factory()->create();

        // CSRF middleware aktiv olduqda, token olmadan POST reject olunmalıdır
        $response = $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Test Post',
                'body' => 'Content',
            ]);

        // Laravel test environment-da CSRF avtomatik keçir,
        // amma biz middleware-i manual test edə bilərik
        $response = $this->call('POST', '/posts', [
            'title' => 'Test Post',
            'body' => 'Content',
        ], [], [], [
            'HTTP_X_REQUESTED_WITH' => '', // CSRF token yoxdur
        ]);

        $this->assertContains($response->getStatusCode(), [419, 302]);
    }

    /** @test */
    public function api_routes_do_not_require_csrf_token(): void
    {
        $user = User::factory()->create();

        // API routes CSRF-dən azaddır (token-based auth istifadə edir)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'title' => 'API Post',
                'body' => 'Content',
            ]);

        $response->assertStatus(201);
    }
}
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Authorization (IDOR) Testing

```php
<?php

namespace Tests\Security;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_cannot_view_another_users_profile_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$otherUser->id}/settings");

        $response->assertStatus(403);
    }

    /** @test */
    public function user_cannot_delete_another_users_post(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    /** @test */
    public function admin_can_delete_any_post(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(204);
    }

    /** @test */
    public function sequential_id_enumeration_is_prevented(): void
    {
        $user = User::factory()->create();

        // ID-ləri ardıcıl sınamaqdla başqa userlərin data-sına çatmaq cəhdi
        for ($id = 1; $id <= 20; $id++) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson("/api/orders/{$id}");

            $this->assertContains($response->getStatusCode(), [403, 404],
                "Order #{$id} should not be accessible");
        }
    }
}
```

### Rate Limiting Testing

```php
<?php

namespace Tests\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_endpoint_has_rate_limiting(): void
    {
        // Laravel default: 5 attempts per minute
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6-cı cəhd rate limit-ə düşməlidir
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    /** @test */
    public function api_endpoints_have_rate_limiting(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/posts');
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts');

        $response->assertStatus(429);
    }
}
```

### Password və Authentication Security Testing

```php
<?php

namespace Tests\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function password_must_meet_complexity_requirements(): void
    {
        $weakPasswords = [
            '123456',           // Çox sadə
            'password',         // Ümumi parol
            'abc',              // Çox qısa
            'aaaaaaaaaa',       // Təkrar simvol
        ];

        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/register', [
                'name' => 'Test User',
                'email' => "test_{$password}@example.com",
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        }
    }

    /** @test */
    public function passwords_are_hashed_in_database(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->assertNotEquals('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    /** @test */
    public function password_is_not_exposed_in_api_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayNotHasKey('password', $json['data']);
        $this->assertArrayNotHasKey('remember_token', $json['data']);
    }

    /** @test */
    public function session_is_invalidated_after_password_change(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password123!',
                'password_confirmation' => 'new-password123!',
            ])
            ->assertStatus(200);

        // Köhnə session invalid olmalıdır
        // Yeni authentication tələb olunmalıdır
    }
}
```

### Security Headers Testing

```php
<?php

namespace Tests\Security;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /** @test */
    public function response_contains_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /** @test */
    public function strict_transport_security_is_set(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Strict-Transport-Security');
    }

    /** @test */
    public function sensitive_endpoints_have_no_cache_headers(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/api/profile');

        $response->assertHeader('Cache-Control', 'no-store, private');
    }
}
```

## Interview Sualları

### 1. OWASP Top 10 nədir?
**Cavab:** OWASP (Open Web Application Security Project) tərəfindən hazırlanan ən kritik 10 web təhlükəsizlik risklərinin siyahısıdır. 2021 versiyasında Broken Access Control birinci yerdədir. Bu siyahı security testing-də prioritetləri müəyyən etmək üçün standart kimi istifadə olunur.

### 2. SQL Injection nədir və Laravel-də necə qarşısı alınır?
**Cavab:** SQL Injection istifadəçi input-unun SQL query-də kod kimi icra edilməsidir. Laravel-də Eloquent ORM və Query Builder avtomatik parameterized query istifadə edir. `DB::raw()` istifadə edərkən isə manual binding lazımdır: `DB::raw('SELECT * FROM users WHERE name = ?', [$name])`.

### 3. XSS və CSRF arasındakı fərq nədir?
**Cavab:** XSS (Cross-Site Scripting): hücumçu səhifəyə JavaScript inject edir, digər istifadəçilərin browser-ində icra olunur. CSRF (Cross-Site Request Forgery): hücumçu istifadəçini bilmədən request göndərməyə məcbur edir. XSS-dən output escaping ilə, CSRF-dən token verification ilə qorunulur.

### 4. IDOR nədir və necə test edilir?
**Cavab:** IDOR (Insecure Direct Object Reference): istifadəçi URL-dəki ID-ni dəyişdirərək başqa user-in data-sına çatır. Test: User A ilə login edib User B-nin resource-larına request göndəririk, 403 gözləyirik. Laravel-də Policy və Gate ilə authorization check edilir.

### 5. Rate limiting niyə vacibdir?
**Cavab:** Brute force hücumlarının qarşısını alır, API abuse-u əngəlləyir, server resurslarını qoruyur. Laravel-də `throttle` middleware ilə konfiqurasiya olunur. Test: limit sayında request göndərib sonrakının 429 status qaytardığını yoxlayırıq.

### 6. Sensitive data exposure necə test edilir?
**Cavab:** API response-larda password, token, credit card kimi data-nın olmamasını yoxlayırıq. Error message-ların stack trace göstərmədiyini test edirik. Logs-da sensitive data olmadığını yoxlayırıq. Laravel-da `$hidden` model property və resource class-lar istifadə olunur.

### 7. Laravel-in built-in security feature-ləri nələrdir?
**Cavab:** CSRF protection (VerifyCsrfToken middleware), Blade templating (auto-escaping {{ }}), bcrypt/argon2 password hashing, SQL injection prevention (Eloquent ORM), mass assignment protection ($fillable/$guarded), rate limiting, encryption (APP_KEY ilə), signed URLs.

### 8. Penetration testing və vulnerability scanning arasındakı fərq nədir?
**Cavab:** Vulnerability scanning avtomatik tool ilə bilinen zəiflikləri tarayır (OWASP ZAP, Nessus). Penetration testing manual olaraq hacker perspektivindən sistemi hack etməyə çalışır. Scanning daha sürətli və geniş, pentest daha dərin və yaradıcıdır. İkisi də lazımdır.

## Best Practices / Anti-Patterns

### Best Practices

1. **OWASP Top 10-u əsas götürün** - Ən çox rast gəlinən zəifliklərdən başlayın
2. **Automated security scans CI/CD-yə əlavə edin** - Hər PR-da SAST/DAST
3. **Dependency audit edin** - `composer audit` ilə zəif package-ləri tapın
4. **Input validation hər yerdə** - Never trust user input
5. **Principle of Least Privilege** - Minimum lazımi icazə verin
6. **Security headers konfiqurasiya edin** - X-Frame-Options, CSP, HSTS

### Anti-Patterns

1. **Security-ni sona saxlamaq** - Başlanğıcdan düşünülməlidir
2. **Yalnız frontend validation** - Server-side validation mütləqdir
3. **Sensitive data log etmək** - Password, token log-lara yazılmamalıdır
4. **Debug mode production-da** - `APP_DEBUG=false` olmalıdır
5. **Hardcoded credentials** - Environment variable istifadə edin
6. **Köhnə dependency-lər** - Mütəmadi yeniləyin və audit edin
