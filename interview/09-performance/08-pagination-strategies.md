# Pagination Strategies (Offset, Cursor, Keyset) (Middle ⭐⭐)

## İcmal

Pagination — böyük dataset-i hissə-hissə vermə strategiyasıdır. Üç əsas yanaşma mövcuddur: OFFSET/LIMIT (klassik), Cursor-based (token), Keyset (seek method). Hər birinin öz güclü tərəfi, zəif nöqtəsi, uyğun use case-i var. Backend developer üçün bu fərqi bilmək, müsahibədə isə hansı ssenarioda hansını seçəcəyini izah etmək vacibdir.

## Niyə Vacibdir

10K istifadəçi olan sistemdə `OFFSET 9900 LIMIT 100` — DB 10K row oxuyur, 9900-ünü atar, 100-ünü qaytarır. Bu "deep pagination problem"-dir. 1M-lik cədvəldə son səhifəni açmaq bütün cədvəli scan edər. Real sistemlərdə — sosial feed, email inbox, axtarış nəticəsi — yanlış pagination strategiyası production-da ciddi yük yaradır.

## Əsas Anlayışlar

- **Offset/Limit (OFFSET-based):**
  - `SELECT * FROM orders LIMIT 20 OFFSET 100`
  - Ən sadə, ən geniş yayılmış
  - Problem: dərin offset-lərdə DB hamısını oxuyur
  - Problem: yeni data gəlsə row-lar sürüşür (duplicate/skip)
  - Yaxşı: az data, random page atlamaq lazımdır

- **Cursor-based pagination:**
  - Son element-in unique identifier-i token kimi qaytarılır
  - Client növbəti istəkdə cursor göndərir
  - `WHERE id > :cursor LIMIT 20`
  - Problem: random page atlamaq olmur, yalnız next/prev
  - Yaxşı: social feed, infinite scroll, API

- **Keyset pagination (seek method):**
  - Cursor-un daha güclü versiyası
  - Composite key ilə: `WHERE (created_at, id) < (:ts, :id)`
  - Index-i tam istifadə edir, dərin pagination problem yoxdur
  - Real-time sort ilə işləyir
  - Yaxşı: böyük cədvəl, real-time data, high performance

- **Page-based vs Token-based:**
  - Page-based: `?page=5` — OFFSET ilə
  - Token-based: `?after=eyJpZCI6MTIzfQ==` — cursor/keyset ilə

- **Keyset key seçimi:**
  - Unique + monotonic olmalıdır
  - Composite: `(created_at DESC, id DESC)` — eyni timestamp-lı row-lar üçün tiebreaker
  - Index olmalıdır

- **Laravel pagination:**
  - `paginate()` — OFFSET/LIMIT, `LengthAwarePaginator`
  - `simplePaginate()` — OFFSET, yalnız next/prev
  - `cursorPaginate()` — Laravel 8+, keyset-based cursor
  - `lazyPaginate()` / `cursor()` — server-side, memory-safe

## Praktik Baxış

**Offset/Limit (klassik):**

```php
// Laravel
$orders = Order::orderBy('created_at', 'desc')
    ->paginate(20); // page=1, offset=0; page=2, offset=20

// SQL:
// SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 0

// Response:
// total, per_page, current_page, last_page, data[]
```

**Deep pagination problemi göstərişi:**

```sql
-- 10M cədvəl, son səhifəyə get
SELECT * FROM orders ORDER BY created_at DESC LIMIT 20 OFFSET 9999980;
-- DB 10M row oxuyur, 9.999.980-ni atar → çox yavaş!

-- EXPLAIN:
-- Sort | cost=2500000 (very high)
-- Seq Scan on orders
```

**Cursor pagination (Laravel 8+):**

```php
// cursorPaginate — Laravel built-in keyset
$orders = Order::orderBy('created_at', 'desc')
    ->orderBy('id', 'desc') // tiebreaker
    ->cursorPaginate(20);

// Response:
// {
//   "data": [...],
//   "next_cursor": "eyJjcmVhdGVkX2F0IjoiMjAyNC0xMi0wMSIsImlkIjoxMjN9",
//   "prev_cursor": "...",
//   "next_page_url": "?cursor=eyJ..."
// }

// Növbəti səhifə:
$nextOrders = Order::orderBy('created_at', 'desc')
    ->orderBy('id', 'desc')
    ->cursorPaginate(20, ['*'], 'cursor', $cursor);
```

**Manual keyset implementation:**

```php
// API: GET /orders?after_id=123&after_ts=2024-01-15T10:00:00Z
public function index(Request $request): JsonResponse
{
    $query = Order::orderBy('created_at', 'desc')
        ->orderBy('id', 'desc')
        ->limit(21); // 21 alırıq, 21-ci varsa "daha var" deməkdir

    if ($request->has('after_id') && $request->has('after_ts')) {
        $afterId = $request->integer('after_id');
        $afterTs = $request->input('after_ts');

        // Keyset condition
        $query->where(function ($q) use ($afterTs, $afterId) {
            $q->where('created_at', '<', $afterTs)
              ->orWhere(function ($q2) use ($afterTs, $afterId) {
                  $q2->where('created_at', '=', $afterTs)
                     ->where('id', '<', $afterId);
              });
        });
    }

    $items = $query->get();
    $hasMore = $items->count() === 21;

    if ($hasMore) {
        $items->pop(); // 21-cini sil
    }

    $lastItem = $items->last();

    return response()->json([
        'data' => $items,
        'has_more' => $hasMore,
        'next_cursor' => $hasMore ? base64_encode(json_encode([
            'id' => $lastItem->id,
            'ts' => $lastItem->created_at->toIso8601String(),
        ])) : null,
    ]);
}
```

**Index üçün:**

```sql
-- Keyset pagination-ı effektiv edən composite index
CREATE INDEX idx_orders_cursor ON orders (created_at DESC, id DESC);

-- Bu index ilə keyset WHERE dərhal index-ə düşür
-- Deep pagination problem yoxdur: index walk, seek
```

**Trade-offs müqayisəsi:**

| Xüsusiyyət | Offset | Cursor | Keyset |
|---|---|---|---|
| Random page atlama | ✅ | ❌ | ❌ |
| Performance (dərin) | ❌ | ✅ | ✅ |
| Total count | ✅ | ❌ (bahalı) | ❌ |
| Real-time safe | ❌ (sürüşmə) | ✅ | ✅ |
| Sort flexibility | ✅ | Məhdud | Məhdud |
| Implementation | Sadə | Orta | Çətin |

**Common mistakes:**
- Böyük cədvəldə `paginate()` istifadə etmək (OFFSET problem)
- `count()` ilə total hesablamaq sonra `paginate()` — 2 query
- Cursor-u imzalamadan client-ə vermək (tamper riski)
- Keyset index olmadan implementasiya (keyset effekti yox)
- API-də pagination olmadan `get()` istifadə etmək

## Nümunələr

### Real Ssenari: Slow admin panel

```
Problem: "Users" admin paneli page 500+ olduqda 12 saniyə çəkir.
SQL: SELECT * FROM users LIMIT 20 OFFSET 9980
Cədvəl: 500K user

EXPLAIN: Seq Scan, rows=500K, sort on disk

Həll seçimi:
- Admin-in random page atlama ehtiyacı var → cursor uyğun deyil
- Amma deep pagination problem var

Kompromis həll:
1. OFFSET-in 10000-dən yuxarı getməsini qadağan et (UI-da "search" göstər)
2. ID-based keyset ilə "load more" düyməsi əlavə et
3. Total count-u approximate etmək: pg_class stats (real-time yox)

Nəticə: page 500 → artıq mümkün deyil (UI), axtarış var
```

### Kod Nümunəsi

```php
<?php

// Cursor token encode/decode
class CursorEncoder
{
    public static function encode(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    public static function decode(string $cursor): array
    {
        $decoded = json_decode(base64_decode($cursor), true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid cursor');
        }

        return $decoded;
    }
}

// Generic keyset paginator
class KeysetPaginator
{
    public function paginate(
        Builder $query,
        int $perPage,
        ?string $cursor,
        array $orderColumns, // [['column' => 'created_at', 'direction' => 'desc'], ...]
    ): array {
        if ($cursor) {
            $decoded = CursorEncoder::decode($cursor);
            $this->applyCursorCondition($query, $orderColumns, $decoded);
        }

        foreach ($orderColumns as $col) {
            $query->orderBy($col['column'], $col['direction']);
        }

        $items = $query->limit($perPage + 1)->get();
        $hasMore = $items->count() > $perPage;

        if ($hasMore) {
            $items = $items->take($perPage);
        }

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $last = $items->last();
            $nextCursor = CursorEncoder::encode(
                collect($orderColumns)->mapWithKeys(fn($col) => [
                    $col['column'] => $last->{$col['column']},
                ])->toArray()
            );
        }

        return [
            'data' => $items,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    private function applyCursorCondition(Builder $query, array $columns, array $values): void
    {
        // İlk sütun üzrə keyset condition
        $firstCol = $columns[0];
        $direction = $firstCol['direction'] === 'desc' ? '<' : '>';

        $query->where($firstCol['column'], $direction, $values[$firstCol['column']]);
    }
}
```

## Praktik Tapşırıqlar

1. **Deep pagination benchmark:** 1M row olan cədvəldə `OFFSET 999980 LIMIT 20` vs keyset `WHERE id < 20` performance fərqini ölç.

2. **cursorPaginate implement:** Laravel `cursorPaginate()` istifadə edərək bir API endpoint yaz, `next_cursor` token-i decode edərək içindəkilərə bax.

3. **Cursor encoder:** JWT-signed cursor implement et — cursor tamper olsa 400 qaytarsın.

4. **Infinite scroll:** Vue.js + Laravel API ilə `after_id` əsasında infinite scroll implement et.

5. **Total count bez OFFSET:** `pg_class` stats-dan approximate row count almağı yoxla, `SELECT reltuples FROM pg_class WHERE relname = 'orders'`.

## Əlaqəli Mövzular

- `02-query-optimization.md` — Pagination query-lərini optimallaşdırmaq
- `15-indexing-strategy.md` — Keyset üçün index strategiyası
- `04-lazy-loading.md` — Böyük dataset-i lazy yükləmək
- `09-async-batch-processing.md` — Pagination ilə batch processing
- `03-caching-layers.md` — Pagination result-larını cache-ləmək
