# API Pagination (Middle)

## İcmal

Pagination böyük datasetləri kiçik hissələrə (səhifələrə) bölmək üçün istifadə olunan texnikadır. 1 milyon record-u bir dəfəyə qaytarmaq əvəzinə, onları 20-50 record-luq səhifələrə bölüb client-ə göndərirsən. Bu həm server load-u azaldır, həm network bandwidth qənaət edir, həm də UX yaxşılaşdırır.

```
Pagination olmadan:
  GET /users   -->  [1 million records]  -> 500MB response, 30 saniyə

Pagination ilə:
  GET /users?page=1&per_page=50  --> 50 records (2KB)
  GET /users?page=2&per_page=50  --> 50 records (2KB)
  ...
```

## Niyə Vacibdir

Böyük datasetlər üzərində düzgün pagination seçimi həm performansı, həm də UX-i əhəmiyyətli dərəcədə təsir edir. Offset-based pagination sadə görünsə də, dərin page-lərdə (page 10000) DB-nin milyonlarla row skip etməsi ciddi performans problemlərinə yol açır. Cursor-based pagination isə Twitter, Instagram, Facebook kimi real-time feed-lərin standartıdır — böyük datasette sabit performans təmin edir.

## Əsas Anlayışlar

### 1. Offset-Based Pagination

```
Client                      Server                   Database
  |                           |                         |
  |-- GET /users?             |                         |
  |   page=3&per_page=20 --->|                         |
  |                           |-- SELECT * FROM users  |
  |                           |   LIMIT 20 OFFSET 40 ->|
  |                           |<-- 20 rows ----------  |
  |<-- {data, meta} ---------|                         |

SQL:
  LIMIT 20 OFFSET 40   (page=3, per_page=20 means skip 40)

Response:
  {
    "data": [...20 users...],
    "meta": {
      "current_page": 3,
      "per_page": 20,
      "total": 10000,
      "last_page": 500
    }
  }
```

**Problem:** Böyük offset-lərdə yavaş. `OFFSET 1000000` demək DB 1M row skip etməli.

### 2. Cursor-Based Pagination

```
Cursor = unique pointer to a record (adətən id və ya created_at)

Client                           Server
  |                                |
  |-- GET /posts?limit=20 ------->|
  |<-- {data, next_cursor:"xyz"} -|
  |                                |
  |-- GET /posts?                  |
  |   cursor=xyz&limit=20 ------->|
  |                                |-- WHERE id > decoded_cursor
  |                                |   ORDER BY id LIMIT 20
  |<-- {data, next_cursor:"abc"} -|

SQL:
  SELECT * FROM posts
  WHERE id > 12345     (cursor)
  ORDER BY id ASC
  LIMIT 20
```

**Üstünlük:** Hər query eyni sürətdədir (O(log n) index lookup).
**Məhdudiyyət:** Random səhifəyə atlanmaq mümkün deyil (yalnız next/prev).

### 3. Keyset Pagination (Seek Method)

```
Cursor-based-in alt növü. Sort edilən sütuna görə seek edir.

SQL:
  -- First page
  SELECT * FROM orders
  ORDER BY created_at DESC, id DESC
  LIMIT 20

  -- Next page (last row: created_at='2024-01-15', id=500)
  SELECT * FROM orders
  WHERE (created_at, id) < ('2024-01-15', 500)
  ORDER BY created_at DESC, id DESC
  LIMIT 20
```

Index-dən tam istifadə edir, heç bir skip yoxdur.

### 4. Offset vs Cursor Müqayisəsi

```
Offset-based:
  URL:  /users?page=5&per_page=20
  +     Random access (page 1, 5, 100)
  +     "Total pages" göstərmək asan
  -     Dərin pagination-da yavaş
  -     Yeni record insert olsa duplicate görsənir

Cursor-based:
  URL:  /users?cursor=eyJpZCI6MTAwfQ
  +     Çox sürətli, sabit performans
  +     Consistent (yeni row insert olsa belə)
  -     Random access yoxdur
  -     Total count adətən verilmir
```

### 5. Nə vaxt hansını seçmək?

```
Offset istifadə et:
  * Kiçik datasetlər (< 10K records)
  * Admin panellər (page number lazım)
  * Total count göstərmək lazımdırsa
  * Random page access davranışı

Cursor istifadə et:
  * Böyük datasetlər (> 10K)
  * Real-time feeds (Twitter, Instagram)
  * Infinite scroll
  * Performance critical
  * Consistent results lazımdırsa
```

### Pagination Metadata

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 2,
    "per_page": 20,
    "total": 1500,
    "last_page": 75,
    "from": 21,
    "to": 40
  },
  "links": {
    "first": "/api/users?page=1",
    "last": "/api/users?page=75",
    "prev": "/api/users?page=1",
    "next": "/api/users?page=3"
  }
}
```

### Cursor Encoding

```
Cursor = Base64(JSON({"id": 12345, "created_at": "2024-01-15"}))

Encoding:
  {"id":12345}  -->  eyJpZCI6MTIzNDV9

Decoding server-də:
  base64_decode("eyJpZCI6MTIzNDV9")  -->  {"id":12345}

Security: Cursor-a sensitive data qoyma, user görəcək!
```

### Consistency Problem (Offset Pagination-da)

```
Time 0:  DB-də 100 user var (id 1-100)
  Client: GET /users?page=1  -->  users 1-20

Time 1:  Yeni user əlavə olundu (id=101, alphabetically first)
  DB: users sıralanır:  [101, 1, 2, ..., 100]

Time 2:  Client: GET /users?page=2  -->  users 20-39
  Amma user 20 artıq page=1-də vardı!  --> DUPLICATE

Cursor-based bu problemi həll edir.
```

## Praktik Baxış

**Trade-off-lar:**
- `paginate()` — tam metadata, amma `COUNT(*)` query əlavə edir
- `simplePaginate()` — COUNT olmadan, daha sürətli, amma total count yoxdur
- `cursorPaginate()` — ən sürətli, sabit performans, amma page number yoxdur

**Anti-pattern-lər:**
- `?per_page=1000000` kimi limitsiz parametrə icazə vermək (DoS vektoru)
- Böyük offset-lərdə offset pagination-u saxlamaq (keyset-ə keç)
- Cursor-a sensitive data (email, token) qoymaq — base64 decode oluna bilər
- Filter parametrlərini `appends()` olmadan saxlamaq — pagination link-lər broken olur

## Nümunələr

### Ümumi Nümunə

Laravel üç pagination metodu təklif edir: `paginate()` (full metadata + COUNT), `simplePaginate()` (COUNT olmadan, yalnız next/prev), `cursorPaginate()` (cursor-based, ən sürətli). Seçim use case-dən asılıdır.

### Kod Nümunəsi

**Basic Offset Pagination:**

```php
public function index(Request $request)
{
    $users = User::orderBy('id')->paginate(20);
    return $users;
}

/* Response:
{
  "current_page": 1,
  "data": [...],
  "last_page": 50,
  "next_page_url": "http://...?page=2",
  "total": 1000,
  ...
}
*/
```

**Simple Pagination (COUNT olmadan — daha sürətli):**

```php
// simplePaginate() COUNT(*) query etmir
$users = User::orderBy('id')->simplePaginate(20);

/* Response:
{
  "current_page": 1,
  "data": [...],
  "next_page_url": "...",
  // Diqqət: total, last_page yoxdur!
}
*/
```

**Cursor Pagination:**

```php
// Laravel 8+
$users = User::orderBy('id')->cursorPaginate(20);

/* Response:
{
  "data": [...],
  "next_cursor": "eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
  "next_page_url": "http://...?cursor=eyJpZCI6MjA...",
  "prev_cursor": null
}
*/

// Next page:
// GET /users?cursor=eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0
```

**Custom Pagination Response (API Resource):**

```php
class UserCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data'  => $this->collection->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
            ]),
            'meta'  => [
                'current_page' => $this->currentPage(),
                'per_page'     => $this->perPage(),
                'total'        => $this->total(),
                'last_page'    => $this->lastPage(),
            ],
            'links' => [
                'next' => $this->nextPageUrl(),
                'prev' => $this->previousPageUrl(),
            ],
        ];
    }
}

public function index()
{
    return new UserCollection(User::paginate(20));
}
```

**Dinamik Per-Page Parameter:**

```php
public function index(Request $request)
{
    $perPage = min($request->integer('per_page', 20), 100); // max 100
    return User::paginate($perPage);
}
// GET /users?per_page=50&page=2
```

**Manual Keyset Pagination:**

```php
public function feed(Request $request)
{
    $limit  = 20;
    $lastId = $request->integer('last_id');

    $query = Post::orderBy('id', 'desc')->limit($limit);

    if ($lastId) {
        $query->where('id', '<', $lastId);
    }

    $posts      = $query->get();
    $nextLastId = $posts->last()?->id;

    return response()->json([
        'data'     => $posts,
        'next_url' => $nextLastId
            ? url("/api/feed?last_id={$nextLastId}")
            : null,
    ]);
}
```

**Pagination with Search/Filter:**

```php
public function index(Request $request)
{
    $query = User::query();

    if ($search = $request->input('search')) {
        $query->where('name', 'like', "%{$search}%");
    }

    if ($role = $request->input('role')) {
        $query->where('role', $role);
    }

    // appends() ilə query parametrləri pagination link-lərində qalır
    return $query->paginate(20)->appends($request->query());
}
// GET /users?search=ali&role=admin&page=2
// Next link: /users?search=ali&role=admin&page=3
```

**Infinite Scroll API:**

```php
// Controller
public function index(Request $request)
{
    $posts = Post::with('user')
        ->orderBy('id', 'desc')
        ->cursorPaginate(20);

    return PostResource::collection($posts);
}

// Frontend (JavaScript)
let nextUrl = '/api/feed';
async function loadMore() {
    if (!nextUrl) return;
    const res  = await fetch(nextUrl);
    const json = await res.json();
    renderPosts(json.data);
    nextUrl = json.next_page_url;
}

// IntersectionObserver ilə scroll trigger
const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) loadMore();
});
observer.observe(document.querySelector('#sentinel'));
```

**Blade-də İstifadə:**

```blade
@foreach ($users as $user)
    <li>{{ $user->name }}</li>
@endforeach

{{-- Pagination linkler --}}
{{ $users->links() }}

{{-- Bootstrap 5 --}}
{{ $users->links('pagination::bootstrap-5') }}
```

## Praktik Tapşırıqlar

1. **Pagination tipləri müqayisəsi:** Eyni endpoint-i `paginate()`, `simplePaginate()`, `cursorPaginate()` ilə implement edin. `EXPLAIN ANALYZE` ilə SQL sorğularını müqayisə edin — cursor-based-in index-dən necə tam istifadə etdiyini görün.

2. **Deep pagination problemi:** `?page=10000` göndərin. `OFFSET 200000` ilə yaranan yavaşlığı müşahidə edin. Sonra keyset pagination-a keçib eyni datanı `WHERE id > X LIMIT 20` ilə əldə edin — fərqi ölçün.

3. **Filter ilə pagination:** `?search=ali&role=admin&page=2` sorğusu üçün pagination yazın. `appends($request->query())` olmadan link-lərin filter parametrlərini itirib-itirmədiyini yoxlayın.

4. **Infinite scroll:** `cursorPaginate(10)` istifadə edərək feed endpoint-i yazın. Sadə HTML/JS ilə sonsuz scroll qurun. IntersectionObserver ilə "Load More" trigger-ini implement edin.

5. **Custom resource collection:** `UserCollection` yaradın. API response-da `meta` və `links` key-lərini özünüzün format-ına görə qurun. Swagger/OpenAPI-da bu formatı sənədləşdirin.

## Əlaqəli Mövzular

- [REST API](08-rest-api.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [API Versioning](22-api-versioning.md)
- [GraphQL](09-graphql.md)
- [Network Timeouts](42-network-timeouts.md)
