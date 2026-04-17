# REST API (Representational State Transfer)

## Nədir? (What is it?)

REST Roy Fielding terefinden 2000-ci ilde doktora dissertasiyasinda teqdim edilmis arxitektural stildir. REST API HTTP protokolu uzerinde isleyen, resource-oriented web servisleridir. Bu gun internet-deki API-larin boyuk ekseriyyeti REST prinsipleri uzerine qurulub.

REST bir **protokol deyil**, arxitektural **constraint**-ler mecmusudur.

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

## Necə İşləyir? (How does it work?)

### REST 6 Constraint-i

1. **Client-Server** - Client ve server bir-birinden asılı deyil
2. **Stateless** - Her request butun lazimi melumati oz icinde saxlayir
3. **Cacheable** - Response-lar cache oluna biler
4. **Uniform Interface** - Vahid interface (resource identification, manipulation through representations, self-descriptive messages, HATEOAS)
5. **Layered System** - Client ara layer-lerin oldugunu bilmir (proxy, LB)
6. **Code on Demand** (optional) - Server client-e executable code gondere biler

### HTTP Methods ve CRUD

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
* PATCH idempotent ola biler, amma specification bunu teleb etmir
```

### Resource Naming Conventions

```
# Yaxsi - plural nouns
GET    /api/users
GET    /api/users/42
GET    /api/users/42/posts
GET    /api/users/42/posts/7

# Pis - verbs istifade etmeyin
GET    /api/getUsers          ✗
POST   /api/createUser        ✗
DELETE /api/deleteUser/42     ✗

# Pis - singular noun
GET    /api/user/42           ✗

# Nested resources (2 seviyyeden cox olmamali)
GET    /api/users/42/posts/7/comments    ✓ (max depth)
GET    /api/users/42/posts/7/comments/3/replies  ✗ (cox deep)

# Bunu edin:
GET    /api/comments?post_id=7           ✓

# Actions (non-CRUD emeliyyatlar)
POST   /api/users/42/activate            ✓
POST   /api/orders/99/cancel             ✓
```

### HTTP Status Codes

```
2xx - Success
  200 OK              - Ugurlu GET, PUT, PATCH
  201 Created         - Ugurlu POST (Location header ile)
  204 No Content      - Ugurlu DELETE

3xx - Redirection
  301 Moved Permanently
  304 Not Modified    - Cache ucun

4xx - Client Error
  400 Bad Request     - Validation error
  401 Unauthorized    - Authentication lazimdir
  403 Forbidden       - Authorization yoxdur
  404 Not Found       - Resource tapilmadi
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

## Əsas Konseptlər (Key Concepts)

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
# URI versioning (en cox istifade olunan)
GET /api/v1/users
GET /api/v2/users

# Header versioning
GET /api/users
Accept: application/vnd.myapp.v2+json

# Query parameter
GET /api/users?version=2
```

## PHP/Laravel ilə İstifadə

### Route Definition

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

### Controller

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

### API Resource (Response Transformation)

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

### API Resource Collection

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

### Form Request (Validation)

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'in:user,admin,editor'],
        ];
    }

    /**
     * Validation ugursuz olanda JSON response qaytarir
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator,
            response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
```

### Error Handling

```php
// app/Exceptions/Handler.php (Laravel 10)
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

## Interview Sualları

### 1. REST nedir? SOAP-dan ferqi nedir?
**Cavab:** REST resource-oriented arxitektural stildir, HTTP uzerinde isleyir, JSON/XML istifade edir, lightweight-dir. SOAP protokoldur, XML-based-dir, WSDL lazimdir, daha complex-dir. REST daha sadedir ve web/mobile API-lar ucun standartdir.

### 2. Idempotent ne demekdir? Hansi HTTP methodlar idempotent-dir?
**Cavab:** Eyni request-i bir ve ya bir nece defe gondermek eyni neticeni verir. GET, PUT, DELETE idempotent-dir. POST idempotent deyil - her POST yeni resource yaradır.

### 3. PUT ve PATCH arasinda ferq nedir?
**Cavab:** PUT resource-un **tam** replacement-idir - butun field-leri gondermek lazimdir. PATCH **qismi** yenilemedir - yalniz deyisen field-leri gonderirsiniz. Meselen: PUT-da name+email+role gonderirsiniz, PATCH-da yalniz name gondere bilersiniz.

### 4. 401 ve 403 arasinda ferq nedir?
**Cavab:** **401 Unauthorized** - authentication problem, kimliyin bilinmir (login olmamisan). **403 Forbidden** - authorization problem, kimlyin bilinir amma icazen yoxdur (login olmusan amma admin deyilsen).

### 5. HATEOAS nedir?
**Cavab:** Hypermedia as the Engine of Application State - API response-unda novbeti mumkun emeliyyatlarin linklerini qaytarmaq. Client hardcode edilmis URL-ler yerine response-daki linkleri istifade edir. Real dunyada tam implement olunmasi nadirdir.

### 6. Stateless ne demekdir ve niye vacibdir?
**Cavab:** Her request ozunde butun lazimi melumati saxlayir, server client-in state-ini yadda saxlamır. Bu scaling-i asanlashdırır (her server her request-e cavab vere biler), reliability artir, caching mumkun olur. Authentication ucun JWT/token istifade olunur.

### 7. API versioning nece edilir? Hansi yol daha yaxsidir?
**Cavab:** 3 usul var: URI versioning (/v1/users), Header versioning (Accept header), Query param (?version=1). URI versioning en sade ve en populyardır. Header versioning daha "RESTful"-dur amma debug etmek cetindir.

### 8. N+1 problem API-da nece hell olunur?
**Cavab:** Eager loading istifade edin: `User::with('posts')->paginate()`. Laravel-de `whenLoaded()` ile yalniz eager load olunmus relation-lari API response-a elave edin. API-da `?include=posts,profile` parametri ile client istediyi relation-lari sorusha biler.

## Best Practices

1. **Consistent response format** - Hemise eyni struktur: `{data, meta, links, errors}`
2. **Plural nouns** - `/users` not `/user`
3. **HTTP status codes duzgun istifade edin** - 200, 201, 204, 400, 404, 422
4. **Versioning** - Ilk gunden versioning elave edin
5. **Pagination default** - Butun list endpoint-lere pagination elave edin
6. **Rate limiting** - API-ni suistifadeden qoruyun
7. **Input validation** - Her request-i validate edin
8. **Error messages** - Human-readable, actionable error mesajlari
9. **Documentation** - OpenAPI/Swagger ile API-ni dokumentlashdirin
10. **HATEOAS** - En azi self link qaytarin
