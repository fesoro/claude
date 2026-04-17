# API Pagination

## Nədir? (What is it?)

Pagination boyuk datasetleri kiçik hisselere (səhifelere) bolmek üçün istifade olunan texnikadir. 1 million record-u bir defeye qaytarmaq əvezine, onlari 20-50 record-luq səhifelere bolup client-e gonderirsən. Bu həm server load-u azaldır, həm network bandwidth qənaət edir, həm də UX yaxşılaşdırır.

```
Pagination olmadan:
  GET /users   -->  [1 million records]  -> 500MB response, 30 saniye

Pagination ile:
  GET /users?page=1&per_page=50  --> 50 records (2KB)
  GET /users?page=2&per_page=50  --> 50 records (2KB)
  ...
```

## Necə İşləyir? (How does it work?)

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
  |                           |                         |

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

**Problem:** Boyuk offset-lerde slow. OFFSET 1000000 demek DB 1M row skip etməli.

### 2. Cursor-Based Pagination

```
Cursor = unique pointer to a record (adəten id və ya created_at)

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

**Ustunluk:** Her query eyni sürətdədir (O(log n) index lookup).
**Mehdudiyyet:** Random sehifeye atlanmamaq (yalniz next/prev).

### 3. Keyset Pagination (Seek Method)

```
Cursor-based-in alt nove. Sort edilen sütüna göre seek edir.

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

### 4. Page Number vs Cursor Comparison

```
Offset-based:
  URL:  /users?page=5&per_page=20
  +     Random access (page 1, 5, 100)
  +     "Total pages" gostermek asan
  -     Slow for deep pagination
  -     Inconsistent (yeni record inserted olarsa, duplicate goresen)

Cursor-based:
  URL:  /users?cursor=eyJpZCI6MTAwfQ
  +     Very fast, constant time
  +     Consistent (yeni row insert olsa bele)
  -     No random access
  -     No total count (adəten)
```

### 5. Infinite Scroll

```
Frontend             Backend
  |                     |
  |-- Initial load ---->|
  |<-- 20 items --------|
  |                     |
  (user scrolls down)
  |                     |
  |-- Load more ------->|
  |<-- next 20 ---------|

Texniki olaraq cursor-based pagination ile ishleyir.
UX-de user "Load More" duymesi və ya scroll ile trigger edir.
```

## Əsas Konseptlər (Key Concepts)

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

Decoding server-de:
  base64_decode("eyJpZCI6MTIzNDV9")  -->  {"id":12345}

Security: Cursor-a sensitive data qoyma, user goreсek!
```

### Consistency Problems

```
Offset pagination-da duplicate problem:

Time 0:  DB-de 100 user var (id 1-100)
  Client: GET /users?page=1  -->  users 1-20

Time 1:  Yeni user əlavə olundu (id=101, alphabetically first)
  DB: users siralanir:  [101, 1, 2, ..., 100]

Time 2:  Client: GET /users?page=2  -->  users 20-39
  Amma user 20 artiq page=1-de vardi!  --> DUPLICATE
```

Cursor-based bu problemi həll edir.

### Cursor vs Offset - Nə vaxt hansi?

```
Offset istifade et:
  * Small datasets (< 10K records)
  * Admin paneller (page number lazim)
  * Total count gostermek lazimdirsa
  * Random page access behavior

Cursor istifade et:
  * Boyuk datasetler (> 10K)
  * Real-time feeds (Twitter, Instagram)
  * Infinite scroll
  * Performance critical
  * Consistent results lazimdirsa
```

## PHP/Laravel ilə İstifadə

### Basic Offset Pagination

```php
use App\Models\User;

// Controller
public function index(Request $request)
{
    // Basit paginate()
    $users = User::orderBy('id')->paginate(20);

    // Response avtomatik meta data ilə gəlir
    return $users;
}

/* Response:
{
  "current_page": 1,
  "data": [...],
  "first_page_url": "http://...?page=1",
  "from": 1,
  "last_page": 50,
  "last_page_url": "http://...?page=50",
  "links": [...],
  "next_page_url": "http://...?page=2",
  "path": "http://...",
  "per_page": 20,
  "prev_page_url": null,
  "to": 20,
  "total": 1000
}
*/
```

### Simple Pagination (total count olmadan - daha sürətli)

```php
// simplePaginate() COUNT(*) query etmir, daha sürətlidir
$users = User::orderBy('id')->simplePaginate(20);

/* Response:
{
  "current_page": 1,
  "data": [...],
  "from": 1,
  "next_page_url": "...",
  "per_page": 20,
  "prev_page_url": null,
  "to": 20
  // Diqqet: total, last_page yoxdur!
}
*/
```

### Cursor Pagination

```php
// Laravel 8+
$users = User::orderBy('id')->cursorPaginate(20);

/* Response:
{
  "data": [...],
  "path": "http://...",
  "per_page": 20,
  "next_cursor": "eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
  "next_page_url": "http://...?cursor=eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
  "prev_cursor": null,
  "prev_page_url": null
}
*/

// Next page request:
// GET /users?cursor=eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0
```

### Custom Pagination Response (API Resource)

```php
// app/Http/Resources/UserCollection.php
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]),
            'meta' => [
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
            ],
            'links' => [
                'next' => $this->nextPageUrl(),
                'prev' => $this->previousPageUrl(),
            ],
        ];
    }
}

// Controller
public function index()
{
    return new UserCollection(User::paginate(20));
}
```

### Per-Page Parameter (Dinamik)

```php
public function index(Request $request)
{
    $perPage = min($request->integer('per_page', 20), 100); // max 100

    return User::paginate($perPage);
}

// GET /users?per_page=50&page=2
```

### Manual Keyset Pagination

```php
public function feed(Request $request)
{
    $limit = 20;
    $lastId = $request->integer('last_id');

    $query = Post::orderBy('id', 'desc')->limit($limit);

    if ($lastId) {
        $query->where('id', '<', $lastId);
    }

    $posts = $query->get();
    $nextLastId = $posts->last()?->id;

    return response()->json([
        'data' => $posts,
        'next_url' => $nextLastId
            ? url("/api/feed?last_id={$nextLastId}")
            : null,
    ]);
}
```

### Pagination with Search/Filter

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

    // appends() ile query parametrləri pagination link-lərinde qalır
    return $query->paginate(20)->appends($request->query());
}

// GET /users?search=ali&role=admin&page=2
// Next link: /users?search=ali&role=admin&page=3
```

### Eloquent Relationship Pagination

```php
$user = User::find(1);

// User-in posts-larini paginate et
$posts = $user->posts()->paginate(10);

// Scope ile
class Post extends Model
{
    public function scopePublished($q)
    {
        return $q->where('published', true);
    }
}

$posts = Post::published()->latest()->cursorPaginate(15);
```

### Blade-də İstifadə

```blade
{{-- users sehifesi --}}
<ul>
    @foreach ($users as $user)
        <li>{{ $user->name }}</li>
    @endforeach
</ul>

{{-- Pagination linkler --}}
{{ $users->links() }}

{{-- Custom theme (Bootstrap 5) --}}
{{ $users->links('pagination::bootstrap-5') }}

{{-- Tailwind (default) --}}
{{ $users->links() }}
```

### Infinite Scroll API

```php
// Route
Route::get('/api/feed', [FeedController::class, 'index']);

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
    const res = await fetch(nextUrl);
    const json = await res.json();
    renderPosts(json.data);
    nextUrl = json.next_page_url;
}

// IntersectionObserver ile scroll trigger
const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) loadMore();
});
observer.observe(document.querySelector('#sentinel'));
```

## Interview Sualları

**Q1: Offset vs cursor pagination fərqi?**

Offset-based `page` və `per_page` istifade edir - DB `LIMIT X OFFSET Y` edir. Asan implementation, total count var, random access var. Amma deep offset-lərdə (page 10000) slow olur çünki DB 200K row skip etməlidir. Həm də yeni record insert olsa duplicates verir.

Cursor-based unikal pointer (adəten id) istifade edir: `WHERE id > cursor LIMIT N`. Index-dən tam istifade, constant time performance, consistent results. Mehdudiyyet: random access yoxdur, total count adəten verilmir.

**Q2: `paginate()` vs `simplePaginate()` vs `cursorPaginate()` Laravel-də?**

`paginate()` - Full metadata (total, last_page, links). `COUNT(*)` query edir.

`simplePaginate()` - Yalniz next/prev links. `COUNT(*)` etmir, daha surətli.

`cursorPaginate()` - Cursor-based. Ən surətli, amma page number yoxdur.

**Q3: `COUNT(*)` niye slow-dur?**

Boyuk cədvəldə COUNT(*) butun row-lari iterate edir (MySQL InnoDB-də xüsusilə). WHERE filter-lər varsa daha pis olur. Hell yolları: cached count, estimate (PostgreSQL `pg_stat`), ve ya simplePaginate/cursorPaginate istifade etmek.

**Q4: Cursor-də niye `id` saxlanir ama sort `created_at`-e gore?**

Eger iki record eyni `created_at`-e sahibdirse, yalniz timestamp-le cursor stable deyil. Composite cursor lazim:
```sql
WHERE (created_at, id) < ('2024-01-15', 500)
ORDER BY created_at DESC, id DESC
```
`id` tie-breaker rolunda çıxış edir.

**Q5: Pagination-da duplicate ve missing rows problemini necə həll etməli?**

Offset-based-də yeni insert-lər zamanı olur. Həllər:
1. Cursor-based pagination-a keç (ən yaxşı)
2. Snapshot isolation level
3. Sort sutünü dəyişməz olsun (created_at)
4. Client tərəfde duplicate filtering (ID-ə görə)

**Q6: Offset pagination-in deep pagination problemini necə optimize etməli?**

- **Index hint**: DB-yə index istifade etməyi tövsiyə et
- **Covering index**: SELECT edilen sütünlar index-də olsun
- **Keyset pagination-a keçid** (ən yaxşı həll)
- **Page limit**: max 1000 page limit qoy
- **Elasticsearch istifade et** deep pagination üçün `search_after`

**Q7: Pagination metadata-da total count verməli, verməməli?**

Asılıdır:
- **Ver**: Admin panel, report, "X nəticə tapildi" UI.
- **Vermə**: Infinite scroll, real-time feed, boyuk dataset (performance), cursor-based pagination.

Total count COUNT(*) query tələb edir - boyuk cədvəldə expensive.

**Q8: API-de max per_page niye lazimdir?**

User `?per_page=1000000` gondersə:
- Memory exhaustion
- DB timeout
- Network bandwidth
- DOS attack vektoru

Həmişə `min($request->per_page, 100)` kimi limit qoy.

**Q9: Cursor-də sensitive data saxlanmalidir?**

XEYR! Cursor client-ə gedir və base64 decode oluna bilər. Cursor-də yalniz:
- Public ID
- Timestamp
- Sort value

Saxlama:
- User ID (başqa user-in)
- Email
- Secret token
- Internal state

**Q10: Infinite scroll vs Pagination - UX baxımından?**

Infinite scroll:
- + Passive content (social media, feed)
- + Discovery, endless browsing
- - Footer access cətin
- - Back button problem
- - Can't "page 50-ye get"

Pagination:
- + Specific page-ə get
- + Footer rahat
- + SEO-friendly (her page ayri URL)
- - Manual interaction (click)
- - Content exploration az

Hybrid approach: Infinite scroll + "Load more" button + URL state (TikTok, YouTube).

## Best Practices

1. **Default per_page qoy** (20-50 arasi) və max 100-dən çox olmasın.

2. **Cursor-based seç** real-time feed, boyuk dataset, performance critical case-lərdə.

3. **`appends()` istifade et** filter/search parametrleri pagination link-lərində qalsın.

4. **Total count-u cache-le** sıx dəyişməyən datasets üçün (`COUNT(*)` expensive).

5. **Index əlavə et** ORDER BY və WHERE sütünlarında (composite indexes).

6. **Pagination response standard et** - butun endpoint-lərdə eyni format.

7. **`simplePaginate()` istifade et** COUNT(*) lazim olmadiqda (API feeds).

8. **Cursor stable olsun** - immutable field-e (id, created_at) bagla.

9. **Link header standard-ini tetbiq et** (RFC 5988):
```
Link: <https://api.example.com/users?page=3>; rel="next",
      <https://api.example.com/users?page=50>; rel="last"
```

10. **Page validation et** - negative page, zero page, integer olmayan deyerlər.

11. **Deep pagination-i mehdudla** - max 1000 page və ya cursor-based-e keçir.

12. **Frontend-də debounce et** infinite scroll trigger-lərini (çox request atmamaq üçün).

13. **Empty state handle et** - `data: []` və `total: 0` düzgün qaytar.

14. **API documentation-da aydın** pagination model-ini göstər (Swagger/OpenAPI).

15. **Monitoring qur** - slow pagination query-lərin logunu yığ, optimize et.
