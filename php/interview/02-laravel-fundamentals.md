# Laravel Fundamentals (Middle)

## 1. Laravel-in Service Container (IoC Container) necə işləyir?

Service Container — Dependency Injection-u idarə edən güclü bir alətdir. Sinifləri qeydiyyatdan keçirir və avtomatik həll edir (resolve).

```php
// Binding — interfeysi konkret sinifə bağlama
// AppServiceProvider::register()
$this->app->bind(PaymentGateway::class, StripeGateway::class);

// Singleton — yalnız bir instance yaradılır
$this->app->singleton(CartService::class, function ($app) {
    return new CartService($app->make(SessionManager::class));
});

// Contextual binding — müxtəlif siniflərdə müxtəlif implementasiya
$this->app->when(OrderController::class)
    ->needs(PaymentGateway::class)
    ->give(StripeGateway::class);

$this->app->when(SubscriptionController::class)
    ->needs(PaymentGateway::class)
    ->give(BraintreeGateway::class);
```

**Auto-resolution:** Əgər sinifin constructor-unda type-hint varsa, Laravel avtomatik həll edir:
```php
class OrderService {
    // Laravel avtomatik UserRepository instance-ını inject edəcək
    public function __construct(private UserRepository $users) {}
}
```

---

## 2. Service Providers nədir?

Laravel-in bootstrapping mərkəzidir. Bütün binding-lər, event listener-lər, middleware, route-lar burada qeydiyyatdan keçir.

```php
class PaymentServiceProvider extends ServiceProvider {
    // register() — yalnız binding-lər (digər servislərdən istifadə etmə)
    public function register(): void {
        $this->app->singleton(PaymentGateway::class, function () {
            return new StripeGateway(config('services.stripe.key'));
        });
    }

    // boot() — bütün provider-lər register olunandan sonra çağırılır
    public function boot(): void {
        Event::listen(PaymentFailed::class, HandleFailedPayment::class);
    }
}

// config/app.php-da qeydiyyat (və ya auto-discovery)
'providers' => [
    App\Providers\PaymentServiceProvider::class,
],
```

**Register vs Boot fərqi:**
- `register()` — yalnız container-ə binding et. Digər servislər hələ hazır olmaya bilər.
- `boot()` — bütün serislər hazırdır, burada istifadə edə bilərsən.

---

## 3. Laravel Middleware necə işləyir?

HTTP request/response pipeline-ında filtrasiya layer-idir.

```php
// Before middleware — request controller-ə çatmamışdan əvvəl
class EnsureApiVersion {
    public function handle(Request $request, Closure $next): Response {
        if ($request->header('Api-Version') !== 'v2') {
            return response()->json(['error' => 'Unsupported API version'], 400);
        }
        return $next($request);
    }
}

// After middleware — response göndərilmədən əvvəl
class AddSecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}

// Terminable middleware — response göndərildikdən sonra
class LogRequest {
    public function handle(Request $request, Closure $next): Response {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void {
        Log::info('Request completed', [
            'url' => $request->url(),
            'status' => $response->status(),
        ]);
    }
}

// Parametrli middleware
class CheckRole {
    public function handle(Request $request, Closure $next, string ...$roles): Response {
        if (!$request->user()?->hasAnyRole($roles)) {
            abort(403);
        }
        return $next($request);
    }
}

// Route-da istifadə
Route::get('/admin', AdminController::class)->middleware('role:admin,super-admin');
```

---

## 4. Laravel Request Lifecycle-ı izah edin.

1. **public/index.php** — giriş nöqtəsi
2. **HTTP Kernel** yüklənir
3. **Service Providers** register və boot olunur
4. **Global Middleware** icra olunur
5. **Router** uyğun route tapır
6. **Route Middleware** icra olunur
7. **Controller** method çağırılır
8. **Response** yaradılır
9. **Middleware** (after) response-u emal edir
10. **Response** client-ə göndərilir
11. **Terminable Middleware** icra olunur

---

## 5. Eloquent ORM - Əlaqələr (Relationships)

```php
class User extends Model {
    // One to One
    public function profile(): HasOne {
        return $this->hasOne(Profile::class);
    }

    // One to Many
    public function posts(): HasMany {
        return $this->hasMany(Post::class);
    }

    // Many to Many
    public function roles(): BelongsToMany {
        return $this->belongsToMany(Role::class)
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    // Has Many Through
    public function comments(): HasManyThrough {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    // Polymorphic
    public function image(): MorphOne {
        return $this->morphOne(Image::class, 'imageable');
    }
}

class Post extends Model {
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    // Polymorphic Many to Many
    public function tags(): MorphToMany {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}

class Image extends Model {
    public function imageable(): MorphTo {
        return $this->morphTo();
    }
}
```

---

## 6. N+1 problem nədir və necə həll olunur?

```php
// N+1 problem — 1 sorğu users üçün + N sorğu hər user-in posts-u üçün
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count(); // Hər iterasiyada ayrı sorğu
}

// Həll: Eager Loading
$users = User::with('posts')->get();              // 2 sorğu
$users = User::with(['posts', 'roles'])->get();   // 3 sorğu
$users = User::with('posts.comments')->get();     // nested eager load

// Conditional eager loading
$users = User::with(['posts' => function ($query) {
    $query->where('published', true)->orderBy('created_at', 'desc');
}])->get();

// Lazy eager loading (artıq yüklənmiş collection üçün)
$users = User::all();
$users->load('posts');

// withCount — əlaqəli modelin sayını almaq
$users = User::withCount('posts')->get();
echo $users[0]->posts_count;

// Preventive measure — development-da N+1 aşkarlamaq
// AppServiceProvider::boot()
Model::preventLazyLoading(!app()->isProduction());
```

---

## 7. Eloquent Scopes nədir?

Tez-tez istifadə olunan query şərtlərini yenidən istifadə oluna bilən şəkildə təyin etmək.

```php
class Post extends Model {
    // Global Scope — həmişə tətbiq olunur
    protected static function booted(): void {
        static::addGlobalScope('published', function (Builder $builder) {
            $builder->where('published', true);
        });
    }

    // Local Scope — yalnız çağırılanda tətbiq olunur
    public function scopePopular(Builder $query, int $minViews = 1000): Builder {
        return $query->where('views', '>=', $minViews);
    }

    public function scopeRecent(Builder $query): Builder {
        return $query->where('created_at', '>=', now()->subWeek());
    }

    public function scopeByAuthor(Builder $query, User $user): Builder {
        return $query->where('user_id', $user->id);
    }
}

// İstifadə — zəncirlənə bilər
$posts = Post::popular(500)->recent()->get();

// Global scope-u deaktiv et
$allPosts = Post::withoutGlobalScope('published')->get();
```

---

## 8. Laravel Events və Queued Listeners

```php
// Event
class OrderShipped {
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}

// Queued Listener — arxa fonda işləyir
class SendShipmentNotification implements ShouldQueue {
    public string $queue = 'notifications';
    public int $tries = 3;
    public int $backoff = 60;

    public function handle(OrderShipped $event): void {
        $event->order->user->notify(new ShipmentNotification($event->order));
    }

    public function failed(OrderShipped $event, Throwable $e): void {
        Log::error('Shipment notification failed', [
            'order' => $event->order->id,
            'error' => $e->getMessage(),
        ]);
    }
}

// Dispatch
OrderShipped::dispatch($order);

// Model Events
class User extends Model {
    protected static function booted(): void {
        static::creating(function (User $user) {
            $user->uuid = Str::uuid();
        });

        static::deleting(function (User $user) {
            $user->posts()->delete();
        });
    }
}

// Observer
class UserObserver {
    public function created(User $user): void { /* ... */ }
    public function updated(User $user): void { /* ... */ }
    public function deleted(User $user): void { /* ... */ }
}
```

---

## 9. Laravel Form Request Validation

```php
class StoreOrderRequest extends FormRequest {
    public function authorize(): bool {
        return $this->user()->can('create', Order::class);
    }

    public function rules(): array {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'shipping_address_id' => ['required', 'exists:addresses,id'],
            'coupon_code' => ['nullable', 'string', 'exists:coupons,code'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array {
        return [
            'items.required' => 'Ən azı bir məhsul seçilməlidir.',
            'items.*.product_id.exists' => 'Seçilmiş məhsul mövcud deyil.',
        ];
    }

    // Validation-dan əvvəl data-nı hazırla
    protected function prepareForValidation(): void {
        $this->merge([
            'coupon_code' => strtoupper($this->coupon_code ?? ''),
        ]);
    }

    // Custom validation rule
    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator) {
            if ($this->hasExpiredCoupon()) {
                $validator->errors()->add('coupon_code', 'Kupon müddəti bitib.');
            }
        });
    }
}
```

---

## 10. Laravel Resource / API Resource nədir?

Eloquent model-i JSON response-a çevirmək üçün transformation layer.

```php
class UserResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'posts_count' => $this->whenCounted('posts'),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'created_at' => $this->created_at->toISOString(),
            'is_admin' => $this->when($request->user()?->isAdmin(), $this->is_admin),
        ];
    }
}

// Collection Resource
class UserCollection extends ResourceCollection {
    public function toArray(Request $request): array {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_admins' => $this->collection->where('is_admin', true)->count(),
            ],
        ];
    }
}

// Controller-da
return new UserResource($user);
return UserResource::collection($users);
return new UserCollection(User::paginate());
```
