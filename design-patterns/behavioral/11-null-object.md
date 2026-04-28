# Null Object (Middle ⭐⭐)

## İcmal
Null Object pattern, `null` yoxlanışlarını (`if ($user !== null)`, `$user?->getName()`) aradan qaldırmaq üçün "heç nə etməyən" (no-op) default implementasiya təqdim edir. Client kod `null` yoxlamaq əvəzinə həmişə real interface ilə işləyir — `NullUser`, `NullLogger`, `NullCache` real implementasiyanın bütün metodlarına cavab verir, sadəcə "boş" cavab qaytarır. "Optional behaviour"u birinci sinif vətəndaş halına gətirir.

## Niyə Vacibdir
PHP layihələrindəki ən çirkin null-check zəncirləri — `if ($user && $user->subscription && $user->subscription->plan)` — oxunaqlılığı məhv edir, `null` unuttduqda `TypeError` atır. Laravel-də `Auth::user()` unauthenticated user üçün `null` qaytarır — əvvəlcə yoxla, sonra işlət. `NullUser` pattern ilə bu yoxlamalar aradan qalxır: guest-in `getName()` `'Guest'` qaytarır, `hasPermission()` `false` qaytarır, `getAvatar()` default şəkil qaytarır — null yox.

## Əsas Anlayışlar
- **Interface**: həm real, həm null implementasiyasının implement etdiyi ortaq contract — `UserInterface`, `LoggerInterface`, `CacheInterface`
- **Real implementasiya**: əsl business logic-i olan class — `AuthenticatedUser`, `FileLogger`, `RedisCache`
- **Null Object**: eyni interface-i implement edir, bütün metodlar "safe default" qaytarır — exception atmır, null qaytarmır, sadəcə boş/default dəyər
- **Client şəffaflığı**: client kod null yoxlamadan interface-i çağırır; null object özü "yoxlamanı" ehtiva edir
- **Default behaviour**: null object-in "boş cavabı" domain-ə uyğun olmalıdır — `NullCache::get()` `null` qaytarır (cache miss), `NullLogger::info()` heç nə etmir

## Praktik Baxış
- **Real istifadə**: Guest user vs Authenticated user, NullLogger (test environment), NullCache (fallback), optional service (analytics, feature flags), optional notification channel
- **Trade-off-lar**: client kod sadələşir; lakin null object-lər "silent failure" yarada bilər — xəta olduğunda heç kim bilmir; real null-un məna daşıdığı hallarda null object semantikası çaşdıra bilər
- **İstifadə etməmək**: null-un özünün mənalı (semantic) olduğu hallarda — `findById()` tapılmamış qeydi bildirmək üçün null qaytarır; bu halda null object yox, exception ya `Optional` daha uyğundur; real xətanın görünən olması lazım olan yerdə
- **Common mistakes**: Null Object-in exception atması lazım olan yerlərdə susması — əgər `NullPaymentGateway::charge()` log etmədən `true` qaytarırsa, production-da heç bir ödəniş alınmadan "uğurlu" görünər; null object yalnız həqiqətən "isteğe bağlı" olan davranış üçün istifadə olunur
- **Anti-Pattern Nə Zaman Olur?**: Null Object real xətanı gizlədəndə — `NullEmailService::send()` log belə etmədən `true` qaytarırsa, istifadəçilər email almır amma sistem "uğurlu" hesab edir. Qayda: null object-lər "no-op" ola bilər amma "silent-error" olmamalıdır; ən azından log yazılmalı ya da izlənə bilən şəkildə işləməlidir. Digər problem: hər yerdə null object istifadə etmək — bəzi null-lar məna daşıyır (`DB::select()` boş array qaytarırsa bu xəta deyil, amma `User::find(999)` `null` qaytarırsa bu "tapılmadı" siqnalıdır — null object bu fərqi gizlədər).

## Nümunələr

### Ümumi Nümunə
E-commerce saytında həm qeydiyyatsız guest, həm qeydiyyatlu user eyni səhifəni açır. `Auth::user()` null qaytardıqda hər yerdə `if ($user)` yazmaq əvəzinə `NullUser` istifadə olunur: `$user->getDisplayName()` guest üçün `'Qonaq'` qaytarır, `$user->canCheckout()` false qaytarır, `$user->getCartId()` session-based cart id qaytarır. Controller heç vaxt null yoxlamır.

### PHP/Laravel Nümunəsi

**User Interface + implementasiyalar:**

```php
<?php

// Interface — həm real, həm null implementasiyası bunu implement edir
interface UserInterface
{
    public function getId(): ?int;
    public function getName(): string;
    public function getEmail(): string;
    public function getAvatar(): string;
    public function isAuthenticated(): bool;
    public function hasPermission(string $permission): bool;
    public function canCheckout(): bool;
    public function getCartId(): string;
}

// Real implementasiya — DB-dən gələn user
class AuthenticatedUser implements UserInterface
{
    public function __construct(private readonly \App\Models\User $model) {}

    public function getId(): ?int         { return $this->model->id; }
    public function getName(): string     { return $this->model->name; }
    public function getEmail(): string    { return $this->model->email; }

    public function getAvatar(): string
    {
        // Gravatar ya da upload olunmuş şəkil
        return $this->model->avatar_url
            ?? 'https://www.gravatar.com/avatar/' . md5($this->model->email);
    }

    public function isAuthenticated(): bool { return true; }

    public function hasPermission(string $permission): bool
    {
        // Spatie permission-dan yoxla
        return $this->model->can($permission);
    }

    public function canCheckout(): bool
    {
        // Email verify olunmuş və aktiv hesab
        return $this->model->hasVerifiedEmail() && !$this->model->is_suspended;
    }

    public function getCartId(): string
    {
        // DB-dəki persistent cart
        return "user_cart_{$this->model->id}";
    }
}

// Null Object — guest user; heç vaxt null atmaz, default cavablar verir
class GuestUser implements UserInterface
{
    public function __construct(
        private readonly string $sessionId // guest cart üçün session-based id
    ) {}

    public function getId(): ?int     { return null; }      // authenticated deyil
    public function getName(): string { return 'Qonaq'; }   // default ad
    public function getEmail(): string { return ''; }       // email yoxdur

    public function getAvatar(): string
    {
        // Default guest avatar — null yox
        return asset('images/guest-avatar.png');
    }

    public function isAuthenticated(): bool { return false; }

    public function hasPermission(string $permission): bool
    {
        // Guest-in heç bir permission-ı yoxdur
        return false;
    }

    public function canCheckout(): bool
    {
        // Guest checkout edə bilməz (register lazımdır)
        return false;
    }

    public function getCartId(): string
    {
        // Session-based cart — guest üçün
        return "guest_cart_{$this->sessionId}";
    }
}

// Laravel Service Provider — null object inject et
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserInterface::class, function () {
            $authUser = auth()->user();

            // null check yalnız bir yerdə — service provider-də
            return $authUser
                ? new AuthenticatedUser($authUser)
                : new GuestUser(session()->getId());
        });
    }
}

// Controller — heç vaxt null yoxlamır
class CartController extends Controller
{
    public function __construct(private readonly UserInterface $user) {}

    public function index(): \Illuminate\View\View
    {
        return view('cart.index', [
            'userName'    => $this->user->getName(),    // 'Əli' ya da 'Qonaq'
            'avatar'      => $this->user->getAvatar(),  // real şəkil ya da default
            'canCheckout' => $this->user->canCheckout(), // true ya da false
            'cartId'      => $this->user->getCartId(),  // user_cart_5 ya da guest_cart_abc
        ]);
    }

    public function checkout(): \Illuminate\Http\JsonResponse
    {
        // if ($user !== null && $user->canCheckout()) — artıq lazım deyil
        if (!$this->user->canCheckout()) {
            return response()->json(['error' => 'Please login to checkout'], 401);
        }

        // real checkout logic
        return response()->json(['redirect' => route('payment.index')]);
    }
}
```

**NullLogger — test/optional logging:**

```php
interface AppLoggerInterface
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
}

// Real implementasiya
class FileLogger implements AppLoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }
}

// Null Object — test-lərdə ya da logging disabled olduqda
class NullLogger implements AppLoggerInterface
{
    // Heç nə etmir — test-lərdə log faylı çirkini qarşısını alır
    public function info(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
}

// Service — logger optional; null yoxlaması yoxdur
class OrderProcessingService
{
    public function __construct(
        private readonly AppLoggerInterface $logger // NullLogger ya da FileLogger
    ) {}

    public function processOrder(Order $order): void
    {
        // Logger null-dırmı yoxlamadan çağırırıq
        $this->logger->info('Processing order', ['order_id' => $order->id]);

        try {
            // order processing logic
            $this->logger->info('Order processed successfully', ['order_id' => $order->id]);
        } catch (\Throwable $e) {
            $this->logger->error('Order processing failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

// ServiceProvider-də environment-a görə seç
class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AppLoggerInterface::class, function () {
            // Test mühitində NullLogger, istehsalda FileLogger
            return app()->environment('testing')
                ? new NullLogger()
                : new FileLogger();
        });
    }
}
```

**NullCache — optional caching:**

```php
interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
    public function forget(string $key): void;
    public function has(string $key): bool;
}

class RedisCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return Cache::get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        Cache::put($key, $value, $ttl);
    }

    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    public function has(string $key): bool
    {
        return Cache::has($key);
    }
}

// NullCache — Redis mövcud olmadıqda ya da cache disabled olduqda
class NullCache implements CacheInterface
{
    // Cache miss kimi davranır — hər dəfə fresh data götürülür
    public function get(string $key): mixed   { return null; }
    public function set(string $key, mixed $value, int $ttl = 3600): void {} // no-op
    public function forget(string $key): void {} // no-op
    public function has(string $key): bool    { return false; }
}

// Service — cache null-dırmı bilmir
class ProductService
{
    public function __construct(private readonly CacheInterface $cache) {}

    public function getPopularProducts(): array
    {
        $key = 'popular_products';

        // if ($this->cache !== null && $this->cache->has($key)) — lazım deyil
        if ($this->cache->has($key)) {
            return $this->cache->get($key); // NullCache: false qaytarır, bura girmir
        }

        $products = Product::popular()->limit(10)->get()->toArray();

        $this->cache->set($key, $products, 1800); // NullCache: heç nə etmir

        return $products;
    }
}

// Feature flag ilə optional cache
class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CacheInterface::class, function () {
            // Config-dən cache-i söndürmək mümkün — test/debug üçün
            return config('cache.enabled', true)
                ? new RedisCache()
                : new NullCache();
        });
    }
}
```

**NullUser-i Laravel Auth ilə:**

```php
// Helper — bütün layihədə istifadə üçün
function currentUser(): UserInterface
{
    return app(UserInterface::class);
}

// View-da da null yoxlaması yoxdur
// resources/views/header.blade.php
// @if(currentUser()->isAuthenticated())
//     <span>{{ currentUser()->getName() }}</span>
// @else
//     <a href="{{ route('login') }}">Login</a>
// @endif

// Ya da hətta view-da da null object-dən istifadə:
// <img src="{{ currentUser()->getAvatar() }}" alt="{{ currentUser()->getName() }}">
// (Hər iki halda — authenticated user şəkli ya da guest default şəkli)
```

## Praktik Tapşırıqlar
1. `AnalyticsService` üçün null object yazın: real implementasiya Google Analytics API-yə data göndərir; `NullAnalytics` heç nə etmir; development/test mühitlərində null object inject edin; bir də `LoggingAnalytics` wrapper yazın ki, production-da real analytics-ə göndərməzdən əvvəl log atsın
2. `FeatureFlagService` üçün: real implementasiya DB-dən feature toggle-ları oxuyur; `NullFeatureFlag` hər zaman `false` qaytarır (feature disabled kimi); `AllEnabledFeatureFlag` hər zaman `true` qaytarır (local development üçün); config-dən hangisini inject edəcəyini seçin
3. Mövcud bir controller-i götürün — `if ($user !== null)`, `$user?->method()` kimi null guard-ları olan; `UserInterface` + `AuthenticatedUser` + `GuestUser` yazaraq null guard-ları tamamilə aradan qaldırın; test edin

## Əlaqəli Mövzular
- [../structural/04-proxy.md](../structural/04-proxy.md) — Proxy da real object-in önündə durur; fərq: Proxy real object-ə delegate edir, Null Object "heç nə" edir
- [../creational/01-singleton.md](../creational/01-singleton.md) — NullLogger/NullCache tez-tez singleton kimi bind olunur
- [02-strategy.md](02-strategy.md) — Null Object Strategy-nin "do-nothing" variantı kimi düşünülə bilər
- [../structural/03-decorator.md](../structural/03-decorator.md) — NullObject + Decorator: `LoggingCache` real cache-i wrap edir; `NullCache`-i wrap etmək isə sadəcə log atar
- [../architecture/02-solid-principles.md](../architecture/02-solid-principles.md) — Liskov Substitution Principle: NullObject real implementasiya ilə tam dəyişdirilə bilər, client kod fərq etməməlidir
