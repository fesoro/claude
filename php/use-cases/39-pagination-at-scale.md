# Pagination at Scale (Senior)

## Problem necə yaranır?

`OFFSET/LIMIT` kiçik cədvəllərdə normal işləyir. Milyonlarla sətirdə isə:

**Performance:** `SELECT * FROM orders LIMIT 20 OFFSET 1000000` — MySQL 1,000,020 sətir oxuyur, yalnız 20-ni qaytarır. OFFSET nə qədər böyüksə o qədər yavaş. Index bu prosesi dayandırmır — DB OFFSET-ə qədər sequential scan edir.

**Page drift:** İstifadəçi 2-ci səhifəni oxuyur. Bu əsnada yeni sifariş əlavə edilir. 3-cü səhifəyə keçəndə eyni sifariş yenidən görünür (ya da birinci görünürdüsə — artıq yoxdur). OFFSET-based pagination real-time data ilə uyğun deyil.

---

## Keyset (Cursor) Pagination

**Fikir:** "Mənə ID=500-dən sonrakı 20 sifarişi ver" — DB indeksdən birbaşa o nöqtəyə atlayır, əvvəlki 500 sətiri oxumur.

***Fikir:** "Mənə ID=500-dən sonrakı 20 sifarişi ver" — DB indeksdən bi üçün kod nümunəsi:*
```sql
SELECT * FROM orders
WHERE user_id = 1
  AND (created_at, id) < (:last_created_at, :last_id)
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

**Composite cursor niyə?** `created_at` non-unique ola bilər — eyni millisecondda iki sifariş. `id` ilə tie-breaking: hər zaman deterministic sıralama.

**İndeks tələbi:** `(user_id, created_at DESC, id DESC)` — index seek, index scan yox. `EXPLAIN` ilə `Using index` görünməlidir.

---

## İmplementasiya

*Bu kod composite cursor ilə sürətli keyset pagination-ı həyata keçirən paginator sinifini və controller istifadəsini göstərir:*

```php
class CursorPaginator
{
    public function paginate(
        Builder $query,
        int     $limit,
        ?string $cursor,
        string  $sortColumn = 'id',
        string  $direction  = 'desc'
    ): array {
        if ($cursor) {
            $decoded = $this->decodeCursor($cursor);
            $op      = $direction === 'desc' ? '<' : '>';

            // Composite cursor: (created_at < X) OR (created_at = X AND id < Y)
            // Bu SQL tuple comparison-a ekvivalentdir
            $query->where(function ($q) use ($decoded, $sortColumn, $op) {
                $q->where($sortColumn, $op, $decoded[$sortColumn])
                  ->orWhere(function ($q2) use ($decoded, $sortColumn, $op) {
                      $q2->where($sortColumn, $decoded[$sortColumn])
                         ->where('id', $op, $decoded['id']);
                  });
            });
        }

        // limit+1 trick: has_more yoxlamaq üçün bir əlavə sətir alınır
        $items = $query
            ->orderBy($sortColumn, $direction)
            ->orderBy('id', $direction)
            ->limit($limit + 1)
            ->get();

        $hasMore    = $items->count() > $limit;
        $items      = $items->take($limit);
        $nextCursor = null;

        if ($hasMore && $items->isNotEmpty()) {
            $last       = $items->last();
            $nextCursor = $this->encodeCursor([
                $sortColumn => $last->{$sortColumn},
                'id'        => $last->id,
            ]);
        }

        return ['data' => $items, 'next_cursor' => $nextCursor, 'has_more' => $hasMore];
    }

    // base64: cursor opaque token-dir, client structure görməməlidir
    private function encodeCursor(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    private function decodeCursor(string $cursor): array
    {
        return json_decode(base64_decode($cursor), true);
    }
}

// Controller
class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $result = app(CursorPaginator::class)->paginate(
            query:      Order::where('user_id', $request->user()->id),
            limit:      20,
            cursor:     $request->query('cursor'),
            sortColumn: 'created_at',
            direction:  'desc',
        );

        return response()->json([
            'orders'      => OrderResource::collection($result['data']),
            'next_cursor' => $result['next_cursor'],
            'has_more'    => $result['has_more'],
        ]);
    }
}
```

---

## İndeks Strategiyası

*Bu kod cursor pagination üçün optimal composite index yaradılmasını və EXPLAIN ilə yoxlanmasını göstərir:*

```sql
-- Filter + cursor üçün covering index
CREATE INDEX idx_orders_user_created_id
ON orders (user_id, created_at DESC, id DESC);

-- EXPLAIN ilə yoxla — "Using index" görünməlidir, "Using filesort" yox
EXPLAIN SELECT * FROM orders
WHERE user_id = 1
  AND created_at < '2024-01-01'
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

---

## Böyük Cədvəllərdə COUNT problemi

`SELECT COUNT(*) FROM orders WHERE user_id = ?` hər request-də çağırılırsa problem yaranır:

*Bu kod böyük cədvəllərdə COUNT(*) performans problemlərini həll edən alternativ yanaşmaları göstərir:*

```php
// Yavaş: hər dəfə full scan
$total = Order::where('user_id', $userId)->count();

// Alternativ 1: Approximate count (MySQL information_schema)
// Dəqiq deyil, amma böyük cədvəllərdə çox sürətli
$approxTotal = DB::select("SELECT TABLE_ROWS FROM information_schema.TABLES
    WHERE TABLE_NAME = 'orders'"
)[0]->TABLE_ROWS;

// Alternativ 2: Counter cache — orders yaradılanda/silindikdə users.orders_count artır/azalır
$total = $user->orders_count; // O(1) — ayrı sütun

// Alternativ 3: Total count-u ayrı, az tez-tez çağırılan endpoint-ə köçür
// GET /orders/count — hər scroll-da deyil, yalnız ilk yükləmədə
```

---

## OFFSET vs Cursor — Müqayisə

| | OFFSET/LIMIT | Cursor |
|---|---|---|
| Performance | O(offset) — böyüdükcə yavaşlayır | O(1) — həmişə eyni |
| Random page | ✅ Mümkün | ❌ Yox |
| Page drift | ❌ Var | ✅ Yox |
| Sort flexibility | İstənilən | Yalnız indexed column |
| Total count | ✅ Asandır | ❌ Çətin/lazımsız |
| İstifadə | Admin panel, kiçik data | Infinite scroll, API |

---

## Hybrid yanaşma

Kiçik dataset üçün OFFSET, böyük dataset üçün cursor — eyni API-da:

*Bu kod eyni endpoint-də həm cursor, həm OFFSET pagination-ı dəstəkləyən hybrid yanaşmanı göstərir:*

```php
// Həm page-based, həm cursor-based dəstəkləyən endpoint
public function index(Request $request): JsonResponse
{
    if ($request->has('cursor')) {
        // Infinite scroll: cursor pagination
        return $this->cursorPaginate($request);
    }

    // Admin panel: OFFSET-based, lakin max page limit ilə
    $page = min((int) $request->get('page', 1), 100); // Max 100-cü səhifə
    return $this->offsetPaginate($request, $page);
}
```

---

## Anti-patterns

- **Böyük OFFSET-i cache-ləmək:** Hər dəfə DB-yə gedir, cache TTL bitdikdə yenə yavaş sorğu.
- **Cursor-suz filter dəyişmək:** Filter dəyişsə köhnə cursor fərqli data set üçündür → yanlış nəticə. Filter dəyişdikdə cursor sıfırlanmalıdır.
- **Non-indexed column ilə cursor:** Sort column-u index-də olmasa cursor sorğusu da yavaş olur.
- **OFFSET-i limitsiz buraxmaq:** Admin panel-də page=99999 istəyi DB-ni çökdürə bilər. Max page limit qoyulmalıdır.

---

## İntervyu Sualları

**1. OFFSET/LIMIT niyə böyük cədvəldə yavaşdır?**
DB `OFFSET N` üçün ilk N sətiri oxuyub atır. N=1,000,000 → 1M sətir oxunur, 20 qaytarılır. Index seek edə bilmir — OFFSET fiziki sıralama tələb edir.

**2. Cursor pagination page drift-i necə həll edir?**
Cursor: "bu ID-dən sonra". Yeni data əlavə edilsə ya da silinse cursor dəyişmir — siz həmin nöqtədən davam edirsiniz. OFFSET-dəki "yeni row gəldi, hamı bir sıra sürüşdü" problemi yoxdur.

**3. Composite cursor niyə lazımdır?**
Sadə cursor: `WHERE created_at < :last`. Eyni `created_at`-ə sahib iki sətir varsa biri daim skip edilir. Composite `(created_at, id)` unique kombinasiya — hər sətir dəqiq müəyyən edilir, heç biri skip edilmir.

**4. Nə vaxt OFFSET istifadə etmək məqsədəuyğundur?**
Kiçik cədvəl (<50K sətir), ya da user-in random page-ə keçməsi lazımdır (admin panel, search results). Cursor random page-ə keçidi dəstəkləmir — "20-ci səhifəyə get" mümkün deyil.

**5. Cursor-un təhlükəsizliyi necə təmin edilir?**
Cursor base64 encode edilib opaque token kimi göndərilir — client format bilmir. Decode edildikdən sonra parametr binding (`?` placeholder) ilə SQL-ə yerləşdirilir, birbaşa interpolasiya edilmir (SQL injection). İstəyə görə cursor HMAC ilə imzalana bilər ki, tampered cursor aşkarlansın.

**6. Elasticsearch/search engine ilə cursor pagination?**
Elasticsearch-in öz `search_after` mexanizmi var — SQL cursor-a analoqdur. Score-a görə sort edilmiş nəticələrdə cursor pagination işləmir (score dəyişkəndir). `search_after` + `sort: [{_score: desc}, {_id: desc}]` kombinasiyası deterministic nəticə verir.

---

## Anti-patternlər

**1. Cursor dəyərini client-ə plain text vermək**
`next_cursor: "2024-01-15 10:30:00"` kimi raw timestamp göndərmək — client cursor formatına bağımlı olur, backend dəyişdirilsə bütün client-lər pozulur. Cursor base64 ilə encode edilməli, format daxili implementation detalı olaraq qalmalıdır.

**2. Cursor-u user input-u kimi birbaşa SQL-ə yerləşdirmək**
`WHERE created_at < '$cursor'` — SQL injection riski. Cursor parametr binding (`?` placeholder) ilə istifadə edilməli, decode edildikdən sonra validasiya keçirilməlidir.

**3. Hər pagination sorğusunda `COUNT(*)` etmək**
`SELECT COUNT(*) FROM posts WHERE user_id = ?` hər səhifə sorğusunda icra etmək — böyük cədvəllərdə full scan, ciddi performans problemi. Total count ya ayrıca endpoint-ə köçürülməli, ya da approximate count (`EXPLAIN` rows) istifadə edilməli, ya da tamamilə çıxarılmalıdır.

**4. Sort column-unu index-ə daxil etməmək**
`ORDER BY created_at` ilə cursor pagination istifadə edib `created_at`-ə index qoymamaq — cursor sorğusu `WHERE created_at < ?` full table scan edir, OFFSET qədər yavaş olur. Sort + filter edilən bütün column-lar composite index-ə daxil edilməlidir.

**5. Keywort axtarışında cursor pagination istifadə etmək**
Full-text search nəticələrini relevance score-a görə sıralayıb cursor pagination tətbiq etmək — score dəyişkəndir, eyni cursor fərqli nəticə verir. Search nəticələri üçün ya OFFSET, ya da search engine-in (Elasticsearch) öz `search_after` mexanizmi istifadə edilməlidir.

**6. Silinmiş record-ların cursor-u pozmağını nəzərə almamaq**
Cursor-un göstərdiyi record silindikdə `WHERE id > :last_id` sorğusunun davranışını test etməmək — soft delete olmadan silmə cursor-u "atlatdıra" bilər. Cursor-based pagination soft delete ilə birlikdə işləyəndə `deleted_at IS NULL` şərtinin cursor sorğusundan önə keçməsi yoxlanılmalıdır.

**7. Admin panel-də OFFSET-i limitsiz buraxmaq**
`page=99999` kimi sorğulara heç bir məhdudiyyət qoymamaq — DB 99,999×20 sətir oxuyur, cavab verməyə bilər. Maximum page sayı ya da maximum OFFSET limiti (məsələn, 10,000 sətir) tətbiq edilməlidir; daha dərin tarixçə üçün filter/axtarış istifadə edilməlidir.
