# N+1 Problem (Middle ⭐⭐)

## İcmal
N+1 problem — bir sorğu N element gətirir, sonra hər element üçün ayrıca sorğu çalışır: cəmi N+1 sorğu. Bu ORM-lərdə ən çox rast gəlinən performance antipattern-dir. Hər PHP/Laravel developer-i bilməlidir, çünki Laravel-də Eloquent lazy loading ilə bu problem demək olar ki, default olaraq yaranır.

## Niyə Vacibdir
N+1 problem development-da gözə dəymir (az data ilə sürətlidir), production-da yüzlərlə query-ə çevrilir. 100 user varsa 101 sorğu — 10.000 user varsa 10.001 sorğu. Database connection pool-u tükənə bilər, response time onlarca saniyəyə çata bilər. İnterviewer bu sualla sizin ORM-i başa düşüb-anladığınızı, debug etmə bacarığınızı, həll yollarını bilibməmənizi yoxlayır.

## Əsas Anlayışlar

- **N+1 query problem**: 1 list query + N detail query — hər əlaqəli record üçün ayrıca sorğu. 100 user = 101 sorğu vs eager loading ilə 2 sorğu.
- **Lazy Loading**: ORM default davranışı — əlaqəli datanı yalnız istifadə ediləndə (accessed) yükləyir. `$user->posts` ilk dəfə oxunanda query çalışır.
- **Eager Loading**: İlk sorğu ilə birgə əlaqəli datanı da yükləyir. Laravel: `with()`, Django: `prefetch_related()`, Rails: `includes()`.
- **SELECT N+1**: 100 user = 1 (users) + 100 (posts) = 101 query. Eager loading ilə: 1 (users) + 1 (posts WHERE user_id IN (...)) = 2 query.
- **Nested N+1**: Posts → Comments → Authors. Hər səviyyədə N+1. 100 post × 10 comment × author = 1 + 100 + 1000 sorğu!
- **DataLoader pattern**: GraphQL kontekstindən gəlir. Request-ləri batch edir, deduplication edir. Hər resolver yalnız ID verir, DataLoader batch sorğu çalışdırır.
- **Batch fetching**: `WHERE id IN (1,2,3...)` — N ayrı sorğu əvəzinə 1 sorğu. Eager loading-in arxasındakı mexanizm.
- **Query log**: Development-da sorğuları izləmək — `DB::getQueryLog()`, Laravel Telescope, Laravel Debugbar, Clockwork.
- **JOIN vs N+1**: JOIN daha az sorğu, lakin data duplication; eager loading iki ayrı sorğu amma clean separation. JOIN daha sürətli ola bilər, amma daha mürəkkəb.
- **withCount vs with**: `withCount('posts')` — subquery ilə say hesablanır, post data-sı yüklənmir. `with('posts')` — bütün post data-sı.
- **withSum, withMax, withMin, withAvg**: Larvel 8+ — aggregate eager loading. `User::withSum('orders', 'total_amount')`.
- **HasManyThrough**: ORM-in deep eager loading-i — 3+ səviyyəli relation. `User → Posts → Comments`.
- **Chunk processing**: Yaddaş problemini həll edir, lakin N+1-i yox — birlikdə düşünmək lazımdır.
- **GraphQL N+1**: Resolver-lər arası batch etmə problemi. Hər field resolver-i ayrıca çalışır — DataLoader olmadan N+1 qaçınılmazdır.
- **REST API N+1**: Endpoint-dən list alıb hər element üçün detail endpoint çağırmaq — API dizayn problemi. `include` / `embed` parametri ilə həll.
- **Subquery vs eager loading**: `withCount('orders')` — subquery ilə. `with('orders')->get()` + `count()` — N+1. Fərqi bilmək lazımdır.
- **Lazy eager loading**: `$collection->loadMissing('relation')` — sonradan əlavə yükləmə. Şərti əlaqə lazy yükləməsi üçün.

## Praktik Baxış

**Interview-da yanaşma:**
- Kod nümunəsi verin: "5 user = 1 sorğu, sonra hər birinin postları = 5 sorğu, cəmi 6"
- Həll yollarını say: eager loading, JOIN, DataLoader, caching
- "Bunu necə detect edirsiniz?" — Telescope, Debugbar, query log, test assertion

**Follow-up suallar interviewerlər soruşur:**
- "Eager loading həmişə daha yaxşıdır?" — Bəzən N+1 qəbul edilir (az data, nadirən çağrılır)
- "GraphQL-də N+1 necə həll olunur?" — DataLoader
- "100.000 user-in postlarını yükləməlisiniz — necə edərdiniz?"
- "`withCount` nə zaman daha yaxşıdır, `with('posts')->count()` nə zaman?"
- "Nested N+1 nədir, 3 səviyyəli nümunə versəniz?"
- "Chunk + N+1 birlikdə olduğunda nə baş verir?"

**Ümumi candidate səhvləri:**
- "Eager loading həmişə lazımdır" demək — kiçik, az istifadə olunan hissələrdə overhead ola bilər
- Nested N+1-i qeyd etməmək
- `withCount` vs `with()->count()` fərqini bilməmək
- GraphQL DataLoader-i bilməmək

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- GraphQL DataLoader-i bilmək
- "Query count-u monitor etmişik, threshold keçəndə alert" demək
- Nested N+1 nümunəsi vermək
- Test assertion ilə N+1 regression-ı tutmaq

## Nümunələr

### Tipik Interview Sualı
"Bu Laravel kodu nə qədər sorğu çalışdırır? Necə optimize edərdiniz?"
```php
$users = User::all();
foreach ($users as $user) {
    echo $user->posts->count();
}
```

### Güclü Cavab
"Bu klassik N+1 problemidir.

`User::all()` — 1 sorğu: `SELECT * FROM users`.
`$user->posts` — hər user üçün 1 ayrı sorğu: `SELECT * FROM posts WHERE user_id = X`.
100 user varsa: 1 + 100 = 101 sorğu. 10.000 user varsa: 10.001 sorğu.

Həll 1 — `withCount`: `User::withCount('posts')->get()` — 1 sorğu: subquery ilə count hesablanır. `$user->posts_count` artıq memory-dədir.

Həll 2 — eager loading: `User::with('posts')->get()` — 2 sorğu: 1 users, 1 posts WHERE user_id IN (...). Sonra `$user->posts->count()` memory-dən hesablanır.

Həll 3 — JOIN: `User::select('users.*')->selectRaw('COUNT(posts.id) as posts_count')->leftJoin('posts', 'posts.user_id', '=', 'users.id')->groupBy('users.id')->get()` — 1 sorğu.

Monitoring üçün Laravel Telescope-da duplicate query warning görünür. Development-da `DB::getQueryLog()` ilə sorğuları sayıram."

### Kod Nümunəsi — Əsas Həll Yolları

```php
// PROBLEM: N+1 (100 user = 101 sorğu)
$users = User::all();
// SELECT * FROM users

foreach ($users as $user) {
    echo $user->posts->count();
    // SELECT * FROM posts WHERE user_id = 1
    // SELECT * FROM posts WHERE user_id = 2
    // ... N dəfə!
}

// HƏLL 1: withCount — subquery ilə count (1 sorğu)
$users = User::withCount('posts')->get();
// SELECT users.*, (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id) AS posts_count
foreach ($users as $user) {
    echo $user->posts_count; // Memory-dən
}

// HƏLL 2: Eager loading (2 sorğu)
$users = User::with('posts')->get();
// SELECT * FROM users
// SELECT * FROM posts WHERE user_id IN (1, 2, 3, ...)
foreach ($users as $user) {
    echo $user->posts->count(); // Memory-dən
}

// HƏLL 3: withSum/withAvg (Laravel 8+, 1 sorğu)
$users = User::withSum('orders', 'total_amount')
             ->withCount('orders')
             ->get();
foreach ($users as $user) {
    echo $user->orders_sum_total_amount;
    echo $user->orders_count;
}

// HƏLL 4: JOIN ilə (1 sorğu, daha da sürətli)
$users = User::select('users.*')
    ->selectRaw('COUNT(posts.id) AS posts_count')
    ->leftJoin('posts', 'posts.user_id', '=', 'users.id')
    ->groupBy('users.id')
    ->get();
```

### Kod Nümunəsi — Nested N+1

```php
// Nested N+1: Users → Posts → Comments → Authors
// Users: 100 → Posts: 1000 → Comments: 10000 → Authors: ???

// PROBLEM: 3 səviyyəli N+1
$users = User::all(); // 1 sorğu
foreach ($users as $user) {
    foreach ($user->posts as $post) { // 100 sorğu
        foreach ($post->comments as $comment) { // 1000 sorğu
            echo $comment->author->name; // 10000 sorğu!
        }
    }
}
// Cəmi: 1 + 100 + 1000 + 10000 = 11101 sorğu!

// HƏLL: Deep eager loading
$users = User::with([
    'posts' => function ($query) {
        $query->select('id', 'user_id', 'title', 'published_at')
              ->where('published', true);
    },
    'posts.comments' => function ($query) {
        $query->select('id', 'post_id', 'author_id', 'body')
              ->latest()
              ->limit(5); // Hər post üçün max 5 şərh
    },
    'posts.comments.author:id,name,avatar', // Yalnız lazım olan column-lar
])->get();
// Cəmi: 4 sorğu — hər relation üçün 1
```

### Kod Nümunəsi — DataLoader Pattern (GraphQL)

```php
// GraphQL N+1 problemi
// Ssenari: 100 post, hər biri üçün author resolver çalışır → 100 ayrı SELECT

// PROBLEM: Hər resolver ayrıca sorğu
class PostType extends GraphQLType
{
    public function fields(): array
    {
        return [
            'author' => [
                'type' => GraphQL::type('User'),
                'resolve' => function (Post $post) {
                    // HƏR POST ÜÇÜN AYRI SORGU!
                    return User::find($post->author_id);
                }
            ]
        ];
    }
}

// HƏLL: DataLoader pattern — batch + cache
class UserLoader
{
    private array $buffer  = [];
    private array $cache   = [];
    private bool  $loading = false;

    // Yükləməni buffer-ə əlavə et
    public function load(int $userId): ?User
    {
        if (!isset($this->cache[$userId])) {
            $this->buffer[] = $userId;
        }
        return $this->cache[$userId] ?? null;
    }

    // Bütün buffer-i bir sorğuda yüklə
    public function flush(): void
    {
        if (empty($this->buffer) || $this->loading) return;
        $this->loading = true;

        $uniqueIds = array_unique($this->buffer);
        $users     = User::whereIn('id', $uniqueIds)
                         ->select('id', 'name', 'email', 'avatar')
                         ->get()
                         ->keyBy('id');

        foreach ($uniqueIds as $id) {
            $this->cache[$id] = $users[$id] ?? null;
        }

        $this->buffer  = [];
        $this->loading = false;
    }
}

// Context-ə əlavə et, hər request üçün yeni loader
class GraphQLContext
{
    public UserLoader $userLoader;

    public function __construct()
    {
        $this->userLoader = new UserLoader();
    }
}

// Resolver-da
'resolve' => function (Post $post, $args, GraphQLContext $context) {
    return $context->userLoader->load($post->author_id);
    // flush() request cycle sonunda çağırılır
    // Nəticə: 100 post üçün 1 sorğu
}
```

### Kod Nümunəsi — Detect + Test

```php
// Laravel Debugbar ilə görünür: "101 queries"

// Manual query log
DB::enableQueryLog();
$users = User::all();
foreach ($users as $u) { $u->posts; }
$queries = DB::getQueryLog();
Log::info('Query count: ' . count($queries)); // 101

// Test-lərdə N+1 regression tutmaq
class UserListTest extends TestCase
{
    public function test_user_list_does_not_have_n_plus_1(): void
    {
        // 20 user, hər birinin 5 postu
        User::factory(20)->has(Post::factory(5))->create();

        DB::enableQueryLog();
        $response = $this->getJson('/api/users?include=posts_count');
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());

        // 2-dən çox olmamalıdır: 1 users + 1 counts
        $this->assertLessThanOrEqual(2, $queryCount,
            "N+1 detected! {$queryCount} queries: " .
            collect(DB::getQueryLog())->pluck('query')->implode("\n")
        );
    }
}

// Chunk ilə N+1 kombinasiyası — tuzaq
// chunk yaddaş problemini həll edir, N+1-i deyil!
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        echo $user->posts->count(); // Hər chunk üçün N+1!
    }
});

// Düzgün: chunk + eager loading
User::with('posts')->chunk(100, function ($users) {
    foreach ($users as $user) {
        echo $user->posts->count(); // Memory-dən
    }
});
```

### Attack/Failure Nümunəsi — Production Outage

```
Ssenari: Dashboard sayfası production-da timeout verdi

Debugging:
1. New Relic / Telescope-da: response time 8s (normal 200ms)
2. Query log: 8023 sorğu!
3. Analiz:
   - /admin/users endpoint-i
   - 400 user var
   - Hər user üçün: orders query (400 sorğu)
   - Hər order üçün: items query (hər user ~5 order = 2000 sorğu)
   - Hər item üçün: product query (~10 item = 20000 sorğu!)
   - Cəmi: 22401 sorğu → timeout

4. Kod baxışı:
   - $users = User::all(); // 1
   - foreach $users: $user->orders // N
   - foreach $orders: $order->items // N×M
   - foreach $items: $item->product // N×M×K

Həll:
$users = User::with([
    'orders.items.product'
])->paginate(50); // Pagination da əlavə edildi
// 4 sorğu, 200ms

Dərs:
- N+1 development-da gözə dəymir (10 test user)
- Production-da real load ilə görünür
- Test-lərdə query assertion lazımdır
- Pagination olmadan bütün record-ları yükləmək ikinci problemdir
```

## Praktik Tapşırıqlar

- Laravel-də N+1 yaradın, Debugbar ilə query sayını görün, `with()` ilə düzəldin, fərqi ölçün
- 3 səviyyəli nested N+1 (users → posts → comments → authors) yaradın, `EXPLAIN ANALYZE` ilə hər versiya üçün query plan baxın
- Query count assertion ilə test yazın — CI-da N+1 regression-ı tutun
- `chunk(100)` ilə işləyən loop-da N+1 varlığını yoxlayın, düzəldin
- GraphQL endpoint-iniz varsa DataLoader olmadan sorğu sayını saydırın, DataLoader ilə müqayisə edin
- `withCount` vs `with('posts')->count()` — hər ikisi üçün `EXPLAIN ANALYZE` görün, fərqi anlayın
- Dashboard endpoint-inizdə query count threshold testi yazın: "50-dən çox sorğu gedirsə test fail etsin"

## Əlaqəli Mövzular
- `05-query-optimization.md` — EXPLAIN ANALYZE ilə sorğu analizi
- `04-index-types.md` — Eager loading sorğularında IN (...) query üçün index
- `03-normalization-denormalization.md` — Denormalization bəzən N+1-i azaldır
- `09-connection-pooling.md` — N+1 connection count-unu artırır — pool tükənə bilər
