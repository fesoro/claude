# Authentication vs Authorization (Junior ⭐)

## İcmal

Authentication (AuthN) — "Sən kimsən?" sualının cavabıdır. Authorization (AuthZ) — "Sən nə edə bilərsən?" sualının cavabıdır. Bu iki konsept tez-tez qarışdırılır, halbuki sistemin güvənliyinin iki ayrı pilləsidir. Interview-da bu fərqi aydın izah etmək, Laravel-də implementation-ı bilmək junior-middle developer üçün minimaldır. OWASP 2021-də Broken Access Control (A01) ən kritik risk olaraq qeyd edilib — əsasən authorization xətalarından qaynaqlanır.

## Niyə Vacibdir

Authentication olmadan heç kim kim olduğunu isbat edə bilmir. Authorization olmadan hər autentifikasiya olmuş istifadəçi hər şeyə daxil ola bilər — biri digərinin datasını silə bilər. Broken Access Control (OWASP A01) əsasən authorization xətalıdır. Real sistemlərdə bu iki mövzunu düzgün tətbiq etmək data breach-in qarşısını alır. 2023-cü ildə OWASP-ın araşdırmasına görə audit edilmiş tətbiqlərin 94%-ində bir növ broken access control tapılıb.

## Əsas Anlayışlar

- **Authentication (AuthN)**: Kimliyi doğrulamaq. "Sən kimsən?" — şifrə, biometrik, OTP, certificate, hardware token. Login prosesi = authentication. 401 Unauthorized = authentication uğursuzluğu (kim olduğun bilinmir)
- **Authorization (AuthZ)**: İcazəni yoxlamaq. "Sən bunu edə bilərsənmi?" — role, permission, policy, attribute. 403 Forbidden = authorization uğursuzluğu (kim olduğun bilinir, amma icazən yoxdur)
- **401 vs 403 fərqi**: 401 = "Kim olduğunu bilmirəm, öncə login ol." 403 = "Kim olduğunu bilirəm, amma bunu edəməzsən." Laravel-də `abort(401)` vs `abort(403)` — doğru kod vacibdir
- **Session-based auth**: Server session-ı saxlayır (Redis, DB), client session ID cookie daşıyır. Stateful. Server-side revocation asandır. Horizontal scaling-də sticky session ya da shared session storage lazımdır
- **Token-based auth**: JWT — stateless, server heç nə saxlamır, client token-ı daşıyır. Horizontal scaling asandır. Revocation çətindir (blacklist lazımdır)
- **OAuth 2.0**: 3rd party authorization framework — "Google ilə giriş". User şifrəsini 3rd party app-ə vermir. Authorization code flow, Client Credentials
- **RBAC (Role-Based Access Control)**: Role-a görə icazə — `admin`, `editor`, `viewer`, `support`. İstifadəçiyə role assign edilir, role-a permission əlavə edilir. Ən geniş yayılmış model
- **ABAC (Attribute-Based Access Control)**: Attribute-a görə icazə — `user.department == resource.department`, `user.clearance_level >= document.classification`. Daha güclü, amma daha mürəkkəb
- **ReBAC (Relationship-Based Access Control)**: Əlaqəyə görə icazə — Google Zanzibar modeli. "User Y-nin document X-ə editori olduğu" kimi əlaqə. Google Drive bu modeli istifadə edir
- **Policy**: Laravel-də authorization logic-i — kim nəyi edə bilər. `OrderPolicy`, `DocumentPolicy`. Controller-dən separation of concerns
- **Gate**: Laravel-də simple authorization check — policy-dən az formal. `Gate::define('view-analytics', fn(User $user) => $user->isAnalyst())`
- **Middleware**: Route-a giriş müdafiəsi — `auth:sanctum`, `role:admin`, `permission:orders.delete`. Request-in controller-ə çatmazdan əvvəl yoxlanması
- **Multi-factor Authentication (MFA)**: Bilinən (şifrə) + sahib olunan (phone/token) + olan (biometrik). Hər faktor ayrı attack vector tələb edir
- **Principle of Least Privilege**: İstifadəçi yalnız lazım olan icazəyə sahib olmalıdır. Admin olmadan editor-ün admin panelə girişi olmamalıdır
- **Impersonation**: Admin başqasının hesabından fəaliyyət göstərə bilmək — müştəri dəstəyi üçün. Audit log vacibdir — kim, nə vaxt, kimin adına
- **Revocation**: Token-ı ya da session-ı etibarsız etmək. Session: server-side silmək. JWT: blacklist ya da short expiry

## Praktik Baxış

**Interview-da yanaşma:**
Əvvəlcə fərqi bir cümlə ilə aydın izah edin, sonra konkret nümunə verin. Laravel-in Policy + Gate mexanizmini, 401 vs 403 ayrımını göstərə bilmək orta-yaxşı cavabdır. RBAC vs ABAC trade-off-unu bilmək sizi fərqləndirir.

**Follow-up suallar (top companies-da soruşulur):**
- "Session-based vs Token-based auth — nə zaman hansını seçərsiniz?" → Session: server-side app, cookie-based auth, tez revocation lazımdır. Token (JWT): microservices, stateless API, mobile, SPA. Token refresh-in revocation-ı çətin — session daha güvənli bu baxımdan
- "RBAC vs ABAC — nə vaxt seçilir?" → RBAC: sadə, az role, performans vacib (permission cache-lənir). ABAC: kompleks access rules, fine-grained control lazımdır, department-based access. ABAC daha güclü amma daha yavaş
- "Middleware vs Policy — nə vaxt hansını istifadə edirsiniz?" → Middleware: route-level — "Bu endpoint-ə yalnız admin girə bilər." Policy: model-level — "Bu user bu spesifik resursa edə bilərmi?" İkisi tamamlayıcıdır, əvəzedici deyil
- "MFA-nı Laravel-də necə implement edərdiniz?" → `laravel-fortify` built-in 2FA dəstəyi. TOTP (Time-based One-Time Password) — Google Authenticator. Backup codes. Login-dən sonra MFA challenge session-a saxla, verify olunana qədər tam access yoxdur
- "Horizontal scaling-də session-based auth problemi?" → Hər worker öz session store-una baxarsa, user worker-1-də login olub worker-2-yə gəlsə session tapılmır. Həll: Redis shared session store (`SESSION_DRIVER=redis`)
- "Super admin — bütün policy-ləri bypass etmək üçün?" → `Gate::before()` hook. Amma impersonation üçün audit log vacibdir — super admin kimin adına nə etdi

**Ümumi səhvlər (candidate-ların etdiyi):**
- Yalnız middleware ilə qorunmaq, controller daxilindəki manual authorization yox — route-level `auth` middleware yetərli deyil, model-level policy lazımdır
- Role-u string kimi hər yerdə yoxlamaq (`if ($user->role === 'admin')`) — Policy class olmaq yerinə magic string-lər
- Authorization xətasında 401 vs 403 qarışdırmaq — hər ikisi "access denied" görünür, amma fərqli mənada
- RBAC-ın yetərli olmadığı hallarda ABAC-ı düşünməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
Policy class-ın controller-dən separation-ının faydalarını izah etmək, 401 vs 403-ü doğru istifadə etmək, RBAC vs ABAC trade-off-unu konkret nümunəylə izah etmək, `Gate::before()` ilə super-admin pattern-inin audit log vacibliyi.

## Nümunələr

### Tipik Interview Sualı

"Authentication ilə Authorization arasındakı fərqi izah edin. Laravel-də necə tətbiq olunur?"

### Güclü Cavab

"Authentication — kimliyi doğrulamaq: login formu, JWT verify. 'Bu user mövcuddur, şifrəsi doğrudur.' Authorization — icazəni yoxlamaq: bu user bu resursa daxil ola bilərmi? 'Bu order bu user-ındır.'

401 = authentication uğursuzluğu (login lazımdır). 403 = authorization uğursuzluğu (login olub amma icazə yoxdur).

Laravel-də authentication `Auth::guard()` + Sanctum/Passport ilə. Authorization Policy-lər ilə. Məsələn: user autentifikasiya olub (AuthN), amma yalnız öz sifarişini görə bilir (AuthZ). `$this->authorize('view', $order)` → `OrderPolicy::view()` → user sahibi deyilsə 403."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// AUTHENTICATION — Laravel Sanctum ilə
// ============================================================
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
            // 401 — Authentication uğursuzluğu: kim olduğun bilinmir
        }

        $user  = $request->user();
        $token = $user->createToken(
            name: $request->device_name ?? 'api',
            abilities: $this->getAbilitiesForUser($user),
        )->plainTextToken;

        return response()->json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => [
                'id'    => $user->id,
                'name'  => $user->name,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    private function getAbilitiesForUser(User $user): array
    {
        // Role-a görə token abilities (scope)
        return match(true) {
            $user->hasRole('admin')  => ['*'],               // Hər şey
            $user->hasRole('editor') => ['posts:*', 'media:upload'],
            default                  => ['profile:read', 'orders:own'],
        };
    }
}
```

```php
// ============================================================
// AUTHORIZATION — Policy ilə (model-level)
// ============================================================
// app/Policies/OrderPolicy.php

class OrderPolicy
{
    // Gate::before() — super admin bypass
    public function before(User $user): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true; // Bütün policy method-larını bypass edir
            // ⚠️ Audit log: super-admin kimin datasını gördü?
        }
        return null; // Digər method-larla davam et
    }

    // İstifadəçi öz sifarişini görə bilər
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->hasRole('admin')
            || $user->hasRole('support');
    }

    // Yalnız "placed" ya da "paid" statusda ləğv etmək olar
    public function cancel(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            && in_array($order->status, ['placed', 'paid'], true);
    }

    // Export yalnız finance role
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('orders.export');
    }

    // Admin silə bilər, lakin active order deyilsə
    public function forceDelete(User $user, Order $order): bool
    {
        return $user->hasRole('admin')
            && !in_array($order->status, ['placed', 'paid', 'shipped'], true);
    }
}

// Controller-də istifadə
class OrderController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        // 403 Forbidden əgər icazə yoxdursa (kimliyi bilinir, icazə yoxdur)
        return response()->json(OrderResource::make($order));
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);
        $order->cancel();
        return response()->json(['message' => 'Order cancelled']);
    }

    // Policy olmadan — sadə check
    public function exportAll(): Response
    {
        $this->authorize('export', Order::class);
        return Excel::download(new OrdersExport(), 'orders.xlsx');
    }
}
```

```php
// ============================================================
// RBAC — Spatie Permission Package
// ============================================================

// Role-lar müəyyən et
$adminRole   = Role::create(['name' => 'admin']);
$editorRole  = Role::create(['name' => 'editor']);
$viewerRole  = Role::create(['name' => 'viewer']);
$supportRole = Role::create(['name' => 'support']);

// Permission-lar
$permissions = [
    'orders.view', 'orders.create', 'orders.update',
    'orders.delete', 'orders.export', 'orders.refund',
    'users.view', 'users.manage',
    'products.view', 'products.manage',
    'reports.view', 'reports.export',
];

foreach ($permissions as $perm) {
    Permission::create(['name' => $perm]);
}

// Role-lara permission assign et (matrix)
$adminRole->syncPermissions([
    'orders.view', 'orders.create', 'orders.update', 'orders.delete',
    'orders.export', 'orders.refund', 'users.view', 'users.manage',
    'products.view', 'products.manage', 'reports.view', 'reports.export',
]);

$editorRole->syncPermissions([
    'orders.view', 'orders.create', 'orders.update',
    'products.view', 'products.manage', 'reports.view',
]);

$supportRole->syncPermissions([
    'orders.view', 'orders.update',
    'users.view',
]);

$viewerRole->syncPermissions([
    'orders.view', 'products.view', 'reports.view',
]);

// User-a role ver
$user->assignRole('editor');
$user->givePermissionTo('orders.export'); // Əlavə permission (role-dan kənar)

// Route-da middleware ilə qoruma
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'permission:orders.export'])->group(function () {
    Route::get('/orders/export', [OrderController::class, 'export']);
});
```

```php
// ============================================================
// GATE — Simple authorization check
// ============================================================
// app/Providers/AuthServiceProvider.php

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Sadə gate — inline
        Gate::define('view-analytics', function (User $user): bool {
            return $user->hasAnyRole(['admin', 'analyst', 'data-team']);
        });

        Gate::define('manage-billing', function (User $user): bool {
            return $user->hasRole('admin')
                || ($user->hasRole('manager') && $user->company->plan === 'enterprise');
        });

        // Super-admin — bütün gate-ləri bypass edir
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasRole('super-admin')) {
                Log::channel('audit')->info('super_admin_access', [
                    'user_id' => $user->id,
                    'ability' => $ability,
                    'ip'      => request()->ip(),
                ]);
                return true;
            }
            return null; // Digər check-lərlə davam et
        });
    }
}

// İstifadəsi:
if (Gate::allows('view-analytics')) {
    // ...
}

Gate::authorize('view-analytics'); // 403 abort edir əgər icazə yoxdursa

// Blade-da:
// @can('manage-billing')
//   <button>Billing Settings</button>
// @endcan
```

```php
// ============================================================
// MFA — TOTP Implementation
// ============================================================
use OTPHP\TOTP;

class TwoFactorController extends Controller
{
    public function enable(Request $request): JsonResponse
    {
        $user  = $request->user();
        $totp  = TOTP::generate();

        // Secret-i user modeline saxla
        $user->update([
            'two_factor_secret' => encrypt($totp->getSecret()),
            'two_factor_enabled' => false, // Verify olunana qədər aktiv deyil
        ]);

        return response()->json([
            'qr_code_url'    => $totp->getQrCodeUri(
                label: $user->email,
                issuer: config('app.name')
            ),
            'manual_entry_key' => $totp->getSecret(),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        $totp = TOTP::createFromSecret(decrypt($user->two_factor_secret));

        if (!$totp->verify($request->code)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $user->update(['two_factor_enabled' => true]);

        // Backup codes generate et
        $backupCodes = collect(range(1, 8))->map(fn() => Str::random(10));
        $user->update(['two_factor_recovery_codes' => encrypt($backupCodes->toJson())]);

        return response()->json([
            'message'      => '2FA enabled',
            'backup_codes' => $backupCodes,
        ]);
    }
}
```

## Praktik Tapşırıqlar

1. OrderPolicy yazın: `view`, `update`, `delete`, `cancel`, `export` metodları ilə. Hər metodda fərqli authorization məntiqi
2. RBAC: `admin`, `manager`, `support`, `viewer` role-larını müəyyən edin, icazə matrixi qurun — Google Sheets-də vizual
3. 401 vs 403 — öz tətbiqinizdə doğru status code istifadə olunurmu? Unauthenticated vs unauthorized ayrımı?
4. `authorize()` olmadan yalnız middleware ilə qorunan endpoint-i tapın — controller-ə gəldikdə başqasının datasına çatmaq mümkündürmü?
5. ABAC ssenariusu: "Yalnız eyni şirkətin istifadəçiləri şirkətin sifarişlərini görə bilər" — bunu Policy-də implement edin
6. Gate::before super-admin implement edin + audit log — kim, nə vaxt, nəyə çatdı
7. MFA: Laravel Fortify-nin 2FA funksiyasını aktiv edin, TOTP ilə test edin

## Əlaqəli Mövzular

- `05-jwt-deep-dive.md` — Token-based authentication dərinliyi, JWT security
- `06-oauth2-flows.md` — Third-party authorization, OAuth 2.0 flows
- `11-least-privilege.md` — Authorization design prinsipləri, blast radius
- `01-owasp-top-10.md` — A01 Broken Access Control, A07 Authentication Failures
