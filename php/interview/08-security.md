# Security (Senior)

## 1. SQL Injection nədir və necə qarşısı alınır?

```php
// Pis — SQL injection-a açıq
$users = DB::select("SELECT * FROM users WHERE email = '$email'");
// Hücumçu: ' OR 1=1 --

// Yaxşı — prepared statements (parameterized queries)
$users = DB::select("SELECT * FROM users WHERE email = ?", [$email]);

// Eloquent avtomatik qoruyur
$user = User::where('email', $email)->first();

// Raw sorğularda ehtiyatlı ol
// Pis
User::whereRaw("email = '$email'")->first();
// Yaxşı
User::whereRaw("email = ?", [$email])->first();

// Order by — injection riski var (sütun adları bind olmur)
// Pis
User::orderBy($request->input('sort'))->get();
// Yaxşı — whitelist ilə
$allowed = ['name', 'email', 'created_at'];
$sort = in_array($request->input('sort'), $allowed) ? $request->input('sort') : 'created_at';
User::orderBy($sort)->get();
```

---

## 2. XSS (Cross-Site Scripting) nədir?

```php
// Blade avtomatik escape edir
{{ $user->name }}  // htmlspecialchars() tətbiq olunur — TƏHLÜKƏSİZ

// Raw output — XSS riski!
{!! $user->bio !!}  // YA DA escape olunmur — EHTİYATLI OL

// User input-u raw göstərməlisənsə:
{!! clean($user->bio) !!}  // HTML Purifier istifadə et

// JavaScript-də
// Pis
<script>var name = "{!! $user->name !!}";</script>
// Yaxşı
<script>var name = @json($user->name);</script>

// Content Security Policy header əlavə et
// middleware:
$response->headers->set('Content-Security-Policy', "default-src 'self'");
```

---

## 3. CSRF (Cross-Site Request Forgery)

```php
// Laravel avtomatik CSRF token yoxlayır (VerifyCsrfToken middleware)

// Blade form-da
<form method="POST" action="/profile">
    @csrf
    <!-- ... -->
</form>

// AJAX-da (meta tag ilə)
<meta name="csrf-token" content="{{ csrf_token() }}">

// Axios avtomatik göndərir, ya da:
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
});

// API route-lar CSRF-dən azaddır (token-based auth istifadə edir)
```

---

## 4. Mass Assignment qoruması

```php
class User extends Model {
    // Yalnız bu sahələr mass-assign oluna bilər
    protected $fillable = ['name', 'email', 'password'];

    // VƏ YA əksi — bu sahələr mass-assign oluna BİLMƏZ
    protected $guarded = ['id', 'is_admin', 'role'];

    // Boş array = heç bir qoruma (təhlükəli!)
    protected $guarded = []; // bütün sahələr fillable olur
}

// İstifadə
$user = User::create($request->validated()); // TƏHLÜKƏSİZ — validated data
$user = User::create($request->all());       // TƏHLÜKƏLİ — bütün input

// Best practice — həmişə validated() istifadə et
public function store(StoreUserRequest $request): JsonResponse {
    $user = User::create($request->validated());
    return new UserResource($user);
}
```

---

## 5. Authentication və Authorization

```php
// Authorization — Policies
class PostPolicy {
    public function update(User $user, Post $post): bool {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool {
        return $user->id === $post->user_id || $user->isAdmin();
    }

    // before — bütün yoxlamalardan əvvəl
    public function before(User $user, string $ability): ?bool {
        if ($user->isSuperAdmin()) {
            return true; // super admin hər şeyi edə bilər
        }
        return null; // digər yoxlamalara davam et
    }
}

// Controller-da
public function update(Request $request, Post $post): JsonResponse {
    $this->authorize('update', $post);
    // və ya
    Gate::authorize('update', $post);
    // ...
}

// Blade-da
@can('update', $post)
    <a href="{{ route('posts.edit', $post) }}">Redaktə et</a>
@endcan

// Password hashing
$hashed = Hash::make($password);                    // bcrypt default
Hash::check($plainPassword, $hashedPassword);       // yoxlama
```

---

## 6. Encryption və Data Protection

```php
// Laravel Encryption (AES-256-CBC)
$encrypted = Crypt::encryptString('sensitive data');
$decrypted = Crypt::decryptString($encrypted);

// Model Casting ilə avtomatik
protected function casts(): array {
    return [
        'ssn' => 'encrypted',
        'api_key' => 'encrypted:string',
    ];
}

// .env faylını qorumaq
// Heç vaxt .env-ni git-ə commit etmə
// .gitignore-da .env olduğunu yoxla
// Production-da environment variables istifadə et

// Sensitive data logging-dən gizlət
// config/logging.php — sensitive fields mask et
// Request-də:
protected $hidden = ['password', 'remember_token', 'api_key'];
```

---

## 7. Rate Limiting və Brute Force qoruması

```php
// Login rate limiting
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->input('email') . '|' . $request->ip()),
    ];
});

// API rate limiting headers
// X-RateLimit-Limit: 60
// X-RateLimit-Remaining: 58
// Retry-After: 30 (limit aşıldıqda)
```

---

## 8. CORS (Cross-Origin Resource Sharing)

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_origins' => ['https://frontend.example.com'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 86400,
    'supports_credentials' => true,
];
```
