# Pagination Patterns (Senior)

## İcmal

Pagination — böyük data set-lərini hissələrə bölərək müştəriyə qaytarma üsuludur. Hər approach-ın öz trade-off-u var: offset/limit sadə lakin yüksək offset-də yavaş; cursor-based sürətli lakin mürəkkəb; keyset pagination isə ən performanslı seçimdir.

Go-da pagination — generics ilə universal response wrapper, database sorğusu və HTTP handler bir-birindən ayrılmış şəkildə yazılır.

## Niyə Vacibdir

- Milyonlarla record saxlayan cədvəldən hamısını çəkmək — memory crash, timeout
- `OFFSET 1000000` — PostgreSQL ilk milyon sətri oxuyur, atır; yavaşlığa səbəb
- Real-time data-da offset pagination ghost records (silinmiş + yeni əlavə) göstərə bilər
- Mobile infinite scroll, API integration — cursor-based daha uyğundur

## Əsas Anlayışlar

### 1. Offset / LIMIT Pagination

Ən sadə, ən çox bilinen:

```sql
SELECT * FROM orders ORDER BY id DESC LIMIT 20 OFFSET 40;
-- 3-cü səhifə: OFFSET = (page-1) * per_page
```

**Problemi:** PostgreSQL `OFFSET 100000` yazanda öncəki 100k sətri oxuyub atır — `O(n)` mürəkkəbliyi.

```go
type OffsetPage struct {
    Page       int   `json:"page"`
    PerPage    int   `json:"per_page"`
    Total      int64 `json:"total"`
    TotalPages int   `json:"total_pages"`
    HasNext    bool  `json:"has_next"`
    HasPrev    bool  `json:"has_prev"`
}

type PagedResult[T any] struct {
    Data []T        `json:"data"`
    Meta OffsetPage `json:"meta"`
}

func paginateOffset[T any](db *sql.DB, query string, args []any, page, perPage int) (PagedResult[T], error) {
    if page < 1 {
        page = 1
    }
    if perPage < 1 || perPage > 100 {
        perPage = 20
    }

    offset := (page - 1) * perPage

    // Count sorğusu (baha ola bilər böyük cədvəllərdə)
    var total int64
    countSQL := "SELECT COUNT(*) FROM (" + query + ") t"
    db.QueryRow(countSQL, args...).Scan(&total)

    // Data sorğusu
    dataSQL := query + fmt.Sprintf(" LIMIT %d OFFSET %d", perPage, offset)
    rows, err := db.Query(dataSQL, args...)
    // ...

    totalPages := int(math.Ceil(float64(total) / float64(perPage)))
    return PagedResult[T]{
        Data: data,
        Meta: OffsetPage{
            Page: page, PerPage: perPage, Total: total,
            TotalPages: totalPages,
            HasNext: page < totalPages,
            HasPrev: page > 1,
        },
    }, nil
}
```

### 2. Cursor-Based Pagination

Cursor — client-in harada dayandığını göstərən opaque token-dir. Adətən son sətrin ID-si base64-encoded olur.

```go
type CursorPage struct {
    NextCursor string `json:"next_cursor,omitempty"`
    PrevCursor string `json:"prev_cursor,omitempty"`
    HasMore    bool   `json:"has_more"`
    Limit      int    `json:"limit"`
}

type CursorResult[T any] struct {
    Data []T        `json:"data"`
    Meta CursorPage `json:"meta"`
}

func encodeCursor(id int64) string {
    return base64.URLEncoding.EncodeToString([]byte(strconv.FormatInt(id, 10)))
}

func decodeCursor(cursor string) (int64, error) {
    b, err := base64.URLEncoding.DecodeString(cursor)
    if err != nil {
        return 0, err
    }
    return strconv.ParseInt(string(b), 10, 64)
}

func (r *OrderRepository) ListCursor(ctx context.Context, cursor string, limit int) (CursorResult[Order], error) {
    if limit <= 0 || limit > 100 {
        limit = 20
    }

    var rows *sql.Rows
    var err error

    if cursor == "" {
        rows, err = r.db.QueryContext(ctx,
            `SELECT id, user_id, total, created_at
             FROM orders ORDER BY id DESC LIMIT $1`, limit+1)
    } else {
        cursorID, err := decodeCursor(cursor)
        if err != nil {
            return CursorResult[Order]{}, fmt.Errorf("invalid cursor: %w", err)
        }
        rows, err = r.db.QueryContext(ctx,
            `SELECT id, user_id, total, created_at
             FROM orders WHERE id < $1 ORDER BY id DESC LIMIT $2`, cursorID, limit+1)
    }
    // ...

    // limit+1 alındı → limit-dən çox varsa hasMore=true
    hasMore := len(orders) > limit
    if hasMore {
        orders = orders[:limit]
    }

    var nextCursor string
    if hasMore {
        nextCursor = encodeCursor(orders[len(orders)-1].ID)
    }

    return CursorResult[Order]{
        Data: orders,
        Meta: CursorPage{NextCursor: nextCursor, HasMore: hasMore, Limit: limit},
    }, nil
}
```

### 3. Keyset Pagination (En Performanslı)

Cursor-based-in daha optimallaşdırılmış variantı. Index-i tam istifadə edir:

```sql
-- OFFSET yoxdur, WHERE şərti ilə filter edilir
SELECT id, name, created_at
FROM users
WHERE (created_at, id) < ($1, $2)  -- son alınan sətrin dəyərləri
ORDER BY created_at DESC, id DESC
LIMIT 20;
```

Bu sorğu `(created_at, id)` composite index-i tam istifadə edir — `O(log n)` mürəkkəbliyi.

```go
type KeysetCursor struct {
    CreatedAt time.Time `json:"created_at"`
    ID        int64     `json:"id"`
}

func encodeCursorKeyset(t time.Time, id int64) string {
    data, _ := json.Marshal(KeysetCursor{CreatedAt: t, ID: id})
    return base64.URLEncoding.EncodeToString(data)
}

func (r *UserRepository) ListKeyset(ctx context.Context, cursor string, limit int) ([]User, string, error) {
    var query string
    var args []any

    if cursor == "" {
        query = `SELECT id, name, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT $1`
        args = []any{limit + 1}
    } else {
        b, _ := base64.URLEncoding.DecodeString(cursor)
        var c KeysetCursor
        json.Unmarshal(b, &c)

        query = `SELECT id, name, created_at FROM users
                 WHERE (created_at, id) < ($1, $2)
                 ORDER BY created_at DESC, id DESC LIMIT $3`
        args = []any{c.CreatedAt, c.ID, limit + 1}
    }

    users, err := r.scanUsers(ctx, query, args...)
    if err != nil {
        return nil, "", err
    }

    var nextCursor string
    if len(users) > limit {
        last := users[limit]
        nextCursor = encodeCursorKeyset(last.CreatedAt, last.ID)
        users = users[:limit]
    }

    return users, nextCursor, nil
}
```

## Praktik Baxış

### HTTP Handler

```go
// GET /orders?cursor=<token>&limit=20
// GET /users?page=3&per_page=20

func (h *OrderHandler) List(w http.ResponseWriter, r *http.Request) {
    cursor := r.URL.Query().Get("cursor")
    limit, _ := strconv.Atoi(r.URL.Query().Get("limit"))

    result, err := h.store.ListCursor(r.Context(), cursor, limit)
    if err != nil {
        http.Error(w, err.Error(), http.StatusInternalServerError)
        return
    }

    // Link header — standart API praktikası
    if result.Meta.NextCursor != "" {
        w.Header().Set("Link", fmt.Sprintf(`</orders?cursor=%s>; rel="next"`, result.Meta.NextCursor))
    }

    json.NewEncoder(w).Encode(result)
}
```

### Hansı pagination nə zaman

```
Offset/Limit:
  ✓ Admin panel, az data (< 100k sətir)
  ✓ Müəyyən səhifəyə atlamaq lazımdır (page=42)
  ✗ Böyük dataset, real-time data

Cursor-based:
  ✓ Infinite scroll, mobile
  ✓ Real-time data (yeni əlavə/silinmə zamanı təkrar/atlanma olmur)
  ✗ Müəyyən səhifəyə atlamaq mümkün deyil

Keyset:
  ✓ Ən böyük cədvəllər (milyonlar)
  ✓ Yüksək sorğu sürəti tələbi
  ✗ Composite sort mürəkkəbdir, filter çoxalınca query çətinləşir
```

### Total count məsələsi

```go
// COUNT(*) böyük cədvəllərdə yavaşdır (sequential scan)
// Alternativlər:

// 1. Approximate count (PostgreSQL):
// SELECT reltuples::bigint FROM pg_class WHERE relname = 'orders';
// -- Tam deyil, amma anında

// 2. Cache total count — 5 dəqiqəlik TTL ilə Redis-də saxla
// 3. Cursor pagination-da total verməyin — sadəcə hasMore

// 4. Cursor + max pages:
// hasMore yalnız son LIMIT+1 sətir varmı onu bildirir
```

### Sort + Filter ilə keyset

```go
// Filter + keyset mürəkkəbləşir:
// Yalnız indexed sahələr üzrə filter + keyset səmərəlidir
// status filterlənəcəksə: CREATE INDEX ON orders(status, created_at DESC, id DESC);

query := `
    SELECT id, total, status, created_at FROM orders
    WHERE status = $1
    AND (created_at, id) < ($2, $3)
    ORDER BY created_at DESC, id DESC
    LIMIT $4`
```

## Praktik Tapşırıqlar

1. **Offset handler:** `GET /products?page=2&per_page=15` → offset/limit, total ile birlikdə cavab
2. **Cursor API:** `GET /notifications?cursor=xxx&limit=20` → cursor, hasMore
3. **Benchmark:** 1M sətirli cədvəldə OFFSET 900000 vs keyset fərqini ölç (`EXPLAIN ANALYZE`)
4. **Link header:** RFC 5988 standartında `Link: </api/v1/users?cursor=xxx>; rel="next"` header yaz
5. **Generics wrapper:** `PagedResult[T]` generik struct, `CursorResult[T]` generik struct

## PHP ilə Müqayisə

```
Laravel                          Go
────────────────────────────────────────────────────────────
$query->paginate(20)         →   offset + LIMIT + COUNT (manual)
$query->cursorPaginate(20)   →   cursor-based (el ilə)
LengthAwarePaginator         →   PagedResult[T] struct
SimplePaginator              →   CursorResult[T] struct
->links()                    →   Link header (RFC 5988)
```

**Fərqlər:**
- Laravel `paginate()` avtomatik COUNT sorğusu edir, `cursorPaginate()` cursor generasiya edir
- Go-da hər şey əl ilə yazılır — daha çox boilerplate, amma daha çox nəzarət
- Laravel cursor — `created_at + id` composite cursor (base64 encoded)
- Go-da generics ilə `PagedResult[User]`, `PagedResult[Order]` tipli response mümkündür

## Əlaqəli Mövzular

- [05-database.md](05-database.md) — database/sql, query yazma
- [38-orm-and-sqlx.md](06-orm-and-sqlx.md) — sqlx, GORM pagination
- [29-generics.md](../core/29-generics.md) — generic wrapper tipləri
- [25-api-versioning.md](25-api-versioning.md) — API dizayn qaydaları
- [93-swagger-openapi.md](31-swagger-openapi.md) — pagination response annotation
