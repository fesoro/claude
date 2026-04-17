# Authentication & Authorization

## Nədir? (What is it?)

**Authentication (AuthN)** - istifadəçinin kim olduğunu təsdiq etmək ("Sən kimsən?").
**Authorization (AuthZ)** - istifadəçinin nə edə biləcəyini müəyyən etmək ("Sən bunu edə bilərsən?").

Sadə dillə: authentication pasport yoxlamasıdır (kim olduğunu sübut et),
authorization isə giriş icazəsidir (bu otağa daxil ola bilərsən?).

```
┌───────────┐     ┌──────────────┐     ┌───────────────┐
│   User    │────▶│Authentication│────▶│ Authorization  │
│           │     │              │     │               │
│ Login     │     │ "Kim?"       │     │ "Nə edə       │
│ Credentials│    │ Verify ID    │     │  bilər?"       │
└───────────┘     └──────────────┘     └───────────────┘
```

## Əsas Konseptlər (Key Concepts)

### Session-Based Authentication

```
1. User login edir (username + password)
2. Server session yaradır, session ID cookie-yə yazılır
3. Hər request-də browser cookie göndərir
4. Server session ID ilə user-i tanıyır

┌────────┐  POST /login        ┌────────┐
│ Client │ ──────────────────▶ │ Server │
│        │  {email, password}  │        │
│        │                     │        │
│        │  Set-Cookie:        │ Session │
│        │ ◀────────────────── │ Store  │
│        │  session_id=abc123  │(Redis) │
│        │                     │        │
│        │  GET /profile       │        │
│        │  Cookie: abc123     │        │
│        │ ──────────────────▶ │ Lookup │
│        │                     │ session│
│        │  200 OK (user data) │        │
│        │ ◀────────────────── │        │
└────────┘                     └────────┘
```

### JWT (JSON Web Token)

```
JWT Structure:
  Header.Payload.Signature

Header:  {"alg": "HS256", "typ": "JWT"}
Payload: {"sub": "1", "name": "John", "role": "admin", "exp": 1700000000}
Signature: HMACSHA256(base64(header) + "." + base64(payload), secret)

Result: eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIiwibmFtZSI6IkpvaG4ifQ.signature

Stateless - server session saxlamır, token özündə info daşıyır
```

**JWT vs Session müqayisəsi:**

| Xüsusiyyət | Session | JWT |
|------------|---------|-----|
| Storage | Server-side (Redis) | Client-side (token) |
| Scalability | Session store lazım | Stateless, asan scale |
| Revocation | Asandır (session sil) | Çətindir (blocklist lazım) |
| Size | Kiçik cookie | Böyük token |
| Mobile | Cookie problem | Header-da rahat |

### OAuth 2.0 Flows

**Authorization Code Flow (web apps üçün ən təhlükəsiz):**
```
┌────────┐                              ┌──────────┐
│  User  │                              │  Auth    │
│Browser │                              │  Server  │
└───┬────┘                              └────┬─────┘
    │  1. Click "Login with Google"          │
    │──────────────────────────────────────▶│
    │                                        │
    │  2. Redirect to Google login page      │
    │◀──────────────────────────────────────│
    │                                        │
    │  3. User logs in, consents             │
    │──────────────────────────────────────▶│
    │                                        │
    │  4. Redirect back with auth code       │
    │◀──────────────────────────────────────│
    │                                        │
    │       ┌──────────┐                     │
    │       │  Your    │                     │
    │       │  Server  │                     │
    │       └────┬─────┘                     │
    │            │  5. Exchange code for token│
    │            │──────────────────────────▶│
    │            │                            │
    │            │  6. Access token + refresh │
    │            │◀──────────────────────────│
    │            │                            │
    │            │  7. Use token for API calls│
    │            │──────────────────────────▶│
```

**Client Credentials Flow (machine-to-machine):**
```
Service A → Auth Server: {client_id, client_secret}
Auth Server → Service A: {access_token}
Service A → Service B: Authorization: Bearer {access_token}
```

### SSO (Single Sign-On)

```
┌─────────────────────────────────────────┐
│          Identity Provider (IdP)         │
│         (Okta, Auth0, Keycloak)         │
└──────────────┬──────────────────────────┘
               │
     ┌─────────┼─────────┐
     │         │         │
┌────┴───┐ ┌──┴────┐ ┌──┴────┐
│ App A  │ │ App B │ │ App C │
│        │ │       │ │       │
│ Login  │ │ Auto  │ │ Auto  │
│ once   │ │ login │ │ login │
└────────┘ └───────┘ └───────┘
```

### RBAC vs ABAC

**RBAC (Role-Based Access Control):**
```
Roles:
  admin  → [create, read, update, delete] all resources
  editor → [create, read, update] articles
  viewer → [read] articles

User → Role → Permissions
```

**ABAC (Attribute-Based Access Control):**
```
Policy: "User can edit article IF user.department == article.department
         AND user.clearance >= article.sensitivity
         AND time.hour BETWEEN 9 AND 17"

More flexible, more complex
```

## Arxitektura (Architecture)

### Complete Auth System

```
┌──────────┐     ┌──────────────┐     ┌───────────┐
│  Client  │────▶│  API Gateway │────▶│  Auth     │
│          │     │              │     │  Service  │
│          │     │  - Validate  │     │           │
│          │     │    token     │     │  - Login  │
│          │     │  - Rate limit│     │  - Register│
└──────────┘     └──────┬───────┘     │  - Token  │
                        │             │  - OAuth  │
                        │             └─────┬─────┘
                 ┌──────┴──────┐            │
                 │  Protected  │      ┌─────┴─────┐
                 │  Services   │      │ User DB   │
                 │             │      │ + Redis   │
                 └─────────────┘      │ (sessions)│
                                      └───────────┘
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Laravel Sanctum (SPA + Mobile API)

```php
// Installation
// composer require laravel/sanctum
// php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

// app/Http/Controllers/AuthController.php
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth-token', ['*'])->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        // Revoke old tokens
        $user->tokens()->where('name', 'auth-token')->delete();

        $token = $user->createToken('auth-token', $this->getAbilities($user));

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    private function getAbilities(User $user): array
    {
        return match ($user->role) {
            'admin' => ['*'],
            'editor' => ['articles:create', 'articles:update', 'articles:read'],
            'viewer' => ['articles:read'],
            default => ['articles:read'],
        };
    }
}

// Token ability check
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/articles', [ArticleController::class, 'index'])
        ->middleware('ability:articles:read');
    Route::post('/articles', [ArticleController::class, 'store'])
        ->middleware('ability:articles:create');
});
```

### Laravel Passport (Full OAuth2 Server)

```php
// OAuth2 Authorization Code flow
// composer require laravel/passport
// php artisan passport:install

// routes/web.php - OAuth consent
Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize']);

// API routes protected by Passport
Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Scopes
Passport::tokensCan([
    'read-orders' => 'Read order information',
    'place-orders' => 'Place new orders',
    'manage-account' => 'Manage account settings',
]);

Route::get('/orders', [OrderController::class, 'index'])
    ->middleware('scope:read-orders');
```

### Gates & Policies (Authorization)

```php
// app/Providers/AuthServiceProvider.php
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Simple Gates
        Gate::define('manage-users', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('update-article', function (User $user, Article $article) {
            return $user->id === $article->author_id
                || $user->role === 'admin';
        });
    }
}

// app/Policies/ArticlePolicy.php
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Everyone can list
    }

    public function view(User $user, Article $article): bool
    {
        if ($article->published) return true;
        return $user->id === $article->author_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'editor']);
    }

    public function update(User $user, Article $article): bool
    {
        return $user->id === $article->author_id || $user->role === 'admin';
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->role === 'admin';
    }
}

// Controller usage
class ArticleController extends Controller
{
    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $article->update($request->validated());

        return response()->json(new ArticleResource($article));
    }
}

// Blade usage
// @can('update', $article)
//     <a href="{{ route('articles.edit', $article) }}">Edit</a>
// @endcan
```

### RBAC Implementation

```php
// Migrations
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('display_name');
    $table->timestamps();
});

Schema::create('permissions', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique(); // articles.create
    $table->string('display_name');
    $table->timestamps();
});

Schema::create('role_permission', function (Blueprint $table) {
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
    $table->primary(['role_id', 'permission_id']);
});

Schema::create('user_role', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->primary(['user_id', 'role_id']);
});

// User Model
class User extends Authenticatable
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }
}

// Middleware
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}

// Usage
Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])
    ->middleware('permission:articles.delete');
```

### JWT with Refresh Token

```php
class TokenService
{
    public function generateTokenPair(User $user): array
    {
        $accessToken = $this->createAccessToken($user);
        $refreshToken = $this->createRefreshToken($user);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => 900, // 15 minutes
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $stored = DB::table('refresh_tokens')
            ->where('token', hash('sha256', $refreshToken))
            ->where('expires_at', '>', now())
            ->where('revoked', false)
            ->first();

        if (!$stored) {
            throw new AuthenticationException('Invalid refresh token');
        }

        // Rotate refresh token
        DB::table('refresh_tokens')
            ->where('id', $stored->id)
            ->update(['revoked' => true]);

        $user = User::findOrFail($stored->user_id);
        return $this->generateTokenPair($user);
    }

    private function createAccessToken(User $user): string
    {
        return JWT::encode([
            'sub' => $user->id,
            'role' => $user->role,
            'exp' => time() + 900,
            'iat' => time(),
        ], config('app.jwt_secret'), 'HS256');
    }

    private function createRefreshToken(User $user): string
    {
        $token = Str::random(64);

        DB::table('refresh_tokens')->insert([
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);

        return $token;
    }
}
```

## Real-World Nümunələr

1. **Google** - OAuth2 provider, SSO across all Google services
2. **Auth0** - Identity-as-a-Service, social login, MFA
3. **Okta** - Enterprise SSO, SAML, SCIM provisioning
4. **GitHub** - OAuth2 for third-party apps, fine-grained PATs
5. **AWS IAM** - ABAC + RBAC hybrid, policy-based access control

## Interview Sualları

**S1: JWT-nin dezavantajları nədir?**
C: Token revocation çətindir (expire olana qədər valid qalır), token size böyük ola bilər,
sensitive data payload-da olmamalıdır (base64 decode olunur), refresh token strategiyası
lazımdır. Blocklist ilə revocation mümkündür amma stateless xüsusiyyətini itirir.

**S2: OAuth2 Authorization Code flow niyə PKCE ilə istifadə olunur?**
C: Public client-lər (SPA, mobile) client secret saxlaya bilmir. PKCE code_verifier
və code_challenge ilə authorization code-un oğurlanmasının qarşısını alır.
Man-in-the-middle attack-dan qoruyur.

**S3: Session vs JWT - nə vaxt hansını istifadə etmək lazımdır?**
C: Session - traditional web apps, server-rendered pages, easy revocation lazım olanda.
JWT - API-first, mobile apps, microservices arası auth, stateless scaling lazım olanda.
Hybrid yanaşma da mümkündür.

**S4: Refresh token niyə lazımdır?**
C: Access token qısa ömürlü olmalıdır (15 dəq) təhlükəsizlik üçün. Refresh token
uzun ömürlü (30 gün) olub yeni access token almağa imkan verir. User hər 15 dəqiqədə
login olmur. Refresh token rotation ilə təhlükəsizlik artırılır.

**S5: RBAC vs ABAC - fərq nədir?**
C: RBAC role-based (admin, editor, viewer), sadə, implement etmək asandır.
ABAC attribute-based (user, resource, environment attributes), daha flexible
amma daha complex. Enterprise sistemlər ABAC, sadə applar RBAC istifadə edir.

**S6: Password necə təhlükəsiz saxlanır?**
C: bcrypt/argon2 ilə hash, salt avtomatik əlavə olunur. Heç vaxt plain text,
MD5 və ya SHA istifadə etməyin. Laravel Hash::make() default olaraq bcrypt
istifadə edir. Password brute-force üçün rate limiting tətbiq edin.

## Best Practices

1. **HTTPS Always** - Token/session heç vaxt HTTP üzərindən göndərməyin
2. **Password Hashing** - bcrypt/argon2 istifadə edin, MD5/SHA yox
3. **Token Rotation** - Refresh token hər istifadədə yenilənsin
4. **MFA** - Həssas əməliyyatlar üçün multi-factor authentication
5. **Rate Limiting** - Login endpoint-ə brute-force qoruması
6. **CORS** - Düzgün origin-ləri icazə verin
7. **CSRF Protection** - Session-based auth üçün CSRF token
8. **Secure Cookies** - HttpOnly, Secure, SameSite flags
9. **Least Privilege** - Minimum lazımi icazələri verin
10. **Audit Logging** - Auth event-lərini log edin
