# API Versioning Strategies (Middle ⭐⭐)

## İcmal

API versioning — mövcud client-ləri pozmadan API-da dəyişiklik etməyin sistematik yanaşmasıdır. Public API-lər, mobil app backend-ləri, ya da third-party integration nöqtələri olan hər sistemdə versioning mütləq düşünülməlidir. "Breaking change" — backward incompatible dəyişiklik deməkdir: field silmək, type dəyişmək, endpoint adını dəyişmək kimi. Interview-larda bu mövzu API dizayn suallarının ayrılmaz hissəsidir.

## Niyə Vacibdir

Versioning olmadan API evolyusiyası impossible olur. Köhnə mobile app-lar hələ istifadəçilərin telefonundadır, third-party integration-lar API-nizi dəyişmədən işlədiyini güman edir. Interviewer bu mövzunu soruşduqda yoxlayır: "Breaking vs non-breaking change-i fərqləndirirsinizmi? Versioning strategy-nin trade-off-larını bilirsizmi? Deprecation planını necə qurarsınız?"

## Əsas Anlayışlar

### Breaking Change vs Non-Breaking Change:
- **Breaking change** (yeni versiya tələb edir): Mövcud client-i pozan dəyişiklik. Field silmək, field tipini dəyişmək (string → int), endpoint URL-i dəyişmək, required field əlavə etmək, response formatını dəyişmək (JSON → XML), HTTP status code-u dəyişmək.
- **Non-breaking change** (yeni versiya lazım deyil): Yeni optional field əlavə etmək, yeni optional parameter əlavə etmək, yeni endpoint əlavə etmək, daha geniş validation (daha çox input qəbul etmək). Köhnə client-lər tanımadığı field-ləri ignore edə bilir — JSON forward compatibility.
- **Postel's Law**: "Be conservative in what you send, liberal in what you accept." Server extra field-ləri qəbul etməli, client tanımadığı field-ləri ignore etməlidir.

### Versioning Strategiyaları:

**URL Versioning** (`/api/v1/users`, `/api/v2/users`):
- Ən geniş yayılmış, ən açıq yanaşma. Browser bookmark, curl, log-larda aydın görünür.
- Cache-friendly: URL unikal olduğu üçün CDN/proxy fərqli versiyaları ayrı-ayrı cache edə bilir.
- Dezavantaj: URL "implementation detail" (versiya) daşıyır, REST prinsipinə görə URL resursu təmsil etməlidir, versiya yox.
- Praktik olaraq ən çox istifadə olunur — Twilio, Twitter, PayPal.

**Header Versioning** (`API-Version: 2` ya da `Accept: application/vnd.api+json;version=2`):
- URL təmiz qalır — `/api/users` hər zaman eyni.
- Content negotiation ilə inteqrasiya olunur.
- Dezavantaj: Browser-dən test etmək çətin (curl-də header əlavə lazımdır), cache-ləmək çətin (URL eyni, Vary header tələb edir), developer experience pisdir.
- GitHub (`X-GitHub-Api-Version`), Stripe bu yanaşmanı istifadə edir.

**Query Parameter Versioning** (`/api/users?version=2`):
- Sadə implement etmək. URL-dən görünür.
- Dezavantaj: URL semantikasına uyğun deyil (query param filter/sort üçündür), version parametri digər filter-lərlə qarışa bilər, bəzi CDN-lər query-i ignore edir.
- Az üstünlük verilir — yalnız kiçik internal API-lərdə.

**Content Negotiation** (`Accept: application/vnd.company.v2+json`):
- Ən "RESTful" yanaşma — HTTP spesifikasiyasına uyğun.
- Cavabda: `Content-Type: application/vnd.company.v2+json`.
- Dezavantaj: Ən mürəkkəb, az tanınan format, tooling dəstəyi zəif, browser-də test etmək çətin.
- GitHub API v3 bu yanaşmanı media type-da istifadə edir.

### Semantic Versioning API üçün:
- MAJOR (v1 → v2): Breaking changes — yeni versiya tələb edir.
- MINOR (v1.1 → v1.2): Non-breaking additions — backward compatible, optional.
- PATCH (v1.1.1 → v1.1.2): Bug fix — transparent, client-i pozmur.
- URL versioning-də adətən yalnız MAJOR versiya göstərilir: `/api/v1/`, `/api/v2/`.
- Header versioning-də daha dəqiq: `API-Version: 2024-12-01` (Stripe-in date-based yanaşması).

### Stripe-in "Default Version" Mexanizmi:
- Yeni API key yaradılanda cari versiyaya "pin" olunur.
- Developer heç versiya göndərməsə, key-in pinləndiyi versiya istifadə olunur.
- Upgrade etmək istəyəndə dashboard-dan versiya dəyişdirilir.
- Bu mexanizm "breaking the world" riskini aradan qaldırır — hər key öz versiyasında qalır.

### Deprecation və Sunset:
- **Sunset header** (RFC 8594): Köhnə versiyayı deprecate edərkən göndərilir: `Sunset: Sat, 31 Dec 2025 23:59:59 GMT`.
- **Deprecation header**: `Deprecation: true` ya da `Deprecation: Mon, 01 Jan 2025 00:00:00 GMT`.
- **Link header**: `Link: </api/v2/users>; rel="successor-version"` — yeni versiyaya yönləndirir.
- **Parallel versioning**: v1 və v2 eyni anda aktiv. Köhnə versiya sunset tarixinə qədər dəstəklənir — adətən 6-12 ay.
- **Traffic monitoring**: Analytics ilə köhnə versiyanın traffic-ini izlə. 5% altına düşəndə sunset planını həyata keçir.

### Consumer-Driven Contract Testing (CDC):
- **Pact framework**: Consumer (client) öz gözləntiləri üçün "contract" yazır. Provider (server) bu contract-ları CI-da test edir.
- Breaking change deploy etmədən əvvəl Pact test-ləri fail edər.
- Microservice-lər arası API compatibility-ni avtomatik yoxlamaq üçün ideal.
- Internal API-lər üçün URL versioning-dən daha effektiv ola bilər.

### Expand and Contract Pattern:
- "Field rename" kimi breaking change-i non-breaking şəkildə etmək üçün.
- **Expand**: Hər iki field-i eyni anda response-a əlavə et (`user_name` + `username`). Köhnə app-lar `user_name`-i, yeni app-lar `username`-i istifadə edir.
- **Migrate**: İstifadəçilərin əksəriyyəti yeni versiyanı yükləyənə qədər gözlə.
- **Contract**: Köhnə field-i (`user_name`) sil. Bu addımda yeni major versiya yaranır.

### gRPC Versioning:
- Protobuf field number-ları dəyişdirilməməlidir — binary encoding bu number-lara əsaslanır.
- Yeni field əlavə etmək backward compatible-dir (optional by default).
- Field silmək — old number-ı "reserved" kimi qeyd et, yenidən istifadə etmə.
- Package-based versioning: `package myservice.v1;` → `package myservice.v2;`.

### GraphQL Versioning:
- GraphQL öz mexanizmi ilə versioning-ə az ehtiyac duyur.
- `@deprecated(reason: "Use newField instead")` directive ilə field deprecation.
- Optional field-lər, union type-lar ilə schema evolyusiyası mümkündür.
- GitHub GraphQL API-da versiyanı URL-də deyil, deprecation directive ilə idarə edirlər.

### Version Matrix və Documentation:
- Hansı versiya nə zaman yaradıldı, nə zaman deprecated oldu, nə zaman sunset olacaq — cədvəl.
- Changelog: Hər versiyada nə dəyişdi — breaking vs non-breaking.
- Migration guide: V1-dən V2-yə keçid üçün konkret kod nümunələri.
- Developer notification: Email, developer portal announcement, SDK deprecation warning.

## Praktik Baxış

### Interview-da Yanaşma:
Hər strategiyanın trade-off-larını izah edin. "Ən yaxşı versioning yanaşması URL versioning-dir" demək simplistic-dir. Kontekst vacibdir:
- **Public API (Stripe, Twilio kimi)**: URL versioning + sunset header. Yüzlərlə third-party developer.
- **Mobile app backend**: URL versioning + expand-and-contract. Köhnə app version-lar aylarca istifadədə qalır.
- **Internal microservices**: CDC (Pact) daha effektiv. Team-lər arasında contract-first development.
- **GraphQL**: Versioning əvəzinə schema evolution + `@deprecated`.

### Follow-up Suallar:
- "Breaking change olmadan yeni required field necə əlavə edərsiniz?" → Default dəyərlə optional başla, sonra required et (expand-and-contract).
- "GraphQL versioning-ə ehtiyac varmı?" → Minimaldır — field deprecation, union type ilə evolyusiya.
- "Internal microservice API-lər üçün versioning lazımdırmı?" → Pact CDC daha effektivdir — deploy-time yox, commit-time yoxlama.
- "gRPC breaking change necə idarə olunur?" → Field number sabit saxla, yeni field əlavə et, sildiyin number-ı reserved qeyd et.
- "API versioning-in test strategiyası nədir?" → Hər versiya üçün ayrı integration test suite. CDC ilə provider test.
- "Sunset-ə hazır olduğunu necə bilirsən?" → Traffic analytics: köhnə versiya < 1% → safe to sunset.
- "Date-based versioning (Stripe: `2024-11-20`) vs integer versioning (v1, v2) fərqi nədir?" → Date-based daha dəqiq, amma migration çətin.

### Ümumi Səhvlər:
- Bütün dəyişiklikləri yeni versiya kimi qəbul etmək — non-breaking changes üçün lazım deyil.
- Köhnə versiyanı sunset etmədən sonsuz versiya saxlamaq — maintenance yükü artır.
- Client-ə migration üçün kifayət vaxt verməmək — ən az 6 ay.
- Versioning-i sonradan layihəyə əlavə etməyə çalışmaq — routes-i yenidən qurmaq çətin.
- Sunset etmədən əvvəl traffic analytics yoxlamamaq.
- Breaking change-i non-breaking kimi deploy etmək — client-lər çökür.

### Yaxşı Cavabı Əla Cavabdan Fərqləndirən:
Sunset header, consumer-driven contract testing, ya da Stripe-in "default version" mexanizmini izah edə bilmək. "Expand and contract" pattern-ini bilmək. Traffic analytics ilə sunset qərarını əsaslandırmaq.

## Nümunələr

### Tipik Interview Sualı

"Your mobile app has 500K users with various app versions installed. You need to rename a JSON field `user_name` to `username` in your API response. How do you handle this without breaking old apps?"

### Güclü Cavab

Bu klassik breaking change problemidir. `user_name` → `username` rename birbaşa etsəniz, köhnə app-lar `user_name` field-ini tapmaz.

**Expand and Contract Pattern** ilə həll:

**Mərhələ 1 — Expand (Genişlən):**
Hər iki field-i eyni anda response-a əlavə edin. Köhnə app-lar `user_name`-i, yeni app-lar `username`-i istifadə edir. Deployment riski sıfırdır.

**Mərhələ 2 — Monitor (İzlə):**
Analytics: neçə % request köhnə app-dan gəlir? `user_name` field-inin istifadəsini log-la.

**Mərhələ 3 — Deprecation Notice:**
`Deprecation: true`, `Sunset: Sat, 31 Dec 2025 23:59:59 GMT` header-ları əlavə et. Developer portal-da announcement.

**Mərhələ 4 — Contract (Sıx):**
Sunset tarixindən sonra (traffic < 5% olduqda) `user_name`-i sil. Bu yeni major versiya (v2) deməkdir.

URL versioning ilə: `/api/v1/user` (hər ikisi), `/api/v2/user` (yalnız `username`). Analytics ilə köhnə versiya traffic-ini izlə, 5% altına düşəndə sunset et.

### Kod Nümunəsi

```php
// Laravel — URL versioning
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('users', Api\V1\UserController::class);
});

Route::prefix('v2')->group(function () {
    Route::apiResource('users', Api\V2\UserController::class);
});

// app/Http/Controllers/Api/V1/UserController.php
class UserController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'id'        => $user->id,
            'user_name' => $user->name,  // v1 köhnə format
            'username'  => $user->name,  // expand: hər ikisi — transition period
            'email'     => $user->email,
        ])->withHeaders([
            'Deprecation' => 'true',
            'Sunset'      => 'Sat, 31 Dec 2025 23:59:59 GMT',
            'Link'        => '</api/v2/users/' . $user->id . '>; rel="successor-version"',
        ]);
    }
}

// app/Http/Controllers/Api/V2/UserController.php
class UserController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'id'       => $user->id,
            'username' => $user->name,  // v2: yalnız yeni format
            'email'    => $user->email,
        ]);
    }
}
```

```php
// Header versioning middleware
// app/Http/Middleware/ApiVersionMiddleware.php
class ApiVersionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('API-Version', 'v1');
        $request->merge(['api_version' => $version]);

        $response = $next($request);

        // Cavabda istifadə olunan version-u bildirmək
        $response->headers->set('API-Version', $version);

        // Köhnə versiya üçün deprecation bildirişi
        if ($version === 'v1') {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', 'Sat, 31 Dec 2025 23:59:59 GMT');
        }

        return $response;
    }
}

// Vary header ilə cache düzgün işləsin
// routes/api.php
Route::middleware(['api.version'])->group(function () {
    Route::get('/users', function (Request $request) {
        $version = $request->get('api_version', 'v1');
        // Version-a görə response formatı seç
    })->withHeaders(['Vary' => 'API-Version']);
});
```

```
HTTP Response Example (V1 — deprecated):
─────────────────────────────────────────
HTTP/1.1 200 OK
Content-Type: application/json
API-Version: 1
Deprecation: true
Sunset: Sat, 31 Dec 2025 23:59:59 GMT
Link: </api/v2/users/1>; rel="successor-version"

{
  "id": 1,
  "user_name": "john",    ← köhnə format (hələ var)
  "username": "john",     ← yeni format (əlavə edilib)
  "email": "john@example.com"
}

Versioning Timeline:
     v1 launch    v2 launch    v1 sunset
         |             |            |
---------|-------------|------------|-------->
         | v1 only     | v1 + v2    | v2 only
                       | both live  |
                       | (6-12 ay)  |
```

### İkinci Nümunə — GraphQL Versioning

**Sual**: "GraphQL API-da `userProfile` query-ni `profile` adına rename etmək istəyirsən. Necə edərdin?"

**Cavab**:
```graphql
type Query {
    # Köhnə — deprecated
    userProfile(id: ID!): Profile
        @deprecated(reason: "Use 'profile' query instead. Will be removed 2026-01-01.")

    # Yeni — preferred
    profile(id: ID!): Profile
}

# Client-lər köhnə query-ni istifadə etdikdə GraphQL Playground/introspection
# deprecation warning göstərir. Server hər ikisini dəstəkləyir — breaking change yoxdur.
# Sunset tarixindən sonra userProfile resolver silinir.
```

## Praktik Tapşırıqlar

1. Mövcud REST API-nizin breaking change-lərini identify edin — field rename, type change, endpoint restructure.
2. URL versioning middleware implement edin — Laravel-də her versiya üçün ayrı controller namespace.
3. Sunset header məntiqini yazın: `config/api.php`-da hər versiyanın sunset tarixini saxla, middleware-da avtomatik header əlavə et.
4. Consumer-driven contract testing (Pact) araşdırın — microservice API-nin contract test-lərini yaz.
5. Stripe API documentation-ını oxuyun: date-based versioning mexanizmini, API key-ə versiya pinning-i öyrənin.
6. Expand-and-contract pattern-ını tətbiq edin: database field-inin rename-i ilə eyni prinsip — köhnəni saxla, yenisini əlavə et, migrate et, köhnəni sil.
7. Traffic analytics qur: versiyaya görə request sayını log-la. Grafana dashboard-da vizualizasiya.
8. Postel's Law-u tətbiq edin: API-nizi extra field-ləri ignore etmək üçün konfiqurasiya edin — forward compatibility.
9. Version matrix sənədini hazırlayın: hər versiya üçün yaranma tarixi, deprecation tarixi, sunset tarixi, migration guide linki.
10. gRPC protobuf versioning-i araşdırın: field number-ları necə idarə olunur, reserved keyword-ün rolu nədir.

### API Versioning Qısa Müqayisəsi

| Strategiya | Cache-friendly | Browser test | REST-ful | Real istifadəçilər |
|------------|:--------------:|:------------:|:--------:|:------------------:|
| URL prefix (`/v2/`) | ✓ | ✓ | Qismən | Twilio, Twitter, PayPal |
| Header (`API-Version:`) | Zəif (Vary) | ✗ | ✓ | GitHub, Stripe |
| Query param (`?v=2`) | Zəif | ✓ | ✗ | Nadir |
| Content type (`vnd.v2+json`) | Zəif | ✗ | ✓ ✓ | RFC puristlər |
| Date-based (`2024-11-20`) | Zəif | ✗ | ✓ | Stripe, Twilio yeni |

## Əlaqəli Mövzular

- [REST vs GraphQL vs gRPC](05-rest-graphql-grpc.md) — Hər API type-ının versioning yanaşması
- [HTTP Caching](09-http-caching.md) — URL versioning cache-ə necə təsir edir; Vary header
- [Webhook Design](12-webhook-design.md) — Webhook payload versioning strategiyaları
- [CORS](10-cors.md) — Versioned API CORS konfiqurasiyası
