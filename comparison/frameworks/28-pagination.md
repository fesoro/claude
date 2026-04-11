# Pagination (Sehifeleme)

## Giris

Boyuk melumat toplusunu bir defede qaytarmaq hem performans, hem de istifade baximindan qeyri-praktikdir. Pagination melumatli kiik hisselere (sehifelere) bolerek qaytarmaq mexanizmidir. Spring `Pageable`, `Page` ve `Sort` interfeysleri ile tip-tehlukesiz pagination teklif edir, Laravel ise `paginate()`, `simplePaginate()` ve `cursorPaginate()` metodlari ile muxtelif pagination novleri destekleyir.

## Spring-de istifadesi

### Pageable ve Page

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserRepository userRepository;

    // Avtomatik Pageable parametr - Spring ozue yaradir
    // GET /api/users?page=0&size=10&sort=name,asc
    @GetMapping
    public Page<UserResponse> getUsers(Pageable pageable) {
        return userRepository.findAll(pageable)
            .map(UserResponse::from);
    }
}
```

Page obyekti asagidaki JSON strukturunu qaytarir:

```json
{
    "content": [
        { "id": 1, "name": "Ahmed" },
        { "id": 2, "name": "Leyla" }
    ],
    "pageable": {
        "pageNumber": 0,
        "pageSize": 10,
        "sort": { "sorted": true, "orders": [{ "property": "name", "direction": "ASC" }] }
    },
    "totalPages": 5,
    "totalElements": 48,
    "last": false,
    "first": true,
    "size": 10,
    "number": 0,
    "numberOfElements": 10
}
```

### Repository-de Pageable istifadesi

```java
@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    // Sade pagination
    Page<User> findByActiveTrue(Pageable pageable);

    // Axtarisla birlestirmek
    Page<User> findByNameContainingIgnoreCase(String name, Pageable pageable);

    // JPQL ile
    @Query("SELECT u FROM User u WHERE u.role = :role")
    Page<User> findByRole(@Param("role") String role, Pageable pageable);

    // Slice - totalElements hesablanmir (daha suretli)
    Slice<User> findByDepartment(String department, Pageable pageable);
}
```

### PageRequest ve Sort yaratmaq

```java
@Service
public class UserService {

    public Page<User> getUsers(int page, int size, String sortBy, String direction) {
        Sort sort = direction.equalsIgnoreCase("desc")
            ? Sort.by(sortBy).descending()
            : Sort.by(sortBy).ascending();

        PageRequest pageRequest = PageRequest.of(page, size, sort);

        return userRepository.findAll(pageRequest);
    }

    // Birden cox saheeye gore siralama
    public Page<User> getUsersSorted() {
        Sort sort = Sort.by(
            Sort.Order.asc("lastName"),
            Sort.Order.desc("createdAt")
        );

        return userRepository.findAll(PageRequest.of(0, 20, sort));
    }
}
```

### Default Pageable konfiqurasiyasi

```java
// application.yml ile
// spring:
//   data:
//     web:
//       pageable:
//         default-page-size: 20
//         max-page-size: 100
//         one-indexed-parameters: true  # page=1-den baslasin (default 0)

// Yaxud metod seviyyesinde
@GetMapping
public Page<UserResponse> getUsers(
        @PageableDefault(size = 20, sort = "createdAt",
                         direction = Sort.Direction.DESC) Pageable pageable) {
    return userRepository.findAll(pageable).map(UserResponse::from);
}
```

### Xususi Pageable resolver

```java
@Configuration
public class PaginationConfig implements WebMvcConfigurer {

    @Override
    public void addArgumentResolvers(List<HandlerMethodArgumentResolver> resolvers) {
        PageableHandlerMethodArgumentResolver resolver =
            new PageableHandlerMethodArgumentResolver();
        resolver.setMaxPageSize(100);
        resolver.setFallbackPageable(PageRequest.of(0, 20));
        resolver.setOneIndexedParameters(true);
        resolvers.add(resolver);
    }
}
```

### Specification ile filtrlemeli pagination

```java
@RestController
@RequestMapping("/api/products")
public class ProductController {

    // GET /api/products?category=electronics&minPrice=100&maxPrice=500&page=0&size=10
    @GetMapping
    public Page<ProductResponse> searchProducts(
            @RequestParam(required = false) String category,
            @RequestParam(required = false) BigDecimal minPrice,
            @RequestParam(required = false) BigDecimal maxPrice,
            Pageable pageable) {

        Specification<Product> spec = Specification.where(null);

        if (category != null) {
            spec = spec.and((root, query, cb) ->
                cb.equal(root.get("category"), category));
        }
        if (minPrice != null) {
            spec = spec.and((root, query, cb) ->
                cb.greaterThanOrEqualTo(root.get("price"), minPrice));
        }
        if (maxPrice != null) {
            spec = spec.and((root, query, cb) ->
                cb.lessThanOrEqualTo(root.get("price"), maxPrice));
        }

        return productRepository.findAll(spec, pageable)
            .map(ProductResponse::from);
    }
}
```

## Laravel-de istifadesi

### paginate() metodu

```php
class UserController extends Controller
{
    // GET /api/users?page=1
    public function index(Request $request)
    {
        $users = User::query()
            ->where('active', true)
            ->orderBy('name')
            ->paginate(15); // Sehife basi 15 yazd

        return UserResource::collection($users);
    }
}
```

Cavab formati:

```json
{
    "data": [
        { "id": 1, "name": "Ahmed" },
        { "id": 2, "name": "Leyla" }
    ],
    "links": {
        "first": "http://example.com/api/users?page=1",
        "last": "http://example.com/api/users?page=5",
        "prev": null,
        "next": "http://example.com/api/users?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "to": 15,
        "total": 73,
        "path": "http://example.com/api/users"
    }
}
```

### simplePaginate() - Daha suretli

```php
// Umumi sayi (total) hesablamir - boyuk cedveller ucun daha suretli
public function index()
{
    $users = User::query()
        ->orderBy('created_at', 'desc')
        ->simplePaginate(20);

    return UserResource::collection($users);
}

// Cavab: "Sonraki" ve "evvelki" linkler var, amma "umumi sehife sayi" yoxdur
// {
//     "data": [...],
//     "links": { "first": "...", "prev": null, "next": "...?page=2" },
//     "meta": { "current_page": 1, "per_page": 20, "from": 1, "to": 20 }
// }
```

### cursorPaginate() - Cursor pagination

```php
// En effektiv usul - boyuk melumat toplulari ucun
// OFFSET istifade etmir, cursor (islgec) istifade edir
public function index()
{
    $users = User::query()
        ->orderBy('id')
        ->cursorPaginate(20);

    return UserResource::collection($users);
}

// Cavab:
// {
//     "data": [...],
//     "next_cursor": "eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
//     "prev_cursor": null,
//     "meta": { "per_page": 20, "path": "..." }
// }

// Novbeti sehife:
// GET /api/users?cursor=eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0
```

### Filtrleme ile pagination

```php
class ProductController extends Controller
{
    // GET /api/products?category=electronics&min_price=100&max_price=500&sort=price&order=asc&page=1
    public function index(Request $request)
    {
        $query = Product::query();

        // Filtrler
        $query->when($request->category, fn ($q, $category) =>
            $q->where('category', $category)
        );

        $query->when($request->min_price, fn ($q, $min) =>
            $q->where('price', '>=', $min)
        );

        $query->when($request->max_price, fn ($q, $max) =>
            $q->where('price', '<=', $max)
        );

        $query->when($request->search, fn ($q, $search) =>
            $q->where('name', 'like', "%{$search}%")
        );

        // Siralama
        $sortBy = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');
        $query->orderBy($sortBy, $order);

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $products = $query->paginate($perPage);

        // Sorgu parametrlerini linklere elave et
        $products->appends($request->query());

        return ProductResource::collection($products);
    }
}
```

### Blade-de pagination linkleri (web ucun)

```blade
<!-- resources/views/users/index.blade.php -->
<div class="users-list">
    @foreach ($users as $user)
        <div class="user-card">
            <h3>{{ $user->name }}</h3>
            <p>{{ $user->email }}</p>
        </div>
    @endforeach
</div>

<!-- Pagination linkleri - avtomatik goruntu -->
{{ $users->links() }}

<!-- Bootstrap stili ile -->
{{ $users->links('pagination::bootstrap-5') }}

<!-- Xususi goruntu ile -->
{{ $users->links('vendor.pagination.custom') }}

<!-- Sade Evvelki/Sonraki -->
{{ $users->links('pagination::simple-default') }}
```

### Xususi Paginator

```php
// Manual pagination yaratmaq
use Illuminate\Pagination\LengthAwarePaginator;

class SearchService
{
    public function search(string $query, int $page = 1, int $perPage = 15)
    {
        // Xarici API-den axtaris (meselen, Elasticsearch)
        $results = $this->elasticsearch->search($query);

        $items = collect($results['hits'])
            ->forPage($page, $perPage);

        return new LengthAwarePaginator(
            $items,
            $results['total'],
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }
}
```

### Eloquent ilaqalarda pagination

```php
class UserController extends Controller
{
    // Istifadecinin sifarisleri - sehifelensmis
    public function orders(User $user)
    {
        $orders = $user->orders()
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return OrderResource::collection($orders);
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Esas interfeys** | `Pageable`, `Page<T>` | `paginate()` metodu |
| **Sehife nomreleme** | 0-dan baslayir (default) | 1-den baslayir |
| **Siralama** | `Sort` sinfi, `?sort=name,asc` | `orderBy()` metodu, manual |
| **Total olmadan** | `Slice<T>` | `simplePaginate()` |
| **Cursor pagination** | Yoxdur (built-in) | `cursorPaginate()` |
| **HTML linkleri** | Yoxdur (API-only) | `$users->links()` Blade-de |
| **Query params saxlama** | Avtomatik | `appends()` metodu |
| **Maks sehife olcusu** | Konfiqurasiya ile | Manual (`min()` ile) |
| **Default deyerler** | `@PageableDefault` | Metod parametri |
| **Filtrlemeli pagintion** | Specification + Pageable | Query builder + paginate() |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring `Pageable` interfeysini avtomatik olaraq HTTP parametrlerinden yaradir (`page`, `size`, `sort`). Bu tip-tehlukesiz bir yanasmadirr - `Page<User>` qaytaranda derleme zamani tip yoxlanir. `Sort` sinfi murakkeb siralama ifadelerini destekleyir. Amma Spring yalniz API ucun dusuunulub, HTML pagination linkleri yaratmir.

**Laravel-in yanasmasi:** Laravel uc ferqli pagination novu teklif edir:
1. `paginate()` - Tam pagination (umumi say, sehife nomoreleri)
2. `simplePaginate()` - Yalniz evvelki/sonraki (daha suretli, COUNT sorgusu yoxdur)
3. `cursorPaginate()` - Cursor esasli (en suretli, OFFSET istifade etmir)

Laravel hem API, hem de web ucun pagination destekleyir - Blade-de `$users->links()` ile gozle gorunuslu pagination linkleri yaranir.

**Cursor pagination ferqi:** Bu, boyuk cedveller ucun muhum performans ferqidir. `OFFSET 10000` istifade etmek 10000 setiri kecerek skan edir, cursor ise birbaşa lazim olan noqteden baslayir. Laravel bunu built-in destekleyir, Spring-de manual implementasiya lazimdir.

**Sehife nomreleme:** Spring default olaraq 0-dan baslayir (Java massiv indeksleme menntiqine uygun), Laravel ise 1-den baslayir (istifadeci dostu). Spring-de `one-indexed-parameters=true` ile 1-den baslatmaq mumkundur.

## Hansi framework-de var, hansinda yoxdur?

- **Cursor pagination** - Yalniz Laravel-de built-in. Spring-de manual implementasiya lazimdir.
- **`simplePaginate()`** - Yalniz Laravel-de. Spring-de `Slice<T>` oxsar funksionalliq verir.
- **HTML pagination linkleri** - Yalniz Laravel-de. `$users->links()` ile avtomatik Blade goruntusu.
- **`Pageable` avtomatik resolver** - Yalniz Spring-de. URL parametrlerinden avtomatik `Pageable` obyekt yaranir.
- **`Sort` sinfi** - Yalniz Spring-de. Tip-tehlukesiz, composable siralama ifadeleri.
- **`@PageableDefault`** - Yalniz Spring-de. Default sehife olcusu ve siralama annotasiya ile.
- **`appends()`** - Yalniz Laravel-de. Pagination linklerine sorgu parametrlerini elave etmek.
- **`withQueryString()`** - Yalniz Laravel-de. Butun query string-i pagination linklerine elave etmek.
- **Specification + Pageable** - Spring-de filtr ve pagination birlesdirilmesi tip-tehlukesiz formada mumkundur.
- **`forPage()` Collection metodu** - Laravel Collection-da manual sehifeleme.
