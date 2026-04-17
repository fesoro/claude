# API Versioning

## Nədir? (What is it?)

API versioning API-nin muxtelif versiyalarini eyni zamanda desdeklemek ucun istifade olunan strategiyadir. API deyisiklikleri movcud client-leri sindirmamalidir (backward compatibility). Breaking change lazim olanda yeni versiya yaradilib kohne versiya mueyyyen muddet saxlanir.

```
Breaking Change numuneleri:
  - Field silinmesi: response-dan "phone" field goturuldu
  - Field tipi deyismesi: "age": "25" -> "age": 25
  - Endpoint silinmesi: DELETE /api/users/{id}/avatar goturuldu
  - Required field elave: indi "phone" mecburi
  - Response structure deyismesi: {data: [...]} -> [...]

Non-Breaking Change numuneleri:
  - Yeni optional field elave: "avatar_url" elave olundu
  - Yeni endpoint elave: POST /api/users/{id}/verify
  - Yeni optional query param: ?include=posts
```

## Necə İşləyir? (How does it work?)

### Versioning Strategiyalari

```
1. URI Versioning (en populyar):
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
   Evvelki field-leri saxla, yeni field elave et, deprecated markerla
```

### Herbirinin Ustulukleri ve Menfi Terefleri

```
URI Versioning:
  + Sadedir, goruntulu
  + Cache-friendly (URL ferqlidir)
  + Postman/browser ile test asan
  - URL deyisir, REST prinsipine zidd
  - Copy-paste zamani versiya unudula biler

Header Versioning:
  + URL temiz qalir
  + Daha RESTful
  - Debug cetindir (Postman-da header elave etmek lazim)
  - Cache key-e header daxil etmek lazim (Vary header)

Query Parameter:
  + Sadedir
  + Default versiya teyin oluna biler
  - Cache key-de ?version=X olmalidir
  - URL-de elave parametr

Schema Evolution (versioning olmadan):
  + Sade, versiya idareetmesi yoxdur
  + GraphQL-in yanasmasi
  - Cox deyisiklikde cetinlesir
```

## Əsas Konseptlər (Key Concepts)

### Deprecation Strategy

```
Timeline:
  v1 Released    -> v2 Released -> v1 Deprecated -> v1 Sunset
  |              |              |                |
  |-- 12 month --|-- 6 month --|--- 6 month ----|
                                                  v1 baglanir

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

API URL-de adeten yalniz MAJOR version istifade olunur:
  /api/v1/...
  /api/v2/...
```

## PHP/Laravel ilə İstifadə

### URI Versioning (Laravel)

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

### V1 vs V2 Resource Ferqi

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
            'id' => $this->id,
            'name' => $this->name,       // Tek "name" field
            'email' => $this->email,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

// V2 Resource - Breaking change: name -> first_name + last_name
namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,   // Ayrildi
            'last_name' => $this->last_name,      // Ayrildi
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,     // Yeni field
            'created_at' => $this->created_at->toISOString(), // Format deyisdi
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

### Header-Based Versioning

```php
// app/Http/Middleware/ApiVersion.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersion
{
    public function handle(Request $request, Closure $next)
    {
        // Header-den version oxu
        $version = $request->header('X-API-Version')
            ?? $request->header('Accept-Version')
            ?? $this->parseAcceptHeader($request)
            ?? config('api.default_version', '1');

        // Version-u request-e elave et
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

### Version-Aware Controller

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
            1 => new V1UserResource($user),
            2 => new V2UserResource($user),
            default => new V2UserResource($user),
        };
    }

    public function index(Request $request)
    {
        $version = $request->attributes->get('api_version', 1);
        $users = User::paginate();

        return match ($version) {
            1 => V1UserResource::collection($users),
            2 => V2UserResource::collection($users),
            default => V2UserResource::collection($users),
        };
    }
}
```

### Deprecation Middleware

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

        // Current version
        $version = $request->segment(2); // /api/v1/...

        if (isset($this->deprecated[$version])) {
            $sunsetDate = $this->deprecated[$version];

            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', date('D, d M Y H:i:s', strtotime($sunsetDate)) . ' GMT');
            $response->headers->set('Link', '<https://api.example.com/v2/>; rel="successor-version"');

            // Response body-ye warning elave et
            if ($response->headers->get('Content-Type') === 'application/json') {
                $data = json_decode($response->getContent(), true);
                $data['_deprecation'] = [
                    'warning' => "API {$version} is deprecated and will be removed on {$sunsetDate}",
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
    // Kohne v1 route-lari
});
```

### API Version Monitoring

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

        // Hansi versiyalar istifade olunur izle
        Log::channel('api-analytics')->info('API Version Usage', [
            'version' => $version,
            'path' => $request->path(),
            'client_id' => $request->user()?->id ?? $request->header('X-API-Key'),
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }
}
```

## Interview Sualları

### 1. API versioning niye lazimdir?
**Cavab:** Breaking change-ler movcud client-leri sindirmasin deye. Yeni funksionalliq elave etmek ve ya strukturu deyismek lazim olanda yeni versiya yaradilir, kohne versiya mueyyyen muddet saxlanir ki client-ler kocmek ucun vaxt olsun.

### 2. Hansi versioning strategiyasi daha yaxsidir?
**Cavab:** URI versioning (/v1/) en populyardır - sadedir, goruntulu, debug asandır. Header versioning daha RESTful-dur amma cetindir. GraphQL-de versioning yoxdur - schema evolution istifade olunur. Secim layihenin ehtiyaclarina baglıdır.

### 3. Breaking change nedir?
**Cavab:** Movcud client-leri sindiren deyisiklik: field silmek, field tipi deyismek, endpoint silmek, required field elave etmek, response strukturu deyismek. Non-breaking: yeni optional field, yeni endpoint, yeni optional parametr.

### 4. Deprecation prosesi nece olmalidir?
**Cavab:** 1) Yeni versiyani release edin, 2) Kohne versiyaya Deprecation header elave edin, 3) Client-lere bildirin ve migration guide hazirlayin, 4) Sunset date teyin edin (en az 6 ay), 5) Analytics ile kimlerin hala kohne versiyadir izleyin, 6) Sunset date-de baglayın.

### 5. Bir API-nin nece versiyasini eyni anda saxlammaq lazimdir?
**Cavab:** Maksimum 2-3. Daha cox versiya maintain etmek cetindir ve resource teleb edir. Adeten: current (v3), previous (v2, deprecated), ve nadir hallarda v1 (sunset date yaxin).

### 6. GraphQL-de versioning nece edilir?
**Cavab:** GraphQL-de versioning yoxdur. Evezine schema evolution: yeni field elave et, kohne field-i `@deprecated(reason: "Use newField instead")` ile isarele. Client ozune lazim olan field-leri secdiyi ucun kohne field-in olmasi problem yaratmır.

## Best Practices

1. **Ilk gunden versioning** - /api/v1/ ile bashlayin
2. **Yalniz MAJOR version** - URL-de v1, v2 (v1.2 deyil)
3. **Minimum breaking change** - Additive change tercih edin
4. **Deprecation timeline** - En az 6 ay xeberdarliq
5. **Migration guide** - Her versiya keciidi ucun dokumentasiya
6. **Analytics** - Hansi versiya ne qeder istifade olunur izleyin
7. **Sunset header** - Deprecated versiyalara Sunset date elave edin
8. **Default version** - Version gosterilmezse en son stable version
9. **Changelog** - Her versiya ucun deyisiklikler siyahisi
10. **Client notification** - Email/webhook ile client-lere bildirin
