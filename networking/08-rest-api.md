# REST API (Junior)

## İcmal

REST Roy Fielding tərəfindən 2000-ci ildə doktora dissertasiyasında təqdim edilmiş arxitektural stildir. REST API HTTP protokolu üzərində işləyən, resource-oriented web servislərdir. Bu gün internetdəki API-ların böyük əksəriyyəti REST prinsipləri üzərinə qurulub.

REST bir **protokol deyil**, arxitektural **constraint**-lər məcmusudur.

```
Client                          Server
  |                               |
  |  GET /api/users/42            |
  |  Accept: application/json     |
  |------------------------------>|
  |                               |
  |  200 OK                       |
  |  Content-Type: application/json
  |  {"id": 42, "name": "Orkhan"} |
  |<------------------------------|
```

## Niyə Vacibdir

REST API dizaynı backend developer-in əsas bacarıqlarından biridir. Düzgün resource naming, HTTP method seçimi, status code istifadəsi API-nın intuitivliyini müəyyən edir. Versioning strategiyası breaking change-lərin idarə edilməsinə imkan verir. Pagination, filtering, sorting backend-in gündəlik işidir. Zəif REST API dizaynı — yanlış status kod, həddindən artıq nested URL, pagination olmaması — client developer-lərlə münaqişəyə səbəb olur və production-da scale problemlər yaradır.

## Əsas Anlayışlar

### REST 6 Constraint-i

1. **Client-Server** — Client və server bir-birindən asılı deyil
2. **Stateless** — Hər request bütün lazımi məlumatı öz içində saxlayır
3. **Cacheable** — Response-lar cache oluna bilər
4. **Uniform Interface** — Vahid interface (resource identification, manipulation through representations, self-descriptive messages, HATEOAS)
5. **Layered System** — Client ara layer-lərin olduğunu bilmir (proxy, LB)
6. **Code on Demand** (optional) — Server client-ə executable code göndərə bilər

### HTTP Methods və CRUD

```
+----------+------------------+-------------------+--------------+
| Method   | CRUD             | URI Example       | Idempotent?  |
+----------+------------------+-------------------+--------------+
| GET      | Read             | /api/users        | Yes          |
| GET      | Read (single)    | /api/users/42     | Yes          |
| POST     | Create           | /api/users        | No           |
| PUT      | Update (full)    | /api/users/42     | Yes          |
| PATCH    | Update (partial) | /api/users/42     | No*          |
| DELETE   | Delete           | /api/users/42     | Yes          |
+----------+------------------+-------------------+--------------+
* PATCH idempotent ola bilər, amma specification bunu tələb etmir
```

### Resource Naming Conventions

```
# Yaxşı - plural nouns
GET    /api/users
GET    /api/users/42
GET    /api/users/42/posts
GET    /api/users/42/posts/7

# Pis - verbs istifadə etməyin
GET    /api/getUsers          ✗
POST   /api/createUser        ✗
DELETE /api/deleteUser/42     ✗

# Pis - singular noun
GET    /api/user/42           ✗

# Nested resources (2 səviyyədən çox olmamalı)
GET    /api/users/42/posts/7/comments    ✓ (max depth)
GET    /api/users/42/posts/7/comments/3/replies  ✗ (çox deep)

# Bunu edin:
GET    /api/comments?post_id=7           ✓

# Actions (non-CRUD əməliyyatlar)
POST   /api/users/42/activate            ✓
POST   /api/orders/99/cancel             ✓
```

### HTTP Status Codes

```
2xx - Success
  200 OK              - Uğurlu GET, PUT, PATCH
  201 Created         - Uğurlu POST (Location header ilə)
  204 No Content      - Uğurlu DELETE

3xx - Redirection
  301 Moved Permanently
  304 Not Modified    - Cache üçün

4xx - Client Error
  400 Bad Request     - Validation error
  401 Unauthorized    - Authentication lazımdır
  403 Forbidden       - Authorization yoxdur
  404 Not Found       - Resource tapılmadı
  405 Method Not Allowed
  409 Conflict        - Resource conflict
  422 Unprocessable Entity - Validation error (Laravel default)
  429 Too Many Requests - Rate limit

5xx - Server Error
  500 Internal Server Error
  502 Bad Gateway
  503 Service Unavailable
```

### HATEOAS (Hypermedia as the Engine of Application State)

```json
{
  "data": {
    "id": 42,
    "name": "Orkhan",
    "email": "orkhan@example.com"
  },
  "_links": {
    "self": {"href": "/api/users/42"},
    "posts": {"href": "/api/users/42/posts"},
    "edit": {"href": "/api/users/42", "method": "PUT"},
    "delete": {"href": "/api/users/42", "method": "DELETE"}
  }
}
```

### Pagination

```
# Offset-based
GET /api/users?page=2&per_page=15

# Cursor-based
GET /api/users?cursor=eyJpZCI6NDJ9&limit=15
```

### Filtering, Sorting, Searching

```
# Filtering
GET /api/users?status=active&role=admin

# Sorting
GET /api/users?sort=created_at&order=desc
GET /api/users?sort=-created_at,name   (- = desc)

# Searching
GET /api/users?search=orkhan
GET /api/users?q=orkhan

# Field selection (sparse fieldsets)
GET /api/users?fields=id,name,email
```

### API Versioning

```
# URI versioning (ən çox istifadə olunan)
GET /api/v1/users
GET /api/v2/users

# Header versioning
GET /api/users
Accept: application/vnd.myapp.v2+json

# Query parameter
GET /api/users?version=2
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- `Location` header-i `201 Created` cavabında yeni resource-un URL-ini göstərir — client ayrıca fetch etmədən URL-i bilir
- Cursor-based pagination böyük dataset-lərdə offset-based-dən effektivdir — offset-based-in sonrakı səhifələrdə performansı düşür
- `sparse fieldsets` (`?fields=id,name`) yavaş mobile internet üçün bandwidth xərclərini azaldır

**Trade-off-lar:**
- URI versioning: sadə, debug rahat; amma URL-i "çirklədirir"
- Header versioning: "daha RESTful"; amma curl ilə test etmək çətin, CDN keşləmə mürəkkəbdir
- HATEOAS: tam implementation nadirdir — overhead-i faydasından çox ola bilər; minimum `self` link lazımdır

**Common mistakes:**
- POST-u update üçün istifadə etmək (idempotency itirilir, retry-da problem yaranır)
- Hər şey üçün 200 qaytarmaq — client-ə mənasız cavab, debug çətin
- Validation xətasını 400 ilə qaytarmaq (Laravel default 422-dir; 400 format xətası üçündür)
- Pagination olmadan list endpoint-i — böyük data-da timeout, memory issue

**Anti-pattern:** `/api/getUser`, `/api/createUserEndpoint` — URL-də verb istifadəsi REST semantikasını pozur; HTTP method-un özü artıq feli ifadə edir.

## Nümunələr

### Ümumi Nümunə

Bir REST API-nin tam resource lifecycle-ı:

```
POST   /api/v1/users           -> 201 Created (+ Location: /api/v1/users/42)
GET    /api/v1/users/42        -> 200 OK
PATCH  /api/v1/users/42        -> 200 OK
DELETE /api/v1/users/42        -> 204 No Content

GET    /api/v1/users           -> 200 OK (paginated list)
GET    /api/v1/users?status=active&sort=-created_at -> 200 OK (filtered)

POST   /api/v1/users/42/activate -> 200 OK (custom action)
```

### Kod Nümunəsi

Route Definition:

```php
// routes/api.php
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\PostController;

Route::prefix('v1')->group(function () {
    // Resource routes (GET, POST, PUT/PATCH, DELETE avtomatik)
    Route::apiResource('users', UserController::class);
    Route::apiResource('users.posts', PostController::class)->shallow();

    // Custom actions
    Route::post('users/{user}/activate', [UserController::class, 'activate']);
});
```

Controller:

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * GET /api/v1/users
     */
    public function index(Request $request): UserCollection
    {
        $users = User::query()
            ->when($request->search, fn($q, $search) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when($request->status, fn($q, $status) =>
                $q->where('status', $status)
            )
            ->when($request->sort, function ($q, $sort) {
                $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
                $column = ltrim($sort, '-');
                $q->orderBy($column, $direction);
            })
            ->paginate($request->per_page ?? 15);

        return new UserCollection($users);
    }

    /**
     * POST /api/v1/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('users.show', $user));
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user): UserResource
    {
        $user->load(['posts', 'profile']);

        return new UserResource($user);
    }

    /**
     * PUT /api/v1/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $user->update($request->validated());

        return new UserResource($user);
    }

    /**
     * DELETE /api/v1/users/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/users/{user}/activate
     */
    public function activate(User $user): UserResource
    {
        $user->update(['status' => 'active', 'activated_at' => now()]);

        return new UserResource($user);
    }
}
```

API Resource (Response Transformation):

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'avatar_url' => $this->avatar_url,
            'posts_count' => $this->whenCounted('posts'),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            '_links' => [
                'self' => route('users.show', $this->id),
                'posts' => route('users.posts.index', $this->id),
            ],
        ];
    }
}
```

Error Handling:

```php
// bootstrap/app.php (Laravel 11)
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'Resource not found',
            ], 404);
        }
    });

    $exceptions->render(function (\Throwable $e, Request $request) {
        if ($request->is('api/*')) {
            $status = method_exists($e, 'getStatusCode')
                ? $e->getStatusCode()
                : 500;

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $status,
            ], $status);
        }
    });
});
```

## Praktik Tapşırıqlar

**Tapşırıq 1: REST API dizaynı**

Aşağıdakı əməliyyatlar üçün düzgün endpoint-lər dizayn edin:

```
- Blog post-larını listlə
- Yeni comment əlavə et
- Məqaləni arxivlə
- İstifadəçinin şifrəsini sıfırla
- Sifarişi ləğv et
- Məhsulları kateqoriyaya görə filtrələ
```

Cavab nümunəsi:
```
GET    /api/v1/posts
POST   /api/v1/posts/{post}/comments
POST   /api/v1/posts/{post}/archive
POST   /api/v1/users/{user}/password-reset
POST   /api/v1/orders/{order}/cancel
GET    /api/v1/products?category=electronics&sort=-price
```

**Tapşırıq 2: API Resource Collection implement edin**

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }
}
```

Test: `GET /api/v1/users?page=2&per_page=5&status=active&sort=-created_at`

**Tapşırıq 3: N+1 problemini həll edin**

Aşağıdakı endpoint-i optimallaşdırın:

```php
// Problem: N+1 query
public function index(): UserCollection
{
    $users = User::paginate(15); // N users
    // UserResource-da hər user üçün $this->posts -> N query!
    return new UserCollection($users);
}

// Həll: Eager loading
public function index(Request $request): UserCollection
{
    $users = User::with(['posts', 'profile'])
        ->withCount('posts')
        ->paginate(15);

    return new UserCollection($users);
}
```

## Əlaqəli Mövzular

- [HTTP Protocol](05-http-protocol.md)
- [GraphQL](09-graphql.md)
- [API Versioning](22-api-versioning.md)
- [API Pagination](24-api-pagination.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [API Security](17-api-security.md)
- [OpenAPI/Swagger](38-openapi-swagger.md)
