# Real Use Cases — Authentication, RBAC, Audit

## 1. Role-Based Access Control (RBAC) sistemi

```php
// Database schema:
// roles: id, name, slug, description
// permissions: id, name, slug, description
// role_permission: role_id, permission_id
// user_role: user_id, role_id

// Models
class Role extends Model {
    public function permissions(): BelongsToMany {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): BelongsToMany {
        return $this->belongsToMany(User::class, 'user_role');
    }
}

trait HasRolesAndPermissions {
    public function roles(): BelongsToMany {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function hasRole(string ...$roles): bool {
        return $this->roles->whereIn('slug', $roles)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool {
        return $this->roles
            ->flatMap->permissions
            ->pluck('slug')
            ->contains($permission);
    }

    public function hasAnyPermission(string ...$permissions): bool {
        $userPermissions = $this->roles->flatMap->permissions->pluck('slug');
        return collect($permissions)->intersect($userPermissions)->isNotEmpty();
    }

    // Super admin bütün permission-lara sahibdir
    public function isSuperAdmin(): bool {
        return $this->hasRole('super-admin');
    }
}

class User extends Authenticatable {
    use HasRolesAndPermissions;
}

// Middleware
class CheckPermission {
    public function handle(Request $request, Closure $next, string ...$permissions): Response {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->hasAnyPermission(...$permissions)) {
            abort(403, 'Bu əməliyyat üçün icazəniz yoxdur.');
        }

        return $next($request);
    }
}

// Route-da istifadə
Route::middleware('permission:orders.view,orders.manage')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

Route::middleware('permission:orders.create')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});

// Gate registration (AuthServiceProvider)
Gate::before(function (User $user) {
    if ($user->isSuperAdmin()) {
        return true;
    }
});

Gate::define('manage-users', fn (User $user) => $user->hasPermission('users.manage'));

// Blade-da
@role('admin')
    <a href="/admin">Admin Panel</a>
@endrole

@can('manage-users')
    <a href="/admin/users">İstifadəçilər</a>
@endcan
```

---

## 2. JWT / OAuth2 Token avtentifikasiyası — daxili işləyişi

```php
// JWT token strukturu:
// Header.Payload.Signature
// eyJhbGc...eyJzdWI...SflKxwR...

// Header: {"alg": "HS256", "typ": "JWT"}
// Payload: {"sub": 1, "email": "user@test.com", "exp": 1680000000, "iat": 1679990000}
// Signature: HMACSHA256(base64(header) + "." + base64(payload), secret)

// Manual JWT implementation (əsas anlayış üçün)
class JwtService {
    public function __construct(private string $secret) {}

    public function generate(User $user, int $ttlMinutes = 60): string {
        $header = $this->base64Encode(json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]));

        $payload = $this->base64Encode(json_encode([
            'sub' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('slug'),
            'iat' => time(),
            'exp' => time() + ($ttlMinutes * 60),
            'jti' => Str::uuid()->toString(), // Unique token ID
        ]));

        $signature = $this->base64Encode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        return "$header.$payload.$signature";
    }

    public function validate(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $payload, $signature] = $parts;

        // Signature yoxla
        $expectedSignature = $this->base64Encode(
            hash_hmac('sha256', "$header.$payload", $this->secret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return null; // İmza uyğun gəlmir
        }

        $data = json_decode($this->base64Decode($payload), true);

        // Expiration yoxla
        if (isset($data['exp']) && $data['exp'] < time()) {
            return null; // Token müddəti bitib
        }

        return $data;
    }

    private function base64Encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64Decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

// Access Token + Refresh Token pattern
class AuthController extends Controller {
    public function login(LoginRequest $request): JsonResponse {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email və ya şifrə yanlışdır.'],
            ]);
        }

        $accessToken = $user->createToken('access', ['*'], now()->addMinutes(15));
        $refreshToken = $user->createToken('refresh', ['token:refresh'], now()->addDays(30));

        return response()->json([
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'expires_in' => 900, // 15 dəqiqə
            'token_type' => 'Bearer',
        ]);
    }

    public function refresh(Request $request): JsonResponse {
        $user = $request->user();

        if (!$user->currentAccessToken()->can('token:refresh')) {
            abort(403, 'Bu token ilə refresh etmək mümkün deyil.');
        }

        // Köhnə token-ləri sil
        $user->currentAccessToken()->delete();

        $accessToken = $user->createToken('access', ['*'], now()->addMinutes(15));
        $refreshToken = $user->createToken('refresh', ['token:refresh'], now()->addDays(30));

        return response()->json([
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'expires_in' => 900,
        ]);
    }

    public function logout(Request $request): JsonResponse {
        // Cari cihazın token-ini sil
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Uğurla çıxış edildi.']);
    }

    public function logoutAll(Request $request): JsonResponse {
        // Bütün cihazlardan çıxış
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Bütün cihazlardan çıxış edildi.']);
    }
}
```

---

## 3. Email Verification və Password Reset — tam flow

```php
class EmailVerificationService {
    public function sendVerificationEmail(User $user): void {
        $token = Str::random(64);
        $expiry = now()->addHours(24);

        Cache::put("email_verify:{$token}", $user->id, $expiry);

        $url = url("/verify-email?token={$token}");

        $user->notify(new VerifyEmailNotification($url));
    }

    public function verify(string $token): User {
        $userId = Cache::pull("email_verify:{$token}"); // get and delete

        if (!$userId) {
            throw new InvalidTokenException('Doğrulama linki keçərsizdir və ya müddəti bitib.');
        }

        $user = User::findOrFail($userId);
        $user->update([
            'email_verified_at' => now(),
        ]);

        EmailVerified::dispatch($user);

        return $user;
    }
}

class PasswordResetService {
    public function sendResetLink(string $email): void {
        $user = User::where('email', $email)->first();

        // User tapılmasa da eyni cavab ver (email enumeration-a qarşı)
        if (!$user) return;

        // Rate limit — eyni email üçün 5 dəqiqədə 1 dəfə
        $key = "password_reset:{$email}";
        if (Cache::has($key)) {
            return;
        }

        $token = Str::random(64);
        Cache::put($key, $token, now()->addMinutes(5));
        Cache::put("password_reset_token:{$token}", $user->id, now()->addHour());

        $user->notify(new ResetPasswordNotification(
            url("/reset-password?token={$token}&email={$email}")
        ));
    }

    public function reset(string $token, string $email, string $newPassword): void {
        $userId = Cache::get("password_reset_token:{$token}");
        $user = User::where('id', $userId)->where('email', $email)->firstOrFail();

        // Əvvəlki şifrə ilə eyni olmamalı
        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Yeni şifrə köhnə şifrədən fərqli olmalıdır.'],
            ]);
        }

        DB::transaction(function () use ($user, $newPassword, $token) {
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // Bütün active session-ları sil (digər cihazlardan çıxış)
            $user->tokens()->delete();

            // Token-i sil
            Cache::forget("password_reset_token:{$token}");
        });

        PasswordReset::dispatch($user);
    }
}
```

---

## 4. Audit Log / Activity Tracking sistemi

```php
// activity_logs table:
// id, user_id, action, model_type, model_id, old_values, new_values,
// ip_address, user_agent, metadata, created_at

trait Auditable {
    protected static function bootAuditable(): void {
        static::created(function (Model $model) {
            AuditLog::record('created', $model, null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $oldValues = collect($model->getOriginal())
                ->only(array_keys($model->getDirty()))
                ->toArray();
            $newValues = $model->getDirty();

            // Sensitive fields-i gizlət
            $hidden = $model->auditHidden ?? ['password', 'remember_token'];
            foreach ($hidden as $field) {
                if (isset($oldValues[$field])) $oldValues[$field] = '***';
                if (isset($newValues[$field])) $newValues[$field] = '***';
            }

            if (!empty($newValues)) {
                AuditLog::record('updated', $model, $oldValues, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            AuditLog::record('deleted', $model, $model->getAttributes(), null);
        });
    }
}

class AuditLog extends Model {
    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'metadata',
    ];

    protected function casts(): array {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public static function record(
        string $action,
        Model $model,
        ?array $oldValues,
        ?array $newValues,
        array $metadata = [],
    ): self {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    // Sorğular
    public function scopeForModel(Builder $query, Model $model): Builder {
        return $query->where('model_type', get_class($model))
            ->where('model_id', $model->getKey());
    }

    public function scopeByUser(Builder $query, int $userId): Builder {
        return $query->where('user_id', $userId);
    }

    public function scopeAction(Builder $query, string $action): Builder {
        return $query->where('action', $action);
    }
}

// İstifadə
class Order extends Model {
    use Auditable;

    protected array $auditHidden = ['payment_token'];
}

// Audit history sorğusu
$history = AuditLog::forModel($order)
    ->with('user:id,name')
    ->latest()
    ->paginate(20);

// API response:
// [
//   {"action": "updated", "user": "Admin", "old": {"status": "pending"}, "new": {"status": "shipped"}, "at": "2026-04-11 15:30"},
//   {"action": "created", "user": "Orxan", "new": {"total": 159.99, ...}, "at": "2026-04-11 10:00"},
// ]
```

---

## 5. Two-Factor Authentication (2FA)

```php
// composer require pragmarx/google2fa-laravel

class TwoFactorService {
    public function __construct(private Google2FA $google2fa) {}

    public function enable(User $user): array {
        $secret = $this->google2fa->generateSecretKey();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
        ]);

        // QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        // Recovery codes
        $recoveryCodes = Collection::times(8, fn () => Str::random(10))->toArray();
        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    public function confirm(User $user, string $code): bool {
        $secret = decrypt($user->two_factor_secret);

        if (!$this->google2fa->verifyKey($secret, $code)) {
            return false;
        }

        $user->update(['two_factor_confirmed_at' => now()]);
        return true;
    }

    public function verify(User $user, string $code): bool {
        // Recovery code yoxla
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        if (in_array($code, $recoveryCodes)) {
            // Recovery code istifadə olundu — sil
            $recoveryCodes = array_diff($recoveryCodes, [$code]);
            $user->update([
                'two_factor_recovery_codes' => encrypt(json_encode(array_values($recoveryCodes))),
            ]);
            return true;
        }

        // TOTP code yoxla
        $secret = decrypt($user->two_factor_secret);
        return $this->google2fa->verifyKey($secret, $code);
    }
}

// Login flow with 2FA
class LoginController extends Controller {
    public function login(LoginRequest $request): JsonResponse {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Yanlış email və ya şifrə.'],
            ]);
        }

        // 2FA aktiv isə, challenge token qaytar
        if ($user->two_factor_confirmed_at) {
            $challengeToken = Str::random(64);
            Cache::put("2fa_challenge:{$challengeToken}", $user->id, now()->addMinutes(5));

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        return $this->issueTokens($user);
    }

    public function verifyTwoFactor(Request $request): JsonResponse {
        $request->validate([
            'challenge_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $userId = Cache::pull("2fa_challenge:{$request->challenge_token}");
        if (!$userId) {
            abort(401, 'Challenge müddəti bitib.');
        }

        $user = User::findOrFail($userId);

        if (!app(TwoFactorService::class)->verify($user, $request->code)) {
            abort(401, 'Yanlış doğrulama kodu.');
        }

        return $this->issueTokens($user);
    }
}
```

---

## 6. Social Login (OAuth2 Client)

```php
// Laravel Socialite
// composer require laravel/socialite

// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],

class SocialAuthController extends Controller {
    public function redirect(string $provider): RedirectResponse {
        $this->validateProvider($provider);
        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): JsonResponse {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            return response()->json(['error' => 'Autentifikasiya uğursuz oldu.'], 401);
        }

        $user = DB::transaction(function () use ($socialUser, $provider) {
            // Əvvəlcə social account-a bağlı user axtar
            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($socialAccount) {
                // Mövcud istifadəçi
                $socialAccount->update([
                    'token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                ]);
                return $socialAccount->user;
            }

            // Email ilə mövcud user axtar
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                // Yeni user yarat
                $user = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'avatar' => $socialUser->getAvatar(),
                    'email_verified_at' => now(), // Social login = verified
                    'password' => Hash::make(Str::random(32)), // random password
                ]);
            }

            // Social account bağla
            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ]);

            return $user;
        });

        $token = $user->createToken('social-login')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    private function validateProvider(string $provider): void {
        if (!in_array($provider, ['google', 'github', 'facebook'])) {
            abort(404, 'Dəstəklənməyən provider.');
        }
    }
}
```
