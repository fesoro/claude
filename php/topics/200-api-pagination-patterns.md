# API Pagination Patterns — Offset, Cursor, Keyset, Page

## Mündəricat
1. [Niyə pagination?](#niyə-pagination)
2. [Offset pagination](#offset-pagination)
3. [Page pagination](#page-pagination)
4. [Cursor pagination](#cursor-pagination)
5. [Keyset pagination](#keyset-pagination)
6. [Performance müqayisəsi](#performance-müqayisəsi)
7. [GraphQL Relay-style cursor](#graphql-relay-style-cursor)
8. [Laravel implementasiyası](#laravel-implementasiyası)
9. [Real-world bug-lar](#real-world-bug-lar)
10. [Best practices](#best-practices)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə pagination?

```
Problem:
  10M user-li DB-də GET /users → server crash, browser freeze
  Limit lazımdır: 10/20/50 per page

Pagination strategiyaları:
  - Offset/Limit (page=N&per_page=20)
  - Page-based (səhifə nömrəsi)
  - Cursor-based (prev/next opaque token)
  - Keyset (sort field-ə görə tracking)

Tələblər:
  ✓ Performance (deep pagination yavaşlamasın)
  ✓ Stable (yeni record əlavə olarsa duplikat yox)
  ✓ Cacheable (CDN dostu)
  ✓ Easy to implement
```

---

## Offset pagination

```sql
-- Klassik
SELECT * FROM users
ORDER BY id
LIMIT 20 OFFSET 0;        -- Page 1

SELECT * FROM users
ORDER BY id
LIMIT 20 OFFSET 20;       -- Page 2

SELECT * FROM users
ORDER BY id
LIMIT 20 OFFSET 1000000;  -- Page 50,001 — YAVAŞ! O(N)
```

```
DB internals:
  OFFSET 1M → DB ilk 1M sətri SCAN edir, sonra atır
  Indeks olsa belə "scan + skip" patternidir
  Postgres/MySQL üçün böyük offset = milyon sətrin oxunması

Performance:
  Page 1:        ~1ms
  Page 100:      ~5ms
  Page 1000:     ~50ms
  Page 100000:   ~5000ms  (5 saniyə!)

Üstünlük:
  ✓ Sadə (frontend page count göstərə bilir)
  ✓ "Jump to page N"
  ✓ Total count asan (COUNT(*))

Çatışmazlıq:
  ✗ Deep pagination ÇOX YAVAŞ
  ✗ Concurrent insert → duplicate / missed (data shift)
  ✗ DB scan həm CPU həm memory yeyir
```

---

## Page pagination

```
GET /users?page=2&per_page=20
Response:
  {
    "data": [...],
    "page": 2,
    "per_page": 20,
    "total": 1234,
    "total_pages": 62
  }

Internal: SQL eyni — OFFSET = (page - 1) × per_page
  Page 2, per_page=20 → OFFSET 20

Eyni problemlər offset ilə.

Use case:
  Admin dashboard
  Search results (5-50 page)
  "Page 1 of 10" UX
```

---

## Cursor pagination

```
Cursor = "next page-i fetch etmək üçün opaque token".
Backend sort field və last record-dan generate edir.

Request:  GET /users?cursor=eyJpZCI6MTAwfQ&limit=20
Response:
  {
    "data": [...],
    "next_cursor": "eyJpZCI6MTIwfQ",   // base64({"id": 120})
    "prev_cursor": null
  }

Next page: GET /users?cursor=eyJpZCI6MTIwfQ&limit=20
```

```sql
-- ID-based cursor
SELECT * FROM users
WHERE id > :last_id     -- WHERE clause, OFFSET YOX
ORDER BY id
LIMIT 20;

-- Performance: O(log N) — index seek
-- Page 1:    ~1ms
-- Page 1000: ~1ms (eyni!)

-- Composite cursor (sort field + tie-breaker)
SELECT * FROM users
WHERE (created_at, id) > (:last_created_at, :last_id)
ORDER BY created_at, id
LIMIT 20;
```

```php
<?php
// Encode cursor (frontend opaque)
function encodeCursor(array $data): string
{
    return base64_encode(json_encode($data));
}

function decodeCursor(string $cursor): array
{
    return json_decode(base64_decode($cursor), true);
}

// Controller
function list(Request $request): JsonResponse
{
    $cursor = $request->input('cursor');
    $limit = (int) $request->input('limit', 20);
    
    $query = User::orderBy('id');
    
    if ($cursor) {
        $data = decodeCursor($cursor);
        $query->where('id', '>', $data['id']);
    }
    
    $users = $query->limit($limit + 1)->get();   // limit+1 — has next?
    
    $hasMore = $users->count() > $limit;
    $users = $users->take($limit);
    
    return response()->json([
        'data'        => $users,
        'next_cursor' => $hasMore ? encodeCursor(['id' => $users->last()->id]) : null,
    ]);
}
```

```
Üstünlük:
  ✓ Performance — bütün page-lər eyni sürət
  ✓ Concurrent insert problem yox
  ✓ Infinite scroll üçün ideal
  ✓ Stateless (cursor opaque)

Çatışmazlıq:
  ✗ Random page jump yoxdur ("Go to page 50")
  ✗ Total count yox (asan)
  ✗ Backward navigation çətin (prev_cursor saxlamalısan)
  ✗ Sort field qoyulmalıdır (consistency)
```

---

## Keyset pagination

```
Keyset = cursor pagination-ın "exposed" forması.
Token əvəzinə sort field-i URL-də açıq:

GET /users?after_id=100&limit=20

Tipik: ID, created_at, hybrid
```

```sql
-- ID-based
SELECT * FROM users
WHERE id > 100
ORDER BY id
LIMIT 20;

-- Created_at + ID (tie-breaker)
SELECT * FROM users
WHERE (created_at, id) > ('2026-04-01', 100)
ORDER BY created_at, id
LIMIT 20;

-- Hybrid: birdən çox sort
SELECT * FROM products
WHERE (price, id) > (99.99, 5000)   -- composite cursor
ORDER BY price, id
LIMIT 20;
```

```
Üstünlük:
  ✓ Cursor kimi sürətli
  ✓ URL-də açıq (debug, share asan)
  ✓ Backend-də cursor encoding yoxdur

Çatışmazlıq:
  ✗ Internal field açılır (DB schema leakage)
  ✗ Cursor encoding ilə daha "stable" deyil
```

---

## Performance müqayisəsi

```sql
-- Test: 10M row table

-- OFFSET pagination
EXPLAIN ANALYZE SELECT * FROM users ORDER BY id LIMIT 20 OFFSET 5000000;
-- Execution time: 3500 ms
-- Rows scanned: 5,000,020

-- CURSOR pagination
EXPLAIN ANALYZE SELECT * FROM users WHERE id > 5000000 ORDER BY id LIMIT 20;
-- Execution time: 0.5 ms
-- Rows scanned: 20

-- 7000× sürətlənmə!
```

```
Real-world test (Postgres, 10M users):

                   | Page 1     | Page 100   | Page 10,000 | Page 500,000
─────────────────────────────────────────────────────────────────────────
OFFSET pagination | 1 ms       | 5 ms       | 200 ms      | 3500 ms
PAGE pagination   | 1 ms       | 5 ms       | 200 ms      | 3500 ms (eyni)
CURSOR pagination | 1 ms       | 1 ms       | 1 ms        | 1 ms
KEYSET pagination | 1 ms       | 1 ms       | 1 ms        | 1 ms

Verdict:
  Public API (high-volume):    cursor / keyset
  Admin / search results:       offset (page count vacibdir)
  Infinite scroll feed:         cursor
```

---

## GraphQL Relay-style cursor

```graphql
# Standardlaşdırılmış cursor pagination (Facebook Relay)
query {
  users(first: 20, after: "Y3Vyc29yOjEwMA==") {
    edges {
      cursor                    # per-edge cursor
      node {
        id
        name
      }
    }
    pageInfo {
      hasNextPage
      hasPreviousPage
      startCursor
      endCursor
    }
    totalCount                  # optional
  }
}
```

```
Üstünlük:
  ✓ Standartlaşdırılıb (tooling: Apollo, urql cache)
  ✓ Bidirectional (after, before, first, last)
  ✓ Per-edge cursor (granular)

Lighthouse PHP-də:
  type Query {
    users(first: Int!, after: String): UserConnection! @paginate(type: CONNECTION)
  }
```

---

## Laravel implementasiyası

```php
<?php
// 1. OFFSET (Laravel default)
$users = User::paginate(20);   // ?page=2

// Response:
// {
//   "data": [...],
//   "current_page": 2,
//   "per_page": 20,
//   "total": 1234,
//   "last_page": 62,
//   "links": [...]
// }

// 2. SIMPLE PAGINATION (no count)
$users = User::simplePaginate(20);
// Total/lastpage YOX, AMMA next/prev var

// 3. CURSOR PAGINATION (Laravel 8+)
$users = User::orderBy('id')->cursorPaginate(20);
// ?cursor=eyJpZCI6MTAwfQ
// Response:
// {
//   "data": [...],
//   "next_cursor": "eyJ...",
//   "prev_cursor": "eyJ..."
// }

// API Resource ilə
class UserCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'next_cursor' => $this->resource->nextCursor()?->encode(),
                'prev_cursor' => $this->resource->previousCursor()?->encode(),
            ],
        ];
    }
}

// Custom keyset
$lastId = $request->input('after_id');
$users = User::where('id', '>', $lastId)
    ->orderBy('id')
    ->limit(20)
    ->get();
```

---

## Real-world bug-lar

```
BUG 1: Sort field unique deyil
  ORDER BY created_at LIMIT 20
  
  Eyni created_at-li 5 row var → cursor naqis qalır
  Həll: tie-breaker ID
  ORDER BY created_at, id

BUG 2: Cursor encoding pozulub
  Frontend cursor saxlayır, backend dəyişir
  Versioning yoxdur
  Həll: cursor JSON-da version field

BUG 3: Hard delete + offset = duplicate
  Page 1: id 1-20
  User 5 silinir
  Page 2: id 21-40 (amma əslində offset 20 — id 22 başlamalı idi)
  Həll: cursor pagination

BUG 4: Total count yavaş
  COUNT(*) FROM users WHERE complex_filter — milyonlarla sətir
  Həll: 
    - "10000+" yaxınlaşdırılmış count
    - Pre-computed count cache
    - Approximate count (Postgres reltuples)

BUG 5: Sorting değişikliği
  ?sort=newest cursor encoded → user ?sort=popular dəyişir
  Cursor invalidate olur
  Həll: sort dəyişəndə cursor reset

BUG 6: Cursor token expiration yoxdur
  6 ay əvvəlki cursor "id > 100" — DB-də indi 100 başqa məna kəsb edir
  Həll: cursor TTL (timestamp embed)

BUG 7: Per_page max yox
  ?per_page=100000000 → DB OOM
  Həll: max 100 enforce
```

---

## Best practices

```
✓ Default per_page = 20-50
✓ Max per_page = 100 (DOS qarşı)
✓ Cursor opaque (base64 + JSON)
✓ Cursor versioning (gələcək schema dəyişikliyi)
✓ Sort field stable (id ilə tie-break)
✓ Index on sort field MƏCBURI
✓ Total count optional (slow olarsa)
✓ next_cursor = null → end of data signal
✓ HATEOAS links (next, prev, first, last URLs)
✓ Cache-Control header (cursor pages immutable)

❌ OFFSET 100k+ public API-da
❌ Cursor URL-də plain text (JSON expose)
❌ Sort dəyişiklikdə cursor saxlama
❌ COUNT(*) hər request-də (cache lazımdır)
```

---

## İntervyu Sualları

- Offset pagination niyə deep page-lərdə yavaşdır?
- Cursor pagination performance niyə eynidir bütün səhifələrdə?
- Cursor token niyə opaque (base64) saxlanır?
- Tie-breaker (composite cursor) nə vaxt lazımdır?
- "Jump to page N" cursor pagination-da niyə mümkün deyil?
- Concurrent insert offset pagination-da hansı bug yaradır?
- Total count `COUNT(*)` yavaşdırsa hansı alternativlər var?
- GraphQL Relay cursor pagination spec niyə standartdır?
- Laravel `cursorPaginate` arxa planda nə edir?
- Search results üçün hansı pagination?
- Infinite scroll feed üçün hansı pagination?
- Cursor versioning niyə vacibdir?
