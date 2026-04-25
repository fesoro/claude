# API Versioning (Middle)

## İcmal

API versioning, API-nin müxtəlif versiyalarını eyni zamanda dəstəkləmək üçün istifadə olunan strategiyadır. API dəyişiklikləri mövcud client-ləri sındırmamalıdır (backward compatibility). Breaking change lazım olanda yeni versiya yaradılıb köhnə versiya müəyyən müddət saxlanılır.

```
Breaking Change nümunələri:
  - Field silinməsi: response-dan "phone" field götürüldü
  - Field tipi dəyişməsi: "age": "25" -> "age": 25
  - Endpoint silinməsi: DELETE /api/users/{id}/avatar götürüldü
  - Required field əlavə: indi "phone" məcburi
  - Response structure dəyişməsi: {data: [...]} -> [...]

Non-Breaking Change nümunələri:
  - Yeni optional field əlavə: "avatar_url" əlavə olundu
  - Yeni endpoint əlavə: POST /api/users/{id}/verify
  - Yeni optional query param: ?include=posts
```

## Niyə Vacibdir

Real layihələrdə API-lər zaman keçdikcə inkişaf edir. Breaking change etmədən inkişaf etdirmək həmişə mümkün olmur. Versioning olmadan ya köhnə client-lər sınır, ya da API heç vaxt yaxşılaşdırıla bilmir. Düzgün versioning strategiyası client-lərə migration üçün vaxt verərək API-nin inkişafına imkan yaradır.

## Əsas Anlayışlar

### Versioning Strategiyaları

```
1. URI Versioning (ən populyar):
   GET /api/v1/users
   GET /api/v2/users

2. Header Versioning:
   GET /api/users
   Accept: application/vnd.myapp.v2+json
   X-API-Version: 2

3. Query Parameter Versioning:
   GET /api/users?version=2

4. Content Negotiation:
   GET /api/users
   Accept: application/vnd.myapp.users.v2+json

5. No Versioning (Schema Evolution):
   Əvvəlki field-ləri saxla, yeni field əlavə et, deprecated marker ilə
```

### Hər Birinin Üstünlükləri və Mənfi Tərəfləri

```
URI Versioning:
  + Sadədir, görüntülü
  + Cache-friendly (URL fərqlidir)
  + Postman/browser ilə test asan
  - URL dəyişir, REST prinsipinə zidd
  - Copy-paste zamanı versiya unudula bilər

Header Versioning:
  + URL təmiz qalır
  + Daha RESTful
  - Debug çətindir (Postman-da header əlavə etmək lazım)
  - Cache key-ə header daxil etmək lazım (Vary header)

Query Parameter:
  + Sadədir
  + Default versiya təyin oluna bilər
  - Cache key-də ?version=X olmalıdır
  - URL-də əlavə parametr

Schema Evolution (versioning olmadan):
  + Sadə, versiya idarəetməsi yoxdur
  + GraphQL-in yanaşması
  - Çox dəyişiklikdə çətinləşir
```

### Deprecation Strategy

```
Timeline:
  v1 Released    -> v2 Released -> v1 Deprecated -> v1 Sunset
  |              |              |                |
  |-- 12 month --|-- 6 month --|--- 6 month ----|
                                                  v1 bağlanır

Deprecation Headers:
  Deprecation: true
  Sunset: Sat, 01 Jan 2027 00:00:00 GMT
  Link: <https://api.example.com/v2/users>; rel="successor-version"

Response body:
{
  "data": [...],
  "_deprecation": {
    "message": "v1 will be removed on 2027-01-01. Please migrate to v2.",
    "sunset_date": "2027-01-01",
    "migration_guide": "https://docs.example.com/migration/v1-to-v2"
  }
}
```

### Semantic Versioning for APIs

```
MAJOR.MINOR.PATCH
  v2.3.1

MAJOR - Breaking changes (v1 -> v2)
MINOR - New features, backward compatible (v2.1 -> v2.2)
PATCH - Bug fixes (v2.2.0 -> v2.2.1)

API URL-də adətən yalnız MAJOR version istifadə olunur:
  /api/v1/...
  /api/v2/...
```

## Praktik Baxış

**Trade-off-lar:**
- URI versioning ən populyardır — sadə, görüntülü, debug asan. Amma URL dəyişir ki, bu REST prinsipinə zidd hesab olunur.
- Header versioning daha RESTful, amma debug çətin, caching üçün `Vary` header lazım
- Nə qədər az versiya saxlanılsa, o qədər az maintenance overhead

**Nə vaxt istifadə edilməməlidir:**
- Breaking change etməmək ən yaxşı seçimdir — schema evolution ilə (additive change) versioning-dən qaçmaq olar
- GraphQL-də versioning yoxdur, `@deprecated` directive istifadə olunur

**Anti-pattern-lər:**
- Çox sayda versiyani paralel saxlamaq (max 2-3 tövsiyə olunur)
- Deprecation xəbərdarlığı vermədən birbaşa bağlamaq
- Sunset date-i çox qısa saxlamaq (minimum 6 ay)
- Version analytics aparmamaq — kim hələ köhnə versiyadadır bilmək lazımdır

## Nümunələr

### Ümumi Nümunə

URI versioning ilə hər versiya üçün ayrı Controller və Resource class-ları yaradılır (`Api/V1/`, `Api/V2/`). Folder strukturu versiyaları açıqca görüntüləyir. Deprecation middleware köhnə versiyalara avtomatik warning header-ləri əlavə edir.

### Kod Nümunəsi

**URI Versioning (Laravel Routes):**

```php
// routes/api.php

// V1 Routes
Route::prefix('v1')->group(function () {
    Route::apiResource('users', \App\Http\Controllers\Api\V1\UserController::class);
    Route::apiResource('posts', \App\Http\Controllers\Api\V1\PostController::class);
});

// V2 Routes
Route::prefix('v2')->group(function () {
    Route::apiResource('users', \App\Http\Controllers\Api\V2\UserController::class);
    Route::apiResource('posts', \App\Http\Controllers\Api\V2\PostController::class);
});

// Folder structure:
// app/Http/Controllers/Api/V1/UserController.php
// app/Http/Controllers/Api/V2/UserController.php
// app/Http/Resources/V1/UserResource.php
// app/Http/Resources/V2/UserResource.php
```

**V1 vs V2 Resource fərqi:**

```php
// V1 Resource
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,       // Tək "name" field
            'email'      => $this->email,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

// V2 Resource — Breaking change: name -> first_name + last_name
namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

**Header-Based Versioning Middleware:**

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        $version = $request->header('X-API-Version')
            ?? $request->header('Accept-Version')
            ?? $this->parseAcceptHeader($request)
            ?? config('api.default_version', '1');

        $request->attributes->set('api_version', (int) $version);

        return $next($request);
    }

    private function parseAcceptHeader(Request $request): ?string
    {
        $accept = $request->header('Accept', '');
        // application/vnd.myapp.v2+json
        if (preg_match('/vnd\.myapp\.v(\d+)/', $accept, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
```

**Version-Aware Controller:**

```php
namespace App\Http\Controllers\Api;

use App\Http\Resources\V1\UserResource as V1UserResource;
use App\Http\Resources\V2\UserResource as V2UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request, User $user)
    {
        $version = $request->attributes->get('api_version', 1);

        return match ($version) {
            1       => new V1UserResource($user),
            2       => new V2UserResource($user),
            default => new V2UserResource($user),
        };
    }

    public function index(Request $request)
    {
        $version = $request->attributes->get('api_version', 1);
        $users = User::paginate();

        return match ($version) {
            1       => V1UserResource::collection($users),
            2       => V2UserResource::collection($users),
            default => V2UserResource::collection($users),
        };
    }
}
```

**Deprecation Middleware:**

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeprecatedApiVersion
{
    private array $deprecated = [
        'v1' => '2027-01-01',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $version = $request->segment(2); // /api/v1/...

        if (isset($this->deprecated[$version])) {
            $sunsetDate = $this->deprecated[$version];

            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', date('D, d M Y H:i:s', strtotime($sunsetDate)) . ' GMT');
            $response->headers->set('Link', '<https://api.example.com/v2/>; rel="successor-version"');

            if ($response->headers->get('Content-Type') === 'application/json') {
                $data = json_decode($response->getContent(), true);
                $data['_deprecation'] = [
                    'warning'         => "API {$version} is deprecated and will be removed on {$sunsetDate}",
                    'migration_guide' => 'https://docs.example.com/migration',
                ];
                $response->setContent(json_encode($data));
            }
        }

        return $response;
    }
}

// routes/api.php
Route::prefix('v1')->middleware(['api', 'deprecated'])->group(function () {
    // Köhnə v1 route-ları
});
```

**API Version Monitoring:**

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TrackApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $version = $request->segment(2) ?? 'unknown';

        Log::channel('api-analytics')->info('API Version Usage', [
            'version'   => $version,
            'path'      => $request->path(),
            'client_id' => $request->user()?->id ?? $request->header('X-API-Key'),
            'user_agent'=> $request->userAgent(),
        ]);

        return $response;
    }
}
```

## Praktik Tapşırıqlar

1. **V1 → V2 migration:** `UserResource` üçün V1 (tək `name`) və V2 (ayrı `first_name` + `last_name`) resource-ları yaradın. Laravel artisan ilə `php artisan make:resource V2/UserResource` edin. Hər iki versiyada eyni model-dən fərqli format qaytardığını yoxlayın.

2. **Deprecation middleware:** `DeprecatedApiVersion` middleware-ini v1 route qrupuna əlavə edin. `curl -I https://api.example.com/api/v1/users` ilə `Deprecation: true` və `Sunset` headerlərinin gəldiyini yoxlayın.

3. **Version analytics:** `TrackApiVersion` middleware-ini aktiv edin. Log-ları analiz edib hansı client-lərin hələ v1 istifadə etdiyini tapın. Bu client-lərə migration guide göndərməyin planını qurun.

4. **Header-based versioning:** `X-API-Version` header-ini dəstəkləyən middleware yazın. Postman-da `X-API-Version: 1` və `X-API-Version: 2` header-ləri ilə eyni endpoint-ə sorğu göndərib fərqli response format-larını yoxlayın.

5. **Non-breaking change practice:** Mövcud v1 endpoint-ə yeni optional field əlavə edin (məsələn `avatar_url`). `null` dəyər qaytarın, client-lərin sınmadığını yoxlayın — bu additive change-in gücünü göstərir.

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [API Gateway](21-api-gateway.md)
- [API Security](17-api-security.md)
- [GraphQL](09-graphql.md)
- [OpenAPI / Swagger](38-openapi-swagger.md)
